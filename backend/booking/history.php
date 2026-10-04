<?php

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

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


/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function historyError($message, $code = 400)
{
    http_response_code($code);

    echo json_encode([
        'status' => false,
        'message' => $message
    ]);

    exit;
}


function isValidDate($date)
{
    if (!is_string($date)) {
        return false;
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }

    $parts = explode('-', $date);

    return checkdate(
        (int) $parts[1],
        (int) $parts[2],
        (int) $parts[0]
    );
}


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

$user = requireAnyUser();

$userId = (int) $user['sub'];
$userRole = $user['role'];

$isCustomer = ($userRole === 'customer');
$isAdminOrStaff = in_array(
    $userRole,
    ['admin', 'staff'],
    true
);


/*
|--------------------------------------------------------------------------
| Allowed Query Parameters
|--------------------------------------------------------------------------
*/

$allowedParams = [
    'page',
    'limit',
    'search',
    'booking_id',
    'booking_reference',
    'customer_id',
    'check_in_date',
    'check_out_date',
    'booking_status',
    'payment_status',
    'min_amount',
    'max_amount',
    'sort_by',
    'sort_order'
];

foreach ($_GET as $key => $value) {

    if (!in_array($key, $allowedParams, true)) {
        historyError(
            "Unknown query parameter: {$key}"
        );
    }
}


/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

$page = $_GET['page'] ?? '1';
$limit = $_GET['limit'] ?? '20';

if (
    !preg_match('/^\d+$/', (string) $page) ||
    (int) $page < 1
) {
    historyError('Page must be a positive integer.');
}

if (
    !preg_match('/^\d+$/', (string) $limit) ||
    (int) $limit < 1 ||
    (int) $limit > 100
) {
    historyError(
        'Limit must be between 1 and 100.'
    );
}

$page = (int) $page;
$limit = (int) $limit;

$offset = ($page - 1) * $limit;


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

$search = null;

if (isset($_GET['search'])) {

    $search = trim($_GET['search']);

    if ($search === '') {
        historyError(
            'Search cannot be empty.'
        );
    }

    if (mb_strlen($search) > 150) {
        historyError(
            'Search cannot exceed 150 characters.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Booking ID
|--------------------------------------------------------------------------
*/

$bookingId = null;

if (isset($_GET['booking_id'])) {

    $value = $_GET['booking_id'];

    if (
        !preg_match('/^\d+$/', (string) $value) ||
        (int) $value <= 0
    ) {
        historyError(
            'Booking ID must be a positive integer.'
        );
    }

    $bookingId = (int) $value;
}


/*
|--------------------------------------------------------------------------
| Booking Reference
|--------------------------------------------------------------------------
*/

$bookingReference = null;

if (isset($_GET['booking_reference'])) {

    $bookingReference = trim(
        $_GET['booking_reference']
    );

    if ($bookingReference === '') {
        historyError(
            'Booking reference cannot be empty.'
        );
    }

    if (mb_strlen($bookingReference) > 50) {
        historyError(
            'Booking reference cannot exceed 50 characters.'
        );
    }

    if (
        !preg_match(
            '/^[A-Za-z0-9._-]+$/',
            $bookingReference
        )
    ) {
        historyError(
            'Invalid booking reference format.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Customer ID
|--------------------------------------------------------------------------
|
| Only admin/staff can filter by customer_id.
|
*/

$customerId = null;

if (isset($_GET['customer_id'])) {

    if ($isCustomer) {
        historyError(
            'Customers cannot filter booking history by customer ID.',
            403
        );
    }

    $value = $_GET['customer_id'];

    if (
        !preg_match('/^\d+$/', (string) $value) ||
        (int) $value <= 0
    ) {
        historyError(
            'Customer ID must be a positive integer.'
        );
    }

    $customerId = (int) $value;
}


/*
|--------------------------------------------------------------------------
| Check-in Date
|--------------------------------------------------------------------------
*/

$checkInDate = null;

if (isset($_GET['check_in_date'])) {

    $checkInDate = trim(
        $_GET['check_in_date']
    );

    if (!isValidDate($checkInDate)) {
        historyError(
            'Check-in date must be a valid date in YYYY-MM-DD format.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Check-out Date
|--------------------------------------------------------------------------
*/

$checkOutDate = null;

if (isset($_GET['check_out_date'])) {

    $checkOutDate = trim(
        $_GET['check_out_date']
    );

    if (!isValidDate($checkOutDate)) {
        historyError(
            'Check-out date must be a valid date in YYYY-MM-DD format.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Date Range Validation
|--------------------------------------------------------------------------
*/

if (
    $checkInDate !== null &&
    $checkOutDate !== null &&
    $checkOutDate <= $checkInDate
) {
    historyError(
        'Check-out date must be after check-in date.'
    );
}


/*
|--------------------------------------------------------------------------
| Booking Status
|--------------------------------------------------------------------------
*/

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
        historyError(
            'Invalid booking status.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Payment Status
|--------------------------------------------------------------------------
*/

$allowedPaymentStatuses = [
    'pending',
    'partial',
    'paid',
    'failed',
    'refunded'
];

$paymentStatus = null;

if (isset($_GET['payment_status'])) {

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
        historyError(
            'Invalid payment status.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Minimum Amount
|--------------------------------------------------------------------------
*/

$minAmount = null;

if (isset($_GET['min_amount'])) {

    $value = trim($_GET['min_amount']);

    if (
        $value === '' ||
        !preg_match('/^\d+(?:\.\d{1,2})?$/', $value)
    ) {
        historyError(
            'Minimum amount must be a valid positive amount with maximum 2 decimal places.'
        );
    }

    $minAmount = (float) $value;

    if ($minAmount < 0) {
        historyError(
            'Minimum amount cannot be negative.'
        );
    }

    if ($minAmount > 100000000) {
        historyError(
            'Minimum amount is too large.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Maximum Amount
|--------------------------------------------------------------------------
*/

$maxAmount = null;

if (isset($_GET['max_amount'])) {

    $value = trim($_GET['max_amount']);

    if (
        $value === '' ||
        !preg_match('/^\d+(?:\.\d{1,2})?$/', $value)
    ) {
        historyError(
            'Maximum amount must be a valid positive amount with maximum 2 decimal places.'
        );
    }

    $maxAmount = (float) $value;

    if ($maxAmount < 0) {
        historyError(
            'Maximum amount cannot be negative.'
        );
    }

    if ($maxAmount > 100000000) {
        historyError(
            'Maximum amount is too large.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Amount Range Validation
|--------------------------------------------------------------------------
*/

if (
    $minAmount !== null &&
    $maxAmount !== null &&
    $minAmount > $maxAmount
) {
    historyError(
        'Minimum amount cannot be greater than maximum amount.'
    );
}


/*
|--------------------------------------------------------------------------
| Sorting
|--------------------------------------------------------------------------
*/

$allowedSortFields = [
    'id' => 'rb.id',
    'booking_reference' => 'rb.booking_reference',
    'check_in_date' => 'rb.check_in_date',
    'check_out_date' => 'rb.check_out_date',
    'room_subtotal' => 'rb.room_subtotal',
    'gst_amount' => 'rb.gst_amount',
    'total_amount' => 'rb.total_amount',
    'payment_status' => 'rb.payment_status',
    'booking_status' => 'rb.booking_status',
    'created_at' => 'rb.created_at'
];

$sortBy = $_GET['sort_by'] ?? 'created_at';

if (!isset($allowedSortFields[$sortBy])) {
    historyError(
        'Invalid sort_by value.'
    );
}

$sortOrder = strtoupper(
    $_GET['sort_order'] ?? 'DESC'
);

if (
    !in_array(
        $sortOrder,
        ['ASC', 'DESC'],
        true
    )
) {
    historyError(
        'sort_order must be ASC or DESC.'
    );
}

$orderBy = $allowedSortFields[$sortBy];


/*
|--------------------------------------------------------------------------
| Build WHERE Conditions
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];
$types = '';


/*
|--------------------------------------------------------------------------
| Customer Restriction
|--------------------------------------------------------------------------
|
| Customer can only see own history.
|
*/

if ($isCustomer) {

    $where[] = 'rb.customer_id = ?';

    $params[] = $userId;
    $types .= 'i';
}


/*
|--------------------------------------------------------------------------
| Admin / Staff Customer Filter
|--------------------------------------------------------------------------
*/

if (
    $isAdminOrStaff &&
    $customerId !== null
) {

    $where[] = 'rb.customer_id = ?';

    $params[] = $customerId;
    $types .= 'i';
}


/*
|--------------------------------------------------------------------------
| Booking ID Filter
|--------------------------------------------------------------------------
*/

if ($bookingId !== null) {

    $where[] = 'rb.id = ?';

    $params[] = $bookingId;
    $types .= 'i';
}


/*
|--------------------------------------------------------------------------
| Booking Reference Filter
|--------------------------------------------------------------------------
*/

if ($bookingReference !== null) {

    $where[] = 'rb.booking_reference = ?';

    $params[] = $bookingReference;
    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
|
| Search:
| - booking reference
| - customer name
| - customer email
| - customer phone
|
*/

if ($search !== null) {

    $where[] = "
        (
            rb.booking_reference LIKE ?
            OR c.name LIKE ?
            OR c.email LIKE ?
            OR c.phone LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= 'ssss';
}


/*
|--------------------------------------------------------------------------
| Check-in Date Filter
|--------------------------------------------------------------------------
*/

if ($checkInDate !== null) {

    $where[] = 'rb.check_in_date >= ?';

    $params[] = $checkInDate;
    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Check-out Date Filter
|--------------------------------------------------------------------------
*/

if ($checkOutDate !== null) {

    $where[] = 'rb.check_out_date <= ?';

    $params[] = $checkOutDate;
    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Booking Status Filter
|--------------------------------------------------------------------------
*/

if ($bookingStatus !== null) {

    $where[] = 'rb.booking_status = ?';

    $params[] = $bookingStatus;
    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Payment Status Filter
|--------------------------------------------------------------------------
*/

if ($paymentStatus !== null) {

    $where[] = 'rb.payment_status = ?';

    $params[] = $paymentStatus;
    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Minimum Amount Filter
|--------------------------------------------------------------------------
*/

if ($minAmount !== null) {

    $where[] = 'rb.total_amount >= ?';

    $params[] = $minAmount;
    $types .= 'd';
}


/*
|--------------------------------------------------------------------------
| Maximum Amount Filter
|--------------------------------------------------------------------------
*/

if ($maxAmount !== null) {

    $where[] = 'rb.total_amount <= ?';

    $params[] = $maxAmount;
    $types .= 'd';
}


$whereSql = '';

if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(
        ' AND ',
        $where
    );
}


/*
|--------------------------------------------------------------------------
| Count Total Records
|--------------------------------------------------------------------------
*/

$countSql = "
    SELECT COUNT(*)
    AS total
    FROM room_bookings rb
    INNER JOIN customers c
        ON c.id = rb.customer_id
    {$whereSql}
";

$countStmt = $conn->prepare($countSql);

if (!$countStmt) {
    historyError(
        'Failed to prepare booking history count query.',
        500
    );
}

if (!empty($params)) {

    $countStmt->bind_param(
        $types,
        ...$params
    );
}

if (!$countStmt->execute()) {

    $countStmt->close();

    historyError(
        'Failed to count booking history.',
        500
    );
}

$countResult = $countStmt->get_result();
$countRow = $countResult->fetch_assoc();

$countStmt->close();

$totalRecords = (int) (
    $countRow['total'] ?? 0
);

$totalPages = $totalRecords > 0
    ? (int) ceil($totalRecords / $limit)
    : 0;


/*
|--------------------------------------------------------------------------
| Fetch Booking History
|--------------------------------------------------------------------------
*/

$sql = "
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

    {$whereSql}

    ORDER BY {$orderBy} {$sortOrder}

    LIMIT ? OFFSET ?
";


$dataParams = $params;
$dataTypes = $types . 'ii';

$dataParams[] = $limit;
$dataParams[] = $offset;


$stmt = $conn->prepare($sql);

if (!$stmt) {
    historyError(
        'Failed to prepare booking history query.',
        500
    );
}

$stmt->bind_param(
    $dataTypes,
    ...$dataParams
);

if (!$stmt->execute()) {

    $stmt->close();

    historyError(
        'Failed to fetch booking history.',
        500
    );
}

$result = $stmt->get_result();

$bookings = [];


/*
|--------------------------------------------------------------------------
| Fetch Booking Records
|--------------------------------------------------------------------------
*/

while ($row = $result->fetch_assoc()) {

    $bookingIdValue = (int) $row['id'];


    /*
    |--------------------------------------------------------------------------
    | Calculate Number of Nights
    |--------------------------------------------------------------------------
    */

    $checkIn = new DateTime(
        $row['check_in_date']
    );

    $checkOut = new DateTime(
        $row['check_out_date']
    );

    $nights = $checkIn->diff(
        $checkOut
    )->days;


    /*
    |--------------------------------------------------------------------------
    | Fetch Rooms
    |--------------------------------------------------------------------------
    */

    $roomSql = "
        SELECT
            br.id AS booking_room_id,
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

        WHERE br.booking_id = ?

        ORDER BY br.id ASC
    ";

    $roomStmt = $conn->prepare($roomSql);

    if (!$roomStmt) {
        $stmt->close();

        historyError(
            'Failed to prepare room history query.',
            500
        );
    }

    $roomStmt->bind_param(
        'i',
        $bookingIdValue
    );

    if (!$roomStmt->execute()) {

        $roomStmt->close();
        $stmt->close();

        historyError(
            'Failed to fetch booking room history.',
            500
        );
    }

    $roomResult = $roomStmt->get_result();

    $rooms = [];

    while ($room = $roomResult->fetch_assoc()) {

        $rooms[] = [
            'booking_room_id' => (int) $room['booking_room_id'],
            'room_id' => (int) $room['room_id'],
            'room_number' => $room['room_number'],
            'room_name' => $room['room_name'],
            'room_type_id' => (int) $room['room_type_id'],
            'room_type_name' => $room['room_type_name'],
            'room_price_per_night' => (float) $room['room_price_per_night'],
            'number_of_nights' => (int) $room['number_of_nights'],
            'room_total' => (float) $room['room_total']
        ];
    }

    $roomStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Fetch Payment Summary
    |--------------------------------------------------------------------------
    */

    $paymentSql = "
        SELECT
            COUNT(*) AS total_payments,
            COALESCE(
                SUM(
                    CASE
                        WHEN payment_status = 'paid'
                        THEN amount
                        ELSE 0
                    END
                ),
                0
            ) AS paid_amount
        FROM payments
        WHERE booking_id = ?
    ";

    $paymentStmt = $conn->prepare(
        $paymentSql
    );

    if (!$paymentStmt) {
        $stmt->close();

        historyError(
            'Failed to prepare payment history query.',
            500
        );
    }

    $paymentStmt->bind_param(
        'i',
        $bookingIdValue
    );

    if (!$paymentStmt->execute()) {

        $paymentStmt->close();
        $stmt->close();

        historyError(
            'Failed to fetch payment history.',
            500
        );
    }

    $paymentResult = $paymentStmt->get_result();

    $paymentRow =
        $paymentResult->fetch_assoc();

    $paymentStmt->close();


    $paidAmount = (float) (
        $paymentRow['paid_amount'] ?? 0
    );

    $totalAmount = (float) (
        $row['total_amount']
    );

    $outstandingAmount = max(
        0,
        $totalAmount - $paidAmount
    );


    /*
    |--------------------------------------------------------------------------
    | Add Booking
    |--------------------------------------------------------------------------
    */

    $bookings[] = [
        'id' => $bookingIdValue,

        'booking_reference' =>
            $row['booking_reference'],

        'customer' => [
            'id' => (int) $row['customer_id'],
            'name' => $row['customer_name'],
            'email' => $row['customer_email'],
            'phone' => $row['customer_phone']
        ],

        'stay' => [
            'check_in_date' =>
                $row['check_in_date'],

            'check_out_date' =>
                $row['check_out_date'],

            'number_of_nights' =>
                (int) $nights,

            'total_guests' =>
                (int) $row['total_guests']
        ],

        'rooms' => $rooms,

        'room_count' => count($rooms),

        'pricing' => [
            'room_subtotal' =>
                (float) $row['room_subtotal'],

            'gst_rate' =>
                (float) $row['gst_rate'],

            'gst_amount' =>
                (float) $row['gst_amount'],

            'total_amount' =>
                $totalAmount
        ],

        'payment' => [
            'payment_status' =>
                $row['payment_status'],

            'total_payments' =>
                (int) (
                    $paymentRow['total_payments'] ?? 0
                ),

            'paid_amount' =>
                $paidAmount,

            'outstanding_amount' =>
                $outstandingAmount
        ],

        'booking_status' =>
            $row['booking_status'],

        'special_request' =>
            $row['special_request'],

        'cancellation_reason' =>
            $row['cancellation_reason'],

        'created_at' =>
            $row['created_at'],

        'updated_at' =>
            $row['updated_at']
    ];
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

http_response_code(200);

echo json_encode([
    'status' => true,
    'message' => 'Booking history fetched successfully.',

    'data' => [
        'bookings' => $bookings,

        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total_records' => $totalRecords,
            'total_pages' => $totalPages,
            'has_next_page' =>
                $page < $totalPages,
            'has_previous_page' =>
                $page > 1
        ],

        'filters' => [
            'search' => $search,
            'booking_id' => $bookingId,
            'booking_reference' => $bookingReference,
            'customer_id' => $isCustomer
                ? $userId
                : $customerId,
            'check_in_date' => $checkInDate,
            'check_out_date' => $checkOutDate,
            'booking_status' => $bookingStatus,
            'payment_status' => $paymentStatus,
            'min_amount' => $minAmount,
            'max_amount' => $maxAmount,
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder
        ]
    ]
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);