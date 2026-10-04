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
| Admin + Staff can update rooms.
|
*/

$user = requireRole(['admin', 'staff']);

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
| Read JSON Body
|--------------------------------------------------------------------------
*/

$rawInput = file_get_contents('php://input');

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

/*
|--------------------------------------------------------------------------
| Decode JSON
|--------------------------------------------------------------------------
*/

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
| Helper Functions
|--------------------------------------------------------------------------
*/

function getStringValue($data, $key)
{
    if (!array_key_exists($key, $data)) {
        return null;
    }

    if (
        is_array($data[$key]) ||
        is_object($data[$key])
    ) {
        return null;
    }

    return trim((string) $data[$key]);
}

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
| Validate room_id
|--------------------------------------------------------------------------
|
| room_id is the ONLY mandatory field.
|
*/

if (!array_key_exists('room_id', $data)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'room_id is required.'
    ]);

    exit;
}

if (!isPositiveInteger($data['room_id'])) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'room_id must be a valid positive integer.'
    ]);

    exit;
}

$roomId = (int) $data['room_id'];

/*
|--------------------------------------------------------------------------
| Allowed Update Fields
|--------------------------------------------------------------------------
*/

$allowedUpdateFields = [
    'room_type_id',
    'room_number',
    'name',
    'description',
    'bed_count',
    'guest_capacity',
    'price_per_night',
    'status'
];

/*
|--------------------------------------------------------------------------
| Check Unknown Fields
|--------------------------------------------------------------------------
*/

foreach ($data as $key => $value) {

    if (
        $key !== 'room_id' &&
        !in_array(
            $key,
            $allowedUpdateFields,
            true
        )
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Invalid field provided: ' . $key
        ]);

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Check At Least One Update Field
|--------------------------------------------------------------------------
*/

$fieldsToUpdate = [];

foreach ($allowedUpdateFields as $field) {

    if (array_key_exists($field, $data)) {
        $fieldsToUpdate[] = $field;
    }
}

if (empty($fieldsToUpdate)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'At least one field is required to update.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Get Existing Room
|--------------------------------------------------------------------------
*/

$roomCheckStmt = $conn->prepare("
    SELECT
        id,
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
    FROM rooms
    WHERE id = ?
    LIMIT 1
");

if (!$roomCheckStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare room validation query.'
    ]);

    exit;
}

$roomCheckStmt->bind_param(
    'i',
    $roomId
);

if (!$roomCheckStmt->execute()) {

    $roomCheckStmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to check room.'
    ]);

    exit;
}

$roomResult = $roomCheckStmt->get_result();

if ($roomResult->num_rows === 0) {

    $roomCheckStmt->close();

    http_response_code(404);

    echo json_encode([
        'status' => false,
        'message' => 'Room not found.'
    ]);

    exit;
}

$currentRoom = $roomResult->fetch_assoc();

$roomCheckStmt->close();

/*
|--------------------------------------------------------------------------
| Prepare Final Values
|--------------------------------------------------------------------------
|
| If a field is not sent, existing DB value is retained.
|
*/

$roomTypeId = (int) $currentRoom['room_type_id'];

$roomNumber = $currentRoom['room_number'];

$name = $currentRoom['name'];

$description = $currentRoom['description'];

$bedCount = (int) $currentRoom['bed_count'];

$guestCapacity = (int) $currentRoom['guest_capacity'];

$pricePerNight = (float) $currentRoom['price_per_night'];

$status = $currentRoom['status'];

/*
|--------------------------------------------------------------------------
| Room GST
|--------------------------------------------------------------------------
|
| Room tariff is always 18% GST inclusive.
|
*/

$gstRate = 18.00;

$priceIncludesGst = 1;

/*
|--------------------------------------------------------------------------
| Validate room_type_id
|--------------------------------------------------------------------------
*/

if (array_key_exists('room_type_id', $data)) {

    if (!isPositiveInteger($data['room_type_id'])) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'room_type_id must be a valid positive integer.'
        ]);

        exit;
    }

    $roomTypeId = (int) $data['room_type_id'];
}

/*
|--------------------------------------------------------------------------
| Validate room_number
|--------------------------------------------------------------------------
*/

if (array_key_exists('room_number', $data)) {

    $roomNumber = getStringValue(
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
            'message' => 'room_number cannot be empty.'
        ]);

        exit;
    }

    if (strlen($roomNumber) > 50) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'room_number cannot exceed 50 characters.'
        ]);

        exit;
    }

    if (
        !preg_match(
            '/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/',
            $roomNumber
        )
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'room_number contains invalid characters.'
        ]);

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Validate name
|--------------------------------------------------------------------------
*/

if (array_key_exists('name', $data)) {

    $name = getStringValue(
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
            'message' => 'name cannot be empty.'
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
            'message' => 'name must be between 2 and 100 characters.'
        ]);

        exit;
    }

    if (
        !preg_match(
            '/^[A-Za-z0-9]+(?: [A-Za-z0-9]+)*$/',
            $name
        )
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'name can contain only letters, numbers and single spaces.'
        ]);

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Validate description
|--------------------------------------------------------------------------
|
| null or empty string can clear the description.
|
*/

if (array_key_exists('description', $data)) {

    if ($data['description'] === null) {

        $description = null;

    } else {

        $description = getStringValue(
            $data,
            'description'
        );

        if ($description === null) {

            http_response_code(422);

            echo json_encode([
                'status' => false,
                'message' => 'description must be a valid string or null.'
            ]);

            exit;
        }

        if ($description === '') {
            $description = null;
        }
    }

    if (
        $description !== null &&
        strlen($description) > 2000
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'description cannot exceed 2000 characters.'
        ]);

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Validate bed_count
|--------------------------------------------------------------------------
*/

if (array_key_exists('bed_count', $data)) {

    if (!isPositiveInteger($data['bed_count'])) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'bed_count must be a valid positive integer.'
        ]);

        exit;
    }

    $bedCount = (int) $data['bed_count'];

    if (
        $bedCount < 1 ||
        $bedCount > 20
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'bed_count must be between 1 and 20.'
        ]);

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Validate guest_capacity
|--------------------------------------------------------------------------
*/

if (array_key_exists('guest_capacity', $data)) {

    if (!isPositiveInteger($data['guest_capacity'])) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'guest_capacity must be a valid positive integer.'
        ]);

        exit;
    }

    $guestCapacity = (int) $data['guest_capacity'];

    if (
        $guestCapacity < 1 ||
        $guestCapacity > 50
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'guest_capacity must be between 1 and 50.'
        ]);

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Validate price_per_night
|--------------------------------------------------------------------------
|
| Price is GST inclusive.
|
*/

if (array_key_exists('price_per_night', $data)) {

    $priceInput = $data['price_per_night'];

    if (
        is_array($priceInput) ||
        is_object($priceInput) ||
        !is_numeric($priceInput)
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'price_per_night must be a valid number.'
        ]);

        exit;
    }

    $priceString = trim((string) $priceInput);

    /*
    |--------------------------------------------------------------------------
    | Reject negative values and invalid numeric formats
    |--------------------------------------------------------------------------
    */

    if (
        !preg_match(
            '/^\d+(?:\.\d+)?$/',
            $priceString
        )
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'price_per_night must be a valid positive number.'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Maximum 2 Decimal Places
    |--------------------------------------------------------------------------
    */

    if (
        preg_match(
            '/^\d+(?:\.(\d+))?$/',
            $priceString,
            $matches
        )
    ) {

        $decimalPart = $matches[1] ?? '';

        if (strlen($decimalPart) > 2) {

            http_response_code(422);

            echo json_encode([
                'status' => false,
                'message' => 'price_per_night can have maximum 2 decimal places.'
            ]);

            exit;
        }
    }

    $pricePerNight = (float) $priceInput;

    if ($pricePerNight <= 0) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'price_per_night must be greater than 0.'
        ]);

        exit;
    }

    if ($pricePerNight > 10000000) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'price_per_night cannot exceed 10000000.'
        ]);

        exit;
    }

    $pricePerNight = round(
        $pricePerNight,
        2
    );
}

/*
|--------------------------------------------------------------------------
| Validate status
|--------------------------------------------------------------------------
|
| IMPORTANT:
| rooms.status ENUM:
| available
| maintenance
| inactive
|
*/

if (array_key_exists('status', $data)) {

    $status = getStringValue(
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
            'message' => 'status cannot be empty.'
        ]);

        exit;
    }

    if (
        !in_array(
            $status,
            [
                'available',
                'maintenance',
                'inactive'
            ],
            true
        )
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Invalid status. Allowed values are available, maintenance or inactive.'
        ]);

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Validate Room Type
|--------------------------------------------------------------------------
|
| If room_type_id is supplied, it must exist
| and must be active.
|
*/

if (array_key_exists('room_type_id', $data)) {

    $roomTypeStmt = $conn->prepare("
        SELECT
            id,
            name,
            status
        FROM room_types
        WHERE id = ?
        LIMIT 1
    ");

    if (!$roomTypeStmt) {

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Failed to prepare room type validation query.'
        ]);

        exit;
    }

    $roomTypeStmt->bind_param(
        'i',
        $roomTypeId
    );

    if (!$roomTypeStmt->execute()) {

        $roomTypeStmt->close();

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Failed to validate room type.'
        ]);

        exit;
    }

    $roomTypeResult = $roomTypeStmt->get_result();

    if ($roomTypeResult->num_rows === 0) {

        $roomTypeStmt->close();

        http_response_code(404);

        echo json_encode([
            'status' => false,
            'message' => 'Selected room type does not exist.'
        ]);

        exit;
    }

    $roomType = $roomTypeResult->fetch_assoc();

    $roomTypeStmt->close();

    if ($roomType['status'] !== 'active') {

        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' => 'Cannot assign room to an inactive room type.',
            'data' => [
                'room_type_id' => $roomTypeId,
                'room_type_name' => $roomType['name'],
                'room_type_status' => $roomType['status']
            ]
        ]);

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Get Existing Room Type
|--------------------------------------------------------------------------
|
| Needed for response when room_type_id is not updated.
|
*/

if (!array_key_exists('room_type_id', $data)) {

    $roomTypeStmt = $conn->prepare("
        SELECT
            id,
            name,
            status
        FROM room_types
        WHERE id = ?
        LIMIT 1
    ");

    if (!$roomTypeStmt) {

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Failed to prepare room type query.'
        ]);

        exit;
    }

    $roomTypeStmt->bind_param(
        'i',
        $roomTypeId
    );

    if (!$roomTypeStmt->execute()) {

        $roomTypeStmt->close();

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Failed to fetch room type.'
        ]);

        exit;
    }

    $roomTypeResult = $roomTypeStmt->get_result();

    if ($roomTypeResult->num_rows === 0) {

        $roomTypeStmt->close();

        http_response_code(404);

        echo json_encode([
            'status' => false,
            'message' => 'Existing room type was not found.'
        ]);

        exit;
    }

    $roomType = $roomTypeResult->fetch_assoc();

    $roomTypeStmt->close();

} else {

    /*
    |--------------------------------------------------------------------------
    | Fetch Room Type Details For Response
    |--------------------------------------------------------------------------
    */

    $roomTypeStmt = $conn->prepare("
        SELECT
            id,
            name,
            status
        FROM room_types
        WHERE id = ?
        LIMIT 1
    ");

    if (!$roomTypeStmt) {

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Failed to prepare room type response query.'
        ]);

        exit;
    }

    $roomTypeStmt->bind_param(
        'i',
        $roomTypeId
    );

    if (!$roomTypeStmt->execute()) {

        $roomTypeStmt->close();

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Failed to fetch updated room type.'
        ]);

        exit;
    }

    $roomTypeResult = $roomTypeStmt->get_result();

    if ($roomTypeResult->num_rows === 0) {

        $roomTypeStmt->close();

        http_response_code(404);

        echo json_encode([
            'status' => false,
            'message' => 'Updated room type was not found.'
        ]);

        exit;
    }

    $roomType = $roomTypeResult->fetch_assoc();

    $roomTypeStmt->close();
}

/*
|--------------------------------------------------------------------------
| Check Duplicate Room Number
|--------------------------------------------------------------------------
|
| Only when room_number is being updated.
|
*/

if (array_key_exists('room_number', $data)) {

    $duplicateStmt = $conn->prepare("
        SELECT
            id,
            room_number
        FROM rooms
        WHERE room_number = ?
          AND id != ?
        LIMIT 1
    ");

    if (!$duplicateStmt) {

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Failed to prepare duplicate room check.'
        ]);

        exit;
    }

    $duplicateStmt->bind_param(
        'si',
        $roomNumber,
        $roomId
    );

    if (!$duplicateStmt->execute()) {

        $duplicateStmt->close();

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Failed to check duplicate room number.'
        ]);

        exit;
    }

    $duplicateResult = $duplicateStmt->get_result();

    if ($duplicateResult->num_rows > 0) {

        $existingRoom = $duplicateResult->fetch_assoc();

        $duplicateStmt->close();

        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' => 'Room number already exists.',
            'data' => [
                'existing_room_id' => (int) $existingRoom['id'],
                'room_number' => $existingRoom['room_number']
            ]
        ]);

        exit;
    }

    $duplicateStmt->close();
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
    | Build Dynamic UPDATE Query
    |--------------------------------------------------------------------------
    |
    | Only supplied fields will be updated.
    |
    */

    $setParts = [];

    $bindTypes = '';

    $bindValues = [];

    /*
    |--------------------------------------------------------------------------
    | room_type_id
    |--------------------------------------------------------------------------
    */

    if (array_key_exists('room_type_id', $data)) {

        $setParts[] = 'room_type_id = ?';

        $bindTypes .= 'i';

        $bindValues[] = $roomTypeId;
    }

    /*
    |--------------------------------------------------------------------------
    | room_number
    |--------------------------------------------------------------------------
    */

    if (array_key_exists('room_number', $data)) {

        $setParts[] = 'room_number = ?';

        $bindTypes .= 's';

        $bindValues[] = $roomNumber;
    }

    /*
    |--------------------------------------------------------------------------
    | name
    |--------------------------------------------------------------------------
    */

    if (array_key_exists('name', $data)) {

        $setParts[] = 'name = ?';

        $bindTypes .= 's';

        $bindValues[] = $name;
    }

    /*
    |--------------------------------------------------------------------------
    | description
    |--------------------------------------------------------------------------
    */

    if (array_key_exists('description', $data)) {

        $setParts[] = 'description = ?';

        $bindTypes .= 's';

        $bindValues[] = $description;
    }

    /*
    |--------------------------------------------------------------------------
    | bed_count
    |--------------------------------------------------------------------------
    */

    if (array_key_exists('bed_count', $data)) {

        $setParts[] = 'bed_count = ?';

        $bindTypes .= 'i';

        $bindValues[] = $bedCount;
    }

    /*
    |--------------------------------------------------------------------------
    | guest_capacity
    |--------------------------------------------------------------------------
    */

    if (array_key_exists('guest_capacity', $data)) {

        $setParts[] = 'guest_capacity = ?';

        $bindTypes .= 'i';

        $bindValues[] = $guestCapacity;
    }

    /*
    |--------------------------------------------------------------------------
    | price_per_night
    |--------------------------------------------------------------------------
    |
    | GST remains 18% inclusive.
    |
    */

    if (array_key_exists('price_per_night', $data)) {

        $setParts[] = 'price_per_night = ?';

        $bindTypes .= 'd';

        $bindValues[] = $pricePerNight;

        $setParts[] = 'gst_rate = ?';

        $bindTypes .= 'd';

        $bindValues[] = $gstRate;

        $setParts[] = 'price_includes_gst = ?';

        $bindTypes .= 'i';

        $bindValues[] = $priceIncludesGst;
    }

    /*
    |--------------------------------------------------------------------------
    | status
    |--------------------------------------------------------------------------
    */

    if (array_key_exists('status', $data)) {

        $setParts[] = 'status = ?';

        $bindTypes .= 's';

        $bindValues[] = $status;
    }

    /*
    |--------------------------------------------------------------------------
    | Safety Check
    |--------------------------------------------------------------------------
    */

    if (empty($setParts)) {

        throw new Exception(
            'No fields available for update.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Build SQL
    |--------------------------------------------------------------------------
    */

    $updateSql = "
        UPDATE rooms
        SET
            " . implode(', ', $setParts) . "
        WHERE id = ?
    ";

    /*
    |--------------------------------------------------------------------------
    | Add room_id
    |--------------------------------------------------------------------------
    */

    $bindTypes .= 'i';

    $bindValues[] = $roomId;

    /*
    |--------------------------------------------------------------------------
    | Prepare Statement
    |--------------------------------------------------------------------------
    */

    $updateStmt = $conn->prepare(
        $updateSql
    );

    if (!$updateStmt) {

        throw new Exception(
            'Failed to prepare room update query.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Bind Dynamic Parameters
    |--------------------------------------------------------------------------
    */

    $bindParams = [];

    $bindParams[] = $bindTypes;

    foreach ($bindValues as $key => $value) {
        $bindParams[] = &$bindValues[$key];
    }

    call_user_func_array(
        [
            $updateStmt,
            'bind_param'
        ],
        $bindParams
    );

    /*
    |--------------------------------------------------------------------------
    | Execute Update
    |--------------------------------------------------------------------------
    */

    if (!$updateStmt->execute()) {

        $errorCode = $updateStmt->errno;

        $errorMessage = $updateStmt->error;

        $updateStmt->close();

        if ($errorCode === 1062) {

            throw new Exception(
                'Room number already exists.'
            );
        }

        throw new Exception(
            'Failed to update room: ' . $errorMessage
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Affected Rows
    |--------------------------------------------------------------------------
    */

    $affectedRows = $updateStmt->affected_rows;

    $updateStmt->close();

    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $conn->commit();

    /*
    |--------------------------------------------------------------------------
    | GST Breakdown
    |--------------------------------------------------------------------------
    |
    | price_per_night is GST inclusive.
    |
    */

    $basePrice = round(
        $pricePerNight / 1.18,
        2
    );

    $gstAmount = round(
        $pricePerNight - $basePrice,
        2
    );

    /*
    |--------------------------------------------------------------------------
    | Success Response
    |--------------------------------------------------------------------------
    */

    http_response_code(200);

    echo json_encode([
        'status' => true,

        'message' => $affectedRows > 0
            ? 'Room updated successfully.'
            : 'No changes were made to the room.',

        'data' => [

            'room_id' => $roomId,

            'updated_fields' => $fieldsToUpdate,

            'room_type' => [
                'id' => $roomTypeId,
                'name' => $roomType['name']
            ],

            'room_number' => $roomNumber,

            'name' => $name,

            'description' => $description,

            'bed_count' => $bedCount,

            'guest_capacity' => $guestCapacity,

            'pricing' => [

                'price_per_night' => number_format(
                    $pricePerNight,
                    2,
                    '.',
                    ''
                ),

                'gst_rate' => number_format(
                    $gstRate,
                    2,
                    '.',
                    ''
                ),

                'price_includes_gst' => true,

                'base_price' => number_format(
                    $basePrice,
                    2,
                    '.',
                    ''
                ),

                'gst_amount' => number_format(
                    $gstAmount,
                    2,
                    '.',
                    ''
                )
            ],

            'status' => $status,

            'changed' => $affectedRows > 0,

            'updated_by' => [
                'user_id' => (int) $user['sub'],
                'role' => $user['role']
            ]
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
    | Log Error
    |--------------------------------------------------------------------------
    */

    error_log(
        '[ROOM UPDATE] ' .
        $e->getMessage()
    );

    /*
    |--------------------------------------------------------------------------
    | Duplicate Room
    |--------------------------------------------------------------------------
    */

    if (
        $e->getMessage() ===
        'Room number already exists.'
    ) {

        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' => 'Room number already exists.'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | General Error
    |--------------------------------------------------------------------------
    */

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to update room.'
    ]);

    exit;
}