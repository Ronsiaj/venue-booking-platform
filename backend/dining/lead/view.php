<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/middleware.php';


/*
|--------------------------------------------------------------------------
| Method Validation
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);

    echo json_encode([
        'status' => false,
        'message' => 'Only GET method is allowed.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Admin / Staff Authentication
|--------------------------------------------------------------------------
*/

$authUser = requireAdminOrStaff();


/*
|--------------------------------------------------------------------------
| Allowed Query Parameters
|--------------------------------------------------------------------------
*/

$allowedParams = [
    'lead_id'
];

$unknownParams = array_diff(
    array_keys($_GET),
    $allowedParams
);

if (!empty($unknownParams)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Unknown query parameter(s) provided.',
        'unknown_parameters' => array_values(
            $unknownParams
        )
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| lead_id Required
|--------------------------------------------------------------------------
*/

if (!array_key_exists('lead_id', $_GET)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'lead_id is required.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Validate lead_id
|--------------------------------------------------------------------------
*/

if (
    !is_string($_GET['lead_id']) ||
    !preg_match('/^[0-9]+$/', $_GET['lead_id'])
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'lead_id must be a positive integer.'
    ]);

    exit;
}

$leadId = (int) $_GET['lead_id'];

if ($leadId <= 0) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'lead_id must be greater than 0.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Fetch Main Lead
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
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

    WHERE l.id = ?
      AND l.domain = 'dining'

    LIMIT 1
");

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare dining lead query.'
    ]);

    exit;
}

$stmt->bind_param(
    "i",
    $leadId
);

if (!$stmt->execute()) {
    $stmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to fetch dining lead.'
    ]);

    exit;
}

$result = $stmt->get_result();

$lead = $result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| Lead Not Found
|--------------------------------------------------------------------------
*/

if (!$lead) {
    http_response_code(404);

    echo json_encode([
        'status' => false,
        'message' => 'Dining lead not found.',
        'lead_id' => $leadId
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Fetch Dining Lead Details
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        dld.lead_id,
        dld.dining_package_id,
        dld.meal_type,
        dld.dining_date,
        dld.dining_time,
        dld.guest_count,
        dld.other_requirements,

        dp.name AS package_name,
        dp.description AS package_description,
        dp.meal_type AS package_meal_type,
        dp.image_path AS package_image_path,
        dp.status AS package_status

    FROM dining_lead_details dld

    LEFT JOIN dining_packages dp
        ON dp.id = dld.dining_package_id

    WHERE dld.lead_id = ?

    LIMIT 1
");

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to prepare dining lead details query.'
    ]);

    exit;
}

$stmt->bind_param(
    "i",
    $leadId
);

if (!$stmt->execute()) {
    $stmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to fetch dining lead details.'
    ]);

    exit;
}

$result = $stmt->get_result();

$diningDetails = $result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| Package Details
|--------------------------------------------------------------------------
*/

$package = null;

if (
    $diningDetails !== null &&
    $diningDetails['dining_package_id'] !== null
) {

    $package = [
        'id' => (int) $diningDetails[
            'dining_package_id'
        ],

        'name' =>
            $diningDetails['package_name'],

        'description' =>
            $diningDetails['package_description'],

        'meal_type' =>
            $diningDetails['package_meal_type'],

        'image_path' =>
            $diningDetails['package_image_path'],

        'status' =>
            $diningDetails['package_status']
    ];
}


/*
|--------------------------------------------------------------------------
| Fetch Selected Package Items
|--------------------------------------------------------------------------
|
| This shows the food items that belong to the
| selected dining package.
|
*/

$packageItems = [];

if (
    $diningDetails !== null &&
    $diningDetails['dining_package_id'] !== null
) {

    $packageId = (int) $diningDetails[
        'dining_package_id'
    ];

    $stmt = $conn->prepare("
        SELECT
            dpi.food_item_id,
            dpi.quantity,

            fi.name AS food_item_name,
            fi.description AS food_item_description,
            fi.image_path AS food_item_image,
            fi.food_type,
            fi.status AS food_item_status,

            fc.id AS category_id,
            fc.name AS category_name,
            fc.description AS category_description,
            fc.status AS category_status

        FROM dining_package_items dpi

        INNER JOIN food_items fi
            ON fi.id = dpi.food_item_id

        LEFT JOIN food_categories fc
            ON fc.id = fi.food_category_id

        WHERE dpi.package_id = ?

        ORDER BY
            fi.name ASC,
            dpi.food_item_id ASC
    ");

    if (!$stmt) {
        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' =>
                'Failed to prepare package item query.'
        ]);

        exit;
    }

    $stmt->bind_param(
        "i",
        $packageId
    );

    if (!$stmt->execute()) {
        $stmt->close();

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' =>
                'Failed to fetch package items.'
        ]);

        exit;
    }

    $result = $stmt->get_result();

    while (
        $row = $result->fetch_assoc()
    ) {

        $packageItems[] = [
            'food_item_id' =>
                (int) $row['food_item_id'],

            'quantity' =>
                (int) $row['quantity'],

            'food_item' => [
                'name' =>
                    $row['food_item_name'],

                'description' =>
                    $row['food_item_description'],

                'image_path' =>
                    $row['food_item_image'],

                'food_type' =>
                    $row['food_type'],

                'status' =>
                    $row['food_item_status']
            ],

            'category' =>
                $row['category_id'] !== null
                    ? [
                        'id' =>
                            (int) $row['category_id'],

                        'name' =>
                            $row['category_name'],

                        'description' =>
                            $row['category_description'],

                        'status' =>
                            $row['category_status']
                    ]
                    : null
        ];
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Fetch Custom Food Items
|--------------------------------------------------------------------------
|
| These are items specifically requested by
| the customer for this dining lead.
|
*/

$customFoodItems = [];

$stmt = $conn->prepare("
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

        fc.id AS category_id,
        fc.name AS category_name,
        fc.description AS category_description,
        fc.status AS category_status

    FROM dining_lead_items dli

    INNER JOIN food_items fi
        ON fi.id = dli.food_item_id

    LEFT JOIN food_categories fc
        ON fc.id = fi.food_category_id

    WHERE dli.lead_id = ?

    ORDER BY
        dli.id ASC
");

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to prepare custom food item query.'
    ]);

    exit;
}

$stmt->bind_param(
    "i",
    $leadId
);

if (!$stmt->execute()) {
    $stmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to fetch custom food items.'
    ]);

    exit;
}

$result = $stmt->get_result();

while (
    $row = $result->fetch_assoc()
) {

    $customFoodItems[] = [
        'id' =>
            (int) $row['id'],

        'food_item_id' =>
            (int) $row['food_item_id'],

        'quantity' =>
            (int) $row['quantity'],

        'notes' =>
            $row['notes'],

        'food_item' => [
            'name' =>
                $row['food_item_name'],

            'description' =>
                $row['food_item_description'],

            'image_path' =>
                $row['food_item_image'],

            'food_type' =>
                $row['food_type'],

            'status' =>
                $row['food_item_status']
        ],

        'category' =>
            $row['category_id'] !== null
                ? [
                    'id' =>
                        (int) $row['category_id'],

                    'name' =>
                        $row['category_name'],

                    'description' =>
                        $row['category_description'],

                    'status' =>
                        $row['category_status']
                ]
                : null
    ];
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Format Dining Details
|--------------------------------------------------------------------------
*/

$formattedDiningDetails = null;

if ($diningDetails !== null) {

    $formattedDiningDetails = [
        'lead_id' =>
            (int) $diningDetails['lead_id'],

        'dining_package_id' =>
            $diningDetails['dining_package_id'] !== null
                ? (int) $diningDetails[
                    'dining_package_id'
                ]
                : null,

        'meal_type' =>
            $diningDetails['meal_type'],

        'dining_date' =>
            $diningDetails['dining_date'],

        'dining_time' =>
            $diningDetails['dining_time'],

        'guest_count' =>
            $diningDetails['guest_count'] !== null
                ? (int) $diningDetails['guest_count']
                : null,

        'other_requirements' =>
            $diningDetails['other_requirements']
    ];
}


/*
|--------------------------------------------------------------------------
| Format Assigned Admin
|--------------------------------------------------------------------------
*/

$assignedAdmin = null;

if ($lead['assigned_admin_id'] !== null) {

    $assignedAdmin = [
        'id' =>
            (int) $lead['assigned_admin_id'],

        'name' =>
            $lead['assigned_admin_name'],

        'email' =>
            $lead['assigned_admin_email'],

        'phone' =>
            $lead['assigned_admin_phone']
    ];
}


/*
|--------------------------------------------------------------------------
| Format Main Lead
|--------------------------------------------------------------------------
*/

$leadData = [
    'id' =>
        (int) $lead['id'],

    'lead_reference' =>
        $lead['lead_reference'],

    'domain' =>
        $lead['domain'],

    'customer' => [
        'name' =>
            $lead['customer_name'],

        'phone' =>
            $lead['customer_phone'],

        'email' =>
            $lead['customer_email']
    ],

    'event' => [
        'date' =>
            $lead['event_date'],

        'guest_count' =>
            $lead['guest_count'] !== null
                ? (int) $lead['guest_count']
                : null,

        'preferred_time' =>
            $lead['preferred_time'],

        'requirement' =>
            $lead['requirement']
    ],

    'status' =>
        $lead['status'],

    'estimated_amount' =>
        $lead['estimated_amount'] !== null
            ? (float) $lead['estimated_amount']
            : null,

    'converted_amount' =>
        $lead['converted_amount'] !== null
            ? (float) $lead['converted_amount']
            : null,

    'converted_at' =>
        $lead['converted_at'],

    'source' =>
        $lead['source'],

    'assigned_admin' =>
        $assignedAdmin,

    'created_at' =>
        $lead['created_at'],

    'updated_at' =>
        $lead['updated_at']
];


/*
|--------------------------------------------------------------------------
| Success Response
|--------------------------------------------------------------------------
*/

http_response_code(200);

echo json_encode([
    'status' => true,
    'message' =>
        'Dining lead fetched successfully.',

    'data' => [
        'lead' =>
            $leadData,

        'dining_details' =>
            $formattedDiningDetails,

        'selected_package' =>
            $package,

        'package_items' => [
            'items' =>
                $packageItems,

            'total_items' =>
                count($packageItems)
        ],

        'custom_food_items' => [
            'items' =>
                $customFoodItems,

            'total_items' =>
                count($customFoodItems)
        ]
    ]
]);

exit;