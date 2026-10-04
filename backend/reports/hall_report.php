<?php

/**
 * SM GROUP
 * Hall Report API
 *
 * File:
 * reports/hall_report.php
 *
 * Method:
 * GET
 *
 * Authentication:
 * Admin / Staff JWT
 *
 * Report Includes:
 * ------------------------------------------------------------
 * 1. Hall inventory
 * 2. Hall type summary
 * 3. Hall status summary
 * 4. Hall lead summary
 * 5. Converted hall revenue
 * 6. Hall performance
 * 7. Monthly hall revenue
 * 8. Monthly lead trend
 * 9. Upcoming hall events
 * 10. Detailed hall leads
 * 11. Chart.js ready data
 *
 * Revenue Definition:
 * ------------------------------------------------------------
 * Hall revenue = converted hall lead converted_amount
 */

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| CORS
|--------------------------------------------------------------------------
*/

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");


/*
|--------------------------------------------------------------------------
| OPTIONS / PREFLIGHT
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {

    http_response_code(200);

    exit;
}


/*
|--------------------------------------------------------------------------
| Method Validation
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {

    http_response_code(405);

    echo json_encode([
        'status' => false,
        'message' => 'Only GET method is allowed.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Includes
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../config/middleware.php';


/*
|--------------------------------------------------------------------------
| Response Function
|--------------------------------------------------------------------------
*/

function sendResponse(
    bool $status,
    string $message,
    array $data = [],
    int $httpCode = 200
): void {

    http_response_code($httpCode);

    echo json_encode(
        [
            'status' => $status,
            'message' => $message,
            'data' => $data
        ],
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_PRESERVE_ZERO_FRACTION
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

$currentUser = requireAdminOrStaff();


/*
|--------------------------------------------------------------------------
| Current User
|--------------------------------------------------------------------------
*/

$currentUserId =
    $currentUser['sub']
    ?? $currentUser['id']
    ?? null;


$currentUserRole =
    strtolower(
        trim(
            (string)(
                $currentUser['role']
                ?? ''
            )
        )
    );


/*
|--------------------------------------------------------------------------
| Role Validation
|--------------------------------------------------------------------------
*/

if (
    !in_array(
        $currentUserRole,
        ['admin', 'staff'],
        true
    )
) {

    sendResponse(
        false,
        'You are not authorized to access the hall report.',
        [],
        403
    );
}


/*
|--------------------------------------------------------------------------
| Database Validation
|--------------------------------------------------------------------------
*/

if (
    !isset($conn) ||
    !($conn instanceof mysqli)
) {

    sendResponse(
        false,
        'Database connection is not available.',
        [],
        500
    );
}


/*
|--------------------------------------------------------------------------
| Helper - Date Validation
|--------------------------------------------------------------------------
*/

function isValidDate(string $date): bool
{
    $dateObject =
        DateTime::createFromFormat(
            'Y-m-d',
            $date
        );

    return
        $dateObject !== false &&
        $dateObject->format('Y-m-d') === $date;
}


/*
|--------------------------------------------------------------------------
| Helper - Dynamic Bind
|--------------------------------------------------------------------------
*/

function bindDynamicParams(
    mysqli_stmt $stmt,
    string $types,
    array &$params
): void {

    if (
        $types === '' ||
        empty($params)
    ) {
        return;
    }

    $bindParams = [];

    $bindParams[] = $types;

    foreach ($params as $key => $value) {

        $bindParams[] =
            &$params[$key];
    }

    call_user_func_array(
        [$stmt, 'bind_param'],
        $bindParams
    );
}


/*
|--------------------------------------------------------------------------
| QUERY PARAMETERS
|--------------------------------------------------------------------------
*/

$hallIdRaw =
    trim(
        (string)(
            $_GET['hall_id']
            ?? ''
        )
    );


$hallTypeIdRaw =
    trim(
        (string)(
            $_GET['hall_type_id']
            ?? ''
        )
    );


$hallStatus =
    strtolower(
        trim(
            (string)(
                $_GET['hall_status']
                ?? 'all'
            )
        )
    );


$leadStatus =
    strtolower(
        trim(
            (string)(
                $_GET['lead_status']
                ?? 'all'
            )
        )
    );


$fromDate =
    trim(
        (string)(
            $_GET['from_date']
            ?? ''
        )
    );


$toDate =
    trim(
        (string)(
            $_GET['to_date']
            ?? ''
        )
    );


$search =
    trim(
        (string)(
            $_GET['search']
            ?? ''
        )
    );


$minGuestsRaw =
    trim(
        (string)(
            $_GET['min_guests']
            ?? ''
        )
    );


$maxGuestsRaw =
    trim(
        (string)(
            $_GET['max_guests']
            ?? ''
        )
    );


$minAmountRaw =
    trim(
        (string)(
            $_GET['min_amount']
            ?? ''
        )
    );


$maxAmountRaw =
    trim(
        (string)(
            $_GET['max_amount']
            ?? ''
        )
    );


$pageRaw =
    trim(
        (string)(
            $_GET['page']
            ?? '1'
        )
    );


$limitRaw =
    trim(
        (string)(
            $_GET['limit']
            ?? '20'
        )
    );


$sortBy =
    strtolower(
        trim(
            (string)(
                $_GET['sort_by']
                ?? 'created_at'
            )
        )
    );


$sortOrder =
    strtoupper(
        trim(
            (string)(
                $_GET['sort_order']
                ?? 'DESC'
            )
        )
    );


/*
|--------------------------------------------------------------------------
| Allowed Hall Status
|--------------------------------------------------------------------------
*/

$allowedHallStatuses = [
    'all',
    'available',
    'inactive',
    'maintenance'
];


/*
|--------------------------------------------------------------------------
| Allowed Lead Status
|--------------------------------------------------------------------------
*/

$allowedLeadStatuses = [
    'all',
    'new',
    'contacted',
    'follow_up',
    'converted',
    'lost',
    'cancelled'
];


/*
|--------------------------------------------------------------------------
| Allowed Sort Fields
|--------------------------------------------------------------------------
*/

$allowedSortFields = [
    'created_at',
    'event_date',
    'guest_count',
    'converted_amount',
    'customer_name',
    'lead_reference'
];


/*
|--------------------------------------------------------------------------
| Allowed Sort Order
|--------------------------------------------------------------------------
*/

$allowedSortOrders = [
    'ASC',
    'DESC'
];


/*
|--------------------------------------------------------------------------
| Hall Status Validation
|--------------------------------------------------------------------------
*/

if (
    !in_array(
        $hallStatus,
        $allowedHallStatuses,
        true
    )
) {

    sendResponse(
        false,
        'Invalid hall_status.',
        [
            'allowed_values' =>
                $allowedHallStatuses
        ],
        422
    );
}


/*
|--------------------------------------------------------------------------
| Lead Status Validation
|--------------------------------------------------------------------------
*/

if (
    !in_array(
        $leadStatus,
        $allowedLeadStatuses,
        true
    )
) {

    sendResponse(
        false,
        'Invalid lead_status.',
        [
            'allowed_values' =>
                $allowedLeadStatuses
        ],
        422
    );
}


/*
|--------------------------------------------------------------------------
| Hall ID Validation
|--------------------------------------------------------------------------
*/

$hallId = null;

if ($hallIdRaw !== '') {

    if (
        !ctype_digit($hallIdRaw) ||
        (int)$hallIdRaw < 1
    ) {

        sendResponse(
            false,
            'hall_id must be a positive integer.',
            [],
            422
        );
    }

    $hallId =
        (int)$hallIdRaw;
}


/*
|--------------------------------------------------------------------------
| Hall Type ID Validation
|--------------------------------------------------------------------------
*/

$hallTypeId = null;

if ($hallTypeIdRaw !== '') {

    if (
        !ctype_digit($hallTypeIdRaw) ||
        (int)$hallTypeIdRaw < 1
    ) {

        sendResponse(
            false,
            'hall_type_id must be a positive integer.',
            [],
            422
        );
    }

    $hallTypeId =
        (int)$hallTypeIdRaw;
}


/*
|--------------------------------------------------------------------------
| Verify Hall Exists
|--------------------------------------------------------------------------
*/

if ($hallId !== null) {

    $stmt =
        $conn->prepare(
            "SELECT id
             FROM halls
             WHERE id = ?
             LIMIT 1"
        );


    if (!$stmt) {

        sendResponse(
            false,
            'Failed to validate hall_id.',
            [
                'error' => $conn->error
            ],
            500
        );
    }


    $stmt->bind_param(
        'i',
        $hallId
    );


    if (!$stmt->execute()) {

        $error =
            $stmt->error;

        $stmt->close();

        sendResponse(
            false,
            'Failed to validate hall_id.',
            [
                'error' => $error
            ],
            500
        );
    }


    $result =
        $stmt->get_result();


    if (!$result->fetch_assoc()) {

        $stmt->close();

        sendResponse(
            false,
            'Hall not found.',
            [],
            404
        );
    }


    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Verify Hall Type Exists
|--------------------------------------------------------------------------
*/

if ($hallTypeId !== null) {

    $stmt =
        $conn->prepare(
            "SELECT id
             FROM hall_types
             WHERE id = ?
             LIMIT 1"
        );


    if (!$stmt) {

        sendResponse(
            false,
            'Failed to validate hall_type_id.',
            [
                'error' => $conn->error
            ],
            500
        );
    }


    $stmt->bind_param(
        'i',
        $hallTypeId
    );


    if (!$stmt->execute()) {

        $error =
            $stmt->error;

        $stmt->close();

        sendResponse(
            false,
            'Failed to validate hall_type_id.',
            [
                'error' => $error
            ],
            500
        );
    }


    $result =
        $stmt->get_result();


    if (!$result->fetch_assoc()) {

        $stmt->close();

        sendResponse(
            false,
            'Hall type not found.',
            [],
            404
        );
    }


    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Date Validation
|--------------------------------------------------------------------------
*/

if ($fromDate !== '') {

    if (!isValidDate($fromDate)) {

        sendResponse(
            false,
            'Invalid from_date. Expected format: YYYY-MM-DD.',
            [],
            422
        );
    }
}


if ($toDate !== '') {

    if (!isValidDate($toDate)) {

        sendResponse(
            false,
            'Invalid to_date. Expected format: YYYY-MM-DD.',
            [],
            422
        );
    }
}


if (
    $fromDate !== '' &&
    $toDate !== '' &&
    $fromDate > $toDate
) {

    sendResponse(
        false,
        'from_date cannot be greater than to_date.',
        [],
        422
    );
}


/*
|--------------------------------------------------------------------------
| SQL Date Values
|--------------------------------------------------------------------------
*/

$fromDateSql = null;
$toDateSql = null;


if ($fromDate !== '') {

    $fromDateSql =
        $fromDate . ' 00:00:00';
}


if ($toDate !== '') {

    $toDateSql =
        $toDate . ' 23:59:59';
}


/*
|--------------------------------------------------------------------------
| Search Validation
|--------------------------------------------------------------------------
*/

if (
    mb_strlen($search) > 100
) {

    sendResponse(
        false,
        'Search cannot exceed 100 characters.',
        [],
        422
    );
}


/*
|--------------------------------------------------------------------------
| Guest Validation
|--------------------------------------------------------------------------
*/

$minGuests = null;
$maxGuests = null;


if ($minGuestsRaw !== '') {

    if (
        !ctype_digit($minGuestsRaw)
    ) {

        sendResponse(
            false,
            'min_guests must be a positive integer.',
            [],
            422
        );
    }

    $minGuests =
        (int)$minGuestsRaw;


    if ($minGuests < 1) {

        sendResponse(
            false,
            'min_guests must be at least 1.',
            [],
            422
        );
    }
}


if ($maxGuestsRaw !== '') {

    if (
        !ctype_digit($maxGuestsRaw)
    ) {

        sendResponse(
            false,
            'max_guests must be a positive integer.',
            [],
            422
        );
    }

    $maxGuests =
        (int)$maxGuestsRaw;


    if ($maxGuests < 1) {

        sendResponse(
            false,
            'max_guests must be at least 1.',
            [],
            422
        );
    }
}


if (
    $minGuests !== null &&
    $maxGuests !== null &&
    $minGuests > $maxGuests
) {

    sendResponse(
        false,
        'min_guests cannot be greater than max_guests.',
        [],
        422
    );
}


/*
|--------------------------------------------------------------------------
| Amount Validation
|--------------------------------------------------------------------------
*/

$minAmount = null;
$maxAmount = null;


if ($minAmountRaw !== '') {

    if (
        !preg_match(
            '/^\d{1,9}(\.\d{1,2})?$/',
            $minAmountRaw
        )
    ) {

        sendResponse(
            false,
            'Invalid min_amount.',
            [],
            422
        );
    }


    $minAmount =
        (float)$minAmountRaw;


    if ($minAmount < 0) {

        sendResponse(
            false,
            'min_amount cannot be negative.',
            [],
            422
        );
    }
}


if ($maxAmountRaw !== '') {

    if (
        !preg_match(
            '/^\d{1,9}(\.\d{1,2})?$/',
            $maxAmountRaw
        )
    ) {

        sendResponse(
            false,
            'Invalid max_amount.',
            [],
            422
        );
    }


    $maxAmount =
        (float)$maxAmountRaw;


    if ($maxAmount < 0) {

        sendResponse(
            false,
            'max_amount cannot be negative.',
            [],
            422
        );
    }
}


if (
    $minAmount !== null &&
    $maxAmount !== null &&
    $minAmount > $maxAmount
) {

    sendResponse(
        false,
        'min_amount cannot be greater than max_amount.',
        [],
        422
    );
}


/*
|--------------------------------------------------------------------------
| Pagination Validation
|--------------------------------------------------------------------------
*/

if (
    !ctype_digit($pageRaw) ||
    (int)$pageRaw < 1
) {

    sendResponse(
        false,
        'page must be a positive integer.',
        [],
        422
    );
}


$page =
    (int)$pageRaw;


if (
    !ctype_digit($limitRaw) ||
    (int)$limitRaw < 1
) {

    sendResponse(
        false,
        'limit must be a positive integer.',
        [],
        422
    );
}


$limit =
    (int)$limitRaw;


$maxLimit = 100;


if ($limit > $maxLimit) {

    sendResponse(
        false,
        "limit cannot exceed {$maxLimit}.",
        [
            'max_limit' =>
                $maxLimit
        ],
        422
    );
}


/*
|--------------------------------------------------------------------------
| Sort Validation
|--------------------------------------------------------------------------
*/

if (
    !in_array(
        $sortBy,
        $allowedSortFields,
        true
    )
) {

    sendResponse(
        false,
        'Invalid sort_by.',
        [
            'allowed_values' =>
                $allowedSortFields
        ],
        422
    );
}


if (
    !in_array(
        $sortOrder,
        $allowedSortOrders,
        true
    )
) {

    sendResponse(
        false,
        'Invalid sort_order.',
        [
            'allowed_values' =>
                $allowedSortOrders
        ],
        422
    );
}


/*
|--------------------------------------------------------------------------
| Base Hall Conditions
|--------------------------------------------------------------------------
*/

$hallWhere = [
    '1 = 1'
];

$hallParams = [];
$hallTypes = '';


if ($hallId !== null) {

    $hallWhere[] =
        'h.id = ?';

    $hallParams[] =
        $hallId;

    $hallTypes .= 'i';
}


if ($hallTypeId !== null) {

    $hallWhere[] =
        'h.hall_type_id = ?';

    $hallParams[] =
        $hallTypeId;

    $hallTypes .= 'i';
}


if ($hallStatus !== 'all') {

    $hallWhere[] =
        'h.status = ?';

    $hallParams[] =
        $hallStatus;

    $hallTypes .= 's';
}


if ($search !== '') {

    $hallWhere[] = "
        (
            h.name LIKE ?
            OR h.slug LIKE ?
            OR ht.name LIKE ?
            OR h.description LIKE ?
        )
    ";

    $searchValue =
        '%' . $search . '%';

    $hallParams[] =
        $searchValue;

    $hallParams[] =
        $searchValue;

    $hallParams[] =
        $searchValue;

    $hallParams[] =
        $searchValue;

    $hallTypes .= 'ssss';
}


$hallWhereSql =
    implode(
        ' AND ',
        $hallWhere
    );


/*
|--------------------------------------------------------------------------
| HALL INVENTORY SUMMARY
|--------------------------------------------------------------------------
*/

$inventorySql = "
    SELECT

        COUNT(*) AS total_halls,

        COALESCE(
            SUM(
                CASE
                    WHEN h.status = 'available'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS available_halls,

        COALESCE(
            SUM(
                CASE
                    WHEN h.status = 'maintenance'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS maintenance_halls,

        COALESCE(
            SUM(
                CASE
                    WHEN h.status = 'inactive'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS inactive_halls,

        COALESCE(
            SUM(h.capacity),
            0
        ) AS total_capacity

    FROM halls h

    LEFT JOIN hall_types ht
        ON ht.id = h.hall_type_id

    WHERE {$hallWhereSql}
";


$stmt =
    $conn->prepare(
        $inventorySql
    );


if (!$stmt) {

    sendResponse(
        false,
        'Failed to prepare hall inventory query.',
        [
            'error' => $conn->error
        ],
        500
    );
}


if (!empty($hallParams)) {

    bindDynamicParams(
        $stmt,
        $hallTypes,
        $hallParams
    );
}


if (!$stmt->execute()) {

    $error =
        $stmt->error;

    $stmt->close();

    sendResponse(
        false,
        'Failed to fetch hall inventory.',
        [
            'error' => $error
        ],
        500
    );
}


$result =
    $stmt->get_result();


$inventory =
    $result->fetch_assoc()
    ?: [];


$stmt->close();


$inventory = [

    'total_halls' =>
        (int)(
            $inventory['total_halls']
            ?? 0
        ),

    'available_halls' =>
        (int)(
            $inventory['available_halls']
            ?? 0
        ),

    'maintenance_halls' =>
        (int)(
            $inventory['maintenance_halls']
            ?? 0
        ),

    'inactive_halls' =>
        (int)(
            $inventory['inactive_halls']
            ?? 0
        ),

    'total_capacity' =>
        (int)(
            $inventory['total_capacity']
            ?? 0
        )
];


/*
|--------------------------------------------------------------------------
| Hall Type Summary
|--------------------------------------------------------------------------
*/

$hallTypeSql = "
    SELECT

        ht.id,

        ht.name,

        ht.slug,

        COUNT(h.id) AS hall_count,

        COALESCE(
            SUM(
                CASE
                    WHEN h.status = 'available'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS available_count,

        COALESCE(
            SUM(h.capacity),
            0
        ) AS total_capacity

    FROM hall_types ht

    LEFT JOIN halls h
        ON h.hall_type_id = ht.id

    WHERE
        ht.status = 'active'

    GROUP BY
        ht.id,
        ht.name,
        ht.slug

    ORDER BY
        ht.name ASC
";


$stmt =
    $conn->prepare(
        $hallTypeSql
    );


if (!$stmt) {

    sendResponse(
        false,
        'Failed to prepare hall type summary.',
        [
            'error' => $conn->error
        ],
        500
    );
}


if (!$stmt->execute()) {

    $error =
        $stmt->error;

    $stmt->close();

    sendResponse(
        false,
        'Failed to fetch hall type summary.',
        [
            'error' => $error
        ],
        500
    );
}


$result =
    $stmt->get_result();


$hallTypesSummary = [];


while (
    $row =
        $result->fetch_assoc()
) {

    $hallTypesSummary[] = [

        'id' =>
            (int)$row['id'],

        'name' =>
            $row['name'],

        'slug' =>
            $row['slug'],

        'hall_count' =>
            (int)$row['hall_count'],

        'available_count' =>
            (int)$row['available_count'],

        'total_capacity' =>
            (int)$row['total_capacity']
    ];
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| Hall Leads Conditions
|--------------------------------------------------------------------------
*/

$leadWhere = [
    "l.domain = 'hall'"
];

$leadParams = [];
$leadTypes = '';


/*
|--------------------------------------------------------------------------
| Lead Status Filter
|--------------------------------------------------------------------------
*/

if ($leadStatus !== 'all') {

    $leadWhere[] =
        'l.status = ?';

    $leadParams[] =
        $leadStatus;

    $leadTypes .= 's';
}


/*
|--------------------------------------------------------------------------
| Hall Filter
|--------------------------------------------------------------------------
*/

if ($hallId !== null) {

    $leadWhere[] =
        'hld.hall_id = ?';

    $leadParams[] =
        $hallId;

    $leadTypes .= 'i';
}


/*
|--------------------------------------------------------------------------
| Date Filter
|--------------------------------------------------------------------------
|
| Hall report date filter is based on
| event date.
|
*/

if ($fromDate !== '') {

    $leadWhere[] =
        'l.event_date >= ?';

    $leadParams[] =
        $fromDate;

    $leadTypes .= 's';
}


if ($toDate !== '') {

    $leadWhere[] =
        'l.event_date <= ?';

    $leadParams[] =
        $toDate;

    $leadTypes .= 's';
}


/*
|--------------------------------------------------------------------------
| Guest Filter
|--------------------------------------------------------------------------
*/

if ($minGuests !== null) {

    $leadWhere[] =
        'COALESCE(hld.guest_count, l.guest_count, 0) >= ?';

    $leadParams[] =
        $minGuests;

    $leadTypes .= 'i';
}


if ($maxGuests !== null) {

    $leadWhere[] =
        'COALESCE(hld.guest_count, l.guest_count, 0) <= ?';

    $leadParams[] =
        $maxGuests;

    $leadTypes .= 'i';
}


/*
|--------------------------------------------------------------------------
| Converted Amount Filter
|--------------------------------------------------------------------------
*/

if ($minAmount !== null) {

    $leadWhere[] =
        'COALESCE(l.converted_amount, 0) >= ?';

    $leadParams[] =
        $minAmount;

    $leadTypes .= 'd';
}


if ($maxAmount !== null) {

    $leadWhere[] =
        'COALESCE(l.converted_amount, 0) <= ?';

    $leadParams[] =
        $maxAmount;

    $leadTypes .= 'd';
}


/*
|--------------------------------------------------------------------------
| Staff Scope
|--------------------------------------------------------------------------
*/

if ($currentUserRole === 'staff') {

    if (
        $currentUserId === null ||
        !ctype_digit(
            (string)$currentUserId
        )
    ) {

        sendResponse(
            false,
            'Invalid staff user identity.',
            [],
            401
        );
    }


    $leadWhere[] =
        'l.assigned_admin_id = ?';

    $leadParams[] =
        (int)$currentUserId;

    $leadTypes .= 'i';
}


/*
|--------------------------------------------------------------------------
| Lead Search
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $leadWhere[] = "
        (
            l.lead_reference LIKE ?
            OR l.customer_name LIKE ?
            OR l.customer_phone LIKE ?
            OR l.customer_email LIKE ?
            OR l.event_type LIKE ?
            OR h.name LIKE ?
            OR ht.name LIKE ?
        )
    ";

    $searchValue =
        '%' . $search . '%';


    for (
        $i = 0;
        $i < 7;
        $i++
    ) {

        $leadParams[] =
            $searchValue;
    }


    $leadTypes .=
        'sssssss';
}


$leadWhereSql =
    implode(
        ' AND ',
        $leadWhere
    );


/*
|--------------------------------------------------------------------------
| HALL LEAD SUMMARY
|--------------------------------------------------------------------------
*/

$leadSummarySql = "
    SELECT

        COUNT(*) AS total_leads,

        COALESCE(
            SUM(
                CASE
                    WHEN l.status = 'new'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS new_leads,

        COALESCE(
            SUM(
                CASE
                    WHEN l.status = 'contacted'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS contacted_leads,

        COALESCE(
            SUM(
                CASE
                    WHEN l.status = 'follow_up'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS follow_up_leads,

        COALESCE(
            SUM(
                CASE
                    WHEN l.status = 'converted'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS converted_leads,

        COALESCE(
            SUM(
                CASE
                    WHEN l.status = 'lost'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS lost_leads,

        COALESCE(
            SUM(
                CASE
                    WHEN l.status = 'cancelled'
                    THEN 1
                    ELSE 0
                END
            ),
            0
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
                    WHEN l.status = 'converted'
                    THEN COALESCE(
                        l.estimated_amount,
                        0
                    )
                    ELSE 0
                END
            ),
            0
        ) AS converted_estimated_value,

        COALESCE(
            SUM(
                COALESCE(
                    hld.guest_count,
                    l.guest_count,
                    0
                )
            ),
            0
        ) AS total_requested_guests

    FROM leads l

    LEFT JOIN hall_lead_details hld
        ON hld.lead_id = l.id

    LEFT JOIN halls h
        ON h.id = hld.hall_id

    LEFT JOIN hall_types ht
        ON ht.id = h.hall_type_id

    WHERE {$leadWhereSql}
";


$stmt =
    $conn->prepare(
        $leadSummarySql
    );


if (!$stmt) {

    sendResponse(
        false,
        'Failed to prepare hall lead summary.',
        [
            'error' => $conn->error
        ],
        500
    );
}


if (!empty($leadParams)) {

    bindDynamicParams(
        $stmt,
        $leadTypes,
        $leadParams
    );
}


if (!$stmt->execute()) {

    $error =
        $stmt->error;

    $stmt->close();

    sendResponse(
        false,
        'Failed to fetch hall lead summary.',
        [
            'error' => $error
        ],
        500
    );
}


$result =
    $stmt->get_result();


$leadSummary =
    $result->fetch_assoc()
    ?: [];


$stmt->close();


$leadSummary = [

    'total_leads' =>
        (int)(
            $leadSummary['total_leads']
            ?? 0
        ),

    'new_leads' =>
        (int)(
            $leadSummary['new_leads']
            ?? 0
        ),

    'contacted_leads' =>
        (int)(
            $leadSummary['contacted_leads']
            ?? 0
        ),

    'follow_up_leads' =>
        (int)(
            $leadSummary['follow_up_leads']
            ?? 0
        ),

    'converted_leads' =>
        (int)(
            $leadSummary['converted_leads']
            ?? 0
        ),

    'lost_leads' =>
        (int)(
            $leadSummary['lost_leads']
            ?? 0
        ),

    'cancelled_leads' =>
        (int)(
            $leadSummary['cancelled_leads']
            ?? 0
        ),

    'converted_revenue' =>
        round(
            (float)(
                $leadSummary['converted_revenue']
                ?? 0
            ),
            2
        ),

    'converted_estimated_value' =>
        round(
            (float)(
                $leadSummary['converted_estimated_value']
                ?? 0
            ),
            2
        ),

    'total_requested_guests' =>
        (int)(
            $leadSummary['total_requested_guests']
            ?? 0
        )
];


/*
|--------------------------------------------------------------------------
| Conversion Rate
|--------------------------------------------------------------------------
*/

$conversionRate =
    $leadSummary['total_leads'] > 0
        ? round(
            (
                $leadSummary['converted_leads']
                /
                $leadSummary['total_leads']
            ) * 100,
            2
        )
        : 0.00;


/*
|--------------------------------------------------------------------------
| Average Converted Revenue
|--------------------------------------------------------------------------
*/

$averageConvertedRevenue =
    $leadSummary['converted_leads'] > 0
        ? round(
            $leadSummary['converted_revenue']
            /
            $leadSummary['converted_leads'],
            2
        )
        : 0.00;


/*
|--------------------------------------------------------------------------
| HALL PERFORMANCE
|--------------------------------------------------------------------------
*/

$performanceParams = [];
$performanceTypes = '';


/*
|--------------------------------------------------------------------------
| Build Performance Conditions
|--------------------------------------------------------------------------
*/

$performanceLeadConditions = [
    "l.domain = 'hall'"
];


if ($leadStatus !== 'all') {

    $performanceLeadConditions[] =
        'l.status = ?';

    $performanceParams[] =
        $leadStatus;

    $performanceTypes .= 's';
}


if ($fromDate !== '') {

    $performanceLeadConditions[] =
        'l.event_date >= ?';

    $performanceParams[] =
        $fromDate;

    $performanceTypes .= 's';
}


if ($toDate !== '') {

    $performanceLeadConditions[] =
        'l.event_date <= ?';

    $performanceParams[] =
        $toDate;

    $performanceTypes .= 's';
}


if ($minGuests !== null) {

    $performanceLeadConditions[] =
        'COALESCE(hld.guest_count, l.guest_count, 0) >= ?';

    $performanceParams[] =
        $minGuests;

    $performanceTypes .= 'i';
}


if ($maxGuests !== null) {

    $performanceLeadConditions[] =
        'COALESCE(hld.guest_count, l.guest_count, 0) <= ?';

    $performanceParams[] =
        $maxGuests;

    $performanceTypes .= 'i';
}


if ($minAmount !== null) {

    $performanceLeadConditions[] =
        'COALESCE(l.converted_amount, 0) >= ?';

    $performanceParams[] =
        $minAmount;

    $performanceTypes .= 'd';
}


if ($maxAmount !== null) {

    $performanceLeadConditions[] =
        'COALESCE(l.converted_amount, 0) <= ?';

    $performanceParams[] =
        $maxAmount;

    $performanceTypes .= 'd';
}


if ($currentUserRole === 'staff') {

    $performanceLeadConditions[] =
        'l.assigned_admin_id = ?';

    $performanceParams[] =
        (int)$currentUserId;

    $performanceTypes .= 'i';
}


if ($search !== '') {

    $performanceLeadConditions[] = "
        (
            l.lead_reference LIKE ?
            OR l.customer_name LIKE ?
            OR l.customer_phone LIKE ?
            OR l.customer_email LIKE ?
            OR h.name LIKE ?
            OR ht.name LIKE ?
        )
    ";

    $searchValue =
        '%' . $search . '%';


    for (
        $i = 0;
        $i < 6;
        $i++
    ) {

        $performanceParams[] =
            $searchValue;
    }


    $performanceTypes .=
        'ssssss';
}


$performanceWhereSql =
    implode(
        ' AND ',
        $performanceLeadConditions
    );


/*
|--------------------------------------------------------------------------
| Hall ID Filter For Performance
|--------------------------------------------------------------------------
*/

$performanceHallCondition = '';


if ($hallId !== null) {

    $performanceHallCondition =
        'AND h.id = ?';

    /*
     * Add parameter at beginning
     * is difficult with bind order.
     *
     * Therefore append it to the
     * existing parameter list.
     */

    $performanceParams[] =
        $hallId;

    $performanceTypes .= 'i';
}


/*
|--------------------------------------------------------------------------
| Hall Type Filter
|--------------------------------------------------------------------------
*/

if ($hallTypeId !== null) {

    $performanceHallCondition .=
        ' AND h.hall_type_id = ?';

    $performanceParams[] =
        $hallTypeId;

    $performanceTypes .= 'i';
}


/*
|--------------------------------------------------------------------------
| Hall Status Filter
|--------------------------------------------------------------------------
*/

if ($hallStatus !== 'all') {

    $performanceHallCondition .=
        ' AND h.status = ?';

    $performanceParams[] =
        $hallStatus;

    $performanceTypes .= 's';
}


/*
|--------------------------------------------------------------------------
| Hall Performance Query
|--------------------------------------------------------------------------
*/

$hallPerformanceSql = "
    SELECT

        h.id,

        h.name,

        h.slug,

        h.capacity,

        h.status,

        ht.id AS hall_type_id,

        ht.name AS hall_type_name,

        COUNT(
            DISTINCT l.id
        ) AS total_leads,

        COUNT(
            DISTINCT
            CASE
                WHEN l.status = 'converted'
                THEN l.id
                ELSE NULL
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
                    hld.guest_count,
                    l.guest_count,
                    0
                )
            ),
            0
        ) AS requested_guests,

        COUNT(
            DISTINCT hi.id
        ) AS image_count,

        COUNT(
            DISTINCT hfm.facility_id
        ) AS facility_count

    FROM halls h

    LEFT JOIN hall_types ht
        ON ht.id = h.hall_type_id

    LEFT JOIN hall_lead_details hld
        ON hld.hall_id = h.id

    LEFT JOIN leads l
        ON l.id = hld.lead_id
        AND {$performanceWhereSql}

    LEFT JOIN hall_images hi
        ON hi.hall_id = h.id

    LEFT JOIN hall_facility_map hfm
        ON hfm.hall_id = h.id

    WHERE
        1 = 1
        {$performanceHallCondition}

    GROUP BY

        h.id,
        h.name,
        h.slug,
        h.capacity,
        h.status,
        ht.id,
        ht.name

    ORDER BY
        converted_revenue DESC,
        total_leads DESC,
        h.name ASC
";


$stmt =
    $conn->prepare(
        $hallPerformanceSql
    );


if (!$stmt) {

    sendResponse(
        false,
        'Failed to prepare hall performance query.',
        [
            'error' => $conn->error
        ],
        500
    );
}


if (!empty($performanceParams)) {

    bindDynamicParams(
        $stmt,
        $performanceTypes,
        $performanceParams
    );
}


if (!$stmt->execute()) {

    $error =
        $stmt->error;

    $stmt->close();

    sendResponse(
        false,
        'Failed to fetch hall performance.',
        [
            'error' => $error
        ],
        500
    );
}


$result =
    $stmt->get_result();


$hallPerformance = [];


while (
    $row =
        $result->fetch_assoc()
) {

    $convertedLeads =
        (int)$row['converted_leads'];


    $totalLeads =
        (int)$row['total_leads'];


    $hallPerformance[] = [

        'hall_id' =>
            (int)$row['id'],

        'hall_name' =>
            $row['name'],

        'slug' =>
            $row['slug'],

        'hall_type_id' =>
            $row['hall_type_id'] !== null
                ? (int)$row['hall_type_id']
                : null,

        'hall_type_name' =>
            $row['hall_type_name'],

        'capacity' =>
            (int)$row['capacity'],

        'status' =>
            $row['status'],

        'total_leads' =>
            $totalLeads,

        'converted_leads' =>
            $convertedLeads,

        'conversion_rate' =>
            $totalLeads > 0
                ? round(
                    (
                        $convertedLeads
                        /
                        $totalLeads
                    ) * 100,
                    2
                )
                : 0.00,

        'converted_revenue' =>
            round(
                (float)$row['converted_revenue'],
                2
            ),

        'requested_guests' =>
            (int)$row['requested_guests'],

        'image_count' =>
            (int)$row['image_count'],

        'facility_count' =>
            (int)$row['facility_count']
    ];
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| MONTHLY HALL REPORT
|--------------------------------------------------------------------------
*/

$monthlyWhere = [
    "l.domain = 'hall'"
];

$monthlyParams = [];
$monthlyTypes = '';


if ($fromDate !== '') {

    $monthlyWhere[] =
        'l.event_date >= ?';

    $monthlyParams[] =
        $fromDate;

    $monthlyTypes .= 's';
}


if ($toDate !== '') {

    $monthlyWhere[] =
        'l.event_date <= ?';

    $monthlyParams[] =
        $toDate;

    $monthlyTypes .= 's';
}


if ($hallId !== null) {

    $monthlyWhere[] =
        'hld.hall_id = ?';

    $monthlyParams[] =
        $hallId;

    $monthlyTypes .= 'i';
}


if ($hallTypeId !== null) {

    $monthlyWhere[] =
        'h.hall_type_id = ?';

    $monthlyParams[] =
        $hallTypeId;

    $monthlyTypes .= 'i';
}


if ($leadStatus !== 'all') {

    $monthlyWhere[] =
        'l.status = ?';

    $monthlyParams[] =
        $leadStatus;

    $monthlyTypes .= 's';
}


if ($currentUserRole === 'staff') {

    $monthlyWhere[] =
        'l.assigned_admin_id = ?';

    $monthlyParams[] =
        (int)$currentUserId;

    $monthlyTypes .= 'i';
}


$monthlyWhereSql =
    implode(
        ' AND ',
        $monthlyWhere
    );


$monthlySql = "
    SELECT

        DATE_FORMAT(
            l.event_date,
            '%Y-%m'
        ) AS report_month,

        COUNT(*) AS total_leads,

        SUM(
            CASE
                WHEN l.status = 'converted'
                THEN 1
                ELSE 0
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
                    hld.guest_count,
                    l.guest_count,
                    0
                )
            ),
            0
        ) AS requested_guests

    FROM leads l

    LEFT JOIN hall_lead_details hld
        ON hld.lead_id = l.id

    LEFT JOIN halls h
        ON h.id = hld.hall_id

    WHERE
        {$monthlyWhereSql}

        AND l.event_date IS NOT NULL

    GROUP BY
        DATE_FORMAT(
            l.event_date,
            '%Y-%m'
        )

    ORDER BY
        report_month ASC
";


$stmt =
    $conn->prepare(
        $monthlySql
    );


if (!$stmt) {

    sendResponse(
        false,
        'Failed to prepare monthly hall report.',
        [
            'error' => $conn->error
        ],
        500
    );
}


if (!empty($monthlyParams)) {

    bindDynamicParams(
        $stmt,
        $monthlyTypes,
        $monthlyParams
    );
}


if (!$stmt->execute()) {

    $error =
        $stmt->error;

    $stmt->close();

    sendResponse(
        false,
        'Failed to fetch monthly hall report.',
        [
            'error' => $error
        ],
        500
    );
}


$result =
    $stmt->get_result();


$monthlyHallReport = [];


while (
    $row =
        $result->fetch_assoc()
) {

    $monthlyHallReport[] = [

        'month' =>
            $row['report_month'],

        'total_leads' =>
            (int)$row['total_leads'],

        'converted_leads' =>
            (int)$row['converted_leads'],

        'conversion_rate' =>
            (int)$row['total_leads'] > 0
                ? round(
                    (
                        (int)$row['converted_leads']
                        /
                        (int)$row['total_leads']
                    ) * 100,
                    2
                )
                : 0.00,

        'converted_revenue' =>
            round(
                (float)$row['converted_revenue'],
                2
            ),

        'requested_guests' =>
            (int)$row['requested_guests']
    ];
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| CHART DATA
|--------------------------------------------------------------------------
*/

$monthlyLabels = [];
$monthlyLeadDataset = [];
$monthlyConvertedDataset = [];
$monthlyRevenueDataset = [];
$monthlyGuestDataset = [];


foreach (
    $monthlyHallReport
    as $month
) {

    $monthlyLabels[] =
        $month['month'];

    $monthlyLeadDataset[] =
        $month['total_leads'];

    $monthlyConvertedDataset[] =
        $month['converted_leads'];

    $monthlyRevenueDataset[] =
        $month['converted_revenue'];

    $monthlyGuestDataset[] =
        $month['requested_guests'];
}


/*
|--------------------------------------------------------------------------
| HALL STATUS CHART
|--------------------------------------------------------------------------
*/

$hallStatusChart = [

    'labels' => [
        'Available',
        'Maintenance',
        'Inactive'
    ],

    'datasets' => [

        [
            'label' =>
                'Halls',

            'data' => [

                $inventory['available_halls'],

                $inventory['maintenance_halls'],

                $inventory['inactive_halls']
            ]
        ]
    ]
];


/*
|--------------------------------------------------------------------------
| HALL PERFORMANCE CHART
|--------------------------------------------------------------------------
*/

$hallPerformanceLabels = [];
$hallPerformanceRevenue = [];
$hallPerformanceLeads = [];
$hallPerformanceConverted = [];


foreach (
    $hallPerformance
    as $performance
) {

    $hallPerformanceLabels[] =
        $performance['hall_name'];

    $hallPerformanceRevenue[] =
        $performance['converted_revenue'];

    $hallPerformanceLeads[] =
        $performance['total_leads'];

    $hallPerformanceConverted[] =
        $performance['converted_leads'];
}


/*
|--------------------------------------------------------------------------
| HALL LEAD RECORDS
|--------------------------------------------------------------------------
*/

$recordsWhere = [
    "l.domain = 'hall'"
];

$recordsParams = [];
$recordsTypes = '';


/*
|--------------------------------------------------------------------------
| Record Status
|--------------------------------------------------------------------------
*/

if ($leadStatus !== 'all') {

    $recordsWhere[] =
        'l.status = ?';

    $recordsParams[] =
        $leadStatus;

    $recordsTypes .= 's';
}


/*
|--------------------------------------------------------------------------
| Record Hall
|--------------------------------------------------------------------------
*/

if ($hallId !== null) {

    $recordsWhere[] =
        'hld.hall_id = ?';

    $recordsParams[] =
        $hallId;

    $recordsTypes .= 'i';
}


/*
|--------------------------------------------------------------------------
| Record Hall Type
|--------------------------------------------------------------------------
*/

if ($hallTypeId !== null) {

    $recordsWhere[] =
        'h.hall_type_id = ?';

    $recordsParams[] =
        $hallTypeId;

    $recordsTypes .= 'i';
}


/*
|--------------------------------------------------------------------------
| Record Date
|--------------------------------------------------------------------------
*/

if ($fromDate !== '') {

    $recordsWhere[] =
        'l.event_date >= ?';

    $recordsParams[] =
        $fromDate;

    $recordsTypes .= 's';
}


if ($toDate !== '') {

    $recordsWhere[] =
        'l.event_date <= ?';

    $recordsParams[] =
        $toDate;

    $recordsTypes .= 's';
}


/*
|--------------------------------------------------------------------------
| Record Guests
|--------------------------------------------------------------------------
*/

if ($minGuests !== null) {

    $recordsWhere[] =
        'COALESCE(hld.guest_count, l.guest_count, 0) >= ?';

    $recordsParams[] =
        $minGuests;

    $recordsTypes .= 'i';
}


if ($maxGuests !== null) {

    $recordsWhere[] =
        'COALESCE(hld.guest_count, l.guest_count, 0) <= ?';

    $recordsParams[] =
        $maxGuests;

    $recordsTypes .= 'i';
}


/*
|--------------------------------------------------------------------------
| Record Amount
|--------------------------------------------------------------------------
*/

if ($minAmount !== null) {

    $recordsWhere[] =
        'COALESCE(l.converted_amount, 0) >= ?';

    $recordsParams[] =
        $minAmount;

    $recordsTypes .= 'd';
}


if ($maxAmount !== null) {

    $recordsWhere[] =
        'COALESCE(l.converted_amount, 0) <= ?';

    $recordsParams[] =
        $maxAmount;

    $recordsTypes .= 'd';
}


/*
|--------------------------------------------------------------------------
| Staff Record Scope
|--------------------------------------------------------------------------
*/

if ($currentUserRole === 'staff') {

    $recordsWhere[] =
        'l.assigned_admin_id = ?';

    $recordsParams[] =
        (int)$currentUserId;

    $recordsTypes .= 'i';
}


/*
|--------------------------------------------------------------------------
| Record Search
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $recordsWhere[] = "
        (
            l.lead_reference LIKE ?
            OR l.customer_name LIKE ?
            OR l.customer_phone LIKE ?
            OR l.customer_email LIKE ?
            OR l.event_type LIKE ?
            OR h.name LIKE ?
            OR ht.name LIKE ?
        )
    ";

    $searchValue =
        '%' . $search . '%';


    for (
        $i = 0;
        $i < 7;
        $i++
    ) {

        $recordsParams[] =
            $searchValue;
    }


    $recordsTypes .=
        'sssssss';
}


$recordsWhereSql =
    implode(
        ' AND ',
        $recordsWhere
    );


/*
|--------------------------------------------------------------------------
| Safe SQL Sort
|--------------------------------------------------------------------------
*/

$sortMap = [

    'created_at' =>
        'l.created_at',

    'event_date' =>
        'l.event_date',

    'guest_count' =>
        'COALESCE(hld.guest_count, l.guest_count, 0)',

    'converted_amount' =>
        'COALESCE(l.converted_amount, 0)',

    'customer_name' =>
        'l.customer_name',

    'lead_reference' =>
        'l.lead_reference'
];


$safeSortColumn =
    $sortMap[$sortBy]
    ?? 'l.created_at';


/*
|--------------------------------------------------------------------------
| Fetch All Matching Records
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

        l.preferred_time,

        l.requirement,

        l.status AS lead_status,

        l.estimated_amount,

        l.converted_amount,

        l.converted_at,

        l.source,

        l.assigned_admin_id,

        l.created_at,

        l.updated_at,

        hld.hall_id,

        hld.event_type,

        hld.event_date AS hall_event_date,

        hld.event_time,

        hld.guest_count,

        hld.food_required,

        hld.decoration_required,

        hld.other_requirements,

        h.id AS actual_hall_id,

        h.name AS hall_name,

        h.slug AS hall_slug,

        h.capacity AS hall_capacity,

        h.area AS hall_area,

        h.price_description,

        h.status AS hall_status,

        ht.id AS hall_type_id,

        ht.name AS hall_type_name,

        a.id AS assigned_admin_id_actual,

        a.name AS assigned_admin_name,

        a.email AS assigned_admin_email

    FROM leads l

    LEFT JOIN hall_lead_details hld
        ON hld.lead_id = l.id

    LEFT JOIN halls h
        ON h.id = hld.hall_id

    LEFT JOIN hall_types ht
        ON ht.id = h.hall_type_id

    LEFT JOIN admin_users a
        ON a.id = l.assigned_admin_id

    WHERE {$recordsWhereSql}

    ORDER BY
        {$safeSortColumn}
        {$sortOrder}
";


$stmt =
    $conn->prepare(
        $recordsSql
    );


if (!$stmt) {

    sendResponse(
        false,
        'Failed to prepare hall lead records query.',
        [
            'error' => $conn->error
        ],
        500
    );
}


if (!empty($recordsParams)) {

    bindDynamicParams(
        $stmt,
        $recordsTypes,
        $recordsParams
    );
}


if (!$stmt->execute()) {

    $error =
        $stmt->error;

    $stmt->close();

    sendResponse(
        false,
        'Failed to fetch hall lead records.',
        [
            'error' => $error
        ],
        500
    );
}


$result =
    $stmt->get_result();


$allRecords = [];


while (
    $row =
        $result->fetch_assoc()
) {

    $allRecords[] = [

        'lead_id' =>
            (int)$row['lead_id'],

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

        'lead' => [

            'status' =>
                $row['lead_status'],

            'source' =>
                $row['source'],

            'event_date' =>
                $row['lead_event_date'],

            'preferred_time' =>
                $row['preferred_time'],

            'requirement' =>
                $row['requirement'],

            'estimated_amount' =>
                $row['estimated_amount'] !== null
                    ? round(
                        (float)$row['estimated_amount'],
                        2
                    )
                    : null,

            'converted_amount' =>
                $row['converted_amount'] !== null
                    ? round(
                        (float)$row['converted_amount'],
                        2
                    )
                    : null,

            'converted_at' =>
                $row['converted_at'],

            'created_at' =>
                $row['created_at'],

            'updated_at' =>
                $row['updated_at']
        ],

        'hall' => [

            'id' =>
                $row['actual_hall_id'] !== null
                    ? (int)$row['actual_hall_id']
                    : null,

            'name' =>
                $row['hall_name'],

            'slug' =>
                $row['hall_slug'],

            'type_id' =>
                $row['hall_type_id'] !== null
                    ? (int)$row['hall_type_id']
                    : null,

            'type_name' =>
                $row['hall_type_name'],

            'capacity' =>
                $row['hall_capacity'] !== null
                    ? (int)$row['hall_capacity']
                    : null,

            'area' =>
                $row['hall_area'],

            'price_description' =>
                $row['price_description'],

            'status' =>
                $row['hall_status']
        ],

        'event' => [

            'event_type' =>
                $row['event_type'],

            'event_date' =>
                $row['hall_event_date']
                ?? $row['lead_event_date'],

            'event_time' =>
                $row['event_time'],

            'guest_count' =>
                $row['guest_count'] !== null
                    ? (int)$row['guest_count']
                    : (
                        $row['lead_event_date'] !== null
                            ? null
                            : null
                    ),

            'food_required' =>
                (bool)$row['food_required'],

            'decoration_required' =>
                (bool)$row['decoration_required'],

            'other_requirements' =>
                $row['other_requirements']
        ],

        'assigned_admin' => [

            'id' =>
                $row['assigned_admin_id_actual'] !== null
                    ? (int)$row['assigned_admin_id_actual']
                    : null,

            'name' =>
                $row['assigned_admin_name'],

            'email' =>
                $row['assigned_admin_email']
        ]
    ];
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| Total Matching Records
|--------------------------------------------------------------------------
*/

$totalRecords =
    count($allRecords);


/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

$totalPages =
    $totalRecords > 0
        ? (int)ceil(
            $totalRecords / $limit
        )
        : 0;


if (
    $totalPages > 0 &&
    $page > $totalPages
) {

    $page =
        $totalPages;
}


$offset =
    ($page - 1) * $limit;


$paginatedRecords =
    array_slice(
        $allRecords,
        $offset,
        $limit
    );


/*
|--------------------------------------------------------------------------
| UPCOMING HALL EVENTS
|--------------------------------------------------------------------------
*/

$today =
    date('Y-m-d');


$upcomingWhere = [
    "l.domain = 'hall'",
    "l.status IN ('new','contacted','follow_up','converted')",
    "COALESCE(hld.event_date, l.event_date) >= ?"
];

$upcomingParams = [
    $today
];

$upcomingTypes = 's';


if ($hallId !== null) {

    $upcomingWhere[] =
        'hld.hall_id = ?';

    $upcomingParams[] =
        $hallId;

    $upcomingTypes .= 'i';
}


if ($hallTypeId !== null) {

    $upcomingWhere[] =
        'h.hall_type_id = ?';

    $upcomingParams[] =
        $hallTypeId;

    $upcomingTypes .= 'i';
}


if ($currentUserRole === 'staff') {

    $upcomingWhere[] =
        'l.assigned_admin_id = ?';

    $upcomingParams[] =
        (int)$currentUserId;

    $upcomingTypes .= 'i';
}


$upcomingWhereSql =
    implode(
        ' AND ',
        $upcomingWhere
    );


$upcomingSql = "
    SELECT

        l.id AS lead_id,

        l.lead_reference,

        l.customer_name,

        l.customer_phone,

        l.status,

        COALESCE(
            hld.event_date,
            l.event_date
        ) AS event_date,

        hld.event_time,

        hld.event_type,

        COALESCE(
            hld.guest_count,
            l.guest_count
        ) AS guest_count,

        h.name AS hall_name,

        ht.name AS hall_type_name,

        l.converted_amount

    FROM leads l

    LEFT JOIN hall_lead_details hld
        ON hld.lead_id = l.id

    LEFT JOIN halls h
        ON h.id = hld.hall_id

    LEFT JOIN hall_types ht
        ON ht.id = h.hall_type_id

    WHERE {$upcomingWhereSql}

    ORDER BY
        COALESCE(
            hld.event_date,
            l.event_date
        ) ASC,

        hld.event_time ASC

    LIMIT 20
";


$stmt =
    $conn->prepare(
        $upcomingSql
    );


if (!$stmt) {

    sendResponse(
        false,
        'Failed to prepare upcoming hall events query.',
        [
            'error' => $conn->error
        ],
        500
    );
}


bindDynamicParams(
    $stmt,
    $upcomingTypes,
    $upcomingParams
);


if (!$stmt->execute()) {

    $error =
        $stmt->error;

    $stmt->close();

    sendResponse(
        false,
        'Failed to fetch upcoming hall events.',
        [
            'error' => $error
        ],
        500
    );
}


$result =
    $stmt->get_result();


$upcomingEvents = [];


while (
    $row =
        $result->fetch_assoc()
) {

    $upcomingEvents[] = [

        'lead_id' =>
            (int)$row['lead_id'],

        'lead_reference' =>
            $row['lead_reference'],

        'customer_name' =>
            $row['customer_name'],

        'customer_phone' =>
            $row['customer_phone'],

        'status' =>
            $row['status'],

        'hall_name' =>
            $row['hall_name'],

        'hall_type_name' =>
            $row['hall_type_name'],

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

        'converted_amount' =>
            $row['converted_amount'] !== null
                ? round(
                    (float)$row['converted_amount'],
                    2
                )
                : null
    ];
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| TODAY SUMMARY
|--------------------------------------------------------------------------
*/

$todayWhere = [
    "l.domain = 'hall'",
    "COALESCE(hld.event_date, l.event_date) = ?"
];

$todayParams = [
    $today
];

$todayTypes = 's';


if ($hallId !== null) {

    $todayWhere[] =
        'hld.hall_id = ?';

    $todayParams[] =
        $hallId;

    $todayTypes .= 'i';
}


if ($currentUserRole === 'staff') {

    $todayWhere[] =
        'l.assigned_admin_id = ?';

    $todayParams[] =
        (int)$currentUserId;

    $todayTypes .= 'i';
}


$todayWhereSql =
    implode(
        ' AND ',
        $todayWhere
    );


$todaySql = "
    SELECT

        COUNT(*) AS today_events,

        COALESCE(
            SUM(
                COALESCE(
                    hld.guest_count,
                    l.guest_count,
                    0
                )
            ),
            0
        ) AS today_guests,

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
        ) AS today_revenue

    FROM leads l

    LEFT JOIN hall_lead_details hld
        ON hld.lead_id = l.id

    WHERE {$todayWhereSql}
";


$stmt =
    $conn->prepare(
        $todaySql
    );


if (!$stmt) {

    sendResponse(
        false,
        'Failed to prepare today hall summary.',
        [
            'error' => $conn->error
        ],
        500
    );
}


bindDynamicParams(
    $stmt,
    $todayTypes,
    $todayParams
);


if (!$stmt->execute()) {

    $error =
        $stmt->error;

    $stmt->close();

    sendResponse(
        false,
        'Failed to fetch today hall summary.',
        [
            'error' => $error
        ],
        500
    );
}


$result =
    $stmt->get_result();


$todaySummary =
    $result->fetch_assoc()
    ?: [];


$stmt->close();


$todaySummary = [

    'events' =>
        (int)(
            $todaySummary['today_events']
            ?? 0
        ),

    'guests' =>
        (int)(
            $todaySummary['today_guests']
            ?? 0
        ),

    'converted_revenue' =>
        round(
            (float)(
                $todaySummary['today_revenue']
                ?? 0
            ),
            2
        )
];


/*
|--------------------------------------------------------------------------
| Revenue
|--------------------------------------------------------------------------
*/

$hallRevenue =
    round(
        $leadSummary['converted_revenue'],
        2
    );


/*
|--------------------------------------------------------------------------
| Estimated Revenue
|--------------------------------------------------------------------------
*/

$estimatedRevenue =
    round(
        $leadSummary['converted_estimated_value'],
        2
    );


/*
|--------------------------------------------------------------------------
| Pending Potential Revenue
|--------------------------------------------------------------------------
*/

$pendingPotentialRevenue =
    max(
        0,
        round(
            $estimatedRevenue -
            $hallRevenue,
            2
        )
    );


/*
|--------------------------------------------------------------------------
| Hall Performance Summary
|--------------------------------------------------------------------------
*/

$hallPerformanceSummary = [

    'total_halls' =>
        $inventory['total_halls'],

    'available_halls' =>
        $inventory['available_halls'],

    'maintenance_halls' =>
        $inventory['maintenance_halls'],

    'inactive_halls' =>
        $inventory['inactive_halls'],

    'total_capacity' =>
        $inventory['total_capacity'],

    'total_leads' =>
        $leadSummary['total_leads'],

    'converted_leads' =>
        $leadSummary['converted_leads'],

    'conversion_rate' =>
        $conversionRate,

    'converted_revenue' =>
        $hallRevenue,

    'estimated_revenue' =>
        $estimatedRevenue,

    'pending_potential_revenue' =>
        $pendingPotentialRevenue,

    'average_converted_revenue' =>
        $averageConvertedRevenue
];


/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

$pagination = [

    'page' =>
        $page,

    'limit' =>
        $limit,

    'total_records' =>
        $totalRecords,

    'total_pages' =>
        $totalPages,

    'from' =>
        $totalRecords > 0
            ? $offset + 1
            : 0,

    'to' =>
        $totalRecords > 0
            ? min(
                $offset + $limit,
                $totalRecords
            )
            : 0,

    'has_next_page' =>
        $page < $totalPages,

    'has_previous_page' =>
        $page > 1
];


/*
|--------------------------------------------------------------------------
| Currency
|--------------------------------------------------------------------------
*/

$currency =
    getenv('CURRENCY_CODE')
    ?: 'INR';


$currencySymbol =
    getenv('CURRENCY_SYMBOL')
    ?: '₹';


/*
|--------------------------------------------------------------------------
| FINAL RESPONSE
|--------------------------------------------------------------------------
*/

sendResponse(
    true,
    'Hall report fetched successfully.',
    [

        /*
        |--------------------------------------------------------------------------
        | Meta
        |--------------------------------------------------------------------------
        */

        'meta' => [

            'report' =>
                'hall_report',

            'currency' =>
                $currency,

            'currency_symbol' =>
                $currencySymbol,

            'generated_at' =>
                date('Y-m-d H:i:s'),

            'date_filter_definition' =>
                'from_date and to_date filter hall event date.',

            'revenue_definition' =>
                'Hall revenue is based on converted hall lead amount.'
        ],


        /*
        |--------------------------------------------------------------------------
        | User Scope
        |--------------------------------------------------------------------------
        */

        'user_scope' => [

            'user_id' =>
                $currentUserId,

            'role' =>
                $currentUserRole,

            'lead_scope' =>
                $currentUserRole === 'staff'
                    ? 'assigned_hall_leads_only'
                    : 'all_hall_leads'
        ],


        /*
        |--------------------------------------------------------------------------
        | Filters
        |--------------------------------------------------------------------------
        */

        'filters' => [

            'hall_id' =>
                $hallId,

            'hall_type_id' =>
                $hallTypeId,

            'hall_status' =>
                $hallStatus,

            'lead_status' =>
                $leadStatus,

            'from_date' =>
                $fromDate !== ''
                    ? $fromDate
                    : null,

            'to_date' =>
                $toDate !== ''
                    ? $toDate
                    : null,

            'search' =>
                $search !== ''
                    ? $search
                    : null,

            'min_guests' =>
                $minGuests,

            'max_guests' =>
                $maxGuests,

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


        /*
        |--------------------------------------------------------------------------
        | KPI
        |--------------------------------------------------------------------------
        */

        'kpis' => [

            'total_halls' =>
                $inventory['total_halls'],

            'available_halls' =>
                $inventory['available_halls'],

            'maintenance_halls' =>
                $inventory['maintenance_halls'],

            'inactive_halls' =>
                $inventory['inactive_halls'],

            'total_capacity' =>
                $inventory['total_capacity'],

            'total_leads' =>
                $leadSummary['total_leads'],

            'new_leads' =>
                $leadSummary['new_leads'],

            'contacted_leads' =>
                $leadSummary['contacted_leads'],

            'follow_up_leads' =>
                $leadSummary['follow_up_leads'],

            'converted_leads' =>
                $leadSummary['converted_leads'],

            'lost_leads' =>
                $leadSummary['lost_leads'],

            'cancelled_leads' =>
                $leadSummary['cancelled_leads'],

            'conversion_rate' =>
                $conversionRate,

            'hall_revenue' =>
                $hallRevenue,

            'estimated_revenue' =>
                $estimatedRevenue,

            'pending_potential_revenue' =>
                $pendingPotentialRevenue,

            'average_converted_revenue' =>
                $averageConvertedRevenue,

            'total_requested_guests' =>
                $leadSummary['total_requested_guests']
        ],


        /*
        |--------------------------------------------------------------------------
        | Inventory
        |--------------------------------------------------------------------------
        */

        'inventory' =>
            $inventory,


        /*
        |--------------------------------------------------------------------------
        | Hall Types
        |--------------------------------------------------------------------------
        */

        'hall_types' =>
            $hallTypesSummary,


        /*
        |--------------------------------------------------------------------------
        | Lead Summary
        |--------------------------------------------------------------------------
        */

        'lead_summary' =>
            $leadSummary,


        /*
        |--------------------------------------------------------------------------
        | Revenue Summary
        |--------------------------------------------------------------------------
        */

        'revenue' => [

            'hall_revenue' =>
                $hallRevenue,

            'estimated_revenue' =>
                $estimatedRevenue,

            'pending_potential_revenue' =>
                $pendingPotentialRevenue,

            'converted_leads' =>
                $leadSummary['converted_leads'],

            'average_converted_revenue' =>
                $averageConvertedRevenue
        ],


        /*
        |--------------------------------------------------------------------------
        | Today
        |--------------------------------------------------------------------------
        */

        'today' =>
            $todaySummary,


        /*
        |--------------------------------------------------------------------------
        | Hall Performance
        |--------------------------------------------------------------------------
        */

        'hall_performance' =>
            $hallPerformance,


        /*
        |--------------------------------------------------------------------------
        | Monthly Report
        |--------------------------------------------------------------------------
        */

        'monthly_report' =>
            $monthlyHallReport,


        /*
        |--------------------------------------------------------------------------
        | Upcoming Events
        |--------------------------------------------------------------------------
        */

        'upcoming_events' =>
            $upcomingEvents,


        /*
        |--------------------------------------------------------------------------
        | Records
        |--------------------------------------------------------------------------
        */

        'records' =>
            $paginatedRecords,


        /*
        |--------------------------------------------------------------------------
        | Pagination
        |--------------------------------------------------------------------------
        */

        'pagination' =>
            $pagination,


        /*
        |--------------------------------------------------------------------------
        | Chart.js
        |--------------------------------------------------------------------------
        */

        'charts' => [

            /*
            |----------------------------------------------------------------------
            | Hall Status
            |----------------------------------------------------------------------
            */

            'hall_status' =>
                $hallStatusChart,


            /*
            |----------------------------------------------------------------------
            | Monthly Lead Trend
            |----------------------------------------------------------------------
            */

            'monthly_leads' => [

                'labels' =>
                    $monthlyLabels,

                'datasets' => [

                    [
                        'label' =>
                            'Total Hall Leads',

                        'data' =>
                            $monthlyLeadDataset
                    ],

                    [
                        'label' =>
                            'Converted Hall Leads',

                        'data' =>
                            $monthlyConvertedDataset
                    ]
                ]
            ],


            /*
            |----------------------------------------------------------------------
            | Monthly Revenue
            |----------------------------------------------------------------------
            */

            'monthly_revenue' => [

                'labels' =>
                    $monthlyLabels,

                'datasets' => [

                    [
                        'label' =>
                            'Hall Revenue',

                        'data' =>
                            $monthlyRevenueDataset
                    ]
                ]
            ],


            /*
            |----------------------------------------------------------------------
            | Monthly Guests
            |----------------------------------------------------------------------
            */

            'monthly_guests' => [

                'labels' =>
                    $monthlyLabels,

                'datasets' => [

                    [
                        'label' =>
                            'Requested Guests',

                        'data' =>
                            $monthlyGuestDataset
                    ]
                ]
            ],


            /*
            |----------------------------------------------------------------------
            | Hall Performance
            |----------------------------------------------------------------------
            */

            'hall_performance' => [

                'labels' =>
                    $hallPerformanceLabels,

                'datasets' => [

                    [
                        'label' =>
                            'Converted Revenue',

                        'data' =>
                            $hallPerformanceRevenue
                    ],

                    [
                        'label' =>
                            'Total Leads',

                        'data' =>
                            $hallPerformanceLeads
                    ],

                    [
                        'label' =>
                            'Converted Leads',

                        'data' =>
                            $hallPerformanceConverted
                    ]
                ]
            ]
        ]
    ]
);