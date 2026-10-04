<?php

/*
|--------------------------------------------------------------------------
| CORS Headers
|--------------------------------------------------------------------------
*/

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: DELETE, POST, OPTIONS");
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
| Admin + Staff can delete room images.
|
*/

$user = requireRole([
    'admin',
    'staff'
]);


/*
|--------------------------------------------------------------------------
| Read Request Body
|--------------------------------------------------------------------------
*/

$rawInput = file_get_contents('php://input');

$data = [];

if (
    $rawInput !== false &&
    trim($rawInput) !== ''
) {

    $decodedInput =
        json_decode(
            $rawInput,
            true
        );

    if (
        json_last_error() !== JSON_ERROR_NONE
    ) {

        http_response_code(400);

        echo json_encode([
            'status' => false,
            'message' => 'Invalid JSON request body.'
        ]);

        exit;
    }

    if (
        !is_array($decodedInput)
    ) {

        http_response_code(400);

        echo json_encode([
            'status' => false,
            'message' => 'Request body must be a JSON object.'
        ]);

        exit;
    }

    $data = $decodedInput;
}


/*
|--------------------------------------------------------------------------
| POST Form Data Support
|--------------------------------------------------------------------------
|
| For POST requests, support form-data / x-www-form-urlencoded.
|
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    !empty($_POST)
) {

    foreach ($_POST as $key => $value) {
        $data[$key] = $value;
    }
}


/*
|--------------------------------------------------------------------------
| Reject Unknown Fields
|--------------------------------------------------------------------------
*/

$allowedFields = [
    'image_id',
    'room_id'
];

foreach ($data as $key => $value) {

    if (
        !in_array(
            $key,
            $allowedFields,
            true
        )
    ) {

        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => "Unknown field: {$key}."
        ]);

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Helper Function - Error Response
|--------------------------------------------------------------------------
*/

function sendError(
    $httpCode,
    $message,
    $data = null
) {

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
| Validate image_id
|--------------------------------------------------------------------------
*/

if (
    !isset($data['image_id'])
) {

    sendError(
        422,
        'image_id is required.'
    );
}


if (
    !isPositiveInteger(
        $data['image_id']
    )
) {

    sendError(
        422,
        'image_id must be a valid positive integer.'
    );
}


$imageId =
    (int) $data['image_id'];


/*
|--------------------------------------------------------------------------
| Validate room_id
|--------------------------------------------------------------------------
*/

if (
    !isset($data['room_id'])
) {

    sendError(
        422,
        'room_id is required.'
    );
}


if (
    !isPositiveInteger(
        $data['room_id']
    )
) {

    sendError(
        422,
        'room_id must be a valid positive integer.'
    );
}


$roomId =
    (int) $data['room_id'];


/*
|--------------------------------------------------------------------------
| Fetch Image + Room Details
|--------------------------------------------------------------------------
*/

$imageStmt = $conn->prepare("
    SELECT

        ri.id AS image_id,
        ri.room_id,
        ri.image_path,
        ri.alt_text,
        ri.is_primary,
        ri.sort_order,
        ri.created_at,

        r.room_number,
        r.name AS room_name,
        r.status AS room_status,

        rt.id AS room_type_id,
        rt.name AS room_type_name,
        rt.status AS room_type_status

    FROM room_images ri

    INNER JOIN rooms r
        ON r.id = ri.room_id

    INNER JOIN room_types rt
        ON rt.id = r.room_type_id

    WHERE ri.id = ?
      AND ri.room_id = ?

    LIMIT 1
");


if (!$imageStmt) {

    sendError(
        500,
        'Failed to prepare image validation query.'
    );
}


$imageStmt->bind_param(
    'ii',
    $imageId,
    $roomId
);


if (
    !$imageStmt->execute()
) {

    $imageStmt->close();

    sendError(
        500,
        'Failed to check room image.'
    );
}


$imageResult =
    $imageStmt->get_result();


if (
    $imageResult->num_rows === 0
) {

    $imageStmt->close();

    sendError(
        404,
        'Room image not found.'
    );
}


$image =
    $imageResult->fetch_assoc();


$imageStmt->close();


/*
|--------------------------------------------------------------------------
| Validate Room Type
|--------------------------------------------------------------------------
*/

if (
    $image['room_type_status'] !== 'active'
) {

    sendError(
        409,
        'Cannot delete image because the room type is inactive.',
        [
            'room_id' =>
                $roomId,

            'room_type_id' =>
                (int) $image['room_type_id'],

            'room_type_name' =>
                $image['room_type_name'],

            'room_type_status' =>
                $image['room_type_status']
        ]
    );
}


/*
|--------------------------------------------------------------------------
| Validate Stored Image Path
|--------------------------------------------------------------------------
|
| Expected:
|
| uploads/rooms/{room_id}/image/{filename}
|
*/

$storedImagePath =
    trim(
        (string) $image['image_path']
    );


if (
    $storedImagePath === ''
) {

    sendError(
        500,
        'Image path is missing in database.'
    );
}


/*
|--------------------------------------------------------------------------
| Expected Relative Directory
|--------------------------------------------------------------------------
*/

$expectedRelativeDirectory =
    'uploads/rooms/' .
    $roomId .
    '/image/';


/*
|--------------------------------------------------------------------------
| Validate Image Path Prefix
|--------------------------------------------------------------------------
*/

if (
    strpos(
        $storedImagePath,
        $expectedRelativeDirectory
    ) !== 0
) {

    sendError(
        500,
        'Invalid image path stored for this room.'
    );
}


/*
|--------------------------------------------------------------------------
| Get Stored Filename
|--------------------------------------------------------------------------
*/

$storedFileName =
    substr(
        $storedImagePath,
        strlen(
            $expectedRelativeDirectory
        )
    );


/*
|--------------------------------------------------------------------------
| Validate Stored Filename
|--------------------------------------------------------------------------
*/

if (
    $storedFileName === ''
) {

    sendError(
        500,
        'Invalid image filename stored in database.'
    );
}


/*
|--------------------------------------------------------------------------
| Prevent Path Traversal
|--------------------------------------------------------------------------
*/

if (
    $storedFileName !==
    basename($storedFileName)
) {

    sendError(
        500,
        'Invalid image path detected.'
    );
}


/*
|--------------------------------------------------------------------------
| Prevent Directory Traversal Characters
|--------------------------------------------------------------------------
*/

if (
    strpos($storedFileName, '/') !== false ||
    strpos($storedFileName, '\\') !== false ||
    strpos($storedFileName, '..') !== false
) {

    sendError(
        500,
        'Invalid image filename detected.'
    );
}


/*
|--------------------------------------------------------------------------
| Validate File Extension
|--------------------------------------------------------------------------
*/

$fileExtension =
    strtolower(
        pathinfo(
            $storedFileName,
            PATHINFO_EXTENSION
        )
    );


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
        500,
        'Invalid image file extension.'
    );
}


/*
|--------------------------------------------------------------------------
| Build Physical Upload Directory
|--------------------------------------------------------------------------
*/

$projectRoot =
    dirname(__DIR__);


$uploadDirectory =
    $projectRoot .
    '/uploads/rooms/' .
    $roomId .
    '/image/';


/*
|--------------------------------------------------------------------------
| Validate Upload Directory
|--------------------------------------------------------------------------
*/

if (
    !is_dir($uploadDirectory)
) {

    sendError(
        500,
        'Room image directory does not exist.'
    );
}


/*
|--------------------------------------------------------------------------
| Resolve Directory Path
|--------------------------------------------------------------------------
*/

$realUploadDirectory =
    realpath(
        $uploadDirectory
    );


if (
    $realUploadDirectory === false
) {

    sendError(
        500,
        'Unable to resolve room image directory.'
    );
}


/*
|--------------------------------------------------------------------------
| Build Physical Image Path
|--------------------------------------------------------------------------
*/

$physicalImagePath =
    $uploadDirectory .
    $storedFileName;


/*
|--------------------------------------------------------------------------
| Resolve Physical File Path
|--------------------------------------------------------------------------
*/

$realImagePath =
    realpath(
        $physicalImagePath
    );


/*
|--------------------------------------------------------------------------
| Validate Physical File Location
|--------------------------------------------------------------------------
|
| Prevent deleting a file outside:
|
| uploads/rooms/{room_id}/image/
|
*/

if (
    $realImagePath !== false
) {

    $normalizedDirectory =
        rtrim(
            $realUploadDirectory,
            DIRECTORY_SEPARATOR
        ) .
        DIRECTORY_SEPARATOR;

    $normalizedImagePath =
        $realImagePath;


    if (
        strpos(
            $normalizedImagePath,
            $normalizedDirectory
        ) !== 0
    ) {

        sendError(
            500,
            'Image file is outside the allowed room directory.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Check Whether Physical File Exists
|--------------------------------------------------------------------------
*/

$physicalFileExists =
    file_exists(
        $physicalImagePath
    );


/*
|--------------------------------------------------------------------------
| Check Whether Physical Path Is A File
|--------------------------------------------------------------------------
*/

if (
    $physicalFileExists &&
    !is_file($physicalImagePath)
) {

    sendError(
        500,
        'Stored image path is not a valid file.'
    );
}


/*
|--------------------------------------------------------------------------
| Get Other Room Images
|--------------------------------------------------------------------------
|
| If deleting primary image:
| another image will automatically
| become primary.
|
*/

$replacementImageId = null;

if (
    (int) $image['is_primary'] === 1
) {

    $replacementStmt =
        $conn->prepare("
            SELECT
                id

            FROM room_images

            WHERE room_id = ?

              AND id != ?

            ORDER BY
                sort_order ASC,
                id ASC

            LIMIT 1
        ");


    if (!$replacementStmt) {

        sendError(
            500,
            'Failed to prepare replacement image query.'
        );
    }


    $replacementStmt->bind_param(
        'ii',
        $roomId,
        $imageId
    );


    if (
        !$replacementStmt->execute()
    ) {

        $replacementStmt->close();

        sendError(
            500,
            'Failed to find replacement primary image.'
        );
    }


    $replacementResult =
        $replacementStmt->get_result();


    if (
        $replacementResult->num_rows > 0
    ) {

        $replacementRow =
            $replacementResult->fetch_assoc();

        $replacementImageId =
            (int) $replacementRow['id'];
    }


    $replacementStmt->close();
}


/*
|--------------------------------------------------------------------------
| Database Transaction
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();


try {

    /*
    |--------------------------------------------------------------------------
    | Delete Image Record
    |--------------------------------------------------------------------------
    */

    $deleteStmt =
        $conn->prepare("
            DELETE FROM room_images

            WHERE id = ?
              AND room_id = ?

            LIMIT 1
        ");


    if (!$deleteStmt) {

        throw new Exception(
            'Failed to prepare image delete query.'
        );
    }


    $deleteStmt->bind_param(
        'ii',
        $imageId,
        $roomId
    );


    if (
        !$deleteStmt->execute()
    ) {

        $errorCode =
            $deleteStmt->errno;

        $errorMessage =
            $deleteStmt->error;

        $deleteStmt->close();

        throw new Exception(
            'Failed to delete image from database. Error ' .
            $errorCode .
            ': ' .
            $errorMessage
        );
    }


    $affectedRows =
        $deleteStmt->affected_rows;


    $deleteStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Validate Delete Result
    |--------------------------------------------------------------------------
    */

    if (
        $affectedRows !== 1
    ) {

        throw new Exception(
            'Image was not deleted from database.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Make Replacement Image Primary
    |--------------------------------------------------------------------------
    */

    if (
        $replacementImageId !== null
    ) {

        $replacementStmt =
            $conn->prepare("
                UPDATE room_images

                SET is_primary = 1

                WHERE id = ?
                  AND room_id = ?

                LIMIT 1
            ");


        if (!$replacementStmt) {

            throw new Exception(
                'Failed to prepare replacement primary image update.'
            );
        }


        $replacementStmt->bind_param(
            'ii',
            $replacementImageId,
            $roomId
        );


        if (
            !$replacementStmt->execute()
        ) {

            $replacementStmt->close();

            throw new Exception(
                'Failed to make replacement image primary.'
            );
        }


        $replacementAffectedRows =
            $replacementStmt->affected_rows;


        $replacementStmt->close();


        if (
            $replacementAffectedRows !== 1
        ) {

            throw new Exception(
                'Replacement image could not be made primary.'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Commit Transaction
    |--------------------------------------------------------------------------
    */

    $conn->commit();


} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | Rollback
    |--------------------------------------------------------------------------
    */

    $conn->rollback();


    /*
    |--------------------------------------------------------------------------
    | Log Error
    |--------------------------------------------------------------------------
    */

    error_log(
        '[ROOM IMAGE DELETE] ' .
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
        'message' => 'Failed to delete room image.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Delete Physical Image File
|--------------------------------------------------------------------------
|
| DB record is already successfully deleted.
|
| If physical file is missing, DB deletion
| is still considered successful because
| there is no file left to delete.
|
*/

$fileDeleted =
    false;

$fileDeleteError =
    null;


if (
    $physicalFileExists
) {

    if (
        @unlink(
            $physicalImagePath
        )
    ) {

        $fileDeleted = true;

    } else {

        $fileDeleteError =
            'Database record was deleted, but the physical image file could not be deleted.';
    }

} else {

    /*
    |--------------------------------------------------------------------------
    | Physical File Already Missing
    |--------------------------------------------------------------------------
    */

    $fileDeleted = false;
}


/*
|--------------------------------------------------------------------------
| Check Remaining Images
|--------------------------------------------------------------------------
*/

$remainingStmt =
    $conn->prepare("
        SELECT
            COUNT(*) AS total

        FROM room_images

        WHERE room_id = ?
    ");


$remainingImageCount = 0;


if ($remainingStmt) {

    $remainingStmt->bind_param(
        'i',
        $roomId
    );


    if (
        $remainingStmt->execute()
    ) {

        $remainingResult =
            $remainingStmt->get_result();

        $remainingRow =
            $remainingResult->fetch_assoc();

        $remainingImageCount =
            (int) (
                $remainingRow['total']
                ?? 0
            );
    }


    $remainingStmt->close();
}


/*
|--------------------------------------------------------------------------
| Success Response
|--------------------------------------------------------------------------
*/

http_response_code(200);


$response = [

    'status' => true,

    'message' =>
        'Room image deleted successfully.',

    'data' => [

        'image_id' =>
            $imageId,

        'room_id' =>
            $roomId,

        'room_number' =>
            $image['room_number'],

        'room_name' =>
            $image['room_name'],

        'deleted_image' => [

            'image_path' =>
                $storedImagePath,

            'is_primary' =>
                (bool) $image['is_primary'],

            'sort_order' =>
                (int) $image['sort_order']
        ],

        'primary_image_replaced' =>
            $replacementImageId !== null,

        'replacement_image_id' =>
            $replacementImageId,

        'physical_file_deleted' =>
            $fileDeleted,

        'physical_file_existed' =>
            $physicalFileExists,

        'remaining_images' =>
            $remainingImageCount,

        'deleted_by' => [

            'user_id' =>
                (int) $user['sub'],

            'role' =>
                $user['role']
        ]
    ]
];


/*
|--------------------------------------------------------------------------
| Physical File Warning
|--------------------------------------------------------------------------
*/

if (
    $fileDeleteError !== null
) {

    $response['warning'] =
        $fileDeleteError;
}


/*
|--------------------------------------------------------------------------
| Output Response
|--------------------------------------------------------------------------
*/

echo json_encode(
    $response,
    JSON_UNESCAPED_UNICODE
);

exit;