<?php
    
// =========================================================
// CORS
// =========================================================

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


// =========================================================
// OPTIONS
// =========================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'OPTIONS'
) {

    http_response_code(200);

    exit;
}


// =========================================================
// METHOD VALIDATION
// =========================================================

if (
    $_SERVER['REQUEST_METHOD'] !== 'GET'
) {

    http_response_code(405);

    echo json_encode([
        'status' => false,
        'message' => 'Only GET method is allowed.'
    ]);

    exit;
}


// =========================================================
// DATABASE
// =========================================================

require_once __DIR__ . '/../config/database.php';


// =========================================================
// ERROR RESPONSE FUNCTION
// =========================================================

function sendError($statusCode, $message)
{
    http_response_code($statusCode);

    echo json_encode(
        [
            'status' => false,
            'message' => $message
        ],
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


// =========================================================
// GET PARAMETERS
// =========================================================

$page =
    $_GET['page'] ?? '1';

$limit =
    $_GET['limit'] ?? '20';

$search =
    $_GET['search'] ?? null;

$roomTypeId =
    $_GET['room_type_id'] ?? null;

$minPrice =
    $_GET['min_price'] ?? null;

$maxPrice =
    $_GET['max_price'] ?? null;

$minGuestCapacity =
    $_GET['min_guest_capacity'] ?? null;

$maxGuestCapacity =
    $_GET['max_guest_capacity'] ?? null;

$sortBy =
    $_GET['sort_by'] ?? 'id';

$sortOrder =
    $_GET['sort_order'] ?? 'DESC';


// =========================================================
// PAGE VALIDATION
// =========================================================

if (
    !is_string($page) ||
    !ctype_digit($page) ||
    (int)$page <= 0
) {

    sendError(
        422,
        'Page must be a positive integer.'
    );
}

$page =
    (int)$page;


// =========================================================
// LIMIT VALIDATION
// =========================================================

if (
    !is_string($limit) ||
    !ctype_digit($limit) ||
    (int)$limit <= 0
) {

    sendError(
        422,
        'Limit must be a positive integer.'
    );
}

$limit =
    (int)$limit;


// =========================================================
// MAX LIMIT
// =========================================================

if (
    $limit > 100
) {

    sendError(
        422,
        'Limit cannot be greater than 100.'
    );
}


// =========================================================
// SEARCH VALIDATION
// =========================================================

if (
    $search !== null
) {

    if (
        !is_string($search)
    ) {

        sendError(
            422,
            'Search must be a valid string.'
        );
    }

    $search =
        trim($search);


    if (
        strlen($search) > 100
    ) {

        sendError(
            422,
            'Search cannot exceed 100 characters.'
        );
    }


    if (
        $search === ''
    ) {

        $search = null;
    }
}


// =========================================================
// ROOM TYPE ID VALIDATION
// =========================================================

if (
    $roomTypeId !== null
) {

    if (
        !is_string($roomTypeId) ||
        !ctype_digit($roomTypeId) ||
        (int)$roomTypeId <= 0
    ) {

        sendError(
            422,
            'Room type ID must be a positive integer.'
        );
    }

    $roomTypeId =
        (int)$roomTypeId;
}


// =========================================================
// MIN PRICE VALIDATION
// =========================================================

if (
    $minPrice !== null
) {

    if (
        !is_string($minPrice)
    ) {

        sendError(
            422,
            'Minimum price must be a valid number.'
        );
    }


    if (
        !preg_match(
            '/^\d+(?:\.\d{1,2})?$/',
            $minPrice
        )
    ) {

        sendError(
            422,
            'Minimum price must be a valid number with maximum 2 decimal places.'
        );
    }


    $minPrice =
        (float)$minPrice;


    if (
        $minPrice < 0
    ) {

        sendError(
            422,
            'Minimum price cannot be negative.'
        );
    }


    if (
        $minPrice > 10000000
    ) {

        sendError(
            422,
            'Minimum price cannot exceed 10000000.'
        );
    }
}


// =========================================================
// MAX PRICE VALIDATION
// =========================================================

if (
    $maxPrice !== null
) {

    if (
        !is_string($maxPrice)
    ) {

        sendError(
            422,
            'Maximum price must be a valid number.'
        );
    }


    if (
        !preg_match(
            '/^\d+(?:\.\d{1,2})?$/',
            $maxPrice
        )
    ) {

        sendError(
            422,
            'Maximum price must be a valid number with maximum 2 decimal places.'
        );
    }


    $maxPrice =
        (float)$maxPrice;


    if (
        $maxPrice < 0
    ) {

        sendError(
            422,
            'Maximum price cannot be negative.'
        );
    }


    if (
        $maxPrice > 10000000
    ) {

        sendError(
            422,
            'Maximum price cannot exceed 10000000.'
        );
    }
}


// =========================================================
// PRICE RANGE VALIDATION
// =========================================================

if (
    $minPrice !== null &&
    $maxPrice !== null
) {

    if (
        $minPrice > $maxPrice
    ) {

        sendError(
            422,
            'Minimum price cannot be greater than maximum price.'
        );
    }
}


// =========================================================
// MIN GUEST CAPACITY
// =========================================================

if (
    $minGuestCapacity !== null
) {

    if (
        !is_string($minGuestCapacity) ||
        !ctype_digit($minGuestCapacity) ||
        (int)$minGuestCapacity <= 0
    ) {

        sendError(
            422,
            'Minimum guest capacity must be a positive integer.'
        );
    }


    $minGuestCapacity =
        (int)$minGuestCapacity;


    if (
        $minGuestCapacity > 50
    ) {

        sendError(
            422,
            'Minimum guest capacity cannot be greater than 50.'
        );
    }
}


// =========================================================
// MAX GUEST CAPACITY
// =========================================================

if (
    $maxGuestCapacity !== null
) {

    if (
        !is_string($maxGuestCapacity) ||
        !ctype_digit($maxGuestCapacity) ||
        (int)$maxGuestCapacity <= 0
    ) {

        sendError(
            422,
            'Maximum guest capacity must be a positive integer.'
        );
    }


    $maxGuestCapacity =
        (int)$maxGuestCapacity;


    if (
        $maxGuestCapacity > 50
    ) {

        sendError(
            422,
            'Maximum guest capacity cannot be greater than 50.'
        );
    }
}


// =========================================================
// GUEST CAPACITY RANGE
// =========================================================

if (
    $minGuestCapacity !== null &&
    $maxGuestCapacity !== null
) {

    if (
        $minGuestCapacity >
        $maxGuestCapacity
    ) {

        sendError(
            422,
            'Minimum guest capacity cannot be greater than maximum guest capacity.'
        );
    }
}


// =========================================================
// SORT COLUMN WHITELIST
// =========================================================

$allowedSortColumns = [

    'id' =>
        'r.id',

    'room_type_id' =>
        'r.room_type_id',

    'room_number' =>
        'r.room_number',

    'name' =>
        'r.name',

    'bed_count' =>
        'r.bed_count',

    'guest_capacity' =>
        'r.guest_capacity',

    'price_per_night' =>
        'r.price_per_night',

    'gst_rate' =>
        'r.gst_rate',

    'created_at' =>
        'r.created_at',

    'updated_at' =>
        'r.updated_at'

];


if (
    !is_string($sortBy) ||
    !array_key_exists(
        $sortBy,
        $allowedSortColumns
    )
) {

    sendError(
        422,
        'Invalid sort_by value.'
    );
}


// =========================================================
// SORT ORDER VALIDATION
// =========================================================

if (
    !is_string($sortOrder)
) {

    sendError(
        422,
        'Sort order must be ASC or DESC.'
    );
}


$sortOrder =
    strtoupper(
        trim($sortOrder)
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


// =========================================================
// OFFSET
// =========================================================

$offset =
    ($page - 1) * $limit;


// =========================================================
// WHERE CONDITIONS
// =========================================================

$whereConditions = [];

$bindTypes = '';

$bindValues = [];


// =========================================================
// PUBLIC ROOM STATUS
// =========================================================
//
// IMPORTANT:
//
// rooms.status enum:
//
// available
// maintenance
// inactive
//
// Customer/public listing should show ONLY
// available rooms.
//
// =========================================================

$whereConditions[] =
    "r.status = 'available'";


// =========================================================
// ROOM TYPE STATUS
// =========================================================
//
// room_types.status:
//
// active
// inactive
//
// Only active room types should appear publicly.
// =========================================================

$whereConditions[] =
    "rt.status = 'active'";


// =========================================================
// SEARCH
// =========================================================

if (
    $search !== null
) {

    $whereConditions[] = "
        (
            r.room_number LIKE ?
            OR r.name LIKE ?
            OR r.description LIKE ?
            OR rt.name LIKE ?
            OR rt.slug LIKE ?
            OR rt.short_description LIKE ?
        )
    ";


    $searchValue =
        '%' . $search . '%';


    $bindTypes .=
        'ssssss';


    $bindValues[] =
        $searchValue;

    $bindValues[] =
        $searchValue;

    $bindValues[] =
        $searchValue;

    $bindValues[] =
        $searchValue;

    $bindValues[] =
        $searchValue;

    $bindValues[] =
        $searchValue;
}


// =========================================================
// ROOM TYPE FILTER
// =========================================================

if (
    $roomTypeId !== null
) {

    $whereConditions[] =
        'r.room_type_id = ?';

    $bindTypes .=
        'i';

    $bindValues[] =
        $roomTypeId;
}


// =========================================================
// MIN PRICE
// =========================================================

if (
    $minPrice !== null
) {

    $whereConditions[] =
        'r.price_per_night >= ?';

    $bindTypes .=
        'd';

    $bindValues[] =
        $minPrice;
}


// =========================================================
// MAX PRICE
// =========================================================

if (
    $maxPrice !== null
) {

    $whereConditions[] =
        'r.price_per_night <= ?';

    $bindTypes .=
        'd';

    $bindValues[] =
        $maxPrice;
}


// =========================================================
// MIN GUEST CAPACITY
// =========================================================

if (
    $minGuestCapacity !== null
) {

    $whereConditions[] =
        'r.guest_capacity >= ?';

    $bindTypes .=
        'i';

    $bindValues[] =
        $minGuestCapacity;
}


// =========================================================
// MAX GUEST CAPACITY
// =========================================================

if (
    $maxGuestCapacity !== null
) {

    $whereConditions[] =
        'r.guest_capacity <= ?';

    $bindTypes .=
        'i';

    $bindValues[] =
        $maxGuestCapacity;
}


// =========================================================
// FINAL WHERE
// =========================================================

$whereSql =
    'WHERE ' .
    implode(
        ' AND ',
        $whereConditions
    );


// =========================================================
// COUNT QUERY
// =========================================================

$countSql = "
    SELECT
        COUNT(*) AS total

    FROM rooms r

    INNER JOIN room_types rt
        ON rt.id = r.room_type_id

    $whereSql
";


$countStmt =
    $conn->prepare(
        $countSql
    );


if (
    !$countStmt
) {

    sendError(
        500,
        'Failed to prepare room count query.'
    );
}


// =========================================================
// COUNT BIND
// =========================================================

if (
    !empty($bindValues)
) {

    $countBindParams = [];

    $countBindParams[] =
        $bindTypes;


    foreach (
        $bindValues
        as $key => $value
    ) {

        $countBindParams[] =
            &$bindValues[$key];
    }


    call_user_func_array(
        [
            $countStmt,
            'bind_param'
        ],
        $countBindParams
    );
}


// =========================================================
// COUNT EXECUTE
// =========================================================

if (
    !$countStmt->execute()
) {

    $countStmt->close();

    sendError(
        500,
        'Failed to execute room count query.'
    );
}


// =========================================================
// COUNT RESULT
// =========================================================

$countResult =
    $countStmt->get_result();


if (
    !$countResult
) {

    $countStmt->close();

    sendError(
        500,
        'Failed to process room count.'
    );
}


$countRow =
    $countResult->fetch_assoc();


$totalRecords =
    (int)(
        $countRow['total'] ?? 0
    );


$countStmt->close();


// =========================================================
// TOTAL PAGES
// =========================================================

$totalPages =
    $totalRecords > 0
        ? (int)ceil(
            $totalRecords / $limit
        )
        : 0;


// =========================================================
// MAIN QUERY
// =========================================================

$sortColumn =
    $allowedSortColumns[$sortBy];


$sql = "
    SELECT

        r.id,

        r.room_type_id,

        rt.name AS room_type_name,

        rt.slug AS room_type_slug,

        rt.short_description AS room_type_short_description,

        rt.description AS room_type_description,

        r.room_number,

        r.name,

        r.description,

        r.bed_count,

        r.guest_capacity,

        r.price_per_night,

        r.gst_rate,

        r.price_includes_gst,

        r.status,

        r.created_at,

        r.updated_at

    FROM rooms r

    INNER JOIN room_types rt
        ON rt.id = r.room_type_id

    $whereSql

    ORDER BY
        $sortColumn $sortOrder,
        r.id DESC

    LIMIT ? OFFSET ?
";


// =========================================================
// PREPARE MAIN QUERY
// =========================================================

$stmt =
    $conn->prepare(
        $sql
    );


if (
    !$stmt
) {

    sendError(
        500,
        'Failed to prepare room list query.'
    );
}


// =========================================================
// MAIN QUERY BIND
// =========================================================

$mainBindTypes =
    $bindTypes . 'ii';


$mainBindValues =
    $bindValues;


$mainBindValues[] =
    $limit;


$mainBindValues[] =
    $offset;


$mainBindParams = [];

$mainBindParams[] =
    $mainBindTypes;


foreach (
    $mainBindValues
    as $key => $value
) {

    $mainBindParams[] =
        &$mainBindValues[$key];
}


$bindResult =
    call_user_func_array(
        [
            $stmt,
            'bind_param'
        ],
        $mainBindParams
    );


if (
    $bindResult === false
) {

    $stmt->close();

    sendError(
        500,
        'Failed to bind room list parameters.'
    );
}


// =========================================================
// EXECUTE
// =========================================================

if (
    !$stmt->execute()
) {

    $stmt->close();

    sendError(
        500,
        'Failed to fetch rooms.'
    );
}


// =========================================================
// RESULT
// =========================================================

$result =
    $stmt->get_result();


if (
    !$result
) {

    $stmt->close();

    sendError(
        500,
        'Failed to process room list.'
    );
}


// =========================================================
// ROOMS ARRAY
// =========================================================

$rooms = [];


// =========================================================
// FETCH ROOMS
// =========================================================

while (
    $row =
        $result->fetch_assoc()
) {

    $roomId =
        (int)$row['id'];


    $pricePerNight =
        (float)$row['price_per_night'];


    $gstRate =
        (float)$row['gst_rate'];


    $priceIncludesGst =
        (
            (int)$row['price_includes_gst']
            === 1
        );


    // =====================================================
    // GST CALCULATION
    // =====================================================

    $basePricePerNight =
        null;

    $gstAmountPerNight =
        null;


    if (
        $priceIncludesGst &&
        $gstRate > 0
    ) {

        $basePricePerNight =
            $pricePerNight /
            (
                1 +
                ($gstRate / 100)
            );


        $gstAmountPerNight =
            $pricePerNight -
            $basePricePerNight;

    } elseif (
        !$priceIncludesGst &&
        $gstRate > 0
    ) {

        $basePricePerNight =
            $pricePerNight;


        $gstAmountPerNight =
            $pricePerNight *
            (
                $gstRate / 100
            );
    }


    if (
        $basePricePerNight !== null
    ) {

        $basePricePerNight =
            round(
                $basePricePerNight,
                2
            );
    }


    if (
        $gstAmountPerNight !== null
    ) {

        $gstAmountPerNight =
            round(
                $gstAmountPerNight,
                2
            );
    }


    // =====================================================
    // ROOM IMAGES
    // =====================================================

    $images = [];


    $imageSql = "
        SELECT

            id,

            image_path,

            alt_text,

            is_primary,

            sort_order,

            created_at

        FROM room_images

        WHERE room_id = ?

        ORDER BY
            is_primary DESC,
            sort_order ASC,
            id ASC
    ";


    $imageStmt =
        $conn->prepare(
            $imageSql
        );


    if (
        $imageStmt
    ) {

        $imageStmt->bind_param(
            'i',
            $roomId
        );


        if (
            $imageStmt->execute()
        ) {

            $imageResult =
                $imageStmt->get_result();


            while (
                $imageRow =
                    $imageResult->fetch_assoc()
            ) {

                $images[] = [

                    'id' =>
                        (int)$imageRow['id'],

                    'image_path' =>
                        $imageRow['image_path'],

                    'alt_text' =>
                        $imageRow['alt_text'],

                    'is_primary' =>
                        (
                            (int)$imageRow['is_primary']
                            === 1
                        ),

                    'sort_order' =>
                        (int)$imageRow['sort_order'],

                    'created_at' =>
                        $imageRow['created_at']

                ];
            }
        }


        $imageStmt->close();
    }


    // =====================================================
    // AMENITIES
    // =====================================================

    $amenities = [];


    $amenitySql = "
        SELECT

            a.id,

            a.name,

            a.icon,

            a.status

        FROM room_amenities ra

        INNER JOIN amenities a
            ON a.id = ra.amenity_id

        WHERE
            ra.room_id = ?

            AND a.status = 'active'

        ORDER BY
            a.name ASC
    ";


    $amenityStmt =
        $conn->prepare(
            $amenitySql
        );


    if (
        $amenityStmt
    ) {

        $amenityStmt->bind_param(
            'i',
            $roomId
        );


        if (
            $amenityStmt->execute()
        ) {

            $amenityResult =
                $amenityStmt->get_result();


            while (
                $amenityRow =
                    $amenityResult->fetch_assoc()
            ) {

                $amenities[] = [

                    'id' =>
                        (int)$amenityRow['id'],

                    'name' =>
                        $amenityRow['name'],

                    'icon' =>
                        $amenityRow['icon']

                ];
            }
        }


        $amenityStmt->close();
    }


    // =====================================================
    // FINAL ROOM RESPONSE
    // =====================================================

    $rooms[] = [

        'id' =>
            $roomId,


        'room_type_id' =>
            (int)$row['room_type_id'],


        'room_type' => [

            'name' =>
                $row['room_type_name'],

            'slug' =>
                $row['room_type_slug'],

            'short_description' =>
                $row['room_type_short_description'],

            'description' =>
                $row['room_type_description']

        ],


        'room_number' =>
            $row['room_number'],


        'name' =>
            $row['name'],


        'description' =>
            $row['description'],


        'bed_count' =>
            (int)$row['bed_count'],


        'guest_capacity' =>
            (int)$row['guest_capacity'],


        'price_per_night' =>
            $pricePerNight,


        'gst' => [

            'rate' =>
                $gstRate,

            'price_includes_gst' =>
                $priceIncludesGst,

            'base_price_per_night' =>
                $basePricePerNight,

            'gst_amount_per_night' =>
                $gstAmountPerNight

        ],


        'images' =>
            $images,


        'amenities' =>
            $amenities,


        'status' =>
            $row['status'],


        'created_at' =>
            $row['created_at'],


        'updated_at' =>
            $row['updated_at']

    ];
}


// =========================================================
// CLOSE MAIN STATEMENT
// =========================================================

$stmt->close();


// =========================================================
// SUCCESS RESPONSE
// =========================================================

http_response_code(200);


echo json_encode(
    [

        'status' => true,

        'message' =>
            'Rooms fetched successfully.',

        'data' => [

            'rooms' =>
                $rooms,

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
                    (
                        $page < $totalPages
                    ),

                'has_previous_page' =>
                    (
                        $page > 1 &&
                        $totalRecords > 0
                    )

            ]

        ]

    ],
    JSON_UNESCAPED_UNICODE
);

exit;