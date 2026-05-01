<?php

$host = getenv('MYSQLHOST') ?: $_ENV['MYSQLHOST'] ?? $_SERVER['MYSQLHOST'] ?? null;
$user = getenv('MYSQLUSER') ?: $_ENV['MYSQLUSER'] ?? $_SERVER['MYSQLUSER'] ?? null;
$pass = getenv('MYSQLPASSWORD') ?: $_ENV['MYSQLPASSWORD'] ?? $_SERVER['MYSQLPASSWORD'] ?? null;
$db   = getenv('MYSQLDATABASE') ?: $_ENV['MYSQLDATABASE'] ?? $_SERVER['MYSQLDATABASE'] ?? null;
$port = getenv('MYSQLPORT') ?: 3306;

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    header('Content-Type: application/json');
    http_response_code(500);
    die(json_encode([
        'error' => 'Database connection failed',
        'debug' => [
            'host' => $host,
            'user' => $user,
            'db'   => $db,
            'port' => $port
        ]
    ]));
}

$conn->set_charset('utf8mb4');
