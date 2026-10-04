<?php

/*
|--------------------------------------------------------------------------
| CORS Headers
|--------------------------------------------------------------------------
*/

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");


/*
|--------------------------------------------------------------------------
| Handle OPTIONS Preflight
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {

    http_response_code(200);
    exit;
}


/*
|--------------------------------------------------------------------------
| Request Method
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
| Include Database & Middleware
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/middleware.php';


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
|
| Admin + Staff can create amenities.
|
*/

$user = requireRole([
    'admin',
    'staff'
]);


/*
|--------------------------------------------------------------------------
| Helper Function - Error Response
|--------------------------------------------------------------------------
*/

function sendError($httpCode, $message, $data = null)
{
    http_response_code($httpCode);

    $response = [
        'status' => false,
        'message' => $message
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode(
        $response,
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Helper Function - String Validation
|--------------------------------------------------------------------------
*/

function getStringValue($value)
{
    if (
        is_array($value) ||
        is_object($value)
    ) {
        return null;
    }

    return trim((string) $value);
}


/*
|--------------------------------------------------------------------------
| Read JSON Request Body
|--------------------------------------------------------------------------
*/

$rawInput =
    file_get_contents('php://input');


if (
    $rawInput === false ||
    trim($rawInput) === ''
) {

    sendError(
        400,
        'Request body is required.'
    );
}


/*
|--------------------------------------------------------------------------
| Decode JSON
|--------------------------------------------------------------------------
*/

$data =
    json_decode(
        $rawInput,
        true
    );


if (
    json_last_error() !== JSON_ERROR_NONE
) {

    sendError(
        400,
        'Invalid JSON request body.'
    );
}


/*
|--------------------------------------------------------------------------
| Request Body Must Be Object
|--------------------------------------------------------------------------
*/

if (
    !is_array($data)
) {

    sendError(
        400,
        'Request body must be a JSON object.'
    );
}


/*
|--------------------------------------------------------------------------
| Allowed Fields
|--------------------------------------------------------------------------
*/

$allowedFields = [
    'name',
    'icon',
    'status'
];


/*
|--------------------------------------------------------------------------
| Reject Unknown Fields
|--------------------------------------------------------------------------
*/

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

        sendError(
            422,
            "Unknown field: {$key}."
        );
    }
}


/*
|--------------------------------------------------------------------------
| Validate Name - Required
|--------------------------------------------------------------------------
*/

if (
    !array_key_exists(
        'name',
        $data
    )
) {

    sendError(
        422,
        'name is required.'
    );
}


$name =
    getStringValue(
        $data['name']
    );


if (
    $name === null
) {

    sendError(
        422,
        'name must be a valid string.'
    );
}


/*
|--------------------------------------------------------------------------
| Validate Name - Empty
|--------------------------------------------------------------------------
*/

if (
    $name === ''
) {

    sendError(
        422,
        'name cannot be empty.'
    );
}


/*
|--------------------------------------------------------------------------
| Validate Name Length
|--------------------------------------------------------------------------
|
| DB: varchar(100)
|
*/

$nameLength =
    strlen($name);


if (
    $nameLength < 2
) {

    sendError(
        422,
        'name must contain at least 2 characters.'
    );
}


if (
    $nameLength > 100
) {

    sendError(
        422,
        'name cannot exceed 100 characters.'
    );
}


/*
|--------------------------------------------------------------------------
| Validate Name Characters
|--------------------------------------------------------------------------
|
| Allows:
| Letters
| Numbers
| Single spaces
| Hyphen
| Ampersand
| Parentheses
| Slash
| Dot
| Comma
|
*/

if (
    !preg_match(
        '/^[A-Za-z0-9]+(?:[ A-Za-z0-9&().,\/-]*[A-Za-z0-9)])?$/',
        $name
    )
) {

    sendError(
        422,
        'name contains invalid characters.'
    );
}


/*
|--------------------------------------------------------------------------
| Prevent Multiple Consecutive Spaces
|--------------------------------------------------------------------------
*/

if (
    preg_match(
        '/\s{2,}/',
        $name
    )
) {

    sendError(
        422,
        'name cannot contain multiple consecutive spaces.'
    );
}


/*
|--------------------------------------------------------------------------
| Validate Icon
|--------------------------------------------------------------------------
|
| Optional
|
| DB: varchar(150)
|
*/

$icon = null;


if (
    array_key_exists(
        'icon',
        $data
    )
) {

    $icon =
        getStringValue(
            $data['icon']
        );


    if (
        $icon === null
    ) {

        sendError(
            422,
            'icon must be a valid string.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Empty Icon
    |--------------------------------------------------------------------------
    */

    if (
        $icon === ''
    ) {

        $icon = null;
    }


    /*
    |--------------------------------------------------------------------------
    | Icon Length
    |--------------------------------------------------------------------------
    */

    if (
        $icon !== null &&
        strlen($icon) > 150
    ) {

        sendError(
            422,
            'icon cannot exceed 150 characters.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Icon Character Validation
    |--------------------------------------------------------------------------
    |
    | Examples:
    |
    | wifi
    | air-conditioner
    | fa-solid fa-wifi
    | lucide-wifi
    | hotel-wifi
    |
    */

    if (
        $icon !== null &&
        !preg_match(
            '/^[A-Za-z0-9][A-Za-z0-9 _:.\/-]*$/',
            $icon
        )
    ) {

        sendError(
            422,
            'icon contains invalid characters.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Validate Status
|--------------------------------------------------------------------------
|
| Actual DB enum:
|
| active
| inactive
|
*/

$status = 'active';


if (
    array_key_exists(
        'status',
        $data
    )
) {

    $status =
        getStringValue(
            $data['status']
        );


    if (
        $status === null ||
        $status === ''
    ) {

        sendError(
            422,
            'status is required when provided.'
        );
    }


    if (
        !in_array(
            $status,
            [
                'active',
                'inactive'
            ],
            true
        )
    ) {

        sendError(
            422,
            'status must be either active or inactive.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Normalize Name For Duplicate Check
|--------------------------------------------------------------------------
|
| Duplicate check is case-insensitive.
|
*/

$normalizedName =
    strtolower(
        preg_replace(
            '/\s+/',
            ' ',
            $name
        )
    );


/*
|--------------------------------------------------------------------------
| Check Duplicate Amenity
|--------------------------------------------------------------------------
*/

$duplicateStmt =
    $conn->prepare("
        SELECT
            id,
            name,
            icon,
            status,
            created_at

        FROM amenities

        WHERE LOWER(TRIM(name)) = ?

        LIMIT 1
    ");


if (!$duplicateStmt) {

    sendError(
        500,
        'Failed to prepare duplicate amenity validation query.'
    );
}


$duplicateStmt->bind_param(
    's',
    $normalizedName
);


if (
    !$duplicateStmt->execute()
) {

    $duplicateStmt->close();

    sendError(
        500,
        'Failed to check duplicate amenity.'
    );
}


$duplicateResult =
    $duplicateStmt->get_result();


if (
    $duplicateResult->num_rows > 0
) {

    $duplicateAmenity =
        $duplicateResult->fetch_assoc();


    $duplicateStmt->close();


    sendError(
        409,
        'An amenity with this name already exists.',
        [
            'existing_amenity' => [

                'id' =>
                    (int) $duplicateAmenity['id'],

                'name' =>
                    $duplicateAmenity['name'],

                'icon' =>
                    $duplicateAmenity['icon'],

                'status' =>
                    $duplicateAmenity['status'],

                'created_at' =>
                    $duplicateAmenity['created_at']
            ]
        ]
    );
}


$duplicateStmt->close();


/*
|--------------------------------------------------------------------------
| Insert Amenity
|--------------------------------------------------------------------------
|
| Actual columns:
|
| name
| icon
| status
|
| created_at is automatic.
|
*/

$insertStmt =
    $conn->prepare("
        INSERT INTO amenities
        (
            name,
            icon,
            status
        )
        VALUES
        (
            ?,
            ?,
            ?
        )
    ");


if (!$insertStmt) {

    sendError(
        500,
        'Failed to prepare amenity insert query.'
    );
}


/*
|--------------------------------------------------------------------------
| Bind Parameters
|--------------------------------------------------------------------------
*/

$insertStmt->bind_param(
    'sss',
    $name,
    $icon,
    $status
);


/*
|--------------------------------------------------------------------------
| Execute Insert
|--------------------------------------------------------------------------
*/

if (
    !$insertStmt->execute()
) {

    $errorCode =
        $insertStmt->errno;

    $errorMessage =
        $insertStmt->error;


    $insertStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Duplicate Entry Safety
    |--------------------------------------------------------------------------
    */

    if (
        $errorCode === 1062
    ) {

        sendError(
            409,
            'An amenity with this name already exists.'
        );
    }


    error_log(
        '[AMENITY CREATE] Database Error ' .
        $errorCode .
        ': ' .
        $errorMessage
    );


    sendError(
        500,
        'Failed to create amenity.'
    );
}


/*
|--------------------------------------------------------------------------
| Created Amenity ID
|--------------------------------------------------------------------------
*/

$amenityId =
    (int) $conn->insert_id;


$insertStmt->close();


if (
    $amenityId <= 0
) {

    sendError(
        500,
        'Amenity was not created successfully.'
    );
}


/*
|--------------------------------------------------------------------------
| Fetch Created Amenity
|--------------------------------------------------------------------------
*/

$createdStmt =
    $conn->prepare("
        SELECT

            id,
            name,
            icon,
            status,
            created_at

        FROM amenities

        WHERE id = ?

        LIMIT 1
    ");


if (!$createdStmt) {

    sendError(
        500,
        'Amenity was created, but failed to fetch the created amenity.'
    );
}


$createdStmt->bind_param(
    'i',
    $amenityId
);


if (
    !$createdStmt->execute()
) {

    $createdStmt->close();

    sendError(
        500,
        'Amenity was created, but failed to fetch the created amenity.'
    );
}


$createdResult =
    $createdStmt->get_result();


if (
    $createdResult->num_rows === 0
) {

    $createdStmt->close();

    sendError(
        500,
        'Amenity was created, but the created record could not be found.'
    );
}


$createdAmenity =
    $createdResult->fetch_assoc();


$createdStmt->close();


/*
|--------------------------------------------------------------------------
| Success Response
|--------------------------------------------------------------------------
*/

http_response_code(201);


echo json_encode([

    'status' => true,

    'message' =>
        'Amenity created successfully.',

    'data' => [

        'amenity' => [

            'id' =>
                (int) $createdAmenity['id'],

            'name' =>
                $createdAmenity['name'],

            'icon' =>
                $createdAmenity['icon'],

            'status' =>
                $createdAmenity['status'],

            'created_at' =>
                $createdAmenity['created_at']
        ],

        'created_by' => [

            'user_id' =>
                (int) $user['sub'],

            'role' =>
                $user['role']
        ]
    ]

], JSON_UNESCAPED_UNICODE);

exit;