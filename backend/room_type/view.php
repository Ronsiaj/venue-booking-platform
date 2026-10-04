<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/middleware.php';

header('Content-Type: application/json; charset=UTF-8');

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

$user = requireRole(['admin', 'staff']);

/*
|--------------------------------------------------------------------------
| Request Method
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
| Validate room_type_id
|--------------------------------------------------------------------------
*/

if (!isset($_GET['room_type_id'])) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'room_type_id is required.'
    ]);

    exit;
}

$roomTypeIdInput = $_GET['room_type_id'];

/*
|--------------------------------------------------------------------------
| Reject Array Input
|--------------------------------------------------------------------------
*/

if (is_array($roomTypeIdInput)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'room_type_id must be a single value.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Trim Input
|--------------------------------------------------------------------------
*/

$roomTypeIdInput = trim((string) $roomTypeIdInput);

/*
|--------------------------------------------------------------------------
| Validate Positive Integer
|--------------------------------------------------------------------------
*/

if (
    $roomTypeIdInput === '' ||
    !ctype_digit($roomTypeIdInput)
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'room_type_id must be a valid positive integer.'
    ]);

    exit;
}

$roomTypeId = (int) $roomTypeIdInput;

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
| Fetch Room Type
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        rt.id,
        rt.name,
        rt.slug,
        rt.description,
        rt.status,
        rt.created_at,
        rt.updated_at,

        (
            SELECT COUNT(*)
            FROM rooms r
            WHERE r.room_type_id = rt.id
        ) AS total_rooms,

        (
            SELECT COUNT(*)
            FROM rooms r
            WHERE r.room_type_id = rt.id
            AND r.status = 'active'
        ) AS active_rooms,

        (
            SELECT COUNT(*)
            FROM rooms r
            WHERE r.room_type_id = rt.id
            AND r.status = 'inactive'
        ) AS inactive_rooms

    FROM room_types rt
    WHERE rt.id = ?
    LIMIT 1
");

/*
|--------------------------------------------------------------------------
| Check Prepare
|--------------------------------------------------------------------------
*/

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare room type query.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Bind ID
|--------------------------------------------------------------------------
*/

$stmt->bind_param('i', $roomTypeId);

/*
|--------------------------------------------------------------------------
| Execute
|--------------------------------------------------------------------------
*/

if (!$stmt->execute()) {

    $stmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to fetch room type.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Get Result
|--------------------------------------------------------------------------
*/

$result = $stmt->get_result();

/*
|--------------------------------------------------------------------------
| Room Type Not Found
|--------------------------------------------------------------------------
*/

if ($result->num_rows === 0) {

    $stmt->close();

    http_response_code(404);

    echo json_encode([
        'status' => false,
        'message' => 'Room type not found.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Fetch Data
|--------------------------------------------------------------------------
*/

$row = $result->fetch_assoc();

$stmt->close();

/*
|--------------------------------------------------------------------------
| Prepare Response
|--------------------------------------------------------------------------
*/

$roomType = [
    'id' => (int) $row['id'],
    'name' => $row['name'],
    'slug' => $row['slug'],
    'description' => $row['description'],
    'status' => $row['status'],

    'room_summary' => [
        'total_rooms' => (int) $row['total_rooms'],
        'active_rooms' => (int) $row['active_rooms'],
        'inactive_rooms' => (int) $row['inactive_rooms']
    ],

    'created_at' => $row['created_at'],
    'updated_at' => $row['updated_at']
];

/*
|--------------------------------------------------------------------------
| Success Response
|--------------------------------------------------------------------------
*/

http_response_code(200);

echo json_encode([
    'status' => true,
    'message' => 'Room type fetched successfully.',
    'data' => $roomType
]);

exit;