<?php

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);

    echo json_encode([
        'status' => false,
        'message' => 'Only GET method is allowed.'
    ]);

    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/middleware.php';


// ============================================================
// AUTHENTICATION
// ============================================================

$user = requireAnyUser();

$userId = (int) $user['sub'];
$userRole = $user['role'];


// ============================================================
// ALLOWED ROLES
// ============================================================

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
// ALLOWED QUERY PARAMETERS
// ============================================================

$allowedParams = [
    'booking_id',
    'booking_reference',
    'customer_id',
    'customer_search',
    'search',
    'check_in_date',
    'check_out_date',
    'booking_status',
    'payment_status',
    'min_amount',
    'max_amount',
    'page',
    'limit',
    'sort_by',
    'sort_order'
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
// BOOKING ID VALIDATION
// ============================================================

$bookingId = null;

if (isset($_GET['booking_id'])) {

    $value = $_GET['booking_id'];

    if (
        !is_string($value) ||
        !preg_match('/^[0-9]+$/', $value)
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'booking_id must be a positive integer.'
        ]);

        exit;
    }

    $bookingId = (int) $value;

    if ($bookingId <= 0) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'booking_id must be greater than 0.'
        ]);

        exit;
    }
}


// ============================================================
// BOOKING REFERENCE VALIDATION
// ============================================================

$bookingReference = null;

if (isset($_GET['booking_reference'])) {

    if (!is_string($_GET['booking_reference'])) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'booking_reference must be a string.'
        ]);

        exit;
    }

    $bookingReference = trim(
        $_GET['booking_reference']
    );

    if ($bookingReference === '') {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'booking_reference cannot be empty.'
        ]);

        exit;
    }

    if (strlen($bookingReference) > 50) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'booking_reference cannot exceed 50 characters.'
        ]);

        exit;
    }

    if (
        !preg_match(
            '/^[A-Za-z0-9_-]+$/',
            $bookingReference
        )
    ) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Invalid booking_reference format.'
        ]);

        exit;
    }
}


// ============================================================
// CUSTOMER ID VALIDATION
// ============================================================

$customerId = null;

if (isset($_GET['customer_id'])) {

    $value = $_GET['customer_id'];

    if (
        !is_string($value) ||
        !preg_match('/^[0-9]+$/', $value)
    ) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'customer_id must be a positive integer.'
        ]);

        exit;
    }

    $customerId = (int) $value;

    if ($customerId <= 0) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'customer_id must be greater than 0.'
        ]);

        exit;
    }
}


// ============================================================
// CUSTOMER SEARCH VALIDATION
// ============================================================

$customerSearch = null;

if (isset($_GET['customer_search'])) {

    if (!is_string($_GET['customer_search'])) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'customer_search must be a string.'
        ]);

        exit;
    }

    $customerSearch = trim(
        $_GET['customer_search']
    );

    if (strlen($customerSearch) > 150) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'customer_search cannot exceed 150 characters.'
        ]);

        exit;
    }
}


// ============================================================
// GENERAL SEARCH VALIDATION
// ============================================================

$search = null;

if (isset($_GET['search'])) {

    if (!is_string($_GET['search'])) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'search must be a string.'
        ]);

        exit;
    }

    $search = trim(
        $_GET['search']
    );

    if (strlen($search) > 150) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'search cannot exceed 150 characters.'
        ]);

        exit;
    }
}


// ============================================================
// DATE VALIDATION FUNCTION
// ============================================================

function validateBookingDate($date, $fieldName)
{
    if (
        !is_string($date) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
    ) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => "{$fieldName} must be in YYYY-MM-DD format."
        ]);

        exit;
    }

    $dateObject = DateTime::createFromFormat(
        '!Y-m-d',
        $date
    );

    if (
        !$dateObject ||
        $dateObject->format('Y-m-d') !== $date
    ) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => "Invalid {$fieldName}."
        ]);

        exit;
    }

    return $date;
}


// ============================================================
// DATE FILTERS
// ============================================================

$checkInDate = null;
$checkOutDate = null;

if (isset($_GET['check_in_date'])) {

    $checkInDate = validateBookingDate(
        $_GET['check_in_date'],
        'check_in_date'
    );
}

if (isset($_GET['check_out_date'])) {

    $checkOutDate = validateBookingDate(
        $_GET['check_out_date'],
        'check_out_date'
    );
}


if (
    $checkInDate !== null &&
    $checkOutDate !== null
) {

    $checkInObject = new DateTime($checkInDate);
    $checkOutObject = new DateTime($checkOutDate);

    if ($checkOutObject < $checkInObject) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'check_out_date cannot be before check_in_date.'
        ]);

        exit;
    }
}


// ============================================================
// BOOKING STATUS
// ============================================================

$allowedBookingStatuses = [
    'pending',
    'confirmed',
    'checked_in',
    'checked_out',
    'cancelled',
    'no_show'
];

$bookingStatus = null;

if (isset($_GET['booking_status'])) {

    if (!is_string($_GET['booking_status'])) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'booking_status must be a string.'
        ]);

        exit;
    }

    $bookingStatus = trim(
        $_GET['booking_status']
    );

    if (
        !in_array(
            $bookingStatus,
            $allowedBookingStatuses,
            true
        )
    ) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Invalid booking_status.',
            'allowed_values' => $allowedBookingStatuses
        ]);

        exit;
    }
}


// ============================================================
// PAYMENT STATUS
// ============================================================

$allowedPaymentStatuses = [
    'pending',
    'partial',
    'paid',
    'failed',
    'refunded'
];

$paymentStatus = null;

if (isset($_GET['payment_status'])) {

    if (!is_string($_GET['payment_status'])) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'payment_status must be a string.'
        ]);

        exit;
    }

    $paymentStatus = trim(
        $_GET['payment_status']
    );

    if (
        !in_array(
            $paymentStatus,
            $allowedPaymentStatuses,
            true
        )
    ) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Invalid payment_status.',
            'allowed_values' => $allowedPaymentStatuses
        ]);

        exit;
    }
}


// ============================================================
// AMOUNT VALIDATION
// ============================================================

$minAmount = null;
$maxAmount = null;

if (isset($_GET['min_amount'])) {

    $value = $_GET['min_amount'];

    if (
        !is_string($value) ||
        !preg_match(
            '/^\d+(?:\.\d{1,2})?$/',
            $value
        )
    ) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'min_amount must be a valid positive amount with maximum 2 decimal places.'
        ]);

        exit;
    }

    $minAmount = (float) $value;

    if ($minAmount < 0 || $minAmount > 100000000) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'min_amount must be between 0 and 100000000.'
        ]);

        exit;
    }
}


if (isset($_GET['max_amount'])) {

    $value = $_GET['max_amount'];

    if (
        !is_string($value) ||
        !preg_match(
            '/^\d+(?:\.\d{1,2})?$/',
            $value
        )
    ) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'max_amount must be a valid positive amount with maximum 2 decimal places.'
        ]);

        exit;
    }

    $maxAmount = (float) $value;

    if ($maxAmount < 0 || $maxAmount > 100000000) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'max_amount must be between 0 and 100000000.'
        ]);

        exit;
    }
}


if (
    $minAmount !== null &&
    $maxAmount !== null &&
    $minAmount > $maxAmount
) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'min_amount cannot be greater than max_amount.'
    ]);

    exit;
}


// ============================================================
// PAGE VALIDATION
// ============================================================

$page = 1;

if (isset($_GET['page'])) {

    $value = $_GET['page'];

    if (
        !is_string($value) ||
        !preg_match('/^[0-9]+$/', $value)
    ) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'page must be a positive integer.'
        ]);

        exit;
    }

    $page = (int) $value;

    if ($page <= 0) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'page must be greater than 0.'
        ]);

        exit;
    }
}


// ============================================================
// LIMIT VALIDATION
// ============================================================

$limit = 20;

if (isset($_GET['limit'])) {

    $value = $_GET['limit'];

    if (
        !is_string($value) ||
        !preg_match('/^[0-9]+$/', $value)
    ) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'limit must be a positive integer.'
        ]);

        exit;
    }

    $limit = (int) $value;

    if ($limit <= 0) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'limit must be greater than 0.'
        ]);

        exit;
    }

    if ($limit > 100) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'limit cannot exceed 100.'
        ]);

        exit;
    }
}


$offset = ($page - 1) * $limit;


// ============================================================
// SORT VALIDATION
// ============================================================

$sortMap = [
    'id' => 'rb.id',
    'booking_reference' => 'rb.booking_reference',
    'check_in_date' => 'rb.check_in_date',
    'check_out_date' => 'rb.check_out_date',
    'total_guests' => 'rb.total_guests',
    'total_amount' => 'rb.total_amount',
    'payment_status' => 'rb.payment_status',
    'booking_status' => 'rb.booking_status',
    'created_at' => 'rb.created_at',
    'updated_at' => 'rb.updated_at'
];

$sortBy = 'created_at';

if (isset($_GET['sort_by'])) {

    if (!is_string($_GET['sort_by'])) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'sort_by must be a string.'
        ]);

        exit;
    }

    $sortBy = trim(
        $_GET['sort_by']
    );

    if (!array_key_exists($sortBy, $sortMap)) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Invalid sort_by.',
            'allowed_values' => array_keys($sortMap)
        ]);

        exit;
    }
}


$sortOrder = 'DESC';

if (isset($_GET['sort_order'])) {

    if (!is_string($_GET['sort_order'])) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'sort_order must be a string.'
        ]);

        exit;
    }

    $sortOrder = strtoupper(
        trim($_GET['sort_order'])
    );

    if (
        !in_array(
            $sortOrder,
            [
                'ASC',
                'DESC'
            ],
            true
        )
    ) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'sort_order must be ASC or DESC.'
        ]);

        exit;
    }
}


// ============================================================
// BUILD WHERE
// ============================================================

$where = [];
$params = [];
$types = '';


// ============================================================
// CUSTOMER ACCESS CONTROL
// ============================================================

if ($userRole === 'customer') {

    /*
     * Customer can ONLY see own bookings.
     */

    $where[] = 'rb.customer_id = ?';

    $params[] = $userId;
    $types .= 'i';

} else {

    /*
     * Admin / Staff can optionally filter by customer.
     */

    if ($customerId !== null) {

        $where[] = 'rb.customer_id = ?';

        $params[] = $customerId;
        $types .= 'i';
    }
}


// ============================================================
// BOOKING ID
// ============================================================

if ($bookingId !== null) {

    $where[] = 'rb.id = ?';

    $params[] = $bookingId;
    $types .= 'i';
}


// ============================================================
// BOOKING REFERENCE
// ============================================================

if ($bookingReference !== null) {

    $where[] = 'rb.booking_reference = ?';

    $params[] = $bookingReference;
    $types .= 's';
}


// ============================================================
// CUSTOMER SEARCH
// ============================================================

if ($customerSearch !== null && $customerSearch !== '') {

    $searchValue = '%' . $customerSearch . '%';

    $where[] = "
        (
            c.name LIKE ?
            OR c.email LIKE ?
            OR c.phone LIKE ?
        )
    ";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= 'sss';
}


// ============================================================
// GENERAL SEARCH
// ============================================================

if ($search !== null && $search !== '') {

    $searchValue = '%' . $search . '%';

    $where[] = "
        (
            rb.booking_reference LIKE ?
            OR c.name LIKE ?
            OR c.email LIKE ?
            OR c.phone LIKE ?
        )
    ";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= 'ssss';
}


// ============================================================
// CHECK-IN DATE
// ============================================================

if ($checkInDate !== null) {

    $where[] = 'rb.check_in_date >= ?';

    $params[] = $checkInDate;
    $types .= 's';
}


// ============================================================
// CHECK-OUT DATE
// ============================================================

if ($checkOutDate !== null) {

    $where[] = 'rb.check_out_date <= ?';

    $params[] = $checkOutDate;
    $types .= 's';
}


// ============================================================
// BOOKING STATUS
// ============================================================

if ($bookingStatus !== null) {

    $where[] = 'rb.booking_status = ?';

    $params[] = $bookingStatus;
    $types .= 's';
}


// ============================================================
// PAYMENT STATUS
// ============================================================

if ($paymentStatus !== null) {

    $where[] = 'rb.payment_status = ?';

    $params[] = $paymentStatus;
    $types .= 's';
}


// ============================================================
// MIN AMOUNT
// ============================================================

if ($minAmount !== null) {

    $where[] = 'rb.total_amount >= ?';

    $params[] = $minAmount;
    $types .= 'd';
}


// ============================================================
// MAX AMOUNT
// ============================================================

if ($maxAmount !== null) {

    $where[] = 'rb.total_amount <= ?';

    $params[] = $maxAmount;
    $types .= 'd';
}


$whereSql = '';

if (!empty($where)) {

    $whereSql =
        'WHERE ' .
        implode(
            ' AND ',
            $where
        );
}


// ============================================================
// COUNT BOOKINGS
// ============================================================

$countSql = "
    SELECT
        COUNT(*) AS total
    FROM room_bookings rb

    INNER JOIN customers c
        ON c.id = rb.customer_id

    $whereSql
";

$stmt = $conn->prepare($countSql);

if (!$stmt) {

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare booking count query.'
    ]);

    exit;
}


if ($types !== '') {

    $stmt->bind_param(
        $types,
        ...$params
    );
}


if (!$stmt->execute()) {

    $stmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to count bookings.'
    ]);

    exit;
}


$result = $stmt->get_result();

$countData = $result->fetch_assoc();

$stmt->close();

$totalRecords = (int) (
    $countData['total'] ?? 0
);


// ============================================================
// FETCH BOOKINGS
// ============================================================

$mainSql = "
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

    $whereSql

    ORDER BY
        {$sortMap[$sortBy]} $sortOrder

    LIMIT ? OFFSET ?
";


$stmt = $conn->prepare($mainSql);

if (!$stmt) {

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare booking list query.'
    ]);

    exit;
}


$listTypes = $types . 'ii';

$listParams = $params;

$listParams[] = $limit;
$listParams[] = $offset;


$stmt->bind_param(
    $listTypes,
    ...$listParams
);


if (!$stmt->execute()) {

    $stmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to fetch bookings.'
    ]);

    exit;
}


$result = $stmt->get_result();

$bookings = [];

while ($row = $result->fetch_assoc()) {

    $id = (int) $row['id'];

    $checkIn = new DateTime(
        $row['check_in_date']
    );

    $checkOut = new DateTime(
        $row['check_out_date']
    );

    $nights = (int) $checkIn->diff(
        $checkOut
    )->days;

    $bookings[$id] = [
        'id' => $id,

        'booking_reference' =>
            $row['booking_reference'],

        'customer' => [
            'id' => (int) $row['customer_id'],
            'name' => $row['customer_name'],
            'email' => $row['customer_email'],
            'phone' => $row['customer_phone']
        ],

        'check_in_date' =>
            $row['check_in_date'],

        'check_out_date' =>
            $row['check_out_date'],

        'number_of_nights' =>
            $nights,

        'total_guests' =>
            (int) $row['total_guests'],

        'special_request' =>
            $row['special_request'],

        'pricing' => [
            'room_subtotal' =>
                number_format(
                    (float) $row['room_subtotal'],
                    2,
                    '.',
                    ''
                ),

            'gst_rate' =>
                number_format(
                    (float) $row['gst_rate'],
                    2,
                    '.',
                    ''
                ),

            'gst_amount' =>
                number_format(
                    (float) $row['gst_amount'],
                    2,
                    '.',
                    ''
                ),

            'total_amount' =>
                number_format(
                    (float) $row['total_amount'],
                    2,
                    '.',
                    ''
                ),

            'currency' => 'INR',

            'price_includes_gst' => true
        ],

        'payment_status' =>
            $row['payment_status'],

        'booking_status' =>
            $row['booking_status'],

        'cancellation_reason' =>
            $row['cancellation_reason'],

        'rooms' => [],

        'food_packages' => [],

        'food_items' => [],

        'payments' => [],

        'created_at' =>
            $row['created_at'],

        'updated_at' =>
            $row['updated_at']
    ];
}

$stmt->close();


// ============================================================
// NO RECORDS
// ============================================================

if (empty($bookings)) {

    http_response_code(200);

    echo json_encode([
        'status' => true,
        'message' => 'No bookings found.',
        'data' => [],
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total_records' => 0,
            'total_pages' => 0,
            'has_previous_page' => false,
            'has_next_page' => false
        ]
    ]);

    exit;
}


// ============================================================
// BOOKING IDS
// ============================================================

$bookingIds = array_keys(
    $bookings
);

$placeholders = implode(
    ',',
    array_fill(
        0,
        count($bookingIds),
        '?'
    )
);

$idTypes = str_repeat(
    'i',
    count($bookingIds)
);


// ============================================================
// FETCH BOOKING ROOMS
// ============================================================

$roomSql = "
    SELECT
        br.id,
        br.booking_id,
        br.room_id,
        br.room_price_per_night,
        br.number_of_nights,
        br.room_total,

        r.room_number,
        r.name AS room_name,

        rt.id AS room_type_id,
        rt.name AS room_type_name

    FROM booking_rooms br

    INNER JOIN rooms r
        ON r.id = br.room_id

    INNER JOIN room_types rt
        ON rt.id = r.room_type_id

    WHERE br.booking_id IN ($placeholders)

    ORDER BY
        br.booking_id ASC,
        br.id ASC
";


$stmt = $conn->prepare($roomSql);

if (!$stmt) {

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare booking rooms query.'
    ]);

    exit;
}


$stmt->bind_param(
    $idTypes,
    ...$bookingIds
);


if (!$stmt->execute()) {

    $stmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to fetch booking rooms.'
    ]);

    exit;
}


$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $bookingIdValue =
        (int) $row['booking_id'];

    if (!isset($bookings[$bookingIdValue])) {
        continue;
    }

    $bookings[$bookingIdValue]['rooms'][] = [
        'id' => (int) $row['id'],

        'room_id' =>
            (int) $row['room_id'],

        'room_number' =>
            $row['room_number'],

        'room_name' =>
            $row['room_name'],

        'room_type' => [
            'id' =>
                (int) $row['room_type_id'],

            'name' =>
                $row['room_type_name']
        ],

        'room_price_per_night' =>
            number_format(
                (float) $row['room_price_per_night'],
                2,
                '.',
                ''
            ),

        'number_of_nights' =>
            (int) $row['number_of_nights'],

        'room_total' =>
            number_format(
                (float) $row['room_total'],
                2,
                '.',
                ''
            )
    ];
}

$stmt->close();


// ============================================================
// FETCH BOOKING FOOD PACKAGES
// ============================================================

$packageSql = "
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

    WHERE bfp.booking_id IN ($placeholders)

    ORDER BY
        bfp.booking_id ASC,
        bfp.id ASC
";


$stmt = $conn->prepare($packageSql);

if (!$stmt) {

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare booking food package query.'
    ]);

    exit;
}


$stmt->bind_param(
    $idTypes,
    ...$bookingIds
);


if (!$stmt->execute()) {

    $stmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to fetch booking food packages.'
    ]);

    exit;
}


$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $bookingIdValue =
        (int) $row['booking_id'];

    if (!isset($bookings[$bookingIdValue])) {
        continue;
    }

    $bookings[$bookingIdValue]['food_packages'][] = [
        'id' => (int) $row['id'],

        'package_id' =>
            (int) $row['package_id'],

        'name' =>
            $row['package_name'],

        'description' =>
            $row['package_description'],

        'meal_type' =>
            $row['meal_type'],

        'quantity' =>
            (int) $row['quantity'],

        'notes' =>
            $row['notes'],

        'status' =>
            $row['package_status']
    ];
}

$stmt->close();


// ============================================================
// FETCH BOOKING FOOD ITEMS
// ============================================================

$foodSql = "
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

    WHERE bfi.booking_id IN ($placeholders)

    ORDER BY
        bfi.booking_id ASC,
        bfi.id ASC
";


$stmt = $conn->prepare($foodSql);

if (!$stmt) {

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare booking food item query.'
    ]);

    exit;
}


$stmt->bind_param(
    $idTypes,
    ...$bookingIds
);


if (!$stmt->execute()) {

    $stmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to fetch booking food items.'
    ]);

    exit;
}


$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $bookingIdValue =
        (int) $row['booking_id'];

    if (!isset($bookings[$bookingIdValue])) {
        continue;
    }


    $category = null;

    if ($row['category_id'] !== null) {

        $category = [
            'id' =>
                (int) $row['category_id'],

            'name' =>
                $row['category_name']
        ];
    }


    $bookings[$bookingIdValue]['food_items'][] = [

        'id' =>
            (int) $row['id'],

        'food_item_id' =>
            (int) $row['food_item_id'],

        'name' =>
            $row['food_item_name'],

        'description' =>
            $row['food_item_description'],

        'image_path' =>
            $row['food_item_image'],

        'food_type' =>
            $row['food_type'],

        'quantity' =>
            (int) $row['quantity'],

        'notes' =>
            $row['notes'],

        'status' =>
            $row['food_item_status'],

        'category' =>
            $category
    ];
}

$stmt->close();


// ============================================================
// FETCH PAYMENTS
// ============================================================

$paymentSql = "
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

    WHERE p.booking_id IN ($placeholders)

    ORDER BY
        p.booking_id ASC,
        p.id DESC
";


$stmt = $conn->prepare($paymentSql);

if (!$stmt) {

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare payment query.'
    ]);

    exit;
}


$stmt->bind_param(
    $idTypes,
    ...$bookingIds
);


if (!$stmt->execute()) {

    $stmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to fetch booking payments.'
    ]);

    exit;
}


$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $bookingIdValue =
        (int) $row['booking_id'];

    if (!isset($bookings[$bookingIdValue])) {
        continue;
    }


    $bookings[$bookingIdValue]['payments'][] = [

        'id' =>
            (int) $row['id'],

        'payment_gateway' =>
            $row['payment_gateway'],

        'razorpay_order_id' =>
            $row['razorpay_order_id'],

        'razorpay_payment_id' =>
            $row['razorpay_payment_id'],

        'razorpay_signature' =>
            $row['razorpay_signature'],

        'amount' =>
            number_format(
                (float) $row['amount'],
                2,
                '.',
                ''
            ),

        'currency' =>
            $row['currency'],

        'payment_status' =>
            $row['payment_status'],

        'payment_method' =>
            $row['payment_method'],

        'paid_at' =>
            $row['paid_at'],

        'created_at' =>
            $row['created_at'],

        'updated_at' =>
            $row['updated_at']
    ];
}

$stmt->close();


// ============================================================
// REINDEX
// ============================================================

$bookings = array_values(
    $bookings
);


// ============================================================
// PAGINATION
// ============================================================

$totalPages = $totalRecords > 0
    ? (int) ceil(
        $totalRecords / $limit
    )
    : 0;


// ============================================================
// RESPONSE
// ============================================================

http_response_code(200);

echo json_encode([

    'status' => true,

    'message' =>
        'Bookings fetched successfully.',

    'data' => $bookings,

    'pagination' => [

        'current_page' =>
            $page,

        'per_page' =>
            $limit,

        'total_records' =>
            $totalRecords,

        'total_pages' =>
            $totalPages,

        'has_previous_page' =>
            $page > 1,

        'has_next_page' =>
            $page < $totalPages
    ],

    'filters' => [

        'booking_id' =>
            $bookingId,

        'booking_reference' =>
            $bookingReference,

        'customer_id' =>
            $customerId,

        'customer_search' =>
            $customerSearch,

        'search' =>
            $search,

        'check_in_date' =>
            $checkInDate,

        'check_out_date' =>
            $checkOutDate,

        'booking_status' =>
            $bookingStatus,

        'payment_status' =>
            $paymentStatus,

        'min_amount' =>
            $minAmount,

        'max_amount' =>
            $maxAmount
    ],

    'sorting' => [

        'sort_by' =>
            $sortBy,

        'sort_order' =>
            $sortOrder
    ]

]);

exit;