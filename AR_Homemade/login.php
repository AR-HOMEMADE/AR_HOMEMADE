<?php
session_start();
require_once "db.php";

$error = "";

if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? "");
    $password = $_POST['password'] ?? "";

    $stmt = $conn->prepare("SELECT id, username, password, fullname, role FROM users WHERE username = ? AND role IN ('Administrator', 'Staff')");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role'] = $user['role'];

            header("Location: " . ($user['role'] === 'Administrator' ? 'index.php' : 'orders.php'));
            exit();
        }
    }

    $error = "Invalid username or password.";
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AR Homemade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --wine: #8f1d1d;
            --wine-dark: #681313;
            --cream: #faf7f4;
            --brown: #2b1b18;
            --line: #eadfd9;
        }

        * { box-sizing: border-box; }

        body {
            min-height: 100vh;
            margin: 0;
            font-family: Arial, sans-serif;
            color: var(--brown);
            background:
                radial-gradient(circle at top left, rgba(143,29,29,.12), transparent 35%),
                var(--cream);
        }

        .auth-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .auth-card {
            width: 100%;
            max-width: 980px;
            min-height: 610px;
            display: grid;
            grid-template-columns: 43% 57%;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 24px 70px rgba(63, 25, 18, .14);
        }

        .brand-panel {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 45px 30px;
            color: #fff;
            background:
                radial-gradient(circle at 80% 15%, rgba(255,255,255,.14), transparent 30%),
                linear-gradient(145deg, var(--wine-dark), var(--wine));
        }

        .brand-panel::after {
            content: "";
            position: absolute;
            width: 220px;
            height: 220px;
            border: 1px solid rgba(255,255,255,.14);
            border-radius: 50%;
            bottom: -100px;
            left: -80px;
        }

        .brand-logo {
            width: 190px;
            height: 190px;
            object-fit: contain;
            background: #fff;
            border-radius: 24px;
            padding: 4px;
            box-shadow: 0 14px 35px rgba(0,0,0,.18);
            margin-bottom: 22px;
        }

        .brand-title {
            margin: 0;
            font-size: 32px;
            font-weight: 800;
        }

        .brand-text {
            max-width: 300px;
            margin: 10px 0 0;
            line-height: 1.6;
            opacity: .9;
        }

        .form-panel {
            display: flex;
            align-items: center;
            padding: 55px;
        }

        .form-wrap {
            width: 100%;
            max-width: 420px;
            margin: auto;
        }

        .form-title {
            font-size: 32px;
            font-weight: 800;
            margin: 0;
            color: #241613;
        }

        .form-subtitle {
            color: #7a6a65;
            margin: 8px 0 28px;
        }

        .form-label {
            font-weight: 700;
            margin-bottom: 8px;
        }

        .form-control {
            height: 52px;
            border: 1px solid #ddd0ca;
            border-radius: 13px;
            padding: 0 15px;
            background: #fffdfc;
        }

        .form-control:focus {
            border-color: var(--wine);
            box-shadow: 0 0 0 4px rgba(143,29,29,.10);
        }

        .login-btn {
            height: 52px;
            border: 0;
            border-radius: 13px;
            background: linear-gradient(135deg, var(--wine), #a92a2a);
            color: #fff;
            font-weight: 800;
            box-shadow: 0 10px 22px rgba(143,29,29,.22);
            transition: .2s ease;
        }

        .login-btn:hover {
            color: #fff;
            transform: translateY(-2px);
            background: linear-gradient(135deg, var(--wine-dark), var(--wine));
        }

        .alert {
            border-radius: 12px;
            border: 0;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 22px;
            color: #7a6a65;
            text-decoration: none;
            font-weight: 700;
        }

        .back-link:hover { color: var(--wine); }

        @media (max-width: 800px) {
            .auth-card {
                grid-template-columns: 1fr;
                max-width: 520px;
                min-height: auto;
            }

            .brand-panel { padding: 35px 25px; }
            .brand-logo { width: 135px; height: 135px; margin-bottom: 15px; }
            .brand-title { font-size: 27px; }
            .brand-text { margin-bottom: 0; }
            .form-panel { padding: 35px 25px 40px; }
        }
    </style>
    <link rel="stylesheet" href="style.css">
</head>

<body>
<main class="auth-page">
    <section class="auth-card">
        <div class="brand-panel">
            <img src="images/ar_logo.png" alt="AR Homemade logo" class="brand-logo">
            <h1 class="brand-title">AR Homemade</h1>
            <p class="brand-text">Manage orders, products, inventory, and sales in one place.</p>
        </div>

        <div class="form-panel">
            <div class="form-wrap">
                <span class="back-link">AR Homemade Staff Portal</span>

                <h2 class="form-title">Welcome back</h2>
                <p class="form-subtitle">Sign in with your Administrator or Staff account.</p>

                <?php if (isset($_GET['access']) && $_GET['access'] === 'denied'): ?>
                    <div class="alert alert-warning mb-4">Only Administrator and Staff accounts can access this system.</div>
                <?php endif; ?>
                <?php if ($error !== ""): ?>
                    <div class="alert alert-danger mb-4"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" id="username" name="username" class="form-control"
                               autocomplete="username" placeholder="Enter your username" required>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" id="password" name="password" class="form-control"
                               autocomplete="current-password" placeholder="Enter your password" required>
                    </div>

                    <button type="submit" name="login" class="btn login-btn w-100">Login</button>
                </form>

                <p class="text-center mt-4 mb-0 text-muted">
                    Account access is managed by the Administrator.
                </p>
            </div>
        </div>
    </section>
</main>
</body>
</html>
