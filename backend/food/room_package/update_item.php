<?php

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (
    $_SERVER['REQUEST_METHOD'] !== 'PUT' &&
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
    http_response_code(405);

    echo json_encode([
        'status' => false,
        'message' => 'Only PUT or POST method is allowed.'
    ]);

    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/middleware.php';

$user = requireAdminOrStaff();


// --------------------------------------------------
// READ JSON BODY
// --------------------------------------------------

$rawInput = file_get_contents('php://input');

if ($rawInput === false || trim($rawInput) === '') {
    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' => 'Request body is required.'
    ]);

    exit;
}

$data = json_decode($rawInput, true);

if (
    json_last_error() !== JSON_ERROR_NONE ||
    !is_array($data)
) {
    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' => 'Invalid JSON request body.'
    ]);

    exit;
}


// --------------------------------------------------
// ALLOWED FIELDS
// --------------------------------------------------

$allowedFields = [
    'package_id',
    'food_item_id',
    'quantity'
];

$unknownFields = array_diff(
    array_keys($data),
    $allowedFields
);

if (!empty($unknownFields)) {
    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' => 'Unknown field(s) found in request.',
        'unknown_fields' => array_values($unknownFields)
    ]);

    exit;
}


// --------------------------------------------------
// REQUIRED FIELDS CHECK
// --------------------------------------------------

$requiredFields = [
    'package_id',
    'food_item_id',
    'quantity'
];

$missingFields = [];

foreach ($requiredFields as $field) {

    if (!array_key_exists($field, $data)) {
        $missingFields[] = $field;
    }
}

if (!empty($missingFields)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Required field(s) are missing.',
        'missing_fields' => $missingFields
    ]);

    exit;
}


// --------------------------------------------------
// PACKAGE ID VALIDATION
// --------------------------------------------------

$packageId = $data['package_id'];

if (
    !is_int($packageId) &&
    !(
        is_string($packageId) &&
        preg_match('/^[0-9]+$/', trim($packageId))
    )
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
// FOOD ITEM ID VALIDATION
// --------------------------------------------------

$foodItemId = $data['food_item_id'];

if (
    !is_int($foodItemId) &&
    !(
        is_string($foodItemId) &&
        preg_match('/^[0-9]+$/', trim($foodItemId))
    )
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'food_item_id must be a positive integer.'
    ]);

    exit;
}

$foodItemId = (int) $foodItemId;

if ($foodItemId <= 0) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'food_item_id must be greater than 0.'
    ]);

    exit;
}


// --------------------------------------------------
// QUANTITY VALIDATION
// --------------------------------------------------

$quantity = $data['quantity'];

if (
    !is_int($quantity) &&
    !(
        is_string($quantity) &&
        preg_match('/^[0-9]+$/', trim($quantity))
    )
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'quantity must be a positive integer.'
    ]);

    exit;
}

$quantity = (int) $quantity;

if ($quantity <= 0) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'quantity must be greater than 0.'
    ]);

    exit;
}

if ($quantity > 1000) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'quantity cannot exceed 1000.'
    ]);

    exit;
}


// --------------------------------------------------
// CHECK PACKAGE EXISTS
// --------------------------------------------------

$stmt = $conn->prepare("
    SELECT
        id,
        name,
        description,
        meal_type,
        status
    FROM room_food_packages
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare package validation query.'
    ]);

    exit;
}

$stmt->bind_param('i', $packageId);

if (!$stmt->execute()) {
    $stmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to validate room food package.'
    ]);

    exit;
}

$result = $stmt->get_result();

$package = $result->fetch_assoc();

$stmt->close();

if (!$package) {
    http_response_code(404);

    echo json_encode([
        'status' => false,
        'message' => 'Room food package not found.'
    ]);

    exit;
}


// --------------------------------------------------
// CHECK FOOD ITEM EXISTS
// --------------------------------------------------

$stmt = $conn->prepare("
    SELECT
        id,
        name,
        description,
        image_path,
        food_type,
        status,
        food_category_id
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


// --------------------------------------------------
// FOOD ITEM MUST BE ACTIVE
// --------------------------------------------------

if ($foodItem['status'] !== 'active') {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Only active food items can be added or updated in a room food package.'
    ]);

    exit;
}


// --------------------------------------------------
// CHECK PACKAGE ITEM MAPPING
// --------------------------------------------------

$stmt = $conn->prepare("
    SELECT
        package_id,
        food_item_id,
        quantity
    FROM room_food_package_items
    WHERE package_id = ?
      AND food_item_id = ?
    LIMIT 1
");

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare package item validation query.'
    ]);

    exit;
}

$stmt->bind_param(
    'ii',
    $packageId,
    $foodItemId
);

if (!$stmt->execute()) {
    $stmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to check package food item mapping.'
    ]);

    exit;
}

$result = $stmt->get_result();

$existingItem = $result->fetch_assoc();

$stmt->close();


// --------------------------------------------------
// MAPPING NOT FOUND
// --------------------------------------------------

if (!$existingItem) {
    http_response_code(404);

    echo json_encode([
        'status' => false,
        'message' => 'This food item is not part of the selected room food package.'
    ]);

    exit;
}


// --------------------------------------------------
// CHECK IF SAME QUANTITY
// --------------------------------------------------

$oldQuantity = (int) $existingItem['quantity'];

if ($oldQuantity === $quantity) {

    http_response_code(200);

    echo json_encode([
        'status' => true,
        'message' => 'No changes were made because the quantity is already the same.',

        'data' => [
            'package' => [
                'id' => (int) $package['id'],
                'name' => $package['name'],
                'meal_type' => $package['meal_type'],
                'status' => $package['status']
            ],

            'food_item' => [
                'id' => (int) $foodItem['id'],
                'name' => $foodItem['name'],
                'food_type' => $foodItem['food_type'],
                'status' => $foodItem['status']
            ],

            'old_quantity' => $oldQuantity,
            'new_quantity' => $quantity,
            'affected_rows' => 0,

            'updated_by' => [
                'user_id' => (int) $user['sub'],
                'role' => $user['role']
            ]
        ]
    ]);

    exit;
}


// --------------------------------------------------
// START TRANSACTION
// --------------------------------------------------

$conn->begin_transaction();

try {

    // --------------------------------------------------
    // UPDATE QUANTITY
    // --------------------------------------------------

    $stmt = $conn->prepare("
        UPDATE room_food_package_items
        SET quantity = ?
        WHERE package_id = ?
          AND food_item_id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception(
            'Failed to prepare package item update query.'
        );
    }

    $stmt->bind_param(
        'iii',
        $quantity,
        $packageId,
        $foodItemId
    );

    if (!$stmt->execute()) {

        $errorCode = $stmt->errno;
        $errorMessage = $stmt->error;

        $stmt->close();

        if ($errorCode === 1452) {
            throw new RuntimeException(
                'Invalid package or food item reference.'
            );
        }

        throw new RuntimeException(
            'Failed to update food item quantity. ' . $errorMessage
        );
    }

    $affectedRows = $stmt->affected_rows;

    $stmt->close();


    // --------------------------------------------------
    // VERIFY UPDATE
    // --------------------------------------------------

    $stmt = $conn->prepare("
        SELECT
            package_id,
            food_item_id,
            quantity
        FROM room_food_package_items
        WHERE package_id = ?
          AND food_item_id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception(
            'Failed to prepare update verification query.'
        );
    }

    $stmt->bind_param(
        'ii',
        $packageId,
        $foodItemId
    );

    if (!$stmt->execute()) {
        $stmt->close();

        throw new RuntimeException(
            'Failed to verify quantity update.'
        );
    }

    $result = $stmt->get_result();

    $updatedItem = $result->fetch_assoc();

    $stmt->close();


    if (!$updatedItem) {
        throw new RuntimeException(
            'Updated package food item could not be found.'
        );
    }


    if ((int) $updatedItem['quantity'] !== $quantity) {
        throw new RuntimeException(
            'Food item quantity was not updated correctly.'
        );
    }


    // --------------------------------------------------
    // COMMIT
    // --------------------------------------------------

    $conn->commit();


    // --------------------------------------------------
    // SUCCESS RESPONSE
    // --------------------------------------------------

    http_response_code(200);

    echo json_encode([
        'status' => true,
        'message' => 'Room food package item quantity updated successfully.',

        'data' => [
            'package' => [
                'id' => (int) $package['id'],
                'name' => $package['name'],
                'description' => $package['description'],
                'meal_type' => $package['meal_type'],
                'status' => $package['status']
            ],

            'food_item' => [
                'id' => (int) $foodItem['id'],
                'name' => $foodItem['name'],
                'description' => $foodItem['description'],
                'image_path' => $foodItem['image_path'],
                'food_type' => $foodItem['food_type'],
                'status' => $foodItem['status']
            ],

            'quantity' => [
                'old' => $oldQuantity,
                'new' => (int) $updatedItem['quantity']
            ],

            'affected_rows' => $affectedRows,

            'updated_by' => [
                'user_id' => (int) $user['sub'],
                'role' => $user['role']
            ]
        ]
    ]);

    exit;

} catch (RuntimeException $e) {

    $conn->rollback();

    http_response_code(409);

    echo json_encode([
        'status' => false,
        'message' => $e->getMessage()
    ]);

    exit;

} catch (Exception $e) {

    $conn->rollback();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to update room food package item.'
    ]);

    exit;
}