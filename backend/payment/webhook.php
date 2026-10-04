<?php

/*
|--------------------------------------------------------------------------
| Razorpay Webhook
|--------------------------------------------------------------------------
|
| IMPORTANT:
| - This endpoint is called by Razorpay.
| - Do NOT use JWT authentication here.
| - Do NOT expect JSON input from frontend.
| - Signature must be verified using the RAW request body.
|
*/


header("Content-Type: application/json; charset=utf-8");


/*
|--------------------------------------------------------------------------
| Only POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        'status' => false,
        'message' => 'Only POST method is allowed.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/database.php';


/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function webhookError($message, $code = 400)
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
| Razorpay Webhook Secret
|--------------------------------------------------------------------------
*/

if (
    !isset($_ENV['RAZORPAY_WEBHOOK_SECRET']) ||
    trim((string) $_ENV['RAZORPAY_WEBHOOK_SECRET']) === ''
) {
    error_log(
        'Razorpay webhook secret is missing.'
    );

    webhookError(
        'Webhook configuration is missing.',
        500
    );
}

$webhookSecret =
    trim($_ENV['RAZORPAY_WEBHOOK_SECRET']);


/*
|--------------------------------------------------------------------------
| Get Razorpay Signature
|--------------------------------------------------------------------------
|
| Razorpay sends:
|
| X-Razorpay-Signature
|
*/

$razorpaySignature = '';

if (
    isset($_SERVER['HTTP_X_RAZORPAY_SIGNATURE'])
) {
    $razorpaySignature =
        trim(
            (string)
            $_SERVER['HTTP_X_RAZORPAY_SIGNATURE']
        );
}

if ($razorpaySignature === '') {

    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' =>
            'Razorpay webhook signature is missing.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Validate Signature Format
|--------------------------------------------------------------------------
*/

if (
    !preg_match(
        '/^[a-fA-F0-9]{64}$/',
        $razorpaySignature
    )
) {

    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' =>
            'Invalid Razorpay webhook signature format.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Read RAW Request Body
|--------------------------------------------------------------------------
|
| IMPORTANT:
| Do not json_encode() the decoded body for signature verification.
|
*/

$rawBody = file_get_contents(
    'php://input'
);

if (
    $rawBody === false ||
    $rawBody === ''
) {

    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' =>
            'Webhook request body is empty.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Validate HMAC Signature
|--------------------------------------------------------------------------
*/

$generatedSignature =
    hash_hmac(
        'sha256',
        $rawBody,
        $webhookSecret
    );

if (
    !hash_equals(
        $generatedSignature,
        $razorpaySignature
    )
) {

    /*
    |--------------------------------------------------------------------------
    | IMPORTANT
    |--------------------------------------------------------------------------
    |
    | Never process an unverified webhook.
    |
    */

    error_log(
        'Invalid Razorpay webhook signature received.'
    );

    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' =>
            'Invalid webhook signature.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Decode JSON
|--------------------------------------------------------------------------
*/

$payload = json_decode(
    $rawBody,
    true
);

if (
    json_last_error() !== JSON_ERROR_NONE ||
    !is_array($payload)
) {

    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' =>
            'Invalid webhook JSON payload.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Event
|--------------------------------------------------------------------------
*/

$event = $payload['event'] ?? null;

if (
    !is_string($event) ||
    trim($event) === ''
) {

    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' =>
            'Webhook event is missing.'
    ]);

    exit;
}

$event = trim($event);


/*
|--------------------------------------------------------------------------
| Supported Events
|--------------------------------------------------------------------------
|
| payment.captured
| order.paid
| payment.failed
|
*/

$allowedEvents = [
    'payment.captured',
    'order.paid',
    'payment.failed'
];

if (
    !in_array(
        $event,
        $allowedEvents,
        true
    )
) {

    /*
    |--------------------------------------------------------------------------
    | Unknown but valid webhook
    |--------------------------------------------------------------------------
    |
    | We return 200 so Razorpay does not keep retrying
    | events that our application intentionally does not process.
    |
    */

    http_response_code(200);

    echo json_encode([
        'status' => true,
        'message' =>
            'Webhook received. Event is not handled.',
        'event' => $event
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Extract Payment Entity
|--------------------------------------------------------------------------
*/

$paymentEntity = null;


/*
|--------------------------------------------------------------------------
| payment.captured / payment.failed
|--------------------------------------------------------------------------
*/

if (
    isset(
        $payload['payload']['payment']['entity']
    ) &&
    is_array(
        $payload['payload']['payment']['entity']
    )
) {

    $paymentEntity =
        $payload['payload']['payment']['entity'];
}


/*
|--------------------------------------------------------------------------
| order.paid
|--------------------------------------------------------------------------
|
| order.paid normally contains order information and
| payment information in the webhook payload.
|
*/

if (
    $paymentEntity === null &&
    isset(
        $payload['payload']['order']['entity']
    ) &&
    is_array(
        $payload['payload']['order']['entity']
    )
) {

    $orderEntity =
        $payload['payload']['order']['entity'];

    /*
    |--------------------------------------------------------------------------
    | Some payload structures may provide payment entity
    |--------------------------------------------------------------------------
    */

    if (
        isset(
            $payload['payload']['payment']['entity']
        ) &&
        is_array(
            $payload['payload']['payment']['entity']
        )
    ) {

        $paymentEntity =
            $payload['payload']['payment']['entity'];
    }
}


/*
|--------------------------------------------------------------------------
| Payment Entity Required
|--------------------------------------------------------------------------
*/

if (
    !is_array($paymentEntity)
) {

    error_log(
        'Razorpay webhook payment entity missing. Event: ' .
        $event
    );

    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' =>
            'Payment entity is missing from webhook.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Extract Razorpay IDs
|--------------------------------------------------------------------------
*/

$razorpayPaymentId =
    $paymentEntity['id'] ?? null;

$razorpayOrderId =
    $paymentEntity['order_id'] ?? null;


/*
|--------------------------------------------------------------------------
| Validate Payment ID
|--------------------------------------------------------------------------
*/

if (
    !is_string($razorpayPaymentId) ||
    trim($razorpayPaymentId) === ''
) {

    webhookError(
        'Razorpay payment ID is missing.'
    );
}

$razorpayPaymentId =
    trim($razorpayPaymentId);

if (
    strlen($razorpayPaymentId) > 100 ||
    !preg_match(
        '/^[A-Za-z0-9_\-]+$/',
        $razorpayPaymentId
    )
) {

    webhookError(
        'Invalid Razorpay payment ID.'
    );
}


/*
|--------------------------------------------------------------------------
| Validate Order ID
|--------------------------------------------------------------------------
*/

if (
    !is_string($razorpayOrderId) ||
    trim($razorpayOrderId) === ''
) {

    webhookError(
        'Razorpay order ID is missing.'
    );
}

$razorpayOrderId =
    trim($razorpayOrderId);

if (
    strlen($razorpayOrderId) > 100 ||
    !preg_match(
        '/^[A-Za-z0-9_\-]+$/',
        $razorpayOrderId
    )
) {

    webhookError(
        'Invalid Razorpay order ID.'
    );
}


/*
|--------------------------------------------------------------------------
| Extract Amount
|--------------------------------------------------------------------------
*/

$gatewayAmount =
    $paymentEntity['amount'] ?? null;

if (
    !is_numeric($gatewayAmount)
) {

    webhookError(
        'Payment amount is missing or invalid.'
    );
}

$gatewayAmount =
    (int) $gatewayAmount;

if ($gatewayAmount <= 0) {

    webhookError(
        'Payment amount must be greater than zero.'
    );
}


/*
|--------------------------------------------------------------------------
| Currency
|--------------------------------------------------------------------------
*/

$gatewayCurrency =
    strtoupper(
        trim(
            (string)
            ($paymentEntity['currency'] ?? '')
        )
    );

if ($gatewayCurrency === '') {

    webhookError(
        'Payment currency is missing.'
    );
}

if ($gatewayCurrency !== 'INR') {

    webhookError(
        'Unsupported payment currency.'
    );
}


/*
|--------------------------------------------------------------------------
| Payment Method
|--------------------------------------------------------------------------
*/

$paymentMethod =
    $paymentEntity['method'] ?? null;

if (
    $paymentMethod !== null &&
    !is_string($paymentMethod)
) {
    $paymentMethod = null;
}


/*
|--------------------------------------------------------------------------
| Gateway Payment Status
|--------------------------------------------------------------------------
*/

$gatewayPaymentStatus =
    strtolower(
        trim(
            (string)
            ($paymentEntity['status'] ?? '')
        )
    );


/*
|--------------------------------------------------------------------------
| Validate Event vs Payment Status
|--------------------------------------------------------------------------
*/

if ($event === 'payment.captured') {

    if (
        $gatewayPaymentStatus !== 'captured'
    ) {

        /*
        | Do not blindly mark as paid.
        */

        http_response_code(200);

        echo json_encode([
            'status' => true,
            'message' =>
                'Webhook received but payment is not captured.',
            'event' => $event,
            'payment_status' =>
                $gatewayPaymentStatus
        ]);

        exit;
    }
}


if ($event === 'payment.failed') {

    if (
        $gatewayPaymentStatus !== 'failed' &&
        $gatewayPaymentStatus !== ''
    ) {

        http_response_code(200);

        echo json_encode([
            'status' => true,
            'message' =>
                'Webhook received but payment status does not indicate failure.',
            'event' => $event,
            'payment_status' =>
                $gatewayPaymentStatus
        ]);

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| For order.paid
|--------------------------------------------------------------------------
*/

if ($event === 'order.paid') {

    /*
    | order.paid should correspond to a successful payment.
    */

    if (
        $gatewayPaymentStatus !== '' &&
        $gatewayPaymentStatus !== 'captured'
    ) {

        http_response_code(200);

        echo json_encode([
            'status' => true,
            'message' =>
                'Order paid webhook received but payment is not captured.',
            'event' => $event,
            'payment_status' =>
                $gatewayPaymentStatus
        ]);

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Gateway Response
|--------------------------------------------------------------------------
*/

$gatewayResponseJson =
    json_encode(
        $paymentEntity,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

if ($gatewayResponseJson === false) {

    webhookError(
        'Failed to encode gateway response.',
        500
    );
}


/*
|--------------------------------------------------------------------------
| Start DB Transaction
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();

try {

    /*
    |--------------------------------------------------------------------------
    | Find Payment by Razorpay Order ID
    |--------------------------------------------------------------------------
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

        WHERE razorpay_order_id = ?

        ORDER BY id DESC

        LIMIT 1

        FOR UPDATE
    ";

    $paymentStmt =
        $conn->prepare(
            $paymentSql
        );

    if (!$paymentStmt) {

        throw new Exception(
            'Failed to prepare payment lookup.'
        );
    }

    $paymentStmt->bind_param(
        's',
        $razorpayOrderId
    );

    if (!$paymentStmt->execute()) {

        $paymentStmt->close();

        throw new Exception(
            'Failed to lookup payment.'
        );
    }

    $paymentResult =
        $paymentStmt->get_result();

    $payment =
        $paymentResult->fetch_assoc();

    $paymentStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Payment Not Found
    |--------------------------------------------------------------------------
    */

    if (!$payment) {

        /*
        |--------------------------------------------------------------------------
        | Important
        |--------------------------------------------------------------------------
        |
        | Never create a payment record from webhook data alone.
        | The order must already exist in our database.
        |
        */

        $conn->rollback();

        error_log(
            'Razorpay webhook order not found in database: ' .
            $razorpayOrderId
        );

        http_response_code(200);

        echo json_encode([
            'status' => true,
            'message' =>
                'Webhook received but matching payment order was not found.'
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Verify Payment Gateway
    |--------------------------------------------------------------------------
    */

    if (
        strtolower(
            (string)
            $payment['payment_gateway']
        ) !== 'razorpay'
    ) {

        throw new Exception(
            'Payment gateway mismatch.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Verify DB Order ID
    |--------------------------------------------------------------------------
    */

    if (
        !hash_equals(
            (string)
            $payment['razorpay_order_id'],
            $razorpayOrderId
        )
    ) {

        throw new Exception(
            'Razorpay order ID mismatch.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Verify Currency
    |--------------------------------------------------------------------------
    */

    if (
        strtoupper(
            (string)
            $payment['currency']
        ) !== $gatewayCurrency
    ) {

        throw new Exception(
            'Payment currency mismatch.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Verify Amount
    |--------------------------------------------------------------------------
    |
    | DB amount = INR
    | Razorpay amount = paise
    |
    */

    $databaseAmount =
        (float)
        $payment['amount'];

    if (
        !is_finite($databaseAmount) ||
        $databaseAmount <= 0
    ) {

        throw new Exception(
            'Invalid payment amount in database.'
        );
    }

    $expectedPaise =
        (int)
        round(
            $databaseAmount * 100
        );

    if (
        $expectedPaise !==
        $gatewayAmount
    ) {

        throw new Exception(
            'Payment amount mismatch.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Idempotency
    |--------------------------------------------------------------------------
    |
    | Same webhook can be delivered more than once.
    |
    */

    if (
        $payment['payment_status'] === 'paid'
    ) {

        if (
            !empty(
                $payment['razorpay_payment_id']
            ) &&
            hash_equals(
                (string)
                $payment['razorpay_payment_id'],
                $razorpayPaymentId
            )
        ) {

            $conn->commit();

            http_response_code(200);

            echo json_encode([
                'status' => true,
                'message' =>
                    'Webhook already processed.',
                'payment_status' =>
                    'paid'
            ]);

            exit;
        }

        /*
        | Same order but different payment ID.
        | Do not overwrite an already paid transaction.
        */

        $conn->rollback();

        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' =>
                'Payment order is already marked as paid with another payment ID.'
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Determine New Payment Status
    |--------------------------------------------------------------------------
    */

    $newPaymentStatus = null;

    if (
        $event === 'payment.captured' ||
        $event === 'order.paid'
    ) {

        $newPaymentStatus = 'paid';

    } elseif (
        $event === 'payment.failed'
    ) {

        $newPaymentStatus = 'failed';
    }


    if ($newPaymentStatus === null) {

        throw new Exception(
            'Unable to determine payment status.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Update payments Table
    |--------------------------------------------------------------------------
    */

    if ($newPaymentStatus === 'paid') {

        $paymentUpdateSql = "
            UPDATE payments

            SET
                razorpay_payment_id = ?,
                payment_status = 'paid',
                payment_method = ?,
                gateway_response = ?,
                paid_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP

            WHERE id = ?
        ";

    } else {

        $paymentUpdateSql = "
            UPDATE payments

            SET
                razorpay_payment_id = ?,
                payment_status = 'failed',
                payment_method = ?,
                gateway_response = ?,
                updated_at = CURRENT_TIMESTAMP

            WHERE id = ?
        ";
    }


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
        'sssi',
        $razorpayPaymentId,
        $paymentMethod,
        $gatewayResponseJson,
        $payment['id']
    );

    if (
        !$paymentUpdateStmt->execute()
    ) {

        $paymentUpdateStmt->close();

        throw new Exception(
            'Failed to update payment.'
        );
    }

    $paymentUpdateStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Fetch Booking
    |--------------------------------------------------------------------------
    */

    $bookingSql = "
        SELECT
            id,
            booking_reference,
            customer_id,
            total_amount,
            payment_status,
            booking_status

        FROM room_bookings

        WHERE id = ?

        FOR UPDATE
    ";

    $bookingStmt =
        $conn->prepare(
            $bookingSql
        );

    if (!$bookingStmt) {

        throw new Exception(
            'Failed to prepare booking lookup.'
        );
    }

    $bookingId =
        (int)
        $payment['booking_id'];

    $bookingStmt->bind_param(
        'i',
        $bookingId
    );

    if (!$bookingStmt->execute()) {

        $bookingStmt->close();

        throw new Exception(
            'Failed to lookup booking.'
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

        throw new Exception(
            'Related booking was not found.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Calculate Successful Payments
    |--------------------------------------------------------------------------
    */

    $paidAmountSql = "
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

    $paidAmountStmt =
        $conn->prepare(
            $paidAmountSql
        );

    if (!$paidAmountStmt) {

        throw new Exception(
            'Failed to prepare paid amount query.'
        );
    }

    $paidAmountStmt->bind_param(
        'i',
        $bookingId
    );

    if (!$paidAmountStmt->execute()) {

        $paidAmountStmt->close();

        throw new Exception(
            'Failed to calculate paid amount.'
        );
    }

    $paidResult =
        $paidAmountStmt->get_result();

    $paidRow =
        $paidResult->fetch_assoc();

    $paidAmountStmt->close();

    $paidAmount =
        (float)
        ($paidRow['paid_amount'] ?? 0);


    /*
    |--------------------------------------------------------------------------
    | Validate Booking Total
    |--------------------------------------------------------------------------
    */

    $bookingTotal =
        (float)
        $booking['total_amount'];

    if (
        !is_finite($bookingTotal) ||
        $bookingTotal <= 0
    ) {

        throw new Exception(
            'Invalid booking total.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Prevent Overpayment
    |--------------------------------------------------------------------------
    */

    if (
        $paidAmount >
        ($bookingTotal + 0.01)
    ) {

        throw new Exception(
            'Paid amount exceeds booking total.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Determine Booking Payment Status
    |--------------------------------------------------------------------------
    */

    if (
        $paidAmount >=
        ($bookingTotal - 0.01)
    ) {

        $bookingPaymentStatus =
            'paid';

    } elseif (
        $paidAmount > 0
    ) {

        $bookingPaymentStatus =
            'partial';

    } else {

        if (
            $newPaymentStatus === 'failed'
        ) {
            $bookingPaymentStatus =
                'failed';
        } else {
            $bookingPaymentStatus =
                'pending';
        }
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
            'Failed to prepare booking payment update.'
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
    | Activity Log
    |--------------------------------------------------------------------------
    */

    if ($newPaymentStatus === 'paid') {

        $activityAction =
            'razorpay_webhook_payment_paid';

        $activityDescription =
            'Razorpay webhook confirmed a successful payment.';

    } else {

        $activityAction =
            'razorpay_webhook_payment_failed';

        $activityDescription =
            'Razorpay webhook reported a failed payment.';
    }


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
            'Failed to prepare activity log.'
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
            'Failed to create activity log.'
        );
    }

    $activityStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $conn->commit();


    /*
    |--------------------------------------------------------------------------
    | Success
    |--------------------------------------------------------------------------
    */

    http_response_code(200);

    echo json_encode([
        'status' => true,
        'message' =>
            'Razorpay webhook processed successfully.',

        'data' => [
            'event' =>
                $event,

            'booking_id' =>
                $bookingId,

            'booking_reference' =>
                $booking['booking_reference'],

            'payment_id' =>
                (int)
                $payment['id'],

            'razorpay_order_id' =>
                $razorpayOrderId,

            'razorpay_payment_id' =>
                $razorpayPaymentId,

            'amount' =>
                $databaseAmount,

            'amount_in_paise' =>
                $gatewayAmount,

            'currency' =>
                $gatewayCurrency,

            'payment_status' =>
                $newPaymentStatus,

            'booking_payment_status' =>
                $bookingPaymentStatus,

            'total_paid_amount' =>
                $paidAmount,

            'outstanding_amount' =>
                max(
                    0,
                    round(
                        $bookingTotal -
                        $paidAmount,
                        2
                    )
                )
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
    | Server Log
    |--------------------------------------------------------------------------
    */

    error_log(
        'Razorpay webhook processing error: ' .
        $e->getMessage()
    );


    /*
    |--------------------------------------------------------------------------
    | Do not expose internal error
    |--------------------------------------------------------------------------
    */

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Unable to process Razorpay webhook.'
    ]);

    exit;
}