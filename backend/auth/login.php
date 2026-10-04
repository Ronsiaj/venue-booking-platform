<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/jwt.php';


// ============================================================
// CORS
// ============================================================

header(
    'Content-Type: application/json; charset=utf-8'
);

header(
    'Access-Control-Allow-Origin: *'
);

header(
    'Access-Control-Allow-Methods: POST, OPTIONS'
);

header(
    'Access-Control-Allow-Headers: Content-Type, Authorization'
);


// ============================================================
// CORS PREFLIGHT
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {

    http_response_code(200);

    exit;
}


// ============================================================
// ALLOW ONLY POST
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        'status' => false,
        'message' => 'Only POST method is allowed.'
    ]);

    exit;
}


// ============================================================
// READ JSON INPUT
// ============================================================

$rawInput = file_get_contents('php://input');

if (
    $rawInput === false ||
    trim($rawInput) === ''
) {

    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' => 'Request body is required.'
    ]);

    exit;
}


$input = json_decode(
    $rawInput,
    true
);


// ============================================================
// JSON VALIDATION
// ============================================================

if (
    json_last_error() !== JSON_ERROR_NONE ||
    !is_array($input)
) {

    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' => 'Request body must be a valid JSON object.'
    ]);

    exit;
}


// ============================================================
// ALLOWED FIELDS
// ============================================================

$allowedFields = [
    'email',
    'password'
];

$unknownFields = array_diff(
    array_keys($input),
    $allowedFields
);

if (!empty($unknownFields)) {

    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' => 'Unknown field(s) found in request.',
        'unknown_fields' => array_values($unknownFields)
    ]);

    exit;
}


// ============================================================
// GET INPUT
// ============================================================

$email = isset($input['email'])
    ? strtolower(trim($input['email']))
    : '';

$password = isset($input['password'])
    ? $input['password']
    : '';


// ============================================================
// EMAIL REQUIRED
// ============================================================

if ($email === '') {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Email is required.'
    ]);

    exit;
}


// ============================================================
// PASSWORD REQUIRED
// ============================================================

if ($password === '') {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Password is required.'
    ]);

    exit;
}


// ============================================================
// EMAIL TYPE VALIDATION
// ============================================================

if (!is_string($email)) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Email must be a valid string.'
    ]);

    exit;
}


// ============================================================
// PASSWORD TYPE VALIDATION
// ============================================================

if (!is_string($password)) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Password must be a valid string.'
    ]);

    exit;
}


// ============================================================
// EMAIL LENGTH VALIDATION
// ============================================================

$emailMaxLength = isset($_ENV['EMAIL_MAX_LENGTH'])
    ? (int) $_ENV['EMAIL_MAX_LENGTH']
    : 150;

if (
    strlen($email) > $emailMaxLength
) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Email address is too long.'
    ]);

    exit;
}


// ============================================================
// EMAIL FORMAT VALIDATION
// ============================================================

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Please enter a valid email address.'
    ]);

    exit;
}


// ============================================================
// PASSWORD LENGTH VALIDATION
// ============================================================

$passwordMinLength = isset($_ENV['PASSWORD_MIN_LENGTH'])
    ? (int) $_ENV['PASSWORD_MIN_LENGTH']
    : 8;

$passwordMaxLength = isset($_ENV['PASSWORD_MAX_LENGTH'])
    ? (int) $_ENV['PASSWORD_MAX_LENGTH']
    : 72;


if (
    strlen($password) < $passwordMinLength ||
    strlen($password) > $passwordMaxLength
) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            "Password must be between {$passwordMinLength} and {$passwordMaxLength} characters."
    ]);

    exit;
}


// ============================================================
// FIND USER IN ADMIN_USERS
// ============================================================

$adminQuery = $conn->prepare("
    SELECT
        id,
        name,
        email,
        phone,
        password_hash,
        role,
        status
    FROM admin_users
    WHERE LOWER(email) = ?
    LIMIT 1
");


if (!$adminQuery) {

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to process login request.'
    ]);

    exit;
}


$adminQuery->bind_param(
    's',
    $email
);


if (!$adminQuery->execute()) {

    $adminQuery->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to process login request.'
    ]);

    exit;
}


$adminResult = $adminQuery->get_result();


// ============================================================
// ADMIN / STAFF FOUND
// ============================================================

if ($adminResult->num_rows > 0) {

    $user = $adminResult->fetch_assoc();

    $adminQuery->close();

    $userType = 'admin_user';

} else {

    $adminQuery->close();

    $user = null;

    $userType = null;
}


// ============================================================
// IF NOT ADMIN/STAFF → CHECK CUSTOMER
// ============================================================

if ($user === null) {

    /*
     * IMPORTANT:
     * customers table does NOT have a role column.
     *
     * Customer role is assigned by application logic below.
     */

    $customerQuery = $conn->prepare("
        SELECT
            id,
            name,
            email,
            phone,
            password_hash,
            status
        FROM customers
        WHERE LOWER(email) = ?
        LIMIT 1
    ");


    if (!$customerQuery) {

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Unable to process login request.'
        ]);

        exit;
    }


    $customerQuery->bind_param(
        's',
        $email
    );


    if (!$customerQuery->execute()) {

        $customerQuery->close();

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Unable to process login request.'
        ]);

        exit;
    }


    $customerResult = $customerQuery->get_result();


    // ========================================================
    // CUSTOMER FOUND
    // ========================================================

    if ($customerResult->num_rows > 0) {

        $user = $customerResult->fetch_assoc();

        $customerQuery->close();

        /*
         * customers table has no role column.
         * Assign customer role at application level.
         */
        $user['role'] = 'customer';

        $userType = 'customer';

    } else {

        $customerQuery->close();

        $user = null;

        $userType = null;
    }
}


// ============================================================
// USER NOT FOUND
// ============================================================

if ($user === null) {

    http_response_code(401);

    echo json_encode([
        'status' => false,
        'message' => 'Invalid email or password.'
    ]);

    exit;
}


// ============================================================
// USER ID VALIDATION
// ============================================================

if (
    !isset($user['id']) ||
    !is_numeric($user['id']) ||
    (int) $user['id'] <= 0
) {

    http_response_code(401);

    echo json_encode([
        'status' => false,
        'message' => 'Invalid login credentials.'
    ]);

    exit;
}


// ============================================================
// ROLE VALIDATION
// ============================================================

if (
    !isset($user['role']) ||
    !in_array(
        $user['role'],
        [
            'admin',
            'staff',
            'customer'
        ],
        true
    )
) {

    http_response_code(401);

    echo json_encode([
        'status' => false,
        'message' => 'Invalid login credentials.'
    ]);

    exit;
}


$role = $user['role'];


// ============================================================
// TABLE / ROLE CONSISTENCY CHECK
// ============================================================

if (
    $userType === 'admin_user' &&
    !in_array(
        $role,
        [
            'admin',
            'staff'
        ],
        true
    )
) {

    http_response_code(401);

    echo json_encode([
        'status' => false,
        'message' => 'Invalid login credentials.'
    ]);

    exit;
}


if (
    $userType === 'customer' &&
    $role !== 'customer'
) {

    http_response_code(401);

    echo json_encode([
        'status' => false,
        'message' => 'Invalid login credentials.'
    ]);

    exit;
}


// ============================================================
// ACCOUNT STATUS CHECK
// ============================================================

if (
    !isset($user['status']) ||
    $user['status'] !== 'active'
) {

    http_response_code(403);

    echo json_encode([
        'status' => false,
        'message' => 'Your account is inactive.'
    ]);

    exit;
}


// ============================================================
// PASSWORD HASH VALIDATION
// ============================================================

if (
    !isset($user['password_hash']) ||
    !is_string($user['password_hash']) ||
    $user['password_hash'] === ''
) {

    http_response_code(401);

    echo json_encode([
        'status' => false,
        'message' => 'Invalid email or password.'
    ]);

    exit;
}


// ============================================================
// PASSWORD VERIFY
// ============================================================

if (
    !password_verify(
        $password,
        $user['password_hash']
    )
) {

    http_response_code(401);

    echo json_encode([
        'status' => false,
        'message' => 'Invalid email or password.'
    ]);

    exit;
}


// ============================================================
// PASSWORD REHASH
// ============================================================

if (
    password_needs_rehash(
        $user['password_hash'],
        PASSWORD_DEFAULT
    )
) {

    $newPasswordHash = password_hash(
        $password,
        PASSWORD_DEFAULT
    );


    if ($newPasswordHash !== false) {

        if ($userType === 'admin_user') {

            $updatePassword = $conn->prepare("
                UPDATE admin_users
                SET password_hash = ?
                WHERE id = ?
            ");

        } else {

            $updatePassword = $conn->prepare("
                UPDATE customers
                SET password_hash = ?
                WHERE id = ?
            ");
        }


        if ($updatePassword) {

            $userId = (int) $user['id'];

            $updatePassword->bind_param(
                'si',
                $newPasswordHash,
                $userId
            );

            $updatePassword->execute();

            $updatePassword->close();
        }
    }
}


// ============================================================
// UPDATE LAST LOGIN FOR CUSTOMER
// ============================================================

if ($userType === 'customer') {

    $userId = (int) $user['id'];

    $updateLastLogin = $conn->prepare("
        UPDATE customers
        SET last_login_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");

    if ($updateLastLogin) {

        $updateLastLogin->bind_param(
            'i',
            $userId
        );

        $updateLastLogin->execute();

        $updateLastLogin->close();
    }
}


// ============================================================
// UPDATE LAST LOGIN FOR ADMIN / STAFF
// ============================================================
//
// Only execute if admin_users table has last_login_at.
// If your table does not contain this column, this block
// should remain disabled.
//
// ============================================================


// ============================================================
// GENERATE ACCESS TOKEN
// ============================================================

$accessToken = generateJWT(
    (int) $user['id'],
    $role,
    'access'
);


// ============================================================
// GENERATE REFRESH TOKEN
// ============================================================

$refreshToken = generateJWT(
    (int) $user['id'],
    $role,
    'refresh'
);


// ============================================================
// LOGIN SUCCESS MESSAGE
// ============================================================

if ($role === 'admin') {

    $loginMessage = 'Admin login successful.';

} elseif ($role === 'staff') {

    $loginMessage = 'Staff login successful.';

} else {

    $loginMessage = 'Customer login successful.';
}


// ============================================================
// LOGIN SUCCESS RESPONSE
// ============================================================

http_response_code(200);

echo json_encode([
    'status' => true,

    'message' => $loginMessage,

    'data' => [
        'user_id' => (int) $user['id'],

        'name' => $user['name'],

        'email' => $user['email'],

        'phone' => $user['phone'],

        'role' => $role,

        'access_token' => $accessToken,

        'refresh_token' => $refreshToken,

        'token_type' => 'Bearer',

        'expires_in' => isset(
            $_ENV['JWT_ACCESS_TOKEN_EXPIRY']
        )
            ? (int) $_ENV['JWT_ACCESS_TOKEN_EXPIRY']
            : 3600
    ]
]);

exit;