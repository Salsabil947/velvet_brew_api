<?php
// ============================================================
// functions_dashboard.php — Dashboard Query Functions
// ============================================================

// ============================================================
// 1. getTotalRevenue($conn)
// ============================================================
// WHAT IT DOES:
//   Adds up all the total_price values from every order item.
//   total_price is already calculated in the DB as:
//       (price × quantity) − discount
//
// RETURNS: array with key 'total_revenue'  e.g. ['total_revenue' => 14280.00]
// ============================================================
function getTotalRevenue($conn) {
    $sql = "SELECT SUM(total_price) AS total_revenue
            FROM order_items";

    $result = $conn->query($sql);
    $row    = $result->fetch_assoc();

    return [
        'total_revenue' => $row['total_revenue'] ?? 0
    ];
}


// ============================================================
// 2. getOrdersToday($conn)
// ============================================================
// WHAT IT DOES:
//   Counts how many orders were placed today.
//
// RETURNS: array with key 'orders_today'  e.g. ['orders_today' => 3]
// ============================================================
function getOrdersToday($conn) {
    $sql = "SELECT COUNT(*) AS orders_today
            FROM orders
            WHERE order_date = CURDATE()";

    $result = $conn->query($sql);
    $row    = $result->fetch_assoc();

    return [
        'orders_today' => $row['orders_today'] ?? 0
    ];
}


// ============================================================
// 3. getAverageOrderValue($conn)
// ============================================================
// WHAT IT DOES:
//   Calculates the average amount spent per ORDER (not per item).
//   Step 1: Add up all items for each order  → order total
//   Step 2: Take the average of those totals → average order value
//
// RETURNS: array with key 'avg_order_value'  e.g. ['avg_order_value' => 42.50]
// ============================================================
function getAverageOrderValue($conn) {
    $sql = "SELECT AVG(order_total) AS avg_order_value
            FROM (
                SELECT SUM(total_price) AS order_total
                FROM order_items
                GROUP BY order_id
            ) AS per_order_totals";

    $result = $conn->query($sql);
    $row    = $result->fetch_assoc();

    return [
        'avg_order_value' => round($row['avg_order_value'] ?? 0, 2)
    ];
}


// ============================================================
// 4. getTopRoast($conn)
// ============================================================
// WHAT IT DOES:
//   Finds the product that has been ordered the most (by quantity).
//
// RETURNS: array with key 'product_name'  e.g. ['product_name' => 'Dark Sumatra']
// ============================================================
function getTopRoast($conn) {
    $sql = "SELECT
                p.product_name,
                SUM(oi.quantity) AS total_sold
            FROM order_items AS oi
            JOIN product_sizes AS ps ON ps.product_sizes_id = oi.product_sizes_id
            JOIN products      AS p  ON p.product_id        = ps.product_id
            GROUP BY p.product_id, p.product_name
            ORDER BY total_sold DESC
            LIMIT 1";

    $result = $conn->query($sql);
    $row    = $result->fetch_assoc();

    return [
        'product_name' => $row['product_name'] ?? 'N/A',
        'total_sold'   => $row['total_sold']   ?? 0
    ];
}


// ============================================================
// 5. getRecentOrders($conn, $limit)
// ============================================================
// WHAT IT DOES:
//   Gets the most recent orders for the dashboard table.
//
// FIX: The orders.status ENUM in the database uses 'order placed'
//      but the UI and api_dashboard.php use 'pending'.
//      We map 'order placed' → 'pending' here so the UI badge
//      shows the correct label and the status filter still works.
//
// RETURNS: array of order rows
// ============================================================
function getRecentOrders($conn, $limit = 5) {

    // Validate and clamp the limit
    $limit = (int) $limit;
    if ($limit <= 0) $limit = 5;
    if ($limit > 100) $limit = 100;

    // FIX: Added CASE to map 'order placed' → 'pending' so the
    //      UI status badge matches what the frontend expects.
    //      The rest of the statuses already match.
    $sql = "SELECT
                o.order_id,
                CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
                CASE o.status
                    WHEN 'order placed' THEN 'pending'
                    ELSE o.status
                END AS status,
                GROUP_CONCAT(
                    CONCAT(oi.quantity, 'x ', p.product_name)
                    ORDER BY p.product_name
                    SEPARATOR ', '
                ) AS items,
                SUM(oi.total_price) AS amount
            FROM orders AS o
            JOIN customers      AS c  ON c.customer_id       = o.customer_id
            LEFT JOIN order_items   AS oi ON oi.order_id         = o.order_id
            LEFT JOIN product_sizes AS ps ON ps.product_sizes_id = oi.product_sizes_id
            LEFT JOIN products      AS p  ON p.product_id        = ps.product_id
            GROUP BY o.order_id, customer_name, o.status, o.order_date
            ORDER BY o.order_date DESC
            LIMIT $limit";

    $result = $conn->query($sql);
    $orders = [];

    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }

    return $orders;
}


// ============================================================
// 6. getInventoryStatus($conn)
// ============================================================
// WHAT IT DOES:
//   Gets inventory levels for all products.
//   Also flags products that are low on stock.
//
// FIX: The inventory.unit ENUM in the DB uses 'Kg' (capital K)
//      but the UI shows 'kg' (lowercase).
//      We use LOWER(i.unit) so it always returns 'kg' or 'pcs'.
//
// RETURNS: array of rows, each row has:
//   - product_name
//   - quantity
//   - unit          (now always lowercase: 'kg' or 'pcs')
//   - low_stock_threshold
//   - is_low_stock  (1 = yes, 0 = no)
// ============================================================
function getInventoryStatus($conn) {
    $sql = "SELECT
                p.product_name,
                i.quantity,
                LOWER(i.unit) AS unit,
                i.low_stock_threshold,
                CASE
                    WHEN i.quantity <= i.low_stock_threshold THEN 1
                    ELSE 0
                END AS is_low_stock
            FROM inventory AS i
            JOIN products  AS p ON p.product_id = i.product_id
            ORDER BY i.quantity ASC";

    $result = $conn->query($sql);

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }

    return $items;
}


// ============================================================
// 7. getRevenueTrend($conn)
// ============================================================
// WHAT IT DOES:
//   Calculates the revenue % change between this month and last month.
//   Used for the "+12.5%" badge on the Total Revenue card.
//
// RETURNS: array with:
//   - this_month   (total revenue this month)
//   - last_month   (total revenue last month)
//   - trend_pct    (percentage change, e.g. 12.5 or -3.2)
//   - trend_label  (e.g. "+12.5%" or "-3.2%")
// ============================================================
function getRevenueTrend($conn) {

    $sql_this = "SELECT SUM(oi.total_price) AS revenue
                 FROM order_items AS oi
                 JOIN orders AS o ON o.order_id = oi.order_id
                 WHERE YEAR(o.order_date)  = YEAR(CURDATE())
                   AND MONTH(o.order_date) = MONTH(CURDATE())";

    $sql_last = "SELECT SUM(oi.total_price) AS revenue
                 FROM order_items AS oi
                 JOIN orders AS o ON o.order_id = oi.order_id
                 WHERE YEAR(o.order_date)  = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                   AND MONTH(o.order_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";

    $this_result = $conn->query($sql_this)->fetch_assoc();
    $last_result = $conn->query($sql_last)->fetch_assoc();

    $this_month = (float)($this_result['revenue'] ?? 0);
    $last_month = (float)($last_result['revenue'] ?? 0);

    if ($last_month > 0) {
        $trend_pct = (($this_month - $last_month) / $last_month) * 100;
    } else {
        $trend_pct = 0;
    }

    $trend_pct   = round($trend_pct, 1);
    $trend_label = ($trend_pct >= 0 ? '+' : '') . $trend_pct . '%';

    return [
        'this_month'  => $this_month,
        'last_month'  => $last_month,
        'trend_pct'   => $trend_pct,
        'trend_label' => $trend_label
    ];
}


// ============================================================
// 8. getOrdersTrend($conn)
// ============================================================
// WHAT IT DOES:
//   Calculates the orders % change between today and yesterday.
//   Used for the "+4.2%" badge on the Orders Today card.
//
// RETURNS: array with:
//   - today         (orders today)
//   - yesterday     (orders yesterday)
//   - trend_pct     (percentage change)
//   - trend_label   (e.g. "+4.2%")
// ============================================================
function getOrdersTrend($conn) {

    $sql_today = "SELECT COUNT(*) AS total
                  FROM orders
                  WHERE order_date = CURDATE()";

    $sql_yesterday = "SELECT COUNT(*) AS total
                      FROM orders
                      WHERE order_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";

    $today_result     = $conn->query($sql_today)->fetch_assoc();
    $yesterday_result = $conn->query($sql_yesterday)->fetch_assoc();

    $today     = (int)($today_result['total']     ?? 0);
    $yesterday = (int)($yesterday_result['total'] ?? 0);

    if ($yesterday > 0) {
        $trend_pct = (($today - $yesterday) / $yesterday) * 100;
    } else {
        $trend_pct = 0;
    }

    $trend_pct   = round($trend_pct, 1);
    $trend_label = ($trend_pct >= 0 ? '+' : '') . $trend_pct . '%';

    return [
        'today'       => $today,
        'yesterday'   => $yesterday,
        'trend_pct'   => $trend_pct,
        'trend_label' => $trend_label
    ];
}