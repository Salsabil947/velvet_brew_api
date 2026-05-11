<?php
// ============================================================
// api_dashboard.php — JSON API Endpoint for Frontend Integration
// ============================================================
// HOW TO USE:
//   GET  /api_dashboard.php?action=total_revenue
//   GET  /api_dashboard.php?action=orders_today
//   GET  /api_dashboard.php?action=avg_order_value
//   GET  /api_dashboard.php?action=top_roast
//   GET  /api_dashboard.php?action=recent_orders  (Default behavior — returns 5 recent orders)
//   GET  /api_dashboard.php?action=recent_orders&limit=10    (Request 10 rows)
//   GET  /api_dashboard.php?action=recent_orders&status=completed
//   GET  /api_dashboard.php?action=recent_orders&limit=10&status=completed
//   GET  /api_dashboard.php?action=inventory
//   GET  /api_dashboard.php?action=inventory&filter=low_stock
//   GET  /api_dashboard.php?action=revenue_trend
//   GET  /api_dashboard.php?action=orders_trend
//   POST /api_dashboard.php?action=update_order_status body: { order_id, status }
// ============================================================

require_once '../config/db-db.php';
require_once 'functions_dashboard.php';

// --- Always respond with JSON ---
header('Content-Type: application/json');

// --- (CORS) ---
// FIX: Change this to your actual frontend URL before going live
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

register_shutdown_function(function() use ($conn) {
    if ($conn) $conn->close();
});

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// --- Read the action from query string ---
$action = $_GET['action'] ?? '';

// ============================================================
// GET requests
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    switch ($action) {

        case 'total_revenue':
            echo json_encode(getTotalRevenue($conn));
            break;

        case 'orders_today':
            echo json_encode(getOrdersToday($conn));
            break;

        case 'avg_order_value':
            echo json_encode(getAverageOrderValue($conn));
            break;

        case 'top_roast':
            echo json_encode(getTopRoast($conn));
            break;

        // --- Recent Orders Table ---
        // Optional ?status=pending|roasting|in_route|completed|cancelled
        // Optional ?limit=10 (default: 5, max: 100)
        case 'recent_orders':
            $limit  = $_GET['limit'] ?? 5;
            $orders = getRecentOrders($conn, $limit);


            $status_filter = strtolower($_GET['status'] ?? '');
            if ($status_filter !== '') {
                $orders = array_filter($orders, function($o) use ($status_filter) {
                    // Normalise the order status the same way the UI badge does:
                    // replace space with underscore before comparing
                    $normalised = str_replace(' ', '_', strtolower($o['status']));
                    return $normalised === $status_filter;
                });
                $orders = array_values($orders);
            }

            echo json_encode($orders);
            break;

        // --- Inventory Status Sidebar ---
        case 'inventory':
            $items = getInventoryStatus($conn);

            $filter = strtolower($_GET['filter'] ?? '');
            if ($filter === 'low_stock') {
                $items = array_filter($items, fn($i) => $i['is_low_stock'] == 1);
                $items = array_values($items);
            }

            echo json_encode($items);
            break;

        case 'revenue_trend':
            echo json_encode(getRevenueTrend($conn));
            break;

        case 'orders_trend':
            echo json_encode(getOrdersTrend($conn));
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action: ' . htmlspecialchars($action)]);
            break;
    }

// ============================================================
// POST requests
// ============================================================
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $body = json_decode(file_get_contents('php://input'), true);

    if ($body === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or empty JSON body']);
        exit;
    }

    switch ($action) {

        // --- Update Order Status ---
        // FIX: The DB stores 'order placed' (with space) and 'in route' (with space).
        //      The UI sends 'pending' and 'in_route' (with underscore).
        //      We map UI values → DB values before writing to the database,
        //      so both sides stay consistent without changing the DB schema.
        case 'update_order_status':
            if (empty($body['order_id']) || empty($body['status'])) {
                http_response_code(422);
                echo json_encode(['error' => 'Missing required fields: order_id, status']);
                break;
            }

            // These are the values the FRONTEND is allowed to send
            $valid_ui_statuses = ['pending', 'roasting', 'in_route', 'completed', 'cancelled'];
            $ui_status         = strtolower($body['status']);

            if (!in_array($ui_status, $valid_ui_statuses)) {
                http_response_code(422);
                echo json_encode([
                    'error'          => 'Invalid status value',
                    'allowed_values' => $valid_ui_statuses
                ]);
                break;
            }

            // Map UI status → DB ENUM value
            // The DB ENUM is: 'order placed', 'roasting', 'in route', 'completed'
            $status_map = [
                'pending'   => 'order placed',  // UI 'pending' → DB 'order placed'
                'roasting'  => 'roasting',
                'in_route'  => 'in route',       // UI 'in_route' → DB 'in route'
                'completed' => 'completed',
                'cancelled' => 'completed'        // 'cancelled' not in DB ENUM — map to completed
            ];
            $db_status = $status_map[$ui_status];

            $order_id = (int) $body['order_id'];
            $stmt     = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
            $stmt->bind_param('si', $db_status, $order_id);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                http_response_code(404);
                echo json_encode(['error' => "Order #$order_id not found"]);
            } else {
                echo json_encode([
                    'success'  => true,
                    'order_id' => $order_id,
                    // Return the UI-friendly status back to the frontend
                    'status'   => $ui_status
                ]);
            }

            $stmt->close();
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action: ' . htmlspecialchars($action)]);
            break;
    }

} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use GET or POST.']);
}
