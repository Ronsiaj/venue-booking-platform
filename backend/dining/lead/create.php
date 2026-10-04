<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/database.php';


/*
|--------------------------------------------------------------------------
| Method Validation
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'status' => false,
        'message' => 'Only POST method is allowed.'
    ]);

    exit;
}


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
    'customer_name',
    'customer_phone',
    'customer_email',
    'dining_package_id',
    'meal_type',
    'dining_date',
    'dining_time',
    'guest_count',
    'other_requirements',
    'items'
];

$unknownFields = array_diff(
    array_keys($data),
    $allowedFields
);

if (!empty($unknownFields)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Unknown field(s) provided.',
        'unknown_fields' => array_values($unknownFields)
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Helper Validation Functions
|--------------------------------------------------------------------------
*/

function validateName($name)
{
    if (!is_string($name)) {
        return false;
    }

    $name = trim($name);

    if (
        mb_strlen($name) < 2 ||
        mb_strlen($name) > 150
    ) {
        return false;
    }

    if (preg_match('/\s{2,}/u', $name)) {
        return false;
    }

    return preg_match(
        '/^[A-Za-z]+(?:[ .\'-][A-Za-z]+)*$/',
        $name
    ) === 1;
}

function validatePhone($phone)
{
    if (!is_string($phone)) {
        return false;
    }

    $phone = trim($phone);

    if (!preg_match('/^[0-9]{10,15}$/', $phone)) {
        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | Reject Same Digit Repeated
    |--------------------------------------------------------------------------
    */

    if (preg_match('/^(\d)\1+$/', $phone)) {
        return false;
    }

    return true;
}

function validateDateFormat($date)
{
    if (!is_string($date)) {
        return false;
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }

    $dateObject = DateTime::createFromFormat(
        'Y-m-d',
        $date
    );

    if (!$dateObject) {
        return false;
    }

    return $dateObject->format('Y-m-d') === $date;
}


/*
|--------------------------------------------------------------------------
| Customer Name
|--------------------------------------------------------------------------
*/

if (!array_key_exists('customer_name', $data)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'customer_name is required.'
    ]);

    exit;
}

$customerName = trim($data['customer_name']);

if (!validateName($customerName)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'customer_name must contain only valid letters, spaces, dots, apostrophes or hyphens and must be between 2 and 150 characters.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Customer Phone
|--------------------------------------------------------------------------
*/

if (!array_key_exists('customer_phone', $data)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'customer_phone is required.'
    ]);

    exit;
}

$customerPhone = trim((string) $data['customer_phone']);

if (!validatePhone($customerPhone)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'customer_phone must contain 10 to 15 digits and cannot contain the same digit repeatedly.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Customer Email
|--------------------------------------------------------------------------
*/

$customerEmail = null;

if (
    array_key_exists('customer_email', $data) &&
    $data['customer_email'] !== null
) {

    if (!is_string($data['customer_email'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'customer_email must be a string.'
        ]);

        exit;
    }

    $customerEmail = strtolower(
        trim($data['customer_email'])
    );

    if ($customerEmail === '') {
        $customerEmail = null;
    }

    if (
        $customerEmail !== null &&
        mb_strlen($customerEmail) > 150
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'customer_email must not exceed 150 characters.'
        ]);

        exit;
    }

    if (
        $customerEmail !== null &&
        !filter_var(
            $customerEmail,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Invalid customer_email.'
        ]);

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Dining Package ID
|--------------------------------------------------------------------------
*/

$diningPackageId = null;

if (
    array_key_exists(
        'dining_package_id',
        $data
    ) &&
    $data['dining_package_id'] !== null &&
    $data['dining_package_id'] !== ''
) {

    if (
        filter_var(
            $data['dining_package_id'],
            FILTER_VALIDATE_INT
        ) === false ||
        (int) $data['dining_package_id'] <= 0
    ) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'dining_package_id must be a positive integer.'
        ]);

        exit;
    }

    $diningPackageId =
        (int) $data['dining_package_id'];
}


/*
|--------------------------------------------------------------------------
| Meal Type
|--------------------------------------------------------------------------
*/

$mealType = null;

$allowedMealTypes = [
    'lunch',
    'dinner',
    'breakfast',
    'other'
];

if (
    array_key_exists(
        'meal_type',
        $data
    ) &&
    $data['meal_type'] !== null &&
    $data['meal_type'] !== ''
) {

    if (!is_string($data['meal_type'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'meal_type must be a string.'
        ]);

        exit;
    }

    $mealType = strtolower(
        trim($data['meal_type'])
    );

    if (
        !in_array(
            $mealType,
            $allowedMealTypes,
            true
        )
    ) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Invalid meal_type.',
            'allowed_values' =>
                $allowedMealTypes
        ]);

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Dining Date
|--------------------------------------------------------------------------
*/

$diningDate = null;

if (
    array_key_exists(
        'dining_date',
        $data
    ) &&
    $data['dining_date'] !== null &&
    $data['dining_date'] !== ''
) {

    $diningDate = trim(
        (string) $data['dining_date']
    );

    if (!validateDateFormat($diningDate)) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'dining_date must be a valid date in YYYY-MM-DD format.'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Dining date cannot be in the past
    |--------------------------------------------------------------------------
    */

    $today = new DateTime(
        'today',
        new DateTimeZone('Asia/Kolkata')
    );

    $selectedDate = new DateTime(
        $diningDate,
        new DateTimeZone('Asia/Kolkata')
    );

    if ($selectedDate < $today) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'dining_date cannot be in the past.'
        ]);

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Dining Time
|--------------------------------------------------------------------------
*/

$diningTime = null;

if (
    array_key_exists(
        'dining_time',
        $data
    ) &&
    $data['dining_time'] !== null
) {

    if (!is_string($data['dining_time'])) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'dining_time must be a string.'
        ]);

        exit;
    }

    $diningTime = trim(
        $data['dining_time']
    );

    if ($diningTime === '') {
        $diningTime = null;
    }

    if (
        $diningTime !== null &&
        mb_strlen($diningTime) > 100
    ) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'dining_time must not exceed 100 characters.'
        ]);

        exit;
    }

    if (
        $diningTime !== null &&
        preg_match(
            '/\s{2,}/u',
            $diningTime
        )
    ) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'dining_time must not contain consecutive spaces.'
        ]);

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Guest Count
|--------------------------------------------------------------------------
*/

$guestCount = null;

if (
    array_key_exists(
        'guest_count',
        $data
    ) &&
    $data['guest_count'] !== null &&
    $data['guest_count'] !== ''
) {

    if (
        filter_var(
            $data['guest_count'],
            FILTER_VALIDATE_INT
        ) === false
    ) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'guest_count must be a valid integer.'
        ]);

        exit;
    }

    $guestCount = (int) $data['guest_count'];

    if (
        $guestCount < 1 ||
        $guestCount > 10000
    ) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'guest_count must be between 1 and 10000.'
        ]);

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Other Requirements
|--------------------------------------------------------------------------
*/

$otherRequirements = null;

if (
    array_key_exists(
        'other_requirements',
        $data
    ) &&
    $data['other_requirements'] !== null
) {

    if (!is_string($data['other_requirements'])) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'other_requirements must be a string.'
        ]);

        exit;
    }

    $otherRequirements = trim(
        $data['other_requirements']
    );

    if ($otherRequirements === '') {
        $otherRequirements = null;
    }

    if (
        $otherRequirements !== null &&
        mb_strlen($otherRequirements) > 5000
    ) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'other_requirements must not exceed 5000 characters.'
        ]);

        exit;
    }

    if (
        $otherRequirements !== null &&
        preg_match(
            '/\s{2,}/u',
            $otherRequirements
        )
    ) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'other_requirements must not contain consecutive spaces.'
        ]);

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Custom Food Items
|--------------------------------------------------------------------------
|
| Expected:
|
| "items": [
|     {
|         "food_item_id": 1,
|         "quantity": 2,
|         "notes": "Less spicy"
|     }
| ]
|
*/

$items = [];

if (
    array_key_exists(
        'items',
        $data
    ) &&
    $data['items'] !== null
) {

    if (!is_array($data['items'])) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'items must be an array.'
        ]);

        exit;
    }

    if (count($data['items']) > 100) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'A maximum of 100 food items is allowed.'
        ]);

        exit;
    }


    $usedFoodItemIds = [];

    foreach (
        $data['items']
        as $index => $item
    ) {

        if (!is_array($item)) {

            http_response_code(422);

            echo json_encode([
                'status' => false,
                'message' =>
                    "items[$index] must be an object."
            ]);

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | Allowed Item Fields
        |--------------------------------------------------------------------------
        */

        $allowedItemFields = [
            'food_item_id',
            'quantity',
            'notes'
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
                    "Unknown field(s) in items[$index].",
                'unknown_fields' =>
                    array_values(
                        $unknownItemFields
                    )
            ]);

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | food_item_id
        |--------------------------------------------------------------------------
        */

        if (
            !array_key_exists(
                'food_item_id',
                $item
            )
        ) {

            http_response_code(422);

            echo json_encode([
                'status' => false,
                'message' =>
                    "items[$index].food_item_id is required."
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
                    "items[$index].food_item_id must be a positive integer."
            ]);

            exit;
        }

        $foodItemId =
            (int) $item['food_item_id'];


        /*
        |--------------------------------------------------------------------------
        | Duplicate Food Item
        |--------------------------------------------------------------------------
        */

        if (
            in_array(
                $foodItemId,
                $usedFoodItemIds,
                true
            )
        ) {

            http_response_code(422);

            echo json_encode([
                'status' => false,
                'message' =>
                    "Duplicate food_item_id found in items.",
                'food_item_id' =>
                    $foodItemId
            ]);

            exit;
        }

        $usedFoodItemIds[] =
            $foodItemId;


        /*
        |--------------------------------------------------------------------------
        | Quantity
        |--------------------------------------------------------------------------
        */

        $quantity = 1;

        if (
            array_key_exists(
                'quantity',
                $item
            )
        ) {

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
                        "items[$index].quantity must be a valid integer."
                ]);

                exit;
            }

            $quantity =
                (int) $item['quantity'];

            if (
                $quantity < 1 ||
                $quantity > 1000
            ) {

                http_response_code(422);

                echo json_encode([
                    'status' => false,
                    'message' =>
                        "items[$index].quantity must be between 1 and 1000."
                ]);

                exit;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Notes
        |--------------------------------------------------------------------------
        */

        $notes = null;

        if (
            array_key_exists(
                'notes',
                $item
            ) &&
            $item['notes'] !== null
        ) {

            if (!is_string($item['notes'])) {

                http_response_code(422);

                echo json_encode([
                    'status' => false,
                    'message' =>
                        "items[$index].notes must be a string."
                ]);

                exit;
            }

            $notes = trim(
                $item['notes']
            );

            if ($notes === '') {
                $notes = null;
            }

            if (
                $notes !== null &&
                mb_strlen($notes) > 2000
            ) {

                http_response_code(422);

                echo json_encode([
                    'status' => false,
                    'message' =>
                        "items[$index].notes must not exceed 2000 characters."
                ]);

                exit;
            }

            if (
                $notes !== null &&
                preg_match(
                    '/\s{2,}/u',
                    $notes
                )
            ) {

                http_response_code(422);

                echo json_encode([
                    'status' => false,
                    'message' =>
                        "items[$index].notes must not contain consecutive spaces."
                ]);

                exit;
            }
        }


        $items[] = [
            'food_item_id' => $foodItemId,
            'quantity' => $quantity,
            'notes' => $notes
        ];
    }
}


/*
|--------------------------------------------------------------------------
| Validate Dining Package
|--------------------------------------------------------------------------
*/

if ($diningPackageId !== null) {

    $stmt = $conn->prepare("
        SELECT
            id,
            name,
            meal_type,
            status
        FROM dining_packages
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' =>
                'Failed to prepare dining package validation query.'
        ]);

        exit;
    }

    $stmt->bind_param(
        "i",
        $diningPackageId
    );

    if (!$stmt->execute()) {

        $stmt->close();

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' =>
                'Failed to validate dining package.'
        ]);

        exit;
    }

    $result = $stmt->get_result();
    $package = $result->fetch_assoc();

    $stmt->close();


    if (!$package) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Selected dining package does not exist.'
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Only Active Package Can Be Selected
    |--------------------------------------------------------------------------
    */

    if ($package['status'] !== 'active') {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Selected dining package is not active.'
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Meal Type Against Package
    |--------------------------------------------------------------------------
    |
    | Package can be combo.
    | If user sends meal_type, allow it only when
    | package meal type matches or package is combo/other.
    |
    */

    if (
        $mealType !== null &&
        $package['meal_type'] !== 'combo' &&
        $package['meal_type'] !== 'other' &&
        $package['meal_type'] !== $mealType
    ) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Selected meal_type does not match the selected dining package meal type.'
        ]);

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Validate Custom Food Items
|--------------------------------------------------------------------------
*/

$validatedFoodItems = [];

if (!empty($items)) {

    $foodItemIds = array_column(
        $items,
        'food_item_id'
    );

    $placeholders = implode(
        ',',
        array_fill(
            0,
            count($foodItemIds),
            '?'
        )
    );

    $sql = "
        SELECT
            id,
            name,
            food_type,
            status
        FROM food_items
        WHERE id IN ($placeholders)
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' =>
                'Failed to prepare food item validation query.'
        ]);

        exit;
    }


    $types = str_repeat(
        'i',
        count($foodItemIds)
    );

    $bindParams = [];

    $bindParams[] = $types;

    foreach (
        $foodItemIds
        as $key => $foodItemId
    ) {
        $bindParams[] =
            &$foodItemIds[$key];
    }

    call_user_func_array(
        [$stmt, 'bind_param'],
        $bindParams
    );


    if (!$stmt->execute()) {

        $stmt->close();

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' =>
                'Failed to validate food items.'
        ]);

        exit;
    }


    $foodResult = $stmt->get_result();

    while (
        $foodRow =
            $foodResult->fetch_assoc()
    ) {

        $validatedFoodItems[
            (int) $foodRow['id']
        ] = $foodRow;
    }

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Check Every Food Item
    |--------------------------------------------------------------------------
    */

    foreach ($items as $index => $item) {

        $foodItemId =
            $item['food_item_id'];

        if (
            !isset(
                $validatedFoodItems[$foodItemId]
            )
        ) {

            http_response_code(422);

            echo json_encode([
                'status' => false,
                'message' =>
                    "Food item does not exist.",
                'food_item_id' =>
                    $foodItemId
            ]);

            exit;
        }


        if (
            $validatedFoodItems[$foodItemId]['status']
            !== 'active'
        ) {

            http_response_code(422);

            echo json_encode([
                'status' => false,
                'message' =>
                    'Selected food item is not active.',
                'food_item_id' =>
                    $foodItemId,
                'food_item_name' =>
                    $validatedFoodItems[
                        $foodItemId
                    ]['name']
            ]);

            exit;
        }
    }
}


/*
|--------------------------------------------------------------------------
| At Least One Dining Detail
|--------------------------------------------------------------------------
|
| The lead can be created without a package,
| but at least one useful dining detail should exist.
|
*/

if (
    $diningPackageId === null &&
    $mealType === null &&
    $diningDate === null &&
    $diningTime === null &&
    $guestCount === null &&
    empty($items) &&
    $otherRequirements === null
) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'Please provide at least one dining requirement such as meal_type, dining_date, dining_time, guest_count, package or custom food items.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Generate Lead Reference
|--------------------------------------------------------------------------
*/

function generateLeadReference($conn)
{
    for ($attempt = 1; $attempt <= 10; $attempt++) {

        $randomPart =
            strtoupper(
                bin2hex(
                    random_bytes(4)
                )
            );

        $reference =
            'DIN-'
            . date('Ymd')
            . '-'
            . $randomPart;


        $stmt = $conn->prepare("
            SELECT id
            FROM leads
            WHERE lead_reference = ?
            LIMIT 1
        ");

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param(
            "s",
            $reference
        );

        if (!$stmt->execute()) {
            $stmt->close();

            return false;
        }

        $result = $stmt->get_result();

        $exists =
            $result->fetch_assoc();

        $stmt->close();

        if (!$exists) {
            return $reference;
        }
    }

    return false;
}

$leadReference =
    generateLeadReference($conn);

if ($leadReference === false) {

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to generate unique lead reference.'
    ]);

    exit;
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
    | Insert Main Lead
    |--------------------------------------------------------------------------
    */

    $domain = 'dining';
    $status = 'new';
    $source = 'website';

    $estimatedAmount = null;
    $convertedAmount = null;
    $convertedAt = null;
    $assignedAdminId = null;


    $stmt = $conn->prepare("
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
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        throw new Exception(
            'Failed to prepare lead insert query.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Main Lead Requirement
    |--------------------------------------------------------------------------
    */

    $requirement =
        $otherRequirements;


    $stmt->bind_param(
        "ssssssisssddssi",
        $leadReference,
        $domain,
        $customerName,
        $customerPhone,
        $customerEmail,
        $diningDate,
        $guestCount,
        $diningTime,
        $requirement,
        $status,
        $estimatedAmount,
        $convertedAmount,
        $convertedAt,
        $source,
        $assignedAdminId
    );


    if (!$stmt->execute()) {

        $errorCode =
            $stmt->errno;

        $stmt->close();

        if ($errorCode === 1062) {

            throw new Exception(
                'Lead reference already exists. Please try again.'
            );
        }

        throw new Exception(
            'Failed to create dining lead.'
        );
    }


    $leadId =
        (int) $conn->insert_id;

    $stmt->close();


    if ($leadId <= 0) {

        throw new Exception(
            'Failed to generate dining lead ID.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Insert Dining Lead Details
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        INSERT INTO dining_lead_details
        (
            lead_id,
            dining_package_id,
            meal_type,
            dining_date,
            dining_time,
            guest_count,
            other_requirements
        )
        VALUES
        (?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {

        throw new Exception(
            'Failed to prepare dining lead details query.'
        );
    }


    $stmt->bind_param(
        "iisssis",
        $leadId,
        $diningPackageId,
        $mealType,
        $diningDate,
        $diningTime,
        $guestCount,
        $otherRequirements
    );


    if (!$stmt->execute()) {

        $errorCode =
            $stmt->errno;

        $stmt->close();

        if ($errorCode === 1452) {

            throw new Exception(
                'Selected dining package is invalid.'
            );
        }

        throw new Exception(
            'Failed to create dining lead details.'
        );
    }

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Insert Custom Food Items
    |--------------------------------------------------------------------------
    */

    if (!empty($items)) {

        $stmt = $conn->prepare("
            INSERT INTO dining_lead_items
            (
                lead_id,
                food_item_id,
                quantity,
                notes
            )
            VALUES
            (?, ?, ?, ?)
        ");

        if (!$stmt) {

            throw new Exception(
                'Failed to prepare dining lead item query.'
            );
        }


        foreach ($items as $item) {

            $foodItemId =
                $item['food_item_id'];

            $quantity =
                $item['quantity'];

            $notes =
                $item['notes'];


            $stmt->bind_param(
                "iiis",
                $leadId,
                $foodItemId,
                $quantity,
                $notes
            );


            if (!$stmt->execute()) {

                $errorCode =
                    $stmt->errno;

                $stmt->close();

                if ($errorCode === 1452) {

                    throw new Exception(
                        'One or more selected food items are invalid.'
                    );
                }

                throw new Exception(
                    'Failed to create dining food item.'
                );
            }
        }


        $stmt->close();
    }


    /*
    |--------------------------------------------------------------------------
    | Commit Transaction
    |--------------------------------------------------------------------------
    */

    $conn->commit();


    /*
    |--------------------------------------------------------------------------
    | Fetch Created Lead
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
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
    ");

    if (!$stmt) {

        http_response_code(201);

        echo json_encode([
            'status' => true,
            'message' =>
                'Dining lead created successfully.',
            'data' => [
                'lead_id' => $leadId,
                'lead_reference' =>
                    $leadReference
            ]
        ]);

        exit;
    }


    $stmt->bind_param(
        "i",
        $leadId
    );

    $stmt->execute();

    $result =
        $stmt->get_result();

    $createdLead =
        $result->fetch_assoc();

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Fetch Created Dining Details
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            lead_id,
            dining_package_id,
            meal_type,
            dining_date,
            dining_time,
            guest_count,
            other_requirements
        FROM dining_lead_details
        WHERE lead_id = ?
        LIMIT 1
    ");

    $diningDetails = null;

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $leadId
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        $diningDetails =
            $result->fetch_assoc();

        $stmt->close();
    }


    /*
    |--------------------------------------------------------------------------
    | Fetch Created Custom Food Items
    |--------------------------------------------------------------------------
    */

    $createdItems = [];

    $stmt = $conn->prepare("
        SELECT
            dli.id,
            dli.food_item_id,
            dli.quantity,
            dli.notes,
            fi.name AS food_item_name,
            fi.food_type
        FROM dining_lead_items dli
        INNER JOIN food_items fi
            ON fi.id = dli.food_item_id
        WHERE dli.lead_id = ?
        ORDER BY dli.id ASC
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $leadId
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        while (
            $row =
                $result->fetch_assoc()
        ) {

            $createdItems[] = [
                'id' =>
                    (int) $row['id'],

                'food_item_id' =>
                    (int) $row['food_item_id'],

                'food_item_name' =>
                    $row['food_item_name'],

                'food_type' =>
                    $row['food_type'],

                'quantity' =>
                    (int) $row['quantity'],

                'notes' =>
                    $row['notes']
            ];
        }

        $stmt->close();
    }


    /*
    |--------------------------------------------------------------------------
    | Success Response
    |--------------------------------------------------------------------------
    */

    http_response_code(201);

    echo json_encode([
        'status' => true,
        'message' =>
            'Dining lead created successfully.',

        'data' => [
            'lead' => $createdLead,

            'dining_details' =>
                $diningDetails,

            'custom_food_items' =>
                $createdItems,

            'total_custom_food_items' =>
                count($createdItems)
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
    | Response
    |--------------------------------------------------------------------------
    */

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            $e->getMessage()
    ]);

    exit;
}