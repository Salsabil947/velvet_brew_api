<?php
/*GET /api/product-details.php?product_id={id}
*/ 
require_once '../config/cors.php';
require_once '../config/db.php';
require_once '../config/response.php';

set_cors_headers();

// ============================================================
// Allow only GET
// ============================================================
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_error('Method not allowed. Use GET.', 405);
}

// ============================================================
// Validate product_id
// ============================================================
if (empty($_GET['product_id'])) {
    send_error('product_id is required.', 400);
}

$productId = (int) $_GET['product_id'];

if ($productId <= 0) {
    send_error('Invalid product_id.', 400);
}

try {
    $pdo = getPDO();

    // ========================================================
    // STEP 1: Get product info
    // ========================================================
    $productSql = "
        SELECT
            p.product_id,
            p.product_name,
            p.collections,
            c.category_name
        FROM products p
        JOIN categories c
            ON p.category_id = c.category_id
        WHERE p.product_id = :product_id
        LIMIT 1
    ";

    $stmt = $pdo->prepare($productSql);
    $stmt->execute(['product_id' => $productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        send_error('Product not found.', 404);
    }

    // ========================================================
    // STEP 2: Get product sizes
    // ========================================================
    $sizesSql = "
        SELECT
            product_sizes_id,
            size,
            price
        FROM product_sizes
        WHERE product_id = :product_id
        ORDER BY price ASC
    ";

    $stmt = $pdo->prepare($sizesSql);
    $stmt->execute(['product_id' => $productId]);
    $sizeRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sizes = [];
    $basePrice = null;

    foreach ($sizeRows as $row) {
        if ($basePrice === null) {
            $basePrice = (float)$row['price'];
        }

        $sizes[] = [
            'product_size_id' => (int)$row['product_sizes_id'],
            'size'            => ucfirst($row['size']),
            'price'           => (float)$row['price']
        ];
    }

    // ========================================================
    // STEP 3: Final response
    // ========================================================
    send_json([
        "success" => true,
        "data" => [
            "product_id"    => (int)$product['product_id'],
            "product_name"  => $product['product_name'],
            "category_name" => $product['category_name'],
            "collection"    => $product['collections'],
            "base_price"    => $basePrice,

            // placeholders for UI
            "image_url"     => "/images/products/" . $product['product_id'] . ".jpg",
            "description"   => "Crafted with premium ingredients and served fresh for the perfect Velvet Brew experience.",

            "sizes"         => $sizes,

            // static milk options because not in DB
            "milk_options"  => [
                "Whole Milk",
                "Oat Milk",
                "Almond Milk",
                "Pistachio Milk"
            ]
        ]
    ]);

} catch (PDOException $e) {
    send_error('Database error: ' . $e->getMessage(), 500);
}