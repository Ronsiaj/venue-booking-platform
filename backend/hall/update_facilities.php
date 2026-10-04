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

if (
    $rawInput === false ||
    trim($rawInput) === ''
) {
    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' => 'Request body is required.'
    ]);

    exit;
}

$data = json_decode(
    $rawInput,
    true
);

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
    'facility_ids'
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

if (
    filter_var(
        $data['hall_id'],
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
            'hall_id must be a valid positive integer.'
    ]);

    exit;
}

$hallId = (int) $data['hall_id'];


// =========================================================
// FACILITY IDS VALIDATION
// =========================================================
//
// facility_ids is required and must be an array.
//
// [] is allowed and means:
// remove all facilities from this hall.
//
// =========================================================

if (!array_key_exists('facility_ids', $data)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'facility_ids is required.'
    ]);

    exit;
}

if (!is_array($data['facility_ids'])) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'facility_ids must be an array.'
    ]);

    exit;
}

$facilityIds = $data['facility_ids'];


// =========================================================
// MAX FACILITY COUNT
// =========================================================

$maxFacilities = 50;

if (count($facilityIds) > $maxFacilities) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'A maximum of 50 facilities can be assigned to a hall.',
        'max_facilities' => $maxFacilities
    ]);

    exit;
}


// =========================================================
// VALIDATE EACH FACILITY ID
// =========================================================

$normalizedFacilityIds = [];

foreach ($facilityIds as $index => $facilityId) {

    if (
        $facilityId === '' ||
        $facilityId === null
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Each facility ID must be a valid positive integer.',
            'invalid_index' => $index
        ]);

        exit;
    }

    if (
        filter_var(
            $facilityId,
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
                'Each facility ID must be a valid positive integer.',
            'invalid_index' => $index,
            'invalid_value' => $facilityId
        ]);

        exit;
    }

    $normalizedFacilityIds[] =
        (int) $facilityId;
}


// =========================================================
// DUPLICATE FACILITY ID CHECK
// =========================================================

if (
    count($normalizedFacilityIds) !==
    count(array_unique($normalizedFacilityIds))
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'Duplicate facility IDs are not allowed.'
    ]);

    exit;
}


// =========================================================
// VERIFY HALL EXISTS
// =========================================================

$hallSql = "
    SELECT
        id,
        hall_type_id,
        name,
        slug,
        status
    FROM halls
    WHERE id = ?
    LIMIT 1
";

$hallStmt = $conn->prepare($hallSql);

if (!$hallStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to validate hall.'
    ]);

    exit;
}

$hallStmt->bind_param(
    'i',
    $hallId
);

$hallStmt->execute();

$hallResult =
    $hallStmt->get_result();

$hall =
    $hallResult->fetch_assoc();

$hallStmt->close();

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
// FETCH CURRENT FACILITY MAPPINGS
// =========================================================

$currentSql = "
    SELECT
        hfm.facility_id,
        hf.name,
        hf.status
    FROM hall_facility_map hfm

    INNER JOIN hall_facilities hf
        ON hf.id = hfm.facility_id

    WHERE hfm.hall_id = ?

    ORDER BY
        hfm.facility_id ASC
";

$currentStmt = $conn->prepare($currentSql);

if (!$currentStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to fetch current hall facilities.'
    ]);

    exit;
}

$currentStmt->bind_param(
    'i',
    $hallId
);

$currentStmt->execute();

$currentResult =
    $currentStmt->get_result();

$currentFacilityIds = [];
$currentFacilities = [];

while (
    $currentRow =
        $currentResult->fetch_assoc()
) {

    $currentFacilityId =
        (int) $currentRow['facility_id'];

    $currentFacilityIds[] =
        $currentFacilityId;

    $currentFacilities[] = [
        'id' =>
            $currentFacilityId,

        'name' =>
            $currentRow['name'],

        'status' =>
            $currentRow['status']
    ];
}

$currentStmt->close();


// =========================================================
// VALIDATE REQUESTED FACILITIES
// =========================================================
//
// [] is valid.
// If IDs are supplied, every facility must:
// 1. Exist
// 2. Be active
//
// =========================================================

$requestedFacilities = [];

if (!empty($normalizedFacilityIds)) {

    $placeholders = implode(
        ',',
        array_fill(
            0,
            count($normalizedFacilityIds),
            '?'
        )
    );

    $facilitySql = "
        SELECT
            id,
            name,
            status
        FROM hall_facilities
        WHERE id IN ($placeholders)
    ";

    $facilityStmt =
        $conn->prepare($facilitySql);

    if (!$facilityStmt) {
        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' =>
                'Failed to validate facilities.'
        ]);

        exit;
    }


    // -----------------------------------------------------
    // Dynamic bind parameters
    // -----------------------------------------------------

    $bindTypes = str_repeat(
        'i',
        count($normalizedFacilityIds)
    );

    $bindParams = [];
    $bindParams[] = $bindTypes;

    $bindValues =
        $normalizedFacilityIds;

    foreach ($bindValues as $key => $value) {
        $bindParams[] =
            &$bindValues[$key];
    }

    call_user_func_array(
        [$facilityStmt, 'bind_param'],
        $bindParams
    );


    if (!$facilityStmt->execute()) {

        $facilityStmt->close();

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' =>
                'Failed to validate facilities.'
        ]);

        exit;
    }

    $facilityResult =
        $facilityStmt->get_result();

    $facilityMap = [];

    while (
        $facilityRow =
            $facilityResult->fetch_assoc()
    ) {

        $facilityId =
            (int) $facilityRow['id'];

        $facilityMap[$facilityId] = [
            'id' =>
                $facilityId,

            'name' =>
                $facilityRow['name'],

            'status' =>
                $facilityRow['status']
        ];
    }

    $facilityStmt->close();


    // -----------------------------------------------------
    // Check missing facility IDs
    // -----------------------------------------------------

    $missingFacilityIds = [];

    foreach ($normalizedFacilityIds as $facilityId) {

        if (!isset($facilityMap[$facilityId])) {

            $missingFacilityIds[] =
                $facilityId;
        }
    }

    if (!empty($missingFacilityIds)) {

        http_response_code(404);

        echo json_encode([
            'status' => false,
            'message' =>
                'One or more facilities were not found.',
            'missing_facility_ids' =>
                $missingFacilityIds
        ]);

        exit;
    }


    // -----------------------------------------------------
    // Check inactive facilities
    // -----------------------------------------------------

    $inactiveFacilities = [];

    foreach ($normalizedFacilityIds as $facilityId) {

        if (
            $facilityMap[$facilityId]['status'] !==
            'active'
        ) {

            $inactiveFacilities[] = [
                'id' =>
                    $facilityId,

                'name' =>
                    $facilityMap[$facilityId]['name'],

                'status' =>
                    $facilityMap[$facilityId]['status']
            ];
        }
    }

    if (!empty($inactiveFacilities)) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Inactive facilities cannot be assigned to a hall.',
            'inactive_facilities' =>
                $inactiveFacilities
        ]);

        exit;
    }


    // -----------------------------------------------------
    // Build requested facility response
    // -----------------------------------------------------

    foreach ($normalizedFacilityIds as $facilityId) {

        $requestedFacilities[] =
            $facilityMap[$facilityId];
    }
}


// =========================================================
// CALCULATE CHANGES
// =========================================================

$previousIds =
    $currentFacilityIds;

$currentIds =
    $normalizedFacilityIds;

$addedIds =
    array_values(
        array_diff(
            $currentIds,
            $previousIds
        )
    );

$removedIds =
    array_values(
        array_diff(
            $previousIds,
            $currentIds
        )
    );

$unchangedIds =
    array_values(
        array_intersect(
            $previousIds,
            $currentIds
        )
    );


// =========================================================
// NO CHANGE
// =========================================================

if (
    empty($addedIds) &&
    empty($removedIds)
) {

    http_response_code(200);

    echo json_encode([
        'status' => true,
        'message' =>
            'No facility changes were made.',

        'data' => [
            'hall' => [
                'id' =>
                    (int) $hall['id'],

                'name' =>
                    $hall['name'],

                'slug' =>
                    $hall['slug'],

                'status' =>
                    $hall['status']
            ],

            'facilities' =>
                $currentFacilities
        ],

        'changes' => [
            'previous_facility_ids' =>
                $previousIds,

            'current_facility_ids' =>
                $currentIds,

            'added_facility_ids' =>
                $addedIds,

            'removed_facility_ids' =>
                $removedIds,

            'unchanged_facility_ids' =>
                $unchangedIds
        ],

        'updated_by' => [
            'id' =>
                (int) $authUser['sub'],

            'role' =>
                $authUser['role']
        ]
    ]);

    exit;
}


// =========================================================
// TRANSACTION
// =========================================================

$conn->begin_transaction();

try {

    // =====================================================
    // LOCK HALL
    // =====================================================

    $lockHallSql = "
        SELECT id
        FROM halls
        WHERE id = ?
        FOR UPDATE
    ";

    $lockHallStmt =
        $conn->prepare($lockHallSql);

    if (!$lockHallStmt) {
        throw new Exception(
            'Failed to lock hall record.'
        );
    }

    $lockHallStmt->bind_param(
        'i',
        $hallId
    );

    if (!$lockHallStmt->execute()) {

        $lockHallStmt->close();

        throw new Exception(
            'Failed to lock hall record.'
        );
    }

    $lockHallStmt->close();


    // =====================================================
    // DELETE EXISTING FACILITY MAPPINGS
    // =====================================================

    $deleteSql = "
        DELETE FROM hall_facility_map
        WHERE hall_id = ?
    ";

    $deleteStmt =
        $conn->prepare($deleteSql);

    if (!$deleteStmt) {
        throw new Exception(
            'Failed to prepare facility mapping deletion.'
        );
    }

    $deleteStmt->bind_param(
        'i',
        $hallId
    );

    if (!$deleteStmt->execute()) {

        $errorMessage =
            $deleteStmt->error;

        $deleteStmt->close();

        throw new Exception(
            'Failed to remove existing hall facilities: ' .
            $errorMessage
        );
    }

    $deletedMappings =
        $deleteStmt->affected_rows;

    $deleteStmt->close();


    // =====================================================
    // INSERT NEW FACILITY MAPPINGS
    // =====================================================

    $insertedMappings = 0;

    if (!empty($normalizedFacilityIds)) {

        $insertSql = "
            INSERT INTO hall_facility_map
            (
                hall_id,
                facility_id
            )
            VALUES (?, ?)
        ";

        $insertStmt =
            $conn->prepare($insertSql);

        if (!$insertStmt) {
            throw new Exception(
                'Failed to prepare facility mapping insert.'
            );
        }

        foreach ($normalizedFacilityIds as $facilityId) {

            $insertStmt->bind_param(
                'ii',
                $hallId,
                $facilityId
            );

            if (!$insertStmt->execute()) {

                $errorCode =
                    $insertStmt->errno;

                $errorMessage =
                    $insertStmt->error;

                $insertStmt->close();

                throw new Exception(
                    'Failed to assign hall facility: ' .
                    $errorMessage,
                    $errorCode
                );
            }

            $insertedMappings++;
        }

        $insertStmt->close();
    }


    // =====================================================
    // COMMIT
    // =====================================================

    $conn->commit();


    // =====================================================
    // BUILD FINAL FACILITY RESPONSE
    // =====================================================

    $finalFacilities = [];

    foreach ($requestedFacilities as $facility) {

        $finalFacilities[] = [
            'id' =>
                (int) $facility['id'],

            'name' =>
                $facility['name'],

            'status' =>
                $facility['status']
        ];
    }


    // =====================================================
    // SUCCESS RESPONSE
    // =====================================================

    http_response_code(200);

    echo json_encode([
        'status' => true,
        'message' =>
            'Hall facilities updated successfully.',

        'data' => [
            'hall' => [
                'id' =>
                    (int) $hall['id'],

                'name' =>
                    $hall['name'],

                'slug' =>
                    $hall['slug'],

                'status' =>
                    $hall['status']
            ],

            'facilities' =>
                $finalFacilities
        ],

        'changes' => [
            'previous_facility_ids' =>
                $previousIds,

            'current_facility_ids' =>
                $currentIds,

            'added_facility_ids' =>
                $addedIds,

            'removed_facility_ids' =>
                $removedIds,

            'unchanged_facility_ids' =>
                $unchangedIds
        ],

        'summary' => [
            'previous_count' =>
                count($previousIds),

            'current_count' =>
                count($currentIds),

            'added_count' =>
                count($addedIds),

            'removed_count' =>
                count($removedIds),

            'unchanged_count' =>
                count($unchangedIds),

            'deleted_mappings' =>
                (int) $deletedMappings,

            'inserted_mappings' =>
                (int) $insertedMappings
        ],

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
    // FOREIGN KEY ERROR
    // =====================================================

    if ((int) $e->getCode() === 1452) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'One or more facility IDs are invalid.'
        ]);

        exit;
    }


    // =====================================================
    // DUPLICATE MAPPING
    // =====================================================

    if ((int) $e->getCode() === 1062) {

        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' =>
                'Duplicate hall facility mapping detected.'
        ]);

        exit;
    }


    // =====================================================
    // SERVER ERROR
    // =====================================================

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to update hall facilities.'
    ]);

    exit;
}