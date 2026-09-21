<?php
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'raquel_hris');

date_default_timezone_set('Asia/Manila');

define('BASE_URL', '/' . basename(dirname(__DIR__)));

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    // If connection failed, provide a clean message or fallback
    die("Database Connection Error: Could not connect to MySQL server at " . DB_HOST . ". Please verify that MySQL is running in XAMPP. (" . htmlspecialchars($e->getMessage()) . ")");
}

 if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
