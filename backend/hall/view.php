<?php

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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

require_once __DIR__ . '/../config/database.php';


// =========================================================
// ALLOWED QUERY PARAMETERS
// =========================================================

$allowedParams = [
    'hall_id'
];

$unknownParams = array_diff(
    array_keys($_GET),
    $allowedParams
);

if (!empty($unknownParams)) {
    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' => 'Unknown query parameter(s) provided.',
        'unknown_parameters' => array_values($unknownParams)
    ]);

    exit;
}


// =========================================================
// HALL ID VALIDATION
// =========================================================

if (
    !array_key_exists('hall_id', $_GET) ||
    $_GET['hall_id'] === '' ||
    $_GET['hall_id'] === null
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'hall_id is required.'
    ]);

    exit;
}

if (
    filter_var(
        $_GET['hall_id'],
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

$hallId = (int) $_GET['hall_id'];


// =========================================================
// FETCH HALL
// =========================================================

$hallSql = "
    SELECT
        h.id,
        h.hall_type_id,

        ht.name AS hall_type_name,
        ht.slug AS hall_type_slug,
        ht.description AS hall_type_description,
        ht.status AS hall_type_status,

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

$hallStmt = $conn->prepare($hallSql);

if (!$hallStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare hall query.'
    ]);

    exit;
}

$hallStmt->bind_param(
    'i',
    $hallId
);

if (!$hallStmt->execute()) {

    $hallStmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to fetch hall.'
    ]);

    exit;
}

$hallResult =
    $hallStmt->get_result();

$hall =
    $hallResult->fetch_assoc();

$hallStmt->close();


// =========================================================
// HALL NOT FOUND
// =========================================================

if (!$hall) {
    http_response_code(404);

    echo json_encode([
        'status' => false,
        'message' => 'Hall not found.',
        'hall_id' => $hallId
    ]);

    exit;
}


// =========================================================
// HALL TYPE VALIDATION
// =========================================================
//
// Normally hall_type_id should always point to an existing
// hall type. This check protects the API if the relationship
// is NULL or the related record is unavailable.
//
// =========================================================

$hallType = null;

if ($hall['hall_type_id'] !== null) {

    $hallType = [
        'id' =>
            (int) $hall['hall_type_id'],

        'name' =>
            $hall['hall_type_name'],

        'slug' =>
            $hall['hall_type_slug'],

        'description' =>
            $hall['hall_type_description'],

        'status' =>
            $hall['hall_type_status']
    ];
}


// =========================================================
// FETCH HALL IMAGES
// =========================================================

$images = [];

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

$imageStmt = $conn->prepare($imageSql);

if (!$imageStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare hall images query.'
    ]);

    exit;
}

$imageStmt->bind_param(
    'i',
    $hallId
);

if (!$imageStmt->execute()) {

    $imageStmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to fetch hall images.'
    ]);

    exit;
}

$imageResult =
    $imageStmt->get_result();

while (
    $imageRow =
        $imageResult->fetch_assoc()
) {

    $images[] = [
        'id' =>
            (int) $imageRow['id'],

        'image_path' =>
            $imageRow['image_path'],

        'alt_text' =>
            $imageRow['alt_text'],

        'is_primary' =>
            (bool) $imageRow['is_primary'],

        'sort_order' =>
            (int) $imageRow['sort_order'],

        'created_at' =>
            $imageRow['created_at']
    ];
}

$imageStmt->close();


// =========================================================
// FETCH HALL FACILITIES
// =========================================================

$facilities = [];

$facilitySql = "
    SELECT
        hf.id,
        hf.name,
        hf.status,
        hf.created_at

    FROM hall_facility_map hfm

    INNER JOIN hall_facilities hf
        ON hf.id = hfm.facility_id

    WHERE hfm.hall_id = ?

    ORDER BY
        hf.name ASC,
        hf.id ASC
";

$facilityStmt = $conn->prepare(
    $facilitySql
);

if (!$facilityStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare hall facilities query.'
    ]);

    exit;
}

$facilityStmt->bind_param(
    'i',
    $hallId
);

if (!$facilityStmt->execute()) {

    $facilityStmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to fetch hall facilities.'
    ]);

    exit;
}

$facilityResult =
    $facilityStmt->get_result();

while (
    $facilityRow =
        $facilityResult->fetch_assoc()
) {

    $facilities[] = [
        'id' =>
            (int) $facilityRow['id'],

        'name' =>
            $facilityRow['name'],

        'status' =>
            $facilityRow['status'],

        'created_at' =>
            $facilityRow['created_at']
    ];
}

$facilityStmt->close();


// =========================================================
// BUILD RESPONSE
// =========================================================

$responseData = [

    'id' =>
        (int) $hall['id'],

    'hall_type' =>
        $hallType,

    'name' =>
        $hall['name'],

    'slug' =>
        $hall['slug'],

    'description' =>
        $hall['description'],

    'capacity' =>
        (int) $hall['capacity'],

    'area' =>
        $hall['area'],

    'price_description' =>
        $hall['price_description'],

    'status' =>
        $hall['status'],

    'images' =>
        $images,

    'facilities' =>
        $facilities,

    'created_at' =>
        $hall['created_at'],

    'updated_at' =>
        $hall['updated_at']
];


// =========================================================
// SUCCESS RESPONSE
// =========================================================

http_response_code(200);

echo json_encode([
    'status' => true,
    'message' => 'Hall fetched successfully.',
    'data' => $responseData
]);

exit;