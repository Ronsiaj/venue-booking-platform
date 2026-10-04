<?php

/*
|--------------------------------------------------------------------------
| SM GROUP - NOTIFICATION HISTORY
|--------------------------------------------------------------------------
|
| GET /notification/history.php
|
| Admin:
|   Can view all notification history.
|
| Staff:
|   Can view only notifications belonging to
|   leads assigned to the logged-in staff.
|
| Supported filters:
|
| ?lead_id=1
| ?domain=hall
| ?channel=email
| ?status=sent
| ?recipient=test@gmail.com
| ?search=9876543210
| ?from_date=2026-09-01
| ?to_date=2026-09-30
| ?page=1
| ?limit=20
| ?sort_by=created_at
| ?sort_order=DESC
|
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| ERROR REPORTING
|--------------------------------------------------------------------------
*/

ini_set('display_errors', '0');
ini_set('log_errors', '1');

error_reporting(E_ALL);


/*
|--------------------------------------------------------------------------
| CORS
|--------------------------------------------------------------------------
*/

header(
    'Content-Type: application/json; charset=UTF-8'
);

header(
    'Access-Control-Allow-Origin: *'
);

header(
    'Access-Control-Allow-Methods: GET, OPTIONS'
);

header(
    'Access-Control-Allow-Headers: Content-Type, Authorization'
);


/*
|--------------------------------------------------------------------------
| OPTIONS
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'OPTIONS'
) {

    http_response_code(200);

    exit;
}


/*
|--------------------------------------------------------------------------
| METHOD VALIDATION
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] !== 'GET'
) {

    http_response_code(405);

    echo json_encode(
        [
            'status' => false,
            'message' => 'Only GET method is allowed.',
            'data' => []
        ],
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

require_once __DIR__ .
    '/../config/database.php';


/*
|--------------------------------------------------------------------------
| MIDDLEWARE
|--------------------------------------------------------------------------
*/

require_once __DIR__ .
    '/../config/middleware.php';


/*
|--------------------------------------------------------------------------
| ADMIN / STAFF AUTHENTICATION
|--------------------------------------------------------------------------
*/

$currentUser =
    requireAdminOrStaff();


/*
|--------------------------------------------------------------------------
| RESPONSE HELPER
|--------------------------------------------------------------------------
*/

function sendJsonResponse(
    int $statusCode,
    bool $status,
    string $message,
    array $data = []
): void {

    http_response_code(
        $statusCode
    );

    echo json_encode(
        [
            'status' => $status,
            'message' => $message,
            'data' => $data
        ],
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| STRING CLEANER
|--------------------------------------------------------------------------
*/

function cleanValue($value): string
{
    return trim(
        (string)$value
    );
}


/*
|--------------------------------------------------------------------------
| DATE VALIDATION
|--------------------------------------------------------------------------
*/

function isValidDate(
    string $date
): bool {

    if (
        !preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $date
        )
    ) {

        return false;
    }

    $parts =
        explode(
            '-',
            $date
        );

    if (
        count($parts) !== 3
    ) {

        return false;
    }

    return checkdate(
        (int)$parts[1],
        (int)$parts[2],
        (int)$parts[0]
    );
}


/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION VALIDATION
|--------------------------------------------------------------------------
*/

if (
    !isset($conn) ||
    !($conn instanceof mysqli)
) {

    sendJsonResponse(
        500,
        false,
        'Database connection is not available.'
    );
}


/*
|--------------------------------------------------------------------------
| AUTHENTICATED USER ROLE
|--------------------------------------------------------------------------
*/

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
| AUTHENTICATED USER ID
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| Your JWT uses:
|
|     sub = authenticated user ID
|
| So we MUST read sub first.
|
| id is kept as fallback for compatibility.
|
|--------------------------------------------------------------------------
*/

$currentUserId =
    $currentUser['sub']
    ?? $currentUser['id']
    ?? null;


/*
|--------------------------------------------------------------------------
| ROLE VALIDATION
|--------------------------------------------------------------------------
*/

if (
    !in_array(
        $currentUserRole,
        [
            'admin',
            'staff'
        ],
        true
    )
) {

    sendJsonResponse(
        403,
        false,
        'You are not authorized to view notification history.'
    );
}


/*
|--------------------------------------------------------------------------
| USER ID VALIDATION
|--------------------------------------------------------------------------
*/

if (
    $currentUserId === null ||
    !is_numeric($currentUserId) ||
    (int)$currentUserId <= 0
) {

    sendJsonResponse(
        401,
        false,
        'Invalid authenticated user.'
    );
}


$currentUserId =
    (int)$currentUserId;


/*
|--------------------------------------------------------------------------
| ALLOWED QUERY PARAMETERS
|--------------------------------------------------------------------------
*/

$allowedParams = [

    'lead_id',

    'domain',

    'channel',

    'status',

    'recipient',

    'search',

    'from_date',

    'to_date',

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

foreach (
    $_GET as $key => $value
) {

    if (
        !in_array(
            $key,
            $allowedParams,
            true
        )
    ) {

        sendJsonResponse(
            400,
            false,
            "Unknown query parameter: {$key}"
        );
    }
}


/*
|--------------------------------------------------------------------------
| LEAD ID
|--------------------------------------------------------------------------
*/

$leadId = null;

if (
    isset($_GET['lead_id'])
) {

    $leadIdValue =
        cleanValue(
            $_GET['lead_id']
        );

    if (
        $leadIdValue === '' ||
        !ctype_digit($leadIdValue)
    ) {

        sendJsonResponse(
            422,
            false,
            'lead_id must be a positive integer.'
        );
    }

    $leadId =
        (int)$leadIdValue;

    if (
        $leadId <= 0
    ) {

        sendJsonResponse(
            422,
            false,
            'lead_id must be greater than zero.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| DOMAIN
|--------------------------------------------------------------------------
*/

$domain = null;

if (
    isset($_GET['domain'])
) {

    $domain =
        strtolower(
            cleanValue(
                $_GET['domain']
            )
        );

    if (
        !in_array(
            $domain,
            [
                'hall',
                'dining',
                'catering'
            ],
            true
        )
    ) {

        sendJsonResponse(
            422,
            false,
            'domain must be hall, dining or catering.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| CHANNEL
|--------------------------------------------------------------------------
*/

$channel = null;

if (
    isset($_GET['channel'])
) {

    $channel =
        strtolower(
            cleanValue(
                $_GET['channel']
            )
        );

    if (
        !in_array(
            $channel,
            [
                'whatsapp',
                'email'
            ],
            true
        )
    ) {

        sendJsonResponse(
            422,
            false,
            'channel must be whatsapp or email.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| NOTIFICATION STATUS
|--------------------------------------------------------------------------
*/

$status = null;

if (
    isset($_GET['status'])
) {

    $status =
        strtolower(
            cleanValue(
                $_GET['status']
            )
        );

    if (
        !in_array(
            $status,
            [
                'pending',
                'sent',
                'failed'
            ],
            true
        )
    ) {

        sendJsonResponse(
            422,
            false,
            'status must be pending, sent or failed.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| RECIPIENT
|--------------------------------------------------------------------------
*/

$recipient = null;

if (
    isset($_GET['recipient'])
) {

    $recipient =
        cleanValue(
            $_GET['recipient']
        );

    if (
        $recipient === ''
    ) {

        sendJsonResponse(
            422,
            false,
            'recipient cannot be empty.'
        );
    }

    if (
        mb_strlen($recipient) > 255
    ) {

        sendJsonResponse(
            422,
            false,
            'recipient cannot exceed 255 characters.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
|
| Searches:
|
| - lead_reference
| - customer_name
| - customer_phone
| - customer_email
| - notification recipient
|
|--------------------------------------------------------------------------
*/

$search = null;

if (
    isset($_GET['search'])
) {

    $search =
        cleanValue(
            $_GET['search']
        );

    if (
        $search === ''
    ) {

        sendJsonResponse(
            422,
            false,
            'search cannot be empty.'
        );
    }

    if (
        mb_strlen($search) > 255
    ) {

        sendJsonResponse(
            422,
            false,
            'search cannot exceed 255 characters.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| FROM DATE
|--------------------------------------------------------------------------
*/

$fromDate = null;

if (
    isset($_GET['from_date'])
) {

    $fromDate =
        cleanValue(
            $_GET['from_date']
        );

    if (
        !isValidDate($fromDate)
    ) {

        sendJsonResponse(
            422,
            false,
            'from_date must be a valid date in YYYY-MM-DD format.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| TO DATE
|--------------------------------------------------------------------------
*/

$toDate = null;

if (
    isset($_GET['to_date'])
) {

    $toDate =
        cleanValue(
            $_GET['to_date']
        );

    if (
        !isValidDate($toDate)
    ) {

        sendJsonResponse(
            422,
            false,
            'to_date must be a valid date in YYYY-MM-DD format.'
        );
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

    sendJsonResponse(
        422,
        false,
        'from_date cannot be greater than to_date.'
    );
}


/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

$page = 1;

if (
    isset($_GET['page'])
) {

    $pageValue =
        cleanValue(
            $_GET['page']
        );

    if (
        $pageValue === '' ||
        !ctype_digit($pageValue)
    ) {

        sendJsonResponse(
            422,
            false,
            'page must be a positive integer.'
        );
    }

    $page =
        (int)$pageValue;

    if (
        $page < 1
    ) {

        sendJsonResponse(
            422,
            false,
            'page must be greater than zero.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| LIMIT
|--------------------------------------------------------------------------
*/

$limit = 20;

if (
    isset($_GET['limit'])
) {

    $limitValue =
        cleanValue(
            $_GET['limit']
        );

    if (
        $limitValue === '' ||
        !ctype_digit($limitValue)
    ) {

        sendJsonResponse(
            422,
            false,
            'limit must be a positive integer.'
        );
    }

    $limit =
        (int)$limitValue;

    if (
        $limit < 1
    ) {

        sendJsonResponse(
            422,
            false,
            'limit must be greater than zero.'
        );
    }

    if (
        $limit > 100
    ) {

        sendJsonResponse(
            422,
            false,
            'limit cannot exceed 100.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| SORT COLUMN
|--------------------------------------------------------------------------
*/

$sortColumns = [

    'id' =>
        'ln.id',

    'lead_id' =>
        'ln.lead_id',

    'channel' =>
        'ln.channel',

    'recipient' =>
        'ln.recipient',

    'status' =>
        'ln.status',

    'sent_at' =>
        'ln.sent_at',

    'created_at' =>
        'ln.created_at',

    'lead_reference' =>
        'l.lead_reference',

    'customer_name' =>
        'l.customer_name'
];


$sortByInput =
    isset($_GET['sort_by'])
        ? cleanValue($_GET['sort_by'])
        : 'id';


if (
    !isset(
        $sortColumns[$sortByInput]
    )
) {

    sendJsonResponse(
        422,
        false,
        'Invalid sort_by value.'
    );
}


$sortBy =
    $sortColumns[$sortByInput];


/*
|--------------------------------------------------------------------------
| SORT ORDER
|--------------------------------------------------------------------------
*/

$sortOrder = 'DESC';

if (
    isset($_GET['sort_order'])
) {

    $sortOrderInput =
        strtoupper(
            cleanValue(
                $_GET['sort_order']
            )
        );

    if (
        !in_array(
            $sortOrderInput,
            [
                'ASC',
                'DESC'
            ],
            true
        )
    ) {

        sendJsonResponse(
            422,
            false,
            'sort_order must be ASC or DESC.'
        );
    }

    $sortOrder =
        $sortOrderInput;
}


/*
|--------------------------------------------------------------------------
| OFFSET
|--------------------------------------------------------------------------
*/

$offset =
    ($page - 1) *
    $limit;


/*
|--------------------------------------------------------------------------
| BUILD WHERE
|--------------------------------------------------------------------------
*/

$where = [];

$params = [];

$types = '';


/*
|--------------------------------------------------------------------------
| LEAD ID FILTER
|--------------------------------------------------------------------------
*/

if (
    $leadId !== null
) {

    $where[] =
        'ln.lead_id = ?';

    $params[] =
        $leadId;

    $types .= 'i';
}


/*
|--------------------------------------------------------------------------
| DOMAIN FILTER
|--------------------------------------------------------------------------
*/

if (
    $domain !== null
) {

    $where[] =
        'l.domain = ?';

    $params[] =
        $domain;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| CHANNEL FILTER
|--------------------------------------------------------------------------
*/

if (
    $channel !== null
) {

    $where[] =
        'ln.channel = ?';

    $params[] =
        $channel;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| STATUS FILTER
|--------------------------------------------------------------------------
*/

if (
    $status !== null
) {

    $where[] =
        'ln.status = ?';

    $params[] =
        $status;

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| RECIPIENT FILTER
|--------------------------------------------------------------------------
*/

if (
    $recipient !== null
) {

    $where[] =
        'ln.recipient LIKE ?';

    $params[] =
        '%' .
        $recipient .
        '%';

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| SEARCH FILTER
|--------------------------------------------------------------------------
*/

if (
    $search !== null
) {

    $searchLike =
        '%' .
        $search .
        '%';

    $where[] = "

        (
            l.lead_reference LIKE ?
            OR l.customer_name LIKE ?
            OR l.customer_phone LIKE ?
            OR l.customer_email LIKE ?
            OR ln.recipient LIKE ?
        )

    ";

    $params[] =
        $searchLike;

    $params[] =
        $searchLike;

    $params[] =
        $searchLike;

    $params[] =
        $searchLike;

    $params[] =
        $searchLike;

    $types .=
        'sssss';
}


/*
|--------------------------------------------------------------------------
| FROM DATE
|--------------------------------------------------------------------------
*/

if (
    $fromDate !== null
) {

    $where[] =
        'ln.created_at >= ?';

    $params[] =
        $fromDate .
        ' 00:00:00';

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| TO DATE
|--------------------------------------------------------------------------
*/

if (
    $toDate !== null
) {

    $where[] =
        'ln.created_at <= ?';

    $params[] =
        $toDate .
        ' 23:59:59';

    $types .= 's';
}


/*
|--------------------------------------------------------------------------
| STAFF VISIBILITY
|--------------------------------------------------------------------------
|
| Admin:
|   Can see all notifications.
|
| Staff:
|   Can see only notifications for leads assigned
|   to their own admin_users.id.
|
|--------------------------------------------------------------------------
*/

if (
    $currentUserRole === 'staff'
) {

    $where[] =
        'l.assigned_admin_id = ?';

    $params[] =
        $currentUserId;

    $types .= 'i';
}


/*
|--------------------------------------------------------------------------
| WHERE SQL
|--------------------------------------------------------------------------
*/

$whereSql = '';

if (
    count($where) > 0
) {

    $whereSql =
        ' WHERE ' .
        implode(
            ' AND ',
            $where
        );
}


/*
|--------------------------------------------------------------------------
| TOTAL RECORD COUNT
|--------------------------------------------------------------------------
*/

$countSql = "

    SELECT
        COUNT(*) AS total

    FROM lead_notifications ln

    INNER JOIN leads l
        ON l.id = ln.lead_id

    {$whereSql}

";


$countStmt =
    $conn->prepare(
        $countSql
    );


if (
    !$countStmt
) {

    sendJsonResponse(
        500,
        false,
        'Failed to prepare count query.'
    );
}


if (
    count($params) > 0
) {

    $countStmt->bind_param(
        $types,
        ...$params
    );
}


if (
    !$countStmt->execute()
) {

    $error =
        $countStmt->error;

    $countStmt->close();

    sendJsonResponse(
        500,
        false,
        'Failed to count notification history.',
        [
            'error' => $error
        ]
    );
}


$countResult =
    $countStmt->get_result();


$countRow =
    $countResult->fetch_assoc();


$countStmt->close();


$totalRecords =
    (int)(
        $countRow['total']
        ?? 0
    );


$totalPages =
    $totalRecords > 0
        ? (int)ceil(
            $totalRecords /
            $limit
        )
        : 0;


/*
|--------------------------------------------------------------------------
| FETCH NOTIFICATION HISTORY
|--------------------------------------------------------------------------
*/

$dataSql = "

    SELECT

        ln.id,
        ln.lead_id,

        ln.channel,
        ln.recipient,
        ln.message,

        ln.status,

        ln.provider_message_id,

        ln.error_message,

        ln.sent_at,
        ln.created_at,

        l.lead_reference,
        l.domain,

        l.customer_name,
        l.customer_phone,
        l.customer_email,

        l.status AS lead_status,

        l.assigned_admin_id,

        au.name AS assigned_admin_name,
        au.email AS assigned_admin_email,
        au.phone AS assigned_admin_phone,
        au.role AS assigned_admin_role

    FROM lead_notifications ln

    INNER JOIN leads l
        ON l.id = ln.lead_id

    LEFT JOIN admin_users au
        ON au.id = l.assigned_admin_id

    {$whereSql}

    ORDER BY
        {$sortBy}
        {$sortOrder}

    LIMIT ?
    OFFSET ?

";


$dataParams =
    $params;


$dataTypes =
    $types .
    'ii';


$dataParams[] =
    $limit;

$dataParams[] =
    $offset;


$dataStmt =
    $conn->prepare(
        $dataSql
    );


if (
    !$dataStmt
) {

    sendJsonResponse(
        500,
        false,
        'Failed to prepare notification history query.'
    );
}


$dataStmt->bind_param(
    $dataTypes,
    ...$dataParams
);


if (
    !$dataStmt->execute()
) {

    $error =
        $dataStmt->error;

    $dataStmt->close();

    sendJsonResponse(
        500,
        false,
        'Failed to fetch notification history.',
        [
            'error' => $error
        ]
    );
}


$result =
    $dataStmt->get_result();


$notifications = [];


while (
    $row =
        $result->fetch_assoc()
) {

    $notifications[] = [

        'id' =>
            (int)$row['id'],

        'lead' => [

            'id' =>
                (int)$row['lead_id'],

            'reference' =>
                $row['lead_reference'],

            'domain' =>
                $row['domain'],

            'status' =>
                $row['lead_status']
        ],

        'customer' => [

            'name' =>
                $row['customer_name'],

            'phone' =>
                $row['customer_phone'],

            'email' =>
                $row['customer_email']
        ],

        'notification' => [

            'channel' =>
                $row['channel'],

            'recipient' =>
                $row['recipient'],

            'message' =>
                $row['message'],

            'status' =>
                $row['status'],

            'provider_message_id' =>
                $row['provider_message_id'],

            'error_message' =>
                $row['error_message'],

            'sent_at' =>
                $row['sent_at'],

            'created_at' =>
                $row['created_at']
        ],

        'assigned_admin' =>
            $row['assigned_admin_id'] !== null

                ? [

                    'id' =>
                        (int)$row[
                            'assigned_admin_id'
                        ],

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
                        ],

                    'role' =>
                        $row[
                            'assigned_admin_role'
                        ]
                ]

                : null
    ];
}


$dataStmt->close();


/*
|--------------------------------------------------------------------------
| CHANNEL SUMMARY
|--------------------------------------------------------------------------
*/

$channelSummary = [

    'email' => 0,

    'whatsapp' => 0
];


$channelSummarySql = "

    SELECT

        ln.channel,

        COUNT(*) AS total

    FROM lead_notifications ln

    INNER JOIN leads l
        ON l.id = ln.lead_id

    {$whereSql}

    GROUP BY ln.channel

";


$channelSummaryStmt =
    $conn->prepare(
        $channelSummarySql
    );


if (
    !$channelSummaryStmt
) {

    sendJsonResponse(
        500,
        false,
        'Failed to prepare channel summary query.'
    );
}


if (
    count($params) > 0
) {

    $channelSummaryStmt->bind_param(
        $types,
        ...$params
    );
}


if (
    !$channelSummaryStmt->execute()
) {

    $error =
        $channelSummaryStmt->error;

    $channelSummaryStmt->close();

    sendJsonResponse(
        500,
        false,
        'Failed to fetch channel summary.',
        [
            'error' => $error
        ]
    );
}


$channelResult =
    $channelSummaryStmt->get_result();


while (
    $row =
        $channelResult->fetch_assoc()
) {

    if (
        isset(
            $channelSummary[
                $row['channel']
            ]
        )
    ) {

        $channelSummary[
            $row['channel']
        ] =
            (int)$row['total'];
    }
}


$channelSummaryStmt->close();


/*
|--------------------------------------------------------------------------
| STATUS SUMMARY
|--------------------------------------------------------------------------
*/

$statusSummary = [

    'pending' => 0,

    'sent' => 0,

    'failed' => 0
];


$statusSummarySql = "

    SELECT

        ln.status,

        COUNT(*) AS total

    FROM lead_notifications ln

    INNER JOIN leads l
        ON l.id = ln.lead_id

    {$whereSql}

    GROUP BY ln.status

";


$statusSummaryStmt =
    $conn->prepare(
        $statusSummarySql
    );


if (
    !$statusSummaryStmt
) {

    sendJsonResponse(
        500,
        false,
        'Failed to prepare status summary query.'
    );
}


if (
    count($params) > 0
) {

    $statusSummaryStmt->bind_param(
        $types,
        ...$params
    );
}


if (
    !$statusSummaryStmt->execute()
) {

    $error =
        $statusSummaryStmt->error;

    $statusSummaryStmt->close();

    sendJsonResponse(
        500,
        false,
        'Failed to fetch status summary.',
        [
            'error' => $error
        ]
    );
}


$statusResult =
    $statusSummaryStmt->get_result();


while (
    $row =
        $statusResult->fetch_assoc()
) {

    if (
        isset(
            $statusSummary[
                $row['status']
            ]
        )
    ) {

        $statusSummary[
            $row['status']
        ] =
            (int)$row['total'];
    }
}


$statusSummaryStmt->close();


/*
|--------------------------------------------------------------------------
| FINAL SUMMARY
|--------------------------------------------------------------------------
*/

$summary = [

    'total' =>
        $totalRecords,

    'sent' =>
        $statusSummary['sent'],

    'pending' =>
        $statusSummary['pending'],

    'failed' =>
        $statusSummary['failed'],

    'email' =>
        $channelSummary['email'],

    'whatsapp' =>
        $channelSummary['whatsapp']
];


/*
|--------------------------------------------------------------------------
| FINAL RESPONSE
|--------------------------------------------------------------------------
*/

sendJsonResponse(

    200,

    true,

    'Notification history fetched successfully.',

    [

        /*
        |--------------------------------------------------------------------------
        | USER SCOPE
        |--------------------------------------------------------------------------
        */

        'user_scope' => [

            'role' =>
                $currentUserRole,

            'user_id' =>
                $currentUserId,

            'scope' =>
                $currentUserRole === 'staff'
                    ? 'assigned_leads_only'
                    : 'all_notifications'
        ],


        /*
        |--------------------------------------------------------------------------
        | APPLIED FILTERS
        |--------------------------------------------------------------------------
        */

        'filters' => [

            'lead_id' =>
                $leadId,

            'domain' =>
                $domain,

            'channel' =>
                $channel,

            'status' =>
                $status,

            'recipient' =>
                $recipient,

            'search' =>
                $search,

            'from_date' =>
                $fromDate,

            'to_date' =>
                $toDate,

            'sort_by' =>
                $sortByInput,

            'sort_order' =>
                $sortOrder
        ],


        /*
        |--------------------------------------------------------------------------
        | SUMMARY
        |--------------------------------------------------------------------------
        */

        'summary' =>
            $summary,


        /*
        |--------------------------------------------------------------------------
        | PAGINATION
        |--------------------------------------------------------------------------
        */

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
        | NOTIFICATIONS
        |--------------------------------------------------------------------------
        */

        'notifications' =>
            $notifications
    ]
);

?>