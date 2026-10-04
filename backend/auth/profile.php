<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/middleware.php';

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);

    echo json_encode([
        'status' => false,
        'message' => 'Only GET method is allowed.'
    ]);

    exit;
}


$user = requireAuth();

$userId = isset($user['sub'])
    ? (int) $user['sub']
    : 0;

$role = isset($user['role'])
    ? $user['role']
    : '';

/*
|--------------------------------------------------------------------------
| Validate User ID
|--------------------------------------------------------------------------
*/

if ($userId <= 0) {
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

if (!in_array($role, $allowedRoles, true)) {
    http_response_code(403);

    echo json_encode([
        'status' => false,
        'message' => 'Invalid user role.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Customer Profile
|--------------------------------------------------------------------------
*/

if ($role === 'customer') {

    $customerQuery = $conn->prepare("
        SELECT
            id,
            name,
            email,
            phone,
            status,
            created_at,
            updated_at
        FROM customers
        WHERE id = ?
        LIMIT 1
    ");

    if (!$customerQuery) {
        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Unable to process profile request.'
        ]);

        exit;
    }

    $customerQuery->bind_param(
        'i',
        $userId
    );

    if (!$customerQuery->execute()) {
        $customerQuery->close();

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Unable to process profile request.'
        ]);

        exit;
    }

    $customerResult = $customerQuery->get_result();

    if ($customerResult->num_rows === 0) {
        $customerQuery->close();

        http_response_code(404);

        echo json_encode([
            'status' => false,
            'message' => 'Customer account not found.'
        ]);

        exit;
    }

    $profile = $customerResult->fetch_assoc();

    $customerQuery->close();

    /*
    |--------------------------------------------------------------------------
    | Check Customer Status
    |--------------------------------------------------------------------------
    */

    if (
        !isset($profile['status']) ||
        $profile['status'] !== 'active'
    ) {
        http_response_code(403);

        echo json_encode([
            'status' => false,
            'message' => 'Your account is inactive.'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Customer Response
    |--------------------------------------------------------------------------
    */

    http_response_code(200);

    echo json_encode([
        'status' => true,
        'message' => 'Customer profile retrieved successfully.',
        'data' => [
            'user_id' => (int) $profile['id'],
            'name' => $profile['name'],
            'email' => $profile['email'],
            'phone' => $profile['phone'],
            'role' => 'customer',
            'status' => $profile['status'],
            'created_at' => $profile['created_at'],
            'updated_at' => $profile['updated_at']
        ]
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Admin / Staff Profile
|--------------------------------------------------------------------------
*/

if (
    $role === 'admin' ||
    $role === 'staff'
) {

    $staffQuery = $conn->prepare("
        SELECT
            id,
            name,
            email,
            phone,
            role,
            status,
            created_at,
            updated_at
        FROM admin_users
        WHERE id = ?
        LIMIT 1
    ");

    if (!$staffQuery) {
        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Unable to process profile request.'
        ]);

        exit;
    }

    $staffQuery->bind_param(
        'i',
        $userId
    );

    if (!$staffQuery->execute()) {
        $staffQuery->close();

        http_response_code(500);

        echo json_encode([
            'status' => false,
            'message' => 'Unable to process profile request.'
        ]);

        exit;
    }

    $staffResult = $staffQuery->get_result();

    if ($staffResult->num_rows === 0) {
        $staffQuery->close();

        http_response_code(404);

        echo json_encode([
            'status' => false,
            'message' => 'User account not found.'
        ]);

        exit;
    }

    $profile = $staffResult->fetch_assoc();

    $staffQuery->close();

    /*
    |--------------------------------------------------------------------------
    | Verify Database Role Matches JWT Role
    |--------------------------------------------------------------------------
    |
    | Prevents a stale/incorrect token role from exposing
    | the wrong profile type.
    |
    */

    if (
        !isset($profile['role']) ||
        $profile['role'] !== $role
    ) {
        http_response_code(401);

        echo json_encode([
            'status' => false,
            'message' => 'User role mismatch.'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Check Account Status
    |--------------------------------------------------------------------------
    */

    if (
        !isset($profile['status']) ||
        $profile['status'] !== 'active'
    ) {
        http_response_code(403);

        echo json_encode([
            'status' => false,
            'message' => 'Your account is inactive.'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Profile
    |--------------------------------------------------------------------------
    */

    if ($role === 'admin') {

        http_response_code(200);

        echo json_encode([
            'status' => true,
            'message' => 'Admin profile retrieved successfully.',
            'data' => [
                'user_id' => (int) $profile['id'],
                'name' => $profile['name'],
                'email' => $profile['email'],
                'phone' => $profile['phone'],
                'role' => 'admin',
                'status' => $profile['status'],
                'created_at' => $profile['created_at'],
                'updated_at' => $profile['updated_at']
            ]
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Staff Profile
    |--------------------------------------------------------------------------
    */

    if ($role === 'staff') {

        http_response_code(200);

        echo json_encode([
            'status' => true,
            'message' => 'Staff profile retrieved successfully.',
            'data' => [
                'user_id' => (int) $profile['id'],
                'name' => $profile['name'],
                'email' => $profile['email'],
                'phone' => $profile['phone'],
                'role' => 'staff',
                'status' => $profile['status'],
                'created_at' => $profile['created_at'],
                'updated_at' => $profile['updated_at']
            ]
        ]);

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Fallback
|--------------------------------------------------------------------------
*/

http_response_code(403);

echo json_encode([
    'status' => false,
    'message' => 'Unable to identify user profile.'
]);

exit;