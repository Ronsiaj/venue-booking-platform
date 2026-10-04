<?php

declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode([
        "status" => true,
        "message" => "CORS preflight successful."
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);

    echo json_encode([
        "status" => false,
        "message" => "Only GET method is allowed."
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Config / DB / Middleware
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../config/middleware.php';

/*
|--------------------------------------------------------------------------
| Helper Response
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
        "status" => $status,
        "message" => $message,
        "data" => $data
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

/*
|--------------------------------------------------------------------------
| Admin Authentication
|--------------------------------------------------------------------------
*/

$currentUser = requireAdmin();

/*
|--------------------------------------------------------------------------
| Read Query Parameters
|--------------------------------------------------------------------------
*/

$staffId       = $_GET['staff_id'] ?? null;
$search        = trim((string)($_GET['search'] ?? ''));
$status        = strtolower(trim((string)($_GET['status'] ?? 'all')));
$page          = $_GET['page'] ?? 1;
$limit         = $_GET['limit'] ?? 20;
$sortBy        = strtolower(trim((string)($_GET['sort_by'] ?? 'created_at')));
$sortOrder     = strtoupper(trim((string)($_GET['sort_order'] ?? 'DESC')));

/*
|--------------------------------------------------------------------------
| Validate staff_id
|--------------------------------------------------------------------------
*/

if ($staffId !== null && $staffId !== '') {

    if (
        !ctype_digit((string)$staffId) ||
        (int)$staffId <= 0
    ) {
        sendResponse(
            false,
            "Invalid staff_id. staff_id must be a positive integer.",
            [],
            422
        );
    }

    $staffId = (int)$staffId;
} else {
    $staffId = null;
}

/*
|--------------------------------------------------------------------------
| Validate Search
|--------------------------------------------------------------------------
*/

if (mb_strlen($search) > 100) {
    sendResponse(
        false,
        "Search cannot exceed 100 characters.",
        [],
        422
    );
}

/*
|--------------------------------------------------------------------------
| Validate Status
|--------------------------------------------------------------------------
|
| Depending on your admin_users table, normally:
| active / inactive
|
| all = both
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    'all',
    'active',
    'inactive'
];

if (!in_array($status, $allowedStatuses, true)) {
    sendResponse(
        false,
        "Invalid status. Allowed values: all, active, inactive.",
        [],
        422
    );
}

/*
|--------------------------------------------------------------------------
| Validate Page
|--------------------------------------------------------------------------
*/

if (
    !ctype_digit((string)$page) ||
    (int)$page < 1
) {
    sendResponse(
        false,
        "Invalid page. Page must be a positive integer.",
        [],
        422
    );
}

$page = (int)$page;

/*
|--------------------------------------------------------------------------
| Validate Limit
|--------------------------------------------------------------------------
*/

if (
    !ctype_digit((string)$limit) ||
    (int)$limit < 1
) {
    sendResponse(
        false,
        "Invalid limit. Limit must be a positive integer.",
        [],
        422
    );
}

$limit = (int)$limit;

if ($limit > 100) {
    $limit = 100;
}

/*
|--------------------------------------------------------------------------
| Validate Sort Order
|--------------------------------------------------------------------------
*/

$allowedSortOrders = [
    'ASC',
    'DESC'
];

if (!in_array($sortOrder, $allowedSortOrders, true)) {
    sendResponse(
        false,
        "Invalid sort_order. Allowed values: ASC or DESC.",
        [],
        422
    );
}

/*
|--------------------------------------------------------------------------
| Validate Sort By
|--------------------------------------------------------------------------
|
| Do NOT allow raw user input directly in ORDER BY.
|--------------------------------------------------------------------------
*/

$allowedSortColumns = [
    'id'         => 'au.id',
    'name'       => 'au.name',
    'email'      => 'au.email',
    'phone'      => 'au.phone',
    'status'     => 'au.status',
    'created_at' => 'au.created_at',
    'updated_at' => 'au.updated_at'
];

if (!array_key_exists($sortBy, $allowedSortColumns)) {
    sendResponse(
        false,
        "Invalid sort_by. Allowed values: id, name, email, phone, status, created_at, updated_at.",
        [],
        422
    );
}

$orderColumn = $allowedSortColumns[$sortBy];

/*
|--------------------------------------------------------------------------
| If staff_id is provided
|--------------------------------------------------------------------------
| Return ONE complete staff record.
|--------------------------------------------------------------------------
*/

if ($staffId !== null) {

    /*
    |--------------------------------------------------------------------------
    | Get Staff
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            au.id,
            au.name,
            au.email,
            au.phone,
            au.role,
            au.status,
            au.created_at,
            au.updated_at
        FROM admin_users au
        WHERE au.id = ?
          AND LOWER(TRIM(au.role)) = 'staff'
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        sendResponse(
            false,
            "Failed to prepare staff query.",
            [],
            500
        );
    }

    $stmt->bind_param("i", $staffId);

    if (!$stmt->execute()) {
        $stmt->close();

        sendResponse(
            false,
            "Failed to fetch staff details.",
            [],
            500
        );
    }

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();

        sendResponse(
            false,
            "Staff not found.",
            [],
            404
        );
    }

    $staff = $result->fetch_assoc();

    $stmt->close();

    /*
    |--------------------------------------------------------------------------
    | Assigned Lead Summary
    |--------------------------------------------------------------------------
    */

    $leadSummary = [
        "total"       => 0,
        "new"         => 0,
        "contacted"   => 0,
        "follow_up"   => 0,
        "converted"   => 0,
        "lost"        => 0,
        "cancelled"   => 0
    ];

    $leadSql = "
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) AS new_count,
            SUM(CASE WHEN status = 'contacted' THEN 1 ELSE 0 END) AS contacted_count,
            SUM(CASE WHEN status = 'follow_up' THEN 1 ELSE 0 END) AS follow_up_count,
            SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) AS converted_count,
            SUM(CASE WHEN status = 'lost' THEN 1 ELSE 0 END) AS lost_count,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count
        FROM leads
        WHERE assigned_admin_id = ?
    ";

    $leadStmt = $conn->prepare($leadSql);

    if ($leadStmt) {

        $leadStmt->bind_param("i", $staffId);

        if ($leadStmt->execute()) {

            $leadResult = $leadStmt->get_result();

            if ($leadRow = $leadResult->fetch_assoc()) {

                $leadSummary = [
                    "total"       => (int)($leadRow['total'] ?? 0),
                    "new"         => (int)($leadRow['new_count'] ?? 0),
                    "contacted"   => (int)($leadRow['contacted_count'] ?? 0),
                    "follow_up"   => (int)($leadRow['follow_up_count'] ?? 0),
                    "converted"   => (int)($leadRow['converted_count'] ?? 0),
                    "lost"        => (int)($leadRow['lost_count'] ?? 0),
                    "cancelled"   => (int)($leadRow['cancelled_count'] ?? 0)
                ];
            }
        }

        $leadStmt->close();
    }

    /*
    |--------------------------------------------------------------------------
    | Converted Revenue
    |--------------------------------------------------------------------------
    */

    $revenueSql = "
        SELECT
            COALESCE(SUM(converted_amount), 0) AS converted_revenue,
            COALESCE(SUM(estimated_amount), 0) AS estimated_revenue
        FROM leads
        WHERE assigned_admin_id = ?
    ";

    $revenueStmt = $conn->prepare($revenueSql);

    $convertedRevenue = 0.00;
    $estimatedRevenue = 0.00;

    if ($revenueStmt) {

        $revenueStmt->bind_param("i", $staffId);

        if ($revenueStmt->execute()) {

            $revenueResult = $revenueStmt->get_result();

            if ($revenueRow = $revenueResult->fetch_assoc()) {

                $convertedRevenue = (float)($revenueRow['converted_revenue'] ?? 0);
                $estimatedRevenue = (float)($revenueRow['estimated_revenue'] ?? 0);
            }
        }

        $revenueStmt->close();
    }

    /*
    |--------------------------------------------------------------------------
    | Notification Summary
    |--------------------------------------------------------------------------
    */

    $notificationSummary = [
        "total"  => 0,
        "sent"   => 0,
        "pending" => 0,
        "failed" => 0
    ];

    $notificationSql = "
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN ln.status = 'sent' THEN 1 ELSE 0 END) AS sent_count,
            SUM(CASE WHEN ln.status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN ln.status = 'failed' THEN 1 ELSE 0 END) AS failed_count
        FROM lead_notifications ln
        INNER JOIN leads l
            ON l.id = ln.lead_id
        WHERE l.assigned_admin_id = ?
    ";

    $notificationStmt = $conn->prepare($notificationSql);

    if ($notificationStmt) {

        $notificationStmt->bind_param("i", $staffId);

        if ($notificationStmt->execute()) {

            $notificationResult = $notificationStmt->get_result();

            if ($notificationRow = $notificationResult->fetch_assoc()) {

                $notificationSummary = [
                    "total"   => (int)($notificationRow['total'] ?? 0),
                    "sent"    => (int)($notificationRow['sent_count'] ?? 0),
                    "pending" => (int)($notificationRow['pending_count'] ?? 0),
                    "failed"  => (int)($notificationRow['failed_count'] ?? 0)
                ];
            }
        }

        $notificationStmt->close();
    }

    /*
    |--------------------------------------------------------------------------
    | Recent Assigned Leads
    |--------------------------------------------------------------------------
    */

    $recentLeads = [];

    $recentLeadSql = "
        SELECT
            id,
            lead_reference,
            domain,
            customer_name,
            customer_phone,
            customer_email,
            event_date,
            guest_count,
            status,
            estimated_amount,
            converted_amount,
            created_at,
            updated_at
        FROM leads
        WHERE assigned_admin_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ";

    $recentStmt = $conn->prepare($recentLeadSql);

    if ($recentStmt) {

        $recentStmt->bind_param("i", $staffId);

        if ($recentStmt->execute()) {

            $recentResult = $recentStmt->get_result();

            while ($row = $recentResult->fetch_assoc()) {

                $row['id'] = (int)$row['id'];
                $row['guest_count'] = $row['guest_count'] !== null
                    ? (int)$row['guest_count']
                    : null;

                $row['estimated_amount'] = $row['estimated_amount'] !== null
                    ? (float)$row['estimated_amount']
                    : null;

                $row['converted_amount'] = $row['converted_amount'] !== null
                    ? (float)$row['converted_amount']
                    : null;

                $recentLeads[] = $row;
            }
        }

        $recentStmt->close();
    }

    /*
    |--------------------------------------------------------------------------
    | Final Staff View Response
    |--------------------------------------------------------------------------
    */

    $staffData = [
        "staff" => [
            "id"         => (int)$staff['id'],
            "name"       => $staff['name'],
            "email"      => $staff['email'],
            "phone"      => $staff['phone'],
            "role"       => $staff['role'],
            "status"     => $staff['status'],
            "created_at" => $staff['created_at'],
            "updated_at" => $staff['updated_at']
        ],

        "lead_summary" => $leadSummary,

        "revenue_summary" => [
            "estimated_revenue" => round($estimatedRevenue, 2),
            "converted_revenue" => round($convertedRevenue, 2)
        ],

        "notification_summary" => $notificationSummary,

        "recent_leads" => $recentLeads
    ];

    sendResponse(
        true,
        "Staff details fetched successfully.",
        $staffData,
        200
    );
}

/*
|--------------------------------------------------------------------------
| STAFF LIST
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Build WHERE Conditions
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];
$types = "";

/*
|--------------------------------------------------------------------------
| Only Staff
|--------------------------------------------------------------------------
*/

$where[] = "LOWER(TRIM(au.role)) = 'staff'";

/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/

if ($status !== 'all') {

    $where[] = "LOWER(TRIM(au.status)) = ?";

    $params[] = $status;
    $types .= "s";
}

/*
|--------------------------------------------------------------------------
| Search Filter
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $searchValue = "%" . $search . "%";

    $where[] = "
        (
            au.name LIKE ?
            OR au.email LIKE ?
            OR au.phone LIKE ?
        )
    ";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= "sss";
}

/*
|--------------------------------------------------------------------------
| WHERE SQL
|--------------------------------------------------------------------------
*/

$whereSql = "";

if (!empty($where)) {
    $whereSql = "WHERE " . implode(" AND ", $where);
}

/*
|--------------------------------------------------------------------------
| Count Total Staff
|--------------------------------------------------------------------------
*/

$countSql = "
    SELECT COUNT(*) AS total
    FROM admin_users au
    $whereSql
";

$countStmt = $conn->prepare($countSql);

if (!$countStmt) {
    sendResponse(
        false,
        "Failed to prepare staff count query.",
        [],
        500
    );
}

if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}

if (!$countStmt->execute()) {

    $countStmt->close();

    sendResponse(
        false,
        "Failed to count staff records.",
        [],
        500
    );
}

$countResult = $countStmt->get_result();
$countRow = $countResult->fetch_assoc();

$totalStaff = (int)($countRow['total'] ?? 0);

$countStmt->close();

/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

$totalPages = $totalStaff > 0
    ? (int)ceil($totalStaff / $limit)
    : 0;

if ($page > 1 && $totalPages > 0 && $page > $totalPages) {

    sendResponse(
        false,
        "Requested page does not exist.",
        [
            "page" => $page,
            "total_pages" => $totalPages
        ],
        422
    );
}

$offset = ($page - 1) * $limit;

/*
|--------------------------------------------------------------------------
| Staff List Query
|--------------------------------------------------------------------------
*/

$listSql = "
    SELECT
        au.id,
        au.name,
        au.email,
        au.phone,
        au.role,
        au.status,
        au.created_at,
        au.updated_at,

        COUNT(DISTINCT l.id) AS assigned_leads,

        COUNT(
            DISTINCT CASE
                WHEN l.status = 'converted'
                THEN l.id
            END
        ) AS converted_leads,

        COALESCE(
            SUM(
                CASE
                    WHEN l.status = 'converted'
                    THEN l.converted_amount
                    ELSE 0
                END
            ),
            0
        ) AS converted_revenue

    FROM admin_users au

    LEFT JOIN leads l
        ON l.assigned_admin_id = au.id

    $whereSql

    GROUP BY
        au.id,
        au.name,
        au.email,
        au.phone,
        au.role,
        au.status,
        au.created_at,
        au.updated_at

    ORDER BY $orderColumn $sortOrder

    LIMIT ? OFFSET ?
";

/*
|--------------------------------------------------------------------------
| Add Pagination Params
|--------------------------------------------------------------------------
*/

$listParams = $params;
$listTypes = $types . "ii";

$listParams[] = $limit;
$listParams[] = $offset;

/*
|--------------------------------------------------------------------------
| Prepare List
|--------------------------------------------------------------------------
*/

$listStmt = $conn->prepare($listSql);

if (!$listStmt) {
    sendResponse(
        false,
        "Failed to prepare staff list query.",
        [],
        500
    );
}

/*
|--------------------------------------------------------------------------
| Bind Dynamic Parameters
|--------------------------------------------------------------------------
*/

$listStmt->bind_param(
    $listTypes,
    ...$listParams
);

/*
|--------------------------------------------------------------------------
| Execute
|--------------------------------------------------------------------------
*/

if (!$listStmt->execute()) {

    $listStmt->close();

    sendResponse(
        false,
        "Failed to fetch staff list.",
        [],
        500
    );
}

$listResult = $listStmt->get_result();

$staffList = [];

while ($row = $listResult->fetch_assoc()) {

    $staffList[] = [
        "id" => (int)$row['id'],
        "name" => $row['name'],
        "email" => $row['email'],
        "phone" => $row['phone'],
        "role" => $row['role'],
        "status" => $row['status'],

        "assigned_leads" => (int)($row['assigned_leads'] ?? 0),

        "converted_leads" => (int)($row['converted_leads'] ?? 0),

        "converted_revenue" => round(
            (float)($row['converted_revenue'] ?? 0),
            2
        ),

        "created_at" => $row['created_at'],
        "updated_at" => $row['updated_at']
    ];
}

$listStmt->close();

/*
|--------------------------------------------------------------------------
| Final Response
|--------------------------------------------------------------------------
*/

sendResponse(
    true,
    "Staff list fetched successfully.",
    [
        "staff" => $staffList,

        "filters" => [
            "search" => $search !== '' ? $search : null,
            "status" => $status,
            "sort_by" => $sortBy,
            "sort_order" => $sortOrder
        ],

        "pagination" => [
            "current_page" => $page,
            "per_page" => $limit,
            "total_records" => $totalStaff,
            "total_pages" => $totalPages,
            "has_next_page" => $page < $totalPages,
            "has_previous_page" => $page > 1
        ]
    ],
    200
);