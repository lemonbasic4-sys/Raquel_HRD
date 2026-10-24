<?php
$page_title = 'Admin Dashboard';
require_once '../includes/session-check.php';
checkRole(['Admin']);
require_once '../includes/header.php';

// Fetch stats - only count valid HR system users (Admin and HR roles), exclude orphaned accounts
$total_users = $conn->query("
    SELECT COUNT(*) as c 
    FROM users u
    LEFT JOIN employees e ON u.employee_id = e.employee_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    WHERE (u.role = 'Admin' AND u.employee_id IS NULL)
       OR (u.role IN ('HR Manager', 'HR Supervisor', 'HR Staff') 
           AND e.employee_id IS NOT NULL 
           AND d.department_name = 'Human Resources')
")->fetch_assoc()['c'];
$active_users = $conn->query("
    SELECT COUNT(*) as c 
    FROM users u
    LEFT JOIN employees e ON u.employee_id = e.employee_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    WHERE u.is_active = 1
      AND ((u.role = 'Admin' AND u.employee_id IS NULL)
       OR (u.role IN ('HR Manager', 'HR Supervisor', 'HR Staff') 
           AND e.employee_id IS NOT NULL 
           AND d.department_name = 'Human Resources'))
")->fetch_assoc()['c'];
$total_branches = $conn->query("SELECT COUNT(*) as c FROM branches WHERE deleted_at IS NULL")->fetch_assoc()['c'];
$recent_logs = $conn->query("SELECT COUNT(*) as c FROM audit_logs WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetch_assoc()['c'];

// 1. Employee Account Issuance Stats
$total_employees = $conn->query("SELECT COUNT(*) as c FROM employees WHERE is_active = 1")->fetch_assoc()['c'];
$employees_with_accounts = $conn->query("SELECT COUNT(DISTINCT employee_id) as c FROM users WHERE employee_id IS NOT NULL")->fetch_assoc()['c'];
$employees_no_accounts = max(0, $total_employees - $employees_with_accounts);
$issued_pct = ($total_employees > 0) ? ($employees_with_accounts / $total_employees) * 100 : 0;

// --- SYSTEM ANALYTICS ---

// 1. User Roles Distribution - only valid HR system users, excludes orphaned accounts
$roles_res = $conn->query("
    SELECT u.role, COUNT(*) as count 
    FROM users u
    LEFT JOIN employees e ON u.employee_id = e.employee_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    WHERE (u.role = 'Admin' AND u.employee_id IS NULL)
       OR (u.role IN ('HR Manager', 'HR Supervisor', 'HR Staff') 
           AND e.employee_id IS NOT NULL 
           AND d.department_name = 'Human Resources')
    GROUP BY u.role
");
$role_labels = [];
$role_counts = [];
while ($row = $roles_res->fetch_assoc()) {
    $role_labels[] = $row['role'];
    $role_counts[] = (int) $row['count'];
}

// 2. Account Status Breakdown - only valid HR system users
$status_res = $conn->query("
    SELECT u.is_active, COUNT(*) as count 
    FROM users u
    LEFT JOIN employees e ON u.employee_id = e.employee_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    WHERE (u.role = 'Admin' AND u.employee_id IS NULL)
       OR (u.role IN ('HR Manager', 'HR Supervisor', 'HR Staff') 
           AND e.employee_id IS NOT NULL 
           AND d.department_name = 'Human Resources')
    GROUP BY u.is_active
");
$status_labels = ['Inactive', 'Active'];
$status_counts = [0, 0];
while ($row = $status_res->fetch_assoc()) {
    $status_counts[(int) $row['is_active']] = (int) $row['count'];
}

// 3. System Activity (Last 7 Days)
$activity_res = $conn->query("SELECT DATE_FORMAT(timestamp, '%b %d') as label, COUNT(*) as count 
                              FROM audit_logs 
                              WHERE timestamp >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY) 
                              GROUP BY DATE(timestamp), DATE_FORMAT(timestamp, '%b %d') 
                              ORDER BY DATE(timestamp) ASC");
$activity_labels = [];
$activity_counts = [];
if ($activity_res) {
    while ($row = $activity_res->fetch_assoc()) {
        $activity_labels[] = $row['label'];
        $activity_counts[] = (int) $row['count'];
    }
}

// Admin Action Center: surface account issues that need a decision, not just branch-level listings.
$pending_account_setup_count = (int) ($conn->query("
    SELECT COUNT(*) AS c
    FROM employees e
    INNER JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN users u ON e.employee_id = u.employee_id
        AND u.role IN ('HR Manager', 'HR Supervisor', 'HR Staff')
    WHERE e.is_active = 1
      AND e.deleted_at IS NULL
      AND d.department_name = 'Human Resources'
      AND u.user_id IS NULL
")->fetch_assoc()['c'] ?? 0);
$pending_account_setup = $conn->query("
    SELECT e.employee_id, e.first_name, e.last_name, e.job_title, b.branch_name
    FROM employees e
    INNER JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN branches b ON e.branch_id = b.branch_id
    LEFT JOIN users u ON e.employee_id = u.employee_id
        AND u.role IN ('HR Manager', 'HR Supervisor', 'HR Staff')
    WHERE e.is_active = 1
      AND e.deleted_at IS NULL
      AND d.department_name = 'Human Resources'
      AND u.user_id IS NULL
    ORDER BY e.last_name, e.first_name
    LIMIT 3
");

$first_signin_count = (int) ($conn->query("
    SELECT COUNT(*) AS c
    FROM users u
    LEFT JOIN employees e ON u.employee_id = e.employee_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    WHERE u.is_active = 1
      AND u.first_login_completed = 0
      AND ((u.role = 'Admin' AND u.employee_id IS NULL)
        OR (u.role IN ('HR Manager', 'HR Supervisor', 'HR Staff')
          AND e.employee_id IS NOT NULL
          AND e.is_active = 1
          AND e.deleted_at IS NULL
          AND d.department_name = 'Human Resources'))
")->fetch_assoc()['c'] ?? 0);
$first_signin_users = $conn->query("
    SELECT u.user_id, u.full_name, u.role, u.created_at
    FROM users u
    LEFT JOIN employees e ON u.employee_id = e.employee_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    WHERE u.is_active = 1
      AND u.first_login_completed = 0
      AND ((u.role = 'Admin' AND u.employee_id IS NULL)
        OR (u.role IN ('HR Manager', 'HR Supervisor', 'HR Staff')
          AND e.employee_id IS NOT NULL
          AND e.is_active = 1
          AND e.deleted_at IS NULL
          AND d.department_name = 'Human Resources'))
    ORDER BY u.created_at ASC
    LIMIT 3
");

$held_account_count = (int) ($conn->query("
    SELECT COUNT(*) AS c
    FROM users u
    LEFT JOIN employees e ON u.employee_id = e.employee_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    WHERE u.account_hold = 1
      AND ((u.role = 'Admin' AND u.employee_id IS NULL)
        OR (u.role IN ('HR Manager', 'HR Supervisor', 'HR Staff')
          AND e.employee_id IS NOT NULL AND d.department_name = 'Human Resources'))
")->fetch_assoc()['c'] ?? 0);
$held_accounts = $conn->query("
    SELECT u.user_id, u.full_name, u.role, u.account_hold_reason
    FROM users u
    LEFT JOIN employees e ON u.employee_id = e.employee_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    WHERE u.account_hold = 1
      AND ((u.role = 'Admin' AND u.employee_id IS NULL)
        OR (u.role IN ('HR Manager', 'HR Supervisor', 'HR Staff')
          AND e.employee_id IS NOT NULL AND d.department_name = 'Human Resources'))
    ORDER BY u.full_name LIMIT 3
");

$auto_backup_enabled = (int) getSetting($conn, 'auto_backup_enabled', '0');
$auto_backup_last_run = getSetting($conn, 'auto_backup_last_run', '');
$auto_backup_next_run = getSetting($conn, 'auto_backup_next_run', '');
$backup_attention = !$auto_backup_enabled
    || empty($auto_backup_last_run)
    || (!empty($auto_backup_next_run) && strtotime($auto_backup_next_run) < time());
if (!$auto_backup_enabled) {
    $backup_status_title = 'Automatic backup disabled';
    $backup_status_detail = 'Enable a schedule to protect system records.';
} elseif (empty($auto_backup_last_run)) {
    $backup_status_title = 'No automatic backup yet';
    $backup_status_detail = 'Review the schedule or create a backup now.';
} elseif (!empty($auto_backup_next_run) && strtotime($auto_backup_next_run) < time()) {
    $backup_status_title = 'Scheduled backup overdue';
    $backup_status_detail = 'The scheduled run time has passed.';
} else {
    $backup_status_title = 'Backup schedule healthy';
    $backup_status_detail = 'Last run ' . date('M j, Y g:i A', strtotime($auto_backup_last_run)) . '.';
}

$audit_logs = $conn->query("SELECT al.*, u.full_name FROM audit_logs al LEFT JOIN users u ON al.user_id = u.user_id ORDER BY al.timestamp DESC LIMIT 10");
?>

<style>
    .admin-dashboard .page-hero { position: relative; overflow: hidden; }
    .admin-dashboard .page-hero::after { content: ''; position: absolute; width: 180px; height: 180px; right: -70px; top: -95px; border: 1px solid rgba(255,255,255,.12); border-radius: 50%; box-shadow: 0 0 0 26px rgba(255,255,255,.035), 0 0 0 52px rgba(255,255,255,.025); pointer-events: none; }
    .admin-dashboard .dashboard-hero-content { position: relative; z-index: 1; }
    .admin-dashboard .hero-refresh { color: rgba(255,255,255,.7); font-size: .8rem; }
    .admin-dashboard .hero-refresh i { color: #ffd97d; }
    .admin-dashboard .dashboard-section-heading { margin-bottom: .75rem; }
    .admin-dashboard .dashboard-section-heading h5 { color: var(--text-dark); font-weight: 750; margin: 0; }
    .admin-dashboard .dashboard-section-heading p { margin: .2rem 0 0; color: var(--text-muted); font-size: .84rem; }
    .admin-dashboard .chart-card .card-body { padding: 1rem 1.25rem 1.25rem; }
    .admin-dashboard .chart-card canvas { height: 230px !important; max-height: 230px; }
    .admin-dashboard .coverage-panel { background: linear-gradient(135deg, #f0f8f2 0%, #fbfdfb 100%); border: 1px solid #d9ecdf; border-radius: 14px; }
    .admin-dashboard .coverage-number { color: #16703a; font-size: 2rem; font-weight: 800; letter-spacing: -.06em; line-height: 1; }
    .admin-dashboard .coverage-label { color: #427453; font-size: .73rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
    .admin-dashboard .issuance-stat + .issuance-stat { border-left: 1px solid #dce9df; }
    .admin-dashboard .audit-card .card-body { min-height: 288px; }
    .admin-dashboard .audit-card .table thead th { font-size: .72rem; letter-spacing: .04em; text-transform: uppercase; white-space: nowrap; }
    .admin-dashboard .audit-card .table td { vertical-align: middle; }
    .admin-dashboard .audit-card .table td:first-child { font-weight: 600; }
    @media (max-width: 767.98px) {
        .admin-dashboard .hero-actions { width: 100%; }
        .admin-dashboard .hero-actions .btn { width: 100%; }
        .admin-dashboard .chart-card canvas { height: 210px !important; }
        .admin-dashboard .issuance-stat + .issuance-stat { border-left: 0; border-top: 1px solid #dce9df; margin-top: .85rem; padding-top: .85rem; }
    }
</style>

<div class="admin-dashboard">
<div class="page-hero fadeup">
    <div class="dashboard-hero-content">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-3">
        <div>
            <div class="mb-1" style="color:#FFD97D;font-size:.88rem;font-weight:600;letter-spacing:.3px;"><?php echo getGreeting($_SESSION['full_name'] ?? ''); ?></div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">
                System Admin · Control Center</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-shield-alt me-2"
                    style="color:var(--primary-light);"></i>System Overview</h4>
        </div>
        <div class="hero-actions d-flex align-items-center gap-3">
            <div class="hero-refresh"><i class="fas fa-sync-alt me-1"></i>Updated <?php echo date('H:i'); ?></div>
            <a href="<?php echo BASE_URL; ?>/admin/users.php" class="btn btn-sm btn-light fw-semibold text-primary"><i class="fas fa-users-cog me-1"></i>Manage users</a>
        </div>
    </div>
    <p class="text-white-50 small mb-0"><i class="fas fa-lock me-1"></i>Security oversight and user management control
        center for Raquel Pawnshop HRIS.</p>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-lg-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-users-cog"></i></div>
            <div class="stat-info"><h3><?php echo $total_users; ?></h3><p>Total User Accounts</p></div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-user-check"></i></div>
            <div class="stat-info"><h3><?php echo $active_users; ?></h3><p>Active Accounts</p></div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="fas fa-building"></i></div>
            <div class="stat-info"><h3><?php echo $total_branches; ?></h3><p>Registered Branches</p></div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon gold"><i class="fas fa-file-invoice"></i></div>
            <div class="stat-info"><h3><?php echo $recent_logs; ?></h3><p>Security Events (24h)</p></div>
        </div>
    </div>
</div>

<!-- Admin Action Center -->
<div class="row g-3 mb-4">
    <div class="col-lg-12">
        <div class="content-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>Admin Action Center</h5>
                    <small class="text-muted">Account tasks that need an administrator's attention</small>
                </div>
                <a href="<?php echo BASE_URL; ?>/admin/users.php" class="btn btn-sm btn-outline-primary">View all accounts</a>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="border rounded-3 p-3 h-100" style="background:linear-gradient(135deg, #eef5ff 0%, #f8fbff 100%); border-color:#cfe0ff !important;">
                            <div class="d-flex align-items-start justify-content-between gap-3">
                                <div>
                                    <div class="text-primary small fw-semibold text-uppercase" style="letter-spacing:.05em;">Pending setup</div>
                                    <div class="d-flex align-items-baseline mt-1">
                                        <span class="display-6 fw-bold text-primary lh-1"><?php echo $pending_account_setup_count; ?></span>
                                        <span class="text-muted small ms-2">employees</span>
                                    </div>
                                    <p class="small text-muted mb-0 mt-2">Active HR employees without system accounts.</p>
                                </div>
                                <span class="rounded-circle d-inline-flex align-items-center justify-content-center text-primary" style="width:42px;height:42px;background:#dbeafe;flex:0 0 42px;">
                                    <i class="fas fa-user-plus"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="border rounded-3 p-3 h-100" style="background:linear-gradient(135deg, #fff8e8 0%, #fffdf8 100%); border-color:#f6dfac !important;">
                            <div class="d-flex align-items-start justify-content-between gap-3">
                                <div>
                                    <div class="text-warning small fw-semibold text-uppercase" style="letter-spacing:.05em;">Awaiting first sign-in</div>
                                    <div class="d-flex align-items-baseline mt-1">
                                        <span class="display-6 fw-bold text-warning lh-1"><?php echo $first_signin_count; ?></span>
                                        <span class="text-muted small ms-2">accounts</span>
                                    </div>
                                    <p class="small text-muted mb-0 mt-2">Issued accounts not yet activated by users.</p>
                                </div>
                                <span class="rounded-circle d-inline-flex align-items-center justify-content-center text-warning" style="width:42px;height:42px;background:#fff0c7;flex:0 0 42px;">
                                    <i class="fas fa-key"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="border rounded-3 p-3 h-100" style="background:linear-gradient(135deg, #fff1f2 0%, #fffafa 100%); border-color:#fecdd3 !important;">
                            <div class="d-flex align-items-start justify-content-between gap-3">
                                <div><div class="text-danger small fw-semibold text-uppercase" style="letter-spacing:.05em;">Accounts on hold</div><div class="d-flex align-items-baseline mt-1"><span class="display-6 fw-bold text-danger lh-1"><?php echo $held_account_count; ?></span><span class="text-muted small ms-2">accounts</span></div><p class="small text-muted mb-0 mt-2">Access restrictions that need review.</p></div>
                                <span class="rounded-circle d-inline-flex align-items-center justify-content-center text-danger" style="width:42px;height:42px;background:#ffe4e6;flex:0 0 42px;"><i class="fas fa-user-lock"></i></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="border rounded-3 p-3 h-100" style="background:linear-gradient(135deg, #f3f4f6 0%, #ffffff 100%); border-color:#d1d5db !important;">
                            <div class="d-flex align-items-start justify-content-between gap-3">
                                <div><div class="small fw-semibold text-uppercase <?php echo $backup_attention ? 'text-danger' : 'text-success'; ?>" style="letter-spacing:.05em;">Backup status</div><div class="fw-bold mt-2"><?php echo e($backup_status_title); ?></div><p class="small text-muted mb-0 mt-2"><?php echo e($backup_status_detail); ?></p></div>
                                <span class="rounded-circle d-inline-flex align-items-center justify-content-center <?php echo $backup_attention ? 'text-danger' : 'text-success'; ?>" style="width:42px;height:42px;background:#e5e7eb;flex:0 0 42px;"><i class="fas fa-database"></i></span>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($pending_account_setup_count === 0 && $first_signin_count === 0 && $held_account_count === 0 && !$backup_attention): ?>
                    <div class="d-flex align-items-center justify-content-center gap-2 rounded-3 py-3 px-4" style="background:#edf9f1; color:#277a47;">
                        <i class="fas fa-check-circle"></i>
                        <span class="small fw-semibold">All HR employee accounts are set up and activated.</span>
                    </div>
                <?php else: ?>
                    <?php if ($pending_account_setup_count > 0): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong class="small text-primary"><i class="fas fa-user-plus me-1"></i>Employees needing an account</strong>
                                <a href="<?php echo BASE_URL; ?>/admin/users.php" class="btn btn-sm btn-link text-decoration-none py-0">Create account <i class="fas fa-arrow-right ms-1"></i></a>
                            </div>
                            <?php while ($employee = $pending_account_setup->fetch_assoc()): ?>
                                <div class="d-flex justify-content-between align-items-center rounded-3 px-3 py-2 mb-2 small" style="background:#f8fafc;">
                                    <div class="pe-3">
                                        <strong><?php echo e($employee['last_name'] . ', ' . $employee['first_name']); ?></strong>
                                        <div class="text-muted mt-1"><?php echo e($employee['job_title'] ?: 'HR employee'); ?><?php echo $employee['branch_name'] ? ' · ' . e($employee['branch_name']) : ''; ?></div>
                                    </div>
                                    <a href="<?php echo BASE_URL; ?>/admin/users.php" class="btn btn-sm btn-outline-primary">Set up</a>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($first_signin_count > 0): ?>
                        <div class="mb-3">
                            <strong class="small text-warning d-block mb-2"><i class="fas fa-key me-1"></i>Accounts awaiting first sign-in</strong>
                            <?php while ($user = $first_signin_users->fetch_assoc()): ?>
                                <div class="d-flex justify-content-between align-items-center rounded-3 px-3 py-2 mb-2 small" style="background:#fffcf5;">
                                    <div class="pe-3">
                                        <strong><?php echo e($user['full_name']); ?></strong>
                                        <div class="text-muted mt-1"><?php echo e($user['role']); ?> · created <?php echo date('M j, Y', strtotime($user['created_at'])); ?></div>
                                    </div>
                                    <a href="<?php echo BASE_URL; ?>/admin/edit-user.php?id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-outline-warning">Review</a>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($held_account_count > 0): ?>
                        <div class="mb-3">
                            <strong class="small text-danger d-block mb-2"><i class="fas fa-user-lock me-1"></i>Accounts currently on hold</strong>
                            <?php while ($user = $held_accounts->fetch_assoc()): ?>
                                <div class="d-flex justify-content-between align-items-center rounded-3 px-3 py-2 mb-2 small" style="background:#fff7f7;">
                                    <div class="pe-3"><strong><?php echo e($user['full_name']); ?></strong><div class="text-muted mt-1"><?php echo e($user['role']); ?><?php echo $user['account_hold_reason'] ? ' &middot; ' . e($user['account_hold_reason']) : ''; ?></div></div>
                                    <a href="<?php echo BASE_URL; ?>/admin/edit-user.php?id=<?php echo (int) $user['user_id']; ?>" class="btn btn-sm btn-outline-danger">Review</a>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($backup_attention): ?>
                        <div class="d-flex justify-content-between align-items-center rounded-3 px-3 py-3 small" style="background:#f8fafc;">
                            <div class="pe-3"><strong><i class="fas fa-database me-1"></i><?php echo e($backup_status_title); ?></strong><div class="text-muted mt-1"><?php echo e($backup_status_detail); ?></div></div>
                            <a href="<?php echo BASE_URL; ?>/admin/backup.php" class="btn btn-sm btn-outline-primary">Manage backups</a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- System Analytics -->
<div class="dashboard-section-heading">
    <h5>System Analytics</h5>
    <p>A quick view of account roles, availability, and recent administrator activity.</p>
</div>
<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="content-card chart-card h-100">
            <div class="card-header">
                <h5><i class="fas fa-user-tag me-2"></i>User Roles Distribution</h5>
            </div>
            <div class="card-body">
                <canvas id="rolesChart" style="max-height: 250px;"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="content-card chart-card h-100">
            <div class="card-header">
                <h5><i class="fas fa-toggle-on me-2"></i>Account Status</h5>
            </div>
            <div class="card-body">
                <canvas id="statusChart" style="max-height: 250px;"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="content-card chart-card h-100">
            <div class="card-header">
                <h5><i class="fas fa-chart-line me-2"></i>Activity Trend (7 Days)</h5>
            </div>
            <div class="card-body">
                <canvas id="activityChart" style="max-height: 250px;"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Employee Account Issuance -->
    <div class="col-lg-4">
        <div class="content-card h-100">
            <div class="card-header">
                <h5><i class="fas fa-id-badge me-2"></i>Employee Account Issuance</h5>
            </div>
            <div class="card-body">
                <div class="coverage-panel p-3 mb-3">
                    <div class="d-flex align-items-end justify-content-between gap-3">
                        <div>
                            <div class="coverage-label">Account coverage</div>
                            <div class="coverage-number mt-1"><?php echo round($issued_pct, 1); ?>%</div>
                        </div>
                        <i class="fas fa-shield-alt fa-2x" style="color:#5ca973;"></i>
                    </div>
                    <div class="progress mt-3" style="height:8px; background:#dceee1;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $issued_pct; ?>%" aria-valuenow="<?php echo $issued_pct; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
                <div class="row text-center">
                    <div class="col-6 issuance-stat">
                        <div class="p-1">
                            <h2 class="fw-bold text-success mb-1"><?php echo $employees_with_accounts; ?></h2>
                            <p class="text-muted small mb-0">Accounts issued</p>
                        </div>
                    </div>
                    <div class="col-6 issuance-stat">
                        <div class="p-1">
                            <h2 class="fw-bold text-warning mb-1"><?php echo $employees_no_accounts; ?></h2>
                            <p class="text-muted small mb-0">Not issued yet</p>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-between border-top mt-3 pt-3">
                    <small class="text-muted">Across all active employees</small>
                    <small class="fw-semibold text-dark"><?php echo $total_employees; ?> total</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Security Activity -->
    <div class="col-lg-8">
        <div class="content-card audit-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-history me-2"></i>Recent Security Activity</h5>
                <a href="<?php echo BASE_URL; ?>/admin/audit-trail.php" class="btn btn-sm btn-outline-primary">View Full
                    Trail</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Operation</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($audit_logs->num_rows === 0): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">No security logs recorded.</td>
                                </tr>
                            <?php else: ?>
                                <?php while ($log = $audit_logs->fetch_assoc()): ?>
                                    <tr>
                                    <td data-label="User"><?php echo e($log['full_name'] ?? 'System Process'); ?></td>
                                    <td data-label="Operation"><span class="badge bg-secondary"><?php echo e($log['action_type']); ?></span></td>
                                    <td data-label="Timestamp"><small><?php echo formatDateTime($log['timestamp']); ?></small></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const commonOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, padding: 15 } }
            }
        };

        // 1. Roles Chart (Pie)
        new Chart(document.getElementById('rolesChart'), {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($role_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($role_counts); ?>,
                    backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e'],
                    hoverOffset: 4
                }]
            },
            options: commonOptions
        });

        // 2. Status Chart (Doughnut)
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($status_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($status_counts); ?>,
                    backgroundColor: ['#e74a3b', '#1cc88a'],
                    hoverOffset: 4
                }]
            },
            options: commonOptions
        });

        // 3. Activity Trend (Line)
        new Chart(document.getElementById('activityChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($activity_labels); ?>,
                datasets: [{
                    label: 'Audit Events',
                    data: <?php echo json_encode($activity_counts); ?>,
                    borderColor: '#4e73df',
                    backgroundColor: 'rgba(78, 115, 223, 0.05)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                ...commonOptions,
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });

    });
</script>

<?php require_once '../includes/footer.php'; ?>
