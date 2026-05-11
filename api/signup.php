<?php
/**
 * POST /api/signup.php
 * Registers a new customer into the existing `customers` table.
 *
 * Accepted JSON fields (must match DB columns):
 *   first_name, last_name, email, password, phone, birth_date
 *
 * NOTE: The customers table has a leading-space typo on the PK column
 *       (` customer_id` instead of `customer_id`). This file works around
 *       that transparently — you do NOT need to alter the table.
 */

declare(strict_types=1);

// CORS MUST come first
require_once __DIR__ . '/../config/cors.php';
set_cors_headers();

// Then other headers
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db-db.php';

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

// ─── 3. Required-field validation ─────────────────────────────────────────────
$required = ['first_name', 'last_name', 'email', 'password'];
$missing  = [];

foreach ($required as $field) {
    if (empty(trim((string)($body[$field] ?? '')))) {
        $missing[] = $field;
    }
}

if (!empty($missing)) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Missing required fields: ' . implode(', ', $missing)
    ]);
    exit;
}

// ─── 4. Email format validation ───────────────────────────────────────────────
if (!filter_var($body['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid email format.']);
    exit;
}

// ─── 5. Password length validation ───────────────────────────────────────────
if (strlen($body['password']) < 6) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Password must be at least 6 characters.']);
    exit;
}

// ─── 6. Discover actual columns from the DB (dynamic / future-proof) ─────────
$pdo = getPDO();

try {
    $colStmt = $pdo->query("DESCRIBE `customers`");
    $dbColumns = $colStmt->fetchAll(PDO::FETCH_COLUMN); // raw names, including the ` customer_id` typo
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Could not read table schema.']);
    exit;
}

// Build a lookup: trimmed_name => real_name_in_db
// This handles the leading-space typo on ` customer_id` transparently.
$columnMap = [];
foreach ($dbColumns as $col) {
    $columnMap[trim($col)] = $col;
}

// ─── 7. Check email uniqueness ────────────────────────────────────────────────
try {
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM `customers` WHERE `email` = :email");
    $checkStmt->execute([':email' => trim($body['email'])]);
    $count = (int) $checkStmt->fetchColumn();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error. Please try again.']);
    exit;
}

if ($count > 0) {
    http_response_code(409);
    echo json_encode(['status' => 'error', 'message' => 'Email already exists.']);
    exit;
}

// ─── 8. Build dynamic INSERT (only fields that exist in the DB) ───────────────
// Fields the caller is NOT allowed to supply directly
$excluded = ['customer_id', ' customer_id']; // skip PK (both variants)

$insertCols   = [];   // real DB column names (quoted)
$insertParams = [];   // PDO placeholders
$insertValues = [];   // actual values

foreach ($body as $key => $value) {
    $trimmedKey = trim($key);

    // Skip if not a real column or is excluded
    if (!isset($columnMap[$trimmedKey])) continue;
    if (in_array($trimmedKey, $excluded, true)) continue;

    $realCol = $columnMap[$trimmedKey]; // may differ from $key due to spaces

    // Hash the password before storing
    if ($trimmedKey === 'password') {
        $value = password_hash((string)$value, PASSWORD_BCRYPT);
    }

    $placeholder = ':param_' . preg_replace('/\W/', '_', $trimmedKey);
    $insertCols[]              = "`{$realCol}`";
    $insertParams[]            = $placeholder;
    $insertValues[$placeholder] = $value;
}

if (empty($insertCols)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No valid fields provided.']);
    exit;
}

$sql = "INSERT INTO `customers` (" . implode(', ', $insertCols) . ")
        VALUES ("                  . implode(', ', $insertParams) . ")";

// ─── 9. Execute ───────────────────────────────────────────────────────────────
try {
    $insertStmt = $pdo->prepare($sql);
    $insertStmt->execute($insertValues);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Registration failed. Please try again.']);
    exit;
}

// ─── 10. Success ──────────────────────────────────────────────────────────────
http_response_code(201);
echo json_encode([
    'status'  => 'success',
    'message' => 'Customer registered successfully.'
]);    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON body.']);
    exit;
}

// ─── 3. Required-field validation ─────────────────────────────────────────────
$required = ['first_name', 'last_name', 'email', 'password'];
$missing  = [];

foreach ($required as $field) {
    if (empty(trim((string)($body[$field] ?? '')))) {
        $missing[] = $field;
    }
}

if (!empty($missing)) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Missing required fields: ' . implode(', ', $missing)
    ]);
    exit;
}

// ─── 4. Email format validation ───────────────────────────────────────────────
if (!filter_var($body['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid email format.']);
    exit;
}

// ─── 5. Password length validation ───────────────────────────────────────────
if (strlen($body['password']) < 6) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Password must be at least 6 characters.']);
    exit;
}

// ─── 6. Discover actual columns from the DB (dynamic / future-proof) ─────────
$pdo = getPDO();

try {
    $colStmt = $pdo->query("DESCRIBE `customers`");
    $dbColumns = $colStmt->fetchAll(PDO::FETCH_COLUMN); // raw names, including the ` customer_id` typo
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Could not read table schema.']);
    exit;
}

// Build a lookup: trimmed_name => real_name_in_db
// This handles the leading-space typo on ` customer_id` transparently.
$columnMap = [];
foreach ($dbColumns as $col) {
    $columnMap[trim($col)] = $col;
}

// ─── 7. Check email uniqueness ────────────────────────────────────────────────
try {
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM `customers` WHERE `email` = :email");
    $checkStmt->execute([':email' => trim($body['email'])]);
    $count = (int) $checkStmt->fetchColumn();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error. Please try again.']);
    exit;
}

if ($count > 0) {
    http_response_code(409);
    echo json_encode(['status' => 'error', 'message' => 'Email already exists.']);
    exit;
}

// ─── 8. Build dynamic INSERT (only fields that exist in the DB) ───────────────
// Fields the caller is NOT allowed to supply directly
$excluded = ['customer_id', ' customer_id']; // skip PK (both variants)

$insertCols   = [];   // real DB column names (quoted)
$insertParams = [];   // PDO placeholders
$insertValues = [];   // actual values

foreach ($body as $key => $value) {
    $trimmedKey = trim($key);

    // Skip if not a real column or is excluded
    if (!isset($columnMap[$trimmedKey])) continue;
    if (in_array($trimmedKey, $excluded, true)) continue;

    $realCol = $columnMap[$trimmedKey]; // may differ from $key due to spaces

    // Hash password
    if ($trimmedKey === 'password') {
        $value = password_hash((string)$value, PASSWORD_BCRYPT);
        }

// Fix birth_date format
if ($trimmedKey === 'birth_date' && !empty($value)) {

    // HTML date input sends YYYY-MM-DD
    $date = DateTime::createFromFormat('Y-m-d', $value);

    // Optional fallback for MM/DD/YYYY
    if (!$date) {
        $date = DateTime::createFromFormat('m/d/Y', $value);
    }

    if ($date) {
        $value = $date->format('Y-m-d');
    } else {
        $value = null;
    }
}

    $placeholder = ':param_' . preg_replace('/\W/', '_', $trimmedKey);
    $insertCols[]              = "`{$realCol}`";
    $insertParams[]            = $placeholder;
    $insertValues[$placeholder] = $value;
}

if (empty($insertCols)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No valid fields provided.']);
    exit;
}

$sql = "INSERT INTO `customers` (" . implode(', ', $insertCols) . ")
        VALUES ("                  . implode(', ', $insertParams) . ")";

// ─── 9. Execute ───────────────────────────────────────────────────────────────
try {
    $insertStmt = $pdo->prepare($sql);
    $insertStmt->execute($insertValues);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Registration failed. Please try again.']);
    exit;
}

// ─── 10. Success ──────────────────────────────────────────────────────────────
http_response_code(201);
echo json_encode([
    'status'  => 'success',
    'message' => 'Customer registered successfully.'
]);
