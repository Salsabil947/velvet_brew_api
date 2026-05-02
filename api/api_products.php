<?php
// ============================================================
// api_products.php — Products API Endpoints
// ============================================================
// HOW TO USE:
//   GET  /api_products.php?action=get_products
//   GET  /api_products.php?action=get_product&id=5
//   POST /api_products.php?action=add_product
//        body: { product_name, collection, description, roast_type, price, quantity }
//   POST /api_products.php?action=update_product
//        body: { product_id, product_name, collection, description, roast_type, quantity, low_stock_threshold }
//   POST /api_products.php?action=delete_product
//        body: { product_id }
// ============================================================

require_once '../config/db-db.php';
require_once 'functions_products.php';
set_cors_headers();

// --- Always respond with JSON ---
header('Content-Type: application/json');

// FIX: Change '*' to your actual frontend URL before going live
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

// --- Always close connection when the script finishes ---
register_shutdown_function(function() use ($conn) {
    if ($conn) $conn->close();
});

// --- Handle browser preflight check ---
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// --- Read the action ---
$action = $_GET['action'] ?? '';

// ============================================================
// GET Routes — Read operations (no body needed)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    switch ($action) {

        // --- Returns all products for the catalog grid ---
        case 'get_products':
            echo json_encode(getAllProducts($conn));
            break;

        // --- Returns one product for the Edit form ---
        // Requires ?id=5 in the URL
        case 'get_product':
            $id = (int) ($_GET['id'] ?? 0);

            if ($id <= 0) {
                http_response_code(422);
                echo json_encode(['error' => 'Missing or invalid product id']);
                break;
            }

            $product = getProductById($conn, $id);

            if (!$product) {
                http_response_code(404);
                echo json_encode(['error' => "Product #$id not found"]);
                break;
            }

            echo json_encode($product);
            break;

        // --- Unknown action ---
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action: ' . htmlspecialchars($action)]);
            break;
    }

// ============================================================
// POST Routes — Write operations (require JSON body)
// ============================================================
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Read and parse the JSON body sent by the frontend ---
    $body = json_decode(file_get_contents('php://input'), true);

    if ($body === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or empty JSON body']);
        exit;
    }

    switch ($action) {

        // --- Add Product (the "Add New Roast" form) ---
        case 'add_product':
            $result = addProduct($conn, $body);

            if (!$result['success']) {
                http_response_code(422);
            }

            echo json_encode($result);
            break;

        // --- Edit Product (the "Edit Product Details" form) ---
        case 'update_product':
            if (empty($body['product_id'])) {
                http_response_code(422);
                echo json_encode(['error' => 'Missing product_id']);
                break;
            }

            $result = updateProduct($conn, $body['product_id'], $body);

            if (!$result['success']) {
                http_response_code(422);
            }

            echo json_encode($result);
            break;

        // --- Delete Product (the "Delete Product?" confirm dialog) ---
        case 'delete_product':
            if (empty($body['product_id'])) {
                http_response_code(422);
                echo json_encode(['error' => 'Missing product_id']);
                break;
            }

            $result = deleteProduct($conn, (int) $body['product_id']);

            if (!$result['success']) {
                // 404 if not found, which is what the error message will say
                http_response_code(404);
            }

            echo json_encode($result);
            break;

        // --- Unknown POST action ---
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action: ' . htmlspecialchars($action)]);
            break;
    }

} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use GET or POST.']);
}
