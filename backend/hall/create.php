<?php

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'status' => false,
        'message' => 'Only POST method is allowed.'
    ]);

    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/middleware.php';

$authUser = requireAdminOrStaff();


// ---------------------------------------------------------
// READ JSON BODY
// ---------------------------------------------------------

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


// ---------------------------------------------------------
// ALLOWED FIELDS
// ---------------------------------------------------------

$allowedFields = [
    'hall_type_id',
    'name',
    'description',
    'capacity',
    'area',
    'price_description',
    'status'
];

$unknownFields = array_diff(
    array_keys($data),
    $allowedFields
);

if (!empty($unknownFields)) {
    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' => 'Unknown field(s) provided.',
        'unknown_fields' => array_values($unknownFields)
    ]);

    exit;
}


// ---------------------------------------------------------
// HELPER VALIDATION FUNCTIONS
// ---------------------------------------------------------

function isPositiveInteger($value)
{
    return filter_var(
        $value,
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 1
            ]
        ]
    ) !== false;
}

function isValidDecimal($value)
{
    return preg_match(
        '/^\d+(?:\.\d{1,2})?$/',
        (string) $value
    );
}


// ---------------------------------------------------------
// HALL TYPE ID
// ---------------------------------------------------------

if (
    !array_key_exists('hall_type_id', $data) ||
    $data['hall_type_id'] === '' ||
    $data['hall_type_id'] === null
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'hall_type_id is required.'
    ]);

    exit;
}

$hallTypeId = $data['hall_type_id'];

if (!isPositiveInteger($hallTypeId)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'hall_type_id must be a valid positive integer.'
    ]);

    exit;
}

$hallTypeId = (int) $hallTypeId;


// ---------------------------------------------------------
// HALL TYPE VALIDATION
// ---------------------------------------------------------

$hallTypeSql = "
    SELECT
        id,
        name,
        slug,
        status
    FROM hall_types
    WHERE id = ?
    LIMIT 1
";

$hallTypeStmt = $conn->prepare($hallTypeSql);

if (!$hallTypeStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to validate hall type.'
    ]);

    exit;
}

$hallTypeStmt->bind_param(
    'i',
    $hallTypeId
);

$hallTypeStmt->execute();

$hallTypeResult = $hallTypeStmt->get_result();
$hallType = $hallTypeResult->fetch_assoc();

$hallTypeStmt->close();

if (!$hallType) {
    http_response_code(404);

    echo json_encode([
        'status' => false,
        'message' => 'Hall type not found.'
    ]);

    exit;
}

if ($hallType['status'] !== 'active') {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Selected hall type is inactive.'
    ]);

    exit;
}


// ---------------------------------------------------------
// NAME VALIDATION
// ---------------------------------------------------------

if (
    !array_key_exists('name', $data) ||
    !is_string($data['name'])
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'name is required.'
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

$nameLength = mb_strlen($name);

if ($nameLength < 2 || $nameLength > 150) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'name must be between 2 and 150 characters.'
    ]);

    exit;
}

// Allows:
// A-Z
// a-z
// 0-9
// &
// ( )
// .
// ,
// /
// -
if (!preg_match(
    '/^[A-Za-z0-9&().,\/-]+(?: [A-Za-z0-9&().,\/-]+)*$/',
    $name
)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'name contains invalid characters or spacing.'
    ]);

    exit;
}

if (preg_match('/\s{2,}/', $name)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'name must not contain consecutive spaces.'
    ]);

    exit;
}


// ---------------------------------------------------------
// DESCRIPTION VALIDATION
// ---------------------------------------------------------

$description = null;

if (array_key_exists('description', $data)) {

    if (
        $data['description'] !== null &&
        !is_string($data['description'])
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'description must be a string or null.'
        ]);

        exit;
    }

    if ($data['description'] !== null) {

        $description = trim($data['description']);

        if ($description === '') {
            $description = null;
        }

        if (
            $description !== null &&
            mb_strlen($description) > 5000
        ) {
            http_response_code(422);

            echo json_encode([
                'status' => false,
                'message' => 'description must not exceed 5000 characters.'
            ]);

            exit;
        }

        if (
            $description !== null &&
            preg_match('/\s{2,}/', $description)
        ) {
            http_response_code(422);

            echo json_encode([
                'status' => false,
                'message' => 'description must not contain consecutive spaces.'
            ]);

            exit;
        }
    }
}


// ---------------------------------------------------------
// CAPACITY VALIDATION
// ---------------------------------------------------------

if (
    !array_key_exists('capacity', $data) ||
    $data['capacity'] === '' ||
    $data['capacity'] === null
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'capacity is required.'
    ]);

    exit;
}

$capacity = $data['capacity'];

if (!isPositiveInteger($capacity)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'capacity must be a valid positive integer.'
    ]);

    exit;
}

$capacity = (int) $capacity;

// Practical upper limit
if ($capacity > 10000) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'capacity must not exceed 10000.'
    ]);

    exit;
}


// ---------------------------------------------------------
// AREA VALIDATION
// ---------------------------------------------------------

$area = null;

if (array_key_exists('area', $data)) {

    if (
        $data['area'] !== null &&
        !is_string($data['area'])
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'area must be a string or null.'
        ]);

        exit;
    }

    if ($data['area'] !== null) {

        $area = trim($data['area']);

        if ($area === '') {
            $area = null;
        }

        if (
            $area !== null &&
            mb_strlen($area) > 100
        ) {
            http_response_code(422);

            echo json_encode([
                'status' => false,
                'message' => 'area must not exceed 100 characters.'
            ]);

            exit;
        }

        if (
            $area !== null &&
            preg_match('/\s{2,}/', $area)
        ) {
            http_response_code(422);

            echo json_encode([
                'status' => false,
                'message' => 'area must not contain consecutive spaces.'
            ]);

            exit;
        }
    }
}


// ---------------------------------------------------------
// PRICE DESCRIPTION VALIDATION
// ---------------------------------------------------------

$priceDescription = null;

if (array_key_exists('price_description', $data)) {

    if (
        $data['price_description'] !== null &&
        !is_string($data['price_description'])
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'price_description must be a string or null.'
        ]);

        exit;
    }

    if ($data['price_description'] !== null) {

        $priceDescription = trim(
            $data['price_description']
        );

        if ($priceDescription === '') {
            $priceDescription = null;
        }

        if (
            $priceDescription !== null &&
            mb_strlen($priceDescription) > 255
        ) {
            http_response_code(422);

            echo json_encode([
                'status' => false,
                'message' => 'price_description must not exceed 255 characters.'
            ]);

            exit;
        }

        if (
            $priceDescription !== null &&
            preg_match('/\s{2,}/', $priceDescription)
        ) {
            http_response_code(422);

            echo json_encode([
                'status' => false,
                'message' => 'price_description must not contain consecutive spaces.'
            ]);

            exit;
        }
    }
}


// ---------------------------------------------------------
// STATUS VALIDATION
// ---------------------------------------------------------

$status = 'available';

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
        'available',
        'inactive',
        'maintenance'
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
}


// ---------------------------------------------------------
// SLUG GENERATION
// ---------------------------------------------------------

$slug = strtolower($name);

// Replace &
$slug = str_replace('&', 'and', $slug);

// Replace anything other than letters/numbers with -
$slug = preg_replace(
    '/[^a-z0-9]+/',
    '-',
    $slug
);

// Remove hyphens from beginning/end
$slug = trim($slug, '-');

// Limit to 180 characters
$slug = substr($slug, 0, 180);

// Remove trailing hyphen if substr cut it
$slug = trim($slug, '-');

if ($slug === '') {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to generate a valid slug from the hall name.'
    ]);

    exit;
}


// ---------------------------------------------------------
// DUPLICATE NAME CHECK
// ---------------------------------------------------------

$duplicateNameSql = "
    SELECT id
    FROM halls
    WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))
    LIMIT 1
";

$duplicateNameStmt = $conn->prepare(
    $duplicateNameSql
);

if (!$duplicateNameStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to validate hall name.'
    ]);

    exit;
}

$duplicateNameStmt->bind_param(
    's',
    $name
);

$duplicateNameStmt->execute();

$duplicateNameResult = $duplicateNameStmt->get_result();

$duplicateName = $duplicateNameResult->fetch_assoc();

$duplicateNameStmt->close();

if ($duplicateName) {
    http_response_code(409);

    echo json_encode([
        'status' => false,
        'message' => 'A hall with this name already exists.',
        'hall_id' => (int) $duplicateName['id']
    ]);

    exit;
}


// ---------------------------------------------------------
// DUPLICATE SLUG CHECK
// ---------------------------------------------------------

$duplicateSlugSql = "
    SELECT id
    FROM halls
    WHERE slug = ?
    LIMIT 1
";

$duplicateSlugStmt = $conn->prepare(
    $duplicateSlugSql
);

if (!$duplicateSlugStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to validate hall slug.'
    ]);

    exit;
}

$duplicateSlugStmt->bind_param(
    's',
    $slug
);

$duplicateSlugStmt->execute();

$duplicateSlugResult = $duplicateSlugStmt->get_result();

$duplicateSlug = $duplicateSlugResult->fetch_assoc();

$duplicateSlugStmt->close();

if ($duplicateSlug) {
    http_response_code(409);

    echo json_encode([
        'status' => false,
        'message' => 'A hall with the generated slug already exists.',
        'hall_id' => (int) $duplicateSlug['id'],
        'slug' => $slug
    ]);

    exit;
}


// ---------------------------------------------------------
// TRANSACTION
// ---------------------------------------------------------

$conn->begin_transaction();

try {

    // -----------------------------------------------------
    // INSERT HALL
    // -----------------------------------------------------

    $insertSql = "
        INSERT INTO halls
        (
            hall_type_id,
            name,
            slug,
            description,
            capacity,
            area,
            price_description,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $insertStmt = $conn->prepare($insertSql);

    if (!$insertStmt) {
        throw new Exception(
            'Failed to prepare hall creation query.'
        );
    }

    $insertStmt->bind_param(
        'isssisss',
        $hallTypeId,
        $name,
        $slug,
        $description,
        $capacity,
        $area,
        $priceDescription,
        $status
    );

    if (!$insertStmt->execute()) {

        $errorCode = $insertStmt->errno;
        $errorMessage = $insertStmt->error;

        $insertStmt->close();

        if ($errorCode === 1062) {
            throw new Exception(
                'A hall with the same name or slug already exists.',
                1062
            );
        }

        throw new Exception(
            'Failed to create hall: ' . $errorMessage,
            $errorCode
        );
    }

    $hallId = $insertStmt->insert_id;

    $insertStmt->close();


    // -----------------------------------------------------
    // FETCH CREATED HALL
    // -----------------------------------------------------

    $fetchSql = "
        SELECT
            h.id,
            h.hall_type_id,
            ht.name AS hall_type_name,
            ht.slug AS hall_type_slug,
            h.name,
            h.slug,
            h.description,
            h.capacity,
            h.area,
            h.price_description,
            h.status,
            h.created_at,
            h.updated_at
        FROM halls h
        LEFT JOIN hall_types ht
            ON ht.id = h.hall_type_id
        WHERE h.id = ?
        LIMIT 1
    ";

    $fetchStmt = $conn->prepare($fetchSql);

    if (!$fetchStmt) {
        throw new Exception(
            'Failed to fetch created hall.'
        );
    }

    $fetchStmt->bind_param(
        'i',
        $hallId
    );

    if (!$fetchStmt->execute()) {
        $fetchStmt->close();

        throw new Exception(
            'Failed to fetch created hall.'
        );
    }

    $fetchResult = $fetchStmt->get_result();

    $createdHall = $fetchResult->fetch_assoc();

    $fetchStmt->close();

    if (!$createdHall) {
        throw new Exception(
            'Hall was created but could not be retrieved.'
        );
    }


    // -----------------------------------------------------
    // COMMIT
    // -----------------------------------------------------

    $conn->commit();


    // -----------------------------------------------------
    // RESPONSE
    // -----------------------------------------------------

    http_response_code(201);

    echo json_encode([
        'status' => true,
        'message' => 'Hall created successfully.',
        'data' => [
            'id' => (int) $createdHall['id'],

            'hall_type' => [
                'id' => (int) $createdHall['hall_type_id'],
                'name' => $createdHall['hall_type_name'],
                'slug' => $createdHall['hall_type_slug']
            ],

            'name' => $createdHall['name'],
            'slug' => $createdHall['slug'],
            'description' => $createdHall['description'],

            'capacity' => (int) $createdHall['capacity'],

            'area' => $createdHall['area'],

            'price_description' =>
                $createdHall['price_description'],

            'status' => $createdHall['status'],

            'created_at' => $createdHall['created_at'],
            'updated_at' => $createdHall['updated_at']
        ],
        'created_by' => [
            'id' => (int) $authUser['sub'],
            'role' => $authUser['role']
        ]
    ]);

    exit;

} catch (Throwable $e) {

    // -----------------------------------------------------
    // ROLLBACK
    // -----------------------------------------------------

    $conn->rollback();


    // Duplicate key
    if ((int) $e->getCode() === 1062) {

        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' =>
                'A hall with the same name or slug already exists.'
        ]);

        exit;
    }


    // Foreign key error
    if ((int) $e->getCode() === 1452) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Invalid hall type. The selected hall type does not exist.'
        ]);

        exit;
    }


    // Generic server error
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to create hall.'
    ]);

    exit;
}