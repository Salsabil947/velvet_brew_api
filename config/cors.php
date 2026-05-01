<?php
// ============================================================
// config/cors.php
// Supports multiple frontend origins + credentials
// ============================================================

function set_cors_headers(): void {

    // 👇 Allowed frontend origins
    $allowed_origins = [
        'http://localhost:5173',
        'http://localhost:5174'
    ];

    // 👇 Get request origin
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    // 👇 Allow only known origins (REQUIRED when using credentials)
    if (in_array($origin, $allowed_origins, true)) {
        header("Access-Control-Allow-Origin: $origin");
    }

    // 👇 Required headers
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Credentials: true');

    // 👇 Handle preflight (OPTIONS request)
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
