<?php

define('DB_HOST', getenv('MYSQLHOST'));
define('DB_NAME', getenv('MYSQLDATABASE'));
define('DB_USER', getenv('MYSQLUSER'));
define('DB_PASS', getenv('MYSQLPASSWORD'));
define('DB_PORT', getenv('MYSQLPORT') ?: 3306);

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if ($conn->connect_error) {
    header('Content-Type: application/json');
    http_response_code(500);
    die(json_encode([
        'error' => 'Database connection failed',
        'details' => $conn->connect_error
    ]));
}

$conn->set_charset('utf8mb4');
