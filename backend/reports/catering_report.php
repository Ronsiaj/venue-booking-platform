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
| Helpers
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
    if (!isset($_GET[$key]) || $_GET[$key] === '') {
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

    if ($value < $min || $value > $max) {
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
    if (!isset($_GET[$key]) || $_GET[$key] === '') {
        return $default;
    }

    $raw = trim((string)$_GET[$key]);

    if (!preg_match('/^\d+(\.\d{1,2})?$/', $raw)) {
        jsonResponse(
            false,
            "{$key} must be a valid amount with maximum 2 decimal places.",
            [],
            422
        );
    }

    $value = (float)$raw;

    if ($value < $min || $value > $max) {
        jsonResponse(
            false,
            "Invalid {$key}. Allowed range: {$min} to {$max}.",
            [],
            422
        );
    }

    return $value;
}


function getDateParam(string $key): ?string
{
    if (!isset($_GET[$key]) || trim((string)$_GET[$key]) === '') {
        return null;
    }

    $value = trim((string)$_GET[$key]);

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


function getStringParam(
    string $key,
    ?string $default = null,
    int $maxLength = 255
): ?string {
    if (!isset($_GET[$key])) {
        return $default;
    }

    $value = trim((string)$_GET[$key]);

    if ($value === '') {
        return $default;
    }

    if (mb_strlen($value) > $maxLength) {
        jsonResponse(
            false,
            "{$key} cannot exceed {$maxLength} characters.",
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
    trim((string)($currentUser['role'] ?? ''))
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
        'Only admin or staff users can access catering reports.',
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

$cateringPackageId = getIntParam(
    'catering_package_id',
    0,
    0
);

$foodItemId = getIntParam(
    'food_item_id',
    0,
    0
);

$leadStatus = getStringParam(
    'lead_status',
    'all',
    30
);

$search = getStringParam(
    'search',
    null,
    150
);

$eventType = getStringParam(
    'event_type',
    'all',
    100
);

$fromDate = getDateParam('from_date');
$toDate = getDateParam('to_date');

$eventDateFrom = getDateParam(
    'event_date_from'
);

$eventDateTo = getDateParam(
    'event_date_to'
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

$minAmount = getFloatParam(
    'min_amount',
    null
);

$maxAmount = getFloatParam(
    'max_amount',
    null
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
| Status Validation
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    'all',
    'new',
    'contacted',
    'follow_up',
    'converted',
    'lost',
    'cancelled'
];

if (!in_array(
    $leadStatus,
    $allowedStatuses,
    true
)) {
    jsonResponse(
        false,
        'Invalid lead_status.',
        [
            'allowed' => $allowedStatuses
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
        'cld.event_date',

    'guest_count' =>
        'cld.guest_count',

    'estimated_amount' =>
        'cld.estimated_amount',

    'converted_amount' =>
        'l.converted_amount',

    'customer_name' =>
        'l.customer_name',

    'lead_reference' =>
        'l.lead_reference',

    'status' =>
        'l.status'
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
                array_keys($allowedSortColumns)
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
          AND domain = 'catering'
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

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {

        $stmt->close();

        jsonResponse(
            false,
            'Catering lead not found.',
            [],
            404
        );
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Validate Catering Package
|--------------------------------------------------------------------------
*/

if ($cateringPackageId > 0) {

    $stmt = $conn->prepare("
        SELECT id
        FROM catering_packages
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        jsonResponse(
            false,
            'Failed to validate catering package.',
            [],
            500
        );
    }

    $stmt->bind_param(
        'i',
        $cateringPackageId
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {

        $stmt->close();

        jsonResponse(
            false,
            'Catering package not found.',
            [],
            404
        );
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Validate Food Item
|--------------------------------------------------------------------------
*/

if ($foodItemId > 0) {

    $stmt = $conn->prepare("
        SELECT id
        FROM food_items
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        jsonResponse(
            false,
            'Failed to validate food item.',
            [],
            500
        );
    }

    $stmt->bind_param(
        'i',
        $foodItemId
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {

        $stmt->close();

        jsonResponse(
            false,
            'Food item not found.',
            [],
            404
        );
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Base Conditions
|--------------------------------------------------------------------------
*/

$where = [
    "l.domain = 'catering'"
];

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
| Package
|--------------------------------------------------------------------------
*/

if ($cateringPackageId > 0) {

    $where[] =
        "cld.catering_package_id = ?";

    $params[] =
        $cateringPackageId;

    $types .= 'i';
}


/*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
*/

if ($leadStatus !== 'all') {

    $where[] =
        "l.status = ?";

    $params[] =
        $leadStatus;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Event Type
|--------------------------------------------------------------------------
*/

if ($eventType !== 'all') {

    $where[] =
        "cld.event_type = ?";

    $params[] =
        $eventType;

    $types .= 's';
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
        "cld.event_date >= ?";

    $params[] =
        $eventDateFrom;

    $types .= 's';
}

if ($eventDateTo !== null) {

    $where[] =
        "cld.event_date <= ?";

    $params[] =
        $eventDateTo;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| Guests
|--------------------------------------------------------------------------
*/

if ($minGuests > 0) {

    $where[] =
        "COALESCE(cld.guest_count, 0) >= ?";

    $params[] =
        $minGuests;

    $types .= 'i';
}

if ($maxGuests > 0) {

    $where[] =
        "COALESCE(cld.guest_count, 0) <= ?";

    $params[] =
        $maxGuests;

    $types .= 'i';
}


/*
|--------------------------------------------------------------------------
| Amount
|--------------------------------------------------------------------------
|
| Uses both estimated_amount and converted_amount
| depending on lead state.
|
*/

if ($minAmount !== null) {

    $where[] = "
        GREATEST(
            COALESCE(cld.estimated_amount, 0),
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
            COALESCE(cld.estimated_amount, 0),
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
            OR COALESCE(cp.name, '') LIKE ?
            OR COALESCE(cld.event_type, '') LIKE ?
            OR COALESCE(cld.venue_address, '') LIKE ?
            OR COALESCE(cld.other_requirements, '') LIKE ?
        )
    ";

    for ($i = 0; $i < 8; $i++) {
        $params[] = $like;
        $types .= 's';
    }
}


/*
|--------------------------------------------------------------------------
| Food Item Filter
|--------------------------------------------------------------------------
*/

if ($foodItemId > 0) {

    $where[] = "
        EXISTS (
            SELECT 1
            FROM catering_lead_items cli_filter
            WHERE cli_filter.lead_id = l.id
              AND cli_filter.food_item_id = ?
        )
    ";

    $params[] =
        $foodItemId;

    $types .= 'i';
}


$whereSql =
    implode(' AND ', $where);


/*
|--------------------------------------------------------------------------
| Main Summary
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
                    THEN COALESCE(l.converted_amount, 0)
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
                        cld.estimated_amount,
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
                    cld.estimated_amount,
                    0
                )
            ),
            0
        ) AS total_estimated_value,

        COALESCE(
            SUM(
                COALESCE(
                    cld.guest_count,
                    0
                )
            ),
            0
        ) AS total_guests,

        COALESCE(
            AVG(
                CASE
                    WHEN cld.guest_count IS NOT NULL
                    THEN cld.guest_count
                END
            ),
            0
        ) AS average_guests

    FROM leads l

    INNER JOIN catering_lead_details cld
        ON cld.lead_id = l.id

    LEFT JOIN catering_packages cp
        ON cp.id = cld.catering_package_id

    WHERE {$whereSql}
";


$stmt = $conn->prepare(
    $summarySql
);

if (!$stmt) {
    jsonResponse(
        false,
        'Failed to prepare summary query.',
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
    $stmt->get_result()->fetch_assoc();

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
    (float)($summary['pending_estimated_revenue'] ?? 0);

$totalEstimatedValue =
    (float)($summary['total_estimated_value'] ?? 0);

$totalGuests =
    (int)($summary['total_guests'] ?? 0);

$averageGuests =
    (float)($summary['average_guests'] ?? 0);

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

    INNER JOIN catering_lead_details cld
        ON cld.lead_id = l.id

    LEFT JOIN catering_packages cp
        ON cp.id = cld.catering_package_id

    WHERE {$whereSql}
";


$stmt = $conn->prepare(
    $countSql
);

if (!$stmt) {
    jsonResponse(
        false,
        'Failed to prepare count query.',
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
    $stmt->get_result()->fetch_assoc();

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
| Detailed Records
|--------------------------------------------------------------------------
*/

$recordsSql = "
    SELECT

        l.id AS lead_id,
        l.lead_reference,

        l.customer_name,
        l.customer_phone,
        l.customer_email,

        l.event_date AS lead_event_date,
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

        cld.catering_package_id,
        cld.event_type,
        cld.event_date,
        cld.event_time,
        cld.guest_count,

        cld.venue_address,
        cld.estimated_amount AS detail_estimated_amount,
        cld.other_requirements,

        cp.name AS catering_package_name,
        cp.description AS catering_package_description,
        cp.rate AS package_rate,
        cp.rate_unit AS package_rate_unit,
        cp.gst_rate AS package_gst_rate,
        cp.price_includes_gst,
        cp.minimum_quantity,
        cp.image_path AS package_image,
        cp.status AS package_status,

        au.name AS assigned_admin_name,
        au.email AS assigned_admin_email

    FROM leads l

    INNER JOIN catering_lead_details cld
        ON cld.lead_id = l.id

    LEFT JOIN catering_packages cp
        ON cp.id = cld.catering_package_id

    LEFT JOIN admin_users au
        ON au.id = l.assigned_admin_id

    WHERE {$whereSql}

    ORDER BY {$sortColumn} {$sortOrder}

    LIMIT ? OFFSET ?
";


$recordParams =
    $params;

$recordTypes =
    $types . 'ii';

$recordParams[] =
    $limit;

$recordParams[] =
    $offset;


$stmt = $conn->prepare(
    $recordsSql
);

if (!$stmt) {
    jsonResponse(
        false,
        'Failed to prepare records query.',
        [],
        500
    );
}

$stmt->bind_param(
    $recordTypes,
    ...$recordParams
);

$stmt->execute();

$result =
    $stmt->get_result();

$records = [];


while ($row = $result->fetch_assoc()) {

    $currentLeadId =
        (int)$row['lead_id'];


    /*
    |--------------------------------------------------------------------------
    | Package Items
    |--------------------------------------------------------------------------
    */

    $packageItems = [];

    if (
        $row['catering_package_id']
        !== null
    ) {

        $itemStmt = $conn->prepare("
            SELECT

                cpi.food_item_id,
                cpi.quantity,

                fi.name AS food_item_name,
                fi.description AS food_item_description,
                fi.food_type,
                fi.image_path,
                fi.status AS food_item_status

            FROM catering_package_items cpi

            INNER JOIN food_items fi
                ON fi.id = cpi.food_item_id

            WHERE cpi.package_id = ?

            ORDER BY fi.name ASC
        ");

        if ($itemStmt) {

            $packageId =
                (int)$row[
                    'catering_package_id'
                ];

            $itemStmt->bind_param(
                'i',
                $packageId
            );

            $itemStmt->execute();

            $itemResult =
                $itemStmt->get_result();

            while (
                $itemRow =
                    $itemResult->fetch_assoc()
            ) {

                $packageItems[] = [

                    'food_item_id' =>
                        (int)$itemRow[
                            'food_item_id'
                        ],

                    'food_item_name' =>
                        $itemRow[
                            'food_item_name'
                        ],

                    'food_item_description' =>
                        $itemRow[
                            'food_item_description'
                        ],

                    'food_type' =>
                        $itemRow[
                            'food_type'
                        ],

                    'image_path' =>
                        $itemRow[
                            'image_path'
                        ],

                    'quantity' =>
                        (int)$itemRow[
                            'quantity'
                        ],

                    'status' =>
                        $itemRow[
                            'food_item_status'
                        ]
                ];
            }

            $itemStmt->close();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Custom Catering Items
    |--------------------------------------------------------------------------
    */

    $customItems = [];

    $customStmt = $conn->prepare("
        SELECT

            cli.id,
            cli.food_item_id,
            cli.quantity,
            cli.notes,

            fi.name AS food_item_name,
            fi.description AS food_item_description,
            fi.food_type,
            fi.image_path,
            fi.status AS food_item_status

        FROM catering_lead_items cli

        INNER JOIN food_items fi
            ON fi.id = cli.food_item_id

        WHERE cli.lead_id = ?

        ORDER BY cli.id ASC
    ");

    if ($customStmt) {

        $customStmt->bind_param(
            'i',
            $currentLeadId
        );

        $customStmt->execute();

        $customResult =
            $customStmt->get_result();

        while (
            $item =
                $customResult->fetch_assoc()
        ) {

            $customItems[] = [

                'id' =>
                    (int)$item['id'],

                'food_item_id' =>
                    (int)$item['food_item_id'],

                'food_item_name' =>
                    $item['food_item_name'],

                'food_item_description' =>
                    $item[
                        'food_item_description'
                    ],

                'food_type' =>
                    $item['food_type'],

                'image_path' =>
                    $item['image_path'],

                'quantity' =>
                    (int)$item['quantity'],

                'notes' =>
                    $item['notes'],

                'status' =>
                    $item[
                        'food_item_status'
                    ]
            ];
        }

        $customStmt->close();
    }


    $records[] = [

        'lead_id' =>
            $currentLeadId,

        'lead_reference' =>
            $row['lead_reference'],


        'customer' => [

            'name' =>
                $row['customer_name'],

            'phone' =>
                $row['customer_phone'],

            'email' =>
                $row['customer_email']
        ],


        'event' => [

            'event_type' =>
                $row['event_type'],

            'event_date' =>
                $row['event_date'],

            'event_time' =>
                $row['event_time'],

            'guest_count' =>
                $row['guest_count'] !== null
                    ? (int)$row['guest_count']
                    : null,

            'venue_address' =>
                $row['venue_address'],

            'other_requirements' =>
                $row['other_requirements']
        ],


        'package' => [

            'id' =>
                $row['catering_package_id']
                !== null
                    ? (int)$row[
                        'catering_package_id'
                    ]
                    : null,

            'name' =>
                $row['catering_package_name'],

            'description' =>
                $row[
                    'catering_package_description'
                ],

            'rate' =>
                $row['package_rate']
                !== null
                    ? (float)$row[
                        'package_rate'
                    ]
                    : null,

            'rate_unit' =>
                $row['package_rate_unit'],

            'gst_rate' =>
                $row['package_gst_rate']
                !== null
                    ? (float)$row[
                        'package_gst_rate'
                    ]
                    : null,

            'price_includes_gst' =>
                $row[
                    'price_includes_gst'
                ] !== null
                    ? (bool)$row[
                        'price_includes_gst'
                    ]
                    : null,

            'minimum_quantity' =>
                $row[
                    'minimum_quantity'
                ] !== null
                    ? (int)$row[
                        'minimum_quantity'
                    ]
                    : null,

            'image_path' =>
                $row['package_image'],

            'status' =>
                $row['package_status'],

            'items' =>
                $packageItems
        ],


        'lead' => [

            'status' =>
                $row['status'],

            'source' =>
                $row['source'],

            'estimated_amount' =>
                $row['estimated_amount']
                !== null
                    ? (float)$row[
                        'estimated_amount'
                    ]
                    : null,

            'detail_estimated_amount' =>
                $row[
                    'detail_estimated_amount'
                ] !== null
                    ? (float)$row[
                        'detail_estimated_amount'
                    ]
                    : null,

            'converted_amount' =>
                $row['converted_amount']
                !== null
                    ? (float)$row[
                        'converted_amount'
                    ]
                    : null,

            'converted_at' =>
                $row['converted_at'],

            'requirement' =>
                $row['requirement']
        ],


        'assigned_admin' => [

            'id' =>
                $row['assigned_admin_id']
                !== null
                    ? (int)$row[
                        'assigned_admin_id'
                    ]
                    : null,

            'name' =>
                $row['assigned_admin_name'],

            'email' =>
                $row['assigned_admin_email']
        ],


        'custom_food_items' =>
            $customItems,


        'created_at' =>
            $row['created_at'],

        'updated_at' =>
            $row['updated_at']
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
        ) AS revenue,

        COALESCE(
            SUM(
                COALESCE(
                    cld.estimated_amount,
                    0
                )
            ),
            0
        ) AS estimated_value

    FROM leads l

    INNER JOIN catering_lead_details cld
        ON cld.lead_id = l.id

    LEFT JOIN catering_packages cp
        ON cp.id = cld.catering_package_id

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
            (float)$row['revenue'],

        'estimated_value' =>
            (float)$row[
                'estimated_value'
            ]
    ];
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Event Type Summary
|--------------------------------------------------------------------------
*/

$eventSql = "
    SELECT

        COALESCE(
            NULLIF(cld.event_type, ''),
            'Not Specified'
        ) AS event_type,

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
        ) AS revenue,

        COALESCE(
            SUM(
                COALESCE(
                    cld.guest_count,
                    0
                )
            ),
            0
        ) AS guests

    FROM leads l

    INNER JOIN catering_lead_details cld
        ON cld.lead_id = l.id

    LEFT JOIN catering_packages cp
        ON cp.id = cld.catering_package_id

    WHERE {$whereSql}

    GROUP BY
        COALESCE(
            NULLIF(cld.event_type, ''),
            'Not Specified'
        )

    ORDER BY total DESC
";


$stmt = $conn->prepare(
    $eventSql
);

if (!$stmt) {
    jsonResponse(
        false,
        'Failed to prepare event type summary.',
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

$eventResult =
    $stmt->get_result();

$eventSummary = [];

while (
    $row =
        $eventResult->fetch_assoc()
) {

    $total =
        (int)$row['total'];

    $converted =
        (int)$row['converted'];

    $eventSummary[] = [

        'event_type' =>
            $row['event_type'],

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

        'guests' =>
            (int)$row['guests'],

        'converted_revenue' =>
            (float)$row['revenue']
    ];
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Package Performance
|--------------------------------------------------------------------------
|
| Pre-aggregate leads by package.
| This avoids revenue multiplication from package-item joins.
|--------------------------------------------------------------------------
*/

$packageWhere = [
    "l.domain = 'catering'"
];

$packageParams = [];
$packageTypes = '';


if ($currentUserRole === 'staff') {

    $packageWhere[] =
        "l.assigned_admin_id = ?";

    $packageParams[] =
        $currentUserId;

    $packageTypes .= 'i';
}


if ($fromDate !== null) {

    $packageWhere[] =
        "DATE(l.created_at) >= ?";

    $packageParams[] =
        $fromDate;

    $packageTypes .= 's';
}

if ($toDate !== null) {

    $packageWhere[] =
        "DATE(l.created_at) <= ?";

    $packageParams[] =
        $toDate;

    $packageTypes .= 's';
}


if ($eventDateFrom !== null) {

    $packageWhere[] =
        "cld.event_date >= ?";

    $packageParams[] =
        $eventDateFrom;

    $packageTypes .= 's';
}

if ($eventDateTo !== null) {

    $packageWhere[] =
        "cld.event_date <= ?";

    $packageParams[] =
        $eventDateTo;

    $packageTypes .= 's';
}


if ($leadStatus !== 'all') {

    $packageWhere[] =
        "l.status = ?";

    $packageParams[] =
        $leadStatus;

    $packageTypes .= 's';
}


if ($eventType !== 'all') {

    $packageWhere[] =
        "cld.event_type = ?";

    $packageParams[] =
        $eventType;

    $packageTypes .= 's';
}


if ($minGuests > 0) {

    $packageWhere[] =
        "COALESCE(cld.guest_count, 0) >= ?";

    $packageParams[] =
        $minGuests;

    $packageTypes .= 'i';
}


if ($maxGuests > 0) {

    $packageWhere[] =
        "COALESCE(cld.guest_count, 0) <= ?";

    $packageParams[] =
        $maxGuests;

    $packageTypes .= 'i';
}


if ($minAmount !== null) {

    $packageWhere[] = "
        GREATEST(
            COALESCE(
                cld.estimated_amount,
                0
            ),
            COALESCE(
                l.converted_amount,
                0
            )
        ) >= ?
    ";

    $packageParams[] =
        $minAmount;

    $packageTypes .= 'd';
}


if ($maxAmount !== null) {

    $packageWhere[] = "
        GREATEST(
            COALESCE(
                cld.estimated_amount,
                0
            ),
            COALESCE(
                l.converted_amount,
                0
            )
        ) <= ?
    ";

    $packageParams[] =
        $maxAmount;

    $packageTypes .= 'd';
}


$packageWhereSql =
    implode(
        ' AND ',
        $packageWhere
    );


$packageSql = "
    SELECT

        cp.id AS package_id,
        cp.name AS package_name,
        cp.rate,
        cp.rate_unit,
        cp.gst_rate,
        cp.price_includes_gst,
        cp.minimum_quantity,
        cp.status AS package_status,

        COALESCE(stats.total_leads, 0)
            AS total_leads,

        COALESCE(stats.converted_leads, 0)
            AS converted_leads,

        COALESCE(stats.pending_leads, 0)
            AS pending_leads,

        COALESCE(
            stats.converted_revenue,
            0
        ) AS converted_revenue,

        COALESCE(
            stats.estimated_value,
            0
        ) AS estimated_value,

        COALESCE(
            stats.total_guests,
            0
        ) AS total_guests

    FROM catering_packages cp

    LEFT JOIN (

        SELECT

            cld.catering_package_id
                AS package_id,

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
                        cld.estimated_amount,
                        0
                    )
                ),
                0
            ) AS estimated_value,

            COALESCE(
                SUM(
                    COALESCE(
                        cld.guest_count,
                        0
                    )
                ),
                0
            ) AS total_guests

        FROM leads l

        INNER JOIN catering_lead_details cld
            ON cld.lead_id = l.id

        WHERE {$packageWhereSql}

        GROUP BY
            cld.catering_package_id

    ) stats
        ON stats.package_id = cp.id

    " . (
        $cateringPackageId > 0
            ? "WHERE cp.id = ?"
            : ""
    ) . "

    ORDER BY
        COALESCE(
            stats.converted_revenue,
            0
        ) DESC,

        COALESCE(
            stats.total_leads,
            0
        ) DESC
";


$finalPackageParams =
    $packageParams;

$finalPackageTypes =
    $packageTypes;


if ($cateringPackageId > 0) {

    $finalPackageParams[] =
        $cateringPackageId;

    $finalPackageTypes .= 'i';
}


$stmt = $conn->prepare(
    $packageSql
);

if (!$stmt) {
    jsonResponse(
        false,
        'Failed to prepare package performance.',
        [],
        500
    );
}

if (!empty($finalPackageParams)) {

    $stmt->bind_param(
        $finalPackageTypes,
        ...$finalPackageParams
    );
}

$stmt->execute();

$packageResult =
    $stmt->get_result();

$packagePerformance = [];

while (
    $row =
        $packageResult->fetch_assoc()
) {

    $total =
        (int)$row['total_leads'];

    $converted =
        (int)$row['converted_leads'];

    $packagePerformance[] = [

        'package_id' =>
            (int)$row['package_id'],

        'package_name' =>
            $row['package_name'],

        'rate' =>
            (float)$row['rate'],

        'rate_unit' =>
            $row['rate_unit'],

        'gst_rate' =>
            (float)$row['gst_rate'],

        'price_includes_gst' =>
            (bool)$row[
                'price_includes_gst'
            ],

        'minimum_quantity' =>
            (int)$row[
                'minimum_quantity'
            ],

        'status' =>
            $row['package_status'],

        'total_leads' =>
            $total,

        'converted_leads' =>
            $converted,

        'pending_leads' =>
            (int)$row['pending_leads'],

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
            ],

        'total_guests' =>
            (int)$row[
                'total_guests'
            ]
    ];
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Monthly Report
|--------------------------------------------------------------------------
*/

$monthlySql = "
    SELECT

        DATE_FORMAT(
            cld.event_date,
            '%Y-%m'
        ) AS month,

        COUNT(*) AS total_leads,

        COUNT(
            CASE
                WHEN l.status = 'converted'
                THEN 1
            END
        ) AS converted_leads,

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
                    cld.estimated_amount,
                    0
                )
            ),
            0
        ) AS estimated_value,

        COALESCE(
            SUM(
                COALESCE(
                    cld.guest_count,
                    0
                )
            ),
            0
        ) AS total_guests

    FROM leads l

    INNER JOIN catering_lead_details cld
        ON cld.lead_id = l.id

    LEFT JOIN catering_packages cp
        ON cp.id = cld.catering_package_id

    WHERE {$whereSql}

    AND cld.event_date IS NOT NULL

    GROUP BY
        DATE_FORMAT(
            cld.event_date,
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
            ],

        'total_guests' =>
            (int)$row[
                'total_guests'
            ]
    ];
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Upcoming Catering
|--------------------------------------------------------------------------
*/

$today =
    date('Y-m-d');

$upcomingWhere = [
    "l.domain = 'catering'",
    "cld.event_date >= ?",
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


$upcomingSql = "
    SELECT

        l.id AS lead_id,
        l.lead_reference,

        l.customer_name,
        l.customer_phone,
        l.customer_email,

        l.status,

        l.estimated_amount,
        l.converted_amount,

        cld.event_type,
        cld.event_date,
        cld.event_time,
        cld.guest_count,
        cld.venue_address,

        cp.name AS catering_package_name

    FROM leads l

    INNER JOIN catering_lead_details cld
        ON cld.lead_id = l.id

    LEFT JOIN catering_packages cp
        ON cp.id = cld.catering_package_id

    WHERE " .
    implode(
        ' AND ',
        $upcomingWhere
    ) . "

    ORDER BY
        cld.event_date ASC,
        cld.event_time ASC

    LIMIT 20
";


$stmt = $conn->prepare(
    $upcomingSql
);

if (!$stmt) {
    jsonResponse(
        false,
        'Failed to prepare upcoming catering query.',
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

$upcomingCatering = [];

while (
    $row =
        $upcomingResult->fetch_assoc()
) {

    $upcomingCatering[] = [

        'lead_id' =>
            (int)$row['lead_id'],

        'lead_reference' =>
            $row['lead_reference'],

        'customer_name' =>
            $row['customer_name'],

        'customer_phone' =>
            $row['customer_phone'],

        'customer_email' =>
            $row['customer_email'],

        'status' =>
            $row['status'],

        'event_type' =>
            $row['event_type'],

        'event_date' =>
            $row['event_date'],

        'event_time' =>
            $row['event_time'],

        'guest_count' =>
            $row['guest_count'] !== null
                ? (int)$row[
                    'guest_count'
                ]
                : null,

        'venue_address' =>
            $row['venue_address'],

        'catering_package_name' =>
            $row[
                'catering_package_name'
            ],

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
| Today's Catering Summary
|--------------------------------------------------------------------------
*/

$todayWhere = [
    "l.domain = 'catering'",
    "cld.event_date = ?"
];

$todayParams = [
    $today
];

$todayTypes = 's';


if ($currentUserRole === 'staff') {

    $todayWhere[] =
        "l.assigned_admin_id = ?";

    $todayParams[] =
        $currentUserId;

    $todayTypes .= 'i';
}


$todaySql = "
    SELECT

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
        ) AS revenue,

        COALESCE(
            SUM(
                COALESCE(
                    cld.guest_count,
                    0
                )
            ),
            0
        ) AS guests

    FROM leads l

    INNER JOIN catering_lead_details cld
        ON cld.lead_id = l.id

    WHERE " .
    implode(
        ' AND ',
        $todayWhere
    );


$stmt = $conn->prepare(
    $todaySql
);

if (!$stmt) {
    jsonResponse(
        false,
        'Failed to prepare today summary.',
        [],
        500
    );
}

$stmt->bind_param(
    $todayTypes,
    ...$todayParams
);

$stmt->execute();

$todaySummary =
    $stmt->get_result()
        ->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| Food Item Usage
|--------------------------------------------------------------------------
*/

$foodWhere = [
    "l.domain = 'catering'"
];

$foodParams = [];
$foodTypes = '';


if ($currentUserRole === 'staff') {

    $foodWhere[] =
        "l.assigned_admin_id = ?";

    $foodParams[] =
        $currentUserId;

    $foodTypes .= 'i';
}


if ($fromDate !== null) {

    $foodWhere[] =
        "DATE(l.created_at) >= ?";

    $foodParams[] =
        $fromDate;

    $foodTypes .= 's';
}


if ($toDate !== null) {

    $foodWhere[] =
        "DATE(l.created_at) <= ?";

    $foodParams[] =
        $toDate;

    $foodTypes .= 's';
}


if ($eventDateFrom !== null) {

    $foodWhere[] =
        "cld.event_date >= ?";

    $foodParams[] =
        $eventDateFrom;

    $foodTypes .= 's';
}


if ($eventDateTo !== null) {

    $foodWhere[] =
        "cld.event_date <= ?";

    $foodParams[] =
        $eventDateTo;

    $foodTypes .= 's';
}


$foodWhereSql =
    implode(
        ' AND ',
        $foodWhere
    );


$foodSql = "
    SELECT

        fi.id AS food_item_id,
        fi.name AS food_item_name,
        fi.food_type,

        COUNT(
            DISTINCT cli.lead_id
        ) AS lead_count,

        COALESCE(
            SUM(cli.quantity),
            0
        ) AS total_quantity

    FROM catering_lead_items cli

    INNER JOIN food_items fi
        ON fi.id = cli.food_item_id

    INNER JOIN leads l
        ON l.id = cli.lead_id

    INNER JOIN catering_lead_details cld
        ON cld.lead_id = l.id

    WHERE {$foodWhereSql}

    GROUP BY
        fi.id,
        fi.name,
        fi.food_type

    ORDER BY
        total_quantity DESC

    LIMIT 20
";


$stmt = $conn->prepare(
    $foodSql
);

if (!$stmt) {
    jsonResponse(
        false,
        'Failed to prepare food usage query.',
        [],
        500
    );
}

if (!empty($foodParams)) {
    $stmt->bind_param(
        $foodTypes,
        ...$foodParams
    );
}

$stmt->execute();

$foodResult =
    $stmt->get_result();

$foodUsage = [];

while (
    $row =
        $foodResult->fetch_assoc()
) {

    $foodUsage[] = [

        'food_item_id' =>
            (int)$row[
                'food_item_id'
            ],

        'food_item_name' =>
            $row[
                'food_item_name'
            ],

        'food_type' =>
            $row['food_type'],

        'lead_count' =>
            (int)$row['lead_count'],

        'total_quantity' =>
            (int)$row[
                'total_quantity'
            ]
    ];
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Chart.js Data
|--------------------------------------------------------------------------
*/

$monthlyLabels = [];
$monthlyRevenue = [];
$monthlyEstimated = [];
$monthlyLeads = [];
$monthlyGuests = [];

foreach (
    $monthlyReport as $row
) {

    $monthlyLabels[] =
        $row['month'];

    $monthlyRevenue[] =
        $row['converted_revenue'];

    $monthlyEstimated[] =
        $row['estimated_value'];

    $monthlyLeads[] =
        $row['total_leads'];

    $monthlyGuests[] =
        $row['total_guests'];
}


$statusLabels = [];
$statusLeads = [];
$statusRevenue = [];

foreach (
    $statusSummary as $row
) {

    $statusLabels[] =
        ucfirst(
            str_replace(
                '_',
                ' ',
                $row['status']
            )
        );

    $statusLeads[] =
        $row['total'];

    $statusRevenue[] =
        $row['converted_revenue'];
}


$eventLabels = [];
$eventLeads = [];
$eventRevenue = [];

foreach (
    $eventSummary as $row
) {

    $eventLabels[] =
        $row['event_type'];

    $eventLeads[] =
        $row['total_leads'];

    $eventRevenue[] =
        $row['converted_revenue'];
}


$packageLabels = [];
$packageRevenue = [];
$packageLeads = [];

foreach (
    $packagePerformance as $row
) {

    $packageLabels[] =
        $row['package_name'];

    $packageRevenue[] =
        $row['converted_revenue'];

    $packageLeads[] =
        $row['total_leads'];
}


/*
|--------------------------------------------------------------------------
| Final Response
|--------------------------------------------------------------------------
*/

jsonResponse(
    true,
    'Catering report fetched successfully.',
    [

        'meta' => [

            'report' =>
                'catering_report',

            'generated_at' =>
                date('Y-m-d H:i:s'),

            'currency' =>
                'INR',

            'revenue_source' =>
                'converted_amount from converted catering leads',

            'estimated_source' =>
                'catering_lead_details.estimated_amount',

            'package_rate_source' =>
                'catering_packages.rate'
        ],


        'user_scope' => [

            'role' =>
                $currentUserRole,

            'user_id' =>
                $currentUserId,

            'scope' =>
                $currentUserRole === 'staff'
                    ? 'assigned catering leads only'
                    : 'all catering leads'
        ],


        'filters' => [

            'lead_id' =>
                $leadId > 0
                    ? $leadId
                    : null,

            'catering_package_id' =>
                $cateringPackageId > 0
                    ? $cateringPackageId
                    : null,

            'food_item_id' =>
                $foodItemId > 0
                    ? $foodItemId
                    : null,

            'lead_status' =>
                $leadStatus,

            'event_type' =>
                $eventType,

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

            'page' =>
                $page,

            'limit' =>
                $limit,

            'sort_by' =>
                $sortBy,

            'sort_order' =>
                $sortOrder
        ],


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


        'today' => [

            'date' =>
                $today,

            'total_catering' =>
                (int)(
                    $todaySummary['total']
                    ?? 0
                ),

            'converted_catering' =>
                (int)(
                    $todaySummary['converted']
                    ?? 0
                ),

            'pending_catering' =>
                (int)(
                    $todaySummary['pending']
                    ?? 0
                ),

            'revenue' =>
                (float)(
                    $todaySummary['revenue']
                    ?? 0
                ),

            'guests' =>
                (int)(
                    $todaySummary['guests']
                    ?? 0
                )
        ],


        'status_summary' =>
            $statusSummary,


        'event_type_summary' =>
            $eventSummary,


        'package_performance' =>
            $packagePerformance,


        'food_item_usage' =>
            $foodUsage,


        'monthly_report' =>
            $monthlyReport,


        'upcoming_catering' =>
            $upcomingCatering,


        'records' =>
            $records,


        'pagination' => [

            'page' =>
                $page,

            'limit' =>
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
        | Chart.js Ready
        |--------------------------------------------------------------------------
        */

        'charts' => [

            'revenue_trend' => [

                'labels' =>
                    $monthlyLabels,

                'datasets' => [

                    [
                        'label' =>
                            'Catering Converted Revenue',

                        'data' =>
                            $monthlyRevenue
                    ],

                    [
                        'label' =>
                            'Catering Estimated Value',

                        'data' =>
                            $monthlyEstimated
                    ]
                ]
            ],


            'lead_trend' => [

                'labels' =>
                    $monthlyLabels,

                'datasets' => [

                    [
                        'label' =>
                            'Catering Leads',

                        'data' =>
                            $monthlyLeads
                    ]
                ]
            ],


            'guest_trend' => [

                'labels' =>
                    $monthlyLabels,

                'datasets' => [

                    [
                        'label' =>
                            'Catering Guests',

                        'data' =>
                            $monthlyGuests
                    ]
                ]
            ],


            'lead_status' => [

                'labels' =>
                    $statusLabels,

                'datasets' => [

                    [
                        'label' =>
                            'Catering Leads',

                        'data' =>
                            $statusLeads
                    ]
                ]
            ],


            'status_revenue' => [

                'labels' =>
                    $statusLabels,

                'datasets' => [

                    [
                        'label' =>
                            'Converted Revenue',

                        'data' =>
                            $statusRevenue
                    ]
                ]
            ],


            'event_type' => [

                'labels' =>
                    $eventLabels,

                'datasets' => [

                    [
                        'label' =>
                            'Catering Leads',

                        'data' =>
                            $eventLeads
                    ]
                ]
            ],


            'event_revenue' => [

                'labels' =>
                    $eventLabels,

                'datasets' => [

                    [
                        'label' =>
                            'Converted Revenue',

                        'data' =>
                            $eventRevenue
                    ]
                ]
            ],


            'package_revenue' => [

                'labels' =>
                    $packageLabels,

                'datasets' => [

                    [
                        'label' =>
                            'Package Revenue',

                        'data' =>
                            $packageRevenue
                    ]
                ]
            ],


            'package_leads' => [

                'labels' =>
                    $packageLabels,

                'datasets' => [

                    [
                        'label' =>
                            'Package Leads',

                        'data' =>
                            $packageLeads
                    ]
                ]
            ]
        ]
    ]
);  