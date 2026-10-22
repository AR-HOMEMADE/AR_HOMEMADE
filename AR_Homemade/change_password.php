<?php
require_once "role_check.php";
require_role(['Administrator']);

require_once "auth.php";
require_once "db.php";

$message = "";
$error = "";

if (isset($_POST['change_password'])) {

    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $user_id = $_SESSION['user_id'];

    if ($new_password !== $confirm_password) {

        $error = "New passwords do not match.";

    } else {

        $stmt = $conn->prepare(
            "SELECT password FROM users WHERE id = ?"
        );

        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        $stmt->close();

        if (!$user || !password_verify($current_password, $user['password'])) {

            $error = "Current password is incorrect.";

        } else {

            $hashed_password = password_hash(
                $new_password,
                PASSWORD_DEFAULT
            );

            $stmt = $conn->prepare(
                "UPDATE users
                 SET password = ?
                 WHERE id = ?"
            );

            $stmt->bind_param(
                "si",
                $hashed_password,
                $user_id
            );

            if ($stmt->execute()) {

                $message = "Password changed successfully.";

            } else {

                $error = "Failed to change password.";

            }

            $stmt->close();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Change Password - AR Homemade</title>

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

    <div class="row justify-content-center">

        <div class="col-md-6">

            <div class="card shadow-sm">

                <div class="card-body">

                    <h2 class="mb-4">
                        Change Password
                    </h2>

                    <?php if ($message != ""): ?>

                        <div class="alert alert-success">
                            <?php echo htmlspecialchars($message); ?>
                        </div>

                    <?php endif; ?>

                    <?php if ($error != ""): ?>

                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($error); ?>
                        </div>

                    <?php endif; ?>

                    <form method="POST">

                        <div class="mb-3">

                            <label class="form-label">
                                Current Password
                            </label>

                            <input
                                type="password"
                                name="current_password"
                                class="form-control"
                                required
                            >

                        </div>

                        <div class="mb-3">

                            <label class="form-label">
                                New Password
                            </label>

                            <input
                                type="password"
                                name="new_password"
                                class="form-control"
                                required
                            >

                        </div>

                        <div class="mb-3">

                            <label class="form-label">
                                Confirm New Password
                            </label>

                            <input
                                type="password"
                                name="confirm_password"
                                class="form-control"
                                required
                            >

                        </div>

                        <button
                            type="submit"
                            name="change_password"
                            class="btn btn-primary"
                        >
                            Change Password
                        </button>

                        <a
                            href="index.php"
                            class="btn btn-secondary"
                        >
                            Back
                        </a>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>

</body>

</html>
