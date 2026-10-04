<?php

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: DELETE, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/middleware.php';


/*
|--------------------------------------------------------------------------
| Method Validation
|--------------------------------------------------------------------------
*/

$method = $_SERVER['REQUEST_METHOD'];

if (!in_array($method, ['DELETE', 'POST'], true)) {
    http_response_code(405);

    echo json_encode([
        'status' => false,
        'message' => 'Only DELETE or POST method is allowed.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

$user = requireAdminOrStaff();


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
| Allowed Fields
|--------------------------------------------------------------------------
*/

$allowedFields = [
    'amenity_id'
];


/*
|--------------------------------------------------------------------------
| Unknown Field Validation
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| amenity_id Required
|--------------------------------------------------------------------------
*/

if (!array_key_exists('amenity_id', $data)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'amenity_id is required.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| amenity_id Validation
|--------------------------------------------------------------------------
*/

$amenityId = $data['amenity_id'];


/*
|--------------------------------------------------------------------------
| Reject Boolean
|--------------------------------------------------------------------------
*/

if (is_bool($amenityId)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'amenity_id must be a positive integer.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Accept Integer or Numeric String
|--------------------------------------------------------------------------
*/

if (
    !is_int($amenityId) &&
    !is_string($amenityId)
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'amenity_id must be a positive integer.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Numeric String Validation
|--------------------------------------------------------------------------
*/

if (
    is_string($amenityId) &&
    !preg_match('/^[0-9]+$/', $amenityId)
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'amenity_id must be a positive integer.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Convert To Integer
|--------------------------------------------------------------------------
*/

$amenityId = (int) $amenityId;

if ($amenityId <= 0) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'amenity_id must be greater than 0.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Fetch Amenity
|--------------------------------------------------------------------------
*/

$selectSql = "
    SELECT
        id,
        name,
        icon,
        status,
        created_at
    FROM amenities
    WHERE id = ?
    LIMIT 1
";

$selectStmt = $conn->prepare($selectSql);

if (!$selectStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare amenity query.'
    ]);

    exit;
}

$selectStmt->bind_param(
    'i',
    $amenityId
);

if (!$selectStmt->execute()) {
    $selectStmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to fetch amenity.'
    ]);

    exit;
}

$result = $selectStmt->get_result();

$amenity = $result->fetch_assoc();

$selectStmt->close();


/*
|--------------------------------------------------------------------------
| Amenity Not Found
|--------------------------------------------------------------------------
*/

if (!$amenity) {
    http_response_code(404);

    echo json_encode([
        'status' => false,
        'message' => 'Amenity not found.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Check Room Mappings
|--------------------------------------------------------------------------
|
| room_amenities:
| room_id
| amenity_id
|
*/

$mappingSql = "
    SELECT
        COUNT(*) AS total_rooms
    FROM room_amenities
    WHERE amenity_id = ?
";

$mappingStmt = $conn->prepare($mappingSql);

if (!$mappingStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare dependency validation query.'
    ]);

    exit;
}

$mappingStmt->bind_param(
    'i',
    $amenityId
);

if (!$mappingStmt->execute()) {
    $mappingStmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to check amenity dependencies.'
    ]);

    exit;
}

$mappingResult =
    $mappingStmt->get_result();

$mappingData =
    $mappingResult->fetch_assoc();

$mappingStmt->close();

$totalRooms =
    (int) ($mappingData['total_rooms'] ?? 0);


/*
|--------------------------------------------------------------------------
| Prevent Delete If Used By Rooms
|--------------------------------------------------------------------------
*/

if ($totalRooms > 0) {
    http_response_code(409);

    echo json_encode([
        'status' => false,
        'message' =>
            'This amenity cannot be deleted because it is assigned to existing rooms.',
        'data' => [
            'amenity_id' => $amenityId,
            'name' => $amenity['name'],
            'assigned_room_count' => $totalRooms
        ],
        'suggestion' =>
            'Remove this amenity from all assigned rooms before deleting it.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Transaction Start
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();

try {

    /*
    |--------------------------------------------------------------------------
    | Delete Amenity
    |--------------------------------------------------------------------------
    */

    $deleteSql = "
        DELETE FROM amenities
        WHERE id = ?
        LIMIT 1
    ";

    $deleteStmt = $conn->prepare($deleteSql);

    if (!$deleteStmt) {
        throw new Exception(
            'Failed to prepare amenity delete query.'
        );
    }

    $deleteStmt->bind_param(
        'i',
        $amenityId
    );

    if (!$deleteStmt->execute()) {

        $errorCode = $deleteStmt->errno;

        $deleteStmt->close();

        /*
        |--------------------------------------------------------------------------
        | Foreign Key Error
        |--------------------------------------------------------------------------
        */

        if ($errorCode === 1451) {
            throw new Exception(
                'This amenity cannot be deleted because it is being used by another record.'
            );
        }

        throw new Exception(
            'Failed to delete amenity.'
        );
    }

    $affectedRows =
        $deleteStmt->affected_rows;

    $deleteStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Verify Delete
    |--------------------------------------------------------------------------
    */

    $verifySql = "
        SELECT id
        FROM amenities
        WHERE id = ?
        LIMIT 1
    ";

    $verifyStmt = $conn->prepare($verifySql);

    if (!$verifyStmt) {
        throw new Exception(
            'Failed to prepare delete verification query.'
        );
    }

    $verifyStmt->bind_param(
        'i',
        $amenityId
    );

    if (!$verifyStmt->execute()) {
        $verifyStmt->close();

        throw new Exception(
            'Failed to verify amenity deletion.'
        );
    }

    $verifyResult =
        $verifyStmt->get_result();

    $deletedRecord =
        $verifyResult->fetch_assoc();

    $verifyStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Delete Verification Failed
    |--------------------------------------------------------------------------
    */

    if ($deletedRecord) {
        throw new Exception(
            'Amenity deletion could not be verified.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Commit
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
        'message' => 'Amenity deleted successfully.',
        'data' => [
            'deleted_amenity' => [
                'id' => (int) $amenity['id'],
                'name' => $amenity['name'],
                'icon' => $amenity['icon'],
                'status' => $amenity['status'],
                'created_at' => $amenity['created_at']
            ],
            'assigned_room_count' => $totalRooms,
            'affected_rows' => $affectedRows
        ],
        'deleted_by' => [
            'id' => (int) $user['sub'],
            'role' => $user['role']
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
    | Foreign Key / Dependency Error
    |--------------------------------------------------------------------------
    */

    if (
        str_contains(
            strtolower($e->getMessage()),
            'another record'
        )
    ) {
        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' => $e->getMessage()
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
        'message' => 'Failed to delete amenity.'
    ]);

    exit;
}