<?php
// ============================================================
// functions_products.php — Product CRUD Functions
// ============================================================
// Handles: list, add, edit, delete products

// ============================================================
// 1. getAllProducts($conn)
// ============================================================
// WHAT IT DOES:
//   Gets all products with their current stock level and price.
//   Powers the Product Catalog grid in the UI.

// RETURNS: array of products, each with:
//   product_id, product_name, collection, description,
//   roast_type, price, quantity, unit, low_stock_threshold, is_low_stock
// ============================================================
function getAllProducts($conn) {
    $sql = "SELECT
                p.product_id,
                p.product_name,
                p.collections          AS collection,
                p.description,
                p.roast_type,
                MIN(ps.price)           AS price,
                COALESCE(i.quantity, 0) AS quantity,
                COALESCE(i.unit, 'pcs') AS unit,
                i.low_stock_threshold,
                CASE
                    WHEN i.quantity <= i.low_stock_threshold THEN 1
                    ELSE 0
                END AS is_low_stock
            FROM products AS p
            LEFT JOIN product_sizes AS ps ON ps.product_id = p.product_id
            LEFT JOIN inventory     AS i  ON i.product_id  = p.product_id
            GROUP BY
                p.product_id,
                p.product_name,
                p.collections,
                p.description,
                p.roast_type,
                i.quantity,
                i.unit,
                i.low_stock_threshold
            ORDER BY p.product_name ASC";

    $result   = $conn->query($sql);
    $products = [];

    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }

    return $products;
}


// ============================================================
// 2. getProductById($conn, $product_id)
// ============================================================
// WHAT IT DOES:
//   Gets a single product by its ID.
//   Used to pre-fill the Edit Product form.

// RETURNS: one product row or null if not found
// ============================================================
function getProductById($conn, $product_id) {
    $id = (int) $product_id;

    $sql = "SELECT
                p.product_id,
                p.category_id,
                p.product_name,
                p.collections          AS collection,
                p.description,
                p.roast_type,
                MIN(ps.price)           AS price,
                COALESCE(i.quantity, 0) AS quantity,
                COALESCE(i.unit, 'pcs') AS unit,
                i.low_stock_threshold
            FROM products AS p
            LEFT JOIN product_sizes AS ps ON ps.product_id = p.product_id
            LEFT JOIN inventory     AS i  ON i.product_id  = p.product_id
            WHERE p.product_id = $id
            GROUP BY
                p.product_id,
                p.category_id,
                p.product_name,
                p.collections,
                p.description,
                p.roast_type,
                i.quantity,
                i.unit,
                i.low_stock_threshold
            LIMIT 1";

    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }

    return null;
}


// ============================================================
// 3. addProduct($conn, $data)
// ============================================================
// WHAT IT DOES:
//   Inserts a new product into the database.
//   Also creates its first product_size and inventory record.
//
// --- UNDERSTANDING category_id vs collection ---
//   These are TWO separate fields, both stored on the products table.
//
//   category_id → links to the categories table (what TYPE of product)
//                 1 = coffee
//                 2 = desserts
//                 3 = pastries
//                 4 = coffee beans
//
//   collection  → the product's style/series label (ENUM column)
//                 'fresh brews' | 'handmade croissants' |
//                 'artisanal toasts' | 'seasonal specials'
//
//   The frontend must send BOTH values in the request body.
//   If category_id is missing it defaults to 4 (coffee beans).

// $data KEYS EXPECTED:
//   - product_name  (string, required)
//   - category_id   (int: 1=coffee, 2=desserts, 3=pastries, 4=coffee beans)
//   - collection    (string: one of the 4 ENUM values)
//   - description   (string, optional)
//   - roast_type    (string: Light / Medium / Dark)
//   - price         (float)
//   - quantity      (float, initial stock)
//
// RETURNS:
//   ['success' => true,  'product_id' => 7]   on success
//   ['success' => false, 'error' => '...']     on failure
// ============================================================
function addProduct($conn, $data) {

    // --- Validate required fields ---
    if (empty($data['product_name'])) {
        return ['success' => false, 'error' => 'Product name is required'];
    }

    // --- Sanitize basic inputs ---
    $product_name = trim($data['product_name']);
    $description  = trim($data['description'] ?? '');
    $price        = (float) ($data['price']    ?? 0);
    $quantity     = (float) ($data['quantity'] ?? 0);
    $category_id  = (int) ($data['category_id'] ?? 4);

    $image_url = trim($data['image_url'] ?? '');

    $allowed_cats = [1, 2, 3, 4];
    if (!in_array($category_id, $allowed_cats)) {
        return [
            'success' => false,
            'error'   => 'Invalid category_id'
        ];
    }

    // --- Validate collection ---
    $collection = trim($data['collection'] ?? 'seasonal specials');
    $allowed_collections = [
        'fresh brews',
        'handmade croissants',
        'artisanal toasts',
        'seasonal specials'
    ];

    if (!in_array($collection, $allowed_collections)) {
        return [
            'success' => false,
            'error'   => 'Invalid collection'
        ];
    }

    // --- Validate roast_type ---
    $roast_type     = trim($data['roast_type'] ?? 'Medium');
    $allowed_roasts = ['Light', 'Medium', 'Dark'];

    if (!in_array($roast_type, $allowed_roasts)) {
        return ['success' => false, 'error' => 'Invalid roast type'];
    }
    
    $stmt = $conn->prepare(
        "INSERT INTO products 
        (category_id, collections, product_name, description, roast_type, price, image_url)
        VALUES (?, ?, ?, ?, ?, ?, ?)"
    );

    $stmt->bind_param(
        'issssds',
        $category_id,
        $collection,
        $product_name,
        $description,
        $roast_type,
        $price,
        $image_url
    );

    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        $stmt->close();
        return ['success' => false, 'error' => 'Failed to insert product'];
    }

    $new_product_id = $conn->insert_id;
    $stmt->close();

    // --- Insert into product_sizes ---
    $default_size = 'regular';
    $stmt = $conn->prepare(
        "INSERT INTO product_sizes (product_id, size, price)
         VALUES (?, ?, ?)"
    );
    $stmt->bind_param('isd', $new_product_id, $default_size, $price);
    $stmt->execute();
    $stmt->close();

    // --- Insert into inventory ---
    $low_stock_threshold = 10;
    $unit = 'pcs';

    $stmt = $conn->prepare(
        "INSERT INTO inventory (product_id, quantity, unit, low_stock_threshold)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param('idsi', $new_product_id, $quantity, $unit, $low_stock_threshold);
    $stmt->execute();
    $stmt->close();

    return [
        'success'    => true,
        'product_id' => $new_product_id
    ];
}


// ============================================================
// 4. updateProduct($conn, $product_id, $data)
// ============================================================
// WHAT IT DOES:
//   Updates an existing product's details and stock level.

// $data KEYS EXPECTED:
//   - product_name        (string, required)
//   - category_id         (int: 1–4)
//   - collection          (string)
//   - description         (string)
//   - roast_type          (string: Light / Medium / Dark)
//   - price               (float)
//   - quantity            (float)
//   - low_stock_threshold (int)
//
// RETURNS:
//   ['success' => true]             on success
//   ['success' => false, 'error']   on failure
// ============================================================
function updateProduct($conn, $product_id, $data) {

    $id = (int) $product_id;

    if (empty($data['product_name'])) {
        return ['success' => false, 'error' => 'Product name is required'];
    }

    // --- Sanitize ---
    $product_name        = trim($data['product_name']);
    $description         = trim($data['description']         ?? '');
    $quantity            = (float) ($data['quantity']            ?? 0);
    $low_stock_threshold = (int)   ($data['low_stock_threshold'] ?? 10);
    $price               = (float) ($data['price']               ?? 0);

    // --- Read and validate category_id ---
    $category_id  = (int) ($data['category_id'] ?? 4);
    $allowed_cats = [1, 2, 3, 4];
    if (!in_array($category_id, $allowed_cats)) {
        return [
            'success' => false,
            'error'   => 'Invalid category_id. Use: 1 (coffee), 2 (desserts), 3 (pastries), 4 (coffee beans)'
        ];
    }

    // --- Validate collection against the actual DB ENUM values ---
    $collection          = trim($data['collection'] ?? '');
    $allowed_collections = [
        'fresh brews',
        'handmade croissants',
        'artisanal toasts',
        'seasonal specials'
    ];
    if ($collection !== '' && !in_array($collection, $allowed_collections)) {
        return ['success' => false, 'error' => 'Invalid collection value'];
    }

    // --- Validate roast_type ---
    $roast_type     = trim($data['roast_type'] ?? 'Medium');
    $allowed_roasts = ['Light', 'Medium', 'Dark'];
    if (!in_array($roast_type, $allowed_roasts)) {
        return ['success' => false, 'error' => 'Invalid roast type'];
    }

    // --- Update products table ---
    $stmt = $conn->prepare(
        "UPDATE products
         SET category_id  = ?,
             product_name = ?,
             collections  = ?,
             description  = ?,
             roast_type   = ?,
             price        = ?
         WHERE product_id = ?"
    );
    // i = category_id, s = product_name, s = collections, s = description,
    // s = roast_type, d = price, i = product_id
    $stmt->bind_param('issssdi', $category_id, $product_name, $collection, $description, $roast_type, $price, $id);
    $stmt->execute();
    $stmt->close();

    // --- Update inventory table ---
    $stmt = $conn->prepare(
        "UPDATE inventory
         SET quantity            = ?,
             low_stock_threshold = ?
         WHERE product_id = ?"
    );
    $stmt->bind_param('dii', $quantity, $low_stock_threshold, $id);
    $stmt->execute();
    $stmt->close();

    return ['success' => true];
}


// ============================================================
// 5. deleteProduct($conn, $product_id)
// ============================================================
// WHAT IT DOES:
//   Removes a product and all its related data from the database.
//
// FIX: Added DELETE from cart before deleting the product.
//      The cart table has a FK on product_id → products.
//      Without this step, deleting a product that exists in any
//      customer's cart would fail with a foreign key error.
//
//   Delete order:
//     1. order_items    (references product_sizes)
//     2. product_sizes  (references products)
//     3. inventory      (references products)
//     4. cart           (references products) ← was missing
//     5. products       (parent row — safe to delete now)
//
// RETURNS:
//   ['success' => true]             on success
//   ['success' => false, 'error']   if product not found
// ============================================================
function deleteProduct($conn, $product_id) {

    $id = (int) $product_id;

    $check = $conn->query("SELECT product_id FROM products WHERE product_id = $id");
    if ($check->num_rows === 0) {
        return ['success' => false, 'error' => "Product #$id not found"];
    }

    // Step 1: Delete order_items that reference this product's sizes
    $stmt = $conn->prepare(
        "DELETE FROM order_items
         WHERE product_sizes_id IN (
             SELECT product_sizes_id FROM product_sizes WHERE product_id = ?
         )"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    // Step 2: Delete product_sizes
    $stmt = $conn->prepare("DELETE FROM product_sizes WHERE product_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    // Step 3: Delete inventory record
    $stmt = $conn->prepare("DELETE FROM inventory WHERE product_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    // Step 4: Delete from cart (was missing — caused FK constraint error)
    $stmt = $conn->prepare("DELETE FROM cart WHERE product_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    // Step 5: Delete the product itself
    $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected === 0) {
        return ['success' => false, 'error' => 'Delete failed'];
    }

    return ['success' => true];
}
