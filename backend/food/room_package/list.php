<?php

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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


// --------------------------------------------------
// ALLOWED QUERY PARAMETERS
// --------------------------------------------------

$allowedParams = [
    'search',
    'meal_type',
    'status',
    'food_item_id',
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
            'message' => 'search cannot exceed 150 characters.'
        ]);

        exit;
    }

    if ($search !== '') {

        if (preg_match('/\s{2,}/', $search)) {
            http_response_code(422);

            echo json_encode([
                'status' => false,
                'message' => 'search cannot contain consecutive spaces.'
            ]);

            exit;
        }
    }
}


// --------------------------------------------------
// MEAL TYPE VALIDATION
// --------------------------------------------------

$allowedMealTypes = [
    'breakfast',
    'lunch',
    'dinner',
    'combo',
    'other'
];

$mealType = null;

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

    if (!in_array($mealType, $allowedMealTypes, true)) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Invalid meal_type.',
            'allowed_values' => $allowedMealTypes
        ]);

        exit;
    }
}


// --------------------------------------------------
// STATUS VALIDATION
// --------------------------------------------------

$allowedStatuses = [
    'active',
    'inactive'
];

$status = null;

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

    if (!in_array($status, $allowedStatuses, true)) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Invalid status.',
            'allowed_values' => $allowedStatuses
        ]);

        exit;
    }
}


// --------------------------------------------------
// FOOD ITEM ID VALIDATION
// --------------------------------------------------

$foodItemId = null;

if (isset($_GET['food_item_id'])) {

    $value = $_GET['food_item_id'];

    if (
        !is_string($value) ||
        !preg_match('/^[0-9]+$/', $value)
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'food_item_id must be a positive integer.'
        ]);

        exit;
    }

    $foodItemId = (int) $value;

    if ($foodItemId <= 0) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'food_item_id must be greater than 0.'
        ]);

        exit;
    }


    // --------------------------------------------------
    // CHECK FOOD ITEM EXISTS
    // --------------------------------------------------

    $stmt = $conn->prepare("
        SELECT id
        FROM food_items
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Failed to prepare food item validation query.'
        ]);

        exit;
    }

    $stmt->bind_param('i', $foodItemId);

    if (!$stmt->execute()) {
        $stmt->close();

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Failed to validate food item.'
        ]);

        exit;
    }

    $result = $stmt->get_result();

    $foodItem = $result->fetch_assoc();

    $stmt->close();

    if (!$foodItem) {
        http_response_code(404);

        echo json_encode([
            'status' => false,
            'message' => 'Food item not found.'
        ]);

        exit;
    }
}


// --------------------------------------------------
// PAGE VALIDATION
// --------------------------------------------------

$page = 1;

if (isset($_GET['page'])) {

    $value = $_GET['page'];

    if (
        !is_string($value) ||
        !preg_match('/^[0-9]+$/', $value)
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'page must be a positive integer.'
        ]);

        exit;
    }

    $page = (int) $value;

    if ($page <= 0) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'page must be greater than 0.'
        ]);

        exit;
    }
}


// --------------------------------------------------
// LIMIT VALIDATION
// --------------------------------------------------

$limit = 20;

if (isset($_GET['limit'])) {

    $value = $_GET['limit'];

    if (
        !is_string($value) ||
        !preg_match('/^[0-9]+$/', $value)
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'limit must be a positive integer.'
        ]);

        exit;
    }

    $limit = (int) $value;

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


// --------------------------------------------------
// OFFSET
// --------------------------------------------------

$offset = ($page - 1) * $limit;


// --------------------------------------------------
// SORT BY VALIDATION
// --------------------------------------------------

$allowedSortFields = [
    'id',
    'name',
    'meal_type',
    'status',
    'created_at',
    'updated_at'
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

    if (!in_array($sortBy, $allowedSortFields, true)) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Invalid sort_by.',
            'allowed_values' => $allowedSortFields
        ]);

        exit;
    }
}


// --------------------------------------------------
// SORT ORDER VALIDATION
// --------------------------------------------------

$sortOrder = 'ASC';

if (isset($_GET['sort_order'])) {

    if (!is_string($_GET['sort_order'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'sort_order must be a string.'
        ]);

        exit;
    }

    $sortOrder = strtoupper(trim($_GET['sort_order']));

    if (!in_array($sortOrder, ['ASC', 'DESC'], true)) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'sort_order must be ASC or DESC.'
        ]);

        exit;
    }
}


// --------------------------------------------------
// BUILD WHERE CONDITION
// --------------------------------------------------

$where = [];
$params = [];
$types = '';


// Only active packages by default
if ($status === null) {
    $where[] = "rfp.status = 'active'";
} else {
    $where[] = "rfp.status = ?";
    $params[] = $status;
    $types .= 's';
}


// Search
if ($search !== null && $search !== '') {

    $searchValue = '%' . $search . '%';

    $where[] = "
        (
            rfp.name LIKE ?
            OR rfp.description LIKE ?
        )
    ";

    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= 'ss';
}


// Meal type
if ($mealType !== null) {

    $where[] = "rfp.meal_type = ?";

    $params[] = $mealType;
    $types .= 's';
}


// Food item filter
if ($foodItemId !== null) {

    $where[] = "
        EXISTS (
            SELECT 1
            FROM room_food_package_items filter_items
            WHERE filter_items.package_id = rfp.id
            AND filter_items.food_item_id = ?
        )
    ";

    $params[] = $foodItemId;
    $types .= 'i';
}


$whereSql = '';

if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}


// --------------------------------------------------
// COUNT TOTAL PACKAGES
// --------------------------------------------------

$countSql = "
    SELECT COUNT(*) AS total
    FROM room_food_packages rfp
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

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

if (!$stmt->execute()) {
    $stmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to count room food packages.'
    ]);

    exit;
}

$result = $stmt->get_result();

$countData = $result->fetch_assoc();

$stmt->close();

$total = (int) ($countData['total'] ?? 0);


// --------------------------------------------------
// FETCH PACKAGES
// --------------------------------------------------

$sql = "
    SELECT
        rfp.id,
        rfp.name,
        rfp.description,
        rfp.meal_type,
        rfp.status,
        rfp.created_at,
        rfp.updated_at,

        (
            SELECT COUNT(*)
            FROM room_food_package_items rfp_count
            WHERE rfp_count.package_id = rfp.id
        ) AS total_items

    FROM room_food_packages rfp

    $whereSql

    ORDER BY rfp.$sortBy $sortOrder

    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare package list query.'
    ]);

    exit;
}


// Add pagination parameters
$listTypes = $types . 'ii';
$listParams = $params;
$listParams[] = $limit;
$listParams[] = $offset;

$stmt->bind_param(
    $listTypes,
    ...$listParams
);

if (!$stmt->execute()) {
    $stmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to fetch room food packages.'
    ]);

    exit;
}

$result = $stmt->get_result();

$packages = [];

while ($row = $result->fetch_assoc()) {

    $packageId = (int) $row['id'];

    $packages[$packageId] = [
        'id' => $packageId,
        'name' => $row['name'],
        'description' => $row['description'],
        'meal_type' => $row['meal_type'],
        'status' => $row['status'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
        'total_items' => (int) $row['total_items'],
        'items' => []
    ];
}

$stmt->close();


// --------------------------------------------------
// FETCH PACKAGE ITEMS
// --------------------------------------------------

if (!empty($packages)) {

    $packageIds = array_keys($packages);

    $placeholders = implode(
        ',',
        array_fill(0, count($packageIds), '?')
    );

    $itemTypes = str_repeat('i', count($packageIds));

    $itemSql = "
        SELECT
            rfpfi.package_id,
            rfpfi.food_item_id,
            rfpfi.quantity,

            fi.name AS food_item_name,
            fi.description AS food_item_description,
            fi.image_path AS food_item_image,
            fi.food_type,
            fi.status AS food_item_status,

            fc.id AS category_id,
            fc.name AS category_name

        FROM room_food_package_items rfpfi

        INNER JOIN food_items fi
            ON fi.id = rfpfi.food_item_id

        LEFT JOIN food_categories fc
            ON fc.id = fi.food_category_id

        WHERE rfpfi.package_id IN ($placeholders)

        ORDER BY
            rfpfi.package_id ASC,
            fi.name ASC
    ";

    $stmt = $conn->prepare($itemSql);

    if (!$stmt) {
        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Failed to prepare package items query.'
        ]);

        exit;
    }

    $stmt->bind_param(
        $itemTypes,
        ...$packageIds
    );

    if (!$stmt->execute()) {
        $stmt->close();

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Failed to fetch package items.'
        ]);

        exit;
    }

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $packageId = (int) $row['package_id'];

        if (!isset($packages[$packageId])) {
            continue;
        }

        $packages[$packageId]['items'][] = [
            'food_item_id' => (int) $row['food_item_id'],
            'food_item_name' => $row['food_item_name'],
            'description' => $row['food_item_description'],
            'image_path' => $row['food_item_image'],
            'food_type' => $row['food_type'],
            'quantity' => (int) $row['quantity'],
            'status' => $row['food_item_status'],

            'category' => $row['category_id'] !== null
                ? [
                    'id' => (int) $row['category_id'],
                    'name' => $row['category_name']
                ]
                : null
        ];
    }

    $stmt->close();
}


// --------------------------------------------------
// REINDEX PACKAGES
// --------------------------------------------------

$packages = array_values($packages);


// --------------------------------------------------
// PAGINATION CALCULATION
// --------------------------------------------------

$totalPages = $total > 0
    ? (int) ceil($total / $limit)
    : 0;


// --------------------------------------------------
// RESPONSE
// --------------------------------------------------

http_response_code(200);

echo json_encode([
    'status' => true,
    'message' => 'Room food packages fetched successfully.',

    'data' => $packages,

    'pagination' => [
        'current_page' => $page,
        'per_page' => $limit,
        'total_records' => $total,
        'total_pages' => $totalPages,

        'has_previous_page' => $page > 1,

        'has_next_page' =>
            $totalPages > 0 &&
            $page < $totalPages
    ],

    'filters' => [
        'search' => $search,
        'meal_type' => $mealType,
        'status' => $status ?? 'active',
        'food_item_id' => $foodItemId
    ],

    'sorting' => [
        'sort_by' => $sortBy,
        'sort_order' => $sortOrder
    ]
]);

exit;