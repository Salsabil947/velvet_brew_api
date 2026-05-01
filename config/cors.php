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
 *   - PHP runs on http://localhost (port 80)
 * CORS headers tell the browser it's safe to allow cross-origin requests.
 */
function set_cors_headers(): void {
   
    // Allow all origins (for development)
    header('Access-Control-Allow-Origin: *');
    
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
