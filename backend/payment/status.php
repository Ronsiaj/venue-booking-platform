<?php

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);

    echo json_encode([
        'status' => false,
        'message' => 'Only GET method is allowed.'
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

function paymentStatusError($message, $code = 400)
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
| Customer Authentication
|--------------------------------------------------------------------------
*/

$user = requireCustomer();

$customerId = (int) $user['sub'];

if ($customerId <= 0) {
    paymentStatusError(
        'Invalid customer information.',
        401
    );
}


/*
|--------------------------------------------------------------------------
| Allowed Query Parameters
|--------------------------------------------------------------------------
|
| Either booking_id OR razorpay_order_id must be provided.
|
*/

$allowedParams = [
    'booking_id',
    'razorpay_order_id'
];

foreach ($_GET as $key => $value) {

    if (!in_array($key, $allowedParams, true)) {

        paymentStatusError(
            "Unknown query parameter: {$key}"
        );
    }
}


/*
|--------------------------------------------------------------------------
| At Least One Identifier
|--------------------------------------------------------------------------
*/

$hasBookingId =
    array_key_exists(
        'booking_id',
        $_GET
    );

$hasOrderId =
    array_key_exists(
        'razorpay_order_id',
        $_GET
    );

if (
    !$hasBookingId &&
    !$hasOrderId
) {

    paymentStatusError(
        'Either booking_id or razorpay_order_id is required.'
    );
}


/*
|--------------------------------------------------------------------------
| booking_id Validation
|--------------------------------------------------------------------------
*/

$bookingId = null;

if ($hasBookingId) {

    $value = $_GET['booking_id'];

    if (
        !is_string($value) ||
        !preg_match(
            '/^\d+$/',
            $value
        )
    ) {

        paymentStatusError(
            'booking_id must be a positive integer.'
        );
    }

    $bookingId = (int) $value;

    if ($bookingId <= 0) {

        paymentStatusError(
            'booking_id must be greater than zero.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Razorpay Order ID Validation
|--------------------------------------------------------------------------
*/

$razorpayOrderId = null;

if ($hasOrderId) {

    $razorpayOrderId =
        trim(
            (string)
            $_GET['razorpay_order_id']
        );

    if ($razorpayOrderId === '') {

        paymentStatusError(
            'razorpay_order_id cannot be empty.'
        );
    }

    if (strlen($razorpayOrderId) > 100) {

        paymentStatusError(
            'razorpay_order_id is too long.'
        );
    }

    if (
        !preg_match(
            '/^[A-Za-z0-9_\-]+$/',
            $razorpayOrderId
        )
    ) {

        paymentStatusError(
            'Invalid razorpay_order_id format.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| If Both IDs Provided
|--------------------------------------------------------------------------
|
| Both must belong to the same payment.
|
*/

$payment = null;


/*
|--------------------------------------------------------------------------
| Fetch Payment By Booking ID
|--------------------------------------------------------------------------
*/

if ($bookingId !== null) {

    $paymentSql = "
        SELECT
            p.id,
            p.booking_id,
            p.payment_gateway,
            p.razorpay_order_id,
            p.razorpay_payment_id,
            p.razorpay_signature,
            p.amount,
            p.currency,
            p.payment_status,
            p.payment_method,
            p.paid_at,
            p.created_at,
            p.updated_at,

            rb.booking_reference,
            rb.customer_id,
            rb.check_in_date,
            rb.check_out_date,
            rb.total_amount,
            rb.payment_status AS booking_payment_status,
            rb.booking_status

        FROM payments p

        INNER JOIN room_bookings rb
            ON rb.id = p.booking_id

        WHERE p.booking_id = ?
          AND rb.customer_id = ?

        ORDER BY p.id DESC

        LIMIT 1
    ";

    $paymentStmt =
        $conn->prepare(
            $paymentSql
        );

    if (!$paymentStmt) {

        paymentStatusError(
            'Failed to prepare payment status query.',
            500
        );
    }

    $paymentStmt->bind_param(
        'ii',
        $bookingId,
        $customerId
    );

    if (!$paymentStmt->execute()) {

        $paymentStmt->close();

        paymentStatusError(
            'Failed to fetch payment status.',
            500
        );
    }

    $paymentResult =
        $paymentStmt->get_result();

    $payment =
        $paymentResult->fetch_assoc();

    $paymentStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Booking / Payment Not Found
    |--------------------------------------------------------------------------
    */

    if (!$payment) {

        paymentStatusError(
            'Payment record not found for this booking.',
            404
        );
    }
}


/*
|--------------------------------------------------------------------------
| Fetch Payment By Razorpay Order ID
|--------------------------------------------------------------------------
*/

if (
    $razorpayOrderId !== null
) {

    $orderSql = "
        SELECT
            p.id,
            p.booking_id,
            p.payment_gateway,
            p.razorpay_order_id,
            p.razorpay_payment_id,
            p.razorpay_signature,
            p.amount,
            p.currency,
            p.payment_status,
            p.payment_method,
            p.paid_at,
            p.created_at,
            p.updated_at,

            rb.booking_reference,
            rb.customer_id,
            rb.check_in_date,
            rb.check_out_date,
            rb.total_amount,
            rb.payment_status AS booking_payment_status,
            rb.booking_status

        FROM payments p

        INNER JOIN room_bookings rb
            ON rb.id = p.booking_id

        WHERE p.razorpay_order_id = ?
          AND rb.customer_id = ?

        ORDER BY p.id DESC

        LIMIT 1
    ";

    $orderStmt =
        $conn->prepare(
            $orderSql
        );

    if (!$orderStmt) {

        paymentStatusError(
            'Failed to prepare payment order query.',
            500
        );
    }

    $orderStmt->bind_param(
        'si',
        $razorpayOrderId,
        $customerId
    );

    if (!$orderStmt->execute()) {

        $orderStmt->close();

        paymentStatusError(
            'Failed to fetch payment order status.',
            500
        );
    }

    $orderResult =
        $orderStmt->get_result();

    $orderPayment =
        $orderResult->fetch_assoc();

    $orderStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Order Not Found
    |--------------------------------------------------------------------------
    */

    if (!$orderPayment) {

        paymentStatusError(
            'Razorpay order was not found.',
            404
        );
    }


    /*
    |--------------------------------------------------------------------------
    | If Both booking_id and order_id supplied
    |--------------------------------------------------------------------------
    */

    if ($payment !== null) {

        if (
            (int) $payment['id']
            !== (int) $orderPayment['id']
        ) {

            paymentStatusError(
                'booking_id and razorpay_order_id do not belong to the same payment.',
                409
            );
        }

    } else {

        $payment = $orderPayment;
    }
}


/*
|--------------------------------------------------------------------------
| Payment Gateway Validation
|--------------------------------------------------------------------------
*/

if (
    strtolower(
        (string) $payment['payment_gateway']
    ) !== 'razorpay'
) {

    paymentStatusError(
        'Invalid payment gateway.',
        409
    );
}


/*
|--------------------------------------------------------------------------
| Validate Payment Amount
|--------------------------------------------------------------------------
*/

$paymentAmount =
    (float) $payment['amount'];

if (
    !is_finite($paymentAmount) ||
    $paymentAmount <= 0
) {

    paymentStatusError(
        'Invalid payment amount stored in database.',
        500
    );
}


/*
|--------------------------------------------------------------------------
| Validate Booking Total
|--------------------------------------------------------------------------
*/

$bookingTotal =
    (float) $payment['total_amount'];

if (
    !is_finite($bookingTotal) ||
    $bookingTotal <= 0
) {

    paymentStatusError(
        'Invalid booking total amount.',
        500
    );
}


/*
|--------------------------------------------------------------------------
| Calculate Successful Paid Amount
|--------------------------------------------------------------------------
|
| This is important when partial payment is allowed.
|
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
        ) AS total_paid

    FROM payments

    WHERE booking_id = ?
";

$paidAmountStmt =
    $conn->prepare(
        $paidAmountSql
    );

if (!$paidAmountStmt) {

    paymentStatusError(
        'Failed to prepare paid amount query.',
        500
    );
}

$paymentBookingId =
    (int) $payment['booking_id'];

$paidAmountStmt->bind_param(
    'i',
    $paymentBookingId
);

if (!$paidAmountStmt->execute()) {

    $paidAmountStmt->close();

    paymentStatusError(
        'Failed to calculate paid amount.',
        500
    );
}

$paidAmountResult =
    $paidAmountStmt->get_result();

$paidAmountRow =
    $paidAmountResult->fetch_assoc();

$paidAmountStmt->close();

$totalPaid =
    (float) (
        $paidAmountRow['total_paid'] ?? 0
    );

if (
    !is_finite($totalPaid) ||
    $totalPaid < 0
) {

    paymentStatusError(
        'Invalid paid amount.',
        500
    );
}


/*
|--------------------------------------------------------------------------
| Prevent Invalid Overpayment State
|--------------------------------------------------------------------------
*/

if (
    $totalPaid >
    ($bookingTotal + 0.01)
) {

    paymentStatusError(
        'Paid amount exceeds booking total. Please contact administrator.',
        409
    );
}


/*
|--------------------------------------------------------------------------
| Outstanding Amount
|--------------------------------------------------------------------------
*/

$outstandingAmount =
    max(
        0,
        round(
            $bookingTotal -
            $totalPaid,
            2
        )
    );


/*
|--------------------------------------------------------------------------
| Determine Human-readable Payment State
|--------------------------------------------------------------------------
*/

$paymentStatus =
    $payment['payment_status'];

$paymentCompleted =
    ($paymentStatus === 'paid');

$paymentFailed =
    ($paymentStatus === 'failed');

$paymentPending =
    (
        $paymentStatus === 'pending'
        ||
        $paymentStatus === 'partial'
    );


/*
|--------------------------------------------------------------------------
| Determine Booking Payment Status
|--------------------------------------------------------------------------
*/

$bookingPaymentStatus =
    $payment['booking_payment_status'];


/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

http_response_code(200);

echo json_encode([

    'status' => true,

    'message' =>
        'Payment status fetched successfully.',

    'data' => [

        /*
        |--------------------------------------------------------------------------
        | Booking
        |--------------------------------------------------------------------------
        */

        'booking' => [

            'id' =>
                (int) $payment['booking_id'],

            'booking_reference' =>
                $payment['booking_reference'],

            'check_in_date' =>
                $payment['check_in_date'],

            'check_out_date' =>
                $payment['check_out_date'],

            'booking_status' =>
                $payment['booking_status']
        ],


        /*
        |--------------------------------------------------------------------------
        | Payment
        |--------------------------------------------------------------------------
        */

        'payment' => [

            'payment_id' =>
                (int) $payment['id'],

            'payment_gateway' =>
                $payment['payment_gateway'],

            'razorpay_order_id' =>
                $payment['razorpay_order_id'],

            'razorpay_payment_id' =>
                $payment['razorpay_payment_id'],

            'amount' =>
                $paymentAmount,

            'amount_in_paise' =>
                (int) round(
                    $paymentAmount * 100
                ),

            'currency' =>
                strtoupper(
                    (string)
                    $payment['currency']
                ),

            'payment_status' =>
                $paymentStatus,

            'payment_method' =>
                $payment['payment_method'],

            'paid_at' =>
                $payment['paid_at'],

            'created_at' =>
                $payment['created_at'],

            'updated_at' =>
                $payment['updated_at']
        ],


        /*
        |--------------------------------------------------------------------------
        | Booking Payment Summary
        |--------------------------------------------------------------------------
        */

        'summary' => [

            'booking_total' =>
                $bookingTotal,

            'total_paid' =>
                $totalPaid,

            'outstanding_amount' =>
                $outstandingAmount,

            'booking_payment_status' =>
                $bookingPaymentStatus,

            'payment_completed' =>
                $paymentCompleted,

            'payment_failed' =>
                $paymentFailed,

            'payment_pending' =>
                $paymentPending
        ]
    ]

], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

exit;