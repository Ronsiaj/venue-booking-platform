<?php

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
| Get Authorization Header
|--------------------------------------------------------------------------
*/

$accessToken = getBearerToken();

if ($accessToken === null || $accessToken === '') {
    http_response_code(401);

    echo json_encode([
        'status' => false,
        'message' => 'Access token is required.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Verify Access Token
|--------------------------------------------------------------------------
*/

$accessPayload = verifyJWT($accessToken);

if (
    !is_array($accessPayload) ||
    !isset($accessPayload['status']) ||
    $accessPayload['status'] !== true
) {
    http_response_code(401);

    echo json_encode([
        'status' => false,
        'message' =>
            $accessPayload['message']
            ?? 'Invalid or expired access token.'
    ]);

    exit;
}

$accessData = $accessPayload['data'] ?? null;

if (!is_array($accessData)) {
    http_response_code(401);

    echo json_encode([
        'status' => false,
        'message' => 'Invalid access token data.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Check Token Type
|--------------------------------------------------------------------------
*/

if (
    !isset($accessData['type']) ||
    $accessData['type'] !== 'access'
) {
    http_response_code(401);

    echo json_encode([
        'status' => false,
        'message' => 'Access token is required.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Validate User ID
|--------------------------------------------------------------------------
*/

if (
    !isset($accessData['sub']) ||
    !is_numeric($accessData['sub']) ||
    (int) $accessData['sub'] <= 0
) {
    http_response_code(401);

    echo json_encode([
        'status' => false,
        'message' => 'Invalid user information.'
    ]);

    exit;
}

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
    !isset($accessData['role']) ||
    !in_array(
        $accessData['role'],
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



http_response_code(200);

echo json_encode([
    'status' => true,
    'message' => 'Logout successful.',
    'data' => [
        'user_id' => (int) $accessData['sub'],
        'role' => $accessData['role']
    ]
]);

exit;