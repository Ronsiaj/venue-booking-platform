<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SM GROUP
| Room Booking Report API
|--------------------------------------------------------------------------
|
| File:
| reports/room_reports.php
|
| Method:
| GET
|
| Access:
| Admin / Staff
|
|--------------------------------------------------------------------------
*/

header('Content-Type: application/json; charset=utf-8');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');


/*
|--------------------------------------------------------------------------
| OPTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {

    http_response_code(200);

    exit;
}


/*
|--------------------------------------------------------------------------
| Method Validation
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {

    http_response_code(405);

    echo json_encode([
        'status' => false,
        'message' => 'Only GET method is allowed.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}


/*
|--------------------------------------------------------------------------
| Includes
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../config/middleware.php';


/*
|--------------------------------------------------------------------------
| Response Helper
|--------------------------------------------------------------------------
*/

function sendResponse(
    bool $status,
    string $message,
    array $data = [],
    int $httpCode = 200
): void {

    http_response_code($httpCode);

    echo json_encode(
        [
            'status' => $status,
            'message' => $message,
            'data' => $data
        ],
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Database Validation
|--------------------------------------------------------------------------
*/

if (
    !isset($conn)
    || !($conn instanceof mysqli)
) {

    sendResponse(
        false,
        'Database connection is not available.',
        [],
        500
    );
}


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

$currentUser = requireAdminOrStaff();


$currentUserRole = strtolower(
    trim(
        (string)($currentUser['role'] ?? '')
    )
);


$currentUserId =
    $currentUser['sub']
    ?? $currentUser['id']
    ?? null;


if (
    !in_array(
        $currentUserRole,
        [
            'super_admin',
            'admin',
            'staff'
        ],
        true
    )
) {

    sendResponse(
        false,
        'You are not authorized to access room reports.',
        [],
        403
    );
}


if (
    !is_numeric($currentUserId)
    || (int)$currentUserId <= 0
) {

    sendResponse(
        false,
        'Invalid authenticated user.',
        [],
        401
    );
}


$currentUserId = (int)$currentUserId;


/*
|--------------------------------------------------------------------------
| Helper - Valid Date
|--------------------------------------------------------------------------
*/

function isValidDate(string $date): bool
{
    $parsed = DateTime::createFromFormat(
        'Y-m-d',
        $date
    );

    return (
        $parsed !== false
        && $parsed->format('Y-m-d') === $date
    );
}


/*
|--------------------------------------------------------------------------
| Helper - Execute Prepared Query
|--------------------------------------------------------------------------
*/

function executeQuery(
    mysqli $conn,
    string $sql,
    string $types = '',
    array $params = []
): mysqli_stmt {

    $stmt = $conn->prepare($sql);

    if (!$stmt) {

        sendResponse(
            false,
            'Failed to prepare database query.',
            [
                'error' => $conn->error
            ],
            500
        );
    }


    if ($types !== '') {

        $stmt->bind_param(
            $types,
            ...$params
        );
    }


    if (!$stmt->execute()) {

        $error =
            $stmt->error;

        $stmt->close();

        sendResponse(
            false,
            'Failed to execute database query.',
            [
                'error' => $error
            ],
            500
        );
    }


    return $stmt;
}


/*
|--------------------------------------------------------------------------
| GET Parameters
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Booking ID
|--------------------------------------------------------------------------
*/

$bookingId = isset($_GET['booking_id'])
    ? filter_var(
        $_GET['booking_id'],
        FILTER_VALIDATE_INT
    )
    : null;


/*
|--------------------------------------------------------------------------
| Booking Reference
|--------------------------------------------------------------------------
*/

$bookingReference = isset($_GET['booking_reference'])
    ? trim(
        (string)$_GET['booking_reference']
    )
    : '';


/*
|--------------------------------------------------------------------------
| Room ID
|--------------------------------------------------------------------------
*/

$roomId = isset($_GET['room_id'])
    ? filter_var(
        $_GET['room_id'],
        FILTER_VALIDATE_INT
    )
    : null;


/*
|--------------------------------------------------------------------------
| Room Type ID
|--------------------------------------------------------------------------
*/

$roomTypeId = isset($_GET['room_type_id'])
    ? filter_var(
        $_GET['room_type_id'],
        FILTER_VALIDATE_INT
    )
    : null;


/*
|--------------------------------------------------------------------------
| Customer ID
|--------------------------------------------------------------------------
*/

$customerId = isset($_GET['customer_id'])
    ? filter_var(
        $_GET['customer_id'],
        FILTER_VALIDATE_INT
    )
    : null;


/*
|--------------------------------------------------------------------------
| Booking Status
|--------------------------------------------------------------------------
*/

$bookingStatus = isset($_GET['booking_status'])
    ? strtolower(
        trim(
            (string)$_GET['booking_status']
        )
    )
    : 'all';


/*
|--------------------------------------------------------------------------
| Payment Status
|--------------------------------------------------------------------------
*/

$paymentStatus = isset($_GET['payment_status'])
    ? strtolower(
        trim(
            (string)$_GET['payment_status']
        )
    )
    : 'all';


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

$search = isset($_GET['search'])
    ? trim(
        (string)$_GET['search']
    )
    : '';


/*
|--------------------------------------------------------------------------
| Date Filters
|--------------------------------------------------------------------------
*/

$fromDate = isset($_GET['from_date'])
    ? trim(
        (string)$_GET['from_date']
    )
    : null;


$toDate = isset($_GET['to_date'])
    ? trim(
        (string)$_GET['to_date']
    )
    : null;


$checkInFrom = isset($_GET['check_in_from'])
    ? trim(
        (string)$_GET['check_in_from']
    )
    : null;


$checkInTo = isset($_GET['check_in_to'])
    ? trim(
        (string)$_GET['check_in_to']
    )
    : null;


$checkOutFrom = isset($_GET['check_out_from'])
    ? trim(
        (string)$_GET['check_out_from']
    )
    : null;


$checkOutTo = isset($_GET['check_out_to'])
    ? trim(
        (string)$_GET['check_out_to']
    )
    : null;


/*
|--------------------------------------------------------------------------
| Amount Filters
|--------------------------------------------------------------------------
*/

$minAmount = isset($_GET['min_amount'])
    ? trim(
        (string)$_GET['min_amount']
    )
    : null;


$maxAmount = isset($_GET['max_amount'])
    ? trim(
        (string)$_GET['max_amount']
    )
    : null;


/*
|--------------------------------------------------------------------------
| Guest Filter
|--------------------------------------------------------------------------
*/

$minGuests = isset($_GET['min_guests'])
    ? trim(
        (string)$_GET['min_guests']
    )
    : null;


$maxGuests = isset($_GET['max_guests'])
    ? trim(
        (string)$_GET['max_guests']
    )
    : null;


/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

$page = isset($_GET['page'])
    ? filter_var(
        $_GET['page'],
        FILTER_VALIDATE_INT
    )
    : 1;


$limit = isset($_GET['limit'])
    ? filter_var(
        $_GET['limit'],
        FILTER_VALIDATE_INT
    )
    : 20;


/*
|--------------------------------------------------------------------------
| Sorting
|--------------------------------------------------------------------------
*/

$sortBy = isset($_GET['sort_by'])
    ? strtolower(
        trim(
            (string)$_GET['sort_by']
        )
    )
    : 'created_at';


$sortOrder = isset($_GET['sort_order'])
    ? strtolower(
        trim(
            (string)$_GET['sort_order']
        )
    )
    : 'desc';


/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

$errors = [];


/*
|--------------------------------------------------------------------------
| Booking ID Validation
|--------------------------------------------------------------------------
*/

if (
    $bookingId !== null
    && (
        $bookingId === false
        || $bookingId < 1
    )
) {

    $errors['booking_id'] =
        'booking_id must be a positive integer.';
}


/*
|--------------------------------------------------------------------------
| Booking Reference Validation
|--------------------------------------------------------------------------
*/

if (
    $bookingReference !== ''
    && (
        strlen($bookingReference) < 3
        || strlen($bookingReference) > 50
    )
) {

    $errors['booking_reference'] =
        'booking_reference must contain 3 to 50 characters.';
}


/*
|--------------------------------------------------------------------------
| Room ID Validation
|--------------------------------------------------------------------------
*/

if (
    $roomId !== null
    && (
        $roomId === false
        || $roomId < 1
    )
) {

    $errors['room_id'] =
        'room_id must be a positive integer.';
}


/*
|--------------------------------------------------------------------------
| Room Type ID Validation
|--------------------------------------------------------------------------
*/

if (
    $roomTypeId !== null
    && (
        $roomTypeId === false
        || $roomTypeId < 1
    )
) {

    $errors['room_type_id'] =
        'room_type_id must be a positive integer.';
}


/*
|--------------------------------------------------------------------------
| Customer ID Validation
|--------------------------------------------------------------------------
*/

if (
    $customerId !== null
    && (
        $customerId === false
        || $customerId < 1
    )
) {

    $errors['customer_id'] =
        'customer_id must be a positive integer.';
}


/*
|--------------------------------------------------------------------------
| Booking Status Validation
|--------------------------------------------------------------------------
*/

$allowedBookingStatuses = [

    'all',

    'pending',

    'confirmed',

    'checked_in',

    'checked_out',

    'cancelled',

    'no_show'
];


if (
    !in_array(
        $bookingStatus,
        $allowedBookingStatuses,
        true
    )
) {

    $errors['booking_status'] =
        'Invalid booking_status.';
}


/*
|--------------------------------------------------------------------------
| Payment Status Validation
|--------------------------------------------------------------------------
*/

$allowedPaymentStatuses = [

    'all',

    'pending',

    'partial',

    'paid',

    'failed',

    'refunded'
];


if (
    !in_array(
        $paymentStatus,
        $allowedPaymentStatuses,
        true
    )
) {

    $errors['payment_status'] =
        'Invalid payment_status.';
}


/*
|--------------------------------------------------------------------------
| Search Validation
|--------------------------------------------------------------------------
*/

if (
    strlen($search) > 150
) {

    $errors['search'] =
        'search cannot exceed 150 characters.';
}


/*
|--------------------------------------------------------------------------
| Date Validation
|--------------------------------------------------------------------------
*/

$dateFields = [

    'from_date' =>
        $fromDate,

    'to_date' =>
        $toDate,

    'check_in_from' =>
        $checkInFrom,

    'check_in_to' =>
        $checkInTo,

    'check_out_from' =>
        $checkOutFrom,

    'check_out_to' =>
        $checkOutTo
];


foreach (
    $dateFields
    as $field =>
    $value
) {

    if (
        $value !== null
        && $value !== ''
        && !isValidDate($value)
    ) {

        $errors[$field] =
            $field .
            ' must be in YYYY-MM-DD format.';
    }
}


/*
|--------------------------------------------------------------------------
| Date Range Validation
|--------------------------------------------------------------------------
*/

if (
    $fromDate
    && $toDate
    && $fromDate > $toDate
) {

    $errors['date_range'] =
        'from_date cannot be greater than to_date.';
}


if (
    $checkInFrom
    && $checkInTo
    && $checkInFrom > $checkInTo
) {

    $errors['check_in_range'] =
        'check_in_from cannot be greater than check_in_to.';
}


if (
    $checkOutFrom
    && $checkOutTo
    && $checkOutFrom > $checkOutTo
) {

    $errors['check_out_range'] =
        'check_out_from cannot be greater than check_out_to.';
}


/*
|--------------------------------------------------------------------------
| Amount Validation
|--------------------------------------------------------------------------
*/

if (
    $minAmount !== null
    && $minAmount !== ''
) {

    if (
        !is_numeric($minAmount)
        || (float)$minAmount < 0
    ) {

        $errors['min_amount'] =
            'min_amount must be a valid non-negative amount.';
    }
}


if (
    $maxAmount !== null
    && $maxAmount !== ''
) {

    if (
        !is_numeric($maxAmount)
        || (float)$maxAmount < 0
    ) {

        $errors['max_amount'] =
            'max_amount must be a valid non-negative amount.';
    }
}


if (
    $minAmount !== null
    && $maxAmount !== null
    && $minAmount !== ''
    && $maxAmount !== ''
    && (float)$minAmount > (float)$maxAmount
) {

    $errors['amount_range'] =
        'min_amount cannot be greater than max_amount.';
}


/*
|--------------------------------------------------------------------------
| Guest Validation
|--------------------------------------------------------------------------
*/

if (
    $minGuests !== null
    && $minGuests !== ''
) {

    if (
        !ctype_digit($minGuests)
        || (int)$minGuests < 1
    ) {

        $errors['min_guests'] =
            'min_guests must be a positive integer.';
    }
}


if (
    $maxGuests !== null
    && $maxGuests !== ''
) {

    if (
        !ctype_digit($maxGuests)
        || (int)$maxGuests < 1
    ) {

        $errors['max_guests'] =
            'max_guests must be a positive integer.';
    }
}


if (
    $minGuests !== null
    && $maxGuests !== null
    && $minGuests !== ''
    && $maxGuests !== ''
    && (int)$minGuests > (int)$maxGuests
) {

    $errors['guest_range'] =
        'min_guests cannot be greater than max_guests.';
}


/*
|--------------------------------------------------------------------------
| Pagination Validation
|--------------------------------------------------------------------------
*/

if (
    $page === false
    || $page < 1
) {

    $errors['page'] =
        'page must be a positive integer.';
}


if (
    $limit === false
    || $limit < 1
    || $limit > 100
) {

    $errors['limit'] =
        'limit must be between 1 and 100.';
}


/*
|--------------------------------------------------------------------------
| Sort Validation
|--------------------------------------------------------------------------
*/

$allowedSortColumns = [

    'created_at',

    'updated_at',

    'check_in_date',

    'check_out_date',

    'total_amount',

    'room_subtotal',

    'gst_amount',

    'booking_reference',

    'total_guests'
];


if (
    !in_array(
        $sortBy,
        $allowedSortColumns,
        true
    )
) {

    $errors['sort_by'] =
        'Invalid sort_by.';
}


if (
    !in_array(
        $sortOrder,
        [
            'asc',
            'desc'
        ],
        true
    )
) {

    $errors['sort_order'] =
        'sort_order must be asc or desc.';
}


/*
|--------------------------------------------------------------------------
| Return Validation Errors
|--------------------------------------------------------------------------
*/

if (!empty($errors)) {

    sendResponse(
        false,
        'Validation failed.',
        [
            'errors' => $errors
        ],
        422
    );
}


/*
|--------------------------------------------------------------------------
| Normalize Values
|--------------------------------------------------------------------------
*/

$page =
    (int)$page;


$limit =
    (int)$limit;


$offset =
    ($page - 1) * $limit;


$minAmount =
    (
        $minAmount !== null
        && $minAmount !== ''
    )
        ? (float)$minAmount
        : null;


$maxAmount =
    (
        $maxAmount !== null
        && $maxAmount !== ''
    )
        ? (float)$maxAmount
        : null;


$minGuests =
    (
        $minGuests !== null
        && $minGuests !== ''
    )
        ? (int)$minGuests
        : null;


$maxGuests =
    (
        $maxGuests !== null
        && $maxGuests !== ''
    )
        ? (int)$maxGuests
        : null;


/*
|--------------------------------------------------------------------------
| Base WHERE Builder
|--------------------------------------------------------------------------
*/

$where = [];

$params = [];

$types = '';


/*
|--------------------------------------------------------------------------
| Booking ID
|--------------------------------------------------------------------------
*/

if ($bookingId !== null) {

    $where[] =
        'rb.id = ?';

    $params[] =
        (int)$bookingId;

    $types .= 'i';
}


/*
|--------------------------------------------------------------------------
| Booking Reference
|--------------------------------------------------------------------------
*/

if ($bookingReference !== '') {

    $where[] =
        'rb.booking_reference = ?';

    $params[] =
        $bookingReference;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Booking Status
|--------------------------------------------------------------------------
*/

if ($bookingStatus !== 'all') {

    $where[] =
        'rb.booking_status = ?';

    $params[] =
        $bookingStatus;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Payment Status
|--------------------------------------------------------------------------
*/

if ($paymentStatus !== 'all') {

    $where[] =
        'rb.payment_status = ?';

    $params[] =
        $paymentStatus;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Room ID
|--------------------------------------------------------------------------
*/

if ($roomId !== null) {

    $where[] = "
        EXISTS (
            SELECT 1

            FROM booking_rooms br_filter_room

            WHERE br_filter_room.booking_id = rb.id

              AND br_filter_room.room_id = ?
        )
    ";

    $params[] =
        (int)$roomId;

    $types .= 'i';
}


/*
|--------------------------------------------------------------------------
| Room Type ID
|--------------------------------------------------------------------------
*/

if ($roomTypeId !== null) {

    $where[] = "
        EXISTS (
            SELECT 1

            FROM booking_rooms br_filter_type

            INNER JOIN rooms r_filter_type
                ON r_filter_type.id =
                   br_filter_type.room_id

            WHERE br_filter_type.booking_id = rb.id

              AND r_filter_type.room_type_id = ?
        )
    ";

    $params[] =
        (int)$roomTypeId;

    $types .= 'i';
}


/*
|--------------------------------------------------------------------------
| Customer ID
|--------------------------------------------------------------------------
*/

if ($customerId !== null) {

    $where[] =
        'rb.customer_id = ?';

    $params[] =
        (int)$customerId;

    $types .= 'i';
}


/*
|--------------------------------------------------------------------------
| Created Date From
|--------------------------------------------------------------------------
*/

if (
    $fromDate !== null
    && $fromDate !== ''
) {

    $where[] =
        'rb.created_at >= ?';

    $params[] =
        $fromDate . ' 00:00:00';

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Created Date To
|--------------------------------------------------------------------------
*/

if (
    $toDate !== null
    && $toDate !== ''
) {

    $where[] =
        'rb.created_at <= ?';

    $params[] =
        $toDate . ' 23:59:59';

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Check-in From
|--------------------------------------------------------------------------
*/

if (
    $checkInFrom !== null
    && $checkInFrom !== ''
) {

    $where[] =
        'rb.check_in_date >= ?';

    $params[] =
        $checkInFrom;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Check-in To
|--------------------------------------------------------------------------
*/

if (
    $checkInTo !== null
    && $checkInTo !== ''
) {

    $where[] =
        'rb.check_in_date <= ?';

    $params[] =
        $checkInTo;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Check-out From
|--------------------------------------------------------------------------
*/

if (
    $checkOutFrom !== null
    && $checkOutFrom !== ''
) {

    $where[] =
        'rb.check_out_date >= ?';

    $params[] =
        $checkOutFrom;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Check-out To
|--------------------------------------------------------------------------
*/

if (
    $checkOutTo !== null
    && $checkOutTo !== ''
) {

    $where[] =
        'rb.check_out_date <= ?';

    $params[] =
        $checkOutTo;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Minimum Amount
|--------------------------------------------------------------------------
*/

if ($minAmount !== null) {

    $where[] =
        'rb.total_amount >= ?';

    $params[] =
        $minAmount;

    $types .= 'd';
}


/*
|--------------------------------------------------------------------------
| Maximum Amount
|--------------------------------------------------------------------------
*/

if ($maxAmount !== null) {

    $where[] =
        'rb.total_amount <= ?';

    $params[] =
        $maxAmount;

    $types .= 'd';
}


/*
|--------------------------------------------------------------------------
| Minimum Guests
|--------------------------------------------------------------------------
*/

if ($minGuests !== null) {

    $where[] =
        'rb.total_guests >= ?';

    $params[] =
        $minGuests;

    $types .= 'i';
}


/*
|--------------------------------------------------------------------------
| Maximum Guests
|--------------------------------------------------------------------------
*/

if ($maxGuests !== null) {

    $where[] =
        'rb.total_guests <= ?';

    $params[] =
        $maxGuests;

    $types .= 'i';
}


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $searchValue =
        '%' . $search . '%';


    $where[] = "
        (
            rb.booking_reference LIKE ?

            OR c.name LIKE ?

            OR c.email LIKE ?

            OR c.phone LIKE ?

            OR rb.special_request LIKE ?

            OR EXISTS (
                SELECT 1

                FROM booking_rooms br_search

                INNER JOIN rooms r_search
                    ON r_search.id =
                       br_search.room_id

                WHERE br_search.booking_id = rb.id

                  AND (
                      r_search.room_number LIKE ?

                      OR r_search.name LIKE ?
                  )
            )
        )
    ";


    $params[] =
        $searchValue;

    $params[] =
        $searchValue;

    $params[] =
        $searchValue;

    $params[] =
        $searchValue;

    $params[] =
        $searchValue;

    $params[] =
        $searchValue;

    $params[] =
        $searchValue;


    $types .= 'sssssss';
}


/*
|--------------------------------------------------------------------------
| WHERE SQL
|--------------------------------------------------------------------------
*/

$whereSql = '';

if (!empty($where)) {

    $whereSql =
        'WHERE ' .
        implode(
            ' AND ',
            $where
        );
}


/*
|--------------------------------------------------------------------------
| Sort Map
|--------------------------------------------------------------------------
*/

$sortMap = [

    'created_at' =>
        'rb.created_at',

    'updated_at' =>
        'rb.updated_at',

    'check_in_date' =>
        'rb.check_in_date',

    'check_out_date' =>
        'rb.check_out_date',

    'total_amount' =>
        'rb.total_amount',

    'room_subtotal' =>
        'rb.room_subtotal',

    'gst_amount' =>
        'rb.gst_amount',

    'booking_reference' =>
        'rb.booking_reference',

    'total_guests' =>
        'rb.total_guests'
];


$orderColumn =
    $sortMap[$sortBy];


$orderDirection =
    strtoupper($sortOrder);


/*
|--------------------------------------------------------------------------
| 1. TOTAL RECORD COUNT
|--------------------------------------------------------------------------
*/

$totalRecords = 0;


$sql = "
    SELECT
        COUNT(*) AS total

    FROM room_bookings rb

    INNER JOIN customers c
        ON c.id = rb.customer_id

    {$whereSql}
";


$stmt =
    executeQuery(
        $conn,
        $sql,
        $types,
        $params
    );


$result =
    $stmt->get_result();


if ($row = $result->fetch_assoc()) {

    $totalRecords =
        (int)$row['total'];
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| 2. MAIN REPORT SUMMARY
|--------------------------------------------------------------------------
*/

$summary = [

    'total_bookings' => 0,

    'total_nights' => 0,

    'total_guests' => 0,

    'total_rooms_booked' => 0,

    'room_subtotal' => 0.00,

    'gst_amount' => 0.00,

    'total_booking_value' => 0.00,

    'captured_payment' => 0.00,

    'refunded_payment' => 0.00,

    'net_collected' => 0.00,

    'outstanding_amount' => 0.00,

    'average_booking_value' => 0.00,

    'average_night_value' => 0.00
];


$sql = "
    SELECT

        COUNT(*) AS total_bookings,

        COALESCE(
            SUM(
                (
                    SELECT
                        COALESCE(
                            SUM(
                                br_sum.number_of_nights
                            ),
                            0
                        )

                    FROM booking_rooms br_sum

                    WHERE br_sum.booking_id =
                          rb.id
                )
            ),
            0
        ) AS total_nights,

        COALESCE(
            SUM(rb.total_guests),
            0
        ) AS total_guests,

        COALESCE(
            SUM(
                (
                    SELECT
                        COUNT(*)

                    FROM booking_rooms br_count

                    WHERE br_count.booking_id =
                          rb.id
                )
            ),
            0
        ) AS total_rooms_booked,

        COALESCE(
            SUM(rb.room_subtotal),
            0
        ) AS room_subtotal,

        COALESCE(
            SUM(rb.gst_amount),
            0
        ) AS gst_amount,

        COALESCE(
            SUM(rb.total_amount),
            0
        ) AS total_booking_value,

        COALESCE(
            SUM(
                (
                    SELECT
                        COALESCE(
                            SUM(
                                CASE
                                    WHEN p.payment_status = 'captured'
                                    THEN p.amount
                                    ELSE 0
                                END
                            ),
                            0
                        )

                    FROM payments p

                    WHERE p.booking_id =
                          rb.id
                )
            ),
            0
        ) AS captured_payment,

        COALESCE(
            SUM(
                (
                    SELECT
                        COALESCE(
                            SUM(
                                CASE
                                    WHEN p_ref.payment_status = 'refunded'
                                    THEN p_ref.amount
                                    ELSE 0
                                END
                            ),
                            0
                        )

                    FROM payments p_ref

                    WHERE p_ref.booking_id =
                          rb.id
                )
            ),
            0
        ) AS refunded_payment

    FROM room_bookings rb

    INNER JOIN customers c
        ON c.id = rb.customer_id

    {$whereSql}
";


$stmt =
    executeQuery(
        $conn,
        $sql,
        $types,
        $params
    );


$result =
    $stmt->get_result();


if ($row = $result->fetch_assoc()) {

    $summary['total_bookings'] =
        (int)$row['total_bookings'];

    $summary['total_nights'] =
        (int)$row['total_nights'];

    $summary['total_guests'] =
        (int)$row['total_guests'];

    $summary['total_rooms_booked'] =
        (int)$row['total_rooms_booked'];

    $summary['room_subtotal'] =
        round(
            (float)$row['room_subtotal'],
            2
        );

    $summary['gst_amount'] =
        round(
            (float)$row['gst_amount'],
            2
        );

    $summary['total_booking_value'] =
        round(
            (float)$row['total_booking_value'],
            2
        );

    $summary['captured_payment'] =
        round(
            (float)$row['captured_payment'],
            2
        );

    $summary['refunded_payment'] =
        round(
            (float)$row['refunded_payment'],
            2
        );
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| Net Collected
|--------------------------------------------------------------------------
*/

$summary['net_collected'] =
    round(
        $summary['captured_payment']
        -
        $summary['refunded_payment'],
        2
    );


/*
|--------------------------------------------------------------------------
| Outstanding
|--------------------------------------------------------------------------
*/

$summary['outstanding_amount'] =
    round(
        max(
            0,
            $summary['total_booking_value']
            -
            $summary['net_collected']
        ),
        2
    );


/*
|--------------------------------------------------------------------------
| Average Booking Value
|--------------------------------------------------------------------------
*/

if (
    $summary['total_bookings'] > 0
) {

    $summary['average_booking_value'] =
        round(
            $summary['total_booking_value']
            /
            $summary['total_bookings'],
            2
        );
}


/*
|--------------------------------------------------------------------------
| Average Night Value
|--------------------------------------------------------------------------
*/

if (
    $summary['total_nights'] > 0
) {

    $summary['average_night_value'] =
        round(
            $summary['total_booking_value']
            /
            $summary['total_nights'],
            2
        );
}


/*
|--------------------------------------------------------------------------
| 3. BOOKING STATUS SUMMARY
|--------------------------------------------------------------------------
*/

$bookingStatusSummary = [

    'pending' => [
        'count' => 0,
        'amount' => 0.00
    ],

    'confirmed' => [
        'count' => 0,
        'amount' => 0.00
    ],

    'checked_in' => [
        'count' => 0,
        'amount' => 0.00
    ],

    'checked_out' => [
        'count' => 0,
        'amount' => 0.00
    ],

    'cancelled' => [
        'count' => 0,
        'amount' => 0.00
    ],

    'no_show' => [
        'count' => 0,
        'amount' => 0.00
    ]
];


$sql = "
    SELECT

        rb.booking_status,

        COUNT(*) AS booking_count,

        COALESCE(
            SUM(rb.total_amount),
            0
        ) AS booking_amount

    FROM room_bookings rb

    INNER JOIN customers c
        ON c.id = rb.customer_id

    {$whereSql}

    GROUP BY rb.booking_status

    ORDER BY rb.booking_status ASC
";


$stmt =
    executeQuery(
        $conn,
        $sql,
        $types,
        $params
    );


$result =
    $stmt->get_result();


while ($row = $result->fetch_assoc()) {

    $statusName =
        $row['booking_status'];


    if (
        isset(
            $bookingStatusSummary[$statusName]
        )
    ) {

        $bookingStatusSummary[$statusName] = [

            'count' =>
                (int)$row['booking_count'],

            'amount' =>
                round(
                    (float)$row['booking_amount'],
                    2
                )
        ];
    }
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| 4. PAYMENT STATUS SUMMARY
|--------------------------------------------------------------------------
*/

$paymentStatusSummary = [

    'pending' => [
        'count' => 0,
        'amount' => 0.00
    ],

    'partial' => [
        'count' => 0,
        'amount' => 0.00
    ],

    'paid' => [
        'count' => 0,
        'amount' => 0.00
    ],

    'failed' => [
        'count' => 0,
        'amount' => 0.00
    ],

    'refunded' => [
        'count' => 0,
        'amount' => 0.00
    ]
];


$sql = "
    SELECT

        rb.payment_status,

        COUNT(*) AS booking_count,

        COALESCE(
            SUM(rb.total_amount),
            0
        ) AS booking_amount

    FROM room_bookings rb

    INNER JOIN customers c
        ON c.id = rb.customer_id

    {$whereSql}

    GROUP BY rb.payment_status

    ORDER BY rb.payment_status ASC
";


$stmt =
    executeQuery(
        $conn,
        $sql,
        $types,
        $params
    );


$result =
    $stmt->get_result();


while ($row = $result->fetch_assoc()) {

    $statusName =
        $row['payment_status'];


    if (
        isset(
            $paymentStatusSummary[$statusName]
        )
    ) {

        $paymentStatusSummary[$statusName] = [

            'count' =>
                (int)$row['booking_count'],

            'amount' =>
                round(
                    (float)$row['booking_amount'],
                    2
                )
        ];
    }
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| 5. ROOM-WISE PERFORMANCE
|--------------------------------------------------------------------------
*/

$roomPerformance = [];


$sql = "
    SELECT

        r.id AS room_id,

        r.room_number,

        r.name AS room_name,

        r.status AS room_status,

        rt.id AS room_type_id,

        rt.name AS room_type_name,

        COUNT(
            DISTINCT rb.id
        ) AS booking_count,

        COALESCE(
            SUM(br.number_of_nights),
            0
        ) AS total_nights,

        COALESCE(
            SUM(br.room_total),
            0
        ) AS room_revenue,

        COALESCE(
            SUM(
                DISTINCT rb.gst_amount
            ),
            0
        ) AS gst_amount,

        COALESCE(
            SUM(
                DISTINCT rb.total_amount
            ),
            0
        ) AS booking_value,

        COUNT(
            DISTINCT
            CASE
                WHEN rb.booking_status = 'checked_in'
                THEN rb.id
            END
        ) AS active_bookings

    FROM rooms r

    INNER JOIN room_types rt
        ON rt.id = r.room_type_id

    LEFT JOIN booking_rooms br
        ON br.room_id = r.id

    LEFT JOIN room_bookings rb
        ON rb.id = br.booking_id

    LEFT JOIN customers c
        ON c.id = rb.customer_id

    /*
    |--------------------------------------------------------------------------
    | IMPORTANT
    |--------------------------------------------------------------------------
    | We cannot directly reuse the main WHERE here because
    | the main WHERE contains rb/customer aliases and
    | EXISTS filters.
    |
    |--------------------------------------------------------------------------
    */

    WHERE 1 = 1
";


$roomPerformanceParams = [];

$roomPerformanceTypes = '';


/*
|--------------------------------------------------------------------------
| Room Performance Filters
|--------------------------------------------------------------------------
*/

if ($bookingStatus !== 'all') {

    $sql .= "
        AND (
            rb.id IS NULL
            OR rb.booking_status = ?
        )
    ";

    $roomPerformanceParams[] =
        $bookingStatus;

    $roomPerformanceTypes .= 's';
}


if ($paymentStatus !== 'all') {

    $sql .= "
        AND (
            rb.id IS NULL
            OR rb.payment_status = ?
        )
    ";

    $roomPerformanceParams[] =
        $paymentStatus;

    $roomPerformanceTypes .= 's';
}


if ($roomId !== null) {

    $sql .= "
        AND r.id = ?
    ";

    $roomPerformanceParams[] =
        (int)$roomId;

    $roomPerformanceTypes .= 'i';
}


if ($roomTypeId !== null) {

    $sql .= "
        AND r.room_type_id = ?
    ";

    $roomPerformanceParams[] =
        (int)$roomTypeId;

    $roomPerformanceTypes .= 'i';
}


if ($customerId !== null) {

    $sql .= "
        AND (
            rb.id IS NULL
            OR rb.customer_id = ?
        )
    ";

    $roomPerformanceParams[] =
        (int)$customerId;

    $roomPerformanceTypes .= 'i';
}


if (
    $fromDate !== null
    && $fromDate !== ''
) {

    $sql .= "
        AND (
            rb.id IS NULL
            OR rb.created_at >= ?
        )
    ";

    $roomPerformanceParams[] =
        $fromDate . ' 00:00:00';

    $roomPerformanceTypes .= 's';
}


if (
    $toDate !== null
    && $toDate !== ''
) {

    $sql .= "
        AND (
            rb.id IS NULL
            OR rb.created_at <= ?
        )
    ";

    $roomPerformanceParams[] =
        $toDate . ' 23:59:59';

    $roomPerformanceTypes .= 's';
}


if (
    $checkInFrom !== null
    && $checkInFrom !== ''
) {

    $sql .= "
        AND (
            rb.id IS NULL
            OR rb.check_in_date >= ?
        )
    ";

    $roomPerformanceParams[] =
        $checkInFrom;

    $roomPerformanceTypes .= 's';
}


if (
    $checkInTo !== null
    && $checkInTo !== ''
) {

    $sql .= "
        AND (
            rb.id IS NULL
            OR rb.check_in_date <= ?
        )
    ";

    $roomPerformanceParams[] =
        $checkInTo;

    $roomPerformanceTypes .= 's';
}


if (
    $checkOutFrom !== null
    && $checkOutFrom !== ''
) {

    $sql .= "
        AND (
            rb.id IS NULL
            OR rb.check_out_date >= ?
        )
    ";

    $roomPerformanceParams[] =
        $checkOutFrom;

    $roomPerformanceTypes .= 's';
}


if (
    $checkOutTo !== null
    && $checkOutTo !== ''
) {

    $sql .= "
        AND (
            rb.id IS NULL
            OR rb.check_out_date <= ?
        )
    ";

    $roomPerformanceParams[] =
        $checkOutTo;

    $roomPerformanceTypes .= 's';
}


if ($minAmount !== null) {

    $sql .= "
        AND (
            rb.id IS NULL
            OR rb.total_amount >= ?
        )
    ";

    $roomPerformanceParams[] =
        $minAmount;

    $roomPerformanceTypes .= 'd';
}


if ($maxAmount !== null) {

    $sql .= "
        AND (
            rb.id IS NULL
            OR rb.total_amount <= ?
        )
    ";

    $roomPerformanceParams[] =
        $maxAmount;

    $roomPerformanceTypes .= 'd';
}


if ($minGuests !== null) {

    $sql .= "
        AND (
            rb.id IS NULL
            OR rb.total_guests >= ?
        )
    ";

    $roomPerformanceParams[] =
        $minGuests;

    $roomPerformanceTypes .= 'i';
}


if ($maxGuests !== null) {

    $sql .= "
        AND (
            rb.id IS NULL
            OR rb.total_guests <= ?
        )
    ";

    $roomPerformanceParams[] =
        $maxGuests;

    $roomPerformanceTypes .= 'i';
}


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $searchValue =
        '%' . $search . '%';


    $sql .= "
        AND (
            rb.id IS NULL

            OR rb.booking_reference LIKE ?

            OR c.name LIKE ?

            OR c.email LIKE ?

            OR c.phone LIKE ?

            OR rb.special_request LIKE ?
        )
    ";


    $roomPerformanceParams[] =
        $searchValue;

    $roomPerformanceParams[] =
        $searchValue;

    $roomPerformanceParams[] =
        $searchValue;

    $roomPerformanceParams[] =
        $searchValue;

    $roomPerformanceParams[] =
        $searchValue;


    $roomPerformanceTypes .=
        'sssss';
}


$sql .= "

    GROUP BY

        r.id,

        r.room_number,

        r.name,

        r.status,

        rt.id,

        rt.name

    ORDER BY
        booking_count DESC,
        room_revenue DESC
";


$stmt =
    executeQuery(
        $conn,
        $sql,
        $roomPerformanceTypes,
        $roomPerformanceParams
    );


$result =
    $stmt->get_result();


while ($row = $result->fetch_assoc()) {

    $roomPerformance[] = [

        'room_id' =>
            (int)$row['room_id'],

        'room_number' =>
            $row['room_number'],

        'room_name' =>
            $row['room_name'],

        'room_status' =>
            $row['room_status'],

        'room_type_id' =>
            (int)$row['room_type_id'],

        'room_type_name' =>
            $row['room_type_name'],

        'booking_count' =>
            (int)$row['booking_count'],

        'total_nights' =>
            (int)$row['total_nights'],

        'room_revenue' =>
            round(
                (float)$row['room_revenue'],
                2
            ),

        'gst_amount' =>
            round(
                (float)$row['gst_amount'],
                2
            ),

        'booking_value' =>
            round(
                (float)$row['booking_value'],
                2
            ),

        'active_bookings' =>
            (int)$row['active_bookings']
    ];
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| 6. ROOM TYPE PERFORMANCE
|--------------------------------------------------------------------------
*/

$roomTypePerformance = [];


$sql = "
    SELECT

        rt.id AS room_type_id,

        rt.name AS room_type_name,

        rt.status AS room_type_status,

        COUNT(
            DISTINCT rb.id
        ) AS booking_count,

        COALESCE(
            SUM(br.number_of_nights),
            0
        ) AS total_nights,

        COALESCE(
            SUM(br.room_total),
            0
        ) AS room_revenue,

        COALESCE(
            SUM(
                DISTINCT rb.total_amount
            ),
            0
        ) AS booking_value

    FROM room_types rt

    LEFT JOIN rooms r
        ON r.room_type_id = rt.id

    LEFT JOIN booking_rooms br
        ON br.room_id = r.id

    LEFT JOIN room_bookings rb
        ON rb.id = br.booking_id

    WHERE 1 = 1
";


$roomTypeParams = [];

$roomTypeTypes = '';


if ($bookingStatus !== 'all') {

    $sql .= "
        AND (
            rb.id IS NULL
            OR rb.booking_status = ?
        )
    ";

    $roomTypeParams[] =
        $bookingStatus;

    $roomTypeTypes .= 's';
}


if ($paymentStatus !== 'all') {

    $sql .= "
        AND (
            rb.id IS NULL
            OR rb.payment_status = ?
        )
    ";

    $roomTypeParams[] =
        $paymentStatus;

    $roomTypeTypes .= 's';
}


if ($roomTypeId !== null) {

    $sql .= "
        AND rt.id = ?
    ";

    $roomTypeParams[] =
        (int)$roomTypeId;

    $roomTypeTypes .= 'i';
}


if (
    $fromDate !== null
    && $fromDate !== ''
) {

    $sql .= "
        AND (
            rb.id IS NULL
            OR rb.created_at >= ?
        )
    ";

    $roomTypeParams[] =
        $fromDate . ' 00:00:00';

    $roomTypeTypes .= 's';
}


if (
    $toDate !== null
    && $toDate !== ''
) {

    $sql .= "
        AND (
            rb.id IS NULL
            OR rb.created_at <= ?
        )
    ";

    $roomTypeParams[] =
        $toDate . ' 23:59:59';

    $roomTypeTypes .= 's';
}


if (
    $checkInFrom !== null
    && $checkInFrom !== ''
) {

    $sql .= "
        AND (
            rb.id IS NULL
            OR rb.check_in_date >= ?
        )
    ";

    $roomTypeParams[] =
        $checkInFrom;

    $roomTypeTypes .= 's';
}


if (
    $checkInTo !== null
    && $checkInTo !== ''
) {

    $sql .= "
        AND (
            rb.id IS NULL
            OR rb.check_in_date <= ?
        )
    ";

    $roomTypeParams[] =
        $checkInTo;

    $roomTypeTypes .= 's';
}


if (
    $checkOutFrom !== null
    && $checkOutFrom !== ''
) {

    $sql .= "
        AND (
            rb.id IS NULL
            OR rb.check_out_date >= ?
        )
    ";

    $roomTypeParams[] =
        $checkOutFrom;

    $roomTypeTypes .= 's';
}


if (
    $checkOutTo !== null
    && $checkOutTo !== ''
) {

    $sql .= "
        AND (
            rb.id IS NULL
            OR rb.check_out_date <= ?
        )
    ";

    $roomTypeParams[] =
        $checkOutTo;

    $roomTypeTypes .= 's';
}


if ($minAmount !== null) {

    $sql .= "
        AND (
            rb.id IS NULL
            OR rb.total_amount >= ?
        )
    ";

    $roomTypeParams[] =
        $minAmount;

    $roomTypeTypes .= 'd';
}


if ($maxAmount !== null) {

    $sql .= "
        AND (
            rb.id IS NULL
            OR rb.total_amount <= ?
        )
    ";

    $roomTypeParams[] =
        $maxAmount;

    $roomTypeTypes .= 'd';
}


if ($customerId !== null) {

    $sql .= "
        AND (
            rb.id IS NULL
            OR rb.customer_id = ?
        )
    ";

    $roomTypeParams[] =
        (int)$customerId;

    $roomTypeTypes .= 'i';
}


$sql .= "

    GROUP BY

        rt.id,

        rt.name,

        rt.status

    ORDER BY
        booking_count DESC,
        room_revenue DESC
";


$stmt =
    executeQuery(
        $conn,
        $sql,
        $roomTypeTypes,
        $roomTypeParams
    );


$result =
    $stmt->get_result();


while ($row = $result->fetch_assoc()) {

    $roomTypePerformance[] = [

        'room_type_id' =>
            (int)$row['room_type_id'],

        'room_type_name' =>
            $row['room_type_name'],

        'room_type_status' =>
            $row['room_type_status'],

        'booking_count' =>
            (int)$row['booking_count'],

        'total_nights' =>
            (int)$row['total_nights'],

        'room_revenue' =>
            round(
                (float)$row['room_revenue'],
                2
            ),

        'booking_value' =>
            round(
                (float)$row['booking_value'],
                2
            )
    ];
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| 7. MONTHLY REVENUE
|--------------------------------------------------------------------------
*/

$monthlyRevenue = [];


$sql = "
    SELECT

        DATE_FORMAT(
            rb.created_at,
            '%Y-%m'
        ) AS report_month,

        COUNT(*) AS booking_count,

        COALESCE(
            SUM(rb.total_amount),
            0
        ) AS booking_value,

        COALESCE(
            SUM(rb.room_subtotal),
            0
        ) AS room_subtotal,

        COALESCE(
            SUM(rb.gst_amount),
            0
        ) AS gst_amount,

        COALESCE(
            SUM(
                (
                    SELECT
                        COALESCE(
                            SUM(
                                CASE
                                    WHEN p.payment_status = 'captured'
                                    THEN p.amount
                                    ELSE 0
                                END
                            ),
                            0
                        )

                    FROM payments p

                    WHERE p.booking_id =
                          rb.id
                )
            ),
            0
        ) AS captured_amount

    FROM room_bookings rb

    INNER JOIN customers c
        ON c.id = rb.customer_id

    {$whereSql}

    GROUP BY
        DATE_FORMAT(
            rb.created_at,
            '%Y-%m'
        )

    ORDER BY
        report_month ASC
";


$stmt =
    executeQuery(
        $conn,
        $sql,
        $types,
        $params
    );


$result =
    $stmt->get_result();


while ($row = $result->fetch_assoc()) {

    $monthlyRevenue[] = [

        'month' =>
            $row['report_month'],

        'booking_count' =>
            (int)$row['booking_count'],

        'booking_value' =>
            round(
                (float)$row['booking_value'],
                2
            ),

        'room_subtotal' =>
            round(
                (float)$row['room_subtotal'],
                2
            ),

        'gst_amount' =>
            round(
                (float)$row['gst_amount'],
                2
            ),

        'captured_amount' =>
            round(
                (float)$row['captured_amount'],
                2
            )
    ];
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| 8. DAILY REVENUE
|--------------------------------------------------------------------------
*/

$dailyRevenue = [];


$sql = "
    SELECT

        DATE(
            rb.created_at
        ) AS report_date,

        COUNT(*) AS booking_count,

        COALESCE(
            SUM(rb.total_amount),
            0
        ) AS booking_value,

        COALESCE(
            SUM(rb.gst_amount),
            0
        ) AS gst_amount

    FROM room_bookings rb

    INNER JOIN customers c
        ON c.id = rb.customer_id

    {$whereSql}

    GROUP BY
        DATE(
            rb.created_at
        )

    ORDER BY
        report_date ASC
";


$stmt =
    executeQuery(
        $conn,
        $sql,
        $types,
        $params
    );


$result =
    $stmt->get_result();


while ($row = $result->fetch_assoc()) {

    $dailyRevenue[] = [

        'date' =>
            $row['report_date'],

        'booking_count' =>
            (int)$row['booking_count'],

        'booking_value' =>
            round(
                (float)$row['booking_value'],
                2
            ),

        'gst_amount' =>
            round(
                (float)$row['gst_amount'],
                2
            )
    ];
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| 9. TODAY SUMMARY
|--------------------------------------------------------------------------
*/

$todaySummary = [

    'check_ins' => 0,

    'check_outs' => 0,

    'bookings_created_today' => 0,

    'revenue_created_today' => 0.00
];


$sql = "
    SELECT

        COALESCE(
            SUM(
                CASE
                    WHEN rb.check_in_date = CURDATE()
                    AND rb.booking_status NOT IN (
                        'cancelled',
                        'no_show'
                    )
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS check_ins,

        COALESCE(
            SUM(
                CASE
                    WHEN rb.check_out_date = CURDATE()
                    AND rb.booking_status NOT IN (
                        'cancelled',
                        'no_show'
                    )
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS check_outs,

        COALESCE(
            SUM(
                CASE
                    WHEN DATE(rb.created_at) = CURDATE()
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS bookings_created_today,

        COALESCE(
            SUM(
                CASE
                    WHEN DATE(rb.created_at) = CURDATE()
                    THEN rb.total_amount
                    ELSE 0
                END
            ),
            0
        ) AS revenue_created_today

    FROM room_bookings rb

    INNER JOIN customers c
        ON c.id = rb.customer_id
";


$stmt =
    executeQuery(
        $conn,
        $sql
    );


$result =
    $stmt->get_result();


if ($row = $result->fetch_assoc()) {

    $todaySummary = [

        'check_ins' =>
            (int)$row['check_ins'],

        'check_outs' =>
            (int)$row['check_outs'],

        'bookings_created_today' =>
            (int)$row['bookings_created_today'],

        'revenue_created_today' =>
            round(
                (float)$row['revenue_created_today'],
                2
            )
    ];
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| 10. ROOM INVENTORY / OCCUPANCY
|--------------------------------------------------------------------------
*/

$roomInventory = [

    'total_rooms' => 0,

    'available_rooms' => 0,

    'maintenance_rooms' => 0,

    'inactive_rooms' => 0,

    'currently_occupied_rooms' => 0,

    'currently_available_for_booking' => 0,

    'occupancy_percentage' => 0.00
];


$sql = "
    SELECT

        COUNT(*) AS total_rooms,

        COALESCE(
            SUM(
                CASE
                    WHEN r.status = 'available'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS available_rooms,

        COALESCE(
            SUM(
                CASE
                    WHEN r.status = 'maintenance'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS maintenance_rooms,

        COALESCE(
            SUM(
                CASE
                    WHEN r.status = 'inactive'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS inactive_rooms

    FROM rooms r
";


$stmt =
    executeQuery(
        $conn,
        $sql
    );


$result =
    $stmt->get_result();


if ($row = $result->fetch_assoc()) {

    $roomInventory['total_rooms'] =
        (int)$row['total_rooms'];

    $roomInventory['available_rooms'] =
        (int)$row['available_rooms'];

    $roomInventory['maintenance_rooms'] =
        (int)$row['maintenance_rooms'];

    $roomInventory['inactive_rooms'] =
        (int)$row['inactive_rooms'];
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| Currently Occupied Rooms
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        COUNT(
            DISTINCT br.room_id
        ) AS occupied_rooms

    FROM booking_rooms br

    INNER JOIN room_bookings rb
        ON rb.id = br.booking_id

    WHERE rb.booking_status = 'checked_in'

      AND rb.check_in_date <= CURDATE()

      AND rb.check_out_date > CURDATE()
";


$stmt =
    executeQuery(
        $conn,
        $sql
    );


$result =
    $stmt->get_result();


if ($row = $result->fetch_assoc()) {

    $roomInventory['currently_occupied_rooms'] =
        (int)$row['occupied_rooms'];
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| Currently Available Rooms
|--------------------------------------------------------------------------
*/

$roomInventory['currently_available_for_booking'] =
    max(
        0,
        $roomInventory['available_rooms']
        -
        $roomInventory['currently_occupied_rooms']
    );


/*
|--------------------------------------------------------------------------
| Occupancy Percentage
|--------------------------------------------------------------------------
*/

if (
    $roomInventory['total_rooms'] > 0
) {

    $roomInventory['occupancy_percentage'] =
        round(
            (
                $roomInventory['currently_occupied_rooms']
                /
                $roomInventory['total_rooms']
            ) * 100,
            2
        );
}


/*
|--------------------------------------------------------------------------
| 11. UPCOMING CHECK-INS
|--------------------------------------------------------------------------
*/

$upcomingCheckIns = [];


$sql = "
    SELECT

        rb.id,

        rb.booking_reference,

        rb.check_in_date,

        rb.check_out_date,

        rb.total_guests,

        rb.total_amount,

        rb.payment_status,

        rb.booking_status,

        c.id AS customer_id,

        c.name AS customer_name,

        c.phone AS customer_phone,

        c.email AS customer_email,

        (
            SELECT
                COUNT(*)

            FROM booking_rooms br_count

            WHERE br_count.booking_id =
                  rb.id
        ) AS room_count

    FROM room_bookings rb

    INNER JOIN customers c
        ON c.id = rb.customer_id

    WHERE rb.check_in_date >= CURDATE()

      AND rb.booking_status NOT IN (
          'cancelled',
          'no_show'
      )

    ORDER BY

        rb.check_in_date ASC,

        rb.id ASC

    LIMIT 10
";


$stmt =
    executeQuery(
        $conn,
        $sql
    );


$result =
    $stmt->get_result();


while ($row = $result->fetch_assoc()) {

    $upcomingCheckIns[] = [

        'id' =>
            (int)$row['id'],

        'booking_reference' =>
            $row['booking_reference'],

        'check_in_date' =>
            $row['check_in_date'],

        'check_out_date' =>
            $row['check_out_date'],

        'total_guests' =>
            (int)$row['total_guests'],

        'room_count' =>
            (int)$row['room_count'],

        'total_amount' =>
            round(
                (float)$row['total_amount'],
                2
            ),

        'payment_status' =>
            $row['payment_status'],

        'booking_status' =>
            $row['booking_status'],

        'customer' => [

            'id' =>
                (int)$row['customer_id'],

            'name' =>
                $row['customer_name'],

            'phone' =>
                $row['customer_phone'],

            'email' =>
                $row['customer_email']
        ]
    ];
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| 12. MAIN BOOKING RECORDS
|--------------------------------------------------------------------------
*/

$bookings = [];


/*
|--------------------------------------------------------------------------
| Booking Query
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

        c.phone AS customer_phone,

        c.status AS customer_status,


        (
            SELECT
                COUNT(*)

            FROM booking_rooms br_count

            WHERE br_count.booking_id =
                  rb.id
        ) AS room_count,


        (
            SELECT
                COALESCE(
                    SUM(
                        br_nights.number_of_nights
                    ),
                    0
                )

            FROM booking_rooms br_nights

            WHERE br_nights.booking_id =
                  rb.id
        ) AS total_nights,


        (
            SELECT
                COALESCE(
                    SUM(
                        br_total.room_total
                    ),
                    0
                )

            FROM booking_rooms br_total

            WHERE br_total.booking_id =
                  rb.id
        ) AS room_total,


        (
            SELECT
                COALESCE(
                    SUM(
                        CASE
                            WHEN p_capture.payment_status =
                                 'captured'
                            THEN p_capture.amount
                            ELSE 0
                        END
                    ),
                    0
                )

            FROM payments p_capture

            WHERE p_capture.booking_id =
                  rb.id
        ) AS captured_amount,


        (
            SELECT
                COALESCE(
                    SUM(
                        CASE
                            WHEN p_refund.payment_status =
                                 'refunded'
                            THEN p_refund.amount
                            ELSE 0
                        END
                    ),
                    0
                )

            FROM payments p_refund

            WHERE p_refund.booking_id =
                  rb.id
        ) AS refunded_amount,


        (
            SELECT
                COUNT(*)

            FROM booking_food_packages bfp

            WHERE bfp.booking_id =
                  rb.id
        ) AS food_package_count,


        (
            SELECT
                COUNT(*)

            FROM booking_food_items bfi

            WHERE bfi.booking_id =
                  rb.id
        ) AS food_item_count

    FROM room_bookings rb

    INNER JOIN customers c
        ON c.id = rb.customer_id

    {$whereSql}

    ORDER BY
        {$orderColumn}
        {$orderDirection}

    LIMIT ?

    OFFSET ?
";


$bookingParams =
    $params;


$bookingTypes =
    $types . 'ii';


$bookingParams[] =
    $limit;


$bookingParams[] =
    $offset;


$stmt =
    executeQuery(
        $conn,
        $sql,
        $bookingTypes,
        $bookingParams
    );


$result =
    $stmt->get_result();


while ($row = $result->fetch_assoc()) {

    $currentBookingId =
        (int)$row['id'];


    /*
    |--------------------------------------------------------------------------
    | Booking Rooms
    |--------------------------------------------------------------------------
    */

    $bookingRooms = [];


    $roomSql = "
        SELECT

            br.id,

            br.room_id,

            br.room_price_per_night,

            br.number_of_nights,

            br.room_total,

            r.room_number,

            r.name AS room_name,

            r.description AS room_description,

            r.bed_count,

            r.guest_capacity,

            r.price_per_night,

            r.gst_rate,

            r.price_includes_gst,

            r.status AS room_status,

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


    $roomStmt =
        executeQuery(
            $conn,
            $roomSql,
            'i',
            [$currentBookingId]
        );


    $roomResult =
        $roomStmt->get_result();


    while (
        $roomRow =
        $roomResult->fetch_assoc()
    ) {

        $bookingRooms[] = [

            'booking_room_id' =>
                (int)$roomRow['id'],

            'room_id' =>
                (int)$roomRow['room_id'],

            'room_number' =>
                $roomRow['room_number'],

            'room_name' =>
                $roomRow['room_name'],

            'room_description' =>
                $roomRow['room_description'],

            'room_type_id' =>
                (int)$roomRow['room_type_id'],

            'room_type_name' =>
                $roomRow['room_type_name'],

            'bed_count' =>
                (int)$roomRow['bed_count'],

            'guest_capacity' =>
                (int)$roomRow['guest_capacity'],

            'current_room_price_per_night' =>
                round(
                    (float)$roomRow['price_per_night'],
                    2
                ),

            'booked_price_per_night' =>
                round(
                    (float)$roomRow['room_price_per_night'],
                    2
                ),

            'number_of_nights' =>
                (int)$roomRow['number_of_nights'],

            'room_total' =>
                round(
                    (float)$roomRow['room_total'],
                    2
                ),

            'gst_rate' =>
                round(
                    (float)$roomRow['gst_rate'],
                    2
                ),

            'price_includes_gst' =>
                (bool)$roomRow['price_includes_gst'],

            'room_status' =>
                $roomRow['room_status']
        ];
    }


    $roomStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Food Packages
    |--------------------------------------------------------------------------
    */

    $foodPackages = [];


    $foodPackageSql = "
        SELECT

            bfp.id,

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

        ORDER BY bfp.id ASC
    ";


    $foodPackageStmt =
        executeQuery(
            $conn,
            $foodPackageSql,
            'i',
            [$currentBookingId]
        );


    $foodPackageResult =
        $foodPackageStmt->get_result();


    while (
        $foodPackageRow =
        $foodPackageResult->fetch_assoc()
    ) {

        $foodPackages[] = [

            'id' =>
                (int)$foodPackageRow['id'],

            'package_id' =>
                (int)$foodPackageRow['package_id'],

            'package_name' =>
                $foodPackageRow['package_name'],

            'package_description' =>
                $foodPackageRow['package_description'],

            'meal_type' =>
                $foodPackageRow['meal_type'],

            'quantity' =>
                (int)$foodPackageRow['quantity'],

            'notes' =>
                $foodPackageRow['notes'],

            'package_status' =>
                $foodPackageRow['package_status']
        ];
    }


    $foodPackageStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Custom Food Items
    |--------------------------------------------------------------------------
    */

    $foodItems = [];


    $foodItemSql = "
        SELECT

            bfi.id,

            bfi.food_item_id,

            bfi.quantity,

            bfi.notes,

            fi.name AS food_item_name,

            fi.description AS food_item_description,

            fi.food_type,

            fi.status AS food_item_status,

            fc.name AS food_category_name

        FROM booking_food_items bfi

        INNER JOIN food_items fi
            ON fi.id = bfi.food_item_id

        LEFT JOIN food_categories fc
            ON fc.id = fi.food_category_id

        WHERE bfi.booking_id = ?

        ORDER BY bfi.id ASC
    ";


    $foodItemStmt =
        executeQuery(
            $conn,
            $foodItemSql,
            'i',
            [$currentBookingId]
        );


    $foodItemResult =
        $foodItemStmt->get_result();


    while (
        $foodItemRow =
        $foodItemResult->fetch_assoc()
    ) {

        $foodItems[] = [

            'id' =>
                (int)$foodItemRow['id'],

            'food_item_id' =>
                (int)$foodItemRow['food_item_id'],

            'food_item_name' =>
                $foodItemRow['food_item_name'],

            'food_item_description' =>
                $foodItemRow['food_item_description'],

            'food_category_name' =>
                $foodItemRow['food_category_name'],

            'food_type' =>
                $foodItemRow['food_type'],

            'quantity' =>
                (int)$foodItemRow['quantity'],

            'notes' =>
                $foodItemRow['notes'],

            'food_item_status' =>
                $foodItemRow['food_item_status']
        ];
    }


    $foodItemStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    */

    $payments = [];


    $paymentSql = "
        SELECT

            p.id,

            p.payment_gateway,

            p.razorpay_order_id,

            p.razorpay_payment_id,

            p.amount,

            p.currency,

            p.payment_status,

            p.payment_method,

            p.paid_at,

            p.created_at,

            p.updated_at

        FROM payments p

        WHERE p.booking_id = ?

        ORDER BY p.id DESC
    ";


    $paymentStmt =
        executeQuery(
            $conn,
            $paymentSql,
            'i',
            [$currentBookingId]
        );


    $paymentResult =
        $paymentStmt->get_result();


    while (
        $paymentRow =
        $paymentResult->fetch_assoc()
    ) {

        $payments[] = [

            'id' =>
                (int)$paymentRow['id'],

            'payment_gateway' =>
                $paymentRow['payment_gateway'],

            'razorpay_order_id' =>
                $paymentRow['razorpay_order_id'],

            'razorpay_payment_id' =>
                $paymentRow['razorpay_payment_id'],

            'amount' =>
                round(
                    (float)$paymentRow['amount'],
                    2
                ),

            'currency' =>
                $paymentRow['currency'],

            'payment_status' =>
                $paymentRow['payment_status'],

            'payment_method' =>
                $paymentRow['payment_method'],

            'paid_at' =>
                $paymentRow['paid_at'],

            'created_at' =>
                $paymentRow['created_at'],

            'updated_at' =>
                $paymentRow['updated_at']
        ];
    }


    $paymentStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Payment Calculation
    |--------------------------------------------------------------------------
    */

    $capturedAmount =
        round(
            (float)$row['captured_amount'],
            2
        );


    $refundedAmount =
        round(
            (float)$row['refunded_amount'],
            2
        );


    $netCollected =
        round(
            $capturedAmount
            -
            $refundedAmount,
            2
        );


    $outstanding =
        round(
            max(
                0,
                (float)$row['total_amount']
                -
                $netCollected
            ),
            2
        );


    /*
    |--------------------------------------------------------------------------
    | Booking Object
    |--------------------------------------------------------------------------
    */

    $bookings[] = [

        'id' =>
            $currentBookingId,

        'booking_reference' =>
            $row['booking_reference'],


        /*
        |--------------------------------------------------------------------------
        | Customer
        |--------------------------------------------------------------------------
        */

        'customer' => [

            'id' =>
                (int)$row['customer_id'],

            'name' =>
                $row['customer_name'],

            'email' =>
                $row['customer_email'],

            'phone' =>
                $row['customer_phone'],

            'status' =>
                $row['customer_status']
        ],


        /*
        |--------------------------------------------------------------------------
        | Stay
        |--------------------------------------------------------------------------
        */

        'stay' => [

            'check_in_date' =>
                $row['check_in_date'],

            'check_out_date' =>
                $row['check_out_date'],

            'total_guests' =>
                (int)$row['total_guests'],

            'room_count' =>
                (int)$row['room_count'],

            'total_nights' =>
                (int)$row['total_nights']
        ],


        /*
        |--------------------------------------------------------------------------
        | Amount
        |--------------------------------------------------------------------------
        */

        'amount' => [

            'room_subtotal' =>
                round(
                    (float)$row['room_subtotal'],
                    2
                ),

            'gst_rate' =>
                round(
                    (float)$row['gst_rate'],
                    2
                ),

            'gst_amount' =>
                round(
                    (float)$row['gst_amount'],
                    2
                ),

            'total_amount' =>
                round(
                    (float)$row['total_amount'],
                    2
                ),

            'room_total' =>
                round(
                    (float)$row['room_total'],
                    2
                ),

            'captured_amount' =>
                $capturedAmount,

            'refunded_amount' =>
                $refundedAmount,

            'net_collected' =>
                $netCollected,

            'outstanding_amount' =>
                $outstanding
        ],


        /*
        |--------------------------------------------------------------------------
        | Status
        |--------------------------------------------------------------------------
        */

        'payment_status' =>
            $row['payment_status'],

        'booking_status' =>
            $row['booking_status'],


        /*
        |--------------------------------------------------------------------------
        | Request
        |--------------------------------------------------------------------------
        */

        'special_request' =>
            $row['special_request'],

        'cancellation_reason' =>
            $row['cancellation_reason'],


        /*
        |--------------------------------------------------------------------------
        | Rooms
        |--------------------------------------------------------------------------
        */

        'rooms' =>
            $bookingRooms,


        /*
        |--------------------------------------------------------------------------
        | Food
        |--------------------------------------------------------------------------
        */

        'food' => [

            'package_count' =>
                (int)$row['food_package_count'],

            'item_count' =>
                (int)$row['food_item_count'],

            'packages' =>
                $foodPackages,

            'custom_items' =>
                $foodItems
        ],


        /*
        |--------------------------------------------------------------------------
        | Payments
        |--------------------------------------------------------------------------
        */

        'payments' =>
            $payments,


        /*
        |--------------------------------------------------------------------------
        | Timestamps
        |--------------------------------------------------------------------------
        */

        'created_at' =>
            $row['created_at'],

        'updated_at' =>
            $row['updated_at']
    ];
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| 13. CHART DATA
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Booking Status Chart
|--------------------------------------------------------------------------
*/

$bookingStatusChart = [

    'labels' => [

        'Pending',

        'Confirmed',

        'Checked In',

        'Checked Out',

        'Cancelled',

        'No Show'
    ],

    'datasets' => [

        [
            'label' =>
                'Bookings',

            'data' => [

                $bookingStatusSummary['pending']['count'],

                $bookingStatusSummary['confirmed']['count'],

                $bookingStatusSummary['checked_in']['count'],

                $bookingStatusSummary['checked_out']['count'],

                $bookingStatusSummary['cancelled']['count'],

                $bookingStatusSummary['no_show']['count']
            ]
        ]
    ]
];


/*
|--------------------------------------------------------------------------
| Payment Status Chart
|--------------------------------------------------------------------------
*/

$paymentStatusChart = [

    'labels' => [

        'Pending',

        'Partial',

        'Paid',

        'Failed',

        'Refunded'
    ],

    'datasets' => [

        [
            'label' =>
                'Bookings',

            'data' => [

                $paymentStatusSummary['pending']['count'],

                $paymentStatusSummary['partial']['count'],

                $paymentStatusSummary['paid']['count'],

                $paymentStatusSummary['failed']['count'],

                $paymentStatusSummary['refunded']['count']
            ]
        ]
    ]
];


/*
|--------------------------------------------------------------------------
| Room Revenue Chart
|--------------------------------------------------------------------------
*/

$roomRevenueLabels = [];

$roomRevenueData = [];

$roomBookingCounts = [];


foreach (
    $roomPerformance
    as $room
) {

    $roomRevenueLabels[] =
        $room['room_number']
        . ' - '
        . $room['room_name'];


    $roomRevenueData[] =
        $room['room_revenue'];


    $roomBookingCounts[] =
        $room['booking_count'];
}


$roomRevenueChart = [

    'labels' =>
        $roomRevenueLabels,

    'datasets' => [

        [
            'label' =>
                'Room Revenue',

            'data' =>
                $roomRevenueData
        ]
    ]
];


$roomBookingChart = [

    'labels' =>
        $roomRevenueLabels,

    'datasets' => [

        [
            'label' =>
                'Bookings',

            'data' =>
                $roomBookingCounts
        ]
    ]
];


/*
|--------------------------------------------------------------------------
| Room Type Chart
|--------------------------------------------------------------------------
*/

$roomTypeLabels = [];

$roomTypeRevenueData = [];


foreach (
    $roomTypePerformance
    as $roomType
) {

    $roomTypeLabels[] =
        $roomType['room_type_name'];


    $roomTypeRevenueData[] =
        $roomType['room_revenue'];
}


$roomTypeRevenueChart = [

    'labels' =>
        $roomTypeLabels,

    'datasets' => [

        [
            'label' =>
                'Revenue',

            'data' =>
                $roomTypeRevenueData
        ]
    ]
];


/*
|--------------------------------------------------------------------------
| Monthly Chart
|--------------------------------------------------------------------------
*/

$monthlyLabels = [];

$monthlyBookingData = [];

$monthlyRevenueData = [];

$monthlyGstData = [];

$monthlyCapturedData = [];


foreach (
    $monthlyRevenue
    as $month
) {

    $monthlyLabels[] =
        $month['month'];


    $monthlyBookingData[] =
        $month['booking_count'];


    $monthlyRevenueData[] =
        $month['booking_value'];


    $monthlyGstData[] =
        $month['gst_amount'];


    $monthlyCapturedData[] =
        $month['captured_amount'];
}


$monthlyRevenueChart = [

    'labels' =>
        $monthlyLabels,

    'datasets' => [

        [
            'label' =>
                'Booking Value',

            'data' =>
                $monthlyRevenueData
        ],

        [
            'label' =>
                'Captured Payment',

            'data' =>
                $monthlyCapturedData
        ],

        [
            'label' =>
                'GST',

            'data' =>
                $monthlyGstData
        ]
    ]
];


$monthlyBookingChart = [

    'labels' =>
        $monthlyLabels,

    'datasets' => [

        [
            'label' =>
                'Bookings',

            'data' =>
                $monthlyBookingData
        ]
    ]
];


/*
|--------------------------------------------------------------------------
| Daily Chart
|--------------------------------------------------------------------------
*/

$dailyLabels = [];

$dailyBookingData = [];

$dailyRevenueData = [];


foreach (
    $dailyRevenue
    as $day
) {

    $dailyLabels[] =
        $day['date'];


    $dailyBookingData[] =
        $day['booking_count'];


    $dailyRevenueData[] =
        $day['booking_value'];
}


$dailyRevenueChart = [

    'labels' =>
        $dailyLabels,

    'datasets' => [

        [
            'label' =>
                'Daily Revenue',

            'data' =>
                $dailyRevenueData
        ]
    ]
];


$dailyBookingChart = [

    'labels' =>
        $dailyLabels,

    'datasets' => [

        [
            'label' =>
                'Daily Bookings',

            'data' =>
                $dailyBookingData
        ]
    ]
];


/*
|--------------------------------------------------------------------------
| Revenue Overview Chart
|--------------------------------------------------------------------------
*/

$revenueOverviewChart = [

    'labels' => [

        'Room Subtotal',

        'GST',

        'Total Booking Value',

        'Net Collected',

        'Outstanding'
    ],

    'datasets' => [

        [
            'label' =>
                'Amount',

            'data' => [

                $summary['room_subtotal'],

                $summary['gst_amount'],

                $summary['total_booking_value'],

                $summary['net_collected'],

                $summary['outstanding_amount']
            ]
        ]
    ]
];


/*
|--------------------------------------------------------------------------
| Occupancy Chart
|--------------------------------------------------------------------------
*/

$occupancyChart = [

    'labels' => [

        'Occupied',

        'Available',

        'Maintenance',

        'Inactive'
    ],

    'datasets' => [

        [
            'label' =>
                'Rooms',

            'data' => [

                $roomInventory[
                    'currently_occupied_rooms'
                ],

                $roomInventory[
                    'currently_available_for_booking'
                ],

                $roomInventory[
                    'maintenance_rooms'
                ],

                $roomInventory[
                    'inactive_rooms'
                ]
            ]
        ]
    ]
];


/*
|--------------------------------------------------------------------------
| 14. PAGINATION
|--------------------------------------------------------------------------
*/

$totalPages =
    $totalRecords > 0
        ? (int)ceil(
            $totalRecords / $limit
        )
        : 0;


/*
|--------------------------------------------------------------------------
| 15. FINAL RESPONSE
|--------------------------------------------------------------------------
*/

sendResponse(
    true,
    'Room booking report fetched successfully.',
    [

        /*
        |--------------------------------------------------------------------------
        | Meta
        |--------------------------------------------------------------------------
        */

        'meta' => [

            'report' =>
                'room_booking_report',

            'api' =>
                'reports/room_reports.php',

            'generated_at' =>
                date('Y-m-d H:i:s'),

            'currency' =>
                'INR',

            'currency_symbol' =>
                '₹'
        ],


        /*
        |--------------------------------------------------------------------------
        | User Scope
        |--------------------------------------------------------------------------
        */

        'user_scope' => [

            'user_id' =>
                $currentUserId,

            'role' =>
                $currentUserRole,

            'scope' =>
                'room_booking_reports'
        ],


        /*
        |--------------------------------------------------------------------------
        | Applied Filters
        |--------------------------------------------------------------------------
        */

        'filters' => [

            'booking_id' =>
                $bookingId,

            'booking_reference' =>
                $bookingReference,

            'room_id' =>
                $roomId,

            'room_type_id' =>
                $roomTypeId,

            'customer_id' =>
                $customerId,

            'booking_status' =>
                $bookingStatus,

            'payment_status' =>
                $paymentStatus,

            'search' =>
                $search,

            'from_date' =>
                $fromDate,

            'to_date' =>
                $toDate,

            'check_in_from' =>
                $checkInFrom,

            'check_in_to' =>
                $checkInTo,

            'check_out_from' =>
                $checkOutFrom,

            'check_out_to' =>
                $checkOutTo,

            'min_amount' =>
                $minAmount,

            'max_amount' =>
                $maxAmount,

            'min_guests' =>
                $minGuests,

            'max_guests' =>
                $maxGuests,

            'page' =>
                $page,

            'limit' =>
                $limit,

            'sort_by' =>
                $sortBy,

            'sort_order' =>
                $sortOrder
        ],


        /*
        |--------------------------------------------------------------------------
        | KPI
        |--------------------------------------------------------------------------
        */

        'kpis' => [

            'total_bookings' =>
                $summary['total_bookings'],

            'total_rooms_booked' =>
                $summary['total_rooms_booked'],

            'total_nights' =>
                $summary['total_nights'],

            'total_guests' =>
                $summary['total_guests'],

            'room_subtotal' =>
                $summary['room_subtotal'],

            'gst_amount' =>
                $summary['gst_amount'],

            'total_booking_value' =>
                $summary['total_booking_value'],

            'captured_payment' =>
                $summary['captured_payment'],

            'refunded_payment' =>
                $summary['refunded_payment'],

            'net_collected' =>
                $summary['net_collected'],

            'outstanding_amount' =>
                $summary['outstanding_amount'],

            'average_booking_value' =>
                $summary['average_booking_value'],

            'average_night_value' =>
                $summary['average_night_value']
        ],


        /*
        |--------------------------------------------------------------------------
        | Today
        |--------------------------------------------------------------------------
        */

        'today' =>
            $todaySummary,


        /*
        |--------------------------------------------------------------------------
        | Room Inventory
        |--------------------------------------------------------------------------
        */

        'room_inventory' =>
            $roomInventory,


        /*
        |--------------------------------------------------------------------------
        | Booking Status
        |--------------------------------------------------------------------------
        */

        'booking_status_summary' =>
            $bookingStatusSummary,


        /*
        |--------------------------------------------------------------------------
        | Payment Status
        |--------------------------------------------------------------------------
        */

        'payment_status_summary' =>
            $paymentStatusSummary,


        /*
        |--------------------------------------------------------------------------
        | Room Performance
        |--------------------------------------------------------------------------
        */

        'room_performance' =>
            $roomPerformance,


        /*
        |--------------------------------------------------------------------------
        | Room Type Performance
        |--------------------------------------------------------------------------
        */

        'room_type_performance' =>
            $roomTypePerformance,


        /*
        |--------------------------------------------------------------------------
        | Monthly Report
        |--------------------------------------------------------------------------
        */

        'monthly' =>
            $monthlyRevenue,


        /*
        |--------------------------------------------------------------------------
        | Daily Report
        |--------------------------------------------------------------------------
        */

        'daily' =>
            $dailyRevenue,


        /*
        |--------------------------------------------------------------------------
        | Upcoming Check-ins
        |--------------------------------------------------------------------------
        */

        'upcoming_check_ins' =>
            $upcomingCheckIns,


        /*
        |--------------------------------------------------------------------------
        | Booking Records
        |--------------------------------------------------------------------------
        */

        'bookings' =>
            $bookings,


        /*
        |--------------------------------------------------------------------------
        | Chart.js Data
        |--------------------------------------------------------------------------
        */

        'charts' => [

            'booking_status' =>
                $bookingStatusChart,

            'payment_status' =>
                $paymentStatusChart,

            'room_revenue' =>
                $roomRevenueChart,

            'room_bookings' =>
                $roomBookingChart,

            'room_type_revenue' =>
                $roomTypeRevenueChart,

            'monthly_revenue' =>
                $monthlyRevenueChart,

            'monthly_bookings' =>
                $monthlyBookingChart,

            'daily_revenue' =>
                $dailyRevenueChart,

            'daily_bookings' =>
                $dailyBookingChart,

            'revenue_overview' =>
                $revenueOverviewChart,

            'occupancy' =>
                $occupancyChart
        ],


        /*
        |--------------------------------------------------------------------------
        | Pagination
        |--------------------------------------------------------------------------
        */

        'pagination' => [

            'page' =>
                $page,

            'limit' =>
                $limit,

            'total_records' =>
                $totalRecords,

            'total_pages' =>
                $totalPages,

            'has_next_page' =>
                $page < $totalPages,

            'has_previous_page' =>
                $page > 1
        ]
    ],
    200
);