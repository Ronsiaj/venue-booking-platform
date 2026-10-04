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
    'package_id',
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
| Package ID Validation
|--------------------------------------------------------------------------
*/

if (!array_key_exists(
    'package_id',
    $data
)) {
    sendResponse(
        422,
        'Package ID is required.'
    );
}

$packageIdValue =
    $data['package_id'];

if (is_int($packageIdValue)) {

    $packageId =
        $packageIdValue;

} elseif (is_string($packageIdValue)) {

    $packageIdValue =
        trim($packageIdValue);

    if (
        !preg_match(
            '/^\d+$/',
            $packageIdValue
        )
    ) {
        sendResponse(
            422,
            'Package ID must be a valid positive integer.'
        );
    }

    $packageId =
        (int) $packageIdValue;

} else {

    sendResponse(
        422,
        'Package ID must be a valid positive integer.'
    );
}

if ($packageId <= 0) {

    sendResponse(
        422,
        'Package ID must be greater than 0.'
    );
}


/*
|--------------------------------------------------------------------------
| Check Package Exists
|--------------------------------------------------------------------------
*/

$currentSQL = "
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

$currentStmt =
    $conn->prepare($currentSQL);

if (!$currentStmt) {

    sendResponse(
        500,
        'Failed to prepare package query.'
    );
}

$currentStmt->bind_param(
    'i',
    $packageId
);

if (!$currentStmt->execute()) {

    $currentStmt->close();

    sendResponse(
        500,
        'Failed to fetch room food package.'
    );
}

$currentResult =
    $currentStmt->get_result();

if (!$currentResult) {

    $currentStmt->close();

    sendResponse(
        500,
        'Failed to process room food package.'
    );
}

$currentPackage =
    $currentResult->fetch_assoc();

$currentStmt->close();

if (!$currentPackage) {

    sendResponse(
        404,
        'Room food package not found.'
    );
}


/*
|--------------------------------------------------------------------------
| At Least One Update Field
|--------------------------------------------------------------------------
*/

if (count($data) <= 1) {

    sendResponse(
        422,
        'At least one field is required for update.'
    );
}


/*
|--------------------------------------------------------------------------
| Current Values
|--------------------------------------------------------------------------
*/

$newName =
    $currentPackage['name'];

$newDescription =
    $currentPackage['description'];

$newMealType =
    $currentPackage['meal_type'];

$newStatus =
    $currentPackage['status'];


/*
|--------------------------------------------------------------------------
| Track Changes
|--------------------------------------------------------------------------
*/

$updatedFields = [];

$itemsProvided =
    array_key_exists(
        'items',
        $data
    );


/*
|--------------------------------------------------------------------------
| Name Validation
|--------------------------------------------------------------------------
*/

if (array_key_exists('name', $data)) {

    if (!is_string($data['name'])) {

        sendResponse(
            422,
            'Package name must be a string.'
        );
    }

    $newName =
        trim($data['name']);

    if ($newName === '') {

        sendResponse(
            422,
            'Package name cannot be empty.'
        );
    }

    $nameLength =
        strlen($newName);

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
            'Package name contains invalid characters or spacing.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Multiple Spaces
    |--------------------------------------------------------------------------
    */

    if (
        preg_match(
            '/\s{2,}/',
            $newName
        )
    ) {

        sendResponse(
            422,
            'Package name cannot contain multiple consecutive spaces.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Duplicate Name Check
    |--------------------------------------------------------------------------
    */

    $duplicateSQL = "
        SELECT id
        FROM room_food_packages
        WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))
        AND id != ?
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
        'si',
        $newName,
        $packageId
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

    if ($duplicateResult->num_rows > 0) {

        $duplicateStmt->close();

        sendResponse(
            409,
            'Another room food package with this name already exists.'
        );
    }

    $duplicateStmt->close();


    if (
        $newName !==
        $currentPackage['name']
    ) {
        $updatedFields[] = 'name';
    }
}


/*
|--------------------------------------------------------------------------
| Description Validation
|--------------------------------------------------------------------------
*/

if (
    array_key_exists(
        'description',
        $data
    )
) {

    if (
        $data['description'] !== null &&
        !is_string(
            $data['description']
        )
    ) {

        sendResponse(
            422,
            'Description must be a string or null.'
        );
    }

    if ($data['description'] === null) {

        $newDescription = null;

    } else {

        $newDescription =
            trim(
                $data['description']
            );

        if ($newDescription === '') {
            $newDescription = null;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Description Length
    |--------------------------------------------------------------------------
    */

    if (
        $newDescription !== null &&
        strlen($newDescription) > 5000
    ) {

        sendResponse(
            422,
            'Description cannot exceed 5000 characters.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Multiple Spaces
    |--------------------------------------------------------------------------
    */

    if (
        $newDescription !== null &&
        preg_match(
            '/\s{2,}/',
            $newDescription
        )
    ) {

        sendResponse(
            422,
            'Description cannot contain multiple consecutive spaces.'
        );
    }


    if (
        $newDescription !==
        $currentPackage['description']
    ) {
        $updatedFields[] =
            'description';
    }
}


/*
|--------------------------------------------------------------------------
| Meal Type Validation
|--------------------------------------------------------------------------
*/

if (
    array_key_exists(
        'meal_type',
        $data
    )
) {

    if (!is_string($data['meal_type'])) {

        sendResponse(
            422,
            'Meal type must be a string.'
        );
    }

    $newMealType =
        strtolower(
            trim(
                $data['meal_type']
            )
        );

    $allowedMealTypes = [
        'breakfast',
        'lunch',
        'dinner',
        'combo',
        'other'
    ];

    if (
        !in_array(
            $newMealType,
            $allowedMealTypes,
            true
        )
    ) {

        sendResponse(
            422,
            'Invalid meal type. Allowed values are breakfast, lunch, dinner, combo, other.'
        );
    }

    if (
        $newMealType !==
        $currentPackage['meal_type']
    ) {
        $updatedFields[] =
            'meal_type';
    }
}


/*
|--------------------------------------------------------------------------
| Status Validation
|--------------------------------------------------------------------------
*/

if (
    array_key_exists(
        'status',
        $data
    )
) {

    if (!is_string($data['status'])) {

        sendResponse(
            422,
            'Status must be a string.'
        );
    }

    $newStatus =
        strtolower(
            trim(
                $data['status']
            )
        );

    $allowedStatuses = [
        'active',
        'inactive'
    ];

    if (
        !in_array(
            $newStatus,
            $allowedStatuses,
            true
        )
    ) {

        sendResponse(
            422,
            'Invalid status. Allowed values are active or inactive.'
        );
    }

    if (
        $newStatus !==
        $currentPackage['status']
    ) {
        $updatedFields[] =
            'status';
    }
}


/*
|--------------------------------------------------------------------------
| Validate Items
|--------------------------------------------------------------------------
*/

$validatedItems = [];

$foodItemsDetails = [];

if ($itemsProvided) {

    if (!is_array($data['items'])) {

        sendResponse(
            422,
            'Items must be an array.'
        );
    }

    $items =
        $data['items'];


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


    $foodItemIds = [];


    /*
    |--------------------------------------------------------------------------
    | Validate Each Item
    |--------------------------------------------------------------------------
    */

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

        $unknownItemFields =
            array_diff(
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

        } elseif (
            is_string($foodItemIdValue)
        ) {

            $foodItemIdValue =
                trim(
                    $foodItemIdValue
                );

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

        if (
            array_key_exists(
                'quantity',
                $item
            )
        ) {

            $quantityValue =
                $item['quantity'];

            if (is_int($quantityValue)) {

                $quantity =
                    $quantityValue;

            } elseif (
                is_string($quantityValue)
            ) {

                $quantityValue =
                    trim(
                        $quantityValue
                    );

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


        if ($quantity > 1000) {

            sendResponse(
                422,
                "Quantity cannot exceed 1000 at index {$index}."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Duplicate Food Item
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
    | Validate Food Items Exist + Active
    |--------------------------------------------------------------------------
    */

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


        if (
            $foodItem['status'] !==
            'active'
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
}


/*
|--------------------------------------------------------------------------
| Check Whether Anything Changed
|--------------------------------------------------------------------------
*/

if (
    empty($updatedFields) &&
    !$itemsProvided
) {

    sendResponse(
        200,
        'No changes were made.',
        [
            'package_id' =>
                $packageId,

            'updated_fields' =>
                []
        ]
    );
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
    | Update Package Main Fields
    |--------------------------------------------------------------------------
    */

    $updateParts = [];

    $updateParams = [];

    $updateTypes = '';


    if (
        array_key_exists(
            'name',
            $data
        )
    ) {

        $updateParts[] =
            'name = ?';

        $updateParams[] =
            $newName;

        $updateTypes .= 's';
    }


    if (
        array_key_exists(
            'description',
            $data
        )
    ) {

        $updateParts[] =
            'description = ?';

        $updateParams[] =
            $newDescription;

        $updateTypes .= 's';
    }


    if (
        array_key_exists(
            'meal_type',
            $data
        )
    ) {

        $updateParts[] =
            'meal_type = ?';

        $updateParams[] =
            $newMealType;

        $updateTypes .= 's';
    }


    if (
        array_key_exists(
            'status',
            $data
        )
    ) {

        $updateParts[] =
            'status = ?';

        $updateParams[] =
            $newStatus;

        $updateTypes .= 's';
    }


    /*
    |--------------------------------------------------------------------------
    | Execute Main Package Update
    |--------------------------------------------------------------------------
    */

    if (!empty($updateParts)) {

        $updateParams[] =
            $packageId;

        $updateTypes .= 'i';

        $updateSQL = "
            UPDATE room_food_packages
            SET " .
            implode(
                ', ',
                $updateParts
            ) .
            "
            WHERE id = ?
            LIMIT 1
        ";

        $updateStmt =
            $conn->prepare(
                $updateSQL
            );

        if (!$updateStmt) {

            throw new Exception(
                'Failed to prepare package update query.'
            );
        }

        $updateStmt->bind_param(
            $updateTypes,
            ...$updateParams
        );

        if (!$updateStmt->execute()) {

            $errorCode =
                $updateStmt->errno;

            $updateStmt->close();

            if ($errorCode === 1062) {

                throw new Exception(
                    'Another room food package with this name already exists.'
                );
            }

            throw new Exception(
                'Failed to update room food package.'
            );
        }

        $updateStmt->close();
    }


    /*
    |--------------------------------------------------------------------------
    | Replace Package Food Items
    |--------------------------------------------------------------------------
    */

    if ($itemsProvided) {

        /*
        |--------------------------------------------------------------------------
        | Delete Existing Package Items
        |--------------------------------------------------------------------------
        */

        $deleteItemsSQL = "
            DELETE FROM room_food_package_items
            WHERE package_id = ?
        ";

        $deleteItemsStmt =
            $conn->prepare(
                $deleteItemsSQL
            );

        if (!$deleteItemsStmt) {

            throw new Exception(
                'Failed to prepare existing package items delete query.'
            );
        }

        $deleteItemsStmt->bind_param(
            'i',
            $packageId
        );

        if (!$deleteItemsStmt->execute()) {

            $deleteItemsStmt->close();

            throw new Exception(
                'Failed to remove existing package food items.'
            );
        }

        $deleteItemsStmt->close();


        /*
        |--------------------------------------------------------------------------
        | Insert New Package Items
        |--------------------------------------------------------------------------
        */

        if (!empty($validatedItems)) {

            $insertItemSQL = "
                INSERT INTO room_food_package_items
                (
                    package_id,
                    food_item_id,
                    quantity
                )
                VALUES (?, ?, ?)
            ";

            $insertItemStmt =
                $conn->prepare(
                    $insertItemSQL
                );

            if (!$insertItemStmt) {

                throw new Exception(
                    'Failed to prepare package item insert query.'
                );
            }


            foreach (
                $validatedItems as $item
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

                if (!$insertItemStmt->execute()) {

                    $errorCode =
                        $insertItemStmt->errno;

                    $insertItemStmt->close();

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

            $insertItemStmt->close();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Commit
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
| Fetch Updated Package
|--------------------------------------------------------------------------
*/

$fetchPackageSQL = "
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

$fetchPackageStmt =
    $conn->prepare(
        $fetchPackageSQL
    );

if (!$fetchPackageStmt) {

    sendResponse(
        500,
        'Package was updated, but failed to prepare response query.'
    );
}

$fetchPackageStmt->bind_param(
    'i',
    $packageId
);

if (!$fetchPackageStmt->execute()) {

    $fetchPackageStmt->close();

    sendResponse(
        500,
        'Package was updated, but failed to fetch updated package.'
    );
}

$fetchPackageResult =
    $fetchPackageStmt->get_result();

if (!$fetchPackageResult) {

    $fetchPackageStmt->close();

    sendResponse(
        500,
        'Package was updated, but failed to process updated package.'
    );
}

$updatedPackage =
    $fetchPackageResult->fetch_assoc();

$fetchPackageStmt->close();


/*
|--------------------------------------------------------------------------
| Fetch Final Package Items
|--------------------------------------------------------------------------
*/

$finalItemsSQL = "
    SELECT
        rfpi.food_item_id,
        fi.name,
        fi.description,
        fi.image_path,
        fi.food_type,
        fi.status,
        rfpi.quantity
    FROM room_food_package_items rfpi

    INNER JOIN food_items fi
        ON fi.id = rfpi.food_item_id

    WHERE rfpi.package_id = ?

    ORDER BY fi.name ASC
";

$finalItemsStmt =
    $conn->prepare(
        $finalItemsSQL
    );

if (!$finalItemsStmt) {

    sendResponse(
        500,
        'Package was updated, but failed to prepare item response query.'
    );
}

$finalItemsStmt->bind_param(
    'i',
    $packageId
);

if (!$finalItemsStmt->execute()) {

    $finalItemsStmt->close();

    sendResponse(
        500,
        'Package was updated, but failed to fetch package items.'
    );
}

$finalItemsResult =
    $finalItemsStmt->get_result();

if (!$finalItemsResult) {

    $finalItemsStmt->close();

    sendResponse(
        500,
        'Package was updated, but failed to process package items.'
    );
}

$finalItems = [];

while (
    $item = $finalItemsResult->fetch_assoc()
) {

    $finalItems[] = [

        'food_item_id' =>
            (int) $item['food_item_id'],

        'name' =>
            $item['name'],

        'description' =>
            $item['description'],

        'image_path' =>
            $item['image_path'],

        'food_type' =>
            $item['food_type'],

        'status' =>
            $item['status'],

        'quantity' =>
            (int) $item['quantity']
    ];
}

$finalItemsStmt->close();


/*
|--------------------------------------------------------------------------
| Final Updated Fields
|--------------------------------------------------------------------------
*/

if ($itemsProvided) {

    $updatedFields[] =
        'items';
}

$updatedFields =
    array_values(
        array_unique(
            $updatedFields
        )
    );


/*
|--------------------------------------------------------------------------
| Final Response
|--------------------------------------------------------------------------
*/

sendResponse(
    200,
    'Room food package updated successfully.',
    [

        'package' => [

            'id' =>
                (int) $updatedPackage['id'],

            'name' =>
                $updatedPackage['name'],

            'description' =>
                $updatedPackage['description'],

            'meal_type' =>
                $updatedPackage['meal_type'],

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
            count($finalItems),

        'updated_fields' =>
            $updatedFields,

        'updated_by' => [

            'id' =>
                (int) $user['sub'],

            'role' =>
                $user['role']
        ]
    ]
);