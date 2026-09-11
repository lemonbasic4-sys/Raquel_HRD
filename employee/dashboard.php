<?php
$page_title = 'My Dashboard';
require_once '../includes/session-check.php';
checkRole(['Employee']);
require_once '../includes/functions.php';

$employee_id = (int)($_SESSION['employee_id'] ?? 0);
$user_id     = (int)($_SESSION['user_id']     ?? 0);

// ── Employee core info ──────────────────────────────────────────────────────
$emp_stmt = $conn->prepare("
    SELECT e.employee_id, e.employee_code, e.first_name, e.last_name,
           e.job_title, e.profile_picture, e.hire_date,
           e.employment_status, e.employment_type,
           e.branch_id, e.department_id, e.rank_category_id,
           d.department_name, b.branch_name,
           rc.rank_name
    FROM employees e
    LEFT JOIN departments    d  ON e.department_id      = d.department_id
    LEFT JOIN branches       b  ON e.branch_id          = b.branch_id
    LEFT JOIN rank_categories rc ON e.rank_category_id = rc.rank_category_id
    WHERE e.employee_id = ?
");
$emp_stmt->bind_param("i", $employee_id);
$emp_stmt->execute();
$emp = $emp_stmt->get_result()->fetch_assoc() ?? [];
$emp_stmt->close();

// ── Years of service ────────────────────────────────────────────────────────
$years_of_service = 0;
$months_of_service = 0;
if (!empty($emp['hire_date'])) {
    $hire = new DateTime($emp['hire_date']);
    $now  = new DateTime();
    $diff = $hire->diff($now);
    $years_of_service  = $diff->y;
    $months_of_service = $diff->m;
}

// ── Active / pending evaluations for this employee ──────────────────────────
$active_eval = null;
$eval_stmt = $conn->prepare("
    SELECT ev.evaluation_id, ev.status, ev.evaluation_type,
           ev.evaluation_period_start, ev.evaluation_period_end,
           ev.total_score, ev.performance_level,
            et.template_name AS template_title,
            ep.package_id, ep.status AS package_status,
            ep.current_step_order, rs.step_label AS current_step_label
    FROM evaluations ev
    LEFT JOIN evaluation_templates et ON ev.template_id = et.template_id
        LEFT JOIN evaluation_package_members pm ON pm.evaluation_id = ev.evaluation_id
        LEFT JOIN evaluation_packages ep ON ep.package_id = pm.package_id
        LEFT JOIN evaluation_package_route_steps rs
         ON rs.package_id = ep.package_id AND rs.step_order = ep.current_step_order
    WHERE ev.employee_id = ? AND ev.deleted_at IS NULL
    ORDER BY ev.created_at DESC
    LIMIT 1
");
$eval_stmt->bind_param("i", $employee_id);
$eval_stmt->execute();
$active_eval = $eval_stmt->get_result()->fetch_assoc();
$eval_stmt->close();

// ── All evaluations count ───────────────────────────────────────────────────
$total_evals = 0;
$completed_evals = 0;
$r = $conn->query("SELECT COUNT(*) as c FROM evaluations WHERE employee_id = $employee_id AND deleted_at IS NULL");
if ($r) $total_evals = (int)$r->fetch_assoc()['c'];
$r2 = $conn->query("SELECT COUNT(*) as c FROM evaluations WHERE employee_id = $employee_id AND status = 'Approved' AND deleted_at IS NULL");
if ($r2) $completed_evals = (int)$r2->fetch_assoc()['c'];

$pending_template_count = 0;
$employee_dept = $emp['department_name'] ?? '';
$pending_templates_stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM evaluation_templates et
    WHERE et.status = 'Active'
      AND et.deleted_at IS NULL
      AND (et.target_department IS NULL OR et.target_department = '' OR et.target_department = 'All Departments' OR et.target_department = ?)
      AND NOT EXISTS (
          SELECT 1
          FROM evaluations ev
          WHERE ev.employee_id = ?
            AND ev.template_id = et.template_id
            AND ev.deleted_at IS NULL
            AND ev.status NOT IN ('Draft', 'Returned', 'Rejected', 'Pending Self-Rating')
      )
");
$pending_templates_stmt->bind_param("si", $employee_dept, $employee_id);
$pending_templates_stmt->execute();
$pending_template_count = (int) ($pending_templates_stmt->get_result()->fetch_assoc()['total'] ?? 0);
$pending_templates_stmt->close();

// ── Latest approved score ───────────────────────────────────────────────────
$latest_score = null;
$score_stmt = $conn->prepare("
    SELECT total_score, performance_level, approved_date
    FROM evaluations
    WHERE employee_id = ? AND status = 'Approved' AND deleted_at IS NULL
    ORDER BY approved_date DESC LIMIT 1
");
$score_stmt->bind_param("i", $employee_id);
$score_stmt->execute();
$latest_score = $score_stmt->get_result()->fetch_assoc();
$score_stmt->close();

// ── Career movements ────────────────────────────────────────────────────────
$career_movements = [];
$cm_stmt = $conn->prepare("
    SELECT cm.movement_type, cm.previous_position, cm.new_position,
           cm.effective_date, cm.approval_status,
           b.branch_name AS new_branch_name
    FROM career_movements cm
    LEFT JOIN branches b ON cm.new_branch_id = b.branch_id
    WHERE cm.employee_id = ?
    ORDER BY cm.effective_date DESC
    LIMIT 5
");
$cm_stmt->bind_param("i", $employee_id);
$cm_stmt->execute();
$career_movements = $cm_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$cm_stmt->close();



// ── Has supervisor privileges? (subordinates in DB OR supervisor/manager role) ─
$is_supervisor = hasSupervisorPrivileges($conn, $employee_id);
$employee_hr_role = getEmployeeHRRole($conn, $employee_id);

// ── Does THIS employee have a dept supervisor / dept manager above them? ─────
ensureEmployeesReportsTo($conn);
$my_supervisor    = getEmployeeSupervisor($conn, $employee_id);
$has_dept_supervisor = ($my_supervisor !== null && !empty($my_supervisor['user_id']));
$my_dept_manager  = getDeptManagerOfEmployee($conn, $employee_id);
$has_dept_manager = ($my_dept_manager !== null && !empty($my_dept_manager['user_id']));

// ── Pending subordinate ratings count ──────────────────────────────────────
$pending_sub_count = 0;
if ($is_supervisor) {
    $sup_branch = (int)($emp['branch_id'] ?? 0);
    $sup_dept   = (int)($emp['department_id'] ?? 0);
    $sup_rank   = (int)($emp['rank_category_id'] ?? 0);

    $where_supervisor = "e.reports_to = $employee_id";
    if (in_array($sup_rank, [3, 4])) {
        $where_supervisor = "(e.reports_to = $employee_id OR (
            e.branch_id = $sup_branch AND e.department_id = $sup_dept AND e.employee_id != $employee_id AND (
                (e.rank_category_id = 5 AND $sup_rank IN (3,4)) OR
                (e.rank_category_id = 4 AND $sup_rank = 3)
            )
        ))";
    }

    $ps = $conn->query("
        SELECT COUNT(*) AS total
        FROM evaluations ev
        INNER JOIN employees e ON ev.employee_id = e.employee_id
        WHERE e.reports_to = $employee_id
          AND ev.status IN ('Pending Dept Supervisor', 'Pending Supervisor')
          AND ev.deleted_at IS NULL
    ");
    if ($ps) $pending_sub_count = (int)$ps->fetch_assoc()['total'];
}

// ── HR Supervisor validation queue (mirrors supervisor Pending Endorsements) ───
$validation_queue_count = 0;
$validation_queue_rows = [];
$show_validation_queue = false; // Disabled on employee portal dashboard as HRD evaluations are handled on HRIS admin portal

if ($show_validation_queue) {
    $validation_count_stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM evaluations ev
        INNER JOIN employees e ON ev.employee_id = e.employee_id
        WHERE ev.status IN ('Pending Supervisor', 'Pending HR Consolidation')
          AND e.is_active = 1
          AND ev.deleted_at IS NULL
          AND e.employee_id NOT IN (
              SELECT employee_id
              FROM users
              WHERE role = 'Admin'
                AND employee_id IS NOT NULL
          )
    ");
    $validation_count_stmt->execute();
    $validation_queue_count = (int) ($validation_count_stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $validation_count_stmt->close();

    $validation_rows_stmt = $conn->prepare("
        SELECT ev.evaluation_id, ev.status, ev.total_score, ev.performance_level,
               ev.submitted_date, et.template_name,
               CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
               e.employee_code, e.job_title,
               d.department_name,
               COALESCE(DATEDIFF(CURRENT_DATE(), DATE(ev.submitted_date)), 0) AS days_pending
        FROM evaluations ev
        INNER JOIN employees e ON ev.employee_id = e.employee_id
        LEFT JOIN departments d ON e.department_id = d.department_id
        LEFT JOIN evaluation_templates et ON ev.template_id = et.template_id
        WHERE ev.status IN ('Pending Supervisor', 'Pending HR Consolidation')
          AND e.is_active = 1
          AND ev.deleted_at IS NULL
          AND e.employee_id NOT IN (
              SELECT employee_id
              FROM users
              WHERE role = 'Admin'
                AND employee_id IS NOT NULL
          )
        ORDER BY COALESCE(ev.submitted_date, ev.updated_at, ev.created_at) ASC, ev.evaluation_id ASC
        LIMIT 3
    ");
    $validation_rows_stmt->execute();
    $validation_queue_rows = $validation_rows_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $validation_rows_stmt->close();
}

require_once '../includes/header.php';

// ── Helpers ─────────────────────────────────────────────────────────────────
function evalStatusBadge(string $status): string {
    $map = [
        'Draft'                    => ['secondary', 'fa-pencil-alt'],
        'Pending Self-Rating'      => ['warning',   'fa-user-edit'],
        'Pending Dept Supervisor'  => ['warning text-dark', 'fa-user-check'],
        'Pending Dept Manager'     => ['info text-dark', 'fa-user-tie'],
        'Pending Supervisor'       => ['info',      'fa-user-check'],
        'Pending HR Consolidation' => ['primary',   'fa-layer-group'],
        'Pending Manager'          => ['primary',   'fa-user-tie'],
        'Pending Team Consolidation' => ['warning text-dark', 'fa-users'],
        'Submitted'                => ['info',      'fa-layer-group'],
        'Supervisor Confirmed'     => ['success',   'fa-check-double'],
        'Approved'                 => ['success',   'fa-check-circle'],
        'Rejected'                 => ['danger',    'fa-times-circle'],
        'Returned'                 => ['warning',   'fa-undo'],
    ];
    [$color, $icon] = $map[$status] ?? ['secondary', 'fa-circle'];
    return "<span class=\"badge bg-{$color}\"><i class=\"fas {$icon} me-1\"></i>" . htmlspecialchars($status) . "</span>";
}

function movementIcon(string $type): string {
    $icons = [
        'Promotion'   => 'fa-arrow-up text-success',
        'Transfer'    => 'fa-exchange-alt text-info',
        'Demotion'    => 'fa-arrow-down text-danger',
        'Role Change' => 'fa-sync-alt text-warning',
    ];
    return $icons[$type] ?? 'fa-circle text-secondary';
}
?>

<!-- ══════════════════════════════════════════════════════════════════════════
     HERO BANNER
══════════════════════════════════════════════════════════════════════════ -->
<div class="page-hero fadeup">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-4">
        <div class="d-flex align-items-center gap-4 flex-wrap">
            <img src="<?php echo getEmployeeAvatar($emp['profile_picture'] ?? ''); ?>"
                 onclick="viewFullImage('<?php echo getEmployeeAvatar($emp['profile_picture'] ?? ''); ?>', '<?php echo e(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')); ?>')"
                 class="cursor-pointer emp-portal-avatar"
                 loading="lazy"
                 style="width:76px;height:76px;border-radius:50%;object-fit:cover;border:3px solid rgba(255,255,255,.35);box-shadow:0 6px 20px rgba(0,0,0,.25);transition:transform .2s;background-color:#ffffff;flex-shrink:0;">

            <div>
                <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:1.5px;color:rgba(255,255,255,.55);">Employee Portal · Welcome Back</div>
                <h2 class="text-white fw-bold mb-1 mt-1" style="font-size:1.6rem;">
                    <i class="fas fa-user-circle me-1"></i> Hello, <?php echo e(trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''))); ?>!
                </h2>
                <p class="mb-0 text-white-50 small">
                    <i class="fas fa-briefcase me-1"></i><?php echo e($emp['job_title'] ?? '—'); ?>
                    <?php if (!empty($emp['department_name'])): ?>&nbsp;·&nbsp;<i class="fas fa-sitemap me-1"></i><?php echo e($emp['department_name']); ?><?php endif; ?>
                    <?php if (!empty($emp['branch_name'])): ?>&nbsp;·&nbsp;<i class="fas fa-map-marker-alt me-1"></i><?php echo e($emp['branch_name']); ?><?php endif; ?>
                </p>
                <?php if (!empty($emp['rank_name'])): ?>
                <p class="mb-0 mt-1">
                    <span class="badge" style="background:rgba(255,255,255,.18);color:#fff;font-size:.72rem;letter-spacing:.5px;">
                        <i class="fas fa-layer-group me-1"></i><?php echo e($emp['rank_name']); ?>
                    </span>
                    <span class="badge ms-1" style="background:rgba(255,255,255,.18);color:#fff;font-size:.72rem;">
                        <i class="fas fa-id-badge me-1"></i><?php echo e($emp['employment_status'] ?? '—'); ?>
                    </span>
                </p>
                <?php endif; ?>
            </div>
        </div>
        <div class="text-end d-none d-md-block">
            <div class="text-white fw-bold" style="font-size:1.8rem;line-height:1;" id="live-time"><?php echo date('h:i A'); ?></div>
            <div class="text-white-50 small mt-1"><?php echo date('l, F d, Y'); ?></div>
            <div class="mt-2">
                <span class="badge" style="background:rgba(255,255,255,.18);color:#fff;font-size:.72rem;">
                    <i class="fas fa-calendar-alt me-1"></i>
                    Hired <?php echo formatDate($emp['hire_date'] ?? ''); ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     STATS ROW (UX-revamp stat-cards)
══════════════════════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-4 fadeup">
    <!-- Years of Service -->
    <div class="col-6 col-md-3">
        <div class="stat-card h-100">
            <div class="stat-icon stat-icon-warning" aria-hidden="true">
                <i class="fas fa-star"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-value">
                    <?php echo $years_of_service; ?><span style="font-size:1rem;font-weight:500;"> yr<?php echo $years_of_service != 1 ? 's' : ''; ?></span>
                </h3>
                <?php if ($months_of_service > 0): ?>
                <p class="stat-label" style="font-size:.75rem;text-transform:none;letter-spacing:0;"><?php echo $months_of_service; ?> month<?php echo $months_of_service != 1 ? 's' : ''; ?> more</p>
                <?php endif; ?>
                <p class="stat-label">Years of Service</p>
            </div>
        </div>
    </div>

    <!-- Total Evaluations -->
    <div class="col-6 col-md-3">
        <div class="stat-card h-100">
            <div class="stat-icon stat-icon-info" aria-hidden="true">
                <i class="fas fa-clipboard-list"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-value"><?php echo $total_evals; ?></h3>
                <p class="stat-label" style="text-transform:none;letter-spacing:0;font-size:.75rem;"><?php echo $completed_evals; ?> approved · <?php echo $pending_template_count; ?> pending</p>
                <p class="stat-label">Total Evaluations</p>
            </div>
        </div>
    </div>

    <!-- Latest Score -->
    <div class="col-6 col-md-3">
        <?php if ($latest_score):
            $ls_level = $latest_score['performance_level'] ?? '';
            $ls_color = $ls_level ? getPerformanceLevelColor($ls_level) : 'var(--text-dark)';
            $ls_badge = $ls_level ? getPerformanceLevelBadgeClass($ls_level) : 'bg-secondary';
        ?>
        <div class="stat-card h-100">
            <div class="stat-icon stat-icon-success" aria-hidden="true">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-value" style="color:<?php echo $ls_color; ?>;"><?php echo number_format((float)$latest_score['total_score'], 2); ?></h3>
                <?php if ($ls_level): ?>
                <span class="badge <?php echo $ls_badge; ?>" style="font-size:.65rem;">
                    <i class="fas fa-award me-1"></i><?php echo e($ls_level); ?>
                </span>
                <?php endif; ?>
                <p class="stat-label">Latest Score</p>
            </div>
        </div>
        <?php else: ?>
        <div class="stat-card h-100">
            <div class="stat-icon stat-icon-success" aria-hidden="true">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-value" style="font-size:1.4rem;color:var(--color-text-muted,#5E6B5C);">N/A</h3>
                <p class="stat-label" style="text-transform:none;font-size:.75rem;letter-spacing:0;">No approved score yet</p>
                <p class="stat-label">Latest Score</p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Career Movements -->
    <div class="col-6 col-md-3">
        <div class="stat-card h-100">
            <div class="stat-icon stat-icon-danger" aria-hidden="true">
                <i class="fas fa-route"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-value"><?php echo count($career_movements); ?></h3>
                <p class="stat-label" style="text-transform:none;letter-spacing:0;font-size:.75rem;">recorded movements</p>
                <p class="stat-label">Career Movements</p>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     MAIN BODY — 2 columns (responsive: single col on mobile, 2-col on md+)
══════════════════════════════════════════════════════════════════════════ -->
<div class="dashboard-grid row g-4 fadeup align-items-stretch">

    <!-- COLUMN 1: My Evaluation Status -->
    <div class="col-12 col-lg-6 d-flex flex-column">

        <!-- Current Evaluation Status -->
        <div class="content-card mb-4">
            <div class="content-card-header">
                <h2 class="content-card-title">
                    <i class="fas fa-clipboard-check" aria-hidden="true"></i>
                    My Evaluation Status
                </h2>
                <div class="d-flex align-items-center gap-2">
                    <a href="<?php echo BASE_URL; ?>/employee/self-rating.php" class="badge bg-warning text-dark text-decoration-none">
                        <i class="fas fa-clock me-1"></i><?php echo $pending_template_count; ?> pending
                    </a>
                    <a href="<?php echo BASE_URL; ?>/employee/evaluation-history.php" class="btn btn-sm btn-outline-primary">View History</a>
                </div>
            </div>
            <div class="content-card-body">
                <?php if ($active_eval): ?>
                <div class="eval-status-summary mb-3">
                    <div class="eval-status-summary-main">
                        <div class="eval-status-copy">
                            <div class="eval-status-title"><?php echo e($active_eval['template_title'] ?? 'Evaluation'); ?></div>
                            <div class="eval-status-meta">
                                <i class="fas fa-tag me-1"></i><?php echo e($active_eval['evaluation_type'] ?? '—'); ?>
                                <?php if (!empty($active_eval['evaluation_period_start'])): ?>
                                &nbsp;·&nbsp;
                                <i class="fas fa-calendar me-1"></i>
                                <?php echo formatDate($active_eval['evaluation_period_start']); ?> – <?php echo formatDate($active_eval['evaluation_period_end'] ?? ''); ?>
                                <?php endif; ?>
                            </div>
                            <?php echo evalStatusBadge($active_eval['status'] ?? 'Draft'); ?>
                        </div>
                        <?php if (!empty($active_eval['total_score'])):
                            $ae_level = $active_eval['performance_level'] ?? '';
                            $ae_color = $ae_level ? getPerformanceLevelColor($ae_level) : 'var(--primary-blue)';
                            $ae_badge = $ae_level ? getPerformanceLevelBadgeClass($ae_level) : 'bg-secondary';
                        ?>
                        <div class="eval-status-score">
                            <div class="eval-score-value" style="color:<?php echo $ae_color; ?>;"><?php echo number_format((float)$active_eval['total_score'], 2); ?></div>
                            <?php if ($ae_level): ?>
                            <span class="badge <?php echo $ae_badge; ?>"><i class="fas fa-award me-1"></i><?php echo e($ae_level); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (in_array($active_eval['status'], ['Pending Self-Rating', 'Draft'])): ?>
                    <div class="eval-status-action">
                        <a href="<?php echo BASE_URL; ?>/employee/self-rating.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-play me-1"></i>Continue Self Rating
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <?php
                // Workflow Progress Bar (dynamic: HRD vs full-chain)
                $current_status = $active_eval['status'] ?? '';
                $hr_role = $employee_hr_role;

                $package_workflow = !empty($active_eval['package_id']);
                if ($package_workflow) {
                    // Organization packages use their configured department route.
                    $workflow_labels = ['Team Self-Ratings'];
                    $route_stmt = $conn->prepare('SELECT step_label FROM evaluation_package_route_steps WHERE package_id = ? ORDER BY step_order');
                    $route_package_id = (int)$active_eval['package_id'];
                    $route_stmt->bind_param('i', $route_package_id);
                    $route_stmt->execute();
                    $route_result = $route_stmt->get_result();
                    while ($route_step = $route_result->fetch_assoc()) {
                        $workflow_labels[] = $route_step['step_label'];
                    }
                    $route_stmt->close();
                    $workflow_labels[] = 'Approved';
                    $workflow_steps = $workflow_labels;

                    $package_status = $active_eval['package_status'] ?? '';
                    $package_step = (int)($active_eval['current_step_order'] ?? 0);
                    if ($package_status === 'Approved and Applied' || $current_status === 'Approved') {
                        $step_index = count($workflow_labels) - 1;
                    } elseif ($package_status === 'Returned' || $package_status === 'Cancelled' || $package_step <= 0) {
                        $step_index = 0;
                    } else {
                        // Route step 1 follows the team self-rating stage at index 0.
                        $step_index = min($package_step, count($workflow_labels) - 2);
                    }
                    $status_step_map = [];
                } elseif ($hr_role === 'HR Manager') {
                    $workflow_steps  = ['Pending Self-Rating', 'Pending Supervisor', 'Approved'];
                    $workflow_labels = ['Self-Rating', 'HR Supervisor', 'Approved'];
                    $status_step_map = [
                        'Draft'                    => 0,
                        'Returned'                 => 0,
                        'Pending Self-Rating'      => 0,
                        'Pending Dept Supervisor'  => 1,
                        'Pending Supervisor'       => 1,
                        'Approved'                 => 2,
                        'Rejected'                 => 2,
                    ];
                } elseif ($hr_role === 'HR Supervisor') {
                    $workflow_steps  = ['Pending Self-Rating', 'Pending Manager', 'Approved'];
                    $workflow_labels = ['Self-Rating', 'HR Manager', 'Approved'];
                    $status_step_map = [
                        'Draft'                    => 0,
                        'Returned'                 => 0,
                        'Pending Self-Rating'      => 0,
                        'Pending Manager'          => 1,
                        'Approved'                 => 2,
                        'Rejected'                 => 2,
                    ];
                } elseif ($hr_role === 'HR Staff') {
                    $workflow_steps  = ['Pending Self-Rating', 'Pending Supervisor', 'Pending Manager', 'Approved'];
                    $workflow_labels = ['Self-Rating', 'HR Supervisor', 'HR Manager', 'Approved'];
                    $status_step_map = [
                        'Draft'                    => 0,
                        'Returned'                 => 0,
                        'Pending Self-Rating'      => 0,
                        'Pending Dept Supervisor'  => 1,
                        'Pending Supervisor'       => 1,
                        'Pending Manager'          => 2,
                        'Approved'                 => 3,
                        'Rejected'                 => 3,
                    ];
                } else {
                    // Non-HR employee
                    if ($has_dept_supervisor && $has_dept_manager) {
                        // Full org-chain workflow
                        $workflow_steps  = ['Pending Self-Rating', 'Pending Dept Supervisor', 'Pending Dept Manager', 'Pending HR Consolidation', 'Pending Manager', 'Approved'];
                        $workflow_labels = ['Self-Rating', 'Dept Supervisor', 'Dept Manager', 'HR Consolidation', 'HR Manager', 'Approved'];
                        $status_step_map = [
                            'Draft'                    => 0,
                            'Returned'                 => 0,
                            'Pending Self-Rating'      => 0,
                            'Pending Dept Supervisor'  => 1,
                            'Pending Supervisor'       => 1,
                            'Pending Dept Manager'     => 2,
                            'Supervisor Confirmed'     => 3,
                            'Pending HR Consolidation' => 3,
                            'Pending Manager'          => 4,
                            'Approved'                 => 5,
                            'Rejected'                 => 5,
                        ];
                    } elseif ($has_dept_supervisor) {
                        // Supervisor only workflow
                        $workflow_steps  = ['Pending Self-Rating', 'Pending Dept Supervisor', 'Pending HR Consolidation', 'Pending Manager', 'Approved'];
                        $workflow_labels = ['Self-Rating', 'Dept Supervisor', 'HR Consolidation', 'HR Manager', 'Approved'];
                        $status_step_map = [
                            'Draft'                    => 0,
                            'Returned'                 => 0,
                            'Pending Self-Rating'      => 0,
                            'Pending Dept Supervisor'  => 1,
                            'Pending Supervisor'       => 1,
                            'Pending Dept Manager'     => 1,
                            'Supervisor Confirmed'     => 2,
                            'Pending HR Consolidation' => 2,
                            'Pending Manager'          => 3,
                            'Approved'                 => 4,
                            'Rejected'                 => 4,
                        ];
                    } elseif ($has_dept_manager) {
                        // Manager only workflow (no supervisor)
                        $workflow_steps  = ['Pending Self-Rating', 'Pending Dept Manager', 'Pending HR Consolidation', 'Pending Manager', 'Approved'];
                        $workflow_labels = ['Self-Rating', 'Dept Manager', 'HR Consolidation', 'HR Manager', 'Approved'];
                        $status_step_map = [
                            'Draft'                    => 0,
                            'Returned'                 => 0,
                            'Pending Self-Rating'      => 0,
                            'Pending Dept Supervisor'  => 1,
                            'Pending Supervisor'       => 1,
                            'Pending Dept Manager'     => 1,
                            'Supervisor Confirmed'     => 2,
                            'Pending HR Consolidation' => 2,
                            'Pending Manager'          => 3,
                            'Approved'                 => 4,
                            'Rejected'                 => 4,
                        ];
                    } else {
                        // Direct-to-HR (no supervisor at all)
                        $workflow_steps  = ['Pending Self-Rating', 'Pending HR Consolidation', 'Pending Manager', 'Approved'];
                        $workflow_labels = ['Self-Rating', 'HR Consolidation', 'HR Manager', 'Approved'];
                        $status_step_map = [
                            'Draft'                    => 0,
                            'Returned'                 => 0,
                            'Pending Self-Rating'      => 0,
                            'Pending Dept Supervisor'  => 1,
                            'Pending Supervisor'       => 1,
                            'Pending Dept Manager'     => 1,
                            'Supervisor Confirmed'     => 1,
                            'Pending HR Consolidation' => 1,
                            'Pending Manager'          => 2,
                            'Approved'                 => 3,
                            'Rejected'                 => 3,
                        ];
                    }
                }

                if (!$package_workflow) {
                    $step_index = $status_step_map[$current_status] ?? null;
                    if ($step_index === null) {
                        $step_index = array_search($current_status, $workflow_steps, true);
                    }
                    if ($step_index === false || $step_index === null) {
                        $step_index = count($workflow_steps) - 1;
                    }
                }
                $total_steps  = count($workflow_steps) - 1;
                $progress_pct = $total_steps > 0 ? round(($step_index / $total_steps) * 100) : 100;
                $is_approved  = ($current_status === 'Approved');
                $is_rejected  = ($current_status === 'Rejected');
                ?>
                <div class="eval-workflow-wrap">
                    <div class="eval-workflow-head">
                        <span>Workflow Progress</span>
                        <strong><?php echo $is_approved ? 'Complete' : ($is_rejected ? 'Action needed' : $progress_pct . '%'); ?></strong>
                    </div>
                    <div class="eval-workflow" style="--eval-progress: <?php echo $progress_pct; ?>%; --eval-progress-ratio: <?php echo $progress_pct / 100; ?>; --eval-step-count: <?php echo count($workflow_labels); ?>;">
                        <?php foreach ($workflow_labels as $i => $label):
                            $done    = ($i < $step_index);
                            $current = ($i === $step_index);
                            $step_class = $done ? 'is-done' : ($current ? ($is_rejected ? 'is-current is-rejected' : 'is-current') : 'is-upcoming');
                        ?>
                        <div class="eval-step <?php echo $step_class; ?>">
                            <div class="eval-step-marker">
                                <?php if ($done): ?>
                                    <i class="fas fa-check"></i>
                                <?php elseif ($current && $is_rejected): ?>
                                    <i class="fas fa-times"></i>
                                <?php else: ?>
                                    <span><?php echo $i + 1; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="eval-step-label"><?php echo e($label); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="eval-workflow-bar <?php echo $is_rejected ? 'is-rejected' : ''; ?>" aria-hidden="true">
                        <span style="width:<?php echo $progress_pct; ?>%;"></span>
                    </div>
                </div>

                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-inbox" style="font-size:2.5rem;opacity:.2;"></i>
                    <p class="text-muted mt-3 mb-0">No active evaluation assigned.</p>
                    <p class="text-muted small">Your HR will assign one when it's time.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- COLUMN 2: Career Timeline -->
    <div class="col-12 col-lg-6 d-flex flex-column gap-4">

        <?php if ($show_validation_queue): ?>
        <!-- HR Supervisor Validation Queue -->
        <div class="content-card mb-0" id="validationQueueCard" data-validation-queue-card>
            <div class="content-card-header">
                <h2 class="content-card-title">
                    <i class="fas fa-user-check" aria-hidden="true"></i>
                    Validation Queue
                </h2>
                <span class="badge bg-primary">
                    <i class="fas fa-sync-alt me-1"></i>Live
                </span>
            </div>
            <div class="content-card-body">
                <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap mb-3">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase" style="letter-spacing:0;">HR Supervisor · Evaluation Review</div>
                        <div class="fw-bold mt-1">Pending Endorsements</div>
                    </div>
                    <div class="text-end">
                        <div style="font-size:2.25rem;font-weight:850;line-height:1;color:var(--primary-blue);">
                            <?php echo (int) $validation_queue_count; ?>
                        </div>
                        <div class="text-muted small">awaiting review</div>
                    </div>
                </div>

                <?php if (!empty($validation_queue_rows)): ?>
                    <div class="d-grid gap-2 mb-3">
                        <?php foreach ($validation_queue_rows as $queue_item): ?>
                            <div class="validation-queue-item">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div class="min-width-0">
                                        <div class="fw-semibold text-truncate"><?php echo e($queue_item['employee_name'] ?? 'Employee'); ?></div>
                                        <div class="text-muted small text-truncate">
                                            <?php echo e($queue_item['template_name'] ?? 'Evaluation'); ?>
                                            <?php if (!empty($queue_item['department_name'])): ?>
                                                · <?php echo e($queue_item['department_name']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <span class="badge <?php echo getStatusBadgeClass($queue_item['status'] ?? ''); ?>">
                                        <?php echo e($queue_item['status'] ?? 'Pending'); ?>
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center gap-2 mt-2 small">
                                    <span class="text-muted">
                                        <i class="fas fa-clock me-1"></i><?php echo (int) ($queue_item['days_pending'] ?? 0); ?> day<?php echo (int) ($queue_item['days_pending'] ?? 0) === 1 ? '' : 's'; ?> pending
                                    </span>
                                    <?php if ($queue_item['total_score'] !== null && $queue_item['total_score'] !== ''): ?>
                                        <span class="fw-semibold"><?php echo number_format((float) $queue_item['total_score'], 2); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle text-success" style="font-size:2.5rem;opacity:.35;"></i>
                        <p class="text-muted mt-3 mb-0">No pending endorsements right now.</p>
                    </div>
                <?php endif; ?>

                <a href="<?php echo BASE_URL; ?>/supervisor/pending-endorsements.php" class="btn btn-primary w-100 rounded-pill">
                    <i class="fas fa-clipboard-check me-2"></i>Open Pending Endorsements
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Career Timeline -->
        <div class="content-card mb-0 h-100">
            <div class="content-card-header">
                <h2 class="content-card-title">
                    <i class="fas fa-route" aria-hidden="true"></i>
                    Career Timeline
                </h2>
            </div>
            <div class="content-card-body">
                <?php if (!empty($career_movements)): ?>
                <div class="timeline">
                    <?php foreach ($career_movements as $i => $cm): ?>
                    <div class="timeline-item <?php echo $i === 0 ? 'latest' : ''; ?>">
                        <div class="timeline-icon">
                            <i class="fas <?php echo movementIcon($cm['movement_type']); ?>" style="font-size:.8rem;"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-1">
                                <div>
                                    <span class="fw-semibold"><?php echo e($cm['movement_type']); ?></span>
                                    <?php if (!empty($cm['previous_position'])): ?>
                                    <span class="text-muted small"> · From: <?php echo e($cm['previous_position']); ?></span>
                                    <?php endif; ?>
                                    <div class="small"><?php echo e($cm['new_position']); ?>
                                        <?php if (!empty($cm['new_branch_name'])): ?>
                                        <span class="text-muted">· <?php echo e($cm['new_branch_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div style="font-size:.72rem;color:var(--text-muted);"><?php echo formatDate($cm['effective_date']); ?></div>
                                    <?php
                                    $statusColors = ['Approved'=>'success','Rejected'=>'danger','Pending'=>'warning'];
                                    $statusIcons  = ['Approved'=>'fa-check-circle','Rejected'=>'fa-times-circle','Pending'=>'fa-clock'];
                                    $sc = $statusColors[$cm['approval_status']] ?? 'secondary';
                                    $si = $statusIcons[$cm['approval_status']] ?? 'fa-circle';
                                    ?>
                                    <span class="badge bg-<?php echo $sc; ?>" style="font-size:.65rem;"><i class="fas <?php echo $si; ?> me-1"></i><?php echo e($cm['approval_status']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-route" style="font-size:2.5rem;opacity:.2;"></i>
                    <p class="text-muted mt-3 mb-0">No career movements on record.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>



<style>
/* ── Quick Actions Section Headings (Req 6.2, 17.2 — 1.5rem, 24px margin-top) */
.quick-actions-section-heading {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--color-text-primary, #1C271B);
    margin-top: 1.5rem;
    margin-bottom: .75rem;
    padding-bottom: .4rem;
    border-bottom: 2px solid var(--color-border-light, #D1D5CE);
    letter-spacing: .01em;
}
.quick-actions-section-heading:first-child { margin-top: 0; }

/* ── Quick Action List-style Buttons (Req 6.3, 6.5 — primary for top priority) */
.quick-action-btn-list {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .5rem;
    min-height: 48px;   /* touch-friendly (Req 3.1) */
    font-size: .95rem;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    transition: all .2s;
}
.quick-action-btn-list i {
    font-size: 1rem;
    flex-shrink: 0;
}

/* Legacy icon-grid style (kept for backward compat if used elsewhere) */
.quick-action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: .5rem;
    padding: 1rem .5rem;
    border-radius: 14px;
    border: 2px solid var(--border-color);
    background: var(--bg-white);
    color: var(--text-dark);
    text-decoration: none;
    font-size: .78rem;
    font-weight: 600;
    text-align: center;
    transition: all .2s;
    height: 100%;
    min-height: 90px;
}
.quick-action-btn i {
    font-size: 1.4rem;
    color: var(--primary-blue);
    transition: transform .2s;
}
.quick-action-btn:hover {
    border-color: var(--primary-blue);
    background: rgba(67,104,254,.05);
    color: var(--primary-blue);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(67,104,254,.12);
}
.quick-action-btn:hover i { transform: scale(1.15); }

/* ── HR Supervisor live validation queue ─────────────────────────────────── */
.validation-queue-item {
    background: var(--bg-gray);
    border: 1px solid var(--color-border-light, #D1D5CE);
    border-radius: 10px;
    padding: .75rem .85rem;
}
.validation-queue-item .badge {
    flex-shrink: 0;
    white-space: normal;
    text-align: right;
}
.min-width-0 { min-width: 0; }

/* ── Timeline ─────────────────────────────────────────────────────────────── */
.timeline { position: relative; padding-left: 2rem; }
.timeline::before {
    content: '';
    position: absolute;
    left: .85rem;
    top: 0; bottom: 0;
    width: 2px;
    background: var(--border-color);
}
.timeline-item {
    position: relative;
    margin-bottom: 1.2rem;
}
.timeline-item:last-child { margin-bottom: 0; }
.timeline-icon {
    position: absolute;
    left: -2rem;
    top: .1rem;
    width: 28px; height: 28px;
    border-radius: 50%;
    background: var(--bg-white);
    border: 2px solid var(--border-color);
    display: flex; align-items: center; justify-content: center;
    z-index: 1;
}
.timeline-item.latest .timeline-icon {
    border-color: var(--primary-blue);
    background: rgba(67,104,254,.08);
}
.timeline-content {
    background: var(--bg-gray);
    border-radius: 10px;
    padding: .75rem 1rem;
    transition: background .2s;
}
.timeline-content:hover { background: rgba(67,104,254,.05); }

/* ── Unread notification item highlight ─────────────────────────────────── */
.notif-unread-item { background: rgba(67,104,254,.04); }

/* ── Cursor pointer ─────────────────────────────────────────────────────── */
.cursor-pointer { cursor: pointer; }

/* ── Evaluation status card ─────────────────────────────────────────────── */
.eval-status-summary {
    background: var(--bg-gray);
    border: 1px solid var(--color-border-light, #D1D5CE);
    border-radius: 12px;
    padding: 1.15rem;
}
.eval-status-summary-main {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}
.eval-status-copy {
    min-width: 0;
}
.eval-status-title {
    color: var(--text-dark);
    font-size: .98rem;
    font-weight: 800;
    line-height: 1.3;
    margin-bottom: .35rem;
}
.eval-status-meta {
    color: var(--text-muted);
    font-size: .78rem;
    line-height: 1.45;
    margin-bottom: .65rem;
}
.eval-status-score {
    flex: 0 0 auto;
    min-width: 76px;
    text-align: right;
}
.eval-score-value {
    font-size: 2rem;
    font-weight: 850;
    line-height: 1;
    margin-bottom: .25rem;
}
.eval-status-score .badge {
    font-size: .65rem;
}
.eval-status-action {
    margin-top: 1rem;
}

.eval-workflow-wrap {
    margin-top: 1rem;
}
.eval-workflow-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    color: var(--text-muted);
    font-size: .74rem;
    font-weight: 800;
    letter-spacing: 0;
    margin-bottom: .8rem;
}
.eval-workflow-head strong {
    color: var(--primary-blue);
    font-size: .8rem;
}
.eval-workflow {
    display: grid;
    grid-template-columns: repeat(var(--eval-step-count, 6), minmax(0, 1fr));
    position: relative;
    gap: .35rem;
}
.eval-workflow::before,
.eval-workflow::after {
    content: '';
    position: absolute;
    left: 18px;
    right: 18px;
    top: 17px;
    height: 3px;
    border-radius: 99px;
}
.eval-workflow::before {
    background: #dee2e6;
}
.eval-workflow::after {
    background: var(--primary-blue);
    width: var(--eval-progress, 0%);
    max-width: calc(100% - 36px);
    min-width: 0;
    right: auto;
    transition: width .4s ease;
}
.eval-step {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 0;
    text-align: center;
}
.eval-step-marker {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #f0f4eb;
    border: 2px solid #dee2e6;
    color: var(--text-muted);
    font-size: .72rem;
    font-weight: 800;
    margin-bottom: .45rem;
}
.eval-step.is-done .eval-step-marker {
    background: var(--primary-blue);
    border-color: var(--primary-blue);
    color: #ffffff;
}
.eval-step.is-current .eval-step-marker {
    background: #ffffff;
    border-color: var(--primary-blue);
    color: var(--primary-blue);
    box-shadow: 0 0 0 4px rgba(67,104,254,.12);
}
.eval-step.is-rejected .eval-step-marker {
    border-color: #dc3545;
    color: #dc3545;
    box-shadow: 0 0 0 4px rgba(220,53,69,.12);
}
.eval-step-label {
    color: var(--text-muted);
    font-size: .7rem;
    font-weight: 650;
    line-height: 1.25;
    max-width: 92px;
    overflow-wrap: anywhere;
}
.eval-step.is-done .eval-step-label,
.eval-step.is-current .eval-step-label {
    color: var(--text-dark);
}
.eval-step.is-current .eval-step-label {
    font-weight: 800;
}
.eval-workflow-bar {
    height: 5px;
    overflow: hidden;
    border-radius: 999px;
    background: #dee2e6;
    margin-top: .85rem;
}
.eval-workflow-bar span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: var(--primary-blue);
    transition: width .4s ease;
}
.eval-workflow-bar.is-rejected span {
    background: #dc3545;
}

@media (max-width: 575.98px) {
    .content-card-header .badge,
    .content-card-header .btn {
        min-height: 34px;
    }
    .eval-status-summary {
        padding: 1rem;
    }
    .eval-status-summary-main {
        flex-direction: column;
        gap: .85rem;
    }
    .eval-status-score {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        text-align: left;
        border-top: 1px solid var(--color-border-light, #D1D5CE);
        padding-top: .85rem;
    }
    .eval-score-value {
        font-size: 1.75rem;
        margin-bottom: 0;
    }
    .eval-status-action .btn {
        width: 100%;
        min-height: 42px;
    }
    .eval-workflow-head {
        margin-bottom: .9rem;
    }
    .eval-workflow {
        display: flex;
        flex-direction: column;
        gap: .85rem;
    }
    .eval-workflow::before,
    .eval-workflow::after {
        left: 17px;
        right: auto;
        top: 18px;
        width: 3px;
        height: calc(100% - 36px);
    }
    .eval-workflow::after {
        width: 3px;
        max-width: none;
        height: var(--eval-progress, 0%);
        max-height: calc(100% - 36px);
        transition: height .4s ease;
    }
    .eval-step {
        display: grid;
        grid-template-columns: 36px minmax(0, 1fr);
        align-items: center;
        column-gap: .75rem;
        text-align: left;
    }
    .eval-step-marker {
        margin-bottom: 0;
    }
    .eval-step-label {
        max-width: none;
        font-size: .82rem;
        line-height: 1.3;
    }
    .eval-workflow-bar {
        display: none;
    }
}
</style>

<script>
// Live clock
function updateClock() {
    const now = new Date();
    let h = now.getHours(), m = now.getMinutes();
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    const el = document.getElementById('live-time');
    if (el) el.textContent = `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')} ${ampm}`;
}
setInterval(updateClock, 1000);

// Keep the HR Supervisor validation queue live without refreshing the dashboard.
(function startValidationQueueRefresh() {
    const queueCard = document.querySelector('[data-validation-queue-card]');
    if (!queueCard || queueCard.dataset.refreshStarted === '1') {
        return;
    }
    queueCard.dataset.refreshStarted = '1';

    let busy = false;
    const refresh = () => {
        if (busy || document.hidden) {
            return;
        }

        busy = true;
        fetch(window.location.href, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            cache: 'no-store'
        })
        .then(response => response.text())
        .then(html => {
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const current = document.querySelector('[data-validation-queue-card]');
            const next = doc.querySelector('[data-validation-queue-card]');
            if (current && next) {
                current.replaceWith(next);
            }
        })
        .catch(() => {})
        .finally(() => {
            busy = false;
        });
    };

    setInterval(refresh, 10000);
})();
</script>

<?php require_once '../includes/footer.php'; ?>
