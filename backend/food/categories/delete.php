<?php

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: DELETE, POST, OPTIONS");
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
    'category_id'
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
| category_id Required
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


/*
|--------------------------------------------------------------------------
| category_id Validation
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Convert To Integer
|--------------------------------------------------------------------------
*/

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
| Fetch Category
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
        'message' => 'Failed to fetch food category.'
    ]);

    exit;
}

$result = $selectStmt->get_result();

$category = $result->fetch_assoc();

$selectStmt->close();


/*
|--------------------------------------------------------------------------
| Category Not Found
|--------------------------------------------------------------------------
*/

if (!$category) {
    http_response_code(404);

    echo json_encode([
        'status' => false,
        'message' => 'Food category not found.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Check Food Item Dependencies
|--------------------------------------------------------------------------
|
| food_items.food_category_id references
| food_categories.id with ON DELETE SET NULL.
|
*/

$dependencySql = "
    SELECT
        COUNT(*) AS total_food_items
    FROM food_items
    WHERE food_category_id = ?
";

$dependencyStmt = $conn->prepare($dependencySql);

if (!$dependencyStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to prepare dependency validation query.'
    ]);

    exit;
}

$dependencyStmt->bind_param(
    'i',
    $categoryId
);

if (!$dependencyStmt->execute()) {

    $dependencyStmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to check category dependencies.'
    ]);

    exit;
}

$dependencyResult =
    $dependencyStmt->get_result();

$dependencyData =
    $dependencyResult->fetch_assoc();

$dependencyStmt->close();

$totalFoodItems =
    (int) ($dependencyData['total_food_items'] ?? 0);


/*
|--------------------------------------------------------------------------
| Transaction Start
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();

try {

    /*
    |--------------------------------------------------------------------------
    | Delete Category
    |--------------------------------------------------------------------------
    */

    $deleteSql = "
        DELETE FROM food_categories
        WHERE id = ?
        LIMIT 1
    ";

    $deleteStmt =
        $conn->prepare($deleteSql);

    if (!$deleteStmt) {
        throw new Exception(
            'Failed to prepare category delete query.'
        );
    }

    $deleteStmt->bind_param(
        'i',
        $categoryId
    );

    if (!$deleteStmt->execute()) {

        $errorCode =
            $deleteStmt->errno;

        $deleteStmt->close();

        /*
        |--------------------------------------------------------------------------
        | Foreign Key Error
        |--------------------------------------------------------------------------
        */

        if ($errorCode === 1451) {
            throw new Exception(
                'This food category cannot be deleted because it is being used by another record.'
            );
        }

        throw new Exception(
            'Failed to delete food category.'
        );
    }

    $affectedRows =
        $deleteStmt->affected_rows;

    $deleteStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Verify Category Deletion
    |--------------------------------------------------------------------------
    */

    $verifySql = "
        SELECT id
        FROM food_categories
        WHERE id = ?
        LIMIT 1
    ";

    $verifyStmt =
        $conn->prepare($verifySql);

    if (!$verifyStmt) {
        throw new Exception(
            'Failed to prepare delete verification query.'
        );
    }

    $verifyStmt->bind_param(
        'i',
        $categoryId
    );

    if (!$verifyStmt->execute()) {

        $verifyStmt->close();

        throw new Exception(
            'Failed to verify category deletion.'
        );
    }

    $verifyResult =
        $verifyStmt->get_result();

    $remainingCategory =
        $verifyResult->fetch_assoc();

    $verifyStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Delete Verification Failed
    |--------------------------------------------------------------------------
    */

    if ($remainingCategory) {
        throw new Exception(
            'Food category deletion could not be verified.'
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
        'message' =>
            'Food category deleted successfully.',
        'data' => [
            'deleted_category' => [
                'id' => (int) $category['id'],
                'name' => $category['name'],
                'description' => $category['description'],
                'status' => $category['status'],
                'created_at' => $category['created_at']
            ],

            /*
            |--------------------------------------------------------------------------
            | Food Items Effect
            |--------------------------------------------------------------------------
            |
            | Because FK uses ON DELETE SET NULL,
            | existing food items remain.
            |
            */

            'food_items_affected' => $totalFoodItems,
            'food_items_category_result' =>
                $totalFoodItems > 0
                    ? 'food_category_id was set to NULL for related food items.'
                    : 'No food items were assigned to this category.',

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
        'message' => 'Failed to delete food category.'
    ]);

    exit;
}