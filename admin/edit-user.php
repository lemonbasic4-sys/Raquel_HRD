<?php
$page_title = 'Reset User Password';
require_once '../includes/session-check.php';
checkRole(['Admin']);
require_once '../includes/functions.php';
ensureUserAccountSecuritySchema($conn);

$uid = (int)($_GET['id'] ?? 0);
if ($uid <= 0) {
    redirectWith(BASE_URL . '/admin/users.php', 'danger', 'Invalid user ID.');
}

$stmt = $conn->prepare('SELECT u.user_id, u.username, u.full_name, u.role, u.is_active, u.account_hold, u.account_hold_reason,
    u.profile_picture AS user_profile_picture, e.profile_picture AS employee_profile_picture, e.job_title
    FROM users u LEFT JOIN employees e ON u.employee_id = e.employee_id WHERE u.user_id = ? LIMIT 1');
$stmt->bind_param('i', $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user) {
    redirectWith(BASE_URL . '/admin/users.php', 'danger', 'User not found.');
}
$page_title = !empty($user['account_hold']) ? 'Manage Account Access' : 'Reset User Password';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $password = (string)($_POST['password'] ?? '');
    $confirmation = (string)($_POST['confirm_password'] ?? '');
    $release_hold = !empty($user['account_hold']) && isset($_POST['release_hold']);
    if ($password === '' && !$release_hold) {
        redirectWith(BASE_URL . "/admin/edit-user.php?id=$uid", 'danger', 'Enter a new password or select Release hold.');
    }
    if ($password !== '' && strlen($password) < 6) {
        redirectWith(BASE_URL . "/admin/edit-user.php?id=$uid", 'danger', 'New passwords must have at least 6 characters.');
    }
    if ($password !== $confirmation) {
        redirectWith(BASE_URL . "/admin/edit-user.php?id=$uid", 'danger', 'New passwords do not match. Try again.');
    }

    $conn->begin_transaction();
    try {
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $update = $conn->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
            $update->bind_param('si', $hash, $uid);
            if (!$update->execute()) {
                throw new RuntimeException('Password update failed.');
            }
            $update->close();
        }

        if ($release_hold) {
            $release = $conn->prepare('UPDATE users SET account_hold = 0, account_hold_reason = NULL,
                account_hold_at = NULL, account_hold_movement_id = NULL WHERE user_id = ?');
            $release->bind_param('i', $uid);
            if (!$release->execute()) {
                throw new RuntimeException('Account hold release failed.');
            }
            $release->close();
        }
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('Admin account access update failed: ' . $e->getMessage());
        redirectWith(BASE_URL . "/admin/edit-user.php?id=$uid", 'danger', 'Could not update account access.');
    }

    if ($password !== '') {
        logAudit($conn, (int)$_SESSION['user_id'], 'CHANGE_PASSWORD', 'User', $uid, 'Administrator reset an account password.');
    }
    if ($release_hold) {
        logAudit($conn, (int)$_SESSION['user_id'], 'ACCOUNT_HOLD_RELEASED', 'User', $uid,
            $password === '' ? 'Administrator released an account hold; password unchanged.' : 'Administrator released an account hold after resetting the password.');
    }
    $result_message = $release_hold
        ? ($password === '' ? 'Account hold released; password unchanged.' : 'Password updated and account hold released.')
        : 'Password updated.';
    notifyOtherAdmins($conn, (int)$_SESSION['user_id'], 'User Account Access Updated',
        'An administrator updated access for ' . $user['username'] . '. ' . $result_message,
        BASE_URL . '/admin/edit-user.php?id=' . $uid, 'accounts');
    if ($password !== '') {
        $_SESSION['new_employee_credentials'] = [
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'password' => $password,
            'password_reset_message' => $result_message,
        ];
    }
    redirectWith(BASE_URL . '/admin/users.php', 'success', $result_message);
}

require_once '../includes/header.php';
?>
<div class="admin-edit-user-page">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <p class="text-muted mb-0"><?php echo !empty($user['account_hold']) ? 'Release the hold or reset the password' : 'Reset account credentials'; ?></p>
        <a href="<?php echo BASE_URL; ?>/admin/users.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Back to Users</a>
    </div>
    <div class="content-card">
        <div class="card-header"><h5 class="mb-0"><i class="fas fa-key me-2"></i><?php echo !empty($user['account_hold']) ? 'Manage Account Access' : 'Reset Password'; ?>: <?php echo e($user['full_name']); ?></h5></div>
        <div class="card-body">
            <div class="d-flex align-items-center gap-3 mb-4">
                <img src="<?php echo getUserAvatar($user['user_profile_picture'], $user['employee_profile_picture']); ?>"
                    alt="Profile" class="rounded-circle" style="width:64px;height:64px;object-fit:cover;">
                <div><strong><?php echo e($user['username']); ?></strong><div class="text-muted small"><?php echo e($user['role']); ?><?php if (!empty($user['job_title'])): ?> · <?php echo e($user['job_title']); ?><?php endif; ?></div></div>
            </div>
            <form method="post">
                <?php echo csrfField(); ?>
                <div class="mb-3" style="max-width:430px;">
                    <label class="form-label" for="newPassword">New Password<?php if (empty($user['account_hold'])): ?> <span class="text-danger">*</span><?php endif; ?></label>
                    <input type="password" class="form-control" name="password" id="newPassword" minlength="6" autocomplete="new-password" <?php echo empty($user['account_hold']) ? 'required' : ''; ?>>
                    <div class="form-text"><?php echo !empty($user['account_hold']) ? 'Leave blank to keep the current password when releasing the hold.' : 'At least 6 characters.'; ?></div>
                </div>
                <div class="mb-3" style="max-width:430px;">
                    <label class="form-label" for="confirmPassword">Confirm New Password</label>
                    <input type="password" class="form-control" name="confirm_password" id="confirmPassword" minlength="6" autocomplete="new-password" <?php echo empty($user['account_hold']) ? 'required' : ''; ?>>
                </div>
                <?php if (!empty($user['account_hold'])): ?>
                    <div class="alert alert-warning" style="max-width:600px;">
                        <strong><i class="fas fa-lock me-1"></i>Account on hold</strong>
                        <div class="small mt-1"><?php echo e($user['account_hold_reason'] ?: 'This account requires Admin review before access can resume.'); ?></div>
                        <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="release_hold" id="releaseHold"><label class="form-check-label" for="releaseHold">Release hold (keep current password if no new password is entered)</label></div>
                    </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between gap-2 mt-4">
                    <a href="<?php echo BASE_URL; ?>/admin/users.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i><?php echo !empty($user['account_hold']) ? 'Save Account Access' : 'Save New Password'; ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
