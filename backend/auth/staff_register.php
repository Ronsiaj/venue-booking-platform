<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/middleware.php';

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
| Admin Authentication
|--------------------------------------------------------------------------
|
| Only an authenticated admin can create a staff account.
|
*/

$admin = requireAdmin();

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
| - Uppercase
| - Lowercase
| - Number
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
| Check Duplicate Email - Admin Users
|--------------------------------------------------------------------------
*/

$checkEmail = $conn->prepare("
    SELECT id
    FROM admin_users
    WHERE LOWER(email) = ?
    LIMIT 1
");

if (!$checkEmail) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to process staff registration request.'
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
        'message' => 'Unable to process staff registration request.'
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
| Check Duplicate Email - Customers
|--------------------------------------------------------------------------
*/

$checkCustomerEmail = $conn->prepare("
    SELECT id
    FROM customers
    WHERE LOWER(email) = ?
    LIMIT 1
");

if (!$checkCustomerEmail) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to process staff registration request.'
    ]);

    exit;
}

$checkCustomerEmail->bind_param(
    's',
    $email
);

if (!$checkCustomerEmail->execute()) {
    $checkCustomerEmail->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to process staff registration request.'
    ]);

    exit;
}

$customerEmailResult = $checkCustomerEmail->get_result();

if ($customerEmailResult->num_rows > 0) {
    $checkCustomerEmail->close();

    http_response_code(409);

    echo json_encode([
        'status' => false,
        'message' => 'This email is already associated with a customer account.'
    ]);

    exit;
}

$checkCustomerEmail->close();

/*
|--------------------------------------------------------------------------
| Check Duplicate Phone - Admin Users
|--------------------------------------------------------------------------
*/

$checkPhone = $conn->prepare("
    SELECT id
    FROM admin_users
    WHERE phone = ?
    LIMIT 1
");

if (!$checkPhone) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to process staff registration request.'
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
        'message' => 'Unable to process staff registration request.'
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
| Check Duplicate Phone - Customers
|--------------------------------------------------------------------------
*/

$checkCustomerPhone = $conn->prepare("
    SELECT id
    FROM customers
    WHERE phone = ?
    LIMIT 1
");

if (!$checkCustomerPhone) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to process staff registration request.'
    ]);

    exit;
}

$checkCustomerPhone->bind_param(
    's',
    $phone
);

if (!$checkCustomerPhone->execute()) {
    $checkCustomerPhone->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to process staff registration request.'
    ]);

    exit;
}

$customerPhoneResult = $checkCustomerPhone->get_result();

if ($customerPhoneResult->num_rows > 0) {
    $checkCustomerPhone->close();

    http_response_code(409);

    echo json_encode([
        'status' => false,
        'message' => 'This phone number is already associated with a customer account.'
    ]);

    exit;
}

$checkCustomerPhone->close();

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
        'message' => 'Unable to create staff account.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Staff Account Values
|--------------------------------------------------------------------------
*/

$role = 'staff';
$status = 'active';

/*
|--------------------------------------------------------------------------
| Insert Staff
|--------------------------------------------------------------------------
|
| admin_users table:
| name
| email
| phone
| password_hash
| role
| status
|
*/

$insertStaff = $conn->prepare("
    INSERT INTO admin_users
    (
        name,
        email,
        phone,
        password_hash,
        role,
        status
    )
    VALUES
    (
        ?,
        ?,
        ?,
        ?,
        ?,
        ?
    )
");

if (!$insertStaff) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to create staff account.'
    ]);

    exit;
}

$insertStaff->bind_param(
    'ssssss',
    $name,
    $email,
    $phone,
    $passwordHash,
    $role,
    $status
);

if (!$insertStaff->execute()) {

    if ($insertStaff->errno === 1062) {
        $insertStaff->close();

        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' => 'Email or phone number already exists.'
        ]);

        exit;
    }

    $insertStaff->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Unable to create staff account.'
    ]);

    exit;
}

$staffId = $conn->insert_id;

$insertStaff->close();

/*
|--------------------------------------------------------------------------
| Success Response
|--------------------------------------------------------------------------
|
| Staff account is created by admin.
| Do not automatically login the newly created staff.
|
*/

http_response_code(201);

echo json_encode([
    'status' => true,
    'message' => 'Staff account created successfully.',
    'data' => [
        'staff_id' => (int) $staffId,
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'role' => 'staff',
        'status' => 'active'
    ]
]);

exit;