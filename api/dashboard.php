<?php
// ============================================================
// dashboard.php — JSON API Endpoint for Frontend Integration
// ============================================================
// HOW TO USE:
//   GET  /dashboard.php?action=total_revenue
//   GET  /dashboard.php?action=orders_today
//   GET  /dashboard.php?action=avg_order_value
//   GET  /dashboard.php?action=top_roast
//   GET  /dashboard.php?action=recent_orders
//   GET  /dashboard.php?action=recent_orders&status=completed
//   GET  /dashboard.php?action=inventory
//   GET  /dashboard.php?action=inventory&filter=low_stock
//   GET  /dashboard.php?action=revenue_trend
//   GET  /dashboard.php?action=orders_trend
//   POST /dashboard.php?action=update_order_status body: { order_id, status }
// ============================================================

require_once '../config/db-db.php';
require_once 'functions.php';

// --- Always respond with JSON ---
header('Content-Type: application/json');

// --- Allow frontend dev server (CORS) ---
// FIX: Change this to your actual frontend URL before going live
// e.g. header('Access-Control-Allow-Origin: http://yourdomain.com');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

// FIX: Register connection close to always run, even if exit is called mid-route
register_shutdown_function(function() use ($conn) {
    if ($conn) $conn->close();
});

// Handle preflight OPTIONS request (browser sends this before POST)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// --- Read the action from query string ---
$action = $_GET['action'] ?? '';

// ============================================================
// ROUTE: GET requests
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    switch ($action) {

        // --- Stat Card 1: Total Revenue ---
        case 'total_revenue':
            echo json_encode(getTotalRevenue($conn));
            break;

        // --- Stat Card 2: Orders Today ---
        case 'orders_today':
            echo json_encode(getOrdersToday($conn));
            break;

        // --- Stat Card 3: Average Order Value ---
        case 'avg_order_value':
            echo json_encode(getAverageOrderValue($conn));
            break;

        // --- Stat Card 4: Top Roast ---
        case 'top_roast':
            echo json_encode(getTopRoast($conn));
            break;

        // --- Recent Orders Table ---
        // Optional ?status=completed filter
        case 'recent_orders':
            $orders = getRecentOrders($conn);

            // Optional status filter (for the status badge tabs)
            $status_filter = strtolower($_GET['status'] ?? '');
            if ($status_filter !== '') {
                $orders = array_filter($orders, function($o) use ($status_filter) {
                    return strtolower($o['status']) === $status_filter;
                });
                $orders = array_values($orders); // re-index array
            }

            echo json_encode($orders);
            break;

        // --- Inventory Status Sidebar ---
        // Optional ?filter=low_stock
        case 'inventory':
            $items = getInventoryStatus($conn);

            // Optional filter for low stock items only (the red badge items)
            $filter = strtolower($_GET['filter'] ?? '');
            if ($filter === 'low_stock') {
                $items = array_filter($items, fn($i) => $i['is_low_stock'] == 1);
                $items = array_values($items);
            }

            echo json_encode($items);
            break;

        // --- Trend Badge on Revenue Card (+12.5%) ---
        case 'revenue_trend':
            echo json_encode(getRevenueTrend($conn));
            break;

        // --- Trend Badge on Orders Card (+4.2%) ---
        case 'orders_trend':
            echo json_encode(getOrdersTrend($conn));
            break;

        // --- Unknown action ---
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action: ' . htmlspecialchars($action)]);
            break;
    }

// ============================================================
// ROUTE: POST requests
// ============================================================
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Read JSON body sent by the frontend
    $body = json_decode(file_get_contents('php://input'), true);

    // If body is empty or not valid JSON
    if ($body === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or empty JSON body']);
        exit;
    }

    switch ($action) {

        // --- Update Order Status (the clickable badge in the orders table) ---
        case 'update_order_status':
            if (empty($body['order_id']) || empty($body['status'])) {
                http_response_code(422);
                echo json_encode(['error' => 'Missing required fields: order_id, status']);
                break;
            }

            $valid_statuses = ['pending', 'roasting', 'in_route', 'completed', 'cancelled'];
            $status         = strtolower($body['status']);

            if (!in_array($status, $valid_statuses)) {
                http_response_code(422);
                echo json_encode([
                    'error'          => 'Invalid status value',
                    'allowed_values' => $valid_statuses
                ]);
                break;
            }

            $order_id = (int) $body['order_id'];
            $stmt     = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
            $stmt->bind_param('si', $status, $order_id);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                http_response_code(404);
                echo json_encode(['error' => "Order #$order_id not found"]);
            } else {
                echo json_encode([
                    'success'  => true,
                    'order_id' => $order_id,
                    'status'   => $status
                ]);
            }

            $stmt->close();
            break;

        // --- Unknown POST action ---
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action: ' . htmlspecialchars($action)]);
            break;
    }

} else {
    // Method not allowed
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use GET or POST.']);
}