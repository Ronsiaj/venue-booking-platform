<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/middleware.php';

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {

    http_response_code(405);

    echo json_encode([
        "status" => false,
        "message" => "Only GET method is allowed."
    ]);

    exit;
}

try {

    /*
    |--------------------------------------------------------------------------
    | AUTHENTICATION
    |--------------------------------------------------------------------------
    | Admin:
    |   Can view all leads
    |
    | Staff:
    |   Can view only leads assigned to themselves
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
        trim((string)($currentUser['role'] ?? ''))
    );

    /*
    |--------------------------------------------------------------------------
    | IMPORTANT:
    | JWT USER ID IS STORED IN "sub"
    |
    | Fallback to "id" for compatibility.
    |--------------------------------------------------------------------------
    */

    $currentUserId =
        $currentUser['sub']
        ?? $currentUser['id']
        ?? null;

    /*
    |--------------------------------------------------------------------------
    | AUTHENTICATED USER VALIDATION
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
                "Only admin or staff users can access leads."
        ]);

        exit;
    }

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
    | ALLOWED QUERY PARAMETERS
    |--------------------------------------------------------------------------
    */

    $allowedParams = [

        'domain',
        'status',
        'search',

        'event_date',
        'from_date',
        'to_date',

        'min_amount',
        'max_amount',

        'assigned_admin_id',

        'source',

        'page',
        'limit',

        'sort_by',
        'sort_order'
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
                    "Unknown query parameter: {$key}"
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DOMAIN VALIDATION
    |--------------------------------------------------------------------------
    */

    $allowedDomains = [
        'hall',
        'dining',
        'catering'
    ];

    $domain = null;

    if (
        isset($_GET['domain']) &&
        trim((string)$_GET['domain']) !== ''
    ) {

        $domain =
            strtolower(
                trim((string)$_GET['domain'])
            );

        if (
            !in_array(
                $domain,
                $allowedDomains,
                true
            )
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "Invalid domain.",
                "allowed_domains" =>
                    $allowedDomains
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS VALIDATION
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

    if (
        isset($_GET['status']) &&
        trim((string)$_GET['status']) !== ''
    ) {

        $status =
            strtolower(
                trim((string)$_GET['status'])
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
    | SEARCH
    |--------------------------------------------------------------------------
    */

    $search = null;

    if (
        isset($_GET['search'])
    ) {

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
    | DATE VALIDATION FUNCTION
    |--------------------------------------------------------------------------
    */

    function validateLeadListDate(
        string $value,
        string $fieldName
    ): string {

        $value =
            trim($value);

        if (
            !preg_match(
                '/^\d{4}-\d{2}-\d{2}$/',
                $value
            )
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "{$fieldName} must be in YYYY-MM-DD format."
            ]);

            exit;
        }

        $dateObject =
            DateTime::createFromFormat(
                'Y-m-d',
                $value
            );

        $dateErrors =
            DateTime::getLastErrors();

        if (
            $dateObject === false ||
            (
                is_array($dateErrors) &&
                (
                    $dateErrors['warning_count'] > 0 ||
                    $dateErrors['error_count'] > 0
                )
            ) ||
            $dateObject->format('Y-m-d') !== $value
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "Invalid {$fieldName}."
            ]);

            exit;
        }

        return $value;
    }

    /*
    |--------------------------------------------------------------------------
    | EVENT DATE
    |--------------------------------------------------------------------------
    */

    $eventDate = null;

    if (
        isset($_GET['event_date']) &&
        trim((string)$_GET['event_date']) !== ''
    ) {

        $eventDate =
            validateLeadListDate(
                (string)$_GET['event_date'],
                'event_date'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | FROM DATE
    |--------------------------------------------------------------------------
    */

    $fromDate = null;

    if (
        isset($_GET['from_date']) &&
        trim((string)$_GET['from_date']) !== ''
    ) {

        $fromDate =
            validateLeadListDate(
                (string)$_GET['from_date'],
                'from_date'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | TO DATE
    |--------------------------------------------------------------------------
    */

    $toDate = null;

    if (
        isset($_GET['to_date']) &&
        trim((string)$_GET['to_date']) !== ''
    ) {

        $toDate =
            validateLeadListDate(
                (string)$_GET['to_date'],
                'to_date'
            );
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
    | MINIMUM AMOUNT
    |--------------------------------------------------------------------------
    */

    $minAmount = null;

    if (
        isset($_GET['min_amount']) &&
        trim((string)$_GET['min_amount']) !== ''
    ) {

        $minAmountRaw =
            trim(
                (string)$_GET['min_amount']
            );

        if (
            !preg_match(
                '/^\d{1,10}(\.\d{1,2})?$/',
                $minAmountRaw
            )
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "min_amount must be a valid decimal with maximum 2 decimal places."
            ]);

            exit;
        }

        $minAmount =
            (float)$minAmountRaw;

        if (
            $minAmount < 0 ||
            $minAmount > 9999999999.99
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "min_amount is out of allowed range."
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MAXIMUM AMOUNT
    |--------------------------------------------------------------------------
    */

    $maxAmount = null;

    if (
        isset($_GET['max_amount']) &&
        trim((string)$_GET['max_amount']) !== ''
    ) {

        $maxAmountRaw =
            trim(
                (string)$_GET['max_amount']
            );

        if (
            !preg_match(
                '/^\d{1,10}(\.\d{1,2})?$/',
                $maxAmountRaw
            )
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "max_amount must be a valid decimal with maximum 2 decimal places."
            ]);

            exit;
        }

        $maxAmount =
            (float)$maxAmountRaw;

        if (
            $maxAmount < 0 ||
            $maxAmount > 9999999999.99
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "max_amount is out of allowed range."
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
    | ASSIGNED ADMIN ID
    |--------------------------------------------------------------------------
    */

    $requestedAssignedAdminId = null;

    if (
        isset($_GET['assigned_admin_id']) &&
        trim((string)$_GET['assigned_admin_id']) !== ''
    ) {

        $assignedAdminIdRaw =
            trim(
                (string)$_GET['assigned_admin_id']
            );

        if (
            !ctype_digit($assignedAdminIdRaw) ||
            (int)$assignedAdminIdRaw <= 0
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "assigned_admin_id must be a positive integer."
            ]);

            exit;
        }

        $requestedAssignedAdminId =
            (int)$assignedAdminIdRaw;
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

    if (
        isset($_GET['source']) &&
        trim((string)$_GET['source']) !== ''
    ) {

        $source =
            strtolower(
                trim((string)$_GET['source'])
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
    | PAGE
    |--------------------------------------------------------------------------
    */

    $page = 1;

    if (
        isset($_GET['page']) &&
        trim((string)$_GET['page']) !== ''
    ) {

        $pageRaw =
            trim(
                (string)$_GET['page']
            );

        if (
            !ctype_digit($pageRaw) ||
            (int)$pageRaw < 1
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
            (int)$pageRaw;

        if ($page > 1000000) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "page value is too large."
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | LIMIT
    |--------------------------------------------------------------------------
    */

    $limit = 20;

    if (
        isset($_GET['limit']) &&
        trim((string)$_GET['limit']) !== ''
    ) {

        $limitRaw =
            trim(
                (string)$_GET['limit']
            );

        if (
            !ctype_digit($limitRaw) ||
            (int)$limitRaw < 1
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
            (int)$limitRaw;

        if ($limit > 100) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "limit cannot exceed 100."
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
    | SORT FIELDS
    |--------------------------------------------------------------------------
    */

    $allowedSortFields = [

        'id' =>
            'l.id',

        'lead_reference' =>
            'l.lead_reference',

        'customer_name' =>
            'l.customer_name',

        'domain' =>
            'l.domain',

        'event_date' =>
            'COALESCE(
                hld.event_date,
                dld.dining_date,
                cld.event_date,
                l.event_date
            )',

        'guest_count' =>
            'COALESCE(
                hld.guest_count,
                dld.guest_count,
                cld.guest_count,
                l.guest_count
            )',

        'estimated_amount' =>
            'l.estimated_amount',

        'converted_amount' =>
            'l.converted_amount',

        'status' =>
            'l.status',

        'created_at' =>
            'l.created_at',

        'updated_at' =>
            'l.updated_at'
    ];

    $sortBy =
        'created_at';

    if (
        isset($_GET['sort_by']) &&
        trim((string)$_GET['sort_by']) !== ''
    ) {

        $sortByRaw =
            trim(
                (string)$_GET['sort_by']
            );

        if (
            !array_key_exists(
                $sortByRaw,
                $allowedSortFields
            )
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" =>
                    "Invalid sort_by.",
                "allowed_sort_by" =>
                    array_keys(
                        $allowedSortFields
                    )
            ]);

            exit;
        }

        $sortBy =
            $sortByRaw;
    }

    /*
    |--------------------------------------------------------------------------
    | SORT ORDER
    |--------------------------------------------------------------------------
    */

    $sortOrder =
        'DESC';

    if (
        isset($_GET['sort_order']) &&
        trim((string)$_GET['sort_order']) !== ''
    ) {

        $sortOrderRaw =
            strtoupper(
                trim(
                    (string)$_GET['sort_order']
                )
            );

        if (
            !in_array(
                $sortOrderRaw,
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

        $sortOrder =
            $sortOrderRaw;
    }

    /*
    |--------------------------------------------------------------------------
    | WHERE CONDITIONS
    |--------------------------------------------------------------------------
    */

    $where = [
        "1 = 1"
    ];

    $params = [];
    $types = '';

    /*
    |--------------------------------------------------------------------------
    | STAFF SCOPE
    |--------------------------------------------------------------------------
    |
    | ADMIN:
    |   All leads
    |
    | STAFF:
    |   Only leads assigned to logged-in staff
    |
    |--------------------------------------------------------------------------
    */

    if ($currentUserRole === 'staff') {

        /*
        | If staff tries:
        |
        | ?assigned_admin_id=another_staff_id
        |
        | reject request.
        */

        if (
            $requestedAssignedAdminId !== null &&
            $requestedAssignedAdminId !== $currentUserId
        ) {

            http_response_code(403);

            echo json_encode([
                "status" => false,
                "message" =>
                    "Staff can view only leads assigned to themselves.",
                "your_user_id" =>
                    $currentUserId
            ]);

            exit;
        }

        /*
        | Force staff scope.
        */

        $where[] =
            "l.assigned_admin_id = ?";

        $params[] =
            $currentUserId;

        $types .= 'i';

        /*
        | Response filter should show the enforced user ID.
        */

        $effectiveAssignedAdminId =
            $currentUserId;

    } else {

        /*
        |--------------------------------------------------------------------------
        | ADMIN SCOPE
        |--------------------------------------------------------------------------
        */

        $effectiveAssignedAdminId =
            $requestedAssignedAdminId;

        if (
            $requestedAssignedAdminId !== null
        ) {

            $where[] =
                "l.assigned_admin_id = ?";

            $params[] =
                $requestedAssignedAdminId;

            $types .= 'i';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATE ASSIGNED ADMIN FOR ADMIN FILTER
    |--------------------------------------------------------------------------
    */

    if (
        $currentUserRole === 'admin' &&
        $requestedAssignedAdminId !== null
    ) {

        $adminCheckSql = "

            SELECT
                id,
                role,
                status

            FROM admin_users

            WHERE id = ?

            LIMIT 1
        ";

        $adminCheckStmt =
            $conn->prepare(
                $adminCheckSql
            );

        if (!$adminCheckStmt) {

            throw new Exception(
                "Failed to prepare assigned user validation."
            );
        }

        $adminCheckStmt->bind_param(
            "i",
            $requestedAssignedAdminId
        );

        if (!$adminCheckStmt->execute()) {

            $error =
                $adminCheckStmt->error;

            $adminCheckStmt->close();

            throw new Exception(
                "Failed to validate assigned user: " .
                $error
            );
        }

        $adminCheckResult =
            $adminCheckStmt->get_result();

        $assignedUser =
            $adminCheckResult->fetch_assoc();

        $adminCheckStmt->close();

        if (!$assignedUser) {

            http_response_code(404);

            echo json_encode([
                "status" => false,
                "message" =>
                    "Assigned admin/staff user not found.",
                "assigned_admin_id" =>
                    $requestedAssignedAdminId
            ]);

            exit;
        }

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
                    "assigned_admin_id must belong to an admin or staff user."
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DOMAIN FILTER
    |--------------------------------------------------------------------------
    */

    if ($domain !== null) {

        $where[] =
            "l.domain = ?";

        $params[] =
            $domain;

        $types .= 's';
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

        $types .= 's';
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

        $types .= 's';
    }

    /*
    |--------------------------------------------------------------------------
    | SEARCH FILTER
    |--------------------------------------------------------------------------
    */

    if ($search !== null) {

        $where[] = "

            (
                l.lead_reference LIKE ?

                OR l.customer_name LIKE ?

                OR l.customer_phone LIKE ?

                OR l.customer_email LIKE ?

                OR l.requirement LIKE ?

                OR hld.event_type LIKE ?

                OR hld.other_requirements LIKE ?

                OR dld.meal_type LIKE ?

                OR dld.other_requirements LIKE ?

                OR cld.event_type LIKE ?

                OR cld.venue_address LIKE ?

                OR cld.other_requirements LIKE ?

                OR ht.name LIKE ?

                OR h.name LIKE ?

                OR dp.name LIKE ?

                OR cp.name LIKE ?

                OR au.name LIKE ?

                OR au.email LIKE ?
            )
        ";

        $searchValue =
            '%' . $search . '%';

        /*
        | 18 placeholders
        */

        for (
            $i = 0;
            $i < 18;
            $i++
        ) {

            $params[] =
                $searchValue;
        }

        $types .=
            'ssssssssssssssssss';
    }

    /*
    |--------------------------------------------------------------------------
    | EVENT DATE EXPRESSION
    |--------------------------------------------------------------------------
    */

    $eventDateExpression = "

        COALESCE(
            hld.event_date,
            dld.dining_date,
            cld.event_date,
            l.event_date
        )
    ";

    /*
    |--------------------------------------------------------------------------
    | EXACT EVENT DATE
    |--------------------------------------------------------------------------
    */

    if ($eventDate !== null) {

        $where[] =
            "{$eventDateExpression} = ?";

        $params[] =
            $eventDate;

        $types .= 's';
    }

    /*
    |--------------------------------------------------------------------------
    | FROM DATE
    |--------------------------------------------------------------------------
    */

    if ($fromDate !== null) {

        $where[] =
            "{$eventDateExpression} >= ?";

        $params[] =
            $fromDate;

        $types .= 's';
    }

    /*
    |--------------------------------------------------------------------------
    | TO DATE
    |--------------------------------------------------------------------------
    */

    if ($toDate !== null) {

        $where[] =
            "{$eventDateExpression} <= ?";

        $params[] =
            $toDate;

        $types .= 's';
    }

    /*
    |--------------------------------------------------------------------------
    | MINIMUM AMOUNT
    |--------------------------------------------------------------------------
    */

    if ($minAmount !== null) {

        $where[] =
            "COALESCE(l.estimated_amount, 0) >= ?";

        $params[] =
            $minAmount;

        $types .= 'd';
    }

    /*
    |--------------------------------------------------------------------------
    | MAXIMUM AMOUNT
    |--------------------------------------------------------------------------
    */

    if ($maxAmount !== null) {

        $where[] =
            "COALESCE(l.estimated_amount, 0) <= ?";

        $params[] =
            $maxAmount;

        $types .= 'd';
    }

    /*
    |--------------------------------------------------------------------------
    | FINAL WHERE SQL
    |--------------------------------------------------------------------------
    */

    $whereSql =
        implode(
            ' AND ',
            $where
        );

    /*
    |--------------------------------------------------------------------------
    | BASE JOINS
    |--------------------------------------------------------------------------
    */

    $baseJoins = "

        LEFT JOIN admin_users au
            ON au.id = l.assigned_admin_id

        LEFT JOIN hall_lead_details hld
            ON hld.lead_id = l.id

        LEFT JOIN halls h
            ON h.id = hld.hall_id

        LEFT JOIN hall_types ht
            ON ht.id = h.hall_type_id

        LEFT JOIN dining_lead_details dld
            ON dld.lead_id = l.id

        LEFT JOIN dining_packages dp
            ON dp.id = dld.dining_package_id

        LEFT JOIN catering_lead_details cld
            ON cld.lead_id = l.id

        LEFT JOIN catering_packages cp
            ON cp.id = cld.catering_package_id
    ";

    /*
    |--------------------------------------------------------------------------
    | DYNAMIC BIND PARAMETER FUNCTION
    |--------------------------------------------------------------------------
    */

    $bindParams = function (
        mysqli_stmt $stmt,
        string $types,
        array &$params
    ): void {

        if ($types === '') {
            return;
        }

        $bindValues = [];

        $bindValues[] =
            $types;

        foreach (
            $params as $key => &$value
        ) {

            $bindValues[] =
                &$value;
        }

        call_user_func_array(
            [$stmt, 'bind_param'],
            $bindValues
        );

        unset($value);
    };

    /*
    |--------------------------------------------------------------------------
    | COUNT TOTAL RECORDS
    |--------------------------------------------------------------------------
    */

    $countSql = "

        SELECT
            COUNT(*) AS total

        FROM leads l

        {$baseJoins}

        WHERE {$whereSql}
    ";

    $countStmt =
        $conn->prepare(
            $countSql
        );

    if (!$countStmt) {

        throw new Exception(
            "Failed to prepare lead count query: " .
            $conn->error
        );
    }

    $countParams =
        $params;

    if (!empty($countParams)) {

        $bindParams(
            $countStmt,
            $types,
            $countParams
        );
    }

    if (!$countStmt->execute()) {

        $error =
            $countStmt->error;

        $countStmt->close();

        throw new Exception(
            "Failed to count leads: " .
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
    | TOTAL PAGES
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
    | PAGE OUT OF RANGE
    |--------------------------------------------------------------------------
    */

    if (
        $totalRecords > 0 &&
        $page > $totalPages
    ) {

        http_response_code(200);

        echo json_encode([

            "status" => true,

            "message" =>
                "No leads found for the requested page.",

            "data" => [],

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

            "pagination" => [

                "current_page" =>
                    $page,

                "per_page" =>
                    $limit,

                "total_records" =>
                    $totalRecords,

                "total_pages" =>
                    $totalPages,

                "has_previous_page" =>
                    $page > 1,

                "has_next_page" =>
                    false
            ]
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | MAIN DATA QUERY
    |--------------------------------------------------------------------------
    */

    $dataSql = "

        SELECT

            /*
            |--------------------------------------------------------------------------
            | COMMON LEAD
            |--------------------------------------------------------------------------
            */

            l.id,
            l.lead_reference,
            l.domain,

            l.customer_name,
            l.customer_phone,
            l.customer_email,

            l.event_date,
            l.guest_count,
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
            | ASSIGNED USER
            |--------------------------------------------------------------------------
            */

            au.name AS assigned_admin_name,
            au.email AS assigned_admin_email,
            au.phone AS assigned_admin_phone,
            au.role AS assigned_admin_role,
            au.status AS assigned_admin_status,

            /*
            |--------------------------------------------------------------------------
            | HALL
            |--------------------------------------------------------------------------
            */

            hld.hall_id,

            h.name AS hall_name,
            h.slug AS hall_slug,

            ht.name AS hall_type_name,

            hld.event_type AS hall_event_type,
            hld.event_date AS hall_event_date,
            hld.event_time AS hall_event_time,
            hld.guest_count AS hall_guest_count,

            hld.food_required,
            hld.decoration_required,

            hld.other_requirements
                AS hall_other_requirements,

            /*
            |--------------------------------------------------------------------------
            | DINING
            |--------------------------------------------------------------------------
            */

            dld.dining_package_id,

            dp.name AS dining_package_name,
            dp.meal_type AS dining_package_meal_type,

            dld.meal_type AS dining_meal_type,
            dld.dining_date,
            dld.dining_time,
            dld.guest_count AS dining_guest_count,

            dld.other_requirements
                AS dining_other_requirements,

            /*
            |--------------------------------------------------------------------------
            | CATERING
            |--------------------------------------------------------------------------
            */

            cld.catering_package_id,

            cp.name AS catering_package_name,
            cp.rate AS catering_package_rate,
            cp.rate_unit AS catering_package_rate_unit,
            cp.gst_rate AS catering_package_gst_rate,
            cp.price_includes_gst
                AS catering_package_price_includes_gst,
            cp.minimum_quantity
                AS catering_package_minimum_quantity,

            cld.event_type AS catering_event_type,
            cld.event_date AS catering_event_date,
            cld.event_time AS catering_event_time,
            cld.guest_count AS catering_guest_count,

            cld.venue_address
                AS catering_venue_address,

            cld.estimated_amount
                AS catering_estimated_amount,

            cld.other_requirements
                AS catering_other_requirements

        FROM leads l

        {$baseJoins}

        WHERE {$whereSql}

        ORDER BY
            {$allowedSortFields[$sortBy]}
            {$sortOrder},

            l.id DESC

        LIMIT ?
        OFFSET ?
    ";

    /*
    |--------------------------------------------------------------------------
    | DATA PARAMETERS
    |--------------------------------------------------------------------------
    */

    $dataParams =
        $params;

    $dataTypes =
        $types . 'ii';

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
    | BIND
    |--------------------------------------------------------------------------
    */

    $bindParams(
        $dataStmt,
        $dataTypes,
        $dataParams
    );

    /*
    |--------------------------------------------------------------------------
    | EXECUTE
    |--------------------------------------------------------------------------
    */

    if (!$dataStmt->execute()) {

        $error =
            $dataStmt->error;

        $dataStmt->close();

        throw new Exception(
            "Failed to fetch leads: " .
            $error
        );
    }

    $dataResult =
        $dataStmt->get_result();

    /*
    |--------------------------------------------------------------------------
    | RESPONSE DATA
    |--------------------------------------------------------------------------
    */

    $leads = [];

    while (
        $row =
        $dataResult->fetch_assoc()
    ) {

        /*
        |--------------------------------------------------------------------------
        | DOMAIN DETAILS
        |--------------------------------------------------------------------------
        */

        $domainDetails = [];

        /*
        |--------------------------------------------------------------------------
        | HALL DETAILS
        |--------------------------------------------------------------------------
        */

        if (
            $row['domain'] === 'hall'
        ) {

            $domainDetails = [

                "hall" => [

                    "id" =>
                        $row['hall_id'] !== null
                            ? (int)$row['hall_id']
                            : null,

                    "name" =>
                        $row['hall_name'],

                    "slug" =>
                        $row['hall_slug'],

                    "type" =>
                        $row['hall_type_name']
                ],

                "event" => [

                    "type" =>
                        $row['hall_event_type'],

                    "date" =>
                        $row['hall_event_date']
                            ?? $row['event_date'],

                    "time" =>
                        $row['hall_event_time']
                            ?? $row['preferred_time'],

                    "guest_count" =>
                        $row['hall_guest_count'] !== null
                            ? (int)$row['hall_guest_count']
                            : (
                                $row['guest_count'] !== null
                                    ? (int)$row['guest_count']
                                    : null
                            )
                ],

                "food_required" =>
                    $row['food_required'] !== null
                        ? (bool)$row['food_required']
                        : null,

                "decoration_required" =>
                    $row['decoration_required'] !== null
                        ? (bool)$row['decoration_required']
                        : null,

                "other_requirements" =>
                    $row['hall_other_requirements']
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | DINING DETAILS
        |--------------------------------------------------------------------------
        */

        elseif (
            $row['domain'] === 'dining'
        ) {

            $domainDetails = [

                "dining_package" => [

                    "id" =>
                        $row['dining_package_id'] !== null
                            ? (int)$row['dining_package_id']
                            : null,

                    "name" =>
                        $row['dining_package_name'],

                    "meal_type" =>
                        $row['dining_package_meal_type']
                ],

                "event" => [

                    "meal_type" =>
                        $row['dining_meal_type'],

                    "date" =>
                        $row['dining_date']
                            ?? $row['event_date'],

                    "time" =>
                        $row['dining_time']
                            ?? $row['preferred_time'],

                    "guest_count" =>
                        $row['dining_guest_count'] !== null
                            ? (int)$row['dining_guest_count']
                            : (
                                $row['guest_count'] !== null
                                    ? (int)$row['guest_count']
                                    : null
                            )
                ],

                "other_requirements" =>
                    $row['dining_other_requirements']
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | CATERING DETAILS
        |--------------------------------------------------------------------------
        */

        elseif (
            $row['domain'] === 'catering'
        ) {

            $domainDetails = [

                "catering_package" => [

                    "id" =>
                        $row['catering_package_id'] !== null
                            ? (int)$row['catering_package_id']
                            : null,

                    "name" =>
                        $row['catering_package_name'],

                    "rate" =>
                        $row['catering_package_rate'] !== null
                            ? (float)$row['catering_package_rate']
                            : null,

                    "rate_unit" =>
                        $row['catering_package_rate_unit'],

                    "gst_rate" =>
                        $row['catering_package_gst_rate'] !== null
                            ? (float)$row['catering_package_gst_rate']
                            : null,

                    "price_includes_gst" =>
                        $row['catering_package_price_includes_gst'] !== null
                            ? (bool)$row['catering_package_price_includes_gst']
                            : null,

                    "minimum_quantity" =>
                        $row['catering_package_minimum_quantity'] !== null
                            ? (int)$row['catering_package_minimum_quantity']
                            : null
                ],

                "event" => [

                    "type" =>
                        $row['catering_event_type'],

                    "date" =>
                        $row['catering_event_date']
                            ?? $row['event_date'],

                    "time" =>
                        $row['catering_event_time']
                            ?? $row['preferred_time'],

                    "guest_count" =>
                        $row['catering_guest_count'] !== null
                            ? (int)$row['catering_guest_count']
                            : (
                                $row['guest_count'] !== null
                                    ? (int)$row['guest_count']
                                    : null
                            ),

                    "venue_address" =>
                        $row['catering_venue_address']
                ],

                "estimated_amount" =>
                    $row['catering_estimated_amount'] !== null
                        ? (float)$row['catering_estimated_amount']
                        : null,

                "other_requirements" =>
                    $row['catering_other_requirements']
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | COMMON LEAD RESPONSE
        |--------------------------------------------------------------------------
        */

        $leads[] = [

            "id" =>
                (int)$row['id'],

            "lead_reference" =>
                $row['lead_reference'],

            "domain" =>
                $row['domain'],

            "customer" => [

                "name" =>
                    $row['customer_name'],

                "phone" =>
                    $row['customer_phone'],

                "email" =>
                    $row['customer_email']
            ],

            "event" => [

                "date" =>
                    $row['event_date'],

                "guest_count" =>
                    $row['guest_count'] !== null
                        ? (int)$row['guest_count']
                        : null,

                "preferred_time" =>
                    $row['preferred_time']
            ],

            "domain_details" =>
                $domainDetails,

            "requirement" =>
                $row['requirement'],

            "status" =>
                $row['status'],

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

            "source" =>
                $row['source'],

            "assigned_admin" => [

                "id" =>
                    $row['assigned_admin_id'] !== null
                        ? (int)$row['assigned_admin_id']
                        : null,

                "name" =>
                    $row['assigned_admin_name'],

                "email" =>
                    $row['assigned_admin_email'],

                "phone" =>
                    $row['assigned_admin_phone'],

                "role" =>
                    $row['assigned_admin_role'],

                "status" =>
                    $row['assigned_admin_status']
            ],

            "created_at" =>
                $row['created_at'],

            "updated_at" =>
                $row['updated_at']
        ];
    }

    $dataStmt->close();

    /*
    |--------------------------------------------------------------------------
    | SUCCESS RESPONSE
    |--------------------------------------------------------------------------
    */

    http_response_code(200);

    echo json_encode([

        "status" => true,

        "message" =>
            count($leads) > 0
                ? "Leads fetched successfully."
                : "No leads found.",

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
                    ? "all_leads"
                    : "assigned_leads_only"
        ],

        /*
        |--------------------------------------------------------------------------
        | DATA
        |--------------------------------------------------------------------------
        */

        "data" =>
            $leads,

        /*
        |--------------------------------------------------------------------------
        | FILTERS
        |--------------------------------------------------------------------------
        */

        "filters" => [

            "domain" =>
                $domain,

            "status" =>
                $status,

            "search" =>
                $search,

            "event_date" =>
                $eventDate,

            "from_date" =>
                $fromDate,

            "to_date" =>
                $toDate,

            "min_amount" =>
                $minAmount,

            "max_amount" =>
                $maxAmount,

            "assigned_admin_id" =>
                $effectiveAssignedAdminId,

            "source" =>
                $source,

            "sort_by" =>
                $sortBy,

            "sort_order" =>
                $sortOrder
        ],

        /*
        |--------------------------------------------------------------------------
        | PAGINATION
        |--------------------------------------------------------------------------
        */

        "pagination" => [

            "current_page" =>
                $page,

            "per_page" =>
                $limit,

            "total_records" =>
                $totalRecords,

            "total_pages" =>
                $totalPages,

            "has_previous_page" =>
                $page > 1,

            "has_next_page" =>
                $page < $totalPages
        ]
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([

        "status" => false,

        "message" =>
            "Failed to fetch leads.",

        "error" =>
            $e->getMessage()
    ]);
}