<?php
$page_title = 'Portal Accounts';
require_once '../includes/session-check.php';
checkRole(['Admin']);
require_once '../includes/functions.php';

require_once '../includes/header.php';

// Pagination settings
$per_page = 20;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $per_page;
$selected_department = isset($_GET['department']) && $_GET['department'] !== '' ? max(0, (int)$_GET['department']) : 0;
$selected_position   = isset($_GET['position'])   && $_GET['position']   !== '' ? trim($_GET['position'])   : '';

$department_options = $conn->query("
    SELECT department_id, department_name
    FROM departments
    WHERE is_active = 1 AND deleted_at IS NULL
    ORDER BY department_name
");

$position_options = $conn->query("
    SELECT DISTINCT e.job_title
    FROM employees e
    LEFT JOIN users ua ON ua.employee_id = e.employee_id AND ua.role = 'Admin'
    WHERE e.job_title IS NOT NULL AND e.job_title <> ''
      AND ua.user_id IS NULL
    ORDER BY e.job_title
");

// Build a dept_id → [job_titles] map for JS-driven filtering
$positions_by_dept_result = $conn->query("
    SELECT DISTINCT e.department_id, e.job_title
    FROM employees e
    LEFT JOIN users ua ON ua.employee_id = e.employee_id AND ua.role = 'Admin'
    WHERE e.job_title IS NOT NULL AND e.job_title <> ''
      AND e.department_id IS NOT NULL
      AND ua.user_id IS NULL
    ORDER BY e.department_id, e.job_title
");
$positions_by_dept = [];
while ($row = $positions_by_dept_result->fetch_assoc()) {
    $positions_by_dept[(int)$row['department_id']][] = $row['job_title'];
}
$positions_by_dept_json = json_encode($positions_by_dept, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);

// Build WHERE conditions
$base_where_conditions = "ua.user_id IS NULL";

if ($selected_department > 0) {
    $base_where_conditions .= " AND e.department_id = $selected_department";
}

if ($selected_position !== '') {
    $safe_position = $conn->real_escape_string($selected_position);
    $base_where_conditions .= " AND e.job_title = '$safe_position'";
}

// Fast COUNT — LEFT JOIN anti-join to exclude Admin-linked employees
$total_accounts_result = $conn->query("
    SELECT COUNT(*) AS total
    FROM employees e
    LEFT JOIN users ua ON ua.employee_id = e.employee_id AND ua.role = 'Admin'
    WHERE $base_where_conditions
");
$total_accounts = (int)($total_accounts_result->fetch_assoc()['total'] ?? 0);
$total_pages = max(1, (int)ceil($total_accounts / $per_page));

if ($current_page > $total_pages) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $per_page;
}

// Fetch paginated employee IDs first (fast, index-only)
$id_result = $conn->query("
    SELECT e.employee_id
    FROM employees e
    LEFT JOIN users ua ON ua.employee_id = e.employee_id AND ua.role = 'Admin'
    WHERE $base_where_conditions
    ORDER BY e.last_name, e.first_name
    LIMIT $per_page OFFSET $offset
");
$emp_ids = [];
while ($r = $id_result->fetch_assoc()) {
    $emp_ids[] = (int)$r['employee_id'];
}

// Fetch full data only for the current page's IDs
$employees = null;
if (!empty($emp_ids)) {
    $ids_in = implode(',', $emp_ids);

    $employees = $conn->query("
        SELECT
            e.employee_id,
            e.employee_code,
            e.first_name,
            e.last_name,
            e.middle_name,
            e.job_title,
            d.department_name,
            b.branch_name,
            e.profile_picture,
            e.is_active AS emp_active,
            -- Employee portal account (non-correlated: JOIN instead of subquery)
            uemp.user_id,
            uemp.username,
            uemp.role,
            uemp.is_active AS user_active,
            -- HR account (non-correlated)
            uhr.role     AS hr_role,
            uhr.username AS hr_username,
            ec.personal_email
        FROM employees e
        LEFT JOIN branches b    ON e.branch_id    = b.branch_id
        LEFT JOIN departments d ON e.department_id = d.department_id
        -- Employee-role portal account (one row per employee via MIN trick)
        LEFT JOIN users uemp ON uemp.employee_id = e.employee_id
                             AND uemp.role = 'Employee'
                             AND uemp.user_id = (
                                 SELECT MIN(u2.user_id) FROM users u2
                                 WHERE u2.employee_id = e.employee_id AND u2.role = 'Employee'
                             )
        -- HR account
        LEFT JOIN users uhr ON uhr.employee_id = e.employee_id
                            AND uhr.role IN ('HR Staff', 'HR Supervisor', 'HR Manager')
                            AND uhr.user_id = (
                                SELECT MIN(u3.user_id) FROM users u3
                                WHERE u3.employee_id = e.employee_id
                                  AND u3.role IN ('HR Staff', 'HR Supervisor', 'HR Manager')
                            )
        -- Contact (employee_contacts has UNIQUE KEY on employee_id — direct JOIN)
        LEFT JOIN employee_contacts ec ON ec.employee_id = e.employee_id
        WHERE e.employee_id IN ($ids_in)
        ORDER BY e.last_name, e.first_name
    ");
}

// Grab and clear any pending credential slip
$new_creds = $_SESSION['new_employee_credentials'] ?? null;
unset($_SESSION['new_employee_credentials']);
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
        <p class="text-muted small mb-1">Account created for:</p>
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

<div class="page-hero fadeup">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-3">
        <div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">System Admin · Employee Access</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-user-lock me-2" style="color:var(--primary-light);"></i>Portal Accounts</h4>
        </div>
        <div style="color:rgba(255,255,255,.6);font-size:.8rem;">
            <i class="fas fa-users me-1"></i><?php echo $total_accounts; ?> employees
        </div>
    </div>
    <p class="text-white-50 small mb-0"><i class="fas fa-key me-1"></i>Manage employee access to the self-service portal.</p>
</div>

<div class="alert alert-info py-2 fadeup-1" style="font-size:0.9rem;">
    <i class="fas fa-info-circle me-2"></i>
    Employee Portal accounts are separate from HR system accounts. Company ID is suggested as the portal username, but you can use a custom one.
</div>

<div class="content-card fadeup-2">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <h5 class="mb-0"><i class="fas fa-user-lock me-2"></i>Portal Account Status</h5>
        <div class="d-flex align-items-center justify-content-end gap-2 flex-wrap ms-auto" style="flex: 1 1 420px;">
            <form method="GET" class="mb-0 d-flex align-items-center gap-2 flex-wrap">
                <select class="form-select form-select-sm" id="department_filter" name="department" aria-label="Filter portal accounts by department" style="width: 200px;">
                    <option value="">All Departments</option>
                    <?php while ($department = $department_options->fetch_assoc()): ?>
                        <option value="<?php echo (int) $department['department_id']; ?>" <?php echo $selected_department === (int) $department['department_id'] ? 'selected' : ''; ?>>
                            <?php echo e($department['department_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <select class="form-select form-select-sm" id="position_filter" name="position" aria-label="Filter portal accounts by position" style="width: 200px;">
                    <option value="">All Positions</option>
                    <?php while ($pos = $position_options->fetch_assoc()): ?>
                        <option value="<?php echo e($pos['job_title']); ?>" <?php echo $selected_position === $pos['job_title'] ? 'selected' : ''; ?>>
                            <?php echo e($pos['job_title']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <?php if ($selected_department > 0 || $selected_position !== ''): ?>
                    <a href="employee-accounts.php" class="btn btn-sm btn-outline-secondary" title="Clear filters">
                        <i class="fas fa-times me-1"></i>Clear
                    </a>
                <?php endif; ?>
            </form>
            <div class="search-box" style="min-width: 240px; flex: 1 1 240px; max-width: 320px;">
                <i class="fas fa-search search-icon"></i>
                <input type="text" class="form-control form-control-sm" id="searchPortal" placeholder="Search employees..." onkeyup="filterTable('searchPortal', 'portalTable')">
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive d-none d-md-block">
            <table class="table table-hover" id="portalTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Photo</th>
                        <th>Employee Name</th>
                        <th>Department</th>
                        <th>Branch</th>
                        <th>Account Status</th>
                        <th>Portal Username</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $row_number = $offset + 1; ?>
                    <?php if (!$employees || $employees->num_rows === 0): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">No employees found for the selected department.</td>
                        </tr>
                    <?php else: ?>
                        <?php while ($emp = $employees->fetch_assoc()): ?>
                            <tr>
                                <td data-label="#"><strong><?php echo $row_number++; ?></strong></td>
                                <td data-label="Photo">
                                    <img src="<?php echo getEmployeeAvatar($emp['profile_picture']); ?>"
                                         alt="Profile" class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover;">
                                </td>
                                <td data-label="Employee Name">
                                    <div><strong><?php echo e($emp['last_name'] . ', ' . $emp['first_name']); ?></strong></div>
                                    <small class="text-muted"><?php echo e($emp['job_title']); ?> <span class="company-id-text">(Company ID: <span class="company-id-value"><?php echo e(getEmployeeDisplayId($emp)); ?></span>)</span></small>
                                </td>
                                <td data-label="Department"><?php echo e($emp['department_name'] ?? 'N/A'); ?></td>
                                <td data-label="Branch"><?php echo e($emp['branch_name'] ?? 'N/A'); ?></td>
                                <td data-label="Account Status">
                                    <?php if ($emp['user_id']): ?>
                                        <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Has Account</span>
                                        <?php
                                        $supervisory_titles = ['Area Supervisor', 'Immediate Head', 'Manager', 'Director', 'Team Lead', 'Supervisor'];
                                        $display_role = $emp['role'];
                                        foreach ($supervisory_titles as $title) {
                                            if (stripos($emp['job_title'], $title) !== false) {
                                                $display_role = $emp['job_title'];
                                                break;
                                            }
                                        }
                                        ?>
                                        <div class="mt-1 small fw-bold text-primary"><?php echo e($display_role); ?></div>
                                        <?php if (!$emp['user_active']): ?>
                                            <span class="badge bg-danger ms-1">Inactive</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark"><i class="fas fa-times-circle me-1"></i>No Account</span>
                                    <?php endif; ?>
                                    <?php if (!empty($emp['hr_role'])): ?>
                                        <div class="mt-1 small text-muted">HR Account: <?php echo e($emp['hr_role']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Portal Username">
                                    <?php echo $emp['username'] ? '<code>' . e($emp['username']) . '</code>' : '<span class="text-muted small">Not Set</span>'; ?>
                                </td>
                                <td data-label="Actions">
                                    <?php if (!$emp['user_id']): ?>
                                        <button class="btn btn-sm btn-primary"
                                                onclick="openCreateAccountModal(<?php echo $emp['employee_id']; ?>, '<?php echo e(addslashes($emp['first_name'] . ' ' . $emp['last_name'])); ?>', '<?php echo e(addslashes($emp['personal_email'] ?? '')); ?>', '<?php echo e(addslashes(getEmployeeDisplayId($emp))); ?>')">
                                            <i class="fas fa-plus me-1"></i>Create Account
                                        </button>
                                    <?php else: ?>
                                        <?php if (($emp['role'] ?? '') === 'Employee'): ?>
                                            <a href="employee-portal-user.php?user_id=<?php echo (int)$emp['user_id']; ?>&page=<?php echo $current_page; ?><?php echo $selected_department > 0 ? '&department=' . (int) $selected_department : ''; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-id-card me-1"></i>Manage Portal
                                            </a>
                                        <?php else: ?>
                                            <a href="users.php?search=<?php echo urlencode($emp['username']); ?>" class="btn btn-sm btn-outline-warning">
                                                <i class="fas fa-user-cog me-1"></i>Manage (HR)
                                            </a>
                                            <button class="btn btn-sm btn-outline-success ms-1"
                                                    onclick="openCreateAccountModal(<?php echo $emp['employee_id']; ?>, '<?php echo e(addslashes($emp['first_name'] . ' ' . $emp['last_name'])); ?>', '<?php echo e(addslashes($emp['personal_email'] ?? '')); ?>', '<?php echo e(addslashes(getEmployeeDisplayId($emp))); ?>')"
                                                    title="Create portal credentials">
                                                <i class="fas fa-plus me-1"></i>Portal
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile view (Student Check-in Style) -->
        <div class="mobile-list-view d-block d-md-none">
            <div class="student-list">
                <?php $row_number_mob = $offset + 1; ?>
                <?php if (!$employees || $employees->num_rows === 0): ?>
                    <div class="text-center py-4 text-muted">No employees found for the selected department.</div>
                <?php else: ?>
                    <?php 
                    $employees->data_seek(0);
                    while ($emp = $employees->fetch_assoc()): 
                        $avatar_num = ($row_number_mob % 6) + 1;
                        $initials = strtoupper(substr($emp['first_name'] ?? '', 0, 1) . substr($emp['last_name'] ?? '', 0, 1));
                    ?>
                        <div class="student-item">
                            <div class="student-avatar">
                                <img src="<?php echo getEmployeeAvatar($emp['profile_picture']); ?>" alt="Profile" class="avatar-img">
                            </div>
                            <div class="student-info">
                                <div class="student-name"><?php echo e($emp['last_name'] . ', ' . $emp['first_name']); ?></div>
                                <div class="student-meta">
                                    <span class="company-id-text">ID: <span class="company-id-value"><?php echo e(getEmployeeDisplayId($emp)); ?></span></span>
                                    &bull; <span><?php echo e($emp['job_title'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="student-meta" style="margin-top: 2px;">
                                    <span><?php echo e($emp['department_name'] ?? 'N/A'); ?></span>
                                    &bull; <span><?php echo e($emp['branch_name'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="student-meta" style="margin-top: 4px;">
                                    <?php if ($emp['user_id']): ?>
                                        <span class="badge bg-success">Has Account</span>
                                        <?php if (!$emp['user_active']): ?>
                                            <span class="badge bg-danger ms-1">Inactive</span>
                                        <?php endif; ?>
                                        <span class="ms-1 text-muted">User: <code><?php echo e($emp['username']); ?></code></span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">No Account</span>
                                    <?php endif; ?>
                                    <?php if (!empty($emp['hr_role'])): ?>
                                        <span class="badge bg-secondary ms-1">HR: <?php echo e($emp['hr_role']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="ms-auto text-end align-self-center">
                                <?php if (!$emp['user_id']): ?>
                                    <button class="btn btn-sm btn-primary"
                                            onclick="openCreateAccountModal(<?php echo $emp['employee_id']; ?>, '<?php echo e(addslashes($emp['first_name'] . ' ' . $emp['last_name'])); ?>', '<?php echo e(addslashes($emp['personal_email'] ?? '')); ?>', '<?php echo e(addslashes(getEmployeeDisplayId($emp))); ?>')"
                                            title="Create Account" style="padding: 6px 12px; border-radius: 8px;">
                                        <i class="fas fa-plus me-1"></i>Create
                                    </button>
                                <?php else: ?>
                                    <?php if (($emp['role'] ?? '') === 'Employee'): ?>
                                        <a href="employee-portal-user.php?user_id=<?php echo (int)$emp['user_id']; ?>&page=<?php echo $current_page; ?><?php echo $selected_department > 0 ? '&department=' . (int) $selected_department : ''; ?>" class="btn btn-sm btn-outline-primary"
                                           title="Manage Portal" style="padding: 6px 12px; border-radius: 8px;">
                                            <i class="fas fa-cog me-1"></i>Manage
                                        </a>
                                    <?php else: ?>
                                        <a href="users.php?search=<?php echo urlencode($emp['username']); ?>" class="btn btn-sm btn-outline-warning"
                                            title="Manage (HR)" style="padding: 6px 12px; border-radius: 8px;">
                                            <i class="fas fa-user-cog me-1"></i>HR
                                        </a>
                                        <button class="btn btn-sm btn-outline-success ms-1"
                                                 onclick="openCreateAccountModal(<?php echo $emp['employee_id']; ?>, '<?php echo e(addslashes($emp['first_name'] . ' ' . $emp['last_name'])); ?>', '<?php echo e(addslashes($emp['personal_email'] ?? '')); ?>', '<?php echo e(addslashes(getEmployeeDisplayId($emp))); ?>')"
                                                 title="Create portal credentials" style="padding: 6px 12px; border-radius: 8px;">
                                             <i class="fas fa-plus me-1"></i>Portal
                                        </button>
                                    <?php endif; ?>
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
            <nav aria-label="Portal accounts pagination">
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

<!-- Create Account Modal -->
<div class="modal fade" id="createAccountModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content portal-modal-content">
            <div class="portal-modal-header d-flex justify-content-between align-items-center">
                <h5 class="modal-title mb-0"><i class="fas fa-user-plus me-2"></i>Create Portal Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="add-user.php" class="portal-input-group mb-0">
                <?php echo csrfField(); ?>
                <div class="modal-body p-4">
                    <input type="hidden" name="employee_id" id="modal_employee_id">
                    <input type="hidden" name="full_name" id="modal_full_name">
                    <input type="hidden" name="email" id="modal_email">
                    <input type="hidden" name="role" value="Employee">
                    <input type="hidden" name="redirect" value="employee-accounts.php?page=<?php echo $current_page; ?><?php echo $selected_department > 0 ? '&department=' . (int) $selected_department : ''; ?><?php echo $selected_position !== '' ? '&position=' . urlencode($selected_position) : ''; ?>">
                    
                    <!-- Employee Badge Info -->
                    <div class="portal-emp-badge">
                        <div class="badge-icon">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <div class="badge-info">
                            <div class="badge-label">Creating Portal Account For</div>
                            <div class="badge-name" id="display_emp_name">Elena Delgado</div>
                        </div>
                    </div>

                    <!-- Username Field -->
                    <div class="mb-3">
                        <label class="form-label-caps">Username</label>
                        <input type="text" class="form-control" name="username" id="modal_username" placeholder="Enter username" required>
                        <div class="form-text" style="font-size: 0.76rem; color: #64748b; margin-top: 4px;">Employee ID is suggested, but you can change it.</div>
                    </div>

                    <!-- Password Field with Generate Button -->
                    <div class="mb-3">
                        <label class="form-label-caps">Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="password" id="modal_password" placeholder="Enter password" required minlength="6" style="border-top-right-radius: 0; border-bottom-right-radius: 0;">
                            <button class="btn btn-generate" type="button" onclick="generatePassword()" title="Generate Random Password">
                                <i class="fas fa-random me-1"></i> Generate
                            </button>
                        </div>
                        <div class="form-text" style="font-size: 0.76rem; color: #64748b; margin-top: 4px;">Minimum 6 characters. Use the random button to generate one.</div>
                    </div>
                </div>
                <div class="modal-footer p-3 bg-light border-0 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-secondary px-3 py-2 fw-semibold" style="border-radius: 8px; font-size: 0.85rem;" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold" style="border-radius: 8px; font-size: 0.85rem; background: linear-gradient(135deg, var(--primary-blue), var(--primary-dark)); border: none;"><i class="fas fa-save me-1.5"></i>Create Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openCreateAccountModal(id, name, email, employeeCode) {
    document.getElementById('modal_employee_id').value = id;
    document.getElementById('modal_full_name').value = name;
    document.getElementById('modal_email').value = email;
    document.getElementById('modal_username').value = employeeCode || '';
    document.getElementById('display_emp_name').textContent = name;
    
    // Clear password
    document.getElementById('modal_password').value = '';
    
    new bootstrap.Modal(document.getElementById('createAccountModal')).show();
}

function generatePassword() {
    const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*";
    let pass = "";
    for (let i = 0; i < 10; i++) {
        pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('modal_password').value = pass;
    document.getElementById('modal_password').type = 'text';
}

function filterTable(inputId, tableId) {
    let input = document.getElementById(inputId);
    let filter = input.value.toLowerCase();
    let table = document.getElementById(tableId);
    let tr = table.getElementsByTagName("tr");

    for (let i = 1; i < tr.length; i++) {
        let text = tr[i].textContent.toLowerCase();
        tr[i].style.display = text.includes(filter) ? "" : "none";
    }
}

// ── Department ↔ Position filter coupling ────────────────────────────────────
(function () {
    const positionsByDept = <?php echo $positions_by_dept_json; ?>;
    const deptSelect     = document.getElementById('department_filter');
    const posSelect      = document.getElementById('position_filter');
    const filterForm     = deptSelect.closest('form');
    const currentPos     = <?php echo json_encode($selected_position); ?>;

    function rebuildPositions(deptId, selectValue) {
        // Collect the positions to show
        let positions;
        if (!deptId) {
            // All departments — flatten all lists
            positions = Object.values(positionsByDept)
                .flat()
                .filter((v, i, a) => a.indexOf(v) === i)
                .sort((a, b) => a.localeCompare(b));
        } else {
            positions = positionsByDept[deptId] || [];
        }

        // Rebuild options
        posSelect.innerHTML = '<option value="">All Positions</option>';
        positions.forEach(pos => {
            const opt = document.createElement('option');
            opt.value = pos;
            opt.textContent = pos;
            if (pos === selectValue) opt.selected = true;
            posSelect.appendChild(opt);
        });
    }

    // On department change: rebuild positions, clear stale position, submit
    deptSelect.addEventListener('change', function () {
        const deptId = this.value ? parseInt(this.value) : null;
        rebuildPositions(deptId, '');   // reset position selection
        filterForm.submit();
    });

    // On position change: just submit
    posSelect.addEventListener('change', function () {
        filterForm.submit();
    });

    // On page load: rebuild positions to match current department
    // (so the dropdown shows only relevant options even on a fresh load)
    const initDept = deptSelect.value ? parseInt(deptSelect.value) : null;
    rebuildPositions(initDept, currentPos);
})();
</script>

<?php require_once '../includes/footer.php'; ?>
