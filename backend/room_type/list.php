<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/middleware.php';

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

$user = requireRole(['admin', 'staff']);

/*
|--------------------------------------------------------------------------
| Request Method
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
| Helper Function
|--------------------------------------------------------------------------
*/

function getQueryParam($key, $default = null)
{
    if (!isset($_GET[$key])) {
        return $default;
    }

    if (is_array($_GET[$key])) {
        return $default;
    }

    return trim((string) $_GET[$key]);
}

/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

$pageInput = getQueryParam('page', '1');
$limitInput = getQueryParam('limit', '20');

/*
|--------------------------------------------------------------------------
| Validate Page
|--------------------------------------------------------------------------
*/

if (
    !ctype_digit($pageInput) ||
    (int) $pageInput < 1
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'page must be a positive integer.'
    ]);

    exit;
}

$page = (int) $pageInput;

/*
|--------------------------------------------------------------------------
| Validate Limit
|--------------------------------------------------------------------------
|
| Maximum 100 records per page.
|
*/

if (
    !ctype_digit($limitInput) ||
    (int) $limitInput < 1
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'limit must be a positive integer.'
    ]);

    exit;
}

$limit = (int) $limitInput;

if ($limit > 100) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'limit cannot be greater than 100.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Calculate Offset
|--------------------------------------------------------------------------
*/

$offset = ($page - 1) * $limit;

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
|
| Searches:
| - name
| - slug
| - description
|
*/

$search = getQueryParam('search', '');

/*
|--------------------------------------------------------------------------
| Search Validation
|--------------------------------------------------------------------------
*/

if (strlen($search) > 100) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'search cannot exceed 100 characters.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/

$status = getQueryParam('status', '');

/*
|--------------------------------------------------------------------------
| Status Validation
|--------------------------------------------------------------------------
*/

if (
    $status !== '' &&
    !in_array($status, ['active', 'inactive'], true)
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Invalid status. Allowed values are active or inactive.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Sorting
|--------------------------------------------------------------------------
*/

$allowedSortColumns = [
    'id' => 'rt.id',
    'name' => 'rt.name',
    'slug' => 'rt.slug',
    'status' => 'rt.status',
    'created_at' => 'rt.created_at',
    'updated_at' => 'rt.updated_at'
];

$sortBy = getQueryParam('sort_by', 'id');
$sortOrder = strtoupper(getQueryParam('sort_order', 'DESC'));

/*
|--------------------------------------------------------------------------
| Validate Sort Column
|--------------------------------------------------------------------------
*/

if (!array_key_exists($sortBy, $allowedSortColumns)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Invalid sort_by value.',
        'allowed_sort_by' => array_keys($allowedSortColumns)
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Validate Sort Order
|--------------------------------------------------------------------------
*/

if (!in_array($sortOrder, ['ASC', 'DESC'], true)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'sort_order must be ASC or DESC.'
    ]);

    exit;
}

$sortColumn = $allowedSortColumns[$sortBy];

/*
|--------------------------------------------------------------------------
| Build WHERE Conditions
|--------------------------------------------------------------------------
*/

$whereConditions = [];
$whereParams = [];
$whereTypes = '';

/*
|--------------------------------------------------------------------------
| Search Condition
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $searchValue = '%' . $search . '%';

    $whereConditions[] = "
        (
            rt.name LIKE ?
            OR rt.slug LIKE ?
            OR rt.description LIKE ?
        )
    ";

    $whereParams[] = $searchValue;
    $whereParams[] = $searchValue;
    $whereParams[] = $searchValue;

    $whereTypes .= 'sss';
}

/*
|--------------------------------------------------------------------------
| Status Condition
|--------------------------------------------------------------------------
*/

if ($status !== '') {

    $whereConditions[] = "rt.status = ?";

    $whereParams[] = $status;

    $whereTypes .= 's';
}

/*
|--------------------------------------------------------------------------
| WHERE Query
|--------------------------------------------------------------------------
*/

$whereSql = '';

if (!empty($whereConditions)) {
    $whereSql = 'WHERE ' . implode(' AND ', $whereConditions);
}

/*
|--------------------------------------------------------------------------
| Count Total Records
|--------------------------------------------------------------------------
*/

$countSql = "
    SELECT COUNT(*) AS total
    FROM room_types rt
    $whereSql
";

$countStmt = $conn->prepare($countSql);

if (!$countStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare count query.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Bind Count Parameters
|--------------------------------------------------------------------------
*/

if (!empty($whereParams)) {

    $bindValues = [];
    $bindValues[] = $whereTypes;

    foreach ($whereParams as $key => $value) {
        $bindValues[] = &$whereParams[$key];
    }

    call_user_func_array(
        [$countStmt, 'bind_param'],
        $bindValues
    );
}

/*
|--------------------------------------------------------------------------
| Execute Count Query
|--------------------------------------------------------------------------
*/

if (!$countStmt->execute()) {

    $countStmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to count room types.'
    ]);

    exit;
}

$countResult = $countStmt->get_result();
$countData = $countResult->fetch_assoc();

$countStmt->close();

$totalRecords = (int) ($countData['total'] ?? 0);

/*
|--------------------------------------------------------------------------
| Calculate Pagination
|--------------------------------------------------------------------------
*/

$totalPages = $totalRecords > 0
    ? (int) ceil($totalRecords / $limit)
    : 0;

/*
|--------------------------------------------------------------------------
| Page Out Of Range
|--------------------------------------------------------------------------
*/

if ($totalPages > 0 && $page > $totalPages) {

    http_response_code(200);

    echo json_encode([
        'status' => true,
        'message' => 'No room types found for the requested page.',
        'data' => [],
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total_records' => $totalRecords,
            'total_pages' => $totalPages,
            'has_previous_page' => $page > 1,
            'has_next_page' => false
        ]
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Fetch Room Types
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        rt.id,
        rt.name,
        rt.slug,
        rt.description,
        rt.status,
        rt.created_at,
        rt.updated_at,

        (
            SELECT COUNT(*)
            FROM rooms r
            WHERE r.room_type_id = rt.id
        ) AS total_rooms,

        (
            SELECT COUNT(*)
            FROM rooms r
            WHERE r.room_type_id = rt.id
            AND r.status = 'active'
        ) AS active_rooms

    FROM room_types rt

    $whereSql

    ORDER BY $sortColumn $sortOrder

    LIMIT ? OFFSET ?
";

/*
|--------------------------------------------------------------------------
| Prepare List Query
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare room type list query.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Bind Parameters
|--------------------------------------------------------------------------
*/

$listParams = $whereParams;
$listTypes = $whereTypes . 'ii';

$listParams[] = $limit;
$listParams[] = $offset;

$bindValues = [];
$bindValues[] = $listTypes;

foreach ($listParams as $key => $value) {
    $bindValues[] = &$listParams[$key];
}

call_user_func_array(
    [$stmt, 'bind_param'],
    $bindValues
);

/*
|--------------------------------------------------------------------------
| Execute List Query
|--------------------------------------------------------------------------
*/

if (!$stmt->execute()) {

    $stmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to fetch room types.'
    ]);

    exit;
}

$result = $stmt->get_result();

$roomTypes = [];

/*
|--------------------------------------------------------------------------
| Fetch Records
|--------------------------------------------------------------------------
*/

while ($row = $result->fetch_assoc()) {

    $roomTypes[] = [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'slug' => $row['slug'],
        'description' => $row['description'],
        'status' => $row['status'],

        'total_rooms' => (int) $row['total_rooms'],
        'active_rooms' => (int) $row['active_rooms'],

        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at']
    ];
}

$stmt->close();

/*
|--------------------------------------------------------------------------
| Pagination Information
|--------------------------------------------------------------------------
*/

$hasPreviousPage = $page > 1;

$hasNextPage = $totalPages > 0 &&
               $page < $totalPages;

/*
|--------------------------------------------------------------------------
| Success Response
|--------------------------------------------------------------------------
*/

http_response_code(200);

echo json_encode([
    'status' => true,
    'message' => count($roomTypes) > 0
        ? 'Room types fetched successfully.'
        : 'No room types found.',

    'data' => $roomTypes,

    'pagination' => [
        'current_page' => $page,
        'per_page' => $limit,
        'total_records' => $totalRecords,
        'total_pages' => $totalPages,
        'has_previous_page' => $hasPreviousPage,
        'has_next_page' => $hasNextPage
    ],

    'filters' => [
        'search' => $search,
        'status' => $status !== '' ? $status : null,
        'sort_by' => $sortBy,
        'sort_order' => $sortOrder
    ]
]);

exit;