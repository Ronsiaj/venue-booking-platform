<?php

require_once __DIR__ . '/../config/database.php';

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}


/*
|--------------------------------------------------------------------------
| METHOD VALIDATION
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        "status" => false,
        "message" => "Method not allowed. Use POST."
    ]);

    exit;
}


try {

    /*
    |--------------------------------------------------------------------------
    | DATABASE
    |--------------------------------------------------------------------------
    */

    global $conn;

    if (
        !isset($conn) ||
        !($conn instanceof mysqli)
    ) {

        throw new Exception(
            "Database connection is not available."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | READ JSON BODY
    |--------------------------------------------------------------------------
    */

    $rawInput =
        file_get_contents("php://input");


    if (
        $rawInput === false ||
        trim($rawInput) === ''
    ) {

        http_response_code(400);

        echo json_encode([
            "status" => false,
            "message" => "Request body is required."
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | DECODE JSON
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

        http_response_code(400);

        echo json_encode([
            "status" => false,
            "message" => "Invalid JSON request body."
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | ALLOWED FIELDS
    |--------------------------------------------------------------------------
    */

    $allowedFields = [
        "lead_id",
        "notification_type"
    ];


    foreach (
        $data
        as $key => $value
    ) {

        if (
            !in_array(
                $key,
                $allowedFields,
                true
            )
        ) {

            http_response_code(400);

            echo json_encode([
                "status" => false,
                "message" =>
                    "Unknown field: " . $key
            ]);

            exit;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | LEAD ID VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        !array_key_exists(
            "lead_id",
            $data
        )
    ) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "lead_id is required."
        ]);

        exit;
    }


    $leadIdValue =
        trim(
            (string)$data['lead_id']
        );


    if (
        !preg_match(
            '/^[1-9][0-9]*$/',
            $leadIdValue
        )
    ) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" =>
                "lead_id must be a positive integer."
        ]);

        exit;
    }


    $leadId =
        filter_var(
            $leadIdValue,
            FILTER_VALIDATE_INT,
            [
                "options" => [
                    "min_range" => 1
                ]
            ]
        );


    if ($leadId === false) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "Invalid lead_id."
        ]);

        exit;
    }


    $leadId =
        (int)$leadId;


    /*
    |--------------------------------------------------------------------------
    | NOTIFICATION TYPE
    |--------------------------------------------------------------------------
    |
    | admin:
    |     All active admins
    |
    | assigned:
    |     Assigned staff/admin only
    |
    | all:
    |     All active admins + assigned user
    |
    */

    $notificationType =
        isset(
            $data['notification_type']
        )
            ? strtolower(
                trim(
                    (string)
                    $data['notification_type']
                )
            )
            : 'all';


    if (
        !in_array(
            $notificationType,
            [
                'admin',
                'assigned',
                'all'
            ],
            true
        )
    ) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" =>
                "notification_type must be admin, assigned, or all."
        ]);

        exit;
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

            l.assigned_admin_id,

            l.source,

            l.created_at,
            l.updated_at

        FROM leads l

        WHERE l.id = ?

        LIMIT 1

    ";


    $leadStmt =
        $conn->prepare(
            $leadSql
        );


    if (!$leadStmt) {

        throw new Exception(
            "Failed to prepare lead query: " .
            $conn->error
        );
    }


    $leadStmt->bind_param(
        "i",
        $leadId
    );


    if (
        !$leadStmt->execute()
    ) {

        $error =
            $leadStmt->error;

        $leadStmt->close();

        throw new Exception(
            "Failed to fetch lead: " .
            $error
        );
    }


    $leadResult =
        $leadStmt->get_result();


    $lead =
        $leadResult->fetch_assoc();


    $leadStmt->close();


    /*
    |--------------------------------------------------------------------------
    | LEAD NOT FOUND
    |--------------------------------------------------------------------------
    */

    if (!$lead) {

        http_response_code(404);

        echo json_encode([
            "status" => false,
            "message" => "Lead not found.",
            "lead_id" => $leadId
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | DOMAIN VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        !in_array(
            $lead['domain'],
            [
                'hall',
                'dining',
                'catering'
            ],
            true
        )
    ) {

        throw new Exception(
            "Invalid lead domain."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | FETCH ASSIGNED USER
    |--------------------------------------------------------------------------
    */

    $assignedUser = null;


    if (
        $lead['assigned_admin_id'] !== null
    ) {

        $assignedUserSql = "

            SELECT

                id,
                name,
                email,
                phone,
                role,
                status

            FROM admin_users

            WHERE id = ?

            LIMIT 1

        ";


        $assignedUserStmt =
            $conn->prepare(
                $assignedUserSql
            );


        if (!$assignedUserStmt) {

            throw new Exception(
                "Failed to prepare assigned user query: " .
                $conn->error
            );
        }


        $assignedUserId =
            (int)$lead['assigned_admin_id'];


        $assignedUserStmt->bind_param(
            "i",
            $assignedUserId
        );


        if (
            !$assignedUserStmt->execute()
        ) {

            $error =
                $assignedUserStmt->error;

            $assignedUserStmt->close();

            throw new Exception(
                "Failed to fetch assigned user: " .
                $error
            );
        }


        $assignedUserResult =
            $assignedUserStmt->get_result();


        $assignedUser =
            $assignedUserResult->fetch_assoc();


        $assignedUserStmt->close();


        /*
        |--------------------------------------------------------------------------
        | ASSIGNED USER VALIDATION
        |--------------------------------------------------------------------------
        */

        if (!$assignedUser) {

            throw new Exception(
                "Assigned user not found."
            );
        }


        if (
            $assignedUser['status'] !== 'active'
        ) {

            throw new Exception(
                "Assigned user is inactive."
            );
        }


        if (
            !in_array(
                $assignedUser['role'],
                [
                    'admin',
                    'staff'
                ],
                true
            )
        ) {

            throw new Exception(
                "Invalid assigned user role."
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | FETCH ACTIVE ADMINS
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | Admin WhatsApp numbers are taken ONLY
    | from admin_users.phone.
    |
    */

    $admins = [];


    if (
        $notificationType === 'admin' ||
        $notificationType === 'all'
    ) {

        $adminSql = "

            SELECT

                id,
                name,
                email,
                phone,
                role,
                status

            FROM admin_users

            WHERE

                role = 'admin'

                AND status = 'active'

                AND phone IS NOT NULL

                AND TRIM(phone) <> ''

            ORDER BY id ASC

        ";


        $adminResult =
            $conn->query(
                $adminSql
            );


        if (
            $adminResult === false
        ) {

            throw new Exception(
                "Failed to fetch admin users: " .
                $conn->error
            );
        }


        while (
            $admin =
            $adminResult->fetch_assoc()
        ) {

            $admins[] =
                $admin;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | NORMALIZE PHONE NUMBER
    |--------------------------------------------------------------------------
    |
    | Indian 10 digit number:
    |
    | 9360552619
    |
    | becomes:
    |
    | 919360552619
    |
    | for wa.me
    |
    */

    function normalizeWhatsAppNumber(
        string $phone
    ): ?string {

        $phone =
            trim($phone);


        /*
        | Remove:
        | + - spaces ( )
        */

        $phone =
            preg_replace(
                '/[\s\-\+\(\)]/',
                '',
                $phone
            );


        if ($phone === '') {

            return null;
        }


        /*
        | Only digits
        */

        if (
            !preg_match(
                '/^\d+$/',
                $phone
            )
        ) {

            return null;
        }


        /*
        | Indian 10 digit number
        */

        if (
            strlen($phone) === 10
        ) {

            return '91' . $phone;
        }


        /*
        | Already country code
        */

        if (
            strlen($phone) >= 11 &&
            strlen($phone) <= 15
        ) {

            return $phone;
        }


        return null;
    }


    /*
    |--------------------------------------------------------------------------
    | BUILD RECIPIENT LIST
    |--------------------------------------------------------------------------
    */

    $recipients = [];


    /*
    |--------------------------------------------------------------------------
    | ADMIN RECIPIENTS
    |--------------------------------------------------------------------------
    */

    foreach (
        $admins
        as $admin
    ) {

        $originalPhone =
            trim(
                (string)
                $admin['phone']
            );


        $whatsappPhone =
            normalizeWhatsAppNumber(
                $originalPhone
            );


        if (
            $whatsappPhone === null
        ) {

            continue;
        }


        $recipients[] = [

            "type" =>
                "admin",

            "user_id" =>
                (int)$admin['id'],

            "name" =>
                $admin['name'],

            "phone" =>
                $originalPhone,

            "whatsapp_phone" =>
                $whatsappPhone
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | ASSIGNED USER
    |--------------------------------------------------------------------------
    */

    if (
        (
            $notificationType === 'assigned' ||
            $notificationType === 'all'
        )
        &&
        $assignedUser !== null
    ) {

        $originalPhone =
            trim(
                (string)
                $assignedUser['phone']
            );


        $whatsappPhone =
            normalizeWhatsAppNumber(
                $originalPhone
            );


        if (
            $whatsappPhone !== null
        ) {

            $recipients[] = [

                "type" =>
                    "assigned_" .
                    strtolower(
                        $assignedUser['role']
                    ),

                "user_id" =>
                    (int)
                    $assignedUser['id'],

                "name" =>
                    $assignedUser['name'],

                "phone" =>
                    $originalPhone,

                "whatsapp_phone" =>
                    $whatsappPhone
            ];
        }
    }


    /*
    |--------------------------------------------------------------------------
    | REMOVE DUPLICATE NUMBERS
    |--------------------------------------------------------------------------
    */

    $uniqueRecipients = [];


    foreach (
        $recipients
        as $recipient
    ) {

        $phone =
            $recipient['whatsapp_phone'];


        if (
            !isset(
                $uniqueRecipients[$phone]
            )
        ) {

            $uniqueRecipients[$phone] =
                $recipient;

        } else {

            $existing =
                $uniqueRecipients[$phone];


            if (
                $existing['type'] !==
                $recipient['type']
            ) {

                $uniqueRecipients[$phone]['type'] =
                    "admin_and_assigned";
            }
        }
    }


    $recipients =
        array_values(
            $uniqueRecipients
        );


    /*
    |--------------------------------------------------------------------------
    | NO VALID RECIPIENT
    |--------------------------------------------------------------------------
    */

    if (
        count($recipients) === 0
    ) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" =>
                "No valid WhatsApp recipient found.",
            "data" => [
                "lead_id" =>
                    $leadId,

                "notification_type" =>
                    $notificationType,

                "active_admin_count" =>
                    count($admins),

                "assigned_user" =>
                    $assignedUser
            ]
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | FETCH DOMAIN DETAILS
    |--------------------------------------------------------------------------
    */

    $domainDetails = null;


    /*
    |--------------------------------------------------------------------------
    | HALL DETAILS
    |--------------------------------------------------------------------------
    */

    if (
        $lead['domain'] === 'hall'
    ) {

        $hallSql = "

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


        $hallStmt =
            $conn->prepare(
                $hallSql
            );


        if (!$hallStmt) {

            throw new Exception(
                "Failed to prepare hall details query: " .
                $conn->error
            );
        }


        $hallStmt->bind_param(
            "i",
            $leadId
        );


        if (
            !$hallStmt->execute()
        ) {

            $error =
                $hallStmt->error;

            $hallStmt->close();

            throw new Exception(
                "Failed to fetch hall details: " .
                $error
            );
        }


        $hallResult =
            $hallStmt->get_result();


        $domainDetails =
            $hallResult->fetch_assoc();


        $hallStmt->close();
    }


    /*
    |--------------------------------------------------------------------------
    | DINING DETAILS
    |--------------------------------------------------------------------------
    */

    if (
        $lead['domain'] === 'dining'
    ) {

        $diningSql = "

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


        $diningStmt =
            $conn->prepare(
                $diningSql
            );


        if (!$diningStmt) {

            throw new Exception(
                "Failed to prepare dining details query: " .
                $conn->error
            );
        }


        $diningStmt->bind_param(
            "i",
            $leadId
        );


        if (
            !$diningStmt->execute()
        ) {

            $error =
                $diningStmt->error;

            $diningStmt->close();

            throw new Exception(
                "Failed to fetch dining details: " .
                $error
            );
        }


        $diningResult =
            $diningStmt->get_result();


        $domainDetails =
            $diningResult->fetch_assoc();


        $diningStmt->close();
    }


    /*
    |--------------------------------------------------------------------------
    | CATERING DETAILS
    |--------------------------------------------------------------------------
    */

    if (
        $lead['domain'] === 'catering'
    ) {

        $cateringSql = "

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


        $cateringStmt =
            $conn->prepare(
                $cateringSql
            );


        if (!$cateringStmt) {

            throw new Exception(
                "Failed to prepare catering details query: " .
                $conn->error
            );
        }


        $cateringStmt->bind_param(
            "i",
            $leadId
        );


        if (
            !$cateringStmt->execute()
        ) {

            $error =
                $cateringStmt->error;

            $cateringStmt->close();

            throw new Exception(
                "Failed to fetch catering details: " .
                $error
            );
        }


        $cateringResult =
            $cateringStmt->get_result();


        $domainDetails =
            $cateringResult->fetch_assoc();


        $cateringStmt->close();
    }


    /*
    |--------------------------------------------------------------------------
    | BUILD MESSAGE
    |--------------------------------------------------------------------------
    */

    $message = "";


    $message .=
        "*SM GROUP - New Lead*\n\n";


    $message .=
        "Lead ID: " .
        $lead['id'] .
        "\n";


    $message .=
        "Reference: " .
        $lead['lead_reference'] .
        "\n";


    $message .=
        "Domain: " .
        strtoupper(
            $lead['domain']
        ) .
        "\n";


    $message .=
        "Status: " .
        strtoupper(
            $lead['status']
        ) .
        "\n\n";


    /*
    |--------------------------------------------------------------------------
    | CUSTOMER
    |--------------------------------------------------------------------------
    */

    $message .=
        "*Customer Details*\n";


    $message .=
        "Name: " .
        $lead['customer_name'] .
        "\n";


    $message .=
        "Phone: " .
        $lead['customer_phone'] .
        "\n";


    if (
        $lead['customer_email'] !== null &&
        trim(
            (string)
            $lead['customer_email']
        ) !== ''
    ) {

        $message .=
            "Email: " .
            $lead['customer_email'] .
            "\n";
    }


    $message .= "\n";


    /*
    |--------------------------------------------------------------------------
    | HALL MESSAGE
    |--------------------------------------------------------------------------
    */

    if (
        $lead['domain'] === 'hall' &&
        $domainDetails !== null
    ) {

        $message .=
            "*Hall Details*\n";


        if (
            $domainDetails['hall_name'] !== null
        ) {

            $message .=
                "Hall: " .
                $domainDetails['hall_name'] .
                "\n";
        }


        if (
            $domainDetails['hall_type_name'] !== null
        ) {

            $message .=
                "Hall Type: " .
                $domainDetails['hall_type_name'] .
                "\n";
        }


        if (
            $domainDetails['event_type'] !== null
        ) {

            $message .=
                "Event Type: " .
                $domainDetails['event_type'] .
                "\n";
        }


        if (
            $domainDetails['event_date'] !== null
        ) {

            $message .=
                "Event Date: " .
                $domainDetails['event_date'] .
                "\n";
        }


        if (
            $domainDetails['event_time'] !== null
        ) {

            $message .=
                "Event Time: " .
                $domainDetails['event_time'] .
                "\n";
        }


        if (
            $domainDetails['guest_count'] !== null
        ) {

            $message .=
                "Guests: " .
                $domainDetails['guest_count'] .
                "\n";
        }


        $message .=
            "Food Required: " .
            (
                (int)
                $domainDetails['food_required'] === 1
                    ? "Yes"
                    : "No"
            ) .
            "\n";


        $message .=
            "Decoration Required: " .
            (
                (int)
                $domainDetails['decoration_required'] === 1
                    ? "Yes"
                    : "No"
            ) .
            "\n";


        if (
            $domainDetails['other_requirements'] !== null &&
            trim(
                (string)
                $domainDetails['other_requirements']
            ) !== ''
        ) {

            $message .=
                "Requirements: " .
                $domainDetails['other_requirements'] .
                "\n";
        }


        $message .= "\n";
    }


    /*
    |--------------------------------------------------------------------------
    | DINING MESSAGE
    |--------------------------------------------------------------------------
    */

    if (
        $lead['domain'] === 'dining' &&
        $domainDetails !== null
    ) {

        $message .=
            "*Dining Details*\n";


        if (
            $domainDetails['package_name'] !== null
        ) {

            $message .=
                "Package: " .
                $domainDetails['package_name'] .
                "\n";
        }


        if (
            $domainDetails['meal_type'] !== null
        ) {

            $message .=
                "Meal Type: " .
                $domainDetails['meal_type'] .
                "\n";
        }


        if (
            $domainDetails['dining_date'] !== null
        ) {

            $message .=
                "Date: " .
                $domainDetails['dining_date'] .
                "\n";
        }


        if (
            $domainDetails['dining_time'] !== null
        ) {

            $message .=
                "Time: " .
                $domainDetails['dining_time'] .
                "\n";
        }


        if (
            $domainDetails['guest_count'] !== null
        ) {

            $message .=
                "Guests: " .
                $domainDetails['guest_count'] .
                "\n";
        }


        if (
            $domainDetails['other_requirements'] !== null &&
            trim(
                (string)
                $domainDetails['other_requirements']
            ) !== ''
        ) {

            $message .=
                "Requirements: " .
                $domainDetails['other_requirements'] .
                "\n";
        }


        $message .= "\n";
    }


    /*
    |--------------------------------------------------------------------------
    | CATERING MESSAGE
    |--------------------------------------------------------------------------
    */

    if (
        $lead['domain'] === 'catering' &&
        $domainDetails !== null
    ) {

        $message .=
            "*Catering Details*\n";


        if (
            $domainDetails['package_name'] !== null
        ) {

            $message .=
                "Package: " .
                $domainDetails['package_name'] .
                "\n";
        }


        if (
            $domainDetails['event_type'] !== null
        ) {

            $message .=
                "Event Type: " .
                $domainDetails['event_type'] .
                "\n";
        }


        if (
            $domainDetails['event_date'] !== null
        ) {

            $message .=
                "Event Date: " .
                $domainDetails['event_date'] .
                "\n";
        }


        if (
            $domainDetails['event_time'] !== null
        ) {

            $message .=
                "Event Time: " .
                $domainDetails['event_time'] .
                "\n";
        }


        if (
            $domainDetails['guest_count'] !== null
        ) {

            $message .=
                "Guests: " .
                $domainDetails['guest_count'] .
                "\n";
        }


        if (
            $domainDetails['venue_address'] !== null &&
            trim(
                (string)
                $domainDetails['venue_address']
            ) !== ''
        ) {

            $message .=
                "Venue: " .
                $domainDetails['venue_address'] .
                "\n";
        }


        if (
            $domainDetails['estimated_amount'] !== null
        ) {

            $message .=
                "Estimated Amount: ₹" .
                number_format(
                    (float)
                    $domainDetails['estimated_amount'],
                    2
                ) .
                "\n";
        }


        if (
            $domainDetails['other_requirements'] !== null &&
            trim(
                (string)
                $domainDetails['other_requirements']
            ) !== ''
        ) {

            $message .=
                "Requirements: " .
                $domainDetails['other_requirements'] .
                "\n";
        }


        $message .= "\n";
    }


    /*
    |--------------------------------------------------------------------------
    | ASSIGNMENT MESSAGE
    |--------------------------------------------------------------------------
    */

    if (
        $assignedUser !== null
    ) {

        $message .=
            "*Assigned To:* " .
            $assignedUser['name'] .
            "\n";


        $message .=
            "Role: " .
            strtoupper(
                $assignedUser['role']
            ) .
            "\n";

    } else {

        $message .=
            "*Assigned To:* Not Assigned\n";
    }


    $message .= "\n";


    $message .=
        "Please follow up with the customer.";


    /*
    |--------------------------------------------------------------------------
    | CREATE WHATSAPP URL
    |--------------------------------------------------------------------------
    */

    $encodedMessage =
        rawurlencode(
            $message
        );


    /*
    |--------------------------------------------------------------------------
    | GENERATE LINKS
    |--------------------------------------------------------------------------
    */

    $results = [];


    foreach (
        $recipients
        as $recipient
    ) {

        $whatsappUrl =
            "https://wa.me/" .
            $recipient['whatsapp_phone'] .
            "?text=" .
            $encodedMessage;


        $results[] = [

            "type" =>
                $recipient['type'],

            "user_id" =>
                $recipient['user_id'],

            "name" =>
                $recipient['name'],

            "phone" =>
                $recipient['phone'],

            "whatsapp_phone" =>
                $recipient['whatsapp_phone'],

            "whatsapp_url" =>
                $whatsappUrl,

            "message" =>
                $message
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | SAVE NOTIFICATION RECORD
    |--------------------------------------------------------------------------
    |
    | Since there is no WhatsApp API, PHP cannot confirm
    | that the message was actually sent.
    |
    | Therefore status = pending.
    |
    */

    $notificationIds = [];


    foreach (
        $results
        as $result
    ) {

        $recipientPhone =
            $result['whatsapp_phone'];


        /*
        |--------------------------------------------------------------------------
        | CHECK EXISTING PENDING RECORD
        |--------------------------------------------------------------------------
        */

        $checkSql = "

            SELECT id

            FROM lead_notifications

            WHERE

                lead_id = ?

                AND channel = 'whatsapp'

                AND recipient = ?

                AND status = 'pending'

            ORDER BY id DESC

            LIMIT 1

        ";


        $checkStmt =
            $conn->prepare(
                $checkSql
            );


        if (!$checkStmt) {

            throw new Exception(
                "Failed to prepare notification check: " .
                $conn->error
            );
        }


        $checkStmt->bind_param(
            "is",
            $leadId,
            $recipientPhone
        );


        if (
            !$checkStmt->execute()
        ) {

            $error =
                $checkStmt->error;

            $checkStmt->close();

            throw new Exception(
                "Failed to check existing notification: " .
                $error
            );
        }


        $checkResult =
            $checkStmt->get_result();


        $existing =
            $checkResult->fetch_assoc();


        $checkStmt->close();


        /*
        |--------------------------------------------------------------------------
        | EXISTING
        |--------------------------------------------------------------------------
        */

        if ($existing) {

            $notificationIds[] = [

                "id" =>
                    (int)$existing['id'],

                "recipient" =>
                    $recipientPhone,

                "status" =>
                    "pending"
            ];

            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | INSERT PENDING NOTIFICATION
        |--------------------------------------------------------------------------
        */

        $insertSql = "

            INSERT INTO lead_notifications (

                lead_id,
                channel,
                recipient,
                message,
                status,
                provider_message_id,
                error_message,
                sent_at

            ) VALUES (

                ?,
                'whatsapp',
                ?,
                ?,
                'pending',
                NULL,
                NULL,
                NULL

            )

        ";


        $insertStmt =
            $conn->prepare(
                $insertSql
            );


        if (!$insertStmt) {

            throw new Exception(
                "Failed to prepare notification insert: " .
                $conn->error
            );
        }


        $insertStmt->bind_param(
            "iss",
            $leadId,
            $recipientPhone,
            $message
        );


        if (
            !$insertStmt->execute()
        ) {

            $error =
                $insertStmt->error;

            $insertStmt->close();

            throw new Exception(
                "Failed to create notification record: " .
                $error
            );
        }


        $notificationId =
            (int)$conn->insert_id;


        $insertStmt->close();


        $notificationIds[] = [

            "id" =>
                $notificationId,

            "recipient" =>
                $recipientPhone,

            "status" =>
                "pending"
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | FINAL RESPONSE
    |--------------------------------------------------------------------------
    */

    http_response_code(200);


    echo json_encode([

        "status" => true,

        "message" =>
            "WhatsApp links generated successfully.",

        "note" =>
            "No WhatsApp API is configured. Open the whatsapp_url and press Send in WhatsApp.",

        "data" => [

            "lead_id" =>
                $leadId,

            "lead_reference" =>
                $lead['lead_reference'],

            "domain" =>
                $lead['domain'],

            "notification_type" =>
                $notificationType,

            "assigned_user" =>
                $assignedUser !== null
                    ? [

                        "id" =>
                            (int)
                            $assignedUser['id'],

                        "name" =>
                            $assignedUser['name'],

                        "phone" =>
                            $assignedUser['phone'],

                        "role" =>
                            $assignedUser['role']

                    ]
                    : null,

            "recipient_count" =>
                count($results),

            "notifications" =>
                $notificationIds,

            "recipients" =>
                $results
        ]

    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;


} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([

        "status" => false,

        "message" =>
            "Failed to generate WhatsApp notification.",

        "error" =>
            $e->getMessage()

    ], JSON_UNESCAPED_UNICODE);

    exit;
}