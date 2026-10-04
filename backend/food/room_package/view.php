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
    'package_id'
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
// PACKAGE ID REQUIRED
// --------------------------------------------------

if (!array_key_exists('package_id', $_GET)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'package_id is required.'
    ]);

    exit;
}

$packageId = $_GET['package_id'];


// --------------------------------------------------
// PACKAGE ID VALIDATION
// --------------------------------------------------

if (
    !is_string($packageId) ||
    !preg_match('/^[0-9]+$/', $packageId)
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'package_id must be a positive integer.'
    ]);

    exit;
}

$packageId = (int) $packageId;

if ($packageId <= 0) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'package_id must be greater than 0.'
    ]);

    exit;
}


// --------------------------------------------------
// FETCH PACKAGE
// --------------------------------------------------

$stmt = $conn->prepare("
    SELECT
        id,
        name,
        description,
        meal_type,
        status,
        created_at,
        updated_at
    FROM room_food_packages
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare package query.'
    ]);

    exit;
}

$stmt->bind_param('i', $packageId);

if (!$stmt->execute()) {
    $stmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to fetch room food package.'
    ]);

    exit;
}

$result = $stmt->get_result();

$package = $result->fetch_assoc();

$stmt->close();


// --------------------------------------------------
// PACKAGE NOT FOUND
// --------------------------------------------------

if (!$package) {
    http_response_code(404);

    echo json_encode([
        'status' => false,
        'message' => 'Room food package not found.'
    ]);

    exit;
}


// --------------------------------------------------
// FETCH PACKAGE ITEMS
// --------------------------------------------------

$stmt = $conn->prepare("
    SELECT
        rfpfi.package_id,
        rfpfi.food_item_id,
        rfpfi.quantity,

        fi.name AS food_item_name,
        fi.description AS food_item_description,
        fi.image_path AS food_item_image,
        fi.food_type,
        fi.status AS food_item_status,
        fi.created_at AS food_item_created_at,
        fi.updated_at AS food_item_updated_at,

        fc.id AS category_id,
        fc.name AS category_name,
        fc.description AS category_description,
        fc.status AS category_status

    FROM room_food_package_items rfpfi

    INNER JOIN food_items fi
        ON fi.id = rfpfi.food_item_id

    LEFT JOIN food_categories fc
        ON fc.id = fi.food_category_id

    WHERE rfpfi.package_id = ?

    ORDER BY fi.name ASC
");

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare package items query.'
    ]);

    exit;
}

$stmt->bind_param('i', $packageId);

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

$items = [];

while ($row = $result->fetch_assoc()) {

    $category = null;

    if ($row['category_id'] !== null) {

        $category = [
            'id' => (int) $row['category_id'],
            'name' => $row['category_name'],
            'description' => $row['category_description'],
            'status' => $row['category_status']
        ];
    }

    $items[] = [
        'food_item_id' => (int) $row['food_item_id'],
        'name' => $row['food_item_name'],
        'description' => $row['food_item_description'],
        'image_path' => $row['food_item_image'],
        'food_type' => $row['food_type'],
        'quantity' => (int) $row['quantity'],
        'status' => $row['food_item_status'],
        'created_at' => $row['food_item_created_at'],
        'updated_at' => $row['food_item_updated_at'],

        'category' => $category
    ];
}

$stmt->close();


// --------------------------------------------------
// RESPONSE
// --------------------------------------------------

http_response_code(200);

echo json_encode([
    'status' => true,
    'message' => 'Room food package fetched successfully.',

    'data' => [
        'id' => (int) $package['id'],
        'name' => $package['name'],
        'description' => $package['description'],
        'meal_type' => $package['meal_type'],
        'status' => $package['status'],
        'created_at' => $package['created_at'],
        'updated_at' => $package['updated_at'],

        'total_items' => count($items),

        'items' => $items
    ]
]);

exit;