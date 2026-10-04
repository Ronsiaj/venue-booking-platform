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
    'search',
    'hall_type_id',
    'status',
    'min_capacity',
    'max_capacity',
    'page',
    'limit',
    'sort_by',
    'sort_order'
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
// SEARCH VALIDATION
// =========================================================

$search = null;

if (array_key_exists('search', $_GET)) {

    if (!is_string($_GET['search'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'search must be a string.'
        ]);

        exit;
    }

    $search = trim($_GET['search']);

    if ($search === '') {
        $search = null;
    }

    if (
        $search !== null &&
        mb_strlen($search) > 150
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'search must not exceed 150 characters.'
        ]);

        exit;
    }
}


// =========================================================
// HALL TYPE ID VALIDATION
// =========================================================

$hallTypeId = null;

if (array_key_exists('hall_type_id', $_GET)) {

    if (
        $_GET['hall_type_id'] === '' ||
        $_GET['hall_type_id'] === null
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
            $_GET['hall_type_id'],
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
            'message' =>
                'hall_type_id must be a valid positive integer.'
        ]);

        exit;
    }

    $hallTypeId = (int) $_GET['hall_type_id'];


    // -----------------------------------------------------
    // Verify hall type exists
    // -----------------------------------------------------

    $hallTypeCheckSql = "
        SELECT
            id,
            name,
            status
        FROM hall_types
        WHERE id = ?
        LIMIT 1
    ";

    $hallTypeCheckStmt = $conn->prepare(
        $hallTypeCheckSql
    );

    if (!$hallTypeCheckStmt) {
        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Failed to validate hall type.'
        ]);

        exit;
    }

    $hallTypeCheckStmt->bind_param(
        'i',
        $hallTypeId
    );

    $hallTypeCheckStmt->execute();

    $hallTypeCheckResult =
        $hallTypeCheckStmt->get_result();

    $hallTypeCheck =
        $hallTypeCheckResult->fetch_assoc();

    $hallTypeCheckStmt->close();

    if (!$hallTypeCheck) {
        http_response_code(404);

        echo json_encode([
            'status' => false,
            'message' => 'Hall type not found.',
            'hall_type_id' => $hallTypeId
        ]);

        exit;
    }
}


// =========================================================
// STATUS VALIDATION
// =========================================================

$status = null;

if (array_key_exists('status', $_GET)) {

    if (!is_string($_GET['status'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'status must be a string.'
        ]);

        exit;
    }

    $status = strtolower(
        trim($_GET['status'])
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
// MIN CAPACITY
// =========================================================

$minCapacity = null;

if (array_key_exists('min_capacity', $_GET)) {

    if (
        filter_var(
            $_GET['min_capacity'],
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
            'message' =>
                'min_capacity must be a valid positive integer.'
        ]);

        exit;
    }

    $minCapacity = (int) $_GET['min_capacity'];

    if ($minCapacity > 10000) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'min_capacity must not exceed 10000.'
        ]);

        exit;
    }
}


// =========================================================
// MAX CAPACITY
// =========================================================

$maxCapacity = null;

if (array_key_exists('max_capacity', $_GET)) {

    if (
        filter_var(
            $_GET['max_capacity'],
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
            'message' =>
                'max_capacity must be a valid positive integer.'
        ]);

        exit;
    }

    $maxCapacity = (int) $_GET['max_capacity'];

    if ($maxCapacity > 10000) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'max_capacity must not exceed 10000.'
        ]);

        exit;
    }
}


// =========================================================
// CAPACITY RANGE VALIDATION
// =========================================================

if (
    $minCapacity !== null &&
    $maxCapacity !== null &&
    $minCapacity > $maxCapacity
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'min_capacity cannot be greater than max_capacity.'
    ]);

    exit;
}


// =========================================================
// PAGE VALIDATION
// =========================================================

$page = 1;

if (array_key_exists('page', $_GET)) {

    if (
        filter_var(
            $_GET['page'],
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
            'message' => 'page must be a positive integer.'
        ]);

        exit;
    }

    $page = (int) $_GET['page'];
}


// =========================================================
// LIMIT VALIDATION
// =========================================================

$limit = 20;

if (array_key_exists('limit', $_GET)) {

    if (
        filter_var(
            $_GET['limit'],
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
            'message' => 'limit must be a positive integer.'
        ]);

        exit;
    }

    $limit = (int) $_GET['limit'];

    if ($limit > 100) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'limit must not exceed 100.'
        ]);

        exit;
    }
}

$offset = ($page - 1) * $limit;


// =========================================================
// SORT BY VALIDATION
// =========================================================

$sortBy = 'id';

$allowedSortFields = [
    'id' => 'h.id',
    'name' => 'h.name',
    'slug' => 'h.slug',
    'hall_type_id' => 'h.hall_type_id',
    'hall_type_name' => 'ht.name',
    'capacity' => 'h.capacity',
    'area' => 'h.area',
    'status' => 'h.status',
    'created_at' => 'h.created_at',
    'updated_at' => 'h.updated_at'
];

if (array_key_exists('sort_by', $_GET)) {

    if (!is_string($_GET['sort_by'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'sort_by must be a string.'
        ]);

        exit;
    }

    $sortBy = strtolower(
        trim($_GET['sort_by'])
    );

    if (!array_key_exists(
        $sortBy,
        $allowedSortFields
    )) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Invalid sort_by value.',
            'allowed_sort_by' =>
                array_keys($allowedSortFields)
        ]);

        exit;
    }
}


// =========================================================
// SORT ORDER VALIDATION
// =========================================================

$sortOrder = 'DESC';

if (array_key_exists('sort_order', $_GET)) {

    if (!is_string($_GET['sort_order'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'sort_order must be a string.'
        ]);

        exit;
    }

    $sortOrder = strtoupper(
        trim($_GET['sort_order'])
    );

    if (!in_array(
        $sortOrder,
        ['ASC', 'DESC'],
        true
    )) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'sort_order must be ASC or DESC.'
        ]);

        exit;
    }
}


// =========================================================
// BUILD WHERE CONDITIONS
// =========================================================

$where = [];
$types = '';
$params = [];


// ---------------------------------------------------------
// SEARCH
// ---------------------------------------------------------

if ($search !== null) {

    $where[] = "
        (
            h.name LIKE ?
            OR h.slug LIKE ?
            OR h.description LIKE ?
            OR h.area LIKE ?
            OR h.price_description LIKE ?
            OR ht.name LIKE ?
            OR ht.slug LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    for ($i = 0; $i < 7; $i++) {
        $types .= 's';
        $params[] = $searchValue;
    }
}


// ---------------------------------------------------------
// HALL TYPE FILTER
// ---------------------------------------------------------

if ($hallTypeId !== null) {

    $where[] = 'h.hall_type_id = ?';

    $types .= 'i';

    $params[] = $hallTypeId;
}


// ---------------------------------------------------------
// STATUS FILTER
// ---------------------------------------------------------

if ($status !== null) {

    $where[] = 'h.status = ?';

    $types .= 's';

    $params[] = $status;
}


// ---------------------------------------------------------
// MIN CAPACITY
// ---------------------------------------------------------

if ($minCapacity !== null) {

    $where[] = 'h.capacity >= ?';

    $types .= 'i';

    $params[] = $minCapacity;
}


// ---------------------------------------------------------
// MAX CAPACITY
// ---------------------------------------------------------

if ($maxCapacity !== null) {

    $where[] = 'h.capacity <= ?';

    $types .= 'i';

    $params[] = $maxCapacity;
}


// =========================================================
// WHERE SQL
// =========================================================

$whereSql = '';

if (!empty($where)) {
    $whereSql =
        ' WHERE ' .
        implode(' AND ', $where);
}


// =========================================================
// COUNT QUERY
// =========================================================

$countSql = "
    SELECT COUNT(*) AS total
    FROM halls h
    LEFT JOIN hall_types ht
        ON ht.id = h.hall_type_id
    {$whereSql}
";

$countStmt = $conn->prepare($countSql);

if (!$countStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare count query.'
    ]);

    exit;
}


if ($types !== '') {

    $countBindParams = [];
    $countBindParams[] = $types;

    foreach ($params as $key => $value) {
        $countBindParams[] = &$params[$key];
    }

    call_user_func_array(
        [$countStmt, 'bind_param'],
        $countBindParams
    );
}


if (!$countStmt->execute()) {

    $countStmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to execute count query.'
    ]);

    exit;
}

$countResult =
    $countStmt->get_result();

$countRow =
    $countResult->fetch_assoc();

$countStmt->close();

$totalRecords =
    (int) ($countRow['total'] ?? 0);


// =========================================================
// PAGINATION CALCULATION
// =========================================================

$totalPages = $totalRecords > 0
    ? (int) ceil($totalRecords / $limit)
    : 0;


// =========================================================
// DATA QUERY
// =========================================================

$dataSql = "
    SELECT
        h.id,
        h.hall_type_id,

        ht.name AS hall_type_name,
        ht.slug AS hall_type_slug,
        ht.description AS hall_type_description,

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

    {$whereSql}

    ORDER BY {$allowedSortFields[$sortBy]} {$sortOrder}

    LIMIT ? OFFSET ?
";

$dataStmt = $conn->prepare($dataSql);

if (!$dataStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare data query.'
    ]);

    exit;
}


// =========================================================
// DATA QUERY BIND PARAMETERS
// =========================================================

$dataTypes = $types . 'ii';

$dataParams = $params;

$dataParams[] = $limit;
$dataParams[] = $offset;

$dataBindParams = [];
$dataBindParams[] = $dataTypes;

foreach ($dataParams as $key => $value) {
    $dataBindParams[] = &$dataParams[$key];
}

call_user_func_array(
    [$dataStmt, 'bind_param'],
    $dataBindParams
);


// =========================================================
// EXECUTE DATA QUERY
// =========================================================

if (!$dataStmt->execute()) {

    $dataStmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to fetch halls.'
    ]);

    exit;
}

$dataResult =
    $dataStmt->get_result();


// =========================================================
// PREPARE HALL DATA
// =========================================================

$halls = [];

while ($row = $dataResult->fetch_assoc()) {

    $hallId = (int) $row['id'];


    // =====================================================
    // FETCH IMAGES
    // =====================================================

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
        $dataStmt->close();

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Failed to fetch hall images.'
        ]);

        exit;
    }

    $imageStmt->bind_param(
        'i',
        $hallId
    );

    $imageStmt->execute();

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


    // =====================================================
    // FETCH FACILITIES
    // =====================================================

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
        $dataStmt->close();

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Failed to fetch hall facilities.'
        ]);

        exit;
    }

    $facilityStmt->bind_param(
        'i',
        $hallId
    );

    $facilityStmt->execute();

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


    // =====================================================
    // HALL RESPONSE
    // =====================================================

    $halls[] = [

        'id' =>
            $hallId,

        'hall_type' => [
            'id' =>
                $row['hall_type_id'] !== null
                    ? (int) $row['hall_type_id']
                    : null,

            'name' =>
                $row['hall_type_name'],

            'slug' =>
                $row['hall_type_slug'],

            'description' =>
                $row['hall_type_description']
        ],

        'name' =>
            $row['name'],

        'slug' =>
            $row['slug'],

        'description' =>
            $row['description'],

        'capacity' =>
            (int) $row['capacity'],

        'area' =>
            $row['area'],

        'price_description' =>
            $row['price_description'],

        'status' =>
            $row['status'],

        'images' =>
            $images,

        'facilities' =>
            $facilities,

        'created_at' =>
            $row['created_at'],

        'updated_at' =>
            $row['updated_at']
    ];
}

$dataStmt->close();


// =========================================================
// RESPONSE
// =========================================================

http_response_code(200);

echo json_encode([
    'status' => true,
    'message' => 'Halls fetched successfully.',

    'data' => $halls,

    'pagination' => [
        'current_page' =>
            $page,

        'per_page' =>
            $limit,

        'total_records' =>
            $totalRecords,

        'total_pages' =>
            $totalPages,

        'has_previous_page' =>
            $page > 1 && $totalRecords > 0,

        'has_next_page' =>
            $totalPages > 0 &&
            $page < $totalPages
    ],

    'filters' => [
        'search' =>
            $search,

        'hall_type_id' =>
            $hallTypeId,

        'status' =>
            $status,

        'min_capacity' =>
            $minCapacity,

        'max_capacity' =>
            $maxCapacity
    ],

    'sorting' => [
        'sort_by' =>
            $sortBy,

        'sort_order' =>
            $sortOrder
    ]
]);