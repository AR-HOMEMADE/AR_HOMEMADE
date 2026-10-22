<?php

require_once "role_check.php";
require_role(['Administrator']);

require_once "db.php";

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>User Management - AR Homemade</title>

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
            <a href="users.php" class="active">Users</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
</div>

<div class="container mt-4">

    <?php if (isset($_GET['created']) && $_GET['created'] === '1'): ?>
        <div class="alert alert-success" role="status">User account created successfully.</div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">

        <h2>User Management</h2>

        <div class="d-flex gap-2 flex-wrap">
            <a href="add_user.php" class="btn btn-danger">+ Add User</a>
            <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>

    </div>

    <div class="card shadow-sm">

        <div class="card-body">

            <h5 class="card-title mb-3">
                Registered Users
            </h5>

            <div class="table-responsive">

                <table class="table table-bordered table-hover">

                    <thead class="table-dark">

                        <tr>

                            <th>ID</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Role</th>
                            <th>Action</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php

                        $result = $conn->query(
                            "SELECT id, username, fullname, role
                             FROM users
                             ORDER BY id ASC"
                        );

                        while ($row = $result->fetch_assoc()):

                        ?>

                            <tr>

                                <td>
                                    <?php echo $row['id']; ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($row['username']); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($row['fullname']); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($row['role']); ?>
                                </td>

                               <td>

                                  <a
                                  href="edit_user.php?id=<?php echo $row['id']; ?>"
                                  class="btn btn-sm btn-primary"
                                    >
                                         Edit
                                 </a>

    <?php if ($row['id'] != $_SESSION['user_id']): ?>

        <a
            href="delete_user.php?id=<?php echo $row['id']; ?>"
            class="btn btn-sm btn-danger"
            onclick="return confirm('Are you sure you want to delete this user?');"
        >
            Delete
        </a>

    <?php endif; ?>

</td>

                            </tr>

                        <?php endwhile; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

</body>

</html>
