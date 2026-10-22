<?php
// Database connection settings.
$host = "127.0.0.1";
$username = "root";
$password = "";
$database = "ar_homemade";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    error_log("AR Homemade database connection failed: " . $conn->connect_error);
    http_response_code(500);
    die("Unable to connect to the database. Please contact the system administrator.");
}

$conn->set_charset("utf8mb4");
?>
