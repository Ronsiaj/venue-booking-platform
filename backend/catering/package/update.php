<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/middleware.php';

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'], true)) {

    http_response_code(405);

    echo json_encode([
        "status" => false,
        "message" => "Only POST or PUT method is allowed."
    ]);

    exit;
}

try {

    /*
    |--------------------------------------------------------------------------
    | ADMIN / STAFF AUTHENTICATION
    |--------------------------------------------------------------------------
    */

    requireAdminOrStaff();

    global $conn;

    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new Exception("Database connection is not available.");
    }

    /*
    |--------------------------------------------------------------------------
    | CONTENT TYPE
    |--------------------------------------------------------------------------
    */

    if (
        !isset($_SERVER['CONTENT_TYPE']) ||
        stripos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') === false
    ) {

        http_response_code(400);

        echo json_encode([
            "status" => false,
            "message" => "Content-Type must be multipart/form-data."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | ALLOWED FIELDS
    |--------------------------------------------------------------------------
    */

    $allowedFields = [
        'package_id',
        'name',
        'description',
        'rate',
        'rate_unit',
        'gst_rate',
        'price_includes_gst',
        'minimum_quantity',
        'status',
        'items',
        'remove_image'
    ];

    foreach ($_POST as $key => $value) {

        if (!in_array($key, $allowedFields, true)) {

            http_response_code(400);

            echo json_encode([
                "status" => false,
                "message" => "Unknown field: {$key}"
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PACKAGE ID
    |--------------------------------------------------------------------------
    */

    if (!isset($_POST['package_id'])) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "package_id is required."
        ]);

        exit;
    }

    $packageIdRaw = trim((string)$_POST['package_id']);

    if (
        $packageIdRaw === '' ||
        !ctype_digit($packageIdRaw) ||
        (int)$packageIdRaw < 1
    ) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "package_id must be a positive integer."
        ]);

        exit;
    }

    $packageId = (int)$packageIdRaw;

    /*
    |--------------------------------------------------------------------------
    | FETCH EXISTING PACKAGE
    |--------------------------------------------------------------------------
    */

    $existingSql = "
        SELECT
            id,
            name,
            description,
            rate,
            rate_unit,
            gst_rate,
            price_includes_gst,
            minimum_quantity,
            image_path,
            status
        FROM catering_packages
        WHERE id = ?
        LIMIT 1
    ";

    $existingStmt = $conn->prepare($existingSql);

    if (!$existingStmt) {
        throw new Exception(
            "Failed to prepare package lookup."
        );
    }

    $existingStmt->bind_param(
        "i",
        $packageId
    );

    if (!$existingStmt->execute()) {

        $existingStmt->close();

        throw new Exception(
            "Failed to fetch existing package."
        );
    }

    $existingResult = $existingStmt->get_result();

    $existingPackage = $existingResult->fetch_assoc();

    $existingStmt->close();

    if (!$existingPackage) {

        http_response_code(404);

        echo json_encode([
            "status" => false,
            "message" => "Catering package not found."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK WHETHER ANY UPDATE FIELD IS PROVIDED
    |--------------------------------------------------------------------------
    */

    $updateFieldsProvided = false;

    foreach ($allowedFields as $field) {

        if ($field === 'package_id') {
            continue;
        }

        if (array_key_exists($field, $_POST)) {
            $updateFieldsProvided = true;
            break;
        }
    }

    /*
    | Image field can be present in multipart request.
    */

    if (isset($_FILES['image'])) {
        $updateFieldsProvided = true;
    }

    if (!$updateFieldsProvided) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "At least one field is required to update."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | NAME
    |--------------------------------------------------------------------------
    */

    $name = $existingPackage['name'];

    if (isset($_POST['name'])) {

        $name = trim((string)$_POST['name']);

        if ($name === '') {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "Name cannot be empty."
            ]);

            exit;
        }

        if (
            mb_strlen($name) < 2 ||
            mb_strlen($name) > 150
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "Name must be between 2 and 150 characters."
            ]);

            exit;
        }

        if (!preg_match(
            "/^[A-Za-z0-9][A-Za-z0-9\s&.,'()\/+\-]*$/u",
            $name
        )) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "Name contains invalid characters."
            ]);

            exit;
        }

        if (preg_match('/\s{2,}/', $name)) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "Name cannot contain multiple consecutive spaces."
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DESCRIPTION
    |--------------------------------------------------------------------------
    */

    $description = $existingPackage['description'];

    if (isset($_POST['description'])) {

        $description = trim((string)$_POST['description']);

        if (
            $description !== '' &&
            mb_strlen($description) > 5000
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "Description cannot exceed 5000 characters."
            ]);

            exit;
        }

        if ($description === '') {
            $description = null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | RATE
    |--------------------------------------------------------------------------
    */

    $rate = (float)$existingPackage['rate'];

    if (isset($_POST['rate'])) {

        $rateRaw = trim((string)$_POST['rate']);

        if (!preg_match(
            '/^\d{1,10}(\.\d{1,2})?$/',
            $rateRaw
        )) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "Rate must be a valid decimal with maximum 2 decimal places."
            ]);

            exit;
        }

        $rate = (float)$rateRaw;

        if (
            $rate < 0 ||
            $rate > 9999999999.99
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "Rate must be between 0 and 9999999999.99."
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | RATE UNIT
    |--------------------------------------------------------------------------
    */

    $allowedRateUnits = [
        'per_person',
        'per_plate',
        'per_event',
        'per_package'
    ];

    $rateUnit = $existingPackage['rate_unit'];

    if (isset($_POST['rate_unit'])) {

        $rateUnit = trim((string)$_POST['rate_unit']);

        if (!in_array(
            $rateUnit,
            $allowedRateUnits,
            true
        )) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "Invalid rate_unit.",
                "allowed_rate_units" => $allowedRateUnits
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | GST RATE
    |--------------------------------------------------------------------------
    */

    $gstRate = (float)$existingPackage['gst_rate'];

    if (isset($_POST['gst_rate'])) {

        $gstRateRaw = trim((string)$_POST['gst_rate']);

        if (!preg_match(
            '/^\d{1,3}(\.\d{1,2})?$/',
            $gstRateRaw
        )) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "gst_rate must be a valid decimal with maximum 2 decimal places."
            ]);

            exit;
        }

        $gstRate = (float)$gstRateRaw;

        if (
            $gstRate < 0 ||
            $gstRate > 100
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "gst_rate must be between 0 and 100."
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PRICE INCLUDES GST
    |--------------------------------------------------------------------------
    */

    $priceIncludesGst =
        (int)$existingPackage['price_includes_gst'];

    if (isset($_POST['price_includes_gst'])) {

        $priceIncludesGstRaw =
            trim((string)$_POST['price_includes_gst']);

        if (!in_array(
            $priceIncludesGstRaw,
            ['0', '1'],
            true
        )) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "price_includes_gst must be either 0 or 1."
            ]);

            exit;
        }

        $priceIncludesGst =
            (int)$priceIncludesGstRaw;
    }

    /*
    |--------------------------------------------------------------------------
    | MINIMUM QUANTITY
    |--------------------------------------------------------------------------
    */

    $minimumQuantity =
        (int)$existingPackage['minimum_quantity'];

    if (isset($_POST['minimum_quantity'])) {

        $minimumQuantityRaw =
            trim((string)$_POST['minimum_quantity']);

        if (
            $minimumQuantityRaw === '' ||
            !ctype_digit($minimumQuantityRaw)
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "minimum_quantity must be a positive integer."
            ]);

            exit;
        }

        $minimumQuantity =
            (int)$minimumQuantityRaw;

        if (
            $minimumQuantity < 1 ||
            $minimumQuantity > 100000
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "minimum_quantity must be between 1 and 100000."
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS
    |--------------------------------------------------------------------------
    */

    $allowedStatuses = [
        'active',
        'inactive'
    ];

    $status = $existingPackage['status'];

    if (isset($_POST['status'])) {

        $status = trim((string)$_POST['status']);

        if (!in_array(
            $status,
            $allowedStatuses,
            true
        )) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "Invalid status.",
                "allowed_statuses" => $allowedStatuses
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ITEMS
    |--------------------------------------------------------------------------
    */

    $itemsProvided = false;
    $items = [];

    if (isset($_POST['items'])) {

        $itemsProvided = true;

        $itemsRaw = trim((string)$_POST['items']);

        if ($itemsRaw === '') {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "items cannot be empty."
            ]);

            exit;
        }

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
                "status" => false,
                "message" => "items must be a valid JSON array."
            ]);

            exit;
        }

        if (count($decodedItems) > 100) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "Maximum 100 food items are allowed."
            ]);

            exit;
        }

        $foodItemIds = [];

        foreach ($decodedItems as $index => $item) {

            if (!is_array($item)) {

                http_response_code(422);

                echo json_encode([
                    "status" => false,
                    "message" => "Each item must be an object.",
                    "item_index" => $index
                ]);

                exit;
            }

            /*
            |--------------------------------------------------------------------------
            | ITEM ALLOWED FIELDS
            |--------------------------------------------------------------------------
            */

            foreach ($item as $itemKey => $itemValue) {

                if (!in_array(
                    $itemKey,
                    ['food_item_id', 'quantity'],
                    true
                )) {

                    http_response_code(422);

                    echo json_encode([
                        "status" => false,
                        "message" => "Unknown item field: {$itemKey}.",
                        "item_index" => $index
                    ]);

                    exit;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | FOOD ITEM ID
            |--------------------------------------------------------------------------
            */

            if (!isset($item['food_item_id'])) {

                http_response_code(422);

                echo json_encode([
                    "status" => false,
                    "message" => "food_item_id is required.",
                    "item_index" => $index
                ]);

                exit;
            }

            $foodItemIdRaw =
                (string)$item['food_item_id'];

            if (
                $foodItemIdRaw === '' ||
                !ctype_digit($foodItemIdRaw)
            ) {

                http_response_code(422);

                echo json_encode([
                    "status" => false,
                    "message" => "food_item_id must be a positive integer.",
                    "item_index" => $index
                ]);

                exit;
            }

            $foodItemId =
                (int)$foodItemIdRaw;

            if ($foodItemId < 1) {

                http_response_code(422);

                echo json_encode([
                    "status" => false,
                    "message" => "food_item_id must be greater than 0.",
                    "item_index" => $index
                ]);

                exit;
            }

            /*
            |--------------------------------------------------------------------------
            | DUPLICATE FOOD ITEM
            |--------------------------------------------------------------------------
            */

            if (in_array(
                $foodItemId,
                $foodItemIds,
                true
            )) {

                http_response_code(422);

                echo json_encode([
                    "status" => false,
                    "message" => "Duplicate food_item_id is not allowed.",
                    "food_item_id" => $foodItemId
                ]);

                exit;
            }

            $foodItemIds[] =
                $foodItemId;

            /*
            |--------------------------------------------------------------------------
            | QUANTITY
            |--------------------------------------------------------------------------
            */

            $quantity = 1;

            if (array_key_exists('quantity', $item)) {

                $quantityRaw =
                    (string)$item['quantity'];

                if (
                    $quantityRaw === '' ||
                    !ctype_digit($quantityRaw)
                ) {

                    http_response_code(422);

                    echo json_encode([
                        "status" => false,
                        "message" => "quantity must be a positive integer.",
                        "item_index" => $index
                    ]);

                    exit;
                }

                $quantity =
                    (int)$quantityRaw;
            }

            if (
                $quantity < 1 ||
                $quantity > 100000
            ) {

                http_response_code(422);

                echo json_encode([
                    "status" => false,
                    "message" => "quantity must be between 1 and 100000.",
                    "item_index" => $index
                ]);

                exit;
            }

            $items[] = [
                "food_item_id" => $foodItemId,
                "quantity" => $quantity
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | REMOVE IMAGE
    |--------------------------------------------------------------------------
    */

    $removeImage = false;

    if (isset($_POST['remove_image'])) {

        $removeImageRaw =
            trim((string)$_POST['remove_image']);

        if (!in_array(
            $removeImageRaw,
            ['0', '1'],
            true
        )) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "remove_image must be either 0 or 1."
            ]);

            exit;
        }

        $removeImage =
            ($removeImageRaw === '1');
    }

    /*
    |--------------------------------------------------------------------------
    | IMAGE VALIDATION
    |--------------------------------------------------------------------------
    */

    $newImageUploaded = false;
    $uploadedImageFullPath = null;

    $oldImagePath =
        $existingPackage['image_path'];

    $newImagePath =
        $existingPackage['image_path'];

    $mimeType = null;
    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    /*
    |--------------------------------------------------------------------------
    | IMAGE FIELD PRESENT
    |--------------------------------------------------------------------------
    */

    if (isset($_FILES['image'])) {

        $image = $_FILES['image'];

        if (is_array($image['error'])) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "Only one image can be uploaded."
            ]);

            exit;
        }

        /*
        | If Postman contains image field but no file,
        | treat it as no new image.
        */

        if ($image['error'] === UPLOAD_ERR_NO_FILE) {

            $newImageUploaded = false;

        } else {

            if ($image['error'] !== UPLOAD_ERR_OK) {

                $uploadErrors = [
                    UPLOAD_ERR_INI_SIZE =>
                        "Uploaded image exceeds server upload limit.",

                    UPLOAD_ERR_FORM_SIZE =>
                        "Uploaded image exceeds form size limit.",

                    UPLOAD_ERR_PARTIAL =>
                        "Image upload was incomplete.",

                    UPLOAD_ERR_NO_TMP_DIR =>
                        "Temporary upload directory is missing.",

                    UPLOAD_ERR_CANT_WRITE =>
                        "Failed to write uploaded image.",

                    UPLOAD_ERR_EXTENSION =>
                        "Image upload was blocked by a PHP extension."
                ];

                http_response_code(422);

                echo json_encode([
                    "status" => false,
                    "message" =>
                        $uploadErrors[$image['error']]
                        ?? "Image upload failed."
                ]);

                exit;
            }

            /*
            |--------------------------------------------------------------------------
            | MAX IMAGE SIZE
            |--------------------------------------------------------------------------
            */

            $maxImageSize =
                5 * 1024 * 1024;

            if (
                $image['size'] <= 0 ||
                $image['size'] > $maxImageSize
            ) {

                http_response_code(422);

                echo json_encode([
                    "status" => false,
                    "message" => "Image size must be between 1 byte and 5 MB."
                ]);

                exit;
            }

            /*
            |--------------------------------------------------------------------------
            | MIME TYPE
            |--------------------------------------------------------------------------
            */

            $finfo =
                new finfo(FILEINFO_MIME_TYPE);

            $mimeType =
                $finfo->file($image['tmp_name']);

            if (!isset($allowedMimeTypes[$mimeType])) {

                http_response_code(422);

                echo json_encode([
                    "status" => false,
                    "message" => "Only JPG, PNG and WEBP images are allowed."
                ]);

                exit;
            }

            /*
            |--------------------------------------------------------------------------
            | IMAGE DIMENSIONS
            |--------------------------------------------------------------------------
            */

            $imageInfo =
                @getimagesize($image['tmp_name']);

            if ($imageInfo === false) {

                http_response_code(422);

                echo json_encode([
                    "status" => false,
                    "message" => "Uploaded file is not a valid image."
                ]);

                exit;
            }

            $imageWidth =
                (int)$imageInfo[0];

            $imageHeight =
                (int)$imageInfo[1];

            if (
                $imageWidth < 1 ||
                $imageHeight < 1 ||
                $imageWidth > 10000 ||
                $imageHeight > 10000
            ) {

                http_response_code(422);

                echo json_encode([
                    "status" => false,
                    "message" => "Image dimensions cannot exceed 10000 x 10000 pixels."
                ]);

                exit;
            }

            $newImageUploaded = true;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | IMAGE CONFLICT
    |--------------------------------------------------------------------------
    */

    if (
        $newImageUploaded &&
        $removeImage
    ) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "Do not send remove_image=1 when uploading a new image."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | DUPLICATE PACKAGE NAME
    |--------------------------------------------------------------------------
    */

    if (
        isset($_POST['name']) &&
        strtolower(trim($name)) !==
        strtolower(trim($existingPackage['name']))
    ) {

        $duplicateSql = "
            SELECT id
            FROM catering_packages
            WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))
              AND id != ?
            LIMIT 1
        ";

        $duplicateStmt =
            $conn->prepare($duplicateSql);

        if (!$duplicateStmt) {
            throw new Exception(
                "Failed to prepare duplicate package check."
            );
        }

        $duplicateStmt->bind_param(
            "si",
            $name,
            $packageId
        );

        if (!$duplicateStmt->execute()) {

            $duplicateStmt->close();

            throw new Exception(
                "Failed to check duplicate package name."
            );
        }

        $duplicateResult =
            $duplicateStmt->get_result();

        if ($duplicateResult->num_rows > 0) {

            $duplicateStmt->close();

            http_response_code(409);

            echo json_encode([
                "status" => false,
                "message" => "Another catering package with this name already exists."
            ]);

            exit;
        }

        $duplicateStmt->close();
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATE FOOD ITEMS
    |--------------------------------------------------------------------------
    */

    if ($itemsProvided && !empty($items)) {

        $foodItemCheckSql = "
            SELECT id
            FROM food_items
            WHERE id = ?
              AND status = 'active'
            LIMIT 1
        ";

        $foodItemCheckStmt =
            $conn->prepare($foodItemCheckSql);

        if (!$foodItemCheckStmt) {
            throw new Exception(
                "Failed to prepare food item validation."
            );
        }

        foreach ($items as $item) {

            $foodItemId =
                $item['food_item_id'];

            $foodItemCheckStmt->bind_param(
                "i",
                $foodItemId
            );

            if (!$foodItemCheckStmt->execute()) {

                $foodItemCheckStmt->close();

                throw new Exception(
                    "Failed to validate food item."
                );
            }

            $foodResult =
                $foodItemCheckStmt->get_result();

            if ($foodResult->num_rows === 0) {

                $foodItemCheckStmt->close();

                http_response_code(422);

                echo json_encode([
                    "status" => false,
                    "message" => "Food item does not exist or is inactive.",
                    "food_item_id" => $foodItemId
                ]);

                exit;
            }
        }

        $foodItemCheckStmt->close();
    }

    /*
    |--------------------------------------------------------------------------
    | START TRANSACTION
    |--------------------------------------------------------------------------
    */

    $conn->begin_transaction();

    try {

        /*
        |--------------------------------------------------------------------------
        | LOCK PACKAGE
        |--------------------------------------------------------------------------
        */

        $lockSql = "
            SELECT
                id,
                image_path
            FROM catering_packages
            WHERE id = ?
            FOR UPDATE
        ";

        $lockStmt =
            $conn->prepare($lockSql);

        if (!$lockStmt) {
            throw new Exception(
                "Failed to prepare package lock."
            );
        }

        $lockStmt->bind_param(
            "i",
            $packageId
        );

        if (!$lockStmt->execute()) {

            $lockStmt->close();

            throw new Exception(
                "Failed to lock catering package."
            );
        }

        $lockResult =
            $lockStmt->get_result();

        $lockedPackage =
            $lockResult->fetch_assoc();

        $lockStmt->close();

        if (!$lockedPackage) {

            throw new Exception(
                "Catering package no longer exists."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | OLD IMAGE PATH
        |--------------------------------------------------------------------------
        */

        $oldImagePath =
            $lockedPackage['image_path'];

        /*
        |--------------------------------------------------------------------------
        | UPDATE PACKAGE
        |--------------------------------------------------------------------------
        */

        $updateSql = "
            UPDATE catering_packages
            SET
                name = ?,
                description = ?,
                rate = ?,
                rate_unit = ?,
                gst_rate = ?,
                price_includes_gst = ?,
                minimum_quantity = ?,
                status = ?
            WHERE id = ?
        ";

        $updateStmt =
            $conn->prepare($updateSql);

        if (!$updateStmt) {

            throw new Exception(
                "Failed to prepare package update."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | CORRECT BIND TYPES
        |--------------------------------------------------------------------------
        |
        | name               -> s
        | description        -> s
        | rate               -> d
        | rate_unit          -> s
        | gst_rate           -> d
        | price_includes_gst -> i
        | minimum_quantity   -> i
        | status             -> s
        | package_id         -> i
        |
        | Total: 9 types / 9 variables
        |
        */

        $updateStmt->bind_param(
            "ssdsdiisi",
            $name,
            $description,
            $rate,
            $rateUnit,
            $gstRate,
            $priceIncludesGst,
            $minimumQuantity,
            $status,
            $packageId
        );

        if (!$updateStmt->execute()) {

            $errorMessage =
                $updateStmt->error;

            $updateStmt->close();

            throw new Exception(
                "Failed to update catering package: " .
                $errorMessage
            );
        }

        $updateStmt->close();

        /*
        |--------------------------------------------------------------------------
        | NEW IMAGE UPLOAD
        |--------------------------------------------------------------------------
        */

        if ($newImageUploaded) {

            $uploadDirectory =
                __DIR__ .
                "/../../uploads/catering_packages/" .
                $packageId .
                "/image/";

            if (!is_dir($uploadDirectory)) {

                if (!mkdir(
                    $uploadDirectory,
                    0755,
                    true
                )) {

                    throw new Exception(
                        "Failed to create image upload directory."
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | RANDOM FILE NAME
            |--------------------------------------------------------------------------
            */

            $extension =
                $allowedMimeTypes[$mimeType];

            $randomFileName =
                bin2hex(random_bytes(16)) .
                "." .
                $extension;

            $destination =
                $uploadDirectory .
                $randomFileName;

            if (!move_uploaded_file(
                $_FILES['image']['tmp_name'],
                $destination
            )) {

                throw new Exception(
                    "Failed to save uploaded image."
                );
            }

            $uploadedImageFullPath =
                $destination;

            /*
            |--------------------------------------------------------------------------
            | DATABASE IMAGE PATH
            |--------------------------------------------------------------------------
            */

            $newImagePath =
                "uploads/catering_packages/" .
                $packageId .
                "/image/" .
                $randomFileName;

            $imageUpdateSql = "
                UPDATE catering_packages
                SET image_path = ?
                WHERE id = ?
            ";

            $imageUpdateStmt =
                $conn->prepare($imageUpdateSql);

            if (!$imageUpdateStmt) {

                throw new Exception(
                    "Failed to prepare image path update."
                );
            }

            $imageUpdateStmt->bind_param(
                "si",
                $newImagePath,
                $packageId
            );

            if (!$imageUpdateStmt->execute()) {

                $imageError =
                    $imageUpdateStmt->error;

                $imageUpdateStmt->close();

                throw new Exception(
                    "Failed to update image path: " .
                    $imageError
                );
            }

            $imageUpdateStmt->close();
        }

        /*
        |--------------------------------------------------------------------------
        | REMOVE IMAGE
        |--------------------------------------------------------------------------
        */

        if ($removeImage) {

            $removeImageSql = "
                UPDATE catering_packages
                SET image_path = NULL
                WHERE id = ?
            ";

            $removeImageStmt =
                $conn->prepare($removeImageSql);

            if (!$removeImageStmt) {

                throw new Exception(
                    "Failed to prepare image removal."
                );
            }

            $removeImageStmt->bind_param(
                "i",
                $packageId
            );

            if (!$removeImageStmt->execute()) {

                $imageRemoveError =
                    $removeImageStmt->error;

                $removeImageStmt->close();

                throw new Exception(
                    "Failed to remove package image: " .
                    $imageRemoveError
                );
            }

            $removeImageStmt->close();

            $newImagePath = null;
        }

        /*
        |--------------------------------------------------------------------------
        | REPLACE PACKAGE ITEMS
        |--------------------------------------------------------------------------
        */

        if ($itemsProvided) {

            /*
            | Delete old mappings
            */

            $deleteItemsSql = "
                DELETE FROM catering_package_items
                WHERE package_id = ?
            ";

            $deleteItemsStmt =
                $conn->prepare($deleteItemsSql);

            if (!$deleteItemsStmt) {

                throw new Exception(
                    "Failed to prepare old package items deletion."
                );
            }

            $deleteItemsStmt->bind_param(
                "i",
                $packageId
            );

            if (!$deleteItemsStmt->execute()) {

                $deleteError =
                    $deleteItemsStmt->error;

                $deleteItemsStmt->close();

                throw new Exception(
                    "Failed to replace package items: " .
                    $deleteError
                );
            }

            $deleteItemsStmt->close();

            /*
            | Insert new mappings
            */

            if (!empty($items)) {

                $insertItemSql = "
                    INSERT INTO catering_package_items
                    (
                        package_id,
                        food_item_id,
                        quantity
                    )
                    VALUES (?, ?, ?)
                ";

                $insertItemStmt =
                    $conn->prepare($insertItemSql);

                if (!$insertItemStmt) {

                    throw new Exception(
                        "Failed to prepare package item insert."
                    );
                }

                foreach ($items as $item) {

                    $foodItemId =
                        $item['food_item_id'];

                    $quantity =
                        $item['quantity'];

                    $insertItemStmt->bind_param(
                        "iii",
                        $packageId,
                        $foodItemId,
                        $quantity
                    );

                    if (!$insertItemStmt->execute()) {

                        $itemError =
                            $insertItemStmt->error;

                        $insertItemStmt->close();

                        throw new Exception(
                            "Failed to insert package food item: " .
                            $itemError
                        );
                    }
                }

                $insertItemStmt->close();
            }
        }

        /*
        |--------------------------------------------------------------------------
        | COMMIT
        |--------------------------------------------------------------------------
        */

        $conn->commit();

    } catch (Throwable $transactionException) {

        $conn->rollback();

        /*
        |--------------------------------------------------------------------------
        | DELETE NEW IMAGE IF TRANSACTION FAILED
        |--------------------------------------------------------------------------
        */

        if (
            $uploadedImageFullPath !== null &&
            file_exists($uploadedImageFullPath)
        ) {

            @unlink(
                $uploadedImageFullPath
            );
        }

        throw $transactionException;
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE OLD PHYSICAL IMAGE
    |--------------------------------------------------------------------------
    */

    $oldImageDeleted = false;

    if (
        (
            $newImageUploaded ||
            $removeImage
        ) &&
        !empty($oldImagePath)
    ) {

        /*
        |--------------------------------------------------------------------------
        | SECURITY:
        | Only delete image from this package's directory.
        |--------------------------------------------------------------------------
        */

        $oldImageRelative =
            ltrim(
                $oldImagePath,
                '/\\'
            );

        $expectedPrefix =
            "uploads/catering_packages/" .
            $packageId .
            "/";

        if (
            strpos(
                $oldImageRelative,
                $expectedPrefix
            ) === 0
        ) {

            $oldImageFullPath =
                __DIR__ .
                "/../../" .
                $oldImageRelative;

            if (
                file_exists($oldImageFullPath) &&
                is_file($oldImageFullPath)
            ) {

                $oldImageDeleted =
                    @unlink(
                        $oldImageFullPath
                    );
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FETCH UPDATED PACKAGE
    |--------------------------------------------------------------------------
    */

    $fetchSql = "
        SELECT
            id,
            name,
            description,
            rate,
            rate_unit,
            gst_rate,
            price_includes_gst,
            minimum_quantity,
            image_path,
            status,
            created_at,
            updated_at
        FROM catering_packages
        WHERE id = ?
        LIMIT 1
    ";

    $fetchStmt =
        $conn->prepare($fetchSql);

    if (!$fetchStmt) {

        throw new Exception(
            "Failed to prepare updated package fetch."
        );
    }

    $fetchStmt->bind_param(
        "i",
        $packageId
    );

    if (!$fetchStmt->execute()) {

        $fetchStmt->close();

        throw new Exception(
            "Failed to fetch updated package."
        );
    }

    $fetchResult =
        $fetchStmt->get_result();

    $package =
        $fetchResult->fetch_assoc();

    $fetchStmt->close();

    if (!$package) {

        throw new Exception(
            "Updated package could not be fetched."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FETCH PACKAGE ITEMS
    |--------------------------------------------------------------------------
    */

    $packageItems = [];

    $itemsSql = "
        SELECT
            cpi.food_item_id,
            cpi.quantity,

            fi.name AS food_item_name,
            fi.description AS food_item_description,
            fi.food_type,
            fi.image_path AS food_item_image,

            fc.id AS food_category_id,
            fc.name AS food_category_name

        FROM catering_package_items cpi

        INNER JOIN food_items fi
            ON fi.id = cpi.food_item_id

        LEFT JOIN food_categories fc
            ON fc.id = fi.food_category_id

        WHERE cpi.package_id = ?

        ORDER BY fi.name ASC
    ";

    $itemsStmt =
        $conn->prepare($itemsSql);

    if (!$itemsStmt) {

        throw new Exception(
            "Failed to prepare package items fetch."
        );
    }

    $itemsStmt->bind_param(
        "i",
        $packageId
    );

    if (!$itemsStmt->execute()) {

        $itemsStmt->close();

        throw new Exception(
            "Failed to fetch package items."
        );
    }

    $itemsResult =
        $itemsStmt->get_result();

    while (
        $itemRow =
        $itemsResult->fetch_assoc()
    ) {

        $packageItems[] = [
            "food_item_id" =>
                (int)$itemRow['food_item_id'],

            "food_item_name" =>
                $itemRow['food_item_name'],

            "food_item_description" =>
                $itemRow['food_item_description'],

            "food_type" =>
                $itemRow['food_type'],

            "food_item_image" =>
                $itemRow['food_item_image'],

            "category" =>
                $itemRow['food_category_id'] !== null
                    ? [
                        "id" =>
                            (int)$itemRow['food_category_id'],

                        "name" =>
                            $itemRow['food_category_name']
                    ]
                    : null,

            "quantity" =>
                (int)$itemRow['quantity']
        ];
    }

    $itemsStmt->close();

    /*
    |--------------------------------------------------------------------------
    | SUCCESS RESPONSE
    |--------------------------------------------------------------------------
    */

    http_response_code(200);

    echo json_encode([
        "status" => true,
        "message" => "Catering package updated successfully.",

        "data" => [
            "id" =>
                (int)$package['id'],

            "name" =>
                $package['name'],

            "description" =>
                $package['description'],

            "rate" =>
                (float)$package['rate'],

            "rate_unit" =>
                $package['rate_unit'],

            "gst_rate" =>
                (float)$package['gst_rate'],

            "price_includes_gst" =>
                (bool)$package['price_includes_gst'],

            "minimum_quantity" =>
                (int)$package['minimum_quantity'],

            "image_path" =>
                $package['image_path'],

            "status" =>
                $package['status'],

            "items" =>
                $packageItems,

            "created_at" =>
                $package['created_at'],

            "updated_at" =>
                $package['updated_at']
        ],

        "image" => [
            "new_image_uploaded" =>
                $newImageUploaded,

            "old_image_deleted" =>
                $oldImageDeleted
        ]
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        "status" => false,
        "message" => "Failed to update catering package.",
        "error" => $e->getMessage()
    ]);
}