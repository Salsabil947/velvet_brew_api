<?php
// ============================================================
// db.php — Works for BOTH local + Railway
// ============================================================

// Use Railway variables if they exist, otherwise fallback to local
$host = getenv('MYSQLHOST') ?: '127.0.0.1';
$user = getenv('MYSQLUSER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: '';
$db   = getenv('MYSQLDATABASE') ?: 'velvet_brew';
$port = getenv('MYSQLPORT') ?: 3306;

// Create connection
$conn = new mysqli();

// IMPORTANT: force TCP (avoids "No such file or directory" bug)
$conn->real_connect($host, $user, $pass, $db, (int)$port);

// Error handling
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

// Charset
$conn->set_charset('utf8mb4');
