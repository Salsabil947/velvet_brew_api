<?php
// ============================================================
// config/cors.php
// CORS Headers — allows React (running on a different port)
// to communicate with this PHP backend.
// ============================================================

/**
 * set_cors_headers()
 * Call this at the TOP of every API file, before any output.
 *
 * During development on XAMPP:
 *   - React dev server typically runs on http://localhost:3000
 *   - PHP runs on http://localhost (port 80)
 * CORS headers tell the browser it's safe to allow cross-origin requests.
 */
function set_cors_headers(): void {
    // Allow requests from React dev server
    // Change the origin if your React app runs on a different port
    header('Access-Control-Allow-Origin: http://localhost:3000');

    // Allow these HTTP methods
    header('Access-Control-Allow-Methods: GET, POST,  PUT, DELETE, OPTIONS');

    // Allow these request headers (Content-Type is needed for POST JSON body)
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    // Allow cookies / sessions to be sent cross-origin
    header('Access-Control-Allow-Credentials: true');

    // Handle preflight OPTIONS request — browsers send this first
    // before making the actual request (CORS spec requirement)
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204); // No Content
        exit;
    }
}
