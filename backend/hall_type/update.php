<?php

header("Content-Type: application/json; charset=UTF-8");
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
    'hall_type_id',
    'name',
    'description',
    'status'
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

if (
    is_array($hallTypeId) ||
    is_object($hallTypeId) ||
    !is_numeric($hallTypeId)
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Hall type ID must be a positive integer.'
    ]);

    exit;
}

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
| At least one field must be updated
|--------------------------------------------------------------------------
*/

$updateFieldsRequested = array_intersect(
    [
        'name',
        'description',
        'status'
    ],
    array_keys($data)
);

if (count($updateFieldsRequested) === 0) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'At least one field must be provided for update: name, description or status.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Fetch existing hall type
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

$currentHallType = $fetchResult->fetch_assoc();

$fetchStmt->close();

/*
|--------------------------------------------------------------------------
| Current values
|--------------------------------------------------------------------------
*/

$currentName = $currentHallType['name'];
$currentSlug = $currentHallType['slug'];
$currentDescription = $currentHallType['description'];
$currentStatus = $currentHallType['status'];

$newName = $currentName;
$newSlug = $currentSlug;
$newDescription = $currentDescription;
$newStatus = $currentStatus;

/*
|--------------------------------------------------------------------------
| NAME VALIDATION
|--------------------------------------------------------------------------
*/

if (array_key_exists('name', $data)) {

    if (!is_string($data['name'])) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Hall type name must be a string.'
        ]);

        exit;
    }

    $newName = trim($data['name']);

    if ($newName === '') {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Hall type name cannot be empty.'
        ]);

        exit;
    }

    $nameLength = mb_strlen($newName);

    if ($nameLength < 2) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Hall type name must contain at least 2 characters.'
        ]);

        exit;
    }

    if ($nameLength > 150) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Hall type name cannot exceed 150 characters.'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Name format
    |--------------------------------------------------------------------------
    */

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
                'Hall type name contains invalid characters. ' .
                'Only letters, numbers, spaces, &, parentheses, dot, comma, slash and hyphen are allowed.'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Consecutive spaces
    |--------------------------------------------------------------------------
    */

    if (preg_match('/\s{2,}/', $newName)) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Hall type name cannot contain multiple consecutive spaces.'
        ]);

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| DESCRIPTION VALIDATION
|--------------------------------------------------------------------------
*/

if (array_key_exists('description', $data)) {

    if (
        $data['description'] !== null &&
        !is_string($data['description'])
    ) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Description must be a string or null.'
        ]);

        exit;
    }

    if ($data['description'] === null) {

        $newDescription = null;

    } else {

        $newDescription = trim($data['description']);

        if ($newDescription === '') {
            $newDescription = null;
        }

        if ($newDescription !== null) {

            $descriptionLength = mb_strlen($newDescription);

            if ($descriptionLength > 5000) {

                http_response_code(422);

                echo json_encode([
                    'status' => false,
                    'message' =>
                        'Description cannot exceed 5000 characters.'
                ]);

                exit;
            }

            /*
            |--------------------------------------------------------------------------
            | Consecutive spaces
            |--------------------------------------------------------------------------
            */

            if (preg_match('/\s{2,}/', $newDescription)) {

                http_response_code(422);

                echo json_encode([
                    'status' => false,
                    'message' =>
                        'Description cannot contain multiple consecutive spaces.'
                ]);

                exit;
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| STATUS VALIDATION
|--------------------------------------------------------------------------
*/

if (array_key_exists('status', $data)) {

    if (!is_string($data['status'])) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Status must be a string.'
        ]);

        exit;
    }

    $newStatus = trim($data['status']);

    $allowedStatuses = [
        'active',
        'inactive'
    ];

    if (!in_array($newStatus, $allowedStatuses, true)) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Invalid status. Allowed values: active, inactive.'
        ]);

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Check whether anything actually changed
|--------------------------------------------------------------------------
*/

$nameChanged =
    array_key_exists('name', $data) &&
    $newName !== $currentName;

$descriptionChanged =
    array_key_exists('description', $data) &&
    $newDescription !== $currentDescription;

$statusChanged =
    array_key_exists('status', $data) &&
    $newStatus !== $currentStatus;

if (
    !$nameChanged &&
    !$descriptionChanged &&
    !$statusChanged
) {

    http_response_code(200);

    echo json_encode([
        'status' => true,
        'message' => 'No changes were made.',
        'data' => [
            'id' => (int) $currentHallType['id'],
            'name' => $currentHallType['name'],
            'slug' => $currentHallType['slug'],
            'description' => $currentHallType['description'],
            'status' => $currentHallType['status'],
            'created_at' => $currentHallType['created_at'],
            'updated_at' => $currentHallType['updated_at']
        ],
        'updated_fields' => [],
        'affected_rows' => 0,
        'updated_by' => [
            'id' => (int) $user['sub'],
            'role' => $user['role']
        ]
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Generate new slug only if name changed
|--------------------------------------------------------------------------
*/

if ($nameChanged) {

    $newSlug = strtolower($newName);

    /*
    |--------------------------------------------------------------------------
    | & -> and
    |--------------------------------------------------------------------------
    */

    $newSlug = preg_replace(
        '/&/',
        'and',
        $newSlug
    );

    /*
    |--------------------------------------------------------------------------
    | Replace invalid characters with hyphen
    |--------------------------------------------------------------------------
    */

    $newSlug = preg_replace(
        '/[^a-z0-9]+/',
        '-',
        $newSlug
    );

    /*
    |--------------------------------------------------------------------------
    | Remove leading/trailing hyphen
    |--------------------------------------------------------------------------
    */

    $newSlug = trim(
        $newSlug,
        '-'
    );

    if ($newSlug === '') {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Unable to generate a valid slug from hall type name.'
        ]);

        exit;
    }

    if (strlen($newSlug) > 180) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Generated slug exceeds the maximum allowed length.'
        ]);

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Duplicate name check
|--------------------------------------------------------------------------
*/

if ($nameChanged) {

    $nameCheckSql = "
        SELECT
            id,
            name,
            slug
        FROM hall_types
        WHERE
            LOWER(TRIM(name)) = LOWER(TRIM(?))
            AND id <> ?
        LIMIT 1
    ";

    $nameCheckStmt = $conn->prepare($nameCheckSql);

    if (!$nameCheckStmt) {

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' =>
                'Failed to prepare duplicate name check.'
        ]);

        exit;
    }

    $nameCheckStmt->bind_param(
        'si',
        $newName,
        $hallTypeId
    );

    if (!$nameCheckStmt->execute()) {

        $nameCheckStmt->close();

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' =>
                'Failed to validate hall type name.'
        ]);

        exit;
    }

    $nameCheckResult =
        $nameCheckStmt->get_result();

    if ($nameCheckResult->num_rows > 0) {

        $existingName =
            $nameCheckResult->fetch_assoc();

        $nameCheckStmt->close();

        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' =>
                'Another hall type with this name already exists.',
            'data' => [
                'existing_id' =>
                    (int) $existingName['id'],
                'existing_name' =>
                    $existingName['name'],
                'existing_slug' =>
                    $existingName['slug']
            ]
        ]);

        exit;
    }

    $nameCheckStmt->close();
}

/*
|--------------------------------------------------------------------------
| Duplicate slug check
|--------------------------------------------------------------------------
*/

if ($nameChanged) {

    $slugCheckSql = "
        SELECT
            id,
            name,
            slug
        FROM hall_types
        WHERE
            slug = ?
            AND id <> ?
        LIMIT 1
    ";

    $slugCheckStmt =
        $conn->prepare($slugCheckSql);

    if (!$slugCheckStmt) {

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' =>
                'Failed to prepare duplicate slug check.'
        ]);

        exit;
    }

    $slugCheckStmt->bind_param(
        'si',
        $newSlug,
        $hallTypeId
    );

    if (!$slugCheckStmt->execute()) {

        $slugCheckStmt->close();

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' =>
                'Failed to validate hall type slug.'
        ]);

        exit;
    }

    $slugCheckResult =
        $slugCheckStmt->get_result();

    if ($slugCheckResult->num_rows > 0) {

        $existingSlug =
            $slugCheckResult->fetch_assoc();

        $slugCheckStmt->close();

        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' =>
                'Another hall type with the generated slug already exists.',
            'data' => [
                'existing_id' =>
                    (int) $existingSlug['id'],
                'existing_name' =>
                    $existingSlug['name'],
                'existing_slug' =>
                    $existingSlug['slug'],
                'generated_slug' =>
                    $newSlug
            ]
        ]);

        exit;
    }

    $slugCheckStmt->close();
}

/*
|--------------------------------------------------------------------------
| Build Dynamic UPDATE
|--------------------------------------------------------------------------
*/

$setParts = [];
$bindTypes = '';
$bindValues = [];
$updatedFields = [];

if ($nameChanged) {

    $setParts[] = 'name = ?';
    $bindTypes .= 's';
    $bindValues[] = $newName;

    $setParts[] = 'slug = ?';
    $bindTypes .= 's';
    $bindValues[] = $newSlug;

    $updatedFields[] = 'name';
    $updatedFields[] = 'slug';
}

if ($descriptionChanged) {

    $setParts[] = 'description = ?';
    $bindTypes .= 's';
    $bindValues[] = $newDescription;

    $updatedFields[] = 'description';
}

if ($statusChanged) {

    $setParts[] = 'status = ?';
    $bindTypes .= 's';
    $bindValues[] = $newStatus;

    $updatedFields[] = 'status';
}

/*
|--------------------------------------------------------------------------
| Add WHERE ID
|--------------------------------------------------------------------------
*/

$bindTypes .= 'i';
$bindValues[] = $hallTypeId;

$updateSql = "
    UPDATE hall_types
    SET " . implode(', ', $setParts) . "
    WHERE id = ?
    LIMIT 1
";

/*
|--------------------------------------------------------------------------
| Transaction
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();

try {

    $updateStmt =
        $conn->prepare($updateSql);

    if (!$updateStmt) {
        throw new Exception(
            'Failed to prepare hall type update.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Dynamic bind_param
    |--------------------------------------------------------------------------
    */

    $params = [];
    $params[] = $bindTypes;

    foreach ($bindValues as $key => $value) {
        $params[] = &$bindValues[$key];
    }

    call_user_func_array(
        [$updateStmt, 'bind_param'],
        $params
    );

    if (!$updateStmt->execute()) {

        $errorCode = $updateStmt->errno;
        $errorMessage = $updateStmt->error;

        $updateStmt->close();

        if ($errorCode === 1062) {

            throw new Exception(
                'Hall type name or slug already exists.'
            );
        }

        throw new Exception(
            'Failed to update hall type. ' .
            $errorMessage
        );
    }

    $affectedRows =
        $updateStmt->affected_rows;

    $updateStmt->close();

    /*
    |--------------------------------------------------------------------------
    | Fetch updated record
    |--------------------------------------------------------------------------
    */

    $finalSql = "
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

    $finalStmt =
        $conn->prepare($finalSql);

    if (!$finalStmt) {
        throw new Exception(
            'Failed to prepare updated hall type fetch.'
        );
    }

    $finalStmt->bind_param(
        'i',
        $hallTypeId
    );

    if (!$finalStmt->execute()) {

        $finalStmt->close();

        throw new Exception(
            'Failed to fetch updated hall type.'
        );
    }

    $finalResult =
        $finalStmt->get_result();

    if ($finalResult->num_rows !== 1) {

        $finalStmt->close();

        throw new Exception(
            'Updated hall type could not be found.'
        );
    }

    $updatedHallType =
        $finalResult->fetch_assoc();

    $finalStmt->close();

    $conn->commit();

    /*
    |--------------------------------------------------------------------------
    | Success
    |--------------------------------------------------------------------------
    */

    http_response_code(200);

    echo json_encode([
        'status' => true,
        'message' => 'Hall type updated successfully.',
        'data' => [
            'id' => (int) $updatedHallType['id'],
            'name' => $updatedHallType['name'],
            'slug' => $updatedHallType['slug'],
            'description' => $updatedHallType['description'],
            'status' => $updatedHallType['status'],
            'created_at' => $updatedHallType['created_at'],
            'updated_at' => $updatedHallType['updated_at']
        ],
        'updated_fields' => $updatedFields,
        'affected_rows' => $affectedRows,
        'updated_by' => [
            'id' => (int) $user['sub'],
            'role' => $user['role']
        ]
    ]);

} catch (Throwable $e) {

    $conn->rollback();

    /*
    |--------------------------------------------------------------------------
    | Duplicate error
    |--------------------------------------------------------------------------
    */

    if (
        str_contains(
            strtolower($e->getMessage()),
            'already exists'
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
    | Internal error
    |--------------------------------------------------------------------------
    */

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to update hall type.'
    ]);

    exit;
}