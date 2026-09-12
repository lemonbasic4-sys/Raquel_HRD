<?php
/**
 * Returns the CSS class for a job title badge based on rank_category_id.
 *  1 = Executives      → gold
 *  2 = Management Team → purple
 *  3 = Manager         → blue
 *  4 = Supervisor      → teal
 *  5 = R&F / Staff     → green
 */
function getJobTitleBadgeClass(int $rankId): string {
    return match ($rankId) {
        1 => 'job-badge-executive',
        2 => 'job-badge-mgmt-team',
        3 => 'job-badge-manager',
        4 => 'job-badge-supervisor',
        5 => 'job-badge-rf',
        default => 'job-badge-default',
    };
}
?>
<?php
$page_title = 'Employees';
require_once '../includes/session-check.php';
checkRole(['HR Manager']);
require_once '../includes/functions.php';

// Resolve the user's assigned branch name for auto-filtering
$user_assigned_branch_name = '';
$branch_id = $_SESSION['branch_id'];
if (!empty($branch_id)) {
    $br_stmt = $conn->prepare("SELECT branch_name FROM branches WHERE branch_id = ? LIMIT 1");
    $br_stmt->bind_param("i", $branch_id);
    $br_stmt->execute();
    $br_res = $br_stmt->get_result();
    if ($br_row = $br_res->fetch_assoc()) {
        $user_assigned_branch_name = $br_row['branch_name'];
    }
    $br_stmt->close();
}

// Helper to preserve active filter parameters upon redirect
$get_params = $_GET;
$buildRedirectWithFilters = function ($actionKey) use ($get_params) {
    $params = $get_params;
    unset($params[$actionKey]);
    if ($actionKey === 'deactivate') {
        unset($params['status'], $params['effective_date'], $params['remarks']);
    }
    $qs = http_build_query($params);
    return BASE_URL . '/manager/employees.php' . ($qs ? '?' . $qs : '');
};

ensureEmployeeSeparationColumns($conn);

// Handle activate/deactivate
if (isset($_GET['deactivate']) && is_numeric($_GET['deactivate'])) {
    $eid = (int) $_GET['deactivate'];
    $status = $_GET['status'] ?? 'Separated';
    $raw_eff_date = trim($_GET['effective_date'] ?? '');
    $separation_date = !empty($raw_eff_date) ? date('Y-m-d', strtotime($raw_eff_date)) : date('Y-m-d');
    $separation_remarks = trim($_GET['remarks'] ?? '');
    $remarks_val = $separation_remarks !== '' ? $separation_remarks : null;

    $stmt = $conn->prepare("UPDATE employees SET is_active = 0, employment_status = ?, separation_date = ?, separation_remarks = ? WHERE employee_id = ?");
    $stmt->bind_param("sssi", $status, $separation_date, $remarks_val, $eid);

    if ($stmt->execute()) {
        $audit_detail = "Separated employee with status: $status, Effective: $separation_date" . ($remarks_val ? ", Remarks: $remarks_val" : "");
        logAudit($conn, $_SESSION['user_id'], 'UPDATE', 'Employee', $eid, $audit_detail);
        redirectWith($buildRedirectWithFilters('deactivate'), 'success', 'Employee separation processed successfully.');
    }
    $stmt->close();
}
if (isset($_GET['activate']) && is_numeric($_GET['activate'])) {
    $eid = (int) $_GET['activate'];
    $conn->query("UPDATE employees SET is_active = 1, employment_status = 'Regular', separation_date = NULL, separation_remarks = NULL WHERE employee_id = $eid");
    logAudit($conn, $_SESSION['user_id'], 'UPDATE', 'Employee', $eid, 'Reactivated employee and reset separation record');
    redirectWith($buildRedirectWithFilters('activate'), 'success', 'Employee reactivated successfully.');
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $eid = (int) $_GET['delete'];
    // Delete the user account associated with this employee (if any) BEFORE deleting employee
    // This prevents orphaned user accounts from appearing in User Management
    $conn->query("DELETE FROM users WHERE employee_id = $eid");
    // Delete the employee and all normalized sub-tables
    $tables = [
        'employee_details',
        'employee_government_ids',
        'employee_addresses',
        'employee_contacts',
        'employee_emergency_contacts',
        'employee_disclosures',
        'employee_family',
        'employee_children',
        'employee_siblings',
        'employee_education',
        'employee_work_experience',
        'employee_trainings',
        'employee_voluntary_work',
        'employee_eligibility',
        'employee_skills',
        'employee_recognitions',
        'employee_memberships',
        'employee_real_properties',
        'employee_personal_properties',
        'employee_liabilities',
        'employee_references'
    ];
    foreach ($tables as $tbl) {
        $conn->query("DELETE FROM $tbl WHERE employee_id = $eid");
    }

    $conn->query("DELETE FROM employees WHERE employee_id = $eid");
    logAudit($conn, $_SESSION['user_id'], 'DELETE', 'Employee', $eid, 'Permanently deleted employee');
    redirectWith($buildRedirectWithFilters('delete'), 'success', 'Employee deleted permanently.');
}

require_once '../includes/header.php';

// Fetch employees
$employees = $conn->query("
    SELECT e.*, b.branch_name, d.department_name, jt.job_title, jt.rank_category_id
    FROM employees e
    LEFT JOIN branches b ON e.branch_id = b.branch_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN job_titles jt ON e.job_title_id = jt.job_title_id
    WHERE e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)
    ORDER BY e.last_name, e.first_name
");
$employee_total = (int) $employees->num_rows;
$employee_active = (int) $conn->query("
    SELECT COUNT(*) as cnt
    FROM employees e
    WHERE e.is_active = 1
      AND e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)
")->fetch_assoc()['cnt'];
$employee_inactive = max(0, $employee_total - $employee_active);

// Fetch distinct values for filter dropdowns
$job_titles_res = $conn->query("
    SELECT 
        jt.job_title_id,
        jt.job_title,
        d.department_id,
        d.department_name,
        rc.rank_name,
        parent.job_title AS reports_to_title,
        (
            SELECT COUNT(*) 
            FROM employees e 
            WHERE e.job_title_id = jt.job_title_id
        ) AS employee_count
    FROM job_titles jt
    LEFT JOIN departments d 
        ON jt.department_id = d.department_id
    LEFT JOIN rank_categories rc 
        ON jt.rank_category_id = rc.rank_category_id
    LEFT JOIN job_titles parent 
        ON jt.reports_to = parent.job_title_id
    WHERE jt.job_title IS NOT NULL 
      AND jt.job_title != ''
    ORDER BY d.department_name ASC, jt.job_title ASC
");
$job_titles = [];
$job_titles_by_dept = []; // Organize by department
while ($r = $job_titles_res->fetch_assoc()) {
    $job_titles[] = $r;
    $dept_name = $r['department_name'] ?? 'Unassigned';
    if (!isset($job_titles_by_dept[$dept_name])) {
        $job_titles_by_dept[$dept_name] = [];
    }
    $job_titles_by_dept[$dept_name][] = $r;
}

$departments_res = $conn->query("SELECT d.department_name FROM departments d ORDER BY d.department_name ASC");
$departments = [];
while ($r = $departments_res->fetch_assoc())
    $departments[] = $r['department_name'];

$branches_res = $conn->query("SELECT b.branch_name FROM branches b ORDER BY b.branch_name ASC");
$branches = [];
while ($r = $branches_res->fetch_assoc())
    $branches[] = $r['branch_name'];

$statuses = ['OJT', 'Probationary', 'Project Based', 'Regular', 'Separated', 'Trainee', 'AWOL', 'Retirement', 'Death', 'Permanent or Total Disability', 'Resignation', 'Failed in Training', 'Termination for Cause'];
$selected_branch = $_GET['branch'] ?? $user_assigned_branch_name;
?>

<style>
    /* ── Job Title Rank Badges ───────────────────────── */
    .job-badge {
        display: inline-block;
        padding: 2px 9px;
        border-radius: 12px;
        font-size: .72rem;
        font-weight: 600;
        letter-spacing: .3px;
        white-space: normal;
        overflow-wrap: anywhere;
        word-break: break-word;
    }
    .job-badge-executive   { background: #fff3cd; color: #856404; border: 1px solid #ffc107; }
    .job-badge-mgmt-team   { background: #ede7f6; color: #5e35b1; border: 1px solid #9c77e0; }
    .job-badge-manager     { background: #dbeafe; color: #1d4ed8; border: 1px solid #60a5fa; }
    .job-badge-supervisor  { background: #ccfbf1; color: #0f766e; border: 1px solid #2dd4bf; }
    .job-badge-rf          { background: #f0fdf4; color: #166534; border: 1px solid #86efac; }
    .job-badge-default     { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }

    /* Filter Toolbar */
    .filter-toolbar {
        padding: 16px 20px;
        background: linear-gradient(135deg, #f8f9fc 0%, #f1f3f8 100%);
        border-bottom: 1px solid #e8ecf1;
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
    }

    .filter-group {
        position: relative;
        min-width: 180px;
        flex: 1;
    }

    .filter-group label {
        display: block;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #8094ae;
        margin-bottom: 4px;
    }

    .filter-group select {
        width: 100%;
        padding: 8px 32px 8px 12px;
        border: 1px solid #dce3ed;
        border-radius: 8px;
        background: #fff;
        font-size: 0.85rem;
        font-weight: 500;
        color: #344357;
        transition: all 0.2s ease;
        appearance: none;
        -webkit-appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%238094ae' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 10px center;
        cursor: pointer;
    }

    .filter-group select:focus {
        outline: none;
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb, 59, 130, 246), 0.1);
    }

    .filter-group select.active-filter {
        border-color: var(--primary-blue);
        background-color: #eef4ff;
        color: var(--primary-blue);
        font-weight: 600;
    }

    .filter-actions {
        display: flex;
        align-items: flex-end;
        gap: 8px;
        padding-bottom: 1px;
    }

    .filter-summary {
        padding: 8px 20px;
        background: #fff;
        border-bottom: 1px solid #e8ecf1;
        display: none;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .filter-summary.has-filters {
        display: flex;
    }

    .filter-summary .filter-label {
        font-size: 0.78rem;
        font-weight: 600;
        color: #8094ae;
        margin-right: 4px;
    }

    .filter-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 3px 10px;
        background: #eef4ff;
        border: 1px solid #d0dfff;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--primary-blue);
        animation: chipIn 0.2s ease;
    }

    .filter-chip .chip-category {
        font-weight: 400;
        color: #8094ae;
    }

    .filter-chip .remove-chip {
        cursor: pointer;
        opacity: 0.6;
        transition: opacity 0.15s;
        font-size: 0.65rem;
    }

    .filter-chip .remove-chip:hover {
        opacity: 1;
    }

    .btn-clear-filters {
        font-size: 0.75rem;
        font-weight: 600;
        color: #dc3545;
        background: none;
        border: none;
        padding: 3px 8px;
        cursor: pointer;
        transition: all 0.15s;
        border-radius: 6px;
    }

    .btn-clear-filters:hover {
        background: #fff5f5;
    }

    @keyframes chipIn {
        from {
            transform: scale(0.85);
            opacity: 0;
        }

        to {
            transform: scale(1);
            opacity: 1;
        }
    }

    @media (max-width: 768px) {
        /* Hide the multi-dropdown filter toolbar on mobile */
        .filter-toolbar {
            display: none !important;
        }
        /* Hide filter chips row on mobile (handled by filter sheet) */
        .filter-summary {
            display: none !important;
        }
        /* Hide desktop search box on mobile (replaced by hr-mobile-search-bar) */
        .cc-header .search-box {
            display: none !important;
        }
        #paginationWrapper {
            flex-direction: column;
            gap: 15px;
            text-align: center;
        }
    }
</style>


<div class="page-hero fadeup mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
        <div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">HR Manager · Employees</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-users me-2" style="color:#BD9414;"></i>All Employees</h4>
            <p class="text-white-50 small mb-0 mt-2">View, search, and manage employee records, employment status, and assigned organizational details.</p>
        </div>
        <div style="color:rgba(255,255,255,.6);font-size:.8rem;">
            <i class="fas fa-sync-alt me-1"></i>Data as of <?php echo date('F d, Y'); ?>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo $employee_total; ?></div>
                        <div class="stat-label">Total Employees</div>
                    </div>
                    <i class="fas fa-id-badge stat-icon text-white-50"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo $employee_active; ?></div>
                        <div class="stat-label">Active</div>
                    </div>
                    <i class="fas fa-check-circle stat-icon" style="color:#28a745;"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo $employee_inactive; ?></div>
                        <div class="stat-label">Inactive</div>
                    </div>
                    <i class="fas fa-pause-circle stat-icon" style="color:#BD9414;"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value">4</div>
                        <div class="stat-label">Filters</div>
                    </div>
                    <i class="fas fa-filter stat-icon" style="color:#17a2b8;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="chart-card fadeup">
    <!-- Mobile-only: compact search + filter sheet trigger -->
    <div class="hr-mobile-search-bar d-md-none px-3 pt-3 pb-1">
        <div class="hr-search-input-wrap">
            <i class="fas fa-search hr-search-icon"></i>
            <input type="search" class="hr-search-input" id="mobileSearchEmp" placeholder="Search by name, employee ID, position, or department" aria-label="Search employees by name, employee ID, position, or department" title="Search by name, employee ID, position, or department">
        </div>
        <button type="button" class="hr-filter-btn" id="mobileFilterOpenBtn" data-hr-filter-open>
            <i class="fas fa-sliders-h"></i> Filters
            <span class="hr-filter-count" style="display:none;">0</span>
        </button>
    </div>

    <div class="cc-header">
        <h5 class="d-none d-md-block"><i class="fas fa-users me-2"></i>All Employees</h5>
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="search" class="form-control form-control-sm" id="customSearchEmp"
                placeholder="Search by name, employee ID, position, or department" aria-label="Search employees by name, employee ID, position, or department" title="Search by name, employee ID, position, or department">
        </div>
    </div>

    <!-- Filter Toolbar -->
    <div class="filter-toolbar" id="filterToolbar">
        <div class="filter-group">
            <label><i class="fas fa-briefcase me-1"></i>Job Title</label>
            <select id="filterJobTitle">
                <option value="">All Titles</option>
                <?php foreach ($job_titles_by_dept as $dept_name => $titles): ?>
                    <optgroup label="<?php echo e($dept_name); ?>">
                        <?php foreach ($titles as $jt): ?>
                            <option value="<?php echo e($jt['job_title']); ?>" 
                                    data-job-title-id="<?php echo $jt['job_title_id']; ?>" 
                                    data-department="<?php echo e($jt['department_name'] ?? ''); ?>">
                                <?php echo e($jt['job_title']); ?>
                                <?php if (!empty($jt['rank_name'])): ?>
                                    — [<?php echo e($jt['rank_name']); ?>]
                                <?php endif; ?>
                                <?php if ($jt['employee_count'] > 0): ?>
                                    (<?php echo $jt['employee_count']; ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label><i class="fas fa-sitemap me-1"></i>Department</label>
            <select id="filterDepartment">
                <option value="">All Departments</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?php echo e($dept); ?>"><?php echo e($dept); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label><i class="fas fa-building me-1"></i>Branch</label>
            <select id="filterBranch">
                <option value="">All Branches</option>
                <?php foreach ($branches as $br): ?>
                    <option value="<?php echo e($br); ?>" <?php echo ($selected_branch === $br) ? 'selected' : ''; ?>>
                        <?php echo e($br); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label><i class="fas fa-user-tag me-1"></i>Status</label>
            <select id="filterStatus">
                <option value="">All Statuses</option>
                <?php foreach ($statuses as $st): ?>
                    <option value="<?php echo e($st); ?>"><?php echo e($st); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Active Filter Chips -->
    <div class="filter-summary" id="filterSummary">
        <span class="filter-label"><i class="fas fa-filter me-1"></i>Filters:</span>
        <div id="filterChips"></div>
        <button class="btn-clear-filters" id="clearAllFilters" title="Clear all filters">
            <i class="fas fa-times me-1"></i>Clear All
        </button>
    </div>
    <div class="cc-body p-0">
        <!-- Desktop Table (hidden on mobile) -->
        <div class="table-responsive d-none d-md-block">
            <table class="table table-hover" id="empTable">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Name</th>
                        <th>Job Title</th>
                        <th>Department</th>
                        <th>Branch</th>
                        <th>Status</th>
                        <th>Hire Date</th>
                        <th style="min-width: 170px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $count = 1;
                    while ($emp = $employees->fetch_assoc()):
                        // Compute days remaining for non-regular statuses
                        $nonRegSt = ['OJT','Trainee','Probationary','Project Based','Project-Based'];
                        $emp_dr = null;
                        if (in_array($emp['employment_status'], $nonRegSt, true)) {
                            if (!empty($emp['contract_end_date'])) {
                                $emp_dr = (int)((strtotime($emp['contract_end_date']) - strtotime(date('Y-m-d'))) / 86400);
                            } elseif ($emp['employment_status'] === 'Probationary') {
                                $emp_dr = (int)((strtotime(date('Y-m-d', strtotime($emp['hire_date'] . ' +6 months'))) - strtotime(date('Y-m-d'))) / 86400);
                            } elseif (in_array($emp['employment_status'], ['OJT','Trainee'], true)) {
                                $emp_dr = (int)((strtotime(date('Y-m-d', strtotime($emp['hire_date'] . ' +60 days'))) - strtotime(date('Y-m-d'))) / 86400);
                            }
                        }
                    ?>
                        <tr data-jobtitle="<?php echo e($emp['job_title']); ?>"
                            data-department="<?php echo e($emp['department_name'] ?? 'N/A'); ?>"
                            data-branch="<?php echo e($emp['branch_name'] ?? 'N/A'); ?>"
                            data-status="<?php echo e($emp['employment_status']); ?>"
                            data-search="<?php echo e($emp['employee_id'] . ' ' . ($emp['employee_code'] ?? '') . ' ' . getEmployeeDisplayId($emp) . ' ' . $emp['first_name'] . ' ' . $emp['last_name'] . ' ' . $emp['last_name'] . ' ' . $emp['first_name'] . ' ' . ($emp['job_title'] ?? '') . ' ' . ($emp['department_name'] ?? '') . ' ' . ($emp['branch_name'] ?? '') . ' ' . ($emp['employment_status'] ?? '')); ?>"
                            data-active="<?php echo $emp['is_active'] ? '1' : '0'; ?>"
                            style="display: none;">
                            <td data-label="#"><strong><?php echo $count++; ?></strong></td>
                            <td data-label="Name">
                                <div class="d-flex align-items-center">
                                    <img src="<?php echo getEmployeeAvatar($emp['profile_picture']); ?>" alt="Profile"
                                        class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                                    <div>
                                        <strong><?php echo e($emp['last_name'] . ', ' . $emp['first_name']); ?></strong>
                                        <div class="text-muted small">ID: <span class="company-id-value"><?php echo e(getEmployeeDisplayId($emp)); ?></span></div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Job Title">
                                <?php
                                    $rankId = (int)($emp['rank_category_id'] ?? 0);
                                    $badgeClass = getJobTitleBadgeClass($rankId);
                                ?>
                                <span class="job-badge <?php echo $badgeClass; ?>"><?php echo e($emp['job_title'] ?? 'N/A'); ?></span>
                            </td>
                            <td data-label="Department"><?php echo e($emp['department_name'] ?? 'N/A'); ?></td>
                            <td data-label="Branch"><?php echo e($emp['branch_name'] ?? 'N/A'); ?></td>
                            <td data-label="Status">
                                <?php echo renderEmploymentStatusBadge($emp['employment_status']); ?>
                                <?php if (!$emp['is_active'] && !empty($emp['separation_date'])): ?>
                                    <small class="text-muted d-block mt-1" style="font-size: 0.72rem; white-space: nowrap;">
                                        <i class="fas fa-calendar-times me-1 text-danger"></i>Eff: <?php echo formatDate($emp['separation_date']); ?>
                                    </small>
                                <?php elseif ($emp_dr !== null && $emp_dr <= 60): ?>
                                    <?php
                                    if ($emp_dr < 0)       { $eI = 'fa-times-circle';         $eL = 'Overdue '.abs($emp_dr).'d'; $eC = 'bg-danger'; }
                                    elseif ($emp_dr === 0) { $eI = 'fa-exclamation-circle';  $eL = 'Ends Today!';               $eC = 'bg-danger'; }
                                    elseif ($emp_dr <= 14) { $eI = 'fa-exclamation-triangle'; $eL = $emp_dr.'d left';             $eC = 'bg-warning text-dark'; }
                                    elseif ($emp_dr <= 30) { $eI = 'fa-exclamation-circle';   $eL = $emp_dr.'d left';             $eC = 'bg-warning text-dark'; }
                                    else                   { $eI = 'fa-calendar-alt';         $eL = $emp_dr.'d left';             $eC = 'bg-info text-dark'; }
                                    ?>
                                    <br><span class="badge <?php echo $eC; ?> mt-1" style="font-size:0.65rem;"><i class="fas <?php echo $eI; ?> me-1"></i><?php echo $eL; ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Hire Date"><small><?php echo formatDate($emp['hire_date']); ?></small></td>
                            <td data-label="Actions">
                                <div class="d-flex gap-1 align-items-center flex-nowrap">
                                    <a href="<?php echo BASE_URL; ?>/manager/view-employee.php?id=<?php echo $emp['employee_id']; ?>"
                                        class="btn btn-sm btn-outline-info employee-view-link" data-base-href="<?php echo BASE_URL; ?>/manager/view-employee.php?id=<?php echo $emp['employee_id']; ?>" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>/manager/edit-employee.php?id=<?php echo $emp['employee_id']; ?>"
                                        class="btn btn-sm btn-outline-primary employee-edit-link" data-base-href="<?php echo BASE_URL; ?>/manager/edit-employee.php?id=<?php echo $emp['employee_id']; ?>"
                                        title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($emp['is_active']): ?>
                                        <button type="button" class="btn btn-sm btn-outline-warning" title="Deactivate"
                                            onclick="setDeactivateTarget(<?php echo $emp['employee_id']; ?>, '<?php echo e(addslashes($emp['first_name'] . ' ' . $emp['last_name'])); ?>')"
                                            data-bs-toggle="modal" data-bs-target="#deactivateModal">
                                            <i class="fas fa-user-slash"></i>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-outline-success" title="Activate"
                                            onclick="setActivateTarget(<?php echo $emp['employee_id']; ?>, '<?php echo e(addslashes($emp['first_name'] . ' ' . $emp['last_name'])); ?>')"
                                            data-bs-toggle="modal" data-bs-target="#activateModal">
                                            <i class="fas fa-user-check"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Delete Permanently"
                                        onclick="setDeleteTarget(<?php echo $emp['employee_id']; ?>, '<?php echo e(addslashes($emp['first_name'] . ' ' . $emp['last_name'])); ?>')"
                                        data-bs-toggle="modal" data-bs-target="#deleteModal">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Card List (visible only on mobile) -->
        <div class="mobile-list-view d-block d-md-none p-3">
            <div class="student-list">
                <?php
                $employees->data_seek(0);
                while ($emp = $employees->fetch_assoc()):
                ?>
                <div class="student-item hr-mobile-card mb-3 p-3 bg-white rounded-3 shadow-sm border"
                     data-jobtitle="<?php echo e($emp['job_title']); ?>"
                     data-department="<?php echo e($emp['department_name'] ?? 'N/A'); ?>"
                     data-branch="<?php echo e($emp['branch_name'] ?? 'N/A'); ?>"
                     data-status="<?php echo e($emp['employment_status']); ?>"
                     data-active="<?php echo $emp['is_active'] ? '1' : '0'; ?>"
                     data-search="<?php echo e($emp['employee_id'] . ' ' . ($emp['employee_code'] ?? '') . ' ' . getEmployeeDisplayId($emp) . ' ' . $emp['first_name'] . ' ' . $emp['last_name'] . ' ' . $emp['last_name'] . ' ' . $emp['first_name'] . ' ' . ($emp['job_title'] ?? '') . ' ' . ($emp['department_name'] ?? '') . ' ' . ($emp['branch_name'] ?? '') . ' ' . ($emp['employment_status'] ?? '')); ?>"
                     style="display: none; flex-direction: column; align-items: stretch; width: 100%; box-sizing: border-box;">

                    <!-- Top Header Bar: Status Badge on left + Actions Menu Button on right -->
                    <div class="d-flex align-items-center justify-content-between pb-2 mb-2 border-bottom">
                        <div>
                            <?php echo renderEmploymentStatusBadge($emp['employment_status']); ?>
                            <?php if (!$emp['is_active'] && !empty($emp['separation_date'])): ?>
                                <div class="text-muted mt-1" style="font-size: 0.72rem;">
                                    <i class="fas fa-calendar-times me-1 text-danger"></i>Eff: <?php echo formatDate($emp['separation_date']); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Actions Dropdown Menu -->
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light rounded-pill border shadow-sm px-2.5 py-1 d-flex align-items-center gap-1" type="button" data-bs-toggle="dropdown" data-bs-boundary="window" aria-expanded="false" style="background: #f8fafc; border-color: #cbd5e1 !important; font-size: 0.78rem;" title="Actions">
                                <i class="fas fa-ellipsis-h text-dark"></i> <span class="fw-semibold text-secondary" style="font-size:0.72rem;">Actions</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-1" style="border-radius: 12px; min-width: 170px; font-size: 0.85rem; z-index: 1095;">
                                <li>
                                    <a href="<?php echo BASE_URL; ?>/manager/view-employee.php?id=<?php echo $emp['employee_id']; ?>"
                                       class="dropdown-item py-2 d-flex align-items-center gap-2 employee-view-link"
                                       data-base-href="<?php echo BASE_URL; ?>/manager/view-employee.php?id=<?php echo $emp['employee_id']; ?>">
                                        <i class="fas fa-eye text-info" style="width: 18px;"></i> View Details
                                    </a>
                                </li>
                                <li>
                                    <a href="<?php echo BASE_URL; ?>/manager/edit-employee.php?id=<?php echo $emp['employee_id']; ?>"
                                       class="dropdown-item py-2 d-flex align-items-center gap-2 employee-edit-link"
                                       data-base-href="<?php echo BASE_URL; ?>/manager/edit-employee.php?id=<?php echo $emp['employee_id']; ?>">
                                        <i class="fas fa-edit text-primary" style="width: 18px;"></i> Edit Info
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider my-1"></li>
                                <?php if ($emp['is_active']): ?>
                                    <li>
                                        <button type="button" class="dropdown-item py-2 d-flex align-items-center gap-2 text-warning"
                                            onclick="setDeactivateTarget(<?php echo $emp['employee_id']; ?>, '<?php echo e(addslashes($emp['first_name'] . ' ' . $emp['last_name'])); ?>')"
                                            data-bs-toggle="modal" data-bs-target="#deactivateModal">
                                            <i class="fas fa-user-slash" style="width: 18px;"></i> Deactivate
                                        </button>
                                    </li>
                                <?php else: ?>
                                    <li>
                                        <button type="button" class="dropdown-item py-2 d-flex align-items-center gap-2 text-success"
                                            onclick="setActivateTarget(<?php echo $emp['employee_id']; ?>, '<?php echo e(addslashes($emp['first_name'] . ' ' . $emp['last_name'])); ?>')"
                                            data-bs-toggle="modal" data-bs-target="#activateModal">
                                            <i class="fas fa-user-check" style="width: 18px;"></i> Activate
                                        </button>
                                    </li>
                                <?php endif; ?>
                                <li>
                                    <button type="button" class="dropdown-item py-2 d-flex align-items-center gap-2 text-danger"
                                        onclick="setDeleteTarget(<?php echo $emp['employee_id']; ?>, '<?php echo e(addslashes($emp['first_name'] . ' ' . $emp['last_name'])); ?>')"
                                        data-bs-toggle="modal" data-bs-target="#deleteModal">
                                        <i class="fas fa-trash" style="width: 18px;"></i> Delete
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Main Card Body: Avatar + Name + Rank Badge -->
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <img src="<?php echo getEmployeeAvatar($emp['profile_picture']); ?>" alt="Profile"
                             class="rounded-circle border flex-shrink-0" style="width: 48px; height: 48px; object-fit: cover;">
                        <div style="flex:1; min-width:0;">
                            <h6 class="fw-bold mb-0 text-truncate" style="font-size: 0.95rem; color: #1c271b;">
                                <?php echo e($emp['last_name'] . ', ' . $emp['first_name']); ?>
                            </h6>
                            <div class="text-muted small mb-1">ID: <span class="company-id-value"><?php echo e(getEmployeeDisplayId($emp)); ?></span></div>
                            <div class="d-flex align-items-center gap-1 flex-wrap">
                                <?php
                                    $rankIdMob = (int)($emp['rank_category_id'] ?? 0);
                                    $badgeClassMob = getJobTitleBadgeClass($rankIdMob);
                                ?>
                                <span class="job-badge <?php echo $badgeClassMob; ?>"><?php echo e($emp['job_title'] ?? 'N/A'); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Full-Width Metadata Grid (makes the design feel rich & occupied) -->
                    <div class="p-2.5 rounded-2 bg-light border text-muted small mt-2">
                        <div class="d-flex align-items-center justify-content-between mb-1 gap-2">
                            <span class="text-truncate"><i class="fas fa-sitemap me-1 opacity-75" style="color:var(--hrm-green-mid);"></i><strong>Dept:</strong> <?php echo e($emp['department_name'] ?? 'N/A'); ?></span>
                            <span class="text-nowrap"><i class="fas fa-calendar-alt me-1 opacity-75" style="color:var(--hrm-gold);"></i><?php echo formatDate($emp['hire_date']); ?></span>
                        </div>
                        <div class="text-truncate">
                            <i class="fas fa-building me-1 opacity-75" style="color:var(--hrm-green-mid);"></i><strong>Branch:</strong> <?php echo e($emp['branch_name'] ?? 'N/A'); ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- Pagination Controls -->
        <div class="d-flex justify-content-between align-items-center p-3 border-top" id="paginationWrapper">
            <div id="paginationInfo" class="text-muted small"></div>
            <ul class="pagination pagination-sm mb-0" id="paginationNumbers">
            </ul>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<!-- Separation (Deactivate) Confirmation Modal -->
<div class="modal fade" id="deactivateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="fas fa-user-slash me-2"></i>Employee Separation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <p class="mb-1">Select separation details for <strong id="deactivateEmpName"></strong>:</p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Reason for Separation <span class="text-danger">*</span></label>
                    <select id="separationReason" class="form-select" required>
                        <option value="" selected disabled>Select a reason</option>
                        <option value="Separated">Separated (General)</option>
                        <option value="AWOL">AWOL</option>
                        <option value="Retirement">Retirement</option>
                        <option value="Death">Death</option>
                        <option value="Permanent or Total Disability">Permanent or Total Disability</option>
                        <option value="Resignation">Resignation</option>
                        <option value="Failed in Training">Failed in Training</option>
                        <option value="Termination for Cause">Termination for Cause</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Effective Date <span class="text-danger">*</span></label>
                    <input type="date" id="separationDate" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    <div class="form-text small">Date when the separation takes official effect.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Separation Remarks / Notes <span class="text-muted fw-normal">(Optional)</span></label>
                    <textarea id="separationRemarks" class="form-control" rows="2" placeholder="e.g. Clearance processed, voluntary resignation, etc."></textarea>
                </div>
                <p class="text-muted small text-center mb-0"><i class="fas fa-info-circle me-1"></i>This will mark the employee as inactive, record the separation effective date, and update their employment status.</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="deactivateConfirmBtn" class="btn btn-warning">
                    <i class="fas fa-user-slash me-1"></i>Confirm Separation
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Activate Confirmation Modal -->
<div class="modal fade" id="activateModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-user-check me-2"></i>Activate Employee</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <p>Reactivate <strong id="activateEmpName"></strong>?</p>
                <p class="text-muted small">This will mark them as an active employee again and reset their status to <strong>Regular</strong>.</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="activateConfirmBtn" class="btn btn-success"><i
                        class="fas fa-user-check me-1"></i>Activate</a>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Delete Employee</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <p>Permanently delete <strong id="deleteEmpName"></strong>?</p>
                <p class="text-danger small"><i class="fas fa-exclamation-circle me-1"></i>This will remove all their
                    records including evaluations. This cannot be undone!</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="deleteConfirmBtn" class="btn btn-danger"><i class="fas fa-trash me-1"></i>Delete
                    Permanently</a>
            </div>
        </div>
    </div>
</div>

<!-- Mobile Filter Bottom Sheet Drawer -->
<div class="hr-filter-backdrop" id="hrFilterBackdrop"></div>
<div class="hr-filter-sheet" id="hrFilterSheet">
    <div class="hr-filter-sheet-handle"></div>
    <div class="hr-filter-sheet-header">
        <h6 class="hr-filter-sheet-title"><i class="fas fa-sliders-h me-2" style="color:var(--hrm-gold);"></i>Filter Employees</h6>
        <button type="button" class="hr-filter-clear-btn" id="hrFilterClear">Reset All</button>
    </div>
    <div class="hr-filter-sheet-body">
        <div class="hr-filter-group">
            <label><i class="fas fa-briefcase me-1"></i>Job Title</label>
            <select id="mobileFilterJobTitle">
                <option value="">All Titles</option>
                <?php foreach ($job_titles_by_dept as $dept_name => $titles): ?>
                    <optgroup label="<?php echo e($dept_name); ?>">
                        <?php foreach ($titles as $jt): ?>
                            <option value="<?php echo e($jt['job_title']); ?>"
                                    data-job-title-id="<?php echo $jt['job_title_id']; ?>" 
                                    data-department="<?php echo e($jt['department_name'] ?? ''); ?>">
                                <?php echo e($jt['job_title']); ?>
                                <?php if (!empty($jt['rank_name'])): ?>
                                    — [<?php echo e($jt['rank_name']); ?>]
                                <?php endif; ?>
                                <?php if ($jt['employee_count'] > 0): ?>
                                    (<?php echo $jt['employee_count']; ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="hr-filter-group">
            <label><i class="fas fa-sitemap me-1"></i>Department</label>
            <select id="mobileFilterDepartment">
                <option value="">All Departments</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?php echo e($dept); ?>"><?php echo e($dept); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="hr-filter-group">
            <label><i class="fas fa-building me-1"></i>Branch</label>
            <select id="mobileFilterBranch">
                <option value="">All Branches</option>
                <?php foreach ($branches as $br): ?>
                    <option value="<?php echo e($br); ?>" <?php echo ($selected_branch === $br) ? 'selected' : ''; ?>><?php echo e($br); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="hr-filter-group">
            <label><i class="fas fa-user-tag me-1"></i>Status</label>
            <select id="mobileFilterStatus">
                <option value="">All Statuses</option>
                <?php foreach ($statuses as $st): ?>
                    <option value="<?php echo e($st); ?>"><?php echo e($st); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="hr-filter-sheet-footer">
        <button type="button" class="hr-filter-apply-btn" id="hrFilterApply">
            <i class="fas fa-check me-1"></i>Apply Filters
        </button>
    </div>
</div>

<script>
    let deactivateTargetId = null;
    function setDeactivateTarget(id, name) {
        deactivateTargetId = id;
        document.getElementById('deactivateEmpName').textContent = name;
        document.getElementById('separationReason').value = '';
        document.getElementById('separationDate').value = '<?php echo date('Y-m-d'); ?>';
        document.getElementById('separationRemarks').value = '';
    }

    document.getElementById('deactivateConfirmBtn').addEventListener('click', function() {
        const reason = document.getElementById('separationReason').value;
        const effDate = document.getElementById('separationDate').value;
        const remarks = document.getElementById('separationRemarks').value.trim();

        if (!reason) {
            alert('Please select a reason for separation.');
            document.getElementById('separationReason').focus();
            return;
        }
        if (!effDate) {
            alert('Please select an effective date.');
            document.getElementById('separationDate').focus();
            return;
        }
        if (deactivateTargetId) {
            const params = new URLSearchParams(window.location.search);
            params.set('deactivate', deactivateTargetId);
            params.set('status', reason);
            params.set('effective_date', effDate);
            if (remarks) {
                params.set('remarks', remarks);
            } else {
                params.delete('remarks');
            }
            window.location.href = '?' + params.toString();
        }
    });
    function setActivateTarget(id, name) {
        document.getElementById('activateEmpName').textContent = name;
        const params = new URLSearchParams(window.location.search);
        params.set('activate', id);
        document.getElementById('activateConfirmBtn').href = '?' + params.toString();
    }
    function setDeleteTarget(id, name) {
        document.getElementById('deleteEmpName').textContent = name;
        const params = new URLSearchParams(window.location.search);
        params.set('delete', id);
        document.getElementById('deleteConfirmBtn').href = '?' + params.toString();
    }

    // State Variables
    let currentPage = 1;
    const ITEMS_PER_PAGE = 10;

    document.getElementById('customSearchEmp').addEventListener('input', function () {
        currentPage = 1;
        syncFiltersToUrl();
        renderTable();
    });

    // --- Mobile Search Sync ---
    const mobSearch = document.getElementById('mobileSearchEmp');
    if (mobSearch) {
        mobSearch.addEventListener('input', function () {
            document.getElementById('customSearchEmp').value = this.value;
            currentPage = 1;
            syncFiltersToUrl();
            renderTable();
        });
    }

    // --- Mobile Filter Apply ---
    function updateMobileFilterBadge() {
        const ids = ['mobileFilterJobTitle','mobileFilterDepartment','mobileFilterBranch','mobileFilterStatus'];
        const activeCount = ids.filter(id => { const el = document.getElementById(id); return el && el.value !== ''; }).length;
        const badge = document.querySelector('#mobileFilterOpenBtn .hr-filter-count');
        if (badge) { badge.textContent = activeCount; badge.style.display = activeCount > 0 ? 'inline-flex' : 'none'; }
        const filterBtn = document.getElementById('mobileFilterOpenBtn');
        if (filterBtn) filterBtn.classList.toggle('active', activeCount > 0);
    }

    document.getElementById('hrFilterApply')?.addEventListener('click', function () {
        currentPage = 1;
        updateMobileFilterBadge();
        renderTable();
        updateFilterChips();
    });

    document.getElementById('hrFilterClear')?.addEventListener('click', function () {
        ['mobileFilterJobTitle','mobileFilterDepartment','mobileFilterBranch','mobileFilterStatus'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        currentPage = 1;
        updateMobileFilterBadge();
        renderTable();
        updateFilterChips();
    });

    // --- Dropdown Filter Logic ---
    const filterSelects = ['filterJobTitle', 'filterDepartment', 'filterBranch', 'filterStatus'];
    const filterLabels = { filterJobTitle: 'Job Title', filterDepartment: 'Department', filterBranch: 'Branch', filterStatus: 'Status' };
    const filterParams = { filterJobTitle: 'job_title', filterDepartment: 'department', filterBranch: 'branch', filterStatus: 'status' };

    function applyFiltersFromUrl() {
        const params = new URLSearchParams(window.location.search);
        const searchVal = params.get('search') || '';
        document.getElementById('customSearchEmp').value = searchVal;
        const mobSearch = document.getElementById('mobileSearchEmp');
        if (mobSearch) mobSearch.value = searchVal;

        filterSelects.forEach(id => {
            const el = document.getElementById(id);
            const value = params.get(filterParams[id]) || '';
            if (el) {
                el.value = value;
                el.classList.toggle('active-filter', value !== '');
            }
        });

        // Sync mobile filter selects
        const mobMap = {
            job_title: 'mobileFilterJobTitle',
            department: 'mobileFilterDepartment',
            branch: 'mobileFilterBranch',
            status: 'mobileFilterStatus'
        };
        Object.entries(mobMap).forEach(([paramKey, mobId]) => {
            const mobEl = document.getElementById(mobId);
            if (mobEl) mobEl.value = params.get(paramKey) || '';
        });

        const selectedDept = params.get('department') || '';
        filterJobTitleOptgroups(selectedDept);
    }

    function syncFiltersToUrl() {
        const params = new URLSearchParams();
        const search = document.getElementById('customSearchEmp').value.trim();
        if (search !== '') params.set('search', search);

        filterSelects.forEach(id => {
            const value = document.getElementById(id).value;
            if (value !== '') params.set(filterParams[id], value);
        });

        const query = params.toString();
        const nextUrl = window.location.pathname + (query ? '?' + query : '');
        history.replaceState(null, '', nextUrl);
        updateEmployeeActionLinks();
    }

    function updateEmployeeActionLinks() {
        const returnUrl = window.location.pathname + window.location.search;
        document.querySelectorAll('.employee-edit-link').forEach(link => {
            link.href = link.dataset.baseHref + '&return=' + encodeURIComponent(returnUrl);
        });
        document.querySelectorAll('.employee-view-link').forEach(link => {
            link.href = link.dataset.baseHref + '&return=' + encodeURIComponent(returnUrl);
        });
    }

    const jobTitleCache = {};

    function initJobTitleSelectCache() {
        ['filterJobTitle', 'mobileFilterJobTitle'].forEach(id => {
            const el = document.getElementById(id);
            if (el && !jobTitleCache[id]) {
                jobTitleCache[id] = Array.from(el.children).map(child => child.cloneNode(true));
            }
        });
    }

    function filterJobTitleOptgroups(selectedDept) {
        initJobTitleSelectCache();
        ['filterJobTitle', 'mobileFilterJobTitle'].forEach(id => {
            const select = document.getElementById(id);
            if (select && jobTitleCache[id]) {
                const curVal = select.value;
                select.innerHTML = '';
                jobTitleCache[id].forEach(node => {
                    if (node.tagName.toLowerCase() === 'option') {
                        select.appendChild(node.cloneNode(true));
                    } else if (node.tagName.toLowerCase() === 'optgroup') {
                        const groupLabel = node.getAttribute('label') || '';
                        if (selectedDept === '' || groupLabel === selectedDept) {
                            select.appendChild(node.cloneNode(true));
                        }
                    }
                });
                select.value = curVal;
                if (select.selectedIndex === -1) select.value = '';
            }
        });
    }

    // --- Department Filter Handler (syncs both desktop & mobile job title optgroups) ---
    function handleDepartmentChange(selectedDept, sourceId) {
        filterJobTitleOptgroups(selectedDept);

        // Sync department select values between desktop & mobile
        const mainDept = document.getElementById('filterDepartment');
        const mobDept = document.getElementById('mobileFilterDepartment');
        if (mainDept && sourceId !== 'filterDepartment') mainDept.value = selectedDept;
        if (mobDept && sourceId !== 'mobileFilterDepartment') mobDept.value = selectedDept;

        if (mainDept) mainDept.classList.toggle('active-filter', mainDept.value !== '');

        // Reset job title selections if current selection is not in the filtered optgroups
        const mainJT = document.getElementById('filterJobTitle');
        const mobJT = document.getElementById('mobileFilterJobTitle');
        if (mainJT && mainJT.selectedIndex === -1) { mainJT.value = ''; mainJT.classList.remove('active-filter'); }
        if (mobJT && mobJT.selectedIndex === -1) { mobJT.value = ''; }

        currentPage = 1;
        syncFiltersToUrl();
        renderTable();
        updateFilterChips();
    }

    document.getElementById('filterDepartment')?.addEventListener('change', function () {
        handleDepartmentChange(this.value, 'filterDepartment');
    });

    document.getElementById('mobileFilterDepartment')?.addEventListener('change', function () {
        handleDepartmentChange(this.value, 'mobileFilterDepartment');
    });


    // Regular handlers for other filters
    ['filterJobTitle', 'filterBranch', 'filterStatus'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', function () {
            currentPage = 1;
            this.classList.toggle('active-filter', this.value !== '');
            syncFiltersToUrl();
            renderTable();
            updateFilterChips();
        });
    });

    function updateFilterChips() {
        const chipsContainer = document.getElementById('filterChips');
        const summary = document.getElementById('filterSummary');
        let html = '';
        let hasAny = false;

        filterSelects.forEach(id => {
            const el = document.getElementById(id);
            if (el.value !== '') {
                hasAny = true;
                html += `<span class="filter-chip"><span class="chip-category">${filterLabels[id]}:</span> ${el.value} <i class="fas fa-times remove-chip" data-filter="${id}"></i></span>`;
            }
        });

        chipsContainer.innerHTML = html;
        summary.classList.toggle('has-filters', hasAny);

        // Bind remove chip clicks
        chipsContainer.querySelectorAll('.remove-chip').forEach(btn => {
            btn.addEventListener('click', function () {
                const filterId = this.dataset.filter;
                const select = document.getElementById(filterId);
                select.value = '';
                select.classList.remove('active-filter');
                
                // If removing Department filter, show all Job Title optgroups
                if (filterId === 'filterDepartment') {
                    const jobTitleSelect = document.getElementById('filterJobTitle');
                    const optgroups = jobTitleSelect.querySelectorAll('optgroup');
                    optgroups.forEach(group => {
                        group.style.display = '';
                    });
                }
                
                currentPage = 1;
                syncFiltersToUrl();
                renderTable();
                updateFilterChips();
            });
        });
    }

    document.getElementById('clearAllFilters').addEventListener('click', function () {
        filterSelects.forEach(id => {
            const el = document.getElementById(id);
            el.value = '';
            el.classList.remove('active-filter');
        });
        
        // Show all job title optgroups again
        const jobTitleSelect = document.getElementById('filterJobTitle');
        const optgroups = jobTitleSelect.querySelectorAll('optgroup');
        optgroups.forEach(group => {
            group.style.display = '';
        });
        
        currentPage = 1;
        syncFiltersToUrl();
        renderTable();
        updateFilterChips();
    });

    function goToPage(page) {
        currentPage = page;
        renderTable();
    }

    function renderTable() {
        const tbody = document.querySelector("#empTable tbody");
        const allRows = Array.from(tbody.querySelectorAll("tr:not(.no-results-row)"));
        const filterInput = document.getElementById('customSearchEmp').value.toLowerCase().trim();

        // Detect mobile by checking if the mobile card list is actually visible in CSS
        // (same breakpoint as Bootstrap d-block d-md-none — avoids window.innerWidth discrepancies on Android)
        const mobileView = document.querySelector('.mobile-list-view');
        const isMobile   = mobileView
            ? window.getComputedStyle(mobileView).display !== 'none'
            : window.matchMedia('(max-width: 767.98px)').matches;

        function normText(str) {
            if (!str) return '';
            return str.replace(/&amp;/g, '&').replace(/\s+/g, ' ').trim().toLowerCase();
        }

        // Read from the correct select based on which view is actually visible.
        // Fallback: if the primary select is empty, also check the other one.
        function getFilterVal(desktopId, mobileId) {
            const primaryId = isMobile ? mobileId : desktopId;
            const fallbackId = isMobile ? desktopId : mobileId;
            const primary = document.getElementById(primaryId);
            const fallback = document.getElementById(fallbackId);
            if (primary && primary.value !== '') return primary.value;
            if (fallback && fallback.value !== '') return fallback.value;
            return '';
        }

        const fJobTitle   = getFilterVal('filterJobTitle',   'mobileFilterJobTitle');
        const fDepartment = getFilterVal('filterDepartment', 'mobileFilterDepartment');
        const fBranch     = getFilterVal('filterBranch',     'mobileFilterBranch');
        const fStatus     = getFilterVal('filterStatus',     'mobileFilterStatus');

        const normFJobTitle = normText(fJobTitle);
        const normFDept     = normText(fDepartment);
        const normFBranch   = normText(fBranch);
        const normFStatus   = normText(fStatus);
        const showInactive = filterInput !== '' || normFStatus !== '';

        let visibleRows = [];

        // 1. Filter desktop rows (text search + dropdown filters)
        allRows.forEach(row => {
            const cells = Array.from(row.querySelectorAll("td"));
            if (cells.length > 1) {
                // Text search
                const rowText = (row.dataset.search || cells.slice(0, 6).map(td => td.textContent.trim().replace(/\s+/g, ' ')).join(' ')).toLowerCase();
                const textMatch = filterInput === "" || rowText.includes(filterInput);

                // Dropdown filters
                const dropdownMatch =
                    (normFJobTitle === '' || normText(row.dataset.jobtitle) === normFJobTitle) &&
                    (normFDept     === '' || normText(row.dataset.department) === normFDept) &&
                    (normFBranch   === '' || normText(row.dataset.branch) === normFBranch) &&
                    (normFStatus   === '' || normText(row.dataset.status) === normFStatus);
                const activeMatch = row.dataset.active === '1' || showInactive;

                if (textMatch && dropdownMatch && activeMatch) {
                    visibleRows.push(row);
                    row.classList.remove('filtered-out');
                } else {
                    row.classList.add('filtered-out');
                    row.style.display = "none";
                }
            }
        });

        // Filter mobile card items
        const mobileList = document.querySelector(".mobile-list-view .student-list");
        const allCards = mobileList ? Array.from(mobileList.querySelectorAll(".student-item")) : [];
        let visibleCards = [];
        allCards.forEach(card => {
            const cardText = (card.dataset.search || card.textContent).toLowerCase();
            const textMatch = filterInput === "" || cardText.includes(filterInput);
            const dropdownMatch =
                (normFJobTitle === '' || normText(card.dataset.jobtitle) === normFJobTitle) &&
                (normFDept     === '' || normText(card.dataset.department) === normFDept) &&
                (normFBranch   === '' || normText(card.dataset.branch) === normFBranch) &&
                (normFStatus   === '' || normText(card.dataset.status) === normFStatus);
            const activeMatch = card.dataset.active === '1' || showInactive;

            if (textMatch && dropdownMatch && activeMatch) {
                visibleCards.push(card);
            } else {
                card.style.display = "none";
            }
        });

        // 2. Paginate — desktop uses row count, mobile uses card count independently
        const activeCount  = isMobile ? visibleCards.length : visibleRows.length;
        const totalPages   = Math.ceil(activeCount / ITEMS_PER_PAGE);
        if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const startIdx = (currentPage - 1) * ITEMS_PER_PAGE;
        const endIdx   = startIdx + ITEMS_PER_PAGE;

        // Paginate desktop rows
        let visibleCount = 0;
        visibleRows.forEach((row, index) => {
            if (index >= startIdx && index < endIdx) {
                row.style.display = "";
                row.classList.remove('odd-row', 'even-row');
                row.classList.add(visibleCount % 2 === 0 ? 'odd-row' : 'even-row');
                const numCell = row.querySelector('td:first-child strong');
                if (numCell) numCell.textContent = startIdx + visibleCount + 1;
                visibleCount++;
            } else {
                row.style.display = "none";
            }
        });

        // Paginate mobile cards (independent pagination)
        const cardStartIdx = (currentPage - 1) * ITEMS_PER_PAGE;
        const cardEndIdx   = cardStartIdx + ITEMS_PER_PAGE;
        visibleCards.forEach((card, index) => {
            if (index >= cardStartIdx && index < cardEndIdx) {
                card.style.display = "flex"; // explicit — overrides inline display:none
            } else {
                card.style.display = "none";
            }
        });

        updatePaginationUI(activeCount, totalPages);
        handleNoResults(isMobile ? visibleCards.length : visibleRows.length, filterInput, tbody);
        updateStatCards(visibleRows);
    }

    function updateStatCards(visibleRows) {
        const total = visibleRows.length;
        let active = 0;
        visibleRows.forEach(row => {
            if (row.dataset.active === '1') active++;
        });
        const inactive = total - active;

        const statValues = document.querySelectorAll('.stat-card .stat-value');
        if (statValues.length >= 3) {
            statValues[0].textContent = total;
            statValues[1].textContent = active;
            statValues[2].textContent = inactive;
        }
    }

    function updatePaginationUI(totalItems, totalPages) {
        const info = document.getElementById("paginationInfo");
        const digits = document.getElementById("paginationNumbers");
        if (!info || !digits) return;

        if (totalItems === 0) {
            info.innerHTML = "Showing 0 entries";
            digits.innerHTML = "";
            return;
        }

        const start = (currentPage - 1) * ITEMS_PER_PAGE + 1;
        const end = Math.min(currentPage * ITEMS_PER_PAGE, totalItems);
        info.innerHTML = `Showing ${start} to ${end} of ${totalItems} entries`;

        let html = "";

        // Previous Button
        html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                <button class="page-link" onclick="goToPage(${currentPage - 1})">Previous</button>
             </li>`;

        // Page Numbers (Show max 5 pagination buttons for cleaner look if many pages)
        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, startPage + 4);
        if (endPage - startPage < 4) {
            startPage = Math.max(1, endPage - 4);
        }

        if (startPage > 1) {
            html += `<li class="page-item"><button class="page-link" onclick="goToPage(1)">1</button></li>`;
            if (startPage > 2) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }
        for (let i = startPage; i <= endPage; i++) {
            html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                    <button class="page-link" onclick="goToPage(${i})">${i}</button>
                 </li>`;
        }
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
            html += `<li class="page-item"><button class="page-link" onclick="goToPage(${totalPages})">${totalPages}</button></li>`;
        }

        // Next Button
        html += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                <button class="page-link" onclick="goToPage(${currentPage + 1})">Next</button>
             </li>`;

        digits.innerHTML = html;
    }

    function handleNoResults(totalItems, filterInput, tbody) {
        const hasDropdownFilter = filterSelects.some(id => document.getElementById(id).value !== '');
        let noResultsRow = tbody.querySelector('.no-results-row');
        if (totalItems === 0 && (filterInput !== "" || hasDropdownFilter)) {
            if (!noResultsRow) {
                noResultsRow = document.createElement('tr');
                noResultsRow.className = 'no-results-row text-center';
                tbody.appendChild(noResultsRow);
            }
            let msg = 'No employees match the current filters.';
            if (filterInput !== '') msg = `No employees found matching "<strong>${filterInput}</strong>"`;
            noResultsRow.innerHTML = `<td colspan="8" class="py-4 text-muted"><i class="fas fa-filter fa-2x mb-3 d-block" style="opacity:0.2;"></i>${msg}</td>`;
            noResultsRow.style.display = '';
        } else if (noResultsRow) {
            noResultsRow.remove();
        }
    }

    // Initial Render on Load
    document.addEventListener("DOMContentLoaded", function () {
        initJobTitleSelectCache();
        applyFiltersFromUrl();
        updateEmployeeActionLinks();
        renderTable();
        updateFilterChips();
    });
</script>
