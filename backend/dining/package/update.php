<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, POST, OPTIONS");
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
// CONTENT TYPE
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
    'package_id',
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
        'unknown_fields' =>
            array_values($unknownFields)
    ]);

    exit;
}


// --------------------------------------------------
// ALLOWED FILE FIELDS
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
// PACKAGE ID VALIDATION
// --------------------------------------------------

if (!array_key_exists(
    'package_id',
    $_POST
)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'Dining package ID is required.'
    ]);

    exit;
}


if (
    filter_var(
        $_POST['package_id'],
        FILTER_VALIDATE_INT
    ) === false ||
    (int) $_POST['package_id'] <= 0
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'Dining package ID must be a valid positive integer.'
    ]);

    exit;
}


$packageId = (int) $_POST['package_id'];


// --------------------------------------------------
// AT LEAST ONE UPDATE FIELD
// --------------------------------------------------

$updateFieldsProvided = array_intersect(
    [
        'name',
        'description',
        'meal_type',
        'status',
        'items'
    ],
    array_keys($_POST)
);


$imageProvided = isset($_FILES['image']);


if (
    empty($updateFieldsProvided) &&
    !$imageProvided
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'At least one field or image must be provided for update.',
        'updatable_fields' => [
            'name',
            'description',
            'meal_type',
            'status',
            'items',
            'image'
        ]
    ]);

    exit;
}


// --------------------------------------------------
// FETCH CURRENT PACKAGE
// --------------------------------------------------

$currentStmt = $conn->prepare("
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


if (!$currentStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to prepare package lookup.'
    ]);

    exit;
}


$currentStmt->bind_param(
    'i',
    $packageId
);


if (!$currentStmt->execute()) {

    $currentStmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to fetch dining package.'
    ]);

    exit;
}


$currentResult =
    $currentStmt->get_result();


if ($currentResult->num_rows !== 1) {

    $currentStmt->close();

    http_response_code(404);

    echo json_encode([
        'status' => false,
        'message' =>
            'Dining package not found.'
    ]);

    exit;
}


$currentPackage =
    $currentResult->fetch_assoc();


$currentStmt->close();


// --------------------------------------------------
// CURRENT VALUES
// --------------------------------------------------

$currentName =
    $currentPackage['name'];

$currentDescription =
    $currentPackage['description'];

$currentMealType =
    $currentPackage['meal_type'];

$currentImagePath =
    $currentPackage['image_path'];

$currentStatus =
    $currentPackage['status'];


// --------------------------------------------------
// NEW VALUES
// --------------------------------------------------

$newName = $currentName;
$newDescription = $currentDescription;
$newMealType = $currentMealType;
$newStatus = $currentStatus;
$newImagePath = $currentImagePath;


// --------------------------------------------------
// NAME VALIDATION
// --------------------------------------------------

if (array_key_exists(
    'name',
    $_POST
)) {

    if (!is_string($_POST['name'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Dining package name must be a string.'
        ]);

        exit;
    }


    $newName =
        trim($_POST['name']);


    $nameLength =
        strlen($newName);


    if (
        $nameLength < 2 ||
        $nameLength > 150
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Dining package name must be between 2 and 150 characters.'
        ]);

        exit;
    }


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
                'Dining package name contains invalid characters or spacing.'
        ]);

        exit;
    }


    if (
        preg_match(
            '/\s{2,}/',
            $newName
        )
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Dining package name must not contain multiple consecutive spaces.'
        ]);

        exit;
    }
}


// --------------------------------------------------
// DESCRIPTION VALIDATION
// --------------------------------------------------

if (array_key_exists(
    'description',
    $_POST
)) {

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


    $newDescription =
        trim(
            (string) $_POST['description']
        );


    if ($newDescription === '') {
        $newDescription = null;
    }


    if (
        $newDescription !== null &&
        strlen($newDescription) > 5000
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
        $newDescription !== null &&
        preg_match(
            '/\s{2,}/',
            $newDescription
        )
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

if (array_key_exists(
    'meal_type',
    $_POST
)) {

    if (!is_string($_POST['meal_type'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Meal type must be a string.'
        ]);

        exit;
    }


    $newMealType =
        strtolower(
            trim($_POST['meal_type'])
        );


    $allowedMealTypes = [
        'lunch',
        'dinner',
        'breakfast',
        'combo',
        'other'
    ];


    if (!in_array(
        $newMealType,
        $allowedMealTypes,
        true
    )) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Invalid meal type.',
            'allowed_meal_types' =>
                $allowedMealTypes
        ]);

        exit;
    }
}


// --------------------------------------------------
// STATUS VALIDATION
// --------------------------------------------------

if (array_key_exists(
    'status',
    $_POST
)) {

    if (!is_string($_POST['status'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Status must be a string.'
        ]);

        exit;
    }


    $newStatus =
        strtolower(
            trim($_POST['status'])
        );


    $allowedStatuses = [
        'active',
        'inactive'
    ];


    if (!in_array(
        $newStatus,
        $allowedStatuses,
        true
    )) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Invalid status.',
            'allowed_statuses' =>
                $allowedStatuses
        ]);

        exit;
    }
}


// --------------------------------------------------
// ITEMS VALIDATION
// --------------------------------------------------

$itemsProvided =
    array_key_exists(
        'items',
        $_POST
    );


$validatedItems = [];


if ($itemsProvided) {

    if (!is_string($_POST['items'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Items must be a JSON array.'
        ]);

        exit;
    }


    $itemsRaw =
        trim($_POST['items']);


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


    if (count($decodedItems) > 100) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'A dining package can contain a maximum of 100 food items.'
        ]);

        exit;
    }


    $foodItemIds = [];


    foreach (
        $decodedItems
        as $index => $item
    ) {

        if (!is_array($item)) {
            http_response_code(422);

            echo json_encode([
                'status' => false,
                'message' =>
                    "Item at index {$index} must be an object."
            ]);

            exit;
        }


        // ------------------------------------------
        // ITEM FIELDS
        // ------------------------------------------

        $allowedItemFields = [
            'food_item_id',
            'quantity'
        ];


        $unknownItemFields =
            array_diff(
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
                    array_values(
                        $unknownItemFields
                    )
            ]);

            exit;
        }


        // ------------------------------------------
        // FOOD ITEM ID
        // ------------------------------------------

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


        $foodItemId =
            (int) $item['food_item_id'];


        // ------------------------------------------
        // DUPLICATE ITEM
        // ------------------------------------------

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


        $foodItemIds[] =
            $foodItemId;


        // ------------------------------------------
        // QUANTITY
        // ------------------------------------------

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


            $quantity =
                (int) $item['quantity'];
        }


        if (
            $quantity < 1 ||
            $quantity > 1000
        ) {
            http_response_code(422);

            echo json_encode([
                'status' => false,
                'message' =>
                    "Quantity at index {$index} must be between 1 and 1000."
            ]);

            exit;
        }


        $validatedItems[] = [
            'food_item_id' =>
                $foodItemId,
            'quantity' =>
                $quantity
        ];
    }
}


// --------------------------------------------------
// VALIDATE FOOD ITEMS
// --------------------------------------------------

$foodItemsData = [];


if ($itemsProvided && !empty($validatedItems)) {

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


    foreach (
        $validatedItems
        as $item
    ) {

        $foodItemId =
            $item['food_item_id'];


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


        if (
            $foodItemResult->num_rows !== 1
        ) {

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


        if (
            $foodItem['status'] !== 'active'
        ) {

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
            'food_item_id' =>
                $foodItemId,
            'name' =>
                $foodItem['name'],
            'food_type' =>
                $foodItem['food_type'],
            'quantity' =>
                $item['quantity']
        ];
    }


    $foodItemStmt->close();
}


// --------------------------------------------------
// IMAGE VALIDATION
// --------------------------------------------------

$newImageUploaded = false;

$uploadedFilePath = null;

$oldImagePathToDelete = null;

$imageExtension = null;


if ($imageProvided) {

    $image = $_FILES['image'];


    // ----------------------------------------------
    // UPLOAD ERROR
    // ----------------------------------------------

    if (
        !isset($image['error']) ||
        $image['error'] !== UPLOAD_ERR_OK
    ) {

        $uploadError =
            $image['error']
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
    // IS UPLOADED FILE
    // ----------------------------------------------

    if (
        !isset($image['tmp_name']) ||
        !is_uploaded_file(
            $image['tmp_name']
        )
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
    // SIZE
    // ----------------------------------------------

    $maxImageSize =
        5 * 1024 * 1024;


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


    if (
        $image['size'] >
        $maxImageSize
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Image size must not exceed 5 MB.'
        ]);

        exit;
    }


    // ----------------------------------------------
    // IMAGE VALIDATION
    // ----------------------------------------------

    $imageInfo =
        @getimagesize(
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


    $imageWidth =
        (int) $imageInfo[0];

    $imageHeight =
        (int) $imageInfo[1];


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
    // MIME TYPE
    // ----------------------------------------------

    $finfo =
        new finfo(FILEINFO_MIME_TYPE);


    $mimeType =
        $finfo->file(
            $image['tmp_name']
        );


    $allowedMimeTypes = [
        'image/jpeg' =>
            'jpg',
        'image/png' =>
            'png',
        'image/webp' =>
            'webp'
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


    $imageExtension =
        $allowedMimeTypes[$mimeType];
}


// --------------------------------------------------
// DUPLICATE NAME CHECK
// --------------------------------------------------

if (
    $newName !== $currentName
) {

    $duplicateStmt = $conn->prepare("
        SELECT id
        FROM dining_packages
        WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))
          AND id != ?
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
        'si',
        $newName,
        $packageId
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


    if (
        $duplicateResult->num_rows > 0
    ) {

        $duplicateStmt->close();

        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' =>
                'Another dining package with this name already exists.'
        ]);

        exit;
    }


    $duplicateStmt->close();
}


// --------------------------------------------------
// DETECT FIELD CHANGES
// --------------------------------------------------

$changedFields = [];


if (
    $newName !== $currentName
) {
    $changedFields[] = 'name';
}


if (
    $newDescription !==
    $currentDescription
) {
    $changedFields[] =
        'description';
}


if (
    $newMealType !==
    $currentMealType
) {
    $changedFields[] =
        'meal_type';
}


if (
    $newStatus !==
    $currentStatus
) {
    $changedFields[] =
        'status';
}


if ($itemsProvided) {
    $changedFields[] =
        'items';
}


if ($imageProvided) {
    $changedFields[] =
        'image';
}


// --------------------------------------------------
// NO CHANGES
// --------------------------------------------------

if (empty($changedFields)) {

    http_response_code(200);

    echo json_encode([
        'status' => true,
        'message' =>
            'No changes were made.',
        'data' => [
            'package' => [
                'id' =>
                    (int) $currentPackage['id'],
                'name' =>
                    $currentPackage['name'],
                'description' =>
                    $currentPackage['description'],
                'meal_type' =>
                    $currentPackage['meal_type'],
                'image_path' =>
                    $currentPackage['image_path'],
                'status' =>
                    $currentPackage['status'],
                'created_at' =>
                    $currentPackage['created_at'],
                'updated_at' =>
                    $currentPackage['updated_at']
            ]
        ],
        'updated_fields' => [],
        'affected_rows' => 0,
        'updated_by' => [
            'user_id' =>
                (int) $user['sub'],
            'role' =>
                $user['role']
        ]
    ]);

    exit;
}


// --------------------------------------------------
// TRANSACTION
// --------------------------------------------------

try {

    $conn->begin_transaction();


    // --------------------------------------------------
    // LOCK PACKAGE
    // --------------------------------------------------

    $lockStmt = $conn->prepare("
        SELECT
            id,
            name,
            description,
            meal_type,
            image_path,
            status
        FROM dining_packages
        WHERE id = ?
        FOR UPDATE
    ");


    if (!$lockStmt) {
        throw new Exception(
            'Failed to prepare package lock.'
        );
    }


    $lockStmt->bind_param(
        'i',
        $packageId
    );


    if (!$lockStmt->execute()) {

        $lockStmt->close();

        throw new Exception(
            'Failed to lock dining package.'
        );
    }


    $lockResult =
        $lockStmt->get_result();


    if ($lockResult->num_rows !== 1) {

        $lockStmt->close();

        $conn->rollback();

        http_response_code(404);

        echo json_encode([
            'status' => false,
            'message' =>
                'Dining package not found.'
        ]);

        exit;
    }


    $lockedPackage =
        $lockResult->fetch_assoc();


    $lockStmt->close();


    // --------------------------------------------------
    // RECHECK DUPLICATE NAME
    // --------------------------------------------------

    if (
        $newName !==
        $lockedPackage['name']
    ) {

        $duplicateLockStmt =
            $conn->prepare("
                SELECT id
                FROM dining_packages
                WHERE LOWER(TRIM(name)) =
                      LOWER(TRIM(?))
                  AND id != ?
                LIMIT 1
            ");


        if (!$duplicateLockStmt) {
            throw new Exception(
                'Failed to prepare duplicate package validation.'
            );
        }


        $duplicateLockStmt->bind_param(
            'si',
            $newName,
            $packageId
        );


        if (
            !$duplicateLockStmt->execute()
        ) {

            $duplicateLockStmt->close();

            throw new Exception(
                'Failed to validate duplicate package.'
            );
        }


        $duplicateLockResult =
            $duplicateLockStmt->get_result();


        if (
            $duplicateLockResult->num_rows > 0
        ) {

            $duplicateLockStmt->close();

            $conn->rollback();

            http_response_code(409);

            echo json_encode([
                'status' => false,
                'message' =>
                    'Another dining package with this name already exists.'
            ]);

            exit;
        }


        $duplicateLockStmt->close();
    }


    // --------------------------------------------------
    // PACKAGE UPDATE
    // --------------------------------------------------

    $setParts = [];

    $bindTypes = '';

    $bindValues = [];


    if (
        in_array(
            'name',
            $changedFields,
            true
        )
    ) {

        $setParts[] =
            'name = ?';

        $bindTypes .= 's';

        $bindValues[] =
            $newName;
    }


    if (
        in_array(
            'description',
            $changedFields,
            true
        )
    ) {

        $setParts[] =
            'description = ?';

        $bindTypes .= 's';

        $bindValues[] =
            $newDescription;
    }


    if (
        in_array(
            'meal_type',
            $changedFields,
            true
        )
    ) {

        $setParts[] =
            'meal_type = ?';

        $bindTypes .= 's';

        $bindValues[] =
            $newMealType;
    }


    if (
        in_array(
            'status',
            $changedFields,
            true
        )
    ) {

        $setParts[] =
            'status = ?';

        $bindTypes .= 's';

        $bindValues[] =
            $newStatus;
    }


    // --------------------------------------------------
    // IMAGE DIRECTORY + FILE
    // --------------------------------------------------

    if ($imageProvided) {

        $uploadDirectory =
            dirname(__DIR__, 2)
            . '/uploads/dining_packages/'
            . $packageId
            . '/image/';


        if (!is_dir(
            $uploadDirectory
        )) {

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
            !is_writable(
                $uploadDirectory
            )
        ) {
            throw new Exception(
                'Dining package image directory is not writable.'
            );
        }


        $randomFileName =
            bin2hex(
                random_bytes(16)
            )
            . '.'
            . $imageExtension;


        $uploadedFilePath =
            $uploadDirectory
            . $randomFileName;


        $newImagePath =
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


        $newImagePath =
            'uploads/dining_packages/'
            . $packageId
            . '/image/'
            . $randomFileName;


        $setParts[] =
            'image_path = ?';

        $bindTypes .= 's';

        $bindValues[] =
            $newImagePath;


        // ------------------------------------------
        // OLD IMAGE
        // ------------------------------------------

        $oldImagePathToDelete =
            $lockedPackage['image_path'];
    }


    // --------------------------------------------------
    // UPDATE PACKAGE
    // --------------------------------------------------

    if (!empty($setParts)) {

        $bindTypes .= 'i';

        $bindValues[] =
            $packageId;


        $updateSql = "
            UPDATE dining_packages
            SET "
            . implode(
                ', ',
                $setParts
            )
            . "
            WHERE id = ?
        ";


        $updateStmt =
            $conn->prepare(
                $updateSql
            );


        if (!$updateStmt) {
            throw new Exception(
                'Failed to prepare dining package update.'
            );
        }


        $bindParams = [];

        $bindParams[] =
            $bindTypes;


        foreach (
            $bindValues
            as $key => $value
        ) {
            $bindParams[] =
                &$bindValues[$key];
        }


        call_user_func_array(
            [
                $updateStmt,
                'bind_param'
            ],
            $bindParams
        );


        if (
            !$updateStmt->execute()
        ) {

            $errorCode =
                $updateStmt->errno;

            $errorMessage =
                $updateStmt->error;


            $updateStmt->close();


            if (
                $errorCode === 1062
            ) {

                $conn->rollback();

                if (
                    $uploadedFilePath !== null &&
                    file_exists(
                        $uploadedFilePath
                    )
                ) {
                    @unlink(
                        $uploadedFilePath
                    );
                }


                http_response_code(409);

                echo json_encode([
                    'status' => false,
                    'message' =>
                        'Another dining package with this name already exists.'
                ]);

                exit;
            }


            throw new Exception(
                $errorMessage
            );
        }


        $affectedRows =
            $updateStmt->affected_rows;


        $updateStmt->close();

    } else {

        $affectedRows = 0;
    }


    // --------------------------------------------------
    // UPDATE ITEMS
    // --------------------------------------------------
    //
    // If items field is NOT supplied:
    // existing items remain unchanged.
    //
    // If items = []:
    // all existing items are removed.
    //
    // If items contains data:
    // existing mappings are replaced.
    //

    if ($itemsProvided) {

        // ----------------------------------------------
        // DELETE OLD ITEMS
        // ----------------------------------------------

        $deleteItemsStmt =
            $conn->prepare("
                DELETE FROM dining_package_items
                WHERE package_id = ?
            ");


        if (!$deleteItemsStmt) {
            throw new Exception(
                'Failed to prepare dining package item replacement.'
            );
        }


        $deleteItemsStmt->bind_param(
            'i',
            $packageId
        );


        if (
            !$deleteItemsStmt->execute()
        ) {

            $deleteItemsStmt->close();

            throw new Exception(
                'Failed to remove existing dining package items.'
            );
        }


        $deleteItemsStmt->close();


        // ----------------------------------------------
        // INSERT NEW ITEMS
        // ----------------------------------------------

        if (!empty(
            $validatedItems
        )) {

            $insertItemStmt =
                $conn->prepare("
                    INSERT INTO dining_package_items
                    (
                        package_id,
                        food_item_id,
                        quantity
                    )
                    VALUES (?, ?, ?)
                ");


            if (!$insertItemStmt) {
                throw new Exception(
                    'Failed to prepare dining package item insertion.'
                );
            }


            foreach (
                $validatedItems
                as $item
            ) {

                $foodItemId =
                    $item['food_item_id'];

                $quantity =
                    $item['quantity'];


                $insertItemStmt->bind_param(
                    'iii',
                    $packageId,
                    $foodItemId,
                    $quantity
                );


                if (
                    !$insertItemStmt->execute()
                ) {

                    $errorCode =
                        $insertItemStmt->errno;


                    $insertItemStmt->close();


                    if (
                        $errorCode === 1062
                    ) {
                        throw new Exception(
                            'Duplicate food item found in dining package.'
                        );
                    }


                    if (
                        $errorCode === 1452
                    ) {
                        throw new Exception(
                            'One or more selected food items do not exist.'
                        );
                    }


                    throw new Exception(
                        'Failed to insert dining package items.'
                    );
                }
            }


            $insertItemStmt->close();
        }
    }


    // --------------------------------------------------
    // FETCH UPDATED PACKAGE
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
            'Failed to prepare updated package fetch.'
        );
    }


    $fetchStmt->bind_param(
        'i',
        $packageId
    );


    if (
        !$fetchStmt->execute()
    ) {

        $fetchStmt->close();

        throw new Exception(
            'Failed to fetch updated dining package.'
        );
    }


    $fetchResult =
        $fetchStmt->get_result();


    if (
        $fetchResult->num_rows !== 1
    ) {

        $fetchStmt->close();

        throw new Exception(
            'Updated dining package could not be found.'
        );
    }


    $updatedPackage =
        $fetchResult->fetch_assoc();


    $fetchStmt->close();


    // --------------------------------------------------
    // FETCH FINAL ITEMS
    // --------------------------------------------------

    $finalItems = [];


    $finalItemsStmt = $conn->prepare("
        SELECT
            dpi.food_item_id,
            fi.name,
            fi.food_type,
            dpi.quantity
        FROM dining_package_items dpi
        INNER JOIN food_items fi
            ON fi.id = dpi.food_item_id
        WHERE dpi.package_id = ?
        ORDER BY fi.name ASC
    ");


    if (!$finalItemsStmt) {
        throw new Exception(
            'Failed to prepare package items fetch.'
        );
    }


    $finalItemsStmt->bind_param(
        'i',
        $packageId
    );


    if (
        !$finalItemsStmt->execute()
    ) {

        $finalItemsStmt->close();

        throw new Exception(
            'Failed to fetch dining package items.'
        );
    }


    $finalItemsResult =
        $finalItemsStmt->get_result();


    while (
        $item =
        $finalItemsResult->fetch_assoc()
    ) {

        $finalItems[] = [
            'food_item_id' =>
                (int) $item['food_item_id'],
            'name' =>
                $item['name'],
            'food_type' =>
                $item['food_type'],
            'quantity' =>
                (int) $item['quantity']
        ];
    }


    $finalItemsStmt->close();


    // --------------------------------------------------
    // COMMIT
    // --------------------------------------------------

    $conn->commit();


    // --------------------------------------------------
    // DELETE OLD IMAGE AFTER COMMIT
    // --------------------------------------------------

    $oldImageDeleted = null;

    $oldImageWarning = null;


    if (
        $imageProvided &&
        $oldImagePathToDelete !== null &&
        trim($oldImagePathToDelete) !== ''
    ) {

        $oldRelativePath =
            str_replace(
                ['\\', '/'],
                '/',
                trim(
                    $oldImagePathToDelete
                )
            );


        // ----------------------------------------------
        // SECURITY CHECK
        // ----------------------------------------------

        if (
            strpos(
                $oldRelativePath,
                '..'
            ) !== false ||
            str_starts_with(
                $oldRelativePath,
                '/'
            ) ||
            preg_match(
                '/^[A-Za-z]:\//',
                $oldRelativePath
            )
        ) {

            $oldImageWarning =
                'Old image path was invalid, so the old physical file was not deleted.';

        } else {

            $projectRoot =
                dirname(__DIR__, 2);


            $oldPhysicalPath =
                $projectRoot
                . '/'
                . $oldRelativePath;


            $realProjectRoot =
                realpath(
                    $projectRoot
                );


            $realOldDirectory =
                realpath(
                    dirname(
                        $oldPhysicalPath
                    )
                );


            if (
                $realProjectRoot !== false &&
                $realOldDirectory !== false &&
                (
                    $realOldDirectory ===
                    $realProjectRoot ||
                    str_starts_with(
                        $realOldDirectory,
                        $realProjectRoot
                        . DIRECTORY_SEPARATOR
                    )
                )
            ) {

                if (
                    file_exists(
                        $oldPhysicalPath
                    )
                ) {

                    if (
                        @unlink(
                            $oldPhysicalPath
                        )
                    ) {
                        $oldImageDeleted =
                            true;
                    } else {
                        $oldImageDeleted =
                            false;

                        $oldImageWarning =
                            'Package was updated, but the old image file could not be deleted.';
                    }

                } else {

                    $oldImageDeleted =
                        false;

                    $oldImageWarning =
                        'Package was updated, but the old image file was not found.';
                }

            } else {

                $oldImageWarning =
                    'Old image path was outside the allowed upload directory.';
            }
        }
    }


    // --------------------------------------------------
    // SUCCESS RESPONSE
    // --------------------------------------------------

    http_response_code(200);

    echo json_encode([
        'status' => true,
        'message' =>
            'Dining package updated successfully.',
        'data' => [
            'package' => [
                'id' =>
                    (int) $updatedPackage['id'],
                'name' =>
                    $updatedPackage['name'],
                'description' =>
                    $updatedPackage['description'],
                'meal_type' =>
                    $updatedPackage['meal_type'],
                'image_path' =>
                    $updatedPackage['image_path'],
                'status' =>
                    $updatedPackage['status'],
                'created_at' =>
                    $updatedPackage['created_at'],
                'updated_at' =>
                    $updatedPackage['updated_at']
            ],
            'items' =>
                $finalItems,
            'total_items' =>
                count($finalItems)
        ],
        'updated_fields' =>
            array_values(
                array_unique(
                    $changedFields
                )
            ),
        'affected_rows' =>
            (int) $affectedRows,
        'image' => $imageProvided
            ? [
                'new_image_uploaded' =>
                    true,
                'old_image_deleted' =>
                    $oldImageDeleted,
                'warning' =>
                    $oldImageWarning
            ]
            : null,
        'updated_by' => [
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


    // --------------------------------------------------
    // CLEANUP NEW IMAGE
    // --------------------------------------------------

    if (
        $uploadedFilePath !== null &&
        file_exists(
            $uploadedFilePath
        )
    ) {
        @unlink(
            $uploadedFilePath
        );
    }


    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to update dining package.'
    ]);

    exit;
}