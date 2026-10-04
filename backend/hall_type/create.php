<?php

header("Content-Type: application/json; charset=UTF-8");
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
| NAME VALIDATION
|--------------------------------------------------------------------------
*/

if (!array_key_exists('name', $data)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Hall type name is required.'
    ]);

    exit;
}

if (!is_string($data['name'])) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Hall type name must be a string.'
    ]);

    exit;
}

$name = trim($data['name']);

if ($name === '') {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Hall type name cannot be empty.'
    ]);

    exit;
}

$nameLength = mb_strlen($name);

if ($nameLength < 2) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Hall type name must contain at least 2 characters.'
    ]);

    exit;
}

if ($nameLength > 150) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Hall type name cannot exceed 150 characters.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Name format
|--------------------------------------------------------------------------
| Allowed:
| A-Z
| a-z
| 0-9
| &
| ( )
| .
| ,
| /
| -
| Single spaces between words
|--------------------------------------------------------------------------
*/

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
            'Hall type name contains invalid characters. ' .
            'Only letters, numbers, spaces, &, parentheses, dot, comma, slash and hyphen are allowed.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Reject consecutive spaces
|--------------------------------------------------------------------------
*/

if (preg_match('/\s{2,}/', $name)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Hall type name cannot contain multiple consecutive spaces.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| DESCRIPTION VALIDATION
|--------------------------------------------------------------------------
*/

$description = null;

if (array_key_exists('description', $data)) {

    if (
        $data['description'] !== null &&
        !is_string($data['description'])
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Description must be a string or null.'
        ]);

        exit;
    }

    if ($data['description'] !== null) {

        $description = trim($data['description']);

        if ($description === '') {
            $description = null;
        }

        if ($description !== null) {

            $descriptionLength = mb_strlen($description);

            if ($descriptionLength > 5000) {
                http_response_code(422);

                echo json_encode([
                    'status' => false,
                    'message' => 'Description cannot exceed 5000 characters.'
                ]);

                exit;
            }

            /*
            |--------------------------------------------------------------------------
            | Reject excessive consecutive spaces
            |--------------------------------------------------------------------------
            */

            if (preg_match('/\s{2,}/', $description)) {
                http_response_code(422);

                echo json_encode([
                    'status' => false,
                    'message' => 'Description cannot contain multiple consecutive spaces.'
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

    $status = trim($data['status']);

    $allowedStatuses = [
        'active',
        'inactive'
    ];

    if (!in_array($status, $allowedStatuses, true)) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Invalid status. Allowed values: active, inactive.'
        ]);

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Generate SLUG
|--------------------------------------------------------------------------
|
| Example:
| Marriage Hall -> marriage-hall
| Party Hall -> party-hall
| AC & Party Hall -> ac-party-hall
|--------------------------------------------------------------------------
*/

$slug = strtolower($name);

/*
|--------------------------------------------------------------------------
| Replace ampersand with "and"
|--------------------------------------------------------------------------
*/

$slug = preg_replace('/&/', 'and', $slug);

/*
|--------------------------------------------------------------------------
| Replace non-alphanumeric characters with hyphen
|--------------------------------------------------------------------------
*/

$slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

/*
|--------------------------------------------------------------------------
| Remove leading/trailing hyphens
|--------------------------------------------------------------------------
*/

$slug = trim($slug, '-');

/*
|--------------------------------------------------------------------------
| Validate generated slug
|--------------------------------------------------------------------------
*/

if ($slug === '') {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to generate a valid slug from hall type name.'
    ]);

    exit;
}

if (strlen($slug) > 180) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Generated slug exceeds the maximum allowed length.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Check duplicate hall type name
|--------------------------------------------------------------------------
*/

$nameCheckSql = "
    SELECT id, name, slug
    FROM hall_types
    WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))
    LIMIT 1
";

$nameCheckStmt = $conn->prepare($nameCheckSql);

if (!$nameCheckStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare duplicate name check.'
    ]);

    exit;
}

$nameCheckStmt->bind_param('s', $name);

if (!$nameCheckStmt->execute()) {

    $nameCheckStmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to validate hall type name.'
    ]);

    exit;
}

$nameCheckResult = $nameCheckStmt->get_result();

if ($nameCheckResult->num_rows > 0) {

    $existingName = $nameCheckResult->fetch_assoc();

    $nameCheckStmt->close();

    http_response_code(409);

    echo json_encode([
        'status' => false,
        'message' => 'Hall type with this name already exists.',
        'data' => [
            'existing_id' => (int) $existingName['id'],
            'existing_name' => $existingName['name'],
            'existing_slug' => $existingName['slug']
        ]
    ]);

    exit;
}

$nameCheckStmt->close();

/*
|--------------------------------------------------------------------------
| Check duplicate generated slug
|--------------------------------------------------------------------------
*/

$slugCheckSql = "
    SELECT id, name, slug
    FROM hall_types
    WHERE slug = ?
    LIMIT 1
";

$slugCheckStmt = $conn->prepare($slugCheckSql);

if (!$slugCheckStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare duplicate slug check.'
    ]);

    exit;
}

$slugCheckStmt->bind_param('s', $slug);

if (!$slugCheckStmt->execute()) {

    $slugCheckStmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to validate hall type slug.'
    ]);

    exit;
}

$slugCheckResult = $slugCheckStmt->get_result();

if ($slugCheckResult->num_rows > 0) {

    $existingSlug = $slugCheckResult->fetch_assoc();

    $slugCheckStmt->close();

    http_response_code(409);

    echo json_encode([
        'status' => false,
        'message' => 'A hall type with the generated slug already exists.',
        'data' => [
            'existing_id' => (int) $existingSlug['id'],
            'existing_name' => $existingSlug['name'],
            'existing_slug' => $existingSlug['slug'],
            'generated_slug' => $slug
        ]
    ]);

    exit;
}

$slugCheckStmt->close();

/*
|--------------------------------------------------------------------------
| Insert Hall Type
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();

try {

    $insertSql = "
        INSERT INTO hall_types
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
    ";

    $insertStmt = $conn->prepare($insertSql);

    if (!$insertStmt) {
        throw new Exception(
            'Failed to prepare hall type insert.'
        );
    }

    $insertStmt->bind_param(
        'ssss',
        $name,
        $slug,
        $description,
        $status
    );

    if (!$insertStmt->execute()) {

        $errorCode = $insertStmt->errno;
        $errorMessage = $insertStmt->error;

        $insertStmt->close();

        /*
        |--------------------------------------------------------------------------
        | Duplicate slug/name
        |--------------------------------------------------------------------------
        */

        if ($errorCode === 1062) {
            throw new Exception(
                'A hall type with this name or slug already exists.'
            );
        }

        throw new Exception(
            'Failed to create hall type. ' . $errorMessage
        );
    }

    $hallTypeId = $conn->insert_id;

    $insertStmt->close();

    /*
    |--------------------------------------------------------------------------
    | Fetch Created Hall Type
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
        throw new Exception(
            'Failed to prepare created hall type fetch.'
        );
    }

    $fetchStmt->bind_param(
        'i',
        $hallTypeId
    );

    if (!$fetchStmt->execute()) {

        $fetchStmt->close();

        throw new Exception(
            'Failed to fetch created hall type.'
        );
    }

    $fetchResult = $fetchStmt->get_result();

    if ($fetchResult->num_rows !== 1) {

        $fetchStmt->close();

        throw new Exception(
            'Created hall type could not be found.'
        );
    }

    $hallType = $fetchResult->fetch_assoc();

    $fetchStmt->close();

    $conn->commit();

    /*
    |--------------------------------------------------------------------------
    | Success Response
    |--------------------------------------------------------------------------
    */

    http_response_code(201);

    echo json_encode([
        'status' => true,
        'message' => 'Hall type created successfully.',
        'data' => [
            'id' => (int) $hallType['id'],
            'name' => $hallType['name'],
            'slug' => $hallType['slug'],
            'description' => $hallType['description'],
            'status' => $hallType['status'],
            'created_at' => $hallType['created_at'],
            'updated_at' => $hallType['updated_at']
        ],
        'created_by' => [
            'id' => (int) $user['sub'],
            'role' => $user['role']
        ]
    ]);

} catch (Throwable $e) {

    $conn->rollback();

    /*
    |--------------------------------------------------------------------------
    | Duplicate entry safety check
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
        'message' => 'Failed to create hall type.'
    ]);

    exit;
}