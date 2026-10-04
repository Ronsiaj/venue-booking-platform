<?php

header("Content-Type: application/json; charset=utf-8");
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
| Validate POST Fields
|--------------------------------------------------------------------------
|
| multipart/form-data is expected.
|
|--------------------------------------------------------------------------
*/

$allowedFields = [
    'food_category_id',
    'name',
    'description',
    'food_type',
    'status'
];

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
| Validate Uploaded Files
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
| Required Name Validation
|--------------------------------------------------------------------------
*/

if (!isset($_POST['name'])) {
    sendResponse(
        422,
        'Food item name is required.'
    );
}

if (!is_string($_POST['name'])) {
    sendResponse(
        422,
        'Food item name must be a string.'
    );
}

$name = trim($_POST['name']);

if ($name === '') {
    sendResponse(
        422,
        'Food item name cannot be empty.'
    );
}

$nameLength = strlen($name);

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
| Food Name Format Validation
|--------------------------------------------------------------------------
*/

if (
    !preg_match(
        '/^[A-Za-z0-9&().,\/-]+(?: [A-Za-z0-9&().,\/-]+)*$/',
        $name
    )
) {
    sendResponse(
        422,
        'Food item name contains invalid characters or spacing.'
    );
}


/*
|--------------------------------------------------------------------------
| Prevent Multiple Consecutive Spaces
|--------------------------------------------------------------------------
*/

if (preg_match('/\s{2,}/', $name)) {
    sendResponse(
        422,
        'Food item name cannot contain multiple consecutive spaces.'
    );
}


/*
|--------------------------------------------------------------------------
| Description Validation
|--------------------------------------------------------------------------
*/

$description = null;

if (isset($_POST['description'])) {

    if (!is_string($_POST['description'])) {
        sendResponse(
            422,
            'Description must be a string.'
        );
    }

    $description = trim($_POST['description']);

    if ($description === '') {
        $description = null;
    }

    if (
        $description !== null &&
        strlen($description) > 5000
    ) {
        sendResponse(
            422,
            'Description cannot exceed 5000 characters.'
        );
    }

    if (
        $description !== null &&
        preg_match('/\s{2,}/', $description)
    ) {
        sendResponse(
            422,
            'Description cannot contain multiple consecutive spaces.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Food Type Validation
|--------------------------------------------------------------------------
*/

$foodType = 'veg';

if (isset($_POST['food_type'])) {

    if (!is_string($_POST['food_type'])) {
        sendResponse(
            422,
            'Food type must be a string.'
        );
    }

    $foodType = strtolower(trim($_POST['food_type']));

    $allowedFoodTypes = [
        'veg',
        'non_veg',
        'egg',
        'vegan',
        'other'
    ];

    if (!in_array($foodType, $allowedFoodTypes, true)) {
        sendResponse(
            422,
            'Invalid food type. Allowed values are veg, non_veg, egg, vegan, other.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Status Validation
|--------------------------------------------------------------------------
*/

$status = 'active';

if (isset($_POST['status'])) {

    if (!is_string($_POST['status'])) {
        sendResponse(
            422,
            'Status must be a string.'
        );
    }

    $status = strtolower(trim($_POST['status']));

    $allowedStatuses = [
        'active',
        'inactive'
    ];

    if (!in_array($status, $allowedStatuses, true)) {
        sendResponse(
            422,
            'Invalid status. Allowed values are active or inactive.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Food Category Validation
|--------------------------------------------------------------------------
*/

$foodCategoryId = null;

if (isset($_POST['food_category_id'])) {

    $categoryValue = trim($_POST['food_category_id']);

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

    $foodCategoryId = (int) $categoryValue;

    if ($foodCategoryId <= 0) {
        sendResponse(
            422,
            'Food category ID must be greater than 0.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Validate Food Category Exists
|--------------------------------------------------------------------------
*/

if ($foodCategoryId !== null) {

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
        $foodCategoryId
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
}


/*
|--------------------------------------------------------------------------
| Duplicate Food Item Check
|--------------------------------------------------------------------------
*/

$duplicateSQL = "
    SELECT id
    FROM food_items
    WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))
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
    's',
    $name
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
        'A food item with this name already exists.'
    );
}

$duplicateStmt->close();


/*
|--------------------------------------------------------------------------
| Image Upload Validation
|--------------------------------------------------------------------------
*/

$imageUploaded = false;
$imageTmpPath = null;
$imageOriginalName = null;
$imageMimeType = null;
$imageExtension = null;


/*
|--------------------------------------------------------------------------
| Image Is Optional
|--------------------------------------------------------------------------
*/

if (isset($_FILES['image'])) {

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
    | File Size Validation
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
    | Original Filename
    |--------------------------------------------------------------------------
    */

    $imageOriginalName = $_FILES['image']['name'] ?? '';

    if (!is_string($imageOriginalName)) {
        sendResponse(
            422,
            'Invalid image filename.'
        );
    }

    $imageOriginalName = trim($imageOriginalName);

    if ($imageOriginalName === '') {
        sendResponse(
            422,
            'Image filename is required.'
        );
    }

    if (strlen($imageOriginalName) > 255) {
        sendResponse(
            422,
            'Image filename is too long.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Get Actual Image Information
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
    | Image Dimensions
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
    | MIME Type Validation
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

    $imageUploaded = true;
}


/*
|--------------------------------------------------------------------------
| Insert Food Item First
|--------------------------------------------------------------------------
|
| We need the generated food item ID to create:
|
| uploads/food/{food_item_id}/image/
|
|--------------------------------------------------------------------------
*/

$imagePath = null;

$insertSQL = "
    INSERT INTO food_items
    (
        food_category_id,
        name,
        description,
        image_path,
        food_type,
        status
    )
    VALUES (?, ?, ?, ?, ?, ?)
";

$insertStmt = $conn->prepare($insertSQL);

if (!$insertStmt) {
    sendResponse(
        500,
        'Failed to prepare food item creation query.'
    );
}

$insertStmt->bind_param(
    'isssss',
    $foodCategoryId,
    $name,
    $description,
    $imagePath,
    $foodType,
    $status
);

if (!$insertStmt->execute()) {

    $errorCode = $insertStmt->errno;

    $insertStmt->close();

    if ($errorCode === 1062) {
        sendResponse(
            409,
            'A food item with this name already exists.'
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
        'Failed to create food item.'
    );
}

$foodItemId = (int) $insertStmt->insert_id;

$insertStmt->close();

if ($foodItemId <= 0) {
    sendResponse(
        500,
        'Food item was created but its ID could not be retrieved.'
    );
}


/*
|--------------------------------------------------------------------------
| Process Image After Food Item Creation
|--------------------------------------------------------------------------
*/

if ($imageUploaded === true) {

    /*
    |--------------------------------------------------------------------------
    | Upload Directory
    |--------------------------------------------------------------------------
    */

    $projectRoot = dirname(__DIR__, 2);

    $uploadDirectory = $projectRoot
        . '/uploads/food/'
        . $foodItemId
        . '/image/';


    /*
    |--------------------------------------------------------------------------
    | Create Directory
    |--------------------------------------------------------------------------
    */

    if (!is_dir($uploadDirectory)) {

        if (
            !mkdir(
                $uploadDirectory,
                0755,
                true
            )
        ) {

            /*
            |--------------------------------------------------------------------------
            | Remove Created Food Item
            |--------------------------------------------------------------------------
            */

            $deleteStmt = $conn->prepare(
                "DELETE FROM food_items WHERE id = ?"
            );

            if ($deleteStmt) {

                $deleteStmt->bind_param(
                    'i',
                    $foodItemId
                );

                $deleteStmt->execute();
                $deleteStmt->close();
            }

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

        $deleteStmt = $conn->prepare(
            "DELETE FROM food_items WHERE id = ?"
        );

        if ($deleteStmt) {

            $deleteStmt->bind_param(
                'i',
                $foodItemId
            );

            $deleteStmt->execute();
            $deleteStmt->close();
        }

        sendResponse(
            500,
            'Failed to generate image filename.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Physical File Path
    |--------------------------------------------------------------------------
    */

    $physicalImagePath =
        $uploadDirectory . $randomFileName;


    /*
    |--------------------------------------------------------------------------
    | Database Image Path
    |--------------------------------------------------------------------------
    */

    $imagePath =
        'uploads/food/'
        . $foodItemId
        . '/image/'
        . $randomFileName;


    /*
    |--------------------------------------------------------------------------
    | Move Uploaded File
    |--------------------------------------------------------------------------
    */

    if (
        !move_uploaded_file(
            $imageTmpPath,
            $physicalImagePath
        )
    ) {

        $deleteStmt = $conn->prepare(
            "DELETE FROM food_items WHERE id = ?"
        );

        if ($deleteStmt) {

            $deleteStmt->bind_param(
                'i',
                $foodItemId
            );

            $deleteStmt->execute();
            $deleteStmt->close();
        }

        sendResponse(
            500,
            'Failed to save uploaded image.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Update Image Path
    |--------------------------------------------------------------------------
    */

    $updateImageSQL = "
        UPDATE food_items
        SET image_path = ?
        WHERE id = ?
        LIMIT 1
    ";

    $updateImageStmt = $conn->prepare($updateImageSQL);

    if (!$updateImageStmt) {

        if (file_exists($physicalImagePath)) {
            @unlink($physicalImagePath);
        }

        $deleteStmt = $conn->prepare(
            "DELETE FROM food_items WHERE id = ?"
        );

        if ($deleteStmt) {

            $deleteStmt->bind_param(
                'i',
                $foodItemId
            );

            $deleteStmt->execute();
            $deleteStmt->close();
        }

        sendResponse(
            500,
            'Image was uploaded but failed to update database.'
        );
    }

    $updateImageStmt->bind_param(
        'si',
        $imagePath,
        $foodItemId
    );

    if (!$updateImageStmt->execute()) {

        $updateImageStmt->close();

        if (file_exists($physicalImagePath)) {
            @unlink($physicalImagePath);
        }

        $deleteStmt = $conn->prepare(
            "DELETE FROM food_items WHERE id = ?"
        );

        if ($deleteStmt) {

            $deleteStmt->bind_param(
                'i',
                $foodItemId
            );

            $deleteStmt->execute();
            $deleteStmt->close();
        }

        sendResponse(
            500,
            'Image was uploaded but failed to save image path.'
        );
    }

    $updateImageStmt->close();
}


/*
|--------------------------------------------------------------------------
| Fetch Created Food Item
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
        'Food item was created, but failed to prepare response query.'
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
        'Food item was created, but failed to fetch created item.'
    );
}

$fetchResult = $fetchStmt->get_result();

if (!$fetchResult) {

    $fetchStmt->close();

    sendResponse(
        500,
        'Food item was created, but failed to process created item.'
    );
}

$createdItem = $fetchResult->fetch_assoc();

$fetchStmt->close();


/*
|--------------------------------------------------------------------------
| Final Response
|--------------------------------------------------------------------------
*/

sendResponse(
    201,
    'Food item created successfully.',
    [
        'food_item' => [
            'id' => (int) $createdItem['id'],

            'food_category_id' =>
                $createdItem['food_category_id'] !== null
                    ? (int) $createdItem['food_category_id']
                    : null,

            'category_name' =>
                $createdItem['category_name'],

            'name' =>
                $createdItem['name'],

            'description' =>
                $createdItem['description'],

            'image_path' =>
                $createdItem['image_path'],

            'food_type' =>
                $createdItem['food_type'],

            'status' =>
                $createdItem['status'],

            'created_at' =>
                $createdItem['created_at'],

            'updated_at' =>
                $createdItem['updated_at']
        ],

        'created_by' => [
            'id' => (int) $user['sub'],
            'role' => $user['role']
        ]
    ]
);