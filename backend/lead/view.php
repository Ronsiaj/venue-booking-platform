<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/middleware.php';

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
        "status" => false,
        "message" => "Only GET method is allowed."
    ]);

    exit;
}

try {

    /*
    |--------------------------------------------------------------------------
    | AUTHENTICATION
    |--------------------------------------------------------------------------
    */

    $currentUser = requireAdminOrStaff();

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
    | CURRENT USER ROLE
    |--------------------------------------------------------------------------
    */

    $currentUserRole = strtolower(
        trim((string)($currentUser['role'] ?? ''))
    );

    /*
    |--------------------------------------------------------------------------
    | IMPORTANT
    |--------------------------------------------------------------------------
    | JWT user ID is stored in "sub".
    | "id" is kept as fallback for compatibility.
    |--------------------------------------------------------------------------
    */

    $currentUserId =
        $currentUser['sub']
        ?? $currentUser['id']
        ?? null;

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
                "Only admin or staff users can view leads."
        ]);

        exit;
    }

    if (
        filter_var(
            $currentUserId,
            FILTER_VALIDATE_INT
        ) === false ||
        (int)$currentUserId <= 0
    ) {
        http_response_code(401);

        echo json_encode([
            "status" => false,
            "message" =>
                "Invalid authenticated user."
        ]);

        exit;
    }

    $currentUserId = (int)$currentUserId;

    /*
    |--------------------------------------------------------------------------
    | QUERY PARAMETER VALIDATION
    |--------------------------------------------------------------------------
    */

    $allowedParams = [
        'lead_id'
    ];

    foreach ($_GET as $key => $value) {

        if (!in_array($key, $allowedParams, true)) {

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
    | LEAD ID
    |--------------------------------------------------------------------------
    */

    if (
        !isset($_GET['lead_id']) ||
        trim((string)$_GET['lead_id']) === ''
    ) {
        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "lead_id is required."
        ]);

        exit;
    }

    $leadIdRaw =
        trim((string)$_GET['lead_id']);

    if (
        !ctype_digit($leadIdRaw) ||
        (int)$leadIdRaw <= 0
    ) {
        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" =>
                "lead_id must be a positive integer."
        ]);

        exit;
    }

    $leadId = (int)$leadIdRaw;

    /*
    |--------------------------------------------------------------------------
    | FETCH MAIN LEAD
    |--------------------------------------------------------------------------
    |
    | ADMIN:
    |   Any lead
    |
    | STAFF:
    |   Only own assigned leads
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
    ";

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
            "Failed to prepare lead query: " .
            $conn->error
        );
    }

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

    if (!$leadStmt->execute()) {

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
    | LEAD NOT FOUND / STAFF NOT AUTHORIZED
    |--------------------------------------------------------------------------
    */

    if (!$lead) {

        http_response_code(404);

        echo json_encode([
            "status" => false,
            "message" =>
                $currentUserRole === 'staff'
                    ? "Lead not found or not assigned to you."
                    : "Lead not found.",
            "lead_id" => $leadId
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | DOMAIN VALIDATION
    |--------------------------------------------------------------------------
    */

    $allowedDomains = [
        'hall',
        'dining',
        'catering'
    ];

    $domain =
        strtolower(
            trim((string)$lead['domain'])
        );

    if (
        !in_array(
            $domain,
            $allowedDomains,
            true
        )
    ) {
        throw new Exception(
            "Invalid domain value stored for this lead."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | COMMON LEAD DATA
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
                $lead['event_date'],

            "guest_count" =>
                $lead['guest_count'] !== null
                    ? (int)$lead['guest_count']
                    : null,

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

        "assigned_admin" => [

            "id" =>
                $lead['assigned_admin_id'] !== null
                    ? (int)$lead['assigned_admin_id']
                    : null,

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
        ],

        "created_at" =>
            $lead['created_at'],

        "updated_at" =>
            $lead['updated_at']
    ];

    /*
    |--------------------------------------------------------------------------
    | DOMAIN DETAILS
    |--------------------------------------------------------------------------
    */

    $domainDetails = null;

    /*
    |--------------------------------------------------------------------------
    | HALL
    |--------------------------------------------------------------------------
    */

    if ($domain === 'hall') {

        $hallSql = "

            SELECT

                hld.lead_id,
                hld.hall_id,

                h.name AS hall_name,
                h.slug AS hall_slug,
                h.description AS hall_description,
                h.capacity AS hall_capacity,
                h.area AS hall_area,
                h.price_description AS hall_price_description,
                h.status AS hall_status,

                ht.id AS hall_type_id,
                ht.name AS hall_type_name,
                ht.slug AS hall_type_slug,

                hld.event_type,
                hld.event_date,
                hld.event_time,
                hld.guest_count,

                hld.food_required,
                hld.decoration_required,

                hld.other_requirements

            FROM hall_lead_details hld

            LEFT JOIN halls h
                ON h.id = hld.hall_id

            LEFT JOIN hall_types ht
                ON ht.id = h.hall_type_id

            WHERE hld.lead_id = ?

            LIMIT 1
        ";

        $hallStmt =
            $conn->prepare($hallSql);

        if (!$hallStmt) {
            throw new Exception(
                "Failed to prepare hall lead query: " .
                $conn->error
            );
        }

        $hallStmt->bind_param(
            "i",
            $leadId
        );

        if (!$hallStmt->execute()) {

            $error =
                $hallStmt->error;

            $hallStmt->close();

            throw new Exception(
                "Failed to fetch hall lead details: " .
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
        | HALL IMAGES
        |--------------------------------------------------------------------------
        */

        $images = [];

        if (
            $hall &&
            $hall['hall_id'] !== null
        ) {

            $hallId =
                (int)$hall['hall_id'];

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
                    "Failed to prepare hall images query."
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
        | HALL FACILITIES
        |--------------------------------------------------------------------------
        */

        $facilities = [];

        if (
            $hall &&
            $hall['hall_id'] !== null
        ) {

            $facilitySql = "

                SELECT

                    hf.id,
                    hf.name,
                    hf.status

                FROM hall_facility_map hfm

                INNER JOIN hall_facilities hf
                    ON hf.id = hfm.facility_id

                WHERE hfm.hall_id = ?

                ORDER BY hf.name ASC
            ";

            $facilityStmt =
                $conn->prepare(
                    $facilitySql
                );

            if (!$facilityStmt) {
                throw new Exception(
                    "Failed to prepare hall facilities query."
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
        | HALL RESPONSE
        |--------------------------------------------------------------------------
        */

        $domainDetails = [

            "hall" => $hall
                ? [

                    "id" =>
                        $hall['hall_id'] !== null
                            ? (int)$hall['hall_id']
                            : null,

                    "name" =>
                        $hall['hall_name'],

                    "slug" =>
                        $hall['hall_slug'],

                    "description" =>
                        $hall['hall_description'],

                    "capacity" =>
                        $hall['hall_capacity'] !== null
                            ? (int)$hall['hall_capacity']
                            : null,

                    "area" =>
                        $hall['hall_area'],

                    "price_description" =>
                        $hall['hall_price_description'],

                    "status" =>
                        $hall['hall_status']
                ]
                : null,

            "hall_type" => $hall
                ? [

                    "id" =>
                        $hall['hall_type_id'] !== null
                            ? (int)$hall['hall_type_id']
                            : null,

                    "name" =>
                        $hall['hall_type_name'],

                    "slug" =>
                        $hall['hall_type_slug']
                ]
                : null,

            "event" => $hall
                ? [

                    "type" =>
                        $hall['event_type'],

                    "date" =>
                        $hall['event_date']
                            ?? $lead['event_date'],

                    "time" =>
                        $hall['event_time']
                            ?? $lead['preferred_time'],

                    "guest_count" =>
                        $hall['guest_count'] !== null
                            ? (int)$hall['guest_count']
                            : (
                                $lead['guest_count'] !== null
                                    ? (int)$lead['guest_count']
                                    : null
                            )
                ]
                : null,

            "food_required" =>
                $hall &&
                $hall['food_required'] !== null
                    ? (bool)$hall['food_required']
                    : null,

            "decoration_required" =>
                $hall &&
                $hall['decoration_required'] !== null
                    ? (bool)$hall['decoration_required']
                    : null,

            "other_requirements" =>
                $hall
                    ? $hall['other_requirements']
                    : null,

            "images" =>
                $images,

            "facilities" =>
                $facilities
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | DINING
    |--------------------------------------------------------------------------
    */

    elseif ($domain === 'dining') {

        $diningSql = "

            SELECT

                dld.lead_id,
                dld.dining_package_id,

                dp.name AS package_name,
                dp.description AS package_description,
                dp.meal_type AS package_meal_type,
                dp.image_path AS package_image,
                dp.status AS package_status,

                dld.meal_type,
                dld.dining_date,
                dld.dining_time,
                dld.guest_count,
                dld.other_requirements

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
                "Failed to prepare dining lead query: " .
                $conn->error
            );
        }

        $diningStmt->bind_param(
            "i",
            $leadId
        );

        if (!$diningStmt->execute()) {

            $error =
                $diningStmt->error;

            $diningStmt->close();

            throw new Exception(
                "Failed to fetch dining lead details: " .
                $error
            );
        }

        $diningResult =
            $diningStmt->get_result();

        $dining =
            $diningResult->fetch_assoc();

        $diningStmt->close();

        /*
        |--------------------------------------------------------------------------
        | DINING PACKAGE ITEMS
        |--------------------------------------------------------------------------
        */

        $packageItems = [];

        if (
            $dining &&
            $dining['dining_package_id'] !== null
        ) {

            $packageId =
                (int)$dining['dining_package_id'];

            $packageItemSql = "

                SELECT

                    dpi.food_item_id,
                    dpi.quantity,

                    fi.name AS food_item_name,
                    fi.description AS food_item_description,
                    fi.image_path AS food_item_image,
                    fi.food_type,
                    fi.status AS food_item_status,

                    fc.id AS food_category_id,
                    fc.name AS food_category_name

                FROM dining_package_items dpi

                INNER JOIN food_items fi
                    ON fi.id = dpi.food_item_id

                LEFT JOIN food_categories fc
                    ON fc.id = fi.food_category_id

                WHERE dpi.package_id = ?

                ORDER BY fi.name ASC
            ";

            $packageItemStmt =
                $conn->prepare(
                    $packageItemSql
                );

            if (!$packageItemStmt) {
                throw new Exception(
                    "Failed to prepare dining package items query."
                );
            }

            $packageItemStmt->bind_param(
                "i",
                $packageId
            );

            if (!$packageItemStmt->execute()) {

                $error =
                    $packageItemStmt->error;

                $packageItemStmt->close();

                throw new Exception(
                    "Failed to fetch dining package items: " .
                    $error
                );
            }

            $packageItemResult =
                $packageItemStmt->get_result();

            while (
                $item =
                $packageItemResult->fetch_assoc()
            ) {

                $packageItems[] = [

                    "food_item_id" =>
                        (int)$item['food_item_id'],

                    "quantity" =>
                        (int)$item['quantity'],

                    "food_item" => [

                        "id" =>
                            (int)$item['food_item_id'],

                        "name" =>
                            $item['food_item_name'],

                        "description" =>
                            $item['food_item_description'],

                        "image_path" =>
                            $item['food_item_image'],

                        "food_type" =>
                            $item['food_type'],

                        "status" =>
                            $item['food_item_status']
                    ],

                    "category" => [

                        "id" =>
                            $item['food_category_id'] !== null
                                ? (int)$item['food_category_id']
                                : null,

                        "name" =>
                            $item['food_category_name']
                    ]
                ];
            }

            $packageItemStmt->close();
        }

        /*
        |--------------------------------------------------------------------------
        | CUSTOM DINING ITEMS
        |--------------------------------------------------------------------------
        */

        $customItems = [];

        $customItemSql = "

            SELECT

                dli.id,
                dli.food_item_id,
                dli.quantity,
                dli.notes,

                fi.name AS food_item_name,
                fi.description AS food_item_description,
                fi.image_path AS food_item_image,
                fi.food_type,
                fi.status AS food_item_status,

                fc.id AS food_category_id,
                fc.name AS food_category_name

            FROM dining_lead_items dli

            INNER JOIN food_items fi
                ON fi.id = dli.food_item_id

            LEFT JOIN food_categories fc
                ON fc.id = fi.food_category_id

            WHERE dli.lead_id = ?

            ORDER BY dli.id ASC
        ";

        $customItemStmt =
            $conn->prepare(
                $customItemSql
            );

        if (!$customItemStmt) {
            throw new Exception(
                "Failed to prepare dining custom items query."
            );
        }

        $customItemStmt->bind_param(
            "i",
            $leadId
        );

        if (!$customItemStmt->execute()) {

            $error =
                $customItemStmt->error;

            $customItemStmt->close();

            throw new Exception(
                "Failed to fetch dining custom items: " .
                $error
            );
        }

        $customItemResult =
            $customItemStmt->get_result();

        while (
            $item =
            $customItemResult->fetch_assoc()
        ) {

            $customItems[] = [

                "id" =>
                    (int)$item['id'],

                "food_item_id" =>
                    (int)$item['food_item_id'],

                "quantity" =>
                    (int)$item['quantity'],

                "notes" =>
                    $item['notes'],

                "food_item" => [

                    "id" =>
                        (int)$item['food_item_id'],

                    "name" =>
                        $item['food_item_name'],

                    "description" =>
                        $item['food_item_description'],

                    "image_path" =>
                        $item['food_item_image'],

                    "food_type" =>
                        $item['food_type'],

                    "status" =>
                        $item['food_item_status']
                ],

                "category" => [

                    "id" =>
                        $item['food_category_id'] !== null
                            ? (int)$item['food_category_id']
                            : null,

                    "name" =>
                        $item['food_category_name']
                ]
            ];
        }

        $customItemStmt->close();

        /*
        |--------------------------------------------------------------------------
        | DINING RESPONSE
        |--------------------------------------------------------------------------
        */

        $domainDetails = [

            "dining_package" =>
                $dining
                    ? [

                        "id" =>
                            $dining['dining_package_id'] !== null
                                ? (int)$dining['dining_package_id']
                                : null,

                        "name" =>
                            $dining['package_name'],

                        "description" =>
                            $dining['package_description'],

                        "meal_type" =>
                            $dining['package_meal_type'],

                        "image_path" =>
                            $dining['package_image'],

                        "status" =>
                            $dining['package_status']
                    ]
                    : null,

            "event" =>
                $dining
                    ? [

                        "meal_type" =>
                            $dining['meal_type'],

                        "date" =>
                            $dining['dining_date']
                                ?? $lead['event_date'],

                        "time" =>
                            $dining['dining_time']
                                ?? $lead['preferred_time'],

                        "guest_count" =>
                            $dining['guest_count'] !== null
                                ? (int)$dining['guest_count']
                                : (
                                    $lead['guest_count'] !== null
                                        ? (int)$lead['guest_count']
                                        : null
                                )
                    ]
                    : null,

            "package_items" =>
                $packageItems,

            "custom_food_items" =>
                $customItems,

            "other_requirements" =>
                $dining
                    ? $dining['other_requirements']
                    : null
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | CATERING
    |--------------------------------------------------------------------------
    */

    elseif ($domain === 'catering') {

        $cateringSql = "

            SELECT

                cld.lead_id,
                cld.catering_package_id,

                cp.name AS package_name,
                cp.description AS package_description,
                cp.rate AS package_rate,
                cp.rate_unit AS package_rate_unit,
                cp.gst_rate AS package_gst_rate,
                cp.price_includes_gst
                    AS package_price_includes_gst,
                cp.minimum_quantity
                    AS package_minimum_quantity,
                cp.image_path AS package_image,
                cp.status AS package_status,

                cld.event_type,
                cld.event_date,
                cld.event_time,
                cld.guest_count,
                cld.venue_address,
                cld.estimated_amount
                    AS catering_estimated_amount,
                cld.other_requirements

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
                "Failed to prepare catering lead query: " .
                $conn->error
            );
        }

        $cateringStmt->bind_param(
            "i",
            $leadId
        );

        if (!$cateringStmt->execute()) {

            $error =
                $cateringStmt->error;

            $cateringStmt->close();

            throw new Exception(
                "Failed to fetch catering lead details: " .
                $error
            );
        }

        $cateringResult =
            $cateringStmt->get_result();

        $catering =
            $cateringResult->fetch_assoc();

        $cateringStmt->close();

        /*
        |--------------------------------------------------------------------------
        | CATERING PACKAGE ITEMS
        |--------------------------------------------------------------------------
        */

        $packageItems = [];

        if (
            $catering &&
            $catering['catering_package_id'] !== null
        ) {

            $packageId =
                (int)$catering['catering_package_id'];

            $packageItemSql = "

                SELECT

                    cpi.food_item_id,
                    cpi.quantity,

                    fi.name AS food_item_name,
                    fi.description AS food_item_description,
                    fi.image_path AS food_item_image,
                    fi.food_type,
                    fi.status AS food_item_status,

                    fc.id AS food_category_id,
                    fc.name AS food_category_name

                FROM catering_package_items cpi

                INNER JOIN food_items fi
                    ON fi.id = cpi.food_item_id

                LEFT JOIN food_categories fc
                    ON fc.id = fi.food_category_id

                WHERE cpi.package_id = ?

                ORDER BY fi.name ASC
            ";

            $packageItemStmt =
                $conn->prepare(
                    $packageItemSql
                );

            if (!$packageItemStmt) {
                throw new Exception(
                    "Failed to prepare catering package items query."
                );
            }

            $packageItemStmt->bind_param(
                "i",
                $packageId
            );

            if (!$packageItemStmt->execute()) {

                $error =
                    $packageItemStmt->error;

                $packageItemStmt->close();

                throw new Exception(
                    "Failed to fetch catering package items: " .
                    $error
                );
            }

            $packageItemResult =
                $packageItemStmt->get_result();

            while (
                $item =
                $packageItemResult->fetch_assoc()
            ) {

                $packageItems[] = [

                    "food_item_id" =>
                        (int)$item['food_item_id'],

                    "quantity" =>
                        (int)$item['quantity'],

                    "food_item" => [

                        "id" =>
                            (int)$item['food_item_id'],

                        "name" =>
                            $item['food_item_name'],

                        "description" =>
                            $item['food_item_description'],

                        "image_path" =>
                            $item['food_item_image'],

                        "food_type" =>
                            $item['food_type'],

                        "status" =>
                            $item['food_item_status']
                    ],

                    "category" => [

                        "id" =>
                            $item['food_category_id'] !== null
                                ? (int)$item['food_category_id']
                                : null,

                        "name" =>
                            $item['food_category_name']
                    ]
                ];
            }

            $packageItemStmt->close();
        }

        /*
        |--------------------------------------------------------------------------
        | CUSTOM CATERING ITEMS
        |--------------------------------------------------------------------------
        */

        $customItems = [];

        $customItemSql = "

            SELECT

                cli.id,
                cli.food_item_id,
                cli.quantity,
                cli.notes,

                fi.name AS food_item_name,
                fi.description AS food_item_description,
                fi.image_path AS food_item_image,
                fi.food_type,
                fi.status AS food_item_status,

                fc.id AS food_category_id,
                fc.name AS food_category_name

            FROM catering_lead_items cli

            INNER JOIN food_items fi
                ON fi.id = cli.food_item_id

            LEFT JOIN food_categories fc
                ON fc.id = fi.food_category_id

            WHERE cli.lead_id = ?

            ORDER BY cli.id ASC
        ";

        $customItemStmt =
            $conn->prepare(
                $customItemSql
            );

        if (!$customItemStmt) {
            throw new Exception(
                "Failed to prepare catering custom items query."
            );
        }

        $customItemStmt->bind_param(
            "i",
            $leadId
        );

        if (!$customItemStmt->execute()) {

            $error =
                $customItemStmt->error;

            $customItemStmt->close();

            throw new Exception(
                "Failed to fetch catering custom items: " .
                $error
            );
        }

        $customItemResult =
            $customItemStmt->get_result();

        while (
            $item =
            $customItemResult->fetch_assoc()
        ) {

            $customItems[] = [

                "id" =>
                    (int)$item['id'],

                "food_item_id" =>
                    (int)$item['food_item_id'],

                "quantity" =>
                    (int)$item['quantity'],

                "notes" =>
                    $item['notes'],

                "food_item" => [

                    "id" =>
                        (int)$item['food_item_id'],

                    "name" =>
                        $item['food_item_name'],

                    "description" =>
                        $item['food_item_description'],

                    "image_path" =>
                        $item['food_item_image'],

                    "food_type" =>
                        $item['food_type'],

                    "status" =>
                        $item['food_item_status']
                ],

                "category" => [

                    "id" =>
                        $item['food_category_id'] !== null
                            ? (int)$item['food_category_id']
                            : null,

                    "name" =>
                        $item['food_category_name']
                ]
            ];
        }

        $customItemStmt->close();

        /*
        |--------------------------------------------------------------------------
        | CATERING RESPONSE
        |--------------------------------------------------------------------------
        */

        $domainDetails = [

            "catering_package" =>
                $catering
                    ? [

                        "id" =>
                            $catering['catering_package_id'] !== null
                                ? (int)$catering['catering_package_id']
                                : null,

                        "name" =>
                            $catering['package_name'],

                        "description" =>
                            $catering['package_description'],

                        "rate" =>
                            $catering['package_rate'] !== null
                                ? (float)$catering['package_rate']
                                : null,

                        "rate_unit" =>
                            $catering['package_rate_unit'],

                        "gst_rate" =>
                            $catering['package_gst_rate'] !== null
                                ? (float)$catering['package_gst_rate']
                                : null,

                        "price_includes_gst" =>
                            $catering['package_price_includes_gst'] !== null
                                ? (bool)$catering['package_price_includes_gst']
                                : null,

                        "minimum_quantity" =>
                            $catering['package_minimum_quantity'] !== null
                                ? (int)$catering['package_minimum_quantity']
                                : null,

                        "image_path" =>
                            $catering['package_image'],

                        "status" =>
                            $catering['package_status']
                    ]
                    : null,

            "event" =>
                $catering
                    ? [

                        "type" =>
                            $catering['event_type'],

                        "date" =>
                            $catering['event_date']
                                ?? $lead['event_date'],

                        "time" =>
                            $catering['event_time']
                                ?? $lead['preferred_time'],

                        "guest_count" =>
                            $catering['guest_count'] !== null
                                ? (int)$catering['guest_count']
                                : (
                                    $lead['guest_count'] !== null
                                        ? (int)$lead['guest_count']
                                        : null
                                ),

                        "venue_address" =>
                            $catering['venue_address']
                    ]
                    : null,

            "estimated_amount" =>
                $catering &&
                $catering['catering_estimated_amount'] !== null
                    ? (float)$catering['catering_estimated_amount']
                    : null,

            "package_items" =>
                $packageItems,

            "custom_food_items" =>
                $customItems,

            "other_requirements" =>
                $catering
                    ? $catering['other_requirements']
                    : null
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | NOTIFICATIONS
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

        ORDER BY id DESC
    ";

    $notificationStmt =
        $conn->prepare(
            $notificationSql
        );

    if (!$notificationStmt) {
        throw new Exception(
            "Failed to prepare notification query."
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
            "Failed to fetch lead notifications: " .
            $error
        );
    }

    $notificationResult =
        $notificationStmt->get_result();

    while (
        $notification =
        $notificationResult->fetch_assoc()
    ) {

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
    | FINAL RESPONSE
    |--------------------------------------------------------------------------
    */

    http_response_code(200);

    echo json_encode([

        "status" => true,

        "message" =>
            "Lead details fetched successfully.",

        "data" => [

            "user_scope" => [

                "role" =>
                    $currentUserRole,

                "user_id" =>
                    $currentUserId,

                "scope" =>
                    $currentUserRole === 'admin'
                        ? "all_leads"
                        : "assigned_leads_only"
            ],

            "lead" =>
                $leadData,

            "domain_details" =>
                $domainDetails,

            "notifications" =>
                $notifications
        ]
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([

        "status" => false,

        "message" =>
            "Failed to fetch lead details.",

        "error" =>
            $e->getMessage()
    ]);
}