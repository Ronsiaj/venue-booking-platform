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

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/middleware.php';


// --------------------------------------------------
// AUTHENTICATION
// --------------------------------------------------

$user = requireAdminOrStaff();


// --------------------------------------------------
// REQUEST TYPE VALIDATION
// --------------------------------------------------

$contentType = $_SERVER['CONTENT_TYPE']
    ?? $_SERVER['HTTP_CONTENT_TYPE']
    ?? '';

if (
    stripos(
        $contentType,
        'multipart/form-data'
    ) === false
) {
    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' =>
            'Content-Type must be multipart/form-data.'
    ]);

    exit;
}


// --------------------------------------------------
// ALLOWED FORM FIELDS
// --------------------------------------------------

$allowedFields = [
    'name',
    'description',
    'meal_type',
    'status',
    'items'
];

$unknownFields = array_diff(
    array_keys($_POST),
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
// ALLOWED FILE FIELD
// --------------------------------------------------

$allowedFileFields = [
    'image'
];

$unknownFileFields = array_diff(
    array_keys($_FILES),
    $allowedFileFields
);

if (!empty($unknownFileFields)) {
    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' => 'Unknown file field(s) found.',
        'unknown_file_fields' =>
            array_values($unknownFileFields)
    ]);

    exit;
}


// --------------------------------------------------
// NAME VALIDATION
// --------------------------------------------------

if (!isset($_POST['name'])) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Dining package name is required.'
    ]);

    exit;
}


if (!is_string($_POST['name'])) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'Dining package name must be a string.'
    ]);

    exit;
}


$name = trim($_POST['name']);

$nameLength = strlen($name);

if ($nameLength < 2 || $nameLength > 150) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'Dining package name must be between 2 and 150 characters.'
    ]);

    exit;
}


// --------------------------------------------------
// NAME FORMAT
// --------------------------------------------------

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
            'Dining package name contains invalid characters or spacing.'
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
            'Dining package name must not contain multiple consecutive spaces.'
    ]);

    exit;
}


// --------------------------------------------------
// DESCRIPTION VALIDATION
// --------------------------------------------------

$description = null;

if (array_key_exists('description', $_POST)) {

    if (
        $_POST['description'] !== '' &&
        !is_string($_POST['description'])
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Description must be a string.'
        ]);

        exit;
    }

    $description = trim(
        (string) $_POST['description']
    );


    if ($description === '') {
        $description = null;
    }


    if (
        $description !== null &&
        strlen($description) > 5000
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Description must not exceed 5000 characters.'
        ]);

        exit;
    }


    if (
        $description !== null &&
        preg_match('/\s{2,}/', $description)
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Description must not contain multiple consecutive spaces.'
        ]);

        exit;
    }
}


// --------------------------------------------------
// MEAL TYPE VALIDATION
// --------------------------------------------------

$mealType = 'combo';

if (array_key_exists('meal_type', $_POST)) {

    if (!is_string($_POST['meal_type'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Meal type must be a string.'
        ]);

        exit;
    }

    $mealType = strtolower(
        trim($_POST['meal_type'])
    );
}


$allowedMealTypes = [
    'lunch',
    'dinner',
    'breakfast',
    'combo',
    'other'
];


if (!in_array(
    $mealType,
    $allowedMealTypes,
    true
)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Invalid meal type.',
        'allowed_meal_types' =>
            $allowedMealTypes
    ]);

    exit;
}


// --------------------------------------------------
// STATUS VALIDATION
// --------------------------------------------------

$status = 'active';

if (array_key_exists('status', $_POST)) {

    if (!is_string($_POST['status'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Status must be a string.'
        ]);

        exit;
    }

    $status = strtolower(
        trim($_POST['status'])
    );
}


$allowedStatuses = [
    'active',
    'inactive'
];


if (!in_array(
    $status,
    $allowedStatuses,
    true
)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Invalid status.',
        'allowed_statuses' =>
            $allowedStatuses
    ]);

    exit;
}


// --------------------------------------------------
// ITEMS VALIDATION
// --------------------------------------------------
//
// Expected:
//
// items = [
//     {
//         "food_item_id": 1,
//         "quantity": 2
//     }
// ]
//
// Because multipart/form-data is used,
// send items as a JSON string.
//

$items = [];

if (array_key_exists('items', $_POST)) {

    if (!is_string($_POST['items'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Items must be a JSON array.'
        ]);

        exit;
    }


    $itemsRaw = trim($_POST['items']);


    if ($itemsRaw === '') {
        $items = [];
    } else {

        $decodedItems = json_decode(
            $itemsRaw,
            true
        );


        if (
            json_last_error() !== JSON_ERROR_NONE ||
            !is_array($decodedItems)
        ) {
            http_response_code(422);

            echo json_encode([
                'status' => false,
                'message' =>
                    'Items must contain a valid JSON array.'
            ]);

            exit;
        }


        $items = $decodedItems;
    }
}


// --------------------------------------------------
// MAX ITEMS
// --------------------------------------------------

if (count($items) > 100) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'A dining package can contain a maximum of 100 food items.'
    ]);

    exit;
}


// --------------------------------------------------
// ITEM VALIDATION
// --------------------------------------------------

$validatedItems = [];

$foodItemIds = [];

foreach ($items as $index => $item) {

    if (!is_array($item)) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                "Item at index {$index} must be an object."
        ]);

        exit;
    }


    // ----------------------------------------------
    // ITEM UNKNOWN FIELDS
    // ----------------------------------------------

    $allowedItemFields = [
        'food_item_id',
        'quantity'
    ];


    $unknownItemFields = array_diff(
        array_keys($item),
        $allowedItemFields
    );


    if (!empty($unknownItemFields)) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                "Unknown field(s) in item at index {$index}.",
            'unknown_fields' =>
                array_values($unknownItemFields)
        ]);

        exit;
    }


    // ----------------------------------------------
    // FOOD ITEM ID
    // ----------------------------------------------

    if (!array_key_exists(
        'food_item_id',
        $item
    )) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                "food_item_id is required for item at index {$index}."
        ]);

        exit;
    }


    if (
        filter_var(
            $item['food_item_id'],
            FILTER_VALIDATE_INT
        ) === false ||
        (int) $item['food_item_id'] <= 0
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                "food_item_id at index {$index} must be a positive integer."
        ]);

        exit;
    }


    $foodItemId = (int) $item['food_item_id'];


    // ----------------------------------------------
    // DUPLICATE FOOD ITEM
    // ----------------------------------------------

    if (in_array(
        $foodItemId,
        $foodItemIds,
        true
    )) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                "Duplicate food_item_id {$foodItemId} found in items."
        ]);

        exit;
    }


    $foodItemIds[] = $foodItemId;


    // ----------------------------------------------
    // QUANTITY
    // ----------------------------------------------

    $quantity = 1;

    if (array_key_exists(
        'quantity',
        $item
    )) {

        if (
            filter_var(
                $item['quantity'],
                FILTER_VALIDATE_INT
            ) === false
        ) {
            http_response_code(422);

            echo json_encode([
                'status' => false,
                'message' =>
                    "Quantity at index {$index} must be a valid integer."
            ]);

            exit;
        }


        $quantity = (int) $item['quantity'];
    }


    if ($quantity < 1 || $quantity > 1000) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                "Quantity at index {$index} must be between 1 and 1000."
        ]);

        exit;
    }


    $validatedItems[] = [
        'food_item_id' => $foodItemId,
        'quantity' => $quantity
    ];
}


// --------------------------------------------------
// IMAGE VALIDATION
// --------------------------------------------------

$imagePath = null;

$uploadedFilePath = null;

$uploadDirectory = null;

$imageUploaded = false;


if (isset($_FILES['image'])) {

    $image = $_FILES['image'];


    // ----------------------------------------------
    // UPLOAD ERROR
    // ----------------------------------------------

    if (
        !isset($image['error']) ||
        $image['error'] !== UPLOAD_ERR_OK
    ) {

        $uploadError = $image['error']
            ?? UPLOAD_ERR_NO_FILE;


        $errorMessages = [
            UPLOAD_ERR_INI_SIZE =>
                'Uploaded image exceeds server upload limit.',
            UPLOAD_ERR_FORM_SIZE =>
                'Uploaded image exceeds form upload limit.',
            UPLOAD_ERR_PARTIAL =>
                'Image upload was incomplete.',
            UPLOAD_ERR_NO_FILE =>
                'No image file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR =>
                'Temporary upload directory is missing.',
            UPLOAD_ERR_CANT_WRITE =>
                'Failed to write uploaded image.',
            UPLOAD_ERR_EXTENSION =>
                'Image upload was blocked by a PHP extension.'
        ];


        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                $errorMessages[$uploadError]
                ?? 'Image upload failed.'
        ]);

        exit;
    }


    // ----------------------------------------------
    // UPLOADED FILE CHECK
    // ----------------------------------------------

    if (
        !isset($image['tmp_name']) ||
        !is_uploaded_file($image['tmp_name'])
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Invalid uploaded image.'
        ]);

        exit;
    }


    // ----------------------------------------------
    // FILE SIZE
    // ----------------------------------------------

    $maxImageSize = 5 * 1024 * 1024;

    if (
        !isset($image['size']) ||
        $image['size'] <= 0
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Uploaded image is empty.'
        ]);

        exit;
    }


    if ($image['size'] > $maxImageSize) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Image size must not exceed 5 MB.'
        ]);

        exit;
    }


    // ----------------------------------------------
    // IMAGE INFORMATION
    // ----------------------------------------------

    $imageInfo = @getimagesize(
        $image['tmp_name']
    );


    if ($imageInfo === false) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Uploaded file is not a valid image.'
        ]);

        exit;
    }


    $imageWidth = (int) $imageInfo[0];
    $imageHeight = (int) $imageInfo[1];


    if (
        $imageWidth <= 0 ||
        $imageHeight <= 0
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Invalid image dimensions.'
        ]);

        exit;
    }


    if (
        $imageWidth > 10000 ||
        $imageHeight > 10000
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Image dimensions must not exceed 10000 x 10000 pixels.'
        ]);

        exit;
    }


    // ----------------------------------------------
    // MIME VALIDATION
    // ----------------------------------------------

    $finfo = new finfo(FILEINFO_MIME_TYPE);

    $mimeType = $finfo->file(
        $image['tmp_name']
    );


    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];


    if (
        !isset(
            $allowedMimeTypes[$mimeType]
        )
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Only JPG, JPEG, PNG and WEBP images are allowed.'
        ]);

        exit;
    }


    $extension =
        $allowedMimeTypes[$mimeType];
}


// --------------------------------------------------
// DUPLICATE PACKAGE NAME
// --------------------------------------------------

$duplicateStmt = $conn->prepare("
    SELECT id
    FROM dining_packages
    WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))
    LIMIT 1
");


if (!$duplicateStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to prepare duplicate package validation.'
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
        'message' =>
            'Failed to validate duplicate package.'
    ]);

    exit;
}


$duplicateResult =
    $duplicateStmt->get_result();


if ($duplicateResult->num_rows > 0) {

    $duplicateStmt->close();

    http_response_code(409);

    echo json_encode([
        'status' => false,
        'message' =>
            'A dining package with this name already exists.'
    ]);

    exit;
}


$duplicateStmt->close();


// --------------------------------------------------
// VALIDATE FOOD ITEMS
// --------------------------------------------------

$foodItemsData = [];


if (!empty($validatedItems)) {

    $foodItemStmt = $conn->prepare("
        SELECT
            id,
            name,
            food_type,
            status
        FROM food_items
        WHERE id = ?
        LIMIT 1
    ");


    if (!$foodItemStmt) {
        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' =>
                'Failed to prepare food item validation.'
        ]);

        exit;
    }


    foreach ($validatedItems as $item) {

        $foodItemId = $item['food_item_id'];


        $foodItemStmt->bind_param(
            'i',
            $foodItemId
        );


        if (!$foodItemStmt->execute()) {

            $foodItemStmt->close();

            http_response_code(500);

            echo json_encode([
                'status' => false,
                'message' =>
                    'Failed to validate food item.'
            ]);

            exit;
        }


        $foodItemResult =
            $foodItemStmt->get_result();


        if ($foodItemResult->num_rows !== 1) {

            $foodItemStmt->close();

            http_response_code(422);

            echo json_encode([
                'status' => false,
                'message' =>
                    "Food item ID {$foodItemId} does not exist."
            ]);

            exit;
        }


        $foodItem =
            $foodItemResult->fetch_assoc();


        if ($foodItem['status'] !== 'active') {

            $foodItemStmt->close();

            http_response_code(422);

            echo json_encode([
                'status' => false,
                'message' =>
                    "Food item ID {$foodItemId} is inactive."
            ]);

            exit;
        }


        $foodItemsData[] = [
            'food_item_id' => $foodItemId,
            'name' => $foodItem['name'],
            'food_type' =>
                $foodItem['food_type'],
            'quantity' =>
                $item['quantity']
        ];
    }


    $foodItemStmt->close();
}


// --------------------------------------------------
// TRANSACTION
// --------------------------------------------------

try {

    $conn->begin_transaction();


    // --------------------------------------------------
    // INSERT PACKAGE
    // --------------------------------------------------

    $insertStmt = $conn->prepare("
        INSERT INTO dining_packages
        (
            name,
            description,
            meal_type,
            image_path,
            status
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?
        )
    ");


    if (!$insertStmt) {
        throw new Exception(
            'Failed to prepare dining package creation.'
        );
    }


    $insertStmt->bind_param(
        'sssss',
        $name,
        $description,
        $mealType,
        $imagePath,
        $status
    );


    if (!$insertStmt->execute()) {

        $errorCode = $insertStmt->errno;
        $errorMessage = $insertStmt->error;

        $insertStmt->close();


        if ($errorCode === 1062) {

            $conn->rollback();

            http_response_code(409);

            echo json_encode([
                'status' => false,
                'message' =>
                    'A dining package with this name already exists.'
            ]);

            exit;
        }


        throw new Exception($errorMessage);
    }


    $packageId =
        (int) $insertStmt->insert_id;


    $insertStmt->close();


    if ($packageId <= 0) {
        throw new Exception(
            'Failed to retrieve dining package ID.'
        );
    }


    // --------------------------------------------------
    // IMAGE DIRECTORY
    // --------------------------------------------------

    if (isset($_FILES['image'])) {

        $uploadDirectory =
            dirname(__DIR__, 2)
            . '/uploads/dining_packages/'
            . $packageId
            . '/image/';


        if (!is_dir($uploadDirectory)) {

            if (
                !mkdir(
                    $uploadDirectory,
                    0755,
                    true
                )
            ) {
                throw new Exception(
                    'Failed to create dining package image directory.'
                );
            }
        }


        if (
            !is_writable($uploadDirectory)
        ) {
            throw new Exception(
                'Dining package image directory is not writable.'
            );
        }


        $randomFileName =
            bin2hex(random_bytes(16))
            . '.'
            . $extension;


        $uploadedFilePath =
            $uploadDirectory
            . $randomFileName;


        $imagePath =
            'uploads/dining_packages/'
            . $packageId
            . '/image/'
            . $randomFileName;


        if (
            !move_uploaded_file(
                $_FILES['image']['tmp_name'],
                $uploadedFilePath
            )
        ) {
            throw new Exception(
                'Failed to save dining package image.'
            );
        }


        // ----------------------------------------------
        // UPDATE IMAGE PATH
        // ----------------------------------------------

        $imageUpdateStmt = $conn->prepare("
            UPDATE dining_packages
            SET image_path = ?
            WHERE id = ?
        ");


        if (!$imageUpdateStmt) {
            throw new Exception(
                'Failed to prepare image path update.'
            );
        }


        $imageUpdateStmt->bind_param(
            'si',
            $imagePath,
            $packageId
        );


        if (!$imageUpdateStmt->execute()) {

            $imageUpdateStmt->close();

            throw new Exception(
                'Failed to save dining package image path.'
            );
        }


        $imageUpdateStmt->close();
    }


    // --------------------------------------------------
    // INSERT PACKAGE ITEMS
    // --------------------------------------------------

    if (!empty($validatedItems)) {

        $itemInsertStmt = $conn->prepare("
            INSERT INTO dining_package_items
            (
                package_id,
                food_item_id,
                quantity
            )
            VALUES
            (
                ?,
                ?,
                ?
            )
        ");


        if (!$itemInsertStmt) {
            throw new Exception(
                'Failed to prepare dining package item creation.'
            );
        }


        foreach ($validatedItems as $item) {

            $foodItemId =
                $item['food_item_id'];

            $quantity =
                $item['quantity'];


            $itemInsertStmt->bind_param(
                'iii',
                $packageId,
                $foodItemId,
                $quantity
            );


            if (!$itemInsertStmt->execute()) {

                $errorCode =
                    $itemInsertStmt->errno;


                $itemInsertStmt->close();


                if ($errorCode === 1062) {
                    throw new Exception(
                        'Duplicate food item found in dining package.'
                    );
                }


                if ($errorCode === 1452) {
                    throw new Exception(
                        'One or more selected food items do not exist.'
                    );
                }


                throw new Exception(
                    'Failed to create dining package items.'
                );
            }
        }


        $itemInsertStmt->close();
    }


    // --------------------------------------------------
    // FETCH CREATED PACKAGE
    // --------------------------------------------------

    $fetchStmt = $conn->prepare("
        SELECT
            id,
            name,
            description,
            meal_type,
            image_path,
            status,
            created_at,
            updated_at
        FROM dining_packages
        WHERE id = ?
        LIMIT 1
    ");


    if (!$fetchStmt) {
        throw new Exception(
            'Failed to prepare created package fetch.'
        );
    }


    $fetchStmt->bind_param(
        'i',
        $packageId
    );


    if (!$fetchStmt->execute()) {

        $fetchStmt->close();

        throw new Exception(
            'Failed to fetch created dining package.'
        );
    }


    $fetchResult =
        $fetchStmt->get_result();


    if ($fetchResult->num_rows !== 1) {

        $fetchStmt->close();

        throw new Exception(
            'Created dining package could not be found.'
        );
    }


    $package =
        $fetchResult->fetch_assoc();


    $fetchStmt->close();


    // --------------------------------------------------
    // COMMIT
    // --------------------------------------------------

    $conn->commit();


    // --------------------------------------------------
    // SUCCESS RESPONSE
    // --------------------------------------------------

    http_response_code(201);

    echo json_encode([
        'status' => true,
        'message' =>
            'Dining package created successfully.',
        'data' => [
            'package' => [
                'id' => (int) $package['id'],
                'name' => $package['name'],
                'description' =>
                    $package['description'],
                'meal_type' =>
                    $package['meal_type'],
                'image_path' =>
                    $package['image_path'],
                'status' =>
                    $package['status'],
                'created_at' =>
                    $package['created_at'],
                'updated_at' =>
                    $package['updated_at']
            ],
            'items' => $foodItemsData,
            'total_items' =>
                count($foodItemsData)
        ],
        'created_by' => [
            'user_id' =>
                (int) $user['sub'],
            'role' =>
                $user['role']
        ]
    ]);

    exit;


} catch (Throwable $e) {

    if ($conn->in_transaction) {
        $conn->rollback();
    }


    // ----------------------------------------------
    // CLEANUP UPLOADED FILE
    // ----------------------------------------------

    if (
        $uploadedFilePath !== null &&
        file_exists($uploadedFilePath)
    ) {
        @unlink($uploadedFilePath);
    }


    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to create dining package.'
    ]);

    exit;
}