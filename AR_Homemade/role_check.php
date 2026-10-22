<?php
require_once "auth.php";
function require_role($allowed_roles)
{
    $role = $_SESSION['role'] ?? '';
    if (!in_array($role, $allowed_roles, true)) {
        // Staff are limited to the order page; administrator-only pages stay private.
        $destination = ($role === 'Staff') ? 'orders.php?access=denied' : 'login.php?access=denied';
        header('Location: ' . $destination);
        exit();
    }
}
?>
