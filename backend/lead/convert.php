<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/middleware.php';

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, PATCH, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/*
|--------------------------------------------------------------------------
| METHOD VALIDATION
|--------------------------------------------------------------------------
*/

if (
    !in_array(
        $_SERVER['REQUEST_METHOD'],
        ['PUT', 'PATCH', 'POST'],
        true
    )
) {
    http_response_code(405);

    echo json_encode([
        "status" => false,
        "message" =>
            "Method not allowed. Use PUT, PATCH or POST."
    ]);

    exit;
}

try {

    /*
    |--------------------------------------------------------------------------
    | AUTHENTICATION
    |--------------------------------------------------------------------------
    | Admin + Staff only
    |--------------------------------------------------------------------------
    */

    $currentUser = requireAdminOrStaff();

    /*
    |--------------------------------------------------------------------------
    | DATABASE CONNECTION
    |--------------------------------------------------------------------------
    */

    global $conn;

    if (
        !isset($conn) ||
        !($conn instanceof mysqli)
    ) {
        throw new Exception(
            "Database connection is not available."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CURRENT USER ROLE
    |--------------------------------------------------------------------------
    */

    $currentUserRole = strtolower(
        trim(
            (string)($currentUser['role'] ?? '')
        )
    );

    /*
    |--------------------------------------------------------------------------
    | CURRENT USER ID
    |--------------------------------------------------------------------------
    | JWT payload normally stores user ID in "sub".
    | "id" is fallback for compatibility.
    |--------------------------------------------------------------------------
    */

    $currentUserId =
        $currentUser['sub']
        ?? $currentUser['id']
        ?? null;

    /*
    |--------------------------------------------------------------------------
    | VALIDATE ROLE
    |--------------------------------------------------------------------------
    */

    if (
        !in_array(
            $currentUserRole,
            ['admin', 'staff'],
            true
        )
    ) {
        http_response_code(403);

        echo json_encode([
            "status" => false,
            "message" =>
                "Only admin or staff users can convert leads."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATE USER ID
    |--------------------------------------------------------------------------
    */

    if (
        filter_var(
            $currentUserId,
            FILTER_VALIDATE_INT
        ) === false ||
        (int)$currentUserId <= 0
    ) {
        http_response_code(401);

        echo json_encode([
            "status" => false,
            "message" =>
                "Invalid authenticated user."
        ]);

        exit;
    }

    $currentUserId =
        (int)$currentUserId;

    /*
    |--------------------------------------------------------------------------
    | READ JSON BODY
    |--------------------------------------------------------------------------
    */

    $rawInput =
        file_get_contents("php://input");

    if (
        $rawInput === false ||
        trim($rawInput) === ''
    ) {
        http_response_code(400);

        echo json_encode([
            "status" => false,
            "message" =>
                "Request body is required."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | DECODE JSON
    |--------------------------------------------------------------------------
    */

    $data =
        json_decode(
            $rawInput,
            true
        );

    if (
        json_last_error() !== JSON_ERROR_NONE ||
        !is_array($data)
    ) {
        http_response_code(400);

        echo json_encode([
            "status" => false,
            "message" =>
                "Invalid JSON request body."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | ALLOWED FIELDS
    |--------------------------------------------------------------------------
    |
    | converted_at is intentionally NOT accepted from frontend.
    | Backend will generate the conversion timestamp.
    |--------------------------------------------------------------------------
    */

    $allowedFields = [
        'lead_id',
        'converted_amount'
    ];

    foreach ($data as $key => $value) {

        if (
            !in_array(
                $key,
                $allowedFields,
                true
            )
        ) {
            http_response_code(400);

            echo json_encode([
                "status" => false,
                "message" =>
                    "Unknown field: {$key}"
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | REQUIRED FIELDS
    |--------------------------------------------------------------------------
    */

    if (
        !array_key_exists(
            'lead_id',
            $data
        )
    ) {
        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" =>
                "lead_id is required."
        ]);

        exit;
    }

    if (
        !array_key_exists(
            'converted_amount',
            $data
        )
    ) {
        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" =>
                "converted_amount is required."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATE LEAD ID
    |--------------------------------------------------------------------------
    */

    $leadId =
        $data['lead_id'];

    /*
    | Accept integer or numeric string.
    */

    if (
        filter_var(
            $leadId,
            FILTER_VALIDATE_INT
        ) === false ||
        (int)$leadId <= 0
    ) {
        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" =>
                "lead_id must be a valid positive integer."
        ]);

        exit;
    }

    $leadId =
        (int)$leadId;

    /*
    |--------------------------------------------------------------------------
    | VALIDATE CONVERTED AMOUNT
    |--------------------------------------------------------------------------
    */

    $convertedAmountRaw =
        $data['converted_amount'];

    /*
    | Reject arrays, objects, booleans and null.
    */

    if (
        is_array($convertedAmountRaw) ||
        is_object($convertedAmountRaw) ||
        is_bool($convertedAmountRaw) ||
        $convertedAmountRaw === null
    ) {
        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" =>
                "converted_amount must be a valid number."
        ]);

        exit;
    }

    /*
    | Convert to string for strict decimal validation.
    */

    $convertedAmountRaw =
        trim(
            (string)$convertedAmountRaw
        );

    if (
        $convertedAmountRaw === ''
    ) {
        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" =>
                "converted_amount cannot be empty."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | DECIMAL FORMAT
    |--------------------------------------------------------------------------
    |
    | Maximum:
    | 9999999999.99
    |
    | Maximum 2 decimal places.
    |--------------------------------------------------------------------------
    */

    if (
        !preg_match(
            '/^\d{1,10}(\.\d{1,2})?$/',
            $convertedAmountRaw
        )
    ) {
        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" =>
                "converted_amount must be a valid positive amount with maximum 2 decimal places.",
            "example" =>
                "50000.00"
        ]);

        exit;
    }

    $convertedAmount =
        (float)$convertedAmountRaw;

    /*
    |--------------------------------------------------------------------------
    | AMOUNT RANGE
    |--------------------------------------------------------------------------
    */

    if (
        $convertedAmount <= 0
    ) {
        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" =>
                "converted_amount must be greater than 0."
        ]);

        exit;
    }

    if (
        $convertedAmount > 9999999999.99
    ) {
        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" =>
                "converted_amount exceeds the maximum allowed amount."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | NORMALIZE TO 2 DECIMAL PLACES
    |--------------------------------------------------------------------------
    */

    $convertedAmount =
        round(
            $convertedAmount,
            2
        );

    /*
    |--------------------------------------------------------------------------
    | BEGIN TRANSACTION
    |--------------------------------------------------------------------------
    */

    $conn->begin_transaction();

    try {

        /*
        |--------------------------------------------------------------------------
        | FETCH LEAD WITH ROW LOCK
        |--------------------------------------------------------------------------
        */

        $leadQuery = "

            SELECT

                id,
                lead_reference,
                domain,

                customer_name,
                customer_phone,
                customer_email,

                event_date,
                guest_count,
                preferred_time,

                requirement,

                status,

                estimated_amount,
                converted_amount,
                converted_at,

                source,
                assigned_admin_id,

                created_at,
                updated_at

            FROM leads

            WHERE id = ?

            LIMIT 1

            FOR UPDATE
        ";

        $stmt =
            $conn->prepare(
                $leadQuery
            );

        if (!$stmt) {
            throw new Exception(
                "Failed to prepare lead query: " .
                $conn->error
            );
        }

        $stmt->bind_param(
            "i",
            $leadId
        );

        if (!$stmt->execute()) {

            $error =
                $stmt->error;

            $stmt->close();

            throw new Exception(
                "Failed to fetch lead: " .
                $error
            );
        }

        $result =
            $stmt->get_result();

        $lead =
            $result->fetch_assoc();

        $stmt->close();

        /*
        |--------------------------------------------------------------------------
        | LEAD NOT FOUND
        |--------------------------------------------------------------------------
        */

        if (!$lead) {

            $conn->rollback();

            http_response_code(404);

            echo json_encode([
                "status" => false,
                "message" =>
                    "Lead not found.",
                "lead_id" =>
                    $leadId
            ]);

            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDATE DOMAIN
        |--------------------------------------------------------------------------
        */

        $allowedDomains = [
            'hall',
            'dining',
            'catering'
        ];

        if (
            !in_array(
                $lead['domain'],
                $allowedDomains,
                true
            )
        ) {
            throw new Exception(
                "Invalid lead domain found in database."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | STAFF ACCESS CONTROL
        |--------------------------------------------------------------------------
        |
        | ADMIN:
        |   Can convert ANY lead.
        |
        | STAFF:
        |   Can convert ONLY leads assigned to themselves.
        |--------------------------------------------------------------------------
        */

        if (
            $currentUserRole === 'staff'
        ) {

            /*
            |--------------------------------------------------------------------------
            | UNASSIGNED LEAD
            |--------------------------------------------------------------------------
            */

            if (
                $lead['assigned_admin_id'] === null
            ) {

                $conn->rollback();

                http_response_code(403);

                echo json_encode([
                    "status" => false,
                    "message" =>
                        "You cannot convert an unassigned lead.",
                    "lead_id" =>
                        (int)$lead['id']
                ]);

                exit;
            }

            /*
            |--------------------------------------------------------------------------
            | ASSIGNED TO ANOTHER USER
            |--------------------------------------------------------------------------
            */

            if (
                (int)$lead['assigned_admin_id']
                !== $currentUserId
            ) {

                $conn->rollback();

                http_response_code(403);

                echo json_encode([
                    "status" => false,
                    "message" =>
                        "You can convert only leads assigned to you.",
                    "lead_id" =>
                        (int)$lead['id'],
                    "assigned_admin_id" =>
                        (int)$lead['assigned_admin_id']
                ]);

                exit;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | ALREADY CONVERTED
        |--------------------------------------------------------------------------
        */

        if (
            $lead['status'] === 'converted'
        ) {

            $conn->rollback();

            http_response_code(409);

            echo json_encode([
                "status" => false,
                "message" =>
                    "This lead is already converted.",
                "lead_id" =>
                    (int)$lead['id'],
                "current_status" =>
                    $lead['status'],
                "converted_amount" =>
                    $lead['converted_amount'] !== null
                        ? (float)$lead['converted_amount']
                        : null,
                "converted_at" =>
                    $lead['converted_at']
            ]);

            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | PREVENT CONVERSION OF LOST LEAD
        |--------------------------------------------------------------------------
        */

        if (
            $lead['status'] === 'lost'
        ) {

            $conn->rollback();

            http_response_code(409);

            echo json_encode([
                "status" => false,
                "message" =>
                    "A lost lead cannot be converted. Update the lead status before conversion.",
                "lead_id" =>
                    (int)$lead['id'],
                "current_status" =>
                    $lead['status']
            ]);

            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | PREVENT CONVERSION OF CANCELLED LEAD
        |--------------------------------------------------------------------------
        */

        if (
            $lead['status'] === 'cancelled'
        ) {

            $conn->rollback();

            http_response_code(409);

            echo json_encode([
                "status" => false,
                "message" =>
                    "A cancelled lead cannot be converted. Update the lead status before conversion.",
                "lead_id" =>
                    (int)$lead['id'],
                "current_status" =>
                    $lead['status']
            ]);

            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | ALLOWED SOURCE STATUSES
        |--------------------------------------------------------------------------
        |
        | Normal conversion flow:
        |
        | new
        | contacted
        | follow_up
        |       ↓
        | converted
        |--------------------------------------------------------------------------
        */

        $allowedConversionStatuses = [
            'new',
            'contacted',
            'follow_up'
        ];

        if (
            !in_array(
                $lead['status'],
                $allowedConversionStatuses,
                true
            )
        ) {

            $conn->rollback();

            http_response_code(409);

            echo json_encode([
                "status" => false,
                "message" =>
                    "Lead with current status '{$lead['status']}' cannot be converted.",
                "allowed_source_statuses" =>
                    $allowedConversionStatuses
            ]);

            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | PREVENT NULL / INVALID ASSIGNMENT FOR STAFF
        |--------------------------------------------------------------------------
        |
        | Already handled above.
        | Admin can convert assigned or unassigned leads.
        |--------------------------------------------------------------------------
        */

        /*
        |--------------------------------------------------------------------------
        | UPDATE LEAD
        |--------------------------------------------------------------------------
        |
        | converted_at is generated by MySQL.
        |--------------------------------------------------------------------------
        */

        $updateQuery = "

            UPDATE leads

            SET

                status = 'converted',

                converted_amount = ?,

                converted_at = CURRENT_TIMESTAMP,

                updated_at = CURRENT_TIMESTAMP

            WHERE id = ?

            LIMIT 1
        ";

        $updateStmt =
            $conn->prepare(
                $updateQuery
            );

        if (!$updateStmt) {
            throw new Exception(
                "Failed to prepare conversion query: " .
                $conn->error
            );
        }

        $updateStmt->bind_param(
            "di",
            $convertedAmount,
            $leadId
        );

        if (!$updateStmt->execute()) {

            $error =
                $updateStmt->error;

            $updateStmt->close();

            throw new Exception(
                "Failed to convert lead: " .
                $error
            );
        }

        $affectedRows =
            $updateStmt->affected_rows;

        $updateStmt->close();

        /*
        |--------------------------------------------------------------------------
        | VERIFY UPDATED LEAD
        |--------------------------------------------------------------------------
        */

        $verifyQuery = "

            SELECT

                id,
                lead_reference,
                domain,

                customer_name,
                customer_phone,
                customer_email,

                event_date,
                guest_count,
                preferred_time,

                requirement,

                status,

                estimated_amount,
                converted_amount,
                converted_at,

                source,
                assigned_admin_id,

                created_at,
                updated_at

            FROM leads

            WHERE id = ?

            LIMIT 1
        ";

        $verifyStmt =
            $conn->prepare(
                $verifyQuery
            );

        if (!$verifyStmt) {
            throw new Exception(
                "Failed to prepare verification query: " .
                $conn->error
            );
        }

        $verifyStmt->bind_param(
            "i",
            $leadId
        );

        if (!$verifyStmt->execute()) {

            $error =
                $verifyStmt->error;

            $verifyStmt->close();

            throw new Exception(
                "Failed to verify converted lead: " .
                $error
            );
        }

        $verifyResult =
            $verifyStmt->get_result();

        $convertedLead =
            $verifyResult->fetch_assoc();

        $verifyStmt->close();

        /*
        |--------------------------------------------------------------------------
        | VERIFY LEAD EXISTS
        |--------------------------------------------------------------------------
        */

        if (!$convertedLead) {

            throw new Exception(
                "Lead verification failed after conversion."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | VERIFY STATUS
        |--------------------------------------------------------------------------
        */

        if (
            $convertedLead['status']
            !== 'converted'
        ) {

            throw new Exception(
                "Lead status was not changed to converted."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | VERIFY CONVERTED AMOUNT
        |--------------------------------------------------------------------------
        */

        if (
            $convertedLead['converted_amount']
            === null
        ) {

            throw new Exception(
                "Converted amount was not saved correctly."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | VERIFY CONVERTED TIMESTAMP
        |--------------------------------------------------------------------------
        */

        if (
            empty(
                $convertedLead['converted_at']
            )
        ) {

            throw new Exception(
                "Converted timestamp was not saved correctly."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | COMMIT
        |--------------------------------------------------------------------------
        */

        $conn->commit();

        /*
        |--------------------------------------------------------------------------
        | FINAL RESPONSE
        |--------------------------------------------------------------------------
        */

        http_response_code(200);

        echo json_encode([

            "status" => true,

            "message" =>
                "Lead converted successfully.",

            "data" => [

                "lead_id" =>
                    (int)$convertedLead['id'],

                "lead_reference" =>
                    $convertedLead['lead_reference'],

                "domain" =>
                    $convertedLead['domain'],

                "customer_name" =>
                    $convertedLead['customer_name'],

                "customer_phone" =>
                    $convertedLead['customer_phone'],

                "customer_email" =>
                    $convertedLead['customer_email'],

                "user_scope" => [

                    "role" =>
                        $currentUserRole,

                    "user_id" =>
                        $currentUserId,

                    "scope" =>
                        $currentUserRole === 'admin'
                            ? "all_leads"
                            : "assigned_leads_only"
                ],

                "assigned_admin_id" =>
                    $convertedLead['assigned_admin_id'] !== null
                        ? (int)$convertedLead['assigned_admin_id']
                        : null,

                "old_status" =>
                    $lead['status'],

                "new_status" =>
                    $convertedLead['status'],

                "estimated_amount" =>
                    $convertedLead['estimated_amount'] !== null
                        ? (float)$convertedLead['estimated_amount']
                        : null,

                "converted_amount" =>
                    $convertedLead['converted_amount'] !== null
                        ? (float)$convertedLead['converted_amount']
                        : null,

                "converted_at" =>
                    $convertedLead['converted_at'],

                "source" =>
                    $convertedLead['source'],

                "updated_at" =>
                    $convertedLead['updated_at'],

                "affected_rows" =>
                    $affectedRows
            ]
        ]);

        exit;

    } catch (Throwable $e) {

        /*
        |--------------------------------------------------------------------------
        | ROLLBACK
        |--------------------------------------------------------------------------
        */

        $conn->rollback();

        throw $e;
    }

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([

        "status" => false,

        "message" =>
            "Failed to convert lead.",

        "error" =>
            $e->getMessage()
    ]);

    exit;
}