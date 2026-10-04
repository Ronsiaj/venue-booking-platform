<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'status' => false,
        'message' => 'Only POST method is allowed.'
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
// NAME REQUIRED
// --------------------------------------------------

if (!array_key_exists('name', $data)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Facility name is required.'
    ]);

    exit;
}


// --------------------------------------------------
// NAME TYPE
// --------------------------------------------------

if (!is_string($data['name'])) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Facility name must be a string.'
    ]);

    exit;
}


$name = trim($data['name']);


// --------------------------------------------------
// NAME LENGTH
// --------------------------------------------------

$nameLength = strlen($name);

if ($nameLength < 2 || $nameLength > 150) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'Facility name must be between 2 and 150 characters.'
    ]);

    exit;
}


// --------------------------------------------------
// NAME FORMAT
// --------------------------------------------------
//
// Allowed:
// A-Z
// a-z
// 0-9
// space
// &
// ( )
// .
// ,
// /
// -
//
// Examples:
// Air Conditioning
// Car Parking
// 24/7 Security
// AC & WiFi
// Stage (Main)
//

if (
    !preg_match(
        '/^[A-Za-z0-9&().,\/-]+(?: [A-Za-z0-9&().,\/-]+)*$/',
        $name
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


// --------------------------------------------------
// MULTIPLE SPACE VALIDATION
// --------------------------------------------------

if (preg_match('/\s{2,}/', $name)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'Facility name must not contain multiple consecutive spaces.'
    ]);

    exit;
}


// --------------------------------------------------
// STATUS
// --------------------------------------------------

$status = 'active';

if (array_key_exists('status', $data)) {

    if (!is_string($data['status'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Status must be a string.'
        ]);

        exit;
    }

    $status = strtolower(trim($data['status']));

    $allowedStatuses = [
        'active',
        'inactive'
    ];

    if (!in_array($status, $allowedStatuses, true)) {
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
// DUPLICATE NAME CHECK
// --------------------------------------------------

$duplicateStmt = $conn->prepare("
    SELECT id
    FROM hall_facilities
    WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))
    LIMIT 1
");

if (!$duplicateStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare duplicate validation.'
    ]);

    exit;
}


$duplicateStmt->bind_param(
    's',
    $name
);


if (!$duplicateStmt->execute()) {

    $duplicateStmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to validate facility name.'
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
            'A hall facility with this name already exists.'
    ]);

    exit;
}


$duplicateStmt->close();


// --------------------------------------------------
// CREATE FACILITY
// --------------------------------------------------

try {

    $conn->begin_transaction();


    // --------------------------------------------------
    // INSERT
    // --------------------------------------------------

    $insertStmt = $conn->prepare("
        INSERT INTO hall_facilities
        (
            name,
            status
        )
        VALUES
        (
            ?,
            ?
        )
    ");


    if (!$insertStmt) {
        throw new Exception(
            'Failed to prepare facility creation.'
        );
    }


    $insertStmt->bind_param(
        'ss',
        $name,
        $status
    );


    if (!$insertStmt->execute()) {

        $errorNumber = $insertStmt->errno;
        $errorMessage = $insertStmt->error;

        $insertStmt->close();


        if ($errorNumber === 1062) {

            $conn->rollback();

            http_response_code(409);

            echo json_encode([
                'status' => false,
                'message' =>
                    'A hall facility with this name already exists.'
            ]);

            exit;
        }


        throw new Exception($errorMessage);
    }


    $facilityId = (int) $insertStmt->insert_id;

    $insertStmt->close();


    if ($facilityId <= 0) {
        throw new Exception(
            'Failed to create hall facility.'
        );
    }


    // --------------------------------------------------
    // FETCH CREATED FACILITY
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
            'Failed to prepare facility fetch.'
        );
    }


    $fetchStmt->bind_param(
        'i',
        $facilityId
    );


    if (!$fetchStmt->execute()) {

        $fetchStmt->close();

        throw new Exception(
            'Failed to fetch created facility.'
        );
    }


    $facilityResult = $fetchStmt->get_result();


    if ($facilityResult->num_rows !== 1) {

        $fetchStmt->close();

        throw new Exception(
            'Created facility could not be found.'
        );
    }


    $facility = $facilityResult->fetch_assoc();

    $fetchStmt->close();


    // --------------------------------------------------
    // COMMIT
    // --------------------------------------------------

    $conn->commit();


    // --------------------------------------------------
    // SUCCESS
    // --------------------------------------------------

    http_response_code(201);

    echo json_encode([
        'status' => true,
        'message' => 'Hall facility created successfully.',
        'data' => [
            'facility' => [
                'id' => (int) $facility['id'],
                'name' => $facility['name'],
                'status' => $facility['status'],
                'created_at' => $facility['created_at']
            ]
        ],
        'created_by' => [
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
        'message' => 'Failed to create hall facility.'
    ]);

    exit;
}