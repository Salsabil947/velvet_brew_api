<?php
// ============================================================
// functions.php — Dashboard Query Functions
// ============================================================

// 1. getTotalRevenue($conn)
// ============================================================
// WHAT IT DOES:
//   Adds up all the total_price values from every order item.
//   total_price is already calculated in the DB as:
//       (price × quantity) − discount
//
// RETURNS: array with key 'total_revenue'  e.g. ['total_revenue' => 14280.00]
//
// SIMPLE EXPLANATION:
//   SUM = add everything together.
//   We go through every row in order_items and add up total_price.
// ============================================================
function getTotalRevenue($conn) {
    $sql = "SELECT SUM(total_price) AS total_revenue
            FROM order_items";

    $result = $conn->query($sql);
    $row    = $result->fetch_assoc();

    // If there are no orders yet, return 0 instead of NULL
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
//
// SIMPLE EXPLANATION:
//   COUNT(*) = count the number of rows.
//   CURDATE() = today's date (MySQL gives us this automatically).
//   WHERE order_date = CURDATE() means "only rows from today".
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
//
// SIMPLE EXPLANATION:
//   The inner query (subquery) adds up total_price per order.
//   The outer query takes the average of those sums.
//   This is different from AVG(price) which would average item prices — wrong!
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
//
// SIMPLE EXPLANATION:
//   JOIN connects three tables together:
//     order_items → product_sizes → products
//   We need this chain because order_items only stores product_sizes_id,
//   not the product name directly.
//   SUM(quantity) = total units sold per product
//   GROUP BY = group rows by product so we can sum each one separately
//   ORDER BY total DESC = put the highest number first
//   LIMIT 1 = only take the top result
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
// 5. getRecentOrders($conn)
// ============================================================
// WHAT IT DOES:
//   Gets the 5 most recent orders with all the info shown
//   in the dashboard table:
//     - Order ID
//     - Customer full name
//     - Items summary (e.g. "2x Midnight Roast, 1x Espresso")
//     - Status
//     - Total amount
//
// RETURNS: array of rows, each row is one order
//
// SIMPLE EXPLANATION:
//   LEFT JOIN = like JOIN but keeps the order row even if
//               there are no matching items (safer than INNER JOIN).
//   GROUP_CONCAT = combine multiple item names into one string
//                  separated by ", "
//                  e.g. "2x Midnight Roast, 1x Espresso"
//   CONCAT = stick two strings together
//            CONCAT(oi.quantity, 'x ', p.product_name)
//            gives us "2x Midnight Roast"
//   GROUP BY = we group by order so all items of the same order
//              are combined into one row
//   ORDER BY order_date DESC = newest orders first
//   LIMIT 5 = only the last 5
// ============================================================
function getRecentOrders($conn) {
    $sql = "SELECT
                o.order_id,
                CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
                o.status,
                GROUP_CONCAT(
                    CONCAT(oi.quantity, 'x ', p.product_name)
                    ORDER BY p.product_name
                    SEPARATOR ', '
                ) AS items,
                SUM(oi.total_price) AS amount
            FROM orders AS o
            JOIN customers     AS c  ON c.customer_id       = o.customer_id
            LEFT JOIN order_items   AS oi ON oi.order_id         = o.order_id
            LEFT JOIN product_sizes AS ps ON ps.product_sizes_id = oi.product_sizes_id
            LEFT JOIN products      AS p  ON p.product_id        = ps.product_id
            GROUP BY o.order_id, customer_name, o.status, o.order_date
            ORDER BY o.order_date DESC
            LIMIT 5";

    // FIX: Changed INNER JOIN to LEFT JOIN on order_items, product_sizes, products
    // so orders with no items are NOT silently dropped from the dashboard.

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
//   Also flags products that are low on stock using the
//   low_stock_threshold column we added to the inventory table.
//
// RETURNS: array of rows, each row has:
//   - product_name
//   - quantity
//   - unit  (Kg or pcs)
//   - low_stock_threshold
//   - is_low_stock  (1 = yes, 0 = no)  ← use this for the red badge
//
// SIMPLE EXPLANATION:
//   JOIN = connect inventory to products to get the product name.
//   CASE WHEN = like an if/else in PHP, but inside SQL.
//     If quantity <= low_stock_threshold → is_low_stock = 1
//     Otherwise                         → is_low_stock = 0
//   ORDER BY quantity ASC = show lowest stock first (most urgent at top)
// ============================================================
function getInventoryStatus($conn) {
    $sql = "SELECT
                p.product_name,
                i.quantity,
                i.unit,
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
//
// SIMPLE EXPLANATION:
//   YEAR(o.order_date) = the year part of the date
//   MONTH(o.order_date) = the month part of the date
//   YEAR(CURDATE()) / MONTH(CURDATE()) = this month right now
//   We calculate last month carefully because January - 1 = December
//   of the previous year (MySQL handles this with DATE_FORMAT tricks).
//
//   % change formula:
//     ((this - last) / last) * 100
// ============================================================
function getRevenueTrend($conn) {

    // --- This month's revenue ---
    $sql_this = "SELECT SUM(oi.total_price) AS revenue
                 FROM order_items AS oi
                 JOIN orders AS o ON o.order_id = oi.order_id
                 WHERE YEAR(o.order_date)  = YEAR(CURDATE())
                   AND MONTH(o.order_date) = MONTH(CURDATE())";

    // --- Last month's revenue ---
    // DATE_SUB(CURDATE(), INTERVAL 1 MONTH) moves back exactly 1 month
    $sql_last = "SELECT SUM(oi.total_price) AS revenue
                 FROM order_items AS oi
                 JOIN orders AS o ON o.order_id = oi.order_id
                 WHERE YEAR(o.order_date)  = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                   AND MONTH(o.order_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";

    $this_result = $conn->query($sql_this)->fetch_assoc();
    $last_result = $conn->query($sql_last)->fetch_assoc();

    $this_month = (float)($this_result['revenue'] ?? 0);
    $last_month = (float)($last_result['revenue'] ?? 0);

    // Calculate % change (avoid divide by zero)
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
//
// SIMPLE EXPLANATION:
//   DATE_SUB(CURDATE(), INTERVAL 1 DAY) = yesterday's date
//   Same % change formula as getRevenueTrend().
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

    // Calculate % change (avoid divide by zero)
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