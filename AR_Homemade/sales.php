<?php
require_once "db.php";
require_once "auth.php";
require_once "role_check.php";

require_role(['Administrator']);
/* GET SALES */

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($search != "") {

    $search_id = intval($search);
    $search_term = "%" . $search . "%";

    $stmt = $conn->prepare(
        "SELECT
            sales.id,
            sales.order_id,
            sales.sale_date,
            sales.amount,
            users.fullname,
            users.username
         FROM sales
         INNER JOIN orders
            ON sales.order_id = orders.id
         INNER JOIN users
            ON orders.user_id = users.id
         WHERE sales.order_id = ?
            OR users.fullname LIKE ?
            OR users.username LIKE ?
         ORDER BY sales.id DESC"
    );

    $stmt->bind_param("iss", $search_id, $search_term, $search_term);

    $stmt->execute();

    $sales = $stmt->get_result();

} else {

    $sales = $conn->query(
        "SELECT
            sales.id,
            sales.order_id,
            sales.sale_date,
            sales.amount,
            users.fullname,
            users.username
         FROM sales
         INNER JOIN orders
            ON sales.order_id = orders.id
         INNER JOIN users
            ON orders.user_id = users.id
         ORDER BY sales.id DESC"
    );
}

$sales_query = "
    SELECT
        s.id,
        s.order_id,
        u.fullname,
        s.sale_date,
        s.amount
    FROM sales s
    LEFT JOIN orders o ON s.order_id = o.id
    LEFT JOIN users u ON o.user_id = u.id
    ORDER BY s.id DESC
";

$sales_result = $conn->query($sales_query);

$total_sales_query = "
    SELECT COALESCE(SUM(amount), 0) AS total_sales
    FROM sales
";

$total_sales_result = $conn->query($total_sales_query);

$total_sales = $total_sales_result->fetch_assoc()['total_sales'];

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sales - AR Homemade</title>

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
            <a href="reports.php">Reports</a>
            <a href="users.php">Users</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
</div>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h1>Sales Tracking</h1>

            <p class="text-muted">
                View completed sales and total sales amount.
            </p>

        </div>

        <a
            href="index.php"
            class="btn btn-secondary"
        >
            Dashboard
        </a>

    </div>

    <!-- TOTAL SALES -->

    <div class="card mb-4">

        <div class="card-body">

            <h5 class="card-title">
                Total Sales
            </h5>

            <h2>
                ₱<?php echo number_format($total_sales, 2); ?>
            </h2>

        </div>

    </div>

    <!-- SALES TABLE -->

    <div class="card">

        <div class="card-header">

    <div class="d-flex justify-content-between align-items-center">

        <strong>Sales History</strong>

        <form method="GET" class="d-flex">

            <input
                type="text"
                name="search"
                class="form-control form-control-sm me-2"
                placeholder="Search Order ID or Customer..."
                value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
            >

            <button type="submit" class="btn btn-primary btn-sm">
                Search
            </button>

            <?php if (isset($_GET['search']) && $_GET['search'] != ""): ?>

                <a href="sales.php" class="btn btn-secondary btn-sm ms-2">
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

                            <th>Sale ID</th>

                            <th>Order ID</th>

                            <th>Customer</th>

                            <th>Date</th>

                            <th>Amount</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if ($sales_result->num_rows > 0): ?>

                        <?php while ($row = $sales_result->fetch_assoc()): ?>

                            <tr>

                                <td>
                                    <?php echo $row['id']; ?>
                                </td>

                                <td>
                                    #<?php echo $row['order_id']; ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($row['fullname']); ?>
                                </td>

                                <td>
                                    <?php echo $row['sale_date']; ?>
                                </td>

                                <td>
                                    ₱<?php echo number_format($row['amount'], 2); ?>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td
                                colspan="5"
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

</div>

</body>

</html>
