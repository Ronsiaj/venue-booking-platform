<?php

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (
    $_SERVER['REQUEST_METHOD'] !== 'DELETE' &&
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
    http_response_code(405);

    echo json_encode([
        'status' => false,
        'message' => 'Only DELETE or POST method is allowed.'
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
    'hall_id'
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
        'message' => 'hall_id must be a valid positive integer.'
    ]);

    exit;
}

$hallId = (int) $data['hall_id'];


// =========================================================
// FETCH HALL
// =========================================================

$hallSql = "
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

$hallStmt = $conn->prepare($hallSql);

if (!$hallStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to fetch hall.'
    ]);

    exit;
}

$hallStmt->bind_param(
    'i',
    $hallId
);

$hallStmt->execute();

$hallResult = $hallStmt->get_result();

$hall = $hallResult->fetch_assoc();

$hallStmt->close();

if (!$hall) {
    http_response_code(404);

    echo json_encode([
        'status' => false,
        'message' => 'Hall not found.'
    ]);

    exit;
}


// =========================================================
// CHECK HALL IMAGES
// =========================================================

$imageCountSql = "
    SELECT COUNT(*) AS total_images
    FROM hall_images
    WHERE hall_id = ?
";

$imageCountStmt = $conn->prepare(
    $imageCountSql
);

if (!$imageCountStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to check hall images.'
    ]);

    exit;
}

$imageCountStmt->bind_param(
    'i',
    $hallId
);

$imageCountStmt->execute();

$imageCountResult =
    $imageCountStmt->get_result();

$imageCountRow =
    $imageCountResult->fetch_assoc();

$imageCountStmt->close();

$totalImages =
    (int) ($imageCountRow['total_images'] ?? 0);


// =========================================================
// CHECK HALL FACILITIES
// =========================================================

$facilityCountSql = "
    SELECT COUNT(*) AS total_facilities
    FROM hall_facility_map
    WHERE hall_id = ?
";

$facilityCountStmt = $conn->prepare(
    $facilityCountSql
);

if (!$facilityCountStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to check hall facilities.'
    ]);

    exit;
}

$facilityCountStmt->bind_param(
    'i',
    $hallId
);

$facilityCountStmt->execute();

$facilityCountResult =
    $facilityCountStmt->get_result();

$facilityCountRow =
    $facilityCountResult->fetch_assoc();

$facilityCountStmt->close();

$totalFacilities =
    (int) ($facilityCountRow['total_facilities'] ?? 0);


// =========================================================
// CHECK ACTIVE / RELATED LEADS
// =========================================================
//
// Hall leads are stored in hall_lead_details.
// We do NOT automatically delete leads because leads are
// business/history records.
//
// If a hall is referenced by hall_lead_details, deletion
// is blocked to preserve historical data.
//
// =========================================================

$leadCountSql = "
    SELECT COUNT(*) AS total_leads
    FROM hall_lead_details
    WHERE hall_id = ?
";

$leadCountStmt = $conn->prepare(
    $leadCountSql
);

if (!$leadCountStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to check hall leads.'
    ]);

    exit;
}

$leadCountStmt->bind_param(
    'i',
    $hallId
);

$leadCountStmt->execute();

$leadCountResult =
    $leadCountStmt->get_result();

$leadCountRow =
    $leadCountResult->fetch_assoc();

$leadCountStmt->close();

$totalLeads =
    (int) ($leadCountRow['total_leads'] ?? 0);


// =========================================================
// BLOCK DELETE IF LEADS EXIST
// =========================================================

if ($totalLeads > 0) {

    http_response_code(409);

    echo json_encode([
        'status' => false,
        'message' =>
            'Hall cannot be deleted because leads are associated with this hall.',
        'hall_id' => $hallId,
        'total_leads' => $totalLeads,
        'suggestion' =>
            'Set the hall status to inactive instead of deleting it.'
    ]);

    exit;
}


// =========================================================
// TRANSACTION
// =========================================================

$conn->begin_transaction();

try {

    // =====================================================
    // DELETE FACILITY MAPPINGS
    // =====================================================

    $deleteFacilitySql = "
        DELETE FROM hall_facility_map
        WHERE hall_id = ?
    ";

    $deleteFacilityStmt = $conn->prepare(
        $deleteFacilitySql
    );

    if (!$deleteFacilityStmt) {
        throw new Exception(
            'Failed to prepare facility mapping deletion.'
        );
    }

    $deleteFacilityStmt->bind_param(
        'i',
        $hallId
    );

    if (!$deleteFacilityStmt->execute()) {

        $errorMessage =
            $deleteFacilityStmt->error;

        $deleteFacilityStmt->close();

        throw new Exception(
            'Failed to delete hall facility mappings: ' .
            $errorMessage
        );
    }

    $deletedFacilityMappings =
        $deleteFacilityStmt->affected_rows;

    $deleteFacilityStmt->close();


    // =====================================================
    // DELETE HALL IMAGES
    // =====================================================

    $deleteImagesSql = "
        DELETE FROM hall_images
        WHERE hall_id = ?
    ";

    $deleteImagesStmt = $conn->prepare(
        $deleteImagesSql
    );

    if (!$deleteImagesStmt) {
        throw new Exception(
            'Failed to prepare hall image deletion.'
        );
    }

    $deleteImagesStmt->bind_param(
        'i',
        $hallId
    );

    if (!$deleteImagesStmt->execute()) {

        $errorMessage =
            $deleteImagesStmt->error;

        $deleteImagesStmt->close();

        throw new Exception(
            'Failed to delete hall images: ' .
            $errorMessage
        );
    }

    $deletedImages =
        $deleteImagesStmt->affected_rows;

    $deleteImagesStmt->close();


    // =====================================================
    // DELETE HALL
    // =====================================================

    $deleteHallSql = "
        DELETE FROM halls
        WHERE id = ?
    ";

    $deleteHallStmt = $conn->prepare(
        $deleteHallSql
    );

    if (!$deleteHallStmt) {
        throw new Exception(
            'Failed to prepare hall deletion.'
        );
    }

    $deleteHallStmt->bind_param(
        'i',
        $hallId
    );

    if (!$deleteHallStmt->execute()) {

        $errorCode =
            $deleteHallStmt->errno;

        $errorMessage =
            $deleteHallStmt->error;

        $deleteHallStmt->close();

        throw new Exception(
            'Failed to delete hall: ' .
            $errorMessage,
            $errorCode
        );
    }

    $deletedHallRows =
        $deleteHallStmt->affected_rows;

    $deleteHallStmt->close();


    // =====================================================
    // VERIFY HALL DELETED
    // =====================================================

    if ($deletedHallRows !== 1) {
        throw new Exception(
            'Hall deletion could not be confirmed.'
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
        'message' => 'Hall deleted successfully.',

        'deleted_hall' => [
            'id' =>
                (int) $hall['id'],

            'hall_type_id' =>
                $hall['hall_type_id'] !== null
                    ? (int) $hall['hall_type_id']
                    : null,

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

            'created_at' =>
                $hall['created_at'],

            'updated_at' =>
                $hall['updated_at']
        ],

        'related_records_deleted' => [
            'hall_images' =>
                (int) $deletedImages,

            'hall_facility_mappings' =>
                (int) $deletedFacilityMappings
        ],

        'existing_related_records_before_delete' => [
            'hall_images' =>
                $totalImages,

            'hall_facility_mappings' =>
                $totalFacilities,

            'hall_leads' =>
                $totalLeads
        ],

        'affected_rows' =>
            (int) $deletedHallRows,

        'deleted_by' => [
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
    // FOREIGN KEY CONSTRAINT
    // =====================================================

    if ((int) $e->getCode() === 1451) {

        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' =>
                'Hall cannot be deleted because related records exist.',
            'hall_id' => $hallId,
            'details' => [
                'hall_images' => $totalImages,
                'hall_facility_mappings' => $totalFacilities,
                'hall_leads' => $totalLeads
            ]
        ]);

        exit;
    }


    // =====================================================
    // SERVER ERROR
    // =====================================================

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to delete hall.'
    ]);

    exit;
}