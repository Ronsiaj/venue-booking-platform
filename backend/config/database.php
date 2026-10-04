<?php

require_once __DIR__ . '/../vendor/autoload.php';

// ============================================================
// LOAD ENVIRONMENT VARIABLES
// ============================================================

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);

$dotenv->load();

// ============================================================
// REQUIRED ENVIRONMENT VARIABLES
// ============================================================

$requiredEnv = [
    'DB_HOST',
    'DB_PORT',
    'DB_NAME',
    'DB_USERNAME',
    'DB_PASSWORD'
];

foreach ($requiredEnv as $envKey) {

    if (!array_key_exists($envKey, $_ENV)) {

        http_response_code(500);

        header(
            'Content-Type: application/json; charset=UTF-8'
        );

        echo json_encode([
            'status' => false,
            'message' => 'Database configuration is missing.'
        ]);

        exit;
    }
}

// ============================================================
// GET DATABASE CONFIGURATION
// ============================================================

$dbHost = $_ENV['DB_HOST'];

$dbPort = (int) $_ENV['DB_PORT'];

$dbName = $_ENV['DB_NAME'];

$dbUsername = $_ENV['DB_USERNAME'];

$dbPassword = $_ENV['DB_PASSWORD'];

// ============================================================
// CREATE MYSQL CONNECTION
// ============================================================

$conn = new mysqli(
    $dbHost,
    $dbUsername,
    $dbPassword,
    $dbName,
    $dbPort
);

// ============================================================
// DATABASE CONNECTION ERROR
// ============================================================

if ($conn->connect_errno) {

    http_response_code(500);

    header(
        'Content-Type: application/json; charset=UTF-8'
    );

    echo json_encode([
        'status' => false,
        'message' => 'Database connection failed.'
    ]);

    exit;
}

// ============================================================
// SET UTF-8 CHARACTER SET
// ============================================================

if (!$conn->set_charset('utf8mb4')) {

    http_response_code(500);

    header(
        'Content-Type: application/json; charset=UTF-8'
    );

    echo json_encode([
        'status' => false,
        'message' => 'Failed to configure database charset.'
    ]);

    exit;
}