<?php
// Only authorized Administrator and Staff accounts may access the internal web system.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$allowed_roles = ['Administrator', 'Staff'];

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles, true)) {
    // End any old Customer session created by the previous version of the system.
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: login.php?access=denied');
    exit();
}
?>
