<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/middleware.php';

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'status' => false,
        'message' => 'Only POST method is allowed.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
|
| Admin and Staff can create room types.
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
    : 'active';

/*
|--------------------------------------------------------------------------
| Name Validation
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
| Examples:
| Deluxe Room
| Premium Room
| Triple Sharing 2
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
| Generate Slug
|--------------------------------------------------------------------------
|
| Example:
|
| Deluxe Room
|     ↓
| deluxe-room
|
| Executive Suite
|     ↓
| executive-suite
|
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
| Check Duplicate Room Type Name
|--------------------------------------------------------------------------
*/

$checkName = $conn->prepare("
    SELECT id
    FROM room_types
    WHERE LOWER(name) = LOWER(?)
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
    's',
    $name
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
        'message' => 'This room type already exists.'
    ]);

    exit;
}

$checkName->close();

/*
|--------------------------------------------------------------------------
| Check Duplicate Slug
|--------------------------------------------------------------------------
*/

$checkSlug = $conn->prepare("
    SELECT id
    FROM room_types
    WHERE slug = ?
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
    's',
    $slug
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
        'message' => 'A room type with the same slug already exists.'
    ]);

    exit;
}

$checkSlug->close();

/*
|--------------------------------------------------------------------------
| Insert Room Type
|--------------------------------------------------------------------------
*/

$insertRoomType = $conn->prepare("
    INSERT INTO room_types
    (
        name,
        slug,
        description,
        status
    )
    VALUES
    (
        ?,
        ?,
        ?,
        ?
    )
");

if (!$insertRoomType) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to create room type.'
    ]);

    exit;
}

$insertRoomType->bind_param(
    'ssss',
    $name,
    $slug,
    $description,
    $status
);

if (!$insertRoomType->execute()) {

    /*
    |--------------------------------------------------------------------------
    | Handle Duplicate Database Constraint
    |--------------------------------------------------------------------------
    */

    if ($insertRoomType->errno === 1062) {
        $insertRoomType->close();

        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' => 'This room type already exists.'
        ]);

        exit;
    }

    $insertRoomType->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to create room type.'
    ]);

    exit;
}

$roomTypeId = $conn->insert_id;

$insertRoomType->close();

/*
|--------------------------------------------------------------------------
| Success Response
|--------------------------------------------------------------------------
*/

http_response_code(201);

echo json_encode([
    'status' => true,
    'message' => 'Room type created successfully.',
    'data' => [
        'room_type_id' => (int) $roomTypeId,
        'name' => $name,
        'slug' => $slug,
        'description' => $description,
        'status' => $status,
        'created_by' => [
            'user_id' => (int) $user['sub'],
            'role' => $user['role']
        ]
    ]
]);

exit;