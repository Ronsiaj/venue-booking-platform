<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/jwt.php';

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'status' => false,
        'message' => 'Only POST method is allowed.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Read JSON Input
|--------------------------------------------------------------------------
*/

$rawInput = file_get_contents('php://input');

$input = json_decode($rawInput, true);

if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' => 'Invalid JSON request.'
    ]);

    exit;
}

if (!is_array($input)) {
    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' => 'Request body must be a valid JSON object.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Get Input
|--------------------------------------------------------------------------
*/

$name = isset($input['name'])
    ? trim($input['name'])
    : '';

$email = isset($input['email'])
    ? strtolower(trim($input['email']))
    : '';

$phone = isset($input['phone'])
    ? trim($input['phone'])
    : '';

$password = isset($input['password'])
    ? $input['password']
    : '';

$confirmPassword = isset($input['confirm_password'])
    ? $input['confirm_password']
    : '';

/*
|--------------------------------------------------------------------------
| Environment Validation
|--------------------------------------------------------------------------
*/

$nameMinLength = isset($_ENV['NAME_MIN_LENGTH'])
    ? (int) $_ENV['NAME_MIN_LENGTH']
    : 2;

$nameMaxLength = isset($_ENV['NAME_MAX_LENGTH'])
    ? (int) $_ENV['NAME_MAX_LENGTH']
    : 100;

$phoneMinLength = isset($_ENV['PHONE_MIN_LENGTH'])
    ? (int) $_ENV['PHONE_MIN_LENGTH']
    : 10;

$phoneMaxLength = isset($_ENV['PHONE_MAX_LENGTH'])
    ? (int) $_ENV['PHONE_MAX_LENGTH']
    : 15;

$emailMaxLength = isset($_ENV['EMAIL_MAX_LENGTH'])
    ? (int) $_ENV['EMAIL_MAX_LENGTH']
    : 150;

$passwordMinLength = isset($_ENV['PASSWORD_MIN_LENGTH'])
    ? (int) $_ENV['PASSWORD_MIN_LENGTH']
    : 8;

$passwordMaxLength = isset($_ENV['PASSWORD_MAX_LENGTH'])
    ? (int) $_ENV['PASSWORD_MAX_LENGTH']
    : 72;

/*
|--------------------------------------------------------------------------
| Name Validation
|--------------------------------------------------------------------------
*/

if ($name === '') {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Name is required.'
    ]);

    exit;
}

if (
    strlen($name) < $nameMinLength ||
    strlen($name) > $nameMaxLength
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            "Name must be between {$nameMinLength} and {$nameMaxLength} characters."
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Name Format
|--------------------------------------------------------------------------
|
| Allows:
| - English letters
| - Single spaces between words
|
| Rejects:
| - Numbers
| - Special characters
| - Multiple spaces
| - Leading spaces
| - Trailing spaces
|
*/

if (!preg_match('/^[A-Za-z]+(?: [A-Za-z]+)*$/', $name)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            'Name can contain only letters with single spaces between words.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Email Validation
|--------------------------------------------------------------------------
*/

if ($email === '') {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Email is required.'
    ]);

    exit;
}

if (strlen($email) > $emailMaxLength) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Email address is too long.'
    ]);

    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Please enter a valid email address.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Phone Validation
|--------------------------------------------------------------------------
|
| Indian mobile number:
| - Exactly 10 digits
| - Starts with 6, 7, 8 or 9
|
*/

if ($phone === '') {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Phone number is required.'
    ]);

    exit;
}

if (!preg_match('/^[0-9]+$/', $phone)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Phone number must contain only digits.'
    ]);

    exit;
}

if (
    strlen($phone) < $phoneMinLength ||
    strlen($phone) > $phoneMaxLength
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' =>
            "Phone number must be between {$phoneMinLength} and {$phoneMaxLength} digits."
    ]);

    exit;
}

if (!preg_match('/^[6-9][0-9]{9}$/', $phone)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Please enter a valid 10-digit Indian mobile number.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Reject Repeated Digits
|--------------------------------------------------------------------------
|
| Examples:
| 6666666666
| 7777777777
| 8888888888
| 9999999999
|
*/

if (preg_match('/^(\d)\1{9}$/', $phone)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Please enter a valid mobile number.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Password Validation
|--------------------------------------------------------------------------
*/

if ($password === '') {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Password is required.'
    ]);

    exit;
}

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

/*
|--------------------------------------------------------------------------
| Confirm Password
|--------------------------------------------------------------------------
*/

if ($confirmPassword === '') {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Confirm password is required.'
    ]);

    exit;
}

if ($password !== $confirmPassword) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Password and confirm password do not match.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Password Strength
|--------------------------------------------------------------------------
|
| Requires:
| - At least one uppercase letter
| - At least one lowercase letter
| - At least one number
|
*/

if (!preg_match('/[A-Z]/', $password)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Password must contain at least one uppercase letter.'
    ]);

    exit;
}

if (!preg_match('/[a-z]/', $password)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Password must contain at least one lowercase letter.'
    ]);

    exit;
}

if (!preg_match('/[0-9]/', $password)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Password must contain at least one number.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Check Duplicate Email - Customers
|--------------------------------------------------------------------------
*/

$checkEmail = $conn->prepare("
    SELECT id
    FROM customers
    WHERE LOWER(email) = ?
    LIMIT 1
");

if (!$checkEmail) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to process registration request.'
    ]);

    exit;
}

$checkEmail->bind_param(
    's',
    $email
);

if (!$checkEmail->execute()) {
    $checkEmail->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to process registration request.'
    ]);

    exit;
}

$emailResult = $checkEmail->get_result();

if ($emailResult->num_rows > 0) {
    $checkEmail->close();

    http_response_code(409);

    echo json_encode([
        'status' => false,
        'message' => 'An account with this email already exists.'
    ]);

    exit;
}

$checkEmail->close();

/*
|--------------------------------------------------------------------------
| Check Duplicate Email - Admin / Staff
|--------------------------------------------------------------------------
*/

$checkAdminEmail = $conn->prepare("
    SELECT id
    FROM admin_users
    WHERE LOWER(email) = ?
    LIMIT 1
");

if (!$checkAdminEmail) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to process registration request.'
    ]);

    exit;
}

$checkAdminEmail->bind_param(
    's',
    $email
);

if (!$checkAdminEmail->execute()) {
    $checkAdminEmail->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to process registration request.'
    ]);

    exit;
}

$adminEmailResult = $checkAdminEmail->get_result();

if ($adminEmailResult->num_rows > 0) {
    $checkAdminEmail->close();

    http_response_code(409);

    echo json_encode([
        'status' => false,
        'message' => 'This email is already associated with another account.'
    ]);

    exit;
}

$checkAdminEmail->close();

/*
|--------------------------------------------------------------------------
| Check Duplicate Phone - Customers
|--------------------------------------------------------------------------
*/

$checkPhone = $conn->prepare("
    SELECT id
    FROM customers
    WHERE phone = ?
    LIMIT 1
");

if (!$checkPhone) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to process registration request.'
    ]);

    exit;
}

$checkPhone->bind_param(
    's',
    $phone
);

if (!$checkPhone->execute()) {
    $checkPhone->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to process registration request.'
    ]);

    exit;
}

$phoneResult = $checkPhone->get_result();

if ($phoneResult->num_rows > 0) {
    $checkPhone->close();

    http_response_code(409);

    echo json_encode([
        'status' => false,
        'message' => 'An account with this phone number already exists.'
    ]);

    exit;
}

$checkPhone->close();

/*
|--------------------------------------------------------------------------
| Check Duplicate Phone - Admin / Staff
|--------------------------------------------------------------------------
*/

$checkAdminPhone = $conn->prepare("
    SELECT id
    FROM admin_users
    WHERE phone = ?
    LIMIT 1
");

if (!$checkAdminPhone) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to process registration request.'
    ]);

    exit;
}

$checkAdminPhone->bind_param(
    's',
    $phone
);

if (!$checkAdminPhone->execute()) {
    $checkAdminPhone->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to process registration request.'
    ]);

    exit;
}

$adminPhoneResult = $checkAdminPhone->get_result();

if ($adminPhoneResult->num_rows > 0) {
    $checkAdminPhone->close();

    http_response_code(409);

    echo json_encode([
        'status' => false,
        'message' => 'This phone number is already associated with another account.'
    ]);

    exit;
}

$checkAdminPhone->close();

/*
|--------------------------------------------------------------------------
| Hash Password
|--------------------------------------------------------------------------
*/

$passwordHash = password_hash(
    $password,
    PASSWORD_DEFAULT
);

if ($passwordHash === false) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to create customer account.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Insert Customer
|--------------------------------------------------------------------------
|
| customers table:
| name
| email
| phone
| password_hash
| status
|
| Customer role is NOT stored in customers table.
| Customer role is assigned in JWT as "customer".
|
*/

$status = 'active';

$insertCustomer = $conn->prepare("
    INSERT INTO customers
    (
        name,
        email,
        phone,
        password_hash,
        status
    )
    VALUES
    (
        ?,
        ?,
        ?,
        ?,
        ?
    )
");

if (!$insertCustomer) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to create customer account.'
    ]);

    exit;
}

$insertCustomer->bind_param(
    'sssss',
    $name,
    $email,
    $phone,
    $passwordHash,
    $status
);

if (!$insertCustomer->execute()) {

    /*
    |--------------------------------------------------------------------------
    | Handle Duplicate Database Constraint
    |--------------------------------------------------------------------------
    */

    if ($insertCustomer->errno === 1062) {
        $insertCustomer->close();

        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' => 'Email or phone number already exists.'
        ]);

        exit;
    }

    $insertCustomer->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to create customer account.'
    ]);

    exit;
}

$customerId = $conn->insert_id;

$insertCustomer->close();

/*
|--------------------------------------------------------------------------
| Generate Access Token
|--------------------------------------------------------------------------
*/

$accessToken = generateJWT(
    (int) $customerId,
    'customer',
    'access'
);

/*
|--------------------------------------------------------------------------
| Generate Refresh Token
|--------------------------------------------------------------------------
*/

$refreshToken = generateJWT(
    (int) $customerId,
    'customer',
    'refresh'
);

/*
|--------------------------------------------------------------------------
| Success Response
|--------------------------------------------------------------------------
*/

http_response_code(201);

echo json_encode([
    'status' => true,
    'message' => 'Customer registration successful.',
    'data' => [
        'user_id' => (int) $customerId,
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'role' => 'customer',
        'access_token' => $accessToken,
        'refresh_token' => $refreshToken,
        'token_type' => 'Bearer',
        'expires_in' => isset($_ENV['JWT_ACCESS_TOKEN_EXPIRY'])
            ? (int) $_ENV['JWT_ACCESS_TOKEN_EXPIRY']
            : 3600
    ]
]);

exit;