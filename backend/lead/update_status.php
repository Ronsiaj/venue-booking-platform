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
    | "id" is kept as fallback for compatibility.
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
                "Only admin or staff users can update lead status."
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
    */

    $allowedFields = [
        'lead_id',
        'status'
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
            'status',
            $data
        )
    ) {
        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" =>
                "status is required."
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
    | VALIDATE STATUS
    |--------------------------------------------------------------------------
    */

    $status =
        $data['status'];

    if (!is_string($status)) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" =>
                "status must be a string."
        ]);

        exit;
    }

    $status =
        strtolower(
            trim($status)
        );

    /*
    |--------------------------------------------------------------------------
    | ALLOWED STATUS VALUES
    |--------------------------------------------------------------------------
    */

    $allowedStatuses = [
        'new',
        'contacted',
        'follow_up',
        'converted',
        'lost',
        'cancelled'
    ];

    if (
        !in_array(
            $status,
            $allowedStatuses,
            true
        )
    ) {
        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" =>
                "Invalid status.",
            "allowed_statuses" =>
                $allowedStatuses
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | BEGIN TRANSACTION
    |--------------------------------------------------------------------------
    */

    $conn->begin_transaction();

    try {

        /*
        |--------------------------------------------------------------------------
        | FETCH LEAD
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
        |   Can update ANY lead.
        |
        | STAFF:
        |   Can update ONLY their assigned leads.
        |
        |   assigned_admin_id = current staff ID
        |--------------------------------------------------------------------------
        */

        if (
            $currentUserRole === 'staff'
        ) {

            /*
            |--------------------------------------------------------------------------
            | Unassigned Lead
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
                        "You cannot update an unassigned lead.",
                    "lead_id" =>
                        (int)$lead['id']
                ]);

                exit;
            }

            /*
            |--------------------------------------------------------------------------
            | Lead Assigned To Another User
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
                        "You can update status only for leads assigned to you.",
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
        | SAME STATUS
        |--------------------------------------------------------------------------
        */

        if (
            $lead['status'] === $status
        ) {

            $conn->commit();

            http_response_code(200);

            echo json_encode([
                "status" => true,
                "message" =>
                    "Lead status is already '{$status}'.",
                "data" => [

                    "lead_id" =>
                        (int)$lead['id'],

                    "lead_reference" =>
                        $lead['lead_reference'],

                    "domain" =>
                        $lead['domain'],

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

                    "old_status" =>
                        $lead['status'],

                    "new_status" =>
                        $lead['status'],

                    "assigned_admin_id" =>
                        $lead['assigned_admin_id'] !== null
                            ? (int)$lead['assigned_admin_id']
                            : null,

                    "converted_at" =>
                        $lead['converted_at']
                ]
            ]);

            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | CONVERTED LEAD PROTECTION
        |--------------------------------------------------------------------------
        |
        | Once a lead is converted, don't move it back using this API.
        |
        | If reversal is required later, create a separate API.
        |--------------------------------------------------------------------------
        */

        if (
            $lead['status'] === 'converted' &&
            $status !== 'converted'
        ) {

            $conn->rollback();

            http_response_code(409);

            echo json_encode([
                "status" => false,
                "message" =>
                    "A converted lead cannot be moved to another status using this API.",
                "current_status" =>
                    "converted",
                "lead_id" =>
                    (int)$lead['id']
            ]);

            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | CONVERTED STATUS
        |--------------------------------------------------------------------------
        |
        | Conversion must go through convert.php.
        |
        | convert.php handles:
        | - converted_amount
        | - converted_at
        | - status = converted
        |--------------------------------------------------------------------------
        */

        if (
            $status === 'converted'
        ) {

            $conn->rollback();

            http_response_code(409);

            echo json_encode([
                "status" => false,
                "message" =>
                    "Use convert.php to convert a lead. update_status.php cannot directly set status to converted.",
                "lead_id" =>
                    (int)$lead['id']
            ]);

            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | UPDATE STATUS
        |--------------------------------------------------------------------------
        */

        $updateQuery = "

            UPDATE leads

            SET

                status = ?,
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
                "Failed to prepare status update query: " .
                $conn->error
            );
        }

        $updateStmt->bind_param(
            "si",
            $status,
            $leadId
        );

        if (!$updateStmt->execute()) {

            $error =
                $updateStmt->error;

            $updateStmt->close();

            throw new Exception(
                "Failed to update lead status: " .
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

                status,

                estimated_amount,
                converted_amount,
                converted_at,

                source,
                assigned_admin_id,

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
                "Failed to verify updated lead: " .
                $error
            );
        }

        $verifyResult =
            $verifyStmt->get_result();

        $updatedLead =
            $verifyResult->fetch_assoc();

        $verifyStmt->close();

        /*
        |--------------------------------------------------------------------------
        | VERIFY LEAD EXISTS
        |--------------------------------------------------------------------------
        */

        if (!$updatedLead) {

            throw new Exception(
                "Lead verification failed after status update."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | VERIFY STATUS
        |--------------------------------------------------------------------------
        */

        if (
            $updatedLead['status'] !== $status
        ) {
            throw new Exception(
                "Lead status was not updated correctly."
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
                "Lead status updated successfully.",

            "data" => [

                "lead_id" =>
                    (int)$updatedLead['id'],

                "lead_reference" =>
                    $updatedLead['lead_reference'],

                "domain" =>
                    $updatedLead['domain'],

                "customer_name" =>
                    $updatedLead['customer_name'],

                "customer_phone" =>
                    $updatedLead['customer_phone'],

                "customer_email" =>
                    $updatedLead['customer_email'],

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

                "old_status" =>
                    $lead['status'],

                "new_status" =>
                    $updatedLead['status'],

                "estimated_amount" =>
                    $updatedLead['estimated_amount'],

                "converted_amount" =>
                    $updatedLead['converted_amount'],

                "converted_at" =>
                    $updatedLead['converted_at'],

                "source" =>
                    $updatedLead['source'],

                "assigned_admin_id" =>
                    $updatedLead['assigned_admin_id'] !== null
                        ? (int)$updatedLead['assigned_admin_id']
                        : null,

                "updated_at" =>
                    $updatedLead['updated_at'],

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
            "Failed to update lead status.",

        "error" =>
            $e->getMessage()
    ]);

    exit;
}