<?php

header("Content-Type: application/json; charset=utf-8");
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
| Allowed Fields
|--------------------------------------------------------------------------
*/

$allowedFields = [
    'food_item_id',
    'food_category_id',
    'name',
    'description',
    'food_type',
    'status'
];


/*
|--------------------------------------------------------------------------
| Validate Unknown POST Fields
|--------------------------------------------------------------------------
*/

$unknownPostFields = array_diff(
    array_keys($_POST),
    $allowedFields
);

if (!empty($unknownPostFields)) {
    sendResponse(
        422,
        'Unknown field(s) provided.',
        [
            'unknown_fields' => array_values($unknownPostFields)
        ]
    );
}


/*
|--------------------------------------------------------------------------
| Validate Unknown File Fields
|--------------------------------------------------------------------------
*/

$allowedFileFields = [
    'image'
];

$unknownFileFields = array_diff(
    array_keys($_FILES),
    $allowedFileFields
);

if (!empty($unknownFileFields)) {
    sendResponse(
        422,
        'Unknown file field(s) provided.',
        [
            'unknown_file_fields' => array_values($unknownFileFields)
        ]
    );
}


/*
|--------------------------------------------------------------------------
| Food Item ID Validation
|--------------------------------------------------------------------------
*/

if (!isset($_POST['food_item_id'])) {
    sendResponse(
        422,
        'Food item ID is required.'
    );
}

$foodItemIdValue = trim((string) $_POST['food_item_id']);

if ($foodItemIdValue === '') {
    sendResponse(
        422,
        'Food item ID cannot be empty.'
    );
}

if (!preg_match('/^\d+$/', $foodItemIdValue)) {
    sendResponse(
        422,
        'Food item ID must be a valid positive integer.'
    );
}

$foodItemId = (int) $foodItemIdValue;

if ($foodItemId <= 0) {
    sendResponse(
        422,
        'Food item ID must be greater than 0.'
    );
}


/*
|--------------------------------------------------------------------------
| Check Food Item Exists
|--------------------------------------------------------------------------
*/

$currentSQL = "
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

$currentStmt = $conn->prepare($currentSQL);

if (!$currentStmt) {
    sendResponse(
        500,
        'Failed to prepare food item query.'
    );
}

$currentStmt->bind_param(
    'i',
    $foodItemId
);

if (!$currentStmt->execute()) {

    $currentStmt->close();

    sendResponse(
        500,
        'Failed to fetch food item.'
    );
}

$currentResult = $currentStmt->get_result();

if (!$currentResult) {

    $currentStmt->close();

    sendResponse(
        500,
        'Failed to process food item.'
    );
}

$currentItem = $currentResult->fetch_assoc();

$currentStmt->close();

if (!$currentItem) {
    sendResponse(
        404,
        'Food item not found.'
    );
}


/*
|--------------------------------------------------------------------------
| At Least One Update Field Required
|--------------------------------------------------------------------------
*/

$hasPostUpdate = count($_POST) > 1;

$hasImageUpdate = isset($_FILES['image']);

if (!$hasPostUpdate && !$hasImageUpdate) {
    sendResponse(
        422,
        'At least one field or image must be provided for update.'
    );
}


/*
|--------------------------------------------------------------------------
| Current Values
|--------------------------------------------------------------------------
*/

$newCategoryId = $currentItem['food_category_id'] !== null
    ? (int) $currentItem['food_category_id']
    : null;

$newName = $currentItem['name'];

$newDescription = $currentItem['description'];

$newFoodType = $currentItem['food_type'];

$newStatus = $currentItem['status'];

$oldImagePath = $currentItem['image_path'];

$updatedFields = [];


/*
|--------------------------------------------------------------------------
| Food Category Validation
|--------------------------------------------------------------------------
*/

if (array_key_exists('food_category_id', $_POST)) {

    $categoryValue = trim(
        (string) $_POST['food_category_id']
    );

    if ($categoryValue === '') {
        sendResponse(
            422,
            'Food category ID cannot be empty.'
        );
    }

    if (!preg_match('/^\d+$/', $categoryValue)) {
        sendResponse(
            422,
            'Food category ID must be a valid positive integer.'
        );
    }

    $newCategoryId = (int) $categoryValue;

    if ($newCategoryId <= 0) {
        sendResponse(
            422,
            'Food category ID must be greater than 0.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Check Category
    |--------------------------------------------------------------------------
    */

    $categorySQL = "
        SELECT
            id,
            name,
            status
        FROM food_categories
        WHERE id = ?
        LIMIT 1
    ";

    $categoryStmt = $conn->prepare($categorySQL);

    if (!$categoryStmt) {
        sendResponse(
            500,
            'Failed to prepare food category validation query.'
        );
    }

    $categoryStmt->bind_param(
        'i',
        $newCategoryId
    );

    if (!$categoryStmt->execute()) {

        $categoryStmt->close();

        sendResponse(
            500,
            'Failed to validate food category.'
        );
    }

    $categoryResult = $categoryStmt->get_result();

    if (!$categoryResult) {

        $categoryStmt->close();

        sendResponse(
            500,
            'Failed to process food category validation.'
        );
    }

    $category = $categoryResult->fetch_assoc();

    $categoryStmt->close();

    if (!$category) {
        sendResponse(
            422,
            'Food category does not exist.'
        );
    }

    if ($category['status'] !== 'active') {
        sendResponse(
            422,
            'Selected food category is inactive.'
        );
    }


    if (
        $currentItem['food_category_id'] === null ||
        (int) $currentItem['food_category_id'] !== $newCategoryId
    ) {
        $updatedFields[] = 'food_category_id';
    }
}


/*
|--------------------------------------------------------------------------
| Food Name Validation
|--------------------------------------------------------------------------
*/

if (array_key_exists('name', $_POST)) {

    if (!is_string($_POST['name'])) {
        sendResponse(
            422,
            'Food item name must be a string.'
        );
    }

    $newName = trim($_POST['name']);

    if ($newName === '') {
        sendResponse(
            422,
            'Food item name cannot be empty.'
        );
    }

    $nameLength = strlen($newName);

    if ($nameLength < 2) {
        sendResponse(
            422,
            'Food item name must contain at least 2 characters.'
        );
    }

    if ($nameLength > 150) {
        sendResponse(
            422,
            'Food item name cannot exceed 150 characters.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Name Format
    |--------------------------------------------------------------------------
    */

    if (
        !preg_match(
            '/^[A-Za-z0-9&().,\/-]+(?: [A-Za-z0-9&().,\/-]+)*$/',
            $newName
        )
    ) {
        sendResponse(
            422,
            'Food item name contains invalid characters or spacing.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Multiple Spaces
    |--------------------------------------------------------------------------
    */

    if (preg_match('/\s{2,}/', $newName)) {
        sendResponse(
            422,
            'Food item name cannot contain multiple consecutive spaces.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Duplicate Name Check
    |--------------------------------------------------------------------------
    */

    $duplicateSQL = "
        SELECT id
        FROM food_items
        WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))
        AND id != ?
        LIMIT 1
    ";

    $duplicateStmt = $conn->prepare($duplicateSQL);

    if (!$duplicateStmt) {
        sendResponse(
            500,
            'Failed to prepare duplicate food item validation.'
        );
    }

    $duplicateStmt->bind_param(
        'si',
        $newName,
        $foodItemId
    );

    if (!$duplicateStmt->execute()) {

        $duplicateStmt->close();

        sendResponse(
            500,
            'Failed to validate duplicate food item.'
        );
    }

    $duplicateResult = $duplicateStmt->get_result();

    if (!$duplicateResult) {

        $duplicateStmt->close();

        sendResponse(
            500,
            'Failed to process duplicate food item validation.'
        );
    }

    if ($duplicateResult->num_rows > 0) {

        $duplicateStmt->close();

        sendResponse(
            409,
            'Another food item with this name already exists.'
        );
    }

    $duplicateStmt->close();


    if ($newName !== $currentItem['name']) {
        $updatedFields[] = 'name';
    }
}


/*
|--------------------------------------------------------------------------
| Description Validation
|--------------------------------------------------------------------------
*/

if (array_key_exists('description', $_POST)) {

    $descriptionValue = $_POST['description'];

    if (!is_string($descriptionValue)) {
        sendResponse(
            422,
            'Description must be a string.'
        );
    }

    $newDescription = trim($descriptionValue);

    if ($newDescription === '') {
        $newDescription = null;
    }

    if (
        $newDescription !== null &&
        strlen($newDescription) > 5000
    ) {
        sendResponse(
            422,
            'Description cannot exceed 5000 characters.'
        );
    }

    if (
        $newDescription !== null &&
        preg_match('/\s{2,}/', $newDescription)
    ) {
        sendResponse(
            422,
            'Description cannot contain multiple consecutive spaces.'
        );
    }


    if ($newDescription !== $currentItem['description']) {
        $updatedFields[] = 'description';
    }
}


/*
|--------------------------------------------------------------------------
| Food Type Validation
|--------------------------------------------------------------------------
*/

if (array_key_exists('food_type', $_POST)) {

    if (!is_string($_POST['food_type'])) {
        sendResponse(
            422,
            'Food type must be a string.'
        );
    }

    $newFoodType = strtolower(
        trim($_POST['food_type'])
    );

    $allowedFoodTypes = [
        'veg',
        'non_veg',
        'egg',
        'vegan',
        'other'
    ];

    if (!in_array($newFoodType, $allowedFoodTypes, true)) {
        sendResponse(
            422,
            'Invalid food type. Allowed values are veg, non_veg, egg, vegan, other.'
        );
    }

    if ($newFoodType !== $currentItem['food_type']) {
        $updatedFields[] = 'food_type';
    }
}


/*
|--------------------------------------------------------------------------
| Status Validation
|--------------------------------------------------------------------------
*/

if (array_key_exists('status', $_POST)) {

    if (!is_string($_POST['status'])) {
        sendResponse(
            422,
            'Status must be a string.'
        );
    }

    $newStatus = strtolower(
        trim($_POST['status'])
    );

    $allowedStatuses = [
        'active',
        'inactive'
    ];

    if (!in_array($newStatus, $allowedStatuses, true)) {
        sendResponse(
            422,
            'Invalid status. Allowed values are active or inactive.'
        );
    }

    if ($newStatus !== $currentItem['status']) {
        $updatedFields[] = 'status';
    }
}


/*
|--------------------------------------------------------------------------
| Image Upload Validation
|--------------------------------------------------------------------------
*/

$imageUploaded = false;

$imageTmpPath = null;
$imageMimeType = null;
$imageExtension = null;

$newImagePath = $oldImagePath;

if (isset($_FILES['image'])) {

    $imageUploaded = true;

    if (!is_array($_FILES['image'])) {
        sendResponse(
            422,
            'Invalid image upload.'
        );
    }

    $imageError = $_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($imageError !== UPLOAD_ERR_OK) {

        switch ($imageError) {

            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $message = 'Image file size is too large.';
                break;

            case UPLOAD_ERR_PARTIAL:
                $message = 'Image upload was incomplete.';
                break;

            case UPLOAD_ERR_NO_FILE:
                $message = 'No image file was uploaded.';
                break;

            default:
                $message = 'Image upload failed.';
                break;
        }

        sendResponse(
            422,
            $message
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Temporary File
    |--------------------------------------------------------------------------
    */

    $imageTmpPath = $_FILES['image']['tmp_name'] ?? '';

    if ($imageTmpPath === '') {
        sendResponse(
            422,
            'Invalid uploaded image.'
        );
    }

    if (!is_uploaded_file($imageTmpPath)) {
        sendResponse(
            422,
            'Invalid image upload source.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | File Size
    |--------------------------------------------------------------------------
    */

    $maxImageSize = 5 * 1024 * 1024;

    $imageSize = $_FILES['image']['size'] ?? 0;

    if (!is_numeric($imageSize) || $imageSize <= 0) {
        sendResponse(
            422,
            'Invalid image file size.'
        );
    }

    if ($imageSize > $maxImageSize) {
        sendResponse(
            422,
            'Image size cannot exceed 5 MB.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Actual Image Validation
    |--------------------------------------------------------------------------
    */

    $imageInfo = @getimagesize($imageTmpPath);

    if ($imageInfo === false) {
        sendResponse(
            422,
            'Uploaded file is not a valid image.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Dimensions
    |--------------------------------------------------------------------------
    */

    $imageWidth = (int) ($imageInfo[0] ?? 0);
    $imageHeight = (int) ($imageInfo[1] ?? 0);

    if ($imageWidth <= 0 || $imageHeight <= 0) {
        sendResponse(
            422,
            'Invalid image dimensions.'
        );
    }

    if ($imageWidth > 10000 || $imageHeight > 10000) {
        sendResponse(
            422,
            'Image dimensions cannot exceed 10000 x 10000 pixels.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | MIME Validation
    |--------------------------------------------------------------------------
    */

    $finfo = new finfo(FILEINFO_MIME_TYPE);

    $imageMimeType = $finfo->file($imageTmpPath);

    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($allowedMimeTypes[$imageMimeType])) {
        sendResponse(
            422,
            'Invalid image format. Allowed formats are JPG, JPEG, PNG and WEBP.'
        );
    }

    $imageExtension = $allowedMimeTypes[$imageMimeType];
}


/*
|--------------------------------------------------------------------------
| Check Whether Any Actual Database Change Exists
|--------------------------------------------------------------------------
*/

$databaseFieldsChanged = !empty($updatedFields);


/*
|--------------------------------------------------------------------------
| If Nothing Changed and No Image
|--------------------------------------------------------------------------
*/

if (!$databaseFieldsChanged && !$imageUploaded) {

    sendResponse(
        200,
        'No changes were made.',
        [
            'food_item_id' => $foodItemId,
            'updated_fields' => []
        ]
    );
}


/*
|--------------------------------------------------------------------------
| Prepare New Image Path
|--------------------------------------------------------------------------
*/

$physicalImagePath = null;

$oldPhysicalImagePath = null;

if ($imageUploaded) {

    $projectRoot = dirname(__DIR__, 2);

    $uploadDirectory =
        $projectRoot
        . '/uploads/food/'
        . $foodItemId
        . '/image/';


    /*
    |--------------------------------------------------------------------------
    | Create Directory
    |--------------------------------------------------------------------------
    */

    if (!is_dir($uploadDirectory)) {

        if (!mkdir($uploadDirectory, 0755, true)) {
            sendResponse(
                500,
                'Failed to create image upload directory.'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Generate Random Filename
    |--------------------------------------------------------------------------
    */

    try {

        $randomFileName =
            bin2hex(random_bytes(16))
            . '.'
            . $imageExtension;

    } catch (Throwable $e) {

        sendResponse(
            500,
            'Failed to generate image filename.'
        );
    }


    $physicalImagePath =
        $uploadDirectory . $randomFileName;

    $newImagePath =
        'uploads/food/'
        . $foodItemId
        . '/image/'
        . $randomFileName;


    /*
    |--------------------------------------------------------------------------
    | Move New Image
    |--------------------------------------------------------------------------
    */

    if (
        !move_uploaded_file(
            $imageTmpPath,
            $physicalImagePath
        )
    ) {
        sendResponse(
            500,
            'Failed to save uploaded image.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Resolve Old Image Physical Path
    |--------------------------------------------------------------------------
    */

    if (
        $oldImagePath !== null &&
        $oldImagePath !== ''
    ) {

        $oldImagePath = ltrim(
            $oldImagePath,
            '/\\'
        );

        if (
            strpos($oldImagePath, '..') !== false ||
            strpos($oldImagePath, "\0") !== false
        ) {

            if (file_exists($physicalImagePath)) {
                @unlink($physicalImagePath);
            }

            sendResponse(
                500,
                'Existing image path is invalid.'
            );
        }

        $oldPhysicalImagePath =
            $projectRoot . '/' . $oldImagePath;
    }
}


/*
|--------------------------------------------------------------------------
| Build Dynamic Update Query
|--------------------------------------------------------------------------
*/

$updateParts = [];

$updateParams = [];

$updateTypes = '';


if (
    array_key_exists('food_category_id', $_POST)
) {

    $updateParts[] = 'food_category_id = ?';

    $updateParams[] = $newCategoryId;

    $updateTypes .= 'i';
}


if (
    array_key_exists('name', $_POST)
) {

    $updateParts[] = 'name = ?';

    $updateParams[] = $newName;

    $updateTypes .= 's';
}


if (
    array_key_exists('description', $_POST)
) {

    $updateParts[] = 'description = ?';

    $updateParams[] = $newDescription;

    $updateTypes .= 's';
}


if ($imageUploaded) {

    $updateParts[] = 'image_path = ?';

    $updateParams[] = $newImagePath;

    $updateTypes .= 's';

    $updatedFields[] = 'image_path';
}


if (
    array_key_exists('food_type', $_POST)
) {

    $updateParts[] = 'food_type = ?';

    $updateParams[] = $newFoodType;

    $updateTypes .= 's';
}


if (
    array_key_exists('status', $_POST)
) {

    $updateParts[] = 'status = ?';

    $updateParams[] = $newStatus;

    $updateTypes .= 's';
}


/*
|--------------------------------------------------------------------------
| Add ID Parameter
|--------------------------------------------------------------------------
*/

$updateParams[] = $foodItemId;

$updateTypes .= 'i';


/*
|--------------------------------------------------------------------------
| Update Database
|--------------------------------------------------------------------------
*/

if (!empty($updateParts)) {

    $updateSQL = "
        UPDATE food_items
        SET " . implode(', ', $updateParts) . "
        WHERE id = ?
        LIMIT 1
    ";

    $updateStmt = $conn->prepare($updateSQL);

    if (!$updateStmt) {

        if (
            $imageUploaded &&
            $physicalImagePath !== null &&
            file_exists($physicalImagePath)
        ) {
            @unlink($physicalImagePath);
        }

        sendResponse(
            500,
            'Failed to prepare food item update query.'
        );
    }


    $updateStmt->bind_param(
        $updateTypes,
        ...$updateParams
    );


    if (!$updateStmt->execute()) {

        $errorCode = $updateStmt->errno;

        $updateStmt->close();

        if (
            $imageUploaded &&
            $physicalImagePath !== null &&
            file_exists($physicalImagePath)
        ) {
            @unlink($physicalImagePath);
        }

        if ($errorCode === 1062) {
            sendResponse(
                409,
                'Another food item with this name already exists.'
            );
        }

        if ($errorCode === 1452) {
            sendResponse(
                422,
                'Selected food category does not exist.'
            );
        }

        sendResponse(
            500,
            'Failed to update food item.'
        );
    }

    $affectedRows = $updateStmt->affected_rows;

    $updateStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Delete Old Image After Successful DB Update
    |--------------------------------------------------------------------------
    */

    if (
        $imageUploaded &&
        $oldPhysicalImagePath !== null &&
        file_exists($oldPhysicalImagePath)
    ) {

        @unlink($oldPhysicalImagePath);
    }
}


/*
|--------------------------------------------------------------------------
| Fetch Updated Food Item
|--------------------------------------------------------------------------
*/

$fetchSQL = "
    SELECT
        fi.id,
        fi.food_category_id,
        fc.name AS category_name,
        fi.name,
        fi.description,
        fi.image_path,
        fi.food_type,
        fi.status,
        fi.created_at,
        fi.updated_at
    FROM food_items fi
    LEFT JOIN food_categories fc
        ON fc.id = fi.food_category_id
    WHERE fi.id = ?
    LIMIT 1
";

$fetchStmt = $conn->prepare($fetchSQL);

if (!$fetchStmt) {
    sendResponse(
        500,
        'Food item was updated, but failed to prepare response query.'
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
        'Food item was updated, but failed to fetch updated item.'
    );
}

$fetchResult = $fetchStmt->get_result();

if (!$fetchResult) {

    $fetchStmt->close();

    sendResponse(
        500,
        'Food item was updated, but failed to process updated item.'
    );
}

$updatedItem = $fetchResult->fetch_assoc();

$fetchStmt->close();


/*
|--------------------------------------------------------------------------
| Remove Duplicate Updated Fields
|--------------------------------------------------------------------------
*/

$updatedFields = array_values(
    array_unique($updatedFields)
);


/*
|--------------------------------------------------------------------------
| Final Response
|--------------------------------------------------------------------------
*/

sendResponse(
    200,
    'Food item updated successfully.',
    [
        'food_item' => [
            'id' => (int) $updatedItem['id'],

            'food_category_id' =>
                $updatedItem['food_category_id'] !== null
                    ? (int) $updatedItem['food_category_id']
                    : null,

            'category_name' =>
                $updatedItem['category_name'],

            'name' =>
                $updatedItem['name'],

            'description' =>
                $updatedItem['description'],

            'image_path' =>
                $updatedItem['image_path'],

            'food_type' =>
                $updatedItem['food_type'],

            'status' =>
                $updatedItem['status'],

            'created_at' =>
                $updatedItem['created_at'],

            'updated_at' =>
                $updatedItem['updated_at']
        ],

        'updated_fields' =>
            $updatedFields,

        'affected_rows' =>
            $affectedRows ?? 0,

        'updated_by' => [
            'id' => (int) $user['sub'],
            'role' => $user['role']
        ]
    ]
);