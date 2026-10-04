<?php


// =========================================================
// CORS
// =========================================================

header(
    "Content-Type: application/json; charset=utf-8"
);

header(
    "Access-Control-Allow-Origin: *"
);

header(
    "Access-Control-Allow-Methods: POST, OPTIONS"
);

header(
    "Access-Control-Allow-Headers: Content-Type, Authorization"
);


// =========================================================
// OPTIONS
// =========================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'OPTIONS'
) {

    http_response_code(200);

    exit;
}


// =========================================================
// DATABASE + MIDDLEWARE
// =========================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/middleware.php';


// =========================================================
// AUTHENTICATION
// =========================================================

$user = requireRole([
    'admin',
    'staff'
]);


// =========================================================
// REQUEST METHOD
// =========================================================

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {

    http_response_code(405);

    echo json_encode([
        'status' => false,
        'message' => 'Only POST method is allowed.'
    ]);

    exit;
}


// =========================================================
// READ JSON REQUEST
// =========================================================

$rawInput =
    file_get_contents('php://input');


if (
    $rawInput === false ||
    trim($rawInput) === ''
) {

    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' => 'Request body is required.'
    ]);

    exit;
}


// =========================================================
// DECODE JSON
// =========================================================

$data =
    json_decode(
        $rawInput,
        true
    );


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


// =========================================================
// ALLOWED FIELDS
// =========================================================

$allowedFields = [

    'room_type_id',
    'room_number',
    'name',
    'description',
    'bed_count',
    'guest_capacity',
    'price_per_night',
    'status'

];


// =========================================================
// UNKNOWN FIELD VALIDATION
// =========================================================

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

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                "Unknown field: {$key}"
        ]);

        exit;
    }
}


// =========================================================
// HELPER FUNCTIONS
// =========================================================

function getStringValue(
    $data,
    $key
) {

    if (
        !isset($data[$key])
    ) {

        return null;
    }


    if (
        is_array($data[$key]) ||
        is_object($data[$key])
    ) {

        return null;
    }


    return trim(
        (string)$data[$key]
    );
}


function isPositiveInteger(
    $value
) {

    if (
        is_array($value) ||
        is_object($value)
    ) {

        return false;
    }


    if (
        !is_int($value) &&
        !ctype_digit((string)$value)
    ) {

        return false;
    }


    return (int)$value > 0;
}


// =========================================================
// VALIDATE ROOM TYPE ID
// =========================================================

if (
    !isset($data['room_type_id'])
) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'room_type_id is required.'
    ]);

    exit;
}


if (
    !isPositiveInteger(
        $data['room_type_id']
    )
) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'room_type_id must be a valid positive integer.'
    ]);

    exit;
}


$roomTypeId =
    (int)$data['room_type_id'];


// =========================================================
// VALIDATE ROOM NUMBER
// =========================================================

if (
    !isset($data['room_number'])
) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'room_number is required.'
    ]);

    exit;
}


$roomNumber =
    getStringValue(
        $data,
        'room_number'
    );


if (
    $roomNumber === null ||
    $roomNumber === ''
) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'room_number is required.'
    ]);

    exit;
}


if (
    strlen($roomNumber) > 50
) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'room_number cannot exceed 50 characters.'
    ]);

    exit;
}


// =========================================================
// ROOM NUMBER FORMAT
// =========================================================

if (
    !preg_match(
        '/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/',
        $roomNumber
    )
) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'room_number contains invalid characters.'
    ]);

    exit;
}


// =========================================================
// VALIDATE NAME
// =========================================================

if (
    !isset($data['name'])
) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'name is required.'
    ]);

    exit;
}


$name =
    getStringValue(
        $data,
        'name'
    );


if (
    $name === null ||
    $name === ''
) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'name is required.'
    ]);

    exit;
}


if (
    strlen($name) < 2 ||
    strlen($name) > 100
) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'name must be between 2 and 100 characters.'
    ]);

    exit;
}


// =========================================================
// NAME FORMAT
// =========================================================

if (
    !preg_match(
        '/^[A-Za-z0-9]+(?: [A-Za-z0-9]+)*$/',
        $name
    )
) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'name can contain only letters, numbers and single spaces.'
    ]);

    exit;
}


// =========================================================
// DESCRIPTION
// =========================================================
//
// Optional
// =========================================================

$description = null;


if (
    array_key_exists(
        'description',
        $data
    )
) {

    $description =
        getStringValue(
            $data,
            'description'
        );


    if (
        $description === null
    ) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'description must be a valid string.'
        ]);

        exit;
    }


    if (
        $description === ''
    ) {

        $description = null;
    }


    if (
        $description !== null &&
        strlen($description) > 2000
    ) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'description cannot exceed 2000 characters.'
        ]);

        exit;
    }
}


// =========================================================
// VALIDATE BED COUNT
// =========================================================

if (
    !isset($data['bed_count'])
) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'bed_count is required.'
    ]);

    exit;
}


if (
    !isPositiveInteger(
        $data['bed_count']
    )
) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'bed_count must be a valid positive integer.'
    ]);

    exit;
}


$bedCount =
    (int)$data['bed_count'];


if (
    $bedCount < 1 ||
    $bedCount > 20
) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'bed_count must be between 1 and 20.'
    ]);

    exit;
}


// =========================================================
// VALIDATE GUEST CAPACITY
// =========================================================

if (
    !isset($data['guest_capacity'])
) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'guest_capacity is required.'
    ]);

    exit;
}


if (
    !isPositiveInteger(
        $data['guest_capacity']
    )
) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'guest_capacity must be a valid positive integer.'
    ]);

    exit;
}


$guestCapacity =
    (int)$data['guest_capacity'];


if (
    $guestCapacity < 1 ||
    $guestCapacity > 50
) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'guest_capacity must be between 1 and 50.'
    ]);

    exit;
}


// =========================================================
// VALIDATE PRICE PER NIGHT
// =========================================================

if (
    !isset($data['price_per_night'])
) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'price_per_night is required.'
    ]);

    exit;
}


$priceInput =
    $data['price_per_night'];


// =========================================================
// PRICE MUST BE NUMERIC
// =========================================================

if (
    is_array($priceInput) ||
    is_object($priceInput) ||
    !is_numeric($priceInput)
) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'price_per_night must be a valid number.'
    ]);

    exit;
}


$priceString =
    trim(
        (string)$priceInput
    );


// =========================================================
// PRICE FORMAT
// =========================================================

if (
    !preg_match(
        '/^\d+(?:\.\d+)?$/',
        $priceString
    )
) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'price_per_night must be a valid positive number.'
    ]);

    exit;
}


$pricePerNight =
    (float)$priceInput;


// =========================================================
// PRICE POSITIVE
// =========================================================

if (
    $pricePerNight <= 0
) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'price_per_night must be greater than 0.'
    ]);

    exit;
}


// =========================================================
// MAXIMUM PRICE
// =========================================================

if (
    $pricePerNight > 10000000
) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'price_per_night cannot exceed 10000000.'
    ]);

    exit;
}


// =========================================================
// MAXIMUM 2 DECIMAL PLACES
// =========================================================

if (
    preg_match(
        '/^\d+(?:\.(\d+))?$/',
        $priceString,
        $matches
    )
) {

    $decimalPart =
        $matches[1] ?? '';


    if (
        strlen($decimalPart) > 2
    ) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'price_per_night can have maximum 2 decimal places.'
        ]);

        exit;
    }
}


$pricePerNight =
    round(
        $pricePerNight,
        2
    );


// =========================================================
// GST
// =========================================================
//
// Room tariff is GST inclusive.
// Fixed GST = 18%.
// =========================================================

$gstRate = 18.00;


// =========================================================
// VALIDATE STATUS
// =========================================================
//
// Actual DB enum:
//
// available
// maintenance
// inactive
// =========================================================

$status =
    'available';


if (
    array_key_exists(
        'status',
        $data
    )
) {

    $status =
        getStringValue(
            $data,
            'status'
        );


    if (
        $status === null ||
        $status === ''
    ) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'status is required when provided.'
        ]);

        exit;
    }


    if (
        !in_array(
            $status,
            [
                'available',
                'inactive',
                'maintenance'
            ],
            true
        )
    ) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Invalid status. Allowed values are available, inactive or maintenance.'
        ]);

        exit;
    }
}


// =========================================================
// CHECK ROOM TYPE EXISTS
// =========================================================

$roomTypeStmt =
    $conn->prepare("
        SELECT
            id,
            name,
            status
        FROM room_types
        WHERE id = ?
        LIMIT 1
    ");


if (
    !$roomTypeStmt
) {

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to prepare room type validation query.'
    ]);

    exit;
}


$roomTypeStmt->bind_param(
    'i',
    $roomTypeId
);


if (
    !$roomTypeStmt->execute()
) {

    $roomTypeStmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to validate room type.'
    ]);

    exit;
}


$roomTypeResult =
    $roomTypeStmt->get_result();


if (
    !$roomTypeResult
) {

    $roomTypeStmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to process room type validation.'
    ]);

    exit;
}


// =========================================================
// ROOM TYPE NOT FOUND
// =========================================================

if (
    $roomTypeResult->num_rows === 0
) {

    $roomTypeStmt->close();

    http_response_code(404);

    echo json_encode([
        'status' => false,
        'message' =>
            'Selected room type does not exist.'
    ]);

    exit;
}


$roomType =
    $roomTypeResult->fetch_assoc();


$roomTypeStmt->close();


// =========================================================
// ROOM TYPE MUST BE ACTIVE
// =========================================================

if (
    $roomType['status'] !== 'active'
) {

    http_response_code(409);

    echo json_encode([
        'status' => false,
        'message' =>
            'Cannot create a room under an inactive room type.',
        'data' => [

            'room_type_id' =>
                $roomTypeId,

            'room_type_name' =>
                $roomType['name'],

            'room_type_status' =>
                $roomType['status']

        ]
    ]);

    exit;
}


// =========================================================
// CHECK DUPLICATE ROOM NUMBER
// =========================================================

$duplicateStmt =
    $conn->prepare("
        SELECT
            id,
            room_number
        FROM rooms
        WHERE room_number = ?
        LIMIT 1
    ");


if (
    !$duplicateStmt
) {

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to prepare duplicate room check.'
    ]);

    exit;
}


$duplicateStmt->bind_param(
    's',
    $roomNumber
);


if (
    !$duplicateStmt->execute()
) {

    $duplicateStmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to check duplicate room number.'
    ]);

    exit;
}


$duplicateResult =
    $duplicateStmt->get_result();


if (
    !$duplicateResult
) {

    $duplicateStmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to process duplicate room check.'
    ]);

    exit;
}


// =========================================================
// DUPLICATE ROOM
// =========================================================

if (
    $duplicateResult->num_rows > 0
) {

    $existingRoom =
        $duplicateResult->fetch_assoc();


    $duplicateStmt->close();

    http_response_code(409);

    echo json_encode([
        'status' => false,
        'message' =>
            'Room number already exists.',
        'data' => [

            'existing_room_id' =>
                (int)$existingRoom['id'],

            'room_number' =>
                $existingRoom['room_number']

        ]
    ]);

    exit;
}


$duplicateStmt->close();


// =========================================================
// INSERT ROOM
// =========================================================

$conn->begin_transaction();


try {

    // =====================================================
    // PREPARE INSERT
    // =====================================================

    $insertStmt =
        $conn->prepare("
            INSERT INTO rooms
            (
                room_type_id,
                room_number,
                name,
                description,
                bed_count,
                guest_capacity,
                price_per_night,
                gst_rate,
                price_includes_gst,
                status
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
                ?,
                ?
            )
        ");


    if (
        !$insertStmt
    ) {

        throw new Exception(
            'Failed to prepare room insert query.'
        );
    }


    // =====================================================
    // GST INCLUSIVE
    // =====================================================

    $priceIncludesGst =
        1;


    // =====================================================
    // BIND PARAMETERS
    // =====================================================

    $insertStmt->bind_param(
        'isssiiddis',
        $roomTypeId,
        $roomNumber,
        $name,
        $description,
        $bedCount,
        $guestCapacity,
        $pricePerNight,
        $gstRate,
        $priceIncludesGst,
        $status
    );


    // =====================================================
    // EXECUTE
    // =====================================================

    if (
        !$insertStmt->execute()
    ) {

        $errorCode =
            $insertStmt->errno;


        $insertStmt->close();


        if (
            $errorCode === 1062
        ) {

            throw new Exception(
                'Room number already exists.'
            );
        }


        if (
            $errorCode === 1265
        ) {

            throw new Exception(
                'Invalid room status value.'
            );
        }


        throw new Exception(
            'Failed to create room.'
        );
    }


    // =====================================================
    // CREATED ROOM ID
    // =====================================================

    $roomId =
        (int)$conn->insert_id;


    $insertStmt->close();


    if (
        $roomId <= 0
    ) {

        throw new Exception(
            'Room was not created successfully.'
        );
    }


    // =====================================================
    // COMMIT
    // =====================================================

    $conn->commit();


    // =====================================================
    // GST BREAKDOWN
    // =====================================================

    $basePrice =
        round(
            $pricePerNight / 1.18,
            2
        );


    $gstAmount =
        round(
            $pricePerNight - $basePrice,
            2
        );


    // =====================================================
    // SUCCESS RESPONSE
    // =====================================================

    http_response_code(201);


    echo json_encode(
        [
            'status' => true,

            'message' =>
                'Room created successfully.',

            'data' => [

                'room_id' =>
                    $roomId,


                'room_type' => [

                    'id' =>
                        $roomTypeId,

                    'name' =>
                        $roomType['name']

                ],


                'room_number' =>
                    $roomNumber,


                'name' =>
                    $name,


                'description' =>
                    $description,


                'bed_count' =>
                    $bedCount,


                'guest_capacity' =>
                    $guestCapacity,


                'pricing' => [

                    'price_per_night' =>
                        number_format(
                            $pricePerNight,
                            2,
                            '.',
                            ''
                        ),

                    'gst_rate' =>
                        number_format(
                            $gstRate,
                            2,
                            '.',
                            ''
                        ),

                    'price_includes_gst' =>
                        true,

                    'base_price' =>
                        number_format(
                            $basePrice,
                            2,
                            '.',
                            ''
                        ),

                    'gst_amount' =>
                        number_format(
                            $gstAmount,
                            2,
                            '.',
                            ''
                        )

                ],


                'status' =>
                    $status,


                'created_by' => [

                    'user_id' =>
                        (int)$user['sub'],

                    'role' =>
                        $user['role']

                ]

            ]
        ],
        JSON_UNESCAPED_UNICODE
    );

    exit;


} catch (
    Throwable $e
) {

    // =====================================================
    // ROLLBACK
    // =====================================================

    $conn->rollback();


    // =====================================================
    // LOG ERROR
    // =====================================================

    error_log(
        '[ROOM CREATE] ' .
        $e->getMessage()
    );


    // =====================================================
    // DUPLICATE ROOM
    // =====================================================

    if (
        $e->getMessage() ===
        'Room number already exists.'
    ) {

        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' =>
                'Room number already exists.'
        ]);

        exit;
    }


    // =====================================================
    // INVALID STATUS
    // =====================================================

    if (
        $e->getMessage() ===
        'Invalid room status value.'
    ) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Invalid room status value.'
        ]);

        exit;
    }


    // =====================================================
    // GENERAL ERROR
    // =====================================================

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to create room.'
    ]);

    exit;
}