<?php

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');


// ============================================================
// CORS PREFLIGHT
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {

    http_response_code(200);

    exit;
}


// ============================================================
// METHOD VALIDATION
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {

    http_response_code(405);

    echo json_encode([
        'status' => false,
        'message' => 'Only GET method is allowed.'
    ]);

    exit;
}


// ============================================================
// CONFIG
// ============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/middleware.php';


// ============================================================
// AUTHENTICATION
// ============================================================

$user = requireAnyUser();

$userId = (int) $user['sub'];
$userRole = $user['role'];


// ============================================================
// USER VALIDATION
// ============================================================

if ($userId <= 0) {

    http_response_code(401);

    echo json_encode([
        'status' => false,
        'message' => 'Invalid user information.'
    ]);

    exit;
}


if (
    !in_array(
        $userRole,
        [
            'admin',
            'staff',
            'customer'
        ],
        true
    )
) {

    http_response_code(403);

    echo json_encode([
        'status' => false,
        'message' => 'Invalid user role.'
    ]);

    exit;
}


// ============================================================
// QUERY PARAMETER VALIDATION
// ============================================================

$allowedParams = [
    'booking_id'
];

$unknownParams = array_diff(
    array_keys($_GET),
    $allowedParams
);

if (!empty($unknownParams)) {

    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' => 'Unknown query parameter(s) found.',
        'unknown_parameters' => array_values($unknownParams)
    ]);

    exit;
}


// ============================================================
// BOOKING ID REQUIRED
// ============================================================

if (!isset($_GET['booking_id'])) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'booking_id is required.'
    ]);

    exit;
}


// ============================================================
// BOOKING ID TYPE VALIDATION
// ============================================================

$bookingIdInput = $_GET['booking_id'];

if (
    !is_string($bookingIdInput) ||
    !preg_match(
        '/^[0-9]+$/',
        $bookingIdInput
    )
) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'booking_id must be a positive integer.'
    ]);

    exit;
}


$bookingId = (int) $bookingIdInput;


// ============================================================
// BOOKING ID RANGE VALIDATION
// ============================================================

if ($bookingId <= 0) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'booking_id must be greater than 0.'
    ]);

    exit;
}


// ============================================================
// FETCH BOOKING
// ============================================================

if ($userRole === 'customer') {

    /*
     * Customer can view ONLY their own booking.
     */

    $bookingQuery = $conn->prepare("
        SELECT
            rb.id,
            rb.booking_reference,
            rb.customer_id,
            rb.check_in_date,
            rb.check_out_date,
            rb.total_guests,
            rb.special_request,
            rb.room_subtotal,
            rb.gst_rate,
            rb.gst_amount,
            rb.total_amount,
            rb.payment_status,
            rb.booking_status,
            rb.cancellation_reason,
            rb.created_at,
            rb.updated_at,

            c.name AS customer_name,
            c.email AS customer_email,
            c.phone AS customer_phone

        FROM room_bookings rb

        INNER JOIN customers c
            ON c.id = rb.customer_id

        WHERE rb.id = ?
          AND rb.customer_id = ?

        LIMIT 1
    ");

    if (!$bookingQuery) {

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Failed to prepare booking query.'
        ]);

        exit;
    }


    $bookingQuery->bind_param(
        'ii',
        $bookingId,
        $userId
    );

} else {

    /*
     * Admin / Staff can view any booking.
     */

    $bookingQuery = $conn->prepare("
        SELECT
            rb.id,
            rb.booking_reference,
            rb.customer_id,
            rb.check_in_date,
            rb.check_out_date,
            rb.total_guests,
            rb.special_request,
            rb.room_subtotal,
            rb.gst_rate,
            rb.gst_amount,
            rb.total_amount,
            rb.payment_status,
            rb.booking_status,
            rb.cancellation_reason,
            rb.created_at,
            rb.updated_at,

            c.name AS customer_name,
            c.email AS customer_email,
            c.phone AS customer_phone

        FROM room_bookings rb

        INNER JOIN customers c
            ON c.id = rb.customer_id

        WHERE rb.id = ?

        LIMIT 1
    ");

    if (!$bookingQuery) {

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Failed to prepare booking query.'
        ]);

        exit;
    }


    $bookingQuery->bind_param(
        'i',
        $bookingId
    );
}


// ============================================================
// EXECUTE BOOKING QUERY
// ============================================================

if (!$bookingQuery->execute()) {

    $bookingQuery->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to fetch booking.'
    ]);

    exit;
}


$result = $bookingQuery->get_result();

$booking = $result->fetch_assoc();

$bookingQuery->close();


// ============================================================
// BOOKING NOT FOUND / ACCESS DENIED
// ============================================================

if (!$booking) {

    /*
     * Do not reveal whether another customer's booking exists.
     */

    http_response_code(404);

    echo json_encode([
        'status' => false,
        'message' => 'Booking not found.'
    ]);

    exit;
}


// ============================================================
// CALCULATE NIGHTS
// ============================================================

$checkInObject = DateTime::createFromFormat(
    '!Y-m-d',
    $booking['check_in_date']
);

$checkOutObject = DateTime::createFromFormat(
    '!Y-m-d',
    $booking['check_out_date']
);


if (
    !$checkInObject ||
    !$checkOutObject
) {

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Invalid booking date data.'
    ]);

    exit;
}


$numberOfNights = (int) $checkInObject->diff(
    $checkOutObject
)->days;


// ============================================================
// BASIC BOOKING DATA
// ============================================================

$bookingData = [

    'id' =>
        (int) $booking['id'],

    'booking_reference' =>
        $booking['booking_reference'],

    'customer' => [

        'id' =>
            (int) $booking['customer_id'],

        'name' =>
            $booking['customer_name'],

        'email' =>
            $booking['customer_email'],

        'phone' =>
            $booking['customer_phone']
    ],

    'check_in_date' =>
        $booking['check_in_date'],

    'check_out_date' =>
        $booking['check_out_date'],

    'number_of_nights' =>
        $numberOfNights,

    'total_guests' =>
        (int) $booking['total_guests'],

    'special_request' =>
        $booking['special_request'],

    'pricing' => [

        'room_subtotal' =>
            number_format(
                (float) $booking['room_subtotal'],
                2,
                '.',
                ''
            ),

        'gst_rate' =>
            number_format(
                (float) $booking['gst_rate'],
                2,
                '.',
                ''
            ),

        'gst_amount' =>
            number_format(
                (float) $booking['gst_amount'],
                2,
                '.',
                ''
            ),

        'total_amount' =>
            number_format(
                (float) $booking['total_amount'],
                2,
                '.',
                ''
            ),

        'currency' =>
            'INR',

        'price_includes_gst' =>
            true
    ],

    'payment_status' =>
        $booking['payment_status'],

    'booking_status' =>
        $booking['booking_status'],

    'cancellation_reason' =>
        $booking['cancellation_reason'],

    'rooms' => [],

    'food_packages' => [],

    'food_items' => [],

    'payments' => [],

    'activity_logs' => [],

    'created_at' =>
        $booking['created_at'],

    'updated_at' =>
        $booking['updated_at']
];


// ============================================================
// FETCH BOOKING ROOMS
// ============================================================

$roomQuery = $conn->prepare("
    SELECT
        br.id,
        br.booking_id,
        br.room_id,
        br.room_price_per_night,
        br.number_of_nights,
        br.room_total,

        r.room_number,
        r.name AS room_name,
        r.description AS room_description,
        r.bed_count,
        r.guest_capacity,

        rt.id AS room_type_id,
        rt.name AS room_type_name,
        rt.slug AS room_type_slug

    FROM booking_rooms br

    INNER JOIN rooms r
        ON r.id = br.room_id

    INNER JOIN room_types rt
        ON rt.id = r.room_type_id

    WHERE br.booking_id = ?

    ORDER BY
        br.id ASC
");


if (!$roomQuery) {

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare booking room query.'
    ]);

    exit;
}


$roomQuery->bind_param(
    'i',
    $bookingId
);


if (!$roomQuery->execute()) {

    $roomQuery->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to fetch booking rooms.'
    ]);

    exit;
}


$roomResult = $roomQuery->get_result();


while ($room = $roomResult->fetch_assoc()) {

    $bookingData['rooms'][] = [

        'id' =>
            (int) $room['id'],

        'room_id' =>
            (int) $room['room_id'],

        'room_number' =>
            $room['room_number'],

        'room_name' =>
            $room['room_name'],

        'room_description' =>
            $room['room_description'],

        'bed_count' =>
            (int) $room['bed_count'],

        'guest_capacity' =>
            (int) $room['guest_capacity'],

        'room_type' => [

            'id' =>
                (int) $room['room_type_id'],

            'name' =>
                $room['room_type_name'],

            'slug' =>
                $room['room_type_slug']
        ],

        'room_price_per_night' =>
            number_format(
                (float) $room['room_price_per_night'],
                2,
                '.',
                ''
            ),

        'number_of_nights' =>
            (int) $room['number_of_nights'],

        'room_total' =>
            number_format(
                (float) $room['room_total'],
                2,
                '.',
                ''
            )
    ];
}


$roomQuery->close();


// ============================================================
// FETCH FOOD PACKAGES
// ============================================================

$packageQuery = $conn->prepare("
    SELECT
        bfp.id,
        bfp.booking_id,
        bfp.package_id,
        bfp.quantity,
        bfp.notes,

        rfp.name AS package_name,
        rfp.description AS package_description,
        rfp.meal_type,
        rfp.status AS package_status

    FROM booking_food_packages bfp

    INNER JOIN room_food_packages rfp
        ON rfp.id = bfp.package_id

    WHERE bfp.booking_id = ?

    ORDER BY
        bfp.id ASC
");


if (!$packageQuery) {

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare food package query.'
    ]);

    exit;
}


$packageQuery->bind_param(
    'i',
    $bookingId
);


if (!$packageQuery->execute()) {

    $packageQuery->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to fetch booking food packages.'
    ]);

    exit;
}


$packageResult = $packageQuery->get_result();


while ($package = $packageResult->fetch_assoc()) {

    $bookingData['food_packages'][] = [

        'id' =>
            (int) $package['id'],

        'package_id' =>
            (int) $package['package_id'],

        'name' =>
            $package['package_name'],

        'description' =>
            $package['package_description'],

        'meal_type' =>
            $package['meal_type'],

        'quantity' =>
            (int) $package['quantity'],

        'notes' =>
            $package['notes'],

        'status' =>
            $package['package_status']
    ];
}


$packageQuery->close();


// ============================================================
// FETCH CUSTOM FOOD ITEMS
// ============================================================

$foodQuery = $conn->prepare("
    SELECT
        bfi.id,
        bfi.booking_id,
        bfi.food_item_id,
        bfi.quantity,
        bfi.notes,

        fi.name AS food_item_name,
        fi.description AS food_item_description,
        fi.image_path AS food_item_image,
        fi.food_type,
        fi.status AS food_item_status,

        fc.id AS category_id,
        fc.name AS category_name

    FROM booking_food_items bfi

    INNER JOIN food_items fi
        ON fi.id = bfi.food_item_id

    LEFT JOIN food_categories fc
        ON fc.id = fi.food_category_id

    WHERE bfi.booking_id = ?

    ORDER BY
        bfi.id ASC
");


if (!$foodQuery) {

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare food item query.'
    ]);

    exit;
}


$foodQuery->bind_param(
    'i',
    $bookingId
);


if (!$foodQuery->execute()) {

    $foodQuery->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to fetch booking food items.'
    ]);

    exit;
}


$foodResult = $foodQuery->get_result();


while ($food = $foodResult->fetch_assoc()) {

    $category = null;

    if ($food['category_id'] !== null) {

        $category = [

            'id' =>
                (int) $food['category_id'],

            'name' =>
                $food['category_name']
        ];
    }


    $bookingData['food_items'][] = [

        'id' =>
            (int) $food['id'],

        'food_item_id' =>
            (int) $food['food_item_id'],

        'name' =>
            $food['food_item_name'],

        'description' =>
            $food['food_item_description'],

        'image_path' =>
            $food['food_item_image'],

        'food_type' =>
            $food['food_type'],

        'quantity' =>
            (int) $food['quantity'],

        'notes' =>
            $food['notes'],

        'status' =>
            $food['food_item_status'],

        'category' =>
            $category
    ];
}


$foodQuery->close();


// ============================================================
// FETCH PAYMENTS
// ============================================================
//
// gateway_response is intentionally NOT returned.
// It can contain internal gateway data and is not required
// by frontend for booking display.
//

$paymentQuery = $conn->prepare("
    SELECT
        p.id,
        p.booking_id,
        p.payment_gateway,
        p.razorpay_order_id,
        p.razorpay_payment_id,
        p.razorpay_signature,
        p.amount,
        p.currency,
        p.payment_status,
        p.payment_method,
        p.paid_at,
        p.created_at,
        p.updated_at

    FROM payments p

    WHERE p.booking_id = ?

    ORDER BY
        p.id DESC
");


if (!$paymentQuery) {

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare payment query.'
    ]);

    exit;
}


$paymentQuery->bind_param(
    'i',
    $bookingId
);


if (!$paymentQuery->execute()) {

    $paymentQuery->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to fetch booking payments.'
    ]);

    exit;
}


$paymentResult = $paymentQuery->get_result();


while ($payment = $paymentResult->fetch_assoc()) {

    $bookingData['payments'][] = [

        'id' =>
            (int) $payment['id'],

        'payment_gateway' =>
            $payment['payment_gateway'],

        'razorpay_order_id' =>
            $payment['razorpay_order_id'],

        'razorpay_payment_id' =>
            $payment['razorpay_payment_id'],

        'razorpay_signature' =>
            $payment['razorpay_signature'],

        'amount' =>
            number_format(
                (float) $payment['amount'],
                2,
                '.',
                ''
            ),

        'currency' =>
            $payment['currency'],

        'payment_status' =>
            $payment['payment_status'],

        'payment_method' =>
            $payment['payment_method'],

        'paid_at' =>
            $payment['paid_at'],

        'created_at' =>
            $payment['created_at'],

        'updated_at' =>
            $payment['updated_at']
    ];
}


$paymentQuery->close();


// ============================================================
// FETCH ACTIVITY LOGS
// ============================================================

$activityQuery = $conn->prepare("
    SELECT
        bal.id,
        bal.booking_id,
        bal.action,
        bal.description,
        bal.performed_by_admin_id,
        bal.created_at,

        au.name AS performed_by_admin_name,
        au.email AS performed_by_admin_email

    FROM booking_activity_logs bal

    LEFT JOIN admin_users au
        ON au.id = bal.performed_by_admin_id

    WHERE bal.booking_id = ?

    ORDER BY
        bal.id DESC
");


if (!$activityQuery) {

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare activity log query.'
    ]);

    exit;
}


$activityQuery->bind_param(
    'i',
    $bookingId
);


if (!$activityQuery->execute()) {

    $activityQuery->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to fetch booking activity logs.'
    ]);

    exit;
}


$activityResult = $activityQuery->get_result();


while ($activity = $activityResult->fetch_assoc()) {

    $performedBy = null;

    if ($activity['performed_by_admin_id'] !== null) {

        $performedBy = [

            'id' =>
                (int) $activity['performed_by_admin_id'],

            'name' =>
                $activity['performed_by_admin_name'],

            'email' =>
                $activity['performed_by_admin_email']
        ];
    }


    $bookingData['activity_logs'][] = [

        'id' =>
            (int) $activity['id'],

        'action' =>
            $activity['action'],

        'description' =>
            $activity['description'],

        'performed_by' =>
            $performedBy,

        'created_at' =>
            $activity['created_at']
    ];
}


$activityQuery->close();


// ============================================================
// RESPONSE
// ============================================================

http_response_code(200);

echo json_encode([

    'status' => true,

    'message' =>
        'Booking details fetched successfully.',

    'data' =>
        $bookingData

]);

exit;