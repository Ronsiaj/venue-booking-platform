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
    'package_id'
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
| Required package_id
|--------------------------------------------------------------------------
*/

if (!array_key_exists('package_id', $_GET)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'package_id is required.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Validate package_id
|--------------------------------------------------------------------------
*/

if (
    !is_string($_GET['package_id']) ||
    !preg_match('/^[0-9]+$/', $_GET['package_id'])
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'package_id must be a positive integer.'
    ]);

    exit;
}

$packageId = (int) $_GET['package_id'];

if ($packageId <= 0) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'package_id must be greater than 0.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Fetch Dining Package
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        name,
        description,
        meal_type,
        image_path,
        status,
        created_at,
        updated_at
    FROM dining_packages
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare dining package query.'
    ]);

    exit;
}

$stmt->bind_param(
    "i",
    $packageId
);

if (!$stmt->execute()) {
    $stmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to fetch dining package.'
    ]);

    exit;
}

$result = $stmt->get_result();

$package = $result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| Package Not Found
|--------------------------------------------------------------------------
*/

if (!$package) {
    http_response_code(404);

    echo json_encode([
        'status' => false,
        'message' => 'Dining package not found.',
        'package_id' => $packageId
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Fetch Package Food Items
|--------------------------------------------------------------------------
*/

$itemStmt = $conn->prepare("
    SELECT
        dpi.food_item_id,
        dpi.quantity,

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

    FROM dining_package_items dpi

    INNER JOIN food_items fi
        ON fi.id = dpi.food_item_id

    LEFT JOIN food_categories fc
        ON fc.id = fi.food_category_id

    WHERE dpi.package_id = ?

    ORDER BY
        fi.name ASC,
        dpi.food_item_id ASC
");

if (!$itemStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare package food item query.'
    ]);

    exit;
}

$itemStmt->bind_param(
    "i",
    $packageId
);

if (!$itemStmt->execute()) {
    $itemStmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to fetch package food items.'
    ]);

    exit;
}

$itemResult = $itemStmt->get_result();

$items = [];

while ($item = $itemResult->fetch_assoc()) {

    $items[] = [
        'food_item_id' => (int) $item['food_item_id'],

        'quantity' => (int) $item['quantity'],

        'food_item' => [
            'name' => $item['food_item_name'],

            'description' =>
                $item['food_item_description'],

            'image_path' =>
                $item['food_item_image'],

            'food_type' =>
                $item['food_type'],

            'status' =>
                $item['food_item_status'],

            'created_at' =>
                $item['food_item_created_at'],

            'updated_at' =>
                $item['food_item_updated_at']
        ],

        'category' => (
            $item['category_id'] !== null
        )
            ? [
                'id' => (int) $item['category_id'],

                'name' =>
                    $item['category_name'],

                'description' =>
                    $item['category_description'],

                'status' =>
                    $item['category_status']
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

$responseData = [
    'id' => (int) $package['id'],

    'name' =>
        $package['name'],

    'description' =>
        $package['description'],

    'meal_type' =>
        $package['meal_type'],

    'image_path' =>
        $package['image_path'],

    'status' =>
        $package['status'],

    'items' =>
        $items,

    'total_items' =>
        count($items),

    'created_at' =>
        $package['created_at'],

    'updated_at' =>
        $package['updated_at']
];


/*
|--------------------------------------------------------------------------
| Success Response
|--------------------------------------------------------------------------
*/

http_response_code(200);

echo json_encode([
    'status' => true,
    'message' => 'Dining package fetched successfully.',
    'data' => $responseData
]);

exit;