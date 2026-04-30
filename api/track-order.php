<?php
/* 
GET api/track-order.php?order_id={order_id}
*/ 

require_once '../config/cors.php';
require_once '../config/db.php';
require_once '../config/response.php';

set_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_error('Method not allowed. Use GET.', 405);
}

// ============================================================
// Validate order_id
// ============================================================
if (empty($_GET['order_id'])) {
    send_error('Missing required parameter: order_id.', 400);
}

$orderId = (int) $_GET['order_id'];

if ($orderId <= 0) {
    send_error('Invalid order_id.', 400);
}

try {
    $pdo = getPDO();

    // ========================================================
    // STEP 1: Get order + shipping + customer info
    // ========================================================
    $orderSql = "
        SELECT
            o.order_id,
            o.order_date,
            o.status,
            c.first_name,
            c.last_name,
            sd.street_address,
            sd.city,
            sd.state,
            sd.zip,
            sd.shipping_method
        FROM orders o
        JOIN customers c
            ON o.customer_id = c.customer_id
        LEFT JOIN shipping_details sd
            ON o.order_id = sd.order_id
        WHERE o.order_id = :order_id
        LIMIT 1
    ";

    $stmt = $pdo->prepare($orderSql);
    $stmt->execute([':order_id' => $orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        send_error('Order not found.', 404);
    }

    // ========================================================
    // STEP 2: Get order items
    // ========================================================
    $itemsSql = "
        SELECT
            p.product_name,
            ps.size,
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
    $stmt->execute([':order_id' => $orderId]);
    $rows = $stmt->fetchAll();

    $items = [];
    $subtotal = 0;

    foreach ($rows as $row) {
        $subtotal += $row['total_price'];

        $items[] = [
            'product_name' => $row['product_name'],
            'size'         => $row['size'],
            'quantity'     => (int)$row['quantity'],
            'price'        => (float)$row['price'],
            'total_price'  => (float)$row['total_price']
        ];
    }

    // ========================================================
    // STEP 3: Shipping fee
    // ========================================================
    $shippingFee = ($order['shipping_method'] === 'express brew') ? 12.50 : 4.95;

    $total = $subtotal + $shippingFee;

    // ========================================================
    // STEP 4: Progress steps for UI
    // ========================================================
    $steps = [
        'order placed' => 1,
        'roasting'     => 2,
        'in route'     => 3,
        'completed'    => 4
    ];

    $currentStep = $steps[$order['status']] ?? 1;

    // ========================================================
    // STEP 5: ETA for UI
    // ========================================================
    $etaMap = [
        'order placed' => '25 - 30 mins',
        'roasting'     => '15 - 20 mins',
        'in route'     => '5 - 10 mins',
        'completed'    => 'Delivered'
    ];

    $estimatedArrival = $etaMap[$order['status']] ?? 'Unknown';

    // ========================================================
    // STEP 6: Return response for frontend
    // ========================================================
    send_json([
        'success' => true,
        'data' => [
            'order_id' => (int)$order['order_id'],
            'status' => $order['status'],
            'current_step' => $currentStep,
            'estimated_arrival' => $estimatedArrival,

            'delivery_person' => [
                'name' => $order['first_name'] . ' ' . $order['last_name']
            ],

            'shipping_address' => [
                'street_address' => $order['street_address'],
                'city' => $order['city'],
                'state' => $order['state'],
                'zip' => $order['zip']
            ],

            'items' => $items,

            'subtotal' => round($subtotal, 2),
            'shipping_fee' => round($shippingFee, 2),
            'total' => round($total, 2)
        ]
    ]);

} catch (PDOException $e) {
    send_error('Database error: ' . $e->getMessage(), 500);
}