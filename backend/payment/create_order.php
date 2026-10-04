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

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/middleware.php';


/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function paymentError($message, $code = 400)
{
    http_response_code($code);

    echo json_encode([
        'status' => false,
        'message' => $message
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
|
| Only customer can create Razorpay room booking order.
|
*/

$user = requireCustomer();

$customerId = (int) $user['sub'];

if ($customerId <= 0) {
    paymentError(
        'Invalid customer information.',
        401
    );
}


/*
|--------------------------------------------------------------------------
| Read JSON Body
|--------------------------------------------------------------------------
*/

$rawBody = file_get_contents('php://input');

if ($rawBody === false || trim($rawBody) === '') {
    paymentError(
        'Request body is required.'
    );
}

$data = json_decode(
    $rawBody,
    true
);

if (
    json_last_error() !== JSON_ERROR_NONE ||
    !is_array($data)
) {
    paymentError(
        'Invalid JSON request body.'
    );
}


/*
|--------------------------------------------------------------------------
| Reject Unknown Fields
|--------------------------------------------------------------------------
*/

$allowedFields = [
    'booking_id'
];

foreach ($data as $key => $value) {

    if (!in_array($key, $allowedFields, true)) {
        paymentError(
            "Unknown field: {$key}"
        );
    }
}


/*
|--------------------------------------------------------------------------
| booking_id Validation
|--------------------------------------------------------------------------
*/

if (!array_key_exists('booking_id', $data)) {
    paymentError(
        'booking_id is required.'
    );
}

$bookingId = $data['booking_id'];

if (
    is_array($bookingId) ||
    is_object($bookingId) ||
    !preg_match(
        '/^\d+$/',
        (string) $bookingId
    )
) {
    paymentError(
        'booking_id must be a positive integer.'
    );
}

$bookingId = (int) $bookingId;

if ($bookingId <= 0) {
    paymentError(
        'booking_id must be greater than zero.'
    );
}


/*
|--------------------------------------------------------------------------
| Customer Existence
|--------------------------------------------------------------------------
*/

$customerSql = "
    SELECT
        id,
        name,
        email,
        phone,
        status
    FROM customers
    WHERE id = ?
    LIMIT 1
";

$customerStmt = $conn->prepare(
    $customerSql
);

if (!$customerStmt) {
    paymentError(
        'Failed to prepare customer query.',
        500
    );
}

$customerStmt->bind_param(
    'i',
    $customerId
);

if (!$customerStmt->execute()) {

    $customerStmt->close();

    paymentError(
        'Failed to verify customer.',
        500
    );
}

$customerResult =
    $customerStmt->get_result();

$customer = $customerResult->fetch_assoc();

$customerStmt->close();

if (!$customer) {
    paymentError(
        'Customer account not found.',
        404
    );
}

if ($customer['status'] !== 'active') {
    paymentError(
        'Customer account is not active.',
        403
    );
}


/*
|--------------------------------------------------------------------------
| Razorpay Configuration
|--------------------------------------------------------------------------
*/

$requiredRazorpayEnv = [
    'RAZORPAY_KEY_ID',
    'RAZORPAY_KEY_SECRET',
    'RAZORPAY_CURRENCY'
];

foreach ($requiredRazorpayEnv as $envKey) {

    if (
        !isset($_ENV[$envKey]) ||
        trim((string) $_ENV[$envKey]) === ''
    ) {
        paymentError(
            'Razorpay configuration is missing.',
            500
        );
    }
}

$razorpayKeyId =
    trim($_ENV['RAZORPAY_KEY_ID']);

$razorpayKeySecret =
    trim($_ENV['RAZORPAY_KEY_SECRET']);

$razorpayCurrency =
    strtoupper(
        trim($_ENV['RAZORPAY_CURRENCY'])
    );

if ($razorpayCurrency !== 'INR') {
    paymentError(
        'Only INR payments are supported for room bookings.',
        500
    );
}


/*
|--------------------------------------------------------------------------
| Fetch Booking
|--------------------------------------------------------------------------
|
| FOR UPDATE prevents two simultaneous order-creation
| requests from reading inconsistent booking/payment state.
|
*/

$conn->begin_transaction();

try {

    $bookingSql = "
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

            c.name AS customer_name,
            c.email AS customer_email,
            c.phone AS customer_phone

        FROM room_bookings rb

        INNER JOIN customers c
            ON c.id = rb.customer_id

        WHERE rb.id = ?

        FOR UPDATE
    ";

    $bookingStmt = $conn->prepare(
        $bookingSql
    );

    if (!$bookingStmt) {
        throw new Exception(
            'Failed to prepare booking query.'
        );
    }

    $bookingStmt->bind_param(
        'i',
        $bookingId
    );

    if (!$bookingStmt->execute()) {
        $bookingStmt->close();

        throw new Exception(
            'Failed to fetch booking.'
        );
    }

    $bookingResult =
        $bookingStmt->get_result();

    $booking =
        $bookingResult->fetch_assoc();

    $bookingStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Booking Exists
    |--------------------------------------------------------------------------
    */

    if (!$booking) {

        $conn->rollback();

        paymentError(
            'Booking not found.',
            404
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Ownership Check
    |--------------------------------------------------------------------------
    */

    if (
        (int) $booking['customer_id']
        !== $customerId
    ) {

        $conn->rollback();

        paymentError(
            'You are not allowed to make payment for this booking.',
            403
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Booking Status Validation
    |--------------------------------------------------------------------------
    */

    $allowedPaymentBookingStatuses = [
        'pending',
        'confirmed'
    ];

    if (
        !in_array(
            $booking['booking_status'],
            $allowedPaymentBookingStatuses,
            true
        )
    ) {

        $conn->rollback();

        if (
            $booking['booking_status']
            === 'cancelled'
        ) {
            paymentError(
                'Cancelled bookings cannot be paid.',
                409
            );
        }

        if (
            $booking['booking_status']
            === 'checked_in'
        ) {
            paymentError(
                'Payment order cannot be created after check-in.',
                409
            );
        }

        if (
            $booking['booking_status']
            === 'checked_out'
        ) {
            paymentError(
                'Payment order cannot be created for a checked-out booking.',
                409
            );
        }

        if (
            $booking['booking_status']
            === 'no_show'
        ) {
            paymentError(
                'Payment order cannot be created for a no-show booking.',
                409
            );
        }

        paymentError(
            'Payment cannot be initiated for the current booking status.',
            409
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Booking Amount
    |--------------------------------------------------------------------------
    */

    $totalAmount = (float) $booking['total_amount'];

    if (!is_finite($totalAmount)) {

        $conn->rollback();

        paymentError(
            'Invalid booking amount.',
            500
        );
    }

    if ($totalAmount <= 0) {

        $conn->rollback();

        paymentError(
            'Booking amount must be greater than zero.',
            409
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Currency
    |--------------------------------------------------------------------------
    */

    $currency = $razorpayCurrency;

    if ($currency !== 'INR') {

        $conn->rollback();

        paymentError(
            'Invalid payment currency.',
            500
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Calculate Already Paid Amount
    |--------------------------------------------------------------------------
    |
    | Only successful "paid" records are counted.
    |
    */

    $paidSql = "
        SELECT
            COALESCE(
                SUM(
                    CASE
                        WHEN payment_status = 'paid'
                        THEN amount
                        ELSE 0
                    END
                ),
                0
            ) AS paid_amount
        FROM payments
        WHERE booking_id = ?
    ";

    $paidStmt = $conn->prepare(
        $paidSql
    );

    if (!$paidStmt) {
        throw new Exception(
            'Failed to prepare payment summary query.'
        );
    }

    $paidStmt->bind_param(
        'i',
        $bookingId
    );

    if (!$paidStmt->execute()) {

        $paidStmt->close();

        throw new Exception(
            'Failed to calculate paid amount.'
        );
    }

    $paidResult =
        $paidStmt->get_result();

    $paidRow =
        $paidResult->fetch_assoc();

    $paidStmt->close();

    $paidAmount = (float) (
        $paidRow['paid_amount'] ?? 0
    );


    /*
    |--------------------------------------------------------------------------
    | Protect Against Invalid Paid Amount
    |--------------------------------------------------------------------------
    */

    if (!is_finite($paidAmount)) {

        $conn->rollback();

        paymentError(
            'Invalid existing payment amount.',
            500
        );
    }

    if ($paidAmount < 0) {

        $conn->rollback();

        paymentError(
            'Invalid existing payment data.',
            500
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Prevent Overpayment
    |--------------------------------------------------------------------------
    */

    if ($paidAmount > $totalAmount) {

        $conn->rollback();

        paymentError(
            'Existing paid amount exceeds booking total. Please contact administrator.',
            409
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Calculate Outstanding Amount
    |--------------------------------------------------------------------------
    */

    $outstandingAmount =
        round(
            $totalAmount - $paidAmount,
            2
        );


    /*
    |--------------------------------------------------------------------------
    | Already Fully Paid
    |--------------------------------------------------------------------------
    */

    if ($outstandingAmount <= 0) {

        /*
        | Keep booking payment status synchronized.
        */

        if ($booking['payment_status'] !== 'paid') {

            $updatePaymentStatusSql = "
                UPDATE room_bookings
                SET payment_status = 'paid'
                WHERE id = ?
            ";

            $updateStmt = $conn->prepare(
                $updatePaymentStatusSql
            );

            if (!$updateStmt) {

                throw new Exception(
                    'Failed to prepare payment status update.'
                );
            }

            $updateStmt->bind_param(
                'i',
                $bookingId
            );

            if (!$updateStmt->execute()) {

                $updateStmt->close();

                throw new Exception(
                    'Failed to update payment status.'
                );
            }

            $updateStmt->close();
        }

        $conn->commit();

        paymentError(
            'This booking is already fully paid.',
            409
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Amount To Send To Razorpay
    |--------------------------------------------------------------------------
    |
    | Razorpay expects amount in currency subunits.
    | INR 100.50 => 10050 paise.
    |
    */

    $razorpayAmount =
        (int) round(
            $outstandingAmount * 100
        );

    if ($razorpayAmount <= 0) {

        $conn->rollback();

        paymentError(
            'Invalid payment amount.',
            409
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Maximum Integer Safety
    |--------------------------------------------------------------------------
    */

    if (
        $razorpayAmount >
        PHP_INT_MAX
    ) {

        $conn->rollback();

        paymentError(
            'Payment amount is too large.',
            409
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Receipt
    |--------------------------------------------------------------------------
    |
    | Razorpay receipt max length is 40 characters.
    |
    */

    $receipt =
        'SMG-' .
        $booking['booking_reference'];

    if (strlen($receipt) > 40) {

        $receipt = substr(
            $receipt,
            0,
            40
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Razorpay API Payload
    |--------------------------------------------------------------------------
    */

    $razorpayPayload = [
        'amount' => $razorpayAmount,
        'currency' => $currency,
        'receipt' => $receipt,
        'notes' => [
            'booking_id' => (string) $bookingId,
            'booking_reference' =>
                (string) $booking['booking_reference'],
            'customer_id' =>
                (string) $customerId
        ]
    ];


    /*
    |--------------------------------------------------------------------------
    | Create Razorpay Order
    |--------------------------------------------------------------------------
    */

    $ch = curl_init(
        'https://api.razorpay.com/v1/orders'
    );

    if ($ch === false) {

        $conn->rollback();

        paymentError(
            'Failed to initialize payment gateway.',
            500
        );
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,

        CURLOPT_USERPWD =>
            $razorpayKeyId .
            ':' .
            $razorpayKeySecret,

        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],

        CURLOPT_POSTFIELDS =>
            json_encode(
                $razorpayPayload,
                JSON_UNESCAPED_SLASHES
            ),

        CURLOPT_CONNECTTIMEOUT => 10,

        CURLOPT_TIMEOUT => 30,

        CURLOPT_SSL_VERIFYPEER => true,

        CURLOPT_SSL_VERIFYHOST => 2
    ]);


    $razorpayResponse =
        curl_exec($ch);

    $curlError =
        curl_error($ch);

    $curlErrorNo =
        curl_errno($ch);

    $httpCode =
        (int) curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

    curl_close($ch);


    /*
    |--------------------------------------------------------------------------
    | cURL Error
    |--------------------------------------------------------------------------
    */

    if (
        $razorpayResponse === false ||
        $curlErrorNo !== 0
    ) {

        $conn->rollback();

        paymentError(
            'Unable to connect to Razorpay.',
            502
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Decode Razorpay Response
    |--------------------------------------------------------------------------
    */

    $razorpayData =
        json_decode(
            $razorpayResponse,
            true
        );

    if (
        json_last_error() !== JSON_ERROR_NONE ||
        !is_array($razorpayData)
    ) {

        $conn->rollback();

        paymentError(
            'Invalid response received from Razorpay.',
            502
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Razorpay API Failure
    |--------------------------------------------------------------------------
    */

    if (
        $httpCode < 200 ||
        $httpCode >= 300
    ) {

        $conn->rollback();

        $gatewayMessage =
            $razorpayData['error']['description']
            ?? $razorpayData['error']['reason']
            ?? 'Razorpay order creation failed.';

        paymentError(
            $gatewayMessage,
            502
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Razorpay Order Response
    |--------------------------------------------------------------------------
    */

    $razorpayOrderId =
        $razorpayData['id']
        ?? null;

    $returnedAmount =
        $razorpayData['amount']
        ?? null;

    $returnedCurrency =
        $razorpayData['currency']
        ?? null;

    $returnedReceipt =
        $razorpayData['receipt']
        ?? null;

    $returnedStatus =
        $razorpayData['status']
        ?? null;


    if (
        !is_string($razorpayOrderId) ||
        trim($razorpayOrderId) === ''
    ) {

        $conn->rollback();

        paymentError(
            'Razorpay did not return a valid order ID.',
            502
        );
    }


    if (
        !is_numeric($returnedAmount) ||
        (int) $returnedAmount
        !== $razorpayAmount
    ) {

        $conn->rollback();

        paymentError(
            'Razorpay returned an invalid order amount.',
            502
        );
    }


    if (
        !is_string($returnedCurrency) ||
        strtoupper($returnedCurrency)
        !== $currency
    ) {

        $conn->rollback();

        paymentError(
            'Razorpay returned an invalid currency.',
            502
        );
    }


    if (
        $returnedReceipt !== null &&
        $returnedReceipt !== $receipt
    ) {

        $conn->rollback();

        paymentError(
            'Razorpay returned an invalid receipt.',
            502
        );
    }


    if (
        $returnedStatus !== null &&
        $returnedStatus !== 'created'
    ) {

        $conn->rollback();

        paymentError(
            'Razorpay order was not created successfully.',
            502
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Check Existing Razorpay Order
    |--------------------------------------------------------------------------
    |
    | Prevent duplicate active orders for the same booking.
    |
    */

    $existingOrderSql = "
        SELECT
            id,
            razorpay_order_id,
            amount,
            payment_status,
            created_at
        FROM payments
        WHERE booking_id = ?
          AND razorpay_order_id IS NOT NULL
          AND razorpay_order_id != ''
          AND payment_status = 'pending'
        ORDER BY id DESC
        LIMIT 1
        FOR UPDATE
    ";

    $existingStmt = $conn->prepare(
        $existingOrderSql
    );

    if (!$existingStmt) {

        throw new Exception(
            'Failed to check existing payment orders.'
        );
    }

    $existingStmt->bind_param(
        'i',
        $bookingId
    );

    if (!$existingStmt->execute()) {

        $existingStmt->close();

        throw new Exception(
            'Failed to check existing payment orders.'
        );
    }

    $existingResult =
        $existingStmt->get_result();

    $existingOrder =
        $existingResult->fetch_assoc();

    $existingStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Existing Pending Order
    |--------------------------------------------------------------------------
    |
    | We don't return the newly-created Razorpay order if an existing
    | pending order is already available for the same outstanding amount.
    |
    | However, because the Razorpay order has already been created above,
    | it cannot be discarded from Razorpay. Therefore this check should
    | ideally happen BEFORE creating a new Razorpay order.
    |
    | If an existing pending order is found, rollback DB transaction and
    | return existing order.
    |
    */

    if ($existingOrder) {

        $existingAmount =
            (int) round(
                ((float) $existingOrder['amount']) * 100
            );

        /*
        | payments.amount is stored in INR.
        */

        if (
            $existingAmount ===
            $razorpayAmount
        ) {

            $conn->rollback();

            http_response_code(200);

            echo json_encode([
                'status' => true,
                'message' =>
                    'An existing payment order is already available.',

                'data' => [
                    'booking_id' =>
                        $bookingId,

                    'booking_reference' =>
                        $booking['booking_reference'],

                    'razorpay_order_id' =>
                        $existingOrder['razorpay_order_id'],

                    'amount' =>
                        $outstandingAmount,

                    'amount_in_paise' =>
                        $razorpayAmount,

                    'currency' =>
                        $currency,

                    'payment_status' =>
                        'pending'
                ]
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            exit;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Insert Pending Payment Record
    |--------------------------------------------------------------------------
    |
    | payments.amount is stored in INR, NOT paise.
    |
    */

    $paymentStatusForInsert = 'pending';

    $paymentGateway = 'razorpay';

    $paymentSql = "
        INSERT INTO payments
        (
            booking_id,
            payment_gateway,
            razorpay_order_id,
            amount,
            currency,
            payment_status
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?
        )
    ";

    $paymentStmt = $conn->prepare(
        $paymentSql
    );

    if (!$paymentStmt) {

        throw new Exception(
            'Failed to prepare payment record.'
        );
    }

    $paymentAmount =
        number_format(
            $outstandingAmount,
            2,
            '.',
            ''
        );

    $paymentStmt->bind_param(
        'issdss',
        $bookingId,
        $paymentGateway,
        $razorpayOrderId,
        $paymentAmount,
        $currency,
        $paymentStatusForInsert
    );

    if (!$paymentStmt->execute()) {

        $paymentStmt->close();

        throw new Exception(
            'Failed to create payment record.'
        );
    }

    $paymentId =
        (int) $paymentStmt->insert_id;

    $paymentStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Update Booking Payment Status
    |--------------------------------------------------------------------------
    |
    | Payment order created, but payment is not completed yet.
    |
    */

    if (
        $booking['payment_status']
        !== 'partial'
    ) {

        $bookingPaymentStatus =
            $paidAmount > 0
                ? 'partial'
                : 'pending';

        $bookingStatusSql = "
            UPDATE room_bookings
            SET payment_status = ?
            WHERE id = ?
        ";

        $bookingStatusStmt =
            $conn->prepare(
                $bookingStatusSql
            );

        if (!$bookingStatusStmt) {

            throw new Exception(
                'Failed to prepare booking payment status update.'
            );
        }

        $bookingStatusStmt->bind_param(
            'si',
            $bookingPaymentStatus,
            $bookingId
        );

        if (
            !$bookingStatusStmt->execute()
        ) {

            $bookingStatusStmt->close();

            throw new Exception(
                'Failed to update booking payment status.'
            );
        }

        $bookingStatusStmt->close();
    }


    /*
    |--------------------------------------------------------------------------
    | Booking Activity Log
    |--------------------------------------------------------------------------
    */

    $activityAction =
        'razorpay_order_created';

    $activityDescription =
        'Razorpay payment order created for booking.';

    $activitySql = "
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
    ";

    $activityStmt =
        $conn->prepare(
            $activitySql
        );

    if (!$activityStmt) {

        throw new Exception(
            'Failed to prepare booking activity log.'
        );
    }

    $activityStmt->bind_param(
        'iss',
        $bookingId,
        $activityAction,
        $activityDescription
    );

    if (!$activityStmt->execute()) {

        $activityStmt->close();

        throw new Exception(
            'Failed to create booking activity log.'
        );
    }

    $activityLogId =
        (int) $activityStmt->insert_id;

    $activityStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $conn->commit();


    /*
    |--------------------------------------------------------------------------
    | Success Response
    |--------------------------------------------------------------------------
    */

    http_response_code(201);

    echo json_encode([
        'status' => true,
        'message' =>
            'Razorpay order created successfully.',

        'data' => [

            'payment_id' =>
                $paymentId,

            'booking_id' =>
                $bookingId,

            'booking_reference' =>
                $booking['booking_reference'],

            'razorpay_order_id' =>
                $razorpayOrderId,

            'razorpay_key_id' =>
                $razorpayKeyId,

            'amount' =>
                $outstandingAmount,

            'amount_in_paise' =>
                $razorpayAmount,

            'currency' =>
                $currency,

            'payment_status' =>
                'pending',

            'booking_payment_status' =>
                $paidAmount > 0
                    ? 'partial'
                    : 'pending',

            'paid_amount' =>
                $paidAmount,

            'outstanding_amount' =>
                $outstandingAmount,

            'receipt' =>
                $receipt,

            'activity_log_id' =>
                $activityLogId
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;


} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | Rollback
    |--------------------------------------------------------------------------
    */

    if ($conn->errno === 0) {
        $conn->rollback();
    }

    /*
    |--------------------------------------------------------------------------
    | Server Log
    |--------------------------------------------------------------------------
    |
    | Do not expose Razorpay credentials or internal DB details.
    |
    */

    error_log(
        'Razorpay create order error: ' .
        $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Unable to create payment order. Please try again.'
    ]);

    exit;
}