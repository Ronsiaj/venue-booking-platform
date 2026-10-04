<?php

declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");
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
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../config/middleware.php';


/*
|--------------------------------------------------------------------------
| Helper Functions
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


function getIntParam(
    string $key,
    int $default = 0,
    int $min = 0,
    int $max = PHP_INT_MAX
): int {
    if (!isset($_GET[$key]) || $_GET[$key] === '') {
        return $default;
    }

    $value = filter_var($_GET[$key], FILTER_VALIDATE_INT);

    if ($value === false) {
        jsonResponse(
            false,
            "Invalid {$key}. It must be an integer.",
            [],
            422
        );
    }

    if ($value < $min || $value > $max) {
        jsonResponse(
            false,
            "Invalid {$key}. Allowed range: {$min} to {$max}.",
            [],
            422
        );
    }

    return (int)$value;
}


function getFloatParam(
    string $key,
    ?float $default = null,
    float $min = 0,
    float $max = 999999999999
): ?float {
    if (!isset($_GET[$key]) || $_GET[$key] === '') {
        return $default;
    }

    if (!is_numeric($_GET[$key])) {
        jsonResponse(
            false,
            "Invalid {$key}. It must be numeric.",
            [],
            422
        );
    }

    $value = (float)$_GET[$key];

    if (!is_finite($value)) {
        jsonResponse(
            false,
            "Invalid {$key}.",
            [],
            422
        );
    }

    if ($value < $min || $value > $max) {
        jsonResponse(
            false,
            "Invalid {$key}. Allowed range: {$min} to {$max}.",
            [],
            422
        );
    }

    return $value;
}


function getDateParam(string $key): ?string
{
    if (!isset($_GET[$key]) || trim((string)$_GET[$key]) === '') {
        return null;
    }

    $value = trim((string)$_GET[$key]);

    $date = DateTime::createFromFormat('Y-m-d', $value);

    if (!$date || $date->format('Y-m-d') !== $value) {
        jsonResponse(
            false,
            "Invalid {$key}. Expected format: YYYY-MM-DD.",
            [],
            422
        );
    }

    return $value;
}


function getStringParam(
    string $key,
    ?string $default = null,
    int $maxLength = 255
): ?string {
    if (!isset($_GET[$key])) {
        return $default;
    }

    $value = trim((string)$_GET[$key]);

    if ($value === '') {
        return $default;
    }

    if (mb_strlen($value) > $maxLength) {
        jsonResponse(
            false,
            "{$key} cannot exceed {$maxLength} characters.",
            [],
            422
        );
    }

    return $value;
}


function validateDateRange(
    ?string $fromDate,
    ?string $toDate,
    string $fromKey = 'from_date',
    string $toKey = 'to_date'
): void {
    if ($fromDate !== null && $toDate !== null && $fromDate > $toDate) {
        jsonResponse(
            false,
            "{$fromKey} cannot be greater than {$toKey}.",
            [],
            422
        );
    }
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

if (!in_array($currentUserRole, ['admin', 'staff'], true)) {
    jsonResponse(
        false,
        'Only admin or staff users can access dining reports.',
        [],
        403
    );
}

if (!$currentUserId || !is_numeric($currentUserId)) {
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
| Read & Validate Filters
|--------------------------------------------------------------------------
*/

$leadId = getIntParam('lead_id', 0, 0);

$diningPackageId = getIntParam(
    'dining_package_id',
    0,
    0
);

$foodItemId = getIntParam(
    'food_item_id',
    0,
    0
);

$leadStatus = getStringParam(
    'lead_status',
    'all',
    30
);

$mealType = getStringParam(
    'meal_type',
    'all',
    30
);

$search = getStringParam(
    'search',
    null,
    150
);

$fromDate = getDateParam('from_date');
$toDate = getDateParam('to_date');

$diningDateFrom = getDateParam('dining_date_from');
$diningDateTo = getDateParam('dining_date_to');

$minGuests = getIntParam(
    'min_guests',
    0,
    0,
    1000000
);

$maxGuests = getIntParam(
    'max_guests',
    0,
    0,
    1000000
);

$minAmount = getFloatParam(
    'min_amount',
    null,
    0
);

$maxAmount = getFloatParam(
    'max_amount',
    null,
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

$sortBy = getStringParam(
    'sort_by',
    'created_at',
    50
);

$sortOrder = strtolower(
    getStringParam('sort_order', 'desc', 10)
);


$allowedLeadStatuses = [
    'all',
    'new',
    'contacted',
    'follow_up',
    'converted',
    'lost',
    'cancelled'
];

if (!in_array($leadStatus, $allowedLeadStatuses, true)) {
    jsonResponse(
        false,
        'Invalid lead_status.',
        [
            'allowed' => $allowedLeadStatuses
        ],
        422
    );
}


$allowedMealTypes = [
    'all',
    'lunch',
    'dinner',
    'breakfast',
    'other'
];

if (!in_array($mealType, $allowedMealTypes, true)) {
    jsonResponse(
        false,
        'Invalid meal_type.',
        [
            'allowed' => $allowedMealTypes
        ],
        422
    );
}


if (!in_array($sortOrder, ['asc', 'desc'], true)) {
    jsonResponse(
        false,
        'Invalid sort_order. Allowed values: asc, desc.',
        [],
        422
    );
}


$allowedSortColumns = [
    'created_at' => 'l.created_at',
    'updated_at' => 'l.updated_at',
    'event_date' => 'dld.dining_date',
    'dining_date' => 'dld.dining_date',
    'guest_count' => 'dld.guest_count',
    'converted_amount' => 'l.converted_amount',
    'customer_name' => 'l.customer_name',
    'lead_reference' => 'l.lead_reference',
    'status' => 'l.status'
];

if (!array_key_exists($sortBy, $allowedSortColumns)) {
    jsonResponse(
        false,
        'Invalid sort_by.',
        [
            'allowed' => array_keys($allowedSortColumns)
        ],
        422
    );
}

$sortColumn = $allowedSortColumns[$sortBy];


validateDateRange($fromDate, $toDate);
validateDateRange(
    $diningDateFrom,
    $diningDateTo,
    'dining_date_from',
    'dining_date_to'
);


if ($minGuests > 0 && $maxGuests > 0 && $minGuests > $maxGuests) {
    jsonResponse(
        false,
        'min_guests cannot be greater than max_guests.',
        [],
        422
    );
}


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
| Validate IDs
|--------------------------------------------------------------------------
*/

if ($leadId > 0) {

    $stmt = $conn->prepare("
        SELECT id
        FROM leads
        WHERE id = ?
          AND domain = 'dining'
        LIMIT 1
    ");

    if (!$stmt) {
        jsonResponse(
            false,
            'Failed to prepare lead validation query.',
            [],
            500
        );
    }

    $stmt->bind_param('i', $leadId);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();

        jsonResponse(
            false,
            'Dining lead not found.',
            [],
            404
        );
    }

    $stmt->close();
}


if ($diningPackageId > 0) {

    $stmt = $conn->prepare("
        SELECT id
        FROM dining_packages
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        jsonResponse(
            false,
            'Failed to prepare dining package validation query.',
            [],
            500
        );
    }

    $stmt->bind_param('i', $diningPackageId);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();

        jsonResponse(
            false,
            'Dining package not found.',
            [],
            404
        );
    }

    $stmt->close();
}


if ($foodItemId > 0) {

    $stmt = $conn->prepare("
        SELECT id
        FROM food_items
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        jsonResponse(
            false,
            'Failed to prepare food item validation query.',
            [],
            500
        );
    }

    $stmt->bind_param('i', $foodItemId);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();

        jsonResponse(
            false,
            'Food item not found.',
            [],
            404
        );
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Base Conditions
|--------------------------------------------------------------------------
*/

$baseWhere = [
    "l.domain = 'dining'"
];

$baseParams = [];
$baseTypes = '';


/*
|--------------------------------------------------------------------------
| Staff Scope
|--------------------------------------------------------------------------
*/

if ($currentUserRole === 'staff') {

    $baseWhere[] = "l.assigned_admin_id = ?";
    $baseParams[] = $currentUserId;
    $baseTypes .= 'i';
}


/*
|--------------------------------------------------------------------------
| Lead ID
|--------------------------------------------------------------------------
*/

if ($leadId > 0) {

    $baseWhere[] = "l.id = ?";
    $baseParams[] = $leadId;
    $baseTypes .= 'i';
}


/*
|--------------------------------------------------------------------------
| Package Filter
|--------------------------------------------------------------------------
*/

if ($diningPackageId > 0) {

    $baseWhere[] = "dld.dining_package_id = ?";
    $baseParams[] = $diningPackageId;
    $baseTypes .= 'i';
}


/*
|--------------------------------------------------------------------------
| Meal Type
|--------------------------------------------------------------------------
*/

if ($mealType !== 'all') {

    $baseWhere[] = "dld.meal_type = ?";
    $baseParams[] = $mealType;
    $baseTypes .= 's';
}


/*
|--------------------------------------------------------------------------
| Lead Status
|--------------------------------------------------------------------------
*/

if ($leadStatus !== 'all') {

    $baseWhere[] = "l.status = ?";
    $baseParams[] = $leadStatus;
    $baseTypes .= 's';
}


/*
|--------------------------------------------------------------------------
| Lead Created Date
|--------------------------------------------------------------------------
*/

if ($fromDate !== null) {

    $baseWhere[] = "DATE(l.created_at) >= ?";
    $baseParams[] = $fromDate;
    $baseTypes .= 's';
}

if ($toDate !== null) {

    $baseWhere[] = "DATE(l.created_at) <= ?";
    $baseParams[] = $toDate;
    $baseTypes .= 's';
}


/*
|--------------------------------------------------------------------------
| Dining Event Date
|--------------------------------------------------------------------------
*/

if ($diningDateFrom !== null) {

    $baseWhere[] = "dld.dining_date >= ?";
    $baseParams[] = $diningDateFrom;
    $baseTypes .= 's';
}

if ($diningDateTo !== null) {

    $baseWhere[] = "dld.dining_date <= ?";
    $baseParams[] = $diningDateTo;
    $baseTypes .= 's';
}


/*
|--------------------------------------------------------------------------
| Guest Filters
|--------------------------------------------------------------------------
*/

if ($minGuests > 0) {

    $baseWhere[] = "COALESCE(dld.guest_count, 0) >= ?";
    $baseParams[] = $minGuests;
    $baseTypes .= 'i';
}

if ($maxGuests > 0) {

    $baseWhere[] = "COALESCE(dld.guest_count, 0) <= ?";
    $baseParams[] = $maxGuests;
    $baseTypes .= 'i';
}


/*
|--------------------------------------------------------------------------
| Amount Filters
|--------------------------------------------------------------------------
*/

if ($minAmount !== null) {

    $baseWhere[] = "COALESCE(l.converted_amount, 0) >= ?";
    $baseParams[] = $minAmount;
    $baseTypes .= 'd';
}

if ($maxAmount !== null) {

    $baseWhere[] = "COALESCE(l.converted_amount, 0) <= ?";
    $baseParams[] = $maxAmount;
    $baseTypes .= 'd';
}


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== null) {

    $searchLike = '%' . $search . '%';

    $baseWhere[] = "(
        l.lead_reference LIKE ?
        OR l.customer_name LIKE ?
        OR l.customer_phone LIKE ?
        OR l.customer_email LIKE ?
        OR COALESCE(dp.name, '') LIKE ?
        OR COALESCE(dld.other_requirements, '') LIKE ?
    )";

    for ($i = 0; $i < 6; $i++) {
        $baseParams[] = $searchLike;
        $baseTypes .= 's';
    }
}


/*
|--------------------------------------------------------------------------
| Food Item Filter
|--------------------------------------------------------------------------
*/

if ($foodItemId > 0) {

    $baseWhere[] = "
        EXISTS (
            SELECT 1
            FROM dining_lead_items dli_filter
            WHERE dli_filter.lead_id = l.id
              AND dli_filter.food_item_id = ?
        )
    ";

    $baseParams[] = $foodItemId;
    $baseTypes .= 'i';
}


$baseWhereSql = implode(' AND ', $baseWhere);


/*
|--------------------------------------------------------------------------
| Summary
|--------------------------------------------------------------------------
*/

$summarySql = "
    SELECT

        COUNT(*) AS total_leads,

        COUNT(
            CASE
                WHEN l.status = 'new'
                THEN 1
            END
        ) AS new_leads,

        COUNT(
            CASE
                WHEN l.status = 'contacted'
                THEN 1
            END
        ) AS contacted_leads,

        COUNT(
            CASE
                WHEN l.status = 'follow_up'
                THEN 1
            END
        ) AS follow_up_leads,

        COUNT(
            CASE
                WHEN l.status = 'converted'
                THEN 1
            END
        ) AS converted_leads,

        COUNT(
            CASE
                WHEN l.status = 'lost'
                THEN 1
            END
        ) AS lost_leads,

        COUNT(
            CASE
                WHEN l.status = 'cancelled'
                THEN 1
            END
        ) AS cancelled_leads,

        COALESCE(
            SUM(
                CASE
                    WHEN l.status = 'converted'
                    THEN COALESCE(l.converted_amount, 0)
                    ELSE 0
                END
            ),
            0
        ) AS converted_revenue,

        COALESCE(
            SUM(
                CASE
                    WHEN l.status IN ('new', 'contacted', 'follow_up')
                    THEN COALESCE(l.estimated_amount, 0)
                    ELSE 0
                END
            ),
            0
        ) AS pending_estimated_revenue,

        COALESCE(
            SUM(COALESCE(dld.guest_count, 0)),
            0
        ) AS total_guests,

        COALESCE(
            AVG(
                CASE
                    WHEN dld.guest_count IS NOT NULL
                    THEN dld.guest_count
                END
            ),
            0
        ) AS average_guests

    FROM leads l

    INNER JOIN dining_lead_details dld
        ON dld.lead_id = l.id

    LEFT JOIN dining_packages dp
        ON dp.id = dld.dining_package_id

    WHERE {$baseWhereSql}
";


$stmt = $conn->prepare($summarySql);

if (!$stmt) {
    jsonResponse(
        false,
        'Failed to prepare summary query.',
        [],
        500
    );
}

if (!empty($baseParams)) {
    $stmt->bind_param($baseTypes, ...$baseParams);
}

$stmt->execute();

$summary = $stmt->get_result()->fetch_assoc();

$stmt->close();


$totalLeads = (int)($summary['total_leads'] ?? 0);
$newLeads = (int)($summary['new_leads'] ?? 0);
$contactedLeads = (int)($summary['contacted_leads'] ?? 0);
$followUpLeads = (int)($summary['follow_up_leads'] ?? 0);
$convertedLeads = (int)($summary['converted_leads'] ?? 0);
$lostLeads = (int)($summary['lost_leads'] ?? 0);
$cancelledLeads = (int)($summary['cancelled_leads'] ?? 0);

$convertedRevenue = (float)($summary['converted_revenue'] ?? 0);
$pendingEstimatedRevenue = (float)($summary['pending_estimated_revenue'] ?? 0);

$totalGuests = (int)($summary['total_guests'] ?? 0);
$averageGuests = (float)($summary['average_guests'] ?? 0);

$conversionRate = $totalLeads > 0
    ? round(($convertedLeads / $totalLeads) * 100, 2)
    : 0;


/*
|--------------------------------------------------------------------------
| Count - Pagination
|--------------------------------------------------------------------------
*/

$countSql = "
    SELECT COUNT(*) AS total

    FROM leads l

    INNER JOIN dining_lead_details dld
        ON dld.lead_id = l.id

    LEFT JOIN dining_packages dp
        ON dp.id = dld.dining_package_id

    WHERE {$baseWhereSql}
";

$stmt = $conn->prepare($countSql);

if (!$stmt) {
    jsonResponse(
        false,
        'Failed to prepare count query.',
        [],
        500
    );
}

if (!empty($baseParams)) {
    $stmt->bind_param($baseTypes, ...$baseParams);
}

$stmt->execute();

$countRow = $stmt->get_result()->fetch_assoc();

$stmt->close();

$totalRecords = (int)($countRow['total'] ?? 0);

$totalPages = $totalRecords > 0
    ? (int)ceil($totalRecords / $limit)
    : 0;

$offset = ($page - 1) * $limit;


/*
|--------------------------------------------------------------------------
| Detailed Records
|--------------------------------------------------------------------------
*/

$recordsSql = "
    SELECT

        l.id AS lead_id,
        l.lead_reference,
        l.customer_name,
        l.customer_phone,
        l.customer_email,

        l.event_date AS lead_event_date,

        l.guest_count AS lead_guest_count,

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

        dld.dining_package_id,
        dld.meal_type,
        dld.dining_date,
        dld.dining_time,
        dld.guest_count,

        dld.other_requirements,

        dp.name AS dining_package_name,
        dp.description AS dining_package_description,
        dp.meal_type AS dining_package_meal_type,
        dp.image_path AS dining_package_image,

        au.name AS assigned_admin_name,
        au.email AS assigned_admin_email

    FROM leads l

    INNER JOIN dining_lead_details dld
        ON dld.lead_id = l.id

    LEFT JOIN dining_packages dp
        ON dp.id = dld.dining_package_id

    LEFT JOIN admin_users au
        ON au.id = l.assigned_admin_id

    WHERE {$baseWhereSql}

    ORDER BY {$sortColumn} {$sortOrder}

    LIMIT ? OFFSET ?
";


$recordParams = $baseParams;
$recordTypes = $baseTypes . 'ii';

$recordParams[] = $limit;
$recordParams[] = $offset;


$stmt = $conn->prepare($recordsSql);

if (!$stmt) {
    jsonResponse(
        false,
        'Failed to prepare records query.',
        [],
        500
    );
}

$stmt->bind_param($recordTypes, ...$recordParams);

$stmt->execute();

$result = $stmt->get_result();

$records = [];


while ($row = $result->fetch_assoc()) {

    $leadIdValue = (int)$row['lead_id'];

    /*
    |--------------------------------------------------------------------------
    | Package Items
    |--------------------------------------------------------------------------
    */

    $packageItems = [];

    if (!empty($row['dining_package_id'])) {

        $packageItemStmt = $conn->prepare("
            SELECT
                dpi.food_item_id,
                dpi.quantity,

                fi.name AS food_item_name,
                fi.description AS food_item_description,
                fi.food_type,
                fi.image_path,
                fi.status AS food_item_status

            FROM dining_package_items dpi

            INNER JOIN food_items fi
                ON fi.id = dpi.food_item_id

            WHERE dpi.package_id = ?

            ORDER BY fi.name ASC
        ");

        if ($packageItemStmt) {

            $packageIdValue = (int)$row['dining_package_id'];

            $packageItemStmt->bind_param(
                'i',
                $packageIdValue
            );

            $packageItemStmt->execute();

            $packageItemResult =
                $packageItemStmt->get_result();

            while ($item = $packageItemResult->fetch_assoc()) {

                $packageItems[] = [
                    'food_item_id' => (int)$item['food_item_id'],
                    'food_item_name' => $item['food_item_name'],
                    'food_item_description' =>
                        $item['food_item_description'],
                    'food_type' => $item['food_type'],
                    'image_path' => $item['image_path'],
                    'quantity' => (int)$item['quantity'],
                    'status' => $item['food_item_status']
                ];
            }

            $packageItemStmt->close();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Custom Food Items
    |--------------------------------------------------------------------------
    */

    $customFoodItems = [];

    $customStmt = $conn->prepare("
        SELECT

            dli.id,
            dli.food_item_id,
            dli.quantity,
            dli.notes,

            fi.name AS food_item_name,
            fi.description AS food_item_description,
            fi.food_type,
            fi.image_path,
            fi.status AS food_item_status

        FROM dining_lead_items dli

        INNER JOIN food_items fi
            ON fi.id = dli.food_item_id

        WHERE dli.lead_id = ?

        ORDER BY dli.id ASC
    ");

    if ($customStmt) {

        $customStmt->bind_param(
            'i',
            $leadIdValue
        );

        $customStmt->execute();

        $customResult = $customStmt->get_result();

        while ($item = $customResult->fetch_assoc()) {

            $customFoodItems[] = [
                'id' => (int)$item['id'],
                'food_item_id' => (int)$item['food_item_id'],
                'food_item_name' => $item['food_item_name'],
                'food_item_description' =>
                    $item['food_item_description'],
                'food_type' => $item['food_type'],
                'image_path' => $item['image_path'],
                'quantity' => (int)$item['quantity'],
                'notes' => $item['notes'],
                'status' => $item['food_item_status']
            ];
        }

        $customStmt->close();
    }


    $records[] = [
        'lead_id' => $leadIdValue,
        'lead_reference' => $row['lead_reference'],

        'customer' => [
            'name' => $row['customer_name'],
            'phone' => $row['customer_phone'],
            'email' => $row['customer_email']
        ],

        'dining' => [
            'package_id' =>
                $row['dining_package_id'] !== null
                    ? (int)$row['dining_package_id']
                    : null,

            'package_name' =>
                $row['dining_package_name'],

            'package_description' =>
                $row['dining_package_description'],

            'package_meal_type' =>
                $row['dining_package_meal_type'],

            'package_image' =>
                $row['dining_package_image'],

            'meal_type' =>
                $row['meal_type'],

            'dining_date' =>
                $row['dining_date'],

            'dining_time' =>
                $row['dining_time'],

            'guest_count' =>
                $row['guest_count'] !== null
                    ? (int)$row['guest_count']
                    : null,

            'other_requirements' =>
                $row['other_requirements']
        ],

        'lead' => [
            'event_date' => $row['lead_event_date'],
            'guest_count' =>
                $row['lead_guest_count'] !== null
                    ? (int)$row['lead_guest_count']
                    : null,

            'preferred_time' =>
                $row['preferred_time'],

            'requirement' =>
                $row['requirement']
        ],

        'status' => $row['status'],

        'amounts' => [
            'estimated_amount' =>
                $row['estimated_amount'] !== null
                    ? (float)$row['estimated_amount']
                    : null,

            'converted_amount' =>
                $row['converted_amount'] !== null
                    ? (float)$row['converted_amount']
                    : null
        ],

        'conversion' => [
            'converted_at' =>
                $row['converted_at']
        ],

        'source' => $row['source'],

        'assigned_admin' => [
            'id' =>
                $row['assigned_admin_id'] !== null
                    ? (int)$row['assigned_admin_id']
                    : null,

            'name' =>
                $row['assigned_admin_name'],

            'email' =>
                $row['assigned_admin_email']
        ],

        'package_items' => $packageItems,

        'custom_food_items' => $customFoodItems,

        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at']
    ];
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Status Summary
|--------------------------------------------------------------------------
*/

$statusSql = "
    SELECT
        l.status,
        COUNT(*) AS total,
        COALESCE(
            SUM(
                CASE
                    WHEN l.status = 'converted'
                    THEN COALESCE(l.converted_amount, 0)
                    ELSE 0
                END
            ),
            0
        ) AS revenue

    FROM leads l

    INNER JOIN dining_lead_details dld
        ON dld.lead_id = l.id

    LEFT JOIN dining_packages dp
        ON dp.id = dld.dining_package_id

    WHERE {$baseWhereSql}

    GROUP BY l.status

    ORDER BY FIELD(
        l.status,
        'new',
        'contacted',
        'follow_up',
        'converted',
        'lost',
        'cancelled'
    )
";

$stmt = $conn->prepare($statusSql);

if (!$stmt) {
    jsonResponse(
        false,
        'Failed to prepare status summary query.',
        [],
        500
    );
}

if (!empty($baseParams)) {
    $stmt->bind_param($baseTypes, ...$baseParams);
}

$stmt->execute();

$statusResult = $stmt->get_result();

$statusSummary = [];

while ($row = $statusResult->fetch_assoc()) {

    $statusSummary[] = [
        'status' => $row['status'],
        'total' => (int)$row['total'],
        'revenue' => (float)$row['revenue']
    ];
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Meal Type Summary
|--------------------------------------------------------------------------
*/

$mealSql = "
    SELECT

        dld.meal_type,

        COUNT(*) AS total,

        COALESCE(
            SUM(
                CASE
                    WHEN l.status = 'converted'
                    THEN COALESCE(l.converted_amount, 0)
                    ELSE 0
                END
            ),
            0
        ) AS revenue,

        COALESCE(
            SUM(dld.guest_count),
            0
        ) AS guests

    FROM leads l

    INNER JOIN dining_lead_details dld
        ON dld.lead_id = l.id

    LEFT JOIN dining_packages dp
        ON dp.id = dld.dining_package_id

    WHERE {$baseWhereSql}

    GROUP BY dld.meal_type

    ORDER BY total DESC
";

$stmt = $conn->prepare($mealSql);

if (!$stmt) {
    jsonResponse(
        false,
        'Failed to prepare meal type query.',
        [],
        500
    );
}

if (!empty($baseParams)) {
    $stmt->bind_param($baseTypes, ...$baseParams);
}

$stmt->execute();

$mealResult = $stmt->get_result();

$mealSummary = [];

while ($row = $mealResult->fetch_assoc()) {

    $mealSummary[] = [
        'meal_type' => $row['meal_type'],
        'total_leads' => (int)$row['total'],
        'guests' => (int)$row['guests'],
        'converted_revenue' => (float)$row['revenue']
    ];
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Dining Package Performance
|--------------------------------------------------------------------------
|
| IMPORTANT:
| dining_packages has no rate/amount column.
| Revenue comes only from converted leads.
|
*/

$packageWhere = [
    "l.domain = 'dining'"
];

$packageParams = [];
$packageTypes = '';

if ($currentUserRole === 'staff') {

    $packageWhere[] = "l.assigned_admin_id = ?";
    $packageParams[] = $currentUserId;
    $packageTypes .= 'i';
}

if ($fromDate !== null) {

    $packageWhere[] = "DATE(l.created_at) >= ?";
    $packageParams[] = $fromDate;
    $packageTypes .= 's';
}

if ($toDate !== null) {

    $packageWhere[] = "DATE(l.created_at) <= ?";
    $packageParams[] = $toDate;
    $packageTypes .= 's';
}

if ($diningDateFrom !== null) {

    $packageWhere[] = "dld.dining_date >= ?";
    $packageParams[] = $diningDateFrom;
    $packageTypes .= 's';
}

if ($diningDateTo !== null) {

    $packageWhere[] = "dld.dining_date <= ?";
    $packageParams[] = $diningDateTo;
    $packageTypes .= 's';
}

if ($mealType !== 'all') {

    $packageWhere[] = "dld.meal_type = ?";
    $packageParams[] = $mealType;
    $packageTypes .= 's';
}

if ($leadStatus !== 'all') {

    $packageWhere[] = "l.status = ?";
    $packageParams[] = $leadStatus;
    $packageTypes .= 's';
}

if ($diningPackageId > 0) {

    $packageWhere[] = "dp.id = ?";
    $packageParams[] = $diningPackageId;
    $packageTypes .= 'i';
}

if ($minGuests > 0) {

    $packageWhere[] = "COALESCE(dld.guest_count, 0) >= ?";
    $packageParams[] = $minGuests;
    $packageTypes .= 'i';
}

if ($maxGuests > 0) {

    $packageWhere[] = "COALESCE(dld.guest_count, 0) <= ?";
    $packageParams[] = $maxGuests;
    $packageTypes .= 'i';
}

if ($minAmount !== null) {

    $packageWhere[] = "COALESCE(l.converted_amount, 0) >= ?";
    $packageParams[] = $minAmount;
    $packageTypes .= 'd';
}

if ($maxAmount !== null) {

    $packageWhere[] = "COALESCE(l.converted_amount, 0) <= ?";
    $packageParams[] = $maxAmount;
    $packageTypes .= 'd';
}

$packageWhereSql = implode(
    ' AND ',
    $packageWhere
);


/*
|--------------------------------------------------------------------------
| Pre-Aggregated Package Performance
|--------------------------------------------------------------------------
*/

$packageSql = "
    SELECT

        dp.id AS dining_package_id,
        dp.name AS dining_package_name,
        dp.meal_type AS package_meal_type,
        dp.status AS package_status,

        COUNT(l.id) AS total_leads,

        COUNT(
            CASE
                WHEN l.status = 'converted'
                THEN 1
            END
        ) AS converted_leads,

        COUNT(
            CASE
                WHEN l.status IN ('new','contacted','follow_up')
                THEN 1
            END
        ) AS active_leads,

        COALESCE(
            SUM(
                CASE
                    WHEN l.status = 'converted'
                    THEN COALESCE(l.converted_amount, 0)
                    ELSE 0
                END
            ),
            0
        ) AS converted_revenue,

        COALESCE(
            SUM(COALESCE(dld.guest_count, 0)),
            0
        ) AS total_guests,

        COALESCE(
            AVG(
                CASE
                    WHEN dld.guest_count IS NOT NULL
                    THEN dld.guest_count
                END
            ),
            0
        ) AS average_guests

    FROM dining_packages dp

    LEFT JOIN dining_lead_details dld
        ON dld.dining_package_id = dp.id

    LEFT JOIN leads l
        ON l.id = dld.lead_id
        AND {$packageWhereSql}

    GROUP BY
        dp.id,
        dp.name,
        dp.meal_type,
        dp.status

    ORDER BY
        converted_revenue DESC,
        total_leads DESC
";

$stmt = $conn->prepare($packageSql);

if (!$stmt) {
    jsonResponse(
        false,
        'Failed to prepare package performance query.',
        [],
        500
    );
}

if (!empty($packageParams)) {
    $stmt->bind_param(
        $packageTypes,
        ...$packageParams
    );
}

$stmt->execute();

$packageResult = $stmt->get_result();

$packagePerformance = [];

while ($row = $packageResult->fetch_assoc()) {

    $packageTotal = (int)$row['total_leads'];
    $packageConverted = (int)$row['converted_leads'];

    $packageConversionRate =
        $packageTotal > 0
            ? round(
                ($packageConverted / $packageTotal) * 100,
                2
            )
            : 0;

    $packagePerformance[] = [
        'dining_package_id' =>
            (int)$row['dining_package_id'],

        'dining_package_name' =>
            $row['dining_package_name'],

        'meal_type' =>
            $row['package_meal_type'],

        'status' =>
            $row['package_status'],

        'total_leads' =>
            $packageTotal,

        'converted_leads' =>
            $packageConverted,

        'active_leads' =>
            (int)$row['active_leads'],

        'conversion_rate' =>
            $packageConversionRate,

        'converted_revenue' =>
            (float)$row['converted_revenue'],

        'total_guests' =>
            (int)$row['total_guests'],

        'average_guests' =>
            (float)$row['average_guests']
    ];
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Monthly Report
|--------------------------------------------------------------------------
*/

$monthlySql = "
    SELECT

        DATE_FORMAT(
            dld.dining_date,
            '%Y-%m'
        ) AS month,

        COUNT(*) AS total_leads,

        COUNT(
            CASE
                WHEN l.status = 'converted'
                THEN 1
            END
        ) AS converted_leads,

        COALESCE(
            SUM(
                CASE
                    WHEN l.status = 'converted'
                    THEN COALESCE(l.converted_amount, 0)
                    ELSE 0
                END
            ),
            0
        ) AS converted_revenue,

        COALESCE(
            SUM(
                COALESCE(dld.guest_count, 0)
            ),
            0
        ) AS total_guests

    FROM leads l

    INNER JOIN dining_lead_details dld
        ON dld.lead_id = l.id

    LEFT JOIN dining_packages dp
        ON dp.id = dld.dining_package_id

    WHERE {$baseWhereSql}

    GROUP BY DATE_FORMAT(
        dld.dining_date,
        '%Y-%m'
    )

    ORDER BY month ASC
";

$stmt = $conn->prepare($monthlySql);

if (!$stmt) {
    jsonResponse(
        false,
        'Failed to prepare monthly report query.',
        [],
        500
    );
}

if (!empty($baseParams)) {
    $stmt->bind_param(
        $baseTypes,
        ...$baseParams
    );
}

$stmt->execute();

$monthlyResult = $stmt->get_result();

$monthlyReport = [];

while ($row = $monthlyResult->fetch_assoc()) {

    $monthTotal = (int)$row['total_leads'];
    $monthConverted = (int)$row['converted_leads'];

    $monthlyReport[] = [
        'month' => $row['month'],
        'total_leads' => $monthTotal,
        'converted_leads' => $monthConverted,

        'conversion_rate' =>
            $monthTotal > 0
                ? round(
                    ($monthConverted / $monthTotal) * 100,
                    2
                )
                : 0,

        'converted_revenue' =>
            (float)$row['converted_revenue'],

        'total_guests' =>
            (int)$row['total_guests']
    ];
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Upcoming Dining
|--------------------------------------------------------------------------
*/

$today = date('Y-m-d');

$upcomingWhere = [
    "l.domain = 'dining'",
    "dld.dining_date >= ?",
    "l.status IN ('new','contacted','follow_up','converted')"
];

$upcomingParams = [
    $today
];

$upcomingTypes = 's';


if ($currentUserRole === 'staff') {

    $upcomingWhere[] =
        "l.assigned_admin_id = ?";

    $upcomingParams[] =
        $currentUserId;

    $upcomingTypes .= 'i';
}


$upcomingSql = "
    SELECT

        l.id AS lead_id,
        l.lead_reference,

        l.customer_name,
        l.customer_phone,
        l.customer_email,

        l.status,

        l.converted_amount,

        dld.dining_date,
        dld.dining_time,
        dld.meal_type,
        dld.guest_count,

        dp.name AS dining_package_name

    FROM leads l

    INNER JOIN dining_lead_details dld
        ON dld.lead_id = l.id

    LEFT JOIN dining_packages dp
        ON dp.id = dld.dining_package_id

    WHERE " . implode(
        ' AND ',
        $upcomingWhere
    ) . "

    ORDER BY
        dld.dining_date ASC,
        dld.dining_time ASC

    LIMIT 20
";


$stmt = $conn->prepare($upcomingSql);

if (!$stmt) {
    jsonResponse(
        false,
        'Failed to prepare upcoming dining query.',
        [],
        500
    );
}

$stmt->bind_param(
    $upcomingTypes,
    ...$upcomingParams
);

$stmt->execute();

$upcomingResult = $stmt->get_result();

$upcomingDining = [];

while ($row = $upcomingResult->fetch_assoc()) {

    $upcomingDining[] = [
        'lead_id' =>
            (int)$row['lead_id'],

        'lead_reference' =>
            $row['lead_reference'],

        'customer_name' =>
            $row['customer_name'],

        'customer_phone' =>
            $row['customer_phone'],

        'customer_email' =>
            $row['customer_email'],

        'status' =>
            $row['status'],

        'dining_date' =>
            $row['dining_date'],

        'dining_time' =>
            $row['dining_time'],

        'meal_type' =>
            $row['meal_type'],

        'guest_count' =>
            $row['guest_count'] !== null
                ? (int)$row['guest_count']
                : null,

        'dining_package_name' =>
            $row['dining_package_name'],

        'converted_amount' =>
            $row['converted_amount'] !== null
                ? (float)$row['converted_amount']
                : null
    ];
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Today's Dining Summary
|--------------------------------------------------------------------------
*/

$todayWhere = [
    "l.domain = 'dining'",
    "dld.dining_date = ?"
];

$todayParams = [
    $today
];

$todayTypes = 's';

if ($currentUserRole === 'staff') {

    $todayWhere[] =
        "l.assigned_admin_id = ?";

    $todayParams[] =
        $currentUserId;

    $todayTypes .= 'i';
}


$todaySql = "
    SELECT

        COUNT(*) AS total,

        COUNT(
            CASE
                WHEN l.status = 'converted'
                THEN 1
            END
        ) AS converted,

        COUNT(
            CASE
                WHEN l.status IN ('new','contacted','follow_up')
                THEN 1
            END
        ) AS pending,

        COALESCE(
            SUM(
                CASE
                    WHEN l.status = 'converted'
                    THEN COALESCE(l.converted_amount, 0)
                    ELSE 0
                END
            ),
            0
        ) AS revenue,

        COALESCE(
            SUM(dld.guest_count),
            0
        ) AS guests

    FROM leads l

    INNER JOIN dining_lead_details dld
        ON dld.lead_id = l.id

    WHERE " . implode(
        ' AND ',
        $todayWhere
    );


$stmt = $conn->prepare($todaySql);

if (!$stmt) {
    jsonResponse(
        false,
        'Failed to prepare today summary query.',
        [],
        500
    );
}

$stmt->bind_param(
    $todayTypes,
    ...$todayParams
);

$stmt->execute();

$todaySummary =
    $stmt->get_result()->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| Food Item Usage
|--------------------------------------------------------------------------
*/

$foodUsageWhere = [
    "l.domain = 'dining'"
];

$foodUsageParams = [];
$foodUsageTypes = '';


if ($currentUserRole === 'staff') {

    $foodUsageWhere[] =
        "l.assigned_admin_id = ?";

    $foodUsageParams[] =
        $currentUserId;

    $foodUsageTypes .= 'i';
}


if ($fromDate !== null) {

    $foodUsageWhere[] =
        "DATE(l.created_at) >= ?";

    $foodUsageParams[] =
        $fromDate;

    $foodUsageTypes .= 's';
}


if ($toDate !== null) {

    $foodUsageWhere[] =
        "DATE(l.created_at) <= ?";

    $foodUsageParams[] =
        $toDate;

    $foodUsageTypes .= 's';
}


if ($diningDateFrom !== null) {

    $foodUsageWhere[] =
        "dld.dining_date >= ?";

    $foodUsageParams[] =
        $diningDateFrom;

    $foodUsageTypes .= 's';
}


if ($diningDateTo !== null) {

    $foodUsageWhere[] =
        "dld.dining_date <= ?";

    $foodUsageParams[] =
        $diningDateTo;

    $foodUsageTypes .= 's';
}


$foodUsageWhereSql =
    implode(
        ' AND ',
        $foodUsageWhere
    );


$foodUsageSql = "
    SELECT

        fi.id AS food_item_id,
        fi.name AS food_item_name,
        fi.food_type,

        COUNT(DISTINCT dli.lead_id)
            AS lead_count,

        COALESCE(
            SUM(dli.quantity),
            0
        ) AS total_quantity

    FROM dining_lead_items dli

    INNER JOIN food_items fi
        ON fi.id = dli.food_item_id

    INNER JOIN leads l
        ON l.id = dli.lead_id

    INNER JOIN dining_lead_details dld
        ON dld.lead_id = l.id

    WHERE {$foodUsageWhereSql}

    GROUP BY
        fi.id,
        fi.name,
        fi.food_type

    ORDER BY total_quantity DESC

    LIMIT 20
";


$stmt = $conn->prepare($foodUsageSql);

if (!$stmt) {
    jsonResponse(
        false,
        'Failed to prepare food usage query.',
        [],
        500
    );
}

if (!empty($foodUsageParams)) {

    $stmt->bind_param(
        $foodUsageTypes,
        ...$foodUsageParams
    );
}

$stmt->execute();

$foodUsageResult =
    $stmt->get_result();

$foodUsage = [];

while ($row = $foodUsageResult->fetch_assoc()) {

    $foodUsage[] = [
        'food_item_id' =>
            (int)$row['food_item_id'],

        'food_item_name' =>
            $row['food_item_name'],

        'food_type' =>
            $row['food_type'],

        'lead_count' =>
            (int)$row['lead_count'],

        'total_quantity' =>
            (int)$row['total_quantity']
    ];
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Chart.js - Monthly Revenue
|--------------------------------------------------------------------------
*/

$revenueLabels = [];
$revenueData = [];
$monthlyLeadData = [];
$monthlyGuestData = [];

foreach ($monthlyReport as $month) {

    $revenueLabels[] =
        $month['month'];

    $revenueData[] =
        $month['converted_revenue'];

    $monthlyLeadData[] =
        $month['total_leads'];

    $monthlyGuestData[] =
        $month['total_guests'];
}


/*
|--------------------------------------------------------------------------
| Chart.js - Status
|--------------------------------------------------------------------------
*/

$statusLabels = [];
$statusData = [];
$statusRevenueData = [];

foreach ($statusSummary as $item) {

    $statusLabels[] =
        ucfirst(str_replace('_', ' ', $item['status']));

    $statusData[] =
        $item['total'];

    $statusRevenueData[] =
        $item['revenue'];
}


/*
|--------------------------------------------------------------------------
| Chart.js - Meal Type
|--------------------------------------------------------------------------
*/

$mealLabels = [];
$mealLeadData = [];
$mealRevenueData = [];

foreach ($mealSummary as $item) {

    $mealLabels[] =
        ucfirst($item['meal_type']);

    $mealLeadData[] =
        $item['total_leads'];

    $mealRevenueData[] =
        $item['converted_revenue'];
}


/*
|--------------------------------------------------------------------------
| Chart.js - Package Revenue
|--------------------------------------------------------------------------
*/

$packageLabels = [];
$packageRevenueData = [];
$packageLeadData = [];

foreach ($packagePerformance as $item) {

    $packageLabels[] =
        $item['dining_package_name'];

    $packageRevenueData[] =
        $item['converted_revenue'];

    $packageLeadData[] =
        $item['total_leads'];
}


/*
|--------------------------------------------------------------------------
| Final Response
|--------------------------------------------------------------------------
*/

jsonResponse(
    true,
    'Dining report fetched successfully.',
    [

        'meta' => [
            'report' => 'dining_report',
            'generated_at' => date('Y-m-d H:i:s'),
            'currency' => 'INR',
            'revenue_source' =>
                'converted_amount from converted dining leads'
        ],


        'user_scope' => [
            'role' => $currentUserRole,
            'user_id' => $currentUserId,

            'scope' =>
                $currentUserRole === 'staff'
                    ? 'assigned dining leads only'
                    : 'all dining leads'
        ],


        'filters' => [
            'lead_id' =>
                $leadId > 0 ? $leadId : null,

            'dining_package_id' =>
                $diningPackageId > 0
                    ? $diningPackageId
                    : null,

            'food_item_id' =>
                $foodItemId > 0
                    ? $foodItemId
                    : null,

            'lead_status' =>
                $leadStatus,

            'meal_type' =>
                $mealType,

            'search' =>
                $search,

            'from_date' =>
                $fromDate,

            'to_date' =>
                $toDate,

            'dining_date_from' =>
                $diningDateFrom,

            'dining_date_to' =>
                $diningDateTo,

            'min_guests' =>
                $minGuests > 0
                    ? $minGuests
                    : null,

            'max_guests' =>
                $maxGuests > 0
                    ? $maxGuests
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


        'kpis' => [

            'total_leads' =>
                $totalLeads,

            'new_leads' =>
                $newLeads,

            'contacted_leads' =>
                $contactedLeads,

            'follow_up_leads' =>
                $followUpLeads,

            'converted_leads' =>
                $convertedLeads,

            'lost_leads' =>
                $lostLeads,

            'cancelled_leads' =>
                $cancelledLeads,

            'conversion_rate' =>
                $conversionRate,

            'converted_revenue' =>
                round($convertedRevenue, 2),

            'pending_estimated_revenue' =>
                round($pendingEstimatedRevenue, 2),

            'total_guests' =>
                $totalGuests,

            'average_guests' =>
                round($averageGuests, 2)
        ],


        'today' => [

            'date' =>
                $today,

            'total_dining' =>
                (int)($todaySummary['total'] ?? 0),

            'converted_dining' =>
                (int)($todaySummary['converted'] ?? 0),

            'pending_dining' =>
                (int)($todaySummary['pending'] ?? 0),

            'revenue' =>
                (float)($todaySummary['revenue'] ?? 0),

            'guests' =>
                (int)($todaySummary['guests'] ?? 0)
        ],


        'status_summary' =>
            $statusSummary,


        'meal_type_summary' =>
            $mealSummary,


        'package_performance' =>
            $packagePerformance,


        'food_item_usage' =>
            $foodUsage,


        'monthly_report' =>
            $monthlyReport,


        'upcoming_dining' =>
            $upcomingDining,


        'records' =>
            $records,


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
                $page > 1 && $totalPages > 0
        ],


        /*
        |--------------------------------------------------------------------------
        | Chart.js Ready Data
        |--------------------------------------------------------------------------
        */

        'charts' => [

            'revenue_trend' => [
                'labels' =>
                    $revenueLabels,

                'datasets' => [
                    [
                        'label' =>
                            'Dining Converted Revenue',

                        'data' =>
                            $revenueData
                    ]
                ]
            ],


            'lead_trend' => [
                'labels' =>
                    $revenueLabels,

                'datasets' => [
                    [
                        'label' =>
                            'Dining Leads',

                        'data' =>
                            $monthlyLeadData
                    ]
                ]
            ],


            'guest_trend' => [
                'labels' =>
                    $revenueLabels,

                'datasets' => [
                    [
                        'label' =>
                            'Dining Guests',

                        'data' =>
                            $monthlyGuestData
                    ]
                ]
            ],


            'lead_status' => [
                'labels' =>
                    $statusLabels,

                'datasets' => [
                    [
                        'label' =>
                            'Dining Leads',

                        'data' =>
                            $statusData
                    ]
                ]
            ],


            'status_revenue' => [
                'labels' =>
                    $statusLabels,

                'datasets' => [
                    [
                        'label' =>
                            'Revenue',

                        'data' =>
                            $statusRevenueData
                    ]
                ]
            ],


            'meal_type' => [
                'labels' =>
                    $mealLabels,

                'datasets' => [
                    [
                        'label' =>
                            'Dining Leads',

                        'data' =>
                            $mealLeadData
                    ]
                ]
            ],


            'meal_type_revenue' => [
                'labels' =>
                    $mealLabels,

                'datasets' => [
                    [
                        'label' =>
                            'Revenue',

                        'data' =>
                            $mealRevenueData
                    ]
                ]
            ],


            'package_revenue' => [
                'labels' =>
                    $packageLabels,

                'datasets' => [
                    [
                        'label' =>
                            'Package Revenue',

                        'data' =>
                            $packageRevenueData
                    ]
                ]
            ],


            'package_leads' => [
                'labels' =>
                    $packageLabels,

                'datasets' => [
                    [
                        'label' =>
                            'Package Leads',

                        'data' =>
                            $packageLeadData
                    ]
                ]
            ]
        ]
    ]
);