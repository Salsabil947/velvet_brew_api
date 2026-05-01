<?php

define('DB_HOST',     getenv('MYSQLHOST'));
define('DB_PORT',     getenv('MYSQLPORT'));
define('DB_USER',     getenv('MYSQLUSER'));
define('DB_PASSWORD', getenv('MYSQLPASSWORD'));
define('DB_NAME',     getenv('MYSQLDATABASE'));

$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT);

if ($conn->connect_error) {
    header('Content-Type: application/json');
    http_response_code(500);
    die(json_encode([
        'error' => 'Database connection failed',
        'details' => $conn->connect_error
    ]));
}

$conn->set_charset('utf8mb4');
