<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, POST, OPTIONS");
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
    'facility_id',
    'name',
    'status'
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
// FACILITY ID VALIDATION
// --------------------------------------------------

if (!array_key_exists('facility_id', $data)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Facility ID is required.'
    ]);

    exit;
}


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
        'message' => 'Facility ID must be a valid positive integer.'
    ]);

    exit;
}


$facilityId = (int) $data['facility_id'];


// --------------------------------------------------
// AT LEAST ONE UPDATE FIELD
// --------------------------------------------------

$updateFieldsProvided = array_intersect(
    [
        'name',
        'status'
    ],
    array_keys($data)
);

if (empty($updateFieldsProvided)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'At least one field must be provided for update.',
        'updatable_fields' => [
            'name',
            'status'
        ]
    ]);

    exit;
}


// --------------------------------------------------
// FETCH CURRENT FACILITY
// --------------------------------------------------

$currentStmt = $conn->prepare("
    SELECT
        id,
        name,
        status,
        created_at
    FROM hall_facilities
    WHERE id = ?
    LIMIT 1
");


if (!$currentStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare facility lookup.'
    ]);

    exit;
}


$currentStmt->bind_param(
    'i',
    $facilityId
);


if (!$currentStmt->execute()) {

    $currentStmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to fetch facility.'
    ]);

    exit;
}


$currentResult = $currentStmt->get_result();


if ($currentResult->num_rows !== 1) {

    $currentStmt->close();

    http_response_code(404);

    echo json_encode([
        'status' => false,
        'message' => 'Hall facility not found.'
    ]);

    exit;
}


$currentFacility = $currentResult->fetch_assoc();

$currentStmt->close();


// --------------------------------------------------
// CURRENT VALUES
// --------------------------------------------------

$currentName = $currentFacility['name'];
$currentStatus = $currentFacility['status'];


// --------------------------------------------------
// NAME VALIDATION
// --------------------------------------------------

$newName = $currentName;

if (array_key_exists('name', $data)) {

    if (!is_string($data['name'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Facility name must be a string.'
        ]);

        exit;
    }


    $newName = trim($data['name']);


    // ----------------------------------------------
    // LENGTH
    // ----------------------------------------------

    $nameLength = strlen($newName);

    if ($nameLength < 2 || $nameLength > 150) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Facility name must be between 2 and 150 characters.'
        ]);

        exit;
    }


    // ----------------------------------------------
    // FORMAT
    // ----------------------------------------------

    if (
        !preg_match(
            '/^[A-Za-z0-9&().,\/-]+(?: [A-Za-z0-9&().,\/-]+)*$/',
            $newName
        )
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Facility name contains invalid characters or spacing.'
        ]);

        exit;
    }


    // ----------------------------------------------
    // MULTIPLE SPACES
    // ----------------------------------------------

    if (preg_match('/\s{2,}/', $newName)) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Facility name must not contain multiple consecutive spaces.'
        ]);

        exit;
    }
}


// --------------------------------------------------
// STATUS VALIDATION
// --------------------------------------------------

$newStatus = $currentStatus;

if (array_key_exists('status', $data)) {

    if (!is_string($data['status'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Status must be a string.'
        ]);

        exit;
    }


    $newStatus = strtolower(
        trim($data['status'])
    );


    $allowedStatuses = [
        'active',
        'inactive'
    ];


    if (
        !in_array(
            $newStatus,
            $allowedStatuses,
            true
        )
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Invalid status.',
            'allowed_statuses' => $allowedStatuses
        ]);

        exit;
    }
}


// --------------------------------------------------
// CHECK ACTUAL CHANGES
// --------------------------------------------------

$changedFields = [];

if ($newName !== $currentName) {
    $changedFields[] = 'name';
}

if ($newStatus !== $currentStatus) {
    $changedFields[] = 'status';
}


// --------------------------------------------------
// NO CHANGES
// --------------------------------------------------

if (empty($changedFields)) {

    http_response_code(200);

    echo json_encode([
        'status' => true,
        'message' => 'No changes were made.',
        'data' => [
            'facility' => [
                'id' => (int) $currentFacility['id'],
                'name' => $currentFacility['name'],
                'status' => $currentFacility['status'],
                'created_at' => $currentFacility['created_at']
            ]
        ],
        'updated_fields' => [],
        'affected_rows' => 0,
        'updated_by' => [
            'user_id' => (int) $user['sub'],
            'role' => $user['role']
        ]
    ]);

    exit;
}


// --------------------------------------------------
// DUPLICATE NAME CHECK
// --------------------------------------------------

if (
    in_array(
        'name',
        $changedFields,
        true
    )
) {

    $duplicateStmt = $conn->prepare("
        SELECT id
        FROM hall_facilities
        WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))
          AND id != ?
        LIMIT 1
    ");


    if (!$duplicateStmt) {
        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' =>
                'Failed to prepare duplicate validation.'
        ]);

        exit;
    }


    $duplicateStmt->bind_param(
        'si',
        $newName,
        $facilityId
    );


    if (!$duplicateStmt->execute()) {

        $duplicateStmt->close();

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' =>
                'Failed to validate facility name.'
        ]);

        exit;
    }


    $duplicateResult = $duplicateStmt->get_result();


    if ($duplicateResult->num_rows > 0) {

        $duplicateStmt->close();

        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' =>
                'Another hall facility with this name already exists.'
        ]);

        exit;
    }


    $duplicateStmt->close();
}


// --------------------------------------------------
// UPDATE FACILITY
// --------------------------------------------------

try {

    $conn->begin_transaction();


    // --------------------------------------------------
    // LOCK CURRENT ROW
    // --------------------------------------------------

    $lockStmt = $conn->prepare("
        SELECT
            id,
            name,
            status
        FROM hall_facilities
        WHERE id = ?
        FOR UPDATE
    ");


    if (!$lockStmt) {
        throw new Exception(
            'Failed to lock facility.'
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
            'message' => 'Hall facility not found.'
        ]);

        exit;
    }


    $lockedFacility = $lockResult->fetch_assoc();

    $lockStmt->close();


    // --------------------------------------------------
    // RECHECK DUPLICATE INSIDE TRANSACTION
    // --------------------------------------------------

    if (
        in_array(
            'name',
            $changedFields,
            true
        )
    ) {

        $duplicateLockStmt = $conn->prepare("
            SELECT id
            FROM hall_facilities
            WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))
              AND id != ?
            LIMIT 1
        ");


        if (!$duplicateLockStmt) {
            throw new Exception(
                'Failed to prepare duplicate validation.'
            );
        }


        $duplicateLockStmt->bind_param(
            'si',
            $newName,
            $facilityId
        );


        if (!$duplicateLockStmt->execute()) {

            $duplicateLockStmt->close();

            throw new Exception(
                'Failed to validate duplicate facility.'
            );
        }


        $duplicateLockResult =
            $duplicateLockStmt->get_result();


        if ($duplicateLockResult->num_rows > 0) {

            $duplicateLockStmt->close();

            $conn->rollback();

            http_response_code(409);

            echo json_encode([
                'status' => false,
                'message' =>
                    'Another hall facility with this name already exists.'
            ]);

            exit;
        }


        $duplicateLockStmt->close();
    }


    // --------------------------------------------------
    // DYNAMIC UPDATE
    // --------------------------------------------------

    $setParts = [];
    $bindTypes = '';
    $bindValues = [];


    if (
        in_array(
            'name',
            $changedFields,
            true
        )
    ) {

        $setParts[] = 'name = ?';
        $bindTypes .= 's';
        $bindValues[] = $newName;
    }


    if (
        in_array(
            'status',
            $changedFields,
            true
        )
    ) {

        $setParts[] = 'status = ?';
        $bindTypes .= 's';
        $bindValues[] = $newStatus;
    }


    $bindTypes .= 'i';
    $bindValues[] = $facilityId;


    $sql = "
        UPDATE hall_facilities
        SET " . implode(', ', $setParts) . "
        WHERE id = ?
    ";


    $updateStmt = $conn->prepare($sql);


    if (!$updateStmt) {
        throw new Exception(
            'Failed to prepare facility update.'
        );
    }


    $bindParams = [];
    $bindParams[] = $bindTypes;


    foreach ($bindValues as $key => $value) {
        $bindParams[] = &$bindValues[$key];
    }


    call_user_func_array(
        [$updateStmt, 'bind_param'],
        $bindParams
    );


    if (!$updateStmt->execute()) {

        $errorNumber = $updateStmt->errno;
        $errorMessage = $updateStmt->error;

        $updateStmt->close();


        if ($errorNumber === 1062) {

            $conn->rollback();

            http_response_code(409);

            echo json_encode([
                'status' => false,
                'message' =>
                    'Another hall facility with this name already exists.'
            ]);

            exit;
        }


        throw new Exception($errorMessage);
    }


    $affectedRows = $updateStmt->affected_rows;

    $updateStmt->close();


    // --------------------------------------------------
    // FETCH UPDATED FACILITY
    // --------------------------------------------------

    $fetchStmt = $conn->prepare("
        SELECT
            id,
            name,
            status,
            created_at
        FROM hall_facilities
        WHERE id = ?
        LIMIT 1
    ");


    if (!$fetchStmt) {
        throw new Exception(
            'Failed to prepare updated facility fetch.'
        );
    }


    $fetchStmt->bind_param(
        'i',
        $facilityId
    );


    if (!$fetchStmt->execute()) {

        $fetchStmt->close();

        throw new Exception(
            'Failed to fetch updated facility.'
        );
    }


    $fetchResult = $fetchStmt->get_result();


    if ($fetchResult->num_rows !== 1) {

        $fetchStmt->close();

        throw new Exception(
            'Updated facility could not be found.'
        );
    }


    $updatedFacility = $fetchResult->fetch_assoc();

    $fetchStmt->close();


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
        'message' => 'Hall facility updated successfully.',
        'data' => [
            'facility' => [
                'id' => (int) $updatedFacility['id'],
                'name' => $updatedFacility['name'],
                'status' => $updatedFacility['status'],
                'created_at' => $updatedFacility['created_at']
            ]
        ],
        'updated_fields' => $changedFields,
        'affected_rows' => (int) $affectedRows,
        'updated_by' => [
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
        'message' => 'Failed to update hall facility.'
    ]);

    exit;
}