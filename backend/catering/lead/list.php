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
    | Only Admin / Staff can list catering leads
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
    | ALLOWED QUERY PARAMETERS
    |--------------------------------------------------------------------------
    */

    $allowedParams = [
        'search',
        'status',
        'event_date',
        'from_date',
        'to_date',
        'min_amount',
        'max_amount',
        'page',
        'limit',
        'sort_by',
        'sort_order'
    ];

    /*
    |--------------------------------------------------------------------------
    | UNKNOWN PARAMETER VALIDATION
    |--------------------------------------------------------------------------
    */

    foreach ($_GET as $key => $value) {

        if (!in_array($key, $allowedParams, true)) {

            http_response_code(400);

            echo json_encode([
                "status" => false,
                "message" => "Unknown query parameter: {$key}"
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
        isset($_GET['search']) &&
        trim((string)$_GET['search']) !== ''
    ) {

        $search =
            trim((string)$_GET['search']);

        if (mb_strlen($search) > 150) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "search cannot exceed 150 characters."
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

    if (
        isset($_GET['status']) &&
        trim((string)$_GET['status']) !== ''
    ) {

        $status =
            trim((string)$_GET['status']);

        if (!in_array($status, $allowedStatuses, true)) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "Invalid status.",
                "allowed_statuses" => $allowedStatuses
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DATE VALIDATION FUNCTION
    |--------------------------------------------------------------------------
    */

    function validateDateParameter(
        string $value,
        string $fieldName
    ): string {

        $value = trim($value);

        if (!preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $value
        )) {

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
            validateDateParameter(
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
            validateDateParameter(
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
            validateDateParameter(
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
            "message" => "from_date cannot be greater than to_date."
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
            trim((string)$_GET['min_amount']);

        if (!preg_match(
            '/^\d{1,10}(\.\d{1,2})?$/',
            $minAmountRaw
        )) {

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
            trim((string)$_GET['max_amount']);

        if (!preg_match(
            '/^\d{1,10}(\.\d{1,2})?$/',
            $maxAmountRaw
        )) {

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
    | AMOUNT RANGE VALIDATION
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
            "message" => "min_amount cannot be greater than max_amount."
        ]);

        exit;
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
            trim((string)$_GET['page']);

        if (
            !ctype_digit($pageRaw) ||
            (int)$pageRaw < 1
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "page must be a positive integer."
            ]);

            exit;
        }

        $page =
            (int)$pageRaw;
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
            trim((string)$_GET['limit']);

        if (
            !ctype_digit($limitRaw) ||
            (int)$limitRaw < 1
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "limit must be a positive integer."
            ]);

            exit;
        }

        $limit =
            (int)$limitRaw;

        if ($limit > 100) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "limit cannot exceed 100."
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
    | SORT BY
    |--------------------------------------------------------------------------
    */

    $allowedSortFields = [
        'id' => 'l.id',
        'lead_reference' => 'l.lead_reference',
        'customer_name' => 'l.customer_name',
        'event_date' => 'l.event_date',
        'guest_count' => 'l.guest_count',
        'estimated_amount' => 'l.estimated_amount',
        'converted_amount' => 'l.converted_amount',
        'status' => 'l.status',
        'created_at' => 'l.created_at',
        'updated_at' => 'l.updated_at'
    ];

    $sortBy = 'created_at';

    if (
        isset($_GET['sort_by']) &&
        trim((string)$_GET['sort_by']) !== ''
    ) {

        $sortByRaw =
            trim((string)$_GET['sort_by']);

        if (!array_key_exists(
            $sortByRaw,
            $allowedSortFields
        )) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "Invalid sort_by.",
                "allowed_sort_by" =>
                    array_keys($allowedSortFields)
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

    $sortOrder = 'DESC';

    if (
        isset($_GET['sort_order']) &&
        trim((string)$_GET['sort_order']) !== ''
    ) {

        $sortOrderRaw =
            strtoupper(
                trim((string)$_GET['sort_order'])
            );

        if (!in_array(
            $sortOrderRaw,
            ['ASC', 'DESC'],
            true
        )) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "sort_order must be ASC or DESC."
            ]);

            exit;
        }

        $sortOrder =
            $sortOrderRaw;
    }

    /*
    |--------------------------------------------------------------------------
    | BUILD WHERE CONDITIONS
    |--------------------------------------------------------------------------
    */

    $where = [
        "l.domain = 'catering'"
    ];

    $params = [];
    $types = '';

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
            )
        ";

        $searchValue =
            '%' . $search . '%';

        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;

        $types .= 'sssss';
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
    | EXACT EVENT DATE
    |--------------------------------------------------------------------------
    */

    if ($eventDate !== null) {

        $where[] =
            "l.event_date = ?";

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
            "l.event_date >= ?";

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
            "l.event_date <= ?";

        $params[] =
            $toDate;

        $types .= 's';
    }

    /*
    |--------------------------------------------------------------------------
    | MIN AMOUNT
    |--------------------------------------------------------------------------
    */

    if ($minAmount !== null) {

        $where[] =
            "l.estimated_amount >= ?";

        $params[] =
            $minAmount;

        $types .= 'd';
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

        $types .= 'd';
    }

    $whereSql =
        implode(
            ' AND ',
            $where
        );

    /*
    |--------------------------------------------------------------------------
    | COUNT TOTAL RECORDS
    |--------------------------------------------------------------------------
    */

    $countSql = "
        SELECT COUNT(*) AS total
        FROM leads l
        WHERE {$whereSql}
    ";

    $countStmt =
        $conn->prepare($countSql);

    if (!$countStmt) {

        throw new Exception(
            "Failed to prepare total count query: " .
            $conn->error
        );
    }

    if (!empty($params)) {

        $countStmt->bind_param(
            $types,
            ...$params
        );
    }

    if (!$countStmt->execute()) {

        $error =
            $countStmt->error;

        $countStmt->close();

        throw new Exception(
            "Failed to count catering leads: " .
            $error
        );
    }

    $countResult =
        $countStmt->get_result();

    $countRow =
        $countResult->fetch_assoc();

    $countStmt->close();

    $totalRecords =
        (int)$countRow['total'];

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
            "message" => "No catering leads found for the requested page.",
            "data" => [],
            "pagination" => [
                "current_page" => $page,
                "per_page" => $limit,
                "total_records" => $totalRecords,
                "total_pages" => $totalPages,
                "has_previous_page" => $page > 1,
                "has_next_page" => false
            ]
        ]);

        exit;
    }

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

            au.name AS assigned_admin_name,
            au.email AS assigned_admin_email,
            au.phone AS assigned_admin_phone,

            cld.catering_package_id,
            cld.event_type,
            cld.event_date AS catering_event_date,
            cld.event_time,
            cld.guest_count AS catering_guest_count,
            cld.venue_address,
            cld.estimated_amount AS catering_estimated_amount,
            cld.other_requirements AS catering_other_requirements,

            cp.name AS catering_package_name,
            cp.rate AS catering_package_rate,
            cp.rate_unit AS catering_package_rate_unit,
            cp.gst_rate AS catering_package_gst_rate,
            cp.price_includes_gst AS catering_package_price_includes_gst,
            cp.minimum_quantity AS catering_package_minimum_quantity,
            cp.status AS catering_package_status

        FROM leads l

        LEFT JOIN admin_users au
            ON au.id = l.assigned_admin_id

        LEFT JOIN catering_lead_details cld
            ON cld.lead_id = l.id

        LEFT JOIN catering_packages cp
            ON cp.id = cld.catering_package_id

        WHERE {$whereSql}

        ORDER BY {$allowedSortFields[$sortBy]} {$sortOrder}

        LIMIT ? OFFSET ?
    ";

    /*
    |--------------------------------------------------------------------------
    | ADD PAGINATION PARAMETERS
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
        $conn->prepare($dataSql);

    if (!$dataStmt) {

        throw new Exception(
            "Failed to prepare catering lead list query: " .
            $conn->error
        );
    }

    /*
    |--------------------------------------------------------------------------
    | BIND DATA PARAMETERS
    |--------------------------------------------------------------------------
    */

    $dataStmt->bind_param(
        $dataTypes,
        ...$dataParams
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
            "Failed to fetch catering leads: " .
            $error
        );
    }

    $dataResult =
        $dataStmt->get_result();

    /*
    |--------------------------------------------------------------------------
    | FORMAT RESPONSE
    |--------------------------------------------------------------------------
    */

    $leads = [];

    while (
        $row =
        $dataResult->fetch_assoc()
    ) {

        $leads[] = [

            /*
            |--------------------------------------------------------------------------
            | BASIC LEAD
            |--------------------------------------------------------------------------
            */

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
            | EVENT
            |--------------------------------------------------------------------------
            */

            "event" => [

                "type" =>
                    $row['event_type'],

                "date" =>
                    $row['catering_event_date'] !== null
                        ? $row['catering_event_date']
                        : $row['event_date'],

                "time" =>
                    $row['event_time'] !== null
                        ? $row['event_time']
                        : $row['preferred_time'],

                "guest_count" =>
                    $row['catering_guest_count'] !== null
                        ? (int)$row['catering_guest_count']
                        : (
                            $row['guest_count'] !== null
                                ? (int)$row['guest_count']
                                : null
                        ),

                "venue_address" =>
                    $row['venue_address']
            ],

            /*
            |--------------------------------------------------------------------------
            | CATERING PACKAGE
            |--------------------------------------------------------------------------
            */

            "catering_package" =>

                $row['catering_package_id'] !== null
                    ? [

                        "id" =>
                            (int)$row['catering_package_id'],

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
                            (bool)$row['catering_package_price_includes_gst'],

                        "minimum_quantity" =>
                            $row['catering_package_minimum_quantity'] !== null
                                ? (int)$row['catering_package_minimum_quantity']
                                : null,

                        "status" =>
                            $row['catering_package_status']
                    ]
                    : null,

            /*
            |--------------------------------------------------------------------------
            | REQUIREMENTS
            |--------------------------------------------------------------------------
            */

            "requirements" => [

                "lead_requirement" =>
                    $row['requirement'],

                "catering_requirement" =>
                    $row['catering_other_requirements'],

                "venue_address" =>
                    $row['venue_address']
            ],

            /*
            |--------------------------------------------------------------------------
            | STATUS / AMOUNT
            |--------------------------------------------------------------------------
            */

            "status" =>
                $row['status'],

            "estimated_amount" =>
                $row['estimated_amount'] !== null
                    ? (float)$row['estimated_amount']
                    : null,

            "catering_estimated_amount" =>
                $row['catering_estimated_amount'] !== null
                    ? (float)$row['catering_estimated_amount']
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
            | ASSIGNED ADMIN
            |--------------------------------------------------------------------------
            */

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
                    $row['assigned_admin_phone']
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
    | SUCCESS RESPONSE
    |--------------------------------------------------------------------------
    */

    http_response_code(200);

    echo json_encode([

        "status" => true,

        "message" =>
            count($leads) > 0
                ? "Catering leads fetched successfully."
                : "No catering leads found.",

        "data" =>
            $leads,

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

            "min_amount" =>
                $minAmount,

            "max_amount" =>
                $maxAmount,

            "sort_by" =>
                $sortBy,

            "sort_order" =>
                $sortOrder
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
                $page < $totalPages
        ]
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        "status" => false,
        "message" => "Failed to fetch catering leads.",
        "error" => $e->getMessage()
    ]);
}