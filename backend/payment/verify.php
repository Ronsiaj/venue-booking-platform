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

function paymentVerifyError($message, $code = 400)
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
| Only customer who owns the booking can verify payment.
|
*/

$user = requireCustomer();

$customerId = (int) $user['sub'];

if ($customerId <= 0) {
    paymentVerifyError(
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

if (
    $rawBody === false ||
    trim($rawBody) === ''
) {
    paymentVerifyError(
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
    paymentVerifyError(
        'Invalid JSON request body.'
    );
}


/*
|--------------------------------------------------------------------------
| Allowed Fields
|--------------------------------------------------------------------------
*/

$allowedFields = [
    'booking_id',
    'razorpay_order_id',
    'razorpay_payment_id',
    'razorpay_signature'
];

foreach ($data as $key => $value) {

    if (!in_array($key, $allowedFields, true)) {
        paymentVerifyError(
            "Unknown field: {$key}"
        );
    }
}


/*
|--------------------------------------------------------------------------
| Required Fields
|--------------------------------------------------------------------------
*/

foreach ($allowedFields as $field) {

    if (
        !array_key_exists($field, $data) ||
        is_array($data[$field]) ||
        is_object($data[$field])
    ) {
        paymentVerifyError(
            "{$field} is required."
        );
    }
}


/*
|--------------------------------------------------------------------------
| booking_id Validation
|--------------------------------------------------------------------------
*/

$bookingId = $data['booking_id'];

if (
    !preg_match(
        '/^\d+$/',
        (string) $bookingId
    )
) {
    paymentVerifyError(
        'booking_id must be a positive integer.'
    );
}

$bookingId = (int) $bookingId;

if ($bookingId <= 0) {
    paymentVerifyError(
        'booking_id must be greater than zero.'
    );
}


/*
|--------------------------------------------------------------------------
| Razorpay Order ID Validation
|--------------------------------------------------------------------------
*/

$razorpayOrderId =
    trim((string) $data['razorpay_order_id']);

if ($razorpayOrderId === '') {
    paymentVerifyError(
        'razorpay_order_id cannot be empty.'
    );
}

if (strlen($razorpayOrderId) > 100) {
    paymentVerifyError(
        'razorpay_order_id is too long.'
    );
}

if (
    !preg_match(
        '/^[A-Za-z0-9_\-]+$/',
        $razorpayOrderId
    )
) {
    paymentVerifyError(
        'Invalid razorpay_order_id format.'
    );
}


/*
|--------------------------------------------------------------------------
| Razorpay Payment ID Validation
|--------------------------------------------------------------------------
*/

$razorpayPaymentId =
    trim((string) $data['razorpay_payment_id']);

if ($razorpayPaymentId === '') {
    paymentVerifyError(
        'razorpay_payment_id cannot be empty.'
    );
}

if (strlen($razorpayPaymentId) > 100) {
    paymentVerifyError(
        'razorpay_payment_id is too long.'
    );
}

if (
    !preg_match(
        '/^[A-Za-z0-9_\-]+$/',
        $razorpayPaymentId
    )
) {
    paymentVerifyError(
        'Invalid razorpay_payment_id format.'
    );
}


/*
|--------------------------------------------------------------------------
| Razorpay Signature Validation
|--------------------------------------------------------------------------
*/

$razorpaySignature =
    trim((string) $data['razorpay_signature']);

if ($razorpaySignature === '') {
    paymentVerifyError(
        'razorpay_signature cannot be empty.'
    );
}

if (strlen($razorpaySignature) !== 64) {
    paymentVerifyError(
        'Invalid razorpay_signature.'
    );
}

if (
    !preg_match(
        '/^[a-fA-F0-9]{64}$/',
        $razorpaySignature
    )
) {
    paymentVerifyError(
        'Invalid razorpay_signature format.'
    );
}


/*
|--------------------------------------------------------------------------
| Razorpay Configuration
|--------------------------------------------------------------------------
*/

$requiredEnv = [
    'RAZORPAY_KEY_ID',
    'RAZORPAY_KEY_SECRET',
    'RAZORPAY_CURRENCY'
];

foreach ($requiredEnv as $envKey) {

    if (
        !isset($_ENV[$envKey]) ||
        trim((string) $_ENV[$envKey]) === ''
    ) {
        paymentVerifyError(
            'Razorpay configuration is missing.',
            500
        );
    }
}

$razorpayKeyId =
    trim($_ENV['RAZORPAY_KEY_ID']);

$razorpayKeySecret =
    trim($_ENV['RAZORPAY_KEY_SECRET']);

$currency =
    strtoupper(
        trim($_ENV['RAZORPAY_CURRENCY'])
    );

if ($currency !== 'INR') {
    paymentVerifyError(
        'Only INR payments are supported.',
        500
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
    | Fetch Booking + Lock
    |--------------------------------------------------------------------------
    */

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
    | Booking Not Found
    |--------------------------------------------------------------------------
    */

    if (!$booking) {

        $conn->rollback();

        paymentVerifyError(
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

        paymentVerifyError(
            'You are not allowed to verify payment for this booking.',
            403
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Booking Status Check
    |--------------------------------------------------------------------------
    */

    if (
        in_array(
            $booking['booking_status'],
            [
                'cancelled',
                'checked_out',
                'no_show'
            ],
            true
        )
    ) {

        $conn->rollback();

        paymentVerifyError(
            'Payment cannot be verified for the current booking status.',
            409
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Fetch Payment Record
    |--------------------------------------------------------------------------
    |
    | The payment record was created by create_order.php.
    |
    */

    $paymentSql = "
        SELECT
            id,
            booking_id,
            payment_gateway,
            razorpay_order_id,
            razorpay_payment_id,
            razorpay_signature,
            amount,
            currency,
            payment_status,
            payment_method,
            gateway_response,
            paid_at,
            created_at,
            updated_at

        FROM payments

        WHERE booking_id = ?
          AND razorpay_order_id = ?

        ORDER BY id DESC

        LIMIT 1

        FOR UPDATE
    ";

    $paymentStmt = $conn->prepare(
        $paymentSql
    );

    if (!$paymentStmt) {
        throw new Exception(
            'Failed to prepare payment query.'
        );
    }

    $paymentStmt->bind_param(
        'is',
        $bookingId,
        $razorpayOrderId
    );

    if (!$paymentStmt->execute()) {

        $paymentStmt->close();

        throw new Exception(
            'Failed to fetch payment record.'
        );
    }

    $paymentResult =
        $paymentStmt->get_result();

    $payment =
        $paymentResult->fetch_assoc();

    $paymentStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Payment Record Not Found
    |--------------------------------------------------------------------------
    */

    if (!$payment) {

        $conn->rollback();

        paymentVerifyError(
            'Payment order was not found for this booking.',
            404
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Check
    |--------------------------------------------------------------------------
    */

    if (
        strtolower(
            (string) $payment['payment_gateway']
        ) !== 'razorpay'
    ) {

        $conn->rollback();

        paymentVerifyError(
            'Invalid payment gateway.',
            409
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Verify Existing Payment Status
    |--------------------------------------------------------------------------
    */

    if (
        $payment['payment_status'] === 'refunded'
    ) {

        $conn->rollback();

        paymentVerifyError(
            'This payment has already been refunded.',
            409
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Already Paid
    |--------------------------------------------------------------------------
    |
    | Idempotency:
    | If same payment was already verified, return success.
    |
    */

    if (
        $payment['payment_status'] === 'paid'
    ) {

        if (
            !empty($payment['razorpay_payment_id']) &&
            hash_equals(
                (string) $payment['razorpay_payment_id'],
                $razorpayPaymentId
            )
        ) {

            $conn->commit();

            http_response_code(200);

            echo json_encode([
                'status' => true,
                'message' =>
                    'Payment has already been verified.',

                'data' => [
                    'payment_id' =>
                        (int) $payment['id'],

                    'booking_id' =>
                        $bookingId,

                    'booking_reference' =>
                        $booking['booking_reference'],

                    'razorpay_order_id' =>
                        $razorpayOrderId,

                    'razorpay_payment_id' =>
                        $razorpayPaymentId,

                    'amount' =>
                        (float) $payment['amount'],

                    'currency' =>
                        $payment['currency'],

                    'payment_status' =>
                        'paid',

                    'booking_payment_status' =>
                        $booking['payment_status']
                ]
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            exit;
        }

        $conn->rollback();

        paymentVerifyError(
            'This payment order is already marked as paid.',
            409
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Verify Razorpay Signature
    |--------------------------------------------------------------------------
    |
    | Razorpay signature:
    |
    | HMAC_SHA256(
    |     razorpay_order_id + "|" + razorpay_payment_id,
    |     RAZORPAY_KEY_SECRET
    | )
    |
    */

    $signaturePayload =
        $razorpayOrderId .
        '|' .
        $razorpayPaymentId;

    $generatedSignature =
        hash_hmac(
            'sha256',
            $signaturePayload,
            $razorpayKeySecret
        );

    if (
        !hash_equals(
            $generatedSignature,
            $razorpaySignature
        )
    ) {

        /*
        | Mark payment as failed only if this payment record
        | is still pending.
        */

        if (
            $payment['payment_status']
            === 'pending'
        ) {

            $failedGatewayResponse = json_encode([
                'verification_error' =>
                    'Invalid Razorpay signature.',
                'razorpay_order_id' =>
                    $razorpayOrderId,
                'razorpay_payment_id' =>
                    $razorpayPaymentId
            ], JSON_UNESCAPED_SLASHES);

            $failedSql = "
                UPDATE payments

                SET
                    razorpay_payment_id = ?,
                    razorpay_signature = ?,
                    payment_status = 'failed',
                    gateway_response = ?,
                    updated_at = CURRENT_TIMESTAMP

                WHERE id = ?
            ";

            $failedStmt = $conn->prepare(
                $failedSql
            );

            if (!$failedStmt) {
                throw new Exception(
                    'Failed to prepare failed payment update.'
                );
            }

            $failedStmt->bind_param(
                'sssi',
                $razorpayPaymentId,
                $razorpaySignature,
                $failedGatewayResponse,
                $payment['id']
            );

            if (!$failedStmt->execute()) {

                $failedStmt->close();

                throw new Exception(
                    'Failed to update failed payment.'
                );
            }

            $failedStmt->close();
        }

        $conn->commit();

        paymentVerifyError(
            'Payment signature verification failed.',
            400
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Verify Razorpay Payment Through API
    |--------------------------------------------------------------------------
    |
    | Signature verification confirms the payment callback is authentic.
    | We additionally fetch the payment from Razorpay and verify:
    |
    | - payment ID
    | - order ID
    | - amount
    | - currency
    | - payment status
    |
    */

    $paymentUrl =
        'https://api.razorpay.com/v1/payments/' .
        rawurlencode($razorpayPaymentId);

    $ch = curl_init($paymentUrl);

    if ($ch === false) {

        $conn->rollback();

        paymentVerifyError(
            'Failed to initialize Razorpay verification.',
            500
        );
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,

        CURLOPT_USERPWD =>
            $razorpayKeyId .
            ':' .
            $razorpayKeySecret,

        CURLOPT_HTTPHEADER => [
            'Accept: application/json'
        ],

        CURLOPT_CONNECTTIMEOUT => 10,

        CURLOPT_TIMEOUT => 30,

        CURLOPT_SSL_VERIFYPEER => true,

        CURLOPT_SSL_VERIFYHOST => 2
    ]);

    $razorpayResponse =
        curl_exec($ch);

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
    | Razorpay API Connection Error
    |--------------------------------------------------------------------------
    */

    if (
        $razorpayResponse === false ||
        $curlErrorNo !== 0
    ) {

        $conn->rollback();

        paymentVerifyError(
            'Unable to verify payment with Razorpay.',
            502
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Decode Razorpay Response
    |--------------------------------------------------------------------------
    */

    $razorpayPayment =
        json_decode(
            $razorpayResponse,
            true
        );

    if (
        json_last_error() !== JSON_ERROR_NONE ||
        !is_array($razorpayPayment)
    ) {

        $conn->rollback();

        paymentVerifyError(
            'Invalid response received from Razorpay.',
            502
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Razorpay API Error
    |--------------------------------------------------------------------------
    */

    if (
        $httpCode < 200 ||
        $httpCode >= 300
    ) {

        $conn->rollback();

        $gatewayMessage =
            $razorpayPayment['error']['description']
            ?? $razorpayPayment['error']['reason']
            ?? 'Unable to verify Razorpay payment.';

        paymentVerifyError(
            $gatewayMessage,
            502
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Razorpay Payment ID
    |--------------------------------------------------------------------------
    */

    $gatewayPaymentId =
        $razorpayPayment['id']
        ?? null;

    if (
        !is_string($gatewayPaymentId) ||
        $gatewayPaymentId === ''
    ) {

        $conn->rollback();

        paymentVerifyError(
            'Razorpay payment ID is missing.',
            502
        );
    }

    if (
        !hash_equals(
            $razorpayPaymentId,
            $razorpayPaymentId
        )
    ) {

        $conn->rollback();

        paymentVerifyError(
            'Invalid Razorpay payment ID.',
            502
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Razorpay Order ID
    |--------------------------------------------------------------------------
    */

    $gatewayOrderId =
        $razorpayPayment['order_id']
        ?? null;

    if (
        !is_string($gatewayOrderId) ||
        $gatewayOrderId === ''
    ) {

        $conn->rollback();

        paymentVerifyError(
            'Razorpay order ID is missing from payment response.',
            502
        );
    }

    if (
        !hash_equals(
            $razorpayOrderId,
            $gatewayOrderId
        )
    ) {

        $conn->rollback();

        paymentVerifyError(
            'Razorpay order ID does not match this booking.',
            409
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Razorpay Amount
    |--------------------------------------------------------------------------
    */

    $gatewayAmount =
        $razorpayPayment['amount']
        ?? null;

    if (
        !is_numeric($gatewayAmount) ||
        (int) $gatewayAmount <= 0
    ) {

        $conn->rollback();

        paymentVerifyError(
            'Invalid amount received from Razorpay.',
            502
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Database Payment Amount
    |--------------------------------------------------------------------------
    |
    | DB amount is INR.
    | Razorpay amount is paise.
    |
    */

    $databaseAmount =
        (float) $payment['amount'];

    if (
        !is_finite($databaseAmount) ||
        $databaseAmount <= 0
    ) {

        $conn->rollback();

        paymentVerifyError(
            'Invalid payment amount in database.',
            500
        );
    }

    $expectedPaise =
        (int) round(
            $databaseAmount * 100
        );

    if (
        (int) $gatewayAmount
        !== $expectedPaise
    ) {

        $conn->rollback();

        paymentVerifyError(
            'Payment amount does not match the booking amount.',
            409
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Currency
    |--------------------------------------------------------------------------
    */

    $gatewayCurrency =
        strtoupper(
            (string) (
                $razorpayPayment['currency']
                ?? ''
            )
        );

    if (
        $gatewayCurrency !== $currency
    ) {

        $conn->rollback();

        paymentVerifyError(
            'Payment currency does not match.',
            409
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Payment Method
    |--------------------------------------------------------------------------
    */

    $paymentMethod =
        $razorpayPayment['method']
        ?? null;

    if (
        $paymentMethod !== null &&
        !is_string($paymentMethod)
    ) {
        $paymentMethod = null;
    }


    /*
    |--------------------------------------------------------------------------
    | Razorpay Payment Status
    |--------------------------------------------------------------------------
    */

    $gatewayPaymentStatus =
        strtolower(
            (string) (
                $razorpayPayment['status']
                ?? ''
            )
        );


    /*
    |--------------------------------------------------------------------------
    | Only Captured Payment Is Successful
    |--------------------------------------------------------------------------
    */

    if (
        $gatewayPaymentStatus !== 'captured'
    ) {

        /*
        |--------------------------------------------------------------------------
        | Authorized
        |--------------------------------------------------------------------------
        |
        | Payment exists but has not been captured yet.
        | Do NOT mark it as paid.
        |
        */

        if (
            $gatewayPaymentStatus === 'authorized'
        ) {

            $gatewayResponseJson =
                json_encode(
                    $razorpayPayment,
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES
                );

            $authorizedSql = "
                UPDATE payments

                SET
                    razorpay_payment_id = ?,
                    razorpay_signature = ?,
                    payment_method = ?,
                    gateway_response = ?,
                    payment_status = 'pending',
                    updated_at = CURRENT_TIMESTAMP

                WHERE id = ?
            ";

            $authorizedStmt =
                $conn->prepare(
                    $authorizedSql
                );

            if (!$authorizedStmt) {
                throw new Exception(
                    'Failed to prepare authorized payment update.'
                );
            }

            $authorizedStmt->bind_param(
                'ssssi',
                $razorpayPaymentId,
                $razorpaySignature,
                $paymentMethod,
                $gatewayResponseJson,
                $payment['id']
            );

            if (
                !$authorizedStmt->execute()
            ) {

                $authorizedStmt->close();

                throw new Exception(
                    'Failed to update authorized payment.'
                );
            }

            $authorizedStmt->close();

            $conn->commit();

            http_response_code(202);

            echo json_encode([
                'status' => true,
                'message' =>
                    'Payment is authorized but not captured yet.',

                'data' => [
                    'payment_id' =>
                        (int) $payment['id'],

                    'booking_id' =>
                        $bookingId,

                    'booking_reference' =>
                        $booking['booking_reference'],

                    'razorpay_order_id' =>
                        $razorpayOrderId,

                    'razorpay_payment_id' =>
                        $razorpayPaymentId,

                    'amount' =>
                        $databaseAmount,

                    'currency' =>
                        $currency,

                    'payment_status' =>
                        'pending',

                    'razorpay_status' =>
                        'authorized'
                ]
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | Failed / Other Status
        |--------------------------------------------------------------------------
        */

        $gatewayResponseJson =
            json_encode(
                $razorpayPayment,
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            );

        $failedSql = "
            UPDATE payments

            SET
                razorpay_payment_id = ?,
                razorpay_signature = ?,
                payment_method = ?,
                gateway_response = ?,
                payment_status = 'failed',
                updated_at = CURRENT_TIMESTAMP

            WHERE id = ?
        ";

        $failedStmt =
            $conn->prepare(
                $failedSql
            );

        if (!$failedStmt) {
            throw new Exception(
                'Failed to prepare payment failure update.'
            );
        }

        $failedStmt->bind_param(
            'ssssi',
            $razorpayPaymentId,
            $razorpaySignature,
            $paymentMethod,
            $gatewayResponseJson,
            $payment['id']
        );

        if (!$failedStmt->execute()) {

            $failedStmt->close();

            throw new Exception(
                'Failed to update failed payment.'
            );
        }

        $failedStmt->close();


        /*
        |--------------------------------------------------------------------------
        | Booking Payment Status
        |--------------------------------------------------------------------------
        |
        | Check if there are already successful payments.
        |
        */

        $successfulSql = "
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

        $successfulStmt =
            $conn->prepare(
                $successfulSql
            );

        if (!$successfulStmt) {
            throw new Exception(
                'Failed to prepare successful payment query.'
            );
        }

        $successfulStmt->bind_param(
            'i',
            $bookingId
        );

        if (!$successfulStmt->execute()) {

            $successfulStmt->close();

            throw new Exception(
                'Failed to calculate successful payments.'
            );
        }

        $successfulResult =
            $successfulStmt->get_result();

        $successfulRow =
            $successfulResult->fetch_assoc();

        $successfulStmt->close();

        $existingPaidAmount =
            (float) (
                $successfulRow['paid_amount']
                ?? 0
            );


        $failedBookingPaymentStatus =
            $existingPaidAmount > 0
                ? 'partial'
                : 'failed';


        $bookingPaymentSql = "
            UPDATE room_bookings

            SET
                payment_status = ?

            WHERE id = ?
        ";

        $bookingPaymentStmt =
            $conn->prepare(
                $bookingPaymentSql
            );

        if (!$bookingPaymentStmt) {
            throw new Exception(
                'Failed to prepare booking payment status update.'
            );
        }

        $bookingPaymentStmt->bind_param(
            'si',
            $failedBookingPaymentStatus,
            $bookingId
        );

        if (
            !$bookingPaymentStmt->execute()
        ) {

            $bookingPaymentStmt->close();

            throw new Exception(
                'Failed to update booking payment status.'
            );
        }

        $bookingPaymentStmt->close();


        /*
        |--------------------------------------------------------------------------
        | Failure Activity Log
        |--------------------------------------------------------------------------
        */

        $failureAction =
            'razorpay_payment_failed';

        $failureDescription =
            'Razorpay payment verification failed or payment was not captured.';

        $failureLogSql = "
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

        $failureLogStmt =
            $conn->prepare(
                $failureLogSql
            );

        if (!$failureLogStmt) {
            throw new Exception(
                'Failed to prepare payment failure log.'
            );
        }

        $failureLogStmt->bind_param(
            'iss',
            $bookingId,
            $failureAction,
            $failureDescription
        );

        if (!$failureLogStmt->execute()) {

            $failureLogStmt->close();

            throw new Exception(
                'Failed to create payment failure log.'
            );
        }

        $failureLogStmt->close();


        $conn->commit();

        paymentVerifyError(
            'Razorpay payment was not captured.',
            409
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CAPTURED PAYMENT
    |--------------------------------------------------------------------------
    */

    $gatewayResponseJson =
        json_encode(
            $razorpayPayment,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

    if (
        $gatewayResponseJson === false
    ) {
        throw new Exception(
            'Failed to encode Razorpay response.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Update Payment Record
    |--------------------------------------------------------------------------
    */

    $paymentUpdateSql = "
        UPDATE payments

        SET
            razorpay_payment_id = ?,
            razorpay_signature = ?,
            payment_status = 'paid',
            payment_method = ?,
            gateway_response = ?,
            paid_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP

        WHERE id = ?
    ";

    $paymentUpdateStmt =
        $conn->prepare(
            $paymentUpdateSql
        );

    if (!$paymentUpdateStmt) {
        throw new Exception(
            'Failed to prepare payment update.'
        );
    }

    $paymentUpdateStmt->bind_param(
        'ssssi',
        $razorpayPaymentId,
        $razorpaySignature,
        $paymentMethod,
        $gatewayResponseJson,
        $payment['id']
    );

    if (!$paymentUpdateStmt->execute()) {

        $paymentUpdateStmt->close();

        throw new Exception(
            'Failed to update payment record.'
        );
    }

    $paymentUpdateStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Calculate Total Paid Amount
    |--------------------------------------------------------------------------
    */

    $totalPaidSql = "
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
            ) AS total_paid

        FROM payments

        WHERE booking_id = ?
    ";

    $totalPaidStmt =
        $conn->prepare(
            $totalPaidSql
        );

    if (!$totalPaidStmt) {
        throw new Exception(
            'Failed to prepare total payment query.'
        );
    }

    $totalPaidStmt->bind_param(
        'i',
        $bookingId
    );

    if (!$totalPaidStmt->execute()) {

        $totalPaidStmt->close();

        throw new Exception(
            'Failed to calculate total paid amount.'
        );
    }

    $totalPaidResult =
        $totalPaidStmt->get_result();

    $totalPaidRow =
        $totalPaidResult->fetch_assoc();

    $totalPaidStmt->close();

    $totalPaid =
        (float) (
            $totalPaidRow['total_paid']
            ?? 0
        );


    /*
    |--------------------------------------------------------------------------
    | Booking Total
    |--------------------------------------------------------------------------
    */

    $bookingTotal =
        (float) $booking['total_amount'];

    if (
        !is_finite($bookingTotal) ||
        $bookingTotal <= 0
    ) {
        throw new Exception(
            'Invalid booking total amount.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Determine Booking Payment Status
    |--------------------------------------------------------------------------
    */

    if (
        $totalPaid >=
        ($bookingTotal - 0.01)
    ) {

        $bookingPaymentStatus =
            'paid';

    } else {

        $bookingPaymentStatus =
            'partial';
    }


    /*
    |--------------------------------------------------------------------------
    | Update Booking Payment Status
    |--------------------------------------------------------------------------
    */

    $bookingUpdateSql = "
        UPDATE room_bookings

        SET
            payment_status = ?

        WHERE id = ?
    ";

    $bookingUpdateStmt =
        $conn->prepare(
            $bookingUpdateSql
        );

    if (!$bookingUpdateStmt) {
        throw new Exception(
            'Failed to prepare booking status update.'
        );
    }

    $bookingUpdateStmt->bind_param(
        'si',
        $bookingPaymentStatus,
        $bookingId
    );

    if (
        !$bookingUpdateStmt->execute()
    ) {

        $bookingUpdateStmt->close();

        throw new Exception(
            'Failed to update booking payment status.'
        );
    }

    $bookingUpdateStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Booking Activity Log
    |--------------------------------------------------------------------------
    */

    $activityAction =
        'razorpay_payment_verified';

    $activityDescription =
        'Razorpay payment verified and captured successfully.';

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
            'Failed to prepare payment activity log.'
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
            'Failed to create payment activity log.'
        );
    }

    $activityLogId =
        (int) $activityStmt->insert_id;

    $activityStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Commit Transaction
    |--------------------------------------------------------------------------
    */

    $conn->commit();


    /*
    |--------------------------------------------------------------------------
    | Calculate Outstanding
    |--------------------------------------------------------------------------
    */

    $outstandingAmount =
        max(
            0,
            round(
                $bookingTotal - $totalPaid,
                2
            )
        );


    /*
    |--------------------------------------------------------------------------
    | Success Response
    |--------------------------------------------------------------------------
    */

    http_response_code(200);

    echo json_encode([
        'status' => true,

        'message' =>
            'Payment verified successfully.',

        'data' => [

            'payment_id' =>
                (int) $payment['id'],

            'booking_id' =>
                $bookingId,

            'booking_reference' =>
                $booking['booking_reference'],

            'razorpay_order_id' =>
                $razorpayOrderId,

            'razorpay_payment_id' =>
                $razorpayPaymentId,

            'amount' =>
                $databaseAmount,

            'amount_in_paise' =>
                $expectedPaise,

            'currency' =>
                $currency,

            'payment_status' =>
                'paid',

            'razorpay_status' =>
                'captured',

            'booking_payment_status' =>
                $bookingPaymentStatus,

            'total_booking_amount' =>
                $bookingTotal,

            'total_paid_amount' =>
                $totalPaid,

            'outstanding_amount' =>
                $outstandingAmount,

            'payment_method' =>
                $paymentMethod,

            'paid_at' =>
                date('Y-m-d H:i:s'),

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

    $conn->rollback();

    /*
    |--------------------------------------------------------------------------
    | Server-side logging only
    |--------------------------------------------------------------------------
    */

    error_log(
        'Razorpay payment verification error: ' .
        $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Unable to verify payment. Please try again.'
    ]);

    exit;
}