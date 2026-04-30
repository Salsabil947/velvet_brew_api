<?php
// ============================================================
// config/response.php
// Helper functions to send JSON responses
// ============================================================

/**
 * send_json($data, $status_code)
 * Sends a successful JSON response with HTTP 200 (or custom code).
 *
 * @param array $data        The data to encode as JSON
 * @param int   $status_code HTTP status code (default 200)
 */
function send_json(array $data, int $status_code = 200): void {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit; // Stop further script execution
}

/**
 * send_error($message, $status_code)
 * Sends a JSON error response.
 *
 * @param string $message    Human-readable error description
 * @param int    $status_code HTTP status code (e.g. 400, 404, 500)
 */
function send_error(string $message, int $status_code = 400): void {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error'   => $message
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit; // Stop further script execution
}

/**
 * sendSuccess($data)
 * Sends a 200 OK JSON response with your data.
 *
 * Example usage:
 *   sendSuccess(["order_id" => 5, "total" => 19.95]);
 */
function sendSuccess($data = []) {
    http_response_code(200);
    echo json_encode(array_merge(["success" => true], $data));
    exit();
}

/**
 * sendError($message, $statusCode)
 * Sends a JSON error response with the given HTTP status code.
 *
 * Example usage:
 *   sendError("Cart is empty", 400);
 */
function sendError($message, $statusCode = 400) {
    http_response_code($statusCode);
    echo json_encode([
        "success" => false,
        "message" => $message
    ]);
    exit();
}

/**
 * setJsonHeaders()
 * Sets the response headers so the browser knows we're sending JSON.
 * Call this at the top of every API file.
 */
function setJsonHeaders() {
    header("Content-Type: application/json");
    header("Access-Control-Allow-Origin: *");           // Allow frontend to call this API
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");

    // Handle browser preflight OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}
