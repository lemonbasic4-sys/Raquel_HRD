<?php
/**
 * HR Staff Portal - Employee Management
 * Full employee list with Edit access (changes go to Manager for approval).
 * Replaces: staff/search-employees.php
 */
function staffGetJobTitleBadgeClass(int $rankId): string {
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
checkRole(['HR Staff']);
require_once '../includes/functions.php';

ensureEmployeeChangeRequests($conn);

// Pending change request count per employee (for badge display)
$pending_eids = [];
$pcr = $conn->query("SELECT DISTINCT employee_id FROM employee_change_requests WHERE status = 'Pending'");
if ($pcr) { while ($r = $pcr->fetch_assoc()) $pending_eids[$r['employee_id']] = true; }

require_once '../includes/header.php';

// Fetch all employees (exclude Admin accounts)
$employees = $conn->query("
    SELECT e.*, b.branch_name, d.department_name
    FROM employees e
    LEFT JOIN branches b ON e.branch_id = b.branch_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    WHERE e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)
    ORDER BY e.last_name, e.first_name
");
$employee_total    = (int) $employees->num_rows;
$employee_active   = (int) $conn->query("SELECT COUNT(*) as cnt FROM employees e WHERE e.is_active = 1 AND e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)")->fetch_assoc()['cnt'];
$employee_inactive = max(0, $employee_total - $employee_active);
$pending_count     = (int) $conn->query("SELECT COUNT(*) as c FROM employee_change_requests WHERE status='Pending'")->fetch_assoc()['c'];

// Filter dropdowns
$job_titles_res = $conn->query("
    SELECT jt.job_title_id, jt.job_title, d.department_id, d.department_name,
           (SELECT COUNT(*) FROM employees e WHERE e.job_title_id = jt.job_title_id) AS employee_count
    FROM job_titles jt
    LEFT JOIN departments d ON jt.department_id = d.department_id
    WHERE jt.job_title IS NOT NULL AND jt.job_title != ''
    ORDER BY d.department_name ASC, jt.job_title ASC
");
$job_titles_by_dept = [];
while ($r = $job_titles_res->fetch_assoc()) {
    $dept_name = $r['department_name'] ?? 'Unassigned';
    $job_titles_by_dept[$dept_name][] = $r;
}
$departments_res = $conn->query("SELECT department_name FROM departments ORDER BY department_name");
$departments = [];
while ($r = $departments_res->fetch_assoc()) $departments[] = $r['department_name'];

$branches_res = $conn->query("SELECT branch_name FROM branches ORDER BY branch_name");
$branches = [];
while ($r = $branches_res->fetch_assoc()) $branches[] = $r['branch_name'];

$statuses = ['OJT','Probationary','Project Based','Regular','Separated','Trainee','AWOL','Retirement','Death','Permanent or Total Disability','Resignation','Failed in Training','Termination for Cause'];
?>

<style>
    .job-badge { display:inline-block;padding:2px 9px;border-radius:12px;font-size:.72rem;font-weight:600;letter-spacing:.3px;white-space:nowrap; }
    .job-badge-executive   { background:#fff3cd;color:#856404;border:1px solid #ffc107; }
    .job-badge-mgmt-team   { background:#ede7f6;color:#5e35b1;border:1px solid #9c77e0; }
    .job-badge-manager     { background:#dbeafe;color:#1d4ed8;border:1px solid #60a5fa; }
    .job-badge-supervisor  { background:#ccfbf1;color:#0f766e;border:1px solid #2dd4bf; }
    .job-badge-rf          { background:#f0fdf4;color:#166534;border:1px solid #86efac; }
    .job-badge-default     { background:#f1f5f9;color:#475569;border:1px solid #cbd5e1; }
    .filter-toolbar { padding:16px 20px;background:linear-gradient(135deg,#f8f9fc,#f1f3f8);border-bottom:1px solid #e8ecf1;display:flex;flex-wrap:wrap;gap:12px;align-items:center; }
    .filter-group { position:relative;min-width:180px;flex:1; }
    .filter-group label { display:block;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#8094ae;margin-bottom:4px; }
    .filter-group select { width:100%;padding:8px 32px 8px 12px;border:1px solid #dce3ed;border-radius:8px;background:#fff;font-size:.85rem;font-weight:500;color:#344357;transition:all .2s;appearance:none;-webkit-appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%238094ae' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;cursor:pointer; }
    .filter-group select:focus { outline:none;border-color:var(--primary-blue);box-shadow:0 0 0 3px rgba(59,130,246,.1); }
    .filter-group select.active-filter { border-color:var(--primary-blue);background-color:#eef4ff;color:var(--primary-blue);font-weight:600; }
    .filter-summary { padding:8px 20px;background:#fff;border-bottom:1px solid #e8ecf1;display:none;align-items:center;gap:8px;flex-wrap:wrap; }
    .filter-summary.has-filters { display:flex; }
    .filter-chip { display:inline-flex;align-items:center;gap:6px;padding:3px 10px;background:#eef4ff;border:1px solid #d0dfff;border-radius:20px;font-size:.75rem;font-weight:600;color:var(--primary-blue);animation:chipIn .2s ease; }
    .filter-chip .chip-category { font-weight:400;color:#8094ae; }
    .filter-chip .remove-chip { cursor:pointer;opacity:.6;transition:opacity .15s;font-size:.65rem; }
    .filter-chip .remove-chip:hover { opacity:1; }
    .btn-clear-filters { font-size:.75rem;font-weight:600;color:#dc3545;background:none;border:none;padding:3px 8px;cursor:pointer;transition:all .15s;border-radius:6px; }
    .btn-clear-filters:hover { background:#fff5f5; }
    @keyframes chipIn { from { transform:scale(.85);opacity:0; } to { transform:scale(1);opacity:1; } }
    .pending-badge { display:inline-block;background:#fff3cd;color:#856404;border:1px solid #ffc107;border-radius:10px;padding:1px 7px;font-size:.65rem;font-weight:700;letter-spacing:.3px;vertical-align:middle;margin-left:4px; }
</style>

<div class="page-hero fadeup mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
        <div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">HR Staff · Employees</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-users me-2" style="color:#BD9414;"></i>All Employees</h4>
            <p class="text-white-50 small mb-0 mt-2">Find employee records and submit update requests for review through the appropriate approval process.</p>
        </div>
        <div style="color:rgba(255,255,255,.6);font-size:.8rem;">
            <i class="fas fa-sync-alt me-1"></i>Data as of <?php echo date('F d, Y'); ?>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div><div class="stat-value"><?php echo $employee_total; ?></div><div class="stat-label">Total Employees</div></div>
                    <i class="fas fa-id-badge stat-icon text-white-50"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div><div class="stat-value"><?php echo $employee_active; ?></div><div class="stat-label">Active</div></div>
                    <i class="fas fa-check-circle stat-icon" style="color:#28a745;"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div><div class="stat-value"><?php echo $employee_inactive; ?></div><div class="stat-label">Inactive</div></div>
                    <i class="fas fa-pause-circle stat-icon" style="color:#BD9414;"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div><div class="stat-value"><?php echo $pending_count; ?></div><div class="stat-label">Pending Changes</div></div>
                    <i class="fas fa-clock stat-icon" style="color:#ffc107;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($pending_count > 0): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 fadeup mb-3">
    <i class="fas fa-clock fa-lg"></i>
    <div>You have <strong><?php echo $pending_count; ?></strong> pending change request(s) awaiting HR Manager review.
        <span class="text-muted small">Changes are applied only after manager approval.</span>
    </div>
</div>
<?php endif; ?>

<div class="chart-card fadeup">
    <div class="cc-header">
        <h5 class="d-none d-md-block"><i class="fas fa-users me-2"></i>All Employees</h5>
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="search" class="form-control form-control-sm" id="customSearchEmp" placeholder="Search by name, employee ID, position, or department" aria-label="Search employees by name, employee ID, position, or department" title="Search by name, employee ID, position, or department">
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
                            <option value="<?php echo e($jt['job_title']); ?>">
                                <?php echo e($jt['job_title']); ?>
                                <?php if ($jt['employee_count'] > 0): ?>(<?php echo $jt['employee_count']; ?>)<?php endif; ?>
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
                    <option value="<?php echo e($br); ?>"><?php echo e($br); ?></option>
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

    <div class="filter-summary" id="filterSummary">
        <span class="filter-label"><i class="fas fa-filter me-1"></i>Filters:</span>
        <div id="filterChips"></div>
        <button class="btn-clear-filters" id="clearAllFilters"><i class="fas fa-times me-1"></i>Clear All</button>
    </div>

    <div class="cc-body p-0">
        <!-- Desktop Table -->
        <div class="table-responsive d-none d-md-block">
            <table class="table table-hover" id="empTable">
                <thead>
                    <tr>
                        <th style="width:50px;">#</th>
                        <th>Name</th>
                        <th>Job Title</th>
                        <th>Department</th>
                        <th>Branch</th>
                        <th>Status</th>
                        <th>Hire Date</th>
                        <th style="min-width:130px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $count = 1;
                    while ($emp = $employees->fetch_assoc()): 
                        $hasPending = isset($pending_eids[$emp['employee_id']]);
                    ?>
                        <tr data-jobtitle="<?php echo e($emp['job_title']); ?>"
                            data-department="<?php echo e($emp['department_name'] ?? 'N/A'); ?>"
                            data-branch="<?php echo e($emp['branch_name'] ?? 'N/A'); ?>"
                            data-status="<?php echo e($emp['employment_status']); ?>"
                            data-search="<?php echo e($emp['employee_id'] . ' ' . ($emp['employee_code'] ?? '') . ' ' . getEmployeeDisplayId($emp) . ' ' . $emp['first_name'] . ' ' . $emp['last_name'] . ' ' . $emp['last_name'] . ' ' . $emp['first_name'] . ' ' . ($emp['job_title'] ?? '') . ' ' . ($emp['department_name'] ?? '') . ' ' . ($emp['branch_name'] ?? '') . ' ' . ($emp['employment_status'] ?? '')); ?>"
                            data-active="<?php echo $emp['is_active'] ? '1' : '0'; ?>"
                            style="display:none;">
                            <td><strong><?php echo $count++; ?></strong></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img src="<?php echo getEmployeeAvatar($emp['profile_picture']); ?>" alt="Profile"
                                        class="rounded-circle" style="width:32px;height:32px;object-fit:cover;">
                                    <div>
                                        <strong><?php echo e($emp['last_name'] . ', ' . $emp['first_name']); ?></strong>
                                        <div class="text-muted small">ID: <span class="company-id-value"><?php echo e(getEmployeeDisplayId($emp)); ?></span></div>
                                        <?php if ($hasPending): ?>
                                            <span class="pending-badge"><i class="fas fa-clock me-1"></i>Pending</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php $rankId = (int)($emp['rank_category_id'] ?? 0); ?>
                                <span class="job-badge <?php echo staffGetJobTitleBadgeClass($rankId); ?>"><?php echo e($emp['job_title'] ?? 'N/A'); ?></span>
                            </td>
                            <td><?php echo e($emp['department_name'] ?? 'N/A'); ?></td>
                            <td><?php echo e($emp['branch_name'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="badge <?php echo $emp['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo $emp['employment_status']; ?>
                                </span>
                            </td>
                            <td><small><?php echo formatDate($emp['hire_date']); ?></small></td>
                            <td>
                                <div class="d-flex gap-1 align-items-center flex-nowrap">
                                    <a href="<?php echo BASE_URL; ?>/staff/view-employee.php?id=<?php echo $emp['employee_id']; ?>"
                                        class="btn btn-sm btn-outline-info" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($hasPending): ?>
                                        <button class="btn btn-sm btn-outline-warning" title="Edit pending — awaiting manager review" disabled>
                                            <i class="fas fa-clock"></i>
                                        </button>
                                    <?php else: ?>
                                        <a href="<?php echo BASE_URL; ?>/staff/edit-employee.php?id=<?php echo $emp['employee_id']; ?>"
                                            class="btn btn-sm btn-outline-primary" title="Submit Edit Request">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Card List -->
        <div class="mobile-list-view d-block d-md-none p-3">
            <?php
            $employees->data_seek(0); $mob_count = 0;
            while ($emp = $employees->fetch_assoc()):
                $hasPending = isset($pending_eids[$emp['employee_id']]);
                $mob_count++;
            ?>
            <div class="d-flex align-items-center gap-3 p-3 mb-2 rounded-3 border bg-white"
                 data-jobtitle="<?php echo e($emp['job_title']); ?>"
                 data-department="<?php echo e($emp['department_name'] ?? 'N/A'); ?>"
                 data-branch="<?php echo e($emp['branch_name'] ?? 'N/A'); ?>"
                 data-status="<?php echo e($emp['employment_status']); ?>"
                 data-search="<?php echo e($emp['employee_id'] . ' ' . ($emp['employee_code'] ?? '') . ' ' . getEmployeeDisplayId($emp) . ' ' . $emp['first_name'] . ' ' . $emp['last_name'] . ' ' . $emp['last_name'] . ' ' . $emp['first_name'] . ' ' . ($emp['job_title'] ?? '') . ' ' . ($emp['department_name'] ?? '') . ' ' . ($emp['branch_name'] ?? '') . ' ' . ($emp['employment_status'] ?? '')); ?>"
                 style="display:none;">
                <img src="<?php echo getEmployeeAvatar($emp['profile_picture']); ?>" alt="Profile"
                    class="rounded-circle flex-shrink-0" style="width:44px;height:44px;object-fit:cover;">
                <div class="flex-grow-1 min-width-0">
                    <div class="fw-bold">
                        <?php echo e($emp['last_name'] . ', ' . $emp['first_name']); ?>
                        <?php if ($hasPending): ?><span class="pending-badge"><i class="fas fa-clock me-1"></i>Pending</span><?php endif; ?>
                    </div>
                    <div class="text-muted small">ID: <span class="company-id-value"><?php echo e(getEmployeeDisplayId($emp)); ?></span></div>
                    <?php $rankId = (int)($emp['rank_category_id'] ?? 0); ?>
                    <span class="job-badge <?php echo staffGetJobTitleBadgeClass($rankId); ?>"><?php echo e($emp['job_title'] ?? 'N/A'); ?></span>
                    <div class="text-muted small mt-1"><?php echo e($emp['branch_name'] ?? 'N/A'); ?> · <?php echo e($emp['department_name'] ?? 'N/A'); ?></div>
                </div>
                <div class="d-flex flex-column gap-1 flex-shrink-0">
                    <span class="badge <?php echo $emp['is_active'] ? 'bg-success' : 'bg-danger'; ?> mb-1"><?php echo $emp['employment_status']; ?></span>
                    <div class="d-flex gap-1">
                        <a href="<?php echo BASE_URL; ?>/staff/view-employee.php?id=<?php echo $emp['employee_id']; ?>" class="btn btn-xs btn-outline-info"><i class="fas fa-eye"></i></a>
                        <?php if ($hasPending): ?>
                            <button class="btn btn-xs btn-outline-warning" disabled title="Edit pending"><i class="fas fa-clock"></i></button>
                        <?php else: ?>
                            <a href="<?php echo BASE_URL; ?>/staff/edit-employee.php?id=<?php echo $emp['employee_id']; ?>" class="btn btn-xs btn-outline-primary"><i class="fas fa-edit"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        <!-- Pagination Wrapper -->
        <div id="paginationWrapper" class="d-flex justify-content-between align-items-center px-4 py-3">
            <span id="pageInfo" class="text-muted small"></span>
            <div id="paginationControls" class="d-flex gap-2"></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput    = document.getElementById('customSearchEmp');
    const filterJobTitle = document.getElementById('filterJobTitle');
    const filterDept     = document.getElementById('filterDepartment');
    const filterBranch   = document.getElementById('filterBranch');
    const filterStatus   = document.getElementById('filterStatus');
    const filterSummary  = document.getElementById('filterSummary');
    const filterChips    = document.getElementById('filterChips');
    const clearAll       = document.getElementById('clearAllFilters');
    const tableRows      = document.querySelectorAll('#empTable tbody tr');
    const mobileCards    = document.querySelectorAll('.mobile-list-view > div');
    const pageInfo       = document.getElementById('pageInfo');
    const pageControls   = document.getElementById('paginationControls');
    const PAGE_SIZE      = 50;
    let currentPage      = 1;

    const urlParams = new URLSearchParams(window.location.search);
    const initialSearch = urlParams.get('search');
    if (initialSearch && !searchInput.value) {
        searchInput.value = initialSearch;
    }

    function getFilters() {
        return {
            search:   (searchInput.value || '').toLowerCase().trim(),
            jobtitle: filterJobTitle.value,
            dept:     filterDept.value,
            branch:   filterBranch.value,
            status:   filterStatus.value,
        };
    }

    function matchesFilters(el, f) {
        const text   = (el.dataset.search || el.textContent).toLowerCase();
        const jt     = el.dataset.jobtitle  || '';
        const dept   = el.dataset.department || '';
        const branch = el.dataset.branch     || '';
        const status = el.dataset.status     || '';
        if (f.search   && !text.includes(f.search))   return false;
        if (f.jobtitle && jt     !== f.jobtitle)       return false;
        if (f.dept     && dept   !== f.dept)           return false;
        if (f.branch   && branch !== f.branch)         return false;
        if (f.status   && status !== f.status)         return false;
        return true;
    }

    function applyFilters() {
        const f = getFilters();
        const matchedRows = [];
        tableRows.forEach(row => {
            const m = matchesFilters(row, f);
            if (m) matchedRows.push(row);
        });
        mobileCards.forEach(card => {
            card.style.display = matchesFilters(card, f) ? '' : 'none';
        });
        renderPage(matchedRows, currentPage);
        renderFilterChips(f);
    }

    function renderPage(matchedRows, page) {
        tableRows.forEach(r => r.style.display = 'none');
        const total = matchedRows.length;
        const pages = Math.max(1, Math.ceil(total / PAGE_SIZE));
        page = Math.min(page, pages);
        currentPage = page;
        const start = (page - 1) * PAGE_SIZE;
        matchedRows.slice(start, start + PAGE_SIZE).forEach(r => r.style.display = '');
        pageInfo.textContent = total > 0
            ? `Showing ${start + 1}–${Math.min(start + PAGE_SIZE, total)} of ${total} employee(s)`
            : 'No employees match the current filters.';
        renderPaginationControls(pages, page, matchedRows);
    }

    function renderPaginationControls(pages, page, matchedRows) {
        pageControls.innerHTML = '';
        if (pages <= 1) return;
        const mkBtn = (label, p, disabled = false, active = false) => {
            const b = document.createElement('button');
            b.className = 'btn btn-sm ' + (active ? 'btn-primary' : 'btn-outline-secondary');
            b.textContent = label;
            b.disabled = disabled;
            b.addEventListener('click', () => { currentPage = p; renderPage(matchedRows, p); });
            return b;
        };
        pageControls.appendChild(mkBtn('«', 1, page === 1));
        pageControls.appendChild(mkBtn('‹', page - 1, page === 1));
        for (let p = Math.max(1, page - 2); p <= Math.min(pages, page + 2); p++) {
            pageControls.appendChild(mkBtn(p, p, false, p === page));
        }
        pageControls.appendChild(mkBtn('›', page + 1, page === pages));
        pageControls.appendChild(mkBtn('»', pages, page === pages));
    }

    function renderFilterChips(f) {
        filterChips.innerHTML = '';
        const labels = { search: 'Search', jobtitle: 'Job Title', dept: 'Department', branch: 'Branch', status: 'Status' };
        let hasFilters = false;
        for (const [key, val] of Object.entries(f)) {
            if (!val) continue;
            hasFilters = true;
            const chip = document.createElement('span');
            chip.className = 'filter-chip';
            chip.innerHTML = `<span class="chip-category">${labels[key]}:</span>${val}<span class="remove-chip" data-key="${key}">✕</span>`;
            filterChips.appendChild(chip);
        }
        filterSummary.classList.toggle('has-filters', hasFilters);
    }

    [searchInput, filterJobTitle, filterDept, filterBranch, filterStatus].forEach(el => {
        el.addEventListener('input', () => { currentPage = 1; applyFilters(); });
    });
    filterChips.addEventListener('click', e => {
        const key = e.target.dataset.key;
        if (!key) return;
        if (key === 'search') searchInput.value = '';
        if (key === 'jobtitle') filterJobTitle.value = '';
        if (key === 'dept') filterDept.value = '';
        if (key === 'branch') filterBranch.value = '';
        if (key === 'status') filterStatus.value = '';
        currentPage = 1; applyFilters();
    });
    clearAll.addEventListener('click', () => {
        searchInput.value = '';
        filterJobTitle.value = ''; filterDept.value = '';
        filterBranch.value = '';  filterStatus.value = '';
        currentPage = 1; applyFilters();
    });
    applyFilters();
});
</script>

<?php require_once '../includes/footer.php'; ?>
