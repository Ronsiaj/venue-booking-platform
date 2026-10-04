<?php

/*
|--------------------------------------------------------------------------
| SM GROUP - EMAIL NOTIFICATION
|--------------------------------------------------------------------------
| Sends lead notification emails using PHPMailer + SMTP.
|
| Request:
| POST /notification/email.php
|
| JSON:
| {
|     "lead_id": 1,
|     "notification_type": "all"
| }
|
| notification_type:
| admin    = all active admins
| assigned = assigned admin/staff only
| all      = admins + assigned user
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| ERROR REPORTING
|--------------------------------------------------------------------------
*/

ini_set('display_errors', '0');
ini_set('log_errors', '1');

error_reporting(E_ALL);


/*
|--------------------------------------------------------------------------
| CORS
|--------------------------------------------------------------------------
*/

header(
    'Content-Type: application/json; charset=UTF-8'
);

header(
    'Access-Control-Allow-Origin: *'
);

header(
    'Access-Control-Allow-Methods: POST, OPTIONS'
);

header(
    'Access-Control-Allow-Headers: Content-Type, Authorization'
);


/*
|--------------------------------------------------------------------------
| OPTIONS
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'OPTIONS'
) {

    http_response_code(200);

    exit;
}


/*
|--------------------------------------------------------------------------
| METHOD VALIDATION
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
|
| database.php already:
| - loads Composer autoload
| - loads config/.env
| - creates $conn
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/database.php';


/*
|--------------------------------------------------------------------------
| PHPMailer AUTOLOAD
|--------------------------------------------------------------------------
|
| database.php normally loads this already.
| This additional check makes this API independent.
|--------------------------------------------------------------------------
*/

$autoloadPath =
    __DIR__ .
    '/../vendor/autoload.php';


if (
    !file_exists($autoloadPath)
) {

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Composer autoload file not found.'
    ]);

    exit;
}

require_once $autoloadPath;


/*
|--------------------------------------------------------------------------
| RESPONSE HELPER
|--------------------------------------------------------------------------
*/

function sendJsonResponse(
    int $statusCode,
    bool $status,
    string $message,
    array $data = []
): void {

    http_response_code($statusCode);

    echo json_encode(
        [
            'status' => $status,
            'message' => $message,
            'data' => $data
        ],
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| TEXT CLEANER
|--------------------------------------------------------------------------
*/

function cleanText(
    $value
): string {

    return trim(
        preg_replace(
            '/\s+/u',
            ' ',
            (string)$value
        )
    );
}


/*
|--------------------------------------------------------------------------
| HTML ESCAPE
|--------------------------------------------------------------------------
*/

function e(
    $value
): string {

    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| DATE FORMAT
|--------------------------------------------------------------------------
*/

function formatEmailDate(
    $date
): string {

    if (
        empty($date)
    ) {

        return '-';
    }

    $timestamp =
        strtotime(
            (string)$date
        );

    if (
        $timestamp === false
    ) {

        return (string)$date;
    }

    return date(
        'd M Y',
        $timestamp
    );
}


/*
|--------------------------------------------------------------------------
| DATETIME FORMAT
|--------------------------------------------------------------------------
*/

function formatEmailDateTime(
    $dateTime
): string {

    if (
        empty($dateTime)
    ) {

        return '-';
    }

    $timestamp =
        strtotime(
            (string)$dateTime
        );

    if (
        $timestamp === false
    ) {

        return (string)$dateTime;
    }

    return date(
        'd M Y, h:i A',
        $timestamp
    );
}


/*
|--------------------------------------------------------------------------
| DATABASE VALIDATION
|--------------------------------------------------------------------------
*/

if (
    !isset($conn) ||
    !($conn instanceof mysqli)
) {

    sendJsonResponse(
        500,
        false,
        'Database connection is not available.'
    );
}


/*
|--------------------------------------------------------------------------
| REQUEST BODY
|--------------------------------------------------------------------------
*/

$rawInput =
    file_get_contents(
        'php://input'
    );


if (
    $rawInput === false ||
    trim($rawInput) === ''
) {

    sendJsonResponse(
        400,
        false,
        'Request body is required.'
    );
}


/*
|--------------------------------------------------------------------------
| JSON DECODE
|--------------------------------------------------------------------------
*/

$data =
    json_decode(
        $rawInput,
        true
    );


if (
    json_last_error() !== JSON_ERROR_NONE ||
    !is_array($data)
) {

    sendJsonResponse(
        400,
        false,
        'Invalid JSON request body.'
    );
}


/*
|--------------------------------------------------------------------------
| ALLOWED FIELDS
|--------------------------------------------------------------------------
*/

$allowedFields = [
    'lead_id',
    'notification_type'
];


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

        sendJsonResponse(
            400,
            false,
            "Unknown field: {$key}"
        );
    }
}


/*
|--------------------------------------------------------------------------
| LEAD ID VALIDATION
|--------------------------------------------------------------------------
*/

if (
    !isset($data['lead_id'])
) {

    sendJsonResponse(
        422,
        false,
        'lead_id is required.'
    );
}


if (
    !is_int($data['lead_id']) &&
    !ctype_digit(
        (string)$data['lead_id']
    )
) {

    sendJsonResponse(
        422,
        false,
        'lead_id must be a positive integer.'
    );
}


$leadId =
    (int)$data['lead_id'];


if (
    $leadId <= 0
) {

    sendJsonResponse(
        422,
        false,
        'lead_id must be greater than zero.'
    );
}


/*
|--------------------------------------------------------------------------
| NOTIFICATION TYPE
|--------------------------------------------------------------------------
*/

$notificationType =
    isset($data['notification_type'])
        ? strtolower(
            cleanText(
                $data['notification_type']
            )
        )
        : 'all';


$allowedNotificationTypes = [
    'admin',
    'assigned',
    'all'
];


if (
    !in_array(
        $notificationType,
        $allowedNotificationTypes,
        true
    )
) {

    sendJsonResponse(
        422,
        false,
        'notification_type must be admin, assigned or all.'
    );
}


/*
|--------------------------------------------------------------------------
| SMTP CONFIGURATION
|--------------------------------------------------------------------------
|
| IMPORTANT:
| database.php loads config/.env using phpdotenv.
|
| Therefore use $_ENV instead of getenv().
|--------------------------------------------------------------------------
*/

$mailHost =
    $_ENV['MAIL_HOST']
    ?? null;


$mailPort =
    $_ENV['MAIL_PORT']
    ?? null;


$mailUsername =
    $_ENV['MAIL_USERNAME']
    ?? null;


$mailPassword =
    $_ENV['MAIL_PASSWORD']
    ?? null;


$mailEncryption =
    strtolower(
        trim(
            (string)(
                $_ENV['MAIL_ENCRYPTION']
                ?? 'tls'
            )
        )
    );


$mailFromEmail =
    $_ENV['MAIL_FROM_EMAIL']
    ?? null;


$mailFromName =
    $_ENV['MAIL_FROM_NAME']
    ?? 'SM GROUP';


/*
|--------------------------------------------------------------------------
| SMTP HOST VALIDATION
|--------------------------------------------------------------------------
*/

if (
    $mailHost === null ||
    trim((string)$mailHost) === ''
) {

    sendJsonResponse(
        500,
        false,
        'MAIL_HOST is not configured.'
    );
}

$mailHost =
    trim(
        (string)$mailHost
    );


/*
|--------------------------------------------------------------------------
| SMTP PORT VALIDATION
|--------------------------------------------------------------------------
*/

if (
    $mailPort === null ||
    !ctype_digit(
        (string)$mailPort
    )
) {

    sendJsonResponse(
        500,
        false,
        'MAIL_PORT is not configured correctly.'
    );
}


$mailPort =
    (int)$mailPort;


if (
    $mailPort < 1 ||
    $mailPort > 65535
) {

    sendJsonResponse(
        500,
        false,
        'MAIL_PORT must be between 1 and 65535.'
    );
}


/*
|--------------------------------------------------------------------------
| SMTP USERNAME VALIDATION
|--------------------------------------------------------------------------
*/

if (
    $mailUsername === null ||
    trim((string)$mailUsername) === ''
) {

    sendJsonResponse(
        500,
        false,
        'MAIL_USERNAME is not configured.'
    );
}


$mailUsername =
    trim(
        (string)$mailUsername
    );


if (
    !filter_var(
        $mailUsername,
        FILTER_VALIDATE_EMAIL
    )
) {

    sendJsonResponse(
        500,
        false,
        'MAIL_USERNAME must be a valid email address.'
    );
}


/*
|--------------------------------------------------------------------------
| SMTP PASSWORD VALIDATION
|--------------------------------------------------------------------------
*/

if (
    $mailPassword === null ||
    trim((string)$mailPassword) === ''
) {

    sendJsonResponse(
        500,
        false,
        'MAIL_PASSWORD is not configured.'
    );
}


/*
|--------------------------------------------------------------------------
| FROM EMAIL VALIDATION
|--------------------------------------------------------------------------
*/

if (
    $mailFromEmail === null ||
    trim((string)$mailFromEmail) === ''
) {

    sendJsonResponse(
        500,
        false,
        'MAIL_FROM_EMAIL is not configured.'
    );
}


$mailFromEmail =
    trim(
        (string)$mailFromEmail
    );


if (
    !filter_var(
        $mailFromEmail,
        FILTER_VALIDATE_EMAIL
    )
) {

    sendJsonResponse(
        500,
        false,
        'MAIL_FROM_EMAIL is not a valid email address.'
    );
}


/*
|--------------------------------------------------------------------------
| FROM NAME
|--------------------------------------------------------------------------
*/

$mailFromName =
    trim(
        (string)$mailFromName
    );


if (
    $mailFromName === ''
) {

    $mailFromName =
        'SM GROUP';
}


/*
|--------------------------------------------------------------------------
| ENCRYPTION VALIDATION
|--------------------------------------------------------------------------
*/

if (
    $mailEncryption === ''
) {

    $mailEncryption =
        'tls';
}


$allowedEncryptions = [
    'tls',
    'ssl',
    'none'
];


if (
    !in_array(
        $mailEncryption,
        $allowedEncryptions,
        true
    )
) {

    sendJsonResponse(
        500,
        false,
        'MAIL_ENCRYPTION must be tls, ssl or none.'
    );
}


/*
|--------------------------------------------------------------------------
| FETCH LEAD
|--------------------------------------------------------------------------
*/

$leadSql = "

    SELECT

        l.id,
        l.lead_reference,
        l.domain,

        l.customer_name,
        l.customer_phone,
        l.customer_email,

        l.event_date,
        l.guest_count,
        l.preferred_time,

        l.requirement,

        l.status,

        l.estimated_amount,
        l.converted_amount,
        l.converted_at,

        l.source,

        l.assigned_admin_id,

        l.created_at,
        l.updated_at,

        au.name AS assigned_admin_name,
        au.email AS assigned_admin_email,
        au.phone AS assigned_admin_phone,
        au.role AS assigned_admin_role,
        au.status AS assigned_admin_status

    FROM leads l

    LEFT JOIN admin_users au
        ON au.id = l.assigned_admin_id

    WHERE l.id = ?

    LIMIT 1

";


$leadStmt =
    $conn->prepare(
        $leadSql
    );


if (
    !$leadStmt
) {

    sendJsonResponse(
        500,
        false,
        'Failed to prepare lead query.'
    );
}


$leadStmt->bind_param(
    'i',
    $leadId
);


if (
    !$leadStmt->execute()
) {

    $leadStmt->close();

    sendJsonResponse(
        500,
        false,
        'Failed to fetch lead.'
    );
}


$leadResult =
    $leadStmt->get_result();


$lead =
    $leadResult->fetch_assoc();


$leadStmt->close();


if (
    !$lead
) {

    sendJsonResponse(
        404,
        false,
        'Lead not found.'
    );
}


/*
|--------------------------------------------------------------------------
| DOMAIN VALIDATION
|--------------------------------------------------------------------------
*/

$domain =
    strtolower(
        trim(
            (string)$lead['domain']
        )
    );


$allowedDomains = [
    'hall',
    'dining',
    'catering'
];


if (
    !in_array(
        $domain,
        $allowedDomains,
        true
    )
) {

    sendJsonResponse(
        422,
        false,
        'Invalid lead domain.'
    );
}


/*
|--------------------------------------------------------------------------
| DOMAIN DETAILS
|--------------------------------------------------------------------------
*/

$domainDetails =
    null;


/*
|--------------------------------------------------------------------------
| HALL DETAILS
|--------------------------------------------------------------------------
*/

if (
    $domain === 'hall'
) {

    $sql = "

        SELECT

            hld.hall_id,
            hld.event_type,
            hld.event_date,
            hld.event_time,
            hld.guest_count,

            hld.food_required,
            hld.decoration_required,

            hld.other_requirements,

            h.name AS hall_name,

            ht.name AS hall_type_name

        FROM hall_lead_details hld

        LEFT JOIN halls h
            ON h.id = hld.hall_id

        LEFT JOIN hall_types ht
            ON ht.id = h.hall_type_id

        WHERE hld.lead_id = ?

        LIMIT 1

    ";


    $stmt =
        $conn->prepare($sql);


    if (!$stmt) {

        sendJsonResponse(
            500,
            false,
            'Failed to prepare hall details query.'
        );
    }


    $stmt->bind_param(
        'i',
        $leadId
    );


    if (!$stmt->execute()) {

        $stmt->close();

        sendJsonResponse(
            500,
            false,
            'Failed to fetch hall details.'
        );
    }


    $result =
        $stmt->get_result();


    $domainDetails =
        $result->fetch_assoc();


    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| DINING DETAILS
|--------------------------------------------------------------------------
*/

if (
    $domain === 'dining'
) {

    $sql = "

        SELECT

            dld.dining_package_id,
            dld.meal_type,
            dld.dining_date,
            dld.dining_time,
            dld.guest_count,
            dld.other_requirements,

            dp.name AS package_name

        FROM dining_lead_details dld

        LEFT JOIN dining_packages dp
            ON dp.id = dld.dining_package_id

        WHERE dld.lead_id = ?

        LIMIT 1

    ";


    $stmt =
        $conn->prepare($sql);


    if (!$stmt) {

        sendJsonResponse(
            500,
            false,
            'Failed to prepare dining details query.'
        );
    }


    $stmt->bind_param(
        'i',
        $leadId
    );


    if (!$stmt->execute()) {

        $stmt->close();

        sendJsonResponse(
            500,
            false,
            'Failed to fetch dining details.'
        );
    }


    $result =
        $stmt->get_result();


    $domainDetails =
        $result->fetch_assoc();


    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| CATERING DETAILS
|--------------------------------------------------------------------------
*/

if (
    $domain === 'catering'
) {

    $sql = "

        SELECT

            cld.catering_package_id,
            cld.event_type,
            cld.event_date,
            cld.event_time,
            cld.guest_count,

            cld.venue_address,
            cld.estimated_amount,
            cld.other_requirements,

            cp.name AS package_name

        FROM catering_lead_details cld

        LEFT JOIN catering_packages cp
            ON cp.id = cld.catering_package_id

        WHERE cld.lead_id = ?

        LIMIT 1

    ";


    $stmt =
        $conn->prepare($sql);


    if (!$stmt) {

        sendJsonResponse(
            500,
            false,
            'Failed to prepare catering details query.'
        );
    }


    $stmt->bind_param(
        'i',
        $leadId
    );


    if (!$stmt->execute()) {

        $stmt->close();

        sendJsonResponse(
            500,
            false,
            'Failed to fetch catering details.'
        );
    }


    $result =
        $stmt->get_result();


    $domainDetails =
        $result->fetch_assoc();


    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| CUSTOM FOOD ITEMS
|--------------------------------------------------------------------------
*/

$customItems = [];


if (
    $domain === 'dining'
) {

    $sql = "

        SELECT

            dli.food_item_id,
            dli.quantity,
            dli.notes,

            fi.name AS food_item_name,
            fi.food_type,

            fc.name AS food_category_name

        FROM dining_lead_items dli

        INNER JOIN food_items fi
            ON fi.id = dli.food_item_id

        LEFT JOIN food_categories fc
            ON fc.id = fi.food_category_id

        WHERE dli.lead_id = ?

        ORDER BY fi.name ASC

    ";


    $stmt =
        $conn->prepare($sql);


    if (!$stmt) {

        sendJsonResponse(
            500,
            false,
            'Failed to prepare dining items query.'
        );
    }


    $stmt->bind_param(
        'i',
        $leadId
    );


    if (!$stmt->execute()) {

        $stmt->close();

        sendJsonResponse(
            500,
            false,
            'Failed to fetch dining custom items.'
        );
    }


    $result =
        $stmt->get_result();


    while (
        $item =
            $result->fetch_assoc()
    ) {

        $customItems[] =
            $item;
    }


    $stmt->close();
}


if (
    $domain === 'catering'
) {

    $sql = "

        SELECT

            cli.food_item_id,
            cli.quantity,
            cli.notes,

            fi.name AS food_item_name,
            fi.food_type,

            fc.name AS food_category_name

        FROM catering_lead_items cli

        INNER JOIN food_items fi
            ON fi.id = cli.food_item_id

        LEFT JOIN food_categories fc
            ON fc.id = fi.food_category_id

        WHERE cli.lead_id = ?

        ORDER BY fi.name ASC

    ";


    $stmt =
        $conn->prepare($sql);


    if (!$stmt) {

        sendJsonResponse(
            500,
            false,
            'Failed to prepare catering items query.'
        );
    }


    $stmt->bind_param(
        'i',
        $leadId
    );


    if (!$stmt->execute()) {

        $stmt->close();

        sendJsonResponse(
            500,
            false,
            'Failed to fetch catering custom items.'
        );
    }


    $result =
        $stmt->get_result();


    while (
        $item =
            $result->fetch_assoc()
    ) {

        $customItems[] =
            $item;
    }


    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| RECIPIENTS
|--------------------------------------------------------------------------
*/

$recipients = [];


/*
|--------------------------------------------------------------------------
| ADD RECIPIENT
|--------------------------------------------------------------------------
*/

$addRecipient =
    function (
        array $user
    ) use (
        &$recipients
    ): void {

        $email =
            strtolower(
                trim(
                    (string)(
                        $user['email']
                        ?? ''
                    )
                )
            );


        if (
            $email === '' ||
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            return;
        }


        $recipients[$email] = [

            'id' =>
                isset($user['id'])
                    ? (int)$user['id']
                    : null,

            'name' =>
                trim(
                    (string)(
                        $user['name']
                        ?? ''
                    )
                ),

            'email' =>
                $email,

            'role' =>
                trim(
                    (string)(
                        $user['role']
                        ?? ''
                    )
                ),

            'phone' =>
                trim(
                    (string)(
                        $user['phone']
                        ?? ''
                    )
                )
        ];
    };


/*
|--------------------------------------------------------------------------
| ACTIVE ADMIN RECIPIENTS
|--------------------------------------------------------------------------
|
| Project roles:
| admin
| staff
| customer
|
| Admin notification goes only to admin accounts.
|--------------------------------------------------------------------------
*/

if (
    $notificationType === 'admin' ||
    $notificationType === 'all'
) {

    $sql = "

        SELECT

            id,
            name,
            email,
            phone,
            role,
            status

        FROM admin_users

        WHERE status = 'active'

        AND role = 'admin'

        AND email IS NOT NULL

        AND TRIM(email) <> ''

        ORDER BY id ASC

    ";


    $stmt =
        $conn->prepare($sql);


    if (!$stmt) {

        sendJsonResponse(
            500,
            false,
            'Failed to prepare admin recipient query.'
        );
    }


    if (!$stmt->execute()) {

        $stmt->close();

        sendJsonResponse(
            500,
            false,
            'Failed to fetch admin recipients.'
        );
    }


    $result =
        $stmt->get_result();


    while (
        $admin =
            $result->fetch_assoc()
    ) {

        $addRecipient(
            $admin
        );
    }


    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| ASSIGNED ADMIN / STAFF
|--------------------------------------------------------------------------
*/

if (
    $notificationType === 'assigned' ||
    $notificationType === 'all'
) {

    if (
        !empty(
            $lead['assigned_admin_id']
        )
    ) {

        $assignedRole =
            strtolower(
                trim(
                    (string)(
                        $lead[
                            'assigned_admin_role'
                        ]
                        ?? ''
                    )
                )
            );


        $assignedStatus =
            strtolower(
                trim(
                    (string)(
                        $lead[
                            'assigned_admin_status'
                        ]
                        ?? ''
                    )
                )
            );


        if (
            $assignedStatus === 'active' &&
            in_array(
                $assignedRole,
                [
                    'admin',
                    'staff'
                ],
                true
            )
        ) {

            $addRecipient([
                'id' =>
                    $lead[
                        'assigned_admin_id'
                    ],

                'name' =>
                    $lead[
                        'assigned_admin_name'
                    ],

                'email' =>
                    $lead[
                        'assigned_admin_email'
                    ],

                'phone' =>
                    $lead[
                        'assigned_admin_phone'
                    ],

                'role' =>
                    $lead[
                        'assigned_admin_role'
                    ]
            ]);
        }
    }
}


/*
|--------------------------------------------------------------------------
| NO RECIPIENT
|--------------------------------------------------------------------------
*/

if (
    count($recipients) === 0
) {

    sendJsonResponse(
        422,
        false,
        'No valid email recipients found for this notification type.'
    );
}


/*
|--------------------------------------------------------------------------
| EMAIL SUBJECT
|--------------------------------------------------------------------------
*/

$domainTitle =
    ucfirst(
        $domain
    );


$emailSubject =
    'SM GROUP - New ' .
    $domainTitle .
    ' Lead - ' .
    $lead['lead_reference'];


/*
|--------------------------------------------------------------------------
| DOMAIN HTML
|--------------------------------------------------------------------------
*/

$domainHtml = '';


/*
|--------------------------------------------------------------------------
| HALL HTML
|--------------------------------------------------------------------------
*/

if (
    $domain === 'hall' &&
    $domainDetails
) {

    $domainHtml .= '

    <div class="section">

        <div class="section-title">
            Hall Enquiry Details
        </div>

        <table class="details">

    ';


    $hallRows = [

        'Hall' =>
            $domainDetails[
                'hall_name'
            ] ?? '-',

        'Hall Type' =>
            $domainDetails[
                'hall_type_name'
            ] ?? '-',

        'Event Type' =>
            $domainDetails[
                'event_type'
            ] ?? '-',

        'Event Date' =>
            !empty(
                $domainDetails['event_date']
            )
                ? formatEmailDate(
                    $domainDetails[
                        'event_date'
                    ]
                )
                : '-',

        'Event Time' =>
            $domainDetails[
                'event_time'
            ] ?? '-',

        'Guest Count' =>
            $domainDetails[
                'guest_count'
            ] !== null
                ? number_format(
                    (int)$domainDetails[
                        'guest_count'
                    ]
                )
                : '-',

        'Food Required' =>
            !empty(
                $domainDetails[
                    'food_required'
                ]
            )
                ? 'Yes'
                : 'No',

        'Decoration Required' =>
            !empty(
                $domainDetails[
                    'decoration_required'
                ]
            )
                ? 'Yes'
                : 'No'
    ];


    foreach (
        $hallRows as $label => $value
    ) {

        $domainHtml .= '

            <tr>

                <td class="label">
                    ' .
                    e($label) .
                    '
                </td>

                <td class="value">
                    ' .
                    e($value) .
                    '
                </td>

            </tr>

        ';
    }


    $domainHtml .= '

        </table>

    </div>

    ';
}


/*
|--------------------------------------------------------------------------
| DINING HTML
|--------------------------------------------------------------------------
*/

if (
    $domain === 'dining' &&
    $domainDetails
) {

    $domainHtml .= '

    <div class="section">

        <div class="section-title">
            Dining Enquiry Details
        </div>

        <table class="details">

    ';


    $diningRows = [

        'Package' =>
            $domainDetails[
                'package_name'
            ] ?? '-',

        'Meal Type' =>
            !empty(
                $domainDetails[
                    'meal_type'
                ]
            )
                ? ucfirst(
                    $domainDetails[
                        'meal_type'
                    ]
                )
                : '-',

        'Dining Date' =>
            !empty(
                $domainDetails[
                    'dining_date'
                ]
            )
                ? formatEmailDate(
                    $domainDetails[
                        'dining_date'
                    ]
                )
                : '-',

        'Dining Time' =>
            $domainDetails[
                'dining_time'
            ] ?? '-',

        'Guest Count' =>
            $domainDetails[
                'guest_count'
            ] !== null
                ? number_format(
                    (int)$domainDetails[
                        'guest_count'
                    ]
                )
                : '-'
    ];


    foreach (
        $diningRows as $label => $value
    ) {

        $domainHtml .= '

            <tr>

                <td class="label">
                    ' .
                    e($label) .
                    '
                </td>

                <td class="value">
                    ' .
                    e($value) .
                    '
                </td>

            </tr>

        ';
    }


    $domainHtml .= '

        </table>

    </div>

    ';
}


/*
|--------------------------------------------------------------------------
| CATERING HTML
|--------------------------------------------------------------------------
*/

if (
    $domain === 'catering' &&
    $domainDetails
) {

    $domainHtml .= '

    <div class="section">

        <div class="section-title">
            Catering Enquiry Details
        </div>

        <table class="details">

    ';


    $cateringRows = [

        'Package' =>
            $domainDetails[
                'package_name'
            ] ?? '-',

        'Event Type' =>
            $domainDetails[
                'event_type'
            ] ?? '-',

        'Event Date' =>
            !empty(
                $domainDetails[
                    'event_date'
                ]
            )
                ? formatEmailDate(
                    $domainDetails[
                        'event_date'
                    ]
                )
                : '-',

        'Event Time' =>
            $domainDetails[
                'event_time'
            ] ?? '-',

        'Guest Count' =>
            $domainDetails[
                'guest_count'
            ] !== null
                ? number_format(
                    (int)$domainDetails[
                        'guest_count'
                    ]
                )
                : '-',

        'Venue' =>
            $domainDetails[
                'venue_address'
            ] ?? '-',

        'Estimated Amount' =>
            $domainDetails[
                'estimated_amount'
            ] !== null
                ? '₹' .
                    number_format(
                        (float)$domainDetails[
                            'estimated_amount'
                        ],
                        2
                    )
                : '-'
    ];


    foreach (
        $cateringRows as $label => $value
    ) {

        $domainHtml .= '

            <tr>

                <td class="label">
                    ' .
                    e($label) .
                    '
                </td>

                <td class="value ' .
                    (
                        $label ===
                        'Estimated Amount'
                            ? 'amount'
                            : ''
                    ) .
                    '">
                    ' .
                    nl2br(
                        e($value)
                    ) .
                    '
                </td>

            </tr>

        ';
    }


    $domainHtml .= '

        </table>

    </div>

    ';
}


/*
|--------------------------------------------------------------------------
| CUSTOM FOOD ITEMS HTML
|--------------------------------------------------------------------------
*/

if (
    count($customItems) > 0
) {

    $domainHtml .= '

    <div class="section">

        <div class="section-title">
            Custom Food Items
        </div>

        <table class="food-table">

            <thead>

                <tr>

                    <th>Food Item</th>
                    <th>Category</th>
                    <th>Type</th>
                    <th>Qty</th>
                    <th>Notes</th>

                </tr>

            </thead>

            <tbody>

    ';


    foreach (
        $customItems as $item
    ) {

        $domainHtml .= '

            <tr>

                <td>
                    ' .
                    e(
                        $item[
                            'food_item_name'
                        ]
                    ) .
                    '
                </td>

                <td>
                    ' .
                    e(
                        $item[
                            'food_category_name'
                        ] ?? '-'
                    ) .
                    '
                </td>

                <td>
                    ' .
                    e(
                        ucfirst(
                            $item[
                                'food_type'
                            ] ?? '-'
                        )
                    ) .
                    '
                </td>

                <td>
                    ' .
                    e(
                        $item['quantity']
                    ) .
                    '
                </td>

                <td>
                    ' .
                    e(
                        $item['notes'] ?? '-'
                    ) .
                    '
                </td>

            </tr>

        ';
    }


    $domainHtml .= '

            </tbody>

        </table>

    </div>

    ';
}


/*
|--------------------------------------------------------------------------
| REQUIREMENTS
|--------------------------------------------------------------------------
*/

$requirements =
    $lead['requirement']
    ?? null;


if (
    $domainDetails &&
    !empty(
        $domainDetails[
            'other_requirements'
        ]
    )
) {

    $requirements =
        $domainDetails[
            'other_requirements'
        ];
}


$requirementsHtml = '';


if (
    !empty($requirements)
) {

    $requirementsHtml = '

    <div class="section">

        <div class="section-title">
            Customer Requirements
        </div>

        <div class="requirements">
            ' .
            nl2br(
                e($requirements)
            ) .
            '
        </div>

    </div>

    ';
}


/*
|--------------------------------------------------------------------------
| ASSIGNED USER
|--------------------------------------------------------------------------
*/

$assignedHtml = '';


if (
    !empty(
        $lead['assigned_admin_name']
    )
) {

    $assignedHtml = '

    <div class="assigned-box">

        <div class="assigned-title">
            Assigned To
        </div>

        <div class="assigned-name">
            ' .
            e(
                $lead[
                    'assigned_admin_name'
                ]
            ) .
            '
        </div>

        <div class="assigned-meta">
            ' .
            e(
                ucfirst(
                    $lead[
                        'assigned_admin_role'
                    ] ?? ''
                )
            ) .
            ' &nbsp; • &nbsp; ' .
            e(
                $lead[
                    'assigned_admin_email'
                ] ?? ''
            ) .
            '
        </div>

    </div>

    ';
}


/*
|--------------------------------------------------------------------------
| AMOUNT HTML
|--------------------------------------------------------------------------
*/

$amountHtml = '';


if (
    $lead['estimated_amount'] !== null
) {

    $amountHtml .= '

        <div class="amount-card">

            <div class="amount-label">
                Estimated Amount
            </div>

            <div class="amount-value">
                ₹' .
                number_format(
                    (float)$lead[
                        'estimated_amount'
                    ],
                    2
                ) .
                '
            </div>

        </div>

    ';
}


if (
    $lead['converted_amount'] !== null
) {

    $amountHtml .= '

        <div class="amount-card">

            <div class="amount-label">
                Converted Amount
            </div>

            <div class="amount-value">
                ₹' .
                number_format(
                    (float)$lead[
                        'converted_amount'
                    ],
                    2
                ) .
                '
            </div>

        </div>

    ';
}


/*
|--------------------------------------------------------------------------
| HTML EMAIL
|--------------------------------------------------------------------------
*/

$htmlBody = '

<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    ' .
    e($emailSubject) .
'
</title>

<style>

body {
    margin: 0;
    padding: 0;
    background: #f3f4f6;
    font-family: Arial, Helvetica, sans-serif;
    color: #1f2937;
}

.wrapper {
    width: 100%;
    padding: 30px 15px;
    box-sizing: border-box;
}

.container {
    max-width: 720px;
    margin: 0 auto;
    background: #ffffff;
    border-radius: 18px;
    overflow: hidden;
    box-shadow:
        0 15px 40px
        rgba(0,0,0,0.10);
}

.header {
    background:
        linear-gradient(
            135deg,
            #0a0a0c,
            #242424,
            #0a0a0c
        );
    padding: 32px;
}

.brand {
    color: #ffffff;
    font-size: 28px;
    font-weight: 800;
    letter-spacing: 2px;
}

.brand span {
    color: #c99a4b;
}

.tagline {
    margin-top: 7px;
    color: #d1d5db;
    font-size: 12px;
    letter-spacing: 1.5px;
}

.gold-line {
    width: 70px;
    height: 3px;
    background: #c99a4b;
    margin-top: 18px;
}

.hero {
    padding: 30px;
    background:
        linear-gradient(
            135deg,
            #fffdf8,
            #f8f3e9
        );
}

.badge {
    display: inline-block;
    padding: 7px 13px;
    border-radius: 30px;
    background: #c99a4b;
    color: #ffffff;
    font-size: 11px;
    font-weight: bold;
    letter-spacing: 1px;
    text-transform: uppercase;
}

.hero-title {
    margin-top: 16px;
    margin-bottom: 8px;
    font-size: 26px;
    font-weight: 800;
    color: #111827;
}

.hero-subtitle {
    color: #6b7280;
    font-size: 14px;
    line-height: 1.6;
}

.reference {
    margin-top: 18px;
    padding: 14px 16px;
    border-left: 4px solid #c99a4b;
    background: #ffffff;
    border-radius: 8px;
}

.reference strong {
    color: #111827;
}

.status {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    background: #fef3c7;
    color: #92400e;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.customer-card {
    margin: 24px 30px;
    padding: 20px;
    background: #111827;
    border-radius: 14px;
    color: #ffffff;
}

.customer-title {
    color: #c99a4b;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: bold;
    margin-bottom: 12px;
}

.customer-name {
    font-size: 20px;
    font-weight: 800;
    margin-bottom: 8px;
}

.customer-row {
    font-size: 13px;
    color: #d1d5db;
    line-height: 1.8;
}

.customer-row a {
    color: #e9c680;
    text-decoration: none;
}

.section {
    margin: 24px 30px;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    overflow: hidden;
}

.section-title {
    padding: 15px 18px;
    background: #fafafa;
    border-bottom: 1px solid #e5e7eb;
    font-size: 14px;
    font-weight: 800;
    color: #111827;
}

.details {
    width: 100%;
    border-collapse: collapse;
}

.details td {
    padding: 13px 18px;
    border-bottom: 1px solid #f0f0f0;
    font-size: 13px;
    vertical-align: top;
}

.details tr:last-child td {
    border-bottom: none;
}

.label {
    width: 38%;
    color: #6b7280;
    font-weight: 600;
}

.value {
    color: #111827;
    font-weight: 600;
}

.amount {
    color: #a8782e;
    font-weight: 800;
}

.requirements {
    padding: 18px;
    font-size: 13px;
    line-height: 1.7;
    color: #374151;
}

.assigned-box {
    margin: 24px 30px;
    padding: 18px;
    border-radius: 12px;
    background: #f5f7fa;
    border: 1px solid #e5e7eb;
}

.assigned-title {
    font-size: 11px;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.assigned-name {
    margin-top: 6px;
    font-size: 16px;
    font-weight: 800;
    color: #111827;
}

.assigned-meta {
    margin-top: 5px;
    font-size: 12px;
    color: #6b7280;
}

.food-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}

.food-table th {
    background: #111827;
    color: #ffffff;
    padding: 11px;
    text-align: left;
}

.food-table td {
    padding: 11px;
    border-bottom: 1px solid #eeeeee;
    color: #374151;
}

.amount-wrapper {
    padding: 0 30px;
}

.amount-card {
    display: inline-block;
    vertical-align: top;
    width: 42%;
    margin-right: 3%;
    padding: 18px;
    border-radius: 12px;
    background: #faf7f0;
    border: 1px solid #eadfc9;
    box-sizing: border-box;
}

.amount-label {
    font-size: 11px;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.8px;
}

.amount-value {
    margin-top: 7px;
    font-size: 21px;
    font-weight: 800;
    color: #a8782e;
}

.action {
    margin: 30px;
    padding: 22px;
    text-align: center;
    background:
        linear-gradient(
            135deg,
            #0a0a0c,
            #202020
        );
    border-radius: 14px;
}

.action-title {
    color: #ffffff;
    font-size: 17px;
    font-weight: 800;
}

.action-text {
    margin-top: 7px;
    color: #d1d5db;
    font-size: 12px;
}

.footer {
    padding: 24px 30px;
    background: #f9fafb;
    text-align: center;
    border-top: 1px solid #e5e7eb;
}

.footer-brand {
    font-size: 17px;
    font-weight: 800;
    color: #111827;
}

.footer-brand span {
    color: #c99a4b;
}

.footer-text {
    margin-top: 7px;
    font-size: 11px;
    line-height: 1.6;
    color: #9ca3af;
}

@media only screen and (max-width: 600px) {

    .wrapper {
        padding: 10px;
    }

    .hero {
        padding: 22px;
    }

    .section {
        margin: 18px 15px;
    }

    .customer-card {
        margin: 18px 15px;
    }

    .assigned-box {
        margin: 18px 15px;
    }

    .action {
        margin: 18px 15px;
    }

    .amount-wrapper {
        padding: 0 15px;
    }

    .amount-card {
        display: block;
        width: auto;
        margin: 10px 0;
    }

    .food-table {
        font-size: 10px;
    }
}

</style>

</head>

<body>

<div class="wrapper">

    <div class="container">

        <div class="header">

            <div class="brand">
                SM <span>GROUP</span>
            </div>

            <div class="tagline">
                HOSPITALITY • EVENTS • DINING • CATERING
            </div>

            <div class="gold-line"></div>

        </div>


        <div class="hero">

            <span class="badge">
                New Lead
            </span>

            <div class="hero-title">
                New ' .
                e($domainTitle) .
                ' Enquiry Received
            </div>

            <div class="hero-subtitle">
                A new customer enquiry has been received
                through the SM GROUP website.
            </div>

            <div class="reference">

                <strong>
                    Lead Reference:
                </strong>

                ' .
                e(
                    $lead['lead_reference']
                ) .
                '

                <br>

                <strong>
                    Lead ID:
                </strong>

                ' .
                e(
                    $lead['id']
                ) .
                '

                <br>

                <strong>
                    Status:
                </strong>

                <span class="status">
                    ' .
                    e(
                        $lead['status']
                    ) .
                    '
                </span>

            </div>

        </div>


        <div class="customer-card">

            <div class="customer-title">
                Customer Details
            </div>

            <div class="customer-name">
                ' .
                e(
                    $lead['customer_name']
                ) .
                '
            </div>

            <div class="customer-row">

                Phone:
                <a href="tel:' .
                    e(
                        $lead['customer_phone']
                    ) .
                '">
                    ' .
                    e(
                        $lead['customer_phone']
                    ) .
                '
                </a>

                <br>

                ';


if (
    !empty(
        $lead['customer_email']
    )
) {

    $htmlBody .= '

                Email:
                <a href="mailto:' .
                    e(
                        $lead['customer_email']
                    ) .
                '">
                    ' .
                    e(
                        $lead['customer_email']
                    ) .
                '
                </a>

                <br>

    ';
}


$htmlBody .= '

                Source:
                ' .
                e(
                    ucfirst(
                        $lead['source']
                    )
                ) .
                '

                <br>

                Created:
                ' .
                e(
                    formatEmailDateTime(
                        $lead['created_at']
                    )
                ) .
                '

            </div>

        </div>


        ' .
        $domainHtml .
        '


        ' .
        $requirementsHtml .
        '


        <div class="amount-wrapper">

            ' .
            $amountHtml .
            '

        </div>


        ' .
        $assignedHtml .
        '


        <div class="action">

            <div class="action-title">
                Action Required
            </div>

            <div class="action-text">
                Please review this enquiry and contact
                the customer as soon as possible.
            </div>

        </div>


        <div class="footer">

            <div class="footer-brand">
                SM <span>GROUP</span>
            </div>

            <div class="footer-text">
                This is an automated notification from
                SM GROUP.<br>
                Please do not reply directly to this
                automated email.
            </div>

        </div>

    </div>

</div>

</body>

</html>

';


/*
|--------------------------------------------------------------------------
| PLAIN TEXT EMAIL
|--------------------------------------------------------------------------
*/

$plainText =

    "SM GROUP - NEW LEAD NOTIFICATION\n\n" .

    "Lead Reference: " .
    $lead['lead_reference'] .
    "\n" .

    "Lead ID: " .
    $lead['id'] .
    "\n" .

    "Domain: " .
    strtoupper($domain) .
    "\n" .

    "Status: " .
    strtoupper($lead['status']) .
    "\n\n" .

    "CUSTOMER DETAILS\n" .

    "Name: " .
    $lead['customer_name'] .
    "\n" .

    "Phone: " .
    $lead['customer_phone'] .
    "\n";


if (
    !empty(
        $lead['customer_email']
    )
) {

    $plainText .=
        "Email: " .
        $lead['customer_email'] .
        "\n";
}


$plainText .= "\n";


/*
|--------------------------------------------------------------------------
| HALL PLAIN TEXT
|--------------------------------------------------------------------------
*/

if (
    $domain === 'hall' &&
    $domainDetails
) {

    $plainText .=

        "HALL DETAILS\n" .

        "Hall: " .
        ($domainDetails[
            'hall_name'
        ] ?? '-') .
        "\n" .

        "Hall Type: " .
        ($domainDetails[
            'hall_type_name'
        ] ?? '-') .
        "\n" .

        "Event Type: " .
        ($domainDetails[
            'event_type'
        ] ?? '-') .
        "\n" .

        "Event Date: " .
        ($domainDetails[
            'event_date'
        ] ?? '-') .
        "\n" .

        "Event Time: " .
        ($domainDetails[
            'event_time'
        ] ?? '-') .
        "\n" .

        "Guests: " .
        ($domainDetails[
            'guest_count'
        ] ?? '-') .
        "\n" .

        "Food Required: " .
        (
            !empty(
                $domainDetails[
                    'food_required'
                ]
            )
                ? 'Yes'
                : 'No'
        ) .
        "\n" .

        "Decoration Required: " .
        (
            !empty(
                $domainDetails[
                    'decoration_required'
                ]
            )
                ? 'Yes'
                : 'No'
        ) .
        "\n\n";
}


/*
|--------------------------------------------------------------------------
| DINING PLAIN TEXT
|--------------------------------------------------------------------------
*/

if (
    $domain === 'dining' &&
    $domainDetails
) {

    $plainText .=

        "DINING DETAILS\n" .

        "Package: " .
        ($domainDetails[
            'package_name'
        ] ?? '-') .
        "\n" .

        "Meal Type: " .
        ($domainDetails[
            'meal_type'
        ] ?? '-') .
        "\n" .

        "Dining Date: " .
        ($domainDetails[
            'dining_date'
        ] ?? '-') .
        "\n" .

        "Dining Time: " .
        ($domainDetails[
            'dining_time'
        ] ?? '-') .
        "\n" .

        "Guests: " .
        ($domainDetails[
            'guest_count'
        ] ?? '-') .
        "\n\n";
}


/*
|--------------------------------------------------------------------------
| CATERING PLAIN TEXT
|--------------------------------------------------------------------------
*/

if (
    $domain === 'catering' &&
    $domainDetails
) {

    $plainText .=

        "CATERING DETAILS\n" .

        "Package: " .
        ($domainDetails[
            'package_name'
        ] ?? '-') .
        "\n" .

        "Event Type: " .
        ($domainDetails[
            'event_type'
        ] ?? '-') .
        "\n" .

        "Event Date: " .
        ($domainDetails[
            'event_date'
        ] ?? '-') .
        "\n" .

        "Event Time: " .
        ($domainDetails[
            'event_time'
        ] ?? '-') .
        "\n" .

        "Guests: " .
        ($domainDetails[
            'guest_count'
        ] ?? '-') .
        "\n" .

        "Venue: " .
        ($domainDetails[
            'venue_address'
        ] ?? '-') .
        "\n" .

        "Estimated Amount: ₹" .
        (
            $domainDetails[
                'estimated_amount'
            ] !== null
                ? number_format(
                    (float)$domainDetails[
                        'estimated_amount'
                    ],
                    2
                )
                : '0.00'
        ) .
        "\n\n";
}


/*
|--------------------------------------------------------------------------
| REQUIREMENTS PLAIN TEXT
|--------------------------------------------------------------------------
*/

if (
    !empty($requirements)
) {

    $plainText .=

        "CUSTOMER REQUIREMENTS\n" .

        $requirements .

        "\n\n";
}


/*
|--------------------------------------------------------------------------
| ASSIGNED USER PLAIN TEXT
|--------------------------------------------------------------------------
*/

if (
    !empty(
        $lead['assigned_admin_name']
    )
) {

    $plainText .=

        "ASSIGNED TO\n" .

        $lead[
            'assigned_admin_name'
        ] .

        " (" .

        (
            $lead[
                'assigned_admin_role'
            ] ?? ''
        ) .

        ")\n\n";
}


$plainText .=

    "Please review this enquiry and contact the customer.\n\n" .

    "SM GROUP";


/*
|--------------------------------------------------------------------------
| SEND EMAIL
|--------------------------------------------------------------------------
*/

$sentCount =
    0;

$failedCount =
    0;

$alreadySentCount =
    0;

$recipientResults =
    [];


/*
|--------------------------------------------------------------------------
| RECIPIENT LOOP
|--------------------------------------------------------------------------
*/

foreach (
    $recipients as $recipient
) {

    $recipientEmail =
        $recipient['email'];


    /*
    |--------------------------------------------------------------------------
    | DUPLICATE CHECK
    |--------------------------------------------------------------------------
    */

    $duplicateSql = "

        SELECT id

        FROM lead_notifications

        WHERE lead_id = ?

        AND channel = 'email'

        AND recipient = ?

        AND status = 'sent'

        LIMIT 1

    ";


    $duplicateStmt =
        $conn->prepare(
            $duplicateSql
        );


    if (!$duplicateStmt) {

        $failedCount++;

        $recipientResults[] = [

            'email' =>
                $recipientEmail,

            'name' =>
                $recipient['name'],

            'status' =>
                'failed',

            'message' =>
                'Failed to check duplicate notification.'
        ];

        continue;
    }


    $duplicateStmt->bind_param(
        'is',
        $leadId,
        $recipientEmail
    );


    if (
        !$duplicateStmt->execute()
    ) {

        $duplicateStmt->close();

        $failedCount++;

        $recipientResults[] = [

            'email' =>
                $recipientEmail,

            'name' =>
                $recipient['name'],

            'status' =>
                'failed',

            'message' =>
                'Failed to check existing notification.'
        ];

        continue;
    }


    $duplicateResult =
        $duplicateStmt->get_result();


    $alreadySent =
        $duplicateResult->fetch_assoc();


    $duplicateStmt->close();


    if (
        $alreadySent
    ) {

        $alreadySentCount++;

        $recipientResults[] = [

            'email' =>
                $recipientEmail,

            'name' =>
                $recipient['name'],

            'role' =>
                $recipient['role'],

            'status' =>
                'already_sent',

            'message' =>
                'Email already sent for this lead.'
        ];

        continue;
    }


    /*
    |--------------------------------------------------------------------------
    | CREATE PENDING NOTIFICATION
    |--------------------------------------------------------------------------
    */

    $notificationSql = "

        INSERT INTO lead_notifications (

            lead_id,
            channel,
            recipient,
            message,
            status

        ) VALUES (

            ?,
            'email',
            ?,
            ?,
            'pending'

        )

    ";


    $notificationStmt =
        $conn->prepare(
            $notificationSql
        );


    if (!$notificationStmt) {

        $failedCount++;

        $recipientResults[] = [

            'email' =>
                $recipientEmail,

            'name' =>
                $recipient['name'],

            'status' =>
                'failed',

            'message' =>
                'Failed to create email notification.'
        ];

        continue;
    }


    $notificationStmt->bind_param(
        'iss',
        $leadId,
        $recipientEmail,
        $plainText
    );


    if (
        !$notificationStmt->execute()
    ) {

        $error =
            $notificationStmt->error;

        $notificationStmt->close();

        $failedCount++;

        $recipientResults[] = [

            'email' =>
                $recipientEmail,

            'name' =>
                $recipient['name'],

            'status' =>
                'failed',

            'message' =>
                'Failed to log notification.',

            'error' =>
                $error
        ];

        continue;
    }


    $pendingNotificationId =
        (int)$conn->insert_id;


    $notificationStmt->close();


    /*
    |--------------------------------------------------------------------------
    | PHPMailer
    |--------------------------------------------------------------------------
    */

    $mail =
        new \PHPMailer\PHPMailer\PHPMailer(
            true
        );


    try {

        /*
        |--------------------------------------------------------------------------
        | SMTP
        |--------------------------------------------------------------------------
        */

        $mail->isSMTP();


        $mail->Host =
            $mailHost;


        $mail->Port =
            $mailPort;


        $mail->SMTPAuth =
            true;


        $mail->Username =
            $mailUsername;


        $mail->Password =
            (string)$mailPassword;


        /*
        |--------------------------------------------------------------------------
        | ENCRYPTION
        |--------------------------------------------------------------------------
        */

        if (
            $mailEncryption === 'tls'
        ) {

            $mail->SMTPSecure =
                \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }

        elseif (
            $mailEncryption === 'ssl'
        ) {

            $mail->SMTPSecure =
                \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        }


        /*
        |--------------------------------------------------------------------------
        | EMAIL SETTINGS
        |--------------------------------------------------------------------------
        */

        $mail->CharSet =
            'UTF-8';

        $mail->Encoding =
            'base64';


        /*
        |--------------------------------------------------------------------------
        | FROM
        |--------------------------------------------------------------------------
        */

        $mail->setFrom(
            $mailFromEmail,
            $mailFromName
        );


        /*
        |--------------------------------------------------------------------------
        | TO
        |--------------------------------------------------------------------------
        */

        $mail->addAddress(
            $recipientEmail,
            $recipient['name']
        );


        /*
        |--------------------------------------------------------------------------
        | REPLY TO CUSTOMER
        |--------------------------------------------------------------------------
        */

        if (
            !empty(
                $lead['customer_email']
            ) &&
            filter_var(
                $lead['customer_email'],
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $mail->addReplyTo(
                $lead['customer_email'],
                $lead['customer_name']
            );
        }


        /*
        |--------------------------------------------------------------------------
        | SUBJECT
        |--------------------------------------------------------------------------
        */

        $mail->Subject =
            $emailSubject;


        /*
        |--------------------------------------------------------------------------
        | HTML EMAIL
        |--------------------------------------------------------------------------
        */

        $mail->isHTML(
            true
        );


        $mail->Body =
            $htmlBody;


        /*
        |--------------------------------------------------------------------------
        | PLAIN TEXT FALLBACK
        |--------------------------------------------------------------------------
        */

        $mail->AltBody =
            $plainText;


        /*
        |--------------------------------------------------------------------------
        | SMTP DEBUG
        |--------------------------------------------------------------------------
        |
        | 0 = disabled
        |--------------------------------------------------------------------------
        */

        $mail->SMTPDebug =
            0;


        /*
        |--------------------------------------------------------------------------
        | SEND
        |--------------------------------------------------------------------------
        */

        $mail->send();


        /*
        |--------------------------------------------------------------------------
        | UPDATE SENT
        |--------------------------------------------------------------------------
        */

        $updateSql = "

            UPDATE lead_notifications

            SET

                status = 'sent',

                sent_at = CURRENT_TIMESTAMP,

                error_message = NULL

            WHERE id = ?

            LIMIT 1

        ";


        $updateStmt =
            $conn->prepare(
                $updateSql
            );


        if ($updateStmt) {

            $updateStmt->bind_param(
                'i',
                $pendingNotificationId
            );

            $updateStmt->execute();

            $updateStmt->close();
        }


        $sentCount++;


        $recipientResults[] = [

            'email' =>
                $recipientEmail,

            'name' =>
                $recipient['name'],

            'role' =>
                $recipient['role'],

            'status' =>
                'sent'
        ];
    }


    catch (
        \PHPMailer\PHPMailer\Exception $e
    ) {

        $errorMessage =
            trim(
                (string)$mail->ErrorInfo
            );


        if (
            $errorMessage === ''
        ) {

            $errorMessage =
                $e->getMessage();
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE FAILED
        |--------------------------------------------------------------------------
        */

        $updateSql = "

            UPDATE lead_notifications

            SET

                status = 'failed',

                error_message = ?

            WHERE id = ?

            LIMIT 1

        ";


        $updateStmt =
            $conn->prepare(
                $updateSql
            );


        if ($updateStmt) {

            $updateStmt->bind_param(
                'si',
                $errorMessage,
                $pendingNotificationId
            );

            $updateStmt->execute();

            $updateStmt->close();
        }


        $failedCount++;


        $recipientResults[] = [

            'email' =>
                $recipientEmail,

            'name' =>
                $recipient['name'],

            'role' =>
                $recipient['role'],

            'status' =>
                'failed',

            'message' =>
                'SMTP email sending failed.',

            'error' =>
                $errorMessage
        ];
    }


    catch (
        \Throwable $e
    ) {

        $errorMessage =
            $e->getMessage();


        /*
        |--------------------------------------------------------------------------
        | UPDATE FAILED
        |--------------------------------------------------------------------------
        */

        $updateSql = "

            UPDATE lead_notifications

            SET

                status = 'failed',

                error_message = ?

            WHERE id = ?

            LIMIT 1

        ";


        $updateStmt =
            $conn->prepare(
                $updateSql
            );


        if ($updateStmt) {

            $updateStmt->bind_param(
                'si',
                $errorMessage,
                $pendingNotificationId
            );

            $updateStmt->execute();

            $updateStmt->close();
        }


        $failedCount++;


        $recipientResults[] = [

            'email' =>
                $recipientEmail,

            'name' =>
                $recipient['name'],

            'role' =>
                $recipient['role'],

            'status' =>
                'failed',

            'message' =>
                'Unexpected email error.',

            'error' =>
                $errorMessage
        ];
    }
}


/*
|--------------------------------------------------------------------------
| FINAL RESPONSE MESSAGE
|--------------------------------------------------------------------------
*/

if (
    $sentCount > 0 &&
    $failedCount === 0
) {

    $responseMessage =
        'Email notification sent successfully.';
}

elseif (
    $sentCount > 0 &&
    $failedCount > 0
) {

    $responseMessage =
        'Email notification partially sent.';
}

elseif (
    $alreadySentCount > 0 &&
    $failedCount === 0
) {

    $responseMessage =
        'Email notification was already sent to the available recipients.';
}

else {

    $responseMessage =
        'Email notification could not be sent.';
}


/*
|--------------------------------------------------------------------------
| HTTP STATUS
|--------------------------------------------------------------------------
*/

$httpStatus =
    $sentCount > 0
        ? 200
        : (
            $alreadySentCount > 0
                ? 200
                : 500
        );


/*
|--------------------------------------------------------------------------
| FINAL RESPONSE
|--------------------------------------------------------------------------
*/

sendJsonResponse(

    $httpStatus,

    $sentCount > 0 ||
    $alreadySentCount > 0,

    $responseMessage,

    [

        'lead_id' =>
            $leadId,

        'lead_reference' =>
            $lead['lead_reference'],

        'domain' =>
            $domain,

        'notification_type' =>
            $notificationType,

        'total_recipients' =>
            count($recipients),

        'sent_count' =>
            $sentCount,

        'already_sent_count' =>
            $alreadySentCount,

        'failed_count' =>
            $failedCount,

        'recipients' =>
            array_values(
                $recipientResults
            )
    ]
);