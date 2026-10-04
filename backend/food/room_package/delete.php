<?php

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (
    $_SERVER['REQUEST_METHOD'] !== 'DELETE' &&
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
    http_response_code(405);

    echo json_encode([
        'status' => false,
        'message' => 'Only DELETE or POST method is allowed.'
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
// ALLOWED FIELDS VALIDATION
// --------------------------------------------------

$allowedFields = [
    'package_id'
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
// PACKAGE ID VALIDATION
// --------------------------------------------------

if (!array_key_exists('package_id', $data)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'package_id is required.'
    ]);

    exit;
}

$packageId = $data['package_id'];


// Must be integer / numeric integer
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
// CHECK PACKAGE ITEMS
// --------------------------------------------------

$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_items
    FROM room_food_package_items
    WHERE package_id = ?
");

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare package item query.'
    ]);

    exit;
}

$stmt->bind_param('i', $packageId);

if (!$stmt->execute()) {
    $stmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to check package items.'
    ]);

    exit;
}

$result = $stmt->get_result();

$itemData = $result->fetch_assoc();

$stmt->close();

$totalItems = (int) ($itemData['total_items'] ?? 0);


// --------------------------------------------------
// GET PACKAGE ITEM DETAILS BEFORE DELETE
// --------------------------------------------------

$packageItems = [];

if ($totalItems > 0) {

    $stmt = $conn->prepare("
        SELECT
            rfpfi.food_item_id,
            rfpfi.quantity,
            fi.name AS food_item_name,
            fi.food_type,
            fi.status AS food_item_status
        FROM room_food_package_items rfpfi
        INNER JOIN food_items fi
            ON fi.id = rfpfi.food_item_id
        WHERE rfpfi.package_id = ?
        ORDER BY fi.name ASC
    ");

    if (!$stmt) {
        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Failed to prepare package item details query.'
        ]);

        exit;
    }

    $stmt->bind_param('i', $packageId);

    if (!$stmt->execute()) {
        $stmt->close();

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Failed to fetch package item details.'
        ]);

        exit;
    }

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $packageItems[] = [
            'food_item_id' => (int) $row['food_item_id'],
            'food_item_name' => $row['food_item_name'],
            'food_type' => $row['food_type'],
            'quantity' => (int) $row['quantity'],
            'food_item_status' => $row['food_item_status']
        ];
    }

    $stmt->close();
}


// --------------------------------------------------
// TRANSACTION START
// --------------------------------------------------

$conn->begin_transaction();

try {

    // --------------------------------------------------
    // DELETE PACKAGE
    // --------------------------------------------------

    $stmt = $conn->prepare("
        DELETE FROM room_food_packages
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception(
            'Failed to prepare package delete query.'
        );
    }

    $stmt->bind_param('i', $packageId);

    if (!$stmt->execute()) {

        $errorCode = $stmt->errno;
        $errorMessage = $stmt->error;

        $stmt->close();

        // Foreign key constraint
        if ($errorCode === 1451) {
            throw new RuntimeException(
                'This room food package cannot be deleted because it is referenced by another record.'
            );
        }

        throw new RuntimeException(
            'Failed to delete room food package. ' . $errorMessage
        );
    }

    $affectedRows = $stmt->affected_rows;

    $stmt->close();


    // --------------------------------------------------
    // VERIFY DELETE
    // --------------------------------------------------

    $stmt = $conn->prepare("
        SELECT id
        FROM room_food_packages
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception(
            'Failed to prepare delete verification query.'
        );
    }

    $stmt->bind_param('i', $packageId);

    if (!$stmt->execute()) {
        $stmt->close();

        throw new RuntimeException(
            'Failed to verify package deletion.'
        );
    }

    $result = $stmt->get_result();

    $deletedPackageCheck = $result->fetch_assoc();

    $stmt->close();


    if ($deletedPackageCheck) {
        throw new RuntimeException(
            'Room food package could not be deleted.'
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
        'message' => 'Room food package deleted successfully.',

        'data' => [
            'deleted_package' => [
                'id' => (int) $package['id'],
                'name' => $package['name'],
                'description' => $package['description'],
                'meal_type' => $package['meal_type'],
                'status' => $package['status'],
                'created_at' => $package['created_at'],
                'updated_at' => $package['updated_at']
            ],

            'package_items_deleted' => $totalItems,

            'deleted_package_items' => $packageItems,

            'affected_rows' => $affectedRows,

            'deleted_by' => [
                'user_id' => (int) $user['sub'],
                'role' => $user['role']
            ]
        ]
    ]);

    exit;

} catch (RuntimeException $e) {

    // --------------------------------------------------
    // ROLLBACK
    // --------------------------------------------------

    $conn->rollback();

    http_response_code(409);

    echo json_encode([
        'status' => false,
        'message' => $e->getMessage()
    ]);

    exit;

} catch (Exception $e) {

    // --------------------------------------------------
    // ROLLBACK
    // --------------------------------------------------

    $conn->rollback();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to delete room food package.'
    ]);

    exit;
}