<?php

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';


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
| Allowed Query Parameters
|--------------------------------------------------------------------------
*/

$allowedParams = [
    'search',
    'status',
    'page',
    'limit',
    'sort_by',
    'sort_order'
];


/*
|--------------------------------------------------------------------------
| Unknown Query Parameter Validation
|--------------------------------------------------------------------------
*/

$unknownParams = array_diff(
    array_keys($_GET),
    $allowedParams
);

if (!empty($unknownParams)) {
    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' => 'Unknown query parameter(s) found.',
        'unknown_parameters' => array_values($unknownParams)
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Search Validation
|--------------------------------------------------------------------------
*/

$search = '';

if (array_key_exists('search', $_GET)) {

    if (!is_string($_GET['search'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'search must be a string.'
        ]);

        exit;
    }

    $search = trim($_GET['search']);

    if (mb_strlen($search) > 100) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'search cannot exceed 100 characters.'
        ]);

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Status Validation
|--------------------------------------------------------------------------
*/

$status = '';

if (array_key_exists('status', $_GET)) {

    if (!is_string($_GET['status'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'status must be a string.'
        ]);

        exit;
    }

    $status = strtolower(trim($_GET['status']));

    if ($status === '') {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'status cannot be empty.'
        ]);

        exit;
    }

    $allowedStatuses = [
        'active',
        'inactive'
    ];

    if (!in_array($status, $allowedStatuses, true)) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Invalid status.',
            'allowed_statuses' => $allowedStatuses
        ]);

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Page Validation
|--------------------------------------------------------------------------
*/

$page = 1;

if (array_key_exists('page', $_GET)) {

    $pageValue = $_GET['page'];

    if (
        !is_string($pageValue) &&
        !is_int($pageValue)
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'page must be a positive integer.'
        ]);

        exit;
    }

    if (
        is_string($pageValue) &&
        !preg_match('/^[0-9]+$/', $pageValue)
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'page must be a positive integer.'
        ]);

        exit;
    }

    $page = (int) $pageValue;

    if ($page <= 0) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'page must be greater than 0.'
        ]);

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Limit Validation
|--------------------------------------------------------------------------
*/

$limit = 20;

if (array_key_exists('limit', $_GET)) {

    $limitValue = $_GET['limit'];

    if (
        !is_string($limitValue) &&
        !is_int($limitValue)
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'limit must be a positive integer.'
        ]);

        exit;
    }

    if (
        is_string($limitValue) &&
        !preg_match('/^[0-9]+$/', $limitValue)
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'limit must be a positive integer.'
        ]);

        exit;
    }

    $limit = (int) $limitValue;

    if ($limit <= 0) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'limit must be greater than 0.'
        ]);

        exit;
    }

    if ($limit > 100) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'limit cannot exceed 100.'
        ]);

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Calculate Offset
|--------------------------------------------------------------------------
*/

$offset = ($page - 1) * $limit;


/*
|--------------------------------------------------------------------------
| Sort By Validation
|--------------------------------------------------------------------------
*/

$sortBy = 'name';

$allowedSortFields = [
    'id',
    'name',
    'status',
    'created_at'
];

if (array_key_exists('sort_by', $_GET)) {

    if (!is_string($_GET['sort_by'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'sort_by must be a string.'
        ]);

        exit;
    }

    $sortBy = trim($_GET['sort_by']);

    if (!in_array($sortBy, $allowedSortFields, true)) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Invalid sort_by value.',
            'allowed_sort_by' => $allowedSortFields
        ]);

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Sort Order Validation
|--------------------------------------------------------------------------
*/

$sortOrder = 'ASC';

if (array_key_exists('sort_order', $_GET)) {

    if (!is_string($_GET['sort_order'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'sort_order must be a string.'
        ]);

        exit;
    }

    $sortOrder = strtoupper(
        trim($_GET['sort_order'])
    );

    if (!in_array($sortOrder, ['ASC', 'DESC'], true)) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Invalid sort_order.',
            'allowed_sort_order' => [
                'ASC',
                'DESC'
            ]
        ]);

        exit;
    }
}


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
| Status Condition
|--------------------------------------------------------------------------
*/

if ($status !== '') {

    $whereConditions[] = 'a.status = ?';

    $whereParams[] = $status;

    $whereTypes .= 's';
}


/*
|--------------------------------------------------------------------------
| Search Condition
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $searchValue = '%' . $search . '%';

    $whereConditions[] = "
        (
            a.name LIKE ?
            OR a.icon LIKE ?
        )
    ";

    $whereParams[] = $searchValue;
    $whereParams[] = $searchValue;

    $whereTypes .= 'ss';
}


/*
|--------------------------------------------------------------------------
| WHERE SQL
|--------------------------------------------------------------------------
*/

$whereSql = '';

if (!empty($whereConditions)) {
    $whereSql =
        ' WHERE ' .
        implode(
            ' AND ',
            $whereConditions
        );
}


/*
|--------------------------------------------------------------------------
| Count Total Records
|--------------------------------------------------------------------------
*/

$countSql = "
    SELECT COUNT(*) AS total
    FROM amenities a
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

    $countBindParams = [];

    $countBindParams[] = $whereTypes;

    foreach ($whereParams as $key => $value) {
        $countBindParams[] = &$whereParams[$key];
    }

    call_user_func_array(
        [$countStmt, 'bind_param'],
        $countBindParams
    );
}


if (!$countStmt->execute()) {

    $countStmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to count amenities.'
    ]);

    exit;
}


$countResult =
    $countStmt->get_result();

$countRow =
    $countResult->fetch_assoc();

$countStmt->close();

$totalRecords =
    (int) ($countRow['total'] ?? 0);


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
| Page Beyond Available Data
|--------------------------------------------------------------------------
*/

if ($totalRecords > 0 && $page > $totalPages) {

    http_response_code(200);

    echo json_encode([
        'status' => true,
        'message' => 'No amenities found for the requested page.',
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
| Fetch Amenities
|--------------------------------------------------------------------------
*/

$listSql = "
    SELECT
        a.id,
        a.name,
        a.icon,
        a.status,
        a.created_at
    FROM amenities a
    $whereSql
    ORDER BY a.$sortBy $sortOrder
    LIMIT ? OFFSET ?
";


$listStmt = $conn->prepare($listSql);

if (!$listStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare amenity list query.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Bind List Parameters
|--------------------------------------------------------------------------
*/

$listBindTypes = $whereTypes . 'ii';

$listBindValues = $whereParams;

$listBindValues[] = $limit;
$listBindValues[] = $offset;

$listBindParams = [];

$listBindParams[] = $listBindTypes;

foreach ($listBindValues as $key => $value) {
    $listBindParams[] = &$listBindValues[$key];
}

call_user_func_array(
    [$listStmt, 'bind_param'],
    $listBindParams
);


/*
|--------------------------------------------------------------------------
| Execute List Query
|--------------------------------------------------------------------------
*/

if (!$listStmt->execute()) {

    $listStmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to fetch amenities.'
    ]);

    exit;
}


$listResult =
    $listStmt->get_result();


/*
|--------------------------------------------------------------------------
| Build Response Data
|--------------------------------------------------------------------------
*/

$amenities = [];

while ($row = $listResult->fetch_assoc()) {

    $amenities[] = [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'icon' => $row['icon'],
        'status' => $row['status'],
        'created_at' => $row['created_at']
    ];
}

$listStmt->close();


/*
|--------------------------------------------------------------------------
| Pagination Flags
|--------------------------------------------------------------------------
*/

$hasPreviousPage =
    $page > 1;

$hasNextPage =
    $totalRecords > 0 &&
    $page < $totalPages;


/*
|--------------------------------------------------------------------------
| Success Response
|--------------------------------------------------------------------------
*/

http_response_code(200);

echo json_encode([
    'status' => true,
    'message' => empty($amenities)
        ? 'No amenities found.'
        : 'Amenities fetched successfully.',
    'data' => $amenities,
    'pagination' => [
        'current_page' => $page,
        'per_page' => $limit,
        'total_records' => $totalRecords,
        'total_pages' => $totalPages,
        'has_previous_page' => $hasPreviousPage,
        'has_next_page' => $hasNextPage
    ],
    'filters' => [
        'search' => $search !== ''
            ? $search
            : null,
        'status' => $status !== ''
            ? $status
            : null,
        'sort_by' => $sortBy,
        'sort_order' => $sortOrder
    ]
]);

exit;