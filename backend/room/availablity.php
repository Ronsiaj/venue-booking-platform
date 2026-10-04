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
| Helper Functions
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
| Positive Integer Validation
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
| Date Validation
|--------------------------------------------------------------------------
*/

function isValidDateFormat($date)
{
    if (!is_string($date)) {
        return false;
    }

    $date = trim($date);

    if ($date === '') {
        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | Strict YYYY-MM-DD Format
    |--------------------------------------------------------------------------
    */

    if (
        !preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $date
        )
    ) {
        return false;
    }

    $dateObject = DateTime::createFromFormat(
        '!Y-m-d',
        $date
    );

    $errors = DateTime::getLastErrors();

    if ($errors !== false) {

        if (
            $errors['warning_count'] > 0 ||
            $errors['error_count'] > 0
        ) {
            return false;
        }
    }

    if (!$dateObject) {
        return false;
    }

    return $dateObject->format('Y-m-d') === $date;
}


/*
|--------------------------------------------------------------------------
| Get Current Date
|--------------------------------------------------------------------------
*/

$currentDate = new DateTime(
    'today',
    new DateTimeZone('Asia/Kolkata')
);

$currentDateString = $currentDate->format('Y-m-d');


/*
|--------------------------------------------------------------------------
| Allowed Query Parameters
|--------------------------------------------------------------------------
*/

$allowedQueryParameters = [
    'room_id',
    'check_in_date',
    'check_out_date',
    'guests',
    'room_type_id',
    'search',
    'min_price',
    'max_price',
    'page',
    'limit',
    'sort_by',
    'sort_order'
];


/*
|--------------------------------------------------------------------------
| Check Unknown Query Parameters
|--------------------------------------------------------------------------
*/

foreach ($_GET as $key => $value) {

    if (
        !in_array(
            $key,
            $allowedQueryParameters,
            true
        )
    ) {

        sendError(
            422,
            'Invalid query parameter: ' . $key
        );
    }
}


/*
|--------------------------------------------------------------------------
| Validate check_in_date
|--------------------------------------------------------------------------
*/

if (!isset($_GET['check_in_date'])) {

    sendError(
        422,
        'check_in_date is required.'
    );
}

$checkInDate = trim(
    (string) $_GET['check_in_date']
);

if (!isValidDateFormat($checkInDate)) {

    sendError(
        422,
        'check_in_date must be a valid date in YYYY-MM-DD format.'
    );
}


/*
|--------------------------------------------------------------------------
| Validate check_out_date
|--------------------------------------------------------------------------
*/

if (!isset($_GET['check_out_date'])) {

    sendError(
        422,
        'check_out_date is required.'
    );
}

$checkOutDate = trim(
    (string) $_GET['check_out_date']
);

if (!isValidDateFormat($checkOutDate)) {

    sendError(
        422,
        'check_out_date must be a valid date in YYYY-MM-DD format.'
    );
}


/*
|--------------------------------------------------------------------------
| Convert Dates
|--------------------------------------------------------------------------
*/

$checkIn = new DateTime(
    $checkInDate,
    new DateTimeZone('Asia/Kolkata')
);

$checkOut = new DateTime(
    $checkOutDate,
    new DateTimeZone('Asia/Kolkata')
);


/*
|--------------------------------------------------------------------------
| Check-In Cannot Be In The Past
|--------------------------------------------------------------------------
*/

if ($checkInDate < $currentDateString) {

    sendError(
        422,
        'check_in_date cannot be in the past.'
    );
}


/*
|--------------------------------------------------------------------------
| Check-Out Must Be After Check-In
|--------------------------------------------------------------------------
*/

if ($checkOut <= $checkIn) {

    sendError(
        422,
        'check_out_date must be after check_in_date.'
    );
}


/*
|--------------------------------------------------------------------------
| Calculate Number Of Nights
|--------------------------------------------------------------------------
*/

$interval = $checkIn->diff($checkOut);

$numberOfNights = (int) $interval->days;


/*
|--------------------------------------------------------------------------
| Validate Number Of Nights
|--------------------------------------------------------------------------
*/

if ($numberOfNights < 1) {

    sendError(
        422,
        'Booking must be for at least 1 night.'
    );
}

if ($numberOfNights > 365) {

    sendError(
        422,
        'Booking cannot exceed 365 nights.'
    );
}


/*
|--------------------------------------------------------------------------
| Validate Guests
|--------------------------------------------------------------------------
*/

$guests = 1;

if (isset($_GET['guests'])) {

    if (!isPositiveInteger($_GET['guests'])) {

        sendError(
            422,
            'guests must be a valid positive integer.'
        );
    }

    $guests = (int) $_GET['guests'];
}


/*
|--------------------------------------------------------------------------
| Guest Limit
|--------------------------------------------------------------------------
*/

if ($guests < 1) {

    sendError(
        422,
        'guests must be at least 1.'
    );
}

if ($guests > 50) {

    sendError(
        422,
        'guests cannot exceed 50.'
    );
}


/*
|--------------------------------------------------------------------------
| Validate room_id
|--------------------------------------------------------------------------
*/

$roomId = null;

if (isset($_GET['room_id'])) {

    if (!isPositiveInteger($_GET['room_id'])) {

        sendError(
            422,
            'room_id must be a valid positive integer.'
        );
    }

    $roomId = (int) $_GET['room_id'];
}


/*
|--------------------------------------------------------------------------
| Validate room_type_id
|--------------------------------------------------------------------------
*/

$roomTypeId = null;

if (isset($_GET['room_type_id'])) {

    if (!isPositiveInteger($_GET['room_type_id'])) {

        sendError(
            422,
            'room_type_id must be a valid positive integer.'
        );
    }

    $roomTypeId = (int) $_GET['room_type_id'];
}


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

$search = null;

if (isset($_GET['search'])) {

    $search = trim(
        (string) $_GET['search']
    );

    if (strlen($search) > 100) {

        sendError(
            422,
            'search cannot exceed 100 characters.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Validate Minimum Price
|--------------------------------------------------------------------------
*/

$minPrice = null;

if (isset($_GET['min_price'])) {

    $minPriceInput = $_GET['min_price'];

    if (
        is_array($minPriceInput) ||
        is_object($minPriceInput) ||
        !is_numeric($minPriceInput)
    ) {

        sendError(
            422,
            'min_price must be a valid number.'
        );
    }

    $minPriceString = trim(
        (string) $minPriceInput
    );

    if (
        !preg_match(
            '/^\d+(?:\.\d{1,2})?$/',
            $minPriceString
        )
    ) {

        sendError(
            422,
            'min_price must be a valid positive number with maximum 2 decimal places.'
        );
    }

    $minPrice = (float) $minPriceInput;

    if ($minPrice < 0) {

        sendError(
            422,
            'min_price cannot be negative.'
        );
    }

    if ($minPrice > 10000000) {

        sendError(
            422,
            'min_price cannot exceed 10000000.'
        );
    }

    $minPrice = round(
        $minPrice,
        2
    );
}


/*
|--------------------------------------------------------------------------
| Validate Maximum Price
|--------------------------------------------------------------------------
*/

$maxPrice = null;

if (isset($_GET['max_price'])) {

    $maxPriceInput = $_GET['max_price'];

    if (
        is_array($maxPriceInput) ||
        is_object($maxPriceInput) ||
        !is_numeric($maxPriceInput)
    ) {

        sendError(
            422,
            'max_price must be a valid number.'
        );
    }

    $maxPriceString = trim(
        (string) $maxPriceInput
    );

    if (
        !preg_match(
            '/^\d+(?:\.\d{1,2})?$/',
            $maxPriceString
        )
    ) {

        sendError(
            422,
            'max_price must be a valid positive number with maximum 2 decimal places.'
        );
    }

    $maxPrice = (float) $maxPriceInput;

    if ($maxPrice < 0) {

        sendError(
            422,
            'max_price cannot be negative.'
        );
    }

    if ($maxPrice > 10000000) {

        sendError(
            422,
            'max_price cannot exceed 10000000.'
        );
    }

    $maxPrice = round(
        $maxPrice,
        2
    );
}


/*
|--------------------------------------------------------------------------
| Validate Price Range
|--------------------------------------------------------------------------
*/

if (
    $minPrice !== null &&
    $maxPrice !== null &&
    $minPrice > $maxPrice
) {

    sendError(
        422,
        'min_price cannot be greater than max_price.'
    );
}


/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

$page = 1;

if (isset($_GET['page'])) {

    if (!ctype_digit((string) $_GET['page'])) {

        sendError(
            422,
            'page must be a valid positive integer.'
        );
    }

    $page = (int) $_GET['page'];

    if ($page < 1) {

        sendError(
            422,
            'page must be at least 1.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Limit
|--------------------------------------------------------------------------
*/

$limit = 20;

if (isset($_GET['limit'])) {

    if (!ctype_digit((string) $_GET['limit'])) {

        sendError(
            422,
            'limit must be a valid positive integer.'
        );
    }

    $limit = (int) $_GET['limit'];

    if ($limit < 1) {

        sendError(
            422,
            'limit must be at least 1.'
        );
    }

    if ($limit > 100) {

        sendError(
            422,
            'limit cannot exceed 100.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Offset
|--------------------------------------------------------------------------
*/

$offset = (
    $page - 1
) * $limit;


/*
|--------------------------------------------------------------------------
| Sort By
|--------------------------------------------------------------------------
*/

$allowedSortFields = [
    'price' => 'r.price_per_night',
    'room_number' => 'r.room_number',
    'name' => 'r.name',
    'guest_capacity' => 'r.guest_capacity',
    'bed_count' => 'r.bed_count'
];

$sortBy = 'r.price_per_night';

if (isset($_GET['sort_by'])) {

    $sortByInput = trim(
        (string) $_GET['sort_by']
    );

    if (
        !array_key_exists(
            $sortByInput,
            $allowedSortFields
        )
    ) {

        sendError(
            422,
            'Invalid sort_by value.'
        );
    }

    $sortBy = $allowedSortFields[$sortByInput];
}


/*
|--------------------------------------------------------------------------
| Sort Order
|--------------------------------------------------------------------------
*/

$sortOrder = 'ASC';

if (isset($_GET['sort_order'])) {

    $sortOrderInput = strtoupper(
        trim(
            (string) $_GET['sort_order']
        )
    );

    if (
        !in_array(
            $sortOrderInput,
            ['ASC', 'DESC'],
            true
        )
    ) {

        sendError(
            422,
            'sort_order must be ASC or DESC.'
        );
    }

    $sortOrder = $sortOrderInput;
}


/*
|--------------------------------------------------------------------------
| Build Base Conditions
|--------------------------------------------------------------------------
|
| Only rooms which are:
|
| 1. available
| 2. room type active
| 3. guest capacity sufficient
|
*/

$whereConditions = [];

$bindTypes = '';

$bindValues = [];


/*
|--------------------------------------------------------------------------
| Room Status
|--------------------------------------------------------------------------
*/

$whereConditions[] =
    "r.status = 'available'";


/*
|--------------------------------------------------------------------------
| Room Type Status
|--------------------------------------------------------------------------
*/

$whereConditions[] =
    "rt.status = 'active'";


/*
|--------------------------------------------------------------------------
| Guest Capacity
|--------------------------------------------------------------------------
*/

$whereConditions[] =
    "r.guest_capacity >= ?";

$bindTypes .= 'i';

$bindValues[] = $guests;


/*
|--------------------------------------------------------------------------
| Specific Room
|--------------------------------------------------------------------------
*/

if ($roomId !== null) {

    $whereConditions[] =
        "r.id = ?";

    $bindTypes .= 'i';

    $bindValues[] = $roomId;
}


/*
|--------------------------------------------------------------------------
| Room Type Filter
|--------------------------------------------------------------------------
*/

if ($roomTypeId !== null) {

    $whereConditions[] =
        "r.room_type_id = ?";

    $bindTypes .= 'i';

    $bindValues[] = $roomTypeId;
}


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== null && $search !== '') {

    $whereConditions[] = "
        (
            r.room_number LIKE ?
            OR r.name LIKE ?
            OR r.description LIKE ?
            OR rt.name LIKE ?
            OR rt.slug LIKE ?
        )
    ";

    $searchValue =
        '%' . $search . '%';

    $bindTypes .= 'sssss';

    $bindValues[] = $searchValue;
    $bindValues[] = $searchValue;
    $bindValues[] = $searchValue;
    $bindValues[] = $searchValue;
    $bindValues[] = $searchValue;
}


/*
|--------------------------------------------------------------------------
| Minimum Price
|--------------------------------------------------------------------------
*/

if ($minPrice !== null) {

    $whereConditions[] =
        "r.price_per_night >= ?";

    $bindTypes .= 'd';

    $bindValues[] = $minPrice;
}


/*
|--------------------------------------------------------------------------
| Maximum Price
|--------------------------------------------------------------------------
*/

if ($maxPrice !== null) {

    $whereConditions[] =
        "r.price_per_night <= ?";

    $bindTypes .= 'd';

    $bindValues[] = $maxPrice;
}


/*
|--------------------------------------------------------------------------
| Build WHERE SQL
|--------------------------------------------------------------------------
*/

$whereSql =
    'WHERE ' .
    implode(
        ' AND ',
        $whereConditions
    );


/*
|--------------------------------------------------------------------------
| Availability Condition
|--------------------------------------------------------------------------
|
| Booking overlaps requested dates when:
|
| booking.check_in_date < requested_check_out
| AND
| booking.check_out_date > requested_check_in
|
| Cancelled and no_show bookings do not block availability.
|
*/

$availabilityCondition = "
    NOT EXISTS (
        SELECT 1

        FROM booking_rooms br

        INNER JOIN room_bookings rb
            ON rb.id = br.booking_id

        WHERE br.room_id = r.id

          AND rb.booking_status NOT IN (
              'cancelled',
              'no_show'
          )

          AND rb.check_in_date < ?

          AND rb.check_out_date > ?
    )
";


/*
|--------------------------------------------------------------------------
| Count Query
|--------------------------------------------------------------------------
*/

$countSql = "
    SELECT
        COUNT(*) AS total

    FROM rooms r

    INNER JOIN room_types rt
        ON rt.id = r.room_type_id

    $whereSql

    AND $availabilityCondition
";


$countStmt = $conn->prepare(
    $countSql
);

if (!$countStmt) {

    sendError(
        500,
        'Failed to prepare availability count query.'
    );
}


/*
|--------------------------------------------------------------------------
| Count Query Bind Values
|--------------------------------------------------------------------------
|
| Existing filters first.
| Availability dates at the end.
|
*/

$countBindTypes =
    $bindTypes . 'ss';

$countBindValues =
    $bindValues;

$countBindValues[] =
    $checkOutDate;

$countBindValues[] =
    $checkInDate;


/*
|--------------------------------------------------------------------------
| Bind Count Parameters
|--------------------------------------------------------------------------
*/

$countBindParams = [];

$countBindParams[] =
    $countBindTypes;

foreach (
    $countBindValues
    as $key => $value
) {

    $countBindParams[] =
        &$countBindValues[$key];
}

call_user_func_array(
    [
        $countStmt,
        'bind_param'
    ],
    $countBindParams
);


/*
|--------------------------------------------------------------------------
| Execute Count Query
|--------------------------------------------------------------------------
*/

if (!$countStmt->execute()) {

    $countStmt->close();

    sendError(
        500,
        'Failed to execute availability count query.'
    );
}


/*
|--------------------------------------------------------------------------
| Fetch Total
|--------------------------------------------------------------------------
*/

$countResult =
    $countStmt->get_result();

$countRow =
    $countResult->fetch_assoc();

$totalRooms =
    (int) $countRow['total'];

$countStmt->close();


/*
|--------------------------------------------------------------------------
| Calculate Pagination
|--------------------------------------------------------------------------
*/

$totalPages = $totalRooms > 0
    ? (int) ceil(
        $totalRooms / $limit
    )
    : 0;


/*
|--------------------------------------------------------------------------
| If Specific Room Requested
|--------------------------------------------------------------------------
|
| We still return a single room result.
|
*/

if ($roomId !== null) {

    $limit = 1;

    $offset = 0;
}


/*
|--------------------------------------------------------------------------
| Main Availability Query
|--------------------------------------------------------------------------
*/

$roomSql = "
    SELECT
        r.id,
        r.room_type_id,
        r.room_number,
        r.name,
        r.description,
        r.bed_count,
        r.guest_capacity,
        r.price_per_night,
        r.gst_rate,
        r.price_includes_gst,
        r.status,

        rt.name AS room_type_name,
        rt.slug AS room_type_slug,
        rt.short_description AS room_type_short_description

    FROM rooms r

    INNER JOIN room_types rt
        ON rt.id = r.room_type_id

    $whereSql

    AND $availabilityCondition

    ORDER BY
        $sortBy $sortOrder

    LIMIT ? OFFSET ?
";


$roomStmt = $conn->prepare(
    $roomSql
);

if (!$roomStmt) {

    sendError(
        500,
        'Failed to prepare room availability query.'
    );
}


/*
|--------------------------------------------------------------------------
| Main Query Bind Values
|--------------------------------------------------------------------------
*/

$roomBindTypes =
    $bindTypes . 'ssi';

$roomBindValues =
    $bindValues;

$roomBindValues[] =
    $checkOutDate;

$roomBindValues[] =
    $checkInDate;

$roomBindValues[] =
    $limit;

$roomBindValues[] =
    $offset;


/*
|--------------------------------------------------------------------------
| Correct Bind Type
|--------------------------------------------------------------------------
|
| We have:
|
| filter values
| + 2 date strings
| + limit integer
| + offset integer
|
*/

$roomBindTypes =
    $bindTypes . 'ssii';


/*
|--------------------------------------------------------------------------
| Bind Main Query Parameters
|--------------------------------------------------------------------------
*/

$roomBindParams = [];

$roomBindParams[] =
    $roomBindTypes;

foreach (
    $roomBindValues
    as $key => $value
) {

    $roomBindParams[] =
        &$roomBindValues[$key];
}

call_user_func_array(
    [
        $roomStmt,
        'bind_param'
    ],
    $roomBindParams
);


/*
|--------------------------------------------------------------------------
| Execute Main Query
|--------------------------------------------------------------------------
*/

if (!$roomStmt->execute()) {

    $roomStmt->close();

    sendError(
        500,
        'Failed to execute room availability query.'
    );
}


/*
|--------------------------------------------------------------------------
| Fetch Rooms
|--------------------------------------------------------------------------
*/

$roomResult =
    $roomStmt->get_result();

$rooms = [];


/*
|--------------------------------------------------------------------------
| Process Rooms
|--------------------------------------------------------------------------
*/

while (
    $room = $roomResult->fetch_assoc()
) {

    /*
    |--------------------------------------------------------------------------
    | GST Calculation
    |--------------------------------------------------------------------------
    |
    | Room price is GST inclusive.
    |
    */

    $pricePerNight =
        (float) $room['price_per_night'];

    $gstRate =
        (float) $room['gst_rate'];

    if (
        (int) $room['price_includes_gst'] === 1
    ) {

        $divisor =
            1 + (
                $gstRate / 100
            );

        $basePrice =
            round(
                $pricePerNight / $divisor,
                2
            );

        $gstAmount =
            round(
                $pricePerNight - $basePrice,
                2
            );

    } else {

        $basePrice =
            $pricePerNight;

        $gstAmount =
            round(
                $basePrice * (
                    $gstRate / 100
                ),
                2
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Total Stay Price
    |--------------------------------------------------------------------------
    */

    $stayAmount =
        round(
            $pricePerNight *
            $numberOfNights,
            2
        );


    /*
    |--------------------------------------------------------------------------
    | Fetch Room Images
    |--------------------------------------------------------------------------
    */

    $images = [];

    $imageStmt = $conn->prepare("
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
    ");

    if ($imageStmt) {

        $roomImageId =
            (int) $room['id'];

        $imageStmt->bind_param(
            'i',
            $roomImageId
        );

        if ($imageStmt->execute()) {

            $imageResult =
                $imageStmt->get_result();

            while (
                $image =
                $imageResult->fetch_assoc()
            ) {

                $images[] = [
                    'id' =>
                        (int) $image['id'],

                    'image_path' =>
                        $image['image_path'],

                    'alt_text' =>
                        $image['alt_text'],

                    'is_primary' =>
                        (bool) $image['is_primary'],

                    'sort_order' =>
                        (int) $image['sort_order'],

                    'created_at' =>
                        $image['created_at']
                ];
            }
        }

        $imageStmt->close();
    }


    /*
    |--------------------------------------------------------------------------
    | Fetch Amenities
    |--------------------------------------------------------------------------
    */

    $amenities = [];

    $amenityStmt = $conn->prepare("
        SELECT
            a.id,
            a.name,
            a.icon

        FROM room_amenities ra

        INNER JOIN amenities a
            ON a.id = ra.amenity_id

        WHERE ra.room_id = ?

          AND a.status = 'active'

        ORDER BY
            a.name ASC
    ");

    if ($amenityStmt) {

        $roomAmenityId =
            (int) $room['id'];

        $amenityStmt->bind_param(
            'i',
            $roomAmenityId
        );

        if ($amenityStmt->execute()) {

            $amenityResult =
                $amenityStmt->get_result();

            while (
                $amenity =
                $amenityResult->fetch_assoc()
            ) {

                $amenities[] = [
                    'id' =>
                        (int) $amenity['id'],

                    'name' =>
                        $amenity['name'],

                    'icon' =>
                        $amenity['icon']
                ];
            }
        }

        $amenityStmt->close();
    }


    /*
    |--------------------------------------------------------------------------
    | Room Response
    |--------------------------------------------------------------------------
    */

    $rooms[] = [

        'room_id' =>
            (int) $room['id'],

        'room_type' => [

            'id' =>
                (int) $room['room_type_id'],

            'name' =>
                $room['room_type_name'],

            'slug' =>
                $room['room_type_slug'],

            'short_description' =>
                $room['room_type_short_description']
        ],

        'room_number' =>
            $room['room_number'],

        'name' =>
            $room['name'],

        'description' =>
            $room['description'],

        'bed_count' =>
            (int) $room['bed_count'],

        'guest_capacity' =>
            (int) $room['guest_capacity'],

        'availability' => [

            'available' => true,

            'check_in_date' =>
                $checkInDate,

            'check_out_date' =>
                $checkOutDate,

            'number_of_nights' =>
                $numberOfNights,

            'requested_guests' =>
                $guests
        ],

        'pricing' => [

            'price_per_night' =>
                number_format(
                    $pricePerNight,
                    2,
                    '.',
                    ''
                ),

            'gst_rate' =>
                number_format(
                    $gstRate,
                    2,
                    '.',
                    ''
                ),

            'price_includes_gst' =>
                (bool) $room['price_includes_gst'],

            'base_price_per_night' =>
                number_format(
                    $basePrice,
                    2,
                    '.',
                    ''
                ),

            'gst_amount_per_night' =>
                number_format(
                    $gstAmount,
                    2,
                    '.',
                    ''
                ),

            'stay_total' =>
                number_format(
                    $stayAmount,
                    2,
                    '.',
                    ''
                )
        ],

        'status' =>
            $room['status'],

        'images' =>
            $images,

        'amenities' =>
            $amenities
    ];
}

$roomStmt->close();


/*
|--------------------------------------------------------------------------
| Specific Room Response
|--------------------------------------------------------------------------
*/

if ($roomId !== null) {

    /*
    |--------------------------------------------------------------------------
    | Room Not Found / Not Available
    |--------------------------------------------------------------------------
    */

    if (empty($rooms)) {

        /*
        |--------------------------------------------------------------------------
        | Check Whether Room Exists
        |--------------------------------------------------------------------------
        */

        $checkRoomStmt = $conn->prepare("
            SELECT
                r.id,
                r.room_number,
                r.name,
                r.status,
                rt.name AS room_type_name,
                rt.status AS room_type_status

            FROM rooms r

            INNER JOIN room_types rt
                ON rt.id = r.room_type_id

            WHERE r.id = ?

            LIMIT 1
        ");

        if (!$checkRoomStmt) {

            sendError(
                500,
                'Failed to verify requested room.'
            );
        }

        $checkRoomStmt->bind_param(
            'i',
            $roomId
        );

        if (!$checkRoomStmt->execute()) {

            $checkRoomStmt->close();

            sendError(
                500,
                'Failed to verify requested room.'
            );
        }

        $checkRoomResult =
            $checkRoomStmt->get_result();

        /*
        |--------------------------------------------------------------------------
        | Room Does Not Exist
        |--------------------------------------------------------------------------
        */

        if (
            $checkRoomResult->num_rows === 0
        ) {

            $checkRoomStmt->close();

            sendError(
                404,
                'Room not found.'
            );
        }

        $requestedRoom =
            $checkRoomResult->fetch_assoc();

        $checkRoomStmt->close();


        /*
        |--------------------------------------------------------------------------
        | Room Status Check
        |--------------------------------------------------------------------------
        */

        if (
            $requestedRoom['status']
            !== 'available'
        ) {

            sendError(
                409,
                'Room is not available for booking.',
                [
                    'room_id' =>
                        $roomId,

                    'room_number' =>
                        $requestedRoom['room_number'],

                    'room_status' =>
                        $requestedRoom['status']
                ]
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Room Type Status Check
        |--------------------------------------------------------------------------
        */

        if (
            $requestedRoom['room_type_status']
            !== 'active'
        ) {

            sendError(
                409,
                'Room type is inactive.',
                [
                    'room_id' =>
                        $roomId,

                    'room_type_name' =>
                        $requestedRoom['room_type_name'],

                    'room_type_status' =>
                        $requestedRoom['room_type_status']
                ]
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Capacity Check
        |--------------------------------------------------------------------------
        */

        $capacityStmt = $conn->prepare("
            SELECT
                guest_capacity

            FROM rooms

            WHERE id = ?

            LIMIT 1
        ");

        if ($capacityStmt) {

            $capacityStmt->bind_param(
                'i',
                $roomId
            );

            if ($capacityStmt->execute()) {

                $capacityResult =
                    $capacityStmt->get_result();

                $capacityRow =
                    $capacityResult->fetch_assoc();

                if (
                    $capacityRow &&
                    $guests >
                    (int) $capacityRow['guest_capacity']
                ) {

                    $capacityStmt->close();

                    sendError(
                        409,
                        'Room cannot accommodate the requested number of guests.',
                        [
                            'room_id' =>
                                $roomId,

                            'requested_guests' =>
                                $guests,

                            'guest_capacity' =>
                                (int) $capacityRow['guest_capacity']
                        ]
                    );
                }
            }

            $capacityStmt->close();
        }


        /*
        |--------------------------------------------------------------------------
        | Date Availability Check
        |--------------------------------------------------------------------------
        */

        sendError(
            409,
            'Room is already booked for the selected dates.',
            [
                'room_id' =>
                    $roomId,

                'check_in_date' =>
                    $checkInDate,

                'check_out_date' =>
                    $checkOutDate,

                'number_of_nights' =>
                    $numberOfNights,

                'available' =>
                    false
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Specific Room Available
    |--------------------------------------------------------------------------
    */

    $roomData =
        $rooms[0];

    $roomData['availability']['available'] =
        true;


    http_response_code(200);

    echo json_encode([
        'status' => true,

        'message' =>
            'Room is available for the selected dates.',

        'data' => $roomData
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


/*
|--------------------------------------------------------------------------
| All Available Rooms Response
|--------------------------------------------------------------------------
*/

http_response_code(200);

echo json_encode([

    'status' => true,

    'message' =>
        'Room availability fetched successfully.',

    'data' => [

        'search' => [

            'check_in_date' =>
                $checkInDate,

            'check_out_date' =>
                $checkOutDate,

            'number_of_nights' =>
                $numberOfNights,

            'guests' =>
                $guests
        ],

        'pagination' => [

            'current_page' =>
                $page,

            'per_page' =>
                $limit,

            'total_rooms' =>
                $totalRooms,

            'total_pages' =>
                $totalPages
        ],

        'rooms' =>
            $rooms
    ]

], JSON_UNESCAPED_UNICODE);

exit;