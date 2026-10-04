<?php

require_once __DIR__ . '/../../config/database.php';

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
        'rate_unit',
        'min_rate',
        'max_rate',
        'min_quantity',
        'max_quantity',
        'page',
        'limit',
        'sort_by',
        'sort_order'
    ];

    foreach ($_GET as $key => $value) {

        if (!in_array(
            $key,
            $allowedParams,
            true
        )) {

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

    $search = '';

    if (isset($_GET['search'])) {

        $search =
            trim((string)$_GET['search']);

        if (mb_strlen($search) > 150) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "Search cannot exceed 150 characters."
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
        'active',
        'inactive'
    ];

    $status = '';

    if (isset($_GET['status'])) {

        $status =
            trim((string)$_GET['status']);

        if (
            $status !== '' &&
            !in_array(
                $status,
                $allowedStatuses,
                true
            )
        ) {

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
    | RATE UNIT
    |--------------------------------------------------------------------------
    */

    $allowedRateUnits = [
        'per_person',
        'per_plate',
        'per_event',
        'per_package'
    ];

    $rateUnit = '';

    if (isset($_GET['rate_unit'])) {

        $rateUnit =
            trim((string)$_GET['rate_unit']);

        if (
            $rateUnit !== '' &&
            !in_array(
                $rateUnit,
                $allowedRateUnits,
                true
            )
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "Invalid rate_unit.",
                "allowed_rate_units" => $allowedRateUnits
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MIN RATE
    |--------------------------------------------------------------------------
    */

    $minRate = null;

    if (
        isset($_GET['min_rate']) &&
        trim((string)$_GET['min_rate']) !== ''
    ) {

        $minRateRaw =
            trim((string)$_GET['min_rate']);

        /*
        | Maximum 2 decimal places
        */

        if (!preg_match(
            '/^\d{1,10}(\.\d{1,2})?$/',
            $minRateRaw
        )) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "min_rate must be a valid decimal with maximum 2 decimal places."
            ]);

            exit;
        }

        $minRate =
            (float)$minRateRaw;

        if (
            $minRate < 0 ||
            $minRate > 9999999999.99
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "min_rate is out of allowed range."
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MAX RATE
    |--------------------------------------------------------------------------
    */

    $maxRate = null;

    if (
        isset($_GET['max_rate']) &&
        trim((string)$_GET['max_rate']) !== ''
    ) {

        $maxRateRaw =
            trim((string)$_GET['max_rate']);

        if (!preg_match(
            '/^\d{1,10}(\.\d{1,2})?$/',
            $maxRateRaw
        )) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "max_rate must be a valid decimal with maximum 2 decimal places."
            ]);

            exit;
        }

        $maxRate =
            (float)$maxRateRaw;

        if (
            $maxRate < 0 ||
            $maxRate > 9999999999.99
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "max_rate is out of allowed range."
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | RATE RANGE VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        $minRate !== null &&
        $maxRate !== null &&
        $minRate > $maxRate
    ) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "min_rate cannot be greater than max_rate."
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | MINIMUM QUANTITY
    |--------------------------------------------------------------------------
    */

    $minQuantity = null;

    if (
        isset($_GET['min_quantity']) &&
        trim((string)$_GET['min_quantity']) !== ''
    ) {

        $minQuantityRaw =
            trim((string)$_GET['min_quantity']);

        if (
            !ctype_digit($minQuantityRaw) ||
            (int)$minQuantityRaw < 1
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "min_quantity must be a positive integer."
            ]);

            exit;
        }

        $minQuantity =
            (int)$minQuantityRaw;

        if ($minQuantity > 100000) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "min_quantity cannot exceed 100000."
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MAXIMUM QUANTITY
    |--------------------------------------------------------------------------
    */

    $maxQuantity = null;

    if (
        isset($_GET['max_quantity']) &&
        trim((string)$_GET['max_quantity']) !== ''
    ) {

        $maxQuantityRaw =
            trim((string)$_GET['max_quantity']);

        if (
            !ctype_digit($maxQuantityRaw) ||
            (int)$maxQuantityRaw < 1
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "max_quantity must be a positive integer."
            ]);

            exit;
        }

        $maxQuantity =
            (int)$maxQuantityRaw;

        if ($maxQuantity > 100000) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "max_quantity cannot exceed 100000."
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | QUANTITY RANGE VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        $minQuantity !== null &&
        $maxQuantity !== null &&
        $minQuantity > $maxQuantity
    ) {

        http_response_code(422);

        echo json_encode([
            "status" => false,
            "message" => "min_quantity cannot be greater than max_quantity."
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
            (int)$limitRaw < 1 ||
            (int)$limitRaw > 100
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "limit must be between 1 and 100."
            ]);

            exit;
        }

        $limit =
            (int)$limitRaw;
    }

    /*
    |--------------------------------------------------------------------------
    | SORT BY
    |--------------------------------------------------------------------------
    */

    $allowedSortColumns = [
        'id' => 'cp.id',
        'name' => 'cp.name',
        'rate' => 'cp.rate',
        'rate_unit' => 'cp.rate_unit',
        'minimum_quantity' => 'cp.minimum_quantity',
        'status' => 'cp.status',
        'created_at' => 'cp.created_at',
        'updated_at' => 'cp.updated_at'
    ];

    $sortBy = 'created_at';

    if (
        isset($_GET['sort_by']) &&
        trim((string)$_GET['sort_by']) !== ''
    ) {

        $sortBy =
            trim((string)$_GET['sort_by']);

        if (
            !array_key_exists(
                $sortBy,
                $allowedSortColumns
            )
        ) {

            http_response_code(422);

            echo json_encode([
                "status" => false,
                "message" => "Invalid sort_by.",
                "allowed_sort_by" =>
                    array_keys($allowedSortColumns)
            ]);

            exit;
        }
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

        $sortOrder =
            strtoupper(
                trim((string)$_GET['sort_order'])
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
                "message" => "sort_order must be ASC or DESC."
            ]);

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | BUILD WHERE
    |--------------------------------------------------------------------------
    */

    $where = [];

    $params = [];

    $types = '';

    /*
    | Search
    */

    if ($search !== '') {

        $where[] = "
            (
                cp.name LIKE ?
                OR cp.description LIKE ?
            )
        ";

        $searchValue =
            '%' . $search . '%';

        $params[] =
            $searchValue;

        $params[] =
            $searchValue;

        $types .= 'ss';
    }

    /*
    | Status
    */

    if ($status !== '') {

        $where[] =
            "cp.status = ?";

        $params[] =
            $status;

        $types .= 's';
    }

    /*
    | Rate unit
    */

    if ($rateUnit !== '') {

        $where[] =
            "cp.rate_unit = ?";

        $params[] =
            $rateUnit;

        $types .= 's';
    }

    /*
    | Min rate
    */

    if ($minRate !== null) {

        $where[] =
            "cp.rate >= ?";

        $params[] =
            $minRate;

        $types .= 'd';
    }

    /*
    | Max rate
    */

    if ($maxRate !== null) {

        $where[] =
            "cp.rate <= ?";

        $params[] =
            $maxRate;

        $types .= 'd';
    }

    /*
    | Min quantity
    */

    if ($minQuantity !== null) {

        $where[] =
            "cp.minimum_quantity >= ?";

        $params[] =
            $minQuantity;

        $types .= 'i';
    }

    /*
    | Max quantity
    */

    if ($maxQuantity !== null) {

        $where[] =
            "cp.minimum_quantity <= ?";

        $params[] =
            $maxQuantity;

        $types .= 'i';
    }

    /*
    |--------------------------------------------------------------------------
    | WHERE SQL
    |--------------------------------------------------------------------------
    */

    $whereSql = '';

    if (!empty($where)) {

        $whereSql =
            "WHERE " .
            implode(
                " AND ",
                $where
            );
    }

    /*
    |--------------------------------------------------------------------------
    | TOTAL COUNT
    |--------------------------------------------------------------------------
    */

    $countSql = "
        SELECT COUNT(*) AS total
        FROM catering_packages cp
        {$whereSql}
    ";

    $countStmt =
        $conn->prepare($countSql);

    if (!$countStmt) {

        throw new Exception(
            "Failed to prepare total count query."
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
            "Failed to execute total count query: " .
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
        $totalPages > 0 &&
        $page > $totalPages
    ) {

        http_response_code(200);

        echo json_encode([
            "status" => true,
            "message" =>
                "No records found for the requested page.",

            "data" => [],

            "pagination" => [
                "current_page" =>
                    $page,

                "per_page" =>
                    $limit,

                "total_records" =>
                    $totalRecords,

                "total_pages" =>
                    $totalPages
            ]
        ]);

        exit;
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
    | SORT COLUMN
    |--------------------------------------------------------------------------
    */

    $sortColumn =
        $allowedSortColumns[$sortBy];

    /*
    |--------------------------------------------------------------------------
    | MAIN QUERY
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            cp.id,
            cp.name,
            cp.description,
            cp.rate,
            cp.rate_unit,
            cp.gst_rate,
            cp.price_includes_gst,
            cp.minimum_quantity,
            cp.image_path,
            cp.status,
            cp.created_at,
            cp.updated_at,

            (
                SELECT COUNT(*)
                FROM catering_package_items cpi_count
                WHERE cpi_count.package_id = cp.id
            ) AS item_count,

            (
                SELECT COUNT(*)
                FROM catering_lead_details cld_count
                WHERE cld_count.catering_package_id = cp.id
            ) AS lead_reference_count

        FROM catering_packages cp

        {$whereSql}

        ORDER BY {$sortColumn} {$sortOrder}

        LIMIT ? OFFSET ?
    ";

    /*
    |--------------------------------------------------------------------------
    | MAIN QUERY PARAMS
    |--------------------------------------------------------------------------
    */

    $mainParams =
        $params;

    $mainTypes =
        $types . 'ii';

    $mainParams[] =
        $limit;

    $mainParams[] =
        $offset;

    /*
    |--------------------------------------------------------------------------
    | PREPARE MAIN QUERY
    |--------------------------------------------------------------------------
    */

    $stmt =
        $conn->prepare($sql);

    if (!$stmt) {

        throw new Exception(
            "Failed to prepare catering package list query."
        );
    }

    $stmt->bind_param(
        $mainTypes,
        ...$mainParams
    );

    if (!$stmt->execute()) {

        $error =
            $stmt->error;

        $stmt->close();

        throw new Exception(
            "Failed to execute catering package list query: " .
            $error
        );
    }

    $result =
        $stmt->get_result();

    $packages = [];

    /*
    |--------------------------------------------------------------------------
    | FETCH PACKAGES
    |--------------------------------------------------------------------------
    */

    while (
        $row =
        $result->fetch_assoc()
    ) {

        $packageId =
            (int)$row['id'];

        /*
        |--------------------------------------------------------------------------
        | FETCH PACKAGE ITEMS
        |--------------------------------------------------------------------------
        */

        $packageItems = [];

        $itemsSql = "
            SELECT
                cpi.food_item_id,
                cpi.quantity,

                fi.name AS food_item_name,
                fi.description AS food_item_description,
                fi.food_type,
                fi.image_path AS food_item_image,

                fc.id AS food_category_id,
                fc.name AS food_category_name

            FROM catering_package_items cpi

            INNER JOIN food_items fi
                ON fi.id = cpi.food_item_id

            LEFT JOIN food_categories fc
                ON fc.id = fi.food_category_id

            WHERE cpi.package_id = ?

            ORDER BY fi.name ASC
        ";

        $itemsStmt =
            $conn->prepare($itemsSql);

        if (!$itemsStmt) {

            $stmt->close();

            throw new Exception(
                "Failed to prepare package items query."
            );
        }

        $itemsStmt->bind_param(
            "i",
            $packageId
        );

        if (!$itemsStmt->execute()) {

            $error =
                $itemsStmt->error;

            $itemsStmt->close();
            $stmt->close();

            throw new Exception(
                "Failed to fetch package items: " .
                $error
            );
        }

        $itemsResult =
            $itemsStmt->get_result();

        while (
            $item =
            $itemsResult->fetch_assoc()
        ) {

            $packageItems[] = [
                "food_item_id" =>
                    (int)$item['food_item_id'],

                "food_item_name" =>
                    $item['food_item_name'],

                "food_item_description" =>
                    $item['food_item_description'],

                "food_type" =>
                    $item['food_type'],

                "food_item_image" =>
                    $item['food_item_image'],

                "category" =>
                    $item['food_category_id'] !== null
                        ? [
                            "id" =>
                                (int)$item['food_category_id'],

                            "name" =>
                                $item['food_category_name']
                        ]
                        : null,

                "quantity" =>
                    (int)$item['quantity']
            ];
        }

        $itemsStmt->close();

        /*
        |--------------------------------------------------------------------------
        | PACKAGE RESPONSE
        |--------------------------------------------------------------------------
        */

        $packages[] = [
            "id" =>
                $packageId,

            "name" =>
                $row['name'],

            "description" =>
                $row['description'],

            "rate" =>
                (float)$row['rate'],

            "rate_unit" =>
                $row['rate_unit'],

            "gst_rate" =>
                (float)$row['gst_rate'],

            "price_includes_gst" =>
                (bool)$row['price_includes_gst'],

            "minimum_quantity" =>
                (int)$row['minimum_quantity'],

            "image_path" =>
                $row['image_path'],

            "status" =>
                $row['status'],

            "item_count" =>
                (int)$row['item_count'],

            "lead_reference_count" =>
                (int)$row['lead_reference_count'],

            "items" =>
                $packageItems,

            "created_at" =>
                $row['created_at'],

            "updated_at" =>
                $row['updated_at']
        ];
    }

    $stmt->close();

    /*
    |--------------------------------------------------------------------------
    | SUCCESS RESPONSE
    |--------------------------------------------------------------------------
    */

    http_response_code(200);

    echo json_encode([
        "status" => true,
        "message" =>
            "Catering packages fetched successfully.",

        "data" =>
            $packages,

        "filters" => [
            "search" =>
                $search !== ''
                    ? $search
                    : null,

            "status" =>
                $status !== ''
                    ? $status
                    : null,

            "rate_unit" =>
                $rateUnit !== ''
                    ? $rateUnit
                    : null,

            "min_rate" =>
                $minRate,

            "max_rate" =>
                $maxRate,

            "min_quantity" =>
                $minQuantity,

            "max_quantity" =>
                $maxQuantity
        ],

        "pagination" => [
            "current_page" =>
                $page,

            "per_page" =>
                $limit,

            "total_records" =>
                $totalRecords,

            "total_pages" =>
                $totalPages
        ],

        "sorting" => [
            "sort_by" =>
                $sortBy,

            "sort_order" =>
                $sortOrder
        ]
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        "status" => false,
        "message" =>
            "Failed to fetch catering packages.",
        "error" =>
            $e->getMessage()
    ]);
}