<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/middleware.php';

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
        "status" => false,
        "message" => "Only POST method is allowed."
    ]);

    exit;
}

try {

    /*
    |--------------------------------------------------------------------------
    | ADMIN / STAFF AUTHENTICATION
    |--------------------------------------------------------------------------
    */

    $currentUser = requireAdminOrStaff();

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
    | ALLOWED FORM FIELDS
    |--------------------------------------------------------------------------
    */

    $allowedFields = [
        'name',
        'description',
        'rate',
        'rate_unit',
        'gst_rate',
        'price_includes_gst',
        'minimum_quantity',
        'status',
        'items'
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
    | NAME
    |--------------------------------------------------------------------------
    */

    if (!isset($_POST['name'])) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "name is required."
        ]);

        exit;
    }

    $name = trim($_POST['name']);

    if ($name === '') {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "Name cannot be empty."
        ]);

        exit;
    }

    if (mb_strlen($name) < 2 || mb_strlen($name) > 150) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "Name must be between 2 and 150 characters."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | NAME CHARACTER VALIDATION
    |--------------------------------------------------------------------------
    */

    if (!preg_match("/^[A-Za-z0-9][A-Za-z0-9\s&.,'()\/+\-]*$/u", $name)) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "Name contains invalid characters."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | NO MULTIPLE SPACES
    |--------------------------------------------------------------------------
    */

    if (preg_match('/\s{2,}/', $name)) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "Name cannot contain multiple consecutive spaces."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | DESCRIPTION
    |--------------------------------------------------------------------------
    */

    $description = null;

    if (isset($_POST['description'])) {

        $description = trim($_POST['description']);

        if ($description !== '' && mb_strlen($description) > 5000) {

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

    if (!isset($_POST['rate'])) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "rate is required."
        ]);

        exit;
    }

    $rateRaw = trim($_POST['rate']);

    /*
    | Allow:
    | 0
    | 100
    | 100.50
    */

    if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $rateRaw)) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "Rate must be a valid positive decimal with maximum 2 decimal places."
        ]);

        exit;
    }

    $rate = (float)$rateRaw;

    if ($rate < 0) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "Rate cannot be negative."
        ]);

        exit;
    }

    if ($rate > 9999999999.99) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "Rate is too large."
        ]);

        exit;
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

    $rateUnit = 'per_person';

    if (isset($_POST['rate_unit']) && trim($_POST['rate_unit']) !== '') {

        $rateUnit = trim($_POST['rate_unit']);

        if (!in_array($rateUnit, $allowedRateUnits, true)) {

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

    $gstRate = 18.00;

    if (isset($_POST['gst_rate']) && trim($_POST['gst_rate']) !== '') {

        $gstRateRaw = trim($_POST['gst_rate']);

        if (!preg_match('/^\d{1,3}(\.\d{1,2})?$/', $gstRateRaw)) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "gst_rate must be a valid decimal with maximum 2 decimal places."
            ]);

            exit;
        }

        $gstRate = (float)$gstRateRaw;

        if ($gstRate < 0 || $gstRate > 100) {

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

    $priceIncludesGst = 1;

    if (isset($_POST['price_includes_gst'])) {

        $priceIncludesGstRaw = trim($_POST['price_includes_gst']);

        if (!in_array($priceIncludesGstRaw, ['0', '1'], true)) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "price_includes_gst must be either 0 or 1."
            ]);

            exit;
        }

        $priceIncludesGst = (int)$priceIncludesGstRaw;
    }

    /*
    |--------------------------------------------------------------------------
    | MINIMUM QUANTITY
    |--------------------------------------------------------------------------
    */

    $minimumQuantity = 1;

    if (
        isset($_POST['minimum_quantity']) &&
        trim($_POST['minimum_quantity']) !== ''
    ) {

        $minimumQuantityRaw = trim($_POST['minimum_quantity']);

        if (!ctype_digit($minimumQuantityRaw)) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "minimum_quantity must be a positive integer."
            ]);

            exit;
        }

        $minimumQuantity = (int)$minimumQuantityRaw;

        if ($minimumQuantity < 1 || $minimumQuantity > 100000) {

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

    $status = 'active';

    if (isset($_POST['status']) && trim($_POST['status']) !== '') {

        $status = trim($_POST['status']);

        if (!in_array($status, $allowedStatuses, true)) {

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
    | ITEMS VALIDATION
    |--------------------------------------------------------------------------
    */

    $items = [];

    if (isset($_POST['items'])) {

        $itemsRaw = trim($_POST['items']);

        if ($itemsRaw === '') {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "items cannot be empty."
            ]);

            exit;
        }

        $decodedItems = json_decode($itemsRaw, true);

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

                if (!in_array($itemKey, ['food_item_id', 'quantity'], true)) {

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

            if (
                !isset($item['food_item_id']) ||
                !is_int($item['food_item_id']) &&
                !ctype_digit((string)$item['food_item_id'])
            ) {

                http_response_code(422);

                echo json_encode([
                    "status" => false,
                    "message" => "food_item_id is required and must be a positive integer.",
                    "item_index" => $index
                ]);

                exit;
            }

            $foodItemId = (int)$item['food_item_id'];

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

            if (in_array($foodItemId, $foodItemIds, true)) {

                http_response_code(422);

                echo json_encode([
                    "status" => false,
                    "message" => "Duplicate food_item_id is not allowed.",
                    "food_item_id" => $foodItemId
                ]);

                exit;
            }

            $foodItemIds[] = $foodItemId;

            /*
            |--------------------------------------------------------------------------
            | QUANTITY
            |--------------------------------------------------------------------------
            */

            $quantity = 1;

            if (isset($item['quantity'])) {

                if (
                    !is_int($item['quantity']) &&
                    !ctype_digit((string)$item['quantity'])
                ) {

                    http_response_code(422);

                    echo json_encode([
                        "status" => false,
                        "message" => "quantity must be a positive integer.",
                        "item_index" => $index
                    ]);

                    exit;
                }

                $quantity = (int)$item['quantity'];
            }

            if ($quantity < 1 || $quantity > 100000) {

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
    | IMAGE VALIDATION
    |--------------------------------------------------------------------------
    */

    $imagePath = null;
    $uploadedImageFullPath = null;

    if (isset($_FILES['image'])) {

        $image = $_FILES['image'];

        if (!isset($image['error']) || is_array($image['error'])) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "Invalid image upload."
            ]);

            exit;
        }

        if ($image['error'] !== UPLOAD_ERR_OK) {

            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE =>
                    "Uploaded image exceeds server upload limit.",

                UPLOAD_ERR_FORM_SIZE =>
                    "Uploaded image exceeds form size limit.",

                UPLOAD_ERR_PARTIAL =>
                    "Image upload was incomplete.",

                UPLOAD_ERR_NO_FILE =>
                    "No image was uploaded.",

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
                "message" => $uploadErrors[$image['error']]
                    ?? "Image upload failed."
            ]);

            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | MAX IMAGE SIZE
        |--------------------------------------------------------------------------
        */

        $maxImageSize = 5 * 1024 * 1024;

        if ($image['size'] <= 0 || $image['size'] > $maxImageSize) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "Image size must be between 1 byte and 5 MB."
            ]);

            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | MIME VALIDATION
        |--------------------------------------------------------------------------
        */

        $finfo = new finfo(FILEINFO_MIME_TYPE);

        $mimeType = $finfo->file($image['tmp_name']);

        $allowedMimeTypes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];

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
        | IMAGE DIMENSION VALIDATION
        |--------------------------------------------------------------------------
        */

        $imageInfo = @getimagesize($image['tmp_name']);

        if ($imageInfo === false) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "Uploaded file is not a valid image."
            ]);

            exit;
        }

        $imageWidth = $imageInfo[0];
        $imageHeight = $imageInfo[1];

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

    } elseif (
        isset($_FILES['image']) &&
        $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE
    ) {

        $imagePath = null;
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK DUPLICATE PACKAGE NAME
    |--------------------------------------------------------------------------
    */

    $duplicateSql = "
        SELECT id
        FROM catering_packages
        WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))
        LIMIT 1
    ";

    $duplicateStmt = $conn->prepare($duplicateSql);

    if (!$duplicateStmt) {
        throw new Exception("Failed to prepare duplicate package check.");
    }

    $duplicateStmt->bind_param("s", $name);

    if (!$duplicateStmt->execute()) {

        $duplicateStmt->close();

        throw new Exception("Failed to check duplicate package name.");
    }

    $duplicateResult = $duplicateStmt->get_result();

    if ($duplicateResult->num_rows > 0) {

        $duplicateStmt->close();

        http_response_code(409);

        echo json_encode([
            "status" => false,
            "message" => "A catering package with this name already exists."
        ]);

        exit;
    }

    $duplicateStmt->close();

    /*
    |--------------------------------------------------------------------------
    | VALIDATE ALL FOOD ITEMS BEFORE INSERT
    |--------------------------------------------------------------------------
    */

    if (!empty($items)) {

        $foodItemCheckSql = "
            SELECT id
            FROM food_items
            WHERE id = ?
              AND status = 'active'
            LIMIT 1
        ";

        $foodItemCheckStmt = $conn->prepare($foodItemCheckSql);

        if (!$foodItemCheckStmt) {
            throw new Exception("Failed to prepare food item validation.");
        }

        foreach ($items as $item) {

            $foodItemId = $item['food_item_id'];

            $foodItemCheckStmt->bind_param(
                "i",
                $foodItemId
            );

            if (!$foodItemCheckStmt->execute()) {

                $foodItemCheckStmt->close();

                throw new Exception("Failed to validate food item.");
            }

            $foodResult = $foodItemCheckStmt->get_result();

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
        | INSERT PACKAGE
        |--------------------------------------------------------------------------
        */

        $insertSql = "
            INSERT INTO catering_packages
            (
                name,
                description,
                rate,
                rate_unit,
                gst_rate,
                price_includes_gst,
                minimum_quantity,
                image_path,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $insertStmt = $conn->prepare($insertSql);

        if (!$insertStmt) {
            throw new Exception("Failed to prepare catering package insert.");
        }

        /*
        | Image path will be assigned after package ID is generated.
        | Insert initially with NULL image_path.
        */

        $insertStmt->bind_param(
            "ssdssdiss",
            $name,
            $description,
            $rate,
            $rateUnit,
            $gstRate,
            $priceIncludesGst,
            $minimumQuantity,
            $imagePath,
            $status
        );

        if (!$insertStmt->execute()) {

            $insertStmt->close();

            throw new Exception(
                "Failed to create catering package: " .
                $conn->error
            );
        }

        $packageId = (int)$conn->insert_id;

        $insertStmt->close();

        /*
        |--------------------------------------------------------------------------
        | IMAGE SAVE
        |--------------------------------------------------------------------------
        */

        if (isset($_FILES['image'])) {

            $image = $_FILES['image'];

            /*
            | Create:
            | uploads/catering_packages/{packageId}/image/
            */

            $uploadDirectory =
                __DIR__ .
                "/../../uploads/catering_packages/" .
                $packageId .
                "/image/";

            if (!is_dir($uploadDirectory)) {

                if (!mkdir($uploadDirectory, 0755, true)) {

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

            $extension = $allowedMimeTypes[$mimeType];

            $randomFileName =
                bin2hex(random_bytes(16)) .
                "." .
                $extension;

            $destination =
                $uploadDirectory .
                $randomFileName;

            if (!move_uploaded_file(
                $image['tmp_name'],
                $destination
            )) {

                throw new Exception(
                    "Failed to save uploaded image."
                );
            }

            $uploadedImageFullPath = $destination;

            /*
            | DB path
            */

            $imagePath =
                "uploads/catering_packages/" .
                $packageId .
                "/image/" .
                $randomFileName;

            /*
            |--------------------------------------------------------------------------
            | UPDATE IMAGE PATH
            |--------------------------------------------------------------------------
            */

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
                $imagePath,
                $packageId
            );

            if (!$imageUpdateStmt->execute()) {

                $imageUpdateStmt->close();

                throw new Exception(
                    "Failed to update image path."
                );
            }

            $imageUpdateStmt->close();
        }

        /*
        |--------------------------------------------------------------------------
        | INSERT PACKAGE ITEMS
        |--------------------------------------------------------------------------
        */

        if (!empty($items)) {

            $itemInsertSql = "
                INSERT INTO catering_package_items
                (
                    package_id,
                    food_item_id,
                    quantity
                )
                VALUES (?, ?, ?)
            ";

            $itemInsertStmt =
                $conn->prepare($itemInsertSql);

            if (!$itemInsertStmt) {
                throw new Exception(
                    "Failed to prepare package item insert."
                );
            }

            foreach ($items as $item) {

                $foodItemId = $item['food_item_id'];
                $quantity = $item['quantity'];

                $itemInsertStmt->bind_param(
                    "iii",
                    $packageId,
                    $foodItemId,
                    $quantity
                );

                if (!$itemInsertStmt->execute()) {

                    $itemInsertStmt->close();

                    throw new Exception(
                        "Failed to insert catering package food item."
                    );
                }
            }

            $itemInsertStmt->close();
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
        | Delete image if transaction failed
        */

        if (
            $uploadedImageFullPath !== null &&
            file_exists($uploadedImageFullPath)
        ) {
            @unlink($uploadedImageFullPath);
        }

        throw $transactionException;
    }

    /*
    |--------------------------------------------------------------------------
    | FETCH CREATED PACKAGE
    |--------------------------------------------------------------------------
    */

    $packageSql = "
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

    $packageStmt = $conn->prepare($packageSql);

    if (!$packageStmt) {
        throw new Exception(
            "Failed to prepare created package fetch."
        );
    }

    $packageStmt->bind_param(
        "i",
        $packageId
    );

    if (!$packageStmt->execute()) {

        $packageStmt->close();

        throw new Exception(
            "Failed to fetch created package."
        );
    }

    $packageResult = $packageStmt->get_result();

    $package = $packageResult->fetch_assoc();

    $packageStmt->close();

    if (!$package) {

        throw new Exception(
            "Catering package was created but could not be fetched."
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

    $itemsStmt = $conn->prepare($itemsSql);

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

    $itemsResult = $itemsStmt->get_result();

    while ($itemRow = $itemsResult->fetch_assoc()) {

        $packageItems[] = [
            "food_item_id" => (int)$itemRow['food_item_id'],
            "food_item_name" => $itemRow['food_item_name'],
            "food_item_description" => $itemRow['food_item_description'],
            "food_type" => $itemRow['food_type'],
            "food_item_image" => $itemRow['food_item_image'],

            "category" => $itemRow['food_category_id'] !== null
                ? [
                    "id" => (int)$itemRow['food_category_id'],
                    "name" => $itemRow['food_category_name']
                ]
                : null,

            "quantity" => (int)$itemRow['quantity']
        ];
    }

    $itemsStmt->close();

    /*
    |--------------------------------------------------------------------------
    | RESPONSE DATA
    |--------------------------------------------------------------------------
    */

    $responsePackage = [
        "id" => (int)$package['id'],
        "name" => $package['name'],
        "description" => $package['description'],

        "rate" => (float)$package['rate'],
        "rate_unit" => $package['rate_unit'],

        "gst_rate" => (float)$package['gst_rate'],
        "price_includes_gst" => (bool)$package['price_includes_gst'],

        "minimum_quantity" =>
            (int)$package['minimum_quantity'],

        "image_path" => $package['image_path'],

        "status" => $package['status'],

        "items" => $packageItems,

        "created_at" => $package['created_at'],
        "updated_at" => $package['updated_at']
    ];

    /*
    |--------------------------------------------------------------------------
    | SUCCESS RESPONSE
    |--------------------------------------------------------------------------
    */

    http_response_code(201);

    echo json_encode([
        "status" => true,
        "message" => "Catering package created successfully.",
        "data" => $responsePackage
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        "status" => false,
        "message" => "Failed to create catering package.",
        "error" => $e->getMessage()
    ]);
}