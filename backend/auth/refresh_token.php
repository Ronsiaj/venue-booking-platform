<?php

require_once __DIR__ . '/../config/jwt.php';

header('Content-Type: application/json; charset=UTF-8');

/*
|--------------------------------------------------------------------------
| Allow Only POST
|--------------------------------------------------------------------------
*/

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
| Read JSON Request
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
| Get Refresh Token
|--------------------------------------------------------------------------
*/

$refreshToken = isset($input['refresh_token'])
    ? trim($input['refresh_token'])
    : '';

if ($refreshToken === '') {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Refresh token is required.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Verify Refresh Token
|--------------------------------------------------------------------------
*/

$payload = verifyJWT($refreshToken);

if (
    !is_array($payload) ||
    !isset($payload['status']) ||
    $payload['status'] !== true
) {

    http_response_code(401);

    echo json_encode([
        'status' => false,
        'message' =>
            $payload['message']
            ?? 'Invalid or expired refresh token.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Get JWT Data
|--------------------------------------------------------------------------
*/

$tokenData = $payload['data'] ?? null;

if (!is_array($tokenData)) {

    http_response_code(401);

    echo json_encode([
        'status' => false,
        'message' => 'Invalid refresh token data.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Check Token Type
|--------------------------------------------------------------------------
*/

if (
    !isset($tokenData['type']) ||
    $tokenData['type'] !== 'refresh'
) {

    http_response_code(401);

    echo json_encode([
        'status' => false,
        'message' => 'Refresh token is required.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Validate User ID
|--------------------------------------------------------------------------
*/

if (
    !isset($tokenData['sub']) ||
    !is_numeric($tokenData['sub']) ||
    (int) $tokenData['sub'] <= 0
) {

    http_response_code(401);

    echo json_encode([
        'status' => false,
        'message' => 'Invalid user information.'
    ]);

    exit;
}

$userId = (int) $tokenData['sub'];

/*
|--------------------------------------------------------------------------
| Validate Role
|--------------------------------------------------------------------------
*/

$allowedRoles = [
    'admin',
    'staff',
    'customer'
];

if (
    !isset($tokenData['role']) ||
    !in_array(
        $tokenData['role'],
        $allowedRoles,
        true
    )
) {

    http_response_code(401);

    echo json_encode([
        'status' => false,
        'message' => 'Invalid user role.'
    ]);

    exit;
}

$role = $tokenData['role'];

/*
|--------------------------------------------------------------------------
| Generate New Access Token
|--------------------------------------------------------------------------
*/

$newAccessToken = generateJWT(
    $userId,
    $role,
    'access'
);

/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

http_response_code(200);

echo json_encode([
    'status' => true,
    'message' => 'Access token refreshed successfully.',
    'data' => [
        'access_token' => $newAccessToken,
        'token_type' => 'Bearer'
    ]
]);

exit;