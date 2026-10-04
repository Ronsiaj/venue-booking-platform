<?php

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, POST, OPTIONS");
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

$method = $_SERVER['REQUEST_METHOD'];

if (!in_array($method, ['PUT', 'POST'], true)) {
    http_response_code(405);

    echo json_encode([
        'status' => false,
        'message' => 'Only PUT or POST method is allowed.'
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
    'category_id',
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
| category_id Validation
|--------------------------------------------------------------------------
*/

if (!array_key_exists('category_id', $data)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'category_id is required.'
    ]);

    exit;
}

$categoryId = $data['category_id'];


/*
|--------------------------------------------------------------------------
| Reject Boolean
|--------------------------------------------------------------------------
*/

if (is_bool($categoryId)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'category_id must be a positive integer.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Accept Integer or Numeric String
|--------------------------------------------------------------------------
*/

if (
    !is_int($categoryId) &&
    !is_string($categoryId)
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'category_id must be a positive integer.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Numeric String Validation
|--------------------------------------------------------------------------
*/

if (
    is_string($categoryId) &&
    !preg_match('/^[0-9]+$/', $categoryId)
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'category_id must be a positive integer.'
    ]);

    exit;
}


$categoryId = (int) $categoryId;


/*
|--------------------------------------------------------------------------
| Positive Integer Validation
|--------------------------------------------------------------------------
*/

if ($categoryId <= 0) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'category_id must be greater than 0.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| At Least One Update Field Required
|--------------------------------------------------------------------------
*/

$updateFields = [
    'name',
    'description',
    'status'
];

$hasUpdateField = false;

foreach ($updateFields as $field) {
    if (array_key_exists($field, $data)) {
        $hasUpdateField = true;
        break;
    }
}

if (!$hasUpdateField) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'At least one field must be provided for update.',
        'allowed_update_fields' => $updateFields
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Fetch Existing Category
|--------------------------------------------------------------------------
*/

$selectSql = "
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

$selectStmt = $conn->prepare($selectSql);

if (!$selectStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare category query.'
    ]);

    exit;
}

$selectStmt->bind_param(
    'i',
    $categoryId
);

if (!$selectStmt->execute()) {

    $selectStmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to fetch category.'
    ]);

    exit;
}

$result = $selectStmt->get_result();

$existingCategory = $result->fetch_assoc();

$selectStmt->close();


/*
|--------------------------------------------------------------------------
| Category Not Found
|--------------------------------------------------------------------------
*/

if (!$existingCategory) {
    http_response_code(404);

    echo json_encode([
        'status' => false,
        'message' => 'Food category not found.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Current Values
|--------------------------------------------------------------------------
*/

$currentName =
    $existingCategory['name'];

$currentDescription =
    $existingCategory['description'];

$currentStatus =
    $existingCategory['status'];


/*
|--------------------------------------------------------------------------
| New Values
|--------------------------------------------------------------------------
*/

$newName = $currentName;

$newDescription =
    $currentDescription;

$newStatus =
    $currentStatus;


/*
|--------------------------------------------------------------------------
| Name Validation
|--------------------------------------------------------------------------
*/

if (array_key_exists('name', $data)) {

    if (!is_string($data['name'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'name must be a string.'
        ]);

        exit;
    }

    $name = trim($data['name']);

    if ($name === '') {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'name cannot be empty.'
        ]);

        exit;
    }

    if (mb_strlen($name) < 2) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'name must contain at least 2 characters.'
        ]);

        exit;
    }

    if (mb_strlen($name) > 100) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'name cannot exceed 100 characters.'
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Name Character Validation
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
            'message' =>
                'name cannot contain multiple consecutive spaces.'
        ]);

        exit;
    }

    $newName = $name;
}


/*
|--------------------------------------------------------------------------
| Description Validation
|--------------------------------------------------------------------------
*/

if (array_key_exists('description', $data)) {

    if ($data['description'] === null) {

        $newDescription = null;

    } else {

        if (!is_string($data['description'])) {
            http_response_code(422);

            echo json_encode([
                'status' => false,
                'message' =>
                    'description must be a string or null.'
            ]);

            exit;
        }

        $description =
            trim($data['description']);


        /*
        |--------------------------------------------------------------------------
        | Empty Description
        |--------------------------------------------------------------------------
        */

        if ($description === '') {
            $newDescription = null;
        } else {

            /*
            |--------------------------------------------------------------------------
            | Description Length
            |--------------------------------------------------------------------------
            */

            if (mb_strlen($description) > 500) {
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
            | Multiple Spaces
            |--------------------------------------------------------------------------
            */

            if (preg_match('/\s{2,}/', $description)) {
                http_response_code(422);

                echo json_encode([
                    'status' => false,
                    'message' =>
                        'description cannot contain multiple consecutive spaces.'
                ]);

                exit;
            }

            $newDescription = $description;
        }
    }
}


/*
|--------------------------------------------------------------------------
| Status Validation
|--------------------------------------------------------------------------
*/

if (array_key_exists('status', $data)) {

    if (!is_string($data['status'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'status must be a string.'
        ]);

        exit;
    }

    $status =
        strtolower(trim($data['status']));

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

    $newStatus = $status;
}


/*
|--------------------------------------------------------------------------
| Detect Actual Changes
|--------------------------------------------------------------------------
*/

$nameChanged =
    $newName !== $currentName;

$descriptionChanged =
    $newDescription !== $currentDescription;

$statusChanged =
    $newStatus !== $currentStatus;


/*
|--------------------------------------------------------------------------
| No Changes
|--------------------------------------------------------------------------
*/

if (
    !$nameChanged &&
    !$descriptionChanged &&
    !$statusChanged
) {
    http_response_code(200);

    echo json_encode([
        'status' => true,
        'message' => 'No changes detected.',
        'data' => [
            'id' => (int) $existingCategory['id'],
            'name' => $existingCategory['name'],
            'description' =>
                $existingCategory['description'],
            'status' => $existingCategory['status'],
            'created_at' =>
                $existingCategory['created_at']
        ]
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Duplicate Name Validation
|--------------------------------------------------------------------------
|
| Only check when name is actually changed.
|
*/

if ($nameChanged) {

    $duplicateSql = "
        SELECT id
        FROM food_categories
        WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))
        AND id != ?
        LIMIT 1
    ";

    $duplicateStmt =
        $conn->prepare($duplicateSql);

    if (!$duplicateStmt) {
        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' =>
                'Failed to prepare duplicate validation query.'
        ]);

        exit;
    }

    $duplicateStmt->bind_param(
        'si',
        $newName,
        $categoryId
    );

    if (!$duplicateStmt->execute()) {

        $duplicateStmt->close();

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' =>
                'Failed to validate category name.'
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
                'Another food category with the same name already exists.'
        ]);

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Build Dynamic UPDATE Query
|--------------------------------------------------------------------------
*/

$setParts = [];

$bindTypes = '';

$bindValues = [];


/*
|--------------------------------------------------------------------------
| Name
|--------------------------------------------------------------------------
*/

if ($nameChanged) {

    $setParts[] = 'name = ?';

    $bindTypes .= 's';

    $bindValues[] = $newName;
}


/*
|--------------------------------------------------------------------------
| Description
|--------------------------------------------------------------------------
*/

if ($descriptionChanged) {

    $setParts[] = 'description = ?';

    $bindTypes .= 's';

    /*
    |--------------------------------------------------------------------------
    | NULL Handling
    |--------------------------------------------------------------------------
    |
    | MySQLi bind_param sends NULL correctly for a nullable column.
    |
    */

    $bindValues[] = $newDescription;
}


/*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
*/

if ($statusChanged) {

    $setParts[] = 'status = ?';

    $bindTypes .= 's';

    $bindValues[] = $newStatus;
}


/*
|--------------------------------------------------------------------------
| Safety Check
|--------------------------------------------------------------------------
*/

if (empty($setParts)) {
    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' =>
            'No valid fields available for update.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Add WHERE Parameter
|--------------------------------------------------------------------------
*/

$bindTypes .= 'i';

$bindValues[] = $categoryId;


/*
|--------------------------------------------------------------------------
| Update Query
|--------------------------------------------------------------------------
*/

$updateSql = "
    UPDATE food_categories
    SET " . implode(', ', $setParts) . "
    WHERE id = ?
    LIMIT 1
";


/*
|--------------------------------------------------------------------------
| Prepare Update
|--------------------------------------------------------------------------
*/

$updateStmt =
    $conn->prepare($updateSql);

if (!$updateStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to prepare category update query.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Dynamic bind_param
|--------------------------------------------------------------------------
*/

$bindParams = [];

$bindParams[] = $bindTypes;

foreach ($bindValues as $key => $value) {
    $bindParams[] = &$bindValues[$key];
}

call_user_func_array(
    [$updateStmt, 'bind_param'],
    $bindParams
);


/*
|--------------------------------------------------------------------------
| Execute Update
|--------------------------------------------------------------------------
*/

if (!$updateStmt->execute()) {

    $errorCode =
        $updateStmt->errno;

    $updateStmt->close();


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
                'Another food category with the same name already exists.'
        ]);

        exit;
    }


    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to update food category.'
    ]);

    exit;
}

$affectedRows =
    $updateStmt->affected_rows;

$updateStmt->close();


/*
|--------------------------------------------------------------------------
| Fetch Updated Category
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

$fetchStmt =
    $conn->prepare($fetchSql);

if (!$fetchStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Category updated, but failed to fetch updated data.'
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
            'Category updated, but failed to fetch updated data.'
    ]);

    exit;
}

$fetchResult =
    $fetchStmt->get_result();

$updatedCategory =
    $fetchResult->fetch_assoc();

$fetchStmt->close();


/*
|--------------------------------------------------------------------------
| Verify Updated Record
|--------------------------------------------------------------------------
*/

if (!$updatedCategory) {
    http_response_code(404);

    echo json_encode([
        'status' => false,
        'message' =>
            'Updated food category could not be found.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Updated Fields
|--------------------------------------------------------------------------
*/

$updatedFields = [];

if ($nameChanged) {
    $updatedFields[] = 'name';
}

if ($descriptionChanged) {
    $updatedFields[] = 'description';
}

if ($statusChanged) {
    $updatedFields[] = 'status';
}


/*
|--------------------------------------------------------------------------
| Success Response
|--------------------------------------------------------------------------
*/

http_response_code(200);

echo json_encode([
    'status' => true,
    'message' =>
        'Food category updated successfully.',
    'data' => [
        'id' => (int) $updatedCategory['id'],
        'name' => $updatedCategory['name'],
        'description' =>
            $updatedCategory['description'],
        'status' => $updatedCategory['status'],
        'created_at' =>
            $updatedCategory['created_at']
    ],
    'updated_fields' => $updatedFields,
    'affected_rows' => $affectedRows,
    'updated_by' => [
        'id' => (int) $user['sub'],
        'role' => $user['role']
    ]
]);

exit;