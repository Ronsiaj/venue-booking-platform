<?php

/**
 * SM GROUP
 * Revenue Report API
 *
 * File:
 * reports/revenue_report.php
 *
 * Method:
 * GET
 *
 * Authentication:
 * Admin / Staff JWT
 *
 * Revenue Definition:
 * ------------------------------------------------------------
 * Room      => Captured Razorpay payments
 * Hall      => Converted lead amount
 * Dining    => Converted lead amount
 * Catering  => Converted lead amount
 *
 * Query Parameters:
 * ------------------------------------------------------------
 * from_date
 * to_date
 * domain
 * payment_status
 * booking_status
 * search
 * min_amount
 * max_amount
 * page
 * limit
 * sort_by
 * sort_order
 */

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| CORS
|--------------------------------------------------------------------------
*/

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");


/*
|--------------------------------------------------------------------------
| OPTIONS / PREFLIGHT
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
    ]);

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
| Common Response Function
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
        JSON_UNESCAPED_SLASHES |
        JSON_PRESERVE_ZERO_FRACTION
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Get Current User
|--------------------------------------------------------------------------
*/

$currentUser = requireAdminOrStaff();


/*
|--------------------------------------------------------------------------
| Current User Details
|--------------------------------------------------------------------------
*/

$currentUserId = $currentUser['sub']
    ?? $currentUser['id']
    ?? null;

$currentUserRole = strtolower(
    trim(
        (string)(
            $currentUser['role']
            ?? ''
        )
    )
);


/*
|--------------------------------------------------------------------------
| Role Validation
|--------------------------------------------------------------------------
*/

if (
    !in_array(
        $currentUserRole,
        ['admin', 'staff'],
        true
    )
) {

    sendResponse(
        false,
        'You are not authorized to access the revenue report.',
        [],
        403
    );
}


/*
|--------------------------------------------------------------------------
| Database Validation
|--------------------------------------------------------------------------
*/

if (!isset($conn) || !($conn instanceof mysqli)) {

    sendResponse(
        false,
        'Database connection is not available.',
        [],
        500
    );
}


/*
|--------------------------------------------------------------------------
| Helper: Validate Date
|--------------------------------------------------------------------------
*/

function isValidDate(string $date): bool
{
    $dateObject = DateTime::createFromFormat(
        'Y-m-d',
        $date
    );

    return
        $dateObject !== false &&
        $dateObject->format('Y-m-d') === $date;
}


/*
|--------------------------------------------------------------------------
| Helper: Bind Dynamic Parameters
|--------------------------------------------------------------------------
*/

function bindDynamicParams(
    mysqli_stmt $stmt,
    string $types,
    array &$params
): void {

    if ($types === '' || empty($params)) {
        return;
    }

    $bindParams = [];
    $bindParams[] = $types;

    foreach ($params as $key => $value) {
        $bindParams[] = &$params[$key];
    }

    call_user_func_array(
        [$stmt, 'bind_param'],
        $bindParams
    );
}


/*
|--------------------------------------------------------------------------
| Helper: Execute Query
|--------------------------------------------------------------------------
*/

function executeQuery(
    mysqli $conn,
    string $sql,
    string $types = '',
    array $params = []
): mysqli_result {

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

    if ($types !== '' && !empty($params)) {

        bindDynamicParams(
            $stmt,
            $types,
            $params
        );
    }

    if (!$stmt->execute()) {

        $error = $stmt->error;

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

    $result = $stmt->get_result();

    /*
     * Store result in memory because statement
     * will be closed after fetching.
     */
    $rows = [];

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();

    /*
     * Return a temporary mysqli_result-like
     * structure is not possible.
     *
     * Therefore this helper is not used for
     * result fetching.
     */

    return $result;
}


/*
|--------------------------------------------------------------------------
| Query Parameters
|--------------------------------------------------------------------------
*/

$fromDate = trim(
    (string)(
        $_GET['from_date']
        ?? ''
    )
);

$toDate = trim(
    (string)(
        $_GET['to_date']
        ?? ''
    )
);

$domain = strtolower(
    trim(
        (string)(
            $_GET['domain']
            ?? 'all'
        )
    )
);

$paymentStatus = strtolower(
    trim(
        (string)(
            $_GET['payment_status']
            ?? 'all'
        )
    )
);

$bookingStatus = strtolower(
    trim(
        (string)(
            $_GET['booking_status']
            ?? 'all'
        )
    )
);

$search = trim(
    (string)(
        $_GET['search']
        ?? ''
    )
);

$minAmountRaw = trim(
    (string)(
        $_GET['min_amount']
        ?? ''
    )
);

$maxAmountRaw = trim(
    (string)(
        $_GET['max_amount']
        ?? ''
    )
);

$pageRaw = trim(
    (string)(
        $_GET['page']
        ?? '1'
    )
);

$limitRaw = trim(
    (string)(
        $_GET['limit']
        ?? '20'
    )
);

$sortBy = strtolower(
    trim(
        (string)(
            $_GET['sort_by']
            ?? 'revenue_date'
        )
    )
);

$sortOrder = strtoupper(
    trim(
        (string)(
            $_GET['sort_order']
            ?? 'DESC'
        )
    )
);


/*
|--------------------------------------------------------------------------
| Allowed Values
|--------------------------------------------------------------------------
*/

$allowedDomains = [
    'all',
    'room',
    'hall',
    'dining',
    'catering'
];

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

$allowedSortBy = [
    'revenue_date',
    'amount',
    'domain',
    'reference_no',
    'created_at'
];

$allowedSortOrder = [
    'ASC',
    'DESC'
];


/*
|--------------------------------------------------------------------------
| Domain Validation
|--------------------------------------------------------------------------
*/

if (!in_array($domain, $allowedDomains, true)) {

    sendResponse(
        false,
        'Invalid domain.',
        [
            'allowed_values' => $allowedDomains
        ],
        422
    );
}


/*
|--------------------------------------------------------------------------
| Payment Status Validation
|--------------------------------------------------------------------------
*/

if (
    !in_array(
        $paymentStatus,
        $allowedPaymentStatuses,
        true
    )
) {

    sendResponse(
        false,
        'Invalid payment_status.',
        [
            'allowed_values' =>
                $allowedPaymentStatuses
        ],
        422
    );
}


/*
|--------------------------------------------------------------------------
| Booking Status Validation
|--------------------------------------------------------------------------
*/

if (
    !in_array(
        $bookingStatus,
        $allowedBookingStatuses,
        true
    )
) {

    sendResponse(
        false,
        'Invalid booking_status.',
        [
            'allowed_values' =>
                $allowedBookingStatuses
        ],
        422
    );
}


/*
|--------------------------------------------------------------------------
| Sort Validation
|--------------------------------------------------------------------------
*/

if (
    !in_array(
        $sortBy,
        $allowedSortBy,
        true
    )
) {

    sendResponse(
        false,
        'Invalid sort_by.',
        [
            'allowed_values' =>
                $allowedSortBy
        ],
        422
    );
}


if (
    !in_array(
        $sortOrder,
        $allowedSortOrder,
        true
    )
) {

    sendResponse(
        false,
        'Invalid sort_order.',
        [
            'allowed_values' =>
                $allowedSortOrder
        ],
        422
    );
}


/*
|--------------------------------------------------------------------------
| Date Validation
|--------------------------------------------------------------------------
*/

if ($fromDate !== '') {

    if (!isValidDate($fromDate)) {

        sendResponse(
            false,
            'Invalid from_date. Expected format: YYYY-MM-DD.',
            [],
            422
        );
    }
}


if ($toDate !== '') {

    if (!isValidDate($toDate)) {

        sendResponse(
            false,
            'Invalid to_date. Expected format: YYYY-MM-DD.',
            [],
            422
        );
    }
}


if (
    $fromDate !== '' &&
    $toDate !== ''
) {

    if ($fromDate > $toDate) {

        sendResponse(
            false,
            'from_date cannot be greater than to_date.',
            [],
            422
        );
    }
}


/*
|--------------------------------------------------------------------------
| Convert Dates To SQL Datetime
|--------------------------------------------------------------------------
*/

$fromDateSql = null;
$toDateSql = null;

if ($fromDate !== '') {

    $fromDateSql =
        $fromDate . ' 00:00:00';
}

if ($toDate !== '') {

    $toDateSql =
        $toDate . ' 23:59:59';
}


/*
|--------------------------------------------------------------------------
| Search Validation
|--------------------------------------------------------------------------
*/

if (mb_strlen($search) > 100) {

    sendResponse(
        false,
        'Search cannot exceed 100 characters.',
        [],
        422
    );
}


/*
|--------------------------------------------------------------------------
| Amount Validation
|--------------------------------------------------------------------------
*/

$minAmount = null;
$maxAmount = null;


if ($minAmountRaw !== '') {

    if (
        !preg_match(
            '/^\d{1,9}(\.\d{1,2})?$/',
            $minAmountRaw
        )
    ) {

        sendResponse(
            false,
            'Invalid min_amount.',
            [],
            422
        );
    }

    $minAmount = (float)$minAmountRaw;

    if ($minAmount < 0) {

        sendResponse(
            false,
            'min_amount cannot be negative.',
            [],
            422
        );
    }
}


if ($maxAmountRaw !== '') {

    if (
        !preg_match(
            '/^\d{1,9}(\.\d{1,2})?$/',
            $maxAmountRaw
        )
    ) {

        sendResponse(
            false,
            'Invalid max_amount.',
            [],
            422
        );
    }

    $maxAmount = (float)$maxAmountRaw;

    if ($maxAmount < 0) {

        sendResponse(
            false,
            'max_amount cannot be negative.',
            [],
            422
        );
    }
}


if (
    $minAmount !== null &&
    $maxAmount !== null &&
    $minAmount > $maxAmount
) {

    sendResponse(
        false,
        'min_amount cannot be greater than max_amount.',
        [],
        422
    );
}


/*
|--------------------------------------------------------------------------
| Page Validation
|--------------------------------------------------------------------------
*/

if (
    !ctype_digit($pageRaw) ||
    (int)$pageRaw < 1
) {

    sendResponse(
        false,
        'Page must be a positive integer.',
        [],
        422
    );
}

$page = (int)$pageRaw;


/*
|--------------------------------------------------------------------------
| Limit Validation
|--------------------------------------------------------------------------
*/

if (
    !ctype_digit($limitRaw) ||
    (int)$limitRaw < 1
) {

    sendResponse(
        false,
        'Limit must be a positive integer.',
        [],
        422
    );
}

$limit = (int)$limitRaw;


/*
|--------------------------------------------------------------------------
| Maximum Limit
|--------------------------------------------------------------------------
*/

$maxLimit = 100;

if ($limit > $maxLimit) {

    sendResponse(
        false,
        "Limit cannot exceed {$maxLimit}.",
        [
            'max_limit' => $maxLimit
        ],
        422
    );
}


/*
|--------------------------------------------------------------------------
| SQL Date Conditions
|--------------------------------------------------------------------------
|
| Room:
|   p.paid_at
|
| Lead:
|   l.converted_at
|
*/


/*
|--------------------------------------------------------------------------
| ROOM REVENUE CONDITIONS
|--------------------------------------------------------------------------
*/

$roomWhere = [
    "p.payment_status = 'captured'",
    "rb.booking_status NOT IN ('cancelled','no_show')"
];

$roomParams = [];
$roomTypes = '';


/*
|--------------------------------------------------------------------------
| Room Date Filter
|--------------------------------------------------------------------------
*/

if ($fromDateSql !== null) {

    $roomWhere[] =
        "p.paid_at >= ?";

    $roomParams[] =
        $fromDateSql;

    $roomTypes .= 's';
}


if ($toDateSql !== null) {

    $roomWhere[] =
        "p.paid_at <= ?";

    $roomParams[] =
        $toDateSql;

    $roomTypes .= 's';
}


/*
|--------------------------------------------------------------------------
| Payment Status Filter
|--------------------------------------------------------------------------
*/

if ($paymentStatus !== 'all') {

    $roomWhere[] =
        "p.payment_status = ?";

    $roomParams[] =
        $paymentStatus;

    $roomTypes .= 's';
}


/*
|--------------------------------------------------------------------------
| Booking Status Filter
|--------------------------------------------------------------------------
*/

if ($bookingStatus !== 'all') {

    $roomWhere[] =
        "rb.booking_status = ?";

    $roomParams[] =
        $bookingStatus;

    $roomTypes .= 's';
}


/*
|--------------------------------------------------------------------------
| Amount Filter
|--------------------------------------------------------------------------
*/

if ($minAmount !== null) {

    $roomWhere[] =
        "p.amount >= ?";

    $roomParams[] =
        $minAmount;

    $roomTypes .= 'd';
}


if ($maxAmount !== null) {

    $roomWhere[] =
        "p.amount <= ?";

    $roomParams[] =
        $maxAmount;

    $roomTypes .= 'd';
}


/*
|--------------------------------------------------------------------------
| Search Filter - Room
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $roomWhere[] = "
        (
            rb.booking_reference LIKE ?
            OR p.razorpay_order_id LIKE ?
            OR p.razorpay_payment_id LIKE ?
        )
    ";

    $searchValue =
        '%' . $search . '%';

    $roomParams[] =
        $searchValue;

    $roomParams[] =
        $searchValue;

    $roomParams[] =
        $searchValue;

    $roomTypes .= 'sss';
}


/*
|--------------------------------------------------------------------------
| Room Where SQL
|--------------------------------------------------------------------------
*/

$roomWhereSql =
    implode(
        ' AND ',
        $roomWhere
    );


/*
|--------------------------------------------------------------------------
| LEAD REVENUE CONDITIONS
|--------------------------------------------------------------------------
*/

$leadWhere = [
    "l.status = 'converted'",
    "l.converted_amount IS NOT NULL",
    "l.converted_amount >= 0",
    "l.converted_at IS NOT NULL"
];

$leadParams = [];
$leadTypes = '';


/*
|--------------------------------------------------------------------------
| Domain Filter
|--------------------------------------------------------------------------
*/

if (
    $domain !== 'all' &&
    $domain !== 'room'
) {

    $leadWhere[] =
        "l.domain = ?";

    $leadParams[] =
        $domain;

    $leadTypes .= 's';
}


/*
|--------------------------------------------------------------------------
| Lead Date Filter
|--------------------------------------------------------------------------
*/

if ($fromDateSql !== null) {

    $leadWhere[] =
        "l.converted_at >= ?";

    $leadParams[] =
        $fromDateSql;

    $leadTypes .= 's';
}


if ($toDateSql !== null) {

    $leadWhere[] =
        "l.converted_at <= ?";

    $leadParams[] =
        $toDateSql;

    $leadTypes .= 's';
}


/*
|--------------------------------------------------------------------------
| Lead Amount Filter
|--------------------------------------------------------------------------
*/

if ($minAmount !== null) {

    $leadWhere[] =
        "l.converted_amount >= ?";

    $leadParams[] =
        $minAmount;

    $leadTypes .= 'd';
}


if ($maxAmount !== null) {

    $leadWhere[] =
        "l.converted_amount <= ?";

    $leadParams[] =
        $maxAmount;

    $leadTypes .= 'd';
}


/*
|--------------------------------------------------------------------------
| Staff Scope
|--------------------------------------------------------------------------
|
| Staff can only see converted leads
| assigned to themselves.
|
*/

if ($currentUserRole === 'staff') {

    if (
        $currentUserId === null ||
        !ctype_digit((string)$currentUserId)
    ) {

        sendResponse(
            false,
            'Invalid staff user identity.',
            [],
            401
        );
    }

    $leadWhere[] =
        "l.assigned_admin_id = ?";

    $leadParams[] =
        (int)$currentUserId;

    $leadTypes .= 'i';
}


/*
|--------------------------------------------------------------------------
| Search Filter - Lead
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $leadWhere[] = "
        (
            l.lead_reference LIKE ?
            OR l.customer_name LIKE ?
            OR l.customer_phone LIKE ?
            OR l.customer_email LIKE ?
        )
    ";

    $searchValue =
        '%' . $search . '%';

    $leadParams[] =
        $searchValue;

    $leadParams[] =
        $searchValue;

    $leadParams[] =
        $searchValue;

    $leadParams[] =
        $searchValue;

    $leadTypes .= 'ssss';
}


/*
|--------------------------------------------------------------------------
| Lead Where SQL
|--------------------------------------------------------------------------
*/

$leadWhereSql =
    implode(
        ' AND ',
        $leadWhere
    );


/*
|--------------------------------------------------------------------------
| INITIAL REVENUE VALUES
|--------------------------------------------------------------------------
*/

$roomRevenue = [
    'total_collected' => 0.00,
    'captured_payments' => 0,
    'booking_count' => 0,
    'refunded_amount' => 0.00,
    'failed_amount' => 0.00,
    'pending_amount' => 0.00
];

$leadRevenue = [
    'total_converted' => 0.00,
    'hall' => 0.00,
    'dining' => 0.00,
    'catering' => 0.00,
    'converted_leads' => 0
];


/*
|--------------------------------------------------------------------------
| ROOM REVENUE SUMMARY
|--------------------------------------------------------------------------
|
| Only execute when:
| all OR room
|
*/

if (
    $domain === 'all' ||
    $domain === 'room'
) {

    /*
     * For summary we need all payment statuses,
     * therefore build separate conditions.
     */

    $summaryRoomWhere = [
        "rb.booking_status NOT IN ('cancelled','no_show')"
    ];

    $summaryRoomParams = [];
    $summaryRoomTypes = '';


    if ($fromDateSql !== null) {

        $summaryRoomWhere[] =
            "p.paid_at >= ?";

        $summaryRoomParams[] =
            $fromDateSql;

        $summaryRoomTypes .= 's';
    }


    if ($toDateSql !== null) {

        $summaryRoomWhere[] =
            "p.paid_at <= ?";

        $summaryRoomParams[] =
            $toDateSql;

        $summaryRoomTypes .= 's';
    }


    if ($paymentStatus !== 'all') {

        $summaryRoomWhere[] =
            "p.payment_status = ?";

        $summaryRoomParams[] =
            $paymentStatus;

        $summaryRoomTypes .= 's';
    }


    if ($bookingStatus !== 'all') {

        $summaryRoomWhere[] =
            "rb.booking_status = ?";

        $summaryRoomParams[] =
            $bookingStatus;

        $summaryRoomTypes .= 's';
    }


    if ($minAmount !== null) {

        $summaryRoomWhere[] =
            "p.amount >= ?";

        $summaryRoomParams[] =
            $minAmount;

        $summaryRoomTypes .= 'd';
    }


    if ($maxAmount !== null) {

        $summaryRoomWhere[] =
            "p.amount <= ?";

        $summaryRoomParams[] =
            $maxAmount;

        $summaryRoomTypes .= 'd';
    }


    if ($search !== '') {

        $summaryRoomWhere[] = "
            (
                rb.booking_reference LIKE ?
                OR p.razorpay_order_id LIKE ?
                OR p.razorpay_payment_id LIKE ?
            )
        ";

        $searchValue =
            '%' . $search . '%';

        $summaryRoomParams[] =
            $searchValue;

        $summaryRoomParams[] =
            $searchValue;

        $summaryRoomParams[] =
            $searchValue;

        $summaryRoomTypes .= 'sss';
    }


    $summaryRoomWhereSql =
        implode(
            ' AND ',
            $summaryRoomWhere
        );


    $roomSummarySql = "
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
            ) AS total_collected,

            COUNT(
                CASE
                    WHEN p.payment_status = 'captured'
                    THEN p.id
                    ELSE NULL
                END
            ) AS captured_payments,

            COUNT(
                DISTINCT
                CASE
                    WHEN p.payment_status = 'captured'
                    THEN rb.id
                    ELSE NULL
                END
            ) AS booking_count,

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
                        WHEN p.payment_status IN
                        ('created','authorized')
                        THEN p.amount
                        ELSE 0
                    END
                ),
                0
            ) AS pending_amount

        FROM payments p

        INNER JOIN room_bookings rb
            ON rb.id = p.booking_id

        WHERE {$summaryRoomWhereSql}
    ";


    $stmt =
        $conn->prepare(
            $roomSummarySql
        );


    if (!$stmt) {

        sendResponse(
            false,
            'Failed to prepare room revenue summary.',
            [
                'error' => $conn->error
            ],
            500
        );
    }


    if (!empty($summaryRoomParams)) {

        bindDynamicParams(
            $stmt,
            $summaryRoomTypes,
            $summaryRoomParams
        );
    }


    if (!$stmt->execute()) {

        $error =
            $stmt->error;

        $stmt->close();

        sendResponse(
            false,
            'Failed to fetch room revenue summary.',
            [
                'error' => $error
            ],
            500
        );
    }


    $result =
        $stmt->get_result();


    if ($row = $result->fetch_assoc()) {

        $roomRevenue = [
            'total_collected' =>
                round(
                    (float)$row['total_collected'],
                    2
                ),

            'captured_payments' =>
                (int)$row['captured_payments'],

            'booking_count' =>
                (int)$row['booking_count'],

            'refunded_amount' =>
                round(
                    (float)$row['refunded_amount'],
                    2
                ),

            'failed_amount' =>
                round(
                    (float)$row['failed_amount'],
                    2
                ),

            'pending_amount' =>
                round(
                    (float)$row['pending_amount'],
                    2
                )
        ];
    }


    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| LEAD REVENUE SUMMARY
|--------------------------------------------------------------------------
*/

if (
    $domain === 'all' ||
    $domain === 'hall' ||
    $domain === 'dining' ||
    $domain === 'catering'
) {

    $leadSummarySql = "
        SELECT

            COUNT(*) AS converted_leads,

            COALESCE(
                SUM(l.converted_amount),
                0
            ) AS total_converted,

            COALESCE(
                SUM(
                    CASE
                        WHEN l.domain = 'hall'
                        THEN l.converted_amount
                        ELSE 0
                    END
                ),
                0
            ) AS hall_revenue,

            COALESCE(
                SUM(
                    CASE
                        WHEN l.domain = 'dining'
                        THEN l.converted_amount
                        ELSE 0
                    END
                ),
                0
            ) AS dining_revenue,

            COALESCE(
                SUM(
                    CASE
                        WHEN l.domain = 'catering'
                        THEN l.converted_amount
                        ELSE 0
                    END
                ),
                0
            ) AS catering_revenue

        FROM leads l

        WHERE {$leadWhereSql}
    ";


    $stmt =
        $conn->prepare(
            $leadSummarySql
        );


    if (!$stmt) {

        sendResponse(
            false,
            'Failed to prepare lead revenue summary.',
            [
                'error' => $conn->error
            ],
            500
        );
    }


    if (!empty($leadParams)) {

        bindDynamicParams(
            $stmt,
            $leadTypes,
            $leadParams
        );
    }


    if (!$stmt->execute()) {

        $error =
            $stmt->error;

        $stmt->close();

        sendResponse(
            false,
            'Failed to fetch lead revenue summary.',
            [
                'error' => $error
            ],
            500
        );
    }


    $result =
        $stmt->get_result();


    if ($row = $result->fetch_assoc()) {

        $leadRevenue = [
            'total_converted' =>
                round(
                    (float)$row['total_converted'],
                    2
                ),

            'hall' =>
                round(
                    (float)$row['hall_revenue'],
                    2
                ),

            'dining' =>
                round(
                    (float)$row['dining_revenue'],
                    2
                ),

            'catering' =>
                round(
                    (float)$row['catering_revenue'],
                    2
                ),

            'converted_leads' =>
                (int)$row['converted_leads']
        ];
    }


    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| TOTAL REVENUE
|--------------------------------------------------------------------------
*/

$totalRevenue = round(
    $roomRevenue['total_collected']
    + $leadRevenue['total_converted'],
    2
);


/*
|--------------------------------------------------------------------------
| NET ROOM COLLECTION
|--------------------------------------------------------------------------
*/

$netRoomCollection = round(
    $roomRevenue['total_collected']
    - $roomRevenue['refunded_amount'],
    2
);


/*
|--------------------------------------------------------------------------
| TOTAL PAYMENT TRANSACTIONS
|--------------------------------------------------------------------------
*/

$totalPaymentTransactions =
    $roomRevenue['captured_payments'];


/*
|--------------------------------------------------------------------------
| Revenue By Domain
|--------------------------------------------------------------------------
*/

$revenueByDomain = [

    'room' =>
        round(
            $roomRevenue['total_collected'],
            2
        ),

    'hall' =>
        round(
            $leadRevenue['hall'],
            2
        ),

    'dining' =>
        round(
            $leadRevenue['dining'],
            2
        ),

    'catering' =>
        round(
            $leadRevenue['catering'],
            2
        )
];


/*
|--------------------------------------------------------------------------
| MONTHLY REVENUE
|--------------------------------------------------------------------------
*/

$monthlyRevenueMap = [];


/*
|--------------------------------------------------------------------------
| Monthly Room Revenue
|--------------------------------------------------------------------------
*/

if (
    $domain === 'all' ||
    $domain === 'room'
) {

    $monthlyRoomWhere = [
        "p.payment_status = 'captured'",
        "rb.booking_status NOT IN ('cancelled','no_show')"
    ];

    $monthlyRoomParams = [];
    $monthlyRoomTypes = '';


    if ($fromDateSql !== null) {

        $monthlyRoomWhere[] =
            "p.paid_at >= ?";

        $monthlyRoomParams[] =
            $fromDateSql;

        $monthlyRoomTypes .= 's';
    }


    if ($toDateSql !== null) {

        $monthlyRoomWhere[] =
            "p.paid_at <= ?";

        $monthlyRoomParams[] =
            $toDateSql;

        $monthlyRoomTypes .= 's';
    }


    if ($paymentStatus !== 'all') {

        $monthlyRoomWhere[] =
            "p.payment_status = ?";

        $monthlyRoomParams[] =
            $paymentStatus;

        $monthlyRoomTypes .= 's';
    }


    if ($bookingStatus !== 'all') {

        $monthlyRoomWhere[] =
            "rb.booking_status = ?";

        $monthlyRoomParams[] =
            $bookingStatus;

        $monthlyRoomTypes .= 's';
    }


    if ($minAmount !== null) {

        $monthlyRoomWhere[] =
            "p.amount >= ?";

        $monthlyRoomParams[] =
            $minAmount;

        $monthlyRoomTypes .= 'd';
    }


    if ($maxAmount !== null) {

        $monthlyRoomWhere[] =
            "p.amount <= ?";

        $monthlyRoomParams[] =
            $maxAmount;

        $monthlyRoomTypes .= 'd';
    }


    if ($search !== '') {

        $monthlyRoomWhere[] = "
            (
                rb.booking_reference LIKE ?
                OR p.razorpay_order_id LIKE ?
                OR p.razorpay_payment_id LIKE ?
            )
        ";

        $searchValue =
            '%' . $search . '%';

        $monthlyRoomParams[] =
            $searchValue;

        $monthlyRoomParams[] =
            $searchValue;

        $monthlyRoomParams[] =
            $searchValue;

        $monthlyRoomTypes .= 'sss';
    }


    $monthlyRoomWhereSql =
        implode(
            ' AND ',
            $monthlyRoomWhere
        );


    $sql = "
        SELECT

            DATE_FORMAT(
                p.paid_at,
                '%Y-%m'
            ) AS revenue_month,

            COALESCE(
                SUM(p.amount),
                0
            ) AS revenue

        FROM payments p

        INNER JOIN room_bookings rb
            ON rb.id = p.booking_id

        WHERE {$monthlyRoomWhereSql}

        GROUP BY
            DATE_FORMAT(
                p.paid_at,
                '%Y-%m'
            )

        ORDER BY
            revenue_month ASC
    ";


    $stmt =
        $conn->prepare($sql);


    if (!$stmt) {

        sendResponse(
            false,
            'Failed to prepare monthly room revenue query.',
            [
                'error' => $conn->error
            ],
            500
        );
    }


    if (!empty($monthlyRoomParams)) {

        bindDynamicParams(
            $stmt,
            $monthlyRoomTypes,
            $monthlyRoomParams
        );
    }


    if (!$stmt->execute()) {

        $error =
            $stmt->error;

        $stmt->close();

        sendResponse(
            false,
            'Failed to fetch monthly room revenue.',
            [
                'error' => $error
            ],
            500
        );
    }


    $result =
        $stmt->get_result();


    while ($row = $result->fetch_assoc()) {

        $month =
            $row['revenue_month'];


        if (!isset(
            $monthlyRevenueMap[$month]
        )) {

            $monthlyRevenueMap[$month] = [
                'room' => 0.00,
                'hall' => 0.00,
                'dining' => 0.00,
                'catering' => 0.00,
                'total' => 0.00
            ];
        }


        $monthlyRevenueMap[$month]['room'] +=
            (float)$row['revenue'];
    }


    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Monthly Lead Revenue
|--------------------------------------------------------------------------
*/

if (
    $domain === 'all' ||
    $domain === 'hall' ||
    $domain === 'dining' ||
    $domain === 'catering'
) {

    $monthlyLeadWhere = [
        "l.status = 'converted'",
        "l.converted_amount IS NOT NULL",
        "l.converted_amount >= 0",
        "l.converted_at IS NOT NULL"
    ];

    $monthlyLeadParams = [];
    $monthlyLeadTypes = '';


    if (
        $domain !== 'all' &&
        $domain !== 'room'
    ) {

        $monthlyLeadWhere[] =
            "l.domain = ?";

        $monthlyLeadParams[] =
            $domain;

        $monthlyLeadTypes .= 's';
    }


    if ($fromDateSql !== null) {

        $monthlyLeadWhere[] =
            "l.converted_at >= ?";

        $monthlyLeadParams[] =
            $fromDateSql;

        $monthlyLeadTypes .= 's';
    }


    if ($toDateSql !== null) {

        $monthlyLeadWhere[] =
            "l.converted_at <= ?";

        $monthlyLeadParams[] =
            $toDateSql;

        $monthlyLeadTypes .= 's';
    }


    if ($minAmount !== null) {

        $monthlyLeadWhere[] =
            "l.converted_amount >= ?";

        $monthlyLeadParams[] =
            $minAmount;

        $monthlyLeadTypes .= 'd';
    }


    if ($maxAmount !== null) {

        $monthlyLeadWhere[] =
            "l.converted_amount <= ?";

        $monthlyLeadParams[] =
            $maxAmount;

        $monthlyLeadTypes .= 'd';
    }


    if ($currentUserRole === 'staff') {

        $monthlyLeadWhere[] =
            "l.assigned_admin_id = ?";

        $monthlyLeadParams[] =
            (int)$currentUserId;

        $monthlyLeadTypes .= 'i';
    }


    if ($search !== '') {

        $monthlyLeadWhere[] = "
            (
                l.lead_reference LIKE ?
                OR l.customer_name LIKE ?
                OR l.customer_phone LIKE ?
                OR l.customer_email LIKE ?
            )
        ";

        $searchValue =
            '%' . $search . '%';

        $monthlyLeadParams[] =
            $searchValue;

        $monthlyLeadParams[] =
            $searchValue;

        $monthlyLeadParams[] =
            $searchValue;

        $monthlyLeadParams[] =
            $searchValue;

        $monthlyLeadTypes .= 'ssss';
    }


    $monthlyLeadWhereSql =
        implode(
            ' AND ',
            $monthlyLeadWhere
        );


    $sql = "
        SELECT

            DATE_FORMAT(
                l.converted_at,
                '%Y-%m'
            ) AS revenue_month,

            l.domain,

            COALESCE(
                SUM(l.converted_amount),
                0
            ) AS revenue

        FROM leads l

        WHERE {$monthlyLeadWhereSql}

        GROUP BY

            DATE_FORMAT(
                l.converted_at,
                '%Y-%m'
            ),

            l.domain

        ORDER BY
            revenue_month ASC
    ";


    $stmt =
        $conn->prepare($sql);


    if (!$stmt) {

        sendResponse(
            false,
            'Failed to prepare monthly lead revenue query.',
            [
                'error' => $conn->error
            ],
            500
        );
    }


    if (!empty($monthlyLeadParams)) {

        bindDynamicParams(
            $stmt,
            $monthlyLeadTypes,
            $monthlyLeadParams
        );
    }


    if (!$stmt->execute()) {

        $error =
            $stmt->error;

        $stmt->close();

        sendResponse(
            false,
            'Failed to fetch monthly lead revenue.',
            [
                'error' => $error
            ],
            500
        );
    }


    $result =
        $stmt->get_result();


    while ($row = $result->fetch_assoc()) {

        $month =
            $row['revenue_month'];

        $leadDomain =
            $row['domain'];


        if (!isset(
            $monthlyRevenueMap[$month]
        )) {

            $monthlyRevenueMap[$month] = [
                'room' => 0.00,
                'hall' => 0.00,
                'dining' => 0.00,
                'catering' => 0.00,
                'total' => 0.00
            ];
        }


        if (
            isset(
                $monthlyRevenueMap[$month][$leadDomain]
            )
        ) {

            $monthlyRevenueMap[$month][$leadDomain] +=
                (float)$row['revenue'];
        }
    }


    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Calculate Monthly Totals
|--------------------------------------------------------------------------
*/

ksort(
    $monthlyRevenueMap
);


foreach (
    $monthlyRevenueMap
    as $month => &$monthData
) {

    $monthData['total'] =
        round(
            $monthData['room']
            + $monthData['hall']
            + $monthData['dining']
            + $monthData['catering'],
            2
        );

    $monthData['room'] =
        round($monthData['room'], 2);

    $monthData['hall'] =
        round($monthData['hall'], 2);

    $monthData['dining'] =
        round($monthData['dining'], 2);

    $monthData['catering'] =
        round($monthData['catering'], 2);
}

unset($monthData);


/*
|--------------------------------------------------------------------------
| CHART DATA
|--------------------------------------------------------------------------
*/

$chartLabels = [];

$roomDataset = [];
$hallDataset = [];
$diningDataset = [];
$cateringDataset = [];
$totalDataset = [];


foreach (
    $monthlyRevenueMap
    as $month => $monthData
) {

    $chartLabels[] =
        $month;

    $roomDataset[] =
        round(
            $monthData['room'],
            2
        );

    $hallDataset[] =
        round(
            $monthData['hall'],
            2
        );

    $diningDataset[] =
        round(
            $monthData['dining'],
            2
        );

    $cateringDataset[] =
        round(
            $monthData['catering'],
            2
        );

    $totalDataset[] =
        round(
            $monthData['total'],
            2
        );
}


/*
|--------------------------------------------------------------------------
| DOMAIN CHART
|--------------------------------------------------------------------------
*/

$domainChart = [
    'labels' => [
        'Room',
        'Hall',
        'Dining',
        'Catering'
    ],

    'datasets' => [
        [
            'label' => 'Revenue',
            'data' => [
                $revenueByDomain['room'],
                $revenueByDomain['hall'],
                $revenueByDomain['dining'],
                $revenueByDomain['catering']
            ]
        ]
    ]
];


/*
|--------------------------------------------------------------------------
| REVENUE RECORDS
|--------------------------------------------------------------------------
*/

$records = [];


/*
|--------------------------------------------------------------------------
| Room Revenue Records
|--------------------------------------------------------------------------
*/

if (
    $domain === 'all' ||
    $domain === 'room'
) {

    $roomRecordWhere = [
        "p.payment_status = 'captured'",
        "rb.booking_status NOT IN ('cancelled','no_show')"
    ];

    $roomRecordParams = [];
    $roomRecordTypes = '';


    if ($fromDateSql !== null) {

        $roomRecordWhere[] =
            "p.paid_at >= ?";

        $roomRecordParams[] =
            $fromDateSql;

        $roomRecordTypes .= 's';
    }


    if ($toDateSql !== null) {

        $roomRecordWhere[] =
            "p.paid_at <= ?";

        $roomRecordParams[] =
            $toDateSql;

        $roomRecordTypes .= 's';
    }


    if ($paymentStatus !== 'all') {

        $roomRecordWhere[] =
            "p.payment_status = ?";

        $roomRecordParams[] =
            $paymentStatus;

        $roomRecordTypes .= 's';
    }


    if ($bookingStatus !== 'all') {

        $roomRecordWhere[] =
            "rb.booking_status = ?";

        $roomRecordParams[] =
            $bookingStatus;

        $roomRecordTypes .= 's';
    }


    if ($minAmount !== null) {

        $roomRecordWhere[] =
            "p.amount >= ?";

        $roomRecordParams[] =
            $minAmount;

        $roomRecordTypes .= 'd';
    }


    if ($maxAmount !== null) {

        $roomRecordWhere[] =
            "p.amount <= ?";

        $roomRecordParams[] =
            $maxAmount;

        $roomRecordTypes .= 'd';
    }


    if ($search !== '') {

        $roomRecordWhere[] = "
            (
                rb.booking_reference LIKE ?
                OR p.razorpay_order_id LIKE ?
                OR p.razorpay_payment_id LIKE ?
            )
        ";

        $searchValue =
            '%' . $search . '%';

        $roomRecordParams[] =
            $searchValue;

        $roomRecordParams[] =
            $searchValue;

        $roomRecordParams[] =
            $searchValue;

        $roomRecordTypes .= 'sss';
    }


    $roomRecordWhereSql =
        implode(
            ' AND ',
            $roomRecordWhere
        );


    $sql = "
        SELECT

            p.id AS revenue_id,

            'room' AS domain,

            rb.booking_reference AS reference_no,

            p.amount,

            p.payment_status,

            rb.booking_status,

            p.paid_at AS revenue_date,

            p.created_at,

            p.razorpay_order_id,

            p.razorpay_payment_id,

            rb.id AS booking_id,

            rb.customer_id,

            NULL AS lead_id,

            c.name AS customer_name

        FROM payments p

        INNER JOIN room_bookings rb
            ON rb.id = p.booking_id

        INNER JOIN customers c
            ON c.id = rb.customer_id

        WHERE {$roomRecordWhereSql}
    ";


    $stmt =
        $conn->prepare($sql);


    if (!$stmt) {

        sendResponse(
            false,
            'Failed to prepare room revenue records query.',
            [
                'error' => $conn->error
            ],
            500
        );
    }


    if (!empty($roomRecordParams)) {

        bindDynamicParams(
            $stmt,
            $roomRecordTypes,
            $roomRecordParams
        );
    }


    if (!$stmt->execute()) {

        $error =
            $stmt->error;

        $stmt->close();

        sendResponse(
            false,
            'Failed to fetch room revenue records.',
            [
                'error' => $error
            ],
            500
        );
    }


    $result =
        $stmt->get_result();


    while ($row = $result->fetch_assoc()) {

        $records[] = [

            'revenue_id' =>
                (int)$row['revenue_id'],

            'domain' =>
                'room',

            'reference_no' =>
                $row['reference_no'],

            'amount' =>
                round(
                    (float)$row['amount'],
                    2
                ),

            'payment_status' =>
                $row['payment_status'],

            'booking_status' =>
                $row['booking_status'],

            'revenue_date' =>
                $row['revenue_date'],

            'created_at' =>
                $row['created_at'],

            'razorpay_order_id' =>
                $row['razorpay_order_id'],

            'razorpay_payment_id' =>
                $row['razorpay_payment_id'],

            'booking_id' =>
                (int)$row['booking_id'],

            'customer_id' =>
                (int)$row['customer_id'],

            'lead_id' =>
                null,

            'customer_name' =>
                $row['customer_name']
        ];
    }


    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Lead Revenue Records
|--------------------------------------------------------------------------
*/

if (
    $domain === 'all' ||
    $domain === 'hall' ||
    $domain === 'dining' ||
    $domain === 'catering'
) {

    $leadRecordWhere = [
        "l.status = 'converted'",
        "l.converted_amount IS NOT NULL",
        "l.converted_amount >= 0",
        "l.converted_at IS NOT NULL"
    ];

    $leadRecordParams = [];
    $leadRecordTypes = '';


    if (
        $domain !== 'all' &&
        $domain !== 'room'
    ) {

        $leadRecordWhere[] =
            "l.domain = ?";

        $leadRecordParams[] =
            $domain;

        $leadRecordTypes .= 's';
    }


    if ($fromDateSql !== null) {

        $leadRecordWhere[] =
            "l.converted_at >= ?";

        $leadRecordParams[] =
            $fromDateSql;

        $leadRecordTypes .= 's';
    }


    if ($toDateSql !== null) {

        $leadRecordWhere[] =
            "l.converted_at <= ?";

        $leadRecordParams[] =
            $toDateSql;

        $leadRecordTypes .= 's';
    }


    if ($minAmount !== null) {

        $leadRecordWhere[] =
            "l.converted_amount >= ?";

        $leadRecordParams[] =
            $minAmount;

        $leadRecordTypes .= 'd';
    }


    if ($maxAmount !== null) {

        $leadRecordWhere[] =
            "l.converted_amount <= ?";

        $leadRecordParams[] =
            $maxAmount;

        $leadRecordTypes .= 'd';
    }


    if ($currentUserRole === 'staff') {

        $leadRecordWhere[] =
            "l.assigned_admin_id = ?";

        $leadRecordParams[] =
            (int)$currentUserId;

        $leadRecordTypes .= 'i';
    }


    if ($search !== '') {

        $leadRecordWhere[] = "
            (
                l.lead_reference LIKE ?
                OR l.customer_name LIKE ?
                OR l.customer_phone LIKE ?
                OR l.customer_email LIKE ?
            )
        ";

        $searchValue =
            '%' . $search . '%';

        $leadRecordParams[] =
            $searchValue;

        $leadRecordParams[] =
            $searchValue;

        $leadRecordParams[] =
            $searchValue;

        $leadRecordParams[] =
            $searchValue;

        $leadRecordTypes .= 'ssss';
    }


    $leadRecordWhereSql =
        implode(
            ' AND ',
            $leadRecordWhere
        );


    $sql = "
        SELECT

            l.id AS revenue_id,

            l.domain,

            l.lead_reference AS reference_no,

            l.converted_amount AS amount,

            NULL AS payment_status,

            l.status AS booking_status,

            l.converted_at AS revenue_date,

            l.created_at,

            NULL AS razorpay_order_id,

            NULL AS razorpay_payment_id,

            NULL AS booking_id,

            NULL AS customer_id,

            l.id AS lead_id,

            l.customer_name

        FROM leads l

        WHERE {$leadRecordWhereSql}
    ";


    $stmt =
        $conn->prepare($sql);


    if (!$stmt) {

        sendResponse(
            false,
            'Failed to prepare lead revenue records query.',
            [
                'error' => $conn->error
            ],
            500
        );
    }


    if (!empty($leadRecordParams)) {

        bindDynamicParams(
            $stmt,
            $leadRecordTypes,
            $leadRecordParams
        );
    }


    if (!$stmt->execute()) {

        $error =
            $stmt->error;

        $stmt->close();

        sendResponse(
            false,
            'Failed to fetch lead revenue records.',
            [
                'error' => $error
            ],
            500
        );
    }


    $result =
        $stmt->get_result();


    while ($row = $result->fetch_assoc()) {

        $records[] = [

            'revenue_id' =>
                (int)$row['revenue_id'],

            'domain' =>
                $row['domain'],

            'reference_no' =>
                $row['reference_no'],

            'amount' =>
                round(
                    (float)$row['amount'],
                    2
                ),

            'payment_status' =>
                null,

            'booking_status' =>
                'converted',

            'revenue_date' =>
                $row['revenue_date'],

            'created_at' =>
                $row['created_at'],

            'razorpay_order_id' =>
                null,

            'razorpay_payment_id' =>
                null,

            'booking_id' =>
                null,

            'customer_id' =>
                null,

            'lead_id' =>
                (int)$row['lead_id'],

            'customer_name' =>
                $row['customer_name']
        ];
    }


    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Sort Records In PHP
|--------------------------------------------------------------------------
|
| Revenue comes from two different tables.
| Therefore sorting the merged records is safer
| than trying to UNION different queries with
| separate prepared parameters.
|
*/

usort(
    $records,
    function (
        array $a,
        array $b
    ) use (
        $sortBy,
        $sortOrder
    ) {

        switch ($sortBy) {

            case 'amount':

                $valueA =
                    (float)$a['amount'];

                $valueB =
                    (float)$b['amount'];

                break;


            case 'domain':

                $valueA =
                    strtolower(
                        (string)$a['domain']
                    );

                $valueB =
                    strtolower(
                        (string)$b['domain']
                    );

                break;


            case 'reference_no':

                $valueA =
                    strtolower(
                        (string)$a['reference_no']
                    );

                $valueB =
                    strtolower(
                        (string)$b['reference_no']
                    );

                break;


            case 'created_at':

                $valueA =
                    (string)$a['created_at'];

                $valueB =
                    (string)$b['created_at'];

                break;


            case 'revenue_date':
            default:

                $valueA =
                    (string)$a['revenue_date'];

                $valueB =
                    (string)$b['revenue_date'];

                break;
        }


        if ($valueA === $valueB) {
            return 0;
        }


        $result =
            $valueA <=> $valueB;


        return
            $sortOrder === 'ASC'
                ? $result
                : -$result;
    }
);


/*
|--------------------------------------------------------------------------
| Total Records
|--------------------------------------------------------------------------
*/

$totalRecords =
    count($records);


/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

$totalPages =
    $totalRecords > 0
        ? (int)ceil(
            $totalRecords / $limit
        )
        : 0;


if (
    $totalPages > 0 &&
    $page > $totalPages
) {

    $page = $totalPages;
}


$offset =
    ($page - 1) * $limit;


$paginatedRecords =
    array_slice(
        $records,
        $offset,
        $limit
    );


/*
|--------------------------------------------------------------------------
| Format Records
|--------------------------------------------------------------------------
*/

$formattedRecords = [];


foreach (
    $paginatedRecords
    as $record
) {

    $formattedRecords[] = [

        'revenue_id' =>
            (int)$record['revenue_id'],

        'domain' =>
            $record['domain'],

        'reference_no' =>
            $record['reference_no'],

        'amount' =>
            round(
                (float)$record['amount'],
                2
            ),

        'payment_status' =>
            $record['payment_status'],

        'booking_status' =>
            $record['booking_status'],

        'revenue_date' =>
            $record['revenue_date'],

        'created_at' =>
            $record['created_at'],

        'razorpay_order_id' =>
            $record['razorpay_order_id'],

        'razorpay_payment_id' =>
            $record['razorpay_payment_id'],

        'booking_id' =>
            $record['booking_id'] !== null
                ? (int)$record['booking_id']
                : null,

        'customer_id' =>
            $record['customer_id'] !== null
                ? (int)$record['customer_id']
                : null,

        'lead_id' =>
            $record['lead_id'] !== null
                ? (int)$record['lead_id']
                : null,

        'customer_name' =>
            $record['customer_name']
    ];
}


/*
|--------------------------------------------------------------------------
| Revenue Counts
|--------------------------------------------------------------------------
*/

$roomRevenueRecordCount = 0;
$hallRevenueRecordCount = 0;
$diningRevenueRecordCount = 0;
$cateringRevenueRecordCount = 0;


foreach (
    $records
    as $record
) {

    switch ($record['domain']) {

        case 'room':
            $roomRevenueRecordCount++;
            break;

        case 'hall':
            $hallRevenueRecordCount++;
            break;

        case 'dining':
            $diningRevenueRecordCount++;
            break;

        case 'catering':
            $cateringRevenueRecordCount++;
            break;
    }
}


/*
|--------------------------------------------------------------------------
| Average Revenue
|--------------------------------------------------------------------------
*/

$averageRevenuePerRecord =
    $totalRecords > 0
        ? round(
            $totalRevenue / $totalRecords,
            2
        )
        : 0.00;


/*
|--------------------------------------------------------------------------
| Revenue Breakdown
|--------------------------------------------------------------------------
*/

$breakdown = [

    'room' => [

        'revenue_type' =>
            'actual_collected_payment',

        'amount' =>
            round(
                $roomRevenue['total_collected'],
                2
            ),

        'payment_count' =>
            $roomRevenue['captured_payments'],

        'booking_count' =>
            $roomRevenue['booking_count']
    ],


    'hall' => [

        'revenue_type' =>
            'converted_lead_value',

        'amount' =>
            round(
                $leadRevenue['hall'],
                2
            ),

        'converted_leads' =>
            $domain === 'hall'
                || $domain === 'all'
                ? count(
                    array_filter(
                        $records,
                        fn($record) =>
                            $record['domain'] === 'hall'
                    )
                )
                : 0
    ],


    'dining' => [

        'revenue_type' =>
            'converted_lead_value',

        'amount' =>
            round(
                $leadRevenue['dining'],
                2
            ),

        'converted_leads' =>
            $domain === 'dining'
                || $domain === 'all'
                ? count(
                    array_filter(
                        $records,
                        fn($record) =>
                            $record['domain'] === 'dining'
                    )
                )
                : 0
    ],


    'catering' => [

        'revenue_type' =>
            'converted_lead_value',

        'amount' =>
            round(
                $leadRevenue['catering'],
                2
            ),

        'converted_leads' =>
            $domain === 'catering'
                || $domain === 'all'
                ? count(
                    array_filter(
                        $records,
                        fn($record) =>
                            $record['domain'] === 'catering'
                    )
                )
                : 0
    ]
];


/*
|--------------------------------------------------------------------------
| Payment Summary
|--------------------------------------------------------------------------
*/

$paymentSummary = [

    'room_collected' =>
        round(
            $roomRevenue['total_collected'],
            2
        ),

    'room_net_after_refund' =>
        round(
            $netRoomCollection,
            2
        ),

    'room_refunded' =>
        round(
            $roomRevenue['refunded_amount'],
            2
        ),

    'room_failed' =>
        round(
            $roomRevenue['failed_amount'],
            2
        ),

    'room_pending' =>
        round(
            $roomRevenue['pending_amount'],
            2
        ),

    'lead_converted' =>
        round(
            $leadRevenue['total_converted'],
            2
        )
];


/*
|--------------------------------------------------------------------------
| Pagination Information
|--------------------------------------------------------------------------
*/

$pagination = [

    'page' =>
        $page,

    'limit' =>
        $limit,

    'total_records' =>
        $totalRecords,

    'total_pages' =>
        $totalPages,

    'from' =>
        $totalRecords > 0
            ? $offset + 1
            : 0,

    'to' =>
        $totalRecords > 0
            ? min(
                $offset + $limit,
                $totalRecords
            )
            : 0,

    'has_next_page' =>
        $page < $totalPages,

    'has_previous_page' =>
        $page > 1
];


/*
|--------------------------------------------------------------------------
| Currency
|--------------------------------------------------------------------------
*/

$currency =
    getenv('CURRENCY_CODE')
    ?: 'INR';

$currencySymbol =
    getenv('CURRENCY_SYMBOL')
    ?: '₹';


/*
|--------------------------------------------------------------------------
| Final Response
|--------------------------------------------------------------------------
*/

sendResponse(
    true,
    'Revenue report fetched successfully.',
    [

        /*
        |--------------------------------------------------------------------------
        | Meta
        |--------------------------------------------------------------------------
        */

        'meta' => [

            'report' =>
                'revenue_report',

            'currency' =>
                $currency,

            'currency_symbol' =>
                $currencySymbol,

            'generated_at' =>
                date('Y-m-d H:i:s'),

            'revenue_definition' => [

                'room' =>
                    'Captured Razorpay payments.',

                'hall' =>
                    'Converted lead amount.',

                'dining' =>
                    'Converted lead amount.',

                'catering' =>
                    'Converted lead amount.'
            ]
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

            'room_revenue_scope' =>
                'all_room_revenue',

            'lead_revenue_scope' =>
                $currentUserRole === 'staff'
                    ? 'assigned_leads_only'
                    : 'all_leads'
        ],


        /*
        |--------------------------------------------------------------------------
        | Filters
        |--------------------------------------------------------------------------
        */

        'filters' => [

            'from_date' =>
                $fromDate !== ''
                    ? $fromDate
                    : null,

            'to_date' =>
                $toDate !== ''
                    ? $toDate
                    : null,

            'domain' =>
                $domain,

            'payment_status' =>
                $paymentStatus,

            'booking_status' =>
                $bookingStatus,

            'search' =>
                $search !== ''
                    ? $search
                    : null,

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
        | Main Revenue
        |--------------------------------------------------------------------------
        */

        'revenue' => [

            'total_revenue' =>
                $totalRevenue,

            'room_revenue' =>
                round(
                    $roomRevenue['total_collected'],
                    2
                ),

            'hall_revenue' =>
                round(
                    $leadRevenue['hall'],
                    2
                ),

            'dining_revenue' =>
                round(
                    $leadRevenue['dining'],
                    2
                ),

            'catering_revenue' =>
                round(
                    $leadRevenue['catering'],
                    2
                ),

            'net_room_collection' =>
                $netRoomCollection,

            'average_revenue_per_record' =>
                $averageRevenuePerRecord
        ],


        /*
        |--------------------------------------------------------------------------
        | Room Revenue
        |--------------------------------------------------------------------------
        */

        'room_revenue' =>
            $roomRevenue,


        /*
        |--------------------------------------------------------------------------
        | Lead Revenue
        |--------------------------------------------------------------------------
        */

        'lead_revenue' =>
            $leadRevenue,


        /*
        |--------------------------------------------------------------------------
        | Revenue Breakdown
        |--------------------------------------------------------------------------
        */

        'breakdown' =>
            $breakdown,


        /*
        |--------------------------------------------------------------------------
        | Payment Summary
        |--------------------------------------------------------------------------
        */

        'payment_summary' =>
            $paymentSummary,


        /*
        |--------------------------------------------------------------------------
        | Domain Revenue
        |--------------------------------------------------------------------------
        */

        'revenue_by_domain' =>
            $revenueByDomain,


        /*
        |--------------------------------------------------------------------------
        | Counts
        |--------------------------------------------------------------------------
        */

        'counts' => [

            'total_revenue_records' =>
                $totalRecords,

            'room_records' =>
                $roomRevenueRecordCount,

            'hall_records' =>
                $hallRevenueRecordCount,

            'dining_records' =>
                $diningRevenueRecordCount,

            'catering_records' =>
                $cateringRevenueRecordCount,

            'captured_room_payments' =>
                $roomRevenue['captured_payments'],

            'converted_leads' =>
                $leadRevenue['converted_leads']
        ],


        /*
        |--------------------------------------------------------------------------
        | Pagination
        |--------------------------------------------------------------------------
        */

        'pagination' =>
            $pagination,


        /*
        |--------------------------------------------------------------------------
        | Records
        |--------------------------------------------------------------------------
        */

        'records' =>
            $formattedRecords,


        /*
        |--------------------------------------------------------------------------
        | Chart.js
        |--------------------------------------------------------------------------
        */

        'charts' => [

            /*
            |--------------------------------------------------------------------------
            | Monthly Revenue
            |--------------------------------------------------------------------------
            */

            'monthly_revenue' => [

                'labels' =>
                    $chartLabels,

                'datasets' => [

                    [
                        'label' =>
                            'Room Revenue',

                        'data' =>
                            $roomDataset
                    ],

                    [
                        'label' =>
                            'Hall Revenue',

                        'data' =>
                            $hallDataset
                    ],

                    [
                        'label' =>
                            'Dining Revenue',

                        'data' =>
                            $diningDataset
                    ],

                    [
                        'label' =>
                            'Catering Revenue',

                        'data' =>
                            $cateringDataset
                    ],

                    [
                        'label' =>
                            'Total Revenue',

                        'data' =>
                            $totalDataset
                    ]
                ]
            ],


            /*
            |--------------------------------------------------------------------------
            | Revenue By Domain
            |--------------------------------------------------------------------------
            */

            'revenue_by_domain' =>
                $domainChart
        ],


        /*
        |--------------------------------------------------------------------------
        | Monthly Raw Data
        |--------------------------------------------------------------------------
        */

        'monthly_revenue' =>
            array_values(
                array_map(
                    function (
                        string $month,
                        array $data
                    ) {

                        return [
                            'month' =>
                                $month,

                            'room' =>
                                round(
                                    $data['room'],
                                    2
                                ),

                            'hall' =>
                                round(
                                    $data['hall'],
                                    2
                                ),

                            'dining' =>
                                round(
                                    $data['dining'],
                                    2
                                ),

                            'catering' =>
                                round(
                                    $data['catering'],
                                    2
                                ),

                            'total' =>
                                round(
                                    $data['total'],
                                    2
                                )
                        ];

                    },
                    array_keys(
                        $monthlyRevenueMap
                    ),
                    array_values(
                        $monthlyRevenueMap
                    )
                )
            )
        ]
    
);