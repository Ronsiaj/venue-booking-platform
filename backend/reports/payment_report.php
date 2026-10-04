<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| PAYMENT REPORT
|--------------------------------------------------------------------------
| Method  : GET
| Access  : Admin / Staff
| Domain  : Room Booking Payments
| Gateway : Razorpay
|--------------------------------------------------------------------------
*/

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");


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
| METHOD VALIDATION
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {

    http_response_code(405);

    echo json_encode([
        'status' => false,
        'message' => 'Only GET method is allowed.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| CONFIG
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../config/middleware.php';


/*
|--------------------------------------------------------------------------
| JSON RESPONSE
|--------------------------------------------------------------------------
*/

function jsonResponse(
    bool $status,
    string $message,
    array $data = [],
    int $httpCode = 200
): void {

    http_response_code($httpCode);

    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


/*
|--------------------------------------------------------------------------
| INTEGER PARAMETER
|--------------------------------------------------------------------------
*/

function getIntParam(
    string $key,
    int $default = 0,
    int $min = 0,
    int $max = PHP_INT_MAX
): int {

    if (
        !isset($_GET[$key]) ||
        $_GET[$key] === ''
    ) {
        return $default;
    }

    $value = filter_var(
        $_GET[$key],
        FILTER_VALIDATE_INT
    );

    if ($value === false) {

        jsonResponse(
            false,
            "Invalid {$key}. It must be an integer.",
            [],
            422
        );
    }

    if (
        $value < $min ||
        $value > $max
    ) {

        jsonResponse(
            false,
            "Invalid {$key}. Allowed range: {$min} to {$max}.",
            [],
            422
        );
    }

    return (int)$value;
}


/*
|--------------------------------------------------------------------------
| FLOAT / MONEY PARAMETER
|--------------------------------------------------------------------------
*/

function getFloatParam(
    string $key,
    ?float $default = null,
    float $min = 0,
    float $max = 999999999999
): ?float {

    if (
        !isset($_GET[$key]) ||
        $_GET[$key] === ''
    ) {
        return $default;
    }

    $value = trim(
        (string)$_GET[$key]
    );

    if (
        !preg_match(
            '/^\d+(\.\d{1,2})?$/',
            $value
        )
    ) {

        jsonResponse(
            false,
            "{$key} must be a valid amount with maximum 2 decimal places.",
            [],
            422
        );
    }

    $number = (float)$value;

    if (
        $number < $min ||
        $number > $max
    ) {

        jsonResponse(
            false,
            "Invalid {$key}. Allowed range: {$min} to {$max}.",
            [],
            422
        );
    }

    return $number;
}


/*
|--------------------------------------------------------------------------
| STRING PARAMETER
|--------------------------------------------------------------------------
*/

function getStringParam(
    string $key,
    ?string $default = null,
    int $maxLength = 255
): ?string {

    if (!isset($_GET[$key])) {
        return $default;
    }

    $value = trim(
        (string)$_GET[$key]
    );

    if ($value === '') {
        return $default;
    }

    if (
        mb_strlen($value) > $maxLength
    ) {

        jsonResponse(
            false,
            "{$key} cannot exceed {$maxLength} characters.",
            [],
            422
        );
    }

    return $value;
}


/*
|--------------------------------------------------------------------------
| DATE VALIDATION
|--------------------------------------------------------------------------
*/

function getDateParam(
    string $key
): ?string {

    if (
        !isset($_GET[$key]) ||
        trim((string)$_GET[$key]) === ''
    ) {
        return null;
    }

    $value = trim(
        (string)$_GET[$key]
    );

    $date = DateTime::createFromFormat(
        'Y-m-d',
        $value
    );

    if (
        !$date ||
        $date->format('Y-m-d') !== $value
    ) {

        jsonResponse(
            false,
            "Invalid {$key}. Expected YYYY-MM-DD.",
            [],
            422
        );
    }

    return $value;
}


/*
|--------------------------------------------------------------------------
| DATE RANGE VALIDATION
|--------------------------------------------------------------------------
*/

function validateDateRange(
    ?string $from,
    ?string $to,
    string $fromName,
    string $toName
): void {

    if (
        $from !== null &&
        $to !== null &&
        $from > $to
    ) {

        jsonResponse(
            false,
            "{$fromName} cannot be greater than {$toName}.",
            [],
            422
        );
    }
}


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
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
        ['admin', 'staff'],
        true
    )
) {

    jsonResponse(
        false,
        'Only admin or staff users can access payment reports.',
        [],
        403
    );
}


if (
    $currentUserId === null ||
    !is_numeric($currentUserId)
) {

    jsonResponse(
        false,
        'Invalid authenticated user.',
        [],
        401
    );
}

$currentUserId = (int)$currentUserId;


/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$paymentId = getIntParam(
    'payment_id',
    0,
    0
);

$bookingId = getIntParam(
    'booking_id',
    0,
    0
);

$customerId = getIntParam(
    'customer_id',
    0,
    0
);

$page = getIntParam(
    'page',
    1,
    1,
    1000000
);

$limit = getIntParam(
    'limit',
    20,
    1,
    100
);


$paymentStatus = getStringParam(
    'payment_status',
    'all',
    30
);

$bookingStatus = getStringParam(
    'booking_status',
    'all',
    30
);

$paymentMethod = getStringParam(
    'payment_method',
    'all',
    50
);

$currency = getStringParam(
    'currency',
    'all',
    10
);

$search = getStringParam(
    'search',
    null,
    150
);

$razorpayOrderId = getStringParam(
    'razorpay_order_id',
    null,
    150
);

$razorpayPaymentId = getStringParam(
    'razorpay_payment_id',
    null,
    150
);

$fromDate = getDateParam(
    'from_date'
);

$toDate = getDateParam(
    'to_date'
);

$paidFromDate = getDateParam(
    'paid_from_date'
);

$paidToDate = getDateParam(
    'paid_to_date'
);

$minAmount = getFloatParam(
    'min_amount',
    null
);

$maxAmount = getFloatParam(
    'max_amount',
    null
);

$sortBy = getStringParam(
    'sort_by',
    'created_at',
    50
);

$sortOrder = strtolower(
    getStringParam(
        'sort_order',
        'desc',
        10
    )
);


/*
|--------------------------------------------------------------------------
| ALLOWED VALUES
|--------------------------------------------------------------------------
*/

$allowedPaymentStatuses = [
    'all',
    'created',
    'authorized',
    'captured',
    'failed',
    'refunded'
];

$allowedBookingStatuses = [
    'all',
    'pending',
    'confirmed',
    'checked_in',
    'checked_out',
    'cancelled',
    'no_show'
];


/*
|--------------------------------------------------------------------------
| Validate Payment Status
|--------------------------------------------------------------------------
*/

if (
    !in_array(
        $paymentStatus,
        $allowedPaymentStatuses,
        true
    )
) {

    jsonResponse(
        false,
        'Invalid payment_status.',
        [
            'allowed' =>
                $allowedPaymentStatuses
        ],
        422
    );
}


/*
|--------------------------------------------------------------------------
| Validate Booking Status
|--------------------------------------------------------------------------
*/

if (
    !in_array(
        $bookingStatus,
        $allowedBookingStatuses,
        true
    )
) {

    jsonResponse(
        false,
        'Invalid booking_status.',
        [
            'allowed' =>
                $allowedBookingStatuses
        ],
        422
    );
}


/*
|--------------------------------------------------------------------------
| Validate Sort Order
|--------------------------------------------------------------------------
*/

if (
    !in_array(
        $sortOrder,
        ['asc', 'desc'],
        true
    )
) {

    jsonResponse(
        false,
        'Invalid sort_order. Allowed: asc, desc.',
        [],
        422
    );
}


/*
|--------------------------------------------------------------------------
| Allowed Sort Columns
|--------------------------------------------------------------------------
*/

$allowedSortColumns = [

    'id' =>
        'p.id',

    'amount' =>
        'p.amount',

    'payment_status' =>
        'p.payment_status',

    'payment_method' =>
        'p.payment_method',

    'paid_at' =>
        'p.paid_at',

    'created_at' =>
        'p.created_at',

    'updated_at' =>
        'p.updated_at',

    'booking_reference' =>
        'rb.booking_reference',

    'customer_name' =>
        'c.name'
];


if (
    !array_key_exists(
        $sortBy,
        $allowedSortColumns
    )
) {

    jsonResponse(
        false,
        'Invalid sort_by.',
        [
            'allowed' =>
                array_keys(
                    $allowedSortColumns
                )
        ],
        422
    );
}


$sortColumn =
    $allowedSortColumns[$sortBy];


/*
|--------------------------------------------------------------------------
| Date Range Validation
|--------------------------------------------------------------------------
*/

validateDateRange(
    $fromDate,
    $toDate,
    'from_date',
    'to_date'
);

validateDateRange(
    $paidFromDate,
    $paidToDate,
    'paid_from_date',
    'paid_to_date'
);


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

    jsonResponse(
        false,
        'min_amount cannot be greater than max_amount.',
        [],
        422
    );
}


/*
|--------------------------------------------------------------------------
| Validate Payment ID
|--------------------------------------------------------------------------
*/

if ($paymentId > 0) {

    $stmt = $conn->prepare("
        SELECT id
        FROM payments
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {

        jsonResponse(
            false,
            'Failed to validate payment.',
            [],
            500
        );
    }

    $stmt->bind_param(
        'i',
        $paymentId
    );

    $stmt->execute();

    $result =
        $stmt->get_result();

    if ($result->num_rows === 0) {

        $stmt->close();

        jsonResponse(
            false,
            'Payment not found.',
            [],
            404
        );
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Validate Booking ID
|--------------------------------------------------------------------------
*/

if ($bookingId > 0) {

    $stmt = $conn->prepare("
        SELECT id
        FROM room_bookings
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {

        jsonResponse(
            false,
            'Failed to validate booking.',
            [],
            500
        );
    }

    $stmt->bind_param(
        'i',
        $bookingId
    );

    $stmt->execute();

    $result =
        $stmt->get_result();

    if ($result->num_rows === 0) {

        $stmt->close();

        jsonResponse(
            false,
            'Booking not found.',
            [],
            404
        );
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Validate Customer ID
|--------------------------------------------------------------------------
*/

if ($customerId > 0) {

    $stmt = $conn->prepare("
        SELECT id
        FROM customers
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {

        jsonResponse(
            false,
            'Failed to validate customer.',
            [],
            500
        );
    }

    $stmt->bind_param(
        'i',
        $customerId
    );

    $stmt->execute();

    $result =
        $stmt->get_result();

    if ($result->num_rows === 0) {

        $stmt->close();

        jsonResponse(
            false,
            'Customer not found.',
            [],
            404
        );
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Base WHERE
|--------------------------------------------------------------------------
*/

$where = [];

$params = [];

$types = '';


/*
|--------------------------------------------------------------------------
| Payment ID
|--------------------------------------------------------------------------
*/

if ($paymentId > 0) {

    $where[] =
        "p.id = ?";

    $params[] =
        $paymentId;

    $types .= 'i';
}


/*
|--------------------------------------------------------------------------
| Booking ID
|--------------------------------------------------------------------------
*/

if ($bookingId > 0) {

    $where[] =
        "p.booking_id = ?";

    $params[] =
        $bookingId;

    $types .= 'i';
}


/*
|--------------------------------------------------------------------------
| Customer ID
|--------------------------------------------------------------------------
*/

if ($customerId > 0) {

    $where[] =
        "rb.customer_id = ?";

    $params[] =
        $customerId;

    $types .= 'i';
}


/*
|--------------------------------------------------------------------------
| Payment Status
|--------------------------------------------------------------------------
*/

if ($paymentStatus !== 'all') {

    $where[] =
        "p.payment_status = ?";

    $params[] =
        $paymentStatus;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Booking Status
|--------------------------------------------------------------------------
*/

if ($bookingStatus !== 'all') {

    $where[] =
        "rb.booking_status = ?";

    $params[] =
        $bookingStatus;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Payment Method
|--------------------------------------------------------------------------
*/

if ($paymentMethod !== 'all') {

    $where[] =
        "p.payment_method = ?";

    $params[] =
        $paymentMethod;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Currency
|--------------------------------------------------------------------------
*/

if ($currency !== 'all') {

    $where[] =
        "p.currency = ?";

    $params[] =
        $currency;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Created Date
|--------------------------------------------------------------------------
*/

if ($fromDate !== null) {

    $where[] =
        "DATE(p.created_at) >= ?";

    $params[] =
        $fromDate;

    $types .= 's';
}


if ($toDate !== null) {

    $where[] =
        "DATE(p.created_at) <= ?";

    $params[] =
        $toDate;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Paid Date
|--------------------------------------------------------------------------
*/

if ($paidFromDate !== null) {

    $where[] =
        "DATE(p.paid_at) >= ?";

    $params[] =
        $paidFromDate;

    $types .= 's';
}


if ($paidToDate !== null) {

    $where[] =
        "DATE(p.paid_at) <= ?";

    $params[] =
        $paidToDate;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Amount
|--------------------------------------------------------------------------
*/

if ($minAmount !== null) {

    $where[] =
        "p.amount >= ?";

    $params[] =
        $minAmount;

    $types .= 'd';
}


if ($maxAmount !== null) {

    $where[] =
        "p.amount <= ?";

    $params[] =
        $maxAmount;

    $types .= 'd';
}


/*
|--------------------------------------------------------------------------
| Razorpay Order ID
|--------------------------------------------------------------------------
*/

if ($razorpayOrderId !== null) {

    $where[] =
        "p.razorpay_order_id = ?";

    $params[] =
        $razorpayOrderId;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Razorpay Payment ID
|--------------------------------------------------------------------------
*/

if ($razorpayPaymentId !== null) {

    $where[] =
        "p.razorpay_payment_id = ?";

    $params[] =
        $razorpayPaymentId;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== null) {

    $like =
        '%' . $search . '%';

    $where[] = "
        (
            rb.booking_reference LIKE ?
            OR c.name LIKE ?
            OR c.phone LIKE ?
            OR c.email LIKE ?
            OR p.razorpay_order_id LIKE ?
            OR p.razorpay_payment_id LIKE ?
            OR p.payment_method LIKE ?
        )
    ";

    for ($i = 0; $i < 7; $i++) {

        $params[] =
            $like;

        $types .= 's';
    }
}


$whereSql =
    !empty($where)
        ? implode(
            ' AND ',
            $where
        )
        : '1=1';


/*
|--------------------------------------------------------------------------
| SUMMARY
|--------------------------------------------------------------------------
|
| Important:
|
| captured_amount
|     = actual captured payment rows
|
| refunded_amount
|     = payment rows having refunded status
|
| net_collected
|     = captured - refunded
|
|--------------------------------------------------------------------------
*/

$summarySql = "
    SELECT

        COUNT(*) AS total_transactions,

        COUNT(
            DISTINCT p.booking_id
        ) AS total_bookings,

        COUNT(
            CASE
                WHEN p.payment_status = 'created'
                THEN 1
            END
        ) AS created_transactions,

        COUNT(
            CASE
                WHEN p.payment_status = 'authorized'
                THEN 1
            END
        ) AS authorized_transactions,

        COUNT(
            CASE
                WHEN p.payment_status = 'captured'
                THEN 1
            END
        ) AS captured_transactions,

        COUNT(
            CASE
                WHEN p.payment_status = 'failed'
                THEN 1
            END
        ) AS failed_transactions,

        COUNT(
            CASE
                WHEN p.payment_status = 'refunded'
                THEN 1
            END
        ) AS refunded_transactions,

        COALESCE(
            SUM(
                CASE
                    WHEN p.payment_status = 'captured'
                    THEN p.amount
                    ELSE 0
                END
            ),
            0
        ) AS captured_amount,

        COALESCE(
            SUM(
                CASE
                    WHEN p.payment_status = 'refunded'
                    THEN p.amount
                    ELSE 0
                END
            ),
            0
        ) AS refunded_amount,

        COALESCE(
            SUM(p.amount),
            0
        ) AS transaction_value,

        COALESCE(
            SUM(
                CASE
                    WHEN p.payment_status = 'failed'
                    THEN p.amount
                    ELSE 0
                END
            ),
            0
        ) AS failed_amount,

        COALESCE(
            SUM(
                CASE
                    WHEN p.payment_status = 'created'
                    THEN p.amount
                    ELSE 0
                END
            ),
            0
        ) AS created_amount

    FROM payments p

    INNER JOIN room_bookings rb
        ON rb.id = p.booking_id

    INNER JOIN customers c
        ON c.id = rb.customer_id

    WHERE {$whereSql}
";


$stmt = $conn->prepare(
    $summarySql
);

if (!$stmt) {

    jsonResponse(
        false,
        'Failed to prepare payment summary.',
        [],
        500
    );
}


if (!empty($params)) {

    $stmt->bind_param(
        $types,
        ...$params
    );
}


$stmt->execute();

$summary =
    $stmt->get_result()
        ->fetch_assoc();

$stmt->close();


$totalTransactions =
    (int)(
        $summary['total_transactions']
        ?? 0
    );

$totalBookings =
    (int)(
        $summary['total_bookings']
        ?? 0
    );

$createdTransactions =
    (int)(
        $summary['created_transactions']
        ?? 0
    );

$authorizedTransactions =
    (int)(
        $summary['authorized_transactions']
        ?? 0
    );

$capturedTransactions =
    (int)(
        $summary['captured_transactions']
        ?? 0
    );

$failedTransactions =
    (int)(
        $summary['failed_transactions']
        ?? 0
    );

$refundedTransactions =
    (int)(
        $summary['refunded_transactions']
        ?? 0
    );

$capturedAmount =
    (float)(
        $summary['captured_amount']
        ?? 0
    );

$refundedAmount =
    (float)(
        $summary['refunded_amount']
        ?? 0
    );

$transactionValue =
    (float)(
        $summary['transaction_value']
        ?? 0
    );

$failedAmount =
    (float)(
        $summary['failed_amount']
        ?? 0
    );

$createdAmount =
    (float)(
        $summary['created_amount']
        ?? 0
    );


$netCollected =
    $capturedAmount -
    $refundedAmount;


$successRate =
    $totalTransactions > 0
        ? round(
            (
                $capturedTransactions
                /
                $totalTransactions
            ) * 100,
            2
        )
        : 0;


/*
|--------------------------------------------------------------------------
| BOOKING VALUE / OUTSTANDING
|--------------------------------------------------------------------------
*/

$bookingSummarySql = "
    SELECT

        COALESCE(
            SUM(
                rb.total_amount
            ),
            0
        ) AS booking_value,

        COALESCE(
            SUM(
                CASE
                    WHEN rb.payment_status IN (
                        'pending',
                        'partial'
                    )
                    THEN rb.total_amount
                    ELSE 0
                END
            ),
            0
        ) AS booking_pending_value,

        COALESCE(
            SUM(
                CASE
                    WHEN rb.payment_status = 'paid'
                    THEN rb.total_amount
                    ELSE 0
                END
            ),
            0
        ) AS booking_paid_value,

        COUNT(
            CASE
                WHEN rb.payment_status = 'pending'
                THEN 1
            END
        ) AS pending_bookings,

        COUNT(
            CASE
                WHEN rb.payment_status = 'partial'
                THEN 1
            END
        ) AS partial_bookings,

        COUNT(
            CASE
                WHEN rb.payment_status = 'paid'
                THEN 1
            END
        ) AS paid_bookings,

        COUNT(
            CASE
                WHEN rb.payment_status = 'failed'
                THEN 1
            END
        ) AS failed_bookings,

        COUNT(
            CASE
                WHEN rb.payment_status = 'refunded'
                THEN 1
            END
        ) AS refunded_bookings

    FROM (
        SELECT DISTINCT
            p.booking_id
        FROM payments p

        INNER JOIN room_bookings rb
            ON rb.id = p.booking_id

        INNER JOIN customers c
            ON c.id = rb.customer_id

        WHERE {$whereSql}
    ) filtered_payments

    INNER JOIN room_bookings rb
        ON rb.id = filtered_payments.booking_id
";


$stmt = $conn->prepare(
    $bookingSummarySql
);

if (!$stmt) {

    jsonResponse(
        false,
        'Failed to prepare booking payment summary.',
        [],
        500
    );
}


if (!empty($params)) {

    $stmt->bind_param(
        $types,
        ...$params
    );
}


$stmt->execute();

$bookingSummary =
    $stmt->get_result()
        ->fetch_assoc();

$stmt->close();


$bookingValue =
    (float)(
        $bookingSummary[
            'booking_value'
        ] ?? 0
    );

$bookingPendingValue =
    (float)(
        $bookingSummary[
            'booking_pending_value'
        ] ?? 0
    );

$bookingPaidValue =
    (float)(
        $bookingSummary[
            'booking_paid_value'
        ] ?? 0
    );

$pendingBookings =
    (int)(
        $bookingSummary[
            'pending_bookings'
        ] ?? 0
    );

$partialBookings =
    (int)(
        $bookingSummary[
            'partial_bookings'
        ] ?? 0
    );

$paidBookings =
    (int)(
        $bookingSummary[
            'paid_bookings'
        ] ?? 0
    );

$failedBookings =
    (int)(
        $bookingSummary[
            'failed_bookings'
        ] ?? 0
    );

$refundedBookings =
    (int)(
        $bookingSummary[
            'refunded_bookings'
        ] ?? 0
    );


/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

$countSql = "
    SELECT COUNT(*)

    FROM payments p

    INNER JOIN room_bookings rb
        ON rb.id = p.booking_id

    INNER JOIN customers c
        ON c.id = rb.customer_id

    WHERE {$whereSql}
";


$stmt = $conn->prepare(
    $countSql
);

if (!$stmt) {

    jsonResponse(
        false,
        'Failed to prepare pagination query.',
        [],
        500
    );
}


if (!empty($params)) {

    $stmt->bind_param(
        $types,
        ...$params
    );
}


$stmt->execute();

$countResult =
    $stmt->get_result();

$countRow =
    $countResult->fetch_row();

$stmt->close();


$totalRecords =
    (int)($countRow[0] ?? 0);

$totalPages =
    $totalRecords > 0
        ? (int)ceil(
            $totalRecords / $limit
        )
        : 0;

$offset =
    ($page - 1) * $limit;


/*
|--------------------------------------------------------------------------
| PAYMENT RECORDS
|--------------------------------------------------------------------------
*/

$dataSql = "
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
        p.updated_at,

        rb.booking_reference,
        rb.customer_id,

        rb.check_in_date,
        rb.check_out_date,

        rb.total_guests,

        rb.room_subtotal,
        rb.gst_rate,
        rb.gst_amount,
        rb.total_amount,

        rb.payment_status AS booking_payment_status,
        rb.booking_status,

        rb.cancellation_reason,

        c.name AS customer_name,
        c.phone AS customer_phone,
        c.email AS customer_email

    FROM payments p

    INNER JOIN room_bookings rb
        ON rb.id = p.booking_id

    INNER JOIN customers c
        ON c.id = rb.customer_id

    WHERE {$whereSql}

    ORDER BY
        {$sortColumn} {$sortOrder}

    LIMIT ? OFFSET ?
";


$dataParams =
    $params;

$dataTypes =
    $types . 'ii';

$dataParams[] =
    $limit;

$dataParams[] =
    $offset;


$stmt = $conn->prepare(
    $dataSql
);

if (!$stmt) {

    jsonResponse(
        false,
        'Failed to prepare payment records.',
        [],
        500
    );
}


$stmt->bind_param(
    $dataTypes,
    ...$dataParams
);

$stmt->execute();

$result =
    $stmt->get_result();

$payments = [];


while (
    $row =
        $result->fetch_assoc()
) {

    $amount =
        (float)$row['amount'];

    $bookingTotal =
        (float)$row['total_amount'];


    /*
    |--------------------------------------------------------------------------
    | Payment Status Meaning
    |--------------------------------------------------------------------------
    */

    $paymentStatus =
        $row['payment_status'];


    $isSuccessful =
        $paymentStatus === 'captured';


    $isRefunded =
        $paymentStatus === 'refunded';


    $isFailed =
        $paymentStatus === 'failed';


    /*
    |--------------------------------------------------------------------------
    | Reconciliation
    |--------------------------------------------------------------------------
    */

    $bookingPaymentStatus =
        $row[
            'booking_payment_status'
        ];


    $statusMatch =
        false;


    if (
        $paymentStatus === 'captured' &&
        $bookingPaymentStatus === 'paid'
    ) {

        $statusMatch = true;
    }

    elseif (
        $paymentStatus === 'refunded' &&
        $bookingPaymentStatus === 'refunded'
    ) {

        $statusMatch = true;
    }

    elseif (
        $paymentStatus === 'failed' &&
        $bookingPaymentStatus === 'failed'
    ) {

        $statusMatch = true;
    }

    elseif (
        in_array(
            $paymentStatus,
            [
                'created',
                'authorized'
            ],
            true
        ) &&
        in_array(
            $bookingPaymentStatus,
            [
                'pending',
                'partial'
            ],
            true
        )
    ) {

        $statusMatch = true;
    }


    /*
    |--------------------------------------------------------------------------
    | Record
    |--------------------------------------------------------------------------
    */

    $payments[] = [

        'payment_id' =>
            (int)$row['id'],

        'booking' => [

            'id' =>
                (int)$row['booking_id'],

            'reference' =>
                $row[
                    'booking_reference'
                ],

            'check_in_date' =>
                $row[
                    'check_in_date'
                ],

            'check_out_date' =>
                $row[
                    'check_out_date'
                ],

            'total_guests' =>
                (int)$row[
                    'total_guests'
                ],

            'booking_status' =>
                $row[
                    'booking_status'
                ],

            'payment_status' =>
                $bookingPaymentStatus
        ],


        'customer' => [

            'id' =>
                (int)$row[
                    'customer_id'
                ],

            'name' =>
                $row[
                    'customer_name'
                ],

            'phone' =>
                $row[
                    'customer_phone'
                ],

            'email' =>
                $row[
                    'customer_email'
                ]
        ],


        'payment' => [

            'gateway' =>
                $row[
                    'payment_gateway'
                ],

            'amount' =>
                round(
                    $amount,
                    2
                ),

            'currency' =>
                $row[
                    'currency'
                ],

            'status' =>
                $paymentStatus,

            'method' =>
                $row[
                    'payment_method'
                ],

            'is_successful' =>
                $isSuccessful,

            'is_refunded' =>
                $isRefunded,

            'is_failed' =>
                $isFailed,

            'paid_at' =>
                $row[
                    'paid_at'
                ],

            'created_at' =>
                $row[
                    'created_at'
                ],

            'updated_at' =>
                $row[
                    'updated_at'
                ]
        ],


        'razorpay' => [

            'order_id' =>
                $row[
                    'razorpay_order_id'
                ],

            'payment_id' =>
                $row[
                    'razorpay_payment_id'
                ],

            'signature_available' =>
                !empty(
                    $row[
                        'razorpay_signature'
                    ]
                )
        ],


        'booking_amount' => [

            'room_subtotal' =>
                (float)$row[
                    'room_subtotal'
                ],

            'gst_rate' =>
                (float)$row[
                    'gst_rate'
                ],

            'gst_amount' =>
                (float)$row[
                    'gst_amount'
                ],

            'total_amount' =>
                $bookingTotal
        ],


        'reconciliation' => [

            'booking_payment_status' =>
                $bookingPaymentStatus,

            'transaction_payment_status' =>
                $paymentStatus,

            'status_match' =>
                $statusMatch
        ]
    ];
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| STATUS SUMMARY
|--------------------------------------------------------------------------
*/

$statusSql = "
    SELECT

        p.payment_status,

        COUNT(*) AS total_transactions,

        COALESCE(
            SUM(p.amount),
            0
        ) AS total_amount

    FROM payments p

    INNER JOIN room_bookings rb
        ON rb.id = p.booking_id

    INNER JOIN customers c
        ON c.id = rb.customer_id

    WHERE {$whereSql}

    GROUP BY p.payment_status

    ORDER BY FIELD(
        p.payment_status,
        'created',
        'authorized',
        'captured',
        'failed',
        'refunded'
    )
";


$stmt = $conn->prepare(
    $statusSql
);

if (!$stmt) {

    jsonResponse(
        false,
        'Failed to prepare payment status summary.',
        [],
        500
    );
}


if (!empty($params)) {

    $stmt->bind_param(
        $types,
        ...$params
    );
}


$stmt->execute();

$statusResult =
    $stmt->get_result();

$statusSummary = [];


while (
    $row =
        $statusResult->fetch_assoc()
) {

    $statusSummary[] = [

        'status' =>
            $row[
                'payment_status'
            ],

        'total_transactions' =>
            (int)$row[
                'total_transactions'
            ],

        'total_amount' =>
            (float)$row[
                'total_amount'
            ]
    ];
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| PAYMENT METHOD SUMMARY
|--------------------------------------------------------------------------
*/

$methodSql = "
    SELECT

        COALESCE(
            NULLIF(
                p.payment_method,
                ''
            ),
            'unknown'
        ) AS payment_method,

        COUNT(*) AS total_transactions,

        COALESCE(
            SUM(p.amount),
            0
        ) AS total_amount,

        COALESCE(
            SUM(
                CASE
                    WHEN p.payment_status = 'captured'
                    THEN p.amount
                    ELSE 0
                END
            ),
            0
        ) AS captured_amount

    FROM payments p

    INNER JOIN room_bookings rb
        ON rb.id = p.booking_id

    INNER JOIN customers c
        ON c.id = rb.customer_id

    WHERE {$whereSql}

    GROUP BY
        COALESCE(
            NULLIF(
                p.payment_method,
                ''
            ),
            'unknown'
        )

    ORDER BY
        total_amount DESC
";


$stmt = $conn->prepare(
    $methodSql
);

if (!$stmt) {

    jsonResponse(
        false,
        'Failed to prepare payment method summary.',
        [],
        500
    );
}


if (!empty($params)) {

    $stmt->bind_param(
        $types,
        ...$params
    );
}


$stmt->execute();

$methodResult =
    $stmt->get_result();

$methodSummary = [];


while (
    $row =
        $methodResult->fetch_assoc()
) {

    $methodSummary[] = [

        'payment_method' =>
            $row[
                'payment_method'
            ],

        'total_transactions' =>
            (int)$row[
                'total_transactions'
            ],

        'total_amount' =>
            (float)$row[
                'total_amount'
            ],

        'captured_amount' =>
            (float)$row[
                'captured_amount'
            ]
    ];
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| DAILY PAYMENT SUMMARY
|--------------------------------------------------------------------------
*/

$dailySql = "
    SELECT

        DATE(p.created_at) AS payment_date,

        COUNT(*) AS total_transactions,

        COUNT(
            CASE
                WHEN p.payment_status = 'captured'
                THEN 1
            END
        ) AS captured_transactions,

        COUNT(
            CASE
                WHEN p.payment_status = 'failed'
                THEN 1
            END
        ) AS failed_transactions,

        COALESCE(
            SUM(
                CASE
                    WHEN p.payment_status = 'captured'
                    THEN p.amount
                    ELSE 0
                END
            ),
            0
        ) AS captured_amount,

        COALESCE(
            SUM(
                CASE
                    WHEN p.payment_status = 'refunded'
                    THEN p.amount
                    ELSE 0
                END
            ),
            0
        ) AS refunded_amount

    FROM payments p

    INNER JOIN room_bookings rb
        ON rb.id = p.booking_id

    INNER JOIN customers c
        ON c.id = rb.customer_id

    WHERE {$whereSql}

    GROUP BY
        DATE(p.created_at)

    ORDER BY
        payment_date ASC
";


$stmt = $conn->prepare(
    $dailySql
);

if (!$stmt) {

    jsonResponse(
        false,
        'Failed to prepare daily payment report.',
        [],
        500
    );
}


if (!empty($params)) {

    $stmt->bind_param(
        $types,
        ...$params
    );
}


$stmt->execute();

$dailyResult =
    $stmt->get_result();

$dailyReport = [];


while (
    $row =
        $dailyResult->fetch_assoc()
) {

    $captured =
        (float)$row[
            'captured_amount'
        ];

    $refunded =
        (float)$row[
            'refunded_amount'
        ];

    $dailyReport[] = [

        'date' =>
            $row[
                'payment_date'
            ],

        'total_transactions' =>
            (int)$row[
                'total_transactions'
            ],

        'captured_transactions' =>
            (int)$row[
                'captured_transactions'
            ],

        'failed_transactions' =>
            (int)$row[
                'failed_transactions'
            ],

        'captured_amount' =>
            $captured,

        'refunded_amount' =>
            $refunded,

        'net_amount' =>
            $captured - $refunded
    ];
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| MONTHLY PAYMENT SUMMARY
|--------------------------------------------------------------------------
*/

$monthlySql = "
    SELECT

        DATE_FORMAT(
            p.created_at,
            '%Y-%m'
        ) AS month,

        COUNT(*) AS total_transactions,

        COUNT(
            CASE
                WHEN p.payment_status = 'captured'
                THEN 1
            END
        ) AS captured_transactions,

        COUNT(
            CASE
                WHEN p.payment_status = 'failed'
                THEN 1
            END
        ) AS failed_transactions,

        COUNT(
            CASE
                WHEN p.payment_status = 'refunded'
                THEN 1
            END
        ) AS refunded_transactions,

        COALESCE(
            SUM(
                CASE
                    WHEN p.payment_status = 'captured'
                    THEN p.amount
                    ELSE 0
                END
            ),
            0
        ) AS captured_amount,

        COALESCE(
            SUM(
                CASE
                    WHEN p.payment_status = 'refunded'
                    THEN p.amount
                    ELSE 0
                END
            ),
            0
        ) AS refunded_amount

    FROM payments p

    INNER JOIN room_bookings rb
        ON rb.id = p.booking_id

    INNER JOIN customers c
        ON c.id = rb.customer_id

    WHERE {$whereSql}

    GROUP BY
        DATE_FORMAT(
            p.created_at,
            '%Y-%m'
        )

    ORDER BY
        month ASC
";


$stmt = $conn->prepare(
    $monthlySql
);

if (!$stmt) {

    jsonResponse(
        false,
        'Failed to prepare monthly payment report.',
        [],
        500
    );
}


if (!empty($params)) {

    $stmt->bind_param(
        $types,
        ...$params
    );
}


$stmt->execute();

$monthlyResult =
    $stmt->get_result();

$monthlyReport = [];


while (
    $row =
        $monthlyResult->fetch_assoc()
) {

    $captured =
        (float)$row[
            'captured_amount'
        ];

    $refunded =
        (float)$row[
            'refunded_amount'
        ];

    $monthlyReport[] = [

        'month' =>
            $row[
                'month'
            ],

        'total_transactions' =>
            (int)$row[
                'total_transactions'
            ],

        'captured_transactions' =>
            (int)$row[
                'captured_transactions'
            ],

        'failed_transactions' =>
            (int)$row[
                'failed_transactions'
            ],

        'refunded_transactions' =>
            (int)$row[
                'refunded_transactions'
            ],

        'captured_amount' =>
            $captured,

        'refunded_amount' =>
            $refunded,

        'net_amount' =>
            $captured - $refunded
    ];
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| BOOKING PAYMENT STATUS SUMMARY
|--------------------------------------------------------------------------
*/

$bookingStatusSql = "
    SELECT

        rb.payment_status,

        COUNT(
            DISTINCT rb.id
        ) AS total_bookings,

        COALESCE(
            SUM(
                DISTINCT rb.total_amount
            ),
            0
        ) AS total_booking_value

    FROM payments p

    INNER JOIN room_bookings rb
        ON rb.id = p.booking_id

    INNER JOIN customers c
        ON c.id = rb.customer_id

    WHERE {$whereSql}

    GROUP BY
        rb.payment_status

    ORDER BY FIELD(
        rb.payment_status,
        'pending',
        'partial',
        'paid',
        'failed',
        'refunded'
    )
";


/*
|--------------------------------------------------------------------------
| NOTE
|--------------------------------------------------------------------------
| We intentionally do NOT use this query's SUM(DISTINCT total_amount)
| as a financial source because two bookings can have the same total.
| We use a derived-table aggregation below.
|--------------------------------------------------------------------------
*/

$bookingStatusSql = "
    SELECT

        filtered.booking_payment_status,

        COUNT(*) AS total_bookings,

        COALESCE(
            SUM(
                filtered.total_amount
            ),
            0
        ) AS total_booking_value

    FROM (

        SELECT DISTINCT

            rb.id AS booking_id,

            rb.payment_status
                AS booking_payment_status,

            rb.total_amount

        FROM payments p

        INNER JOIN room_bookings rb
            ON rb.id = p.booking_id

        INNER JOIN customers c
            ON c.id = rb.customer_id

        WHERE {$whereSql}

    ) filtered

    GROUP BY
        filtered.booking_payment_status

    ORDER BY FIELD(
        filtered.booking_payment_status,
        'pending',
        'partial',
        'paid',
        'failed',
        'refunded'
    )
";


$stmt = $conn->prepare(
    $bookingStatusSql
);

if (!$stmt) {

    jsonResponse(
        false,
        'Failed to prepare booking payment status report.',
        [],
        500
    );
}


if (!empty($params)) {

    $stmt->bind_param(
        $types,
        ...$params
    );
}


$stmt->execute();

$bookingStatusResult =
    $stmt->get_result();

$bookingStatusSummary = [];


while (
    $row =
        $bookingStatusResult->fetch_assoc()
) {

    $bookingStatusSummary[] = [

        'booking_payment_status' =>
            $row[
                'booking_payment_status'
            ],

        'total_bookings' =>
            (int)$row[
                'total_bookings'
            ],

        'total_booking_value' =>
            (float)$row[
                'total_booking_value'
            ]
    ];
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| RECONCILIATION SUMMARY
|--------------------------------------------------------------------------
*/

$reconciliationSql = "
    SELECT

        COUNT(*) AS total_transactions,

        SUM(
            CASE

                WHEN
                    p.payment_status = 'captured'
                    AND rb.payment_status = 'paid'

                THEN 1

                WHEN
                    p.payment_status = 'refunded'
                    AND rb.payment_status = 'refunded'

                THEN 1

                WHEN
                    p.payment_status = 'failed'
                    AND rb.payment_status = 'failed'

                THEN 1

                WHEN
                    p.payment_status IN (
                        'created',
                        'authorized'
                    )

                    AND rb.payment_status IN (
                        'pending',
                        'partial'
                    )

                THEN 1

                ELSE 0

            END
        ) AS matched_transactions,

        SUM(
            CASE

                WHEN
                    p.payment_status = 'captured'
                    AND rb.payment_status = 'paid'

                THEN 0

                WHEN
                    p.payment_status = 'refunded'
                    AND rb.payment_status = 'refunded'

                THEN 0

                WHEN
                    p.payment_status = 'failed'
                    AND rb.payment_status = 'failed'

                THEN 0

                WHEN
                    p.payment_status IN (
                        'created',
                        'authorized'
                    )

                    AND rb.payment_status IN (
                        'pending',
                        'partial'
                    )

                THEN 0

                ELSE 1

            END
        ) AS mismatch_transactions

    FROM payments p

    INNER JOIN room_bookings rb
        ON rb.id = p.booking_id

    INNER JOIN customers c
        ON c.id = rb.customer_id

    WHERE {$whereSql}
";


$stmt = $conn->prepare(
    $reconciliationSql
);

if (!$stmt) {

    jsonResponse(
        false,
        'Failed to prepare reconciliation summary.',
        [],
        500
    );
}


if (!empty($params)) {

    $stmt->bind_param(
        $types,
        ...$params
    );
}


$stmt->execute();

$reconciliation =
    $stmt->get_result()
        ->fetch_assoc();

$stmt->close();


$reconciliationTotal =
    (int)(
        $reconciliation[
            'total_transactions'
        ] ?? 0
    );

$matchedTransactions =
    (int)(
        $reconciliation[
            'matched_transactions'
        ] ?? 0
    );

$mismatchTransactions =
    (int)(
        $reconciliation[
            'mismatch_transactions'
        ] ?? 0
    );

$reconciliationRate =
    $reconciliationTotal > 0
        ? round(
            (
                $matchedTransactions
                /
                $reconciliationTotal
            ) * 100,
            2
        )
        : 0;


/*
|--------------------------------------------------------------------------
| CHART DATA
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Payment Status Chart
|--------------------------------------------------------------------------
*/

$paymentStatusLabels = [];
$paymentStatusCounts = [];
$paymentStatusAmounts = [];

foreach (
    $statusSummary as $item
) {

    $paymentStatusLabels[] =
        ucfirst(
            $item['status']
        );

    $paymentStatusCounts[] =
        $item[
            'total_transactions'
        ];

    $paymentStatusAmounts[] =
        $item[
            'total_amount'
        ];
}


/*
|--------------------------------------------------------------------------
| Payment Method Chart
|--------------------------------------------------------------------------
*/

$paymentMethodLabels = [];
$paymentMethodCounts = [];
$paymentMethodCaptured = [];

foreach (
    $methodSummary as $item
) {

    $paymentMethodLabels[] =
        ucfirst(
            $item[
                'payment_method'
            ]
        );

    $paymentMethodCounts[] =
        $item[
            'total_transactions'
        ];

    $paymentMethodCaptured[] =
        $item[
            'captured_amount'
        ];
}


/*
|--------------------------------------------------------------------------
| Daily Chart
|--------------------------------------------------------------------------
*/

$dailyLabels = [];
$dailyCaptured = [];
$dailyRefunded = [];
$dailyNet = [];

foreach (
    $dailyReport as $item
) {

    $dailyLabels[] =
        $item['date'];

    $dailyCaptured[] =
        $item[
            'captured_amount'
        ];

    $dailyRefunded[] =
        $item[
            'refunded_amount'
        ];

    $dailyNet[] =
        $item[
            'net_amount'
        ];
}


/*
|--------------------------------------------------------------------------
| Monthly Chart
|--------------------------------------------------------------------------
*/

$monthlyLabels = [];
$monthlyCaptured = [];
$monthlyRefunded = [];
$monthlyNet = [];

foreach (
    $monthlyReport as $item
) {

    $monthlyLabels[] =
        $item['month'];

    $monthlyCaptured[] =
        $item[
            'captured_amount'
        ];

    $monthlyRefunded[] =
        $item[
            'refunded_amount'
        ];

    $monthlyNet[] =
        $item[
            'net_amount'
        ];
}


/*
|--------------------------------------------------------------------------
| Booking Payment Status Chart
|--------------------------------------------------------------------------
*/

$bookingStatusLabels = [];
$bookingStatusCounts = [];
$bookingStatusAmounts = [];

foreach (
    $bookingStatusSummary as $item
) {

    $bookingStatusLabels[] =
        ucfirst(
            $item[
                'booking_payment_status'
            ]
        );

    $bookingStatusCounts[] =
        $item[
            'total_bookings'
        ];

    $bookingStatusAmounts[] =
        $item[
            'total_booking_value'
        ];
}


/*
|--------------------------------------------------------------------------
| FINAL RESPONSE
|--------------------------------------------------------------------------
*/

jsonResponse(
    true,
    'Payment report fetched successfully.',
    [

        /*
        |--------------------------------------------------------------------------
        | META
        |--------------------------------------------------------------------------
        */

        'meta' => [

            'report' =>
                'payment_report',

            'generated_at' =>
                date(
                    'Y-m-d H:i:s'
                ),

            'currency' =>
                'INR',

            'payment_gateway' =>
                'razorpay',

            'payment_statuses' =>
                [
                    'created',
                    'authorized',
                    'captured',
                    'failed',
                    'refunded'
                ],

            'booking_payment_statuses' =>
                [
                    'pending',
                    'partial',
                    'paid',
                    'failed',
                    'refunded'
                ]
        ],


        /*
        |--------------------------------------------------------------------------
        | USER SCOPE
        |--------------------------------------------------------------------------
        */

        'user_scope' => [

            'role' =>
                $currentUserRole,

            'user_id' =>
                $currentUserId,

            'scope' =>
                'room booking payments'
        ],


        /*
        |--------------------------------------------------------------------------
        | FILTERS
        |--------------------------------------------------------------------------
        */

        'filters' => [

            'payment_id' =>
                $paymentId > 0
                    ? $paymentId
                    : null,

            'booking_id' =>
                $bookingId > 0
                    ? $bookingId
                    : null,

            'customer_id' =>
                $customerId > 0
                    ? $customerId
                    : null,

            'payment_status' =>
                $paymentStatus,

            'booking_status' =>
                $bookingStatus,

            'payment_method' =>
                $paymentMethod,

            'currency' =>
                $currency,

            'search' =>
                $search,

            'razorpay_order_id' =>
                $razorpayOrderId,

            'razorpay_payment_id' =>
                $razorpayPaymentId,

            'from_date' =>
                $fromDate,

            'to_date' =>
                $toDate,

            'paid_from_date' =>
                $paidFromDate,

            'paid_to_date' =>
                $paidToDate,

            'min_amount' =>
                $minAmount,

            'max_amount' =>
                $maxAmount,

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
        | PAYMENT KPIs
        |--------------------------------------------------------------------------
        */

        'kpis' => [

            'total_transactions' =>
                $totalTransactions,

            'total_bookings' =>
                $totalBookings,

            'created_transactions' =>
                $createdTransactions,

            'authorized_transactions' =>
                $authorizedTransactions,

            'captured_transactions' =>
                $capturedTransactions,

            'failed_transactions' =>
                $failedTransactions,

            'refunded_transactions' =>
                $refundedTransactions,

            'transaction_value' =>
                round(
                    $transactionValue,
                    2
                ),

            'captured_amount' =>
                round(
                    $capturedAmount,
                    2
                ),

            'refunded_amount' =>
                round(
                    $refundedAmount,
                    2
                ),

            'net_collected' =>
                round(
                    $netCollected,
                    2
                ),

            'failed_amount' =>
                round(
                    $failedAmount,
                    2
                ),

            'created_amount' =>
                round(
                    $createdAmount,
                    2
                ),

            'success_rate' =>
                $successRate
        ],


        /*
        |--------------------------------------------------------------------------
        | BOOKING PAYMENT KPIs
        |--------------------------------------------------------------------------
        */

        'booking_payment_summary' => [

            'booking_value' =>
                round(
                    $bookingValue,
                    2
                ),

            'booking_paid_value' =>
                round(
                    $bookingPaidValue,
                    2
                ),

            'booking_pending_value' =>
                round(
                    $bookingPendingValue,
                    2
                ),

            'pending_bookings' =>
                $pendingBookings,

            'partial_bookings' =>
                $partialBookings,

            'paid_bookings' =>
                $paidBookings,

            'failed_bookings' =>
                $failedBookings,

            'refunded_bookings' =>
                $refundedBookings
        ],


        /*
        |--------------------------------------------------------------------------
        | RECONCILIATION
        |--------------------------------------------------------------------------
        */

        'reconciliation' => [

            'total_transactions' =>
                $reconciliationTotal,

            'matched_transactions' =>
                $matchedTransactions,

            'mismatch_transactions' =>
                $mismatchTransactions,

            'match_rate' =>
                $reconciliationRate
        ],


        /*
        |--------------------------------------------------------------------------
        | STATUS SUMMARY
        |--------------------------------------------------------------------------
        */

        'payment_status_summary' =>
            $statusSummary,


        /*
        |--------------------------------------------------------------------------
        | METHOD SUMMARY
        |--------------------------------------------------------------------------
        */

        'payment_method_summary' =>
            $methodSummary,


        /*
        |--------------------------------------------------------------------------
        | BOOKING PAYMENT STATUS
        |--------------------------------------------------------------------------
        */

        'booking_payment_status_summary' =>
            $bookingStatusSummary,


        /*
        |--------------------------------------------------------------------------
        | DAILY REPORT
        |--------------------------------------------------------------------------
        */

        'daily_report' =>
            $dailyReport,


        /*
        |--------------------------------------------------------------------------
        | MONTHLY REPORT
        |--------------------------------------------------------------------------
        */

        'monthly_report' =>
            $monthlyReport,


        /*
        |--------------------------------------------------------------------------
        | PAYMENT RECORDS
        |--------------------------------------------------------------------------
        */

        'records' =>
            $payments,


        /*
        |--------------------------------------------------------------------------
        | PAGINATION
        |--------------------------------------------------------------------------
        */

        'pagination' => [

            'current_page' =>
                $page,

            'per_page' =>
                $limit,

            'total_records' =>
                $totalRecords,

            'total_pages' =>
                $totalPages,

            'has_next_page' =>
                $page < $totalPages,

            'has_previous_page' =>
                $page > 1 &&
                $totalPages > 0
        ],


        /*
        |--------------------------------------------------------------------------
        | CHART.JS DATA
        |--------------------------------------------------------------------------
        */

        'charts' => [

            /*
            |--------------------------------------------------------------
            | Payment Status
            |--------------------------------------------------------------
            */

            'payment_status' => [

                'labels' =>
                    $paymentStatusLabels,

                'datasets' => [

                    [
                        'label' =>
                            'Transactions',

                        'data' =>
                            $paymentStatusCounts
                    ],

                    [
                        'label' =>
                            'Amount',

                        'data' =>
                            $paymentStatusAmounts
                    ]
                ]
            ],


            /*
            |--------------------------------------------------------------
            | Payment Method
            |--------------------------------------------------------------
            */

            'payment_method' => [

                'labels' =>
                    $paymentMethodLabels,

                'datasets' => [

                    [
                        'label' =>
                            'Transactions',

                        'data' =>
                            $paymentMethodCounts
                    ],

                    [
                        'label' =>
                            'Captured Amount',

                        'data' =>
                            $paymentMethodCaptured
                    ]
                ]
            ],


            /*
            |--------------------------------------------------------------
            | Daily Revenue
            |--------------------------------------------------------------
            */

            'daily_revenue' => [

                'labels' =>
                    $dailyLabels,

                'datasets' => [

                    [
                        'label' =>
                            'Captured',

                        'data' =>
                            $dailyCaptured
                    ],

                    [
                        'label' =>
                            'Refunded',

                        'data' =>
                            $dailyRefunded
                    ],

                    [
                        'label' =>
                            'Net',

                        'data' =>
                            $dailyNet
                    ]
                ]
            ],


            /*
            |--------------------------------------------------------------
            | Monthly Revenue
            |--------------------------------------------------------------
            */

            'monthly_revenue' => [

                'labels' =>
                    $monthlyLabels,

                'datasets' => [

                    [
                        'label' =>
                            'Captured',

                        'data' =>
                            $monthlyCaptured
                    ],

                    [
                        'label' =>
                            'Refunded',

                        'data' =>
                            $monthlyRefunded
                    ],

                    [
                        'label' =>
                            'Net',

                        'data' =>
                            $monthlyNet
                    ]
                ]
            ],


            /*
            |--------------------------------------------------------------
            | Booking Payment Status
            |--------------------------------------------------------------
            */

            'booking_payment_status' => [

                'labels' =>
                    $bookingStatusLabels,

                'datasets' => [

                    [
                        'label' =>
                            'Bookings',

                        'data' =>
                            $bookingStatusCounts
                    ],

                    [
                        'label' =>
                            'Booking Value',

                        'data' =>
                            $bookingStatusAmounts
                    ]
                ]
            ]
        ]
    ]
);