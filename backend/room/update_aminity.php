<?php

/*
|--------------------------------------------------------------------------
| CORS Headers
|--------------------------------------------------------------------------
*/

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");


/*
|--------------------------------------------------------------------------
| Handle OPTIONS Preflight
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {

    http_response_code(200);
    exit;
}


/*
|--------------------------------------------------------------------------
| Request Method
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Include Database & Middleware
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/middleware.php';


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
|
| Admin + Staff can update room amenities.
|
*/

$user = requireRole([
    'admin',
    'staff'
]);


/*
|--------------------------------------------------------------------------
| Helper Function - Error Response
|--------------------------------------------------------------------------
*/

function sendError($httpCode, $message, $data = null)
{
    http_response_code($httpCode);

    $response = [
        'status' => false,
        'message' => $message
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode(
        $response,
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Helper Function - Positive Integer
|--------------------------------------------------------------------------
*/

function isPositiveInteger($value)
{
    if (
        is_array($value) ||
        is_object($value)
    ) {
        return false;
    }

    if (
        !is_int($value) &&
        !ctype_digit((string) $value)
    ) {
        return false;
    }

    return (int) $value > 0;
}


/*
|--------------------------------------------------------------------------
| Read JSON Request Body
|--------------------------------------------------------------------------
*/

$rawInput =
    file_get_contents('php://input');


if (
    $rawInput === false ||
    trim($rawInput) === ''
) {

    sendError(
        400,
        'Request body is required.'
    );
}


/*
|--------------------------------------------------------------------------
| Decode JSON
|--------------------------------------------------------------------------
*/

$data =
    json_decode(
        $rawInput,
        true
    );


if (
    json_last_error() !== JSON_ERROR_NONE
) {

    sendError(
        400,
        'Invalid JSON request body.'
    );
}


/*
|--------------------------------------------------------------------------
| Request Body Must Be Object
|--------------------------------------------------------------------------
*/

if (
    !is_array($data)
) {

    sendError(
        400,
        'Request body must be a JSON object.'
    );
}


/*
|--------------------------------------------------------------------------
| Allowed Fields
|--------------------------------------------------------------------------
*/

$allowedFields = [
    'room_id',
    'amenity_ids'
];


/*
|--------------------------------------------------------------------------
| Reject Unknown Fields
|--------------------------------------------------------------------------
*/

foreach (
    $data as $key => $value
) {

    if (
        !in_array(
            $key,
            $allowedFields,
            true
        )
    ) {

        sendError(
            422,
            "Unknown field: {$key}."
        );
    }
}


/*
|--------------------------------------------------------------------------
| Validate room_id
|--------------------------------------------------------------------------
*/

if (
    !array_key_exists(
        'room_id',
        $data
    )
) {

    sendError(
        422,
        'room_id is required.'
    );
}


if (
    !isPositiveInteger(
        $data['room_id']
    )
) {

    sendError(
        422,
        'room_id must be a valid positive integer.'
    );
}


$roomId =
    (int) $data['room_id'];


/*
|--------------------------------------------------------------------------
| Validate amenity_ids
|--------------------------------------------------------------------------
|
| amenity_ids is mandatory.
|
| Empty array is allowed:
|
| []
|
| This means remove all amenities
| from the room.
|
*/

if (
    !array_key_exists(
        'amenity_ids',
        $data
    )
) {

    sendError(
        422,
        'amenity_ids is required.'
    );
}


if (
    !is_array(
        $data['amenity_ids']
    )
) {

    sendError(
        422,
        'amenity_ids must be an array.'
    );
}


$amenityIds =
    $data['amenity_ids'];


/*
|--------------------------------------------------------------------------
| Maximum Amenities Validation
|--------------------------------------------------------------------------
|
| Maximum 50 amenities can be assigned
| to one room.
|
*/

$maxAmenitiesPerRoom = 50;


if (
    count($amenityIds) >
    $maxAmenitiesPerRoom
) {

    sendError(
        422,
        'A maximum of 50 amenities can be assigned to a room.'
    );
}


/*
|--------------------------------------------------------------------------
| Validate Each Amenity ID
|--------------------------------------------------------------------------
*/

$validatedAmenityIds = [];


foreach (
    $amenityIds as $index => $amenityId
) {

    if (
        is_array($amenityId) ||
        is_object($amenityId)
    ) {

        sendError(
            422,
            "amenity_ids[{$index}] must be a valid positive integer."
        );
    }


    if (
        !isPositiveInteger($amenityId)
    ) {

        sendError(
            422,
            "amenity_ids[{$index}] must be a valid positive integer."
        );
    }


    $validatedAmenityIds[] =
        (int) $amenityId;
}


/*
|--------------------------------------------------------------------------
| Remove Duplicate Amenity IDs
|--------------------------------------------------------------------------
*/

$uniqueAmenityIds =
    array_values(
        array_unique(
            $validatedAmenityIds
        )
    );


/*
|--------------------------------------------------------------------------
| Check Room Exists
|--------------------------------------------------------------------------
*/

$roomStmt =
    $conn->prepare("
        SELECT

            r.id,
            r.room_number,
            r.name,
            r.status,

            rt.id AS room_type_id,
            rt.name AS room_type_name,
            rt.status AS room_type_status

        FROM rooms r

        INNER JOIN room_types rt
            ON rt.id = r.room_type_id

        WHERE r.id = ?

        LIMIT 1
    ");


if (!$roomStmt) {

    sendError(
        500,
        'Failed to prepare room validation query.'
    );
}


$roomStmt->bind_param(
    'i',
    $roomId
);


if (
    !$roomStmt->execute()
) {

    $roomStmt->close();

    sendError(
        500,
        'Failed to check room.'
    );
}


$roomResult =
    $roomStmt->get_result();


if (
    $roomResult->num_rows === 0
) {

    $roomStmt->close();

    sendError(
        404,
        'Room not found.'
    );
}


$room =
    $roomResult->fetch_assoc();


$roomStmt->close();


/*
|--------------------------------------------------------------------------
| Validate Room Type Status
|--------------------------------------------------------------------------
*/

if (
    $room['room_type_status'] !== 'active'
) {

    sendError(
        409,
        'Cannot update room amenities because the room type is inactive.',
        [
            'room_id' =>
                $roomId,

            'room_type_id' =>
                (int) $room['room_type_id'],

            'room_type_name' =>
                $room['room_type_name'],

            'room_type_status' =>
                $room['room_type_status']
        ]
    );
}


/*
|--------------------------------------------------------------------------
| Validate Amenity IDs
|--------------------------------------------------------------------------
|
| If empty array is provided, no amenity
| validation is required.
|
*/

$validAmenities = [];


if (
    !empty($uniqueAmenityIds)
) {

    /*
    |--------------------------------------------------------------------------
    | Create Placeholders
    |--------------------------------------------------------------------------
    */

    $placeholders =
        implode(
            ',',
            array_fill(
                0,
                count($uniqueAmenityIds),
                '?'
            )
        );


    /*
    |--------------------------------------------------------------------------
    | Build Dynamic Types
    |--------------------------------------------------------------------------
    */

    $types =
        str_repeat(
            'i',
            count($uniqueAmenityIds)
        );


    /*
    |--------------------------------------------------------------------------
    | Fetch Active Amenities
    |--------------------------------------------------------------------------
    */

    $amenitySql = "
        SELECT

            id,
            name,
            icon,
            status,
            created_at

        FROM amenities

        WHERE id IN ({$placeholders})

          AND status = 'active'

        ORDER BY name ASC
    ";


    $amenityStmt =
        $conn->prepare(
            $amenitySql
        );


    if (!$amenityStmt) {

        sendError(
            500,
            'Failed to prepare amenity validation query.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Dynamic Bind Parameters
    |--------------------------------------------------------------------------
    */

    $bindParams = [];

    $bindParams[] =
        $types;


    foreach (
        $uniqueAmenityIds as $key => $value
    ) {

        $bindParams[] =
            &$uniqueAmenityIds[$key];
    }


    call_user_func_array(
        [$amenityStmt, 'bind_param'],
        $bindParams
    );


    /*
    |--------------------------------------------------------------------------
    | Execute
    |--------------------------------------------------------------------------
    */

    if (
        !$amenityStmt->execute()
    ) {

        $amenityStmt->close();

        sendError(
            500,
            'Failed to validate amenities.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Fetch Valid Amenities
    |--------------------------------------------------------------------------
    */

    $amenityResult =
        $amenityStmt->get_result();


    while (
        $amenityRow =
        $amenityResult->fetch_assoc()
    ) {

        $validAmenities[] = [

            'id' =>
                (int) $amenityRow['id'],

            'name' =>
                $amenityRow['name'],

            'icon' =>
                $amenityRow['icon'],

            'status' =>
                $amenityRow['status'],

            'created_at' =>
                $amenityRow['created_at']
        ];
    }


    $amenityStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Validate All IDs Exist + Active
    |--------------------------------------------------------------------------
    */

    $validAmenityIds = [];


    foreach (
        $validAmenities as $amenity
    ) {

        $validAmenityIds[] =
            (int) $amenity['id'];
    }


    $invalidAmenityIds =
        array_values(
            array_diff(
                $uniqueAmenityIds,
                $validAmenityIds
            )
        );


    if (
        !empty($invalidAmenityIds)
    ) {

        sendError(
            422,
            'One or more amenity IDs are invalid, inactive, or do not exist.',
            [
                'invalid_amenity_ids' =>
                    $invalidAmenityIds,

                'valid_amenity_ids' =>
                    $validAmenityIds
            ]
        );
    }
}


/*
|--------------------------------------------------------------------------
| Start Database Transaction
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();


try {

    /*
    |--------------------------------------------------------------------------
    | Get Existing Amenity Mappings
    |--------------------------------------------------------------------------
    */

    $existingStmt =
        $conn->prepare("
            SELECT
                amenity_id

            FROM room_amenities

            WHERE room_id = ?
        ");


    if (!$existingStmt) {

        throw new Exception(
            'Failed to prepare existing amenity query.'
        );
    }


    $existingStmt->bind_param(
        'i',
        $roomId
    );


    if (
        !$existingStmt->execute()
    ) {

        $existingStmt->close();

        throw new Exception(
            'Failed to fetch existing room amenities.'
        );
    }


    $existingResult =
        $existingStmt->get_result();


    $existingAmenityIds = [];


    while (
        $existingRow =
        $existingResult->fetch_assoc()
    ) {

        $existingAmenityIds[] =
            (int) $existingRow['amenity_id'];
    }


    $existingStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Delete Existing Mappings
    |--------------------------------------------------------------------------
    */

    $deleteStmt =
        $conn->prepare("
            DELETE FROM room_amenities

            WHERE room_id = ?
        ");


    if (!$deleteStmt) {

        throw new Exception(
            'Failed to prepare existing amenity delete query.'
        );
    }


    $deleteStmt->bind_param(
        'i',
        $roomId
    );


    if (
        !$deleteStmt->execute()
    ) {

        $deleteStmt->close();

        throw new Exception(
            'Failed to remove existing room amenities.'
        );
    }


    $removedCount =
        $deleteStmt->affected_rows;


    $deleteStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Insert New Amenity Mappings
    |--------------------------------------------------------------------------
    */

    $addedCount = 0;


    if (
        !empty($uniqueAmenityIds)
    ) {

        $insertStmt =
            $conn->prepare("
                INSERT INTO room_amenities
                (
                    room_id,
                    amenity_id
                )
                VALUES
                (
                    ?,
                    ?
                )
            ");


        if (!$insertStmt) {

            throw new Exception(
                'Failed to prepare amenity mapping insert query.'
            );
        }


        foreach (
            $uniqueAmenityIds as $amenityId
        ) {

            $insertStmt->bind_param(
                'ii',
                $roomId,
                $amenityId
            );


            if (
                !$insertStmt->execute()
            ) {

                $insertStmt->close();

                throw new Exception(
                    'Failed to assign amenity to room.'
                );
            }


            $addedCount++;
        }


        $insertStmt->close();
    }


    /*
    |--------------------------------------------------------------------------
    | Commit Transaction
    |--------------------------------------------------------------------------
    */

    $conn->commit();


} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | Rollback
    |--------------------------------------------------------------------------
    */

    $conn->rollback();


    /*
    |--------------------------------------------------------------------------
    | Log Error
    |--------------------------------------------------------------------------
    */

    error_log(
        '[ROOM AMENITY UPDATE] ' .
        $e->getMessage()
    );


    /*
    |--------------------------------------------------------------------------
    | Error Response
    |--------------------------------------------------------------------------
    */

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to update room amenities.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Calculate Added / Removed / Unchanged
|--------------------------------------------------------------------------
*/

$finalAmenityIds =
    $uniqueAmenityIds;


$addedAmenityIds =
    array_values(
        array_diff(
            $finalAmenityIds,
            $existingAmenityIds
        )
    );


$removedAmenityIds =
    array_values(
        array_diff(
            $existingAmenityIds,
            $finalAmenityIds
        )
    );


$unchangedAmenityIds =
    array_values(
        array_intersect(
            $existingAmenityIds,
            $finalAmenityIds
        )
    );


/*
|--------------------------------------------------------------------------
| Fetch Final Room Amenities
|--------------------------------------------------------------------------
*/

$finalStmt =
    $conn->prepare("
        SELECT

            a.id,
            a.name,
            a.icon,
            a.status,
            a.created_at

        FROM room_amenities ra

        INNER JOIN amenities a
            ON a.id = ra.amenity_id

        WHERE ra.room_id = ?

        ORDER BY
            a.name ASC
    ");


$finalAmenities = [];


if (
    $finalStmt
) {

    $finalStmt->bind_param(
        'i',
        $roomId
    );


    if (
        $finalStmt->execute()
    ) {

        $finalResult =
            $finalStmt->get_result();


        while (
            $finalRow =
            $finalResult->fetch_assoc()
        ) {

            $finalAmenities[] = [

                'id' =>
                    (int) $finalRow['id'],

                'name' =>
                    $finalRow['name'],

                'icon' =>
                    $finalRow['icon'],

                'status' =>
                    $finalRow['status'],

                'created_at' =>
                    $finalRow['created_at']
            ];
        }
    }


    $finalStmt->close();
}


/*
|--------------------------------------------------------------------------
| Success Response
|--------------------------------------------------------------------------
*/

http_response_code(200);


echo json_encode([

    'status' => true,

    'message' =>
        'Room amenities updated successfully.',

    'data' => [

        'room' => [

            'room_id' =>
                $roomId,

            'room_number' =>
                $room['room_number'],

            'room_name' =>
                $room['name'],

            'room_status' =>
                $room['status'],

            'room_type_id' =>
                (int) $room['room_type_id'],

            'room_type_name' =>
                $room['room_type_name']
        ],

        'amenities' => [

            'total' =>
                count($finalAmenities),

            'items' =>
                $finalAmenities
        ],

        'changes' => [

            'previous_amenity_ids' =>
                $existingAmenityIds,

            'current_amenity_ids' =>
                $finalAmenityIds,

            'added_amenity_ids' =>
                $addedAmenityIds,

            'removed_amenity_ids' =>
                $removedAmenityIds,

            'unchanged_amenity_ids' =>
                $unchangedAmenityIds
        ],

        'summary' => [

            'previous_count' =>
                count($existingAmenityIds),

            'current_count' =>
                count($finalAmenityIds),

            'added_count' =>
                count($addedAmenityIds),

            'removed_count' =>
                count($removedAmenityIds),

            'unchanged_count' =>
                count($unchangedAmenityIds)
        ],

        'updated_by' => [

            'user_id' =>
                (int) $user['sub'],

            'role' =>
                $user['role']
        ]
    ]

], JSON_UNESCAPED_UNICODE);

exit;