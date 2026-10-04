<?php



ini_set(
    'display_errors',
    '0'
);

ini_set(
    'log_errors',
    '1'
);

error_reporting(
    E_ALL
);


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
| AUTHENTICATION
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
| DATABASE VALIDATION
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
| CURRENT USER
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
| IMPORTANT JWT ID
|--------------------------------------------------------------------------
|
| JWT uses:
|
| sub = user ID
|
| id is kept as fallback.
|
|--------------------------------------------------------------------------
*/

$currentUserId =
    $currentUser['sub']
    ?? $currentUser['id']
    ?? null;


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
        'You are not authorized to access the dashboard.'
    );
}


/*
|--------------------------------------------------------------------------
| DATE VALIDATION HELPER
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
| DYNAMIC PARAMETER BINDING
|--------------------------------------------------------------------------
*/

function bindDynamicParams(
    mysqli_stmt $stmt,
    string $types,
    array &$params
): void {

    if (
        $types === ''
    ) {

        return;
    }

    $bindParams = [];

    $bindParams[] =
        $types;

    foreach (
        $params as $key => &$value
    ) {

        $bindParams[] =
            &$value;
    }

    call_user_func_array(
        [
            $stmt,
            'bind_param'
        ],
        $bindParams
    );
}


/*
|--------------------------------------------------------------------------
| SELECT HELPER
|--------------------------------------------------------------------------
*/

function fetchAllRows(
    mysqli $conn,
    string $sql,
    string $types = '',
    array $params = []
): array {

    $stmt =
        $conn->prepare(
            $sql
        );

    if (
        !$stmt
    ) {

        throw new Exception(
            'Failed to prepare query: ' .
            $conn->error
        );
    }

    if (
        $types !== ''
    ) {

        bindDynamicParams(
            $stmt,
            $types,
            $params
        );
    }

    if (
        !$stmt->execute()
    ) {

        $error =
            $stmt->error;

        $stmt->close();

        throw new Exception(
            'Failed to execute query: ' .
            $error
        );
    }

    $result =
        $stmt->get_result();

    $rows = [];

    while (
        $row =
            $result->fetch_assoc()
    ) {

        $rows[] =
            $row;
    }

    $stmt->close();

    return $rows;
}


/*
|--------------------------------------------------------------------------
| SINGLE ROW HELPER
|--------------------------------------------------------------------------
*/

function fetchOneRow(
    mysqli $conn,
    string $sql,
    string $types = '',
    array $params = []
): ?array {

    $rows =
        fetchAllRows(
            $conn,
            $sql,
            $types,
            $params
        );

    return
        $rows[0]
        ?? null;
}


/*
|--------------------------------------------------------------------------
| SINGLE VALUE HELPER
|--------------------------------------------------------------------------
*/

function fetchScalar(
    mysqli $conn,
    string $sql,
    string $types = '',
    array $params = []
) {

    $row =
        fetchOneRow(
            $conn,
            $sql,
            $types,
            $params
        );

    if (
        !$row
    ) {

        return null;
    }

    return
        array_values($row)[0]
        ?? null;
}


/*
|--------------------------------------------------------------------------
| DATE FILTERS
|--------------------------------------------------------------------------
*/

$fromDate = null;

$toDate = null;


if (
    isset($_GET['from_date'])
) {

    $fromDate =
        trim(
            (string)
            $_GET['from_date']
        );

    if (
        !isValidDate(
            $fromDate
        )
    ) {

        sendJsonResponse(
            422,
            false,
            'from_date must be a valid YYYY-MM-DD date.'
        );
    }
}


if (
    isset($_GET['to_date'])
) {

    $toDate =
        trim(
            (string)
            $_GET['to_date']
        );

    if (
        !isValidDate(
            $toDate
        )
    ) {

        sendJsonResponse(
            422,
            false,
            'to_date must be a valid YYYY-MM-DD date.'
        );
    }
}


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
| COMMON DATE CONDITION
|--------------------------------------------------------------------------
|
| This is used for KPI queries.
|
|--------------------------------------------------------------------------
*/

$dateWhere = [];

$dateParams = [];

$dateTypes = '';


if (
    $fromDate !== null
) {

    $dateWhere[] =
        'created_at >= ?';

    $dateParams[] =
        $fromDate .
        ' 00:00:00';

    $dateTypes .=
        's';
}


if (
    $toDate !== null
) {

    $dateWhere[] =
        'created_at <= ?';

    $dateParams[] =
        $toDate .
        ' 23:59:59';

    $dateTypes .=
        's';
}


/*
|--------------------------------------------------------------------------
| DASHBOARD DATA
|--------------------------------------------------------------------------
*/

try {


    /*
    |--------------------------------------------------------------------------
    | SYSTEM SETTINGS
    |--------------------------------------------------------------------------
    */

    $settingsRows =
        fetchAllRows(
            $conn,

            "
            SELECT
                setting_key,
                setting_value,
                setting_type,
                description,
                updated_at

            FROM settings

            ORDER BY id ASC
            "
        );


    $settings = [];


    foreach (
        $settingsRows as $setting
    ) {

        $value =
            $setting['setting_value'];

        switch (
            $setting['setting_type']
        ) {

            case 'number':

                $value =
                    is_numeric($value)
                        ? (
                            strpos(
                                (string)$value,
                                '.'
                            ) !== false
                                ? (float)$value
                                : (int)$value
                        )
                        : null;

                break;


            case 'boolean':

                $value =
                    in_array(
                        strtolower(
                            (string)$value
                        ),
                        [
                            '1',
                            'true',
                            'yes',
                            'on'
                        ],
                        true
                    );

                break;


            case 'json':

                $decoded =
                    json_decode(
                        $value,
                        true
                    );

                $value =
                    json_last_error() === JSON_ERROR_NONE
                        ? $decoded
                        : $value;

                break;


            default:

                $value =
                    $value;

                break;
        }


        $settings[
            $setting['setting_key']
        ] = [

            'value' =>
                $value,

            'type' =>
                $setting['setting_type'],

            'description' =>
                $setting['description'],

            'updated_at' =>
                $setting['updated_at']
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | ADMIN / STAFF STATISTICS
    |--------------------------------------------------------------------------
    */

    $adminStats =
        fetchOneRow(
            $conn,

            "
            SELECT

                COUNT(*) AS total,

                SUM(
                    CASE
                        WHEN status = 'active'
                        THEN 1
                        ELSE 0
                    END
                ) AS active,

                SUM(
                    CASE
                        WHEN status = 'inactive'
                        THEN 1
                        ELSE 0
                    END
                ) AS inactive,

                SUM(
                    CASE
                        WHEN role = 'super_admin'
                        THEN 1
                        ELSE 0
                    END
                ) AS super_admins,

                SUM(
                    CASE
                        WHEN role = 'admin'
                        THEN 1
                        ELSE 0
                    END
                ) AS admins,

                SUM(
                    CASE
                        WHEN role = 'staff'
                        THEN 1
                        ELSE 0
                    END
                ) AS staff

            FROM admin_users
            "
        );


    /*
    |--------------------------------------------------------------------------
    | CUSTOMER STATISTICS
    |--------------------------------------------------------------------------
    */

    $customerStats =
        fetchOneRow(
            $conn,

            "
            SELECT

                COUNT(*) AS total,

                SUM(
                    CASE
                        WHEN status = 'active'
                        THEN 1
                        ELSE 0
                    END
                ) AS active,

                SUM(
                    CASE
                        WHEN status = 'inactive'
                        THEN 1
                        ELSE 0
                    END
                ) AS inactive,

                SUM(
                    CASE
                        WHEN status = 'blocked'
                        THEN 1
                        ELSE 0
                    END
                ) AS blocked,

                SUM(
                    CASE
                        WHEN email_verified_at IS NOT NULL
                        THEN 1
                        ELSE 0
                    END
                ) AS email_verified,

                SUM(
                    CASE
                        WHEN phone_verified_at IS NOT NULL
                        THEN 1
                        ELSE 0
                    END
                ) AS phone_verified

            FROM customers
            "
        );


    /*
    |--------------------------------------------------------------------------
    | CUSTOMER SESSIONS
    |--------------------------------------------------------------------------
    */

    $sessionStats =
        fetchOneRow(
            $conn,

            "
            SELECT

                COUNT(*) AS total,

                SUM(
                    CASE
                        WHEN revoked_at IS NULL
                        AND expires_at > NOW()
                        THEN 1
                        ELSE 0
                    END
                ) AS active,

                SUM(
                    CASE
                        WHEN revoked_at IS NOT NULL
                        THEN 1
                        ELSE 0
                    END
                ) AS revoked,

                SUM(
                    CASE
                        WHEN revoked_at IS NULL
                        AND expires_at <= NOW()
                        THEN 1
                        ELSE 0
                    END
                ) AS expired

            FROM customer_sessions
            "
        );


    /*
    |--------------------------------------------------------------------------
    | ROOM TYPE STATISTICS
    |--------------------------------------------------------------------------
    */

    $roomTypeStats =
        fetchOneRow(
            $conn,

            "
            SELECT

                COUNT(*) AS total,

                SUM(
                    CASE
                        WHEN status = 'active'
                        THEN 1
                        ELSE 0
                    END
                ) AS active,

                SUM(
                    CASE
                        WHEN status = 'inactive'
                        THEN 1
                        ELSE 0
                    END
                ) AS inactive

            FROM room_types
            "
        );


    /*
    |--------------------------------------------------------------------------
    | ROOM STATISTICS
    |--------------------------------------------------------------------------
    */

    $roomStats =
        fetchOneRow(
            $conn,

            "
            SELECT

                COUNT(*) AS total,

                SUM(
                    CASE
                        WHEN status = 'available'
                        THEN 1
                        ELSE 0
                    END
                ) AS available,

                SUM(
                    CASE
                        WHEN status = 'maintenance'
                        THEN 1
                        ELSE 0
                    END
                ) AS maintenance,

                SUM(
                    CASE
                        WHEN status = 'inactive'
                        THEN 1
                        ELSE 0
                    END
                ) AS inactive,

                COALESCE(
                    SUM(
                        CASE
                            WHEN status = 'available'
                            THEN price_per_night
                            ELSE 0
                        END
                    ),
                    0
                ) AS available_room_tariff,

                COALESCE(
                    AVG(
                        CASE
                            WHEN status = 'available'
                            THEN price_per_night
                            ELSE NULL
                        END
                    ),
                    0
                ) AS average_available_room_tariff

            FROM rooms
            "
        );


    /*
    |--------------------------------------------------------------------------
    | ROOM IMAGES / AMENITIES
    |--------------------------------------------------------------------------
    */

    $roomContentStats =
        fetchOneRow(
            $conn,

            "
            SELECT

                (
                    SELECT COUNT(*)
                    FROM room_images
                ) AS total_room_images,

                (
                    SELECT COUNT(*)
                    FROM room_amenities
                ) AS total_room_amenity_mappings,

                (
                    SELECT COUNT(*)
                    FROM amenities
                ) AS total_amenities,

                (
                    SELECT COUNT(*)
                    FROM amenities
                    WHERE status = 'active'
                ) AS active_amenities

            "
        );


    /*
    |--------------------------------------------------------------------------
    | FOOD CATEGORY STATISTICS
    |--------------------------------------------------------------------------
    */

    $foodCategoryStats =
        fetchOneRow(
            $conn,

            "
            SELECT

                COUNT(*) AS total,

                SUM(
                    CASE
                        WHEN status = 'active'
                        THEN 1
                        ELSE 0
                    END
                ) AS active,

                SUM(
                    CASE
                        WHEN status = 'inactive'
                        THEN 1
                        ELSE 0
                    END
                ) AS inactive

            FROM food_categories
            "
        );


    /*
    |--------------------------------------------------------------------------
    | FOOD ITEM STATISTICS
    |--------------------------------------------------------------------------
    */

    $foodStats =
        fetchOneRow(
            $conn,

            "
            SELECT

                COUNT(*) AS total,

                SUM(
                    CASE
                        WHEN status = 'active'
                        THEN 1
                        ELSE 0
                    END
                ) AS active,

                SUM(
                    CASE
                        WHEN status = 'inactive'
                        THEN 1
                        ELSE 0
                    END
                ) AS inactive,

                SUM(
                    CASE
                        WHEN food_type = 'veg'
                        AND status = 'active'
                        THEN 1
                        ELSE 0
                    END
                ) AS veg,

                SUM(
                    CASE
                        WHEN food_type = 'non_veg'
                        AND status = 'active'
                        THEN 1
                        ELSE 0
                    END
                ) AS non_veg,

                SUM(
                    CASE
                        WHEN food_type = 'egg'
                        AND status = 'active'
                        THEN 1
                        ELSE 0
                    END
                ) AS egg,

                SUM(
                    CASE
                        WHEN food_type = 'vegan'
                        AND status = 'active'
                        THEN 1
                        ELSE 0
                    END
                ) AS vegan,

                SUM(
                    CASE
                        WHEN food_type = 'other'
                        AND status = 'active'
                        THEN 1
                        ELSE 0
                    END
                ) AS other

            FROM food_items
            "
        );


    /*
    |--------------------------------------------------------------------------
    | ROOM FOOD PACKAGE STATISTICS
    |--------------------------------------------------------------------------
    */

    $roomFoodPackageStats =
        fetchOneRow(
            $conn,

            "
            SELECT

                COUNT(*) AS total,

                SUM(
                    CASE
                        WHEN status = 'active'
                        THEN 1
                        ELSE 0
                    END
                ) AS active,

                SUM(
                    CASE
                        WHEN status = 'inactive'
                        THEN 1
                        ELSE 0
                    END
                ) AS inactive,

                (
                    SELECT COUNT(*)
                    FROM room_food_package_items
                ) AS total_items

            FROM room_food_packages
            "
        );


    /*
    |--------------------------------------------------------------------------
    | HALL TYPE STATISTICS
    |--------------------------------------------------------------------------
    */

    $hallTypeStats =
        fetchOneRow(
            $conn,

            "
            SELECT

                COUNT(*) AS total,

                SUM(
                    CASE
                        WHEN status = 'active'
                        THEN 1
                        ELSE 0
                    END
                ) AS active,

                SUM(
                    CASE
                        WHEN status = 'inactive'
                        THEN 1
                        ELSE 0
                    END
                ) AS inactive

            FROM hall_types
            "
        );


    /*
    |--------------------------------------------------------------------------
    | HALL STATISTICS
    |--------------------------------------------------------------------------
    */

    $hallStats =
        fetchOneRow(
            $conn,

            "
            SELECT

                COUNT(*) AS total,

                SUM(
                    CASE
                        WHEN status = 'available'
                        THEN 1
                        ELSE 0
                    END
                ) AS available,

                SUM(
                    CASE
                        WHEN status = 'maintenance'
                        THEN 1
                        ELSE 0
                    END
                ) AS maintenance,

                SUM(
                    CASE
                        WHEN status = 'inactive'
                        THEN 1
                        ELSE 0
                    END
                ) AS inactive,

                COALESCE(
                    SUM(capacity),
                    0
                ) AS total_capacity,

                COALESCE(
                    AVG(
                        CASE
                            WHEN status = 'available'
                            AND capacity > 0
                            THEN capacity
                            ELSE NULL
                        END
                    ),
                    0
                ) AS average_available_capacity

            FROM halls
            "
        );


    /*
    |--------------------------------------------------------------------------
    | HALL IMAGES / FACILITIES
    |--------------------------------------------------------------------------
    */

    $hallContentStats =
        fetchOneRow(
            $conn,

            "
            SELECT

                (
                    SELECT COUNT(*)
                    FROM hall_images
                ) AS total_hall_images,

                (
                    SELECT COUNT(*)
                    FROM hall_facilities
                ) AS total_hall_facilities,

                (
                    SELECT COUNT(*)
                    FROM hall_facilities
                    WHERE status = 'active'
                ) AS active_hall_facilities,

                (
                    SELECT COUNT(*)
                    FROM hall_facility_map
                ) AS total_hall_facility_mappings

            "
        );


    /*
    |--------------------------------------------------------------------------
    | DINING PACKAGE STATISTICS
    |--------------------------------------------------------------------------
    */

    $diningPackageStats =
        fetchOneRow(
            $conn,

            "
            SELECT

                COUNT(*) AS total,

                SUM(
                    CASE
                        WHEN status = 'active'
                        THEN 1
                        ELSE 0
                    END
                ) AS active,

                SUM(
                    CASE
                        WHEN status = 'inactive'
                        THEN 1
                        ELSE 0
                    END
                ) AS inactive,

                (
                    SELECT COUNT(*)
                    FROM dining_package_items
                ) AS total_items

            FROM dining_packages
            "
        );


    /*
    |--------------------------------------------------------------------------
    | CATERING PACKAGE STATISTICS
    |--------------------------------------------------------------------------
    */

    $cateringPackageStats =
        fetchOneRow(
            $conn,

            "
            SELECT

                COUNT(*) AS total,

                SUM(
                    CASE
                        WHEN status = 'active'
                        THEN 1
                        ELSE 0
                    END
                ) AS active,

                SUM(
                    CASE
                        WHEN status = 'inactive'
                        THEN 1
                        ELSE 0
                    END
                ) AS inactive,

                (
                    SELECT COUNT(*)
                    FROM catering_package_items
                ) AS total_items

            FROM catering_packages
            "
        );


    /*
    |--------------------------------------------------------------------------
    | ROOM BOOKING KPI
    |--------------------------------------------------------------------------
    */

    $bookingWhere = [];

    $bookingParams = [];

    $bookingTypes = '';


    if (
        $fromDate !== null
    ) {

        $bookingWhere[] =
            'rb.created_at >= ?';

        $bookingParams[] =
            $fromDate .
            ' 00:00:00';

        $bookingTypes .=
            's';
    }


    if (
        $toDate !== null
    ) {

        $bookingWhere[] =
            'rb.created_at <= ?';

        $bookingParams[] =
            $toDate .
            ' 23:59:59';

        $bookingTypes .=
            's';
    }


    $bookingWhereSql =
        count($bookingWhere) > 0
            ? 'WHERE ' .
                implode(
                    ' AND ',
                    $bookingWhere
                )
            : '';


    $bookingStats =
        fetchOneRow(
            $conn,

            "
            SELECT

                COUNT(*) AS total_bookings,

                SUM(
                    CASE
                        WHEN rb.booking_status = 'pending'
                        THEN 1
                        ELSE 0
                    END
                ) AS pending_bookings,

                SUM(
                    CASE
                        WHEN rb.booking_status = 'confirmed'
                        THEN 1
                        ELSE 0
                    END
                ) AS confirmed_bookings,

                SUM(
                    CASE
                        WHEN rb.booking_status = 'checked_in'
                        THEN 1
                        ELSE 0
                    END
                ) AS checked_in_bookings,

                SUM(
                    CASE
                        WHEN rb.booking_status = 'checked_out'
                        THEN 1
                        ELSE 0
                    END
                ) AS checked_out_bookings,

                SUM(
                    CASE
                        WHEN rb.booking_status = 'cancelled'
                        THEN 1
                        ELSE 0
                    END
                ) AS cancelled_bookings,

                SUM(
                    CASE
                        WHEN rb.booking_status = 'no_show'
                        THEN 1
                        ELSE 0
                    END
                ) AS no_show_bookings,

                SUM(
                    CASE
                        WHEN rb.payment_status = 'pending'
                        THEN 1
                        ELSE 0
                    END
                ) AS payment_pending_bookings,

                SUM(
                    CASE
                        WHEN rb.payment_status = 'partial'
                        THEN 1
                        ELSE 0
                    END
                ) AS payment_partial_bookings,

                SUM(
                    CASE
                        WHEN rb.payment_status = 'paid'
                        THEN 1
                        ELSE 0
                    END
                ) AS payment_paid_bookings,

                SUM(
                    CASE
                        WHEN rb.payment_status = 'failed'
                        THEN 1
                        ELSE 0
                    END
                ) AS payment_failed_bookings,

                SUM(
                    CASE
                        WHEN rb.payment_status = 'refunded'
                        THEN 1
                        ELSE 0
                    END
                ) AS payment_refunded_bookings,

                COALESCE(
                    SUM(
                        CASE
                            WHEN rb.booking_status
                                NOT IN (
                                    'cancelled',
                                    'no_show'
                                )
                            THEN rb.total_amount
                            ELSE 0
                        END
                    ),
                    0
                ) AS active_booking_value,

                COALESCE(
                    SUM(
                        CASE
                            WHEN rb.payment_status = 'paid'
                            THEN rb.total_amount
                            ELSE 0
                        END
                    ),
                    0
                ) AS booking_paid_value,

                COALESCE(
                    SUM(
                        CASE
                            WHEN rb.payment_status IN (
                                'pending',
                                'partial'
                            )
                            THEN rb.total_amount
                            ELSE 0
                        END
                    ),
                    0
                ) AS booking_pending_value,

                COALESCE(
                    SUM(
                        CASE
                            WHEN rb.booking_status
                                NOT IN (
                                    'cancelled',
                                    'no_show'
                                )
                            THEN rb.gst_amount
                            ELSE 0
                        END
                    ),
                    0
                ) AS booking_gst_value,

                COALESCE(
                    SUM(rb.total_guests),
                    0
                ) AS total_guests,

                COALESCE(
                    SUM(
                        DATEDIFF(
                            rb.check_out_date,
                            rb.check_in_date
                        )
                    ),
                    0
                ) AS total_nights

            FROM room_bookings rb

            {$bookingWhereSql}
            ",

            $bookingTypes,

            $bookingParams
        );


    /*
    |--------------------------------------------------------------------------
    | PAYMENT STATISTICS
    |--------------------------------------------------------------------------
    */

    $paymentWhere = [];

    $paymentParams = [];

    $paymentTypes = '';


    if (
        $fromDate !== null
    ) {

        $paymentWhere[] =
            'p.created_at >= ?';

        $paymentParams[] =
            $fromDate .
            ' 00:00:00';

        $paymentTypes .=
            's';
    }


    if (
        $toDate !== null
    ) {

        $paymentWhere[] =
            'p.created_at <= ?';

        $paymentParams[] =
            $toDate .
            ' 23:59:59';

        $paymentTypes .=
            's';
    }


    $paymentWhereSql =
        count($paymentWhere) > 0
            ? 'WHERE ' .
                implode(
                    ' AND ',
                    $paymentWhere
                )
            : '';


    $paymentStats =
        fetchOneRow(
            $conn,

            "
            SELECT

                COUNT(*) AS total_payments,

                SUM(
                    CASE
                        WHEN p.payment_status = 'created'
                        THEN 1
                        ELSE 0
                    END
                ) AS created_payments,

                SUM(
                    CASE
                        WHEN p.payment_status = 'authorized'
                        THEN 1
                        ELSE 0
                    END
                ) AS authorized_payments,

                SUM(
                    CASE
                        WHEN p.payment_status = 'captured'
                        THEN 1
                        ELSE 0
                    END
                ) AS captured_payments,

                SUM(
                    CASE
                        WHEN p.payment_status = 'failed'
                        THEN 1
                        ELSE 0
                    END
                ) AS failed_payments,

                SUM(
                    CASE
                        WHEN p.payment_status = 'refunded'
                        THEN 1
                        ELSE 0
                    END
                ) AS refunded_payments,

                COALESCE(
                    SUM(
                        CASE
                            WHEN p.payment_status = 'captured'
                            THEN p.amount
                            ELSE 0
                        END
                    ),
                    0
                ) AS captured_amount,

                COALESCE(
                    SUM(
                        CASE
                            WHEN p.payment_status = 'created'
                            THEN p.amount
                            ELSE 0
                        END
                    ),
                    0
                ) AS created_amount,

                COALESCE(
                    SUM(
                        CASE
                            WHEN p.payment_status = 'failed'
                            THEN p.amount
                            ELSE 0
                        END
                    ),
                    0
                ) AS failed_amount,

                COALESCE(
                    SUM(
                        CASE
                            WHEN p.payment_status = 'refunded'
                            THEN p.amount
                            ELSE 0
                        END
                    ),
                    0
                ) AS refunded_amount

            FROM payments p

            {$paymentWhereSql}
            ",

            $paymentTypes,

            $paymentParams
        );


    /*
    |--------------------------------------------------------------------------
    | LEAD WHERE
    |--------------------------------------------------------------------------
    */

    $leadWhere = [];

    $leadParams = [];

    $leadTypes = '';


    if (
        $fromDate !== null
    ) {

        $leadWhere[] =
            'l.created_at >= ?';

        $leadParams[] =
            $fromDate .
            ' 00:00:00';

        $leadTypes .=
            's';
    }


    if (
        $toDate !== null
    ) {

        $leadWhere[] =
            'l.created_at <= ?';

        $leadParams[] =
            $toDate .
            ' 23:59:59';

        $leadTypes .=
            's';
    }


    /*
    |--------------------------------------------------------------------------
    | STAFF LEAD SCOPE
    |--------------------------------------------------------------------------
    */

    if (
        $currentUserRole === 'staff'
    ) {

        $leadWhere[] =
            'l.assigned_admin_id = ?';

        $leadParams[] =
            $currentUserId;

        $leadTypes .=
            'i';
    }


    $leadWhereSql =
        count($leadWhere) > 0
            ? 'WHERE ' .
                implode(
                    ' AND ',
                    $leadWhere
                )
            : '';


    /*
    |--------------------------------------------------------------------------
    | LEAD KPI
    |--------------------------------------------------------------------------
    */

    $leadStats =
        fetchOneRow(
            $conn,

            "
            SELECT

                COUNT(*) AS total_leads,

                SUM(
                    CASE
                        WHEN l.status = 'new'
                        THEN 1
                        ELSE 0
                    END
                ) AS new_leads,

                SUM(
                    CASE
                        WHEN l.status = 'contacted'
                        THEN 1
                        ELSE 0
                    END
                ) AS contacted_leads,

                SUM(
                    CASE
                        WHEN l.status = 'follow_up'
                        THEN 1
                        ELSE 0
                    END
                ) AS follow_up_leads,

                SUM(
                    CASE
                        WHEN l.status = 'converted'
                        THEN 1
                        ELSE 0
                    END
                ) AS converted_leads,

                SUM(
                    CASE
                        WHEN l.status = 'lost'
                        THEN 1
                        ELSE 0
                    END
                ) AS lost_leads,

                SUM(
                    CASE
                        WHEN l.status = 'cancelled'
                        THEN 1
                        ELSE 0
                    END
                ) AS cancelled_leads,

                COALESCE(
                    SUM(
                        CASE
                            WHEN l.estimated_amount IS NOT NULL
                            THEN l.estimated_amount
                            ELSE 0
                        END
                    ),
                    0
                ) AS estimated_pipeline,

                COALESCE(
                    SUM(
                        CASE
                            WHEN l.status = 'converted'
                            THEN l.converted_amount
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
                ) AS open_pipeline,

                COALESCE(
                    AVG(
                        CASE
                            WHEN l.status = 'converted'
                            THEN l.converted_amount
                            ELSE NULL
                        END
                    ),
                    0
                ) AS average_converted_value

            FROM leads l

            {$leadWhereSql}
            ",

            $leadTypes,

            $leadParams
        );


    /*
    |--------------------------------------------------------------------------
    | LEAD CONVERSION RATE
    |--------------------------------------------------------------------------
    */

    $totalLeads =
        (int)(
            $leadStats['total_leads']
            ?? 0
        );

    $convertedLeads =
        (int)(
            $leadStats['converted_leads']
            ?? 0
        );


    $leadConversionRate =
        $totalLeads > 0
            ? round(
                (
                    $convertedLeads /
                    $totalLeads
                ) *
                100,
                2
            )
            : 0;


    /*
    |--------------------------------------------------------------------------
    | LEAD DOMAIN BREAKDOWN
    |--------------------------------------------------------------------------
    */

    $leadDomainRows =
        fetchAllRows(
            $conn,

            "
            SELECT

                l.domain,

                COUNT(*) AS total_leads,

                SUM(
                    CASE
                        WHEN l.status = 'new'
                        THEN 1
                        ELSE 0
                    END
                ) AS new_leads,

                SUM(
                    CASE
                        WHEN l.status = 'contacted'
                        THEN 1
                        ELSE 0
                    END
                ) AS contacted_leads,

                SUM(
                    CASE
                        WHEN l.status = 'follow_up'
                        THEN 1
                        ELSE 0
                    END
                ) AS follow_up_leads,

                SUM(
                    CASE
                        WHEN l.status = 'converted'
                        THEN 1
                        ELSE 0
                    END
                ) AS converted_leads,

                SUM(
                    CASE
                        WHEN l.status = 'lost'
                        THEN 1
                        ELSE 0
                    END
                ) AS lost_leads,

                SUM(
                    CASE
                        WHEN l.status = 'cancelled'
                        THEN 1
                        ELSE 0
                    END
                ) AS cancelled_leads,

                COALESCE(
                    SUM(
                        CASE
                            WHEN l.status = 'converted'
                            THEN l.converted_amount
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
                ) AS open_pipeline

            FROM leads l

            {$leadWhereSql}

            GROUP BY l.domain

            ORDER BY total_leads DESC
            ",

            $leadTypes,

            $leadParams
        );


    /*
    |--------------------------------------------------------------------------
    | LEAD STATUS BREAKDOWN
    |--------------------------------------------------------------------------
    */

    $leadStatusRows =
        fetchAllRows(
            $conn,

            "
            SELECT

                l.status,

                COUNT(*) AS total

            FROM leads l

            {$leadWhereSql}

            GROUP BY l.status

            ORDER BY total DESC
            ",

            $leadTypes,

            $leadParams
        );


    /*
    |--------------------------------------------------------------------------
    | ROOM STATUS BREAKDOWN
    |--------------------------------------------------------------------------
    */

    $roomStatusRows =
        fetchAllRows(
            $conn,

            "
            SELECT

                status,

                COUNT(*) AS total,

                COALESCE(
                    SUM(price_per_night),
                    0
                ) AS tariff_value

            FROM rooms

            GROUP BY status

            ORDER BY total DESC
            "
        );


    /*
    |--------------------------------------------------------------------------
    | ROOM TYPE BREAKDOWN
    |--------------------------------------------------------------------------
    */

    $roomTypeRows =
        fetchAllRows(
            $conn,

            "
            SELECT

                rt.id,
                rt.name,
                rt.slug,
                rt.status,

                COUNT(r.id) AS room_count,

                COALESCE(
                    SUM(
                        CASE
                            WHEN r.status = 'available'
                            THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS available_rooms,

                COALESCE(
                    SUM(
                        CASE
                            WHEN r.status = 'maintenance'
                            THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS maintenance_rooms,

                COALESCE(
                    SUM(
                        CASE
                            WHEN r.status = 'inactive'
                            THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS inactive_rooms,

                COALESCE(
                    AVG(r.price_per_night),
                    0
                ) AS average_price_per_night

            FROM room_types rt

            LEFT JOIN rooms r
                ON r.room_type_id = rt.id

            GROUP BY
                rt.id,
                rt.name,
                rt.slug,
                rt.status

            ORDER BY
                room_count DESC,
                rt.id ASC
            "
        );


    /*
    |--------------------------------------------------------------------------
    | HALL STATUS BREAKDOWN
    |--------------------------------------------------------------------------
    */

    $hallStatusRows =
        fetchAllRows(
            $conn,

            "
            SELECT

                status,

                COUNT(*) AS total,

                COALESCE(
                    SUM(capacity),
                    0
                ) AS total_capacity

            FROM halls

            GROUP BY status

            ORDER BY total DESC
            "
        );


    /*
    |--------------------------------------------------------------------------
    | HALL TYPE BREAKDOWN
    |--------------------------------------------------------------------------
    */

    $hallTypeRows =
        fetchAllRows(
            $conn,

            "
            SELECT

                ht.id,
                ht.name,
                ht.slug,
                ht.status,

                COUNT(h.id) AS hall_count,

                COALESCE(
                    SUM(h.capacity),
                    0
                ) AS total_capacity

            FROM hall_types ht

            LEFT JOIN halls h
                ON h.hall_type_id = ht.id

            GROUP BY
                ht.id,
                ht.name,
                ht.slug,
                ht.status

            ORDER BY
                hall_count DESC,
                ht.id ASC
            "
        );


    /*
    |--------------------------------------------------------------------------
    | BOOKING STATUS CHART
    |--------------------------------------------------------------------------
    */

    $bookingStatusRows =
        fetchAllRows(
            $conn,

            "
            SELECT

                rb.booking_status AS status,

                COUNT(*) AS total,

                COALESCE(
                    SUM(rb.total_amount),
                    0
                ) AS total_amount

            FROM room_bookings rb

            {$bookingWhereSql}

            GROUP BY rb.booking_status

            ORDER BY total DESC
            ",

            $bookingTypes,

            $bookingParams
        );


    /*
    |--------------------------------------------------------------------------
    | PAYMENT STATUS CHART
    |--------------------------------------------------------------------------
    */

    $paymentStatusRows =
        fetchAllRows(
            $conn,

            "
            SELECT

                p.payment_status AS status,

                COUNT(*) AS total,

                COALESCE(
                    SUM(p.amount),
                    0
                ) AS amount

            FROM payments p

            {$paymentWhereSql}

            GROUP BY p.payment_status

            ORDER BY total DESC
            ",

            $paymentTypes,

            $paymentParams
        );


    /*
    |--------------------------------------------------------------------------
    | NOTIFICATION STATISTICS
    |--------------------------------------------------------------------------
    */

    $notificationWhere = [];

    $notificationParams = [];

    $notificationTypes = '';


    if (
        $fromDate !== null
    ) {

        $notificationWhere[] =
            'ln.created_at >= ?';

        $notificationParams[] =
            $fromDate .
            ' 00:00:00';

        $notificationTypes .=
            's';
    }


    if (
        $toDate !== null
    ) {

        $notificationWhere[] =
            'ln.created_at <= ?';

        $notificationParams[] =
            $toDate .
            ' 23:59:59';

        $notificationTypes .=
            's';
    }


    if (
        $currentUserRole === 'staff'
    ) {

        $notificationWhere[] =
            'l.assigned_admin_id = ?';

        $notificationParams[] =
            $currentUserId;

        $notificationTypes .=
            'i';
    }


    $notificationWhereSql =
        count($notificationWhere) > 0
            ? 'WHERE ' .
                implode(
                    ' AND ',
                    $notificationWhere
                )
            : '';


    $notificationStats =
        fetchOneRow(
            $conn,

            "
            SELECT

                COUNT(*) AS total,

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
                        WHEN ln.channel = 'email'
                        THEN 1
                        ELSE 0
                    END
                ) AS email,

                SUM(
                    CASE
                        WHEN ln.channel = 'whatsapp'
                        THEN 1
                        ELSE 0
                    END
                ) AS whatsapp

            FROM lead_notifications ln

            INNER JOIN leads l
                ON l.id = ln.lead_id

            {$notificationWhereSql}
            ",

            $notificationTypes,

            $notificationParams
        );


    /*
    |--------------------------------------------------------------------------
    | NOTIFICATION STATUS CHART
    |--------------------------------------------------------------------------
    */

    $notificationStatusRows =
        fetchAllRows(
            $conn,

            "
            SELECT

                ln.status,

                COUNT(*) AS total

            FROM lead_notifications ln

            INNER JOIN leads l
                ON l.id = ln.lead_id

            {$notificationWhereSql}

            GROUP BY ln.status

            ORDER BY total DESC
            ",

            $notificationTypes,

            $notificationParams
        );


    /*
    |--------------------------------------------------------------------------
    | NOTIFICATION CHANNEL CHART
    |--------------------------------------------------------------------------
    */

    $notificationChannelRows =
        fetchAllRows(
            $conn,

            "
            SELECT

                ln.channel,

                COUNT(*) AS total

            FROM lead_notifications ln

            INNER JOIN leads l
                ON l.id = ln.lead_id

            {$notificationWhereSql}

            GROUP BY ln.channel

            ORDER BY total DESC
            ",

            $notificationTypes,

            $notificationParams
        );


    /*
    |--------------------------------------------------------------------------
    | MONTHLY TREND RANGE
    |--------------------------------------------------------------------------
    |
    | If user gives date range:
    |   use that range.
    |
    | Otherwise:
    |   last 12 months including current month.
    |
    |--------------------------------------------------------------------------
    */

    if (
        $fromDate !== null
    ) {

        $trendFrom =
            $fromDate;

    } else {

        $trendFrom =
            date(
                'Y-m-01',
                strtotime(
                    '-11 months'
                )
            );
    }


    $trendTo =
        $toDate !== null
            ? $toDate
            : date(
                'Y-m-t'
            );


    /*
    |--------------------------------------------------------------------------
    | MONTHLY ROOM REVENUE
    |--------------------------------------------------------------------------
    */

    $monthlyRoomRevenueRows =
        fetchAllRows(
            $conn,

            "
            SELECT

                DATE_FORMAT(
                    rb.created_at,
                    '%Y-%m'
                ) AS revenue_month,

                COUNT(*) AS total_bookings,

                COALESCE(
                    SUM(
                        CASE
                            WHEN rb.payment_status = 'paid'
                            AND rb.booking_status
                                NOT IN (
                                    'cancelled',
                                    'no_show'
                                )
                            THEN rb.total_amount
                            ELSE 0
                        END
                    ),
                    0
                ) AS revenue

            FROM room_bookings rb

            WHERE
                rb.created_at >= ?
                AND rb.created_at <= ?

            GROUP BY
                DATE_FORMAT(
                    rb.created_at,
                    '%Y-%m'
                )

            ORDER BY
                revenue_month ASC
            ",

            'ss',

            [
                $trendFrom .
                ' 00:00:00',

                $trendTo .
                ' 23:59:59'
            ]
        );


    /*
    |--------------------------------------------------------------------------
    | MONTHLY LEAD REVENUE
    |--------------------------------------------------------------------------
    */

    $monthlyLeadRevenueWhere = [

        'l.created_at >= ?',

        'l.created_at <= ?'
    ];

    $monthlyLeadRevenueParams = [

        $trendFrom .
        ' 00:00:00',

        $trendTo .
        ' 23:59:59'
    ];

    $monthlyLeadRevenueTypes =
        'ss';


    if (
        $currentUserRole === 'staff'
    ) {

        $monthlyLeadRevenueWhere[] =
            'l.assigned_admin_id = ?';

        $monthlyLeadRevenueParams[] =
            $currentUserId;

        $monthlyLeadRevenueTypes .=
            'i';
    }


    $monthlyLeadRevenueWhereSql =
        implode(
            ' AND ',
            $monthlyLeadRevenueWhere
        );


    $monthlyLeadRevenueRows =
        fetchAllRows(
            $conn,

            "
            SELECT

                DATE_FORMAT(
                    l.created_at,
                    '%Y-%m'
                ) AS lead_month,

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
                            THEN l.converted_amount
                            ELSE 0
                        END
                    ),
                    0
                ) AS converted_revenue

            FROM leads l

            WHERE
                {$monthlyLeadRevenueWhereSql}

            GROUP BY
                DATE_FORMAT(
                    l.created_at,
                    '%Y-%m'
                )

            ORDER BY
                lead_month ASC
            ",

            $monthlyLeadRevenueTypes,

            $monthlyLeadRevenueParams
        );


    /*
    |--------------------------------------------------------------------------
    | MONTHLY BOOKING TREND
    |--------------------------------------------------------------------------
    */

    $monthlyBookingRows =
        fetchAllRows(
            $conn,

            "
            SELECT

                DATE_FORMAT(
                    rb.created_at,
                    '%Y-%m'
                ) AS booking_month,

                COUNT(*) AS total_bookings,

                SUM(
                    CASE
                        WHEN rb.booking_status = 'cancelled'
                        THEN 1
                        ELSE 0
                    END
                ) AS cancelled_bookings,

                SUM(
                    CASE
                        WHEN rb.booking_status
                            NOT IN (
                                'cancelled',
                                'no_show'
                            )
                        THEN 1
                        ELSE 0
                    END
                ) AS active_bookings

            FROM room_bookings rb

            WHERE
                rb.created_at >= ?
                AND rb.created_at <= ?

            GROUP BY
                DATE_FORMAT(
                    rb.created_at,
                    '%Y-%m'
                )

            ORDER BY
                booking_month ASC
            ",

            'ss',

            [
                $trendFrom .
                ' 00:00:00',

                $trendTo .
                ' 23:59:59'
            ]
        );


    /*
    |--------------------------------------------------------------------------
    | MONTHLY LEAD CONVERSION TREND
    |--------------------------------------------------------------------------
    */

    $monthlyLeadConversionRows =
        fetchAllRows(
            $conn,

            "
            SELECT

                DATE_FORMAT(
                    l.created_at,
                    '%Y-%m'
                ) AS lead_month,

                l.domain,

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
                            THEN l.converted_amount
                            ELSE 0
                        END
                    ),
                    0
                ) AS converted_revenue

            FROM leads l

            WHERE
                l.created_at >= ?
                AND l.created_at <= ?

                " .
                (
                    $currentUserRole === 'staff'
                        ? "AND l.assigned_admin_id = ?"
                        : ""
                ) .
                "

            GROUP BY

                DATE_FORMAT(
                    l.created_at,
                    '%Y-%m'
                ),

                l.domain

            ORDER BY
                lead_month ASC
            ",

            $currentUserRole === 'staff'
                ? 'ssi'
                : 'ss',

            $currentUserRole === 'staff'
                ? [
                    $trendFrom .
                        ' 00:00:00',

                    $trendTo .
                        ' 23:59:59',

                    $currentUserId
                ]
                : [
                    $trendFrom .
                        ' 00:00:00',

                    $trendTo .
                        ' 23:59:59'
                ]
        );


    /*
    |--------------------------------------------------------------------------
    | TOP ROOMS BY BOOKING COUNT
    |--------------------------------------------------------------------------
    */

    $topRooms =
        fetchAllRows(
            $conn,

            "
            SELECT

                r.id AS room_id,
                r.room_number,
                r.name AS room_name,

                rt.name AS room_type_name,

                COUNT(
                    br.id
                ) AS booking_count,

                COALESCE(
                    SUM(
                        br.room_total
                    ),
                    0
                ) AS booking_value,

                COALESCE(
                    SUM(
                        br.number_of_nights
                    ),
                    0
                ) AS total_nights

            FROM booking_rooms br

            INNER JOIN rooms r
                ON r.id = br.room_id

            INNER JOIN room_types rt
                ON rt.id = r.room_type_id

            INNER JOIN room_bookings rb
                ON rb.id = br.booking_id

            WHERE
                rb.booking_status
                    NOT IN (
                        'cancelled',
                        'no_show'
                    )

                AND rb.created_at >= ?

                AND rb.created_at <= ?

            GROUP BY

                r.id,
                r.room_number,
                r.name,
                rt.name

            ORDER BY
                booking_count DESC,
                booking_value DESC

            LIMIT 10
            ",

            'ss',

            [
                $trendFrom .
                    ' 00:00:00',

                $trendTo .
                    ' 23:59:59'
            ]
        );


    /*
    |--------------------------------------------------------------------------
    | FOOD USAGE
    |--------------------------------------------------------------------------
    */

    $foodUsageRows =
        fetchAllRows(
            $conn,

            "
            SELECT

                fi.id AS food_item_id,

                fi.name AS food_item_name,

                fi.food_type,

                fc.name AS category_name,

                COALESCE(
                    SUM(
                        bfi.quantity
                    ),
                    0
                ) AS booking_quantity

            FROM booking_food_items bfi

            INNER JOIN food_items fi
                ON fi.id = bfi.food_item_id

            LEFT JOIN food_categories fc
                ON fc.id = fi.food_category_id

            INNER JOIN room_bookings rb
                ON rb.id = bfi.booking_id

            WHERE
                rb.created_at >= ?

                AND rb.created_at <= ?

                AND rb.booking_status
                    NOT IN (
                        'cancelled',
                        'no_show'
                    )

            GROUP BY

                fi.id,
                fi.name,
                fi.food_type,
                fc.name

            ORDER BY
                booking_quantity DESC

            LIMIT 10
            ",

            'ss',

            [
                $trendFrom .
                    ' 00:00:00',

                $trendTo .
                    ' 23:59:59'
            ]
        );


    /*
    |--------------------------------------------------------------------------
    | BOOKING FOOD PACKAGE USAGE
    |--------------------------------------------------------------------------
    */

    $foodPackageUsageRows =
        fetchAllRows(
            $conn,

            "
            SELECT

                rfp.id AS package_id,

                rfp.name AS package_name,

                rfp.meal_type,

                COALESCE(
                    SUM(
                        bfp.quantity
                    ),
                    0
                ) AS quantity

            FROM booking_food_packages bfp

            INNER JOIN room_food_packages rfp
                ON rfp.id = bfp.package_id

            INNER JOIN room_bookings rb
                ON rb.id = bfp.booking_id

            WHERE
                rb.created_at >= ?

                AND rb.created_at <= ?

                AND rb.booking_status
                    NOT IN (
                        'cancelled',
                        'no_show'
                    )

            GROUP BY

                rfp.id,
                rfp.name,
                rfp.meal_type

            ORDER BY
                quantity DESC

            LIMIT 10
            ",

            'ss',

            [
                $trendFrom .
                    ' 00:00:00',

                $trendTo .
                    ' 23:59:59'
            ]
        );


    /*
    |--------------------------------------------------------------------------
    | RECENT BOOKINGS
    |--------------------------------------------------------------------------
    */

    $recentBookings =
        fetchAllRows(
            $conn,

            "
            SELECT

                rb.id,

                rb.booking_reference,

                rb.customer_id,

                c.name AS customer_name,
                c.phone AS customer_phone,
                c.email AS customer_email,

                rb.check_in_date,
                rb.check_out_date,

                rb.total_guests,

                rb.room_subtotal,
                rb.gst_rate,
                rb.gst_amount,
                rb.total_amount,

                rb.payment_status,
                rb.booking_status,

                rb.created_at,
                rb.updated_at,

                COUNT(
                    DISTINCT br.room_id
                ) AS room_count

            FROM room_bookings rb

            INNER JOIN customers c
                ON c.id = rb.customer_id

            LEFT JOIN booking_rooms br
                ON br.booking_id = rb.id

            GROUP BY

                rb.id,
                rb.booking_reference,
                rb.customer_id,

                c.name,
                c.phone,
                c.email,

                rb.check_in_date,
                rb.check_out_date,

                rb.total_guests,

                rb.room_subtotal,
                rb.gst_rate,
                rb.gst_amount,
                rb.total_amount,

                rb.payment_status,
                rb.booking_status,

                rb.created_at,
                rb.updated_at

            ORDER BY
                rb.created_at DESC

            LIMIT 10
            "
        );


    /*
    |--------------------------------------------------------------------------
    | UPCOMING ROOM CHECK-INS
    |--------------------------------------------------------------------------
    */

    $upcomingCheckIns =
        fetchAllRows(
            $conn,

            "
            SELECT

                rb.id,

                rb.booking_reference,

                c.name AS customer_name,
                c.phone AS customer_phone,

                rb.check_in_date,
                rb.check_out_date,

                rb.total_guests,

                rb.booking_status,
                rb.payment_status,

                COALESCE(
                    GROUP_CONCAT(
                        DISTINCT
                        CONCAT(
                            r.room_number,
                            ' - ',
                            r.name
                        )
                        ORDER BY r.room_number
                        SEPARATOR ', '
                    ),
                    ''
                ) AS rooms

            FROM room_bookings rb

            INNER JOIN customers c
                ON c.id = rb.customer_id

            LEFT JOIN booking_rooms br
                ON br.booking_id = rb.id

            LEFT JOIN rooms r
                ON r.id = br.room_id

            WHERE

                rb.check_in_date >= CURDATE()

                AND rb.booking_status
                    NOT IN (
                        'cancelled',
                        'checked_out',
                        'no_show'
                    )

            GROUP BY

                rb.id,
                rb.booking_reference,

                c.name,
                c.phone,

                rb.check_in_date,
                rb.check_out_date,

                rb.total_guests,

                rb.booking_status,
                rb.payment_status

            ORDER BY
                rb.check_in_date ASC

            LIMIT 10
            "
        );


    /*
    |--------------------------------------------------------------------------
    | UPCOMING LEAD EVENTS
    |--------------------------------------------------------------------------
    */

    $upcomingLeadWhere = [

        'l.event_date IS NOT NULL',

        'l.event_date >= CURDATE()'
    ];

    $upcomingLeadParams = [];

    $upcomingLeadTypes = '';


    if (
        $currentUserRole === 'staff'
    ) {

        $upcomingLeadWhere[] =
            'l.assigned_admin_id = ?';

        $upcomingLeadParams[] =
            $currentUserId;

        $upcomingLeadTypes .=
            'i';
    }


    $upcomingLeadWhereSql =
        implode(
            ' AND ',
            $upcomingLeadWhere
        );


    $upcomingLeadEvents =
        fetchAllRows(
            $conn,

            "
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
                l.converted_amount,

                au.name AS assigned_admin_name

            FROM leads l

            LEFT JOIN admin_users au
                ON au.id = l.assigned_admin_id

            WHERE
                {$upcomingLeadWhereSql}

            ORDER BY
                l.event_date ASC

            LIMIT 10
            ",

            $upcomingLeadTypes,

            $upcomingLeadParams
        );


    /*
    |--------------------------------------------------------------------------
    | RECENT LEADS
    |--------------------------------------------------------------------------
    */

    $recentLeadWhere = [];

    $recentLeadParams = [];

    $recentLeadTypes = '';


    if (
        $currentUserRole === 'staff'
    ) {

        $recentLeadWhere[] =
            'l.assigned_admin_id = ?';

        $recentLeadParams[] =
            $currentUserId;

        $recentLeadTypes .=
            'i';
    }


    $recentLeadWhereSql =
        count($recentLeadWhere) > 0
            ? 'WHERE ' .
                implode(
                    ' AND ',
                    $recentLeadWhere
                )
            : '';


    $recentLeads =
        fetchAllRows(
            $conn,

            "
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

                l.status,

                l.estimated_amount,
                l.converted_amount,

                l.source,

                l.assigned_admin_id,

                au.name AS assigned_admin_name,
                au.role AS assigned_admin_role,

                l.created_at,
                l.updated_at

            FROM leads l

            LEFT JOIN admin_users au
                ON au.id = l.assigned_admin_id

            {$recentLeadWhereSql}

            ORDER BY
                l.created_at DESC

            LIMIT 10
            ",

            $recentLeadTypes,

            $recentLeadParams
        );


    /*
    |--------------------------------------------------------------------------
    | RECENT PAYMENTS
    |--------------------------------------------------------------------------
    */

    $recentPayments =
        fetchAllRows(
            $conn,

            "
            SELECT

                p.id,

                p.booking_id,

                rb.booking_reference,

                c.name AS customer_name,

                p.payment_gateway,

                p.razorpay_order_id,
                p.razorpay_payment_id,

                p.amount,
                p.currency,

                p.payment_status,
                p.payment_method,

                p.paid_at,

                p.created_at,
                p.updated_at

            FROM payments p

            INNER JOIN room_bookings rb
                ON rb.id = p.booking_id

            INNER JOIN customers c
                ON c.id = rb.customer_id

            ORDER BY
                p.created_at DESC

            LIMIT 10
            "
        );


    /*
    |--------------------------------------------------------------------------
    | RECENT NOTIFICATIONS
    |--------------------------------------------------------------------------
    */

    $recentNotifications =
        fetchAllRows(
            $conn,

            "
            SELECT

                ln.id,

                ln.lead_id,

                l.lead_reference,
                l.domain,

                l.customer_name,

                ln.channel,
                ln.recipient,

                ln.status,

                ln.provider_message_id,

                ln.error_message,

                ln.sent_at,
                ln.created_at

            FROM lead_notifications ln

            INNER JOIN leads l
                ON l.id = ln.lead_id

            {$notificationWhereSql}

            ORDER BY
                ln.created_at DESC

            LIMIT 10
            ",

            $notificationTypes,

            $notificationParams
        );


    /*
    |--------------------------------------------------------------------------
    | RECENT BOOKING ACTIVITY
    |--------------------------------------------------------------------------
    */

    $recentActivities =
        fetchAllRows(
            $conn,

            "
            SELECT

                bal.id,

                bal.booking_id,

                rb.booking_reference,

                bal.action,

                bal.description,

                bal.performed_by_admin_id,

                au.name AS performed_by_name,
                au.role AS performed_by_role,

                bal.created_at

            FROM booking_activity_logs bal

            INNER JOIN room_bookings rb
                ON rb.id = bal.booking_id

            LEFT JOIN admin_users au
                ON au.id = bal.performed_by_admin_id

            ORDER BY
                bal.created_at DESC

            LIMIT 20
            "
        );


    /*
    |--------------------------------------------------------------------------
    | CUSTOMER TOP BOOKERS
    |--------------------------------------------------------------------------
    */

    $topCustomers =
        fetchAllRows(
            $conn,

            "
            SELECT

                c.id AS customer_id,

                c.name,

                c.phone,
                c.email,

                COUNT(
                    rb.id
                ) AS booking_count,

                COALESCE(
                    SUM(
                        CASE
                            WHEN rb.booking_status
                                NOT IN (
                                    'cancelled',
                                    'no_show'
                                )
                            THEN rb.total_amount
                            ELSE 0
                        END
                    ),
                    0
                ) AS booking_value

            FROM customers c

            INNER JOIN room_bookings rb
                ON rb.customer_id = c.id

            GROUP BY

                c.id,
                c.name,
                c.phone,
                c.email

            ORDER BY
                booking_count DESC,
                booking_value DESC

            LIMIT 10
            "
        );


    /*
    |--------------------------------------------------------------------------
    | ROOM BOOKING FOOD PACKAGE TOTALS
    |--------------------------------------------------------------------------
    */

    $foodBookingStats =
        fetchOneRow(
            $conn,

            "
            SELECT

                (
                    SELECT COUNT(*)
                    FROM booking_food_packages
                ) AS total_package_selections,

                (
                    SELECT COALESCE(
                        SUM(quantity),
                        0
                    )
                    FROM booking_food_packages
                ) AS total_package_quantity,

                (
                    SELECT COUNT(*)
                    FROM booking_food_items
                ) AS total_custom_food_selections,

                (
                    SELECT COALESCE(
                        SUM(quantity),
                        0
                    )
                    FROM booking_food_items
                ) AS total_custom_food_quantity

            "
        );


    /*
    |--------------------------------------------------------------------------
    | MONTH LABEL GENERATOR
    |--------------------------------------------------------------------------
    */

    $months = [];

    $cursor =
        new DateTime(
            date(
                'Y-m-01',
                strtotime(
                    $trendFrom
                )
            )
        );

    $end =
        new DateTime(
            date(
                'Y-m-01',
                strtotime(
                    $trendTo
                )
            )
        );


    while (
        $cursor <= $end
    ) {

        $monthKey =
            $cursor->format(
                'Y-m'
            );

        $months[
            $monthKey
        ] = [

            'month' =>
                $monthKey,

            'label' =>
                $cursor->format(
                    'M Y'
                ),

            'room_revenue' =>
                0,

            'lead_revenue' =>
                0,

            'total_revenue' =>
                0,

            'bookings' =>
                0,

            'cancelled_bookings' =>
                0,

            'active_bookings' =>
                0,

            'total_leads' =>
                0,

            'converted_leads' =>
                0,

            'lead_conversion_rate' =>
                0
        ];


        $cursor->modify(
            '+1 month'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | MERGE ROOM REVENUE
    |--------------------------------------------------------------------------
    */

    foreach (
        $monthlyRoomRevenueRows
        as $row
    ) {

        $key =
            $row['revenue_month'];

        if (
            isset(
                $months[$key]
            )
        ) {

            $months[$key][
                'room_revenue'
            ] =
                (float)$row[
                    'revenue'
                ];

            $months[$key][
                'bookings'
            ] =
                (int)$row[
                    'total_bookings'
                ];
        }
    }


    /*
    |--------------------------------------------------------------------------
    | MERGE LEAD REVENUE
    |--------------------------------------------------------------------------
    */

    foreach (
        $monthlyLeadRevenueRows
        as $row
    ) {

        $key =
            $row['lead_month'];

        if (
            isset(
                $months[$key]
            )
        ) {

            $months[$key][
                'lead_revenue'
            ] =
                (float)$row[
                    'converted_revenue'
                ];

            $months[$key][
                'total_leads'
            ] =
                (int)$row[
                    'total_leads'
                ];

            $months[$key][
                'converted_leads'
            ] =
                (int)$row[
                    'converted_leads'
                ];
        }
    }


    /*
    |--------------------------------------------------------------------------
    | MERGE BOOKING TREND
    |--------------------------------------------------------------------------
    */

    foreach (
        $monthlyBookingRows
        as $row
    ) {

        $key =
            $row['booking_month'];

        if (
            isset(
                $months[$key]
            )
        ) {

            $months[$key][
                'bookings'
            ] =
                (int)$row[
                    'total_bookings'
                ];

            $months[$key][
                'cancelled_bookings'
            ] =
                (int)$row[
                    'cancelled_bookings'
                ];

            $months[$key][
                'active_bookings'
            ] =
                (int)$row[
                    'active_bookings'
                ];
        }
    }


    /*
    |--------------------------------------------------------------------------
    | MERGE LEAD CONVERSION
    |--------------------------------------------------------------------------
    */

    foreach (
        $monthlyLeadConversionRows
        as $row
    ) {

        $key =
            $row['lead_month'];

        if (
            isset(
                $months[$key]
            )
        ) {

            /*
            |--------------------------------------------------------------
            | Staff already filtered in SQL.
            |--------------------------------------------------------------
            */

            $months[$key][
                'total_leads'
            ] +=
                (int)$row[
                        'total_leads'
                    ];

            $months[$key][
                'converted_leads'
            ] +=
                (int)$row[
                        'converted_leads'
                    ];

            $months[$key][
                'lead_revenue'
            ] =
                (float)$months[$key][
                    'lead_revenue'
                ]
                +
                (
                    (float)$row[
                        'converted_revenue'
                    ]
                );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CALCULATE MONTHLY TOTALS
    |--------------------------------------------------------------------------
    */

    foreach (
        $months as $key => $month
    ) {

        $months[$key][
            'total_revenue'
        ] =
            (float)$month[
                'room_revenue'
            ]
            +
            (float)$month[
                'lead_revenue'
            ];


        $months[$key][
            'lead_conversion_rate'
        ] =
            $month['total_leads'] > 0

                ? round(
                    (
                        $month[
                            'converted_leads'
                        ]
                        /
                        $month[
                            'total_leads'
                        ]
                    ) *
                    100,
                    2
                )

                : 0;
    }


    $monthlyTrend =
        array_values(
            $months
        );


    /*
    |--------------------------------------------------------------------------
    | CHART.JS - REVENUE TREND
    |--------------------------------------------------------------------------
    */

    $revenueChart = [

        'labels' =>
            array_column(
                $monthlyTrend,
                'label'
            ),

        'datasets' => [

            [
                'label' =>
                    'Room Revenue',

                'data' =>
                    array_map(
                        'floatval',
                        array_column(
                            $monthlyTrend,
                            'room_revenue'
                        )
                    ),

                'borderWidth' =>
                    2,

                'tension' =>
                    0.35,

                'fill' =>
                    false
            ],

            [
                'label' =>
                    'Lead Revenue',

                'data' =>
                    array_map(
                        'floatval',
                        array_column(
                            $monthlyTrend,
                            'lead_revenue'
                        )
                    ),

                'borderWidth' =>
                    2,

                'tension' =>
                    0.35,

                'fill' =>
                    false
            ],

            [
                'label' =>
                    'Total Revenue',

                'data' =>
                    array_map(
                        'floatval',
                        array_column(
                            $monthlyTrend,
                            'total_revenue'
                        )
                    ),

                'borderWidth' =>
                    3,

                'tension' =>
                    0.35,

                'fill' =>
                    false
            ]
        ]
    ];


    /*
    |--------------------------------------------------------------------------
    | CHART.JS - BOOKING TREND
    |--------------------------------------------------------------------------
    */

    $bookingTrendChart = [

        'labels' =>
            array_column(
                $monthlyTrend,
                'label'
            ),

        'datasets' => [

            [
                'label' =>
                    'Total Bookings',

                'data' =>
                    array_map(
                        'intval',
                        array_column(
                            $monthlyTrend,
                            'bookings'
                        )
                    ),

                'borderWidth' =>
                    2,

                'tension' =>
                    0.35
            ],

            [
                'label' =>
                    'Active Bookings',

                'data' =>
                    array_map(
                        'intval',
                        array_column(
                            $monthlyTrend,
                            'active_bookings'
                        )
                    ),

                'borderWidth' =>
                    2,

                'tension' =>
                    0.35
            ],

            [
                'label' =>
                    'Cancelled Bookings',

                'data' =>
                    array_map(
                        'intval',
                        array_column(
                            $monthlyTrend,
                            'cancelled_bookings'
                        )
                    ),

                'borderWidth' =>
                    2,

                'tension' =>
                    0.35
            ]
        ]
    ];


    /*
    |--------------------------------------------------------------------------
    | CHART.JS - LEAD CONVERSION TREND
    |--------------------------------------------------------------------------
    */

    $leadConversionChart = [

        'labels' =>
            array_column(
                $monthlyTrend,
                'label'
            ),

        'datasets' => [

            [
                'label' =>
                    'Total Leads',

                'data' =>
                    array_map(
                        'intval',
                        array_column(
                            $monthlyTrend,
                            'total_leads'
                        )
                    ),

                'borderWidth' =>
                    2,

                'tension' =>
                    0.35
            ],

            [
                'label' =>
                    'Converted Leads',

                'data' =>
                    array_map(
                        'intval',
                        array_column(
                            $monthlyTrend,
                            'converted_leads'
                        )
                    ),

                'borderWidth' =>
                    2,

                'tension' =>
                    0.35
            ]
        ]
    ];


    /*
    |--------------------------------------------------------------------------
    | CHART.JS - LEAD DOMAIN
    |--------------------------------------------------------------------------
    */

    $leadDomainChart = [

        'labels' => [],

        'datasets' => [

            [
                'label' =>
                    'Leads',

                'data' => [],

                'borderWidth' =>
                    1
            ]
        ]
    ];


    foreach (
        $leadDomainRows as $row
    ) {

        $leadDomainChart[
            'labels'
        ][] =
            strtoupper(
                $row['domain']
            );

        $leadDomainChart[
            'datasets'
        ][0][
            'data'
        ][] =
            (int)$row[
                'total_leads'
            ];
    }


    /*
    |--------------------------------------------------------------------------
    | CHART.JS - LEAD STATUS
    |--------------------------------------------------------------------------
    */

    $leadStatusChart = [

        'labels' => [],

        'datasets' => [

            [
                'label' =>
                    'Lead Status',

                'data' => [],

                'borderWidth' =>
                    1
            ]
        ]
    ];


    foreach (
        $leadStatusRows as $row
    ) {

        $leadStatusChart[
            'labels'
        ][] =
            strtoupper(
                str_replace(
                    '_',
                    ' ',
                    $row['status']
                )
            );

        $leadStatusChart[
            'datasets'
        ][0][
            'data'
        ][] =
            (int)$row[
                'total'
            ];
    }


    /*
    |--------------------------------------------------------------------------
    | CHART.JS - ROOM STATUS
    |--------------------------------------------------------------------------
    */

    $roomStatusChart = [

        'labels' => [],

        'datasets' => [

            [
                'label' =>
                    'Rooms',

                'data' => [],

                'borderWidth' =>
                    1
            ]
        ]
    ];


    foreach (
        $roomStatusRows as $row
    ) {

        $roomStatusChart[
            'labels'
        ][] =
            strtoupper(
                $row['status']
            );

        $roomStatusChart[
            'datasets'
        ][0][
            'data'
        ][] =
            (int)$row[
                'total'
            ];
    }


    /*
    |--------------------------------------------------------------------------
    | CHART.JS - HALL STATUS
    |--------------------------------------------------------------------------
    */

    $hallStatusChart = [

        'labels' => [],

        'datasets' => [

            [
                'label' =>
                    'Halls',

                'data' => [],

                'borderWidth' =>
                    1
            ]
        ]
    ];


    foreach (
        $hallStatusRows as $row
    ) {

        $hallStatusChart[
            'labels'
        ][] =
            strtoupper(
                $row['status']
            );

        $hallStatusChart[
            'datasets'
        ][0][
            'data'
        ][] =
            (int)$row[
                'total'
            ];
    }


    /*
    |--------------------------------------------------------------------------
    | CHART.JS - BOOKING STATUS
    |--------------------------------------------------------------------------
    */

    $bookingStatusChart = [

        'labels' => [],

        'datasets' => [

            [
                'label' =>
                    'Bookings',

                'data' => [],

                'borderWidth' =>
                    1
            ]
        ]
    ];


    foreach (
        $bookingStatusRows as $row
    ) {

        $bookingStatusChart[
            'labels'
        ][] =
            strtoupper(
                str_replace(
                    '_',
                    ' ',
                    $row['status']
                )
            );

        $bookingStatusChart[
            'datasets'
        ][0][
            'data'
        ][] =
            (int)$row[
                'total'
            ];
    }


    /*
    |--------------------------------------------------------------------------
    | CHART.JS - PAYMENT STATUS
    |--------------------------------------------------------------------------
    */

    $paymentStatusChart = [

        'labels' => [],

        'datasets' => [

            [
                'label' =>
                    'Payments',

                'data' => [],

                'borderWidth' =>
                    1
            ]
        ]
    ];


    foreach (
        $paymentStatusRows as $row
    ) {

        $paymentStatusChart[
            'labels'
        ][] =
            strtoupper(
                $row['status']
            );

        $paymentStatusChart[
            'datasets'
        ][0][
            'data'
        ][] =
            (int)$row[
                'total'
            ];
    }


    /*
    |--------------------------------------------------------------------------
    | CHART.JS - NOTIFICATION STATUS
    |--------------------------------------------------------------------------
    */

    $notificationStatusChart = [

        'labels' => [],

        'datasets' => [

            [
                'label' =>
                    'Notifications',

                'data' => [],

                'borderWidth' =>
                    1
            ]
        ]
    ];


    foreach (
        $notificationStatusRows
        as $row
    ) {

        $notificationStatusChart[
            'labels'
        ][] =
            strtoupper(
                $row['status']
            );

        $notificationStatusChart[
            'datasets'
        ][0][
            'data'
        ][] =
            (int)$row[
                'total'
            ];
    }


    /*
    |--------------------------------------------------------------------------
    | CHART.JS - NOTIFICATION CHANNEL
    |--------------------------------------------------------------------------
    */

    $notificationChannelChart = [

        'labels' => [],

        'datasets' => [

            [
                'label' =>
                    'Notifications',

                'data' => [],

                'borderWidth' =>
                    1
            ]
        ]
    ];


    foreach (
        $notificationChannelRows
        as $row
    ) {

        $notificationChannelChart[
            'labels'
        ][] =
            strtoupper(
                $row['channel']
            );

        $notificationChannelChart[
            'datasets'
        ][0][
            'data'
        ][] =
            (int)$row[
                'total'
            ];
    }


    /*
    |--------------------------------------------------------------------------
    | DATABASE VIEWS
    |--------------------------------------------------------------------------
    |
    | Existing reporting views from the database are also exposed.
    |
    |--------------------------------------------------------------------------
    */

    $viewRoomRevenue =
        fetchAllRows(
            $conn,

            "
            SELECT *
            FROM vw_room_revenue
            "
        );


    $viewMonthlyRoomRevenue =
        fetchAllRows(
            $conn,

            "
            SELECT *
            FROM vw_monthly_room_revenue
            ORDER BY revenue_month ASC
            "
        );


    $viewLeadSummary =
        fetchAllRows(
            $conn,

            "
            SELECT *
            FROM vw_lead_summary
            ORDER BY domain ASC
            "
        );


    $viewMonthlyLeadConversion =
        fetchAllRows(
            $conn,

            "
            SELECT *
            FROM vw_monthly_lead_conversion
            ORDER BY lead_month ASC, domain ASC
            "
        );


    /*
    |--------------------------------------------------------------------------
    | TOTAL DASHBOARD REVENUE
    |--------------------------------------------------------------------------
    */

    $roomPaidRevenue =
        (float)(
            $bookingStats[
                'booking_paid_value'
            ]
            ?? 0
        );


    $leadConvertedRevenue =
        (float)(
            $leadStats[
                'converted_revenue'
            ]
            ?? 0
        );


    $totalRevenue =
        $roomPaidRevenue +
        $leadConvertedRevenue;


    /*
    |--------------------------------------------------------------------------
    | AVERAGE BOOKING VALUE
    |--------------------------------------------------------------------------
    */

    $averageBookingValue =
        $totalLeads > 0
            ? (
                (float)(
                    $bookingStats[
                        'active_booking_value'
                    ]
                    ?? 0
                )
                /
                max(
                    1,
                    (int)(
                        $bookingStats[
                            'total_bookings'
                        ]
                        ?? 0
                    )
                )
            )
            : 0;


    /*
    |--------------------------------------------------------------------------
    | AVERAGE NIGHTS
    |--------------------------------------------------------------------------
    */

    $totalBookingCount =
        (int)(
            $bookingStats[
                'total_bookings'
            ]
            ?? 0
        );


    $totalNights =
        (int)(
            $bookingStats[
                'total_nights'
            ]
            ?? 0
        );


    $averageNights =
        $totalBookingCount > 0
            ? round(
                $totalNights /
                $totalBookingCount,
                2
            )
            : 0;


    /*
    |--------------------------------------------------------------------------
    | LEAD DOMAIN RESPONSE
    |--------------------------------------------------------------------------
    */

    $leadDomains = [];

    foreach (
        $leadDomainRows as $row
    ) {

        $domainTotal =
            (int)$row[
                'total_leads'
            ];

        $domainConverted =
            (int)$row[
                'converted_leads'
            ];

        $leadDomains[] = [

            'domain' =>
                $row['domain'],

            'total_leads' =>
                $domainTotal,

            'new_leads' =>
                (int)$row[
                    'new_leads'
                ],

            'contacted_leads' =>
                (int)$row[
                    'contacted_leads'
                ],

            'follow_up_leads' =>
                (int)$row[
                    'follow_up_leads'
                ],

            'converted_leads' =>
                $domainConverted,

            'lost_leads' =>
                (int)$row[
                    'lost_leads'
                ],

            'cancelled_leads' =>
                (int)$row[
                    'cancelled_leads'
                ],

            'conversion_rate' =>
                $domainTotal > 0
                    ? round(
                        (
                            $domainConverted /
                            $domainTotal
                        ) *
                        100,
                        2
                    )
                    : 0,

            'converted_revenue' =>
                (float)$row[
                    'converted_revenue'
                ],

            'open_pipeline' =>
                (float)$row[
                    'open_pipeline'
                ]
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | FINAL RESPONSE
    |--------------------------------------------------------------------------
    */

    sendJsonResponse(

        200,

        true,

        'Dashboard overview fetched successfully.',

        [

            /*
            |--------------------------------------------------------------------------
            | META
            |--------------------------------------------------------------------------
            */

            'meta' => [

                'dashboard' =>
                    'SM GROUP',

                'generated_at' =>
                    date(
                        'Y-m-d H:i:s'
                    ),

                'timezone' =>
                    date_default_timezone_get(),

                'currency' =>
                    $settings[
                        'currency'
                    ]['value']
                    ?? 'INR',

                'currency_symbol' =>
                    $settings[
                        'currency_symbol'
                    ]['value']
                    ?? '₹'
            ],


            /*
            |--------------------------------------------------------------------------
            | FILTERS
            |--------------------------------------------------------------------------
            */

            'filters' => [

                'from_date' =>
                    $fromDate,

                'to_date' =>
                    $toDate,

                'trend_from' =>
                    $trendFrom,

                'trend_to' =>
                    $trendTo
            ],


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

                'lead_scope' =>
                    $currentUserRole === 'staff'
                        ? 'assigned_leads_only'
                        : 'all_leads'
            ],


            /*
            |--------------------------------------------------------------------------
            | KPI CARDS
            |--------------------------------------------------------------------------
            */

            'kpis' => [

                'revenue' => [

                    'total_revenue' =>
                        round(
                            $totalRevenue,
                            2
                        ),

                    'room_revenue' =>
                        round(
                            $roomPaidRevenue,
                            2
                        ),

                    'lead_revenue' =>
                        round(
                            $leadConvertedRevenue,
                            2
                        ),

                    'payment_captured_amount' =>
                        round(
                            (float)(
                                $paymentStats[
                                    'captured_amount'
                                ]
                                ?? 0
                            ),
                            2
                        )
                ],


                'room_booking' => [

                    'total_bookings' =>
                        (int)(
                            $bookingStats[
                                'total_bookings'
                            ]
                            ?? 0
                        ),

                    'active_booking_value' =>
                        round(
                            (float)(
                                $bookingStats[
                                    'active_booking_value'
                                ]
                                ?? 0
                            ),
                            2
                        ),

                    'paid_value' =>
                        round(
                            $roomPaidRevenue,
                            2
                        ),

                    'pending_value' =>
                        round(
                            (float)(
                                $bookingStats[
                                    'booking_pending_value'
                                ]
                                ?? 0
                            ),
                            2
                        ),

                    'gst_value' =>
                        round(
                            (float)(
                                $bookingStats[
                                    'booking_gst_value'
                                ]
                                ?? 0
                            ),
                            2
                        ),

                    'total_guests' =>
                        (int)(
                            $bookingStats[
                                'total_guests'
                            ]
                            ?? 0
                        ),

                    'total_nights' =>
                        $totalNights,

                    'average_nights' =>
                        $averageNights,

                    'average_booking_value' =>
                        round(
                            $averageBookingValue,
                            2
                        )
                ],


                'leads' => [

                    'total' =>
                        $totalLeads,

                    'new' =>
                        (int)(
                            $leadStats[
                                'new_leads'
                            ]
                            ?? 0
                        ),

                    'contacted' =>
                        (int)(
                            $leadStats[
                                'contacted_leads'
                            ]
                            ?? 0
                        ),

                    'follow_up' =>
                        (int)(
                            $leadStats[
                                'follow_up_leads'
                            ]
                            ?? 0
                        ),

                    'converted' =>
                        $convertedLeads,

                    'lost' =>
                        (int)(
                            $leadStats[
                                'lost_leads'
                            ]
                            ?? 0
                        ),

                    'cancelled' =>
                        (int)(
                            $leadStats[
                                'cancelled_leads'
                            ]
                            ?? 0
                        ),

                    'conversion_rate' =>
                        $leadConversionRate,

                    'estimated_pipeline' =>
                        round(
                            (float)(
                                $leadStats[
                                    'estimated_pipeline'
                                ]
                                ?? 0
                            ),
                            2
                        ),

                    'open_pipeline' =>
                        round(
                            (float)(
                                $leadStats[
                                    'open_pipeline'
                                ]
                                ?? 0
                            ),
                            2
                        ),

                    'converted_revenue' =>
                        round(
                            $leadConvertedRevenue,
                            2
                        ),

                    'average_converted_value' =>
                        round(
                            (float)(
                                $leadStats[
                                    'average_converted_value'
                                ]
                                ?? 0
                            ),
                            2
                        )
                ],


                'payments' => [

                    'total' =>
                        (int)(
                            $paymentStats[
                                'total_payments'
                            ]
                            ?? 0
                        ),

                    'created' =>
                        (int)(
                            $paymentStats[
                                'created_payments'
                            ]
                            ?? 0
                        ),

                    'authorized' =>
                        (int)(
                            $paymentStats[
                                'authorized_payments'
                            ]
                            ?? 0
                        ),

                    'captured' =>
                        (int)(
                            $paymentStats[
                                'captured_payments'
                            ]
                            ?? 0
                        ),

                    'failed' =>
                        (int)(
                            $paymentStats[
                                'failed_payments'
                            ]
                            ?? 0
                        ),

                    'refunded' =>
                        (int)(
                            $paymentStats[
                                'refunded_payments'
                            ]
                            ?? 0
                        ),

                    'captured_amount' =>
                        round(
                            (float)(
                                $paymentStats[
                                    'captured_amount'
                                ]
                                ?? 0
                            ),
                            2
                        ),

                    'created_amount' =>
                        round(
                            (float)(
                                $paymentStats[
                                    'created_amount'
                                ]
                                ?? 0
                            ),
                            2
                        ),

                    'failed_amount' =>
                        round(
                            (float)(
                                $paymentStats[
                                    'failed_amount'
                                ]
                                ?? 0
                            ),
                            2
                        ),

                    'refunded_amount' =>
                        round(
                            (float)(
                                $paymentStats[
                                    'refunded_amount'
                                ]
                                ?? 0
                            ),
                            2
                        )
                ],


                'notifications' => [

                    'total' =>
                        (int)(
                            $notificationStats[
                                'total'
                            ]
                            ?? 0
                        ),

                    'sent' =>
                        (int)(
                            $notificationStats[
                                'sent'
                            ]
                            ?? 0
                        ),

                    'pending' =>
                        (int)(
                            $notificationStats[
                                'pending'
                            ]
                            ?? 0
                        ),

                    'failed' =>
                        (int)(
                            $notificationStats[
                                'failed'
                            ]
                            ?? 0
                        ),

                    'email' =>
                        (int)(
                            $notificationStats[
                                'email'
                            ]
                            ?? 0
                        ),

                    'whatsapp' =>
                        (int)(
                            $notificationStats[
                                'whatsapp'
                            ]
                            ?? 0
                        )
                ]
            ],


            /*
            |--------------------------------------------------------------------------
            | MASTER DATA
            |--------------------------------------------------------------------------
            */

            'master_data' => [

                'admins' => [

                    'total' =>
                        (int)$adminStats[
                            'total'
                        ],

                    'active' =>
                        (int)$adminStats[
                            'active'
                        ],

                    'inactive' =>
                        (int)$adminStats[
                            'inactive'
                        ],

                    'super_admins' =>
                        (int)$adminStats[
                            'super_admins'
                        ],

                    'admins' =>
                        (int)$adminStats[
                            'admins'
                        ],

                    'staff' =>
                        (int)$adminStats[
                            'staff'
                        ]
                ],


                'customers' => [

                    'total' =>
                        (int)$customerStats[
                            'total'
                        ],

                    'active' =>
                        (int)$customerStats[
                            'active'
                        ],

                    'inactive' =>
                        (int)$customerStats[
                            'inactive'
                        ],

                    'blocked' =>
                        (int)$customerStats[
                            'blocked'
                        ],

                    'email_verified' =>
                        (int)$customerStats[
                            'email_verified'
                        ],

                    'phone_verified' =>
                        (int)$customerStats[
                            'phone_verified'
                        ]
                ],


                'customer_sessions' => [

                    'total' =>
                        (int)$sessionStats[
                            'total'
                        ],

                    'active' =>
                        (int)$sessionStats[
                            'active'
                        ],

                    'revoked' =>
                        (int)$sessionStats[
                            'revoked'
                        ],

                    'expired' =>
                        (int)$sessionStats[
                            'expired'
                        ]
                ],


                'room_types' => [

                    'total' =>
                        (int)$roomTypeStats[
                            'total'
                        ],

                    'active' =>
                        (int)$roomTypeStats[
                            'active'
                        ],

                    'inactive' =>
                        (int)$roomTypeStats[
                            'inactive'
                        ]
                ],


                'rooms' => [

                    'total' =>
                        (int)$roomStats[
                            'total'
                        ],

                    'available' =>
                        (int)$roomStats[
                            'available'
                        ],

                    'maintenance' =>
                        (int)$roomStats[
                            'maintenance'
                        ],

                    'inactive' =>
                        (int)$roomStats[
                            'inactive'
                        ],

                    'available_room_tariff' =>
                        round(
                            (float)$roomStats[
                                'available_room_tariff'
                            ],
                            2
                        ),

                    'average_available_room_tariff' =>
                        round(
                            (float)$roomStats[
                                'average_available_room_tariff'
                            ],
                            2
                        ),

                    'images' =>
                        (int)$roomContentStats[
                            'total_room_images'
                        ],

                    'amenities' =>
                        (int)$roomContentStats[
                            'total_amenities'
                        ],

                    'active_amenities' =>
                        (int)$roomContentStats[
                            'active_amenities'
                        ],

                    'amenity_mappings' =>
                        (int)$roomContentStats[
                            'total_room_amenity_mappings'
                        ]
                ],


                'food_categories' => [

                    'total' =>
                        (int)$foodCategoryStats[
                            'total'
                        ],

                    'active' =>
                        (int)$foodCategoryStats[
                            'active'
                        ],

                    'inactive' =>
                        (int)$foodCategoryStats[
                            'inactive'
                        ]
                ],


                'food_items' => [

                    'total' =>
                        (int)$foodStats[
                            'total'
                        ],

                    'active' =>
                        (int)$foodStats[
                            'active'
                        ],

                    'inactive' =>
                        (int)$foodStats[
                            'inactive'
                        ],

                    'veg' =>
                        (int)$foodStats[
                            'veg'
                        ],

                    'non_veg' =>
                        (int)$foodStats[
                            'non_veg'
                        ],

                    'egg' =>
                        (int)$foodStats[
                            'egg'
                        ],

                    'vegan' =>
                        (int)$foodStats[
                            'vegan'
                        ],

                    'other' =>
                        (int)$foodStats[
                            'other'
                        ]
                ],


                'room_food_packages' => [

                    'total' =>
                        (int)$roomFoodPackageStats[
                            'total'
                        ],

                    'active' =>
                        (int)$roomFoodPackageStats[
                            'active'
                        ],

                    'inactive' =>
                        (int)$roomFoodPackageStats[
                            'inactive'
                        ],

                    'items' =>
                        (int)$roomFoodPackageStats[
                            'total_items'
                        ]
                ],


                'hall_types' => [

                    'total' =>
                        (int)$hallTypeStats[
                            'total'
                        ],

                    'active' =>
                        (int)$hallTypeStats[
                            'active'
                        ],

                    'inactive' =>
                        (int)$hallTypeStats[
                            'inactive'
                        ]
                ],


                'halls' => [

                    'total' =>
                        (int)$hallStats[
                            'total'
                        ],

                    'available' =>
                        (int)$hallStats[
                            'available'
                        ],

                    'maintenance' =>
                        (int)$hallStats[
                            'maintenance'
                        ],

                    'inactive' =>
                        (int)$hallStats[
                            'inactive'
                        ],

                    'total_capacity' =>
                        (int)$hallStats[
                            'total_capacity'
                        ],

                    'average_available_capacity' =>
                        round(
                            (float)$hallStats[
                                'average_available_capacity'
                            ],
                            2
                        ),

                    'images' =>
                        (int)$hallContentStats[
                            'total_hall_images'
                        ],

                    'facilities' =>
                        (int)$hallContentStats[
                            'total_hall_facilities'
                        ],

                    'active_facilities' =>
                        (int)$hallContentStats[
                            'active_hall_facilities'
                        ],

                    'facility_mappings' =>
                        (int)$hallContentStats[
                            'total_hall_facility_mappings'
                        ]
                ],


                'dining_packages' => [

                    'total' =>
                        (int)$diningPackageStats[
                            'total'
                        ],

                    'active' =>
                        (int)$diningPackageStats[
                            'active'
                        ],

                    'inactive' =>
                        (int)$diningPackageStats[
                            'inactive'
                        ],

                    'items' =>
                        (int)$diningPackageStats[
                            'total_items'
                        ]
                ],


                'catering_packages' => [

                    'total' =>
                        (int)$cateringPackageStats[
                            'total'
                        ],

                    'active' =>
                        (int)$cateringPackageStats[
                            'active'
                        ],

                    'inactive' =>
                        (int)$cateringPackageStats[
                            'inactive'
                        ],

                    'items' =>
                        (int)$cateringPackageStats[
                            'total_items'
                        ]
                ]
            ],


            /*
            |--------------------------------------------------------------------------
            | BOOKING BREAKDOWNS
            |--------------------------------------------------------------------------
            */

            'bookings' => [

                'status' =>
                    $bookingStatusRows,

                'food' => [

                    'total_package_selections' =>
                        (int)$foodBookingStats[
                            'total_package_selections'
                        ],

                    'total_package_quantity' =>
                        (int)$foodBookingStats[
                            'total_package_quantity'
                        ],

                    'total_custom_food_selections' =>
                        (int)$foodBookingStats[
                            'total_custom_food_selections'
                        ],

                    'total_custom_food_quantity' =>
                        (int)$foodBookingStats[
                            'total_custom_food_quantity'
                        ]
                ],

                'food_package_usage' =>
                    $foodPackageUsageRows,

                'food_item_usage' =>
                    $foodUsageRows
            ],


            /*
            |--------------------------------------------------------------------------
            | LEADS
            |--------------------------------------------------------------------------
            */

            'leads' => [

                'by_domain' =>
                    $leadDomains,

                'by_status' =>
                    $leadStatusRows
            ],


            /*
            |--------------------------------------------------------------------------
            | INVENTORY
            |--------------------------------------------------------------------------
            */

            'inventory' => [

                'rooms' => [

                    'by_status' =>
                        $roomStatusRows,

                    'by_type' =>
                        $roomTypeRows
                ],

                'halls' => [

                    'by_status' =>
                        $hallStatusRows,

                    'by_type' =>
                        $hallTypeRows
                ]
            ],


            /*
            |--------------------------------------------------------------------------
            | CHART.JS DATA
            |--------------------------------------------------------------------------
            */

            'charts' => [

                'revenue_trend' =>
                    $revenueChart,

                'booking_trend' =>
                    $bookingTrendChart,

                'lead_conversion_trend' =>
                    $leadConversionChart,

                'lead_by_domain' =>
                    $leadDomainChart,

                'lead_by_status' =>
                    $leadStatusChart,

                'room_by_status' =>
                    $roomStatusChart,

                'hall_by_status' =>
                    $hallStatusChart,

                'booking_by_status' =>
                    $bookingStatusChart,

                'payment_by_status' =>
                    $paymentStatusChart,

                'notification_by_status' =>
                    $notificationStatusChart,

                'notification_by_channel' =>
                    $notificationChannelChart
            ],


            /*
            |--------------------------------------------------------------------------
            | TREND RAW DATA
            |--------------------------------------------------------------------------
            */

            'trends' => [

                'monthly' =>
                    $monthlyTrend,

                'monthly_room_revenue' =>
                    $monthlyRoomRevenueRows,

                'monthly_lead_conversion' =>
                    $monthlyLeadConversionRows
            ],


            /*
            |--------------------------------------------------------------------------
            | TOP PERFORMERS
            |--------------------------------------------------------------------------
            */

            'top' => [

                'rooms' =>
                    $topRooms,

                'customers' =>
                    $topCustomers,

                'food_items' =>
                    $foodUsageRows,

                'food_packages' =>
                    $foodPackageUsageRows
            ],


            /*
            |--------------------------------------------------------------------------
            | RECENT DATA
            |--------------------------------------------------------------------------
            */

            'recent' => [

                'bookings' =>
                    $recentBookings,

                'leads' =>
                    $recentLeads,

                'payments' =>
                    $recentPayments,

                'notifications' =>
                    $recentNotifications,

                'booking_activity' =>
                    $recentActivities
            ],


            /*
            |--------------------------------------------------------------------------
            | UPCOMING
            |--------------------------------------------------------------------------
            */

            'upcoming' => [

                'room_check_ins' =>
                    $upcomingCheckIns,

                'lead_events' =>
                    $upcomingLeadEvents
            ],


            /*
            |--------------------------------------------------------------------------
            | EXISTING DATABASE REPORTING VIEWS
            |--------------------------------------------------------------------------
            */

            'database_views' => [

                'room_revenue' =>
                    $viewRoomRevenue,

                'monthly_room_revenue' =>
                    $viewMonthlyRoomRevenue,

                'lead_summary' =>
                    $viewLeadSummary,

                'monthly_lead_conversion' =>
                    $viewMonthlyLeadConversion
            ],


            /*
            |--------------------------------------------------------------------------
            | SETTINGS
            |--------------------------------------------------------------------------
            */

            'settings' =>
                $settings
        ]
    );


} catch (
    Throwable $e
) {


    /*
    |--------------------------------------------------------------------------
    | LOG ERROR
    |--------------------------------------------------------------------------
    */

    error_log(
        'SM GROUP dashboard/overview.php error: ' .
        $e->getMessage()
    );


    /*
    |--------------------------------------------------------------------------
    | ERROR RESPONSE
    |--------------------------------------------------------------------------
    */

    sendJsonResponse(

        500,

        false,

        'Failed to load dashboard overview.',

        [

            'error' =>
                $e->getMessage()
        ]
    );
}

?>