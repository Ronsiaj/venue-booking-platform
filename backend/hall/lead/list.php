<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/middleware.php';

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {

    http_response_code(405);

    echo json_encode([
        "status" => false,
        "message" => "Method not allowed. Use GET."
    ]);

    exit;
}

try {

    /*
    |--------------------------------------------------------------------------
    | ADMIN / STAFF AUTHENTICATION
    |--------------------------------------------------------------------------
    |
    | Admin:
    |   Can view all hall leads.
    |
    | Staff:
    |   Can view only leads assigned to them.
    |
    */

    $currentUser = requireAdminOrStaff();

    if (!is_array($currentUser)) {

        throw new Exception(
            "Invalid authenticated user data."
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
    */

    $currentUserId = null;

    if (
        isset($currentUser['id']) &&
        filter_var(
            $currentUser['id'],
            FILTER_VALIDATE_INT
        ) !== false
    ) {

        $currentUserId =
            (int)$currentUser['id'];

    } elseif (
        isset($currentUser['sub']) &&
        filter_var(
            $currentUser['sub'],
            FILTER_VALIDATE_INT
        ) !== false
    ) {

        $currentUserId =
            (int)$currentUser['sub'];
    }

    /*
    |--------------------------------------------------------------------------
    | ROLE VALIDATION
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
                "Only admin or staff can view hall leads."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | USER ID VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        $currentUserId === null ||
        $currentUserId <= 0
    ) {

        http_response_code(401);

        echo json_encode([
            "status" => false,
            "message" =>
                "Authenticated user ID is invalid."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | DATABASE
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
    | ALLOWED QUERY PARAMETERS
    |--------------------------------------------------------------------------
    */

    $allowedParams = [

        "search",

        "status",

        "event_date",
        "from_date",
        "to_date",

        "hall_id",

        "min_guest_count",
        "max_guest_count",

        "assigned_admin_id",

        "source",

        "min_amount",
        "max_amount",

        "page",
        "limit",

        "sort_by",
        "sort_order"
    ];

    /*
    |--------------------------------------------------------------------------
    | UNKNOWN QUERY PARAMETER VALIDATION
    |--------------------------------------------------------------------------
    */

    foreach ($_GET as $key => $value) {

        if (
            !in_array(
                $key,
                $allowedParams,
                true
            )
        ) {

            http_response_code(400);

            echo json_encode([
                "status" => false,
                "message" =>
                    "Unknown query parameter: " .
                    $key
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER - STRICT DATE VALIDATION
    |--------------------------------------------------------------------------
    */

    $isValidDate = function ($date) {

        if (
            !is_string($date) ||
            !preg_match(
                '/^\d{4}-\d{2}-\d{2}$/',
                $date
            )
        ) {

            return false;
        }

        $parts = explode(
            '-',
            $date
        );

        return checkdate(
            (int)$parts[1],
            (int)$parts[2],
            (int)$parts[0]
        );
    };

    /*
    |--------------------------------------------------------------------------
    | HELPER - POSITIVE INTEGER
    |--------------------------------------------------------------------------
    */

    $isPositiveInteger = function ($value) {

        if (
            !is_string($value) &&
            !is_int($value)
        ) {

            return false;
        }

        return preg_match(
            '/^[1-9][0-9]*$/',
            (string)$value
        );
    };

    /*
    |--------------------------------------------------------------------------
    | SEARCH
    |--------------------------------------------------------------------------
    */

    $search = null;

    if (isset($_GET['search'])) {

        $search =
            trim(
                (string)$_GET['search']
            );

        if ($search === '') {

            $search = null;
        }

        if (
            $search !== null &&
            mb_strlen($search) > 150
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "search cannot exceed 150 characters."
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS
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

    $status = null;

    if (isset($_GET['status'])) {

        $status =
            strtolower(
                trim(
                    (string)$_GET['status']
                )
            );

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
    }

    /*
    |--------------------------------------------------------------------------
    | EVENT DATE
    |--------------------------------------------------------------------------
    */

    $eventDate = null;

    if (isset($_GET['event_date'])) {

        $eventDate =
            trim(
                (string)$_GET['event_date']
            );

        if (
            !$isValidDate($eventDate)
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "event_date must be a valid date in YYYY-MM-DD format."
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FROM DATE
    |--------------------------------------------------------------------------
    */

    $fromDate = null;

    if (isset($_GET['from_date'])) {

        $fromDate =
            trim(
                (string)$_GET['from_date']
            );

        if (
            !$isValidDate($fromDate)
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "from_date must be a valid date in YYYY-MM-DD format."
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | TO DATE
    |--------------------------------------------------------------------------
    */

    $toDate = null;

    if (isset($_GET['to_date'])) {

        $toDate =
            trim(
                (string)$_GET['to_date']
            );

        if (
            !$isValidDate($toDate)
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "to_date must be a valid date in YYYY-MM-DD format."
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DATE RANGE VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        $fromDate !== null &&
        $toDate !== null &&
        $fromDate > $toDate
    ) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" =>
                "from_date cannot be greater than to_date."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | EVENT DATE VS RANGE VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        $eventDate !== null &&
        $fromDate !== null &&
        $eventDate < $fromDate
    ) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" =>
                "event_date cannot be before from_date."
        ]);

        exit;
    }

    if (
        $eventDate !== null &&
        $toDate !== null &&
        $eventDate > $toDate
    ) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" =>
                "event_date cannot be after to_date."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | HALL ID
    |--------------------------------------------------------------------------
    */

    $hallId = null;

    if (isset($_GET['hall_id'])) {

        $hallIdValue =
            trim(
                (string)$_GET['hall_id']
            );

        if (
            !$isPositiveInteger(
                $hallIdValue
            )
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "hall_id must be a positive integer."
            ]);

            exit;
        }

        $hallId =
            filter_var(
                $hallIdValue,
                FILTER_VALIDATE_INT,
                [
                    "options" => [
                        "min_range" => 1
                    ]
                ]
            );

        if ($hallId === false) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "hall_id is out of valid range."
            ]);

            exit;
        }

        $hallId = (int)$hallId;

        /*
        |--------------------------------------------------------------------------
        | VERIFY HALL EXISTS
        |--------------------------------------------------------------------------
        */

        $hallCheckSql = "

            SELECT
                id,
                name,
                status

            FROM halls

            WHERE id = ?

            LIMIT 1
        ";

        $hallCheckStmt =
            $conn->prepare(
                $hallCheckSql
            );

        if (!$hallCheckStmt) {

            throw new Exception(
                "Failed to prepare hall validation query: " .
                $conn->error
            );
        }

        $hallCheckStmt->bind_param(
            "i",
            $hallId
        );

        if (!$hallCheckStmt->execute()) {

            $error =
                $hallCheckStmt->error;

            $hallCheckStmt->close();

            throw new Exception(
                "Failed to validate hall: " .
                $error
            );
        }

        $hallCheckResult =
            $hallCheckStmt->get_result();

        $hallCheck =
            $hallCheckResult->fetch_assoc();

        $hallCheckStmt->close();

        if (!$hallCheck) {

            http_response_code(404);

            echo json_encode([
                "status" => false,
                "message" =>
                    "Hall not found.",
                "hall_id" =>
                    $hallId
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MIN GUEST COUNT
    |--------------------------------------------------------------------------
    */

    $minGuestCount = null;

    if (
        isset(
            $_GET['min_guest_count']
        )
    ) {

        $value =
            trim(
                (string)$_GET['min_guest_count']
            );

        if (
            !$isPositiveInteger($value)
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "min_guest_count must be a positive integer."
            ]);

            exit;
        }

        $minGuestCount =
            filter_var(
                $value,
                FILTER_VALIDATE_INT,
                [
                    "options" => [
                        "min_range" => 1
                    ]
                ]
            );

        if ($minGuestCount === false) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "min_guest_count is out of valid range."
            ]);

            exit;
        }

        $minGuestCount =
            (int)$minGuestCount;
    }

    /*
    |--------------------------------------------------------------------------
    | MAX GUEST COUNT
    |--------------------------------------------------------------------------
    */

    $maxGuestCount = null;

    if (
        isset(
            $_GET['max_guest_count']
        )
    ) {

        $value =
            trim(
                (string)$_GET['max_guest_count']
            );

        if (
            !$isPositiveInteger($value)
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "max_guest_count must be a positive integer."
            ]);

            exit;
        }

        $maxGuestCount =
            filter_var(
                $value,
                FILTER_VALIDATE_INT,
                [
                    "options" => [
                        "min_range" => 1
                    ]
                ]
            );

        if ($maxGuestCount === false) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "max_guest_count is out of valid range."
            ]);

            exit;
        }

        $maxGuestCount =
            (int)$maxGuestCount;
    }

    /*
    |--------------------------------------------------------------------------
    | GUEST COUNT RANGE VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        $minGuestCount !== null &&
        $maxGuestCount !== null &&
        $minGuestCount > $maxGuestCount
    ) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" =>
                "min_guest_count cannot be greater than max_guest_count."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | ASSIGNED ADMIN ID
    |--------------------------------------------------------------------------
    */

    $assignedAdminId = null;

    if (
        isset(
            $_GET['assigned_admin_id']
        )
    ) {

        $value =
            trim(
                (string)$_GET['assigned_admin_id']
            );

        if (
            !$isPositiveInteger($value)
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "assigned_admin_id must be a positive integer."
            ]);

            exit;
        }

        $assignedAdminId =
            filter_var(
                $value,
                FILTER_VALIDATE_INT,
                [
                    "options" => [
                        "min_range" => 1
                    ]
                ]
            );

        if ($assignedAdminId === false) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "assigned_admin_id is out of valid range."
            ]);

            exit;
        }

        $assignedAdminId =
            (int)$assignedAdminId;

        /*
        |--------------------------------------------------------------------------
        | STAFF CANNOT REQUEST ANOTHER USER
        |--------------------------------------------------------------------------
        */

        if (
            $currentUserRole === 'staff' &&
            $assignedAdminId !== $currentUserId
        ) {

            http_response_code(403);

            echo json_encode([
                "status" => false,
                "message" =>
                    "Staff can only view their own assigned leads."
            ]);

            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | VERIFY ASSIGNED USER
        |--------------------------------------------------------------------------
        */

        $assignedUserSql = "

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

        $assignedUserStmt =
            $conn->prepare(
                $assignedUserSql
            );

        if (!$assignedUserStmt) {

            throw new Exception(
                "Failed to prepare assigned user validation query: " .
                $conn->error
            );
        }

        $assignedUserStmt->bind_param(
            "i",
            $assignedAdminId
        );

        if (
            !$assignedUserStmt->execute()
        ) {

            $error =
                $assignedUserStmt->error;

            $assignedUserStmt->close();

            throw new Exception(
                "Failed to validate assigned user: " .
                $error
            );
        }

        $assignedUserResult =
            $assignedUserStmt->get_result();

        $assignedUser =
            $assignedUserResult->fetch_assoc();

        $assignedUserStmt->close();

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
        | ASSIGNED USER ROLE
        |--------------------------------------------------------------------------
        */

        $assignedRole =
            strtolower(
                trim(
                    (string)$assignedUser['role']
                )
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
                    "Assigned user must have admin or staff role."
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SOURCE
    |--------------------------------------------------------------------------
    */

    $allowedSources = [

        'website',
        'whatsapp',
        'phone',
        'admin',
        'other'
    ];

    $source = null;

    if (isset($_GET['source'])) {

        $source =
            strtolower(
                trim(
                    (string)$_GET['source']
                )
            );

        if (
            !in_array(
                $source,
                $allowedSources,
                true
            )
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "Invalid source.",
                "allowed_sources" =>
                    $allowedSources
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MIN AMOUNT
    |--------------------------------------------------------------------------
    */

    $minAmount = null;

    if (isset($_GET['min_amount'])) {

        $value =
            trim(
                (string)$_GET['min_amount']
            );

        /*
        | Allow:
        | 100
        | 100.50
        |
        | Reject:
        | -10
        | abc
        | 10.555
        */

        if (
            !preg_match(
                '/^\d{1,10}(\.\d{1,2})?$/',
                $value
            )
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "min_amount must be a valid positive amount with maximum 2 decimal places."
            ]);

            exit;
        }

        $minAmount =
            (float)$value;

        if ($minAmount < 0) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "min_amount cannot be negative."
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MAX AMOUNT
    |--------------------------------------------------------------------------
    */

    $maxAmount = null;

    if (isset($_GET['max_amount'])) {

        $value =
            trim(
                (string)$_GET['max_amount']
            );

        if (
            !preg_match(
                '/^\d{1,10}(\.\d{1,2})?$/',
                $value
            )
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "max_amount must be a valid positive amount with maximum 2 decimal places."
            ]);

            exit;
        }

        $maxAmount =
            (float)$value;

        if ($maxAmount < 0) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "max_amount cannot be negative."
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | AMOUNT RANGE
    |--------------------------------------------------------------------------
    */

    if (
        $minAmount !== null &&
        $maxAmount !== null &&
        $minAmount > $maxAmount
    ) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" =>
                "min_amount cannot be greater than max_amount."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | PAGE
    |--------------------------------------------------------------------------
    */

    $page = 1;

    if (isset($_GET['page'])) {

        $pageValue =
            trim(
                (string)$_GET['page']
            );

        if (
            !$isPositiveInteger(
                $pageValue
            )
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "page must be a positive integer."
            ]);

            exit;
        }

        $page =
            filter_var(
                $pageValue,
                FILTER_VALIDATE_INT,
                [
                    "options" => [
                        "min_range" => 1
                    ]
                ]
            );

        if ($page === false) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "Invalid page value."
            ]);

            exit;
        }

        $page = (int)$page;
    }

    /*
    |--------------------------------------------------------------------------
    | LIMIT
    |--------------------------------------------------------------------------
    */

    $limit = 20;

    if (isset($_GET['limit'])) {

        $limitValue =
            trim(
                (string)$_GET['limit']
            );

        if (
            !$isPositiveInteger(
                $limitValue
            )
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "limit must be a positive integer."
            ]);

            exit;
        }

        $limit =
            filter_var(
                $limitValue,
                FILTER_VALIDATE_INT,
                [
                    "options" => [
                        "min_range" => 1,
                        "max_range" => 100
                    ]
                ]
            );

        if ($limit === false) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "limit must be between 1 and 100."
            ]);

            exit;
        }

        $limit = (int)$limit;
    }

    /*
    |--------------------------------------------------------------------------
    | SORT BY
    |--------------------------------------------------------------------------
    */

    $allowedSortFields = [

        "id" =>
            "l.id",

        "lead_reference" =>
            "l.lead_reference",

        "customer_name" =>
            "l.customer_name",

        "event_date" =>
            "hld.event_date",

        "guest_count" =>
            "hld.guest_count",

        "status" =>
            "l.status",

        "estimated_amount" =>
            "l.estimated_amount",

        "converted_amount" =>
            "l.converted_amount",

        "created_at" =>
            "l.created_at",

        "updated_at" =>
            "l.updated_at"
    ];

    $sortBy = "created_at";

    if (isset($_GET['sort_by'])) {

        $sortBy =
            trim(
                (string)$_GET['sort_by']
            );

        if (
            !array_key_exists(
                $sortBy,
                $allowedSortFields
            )
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "Invalid sort_by value.",
                "allowed_sort_by" =>
                    array_keys(
                        $allowedSortFields
                    )
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SORT ORDER
    |--------------------------------------------------------------------------
    */

    $sortOrder = "DESC";

    if (isset($_GET['sort_order'])) {

        $sortOrder =
            strtoupper(
                trim(
                    (string)$_GET['sort_order']
                )
            );

        if (
            !in_array(
                $sortOrder,
                ['ASC', 'DESC'],
                true
            )
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "sort_order must be ASC or DESC."
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | OFFSET
    |--------------------------------------------------------------------------
    */

    $offset =
        ($page - 1) * $limit;

    /*
    |--------------------------------------------------------------------------
    | BASE WHERE
    |--------------------------------------------------------------------------
    */

    $where = [];

    $params = [];

    $types = "";

    /*
    |--------------------------------------------------------------------------
    | ONLY HALL DOMAIN
    |--------------------------------------------------------------------------
    */

    $where[] =
        "l.domain = 'hall'";

    /*
    |--------------------------------------------------------------------------
    | SEARCH FILTER
    |--------------------------------------------------------------------------
    |
    | Searches:
    | - Lead reference
    | - Customer name
    | - Phone
    | - Email
    | - Event type
    | - Hall name
    | - Hall slug
    |
    */

    if ($search !== null) {

        $where[] = "

            (
                l.lead_reference LIKE ?
                OR l.customer_name LIKE ?
                OR l.customer_phone LIKE ?
                OR l.customer_email LIKE ?
                OR hld.event_type LIKE ?
                OR h.name LIKE ?
                OR h.slug LIKE ?
            )

        ";

        $searchLike =
            "%" . $search . "%";

        for ($i = 0; $i < 7; $i++) {

            $params[] =
                $searchLike;

            $types .= "s";
        }
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS FILTER
    |--------------------------------------------------------------------------
    */

    if ($status !== null) {

        $where[] =
            "l.status = ?";

        $params[] =
            $status;

        $types .= "s";
    }

    /*
    |--------------------------------------------------------------------------
    | EXACT EVENT DATE
    |--------------------------------------------------------------------------
    */

    if ($eventDate !== null) {

        $where[] =
            "hld.event_date = ?";

        $params[] =
            $eventDate;

        $types .= "s";
    }

    /*
    |--------------------------------------------------------------------------
    | FROM DATE
    |--------------------------------------------------------------------------
    */

    if ($fromDate !== null) {

        $where[] =
            "hld.event_date >= ?";

        $params[] =
            $fromDate;

        $types .= "s";
    }

    /*
    |--------------------------------------------------------------------------
    | TO DATE
    |--------------------------------------------------------------------------
    */

    if ($toDate !== null) {

        $where[] =
            "hld.event_date <= ?";

        $params[] =
            $toDate;

        $types .= "s";
    }

    /*
    |--------------------------------------------------------------------------
    | HALL FILTER
    |--------------------------------------------------------------------------
    */

    if ($hallId !== null) {

        $where[] =
            "hld.hall_id = ?";

        $params[] =
            $hallId;

        $types .= "i";
    }

    /*
    |--------------------------------------------------------------------------
    | MIN GUEST COUNT
    |--------------------------------------------------------------------------
    */

    if ($minGuestCount !== null) {

        $where[] =
            "hld.guest_count >= ?";

        $params[] =
            $minGuestCount;

        $types .= "i";
    }

    /*
    |--------------------------------------------------------------------------
    | MAX GUEST COUNT
    |--------------------------------------------------------------------------
    */

    if ($maxGuestCount !== null) {

        $where[] =
            "hld.guest_count <= ?";

        $params[] =
            $maxGuestCount;

        $types .= "i";
    }

    /*
    |--------------------------------------------------------------------------
    | ASSIGNED ADMIN FILTER
    |--------------------------------------------------------------------------
    */

    if ($assignedAdminId !== null) {

        $where[] =
            "l.assigned_admin_id = ?";

        $params[] =
            $assignedAdminId;

        $types .= "i";
    }

    /*
    |--------------------------------------------------------------------------
    | SOURCE FILTER
    |--------------------------------------------------------------------------
    */

    if ($source !== null) {

        $where[] =
            "l.source = ?";

        $params[] =
            $source;

        $types .= "s";
    }

    /*
    |--------------------------------------------------------------------------
    | MIN AMOUNT
    |--------------------------------------------------------------------------
    |
    | Uses estimated_amount.
    |
    */

    if ($minAmount !== null) {

        $where[] =
            "l.estimated_amount >= ?";

        $params[] =
            $minAmount;

        $types .= "d";
    }

    /*
    |--------------------------------------------------------------------------
    | MAX AMOUNT
    |--------------------------------------------------------------------------
    */

    if ($maxAmount !== null) {

        $where[] =
            "l.estimated_amount <= ?";

        $params[] =
            $maxAmount;

        $types .= "d";
    }

    /*
    |--------------------------------------------------------------------------
    | STAFF SCOPE
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | Staff should never be able to see:
    | - another staff's lead
    | - unassigned lead
    |
    */

    if ($currentUserRole === 'staff') {

        $where[] =
            "l.assigned_admin_id = ?";

        $params[] =
            $currentUserId;

        $types .= "i";
    }

    /*
    |--------------------------------------------------------------------------
    | WHERE SQL
    |--------------------------------------------------------------------------
    */

    $whereSql =
        implode(
            " AND ",
            $where
        );

    /*
    |--------------------------------------------------------------------------
    | COUNT TOTAL RECORDS
    |--------------------------------------------------------------------------
    */

    $countSql = "

        SELECT
            COUNT(*) AS total

        FROM leads l

        INNER JOIN hall_lead_details hld
            ON hld.lead_id = l.id

        LEFT JOIN halls h
            ON h.id = hld.hall_id

        LEFT JOIN hall_types ht
            ON ht.id = h.hall_type_id

        LEFT JOIN admin_users au
            ON au.id = l.assigned_admin_id

        WHERE
            {$whereSql}
    ";

    $countStmt =
        $conn->prepare(
            $countSql
        );

    if (!$countStmt) {

        throw new Exception(
            "Failed to prepare count query: " .
            $conn->error
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DYNAMIC BIND FOR COUNT
    |--------------------------------------------------------------------------
    */

    if (count($params) > 0) {

        $bindParams = [];

        $bindParams[] =
            $types;

        foreach ($params as $key => $value) {

            $bindParams[] =
                &$params[$key];
        }

        call_user_func_array(
            [$countStmt, 'bind_param'],
            $bindParams
        );
    }

    if (!$countStmt->execute()) {

        $error =
            $countStmt->error;

        $countStmt->close();

        throw new Exception(
            "Failed to execute count query: " .
            $error
        );
    }

    $countResult =
        $countStmt->get_result();

    $countRow =
        $countResult->fetch_assoc();

    $countStmt->close();

    $totalRecords =
        (int)($countRow['total'] ?? 0);

    /*
    |--------------------------------------------------------------------------
    | CALCULATE PAGINATION
    |--------------------------------------------------------------------------
    */

    $totalPages =
        $totalRecords > 0
            ? (int)ceil(
                $totalRecords / $limit
            )
            : 0;

    /*
    |--------------------------------------------------------------------------
    | FETCH LEADS
    |--------------------------------------------------------------------------
    */

    $dataSql = "

        SELECT

            l.id,
            l.lead_reference,
            l.domain,

            l.customer_name,
            l.customer_phone,
            l.customer_email,

            l.event_date AS common_event_date,
            l.guest_count AS common_guest_count,
            l.preferred_time,

            l.requirement,

            l.status,

            l.estimated_amount,
            l.converted_amount,
            l.converted_at,

            l.source,
            l.assigned_admin_id,

            l.created_at,
            l.updated_at,

            /*
            |--------------------------------------------------------------------------
            | HALL LEAD DETAILS
            |--------------------------------------------------------------------------
            */

            hld.event_type,
            hld.event_date AS hall_event_date,
            hld.event_time,
            hld.guest_count AS hall_guest_count,

            hld.food_required,
            hld.decoration_required,

            hld.other_requirements,

            /*
            |--------------------------------------------------------------------------
            | HALL
            |--------------------------------------------------------------------------
            */

            h.id AS hall_id,
            h.name AS hall_name,
            h.slug AS hall_slug,
            h.description AS hall_description,

            h.capacity AS hall_capacity,
            h.area AS hall_area,
            h.price_description AS hall_price_description,
            h.status AS hall_status,

            /*
            |--------------------------------------------------------------------------
            | HALL TYPE
            |--------------------------------------------------------------------------
            */

            ht.id AS hall_type_id,
            ht.name AS hall_type_name,
            ht.slug AS hall_type_slug,
            ht.status AS hall_type_status,

            /*
            |--------------------------------------------------------------------------
            | ASSIGNED ADMIN
            |--------------------------------------------------------------------------
            */

            au.id AS assigned_user_id,
            au.name AS assigned_user_name,
            au.email AS assigned_user_email,
            au.phone AS assigned_user_phone,
            au.role AS assigned_user_role,
            au.status AS assigned_user_status,

            /*
            |--------------------------------------------------------------------------
            | NOTIFICATION COUNT
            |--------------------------------------------------------------------------
            */

            (
                SELECT COUNT(*)
                FROM lead_notifications ln
                WHERE ln.lead_id = l.id
            ) AS notification_count,

            (
                SELECT COUNT(*)
                FROM lead_notifications ln
                WHERE
                    ln.lead_id = l.id
                    AND ln.status = 'pending'
            ) AS pending_notification_count,

            (
                SELECT COUNT(*)
                FROM lead_notifications ln
                WHERE
                    ln.lead_id = l.id
                    AND ln.status = 'sent'
            ) AS sent_notification_count,

            (
                SELECT COUNT(*)
                FROM lead_notifications ln
                WHERE
                    ln.lead_id = l.id
                    AND ln.status = 'failed'
            ) AS failed_notification_count

        FROM leads l

        INNER JOIN hall_lead_details hld
            ON hld.lead_id = l.id

        LEFT JOIN halls h
            ON h.id = hld.hall_id

        LEFT JOIN hall_types ht
            ON ht.id = h.hall_type_id

        LEFT JOIN admin_users au
            ON au.id = l.assigned_admin_id

        WHERE
            {$whereSql}

        ORDER BY
            {$allowedSortFields[$sortBy]}
            {$sortOrder}

        LIMIT ?
        OFFSET ?
    ";

    /*
    |--------------------------------------------------------------------------
    | PAGINATION PARAMETERS
    |--------------------------------------------------------------------------
    */

    $dataParams =
        $params;

    $dataTypes =
        $types . "ii";

    $dataParams[] =
        $limit;

    $dataParams[] =
        $offset;

    /*
    |--------------------------------------------------------------------------
    | PREPARE DATA QUERY
    |--------------------------------------------------------------------------
    */

    $dataStmt =
        $conn->prepare(
            $dataSql
        );

    if (!$dataStmt) {

        throw new Exception(
            "Failed to prepare lead list query: " .
            $conn->error
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DYNAMIC BIND DATA QUERY
    |--------------------------------------------------------------------------
    */

    $bindParams = [];

    $bindParams[] =
        $dataTypes;

    foreach (
        $dataParams
        as $key => $value
    ) {

        $bindParams[] =
            &$dataParams[$key];
    }

    call_user_func_array(
        [$dataStmt, 'bind_param'],
        $bindParams
    );

    /*
    |--------------------------------------------------------------------------
    | EXECUTE DATA QUERY
    |--------------------------------------------------------------------------
    */

    if (!$dataStmt->execute()) {

        $error =
            $dataStmt->error;

        $dataStmt->close();

        throw new Exception(
            "Failed to execute lead list query: " .
            $error
        );
    }

    $dataResult =
        $dataStmt->get_result();

    /*
    |--------------------------------------------------------------------------
    | BUILD LEAD LIST
    |--------------------------------------------------------------------------
    */

    $leads = [];

    while (
        $row =
        $dataResult->fetch_assoc()
    ) {

        /*
        |--------------------------------------------------------------------------
        | SAFE STATUS VALIDATION
        |--------------------------------------------------------------------------
        */

        if (
            !in_array(
                $row['status'],
                $allowedStatuses,
                true
            )
        ) {

            throw new Exception(
                "Invalid lead status found in database for lead ID " .
                $row['id']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | SAFE SOURCE VALIDATION
        |--------------------------------------------------------------------------
        */

        if (
            !in_array(
                $row['source'],
                $allowedSources,
                true
            )
        ) {

            throw new Exception(
                "Invalid lead source found in database for lead ID " .
                $row['id']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | ASSIGNED USER
        |--------------------------------------------------------------------------
        */

        $assignedUser = null;

        if (
            $row['assigned_user_id'] !== null
        ) {

            $assignedUser = [

                "id" =>
                    (int)$row['assigned_user_id'],

                "name" =>
                    $row['assigned_user_name'],

                "email" =>
                    $row['assigned_user_email'],

                "phone" =>
                    $row['assigned_user_phone'],

                "role" =>
                    $row['assigned_user_role'],

                "status" =>
                    $row['assigned_user_status']
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | HALL DATA
        |--------------------------------------------------------------------------
        */

        $hallData = null;

        if (
            $row['hall_id'] !== null
        ) {

            $hallData = [

                "id" =>
                    (int)$row['hall_id'],

                "name" =>
                    $row['hall_name'],

                "slug" =>
                    $row['hall_slug'],

                "description" =>
                    $row['hall_description'],

                "capacity" =>
                    (int)$row['hall_capacity'],

                "area" =>
                    $row['hall_area'],

                "price_description" =>
                    $row['hall_price_description'],

                "status" =>
                    $row['hall_status'],

                "hall_type" =>
                    $row['hall_type_id'] !== null
                        ? [

                            "id" =>
                                (int)$row['hall_type_id'],

                            "name" =>
                                $row['hall_type_name'],

                            "slug" =>
                                $row['hall_type_slug'],

                            "status" =>
                                $row['hall_type_status']
                        ]
                        : null
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | EVENT DATE
        |--------------------------------------------------------------------------
        */

        $finalEventDate =
            $row['hall_event_date'] !== null
                ? $row['hall_event_date']
                : $row['common_event_date'];

        /*
        |--------------------------------------------------------------------------
        | GUEST COUNT
        |--------------------------------------------------------------------------
        */

        $finalGuestCount =
            $row['hall_guest_count'] !== null
                ? (int)$row['hall_guest_count']
                : (
                    $row['common_guest_count'] !== null
                        ? (int)$row['common_guest_count']
                        : null
                );

        /*
        |--------------------------------------------------------------------------
        | EVENT TIME
        |--------------------------------------------------------------------------
        */

        $finalEventTime =
            $row['event_time'] !== null
                ? $row['event_time']
                : $row['preferred_time'];

        /*
        |--------------------------------------------------------------------------
        | LEAD OBJECT
        |--------------------------------------------------------------------------
        */

        $leads[] = [

            "id" =>
                (int)$row['id'],

            "lead_reference" =>
                $row['lead_reference'],

            "domain" =>
                $row['domain'],

            /*
            |--------------------------------------------------------------------------
            | CUSTOMER
            |--------------------------------------------------------------------------
            */

            "customer" => [

                "name" =>
                    $row['customer_name'],

                "phone" =>
                    $row['customer_phone'],

                "email" =>
                    $row['customer_email']
            ],

            /*
            |--------------------------------------------------------------------------
            | HALL
            |--------------------------------------------------------------------------
            */

            "hall" =>
                $hallData,

            /*
            |--------------------------------------------------------------------------
            | EVENT
            |--------------------------------------------------------------------------
            */

            "event" => [

                "type" =>
                    $row['event_type'],

                "date" =>
                    $finalEventDate,

                "time" =>
                    $finalEventTime,

                "guest_count" =>
                    $finalGuestCount,

                "food_required" =>
                    (bool)$row['food_required'],

                "decoration_required" =>
                    (bool)$row['decoration_required']
            ],

            /*
            |--------------------------------------------------------------------------
            | REQUIREMENT
            |--------------------------------------------------------------------------
            */

            "requirement" =>
                $row['other_requirements'] !== null
                    ? $row['other_requirements']
                    : $row['requirement'],

            /*
            |--------------------------------------------------------------------------
            | STATUS
            |--------------------------------------------------------------------------
            */

            "status" =>
                $row['status'],

            /*
            |--------------------------------------------------------------------------
            | AMOUNTS
            |--------------------------------------------------------------------------
            */

            "estimated_amount" =>
                $row['estimated_amount'] !== null
                    ? (float)$row['estimated_amount']
                    : null,

            "converted_amount" =>
                $row['converted_amount'] !== null
                    ? (float)$row['converted_amount']
                    : null,

            "converted_at" =>
                $row['converted_at'],

            /*
            |--------------------------------------------------------------------------
            | SOURCE
            |--------------------------------------------------------------------------
            */

            "source" =>
                $row['source'],

            /*
            |--------------------------------------------------------------------------
            | ASSIGNED USER
            |--------------------------------------------------------------------------
            */

            "assigned_admin" =>
                $assignedUser,

            /*
            |--------------------------------------------------------------------------
            | NOTIFICATIONS
            |--------------------------------------------------------------------------
            */

            "notifications" => [

                "total" =>
                    (int)$row['notification_count'],

                "pending" =>
                    (int)$row['pending_notification_count'],

                "sent" =>
                    (int)$row['sent_notification_count'],

                "failed" =>
                    (int)$row['failed_notification_count']
            ],

            /*
            |--------------------------------------------------------------------------
            | TIMESTAMPS
            |--------------------------------------------------------------------------
            */

            "created_at" =>
                $row['created_at'],

            "updated_at" =>
                $row['updated_at']
        ];
    }

    $dataStmt->close();

    /*
    |--------------------------------------------------------------------------
    | PAGINATION DATA
    |--------------------------------------------------------------------------
    */

    $hasNextPage =
        $page < $totalPages;

    $hasPreviousPage =
        $page > 1 && $totalPages > 0;

    /*
    |--------------------------------------------------------------------------
    | FINAL RESPONSE
    |--------------------------------------------------------------------------
    */

    http_response_code(200);

    echo json_encode([

        "status" => true,

        "message" =>
            "Hall leads fetched successfully.",

        "data" => [

            /*
            |--------------------------------------------------------------------------
            | USER SCOPE
            |--------------------------------------------------------------------------
            */

            "user_scope" => [

                "role" =>
                    $currentUserRole,

                "user_id" =>
                    $currentUserId,

                "scope" =>
                    $currentUserRole === 'admin'
                        ? "all_hall_leads"
                        : "assigned_hall_leads_only"
            ],

            /*
            |--------------------------------------------------------------------------
            | FILTERS
            |--------------------------------------------------------------------------
            */

            "filters" => [

                "search" =>
                    $search,

                "status" =>
                    $status,

                "event_date" =>
                    $eventDate,

                "from_date" =>
                    $fromDate,

                "to_date" =>
                    $toDate,

                "hall_id" =>
                    $hallId,

                "min_guest_count" =>
                    $minGuestCount,

                "max_guest_count" =>
                    $maxGuestCount,

                "assigned_admin_id" =>
                    $assignedAdminId,

                "source" =>
                    $source,

                "min_amount" =>
                    $minAmount,

                "max_amount" =>
                    $maxAmount
            ],

            /*
            |--------------------------------------------------------------------------
            | PAGINATION
            |--------------------------------------------------------------------------
            */

            "pagination" => [

                "page" =>
                    $page,

                "limit" =>
                    $limit,

                "total_records" =>
                    $totalRecords,

                "total_pages" =>
                    $totalPages,

                "has_next_page" =>
                    $hasNextPage,

                "has_previous_page" =>
                    $hasPreviousPage
            ],

            /*
            |--------------------------------------------------------------------------
            | SORT
            |--------------------------------------------------------------------------
            */

            "sort" => [

                "sort_by" =>
                    $sortBy,

                "sort_order" =>
                    $sortOrder
            ],

            /*
            |--------------------------------------------------------------------------
            | LEADS
            |--------------------------------------------------------------------------
            */

            "leads" =>
                $leads
        ]
    ]);

    exit;

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([

        "status" => false,

        "message" =>
            "Failed to fetch hall leads.",

        "error" =>
            $e->getMessage()
    ]);

    exit;
}