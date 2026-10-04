<?php


require_once __DIR__ . '/jwt.php';


// ============================================================
// JSON RESPONSE HEADER
// ============================================================

header(
    'Content-Type: application/json; charset=UTF-8'
);


// ============================================================
// AUTHENTICATION REQUIRED
// ============================================================

function requireAuth()
{
    // --------------------------------------------------------
    // Get authenticated user from JWT
    // --------------------------------------------------------

    $auth = getCurrentUserFromJWT();


    // --------------------------------------------------------
    // Authentication failed
    // --------------------------------------------------------

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


    // --------------------------------------------------------
    // Access token only
    // --------------------------------------------------------

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


    // --------------------------------------------------------
    // User ID validation
    // --------------------------------------------------------

    if (
        !isset($auth['data']['sub']) ||
        !is_numeric($auth['data']['sub']) ||
        (int) $auth['data']['sub'] <= 0
    ) {

        http_response_code(401);

        echo json_encode([
            'status' => false,
            'message' => 'Invalid user information.'
        ]);

        exit;
    }


    // --------------------------------------------------------
    // Role validation
    // --------------------------------------------------------

    if (
        !isset($auth['data']['role']) ||
        !in_array(
            $auth['data']['role'],
            [
                'admin',
                'staff',
                'customer'
            ],
            true
        )
    ) {

        http_response_code(403);

        echo json_encode([
            'status' => false,
            'message' => 'Invalid user role.'
        ]);

        exit;
    }


    // --------------------------------------------------------
    // Return authenticated user
    // --------------------------------------------------------

    return $auth['data'];
}


// ============================================================
// ROLE CHECK
// ============================================================

function requireRole($allowedRoles)
{
    // --------------------------------------------------------
    // Get authenticated user
    // --------------------------------------------------------

    $user = requireAuth();


    // --------------------------------------------------------
    // Convert single role to array
    // --------------------------------------------------------

    if (
        !is_array($allowedRoles)
    ) {

        $allowedRoles = [
            $allowedRoles
        ];
    }


    // --------------------------------------------------------
    // Allowed roles validation
    // --------------------------------------------------------

    $validRoles = [
        'admin',
        'staff',
        'customer'
    ];


    foreach (
        $allowedRoles as $role
    ) {

        if (
            !in_array(
                $role,
                $validRoles,
                true
            )
        ) {

            http_response_code(500);

            echo json_encode([
                'status' => false,
                'message' => 'Invalid role configuration.'
            ]);

            exit;
        }
    }


    // --------------------------------------------------------
    // Check user's role
    // --------------------------------------------------------

    if (
        !isset($user['role']) ||
        !in_array(
            $user['role'],
            $allowedRoles,
            true
        )
    ) {

        http_response_code(403);

        echo json_encode([
            'status' => false,
            'message' =>
                'You do not have permission to access this resource.'
        ]);

        exit;
    }


    // --------------------------------------------------------
    // Return authenticated user
    // --------------------------------------------------------

    return $user;
}


// ============================================================
// ADMIN ONLY
// ============================================================

function requireAdmin()
{
    return requireRole([
        'admin'
    ]);
}


// ============================================================
// STAFF ONLY
// ============================================================

function requireStaff()
{
    return requireRole([
        'staff'
    ]);
}


// ============================================================
// CUSTOMER ONLY
// ============================================================

function requireCustomer()
{
    return requireRole([
        'customer'
    ]);
}


// ============================================================
// ADMIN OR STAFF
// ============================================================

function requireAdminOrStaff()
{
    return requireRole([
        'admin',
        'staff'
    ]);
}


// ============================================================
// ADMIN OR CUSTOMER
// ============================================================

function requireAdminOrCustomer()
{
    return requireRole([
        'admin',
        'customer'
    ]);
}


// ============================================================
// STAFF OR CUSTOMER
// ============================================================

function requireStaffOrCustomer()
{
    return requireRole([
        'staff',
        'customer'
    ]);
}


// ============================================================
// ANY AUTHENTICATED USER
// ============================================================

function requireAnyUser()
{
    return requireAuth();
}