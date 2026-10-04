<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: DELETE, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");


// --------------------------------------------------
// OPTIONS REQUEST
// --------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}


// --------------------------------------------------
// METHOD VALIDATION
// --------------------------------------------------

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


// --------------------------------------------------
// CONFIG
// --------------------------------------------------

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/middleware.php';


// --------------------------------------------------
// AUTHENTICATION
// --------------------------------------------------

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
    'facility_id'
];

$unknownFields = array_diff(
    array_keys($data),
    $allowedFields
);

if (!empty($unknownFields)) {
    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' => 'Unknown field(s) found.',
        'unknown_fields' => array_values($unknownFields)
    ]);

    exit;
}


// --------------------------------------------------
// FACILITY ID REQUIRED
// --------------------------------------------------

if (!array_key_exists('facility_id', $data)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Facility ID is required.'
    ]);

    exit;
}


// --------------------------------------------------
// FACILITY ID VALIDATION
// --------------------------------------------------

if (
    filter_var(
        $data['facility_id'],
        FILTER_VALIDATE_INT
    ) === false ||
    (int) $data['facility_id'] <= 0
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'Facility ID must be a valid positive integer.'
    ]);

    exit;
}


$facilityId = (int) $data['facility_id'];


// --------------------------------------------------
// FETCH FACILITY
// --------------------------------------------------

$facilityStmt = $conn->prepare("
    SELECT
        id,
        name,
        status,
        created_at
    FROM hall_facilities
    WHERE id = ?
    LIMIT 1
");


if (!$facilityStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to prepare facility lookup.'
    ]);

    exit;
}


$facilityStmt->bind_param(
    'i',
    $facilityId
);


if (!$facilityStmt->execute()) {

    $facilityStmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to fetch hall facility.'
    ]);

    exit;
}


$facilityResult = $facilityStmt->get_result();


if ($facilityResult->num_rows !== 1) {

    $facilityStmt->close();

    http_response_code(404);

    echo json_encode([
        'status' => false,
        'message' =>
            'Hall facility not found.'
    ]);

    exit;
}


$facility = $facilityResult->fetch_assoc();

$facilityStmt->close();


// --------------------------------------------------
// CHECK FACILITY MAPPINGS
// --------------------------------------------------
//
// hall_facility_map:
//     hall_id
//     facility_id
//
// If this facility is already assigned to a hall,
// hard deletion should be blocked.
//

$mappingStmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_halls
    FROM hall_facility_map
    WHERE facility_id = ?
");


if (!$mappingStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to prepare facility dependency check.'
    ]);

    exit;
}


$mappingStmt->bind_param(
    'i',
    $facilityId
);


if (!$mappingStmt->execute()) {

    $mappingStmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to check facility dependencies.'
    ]);

    exit;
}


$mappingResult = $mappingStmt->get_result();

$mappingData = $mappingResult->fetch_assoc();

$mappingStmt->close();


$totalHalls = (int) (
    $mappingData['total_halls'] ?? 0
);


// --------------------------------------------------
// BLOCK DELETE IF ASSIGNED
// --------------------------------------------------

if ($totalHalls > 0) {

    http_response_code(409);

    echo json_encode([
        'status' => false,
        'message' =>
            'This hall facility cannot be deleted because it is assigned to one or more halls.',
        'data' => [
            'facility' => [
                'id' => (int) $facility['id'],
                'name' => $facility['name'],
                'status' => $facility['status']
            ],
            'total_halls_using_facility' => $totalHalls
        ],
        'suggestion' =>
            'Set the facility status to inactive instead of deleting it.'
    ]);

    exit;
}


// --------------------------------------------------
// DELETE FACILITY
// --------------------------------------------------

try {

    $conn->begin_transaction();


    // --------------------------------------------------
    // LOCK FACILITY ROW
    // --------------------------------------------------

    $lockStmt = $conn->prepare("
        SELECT
            id,
            name,
            status,
            created_at
        FROM hall_facilities
        WHERE id = ?
        FOR UPDATE
    ");


    if (!$lockStmt) {
        throw new Exception(
            'Failed to prepare facility lock.'
        );
    }


    $lockStmt->bind_param(
        'i',
        $facilityId
    );


    if (!$lockStmt->execute()) {

        $lockStmt->close();

        throw new Exception(
            'Failed to lock facility.'
        );
    }


    $lockResult = $lockStmt->get_result();


    if ($lockResult->num_rows !== 1) {

        $lockStmt->close();

        $conn->rollback();

        http_response_code(404);

        echo json_encode([
            'status' => false,
            'message' =>
                'Hall facility not found.'
        ]);

        exit;
    }


    $lockedFacility = $lockResult->fetch_assoc();

    $lockStmt->close();


    // --------------------------------------------------
    // RECHECK DEPENDENCY INSIDE TRANSACTION
    // --------------------------------------------------

    $dependencyStmt = $conn->prepare("
        SELECT
            COUNT(*) AS total_halls
        FROM hall_facility_map
        WHERE facility_id = ?
    ");


    if (!$dependencyStmt) {
        throw new Exception(
            'Failed to prepare dependency validation.'
        );
    }


    $dependencyStmt->bind_param(
        'i',
        $facilityId
    );


    if (!$dependencyStmt->execute()) {

        $dependencyStmt->close();

        throw new Exception(
            'Failed to validate facility dependencies.'
        );
    }


    $dependencyResult =
        $dependencyStmt->get_result();

    $dependencyData =
        $dependencyResult->fetch_assoc();

    $dependencyStmt->close();


    $totalHallsInsideTransaction = (int) (
        $dependencyData['total_halls'] ?? 0
    );


    // --------------------------------------------------
    // BLOCK DELETE IF ASSIGNED
    // --------------------------------------------------

    if ($totalHallsInsideTransaction > 0) {

        $conn->rollback();

        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' =>
                'This hall facility cannot be deleted because it is assigned to one or more halls.',
            'data' => [
                'facility' => [
                    'id' => (int) $lockedFacility['id'],
                    'name' => $lockedFacility['name'],
                    'status' => $lockedFacility['status']
                ],
                'total_halls_using_facility' =>
                    $totalHallsInsideTransaction
            ],
            'suggestion' =>
                'Set the facility status to inactive instead of deleting it.'
        ]);

        exit;
    }


    // --------------------------------------------------
    // DELETE
    // --------------------------------------------------

    $deleteStmt = $conn->prepare("
        DELETE FROM hall_facilities
        WHERE id = ?
    ");


    if (!$deleteStmt) {
        throw new Exception(
            'Failed to prepare facility deletion.'
        );
    }


    $deleteStmt->bind_param(
        'i',
        $facilityId
    );


    if (!$deleteStmt->execute()) {

        $errorNumber = $deleteStmt->errno;
        $errorMessage = $deleteStmt->error;

        $deleteStmt->close();


        // ----------------------------------------------
        // FOREIGN KEY ERROR
        // ----------------------------------------------

        if ($errorNumber === 1451) {

            $conn->rollback();

            http_response_code(409);

            echo json_encode([
                'status' => false,
                'message' =>
                    'This hall facility cannot be deleted because it is being used by another record.'
            ]);

            exit;
        }


        throw new Exception($errorMessage);
    }


    $affectedRows = $deleteStmt->affected_rows;

    $deleteStmt->close();


    // --------------------------------------------------
    // VERIFY DELETION
    // --------------------------------------------------

    $verifyStmt = $conn->prepare("
        SELECT id
        FROM hall_facilities
        WHERE id = ?
        LIMIT 1
    ");


    if (!$verifyStmt) {
        throw new Exception(
            'Failed to prepare deletion verification.'
        );
    }


    $verifyStmt->bind_param(
        'i',
        $facilityId
    );


    if (!$verifyStmt->execute()) {

        $verifyStmt->close();

        throw new Exception(
            'Failed to verify facility deletion.'
        );
    }


    $verifyResult = $verifyStmt->get_result();

    $verifyStmt->close();


    if ($verifyResult->num_rows > 0) {

        throw new Exception(
            'Hall facility deletion could not be verified.'
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
        'message' =>
            'Hall facility deleted successfully.',
        'data' => [
            'deleted_facility' => [
                'id' => (int) $lockedFacility['id'],
                'name' => $lockedFacility['name'],
                'status' => $lockedFacility['status'],
                'created_at' =>
                    $lockedFacility['created_at']
            ],
            'total_halls_using_facility' => 0,
            'affected_rows' => (int) $affectedRows
        ],
        'deleted_by' => [
            'user_id' => (int) $user['sub'],
            'role' => $user['role']
        ]
    ]);

    exit;


} catch (Throwable $e) {

    if ($conn->in_transaction) {
        $conn->rollback();
    }


    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to delete hall facility.'
    ]);

    exit;
}