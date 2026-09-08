<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'raquel_hris');

// Raquel Pawnshop operates on Philippine time. All scheduled tasks use this clock.
date_default_timezone_set('Asia/Manila');

define('BASE_URL', '/' . basename(dirname(__DIR__)));
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
