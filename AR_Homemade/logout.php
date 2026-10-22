<?php
session_start();

// Remove all login information and return to the login page.
session_unset();
session_destroy();

header("Location: login.php");
exit();
?>
