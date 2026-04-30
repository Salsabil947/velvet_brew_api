<?php
/**
 * POST /api/login.php
 * Authenticates an existing customer.
 *
 * Accepted JSON:
 *   { "email": "...", "password": "..." }
 */

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';

// ─── 1. Only allow POST ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed. Use POST.']);
    exit;
}

// ─── 2. Parse JSON body ───────────────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true);

if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON body.']);
    exit;
}

// ─── 3. Presence check ───────────────────────────────────────────────────────
$email    = trim((string)($body['email']    ?? ''));
$password = (string)($body['password'] ?? '');

if ($email === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Email and password are required.']);
    exit;
}

// ─── 4. Look up the customer by email ────────────────────────────────────────
$pdo = getPDO();

try {
    // Select using the backtick-quoted real column name.
    // The PK column has a leading space (` customer_id`) — we alias it cleanly.
    $stmt = $pdo->prepare(
        "SELECT `customer_id`, `first_name`, `last_name`, `email`, `password`
         FROM   `customers`
         WHERE  `email` = :email
         LIMIT  1"
    );
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(); // returns array or false
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error. Please try again.']);
    exit;
}

// ─── 5. Timing-safe verification ─────────────────────────────────────────────
// Even when no user is found we still call password_verify on a dummy hash.
// This prevents an attacker from inferring existence via response-time differences.
$DUMMY_HASH = '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';

if ($user === false) {
    // Burn the same CPU time as a real check
    password_verify($password, $DUMMY_HASH);

    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid credentials.']);
    exit;
}

if (!password_verify($password, $user['password'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid credentials.']);
    exit;
}

// ─── 6. Success — return safe user data (never return the password hash) ─────

// Handle the leading-space PK alias gracefully
$customerId = $user['customer_id'] ?? $user[' customer_id'] ?? null;

http_response_code(200);
echo json_encode([
    'status' => 'success',
    'user'   => [
        'customer_id' => $customerId,
        'first_name'  => $user['first_name'],
        'last_name'   => $user['last_name'],
        'email'       => $user['email'],
    ]
]);
