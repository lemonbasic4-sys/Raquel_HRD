<?php
$page_title = 'Career Progression';
require_once '../includes/session-check.php';
checkRole(['HR Supervisor']);
require_once '../includes/functions.php';

// ── Init & session vars ───────────────────────────────────────────────────────
$movement_ready    = ensureCareerProgressionMovements($conn);
$current_user_id   = (int) ($_SESSION['user_id'] ?? 0);
$sup_branch_id     = (int) ($_SESSION['branch_id'] ?? 0);

// ── Summary counts ────────────────────────────────────────────────────────────
$counts = ['total' => 0, 'Promotion' => 0, 'Transfer' => 0, 'Demotion' => 0, 'Role Change' => 0, 'Pending' => 0];

// ── Employees with their movement history ─────────────────────────────────────
$branch_filter = $sup_branch_id > 0 ? "AND e.branch_id = {$sup_branch_id}" : '';
$emp_sql = "
    SELECT e.employee_id, e.employee_code, e.first_name, e.last_name, e.department_id,
           e.job_title, e.hire_date, e.profile_picture,
           d.department_name, b.branch_name, rc.rank_name
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN branches b    ON e.branch_id = b.branch_id
    LEFT JOIN rank_categories rc ON e.rank_category_id = rc.rank_category_id
    WHERE e.is_active = 1 AND e.deleted_at IS NULL
      AND e.employee_id NOT IN (SELECT employee_id FROM users WHERE role='Admin' AND employee_id IS NOT NULL)
      {$branch_filter}
    ORDER BY e.last_name, e.first_name
";
$emp_result  = $conn->query($emp_sql);
$employees   = [];
while ($row = $emp_result->fetch_assoc()) {
    $employees[$row['employee_id']] = $row + ['movements' => [], 'eval_growth' => null];
}

// ── Career movements per employee ─────────────────────────────────────────────
if ($movement_ready && !empty($employees)) {
    $ids = implode(',', array_keys($employees));
    $mv_res = $conn->query("
        SELECT cm.*, pb.branch_name AS prev_branch, nb.branch_name AS new_branch,
               u.full_name AS logged_by_name
        FROM career_movements cm
        LEFT JOIN branches pb ON cm.previous_branch_id = pb.branch_id
        LEFT JOIN branches nb ON cm.new_branch_id = nb.branch_id
        LEFT JOIN users u ON cm.logged_by = u.user_id
        WHERE cm.employee_id IN ({$ids})
        ORDER BY cm.effective_date ASC, cm.created_at ASC
    ");
    while ($mv = $mv_res->fetch_assoc()) {
        $eid = (int)$mv['employee_id'];
        if (isset($employees[$eid])) {
            if ($mv['approval_status'] === 'Pending') $counts['Pending']++;
            if ($mv['approval_status'] === 'Approved' && (int)$mv['is_applied'] === 1) {
                $employees[$eid]['movements'][] = $mv;
                $counts['total']++;
                if (isset($counts[$mv['movement_type']])) $counts[$mv['movement_type']]++;
            }
        }
    }
}

// ── Latest eval career-growth data per employee ───────────────────────────────
if (!empty($employees)) {
    $ids = implode(',', array_keys($employees));
    $ev_res = $conn->query("
        SELECT ev.employee_id, ev.desired_position, ev.career_growth_suited,
               ev.career_growth_details, ev.current_position,
               ev.total_score, ev.performance_level,
               ev.evaluation_period_end
        FROM evaluations ev
        INNER JOIN (
            SELECT employee_id, MAX(evaluation_id) AS max_id
            FROM evaluations
            WHERE employee_id IN ({$ids}) AND status='Approved' AND deleted_at IS NULL
            GROUP BY employee_id
        ) latest ON ev.evaluation_id = latest.max_id
    ");
    while ($eg = $ev_res->fetch_assoc()) {
        $eid = (int)$eg['employee_id'];
        if (isset($employees[$eid])) {
            $employees[$eid]['eval_growth'] = $eg;
        }
    }
}

// ── Filters ───────────────────────────────────────────────────────────────────
$filter_type   = trim($_GET['type']   ?? '');
$filter_dept   = trim($_GET['dept']   ?? '');
$filter_ready  = trim($_GET['ready']  ?? '');
$search        = trim($_GET['search'] ?? '');

$departments = [];
$dept_res = $conn->query("SELECT department_id, department_name FROM departments WHERE is_active=1 ORDER BY department_name");
while ($d = $dept_res->fetch_assoc()) $departments[] = $d;

// ── Helper: tenure string ─────────────────────────────────────────────────────
function tenureText($hire_date): string {
    if (empty($hire_date)) return 'Unknown';
    $months = (int) floor((time() - strtotime($hire_date)) / (30.44 * 86400));
    if ($months < 1)  return 'Less than a month';
    if ($months < 12) return $months . ' month' . ($months === 1 ? '' : 's');
    $years = floor($months / 12); $rem = $months % 12;
    $out = $years . ' yr' . ($years === 1 ? '' : 's');
    if ($rem) $out .= ' ' . $rem . ' mo' . ($rem === 1 ? '' : 's');
    return $out;
}

// ── Helper: indication recorded in the latest approved evaluation ────────────
function careerIndicationBadge($employee): array {
    $eg = $employee['eval_growth'] ?? null;
    if (!$eg) return ['No approved evaluation', 'bg-secondary'];
    if (!empty($eg['career_growth_suited']) || trim((string)($eg['desired_position'] ?? '')) !== '') return ['Role fit suggested', 'bg-success'];
    return ['No role suggestion', 'bg-light text-dark border'];
}

require_once '../includes/header.php';
?>
<style>
/* ── Timeline ─────────────────────────────────────────────── */
.cp-timeline { position:relative; padding-left:28px; }
.cp-timeline::before { content:''; position:absolute; left:9px; top:0; bottom:0; width:2px; background:linear-gradient(to bottom,#BD9414,rgba(189,148,20,.15)); border-radius:2px; }
.cp-node { position:relative; margin-bottom:12px; }
.cp-node:last-child { margin-bottom:0; }
.cp-dot { position:absolute; left:-25px; top:3px; width:14px; height:14px; border-radius:50%; border:2px solid #fff; box-shadow:0 0 0 2px rgba(0,0,0,.1); }
.cp-dot.dot-hire      { background:#6c757d; }
.cp-dot.dot-Promotion { background:#198754; }
.cp-dot.dot-Transfer  { background:#0dcaf0; }
.cp-dot.dot-Demotion  { background:#dc3545; }
.cp-dot.dot-RoleChange{ background:#0d6efd; }
.cp-dot.dot-current   { background:#BD9414; }
/* ── Employee card ───────────────────────────────────────── */
.cp-avatar { width:42px; height:42px; border-radius:10px; object-fit:cover; flex-shrink:0; }
.cp-avatar-initials { width:42px; height:42px; border-radius:10px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:.95rem; color:#fff; background:var(--primary-blue,#294306); }
/* ── Stat pills ──────────────────────────────────────────── */
/* ── Aspires row — fixed single line ────────────────────── */
/* ── Notes block — fixed height ─────────────────────────── */
/* ── Desired position tag ────────────────────────────────── */
/* ── Filter bar ──────────────────────────────────────────── */
.cp-filter-bar { display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-bottom:18px; }
.cp-filter-bar select, .cp-filter-bar input { font-size:.85rem; }
/* ── Empty state ─────────────────────────────────────────── */
.cp-empty { padding:52px 20px; text-align:center; color:#adb5bd; }
.cp-empty i { display:block; font-size:3rem; margin-bottom:14px; opacity:.2; }
.career-progression-page .page-hero { padding: 1.5rem !important; }
.career-progression-page .page-hero .stat-card { min-height: 84px; }
.career-progression-page .cp-breakdown .cc-body { padding: .85rem 1.25rem; }
.career-progression-page .cp-breakdown-item { display:flex; align-items:center; gap:.75rem; padding:.6rem .85rem; border:1px solid #e2e9df; border-radius:9px; background:#fafcf9; }
.career-progression-page .cp-breakdown-item .cp-breakdown-count { margin-left:auto; font-size:1.1rem; font-weight:800; color:#18371a; }
.career-progression-page .cp-breakdown-item small { display:block; color:#6b786b; font-size:.7rem; }
.career-progression-page .cp-filter-bar { display:grid; grid-template-columns:minmax(170px,1.4fr) repeat(3,minmax(130px,1fr)) auto auto; gap:.6rem; margin-bottom:1rem; }
.career-progression-page .cp-filter-bar > * { width:100%; max-width:none !important; min-width:0; }
.career-progression-page .cp-filter-bar .btn { white-space:nowrap; }
.career-progression-page .cp-table-wrap { border:1px solid #e0e8dd; border-radius:10px; overflow:visible; }
.career-progression-page .cp-table { width:100%; table-layout:fixed; font-size:.78rem; }
.career-progression-page .cp-table thead th { padding:.75rem .65rem; background:#f2f6f0; color:#546754; font-size:.66rem; text-transform:uppercase; letter-spacing:.045em; border-bottom:1px solid #dce5d9; }
.career-progression-page .cp-table thead th:nth-child(1) { width:19%; }
.career-progression-page .cp-table thead th:nth-child(2) { width:18%; }
.career-progression-page .cp-table thead th:nth-child(3) { width:10%; }
.career-progression-page .cp-table thead th:nth-child(4) { width:12%; }
.career-progression-page .cp-table thead th:nth-child(5) { width:11%; }
.career-progression-page .cp-table thead th:nth-child(6) { width:17%; }
.career-progression-page .cp-table thead th:nth-child(7) { width:13%; }
.career-progression-page .cp-table td { padding:.75rem .65rem; vertical-align:middle; border-color:#e8ede6; overflow-wrap:anywhere; }
.career-progression-page .cp-table td strong { color:#193b1b; font-weight:700; }
.career-progression-page .cp-table td small { display:block; color:#687768; line-height:1.3; }
.career-progression-page .cp-employee-cell { display:flex; align-items:center; gap:.6rem; }
.career-progression-page .cp-avatar, .career-progression-page .cp-avatar-initials { width:34px; height:34px; border-radius:50%; font-size:.7rem; }
.career-progression-page .cp-score { display:flex; align-items:center; gap:.5rem; }
.career-progression-page .cp-score-track { height:5px; flex:1; min-width:24px; border-radius:5px; background:#e5ece3; overflow:hidden; }
.career-progression-page .cp-score-track span { display:block; height:100%; background:#2b8b47; }
.career-progression-page .cp-move-count { display:inline-block; padding:.2rem .45rem; border:1px solid #dce6d8; border-radius:6px; color:#435843; white-space:nowrap; }
.career-progression-page .cp-actions { display:flex; justify-content:flex-end; }
.career-progression-page .cp-actions .btn { width:34px; height:34px; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; }
.career-progression-page .cp-actions .dropdown-menu { min-width:180px; border-color:#dce6d8; box-shadow:0 10px 28px rgba(8,46,6,.14); }
.career-progression-page .cp-actions .dropdown-item { font-size:.8rem; padding:.5rem .8rem; }
.career-progression-page .cp-actions .dropdown-item i { width:18px; color:#476a48; }
.career-progression-page .cp-detail-row { background:#f8fbf7; }
.career-progression-page .cp-detail-content { padding:1rem 1.25rem; border-bottom:1px solid #dce6d8; }
.career-progression-page .cp-detail-grid { display:grid; grid-template-columns:1fr 1.5fr; gap:1.5rem; }
.career-progression-page .cp-detail-grid h6 { color:#244825; font-size:.78rem; font-weight:700; }
.career-progression-page .cp-table-footer { padding:.65rem 1rem; background:#f8faf7; color:#687768; font-size:.74rem; }
@media (max-width:1100px) {
    .career-progression-page .cp-filter-bar { grid-template-columns:repeat(3,minmax(0,1fr)); }
    .career-progression-page .cp-table { font-size:.72rem; }
}
@media (max-width:767.98px) {
    .career-progression-page .cp-filter-bar { grid-template-columns:1fr 1fr; }
    .career-progression-page .cp-filter-bar .input-group { grid-column:1 / -1; }
    .career-progression-page .cp-table { min-width:0; }
    .career-progression-page .cp-table thead { display:none; }
    .career-progression-page .cp-table, .career-progression-page .cp-table tbody, .career-progression-page .cp-table tr, .career-progression-page .cp-table td { display:block; width:100%; }
    .career-progression-page .cp-table tr.cp-employee-row { padding:.75rem; border-bottom:1px solid #dce6d8; }
    .career-progression-page .cp-table tr.cp-employee-row td { display:flex; justify-content:space-between; gap:1rem; padding:.45rem .25rem; border:0; text-align:right; }
    .career-progression-page .cp-table tr.cp-employee-row td::before { content:attr(data-label); color:#647464; font-size:.7rem; font-weight:700; text-align:left; }
    .career-progression-page .cp-table tr.cp-employee-row td:first-child { display:block; text-align:left; }
    .career-progression-page .cp-table tr.cp-employee-row td:first-child::before { display:none; }
    .career-progression-page .cp-table tr.cp-employee-row td:last-child { display:block; text-align:left; }
    .career-progression-page .cp-table tr.cp-employee-row td:last-child::before { display:none; }
    .career-progression-page .cp-actions { justify-content:flex-start; }
    .career-progression-page .cp-detail-grid { grid-template-columns:1fr; }
}
</style>

<div class="career-progression-page">
<div class="page-hero fadeup">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
        <div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">HR Supervisor &middot; Career</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-chart-line me-2" style="color:#BD9414;"></i>Career Progression</h4>
            <p class="text-white-50 small mb-0 mt-2">Applied career movements and career-growth indications from approved evaluations.</p>
        </div>
        <a href="career-movements.php" class="btn btn-sm px-3 fw-semibold" style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.25);border-radius:20px;font-size:.78rem;backdrop-filter:blur(4px);">
            <i class="fas fa-route me-1"></i>Manage Movements
        </a>
    </div>
    <div class="row g-3">
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-value"><?php echo number_format($counts['total']); ?></div><div class="stat-label">Applied Movements</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-value" style="color:#ffc107;"><?php echo number_format($counts['Pending']); ?></div><div class="stat-label">Pending Requests</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-value" style="color:#28a745;"><?php echo number_format($counts['Promotion']); ?></div><div class="stat-label">Promotions</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-value" style="color:#0dcaf0;"><?php echo number_format($counts['Transfer']); ?></div><div class="stat-label">Transfers</div></div></div>
    </div>
</div>

<!-- Type breakdown mini-chart card -->
<div class="chart-card cp-breakdown fadeup mt-4">
    <div class="cc-header"><h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Movement Breakdown</h5></div>
    <div class="cc-body">
        <div class="row g-2">
            <?php
            $breakdown = [
                ['Promotion',  $counts['Promotion'],  'bg-success', 'fas fa-arrow-trend-up'],
                ['Transfer',   $counts['Transfer'],   'bg-info text-dark', 'fas fa-arrows-left-right'],
                ['Demotion',   $counts['Demotion'],   'bg-danger',  'fas fa-arrow-trend-down'],
                ['Role Change',$counts['Role Change'],'bg-primary', 'fas fa-user-pen'],
            ];
            foreach ($breakdown as [$label, $val, $cls, $ico]):
            ?>
            <div class="col-6 col-md-3">
                <div class="cp-breakdown-item h-100">
                    <span class="badge <?php echo $cls; ?>"><i class="<?php echo $ico; ?> me-1"></i><?php echo $label; ?></span>
                    <span class="cp-breakdown-count"><?php echo number_format($val); ?><small><?php echo $counts['total'] > 0 ? round($val / $counts['total'] * 100) : 0; ?>% of applied movements</small></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="chart-card allow-overflow fadeup mt-4">
    <div class="cc-header d-flex flex-wrap align-items-center justify-content-between gap-3">
        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Employee Career Roadmaps</h5>
        <span class="badge bg-secondary-subtle text-secondary border"><?php echo count($employees); ?> employee<?php echo count($employees) === 1 ? '' : 's'; ?></span>
    </div>
    <div class="cc-body">

        <!-- Filter bar -->
        <form method="GET" class="cp-filter-bar" id="cpFilterForm">
            <div class="input-group" style="max-width:220px;">
                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted" style="font-size:.8rem;"></i></span>
                <input type="text" name="search" class="form-control border-start-0" placeholder="Search employee…" value="<?php echo e($search); ?>" style="font-size:.85rem;">
            </div>
            <select name="type" class="form-select" style="max-width:160px;">
                <option value="">All Types</option>
                <?php foreach (['Promotion','Transfer','Demotion','Role Change'] as $t): ?>
                    <option value="<?php echo $t; ?>" <?php echo $filter_type === $t ? 'selected' : ''; ?>><?php echo $t; ?></option>
                <?php endforeach; ?>
            </select>
            <select name="dept" class="form-select" style="max-width:200px;">
                <option value="">All Departments</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?php echo (int)$d['department_id']; ?>" <?php echo $filter_dept === (string)$d['department_id'] ? 'selected' : ''; ?>><?php echo e($d['department_name']); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="ready" class="form-select" style="max-width:160px;">
                <option value="">All Career Indications</option>
                <?php foreach (['Role fit suggested','No role suggestion','No approved evaluation'] as $r): ?>
                    <option value="<?php echo $r; ?>" <?php echo $filter_ready === $r ? 'selected' : ''; ?>><?php echo $r; ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm px-3"><i class="fas fa-filter me-1"></i>Filter</button>
            <?php if ($search || $filter_type || $filter_dept || $filter_ready): ?>
                <a href="career-progression.php" class="btn btn-outline-secondary btn-sm px-3"><i class="fas fa-times me-1"></i>Clear</a>
            <?php endif; ?>
        </form>

        <?php
        // Apply PHP-side filters (search, type, dept, readiness)
        $visible = [];
        foreach ($employees as $emp) {
            // Search
            if ($search) {
                $haystack = strtolower($emp['first_name'] . ' ' . $emp['last_name'] . ' ' . ($emp['job_title'] ?? '') . ' ' . ($emp['employee_code'] ?? ''));
                if (strpos($haystack, strtolower($search)) === false) continue;
            }
            // Type filter — employee must have at least one movement of this type
            if ($filter_type) {
                $has = false;
                foreach ($emp['movements'] as $mv) { if ($mv['movement_type'] === $filter_type) { $has = true; break; } }
                if (!$has) continue;
            }
            // Dept filter
            if ($filter_dept && (string)$emp['department_id'] !== $filter_dept) continue;
            // Career indication filter
            if ($filter_ready) {
                [$badge_label] = careerIndicationBadge($emp);
                if ($badge_label !== $filter_ready) continue;
            }
            $visible[] = $emp;
        }
        ?>

        <?php if (empty($visible)): ?>
            <div class="cp-empty">
                <i class="fas fa-chart-line"></i>
                <p class="mb-2 fw-semibold">No employees match your filters.</p>
                <a href="career-progression.php" class="btn btn-sm btn-outline-secondary px-4">Clear Filters</a>
            </div>
        <?php else: ?>
        <div class="cp-table-wrap">
            <table class="table cp-table mb-0">
                <thead><tr>
                    <th>Employee</th><th>Department / Location</th><th>Tenure</th><th>Evaluation</th><th>Applied Moves</th><th>Suggested Role</th><th class="text-end">Actions</th>
                </tr></thead>
                <tbody>
                <?php foreach ($visible as $emp):
                    $eid = (int)$emp['employee_id'];
                    $movements = $emp['movements'];
                    $eg = $emp['eval_growth'];
                    $name_parts = preg_split('/\s+/', trim($emp['first_name'] . ' ' . $emp['last_name']));
                    $initials = strtoupper(substr($name_parts[0] ?? 'U', 0, 1) . substr($name_parts[1] ?? '', 0, 1));
                    $collapseId = 'cpTimeline' . $eid;
                    $score = ($eg && $eg['total_score'] !== null) ? (float)$eg['total_score'] : null;
                    $desired = trim((string)($eg['desired_position'] ?? ''));
                ?>
                <tr class="cp-employee-row">
                    <td data-label="Employee"><div class="cp-employee-cell">
                        <?php if (!empty($emp['profile_picture'])): ?>
                            <img src="<?php echo e(getEmployeeAvatar($emp['profile_picture'])); ?>" alt="" class="cp-avatar">
                        <?php else: ?><span class="cp-avatar-initials"><?php echo e($initials); ?></span><?php endif; ?>
                        <span><strong><?php echo e($emp['last_name'] . ', ' . $emp['first_name']); ?></strong><small><?php echo e($emp['job_title'] ?: 'No position'); ?></small></span>
                    </div></td>
                    <td data-label="Department / Location"><strong><?php echo e($emp['department_name'] ?: 'No department'); ?></strong><small><?php echo e($emp['branch_name'] ?: 'No branch'); ?></small></td>
                    <td data-label="Tenure"><?php echo e(tenureText($emp['hire_date'] ?? '')); ?></td>
                    <td data-label="Evaluation">
                        <?php if ($score !== null): ?><div class="cp-score"><strong><?php echo number_format($score, 2); ?></strong><span class="cp-score-track"><span style="width:<?php echo min(100, max(0, $score / 4 * 100)); ?>%"></span></span></div>
                        <?php else: ?><span class="text-muted">No approved score</span><?php endif; ?>
                    </td>
                    <td data-label="Applied Moves"><span class="cp-move-count"><?php echo count($movements); ?> applied</span></td>
                    <td data-label="Suggested Role"><?php if ($desired !== ''): ?><strong><?php echo e($desired); ?></strong><?php else: ?><span class="text-muted fst-italic"><?php echo $eg ? 'No target role recorded' : 'No approved evaluation'; ?></span><?php endif; ?></td>
                    <td data-label="Actions"><div class="cp-actions dropdown">
                        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions for <?php echo e($emp['first_name'] . ' ' . $emp['last_name']); ?>"><i class="fas fa-ellipsis-v"></i></button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="career-movements.php?new_movement=1&amp;emp_id=<?php echo $eid; ?>"><i class="fas fa-plus"></i>Add Movement</a></li>
                            <li><a class="dropdown-item" href="view-employee.php?id=<?php echo $eid; ?>"><i class="fas fa-user"></i>View Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><button class="dropdown-item" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" aria-expanded="false" aria-controls="<?php echo $collapseId; ?>"><i class="fas fa-history"></i>View Timeline</button></li>
                        </ul>
                    </div></td>
                </tr>
                <tr class="cp-detail-row"><td colspan="7" class="p-0 border-0"><div class="collapse" id="<?php echo $collapseId; ?>"><div class="cp-detail-content">
                    <div class="cp-detail-grid"><div><h6>Career indication</h6><?php [$indication, $indicationClass] = careerIndicationBadge($emp); ?><span class="badge <?php echo $indicationClass; ?>"><?php echo e($indication); ?></span>
                        <?php if (!empty($eg['career_growth_details'])): ?><p class="mb-0 mt-2 small"><?php echo nl2br(e($eg['career_growth_details'])); ?></p><?php endif; ?>
                    </div><div><h6>Movement timeline</h6><div class="cp-timeline">
                        <div class="cp-node"><div class="cp-dot dot-hire"></div><strong>Hired</strong> <span class="text-muted small"><?php echo formatDate($emp['hire_date'] ?? ''); ?></span></div>
                        <?php foreach ($movements as $mv): ?><div class="cp-node"><div class="cp-dot dot-<?php echo e(str_replace(' ', '', $mv['movement_type'])); ?>"></div><strong><?php echo e($mv['movement_type']); ?></strong> <span class="text-muted small"><?php echo formatDate($mv['effective_date']); ?></span>
                            <div class="small"><?php echo e($mv['previous_position'] ?: '—'); ?> → <?php echo e($mv['new_position'] ?: '—'); ?></div>
                            <?php if (!empty($mv['new_department_name']) && $mv['new_department_name'] !== $mv['previous_department_name']): ?><div class="small text-muted">Department: <?php echo e($mv['previous_department_name'] ?: '—'); ?> → <?php echo e($mv['new_department_name']); ?></div><?php endif; ?>
                            <?php if (!empty($mv['new_branch'])): ?><div class="small text-muted">Branch: <?php echo e($mv['prev_branch'] ?: '—'); ?> → <?php echo e($mv['new_branch']); ?></div><?php endif; ?>
                        </div><?php endforeach; ?>
                        <?php if (!$movements): ?><div class="cp-node"><div class="cp-dot dot-current"></div><span class="text-muted small">No applied movements recorded.</span></div><?php endif; ?>
                    </div></div></div>
                </div></div></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="cp-table-footer">Showing <?php echo count($visible); ?> of <?php echo count($employees); ?> employees</div>
        </div>
        <?php endif; ?>

    </div><!-- /.cc-body -->
</div><!-- /.chart-card -->

<script>
// Rotate chevron on collapse toggle
document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(btn => {
    const target = btn.getAttribute('data-bs-target');
    const el = document.querySelector(target);
    if (!el) return;
    el.addEventListener('show.bs.collapse', () => { const chev = btn.querySelector('.fa-chevron-down'); if (chev) chev.style.transform = 'rotate(180deg)'; });
    el.addEventListener('hide.bs.collapse', () => { const chev = btn.querySelector('.fa-chevron-down'); if (chev) chev.style.transform = 'rotate(0deg)'; });
});
</script>

</div>
<?php require_once '../includes/footer.php'; ?>
