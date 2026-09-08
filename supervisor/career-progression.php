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
$counts = ['total' => 0, 'Promotion' => 0, 'Transfer' => 0, 'Demotion' => 0, 'Role Change' => 0, 'Pending' => 0, 'Approved' => 0];
if ($movement_ready) {
    $cnt_res = $conn->query("SELECT movement_type, approval_status, COUNT(*) AS c FROM career_movements GROUP BY movement_type, approval_status");
    while ($r = $cnt_res->fetch_assoc()) {
        $counts['total'] += (int)$r['c'];
        if (isset($counts[$r['movement_type']])) $counts[$r['movement_type']] += (int)$r['c'];
        if (isset($counts[$r['approval_status']])) $counts[$r['approval_status']] += (int)$r['c'];
    }
}

// ── Employees with their movement history ─────────────────────────────────────
$branch_filter = $sup_branch_id > 0 ? "AND e.branch_id = {$sup_branch_id}" : '';
$emp_sql = "
    SELECT e.employee_id, e.employee_code, e.first_name, e.last_name,
           e.job_title, e.hire_date, e.profile_picture,
           d.department_name, b.branch_name, rc.rank_name
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN branches b    ON e.branch_id = b.branch_id
    LEFT JOIN rank_categories rc ON e.rank_category_id = rc.rank_category_id
    WHERE e.is_active = 1
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
            $employees[$eid]['movements'][] = $mv;
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
            WHERE employee_id IN ({$ids}) AND status='Approved'
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

// ── Helper: readiness badge ───────────────────────────────────────────────────
function readinessBadge($employee): array {
    $months = (int) floor((time() - strtotime($employee['hire_date'] ?? '')) / (30.44 * 86400));
    $mvCount = count($employee['movements'] ?? []);
    $eg = $employee['eval_growth'] ?? null;
    $suited = $eg ? (int)($eg['career_growth_suited'] ?? 0) : 0;
    $score  = $eg ? (float)($eg['total_score'] ?? 0) : 0;
    if ($suited && $score >= 2.6 && $months >= 12) return ['Ready', 'bg-success'];
    if ($months >= 6 && $score >= 2.0)             return ['Developing', 'bg-info text-dark'];
    if ($months < 6)                               return ['New Hire', 'bg-secondary'];
    return ['Monitoring', 'bg-warning text-dark'];
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
.cp-card { border:1px solid #e8ecf0; border-radius:14px; overflow:hidden; transition:box-shadow .2s; }
.cp-card:hover { box-shadow:0 4px 20px rgba(0,0,0,.08); }
.cp-card-header { padding:14px 16px; background:linear-gradient(135deg,#f8faf5 0%,#f0f4eb 100%); border-bottom:1px solid #e8ecf0; display:flex; gap:12px; align-items:flex-start; }
.cp-avatar { width:42px; height:42px; border-radius:10px; object-fit:cover; flex-shrink:0; }
.cp-avatar-initials { width:42px; height:42px; border-radius:10px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:.95rem; color:#fff; background:var(--primary-blue,#294306); }
.cp-card-body { padding:16px; }
/* ── Stat pills ──────────────────────────────────────────── */
.cp-stat-pill { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:20px; font-size:.74rem; font-weight:600; background:#f1f3f5; color:#495057; white-space:nowrap; }
.cp-stat-pill i { font-size:.78rem; color:#adb5bd; }
/* ── Aspires row — fixed single line ────────────────────── */
.cp-aspires { display:flex; align-items:center; gap:6px; flex-wrap:nowrap; overflow:hidden; min-height:26px; }
.cp-aspires-label { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#adb5bd; white-space:nowrap; flex-shrink:0; }
/* ── Notes block — fixed height ─────────────────────────── */
.cp-notes { background:#f8faf5; border:1px solid #e8ecf0; border-radius:8px; padding:8px 10px; min-height:58px; max-height:58px; overflow:hidden; position:relative; }
.cp-notes-label { font-size:.67rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#adb5bd; margin-bottom:3px; }
.cp-notes-text { font-size:.76rem; color:#495057; line-height:1.4; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; }
.cp-notes-empty { font-size:.76rem; color:#adb5bd; font-style:italic; line-height:1.4; padding-top:4px; }
/* ── Desired position tag ────────────────────────────────── */
.desired-tag { background:linear-gradient(135deg,#fff7e0,#fff0b3); border:1px solid #ffe066; color:#856404; font-size:.72rem; font-weight:600; padding:2px 8px; border-radius:10px; display:inline-block; max-width:160px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; vertical-align:middle; flex-shrink:0; }
/* ── Filter bar ──────────────────────────────────────────── */
.cp-filter-bar { display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-bottom:18px; }
.cp-filter-bar select, .cp-filter-bar input { font-size:.85rem; }
/* ── Empty state ─────────────────────────────────────────── */
.cp-empty { padding:52px 20px; text-align:center; color:#adb5bd; }
.cp-empty i { display:block; font-size:3rem; margin-bottom:14px; opacity:.2; }
</style>

<div class="page-hero fadeup">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
        <div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">HR Supervisor &middot; Career</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-chart-line me-2" style="color:#BD9414;"></i>Career Progression</h4>
            <p class="text-white-50 small mb-0 mt-2">Employee growth roadmaps, movement histories, and career readiness across your workforce.</p>
        </div>
        <a href="career-movements.php" class="btn btn-sm px-3 fw-semibold" style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.25);border-radius:20px;font-size:.78rem;backdrop-filter:blur(4px);">
            <i class="fas fa-route me-1"></i>Manage Movements
        </a>
    </div>
    <div class="row g-3">
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-value"><?php echo number_format($counts['total']); ?></div><div class="stat-label">Total Movements</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-value" style="color:#ffc107;"><?php echo number_format($counts['Pending']); ?></div><div class="stat-label">Pending</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-value" style="color:#28a745;"><?php echo number_format($counts['Promotion']); ?></div><div class="stat-label">Promotions</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-value" style="color:#0dcaf0;"><?php echo number_format($counts['Transfer']); ?></div><div class="stat-label">Transfers</div></div></div>
    </div>
</div>

<div class="chart-card fadeup">
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
                <option value="">All Readiness</option>
                <?php foreach (['Ready','Developing','Monitoring','New Hire'] as $r): ?>
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
            if ($filter_dept) {
                // department_id on employee row
                // We re-query or match via departments array — get dept id from name
                // Already stored department_name; get id by matching
                $matched_dept_name = '';
                foreach ($departments as $d) { if ((string)$d['department_id'] === $filter_dept) { $matched_dept_name = $d['department_name']; break; } }
                if ($emp['department_name'] !== $matched_dept_name) continue;
            }
            // Readiness filter
            if ($filter_ready) {
                [$badge_label] = readinessBadge($emp);
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
        <div class="row g-3" id="cpGrid">
            <?php foreach ($visible as $emp):
                $eid        = (int)$emp['employee_id'];
                $movements  = $emp['movements'];
                $eg         = $emp['eval_growth'];
                [$ready_label, $ready_class] = readinessBadge($emp);
                $name_parts = preg_split('/\s+/', trim($emp['first_name'] . ' ' . $emp['last_name']));
                $initials   = strtoupper(substr($name_parts[0] ?? 'U', 0, 1) . substr($name_parts[1] ?? '', 0, 1));
                $collapseId = 'cpTimeline' . $eid;

                // Normalise eval data so every card renders the same fields
                $score_txt      = ($eg && !empty($eg['total_score']))    ? number_format((float)$eg['total_score'], 2) . ' / 4.0' : '—';
                $desired_txt    = ($eg && !empty($eg['desired_position'])) ? $eg['desired_position'] : null;
                $endorsed       = ($eg && (int)($eg['career_growth_suited'] ?? 0) === 1);
                $growth_notes   = ($eg && !empty($eg['career_growth_details'])) ? mb_substr($eg['career_growth_details'], 0, 140) . (mb_strlen($eg['career_growth_details']) > 140 ? '…' : '') : null;
            ?>
            <div class="col-md-6 col-xl-4">
                <div class="cp-card d-flex flex-column h-100">

                    <!-- ── Header ── -->
                    <div class="cp-card-header">
                        <?php if (!empty($emp['profile_picture'])): ?>
                            <img src="<?php echo e(getEmployeeAvatar($emp['profile_picture'])); ?>" alt="" class="cp-avatar">
                        <?php else: ?>
                            <div class="cp-avatar-initials"><?php echo e($initials); ?></div>
                        <?php endif; ?>
                        <div class="flex-grow-1" style="min-width:0;">
                            <div class="fw-bold text-truncate" style="font-size:.93rem;"><?php echo e($emp['last_name'] . ', ' . $emp['first_name']); ?></div>
                            <div class="text-muted text-truncate" style="font-size:.78rem;"><?php echo e($emp['job_title'] ?? 'No Position'); ?></div>
                            <div class="d-flex flex-wrap gap-1 mt-1">
                                <?php if (!empty($emp['department_name'])): ?>
                                    <span class="badge bg-light text-dark border" style="font-size:.65rem;"><?php echo e($emp['department_name']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($emp['branch_name'])): ?>
                                    <span class="badge bg-light text-dark border" style="font-size:.65rem;"><i class="fas fa-map-marker-alt me-1" style="color:#BD9414;"></i><?php echo e($emp['branch_name']); ?></span>
                                <?php endif; ?>
                                <span class="badge <?php echo $ready_class; ?>" style="font-size:.65rem;"><?php echo $ready_label; ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- ── Body (flex-grow fills remaining height) ── -->
                    <div class="cp-card-body d-flex flex-column flex-grow-1">

                        <!-- Stats row — always 3 pills -->
                        <div class="d-flex gap-2 mb-3" style="flex-wrap:nowrap;overflow:hidden;">
                            <span class="cp-stat-pill flex-shrink-0"><i class="fas fa-calendar-alt"></i><?php echo e(tenureText($emp['hire_date'] ?? '')); ?></span>
                            <span class="cp-stat-pill flex-shrink-0"><i class="fas fa-route"></i><?php echo count($movements); ?> move<?php echo count($movements) === 1 ? '' : 's'; ?></span>
                            <span class="cp-stat-pill flex-shrink-0"><i class="fas fa-star"></i><?php echo $score_txt; ?></span>
                        </div>

                        <!-- Aspires-to row — always present, placeholder when empty -->
                        <div class="cp-aspires mb-3">
                            <span class="cp-aspires-label">Aspires to</span>
                            <?php if ($desired_txt): ?>
                                <span class="desired-tag" title="<?php echo e($desired_txt); ?>"><i class="fas fa-arrow-up me-1"></i><?php echo e($desired_txt); ?></span>
                                <?php if ($endorsed): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:.65rem;"><i class="fas fa-check me-1"></i>Endorsed</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted" style="font-size:.78rem;font-style:italic;">No eval data yet</span>
                            <?php endif; ?>
                        </div>

                        <!-- Growth notes — fixed height, always present -->
                        <div class="cp-notes mb-3">
                            <?php if ($growth_notes): ?>
                                <div class="cp-notes-label">HR Notes</div>
                                <div class="cp-notes-text"><?php echo nl2br(e($growth_notes)); ?></div>
                            <?php else: ?>
                                <div class="cp-notes-empty"><i class="fas fa-sticky-note me-1"></i>No growth notes on record</div>
                            <?php endif; ?>
                        </div>

                        <!-- Timeline — always same height toggle button -->
                        <div class="mb-3">
                            <button class="btn btn-sm btn-outline-secondary w-100" style="font-size:.78rem;border-radius:8px;"
                                data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" aria-expanded="false">
                                <i class="fas fa-project-diagram me-1"></i>
                                <?php echo count($movements) > 0 ? count($movements) . ' movement' . (count($movements) === 1 ? '' : 's') . ' — view timeline' : 'No movements yet'; ?>
                                <i class="fas fa-chevron-down ms-1" style="font-size:.68rem;transition:transform .2s;"></i>
                            </button>
                            <div class="collapse" id="<?php echo $collapseId; ?>">
                                <div class="cp-timeline mt-2">
                                    <!-- Hire -->
                                    <div class="cp-node">
                                        <div class="cp-dot dot-hire"></div>
                                        <div>
                                            <div class="fw-semibold" style="font-size:.8rem;">Hired</div>
                                            <div class="text-muted" style="font-size:.73rem;"><?php echo formatDate($emp['hire_date'] ?? ''); ?></div>
                                        </div>
                                    </div>
                                    <?php if (empty($movements)): ?>
                                        <div class="cp-node">
                                            <div class="cp-dot dot-current"></div>
                                            <div style="font-size:.78rem;color:#6c757d;font-style:italic;">No movements recorded.</div>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($movements as $mv):
                                            $dotClass  = 'dot-' . str_replace(' ', '', $mv['movement_type']);
                                            $isApplied = (int)($mv['is_applied'] ?? 0) === 1;
                                            $isPending = $mv['approval_status'] === 'Pending';
                                        ?>
                                        <div class="cp-node">
                                            <div class="cp-dot <?php echo $dotClass; ?>" <?php echo $isPending ? 'style="opacity:.5;"' : ''; ?>></div>
                                            <div>
                                                <div class="d-flex flex-wrap align-items-center gap-1">
                                                    <span class="fw-semibold" style="font-size:.8rem;"><?php echo e($mv['movement_type']); ?></span>
                                                    <?php if ($isPending): ?><span class="badge bg-warning text-dark" style="font-size:.62rem;">Pending</span>
                                                    <?php elseif ($mv['approval_status'] === 'Rejected'): ?><span class="badge bg-danger" style="font-size:.62rem;">Rejected</span>
                                                    <?php elseif ($isApplied): ?><span class="badge bg-success" style="font-size:.62rem;">Applied</span>
                                                    <?php else: ?><span class="badge bg-secondary" style="font-size:.62rem;">Scheduled</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div style="font-size:.73rem;color:#495057;">
                                                    <?php if (!empty($mv['new_position'])): ?>
                                                        <?php echo e($mv['previous_position'] ?: '—'); ?> <i class="fas fa-arrow-right mx-1" style="color:#BD9414;font-size:.68rem;"></i><?php echo e($mv['new_position']); ?>
                                                    <?php endif; ?>
                                                    <?php if (!empty($mv['new_branch'])): ?>
                                                        <div class="text-muted"><?php echo e($mv['prev_branch'] ?? '—'); ?> → <?php echo e($mv['new_branch']); ?></div>
                                                    <?php endif; ?>
                                                    <div class="text-muted"><?php echo formatDate($mv['effective_date']); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                        <!-- Current -->
                                        <div class="cp-node">
                                            <div class="cp-dot dot-current"></div>
                                            <div>
                                                <div class="fw-semibold" style="font-size:.8rem;color:#BD9414;">Current</div>
                                                <div class="text-muted" style="font-size:.73rem;"><?php echo e($emp['job_title'] ?? 'No position'); ?></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Actions — always pinned at bottom -->
                        <div class="mt-auto pt-2 border-top d-flex gap-2">
                            <a href="career-movements.php?new_movement=1&emp_id=<?php echo $eid; ?>" class="btn btn-sm btn-primary flex-grow-1" style="font-size:.78rem;border-radius:8px;">
                                <i class="fas fa-plus me-1"></i>Add Movement
                            </a>
                            <a href="view-employee.php?id=<?php echo $eid; ?>" class="btn btn-sm btn-outline-secondary" style="font-size:.78rem;border-radius:8px;" title="View Profile">
                                <i class="fas fa-user"></i>
                            </a>
                        </div>

                    </div><!-- /.cp-card-body -->
                </div><!-- /.cp-card -->
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div><!-- /.cc-body -->
</div><!-- /.chart-card -->

<!-- Type breakdown mini-chart card -->
<div class="chart-card fadeup mt-4">
    <div class="cc-header"><h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Movement Breakdown</h5></div>
    <div class="cc-body">
        <div class="row g-3 text-center">
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
                <div class="p-3 rounded-3 border h-100" style="background:#fafafa;">
                    <div class="mb-2"><span class="badge <?php echo $cls; ?> px-3 py-2" style="font-size:.8rem;"><i class="<?php echo $ico; ?> me-1"></i><?php echo $label; ?></span></div>
                    <div style="font-size:1.8rem;font-weight:800;color:#212529;"><?php echo number_format($val); ?></div>
                    <div class="text-muted" style="font-size:.75rem;"><?php echo $counts['total'] > 0 ? round($val / $counts['total'] * 100) : 0; ?>% of total</div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

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

<?php require_once '../includes/footer.php'; ?>
