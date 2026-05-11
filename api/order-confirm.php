<?php
/*
GET /api/order-confirm.php?order_id={order_id}
*/

require_once '../config/cors.php';
require_once '../config/db.php';
require_once '../config/response.php';

set_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_error("Only GET method is allowed.", 405);
}

if (empty($_GET['order_id'])) {
    send_error("order_id is required.", 400);
}

$orderId = (int) $_GET['order_id'];

if ($orderId <= 0) {
    send_error("Invalid order_id.", 400);
}

$pdo = getPDO();

try {

    // Get order + shipping details
    $sql = "
        SELECT
            o.order_id,
            o.status,
            sd.street_address,
            sd.city,
            sd.state,
            sd.zip
        FROM orders o
        LEFT JOIN shipping_details sd 
            ON o.order_id = sd.order_id
        WHERE o.order_id = :order_id
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'order_id' => $orderId
    ]);

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        send_error("Order not found.", 404);
    }

    // Get order items
    $itemsSql = "
        SELECT
            p.product_name,
            oi.quantity,
            oi.price,
            oi.total_price
        FROM order_items oi
        JOIN product_sizes ps 
            ON oi.product_sizes_id = ps.product_sizes_id
        JOIN products p 
            ON ps.product_id = p.product_id
        WHERE oi.order_id = :order_id
    ";

    $stmt = $pdo->prepare($itemsSql);
    $stmt->execute([
        'order_id' => $orderId
    ]);

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $subtotal = 0;
    $formattedItems = [];

    foreach ($items as $item) {

        $lineTotal = (float) $item['total_price'];

        $subtotal += $lineTotal;

        $formattedItems[] = [
            "product_name" => $item['product_name'],
            "quantity"     => (int) $item['quantity'],
            "price"        => (float) $item['price'],
            "line_total"   => $lineTotal
        ];
    }

    // Fixed delivery fee
    $shippingFee = 30;

    $total = $subtotal + $shippingFee;

    // Estimated arrival
    $arrivalMap = [
        'order placed' => '20 - 30 mins',
        'roasting'     => '15 - 25 mins',
        'in route'     => '5 - 10 mins',
        'completed'    => 'Delivered'
    ];

    $estimatedArrival = $arrivalMap[$order['status']] ?? 'Pending';

    // Final Response
    send_json([
        "success" => true,
        "data" => [

            "order_id" => (int) $order['order_id'],

            "status" => $order['status'],

            "estimated_arrival" => $estimatedArrival,

            "summary" => [
                "items_total" => count($formattedItems),
                "subtotal" => round($subtotal, 2),
                "delivery_fee" => round($shippingFee, 2),
                "total" => round($total, 2)
            ],

            "shipping_address" => [
                "street_address" => $order['street_address'],
                "city" => $order['city'],
                "state" => $order['state'],
                "zip" => $order['zip']
            ],

            "items" => $formattedItems
        ]
    ]);

} catch (PDOException $e) {

    send_error("Database error: " . $e->getMessage(), 500);

}
