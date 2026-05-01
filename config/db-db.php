<?php

$host = getenv('MYSQLHOST');
$user = getenv('MYSQLUSER');
$pass = getenv('MYSQLPASSWORD');
$db   = getenv('MYSQLDATABASE');
$port = getenv('MYSQLPORT') ?: 3306;

$conn = new mysqli();

$conn->real_connect($host, $user, $pass, $db, (int)$port);

if ($conn->connect_error) {
    header('Content-Type: application/json');
    http_response_code(500);
    die(json_encode([
        'error' => 'Database connection failed',
        'debug' => [
            'host' => $host,
            'user' => $user,
            'db'   => $db,
            'port' => $port,
            'mysql_error' => $conn->connect_error
        ]
    ]));
}

$conn->set_charset('utf8mb4');

$conn->set_charset('utf8mb4');
