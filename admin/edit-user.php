<?php
$page_title = 'Edit User';
require_once '../includes/session-check.php';
checkRole(['Admin']);
require_once '../includes/functions.php';   // REQUIRED for redirectWith() and logAudit()
ensureUserAccountSecuritySchema($conn);

// Validate user ID from URL
$uid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($uid <= 0) {
    redirectWith(BASE_URL . '/admin/users.php', 'danger', 'Invalid user ID.');
}

// Fetch the user to edit with employee info and profile picture
$stmt = $conn->prepare("
    SELECT u.*, u.profile_picture AS user_profile_picture,
           e.first_name, e.last_name, e.profile_picture AS employee_profile_picture,
           e.employee_code, e.job_title, rc.rank_name
    FROM users u 
    LEFT JOIN employees e ON u.employee_id = e.employee_id 
    LEFT JOIN rank_categories rc ON e.rank_category_id = rc.rank_category_id
    WHERE u.user_id = ?
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    redirectWith(BASE_URL . '/admin/users.php', 'danger', 'User not found.');
}

$is_standalone_admin = ($user['role'] === 'Admin');

// ── Handle form submission ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $username   = trim($_POST['username']  ?? '');
    $email      = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $full_name  = trim($_POST['full_name'] ?? '');
    $role       = $_POST['role']           ?? '';
    $branch_id  = !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : null;
    $new_pass   = trim($_POST['password']  ?? '');
    $release_hold = isset($_POST['release_hold']);
    $linked_employee_username = !empty($user['employee_code']) ? (string)$user['employee_code'] : '';

    if ($is_standalone_admin) {
        $role = 'Admin';
        $branch_id = null;
    }

    // Basic validation
    if (empty($username) || !$email || empty($full_name) || empty($role)) {
        redirectWith(BASE_URL . "/admin/edit-user.php?id=$uid", 'danger', 'Please fill in all required fields.');
    }
    if ($is_standalone_admin && $role !== 'Admin') {
        redirectWith(BASE_URL . "/admin/edit-user.php?id=$uid", 'danger', 'Standalone Admin accounts must remain Admin.');
    }
    if ($linked_employee_username !== '' && $role !== 'Employee' && $username === $linked_employee_username) {
        redirectWith(BASE_URL . "/admin/edit-user.php?id=$uid", 'danger', 'HR and admin usernames must be custom and must not use the Employee ID.');
    }

    // Duplicate username/email check (exclude self)
    $dup = $conn->prepare("SELECT user_id FROM users WHERE (username = ? OR email = ?) AND user_id != ?");
    $dup->bind_param("ssi", $username, $email, $uid);
    $dup->execute();
    if ($dup->get_result()->num_rows > 0) {
        $dup->close();
        redirectWith(BASE_URL . "/admin/edit-user.php?id=$uid", 'danger', 'Username or email is already taken by another user.');
    }
    $dup->close();

    // ── Handle Account Profile Picture Upload ───────────────────────────────────
    $new_profile_picture = $user['user_profile_picture'];
    $uploaded_profile_picture_path = null;
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
                $new_profile_picture = $new_file_name;
                $uploaded_profile_picture_path = $upload_path;
            }
        }
    }

    if (!empty($new_pass) && strlen($new_pass) < 6) {
        redirectWith(BASE_URL . "/admin/edit-user.php?id=$uid", 'danger', 'Password must be at least 6 characters.');
    }
    if (!empty($user['account_hold']) && $release_hold && $new_pass === '') {
        redirectWith(BASE_URL . "/admin/edit-user.php?id=$uid", 'danger', 'Enter new credentials before releasing this account hold.');
    }

    if ($is_standalone_admin && !empty($new_pass)) {
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET employee_id=NULL, username=?, email=?, full_name=?, profile_picture=?, role=?, branch_id=NULL, password_hash=? WHERE user_id=?");
        $stmt->bind_param("ssssssi", $username, $email, $full_name, $new_profile_picture, $role, $hash, $uid);
    } elseif ($is_standalone_admin) {
        $stmt = $conn->prepare("UPDATE users SET employee_id=NULL, username=?, email=?, full_name=?, profile_picture=?, role=?, branch_id=NULL WHERE user_id=?");
        $stmt->bind_param("sssssi", $username, $email, $full_name, $new_profile_picture, $role, $uid);
    } elseif (!empty($new_pass)) {
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET username=?, email=?, full_name=?, role=?, branch_id=?, profile_picture=?, password_hash=? WHERE user_id=?");
        $stmt->bind_param("ssssissi", $username, $email, $full_name, $role, $branch_id, $new_profile_picture, $hash, $uid);
    } else {
        $stmt = $conn->prepare("UPDATE users SET username=?, email=?, full_name=?, role=?, branch_id=?, profile_picture=? WHERE user_id=?");
        $stmt->bind_param("ssssisi", $username, $email, $full_name, $role, $branch_id, $new_profile_picture, $uid);
    }

    if ($stmt->execute()) {
        if (!empty($user['account_hold']) && $release_hold) {
            $hold_update = $conn->prepare("UPDATE users SET account_hold = 0, account_hold_reason = NULL, account_hold_at = NULL, account_hold_movement_id = NULL WHERE user_id = ?");
            $hold_update->bind_param("i", $uid);
            $hold_update->execute();
            $hold_update->close();
        }
        if ($uploaded_profile_picture_path && !empty($user['user_profile_picture'])) {
            $old_profile_picture_path = '../assets/img/users/' . $user['user_profile_picture'];
            if (file_exists($old_profile_picture_path)) {
                unlink($old_profile_picture_path);
            }
        }
        logAudit($conn, $_SESSION['user_id'], 'UPDATE', 'User', $uid, "Updated user: $username ($role)");
        redirectWith(BASE_URL . '/admin/users.php', 'success', "User '$username' updated successfully.");
    } else {
        // Cleanup newly uploaded file if DB fails
        if ($uploaded_profile_picture_path && file_exists($uploaded_profile_picture_path)) {
            unlink($uploaded_profile_picture_path);
        }
        redirectWith(BASE_URL . "/admin/edit-user.php?id=$uid", 'danger', 'Database error: ' . $conn->error);
    }
    $stmt->close();
}

// Load page UI
require_once '../includes/header.php';

// Branches for the dropdown
$branches = $conn->query("SELECT * FROM branches ORDER BY branch_name");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <p class="text-muted mb-0">Edit user account details</p>
    <a href="<?php echo BASE_URL; ?>/admin/users.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to Users
    </a>
</div>

<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5><i class="fas fa-user-edit me-2"></i>Edit User: <?php echo e($user['full_name']); ?></h5>
    </div>
    <div class="card-body">
        <form method="POST" action="" enctype="multipart/form-data">
            <?php echo csrfField(); ?>
            <div class="row mb-4 text-center">
                <div class="col-12">
                    <div class="position-relative d-inline-block">
                        <img src="<?php echo getUserAvatar($user['user_profile_picture'], $user['employee_profile_picture']); ?>" 
                             alt="Profile" class="rounded-circle img-thumbnail" style="width: 120px; height: 120px; object-fit: cover;">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Username <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="username"
                           value="<?php echo e($user['username']); ?>" required>
                    <?php if (!empty($user['employee_id'])): ?>
                        <div class="form-text">Use a custom HR/Admin username. Do not use Employee ID `<?php echo e(getEmployeeDisplayId($user)); ?>`.</div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" name="email"
                           value="<?php echo e($user['email']); ?>" required>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="full_name"
                           value="<?php echo e($user['full_name']); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Role <span class="text-danger">*</span></label>
                    <?php if ($is_standalone_admin): ?>
                        <input type="text" class="form-control" value="Admin" readonly>
                        <input type="hidden" name="role" value="Admin">
                        <div class="form-text">Standalone Admin accounts are fixed to the Admin role.</div>
                    <?php else: ?>
                        <select class="form-select" name="role" required>
                            <option value="Admin"        <?php echo $user['role']==='Admin'         ? 'selected' : ''; ?>>Admin</option>
                            <option value="HR Manager"   <?php echo $user['role']==='HR Manager'   ? 'selected' : ''; ?>>HR Manager / Manager Level</option>
                            <option value="HR Supervisor"<?php echo $user['role']==='HR Supervisor'? 'selected' : ''; ?>>HR Supervisor</option>
                            <option value="HR Staff"     <?php echo $user['role']==='HR Staff'     ? 'selected' : ''; ?>>HR Staff</option>
                        </select>
                        <div class="form-text">This access role can be used by multiple HR manager-level employees.</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Employee Position</label>
                    <input type="text" class="form-control"
                           value="<?php echo e($user['job_title'] ?: 'Standalone account'); ?>" readonly>
                    <?php if (!empty($user['rank_name'])): ?>
                        <div class="form-text">Rank: <?php echo e($user['rank_name']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Branch <small class="text-muted">(leave empty for Admin)</small></label>
                    <?php if ($is_standalone_admin): ?>
                        <input type="text" class="form-control" value="None (Admin)" readonly>
                    <?php else: ?>
                        <select class="form-select" name="branch_id">
                            <option value="">None (Admin)</option>
                            <?php while ($branch = $branches->fetch_assoc()): ?>
                                <option value="<?php echo $branch['branch_id']; ?>"
                                    <?php echo ($user['branch_id'] == $branch['branch_id']) ? 'selected' : ''; ?>>
                                    <?php echo e($branch['branch_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">
                        New Password
                        <small class="text-muted">(leave blank to keep current)</small>
                    </label>
                    <input type="password" class="form-control" name="password"
                           minlength="6" placeholder="Min. 6 characters">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Account Profile Picture</label>
                    <input type="file" class="form-control" name="profile_picture" accept="image/*">
                    <div class="form-text">Max 2MB (JPG, PNG, WebP).</div>
                </div>
            </div>

            <!-- Active status toggle -->
            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="isActiveToggle" disabled
                           <?php echo $user['is_active'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="isActiveToggle">
                        Account is <?php echo $user['is_active'] ? '<span class="text-success">Active</span>' : '<span class="text-danger">Inactive</span>'; ?>
                        &mdash; <small class="text-muted">use the power button on the Users list to toggle</small>
                    </label>
                </div>
                <?php if (!empty($user['account_hold'])): ?>
                <div class="alert alert-warning d-flex align-items-start gap-2">
                    <i class="fas fa-lock mt-1"></i>
                    <div>
                        <strong>Account on hold</strong>
                        <div class="small"><?php echo e($user['account_hold_reason'] ?? 'This account requires Admin review before access can resume.'); ?></div>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="release_hold" id="releaseHold">
                            <label class="form-check-label" for="releaseHold">Release hold after setting a new password</label>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="d-flex justify-content-between mt-3">
                <a href="<?php echo BASE_URL; ?>/admin/users.php" class="btn btn-secondary">
                    <i class="fas fa-times me-1"></i>Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Update User
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
