<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/middleware.php';

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: DELETE, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (!in_array($_SERVER['REQUEST_METHOD'], ['DELETE', 'POST'], true)) {

    http_response_code(405);

    echo json_encode([
        "status" => false,
        "message" => "Only DELETE or POST method is allowed."
    ]);

    exit;
}

try {

    /*
    |--------------------------------------------------------------------------
    | ADMIN / STAFF AUTHENTICATION
    |--------------------------------------------------------------------------
    */

    requireAdminOrStaff();

    global $conn;

    if (!isset($conn) || !($conn instanceof mysqli)) {

        throw new Exception(
            "Database connection is not available."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | READ REQUEST DATA
    |--------------------------------------------------------------------------
    */

    $data = [];

    /*
    | DELETE request
    | Supports:
    | 1. JSON body
    | 2. Query parameter package_id
    |
    | POST request
    | Supports:
    | 1. JSON body
    | 2. Form-data / x-www-form-urlencoded
    */

    $contentType =
        $_SERVER['CONTENT_TYPE'] ?? '';

    if (
        stripos(
            $contentType,
            'application/json'
        ) !== false
    ) {

        $rawBody =
            file_get_contents("php://input");

        if ($rawBody === false) {

            http_response_code(400);

            echo json_encode([
                "status" => false,
                "message" => "Unable to read request body."
            ]);

            exit;
        }

        if (trim($rawBody) === '') {

            http_response_code(400);

            echo json_encode([
                "status" => false,
                "message" => "Request body cannot be empty."
            ]);

            exit;
        }

        $decodedData =
            json_decode(
                $rawBody,
                true
            );

        if (
            json_last_error() !== JSON_ERROR_NONE ||
            !is_array($decodedData)
        ) {

            http_response_code(400);

            echo json_encode([
                "status" => false,
                "message" => "Request body must contain valid JSON."
            ]);

            exit;
        }

        $data = $decodedData;

    } else {

        /*
        | Form-data / x-www-form-urlencoded
        */

        $data = $_POST;

        /*
        | For DELETE, PHP may not populate $_POST.
        | Try query string as fallback.
        */

        if (
            $_SERVER['REQUEST_METHOD'] === 'DELETE' &&
            empty($data) &&
            isset($_GET['package_id'])
        ) {

            $data = [
                'package_id' => $_GET['package_id']
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ALLOWED FIELDS
    |--------------------------------------------------------------------------
    */

    foreach ($data as $key => $value) {

        if ($key !== 'package_id') {

            http_response_code(400);

            echo json_encode([
                "status" => false,
                "message" => "Unknown field: {$key}"
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PACKAGE ID REQUIRED
    |--------------------------------------------------------------------------
    */

    if (!array_key_exists('package_id', $data)) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "package_id is required."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | PACKAGE ID VALIDATION
    |--------------------------------------------------------------------------
    */

    $packageIdRaw =
        trim((string)$data['package_id']);

    if (
        $packageIdRaw === '' ||
        !ctype_digit($packageIdRaw)
    ) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "package_id must be a positive integer."
        ]);

        exit;
    }

    $packageId =
        (int)$packageIdRaw;

    if ($packageId < 1) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "package_id must be greater than 0."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | START TRANSACTION
    |--------------------------------------------------------------------------
    */

    $conn->begin_transaction();

    try {

        /*
        |--------------------------------------------------------------------------
        | FETCH + LOCK PACKAGE
        |--------------------------------------------------------------------------
        */

        $packageSql = "
            SELECT
                id,
                name,
                description,
                rate,
                rate_unit,
                gst_rate,
                price_includes_gst,
                minimum_quantity,
                image_path,
                status,
                created_at,
                updated_at
            FROM catering_packages
            WHERE id = ?
            FOR UPDATE
        ";

        $packageStmt =
            $conn->prepare($packageSql);

        if (!$packageStmt) {

            throw new Exception(
                "Failed to prepare package lookup."
            );
        }

        $packageStmt->bind_param(
            "i",
            $packageId
        );

        if (!$packageStmt->execute()) {

            $error =
                $packageStmt->error;

            $packageStmt->close();

            throw new Exception(
                "Failed to fetch catering package: " .
                $error
            );
        }

        $packageResult =
            $packageStmt->get_result();

        $package =
            $packageResult->fetch_assoc();

        $packageStmt->close();

        /*
        |--------------------------------------------------------------------------
        | PACKAGE NOT FOUND
        |--------------------------------------------------------------------------
        */

        if (!$package) {

            $conn->rollback();

            http_response_code(404);

            echo json_encode([
                "status" => false,
                "message" => "Catering package not found.",
                "package_id" => $packageId
            ]);

            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | COUNT PACKAGE ITEMS
        |--------------------------------------------------------------------------
        */

        $itemCountSql = "
            SELECT COUNT(*) AS total
            FROM catering_package_items
            WHERE package_id = ?
        ";

        $itemCountStmt =
            $conn->prepare($itemCountSql);

        if (!$itemCountStmt) {

            throw new Exception(
                "Failed to prepare package item count."
            );
        }

        $itemCountStmt->bind_param(
            "i",
            $packageId
        );

        if (!$itemCountStmt->execute()) {

            $error =
                $itemCountStmt->error;

            $itemCountStmt->close();

            throw new Exception(
                "Failed to count package items: " .
                $error
            );
        }

        $itemCountResult =
            $itemCountStmt->get_result();

        $itemCountRow =
            $itemCountResult->fetch_assoc();

        $itemCountStmt->close();

        $packageItemCount =
            (int)($itemCountRow['total'] ?? 0);

        /*
        |--------------------------------------------------------------------------
        | COUNT LEAD REFERENCES
        |--------------------------------------------------------------------------
        |
        | Catering lead package references use ON DELETE SET NULL.
        | Therefore deleting this package does NOT delete leads.
        |
        */

        $leadCountSql = "
            SELECT COUNT(*) AS total
            FROM catering_lead_details
            WHERE catering_package_id = ?
        ";

        /*
        | If the actual column is named package_id instead of
        | catering_package_id, use the schema-specific column.
        |
        | Current project schema uses catering_package_id.
        */

        $leadCountStmt =
            $conn->prepare($leadCountSql);

        if (!$leadCountStmt) {

            throw new Exception(
                "Failed to prepare package lead reference count."
            );
        }

        $leadCountStmt->bind_param(
            "i",
            $packageId
        );

        if (!$leadCountStmt->execute()) {

            $error =
                $leadCountStmt->error;

            $leadCountStmt->close();

            throw new Exception(
                "Failed to count package lead references: " .
                $error
            );
        }

        $leadCountResult =
            $leadCountStmt->get_result();

        $leadCountRow =
            $leadCountResult->fetch_assoc();

        $leadCountStmt->close();

        $leadReferenceCount =
            (int)($leadCountRow['total'] ?? 0);

        /*
        |--------------------------------------------------------------------------
        | DELETE PACKAGE
        |--------------------------------------------------------------------------
        */

        $deleteSql = "
            DELETE FROM catering_packages
            WHERE id = ?
        ";

        $deleteStmt =
            $conn->prepare($deleteSql);

        if (!$deleteStmt) {

            throw new Exception(
                "Failed to prepare package deletion."
            );
        }

        $deleteStmt->bind_param(
            "i",
            $packageId
        );

        if (!$deleteStmt->execute()) {

            $error =
                $deleteStmt->error;

            $deleteStmt->close();

            throw new Exception(
                "Failed to delete catering package: " .
                $error
            );
        }

        $affectedRows =
            $deleteStmt->affected_rows;

        $deleteStmt->close();

        /*
        |--------------------------------------------------------------------------
        | VERIFY DELETE
        |--------------------------------------------------------------------------
        */

        if ($affectedRows !== 1) {

            throw new Exception(
                "Catering package was not deleted."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | VERIFY PACKAGE IS ACTUALLY DELETED
        |--------------------------------------------------------------------------
        */

        $verifySql = "
            SELECT id
            FROM catering_packages
            WHERE id = ?
            LIMIT 1
        ";

        $verifyStmt =
            $conn->prepare($verifySql);

        if (!$verifyStmt) {

            throw new Exception(
                "Failed to prepare deletion verification."
            );
        }

        $verifyStmt->bind_param(
            "i",
            $packageId
        );

        if (!$verifyStmt->execute()) {

            $error =
                $verifyStmt->error;

            $verifyStmt->close();

            throw new Exception(
                "Failed to verify package deletion: " .
                $error
            );
        }

        $verifyResult =
            $verifyStmt->get_result();

        $stillExists =
            $verifyResult->num_rows > 0;

        $verifyStmt->close();

        if ($stillExists) {

            throw new Exception(
                "Package deletion could not be verified."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | VERIFY PACKAGE ITEM MAPPINGS
        |--------------------------------------------------------------------------
        */

        $mappingVerifySql = "
            SELECT COUNT(*) AS total
            FROM catering_package_items
            WHERE package_id = ?
        ";

        $mappingVerifyStmt =
            $conn->prepare($mappingVerifySql);

        if (!$mappingVerifyStmt) {

            throw new Exception(
                "Failed to prepare package mapping verification."
            );
        }

        $mappingVerifyStmt->bind_param(
            "i",
            $packageId
        );

        if (!$mappingVerifyStmt->execute()) {

            $error =
                $mappingVerifyStmt->error;

            $mappingVerifyStmt->close();

            throw new Exception(
                "Failed to verify package item deletion: " .
                $error
            );
        }

        $mappingVerifyResult =
            $mappingVerifyStmt->get_result();

        $mappingVerifyRow =
            $mappingVerifyResult->fetch_assoc();

        $mappingVerifyStmt->close();

        $remainingMappings =
            (int)($mappingVerifyRow['total'] ?? 0);

        /*
        |--------------------------------------------------------------------------
        | MAPPINGS SHOULD BE ZERO
        |--------------------------------------------------------------------------
        */

        if ($remainingMappings > 0) {

            throw new Exception(
                "Package was deleted but package item mappings still exist."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | COMMIT
        |--------------------------------------------------------------------------
        */

        $conn->commit();

    } catch (Throwable $transactionException) {

        $conn->rollback();

        throw $transactionException;
    }

    /*
    |--------------------------------------------------------------------------
    | PHYSICAL IMAGE CLEANUP
    |--------------------------------------------------------------------------
    */

    $imageDeleted = false;
    $imageCleanupSkipped = false;

    if (!empty($package['image_path'])) {

        $oldImageRelative =
            ltrim(
                $package['image_path'],
                '/\\'
            );

        /*
        |--------------------------------------------------------------------------
        | SECURITY CHECK
        |--------------------------------------------------------------------------
        |
        | Only delete files inside:
        |
        | uploads/catering_packages/{packageId}/
        |
        */

        $expectedPrefix =
            "uploads/catering_packages/" .
            $packageId .
            "/";

        if (
            strpos(
                $oldImageRelative,
                $expectedPrefix
            ) !== 0
        ) {

            $imageCleanupSkipped = true;

        } else {

            $oldImageFullPath =
                __DIR__ .
                "/../../" .
                $oldImageRelative;

            if (
                file_exists($oldImageFullPath) &&
                is_file($oldImageFullPath)
            ) {

                $imageDeleted =
                    @unlink(
                        $oldImageFullPath
                    );
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SUCCESS RESPONSE
    |--------------------------------------------------------------------------
    */

    http_response_code(200);

    echo json_encode([
        "status" => true,
        "message" => "Catering package deleted successfully.",

        "data" => [
            "deleted_package" => [
                "id" =>
                    (int)$package['id'],

                "name" =>
                    $package['name'],

                "rate" =>
                    (float)$package['rate'],

                "rate_unit" =>
                    $package['rate_unit'],

                "gst_rate" =>
                    (float)$package['gst_rate'],

                "minimum_quantity" =>
                    (int)$package['minimum_quantity'],

                "status" =>
                    $package['status']
            ],

            "deleted_package_item_mappings" =>
                $packageItemCount,

            "lead_references" =>
                $leadReferenceCount,

            "remaining_package_item_mappings" =>
                $remainingMappings
        ],

        "image_cleanup" => [
            "image_was_present" =>
                !empty($package['image_path']),

            "image_deleted" =>
                $imageDeleted,

            "cleanup_skipped_for_security" =>
                $imageCleanupSkipped
        ]
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        "status" => false,
        "message" => "Failed to delete catering package.",
        "error" => $e->getMessage()
    ]);
}