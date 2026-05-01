<?php
// ============================================================
// db.php — Database Connection
// ============================================================

// --- Your database settings ---
define('DB_HOST', getenv('MYSQLHOST'));
define('DB_NAME', getenv('MYSQLDATABASE'));
define('DB_USER', getenv('MYSQLUSER'));
define('DB_PASSWORD', getenv('MYSQLPASSWORD'));
define('DB_PORT', getenv('MYSQLPORT') ?: 3306);


// --- Create the connection ---
// Port must be passed as the 4th parameter separately — not inside the host string
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT);

// --- Check if connection failed ---
// FIX: Return JSON error instead of plain text so api.php doesn't break
if ($conn->connect_error) {
    header('Content-Type: application/json');
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed']));
}

// --- Set character encoding to UTF-8 ---
$conn->set_charset('utf8mb4');

