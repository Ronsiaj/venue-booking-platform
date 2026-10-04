<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SM GROUP
| Dashboard Lead API
|--------------------------------------------------------------------------
|
| Endpoint:
| GET /dashboard/lead.php
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
        'You are not authorized to access lead dashboard.',
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

    return (
        $parsed !== false
        && $parsed->format('Y-m-d') === $date
    );
}


/*
|--------------------------------------------------------------------------
| Helper - Execute Statement
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

        $error =
            $stmt->error;

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
| Lead ID
|--------------------------------------------------------------------------
*/

$leadId = isset($_GET['lead_id'])
    ? filter_var(
        $_GET['lead_id'],
        FILTER_VALIDATE_INT
    )
    : null;


/*
|--------------------------------------------------------------------------
| Domain
|--------------------------------------------------------------------------
*/

$domain = isset($_GET['domain'])
    ? strtolower(
        trim(
            (string)$_GET['domain']
        )
    )
    : 'all';


/*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
*/

$status = isset($_GET['status'])
    ? strtolower(
        trim(
            (string)$_GET['status']
        )
    )
    : 'all';


/*
|--------------------------------------------------------------------------
| Source
|--------------------------------------------------------------------------
*/

$source = isset($_GET['source'])
    ? strtolower(
        trim(
            (string)$_GET['source']
        )
    )
    : 'all';


/*
|--------------------------------------------------------------------------
| Assigned Admin
|--------------------------------------------------------------------------
*/

$assignedAdminId = isset($_GET['assigned_admin_id'])
    ? filter_var(
        $_GET['assigned_admin_id'],
        FILTER_VALIDATE_INT
    )
    : null;


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
| Event Date
|--------------------------------------------------------------------------
*/

$eventDate = isset($_GET['event_date'])
    ? trim(
        (string)$_GET['event_date']
    )
    : null;


/*
|--------------------------------------------------------------------------
| Date Range
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
| Domain Validation
|--------------------------------------------------------------------------
*/

$allowedDomains = [
    'all',
    'hall',
    'dining',
    'catering'
];


if (
    !in_array(
        $domain,
        $allowedDomains,
        true
    )
) {

    $errors['domain'] =
        'Invalid domain. Allowed values: hall, dining, catering.';
}


/*
|--------------------------------------------------------------------------
| Status Validation
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    'all',
    'new',
    'contacted',
    'follow_up',
    'converted',
    'lost',
    'cancelled'
];


if (
    !in_array(
        $status,
        $allowedStatuses,
        true
    )
) {

    $errors['status'] =
        'Invalid status.';
}


/*
|--------------------------------------------------------------------------
| Source Validation
|--------------------------------------------------------------------------
*/

$allowedSources = [
    'all',
    'website',
    'whatsapp',
    'phone',
    'admin',
    'other'
];


if (
    !in_array(
        $source,
        $allowedSources,
        true
    )
) {

    $errors['source'] =
        'Invalid source.';
}


/*
|--------------------------------------------------------------------------
| Lead ID Validation
|--------------------------------------------------------------------------
*/

if (
    $leadId !== null
    && (
        $leadId === false
        || $leadId < 1
    )
) {

    $errors['lead_id'] =
        'lead_id must be a positive integer.';
}


/*
|--------------------------------------------------------------------------
| Assigned Admin Validation
|--------------------------------------------------------------------------
*/

if (
    $assignedAdminId !== null
    && (
        $assignedAdminId === false
        || $assignedAdminId < 1
    )
) {

    $errors['assigned_admin_id'] =
        'assigned_admin_id must be a positive integer.';
}


/*
|--------------------------------------------------------------------------
| Date Validation
|--------------------------------------------------------------------------
*/

if (
    $eventDate !== null
    && $eventDate !== ''
    && !isValidDate($eventDate)
) {

    $errors['event_date'] =
        'event_date must be in YYYY-MM-DD format.';
}


if (
    $fromDate !== null
    && $fromDate !== ''
    && !isValidDate($fromDate)
) {

    $errors['from_date'] =
        'from_date must be in YYYY-MM-DD format.';
}


if (
    $toDate !== null
    && $toDate !== ''
    && !isValidDate($toDate)
) {

    $errors['to_date'] =
        'to_date must be in YYYY-MM-DD format.';
}


if (
    $fromDate
    && $toDate
    && $fromDate > $toDate
) {

    $errors['date_range'] =
        'from_date cannot be greater than to_date.';
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
            'min_amount must be a valid positive amount.';
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
            'max_amount must be a valid positive amount.';
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
    'event_date',
    'customer_name',
    'status',
    'estimated_amount',
    'converted_amount',
    'lead_reference'
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
| Staff Scope Validation
|--------------------------------------------------------------------------
|
| Staff can only see leads assigned to themselves.
|
*/

if (
    $currentUserRole === 'staff'
    && $assignedAdminId !== null
    && (int)$assignedAdminId !== $currentUserId
) {

    $errors['assigned_admin_id'] =
        'Staff users can only access their own assigned leads.';
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


$page = (int)$page;

$limit = (int)$limit;


/*
|--------------------------------------------------------------------------
| Normalize Filters
|--------------------------------------------------------------------------
*/

$eventDate =
    ($eventDate !== null && $eventDate !== '')
        ? $eventDate
        : null;


$fromDate =
    ($fromDate !== null && $fromDate !== '')
        ? $fromDate
        : null;


$toDate =
    ($toDate !== null && $toDate !== '')
        ? $toDate
        : null;


$minAmount =
    ($minAmount !== null && $minAmount !== '')
        ? (float)$minAmount
        : null;


$maxAmount =
    ($maxAmount !== null && $maxAmount !== '')
        ? (float)$maxAmount
        : null;


/*
|--------------------------------------------------------------------------
| Build Common WHERE
|--------------------------------------------------------------------------
*/

$where = [];

$params = [];

$types = '';


/*
|--------------------------------------------------------------------------
| Specific Lead
|--------------------------------------------------------------------------
*/

if ($leadId !== null) {

    $where[] =
        'l.id = ?';

    $params[] =
        (int)$leadId;

    $types .= 'i';
}


/*
|--------------------------------------------------------------------------
| Domain
|--------------------------------------------------------------------------
*/

if ($domain !== 'all') {

    $where[] =
        'l.domain = ?';

    $params[] =
        $domain;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
*/

if ($status !== 'all') {

    $where[] =
        'l.status = ?';

    $params[] =
        $status;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Source
|--------------------------------------------------------------------------
*/

if ($source !== 'all') {

    $where[] =
        'l.source = ?';

    $params[] =
        $source;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Staff Scope
|--------------------------------------------------------------------------
*/

if (
    $currentUserRole === 'staff'
) {

    $where[] =
        'l.assigned_admin_id = ?';

    $params[] =
        $currentUserId;

    $types .= 'i';
}


/*
|--------------------------------------------------------------------------
| Admin Assignment Filter
|--------------------------------------------------------------------------
*/

if (
    $assignedAdminId !== null
    && $currentUserRole !== 'staff'
) {

    $where[] =
        'l.assigned_admin_id = ?';

    $params[] =
        (int)$assignedAdminId;

    $types .= 'i';
}


/*
|--------------------------------------------------------------------------
| Event Date
|--------------------------------------------------------------------------
*/

if ($eventDate !== null) {

    $where[] =
        'l.event_date = ?';

    $params[] =
        $eventDate;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| From Date
|--------------------------------------------------------------------------
*/

if ($fromDate !== null) {

    $where[] =
        'l.created_at >= ?';

    $params[] =
        $fromDate . ' 00:00:00';

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| To Date
|--------------------------------------------------------------------------
*/

if ($toDate !== null) {

    $where[] =
        'l.created_at <= ?';

    $params[] =
        $toDate . ' 23:59:59';

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Minimum Amount
|--------------------------------------------------------------------------
*/

if ($minAmount !== null) {

    $where[] =
        'COALESCE(l.converted_amount, l.estimated_amount, 0) >= ?';

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
        'COALESCE(l.converted_amount, l.estimated_amount, 0) <= ?';

    $params[] =
        $maxAmount;

    $types .= 'd';
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
            l.lead_reference LIKE ?

            OR l.customer_name LIKE ?

            OR l.customer_phone LIKE ?

            OR l.customer_email LIKE ?

            OR l.requirement LIKE ?
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


    $types .= 'sssss';
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
| Total Leads
|--------------------------------------------------------------------------
*/

$totalLeads = 0;


$sql = "
    SELECT
        COUNT(*) AS total

    FROM leads l

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

    $totalLeads =
        (int)$row['total'];
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| KPI Summary
|--------------------------------------------------------------------------
*/

$summary = [

    'total' => 0,

    'new' => 0,

    'contacted' => 0,

    'follow_up' => 0,

    'converted' => 0,

    'lost' => 0,

    'cancelled' => 0,

    'total_estimated_amount' => 0.00,

    'total_converted_amount' => 0.00,

    'total_open_value' => 0.00,

    'average_estimated_amount' => 0.00,

    'average_converted_amount' => 0.00,

    'conversion_rate' => 0.00,

    'total_guests' => 0
];


$sql = "
    SELECT

        COUNT(*) AS total,

        COALESCE(
            SUM(
                CASE
                    WHEN l.status = 'new'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS new_count,

        COALESCE(
            SUM(
                CASE
                    WHEN l.status = 'contacted'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS contacted_count,

        COALESCE(
            SUM(
                CASE
                    WHEN l.status = 'follow_up'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS follow_up_count,

        COALESCE(
            SUM(
                CASE
                    WHEN l.status = 'converted'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS converted_count,

        COALESCE(
            SUM(
                CASE
                    WHEN l.status = 'lost'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS lost_count,

        COALESCE(
            SUM(
                CASE
                    WHEN l.status = 'cancelled'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS cancelled_count,

        COALESCE(
            SUM(
                COALESCE(
                    l.estimated_amount,
                    0
                )
            ),
            0
        ) AS estimated_total,

        COALESCE(
            SUM(
                CASE
                    WHEN l.status = 'converted'
                    THEN COALESCE(
                        l.converted_amount,
                        0
                    )
                    ELSE 0
                END
            ),
            0
        ) AS converted_total,

        COALESCE(
            SUM(
                CASE
                    WHEN l.status IN (
                        'new',
                        'contacted',
                        'follow_up'
                    )
                    THEN COALESCE(
                        l.estimated_amount,
                        0
                    )
                    ELSE 0
                END
            ),
            0
        ) AS open_value,

        COALESCE(
            SUM(
                COALESCE(
                    l.guest_count,
                    0
                )
            ),
            0
        ) AS total_guests

    FROM leads l

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

    $summary['total'] =
        (int)$row['total'];

    $summary['new'] =
        (int)$row['new_count'];

    $summary['contacted'] =
        (int)$row['contacted_count'];

    $summary['follow_up'] =
        (int)$row['follow_up_count'];

    $summary['converted'] =
        (int)$row['converted_count'];

    $summary['lost'] =
        (int)$row['lost_count'];

    $summary['cancelled'] =
        (int)$row['cancelled_count'];

    $summary['total_estimated_amount'] =
        round(
            (float)$row['estimated_total'],
            2
        );

    $summary['total_converted_amount'] =
        round(
            (float)$row['converted_total'],
            2
        );

    $summary['total_open_value'] =
        round(
            (float)$row['open_value'],
            2
        );

    $summary['total_guests'] =
        (int)$row['total_guests'];
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| Average Estimated Amount
|--------------------------------------------------------------------------
*/

if ($summary['total'] > 0) {

    $summary['average_estimated_amount'] =
        round(
            $summary['total_estimated_amount']
            /
            $summary['total'],
            2
        );
}


/*
|--------------------------------------------------------------------------
| Average Converted Amount
|--------------------------------------------------------------------------
*/

if ($summary['converted'] > 0) {

    $summary['average_converted_amount'] =
        round(
            $summary['total_converted_amount']
            /
            $summary['converted'],
            2
        );
}


/*
|--------------------------------------------------------------------------
| Conversion Rate
|--------------------------------------------------------------------------
*/

if ($summary['total'] > 0) {

    $summary['conversion_rate'] =
        round(
            (
                $summary['converted']
                /
                $summary['total']
            ) * 100,
            2
        );
}


/*
|--------------------------------------------------------------------------
| Domain Summary
|--------------------------------------------------------------------------
*/

$domainSummary = [

    'hall' => [
        'count' => 0,
        'estimated_amount' => 0.00,
        'converted_amount' => 0.00,
        'converted_count' => 0
    ],

    'dining' => [
        'count' => 0,
        'estimated_amount' => 0.00,
        'converted_amount' => 0.00,
        'converted_count' => 0
    ],

    'catering' => [
        'count' => 0,
        'estimated_amount' => 0.00,
        'converted_amount' => 0.00,
        'converted_count' => 0
    ]
];


$sql = "
    SELECT

        l.domain,

        COUNT(*) AS lead_count,

        COALESCE(
            SUM(
                COALESCE(
                    l.estimated_amount,
                    0
                )
            ),
            0
        ) AS estimated_amount,

        COALESCE(
            SUM(
                CASE
                    WHEN l.status = 'converted'
                    THEN COALESCE(
                        l.converted_amount,
                        0
                    )
                    ELSE 0
                END
            ),
            0
        ) AS converted_amount,

        COALESCE(
            SUM(
                CASE
                    WHEN l.status = 'converted'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS converted_count

    FROM leads l

    {$whereSql}

    GROUP BY l.domain

    ORDER BY l.domain ASC
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


while ($row = $result->fetch_assoc()) {

    $domainName =
        $row['domain'];


    if (
        isset(
            $domainSummary[$domainName]
        )
    ) {

        $domainSummary[$domainName] = [

            'count' =>
                (int)$row['lead_count'],

            'estimated_amount' =>
                round(
                    (float)$row['estimated_amount'],
                    2
                ),

            'converted_amount' =>
                round(
                    (float)$row['converted_amount'],
                    2
                ),

            'converted_count' =>
                (int)$row['converted_count']
        ];
    }
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| Source Summary
|--------------------------------------------------------------------------
*/

$sourceSummary = [];


$sql = "
    SELECT

        l.source,

        COUNT(*) AS lead_count,

        COALESCE(
            SUM(
                CASE
                    WHEN l.status = 'converted'
                    THEN COALESCE(
                        l.converted_amount,
                        0
                    )
                    ELSE 0
                END
            ),
            0
        ) AS converted_amount

    FROM leads l

    {$whereSql}

    GROUP BY l.source

    ORDER BY lead_count DESC
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


while ($row = $result->fetch_assoc()) {

    $sourceSummary[] = [

        'source' =>
            $row['source'],

        'lead_count' =>
            (int)$row['lead_count'],

        'converted_amount' =>
            round(
                (float)$row['converted_amount'],
                2
            )
    ];
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| Assigned Admin Summary
|--------------------------------------------------------------------------
*/

$assignedSummary = [];


$assignedWhere =
    $where;


$assignedParams =
    $params;


$assignedTypes =
    $types;


/*
|--------------------------------------------------------------------------
| Remove assigned_admin filter from summary
|--------------------------------------------------------------------------
|
| We want staff/admin distribution correctly.
|
*/

$assignedWhere = [];

$assignedParams = [];

$assignedTypes = '';


/*
|--------------------------------------------------------------------------
| Rebuild basic filters for assigned summary
|--------------------------------------------------------------------------
*/

if ($domain !== 'all') {

    $assignedWhere[] =
        'l.domain = ?';

    $assignedParams[] =
        $domain;

    $assignedTypes .= 's';
}


if ($status !== 'all') {

    $assignedWhere[] =
        'l.status = ?';

    $assignedParams[] =
        $status;

    $assignedTypes .= 's';
}


if ($source !== 'all') {

    $assignedWhere[] =
        'l.source = ?';

    $assignedParams[] =
        $source;

    $assignedTypes .= 's';
}


if ($currentUserRole === 'staff') {

    $assignedWhere[] =
        'l.assigned_admin_id = ?';

    $assignedParams[] =
        $currentUserId;

    $assignedTypes .= 'i';
}


if ($eventDate !== null) {

    $assignedWhere[] =
        'l.event_date = ?';

    $assignedParams[] =
        $eventDate;

    $assignedTypes .= 's';
}


if ($fromDate !== null) {

    $assignedWhere[] =
        'l.created_at >= ?';

    $assignedParams[] =
        $fromDate . ' 00:00:00';

    $assignedTypes .= 's';
}


if ($toDate !== null) {

    $assignedWhere[] =
        'l.created_at <= ?';

    $assignedParams[] =
        $toDate . ' 23:59:59';

    $assignedTypes .= 's';
}


$assignedWhereSql = '';

if (!empty($assignedWhere)) {

    $assignedWhereSql =
        'WHERE ' .
        implode(
            ' AND ',
            $assignedWhere
        );
}


$sql = "
    SELECT

        au.id,

        au.name,

        au.email,

        au.phone,

        au.role,

        au.status,

        COUNT(l.id) AS lead_count,

        COALESCE(
            SUM(
                CASE
                    WHEN l.status = 'converted'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS converted_count,

        COALESCE(
            SUM(
                CASE
                    WHEN l.status = 'converted'
                    THEN COALESCE(
                        l.converted_amount,
                        0
                    )
                    ELSE 0
                END
            ),
            0
        ) AS converted_amount

    FROM admin_users au

    INNER JOIN leads l
        ON l.assigned_admin_id = au.id

    {$assignedWhereSql}

    GROUP BY

        au.id,
        au.name,
        au.email,
        au.phone,
        au.role,
        au.status

    ORDER BY lead_count DESC
";


$stmt =
    executeStatement(
        $conn,
        $sql,
        $assignedTypes,
        $assignedParams
    );


$result =
    $stmt->get_result();


while ($row = $result->fetch_assoc()) {

    $assignedSummary[] = [

        'admin_id' =>
            (int)$row['id'],

        'name' =>
            $row['name'],

        'email' =>
            $row['email'],

        'phone' =>
            $row['phone'],

        'role' =>
            $row['role'],

        'status' =>
            $row['status'],

        'lead_count' =>
            (int)$row['lead_count'],

        'converted_count' =>
            (int)$row['converted_count'],

        'converted_amount' =>
            round(
                (float)$row['converted_amount'],
                2
            )
    ];
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| Unassigned Leads Count
|--------------------------------------------------------------------------
*/

$unassignedCount = 0;


/*
|--------------------------------------------------------------------------
| Use same filters but force NULL assignment
|--------------------------------------------------------------------------
*/

$unassignedWhere = [];

$unassignedParams = [];

$unassignedTypes = '';


if ($domain !== 'all') {

    $unassignedWhere[] =
        'l.domain = ?';

    $unassignedParams[] =
        $domain;

    $unassignedTypes .= 's';
}


if ($status !== 'all') {

    $unassignedWhere[] =
        'l.status = ?';

    $unassignedParams[] =
        $status;

    $unassignedTypes .= 's';
}


if ($source !== 'all') {

    $unassignedWhere[] =
        'l.source = ?';

    $unassignedParams[] =
        $source;

    $unassignedTypes .= 's';
}


if ($eventDate !== null) {

    $unassignedWhere[] =
        'l.event_date = ?';

    $unassignedParams[] =
        $eventDate;

    $unassignedTypes .= 's';
}


if ($fromDate !== null) {

    $unassignedWhere[] =
        'l.created_at >= ?';

    $unassignedParams[] =
        $fromDate . ' 00:00:00';

    $unassignedTypes .= 's';
}


if ($toDate !== null) {

    $unassignedWhere[] =
        'l.created_at <= ?';

    $unassignedParams[] =
        $toDate . ' 23:59:59';

    $unassignedTypes .= 's';
}


if ($currentUserRole === 'staff') {

    /*
    |--------------------------------------------------------------------------
    | Staff should never see unassigned leads
    |--------------------------------------------------------------------------
    */

    $unassignedWhere[] =
        '1 = 0';
}


$unassignedWhere[] =
    'l.assigned_admin_id IS NULL';


$unassignedWhereSql =
    'WHERE ' .
    implode(
        ' AND ',
        $unassignedWhere
    );


$sql = "
    SELECT
        COUNT(*) AS total

    FROM leads l

    {$unassignedWhereSql}
";


$stmt =
    executeStatement(
        $conn,
        $sql,
        $unassignedTypes,
        $unassignedParams
    );


$result =
    $stmt->get_result();


if ($row = $result->fetch_assoc()) {

    $unassignedCount =
        (int)$row['total'];
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| Main Lead Query
|--------------------------------------------------------------------------
*/

$sortMap = [

    'created_at' =>
        'l.created_at',

    'updated_at' =>
        'l.updated_at',

    'event_date' =>
        'l.event_date',

    'customer_name' =>
        'l.customer_name',

    'status' =>
        'l.status',

    'estimated_amount' =>
        'l.estimated_amount',

    'converted_amount' =>
        'l.converted_amount',

    'lead_reference' =>
        'l.lead_reference'
];


$orderColumn =
    $sortMap[$sortBy];


$orderDirection =
    strtoupper($sortOrder);


$offset =
    ($page - 1) * $limit;


$sql = "
    SELECT

        l.id,

        l.lead_reference,

        l.domain,

        l.customer_name,

        l.customer_phone,

        l.customer_email,

        l.event_date,

        l.guest_count,

        l.preferred_time,

        l.requirement,

        l.status,

        l.estimated_amount,

        l.converted_amount,

        l.converted_at,

        l.source,

        l.assigned_admin_id,

        l.created_at,

        l.updated_at,


        au.name AS assigned_admin_name,

        au.email AS assigned_admin_email,

        au.phone AS assigned_admin_phone,

        au.role AS assigned_admin_role,

        au.status AS assigned_admin_status,


        (
            SELECT
                COUNT(*)

            FROM lead_notifications ln

            WHERE ln.lead_id = l.id
        ) AS notification_count,


        (
            SELECT
                COUNT(*)

            FROM lead_notifications ln_sent

            WHERE ln_sent.lead_id = l.id

              AND ln_sent.status = 'sent'
        ) AS sent_notification_count,


        (
            SELECT
                COUNT(*)

            FROM lead_notifications ln_pending

            WHERE ln_pending.lead_id = l.id

              AND ln_pending.status = 'pending'
        ) AS pending_notification_count,


        (
            SELECT
                COUNT(*)

            FROM lead_notifications ln_failed

            WHERE ln_failed.lead_id = l.id

              AND ln_failed.status = 'failed'
        ) AS failed_notification_count

    FROM leads l

    LEFT JOIN admin_users au
        ON au.id = l.assigned_admin_id

    {$whereSql}

    ORDER BY
        {$orderColumn}
        {$orderDirection}

    LIMIT ?

    OFFSET ?
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


$leads = [];


/*
|--------------------------------------------------------------------------
| Loop Leads
|--------------------------------------------------------------------------
*/

while ($row = $result->fetch_assoc()) {

    $currentLeadId =
        (int)$row['id'];


    /*
    |--------------------------------------------------------------------------
    | Domain Details
    |--------------------------------------------------------------------------
    */

    $domainDetails = null;


    /*
    |--------------------------------------------------------------------------
    | HALL
    |--------------------------------------------------------------------------
    */

    if (
        $row['domain'] === 'hall'
    ) {

        $hallSql = "
            SELECT

                hld.lead_id,

                hld.hall_id,

                hld.event_type,

                hld.event_date,

                hld.event_time,

                hld.guest_count,

                hld.food_required,

                hld.decoration_required,

                hld.other_requirements,

                h.name AS hall_name,

                h.slug AS hall_slug,

                h.capacity AS hall_capacity,

                h.area AS hall_area,

                h.price_description AS hall_price_description,

                h.status AS hall_status,

                ht.id AS hall_type_id,

                ht.name AS hall_type_name

            FROM hall_lead_details hld

            LEFT JOIN halls h
                ON h.id = hld.hall_id

            LEFT JOIN hall_types ht
                ON ht.id = h.hall_type_id

            WHERE hld.lead_id = ?

            LIMIT 1
        ";


        $hallStmt =
            executeStatement(
                $conn,
                $hallSql,
                'i',
                [$currentLeadId]
            );


        $hallResult =
            $hallStmt->get_result();


        if ($hallRow = $hallResult->fetch_assoc()) {

            $domainDetails = [

                'hall_id' =>
                    $hallRow['hall_id'] !== null
                        ? (int)$hallRow['hall_id']
                        : null,

                'hall_name' =>
                    $hallRow['hall_name'],

                'hall_slug' =>
                    $hallRow['hall_slug'],

                'hall_type_id' =>
                    $hallRow['hall_type_id'] !== null
                        ? (int)$hallRow['hall_type_id']
                        : null,

                'hall_type_name' =>
                    $hallRow['hall_type_name'],

                'hall_capacity' =>
                    $hallRow['hall_capacity'] !== null
                        ? (int)$hallRow['hall_capacity']
                        : null,

                'hall_area' =>
                    $hallRow['hall_area'],

                'hall_price_description' =>
                    $hallRow['hall_price_description'],

                'hall_status' =>
                    $hallRow['hall_status'],

                'event_type' =>
                    $hallRow['event_type'],

                'event_date' =>
                    $hallRow['event_date'],

                'event_time' =>
                    $hallRow['event_time'],

                'guest_count' =>
                    $hallRow['guest_count'] !== null
                        ? (int)$hallRow['guest_count']
                        : null,

                'food_required' =>
                    (bool)$hallRow['food_required'],

                'decoration_required' =>
                    (bool)$hallRow['decoration_required'],

                'other_requirements' =>
                    $hallRow['other_requirements']
            ];
        }


        $hallStmt->close();
    }


    /*
    |--------------------------------------------------------------------------
    | DINING
    |--------------------------------------------------------------------------
    */

    if (
        $row['domain'] === 'dining'
    ) {

        $diningSql = "
            SELECT

                dld.lead_id,

                dld.dining_package_id,

                dld.meal_type,

                dld.dining_date,

                dld.dining_time,

                dld.guest_count,

                dld.other_requirements,

                dp.name AS package_name,

                dp.description AS package_description,

                dp.meal_type AS package_meal_type,

                dp.image_path AS package_image_path,

                dp.status AS package_status

            FROM dining_lead_details dld

            LEFT JOIN dining_packages dp
                ON dp.id = dld.dining_package_id

            WHERE dld.lead_id = ?

            LIMIT 1
        ";


        $diningStmt =
            executeStatement(
                $conn,
                $diningSql,
                'i',
                [$currentLeadId]
            );


        $diningResult =
            $diningStmt->get_result();


        $diningPackageItems = [];


        if (
            $diningRow =
            $diningResult->fetch_assoc()
        ) {

            /*
            |--------------------------------------------------------------------------
            | Package Items
            |--------------------------------------------------------------------------
            */

            if (
                $diningRow['dining_package_id']
                !== null
            ) {

                $packageItemSql = "
                    SELECT

                        dpi.food_item_id,

                        dpi.quantity,

                        fi.name AS food_item_name,

                        fi.description AS food_item_description,

                        fi.food_type,

                        fi.status AS food_item_status

                    FROM dining_package_items dpi

                    INNER JOIN food_items fi
                        ON fi.id = dpi.food_item_id

                    WHERE dpi.package_id = ?

                    ORDER BY fi.name ASC
                ";


                $packageItemStmt =
                    executeStatement(
                        $conn,
                        $packageItemSql,
                        'i',
                        [
                            (int)$diningRow[
                                'dining_package_id'
                            ]
                        ]
                    );


                $packageItemResult =
                    $packageItemStmt->get_result();


                while (
                    $itemRow =
                    $packageItemResult->fetch_assoc()
                ) {

                    $diningPackageItems[] = [

                        'food_item_id' =>
                            (int)$itemRow['food_item_id'],

                        'food_item_name' =>
                            $itemRow['food_item_name'],

                        'food_item_description' =>
                            $itemRow['food_item_description'],

                        'food_type' =>
                            $itemRow['food_type'],

                        'quantity' =>
                            (int)$itemRow['quantity'],

                        'food_item_status' =>
                            $itemRow['food_item_status']
                    ];
                }


                $packageItemStmt->close();
            }


            /*
            |--------------------------------------------------------------------------
            | Custom Items
            |--------------------------------------------------------------------------
            */

            $diningCustomItems = [];


            $customItemSql = "
                SELECT

                    dli.id,

                    dli.food_item_id,

                    dli.quantity,

                    dli.notes,

                    fi.name AS food_item_name,

                    fi.description AS food_item_description,

                    fi.food_type,

                    fi.status AS food_item_status,

                    fc.name AS food_category_name

                FROM dining_lead_items dli

                INNER JOIN food_items fi
                    ON fi.id = dli.food_item_id

                LEFT JOIN food_categories fc
                    ON fc.id = fi.food_category_id

                WHERE dli.lead_id = ?

                ORDER BY dli.id ASC
            ";


            $customItemStmt =
                executeStatement(
                    $conn,
                    $customItemSql,
                    'i',
                    [$currentLeadId]
                );


            $customItemResult =
                $customItemStmt->get_result();


            while (
                $customRow =
                $customItemResult->fetch_assoc()
            ) {

                $diningCustomItems[] = [

                    'id' =>
                        (int)$customRow['id'],

                    'food_item_id' =>
                        (int)$customRow['food_item_id'],

                    'food_item_name' =>
                        $customRow['food_item_name'],

                    'food_item_description' =>
                        $customRow['food_item_description'],

                    'food_category_name' =>
                        $customRow['food_category_name'],

                    'food_type' =>
                        $customRow['food_type'],

                    'quantity' =>
                        (int)$customRow['quantity'],

                    'notes' =>
                        $customRow['notes'],

                    'food_item_status' =>
                        $customRow['food_item_status']
                ];
            }


            $customItemStmt->close();


            $domainDetails = [

                'dining_package_id' =>
                    $diningRow['dining_package_id'] !== null
                        ? (int)$diningRow['dining_package_id']
                        : null,

                'package_name' =>
                    $diningRow['package_name'],

                'package_description' =>
                    $diningRow['package_description'],

                'package_meal_type' =>
                    $diningRow['package_meal_type'],

                'package_image_path' =>
                    $diningRow['package_image_path'],

                'package_status' =>
                    $diningRow['package_status'],

                'meal_type' =>
                    $diningRow['meal_type'],

                'dining_date' =>
                    $diningRow['dining_date'],

                'dining_time' =>
                    $diningRow['dining_time'],

                'guest_count' =>
                    $diningRow['guest_count'] !== null
                        ? (int)$diningRow['guest_count']
                        : null,

                'other_requirements' =>
                    $diningRow['other_requirements'],

                'package_items' =>
                    $diningPackageItems,

                'custom_items' =>
                    $diningCustomItems
            ];
        }


        $diningStmt->close();
    }


    /*
    |--------------------------------------------------------------------------
    | CATERING
    |--------------------------------------------------------------------------
    */

    if (
        $row['domain'] === 'catering'
    ) {

        $cateringSql = "
            SELECT

                cld.lead_id,

                cld.catering_package_id,

                cld.event_type,

                cld.event_date,

                cld.event_time,

                cld.guest_count,

                cld.venue_address,

                cld.estimated_amount,

                cld.other_requirements,

                cp.name AS package_name,

                cp.description AS package_description,

                cp.rate AS package_rate,

                cp.rate_unit,

                cp.gst_rate,

                cp.price_includes_gst,

                cp.minimum_quantity,

                cp.image_path AS package_image_path,

                cp.status AS package_status

            FROM catering_lead_details cld

            LEFT JOIN catering_packages cp
                ON cp.id = cld.catering_package_id

            WHERE cld.lead_id = ?

            LIMIT 1
        ";


        $cateringStmt =
            executeStatement(
                $conn,
                $cateringSql,
                'i',
                [$currentLeadId]
            );


        $cateringResult =
            $cateringStmt->get_result();


        if (
            $cateringRow =
            $cateringResult->fetch_assoc()
        ) {

            /*
            |--------------------------------------------------------------------------
            | Package Items
            |--------------------------------------------------------------------------
            */

            $cateringPackageItems = [];


            if (
                $cateringRow['catering_package_id']
                !== null
            ) {

                $packageItemSql = "
                    SELECT

                        cpi.food_item_id,

                        cpi.quantity,

                        fi.name AS food_item_name,

                        fi.description AS food_item_description,

                        fi.food_type,

                        fi.status AS food_item_status

                    FROM catering_package_items cpi

                    INNER JOIN food_items fi
                        ON fi.id = cpi.food_item_id

                    WHERE cpi.package_id = ?

                    ORDER BY fi.name ASC
                ";


                $packageItemStmt =
                    executeStatement(
                        $conn,
                        $packageItemSql,
                        'i',
                        [
                            (int)$cateringRow[
                                'catering_package_id'
                            ]
                        ]
                    );


                $packageItemResult =
                    $packageItemStmt->get_result();


                while (
                    $itemRow =
                    $packageItemResult->fetch_assoc()
                ) {

                    $cateringPackageItems[] = [

                        'food_item_id' =>
                            (int)$itemRow['food_item_id'],

                        'food_item_name' =>
                            $itemRow['food_item_name'],

                        'food_item_description' =>
                            $itemRow['food_item_description'],

                        'food_type' =>
                            $itemRow['food_type'],

                        'quantity' =>
                            (int)$itemRow['quantity'],

                        'food_item_status' =>
                            $itemRow['food_item_status']
                    ];
                }


                $packageItemStmt->close();
            }


            /*
            |--------------------------------------------------------------------------
            | Custom Items
            |--------------------------------------------------------------------------
            */

            $cateringCustomItems = [];


            $customItemSql = "
                SELECT

                    cli.id,

                    cli.food_item_id,

                    cli.quantity,

                    cli.notes,

                    fi.name AS food_item_name,

                    fi.description AS food_item_description,

                    fi.food_type,

                    fi.status AS food_item_status,

                    fc.name AS food_category_name

                FROM catering_lead_items cli

                INNER JOIN food_items fi
                    ON fi.id = cli.food_item_id

                LEFT JOIN food_categories fc
                    ON fc.id = fi.food_category_id

                WHERE cli.lead_id = ?

                ORDER BY cli.id ASC
            ";


            $customItemStmt =
                executeStatement(
                    $conn,
                    $customItemSql,
                    'i',
                    [$currentLeadId]
                );


            $customItemResult =
                $customItemStmt->get_result();


            while (
                $customRow =
                $customItemResult->fetch_assoc()
            ) {

                $cateringCustomItems[] = [

                    'id' =>
                        (int)$customRow['id'],

                    'food_item_id' =>
                        (int)$customRow['food_item_id'],

                    'food_item_name' =>
                        $customRow['food_item_name'],

                    'food_item_description' =>
                        $customRow['food_item_description'],

                    'food_category_name' =>
                        $customRow['food_category_name'],

                    'food_type' =>
                        $customRow['food_type'],

                    'quantity' =>
                        (int)$customRow['quantity'],

                    'notes' =>
                        $customRow['notes'],

                    'food_item_status' =>
                        $customRow['food_item_status']
                ];
            }


            $customItemStmt->close();


            $domainDetails = [

                'catering_package_id' =>
                    $cateringRow['catering_package_id'] !== null
                        ? (int)$cateringRow['catering_package_id']
                        : null,

                'package_name' =>
                    $cateringRow['package_name'],

                'package_description' =>
                    $cateringRow['package_description'],

                'package_rate' =>
                    round(
                        (float)$cateringRow['package_rate'],
                        2
                    ),

                'rate_unit' =>
                    $cateringRow['rate_unit'],

                'gst_rate' =>
                    round(
                        (float)$cateringRow['gst_rate'],
                        2
                    ),

                'price_includes_gst' =>
                    (bool)$cateringRow['price_includes_gst'],

                'minimum_quantity' =>
                    (int)$cateringRow['minimum_quantity'],

                'package_image_path' =>
                    $cateringRow['package_image_path'],

                'package_status' =>
                    $cateringRow['package_status'],

                'event_type' =>
                    $cateringRow['event_type'],

                'event_date' =>
                    $cateringRow['event_date'],

                'event_time' =>
                    $cateringRow['event_time'],

                'guest_count' =>
                    $cateringRow['guest_count'] !== null
                        ? (int)$cateringRow['guest_count']
                        : null,

                'venue_address' =>
                    $cateringRow['venue_address'],

                'estimated_amount' =>
                    $cateringRow['estimated_amount'] !== null
                        ? round(
                            (float)$cateringRow['estimated_amount'],
                            2
                        )
                        : null,

                'other_requirements' =>
                    $cateringRow['other_requirements'],

                'package_items' =>
                    $cateringPackageItems,

                'custom_items' =>
                    $cateringCustomItems
            ];
        }


        $cateringStmt->close();
    }


    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */

    $notifications = [];


    $notificationSql = "
        SELECT

            id,

            channel,

            recipient,

            status,

            provider_message_id,

            error_message,

            sent_at,

            created_at

        FROM lead_notifications

        WHERE lead_id = ?

        ORDER BY id DESC
    ";


    $notificationStmt =
        executeStatement(
            $conn,
            $notificationSql,
            'i',
            [$currentLeadId]
        );


    $notificationResult =
        $notificationStmt->get_result();


    while (
        $notificationRow =
        $notificationResult->fetch_assoc()
    ) {

        $notifications[] = [

            'id' =>
                (int)$notificationRow['id'],

            'channel' =>
                $notificationRow['channel'],

            'recipient' =>
                $notificationRow['recipient'],

            'status' =>
                $notificationRow['status'],

            'provider_message_id' =>
                $notificationRow['provider_message_id'],

            'error_message' =>
                $notificationRow['error_message'],

            'sent_at' =>
                $notificationRow['sent_at'],

            'created_at' =>
                $notificationRow['created_at']
        ];
    }


    $notificationStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Lead Object
    |--------------------------------------------------------------------------
    */

    $leads[] = [

        'id' =>
            $currentLeadId,

        'lead_reference' =>
            $row['lead_reference'],

        'domain' =>
            $row['domain'],


        /*
        |--------------------------------------------------------------------------
        | Customer
        |--------------------------------------------------------------------------
        */

        'customer' => [

            'name' =>
                $row['customer_name'],

            'phone' =>
                $row['customer_phone'],

            'email' =>
                $row['customer_email']
        ],


        /*
        |--------------------------------------------------------------------------
        | Common Event
        |--------------------------------------------------------------------------
        */

        'event' => [

            'event_date' =>
                $row['event_date'],

            'guest_count' =>
                $row['guest_count'] !== null
                    ? (int)$row['guest_count']
                    : null,

            'preferred_time' =>
                $row['preferred_time']
        ],


        /*
        |--------------------------------------------------------------------------
        | Requirement
        |--------------------------------------------------------------------------
        */

        'requirement' =>
            $row['requirement'],


        /*
        |--------------------------------------------------------------------------
        | Status
        |--------------------------------------------------------------------------
        */

        'status' =>
            $row['status'],


        /*
        |--------------------------------------------------------------------------
        | Amount
        |--------------------------------------------------------------------------
        */

        'amount' => [

            'estimated_amount' =>
                $row['estimated_amount'] !== null
                    ? round(
                        (float)$row['estimated_amount'],
                        2
                    )
                    : null,

            'converted_amount' =>
                $row['converted_amount'] !== null
                    ? round(
                        (float)$row['converted_amount'],
                        2
                    )
                    : null,

            'converted_at' =>
                $row['converted_at']
        ],


        /*
        |--------------------------------------------------------------------------
        | Source
        |--------------------------------------------------------------------------
        */

        'source' =>
            $row['source'],


        /*
        |--------------------------------------------------------------------------
        | Assigned Admin
        |--------------------------------------------------------------------------
        */

        'assigned_admin' =>

            $row['assigned_admin_id'] !== null

                ? [

                    'id' =>
                        (int)$row['assigned_admin_id'],

                    'name' =>
                        $row['assigned_admin_name'],

                    'email' =>
                        $row['assigned_admin_email'],

                    'phone' =>
                        $row['assigned_admin_phone'],

                    'role' =>
                        $row['assigned_admin_role'],

                    'status' =>
                        $row['assigned_admin_status']
                ]

                : null,


        /*
        |--------------------------------------------------------------------------
        | Domain Details
        |--------------------------------------------------------------------------
        */

        'domain_details' =>
            $domainDetails,


        /*
        |--------------------------------------------------------------------------
        | Notifications
        |--------------------------------------------------------------------------
        */

        'notifications' => [

            'total' =>
                (int)$row['notification_count'],

            'sent' =>
                (int)$row['sent_notification_count'],

            'pending' =>
                (int)$row['pending_notification_count'],

            'failed' =>
                (int)$row['failed_notification_count'],

            'items' =>
                $notifications
        ],


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
| Monthly Lead Trend
|--------------------------------------------------------------------------
*/

$monthlyWhere = [];

$monthlyParams = [];

$monthlyTypes = '';


if ($domain !== 'all') {

    $monthlyWhere[] =
        'l.domain = ?';

    $monthlyParams[] =
        $domain;

    $monthlyTypes .= 's';
}


if ($status !== 'all') {

    $monthlyWhere[] =
        'l.status = ?';

    $monthlyParams[] =
        $status;

    $monthlyTypes .= 's';
}


if ($source !== 'all') {

    $monthlyWhere[] =
        'l.source = ?';

    $monthlyParams[] =
        $source;

    $monthlyTypes .= 's';
}


if ($currentUserRole === 'staff') {

    $monthlyWhere[] =
        'l.assigned_admin_id = ?';

    $monthlyParams[] =
        $currentUserId;

    $monthlyTypes .= 'i';
}


if ($assignedAdminId !== null && $currentUserRole !== 'staff') {

    $monthlyWhere[] =
        'l.assigned_admin_id = ?';

    $monthlyParams[] =
        (int)$assignedAdminId;

    $monthlyTypes .= 'i';
}


if ($eventDate !== null) {

    $monthlyWhere[] =
        'l.event_date = ?';

    $monthlyParams[] =
        $eventDate;

    $monthlyTypes .= 's';
}


if ($fromDate !== null) {

    $monthlyWhere[] =
        'l.created_at >= ?';

    $monthlyParams[] =
        $fromDate . ' 00:00:00';

    $monthlyTypes .= 's';
}


if ($toDate !== null) {

    $monthlyWhere[] =
        'l.created_at <= ?';

    $monthlyParams[] =
        $toDate . ' 23:59:59';

    $monthlyTypes .= 's';
}


$monthlyWhereSql = '';

if (!empty($monthlyWhere)) {

    $monthlyWhereSql =
        'WHERE ' .
        implode(
            ' AND ',
            $monthlyWhere
        );
}


$monthlyData = [];


$sql = "
    SELECT

        DATE_FORMAT(
            l.created_at,
            '%Y-%m'
        ) AS lead_month,

        COUNT(*) AS lead_count,

        COALESCE(
            SUM(
                CASE
                    WHEN l.status = 'converted'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS converted_count,

        COALESCE(
            SUM(
                CASE
                    WHEN l.status = 'converted'
                    THEN COALESCE(
                        l.converted_amount,
                        0
                    )
                    ELSE 0
                END
            ),
            0
        ) AS converted_amount,

        COALESCE(
            SUM(
                COALESCE(
                    l.estimated_amount,
                    0
                )
            ),
            0
        ) AS estimated_amount

    FROM leads l

    {$monthlyWhereSql}

    GROUP BY
        DATE_FORMAT(
            l.created_at,
            '%Y-%m'
        )

    ORDER BY
        lead_month ASC
";


$stmt =
    executeStatement(
        $conn,
        $sql,
        $monthlyTypes,
        $monthlyParams
    );


$result =
    $stmt->get_result();


while ($row = $result->fetch_assoc()) {

    $monthlyData[] = [

        'month' =>
            $row['lead_month'],

        'lead_count' =>
            (int)$row['lead_count'],

        'converted_count' =>
            (int)$row['converted_count'],

        'converted_amount' =>
            round(
                (float)$row['converted_amount'],
                2
            ),

        'estimated_amount' =>
            round(
                (float)$row['estimated_amount'],
                2
            )
    ];
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| Chart Data - Monthly
|--------------------------------------------------------------------------
*/

$monthlyLabels = [];

$monthlyLeadCount = [];

$monthlyConvertedCount = [];

$monthlyConvertedAmount = [];

$monthlyEstimatedAmount = [];


foreach (
    $monthlyData
    as $month
) {

    $monthlyLabels[] =
        $month['month'];

    $monthlyLeadCount[] =
        $month['lead_count'];

    $monthlyConvertedCount[] =
        $month['converted_count'];

    $monthlyConvertedAmount[] =
        $month['converted_amount'];

    $monthlyEstimatedAmount[] =
        $month['estimated_amount'];
}


/*
|--------------------------------------------------------------------------
| Domain Chart
|--------------------------------------------------------------------------
*/

$domainChart = [

    'labels' => [

        'Hall',

        'Dining',

        'Catering'
    ],

    'datasets' => [

        [
            'label' =>
                'Leads',

            'data' => [

                $domainSummary['hall']['count'],

                $domainSummary['dining']['count'],

                $domainSummary['catering']['count']
            ]
        ],

        [
            'label' =>
                'Converted Leads',

            'data' => [

                $domainSummary['hall']['converted_count'],

                $domainSummary['dining']['converted_count'],

                $domainSummary['catering']['converted_count']
            ]
        ]
    ]
];


/*
|--------------------------------------------------------------------------
| Domain Revenue Chart
|--------------------------------------------------------------------------
*/

$domainRevenueChart = [

    'labels' => [

        'Hall',

        'Dining',

        'Catering'
    ],

    'datasets' => [

        [
            'label' =>
                'Converted Revenue',

            'data' => [

                $domainSummary['hall']['converted_amount'],

                $domainSummary['dining']['converted_amount'],

                $domainSummary['catering']['converted_amount']
            ]
        ]
    ]
];


/*
|--------------------------------------------------------------------------
| Status Chart
|--------------------------------------------------------------------------
*/

$statusChart = [

    'labels' => [

        'New',

        'Contacted',

        'Follow Up',

        'Converted',

        'Lost',

        'Cancelled'
    ],

    'datasets' => [

        [
            'label' =>
                'Leads',

            'data' => [

                $summary['new'],

                $summary['contacted'],

                $summary['follow_up'],

                $summary['converted'],

                $summary['lost'],

                $summary['cancelled']
            ]
        ]
    ]
];


/*
|--------------------------------------------------------------------------
| Source Chart
|--------------------------------------------------------------------------
*/

$sourceLabels = [];

$sourceLeadCounts = [];


foreach (
    $sourceSummary
    as $sourceRow
) {

    $sourceLabels[] =
        ucfirst(
            $sourceRow['source']
        );

    $sourceLeadCounts[] =
        $sourceRow['lead_count'];
}


$sourceChart = [

    'labels' =>
        $sourceLabels,

    'datasets' => [

        [
            'label' =>
                'Leads',

            'data' =>
                $sourceLeadCounts
        ]
    ]
];


/*
|--------------------------------------------------------------------------
| Monthly Chart
|--------------------------------------------------------------------------
*/

$monthlyChart = [

    'labels' =>
        $monthlyLabels,

    'datasets' => [

        [
            'label' =>
                'Total Leads',

            'data' =>
                $monthlyLeadCount
        ],

        [
            'label' =>
                'Converted Leads',

            'data' =>
                $monthlyConvertedCount
        ],

        [
            'label' =>
                'Converted Revenue',

            'data' =>
                $monthlyConvertedAmount
        ],

        [
            'label' =>
                'Estimated Value',

            'data' =>
                $monthlyEstimatedAmount
        ]
    ]
];


/*
|--------------------------------------------------------------------------
| Upcoming Events
|--------------------------------------------------------------------------
*/

$upcomingEvents = [];


/*
|--------------------------------------------------------------------------
| Upcoming event query
|--------------------------------------------------------------------------
*/

$upcomingWhere = [

    'l.event_date >= CURDATE()',

    'l.status NOT IN (
        \'cancelled\',
        \'lost\'
    )'
];


$upcomingParams = [];

$upcomingTypes = '';


if ($currentUserRole === 'staff') {

    $upcomingWhere[] =
        'l.assigned_admin_id = ?';

    $upcomingParams[] =
        $currentUserId;

    $upcomingTypes .= 'i';
}


if ($domain !== 'all') {

    $upcomingWhere[] =
        'l.domain = ?';

    $upcomingParams[] =
        $domain;

    $upcomingTypes .= 's';
}


$upcomingWhereSql =
    'WHERE ' .
    implode(
        ' AND ',
        $upcomingWhere
    );


$sql = "
    SELECT

        l.id,

        l.lead_reference,

        l.domain,

        l.customer_name,

        l.customer_phone,

        l.event_date,

        l.guest_count,

        l.preferred_time,

        l.status,

        l.estimated_amount,

        l.converted_amount,

        au.name AS assigned_admin_name

    FROM leads l

    LEFT JOIN admin_users au
        ON au.id = l.assigned_admin_id

    {$upcomingWhereSql}

    ORDER BY

        l.event_date ASC,

        l.id ASC

    LIMIT 10
";


$stmt =
    executeStatement(
        $conn,
        $sql,
        $upcomingTypes,
        $upcomingParams
    );


$result =
    $stmt->get_result();


while ($row = $result->fetch_assoc()) {

    $upcomingEvents[] = [

        'id' =>
            (int)$row['id'],

        'lead_reference' =>
            $row['lead_reference'],

        'domain' =>
            $row['domain'],

        'customer_name' =>
            $row['customer_name'],

        'customer_phone' =>
            $row['customer_phone'],

        'event_date' =>
            $row['event_date'],

        'preferred_time' =>
            $row['preferred_time'],

        'guest_count' =>
            $row['guest_count'] !== null
                ? (int)$row['guest_count']
                : null,

        'status' =>
            $row['status'],

        'estimated_amount' =>
            $row['estimated_amount'] !== null
                ? round(
                    (float)$row['estimated_amount'],
                    2
                )
                : null,

        'converted_amount' =>
            $row['converted_amount'] !== null
                ? round(
                    (float)$row['converted_amount'],
                    2
                )
                : null,

        'assigned_admin_name' =>
            $row['assigned_admin_name']
    ];
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| Recent Leads
|--------------------------------------------------------------------------
*/

$recentLeads = [];


$recentWhere = [];

$recentParams = [];

$recentTypes = '';


if ($currentUserRole === 'staff') {

    $recentWhere[] =
        'l.assigned_admin_id = ?';

    $recentParams[] =
        $currentUserId;

    $recentTypes .= 'i';
}


$recentWhereSql = '';

if (!empty($recentWhere)) {

    $recentWhereSql =
        'WHERE ' .
        implode(
            ' AND ',
            $recentWhere
        );
}


$sql = "
    SELECT

        l.id,

        l.lead_reference,

        l.domain,

        l.customer_name,

        l.customer_phone,

        l.customer_email,

        l.event_date,

        l.guest_count,

        l.preferred_time,

        l.status,

        l.estimated_amount,

        l.converted_amount,

        l.source,

        l.created_at,

        au.name AS assigned_admin_name

    FROM leads l

    LEFT JOIN admin_users au
        ON au.id = l.assigned_admin_id

    {$recentWhereSql}

    ORDER BY
        l.created_at DESC

    LIMIT 10
";


$stmt =
    executeStatement(
        $conn,
        $sql,
        $recentTypes,
        $recentParams
    );


$result =
    $stmt->get_result();


while ($row = $result->fetch_assoc()) {

    $recentLeads[] = [

        'id' =>
            (int)$row['id'],

        'lead_reference' =>
            $row['lead_reference'],

        'domain' =>
            $row['domain'],

        'customer_name' =>
            $row['customer_name'],

        'customer_phone' =>
            $row['customer_phone'],

        'customer_email' =>
            $row['customer_email'],

        'event_date' =>
            $row['event_date'],

        'guest_count' =>
            $row['guest_count'] !== null
                ? (int)$row['guest_count']
                : null,

        'preferred_time' =>
            $row['preferred_time'],

        'status' =>
            $row['status'],

        'estimated_amount' =>
            $row['estimated_amount'] !== null
                ? round(
                    (float)$row['estimated_amount'],
                    2
                )
                : null,

        'converted_amount' =>
            $row['converted_amount'] !== null
                ? round(
                    (float)$row['converted_amount'],
                    2
                )
                : null,

        'source' =>
            $row['source'],

        'assigned_admin_name' =>
            $row['assigned_admin_name'],

        'created_at' =>
            $row['created_at']
    ];
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| Notification Summary
|--------------------------------------------------------------------------
*/

$notificationSummary = [

    'total' => 0,

    'pending' => 0,

    'sent' => 0,

    'failed' => 0,

    'whatsapp' => 0,

    'email' => 0
];


$notificationWhere = [];

$notificationParams = [];

$notificationTypes = '';


/*
|--------------------------------------------------------------------------
| Staff Notification Scope
|--------------------------------------------------------------------------
*/

if ($currentUserRole === 'staff') {

    $notificationWhere[] = "
        EXISTS (
            SELECT 1

            FROM leads nl

            WHERE nl.id = ln.lead_id

            AND nl.assigned_admin_id = ?
        )
    ";

    $notificationParams[] =
        $currentUserId;

    $notificationTypes .= 'i';
}


$notificationWhereSql = '';

if (!empty($notificationWhere)) {

    $notificationWhereSql =
        'WHERE ' .
        implode(
            ' AND ',
            $notificationWhere
        );
}


$sql = "
    SELECT

        COUNT(*) AS total,

        COALESCE(
            SUM(
                CASE
                    WHEN ln.status = 'pending'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS pending_count,

        COALESCE(
            SUM(
                CASE
                    WHEN ln.status = 'sent'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS sent_count,

        COALESCE(
            SUM(
                CASE
                    WHEN ln.status = 'failed'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS failed_count,

        COALESCE(
            SUM(
                CASE
                    WHEN ln.channel = 'whatsapp'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS whatsapp_count,

        COALESCE(
            SUM(
                CASE
                    WHEN ln.channel = 'email'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS email_count

    FROM lead_notifications ln

    {$notificationWhereSql}
";


$stmt =
    executeStatement(
        $conn,
        $sql,
        $notificationTypes,
        $notificationParams
    );


$result =
    $stmt->get_result();


if ($row = $result->fetch_assoc()) {

    $notificationSummary = [

        'total' =>
            (int)$row['total'],

        'pending' =>
            (int)$row['pending_count'],

        'sent' =>
            (int)$row['sent_count'],

        'failed' =>
            (int)$row['failed_count'],

        'whatsapp' =>
            (int)$row['whatsapp_count'],

        'email' =>
            (int)$row['email_count']
    ];
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| Notification Chart
|--------------------------------------------------------------------------
*/

$notificationChart = [

    'labels' => [

        'Pending',

        'Sent',

        'Failed'
    ],

    'datasets' => [

        [
            'label' =>
                'Notifications',

            'data' => [

                $notificationSummary['pending'],

                $notificationSummary['sent'],

                $notificationSummary['failed']
            ]
        ]
    ]
];


/*
|--------------------------------------------------------------------------
| Conversion Value Chart
|--------------------------------------------------------------------------
*/

$conversionValueChart = [

    'labels' => [

        'Estimated Value',

        'Converted Revenue',

        'Open Pipeline'
    ],

    'datasets' => [

        [
            'label' =>
                'Amount',

            'data' => [

                $summary['total_estimated_amount'],

                $summary['total_converted_amount'],

                $summary['total_open_value']
            ]
        ]
    ]
];


/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

$totalPages =
    $totalLeads > 0
        ? (int)ceil(
            $totalLeads / $limit
        )
        : 0;


/*
|--------------------------------------------------------------------------
| Final Response
|--------------------------------------------------------------------------
*/

sendResponse(
    true,
    'Lead dashboard data fetched successfully.',
    [

        /*
        |--------------------------------------------------------------------------
        | Meta
        |--------------------------------------------------------------------------
        */

        'meta' => [

            'api' =>
                'dashboard/lead.php',

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

            'lead_id' =>
                $leadId,

            'domain' =>
                $domain,

            'status' =>
                $status,

            'source' =>
                $source,

            'assigned_admin_id' =>
                $assignedAdminId,

            'search' =>
                $search,

            'event_date' =>
                $eventDate,

            'from_date' =>
                $fromDate,

            'to_date' =>
                $toDate,

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
        | KPI
        |--------------------------------------------------------------------------
        */

        'kpis' => [

            'total_leads' =>
                $summary['total'],

            'new_leads' =>
                $summary['new'],

            'contacted_leads' =>
                $summary['contacted'],

            'follow_up_leads' =>
                $summary['follow_up'],

            'converted_leads' =>
                $summary['converted'],

            'lost_leads' =>
                $summary['lost'],

            'cancelled_leads' =>
                $summary['cancelled'],

            'conversion_rate' =>
                $summary['conversion_rate'],

            'estimated_pipeline_value' =>
                $summary['total_estimated_amount'],

            'converted_revenue' =>
                $summary['total_converted_amount'],

            'open_pipeline_value' =>
                $summary['total_open_value'],

            'average_estimated_value' =>
                $summary['average_estimated_amount'],

            'average_converted_value' =>
                $summary['average_converted_amount'],

            'total_guests' =>
                $summary['total_guests'],

            'unassigned_leads' =>
                $unassignedCount
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
        | Domain Summary
        |--------------------------------------------------------------------------
        */

        'by_domain' =>
            $domainSummary,


        /*
        |--------------------------------------------------------------------------
        | Source Summary
        |--------------------------------------------------------------------------
        */

        'by_source' =>
            $sourceSummary,


        /*
        |--------------------------------------------------------------------------
        | Assigned Staff Summary
        |--------------------------------------------------------------------------
        */

        'by_assigned_admin' =>
            $assignedSummary,


        /*
        |--------------------------------------------------------------------------
        | Notifications
        |--------------------------------------------------------------------------
        */

        'notifications' =>
            $notificationSummary,


        /*
        |--------------------------------------------------------------------------
        | Charts
        |--------------------------------------------------------------------------
        */

        'charts' => [

            'lead_status' =>
                $statusChart,

            'lead_domain' =>
                $domainChart,

            'revenue_by_domain' =>
                $domainRevenueChart,

            'lead_source' =>
                $sourceChart,

            'monthly_leads' =>
                $monthlyChart,

            'conversion_value' =>
                $conversionValueChart,

            'notification_status' =>
                $notificationChart
        ],


        /*
        |--------------------------------------------------------------------------
        | Monthly Data
        |--------------------------------------------------------------------------
        */

        'monthly' =>
            $monthlyData,


        /*
        |--------------------------------------------------------------------------
        | Upcoming Events
        |--------------------------------------------------------------------------
        */

        'upcoming_events' =>
            $upcomingEvents,


        /*
        |--------------------------------------------------------------------------
        | Recent Leads
        |--------------------------------------------------------------------------
        */

        'recent_leads' =>
            $recentLeads,


        /*
        |--------------------------------------------------------------------------
        | Current Page Leads
        |--------------------------------------------------------------------------
        */

        'leads' =>
            $leads,


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
                $totalLeads,

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