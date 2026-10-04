<?php

// ============================================================
// CORS
// ============================================================

header('Content-Type: application/json; charset=UTF-8');

header(
    'Access-Control-Allow-Origin: *'
);

header(
    'Access-Control-Allow-Methods: POST, PUT, OPTIONS'
);

header(
    'Access-Control-Allow-Headers: Content-Type, Authorization'
);


// ============================================================
// OPTIONS
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {

    http_response_code(200);
    exit;
}


// ============================================================
// METHOD VALIDATION
// ============================================================

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' &&
    $_SERVER['REQUEST_METHOD'] !== 'PUT'
) {

    http_response_code(405);

    echo json_encode([
        'status' => false,
        'message' => 'Only POST or PUT method is allowed.'
    ]);

    exit;
}


// ============================================================
// CONFIG
// ============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/middleware.php';


// ============================================================
// ADMIN / STAFF AUTHENTICATION
// ============================================================

$user = requireAdminOrStaff();


// ============================================================
// USER VALIDATION
// ============================================================

if (
    !isset($user['sub']) ||
    !is_numeric($user['sub']) ||
    (int) $user['sub'] <= 0
) {

    http_response_code(401);

    echo json_encode([
        'status' => false,
        'message' => 'Invalid user information.'
    ]);

    exit;
}


$userId = (int) $user['sub'];
$userRole = $user['role'];


// ============================================================
// ROLE VALIDATION
// ============================================================

if (
    !in_array(
        $userRole,
        [
            'admin',
            'staff'
        ],
        true
    )
) {

    http_response_code(403);

    echo json_encode([
        'status' => false,
        'message' => 'Only admin or staff can check out a booking.'
    ]);

    exit;
}


// ============================================================
// READ JSON BODY
// ============================================================

$rawInput = file_get_contents(
    'php://input'
);

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


// ============================================================
// JSON DECODE
// ============================================================

$input = json_decode(
    $rawInput,
    true
);


if (
    json_last_error() !== JSON_ERROR_NONE ||
    !is_array($input)
) {

    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' => 'Request body must be a valid JSON object.'
    ]);

    exit;
}


// ============================================================
// ALLOWED FIELDS
// ============================================================

$allowedFields = [
    'booking_id'
];


$unknownFields = array_diff(
    array_keys($input),
    $allowedFields
);


if (!empty($unknownFields)) {

    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' => 'Unknown field(s) found in request.',
        'unknown_fields' => array_values(
            $unknownFields
        )
    ]);

    exit;
}


// ============================================================
// BOOKING ID REQUIRED
// ============================================================

if (!array_key_exists(
    'booking_id',
    $input
)) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'booking_id is required.'
    ]);

    exit;
}


// ============================================================
// BOOKING ID VALIDATION
// ============================================================

$bookingIdValue = $input['booking_id'];


if (
    !is_int($bookingIdValue) &&
    !is_string($bookingIdValue)
) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'booking_id must be a positive integer.'
    ]);

    exit;
}


$bookingIdString = (string) $bookingIdValue;


if (
    !preg_match(
        '/^[0-9]+$/',
        $bookingIdString
    )
) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'booking_id must be a positive integer.'
    ]);

    exit;
}


$bookingId = (int) $bookingIdString;


if ($bookingId <= 0) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'booking_id must be greater than 0.'
    ]);

    exit;
}


// ============================================================
// START TRANSACTION
// ============================================================

$conn->begin_transaction();


try {

    // ========================================================
    // FETCH BOOKING WITH ROW LOCK
    // ========================================================

    $bookingQuery = $conn->prepare("
        SELECT
            rb.id,
            rb.booking_reference,
            rb.customer_id,
            rb.check_in_date,
            rb.check_out_date,
            rb.total_guests,
            rb.special_request,
            rb.room_subtotal,
            rb.gst_rate,
            rb.gst_amount,
            rb.total_amount,
            rb.payment_status,
            rb.booking_status,
            rb.cancellation_reason,
            rb.created_at,
            rb.updated_at,

            c.name AS customer_name,
            c.email AS customer_email,
            c.phone AS customer_phone

        FROM room_bookings rb

        INNER JOIN customers c
            ON c.id = rb.customer_id

        WHERE rb.id = ?

        LIMIT 1

        FOR UPDATE
    ");


    if (!$bookingQuery) {

        throw new Exception(
            'Failed to prepare booking query.'
        );
    }


    $bookingQuery->bind_param(
        'i',
        $bookingId
    );


    if (!$bookingQuery->execute()) {

        $bookingQuery->close();

        throw new Exception(
            'Failed to fetch booking.'
        );
    }


    $bookingResult =
        $bookingQuery->get_result();


    $booking =
        $bookingResult->fetch_assoc();


    $bookingQuery->close();


    // ========================================================
    // BOOKING NOT FOUND
    // ========================================================

    if (!$booking) {

        $conn->rollback();

        http_response_code(404);

        echo json_encode([
            'status' => false,
            'message' => 'Booking not found.'
        ]);

        exit;
    }


    // ========================================================
    // CURRENT STATUS
    // ========================================================

    $currentStatus =
        $booking['booking_status'];


    // ========================================================
    // ALREADY CHECKED OUT
    // ========================================================

    if (
        $currentStatus === 'checked_out'
    ) {

        $conn->rollback();

        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' => 'Booking is already checked out.',
            'data' => [
                'booking_id' =>
                    (int) $booking['id'],

                'booking_reference' =>
                    $booking['booking_reference'],

                'booking_status' =>
                    $currentStatus
            ]
        ]);

        exit;
    }


    // ========================================================
    // CHECKED-IN VALIDATION
    // ========================================================

    if (
        $currentStatus !== 'checked_in'
    ) {

        $conn->rollback();

        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' => 'Only a checked-in booking can be checked out.',
            'current_status' =>
                $currentStatus
        ]);

        exit;
    }


    // ========================================================
    // DATE VALIDATION
    // ========================================================

    $checkInDate =
        $booking['check_in_date'];

    $checkOutDate =
        $booking['check_out_date'];


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

        $conn->rollback();

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Invalid booking check-in date.'
        ]);

        exit;
    }


    if (
        !$checkOutObject ||
        $checkOutObject->format('Y-m-d') !== $checkOutDate
    ) {

        $conn->rollback();

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Invalid booking check-out date.'
        ]);

        exit;
    }


    // ========================================================
    // DATE RANGE VALIDATION
    // ========================================================

    if (
        $checkOutObject <= $checkInObject
    ) {

        $conn->rollback();

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Invalid booking date range.'
        ]);

        exit;
    }


    // ========================================================
    // CURRENT DATE
    // ========================================================

    $timezone = new DateTimeZone(
        'Asia/Kolkata'
    );


    $today = new DateTime(
        'today',
        $timezone
    );


    // ========================================================
    // FUTURE CHECKOUT VALIDATION
    // ========================================================
    //
    // Normal checkout should happen on or after the booked
    // checkout date.
    //
    // Early checkout is not automatically blocked because
    // early checkout can be a valid hotel operation.
    //
    // Therefore this API allows checkout before the booked
    // checkout date, but only after the booking is checked in.
    // ========================================================


    // ========================================================
    // FETCH ASSIGNED ROOMS
    // ========================================================

    $roomQuery = $conn->prepare("
        SELECT
            br.id,
            br.booking_id,
            br.room_id,
            br.room_price_per_night,
            br.number_of_nights,
            br.room_total,

            r.room_number,
            r.name AS room_name,
            r.status AS room_status,

            rt.id AS room_type_id,
            rt.name AS room_type_name,
            rt.status AS room_type_status

        FROM booking_rooms br

        INNER JOIN rooms r
            ON r.id = br.room_id

        INNER JOIN room_types rt
            ON rt.id = r.room_type_id

        WHERE br.booking_id = ?

        ORDER BY
            br.id ASC

        FOR UPDATE
    ");


    if (!$roomQuery) {

        throw new Exception(
            'Failed to prepare booking room query.'
        );
    }


    $roomQuery->bind_param(
        'i',
        $bookingId
    );


    if (!$roomQuery->execute()) {

        $roomQuery->close();

        throw new Exception(
            'Failed to fetch assigned rooms.'
        );
    }


    $roomResult =
        $roomQuery->get_result();


    $assignedRooms = [];


    while (
        $room = $roomResult->fetch_assoc()
    ) {

        $assignedRooms[] = $room;
    }


    $roomQuery->close();


    // ========================================================
    // ROOM ASSIGNMENT VALIDATION
    // ========================================================

    if (empty($assignedRooms)) {

        $conn->rollback();

        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' => 'No room is assigned to this booking.'
        ]);

        exit;
    }


    // ========================================================
    // VALIDATE ASSIGNED ROOMS
    // ========================================================

    foreach (
        $assignedRooms as $room
    ) {

        if (
            (int) $room['room_id'] <= 0
        ) {

            throw new Exception(
                'Invalid room assignment found.'
            );
        }


        if (
            $room['room_status'] === 'inactive'
        ) {

            $conn->rollback();

            http_response_code(409);

            echo json_encode([
                'status' => false,
                'message' => 'One or more assigned rooms are inactive.',
                'data' => [
                    'room_id' =>
                        (int) $room['room_id'],

                    'room_number' =>
                        $room['room_number'],

                    'room_status' =>
                        $room['room_status']
                ]
            ]);

            exit;
        }


        if (
            $room['room_type_status'] !== 'active'
        ) {

            $conn->rollback();

            http_response_code(409);

            echo json_encode([
                'status' => false,
                'message' => 'One or more assigned room types are inactive.',
                'data' => [
                    'room_id' =>
                        (int) $room['room_id'],

                    'room_number' =>
                        $room['room_number'],

                    'room_type_id' =>
                        (int) $room['room_type_id'],

                    'room_type_status' =>
                        $room['room_type_status']
                ]
            ]);

            exit;
        }
    }


    // ========================================================
    // PAYMENT STATUS
    // ========================================================
    //
    // We do NOT block checkout based on payment status.
    //
    // If payment is pending/partial, frontend/admin can see
    // the outstanding status and handle payment separately.
    //
    // ========================================================

    $paymentStatus =
        $booking['payment_status'];


    $paymentCompleted =
        ($paymentStatus === 'paid');


    $paymentOutstanding =
        in_array(
            $paymentStatus,
            [
                'pending',
                'partial'
            ],
            true
        );


    // ========================================================
    // UPDATE BOOKING STATUS
    // ========================================================

    $updateQuery = $conn->prepare("
        UPDATE room_bookings

        SET
            booking_status = 'checked_out',
            updated_at = CURRENT_TIMESTAMP

        WHERE id = ?

          AND booking_status = 'checked_in'
    ");


    if (!$updateQuery) {

        throw new Exception(
            'Failed to prepare checkout update.'
        );
    }


    $updateQuery->bind_param(
        'i',
        $bookingId
    );


    if (!$updateQuery->execute()) {

        $updateQuery->close();

        throw new Exception(
            'Failed to update booking status.'
        );
    }


    $affectedRows =
        $updateQuery->affected_rows;


    $updateQuery->close();


    // ========================================================
    // RACE CONDITION VALIDATION
    // ========================================================

    if ($affectedRows !== 1) {

        throw new Exception(
            'Booking status changed before checkout could be completed.'
        );
    }


    // ========================================================
    // ACTIVITY LOG
    // ========================================================

    $activityQuery = $conn->prepare("
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
            'booking_checked_out',
            ?,
            ?
        )
    ");


    if (!$activityQuery) {

        throw new Exception(
            'Failed to prepare activity log.'
        );
    }


    $description =
        "Booking checked out by {$userRole}.";


    $activityQuery->bind_param(
        'isi',
        $bookingId,
        $description,
        $userId
    );


    if (!$activityQuery->execute()) {

        $activityQuery->close();

        throw new Exception(
            'Failed to create checkout activity log.'
        );
    }


    $activityLogId =
        $activityQuery->insert_id;


    $activityQuery->close();


    // ========================================================
    // COMMIT
    // ========================================================

    $conn->commit();


    // ========================================================
    // RESPONSE
    // ========================================================

    http_response_code(200);

    echo json_encode([

        'status' => true,

        'message' =>
            'Booking checked out successfully.',

        'data' => [

            'booking_id' =>
                (int) $booking['id'],

            'booking_reference' =>
                $booking['booking_reference'],

            'customer' => [

                'id' =>
                    (int) $booking['customer_id'],

                'name' =>
                    $booking['customer_name'],

                'email' =>
                    $booking['customer_email'],

                'phone' =>
                    $booking['customer_phone']
            ],

            'check_in_date' =>
                $checkInDate,

            'check_out_date' =>
                $checkOutDate,

            'previous_booking_status' =>
                $currentStatus,

            'booking_status' =>
                'checked_out',

            'payment_status' =>
                $paymentStatus,

            'payment_completed' =>
                $paymentCompleted,

            'payment_outstanding' =>
                $paymentOutstanding,

            'total_amount' =>
                number_format(
                    (float) $booking['total_amount'],
                    2,
                    '.',
                    ''
                ),

            'rooms' =>
                array_map(
                    function ($room) {

                        return [

                            'booking_room_id' =>
                                (int) $room['id'],

                            'room_id' =>
                                (int) $room['room_id'],

                            'room_number' =>
                                $room['room_number'],

                            'room_name' =>
                                $room['room_name'],

                            'room_type_id' =>
                                (int) $room['room_type_id'],

                            'room_type_name' =>
                                $room['room_type_name']
                        ];
                    },
                    $assignedRooms
                ),

            'activity_log_id' =>
                (int) $activityLogId,

            'checked_out_by' => [

                'user_id' =>
                    $userId,

                'role' =>
                    $userRole
            ],

            'checked_out_at' =>
                date(
                    'Y-m-d H:i:s'
                )
        ]

    ]);

    exit;


} catch (Throwable $e) {

    // ========================================================
    // ROLLBACK
    // ========================================================

    $conn->rollback();


    // ========================================================
    // INTERNAL ERROR LOG
    // ========================================================

    error_log(
        'Booking checkout error: ' .
        $e->getMessage()
    );


    // ========================================================
    // ERROR RESPONSE
    // ========================================================

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to check out booking at this time.'
    ]);

    exit;
}