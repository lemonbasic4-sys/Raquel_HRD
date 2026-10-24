<?php
$page_title = 'User Management';
require_once '../includes/session-check.php';
checkRole(['Admin']);
require_once '../includes/functions.php';
ensureUserAccountSecuritySchema($conn);

// State-changing account actions must be POST requests protected by CSRF.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['toggle_status', 'delete_user'], true)) {
    verifyCsrfToken();
    $action = $_POST['action'];
    $uid = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    if (!$uid) redirectWith(BASE_URL . '/admin/users.php', 'danger', 'Invalid user account.');
    if ($uid === (int) $_SESSION['user_id']) redirectWith(BASE_URL . '/admin/users.php', 'danger', 'You cannot disable or delete your own account.');

    $target_stmt = $conn->prepare("SELECT user_id, username, role, employee_id, is_active FROM users WHERE user_id = ? LIMIT 1");
    $target_stmt->bind_param('i', $uid);
    $target_stmt->execute();
    $target = $target_stmt->get_result()->fetch_assoc();
    $target_stmt->close();
    if (!$target) redirectWith(BASE_URL . '/admin/users.php', 'danger', 'User account was not found.');

    if ($target['role'] === 'Admin' && empty($target['employee_id']) && (int) $target['is_active'] === 1) {
        $admin_count = (int) ($conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'Admin' AND employee_id IS NULL AND is_active = 1")->fetch_assoc()['c'] ?? 0);
        if ($admin_count <= 1) redirectWith(BASE_URL . '/admin/users.php', 'danger', 'The last active administrator cannot be disabled or deleted.');
    }

    if ($action === 'toggle_status') {
        $new_active = (int) $target['is_active'] === 1 ? 0 : 1;
        $stmt = $conn->prepare('UPDATE users SET is_active = ? WHERE user_id = ?');
        $stmt->bind_param('ii', $new_active, $uid);
        $stmt->execute();
        $changed = $stmt->affected_rows > 0;
        $stmt->close();
        if ($changed) {
            $old_label = $new_active ? 'Inactive' : 'Active';
            $new_label = $new_active ? 'Active' : 'Inactive';
            logAudit($conn, $_SESSION['user_id'], 'UPDATE', 'User', $uid, 'Changed user account status to ' . $new_label . '.', ['previous_value' => $old_label, 'new_value' => $new_label]);
        }
        redirectWith(BASE_URL . '/admin/users.php', 'success', 'User status updated successfully.');
    }

    $stmt = $conn->prepare('DELETE FROM users WHERE user_id = ?');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $deleted = $stmt->affected_rows > 0;
    $stmt->close();
    if ($deleted) {
        logAudit($conn, $_SESSION['user_id'], 'DELETE', 'User', $uid, 'Deleted user account: ' . $target['username']);
        redirectWith(BASE_URL . '/admin/users.php', 'success', 'User deleted successfully.');
    }
    redirectWith(BASE_URL . '/admin/users.php', 'danger', 'User could not be deleted.');
}

require_once '../includes/header.php';

// Pagination settings
$per_page = 10;
$current_page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($current_page - 1) * $per_page;

// Count only HR system users (Admin or valid HR employees only)
$total_users_result = $conn->query("
    SELECT COUNT(*) AS total
    FROM users u
    LEFT JOIN employees e ON u.employee_id = e.employee_id
    WHERE (u.role = 'Admin')
       OR (u.role IN ('HR Manager', 'HR Supervisor', 'HR Staff')
           AND e.employee_id IS NOT NULL
           AND e.department_id = (SELECT department_id FROM departments WHERE department_name = 'Human Resources' LIMIT 1))
");
$total_users = (int) ($total_users_result->fetch_assoc()['total'] ?? 0);
$active_users_result = $conn->query("\n    SELECT COUNT(*) AS total\n    FROM users u\n    LEFT JOIN employees e ON u.employee_id = e.employee_id\n    WHERE u.is_active = 1 AND COALESCE(u.account_hold, 0) = 0\n      AND ((u.role = 'Admin')\n       OR (u.role IN ('HR Manager', 'HR Supervisor', 'HR Staff')\n           AND e.employee_id IS NOT NULL\n           AND e.department_id = (SELECT department_id FROM departments WHERE department_name = 'Human Resources' LIMIT 1)))\n");
$active_users_count = (int) ($active_users_result->fetch_assoc()['total'] ?? 0);
$inactive_users_result = $conn->query("\n    SELECT COUNT(*) AS total\n    FROM users u\n    LEFT JOIN employees e ON u.employee_id = e.employee_id\n    WHERE u.is_active = 0\n      AND ((u.role = 'Admin')\n       OR (u.role IN ('HR Manager', 'HR Supervisor', 'HR Staff')\n           AND e.employee_id IS NOT NULL\n           AND e.department_id = (SELECT department_id FROM departments WHERE department_name = 'Human Resources' LIMIT 1)))\n");
$inactive_users_count = (int) ($inactive_users_result->fetch_assoc()['total'] ?? 0);
$held_users_result = $conn->query("
    SELECT COUNT(*) AS total
    FROM users u
    LEFT JOIN employees e ON u.employee_id = e.employee_id
    WHERE u.account_hold = 1
      AND ((u.role = 'Admin')
       OR (u.role IN ('HR Manager', 'HR Supervisor', 'HR Staff')
           AND e.employee_id IS NOT NULL
           AND e.department_id = (SELECT department_id FROM departments WHERE department_name = 'Human Resources' LIMIT 1)))
");
$held_users_count = (int) ($held_users_result->fetch_assoc()['total'] ?? 0);
$total_pages = max(1, (int) ceil($total_users / $per_page));

if ($current_page > $total_pages) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $per_page;
}

// Fetch HR system users
$users = $conn->query("
    SELECT u.*, u.profile_picture AS user_profile_picture, b.branch_name,
           e.profile_picture AS employee_profile_picture, e.job_title, rc.rank_name
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.branch_id
    LEFT JOIN employees e ON u.employee_id = e.employee_id
    LEFT JOIN rank_categories rc ON e.rank_category_id = rc.rank_category_id
    WHERE (u.role = 'Admin')
       OR (u.role IN ('HR Manager', 'HR Supervisor', 'HR Staff')
           AND e.employee_id IS NOT NULL
           AND e.department_id = (SELECT department_id FROM departments WHERE department_name = 'Human Resources' LIMIT 1))
    ORDER BY u.created_at DESC
    LIMIT $per_page OFFSET $offset
");

// Only regular, active HR employees are eligible for a new HRIS account.
$eligible_employees = $conn->query("
    SELECT e.employee_id, e.employee_code, e.first_name, e.last_name, e.job_title, e.branch_id, e.profile_picture,
           rc.rank_name, ec.personal_email, b.branch_name
    FROM employees e
    JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN rank_categories rc ON e.rank_category_id = rc.rank_category_id
    LEFT JOIN employee_contacts ec ON e.employee_id = ec.employee_id
    LEFT JOIN branches b ON e.branch_id = b.branch_id
    LEFT JOIN users u ON e.employee_id = u.employee_id AND u.role != 'Employee'
    WHERE u.user_id IS NULL
      AND e.is_active = 1
      AND e.deleted_at IS NULL
      AND e.employment_status = 'Regular'
      AND d.department_name = 'Human Resources'
    ORDER BY e.last_name, e.first_name
");

// Grab and clear any pending credential slip
$new_creds = $_SESSION['new_employee_credentials'] ?? null;
unset($_SESSION['new_employee_credentials']);
?>

<?php if ($new_creds): ?>
<!-- Credential Slip Modal — shown once after creating any account -->
<div class="modal fade" id="credentialSlipModal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 380px;">
    <div class="modal-content creds-modal-content">
      <div class="creds-modal-header">
        <div class="creds-icon-ring">
          <i class="fas fa-shield-alt"></i>
        </div>
        <h5 class="modal-title">Account Credentials</h5>
      </div>
      <div class="modal-body text-center px-4 pt-3 pb-4">
        <p class="text-muted small mb-1">Account created successfully for:</p>
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
          <div>Save and hand these credentials securely to the user. This slip will not be shown again.</div>
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

<!-- Page Header -->
<style>
    .user-management .page-hero { position: relative; overflow: hidden; }
    .user-management .page-hero::after { content: ''; position: absolute; width: 190px; height: 190px; right: -78px; top: -100px; border: 1px solid rgba(255,255,255,.13); border-radius: 50%; box-shadow: 0 0 0 26px rgba(255,255,255,.035), 0 0 0 52px rgba(255,255,255,.02); pointer-events: none; }
    .user-management .hero-content { position: relative; z-index: 1; }
    .user-management .access-overview { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:1rem; margin-bottom:1.5rem; }
    .user-management .overview-stat { position:relative; display:flex; align-items:center; gap:.9rem; min-width:0; padding:1rem 1.05rem; border-radius:14px; overflow:hidden; }
    .user-management .access-overview .overview-stat.page-hero { margin:0; padding:1rem 1.05rem; min-height:92px; background:linear-gradient(135deg,var(--hero-from) 0%,var(--hero-mid) 50%,var(--hero-to) 100%); border:1px solid rgba(255,255,255,.1); box-shadow:0 8px 22px rgba(23,75,18,.16); }
    .user-management .overview-stat::before { content:''; position:absolute; inset:0; opacity:.07; background-image:radial-gradient(circle at 1px 1px,#fff 1px,transparent 0); background-size:18px 18px; pointer-events:none; }
    .user-management .overview-stat::after { content:''; position:absolute; width:72px; height:72px; right:-30px; bottom:-36px; border:1px solid rgba(255,255,255,.12); border-radius:50%; background:rgba(255,255,255,.025); box-shadow:0 0 0 18px rgba(255,255,255,.025); }
    .user-management .overview-icon { position:relative; z-index:1; width:42px; height:42px; flex:0 0 42px; display:grid; place-items:center; border-radius:12px; color:#f4cb53; background:rgba(255,255,255,.1); }
    .user-management .overview-copy { min-width:0; }
    .user-management .overview-value, .user-management .overview-value.text-success, .user-management .overview-value.text-danger, .user-management .overview-value.text-warning { position:relative; z-index:1; color:#fff !important; font-size:1.55rem; font-weight:800; line-height:1.1; letter-spacing:-.04em; }
    .user-management .overview-label { position:relative; z-index:1; color:rgba(255,255,255,.67); font-size:.7rem; font-weight:700; letter-spacing:.055em; text-transform:uppercase; }
    .user-management .users-card { border-radius:16px; overflow:hidden; }
    .user-management .users-card > .card-header { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:1.05rem 1.25rem; }
    .user-management .account-count-badge { display:inline-flex; align-items:center; gap:.35rem; padding:.38rem .68rem; color:#365d38; background:#edf5eb; border-radius:999px; font-size:.75rem; font-weight:700; }
    .user-management .user-toolbar { display:grid !important; grid-template-columns:minmax(220px,1fr) 170px 170px; background:#fbfdfb; border-bottom:1px solid var(--glass-border); border-top:1px solid var(--glass-border); padding:.9rem 1.25rem; }
    .user-management .user-toolbar .search-box { width:100%; }
    .user-management .user-toolbar .form-select { width:100%; min-width:0; }
    .user-management .users-table { margin:0; }
    .user-management .users-table th { white-space:nowrap; padding:.8rem .7rem; color:#536455; background:#f1f6ef; border-bottom:1px solid #dfe9dc; font-size:.7rem; letter-spacing:.045em; text-transform:uppercase; }
    .user-management .users-table td { vertical-align: middle; }
    .user-management .users-table tbody tr { transition:background-color .18s ease; border-color:#edf1eb; }
    .user-management .users-table tbody td { padding:.75rem .7rem; }
    .user-management .users-table td:last-child { white-space:nowrap; }
    .user-management .user-count { color: var(--text-muted); font-size: .82rem; }
    .account-create-modal .modal-dialog { max-width:680px; margin-top:1.5rem; margin-bottom:1.5rem; height:auto; min-height:calc(100% - 3rem); }
    .account-create-modal .modal-content { max-height:calc(100vh - 3rem); max-height:calc(100dvh - 3rem); border:0; border-radius:16px; overflow:hidden; box-shadow:0 22px 60px rgba(12,42,15,.22); }
    .account-create-modal .modal-header { padding:1rem 1.2rem; color:#fff; background:linear-gradient(125deg,#174b12,#2f6d1b); border:0; }
    .account-create-modal .modal-title { display:flex; align-items:center; gap:.65rem; font-size:1rem; font-weight:750; }
    .account-create-modal .modal-title-icon { width:34px; height:34px; display:grid; place-items:center; border-radius:10px; color:#f4cb53; background:rgba(255,255,255,.1); }
    .account-create-modal .btn-close { filter:brightness(0) invert(1); opacity:.8; }
    .account-create-modal .modal-body { min-height:0; overflow-y:auto; padding:1rem 1.2rem .75rem; }
    .account-create-modal .compact-form-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.75rem 1rem; }
    .account-create-modal .form-span-2 { grid-column:1 / -1; }
    .account-create-modal .form-field { min-width:0; }
    .account-create-modal .form-label { margin-bottom:.35rem; color:#2c3d2e; font-size:.78rem; font-weight:700; }
    .account-create-modal .form-control, .account-create-modal .form-select { min-height:40px; border-color:#d8e2d6; border-radius:9px; font-size:.86rem; }
    .account-create-modal input[type="file"].form-control { padding:.47rem .65rem; }
    .account-create-modal .employee-photo-source { min-height:40px; display:flex; align-items:center; gap:.7rem; padding:.35rem .55rem; background:#f7faf6; border:1px solid #d8e2d6; border-radius:9px; }
    .account-create-modal .employee-photo-source img { width:32px; height:32px; flex:0 0 32px; object-fit:cover; border-radius:50%; border:1px solid #d6e0d4; background:#fff; }
    .account-create-modal .form-text { margin-top:.3rem; font-size:.7rem; line-height:1.35; }
    .account-create-modal .account-note { display:flex; align-items:flex-start; gap:.65rem; margin:0 0 .9rem; padding:.7rem .8rem; color:#365b39; background:#f1f7ef; border:1px solid #dcebd8; border-radius:10px; font-size:.76rem; }
    .account-create-modal .modal-footer { flex:0 0 auto; padding:.85rem 1.2rem 1.15rem; background:#fafcfa; border-top:1px solid #e8eee6; }
    .account-create-modal .modal-footer .btn { min-width:112px; border-radius:9px; }
    @media (max-width: 767.98px) {
        .user-management .hero-actions { width: 100%; }
        .user-management .hero-actions .btn { flex: 1; }
        .user-management .access-overview { grid-template-columns:repeat(2,minmax(0,1fr)); gap:.75rem; }
        .user-management .overview-stat { padding:.85rem; }
        .user-management .overview-icon { width:36px; height:36px; flex-basis:36px; }
        .user-management .overview-value { font-size: 1.35rem; }
        .user-management .user-toolbar { grid-template-columns:1fr; padding:.75rem 1rem; }
        .user-management .user-toolbar .search-box, .user-management .user-toolbar .form-select { width: 100%; }
        .account-create-modal .modal-dialog { margin:1rem .75rem; min-height:calc(100% - 2rem); }
        .account-create-modal .modal-content { max-height:calc(100vh - 2rem); max-height:calc(100dvh - 2rem); }
        .account-create-modal .compact-form-grid { grid-template-columns:1fr; }
        .account-create-modal .form-span-2 { grid-column:auto; }
        .account-create-modal .modal-footer .btn { flex:1; min-width:0; }
    }
    @media (min-width:768px) and (max-width:991.98px) {
        .user-management .access-overview { grid-template-columns:repeat(2,minmax(0,1fr)); }
        .user-management .user-toolbar { grid-template-columns:1fr 150px 150px; }
    }
</style>

<div class="user-management">
<div class="page-hero fadeup">
    <div class="hero-content">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-3">
        <div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">System Admin · Access Control</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-users-cog me-2" style="color:var(--primary-light);"></i>User Management</h4>
        </div>
        <div class="hero-actions d-flex gap-2 flex-wrap justify-content-end">
            <button class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                <i class="fas fa-user-shield me-2"></i>Add New Admin
            </button>
            <button class="btn btn-light text-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fas fa-plus me-2"></i>Add New User
            </button>
        </div>
    </div>
    <p class="text-white-50 small mb-0"><i class="fas fa-lock me-1"></i>Manage system user accounts, roles, and HR access permissions.</p>
    </div>
</div>

<div class="access-overview" data-flash-toast-anchor>
    <div class="overview-stat page-hero fadeup">
        <div class="overview-icon"><i class="fas fa-users"></i></div><div class="overview-copy"><div class="overview-value"><?php echo $total_users; ?></div><div class="overview-label">HRIS accounts</div></div>
    </div>
    <div class="overview-stat page-hero fadeup">
        <div class="overview-icon"><i class="fas fa-user-check"></i></div><div class="overview-copy"><div class="overview-value text-success"><?php echo $active_users_count; ?></div><div class="overview-label">Active</div></div>
    </div>
    <div class="overview-stat page-hero fadeup">
        <div class="overview-icon"><i class="fas fa-user-slash"></i></div><div class="overview-copy"><div class="overview-value text-danger"><?php echo $inactive_users_count; ?></div><div class="overview-label">Inactive</div></div>
    </div>
    <div class="overview-stat page-hero fadeup">
        <div class="overview-icon"><i class="fas fa-user-lock"></i></div><div class="overview-copy"><div class="overview-value text-warning"><?php echo $held_users_count; ?></div><div class="overview-label">On hold</div></div>
    </div>
</div>

<!-- Users Table -->
<div class="content-card users-card fadeup-1">
    <div class="card-header">
        <div>
            <h5><i class="fas fa-users me-2"></i>All Users</h5>
            <div class="user-count mt-1">Search, filter, and manage system access.</div>
        </div>
        <span class="account-count-badge"><i class="fas fa-database"></i><?php echo $total_users; ?> account<?php echo $total_users === 1 ? '' : 's'; ?></span>
    </div>
    <div class="user-toolbar d-flex flex-wrap align-items-center gap-2">
        <div class="search-box flex-grow-1">
            <i class="fas fa-search search-icon"></i>
            <input type="search" class="form-control form-control-sm" id="searchUsers" placeholder="Search name, username, email...">
        </div>
        <select class="form-select form-select-sm" id="roleFilter" aria-label="Filter by role">
            <option value="">All roles</option>
            <option value="Admin">Admin</option>
            <option value="HR Manager">HR Manager</option>
            <option value="HR Supervisor">HR Supervisor</option>
            <option value="HR Staff">HR Staff</option>
        </select>
        <select class="form-select form-select-sm" id="statusFilter" aria-label="Filter by account status">
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="hold">On hold</option>
        </select>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive d-none d-md-block">
            <table class="table table-hover users-table" id="usersTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Photo</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Position</th>
                        <th>Branch</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $row_number = $offset + 1; ?>
                    <?php while ($user = $users->fetch_assoc()): ?>
                        <tr data-role="<?php echo e($user['role']); ?>" data-status="<?php echo !empty($user['account_hold']) ? 'hold' : ($user['is_active'] ? 'active' : 'inactive'); ?>">
                            <td><strong><?php echo $row_number++; ?></strong></td>
                            <td>
                                <img src="<?php echo getUserAvatar($user['user_profile_picture'], $user['employee_profile_picture']); ?>"
                                    alt="Profile" class="rounded-circle"
                                    style="width: 32px; height: 32px; object-fit: cover;">
                            </td>
                            <td><strong><?php echo e($user['username']); ?></strong></td>
                            <td><?php echo e($user['full_name']); ?></td>
                            <td><?php echo e($user['email']); ?></td>
                            <td><span class="badge bg-primary"><?php echo e($user['role']); ?></span></td>
                            <td>
                                <?php if ($user['role'] === 'Admin'): ?>
                                    <span class="text-muted small">Standalone admin account</span>
                                <?php elseif (!empty($user['job_title'])): ?>
                                    <div><?php echo e($user['job_title']); ?></div>
                                    <?php if (!empty($user['rank_name'])): ?>
                                        <small class="text-muted"><?php echo e($user['rank_name']); ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted small">Standalone account</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $user['role'] === 'Admin' ? '<span class="text-muted small">None</span>' : e($user['branch_name'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="badge <?php echo !empty($user['account_hold']) ? 'bg-warning text-dark' : ($user['is_active'] ? 'bg-success' : 'bg-danger'); ?>">
                                    <?php echo !empty($user['account_hold']) ? 'On Hold' : ($user['is_active'] ? 'Active' : 'Inactive'); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                    <!-- Edit -->
                                    <a href="<?php echo BASE_URL; ?>/admin/edit-user.php?id=<?php echo $user['user_id']; ?>"
                                        class="btn btn-sm btn-outline-primary" title="Edit User">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <!-- Toggle Status -->
                                    <form method="post" class="d-inline">
                                        <?php echo csrfField(); ?><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="user_id" value="<?php echo (int) $user['user_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-warning" title="Toggle Active/Inactive"><i class="fas fa-power-off"></i></button>
                                    </form>
                                    <!-- Delete -->
                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Delete User"
                                        onclick="setDeleteTarget(<?php echo $user['user_id']; ?>, '<?php echo e(addslashes($user['username'])); ?>')"
                                        data-bs-toggle="modal" data-bs-target="#deleteModal">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php else: ?>
                                    <small class="text-muted">Current User</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile user list -->
        <div class="mobile-list-view d-block d-md-none">
            <div class="student-list" id="mobileUsersList">
                <?php $row_number_mob = $offset + 1; ?>
                <?php if ($users->num_rows === 0): ?>
                    <div class="text-center py-4 text-muted">No users found.</div>
                <?php else: ?>
                    <?php 
                    $users->data_seek(0);
                    while ($user = $users->fetch_assoc()): 
                        $avatar_num = ($row_number_mob % 6) + 1;
                        // Split full name to get initials
                        $name_parts = explode(' ', trim($user['full_name'] ?? ''));
                        $first_part = $name_parts[0] ?? '';
                        $last_part = end($name_parts);
                        if ($first_part === $last_part) {
                            $initials = strtoupper(substr($first_part, 0, 2));
                        } else {
                            $initials = strtoupper(substr($first_part, 0, 1) . substr($last_part, 0, 1));
                        }
                        if (empty($initials)) $initials = 'US';
                    ?>
                        <div class="student-item" data-role="<?php echo e($user['role']); ?>" data-status="<?php echo !empty($user['account_hold']) ? 'hold' : ($user['is_active'] ? 'active' : 'inactive'); ?>">
                            <div class="student-avatar">
                                <img src="<?php echo getUserAvatar($user['user_profile_picture'], $user['employee_profile_picture']); ?>" alt="Profile" class="avatar-img">
                            </div>
                            <div class="student-info">
                                <div class="student-name">
                                    <?php echo e($user['full_name']); ?>
                                </div>
                                <div class="student-meta">
                                    Username: <code><?php echo e($user['username']); ?></code> &bull; <span class="badge bg-primary"><?php echo e($user['role']); ?></span>
                                </div>
                                <div class="student-meta" style="margin-top: 2px;">
                                    <?php if ($user['role'] === 'Admin'): ?>
                                        <span class="text-muted small">Standalone admin account</span>
                                    <?php elseif (!empty($user['job_title'])): ?>
                                        <span><?php echo e($user['job_title']); ?></span>
                                        <?php if (!empty($user['rank_name'])): ?>
                                            <span class="text-muted small">(<?php echo e($user['rank_name']); ?>)</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted small">Standalone Account</span>
                                    <?php endif; ?>
                                </div>
                                <div class="student-meta" style="margin-top: 2px;">
                                    <span>Branch: <?php echo $user['role'] === 'Admin' ? 'None' : e($user['branch_name'] ?? 'N/A'); ?></span> &bull; <span><?php echo e($user['email']); ?></span>
                                </div>
                                <div class="student-meta" style="margin-top: 4px;">
                                    Status: 
                                    <span class="badge <?php echo !empty($user['account_hold']) ? 'bg-warning text-dark' : ($user['is_active'] ? 'bg-success' : 'bg-danger'); ?>">
                                        <?php echo !empty($user['account_hold']) ? 'On Hold' : ($user['is_active'] ? 'Active' : 'Inactive'); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="ms-auto text-end d-flex flex-column gap-2 justify-content-center">
                                <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                    <div class="d-flex gap-1">
                                        <!-- Edit -->
                                        <a href="<?php echo BASE_URL; ?>/admin/edit-user.php?id=<?php echo $user['user_id']; ?>"
                                            class="btn btn-sm btn-outline-primary" title="Edit User" style="padding: 5px 8px; border-radius: 6px;">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <!-- Toggle Status -->
                                        <form method="post" class="d-inline">
                                            <?php echo csrfField(); ?><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="user_id" value="<?php echo (int) $user['user_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-warning" title="Toggle Active/Inactive" style="padding: 5px 8px; border-radius: 6px;"><i class="fas fa-power-off"></i></button>
                                        </form>
                                        <!-- Delete -->
                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Delete User"
                                            onclick="setDeleteTarget(<?php echo $user['user_id']; ?>, '<?php echo e(addslashes($user['username'])); ?>')"
                                            data-bs-toggle="modal" data-bs-target="#deleteModal" style="padding: 5px 8px; border-radius: 6px;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <small class="text-muted fw-bold">You</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php 
                        $row_number_mob++;
                    endwhile; 
                    ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php if ($total_pages > 1): ?>
        <div class="card-footer bg-white border-0 pt-0">
            <nav aria-label="Users pagination">
                <ul class="pagination pagination-sm justify-content-end mb-0">
                    <?php $query_params = $_GET; unset($query_params['page']); ?>
                    <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($query_params, ['page' => $current_page - 1])); ?>">Previous</a>
                    </li>
                    <?php 
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $start_page + 4);
                    if ($end_page - $start_page < 4) {
                        $start_page = max(1, $end_page - 4);
                    }

                    if ($start_page > 1) {
                        ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($query_params, ['page' => 1])); ?>">1</a>
                        </li>
                        <?php if ($start_page > 2): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <?php
                    }

                    for ($p = $start_page; $p <= $end_page; $p++) {
                        ?>
                        <li class="page-item <?php echo $p === $current_page ? 'active' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($query_params, ['page' => $p])); ?>"><?php echo $p; ?></a>
                        </li>
                        <?php
                    }

                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($query_params, ['page' => $total_pages])); ?>"><?php echo $total_pages; ?></a>
                        </li>
                        <?php
                    }
                    ?>
                    <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($query_params, ['page' => $current_page + 1])); ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

</div>

<!-- Add User Modal -->
<div class="modal fade account-create-modal" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><span class="modal-title-icon"><i class="fas fa-user-plus"></i></span>Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?php echo BASE_URL; ?>/admin/add-user.php">
                <?php echo csrfField(); ?>
                <div class="modal-body"><div class="compact-form-grid">
                    <div class="form-field form-span-2">
                        <label class="form-label">Select Employee <span class="text-danger">*</span></label>
                        <select class="form-select" name="employee_id" required onchange="prefillUserInfo(this)">
                            <option value="">Select Employee</option>
                            <?php while ($emp = $eligible_employees->fetch_assoc()): ?>
                                <option value="<?php echo $emp['employee_id']; ?>"
                                    data-name="<?php echo e($emp['first_name'] . ' ' . $emp['last_name']); ?>"
                                    data-email="<?php echo e($emp['personal_email']); ?>"
                                    data-rank="<?php echo e($emp['rank_name'] ?? ''); ?>"
                                    data-job-title="<?php echo e($emp['job_title'] ?? ''); ?>"
                                    data-branch-id="<?php echo (int) ($emp['branch_id'] ?? 0); ?>"
                                    data-branch-name="<?php echo e($emp['branch_name'] ?? 'Not assigned'); ?>"
                                    data-avatar="<?php echo e(getUserAvatar(null, $emp['profile_picture'] ?? null)); ?>">
                                    <?php echo e($emp['last_name'] . ', ' . $emp['first_name']); ?>
                                    <?php if (!empty($emp['job_title'])): ?> - <?php echo e($emp['job_title']); ?><?php endif; ?>
                                    <?php if (!empty($emp['employee_code'])): ?> (<?php echo e($emp['employee_code']); ?>)<?php endif; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <div class="form-text">Only active, regular Human Resources employees without an HRIS account are eligible.</div>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="username" id="new_username" required>
                        <div class="form-text">Use a custom HR username. Do not use the Employee ID.</div>
                    </div>
                    <input type="hidden" name="full_name" id="new_full_name">
                    <input type="hidden" name="email" id="new_email">
                    <input type="hidden" name="redirect" value="users.php">
                    <div class="form-field">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <input type="text" class="form-control bg-light" id="new_role_display" value="Select an employee" readonly aria-describedby="roleAutoHelp">
                        <input type="hidden" name="role" id="new_role" value="">
                        <div class="form-text" id="roleAutoHelp"><i class="fas fa-lock me-1"></i>Automatically assigned from the employee's position.</div>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Branch</label>
                        <input type="text" class="form-control bg-light" id="new_branch_display" value="Select an employee" readonly>
                        <input type="hidden" name="branch_id" id="new_branch_id" value="">
                        <div class="form-text"><i class="fas fa-lock me-1"></i>Uses the employee's assigned branch.</div>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Account Profile Picture</label>
                        <div class="employee-photo-source">
                            <img id="new_employee_avatar" src="<?php echo e(getUserAvatar(null, null)); ?>" alt="Employee profile preview">
                            <span class="small text-muted" id="new_employee_avatar_text">Select an employee</span>
                        </div>
                        <div class="form-text"><i class="fas fa-lock me-1"></i>Automatically uses the employee profile picture.</div>
                    </div>
                    <div class="form-field form-span-2">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" required minlength="6">
                    </div>
                </div></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Admin Modal -->
<div class="modal fade account-create-modal" id="addAdminModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><span class="modal-title-icon"><i class="fas fa-user-shield"></i></span>Add New Admin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?php echo BASE_URL; ?>/admin/add-user.php">
                <?php echo csrfField(); ?>
                <div class="modal-body">
                    <div class="account-note form-span-2">
                        <i class="fas fa-info-circle mt-1"></i><span>Admin accounts are standalone and are not linked to employee records.</span>
                    </div>
                    <input type="hidden" name="role" value="Admin">
                    <input type="hidden" name="redirect" value="users.php">
                    <div class="compact-form-grid">
                    <div class="form-field">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="full_name" required>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="username" required>
                        <div class="form-text">Use a custom admin username.</div>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" required minlength="6">
                    </div></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Create Admin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Delete User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <p>Are you sure you want to delete user <strong id="deleteUserName"></strong>?</p>
                <p class="text-danger small">This action cannot be undone.</p>
            </div>
            <form method="post">
                <?php echo csrfField(); ?><input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" id="deleteUserId" value="">
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash me-1"></i>Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function setDeleteTarget(userId, username) {
        document.getElementById('deleteUserName').textContent = username;
        document.getElementById('deleteUserId').value = userId;
    }

    function prefillUserInfo(select) {
        const option = select.options[select.selectedIndex];
        const roleField = document.getElementById('new_role');
        const roleDisplay = document.getElementById('new_role_display');
        const branchField = document.getElementById('new_branch_id');
        const branchDisplay = document.getElementById('new_branch_display');
        const avatar = document.getElementById('new_employee_avatar');
        const avatarText = document.getElementById('new_employee_avatar_text');
        if (!option.value) {
            document.getElementById('new_full_name').value = '';
            document.getElementById('new_email').value = '';
            roleField.value = '';
            roleDisplay.value = 'Select an employee';
            branchField.value = '';
            branchDisplay.value = 'Select an employee';
            avatar.src = '<?php echo e(getUserAvatar(null, null)); ?>';
            avatarText.textContent = 'Select an employee';
            return;
        }

        document.getElementById('new_full_name').value = option.getAttribute('data-name');
        document.getElementById('new_email').value = option.getAttribute('data-email') || '';

        const usernameField = document.getElementById('new_username');
        const position = ((option.getAttribute('data-rank') || '') + ' ' + (option.getAttribute('data-job-title') || '')).toLowerCase();

        if (!usernameField.value || usernameField.value === option.value) {
            const name = option.getAttribute('data-name').toLowerCase().replace(/\s+/g, '.');
            usernameField.value = name;
        }

        const assignedRole = position.includes('manager') ? 'HR Manager' : (position.includes('supervisor') ? 'HR Supervisor' : 'HR Staff');
        roleField.value = assignedRole;
        roleDisplay.value = assignedRole;
        branchField.value = option.getAttribute('data-branch-id') || '';
        branchDisplay.value = option.getAttribute('data-branch-name') || 'Not assigned';
        avatar.src = option.getAttribute('data-avatar') || '<?php echo e(getUserAvatar(null, null)); ?>';
        avatarText.textContent = 'Inherited from employee profile';
    }

    document.addEventListener('DOMContentLoaded', function () {
        const empSelect = document.querySelector('#addUserModal [name="employee_id"]');
        const usernameField = document.getElementById('new_username');
        if (empSelect && usernameField) {
            empSelect.addEventListener('change', function () {
                const opt = empSelect.options[empSelect.selectedIndex];
                if (!opt || !opt.value) return;
                if (!usernameField.value || usernameField.value === opt.value || /^\d+$/.test(usernameField.value)) {
                    usernameField.value = opt.getAttribute('data-name')?.toLowerCase().replace(/\s+/g, '.') || '';
                }
            });
        }

        const searchUsers = document.getElementById('searchUsers');
        const roleFilter = document.getElementById('roleFilter');
        const statusFilter = document.getElementById('statusFilter');

        function filterUsers() {
            const searchTerm = (searchUsers?.value || '').trim().toLowerCase();
            const selectedRole = roleFilter?.value || '';
            const selectedStatus = statusFilter?.value || '';

            document.querySelectorAll('#usersTable tbody tr, #mobileUsersList .student-item').forEach(function (item) {
                const matchesSearch = !searchTerm || item.textContent.toLowerCase().includes(searchTerm);
                const matchesRole = !selectedRole || item.dataset.role === selectedRole;
                const matchesStatus = !selectedStatus || item.dataset.status === selectedStatus;
                item.style.display = matchesSearch && matchesRole && matchesStatus ? '' : 'none';
            });
        }

        searchUsers?.addEventListener('input', filterUsers);
        roleFilter?.addEventListener('change', filterUsers);
        statusFilter?.addEventListener('change', filterUsers);
    });
</script>

<?php require_once '../includes/footer.php'; ?>
