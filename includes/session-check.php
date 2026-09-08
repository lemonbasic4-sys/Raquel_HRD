<?php
// ============================================
// Session Validation
// ============================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
ensureUserAccountSecuritySchema($conn);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // If accessing employee folder, redirect to employee login
    if (strpos($_SERVER['REQUEST_URI'], '/employee/') !== false) {
        header("Location: " . BASE_URL . "/employee/index.php");
    } else {
        header("Location: " . BASE_URL . "/index.php");
    }
    exit();
}

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Verify user & employee account status
if (isset($_SESSION['user_id'])) {
    $uid = (int) $_SESSION['user_id'];
    $active_chk = $conn->prepare("SELECT u.is_active AS user_active, u.account_hold, u.deleted_at AS user_deleted_at,
        e.is_active AS emp_active, e.deleted_at AS emp_deleted_at, e.employment_status
        FROM users u LEFT JOIN employees e ON u.employee_id = e.employee_id
        WHERE u.user_id = ? LIMIT 1");
    if ($active_chk) {
        $active_chk->bind_param("i", $uid);
        $active_chk->execute();
        $active_res = $active_chk->get_result();
        $row = $active_res->fetch_assoc();
        // A PHP session can outlive a database reset. Treat a missing account
        // exactly like a disabled or deleted account and force a fresh login.
        $is_emp_inactive = $row && (($row['emp_active'] !== null && (int)$row['emp_active'] === 0)
            || !empty($row['emp_deleted_at'])
            || (strcasecmp($row['employment_status'] ?? '', 'Inactive') === 0));
        if (!$row || !(int)$row['user_active'] || (int)($row['account_hold'] ?? 0) === 1 || !empty($row['user_deleted_at']) || $is_emp_inactive) {
            session_unset();
            session_destroy();
            if (strpos($_SERVER['REQUEST_URI'], '/employee/') !== false) {
                header("Location: " . BASE_URL . "/employee/index.php");
            } else {
                header("Location: " . BASE_URL . "/index.php");
            }
            exit();
        }
        $active_chk->close();
    }
}

/**
 * Check if current user has the required role.
 * 
 * @param array $allowed_roles Array of allowed role strings
 */
function checkRole($allowed_roles)
{
    $current_role = $_SESSION['role'] ?? '';

    if (!in_array($current_role, $allowed_roles, true)) {
        if (in_array('Employee', $allowed_roles, true)) {
            header("Location: " . BASE_URL . "/employee/index.php");
        } else {
            header("Location: " . BASE_URL . "/index.php");
        }
        exit();
    }
}
?>
