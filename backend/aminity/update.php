<?php

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/middleware.php';


/*
|--------------------------------------------------------------------------
| Method Validation
|--------------------------------------------------------------------------
*/

$method = $_SERVER['REQUEST_METHOD'];

if (!in_array($method, ['PUT', 'POST'], true)) {
    http_response_code(405);

    echo json_encode([
        'status' => false,
        'message' => 'Only PUT or POST method is allowed.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

$user = requireAdminOrStaff();


/*
|--------------------------------------------------------------------------
| Read JSON Body
|--------------------------------------------------------------------------
*/

$rawInput = file_get_contents('php://input');

if ($rawInput === false || trim($rawInput) === '') {
    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' => 'Request body is required.'
    ]);

    exit;
}

$data = json_decode($rawInput, true);

if (
    json_last_error() !== JSON_ERROR_NONE ||
    !is_array($data)
) {
    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' => 'Invalid JSON request body.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Allowed Fields
|--------------------------------------------------------------------------
*/

$allowedFields = [
    'amenity_id',
    'name',
    'icon',
    'status'
];


/*
|--------------------------------------------------------------------------
| Unknown Field Validation
|--------------------------------------------------------------------------
*/

$unknownFields = array_diff(
    array_keys($data),
    $allowedFields
);

if (!empty($unknownFields)) {
    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' => 'Unknown field(s) found in request.',
        'unknown_fields' => array_values($unknownFields)
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| amenity_id Validation
|--------------------------------------------------------------------------
*/

if (!array_key_exists('amenity_id', $data)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'amenity_id is required.'
    ]);

    exit;
}

$amenityId = $data['amenity_id'];

if (
    is_bool($amenityId) ||
    !is_int($amenityId) &&
    !is_string($amenityId)
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'amenity_id must be a positive integer.'
    ]);

    exit;
}

if (
    is_string($amenityId) &&
    !preg_match('/^[0-9]+$/', $amenityId)
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'amenity_id must be a positive integer.'
    ]);

    exit;
}

$amenityId = (int) $amenityId;

if ($amenityId <= 0) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'amenity_id must be greater than 0.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| At Least One Field Required
|--------------------------------------------------------------------------
*/

$updateFields = [
    'name',
    'icon',
    'status'
];

$hasUpdateField = false;

foreach ($updateFields as $field) {
    if (array_key_exists($field, $data)) {
        $hasUpdateField = true;
        break;
    }
}

if (!$hasUpdateField) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'At least one field must be provided for update.',
        'allowed_update_fields' => $updateFields
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Fetch Existing Amenity
|--------------------------------------------------------------------------
*/

$selectSql = "
    SELECT
        id,
        name,
        icon,
        status,
        created_at
    FROM amenities
    WHERE id = ?
    LIMIT 1
";

$selectStmt = $conn->prepare($selectSql);

if (!$selectStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare amenity query.'
    ]);

    exit;
}

$selectStmt->bind_param(
    'i',
    $amenityId
);

$selectStmt->execute();

$result = $selectStmt->get_result();

$existingAmenity = $result->fetch_assoc();

$selectStmt->close();

if (!$existingAmenity) {
    http_response_code(404);

    echo json_encode([
        'status' => false,
        'message' => 'Amenity not found.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Current Values
|--------------------------------------------------------------------------
*/

$currentName = $existingAmenity['name'];
$currentIcon = $existingAmenity['icon'];
$currentStatus = $existingAmenity['status'];


/*
|--------------------------------------------------------------------------
| Prepare New Values
|--------------------------------------------------------------------------
*/

$newName = $currentName;
$newIcon = $currentIcon;
$newStatus = $currentStatus;


/*
|--------------------------------------------------------------------------
| Name Validation
|--------------------------------------------------------------------------
*/

if (array_key_exists('name', $data)) {

    if (!is_string($data['name'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'name must be a string.'
        ]);

        exit;
    }

    $name = trim($data['name']);

    if ($name === '') {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'name cannot be empty.'
        ]);

        exit;
    }

    if (mb_strlen($name) < 2) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'name must contain at least 2 characters.'
        ]);

        exit;
    }

    if (mb_strlen($name) > 100) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'name cannot exceed 100 characters.'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Name Character Validation
    |--------------------------------------------------------------------------
    |
    | Allows:
    | Letters
    | Numbers
    | Space
    | Hyphen
    | &
    | Parentheses
    | Slash
    | Dot
    | Comma
    |
    */

    if (
        !preg_match(
            '/^[A-Za-z0-9&().,\/-]+(?: [A-Za-z0-9&().,\/-]+)*$/',
            $name
        )
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'name contains invalid characters. Only letters, numbers, spaces, hyphen, &, parentheses, slash, dot and comma are allowed.'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Multiple Space Validation
    |--------------------------------------------------------------------------
    */

    if (preg_match('/\s{2,}/', $name)) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'name cannot contain multiple consecutive spaces.'
        ]);

        exit;
    }

    $newName = $name;
}


/*
|--------------------------------------------------------------------------
| Icon Validation
|--------------------------------------------------------------------------
*/

if (array_key_exists('icon', $data)) {

    /*
    |--------------------------------------------------------------------------
    | Allow NULL / Empty Icon
    |--------------------------------------------------------------------------
    */

    if ($data['icon'] === null) {
        $newIcon = null;
    } else {

        if (!is_string($data['icon'])) {
            http_response_code(422);

            echo json_encode([
                'status' => false,
                'message' => 'icon must be a string or null.'
            ]);

            exit;
        }

        $icon = trim($data['icon']);

        /*
        |--------------------------------------------------------------------------
        | Empty String
        |--------------------------------------------------------------------------
        */

        if ($icon === '') {
            $newIcon = null;
        } else {

            if (mb_strlen($icon) > 150) {
                http_response_code(422);

                echo json_encode([
                    'status' => false,
                    'message' => 'icon cannot exceed 150 characters.'
                ]);

                exit;
            }

            /*
            |--------------------------------------------------------------------------
            | Icon Character Validation
            |--------------------------------------------------------------------------
            */

            if (
                !preg_match(
                    '/^[A-Za-z0-9][A-Za-z0-9 _:.\/-]*$/',
                    $icon
                )
            ) {
                http_response_code(422);

                echo json_encode([
                    'status' => false,
                    'message' =>
                        'icon contains invalid characters.'
                ]);

                exit;
            }

            $newIcon = $icon;
        }
    }
}


/*
|--------------------------------------------------------------------------
| Status Validation
|--------------------------------------------------------------------------
*/

if (array_key_exists('status', $data)) {

    if (!is_string($data['status'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'status must be a string.'
        ]);

        exit;
    }

    $status = strtolower(trim($data['status']));

    $allowedStatuses = [
        'active',
        'inactive'
    ];

    if (!in_array($status, $allowedStatuses, true)) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Invalid status.',
            'allowed_statuses' => $allowedStatuses
        ]);

        exit;
    }

    $newStatus = $status;
}


/*
|--------------------------------------------------------------------------
| Check If Any Actual Change Exists
|--------------------------------------------------------------------------
*/

$nameChanged =
    $newName !== $currentName;

$iconChanged =
    $newIcon !== $currentIcon;

$statusChanged =
    $newStatus !== $currentStatus;

if (
    !$nameChanged &&
    !$iconChanged &&
    !$statusChanged
) {
    http_response_code(200);

    echo json_encode([
        'status' => true,
        'message' => 'No changes detected.',
        'data' => [
            'id' => (int) $existingAmenity['id'],
            'name' => $existingAmenity['name'],
            'icon' => $existingAmenity['icon'],
            'status' => $existingAmenity['status'],
            'created_at' => $existingAmenity['created_at']
        ]
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Duplicate Name Validation
|--------------------------------------------------------------------------
|
| Case-insensitive duplicate check.
|
*/

if ($nameChanged) {

    $duplicateSql = "
        SELECT id
        FROM amenities
        WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))
        AND id != ?
        LIMIT 1
    ";

    $duplicateStmt = $conn->prepare($duplicateSql);

    if (!$duplicateStmt) {
        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Failed to prepare duplicate validation query.'
        ]);

        exit;
    }

    $duplicateStmt->bind_param(
        'si',
        $newName,
        $amenityId
    );

    $duplicateStmt->execute();

    $duplicateResult =
        $duplicateStmt->get_result();

    $duplicateAmenity =
        $duplicateResult->fetch_assoc();

    $duplicateStmt->close();

    if ($duplicateAmenity) {
        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' => 'Another amenity with the same name already exists.'
        ]);

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Build Dynamic UPDATE Query
|--------------------------------------------------------------------------
*/

$setParts = [];
$bindTypes = '';
$bindValues = [];


/*
|--------------------------------------------------------------------------
| Name
|--------------------------------------------------------------------------
*/

if ($nameChanged) {

    $setParts[] = 'name = ?';
    $bindTypes .= 's';
    $bindValues[] = $newName;
}


/*
|--------------------------------------------------------------------------
| Icon
|--------------------------------------------------------------------------
*/

if ($iconChanged) {

    $setParts[] = 'icon = ?';
    $bindTypes .= 's';
    $bindValues[] = $newIcon;
}


/*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
*/

if ($statusChanged) {

    $setParts[] = 'status = ?';
    $bindTypes .= 's';
    $bindValues[] = $newStatus;
}


/*
|--------------------------------------------------------------------------
| Safety Check
|--------------------------------------------------------------------------
*/

if (empty($setParts)) {
    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' => 'No valid fields available for update.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Add WHERE Parameter
|--------------------------------------------------------------------------
*/

$bindTypes .= 'i';
$bindValues[] = $amenityId;

$updateSql = "
    UPDATE amenities
    SET " . implode(', ', $setParts) . "
    WHERE id = ?
    LIMIT 1
";


/*
|--------------------------------------------------------------------------
| Prepare Update
|--------------------------------------------------------------------------
*/

$updateStmt = $conn->prepare($updateSql);

if (!$updateStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare amenity update query.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Dynamic bind_param
|--------------------------------------------------------------------------
*/

$bindParams = [];
$bindParams[] = $bindTypes;

foreach ($bindValues as $key => $value) {
    $bindParams[] = &$bindValues[$key];
}

call_user_func_array(
    [$updateStmt, 'bind_param'],
    $bindParams
);


/*
|--------------------------------------------------------------------------
| Execute Update
|--------------------------------------------------------------------------
*/

if (!$updateStmt->execute()) {

    $errorNumber = $updateStmt->errno;

    $updateStmt->close();

    /*
    |--------------------------------------------------------------------------
    | Duplicate Key Error
    |--------------------------------------------------------------------------
    */

    if ($errorNumber === 1062) {
        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' => 'Another amenity with the same name already exists.'
        ]);

        exit;
    }

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to update amenity.'
    ]);

    exit;
}

$affectedRows = $updateStmt->affected_rows;

$updateStmt->close();


/*
|--------------------------------------------------------------------------
| Fetch Updated Amenity
|--------------------------------------------------------------------------
*/

$fetchSql = "
    SELECT
        id,
        name,
        icon,
        status,
        created_at
    FROM amenities
    WHERE id = ?
    LIMIT 1
";

$fetchStmt = $conn->prepare($fetchSql);

if (!$fetchStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Amenity updated, but failed to fetch updated data.'
    ]);

    exit;
}

$fetchStmt->bind_param(
    'i',
    $amenityId
);

$fetchStmt->execute();

$fetchResult =
    $fetchStmt->get_result();

$updatedAmenity =
    $fetchResult->fetch_assoc();

$fetchStmt->close();


/*
|--------------------------------------------------------------------------
| Final Response
|--------------------------------------------------------------------------
*/

if (!$updatedAmenity) {
    http_response_code(404);

    echo json_encode([
        'status' => false,
        'message' => 'Updated amenity could not be found.'
    ]);

    exit;
}


http_response_code(200);

echo json_encode([
    'status' => true,
    'message' => 'Amenity updated successfully.',
    'data' => [
        'id' => (int) $updatedAmenity['id'],
        'name' => $updatedAmenity['name'],
        'icon' => $updatedAmenity['icon'],
        'status' => $updatedAmenity['status'],
        'created_at' => $updatedAmenity['created_at']
    ],
    'updated_fields' => array_values(
        array_filter([
            $nameChanged ? 'name' : null,
            $iconChanged ? 'icon' : null,
            $statusChanged ? 'status' : null
        ])
    ),
    'affected_rows' => $affectedRows,
    'updated_by' => [
        'id' => (int) $user['sub'],
        'role' => $user['role']
    ]
]);

exit;