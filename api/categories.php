<?php
// api/categories.php
// ─────────────────────────────────────────────────────────────
//  GET /api/categories.php         → all categories
//  GET /api/categories.php?id=N    → single category
// ─────────────────────────────────────────────────────────────

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/response.php';

set_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_error('Method not allowed. Use GET.', 405);
}

try {
    $pdo = get_db();

    // ── Single category ──────────────────────────────────────
    if (isset($_GET['id'])) {
        $id = filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            send_error('Invalid category id. Must be a positive integer.', 422);
        }

        $stmt = $pdo->prepare(
            'SELECT id, name, slug, icon, sort_order FROM categories WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $category = $stmt->fetch();

        if (!$category) {
            send_error('Category not found.', 404);
        }

        $category['id']         = (int) $category['id'];
        $category['sort_order'] = (int) $category['sort_order'];
        send_json($category);
    }

    // ── All categories ───────────────────────────────────────
    $stmt      = $pdo->query('SELECT id, name, slug, icon, sort_order FROM categories ORDER BY sort_order ASC');
    $categories = $stmt->fetchAll();

    foreach ($categories as &$cat) {
        $cat['id']         = (int) $cat['id'];
        $cat['sort_order'] = (int) $cat['sort_order'];
    }
    unset($cat);

    send_json($categories);

} catch (PDOException $e) {
    send_error('Database error: ' . $e->getMessage(), 500);
}
