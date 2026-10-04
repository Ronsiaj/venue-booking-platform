<?php

require_once __DIR__ . '/../../config/database.php';

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
    | DATABASE CONNECTION
    |--------------------------------------------------------------------------
    */

    global $conn;

    if (
        !isset($conn) ||
        !($conn instanceof mysqli)
    ) {
        throw new Exception("Database connection is not available.");
    }

    /*
    |--------------------------------------------------------------------------
    | CONTENT TYPE
    |--------------------------------------------------------------------------
    */

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (
        stripos($contentType, 'application/json') === false
    ) {

        http_response_code(400);

        echo json_encode([
            "status" => false,
            "message" => "Content-Type must be application/json."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | READ JSON BODY
    |--------------------------------------------------------------------------
    */

    $rawBody = file_get_contents("php://input");

    if ($rawBody === false) {

        http_response_code(400);

        echo json_encode([
            "status" => false,
            "message" => "Unable to read request body."
        ]);

        exit;
    }

    if (trim($rawBody) === '') {

        http_response_code(400);

        echo json_encode([
            "status" => false,
            "message" => "Request body cannot be empty."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | DECODE JSON
    |--------------------------------------------------------------------------
    */

    $data = json_decode($rawBody, true);

    if (
        json_last_error() !== JSON_ERROR_NONE ||
        !is_array($data)
    ) {

        http_response_code(400);

        echo json_encode([
            "status" => false,
            "message" => "Request body must contain valid JSON."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | ALLOWED FIELDS
    |--------------------------------------------------------------------------
    */

    $allowedFields = [
        'customer_name',
        'customer_phone',
        'customer_email',
        'catering_package_id',
        'event_type',
        'event_date',
        'event_time',
        'guest_count',
        'venue_address',
        'estimated_amount',
        'other_requirements',
        'items'
    ];

    foreach ($data as $key => $value) {

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
    | CUSTOMER NAME
    |--------------------------------------------------------------------------
    */

    if (!isset($data['customer_name'])) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "customer_name is required."
        ]);

        exit;
    }

    $customerName = trim((string)$data['customer_name']);

    if ($customerName === '') {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "customer_name cannot be empty."
        ]);

        exit;
    }

    if (
        mb_strlen($customerName) < 2 ||
        mb_strlen($customerName) > 150
    ) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "customer_name must be between 2 and 150 characters."
        ]);

        exit;
    }

    if (!preg_match(
        "/^[A-Za-zÀ-ÖØ-öø-ÿ.'\- ]+$/u",
        $customerName
    )) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "customer_name contains invalid characters."
        ]);

        exit;
    }

    if (preg_match('/\s{2,}/', $customerName)) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "customer_name cannot contain multiple consecutive spaces."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER PHONE
    |--------------------------------------------------------------------------
    */

    if (!isset($data['customer_phone'])) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "customer_phone is required."
        ]);

        exit;
    }

    $customerPhone = trim((string)$data['customer_phone']);

    if (!preg_match('/^\d{10,15}$/', $customerPhone)) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "customer_phone must contain 10 to 15 digits only."
        ]);

        exit;
    }

    if (preg_match('/^(\d)\1+$/', $customerPhone)) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "Invalid customer_phone."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER EMAIL
    |--------------------------------------------------------------------------
    */

    $customerEmail = null;

    if (
        isset($data['customer_email']) &&
        $data['customer_email'] !== null
    ) {

        $customerEmail = strtolower(
            trim((string)$data['customer_email'])
        );

        if ($customerEmail === '') {

            $customerEmail = null;

        } else {

            if (mb_strlen($customerEmail) > 150) {

                http_response_code(422);

                echo json_encode([
                    "status" => false,
                    "message" => "customer_email cannot exceed 150 characters."
                ]);

                exit;
            }

            if (!filter_var(
                $customerEmail,
                FILTER_VALIDATE_EMAIL
            )) {

                http_response_code(422);

                echo json_encode([
                    "status" => false,
                    "message" => "Invalid customer_email."
                ]);

                exit;
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CATERING PACKAGE ID
    |--------------------------------------------------------------------------
    */

    $cateringPackageId = null;

    if (
        isset($data['catering_package_id']) &&
        $data['catering_package_id'] !== null &&
        trim((string)$data['catering_package_id']) !== ''
    ) {

        $packageIdRaw = trim(
            (string)$data['catering_package_id']
        );

        if (
            !ctype_digit($packageIdRaw) ||
            (int)$packageIdRaw < 1
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "catering_package_id must be a positive integer."
            ]);

            exit;
        }

        $cateringPackageId = (int)$packageIdRaw;
    }

    /*
    |--------------------------------------------------------------------------
    | EVENT TYPE
    |--------------------------------------------------------------------------
    */

    $eventType = null;

    if (
        isset($data['event_type']) &&
        $data['event_type'] !== null
    ) {

        $eventType = trim(
            (string)$data['event_type']
        );

        if ($eventType !== '') {

            if (mb_strlen($eventType) > 100) {

                http_response_code(422);

                echo json_encode([
                    "status" => false,
                    "message" => "event_type cannot exceed 100 characters."
                ]);

                exit;
            }

            if (preg_match('/\s{2,}/', $eventType)) {

                http_response_code(422);

                echo json_encode([
                    "status" => false,
                    "message" => "event_type cannot contain multiple consecutive spaces."
                ]);

                exit;
            }

        } else {

            $eventType = null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | EVENT DATE
    |--------------------------------------------------------------------------
    */

    $eventDate = null;

    if (
        isset($data['event_date']) &&
        $data['event_date'] !== null &&
        trim((string)$data['event_date']) !== ''
    ) {

        $eventDate = trim(
            (string)$data['event_date']
        );

        if (!preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $eventDate
        )) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "event_date must be in YYYY-MM-DD format."
            ]);

            exit;
        }

        $dateObject = DateTime::createFromFormat(
            'Y-m-d',
            $eventDate
        );

        $dateErrors = DateTime::getLastErrors();

        if (
            $dateObject === false ||
            (
                is_array($dateErrors) &&
                (
                    $dateErrors['warning_count'] > 0 ||
                    $dateErrors['error_count'] > 0
                )
            ) ||
            $dateObject->format('Y-m-d') !== $eventDate
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "Invalid event_date."
            ]);

            exit;
        }

        $today = new DateTime(
            date('Y-m-d')
        );

        if ($dateObject < $today) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "event_date cannot be in the past."
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | EVENT TIME
    |--------------------------------------------------------------------------
    */

    $eventTime = null;

    if (
        isset($data['event_time']) &&
        $data['event_time'] !== null
    ) {

        $eventTime = trim(
            (string)$data['event_time']
        );

        if ($eventTime !== '') {

            if (mb_strlen($eventTime) > 100) {

                http_response_code(422);

                echo json_encode([
                    "status" => false,
                    "message" => "event_time cannot exceed 100 characters."
                ]);

                exit;
            }

            if (preg_match('/\s{2,}/', $eventTime)) {

                http_response_code(422);

                echo json_encode([
                    "status" => false,
                    "message" => "event_time cannot contain multiple consecutive spaces."
                ]);

                exit;
            }

        } else {

            $eventTime = null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | GUEST COUNT
    |--------------------------------------------------------------------------
    */

    $guestCount = null;

    if (
        isset($data['guest_count']) &&
        $data['guest_count'] !== null &&
        trim((string)$data['guest_count']) !== ''
    ) {

        $guestCountRaw = trim(
            (string)$data['guest_count']
        );

        if (!ctype_digit($guestCountRaw)) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "guest_count must be a positive integer."
            ]);

            exit;
        }

        $guestCount = (int)$guestCountRaw;

        if (
            $guestCount < 1 ||
            $guestCount > 100000
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "guest_count must be between 1 and 100000."
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | VENUE ADDRESS
    |--------------------------------------------------------------------------
    */

    $venueAddress = null;

    if (
        isset($data['venue_address']) &&
        $data['venue_address'] !== null
    ) {

        $venueAddress = trim(
            (string)$data['venue_address']
        );

        if ($venueAddress !== '') {

            if (mb_strlen($venueAddress) > 5000) {

                http_response_code(422);

                echo json_encode([
                    "status" => false,
                    "message" => "venue_address cannot exceed 5000 characters."
                ]);

                exit;
            }

        } else {

            $venueAddress = null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | OTHER REQUIREMENTS
    |--------------------------------------------------------------------------
    */

    $otherRequirements = null;

    if (
        isset($data['other_requirements']) &&
        $data['other_requirements'] !== null
    ) {

        $otherRequirements = trim(
            (string)$data['other_requirements']
        );

        if ($otherRequirements !== '') {

            if (mb_strlen($otherRequirements) > 5000) {

                http_response_code(422);

                echo json_encode([
                    "status" => false,
                    "message" => "other_requirements cannot exceed 5000 characters."
                ]);

                exit;
            }

        } else {

            $otherRequirements = null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ESTIMATED AMOUNT
    |--------------------------------------------------------------------------
    */

    $requestedEstimatedAmount = null;

    if (
        isset($data['estimated_amount']) &&
        $data['estimated_amount'] !== null &&
        trim((string)$data['estimated_amount']) !== ''
    ) {

        $estimatedAmountRaw = trim(
            (string)$data['estimated_amount']
        );

        if (!preg_match(
            '/^\d{1,10}(\.\d{1,2})?$/',
            $estimatedAmountRaw
        )) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "estimated_amount must be a valid decimal with maximum 2 decimal places."
            ]);

            exit;
        }

        $requestedEstimatedAmount =
            (float)$estimatedAmountRaw;

        if (
            $requestedEstimatedAmount < 0 ||
            $requestedEstimatedAmount > 9999999999.99
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "estimated_amount is out of allowed range."
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CUSTOM FOOD ITEMS
    |--------------------------------------------------------------------------
    */

    $items = [];

    if (isset($data['items'])) {

        if (!is_array($data['items'])) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "items must be an array."
            ]);

            exit;
        }

        if (count($data['items']) > 100) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "Maximum 100 custom food items are allowed."
            ]);

            exit;
        }

        $foodItemIds = [];

        foreach ($data['items'] as $index => $item) {

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
                    [
                        'food_item_id',
                        'quantity',
                        'notes'
                    ],
                    true
                )) {

                    http_response_code(400);

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

            $foodItemIdRaw = trim(
                (string)$item['food_item_id']
            );

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

            $foodItemId = (int)$foodItemIdRaw;

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

            $foodItemIds[] = $foodItemId;

            /*
            |--------------------------------------------------------------------------
            | QUANTITY
            |--------------------------------------------------------------------------
            */

            $quantity = 1;

            if (
                array_key_exists(
                    'quantity',
                    $item
                )
            ) {

                $quantityRaw = trim(
                    (string)$item['quantity']
                );

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

                $quantity = (int)$quantityRaw;
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

            /*
            |--------------------------------------------------------------------------
            | NOTES
            |--------------------------------------------------------------------------
            */

            $notes = null;

            if (
                isset($item['notes']) &&
                $item['notes'] !== null
            ) {

                $notes = trim(
                    (string)$item['notes']
                );

                if ($notes !== '') {

                    if (mb_strlen($notes) > 2000) {

                        http_response_code(422);

                        echo json_encode([
                            "status" => false,
                            "message" => "notes cannot exceed 2000 characters.",
                            "item_index" => $index
                        ]);

                        exit;
                    }

                } else {

                    $notes = null;
                }
            }

            $items[] = [
                "food_item_id" => $foodItemId,
                "quantity" => $quantity,
                "notes" => $notes
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | BUSINESS VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        $cateringPackageId === null &&
        $eventType === null &&
        $eventDate === null &&
        $eventTime === null &&
        $guestCount === null &&
        $venueAddress === null &&
        $otherRequirements === null &&
        empty($items)
    ) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "At least one catering requirement or package detail is required."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | FETCH CATERING PACKAGE
    |--------------------------------------------------------------------------
    */

    $package = null;

    if ($cateringPackageId !== null) {

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
                status
            FROM catering_packages
            WHERE id = ?
            LIMIT 1
        ";

        $packageStmt = $conn->prepare($packageSql);

        if (!$packageStmt) {
            throw new Exception(
                "Failed to prepare catering package validation."
            );
        }

        $packageStmt->bind_param(
            "i",
            $cateringPackageId
        );

        if (!$packageStmt->execute()) {

            $error = $packageStmt->error;

            $packageStmt->close();

            throw new Exception(
                "Failed to validate catering package: " . $error
            );
        }

        $packageResult = $packageStmt->get_result();

        $package = $packageResult->fetch_assoc();

        $packageStmt->close();

        if (!$package) {

            http_response_code(404);

            echo json_encode([
                "status" => false,
                "message" => "Catering package not found.",
                "catering_package_id" => $cateringPackageId
            ]);

            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | PACKAGE MUST BE ACTIVE
        |--------------------------------------------------------------------------
        */

        if ($package['status'] !== 'active') {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "Selected catering package is inactive.",
                "catering_package_id" => $cateringPackageId
            ]);

            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | MINIMUM QUANTITY
        |--------------------------------------------------------------------------
        */

        $minimumQuantity = (int)$package['minimum_quantity'];

        if (
            $guestCount !== null &&
            $minimumQuantity > 0 &&
            $guestCount < $minimumQuantity
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "guest_count is below the minimum quantity required for the selected package.",
                "minimum_quantity" => $minimumQuantity,
                "guest_count" => $guestCount
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATE CUSTOM FOOD ITEMS
    |--------------------------------------------------------------------------
    */

    if (!empty($items)) {

        $foodValidationSql = "
            SELECT
                id,
                name,
                food_type,
                status
            FROM food_items
            WHERE id = ?
            LIMIT 1
        ";

        $foodValidationStmt = $conn->prepare(
            $foodValidationSql
        );

        if (!$foodValidationStmt) {
            throw new Exception(
                "Failed to prepare food item validation."
            );
        }

        foreach ($items as $item) {

            $foodItemId = $item['food_item_id'];

            $foodValidationStmt->bind_param(
                "i",
                $foodItemId
            );

            if (!$foodValidationStmt->execute()) {

                $error = $foodValidationStmt->error;

                $foodValidationStmt->close();

                throw new Exception(
                    "Failed to validate food item: " . $error
                );
            }

            $foodResult =
                $foodValidationStmt->get_result();

            $foodRow =
                $foodResult->fetch_assoc();

            if (!$foodRow) {

                $foodValidationStmt->close();

                http_response_code(422);

                echo json_encode([
                    "status" => false,
                    "message" => "Food item does not exist.",
                    "food_item_id" => $foodItemId
                ]);

                exit;
            }

            if ($foodRow['status'] !== 'active') {

                $foodValidationStmt->close();

                http_response_code(422);

                echo json_encode([
                    "status" => false,
                    "message" => "Selected food item is inactive.",
                    "food_item_id" => $foodItemId
                ]);

                exit;
            }
        }

        $foodValidationStmt->close();
    }

    /*
    |--------------------------------------------------------------------------
    | ESTIMATED AMOUNT CALCULATION
    |--------------------------------------------------------------------------
    */

    $calculatedEstimatedAmount = null;

    if (
        $package !== null &&
        $guestCount !== null
    ) {

        $baseAmount = 0;

        $packageRate =
            (float)$package['rate'];

        $rateUnit =
            strtolower(trim((string)$package['rate_unit']));

        /*
        |--------------------------------------------------------------------------
        | PER PERSON / PER PLATE
        |--------------------------------------------------------------------------
        */

        if (
            $rateUnit === 'per_person' ||
            $rateUnit === 'per_plate'
        ) {

            $baseAmount =
                $packageRate * $guestCount;

        } else {

            /*
            |--------------------------------------------------------------------------
            | EVENT / PACKAGE BASED
            |--------------------------------------------------------------------------
            */

            $baseAmount = $packageRate;
        }

        /*
        |--------------------------------------------------------------------------
        | GST
        |--------------------------------------------------------------------------
        */

        if (!(bool)$package['price_includes_gst']) {

            $gstRate =
                (float)$package['gst_rate'];

            $gstAmount =
                ($baseAmount * $gstRate) / 100;

            $baseAmount += $gstAmount;
        }

        $calculatedEstimatedAmount =
            round($baseAmount, 2);
    }

    /*
    |--------------------------------------------------------------------------
    | FINAL ESTIMATED AMOUNT
    |--------------------------------------------------------------------------
    */

    $estimatedAmount =
        $requestedEstimatedAmount;

    if (
        $estimatedAmount === null &&
        $calculatedEstimatedAmount !== null
    ) {

        $estimatedAmount =
            $calculatedEstimatedAmount;
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE LEAD REFERENCE
    |--------------------------------------------------------------------------
    */

    $leadId = null;
    $leadReference = null;

    /*
    |--------------------------------------------------------------------------
    | START TRANSACTION
    |--------------------------------------------------------------------------
    */

    $conn->begin_transaction();

    try {

        /*
        |--------------------------------------------------------------------------
        | GENERATE UNIQUE LEAD REFERENCE
        |--------------------------------------------------------------------------
        */

        $referenceGenerated = false;

        for ($attempt = 1; $attempt <= 10; $attempt++) {

            $randomPart = strtoupper(
                bin2hex(
                    random_bytes(4)
                )
            );

            $leadReference =
                'CAT-' .
                date('Ymd') .
                '-' .
                $randomPart;

            $checkReferenceSql = "
                SELECT id
                FROM leads
                WHERE lead_reference = ?
                LIMIT 1
            ";

            $checkReferenceStmt =
                $conn->prepare(
                    $checkReferenceSql
                );

            if (!$checkReferenceStmt) {
                throw new Exception(
                    "Failed to prepare lead reference validation."
                );
            }

            $checkReferenceStmt->bind_param(
                "s",
                $leadReference
            );

            if (!$checkReferenceStmt->execute()) {

                $error =
                    $checkReferenceStmt->error;

                $checkReferenceStmt->close();

                throw new Exception(
                    "Failed to validate lead reference: " . $error
                );
            }

            $referenceResult =
                $checkReferenceStmt->get_result();

            $referenceExists =
                $referenceResult->num_rows > 0;

            $checkReferenceStmt->close();

            if (!$referenceExists) {

                $referenceGenerated = true;

                break;
            }
        }

        if (!$referenceGenerated) {

            throw new Exception(
                "Unable to generate unique lead reference."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | INSERT GENERIC LEAD
        |--------------------------------------------------------------------------
        */

        $leadSql = "
            INSERT INTO leads
            (
                lead_reference,
                domain,
                customer_name,
                customer_phone,
                customer_email,
                event_date,
                guest_count,
                preferred_time,
                requirement,
                status,
                estimated_amount,
                converted_amount,
                converted_at,
                source,
                assigned_admin_id
            )
            VALUES
            (
                ?,
                'catering',
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                'new',
                ?,
                NULL,
                NULL,
                'website',
                NULL
            )
        ";

        $leadStmt =
            $conn->prepare($leadSql);

        if (!$leadStmt) {

            throw new Exception(
                "Failed to prepare lead creation: " .
                $conn->error
            );
        }

        /*
        |--------------------------------------------------------------------------
        | CORRECT BIND PARAM
        |--------------------------------------------------------------------------
        |
        | lead_reference    = s
        | customer_name     = s
        | customer_phone    = s
        | customer_email    = s
        | event_date        = s
        | guest_count       = i
        | preferred_time    = s
        | requirement       = s
        | estimated_amount  = d
        |
        | TOTAL = 9
        |
        | Correct type string:
        | sssssissd
        |
        */

        $leadStmt->bind_param(
            "sssssissd",
            $leadReference,
            $customerName,
            $customerPhone,
            $customerEmail,
            $eventDate,
            $guestCount,
            $eventTime,
            $otherRequirements,
            $estimatedAmount
        );

        if (!$leadStmt->execute()) {

            $error =
                $leadStmt->error;

            $leadStmt->close();

            throw new Exception(
                "Failed to create catering lead: " .
                $error
            );
        }

        $leadId =
            $conn->insert_id;

        $leadStmt->close();

        /*
        |--------------------------------------------------------------------------
        | CATERING LEAD DETAILS
        |--------------------------------------------------------------------------
        */

        $detailsSql = "
            INSERT INTO catering_lead_details
            (
                lead_id,
                catering_package_id,
                event_type,
                event_date,
                event_time,
                guest_count,
                venue_address,
                estimated_amount,
                other_requirements
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ";

        $detailsStmt =
            $conn->prepare($detailsSql);

        if (!$detailsStmt) {

            throw new Exception(
                "Failed to prepare catering lead details: " .
                $conn->error
            );
        }

        /*
        |--------------------------------------------------------------------------
        | BIND
        |--------------------------------------------------------------------------
        |
        | i lead_id
        | i catering_package_id
        | s event_type
        | s event_date
        | s event_time
        | i guest_count
        | s venue_address
        | d estimated_amount
        | s other_requirements
        |
        */

        $detailsStmt->bind_param(
            "iisssisds",
            $leadId,
            $cateringPackageId,
            $eventType,
            $eventDate,
            $eventTime,
            $guestCount,
            $venueAddress,
            $estimatedAmount,
            $otherRequirements
        );

        if (!$detailsStmt->execute()) {

            $error =
                $detailsStmt->error;

            $detailsStmt->close();

            throw new Exception(
                "Failed to create catering lead details: " .
                $error
            );
        }

        $detailsStmt->close();

        /*
        |--------------------------------------------------------------------------
        | INSERT CUSTOM FOOD ITEMS
        |--------------------------------------------------------------------------
        */

        if (!empty($items)) {

            $itemSql = "
                INSERT INTO catering_lead_items
                (
                    lead_id,
                    food_item_id,
                    quantity,
                    notes
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?
                )
            ";

            $itemStmt =
                $conn->prepare($itemSql);

            if (!$itemStmt) {

                throw new Exception(
                    "Failed to prepare catering lead item insertion: " .
                    $conn->error
                );
            }

            foreach ($items as $item) {

                $foodItemId =
                    $item['food_item_id'];

                $quantity =
                    $item['quantity'];

                $notes =
                    $item['notes'];

                $itemStmt->bind_param(
                    "iiis",
                    $leadId,
                    $foodItemId,
                    $quantity,
                    $notes
                );

                if (!$itemStmt->execute()) {

                    $error =
                        $itemStmt->error;

                    $itemStmt->close();

                    throw new Exception(
                        "Failed to insert catering lead food item: " .
                        $error
                    );
                }
            }

            $itemStmt->close();
        }

        /*
        |--------------------------------------------------------------------------
        | COMMIT
        |--------------------------------------------------------------------------
        */

        $conn->commit();

    } catch (Throwable $transactionException) {

        $conn->rollback();

        throw $transactionException;
    }

    /*
    |--------------------------------------------------------------------------
    | FETCH CREATED LEAD
    |--------------------------------------------------------------------------
    */

    $fetchLeadSql = "
        SELECT
            id,
            lead_reference,
            domain,
            customer_name,
            customer_phone,
            customer_email,
            event_date,
            guest_count,
            preferred_time,
            requirement,
            status,
            estimated_amount,
            converted_amount,
            converted_at,
            source,
            assigned_admin_id,
            created_at,
            updated_at
        FROM leads
        WHERE id = ?
        LIMIT 1
    ";

    $fetchLeadStmt =
        $conn->prepare($fetchLeadSql);

    if (!$fetchLeadStmt) {

        throw new Exception(
            "Failed to prepare created lead fetch."
        );
    }

    $fetchLeadStmt->bind_param(
        "i",
        $leadId
    );

    if (!$fetchLeadStmt->execute()) {

        $error =
            $fetchLeadStmt->error;

        $fetchLeadStmt->close();

        throw new Exception(
            "Failed to fetch created catering lead: " .
            $error
        );
    }

    $fetchLeadResult =
        $fetchLeadStmt->get_result();

    $createdLead =
        $fetchLeadResult->fetch_assoc();

    $fetchLeadStmt->close();

    if (!$createdLead) {

        throw new Exception(
            "Catering lead was created but could not be fetched."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SUCCESS RESPONSE
    |--------------------------------------------------------------------------
    */

    http_response_code(201);

    echo json_encode([
        "status" => true,
        "message" => "Catering lead created successfully.",
        "data" => [

            "lead" => [

                "id" =>
                    (int)$createdLead['id'],

                "lead_reference" =>
                    $createdLead['lead_reference'],

                "domain" =>
                    $createdLead['domain'],

                "customer_name" =>
                    $createdLead['customer_name'],

                "customer_phone" =>
                    $createdLead['customer_phone'],

                "customer_email" =>
                    $createdLead['customer_email'],

                "event_date" =>
                    $createdLead['event_date'],

                "guest_count" =>
                    $createdLead['guest_count'] !== null
                        ? (int)$createdLead['guest_count']
                        : null,

                "preferred_time" =>
                    $createdLead['preferred_time'],

                "requirement" =>
                    $createdLead['requirement'],

                "status" =>
                    $createdLead['status'],

                "estimated_amount" =>
                    $createdLead['estimated_amount'] !== null
                        ? (float)$createdLead['estimated_amount']
                        : null,

                "source" =>
                    $createdLead['source'],

                "created_at" =>
                    $createdLead['created_at']
            ],

            "catering_details" => [

                "catering_package_id" =>
                    $cateringPackageId,

                "event_type" =>
                    $eventType,

                "event_date" =>
                    $eventDate,

                "event_time" =>
                    $eventTime,

                "guest_count" =>
                    $guestCount,

                "venue_address" =>
                    $venueAddress,

                "estimated_amount" =>
                    $estimatedAmount,

                "other_requirements" =>
                    $otherRequirements
            ],

            "custom_food_items" =>
                $items,

            "estimate" => [

                "package_selected" =>
                    $package !== null,

                "package_name" =>
                    $package !== null
                        ? $package['name']
                        : null,

                "package_rate" =>
                    $package !== null
                        ? (float)$package['rate']
                        : null,

                "rate_unit" =>
                    $package !== null
                        ? $package['rate_unit']
                        : null,

                "gst_rate" =>
                    $package !== null
                        ? (float)$package['gst_rate']
                        : null,

                "calculated_amount" =>
                    $calculatedEstimatedAmount,

                "requested_amount" =>
                    $requestedEstimatedAmount,

                "final_estimated_amount" =>
                    $estimatedAmount
            ]
        ]
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        "status" => false,
        "message" => "Failed to create catering lead.",
        "error" => $e->getMessage()
    ]);
}