<?php
require_once "db.php";
require_once "auth.php";
require_once "role_check.php";
require_role(['Administrator']);

$error = "";
$success = "";
$fullname = "";
$username = "";
$role = "Staff";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = trim($_POST['role'] ?? 'Staff');
    $allowed_roles = ['Administrator', 'Staff'];

    if ($fullname === '' || $username === '' || $password === '') {
        $error = "Please complete all required fields.";
    } elseif (strlen($fullname) > 150 || strlen($username) > 100) {
        $error = "Full name or username is too long.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif (!in_array($role, $allowed_roles, true)) {
        $error = "Please select a valid role.";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $check->bind_param("s", $username);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $check->close();

        if ($exists) {
            $error = "That username is already in use.";
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $insert = $conn->prepare("INSERT INTO users (username, password, fullname, role) VALUES (?, ?, ?, ?)");
            $insert->bind_param("ssss", $username, $password_hash, $fullname, $role);
            if ($insert->execute()) {
                $insert->close();
                header("Location: users.php?created=1");
                exit();
            }
            $insert->close();
            $error = "The account could not be created. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User - AR Homemade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        :root { --wine:#8f1d1d; --cream:#faf7f4; --brown:#2b1b18; --line:#eadfd9; }
        body { background:var(--cream); color:var(--brown); }
        .form-card { max-width:680px; margin:36px auto; border:1px solid var(--line); border-radius:18px; background:#fff; box-shadow:0 12px 32px rgba(43,27,24,.08); }
        .form-card .card-header { background:var(--wine); color:#fff; border-radius:17px 17px 0 0; padding:18px 24px; }
        .form-card .card-body { padding:24px; }
        .btn-wine { background:var(--wine); color:#fff; border:0; }
        .btn-wine:hover { background:#681313; color:#fff; }
    </style>
</head>
<body>
<div class="container">
    <section class="card form-card">
        <div class="card-header"><h1 class="h4 mb-0">Create User Account</h1></div>
        <div class="card-body">
            <p class="text-muted">Only an Administrator can create Administrator or Staff accounts. Customer accounts are not supported.</p>
            <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <form method="post" autocomplete="off">
                <div class="mb-3">
                    <label for="fullname" class="form-label">Full name</label>
                    <input id="fullname" name="fullname" class="form-control" maxlength="150" value="<?php echo htmlspecialchars($fullname); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input id="username" name="username" class="form-control" maxlength="100" value="<?php echo htmlspecialchars($username); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Temporary password</label>
                    <input id="password" name="password" type="password" class="form-control" minlength="8" required>
                    <div class="form-text">Use at least 8 characters. Give the password to the account owner securely.</div>
                </div>
                <div class="mb-4">
                    <label for="role" class="form-label">Role</label>
                    <select id="role" name="role" class="form-select" required>
                        <?php foreach (['Administrator','Staff'] as $available_role): ?>
                            <option value="<?php echo $available_role; ?>" <?php echo $role === $available_role ? 'selected' : ''; ?>><?php echo $available_role; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-wine">Create Account</button>
                    <a href="users.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </section>
</div>
</body>
</html>
