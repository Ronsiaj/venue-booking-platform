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


// =========================================================
// CONFIGURATION
// =========================================================

$maxFileSize = 5 * 1024 * 1024; // 5 MB
$maxImagesPerHall = 10;

$allowedMimeTypes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp'
];

$allowedExtensions = [
    'jpg',
    'jpeg',
    'png',
    'webp'
];

$maxWidth = 10000;
$maxHeight = 10000;


// =========================================================
// VALIDATE CONTENT TYPE
// =========================================================

if (
    !isset($_SERVER['CONTENT_TYPE']) ||
    stripos(
        $_SERVER['CONTENT_TYPE'],
        'multipart/form-data'
    ) !== 0
) {
    http_response_code(415);

    echo json_encode([
        'status' => false,
        'message' =>
            'Content-Type must be multipart/form-data.'
    ]);

    exit;
}


// =========================================================
// HALL ID VALIDATION
// =========================================================

if (
    !isset($_POST['hall_id']) ||
    $_POST['hall_id'] === '' ||
    $_POST['hall_id'] === null
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
        $_POST['hall_id'],
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

$hallId = (int) $_POST['hall_id'];


// =========================================================
// ALLOWED POST FIELDS
// =========================================================

$allowedPostFields = [
    'hall_id',
    'alt_text',
    'is_primary',
    'sort_order'
];

$unknownPostFields = array_diff(
    array_keys($_POST),
    $allowedPostFields
);

if (!empty($unknownPostFields)) {
    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' =>
            'Unknown form field(s) provided.',
        'unknown_fields' =>
            array_values($unknownPostFields)
    ]);

    exit;
}


// =========================================================
// FILE FIELD VALIDATION
// =========================================================

if (
    !isset($_FILES['image']) ||
    !is_array($_FILES['image'])
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Image file is required.'
    ]);

    exit;
}


// =========================================================
// FETCH HALL
// =========================================================

$hallSql = "
    SELECT
        h.id,
        h.hall_type_id,
        h.name,
        h.slug,
        h.status,
        ht.status AS hall_type_status
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
// HALL TYPE VALIDATION
// =========================================================

if (
    $hall['hall_type_id'] !== null &&
    $hall['hall_type_status'] !== 'active'
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'The hall type associated with this hall is inactive.'
    ]);

    exit;
}


// =========================================================
// HALL STATUS VALIDATION
// =========================================================
//
// Images can still be managed for inactive/maintenance halls,
// so we do not block based on hall status.
//
// =========================================================


// =========================================================
// ALT TEXT VALIDATION
// =========================================================

$altText = null;

if (array_key_exists('alt_text', $_POST)) {

    if (!is_string($_POST['alt_text'])) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'alt_text must be a string.'
        ]);

        exit;
    }

    $altText = trim($_POST['alt_text']);

    if ($altText === '') {
        $altText = null;
    }

    if (
        $altText !== null &&
        mb_strlen($altText) > 255
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'alt_text must not exceed 255 characters.'
        ]);

        exit;
    }

    if (
        $altText !== null &&
        preg_match('/\s{2,}/', $altText)
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'alt_text must not contain consecutive spaces.'
        ]);

        exit;
    }
}


// =========================================================
// IS PRIMARY VALIDATION
// =========================================================

$isPrimary = 0;

if (array_key_exists('is_primary', $_POST)) {

    $primaryValue = $_POST['is_primary'];

    if (
        !in_array(
            (string) $primaryValue,
            ['0', '1'],
            true
        )
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'is_primary must be 0 or 1.'
        ]);

        exit;
    }

    $isPrimary = (int) $primaryValue;
}


// =========================================================
// SORT ORDER VALIDATION
// =========================================================

$sortOrder = 0;

if (array_key_exists('sort_order', $_POST)) {

    if (
        filter_var(
            $_POST['sort_order'],
            FILTER_VALIDATE_INT
        ) === false
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'sort_order must be a valid integer.'
        ]);

        exit;
    }

    $sortOrder = (int) $_POST['sort_order'];

    if ($sortOrder < 0) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'sort_order cannot be negative.'
        ]);

        exit;
    }

    if ($sortOrder > 100000) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'sort_order must not exceed 100000.'
        ]);

        exit;
    }
}


// =========================================================
// IMAGE ERROR VALIDATION
// =========================================================

$image = $_FILES['image'];

if (
    !isset($image['error']) ||
    !is_int($image['error'])
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Invalid uploaded image data.'
    ]);

    exit;
}

if ($image['error'] !== UPLOAD_ERR_OK) {

    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE =>
            'The uploaded file exceeds the server upload size limit.',

        UPLOAD_ERR_FORM_SIZE =>
            'The uploaded file exceeds the allowed form size.',

        UPLOAD_ERR_PARTIAL =>
            'The image upload was incomplete.',

        UPLOAD_ERR_NO_FILE =>
            'Image file is required.',

        UPLOAD_ERR_NO_TMP_DIR =>
            'Server temporary directory is missing.',

        UPLOAD_ERR_CANT_WRITE =>
            'Server failed to write the uploaded file.',

        UPLOAD_ERR_EXTENSION =>
            'The image upload was blocked by a server extension.'
    ];

    $message =
        $uploadErrors[$image['error']]
        ?? 'Image upload failed.';

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => $message
    ]);

    exit;
}


// =========================================================
// TEMP FILE VALIDATION
// =========================================================

if (
    !isset($image['tmp_name']) ||
    !is_string($image['tmp_name']) ||
    !is_uploaded_file($image['tmp_name'])
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'Invalid uploaded image.'
    ]);

    exit;
}

$tmpFile = $image['tmp_name'];


// =========================================================
// FILE SIZE VALIDATION
// =========================================================

$fileSize = filesize($tmpFile);

if ($fileSize === false) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'Unable to determine uploaded file size.'
    ]);

    exit;
}

if ($fileSize <= 0) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Uploaded image is empty.'
    ]);

    exit;
}

if ($fileSize > $maxFileSize) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'Image size must not exceed 5 MB.',
        'max_size_bytes' =>
            $maxFileSize,
        'uploaded_size_bytes' =>
            $fileSize
    ]);

    exit;
}


// =========================================================
// IMAGE MIME TYPE + DIMENSION VALIDATION
// =========================================================

$imageInfo = @getimagesize($tmpFile);

if ($imageInfo === false) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'Uploaded file is not a valid image.'
    ]);

    exit;
}

$imageWidth =
    isset($imageInfo[0])
        ? (int) $imageInfo[0]
        : 0;

$imageHeight =
    isset($imageInfo[1])
        ? (int) $imageInfo[1]
        : 0;

$imageMime =
    isset($imageInfo['mime'])
        ? strtolower(trim($imageInfo['mime']))
        : '';


if (
    $imageWidth <= 0 ||
    $imageHeight <= 0
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'Invalid image dimensions.'
    ]);

    exit;
}


if (
    $imageWidth > $maxWidth ||
    $imageHeight > $maxHeight
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'Image dimensions must not exceed 10000 x 10000 pixels.',
        'width' => $imageWidth,
        'height' => $imageHeight
    ]);

    exit;
}


if (!array_key_exists(
    $imageMime,
    $allowedMimeTypes
)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'Invalid image type. Only JPG, JPEG, PNG and WEBP are allowed.',
        'allowed_types' => [
            'jpg',
            'jpeg',
            'png',
            'webp'
        ]
    ]);

    exit;
}


// =========================================================
// ORIGINAL EXTENSION VALIDATION
// =========================================================

$originalName =
    isset($image['name'])
        ? (string) $image['name']
        : '';

$originalExtension =
    strtolower(
        pathinfo(
            $originalName,
            PATHINFO_EXTENSION
        )
    );

if (
    $originalExtension === '' ||
    !in_array(
        $originalExtension,
        $allowedExtensions,
        true
    )
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'Invalid file extension. Only JPG, JPEG, PNG and WEBP are allowed.'
    ]);

    exit;
}


// =========================================================
// EXTENSION / MIME CONSISTENCY
// =========================================================

$expectedExtension =
    $allowedMimeTypes[$imageMime];

$extensionMap = [
    'jpg'  => 'jpg',
    'jpeg' => 'jpg',
    'png'  => 'png',
    'webp' => 'webp'
];

$normalizedOriginalExtension =
    $extensionMap[$originalExtension]
    ?? $originalExtension;

if (
    $normalizedOriginalExtension !==
    $expectedExtension
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'File extension does not match the actual image type.'
    ]);

    exit;
}


// =========================================================
// COUNT EXISTING IMAGES
// =========================================================

$countSql = "
    SELECT COUNT(*) AS total_images
    FROM hall_images
    WHERE hall_id = ?
";

$countStmt = $conn->prepare($countSql);

if (!$countStmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to check existing hall images.'
    ]);

    exit;
}

$countStmt->bind_param(
    'i',
    $hallId
);

$countStmt->execute();

$countResult =
    $countStmt->get_result();

$countRow =
    $countResult->fetch_assoc();

$countStmt->close();

$totalImages =
    (int) ($countRow['total_images'] ?? 0);


if ($totalImages >= $maxImagesPerHall) {
    http_response_code(409);

    echo json_encode([
        'status' => false,
        'message' =>
            'Maximum number of images already uploaded for this hall.',
        'hall_id' => $hallId,
        'current_images' => $totalImages,
        'max_images' => $maxImagesPerHall
    ]);

    exit;
}


// =========================================================
// AUTO PRIMARY LOGIC
// =========================================================
//
// If this is the first image, it automatically becomes
// primary even if is_primary = 0 was sent.
//
// =========================================================

if ($totalImages === 0) {
    $isPrimary = 1;
}


// =========================================================
// CREATE UPLOAD DIRECTORY
// =========================================================

$projectRoot =
    dirname(__DIR__);

$uploadDirectory =
    $projectRoot .
    DIRECTORY_SEPARATOR .
    'uploads' .
    DIRECTORY_SEPARATOR .
    'halls' .
    DIRECTORY_SEPARATOR .
    $hallId .
    DIRECTORY_SEPARATOR .
    'image';

if (!is_dir($uploadDirectory)) {

    if (!mkdir(
        $uploadDirectory,
        0755,
        true
    )) {
        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' =>
                'Failed to create hall image upload directory.'
        ]);

        exit;
    }
}


// =========================================================
// DIRECTORY WRITABLE CHECK
// =========================================================

if (!is_writable($uploadDirectory)) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Hall image upload directory is not writable.'
    ]);

    exit;
}


// =========================================================
// GENERATE SAFE RANDOM FILE NAME
// =========================================================

try {

    $randomName =
        bin2hex(
            random_bytes(16)
        );

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to generate secure image filename.'
    ]);

    exit;
}

$fileName =
    $randomName .
    '.' .
    $expectedExtension;

$targetPath =
    $uploadDirectory .
    DIRECTORY_SEPARATOR .
    $fileName;


// =========================================================
// RELATIVE DB PATH
// =========================================================

$relativePath =
    'uploads/halls/' .
    $hallId .
    '/image/' .
    $fileName;


// =========================================================
// PATH SAFETY CHECK
// =========================================================

$realUploadDirectory =
    realpath($uploadDirectory);

if ($realUploadDirectory === false) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to resolve upload directory.'
    ]);

    exit;
}

$realUploadDirectory =
    rtrim(
        $realUploadDirectory,
        DIRECTORY_SEPARATOR
    );


// =========================================================
// TRANSACTION
// =========================================================

$conn->begin_transaction();

$fileMoved = false;

try {

    // =====================================================
    // PRIMARY IMAGE HANDLING
    // =====================================================
    //
    // If new image is primary, remove primary status from
    // existing images first.
    //
    // =====================================================

    if ($isPrimary === 1) {

        $resetPrimarySql = "
            UPDATE hall_images
            SET is_primary = 0
            WHERE hall_id = ?
        ";

        $resetPrimaryStmt =
            $conn->prepare(
                $resetPrimarySql
            );

        if (!$resetPrimaryStmt) {
            throw new Exception(
                'Failed to prepare primary image update.'
            );
        }

        $resetPrimaryStmt->bind_param(
            'i',
            $hallId
        );

        if (!$resetPrimaryStmt->execute()) {

            $errorMessage =
                $resetPrimaryStmt->error;

            $resetPrimaryStmt->close();

            throw new Exception(
                'Failed to update existing primary image: ' .
                $errorMessage
            );
        }

        $resetPrimaryStmt->close();
    }


    // =====================================================
    // MOVE UPLOADED FILE
    // =====================================================

    if (!move_uploaded_file(
        $tmpFile,
        $targetPath
    )) {
        throw new Exception(
            'Failed to store uploaded image.'
        );
    }

    $fileMoved = true;


    // =====================================================
    // VERIFY STORED FILE
    // =====================================================

    if (
        !file_exists($targetPath) ||
        !is_file($targetPath)
    ) {
        throw new Exception(
            'Uploaded image could not be verified after storage.'
        );
    }


    // =====================================================
    // INSERT IMAGE RECORD
    // =====================================================

    $insertSql = "
        INSERT INTO hall_images
        (
            hall_id,
            image_path,
            alt_text,
            is_primary,
            sort_order
        )
        VALUES (?, ?, ?, ?, ?)
    ";

    $insertStmt =
        $conn->prepare($insertSql);

    if (!$insertStmt) {
        throw new Exception(
            'Failed to prepare hall image insert query.'
        );
    }

    $insertStmt->bind_param(
        'issii',
        $hallId,
        $relativePath,
        $altText,
        $isPrimary,
        $sortOrder
    );

    if (!$insertStmt->execute()) {

        $errorCode =
            $insertStmt->errno;

        $errorMessage =
            $insertStmt->error;

        $insertStmt->close();

        throw new Exception(
            'Failed to save hall image record: ' .
            $errorMessage,
            $errorCode
        );
    }

    $imageId =
        $insertStmt->insert_id;

    $insertStmt->close();


    // =====================================================
    // FETCH CREATED IMAGE
    // =====================================================

    $fetchSql = "
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

    $fetchStmt =
        $conn->prepare($fetchSql);

    if (!$fetchStmt) {
        throw new Exception(
            'Failed to fetch created hall image.'
        );
    }

    $fetchStmt->bind_param(
        'i',
        $imageId
    );

    if (!$fetchStmt->execute()) {

        $fetchStmt->close();

        throw new Exception(
            'Failed to fetch created hall image.'
        );
    }

    $fetchResult =
        $fetchStmt->get_result();

    $createdImage =
        $fetchResult->fetch_assoc();

    $fetchStmt->close();

    if (!$createdImage) {
        throw new Exception(
            'Hall image was created but could not be retrieved.'
        );
    }


    // =====================================================
    // COMMIT
    // =====================================================

    $conn->commit();


    // =====================================================
    // SUCCESS RESPONSE
    // =====================================================

    http_response_code(201);

    echo json_encode([
        'status' => true,
        'message' =>
            'Hall image uploaded successfully.',

        'data' => [
            'id' =>
                (int) $createdImage['id'],

            'hall_id' =>
                (int) $createdImage['hall_id'],

            'image_path' =>
                $createdImage['image_path'],

            'alt_text' =>
                $createdImage['alt_text'],

            'is_primary' =>
                (bool) $createdImage['is_primary'],

            'sort_order' =>
                (int) $createdImage['sort_order'],

            'created_at' =>
                $createdImage['created_at']
        ],

        'file' => [
            'original_name' =>
                $originalName,

            'size_bytes' =>
                (int) $fileSize,

            'mime_type' =>
                $imageMime,

            'extension' =>
                $expectedExtension,

            'width' =>
                $imageWidth,

            'height' =>
                $imageHeight
        ],

        'hall' => [
            'id' =>
                (int) $hall['id'],

            'name' =>
                $hall['name'],

            'slug' =>
                $hall['slug']
        ],

        'image_summary' => [
            'previous_image_count' =>
                $totalImages,

            'current_image_count' =>
                $totalImages + 1,

            'max_images' =>
                $maxImagesPerHall
        ],

        'uploaded_by' => [
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
    // REMOVE FILE IF DB OPERATION FAILED
    // =====================================================

    if (
        $fileMoved &&
        file_exists($targetPath)
    ) {
        @unlink($targetPath);
    }


    // =====================================================
    // DUPLICATE / CONSTRAINT ERROR
    // =====================================================

    if ((int) $e->getCode() === 1062) {

        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' =>
                'Duplicate hall image record.'
        ]);

        exit;
    }


    // =====================================================
    // FOREIGN KEY ERROR
    // =====================================================

    if ((int) $e->getCode() === 1452) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                'Invalid hall. The selected hall does not exist.'
        ]);

        exit;
    }


    // =====================================================
    // GENERIC ERROR
    // =====================================================

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' =>
            'Failed to upload hall image.'
    ]);

    exit;
}