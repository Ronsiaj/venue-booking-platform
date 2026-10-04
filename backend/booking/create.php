<?php

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/middleware.php';


// --------------------------------------------------
// CUSTOMER AUTHENTICATION
// --------------------------------------------------

$user = requireCustomer();

$customerId = (int) $user['sub'];

if ($customerId <= 0) {
    http_response_code(401);

    echo json_encode([
        'status' => false,
        'message' => 'Invalid customer information.'
    ]);

    exit;
}


// --------------------------------------------------
// VERIFY CUSTOMER
// --------------------------------------------------

$stmt = $conn->prepare("
    SELECT
        id,
        name,
        email,
        phone,
        status
    FROM customers
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare customer validation query.'
    ]);

    exit;
}

$stmt->bind_param('i', $customerId);

if (!$stmt->execute()) {
    $stmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to validate customer.'
    ]);

    exit;
}

$result = $stmt->get_result();

$customer = $result->fetch_assoc();

$stmt->close();

if (!$customer) {
    http_response_code(401);

    echo json_encode([
        'status' => false,
        'message' => 'Customer account not found.'
    ]);

    exit;
}

if ($customer['status'] !== 'active') {
    http_response_code(403);

    echo json_encode([
        'status' => false,
        'message' => 'Your customer account is not active.'
    ]);

    exit;
}


// --------------------------------------------------
// READ JSON BODY
// --------------------------------------------------

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


// --------------------------------------------------
// ALLOWED FIELDS
// --------------------------------------------------

$allowedFields = [
    'check_in_date',
    'check_out_date',
    'total_guests',
    'special_request',
    'rooms',
    'food_packages',
    'food_items'
];

$unknownFields = array_diff(
    array_keys($data),
    $allowedFields
);

if (!empty($unknownFields)) {
    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' => 'Unknown field(s) found in request.',
        'unknown_fields' => array_values($unknownFields)
    ]);

    exit;
}


// --------------------------------------------------
// REQUIRED FIELDS
// --------------------------------------------------

$requiredFields = [
    'check_in_date',
    'check_out_date',
    'total_guests',
    'rooms'
];

$missingFields = [];

foreach ($requiredFields as $field) {
    if (!array_key_exists($field, $data)) {
        $missingFields[] = $field;
    }
}

if (!empty($missingFields)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Required field(s) are missing.',
        'missing_fields' => $missingFields
    ]);

    exit;
}


// --------------------------------------------------
// DATE VALIDATION
// --------------------------------------------------

$checkInDate = $data['check_in_date'];
$checkOutDate = $data['check_out_date'];

if (
    !is_string($checkInDate) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkInDate)
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'check_in_date must be in YYYY-MM-DD format.'
    ]);

    exit;
}

if (
    !is_string($checkOutDate) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkOutDate)
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'check_out_date must be in YYYY-MM-DD format.'
    ]);

    exit;
}


// Strict date validation
$checkInObject = DateTime::createFromFormat(
    '!Y-m-d',
    $checkInDate
);

$checkOutObject = DateTime::createFromFormat(
    '!Y-m-d',
    $checkOutDate
);

if (
    !$checkInObject ||
    $checkInObject->format('Y-m-d') !== $checkInDate
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Invalid check_in_date.'
    ]);

    exit;
}

if (
    !$checkOutObject ||
    $checkOutObject->format('Y-m-d') !== $checkOutDate
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Invalid check_out_date.'
    ]);

    exit;
}


// --------------------------------------------------
// CHECK-IN DATE CANNOT BE PAST
// --------------------------------------------------

$today = new DateTime('today');

if ($checkInObject < $today) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'check_in_date cannot be in the past.'
    ]);

    exit;
}


// --------------------------------------------------
// CHECK-OUT MUST BE AFTER CHECK-IN
// --------------------------------------------------

if ($checkOutObject <= $checkInObject) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'check_out_date must be after check_in_date.'
    ]);

    exit;
}


// --------------------------------------------------
// CALCULATE NIGHTS
// --------------------------------------------------

$numberOfNights = (int) $checkInObject->diff(
    $checkOutObject
)->days;

if ($numberOfNights < 1) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Booking must be for at least 1 night.'
    ]);

    exit;
}

$maxNights = 365;

if ($numberOfNights > $maxNights) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Booking cannot exceed 365 nights.'
    ]);

    exit;
}


// --------------------------------------------------
// TOTAL GUESTS VALIDATION
// --------------------------------------------------

$totalGuests = $data['total_guests'];

if (
    !is_int($totalGuests) &&
    !(
        is_string($totalGuests) &&
        preg_match('/^[0-9]+$/', trim($totalGuests))
    )
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'total_guests must be a positive integer.'
    ]);

    exit;
}

$totalGuests = (int) $totalGuests;

if ($totalGuests < 1) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'total_guests must be at least 1.'
    ]);

    exit;
}

if ($totalGuests > 50) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'total_guests cannot exceed 50.'
    ]);

    exit;
}


// --------------------------------------------------
// SPECIAL REQUEST VALIDATION
// --------------------------------------------------

$specialRequest = null;

if (array_key_exists('special_request', $data)) {

    if (
        $data['special_request'] !== null &&
        !is_string($data['special_request'])
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'special_request must be a string or null.'
        ]);

        exit;
    }

    if ($data['special_request'] !== null) {

        $specialRequest = trim($data['special_request']);

        if ($specialRequest === '') {
            $specialRequest = null;
        }

        if (
            $specialRequest !== null &&
            mb_strlen($specialRequest) > 5000
        ) {
            http_response_code(422);

            echo json_encode([
                'status' => false,
                'message' => 'special_request cannot exceed 5000 characters.'
            ]);

            exit;
        }
    }
}


// --------------------------------------------------
// ROOMS VALIDATION
// --------------------------------------------------

$rooms = $data['rooms'];

if (!is_array($rooms)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'rooms must be an array.'
    ]);

    exit;
}

if (empty($rooms)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'At least one room is required.'
    ]);

    exit;
}

if (count($rooms) > 50) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'You cannot book more than 50 rooms in one booking.'
    ]);

    exit;
}


// --------------------------------------------------
// VALIDATE ROOM INPUT
// --------------------------------------------------

$roomIds = [];

foreach ($rooms as $index => $room) {

    if (!is_array($room)) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => "rooms[$index] must be an object."
        ]);

        exit;
    }

    $allowedRoomFields = [
        'room_id'
    ];

    $unknownRoomFields = array_diff(
        array_keys($room),
        $allowedRoomFields
    );

    if (!empty($unknownRoomFields)) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => "Unknown field(s) found in rooms[$index].",
            'unknown_fields' => array_values($unknownRoomFields)
        ]);

        exit;
    }

    if (!array_key_exists('room_id', $room)) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => "rooms[$index].room_id is required."
        ]);

        exit;
    }

    $roomId = $room['room_id'];

    if (
        !is_int($roomId) &&
        !(
            is_string($roomId) &&
            preg_match('/^[0-9]+$/', trim($roomId))
        )
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => "rooms[$index].room_id must be a positive integer."
        ]);

        exit;
    }

    $roomId = (int) $roomId;

    if ($roomId <= 0) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => "rooms[$index].room_id must be greater than 0."
        ]);

        exit;
    }

    if (in_array($roomId, $roomIds, true)) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => "Room ID $roomId is duplicated in the booking."
        ]);

        exit;
    }

    $roomIds[] = $roomId;
}


// --------------------------------------------------
// FOOD PACKAGES VALIDATION
// --------------------------------------------------

$foodPackages = [];

if (array_key_exists('food_packages', $data)) {

    $foodPackages = $data['food_packages'];

    if (!is_array($foodPackages)) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'food_packages must be an array.'
        ]);

        exit;
    }

    if (count($foodPackages) > 100) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Maximum 100 food packages are allowed.'
        ]);

        exit;
    }
}


// --------------------------------------------------
// VALIDATE FOOD PACKAGES
// --------------------------------------------------

$foodPackageIds = [];

foreach ($foodPackages as $index => $foodPackage) {

    if (!is_array($foodPackage)) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => "food_packages[$index] must be an object."
        ]);

        exit;
    }

    $allowedPackageFields = [
        'package_id',
        'quantity',
        'notes'
    ];

    $unknownPackageFields = array_diff(
        array_keys($foodPackage),
        $allowedPackageFields
    );

    if (!empty($unknownPackageFields)) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => "Unknown field(s) found in food_packages[$index].",
            'unknown_fields' => array_values($unknownPackageFields)
        ]);

        exit;
    }

    if (!array_key_exists('package_id', $foodPackage)) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => "food_packages[$index].package_id is required."
        ]);

        exit;
    }

    $packageId = $foodPackage['package_id'];

    if (
        !is_int($packageId) &&
        !(
            is_string($packageId) &&
            preg_match('/^[0-9]+$/', trim($packageId))
        )
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => "food_packages[$index].package_id must be a positive integer."
        ]);

        exit;
    }

    $packageId = (int) $packageId;

    if ($packageId <= 0) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => "food_packages[$index].package_id must be greater than 0."
        ]);

        exit;
    }

    if (in_array($packageId, $foodPackageIds, true)) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => "Food package ID $packageId is duplicated."
        ]);

        exit;
    }

    $foodPackageIds[] = $packageId;


    // Quantity
    $quantity = 1;

    if (array_key_exists('quantity', $foodPackage)) {

        $quantityValue = $foodPackage['quantity'];

        if (
            !is_int($quantityValue) &&
            !(
                is_string($quantityValue) &&
                preg_match('/^[0-9]+$/', trim($quantityValue))
            )
        ) {
            http_response_code(422);

            echo json_encode([
                'status' => false,
                'message' => "food_packages[$index].quantity must be a positive integer."
            ]);

            exit;
        }

        $quantity = (int) $quantityValue;
    }

    if ($quantity < 1 || $quantity > 1000) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => "food_packages[$index].quantity must be between 1 and 1000."
        ]);

        exit;
    }


    // Notes
    $notes = null;

    if (array_key_exists('notes', $foodPackage)) {

        if (
            $foodPackage['notes'] !== null &&
            !is_string($foodPackage['notes'])
        ) {
            http_response_code(422);

            echo json_encode([
                'status' => false,
                'message' => "food_packages[$index].notes must be a string or null."
            ]);

            exit;
        }

        if ($foodPackage['notes'] !== null) {

            $notes = trim($foodPackage['notes']);

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
                    'message' => "food_packages[$index].notes cannot exceed 2000 characters."
                ]);

                exit;
            }
        }
    }

    $foodPackages[$index]['package_id'] = $packageId;
    $foodPackages[$index]['quantity'] = $quantity;
    $foodPackages[$index]['notes'] = $notes;
}


// --------------------------------------------------
// FOOD ITEMS VALIDATION
// --------------------------------------------------

$foodItems = [];

if (array_key_exists('food_items', $data)) {

    $foodItems = $data['food_items'];

    if (!is_array($foodItems)) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'food_items must be an array.'
        ]);

        exit;
    }

    if (count($foodItems) > 100) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Maximum 100 food items are allowed.'
        ]);

        exit;
    }
}


// --------------------------------------------------
// VALIDATE FOOD ITEMS
// --------------------------------------------------

$foodItemIds = [];

foreach ($foodItems as $index => $foodItem) {

    if (!is_array($foodItem)) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => "food_items[$index] must be an object."
        ]);

        exit;
    }

    $allowedFoodFields = [
        'food_item_id',
        'quantity',
        'notes'
    ];

    $unknownFoodFields = array_diff(
        array_keys($foodItem),
        $allowedFoodFields
    );

    if (!empty($unknownFoodFields)) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => "Unknown field(s) found in food_items[$index].",
            'unknown_fields' => array_values($unknownFoodFields)
        ]);

        exit;
    }

    if (!array_key_exists('food_item_id', $foodItem)) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => "food_items[$index].food_item_id is required."
        ]);

        exit;
    }

    $foodItemId = $foodItem['food_item_id'];

    if (
        !is_int($foodItemId) &&
        !(
            is_string($foodItemId) &&
            preg_match('/^[0-9]+$/', trim($foodItemId))
        )
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => "food_items[$index].food_item_id must be a positive integer."
        ]);

        exit;
    }

    $foodItemId = (int) $foodItemId;

    if ($foodItemId <= 0) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => "food_items[$index].food_item_id must be greater than 0."
        ]);

        exit;
    }

    if (in_array($foodItemId, $foodItemIds, true)) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => "Food item ID $foodItemId is duplicated."
        ]);

        exit;
    }

    $foodItemIds[] = $foodItemId;


    // Quantity
    $quantity = 1;

    if (array_key_exists('quantity', $foodItem)) {

        $quantityValue = $foodItem['quantity'];

        if (
            !is_int($quantityValue) &&
            !(
                is_string($quantityValue) &&
                preg_match('/^[0-9]+$/', trim($quantityValue))
            )
        ) {
            http_response_code(422);

            echo json_encode([
                'status' => false,
                'message' => "food_items[$index].quantity must be a positive integer."
            ]);

            exit;
        }

        $quantity = (int) $quantityValue;
    }

    if ($quantity < 1 || $quantity > 1000) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => "food_items[$index].quantity must be between 1 and 1000."
        ]);

        exit;
    }


    // Notes
    $notes = null;

    if (array_key_exists('notes', $foodItem)) {

        if (
            $foodItem['notes'] !== null &&
            !is_string($foodItem['notes'])
        ) {
            http_response_code(422);

            echo json_encode([
                'status' => false,
                'message' => "food_items[$index].notes must be a string or null."
            ]);

            exit;
        }

        if ($foodItem['notes'] !== null) {

            $notes = trim($foodItem['notes']);

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
                    'message' => "food_items[$index].notes cannot exceed 2000 characters."
                ]);

                exit;
            }
        }
    }

    $foodItems[$index]['food_item_id'] = $foodItemId;
    $foodItems[$index]['quantity'] = $quantity;
    $foodItems[$index]['notes'] = $notes;
}


// --------------------------------------------------
// TOTAL ROOM CAPACITY CHECK
// --------------------------------------------------
//
// Each selected room capacity is checked individually below.
// The total selected room capacity must be >= total_guests.
//

// --------------------------------------------------
// START TRANSACTION
// --------------------------------------------------

$conn->begin_transaction();

try {

    // --------------------------------------------------
    // LOCK AND FETCH ROOMS
    // --------------------------------------------------

    sort($roomIds, SORT_NUMERIC);

    $lockedRooms = [];

    foreach ($roomIds as $roomId) {

        /*
         * FOR UPDATE locks the room row during this transaction.
         * This is important to reduce double-booking race conditions.
         */

        $stmt = $conn->prepare("
            SELECT
                r.id,
                r.room_type_id,
                r.room_number,
                r.name,
                r.guest_capacity,
                r.price_per_night,
                r.gst_rate,
                r.price_includes_gst,
                r.status,

                rt.name AS room_type_name,
                rt.status AS room_type_status

            FROM rooms r

            INNER JOIN room_types rt
                ON rt.id = r.room_type_id

            WHERE r.id = ?

            LIMIT 1

            FOR UPDATE
        ");

        if (!$stmt) {
            throw new Exception(
                'Failed to prepare room validation query.'
            );
        }

        $stmt->bind_param('i', $roomId);

        if (!$stmt->execute()) {
            $stmt->close();

            throw new RuntimeException(
                'Failed to validate selected room.'
            );
        }

        $result = $stmt->get_result();

        $room = $result->fetch_assoc();

        $stmt->close();

        if (!$room) {
            throw new RuntimeException(
                "Room ID $roomId was not found."
            );
        }


        // --------------------------------------------------
        // ROOM STATUS
        // --------------------------------------------------

        if ($room['status'] !== 'available') {
            throw new RuntimeException(
                "Room {$room['room_number']} is not available."
            );
        }


        // --------------------------------------------------
        // ROOM TYPE STATUS
        // --------------------------------------------------

        if ($room['room_type_status'] !== 'active') {
            throw new RuntimeException(
                "Room type for room {$room['room_number']} is inactive."
            );
        }


        // --------------------------------------------------
        // PRICE VALIDATION
        // --------------------------------------------------

        $roomPrice = (float) $room['price_per_night'];

        if ($roomPrice <= 0) {
            throw new RuntimeException(
                "Room {$room['room_number']} does not have a valid price."
            );
        }


        // --------------------------------------------------
        // GST VALIDATION
        // --------------------------------------------------

        $gstRate = (float) $room['gst_rate'];

        if ($gstRate < 0 || $gstRate > 100) {
            throw new RuntimeException(
                "Invalid GST rate configured for room {$room['room_number']}."
            );
        }


        if ((int) $room['price_includes_gst'] !== 1) {
            throw new RuntimeException(
                "Room {$room['room_number']} is not configured with GST-inclusive pricing."
            );
        }


        $lockedRooms[] = $room;
    }


    // --------------------------------------------------
    // TOTAL ROOM CAPACITY
    // --------------------------------------------------

    $totalRoomCapacity = 0;

    foreach ($lockedRooms as $room) {

        $totalRoomCapacity += (int) $room['guest_capacity'];
    }

    if ($totalRoomCapacity < $totalGuests) {
        throw new RuntimeException(
            "Selected rooms can accommodate only {$totalRoomCapacity} guests. You requested {$totalGuests} guests."
        );
    }


    // --------------------------------------------------
    // AVAILABILITY / OVERLAP CHECK
    // --------------------------------------------------

    foreach ($lockedRooms as $room) {

        $roomId = (int) $room['id'];

        $stmt = $conn->prepare("
            SELECT
                br.id,
                rb.booking_reference,
                rb.check_in_date,
                rb.check_out_date,
                rb.booking_status

            FROM booking_rooms br

            INNER JOIN room_bookings rb
                ON rb.id = br.booking_id

            WHERE br.room_id = ?

              AND rb.booking_status NOT IN (
                  'cancelled',
                  'no_show'
              )

              AND rb.check_in_date < ?

              AND rb.check_out_date > ?

            LIMIT 1
        ");

        if (!$stmt) {
            throw new Exception(
                'Failed to prepare room availability query.'
            );
        }

        $stmt->bind_param(
            'iss',
            $roomId,
            $checkOutDate,
            $checkInDate
        );

        if (!$stmt->execute()) {
            $stmt->close();

            throw new RuntimeException(
                'Failed to check room availability.'
            );
        }

        $result = $stmt->get_result();

        $existingBooking = $result->fetch_assoc();

        $stmt->close();

        if ($existingBooking) {

            throw new RuntimeException(
                "Room {$room['room_number']} is already booked for the selected dates."
            );
        }
    }


    // --------------------------------------------------
    // VALIDATE FOOD PACKAGES
    // --------------------------------------------------

    foreach ($foodPackages as $index => $foodPackage) {

        $packageId = (int) $foodPackage['package_id'];

        $stmt = $conn->prepare("
            SELECT
                id,
                name,
                description,
                meal_type,
                status
            FROM room_food_packages
            WHERE id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            throw new Exception(
                'Failed to prepare food package validation query.'
            );
        }

        $stmt->bind_param('i', $packageId);

        if (!$stmt->execute()) {
            $stmt->close();

            throw new RuntimeException(
                'Failed to validate food package.'
            );
        }

        $result = $stmt->get_result();

        $package = $result->fetch_assoc();

        $stmt->close();

        if (!$package) {
            throw new RuntimeException(
                "Food package ID $packageId was not found."
            );
        }

        if ($package['status'] !== 'active') {
            throw new RuntimeException(
                "Food package '{$package['name']}' is inactive."
            );
        }
    }


    // --------------------------------------------------
    // VALIDATE FOOD ITEMS
    // --------------------------------------------------

    foreach ($foodItems as $index => $foodItem) {

        $foodItemId = (int) $foodItem['food_item_id'];

        $stmt = $conn->prepare("
            SELECT
                id,
                name,
                status
            FROM food_items
            WHERE id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            throw new Exception(
                'Failed to prepare food item validation query.'
            );
        }

        $stmt->bind_param('i', $foodItemId);

        if (!$stmt->execute()) {
            $stmt->close();

            throw new RuntimeException(
                'Failed to validate food item.'
            );
        }

        $result = $stmt->get_result();

        $foodItemRecord = $result->fetch_assoc();

        $stmt->close();

        if (!$foodItemRecord) {
            throw new RuntimeException(
                "Food item ID $foodItemId was not found."
            );
        }

        if ($foodItemRecord['status'] !== 'active') {
            throw new RuntimeException(
                "Food item '{$foodItemRecord['name']}' is inactive."
            );
        }
    }


    // --------------------------------------------------
    // GENERATE UNIQUE BOOKING REFERENCE
    // --------------------------------------------------

    $bookingReference = null;

    for ($attempt = 1; $attempt <= 10; $attempt++) {

        $bookingReference =
            'SMB-' .
            date('Ymd') .
            '-' .
            strtoupper(
                substr(
                    bin2hex(random_bytes(6)),
                    0,
                    8
                )
            );

        $stmt = $conn->prepare("
            SELECT id
            FROM room_bookings
            WHERE booking_reference = ?
            LIMIT 1
        ");

        if (!$stmt) {
            throw new Exception(
                'Failed to prepare booking reference validation query.'
            );
        }

        $stmt->bind_param(
            's',
            $bookingReference
        );

        if (!$stmt->execute()) {
            $stmt->close();

            throw new RuntimeException(
                'Failed to validate booking reference.'
            );
        }

        $result = $stmt->get_result();

        $existingReference = $result->fetch_assoc();

        $stmt->close();

        if (!$existingReference) {
            break;
        }

        $bookingReference = null;
    }

    if ($bookingReference === null) {
        throw new RuntimeException(
            'Unable to generate a unique booking reference.'
        );
    }


    // --------------------------------------------------
    // CALCULATE ROOM TOTALS
    // --------------------------------------------------

    $grossRoomTotal = 0.00;
    $roomBookingRows = [];

    foreach ($lockedRooms as $room) {

        $roomPricePerNight =
            round(
                (float) $room['price_per_night'],
                2
            );

        $roomTotal =
            round(
                $roomPricePerNight * $numberOfNights,
                2
            );

        $grossRoomTotal += $roomTotal;

        $roomBookingRows[] = [
            'room_id' => (int) $room['id'],
            'room_number' => $room['room_number'],
            'room_name' => $room['name'],
            'room_price_per_night' => $roomPricePerNight,
            'number_of_nights' => $numberOfNights,
            'room_total' => $roomTotal
        ];
    }

    $grossRoomTotal = round(
        $grossRoomTotal,
        2
    );


    // --------------------------------------------------
    // GST-INCLUSIVE CALCULATION
    // --------------------------------------------------

    /*
     * Room tariff already includes 18% GST.
     *
     * Base amount = Gross / 1.18
     * GST amount  = Gross - Base
     * Total       = Gross
     */

    $bookingGstRate = 18.00;

    $roomSubtotal = round(
        $grossRoomTotal /
        (1 + ($bookingGstRate / 100)),
        2
    );

    $gstAmount = round(
        $grossRoomTotal - $roomSubtotal,
        2
    );

    $totalAmount = round(
        $grossRoomTotal,
        2
    );


    // --------------------------------------------------
    // AMOUNT VALIDATION
    // --------------------------------------------------

    if ($totalAmount <= 0) {
        throw new RuntimeException(
            'Invalid booking amount.'
        );
    }

    if ($totalAmount > 100000000) {
        throw new RuntimeException(
            'Booking amount exceeds the maximum allowed amount.'
        );
    }


    // --------------------------------------------------
    // INSERT ROOM BOOKING
    // --------------------------------------------------

    $paymentStatus = 'pending';
    $bookingStatus = 'pending';

    $stmt = $conn->prepare("
        INSERT INTO room_bookings
        (
            booking_reference,
            customer_id,
            check_in_date,
            check_out_date,
            total_guests,
            special_request,
            room_subtotal,
            gst_rate,
            gst_amount,
            total_amount,
            payment_status,
            booking_status
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
            ?,
            ?,
            ?
        )
    ");

    if (!$stmt) {
        throw new Exception(
            'Failed to prepare booking insert query.'
        );
    }

    $stmt->bind_param(
        'sissisddddss',
        $bookingReference,
        $customerId,
        $checkInDate,
        $checkOutDate,
        $totalGuests,
        $specialRequest,
        $roomSubtotal,
        $bookingGstRate,
        $gstAmount,
        $totalAmount,
        $paymentStatus,
        $bookingStatus
    );

    if (!$stmt->execute()) {

        $errorCode = $stmt->errno;

        $stmt->close();

        if ($errorCode === 1062) {
            throw new RuntimeException(
                'Booking reference already exists. Please try again.'
            );
        }

        throw new RuntimeException(
            'Failed to create room booking.'
        );
    }

    $bookingId = (int) $stmt->insert_id;

    $stmt->close();


    if ($bookingId <= 0) {
        throw new RuntimeException(
            'Failed to generate booking ID.'
        );
    }


    // --------------------------------------------------
    // INSERT BOOKING ROOMS
    // --------------------------------------------------

    foreach ($roomBookingRows as $roomRow) {

        $stmt = $conn->prepare("
            INSERT INTO booking_rooms
            (
                booking_id,
                room_id,
                room_price_per_night,
                number_of_nights,
                room_total
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ");

        if (!$stmt) {
            throw new Exception(
                'Failed to prepare booking room insert query.'
            );
        }

        $stmt->bind_param(
            'iidid',
            $bookingId,
            $roomRow['room_id'],
            $roomRow['room_price_per_night'],
            $roomRow['number_of_nights'],
            $roomRow['room_total']
        );

        if (!$stmt->execute()) {

            $errorCode = $stmt->errno;

            $stmt->close();

            if ($errorCode === 1452) {
                throw new RuntimeException(
                    'Invalid room reference.'
                );
            }

            throw new RuntimeException(
                'Failed to add room to booking.'
            );
        }

        $stmt->close();
    }


    // --------------------------------------------------
    // INSERT FOOD PACKAGES
    // --------------------------------------------------

    foreach ($foodPackages as $foodPackage) {

        $packageId = (int) $foodPackage['package_id'];
        $quantity = (int) $foodPackage['quantity'];
        $notes = $foodPackage['notes'];

        $stmt = $conn->prepare("
            INSERT INTO booking_food_packages
            (
                booking_id,
                package_id,
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
        ");

        if (!$stmt) {
            throw new Exception(
                'Failed to prepare booking food package insert query.'
            );
        }

        $stmt->bind_param(
            'iiis',
            $bookingId,
            $packageId,
            $quantity,
            $notes
        );

        if (!$stmt->execute()) {

            $errorCode = $stmt->errno;

            $stmt->close();

            if ($errorCode === 1452) {
                throw new RuntimeException(
                    'Invalid food package reference.'
                );
            }

            throw new RuntimeException(
                'Failed to add food package to booking.'
            );
        }

        $stmt->close();
    }


    // --------------------------------------------------
    // INSERT CUSTOM FOOD ITEMS
    // --------------------------------------------------

    foreach ($foodItems as $foodItem) {

        $foodItemId = (int) $foodItem['food_item_id'];
        $quantity = (int) $foodItem['quantity'];
        $notes = $foodItem['notes'];

        $stmt = $conn->prepare("
            INSERT INTO booking_food_items
            (
                booking_id,
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
        ");

        if (!$stmt) {
            throw new Exception(
                'Failed to prepare booking food item insert query.'
            );
        }

        $stmt->bind_param(
            'iiis',
            $bookingId,
            $foodItemId,
            $quantity,
            $notes
        );

        if (!$stmt->execute()) {

            $errorCode = $stmt->errno;

            $stmt->close();

            if ($errorCode === 1452) {
                throw new RuntimeException(
                    'Invalid food item reference.'
                );
            }

            throw new RuntimeException(
                'Failed to add custom food item to booking.'
            );
        }

        $stmt->close();
    }


    // --------------------------------------------------
    // BOOKING ACTIVITY LOG
    // --------------------------------------------------

    $activityAction = 'booking_created';

    $activityDescription =
        'Room booking created by customer.';

    $stmt = $conn->prepare("
        INSERT INTO booking_activity_logs
        (
            booking_id,
            action,
            description,
            performed_by_admin_id
        )
        VALUES
        (
            ?,
            ?,
            ?,
            NULL
        )
    ");

    if (!$stmt) {
        throw new Exception(
            'Failed to prepare booking activity log query.'
        );
    }

    $stmt->bind_param(
        'iss',
        $bookingId,
        $activityAction,
        $activityDescription
    );

    if (!$stmt->execute()) {
        $stmt->close();

        throw new RuntimeException(
            'Failed to create booking activity log.'
        );
    }

    $stmt->close();


    // --------------------------------------------------
    // COMMIT DATABASE TRANSACTION
    // --------------------------------------------------

    $conn->commit();


} catch (RuntimeException $e) {

    $conn->rollback();

    http_response_code(409);

    echo json_encode([
        'status' => false,
        'message' => $e->getMessage()
    ]);

    exit;

} catch (Exception $e) {

    $conn->rollback();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to create room booking.'
    ]);

    exit;
}


// ==================================================
// RAZORPAY ORDER CREATION
// ==================================================
//
// Database transaction is already committed.
// Razorpay API is external, so it is not executed
// inside the MySQL transaction.
//

$razorpayKeyId = $_ENV['RAZORPAY_KEY_ID'] ?? '';
$razorpayKeySecret = $_ENV['RAZORPAY_KEY_SECRET'] ?? '';
$razorpayCurrency = $_ENV['RAZORPAY_CURRENCY'] ?? 'INR';


// --------------------------------------------------
// RAZORPAY CONFIGURATION CHECK
// --------------------------------------------------

if (
    $razorpayKeyId === '' ||
    $razorpayKeySecret === ''
) {

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Booking created, but Razorpay configuration is missing.',
        'data' => [
            'booking_id' => $bookingId,
            'booking_reference' => $bookingReference,
            'payment_status' => 'pending'
        ]
    ]);

    exit;
}


// --------------------------------------------------
// RAZORPAY AMOUNT
// --------------------------------------------------

$razorpayAmount = (int) round(
    $totalAmount * 100
);

if ($razorpayAmount <= 0) {

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Invalid Razorpay payment amount.',
        'data' => [
            'booking_id' => $bookingId,
            'booking_reference' => $bookingReference
        ]
    ]);

    exit;
}


// --------------------------------------------------
// RAZORPAY ORDER PAYLOAD
// --------------------------------------------------

$razorpayPayload = [
    'amount' => $razorpayAmount,
    'currency' => $razorpayCurrency,
    'receipt' => $bookingReference,
    'notes' => [
        'booking_id' => (string) $bookingId,
        'booking_reference' => $bookingReference,
        'customer_id' => (string) $customerId
    ]
];


// --------------------------------------------------
// RAZORPAY API CALL
// --------------------------------------------------

$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => 'https://api.razorpay.com/v1/orders',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($razorpayPayload),
    CURLOPT_USERPWD => $razorpayKeyId . ':' . $razorpayKeySecret,
    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json'
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 10
]);

$razorpayResponse = curl_exec($ch);

$curlError = curl_error($ch);

$httpCode = (int) curl_getinfo(
    $ch,
    CURLINFO_HTTP_CODE
);

curl_close($ch);


// --------------------------------------------------
// RAZORPAY CURL ERROR
// --------------------------------------------------

if ($razorpayResponse === false) {

    // Mark payment as failed
    $failedStatus = 'failed';

    $stmt = $conn->prepare("
        INSERT INTO payments
        (
            booking_id,
            payment_gateway,
            amount,
            currency,
            payment_status,
            gateway_response
        )
        VALUES
        (
            ?,
            'razorpay',
            ?,
            ?,
            ?,
            ?
        )
    ");

    if ($stmt) {

        $gatewayResponse = json_encode([
            'error' => $curlError
        ]);

        $stmt->bind_param(
            'idsss',
            $bookingId,
            $totalAmount,
            $razorpayCurrency,
            $failedStatus,
            $gatewayResponse
        );

        $stmt->execute();

        $stmt->close();
    }


    http_response_code(502);

    echo json_encode([
        'status' => false,
        'message' => 'Booking was created, but Razorpay order creation failed.',
        'data' => [
            'booking_id' => $bookingId,
            'booking_reference' => $bookingReference,
            'payment_status' => 'failed',
            'payment_error' => $curlError
        ]
    ]);

    exit;
}


// --------------------------------------------------
// PARSE RAZORPAY RESPONSE
// --------------------------------------------------

$razorpayData = json_decode(
    $razorpayResponse,
    true
);

if (
    $httpCode < 200 ||
    $httpCode >= 300 ||
    !is_array($razorpayData) ||
    empty($razorpayData['id'])
) {

    $failedStatus = 'failed';

    $stmt = $conn->prepare("
        INSERT INTO payments
        (
            booking_id,
            payment_gateway,
            amount,
            currency,
            payment_status,
            gateway_response
        )
        VALUES
        (
            ?,
            'razorpay',
            ?,
            ?,
            ?,
            ?
        )
    ");

    if ($stmt) {

        $gatewayResponse = $razorpayResponse;

        $stmt->bind_param(
            'idsss',
            $bookingId,
            $totalAmount,
            $razorpayCurrency,
            $failedStatus,
            $gatewayResponse
        );

        $stmt->execute();

        $stmt->close();
    }


    http_response_code(502);

    echo json_encode([
        'status' => false,
        'message' => 'Booking was created, but Razorpay order creation failed.',
        'data' => [
            'booking_id' => $bookingId,
            'booking_reference' => $bookingReference,
            'payment_status' => 'failed'
        ]
    ]);

    exit;
}


// --------------------------------------------------
// RAZORPAY ORDER DETAILS
// --------------------------------------------------

$razorpayOrderId = $razorpayData['id'];


// --------------------------------------------------
// INSERT PAYMENT RECORD
// --------------------------------------------------

$paymentStatus = 'created';

$stmt = $conn->prepare("
    INSERT INTO payments
    (
        booking_id,
        payment_gateway,
        razorpay_order_id,
        amount,
        currency,
        payment_status,
        gateway_response
    )
    VALUES
    (
        ?,
        'razorpay',
        ?,
        ?,
        ?,
        ?,
        ?
    )
");

if (!$stmt) {

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Booking created, but payment record could not be prepared.',
        'data' => [
            'booking_id' => $bookingId,
            'booking_reference' => $bookingReference,
            'razorpay_order_id' => $razorpayOrderId
        ]
    ]);

    exit;
}

$gatewayResponse = $razorpayResponse;

$stmt->bind_param(
    'isdsss',
    $bookingId,
    $razorpayOrderId,
    $totalAmount,
    $razorpayCurrency,
    $paymentStatus,
    $gatewayResponse
);

if (!$stmt->execute()) {

    $stmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Booking created, but payment record could not be saved.',
        'data' => [
            'booking_id' => $bookingId,
            'booking_reference' => $bookingReference,
            'razorpay_order_id' => $razorpayOrderId
        ]
    ]);

    exit;
}

$paymentId = (int) $stmt->insert_id;

$stmt->close();


// --------------------------------------------------
// SUCCESS RESPONSE
// --------------------------------------------------

http_response_code(201);

echo json_encode([
    'status' => true,
    'message' => 'Room booking created successfully.',

    'data' => [
        'booking' => [
            'id' => $bookingId,
            'booking_reference' => $bookingReference,

            'customer' => [
                'id' => (int) $customer['id'],
                'name' => $customer['name'],
                'email' => $customer['email'],
                'phone' => $customer['phone']
            ],

            'check_in_date' => $checkInDate,
            'check_out_date' => $checkOutDate,
            'number_of_nights' => $numberOfNights,
            'total_guests' => $totalGuests,

            'special_request' => $specialRequest,

            'rooms' => $roomBookingRows,

            'food_packages' => $foodPackages,

            'food_items' => $foodItems,

            'pricing' => [
                'room_subtotal_excluding_gst' =>
                    number_format(
                        $roomSubtotal,
                        2,
                        '.',
                        ''
                    ),

                'gst_rate' =>
                    number_format(
                        $bookingGstRate,
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
                    ),

                'total_amount' =>
                    number_format(
                        $totalAmount,
                        2,
                        '.',
                        ''
                    ),

                'currency' => $razorpayCurrency,

                'price_includes_gst' => true
            ],

            'booking_status' => 'pending',

            'payment' => [
                'id' => $paymentId,
                'gateway' => 'razorpay',
                'razorpay_order_id' => $razorpayOrderId,
                'amount' => number_format(
                    $totalAmount,
                    2,
                    '.',
                    ''
                ),
                'amount_in_paise' => $razorpayAmount,
                'currency' => $razorpayCurrency,
                'payment_status' => 'created'
            ]
        ]
    ]
]);

exit;