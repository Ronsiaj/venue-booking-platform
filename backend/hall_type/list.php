<?php

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

foreach ($_GET as $key => $value) {

    if (!in_array($key, $allowedParams, true)) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => "Unknown query parameter: {$key}."
        ]);

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

$search = null;

if (isset($_GET['search'])) {

    if (is_array($_GET['search'])) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Search must be a string.'
        ]);

        exit;
    }

    $search = trim($_GET['search']);

    if (mb_strlen($search) > 150) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Search cannot exceed 150 characters.'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Empty search treated as no filter
    |--------------------------------------------------------------------------
    */

    if ($search === '') {
        $search = null;
    }
}

/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

$status = null;

if (isset($_GET['status'])) {

    if (is_array($_GET['status'])) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Status must be a string.'
        ]);

        exit;
    }

    $status = trim($_GET['status']);

    $allowedStatuses = [
        'active',
        'inactive'
    ];

    if (!in_array($status, $allowedStatuses, true)) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Invalid status. Allowed values: active, inactive.'
        ]);

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

$page = 1;

if (isset($_GET['page'])) {

    if (
        is_array($_GET['page']) ||
        !preg_match('/^\d+$/', (string) $_GET['page'])
    ) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Page must be a positive integer.'
        ]);

        exit;
    }

    $page = (int) $_GET['page'];

    if ($page < 1) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Page must be at least 1.'
        ]);

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| LIMIT
|--------------------------------------------------------------------------
*/

$limit = 20;

if (isset($_GET['limit'])) {

    if (
        is_array($_GET['limit']) ||
        !preg_match('/^\d+$/', (string) $_GET['limit'])
    ) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Limit must be a positive integer.'
        ]);

        exit;
    }

    $limit = (int) $_GET['limit'];

    if ($limit < 1 || $limit > 100) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Limit must be between 1 and 100.'
        ]);

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| SORT BY
|--------------------------------------------------------------------------
*/

$allowedSortFields = [
    'id',
    'name',
    'slug',
    'status',
    'created_at',
    'updated_at'
];

$sortBy = 'created_at';

if (isset($_GET['sort_by'])) {

    if (is_array($_GET['sort_by'])) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Sort field must be a string.'
        ]);

        exit;
    }

    $sortBy = trim($_GET['sort_by']);

    if (!in_array($sortBy, $allowedSortFields, true)) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Invalid sort field. Allowed values: id, name, slug, status, created_at, updated_at.'
        ]);

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| SORT ORDER
|--------------------------------------------------------------------------
*/

$sortOrder = 'DESC';

if (isset($_GET['sort_order'])) {

    if (is_array($_GET['sort_order'])) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Sort order must be a string.'
        ]);

        exit;
    }

    $sortOrderInput =
        strtoupper(trim($_GET['sort_order']));

    if (!in_array(
        $sortOrderInput,
        ['ASC', 'DESC'],
        true
    )) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Invalid sort order. Allowed values: ASC, DESC.'
        ]);

        exit;
    }

    $sortOrder = $sortOrderInput;
}

/*
|--------------------------------------------------------------------------
| OFFSET
|--------------------------------------------------------------------------
*/

$offset = ($page - 1) * $limit;

/*
|--------------------------------------------------------------------------
| Build WHERE Conditions
|--------------------------------------------------------------------------
*/

$whereParts = [];
$bindTypes = '';
$bindValues = [];

/*
|--------------------------------------------------------------------------
| Search Filter
|--------------------------------------------------------------------------
|
| Searches:
| - name
| - slug
| - description
|--------------------------------------------------------------------------
*/

if ($search !== null) {

    $searchLike = '%' . $search . '%';

    $whereParts[] = "
        (
            name LIKE ?
            OR slug LIKE ?
            OR description LIKE ?
        )
    ";

    $bindTypes .= 'sss';

    $bindValues[] = $searchLike;
    $bindValues[] = $searchLike;
    $bindValues[] = $searchLike;
}

/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/

if ($status !== null) {

    $whereParts[] = 'status = ?';

    $bindTypes .= 's';

    $bindValues[] = $status;
}

/*
|--------------------------------------------------------------------------
| WHERE Clause
|--------------------------------------------------------------------------
*/

$whereSql = '';

if (count($whereParts) > 0) {

    $whereSql =
        'WHERE ' .
        implode(' AND ', $whereParts);
}

/*
|--------------------------------------------------------------------------
| Count Total Records
|--------------------------------------------------------------------------
*/

$countSql = "
    SELECT COUNT(*) AS total
    FROM hall_types
    {$whereSql}
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

if ($bindTypes !== '') {

    $countParams = [];
    $countParams[] = $bindTypes;

    foreach ($bindValues as $key => $value) {
        $countParams[] = &$bindValues[$key];
    }

    call_user_func_array(
        [$countStmt, 'bind_param'],
        $countParams
    );
}

if (!$countStmt->execute()) {

    $countStmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to count hall types.'
    ]);

    exit;
}

$countResult =
    $countStmt->get_result();

$countRow =
    $countResult->fetch_assoc();

$totalRecords =
    (int) ($countRow['total'] ?? 0);

$countStmt->close();

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
| If Requested Page Is Beyond Available Pages
|--------------------------------------------------------------------------
*/

if ($totalRecords > 0 && $page > $totalPages) {

    http_response_code(200);

    echo json_encode([
        'status' => true,
        'message' => 'Hall types fetched successfully.',
        'data' => [],
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total_records' => $totalRecords,
            'total_pages' => $totalPages,
            'has_previous_page' => $page > 1,
            'has_next_page' => false
        ],
        'filters' => [
            'search' => $search,
            'status' => $status
        ],
        'sorting' => [
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder
        ]
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Fetch Hall Types
|--------------------------------------------------------------------------
*/

$dataSql = "
    SELECT
        id,
        name,
        slug,
        description,
        status,
        created_at,
        updated_at
    FROM hall_types
    {$whereSql}
    ORDER BY {$sortBy} {$sortOrder}
    LIMIT ? OFFSET ?
";

$dataStmt = $conn->prepare($dataSql);

if (!$dataStmt) {

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare hall type list query.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Bind Search / Status + Pagination
|--------------------------------------------------------------------------
*/

$dataBindTypes =
    $bindTypes . 'ii';

$dataBindValues =
    $bindValues;

$dataBindValues[] = $limit;
$dataBindValues[] = $offset;

$dataParams = [];
$dataParams[] = $dataBindTypes;

foreach ($dataBindValues as $key => $value) {
    $dataParams[] = &$dataBindValues[$key];
}

call_user_func_array(
    [$dataStmt, 'bind_param'],
    $dataParams
);

/*
|--------------------------------------------------------------------------
| Execute
|--------------------------------------------------------------------------
*/

if (!$dataStmt->execute()) {

    $dataStmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to fetch hall types.'
    ]);

    exit;
}

$dataResult =
    $dataStmt->get_result();

$hallTypes = [];

/*
|--------------------------------------------------------------------------
| Prepare Hall Type Data
|--------------------------------------------------------------------------
*/

while ($row = $dataResult->fetch_assoc()) {

    $hallTypes[] = [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'slug' => $row['slug'],
        'description' => $row['description'],
        'status' => $row['status'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at']
    ];
}

$dataStmt->close();

/*
|--------------------------------------------------------------------------
| Success Response
|--------------------------------------------------------------------------
*/

http_response_code(200);

echo json_encode([
    'status' => true,
    'message' => 'Hall types fetched successfully.',
    'data' => $hallTypes,
    'pagination' => [
        'current_page' => $page,
        'per_page' => $limit,
        'total_records' => $totalRecords,
        'total_pages' => $totalPages,
        'has_previous_page' => $page > 1,
        'has_next_page' =>
            $page < $totalPages
    ],
    'filters' => [
        'search' => $search,
        'status' => $status
    ],
    'sorting' => [
        'sort_by' => $sortBy,
        'sort_order' => $sortOrder
    ]
]);