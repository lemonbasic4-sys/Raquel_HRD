<?php
/**
 * Employee Portal - Team List
 * Allows Branch Supervisors and Branch Managers to view members of their branch.
 */
$page_title = 'My Team';
require_once '../includes/session-check.php';
checkRole(['Employee']);
require_once '../includes/functions.php';

$user_id     = (int)($_SESSION['user_id']     ?? 0);
$employee_id = (int)($_SESSION['employee_id'] ?? 0);

// Human Resource personnel manage teams and evaluations directly on HRIS
$emp_dept_stmt = $conn->prepare("SELECT d.department_name FROM employees e LEFT JOIN departments d ON e.department_id = d.department_id WHERE e.employee_id = ? LIMIT 1");
$emp_dept_stmt->bind_param("i", $employee_id);
$emp_dept_stmt->execute();
$emp_dept_name = $emp_dept_stmt->get_result()->fetch_assoc()['department_name'] ?? '';
$emp_dept_stmt->close();

// Use the employee's current department. A former HRIS role may remain on a
// held account for audit/history after a Career Movement and must not block
// the employee's active Employee Portal access.
if (strcasecmp($emp_dept_name, 'Human Resources') === 0) {
    redirectWith(BASE_URL . '/employee/dashboard.php', 'info', 'Human Resource personnel manage team evaluation features directly on the HRIS portal.');
}

// Only supervisors / managers may access this page
if (!hasSupervisorPrivileges($conn, $employee_id)) {
    redirectWith(BASE_URL . '/employee/dashboard.php', 'danger', 'Access Denied: You do not have supervisor or manager privileges.');
}

// ── Current user's employee record ────────────────────────────────────────
$me_stmt = $conn->prepare("
    SELECT e.employee_id, e.first_name, e.last_name, e.job_title,
           e.branch_id, e.department_id, e.rank_category_id,
           b.branch_name, d.department_name
    FROM employees e
    LEFT JOIN branches   b ON e.branch_id     = b.branch_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    WHERE e.employee_id = ? LIMIT 1
");
$me_stmt->bind_param("i", $employee_id);
$me_stmt->execute();
$me = $me_stmt->get_result()->fetch_assoc();
$me_stmt->close();

$branch_id     = (int)($me['branch_id']     ?? 0);
$branch_name   = $me['branch_name']   ?? 'Your Branch';
$dept_id       = (int)($me['department_id'] ?? 0);
$dept_name     = $me['department_name'] ?? 'Your Department';
$is_dept_mgr   = isDeptManagerRole($conn, $employee_id);

// ── Search / filter ────────────────────────────────────────────────────────
$search  = trim($_GET['search']  ?? '');
$filter  = trim($_GET['filter']  ?? 'all'); // all | direct

// ── Build team query ───────────────────────────────────────────────────────
// Always show everyone in the branch (excluding self).
// "Direct" tab filters to reports_to = this employee only.
$like = '%' . $search . '%';

if ($filter === 'direct') {
    $sup_rank = (int)($me['rank_category_id'] ?? 0);
    $hr_direct_filter = $dept_name === 'Human Resources';
    if ($hr_direct_filter) {
        $direct_reports = getEmployeeSubordinates($conn, $employee_id);
        $direct_report_ids = array_map(static function ($report) {
            return (int)$report['employee_id'];
        }, $direct_reports);
        $where_supervisor = $direct_report_ids
            ? 'e.employee_id IN (' . implode(',', $direct_report_ids) . ')'
            : '1 = 0';
    } else {
        $where_supervisor = "e.reports_to = ?";
        if (in_array($sup_rank, [3, 4])) {
            $where_supervisor = "(e.reports_to = ? OR (
                e.branch_id = " . (int)$branch_id . " AND e.department_id = " . (int)$dept_id . " AND e.employee_id != ? AND (
                    (e.rank_category_id = 5 AND $sup_rank IN (3,4)) OR
                    (e.rank_category_id = 4 AND $sup_rank = 3)
                )
            ))";
        }
    }

    $team_stmt = $conn->prepare("
        SELECT e.employee_id, e.employee_code, e.first_name, e.last_name,
               e.job_title, e.hire_date, e.employment_status, e.employment_type,
               e.profile_picture, e.reports_to,
               d.department_name, b.branch_name,
               rc.rank_name,
               u.email,
               ec.mobile_number, ec.telephone_number,
               (SELECT ev.status
                FROM evaluations ev
                WHERE ev.employee_id = e.employee_id
                  AND ev.deleted_at IS NULL
                ORDER BY COALESCE(ev.submitted_date, ev.updated_at, ev.created_at) DESC LIMIT 1
               ) AS latest_eval_status,
               (SELECT ev.total_score
                FROM evaluations ev
                WHERE ev.employee_id = e.employee_id
                  AND ev.deleted_at IS NULL
                  AND ev.status NOT IN ('Draft', 'Pending Self-Rating')
                ORDER BY COALESCE(ev.submitted_date, ev.updated_at, ev.created_at) DESC LIMIT 1
               ) AS latest_submitted_score,
               (SELECT ev.performance_level
                FROM evaluations ev
                WHERE ev.employee_id = e.employee_id
                  AND ev.deleted_at IS NULL
                  AND ev.status NOT IN ('Draft', 'Pending Self-Rating')
                ORDER BY COALESCE(ev.submitted_date, ev.updated_at, ev.created_at) DESC LIMIT 1
               ) AS latest_perf_level
        FROM employees e
        LEFT JOIN departments  d  ON e.department_id      = d.department_id
        LEFT JOIN branches     b  ON e.branch_id          = b.branch_id
        LEFT JOIN rank_categories rc ON e.rank_category_id = rc.rank_category_id
        LEFT JOIN users        u  ON u.employee_id        = e.employee_id
        LEFT JOIN employee_contacts ec ON ec.employee_id  = e.employee_id
        WHERE $where_supervisor
          AND e.is_active  = 1
          AND e.deleted_at IS NULL
          AND e.employee_id != ?
          AND (
              e.first_name  LIKE ? OR
              e.last_name   LIKE ? OR
              e.job_title   LIKE ? OR
              e.employee_code LIKE ?
          )
        ORDER BY e.last_name, e.first_name
    ");
    if ($hr_direct_filter) {
        $team_stmt->bind_param("issss", $employee_id, $like, $like, $like, $like);
    } elseif (in_array($sup_rank, [3, 4])) {
        $team_stmt->bind_param("iisssss", $employee_id, $employee_id, $employee_id, $like, $like, $like, $like);
    } else {
        $team_stmt->bind_param("iissss", $employee_id, $employee_id, $like, $like, $like, $like);
    }
} else {
    // All department members (excluding self)
    $team_stmt = $conn->prepare("
        SELECT e.employee_id, e.employee_code, e.first_name, e.last_name,
               e.job_title, e.hire_date, e.employment_status, e.employment_type,
               e.profile_picture, e.reports_to,
               d.department_name, b.branch_name,
               rc.rank_name,
               u.email,
               ec.mobile_number, ec.telephone_number,
               (SELECT ev.status
                FROM evaluations ev
                WHERE ev.employee_id = e.employee_id
                  AND ev.deleted_at IS NULL
                ORDER BY COALESCE(ev.submitted_date, ev.updated_at, ev.created_at) DESC LIMIT 1
               ) AS latest_eval_status,
               (SELECT ev.total_score
                FROM evaluations ev
                WHERE ev.employee_id = e.employee_id
                  AND ev.deleted_at IS NULL
                  AND ev.status NOT IN ('Draft', 'Pending Self-Rating')
                ORDER BY COALESCE(ev.submitted_date, ev.updated_at, ev.created_at) DESC LIMIT 1
               ) AS latest_submitted_score,
               (SELECT ev.performance_level
                FROM evaluations ev
                WHERE ev.employee_id = e.employee_id
                  AND ev.deleted_at IS NULL
                  AND ev.status NOT IN ('Draft', 'Pending Self-Rating')
                ORDER BY COALESCE(ev.submitted_date, ev.updated_at, ev.created_at) DESC LIMIT 1
               ) AS latest_perf_level
        FROM employees e
        LEFT JOIN departments  d  ON e.department_id      = d.department_id
        LEFT JOIN branches     b  ON e.branch_id          = b.branch_id
        LEFT JOIN rank_categories rc ON e.rank_category_id = rc.rank_category_id
        LEFT JOIN users        u  ON u.employee_id        = e.employee_id
        LEFT JOIN employee_contacts ec ON ec.employee_id  = e.employee_id
        WHERE e.department_id = ?
          AND e.is_active     = 1
          AND e.deleted_at    IS NULL
          AND e.employee_id  != ?
          AND (
              e.first_name  LIKE ? OR
              e.last_name   LIKE ? OR
              e.job_title   LIKE ? OR
              e.employee_code LIKE ?
          )
        ORDER BY e.last_name, e.first_name
    ");
    $team_stmt->bind_param("iissss", $dept_id, $employee_id, $like, $like, $like, $like);
}

$team_stmt->execute();
$team_result = $team_stmt->get_result();
$team        = $team_result->fetch_all(MYSQLI_ASSOC);
$team_stmt->close();

$total_members = count($team);

// ── Quick stats ────────────────────────────────────────────────────────────
$pending_eval_count    = 0;
$no_eval_count         = 0;
$approved_eval_count   = 0;
foreach ($team as $m) {
    $s = $m['latest_eval_status'] ?? null;
    if ($s === null || $s === '') $no_eval_count++;
    elseif ($s === 'Approved')    $approved_eval_count++;
    elseif (in_array($s, ['Pending Dept Supervisor','Pending Supervisor','Pending HR Consolidation','Pending Manager','Pending Dept Manager'])) $pending_eval_count++;
}

require_once '../includes/header.php';
?>

<style>
/* ── Team List Premium Styles ──────────────────────────────────────────── */
.team-hero-stat {
    background: rgba(255,255,255,.12);
    border: 1px solid rgba(255,255,255,.2);
    border-radius: 14px;
    padding: .9rem 1.2rem;
    color: #fff;
    text-align: center;
    backdrop-filter: blur(8px);
    min-width: 100px;
}
.team-hero-stat .stat-num {
    font-size: 1.8rem;
    font-weight: 800;
    line-height: 1;
}
.team-hero-stat .stat-lbl {
    font-size: .65rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    opacity: .75;
    margin-top: 3px;
}

/* ── Member Cards ───────────────────────────────────────────────────────── */
.member-card {
    background: var(--bg-white);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.25rem;
    display: flex;
    gap: 1rem;
    align-items: flex-start;
    transition: box-shadow .2s, transform .2s;
    position: relative;
    overflow: hidden;
    min-height: 80px;
}
.member-card:hover {
    box-shadow: 0 8px 28px rgba(67,104,254,.11);
    transform: translateY(-2px);
}
.member-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0;
    width: 4px; height: 100%;
    background: var(--primary-blue);
    border-radius: 4px 0 0 4px;
    opacity: 0;
    transition: opacity .2s;
}
.member-card:hover::before { opacity: 1; }

.member-avatar {
    width: 56px; height: 56px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--border-color);
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(0,0,0,.08);
}
.member-avatar-placeholder {
    width: 56px; height: 56px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(0,0,0,.1);
}

.member-info { flex: 1; min-width: 0; }
.member-name {
    font-weight: 700;
    font-size: .95rem;
    color: var(--text-dark);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.member-title {
    font-size: .78rem;
    color: var(--text-muted);
    margin-top: 2px;
}
.member-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: .55rem;
}
.meta-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: .68rem;
    padding: 2px 8px;
    border-radius: 20px;
    font-weight: 500;
}
.meta-pill.dept  { background: rgba(67,104,254,.08); color: var(--primary-blue); }
.meta-pill.hire  { background: rgba(40,167,69,.08);  color: #28a745; }
.meta-pill.type  { background: rgba(255,193,7,.12);  color: #856404; }
.meta-pill.rank  { background: rgba(108,117,125,.1); color: #495057; }
.meta-pill.direct{ background: rgba(220,53,69,.08);  color: #dc3545; }

.member-eval-block {
    text-align: right;
    flex-shrink: 0;
    min-width: 90px;
}
.score-circle {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 56px; height: 56px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-blue), var(--primary-dark));
    color: #fff;
    font-weight: 800;
    font-size: .85rem;
    box-shadow: 0 4px 12px rgba(67,104,254,.25);
    margin-bottom: 4px;
}
.score-circle.no-score {
    background: var(--bg-gray);
    color: var(--text-muted);
    font-size: .65rem;
    font-weight: 600;
    box-shadow: none;
}

/* ── Filter tabs ────────────────────────────────────────────────────────── */
.filter-tab-group {
    display: flex; gap: 6px;
}
.filter-tab {
    min-height: 48px;
    display: inline-flex;
    align-items: center;
    padding: 0 16px;
    border-radius: 20px;
    font-size: .8rem;
    font-weight: 600;
    border: 1.5px solid var(--border-color);
    background: var(--bg-white);
    color: var(--text-muted);
    text-decoration: none;
    transition: all .2s;
    cursor: pointer;
}
.filter-tab:hover,
.filter-tab.active {
    background: var(--primary-blue);
    border-color: var(--primary-blue);
    color: #fff;
}

/* ── Search bar ──────────────────────────────────────────────────────────── */
.team-search-wrap { position: relative; }
.team-search-wrap i {
    position: absolute;
    left: 12px; top: 50%; transform: translateY(-50%);
    color: var(--text-muted); font-size: .85rem;
}
.team-search-wrap input {
    padding-left: 34px;
    border-radius: 20px;
    font-size: .85rem;
}

/* ── Empty state ────────────────────────────────────────────────────────── */
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--text-muted);
}
.empty-state i { font-size: 2.8rem; opacity: .2; margin-bottom: 1rem; display: block; }

/* ── Responsive grid ────────────────────────────────────────────────────── */
.team-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
}
@media (min-width: 768px) {
    .team-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (min-width: 992px) {
    .team-grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 767px) {
    .member-card { padding: 1rem; gap: .75rem; }
    .member-avatar, .member-avatar-placeholder { width: 46px; height: 46px; }
    .score-circle { width: 46px; height: 46px; font-size: .75rem; }
    .team-hero-stat { padding: .6rem .75rem; min-width: 70px; }
    .team-hero-stat .stat-num { font-size: 1.4rem; }
}

/* ── Contact action buttons ─────────────────────────────────────────────── */
.contact-actions {
    display: flex;
    gap: 6px;
    margin-top: .6rem;
    flex-wrap: wrap;
}
.contact-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    min-height: 40px;
    padding: 0 10px;
    border-radius: 8px;
    font-size: .75rem;
    font-weight: 600;
    text-decoration: none;
    gap: 4px;
    transition: background .2s, color .2s, box-shadow .2s;
    border: 1.5px solid transparent;
}
.contact-btn-email {
    background: rgba(67,104,254,.08);
    color: var(--primary-blue, #4368fe);
    border-color: rgba(67,104,254,.2);
}
.contact-btn-email:hover {
    background: var(--primary-blue, #4368fe);
    color: #fff;
    box-shadow: 0 2px 8px rgba(67,104,254,.25);
}
.contact-btn-call {
    background: rgba(40,167,69,.08);
    color: #28a745;
    border-color: rgba(40,167,69,.2);
}
.contact-btn-call:hover {
    background: #28a745;
    color: #fff;
    box-shadow: 0 2px 8px rgba(40,167,69,.2);
}
</style>

<!-- ── PAGE HERO ─────────────────────────────────────────────────────────── -->
<div class="page-hero fadeup">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
        <div>
            <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:1.5px;color:rgba(255,255,255,.55);">
                Employee Portal · <?php echo $is_dept_mgr ? 'Dept. Manager' : 'Dept. Supervisor'; ?>
            </div>
            <h2 class="text-white fw-bold mb-1 mt-1">
                <i class="fas fa-users me-2"></i>My Team
            </h2>
            <p class="mb-0 text-white-50 small">
                <i class="fas fa-sitemap me-1"></i><?php echo e($dept_name); ?> Department
            </p>
        </div>

        <!-- Hero Stats -->
        <div class="d-flex flex-wrap gap-2 mt-1">
            <div class="team-hero-stat">
                <div class="stat-num"><?php echo $total_members; ?></div>
                <div class="stat-lbl">Members</div>
            </div>
            <div class="team-hero-stat">
                <div class="stat-num text-warning"><?php echo $pending_eval_count; ?></div>
                <div class="stat-lbl">Pending Eval</div>
            </div>
            <div class="team-hero-stat">
                <div class="stat-num" style="color:#86efac;"><?php echo $approved_eval_count; ?></div>
                <div class="stat-lbl">Approved</div>
            </div>
            <div class="team-hero-stat">
                <div class="stat-num" style="color:rgba(255,255,255,.55);"><?php echo $no_eval_count; ?></div>
                <div class="stat-lbl">No Eval Yet</div>
            </div>
        </div>
    </div>
</div>



<!-- ── TOOLBAR ───────────────────────────────────────────────────────────── -->
<div class="content-card fadeup mb-4" style="padding: 1rem 1.25rem;">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">

        <!-- Filter Tabs -->
        <div class="filter-tab-group">
            <a href="?filter=all<?php echo $search ? '&search='.urlencode($search) : ''; ?>"
               class="filter-tab <?php echo $filter !== 'direct' ? 'active' : ''; ?>">
                <i class="fas fa-users me-1"></i>All Team
            </a>
            <a href="?filter=direct<?php echo $search ? '&search='.urlencode($search) : ''; ?>"
               class="filter-tab <?php echo $filter === 'direct' ? 'active' : ''; ?>">
                <i class="fas fa-sitemap me-1"></i>Direct Reports
            </a>
        </div>

        <!-- Search -->
        <form method="GET" action="" class="team-search-wrap" style="min-width: 220px; max-width: 320px; width: 100%;">
            <input type="hidden" name="filter" value="<?php echo e($filter); ?>">
            <i class="fas fa-search" aria-hidden="true"></i>
            <input type="text" class="form-control" name="search"
                   placeholder="Search name, position…"
                   value="<?php echo e($search); ?>"
                   aria-label="Search team members by name or position"
                   style="min-height: 48px;"
                   id="teamSearchInput">
        </form>
    </div>
</div>

<!-- ── TEAM GRID ─────────────────────────────────────────────────────────── -->
<?php
// Colour palette for avatar placeholders
$palette = ['#4368fe','#7c3aed','#0891b2','#16a34a','#dc2626','#d97706','#db2777','#059669','#6366f1','#0369a1'];

if (empty($team)):
?>
<div class="content-card fadeup">
    <div class="empty-state">
        <i class="fas fa-user-slash"></i>
        <h5 class="fw-semibold">No team members found</h5>
        <p class="small">
            <?php if ($search): ?>
                No results match "<strong><?php echo e($search); ?></strong>".
                <a href="?filter=<?php echo e($filter); ?>">Clear search</a>
            <?php elseif ($filter === 'direct'): ?>
                No employees are directly reporting to you yet.<br>
                <a href="?filter=all">View all department members instead</a>
            <?php else: ?>
                No active employees found in this department.
            <?php endif; ?>
        </p>
    </div>
</div>

<?php else: ?>

<div class="team-grid fadeup">
<?php
$idx = 0;
foreach ($team as $member):
    $full_name    = trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''));
    $initials     = strtoupper(substr($member['first_name'] ?? '?', 0, 1) . substr($member['last_name'] ?? '', 0, 1));
    $avatar_color = $palette[$idx % count($palette)];
    $avatar_url   = getEmployeeAvatar($member['profile_picture'] ?? '');
    $has_avatar   = !empty($member['profile_picture']);
    $is_direct    = ((int)$member['reports_to'] === $employee_id);

    $eval_status  = $member['latest_eval_status'] ?? null;
    $score        = $member['latest_submitted_score'] ?? null;
    $perf_level   = $member['latest_perf_level']   ?? null;

    // Employment status badge color
    $emp_status = $member['employment_status'] ?? '';
    $emp_status_class = in_array($emp_status, ['Regular','Full-time']) ? 'bg-success' :
                        (in_array($emp_status, ['Probationary','Trainee','OJT']) ? 'bg-warning text-dark' : 'bg-secondary');

    // Eval status pill
    $eval_badge_map = [
        'Draft'                    => ['secondary', 'fa-pencil-alt'],
        'Pending Self-Rating'      => ['warning text-dark', 'fa-user-edit'],
        'Pending Dept Supervisor'  => ['warning text-dark', 'fa-user-check'],
        'Pending Supervisor'       => ['info text-dark',    'fa-user-check'],
        'Pending Dept Manager'     => ['info text-dark',    'fa-user-tie'],
        'Pending HR Consolidation' => ['primary',           'fa-layer-group'],
        'Pending Manager'          => ['primary',           'fa-user-tie'],
        'Supervisor Confirmed'     => ['success',           'fa-check-double'],
        'Approved'                 => ['success',           'fa-check-circle'],
        'Rejected'                 => ['danger',            'fa-times-circle'],
        'Returned'                 => ['warning text-dark', 'fa-undo'],
    ];
    [$eval_color, $eval_icon] = $eval_badge_map[$eval_status] ?? ['secondary', 'fa-circle'];
    $idx++;
?>
    <div class="member-card" role="article" aria-label="<?php echo e($full_name); ?>">
        <!-- Avatar -->
        <?php if ($has_avatar): ?>
            <img src="<?php echo $avatar_url; ?>?v=<?php echo time(); ?>"
                 alt="<?php echo e($full_name); ?>"
                 class="member-avatar"
                 loading="lazy">
        <?php else: ?>
            <div class="member-avatar-placeholder"
                 style="background: <?php echo $avatar_color; ?>;">
                <?php echo $initials; ?>
            </div>
        <?php endif; ?>

        <!-- Info -->
        <div class="member-info">
            <div class="member-name"><?php echo e($full_name); ?></div>
            <div class="member-title"><?php echo e($member['job_title'] ?? '—'); ?></div>

            <div class="member-meta">
                <?php if ($is_direct): ?>
                    <span class="meta-pill direct"><i class="fas fa-level-up-alt"></i> Direct Report</span>
                <?php endif; ?>
                <?php if (!empty($member['department_name'])): ?>
                    <span class="meta-pill dept"><i class="fas fa-sitemap"></i><?php echo e($member['department_name']); ?></span>
                <?php endif; ?>
                <?php if (!empty($member['rank_name'])): ?>
                    <span class="meta-pill rank"><i class="fas fa-layer-group"></i><?php echo e($member['rank_name']); ?></span>
                <?php endif; ?>
                <?php if (!empty($member['hire_date'])): ?>
                    <span class="meta-pill hire"><i class="fas fa-calendar-alt"></i>Since <?php echo date('M Y', strtotime($member['hire_date'])); ?></span>
                <?php endif; ?>
                <?php if ($emp_status): ?>
                    <span class="badge <?php echo $emp_status_class; ?>" style="font-size:.65rem;"><?php echo e($emp_status); ?></span>
                <?php endif; ?>
            </div>

            <!-- Eval status -->
            <div class="mt-2">
                <?php if ($eval_status): ?>
                    <span class="badge bg-<?php echo $eval_color; ?>" style="font-size:.68rem;">
                        <i class="fas <?php echo $eval_icon; ?> me-1"></i><?php echo e($eval_status); ?>
                    </span>
                <?php else: ?>
                    <span class="badge bg-light text-muted border" style="font-size:.68rem;">
                        <i class="fas fa-minus me-1"></i>No Evaluation Yet
                    </span>
                <?php endif; ?>
            </div>

            <!-- Contact actions (Req 12.2, 12.7) -->
            <?php
                $member_email = $member['email'] ?? null;
                $member_phone = $member['mobile_number'] ?? ($member['telephone_number'] ?? null);
            ?>
            <div class="contact-actions" aria-label="Contact <?php echo e($full_name); ?>">
                <?php if (!empty($member_email)): ?>
                    <a href="mailto:<?php echo e($member_email); ?>"
                       class="contact-btn contact-btn-email"
                       title="Email <?php echo e($full_name); ?>"
                       aria-label="Email <?php echo e($full_name); ?>">
                        <i class="fas fa-envelope" aria-hidden="true"></i>
                        <span>Email</span>
                    </a>
                <?php else: ?>
                    <span class="contact-btn contact-btn-email"
                          title="No email on record"
                          style="opacity:.45;cursor:default;"
                          aria-label="No email available for <?php echo e($full_name); ?>">
                        <i class="fas fa-envelope" aria-hidden="true"></i>
                        <span>Email</span>
                    </span>
                <?php endif; ?>

            </div>
        </div>

        <!-- Score -->
        <div class="member-eval-block">
            <?php if ($score !== null && $score !== ''): ?>
                <?php if ($eval_status === 'Approved'): ?>
                    <div class="score-circle score-circle--approved" title="Approved Final Rating: <?php echo number_format((float)$score, 2); ?>">
                        <?php echo number_format((float)$score, 2); ?>
                    </div>
                    <div style="font-size:.68rem;color:#16A34A;font-weight:700;white-space:nowrap;">
                        <?php echo e($perf_level ?: 'Approved'); ?>
                    </div>
                <?php else: ?>
                    <div class="score-circle score-circle--submitted" title="Submitted Self-Rating (In Review): <?php echo number_format((float)$score, 2); ?>">
                        <?php echo number_format((float)$score, 2); ?>
                    </div>
                    <div style="font-size:.68rem;color:#8C6D0D;font-weight:700;white-space:nowrap;" title="Self-rating submitted and currently in review">
                        Self-Rating
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="score-circle no-score" title="No self-rating submitted yet">
                    N/A
                </div>
                <div style="font-size:.65rem;color:var(--text-muted);white-space:nowrap;">No score</div>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
</div>

<!-- Result count -->
<div class="text-muted small mt-3 mb-2 fadeup" style="animation-delay:.15s;">
    <i class="fas fa-users me-1"></i>
    Showing <strong><?php echo $total_members; ?></strong> member<?php echo $total_members !== 1 ? 's' : ''; ?>
    <?php if ($search): ?> matching "<strong><?php echo e($search); ?></strong>"<?php endif; ?>
    <?php if ($filter === 'direct'): ?> (direct reports only)<?php endif; ?>
</div>

<?php endif; ?>

<script>
// Live client-side search filter (supplements server-side)
document.getElementById('teamSearchInput').addEventListener('input', function () {
    clearTimeout(this._t);
    this._t = setTimeout(() => this.closest('form').submit(), 400);
});
</script>

<?php require_once '../includes/footer.php'; ?>
