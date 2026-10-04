<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SM GROUP
| Dashboard Booking API
|--------------------------------------------------------------------------
|
| Endpoint:
| GET /dashboard/booking.php
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
| Authentication
|--------------------------------------------------------------------------
*/

$currentUser = requireAdminOrStaff();


$currentUserRole = strtolower(
    trim((string)($currentUser['role'] ?? ''))
);


$currentUserId =
    $currentUser['sub']
    ?? $currentUser['id']
    ?? null;


if (!in_array(
    $currentUserRole,
    ['admin', 'staff', 'super_admin'],
    true
)) {

    sendResponse(
        false,
        'You are not authorized to access booking dashboard.',
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
| Helper - Date Validation
|--------------------------------------------------------------------------
*/

function isValidDate(string $date): bool
{
    $parsed = DateTime::createFromFormat(
        'Y-m-d',
        $date
    );

    return
        $parsed !== false
        && $parsed->format('Y-m-d') === $date;
}


/*
|--------------------------------------------------------------------------
| Helper - Execute Prepared Statement
|--------------------------------------------------------------------------
*/

function executeStatement(
    mysqli $conn,
    string $sql,
    string $types = '',
    array $params = []
): mysqli_stmt {

    $stmt = $conn->prepare($sql);

    if (!$stmt) {

        sendResponse(
            false,
            'Database query preparation failed.',
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

        $error = $stmt->error;

        $stmt->close();

        sendResponse(
            false,
            'Database query execution failed.',
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
| Query Parameters
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Date Filters
|--------------------------------------------------------------------------
*/

$checkInFrom = isset($_GET['check_in_from'])
    ? trim((string)$_GET['check_in_from'])
    : null;

$checkInTo = isset($_GET['check_in_to'])
    ? trim((string)$_GET['check_in_to'])
    : null;

$checkOutFrom = isset($_GET['check_out_from'])
    ? trim((string)$_GET['check_out_from'])
    : null;

$checkOutTo = isset($_GET['check_out_to'])
    ? trim((string)$_GET['check_out_to'])
    : null;

$createdFrom = isset($_GET['created_from'])
    ? trim((string)$_GET['created_from'])
    : null;

$createdTo = isset($_GET['created_to'])
    ? trim((string)$_GET['created_to'])
    : null;


/*
|--------------------------------------------------------------------------
| Status Filters
|--------------------------------------------------------------------------
*/

$bookingStatus = isset($_GET['booking_status'])
    ? strtolower(trim((string)$_GET['booking_status']))
    : 'all';

$paymentStatus = isset($_GET['payment_status'])
    ? strtolower(trim((string)$_GET['payment_status']))
    : 'all';


/*
|--------------------------------------------------------------------------
| ID Filters
|--------------------------------------------------------------------------
*/

$roomId = isset($_GET['room_id'])
    ? filter_var(
        $_GET['room_id'],
        FILTER_VALIDATE_INT
    )
    : null;

$roomTypeId = isset($_GET['room_type_id'])
    ? filter_var(
        $_GET['room_type_id'],
        FILTER_VALIDATE_INT
    )
    : null;

$customerId = isset($_GET['customer_id'])
    ? filter_var(
        $_GET['customer_id'],
        FILTER_VALIDATE_INT
    )
    : null;


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

$search = isset($_GET['search'])
    ? trim((string)$_GET['search'])
    : '';


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
    ? strtolower(trim((string)$_GET['sort_by']))
    : 'created_at';

$sortOrder = isset($_GET['sort_order'])
    ? strtolower(trim((string)$_GET['sort_order']))
    : 'desc';


/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

$errors = [];


/*
|--------------------------------------------------------------------------
| Validate Dates
|--------------------------------------------------------------------------
*/

$dateFields = [
    'check_in_from' => $checkInFrom,
    'check_in_to' => $checkInTo,
    'check_out_from' => $checkOutFrom,
    'check_out_to' => $checkOutTo,
    'created_from' => $createdFrom,
    'created_to' => $createdTo
];


foreach ($dateFields as $field => $value) {

    if (
        $value !== null
        && $value !== ''
        && !isValidDate($value)
    ) {

        $errors[$field] =
            $field . ' must be in YYYY-MM-DD format.';
    }
}


/*
|--------------------------------------------------------------------------
| Validate Date Ranges
|--------------------------------------------------------------------------
*/

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


if (
    $createdFrom
    && $createdTo
    && $createdFrom > $createdTo
) {

    $errors['created_range'] =
        'created_from cannot be greater than created_to.';
}


/*
|--------------------------------------------------------------------------
| Booking Status
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


if (!in_array(
    $bookingStatus,
    $allowedBookingStatuses,
    true
)) {

    $errors['booking_status'] =
        'Invalid booking_status.';
}


/*
|--------------------------------------------------------------------------
| Payment Status
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


if (!in_array(
    $paymentStatus,
    $allowedPaymentStatuses,
    true
)) {

    $errors['payment_status'] =
        'Invalid payment_status.';
}


/*
|--------------------------------------------------------------------------
| ID Validation
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
| Search Validation
|--------------------------------------------------------------------------
*/

if (strlen($search) > 150) {

    $errors['search'] =
        'search cannot exceed 150 characters.';
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
    'booking_reference'
];


if (!in_array(
    $sortBy,
    $allowedSortColumns,
    true
)) {

    $errors['sort_by'] =
        'Invalid sort_by.';
}


if (!in_array(
    $sortOrder,
    ['asc', 'desc'],
    true
)) {

    $errors['sort_order'] =
        'sort_order must be asc or desc.';
}


/*
|--------------------------------------------------------------------------
| Validation Response
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
| Normalize
|--------------------------------------------------------------------------
*/

$page = (int)$page;
$limit = (int)$limit;


/*
|--------------------------------------------------------------------------
| SQL Date Values
|--------------------------------------------------------------------------
*/

$checkInFromSql =
    $checkInFrom ?: null;

$checkInToSql =
    $checkInTo ?: null;

$checkOutFromSql =
    $checkOutFrom ?: null;

$checkOutToSql =
    $checkOutTo ?: null;

$createdFromSql =
    $createdFrom
        ? $createdFrom . ' 00:00:00'
        : null;

$createdToSql =
    $createdTo
        ? $createdTo . ' 23:59:59'
        : null;


/*
|--------------------------------------------------------------------------
| Common WHERE Builder
|--------------------------------------------------------------------------
*/

$where = [];

$params = [];

$types = '';


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
| Check-in
|--------------------------------------------------------------------------
*/

if ($checkInFromSql !== null) {

    $where[] =
        'rb.check_in_date >= ?';

    $params[] =
        $checkInFromSql;

    $types .= 's';
}


if ($checkInToSql !== null) {

    $where[] =
        'rb.check_in_date <= ?';

    $params[] =
        $checkInToSql;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Check-out
|--------------------------------------------------------------------------
*/

if ($checkOutFromSql !== null) {

    $where[] =
        'rb.check_out_date >= ?';

    $params[] =
        $checkOutFromSql;

    $types .= 's';
}


if ($checkOutToSql !== null) {

    $where[] =
        'rb.check_out_date <= ?';

    $params[] =
        $checkOutToSql;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Created Date
|--------------------------------------------------------------------------
*/

if ($createdFromSql !== null) {

    $where[] =
        'rb.created_at >= ?';

    $params[] =
        $createdFromSql;

    $types .= 's';
}


if ($createdToSql !== null) {

    $where[] =
        'rb.created_at <= ?';

    $params[] =
        $createdToSql;

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

            FROM booking_rooms br_filter

            WHERE br_filter.booking_id = rb.id

              AND br_filter.room_id = ?
        )
    ";

    $params[] =
        (int)$roomId;

    $types .= 'i';
}


/*
|--------------------------------------------------------------------------
| Room Type
|--------------------------------------------------------------------------
*/

if ($roomTypeId !== null) {

    $where[] = "
        EXISTS (
            SELECT 1

            FROM booking_rooms br_type

            INNER JOIN rooms r_type
                ON r_type.id = br_type.room_id

            WHERE br_type.booking_id = rb.id

              AND r_type.room_type_id = ?
        )
    ";

    $params[] =
        (int)$roomTypeId;

    $types .= 'i';
}


/*
|--------------------------------------------------------------------------
| Customer
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

            OR c.phone LIKE ?

            OR c.email LIKE ?

            OR EXISTS (
                SELECT 1

                FROM booking_rooms br_search

                INNER JOIN rooms r_search
                    ON r_search.id = br_search.room_id

                WHERE br_search.booking_id = rb.id

                AND (
                    r_search.room_number LIKE ?

                    OR r_search.name LIKE ?
                )
            )
        )
    ";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= 'ssssss';
}


/*
|--------------------------------------------------------------------------
| WHERE SQL
|--------------------------------------------------------------------------
*/

$whereSql = '';

if (!empty($where)) {

    $whereSql =
        'WHERE ' . implode(
            ' AND ',
            $where
        );
}


/*
|--------------------------------------------------------------------------
| Total Booking Count
|--------------------------------------------------------------------------
*/

$totalBookings = 0;


$sql = "
    SELECT
        COUNT(DISTINCT rb.id) AS total

    FROM room_bookings rb

    INNER JOIN customers c
        ON c.id = rb.customer_id

    {$whereSql}
";


$stmt =
    executeStatement(
        $conn,
        $sql,
        $types,
        $params
    );


$result =
    $stmt->get_result();


if ($row = $result->fetch_assoc()) {

    $totalBookings =
        (int)$row['total'];
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| Booking Summary
|--------------------------------------------------------------------------
*/

$summary = [
    'total_bookings' => 0,
    'pending' => 0,
    'confirmed' => 0,
    'checked_in' => 0,
    'checked_out' => 0,
    'cancelled' => 0,
    'no_show' => 0,

    'total_booking_value' => 0.00,

    'paid_booking_value' => 0.00,
    'partial_booking_value' => 0.00,
    'pending_booking_value' => 0.00,
    'failed_booking_value' => 0.00,
    'refunded_booking_value' => 0.00,

    'total_guests' => 0
];


$sql = "
    SELECT

        COUNT(DISTINCT rb.id)
            AS total_bookings,

        COALESCE(
            SUM(
                CASE
                    WHEN rb.booking_status = 'pending'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS pending_count,

        COALESCE(
            SUM(
                CASE
                    WHEN rb.booking_status = 'confirmed'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS confirmed_count,

        COALESCE(
            SUM(
                CASE
                    WHEN rb.booking_status = 'checked_in'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS checked_in_count,

        COALESCE(
            SUM(
                CASE
                    WHEN rb.booking_status = 'checked_out'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS checked_out_count,

        COALESCE(
            SUM(
                CASE
                    WHEN rb.booking_status = 'cancelled'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS cancelled_count,

        COALESCE(
            SUM(
                CASE
                    WHEN rb.booking_status = 'no_show'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS no_show_count,

        COALESCE(
            SUM(rb.total_amount),
            0
        ) AS total_booking_value,

        COALESCE(
            SUM(
                CASE
                    WHEN rb.payment_status = 'paid'
                    THEN rb.total_amount
                    ELSE 0
                END
            ),
            0
        ) AS paid_value,

        COALESCE(
            SUM(
                CASE
                    WHEN rb.payment_status = 'partial'
                    THEN rb.total_amount
                    ELSE 0
                END
            ),
            0
        ) AS partial_value,

        COALESCE(
            SUM(
                CASE
                    WHEN rb.payment_status = 'pending'
                    THEN rb.total_amount
                    ELSE 0
                END
            ),
            0
        ) AS pending_value,

        COALESCE(
            SUM(
                CASE
                    WHEN rb.payment_status = 'failed'
                    THEN rb.total_amount
                    ELSE 0
                END
            ),
            0
        ) AS failed_value,

        COALESCE(
            SUM(
                CASE
                    WHEN rb.payment_status = 'refunded'
                    THEN rb.total_amount
                    ELSE 0
                END
            ),
            0
        ) AS refunded_value,

        COALESCE(
            SUM(rb.total_guests),
            0
        ) AS total_guests

    FROM room_bookings rb

    INNER JOIN customers c
        ON c.id = rb.customer_id

    {$whereSql}
";


$stmt =
    executeStatement(
        $conn,
        $sql,
        $types,
        $params
    );


$result =
    $stmt->get_result();


if ($row = $result->fetch_assoc()) {

    $summary = [

        'total_bookings' =>
            (int)$row['total_bookings'],

        'pending' =>
            (int)$row['pending_count'],

        'confirmed' =>
            (int)$row['confirmed_count'],

        'checked_in' =>
            (int)$row['checked_in_count'],

        'checked_out' =>
            (int)$row['checked_out_count'],

        'cancelled' =>
            (int)$row['cancelled_count'],

        'no_show' =>
            (int)$row['no_show_count'],

        'total_booking_value' =>
            round(
                (float)$row['total_booking_value'],
                2
            ),

        'paid_booking_value' =>
            round(
                (float)$row['paid_value'],
                2
            ),

        'partial_booking_value' =>
            round(
                (float)$row['partial_value'],
                2
            ),

        'pending_booking_value' =>
            round(
                (float)$row['pending_value'],
                2
            ),

        'failed_booking_value' =>
            round(
                (float)$row['failed_value'],
                2
            ),

        'refunded_booking_value' =>
            round(
                (float)$row['refunded_value'],
                2
            ),

        'total_guests' =>
            (int)$row['total_guests']
    ];
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| Total Nights
|--------------------------------------------------------------------------
*/

$totalNights = 0;


$sql = "
    SELECT
        COALESCE(
            SUM(br.number_of_nights),
            0
        ) AS total_nights

    FROM booking_rooms br

    INNER JOIN room_bookings rb
        ON rb.id = br.booking_id

    INNER JOIN customers c
        ON c.id = rb.customer_id

    {$whereSql}
";


$stmt =
    executeStatement(
        $conn,
        $sql,
        $types,
        $params
    );


$result =
    $stmt->get_result();


if ($row = $result->fetch_assoc()) {

    $totalNights =
        (int)$row['total_nights'];
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| Sorting
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

    'booking_reference' =>
        'rb.booking_reference'
];


$orderColumn =
    $sortMap[$sortBy];

$orderDirection =
    strtoupper($sortOrder);


/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

$offset =
    ($page - 1) * $limit;


/*
|--------------------------------------------------------------------------
| Main Booking Query
|--------------------------------------------------------------------------
|
| IMPORTANT:
| No JSON_ARRAYAGG()
|
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
            SELECT COUNT(*)

            FROM booking_rooms br_count

            WHERE br_count.booking_id = rb.id
        ) AS room_count,


        (
            SELECT COALESCE(
                SUM(br_nights.number_of_nights),
                0
            )

            FROM booking_rooms br_nights

            WHERE br_nights.booking_id = rb.id
        ) AS total_nights,


        (
            SELECT COALESCE(
                SUM(br_total.room_total),
                0
            )

            FROM booking_rooms br_total

            WHERE br_total.booking_id = rb.id
        ) AS room_total,


        (
            SELECT COUNT(*)

            FROM booking_food_packages bfp_count

            WHERE bfp_count.booking_id = rb.id
        ) AS food_package_count,


        (
            SELECT COUNT(*)

            FROM booking_food_items bfi_count

            WHERE bfi_count.booking_id = rb.id
        ) AS food_item_count,


        (
            SELECT COALESCE(
                SUM(
                    CASE
                        WHEN p_sum.payment_status = 'captured'
                        THEN p_sum.amount
                        ELSE 0
                    END
                ),
                0
            )

            FROM payments p_sum

            WHERE p_sum.booking_id = rb.id
        ) AS captured_payment_amount,


        (
            SELECT COUNT(*)

            FROM payments p_count

            WHERE p_count.booking_id = rb.id
        ) AS payment_count,


        (
            SELECT p_last.payment_status

            FROM payments p_last

            WHERE p_last.booking_id = rb.id

            ORDER BY p_last.id DESC

            LIMIT 1
        ) AS last_payment_status,


        (
            SELECT p_last.razorpay_payment_id

            FROM payments p_last

            WHERE p_last.booking_id = rb.id

            ORDER BY p_last.id DESC

            LIMIT 1
        ) AS last_razorpay_payment_id,


        (
            SELECT COUNT(*)

            FROM booking_activity_logs bal_count

            WHERE bal_count.booking_id = rb.id
        ) AS activity_count


    FROM room_bookings rb

    INNER JOIN customers c
        ON c.id = rb.customer_id

    {$whereSql}

    ORDER BY {$orderColumn} {$orderDirection}

    LIMIT ? OFFSET ?
";


$recordParams =
    $params;

$recordTypes =
    $types . 'ii';

$recordParams[] =
    $limit;

$recordParams[] =
    $offset;


$stmt =
    executeStatement(
        $conn,
        $sql,
        $recordTypes,
        $recordParams
    );


$result =
    $stmt->get_result();


$bookings = [];


while ($row = $result->fetch_assoc()) {

    $bookingId =
        (int)$row['id'];


    /*
    |--------------------------------------------------------------------------
    | Room Details
    |--------------------------------------------------------------------------
    */

    $roomsData = [];


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

            r.room_type_id,

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
        executeStatement(
            $conn,
            $roomSql,
            'i',
            [$bookingId]
        );


    $roomResult =
        $roomStmt->get_result();


    while (
        $roomRow =
        $roomResult->fetch_assoc()
    ) {

        $roomsData[] = [

            'id' =>
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

            'price_per_night' =>
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
                )
        ];
    }


    $roomStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Food Packages
    |--------------------------------------------------------------------------
    */

    $foodPackagesData = [];


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
        executeStatement(
            $conn,
            $foodPackageSql,
            'i',
            [$bookingId]
        );


    $foodPackageResult =
        $foodPackageStmt->get_result();


    while (
        $foodPackageRow =
        $foodPackageResult->fetch_assoc()
    ) {

        $foodPackagesData[] = [

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

    $foodItemsData = [];


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

            fc.id AS food_category_id,

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
        executeStatement(
            $conn,
            $foodItemSql,
            'i',
            [$bookingId]
        );


    $foodItemResult =
        $foodItemStmt->get_result();


    while (
        $foodItemRow =
        $foodItemResult->fetch_assoc()
    ) {

        $foodItemsData[] = [

            'id' =>
                (int)$foodItemRow['id'],

            'food_item_id' =>
                (int)$foodItemRow['food_item_id'],

            'food_item_name' =>
                $foodItemRow['food_item_name'],

            'food_item_description' =>
                $foodItemRow['food_item_description'],

            'food_type' =>
                $foodItemRow['food_type'],

            'food_category_id' =>
                $foodItemRow['food_category_id'] !== null
                    ? (int)$foodItemRow['food_category_id']
                    : null,

            'food_category_name' =>
                $foodItemRow['food_category_name'],

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

    $paymentsData = [];


    $paymentSql = "
        SELECT

            id,

            payment_gateway,

            razorpay_order_id,

            razorpay_payment_id,

            amount,

            currency,

            payment_status,

            payment_method,

            paid_at,

            created_at,

            updated_at

        FROM payments

        WHERE booking_id = ?

        ORDER BY id DESC
    ";


    $paymentStmt =
        executeStatement(
            $conn,
            $paymentSql,
            'i',
            [$bookingId]
        );


    $paymentResult =
        $paymentStmt->get_result();


    while (
        $paymentRow =
        $paymentResult->fetch_assoc()
    ) {

        $paymentsData[] = [

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
    | Activity Logs
    |--------------------------------------------------------------------------
    */

    $activityData = [];


    $activitySql = "
        SELECT

            bal.id,

            bal.action,

            bal.description,

            bal.performed_by_admin_id,

            au.name AS performed_by_name,

            au.role AS performed_by_role,

            bal.created_at

        FROM booking_activity_logs bal

        LEFT JOIN admin_users au
            ON au.id = bal.performed_by_admin_id

        WHERE bal.booking_id = ?

        ORDER BY bal.id DESC
    ";


    $activityStmt =
        executeStatement(
            $conn,
            $activitySql,
            'i',
            [$bookingId]
        );


    $activityResult =
        $activityStmt->get_result();


    while (
        $activityRow =
        $activityResult->fetch_assoc()
    ) {

        $activityData[] = [

            'id' =>
                (int)$activityRow['id'],

            'action' =>
                $activityRow['action'],

            'description' =>
                $activityRow['description'],

            'performed_by_admin_id' =>
                $activityRow['performed_by_admin_id'] !== null
                    ? (int)$activityRow['performed_by_admin_id']
                    : null,

            'performed_by_name' =>
                $activityRow['performed_by_name'],

            'performed_by_role' =>
                $activityRow['performed_by_role'],

            'created_at' =>
                $activityRow['created_at']
        ];
    }


    $activityStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Balance
    |--------------------------------------------------------------------------
    */

    $totalCaptured =
        (float)$row['captured_payment_amount'];

    $totalAmount =
        (float)$row['total_amount'];

    $balanceAmount =
        max(
            0,
            $totalAmount - $totalCaptured
        );


    /*
    |--------------------------------------------------------------------------
    | Booking Object
    |--------------------------------------------------------------------------
    */

    $bookings[] = [

        'id' =>
            $bookingId,

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

            'total_nights' =>
                (int)$row['total_nights'],

            'total_guests' =>
                (int)$row['total_guests']
        ],


        /*
        |--------------------------------------------------------------------------
        | Rooms
        |--------------------------------------------------------------------------
        */

        'rooms' => [

            'count' =>
                (int)$row['room_count'],

            'room_total' =>
                round(
                    (float)$row['room_total'],
                    2
                ),

            'items' =>
                $roomsData
        ],


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
                $foodPackagesData,

            'custom_items' =>
                $foodItemsData
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

            'captured_payment_amount' =>
                round(
                    $totalCaptured,
                    2
                ),

            'balance_amount' =>
                round(
                    $balanceAmount,
                    2
                )
        ],


        /*
        |--------------------------------------------------------------------------
        | Payment
        |--------------------------------------------------------------------------
        */

        'payment' => [

            'booking_payment_status' =>
                $row['payment_status'],

            'last_payment_status' =>
                $row['last_payment_status'],

            'payment_count' =>
                (int)$row['payment_count'],

            'last_razorpay_payment_id' =>
                $row['last_razorpay_payment_id'],

            'payments' =>
                $paymentsData
        ],


        /*
        |--------------------------------------------------------------------------
        | Status
        |--------------------------------------------------------------------------
        */

        'status' => [

            'booking_status' =>
                $row['booking_status'],

            'cancellation_reason' =>
                $row['cancellation_reason']
        ],


        /*
        |--------------------------------------------------------------------------
        | Special Request
        |--------------------------------------------------------------------------
        */

        'special_request' =>
            $row['special_request'],


        /*
        |--------------------------------------------------------------------------
        | Activity
        |--------------------------------------------------------------------------
        */

        'activity_count' =>
            (int)$row['activity_count'],

        'activity_logs' =>
            $activityData,


        /*
        |--------------------------------------------------------------------------
        | Dates
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
            'label' => 'Bookings',

            'data' => [

                $summary['pending'],

                $summary['confirmed'],

                $summary['checked_in'],

                $summary['checked_out'],

                $summary['cancelled'],

                $summary['no_show']
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
            'label' => 'Booking Value',

            'data' => [

                $summary['pending_booking_value'],

                $summary['partial_booking_value'],

                $summary['paid_booking_value'],

                $summary['failed_booking_value'],

                $summary['refunded_booking_value']
            ]
        ]
    ]
];


/*
|--------------------------------------------------------------------------
| Monthly Booking Trend
|--------------------------------------------------------------------------
*/

$monthlyBookingWhere = [];

$monthlyBookingParams = [];

$monthlyBookingTypes = '';


if ($bookingStatus !== 'all') {

    $monthlyBookingWhere[] =
        'rb.booking_status = ?';

    $monthlyBookingParams[] =
        $bookingStatus;

    $monthlyBookingTypes .= 's';
}


if ($paymentStatus !== 'all') {

    $monthlyBookingWhere[] =
        'rb.payment_status = ?';

    $monthlyBookingParams[] =
        $paymentStatus;

    $monthlyBookingTypes .= 's';
}


if ($createdFromSql !== null) {

    $monthlyBookingWhere[] =
        'rb.created_at >= ?';

    $monthlyBookingParams[] =
        $createdFromSql;

    $monthlyBookingTypes .= 's';
}


if ($createdToSql !== null) {

    $monthlyBookingWhere[] =
        'rb.created_at <= ?';

    $monthlyBookingParams[] =
        $createdToSql;

    $monthlyBookingTypes .= 's';
}


$monthlyBookingWhereSql = '';

if (!empty($monthlyBookingWhere)) {

    $monthlyBookingWhereSql =
        'WHERE ' .
        implode(
            ' AND ',
            $monthlyBookingWhere
        );
}


$monthlyBookings = [];


$sql = "
    SELECT

        DATE_FORMAT(
            rb.created_at,
            '%Y-%m'
        ) AS booking_month,

        COUNT(*) AS booking_count,

        COALESCE(
            SUM(rb.total_amount),
            0
        ) AS booking_value,

        COALESCE(
            SUM(rb.total_guests),
            0
        ) AS guest_count

    FROM room_bookings rb

    {$monthlyBookingWhereSql}

    GROUP BY
        DATE_FORMAT(
            rb.created_at,
            '%Y-%m'
        )

    ORDER BY booking_month ASC
";


$stmt =
    executeStatement(
        $conn,
        $sql,
        $monthlyBookingTypes,
        $monthlyBookingParams
    );


$result =
    $stmt->get_result();


while ($row = $result->fetch_assoc()) {

    $monthlyBookings[] = [

        'month' =>
            $row['booking_month'],

        'booking_count' =>
            (int)$row['booking_count'],

        'booking_value' =>
            round(
                (float)$row['booking_value'],
                2
            ),

        'guest_count' =>
            (int)$row['guest_count']
    ];
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| Monthly Chart
|--------------------------------------------------------------------------
*/

$monthlyLabels = [];

$monthlyBookingCountData = [];

$monthlyBookingValueData = [];

$monthlyGuestData = [];


foreach (
    $monthlyBookings
    as $month
) {

    $monthlyLabels[] =
        $month['month'];

    $monthlyBookingCountData[] =
        $month['booking_count'];

    $monthlyBookingValueData[] =
        $month['booking_value'];

    $monthlyGuestData[] =
        $month['guest_count'];
}


$monthlyBookingChart = [

    'labels' =>
        $monthlyLabels,

    'datasets' => [

        [
            'label' =>
                'Bookings',

            'data' =>
                $monthlyBookingCountData
        ],

        [
            'label' =>
                'Booking Value',

            'data' =>
                $monthlyBookingValueData
        ],

        [
            'label' =>
                'Guests',

            'data' =>
                $monthlyGuestData
        ]
    ]
];


/*
|--------------------------------------------------------------------------
| Upcoming Check-ins
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

        c.name AS customer_name,

        c.phone AS customer_phone,

        (
            SELECT
                GROUP_CONCAT(
                    DISTINCT r_up.room_number
                    ORDER BY r_up.room_number
                    SEPARATOR ', '
                )

            FROM booking_rooms br_up

            INNER JOIN rooms r_up
                ON r_up.id = br_up.room_id

            WHERE br_up.booking_id = rb.id
        ) AS room_numbers

    FROM room_bookings rb

    INNER JOIN customers c
        ON c.id = rb.customer_id

    WHERE rb.check_in_date >= CURDATE()

      AND rb.booking_status IN (
          'pending',
          'confirmed'
      )

    ORDER BY
        rb.check_in_date ASC,
        rb.id ASC

    LIMIT 10
";


$stmt =
    executeStatement(
        $conn,
        $sql
    );


$result =
    $stmt->get_result();


while ($row = $result->fetch_assoc()) {

    $upcomingCheckIns[] = [

        'booking_id' =>
            (int)$row['id'],

        'booking_reference' =>
            $row['booking_reference'],

        'customer_name' =>
            $row['customer_name'],

        'customer_phone' =>
            $row['customer_phone'],

        'check_in_date' =>
            $row['check_in_date'],

        'check_out_date' =>
            $row['check_out_date'],

        'room_numbers' =>
            $row['room_numbers'],

        'total_guests' =>
            (int)$row['total_guests'],

        'total_amount' =>
            round(
                (float)$row['total_amount'],
                2
            ),

        'payment_status' =>
            $row['payment_status'],

        'booking_status' =>
            $row['booking_status']
    ];
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| Today's Check-in / Check-out
|--------------------------------------------------------------------------
*/

$todaySummary = [

    'check_ins' => 0,

    'check_outs' => 0
];


$sql = "
    SELECT

        COALESCE(
            SUM(
                CASE
                    WHEN check_in_date = CURDATE()
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS check_ins,

        COALESCE(
            SUM(
                CASE
                    WHEN check_out_date = CURDATE()
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS check_outs

    FROM room_bookings

    WHERE booking_status NOT IN (
        'cancelled',
        'no_show'
    )
";


$stmt =
    executeStatement(
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
            (int)$row['check_outs']
    ];
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| Room Occupancy
|--------------------------------------------------------------------------
*/

$roomOccupancy = [

    'total_rooms' => 0,

    'available_rooms' => 0,

    'maintenance_rooms' => 0,

    'inactive_rooms' => 0,

    'currently_occupied_rooms' => 0
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
    executeStatement(
        $conn,
        $sql
    );


$result =
    $stmt->get_result();


if ($row = $result->fetch_assoc()) {

    $roomOccupancy['total_rooms'] =
        (int)$row['total_rooms'];

    $roomOccupancy['available_rooms'] =
        (int)$row['available_rooms'];

    $roomOccupancy['maintenance_rooms'] =
        (int)$row['maintenance_rooms'];

    $roomOccupancy['inactive_rooms'] =
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
        COUNT(DISTINCT br.room_id)
            AS occupied_rooms

    FROM booking_rooms br

    INNER JOIN room_bookings rb
        ON rb.id = br.booking_id

    WHERE rb.booking_status = 'checked_in'

      AND rb.check_in_date <= CURDATE()

      AND rb.check_out_date > CURDATE()
";


$stmt =
    executeStatement(
        $conn,
        $sql
    );


$result =
    $stmt->get_result();


if ($row = $result->fetch_assoc()) {

    $roomOccupancy['currently_occupied_rooms'] =
        (int)$row['occupied_rooms'];
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| Occupancy Percentage
|--------------------------------------------------------------------------
*/

$occupancyPercentage = 0.00;


if (
    $roomOccupancy['total_rooms'] > 0
) {

    $occupancyPercentage =
        round(
            (
                $roomOccupancy['currently_occupied_rooms']
                /
                $roomOccupancy['total_rooms']
            ) * 100,
            2
        );
}


/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

$totalPages =
    $totalBookings > 0
        ? (int)ceil(
            $totalBookings / $limit
        )
        : 0;


/*
|--------------------------------------------------------------------------
| Average Booking Value
|--------------------------------------------------------------------------
*/

$averageBookingValue = 0.00;


if ($summary['total_bookings'] > 0) {

    $averageBookingValue =
        round(
            $summary['total_booking_value']
            /
            $summary['total_bookings'],
            2
        );
}


/*
|--------------------------------------------------------------------------
| Status Percentages
|--------------------------------------------------------------------------
*/

$statusPercentages = [];


if ($summary['total_bookings'] > 0) {

    $statusPercentages = [

        'pending' =>
            round(
                (
                    $summary['pending']
                    /
                    $summary['total_bookings']
                ) * 100,
                2
            ),

        'confirmed' =>
            round(
                (
                    $summary['confirmed']
                    /
                    $summary['total_bookings']
                ) * 100,
                2
            ),

        'checked_in' =>
            round(
                (
                    $summary['checked_in']
                    /
                    $summary['total_bookings']
                ) * 100,
                2
            ),

        'checked_out' =>
            round(
                (
                    $summary['checked_out']
                    /
                    $summary['total_bookings']
                ) * 100,
                2
            ),

        'cancelled' =>
            round(
                (
                    $summary['cancelled']
                    /
                    $summary['total_bookings']
                ) * 100,
                2
            ),

        'no_show' =>
            round(
                (
                    $summary['no_show']
                    /
                    $summary['total_bookings']
                ) * 100,
                2
            )
    ];
}


/*
|--------------------------------------------------------------------------
| Recent Bookings
|--------------------------------------------------------------------------
*/

$recentBookings =
    array_slice(
        $bookings,
        0,
        10
    );


/*
|--------------------------------------------------------------------------
| Final Response
|--------------------------------------------------------------------------
*/

sendResponse(
    true,
    'Booking dashboard data fetched successfully.',
    [

        /*
        |--------------------------------------------------------------------------
        | Meta
        |--------------------------------------------------------------------------
        */

        'meta' => [

            'api' =>
                'dashboard/booking.php',

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
                'all_room_bookings'
        ],


        /*
        |--------------------------------------------------------------------------
        | Filters
        |--------------------------------------------------------------------------
        */

        'filters' => [

            'check_in_from' =>
                $checkInFrom,

            'check_in_to' =>
                $checkInTo,

            'check_out_from' =>
                $checkOutFrom,

            'check_out_to' =>
                $checkOutTo,

            'created_from' =>
                $createdFrom,

            'created_to' =>
                $createdTo,

            'booking_status' =>
                $bookingStatus,

            'payment_status' =>
                $paymentStatus,

            'room_id' =>
                $roomId,

            'room_type_id' =>
                $roomTypeId,

            'customer_id' =>
                $customerId,

            'search' =>
                $search,

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
        | KPIs
        |--------------------------------------------------------------------------
        */

        'kpis' => [

            'total_bookings' =>
                $summary['total_bookings'],

            'total_booking_value' =>
                $summary['total_booking_value'],

            'average_booking_value' =>
                $averageBookingValue,

            'total_guests' =>
                $summary['total_guests'],

            'total_nights' =>
                $totalNights,

            'paid_booking_value' =>
                $summary['paid_booking_value'],

            'partial_booking_value' =>
                $summary['partial_booking_value'],

            'pending_booking_value' =>
                $summary['pending_booking_value'],

            'failed_booking_value' =>
                $summary['failed_booking_value'],

            'refunded_booking_value' =>
                $summary['refunded_booking_value'],

            'today_check_ins' =>
                $todaySummary['check_ins'],

            'today_check_outs' =>
                $todaySummary['check_outs'],

            'occupancy_percentage' =>
                $occupancyPercentage
        ],


        /*
        |--------------------------------------------------------------------------
        | Summary
        |--------------------------------------------------------------------------
        */

        'summary' =>
            $summary,


        /*
        |--------------------------------------------------------------------------
        | Status Percentages
        |--------------------------------------------------------------------------
        */

        'status_percentages' =>
            $statusPercentages,


        /*
        |--------------------------------------------------------------------------
        | Room Occupancy
        |--------------------------------------------------------------------------
        */

        'room_occupancy' =>
            $roomOccupancy,


        /*
        |--------------------------------------------------------------------------
        | Charts
        |--------------------------------------------------------------------------
        */

        'charts' => [

            'booking_status' =>
                $bookingStatusChart,

            'payment_status' =>
                $paymentStatusChart,

            'monthly_booking_trend' =>
                $monthlyBookingChart
        ],


        /*
        |--------------------------------------------------------------------------
        | Monthly
        |--------------------------------------------------------------------------
        */

        'monthly' =>
            $monthlyBookings,


        /*
        |--------------------------------------------------------------------------
        | Upcoming Check-ins
        |--------------------------------------------------------------------------
        */

        'upcoming_check_ins' =>
            $upcomingCheckIns,


        /*
        |--------------------------------------------------------------------------
        | Recent
        |--------------------------------------------------------------------------
        */

        'recent_bookings' =>
            $recentBookings,


        /*
        |--------------------------------------------------------------------------
        | All Booking Records
        |--------------------------------------------------------------------------
        */

        'bookings' =>
            $bookings,


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
                $totalBookings,

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