<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);

    echo json_encode([
        'status' => false,
        'message' => 'Only GET method is allowed.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


/*
|--------------------------------------------------------------------------
| Config
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../config/middleware.php';


/*
|--------------------------------------------------------------------------
| Helper: JSON Response
|--------------------------------------------------------------------------
*/

function sendResponse(
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
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

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

if (!in_array($currentUserRole, ['admin', 'staff', 'super_admin'], true)) {

    sendResponse(
        false,
        'You are not authorized to access revenue data.',
        [],
        403
    );
}

if (!is_numeric($currentUserId) || (int)$currentUserId <= 0) {

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
| Database
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
| Read Query Parameters
|--------------------------------------------------------------------------
*/

$fromDate       = isset($_GET['from_date']) ? trim((string)$_GET['from_date']) : null;
$toDate         = isset($_GET['to_date']) ? trim((string)$_GET['to_date']) : null;
$domain         = isset($_GET['domain']) ? strtolower(trim((string)$_GET['domain'])) : 'all';
$paymentStatus  = isset($_GET['payment_status']) ? strtolower(trim((string)$_GET['payment_status'])) : 'all';
$bookingStatus  = isset($_GET['booking_status']) ? strtolower(trim((string)$_GET['booking_status'])) : 'all';

$search         = isset($_GET['search']) ? trim((string)$_GET['search']) : '';

$page = isset($_GET['page'])
    ? filter_var($_GET['page'], FILTER_VALIDATE_INT)
    : 1;

$limit = isset($_GET['limit'])
    ? filter_var($_GET['limit'], FILTER_VALIDATE_INT)
    : 20;

$sortBy = isset($_GET['sort_by'])
    ? trim((string)$_GET['sort_by'])
    : 'date';

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
| Date Validation
|--------------------------------------------------------------------------
*/

function isValidDate(string $date): bool
{
    $parsed = DateTime::createFromFormat('Y-m-d', $date);

    return $parsed !== false
        && $parsed->format('Y-m-d') === $date;
}


if ($fromDate !== null && $fromDate !== '') {

    if (!isValidDate($fromDate)) {
        $errors['from_date'] = 'from_date must be in YYYY-MM-DD format.';
    }
}


if ($toDate !== null && $toDate !== '') {

    if (!isValidDate($toDate)) {
        $errors['to_date'] = 'to_date must be in YYYY-MM-DD format.';
    }
}


if (
    empty($errors)
    && $fromDate !== null
    && $fromDate !== ''
    && $toDate !== null
    && $toDate !== ''
) {

    if ($fromDate > $toDate) {

        $errors['date_range'] =
            'from_date cannot be greater than to_date.';
    }
}


/*
|--------------------------------------------------------------------------
| Domain Validation
|--------------------------------------------------------------------------
*/

$allowedDomains = [
    'all',
    'room',
    'hall',
    'dining',
    'catering'
];

if (!in_array($domain, $allowedDomains, true)) {

    $errors['domain'] =
        'Invalid domain. Allowed values: all, room, hall, dining, catering.';
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

if (!in_array($paymentStatus, $allowedPaymentStatuses, true)) {

    $errors['payment_status'] =
        'Invalid payment_status.';
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

if (!in_array($bookingStatus, $allowedBookingStatuses, true)) {

    $errors['booking_status'] =
        'Invalid booking_status.';
}


/*
|--------------------------------------------------------------------------
| Pagination Validation
|--------------------------------------------------------------------------
*/

if ($page === false || $page < 1) {

    $errors['page'] =
        'page must be a positive integer.';
}

if ($limit === false || $limit < 1 || $limit > 100) {

    $errors['limit'] =
        'limit must be between 1 and 100.';
}


/*
|--------------------------------------------------------------------------
| Sort Validation
|--------------------------------------------------------------------------
*/

$allowedSortColumns = [
    'date',
    'amount',
    'reference',
    'domain'
];

if (!in_array($sortBy, $allowedSortColumns, true)) {

    $errors['sort_by'] =
        'Invalid sort_by. Allowed values: date, amount, reference, domain.';
}


if (!in_array($sortOrder, ['asc', 'desc'], true)) {

    $errors['sort_order'] =
        'sort_order must be asc or desc.';
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
| Normalise Dates
|--------------------------------------------------------------------------
*/

$fromDateSql = null;
$toDateSql   = null;

if ($fromDate !== null && $fromDate !== '') {
    $fromDateSql = $fromDate . ' 00:00:00';
}

if ($toDate !== null && $toDate !== '') {
    $toDateSql = $toDate . ' 23:59:59';
}


/*
|--------------------------------------------------------------------------
| Currency
|--------------------------------------------------------------------------
*/

$currency = 'INR';
$currencySymbol = '₹';

try {

    $settingsStmt = $conn->prepare("
        SELECT setting_key, setting_value
        FROM settings
        WHERE setting_key IN ('currency', 'currency_symbol')
    ");

    if ($settingsStmt) {

        $settingsStmt->execute();

        $settingsResult = $settingsStmt->get_result();

        while ($setting = $settingsResult->fetch_assoc()) {

            if ($setting['setting_key'] === 'currency') {
                $currency = (string)$setting['setting_value'];
            }

            if ($setting['setting_key'] === 'currency_symbol') {
                $currencySymbol = (string)$setting['setting_value'];
            }
        }

        $settingsStmt->close();
    }

} catch (Throwable $e) {

    // Keep defaults.
}


/*
|--------------------------------------------------------------------------
| Revenue Summary
|--------------------------------------------------------------------------
|
| ROOM:
|   Actual collected revenue = captured payments.
|
| LEADS:
|   Converted revenue = leads.converted_amount
|
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| 1. ROOM REVENUE
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


if ($domain === 'all' || $domain === 'room') {

    $roomWhere = [
        "p.payment_gateway = 'razorpay'"
    ];

    $roomParams = [];
    $roomTypes = '';


    /*
    |--------------------------------------------------------------------------
    | Payment Status Filter
    |--------------------------------------------------------------------------
    */

    if ($paymentStatus !== 'all') {

        $roomWhere[] = "p.payment_status = ?";
        $roomParams[] = $paymentStatus;
        $roomTypes .= 's';
    }


    /*
    |--------------------------------------------------------------------------
    | Booking Status Filter
    |--------------------------------------------------------------------------
    */

    if ($bookingStatus !== 'all') {

        $roomWhere[] = "rb.booking_status = ?";
        $roomParams[] = $bookingStatus;
        $roomTypes .= 's';

    } else {

        /*
        | Cancelled/no_show bookings should not be considered
        | as normal revenue records.
        */

        $roomWhere[] = "rb.booking_status NOT IN ('cancelled','no_show')";
    }


    /*
    |--------------------------------------------------------------------------
    | Date Filter
    |--------------------------------------------------------------------------
    */

    if ($fromDateSql !== null) {

        $roomWhere[] = "p.created_at >= ?";
        $roomParams[] = $fromDateSql;
        $roomTypes .= 's';
    }


    if ($toDateSql !== null) {

        $roomWhere[] = "p.created_at <= ?";
        $roomParams[] = $toDateSql;
        $roomTypes .= 's';
    }


    $roomWhereSql = implode(' AND ', $roomWhere);


    $roomSql = "
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
                DISTINCT
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
                        WHEN p.payment_status IN ('created','authorized')
                        THEN p.amount
                        ELSE 0
                    END
                ),
                0
            ) AS pending_amount

        FROM payments p

        INNER JOIN room_bookings rb
            ON rb.id = p.booking_id

        WHERE {$roomWhereSql}
    ";


    $stmt = $conn->prepare($roomSql);

    if (!$stmt) {

        sendResponse(
            false,
            'Failed to prepare room revenue query.',
            [
                'error' => $conn->error
            ],
            500
        );
    }


    if (!empty($roomParams)) {

        $stmt->bind_param(
            $roomTypes,
            ...$roomParams
        );
    }


    if (!$stmt->execute()) {

        $error = $stmt->error;
        $stmt->close();

        sendResponse(
            false,
            'Failed to fetch room revenue.',
            [
                'error' => $error
            ],
            500
        );
    }


    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {

        $roomRevenue = [
            'total_collected' => round(
                (float)$row['total_collected'],
                2
            ),

            'captured_payments' => (int)$row['captured_payments'],

            'booking_count' => (int)$row['booking_count'],

            'refunded_amount' => round(
                (float)$row['refunded_amount'],
                2
            ),

            'failed_amount' => round(
                (float)$row['failed_amount'],
                2
            ),

            'pending_amount' => round(
                (float)$row['pending_amount'],
                2
            )
        ];
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| 2. LEAD REVENUE
|--------------------------------------------------------------------------
*/

$leadRevenue = [
    'total_converted' => 0.00,
    'hall' => 0.00,
    'dining' => 0.00,
    'catering' => 0.00,
    'converted_leads' => 0
];


if (
    $domain === 'all'
    || $domain === 'hall'
    || $domain === 'dining'
    || $domain === 'catering'
) {

    $leadWhere = [
        "l.status = 'converted'",
        "l.converted_amount IS NOT NULL",
        "l.converted_amount >= 0"
    ];

    $leadParams = [];
    $leadTypes = '';


    /*
    |--------------------------------------------------------------------------
    | Domain Filter
    |--------------------------------------------------------------------------
    */

    if ($domain !== 'all' && $domain !== 'room') {

        $leadWhere[] = "l.domain = ?";
        $leadParams[] = $domain;
        $leadTypes .= 's';
    }


    /*
    |--------------------------------------------------------------------------
    | Date Filter
    |--------------------------------------------------------------------------
    */

    if ($fromDateSql !== null) {

        $leadWhere[] = "l.converted_at >= ?";
        $leadParams[] = $fromDateSql;
        $leadTypes .= 's';
    }


    if ($toDateSql !== null) {

        $leadWhere[] = "l.converted_at <= ?";
        $leadParams[] = $toDateSql;
        $leadTypes .= 's';
    }


    /*
    |--------------------------------------------------------------------------
    | Staff Scope
    |--------------------------------------------------------------------------
    */

    if ($currentUserRole === 'staff') {

        $leadWhere[] = "l.assigned_admin_id = ?";
        $leadParams[] = $currentUserId;
        $leadTypes .= 'i';
    }


    $leadWhereSql = implode(' AND ', $leadWhere);


    $leadSql = "
        SELECT

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
            ) AS catering_revenue,

            COUNT(*) AS converted_leads

        FROM leads l

        WHERE {$leadWhereSql}
    ";


    $stmt = $conn->prepare($leadSql);

    if (!$stmt) {

        sendResponse(
            false,
            'Failed to prepare lead revenue query.',
            [
                'error' => $conn->error
            ],
            500
        );
    }


    if (!empty($leadParams)) {

        $stmt->bind_param(
            $leadTypes,
            ...$leadParams
        );
    }


    if (!$stmt->execute()) {

        $error = $stmt->error;
        $stmt->close();

        sendResponse(
            false,
            'Failed to fetch lead revenue.',
            [
                'error' => $error
            ],
            500
        );
    }


    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {

        $leadRevenue = [
            'total_converted' => round(
                (float)$row['total_converted'],
                2
            ),

            'hall' => round(
                (float)$row['hall_revenue'],
                2
            ),

            'dining' => round(
                (float)$row['dining_revenue'],
                2
            ),

            'catering' => round(
                (float)$row['catering_revenue'],
                2
            ),

            'converted_leads' => (int)$row['converted_leads']
        ];
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| 3. TOTAL REVENUE
|--------------------------------------------------------------------------
*/

$totalRevenue = round(
    $roomRevenue['total_collected']
    + $leadRevenue['total_converted'],
    2
);


/*
|--------------------------------------------------------------------------
| 4. REVENUE BY DOMAIN
|--------------------------------------------------------------------------
*/

$revenueByDomain = [
    'room' => $roomRevenue['total_collected'],
    'hall' => $leadRevenue['hall'],
    'dining' => $leadRevenue['dining'],
    'catering' => $leadRevenue['catering']
];


/*
|--------------------------------------------------------------------------
| 5. MONTHLY REVENUE
|--------------------------------------------------------------------------
|
| IMPORTANT:
| We calculate this directly from tables instead of combining
| vw_monthly_room_revenue + vw_monthly_lead_conversion in a way
| that could double-count lead revenue.
|
|--------------------------------------------------------------------------
*/

$monthlyRevenueMap = [];


/*
|--------------------------------------------------------------------------
| Room Monthly Revenue
|--------------------------------------------------------------------------
*/

if ($domain === 'all' || $domain === 'room') {

    $monthlyRoomWhere = [
        "p.payment_status = 'captured'",
        "rb.booking_status NOT IN ('cancelled','no_show')"
    ];

    $monthlyRoomParams = [];
    $monthlyRoomTypes = '';


    if ($fromDateSql !== null) {

        $monthlyRoomWhere[] = "p.created_at >= ?";
        $monthlyRoomParams[] = $fromDateSql;
        $monthlyRoomTypes .= 's';
    }


    if ($toDateSql !== null) {

        $monthlyRoomWhere[] = "p.created_at <= ?";
        $monthlyRoomParams[] = $toDateSql;
        $monthlyRoomTypes .= 's';
    }


    $monthlyRoomWhereSql =
        implode(' AND ', $monthlyRoomWhere);


    $sql = "
        SELECT
            DATE_FORMAT(p.paid_at, '%Y-%m') AS revenue_month,
            COALESCE(SUM(p.amount), 0) AS revenue

        FROM payments p

        INNER JOIN room_bookings rb
            ON rb.id = p.booking_id

        WHERE {$monthlyRoomWhereSql}

        GROUP BY DATE_FORMAT(p.paid_at, '%Y-%m')

        ORDER BY revenue_month ASC
    ";


    $stmt = $conn->prepare($sql);

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

        $stmt->bind_param(
            $monthlyRoomTypes,
            ...$monthlyRoomParams
        );
    }


    if (!$stmt->execute()) {

        $error = $stmt->error;
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


    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $month = $row['revenue_month'];

        if (!isset($monthlyRevenueMap[$month])) {

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
| Lead Monthly Revenue
|--------------------------------------------------------------------------
*/

if (
    $domain === 'all'
    || $domain === 'hall'
    || $domain === 'dining'
    || $domain === 'catering'
) {

    $monthlyLeadWhere = [
        "l.status = 'converted'",
        "l.converted_amount IS NOT NULL",
        "l.converted_amount >= 0",
        "l.converted_at IS NOT NULL"
    ];

    $monthlyLeadParams = [];
    $monthlyLeadTypes = '';


    if ($domain !== 'all' && $domain !== 'room') {

        $monthlyLeadWhere[] = "l.domain = ?";
        $monthlyLeadParams[] = $domain;
        $monthlyLeadTypes .= 's';
    }


    if ($fromDateSql !== null) {

        $monthlyLeadWhere[] = "l.converted_at >= ?";
        $monthlyLeadParams[] = $fromDateSql;
        $monthlyLeadTypes .= 's';
    }


    if ($toDateSql !== null) {

        $monthlyLeadWhere[] = "l.converted_at <= ?";
        $monthlyLeadParams[] = $toDateSql;
        $monthlyLeadTypes .= 's';
    }


    if ($currentUserRole === 'staff') {

        $monthlyLeadWhere[] = "l.assigned_admin_id = ?";
        $monthlyLeadParams[] = $currentUserId;
        $monthlyLeadTypes .= 'i';
    }


    $monthlyLeadWhereSql =
        implode(' AND ', $monthlyLeadWhere);


    $sql = "
        SELECT

            DATE_FORMAT(l.converted_at, '%Y-%m')
                AS revenue_month,

            l.domain,

            COALESCE(
                SUM(l.converted_amount),
                0
            ) AS revenue

        FROM leads l

        WHERE {$monthlyLeadWhereSql}

        GROUP BY
            DATE_FORMAT(l.converted_at, '%Y-%m'),
            l.domain

        ORDER BY revenue_month ASC
    ";


    $stmt = $conn->prepare($sql);

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

        $stmt->bind_param(
            $monthlyLeadTypes,
            ...$monthlyLeadParams
        );
    }


    if (!$stmt->execute()) {

        $error = $stmt->error;
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


    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $month = $row['revenue_month'];
        $leadDomain = $row['domain'];

        if (!isset($monthlyRevenueMap[$month])) {

            $monthlyRevenueMap[$month] = [
                'room' => 0.00,
                'hall' => 0.00,
                'dining' => 0.00,
                'catering' => 0.00,
                'total' => 0.00
            ];
        }


        if (isset($monthlyRevenueMap[$month][$leadDomain])) {

            $monthlyRevenueMap[$month][$leadDomain] +=
                (float)$row['revenue'];
        }
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Sort Monthly Revenue
|--------------------------------------------------------------------------
*/

ksort($monthlyRevenueMap);


/*
|--------------------------------------------------------------------------
| Calculate Monthly Total
|--------------------------------------------------------------------------
*/

foreach ($monthlyRevenueMap as $month => &$monthData) {

    $monthData['room'] =
        round((float)$monthData['room'], 2);

    $monthData['hall'] =
        round((float)$monthData['hall'], 2);

    $monthData['dining'] =
        round((float)$monthData['dining'], 2);

    $monthData['catering'] =
        round((float)$monthData['catering'], 2);

    $monthData['total'] = round(
        $monthData['room']
        + $monthData['hall']
        + $monthData['dining']
        + $monthData['catering'],
        2
    );
}

unset($monthData);


/*
|--------------------------------------------------------------------------
| Chart.js Data
|--------------------------------------------------------------------------
*/

$chartLabels = [];

$roomDataset = [];
$hallDataset = [];
$diningDataset = [];
$cateringDataset = [];
$totalDataset = [];


foreach ($monthlyRevenueMap as $month => $data) {

    $chartLabels[] = $month;

    $roomDataset[] =
        round((float)$data['room'], 2);

    $hallDataset[] =
        round((float)$data['hall'], 2);

    $diningDataset[] =
        round((float)$data['dining'], 2);

    $cateringDataset[] =
        round((float)$data['catering'], 2);

    $totalDataset[] =
        round((float)$data['total'], 2);
}


/*
|--------------------------------------------------------------------------
| 6. Revenue Records
|--------------------------------------------------------------------------
|
| Paginated combined records:
|
| ROOM  -> captured payment
| LEADS -> converted lead
|
|--------------------------------------------------------------------------
*/

$records = [];


/*
|--------------------------------------------------------------------------
| Room Records
|--------------------------------------------------------------------------
*/

if ($domain === 'all' || $domain === 'room') {

    $roomRecordWhere = [
        "p.payment_status = 'captured'",
        "rb.booking_status NOT IN ('cancelled','no_show')"
    ];

    $roomRecordParams = [];
    $roomRecordTypes = '';


    if ($fromDateSql !== null) {

        $roomRecordWhere[] = "p.paid_at >= ?";
        $roomRecordParams[] = $fromDateSql;
        $roomRecordTypes .= 's';
    }


    if ($toDateSql !== null) {

        $roomRecordWhere[] = "p.paid_at <= ?";
        $roomRecordParams[] = $toDateSql;
        $roomRecordTypes .= 's';
    }


    if ($paymentStatus !== 'all') {

        $roomRecordWhere[] = "p.payment_status = ?";
        $roomRecordParams[] = $paymentStatus;
        $roomRecordTypes .= 's';
    }


    if ($bookingStatus !== 'all') {

        $roomRecordWhere[] = "rb.booking_status = ?";
        $roomRecordParams[] = $bookingStatus;
        $roomRecordTypes .= 's';
    }


    if ($search !== '') {

        $roomRecordWhere[] = "
            (
                rb.booking_reference LIKE ?
                OR p.razorpay_payment_id LIKE ?
                OR p.razorpay_order_id LIKE ?
            )
        ";

        $searchValue = '%' . $search . '%';

        $roomRecordParams[] = $searchValue;
        $roomRecordParams[] = $searchValue;
        $roomRecordParams[] = $searchValue;

        $roomRecordTypes .= 'sss';
    }


    $roomRecordWhereSql =
        implode(' AND ', $roomRecordWhere);


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

            NULL AS customer_name

        FROM payments p

        INNER JOIN room_bookings rb
            ON rb.id = p.booking_id

        WHERE {$roomRecordWhereSql}
    ";


    $stmt = $conn->prepare($sql);

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

        $stmt->bind_param(
            $roomRecordTypes,
            ...$roomRecordParams
        );
    }


    if (!$stmt->execute()) {

        $error = $stmt->error;
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


    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $records[] = [
            'revenue_id' => (int)$row['revenue_id'],
            'domain' => 'room',
            'reference_no' => $row['reference_no'],
            'amount' => round((float)$row['amount'], 2),
            'payment_status' => $row['payment_status'],
            'booking_status' => $row['booking_status'],
            'revenue_date' => $row['revenue_date'],
            'created_at' => $row['created_at'],
            'razorpay_order_id' => $row['razorpay_order_id'],
            'razorpay_payment_id' => $row['razorpay_payment_id'],
            'booking_id' => (int)$row['booking_id'],
            'customer_id' => (int)$row['customer_id'],
            'lead_id' => null,
            'customer_name' => null
        ];
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Lead Records
|--------------------------------------------------------------------------
*/

if (
    $domain === 'all'
    || $domain === 'hall'
    || $domain === 'dining'
    || $domain === 'catering'
) {

    $leadRecordWhere = [
        "l.status = 'converted'",
        "l.converted_amount IS NOT NULL",
        "l.converted_amount >= 0"
    ];

    $leadRecordParams = [];
    $leadRecordTypes = '';


    if ($domain !== 'all' && $domain !== 'room') {

        $leadRecordWhere[] = "l.domain = ?";
        $leadRecordParams[] = $domain;
        $leadRecordTypes .= 's';
    }


    if ($fromDateSql !== null) {

        $leadRecordWhere[] = "l.converted_at >= ?";
        $leadRecordParams[] = $fromDateSql;
        $leadRecordTypes .= 's';
    }


    if ($toDateSql !== null) {

        $leadRecordWhere[] = "l.converted_at <= ?";
        $leadRecordParams[] = $toDateSql;
        $leadRecordTypes .= 's';
    }


    if ($currentUserRole === 'staff') {

        $leadRecordWhere[] = "l.assigned_admin_id = ?";
        $leadRecordParams[] = $currentUserId;
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

        $searchValue = '%' . $search . '%';

        $leadRecordParams[] = $searchValue;
        $leadRecordParams[] = $searchValue;
        $leadRecordParams[] = $searchValue;
        $leadRecordParams[] = $searchValue;

        $leadRecordTypes .= 'ssss';
    }


    $leadRecordWhereSql =
        implode(' AND ', $leadRecordWhere);


    $sql = "
        SELECT

            l.id AS revenue_id,
            l.domain,

            l.lead_reference AS reference_no,

            l.converted_amount AS amount,

            'converted' AS payment_status,

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


    $stmt = $conn->prepare($sql);

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

        $stmt->bind_param(
            $leadRecordTypes,
            ...$leadRecordParams
        );
    }


    if (!$stmt->execute()) {

        $error = $stmt->error;
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


    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $records[] = [
            'revenue_id' => (int)$row['revenue_id'],
            'domain' => $row['domain'],
            'reference_no' => $row['reference_no'],
            'amount' => round((float)$row['amount'], 2),
            'payment_status' => 'converted',
            'booking_status' => $row['booking_status'],
            'revenue_date' => $row['revenue_date'],
            'created_at' => $row['created_at'],
            'razorpay_order_id' => null,
            'razorpay_payment_id' => null,
            'booking_id' => null,
            'customer_id' => null,
            'lead_id' => (int)$row['lead_id'],
            'customer_name' => $row['customer_name']
        ];
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| 7. Sort Combined Records
|--------------------------------------------------------------------------
*/

usort(
    $records,
    function (array $a, array $b) use ($sortBy, $sortOrder): int {

        $valueA = null;
        $valueB = null;

        switch ($sortBy) {

            case 'amount':

                $valueA = (float)$a['amount'];
                $valueB = (float)$b['amount'];

                break;

            case 'reference':

                $valueA = strtolower(
                    (string)$a['reference_no']
                );

                $valueB = strtolower(
                    (string)$b['reference_no']
                );

                break;

            case 'domain':

                $valueA = strtolower(
                    (string)$a['domain']
                );

                $valueB = strtolower(
                    (string)$b['domain']
                );

                break;

            case 'date':
            default:

                $valueA = strtotime(
                    (string)$a['revenue_date']
                );

                $valueB = strtotime(
                    (string)$b['revenue_date']
                );

                break;
        }


        if ($valueA == $valueB) {
            return 0;
        }


        $comparison = ($valueA < $valueB) ? -1 : 1;

        return $sortOrder === 'asc'
            ? $comparison
            : -$comparison;
    }
);


/*
|--------------------------------------------------------------------------
| 8. Pagination
|--------------------------------------------------------------------------
*/

$totalRecords = count($records);

$totalPages = $totalRecords > 0
    ? (int)ceil($totalRecords / $limit)
    : 0;


if ($page > max(1, $totalPages) && $totalRecords > 0) {

    sendResponse(
        false,
        'Requested page does not exist.',
        [
            'page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords
        ],
        422
    );
}


$offset = ($page - 1) * $limit;

$paginatedRecords = array_slice(
    $records,
    $offset,
    $limit
);


/*
|--------------------------------------------------------------------------
| 9. Recent Revenue
|--------------------------------------------------------------------------
*/

$recentRevenue = array_slice(
    $records,
    0,
    10
);


/*
|--------------------------------------------------------------------------
| 10. Highest Revenue Records
|--------------------------------------------------------------------------
*/

$topRevenueRecords = $records;

usort(
    $topRevenueRecords,
    function (array $a, array $b): int {

        return (float)$b['amount'] <=> (float)$a['amount'];
    }
);

$topRevenueRecords = array_slice(
    $topRevenueRecords,
    0,
    10
);


/*
|--------------------------------------------------------------------------
| 11. Domain Chart
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
                round($revenueByDomain['room'], 2),
                round($revenueByDomain['hall'], 2),
                round($revenueByDomain['dining'], 2),
                round($revenueByDomain['catering'], 2)
            ]
        ]
    ]
];


/*
|--------------------------------------------------------------------------
| 12. Payment Summary
|--------------------------------------------------------------------------
*/

$paymentSummary = [
    'room_collected' =>
        round($roomRevenue['total_collected'], 2),

    'room_refunded' =>
        round($roomRevenue['refunded_amount'], 2),

    'room_failed' =>
        round($roomRevenue['failed_amount'], 2),

    'room_pending' =>
        round($roomRevenue['pending_amount'], 2),

    'lead_converted' =>
        round($leadRevenue['total_converted'], 2)
];


/*
|--------------------------------------------------------------------------
| 13. Revenue Breakdown
|--------------------------------------------------------------------------
*/

$breakdown = [

    'room' => [
        'revenue_type' => 'actual_collected_payment',
        'amount' => round(
            $roomRevenue['total_collected'],
            2
        ),
        'payment_count' =>
            $roomRevenue['captured_payments'],
        'booking_count' =>
            $roomRevenue['booking_count']
    ],

    'hall' => [
        'revenue_type' => 'converted_lead_value',
        'amount' =>
            round($leadRevenue['hall'], 2)
    ],

    'dining' => [
        'revenue_type' => 'converted_lead_value',
        'amount' =>
            round($leadRevenue['dining'], 2)
    ],

    'catering' => [
        'revenue_type' => 'converted_lead_value',
        'amount' =>
            round($leadRevenue['catering'], 2)
    ]
];


/*
|--------------------------------------------------------------------------
| 14. Final Response
|--------------------------------------------------------------------------
*/

sendResponse(
    true,
    'Revenue data fetched successfully.',
    [

        /*
        |--------------------------------------------------------------------------
        | Meta
        |--------------------------------------------------------------------------
        */

        'meta' => [

            'currency' => $currency,

            'currency_symbol' =>
                $currencySymbol,

            'generated_at' =>
                date('Y-m-d H:i:s'),

            'api' =>
                'dashboard/revenue.php',

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

            'lead_scope' =>
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
                $fromDate,

            'to_date' =>
                $toDate,

            'domain' =>
                $domain,

            'payment_status' =>
                $paymentStatus,

            'booking_status' =>
                $bookingStatus,

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
                )
        ],


        /*
        |--------------------------------------------------------------------------
        | Room Revenue
        |--------------------------------------------------------------------------
        */

        'room_revenue' => $roomRevenue,


        /*
        |--------------------------------------------------------------------------
        | Lead Revenue
        |--------------------------------------------------------------------------
        */

        'lead_revenue' => $leadRevenue,


        /*
        |--------------------------------------------------------------------------
        | Revenue Breakdown
        |--------------------------------------------------------------------------
        */

        'breakdown' => $breakdown,


        /*
        |--------------------------------------------------------------------------
        | Payment Summary
        |--------------------------------------------------------------------------
        */

        'payment_summary' => $paymentSummary,


        /*
        |--------------------------------------------------------------------------
        | Domain Revenue
        |--------------------------------------------------------------------------
        */

        'revenue_by_domain' => $revenueByDomain,


        /*
        |--------------------------------------------------------------------------
        | Chart.js
        |--------------------------------------------------------------------------
        */

        'charts' => [

            /*
            | Monthly revenue
            */

            'monthly_revenue' => [

                'labels' =>
                    $chartLabels,

                'datasets' => [

                    [
                        'label' => 'Room Revenue',
                        'data' => $roomDataset
                    ],

                    [
                        'label' => 'Hall Revenue',
                        'data' => $hallDataset
                    ],

                    [
                        'label' => 'Dining Revenue',
                        'data' => $diningDataset
                    ],

                    [
                        'label' => 'Catering Revenue',
                        'data' => $cateringDataset
                    ],

                    [
                        'label' => 'Total Revenue',
                        'data' => $totalDataset
                    ]
                ]
            ],


            /*
            | Domain revenue
            */

            'revenue_by_domain' =>
                $domainChart
        ],


        /*
        |--------------------------------------------------------------------------
        | Monthly Raw Data
        |--------------------------------------------------------------------------
        */

        'monthly' => $monthlyRevenueMap,


        /*
        |--------------------------------------------------------------------------
        | Paginated Revenue Records
        |--------------------------------------------------------------------------
        */

        'records' => $paginatedRecords,


        /*
        |--------------------------------------------------------------------------
        | Recent Revenue
        |--------------------------------------------------------------------------
        */

        'recent' => $recentRevenue,


        /*
        |--------------------------------------------------------------------------
        | Top Revenue
        |--------------------------------------------------------------------------
        */

        'top' => $topRevenueRecords,


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