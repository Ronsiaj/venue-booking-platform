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
    'image_id',
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
        'message' =>
            'Unknown field(s) provided.',
        'unknown_fields' =>
            array_values($unknownFields)
    ]);

    exit;
}


// =========================================================
// IMAGE ID VALIDATION
// =========================================================

if (
    !array_key_exists('image_id', $data) ||
    $data['image_id'] === '' ||
    $data['image_id'] === null
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'image_id is required.'
    ]);

    exit;
}

if (
    filter_var(
        $data['image_id'],
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
            'image_id must be a valid positive integer.'
    ]);

    exit;
}

$imageId = (int) $data['image_id'];


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
        'message' =>
            'Failed to validate hall.'
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
// FETCH IMAGE
// =========================================================

$imageSql = "
    SELECT
        id,
        hall_id,
        image_path,
        alt_text,
        is_primary,
        sort_order,
        created_at
    FROM hall_images
    WHERE id = ?
    LIMIT 1
";

$imageStmt = $conn->prepare($imageSql);

if (!$imageStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to fetch hall image.'
    ]);

    exit;
}

$imageStmt->bind_param(
    'i',
    $imageId
);

$imageStmt->execute();

$imageResult =
    $imageStmt->get_result();

$image =
    $imageResult->fetch_assoc();

$imageStmt->close();

if (!$image) {
    http_response_code(404);

    echo json_encode([
        'status' => false,
        'message' =>
            'Hall image not found.',
        'image_id' => $imageId
    ]);

    exit;
}


// =========================================================
// VERIFY IMAGE BELONGS TO HALL
// =========================================================

if (
    (int) $image['hall_id'] !== $hallId
) {
    http_response_code(409);

    echo json_encode([
        'status' => false,
        'message' =>
            'The selected image does not belong to the specified hall.',
        'image_id' => $imageId,
        'hall_id' => $hallId,
        'image_hall_id' =>
            (int) $image['hall_id']
    ]);

    exit;
}


// =========================================================
// VALIDATE IMAGE PATH
// =========================================================

$imagePath =
    trim(
        (string) $image['image_path']
    );

if ($imagePath === '') {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Hall image has an invalid database path.'
    ]);

    exit;
}


// =========================================================
// PATH TRAVERSAL PROTECTION
// =========================================================

if (
    str_contains($imagePath, '..') ||
    str_contains($imagePath, "\0") ||
    str_starts_with($imagePath, '/') ||
    preg_match('/^[A-Za-z]:[\\\\\/]/', $imagePath)
) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Invalid image path detected.'
    ]);

    exit;
}


// =========================================================
// NORMALIZE PATH
// =========================================================

$imagePath =
    str_replace(
        '\\',
        '/',
        $imagePath
    );


// =========================================================
// EXPECTED HALL IMAGE PREFIX
// =========================================================

$expectedPrefix =
    'uploads/halls/' .
    $hallId .
    '/image/';

if (
    !str_starts_with(
        $imagePath,
        $expectedPrefix
    )
) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Image path does not belong to this hall.'
    ]);

    exit;
}


// =========================================================
// EXTRACT FILE NAME
// =========================================================

$fileName =
    basename($imagePath);

if (
    $fileName === '' ||
    $fileName === '.' ||
    $fileName === '..'
) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Invalid hall image filename.'
    ]);

    exit;
}


// =========================================================
// SAFE FILENAME VALIDATION
// =========================================================
//
// upload_img.php creates random hexadecimal filenames.
// Therefore only these filename characters are expected.
//
// =========================================================

if (!preg_match(
    '/^[a-fA-F0-9]{32}\.(jpg|jpeg|png|webp)$/i',
    $fileName
)) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Invalid hall image filename detected.'
    ]);

    exit;
}


// =========================================================
// PROJECT ROOT
// =========================================================

$projectRoot =
    dirname(__DIR__);

$hallImageDirectory =
    $projectRoot .
    DIRECTORY_SEPARATOR .
    'uploads' .
    DIRECTORY_SEPARATOR .
    'halls' .
    DIRECTORY_SEPARATOR .
    $hallId .
    DIRECTORY_SEPARATOR .
    'image';

if (!is_dir($hallImageDirectory)) {

    // Directory doesn't exist.
    // DB record can still be safely removed.
    $directoryExists = false;

} else {

    $directoryExists = true;
}


// =========================================================
// RESOLVE DIRECTORY
// =========================================================

$realImageDirectory = null;

if ($directoryExists) {

    $realImageDirectory =
        realpath(
            $hallImageDirectory
        );

    if ($realImageDirectory === false) {
        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' =>
                'Failed to resolve hall image directory.'
        ]);

        exit;
    }

    $realImageDirectory =
        rtrim(
            $realImageDirectory,
            DIRECTORY_SEPARATOR
        );
}


// =========================================================
// BUILD PHYSICAL FILE PATH
// =========================================================

$physicalFilePath =
    $hallImageDirectory .
    DIRECTORY_SEPARATOR .
    $fileName;


// =========================================================
// PHYSICAL PATH SAFETY CHECK
// =========================================================

if ($directoryExists) {

    $normalizedDirectory =
        str_replace(
            '\\',
            '/',
            $realImageDirectory
        );

    $normalizedFilePath =
        str_replace(
            '\\',
            '/',
            $physicalFilePath
        );

    $expectedPhysicalPrefix =
        rtrim(
            $normalizedDirectory,
            '/'
        ) . '/';

    if (
        !str_starts_with(
            $normalizedFilePath,
            $expectedPhysicalPrefix
        )
    ) {
        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' =>
                'Unsafe physical image path detected.'
        ]);

        exit;
    }
}


// =========================================================
// CHECK IF PRIMARY
// =========================================================

$wasPrimary =
    (int) $image['is_primary'] === 1;


// =========================================================
// TRANSACTION
// =========================================================

$conn->begin_transaction();

try {

    // =====================================================
    // LOCK IMAGE RECORD
    // =====================================================

    $lockSql = "
        SELECT
            id,
            hall_id,
            image_path,
            alt_text,
            is_primary,
            sort_order,
            created_at
        FROM hall_images
        WHERE id = ?
        AND hall_id = ?
        FOR UPDATE
    ";

    $lockStmt =
        $conn->prepare($lockSql);

    if (!$lockStmt) {
        throw new Exception(
            'Failed to lock hall image record.'
        );
    }

    $lockStmt->bind_param(
        'ii',
        $imageId,
        $hallId
    );

    if (!$lockStmt->execute()) {

        $lockStmt->close();

        throw new Exception(
            'Failed to lock hall image record.'
        );
    }

    $lockResult =
        $lockStmt->get_result();

    $lockedImage =
        $lockResult->fetch_assoc();

    $lockStmt->close();

    if (!$lockedImage) {
        throw new Exception(
            'Hall image no longer exists.'
        );
    }


    // =====================================================
    // DELETE IMAGE DB RECORD
    // =====================================================

    $deleteSql = "
        DELETE FROM hall_images
        WHERE id = ?
        AND hall_id = ?
    ";

    $deleteStmt =
        $conn->prepare($deleteSql);

    if (!$deleteStmt) {
        throw new Exception(
            'Failed to prepare image deletion query.'
        );
    }

    $deleteStmt->bind_param(
        'ii',
        $imageId,
        $hallId
    );

    if (!$deleteStmt->execute()) {

        $errorCode =
            $deleteStmt->errno;

        $errorMessage =
            $deleteStmt->error;

        $deleteStmt->close();

        throw new Exception(
            'Failed to delete hall image record: ' .
            $errorMessage,
            $errorCode
        );
    }

    $deletedRows =
        $deleteStmt->affected_rows;

    $deleteStmt->close();


    if ($deletedRows !== 1) {
        throw new Exception(
            'Hall image deletion could not be confirmed.'
        );
    }


    // =====================================================
    // PRIMARY IMAGE REPLACEMENT
    // =====================================================
    //
    // If deleted image was primary, select the next image
    // using sort_order + id.
    //
    // =====================================================

    $newPrimaryImage = null;

    if ($wasPrimary) {

        $nextImageSql = "
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
                sort_order ASC,
                id ASC
            LIMIT 1
            FOR UPDATE
        ";

        $nextImageStmt =
            $conn->prepare(
                $nextImageSql
            );

        if (!$nextImageStmt) {
            throw new Exception(
                'Failed to find replacement primary image.'
            );
        }

        $nextImageStmt->bind_param(
            'i',
            $hallId
        );

        if (!$nextImageStmt->execute()) {

            $nextImageStmt->close();

            throw new Exception(
                'Failed to find replacement primary image.'
            );
        }

        $nextImageResult =
            $nextImageStmt->get_result();

        $nextImage =
            $nextImageResult->fetch_assoc();

        $nextImageStmt->close();


        if ($nextImage) {

            $newPrimaryId =
                (int) $nextImage['id'];


            // ---------------------------------------------
            // Set replacement image as primary
            // ---------------------------------------------

            $primaryUpdateSql = "
                UPDATE hall_images
                SET is_primary = 1
                WHERE id = ?
                AND hall_id = ?
            ";

            $primaryUpdateStmt =
                $conn->prepare(
                    $primaryUpdateSql
                );

            if (!$primaryUpdateStmt) {
                throw new Exception(
                    'Failed to prepare replacement primary update.'
                );
            }

            $primaryUpdateStmt->bind_param(
                'ii',
                $newPrimaryId,
                $hallId
            );

            if (!$primaryUpdateStmt->execute()) {

                $primaryUpdateStmt->close();

                throw new Exception(
                    'Failed to set replacement primary image.'
                );
            }

            $primaryUpdateStmt->close();


            // ---------------------------------------------
            // Prepare response data
            // ---------------------------------------------

            $newPrimaryImage = [
                'id' =>
                    $newPrimaryId,

                'image_path' =>
                    $nextImage['image_path'],

                'alt_text' =>
                    $nextImage['alt_text'],

                'is_primary' =>
                    true,

                'sort_order' =>
                    (int) $nextImage['sort_order'],

                'created_at' =>
                    $nextImage['created_at']
            ];
        }
    }


    // =====================================================
    // COMMIT
    // =====================================================

    $conn->commit();


    // =====================================================
    // PHYSICAL FILE DELETE
    // =====================================================
    //
    // DB transaction is already committed.
    //
    // If physical file doesn't exist, DB deletion is still
    // successful.
    //
    // =====================================================

    $physicalFileExists =
        file_exists($physicalFilePath);

    $physicalFileDeleted = false;
    $physicalFileWarning = null;

    if ($physicalFileExists) {

        if (@unlink($physicalFilePath)) {

            $physicalFileDeleted = true;

        } else {

            $physicalFileWarning =
                'Image database record was deleted, but the physical image file could not be deleted.';
        }

    } else {

        $physicalFileWarning =
            'Image database record was deleted, but the physical image file was not found.';
    }


    // =====================================================
    // COUNT REMAINING IMAGES
    // =====================================================

    $remainingCountSql = "
        SELECT COUNT(*) AS total_images
        FROM hall_images
        WHERE hall_id = ?
    ";

    $remainingCountStmt =
        $conn->prepare(
            $remainingCountSql
        );

    $remainingImages = 0;

    if ($remainingCountStmt) {

        $remainingCountStmt->bind_param(
            'i',
            $hallId
        );

        if ($remainingCountStmt->execute()) {

            $remainingCountResult =
                $remainingCountStmt->get_result();

            $remainingCountRow =
                $remainingCountResult->fetch_assoc();

            $remainingImages =
                (int) (
                    $remainingCountRow['total_images']
                    ?? 0
                );
        }

        $remainingCountStmt->close();
    }


    // =====================================================
    // RESPONSE
    // =====================================================

    http_response_code(200);

    $response = [
        'status' => true,
        'message' =>
            'Hall image deleted successfully.',

        'deleted_image' => [
            'id' =>
                (int) $image['id'],

            'hall_id' =>
                (int) $image['hall_id'],

            'image_path' =>
                $image['image_path'],

            'alt_text' =>
                $image['alt_text'],

            'was_primary' =>
                $wasPrimary,

            'sort_order' =>
                (int) $image['sort_order'],

            'created_at' =>
                $image['created_at']
        ],

        'physical_file' => [
            'existed' =>
                $physicalFileExists,

            'deleted' =>
                $physicalFileDeleted,

            'warning' =>
                $physicalFileWarning
        ],

        'primary_image' => [
            'replacement_required' =>
                $wasPrimary,

            'replacement_found' =>
                $newPrimaryImage !== null,

            'replacement' =>
                $newPrimaryImage
        ],

        'remaining_images' =>
            $remainingImages,

        'hall' => [
            'id' =>
                (int) $hall['id'],

            'name' =>
                $hall['name'],

            'slug' =>
                $hall['slug']
        ],

        'deleted_by' => [
            'id' =>
                (int) $authUser['sub'],

            'role' =>
                $authUser['role']
        ]
    ];

    echo json_encode($response);

    exit;

} catch (Throwable $e) {

    // =====================================================
    // ROLLBACK
    // =====================================================

    $conn->rollback();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to delete hall image.'
    ]);

    exit;
}