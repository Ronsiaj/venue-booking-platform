<?php

/*
|--------------------------------------------------------------------------
| CORS Headers
|--------------------------------------------------------------------------
*/

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");


/*
|--------------------------------------------------------------------------
| Handle OPTIONS Preflight
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {

    http_response_code(200);
    exit;
}


/*
|--------------------------------------------------------------------------
| Request Method
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
| Include Database
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/database.php';


/*
|--------------------------------------------------------------------------
| Helper Function - Error Response
|--------------------------------------------------------------------------
*/

function sendError($httpCode, $message, $data = null)
{
    http_response_code($httpCode);

    $response = [
        'status' => false,
        'message' => $message
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode(
        $response,
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Helper Function - Positive Integer
|--------------------------------------------------------------------------
*/

function isPositiveInteger($value)
{
    if (
        is_array($value) ||
        is_object($value)
    ) {
        return false;
    }

    if (
        !is_int($value) &&
        !ctype_digit((string) $value)
    ) {
        return false;
    }

    return (int) $value > 0;
}


/*
|--------------------------------------------------------------------------
| Allowed Query Parameters
|--------------------------------------------------------------------------
*/

$allowedParams = [
    'search',
    'page',
    'limit',
    'sort_by',
    'sort_order'
];


/*
|--------------------------------------------------------------------------
| Validate Unknown Query Parameters
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

        sendError(
            422,
            "Unknown query parameter: {$key}."
        );
    }
}


/*
|--------------------------------------------------------------------------
| Validate Search
|--------------------------------------------------------------------------
*/

$search = '';

if (isset($_GET['search'])) {

    if (
        is_array($_GET['search']) ||
        is_object($_GET['search'])
    ) {

        sendError(
            422,
            'search must be a valid string.'
        );
    }

    $search =
        trim(
            (string) $_GET['search']
        );

    if (
        strlen($search) > 100
    ) {

        sendError(
            422,
            'search cannot exceed 100 characters.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Validate Page
|--------------------------------------------------------------------------
*/

$page = 1;

if (isset($_GET['page'])) {

    if (
        !isPositiveInteger(
            $_GET['page']
        )
    ) {

        sendError(
            422,
            'page must be a valid positive integer.'
        );
    }

    $page =
        (int) $_GET['page'];
}


/*
|--------------------------------------------------------------------------
| Validate Limit
|--------------------------------------------------------------------------
*/

$limit = 20;

if (isset($_GET['limit'])) {

    if (
        !isPositiveInteger(
            $_GET['limit']
        )
    ) {

        sendError(
            422,
            'limit must be a valid positive integer.'
        );
    }

    $limit =
        (int) $_GET['limit'];

    if (
        $limit > 100
    ) {

        sendError(
            422,
            'limit cannot exceed 100.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Validate Sort By
|--------------------------------------------------------------------------
*/

$allowedSortColumns = [
    'id',
    'name'
];

$sortBy = 'name';

if (isset($_GET['sort_by'])) {

    if (
        is_array($_GET['sort_by']) ||
        is_object($_GET['sort_by'])
    ) {

        sendError(
            422,
            'sort_by must be a valid string.'
        );
    }

    $sortBy =
        trim(
            (string) $_GET['sort_by']
        );

    if (
        !in_array(
            $sortBy,
            $allowedSortColumns,
            true
        )
    ) {

        sendError(
            422,
            'Invalid sort_by. Allowed values are id and name.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Validate Sort Order
|--------------------------------------------------------------------------
*/

$sortOrder = 'ASC';

if (isset($_GET['sort_order'])) {

    if (
        is_array($_GET['sort_order']) ||
        is_object($_GET['sort_order'])
    ) {

        sendError(
            422,
            'sort_order must be a valid string.'
        );
    }

    $sortOrder =
        strtoupper(
            trim(
                (string) $_GET['sort_order']
            )
        );

    if (
        !in_array(
            $sortOrder,
            ['ASC', 'DESC'],
            true
        )
    ) {

        sendError(
            422,
            'sort_order must be ASC or DESC.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Build WHERE Conditions
|--------------------------------------------------------------------------
|
| Only active amenities are returned.
|
*/

$whereConditions = [
    "a.status = 'active'"
];

$queryParams = [];
$queryTypes = '';


/*
|--------------------------------------------------------------------------
| Search Filter
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $whereConditions[] = "
        (
            a.name LIKE ?
            OR a.icon LIKE ?
        )
    ";

    $searchValue =
        '%' . $search . '%';

    $queryParams[] =
        $searchValue;

    $queryParams[] =
        $searchValue;

    $queryTypes .= 'ss';
}


/*
|--------------------------------------------------------------------------
| Final WHERE Clause
|--------------------------------------------------------------------------
*/

$whereClause =
    implode(
        ' AND ',
        $whereConditions
    );


/*
|--------------------------------------------------------------------------
| Count Total Amenities
|--------------------------------------------------------------------------
*/

$countSql = "
    SELECT
        COUNT(*) AS total

    FROM amenities a

    WHERE {$whereClause}
";


$countStmt =
    $conn->prepare($countSql);


if (!$countStmt) {

    sendError(
        500,
        'Failed to prepare amenity count query.'
    );
}


/*
|--------------------------------------------------------------------------
| Bind Count Parameters
|--------------------------------------------------------------------------
*/

if (
    !empty($queryParams)
) {

    $bindParams = [];

    $bindParams[] =
        $queryTypes;

    foreach (
        $queryParams as $key => $value
    ) {

        $bindParams[] =
            &$queryParams[$key];
    }

    call_user_func_array(
        [$countStmt, 'bind_param'],
        $bindParams
    );
}


/*
|--------------------------------------------------------------------------
| Execute Count Query
|--------------------------------------------------------------------------
*/

if (
    !$countStmt->execute()
) {

    $countStmt->close();

    sendError(
        500,
        'Failed to count amenities.'
    );
}


/*
|--------------------------------------------------------------------------
| Get Count Result
|--------------------------------------------------------------------------
*/

$countResult =
    $countStmt->get_result();

$countRow =
    $countResult->fetch_assoc();

$total =
    (int) (
        $countRow['total']
        ?? 0
    );


$countStmt->close();


/*
|--------------------------------------------------------------------------
| Calculate Pagination
|--------------------------------------------------------------------------
*/

$totalPages =
    $total > 0
        ? (int) ceil($total / $limit)
        : 0;


/*
|--------------------------------------------------------------------------
| Validate Requested Page
|--------------------------------------------------------------------------
*/

if (
    $total > 0 &&
    $page > $totalPages
) {

    sendError(
        404,
        'Page not found.',
        [
            'page' =>
                $page,

            'total_pages' =>
                $totalPages
        ]
    );
}


/*
|--------------------------------------------------------------------------
| Calculate Offset
|--------------------------------------------------------------------------
*/

$offset =
    ($page - 1) * $limit;


/*
|--------------------------------------------------------------------------
| Sort Column Whitelist
|--------------------------------------------------------------------------
*/

$sortColumnMap = [

    'id' =>
        'a.id',

    'name' =>
        'a.name'
];


$orderBy =
    $sortColumnMap[$sortBy];


/*
|--------------------------------------------------------------------------
| Fetch Amenities
|--------------------------------------------------------------------------
|
| IMPORTANT:
| amenities table has NO updated_at column.
|
| Actual columns:
| id
| name
| icon
| status
| created_at
|
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT

        a.id,
        a.name,
        a.icon,
        a.status,
        a.created_at

    FROM amenities a

    WHERE {$whereClause}

    ORDER BY
        {$orderBy} {$sortOrder}

    LIMIT ? OFFSET ?
";


$stmt =
    $conn->prepare($sql);


if (!$stmt) {

    sendError(
        500,
        'Failed to prepare amenity list query.'
    );
}


/*
|--------------------------------------------------------------------------
| Prepare All Parameters
|--------------------------------------------------------------------------
*/

$allParams =
    $queryParams;

$allTypes =
    $queryTypes . 'ii';

$allParams[] =
    $limit;

$allParams[] =
    $offset;


/*
|--------------------------------------------------------------------------
| Dynamic Bind Parameters
|--------------------------------------------------------------------------
*/

$bindParams = [];

$bindParams[] =
    $allTypes;

foreach (
    $allParams as $key => $value
) {

    $bindParams[] =
        &$allParams[$key];
}


call_user_func_array(
    [$stmt, 'bind_param'],
    $bindParams
);


/*
|--------------------------------------------------------------------------
| Execute Query
|--------------------------------------------------------------------------
*/

if (
    !$stmt->execute()
) {

    $stmt->close();

    sendError(
        500,
        'Failed to fetch amenities.'
    );
}


/*
|--------------------------------------------------------------------------
| Get Result
|--------------------------------------------------------------------------
*/

$result =
    $stmt->get_result();


/*
|--------------------------------------------------------------------------
| Prepare Amenity List
|--------------------------------------------------------------------------
*/

$amenities = [];


while (
    $row = $result->fetch_assoc()
) {

    $amenities[] = [

        'id' =>
            (int) $row['id'],

        'name' =>
            $row['name'],

        'icon' =>
            $row['icon'],

        'status' =>
            $row['status'],

        'created_at' =>
            $row['created_at']
    ];
}


$stmt->close();


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

    'total' =>
        $total,

    'total_pages' =>
        $totalPages,

    'has_next_page' =>
        $page < $totalPages,

    'has_previous_page' =>
        $page > 1
];


/*
|--------------------------------------------------------------------------
| Success Response
|--------------------------------------------------------------------------
*/

http_response_code(200);

echo json_encode([

    'status' => true,

    'message' =>
        'Amenities fetched successfully.',

    'data' => [

        'amenities' =>
            $amenities,

        'pagination' =>
            $pagination
    ]

], JSON_UNESCAPED_UNICODE);

exit;