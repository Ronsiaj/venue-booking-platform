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
// AUTHENTICATION
// ============================================================

$user = requireAnyUser();


// ============================================================
// USER DETAILS
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
            'staff',
            'customer'
        ],
        true
    )
) {

    http_response_code(403);

    echo json_encode([
        'status' => false,
        'message' => 'Invalid user role.'
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
    'booking_id',
    'reason'
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
// REASON REQUIRED
// ============================================================

if (!array_key_exists(
    'reason',
    $input
)) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Cancellation reason is required.'
    ]);

    exit;
}


// ============================================================
// REASON TYPE VALIDATION
// ============================================================

if (!is_string(
    $input['reason']
)) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Cancellation reason must be a string.'
    ]);

    exit;
}


$reason = trim(
    $input['reason']
);


// ============================================================
// REASON EMPTY VALIDATION
// ============================================================

if ($reason === '') {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Cancellation reason cannot be empty.'
    ]);

    exit;
}


// ============================================================
// REASON LENGTH VALIDATION
// ============================================================

if (strlen($reason) < 3) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Cancellation reason must contain at least 3 characters.'
    ]);

    exit;
}


if (strlen($reason) > 1000) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Cancellation reason cannot exceed 1000 characters.'
    ]);

    exit;
}


// ============================================================
// MULTIPLE SPACE VALIDATION
// ============================================================

if (
    preg_match(
        '/\s{2,}/',
        $reason
    )
) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Cancellation reason cannot contain consecutive spaces.'
    ]);

    exit;
}


// ============================================================
// DATABASE TRANSACTION
// ============================================================

$conn->begin_transaction();


try {

    // ========================================================
    // FETCH BOOKING WITH ROW LOCK
    // ========================================================

    if ($userRole === 'customer') {

        /*
         * Customer can only cancel their own booking.
         */

        $bookingQuery = $conn->prepare("
            SELECT
                rb.id,
                rb.booking_reference,
                rb.customer_id,
                rb.check_in_date,
                rb.check_out_date,
                rb.total_guests,
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
              AND rb.customer_id = ?

            LIMIT 1

            FOR UPDATE
        ");

        if (!$bookingQuery) {
            throw new Exception(
                'Failed to prepare booking query.'
            );
        }


        $bookingQuery->bind_param(
            'ii',
            $bookingId,
            $userId
        );

    } else {

        /*
         * Admin / Staff can cancel any booking.
         */

        $bookingQuery = $conn->prepare("
            SELECT
                rb.id,
                rb.booking_reference,
                rb.customer_id,
                rb.check_in_date,
                rb.check_out_date,
                rb.total_guests,
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
    }


    // ========================================================
    // EXECUTE
    // ========================================================

    if (!$bookingQuery->execute()) {

        $bookingQuery->close();

        throw new Exception(
            'Failed to fetch booking.'
        );
    }


    $result = $bookingQuery->get_result();

    $booking = $result->fetch_assoc();

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
    // CURRENT BOOKING STATUS
    // ========================================================

    $currentBookingStatus =
        $booking['booking_status'];


    // ========================================================
    // ALREADY CANCELLED
    // ========================================================

    if (
        $currentBookingStatus === 'cancelled'
    ) {

        $conn->rollback();

        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' => 'Booking is already cancelled.',
            'data' => [
                'booking_id' =>
                    (int) $booking['id'],

                'booking_reference' =>
                    $booking['booking_reference'],

                'booking_status' =>
                    $currentBookingStatus,

                'cancellation_reason' =>
                    $booking['cancellation_reason']
            ]
        ]);

        exit;
    }


    // ========================================================
    // CHECKED-IN VALIDATION
    // ========================================================

    if (
        $currentBookingStatus === 'checked_in'
    ) {

        $conn->rollback();

        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' => 'A checked-in booking cannot be cancelled.'
        ]);

        exit;
    }


    // ========================================================
    // CHECKED-OUT VALIDATION
    // ========================================================

    if (
        $currentBookingStatus === 'checked_out'
    ) {

        $conn->rollback();

        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' => 'A checked-out booking cannot be cancelled.'
        ]);

        exit;
    }


    // ========================================================
    // NO-SHOW VALIDATION
    // ========================================================

    if (
        $currentBookingStatus === 'no_show'
    ) {

        $conn->rollback();

        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' => 'A no-show booking cannot be cancelled.'
        ]);

        exit;
    }


    // ========================================================
    // ALLOWED CANCELLATION STATUSES
    // ========================================================

    if (
        !in_array(
            $currentBookingStatus,
            [
                'pending',
                'confirmed'
            ],
            true
        )
    ) {

        $conn->rollback();

        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' => 'This booking cannot be cancelled in its current status.',
            'current_status' =>
                $currentBookingStatus
        ]);

        exit;
    }


    // ========================================================
    // CHECK-IN DATE VALIDATION
    // ========================================================

    $checkInDate = $booking['check_in_date'];

    $checkInObject = DateTime::createFromFormat(
        '!Y-m-d',
        $checkInDate
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


    // ========================================================
    // CURRENT DATE
    // ========================================================

    $today = new DateTime(
        'today',
        new DateTimeZone('Asia/Kolkata')
    );


    // ========================================================
    // SAME-DAY / PAST CHECK-IN VALIDATION
    // ========================================================
    //
    // Once the check-in date has arrived, normal customer
    // cancellation is not allowed.
    //
    // Admin / staff also follow the same cancellation rule.
    // ========================================================

    if (
        $checkInObject <= $today
    ) {

        $conn->rollback();

        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' => 'Booking cannot be cancelled on or after the check-in date.',
            'check_in_date' =>
                $checkInDate
        ]);

        exit;
    }


    // ========================================================
    // UPDATE BOOKING
    // ========================================================

    $updateQuery = $conn->prepare("
        UPDATE room_bookings

        SET
            booking_status = 'cancelled',
            cancellation_reason = ?,
            updated_at = CURRENT_TIMESTAMP

        WHERE id = ?
          AND booking_status IN (
              'pending',
              'confirmed'
          )
    ");


    if (!$updateQuery) {

        throw new Exception(
            'Failed to prepare cancellation update.'
        );
    }


    $updateQuery->bind_param(
        'si',
        $reason,
        $bookingId
    );


    if (!$updateQuery->execute()) {

        $updateQuery->close();

        throw new Exception(
            'Failed to cancel booking.'
        );
    }


    $affectedRows =
        $updateQuery->affected_rows;


    $updateQuery->close();


    // ========================================================
    // RACE CONDITION CHECK
    // ========================================================

    if ($affectedRows !== 1) {

        throw new Exception(
            'Booking cancellation could not be completed.'
        );
    }


    // ========================================================
    // PAYMENT INFORMATION
    // ========================================================

    /*
     * We do NOT automatically change payment_status here.
     *
     * If payment is already paid/partial, refund should be
     * processed through a separate Razorpay refund API.
     *
     * If payment failed/pending, it remains unchanged until
     * the payment/refund workflow handles it.
     */

    $paymentStatus =
        $booking['payment_status'];


    $refundRequired = in_array(
        $paymentStatus,
        [
            'paid',
            'partial'
        ],
        true
    );


    // ========================================================
    // ACTIVITY LOG
    // ========================================================

    if (
        $userRole === 'admin' ||
        $userRole === 'staff'
    ) {

        $adminId = $userId;

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
                'booking_cancelled',
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
            "Booking cancelled by {$userRole}. Reason: {$reason}";


        $activityQuery->bind_param(
            'isi',
            $bookingId,
            $description,
            $adminId
        );


    } else {

        /*
         * Customer does not have admin_users ID.
         * Therefore performed_by_admin_id remains NULL.
         */

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
                'booking_cancelled',
                ?,
                NULL
            )
        ");


        if (!$activityQuery) {

            throw new Exception(
                'Failed to prepare activity log.'
            );
        }


        $description =
            "Booking cancelled by customer. Reason: {$reason}";


        $activityQuery->bind_param(
            'is',
            $bookingId,
            $description
        );
    }


    if (!$activityQuery->execute()) {

        $activityQuery->close();

        throw new Exception(
            'Failed to create booking activity log.'
        );
    }


    $activityQuery->close();


    // ========================================================
    // COMMIT
    // ========================================================

    $conn->commit();


    // ========================================================
    // SUCCESS RESPONSE
    // ========================================================

    http_response_code(200);

    echo json_encode([

        'status' => true,

        'message' =>
            'Booking cancelled successfully.',

        'data' => [

            'booking_id' =>
                (int) $booking['id'],

            'booking_reference' =>
                $booking['booking_reference'],

            'customer_id' =>
                (int) $booking['customer_id'],

            'previous_booking_status' =>
                $currentBookingStatus,

            'booking_status' =>
                'cancelled',

            'cancellation_reason' =>
                $reason,

            'payment_status' =>
                $paymentStatus,

            'refund_required' =>
                $refundRequired,

            'refund_status' =>
                $refundRequired
                    ? 'pending'
                    : 'not_required',

            'cancelled_by' => [

                'user_id' =>
                    $userId,

                'role' =>
                    $userRole
            ],

            'cancelled_at' =>
                date('Y-m-d H:i:s')
        ]

    ]);

    exit;


} catch (Throwable $e) {

    // ========================================================
    // ROLLBACK
    // ========================================================

    if ($conn->errno === 0) {
        $conn->rollback();
    } else {
        $conn->rollback();
    }


    // ========================================================
    // LOG INTERNAL ERROR
    // ========================================================

    error_log(
        'Booking cancellation error: ' .
        $e->getMessage()
    );


    // ========================================================
    // ERROR RESPONSE
    // ========================================================

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to cancel booking at this time.'
    ]);

    exit;
}