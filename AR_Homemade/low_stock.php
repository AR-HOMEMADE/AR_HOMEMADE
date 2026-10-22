<?php

require_once "role_check.php";
require_role(['Administrator']);

require_once "db.php";

$query = "
    SELECT
        id,
        ingredient_name AS product_name,
        quantity_in_stock AS stock,
        low_stock_level,
        unit
    FROM ingredients
    WHERE quantity_in_stock <= low_stock_level
    ORDER BY quantity_in_stock ASC
";

$result = $conn->query($query);

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Low Stock Alerts - AR Homemade</title>

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
            <a href="products.php">Menu Items</a>
            <a href="inventory.php">Ingredients & Recipes</a>
            <a href="reports.php">Reports</a>
            <a href="users.php">Users</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
</div>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h1>Low Stock Alerts</h1>

            <p class="text-muted">
                Raw ingredients that need attention because their stock is low.
            </p>

        </div>

        <a
            href="index.php"
            class="btn btn-secondary"
        >
            Dashboard
        </a>

    </div>

    <?php if ($result->num_rows > 0): ?>

        <div class="alert alert-warning">

            <strong>Warning!</strong>

            The following products have reached or fallen below
            their low-stock level.

        </div>

        <div class="card">

            <div class="card-header">

                <strong>
                    Products Requiring Attention
                </strong>

            </div>

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-bordered table-striped">

                        <thead class="table-dark">

                            <tr>

                                <th>ID</th>

                                <th>Product</th>

                                <th>Price</th>

                                <th>Current Stock</th>

                                <th>Low Stock Level</th>

                                <th>Status</th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php while ($row = $result->fetch_assoc()): ?>

                            <tr>

                                <td>
                                    <?php echo $row['id']; ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($row['product_name']); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($row['unit']); ?>
                                </td>

                                <td>
                                    <strong>
                                        <?php echo $row['stock']; ?>
                                    </strong>
                                </td>

                                <td>
                                    <?php echo $row['low_stock_level']; ?>
                                </td>

                                <td>

                                    <span class="badge bg-danger">
                                        LOW STOCK
                                    </span>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    <?php else: ?>

        <div class="alert alert-success">

            <strong>All good!</strong>

            No ingredients are currently at or below their low-stock level.

        </div>

    <?php endif; ?>

</div>

</body>

</html>
