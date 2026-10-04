<?php

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (
    $_SERVER['REQUEST_METHOD'] !== 'PUT' &&
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
    http_response_code(405);

    echo json_encode([
        'status' => false,
        'message' => 'Only PUT or POST method is allowed.'
    ]);

    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/middleware.php';

$authUser = requireAdminOrStaff();


// =========================================================
// READ JSON BODY
// =========================================================

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


// =========================================================
// ALLOWED FIELDS
// =========================================================

$allowedFields = [
    'hall_id',
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


// =========================================================
// HALL ID VALIDATION
// =========================================================

if (
    !array_key_exists('hall_id', $data) ||
    $data['hall_id'] === '' ||
    $data['hall_id'] === null
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'hall_id is required.'
    ]);

    exit;
}

$hallId = $data['hall_id'];

if (
    filter_var(
        $hallId,
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 1
            ]
        ]
    ) === false
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'hall_id must be a valid positive integer.'
    ]);

    exit;
}

$hallId = (int) $hallId;


// =========================================================
// AT LEAST ONE UPDATE FIELD
// =========================================================

$updateFieldsRequested = [
    'hall_type_id',
    'name',
    'description',
    'capacity',
    'area',
    'price_description',
    'status'
];

$hasUpdateField = false;

foreach ($updateFieldsRequested as $field) {
    if (array_key_exists($field, $data)) {
        $hasUpdateField = true;
        break;
    }
}

if (!$hasUpdateField) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'At least one field is required to update.'
    ]);

    exit;
}


// =========================================================
// FETCH CURRENT HALL
// =========================================================

$currentSql = "
    SELECT
        id,
        hall_type_id,
        name,
        slug,
        description,
        capacity,
        area,
        price_description,
        status,
        created_at,
        updated_at
    FROM halls
    WHERE id = ?
    LIMIT 1
";

$currentStmt = $conn->prepare($currentSql);

if (!$currentStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to fetch hall.'
    ]);

    exit;
}

$currentStmt->bind_param(
    'i',
    $hallId
);

$currentStmt->execute();

$currentResult = $currentStmt->get_result();

$currentHall = $currentResult->fetch_assoc();

$currentStmt->close();

if (!$currentHall) {
    http_response_code(404);

    echo json_encode([
        'status' => false,
        'message' => 'Hall not found.'
    ]);

    exit;
}


// =========================================================
// PREPARE CURRENT VALUES
// =========================================================

$hallTypeId = (int) $currentHall['hall_type_id'];

$name = $currentHall['name'];

$description = $currentHall['description'];

$capacity = (int) $currentHall['capacity'];

$area = $currentHall['area'];

$priceDescription = $currentHall['price_description'];

$status = $currentHall['status'];


// =========================================================
// HALL TYPE VALIDATION
// =========================================================

if (array_key_exists('hall_type_id', $data)) {

    if (
        $data['hall_type_id'] === '' ||
        $data['hall_type_id'] === null
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'hall_type_id cannot be empty.'
        ]);

        exit;
    }

    if (
        filter_var(
            $data['hall_type_id'],
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1
                ]
            ]
        ) === false
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'hall_type_id must be a valid positive integer.'
        ]);

        exit;
    }

    $hallTypeId = (int) $data['hall_type_id'];


    // -----------------------------------------------------
    // Check hall type
    // -----------------------------------------------------

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
}


// =========================================================
// NAME VALIDATION
// =========================================================

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

    $nameLength = mb_strlen($name);

    if (
        $nameLength < 2 ||
        $nameLength > 150
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'name must be between 2 and 150 characters.'
        ]);

        exit;
    }

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
}


// =========================================================
// DESCRIPTION VALIDATION
// =========================================================

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

    if ($data['description'] === null) {

        $description = null;

    } else {

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


// =========================================================
// CAPACITY VALIDATION
// =========================================================

if (array_key_exists('capacity', $data)) {

    if (
        $data['capacity'] === '' ||
        $data['capacity'] === null
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'capacity cannot be empty.'
        ]);

        exit;
    }

    if (
        filter_var(
            $data['capacity'],
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1
                ]
            ]
        ) === false
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'capacity must be a valid positive integer.'
        ]);

        exit;
    }

    $capacity = (int) $data['capacity'];

    if ($capacity > 10000) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'capacity must not exceed 10000.'
        ]);

        exit;
    }
}


// =========================================================
// AREA VALIDATION
// =========================================================

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

    if ($data['area'] === null) {

        $area = null;

    } else {

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


// =========================================================
// PRICE DESCRIPTION VALIDATION
// =========================================================

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

    if ($data['price_description'] === null) {

        $priceDescription = null;

    } else {

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
                'message' =>
                    'price_description must not exceed 255 characters.'
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
                'message' =>
                    'price_description must not contain consecutive spaces.'
            ]);

            exit;
        }
    }
}


// =========================================================
// STATUS VALIDATION
// =========================================================

if (array_key_exists('status', $data)) {

    if (!is_string($data['status'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'status must be a string.'
        ]);

        exit;
    }

    $status = strtolower(
        trim($data['status'])
    );

    $allowedStatuses = [
        'available',
        'inactive',
        'maintenance'
    ];

    if (!in_array(
        $status,
        $allowedStatuses,
        true
    )) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Invalid status.',
            'allowed_statuses' => $allowedStatuses
        ]);

        exit;
    }
}


// =========================================================
// DETECT ACTUAL CHANGES
// =========================================================

$changedFields = [];

if (
    array_key_exists('hall_type_id', $data) &&
    $hallTypeId !== (int) $currentHall['hall_type_id']
) {
    $changedFields[] = 'hall_type_id';
}

if (
    array_key_exists('name', $data) &&
    $name !== $currentHall['name']
) {
    $changedFields[] = 'name';
}

if (
    array_key_exists('description', $data) &&
    $description !== $currentHall['description']
) {
    $changedFields[] = 'description';
}

if (
    array_key_exists('capacity', $data) &&
    $capacity !== (int) $currentHall['capacity']
) {
    $changedFields[] = 'capacity';
}

if (
    array_key_exists('area', $data) &&
    $area !== $currentHall['area']
) {
    $changedFields[] = 'area';
}

if (
    array_key_exists('price_description', $data) &&
    $priceDescription !== $currentHall['price_description']
) {
    $changedFields[] = 'price_description';
}

if (
    array_key_exists('status', $data) &&
    $status !== $currentHall['status']
) {
    $changedFields[] = 'status';
}


// =========================================================
// NO ACTUAL CHANGE
// =========================================================

if (empty($changedFields)) {

    http_response_code(200);

    echo json_encode([
        'status' => true,
        'message' => 'No changes were made.',
        'data' => [
            'id' => (int) $currentHall['id'],
            'hall_type_id' => (int) $currentHall['hall_type_id'],
            'name' => $currentHall['name'],
            'slug' => $currentHall['slug'],
            'description' => $currentHall['description'],
            'capacity' => (int) $currentHall['capacity'],
            'area' => $currentHall['area'],
            'price_description' =>
                $currentHall['price_description'],
            'status' => $currentHall['status'],
            'created_at' => $currentHall['created_at'],
            'updated_at' => $currentHall['updated_at']
        ],
        'updated_fields' => [],
        'updated_by' => [
            'id' => (int) $authUser['sub'],
            'role' => $authUser['role']
        ]
    ]);

    exit;
}


// =========================================================
// SLUG GENERATION
// =========================================================

$slug = $currentHall['slug'];

if (in_array('name', $changedFields, true)) {

    $slug = strtolower($name);

    // &
    $slug = str_replace(
        '&',
        'and',
        $slug
    );

    // Anything except a-z / 0-9
    $slug = preg_replace(
        '/[^a-z0-9]+/',
        '-',
        $slug
    );

    $slug = trim(
        $slug,
        '-'
    );

    $slug = substr(
        $slug,
        0,
        180
    );

    $slug = trim(
        $slug,
        '-'
    );

    if ($slug === '') {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Unable to generate a valid slug from the hall name.'
        ]);

        exit;
    }
}


// =========================================================
// DUPLICATE NAME CHECK
// =========================================================

if (in_array('name', $changedFields, true)) {

    $duplicateNameSql = "
        SELECT id
        FROM halls
        WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))
        AND id <> ?
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
        'si',
        $name,
        $hallId
    );

    $duplicateNameStmt->execute();

    $duplicateNameResult =
        $duplicateNameStmt->get_result();

    $duplicateName =
        $duplicateNameResult->fetch_assoc();

    $duplicateNameStmt->close();

    if ($duplicateName) {

        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' =>
                'Another hall with this name already exists.',
            'hall_id' =>
                (int) $duplicateName['id']
        ]);

        exit;
    }
}


// =========================================================
// DUPLICATE SLUG CHECK
// =========================================================

if (in_array('name', $changedFields, true)) {

    $duplicateSlugSql = "
        SELECT id
        FROM halls
        WHERE slug = ?
        AND id <> ?
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
        'si',
        $slug,
        $hallId
    );

    $duplicateSlugStmt->execute();

    $duplicateSlugResult =
        $duplicateSlugStmt->get_result();

    $duplicateSlug =
        $duplicateSlugResult->fetch_assoc();

    $duplicateSlugStmt->close();

    if ($duplicateSlug) {

        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' =>
                'Another hall with the generated slug already exists.',
            'hall_id' =>
                (int) $duplicateSlug['id'],
            'slug' => $slug
        ]);

        exit;
    }
}


// =========================================================
// TRANSACTION
// =========================================================

$conn->begin_transaction();

try {

    // =====================================================
    // BUILD DYNAMIC UPDATE QUERY
    // =====================================================

    $setParts = [];
    $bindTypes = '';
    $bindValues = [];


    if (in_array('hall_type_id', $changedFields, true)) {

        $setParts[] = 'hall_type_id = ?';
        $bindTypes .= 'i';
        $bindValues[] = $hallTypeId;
    }


    if (in_array('name', $changedFields, true)) {

        $setParts[] = 'name = ?';
        $bindTypes .= 's';
        $bindValues[] = $name;

        $setParts[] = 'slug = ?';
        $bindTypes .= 's';
        $bindValues[] = $slug;
    }


    if (in_array('description', $changedFields, true)) {

        $setParts[] = 'description = ?';
        $bindTypes .= 's';
        $bindValues[] = $description;
    }


    if (in_array('capacity', $changedFields, true)) {

        $setParts[] = 'capacity = ?';
        $bindTypes .= 'i';
        $bindValues[] = $capacity;
    }


    if (in_array('area', $changedFields, true)) {

        $setParts[] = 'area = ?';
        $bindTypes .= 's';
        $bindValues[] = $area;
    }


    if (in_array('price_description', $changedFields, true)) {

        $setParts[] = 'price_description = ?';
        $bindTypes .= 's';
        $bindValues[] = $priceDescription;
    }


    if (in_array('status', $changedFields, true)) {

        $setParts[] = 'status = ?';
        $bindTypes .= 's';
        $bindValues[] = $status;
    }


    if (empty($setParts)) {
        throw new Exception(
            'No fields available for update.'
        );
    }


    // =====================================================
    // UPDATE QUERY
    // =====================================================

    $updateSql = "
        UPDATE halls
        SET " . implode(', ', $setParts) . "
        WHERE id = ?
    ";

    $bindTypes .= 'i';
    $bindValues[] = $hallId;

    $updateStmt = $conn->prepare($updateSql);

    if (!$updateStmt) {
        throw new Exception(
            'Failed to prepare hall update query.'
        );
    }


    // =====================================================
    // BIND DYNAMIC PARAMETERS
    // =====================================================

    $bindParams = [];
    $bindParams[] = $bindTypes;

    foreach ($bindValues as $key => $value) {
        $bindParams[] = &$bindValues[$key];
    }

    call_user_func_array(
        [$updateStmt, 'bind_param'],
        $bindParams
    );


    // =====================================================
    // EXECUTE
    // =====================================================

    if (!$updateStmt->execute()) {

        $errorCode = $updateStmt->errno;
        $errorMessage = $updateStmt->error;

        $updateStmt->close();

        if ($errorCode === 1062) {
            throw new Exception(
                'A hall with the same name or slug already exists.',
                1062
            );
        }

        if ($errorCode === 1452) {
            throw new Exception(
                'Invalid hall type.',
                1452
            );
        }

        throw new Exception(
            'Failed to update hall: ' . $errorMessage,
            $errorCode
        );
    }

    $affectedRows = $updateStmt->affected_rows;

    $updateStmt->close();


    // =====================================================
    // FETCH UPDATED HALL
    // =====================================================

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

    $fetchStmt = $conn->prepare(
        $fetchSql
    );

    if (!$fetchStmt) {
        throw new Exception(
            'Failed to fetch updated hall.'
        );
    }

    $fetchStmt->bind_param(
        'i',
        $hallId
    );

    if (!$fetchStmt->execute()) {

        $fetchStmt->close();

        throw new Exception(
            'Failed to fetch updated hall.'
        );
    }

    $fetchResult =
        $fetchStmt->get_result();

    $updatedHall =
        $fetchResult->fetch_assoc();

    $fetchStmt->close();

    if (!$updatedHall) {
        throw new Exception(
            'Hall was updated but could not be retrieved.'
        );
    }


    // =====================================================
    // COMMIT
    // =====================================================

    $conn->commit();


    // =====================================================
    // RESPONSE
    // =====================================================

    http_response_code(200);

    echo json_encode([
        'status' => true,
        'message' => 'Hall updated successfully.',

        'data' => [
            'id' =>
                (int) $updatedHall['id'],

            'hall_type' => [
                'id' =>
                    (int) $updatedHall['hall_type_id'],

                'name' =>
                    $updatedHall['hall_type_name'],

                'slug' =>
                    $updatedHall['hall_type_slug']
            ],

            'name' =>
                $updatedHall['name'],

            'slug' =>
                $updatedHall['slug'],

            'description' =>
                $updatedHall['description'],

            'capacity' =>
                (int) $updatedHall['capacity'],

            'area' =>
                $updatedHall['area'],

            'price_description' =>
                $updatedHall['price_description'],

            'status' =>
                $updatedHall['status'],

            'created_at' =>
                $updatedHall['created_at'],

            'updated_at' =>
                $updatedHall['updated_at']
        ],

        'updated_fields' =>
            array_values($changedFields),

        'affected_rows' =>
            (int) $affectedRows,

        'updated_by' => [
            'id' =>
                (int) $authUser['sub'],

            'role' =>
                $authUser['role']
        ]
    ]);

    exit;

} catch (Throwable $e) {

    // =====================================================
    // ROLLBACK
    // =====================================================

    $conn->rollback();


    // =====================================================
    // DUPLICATE
    // =====================================================

    if ((int) $e->getCode() === 1062) {

        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' =>
                'A hall with the same name or slug already exists.'
        ]);

        exit;
    }


    // =====================================================
    // FOREIGN KEY
    // =====================================================

    if ((int) $e->getCode() === 1452) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Invalid hall type. The selected hall type does not exist.'
        ]);

        exit;
    }


    // =====================================================
    // SERVER ERROR
    // =====================================================

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to update hall.'
    ]);

    exit;
}