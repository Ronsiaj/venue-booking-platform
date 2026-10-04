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

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
|
| Admin and Staff can update room types.
|
*/

$user = requireRole([
    'admin',
    'staff'
]);

/*
|--------------------------------------------------------------------------
| Read JSON Input
|--------------------------------------------------------------------------
*/

$rawInput = file_get_contents('php://input');

$input = json_decode($rawInput, true);

if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' => 'Invalid JSON request.'
    ]);

    exit;
}

if (!is_array($input)) {
    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' => 'Request body must be a valid JSON object.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Get Room Type ID
|--------------------------------------------------------------------------
*/

$roomTypeId = isset($input['room_type_id'])
    ? $input['room_type_id']
    : null;

/*
|--------------------------------------------------------------------------
| Room Type ID Validation
|--------------------------------------------------------------------------
*/

if ($roomTypeId === null || $roomTypeId === '') {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Room type ID is required.'
    ]);

    exit;
}

if (
    filter_var(
        $roomTypeId,
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 1
            ]
        ]
    ) === false
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Room type ID must be a valid positive integer.'
    ]);

    exit;
}

$roomTypeId = (int) $roomTypeId;

/*
|--------------------------------------------------------------------------
| Get Input
|--------------------------------------------------------------------------
*/

$name = isset($input['name'])
    ? trim($input['name'])
    : '';

$description = isset($input['description'])
    ? trim($input['description'])
    : '';

$status = isset($input['status'])
    ? strtolower(trim($input['status']))
    : '';

/*
|--------------------------------------------------------------------------
| Required Fields
|--------------------------------------------------------------------------
*/

if ($name === '') {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Room type name is required.'
    ]);

    exit;
}

if ($status === '') {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Status is required.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Name Length Validation
|--------------------------------------------------------------------------
*/

$nameLength = strlen($name);

if ($nameLength < 2) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Room type name must be at least 2 characters.'
    ]);

    exit;
}

if ($nameLength > 100) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Room type name cannot exceed 100 characters.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Name Format Validation
|--------------------------------------------------------------------------
|
| Allows:
| - English letters
| - Numbers
| - Single spaces
|
| Rejects:
| - Leading spaces
| - Trailing spaces
| - Multiple spaces
| - Special characters
|
*/

if (!preg_match('/^[A-Za-z0-9]+(?: [A-Za-z0-9]+)*$/', $name)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'Room type name can contain only letters, numbers and single spaces.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Description Validation
|--------------------------------------------------------------------------
*/

if (strlen($description) > 1000) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Description cannot exceed 1000 characters.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Status Validation
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    'active',
    'inactive'
];

if (!in_array($status, $allowedStatuses, true)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Status must be either active or inactive.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Check Room Type Exists
|--------------------------------------------------------------------------
*/

$checkRoomType = $conn->prepare("
    SELECT
        id,
        name,
        slug,
        description,
        status
    FROM room_types
    WHERE id = ?
    LIMIT 1
");

if (!$checkRoomType) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to process room type request.'
    ]);

    exit;
}

$checkRoomType->bind_param(
    'i',
    $roomTypeId
);

if (!$checkRoomType->execute()) {
    $checkRoomType->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to process room type request.'
    ]);

    exit;
}

$roomTypeResult = $checkRoomType->get_result();

if ($roomTypeResult->num_rows === 0) {
    $checkRoomType->close();

    http_response_code(404);

    echo json_encode([
        'status' => false,
        'message' => 'Room type not found.'
    ]);

    exit;
}

$currentRoomType = $roomTypeResult->fetch_assoc();

$checkRoomType->close();

/*
|--------------------------------------------------------------------------
| Generate New Slug
|--------------------------------------------------------------------------
*/

$slug = strtolower($name);

$slug = preg_replace(
    '/[^a-z0-9]+/',
    '-',
    $slug
);

$slug = trim(
    $slug,
    '-'
);

/*
|--------------------------------------------------------------------------
| Validate Generated Slug
|--------------------------------------------------------------------------
*/

if ($slug === '') {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to generate a valid room type slug.'
    ]);

    exit;
}

if (strlen($slug) > 150) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Generated room type slug is too long.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Check Duplicate Name
|--------------------------------------------------------------------------
|
| Exclude current room type.
|
*/

$checkName = $conn->prepare("
    SELECT id
    FROM room_types
    WHERE LOWER(name) = LOWER(?)
      AND id != ?
    LIMIT 1
");

if (!$checkName) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to process room type request.'
    ]);

    exit;
}

$checkName->bind_param(
    'si',
    $name,
    $roomTypeId
);

if (!$checkName->execute()) {
    $checkName->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to process room type request.'
    ]);

    exit;
}

$nameResult = $checkName->get_result();

if ($nameResult->num_rows > 0) {
    $checkName->close();

    http_response_code(409);

    echo json_encode([
        'status' => false,
        'message' => 'Another room type with this name already exists.'
    ]);

    exit;
}

$checkName->close();

/*
|--------------------------------------------------------------------------
| Check Duplicate Slug
|--------------------------------------------------------------------------
|
| Exclude current room type.
|
*/

$checkSlug = $conn->prepare("
    SELECT id
    FROM room_types
    WHERE slug = ?
      AND id != ?
    LIMIT 1
");

if (!$checkSlug) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to process room type request.'
    ]);

    exit;
}

$checkSlug->bind_param(
    'si',
    $slug,
    $roomTypeId
);

if (!$checkSlug->execute()) {
    $checkSlug->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to process room type request.'
    ]);

    exit;
}

$slugResult = $checkSlug->get_result();

if ($slugResult->num_rows > 0) {
    $checkSlug->close();

    http_response_code(409);

    echo json_encode([
        'status' => false,
        'message' => 'Another room type with the same slug already exists.'
    ]);

    exit;
}

$checkSlug->close();

/*
|--------------------------------------------------------------------------
| Update Room Type
|--------------------------------------------------------------------------
*/

$updateRoomType = $conn->prepare("
    UPDATE room_types
    SET
        name = ?,
        slug = ?,
        description = ?,
        status = ?
    WHERE id = ?
");

if (!$updateRoomType) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to update room type.'
    ]);

    exit;
}

$updateRoomType->bind_param(
    'ssssi',
    $name,
    $slug,
    $description,
    $status,
    $roomTypeId
);

if (!$updateRoomType->execute()) {

    /*
    |--------------------------------------------------------------------------
    | Handle Duplicate Database Constraint
    |--------------------------------------------------------------------------
    */

    if ($updateRoomType->errno === 1062) {
        $updateRoomType->close();

        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' => 'This room type name or slug already exists.'
        ]);

        exit;
    }

    $updateRoomType->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to update room type.'
    ]);

    exit;
}

$affectedRows = $updateRoomType->affected_rows;

$updateRoomType->close();

/*
|--------------------------------------------------------------------------
| Success Response
|--------------------------------------------------------------------------
*/

http_response_code(200);

echo json_encode([
    'status' => true,
    'message' => 'Room type updated successfully.',
    'data' => [
        'room_type_id' => $roomTypeId,
        'name' => $name,
        'slug' => $slug,
        'description' => $description,
        'status' => $status,
        'updated_by' => [
            'user_id' => (int) $user['sub'],
            'role' => $user['role']
        ],
        'changed' => $affectedRows > 0
    ]
]);

exit;