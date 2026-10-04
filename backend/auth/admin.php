<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/middleware.php';

header('Content-Type: application/json; charset=UTF-8');

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

$input = json_decode(
    file_get_contents('php://input'),
    true
);

if (!is_array($input)) {
    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' => 'Invalid JSON request.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Check Existing Admin
|--------------------------------------------------------------------------
*/

$adminCount = 0;

$countQuery = "
    SELECT COUNT(*) AS total
    FROM admin_users
    WHERE role = 'admin'
";

$countResult = $conn->query($countQuery);

if (!$countResult) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to check admin account.'
    ]);

    exit;
}

$countRow = $countResult->fetch_assoc();

$adminCount = (int) $countRow['total'];

/*
|--------------------------------------------------------------------------
| FIRST ADMIN CREATE
|--------------------------------------------------------------------------
*/

if ($adminCount === 0) {

    /*
    |--------------------------------------------------------------------------
    | Required Fields
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
        strlen($name) < 2 ||
        strlen($name) > 100
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Name must be between 2 and 100 characters.'
        ]);

        exit;
    }

    if (!preg_match('/^[A-Za-z]+(?: [A-Za-z]+)*$/', $name)) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Name can contain only letters and single spaces.'
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

    if (
        strlen($email) > 150 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
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
    */

    if ($phone === '') {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Phone number is required.'
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

    if (preg_match('/^(\d)\1{9}$/', $phone)) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Please enter a valid phone number.'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Password Validation
    |--------------------------------------------------------------------------
    */

    $minPasswordLength = isset($_ENV['PASSWORD_MIN_LENGTH'])
        ? (int) $_ENV['PASSWORD_MIN_LENGTH']
        : 8;

    $maxPasswordLength = isset($_ENV['PASSWORD_MAX_LENGTH'])
        ? (int) $_ENV['PASSWORD_MAX_LENGTH']
        : 72;

    if ($password === '') {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' => 'Password is required.'
        ]);

        exit;
    }

    if (
        strlen($password) < $minPasswordLength ||
        strlen($password) > $maxPasswordLength
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                "Password must be between {$minPasswordLength} and {$maxPasswordLength} characters."
        ]);

        exit;
    }

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
    | Check Duplicate Email
    |--------------------------------------------------------------------------
    */

    $emailCheck = $conn->prepare("
        SELECT id
        FROM admin_users
        WHERE LOWER(email) = ?
        LIMIT 1
    ");

    if (!$emailCheck) {
        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Failed to validate email.'
        ]);

        exit;
    }

    $emailCheck->bind_param(
        's',
        $email
    );

    $emailCheck->execute();

    $emailResult = $emailCheck->get_result();

    if ($emailResult->num_rows > 0) {

        $emailCheck->close();

        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' => 'Email address already exists.'
        ]);

        exit;
    }

    $emailCheck->close();

    /*
    |--------------------------------------------------------------------------
    | Check Duplicate Phone
    |--------------------------------------------------------------------------
    */

    $phoneCheck = $conn->prepare("
        SELECT id
        FROM admin_users
        WHERE phone = ?
        LIMIT 1
    ");

    if (!$phoneCheck) {
        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Failed to validate phone number.'
        ]);

        exit;
    }

    $phoneCheck->bind_param(
        's',
        $phone
    );

    $phoneCheck->execute();

    $phoneResult = $phoneCheck->get_result();

    if ($phoneResult->num_rows > 0) {

        $phoneCheck->close();

        http_response_code(409);

        echo json_encode([
            'status' => false,
            'message' => 'Phone number already exists.'
        ]);

        exit;
    }

    $phoneCheck->close();

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
            'message' => 'Failed to secure password.'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Create First Admin
    |--------------------------------------------------------------------------
    */

    $insertQuery = $conn->prepare("
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
            'admin',
            'active'
        )
    ");

    if (!$insertQuery) {
        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Failed to create admin account.'
        ]);

        exit;
    }

    $insertQuery->bind_param(
        'ssss',
        $name,
        $email,
        $phone,
        $passwordHash
    );

    if (!$insertQuery->execute()) {

        $insertQuery->close();

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Failed to create admin account.'
        ]);

        exit;
    }

    $adminId = $conn->insert_id;

    $insertQuery->close();

    /*
    |--------------------------------------------------------------------------
    | Generate JWT
    |--------------------------------------------------------------------------
    */

    $accessToken = generateJWT(
        $adminId,
        'admin',
        'access'
    );

    $refreshToken = generateJWT(
        $adminId,
        'admin',
        'refresh'
    );

    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    http_response_code(201);

    echo json_encode([
        'status' => true,
        'message' => 'First admin account created successfully.',
        'data' => [
            'admin_id' => $adminId,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'role' => 'admin',
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken
        ]
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| ADMIN UPDATE
|--------------------------------------------------------------------------
|
| If admin already exists, JWT authentication is required.
|--------------------------------------------------------------------------
*/

$auth = getCurrentUserFromJWT();

if (
    !isset($auth['status']) ||
    $auth['status'] !== true
) {
    http_response_code(401);

    echo json_encode([
        'status' => false,
        'message' =>
            $auth['message']
            ?? 'Authentication required.'
    ]);

    exit;
}

if (
    !isset($auth['data']['type']) ||
    $auth['data']['type'] !== 'access'
) {
    http_response_code(401);

    echo json_encode([
        'status' => false,
        'message' => 'Access token is required.'
    ]);

    exit;
}

if (
    !isset($auth['data']['role']) ||
    $auth['data']['role'] !== 'admin'
) {
    http_response_code(403);

    echo json_encode([
        'status' => false,
        'message' => 'Only admin can update admin details.'
    ]);

    exit;
}

$adminId = (int) $auth['data']['sub'];

if ($adminId <= 0) {
    http_response_code(401);

    echo json_encode([
        'status' => false,
        'message' => 'Invalid admin information.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Check Admin Exists
|--------------------------------------------------------------------------
*/

$adminCheck = $conn->prepare("
    SELECT id, name, email, phone
    FROM admin_users
    WHERE id = ?
    AND role = 'admin'
    LIMIT 1
");

if (!$adminCheck) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to validate admin account.'
    ]);

    exit;
}

$adminCheck->bind_param(
    'i',
    $adminId
);

$adminCheck->execute();

$adminResult = $adminCheck->get_result();

if ($adminResult->num_rows === 0) {

    $adminCheck->close();

    http_response_code(404);

    echo json_encode([
        'status' => false,
        'message' => 'Admin account not found.'
    ]);

    exit;
}

$currentAdmin = $adminResult->fetch_assoc();

$adminCheck->close();

/*
|--------------------------------------------------------------------------
| Update Fields
|--------------------------------------------------------------------------
*/

$name = array_key_exists('name', $input)
    ? trim($input['name'])
    : $currentAdmin['name'];

$email = array_key_exists('email', $input)
    ? strtolower(trim($input['email']))
    : $currentAdmin['email'];

$phone = array_key_exists('phone', $input)
    ? trim($input['phone'])
    : $currentAdmin['phone'];

$password = array_key_exists('password', $input)
    ? $input['password']
    : '';

$confirmPassword = array_key_exists('confirm_password', $input)
    ? $input['confirm_password']
    : '';

/*
|--------------------------------------------------------------------------
| Name Validation
|--------------------------------------------------------------------------
*/

if ($name === '') {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Name cannot be empty.'
    ]);

    exit;
}

if (
    strlen($name) < 2 ||
    strlen($name) > 100
) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Name must be between 2 and 100 characters.'
    ]);

    exit;
}

if (!preg_match('/^[A-Za-z]+(?: [A-Za-z]+)*$/', $name)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Name can contain only letters and single spaces.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Email Validation
|--------------------------------------------------------------------------
*/

if (
    $email === '' ||
    strlen($email) > 150 ||
    !filter_var($email, FILTER_VALIDATE_EMAIL)
) {
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
*/

if (!preg_match('/^[6-9][0-9]{9}$/', $phone)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Please enter a valid 10-digit Indian mobile number.'
    ]);

    exit;
}

if (preg_match('/^(\d)\1{9}$/', $phone)) {
    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Please enter a valid phone number.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Check Duplicate Email
|--------------------------------------------------------------------------
*/

$emailCheck = $conn->prepare("
    SELECT id
    FROM admin_users
    WHERE LOWER(email) = ?
    AND id != ?
    LIMIT 1
");

if (!$emailCheck) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to validate email.'
    ]);

    exit;
}

$emailCheck->bind_param(
    'si',
    $email,
    $adminId
);

$emailCheck->execute();

$emailResult = $emailCheck->get_result();

if ($emailResult->num_rows > 0) {

    $emailCheck->close();

    http_response_code(409);

    echo json_encode([
        'status' => false,
        'message' => 'Email address already exists.'
    ]);

    exit;
}

$emailCheck->close();

/*
|--------------------------------------------------------------------------
| Check Duplicate Phone
|--------------------------------------------------------------------------
*/

$phoneCheck = $conn->prepare("
    SELECT id
    FROM admin_users
    WHERE phone = ?
    AND id != ?
    LIMIT 1
");

if (!$phoneCheck) {
    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to validate phone number.'
    ]);

    exit;
}

$phoneCheck->bind_param(
    'si',
    $phone,
    $adminId
);

$phoneCheck->execute();

$phoneResult = $phoneCheck->get_result();

if ($phoneResult->num_rows > 0) {

    $phoneCheck->close();

    http_response_code(409);

    echo json_encode([
        'status' => false,
        'message' => 'Phone number already exists.'
    ]);

    exit;
}

$phoneCheck->close();

/*
|--------------------------------------------------------------------------
| Password Update
|--------------------------------------------------------------------------
*/

if ($password !== '') {

    $minPasswordLength = isset($_ENV['PASSWORD_MIN_LENGTH'])
        ? (int) $_ENV['PASSWORD_MIN_LENGTH']
        : 8;

    $maxPasswordLength = isset($_ENV['PASSWORD_MAX_LENGTH'])
        ? (int) $_ENV['PASSWORD_MAX_LENGTH']
        : 72;

    if (
        strlen($password) < $minPasswordLength ||
        strlen($password) > $maxPasswordLength
    ) {
        http_response_code(422);

        echo json_encode([
            'status' => false,
            'message' =>
                "Password must be between {$minPasswordLength} and {$maxPasswordLength} characters."
        ]);

        exit;
    }

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

    $passwordHash = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    if ($passwordHash === false) {
        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Failed to secure password.'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Update With Password
    |--------------------------------------------------------------------------
    */

    $updateQuery = $conn->prepare("
        UPDATE admin_users
        SET
            name = ?,
            email = ?,
            phone = ?,
            password_hash = ?
        WHERE id = ?
        AND role = 'admin'
    ");

    if (!$updateQuery) {
        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Failed to prepare admin update.'
        ]);

        exit;
    }

    $updateQuery->bind_param(
        'ssssi',
        $name,
        $email,
        $phone,
        $passwordHash,
        $adminId
    );

} else {

    /*
    |--------------------------------------------------------------------------
    | Update Without Password
    |--------------------------------------------------------------------------
    */

    $updateQuery = $conn->prepare("
        UPDATE admin_users
        SET
            name = ?,
            email = ?,
            phone = ?
        WHERE id = ?
        AND role = 'admin'
    ");

    if (!$updateQuery) {
        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Failed to prepare admin update.'
        ]);

        exit;
    }

    $updateQuery->bind_param(
        'sssi',
        $name,
        $email,
        $phone,
        $adminId
    );
}

/*
|--------------------------------------------------------------------------
| Execute Update
|--------------------------------------------------------------------------
*/

if (!$updateQuery->execute()) {

    $updateQuery->close();

    http_response_code(500);

    echo json_encode([
        'status' => false,
        'message' => 'Failed to update admin details.'
    ]);

    exit;
}

$updateQuery->close();

/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

echo json_encode([
    'status' => true,
    'message' => 'Admin details updated successfully.',
    'data' => [
        'admin_id' => $adminId,
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'role' => 'admin'
    ]
]);

exit;