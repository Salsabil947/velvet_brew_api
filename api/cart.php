<?php
/*
 GET /api/cart.php?customer_id=1
 POST /api/cart.php
    Body: { "customer_id": 1, "product_id": 17, "quantity": 2 }
 PUT /api/cart.php
    Body: { "cart_id": 1, "quantity": 3 }
 DELETE /api/cart.php
    Body: { "cart_id": 1 } 

  */
require_once '../config/cors.php';
require_once '../config/db.php';
require_once '../config/response.php';

set_cors_headers();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGetCart();
        break;
    case 'POST':
        handleAddToCart();
        break;
    case 'PUT':
        handleUpdateCart();
        break;
    case 'DELETE':
        handleDeleteCart();
        break;
    default:
        send_error("Method not allowed", 405);
}

// Get cart
function handleGetCart() {
    $pdo = getPDO();

    $customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;

    if ($customer_id <= 0) {
        send_error("customer_id is required", 400);
    }

    try {
        $sql = "
            SELECT 
                c.cart_id,
                c.product_id,
                c.quantity,
                p.product_name,
                p.description,
                MIN(ps.price) AS price
            FROM cart c
            JOIN products p ON c.product_id = p.product_id
            JOIN product_sizes ps ON p.product_id = ps.product_id
            WHERE c.customer_id = :customer_id
            GROUP BY c.cart_id, c.product_id, c.quantity, p.product_name
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['customer_id' => $customer_id]);
        $items = $stmt->fetchAll();

        $subtotal = 0;

        foreach ($items as &$item) {
            $item['price'] = (float)$item['price'];
            $item['line_total'] = $item['price'] * $item['quantity'];
            $subtotal += $item['line_total'];
        }

        $shipping = $subtotal > 0 ? 30 : 0;
        $tax = $subtotal * 0.14;
        $grand_total = $subtotal + $shipping + $tax;

        send_json([
            "success" => true,
            "items" => $items,
            "summary" => [
                "subtotal" => round($subtotal, 2),
                "shipping" => round($shipping, 2),
                "tax" => round($tax, 2),
                "grand_total" => round($grand_total, 2)
            ]
        ]);

    } catch(PDOException $e) {
        send_error("Database error: " . $e->getMessage(), 500);
    }
}

// Add to cart
function handleAddToCart() {
    $pdo = getPDO();

    $data = json_decode(file_get_contents("php://input"), true);

    $customer_id = (int)($data['customer_id'] ?? 0);
    $product_id  = (int)($data['product_id'] ?? 0);
    $quantity    = (int)($data['quantity'] ?? 1);

    if ($customer_id <= 0 || $product_id <= 0) {
        send_error("customer_id and product_id are required", 400);
    }

    try {
        $check = $pdo->prepare("
            SELECT cart_id, quantity
            FROM cart
            WHERE customer_id = :customer_id AND product_id = :product_id
        ");
        $check->execute([
            'customer_id' => $customer_id,
            'product_id' => $product_id
        ]);

        $existing = $check->fetch();

        if ($existing) {
            $newQty = $existing['quantity'] + $quantity;

            $update = $pdo->prepare("
                UPDATE cart
                SET quantity = :quantity
                WHERE cart_id = :cart_id
            ");
            $update->execute([
                'quantity' => $newQty,
                'cart_id' => $existing['cart_id']
            ]);
        } else {
            $insert = $pdo->prepare("
                INSERT INTO cart (customer_id, product_id, quantity)
                VALUES (:customer_id, :product_id, :quantity)
            ");
            $insert->execute([
                'customer_id' => $customer_id,
                'product_id' => $product_id,
                'quantity' => $quantity
            ]);
        }

        send_json([
            "success" => true,
            "message" => "Item added to cart"
        ], 201);

    } catch(PDOException $e) {
        send_error("Database error: " . $e->getMessage(), 500);
    }
}

// Update cart item
function handleUpdateCart() {
    $pdo = getPDO();

    $data = json_decode(file_get_contents("php://input"), true);

    $cart_id = (int)($data['cart_id'] ?? 0);
    $quantity = (int)($data['quantity'] ?? 0);

    if ($cart_id <= 0) {
        send_error("cart_id is required", 400);
    }

    try {
        if ($quantity <= 0) {
            $stmt = $pdo->prepare("DELETE FROM cart WHERE cart_id = :cart_id");
            $stmt->execute(['cart_id' => $cart_id]);

            send_json([
                "success" => true,
                "message" => "Item removed from cart"
            ]);
        }

        $stmt = $pdo->prepare("
            UPDATE cart
            SET quantity = :quantity
            WHERE cart_id = :cart_id
        ");

        $stmt->execute([
            'quantity' => $quantity,
            'cart_id' => $cart_id
        ]);

        send_json([
            "success" => true,
            "message" => "Cart updated"
        ]);

    } catch(PDOException $e) {
        send_error("Database error: " . $e->getMessage(), 500);
    }
}

// Delete cart item
function handleDeleteCart() {
    $pdo = getPDO();

    $data = json_decode(file_get_contents("php://input"), true);
    $cart_id = (int)($data['cart_id'] ?? 0);

    if ($cart_id <= 0) {
        send_error("cart_id is required", 400);
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM cart WHERE cart_id = :cart_id");
        $stmt->execute(['cart_id' => $cart_id]);

        send_json([
            "success" => true,
            "message" => "Item deleted"
        ]);

    } catch(PDOException $e) {
        send_error("Database error: " . $e->getMessage(), 500);
    }
}
