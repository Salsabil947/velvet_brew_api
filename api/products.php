<?php
/**
 * GET /api/products.php
 * GET /api/products.php?collection=...
 * GET /api/products.php?search=fresh brews
 * GET /api/products.php?category=coffee
 */
require_once '../config/cors.php';
require_once '../config/db.php';
require_once '../config/response.php';

set_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_error('Method not allowed. Use GET.', 405);
}

try {
    $pdo = getPDO();

    // filters
    $search     = $_GET['search'] ?? '';
    $category   = $_GET['category'] ?? '';
    $collection = $_GET['collection'] ?? '';

    // SQL Query 
    $sql = "
        SELECT
            p.product_id,
            p.product_name,
            p.collections,
            p.image_url,              
            c.category_name,
            p.description,
            MIN(ps.price) AS price     
        FROM products p
        INNER JOIN categories c 
            ON p.category_id = c.category_id
        LEFT JOIN product_sizes ps 
            ON p.product_id = ps.product_id
        WHERE 1 = 1
    ";

    $params = [];

    if (!empty($search)) {
        $sql .= " AND p.product_name LIKE :search ";
        $params[':search'] = "%$search%";
    }

    if (!empty($category)) {
        $sql .= " AND c.category_name = :category ";
        $params[':category'] = $category;
    }

    if (!empty($collection)) {
        $sql .= " AND p.collections = :collection ";
        $params[':collection'] = $collection;
    }

    $sql .= "
        GROUP BY
            p.product_id,
            p.product_name,
            p.collections,
            p.image_url,
            p.description,
            c.category_name
        ORDER BY
            p.collections,
            p.product_name
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by collection
    $collections = [];

    foreach ($products as $product) {
        $collectionName = $product['collections'];

        if (!isset($collections[$collectionName])) {
            $collections[$collectionName] = [
                'collection_name' => $collectionName,
                'products' => []
            ];
        }

        $collections[$collectionName]['products'][] = [
            'product_id'    => (int)$product['product_id'],
            'product_name'  => $product['product_name'],
            'description'   => $product['description'],
            'category_name' => $product['category_name'],
            'price'         => (float)$product['price'],

            
            'image_url'     => $product['image_url'] 
                                ? $product['image_url']
                                : '/images/default.jpg',
        ];
    }

    send_json([
        'success' => true,
        'data' => array_values($collections)
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    send_error('Database error', 500);
} catch (Exception $e) {
    send_error('Server error', 500);
}
