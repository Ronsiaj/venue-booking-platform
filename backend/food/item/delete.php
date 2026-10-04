<?php

header("Content-Type: application/json; charset=utf-8");
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

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/middleware.php';

$user = requireAdminOrStaff();


/*
|--------------------------------------------------------------------------
| Helper Function
|--------------------------------------------------------------------------
*/

function sendResponse($statusCode, $message, $data = null)
{
    http_response_code($statusCode);

    $response = [
        'status' => $statusCode >= 200 && $statusCode < 300,
        'message' => $message
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode(
        $response,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Read Request Body
|--------------------------------------------------------------------------
|
| Supports:
| - application/json
| - form-data
|
|--------------------------------------------------------------------------
*/

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

$data = [];


/*
|--------------------------------------------------------------------------
| JSON Request
|--------------------------------------------------------------------------
*/

if (
    stripos(
        $contentType,
        'application/json'
    ) !== false
) {

    $rawInput = file_get_contents('php://input');

    if (
        $rawInput === false ||
        trim($rawInput) === ''
    ) {
        sendResponse(
            400,
            'Request body is required.'
        );
    }

    $data = json_decode(
        $rawInput,
        true
    );

    if (
        json_last_error() !== JSON_ERROR_NONE ||
        !is_array($data)
    ) {
        sendResponse(
            400,
            'Invalid JSON request body.'
        );
    }

} else {

    /*
    |--------------------------------------------------------------------------
    | Form Data / URL Encoded
    |--------------------------------------------------------------------------
    */

    $data = $_POST;

}


/*
|--------------------------------------------------------------------------
| Allowed Fields
|--------------------------------------------------------------------------
*/

$allowedFields = [
    'food_item_id'
];

$unknownFields = array_diff(
    array_keys($data),
    $allowedFields
);

if (!empty($unknownFields)) {

    sendResponse(
        422,
        'Unknown field(s) provided.',
        [
            'unknown_fields' => array_values(
                $unknownFields
            )
        ]
    );
}


/*
|--------------------------------------------------------------------------
| Food Item ID Required
|--------------------------------------------------------------------------
*/

if (!array_key_exists(
    'food_item_id',
    $data
)) {
    sendResponse(
        422,
        'Food item ID is required.'
    );
}


/*
|--------------------------------------------------------------------------
| Food Item ID Validation
|--------------------------------------------------------------------------
*/

$foodItemIdValue = $data['food_item_id'];

if (
    is_int($foodItemIdValue)
) {

    $foodItemId = $foodItemIdValue;

} elseif (
    is_string($foodItemIdValue)
) {

    $foodItemIdValue = trim(
        $foodItemIdValue
    );

    if ($foodItemIdValue === '') {
        sendResponse(
            422,
            'Food item ID cannot be empty.'
        );
    }

    if (
        !preg_match(
            '/^\d+$/',
            $foodItemIdValue
        )
    ) {
        sendResponse(
            422,
            'Food item ID must be a valid positive integer.'
        );
    }

    $foodItemId = (int) $foodItemIdValue;

} else {

    sendResponse(
        422,
        'Food item ID must be a valid positive integer.'
    );
}


if ($foodItemId <= 0) {
    sendResponse(
        422,
        'Food item ID must be greater than 0.'
    );
}


/*
|--------------------------------------------------------------------------
| Fetch Food Item
|--------------------------------------------------------------------------
*/

$fetchSQL = "
    SELECT
        id,
        food_category_id,
        name,
        description,
        image_path,
        food_type,
        status,
        created_at,
        updated_at
    FROM food_items
    WHERE id = ?
    LIMIT 1
";

$fetchStmt = $conn->prepare(
    $fetchSQL
);

if (!$fetchStmt) {
    sendResponse(
        500,
        'Failed to prepare food item query.'
    );
}

$fetchStmt->bind_param(
    'i',
    $foodItemId
);

if (!$fetchStmt->execute()) {

    $fetchStmt->close();

    sendResponse(
        500,
        'Failed to fetch food item.'
    );
}

$fetchResult = $fetchStmt->get_result();

if (!$fetchResult) {

    $fetchStmt->close();

    sendResponse(
        500,
        'Failed to process food item.'
    );
}

$foodItem = $fetchResult->fetch_assoc();

$fetchStmt->close();

if (!$foodItem) {
    sendResponse(
        404,
        'Food item not found.'
    );
}


/*
|--------------------------------------------------------------------------
| Check Dependency Tables
|--------------------------------------------------------------------------
|
| food_items is referenced by:
|
| 1. booking_food_items
| 2. room_food_package_items
| 3. dining_package_items
| 4. catering_package_items
| 5. dining_lead_items
| 6. catering_lead_items
|
|--------------------------------------------------------------------------
*/

$dependencyTables = [
    'booking_food_items' => 'Booking food items',
    'room_food_package_items' => 'Room food package items',
    'dining_package_items' => 'Dining package items',
    'catering_package_items' => 'Catering package items',
    'dining_lead_items' => 'Dining lead items',
    'catering_lead_items' => 'Catering lead items'
];

$dependencies = [];

$totalDependencies = 0;


/*
|--------------------------------------------------------------------------
| Check Every Dependency
|--------------------------------------------------------------------------
*/

foreach (
    $dependencyTables as $tableName => $label
) {

    $dependencySQL = "
        SELECT COUNT(*) AS total
        FROM `$tableName`
        WHERE food_item_id = ?
    ";

    $dependencyStmt = $conn->prepare(
        $dependencySQL
    );

    if (!$dependencyStmt) {
        sendResponse(
            500,
            'Failed to check food item dependencies.'
        );
    }

    $dependencyStmt->bind_param(
        'i',
        $foodItemId
    );

    if (!$dependencyStmt->execute()) {

        $dependencyStmt->close();

        sendResponse(
            500,
            'Failed to check food item dependencies.'
        );
    }

    $dependencyResult =
        $dependencyStmt->get_result();

    if (!$dependencyResult) {

        $dependencyStmt->close();

        sendResponse(
            500,
            'Failed to process food item dependencies.'
        );
    }

    $dependencyRow =
        $dependencyResult->fetch_assoc();

    $dependencyStmt->close();

    $count = (int) (
        $dependencyRow['total'] ?? 0
    );

    if ($count > 0) {

        $dependencies[] = [
            'table' => $tableName,
            'description' => $label,
            'count' => $count
        ];

        $totalDependencies += $count;
    }
}


/*
|--------------------------------------------------------------------------
| Prevent Delete If Referenced
|--------------------------------------------------------------------------
*/

if ($totalDependencies > 0) {

    sendResponse(
        409,
        'Food item cannot be deleted because it is already being used.',
        [
            'food_item' => [
                'id' => (int) $foodItem['id'],
                'name' => $foodItem['name'],
                'status' => $foodItem['status']
            ],

            'total_references' =>
                $totalDependencies,

            'dependencies' =>
                $dependencies,

            'suggestion' =>
                'Set the food item status to inactive instead of deleting it.'
        ]
    );
}


/*
|--------------------------------------------------------------------------
| Prepare Existing Image Path
|--------------------------------------------------------------------------
*/

$physicalImagePath = null;

$imagePath = $foodItem['image_path'];

if (
    $imagePath !== null &&
    $imagePath !== ''
) {

    $imagePath = ltrim(
        $imagePath,
        '/\\'
    );


    /*
    |--------------------------------------------------------------------------
    | Prevent Path Traversal
    |--------------------------------------------------------------------------
    */

    if (
        strpos(
            $imagePath,
            '..'
        ) !== false ||
        strpos(
            $imagePath,
            "\0"
        ) !== false
    ) {

        sendResponse(
            500,
            'Food item contains an invalid image path.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Make Sure Image Belongs To Food Upload Directory
    |--------------------------------------------------------------------------
    */

    $expectedPrefix =
        'uploads/food/'
        . $foodItemId
        . '/image/';

    if (
        strpos(
            $imagePath,
            $expectedPrefix
        ) !== 0
    ) {

        sendResponse(
            500,
            'Food item image path is invalid.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Physical Path
    |--------------------------------------------------------------------------
    */

    $projectRoot =
        dirname(__DIR__, 2);

    $physicalImagePath =
        $projectRoot
        . '/'
        . $imagePath;
}


/*
|--------------------------------------------------------------------------
| Start Transaction
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();

try {

    /*
    |--------------------------------------------------------------------------
    | Delete Food Item
    |--------------------------------------------------------------------------
    */

    $deleteSQL = "
        DELETE FROM food_items
        WHERE id = ?
        LIMIT 1
    ";

    $deleteStmt = $conn->prepare(
        $deleteSQL
    );

    if (!$deleteStmt) {
        throw new Exception(
            'Failed to prepare food item delete query.'
        );
    }

    $deleteStmt->bind_param(
        'i',
        $foodItemId
    );

    if (!$deleteStmt->execute()) {

        $errorCode =
            $deleteStmt->errno;

        $errorMessage =
            $deleteStmt->error;

        $deleteStmt->close();

        if ($errorCode === 1451) {
            throw new Exception(
                'Food item is still being used by another record.'
            );
        }

        throw new Exception(
            'Failed to delete food item.'
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

    $verifySQL = "
        SELECT id
        FROM food_items
        WHERE id = ?
        LIMIT 1
    ";

    $verifyStmt = $conn->prepare(
        $verifySQL
    );

    if (!$verifyStmt) {
        throw new Exception(
            'Failed to prepare delete verification query.'
        );
    }

    $verifyStmt->bind_param(
        'i',
        $foodItemId
    );

    if (!$verifyStmt->execute()) {

        $verifyStmt->close();

        throw new Exception(
            'Failed to verify food item deletion.'
        );
    }

    $verifyResult =
        $verifyStmt->get_result();

    if (!$verifyResult) {

        $verifyStmt->close();

        throw new Exception(
            'Failed to process delete verification.'
        );
    }

    $stillExists =
        $verifyResult->num_rows > 0;

    $verifyStmt->close();

    if ($stillExists) {
        throw new Exception(
            'Food item could not be deleted.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Commit Transaction
    |--------------------------------------------------------------------------
    */

    $conn->commit();

} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | Rollback
    |--------------------------------------------------------------------------
    */

    $conn->rollback();

    sendResponse(
        409,
        $e->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| Delete Physical Image
|--------------------------------------------------------------------------
|
| Database deletion is already committed.
| Image file is removed after successful DB delete.
|
|--------------------------------------------------------------------------
*/

$imageDeleteWarning = null;

if (
    $physicalImagePath !== null &&
    file_exists($physicalImagePath)
) {

    if (!@unlink($physicalImagePath)) {

        $imageDeleteWarning =
            'Food item was deleted successfully, but the physical image file could not be deleted.';
    }
}


/*
|--------------------------------------------------------------------------
| Delete Empty Image Directory
|--------------------------------------------------------------------------
*/

if (
    $physicalImagePath !== null &&
    $imageDeleteWarning === null
) {

    $imageDirectory =
        dirname(
            $physicalImagePath
        );

    if (
        is_dir($imageDirectory)
    ) {

        $files = scandir(
            $imageDirectory
        );

        if (
            is_array($files) &&
            count($files) <= 2
        ) {
            @rmdir(
                $imageDirectory
            );
        }
    }
}


/*
|--------------------------------------------------------------------------
| Final Response
|--------------------------------------------------------------------------
*/

$responseData = [
    'deleted_food_item' => [
        'id' => (int) $foodItem['id'],
        'food_category_id' =>
            $foodItem['food_category_id'] !== null
                ? (int) $foodItem['food_category_id']
                : null,
        'name' => $foodItem['name'],
        'food_type' => $foodItem['food_type'],
        'status' => $foodItem['status'],
        'image_path' => $foodItem['image_path']
    ],

    'affected_rows' =>
        $affectedRows,

    'total_references' => 0,

    'deleted_by' => [
        'id' => (int) $user['sub'],
        'role' => $user['role']
    ]
];

if ($imageDeleteWarning !== null) {
    $responseData['warning'] =
        $imageDeleteWarning;
}


sendResponse(
    200,
    'Food item deleted successfully.',
    $responseData
);