<?php
/*
POST /api/checkout.php
   Body: {
     "customer_id": ,
     "shipping": {
       "first_name": "",
       "last_name": "",
       "street_address": "",
       "city": "",
       "state": "",
       "zip": "",
       "shipping_method": ""
     },
     "payment": {
       "card_number": "",
       "expiration_date": "",
       "cvv": ""
     }
   }
*/

require_once '../config/cors.php';
require_once '../config/db.php';
require_once '../config/response.php';

set_cors_headers();


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error("Only POST method is allowed.", 405);
}

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    send_error("Invalid JSON body.", 400);
}

// shipping_method requirement
if (empty($data['customer_id']) || empty($data['shipping']) || empty($data['payment'])) {
    send_error("customer_id, shipping, and payment are required.", 400);
}

$customerId = (int)$data['customer_id'];
$shipping   = $data['shipping'];
$payment    = $data['payment'];

$pdo = getPDO();

try {

    // Fetch cart items
    $cartSql = "
        SELECT
            c.cart_id,
            c.product_id,
            c.quantity,
            p.product_name,
            ps.product_sizes_id,
            MIN(ps.price) AS price
        FROM cart c
        JOIN products p ON c.product_id = p.product_id
        JOIN product_sizes ps ON p.product_id = ps.product_id
        WHERE c.customer_id = :customer_id
        GROUP BY c.cart_id, c.product_id, c.quantity, p.product_name, ps.product_sizes_id
    ";

    $stmt = $pdo->prepare($cartSql);
    $stmt->execute(['customer_id' => $customerId]);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$cartItems) {
        send_error("Cart is empty.", 400);
    }

   
    // Calculate totals
    $subtotal = 0;
    $orderSummaryItems = [];

    foreach ($cartItems as $item) {
        $lineTotal = $item['price'] * $item['quantity'];
        $subtotal += $lineTotal;

        $orderSummaryItems[] = [
            "product_name" => $item['product_name'],
            "quantity"     => (int)$item['quantity'],
            "price"        => (float)$item['price'],
            "line_total"   => round($lineTotal, 2)
        ];
    }

    // 
    $shippingFee = 4.95;

    $tax   = round($subtotal * 0.10, 2);
    $total = round($subtotal + $shippingFee + $tax, 2);

    // Start transaction
    $pdo->beginTransaction();

    // Insert order
    $orderStmt = $pdo->prepare("
        INSERT INTO orders (customer_id, order_date, status)
        VALUES (:customer_id, CURDATE(), 'order placed')
    ");
    $orderStmt->execute([
        'customer_id' => $customerId
    ]);

    $orderId = $pdo->lastInsertId();

    // Insert order items
    $itemStmt = $pdo->prepare("
        INSERT INTO order_items (order_id, product_sizes_id, quantity, discount, price)
        VALUES (:order_id, :product_sizes_id, :quantity, 0, :price)
    ");

    foreach ($cartItems as $item) {
        $itemStmt->execute([
            'order_id'         => $orderId,
            'product_sizes_id' => $item['product_sizes_id'],
            'quantity'         => $item['quantity'],
            'price'            => $item['price']
        ]);
    }

    // Insert shipping details
    $shippingStmt = $pdo->prepare("
        INSERT INTO shipping_details
        (order_id, first_name, last_name, street_address, city, state, zip)
        VALUES
        (:order_id, :first_name, :last_name, :street_address, :city, :state, :zip)
    ");

    $shippingStmt->execute([
        'order_id'       => $orderId,
        'first_name'     => $shipping['first_name'],
        'last_name'      => $shipping['last_name'],
        'street_address' => $shipping['street_address'],
        'city'           => $shipping['city'],
        'state'          => $shipping['state'],
        'zip'            => $shipping['zip']
    ]);

// Insert payment
   if (!preg_match('/^\d{1,2}\/\d{2}$/', $payment['expiration_date'])) {
       throw new Exception("Invalid expiration date format. Use MM/YY");
   }

   $exp = $payment['expiration_date'];
   list($month, $year) = explode('/', $exp);

   $month = str_pad($month, 2, '0', STR_PAD_LEFT);

   $year = "20" . $year;

   $formatted_exp = "$year-$month-01";

   $paymentStmt = $pdo->prepare("
       INSERT INTO payments
       (customer_id, order_id, card_number, expiration_date, cvv, payment_status)
       VALUES
       (:customer_id, :order_id, :card_number, :expiration_date, :cvv, 'completed')
   ");

   $paymentStmt->execute([
       'customer_id'     => $customerId,
       'order_id'        => $orderId,
       'card_number'     => $payment['card_number'],
       'expiration_date' => $formatted_exp,
       'cvv'             => $payment['cvv']
   ]);

    // Clear cart
    $clearStmt = $pdo->prepare("DELETE FROM cart WHERE customer_id = :customer_id");
    $clearStmt->execute([
        'customer_id' => $customerId
    ]);

    $pdo->commit();

    // Response
    send_json([
        "success" => true,
        "order_id" => (int)$orderId,
        "items" => $orderSummaryItems,
        "summary" => [
            "subtotal" => round($subtotal, 2),
            "shipping" => $shippingFee,
            "tax"      => $tax,
            "total"    => $total
        ]
    ], 201);

} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    send_error("Checkout failed: " . $e->getMessage(), 500);
}
