<?php


require_once __DIR__ . '/../vendor/autoload.php';


// ============================================================
// LOAD ENVIRONMENT VARIABLES
// ============================================================

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);

$dotenv->safeLoad();


// ============================================================
// REQUIRED JWT ENVIRONMENT VARIABLES
// ============================================================

$requiredJwtEnv = [
    'JWT_SECRET',
    'JWT_ALGORITHM',
    'JWT_ISSUER',
    'JWT_ACCESS_TOKEN_EXPIRY',
    'JWT_REFRESH_TOKEN_EXPIRY'
];


foreach ($requiredJwtEnv as $envKey) {

    if (
        !isset($_ENV[$envKey]) ||
        $_ENV[$envKey] === ''
    ) {

        http_response_code(500);

        header(
            'Content-Type: application/json; charset=UTF-8'
        );

        echo json_encode([
            'status' => false,
            'message' => 'JWT configuration is missing.'
        ]);

        exit;
    }
}


// ============================================================
// JWT CONFIGURATION
// ============================================================

$JWT_SECRET = $_ENV['JWT_SECRET'];

$JWT_ALGORITHM = $_ENV['JWT_ALGORITHM'];

$JWT_ISSUER = $_ENV['JWT_ISSUER'];

$JWT_ACCESS_TOKEN_EXPIRY =
    (int) $_ENV['JWT_ACCESS_TOKEN_EXPIRY'];

$JWT_REFRESH_TOKEN_EXPIRY =
    (int) $_ENV['JWT_REFRESH_TOKEN_EXPIRY'];


// ============================================================
// ALLOWED JWT ALGORITHM
// ============================================================

if ($JWT_ALGORITHM !== 'HS256') {

    http_response_code(500);

    header(
        'Content-Type: application/json; charset=UTF-8'
    );

    echo json_encode([
        'status' => false,
        'message' => 'Unsupported JWT algorithm.'
    ]);

    exit;
}


// ============================================================
// JWT SECRET VALIDATION
// ============================================================

if (strlen($JWT_SECRET) < 32) {

    http_response_code(500);

    header(
        'Content-Type: application/json; charset=UTF-8'
    );

    echo json_encode([
        'status' => false,
        'message' =>
            'JWT secret must contain at least 32 characters.'
    ]);

    exit;
}


// ============================================================
// JWT EXPIRY VALIDATION
// ============================================================

if ($JWT_ACCESS_TOKEN_EXPIRY <= 0) {

    http_response_code(500);

    header(
        'Content-Type: application/json; charset=UTF-8'
    );

    echo json_encode([
        'status' => false,
        'message' =>
            'Invalid access token expiry configuration.'
    ]);

    exit;
}


if ($JWT_REFRESH_TOKEN_EXPIRY <= 0) {

    http_response_code(500);

    header(
        'Content-Type: application/json; charset=UTF-8'
    );

    echo json_encode([
        'status' => false,
        'message' =>
            'Invalid refresh token expiry configuration.'
    ]);

    exit;
}


// ============================================================
// BASE64 URL ENCODE
// ============================================================

function base64UrlEncode($data)
{
    return rtrim(
        strtr(
            base64_encode($data),
            '+/',
            '-_'
        ),
        '='
    );
}


// ============================================================
// BASE64 URL DECODE
// ============================================================

function base64UrlDecode($data)
{
    $remainder = strlen($data) % 4;

    if ($remainder > 0) {

        $data .= str_repeat(
            '=',
            4 - $remainder
        );
    }

    $decoded = base64_decode(
        strtr(
            $data,
            '-_',
            '+/'
        ),
        true
    );

    return $decoded;
}


// ============================================================
// GENERATE JWT
// ============================================================

function generateJWT(
    $userId,
    $role,
    $type = 'access',
    $additionalData = []
) {
    global
        $JWT_SECRET,
        $JWT_ALGORITHM,
        $JWT_ISSUER,
        $JWT_ACCESS_TOKEN_EXPIRY,
        $JWT_REFRESH_TOKEN_EXPIRY;


    // --------------------------------------------------------
    // Validate User ID
    // --------------------------------------------------------

    if (
        !is_numeric($userId) ||
        (int) $userId <= 0
    ) {

        return false;
    }


    // --------------------------------------------------------
    // Validate Role
    // --------------------------------------------------------

    $allowedRoles = [
        'admin',
        'staff',
        'customer'
    ];


    if (
        !in_array(
            $role,
            $allowedRoles,
            true
        )
    ) {

        return false;
    }


    // --------------------------------------------------------
    // Validate Token Type
    // --------------------------------------------------------

    if (
        !in_array(
            $type,
            [
                'access',
                'refresh'
            ],
            true
        )
    ) {

        return false;
    }


    // --------------------------------------------------------
    // Current timestamp
    // --------------------------------------------------------

    $issuedAt = time();


    // --------------------------------------------------------
    // Expiration
    // --------------------------------------------------------

    if ($type === 'refresh') {

        $expiration =
            $issuedAt +
            $JWT_REFRESH_TOKEN_EXPIRY;

    } else {

        $expiration =
            $issuedAt +
            $JWT_ACCESS_TOKEN_EXPIRY;
    }


    // --------------------------------------------------------
    // JWT Header
    // --------------------------------------------------------

    $header = [
        'typ' => 'JWT',
        'alg' => $JWT_ALGORITHM
    ];


    // --------------------------------------------------------
    // JWT Payload
    // --------------------------------------------------------

    $payload = [

        // Unique token ID
        'jti' => bin2hex(
            random_bytes(16)
        ),

        // User ID
        'sub' => (int) $userId,

        // User role
        'role' => $role,

        // Token type
        'type' => $type,

        // Issued timestamp
        'iat' => $issuedAt,

        // Expiration timestamp
        'exp' => $expiration,

        // Issuer
        'iss' => $JWT_ISSUER
    ];


    // --------------------------------------------------------
    // Additional payload
    // --------------------------------------------------------

    if (
        is_array($additionalData) &&
        !empty($additionalData)
    ) {

        /*
         * Reserved JWT claims cannot be overwritten.
         */

        $reservedClaims = [
            'jti',
            'sub',
            'role',
            'type',
            'iat',
            'exp',
            'iss'
        ];


        foreach (
            $additionalData as $key => $value
        ) {

            if (
                in_array(
                    $key,
                    $reservedClaims,
                    true
                )
            ) {
                continue;
            }

            $payload[$key] = $value;
        }
    }


    // --------------------------------------------------------
    // Encode Header
    // --------------------------------------------------------

    $encodedHeader =
        base64UrlEncode(
            json_encode(
                $header,
                JSON_UNESCAPED_SLASHES
            )
        );


    // --------------------------------------------------------
    // Encode Payload
    // --------------------------------------------------------

    $encodedPayload =
        base64UrlEncode(
            json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES
            )
        );


    // --------------------------------------------------------
    // Signature Data
    // --------------------------------------------------------

    $signatureData =
        $encodedHeader .
        '.' .
        $encodedPayload;


    // --------------------------------------------------------
    // Create HMAC SHA256 Signature
    // --------------------------------------------------------

    $signature =
        hash_hmac(
            'sha256',
            $signatureData,
            $JWT_SECRET,
            true
        );


    // --------------------------------------------------------
    // Encode Signature
    // --------------------------------------------------------

    $encodedSignature =
        base64UrlEncode(
            $signature
        );


    // --------------------------------------------------------
    // Final JWT
    // --------------------------------------------------------

    return
        $encodedHeader .
        '.' .
        $encodedPayload .
        '.' .
        $encodedSignature;
}


// ============================================================
// VERIFY JWT
// ============================================================

function verifyJWT($token)
{
    global
        $JWT_SECRET,
        $JWT_ALGORITHM,
        $JWT_ISSUER;


    try {

        // ----------------------------------------------------
        // Token validation
        // ----------------------------------------------------

        if (
            empty($token) ||
            !is_string($token)
        ) {

            return [
                'status' => false,
                'message' => 'Token is required.'
            ];
        }


        $token = trim($token);


        // ----------------------------------------------------
        // JWT must contain exactly 3 parts
        // ----------------------------------------------------

        $parts = explode(
            '.',
            $token
        );


        if (
            count($parts) !== 3
        ) {

            return [
                'status' => false,
                'message' => 'Invalid JWT format.'
            ];
        }


        [
            $encodedHeader,
            $encodedPayload,
            $encodedSignature
        ] = $parts;


        // ----------------------------------------------------
        // Decode Header
        // ----------------------------------------------------

        $headerJson =
            base64UrlDecode(
                $encodedHeader
            );


        if (
            $headerJson === false
        ) {

            return [
                'status' => false,
                'message' => 'Invalid JWT header.'
            ];
        }


        $header =
            json_decode(
                $headerJson,
                true
            );


        if (
            !is_array($header)
        ) {

            return [
                'status' => false,
                'message' => 'Invalid JWT header.'
            ];
        }


        // ----------------------------------------------------
        // JWT Type
        // ----------------------------------------------------

        if (
            !isset($header['typ']) ||
            $header['typ'] !== 'JWT'
        ) {

            return [
                'status' => false,
                'message' => 'Invalid JWT type.'
            ];
        }


        // ----------------------------------------------------
        // Algorithm validation
        // ----------------------------------------------------

        if (
            !isset($header['alg']) ||
            $header['alg'] !== $JWT_ALGORITHM
        ) {

            return [
                'status' => false,
                'message' => 'Invalid JWT algorithm.'
            ];
        }


        // ----------------------------------------------------
        // Decode Payload
        // ----------------------------------------------------

        $payloadJson =
            base64UrlDecode(
                $encodedPayload
            );


        if (
            $payloadJson === false
        ) {

            return [
                'status' => false,
                'message' => 'Invalid JWT payload.'
            ];
        }


        $payload =
            json_decode(
                $payloadJson,
                true
            );


        if (
            !is_array($payload)
        ) {

            return [
                'status' => false,
                'message' => 'Invalid JWT payload.'
            ];
        }


        // ----------------------------------------------------
        // Required Claims
        // ----------------------------------------------------

        $requiredClaims = [
            'jti',
            'sub',
            'role',
            'type',
            'iat',
            'exp',
            'iss'
        ];


        foreach (
            $requiredClaims as $claim
        ) {

            if (
                !array_key_exists(
                    $claim,
                    $payload
                )
            ) {

                return [
                    'status' => false,
                    'message' =>
                        'Invalid JWT. Missing claim: ' .
                        $claim
                ];
            }
        }


        // ----------------------------------------------------
        // Issuer validation
        // ----------------------------------------------------

        if (
            $payload['iss'] !== $JWT_ISSUER
        ) {

            return [
                'status' => false,
                'message' => 'Invalid token issuer.'
            ];
        }


        // ----------------------------------------------------
        // User ID validation
        // ----------------------------------------------------

        if (
            !is_numeric($payload['sub']) ||
            (int) $payload['sub'] <= 0
        ) {

            return [
                'status' => false,
                'message' => 'Invalid user ID.'
            ];
        }


        // ----------------------------------------------------
        // Role validation
        // ----------------------------------------------------

        $allowedRoles = [
            'admin',
            'staff',
            'customer'
        ];


        if (
            !in_array(
                $payload['role'],
                $allowedRoles,
                true
            )
        ) {

            return [
                'status' => false,
                'message' => 'Invalid user role.'
            ];
        }


        // ----------------------------------------------------
        // Token type validation
        // ----------------------------------------------------

        if (
            !in_array(
                $payload['type'],
                [
                    'access',
                    'refresh'
                ],
                true
            )
        ) {

            return [
                'status' => false,
                'message' => 'Invalid token type.'
            ];
        }


        // ----------------------------------------------------
        // Issued At validation
        // ----------------------------------------------------

        if (
            !is_numeric($payload['iat'])
        ) {

            return [
                'status' => false,
                'message' => 'Invalid token issue time.'
            ];
        }


        // ----------------------------------------------------
        // Expiration validation
        // ----------------------------------------------------

        if (
            !is_numeric($payload['exp'])
        ) {

            return [
                'status' => false,
                'message' => 'Invalid token expiration.'
            ];
        }


        // ----------------------------------------------------
        // Token issued in future
        // Allow 60 seconds clock difference
        // ----------------------------------------------------

        if (
            (int) $payload['iat'] >
            (time() + 60)
        ) {

            return [
                'status' => false,
                'message' => 'Invalid token issue time.'
            ];
        }


        // ----------------------------------------------------
        // Token expiration
        // ----------------------------------------------------

        if (
            (int) $payload['exp'] <= time()
        ) {

            return [
                'status' => false,
                'message' => 'Token has expired.'
            ];
        }


        // ----------------------------------------------------
        // Generate expected signature
        // ----------------------------------------------------

        $signatureData =
            $encodedHeader .
            '.' .
            $encodedPayload;


        $expectedSignature =
            hash_hmac(
                'sha256',
                $signatureData,
                $JWT_SECRET,
                true
            );


        // ----------------------------------------------------
        // Decode actual signature
        // ----------------------------------------------------

        $actualSignature =
            base64UrlDecode(
                $encodedSignature
            );


        if (
            $actualSignature === false
        ) {

            return [
                'status' => false,
                'message' => 'Invalid token signature.'
            ];
        }


        // ----------------------------------------------------
        // Timing-safe comparison
        // ----------------------------------------------------

        if (
            !hash_equals(
                $expectedSignature,
                $actualSignature
            )
        ) {

            return [
                'status' => false,
                'message' => 'Invalid token signature.'
            ];
        }


        // ----------------------------------------------------
        // SUCCESS
        // ----------------------------------------------------

        return [
            'status' => true,
            'message' => 'Token verified successfully.',
            'data' => $payload
        ];


    } catch (Throwable $e) {

        return [
            'status' => false,
            'message' => 'Unable to verify token.'
        ];
    }
}


// ============================================================
// GET AUTHORIZATION HEADER
// ============================================================

function getAuthorizationHeader()
{
    // --------------------------------------------------------
    // Apache / XAMPP
    // --------------------------------------------------------

    if (
        isset($_SERVER['HTTP_AUTHORIZATION'])
    ) {

        return trim(
            $_SERVER['HTTP_AUTHORIZATION']
        );
    }


    // --------------------------------------------------------
    // Apache redirect header
    // --------------------------------------------------------

    if (
        isset(
            $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        )
    ) {

        return trim(
            $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        );
    }


    // --------------------------------------------------------
    // Fallback using getallheaders()
    // --------------------------------------------------------

    if (
        function_exists('getallheaders')
    ) {

        $headers =
            getallheaders();


        foreach (
            $headers as $key => $value
        ) {

            if (
                strtolower($key) ===
                'authorization'
            ) {

                return trim($value);
            }
        }
    }


    return null;
}


// ============================================================
// GET BEARER TOKEN
// ============================================================

function getBearerToken()
{
    $authorization =
        getAuthorizationHeader();


    if (
        empty($authorization)
    ) {

        return null;
    }


    // --------------------------------------------------------
    // Expected:
    //
    // Authorization: Bearer TOKEN
    // --------------------------------------------------------

    if (
        !preg_match(
            '/^Bearer\s+(.+)$/i',
            $authorization,
            $matches
        )
    ) {

        return null;
    }


    return trim(
        $matches[1]
    );
}


// ============================================================
// GET CURRENT USER FROM JWT
// ============================================================

function getCurrentUserFromJWT()
{
    $token =
        getBearerToken();


    if (
        empty($token)
    ) {

        return [
            'status' => false,
            'message' =>
                'Authorization token is required.'
        ];
    }


    $verification =
        verifyJWT($token);


    if (
        !$verification['status']
    ) {

        return $verification;
    }


    return [
        'status' => true,
        'message' =>
            'Authenticated successfully.',
        'data' =>
            $verification['data']
    ];
}


// ============================================================
// ACCESS TOKEN CHECK
// ============================================================

function isAccessToken($payload)
{
    return (
        isset($payload['type']) &&
        $payload['type'] === 'access'
    );
}


// ============================================================
// REFRESH TOKEN CHECK
// ============================================================

function isRefreshToken($payload)
{
    return (
        isset($payload['type']) &&
        $payload['type'] === 'refresh'
    );
}