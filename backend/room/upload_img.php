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
| Admin + Staff can upload room images.
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
| Helper Function - Positive Integer
|--------------------------------------------------------------------------
*/

function isPositiveInteger($value)
{
    if (
        is_array($value) ||
        is_object($value)
    ) {
        return false;
    }

    if (
        !is_int($value) &&
        !ctype_digit((string) $value)
    ) {
        return false;
    }

    return (int) $value > 0;
}


/*
|--------------------------------------------------------------------------
| Helper Function - String Value
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
| Validate room_id
|--------------------------------------------------------------------------
*/

if (!isset($_POST['room_id'])) {

    sendError(
        422,
        'room_id is required.'
    );
}

if (!isPositiveInteger($_POST['room_id'])) {

    sendError(
        422,
        'room_id must be a valid positive integer.'
    );
}

$roomId = (int) $_POST['room_id'];


/*
|--------------------------------------------------------------------------
| Validate alt_text
|--------------------------------------------------------------------------
|
| Optional
|
*/

$altText = null;

if (isset($_POST['alt_text'])) {

    $altText = getStringValue(
        $_POST['alt_text']
    );

    if ($altText === null) {

        sendError(
            422,
            'alt_text must be a valid string.'
        );
    }

    if (strlen($altText) > 255) {

        sendError(
            422,
            'alt_text cannot exceed 255 characters.'
        );
    }

    if ($altText === '') {
        $altText = null;
    }
}


/*
|--------------------------------------------------------------------------
| Validate is_primary
|--------------------------------------------------------------------------
|
| Accepted:
| 0
| 1
| true
| false
|
*/

$isPrimary = 0;

if (isset($_POST['is_primary'])) {

    $primaryInput = $_POST['is_primary'];

    if (
        $primaryInput === '1' ||
        $primaryInput === 1 ||
        $primaryInput === true ||
        $primaryInput === 'true'
    ) {

        $isPrimary = 1;

    } elseif (
        $primaryInput === '0' ||
        $primaryInput === 0 ||
        $primaryInput === false ||
        $primaryInput === 'false'
    ) {

        $isPrimary = 0;

    } else {

        sendError(
            422,
            'is_primary must be 0 or 1.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Validate sort_order
|--------------------------------------------------------------------------
*/

$sortOrder = 0;

if (isset($_POST['sort_order'])) {

    if (
        !ctype_digit(
            (string) $_POST['sort_order']
        )
    ) {

        sendError(
            422,
            'sort_order must be a valid non-negative integer.'
        );
    }

    $sortOrder = (int) $_POST['sort_order'];

    if ($sortOrder > 100000) {

        sendError(
            422,
            'sort_order cannot exceed 100000.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Check Uploaded File
|--------------------------------------------------------------------------
*/

if (!isset($_FILES['image'])) {

    sendError(
        422,
        'image file is required.'
    );
}


/*
|--------------------------------------------------------------------------
| Validate Upload Structure
|--------------------------------------------------------------------------
*/

if (
    !is_array($_FILES['image']) ||
    !isset(
        $_FILES['image']['error'],
        $_FILES['image']['tmp_name'],
        $_FILES['image']['name'],
        $_FILES['image']['size']
    )
) {

    sendError(
        422,
        'Invalid image upload.'
    );
}


/*
|--------------------------------------------------------------------------
| Upload Error Validation
|--------------------------------------------------------------------------
*/

$uploadError =
    (int) $_FILES['image']['error'];

if (
    $uploadError !== UPLOAD_ERR_OK
) {

    $uploadErrorMessages = [

        UPLOAD_ERR_INI_SIZE =>
            'Uploaded image exceeds the server upload limit.',

        UPLOAD_ERR_FORM_SIZE =>
            'Uploaded image exceeds the allowed form size.',

        UPLOAD_ERR_PARTIAL =>
            'Image upload was incomplete.',

        UPLOAD_ERR_NO_FILE =>
            'No image file was uploaded.',

        UPLOAD_ERR_NO_TMP_DIR =>
            'Temporary upload directory is missing.',

        UPLOAD_ERR_CANT_WRITE =>
            'Failed to write uploaded image.',

        UPLOAD_ERR_EXTENSION =>
            'Image upload was blocked by a server extension.'
    ];

    sendError(
        422,
        $uploadErrorMessages[$uploadError]
        ?? 'Image upload failed.'
    );
}


/*
|--------------------------------------------------------------------------
| File Information
|--------------------------------------------------------------------------
*/

$originalFileName =
    $_FILES['image']['name'];

$tmpFile =
    $_FILES['image']['tmp_name'];

$fileSize =
    (int) $_FILES['image']['size'];


/*
|--------------------------------------------------------------------------
| Validate Temporary File
|--------------------------------------------------------------------------
*/

if (
    $tmpFile === '' ||
    !is_uploaded_file($tmpFile)
) {

    sendError(
        422,
        'Invalid uploaded image.'
    );
}


/*
|--------------------------------------------------------------------------
| Validate File Size
|--------------------------------------------------------------------------
|
| Maximum = 5 MB
|
*/

$maxImageSize = 5 * 1024 * 1024;

if ($fileSize <= 0) {

    sendError(
        422,
        'Uploaded image is empty.'
    );
}

if ($fileSize > $maxImageSize) {

    sendError(
        422,
        'Image size cannot exceed 5 MB.'
    );
}


/*
|--------------------------------------------------------------------------
| Validate Original Filename
|--------------------------------------------------------------------------
*/

$originalFileName =
    getStringValue(
        $originalFileName
    );

if (
    $originalFileName === null ||
    $originalFileName === ''
) {

    sendError(
        422,
        'Invalid image filename.'
    );
}


/*
|--------------------------------------------------------------------------
| Get File Extension
|--------------------------------------------------------------------------
*/

$fileExtension =
    strtolower(
        pathinfo(
            $originalFileName,
            PATHINFO_EXTENSION
        )
    );


/*
|--------------------------------------------------------------------------
| Allowed Extensions
|--------------------------------------------------------------------------
*/

$allowedExtensions = [
    'jpg',
    'jpeg',
    'png',
    'webp'
];

if (
    !in_array(
        $fileExtension,
        $allowedExtensions,
        true
    )
) {

    sendError(
        422,
        'Invalid image format. Allowed formats are JPG, JPEG, PNG and WEBP.'
    );
}


/*
|--------------------------------------------------------------------------
| Detect MIME Type
|--------------------------------------------------------------------------
*/

$finfo = new finfo(
    FILEINFO_MIME_TYPE
);

$detectedMimeType =
    $finfo->file($tmpFile);

if ($detectedMimeType === false) {

    sendError(
        422,
        'Unable to determine image type.'
    );
}


/*
|--------------------------------------------------------------------------
| Allowed MIME Types
|--------------------------------------------------------------------------
*/

$allowedMimeTypes = [

    'image/jpeg' =>
        'jpg',

    'image/png' =>
        'png',

    'image/webp' =>
        'webp'
];


/*
|--------------------------------------------------------------------------
| MIME Validation
|--------------------------------------------------------------------------
*/

if (
    !array_key_exists(
        $detectedMimeType,
        $allowedMimeTypes
    )
) {

    sendError(
        422,
        'Invalid image MIME type. Only JPG, PNG and WEBP images are allowed.'
    );
}


/*
|--------------------------------------------------------------------------
| Extension + MIME Match
|--------------------------------------------------------------------------
*/

$expectedExtension =
    $allowedMimeTypes[
        $detectedMimeType
    ];

if (
    $fileExtension !==
    $expectedExtension
) {

    /*
    |--------------------------------------------------------------------------
    | JPEG special case
    |--------------------------------------------------------------------------
    */

    if (
        !(
            $detectedMimeType === 'image/jpeg' &&
            $fileExtension === 'jpeg'
        )
    ) {

        sendError(
            422,
            'Image extension does not match the actual image type.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Validate Actual Image
|--------------------------------------------------------------------------
*/

$imageInfo =
    @getimagesize($tmpFile);

if ($imageInfo === false) {

    sendError(
        422,
        'Uploaded file is not a valid image.'
    );
}


/*
|--------------------------------------------------------------------------
| Validate Image Width / Height
|--------------------------------------------------------------------------
*/

$imageWidth =
    (int) ($imageInfo[0] ?? 0);

$imageHeight =
    (int) ($imageInfo[1] ?? 0);

if (
    $imageWidth <= 0 ||
    $imageHeight <= 0
) {

    sendError(
        422,
        'Invalid image dimensions.'
    );
}


/*
|--------------------------------------------------------------------------
| Maximum Image Dimensions
|--------------------------------------------------------------------------
*/

$maxImageWidth = 10000;
$maxImageHeight = 10000;

if (
    $imageWidth > $maxImageWidth ||
    $imageHeight > $maxImageHeight
) {

    sendError(
        422,
        'Image dimensions cannot exceed 10000 x 10000 pixels.'
    );
}


/*
|--------------------------------------------------------------------------
| Check Room Exists
|--------------------------------------------------------------------------
*/

$roomCheckStmt = $conn->prepare("
    SELECT
        r.id,
        r.room_number,
        r.name,
        r.status,
        rt.id AS room_type_id,
        rt.name AS room_type_name,
        rt.status AS room_type_status

    FROM rooms r

    INNER JOIN room_types rt
        ON rt.id = r.room_type_id

    WHERE r.id = ?

    LIMIT 1
");

if (!$roomCheckStmt) {

    sendError(
        500,
        'Failed to prepare room validation query.'
    );
}

$roomCheckStmt->bind_param(
    'i',
    $roomId
);

if (!$roomCheckStmt->execute()) {

    $roomCheckStmt->close();

    sendError(
        500,
        'Failed to check room.'
    );
}

$roomResult =
    $roomCheckStmt->get_result();

if (
    $roomResult->num_rows === 0
) {

    $roomCheckStmt->close();

    sendError(
        404,
        'Room not found.'
    );
}

$room =
    $roomResult->fetch_assoc();

$roomCheckStmt->close();


/*
|--------------------------------------------------------------------------
| Check Room Type
|--------------------------------------------------------------------------
*/

if (
    $room['room_type_status'] !==
    'active'
) {

    sendError(
        409,
        'Cannot upload image because the room type is inactive.',
        [
            'room_id' =>
                $roomId,

            'room_type_id' =>
                (int) $room['room_type_id'],

            'room_type_name' =>
                $room['room_type_name'],

            'room_type_status' =>
                $room['room_type_status']
        ]
    );
}


/*
|--------------------------------------------------------------------------
| Maximum Room Images
|--------------------------------------------------------------------------
|
| Maximum = 10 images per room
|
*/

$maxRoomImages = 10;

$countStmt = $conn->prepare("
    SELECT
        COUNT(*) AS total

    FROM room_images

    WHERE room_id = ?
");

if (!$countStmt) {

    sendError(
        500,
        'Failed to prepare image count query.'
    );
}

$countStmt->bind_param(
    'i',
    $roomId
);

if (!$countStmt->execute()) {

    $countStmt->close();

    sendError(
        500,
        'Failed to count room images.'
    );
}

$countResult =
    $countStmt->get_result();

$countRow =
    $countResult->fetch_assoc();

$currentImageCount =
    (int) ($countRow['total'] ?? 0);

$countStmt->close();


/*
|--------------------------------------------------------------------------
| Maximum Image Count Validation
|--------------------------------------------------------------------------
*/

if (
    $currentImageCount >=
    $maxRoomImages
) {

    sendError(
        409,
        'Maximum 10 images are allowed for a room.',
        [
            'room_id' =>
                $roomId,

            'current_image_count' =>
                $currentImageCount,

            'max_images' =>
                $maxRoomImages
        ]
    );
}


/*
|--------------------------------------------------------------------------
| Check Whether Room Already Has Primary Image
|--------------------------------------------------------------------------
*/

$primaryImageExists = false;

$primaryCheckStmt = $conn->prepare("
    SELECT
        id

    FROM room_images

    WHERE room_id = ?

      AND is_primary = 1

    LIMIT 1
");

if (!$primaryCheckStmt) {

    sendError(
        500,
        'Failed to prepare primary image validation query.'
    );
}

$primaryCheckStmt->bind_param(
    'i',
    $roomId
);

if (!$primaryCheckStmt->execute()) {

    $primaryCheckStmt->close();

    sendError(
        500,
        'Failed to check primary image.'
    );
}

$primaryResult =
    $primaryCheckStmt->get_result();

if (
    $primaryResult->num_rows > 0
) {

    $primaryImageExists = true;
}

$primaryCheckStmt->close();


/*
|--------------------------------------------------------------------------
| First Image Automatically Primary
|--------------------------------------------------------------------------
*/

if (
    $currentImageCount === 0
) {

    $isPrimary = 1;
}


/*
|--------------------------------------------------------------------------
| Room-Specific Image Directory
|--------------------------------------------------------------------------
|
| NEW STRUCTURE:
|
| uploads/
| └── rooms/
|     └── {room_id}/
|         └── image/
|
| Example:
|
| uploads/rooms/1/image/
| uploads/rooms/2/image/
| uploads/rooms/3/image/
|
*/

$uploadDirectory =
    __DIR__ .
    '/../uploads/rooms/' .
    $roomId .
    '/image/';


/*
|--------------------------------------------------------------------------
| Create Room-Specific Upload Directory
|--------------------------------------------------------------------------
*/

if (
    !is_dir($uploadDirectory)
) {

    if (
        !mkdir(
            $uploadDirectory,
            0755,
            true
        )
    ) {

        sendError(
            500,
            'Failed to create room image upload directory.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Check Directory Writable
|--------------------------------------------------------------------------
*/

if (
    !is_writable($uploadDirectory)
) {

    sendError(
        500,
        'Room image upload directory is not writable.'
    );
}


/*
|--------------------------------------------------------------------------
| Generate Safe Unique Filename
|--------------------------------------------------------------------------
*/

$randomName =
    bin2hex(
        random_bytes(16)
    );

$finalFileName =
    $randomName .
    '.' .
    $expectedExtension;


/*
|--------------------------------------------------------------------------
| Final Physical File Path
|--------------------------------------------------------------------------
*/

$destinationPath =
    $uploadDirectory .
    $finalFileName;


/*
|--------------------------------------------------------------------------
| Prevent Unexpected Collision
|--------------------------------------------------------------------------
*/

if (
    file_exists($destinationPath)
) {

    sendError(
        500,
        'Failed to generate a unique image filename.'
    );
}


/*
|--------------------------------------------------------------------------
| Relative Image Path
|--------------------------------------------------------------------------
|
| This value is stored in DB.
|
| Example:
|
| uploads/rooms/1/image/abc123.webp
|
*/

$imagePath =
    'uploads/rooms/' .
    $roomId .
    '/image/' .
    $finalFileName;


/*
|--------------------------------------------------------------------------
| Move Uploaded File
|--------------------------------------------------------------------------
*/

if (
    !move_uploaded_file(
        $tmpFile,
        $destinationPath
    )
) {

    sendError(
        500,
        'Failed to save uploaded image.'
    );
}


/*
|--------------------------------------------------------------------------
| Set File Permission
|--------------------------------------------------------------------------
*/

@chmod(
    $destinationPath,
    0644
);


/*
|--------------------------------------------------------------------------
| Database Transaction
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();

try {

    /*
    |--------------------------------------------------------------------------
    | If New Image Is Primary
    |--------------------------------------------------------------------------
    |
    | Remove old primary image.
    |
    */

    if ($isPrimary === 1) {

        $removePrimaryStmt =
            $conn->prepare("
                UPDATE room_images

                SET is_primary = 0

                WHERE room_id = ?

                  AND is_primary = 1
            ");

        if (!$removePrimaryStmt) {

            throw new Exception(
                'Failed to prepare primary image update.'
            );
        }

        $removePrimaryStmt->bind_param(
            'i',
            $roomId
        );

        if (
            !$removePrimaryStmt->execute()
        ) {

            $removePrimaryStmt->close();

            throw new Exception(
                'Failed to update existing primary image.'
            );
        }

        $removePrimaryStmt->close();
    }


    /*
    |--------------------------------------------------------------------------
    | Insert Image
    |--------------------------------------------------------------------------
    |
    | Actual room_images columns:
    |
    | room_id
    | image_path
    | alt_text
    | is_primary
    | sort_order
    |
    */

    $insertStmt = $conn->prepare("
        INSERT INTO room_images
        (
            room_id,
            image_path,
            alt_text,
            is_primary,
            sort_order
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?
        )
    ");

    if (!$insertStmt) {

        throw new Exception(
            'Failed to prepare image insert query.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Bind Parameters
    |--------------------------------------------------------------------------
    |
    | i = room_id
    | s = image_path
    | s = alt_text
    | i = is_primary
    | i = sort_order
    |
    */

    $insertStmt->bind_param(
        'issii',
        $roomId,
        $imagePath,
        $altText,
        $isPrimary,
        $sortOrder
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

        throw new Exception(
            'Failed to save image in database. Error ' .
            $errorCode .
            ': ' .
            $errorMessage
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Created Image ID
    |--------------------------------------------------------------------------
    */

    $imageId =
        (int) $conn->insert_id;

    $insertStmt->close();


    if ($imageId <= 0) {

        throw new Exception(
            'Image was not saved successfully.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Commit Transaction
    |--------------------------------------------------------------------------
    */

    $conn->commit();


    /*
    |--------------------------------------------------------------------------
    | Success Response
    |--------------------------------------------------------------------------
    */

    http_response_code(201);

    echo json_encode([

        'status' => true,

        'message' =>
            'Room image uploaded successfully.',

        'data' => [

            'image_id' =>
                $imageId,

            'room_id' =>
                $roomId,

            'room_number' =>
                $room['room_number'],

            'room_name' =>
                $room['name'],

            'image' => [

                'image_path' =>
                    $imagePath,

                'image_url' =>
                    $imagePath,

                'alt_text' =>
                    $altText,

                'is_primary' =>
                    (bool) $isPrimary,

                'sort_order' =>
                    $sortOrder,

                'original_file_name' =>
                    $originalFileName,

                'file_size_bytes' =>
                    $fileSize,

                'mime_type' =>
                    $detectedMimeType,

                'width' =>
                    $imageWidth,

                'height' =>
                    $imageHeight
            ],

            'total_images' =>
                $currentImageCount + 1,

            'max_images' =>
                $maxRoomImages,

            'uploaded_by' => [

                'user_id' =>
                    (int) $user['sub'],

                'role' =>
                    $user['role']
            ]
        ]

    ], JSON_UNESCAPED_UNICODE);

    exit;


} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | Rollback Database
    |--------------------------------------------------------------------------
    */

    $conn->rollback();


    /*
    |--------------------------------------------------------------------------
    | Delete Uploaded File
    |--------------------------------------------------------------------------
    |
    | Database insert failed,
    | so remove physical file.
    |
    */

    if (
        file_exists($destinationPath)
    ) {

        @unlink($destinationPath);
    }


    /*
    |--------------------------------------------------------------------------
    | Log Error
    |--------------------------------------------------------------------------
    */

    error_log(
        '[ROOM IMAGE UPLOAD] ' .
        $e->getMessage()
    );


    /*
    |--------------------------------------------------------------------------
    | Error Response
    |--------------------------------------------------------------------------
    */

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to upload room image.'
    ]);

    exit;
}