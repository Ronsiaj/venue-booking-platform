<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: DELETE, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/middleware.php';

requireAdminOrStaff();

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
| Read JSON Body
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Allowed Fields
|--------------------------------------------------------------------------
*/

$allowedFields = [
    'package_id'
];

$unknownFields = array_diff(
    array_keys($data),
    $allowedFields
);

if (!empty($unknownFields)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Unknown field(s) provided.',
        'unknown_fields' => array_values($unknownFields)
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Required Field
|--------------------------------------------------------------------------
*/

if (!array_key_exists('package_id', $data)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'package_id is required.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Validate Package ID
|--------------------------------------------------------------------------
*/

$packageId = $data['package_id'];

if (
    filter_var($packageId, FILTER_VALIDATE_INT) === false ||
    (int) $packageId <= 0
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'package_id must be a valid positive integer.'
    ]);

    exit;
}

$packageId = (int) $packageId;


/*
|--------------------------------------------------------------------------
| Fetch Package
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        name,
        description,
        meal_type,
        image_path,
        status,
        created_at,
        updated_at
    FROM dining_packages
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare package query.'
    ]);

    exit;
}

$stmt->bind_param("i", $packageId);

if (!$stmt->execute()) {
    $stmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to fetch dining package.'
    ]);

    exit;
}

$result = $stmt->get_result();
$package = $result->fetch_assoc();

$stmt->close();

if (!$package) {
    http_response_code(404);

    echo json_encode([
        'status' => false,
        'message' => 'Dining package not found.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Store Existing Image Path
|--------------------------------------------------------------------------
*/

$oldImagePath = $package['image_path'];


/*
|--------------------------------------------------------------------------
| Count Package Items
|--------------------------------------------------------------------------
|
| FK has ON DELETE CASCADE.
| These mappings will be automatically deleted when
| the package is deleted.
|
*/

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total_items
    FROM dining_package_items
    WHERE package_id = ?
");

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to prepare package item query.'
    ]);

    exit;
}

$stmt->bind_param("i", $packageId);

if (!$stmt->execute()) {
    $stmt->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to check package items.'
    ]);

    exit;
}

$itemResult = $stmt->get_result();
$itemRow = $itemResult->fetch_assoc();

$stmt->close();

$totalItems = (int) ($itemRow['total_items'] ?? 0);


/*
|--------------------------------------------------------------------------
| Begin Transaction
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();

try {

    /*
    |--------------------------------------------------------------------------
    | Lock Package
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            id,
            name,
            description,
            meal_type,
            image_path,
            status,
            created_at,
            updated_at
        FROM dining_packages
        WHERE id = ?
        FOR UPDATE
    ");

    if (!$stmt) {
        throw new Exception(
            'Failed to lock dining package.'
        );
    }

    $stmt->bind_param("i", $packageId);

    if (!$stmt->execute()) {
        $stmt->close();

        throw new Exception(
            'Failed to lock dining package.'
        );
    }

    $lockedResult = $stmt->get_result();
    $lockedPackage = $lockedResult->fetch_assoc();

    $stmt->close();

    if (!$lockedPackage) {
        throw new Exception(
            'Dining package no longer exists.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Re-fetch Current Image Path
    |--------------------------------------------------------------------------
    */

    $oldImagePath = $lockedPackage['image_path'];


    /*
    |--------------------------------------------------------------------------
    | Re-check Package Items
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total_items
        FROM dining_package_items
        WHERE package_id = ?
    ");

    if (!$stmt) {
        throw new Exception(
            'Failed to check package items.'
        );
    }

    $stmt->bind_param("i", $packageId);

    if (!$stmt->execute()) {
        $stmt->close();

        throw new Exception(
            'Failed to check package items.'
        );
    }

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $stmt->close();

    $totalItems = (int) ($row['total_items'] ?? 0);


    /*
    |--------------------------------------------------------------------------
    | Delete Package
    |--------------------------------------------------------------------------
    |
    | dining_package_items are automatically deleted
    | because of ON DELETE CASCADE.
    |
    */

    $stmt = $conn->prepare("
        DELETE FROM dining_packages
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception(
            'Failed to prepare package delete query.'
        );
    }

    $stmt->bind_param("i", $packageId);

    if (!$stmt->execute()) {

        $errorCode = $stmt->errno;
        $stmt->close();

        if ($errorCode === 1451) {
            throw new Exception(
                'Dining package cannot be deleted because related records exist.'
            );
        }

        throw new Exception(
            'Failed to delete dining package.'
        );
    }

    $affectedRows = $stmt->affected_rows;

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Verify Delete
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT id
        FROM dining_packages
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception(
            'Failed to verify package deletion.'
        );
    }

    $stmt->bind_param("i", $packageId);

    if (!$stmt->execute()) {
        $stmt->close();

        throw new Exception(
            'Failed to verify package deletion.'
        );
    }

    $verifyResult = $stmt->get_result();
    $deletedCheck = $verifyResult->fetch_assoc();

    $stmt->close();

    if ($deletedCheck) {
        throw new Exception(
            'Dining package deletion could not be verified.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Verify Item Mappings Are Deleted
    |--------------------------------------------------------------------------
    |
    | Because the FK is ON DELETE CASCADE.
    |
    */

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS remaining_items
        FROM dining_package_items
        WHERE package_id = ?
    ");

    if (!$stmt) {
        throw new Exception(
            'Failed to verify package item deletion.'
        );
    }

    $stmt->bind_param("i", $packageId);

    if (!$stmt->execute()) {
        $stmt->close();

        throw new Exception(
            'Failed to verify package item deletion.'
        );
    }

    $remainingResult = $stmt->get_result();
    $remainingRow = $remainingResult->fetch_assoc();

    $stmt->close();

    $remainingItems = (int) (
        $remainingRow['remaining_items'] ?? 0
    );

    if ($remainingItems > 0) {
        throw new Exception(
            'Package food item mappings were not deleted.'
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
    | Delete Physical Image
    |--------------------------------------------------------------------------
    |
    | DB deletion is already committed.
    | Physical file deletion is handled separately.
    |
    */

    $imageDeleted = false;
    $imageWarning = null;

    if (
        $oldImagePath !== null &&
        trim($oldImagePath) !== ''
    ) {

        $oldImagePath = trim($oldImagePath);


        /*
        |--------------------------------------------------------------------------
        | Security Validation
        |--------------------------------------------------------------------------
        */

        $invalidPath =
            str_contains($oldImagePath, '..') ||
            str_contains($oldImagePath, "\0") ||
            str_starts_with($oldImagePath, '/') ||
            preg_match('/^[A-Za-z]:[\/\\\\]/', $oldImagePath);


        if ($invalidPath) {

            $imageWarning =
                'Image path was invalid. Database record was deleted, but physical image was not removed.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Expected Directory
            |--------------------------------------------------------------------------
            */

            $projectRoot = dirname(__DIR__, 2);

            $expectedPrefix =
                'uploads/dining_packages/'
                . $packageId
                . '/image/';


            /*
            |--------------------------------------------------------------------------
            | Validate Relative Path
            |--------------------------------------------------------------------------
            */

            if (
                !str_starts_with(
                    $oldImagePath,
                    $expectedPrefix
                )
            ) {

                $imageWarning =
                    'Image path did not match the expected package directory. Database record was deleted, but physical image was not removed.';

            } else {

                $fileName = basename($oldImagePath);


                /*
                |--------------------------------------------------------------------------
                | Validate File Name
                |--------------------------------------------------------------------------
                */

                if (
                    !preg_match(
                        '/^[a-fA-F0-9]{32}\.(jpg|jpeg|png|webp)$/',
                        $fileName
                    )
                ) {

                    $imageWarning =
                        'Image filename was invalid. Database record was deleted, but physical image was not removed.';

                } else {

                    $physicalImagePath =
                        $projectRoot
                        . '/'
                        . $oldImagePath;


                    /*
                    |--------------------------------------------------------------------------
                    | Final Path Safety Check
                    |--------------------------------------------------------------------------
                    */

                    $expectedDirectory =
                        realpath(
                            $projectRoot
                            . '/uploads/dining_packages/'
                            . $packageId
                            . '/image'
                        );


                    if ($expectedDirectory !== false) {

                        $realFilePath =
                            realpath(
                                $physicalImagePath
                            );


                        if (
                            $realFilePath !== false &&
                            str_starts_with(
                                $realFilePath,
                                $expectedDirectory
                                . DIRECTORY_SEPARATOR
                            )
                        ) {

                            if (
                                is_file(
                                    $realFilePath
                                )
                            ) {

                                if (
                                    @unlink(
                                        $realFilePath
                                    )
                                ) {
                                    $imageDeleted = true;
                                } else {
                                    $imageWarning =
                                        'Package deleted, but physical image could not be removed.';
                                }

                            } else {

                                $imageWarning =
                                    'Package deleted. Physical image file was already missing.';
                            }

                        } else {

                            $imageWarning =
                                'Package deleted, but physical image path could not be safely resolved.';
                        }

                    } else {

                        $imageWarning =
                            'Package deleted, but image directory does not exist.';
                    }
                }
            }
        }

    } else {

        $imageDeleted = true;
    }


    /*
    |--------------------------------------------------------------------------
    | Success Response
    |--------------------------------------------------------------------------
    */

    http_response_code(200);

    echo json_encode([
        'status' => true,
        'message' => 'Dining package deleted successfully.',
        'data' => [
            'deleted_package' => [
                'id' => (int) $lockedPackage['id'],
                'name' => $lockedPackage['name'],
                'description' => $lockedPackage['description'],
                'meal_type' => $lockedPackage['meal_type'],
                'image_path' => $oldImagePath,
                'status' => $lockedPackage['status'],
                'created_at' => $lockedPackage['created_at'],
                'updated_at' => $lockedPackage['updated_at']
            ],
            'food_item_mappings_deleted' => $totalItems,
            'physical_image_deleted' => $imageDeleted,
            'image_warning' => $imageWarning,
            'affected_rows' => $affectedRows,
            'deleted_by' => [
                'user_id' => (int) (
                    $GLOBALS['auth_user']['sub']
                    ?? 0
                ),
                'role' => (
                    $GLOBALS['auth_user']['role']
                    ?? null
                )
            ]
        ]
    ]);

    exit;


} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | Rollback
    |--------------------------------------------------------------------------
    */

    $conn->rollback();


    /*
    |--------------------------------------------------------------------------
    | Dependency Error
    |--------------------------------------------------------------------------
    */

    if (
        $e->getCode() === 1451 ||
        str_contains(
            strtolower($e->getMessage()),
            'related records'
        )
    ) {

        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' => $e->getMessage(),
            'package_id' => $packageId
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | General Error
    |--------------------------------------------------------------------------
    */

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => $e->getMessage()
    ]);

    exit;
}