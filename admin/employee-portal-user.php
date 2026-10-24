<?php
$page_title = 'Employee Portal Account';
require_once '../includes/session-check.php';
checkRole(['Admin']);
require_once '../includes/functions.php';
ensureUserAccountSecuritySchema($conn);

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$dept = isset($_GET['department']) ? (int)$_GET['department'] : 0;
if ($user_id <= 0) {
    redirectWith(BASE_URL . '/admin/employee-accounts.php?page=' . $page . ($dept > 0 ? '&department=' . $dept : ''), 'danger', 'Invalid portal user ID.');
}

// Grab and clear any pending credential slip
$new_creds = $_SESSION['new_employee_credentials'] ?? null;
unset($_SESSION['new_employee_credentials']);

// Fetch user + employee details
$stmt = $conn->prepare("
    SELECT
        u.user_id, u.employee_id, u.username, u.email, u.full_name, u.role, u.is_active, u.account_hold, u.account_hold_reason, u.created_at,
        e.employee_code, e.first_name, e.last_name, e.job_title, e.profile_picture,
        b.branch_name
    FROM users u
    LEFT JOIN employees e ON u.employee_id = e.employee_id
    LEFT JOIN branches b ON e.branch_id = b.branch_id
    WHERE u.user_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    redirectWith(BASE_URL . '/admin/employee-accounts.php?page=' . $page . ($dept > 0 ? '&department=' . $dept : ''), 'danger', 'Portal user not found.');
}

// This page is exclusively for non-HR employees using a dedicated Employee role account
if (($user['role'] ?? '') !== 'Employee') {
    redirectWith(
        BASE_URL . '/admin/users.php?search=' . urlencode($user['username'] ?? ''),
        'info',
        'This employee uses an HR account for portal access. Manage it in User Management.'
    );
}

if (empty($user['employee_id'])) {
    redirectWith(BASE_URL . '/admin/employee-accounts.php?page=' . $page . ($dept > 0 ? '&department=' . $dept : ''), 'danger', 'This portal account is not linked to an employee record.');
}

// ── Handle delete & update ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_portal_user') {
    $confirm = (string)($_POST['confirm_delete'] ?? '');
    if ($confirm !== 'DELETE') {
        redirectWith(
            BASE_URL . "/admin/employee-portal-user.php?user_id=$user_id&page=$page" . ($dept > 0 ? "&department=$dept" : ""),
            'danger',
            'Deletion not confirmed. Type DELETE to confirm.'
        );
    }

    $del = $conn->prepare("DELETE FROM users WHERE user_id = ? AND role = 'Employee' LIMIT 1");
    $del->bind_param("i", $user_id);

    if ($del->execute()) {
        $del->close();
        logAudit($conn, $_SESSION['user_id'], 'DELETE', 'User', $user_id, "Deleted Employee Portal account: {$user['username']}");
        notifyOtherAdmins($conn, (int) $_SESSION['user_id'], 'Employee Portal Account Deleted',
            "An administrator deleted Employee Portal account {$user['username']}.", BASE_URL . '/admin/employee-accounts.php', 'portal');
        redirectWith(BASE_URL . '/admin/employee-accounts.php?page=' . $page . ($dept > 0 ? '&department=' . $dept : ''), 'success', "Employee Portal account '{$user['username']}' deleted successfully.");
    }

    $del->close();
    redirectWith(BASE_URL . "/admin/employee-portal-user.php?user_id=$user_id&page=$page" . ($dept > 0 ? "&department=$dept" : ""), 'danger', 'Failed to delete portal account. Please try again.');
}

// ── Handle update ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_username = trim($_POST['username'] ?? '');
    $new_password = (string)($_POST['password'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $errors = [];
    if ($new_username === '') {
        $errors[] = 'Username is required.';
    }

    if ($new_password !== '' && strlen($new_password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    // Unique username (global)
    if (empty($errors)) {
        $dup = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id <> ? LIMIT 1");
        $dup->bind_param("si", $new_username, $user_id);
        $dup->execute();
        if ($dup->get_result()->num_rows > 0) {
            $errors[] = 'Username already exists.';
        }
        $dup->close();
    }

    if (!empty($errors)) {
        redirectWith(BASE_URL . "/admin/employee-portal-user.php?user_id=$user_id&page=$page" . ($dept > 0 ? "&department=$dept" : ""), 'danger', implode(' ', $errors));
    }

    if ($new_password !== '') {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE users SET username=?, is_active=?, password_hash=?, account_hold=0, account_hold_reason=NULL, account_hold_at=NULL, account_hold_movement_id=NULL WHERE user_id=?");
        $upd->bind_param("sisi", $new_username, $is_active, $hash, $user_id);
    } else {
        $upd = $conn->prepare("UPDATE users SET username=?, is_active=? WHERE user_id=?");
        $upd->bind_param("sii", $new_username, $is_active, $user_id);
    }

    if ($upd->execute()) {
        logAudit($conn, $_SESSION['user_id'], 'UPDATE', 'User', $user_id, "Updated Employee Portal account: {$user['username']} → {$new_username}");
        if ($new_password !== '') logAudit($conn, $_SESSION['user_id'], 'CHANGE_PASSWORD', 'User', $user_id, 'Administrator reset an Employee Portal password.');
        if ((int) ($user['is_active'] ?? 0) !== $is_active) {
            logAudit($conn, $_SESSION['user_id'], 'UPDATE', 'User', $user_id, 'Changed Employee Portal account status.',
                ['previous_value' => (int) $user['is_active'] ? 'Active' : 'Inactive', 'new_value' => $is_active ? 'Active' : 'Inactive']);
        }
        notifyOtherAdmins($conn, (int) $_SESSION['user_id'], 'Employee Portal Account Updated',
            "An administrator updated Employee Portal account {$new_username}.", BASE_URL . '/admin/employee-portal-user.php?user_id=' . $user_id, 'portal');
        if ($new_password !== '') {
            $_SESSION['new_employee_credentials'] = [
                'username'  => $new_username,
                'password'  => $new_password,
                'full_name' => $user['full_name'] ?? (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
            ];
        }
        redirectWith(BASE_URL . "/admin/employee-portal-user.php?user_id=$user_id&page=$page" . ($dept > 0 ? "&department=$dept" : ""), 'success', 'Employee Portal account updated successfully.');
    } else {
        redirectWith(BASE_URL . "/admin/employee-portal-user.php?user_id=$user_id&page=$page" . ($dept > 0 ? "&department=$dept" : ""), 'danger', 'Failed to update portal account. Please try again.');
    }
}

require_once '../includes/header.php';
?>

<?php if ($new_creds): ?>
<!-- Credential Slip Modal -->
<div class="modal fade" id="credentialSlipModal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 380px;">
    <div class="modal-content creds-modal-content">
      <div class="creds-modal-header">
        <div class="creds-icon-ring">
          <i class="fas fa-shield-alt"></i>
        </div>
        <h5 class="modal-title">Employee Credentials</h5>
      </div>
      <div class="modal-body text-center px-4 pt-3 pb-4">
        <p class="text-muted small mb-1">Account updated for:</p>
        <h5 class="fw-bold mb-3" style="color: var(--primary-blue);"><?php echo e($new_creds['full_name']); ?></h5>
        
        <div class="creds-card-box">
          <div class="creds-field-group">
            <div class="creds-field-label">Username</div>
            <div class="creds-input-wrapper">
              <div class="creds-value-display" id="display_username"><?php echo e($new_creds['username']); ?></div>
              <button type="button" class="creds-copy-btn" onclick="copyCredValue(this, '<?php echo e(addslashes($new_creds['username'])); ?>')" title="Copy Username">
                <i class="far fa-copy"></i>
              </button>
            </div>
          </div>
          
          <div class="creds-field-group">
            <div class="creds-field-label">Temporary Password</div>
            <div class="creds-input-wrapper">
              <div class="creds-value-display" id="display_password"><?php echo e($new_creds['password']); ?></div>
              <button type="button" class="creds-copy-btn" onclick="copyCredValue(this, '<?php echo e(addslashes($new_creds['password'])); ?>')" title="Copy Password">
                <i class="far fa-copy"></i>
              </button>
            </div>
          </div>
        </div>

        <div class="creds-warning-callout mb-3">
          <i class="fas fa-exclamation-circle"></i>
          <div>Save and hand these credentials securely to the employee. This slip will not be shown again.</div>
        </div>
        
        <button type="button" class="btn btn-primary w-100 py-2.5 mt-2 fw-bold" style="border-radius: 10px; background: linear-gradient(135deg, var(--primary-blue), var(--primary-dark)); border: none;" data-bs-dismiss="modal">
          I've Noted the Credentials
        </button>
      </div>
    </div>
  </div>
</div>
<script>
function copyCredValue(btn, text) {
    navigator.clipboard.writeText(text).then(() => {
        const icon = btn.querySelector('i');
        icon.className = 'fas fa-check';
        btn.classList.add('copied');
        setTimeout(() => {
            icon.className = 'far fa-copy';
            btn.classList.remove('copied');
        }, 1500);
    });
}
document.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('credentialSlipModal')).show());
</script>
<?php endif; ?>

<!-- ═══════════════════════════════════════════
     Page Header
═══════════════════════════════════════════ -->
<style>
    .admin-portal-user-page { max-width:1050px; margin:0 auto; }
    .admin-portal-user-page .page-hero { position:relative; overflow:hidden; padding:1.2rem 1.35rem; margin-bottom:1rem; }
    .admin-portal-user-page .portal-hero-top { display:flex; justify-content:space-between; align-items:center; gap:1rem; margin-bottom:1rem; }
    .admin-portal-user-page .portal-back { display:inline-flex; align-items:center; gap:.45rem; color:rgba(255,255,255,.82); font-size:.76rem; font-weight:700; text-decoration:none; }
    .admin-portal-user-page .portal-status { display:inline-flex; align-items:center; gap:.4rem; padding:.35rem .65rem; color:#fff; background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.14); border-radius:999px; font-size:.72rem; font-weight:700; }
    .admin-portal-user-page .epu-avatar { width:68px; height:68px; border:3px solid rgba(255,255,255,.35); }
    .admin-portal-user-page .epu-meta-chip { padding:.3rem .55rem; font-size:.7rem; }
    .admin-portal-user-page .epu-form-card { margin-bottom:1rem; border:1px solid #e2eae0; border-radius:15px; box-shadow:0 7px 22px rgba(29,63,31,.06); overflow:hidden; }
    .admin-portal-user-page .epu-form-card-header { padding:.85rem 1.1rem; background:#fff; border-bottom:1px solid #e8eee6; }
    .admin-portal-user-page .epu-form-card-icon { width:36px; height:36px; }
    .admin-portal-user-page .epu-form-card-body { padding:1.05rem 1.1rem; }
    .admin-portal-user-page .epu-field-label { margin-bottom:.35rem; font-size:.75rem; }
    .admin-portal-user-page .epu-form-control { min-height:40px; font-size:.86rem; }
    .admin-portal-user-page .epu-field-hint { margin-top:.3rem; font-size:.7rem; }
    .admin-portal-user-page .epu-switch-row { margin-top:1rem; padding:.75rem .85rem; border-radius:10px; }
    .admin-portal-user-page .epu-form-actions { margin-top:1rem; padding-top:.85rem; }
    .admin-portal-user-page .epu-danger-card { margin-top:0; border-radius:14px; }
    .admin-portal-user-page .epu-danger-card-inner { padding:.85rem 1rem; }
    .admin-portal-user-page .epu-danger-desc { margin-bottom:0; font-size:.75rem; }
    @media (max-width:767.98px) {
        .admin-portal-user-page .page-hero { padding:1rem; }
        .admin-portal-user-page .portal-hero-top { align-items:flex-start; }
        .admin-portal-user-page .epu-form-card-body { padding:.9rem; }
        .admin-portal-user-page .epu-danger-card-inner { align-items:stretch; }
        .admin-portal-user-page .epu-btn-delete { width:100%; }
    }
</style>
<!-- ═══════════════════════════════════════════
     Profile Hero Card
═══════════════════════════════════════════ -->
<div class="admin-portal-user-page">
<div class="page-hero fadeup">
    <div class="portal-hero-top">
        <a href="<?php echo BASE_URL; ?>/admin/employee-accounts.php?page=<?php echo $page; ?><?php echo $dept > 0 ? '&department=' . $dept : ''; ?>" class="portal-back"><i class="fas fa-arrow-left"></i>Portal accounts</a>
        <span class="portal-status"><i class="fas fa-<?php echo !empty($user['is_active']) ? 'check-circle' : 'ban'; ?>"></i><?php echo !empty($user['is_active']) ? 'Account active' : 'Account inactive'; ?></span>
    </div>
    <div class="d-flex align-items-center gap-4 flex-wrap">
        <div class="epu-avatar-wrap">
            <img src="<?php echo getEmployeeAvatar($user['profile_picture']); ?>"
                 alt="Profile" class="epu-avatar">
            <span class="epu-avatar-dot <?php echo !empty($user['is_active']) ? 'online' : 'offline'; ?>"></span>
        </div>
        <div class="epu-hero-info">
            <div style="color:#f3cf62;font-size:.68rem;font-weight:750;letter-spacing:.09em;text-transform:uppercase;margin-bottom:.2rem;">Employee Portal Account</div>
            <h3 class="epu-hero-name" style="color: #ffffff; font-weight: 800; font-size: 1.35rem; margin: 0 0 4px;"><?php echo e($user['last_name'] . ', ' . $user['first_name']); ?></h3>
            <p class="epu-hero-role" style="color: rgba(255,255,255,0.75); font-size: 0.85rem; margin: 0 0 12px;"><?php echo e($user['job_title'] ?? 'Employee'); ?></p>
            <div class="epu-hero-meta">
                <?php if (!empty($user['branch_name'])): ?>
                <span class="epu-meta-chip"><i class="fas fa-building"></i><?php echo e($user['branch_name']); ?></span>
                <?php endif; ?>
                <span class="epu-meta-chip"><i class="fas fa-id-badge"></i><?php echo e(getEmployeeDisplayId($user)); ?></span>
                <span class="epu-meta-chip"><i class="fas fa-user"></i><?php echo e($user['username']); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     Edit Credentials Card
═══════════════════════════════════════════ -->
<div class="epu-form-card fadeup-1">
    <div class="epu-form-card-header">
        <div class="epu-form-card-icon"><i class="fas fa-key"></i></div>
        <div>
            <h6 class="epu-form-card-title">Portal Credentials</h6>
            <p class="epu-form-card-subtitle">Update username, password, or account status</p>
        </div>
    </div>
    <div class="epu-form-card-body">
        <form method="POST" id="updatePortalForm">
            <?php echo csrfField(); ?>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="epu-field-label" for="portal_username">Portal Username</label>
                    <div class="epu-input-group">
                        <span class="epu-input-prefix"><i class="fas fa-at"></i></span>
                        <input type="text" class="epu-form-control" name="username" id="portal_username"
                               value="<?php echo e($user['username']); ?>" required>
                        <button type="button" class="epu-input-action" onclick="setUsernameToEmployeeId()"
                                title="Use Employee ID as username">
                            <i class="fas fa-id-card"></i>
                        </button>
                    </div>
                    <div class="epu-field-hint">Current portal username. Use the ID-card button to restore the Employee ID.</div>
                </div>

                <div class="col-md-6">
                    <label class="epu-field-label" for="portal_password">
                        New Password <span class="epu-optional-tag">optional</span>
                    </label>
                    <div class="epu-input-group">
                        <span class="epu-input-prefix"><i class="fas fa-lock"></i></span>
                        <input type="password" class="epu-form-control" name="password" id="portal_password"
                               minlength="6" placeholder="Leave blank to keep current">
                        <button type="button" class="epu-input-action" onclick="generatePassword()" title="Generate random password">
                            <i class="fas fa-dice"></i>
                        </button>
                        <button type="button" class="epu-input-action" onclick="togglePasswordView()" id="togglePwBtn" title="Show/hide password">
                            <i class="fas fa-eye" id="togglePwIcon"></i>
                        </button>
                    </div>
                    <div class="epu-field-hint">Minimum 6 characters. Use the dice to auto-generate.</div>
                </div>
            </div>

            <div class="epu-switch-row">
                <div class="epu-switch-info">
                    <i class="fas fa-toggle-on epu-switch-icon"></i>
                    <div>
                        <div class="epu-switch-label">Account Status</div>
                        <div class="epu-switch-desc">Disable to block portal login without deleting the account</div>
                    </div>
                </div>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" id="isActive" name="is_active"
                           <?php echo !empty($user['is_active']) ? 'checked' : ''; ?> style="width:2.5em;height:1.4em;cursor:pointer;">
                    <label class="form-check-label visually-hidden" for="isActive">Account is active</label>
                </div>
            </div>

            <div class="epu-form-actions">
                <a href="<?php echo BASE_URL; ?>/admin/employee-accounts.php?page=<?php echo $page; ?><?php echo $dept > 0 ? '&department=' . $dept : ''; ?>" class="epu-btn-cancel">
                    <i class="fas fa-times me-2"></i>Cancel
                </a>
                <button type="submit" class="epu-btn-save">
                    <i class="fas fa-save me-2"></i>Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     Danger Zone Card
═══════════════════════════════════════════ -->
<div class="epu-danger-card fadeup-2">
    <div class="epu-danger-card-inner">
        <div class="epu-danger-icon-wrap"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="epu-danger-info">
            <h6 class="epu-danger-title">Delete Portal Account</h6>
            <p class="epu-danger-desc">This action is irreversible. Deleting may also remove related portal records (e.g., PDS submissions) due to database constraints.</p>
        </div>
        <button type="button" class="epu-btn-delete" data-bs-toggle="modal" data-bs-target="#deletePortalModal">
            <i class="fas fa-trash-alt me-2"></i>Delete Account
        </button>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     Delete Confirmation Modal
═══════════════════════════════════════════ -->
<div class="modal fade" id="deletePortalModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
    <div class="modal-content epu-delete-modal-content">
      <div class="epu-delete-modal-header">
        <div class="epu-delete-modal-icon"><i class="fas fa-trash-alt"></i></div>
        <h5 class="epu-delete-modal-title">Delete Portal Account</h5>
        <p class="epu-delete-modal-sub">This action cannot be undone.</p>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="position:absolute;top:16px;right:18px;"></button>
      </div>
      <div class="epu-delete-modal-body">
        <div class="epu-delete-target-card">
            <div class="epu-delete-target-avatar">
                <img src="<?php echo getEmployeeAvatar($user['profile_picture']); ?>" alt="">
            </div>
            <div>
                <div class="epu-delete-target-name"><?php echo e($user['last_name'] . ', ' . $user['first_name']); ?></div>
                <div class="epu-delete-target-user"><i class="fas fa-at me-1"></i><?php echo e($user['username']); ?></div>
            </div>
        </div>

        <label class="epu-delete-confirm-label">
            Type <strong>DELETE</strong> to confirm
        </label>
        <input type="text" class="epu-delete-confirm-input" id="confirm_delete"
               form="deletePortalForm" name="confirm_delete" autocomplete="off"
               placeholder="DELETE">
      </div>
      <div class="epu-delete-modal-footer">
        <button type="button" class="epu-btn-cancel" data-bs-dismiss="modal">
            <i class="fas fa-times me-2"></i>Cancel
        </button>
        <form method="POST" id="deletePortalForm" class="d-inline">
          <?php echo csrfField(); ?>
          <input type="hidden" name="action" value="delete_portal_user">
          <button type="submit" class="epu-btn-delete-confirm">
            <i class="fas fa-trash-alt me-2"></i>Delete Account
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function setUsernameToEmployeeId() {
    document.getElementById('portal_username').value = '<?php echo e(getEmployeeDisplayId($user)); ?>';
}

function togglePasswordView() {
    const input = document.getElementById('portal_password');
    const icon  = document.getElementById('togglePwIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

function generatePassword() {
    const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*";
    let pass = "";
    for (let i = 0; i < 10; i++) {
        pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    const input = document.getElementById('portal_password');
    input.value = pass;
    input.type = 'text';
}
</script>

</div>
<?php require_once '../includes/footer.php'; ?>
