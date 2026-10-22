<?php

require_once "role_check.php";
require_role(['Administrator']);

require_once "db.php";

if (!isset($_GET['id'])) {
    header("Location: users.php");
    exit();
}

$id = intval($_GET['id']);

/* Prevent deleting the currently logged-in account */

if ($id == $_SESSION['user_id']) {
    header("Location: users.php");
    exit();
}

$stmt = $conn->prepare(
    "DELETE FROM users WHERE id = ?"
);

$stmt->bind_param("i", $id);

$stmt->execute();

$stmt->close();

header("Location: users.php");
exit();

?>
