<?php
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
$page_title = 'Member List';
require_once '../includes/session-check.php';
checkRole(['Admin']);
require_once '../includes/functions.php';

require_once '../includes/header.php';

// Pagination settings
$per_page = 10;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $per_page;
$selected_department = isset($_GET['department']) && $_GET['department'] !== '' ? max(0, (int)$_GET['department']) : 0;
$search = trim($_GET['search'] ?? '');

$department_options = $conn->query("
    SELECT department_id, department_name
    FROM departments
    WHERE is_active = 1 AND deleted_at IS NULL
    ORDER BY department_name
");

$member_where = "
    WHERE ua.user_id IS NULL
";

if ($selected_department > 0) {
    $member_where .= " AND e.department_id = $selected_department";
}

if ($search !== '') {
    $search_safe = $conn->real_escape_string($search);
    $member_where .= " AND (e.first_name LIKE '%$search_safe%' OR e.last_name LIKE '%$search_safe%' OR CONCAT(e.first_name, ' ', e.last_name) LIKE '%$search_safe%' OR CONCAT(e.last_name, ', ', e.first_name) LIKE '%$search_safe%' OR e.employee_id LIKE '%$search_safe%' OR e.employee_code LIKE '%$search_safe%' OR e.job_title LIKE '%$search_safe%')";
}

// Count all employees (excluding strictly Admin accounts)
$total_members_result = $conn->query("
    SELECT COUNT(*) AS total
    FROM employees e
    LEFT JOIN users ua ON ua.employee_id = e.employee_id AND ua.role = 'Admin'
    $member_where
");
$total_members = (int)($total_members_result->fetch_assoc()['total'] ?? 0);
$total_pages = max(1, (int)ceil($total_members / $per_page));

if ($current_page > $total_pages) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $per_page;
}

// Fetch paginated employees with branch info (excluding strictly Admin accounts)
$employees = $conn->query("
    SELECT e.employee_id, e.employee_code, e.first_name, e.last_name, e.middle_name, e.job_title,
           e.rank_category_id,
           b.branch_name, d.department_name, e.profile_picture, e.is_active
    FROM employees e
    LEFT JOIN branches b ON e.branch_id = b.branch_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN users ua ON ua.employee_id = e.employee_id AND ua.role = 'Admin'
    $member_where
    ORDER BY e.last_name, e.first_name
    LIMIT $per_page OFFSET $offset
");

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
        white-space: nowrap;
    }
    .job-badge-executive   { background: #fff3cd; color: #856404; border: 1px solid #ffc107; }
    .job-badge-mgmt-team   { background: #ede7f6; color: #5e35b1; border: 1px solid #9c77e0; }
    .job-badge-manager     { background: #dbeafe; color: #1d4ed8; border: 1px solid #60a5fa; }
    .job-badge-supervisor  { background: #ccfbf1; color: #0f766e; border: 1px solid #2dd4bf; }
    .job-badge-rf          { background: #f0fdf4; color: #166534; border: 1px solid #86efac; }
    .job-badge-default     { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }

    .admin-member-toolbar {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: flex-end;
    }

    .admin-member-toolbar .form-select {
        min-width: 190px;
    }

    @media (max-width: 768px) {
        .admin-member-toolbar,
        .admin-member-toolbar form,
        .admin-member-toolbar .search-box {
            width: 100%;
        }

        .admin-member-toolbar .form-select,
        .admin-member-toolbar .search-box input {
            width: 100%;
        }
    }
</style>

<div class="admin-members-page">
<div class="page-hero fadeup">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-3">
        <div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">System Admin · Member Directory</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-id-card me-2" style="color:var(--primary-light);"></i>Member List</h4>
        </div>
        <div style="color:rgba(255,255,255,.6);font-size:.8rem;">
            <i class="fas fa-users me-1"></i><?php echo $total_members; ?> registered members
        </div>
    </div>
    <p class="text-white-50 small mb-0"><i class="fas fa-address-book me-1"></i>Full list of registered employees in the HRIS system.</p>
</div>

<div class="content-card fadeup-1">
    <div class="card-header">
        <h5><i class="fas fa-id-card me-2"></i>Employee Members</h5>
        <div class="admin-member-toolbar">
            <form method="GET" class="d-flex align-items-center gap-2 flex-wrap">
                <select name="department" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Filter members by department">
                    <option value="">All Departments</option>
                    <?php while ($department = $department_options->fetch_assoc()): ?>
                        <option value="<?php echo (int) $department['department_id']; ?>" <?php echo $selected_department === (int) $department['department_id'] ? 'selected' : ''; ?>>
                            <?php echo e($department['department_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="search" name="search" class="form-control form-control-sm" id="searchMembers" value="<?php echo e($search); ?>" placeholder="Search by name, ID, position..." onkeyup="filterTable('searchMembers', 'membersTable')">
                </div>
            </form>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive d-none d-md-block">
            <table class="table table-hover" id="membersTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Employee ID</th>
                        <th>Photo</th>
                        <th>Full Name</th>
                        <th>Department</th>
                        <th>Branch</th>
                        <th>Position</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $row_number = $offset + 1; ?>
                    <?php if ($employees->num_rows === 0): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">No members found for the selected department.</td>
                        </tr>
                    <?php else: ?>
                        <?php while ($emp = $employees->fetch_assoc()): ?>
                            <tr>
                                <td data-label="#"><strong><?php echo $row_number++; ?></strong></td>
                                <td data-label="Employee ID"><strong class="company-id-value"><?php echo e(getEmployeeDisplayId($emp)); ?></strong></td>
                                <td data-label="Photo">
                                    <img src="<?php echo getEmployeeAvatar($emp['profile_picture']); ?>"
                                         alt="Profile" class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover;">
                                </td>
                                <td data-label="Full Name"><strong><?php echo e($emp['last_name'] . ', ' . $emp['first_name'] . ' ' . $emp['middle_name']); ?></strong></td>
                                <td data-label="Department"><?php echo e($emp['department_name'] ?? 'N/A'); ?></td>
                                <td data-label="Branch"><?php echo e($emp['branch_name'] ?? 'N/A'); ?></td>
                                <td data-label="Position">
                                    <?php 
                                        $rankId = (int)($emp['rank_category_id'] ?? 0);
                                        $badgeClass = getJobTitleBadgeClass($rankId);
                                        $title = e($emp['job_title'] ?? 'N/A');
                                    ?>
                                    <span class="job-badge <?php echo $badgeClass; ?>"><?php echo $title; ?></span>
                                </td>
                                <td data-label="Status">
                                    <span class="badge <?php echo $emp['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $emp['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
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
                <?php if ($employees->num_rows === 0): ?>
                    <div class="text-center py-4 text-muted">No members found for the selected department.</div>
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
                                <div class="student-name"><?php echo e($emp['last_name'] . ', ' . $emp['first_name'] . ' ' . $emp['middle_name']); ?></div>
                                <div class="student-meta">
                                    <span class="company-id-text">ID: <span class="company-id-value"><?php echo e(getEmployeeDisplayId($emp)); ?></span></span>
                                    &bull; 
                                    <?php 
                                        $rankIdMob = (int)($emp['rank_category_id'] ?? 0);
                                        $badgeClassMob = getJobTitleBadgeClass($rankIdMob);
                                    ?>
                                    <span class="job-badge <?php echo $badgeClassMob; ?>"><?php echo e($emp['job_title'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="student-meta" style="margin-top: 2px;">
                                    <span><?php echo e($emp['department_name'] ?? 'N/A'); ?></span>
                                    &bull; <span><?php echo e($emp['branch_name'] ?? 'N/A'); ?></span>
                                </div>
                            </div>
                            <div class="ms-auto text-end">
                                <span class="badge <?php echo $emp['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo $emp['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
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
            <nav aria-label="Members pagination">
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
<?php require_once '../includes/footer.php'; ?>
