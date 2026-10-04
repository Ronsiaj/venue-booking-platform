<?php

/*
|--------------------------------------------------------------------------
| CORS Headers
|--------------------------------------------------------------------------
*/

header("Content-Type: application/json; charset=utf-8");

header("Access-Control-Allow-Origin: *");

header("Access-Control-Allow-Methods: DELETE, POST, OPTIONS");

header("Access-Control-Allow-Headers: Content-Type, Authorization");

/*
|--------------------------------------------------------------------------
| Handle OPTIONS Preflight
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {

    http_response_code(200);

    exit;
}

/*
|--------------------------------------------------------------------------
| Include Database & Middleware
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/middleware.php';

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
|
| Admin + Staff can delete rooms.
|
*/

$user = requireRole(['admin', 'staff']);

/*
|--------------------------------------------------------------------------
| Request Method
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| Read JSON Body
|--------------------------------------------------------------------------
*/

$rawInput = file_get_contents('php://input');

if (
    $rawInput === false ||
    trim($rawInput) === ''
) {

    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' => 'Request body is required.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Decode JSON
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| Reject Unknown Fields
|--------------------------------------------------------------------------
*/

$allowedFields = [
    'room_id'
];

foreach ($data as $key => $value) {

    if (!in_array($key, $allowedFields, true)) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Invalid field provided: ' . $key
        ]);

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Validate room_id Presence
|--------------------------------------------------------------------------
*/

if (!array_key_exists('room_id', $data)) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'room_id is required.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Validate room_id Type
|--------------------------------------------------------------------------
*/

$roomIdInput = $data['room_id'];

if (
    is_array($roomIdInput) ||
    is_object($roomIdInput)
) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'room_id must be a valid positive integer.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Validate Positive Integer
|--------------------------------------------------------------------------
*/

if (
    !is_int($roomIdInput) &&
    !ctype_digit((string) $roomIdInput)
) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'room_id must be a valid positive integer.'
    ]);

    exit;
}

$roomId = (int) $roomIdInput;

if ($roomId <= 0) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'room_id must be greater than 0.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Check Room Exists
|--------------------------------------------------------------------------
*/

$roomStmt = $conn->prepare("
    SELECT
        id,
        room_type_id,
        room_number,
        name,
        status
    FROM rooms
    WHERE id = ?
    LIMIT 1
");

if (!$roomStmt) {

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare room validation query.'
    ]);

    exit;
}

$roomStmt->bind_param(
    'i',
    $roomId
);

if (!$roomStmt->execute()) {

    $roomStmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to check room.'
    ]);

    exit;
}

$roomResult = $roomStmt->get_result();

if ($roomResult->num_rows === 0) {

    $roomStmt->close();

    http_response_code(404);

    echo json_encode([
        'status' => false,
        'message' => 'Room not found.'
    ]);

    exit;
}

$room = $roomResult->fetch_assoc();

$roomStmt->close();

/*
|--------------------------------------------------------------------------
| Check Room Booking References
|--------------------------------------------------------------------------
|
| A room should not be hard deleted if it is already
| connected with booking records.
|
*/

$bookingStmt = $conn->prepare("
    SELECT COUNT(*) AS total_bookings
    FROM booking_rooms
    WHERE room_id = ?
");

if (!$bookingStmt) {

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare booking reference check.'
    ]);

    exit;
}

$bookingStmt->bind_param(
    'i',
    $roomId
);

if (!$bookingStmt->execute()) {

    $bookingStmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to check room booking references.'
    ]);

    exit;
}

$bookingResult = $bookingStmt->get_result();

$bookingData = $bookingResult->fetch_assoc();

$bookingStmt->close();

$totalBookings = (int) (
    $bookingData['total_bookings'] ?? 0
);

/*
|--------------------------------------------------------------------------
| Prevent Delete If Booking Exists
|--------------------------------------------------------------------------
*/

if ($totalBookings > 0) {

    http_response_code(409);

    echo json_encode([
        'status' => false,
        'message' => 'Room cannot be deleted because it is already associated with booking records.',
        'data' => [
            'room_id' => $roomId,
            'room_number' => $room['room_number'],
            'room_name' => $room['name'],
            'total_bookings' => $totalBookings
        ]
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Begin Transaction
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();

try {

    /*
    |--------------------------------------------------------------------------
    | Delete Room
    |--------------------------------------------------------------------------
    */

    $deleteStmt = $conn->prepare("
        DELETE FROM rooms
        WHERE id = ?
    ");

    if (!$deleteStmt) {

        throw new Exception(
            'Failed to prepare room delete query.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Bind Room ID
    |--------------------------------------------------------------------------
    */

    $deleteStmt->bind_param(
        'i',
        $roomId
    );

    /*
    |--------------------------------------------------------------------------
    | Execute Delete
    |--------------------------------------------------------------------------
    */

    if (!$deleteStmt->execute()) {

        $errorCode = $deleteStmt->errno;

        $deleteStmt->close();

        /*
        |--------------------------------------------------------------------------
        | Foreign Key Constraint
        |--------------------------------------------------------------------------
        */

        if ($errorCode === 1451) {

            throw new Exception(
                'Room cannot be deleted because it is referenced by other records.'
            );
        }

        throw new Exception(
            'Failed to delete room.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Check Affected Rows
    |--------------------------------------------------------------------------
    */

    $affectedRows = $deleteStmt->affected_rows;

    $deleteStmt->close();

    if ($affectedRows !== 1) {

        throw new Exception(
            'Room was not deleted.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Commit Transaction
    |--------------------------------------------------------------------------
    */

    $conn->commit();

    /*
    |--------------------------------------------------------------------------
    | Success Response
    |--------------------------------------------------------------------------
    */

    http_response_code(200);

    echo json_encode([
        'status' => true,
        'message' => 'Room deleted successfully.',

        'data' => [
            'room_id' => $roomId,
            'room_number' => $room['room_number'],
            'name' => $room['name'],
            'status' => $room['status'],

            'deleted_by' => [
                'user_id' => (int) $user['sub'],
                'role' => $user['role']
            ]
        ]
    ]);

    exit;

} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | Rollback
    |--------------------------------------------------------------------------
    */

    $conn->rollback();

    /*
    |--------------------------------------------------------------------------
    | Log Error
    |--------------------------------------------------------------------------
    */

    error_log(
        '[ROOM DELETE] ' .
        $e->getMessage()
    );

    /*
    |--------------------------------------------------------------------------
    | Foreign Key / Reference Error
    |--------------------------------------------------------------------------
    */

    if (
        $e->getMessage() ===
        'Room cannot be deleted because it is referenced by other records.'
    ) {

        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' => 'Room cannot be deleted because it is referenced by other records.'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | General Error
    |--------------------------------------------------------------------------
    */

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to delete room.'
    ]);

    exit;
}