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
    | ADMIN / STAFF AUTHENTICATION
    |--------------------------------------------------------------------------
    */

    $currentUser = requireAdminOrStaff();

    /*
    |--------------------------------------------------------------------------
    | ALLOWED QUERY PARAMETERS
    |--------------------------------------------------------------------------
    */

    $allowedParams = [
        'search',
        'status',
        'meal_type',
        'from_date',
        'to_date',
        'min_guests',
        'max_guests',
        'page',
        'limit',
        'sort_by',
        'sort_order'
    ];

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

    $search = isset($_GET['search'])
        ? trim($_GET['search'])
        : '';

    if (mb_strlen($search) > 150) {

        http_response_code(400);

        echo json_encode([
            "status" => false,
            "message" => "Search cannot exceed 150 characters."
        ]);

        exit;
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

    $status = isset($_GET['status'])
        ? trim($_GET['status'])
        : '';

    if ($status !== '' && !in_array($status, $allowedStatuses, true)) {

        http_response_code(400);

        echo json_encode([
            "status" => false,
            "message" => "Invalid status.",
            "allowed_statuses" => $allowedStatuses
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | MEAL TYPE VALIDATION
    |--------------------------------------------------------------------------
    */

    $allowedMealTypes = [
        'lunch',
        'dinner',
        'breakfast',
        'other'
    ];

    $mealType = isset($_GET['meal_type'])
        ? trim($_GET['meal_type'])
        : '';

    if ($mealType !== '' && !in_array($mealType, $allowedMealTypes, true)) {

        http_response_code(400);

        echo json_encode([
            "status" => false,
            "message" => "Invalid meal_type.",
            "allowed_meal_types" => $allowedMealTypes
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | DATE VALIDATION FUNCTION
    |--------------------------------------------------------------------------
    */

    function validateDateFormat($date)
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        $dateObject = DateTime::createFromFormat('Y-m-d', $date);

        return $dateObject &&
               $dateObject->format('Y-m-d') === $date;
    }

    /*
    |--------------------------------------------------------------------------
    | FROM DATE
    |--------------------------------------------------------------------------
    */

    $fromDate = isset($_GET['from_date'])
        ? trim($_GET['from_date'])
        : '';

    if ($fromDate !== '' && !validateDateFormat($fromDate)) {

        http_response_code(400);

        echo json_encode([
            "status" => false,
            "message" => "Invalid from_date. Expected format: YYYY-MM-DD."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | TO DATE
    |--------------------------------------------------------------------------
    */

    $toDate = isset($_GET['to_date'])
        ? trim($_GET['to_date'])
        : '';

    if ($toDate !== '' && !validateDateFormat($toDate)) {

        http_response_code(400);

        echo json_encode([
            "status" => false,
            "message" => "Invalid to_date. Expected format: YYYY-MM-DD."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | DATE RANGE VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($fromDate !== '' && $toDate !== '') {

        if ($fromDate > $toDate) {

            http_response_code(400);

            echo json_encode([
                "status" => false,
                "message" => "from_date cannot be greater than to_date."
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MIN GUESTS
    |--------------------------------------------------------------------------
    */

    $minGuests = null;

    if (isset($_GET['min_guests']) && $_GET['min_guests'] !== '') {

        $minGuestsRaw = trim($_GET['min_guests']);

        if (
            !ctype_digit($minGuestsRaw) ||
            (int)$minGuestsRaw < 1 ||
            (int)$minGuestsRaw > 10000
        ) {

            http_response_code(400);

            echo json_encode([
                "status" => false,
                "message" => "min_guests must be an integer between 1 and 10000."
            ]);

            exit;
        }

        $minGuests = (int)$minGuestsRaw;
    }

    /*
    |--------------------------------------------------------------------------
    | MAX GUESTS
    |--------------------------------------------------------------------------
    */

    $maxGuests = null;

    if (isset($_GET['max_guests']) && $_GET['max_guests'] !== '') {

        $maxGuestsRaw = trim($_GET['max_guests']);

        if (
            !ctype_digit($maxGuestsRaw) ||
            (int)$maxGuestsRaw < 1 ||
            (int)$maxGuestsRaw > 10000
        ) {

            http_response_code(400);

            echo json_encode([
                "status" => false,
                "message" => "max_guests must be an integer between 1 and 10000."
            ]);

            exit;
        }

        $maxGuests = (int)$maxGuestsRaw;
    }

    /*
    |--------------------------------------------------------------------------
    | GUEST RANGE VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        $minGuests !== null &&
        $maxGuests !== null &&
        $minGuests > $maxGuests
    ) {

        http_response_code(400);

        echo json_encode([
            "status" => false,
            "message" => "min_guests cannot be greater than max_guests."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | PAGE VALIDATION
    |--------------------------------------------------------------------------
    */

    $page = 1;

    if (isset($_GET['page']) && $_GET['page'] !== '') {

        $pageRaw = trim($_GET['page']);

        if (
            !ctype_digit($pageRaw) ||
            (int)$pageRaw < 1
        ) {

            http_response_code(400);

            echo json_encode([
                "status" => false,
                "message" => "Page must be a positive integer."
            ]);

            exit;
        }

        $page = (int)$pageRaw;
    }

    /*
    |--------------------------------------------------------------------------
    | LIMIT VALIDATION
    |--------------------------------------------------------------------------
    */

    $limit = 20;

    if (isset($_GET['limit']) && $_GET['limit'] !== '') {

        $limitRaw = trim($_GET['limit']);

        if (
            !ctype_digit($limitRaw) ||
            (int)$limitRaw < 1 ||
            (int)$limitRaw > 100
        ) {

            http_response_code(400);

            echo json_encode([
                "status" => false,
                "message" => "Limit must be between 1 and 100."
            ]);

            exit;
        }

        $limit = (int)$limitRaw;
    }

    /*
    |--------------------------------------------------------------------------
    | SORT BY VALIDATION
    |--------------------------------------------------------------------------
    */

    $allowedSortColumns = [
        'id' => 'l.id',
        'created_at' => 'l.created_at',
        'updated_at' => 'l.updated_at',
        'event_date' => 'l.event_date',
        'customer_name' => 'l.customer_name',
        'guest_count' => 'l.guest_count',
        'status' => 'l.status'
    ];

    $sortBy = isset($_GET['sort_by'])
        ? trim($_GET['sort_by'])
        : 'created_at';

    if (!array_key_exists($sortBy, $allowedSortColumns)) {

        http_response_code(400);

        echo json_encode([
            "status" => false,
            "message" => "Invalid sort_by.",
            "allowed_sort_by" => array_keys($allowedSortColumns)
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | SORT ORDER VALIDATION
    |--------------------------------------------------------------------------
    */

    $sortOrder = isset($_GET['sort_order'])
        ? strtoupper(trim($_GET['sort_order']))
        : 'DESC';

    if (!in_array($sortOrder, ['ASC', 'DESC'], true)) {

        http_response_code(400);

        echo json_encode([
            "status" => false,
            "message" => "sort_order must be ASC or DESC."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | BUILD WHERE CONDITIONS
    |--------------------------------------------------------------------------
    */

    $where = [];
    $params = [];
    $types = '';

    /*
    |--------------------------------------------------------------------------
    | ONLY DINING LEADS
    |--------------------------------------------------------------------------
    */

    $where[] = "l.domain = 'dining'";

    /*
    |--------------------------------------------------------------------------
    | SEARCH
    |--------------------------------------------------------------------------
    */

    if ($search !== '') {

        $where[] = "(
            l.lead_reference LIKE ?
            OR l.customer_name LIKE ?
            OR l.customer_phone LIKE ?
            OR l.customer_email LIKE ?
        )";

        $searchValue = '%' . $search . '%';

        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;

        $types .= 'ssss';
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS
    |--------------------------------------------------------------------------
    */

    if ($status !== '') {

        $where[] = "l.status = ?";

        $params[] = $status;
        $types .= 's';
    }

    /*
    |--------------------------------------------------------------------------
    | MEAL TYPE
    |--------------------------------------------------------------------------
    */

    if ($mealType !== '') {

        $where[] = "dld.meal_type = ?";

        $params[] = $mealType;
        $types .= 's';
    }

    /*
    |--------------------------------------------------------------------------
    | FROM DATE
    |--------------------------------------------------------------------------
    */

    if ($fromDate !== '') {

        $where[] = "dld.dining_date >= ?";

        $params[] = $fromDate;
        $types .= 's';
    }

    /*
    |--------------------------------------------------------------------------
    | TO DATE
    |--------------------------------------------------------------------------
    */

    if ($toDate !== '') {

        $where[] = "dld.dining_date <= ?";

        $params[] = $toDate;
        $types .= 's';
    }

    /*
    |--------------------------------------------------------------------------
    | MIN GUESTS
    |--------------------------------------------------------------------------
    */

    if ($minGuests !== null) {

        $where[] = "dld.guest_count >= ?";

        $params[] = $minGuests;
        $types .= 'i';
    }

    /*
    |--------------------------------------------------------------------------
    | MAX GUESTS
    |--------------------------------------------------------------------------
    */

    if ($maxGuests !== null) {

        $where[] = "dld.guest_count <= ?";

        $params[] = $maxGuests;
        $types .= 'i';
    }

    $whereSql = implode(" AND ", $where);

    /*
    |--------------------------------------------------------------------------
    | DATABASE CONNECTION
    |--------------------------------------------------------------------------
    */

    global $conn;

    if (!isset($conn) || !($conn instanceof mysqli)) {

        throw new Exception("Database connection is not available.");
    }

    /*
    |--------------------------------------------------------------------------
    | TOTAL COUNT
    |--------------------------------------------------------------------------
    */

    $countSql = "
        SELECT COUNT(DISTINCT l.id) AS total
        FROM leads l

        LEFT JOIN dining_lead_details dld
            ON dld.lead_id = l.id

        WHERE {$whereSql}
    ";

    $countStmt = $conn->prepare($countSql);

    if (!$countStmt) {
        throw new Exception("Failed to prepare count query.");
    }

    if (!empty($params)) {

        $countStmt->bind_param(
            $types,
            ...$params
        );
    }

    if (!$countStmt->execute()) {

        $countStmt->close();

        throw new Exception("Failed to execute count query.");
    }

    $countResult = $countStmt->get_result();
    $countRow = $countResult->fetch_assoc();

    $totalRecords = (int)($countRow['total'] ?? 0);

    $countStmt->close();

    /*
    |--------------------------------------------------------------------------
    | PAGINATION CALCULATION
    |--------------------------------------------------------------------------
    */

    $totalPages = $totalRecords > 0
        ? (int)ceil($totalRecords / $limit)
        : 0;

    $offset = ($page - 1) * $limit;

    /*
    |--------------------------------------------------------------------------
    | PREVENT PAGE BEYOND AVAILABLE DATA
    |--------------------------------------------------------------------------
    */

    if ($totalPages > 0 && $page > $totalPages) {

        http_response_code(200);

        echo json_encode([
            "status" => true,
            "message" => "No records found for the requested page.",
            "data" => [],
            "pagination" => [
                "current_page" => $page,
                "per_page" => $limit,
                "total_records" => $totalRecords,
                "total_pages" => $totalPages
            ]
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | MAIN QUERY
    |--------------------------------------------------------------------------
    */

    $sortColumn = $allowedSortColumns[$sortBy];

    $sql = "
        SELECT
            l.id,
            l.lead_reference,
            l.domain,
            l.customer_name,
            l.customer_phone,
            l.customer_email,
            l.event_date,
            l.guest_count AS lead_guest_count,
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

            dld.dining_package_id,
            dp.name AS dining_package_name,
            dld.meal_type,
            dld.dining_date,
            dld.dining_time,
            dld.guest_count AS dining_guest_count,
            dld.other_requirements,

            COUNT(DISTINCT dli.id) AS custom_food_item_count

        FROM leads l

        LEFT JOIN dining_lead_details dld
            ON dld.lead_id = l.id

        LEFT JOIN dining_packages dp
            ON dp.id = dld.dining_package_id

        LEFT JOIN dining_lead_items dli
            ON dli.lead_id = l.id

        WHERE {$whereSql}

        GROUP BY
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

            dld.dining_package_id,
            dp.name,
            dld.meal_type,
            dld.dining_date,
            dld.dining_time,
            dld.guest_count,
            dld.other_requirements

        ORDER BY {$sortColumn} {$sortOrder}

        LIMIT ? OFFSET ?
    ";

    $mainParams = $params;
    $mainTypes = $types . 'ii';

    $mainParams[] = $limit;
    $mainParams[] = $offset;

    $stmt = $conn->prepare($sql);

    if (!$stmt) {

        throw new Exception("Failed to prepare list query.");
    }

    $stmt->bind_param(
        $mainTypes,
        ...$mainParams
    );

    if (!$stmt->execute()) {

        $stmt->close();

        throw new Exception("Failed to execute list query.");
    }

    $result = $stmt->get_result();

    $leads = [];

    while ($row = $result->fetch_assoc()) {

        $leads[] = [
            "id" => (int)$row['id'],
            "lead_reference" => $row['lead_reference'],
            "domain" => $row['domain'],

            "customer" => [
                "name" => $row['customer_name'],
                "phone" => $row['customer_phone'],
                "email" => $row['customer_email']
            ],

            "event" => [
                "date" => $row['dining_date'],
                "time" => $row['dining_time'],
                "guest_count" => $row['dining_guest_count'] !== null
                    ? (int)$row['dining_guest_count']
                    : null
            ],

            "dining" => [
                "meal_type" => $row['meal_type'],
                "package" => $row['dining_package_id'] !== null
                    ? [
                        "id" => (int)$row['dining_package_id'],
                        "name" => $row['dining_package_name']
                    ]
                    : null,

                "other_requirements" => $row['other_requirements']
            ],

            "custom_food_item_count" => (int)$row['custom_food_item_count'],

            "lead" => [
                "status" => $row['status'],
                "estimated_amount" => $row['estimated_amount'] !== null
                    ? (float)$row['estimated_amount']
                    : null,

                "converted_amount" => $row['converted_amount'] !== null
                    ? (float)$row['converted_amount']
                    : null,

                "converted_at" => $row['converted_at'],
                "source" => $row['source'],
                "assigned_admin_id" => $row['assigned_admin_id'] !== null
                    ? (int)$row['assigned_admin_id']
                    : null
            ],

            "created_at" => $row['created_at'],
            "updated_at" => $row['updated_at']
        ];
    }

    $stmt->close();

    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    http_response_code(200);

    echo json_encode([
        "status" => true,
        "message" => "Dining leads fetched successfully.",
        "data" => $leads,

        "filters" => [
            "search" => $search !== '' ? $search : null,
            "status" => $status !== '' ? $status : null,
            "meal_type" => $mealType !== '' ? $mealType : null,
            "from_date" => $fromDate !== '' ? $fromDate : null,
            "to_date" => $toDate !== '' ? $toDate : null,
            "min_guests" => $minGuests,
            "max_guests" => $maxGuests
        ],

        "pagination" => [
            "current_page" => $page,
            "per_page" => $limit,
            "total_records" => $totalRecords,
            "total_pages" => $totalPages
        ],

        "sorting" => [
            "sort_by" => $sortBy,
            "sort_order" => $sortOrder
        ]
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        "status" => false,
        "message" => "Failed to fetch dining leads.",
        "error" => $e->getMessage()
    ]);

}