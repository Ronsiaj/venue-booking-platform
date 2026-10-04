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
| Helper
|--------------------------------------------------------------------------
*/

function paymentHistoryError($message, $code = 400)
{
    http_response_code($code);

    echo json_encode([
        'status' => false,
        'message' => $message
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
|
| Payment history is customer-specific.
|
*/

$user = requireCustomer();

$customerId = (int) $user['sub'];

if ($customerId <= 0) {
    paymentHistoryError(
        'Invalid customer information.',
        401
    );
}


/*
|--------------------------------------------------------------------------
| Allowed Query Parameters
|--------------------------------------------------------------------------
*/

$allowedParams = [
    'page',
    'limit',
    'booking_id',
    'booking_reference',
    'razorpay_order_id',
    'razorpay_payment_id',
    'payment_status',
    'payment_method',
    'start_date',
    'end_date',
    'min_amount',
    'max_amount',
    'sort_by',
    'sort_order'
];

foreach ($_GET as $key => $value) {

    if (!in_array($key, $allowedParams, true)) {

        paymentHistoryError(
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
    !is_string($page) ||
    !preg_match('/^\d+$/', $page) ||
    (int) $page < 1
) {
    paymentHistoryError(
        'Page must be a positive integer.'
    );
}

if (
    !is_string($limit) ||
    !preg_match('/^\d+$/', $limit) ||
    (int) $limit < 1 ||
    (int) $limit > 100
) {
    paymentHistoryError(
        'Limit must be between 1 and 100.'
    );
}

$page = (int) $page;
$limit = (int) $limit;

$offset = ($page - 1) * $limit;


/*
|--------------------------------------------------------------------------
| Booking ID
|--------------------------------------------------------------------------
*/

$bookingId = null;

if (isset($_GET['booking_id'])) {

    $value = $_GET['booking_id'];

    if (
        !is_string($value) ||
        !preg_match('/^\d+$/', $value)
    ) {
        paymentHistoryError(
            'booking_id must be a positive integer.'
        );
    }

    $bookingId = (int) $value;

    if ($bookingId <= 0) {
        paymentHistoryError(
            'booking_id must be greater than zero.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Booking Reference
|--------------------------------------------------------------------------
*/

$bookingReference = null;

if (isset($_GET['booking_reference'])) {

    $bookingReference =
        trim(
            (string) $_GET['booking_reference']
        );

    if ($bookingReference === '') {
        paymentHistoryError(
            'booking_reference cannot be empty.'
        );
    }

    if (strlen($bookingReference) > 50) {
        paymentHistoryError(
            'booking_reference cannot exceed 50 characters.'
        );
    }

    if (
        !preg_match(
            '/^[A-Za-z0-9._-]+$/',
            $bookingReference
        )
    ) {
        paymentHistoryError(
            'Invalid booking_reference format.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Razorpay Order ID
|--------------------------------------------------------------------------
*/

$razorpayOrderId = null;

if (isset($_GET['razorpay_order_id'])) {

    $razorpayOrderId =
        trim(
            (string) $_GET['razorpay_order_id']
        );

    if ($razorpayOrderId === '') {
        paymentHistoryError(
            'razorpay_order_id cannot be empty.'
        );
    }

    if (strlen($razorpayOrderId) > 100) {
        paymentHistoryError(
            'razorpay_order_id cannot exceed 100 characters.'
        );
    }

    if (
        !preg_match(
            '/^[A-Za-z0-9_-]+$/',
            $razorpayOrderId
        )
    ) {
        paymentHistoryError(
            'Invalid razorpay_order_id format.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Razorpay Payment ID
|--------------------------------------------------------------------------
*/

$razorpayPaymentId = null;

if (isset($_GET['razorpay_payment_id'])) {

    $razorpayPaymentId =
        trim(
            (string) $_GET['razorpay_payment_id']
        );

    if ($razorpayPaymentId === '') {
        paymentHistoryError(
            'razorpay_payment_id cannot be empty.'
        );
    }

    if (strlen($razorpayPaymentId) > 100) {
        paymentHistoryError(
            'razorpay_payment_id cannot exceed 100 characters.'
        );
    }

    if (
        !preg_match(
            '/^[A-Za-z0-9_-]+$/',
            $razorpayPaymentId
        )
    ) {
        paymentHistoryError(
            'Invalid razorpay_payment_id format.'
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

    $paymentStatus =
        trim(
            (string) $_GET['payment_status']
        );

    if (
        !in_array(
            $paymentStatus,
            $allowedPaymentStatuses,
            true
        )
    ) {
        paymentHistoryError(
            'Invalid payment_status.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Payment Method
|--------------------------------------------------------------------------
|
| Razorpay payment methods can vary.
| Keep this filter reasonably restricted.
|
*/

$paymentMethod = null;

if (isset($_GET['payment_method'])) {

    $paymentMethod =
        trim(
            (string) $_GET['payment_method']
        );

    if ($paymentMethod === '') {
        paymentHistoryError(
            'payment_method cannot be empty.'
        );
    }

    if (strlen($paymentMethod) > 50) {
        paymentHistoryError(
            'payment_method cannot exceed 50 characters.'
        );
    }

    if (
        !preg_match(
            '/^[A-Za-z0-9_-]+$/',
            $paymentMethod
        )
    ) {
        paymentHistoryError(
            'Invalid payment_method format.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Date Validation Helper
|--------------------------------------------------------------------------
*/

function isValidHistoryDate($date)
{
    if (
        !is_string($date) ||
        !preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $date
        )
    ) {
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
| Start Date
|--------------------------------------------------------------------------
*/

$startDate = null;

if (isset($_GET['start_date'])) {

    $startDate =
        trim(
            (string) $_GET['start_date']
        );

    if (!isValidHistoryDate($startDate)) {
        paymentHistoryError(
            'start_date must be a valid date in YYYY-MM-DD format.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| End Date
|--------------------------------------------------------------------------
*/

$endDate = null;

if (isset($_GET['end_date'])) {

    $endDate =
        trim(
            (string) $_GET['end_date']
        );

    if (!isValidHistoryDate($endDate)) {
        paymentHistoryError(
            'end_date must be a valid date in YYYY-MM-DD format.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Date Range Validation
|--------------------------------------------------------------------------
*/

if (
    $startDate !== null &&
    $endDate !== null &&
    $startDate > $endDate
) {
    paymentHistoryError(
        'start_date cannot be greater than end_date.'
    );
}


/*
|--------------------------------------------------------------------------
| Minimum Amount
|--------------------------------------------------------------------------
*/

$minAmount = null;

if (isset($_GET['min_amount'])) {

    $value =
        trim(
            (string) $_GET['min_amount']
        );

    if (
        $value === '' ||
        !preg_match(
            '/^\d+(?:\.\d{1,2})?$/',
            $value
        )
    ) {
        paymentHistoryError(
            'min_amount must be a valid amount with maximum 2 decimal places.'
        );
    }

    $minAmount = (float) $value;

    if (!is_finite($minAmount)) {
        paymentHistoryError(
            'Invalid min_amount.'
        );
    }

    if ($minAmount < 0) {
        paymentHistoryError(
            'min_amount cannot be negative.'
        );
    }

    if ($minAmount > 100000000) {
        paymentHistoryError(
            'min_amount is too large.'
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

    $value =
        trim(
            (string) $_GET['max_amount']
        );

    if (
        $value === '' ||
        !preg_match(
            '/^\d+(?:\.\d{1,2})?$/',
            $value
        )
    ) {
        paymentHistoryError(
            'max_amount must be a valid amount with maximum 2 decimal places.'
        );
    }

    $maxAmount = (float) $value;

    if (!is_finite($maxAmount)) {
        paymentHistoryError(
            'Invalid max_amount.'
        );
    }

    if ($maxAmount < 0) {
        paymentHistoryError(
            'max_amount cannot be negative.'
        );
    }

    if ($maxAmount > 100000000) {
        paymentHistoryError(
            'max_amount is too large.'
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
    paymentHistoryError(
        'min_amount cannot be greater than max_amount.'
    );
}


/*
|--------------------------------------------------------------------------
| Sorting
|--------------------------------------------------------------------------
*/

$allowedSortFields = [
    'id' => 'p.id',
    'amount' => 'p.amount',
    'payment_status' => 'p.payment_status',
    'payment_method' => 'p.payment_method',
    'paid_at' => 'p.paid_at',
    'created_at' => 'p.created_at',
    'updated_at' => 'p.updated_at'
];

$sortBy =
    $_GET['sort_by'] ?? 'created_at';

if (
    !is_string($sortBy) ||
    !isset($allowedSortFields[$sortBy])
) {
    paymentHistoryError(
        'Invalid sort_by value.'
    );
}

$sortOrder =
    strtoupper(
        (string)
        ($_GET['sort_order'] ?? 'DESC')
    );

if (
    !in_array(
        $sortOrder,
        ['ASC', 'DESC'],
        true
    )
) {
    paymentHistoryError(
        'sort_order must be ASC or DESC.'
    );
}

$orderBy =
    $allowedSortFields[$sortBy];


/*
|--------------------------------------------------------------------------
| Build WHERE
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
| Very important:
| Customer ID comes only from JWT.
|
*/

$where[] =
    'rb.customer_id = ?';

$params[] =
    $customerId;

$types .= 'i';


/*
|--------------------------------------------------------------------------
| Booking ID
|--------------------------------------------------------------------------
*/

if ($bookingId !== null) {

    $where[] =
        'p.booking_id = ?';

    $params[] =
        $bookingId;

    $types .= 'i';
}


/*
|--------------------------------------------------------------------------
| Booking Reference
|--------------------------------------------------------------------------
*/

if ($bookingReference !== null) {

    $where[] =
        'rb.booking_reference = ?';

    $params[] =
        $bookingReference;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Razorpay Order ID
|--------------------------------------------------------------------------
*/

if ($razorpayOrderId !== null) {

    $where[] =
        'p.razorpay_order_id = ?';

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
        'p.razorpay_payment_id = ?';

    $params[] =
        $razorpayPaymentId;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Payment Status
|--------------------------------------------------------------------------
*/

if ($paymentStatus !== null) {

    $where[] =
        'p.payment_status = ?';

    $params[] =
        $paymentStatus;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Payment Method
|--------------------------------------------------------------------------
*/

if ($paymentMethod !== null) {

    $where[] =
        'p.payment_method = ?';

    $params[] =
        $paymentMethod;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Start Date
|--------------------------------------------------------------------------
*/

if ($startDate !== null) {

    $where[] =
        'DATE(p.created_at) >= ?';

    $params[] =
        $startDate;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| End Date
|--------------------------------------------------------------------------
*/

if ($endDate !== null) {

    $where[] =
        'DATE(p.created_at) <= ?';

    $params[] =
        $endDate;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Minimum Amount
|--------------------------------------------------------------------------
*/

if ($minAmount !== null) {

    $where[] =
        'p.amount >= ?';

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
        'p.amount <= ?';

    $params[] =
        $maxAmount;

    $types .= 'd';
}


$whereSql =
    'WHERE ' .
    implode(
        ' AND ',
        $where
    );


/*
|--------------------------------------------------------------------------
| Count Total Records
|--------------------------------------------------------------------------
*/

$countSql = "
    SELECT
        COUNT(*) AS total

    FROM payments p

    INNER JOIN room_bookings rb
        ON rb.id = p.booking_id

    {$whereSql}
";

$countStmt =
    $conn->prepare(
        $countSql
    );

if (!$countStmt) {
    paymentHistoryError(
        'Failed to prepare payment history count query.',
        500
    );
}

$countStmt->bind_param(
    $types,
    ...$params
);

if (!$countStmt->execute()) {

    $countStmt->close();

    paymentHistoryError(
        'Failed to count payment history.',
        500
    );
}

$countResult =
    $countStmt->get_result();

$countRow =
    $countResult->fetch_assoc();

$countStmt->close();

$totalRecords =
    (int) (
        $countRow['total'] ?? 0
    );

$totalPages =
    $totalRecords > 0
        ? (int) ceil(
            $totalRecords / $limit
        )
        : 0;


/*
|--------------------------------------------------------------------------
| Fetch Payment History
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT

        p.id,
        p.booking_id,
        p.payment_gateway,
        p.razorpay_order_id,
        p.razorpay_payment_id,
        p.amount,
        p.currency,
        p.payment_status,
        p.payment_method,
        p.paid_at,
        p.created_at,
        p.updated_at,

        rb.booking_reference,
        rb.check_in_date,
        rb.check_out_date,
        rb.total_guests,
        rb.total_amount AS booking_total_amount,
        rb.payment_status AS booking_payment_status,
        rb.booking_status

    FROM payments p

    INNER JOIN room_bookings rb
        ON rb.id = p.booking_id

    {$whereSql}

    ORDER BY {$orderBy} {$sortOrder}

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


$stmt =
    $conn->prepare(
        $sql
    );

if (!$stmt) {
    paymentHistoryError(
        'Failed to prepare payment history query.',
        500
    );
}

$stmt->bind_param(
    $dataTypes,
    ...$dataParams
);

if (!$stmt->execute()) {

    $stmt->close();

    paymentHistoryError(
        'Failed to fetch payment history.',
        500
    );
}

$result =
    $stmt->get_result();

$payments = [];


/*
|--------------------------------------------------------------------------
| Fetch Records
|--------------------------------------------------------------------------
*/

while ($row = $result->fetch_assoc()) {

    $paymentAmount =
        (float) $row['amount'];

    $bookingTotal =
        (float) $row['booking_total_amount'];


    /*
    |--------------------------------------------------------------------------
    | Payment State
    |--------------------------------------------------------------------------
    */

    $isPaid =
        $row['payment_status'] === 'paid';

    $isFailed =
        $row['payment_status'] === 'failed';

    $isPending =
        $row['payment_status'] === 'pending';

    $isRefunded =
        $row['payment_status'] === 'refunded';


    /*
    |--------------------------------------------------------------------------
    | Payment Record
    |--------------------------------------------------------------------------
    */

    $payments[] = [

        'payment_id' =>
            (int) $row['id'],

        'booking' => [

            'id' =>
                (int) $row['booking_id'],

            'booking_reference' =>
                $row['booking_reference'],

            'check_in_date' =>
                $row['check_in_date'],

            'check_out_date' =>
                $row['check_out_date'],

            'total_guests' =>
                (int) $row['total_guests'],

            'booking_total_amount' =>
                $bookingTotal,

            'booking_payment_status' =>
                $row['booking_payment_status'],

            'booking_status' =>
                $row['booking_status']
        ],

        'payment' => [

            'payment_gateway' =>
                $row['payment_gateway'],

            'razorpay_order_id' =>
                $row['razorpay_order_id'],

            'razorpay_payment_id' =>
                $row['razorpay_payment_id'],

            'amount' =>
                $paymentAmount,

            'amount_in_paise' =>
                (int) round(
                    $paymentAmount * 100
                ),

            'currency' =>
                strtoupper(
                    (string)
                    $row['currency']
                ),

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
        ],

        'state' => [

            'is_paid' =>
                $isPaid,

            'is_failed' =>
                $isFailed,

            'is_pending' =>
                $isPending,

            'is_refunded' =>
                $isRefunded
        ]
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

    'message' =>
        'Payment history fetched successfully.',

    'data' => [

        'payments' =>
            $payments,

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
                $page > 1
        ],

        'filters' => [

            'booking_id' =>
                $bookingId,

            'booking_reference' =>
                $bookingReference,

            'razorpay_order_id' =>
                $razorpayOrderId,

            'razorpay_payment_id' =>
                $razorpayPaymentId,

            'payment_status' =>
                $paymentStatus,

            'payment_method' =>
                $paymentMethod,

            'start_date' =>
                $startDate,

            'end_date' =>
                $endDate,

            'min_amount' =>
                $minAmount,

            'max_amount' =>
                $maxAmount,

            'sort_by' =>
                $sortBy,

            'sort_order' =>
                $sortOrder
        ]
    ]

], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

exit;