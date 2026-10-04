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
| Read JSON Body
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Allowed Fields
|--------------------------------------------------------------------------
*/

$allowedFields = [
    'name',
    'description',
    'meal_type',
    'status',
    'items'
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
            'unknown_fields' =>
                array_values($unknownFields)
        ]
    );
}


/*
|--------------------------------------------------------------------------
| Required Name
|--------------------------------------------------------------------------
*/

if (!array_key_exists('name', $data)) {

    sendResponse(
        422,
        'Package name is required.'
    );
}


/*
|--------------------------------------------------------------------------
| Name Validation
|--------------------------------------------------------------------------
*/

if (!is_string($data['name'])) {

    sendResponse(
        422,
        'Package name must be a string.'
    );
}

$name = trim(
    $data['name']
);

if ($name === '') {

    sendResponse(
        422,
        'Package name cannot be empty.'
    );
}

$nameLength = strlen($name);

if ($nameLength < 2) {

    sendResponse(
        422,
        'Package name must contain at least 2 characters.'
    );
}

if ($nameLength > 150) {

    sendResponse(
        422,
        'Package name cannot exceed 150 characters.'
    );
}


/*
|--------------------------------------------------------------------------
| Package Name Format
|--------------------------------------------------------------------------
|
| Allows:
| Letters
| Numbers
| Spaces
| &
| -
| /
| .
| ,
| (
| )
|
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
        'Package name contains invalid characters or spacing.'
    );
}


/*
|--------------------------------------------------------------------------
| Prevent Multiple Spaces
|--------------------------------------------------------------------------
*/

if (preg_match('/\s{2,}/', $name)) {

    sendResponse(
        422,
        'Package name cannot contain multiple consecutive spaces.'
    );
}


/*
|--------------------------------------------------------------------------
| Description Validation
|--------------------------------------------------------------------------
*/

$description = null;

if (array_key_exists('description', $data)) {

    if (
        $data['description'] !== null &&
        !is_string($data['description'])
    ) {

        sendResponse(
            422,
            'Description must be a string or null.'
        );
    }

    if ($data['description'] !== null) {

        $description = trim(
            $data['description']
        );

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
}


/*
|--------------------------------------------------------------------------
| Meal Type Validation
|--------------------------------------------------------------------------
*/

$mealType = 'combo';

if (array_key_exists('meal_type', $data)) {

    if (!is_string($data['meal_type'])) {

        sendResponse(
            422,
            'Meal type must be a string.'
        );
    }

    $mealType = strtolower(
        trim($data['meal_type'])
    );
}

$allowedMealTypes = [
    'breakfast',
    'lunch',
    'dinner',
    'combo',
    'other'
];

if (
    !in_array(
        $mealType,
        $allowedMealTypes,
        true
    )
) {

    sendResponse(
        422,
        'Invalid meal type. Allowed values are breakfast, lunch, dinner, combo, other.'
    );
}


/*
|--------------------------------------------------------------------------
| Status Validation
|--------------------------------------------------------------------------
*/

$status = 'active';

if (array_key_exists('status', $data)) {

    if (!is_string($data['status'])) {

        sendResponse(
            422,
            'Status must be a string.'
        );
    }

    $status = strtolower(
        trim($data['status'])
    );
}

$allowedStatuses = [
    'active',
    'inactive'
];

if (
    !in_array(
        $status,
        $allowedStatuses,
        true
    )
) {

    sendResponse(
        422,
        'Invalid status. Allowed values are active or inactive.'
    );
}


/*
|--------------------------------------------------------------------------
| Items Validation
|--------------------------------------------------------------------------
|
| Example:
|
| "items": [
|     {
|         "food_item_id": 1,
|         "quantity": 2
|     }
| ]
|
|--------------------------------------------------------------------------
*/

$items = [];

if (array_key_exists('items', $data)) {

    if (!is_array($data['items'])) {

        sendResponse(
            422,
            'Items must be an array.'
        );
    }

    $items = $data['items'];
}


/*
|--------------------------------------------------------------------------
| Maximum Items
|--------------------------------------------------------------------------
*/

if (count($items) > 100) {

    sendResponse(
        422,
        'A food package cannot contain more than 100 food items.'
    );
}


/*
|--------------------------------------------------------------------------
| Validate Each Food Item
|--------------------------------------------------------------------------
*/

$validatedItems = [];

$foodItemIds = [];

foreach (
    $items as $index => $item
) {

    if (!is_array($item)) {

        sendResponse(
            422,
            "Item at index {$index} must be an object."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Allowed Item Fields
    |--------------------------------------------------------------------------
    */

    $allowedItemFields = [
        'food_item_id',
        'quantity'
    ];

    $unknownItemFields = array_diff(
        array_keys($item),
        $allowedItemFields
    );

    if (!empty($unknownItemFields)) {

        sendResponse(
            422,
            "Unknown field(s) in item at index {$index}.",
            [
                'unknown_fields' =>
                    array_values(
                        $unknownItemFields
                    )
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Food Item ID Required
    |--------------------------------------------------------------------------
    */

    if (
        !array_key_exists(
            'food_item_id',
            $item
        )
    ) {

        sendResponse(
            422,
            "Food item ID is required at index {$index}."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Food Item ID Validation
    |--------------------------------------------------------------------------
    */

    $foodItemIdValue =
        $item['food_item_id'];

    if (is_int($foodItemIdValue)) {

        $foodItemId =
            $foodItemIdValue;

    } elseif (is_string($foodItemIdValue)) {

        $foodItemIdValue =
            trim($foodItemIdValue);

        if (
            !preg_match(
                '/^\d+$/',
                $foodItemIdValue
            )
        ) {

            sendResponse(
                422,
                "Food item ID must be a valid positive integer at index {$index}."
            );
        }

        $foodItemId =
            (int) $foodItemIdValue;

    } else {

        sendResponse(
            422,
            "Food item ID must be a valid positive integer at index {$index}."
        );
    }

    if ($foodItemId <= 0) {

        sendResponse(
            422,
            "Food item ID must be greater than 0 at index {$index}."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Quantity Validation
    |--------------------------------------------------------------------------
    */

    $quantity = 1;

    if (array_key_exists(
        'quantity',
        $item
    )) {

        $quantityValue =
            $item['quantity'];

        if (is_int($quantityValue)) {

            $quantity =
                $quantityValue;

        } elseif (is_string($quantityValue)) {

            $quantityValue =
                trim($quantityValue);

            if (
                !preg_match(
                    '/^\d+$/',
                    $quantityValue
                )
            ) {

                sendResponse(
                    422,
                    "Quantity must be a valid positive integer at index {$index}."
                );
            }

            $quantity =
                (int) $quantityValue;

        } else {

            sendResponse(
                422,
                "Quantity must be a valid positive integer at index {$index}."
            );
        }
    }


    if ($quantity < 1) {

        sendResponse(
            422,
            "Quantity must be greater than 0 at index {$index}."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Maximum Quantity
    |--------------------------------------------------------------------------
    */

    if ($quantity > 1000) {

        sendResponse(
            422,
            "Quantity cannot exceed 1000 at index {$index}."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Duplicate Food Item Check
    |--------------------------------------------------------------------------
    |
    | Same food item cannot appear twice.
    |
    |--------------------------------------------------------------------------
    */

    if (
        in_array(
            $foodItemId,
            $foodItemIds,
            true
        )
    ) {

        sendResponse(
            422,
            "Food item ID {$foodItemId} is duplicated in the package."
        );
    }

    $foodItemIds[] =
        $foodItemId;


    $validatedItems[] = [
        'food_item_id' =>
            $foodItemId,

        'quantity' =>
            $quantity
    ];
}


/*
|--------------------------------------------------------------------------
| Duplicate Package Name Check
|--------------------------------------------------------------------------
*/

$duplicateSQL = "
    SELECT id
    FROM room_food_packages
    WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))
    LIMIT 1
";

$duplicateStmt =
    $conn->prepare(
        $duplicateSQL
    );

if (!$duplicateStmt) {

    sendResponse(
        500,
        'Failed to prepare duplicate package validation.'
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
        'Failed to validate duplicate package.'
    );
}

$duplicateResult =
    $duplicateStmt->get_result();

if (!$duplicateResult) {

    $duplicateStmt->close();

    sendResponse(
        500,
        'Failed to process duplicate package validation.'
    );
}

if (
    $duplicateResult->num_rows > 0
) {

    $duplicateStmt->close();

    sendResponse(
        409,
        'A room food package with this name already exists.'
    );
}

$duplicateStmt->close();


/*
|--------------------------------------------------------------------------
| Validate All Food Items
|--------------------------------------------------------------------------
*/

$foodItemsDetails = [];

foreach (
    $validatedItems as $item
) {

    $foodItemId =
        $item['food_item_id'];

    $foodSQL = "
        SELECT
            id,
            name,
            food_type,
            status
        FROM food_items
        WHERE id = ?
        LIMIT 1
    ";

    $foodStmt =
        $conn->prepare(
            $foodSQL
        );

    if (!$foodStmt) {

        sendResponse(
            500,
            'Failed to prepare food item validation query.'
        );
    }

    $foodStmt->bind_param(
        'i',
        $foodItemId
    );

    if (!$foodStmt->execute()) {

        $foodStmt->close();

        sendResponse(
            500,
            'Failed to validate food item.'
        );
    }

    $foodResult =
        $foodStmt->get_result();

    if (!$foodResult) {

        $foodStmt->close();

        sendResponse(
            500,
            'Failed to process food item validation.'
        );
    }

    $foodItem =
        $foodResult->fetch_assoc();

    $foodStmt->close();

    if (!$foodItem) {

        sendResponse(
            422,
            "Food item ID {$foodItemId} does not exist."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Food Item Must Be Active
    |--------------------------------------------------------------------------
    */

    if (
        $foodItem['status'] !== 'active'
    ) {

        sendResponse(
            422,
            "Food item '{$foodItem['name']}' is inactive."
        );
    }


    $foodItemsDetails[] = [
        'food_item_id' =>
            (int) $foodItem['id'],

        'name' =>
            $foodItem['name'],

        'food_type' =>
            $foodItem['food_type'],

        'quantity' =>
            $item['quantity']
    ];
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
    | Insert Room Food Package
    |--------------------------------------------------------------------------
    */

    $insertSQL = "
        INSERT INTO room_food_packages
        (
            name,
            description,
            meal_type,
            status
        )
        VALUES (?, ?, ?, ?)
    ";

    $insertStmt =
        $conn->prepare(
            $insertSQL
        );

    if (!$insertStmt) {

        throw new Exception(
            'Failed to prepare package creation query.'
        );
    }

    $insertStmt->bind_param(
        'ssss',
        $name,
        $description,
        $mealType,
        $status
    );

    if (!$insertStmt->execute()) {

        $errorCode =
            $insertStmt->errno;

        $insertStmt->close();

        if ($errorCode === 1062) {

            throw new Exception(
                'A room food package with this name already exists.'
            );
        }

        throw new Exception(
            'Failed to create room food package.'
        );
    }

    $packageId =
        (int) $insertStmt->insert_id;

    $insertStmt->close();

    if ($packageId <= 0) {

        throw new Exception(
            'Package was created but its ID could not be retrieved.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Insert Package Food Items
    |--------------------------------------------------------------------------
    */

    if (!empty($validatedItems)) {

        $itemInsertSQL = "
            INSERT INTO room_food_package_items
            (
                package_id,
                food_item_id,
                quantity
            )
            VALUES (?, ?, ?)
        ";

        $itemInsertStmt =
            $conn->prepare(
                $itemInsertSQL
            );

        if (!$itemInsertStmt) {

            throw new Exception(
                'Failed to prepare package food item query.'
            );
        }


        foreach (
            $validatedItems as $item
        ) {

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
                        'Duplicate food item found in package.'
                    );
                }

                if ($errorCode === 1452) {

                    throw new Exception(
                        'Invalid food item reference.'
                    );
                }

                throw new Exception(
                    'Failed to add food item to package.'
                );
            }
        }

        $itemInsertStmt->close();
    }


    /*
    |--------------------------------------------------------------------------
    | Commit Transaction
    |--------------------------------------------------------------------------
    */

    $conn->commit();

} catch (Throwable $e) {

    $conn->rollback();

    sendResponse(
        500,
        $e->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| Fetch Created Package
|--------------------------------------------------------------------------
*/

$packageSQL = "
    SELECT
        id,
        name,
        description,
        meal_type,
        status,
        created_at,
        updated_at
    FROM room_food_packages
    WHERE id = ?
    LIMIT 1
";

$packageStmt =
    $conn->prepare(
        $packageSQL
    );

if (!$packageStmt) {

    sendResponse(
        500,
        'Package was created, but failed to prepare response query.'
    );
}

$packageStmt->bind_param(
    'i',
    $packageId
);

if (!$packageStmt->execute()) {

    $packageStmt->close();

    sendResponse(
        500,
        'Package was created, but failed to fetch created package.'
    );
}

$packageResult =
    $packageStmt->get_result();

if (!$packageResult) {

    $packageStmt->close();

    sendResponse(
        500,
        'Package was created, but failed to process created package.'
    );
}

$createdPackage =
    $packageResult->fetch_assoc();

$packageStmt->close();


/*
|--------------------------------------------------------------------------
| Final Response
|--------------------------------------------------------------------------
*/

sendResponse(
    201,
    'Room food package created successfully.',
    [

        'package' => [

            'id' =>
                (int) $createdPackage['id'],

            'name' =>
                $createdPackage['name'],

            'description' =>
                $createdPackage['description'],

            'meal_type' =>
                $createdPackage['meal_type'],

            'status' =>
                $createdPackage['status'],

            'created_at' =>
                $createdPackage['created_at'],

            'updated_at' =>
                $createdPackage['updated_at']
        ],

        'items' =>
            $foodItemsDetails,

        'total_items' =>
            count($foodItemsDetails),

        'created_by' => [

            'id' =>
                (int) $user['sub'],

            'role' =>
                $user['role']
        ]
    ]
);