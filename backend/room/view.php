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
// OPTIONS / PREFLIGHT
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
// VALIDATE QUERY PARAMETERS
// =========================================================
//
// This API only accepts:
// room_id
//
// Reject unexpected query parameters.
// =========================================================

$allowedParams = [
    'room_id'
];

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

        sendError(
            422,
            'Invalid query parameter: ' . $key
        );
    }
}


// =========================================================
// ROOM ID REQUIRED
// =========================================================

if (
    !isset($_GET['room_id'])
) {

    sendError(
        422,
        'Room ID is required.'
    );
}


$roomId =
    $_GET['room_id'];


// =========================================================
// ROOM ID VALIDATION
// =========================================================

if (
    !is_string($roomId) ||
    !ctype_digit($roomId) ||
    (int)$roomId <= 0
) {

    sendError(
        422,
        'Room ID must be a positive integer.'
    );
}


$roomId =
    (int)$roomId;


// =========================================================
// FETCH ROOM
// =========================================================
//
// Public room details:
// - Room must be available
// - Room type must be active
//
// rooms.status:
// available
// maintenance
// inactive
//
// room_types.status:
// active
// inactive
// =========================================================

$sql = "
    SELECT

        r.id,

        r.room_type_id,

        rt.name AS room_type_name,

        rt.slug AS room_type_slug,

        rt.short_description
            AS room_type_short_description,

        rt.description
            AS room_type_description,

        rt.default_bed_count
            AS room_type_default_bed_count,

        rt.default_guest_capacity
            AS room_type_default_guest_capacity,

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

    WHERE
        r.id = ?

        AND r.status = 'available'

        AND rt.status = 'active'

    LIMIT 1
";


$stmt =
    $conn->prepare($sql);


if (
    !$stmt
) {

    sendError(
        500,
        'Failed to prepare room view query.'
    );
}


// =========================================================
// BIND ROOM ID
// =========================================================

$stmt->bind_param(
    'i',
    $roomId
);


// =========================================================
// EXECUTE
// =========================================================

if (
    !$stmt->execute()
) {

    $stmt->close();

    sendError(
        500,
        'Failed to fetch room details.'
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
        'Failed to process room details.'
    );
}


// =========================================================
// ROOM NOT FOUND
// =========================================================

if (
    $result->num_rows === 0
) {

    $stmt->close();

    http_response_code(404);

    echo json_encode(
        [
            'status' => false,
            'message' =>
                'Room not found or currently unavailable.'
        ],
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


// =========================================================
// FETCH ROOM ROW
// =========================================================

$row =
    $result->fetch_assoc();


$stmt->close();


// =========================================================
// BASIC VALUES
// =========================================================

$pricePerNight =
    (float)$row['price_per_night'];


$gstRate =
    (float)$row['gst_rate'];


$priceIncludesGst =
    (
        (int)$row['price_includes_gst']
        === 1
    );


// =========================================================
// GST CALCULATION
// =========================================================

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


// =========================================================
// FETCH ROOM IMAGES
// =========================================================

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
    !$imageStmt
) {

    sendError(
        500,
        'Failed to prepare room image query.'
    );
}


$imageStmt->bind_param(
    'i',
    $roomId
);


if (
    !$imageStmt->execute()
) {

    $imageStmt->close();

    sendError(
        500,
        'Failed to fetch room images.'
    );
}


$imageResult =
    $imageStmt->get_result();


if (
    !$imageResult
) {

    $imageStmt->close();

    sendError(
        500,
        'Failed to process room images.'
    );
}


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


$imageStmt->close();


// =========================================================
// FETCH ROOM AMENITIES
// =========================================================

$amenities = [];


$amenitySql = "
    SELECT

        a.id,

        a.name,

        a.icon

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
    !$amenityStmt
) {

    sendError(
        500,
        'Failed to prepare room amenity query.'
    );
}


$amenityStmt->bind_param(
    'i',
    $roomId
);


if (
    !$amenityStmt->execute()
) {

    $amenityStmt->close();

    sendError(
        500,
        'Failed to fetch room amenities.'
    );
}


$amenityResult =
    $amenityStmt->get_result();


if (
    !$amenityResult
) {

    $amenityStmt->close();

    sendError(
        500,
        'Failed to process room amenities.'
    );
}


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


$amenityStmt->close();


// =========================================================
// FINAL RESPONSE
// =========================================================

$room = [

    'id' =>
        (int)$row['id'],


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
            $row['room_type_description'],

        'default_bed_count' =>
            (int)$row['room_type_default_bed_count'],

        'default_guest_capacity' =>
            (int)$row['room_type_default_guest_capacity']

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


// =========================================================
// SUCCESS RESPONSE
// =========================================================

http_response_code(200);


echo json_encode(
    [
        'status' => true,

        'message' =>
            'Room details fetched successfully.',

        'data' => [
            'room' => $room
        ]
    ],
    JSON_UNESCAPED_UNICODE
);

exit;