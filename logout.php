<?php
require_once __DIR__ . '/config/database.php';
if (!empty($_SESSION['user_id'])) {
    require_once __DIR__ . '/includes/functions.php';
    logAudit($conn, (int) $_SESSION['user_id'], 'LOGOUT', 'Authentication', (int) $_SESSION['user_id'], 'User signed out.', ['module' => 'Authentication & Access']);
}
session_unset();
session_destroy();
header("Location: " . BASE_URL . "/index.php");
exit();
?>
