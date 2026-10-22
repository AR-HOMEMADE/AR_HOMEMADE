<?php
require_once "db.php";
require_once "auth.php";
require_once "role_check.php";

require_role(['Administrator']);

/* TOTAL SALES */

$sales_query = "
    SELECT COALESCE(SUM(amount), 0) AS total_sales
    FROM sales
";

$sales_result = $conn->query($sales_query);
$total_sales = $sales_result->fetch_assoc()['total_sales'];

/* TOTAL ORDERS */

$orders_query = "
    SELECT COUNT(*) AS total_orders
    FROM orders
";

$orders_result = $conn->query($orders_query);
$total_orders = $orders_result->fetch_assoc()['total_orders'];

/* COMPLETED ORDERS */

$completed_query = "
    SELECT COUNT(*) AS completed_orders
    FROM orders
    WHERE status = 'Completed'
";

$completed_result = $conn->query($completed_query);
$completed_orders = $completed_result->fetch_assoc()['completed_orders'];

/* TOTAL PRODUCTS */

$products_query = "
    SELECT COUNT(*) AS total_products
    FROM products
";

$products_result = $conn->query($products_query);
$total_products = $products_result->fetch_assoc()['total_products'];

/* LOW STOCK PRODUCTS */

$low_stock_query = "
    SELECT COUNT(*) AS low_stock
    FROM products
    WHERE stock <= low_stock_level
";

$low_stock_result = $conn->query($low_stock_query);
$low_stock = $low_stock_result->fetch_assoc()['low_stock'];

/* SALES BY DATE */

$search_date = isset($_GET['search_date']) ? $_GET['search_date'] : '';

if ($search_date != "") {

    $stmt = $conn->prepare(
        "SELECT
            DATE(sale_date) AS sale_day,
            SUM(amount) AS daily_total
         FROM sales
         WHERE DATE(sale_date) = ?
         GROUP BY DATE(sale_date)
         ORDER BY sale_day DESC"
    );

    $stmt->bind_param("s", $search_date);

    $stmt->execute();

    $daily_sales_result = $stmt->get_result();

} else {

    $daily_sales_query = "
        SELECT
            DATE(sale_date) AS sale_day,
            SUM(amount) AS daily_total
        FROM sales
        GROUP BY DATE(sale_date)
        ORDER BY sale_day DESC
    ";

    $daily_sales_result = $conn->query($daily_sales_query);
}

/* LOW STOCK LIST */

$product_search = isset($_GET['product_search'])
    ? trim($_GET['product_search'])
    : '';

if ($product_search != "") {

    $search_term = "%" . $product_search . "%";

    $stmt = $conn->prepare(
        "SELECT
            product_name,
            stock,
            low_stock_level
         FROM products
         WHERE stock <= low_stock_level
         AND product_name LIKE ?
         ORDER BY stock ASC"
    );

    $stmt->bind_param("s", $search_term);

    $stmt->execute();

    $low_products_result = $stmt->get_result();

} else {

    $low_products_query = "
        SELECT
            product_name,
            stock,
            low_stock_level
        FROM products
        WHERE stock <= low_stock_level
        ORDER BY stock ASC
    ";

    $low_products_result = $conn->query($low_products_query);
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

    <title>Reports - AR Homemade</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

<style>
/* Shared AR Homemade navigation styling */
.topbar {
    background: white;
    border-bottom: 1px solid #e5e5e5;
    position: sticky;
    top: 0;
    z-index: 1000;
}
.navbar-inner {
    max-width: 1200px;
    margin: auto;
    padding: 15px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.logo {
    font-size: 25px;
    font-weight: 800;
    color: #d62828;
    text-decoration: none;
}
.nav-links {
    display: flex;
    align-items: center;
    gap: 25px;
}
.nav-links a {
    text-decoration: none;
    color: #333;
    font-weight: 600;
}
.nav-links a:hover {
    color: #d62828;
}
.nav-links a.active {
    background: #d62828;
    color: white !important;
    padding: 9px 18px;
    border-radius: 25px;
}

.cart-link {
    padding: 9px 18px;
}
.navbar-user {
    color: #333;
    font-weight: 600;
    white-space: nowrap;
}
@media (max-width: 800px) {
    .navbar-inner { padding: 12px 15px; }
    .logo { font-size: 21px; }
    .nav-links { gap: 10px; }
    .nav-links a:not(.active) { display: none; }
}
</style>

    <link rel="stylesheet" href="style.css">
</head>

<body class="bg-light">

<div class="topbar">
    <div class="navbar-inner">
        <a href="index.php" class="logo"><img src="images/ar_logo.png" alt="AR Homemade"></a>
        <div class="nav-links">
            <a href="index.php">Dashboard</a>
            <a href="orders.php">Orders</a>
            <a href="products.php">Products</a>
            <a href="inventory.php">Inventory</a>
            <a href="reports.php" class="active">Reports</a>
            <a href="users.php">Users</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
</div>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h1>Reports</h1>

            <p class="text-muted">
                Real-time summary of orders, sales, products, and inventory.
            </p>

        </div>

        <a
            href="index.php"
            class="btn btn-secondary"
        >
            Dashboard
        </a>

    </div>

    <!-- SUMMARY CARDS -->

    <div class="row g-3 mb-4">

        <div class="col-md-4">

            <div class="card">

                <div class="card-body">

                    <h6 class="text-muted">
                        Total Sales
                    </h6>

                    <h2>
                        ₱<?php echo number_format($total_sales, 2); ?>
                    </h2>

                </div>

            </div>

        </div>

        <div class="col-md-4">

            <div class="card">

                <div class="card-body">

                    <h6 class="text-muted">
                        Total Orders
                    </h6>

                    <h2>
                        <?php echo $total_orders; ?>
                    </h2>

                </div>

            </div>

        </div>

        <div class="col-md-4">

            <div class="card">

                <div class="card-body">

                    <h6 class="text-muted">
                        Completed Orders
                    </h6>

                    <h2>
                        <?php echo $completed_orders; ?>
                    </h2>

                </div>

            </div>

        </div>

        <div class="col-md-6">

            <div class="card">

                <div class="card-body">

                    <h6 class="text-muted">
                        Total Products
                    </h6>

                    <h2>
                        <?php echo $total_products; ?>
                    </h2>

                </div>

            </div>

        </div>

        <div class="col-md-6">

            <div class="card">

                <div class="card-body">

                    <h6 class="text-muted">
                        Low Stock Products
                    </h6>

                    <h2>
                        <?php echo $low_stock; ?>
                    </h2>

                </div>

            </div>

        </div>

    </div>

    <!-- DAILY SALES -->

    <div class="card mb-4">
<div class="card-header">

    <div class="d-flex justify-content-between align-items-center">

        <strong>
            Daily Sales
        </strong>

        <form method="GET" class="d-flex align-items-center">

            <input
                type="date"
                name="search_date"
                class="form-control form-control-sm me-2"
                value="<?php echo htmlspecialchars($search_date); ?>"
            >

            <button
                type="submit"
                class="btn btn-primary btn-sm"
            >
                Search
            </button>

            <?php if ($search_date != ""): ?>

                <a
                    href="reports.php"
                    class="btn btn-secondary btn-sm ms-2"
                >
                    Clear
                </a>

            <?php endif; ?>

        </form>

    </div>

</div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-striped">

                    <thead class="table-dark">

                        <tr>

                            <th>Date</th>

                            <th>Total Sales</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if ($daily_sales_result->num_rows > 0): ?>

                        <?php while ($row = $daily_sales_result->fetch_assoc()): ?>

                            <tr>

                                <td>
                                    <?php echo $row['sale_day']; ?>
                                </td>

                                <td>
                                    ₱<?php echo number_format($row['daily_total'], 2); ?>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td
                                colspan="2"
                                class="text-center"
                            >
                                No sales recorded yet.
                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

    <!-- LOW STOCK PRODUCTS -->

    <div class="card mb-4">

       <div class="card-header">

    <div class="d-flex justify-content-between align-items-center">

        <strong>
            Low Stock Report
        </strong>

        <form method="GET" class="d-flex">

            <input
                type="text"
                name="product_search"
                class="form-control form-control-sm me-2"
                placeholder="Search Product..."
                value="<?php echo isset($_GET['product_search']) ? htmlspecialchars($_GET['product_search']) : ''; ?>"
            >

            <button
                type="submit"
                class="btn btn-primary btn-sm"
            >
                Search
            </button>

            <?php if (isset($_GET['product_search']) && $_GET['product_search'] != ""): ?>

                <a
                    href="reports.php"
                    class="btn btn-secondary btn-sm ms-2"
                >
                    Clear
                </a>

            <?php endif; ?>

        </form>

    </div>

</div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered">

                    <thead class="table-dark">

                        <tr>

                            <th>Product</th>

                            <th>Current Stock</th>

                            <th>Low Stock Level</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if ($low_products_result->num_rows > 0): ?>

                        <?php while ($row = $low_products_result->fetch_assoc()): ?>

                            <tr>

                                <td>
                                    <?php echo htmlspecialchars($row['product_name']); ?>
                                </td>

                                <td>
                                    <?php echo $row['stock']; ?>
                                </td>

                                <td>
                                    <?php echo $row['low_stock_level']; ?>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td
                                colspan="3"
                                class="text-center"
                            >
                                No low-stock products.
                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

</body>

</html>
