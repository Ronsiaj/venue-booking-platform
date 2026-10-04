<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");


// --------------------------------------------------
// OPTIONS REQUEST
// --------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}


// --------------------------------------------------
// METHOD VALIDATION
// --------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);

    echo json_encode([
        'status' => false,
        'message' => 'Only GET method is allowed.'
    ]);

    exit;
}


// --------------------------------------------------
// CONFIG
// --------------------------------------------------

require_once __DIR__ . '/../config/database.php';


// --------------------------------------------------
// ALLOWED QUERY PARAMETERS
// --------------------------------------------------

$allowedParams = [
    'search',
    'status',
    'page',
    'limit',
    'sort_by',
    'sort_order'
];


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


// --------------------------------------------------
// SEARCH VALIDATION
// --------------------------------------------------

$search = '';

if (isset($_GET['search'])) {

    if (!is_string($_GET['search'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Search must be a string.'
        ]);

        exit;
    }

    $search = trim($_GET['search']);

    if (strlen($search) > 150) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Search must not exceed 150 characters.'
        ]);

        exit;
    }
}


// --------------------------------------------------
// STATUS VALIDATION
// --------------------------------------------------

$status = '';

if (isset($_GET['status'])) {

    if (!is_string($_GET['status'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Status must be a string.'
        ]);

        exit;
    }

    $status = strtolower(
        trim($_GET['status'])
    );


    $allowedStatuses = [
        'active',
        'inactive'
    ];


    if (!in_array(
        $status,
        $allowedStatuses,
        true
    )) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Invalid status.',
            'allowed_statuses' => $allowedStatuses
        ]);

        exit;
    }
}


// --------------------------------------------------
// PAGE VALIDATION
// --------------------------------------------------

$page = 1;

if (isset($_GET['page'])) {

    if (
        filter_var(
            $_GET['page'],
            FILTER_VALIDATE_INT
        ) === false
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Page must be a valid integer.'
        ]);

        exit;
    }


    $page = (int) $_GET['page'];


    if ($page < 1) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Page must be greater than or equal to 1.'
        ]);

        exit;
    }
}


// --------------------------------------------------
// LIMIT VALIDATION
// --------------------------------------------------

$limit = 20;

if (isset($_GET['limit'])) {

    if (
        filter_var(
            $_GET['limit'],
            FILTER_VALIDATE_INT
        ) === false
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Limit must be a valid integer.'
        ]);

        exit;
    }


    $limit = (int) $_GET['limit'];


    if ($limit < 1 || $limit > 100) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Limit must be between 1 and 100.'
        ]);

        exit;
    }
}


$offset = ($page - 1) * $limit;


// --------------------------------------------------
// SORT BY VALIDATION
// --------------------------------------------------

$sortBy = 'id';

if (isset($_GET['sort_by'])) {

    if (!is_string($_GET['sort_by'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'sort_by must be a string.'
        ]);

        exit;
    }


    $sortBy = trim($_GET['sort_by']);


    $allowedSortFields = [
        'id',
        'name',
        'status',
        'created_at'
    ];


    if (!in_array(
        $sortBy,
        $allowedSortFields,
        true
    )) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Invalid sort_by field.',
            'allowed_sort_fields' =>
                $allowedSortFields
        ]);

        exit;
    }
}


// --------------------------------------------------
// SORT ORDER VALIDATION
// --------------------------------------------------

$sortOrder = 'DESC';

if (isset($_GET['sort_order'])) {

    if (!is_string($_GET['sort_order'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'sort_order must be a string.'
        ]);

        exit;
    }


    $sortOrder = strtoupper(
        trim($_GET['sort_order'])
    );


    $allowedSortOrders = [
        'ASC',
        'DESC'
    ];


    if (!in_array(
        $sortOrder,
        $allowedSortOrders,
        true
    )) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Invalid sort_order.',
            'allowed_sort_orders' =>
                $allowedSortOrders
        ]);

        exit;
    }
}


// --------------------------------------------------
// BUILD WHERE CONDITIONS
// --------------------------------------------------

$whereParts = [];
$bindTypes = '';
$bindValues = [];


if ($search !== '') {

    $whereParts[] = "
        (
            name LIKE CONCAT('%', ?, '%')
        )
    ";

    $bindTypes .= 's';
    $bindValues[] = $search;
}


if ($status !== '') {

    $whereParts[] = "
        status = ?
    ";

    $bindTypes .= 's';
    $bindValues[] = $status;
}


$whereSql = '';

if (!empty($whereParts)) {
    $whereSql = 'WHERE ' .
        implode(' AND ', $whereParts);
}


// --------------------------------------------------
// TOTAL COUNT
// --------------------------------------------------

$countSql = "
    SELECT COUNT(*) AS total
    FROM hall_facilities
    $whereSql
";


$countStmt = $conn->prepare($countSql);


if (!$countStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to prepare total count query.'
    ]);

    exit;
}


// --------------------------------------------------
// BIND COUNT PARAMETERS
// --------------------------------------------------

if ($bindTypes !== '') {

    $countBindParams = [];
    $countBindParams[] = $bindTypes;

    foreach ($bindValues as $key => $value) {
        $countBindParams[] = &$bindValues[$key];
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
        'message' =>
            'Failed to fetch total facility count.'
    ]);

    exit;
}


$countResult = $countStmt->get_result();

$countData = $countResult->fetch_assoc();

$countStmt->close();


$totalRecords = (int) (
    $countData['total'] ?? 0
);


// --------------------------------------------------
// TOTAL PAGES
// --------------------------------------------------

$totalPages = $totalRecords > 0
    ? (int) ceil($totalRecords / $limit)
    : 0;


// --------------------------------------------------
// DATA QUERY
// --------------------------------------------------
//
// sort_by and sort_order are already validated
// against whitelist, so they are safe to inject.
//

$dataSql = "
    SELECT
        id,
        name,
        status,
        created_at
    FROM hall_facilities
    $whereSql
    ORDER BY $sortBy $sortOrder
    LIMIT ? OFFSET ?
";


$dataStmt = $conn->prepare($dataSql);


if (!$dataStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to prepare facility list query.'
    ]);

    exit;
}


// --------------------------------------------------
// BIND DATA PARAMETERS
// --------------------------------------------------

$dataBindTypes = $bindTypes . 'ii';

$dataBindValues = $bindValues;

$dataBindValues[] = $limit;
$dataBindValues[] = $offset;


$dataBindParams = [];
$dataBindParams[] = $dataBindTypes;


foreach ($dataBindValues as $key => $value) {
    $dataBindParams[] = &$dataBindValues[$key];
}


call_user_func_array(
    [$dataStmt, 'bind_param'],
    $dataBindParams
);


// --------------------------------------------------
// EXECUTE DATA QUERY
// --------------------------------------------------

if (!$dataStmt->execute()) {

    $dataStmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to fetch hall facilities.'
    ]);

    exit;
}


$dataResult = $dataStmt->get_result();

$facilities = [];


// --------------------------------------------------
// FETCH DATA
// --------------------------------------------------

while ($row = $dataResult->fetch_assoc()) {

    $facilities[] = [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'status' => $row['status'],
        'created_at' => $row['created_at']
    ];
}


$dataStmt->close();


// --------------------------------------------------
// SUCCESS RESPONSE
// --------------------------------------------------

http_response_code(200);

echo json_encode([
    'status' => true,
    'message' =>
        'Hall facilities fetched successfully.',
    'data' => $facilities,
    'pagination' => [
        'current_page' => $page,
        'per_page' => $limit,
        'total_records' => $totalRecords,
        'total_pages' => $totalPages,
        'has_next_page' =>
            $page < $totalPages,
        'has_previous_page' =>
            $page > 1 && $totalPages > 0
    ],
    'filters' => [
        'search' => $search !== ''
            ? $search
            : null,
        'status' => $status !== ''
            ? $status
            : null
    ],
    'sorting' => [
        'sort_by' => $sortBy,
        'sort_order' => $sortOrder
    ]
]);

exit;