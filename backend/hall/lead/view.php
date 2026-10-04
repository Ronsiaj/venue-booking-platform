<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/middleware.php';

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {

    http_response_code(405);

    echo json_encode([
        "status" => false,
        "message" => "Method not allowed. Use GET."
    ]);

    exit;
}

try {

    /*
    |--------------------------------------------------------------------------
    | ADMIN / STAFF AUTHENTICATION
    |--------------------------------------------------------------------------
    |
    | Admin:
    |   Can view any hall lead.
    |
    | Staff:
    |   Can view only leads assigned to them.
    |
    */

    $currentUser = requireAdminOrStaff();

    if (!is_array($currentUser)) {

        throw new Exception(
            "Invalid authenticated user data."
        );
    }

    $currentUserRole = strtolower(
        trim(
            (string)($currentUser['role'] ?? '')
        )
    );

    /*
    |--------------------------------------------------------------------------
    | CURRENT USER ID
    |--------------------------------------------------------------------------
    */

    $currentUserId = null;

    if (
        isset($currentUser['id']) &&
        filter_var(
            $currentUser['id'],
            FILTER_VALIDATE_INT
        ) !== false
    ) {

        $currentUserId =
            (int)$currentUser['id'];

    } elseif (
        isset($currentUser['sub']) &&
        filter_var(
            $currentUser['sub'],
            FILTER_VALIDATE_INT
        ) !== false
    ) {

        $currentUserId =
            (int)$currentUser['sub'];
    }

    /*
    |--------------------------------------------------------------------------
    | ROLE VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        !in_array(
            $currentUserRole,
            ['admin', 'staff'],
            true
        )
    ) {

        http_response_code(403);

        echo json_encode([
            "status" => false,
            "message" =>
                "Only admin or staff can view hall leads."
        ]);

        exit;
    }

    if (
        $currentUserId === null ||
        $currentUserId <= 0
    ) {

        http_response_code(401);

        echo json_encode([
            "status" => false,
            "message" =>
                "Authenticated user ID is invalid."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | DATABASE CONNECTION
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
    | VALIDATE QUERY PARAMETERS
    |--------------------------------------------------------------------------
    |
    | Allowed:
    |
    | ?lead_id=1
    |
    */

    $allowedParams = [
        'lead_id'
    ];

    foreach ($_GET as $key => $value) {

        if (
            !in_array(
                $key,
                $allowedParams,
                true
            )
        ) {

            http_response_code(400);

            echo json_encode([
                "status" => false,
                "message" =>
                    "Unknown query parameter: {$key}"
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | LEAD ID REQUIRED
    |--------------------------------------------------------------------------
    */

    if (
        !isset($_GET['lead_id']) ||
        trim((string)$_GET['lead_id']) === ''
    ) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" =>
                "lead_id is required."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | LEAD ID VALIDATION
    |--------------------------------------------------------------------------
    */

    $leadIdValue =
        trim(
            (string)$_GET['lead_id']
        );

    /*
    | Only positive integer.
    |
    | Reject:
    | 1.5
    | abc
    | -1
    | 0
    | 1abc
    */

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
                "lead_id must be a valid positive integer."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | INTEGER RANGE VALIDATION
    |--------------------------------------------------------------------------
    */

    $leadId = filter_var(
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
            "message" =>
                "lead_id is out of valid range."
        ]);

        exit;
    }

    $leadId = (int)$leadId;

    /*
    |--------------------------------------------------------------------------
    | FETCH COMMON LEAD
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

            /* Assigned user */
            au.name AS assigned_admin_name,
            au.email AS assigned_admin_email,
            au.phone AS assigned_admin_phone,
            au.role AS assigned_admin_role,
            au.status AS assigned_admin_status

        FROM leads l

        LEFT JOIN admin_users au
            ON au.id = l.assigned_admin_id

        WHERE
            l.id = ?
            AND l.domain = 'hall'
    ";

    /*
    |--------------------------------------------------------------------------
    | STAFF ACCESS RESTRICTION
    |--------------------------------------------------------------------------
    |
    | Staff can only view leads assigned to them.
    |
    */

    if ($currentUserRole === 'staff') {

        $leadSql .= "
            AND l.assigned_admin_id = ?
        ";
    }

    $leadSql .= "
        LIMIT 1
    ";

    $leadStmt =
        $conn->prepare($leadSql);

    if (!$leadStmt) {

        throw new Exception(
            "Failed to prepare hall lead query: " .
            $conn->error
        );
    }

    /*
    |--------------------------------------------------------------------------
    | BIND PARAMETERS
    |--------------------------------------------------------------------------
    */

    if ($currentUserRole === 'staff') {

        $leadStmt->bind_param(
            "ii",
            $leadId,
            $currentUserId
        );

    } else {

        $leadStmt->bind_param(
            "i",
            $leadId
        );
    }

    /*
    |--------------------------------------------------------------------------
    | EXECUTE
    |--------------------------------------------------------------------------
    */

    if (!$leadStmt->execute()) {

        $error =
            $leadStmt->error;

        $leadStmt->close();

        throw new Exception(
            "Failed to fetch hall lead: " .
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
            "message" =>
                $currentUserRole === 'staff'
                    ? "Hall lead not found or not assigned to you."
                    : "Hall lead not found.",
            "lead_id" =>
                $leadId
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | DOMAIN VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($lead['domain'] !== 'hall') {

        throw new Exception(
            "Invalid domain value stored for this lead."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS VALIDATION
    |--------------------------------------------------------------------------
    */

    $allowedStatuses = [
        'new',
        'contacted',
        'follow_up',
        'converted',
        'lost',
        'cancelled'
    ];

    if (
        !in_array(
            $lead['status'],
            $allowedStatuses,
            true
        )
    ) {

        throw new Exception(
            "Invalid lead status stored in database."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SOURCE VALIDATION
    |--------------------------------------------------------------------------
    */

    $allowedSources = [
        'website',
        'whatsapp',
        'phone',
        'admin',
        'other'
    ];

    if (
        !in_array(
            $lead['source'],
            $allowedSources,
            true
        )
    ) {

        throw new Exception(
            "Invalid lead source stored in database."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ASSIGNED ADMIN VALIDATION
    |--------------------------------------------------------------------------
    */

    $assignedAdmin = null;

    if (
        $lead['assigned_admin_id'] !== null
    ) {

        $assignedAdmin = [

            "id" =>
                (int)$lead['assigned_admin_id'],

            "name" =>
                $lead['assigned_admin_name'],

            "email" =>
                $lead['assigned_admin_email'],

            "phone" =>
                $lead['assigned_admin_phone'],

            "role" =>
                $lead['assigned_admin_role'],

            "status" =>
                $lead['assigned_admin_status']
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | FETCH HALL LEAD DETAILS
    |--------------------------------------------------------------------------
    */

    $hallLeadSql = "

        SELECT

            hld.lead_id,
            hld.hall_id,

            hld.event_type,
            hld.event_date,
            hld.event_time,

            hld.guest_count,

            hld.food_required,
            hld.decoration_required,

            hld.other_requirements

        FROM hall_lead_details hld

        WHERE hld.lead_id = ?

        LIMIT 1
    ";

    $hallLeadStmt =
        $conn->prepare($hallLeadSql);

    if (!$hallLeadStmt) {

        throw new Exception(
            "Failed to prepare hall lead details query: " .
            $conn->error
        );
    }

    $hallLeadStmt->bind_param(
        "i",
        $leadId
    );

    if (!$hallLeadStmt->execute()) {

        $error =
            $hallLeadStmt->error;

        $hallLeadStmt->close();

        throw new Exception(
            "Failed to fetch hall lead details: " .
            $error
        );
    }

    $hallLeadResult =
        $hallLeadStmt->get_result();

    $hallLead =
        $hallLeadResult->fetch_assoc();

    $hallLeadStmt->close();

    /*
    |--------------------------------------------------------------------------
    | HALL DETAILS MUST EXIST
    |--------------------------------------------------------------------------
    */

    if (!$hallLead) {

        http_response_code(404);

        echo json_encode([
            "status" => false,
            "message" =>
                "Hall lead details not found.",
            "lead_id" =>
                $leadId
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATE HALL ID
    |--------------------------------------------------------------------------
    */

    $hallId = null;

    if (
        $hallLead['hall_id'] !== null
    ) {

        $hallId = (int)$hallLead['hall_id'];

        if ($hallId <= 0) {

            throw new Exception(
                "Invalid hall_id stored for this lead."
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FETCH HALL
    |--------------------------------------------------------------------------
    */

    $hall = null;

    if ($hallId !== null) {

        $hallSql = "

            SELECT

                h.id,
                h.hall_type_id,

                h.name,
                h.slug,
                h.description,

                h.capacity,
                h.area,
                h.price_description,

                h.status,

                h.created_at,
                h.updated_at,

                ht.name AS hall_type_name,
                ht.slug AS hall_type_slug,
                ht.description AS hall_type_description,
                ht.status AS hall_type_status

            FROM halls h

            LEFT JOIN hall_types ht
                ON ht.id = h.hall_type_id

            WHERE h.id = ?

            LIMIT 1
        ";

        $hallStmt =
            $conn->prepare($hallSql);

        if (!$hallStmt) {

            throw new Exception(
                "Failed to prepare hall query: " .
                $conn->error
            );
        }

        $hallStmt->bind_param(
            "i",
            $hallId
        );

        if (!$hallStmt->execute()) {

            $error =
                $hallStmt->error;

            $hallStmt->close();

            throw new Exception(
                "Failed to fetch hall: " .
                $error
            );
        }

        $hallResult =
            $hallStmt->get_result();

        $hall =
            $hallResult->fetch_assoc();

        $hallStmt->close();

        /*
        |--------------------------------------------------------------------------
        | HALL DELETED / NOT FOUND
        |--------------------------------------------------------------------------
        |
        | hall_lead_details.hall_id uses ON DELETE SET NULL.
        | Therefore old lead can exist even if hall was deleted.
        |
        */

        if (!$hall) {

            $hall = null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FETCH HALL IMAGES
    |--------------------------------------------------------------------------
    */

    $images = [];

    if ($hallId !== null && $hall !== null) {

        $imageSql = "

            SELECT

                id,
                image_path,
                alt_text,
                is_primary,
                sort_order,
                created_at

            FROM hall_images

            WHERE hall_id = ?

            ORDER BY
                is_primary DESC,
                sort_order ASC,
                id ASC
        ";

        $imageStmt =
            $conn->prepare($imageSql);

        if (!$imageStmt) {

            throw new Exception(
                "Failed to prepare hall images query: " .
                $conn->error
            );
        }

        $imageStmt->bind_param(
            "i",
            $hallId
        );

        if (!$imageStmt->execute()) {

            $error =
                $imageStmt->error;

            $imageStmt->close();

            throw new Exception(
                "Failed to fetch hall images: " .
                $error
            );
        }

        $imageResult =
            $imageStmt->get_result();

        while (
            $image =
            $imageResult->fetch_assoc()
        ) {

            $images[] = [

                "id" =>
                    (int)$image['id'],

                "image_path" =>
                    $image['image_path'],

                "alt_text" =>
                    $image['alt_text'],

                "is_primary" =>
                    (bool)$image['is_primary'],

                "sort_order" =>
                    (int)$image['sort_order'],

                "created_at" =>
                    $image['created_at']
            ];
        }

        $imageStmt->close();
    }

    /*
    |--------------------------------------------------------------------------
    | FETCH HALL FACILITIES
    |--------------------------------------------------------------------------
    */

    $facilities = [];

    if ($hallId !== null && $hall !== null) {

        $facilitySql = "

            SELECT

                hf.id,
                hf.name,
                hf.status

            FROM hall_facility_map hfm

            INNER JOIN hall_facilities hf
                ON hf.id = hfm.facility_id

            WHERE hfm.hall_id = ?

            ORDER BY
                hf.name ASC,
                hf.id ASC
        ";

        $facilityStmt =
            $conn->prepare($facilitySql);

        if (!$facilityStmt) {

            throw new Exception(
                "Failed to prepare hall facilities query: " .
                $conn->error
            );
        }

        $facilityStmt->bind_param(
            "i",
            $hallId
        );

        if (!$facilityStmt->execute()) {

            $error =
                $facilityStmt->error;

            $facilityStmt->close();

            throw new Exception(
                "Failed to fetch hall facilities: " .
                $error
            );
        }

        $facilityResult =
            $facilityStmt->get_result();

        while (
            $facility =
            $facilityResult->fetch_assoc()
        ) {

            $facilities[] = [

                "id" =>
                    (int)$facility['id'],

                "name" =>
                    $facility['name'],

                "status" =>
                    $facility['status']
            ];
        }

        $facilityStmt->close();
    }

    /*
    |--------------------------------------------------------------------------
    | BUILD HALL DATA
    |--------------------------------------------------------------------------
    */

    $hallData = null;

    if ($hall !== null) {

        $hallData = [

            "id" =>
                (int)$hall['id'],

            "name" =>
                $hall['name'],

            "slug" =>
                $hall['slug'],

            "description" =>
                $hall['description'],

            "capacity" =>
                (int)$hall['capacity'],

            "area" =>
                $hall['area'],

            "price_description" =>
                $hall['price_description'],

            "status" =>
                $hall['status'],

            "hall_type" =>
                $hall['hall_type_id'] !== null
                    ? [

                        "id" =>
                            (int)$hall['hall_type_id'],

                        "name" =>
                            $hall['hall_type_name'],

                        "slug" =>
                            $hall['hall_type_slug'],

                        "description" =>
                            $hall['hall_type_description'],

                        "status" =>
                            $hall['hall_type_status']
                    ]
                    : null,

            "images" =>
                $images,

            "facilities" =>
                $facilities,

            "created_at" =>
                $hall['created_at'],

            "updated_at" =>
                $hall['updated_at']
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | BUILD EVENT DATA
    |--------------------------------------------------------------------------
    */

    $eventDate =
        $hallLead['event_date'] !== null
            ? $hallLead['event_date']
            : $lead['event_date'];

    $eventTime =
        $hallLead['event_time'] !== null
            ? $hallLead['event_time']
            : $lead['preferred_time'];

    $guestCount =
        $hallLead['guest_count'] !== null
            ? (int)$hallLead['guest_count']
            : (
                $lead['guest_count'] !== null
                    ? (int)$lead['guest_count']
                    : null
            );

    /*
    |--------------------------------------------------------------------------
    | BUILD HALL LEAD DATA
    |--------------------------------------------------------------------------
    */

    $hallLeadData = [

        "lead_id" =>
            (int)$hallLead['lead_id'],

        "hall_id" =>
            $hallLead['hall_id'] !== null
                ? (int)$hallLead['hall_id']
                : null,

        "event_type" =>
            $hallLead['event_type'],

        "event_date" =>
            $eventDate,

        "event_time" =>
            $eventTime,

        "guest_count" =>
            $guestCount,

        "food_required" =>
            (bool)$hallLead['food_required'],

        "decoration_required" =>
            (bool)$hallLead['decoration_required'],

        "other_requirements" =>
            $hallLead['other_requirements']
    ];

    /*
    |--------------------------------------------------------------------------
    | FETCH NOTIFICATIONS
    |--------------------------------------------------------------------------
    */

    $notifications = [];

    $notificationSql = "

        SELECT

            id,
            channel,
            recipient,
            message,
            status,
            provider_message_id,
            error_message,
            sent_at,
            created_at

        FROM lead_notifications

        WHERE lead_id = ?

        ORDER BY
            id DESC
    ";

    $notificationStmt =
        $conn->prepare($notificationSql);

    if (!$notificationStmt) {

        throw new Exception(
            "Failed to prepare notification query: " .
            $conn->error
        );
    }

    $notificationStmt->bind_param(
        "i",
        $leadId
    );

    if (!$notificationStmt->execute()) {

        $error =
            $notificationStmt->error;

        $notificationStmt->close();

        throw new Exception(
            "Failed to fetch notifications: " .
            $error
        );
    }

    $notificationResult =
        $notificationStmt->get_result();

    while (
        $notification =
        $notificationResult->fetch_assoc()
    ) {

        /*
        |--------------------------------------------------------------------------
        | VALIDATE CHANNEL
        |--------------------------------------------------------------------------
        */

        $allowedChannels = [
            'whatsapp',
            'email'
        ];

        if (
            !in_array(
                $notification['channel'],
                $allowedChannels,
                true
            )
        ) {

            throw new Exception(
                "Invalid notification channel stored in database."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDATE NOTIFICATION STATUS
        |--------------------------------------------------------------------------
        */

        $allowedNotificationStatuses = [
            'pending',
            'sent',
            'failed'
        ];

        if (
            !in_array(
                $notification['status'],
                $allowedNotificationStatuses,
                true
            )
        ) {

            throw new Exception(
                "Invalid notification status stored in database."
            );
        }

        $notifications[] = [

            "id" =>
                (int)$notification['id'],

            "channel" =>
                $notification['channel'],

            "recipient" =>
                $notification['recipient'],

            "message" =>
                $notification['message'],

            "status" =>
                $notification['status'],

            "provider_message_id" =>
                $notification['provider_message_id'],

            "error_message" =>
                $notification['error_message'],

            "sent_at" =>
                $notification['sent_at'],

            "created_at" =>
                $notification['created_at']
        ];
    }

    $notificationStmt->close();

    /*
    |--------------------------------------------------------------------------
    | COMMON LEAD RESPONSE
    |--------------------------------------------------------------------------
    */

    $leadData = [

        "id" =>
            (int)$lead['id'],

        "lead_reference" =>
            $lead['lead_reference'],

        "domain" =>
            $lead['domain'],

        "customer" => [

            "name" =>
                $lead['customer_name'],

            "phone" =>
                $lead['customer_phone'],

            "email" =>
                $lead['customer_email']
        ],

        "event" => [

            "date" =>
                $eventDate,

            "guest_count" =>
                $guestCount,

            "preferred_time" =>
                $lead['preferred_time']
        ],

        "requirement" =>
            $lead['requirement'],

        "status" =>
            $lead['status'],

        "estimated_amount" =>
            $lead['estimated_amount'] !== null
                ? (float)$lead['estimated_amount']
                : null,

        "converted_amount" =>
            $lead['converted_amount'] !== null
                ? (float)$lead['converted_amount']
                : null,

        "converted_at" =>
            $lead['converted_at'],

        "source" =>
            $lead['source'],

        "assigned_admin" =>
            $assignedAdmin,

        "created_at" =>
            $lead['created_at'],

        "updated_at" =>
            $lead['updated_at']
    ];

    /*
    |--------------------------------------------------------------------------
    | FINAL RESPONSE
    |--------------------------------------------------------------------------
    */

    http_response_code(200);

    echo json_encode([

        "status" => true,

        "message" =>
            "Hall lead details fetched successfully.",

        "data" => [

            /*
            |--------------------------------------------------------------------------
            | USER ACCESS SCOPE
            |--------------------------------------------------------------------------
            */

            "user_scope" => [

                "role" =>
                    $currentUserRole,

                "user_id" =>
                    $currentUserId,

                "scope" =>
                    $currentUserRole === 'admin'
                        ? "all_hall_leads"
                        : "assigned_hall_leads_only"
            ],

            /*
            |--------------------------------------------------------------------------
            | LEAD
            |--------------------------------------------------------------------------
            */

            "lead" =>
                $leadData,

            /*
            |--------------------------------------------------------------------------
            | HALL LEAD DETAILS
            |--------------------------------------------------------------------------
            */

            "hall_enquiry" =>
                $hallLeadData,

            /*
            |--------------------------------------------------------------------------
            | HALL
            |--------------------------------------------------------------------------
            */

            "hall" =>
                $hallData,

            /*
            |--------------------------------------------------------------------------
            | NOTIFICATIONS
            |--------------------------------------------------------------------------
            */

            "notifications" =>
                $notifications
        ]
    ]);

    exit;

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([

        "status" => false,

        "message" =>
            "Failed to fetch hall lead details.",

        "error" =>
            $e->getMessage()
    ]);

    exit;
}