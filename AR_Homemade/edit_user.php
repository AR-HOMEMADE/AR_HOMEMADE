<?php

require_once "role_check.php";
require_role(['Administrator']);

require_once "db.php";

if (!isset($_GET['id'])) {
    header("Location: users.php");
    exit();
}

$id = intval($_GET['id']);

$message = "";
$error = "";

$stmt = $conn->prepare(
    "SELECT id, username, fullname, role
     FROM users
     WHERE id = ?"
);

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: users.php");
    exit();
}

$user = $result->fetch_assoc();

$stmt->close();

if (isset($_POST['update'])) {

    $fullname = trim($_POST['fullname']);
    $role = $_POST['role'];

    $allowed_roles = ['Administrator', 'Staff'];

    if (!in_array($role, $allowed_roles)) {

        $error = "Invalid role.";

    } else {

        $stmt = $conn->prepare(
            "UPDATE users
             SET fullname = ?, role = ?
             WHERE id = ?"
        );

        $stmt->bind_param(
            "ssi",
            $fullname,
            $role,
            $id
        );

        if ($stmt->execute()) {

            $message = "User updated successfully.";

            $user['fullname'] = $fullname;
            $user['role'] = $role;

        } else {

            $error = "Failed to update user.";

        }

        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Edit User - AR Homemade</title>

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

    <div class="card shadow-sm">

        <div class="card-body">

            <h2>Edit User</h2>

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
                        Username
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        value="<?php echo htmlspecialchars($user['username']); ?>"
                        readonly
                    >

                </div>

                <div class="mb-3">

                    <label class="form-label">
                        Full Name
                    </label>

                    <input
                        type="text"
                        name="fullname"
                        class="form-control"
                        value="<?php echo htmlspecialchars($user['fullname']); ?>"
                        required
                    >

                </div>

                <div class="mb-3">

                    <label class="form-label">
                        Role
                    </label>

                    <select
                        name="role"
                        class="form-select"
                        required
                    >

                        <option value="Administrator"
                            <?php if ($user['role'] == 'Administrator') echo 'selected'; ?>>
                            Administrator
                        </option>

                        <option value="Staff"
                            <?php if ($user['role'] == 'Staff') echo 'selected'; ?>>
                            Staff
                        </option>
</select>

                </div>

                <button
                    type="submit"
                    name="update"
                    class="btn btn-primary"
                >
                    Update User
                </button>

                <a
                    href="users.php"
                    class="btn btn-secondary"
                >
                    Cancel
                </a>

            </form>

        </div>

    </div>

</div>

</body>

</html>
