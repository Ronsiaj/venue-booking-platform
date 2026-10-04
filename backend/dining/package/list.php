<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/database.php';


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
    'meal_type',
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
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Unknown query parameter(s) provided.',
        'unknown_parameters' => array_values($unknownParams)
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

$search = null;

if (isset($_GET['search'])) {

    if (!is_string($_GET['search'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'search must be a string.'
        ]);

        exit;
    }

    $search = trim($_GET['search']);

    if (mb_strlen($search) > 150) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'search must not exceed 150 characters.'
        ]);

        exit;
    }

    if ($search === '') {
        $search = null;
    }

    if (
        $search !== null &&
        preg_match('/\s{2,}/u', $search)
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'search must not contain consecutive spaces.'
        ]);

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Meal Type Validation
|--------------------------------------------------------------------------
*/

$mealType = null;

$allowedMealTypes = [
    'lunch',
    'dinner',
    'breakfast',
    'combo',
    'other'
];

if (isset($_GET['meal_type'])) {

    if (!is_string($_GET['meal_type'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'meal_type must be a string.'
        ]);

        exit;
    }

    $mealType = trim($_GET['meal_type']);

    if (
        !in_array(
            $mealType,
            $allowedMealTypes,
            true
        )
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Invalid meal_type.',
            'allowed_values' => $allowedMealTypes
        ]);

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Status Validation
|--------------------------------------------------------------------------
*/

$status = null;

$allowedStatuses = [
    'active',
    'inactive'
];

if (isset($_GET['status'])) {

    if (!is_string($_GET['status'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'status must be a string.'
        ]);

        exit;
    }

    $status = trim($_GET['status']);

    if (
        !in_array(
            $status,
            $allowedStatuses,
            true
        )
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Invalid status.',
            'allowed_values' => $allowedStatuses
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

if (isset($_GET['page'])) {

    if (
        !is_string($_GET['page']) ||
        !preg_match('/^[0-9]+$/', $_GET['page'])
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'page must be a positive integer.'
        ]);

        exit;
    }

    $page = (int) $_GET['page'];

    if ($page < 1) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'page must be greater than or equal to 1.'
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

if (isset($_GET['limit'])) {

    if (
        !is_string($_GET['limit']) ||
        !preg_match('/^[0-9]+$/', $_GET['limit'])
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'limit must be a positive integer.'
        ]);

        exit;
    }

    $limit = (int) $_GET['limit'];

    if ($limit < 1 || $limit > 100) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'limit must be between 1 and 100.'
        ]);

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Sort By Validation
|--------------------------------------------------------------------------
*/

$allowedSortFields = [
    'id' => 'dp.id',
    'name' => 'dp.name',
    'meal_type' => 'dp.meal_type',
    'status' => 'dp.status',
    'created_at' => 'dp.created_at',
    'updated_at' => 'dp.updated_at'
];

$sortBy = 'id';

if (isset($_GET['sort_by'])) {

    if (!is_string($_GET['sort_by'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'sort_by must be a string.'
        ]);

        exit;
    }

    $sortBy = trim($_GET['sort_by']);

    if (
        !array_key_exists(
            $sortBy,
            $allowedSortFields
        )
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Invalid sort_by field.',
            'allowed_values' => array_keys(
                $allowedSortFields
            )
        ]);

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Sort Order Validation
|--------------------------------------------------------------------------
*/

$sortOrder = 'DESC';

if (isset($_GET['sort_order'])) {

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

    if (
        !in_array(
            $sortOrder,
            ['ASC', 'DESC'],
            true
        )
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'sort_order must be ASC or DESC.'
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
| Build WHERE Conditions
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];
$types = '';


/*
|--------------------------------------------------------------------------
| Search Filter
|--------------------------------------------------------------------------
|
| Search package name, description and meal type.
|
*/

if ($search !== null) {

    $where[] = "
        (
            dp.name LIKE ?
            OR dp.description LIKE ?
            OR dp.meal_type LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= 'sss';
}


/*
|--------------------------------------------------------------------------
| Meal Type Filter
|--------------------------------------------------------------------------
*/

if ($mealType !== null) {

    $where[] = "dp.meal_type = ?";

    $params[] = $mealType;
    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/

if ($status !== null) {

    $where[] = "dp.status = ?";

    $params[] = $status;
    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Default WHERE
|--------------------------------------------------------------------------
*/

$whereSql = '';

if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(
        ' AND ',
        $where
    );
}


/*
|--------------------------------------------------------------------------
| Count Total Packages
|--------------------------------------------------------------------------
*/

$countSql = "
    SELECT COUNT(*) AS total
    FROM dining_packages dp
    $whereSql
";

$stmt = $conn->prepare($countSql);

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare count query.'
    ]);

    exit;
}


if (!empty($params)) {

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


if (!$stmt->execute()) {

    $stmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to count dining packages.'
    ]);

    exit;
}


$countResult = $stmt->get_result();
$countRow = $countResult->fetch_assoc();

$stmt->close();

$totalRecords = (int) (
    $countRow['total'] ?? 0
);


/*
|--------------------------------------------------------------------------
| Total Pages
|--------------------------------------------------------------------------
*/

$totalPages = $totalRecords > 0
    ? (int) ceil($totalRecords / $limit)
    : 0;


/*
|--------------------------------------------------------------------------
| Fetch Packages
|--------------------------------------------------------------------------
*/

$dataSql = "
    SELECT
        dp.id,
        dp.name,
        dp.description,
        dp.meal_type,
        dp.image_path,
        dp.status,
        dp.created_at,
        dp.updated_at
    FROM dining_packages dp
    $whereSql
    ORDER BY {$allowedSortFields[$sortBy]} $sortOrder, dp.id DESC
    LIMIT ? OFFSET ?
";


$dataParams = $params;
$dataTypes = $types . 'ii';

$dataParams[] = $limit;
$dataParams[] = $offset;


$stmt = $conn->prepare($dataSql);

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare dining package query.'
    ]);

    exit;
}


$bindParams = [];

$bindParams[] = $dataTypes;

foreach ($dataParams as $key => $value) {
    $bindParams[] = &$dataParams[$key];
}

call_user_func_array(
    [$stmt, 'bind_param'],
    $bindParams
);


if (!$stmt->execute()) {

    $stmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to fetch dining packages.'
    ]);

    exit;
}


$result = $stmt->get_result();

$packages = [];


/*
|--------------------------------------------------------------------------
| Fetch Package Items
|--------------------------------------------------------------------------
*/

while ($row = $result->fetch_assoc()) {

    $packageId = (int) $row['id'];

    $items = [];


    $itemStmt = $conn->prepare("
        SELECT
            dpi.food_item_id,
            dpi.quantity,

            fi.name AS food_item_name,
            fi.description AS food_item_description,
            fi.image_path AS food_item_image,
            fi.food_type,
            fi.status AS food_item_status,

            fc.id AS category_id,
            fc.name AS category_name

        FROM dining_package_items dpi

        INNER JOIN food_items fi
            ON fi.id = dpi.food_item_id

        LEFT JOIN food_categories fc
            ON fc.id = fi.food_category_id

        WHERE dpi.package_id = ?

        ORDER BY fi.name ASC, dpi.food_item_id ASC
    ");


    if (!$itemStmt) {

        $stmt->close();

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' =>
                'Failed to prepare package item query.'
        ]);

        exit;
    }


    $itemStmt->bind_param(
        "i",
        $packageId
    );


    if (!$itemStmt->execute()) {

        $itemStmt->close();
        $stmt->close();

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' =>
                'Failed to fetch package food items.'
        ]);

        exit;
    }


    $itemResult = $itemStmt->get_result();


    while (
        $itemRow = $itemResult->fetch_assoc()
    ) {

        $items[] = [
            'food_item_id' => (int) (
                $itemRow['food_item_id']
            ),

            'quantity' => (int) (
                $itemRow['quantity']
            ),

            'food_item' => [
                'name' => $itemRow['food_item_name'],
                'description' =>
                    $itemRow['food_item_description'],
                'image_path' =>
                    $itemRow['food_item_image'],
                'food_type' =>
                    $itemRow['food_type'],
                'status' =>
                    $itemRow['food_item_status']
            ],

            'category' => $itemRow['category_id'] !== null
                ? [
                    'id' => (int) (
                        $itemRow['category_id']
                    ),
                    'name' =>
                        $itemRow['category_name']
                ]
                : null
        ];
    }


    $itemStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Package Response
    |--------------------------------------------------------------------------
    */

    $packages[] = [
        'id' => $packageId,

        'name' => $row['name'],

        'description' =>
            $row['description'],

        'meal_type' =>
            $row['meal_type'],

        'image_path' =>
            $row['image_path'],

        'status' =>
            $row['status'],

        'items' => $items,

        'total_items' => count($items),

        'created_at' =>
            $row['created_at'],

        'updated_at' =>
            $row['updated_at']
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
    'message' => 'Dining packages fetched successfully.',

    'data' => $packages,

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
        'search' => $search,
        'meal_type' => $mealType,
        'status' => $status
    ],

    'sorting' => [
        'sort_by' => $sortBy,
        'sort_order' => $sortOrder
    ]
]);

exit;