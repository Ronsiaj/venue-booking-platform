<?php

header("Content-Type: application/json; charset=utf-8");
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

require_once __DIR__ . '/../../config/database.php';


/*
|--------------------------------------------------------------------------
| Helper Function
|--------------------------------------------------------------------------
*/

function sendResponse($statusCode, $message, $data = null)
{
    http_response_code($statusCode);

    $response = [
        'status' => $statusCode >= 200 && $statusCode < 300,
        'message' => $message
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode(
        $response,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Get Query Parameters
|--------------------------------------------------------------------------
*/

$page = $_GET['page'] ?? '1';
$limit = $_GET['limit'] ?? '20';

$search = $_GET['search'] ?? '';

$foodCategoryId = $_GET['food_category_id'] ?? '';

$foodType = $_GET['food_type'] ?? '';

$status = $_GET['status'] ?? '';

$sortBy = $_GET['sort_by'] ?? 'id';

$sortOrder = $_GET['sort_order'] ?? 'DESC';


/*
|--------------------------------------------------------------------------
| Page Validation
|--------------------------------------------------------------------------
*/

if (
    !is_string($page) ||
    !preg_match('/^\d+$/', $page)
) {
    sendResponse(
        422,
        'Page must be a valid positive integer.'
    );
}

$page = (int) $page;

if ($page < 1) {
    sendResponse(
        422,
        'Page must be greater than or equal to 1.'
    );
}


/*
|--------------------------------------------------------------------------
| Limit Validation
|--------------------------------------------------------------------------
*/

if (
    !is_string($limit) ||
    !preg_match('/^\d+$/', $limit)
) {
    sendResponse(
        422,
        'Limit must be a valid positive integer.'
    );
}

$limit = (int) $limit;

if ($limit < 1) {
    sendResponse(
        422,
        'Limit must be greater than or equal to 1.'
    );
}

if ($limit > 100) {
    sendResponse(
        422,
        'Limit cannot be greater than 100.'
    );
}


/*
|--------------------------------------------------------------------------
| Search Validation
|--------------------------------------------------------------------------
*/

if (!is_string($search)) {
    sendResponse(
        422,
        'Search must be a valid string.'
    );
}

$search = trim($search);

if (strlen($search) > 100) {
    sendResponse(
        422,
        'Search cannot exceed 100 characters.'
    );
}


/*
|--------------------------------------------------------------------------
| Food Category ID Validation
|--------------------------------------------------------------------------
*/

if (!is_string($foodCategoryId)) {

    sendResponse(
        422,
        'Food category ID must be a valid integer.'
    );
}

$foodCategoryId = trim($foodCategoryId);

if ($foodCategoryId !== '') {

    if (!preg_match('/^\d+$/', $foodCategoryId)) {
        sendResponse(
            422,
            'Food category ID must be a valid positive integer.'
        );
    }

    $foodCategoryId = (int) $foodCategoryId;

    if ($foodCategoryId <= 0) {
        sendResponse(
            422,
            'Food category ID must be greater than 0.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Verify Category Exists
    |--------------------------------------------------------------------------
    */

    $categoryCheckSQL = "
        SELECT
            id,
            name,
            status
        FROM food_categories
        WHERE id = ?
        LIMIT 1
    ";

    $categoryCheckStmt = $conn->prepare(
        $categoryCheckSQL
    );

    if (!$categoryCheckStmt) {
        sendResponse(
            500,
            'Failed to prepare food category validation query.'
        );
    }

    $categoryCheckStmt->bind_param(
        'i',
        $foodCategoryId
    );

    if (!$categoryCheckStmt->execute()) {

        $categoryCheckStmt->close();

        sendResponse(
            500,
            'Failed to validate food category.'
        );
    }

    $categoryCheckResult =
        $categoryCheckStmt->get_result();

    if (!$categoryCheckResult) {

        $categoryCheckStmt->close();

        sendResponse(
            500,
            'Failed to process food category validation.'
        );
    }

    $categoryCheck =
        $categoryCheckResult->fetch_assoc();

    $categoryCheckStmt->close();

    if (!$categoryCheck) {
        sendResponse(
            422,
            'Food category does not exist.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Food Type Validation
|--------------------------------------------------------------------------
*/

if (!is_string($foodType)) {
    sendResponse(
        422,
        'Food type must be a valid string.'
    );
}

$foodType = strtolower(
    trim($foodType)
);

if ($foodType !== '') {

    $allowedFoodTypes = [
        'veg',
        'non_veg',
        'egg',
        'vegan',
        'other'
    ];

    if (
        !in_array(
            $foodType,
            $allowedFoodTypes,
            true
        )
    ) {
        sendResponse(
            422,
            'Invalid food type. Allowed values are veg, non_veg, egg, vegan, other.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Status Validation
|--------------------------------------------------------------------------
*/

if (!is_string($status)) {
    sendResponse(
        422,
        'Status must be a valid string.'
    );
}

$status = strtolower(
    trim($status)
);

if ($status !== '') {

    $allowedStatuses = [
        'active',
        'inactive'
    ];

    if (
        !in_array(
            $status,
            $allowedStatuses,
            true
        )
    ) {
        sendResponse(
            422,
            'Invalid status. Allowed values are active or inactive.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Sort By Validation
|--------------------------------------------------------------------------
*/

if (!is_string($sortBy)) {
    sendResponse(
        422,
        'sort_by must be a valid string.'
    );
}

$sortBy = strtolower(
    trim($sortBy)
);


/*
|--------------------------------------------------------------------------
| Allowed Sort Fields
|--------------------------------------------------------------------------
*/

$allowedSortFields = [

    'id' => 'fi.id',

    'name' => 'fi.name',

    'food_category_id' =>
        'fi.food_category_id',

    'category_name' =>
        'fc.name',

    'food_type' =>
        'fi.food_type',

    'status' =>
        'fi.status',

    'created_at' =>
        'fi.created_at',

    'updated_at' =>
        'fi.updated_at'
];


if (
    !array_key_exists(
        $sortBy,
        $allowedSortFields
    )
) {
    sendResponse(
        422,
        'Invalid sort_by. Allowed values are id, name, food_category_id, category_name, food_type, status, created_at, updated_at.'
    );
}

$orderBy =
    $allowedSortFields[$sortBy];


/*
|--------------------------------------------------------------------------
| Sort Order Validation
|--------------------------------------------------------------------------
*/

if (!is_string($sortOrder)) {
    sendResponse(
        422,
        'sort_order must be a valid string.'
    );
}

$sortOrder = strtoupper(
    trim($sortOrder)
);

if (
    !in_array(
        $sortOrder,
        ['ASC', 'DESC'],
        true
    )
) {
    sendResponse(
        422,
        'Invalid sort_order. Allowed values are ASC or DESC.'
    );
}


/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

$offset =
    ($page - 1) * $limit;


/*
|--------------------------------------------------------------------------
| WHERE Conditions
|--------------------------------------------------------------------------
*/

$whereConditions = [];

$params = [];

$types = '';


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $whereConditions[] = "
        (
            fi.name LIKE ?
            OR fi.description LIKE ?
            OR fc.name LIKE ?
        )
    ";

    $searchValue =
        '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= 'sss';
}


/*
|--------------------------------------------------------------------------
| Category Filter
|--------------------------------------------------------------------------
*/

if ($foodCategoryId !== '') {

    $whereConditions[] =
        'fi.food_category_id = ?';

    $params[] =
        $foodCategoryId;

    $types .= 'i';
}


/*
|--------------------------------------------------------------------------
| Food Type Filter
|--------------------------------------------------------------------------
*/

if ($foodType !== '') {

    $whereConditions[] =
        'fi.food_type = ?';

    $params[] =
        $foodType;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/

if ($status !== '') {

    $whereConditions[] =
        'fi.status = ?';

    $params[] =
        $status;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| WHERE SQL
|--------------------------------------------------------------------------
*/

$whereSQL = '';

if (!empty($whereConditions)) {

    $whereSQL =
        'WHERE ' .
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

$countSQL = "
    SELECT
        COUNT(*) AS total
    FROM food_items fi
    LEFT JOIN food_categories fc
        ON fc.id = fi.food_category_id
    $whereSQL
";

$countStmt =
    $conn->prepare($countSQL);

if (!$countStmt) {
    sendResponse(
        500,
        'Failed to prepare food item count query.'
    );
}


/*
|--------------------------------------------------------------------------
| Bind Count Parameters
|--------------------------------------------------------------------------
*/

if (!empty($params)) {

    $countStmt->bind_param(
        $types,
        ...$params
    );
}


/*
|--------------------------------------------------------------------------
| Execute Count
|--------------------------------------------------------------------------
*/

if (!$countStmt->execute()) {

    $countStmt->close();

    sendResponse(
        500,
        'Failed to fetch food item count.'
    );
}

$countResult =
    $countStmt->get_result();

if (!$countResult) {

    $countStmt->close();

    sendResponse(
        500,
        'Failed to process food item count.'
    );
}

$countRow =
    $countResult->fetch_assoc();

$countStmt->close();

$totalRecords =
    (int) (
        $countRow['total'] ?? 0
    );


/*
|--------------------------------------------------------------------------
| Total Pages
|--------------------------------------------------------------------------
*/

$totalPages =
    $totalRecords > 0
        ? (int) ceil(
            $totalRecords / $limit
        )
        : 0;


/*
|--------------------------------------------------------------------------
| Requested Page Beyond Available Data
|--------------------------------------------------------------------------
*/

if (
    $totalPages > 0 &&
    $page > $totalPages
) {

    sendResponse(
        200,
        'No food items found for the requested page.',
        [
            'food_items' => [],

            'pagination' => [
                'current_page' =>
                    $page,

                'per_page' =>
                    $limit,

                'total_records' =>
                    $totalRecords,

                'total_pages' =>
                    $totalPages,

                'has_previous_page' =>
                    $page > 1,

                'has_next_page' =>
                    false
            ]
        ]
    );
}


/*
|--------------------------------------------------------------------------
| Fetch Food Items
|--------------------------------------------------------------------------
*/

$listSQL = "
    SELECT
        fi.id,
        fi.food_category_id,
        fc.name AS category_name,
        fi.name,
        fi.description,
        fi.image_path,
        fi.food_type,
        fi.status,
        fi.created_at,
        fi.updated_at

    FROM food_items fi

    LEFT JOIN food_categories fc
        ON fc.id = fi.food_category_id

    $whereSQL

    ORDER BY
        $orderBy $sortOrder

    LIMIT ?
    OFFSET ?
";


$listStmt =
    $conn->prepare($listSQL);

if (!$listStmt) {
    sendResponse(
        500,
        'Failed to prepare food item list query.'
    );
}


/*
|--------------------------------------------------------------------------
| Bind List Parameters
|--------------------------------------------------------------------------
*/

if (!empty($params)) {

    $listTypes =
        $types . 'ii';

    $listParams =
        $params;

    $listParams[] =
        $limit;

    $listParams[] =
        $offset;

    $listStmt->bind_param(
        $listTypes,
        ...$listParams
    );

} else {

    $listStmt->bind_param(
        'ii',
        $limit,
        $offset
    );
}


/*
|--------------------------------------------------------------------------
| Execute List Query
|--------------------------------------------------------------------------
*/

if (!$listStmt->execute()) {

    $listStmt->close();

    sendResponse(
        500,
        'Failed to fetch food items.'
    );
}


/*
|--------------------------------------------------------------------------
| Get Result
|--------------------------------------------------------------------------
*/

$result =
    $listStmt->get_result();

if (!$result) {

    $listStmt->close();

    sendResponse(
        500,
        'Failed to process food item list.'
    );
}


/*
|--------------------------------------------------------------------------
| Build Food Item List
|--------------------------------------------------------------------------
*/

$foodItems = [];

while (
    $row = $result->fetch_assoc()
) {

    $foodItems[] = [

        'id' =>
            (int) $row['id'],

        'food_category_id' =>
            $row['food_category_id'] !== null
                ? (int) $row['food_category_id']
                : null,

        'category_name' =>
            $row['category_name'],

        'name' =>
            $row['name'],

        'description' =>
            $row['description'],

        'image_path' =>
            $row['image_path'],

        'food_type' =>
            $row['food_type'],

        'status' =>
            $row['status'],

        'created_at' =>
            $row['created_at'],

        'updated_at' =>
            $row['updated_at']
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
    $totalPages > 0
        ? $page < $totalPages
        : false;


/*
|--------------------------------------------------------------------------
| Final Response
|--------------------------------------------------------------------------
*/

sendResponse(
    200,
    'Food items fetched successfully.',
    [

        'food_items' =>
            $foodItems,

        'pagination' => [

            'current_page' =>
                $page,

            'per_page' =>
                $limit,

            'total_records' =>
                $totalRecords,

            'total_pages' =>
                $totalPages,

            'has_previous_page' =>
                $hasPreviousPage,

            'has_next_page' =>
                $hasNextPage
        ],

        'filters' => [

            'search' =>
                $search !== ''
                    ? $search
                    : null,

            'food_category_id' =>
                $foodCategoryId !== ''
                    ? (int) $foodCategoryId
                    : null,

            'food_type' =>
                $foodType !== ''
                    ? $foodType
                    : null,

            'status' =>
                $status !== ''
                    ? $status
                    : null,

            'sort_by' =>
                $sortBy,

            'sort_order' =>
                $sortOrder
        ]
    ]
);