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
    | Only Admin / Staff can view catering leads
    |--------------------------------------------------------------------------
    */

    $currentUser = requireAdminOrStaff();

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
    | QUERY PARAMETER VALIDATION
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | Only lead_id is allowed
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
                "message" => "Unknown query parameter: {$key}"
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
            "message" => "lead_id is required."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | LEAD ID VALIDATION
    |--------------------------------------------------------------------------
    */

    $leadIdRaw = trim(
        (string)$_GET['lead_id']
    );

    if (
        !ctype_digit($leadIdRaw)
    ) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "lead_id must be a positive integer."
        ]);

        exit;
    }

    $leadId = (int)$leadIdRaw;

    if ($leadId < 1) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "lead_id must be greater than 0."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | FETCH MAIN LEAD
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
            au.phone AS assigned_admin_phone

        FROM leads l

        LEFT JOIN admin_users au
            ON au.id = l.assigned_admin_id

        WHERE
            l.id = ?
            AND l.domain = 'catering'

        LIMIT 1
    ";

    $leadStmt = $conn->prepare($leadSql);

    if (!$leadStmt) {

        throw new Exception(
            "Failed to prepare catering lead query: " .
            $conn->error
        );
    }

    $leadStmt->bind_param(
        "i",
        $leadId
    );

    if (!$leadStmt->execute()) {

        $error =
            $leadStmt->error;

        $leadStmt->close();

        throw new Exception(
            "Failed to fetch catering lead: " .
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
            "message" => "Catering lead not found.",
            "lead_id" => $leadId
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | FETCH CATERING LEAD DETAILS
    |--------------------------------------------------------------------------
    */

    $detailsSql = "
        SELECT
            cld.lead_id,
            cld.catering_package_id,
            cld.event_type,
            cld.event_date,
            cld.event_time,
            cld.guest_count,
            cld.venue_address,
            cld.estimated_amount,
            cld.other_requirements,

            cp.name AS package_name,
            cp.description AS package_description,
            cp.rate AS package_rate,
            cp.rate_unit AS package_rate_unit,
            cp.gst_rate AS package_gst_rate,
            cp.price_includes_gst AS package_price_includes_gst,
            cp.minimum_quantity AS package_minimum_quantity,
            cp.status AS package_status

        FROM catering_lead_details cld

        LEFT JOIN catering_packages cp
            ON cp.id = cld.catering_package_id

        WHERE cld.lead_id = ?

        LIMIT 1
    ";

    $detailsStmt =
        $conn->prepare($detailsSql);

    if (!$detailsStmt) {

        throw new Exception(
            "Failed to prepare catering lead details query: " .
            $conn->error
        );
    }

    $detailsStmt->bind_param(
        "i",
        $leadId
    );

    if (!$detailsStmt->execute()) {

        $error =
            $detailsStmt->error;

        $detailsStmt->close();

        throw new Exception(
            "Failed to fetch catering lead details: " .
            $error
        );
    }

    $detailsResult =
        $detailsStmt->get_result();

    $details =
        $detailsResult->fetch_assoc();

    $detailsStmt->close();

    /*
    |--------------------------------------------------------------------------
    | CATERING DETAILS DEFAULT
    |--------------------------------------------------------------------------
    */

    if (!$details) {

        $cateringDetails = [
            "lead_id" => $leadId,
            "catering_package_id" => null,
            "event_type" => null,
            "event_date" => null,
            "event_time" => null,
            "guest_count" => null,
            "venue_address" => null,
            "estimated_amount" => null,
            "other_requirements" => null
        ];

        $selectedPackage = null;

    } else {

        /*
        |--------------------------------------------------------------------------
        | CATERING DETAILS
        |--------------------------------------------------------------------------
        */

        $cateringDetails = [
            "lead_id" =>
                (int)$details['lead_id'],

            "catering_package_id" =>
                $details['catering_package_id'] !== null
                    ? (int)$details['catering_package_id']
                    : null,

            "event_type" =>
                $details['event_type'],

            "event_date" =>
                $details['event_date'],

            "event_time" =>
                $details['event_time'],

            "guest_count" =>
                $details['guest_count'] !== null
                    ? (int)$details['guest_count']
                    : null,

            "venue_address" =>
                $details['venue_address'],

            "estimated_amount" =>
                $details['estimated_amount'] !== null
                    ? (float)$details['estimated_amount']
                    : null,

            "other_requirements" =>
                $details['other_requirements']
        ];

        /*
        |--------------------------------------------------------------------------
        | SELECTED PACKAGE
        |--------------------------------------------------------------------------
        */

        if (
            $details['catering_package_id'] !== null &&
            $details['package_name'] !== null
        ) {

            $selectedPackage = [

                "id" =>
                    (int)$details['catering_package_id'],

                "name" =>
                    $details['package_name'],

                "description" =>
                    $details['package_description'],

                "rate" =>
                    $details['package_rate'] !== null
                        ? (float)$details['package_rate']
                        : null,

                "rate_unit" =>
                    $details['package_rate_unit'],

                "gst_rate" =>
                    $details['package_gst_rate'] !== null
                        ? (float)$details['package_gst_rate']
                        : null,

                "price_includes_gst" =>
                    (bool)$details['package_price_includes_gst'],

                "minimum_quantity" =>
                    $details['package_minimum_quantity'] !== null
                        ? (int)$details['package_minimum_quantity']
                        : null,

                "status" =>
                    $details['package_status']
            ];

        } else {

            /*
            |--------------------------------------------------------------------------
            | Package may have been deleted
            |--------------------------------------------------------------------------
            */

            $selectedPackage = null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FETCH SELECTED PACKAGE FOOD ITEMS
    |--------------------------------------------------------------------------
    */

    $packageItems = [];

    if (
        $details &&
        $details['catering_package_id'] !== null
    ) {

        $packageItemsSql = "
            SELECT
                cpi.package_id,
                cpi.food_item_id,
                cpi.quantity,

                fi.name AS food_item_name,
                fi.description AS food_item_description,
                fi.food_type,
                fi.image_path AS food_item_image,
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

        $packageItemsStmt =
            $conn->prepare($packageItemsSql);

        if (!$packageItemsStmt) {

            throw new Exception(
                "Failed to prepare catering package items query: " .
                $conn->error
            );
        }

        $packageId =
            (int)$details['catering_package_id'];

        $packageItemsStmt->bind_param(
            "i",
            $packageId
        );

        if (!$packageItemsStmt->execute()) {

            $error =
                $packageItemsStmt->error;

            $packageItemsStmt->close();

            throw new Exception(
                "Failed to fetch catering package items: " .
                $error
            );
        }

        $packageItemsResult =
            $packageItemsStmt->get_result();

        while (
            $row =
            $packageItemsResult->fetch_assoc()
        ) {

            $packageItems[] = [

                "food_item_id" =>
                    (int)$row['food_item_id'],

                "food_item_name" =>
                    $row['food_item_name'],

                "food_item_description" =>
                    $row['food_item_description'],

                "food_type" =>
                    $row['food_type'],

                "food_item_image" =>
                    $row['food_item_image'],

                "food_item_status" =>
                    $row['food_item_status'],

                "food_category" => [

                    "id" =>
                        $row['food_category_id'] !== null
                            ? (int)$row['food_category_id']
                            : null,

                    "name" =>
                        $row['food_category_name']
                ],

                "quantity" =>
                    (int)$row['quantity']
            ];
        }

        $packageItemsStmt->close();
    }

    /*
    |--------------------------------------------------------------------------
    | FETCH CUSTOM FOOD ITEMS
    |--------------------------------------------------------------------------
    */

    $customFoodItems = [];

    $customItemsSql = "
        SELECT
            cli.id,
            cli.lead_id,
            cli.food_item_id,
            cli.quantity,
            cli.notes,

            fi.name AS food_item_name,
            fi.description AS food_item_description,
            fi.food_type,
            fi.image_path AS food_item_image,
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

    $customItemsStmt =
        $conn->prepare($customItemsSql);

    if (!$customItemsStmt) {

        throw new Exception(
            "Failed to prepare custom food items query: " .
            $conn->error
        );
    }

    $customItemsStmt->bind_param(
        "i",
        $leadId
    );

    if (!$customItemsStmt->execute()) {

        $error =
            $customItemsStmt->error;

        $customItemsStmt->close();

        throw new Exception(
            "Failed to fetch custom food items: " .
            $error
        );
    }

    $customItemsResult =
        $customItemsStmt->get_result();

    while (
        $row =
        $customItemsResult->fetch_assoc()
    ) {

        $customFoodItems[] = [

            "id" =>
                (int)$row['id'],

            "food_item_id" =>
                (int)$row['food_item_id'],

            "food_item_name" =>
                $row['food_item_name'],

            "food_item_description" =>
                $row['food_item_description'],

            "food_type" =>
                $row['food_type'],

            "food_item_image" =>
                $row['food_item_image'],

            "food_item_status" =>
                $row['food_item_status'],

            "food_category" => [

                "id" =>
                    $row['food_category_id'] !== null
                        ? (int)$row['food_category_id']
                        : null,

                "name" =>
                    $row['food_category_name']
            ],

            "quantity" =>
                (int)$row['quantity'],

            "notes" =>
                $row['notes']
        ];
    }

    $customItemsStmt->close();

    /*
    |--------------------------------------------------------------------------
    | RESPONSE DATA
    |--------------------------------------------------------------------------
    */

    $responseLead = [

        "id" =>
            (int)$lead['id'],

        "lead_reference" =>
            $lead['lead_reference'],

        "domain" =>
            $lead['domain'],

        "customer_name" =>
            $lead['customer_name'],

        "customer_phone" =>
            $lead['customer_phone'],

        "customer_email" =>
            $lead['customer_email'],

        "event_date" =>
            $lead['event_date'],

        "guest_count" =>
            $lead['guest_count'] !== null
                ? (int)$lead['guest_count']
                : null,

        "preferred_time" =>
            $lead['preferred_time'],

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
                $lead['assigned_admin_phone']
        ],

        "created_at" =>
            $lead['created_at'],

        "updated_at" =>
            $lead['updated_at']
    ];

    /*
    |--------------------------------------------------------------------------
    | SUCCESS RESPONSE
    |--------------------------------------------------------------------------
    */

    http_response_code(200);

    echo json_encode([
        "status" => true,

        "message" =>
            "Catering lead fetched successfully.",

        "data" => [

            "lead" =>
                $responseLead,

            "catering_details" =>
                $cateringDetails,

            "selected_package" =>
                $selectedPackage,

            "package_food_items" =>
                $packageItems,

            "custom_food_items" =>
                $customFoodItems,

            "summary" => [

                "package_selected" =>
                    $selectedPackage !== null,

                "package_food_item_count" =>
                    count($packageItems),

                "custom_food_item_count" =>
                    count($customFoodItems),

                "total_food_item_types" =>
                    count($packageItems) +
                    count($customFoodItems)
            ]
        ]
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        "status" => false,
        "message" => "Failed to fetch catering lead.",
        "error" => $e->getMessage()
    ]);
}