<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: DELETE, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

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

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/middleware.php';

$user = requireAdminOrStaff();

/*
|--------------------------------------------------------------------------
| Read JSON body
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
| Allowed fields
|--------------------------------------------------------------------------
*/

$allowedFields = [
    'hall_type_id'
];

foreach ($data as $key => $value) {

    if (!in_array($key, $allowedFields, true)) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => "Unknown field: {$key}."
        ]);

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| hall_type_id validation
|--------------------------------------------------------------------------
*/

if (!array_key_exists('hall_type_id', $data)) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Hall type ID is required.'
    ]);

    exit;
}

$hallTypeId = $data['hall_type_id'];

/*
|--------------------------------------------------------------------------
| Reject arrays / objects
|--------------------------------------------------------------------------
*/

if (
    is_array($hallTypeId) ||
    is_object($hallTypeId)
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Hall type ID must be a positive integer.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Positive integer validation
|--------------------------------------------------------------------------
*/

if (
    filter_var(
        $hallTypeId,
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
        'message' => 'Hall type ID must be a positive integer.'
    ]);

    exit;
}

$hallTypeId = (int) $hallTypeId;

/*
|--------------------------------------------------------------------------
| Fetch Hall Type
|--------------------------------------------------------------------------
*/

$fetchSql = "
    SELECT
        id,
        name,
        slug,
        description,
        status,
        created_at,
        updated_at
    FROM hall_types
    WHERE id = ?
    LIMIT 1
";

$fetchStmt = $conn->prepare($fetchSql);

if (!$fetchStmt) {

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare hall type query.'
    ]);

    exit;
}

$fetchStmt->bind_param(
    'i',
    $hallTypeId
);

if (!$fetchStmt->execute()) {

    $fetchStmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to fetch hall type.'
    ]);

    exit;
}

$fetchResult = $fetchStmt->get_result();

if ($fetchResult->num_rows !== 1) {

    $fetchStmt->close();

    http_response_code(404);

    echo json_encode([
        'status' => false,
        'message' => 'Hall type not found.'
    ]);

    exit;
}

$hallType = $fetchResult->fetch_assoc();

$fetchStmt->close();

/*
|--------------------------------------------------------------------------
| Check whether halls are using this hall type
|--------------------------------------------------------------------------
*/

$dependencySql = "
    SELECT
        COUNT(*) AS total_halls
    FROM halls
    WHERE hall_type_id = ?
";

$dependencyStmt = $conn->prepare($dependencySql);

if (!$dependencyStmt) {

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare hall dependency check.'
    ]);

    exit;
}

$dependencyStmt->bind_param(
    'i',
    $hallTypeId
);

if (!$dependencyStmt->execute()) {

    $dependencyStmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to check hall type dependencies.'
    ]);

    exit;
}

$dependencyResult =
    $dependencyStmt->get_result();

$dependencyRow =
    $dependencyResult->fetch_assoc();

$totalHalls =
    (int) ($dependencyRow['total_halls'] ?? 0);

$dependencyStmt->close();

/*
|--------------------------------------------------------------------------
| Do not delete if halls are assigned
|--------------------------------------------------------------------------
*/

if ($totalHalls > 0) {

    http_response_code(409);

    echo json_encode([
        'status' => false,
        'message' =>
            'Hall type cannot be deleted because it is assigned to existing halls.',
        'data' => [
            'hall_type_id' => $hallTypeId,
            'hall_type_name' => $hallType['name'],
            'assigned_halls' => $totalHalls
        ]
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Delete Hall Type
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();

try {

    $deleteSql = "
        DELETE FROM hall_types
        WHERE id = ?
        LIMIT 1
    ";

    $deleteStmt = $conn->prepare($deleteSql);

    if (!$deleteStmt) {
        throw new Exception(
            'Failed to prepare hall type delete.'
        );
    }

    $deleteStmt->bind_param(
        'i',
        $hallTypeId
    );

    if (!$deleteStmt->execute()) {

        $errorCode =
            $deleteStmt->errno;

        $errorMessage =
            $deleteStmt->error;

        $deleteStmt->close();

        /*
        |--------------------------------------------------------------------------
        | Foreign key dependency
        |--------------------------------------------------------------------------
        */

        if ($errorCode === 1451) {

            throw new Exception(
                'Hall type cannot be deleted because it is referenced by another record.'
            );
        }

        throw new Exception(
            'Failed to delete hall type. ' .
            $errorMessage
        );
    }

    $affectedRows =
        $deleteStmt->affected_rows;

    $deleteStmt->close();

    /*
    |--------------------------------------------------------------------------
    | Verify deletion
    |--------------------------------------------------------------------------
    */

    $verifySql = "
        SELECT id
        FROM hall_types
        WHERE id = ?
        LIMIT 1
    ";

    $verifyStmt =
        $conn->prepare($verifySql);

    if (!$verifyStmt) {
        throw new Exception(
            'Failed to prepare delete verification.'
        );
    }

    $verifyStmt->bind_param(
        'i',
        $hallTypeId
    );

    if (!$verifyStmt->execute()) {

        $verifyStmt->close();

        throw new Exception(
            'Failed to verify hall type deletion.'
        );
    }

    $verifyResult =
        $verifyStmt->get_result();

    $stillExists =
        $verifyResult->num_rows > 0;

    $verifyStmt->close();

    if ($stillExists) {

        throw new Exception(
            'Hall type could not be deleted.'
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
        'message' => 'Hall type deleted successfully.',
        'data' => [
            'id' => (int) $hallType['id'],
            'name' => $hallType['name'],
            'slug' => $hallType['slug'],
            'description' => $hallType['description'],
            'status' => $hallType['status'],
            'assigned_halls' => $totalHalls,
            'affected_rows' => $affectedRows
        ],
        'deleted_by' => [
            'id' => (int) $user['sub'],
            'role' => $user['role']
        ]
    ]);

} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | Rollback
    |--------------------------------------------------------------------------
    */

    $conn->rollback();

    /*
    |--------------------------------------------------------------------------
    | Foreign key / dependency conflict
    |--------------------------------------------------------------------------
    */

    if (
        str_contains(
            strtolower($e->getMessage()),
            'referenced'
        )
    ) {

        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' => $e->getMessage(),
            'data' => [
                'hall_type_id' => $hallTypeId,
                'assigned_halls' => $totalHalls
            ]
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Internal error
    |--------------------------------------------------------------------------
    */

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to delete hall type.'
    ]);

    exit;
}