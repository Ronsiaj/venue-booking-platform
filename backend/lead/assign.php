<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/middleware.php';

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        "status" => false,
        "message" => "Only POST method is allowed."
    ]);

    exit;
}

try {

    /*
    |--------------------------------------------------------------------------
    | AUTHENTICATION
    |--------------------------------------------------------------------------
    | Only Admin can assign / reassign / unassign leads.
    |--------------------------------------------------------------------------
    */

    $currentUser = requireAdmin();

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
    | CURRENT AUTHENTICATED USER
    |--------------------------------------------------------------------------
    |
    | JWT user ID is normally stored in "sub".
    | Fallback to "id" for compatibility.
    |--------------------------------------------------------------------------
    */

    $currentUserRole = strtolower(
        trim((string)($currentUser['role'] ?? 'admin'))
    );

    $currentUserId =
        $currentUser['sub']
        ?? $currentUser['id']
        ?? null;

    /*
    |--------------------------------------------------------------------------
    | CURRENT USER ID VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        filter_var($currentUserId, FILTER_VALIDATE_INT) === false ||
        (int)$currentUserId <= 0
    ) {

        http_response_code(401);

        echo json_encode([
            "status" => false,
            "message" => "Invalid authenticated admin."
        ]);

        exit;
    }

    $currentUserId = (int)$currentUserId;

    /*
    |--------------------------------------------------------------------------
    | READ JSON BODY
    |--------------------------------------------------------------------------
    */

    $rawInput = file_get_contents("php://input");

    if (
        $rawInput === false ||
        trim($rawInput) === ''
    ) {

        http_response_code(400);

        echo json_encode([
            "status" => false,
            "message" => "Request body is required."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | DECODE JSON
    |--------------------------------------------------------------------------
    */

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
            "status" => false,
            "message" => "Invalid JSON request body."
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
        'assigned_admin_id'
    ];

    foreach ($data as $key => $value) {

        if (!in_array($key, $allowedFields, true)) {

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
    | LEAD ID VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        !array_key_exists('lead_id', $data) ||
        $data['lead_id'] === null ||
        $data['lead_id'] === ''
    ) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "lead_id is required."
        ]);

        exit;
    }

    $leadIdRaw = trim(
        (string)$data['lead_id']
    );

    if (
        !ctype_digit($leadIdRaw) ||
        (int)$leadIdRaw <= 0
    ) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" =>
                "lead_id must be a positive integer."
        ]);

        exit;
    }

    $leadId = (int)$leadIdRaw;

    /*
    |--------------------------------------------------------------------------
    | ASSIGNED ADMIN ID
    |--------------------------------------------------------------------------
    |
    | Integer = assign
    | NULL     = unassign
    |--------------------------------------------------------------------------
    */

    if (
        !array_key_exists(
            'assigned_admin_id',
            $data
        )
    ) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" =>
                "assigned_admin_id is required. Use null to unassign the lead."
        ]);

        exit;
    }

    $assignedAdminId = null;

    /*
    |--------------------------------------------------------------------------
    | NULL / EMPTY = UNASSIGN
    |--------------------------------------------------------------------------
    */

    if (
        $data['assigned_admin_id'] === null ||
        (
            is_string($data['assigned_admin_id']) &&
            trim($data['assigned_admin_id']) === ''
        )
    ) {

        $assignedAdminId = null;

    } else {

        $assignedAdminIdRaw = trim(
            (string)$data['assigned_admin_id']
        );

        if (
            !ctype_digit($assignedAdminIdRaw) ||
            (int)$assignedAdminIdRaw <= 0
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "assigned_admin_id must be a positive integer or null."
            ]);

            exit;
        }

        $assignedAdminId =
            (int)$assignedAdminIdRaw;
    }

    /*
    |--------------------------------------------------------------------------
    | FETCH LEAD
    |--------------------------------------------------------------------------
    */

    $leadSql = "

        SELECT

            id,
            lead_reference,
            domain,
            customer_name,
            customer_phone,
            status,
            assigned_admin_id

        FROM leads

        WHERE id = ?

        LIMIT 1
    ";

    $leadStmt = $conn->prepare($leadSql);

    if (!$leadStmt) {

        throw new Exception(
            "Failed to prepare lead query: " .
            $conn->error
        );
    }

    $leadStmt->bind_param(
        "i",
        $leadId
    );

    if (!$leadStmt->execute()) {

        $error = $leadStmt->error;

        $leadStmt->close();

        throw new Exception(
            "Failed to fetch lead: " .
            $error
        );
    }

    $leadResult =
        $leadStmt->get_result();

    $lead =
        $leadResult->fetch_assoc();

    $leadStmt->close();

    /*
    |--------------------------------------------------------------------------
    | LEAD NOT FOUND
    |--------------------------------------------------------------------------
    */

    if (!$lead) {

        http_response_code(404);

        echo json_encode([
            "status" => false,
            "message" => "Lead not found.",
            "lead_id" => $leadId
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

    $leadDomain = strtolower(
        trim((string)$lead['domain'])
    );

    if (!in_array($leadDomain, $allowedDomains, true)) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" =>
                "Invalid lead domain.",
            "domain" =>
                $leadDomain
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATE LEAD STATUS
    |--------------------------------------------------------------------------
    */

    $leadStatus = strtolower(
        trim((string)$lead['status'])
    );

    /*
    |--------------------------------------------------------------------------
    | LOST / CANCELLED
    |--------------------------------------------------------------------------
    |
    | These leads cannot be assigned.
    |--------------------------------------------------------------------------
    */

    if (
        in_array(
            $leadStatus,
            ['lost', 'cancelled'],
            true
        )
    ) {

        http_response_code(409);

        echo json_encode([
            "status" => false,
            "message" =>
                "Cannot assign or modify assignment for a " .
                $leadStatus .
                " lead.",
            "lead_id" =>
                (int)$lead['id'],
            "lead_status" =>
                $leadStatus
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | CONVERTED LEAD
    |--------------------------------------------------------------------------
    |
    | Converted leads cannot be reassigned.
    |
    | But if already assigned and same assignment is sent,
    | we allow it as a no-op.
    |--------------------------------------------------------------------------
    */

    $existingAssignedId =
        $lead['assigned_admin_id'] !== null
            ? (int)$lead['assigned_admin_id']
            : null;

    if (
        $leadStatus === 'converted' &&
        $assignedAdminId !== null &&
        $existingAssignedId !== $assignedAdminId
    ) {

        http_response_code(409);

        echo json_encode([
            "status" => false,
            "message" =>
                "Converted lead cannot be reassigned.",
            "lead_id" =>
                (int)$lead['id'],
            "lead_status" =>
                $leadStatus,
            "current_assigned_admin_id" =>
                $existingAssignedId
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | CONVERTED LEAD UNASSIGN
    |--------------------------------------------------------------------------
    |
    | Do not allow removing the existing owner from converted lead.
    |--------------------------------------------------------------------------
    */

    if (
        $leadStatus === 'converted' &&
        $assignedAdminId === null &&
        $existingAssignedId !== null
    ) {

        http_response_code(409);

        echo json_encode([
            "status" => false,
            "message" =>
                "Converted lead cannot be unassigned.",
            "lead_id" =>
                (int)$lead['id'],
            "lead_status" =>
                $leadStatus,
            "current_assigned_admin_id" =>
                $existingAssignedId
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | SAME ASSIGNMENT CHECK
    |--------------------------------------------------------------------------
    */

    if (
        $existingAssignedId === $assignedAdminId
    ) {

        /*
        |--------------------------------------------------------------------------
        | FETCH CURRENT ASSIGNED USER
        |--------------------------------------------------------------------------
        */

        $sameAssignedUser = null;

        if ($existingAssignedId !== null) {

            $sameUserSql = "

                SELECT

                    id,
                    name,
                    email,
                    phone,
                    role,
                    status

                FROM admin_users

                WHERE id = ?

                LIMIT 1
            ";

            $sameUserStmt =
                $conn->prepare($sameUserSql);

            if (!$sameUserStmt) {

                throw new Exception(
                    "Failed to prepare assigned user query: " .
                    $conn->error
                );
            }

            $sameUserStmt->bind_param(
                "i",
                $existingAssignedId
            );

            if (!$sameUserStmt->execute()) {

                $error =
                    $sameUserStmt->error;

                $sameUserStmt->close();

                throw new Exception(
                    "Failed to fetch assigned user: " .
                    $error
                );
            }

            $sameUserResult =
                $sameUserStmt->get_result();

            $sameAssignedUser =
                $sameUserResult->fetch_assoc();

            $sameUserStmt->close();
        }

        http_response_code(200);

        echo json_encode([

            "status" => true,

            "message" =>
                $assignedAdminId === null
                    ? "Lead is already unassigned."
                    : "Lead is already assigned to this user.",

            "data" => [

                "lead_id" =>
                    (int)$lead['id'],

                "lead_reference" =>
                    $lead['lead_reference'],

                "domain" =>
                    $leadDomain,

                "lead_status" =>
                    $leadStatus,

                "previous_assigned_admin_id" =>
                    $existingAssignedId,

                "assigned_admin_id" =>
                    $assignedAdminId,

                "assigned_admin" =>
                    $sameAssignedUser
                        ? [
                            "id" =>
                                (int)$sameAssignedUser['id'],

                            "name" =>
                                $sameAssignedUser['name'],

                            "email" =>
                                $sameAssignedUser['email'],

                            "phone" =>
                                $sameAssignedUser['phone'],

                            "role" =>
                                $sameAssignedUser['role'],

                            "status" =>
                                $sameAssignedUser['status']
                        ]
                        : null,

                "changed" => false,

                "assigned_by" => [

                    "id" =>
                        $currentUserId,

                    "role" =>
                        $currentUserRole
                ]
            ]
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATE NEW ASSIGNED ADMIN / STAFF
    |--------------------------------------------------------------------------
    |
    | assigned_admin_id must exist in admin_users.
    | Allowed roles:
    |     admin
    |     staff
    |--------------------------------------------------------------------------
    */

    $assignedUser = null;

    if ($assignedAdminId !== null) {

        $userSql = "

            SELECT

                id,
                name,
                email,
                phone,
                role,
                status

            FROM admin_users

            WHERE id = ?

            LIMIT 1
        ";

        $userStmt =
            $conn->prepare($userSql);

        if (!$userStmt) {

            throw new Exception(
                "Failed to prepare assigned user query: " .
                $conn->error
            );
        }

        $userStmt->bind_param(
            "i",
            $assignedAdminId
        );

        if (!$userStmt->execute()) {

            $error =
                $userStmt->error;

            $userStmt->close();

            throw new Exception(
                "Failed to fetch assigned user: " .
                $error
            );
        }

        $userResult =
            $userStmt->get_result();

        $assignedUser =
            $userResult->fetch_assoc();

        $userStmt->close();

        /*
        |--------------------------------------------------------------------------
        | USER NOT FOUND
        |--------------------------------------------------------------------------
        */

        if (!$assignedUser) {

            http_response_code(404);

            echo json_encode([
                "status" => false,
                "message" =>
                    "Assigned admin/staff user not found.",
                "assigned_admin_id" =>
                    $assignedAdminId
            ]);

            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | USER ROLE VALIDATION
        |--------------------------------------------------------------------------
        */

        $assignedRole = strtolower(
            trim((string)$assignedUser['role'])
        );

        if (
            !in_array(
                $assignedRole,
                ['admin', 'staff'],
                true
            )
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "Lead can only be assigned to an admin or staff user.",
                "assigned_user_role" =>
                    $assignedRole
            ]);

            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | USER STATUS VALIDATION
        |--------------------------------------------------------------------------
        */

        $assignedUserStatus = strtolower(
            trim((string)$assignedUser['status'])
        );

        if (
            in_array(
                $assignedUserStatus,
                [
                    'inactive',
                    'disabled',
                    'blocked'
                ],
                true
            )
        ) {

            http_response_code(409);

            echo json_encode([
                "status" => false,
                "message" =>
                    "Cannot assign lead to an inactive user.",
                "assigned_admin_id" =>
                    $assignedAdminId,
                "assigned_user_status" =>
                    $assignedUserStatus
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE ASSIGNMENT
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | If assignedAdminId is NULL:
    |     SET assigned_admin_id = NULL
    |
    | If assignedAdminId has value:
    |     SET assigned_admin_id = ?
    |
    | This avoids mysqli bind_param issues with NULL.
    |--------------------------------------------------------------------------
    */

    if ($assignedAdminId === null) {

        $updateSql = "

            UPDATE leads

            SET
                assigned_admin_id = NULL,
                updated_at = CURRENT_TIMESTAMP

            WHERE id = ?

            LIMIT 1
        ";

        $updateStmt =
            $conn->prepare($updateSql);

        if (!$updateStmt) {

            throw new Exception(
                "Failed to prepare lead unassignment query: " .
                $conn->error
            );
        }

        $updateStmt->bind_param(
            "i",
            $leadId
        );

    } else {

        $updateSql = "

            UPDATE leads

            SET
                assigned_admin_id = ?,
                updated_at = CURRENT_TIMESTAMP

            WHERE id = ?

            LIMIT 1
        ";

        $updateStmt =
            $conn->prepare($updateSql);

        if (!$updateStmt) {

            throw new Exception(
                "Failed to prepare lead assignment query: " .
                $conn->error
            );
        }

        $updateStmt->bind_param(
            "ii",
            $assignedAdminId,
            $leadId
        );
    }

    /*
    |--------------------------------------------------------------------------
    | EXECUTE UPDATE
    |--------------------------------------------------------------------------
    */

    if (!$updateStmt->execute()) {

        $error =
            $updateStmt->error;

        $updateStmt->close();

        throw new Exception(
            "Failed to update lead assignment: " .
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

    $verifySql = "

        SELECT

            id,
            lead_reference,
            domain,
            customer_name,
            customer_phone,
            status,
            assigned_admin_id

        FROM leads

        WHERE id = ?

        LIMIT 1
    ";

    $verifyStmt =
        $conn->prepare($verifySql);

    if (!$verifyStmt) {

        throw new Exception(
            "Failed to prepare assignment verification query: " .
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
            "Failed to verify lead assignment: " .
            $error
        );
    }

    $verifyResult =
        $verifyStmt->get_result();

    $updatedLead =
        $verifyResult->fetch_assoc();

    $verifyStmt->close();

    if (!$updatedLead) {

        throw new Exception(
            "Lead assignment update could not be verified."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | VERIFY ASSIGNED ID
    |--------------------------------------------------------------------------
    */

    $updatedAssignedId =
        $updatedLead['assigned_admin_id'] !== null
            ? (int)$updatedLead['assigned_admin_id']
            : null;

    if (
        $updatedAssignedId !== $assignedAdminId
    ) {

        throw new Exception(
            "Lead assignment verification failed."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FETCH FINAL ASSIGNED USER
    |--------------------------------------------------------------------------
    */

    $finalAssignedUser = null;

    if ($updatedAssignedId !== null) {

        $finalUserSql = "

            SELECT

                id,
                name,
                email,
                phone,
                role,
                status

            FROM admin_users

            WHERE id = ?

            LIMIT 1
        ";

        $finalUserStmt =
            $conn->prepare($finalUserSql);

        if (!$finalUserStmt) {

            throw new Exception(
                "Failed to prepare final assigned user query: " .
                $conn->error
            );
        }

        $finalUserStmt->bind_param(
            "i",
            $updatedAssignedId
        );

        if (!$finalUserStmt->execute()) {

            $error =
                $finalUserStmt->error;

            $finalUserStmt->close();

            throw new Exception(
                "Failed to fetch final assigned user: " .
                $error
            );
        }

        $finalUserResult =
            $finalUserStmt->get_result();

        $finalAssignedUser =
            $finalUserResult->fetch_assoc();

        $finalUserStmt->close();
    }

    /*
    |--------------------------------------------------------------------------
    | SUCCESS RESPONSE
    |--------------------------------------------------------------------------
    */

    http_response_code(200);

    echo json_encode([

        "status" => true,

        "message" =>
            $updatedAssignedId === null
                ? "Lead unassigned successfully."
                : "Lead assigned successfully.",

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

            "lead_status" =>
                $updatedLead['status'],

            "previous_assigned_admin_id" =>
                $existingAssignedId,

            "assigned_admin_id" =>
                $updatedAssignedId,

            "assigned_admin" =>
                $finalAssignedUser
                    ? [
                        "id" =>
                            (int)$finalAssignedUser['id'],

                        "name" =>
                            $finalAssignedUser['name'],

                        "email" =>
                            $finalAssignedUser['email'],

                        "phone" =>
                            $finalAssignedUser['phone'],

                        "role" =>
                            $finalAssignedUser['role'],

                        "status" =>
                            $finalAssignedUser['status']
                    ]
                    : null,

            "changed" =>
                $affectedRows > 0,

            "assigned_by" => [

                "id" =>
                    $currentUserId,

                "role" =>
                    $currentUserRole
            ]
        ]
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([

        "status" => false,

        "message" =>
            "Failed to assign lead.",

        "error" =>
            $e->getMessage()
    ]);
}