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

require_once __DIR__ . '/../../config/database.php';


/*
|--------------------------------------------------------------------------
| Helper Function
|--------------------------------------------------------------------------
*/

function sendResponse($statusCode, $message, $data = null)
{
    http_response_code($statusCode);

    $response = [
        'status' => $statusCode >= 200 && $statusCode < 300,
        'message' => $message
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode(
        $response,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Validate Query Parameters
|--------------------------------------------------------------------------
*/

$allowedQueryParams = [
    'food_item_id'
];

$unknownParams = array_diff(
    array_keys($_GET),
    $allowedQueryParams
);

if (!empty($unknownParams)) {

    sendResponse(
        422,
        'Unknown query parameter(s) provided.',
        [
            'unknown_parameters' =>
                array_values($unknownParams)
        ]
    );
}


/*
|--------------------------------------------------------------------------
| Food Item ID Required
|--------------------------------------------------------------------------
*/

if (!isset($_GET['food_item_id'])) {

    sendResponse(
        422,
        'Food item ID is required.'
    );
}


/*
|--------------------------------------------------------------------------
| Food Item ID Validation
|--------------------------------------------------------------------------
*/

$foodItemIdValue = $_GET['food_item_id'];


/*
|--------------------------------------------------------------------------
| Must Be String From Query Parameter
|--------------------------------------------------------------------------
*/

if (!is_string($foodItemIdValue)) {

    sendResponse(
        422,
        'Food item ID must be a valid positive integer.'
    );
}

$foodItemIdValue =
    trim($foodItemIdValue);


/*
|--------------------------------------------------------------------------
| Empty Validation
|--------------------------------------------------------------------------
*/

if ($foodItemIdValue === '') {

    sendResponse(
        422,
        'Food item ID cannot be empty.'
    );
}


/*
|--------------------------------------------------------------------------
| Integer Validation
|--------------------------------------------------------------------------
*/

if (
    !preg_match(
        '/^\d+$/',
        $foodItemIdValue
    )
) {

    sendResponse(
        422,
        'Food item ID must be a valid positive integer.'
    );
}


$foodItemId =
    (int) $foodItemIdValue;


/*
|--------------------------------------------------------------------------
| Positive Integer Validation
|--------------------------------------------------------------------------
*/

if ($foodItemId <= 0) {

    sendResponse(
        422,
        'Food item ID must be greater than 0.'
    );
}


/*
|--------------------------------------------------------------------------
| Fetch Food Item
|--------------------------------------------------------------------------
|
| Exact food_items fields:
|
| id
| food_category_id
| name
| description
| image_path
| food_type
| status
| created_at
| updated_at
|
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        fi.id,
        fi.food_category_id,

        fc.name AS category_name,
        fc.description AS category_description,
        fc.status AS category_status,

        fi.name,
        fi.description,
        fi.image_path,
        fi.food_type,
        fi.status,
        fi.created_at,
        fi.updated_at

    FROM food_items fi

    LEFT JOIN food_categories fc
        ON fc.id = fi.food_category_id

    WHERE fi.id = ?

    LIMIT 1
";


$stmt = $conn->prepare($sql);

if (!$stmt) {

    sendResponse(
        500,
        'Failed to prepare food item query.'
    );
}


/*
|--------------------------------------------------------------------------
| Bind Food Item ID
|--------------------------------------------------------------------------
*/

$stmt->bind_param(
    'i',
    $foodItemId
);


/*
|--------------------------------------------------------------------------
| Execute Query
|--------------------------------------------------------------------------
*/

if (!$stmt->execute()) {

    $stmt->close();

    sendResponse(
        500,
        'Failed to fetch food item.'
    );
}


/*
|--------------------------------------------------------------------------
| Get Result
|--------------------------------------------------------------------------
*/

$result =
    $stmt->get_result();

if (!$result) {

    $stmt->close();

    sendResponse(
        500,
        'Failed to process food item.'
    );
}


/*
|--------------------------------------------------------------------------
| Check Food Item Exists
|--------------------------------------------------------------------------
*/

if ($result->num_rows === 0) {

    $stmt->close();

    sendResponse(
        404,
        'Food item not found.'
    );
}


/*
|--------------------------------------------------------------------------
| Fetch Food Item
|--------------------------------------------------------------------------
*/

$row =
    $result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| Category Information
|--------------------------------------------------------------------------
*/

$category = null;

if (
    $row['food_category_id'] !== null
) {

    $category = [

        'id' =>
            (int) $row['food_category_id'],

        'name' =>
            $row['category_name'],

        'description' =>
            $row['category_description'],

        'status' =>
            $row['category_status']
    ];
}


/*
|--------------------------------------------------------------------------
| Final Response
|--------------------------------------------------------------------------
*/

sendResponse(
    200,
    'Food item fetched successfully.',
    [

        'food_item' => [

            'id' =>
                (int) $row['id'],

            'food_category_id' =>
                $row['food_category_id'] !== null
                    ? (int) $row['food_category_id']
                    : null,

            'category' =>
                $category,

            'name' =>
                $row['name'],

            'description' =>
                $row['description'],

            'image_path' =>
                $row['image_path'],

            'food_type' =>
                $row['food_type'],

            'status' =>
                $row['status'],

            'created_at' =>
                $row['created_at'],

            'updated_at' =>
                $row['updated_at']
        ]
    ]
);