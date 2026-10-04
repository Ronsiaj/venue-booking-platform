<?php

require_once __DIR__ . '/../../config/database.php';

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
| ONLY GET
|--------------------------------------------------------------------------
*/

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
    | ALLOWED QUERY PARAMETERS
    |--------------------------------------------------------------------------
    |
    | Only package_id is accepted.
    |
    */

    $allowedParams = [
        'package_id'
    ];

    foreach ($_GET as $key => $value) {

        if (!in_array(
            $key,
            $allowedParams,
            true
        )) {

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
    | PACKAGE ID REQUIRED
    |--------------------------------------------------------------------------
    */

    if (!isset($_GET['package_id'])) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "package_id is required."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | PACKAGE ID VALIDATION
    |--------------------------------------------------------------------------
    */

    $packageIdRaw =
        trim((string)$_GET['package_id']);

    if (
        $packageIdRaw === '' ||
        !ctype_digit($packageIdRaw)
    ) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "package_id must be a positive integer."
        ]);

        exit;
    }

    $packageId =
        (int)$packageIdRaw;

    if ($packageId < 1) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "package_id must be greater than 0."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | FETCH PACKAGE
    |--------------------------------------------------------------------------
    |
    | Public API:
    | Only ACTIVE packages are exposed.
    |
    */

    $packageSql = "
        SELECT
            id,
            name,
            description,
            rate,
            rate_unit,
            gst_rate,
            price_includes_gst,
            minimum_quantity,
            image_path,
            status,
            created_at,
            updated_at
        FROM catering_packages
        WHERE id = ?
          AND status = 'active'
        LIMIT 1
    ";

    $packageStmt =
        $conn->prepare($packageSql);

    if (!$packageStmt) {

        throw new Exception(
            "Failed to prepare catering package query."
        );
    }

    $packageStmt->bind_param(
        "i",
        $packageId
    );

    if (!$packageStmt->execute()) {

        $error =
            $packageStmt->error;

        $packageStmt->close();

        throw new Exception(
            "Failed to fetch catering package: " .
            $error
        );
    }

    $packageResult =
        $packageStmt->get_result();

    $package =
        $packageResult->fetch_assoc();

    $packageStmt->close();

    /*
    |--------------------------------------------------------------------------
    | PACKAGE NOT FOUND
    |--------------------------------------------------------------------------
    */

    if (!$package) {

        http_response_code(404);

        echo json_encode([
            "status" => false,
            "message" =>
                "Active catering package not found.",
            "package_id" =>
                $packageId
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | FETCH PACKAGE ITEMS
    |--------------------------------------------------------------------------
    */

    $packageItems = [];

    $itemsSql = "
        SELECT
            cpi.food_item_id,
            cpi.quantity,

            fi.name AS food_item_name,
            fi.description AS food_item_description,
            fi.image_path AS food_item_image,
            fi.food_type,
            fi.status AS food_item_status,

            fc.id AS food_category_id,
            fc.name AS food_category_name,
            fc.description AS food_category_description,
            fc.status AS food_category_status

        FROM catering_package_items cpi

        INNER JOIN food_items fi
            ON fi.id = cpi.food_item_id

        LEFT JOIN food_categories fc
            ON fc.id = fi.food_category_id

        WHERE cpi.package_id = ?

        ORDER BY
            fc.name ASC,
            fi.name ASC
    ";

    $itemsStmt =
        $conn->prepare($itemsSql);

    if (!$itemsStmt) {

        throw new Exception(
            "Failed to prepare catering package items query."
        );
    }

    $itemsStmt->bind_param(
        "i",
        $packageId
    );

    if (!$itemsStmt->execute()) {

        $error =
            $itemsStmt->error;

        $itemsStmt->close();

        throw new Exception(
            "Failed to fetch catering package items: " .
            $error
        );
    }

    $itemsResult =
        $itemsStmt->get_result();

    while (
        $item =
        $itemsResult->fetch_assoc()
    ) {

        /*
        |--------------------------------------------------------------------------
        | FOOD ITEM RESPONSE
        |--------------------------------------------------------------------------
        */

        $packageItems[] = [

            "food_item_id" =>
                (int)$item['food_item_id'],

            "food_item_name" =>
                $item['food_item_name'],

            "food_item_description" =>
                $item['food_item_description'],

            "food_type" =>
                $item['food_type'],

            "food_item_image" =>
                $item['food_item_image'],

            "quantity" =>
                (int)$item['quantity'],

            "category" =>
                $item['food_category_id'] !== null
                    ? [
                        "id" =>
                            (int)$item['food_category_id'],

                        "name" =>
                            $item['food_category_name'],

                        "description" =>
                            $item['food_category_description']
                    ]
                    : null
        ];
    }

    $itemsStmt->close();

    /*
    |--------------------------------------------------------------------------
    | ITEM COUNT
    |--------------------------------------------------------------------------
    */

    $itemCount =
        count($packageItems);

    /*
    |--------------------------------------------------------------------------
    | LEAD REFERENCE COUNT
    |--------------------------------------------------------------------------
    |
    | Existing catering leads are not deleted when a package
    | is deleted. Their package reference becomes NULL.
    |
    */

    $leadCountSql = "
        SELECT COUNT(*) AS total
        FROM catering_lead_details
        WHERE catering_package_id = ?
    ";

    $leadCountStmt =
        $conn->prepare($leadCountSql);

    if (!$leadCountStmt) {

        throw new Exception(
            "Failed to prepare lead reference count query."
        );
    }

    $leadCountStmt->bind_param(
        "i",
        $packageId
    );

    if (!$leadCountStmt->execute()) {

        $error =
            $leadCountStmt->error;

        $leadCountStmt->close();

        throw new Exception(
            "Failed to fetch package lead reference count: " .
            $error
        );
    }

    $leadCountResult =
        $leadCountStmt->get_result();

    $leadCountRow =
        $leadCountResult->fetch_assoc();

    $leadCountStmt->close();

    $leadReferenceCount =
        (int)($leadCountRow['total'] ?? 0);

    /*
    |--------------------------------------------------------------------------
    | SUCCESS RESPONSE
    |--------------------------------------------------------------------------
    */

    http_response_code(200);

    echo json_encode([

        "status" => true,

        "message" =>
            "Catering package fetched successfully.",

        "data" => [

            /*
            |--------------------------------------------------------------------------
            | PACKAGE DETAILS
            |--------------------------------------------------------------------------
            */

            "id" =>
                (int)$package['id'],

            "name" =>
                $package['name'],

            "description" =>
                $package['description'],

            "rate" =>
                (float)$package['rate'],

            "rate_unit" =>
                $package['rate_unit'],

            "gst_rate" =>
                (float)$package['gst_rate'],

            "price_includes_gst" =>
                (bool)$package['price_includes_gst'],

            "minimum_quantity" =>
                (int)$package['minimum_quantity'],

            "image_path" =>
                $package['image_path'],

            "status" =>
                $package['status'],

            /*
            |--------------------------------------------------------------------------
            | COUNTS
            |--------------------------------------------------------------------------
            */

            "item_count" =>
                $itemCount,

            "lead_reference_count" =>
                $leadReferenceCount,

            /*
            |--------------------------------------------------------------------------
            | FOOD ITEMS
            |--------------------------------------------------------------------------
            */

            "items" =>
                $packageItems,

            /*
            |--------------------------------------------------------------------------
            | TIMESTAMPS
            |--------------------------------------------------------------------------
            */

            "created_at" =>
                $package['created_at'],

            "updated_at" =>
                $package['updated_at']
        ]
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        "status" => false,
        "message" =>
            "Failed to fetch catering package.",
        "error" =>
            $e->getMessage()
    ]);
}