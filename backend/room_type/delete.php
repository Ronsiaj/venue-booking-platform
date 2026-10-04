<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/middleware.php';

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$user = requireRole(['admin', 'staff']);

/*
|--------------------------------------------------------------------------
| Request Method
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'status' => false,
        'message' => 'Only DELETE or POST method is allowed.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Read JSON Body
|--------------------------------------------------------------------------
*/

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

if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' => 'Invalid JSON request body.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Validate room_type_id
|--------------------------------------------------------------------------
*/

if (!isset($data['room_type_id'])) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'room_type_id is required.'
    ]);

    exit;
}

$roomTypeId = $data['room_type_id'];

/*
|--------------------------------------------------------------------------
| Validate ID Type
|--------------------------------------------------------------------------
*/

if (
    !is_int($roomTypeId) &&
    !ctype_digit((string) $roomTypeId)
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'room_type_id must be a valid positive integer.'
    ]);

    exit;
}

$roomTypeId = (int) $roomTypeId;

if ($roomTypeId <= 0) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'room_type_id must be greater than 0.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Check Room Type Exists
|--------------------------------------------------------------------------
*/

$checkStmt = $conn->prepare("
    SELECT
        id,
        name,
        slug,
        status
    FROM room_types
    WHERE id = ?
    LIMIT 1
");

if (!$checkStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare room type query.'
    ]);

    exit;
}

$checkStmt->bind_param('i', $roomTypeId);

if (!$checkStmt->execute()) {
    $checkStmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to check room type.'
    ]);

    exit;
}

$result = $checkStmt->get_result();

if ($result->num_rows === 0) {
    $checkStmt->close();

    http_response_code(404);

    echo json_encode([
        'status' => false,
        'message' => 'Room type not found.'
    ]);

    exit;
}

$roomType = $result->fetch_assoc();

$checkStmt->close();

/*
|--------------------------------------------------------------------------
| Check Related Rooms
|--------------------------------------------------------------------------
|
| If rooms are using this room type, deleting the room type can
| break data integrity / foreign key relationship.
|
*/

$roomCheckStmt = $conn->prepare("
    SELECT COUNT(*) AS total_rooms
    FROM rooms
    WHERE room_type_id = ?
");

if (!$roomCheckStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare room reference check.'
    ]);

    exit;
}

$roomCheckStmt->bind_param('i', $roomTypeId);

if (!$roomCheckStmt->execute()) {
    $roomCheckStmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to check room references.'
    ]);

    exit;
}

$roomResult = $roomCheckStmt->get_result();
$roomData = $roomResult->fetch_assoc();

$roomCheckStmt->close();

$totalRooms = (int) ($roomData['total_rooms'] ?? 0);

/*
|--------------------------------------------------------------------------
| Prevent Delete If Rooms Exist
|--------------------------------------------------------------------------
*/

if ($totalRooms > 0) {
    http_response_code(409);

    echo json_encode([
        'status' => false,
        'message' => 'This room type cannot be deleted because rooms are already assigned to it.',
        'data' => [
            'room_type_id' => $roomTypeId,
            'room_type_name' => $roomType['name'],
            'assigned_rooms' => $totalRooms
        ]
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Delete Room Type
|--------------------------------------------------------------------------
*/

$deleteStmt = $conn->prepare("
    DELETE FROM room_types
    WHERE id = ?
");

if (!$deleteStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare delete query.'
    ]);

    exit;
}

$deleteStmt->bind_param('i', $roomTypeId);

if (!$deleteStmt->execute()) {

    $errorCode = $deleteStmt->errno;

    $deleteStmt->close();

    /*
    |--------------------------------------------------------------------------
    | Foreign Key Constraint Handling
    |--------------------------------------------------------------------------
    */

    if ($errorCode === 1451) {
        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' => 'Room type cannot be deleted because it is referenced by other records.'
        ]);

        exit;
    }

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to delete room type.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Check Whether Delete Actually Happened
|--------------------------------------------------------------------------
*/

if ($deleteStmt->affected_rows !== 1) {
    $deleteStmt->close();

    http_response_code(404);

    echo json_encode([
        'status' => false,
        'message' => 'Room type was not deleted.'
    ]);

    exit;
}

$deleteStmt->close();

/*
|--------------------------------------------------------------------------
| Success Response
|--------------------------------------------------------------------------
*/

http_response_code(200);

echo json_encode([
    'status' => true,
    'message' => 'Room type deleted successfully.',
    'data' => [
        'room_type_id' => $roomTypeId,
        'room_type_name' => $roomType['name'],
        'deleted_by' => [
            'user_id' => (int) $user['sub'],
            'role' => $user['role']
        ]
    ]
]);

exit;