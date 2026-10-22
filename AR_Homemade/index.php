<?php
require_once "auth.php";
require_once "db.php";
require_once "role_check.php";
require_role(['Administrator']);

$message = "";

/* ==========================================
   ADMIN / STAFF DASHBOARD DATA
   ========================================== */

$dashboard = [
    'orders' => 0,
    'products' => 0,
    'sales' => 0,
    'low_stock' => 0,
    'pending' => 0,
    'processing' => 0,
    'completed' => 0,
    'cancelled' => 0,
    'sales_days' => [],
    'top_products' => [],
    'stock_products' => [],
    'recent_sales' => [],
    'range_start' => '',
    'range_end' => ''
];

if (in_array($_SESSION['role'], ['Administrator', 'Staff'], true)) {

    /*
     * Dashboard date filter.
     * Default: the latest 3 calendar days, which on Oct. 7, 2026
     * gives Oct. 5-7, 2026.
     */
    $range_start = trim($_GET['start_date'] ?? '');
    $range_end   = trim($_GET['end_date'] ?? '');

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $range_start)) {
        $range_start = date('Y-m-d', strtotime('-2 days'));
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $range_end)) {
        $range_end = date('Y-m-d');
    }

    if ($range_start > $range_end) {
        [$range_start, $range_end] = [$range_end, $range_start];
    }

    $dashboard['range_start'] = $range_start;
    $dashboard['range_end'] = $range_end;

    /*
     * Use an exclusive end date so the entire selected end date is included,
     * regardless of the time stored in sale_date/order_date.
     */
    $range_end_exclusive = date('Y-m-d', strtotime($range_end . ' +1 day'));

    /* Overall product count and current low-stock count are not date-based. */
    $q = $conn->query("SELECT COUNT(*) total FROM products");
    $dashboard['products'] = (int)($q->fetch_assoc()['total'] ?? 0);

    $q = $conn->query("SELECT COUNT(*) total FROM ingredients WHERE quantity_in_stock <= low_stock_level");
    $dashboard['low_stock'] = (int)($q->fetch_assoc()['total'] ?? 0);

    /* Selected-period order totals/statuses. */
    $stmt = $conn->prepare("
        SELECT COUNT(*) total
        FROM orders
        WHERE order_date >= ? AND order_date < ?
    ");
    $stmt->bind_param("ss", $range_start, $range_end_exclusive);
    $stmt->execute();
    $dashboard['orders'] = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    $stmt = $conn->prepare("
        SELECT status, COUNT(*) total
        FROM orders
        WHERE order_date >= ? AND order_date < ?
        GROUP BY status
    ");
    $stmt->bind_param("ss", $range_start, $range_end_exclusive);
    $stmt->execute();
    $q = $stmt->get_result();

    while ($r = $q->fetch_assoc()) {
        if ($r['status'] === 'Pending') {
            $dashboard['pending'] = (int)$r['total'];
        } elseif (in_array($r['status'], ['Preparing', 'Ready'], true)) {
            $dashboard['processing'] += (int)$r['total'];
        } elseif ($r['status'] === 'Completed') {
            $dashboard['completed'] = (int)$r['total'];
        } elseif ($r['status'] === 'Cancelled') {
            $dashboard['cancelled'] = (int)$r['total'];
        }
    }
    $stmt->close();

    /* Sales total for the selected period. */
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) total
        FROM sales
        WHERE sale_date >= ? AND sale_date < ?
    ");
    $stmt->bind_param("ss", $range_start, $range_end_exclusive);
    $stmt->execute();
    $dashboard['sales'] = (float)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    /* Sales chart: one bar for every selected calendar day. */
    $days = [];
    $cursor = new DateTime($range_start);
    $last_day = new DateTime($range_end);

    while ($cursor <= $last_day) {
        $key = $cursor->format('Y-m-d');
        $days[$key] = [
            'label' => $cursor->format('M d'),
            'amount' => 0
        ];
        $cursor->modify('+1 day');
    }

    $stmt = $conn->prepare("
        SELECT DATE(sale_date) sale_day, COALESCE(SUM(amount), 0) amount
        FROM sales
        WHERE sale_date >= ? AND sale_date < ?
        GROUP BY DATE(sale_date)
        ORDER BY sale_day
    ");
    $stmt->bind_param("ss", $range_start, $range_end_exclusive);
    $stmt->execute();
    $q = $stmt->get_result();

    while ($r = $q->fetch_assoc()) {
        if (isset($days[$r['sale_day']])) {
            $days[$r['sale_day']]['amount'] = (float)$r['amount'];
        }
    }
    $stmt->close();

    $dashboard['sales_days'] = array_values($days);

    /* Top-selling products during the selected period only. */
    $stmt = $conn->prepare("
        SELECT
            p.product_name,
            p.image,
            SUM(oi.quantity) qty,
            SUM(oi.subtotal) total_sales
        FROM order_items oi
        INNER JOIN products p ON p.id = oi.product_id
        INNER JOIN orders o ON o.id = oi.order_id
        WHERE o.status = 'Completed'
          AND o.order_date >= ?
          AND o.order_date < ?
        GROUP BY p.id, p.product_name, p.image
        ORDER BY qty DESC, total_sales DESC
        LIMIT 5
    ");
    $stmt->bind_param("ss", $range_start, $range_end_exclusive);
    $stmt->execute();
    $q = $stmt->get_result();

    while ($r = $q->fetch_assoc()) {
        $dashboard['top_products'][] = $r;
    }
    $stmt->close();

    /* Inventory is current stock, so it stays independent of the date filter. */
    $q = $conn->query("
        SELECT ingredient_name AS product_name, NULL AS image,
               quantity_in_stock AS stock, low_stock_level
        FROM ingredients
        ORDER BY quantity_in_stock ASC, ingredient_name ASC
        LIMIT 6
    ");

    while ($r = $q->fetch_assoc()) {
        $dashboard['stock_products'][] = $r;
    }

    /* Recent transactions inside the selected period only. */
    $stmt = $conn->prepare("
        SELECT sale_date, order_id, amount
        FROM sales
        WHERE sale_date >= ? AND sale_date < ?
        ORDER BY sale_date DESC
        LIMIT 5
    ");
    $stmt->bind_param("ss", $range_start, $range_end_exclusive);
    $stmt->execute();
    $q = $stmt->get_result();

    while ($r = $q->fetch_assoc()) {
        $dashboard['recent_sales'][] = $r;
    }
    $stmt->close();
}

/* Current low-stock items for the notification bell. */
$low_stock_notifications = [];

if (($_SESSION['role'] ?? '') === 'Administrator') {
    $notification_result = $conn->query("
        SELECT id, ingredient_name AS product_name, quantity_in_stock AS stock, low_stock_level, unit
        FROM ingredients
        WHERE quantity_in_stock <= low_stock_level
        ORDER BY quantity_in_stock ASC, ingredient_name ASC
        LIMIT 10
    ");

    if ($notification_result) {
        while ($row = $notification_result->fetch_assoc()) {
            $low_stock_notifications[] = $row;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>AR Homemade</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>
        body {
            background: #faf7f4;
            font-family: Arial, sans-serif;
            color: #2b1b18;
        }

        .topbar {
            background: rgba(255,255,255,.96);
            border-bottom: 1px solid #eee2dc;
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(12px);
        }

        .navbar-inner {
            max-width: 1200px;
            margin: auto;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 72px;
        }

        .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            line-height: 0;
        }

        .logo img {
            height: 58px;
            width: 58px;
            object-fit: contain;
            display: block;
            border: 0;
            border-radius: 50%;
        }

        .dashboard-filter-panel {
            margin-bottom: 14px;
            padding: 20px 22px;
            background: #fff;
            border: 1px solid #eadfd9;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(143, 29, 29, .07);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .dashboard-filter-panel h3 {
            margin: 0 0 4px;
            font-size: 18px;
            font-weight: 800;
        }

        .dashboard-filter-panel p {
            margin: 0;
            color: #777;
            font-size: 13px;
        }

        .dashboard-filter-form {
            display: flex;
            align-items: end;
            gap: 10px;
            flex-wrap: wrap;
        }

        .dashboard-filter-form label {
            display: block;
            margin-bottom: 5px;
            color: #6d5750;
            font-size: 12px;
            font-weight: 700;
        }

        .dashboard-filter-form input {
            height: 40px;
            border: 1px solid #eadfd9;
            border-radius: 10px;
            padding: 0 10px;
            color: #2b1b18;
            background: #fff;
        }

        .dashboard-filter-form button {
            height: 40px;
            border: 0;
            border-radius: 10px;
            padding: 0 17px;
            background: #d62828;
            color: #fff;
            font-weight: 700;
        }

        .dashboard-filter-form a {
            height: 40px;
            display: flex;
            align-items: center;
            padding: 0 12px;
            border-radius: 10px;
            color: #8f1d1d;
            background: #f8eeea;
            text-decoration: none;
            font-weight: 700;
        }

        .selected-period-label {
            margin: 0 0 18px;
            color: #6d5750;
            font-size: 13px;
        }

        /* ==========================================
           LOW STOCK NOTIFICATION
           ========================================== */

        .admin-topbar-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .admin-navbar-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .notification-wrap,
        .profile-wrap {
            position: relative;
        }

        .profile-btn {
            display: flex;
            align-items: center;
            gap: 9px;
            min-height: 44px;
            padding: 5px 10px 5px 6px;
            border: 1px solid #eadfd9;
            border-radius: 13px;
            background: #fff;
            color: #2b1b18;
            cursor: pointer;
            box-shadow: 0 6px 18px rgba(43, 27, 24, .06);
        }

        .profile-btn:hover {
            background: #fff7f3;
        }

        .profile-avatar {
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: #fff0eb;
            color: #d62828;
            font-size: 12px;
        }

        .profile-avatar.large {
            width: 42px;
            height: 42px;
        }

        .profile-info {
            text-align: left;
            line-height: 1.15;
        }

        .profile-info strong,
        .profile-info small {
            display: block;
        }

        .profile-info strong {
            font-size: 12px;
            max-width: 125px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .profile-info small {
            margin-top: 3px;
            color: #8b7770;
            font-size: 10px;
        }

        .profile-arrow {
            color: #8b7770;
            font-size: 16px;
            margin-left: 2px;
        }

        .profile-dropdown {
            display: none;
            position: absolute;
            top: 52px;
            right: 0;
            width: 235px;
            background: #fff;
            border: 1px solid #eadfd9;
            border-radius: 16px;
            box-shadow: 0 18px 45px rgba(43, 27, 24, .16);
            overflow: hidden;
            z-index: 2000;
        }

        .profile-dropdown.show {
            display: block;
        }

        .profile-dropdown-head {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px;
            background: #fff8f5;
            border-bottom: 1px solid #f0e7e2;
        }

        .profile-dropdown-head strong,
        .profile-dropdown-head small {
            display: block;
        }

        .profile-dropdown-head strong {
            font-size: 12px;
        }

        .profile-dropdown-head small {
            margin-top: 3px;
            color: #8b7770;
            font-size: 10px;
        }

        .profile-dropdown > a {
            display: block;
            padding: 12px 14px;
            color: #4a302b;
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
            border-bottom: 1px solid #f4eeeb;
        }

        .profile-dropdown > a:hover {
            background: #fff7f3;
            color: #d62828;
        }

        .profile-dropdown > a.profile-logout {
            color: #b71c1c;
            border-bottom: 0;
        }

        .notification-wrap {
            position: relative;
        }

        .notification-btn {
            position: relative;
            width: 44px;
            height: 44px;
            border: 1px solid #eadfd9;
            border-radius: 50%;
            background: #fff;
            font-size: 19px;
            cursor: pointer;
            box-shadow: 0 6px 18px rgba(43, 27, 24, .06);
        }

        .notification-btn:hover {
            background: #fff7f3;
        }

        .notification-count {
            position: absolute;
            top: -3px;
            right: -2px;
            min-width: 19px;
            height: 19px;
            padding: 0 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 20px;
            background: #d62828;
            color: #fff;
            border: 2px solid #fff;
            font-size: 10px;
            font-weight: 800;
        }

        .notification-dropdown {
            display: none;
            position: absolute;
            top: 52px;
            right: 0;
            width: 340px;
            background: #fff;
            border: 1px solid #eadfd9;
            border-radius: 16px;
            box-shadow: 0 18px 45px rgba(43, 27, 24, .16);
            overflow: hidden;
            z-index: 2000;
        }

        .notification-dropdown.show {
            display: block;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            padding: 15px 16px;
            border-bottom: 1px solid #f0e7e2;
        }

        .notification-header span {
            min-width: 23px;
            height: 23px;
            padding: 0 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #f8eeea;
            color: #8f1d1d;
            font-size: 11px;
            font-weight: 800;
        }

        .notification-item {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 12px 16px;
            color: #2b1b18;
            text-decoration: none;
            border-bottom: 1px solid #f4eeeb;
        }

        .notification-item:hover {
            background: #fff7f3;
        }

        .notification-item strong,
        .notification-item small {
            display: block;
        }

        .notification-item strong {
            font-size: 13px;
        }

        .notification-item small {
            margin-top: 2px;
            color: #888;
            font-size: 11px;
        }

        .notification-icon {
            width: 30px;
            height: 30px;
            flex: 0 0 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #fff0ed;
            color: #d62828;
            font-weight: 900;
        }

        .notification-empty {
            padding: 22px 16px;
            color: #777;
            text-align: center;
            font-size: 13px;
        }

        .notification-footer {
            display: block;
            padding: 12px 16px;
            color: #8f1d1d;
            background: #faf7f4;
            text-decoration: none;
            font-size: 12px;
            font-weight: 800;
        }

        /* ==========================================
           MOBILE NAVBAR
           ========================================== */

        @media (max-width: 700px) {

            .navbar-inner {
                padding: 12px 15px;
            }

            .logo img {
                width: 48px;
                height: 48px;
            }


            .dashboard-filter-panel {
                align-items: stretch;
                flex-direction: column;
            }

            .dashboard-filter-form {
                align-items: stretch;
            }

            .dashboard-filter-form > div {
                flex: 1 1 140px;
            }

            .dashboard-filter-form input,
            .dashboard-filter-form button,
            .dashboard-filter-form a {
                width: 100%;
            }

            .notification-dropdown {
                position: fixed;
                top: 72px;
                left: 15px;
                right: 15px;
                width: auto;
            }

            .admin-navbar-actions {
                gap: 6px;
            }

            .profile-info,
            .profile-arrow {
                display: none;
            }

            .profile-btn {
                padding-right: 6px;
            }

        }

    </style>
    <link rel="stylesheet" href="style.css">
</head>

<body>

<div class="topbar">
    <div class="navbar-inner">
        <a href="index.php" class="logo"><img src="images/ar_logo.png" alt="AR Homemade"></a>

        
            <div class="admin-navbar-actions">
                <div class="notification-wrap">
                    <button type="button" class="notification-btn" id="notificationButton" aria-label="Low stock notifications">
                        🔔
                        <?php if (count($low_stock_notifications) > 0): ?>
                            <span class="notification-count"><?php echo count($low_stock_notifications); ?></span>
                        <?php endif; ?>
                    </button>

                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <strong>Low Stock Alerts</strong>
                            <span><?php echo count($low_stock_notifications); ?></span>
                        </div>

                        <?php if ($low_stock_notifications): ?>
                            <?php foreach ($low_stock_notifications as $notice): ?>
                                <a href="inventory.php" class="notification-item">
                                    <span class="notification-icon">!</span>
                                    <span>
                                        <strong><?php echo htmlspecialchars($notice['product_name']); ?></strong>
                                        <small><?php echo rtrim(rtrim(number_format((float)$notice['stock'], 3, '.', ''), '0'), '.'); ?> <?php echo htmlspecialchars($notice['unit']); ?> left · Alert level <?php echo rtrim(rtrim(number_format((float)$notice['low_stock_level'], 3, '.', ''), '0'), '.'); ?></small>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="notification-empty">No low stock alerts.</div>
                        <?php endif; ?>

                        <a href="low_stock.php" class="notification-footer">View all low stock items →</a>
                    </div>
                </div>

                <div class="profile-wrap">
                    <button type="button" class="profile-btn" id="profileButton" aria-label="Open profile menu">
                        <span class="profile-avatar">👤</span>
                        <span class="profile-info">
                            <strong><?php echo htmlspecialchars($_SESSION['fullname'] ?? 'User'); ?></strong>
                            <small><?php echo htmlspecialchars($_SESSION['role'] ?? 'Staff'); ?></small>
                        </span>
                        <span class="profile-arrow">⌄</span>
                    </button>

                    <div class="profile-dropdown" id="profileDropdown">
                        <div class="profile-dropdown-head">
                            <span class="profile-avatar large">👤</span>
                            <div>
                                <strong><?php echo htmlspecialchars($_SESSION['fullname'] ?? 'User'); ?></strong>
                                <small><?php echo htmlspecialchars($_SESSION['role'] ?? 'Staff'); ?></small>
                            </div>
                        </div>
                        <a href="change_password.php">👤 Profile / Password</a>
                        <a href="logout.php" class="profile-logout">↪ Logout</a>
                    </div>
                </div>
            </div>
    </div>
</div>


<!-- ==========================================
     ADMIN / STAFF DASHBOARD
     ========================================== -->
<div class="admin-shell">
    <aside class="admin-sidebar">
        <div class="admin-brand">
            <img src="images/ar_logo.png" alt="AR Homemade">
            <div>
                <strong>AR Homemade</strong>
                <span>Order &amp; Inventory System</span>
            </div>
        </div>

        <nav class="admin-nav">
            <a class="admin-nav-item active" href="index.php"><span>⌂</span> Dashboard</a>
            <div class="admin-nav-group">
                <div class="admin-nav-title">Orders</div>
                <a href="orders.php">• View Orders</a>
                <a href="orders.php">• Order Management</a>
            </div>
            <div class="admin-nav-group">
                <div class="admin-nav-title">Inventory</div>
                <a href="inventory.php">• Stock Items</a>
                <a href="products.php">• Add / Manage Product</a>
                <a href="low_stock.php">• Low Stock Alert</a>
            </div>
            <div class="admin-nav-group">
                <div class="admin-nav-title">Sales</div>
                <a href="sales.php">• Sales Records</a>
            </div>
            <div class="admin-nav-group">
                <div class="admin-nav-title">Reports</div>
                <a href="reports.php">• Inventory Report</a>
                <a href="reports.php">• Sales Report</a>
            </div>
            <?php if ($_SESSION['role'] == 'Administrator'): ?>
            <div class="admin-nav-group">
                <div class="admin-nav-title">Settings</div>
                <a href="users.php">• Users</a>
                <a href="change_password.php">• Profile / Password</a>
            </div>
            <?php endif; ?>
        </nav>

        <a class="admin-logout" href="logout.php">↪ <span>Logout</span></a>
    </aside>

    <main class="admin-main">
        <div class="admin-topbar">
            <div>
                <h1>Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars($_SESSION['fullname'] ?? 'Admin'); ?>! Here's what's happening with your business.</p>
            </div>
        </div>

        <div class="dashboard-filter-panel">
            <div>
                <h3>Sales Period</h3>
                <p>Choose the dates you want to see on the dashboard.</p>
            </div>

            <form method="GET" class="dashboard-filter-form">
                <div>
                    <label for="start_date">From</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($dashboard['range_start']); ?>" required>
                </div>

                <div>
                    <label for="end_date">To</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($dashboard['range_end']); ?>" required>
                </div>

                <button type="submit">Apply</button>
                <a href="index.php">Reset</a>
            </form>
        </div>

        <div class="selected-period-label">
            Showing sales and order details from
            <strong><?php echo date('F d, Y', strtotime($dashboard['range_start'])); ?></strong>
            to
            <strong><?php echo date('F d, Y', strtotime($dashboard['range_end'])); ?></strong>
        </div>

        <div class="dashboard-cards">
            <a class="dash-stat stat-orders" href="orders.php"><span class="stat-icon">🛒</span><div><span>Total Orders</span><strong><?php echo $dashboard['orders']; ?></strong><small>All recorded orders</small></div></a>
            <a class="dash-stat stat-products" href="products.php"><span class="stat-icon">▣</span><div><span>Total Products</span><strong><?php echo $dashboard['products']; ?></strong><small>Products in catalog</small></div></a>
            <a class="dash-stat stat-sales" href="sales.php"><span class="stat-icon">₱</span><div><span>Total Sales</span><strong>₱<?php echo number_format($dashboard['sales'], 2); ?></strong><small>Completed sales</small></div></a>
            <a class="dash-stat stat-low" href="low_stock.php"><span class="stat-icon">!</span><div><span>Low Stock Items</span><strong><?php echo $dashboard['low_stock']; ?></strong><small>Need attention</small></div></a>
        </div>

        <div class="dashboard-grid dashboard-middle">
            <section class="dash-panel sales-panel">
                <div class="panel-heading"><div><h3>Sales Overview</h3><p>Completed sales for the selected period</p></div><a href="reports.php">View Report →</a></div>
                <?php
                $maxSales = 1;
                foreach ($dashboard['sales_days'] as $d) $maxSales = max($maxSales, $d['amount']);
                ?>
                <div class="sales-chart">
                    <?php foreach ($dashboard['sales_days'] as $d): $height = $d['amount'] > 0 ? max(8, (int)(($d['amount'] / $maxSales) * 100)) : 4; ?>
                        <div class="chart-col"><div class="chart-value">₱<?php echo number_format($d['amount'], 0); ?></div><div class="chart-bar"><i style="height:<?php echo $height; ?>%"></i></div><span><?php echo htmlspecialchars($d['label']); ?></span></div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="dash-panel status-panel">
                <div class="panel-heading"><div><h3>Orders by Status</h3><p>Current order distribution</p></div></div>
                <?php $statusTotal=max(1,$dashboard['orders']); ?>
                <div class="status-layout">
                    <div class="status-donut" style="--p1:<?php echo ($dashboard['pending']/$statusTotal)*100; ?>%;--p2:<?php echo (($dashboard['pending']+$dashboard['processing'])/$statusTotal)*100; ?>%;--p3:<?php echo (($dashboard['pending']+$dashboard['processing']+$dashboard['completed'])/$statusTotal)*100; ?>%"><div><?php echo $dashboard['orders']; ?><small>Total Orders</small></div></div>
                    <div class="status-list">
                        <div><i class="dot pending"></i>Pending <b><?php echo $dashboard['pending']; ?></b></div>
                        <div><i class="dot processing"></i>Processing <b><?php echo $dashboard['processing']; ?></b></div>
                        <div><i class="dot completed"></i>Completed <b><?php echo $dashboard['completed']; ?></b></div>
                        <div><i class="dot cancelled"></i>Cancelled <b><?php echo $dashboard['cancelled']; ?></b></div>
                    </div>
                </div>
            </section>

            <section class="dash-panel quick-panel">
                <div class="panel-heading"><div><h3>Quick Actions</h3><p>Common system tasks</p></div></div>
                <a href="orders.php" class="quick-action">🛒 <span>Manage Orders</span></a>
                <a href="products.php" class="quick-action">▣ <span>Manage Products</span></a>
                <a href="inventory.php" class="quick-action">▤ <span>View Inventory</span></a>
                <a href="reports.php" class="quick-action">▧ <span>Generate Report</span></a>
            </section>
        </div>

        <div class="dashboard-grid dashboard-bottom">
            <section class="dash-panel top-products-panel">
                <div class="panel-heading"><div><h3>Top Selling Products</h3><p>Based on completed orders in the selected period</p></div></div>
                <div class="dash-table-wrap"><table class="dash-table"><thead><tr><th>Product</th><th>Qty Sold</th><th>Total Sales</th></tr></thead><tbody>
                <?php if ($dashboard['top_products']): foreach ($dashboard['top_products'] as $item): ?>
                    <tr><td><div class="dash-product"><span><?php if (!empty($item['image'])): ?><img src="<?php echo htmlspecialchars(strpos($item['image'],'images/')===0?$item['image']:'uploads/'.basename($item['image'])); ?>" alt=""><?php else: ?><b>🍽</b><?php endif; ?></span><?php echo htmlspecialchars($item['product_name']); ?></div></td><td><?php echo (int)$item['qty']; ?></td><td>₱<?php echo number_format((float)$item['total_sales'],2); ?></td></tr>
                <?php endforeach; else: ?><tr><td colspan="3" class="empty-dash">No completed sales yet.</td></tr><?php endif; ?>
                </tbody></table></div>
            </section>

            <section class="dash-panel stock-panel">
                <div class="panel-heading"><div><h3>Ingredient Stock Levels</h3><p>Raw ingredients with the lowest stock</p></div><a href="inventory.php">View All →</a></div>
                <div class="stock-list">
                <?php foreach ($dashboard['stock_products'] as $item): $stock=(float)$item['stock']; $bar=min(100,max(4,$stock)); $isLow=$stock <= (float)$item['low_stock_level']; ?>
                    <div class="stock-row"><div class="stock-label"><span><?php echo htmlspecialchars($item['product_name']); ?></span><b><?php echo rtrim(rtrim(number_format($stock,3,'.',''),'0'),'.'); ?></b></div><div class="stock-track"><i class="<?php echo $isLow?'low':''; ?>" style="width:<?php echo $bar; ?>%"></i></div></div>
                <?php endforeach; ?>
                </div>
            </section>

            <section class="dash-panel recent-panel">
                <div class="panel-heading"><div><h3>Recent Transactions</h3><p>Latest completed sales</p></div><a href="sales.php">View All →</a></div>
                <div class="dash-table-wrap"><table class="dash-table"><thead><tr><th>Date</th><th>Type</th><th>Order</th><th>Amount</th></tr></thead><tbody>
                <?php if ($dashboard['recent_sales']): foreach ($dashboard['recent_sales'] as $sale): ?>
                    <tr><td><?php echo date('M d, Y', strtotime($sale['sale_date'])); ?></td><td><span class="sale-badge">Sale</span></td><td>Order #<?php echo str_pad((int)$sale['order_id'], 4, '0', STR_PAD_LEFT); ?></td><td>₱<?php echo number_format((float)$sale['amount'],2); ?></td></tr>
                <?php endforeach; else: ?><tr><td colspan="4" class="empty-dash">No transactions yet.</td></tr><?php endif; ?>
                </tbody></table></div>
            </section>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const notificationButton = document.getElementById('notificationButton');
    const notificationDropdown = document.getElementById('notificationDropdown');
    const profileButton = document.getElementById('profileButton');
    const profileDropdown = document.getElementById('profileDropdown');

    if (notificationButton && notificationDropdown) {
        notificationButton.addEventListener('click', function (event) {
            event.stopPropagation();
            notificationDropdown.classList.toggle('show');
            if (profileDropdown) profileDropdown.classList.remove('show');
        });
        notificationDropdown.addEventListener('click', function (event) { event.stopPropagation(); });
    }

    if (profileButton && profileDropdown) {
        profileButton.addEventListener('click', function (event) {
            event.stopPropagation();
            profileDropdown.classList.toggle('show');
            if (notificationDropdown) notificationDropdown.classList.remove('show');
        });
        profileDropdown.addEventListener('click', function (event) { event.stopPropagation(); });
    }

    document.addEventListener('click', function () {
        if (notificationDropdown) notificationDropdown.classList.remove('show');
        if (profileDropdown) profileDropdown.classList.remove('show');
    });
});
</script>
</body>

</html>
