<?php
// Handle Add User form submission
require_once '../includes/session-check.php';
checkRole(['Admin']);
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $username = trim($_POST['username'] ?? '');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $full_name = trim($_POST['full_name'] ?? '');
    $role = $_POST['role'] ?? '';
    $branch_id = !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : null;
    $employee_id = !empty($_POST['employee_id']) ? (int)$_POST['employee_id'] : null;
    $password = $_POST['password'] ?? '';
    $redirect = $_POST['redirect'] ?? 'users.php';

    // Admin accounts are standalone security accounts and never inherit employee or branch access.
    if ($role === 'Admin') {
        $employee_id = null;
        $branch_id = null;
    }

    $employee_username = '';
    $is_standalone_admin = ($role === 'Admin');

    if ($employee_id !== null) {
        $employee_lookup = $conn->prepare("SELECT employee_code FROM employees WHERE employee_id = ? LIMIT 1");
        $employee_lookup->bind_param("i", $employee_id);
        $employee_lookup->execute();
        $employee_row = $employee_lookup->get_result()->fetch_assoc();
        $employee_lookup->close();
        $employee_username = trim((string)($employee_row['employee_code'] ?? ''));
    }

    // For Employee-role portal accounts with no email, auto-generate a placeholder
    if ($role === 'Employee' && !$email && $employee_id) {
        $email = 'employee-' . $employee_id . '@portal.raquel.local';
    }

    // Validate
    $errors = [];
    if (empty($username)) $errors[] = 'Username is required.';
    if (!$email) $errors[] = $is_standalone_admin ? 'Valid email is required.' : 'Valid email is required (Check employee contact info).';
    if (empty($full_name)) $errors[] = 'Full name is required.';
    if (!in_array($role, ['Admin', 'HR Manager', 'HR Supervisor', 'HR Staff', 'Employee'])) $errors[] = 'Invalid role.';
    if ($role !== 'Admin' && empty($employee_id)) $errors[] = 'Employee selection is required.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($role !== 'Employee' && $employee_username !== '' && $username === $employee_username) $errors[] = 'HR and admin usernames must be custom and must not use the Employee ID.';


    // Check for duplicate username
    if (empty($errors)) {
        $check = $conn->prepare("SELECT user_id, employee_id, role FROM users WHERE username = ? LIMIT 1");
        $check->bind_param("s", $username);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        $check->close();

        if ($existing) {
            if ($role === 'Employee' && (int)($existing['employee_id'] ?? 0) === (int)$employee_id && ($existing['role'] ?? '') !== 'Employee') {
                $errors[] = "The Employee ID username is already being used by this employee's {$existing['role']} account. Rename the HR/account username first, then create the Employee Portal account.";
            }
            $errors[] = 'Username already exists.';
        }
    }

    if (empty($errors)) {
        $email_check = $conn->prepare("SELECT user_id, employee_id, role FROM users WHERE email = ? LIMIT 1");
        $email_check->bind_param("s", $email);
        $email_check->execute();
        $existing_email = $email_check->get_result()->fetch_assoc();
        $email_check->close();

        if ($existing_email) {
            // Allow same employee to have both HR and Employee accounts with same email
            if ((int)($existing_email['employee_id'] ?? 0) === (int)$employee_id) {
                // Same employee - generate a safe unique email
                if ($role === 'Employee') {
                    $email = 'employee-' . $employee_id . '@portal.raquel.local';
                } else {
                    $email = strtolower(str_replace(' ', '.', $full_name)) . '-' . $employee_id . '@hr.raquel.local';
                }
            } elseif ($role === 'Employee') {
                // Different employee shares same email (e.g. governance officials) — generate unique placeholder
                $email = 'employee-' . $employee_id . '@portal.raquel.local';
            } else {
                $errors[] = 'Email is already used by another account.';
            }
        }

    }

    if (!empty($errors)) {
        redirectWith(BASE_URL . '/admin/' . $redirect, 'danger', implode(' ', $errors));
    }

    // Hash password and insert
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Account avatars belong to the user record so standalone Admin accounts can use them too.
    $profile_picture = null;
    $profile_picture_path = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['profile_picture']['tmp_name'];
        $file_name = $_FILES['profile_picture']['name'];
        $file_size = $_FILES['profile_picture']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($file_ext, $allowed_exts) && $file_size <= 2 * 1024 * 1024) {
            $upload_dir = '../assets/img/users/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $new_file_name = 'usr_' . bin2hex(random_bytes(8)) . '.' . $file_ext;
            $upload_path = $upload_dir . $new_file_name;

            if (move_uploaded_file($file_tmp, $upload_path)) {
                $profile_picture = $new_file_name;
                $profile_picture_path = $upload_path;
            }
        }
    }

    // Store generated password in session for display (shown once on next page)
    $_SESSION['new_employee_credentials'] = [
        'username'  => $username,
        'password'  => $password,
        'full_name' => $full_name,
    ];
    $stmt = $conn->prepare("INSERT INTO users (employee_id, username, email, password_hash, full_name, profile_picture, role, branch_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssssi", $employee_id, $username, $email, $password_hash, $full_name, $profile_picture, $role, $branch_id);

    if ($stmt->execute()) {
        $new_id = $stmt->insert_id;
        logAudit($conn, $_SESSION['user_id'], 'CREATE', 'User', $new_id, "Created user: $username ($role)");
        redirectWith(BASE_URL . '/admin/' . $redirect, 'success', "User '$username' created successfully.");
    } else {
        // Cleanup uploaded file if DB fails
        if ($profile_picture_path && file_exists($profile_picture_path)) {
            unlink($profile_picture_path);
        }
        redirectWith(BASE_URL . '/admin/' . $redirect, 'danger', 'Failed to create user. Please try again.');
    }
    $stmt->close();
} else {
    header("Location: " . BASE_URL . "/admin/users.php");
    exit();
}
?>
