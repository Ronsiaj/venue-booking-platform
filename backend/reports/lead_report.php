<?php

declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");
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
        'status' => false,
        'message' => 'Only GET method is allowed.'
    ]);

    exit;
}


require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../config/middleware.php';


/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function jsonResponse(
    bool $status,
    string $message,
    array $data = [],
    int $httpCode = 200
): void {

    http_response_code($httpCode);

    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


function getIntParam(
    string $key,
    int $default = 0,
    int $min = 0,
    int $max = PHP_INT_MAX
): int {

    if (
        !isset($_GET[$key]) ||
        $_GET[$key] === ''
    ) {
        return $default;
    }

    $value = filter_var(
        $_GET[$key],
        FILTER_VALIDATE_INT
    );

    if ($value === false) {

        jsonResponse(
            false,
            "Invalid {$key}. It must be an integer.",
            [],
            422
        );
    }

    if (
        $value < $min ||
        $value > $max
    ) {

        jsonResponse(
            false,
            "Invalid {$key}. Allowed range: {$min} to {$max}.",
            [],
            422
        );
    }

    return (int)$value;
}


function getFloatParam(
    string $key,
    ?float $default = null,
    float $min = 0,
    float $max = 999999999999
): ?float {

    if (
        !isset($_GET[$key]) ||
        $_GET[$key] === ''
    ) {
        return $default;
    }

    $raw = trim(
        (string)$_GET[$key]
    );

    if (
        !preg_match(
            '/^\d+(\.\d{1,2})?$/',
            $raw
        )
    ) {

        jsonResponse(
            false,
            "{$key} must be a valid amount with maximum 2 decimal places.",
            [],
            422
        );
    }

    $value = (float)$raw;

    if (
        $value < $min ||
        $value > $max
    ) {

        jsonResponse(
            false,
            "Invalid {$key}. Allowed range: {$min} to {$max}.",
            [],
            422
        );
    }

    return $value;
}


function getStringParam(
    string $key,
    ?string $default = null,
    int $maxLength = 255
): ?string {

    if (!isset($_GET[$key])) {
        return $default;
    }

    $value = trim(
        (string)$_GET[$key]
    );

    if ($value === '') {
        return $default;
    }

    if (
        mb_strlen($value) > $maxLength
    ) {

        jsonResponse(
            false,
            "{$key} cannot exceed {$maxLength} characters.",
            [],
            422
        );
    }

    return $value;
}


function getDateParam(
    string $key
): ?string {

    if (
        !isset($_GET[$key]) ||
        trim((string)$_GET[$key]) === ''
    ) {
        return null;
    }

    $value = trim(
        (string)$_GET[$key]
    );

    $date = DateTime::createFromFormat(
        'Y-m-d',
        $value
    );

    if (
        !$date ||
        $date->format('Y-m-d') !== $value
    ) {

        jsonResponse(
            false,
            "Invalid {$key}. Expected YYYY-MM-DD.",
            [],
            422
        );
    }

    return $value;
}


function validateDateRange(
    ?string $from,
    ?string $to,
    string $fromName,
    string $toName
): void {

    if (
        $from !== null &&
        $to !== null &&
        $from > $to
    ) {

        jsonResponse(
            false,
            "{$fromName} cannot be greater than {$toName}.",
            [],
            422
        );
    }
}


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

$currentUser = requireAdminOrStaff();

$currentUserRole = strtolower(
    trim(
        (string)($currentUser['role'] ?? '')
    )
);

$currentUserId =
    $currentUser['sub']
    ?? $currentUser['id']
    ?? null;


if (!in_array(
    $currentUserRole,
    ['admin', 'staff'],
    true
)) {

    jsonResponse(
        false,
        'Only admin or staff users can access lead reports.',
        [],
        403
    );
}


if (
    $currentUserId === null ||
    !is_numeric($currentUserId)
) {

    jsonResponse(
        false,
        'Invalid authenticated user.',
        [],
        401
    );
}

$currentUserId = (int)$currentUserId;


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$leadId = getIntParam(
    'lead_id',
    0,
    0
);

$assignedAdminId = getIntParam(
    'assigned_admin_id',
    0,
    0
);

$minGuests = getIntParam(
    'min_guests',
    0,
    0,
    1000000
);

$maxGuests = getIntParam(
    'max_guests',
    0,
    0,
    1000000
);

$page = getIntParam(
    'page',
    1,
    1,
    1000000
);

$limit = getIntParam(
    'limit',
    20,
    1,
    100
);


$domain = getStringParam(
    'domain',
    'all',
    30
);

$status = getStringParam(
    'status',
    'all',
    30
);

$source = getStringParam(
    'source',
    'all',
    30
);

$search = getStringParam(
    'search',
    null,
    150
);

$fromDate = getDateParam(
    'from_date'
);

$toDate = getDateParam(
    'to_date'
);

$eventDateFrom = getDateParam(
    'event_date_from'
);

$eventDateTo = getDateParam(
    'event_date_to'
);

$minAmount = getFloatParam(
    'min_amount',
    null
);

$maxAmount = getFloatParam(
    'max_amount',
    null
);

$sortBy = getStringParam(
    'sort_by',
    'created_at',
    50
);

$sortOrder = strtolower(
    getStringParam(
        'sort_order',
        'desc',
        10
    )
);


/*
|--------------------------------------------------------------------------
| Allowed Values
|--------------------------------------------------------------------------
*/

$allowedDomains = [
    'all',
    'hall',
    'dining',
    'catering'
];

$allowedStatuses = [
    'all',
    'new',
    'contacted',
    'follow_up',
    'converted',
    'lost',
    'cancelled'
];

$allowedSources = [
    'all',
    'website',
    'whatsapp',
    'phone',
    'admin',
    'other'
];


if (!in_array(
    $domain,
    $allowedDomains,
    true
)) {

    jsonResponse(
        false,
        'Invalid domain.',
        [
            'allowed' =>
                $allowedDomains
        ],
        422
    );
}


if (!in_array(
    $status,
    $allowedStatuses,
    true
)) {

    jsonResponse(
        false,
        'Invalid status.',
        [
            'allowed' =>
                $allowedStatuses
        ],
        422
    );
}


if (!in_array(
    $source,
    $allowedSources,
    true
)) {

    jsonResponse(
        false,
        'Invalid source.',
        [
            'allowed' =>
                $allowedSources
        ],
        422
    );
}


if (!in_array(
    $sortOrder,
    ['asc', 'desc'],
    true
)) {

    jsonResponse(
        false,
        'Invalid sort_order. Allowed: asc, desc.',
        [],
        422
    );
}


$allowedSortColumns = [

    'created_at' =>
        'l.created_at',

    'updated_at' =>
        'l.updated_at',

    'event_date' =>
        'l.event_date',

    'customer_name' =>
        'l.customer_name',

    'lead_reference' =>
        'l.lead_reference',

    'guest_count' =>
        'l.guest_count',

    'estimated_amount' =>
        'l.estimated_amount',

    'converted_amount' =>
        'l.converted_amount',

    'converted_at' =>
        'l.converted_at',

    'status' =>
        'l.status',

    'domain' =>
        'l.domain',

    'source' =>
        'l.source'
];


if (!array_key_exists(
    $sortBy,
    $allowedSortColumns
)) {

    jsonResponse(
        false,
        'Invalid sort_by.',
        [
            'allowed' =>
                array_keys(
                    $allowedSortColumns
                )
        ],
        422
    );
}


$sortColumn =
    $allowedSortColumns[$sortBy];


validateDateRange(
    $fromDate,
    $toDate,
    'from_date',
    'to_date'
);

validateDateRange(
    $eventDateFrom,
    $eventDateTo,
    'event_date_from',
    'event_date_to'
);


if (
    $minGuests > 0 &&
    $maxGuests > 0 &&
    $minGuests > $maxGuests
) {

    jsonResponse(
        false,
        'min_guests cannot be greater than max_guests.',
        [],
        422
    );
}


if (
    $minAmount !== null &&
    $maxAmount !== null &&
    $minAmount > $maxAmount
) {

    jsonResponse(
        false,
        'min_amount cannot be greater than max_amount.',
        [],
        422
    );
}


/*
|--------------------------------------------------------------------------
| Validate Lead ID
|--------------------------------------------------------------------------
*/

if ($leadId > 0) {

    $stmt = $conn->prepare("
        SELECT id
        FROM leads
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {

        jsonResponse(
            false,
            'Failed to validate lead.',
            [],
            500
        );
    }

    $stmt->bind_param(
        'i',
        $leadId
    );

    $stmt->execute();

    $result =
        $stmt->get_result();

    if ($result->num_rows === 0) {

        $stmt->close();

        jsonResponse(
            false,
            'Lead not found.',
            [],
            404
        );
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Validate Assigned Admin
|--------------------------------------------------------------------------
*/

if ($assignedAdminId > 0) {

    /*
     * Staff cannot use another admin's ID
     * to bypass their own scope.
     */

    if (
        $currentUserRole === 'staff' &&
        $assignedAdminId !== $currentUserId
    ) {

        jsonResponse(
            false,
            'Staff users can only view their own assigned leads.',
            [],
            403
        );
    }


    $stmt = $conn->prepare("
        SELECT id
        FROM admin_users
        WHERE id = ?
          AND status = 'active'
        LIMIT 1
    ");

    if (!$stmt) {

        jsonResponse(
            false,
            'Failed to validate assigned admin.',
            [],
            500
        );
    }

    $stmt->bind_param(
        'i',
        $assignedAdminId
    );

    $stmt->execute();

    $result =
        $stmt->get_result();

    if ($result->num_rows === 0) {

        $stmt->close();

        jsonResponse(
            false,
            'Assigned admin not found or inactive.',
            [],
            404
        );
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Base WHERE
|--------------------------------------------------------------------------
*/

$where = [];

$params = [];

$types = '';


/*
|--------------------------------------------------------------------------
| Staff Scope
|--------------------------------------------------------------------------
*/

if ($currentUserRole === 'staff') {

    $where[] =
        "l.assigned_admin_id = ?";

    $params[] =
        $currentUserId;

    $types .= 'i';
}


/*
|--------------------------------------------------------------------------
| Lead ID
|--------------------------------------------------------------------------
*/

if ($leadId > 0) {

    $where[] =
        "l.id = ?";

    $params[] =
        $leadId;

    $types .= 'i';
}


/*
|--------------------------------------------------------------------------
| Domain
|--------------------------------------------------------------------------
*/

if ($domain !== 'all') {

    $where[] =
        "l.domain = ?";

    $params[] =
        $domain;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
*/

if ($status !== 'all') {

    $where[] =
        "l.status = ?";

    $params[] =
        $status;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Source
|--------------------------------------------------------------------------
*/

if ($source !== 'all') {

    $where[] =
        "l.source = ?";

    $params[] =
        $source;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Assigned Admin
|--------------------------------------------------------------------------
*/

if ($assignedAdminId > 0) {

    $where[] =
        "l.assigned_admin_id = ?";

    $params[] =
        $assignedAdminId;

    $types .= 'i';
}


/*
|--------------------------------------------------------------------------
| Lead Created Date
|--------------------------------------------------------------------------
*/

if ($fromDate !== null) {

    $where[] =
        "DATE(l.created_at) >= ?";

    $params[] =
        $fromDate;

    $types .= 's';
}


if ($toDate !== null) {

    $where[] =
        "DATE(l.created_at) <= ?";

    $params[] =
        $toDate;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Event Date
|--------------------------------------------------------------------------
*/

if ($eventDateFrom !== null) {

    $where[] =
        "l.event_date >= ?";

    $params[] =
        $eventDateFrom;

    $types .= 's';
}


if ($eventDateTo !== null) {

    $where[] =
        "l.event_date <= ?";

    $params[] =
        $eventDateTo;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Guest Count
|--------------------------------------------------------------------------
*/

if ($minGuests > 0) {

    $where[] =
        "COALESCE(l.guest_count, 0) >= ?";

    $params[] =
        $minGuests;

    $types .= 'i';
}


if ($maxGuests > 0) {

    $where[] =
        "COALESCE(l.guest_count, 0) <= ?";

    $params[] =
        $maxGuests;

    $types .= 'i';
}


/*
|--------------------------------------------------------------------------
| Amount
|--------------------------------------------------------------------------
*/

if ($minAmount !== null) {

    $where[] = "
        GREATEST(
            COALESCE(l.estimated_amount, 0),
            COALESCE(l.converted_amount, 0)
        ) >= ?
    ";

    $params[] =
        $minAmount;

    $types .= 'd';
}


if ($maxAmount !== null) {

    $where[] = "
        GREATEST(
            COALESCE(l.estimated_amount, 0),
            COALESCE(l.converted_amount, 0)
        ) <= ?
    ";

    $params[] =
        $maxAmount;

    $types .= 'd';
}


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== null) {

    $like =
        '%' . $search . '%';

    $where[] = "
        (
            l.lead_reference LIKE ?
            OR l.customer_name LIKE ?
            OR l.customer_phone LIKE ?
            OR l.customer_email LIKE ?
            OR l.requirement LIKE ?
        )
    ";

    for ($i = 0; $i < 5; $i++) {

        $params[] =
            $like;

        $types .= 's';
    }
}


$whereSql =
    !empty($where)
        ? implode(
            ' AND ',
            $where
        )
        : '1=1';


/*
|--------------------------------------------------------------------------
| Overall KPI Summary
|--------------------------------------------------------------------------
*/

$summarySql = "
    SELECT

        COUNT(*) AS total_leads,

        COUNT(
            CASE
                WHEN l.status = 'new'
                THEN 1
            END
        ) AS new_leads,

        COUNT(
            CASE
                WHEN l.status = 'contacted'
                THEN 1
            END
        ) AS contacted_leads,

        COUNT(
            CASE
                WHEN l.status = 'follow_up'
                THEN 1
            END
        ) AS follow_up_leads,

        COUNT(
            CASE
                WHEN l.status = 'converted'
                THEN 1
            END
        ) AS converted_leads,

        COUNT(
            CASE
                WHEN l.status = 'lost'
                THEN 1
            END
        ) AS lost_leads,

        COUNT(
            CASE
                WHEN l.status = 'cancelled'
                THEN 1
            END
        ) AS cancelled_leads,

        COALESCE(
            SUM(
                CASE
                    WHEN l.status = 'converted'
                    THEN COALESCE(
                        l.converted_amount,
                        0
                    )
                    ELSE 0
                END
            ),
            0
        ) AS converted_revenue,

        COALESCE(
            SUM(
                CASE
                    WHEN l.status IN (
                        'new',
                        'contacted',
                        'follow_up'
                    )
                    THEN COALESCE(
                        l.estimated_amount,
                        0
                    )
                    ELSE 0
                END
            ),
            0
        ) AS pending_estimated_revenue,

        COALESCE(
            SUM(
                COALESCE(
                    l.estimated_amount,
                    0
                )
            ),
            0
        ) AS total_estimated_value,

        COALESCE(
            SUM(
                COALESCE(
                    l.guest_count,
                    0
                )
            ),
            0
        ) AS total_guests,

        COALESCE(
            AVG(
                CASE
                    WHEN l.guest_count IS NOT NULL
                    THEN l.guest_count
                END
            ),
            0
        ) AS average_guests

    FROM leads l

    WHERE {$whereSql}
";


$stmt = $conn->prepare(
    $summarySql
);

if (!$stmt) {

    jsonResponse(
        false,
        'Failed to prepare lead summary.',
        [],
        500
    );
}


if (!empty($params)) {

    $stmt->bind_param(
        $types,
        ...$params
    );
}


$stmt->execute();

$summary =
    $stmt->get_result()
        ->fetch_assoc();

$stmt->close();


$totalLeads =
    (int)($summary['total_leads'] ?? 0);

$newLeads =
    (int)($summary['new_leads'] ?? 0);

$contactedLeads =
    (int)($summary['contacted_leads'] ?? 0);

$followUpLeads =
    (int)($summary['follow_up_leads'] ?? 0);

$convertedLeads =
    (int)($summary['converted_leads'] ?? 0);

$lostLeads =
    (int)($summary['lost_leads'] ?? 0);

$cancelledLeads =
    (int)($summary['cancelled_leads'] ?? 0);

$convertedRevenue =
    (float)($summary['converted_revenue'] ?? 0);

$pendingEstimatedRevenue =
    (float)(
        $summary[
            'pending_estimated_revenue'
        ] ?? 0
    );

$totalEstimatedValue =
    (float)(
        $summary[
            'total_estimated_value'
        ] ?? 0
    );

$totalGuests =
    (int)(
        $summary['total_guests'] ?? 0
    );

$averageGuests =
    (float)(
        $summary['average_guests'] ?? 0
    );

$conversionRate =
    $totalLeads > 0
        ? round(
            ($convertedLeads / $totalLeads) * 100,
            2
        )
        : 0;


/*
|--------------------------------------------------------------------------
| Pagination Count
|--------------------------------------------------------------------------
*/

$countSql = "
    SELECT COUNT(*) AS total

    FROM leads l

    WHERE {$whereSql}
";


$stmt = $conn->prepare(
    $countSql
);

if (!$stmt) {

    jsonResponse(
        false,
        'Failed to prepare pagination count.',
        [],
        500
    );
}


if (!empty($params)) {

    $stmt->bind_param(
        $types,
        ...$params
    );
}


$stmt->execute();

$countRow =
    $stmt->get_result()
        ->fetch_assoc();

$stmt->close();


$totalRecords =
    (int)($countRow['total'] ?? 0);

$totalPages =
    $totalRecords > 0
        ? (int)ceil(
            $totalRecords / $limit
        )
        : 0;

$offset =
    ($page - 1) * $limit;


/*
|--------------------------------------------------------------------------
| Detailed Lead Records
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
        au.phone AS assigned_admin_phone

    FROM leads l

    LEFT JOIN admin_users au
        ON au.id = l.assigned_admin_id

    WHERE {$whereSql}

    ORDER BY
        {$sortColumn} {$sortOrder}

    LIMIT ? OFFSET ?
";


$dataParams =
    $params;

$dataTypes =
    $types . 'ii';

$dataParams[] =
    $limit;

$dataParams[] =
    $offset;


$stmt = $conn->prepare(
    $dataSql
);

if (!$stmt) {

    jsonResponse(
        false,
        'Failed to prepare lead records query.',
        [],
        500
    );
}


$stmt->bind_param(
    $dataTypes,
    ...$dataParams
);

$stmt->execute();

$result =
    $stmt->get_result();

$leads = [];


while (
    $row =
        $result->fetch_assoc()
) {

    $currentLeadId =
        (int)$row['id'];

    $domainDetails = [];


    /*
    |--------------------------------------------------------------------------
    | HALL DETAILS
    |--------------------------------------------------------------------------
    */

    if ($row['domain'] === 'hall') {

        $hallStmt = $conn->prepare("
            SELECT

                hld.hall_id,
                h.name AS hall_name,
                h.slug AS hall_slug,
                ht.id AS hall_type_id,
                ht.name AS hall_type_name,

                hld.event_type,
                hld.event_date,
                hld.event_time,
                hld.guest_count,

                hld.food_required,
                hld.decoration_required,

                hld.other_requirements

            FROM hall_lead_details hld

            LEFT JOIN halls h
                ON h.id = hld.hall_id

            LEFT JOIN hall_types ht
                ON ht.id = h.hall_type_id

            WHERE hld.lead_id = ?

            LIMIT 1
        ");

        if ($hallStmt) {

            $hallStmt->bind_param(
                'i',
                $currentLeadId
            );

            $hallStmt->execute();

            $hallRow =
                $hallStmt
                    ->get_result()
                    ->fetch_assoc();

            $hallStmt->close();

            if ($hallRow) {

                $domainDetails = [

                    'hall_id' =>
                        $hallRow['hall_id']
                        !== null
                            ? (int)$hallRow[
                                'hall_id'
                            ]
                            : null,

                    'hall_name' =>
                        $hallRow[
                            'hall_name'
                        ],

                    'hall_slug' =>
                        $hallRow[
                            'hall_slug'
                        ],

                    'hall_type_id' =>
                        $hallRow[
                            'hall_type_id'
                        ] !== null
                            ? (int)$hallRow[
                                'hall_type_id'
                            ]
                            : null,

                    'hall_type_name' =>
                        $hallRow[
                            'hall_type_name'
                        ],

                    'event_type' =>
                        $hallRow[
                            'event_type'
                        ],

                    'event_date' =>
                        $hallRow[
                            'event_date'
                        ],

                    'event_time' =>
                        $hallRow[
                            'event_time'
                        ],

                    'guest_count' =>
                        $hallRow[
                            'guest_count'
                        ] !== null
                            ? (int)$hallRow[
                                'guest_count'
                            ]
                            : null,

                    'food_required' =>
                        (bool)$hallRow[
                            'food_required'
                        ],

                    'decoration_required' =>
                        (bool)$hallRow[
                            'decoration_required'
                        ],

                    'other_requirements' =>
                        $hallRow[
                            'other_requirements'
                        ]
                ];
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | DINING DETAILS
    |--------------------------------------------------------------------------
    */

    elseif (
        $row['domain'] === 'dining'
    ) {

        $diningStmt = $conn->prepare("
            SELECT

                dld.dining_package_id,

                dp.name AS dining_package_name,
                dp.description AS dining_package_description,
                dp.meal_type AS dining_package_meal_type,

                dld.meal_type,
                dld.dining_date,
                dld.dining_time,
                dld.guest_count,

                dld.other_requirements

            FROM dining_lead_details dld

            LEFT JOIN dining_packages dp
                ON dp.id = dld.dining_package_id

            WHERE dld.lead_id = ?

            LIMIT 1
        ");

        if ($diningStmt) {

            $diningStmt->bind_param(
                'i',
                $currentLeadId
            );

            $diningStmt->execute();

            $diningRow =
                $diningStmt
                    ->get_result()
                    ->fetch_assoc();

            $diningStmt->close();

            if ($diningRow) {

                $domainDetails = [

                    'dining_package_id' =>
                        $diningRow[
                            'dining_package_id'
                        ] !== null
                            ? (int)$diningRow[
                                'dining_package_id'
                            ]
                            : null,

                    'dining_package_name' =>
                        $diningRow[
                            'dining_package_name'
                        ],

                    'dining_package_description' =>
                        $diningRow[
                            'dining_package_description'
                        ],

                    'dining_package_meal_type' =>
                        $diningRow[
                            'dining_package_meal_type'
                        ],

                    'meal_type' =>
                        $diningRow[
                            'meal_type'
                        ],

                    'dining_date' =>
                        $diningRow[
                            'dining_date'
                        ],

                    'dining_time' =>
                        $diningRow[
                            'dining_time'
                        ],

                    'guest_count' =>
                        $diningRow[
                            'guest_count'
                        ] !== null
                            ? (int)$diningRow[
                                'guest_count'
                            ]
                            : null,

                    'other_requirements' =>
                        $diningRow[
                            'other_requirements'
                        ]
                ];
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CATERING DETAILS
    |--------------------------------------------------------------------------
    */

    elseif (
        $row['domain'] === 'catering'
    ) {

        $cateringStmt = $conn->prepare("
            SELECT

                cld.catering_package_id,

                cp.name AS catering_package_name,
                cp.description AS catering_package_description,

                cp.rate AS package_rate,
                cp.rate_unit AS package_rate_unit,
                cp.gst_rate AS package_gst_rate,
                cp.price_includes_gst,
                cp.minimum_quantity,

                cld.event_type,
                cld.event_date,
                cld.event_time,
                cld.guest_count,

                cld.venue_address,
                cld.estimated_amount,
                cld.other_requirements

            FROM catering_lead_details cld

            LEFT JOIN catering_packages cp
                ON cp.id = cld.catering_package_id

            WHERE cld.lead_id = ?

            LIMIT 1
        ");

        if ($cateringStmt) {

            $cateringStmt->bind_param(
                'i',
                $currentLeadId
            );

            $cateringStmt->execute();

            $cateringRow =
                $cateringStmt
                    ->get_result()
                    ->fetch_assoc();

            $cateringStmt->close();

            if ($cateringRow) {

                $domainDetails = [

                    'catering_package_id' =>
                        $cateringRow[
                            'catering_package_id'
                        ] !== null
                            ? (int)$cateringRow[
                                'catering_package_id'
                            ]
                            : null,

                    'catering_package_name' =>
                        $cateringRow[
                            'catering_package_name'
                        ],

                    'catering_package_description' =>
                        $cateringRow[
                            'catering_package_description'
                        ],

                    'package_rate' =>
                        $cateringRow[
                            'package_rate'
                        ] !== null
                            ? (float)$cateringRow[
                                'package_rate'
                            ]
                            : null,

                    'package_rate_unit' =>
                        $cateringRow[
                            'package_rate_unit'
                        ],

                    'package_gst_rate' =>
                        $cateringRow[
                            'package_gst_rate'
                        ] !== null
                            ? (float)$cateringRow[
                                'package_gst_rate'
                            ]
                            : null,

                    'price_includes_gst' =>
                        $cateringRow[
                            'price_includes_gst'
                        ] !== null
                            ? (bool)$cateringRow[
                                'price_includes_gst'
                            ]
                            : null,

                    'minimum_quantity' =>
                        $cateringRow[
                            'minimum_quantity'
                        ] !== null
                            ? (int)$cateringRow[
                                'minimum_quantity'
                            ]
                            : null,

                    'event_type' =>
                        $cateringRow[
                            'event_type'
                        ],

                    'event_date' =>
                        $cateringRow[
                            'event_date'
                        ],

                    'event_time' =>
                        $cateringRow[
                            'event_time'
                        ],

                    'guest_count' =>
                        $cateringRow[
                            'guest_count'
                        ] !== null
                            ? (int)$cateringRow[
                                'guest_count'
                            ]
                            : null,

                    'venue_address' =>
                        $cateringRow[
                            'venue_address'
                        ],

                    'estimated_amount' =>
                        $cateringRow[
                            'estimated_amount'
                        ] !== null
                            ? (float)$cateringRow[
                                'estimated_amount'
                            ]
                            : null,

                    'other_requirements' =>
                        $cateringRow[
                            'other_requirements'
                        ]
                ];
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Lead Notifications
    |--------------------------------------------------------------------------
    */

    $notificationSummary = [

        'total' => 0,

        'pending' => 0,
        'sent' => 0,
        'failed' => 0,

        'whatsapp' => 0,
        'email' => 0
    ];


    $notificationStmt =
        $conn->prepare("
            SELECT

                COUNT(*) AS total,

                SUM(
                    CASE
                        WHEN status = 'pending'
                        THEN 1
                        ELSE 0
                    END
                ) AS pending,

                SUM(
                    CASE
                        WHEN status = 'sent'
                        THEN 1
                        ELSE 0
                    END
                ) AS sent,

                SUM(
                    CASE
                        WHEN status = 'failed'
                        THEN 1
                        ELSE 0
                    END
                ) AS failed,

                SUM(
                    CASE
                        WHEN channel = 'whatsapp'
                        THEN 1
                        ELSE 0
                    END
                ) AS whatsapp,

                SUM(
                    CASE
                        WHEN channel = 'email'
                        THEN 1
                        ELSE 0
                    END
                ) AS email

            FROM lead_notifications

            WHERE lead_id = ?
        ");

    if ($notificationStmt) {

        $notificationStmt->bind_param(
            'i',
            $currentLeadId
        );

        $notificationStmt->execute();

        $notificationRow =
            $notificationStmt
                ->get_result()
                ->fetch_assoc();

        $notificationStmt->close();

        if ($notificationRow) {

            $notificationSummary = [

                'total' =>
                    (int)(
                        $notificationRow[
                            'total'
                        ] ?? 0
                    ),

                'pending' =>
                    (int)(
                        $notificationRow[
                            'pending'
                        ] ?? 0
                    ),

                'sent' =>
                    (int)(
                        $notificationRow[
                            'sent'
                        ] ?? 0
                    ),

                'failed' =>
                    (int)(
                        $notificationRow[
                            'failed'
                        ] ?? 0
                    ),

                'whatsapp' =>
                    (int)(
                        $notificationRow[
                            'whatsapp'
                        ] ?? 0
                    ),

                'email' =>
                    (int)(
                        $notificationRow[
                            'email'
                        ] ?? 0
                    )
            ];
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Final Lead Object
    |--------------------------------------------------------------------------
    */

    $leads[] = [

        'id' =>
            $currentLeadId,

        'lead_reference' =>
            $row['lead_reference'],

        'domain' =>
            $row['domain'],


        'customer' => [

            'name' =>
                $row['customer_name'],

            'phone' =>
                $row['customer_phone'],

            'email' =>
                $row['customer_email']
        ],


        'event' => [

            'date' =>
                $row['event_date'],

            'guest_count' =>
                $row['guest_count']
                !== null
                    ? (int)$row[
                        'guest_count'
                    ]
                    : null,

            'preferred_time' =>
                $row['preferred_time']
        ],


        'domain_details' =>
            $domainDetails,


        'requirement' =>
            $row['requirement'],


        'status' =>
            $row['status'],


        'amounts' => [

            'estimated_amount' =>
                $row['estimated_amount']
                !== null
                    ? (float)$row[
                        'estimated_amount'
                    ]
                    : null,

            'converted_amount' =>
                $row['converted_amount']
                !== null
                    ? (float)$row[
                        'converted_amount'
                    ]
                    : null
        ],


        'conversion' => [

            'converted_at' =>
                $row['converted_at']
        ],


        'source' =>
            $row['source'],


        'assigned_admin' => [

            'id' =>
                $row[
                    'assigned_admin_id'
                ] !== null
                    ? (int)$row[
                        'assigned_admin_id'
                    ]
                    : null,

            'name' =>
                $row[
                    'assigned_admin_name'
                ],

            'email' =>
                $row[
                    'assigned_admin_email'
                ],

            'phone' =>
                $row[
                    'assigned_admin_phone'
                ]
        ],


        'notifications' =>
            $notificationSummary,


        'created_at' =>
            $row['created_at'],

        'updated_at' =>
            $row['updated_at']
    ];
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Domain Summary
|--------------------------------------------------------------------------
*/

$domainSql = "
    SELECT

        l.domain,

        COUNT(*) AS total,

        COUNT(
            CASE
                WHEN l.status = 'converted'
                THEN 1
            END
        ) AS converted,

        COUNT(
            CASE
                WHEN l.status IN (
                    'new',
                    'contacted',
                    'follow_up'
                )
                THEN 1
            END
        ) AS pending,

        COUNT(
            CASE
                WHEN l.status = 'lost'
                THEN 1
            END
        ) AS lost,

        COUNT(
            CASE
                WHEN l.status = 'cancelled'
                THEN 1
            END
        ) AS cancelled,

        COALESCE(
            SUM(
                CASE
                    WHEN l.status = 'converted'
                    THEN COALESCE(
                        l.converted_amount,
                        0
                    )
                    ELSE 0
                END
            ),
            0
        ) AS revenue,

        COALESCE(
            SUM(
                COALESCE(
                    l.estimated_amount,
                    0
                )
            ),
            0
        ) AS estimated_value,

        COALESCE(
            SUM(
                COALESCE(
                    l.guest_count,
                    0
                )
            ),
            0
        ) AS guests

    FROM leads l

    WHERE {$whereSql}

    GROUP BY l.domain

    ORDER BY total DESC
";


$stmt = $conn->prepare(
    $domainSql
);

if (!$stmt) {

    jsonResponse(
        false,
        'Failed to prepare domain summary.',
        [],
        500
    );
}


if (!empty($params)) {

    $stmt->bind_param(
        $types,
        ...$params
    );
}


$stmt->execute();

$domainResult =
    $stmt->get_result();

$domainSummary = [];

while (
    $row =
        $domainResult->fetch_assoc()
) {

    $total =
        (int)$row['total'];

    $converted =
        (int)$row['converted'];

    $domainSummary[] = [

        'domain' =>
            $row['domain'],

        'total_leads' =>
            $total,

        'converted_leads' =>
            $converted,

        'pending_leads' =>
            (int)$row['pending'],

        'lost_leads' =>
            (int)$row['lost'],

        'cancelled_leads' =>
            (int)$row['cancelled'],

        'conversion_rate' =>
            $total > 0
                ? round(
                    ($converted / $total) * 100,
                    2
                )
                : 0,

        'converted_revenue' =>
            (float)$row['revenue'],

        'estimated_value' =>
            (float)$row[
                'estimated_value'
            ],

        'total_guests' =>
            (int)$row['guests']
    ];
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Status Summary
|--------------------------------------------------------------------------
*/

$statusSql = "
    SELECT

        l.status,

        COUNT(*) AS total,

        COALESCE(
            SUM(
                CASE
                    WHEN l.status = 'converted'
                    THEN COALESCE(
                        l.converted_amount,
                        0
                    )
                    ELSE 0
                END
            ),
            0
        ) AS revenue

    FROM leads l

    WHERE {$whereSql}

    GROUP BY l.status

    ORDER BY FIELD(
        l.status,
        'new',
        'contacted',
        'follow_up',
        'converted',
        'lost',
        'cancelled'
    )
";


$stmt = $conn->prepare(
    $statusSql
);

if (!$stmt) {

    jsonResponse(
        false,
        'Failed to prepare status summary.',
        [],
        500
    );
}


if (!empty($params)) {

    $stmt->bind_param(
        $types,
        ...$params
    );
}


$stmt->execute();

$statusResult =
    $stmt->get_result();

$statusSummary = [];

while (
    $row =
        $statusResult->fetch_assoc()
) {

    $statusSummary[] = [

        'status' =>
            $row['status'],

        'total' =>
            (int)$row['total'],

        'converted_revenue' =>
            (float)$row['revenue']
    ];
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Source Summary
|--------------------------------------------------------------------------
*/

$sourceSql = "
    SELECT

        l.source,

        COUNT(*) AS total,

        COUNT(
            CASE
                WHEN l.status = 'converted'
                THEN 1
            END
        ) AS converted,

        COALESCE(
            SUM(
                CASE
                    WHEN l.status = 'converted'
                    THEN COALESCE(
                        l.converted_amount,
                        0
                    )
                    ELSE 0
                END
            ),
            0
        ) AS revenue

    FROM leads l

    WHERE {$whereSql}

    GROUP BY l.source

    ORDER BY total DESC
";


$stmt = $conn->prepare(
    $sourceSql
);

if (!$stmt) {

    jsonResponse(
        false,
        'Failed to prepare source summary.',
        [],
        500
    );
}


if (!empty($params)) {

    $stmt->bind_param(
        $types,
        ...$params
    );
}


$stmt->execute();

$sourceResult =
    $stmt->get_result();

$sourceSummary = [];

while (
    $row =
        $sourceResult->fetch_assoc()
) {

    $total =
        (int)$row['total'];

    $converted =
        (int)$row['converted'];

    $sourceSummary[] = [

        'source' =>
            $row['source'],

        'total_leads' =>
            $total,

        'converted_leads' =>
            $converted,

        'conversion_rate' =>
            $total > 0
                ? round(
                    ($converted / $total) * 100,
                    2
                )
                : 0,

        'converted_revenue' =>
            (float)$row['revenue']
    ];
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Assigned Admin Summary
|--------------------------------------------------------------------------
*/

$assignedSql = "
    SELECT

        COALESCE(
            l.assigned_admin_id,
            0
        ) AS assigned_admin_id,

        COALESCE(
            au.name,
            'Unassigned'
        ) AS admin_name,

        COUNT(*) AS total,

        COUNT(
            CASE
                WHEN l.status = 'converted'
                THEN 1
            END
        ) AS converted,

        COUNT(
            CASE
                WHEN l.status IN (
                    'new',
                    'contacted',
                    'follow_up'
                )
                THEN 1
            END
        ) AS pending,

        COALESCE(
            SUM(
                CASE
                    WHEN l.status = 'converted'
                    THEN COALESCE(
                        l.converted_amount,
                        0
                    )
                    ELSE 0
                END
            ),
            0
        ) AS revenue

    FROM leads l

    LEFT JOIN admin_users au
        ON au.id = l.assigned_admin_id

    WHERE {$whereSql}

    GROUP BY
        l.assigned_admin_id,
        au.name

    ORDER BY
        total DESC
";


$stmt = $conn->prepare(
    $assignedSql
);

if (!$stmt) {

    jsonResponse(
        false,
        'Failed to prepare assigned admin summary.',
        [],
        500
    );
}


if (!empty($params)) {

    $stmt->bind_param(
        $types,
        ...$params
    );
}


$stmt->execute();

$assignedResult =
    $stmt->get_result();

$assignedSummary = [];

while (
    $row =
        $assignedResult->fetch_assoc()
) {

    $total =
        (int)$row['total'];

    $converted =
        (int)$row['converted'];

    $assignedSummary[] = [

        'assigned_admin_id' =>
            (int)$row[
                'assigned_admin_id'
            ],

        'admin_name' =>
            $row['admin_name'],

        'total_leads' =>
            $total,

        'converted_leads' =>
            $converted,

        'pending_leads' =>
            (int)$row['pending'],

        'conversion_rate' =>
            $total > 0
                ? round(
                    ($converted / $total) * 100,
                    2
                )
                : 0,

        'converted_revenue' =>
            (float)$row['revenue']
    ];
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Monthly Lead Report
|--------------------------------------------------------------------------
*/

$monthlySql = "
    SELECT

        DATE_FORMAT(
            l.created_at,
            '%Y-%m'
        ) AS month,

        COUNT(*) AS total_leads,

        COUNT(
            CASE
                WHEN l.status = 'converted'
                THEN 1
            END
        ) AS converted_leads,

        COUNT(
            CASE
                WHEN l.status IN (
                    'new',
                    'contacted',
                    'follow_up'
                )
                THEN 1
            END
        ) AS pending_leads,

        COUNT(
            CASE
                WHEN l.status = 'lost'
                THEN 1
            END
        ) AS lost_leads,

        COALESCE(
            SUM(
                CASE
                    WHEN l.status = 'converted'
                    THEN COALESCE(
                        l.converted_amount,
                        0
                    )
                    ELSE 0
                END
            ),
            0
        ) AS converted_revenue,

        COALESCE(
            SUM(
                COALESCE(
                    l.estimated_amount,
                    0
                )
            ),
            0
        ) AS estimated_value

    FROM leads l

    WHERE {$whereSql}

    GROUP BY
        DATE_FORMAT(
            l.created_at,
            '%Y-%m'
        )

    ORDER BY month ASC
";


$stmt = $conn->prepare(
    $monthlySql
);

if (!$stmt) {

    jsonResponse(
        false,
        'Failed to prepare monthly report.',
        [],
        500
    );
}


if (!empty($params)) {

    $stmt->bind_param(
        $types,
        ...$params
    );
}


$stmt->execute();

$monthlyResult =
    $stmt->get_result();

$monthlyReport = [];

while (
    $row =
        $monthlyResult->fetch_assoc()
) {

    $total =
        (int)$row['total_leads'];

    $converted =
        (int)$row['converted_leads'];

    $monthlyReport[] = [

        'month' =>
            $row['month'],

        'total_leads' =>
            $total,

        'converted_leads' =>
            $converted,

        'pending_leads' =>
            (int)$row['pending_leads'],

        'lost_leads' =>
            (int)$row['lost_leads'],

        'conversion_rate' =>
            $total > 0
                ? round(
                    ($converted / $total) * 100,
                    2
                )
                : 0,

        'converted_revenue' =>
            (float)$row[
                'converted_revenue'
            ],

        'estimated_value' =>
            (float)$row[
                'estimated_value'
            ]
    ];
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Upcoming Events
|--------------------------------------------------------------------------
*/

$today =
    date('Y-m-d');


$upcomingWhere = [

    "l.event_date >= ?",

    "l.status IN (
        'new',
        'contacted',
        'follow_up',
        'converted'
    )"
];

$upcomingParams = [
    $today
];

$upcomingTypes = 's';


if ($currentUserRole === 'staff') {

    $upcomingWhere[] =
        "l.assigned_admin_id = ?";

    $upcomingParams[] =
        $currentUserId;

    $upcomingTypes .= 'i';
}


if ($domain !== 'all') {

    $upcomingWhere[] =
        "l.domain = ?";

    $upcomingParams[] =
        $domain;

    $upcomingTypes .= 's';
}


$upcomingSql = "
    SELECT

        l.id,
        l.lead_reference,
        l.domain,

        l.customer_name,
        l.customer_phone,

        l.event_date,
        l.guest_count,
        l.preferred_time,

        l.status,

        l.estimated_amount,
        l.converted_amount

    FROM leads l

    WHERE " .
    implode(
        ' AND ',
        $upcomingWhere
    ) . "

    ORDER BY
        l.event_date ASC,
        l.created_at ASC

    LIMIT 20
";


$stmt = $conn->prepare(
    $upcomingSql
);

if (!$stmt) {

    jsonResponse(
        false,
        'Failed to prepare upcoming events.',
        [],
        500
    );
}


$stmt->bind_param(
    $upcomingTypes,
    ...$upcomingParams
);

$stmt->execute();

$upcomingResult =
    $stmt->get_result();

$upcomingEvents = [];

while (
    $row =
        $upcomingResult->fetch_assoc()
) {

    $upcomingEvents[] = [

        'lead_id' =>
            (int)$row['id'],

        'lead_reference' =>
            $row['lead_reference'],

        'domain' =>
            $row['domain'],

        'customer_name' =>
            $row['customer_name'],

        'customer_phone' =>
            $row['customer_phone'],

        'event_date' =>
            $row['event_date'],

        'guest_count' =>
            $row['guest_count']
            !== null
                ? (int)$row[
                    'guest_count'
                ]
                : null,

        'preferred_time' =>
            $row['preferred_time'],

        'status' =>
            $row['status'],

        'estimated_amount' =>
            $row['estimated_amount']
            !== null
                ? (float)$row[
                    'estimated_amount'
                ]
                : null,

        'converted_amount' =>
            $row['converted_amount']
            !== null
                ? (float)$row[
                    'converted_amount'
                ]
                : null
    ];
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Notification Summary
|--------------------------------------------------------------------------
*/

$notificationWhere = [
    "l.domain IS NOT NULL"
];

$notificationParams = [];
$notificationTypes = '';


if ($currentUserRole === 'staff') {

    $notificationWhere[] =
        "l.assigned_admin_id = ?";

    $notificationParams[] =
        $currentUserId;

    $notificationTypes .= 'i';
}


if ($domain !== 'all') {

    $notificationWhere[] =
        "l.domain = ?";

    $notificationParams[] =
        $domain;

    $notificationTypes .= 's';
}


$notificationSql = "
    SELECT

        COUNT(ln.id) AS total,

        SUM(
            CASE
                WHEN ln.status = 'pending'
                THEN 1
                ELSE 0
            END
        ) AS pending,

        SUM(
            CASE
                WHEN ln.status = 'sent'
                THEN 1
                ELSE 0
            END
        ) AS sent,

        SUM(
            CASE
                WHEN ln.status = 'failed'
                THEN 1
                ELSE 0
            END
        ) AS failed,

        SUM(
            CASE
                WHEN ln.channel = 'whatsapp'
                THEN 1
                ELSE 0
            END
        ) AS whatsapp,

        SUM(
            CASE
                WHEN ln.channel = 'email'
                THEN 1
                ELSE 0
            END
        ) AS email

    FROM lead_notifications ln

    INNER JOIN leads l
        ON l.id = ln.lead_id

    WHERE " .
    implode(
        ' AND ',
        $notificationWhere
    );


$stmt = $conn->prepare(
    $notificationSql
);

if (!$stmt) {

    jsonResponse(
        false,
        'Failed to prepare notification summary.',
        [],
        500
    );
}


if (!empty($notificationParams)) {

    $stmt->bind_param(
        $notificationTypes,
        ...$notificationParams
    );
}


$stmt->execute();

$notificationRow =
    $stmt->get_result()
        ->fetch_assoc();

$stmt->close();


$notificationSummary = [

    'total' =>
        (int)(
            $notificationRow['total']
            ?? 0
        ),

    'pending' =>
        (int)(
            $notificationRow['pending']
            ?? 0
        ),

    'sent' =>
        (int)(
            $notificationRow['sent']
            ?? 0
        ),

    'failed' =>
        (int)(
            $notificationRow['failed']
            ?? 0
        ),

    'whatsapp' =>
        (int)(
            $notificationRow['whatsapp']
            ?? 0
        ),

    'email' =>
        (int)(
            $notificationRow['email']
            ?? 0
        )
];


/*
|--------------------------------------------------------------------------
| Chart.js Data
|--------------------------------------------------------------------------
*/

$domainLabels = [];
$domainLeadData = [];
$domainRevenueData = [];

foreach (
    $domainSummary as $item
) {

    $domainLabels[] =
        ucfirst(
            $item['domain']
        );

    $domainLeadData[] =
        $item['total_leads'];

    $domainRevenueData[] =
        $item['converted_revenue'];
}


$statusLabels = [];
$statusLeadData = [];
$statusRevenueData = [];

foreach (
    $statusSummary as $item
) {

    $statusLabels[] =
        ucfirst(
            str_replace(
                '_',
                ' ',
                $item['status']
            )
        );

    $statusLeadData[] =
        $item['total'];

    $statusRevenueData[] =
        $item[
            'converted_revenue'
        ];
}


$sourceLabels = [];
$sourceLeadData = [];
$sourceRevenueData = [];

foreach (
    $sourceSummary as $item
) {

    $sourceLabels[] =
        ucfirst(
            $item['source']
        );

    $sourceLeadData[] =
        $item['total_leads'];

    $sourceRevenueData[] =
        $item[
            'converted_revenue'
        ];
}


$monthlyLabels = [];
$monthlyLeadData = [];
$monthlyConvertedData = [];
$monthlyRevenueData = [];

foreach (
    $monthlyReport as $item
) {

    $monthlyLabels[] =
        $item['month'];

    $monthlyLeadData[] =
        $item['total_leads'];

    $monthlyConvertedData[] =
        $item['converted_leads'];

    $monthlyRevenueData[] =
        $item[
            'converted_revenue'
        ];
}


$adminLabels = [];
$adminLeadData = [];
$adminRevenueData = [];

foreach (
    $assignedSummary as $item
) {

    $adminLabels[] =
        $item['admin_name'];

    $adminLeadData[] =
        $item['total_leads'];

    $adminRevenueData[] =
        $item[
            'converted_revenue'
        ];
}


/*
|--------------------------------------------------------------------------
| Final Response
|--------------------------------------------------------------------------
*/

jsonResponse(
    true,
    'Lead report fetched successfully.',
    [

        /*
        |--------------------------------------------------------------------------
        | Meta
        |--------------------------------------------------------------------------
        */

        'meta' => [

            'report' =>
                'lead_report',

            'generated_at' =>
                date('Y-m-d H:i:s'),

            'currency' =>
                'INR',

            'domains' =>
                [
                    'hall',
                    'dining',
                    'catering'
                ],

            'revenue_source' =>
                'converted_amount from converted leads'
        ],


        /*
        |--------------------------------------------------------------------------
        | User Scope
        |--------------------------------------------------------------------------
        */

        'user_scope' => [

            'role' =>
                $currentUserRole,

            'user_id' =>
                $currentUserId,

            'scope' =>
                $currentUserRole === 'staff'
                    ? 'assigned leads only'
                    : 'all leads'
        ],


        /*
        |--------------------------------------------------------------------------
        | Applied Filters
        |--------------------------------------------------------------------------
        */

        'filters' => [

            'lead_id' =>
                $leadId > 0
                    ? $leadId
                    : null,

            'domain' =>
                $domain,

            'status' =>
                $status,

            'source' =>
                $source,

            'search' =>
                $search,

            'from_date' =>
                $fromDate,

            'to_date' =>
                $toDate,

            'event_date_from' =>
                $eventDateFrom,

            'event_date_to' =>
                $eventDateTo,

            'min_guests' =>
                $minGuests > 0
                    ? $minGuests
                    : null,

            'max_guests' =>
                $maxGuests > 0
                    ? $maxGuests
                    : null,

            'min_amount' =>
                $minAmount,

            'max_amount' =>
                $maxAmount,

            'assigned_admin_id' =>
                $assignedAdminId > 0
                    ? $assignedAdminId
                    : null,

            'page' =>
                $page,

            'limit' =>
                $limit,

            'sort_by' =>
                $sortBy,

            'sort_order' =>
                $sortOrder
        ],


        /*
        |--------------------------------------------------------------------------
        | KPI
        |--------------------------------------------------------------------------
        */

        'kpis' => [

            'total_leads' =>
                $totalLeads,

            'new_leads' =>
                $newLeads,

            'contacted_leads' =>
                $contactedLeads,

            'follow_up_leads' =>
                $followUpLeads,

            'converted_leads' =>
                $convertedLeads,

            'lost_leads' =>
                $lostLeads,

            'cancelled_leads' =>
                $cancelledLeads,

            'conversion_rate' =>
                $conversionRate,

            'converted_revenue' =>
                round(
                    $convertedRevenue,
                    2
                ),

            'pending_estimated_revenue' =>
                round(
                    $pendingEstimatedRevenue,
                    2
                ),

            'total_estimated_value' =>
                round(
                    $totalEstimatedValue,
                    2
                ),

            'total_guests' =>
                $totalGuests,

            'average_guests' =>
                round(
                    $averageGuests,
                    2
                )
        ],


        /*
        |--------------------------------------------------------------------------
        | Domain Summary
        |--------------------------------------------------------------------------
        */

        'domain_summary' =>
            $domainSummary,


        /*
        |--------------------------------------------------------------------------
        | Status Summary
        |--------------------------------------------------------------------------
        */

        'status_summary' =>
            $statusSummary,


        /*
        |--------------------------------------------------------------------------
        | Source Summary
        |--------------------------------------------------------------------------
        */

        'source_summary' =>
            $sourceSummary,


        /*
        |--------------------------------------------------------------------------
        | Assigned Admin Summary
        |--------------------------------------------------------------------------
        */

        'assigned_admin_summary' =>
            $assignedSummary,


        /*
        |--------------------------------------------------------------------------
        | Monthly
        |--------------------------------------------------------------------------
        */

        'monthly_report' =>
            $monthlyReport,


        /*
        |--------------------------------------------------------------------------
        | Upcoming
        |--------------------------------------------------------------------------
        */

        'upcoming_events' =>
            $upcomingEvents,


        /*
        |--------------------------------------------------------------------------
        | Notifications
        |--------------------------------------------------------------------------
        */

        'notifications' =>
            $notificationSummary,


        /*
        |--------------------------------------------------------------------------
        | Records
        |--------------------------------------------------------------------------
        */

        'records' =>
            $leads,


        /*
        |--------------------------------------------------------------------------
        | Pagination
        |--------------------------------------------------------------------------
        */

        'pagination' => [

            'current_page' =>
                $page,

            'per_page' =>
                $limit,

            'total_records' =>
                $totalRecords,

            'total_pages' =>
                $totalPages,

            'has_next_page' =>
                $page < $totalPages,

            'has_previous_page' =>
                $page > 1 &&
                $totalPages > 0
        ],


        /*
        |--------------------------------------------------------------------------
        | Chart.js
        |--------------------------------------------------------------------------
        */

        'charts' => [

            'leads_by_domain' => [

                'labels' =>
                    $domainLabels,

                'datasets' => [

                    [
                        'label' =>
                            'Total Leads',

                        'data' =>
                            $domainLeadData
                    ]
                ]
            ],


            'revenue_by_domain' => [

                'labels' =>
                    $domainLabels,

                'datasets' => [

                    [
                        'label' =>
                            'Converted Revenue',

                        'data' =>
                            $domainRevenueData
                    ]
                ]
            ],


            'leads_by_status' => [

                'labels' =>
                    $statusLabels,

                'datasets' => [

                    [
                        'label' =>
                            'Leads',

                        'data' =>
                            $statusLeadData
                    ]
                ]
            ],


            'revenue_by_status' => [

                'labels' =>
                    $statusLabels,

                'datasets' => [

                    [
                        'label' =>
                            'Converted Revenue',

                        'data' =>
                            $statusRevenueData
                    ]
                ]
            ],


            'leads_by_source' => [

                'labels' =>
                    $sourceLabels,

                'datasets' => [

                    [
                        'label' =>
                            'Leads',

                        'data' =>
                            $sourceLeadData
                    ]
                ]
            ],


            'revenue_by_source' => [

                'labels' =>
                    $sourceLabels,

                'datasets' => [

                    [
                        'label' =>
                            'Converted Revenue',

                        'data' =>
                            $sourceRevenueData
                    ]
                ]
            ],


            'monthly_leads' => [

                'labels' =>
                    $monthlyLabels,

                'datasets' => [

                    [
                        'label' =>
                            'Total Leads',

                        'data' =>
                            $monthlyLeadData
                    ],

                    [
                        'label' =>
                            'Converted Leads',

                        'data' =>
                            $monthlyConvertedData
                    ]
                ]
            ],


            'monthly_revenue' => [

                'labels' =>
                    $monthlyLabels,

                'datasets' => [

                    [
                        'label' =>
                            'Converted Revenue',

                        'data' =>
                            $monthlyRevenueData
                    ]
                ]
            ],


            'leads_by_admin' => [

                'labels' =>
                    $adminLabels,

                'datasets' => [

                    [
                        'label' =>
                            'Assigned Leads',

                        'data' =>
                            $adminLeadData
                    ]
                ]
            ],


            'revenue_by_admin' => [

                'labels' =>
                    $adminLabels,

                'datasets' => [

                    [
                        'label' =>
                            'Converted Revenue',

                        'data' =>
                            $adminRevenueData
                    ]
                ]
            ]
        ]
    ]
);