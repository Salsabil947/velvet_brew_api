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
require_once __DIR__ . '/../config/cors.php';

set_cors_headers();

// ─── 1. Only allow POST ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed. Use POST.'
    ]);
    exit;
}

// ─── 2. Parse JSON body ─────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true);

if (!is_array($body)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid JSON body.'
    ]);
    exit;
}

// ─── 3. Validate input ──────────────────────────────────────
$email    = trim((string)($body['email'] ?? ''));
$password = (string)($body['password'] ?? '');

if ($email === '' || $password === '') {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Email and password are required.'
    ]);
    exit;
}

// ─── 4. Fetch user from DB (INCLUDING role) ─────────────────
$pdo = getPDO();

try {
    $stmt = $pdo->prepare("
        SELECT 
            `customer_id`,
            `first_name`,
            `last_name`,
            `email`,
            `password`,
            `role`
        FROM `customers`
        WHERE `email` = :email
        LIMIT 1
    ");

    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error. Please try again.'
    ]);
    exit;
}

// ─── 5. Timing-safe verification ───────────────────────────
$DUMMY_HASH = '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';

if ($user === false) {
    password_verify($password, $DUMMY_HASH);

    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid credentials.'
    ]);
    exit;
}

if (!password_verify($password, $user['password'])) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid credentials.'
    ]);
    exit;
}

// ─── 6. Get role from DB ───────────────────────────────────
$role = $user['role'] ?? 'user';

// ─── 7. Clean user ID (handles weird column typo) ─────────
$customerId = $user['customer_id'] ?? $user[' customer_id'] ?? null;

// ─── 8. Success response ──────────────────────────────────
http_response_code(200);
echo json_encode([
    'status' => 'success',
    'role'   => $role,
    'user'   => [
        'customer_id' => $customerId,
        'first_name'  => $user['first_name'],
        'last_name'   => $user['last_name'],
        'email'       => $user['email'],
    ]
]);
