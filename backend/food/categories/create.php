<?php

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/middleware.php';


/*
|--------------------------------------------------------------------------
| Method Validation
|--------------------------------------------------------------------------
*/

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
    'name',
    'description',
    'status'
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
| Required Field Validation
|--------------------------------------------------------------------------
*/

if (!array_key_exists('name', $data)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'name is required.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Name Validation
|--------------------------------------------------------------------------
*/

if (!is_string($data['name'])) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'name must be a string.'
    ]);

    exit;
}

$name = trim($data['name']);


/*
|--------------------------------------------------------------------------
| Name Empty Validation
|--------------------------------------------------------------------------
*/

if ($name === '') {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'name cannot be empty.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Name Length Validation
|--------------------------------------------------------------------------
*/

if (mb_strlen($name) < 2) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'name must contain at least 2 characters.'
    ]);

    exit;
}

if (mb_strlen($name) > 100) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'name cannot exceed 100 characters.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Name Character Validation
|--------------------------------------------------------------------------
|
| Allowed:
| Letters
| Numbers
| Spaces
| &
| Hyphen
| Parentheses
| Slash
| Dot
| Comma
|
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
            'name contains invalid characters. Only letters, numbers, spaces, hyphen, &, parentheses, slash, dot and comma are allowed.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Multiple Space Validation
|--------------------------------------------------------------------------
*/

if (preg_match('/\s{2,}/', $name)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'name cannot contain multiple consecutive spaces.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Description Validation
|--------------------------------------------------------------------------
*/

$description = null;

if (array_key_exists('description', $data)) {

    if ($data['description'] === null) {
        $description = null;
    } else {

        if (!is_string($data['description'])) {
            http_response_code(422);

            echo json_encode([
                'status' => false,
                'message' => 'description must be a string or null.'
            ]);

            exit;
        }

        $description = trim($data['description']);

        /*
        |--------------------------------------------------------------------------
        | Empty Description
        |--------------------------------------------------------------------------
        */

        if ($description === '') {
            $description = null;
        }

        /*
        |--------------------------------------------------------------------------
        | Description Length
        |--------------------------------------------------------------------------
        */

        if (
            $description !== null &&
            mb_strlen($description) > 500
        ) {
            http_response_code(422);

            echo json_encode([
                'status' => false,
                'message' =>
                    'description cannot exceed 500 characters.'
            ]);

            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | Multiple Consecutive Spaces
        |--------------------------------------------------------------------------
        */

        if (
            $description !== null &&
            preg_match('/\s{2,}/', $description)
        ) {
            http_response_code(422);

            echo json_encode([
                'status' => false,
                'message' =>
                    'description cannot contain multiple consecutive spaces.'
            ]);

            exit;
        }
    }
}


/*
|--------------------------------------------------------------------------
| Status Validation
|--------------------------------------------------------------------------
*/

$status = 'active';

if (array_key_exists('status', $data)) {

    if (!is_string($data['status'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'status must be a string.'
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


/*
|--------------------------------------------------------------------------
| Duplicate Name Validation
|--------------------------------------------------------------------------
|
| food_categories.name has UNIQUE constraint.
|
*/

$duplicateSql = "
    SELECT id
    FROM food_categories
    WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))
    LIMIT 1
";

$duplicateStmt = $conn->prepare($duplicateSql);

if (!$duplicateStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare duplicate validation query.'
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
        'message' => 'Failed to validate category name.'
    ]);

    exit;
}

$duplicateResult =
    $duplicateStmt->get_result();

$duplicateCategory =
    $duplicateResult->fetch_assoc();

$duplicateStmt->close();


if ($duplicateCategory) {
    http_response_code(409);

    echo json_encode([
        'status' => false,
        'message' =>
            'A food category with the same name already exists.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Insert Food Category
|--------------------------------------------------------------------------
*/

$insertSql = "
    INSERT INTO food_categories
    (
        name,
        description,
        status
    )
    VALUES (?, ?, ?)
";

$insertStmt = $conn->prepare($insertSql);

if (!$insertStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare category creation query.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Bind Parameters
|--------------------------------------------------------------------------
*/

$insertStmt->bind_param(
    'sss',
    $name,
    $description,
    $status
);


/*
|--------------------------------------------------------------------------
| Execute Insert
|--------------------------------------------------------------------------
*/

if (!$insertStmt->execute()) {

    $errorCode = $insertStmt->errno;

    $insertStmt->close();

    /*
    |--------------------------------------------------------------------------
    | Duplicate Key Error
    |--------------------------------------------------------------------------
    */

    if ($errorCode === 1062) {
        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' =>
                'A food category with the same name already exists.'
        ]);

        exit;
    }

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to create food category.'
    ]);

    exit;
}

$categoryId =
    $insertStmt->insert_id;

$insertStmt->close();


/*
|--------------------------------------------------------------------------
| Fetch Created Category
|--------------------------------------------------------------------------
*/

$fetchSql = "
    SELECT
        id,
        name,
        description,
        status,
        created_at
    FROM food_categories
    WHERE id = ?
    LIMIT 1
";

$fetchStmt = $conn->prepare($fetchSql);

if (!$fetchStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Category created, but failed to fetch created data.'
    ]);

    exit;
}

$fetchStmt->bind_param(
    'i',
    $categoryId
);

if (!$fetchStmt->execute()) {

    $fetchStmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Category created, but failed to fetch created data.'
    ]);

    exit;
}

$fetchResult =
    $fetchStmt->get_result();

$category =
    $fetchResult->fetch_assoc();

$fetchStmt->close();


/*
|--------------------------------------------------------------------------
| Verify Created Record
|--------------------------------------------------------------------------
*/

if (!$category) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Category was created but could not be verified.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Success Response
|--------------------------------------------------------------------------
*/

http_response_code(201);

echo json_encode([
    'status' => true,
    'message' => 'Food category created successfully.',
    'data' => [
        'id' => (int) $category['id'],
        'name' => $category['name'],
        'description' => $category['description'],
        'status' => $category['status'],
        'created_at' => $category['created_at']
    ],
    'created_by' => [
        'id' => (int) $user['sub'],
        'role' => $user['role']
    ]
]);

exit;