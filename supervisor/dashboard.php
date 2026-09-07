<?php
$page_title = 'Supervisor Dashboard';
require_once '../includes/session-check.php';
checkRole(['HR Supervisor']);
require_once '../includes/header.php';

// Fetch stats
$pending_validations = $conn->query("SELECT COUNT(*) as c 
    FROM evaluations ev 
    INNER JOIN employees e ON ev.employee_id = e.employee_id 
    WHERE ev.status IN ('Pending Supervisor', 'Pending HR Consolidation')
      AND e.is_active = 1 
      AND e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)")->fetch_assoc()['c'];

$validated_month = $conn->query("SELECT COUNT(*) as c FROM evaluations WHERE endorsed_by = {$_SESSION['user_id']} AND MONTH(endorsed_date) = MONTH(CURRENT_DATE()) AND YEAR(endorsed_date) = YEAR(CURRENT_DATE())")->fetch_assoc()['c'];

$total_employees = $conn->query("SELECT COUNT(*) as c FROM employees WHERE is_active = 1 AND employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)")->fetch_assoc()['c'];

$overdue_count = $conn->query("SELECT COUNT(*) as c
    FROM evaluations ev
    INNER JOIN employees e ON ev.employee_id = e.employee_id
    WHERE ev.status IN ('Pending Supervisor', 'Pending HR Consolidation')
      AND e.is_active = 1
      AND DATEDIFF(CURRENT_DATE(), DATE(ev.submitted_date)) >= 7
      AND e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)")->fetch_assoc()['c'];

$low_score_count = $conn->query("SELECT COUNT(*) as c
    FROM evaluations ev
    INNER JOIN employees e ON ev.employee_id = e.employee_id
    WHERE ev.status IN ('Pending Supervisor', 'Pending HR Consolidation')
      AND e.is_active = 1
      AND ((ev.total_score IS NOT NULL AND ev.total_score < 2) OR ev.performance_level = 'Needs Improvement')
      AND e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)")->fetch_assoc()['c'];

$oldest_days = (int)($conn->query("SELECT COALESCE(MAX(DATEDIFF(CURRENT_DATE(), DATE(ev.submitted_date))), 0) as d
    FROM evaluations ev
    INNER JOIN employees e ON ev.employee_id = e.employee_id
    WHERE ev.status IN ('Pending Supervisor', 'Pending HR Consolidation')
      AND e.is_active = 1
      AND e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)")->fetch_assoc()['d'] ?? 0);

$validated_total = $conn->query("SELECT COUNT(*) as c FROM evaluations WHERE endorsed_by = {$_SESSION['user_id']}")->fetch_assoc()['c'];

// ── Non-Regular Watchlist ────────────────────────────────────────────────────
$watchlist_departments = $conn->query("SELECT department_id, department_name FROM departments WHERE is_active = 1 AND deleted_at IS NULL ORDER BY department_name");
$expiring_staff = getExpiringNonRegularEmployees($conn, 60);
$expiring_count = count($expiring_staff);
$overdue_count_watchlist  = count(array_filter($expiring_staff, fn($r) => $r['urgency'] === 'overdue'));
$critical_count_watchlist = count(array_filter($expiring_staff, fn($r) => $r['urgency'] === 'critical'));

$pending_rows = [];
$pending_result = $conn->query("SELECT ev.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name,
    e.job_title, e.profile_picture, et.template_name,
    u.full_name as submitted_by_name
    FROM evaluations ev
    INNER JOIN employees e ON ev.employee_id = e.employee_id
    LEFT JOIN users u ON ev.submitted_by = u.user_id
    LEFT JOIN evaluation_templates et ON ev.template_id = et.template_id
    WHERE ev.status IN ('Pending Supervisor', 'Pending HR Consolidation')
      AND e.is_active = 1
      AND e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)
    ORDER BY ev.submitted_date DESC");
while ($row = $pending_result->fetch_assoc()) {
    $pending_rows[] = $row;
}

$pending_groups = [];
foreach ($pending_rows as $row) {
    $employeeId = (int) ($row['employee_id'] ?? 0);
    if ($employeeId <= 0) {
        continue;
    }
    if (!isset($pending_groups[$employeeId])) {
        $pending_groups[$employeeId] = [
            'employee_id' => $employeeId,
            'employee_name' => $row['employee_name'] ?? '',
            'profile_picture' => $row['profile_picture'] ?? '',
            'submitted_by_name' => $row['submitted_by_name'] ?? '',
            'evaluations' => [],
        ];
    }
    $pending_groups[$employeeId]['evaluations'][] = $row;
}
$pending_groups = array_values($pending_groups);
$queue_preview_groups = array_slice($pending_groups, 0, 5);
$queue_employee_count = count($pending_groups);

ensureOrganizationEvaluationPackageSchema($conn);
syncPendingOrganizationPackageGovernanceApprovers($conn);
syncWaitingOrganizationPackages($conn);
$user_id_pkg = (int) ($_SESSION['user_id'] ?? 0);
$reviewer_match_pkg = organizationPackageReviewerMatchSql('rs');

$pending_packages = [];
if ($user_id_pkg > 0) {
    $pkg_stmt = $conn->prepare("SELECT DISTINCT ep.*, d.department_name, et.template_name, rs.package_route_step_id, rs.step_label, rs.step_type, rs.action_status
        FROM evaluation_packages ep
        JOIN evaluation_package_route_steps rs ON rs.package_id = ep.package_id
        JOIN departments d ON d.department_id = ep.department_id
        JOIN evaluation_templates et ON et.template_id = ep.template_id
        WHERE $reviewer_match_pkg
          AND (
              (rs.step_order = 1 AND rs.action_status IN ('Pending', 'Waiting'))
              OR (rs.step_order > 1 AND rs.action_status = 'Pending')
          )
          AND ep.status <> 'Approved and Applied'
        ORDER BY ep.updated_at DESC");
    $pkg_stmt->bind_param('ii', $user_id_pkg, $user_id_pkg);
    $pkg_stmt->execute();
    $pending_packages = $pkg_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $pkg_stmt->close();
}
$pending_pkg_count = count($pending_packages);
?>

<style>
    .supervisor-dashboard .approval-list {
        padding: 15px;
    }

    .supervisor-dashboard .approval-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 15px;
        background: #fff;
        border: 1px solid #f0f0f0;
        border-radius: 12px;
        margin-bottom: 12px;
        transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .supervisor-dashboard .approval-item:hover {
        transform: translateX(5px);
        border-color: var(--primary-light);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    }

    .supervisor-dashboard .approval-item .emp-info {
        display: flex;
        align-items: center;
        gap: 12px;
        flex: 1;
        min-width: 0;
    }

    .supervisor-dashboard .approval-item .avatar-circle {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        background: rgba(41, 67, 6, 0.05);
        color: var(--primary-blue);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        flex-shrink: 0;
    }

    .supervisor-dashboard .approval-item .details {
        min-width: 0;
    }

    .supervisor-dashboard .approval-item .details h6 {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 700;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .supervisor-dashboard .approval-item .details span {
        color: var(--text-muted);
        display: block;
        font-size: 0.75rem;
    }

    .supervisor-dashboard .approval-item .score-meter {
        width: 140px;
        flex-shrink: 0;
    }

    .supervisor-dashboard .approval-item .score-val {
        display: block;
        font-size: 0.85rem;
        font-weight: 700;
        margin-bottom: 4px;
    }

    .supervisor-dashboard .approval-item .status-meta {
        color: var(--text-muted);
        flex-shrink: 0;
        font-size: 0.75rem;
        text-align: right;
    }

    .supervisor-dashboard .approval-item .btn-review {
        border-radius: 20px;
        flex-shrink: 0;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 6px 18px;
    }

    .supervisor-dashboard .approval-group-wrap {
        background: #fff;
        border: 1px solid #f0f0f0;
        border-radius: 12px;
        margin-bottom: 12px;
        overflow: hidden;
    }

    .supervisor-dashboard .approval-group-header {
        align-items: center;
        cursor: pointer;
        display: flex;
        gap: 16px;
        justify-content: space-between;
        padding: 15px;
        transition: background 0.2s ease;
    }

    .supervisor-dashboard .approval-group-header:hover {
        background: #fbfcf8;
    }

    .supervisor-dashboard .approval-group-header[aria-expanded="true"] .group-chevron {
        transform: rotate(180deg);
    }

    .supervisor-dashboard .group-chevron {
        transition: transform 0.2s ease;
    }

    .supervisor-dashboard .approval-group-entries {
        background: #f8faf5;
        border-top: 1px solid #eef2e8;
        padding: 0 15px 12px;
    }

    .supervisor-dashboard .approval-group-entry {
        align-items: center;
        background: #fff;
        border: 1px solid #eef2e8;
        border-radius: 10px;
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        justify-content: space-between;
        margin-top: 10px;
        padding: 12px;
    }

    .supervisor-dashboard .approval-group-entry .entry-template {
        font-size: 0.82rem;
        font-weight: 700;
        min-width: 0;
    }

    .supervisor-dashboard .empty-state-card {
        color: var(--text-muted);
        padding: 42px 20px;
        text-align: center;
    }

    .supervisor-dashboard .empty-state-card i {
        display: block;
        font-size: 2.6rem;
        margin-bottom: 14px;
        opacity: 0.2;
    }

    .supervisor-dashboard .workflow-card {
        min-height: 100%;
    }

    .supervisor-dashboard .workflow-step {
        display: grid;
        grid-template-columns: 38px minmax(0, 1fr);
        gap: 12px;
        padding: 12px 0;
    }

    .supervisor-dashboard .workflow-step + .workflow-step {
        border-top: 1px solid #f0f4eb;
    }

    .supervisor-dashboard .workflow-step .step-icon {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(41, 67, 6, 0.08);
        color: var(--primary-blue);
    }

    @media (max-width: 576px) {
        /* Stat cards — 2 per row on small mobile */
        .supervisor-dashboard .stat-card {
            padding: 12px 10px;
        }
        .supervisor-dashboard .stat-card .stat-value {
            font-size: 1.5rem;
        }
        .supervisor-dashboard .stat-card .stat-label {
            font-size: 0.65rem;
        }
        .supervisor-dashboard .stat-card .stat-icon {
            font-size: 1.2rem;
        }
        /* Hero header quick actions stacked */
        .supervisor-dashboard .page-hero .d-flex.flex-column.align-items-end {
            align-items: stretch !important;
        }
        .supervisor-dashboard .page-hero .d-flex.gap-2.flex-wrap.justify-content-end {
            justify-content: flex-start !important;
        }
    }

    @media (max-width: 768px) {
        .supervisor-dashboard .approval-item,
        .supervisor-dashboard .approval-group-header {
            align-items: stretch;
            flex-direction: column;
        }

        .supervisor-dashboard .approval-item .status-meta,
        .supervisor-dashboard .approval-group-header .status-meta {
            text-align: left;
        }

        .supervisor-dashboard .approval-item .btn-review,
        .supervisor-dashboard .approval-group-header .btn-review {
            width: 100%;
            text-align: center;
        }

        .supervisor-dashboard .approval-group-entry {
            flex-direction: column;
            align-items: stretch;
        }

        /* Score meter visible on mobile too */
        .supervisor-dashboard .approval-item .score-meter {
            display: block !important;
            width: 100%;
        }

        /* Group chevron always visible */
        .supervisor-dashboard .group-chevron {
            display: inline-block !important;
        }
    }
</style>

<script>
// Supervisor Dashboard — Queue Group Accordion: Update Show/Hide label
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.supervisor-dashboard .approval-group-wrap').forEach(function (wrap) {
        const collapseEl = wrap.querySelector('.collapse');
        const btn = wrap.querySelector('[data-bs-toggle="collapse"].btn-review');
        if (!collapseEl || !btn) return;
        collapseEl.addEventListener('show.bs.collapse', function () {
            btn.querySelector('i').className = 'fas fa-chevron-up group-chevron me-1';
            const label = btn.querySelector('span.toggle-label') || btn;
            if (label !== btn) label.textContent = 'Hide';
            else btn.textContent = btn.textContent.replace('Show', 'Hide');
        });
        collapseEl.addEventListener('hide.bs.collapse', function () {
            btn.querySelector('i').className = 'fas fa-chevron-down group-chevron me-1';
            const label = btn.querySelector('span.toggle-label') || btn;
            if (label !== btn) label.textContent = 'Show';
            else btn.textContent = btn.textContent.replace('Hide', 'Show');
        });
    });
});
</script>

<div class="supervisor-dashboard">
<div class="page-hero fadeup">
    <!-- Top Row: Greeting + Date + Quick Actions -->
    <div class="d-flex flex-wrap align-items-start justify-content-between mb-4 gap-3">
        <div>
            <div class="mb-1" style="color:#FFD97D;font-size:.88rem;font-weight:600;letter-spacing:.3px;"><?php echo getGreeting($_SESSION['full_name'] ?? ''); ?></div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">HR Supervisor &middot; Dashboard</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-check-double me-2" style="color:#BD9414;"></i>Supervisor Overview</h4>
        </div>
        <div class="d-flex flex-column align-items-end gap-2">
            <div style="color:rgba(255,255,255,.6);font-size:.8rem;">
                <i class="fas fa-sync-alt me-1"></i>Data as of <?php echo date('F d, Y'); ?>
            </div>
            <!-- Quick Actions -->
            <div class="d-flex gap-2 flex-wrap justify-content-end">
                <a href="pending-endorsements.php" class="btn btn-sm px-3 fw-semibold" style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.25);border-radius:20px;font-size:.78rem;backdrop-filter:blur(4px);">
                    <i class="fas fa-inbox me-1"></i>Open Queue
                </a>
                <a href="evaluation-history.php" class="btn btn-sm px-3 fw-semibold" style="background:rgba(255,255,255,.1);color:rgba(255,255,255,.8);border:1px solid rgba(255,255,255,.15);border-radius:20px;font-size:.78rem;">
                    <i class="fas fa-history me-1"></i>View History
                </a>
                <?php if ($overdue_count > 0): ?>
                <a href="pending-endorsements.php?attention=overdue" class="btn btn-sm px-3 fw-semibold" style="background:rgba(220,53,69,.35);color:#fff;border:1px solid rgba(220,53,69,.4);border-radius:20px;font-size:.78rem;">
                    <i class="fas fa-triangle-exclamation me-1"></i><?php echo $overdue_count; ?> Overdue
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Stat Cards Row -->
    <div class="row g-3 mb-3">
        <!-- Team Evaluation Packages -->
        <div class="col-6 col-md-3">
            <a href="<?php echo BASE_URL; ?>/employee/team-evaluation-packages.php" class="stat-card text-decoration-none d-block">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo $pending_pkg_count > 0 ? $pending_pkg_count : $pending_validations; ?></div>
                        <div class="stat-label">Team Packages</div>
                    </div>
                    <i class="fas fa-layer-group stat-icon" style="color:#ffc107;"></i>
                </div>
                <?php if ($pending_pkg_count > 0): ?>
                <div class="mt-2" style="font-size:.72rem;color:rgba(255,255,255,.55);">
                    <i class="fas fa-layer-group me-1"></i><?php echo $pending_pkg_count; ?> package<?php echo $pending_pkg_count === 1 ? '' : 's'; ?> awaiting review
                </div>
                <?php elseif ($queue_employee_count > 0): ?>
                <div class="mt-2" style="font-size:.72rem;color:rgba(255,255,255,.55);">
                    <i class="fas fa-users me-1"></i><?php echo $queue_employee_count; ?> employee<?php echo $queue_employee_count === 1 ? '' : 's'; ?> in queue
                </div>
                <?php endif; ?>
            </a>
        </div>
        <!-- Overdue (7+ days) -->
        <div class="col-6 col-md-3">
            <a href="pending-endorsements.php?attention=overdue" class="stat-card text-decoration-none d-block">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value" style="<?php echo $overdue_count > 0 ? 'color:#ff6b6b;' : ''; ?>"><?php echo $overdue_count; ?></div>
                        <div class="stat-label">Overdue (7+ Days)</div>
                    </div>
                    <i class="fas fa-hourglass-end stat-icon" style="color:#dc3545;"></i>
                </div>
                <div class="mt-2" style="font-size:.72rem;color:rgba(255,255,255,.55);">
                    <?php if ($oldest_days > 0): ?>
                        <i class="fas fa-clock me-1"></i>Oldest: <?php echo $oldest_days; ?> day<?php echo $oldest_days === 1 ? '' : 's'; ?> ago
                    <?php else: ?>
                        <i class="fas fa-check me-1"></i>No overdue items
                    <?php endif; ?>
                </div>
            </a>
        </div>
        <!-- Low Score -->
        <div class="col-6 col-md-3">
            <a href="pending-endorsements.php?attention=low_score" class="stat-card text-decoration-none d-block">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value" style="<?php echo $low_score_count > 0 ? 'color:#ff6b6b;' : ''; ?>"><?php echo $low_score_count; ?></div>
                        <div class="stat-label">Low Score (&lt; 2.0)</div>
                    </div>
                    <i class="fas fa-arrow-trend-down stat-icon" style="color:#fd7e14;"></i>
                </div>
                <div class="mt-2" style="font-size:.72rem;color:rgba(255,255,255,.55);">
                    <i class="fas fa-exclamation-circle me-1"></i><?php echo $low_score_count > 0 ? 'Needs immediate review' : 'All scores acceptable'; ?>
                </div>
            </a>
        </div>
        <!-- Validated This Month -->
        <div class="col-6 col-md-3">
            <a href="evaluation-history.php" class="stat-card text-decoration-none d-block">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo $validated_month; ?></div>
                        <div class="stat-label">Validated This Month</div>
                    </div>
                    <i class="fas fa-check-circle stat-icon" style="color:#28a745;"></i>
                </div>
                <div class="mt-2" style="font-size:.72rem;color:rgba(255,255,255,.55);">
                    <i class="fas fa-history me-1"></i><?php echo $validated_total; ?> total all-time
                </div>
            </a>
        </div>
    </div>

    <!-- Urgency / Progress Bar -->
    <?php
    $total_action_items = $pending_validations + $validated_month;
    $progress_pct = $total_action_items > 0 ? min(100, round(($validated_month / $total_action_items) * 100)) : 100;
    $progress_color = $progress_pct >= 80 ? '#28a745' : ($progress_pct >= 40 ? '#ffc107' : '#dc3545');
    ?>
    <div style="background:rgba(255,255,255,.08);border-radius:8px;padding:10px 14px;display:flex;align-items:center;gap:14px;">
        <div style="flex:1;min-width:0;">
            <div style="font-size:.72rem;color:rgba(255,255,255,.55);font-weight:700;letter-spacing:.4px;text-transform:uppercase;margin-bottom:5px;">
                Monthly Completion Rate &mdash; <?php echo date('F Y'); ?>
            </div>
            <div style="height:6px;background:rgba(255,255,255,.12);border-radius:99px;overflow:hidden;">
                <div style="height:100%;width:<?php echo $progress_pct; ?>%;background:<?php echo $progress_color; ?>;border-radius:99px;transition:width .6s ease;"></div>
            </div>
        </div>
        <div style="text-align:right;flex-shrink:0;">
            <div style="font-size:1.1rem;font-weight:800;color:#fff;"><?php echo $progress_pct; ?>%</div>
            <div style="font-size:.68rem;color:rgba(255,255,255,.5);"><?php echo $validated_month; ?> of <?php echo $total_action_items; ?> processed</div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="chart-card h-100">
            <div class="cc-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0"><i class="fas fa-layer-group me-2"></i>Team Evaluation Packages</h5>
                <div class="d-flex align-items-center gap-2">
                    <?php if ($pending_pkg_count > 0): ?>
                        <span class="badge bg-light text-muted border"><?php echo number_format($pending_pkg_count); ?> package<?php echo $pending_pkg_count === 1 ? '' : 's'; ?></span>
                    <?php elseif ($queue_employee_count > 0): ?>
                        <span class="badge bg-light text-muted border"><?php echo number_format($queue_employee_count); ?> employee<?php echo $queue_employee_count === 1 ? '' : 's'; ?></span>
                    <?php endif; ?>
                    <a href="<?php echo BASE_URL; ?>/employee/team-evaluation-packages.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">View All</a>
                </div>
            </div>
            <div class="cc-body p-0">
                <div class="approval-list">
                    <?php if (!empty($pending_packages)): ?>
                        <?php foreach ($pending_packages as $pkg):
                            $is_waiting = ($pkg['action_status'] === 'Waiting');
                        ?>
                            <div class="approval-item">
                                <div class="emp-info">
                                    <div class="avatar-circle bg-primary-subtle text-primary fw-bold d-flex align-items-center justify-content-center" style="width:42px;height:42px;border-radius:50%;">
                                        <i class="fas fa-layer-group"></i>
                                    </div>
                                    <div class="details">
                                        <h6 class="mb-0 fw-bold"><?php echo e($pkg['department_name']); ?> Package</h6>
                                        <span class="small text-muted"><?php echo e($pkg['template_name']); ?> &middot; Step: <?php echo e($pkg['step_label']); ?></span>
                                    </div>
                                </div>
                                <div class="status-meta d-none d-sm-block text-end">
                                    <div class="fw-bold text-dark"><?php echo formatDate($pkg['created_at']); ?></div>
                                    <?php if ($is_waiting): ?>
                                        <div class="x-small text-secondary fw-bold"><i class="fas fa-user-clock me-1"></i>Waiting for Team Self-Ratings</div>
                                    <?php else: ?>
                                        <div class="x-small text-warning fw-bold"><i class="fas fa-clock me-1"></i><?php echo e($pkg['status']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($is_waiting): ?>
                                    <a href="<?php echo BASE_URL; ?>/employee/team-evaluation-packages.php" class="btn btn-outline-primary btn-sm px-3">View Progress</a>
                                <?php else: ?>
                                    <a href="<?php echo BASE_URL; ?>/employee/team-evaluation-packages.php" class="btn btn-primary btn-sm btn-review px-3">Review Package</a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php elseif (empty($queue_preview_groups)): ?>
                        <div class="empty-state-card">
                            <i class="fas fa-layer-group"></i>
                            <p class="mb-0">All team evaluation packages have been processed.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($queue_preview_groups as $group):
                            $evaluations = $group['evaluations'];
                            $evalCount = count($evaluations);
                            $name_parts = preg_split('/\s+/', trim($group['employee_name'] ?? ''));
                            $initials = strtoupper(substr($name_parts[0] ?? 'U', 0, 1) . substr($name_parts[1] ?? '', 0, 1));
                            $groupCollapseId = 'dashQueue' . (int) $group['employee_id'];
                        ?>
                            <?php if ($evalCount === 1):
                                $row = $evaluations[0];
                                $score = (float) ($row['total_score'] ?? 0);
                            ?>
                                <div class="approval-item">
                                    <div class="emp-info">
                                        <div class="avatar-circle"><?php echo e($initials ?: 'U'); ?></div>
                                        <div class="details">
                                            <h6><?php echo e($group['employee_name']); ?></h6>
                                            <span><?php echo e($row['template_name'] ?? 'Evaluation'); ?> · Submitted by <?php echo e($row['submitted_by_name']); ?></span>
                                        </div>
                                    </div>
                                    <div class="score-meter d-none d-md-block">
                                        <span class="score-val"><?php echo number_format($score, 2); ?> / 4</span>
                                        <div class="progress" style="height: 4px;">
                                            <div class="progress-bar <?php echo ($score >= 3) ? 'bg-success' : (($score >= 2) ? 'bg-primary' : 'bg-warning'); ?>" style="width: <?php echo min(100, max(0, ($score / 4) * 100)); ?>%;"></div>
                                        </div>
                                    </div>
                                    <div class="status-meta d-none d-sm-block">
                                        <div class="fw-bold text-dark"><?php echo formatDate($row['submitted_date']); ?></div>
                                        <div class="x-small">Pending Validation</div>
                                    </div>
                                    <a href="<?php echo BASE_URL; ?>/supervisor/pending-endorsements.php" class="btn btn-primary btn-review">Review</a>
                                </div>
                            <?php else: ?>
                                <div class="approval-group-wrap">
                                    <div class="approval-group-header"
                                         role="button"
                                         tabindex="0"
                                         data-bs-toggle="collapse"
                                         data-bs-target="#<?php echo e($groupCollapseId); ?>"
                                         aria-expanded="false"
                                         aria-controls="<?php echo e($groupCollapseId); ?>">
                                        <div class="emp-info">
                                            <div class="avatar-circle"><?php echo e($initials ?: 'U'); ?></div>
                                            <div class="details">
                                                <h6><?php echo e($group['employee_name']); ?></h6>
                                                <span><span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?php echo $evalCount; ?> evaluations pending</span></span>
                                            </div>
                                        </div>
                                        <div class="status-meta d-none d-sm-block">
                                            <div class="fw-bold text-dark">Pending Validation</div>
                                            <div class="x-small">Click to expand</div>
                                        </div>
                                        <button type="button" class="btn btn-outline-primary btn-review" data-bs-toggle="collapse" data-bs-target="#<?php echo e($groupCollapseId); ?>" aria-expanded="false" onclick="event.stopPropagation();">
                                            <i class="fas fa-chevron-down group-chevron me-1"></i>Show
                                        </button>
                                    </div>
                                    <div class="collapse approval-group-entries" id="<?php echo e($groupCollapseId); ?>">
                                        <?php foreach ($evaluations as $row):
                                            $score = (float) ($row['total_score'] ?? 0);
                                        ?>
                                            <div class="approval-group-entry">
                                                <div class="flex-grow-1 min-w-0">
                                                    <div class="entry-template"><?php echo e($row['template_name'] ?? 'Evaluation'); ?></div>
                                                    <div class="small text-muted">
                                                        <?php echo e($row['evaluation_type'] ?? 'Annual'); ?> ·
                                                        Submitted <?php echo formatDate($row['submitted_date']); ?> ·
                                                        <?php echo e($row['submitted_by_name']); ?>
                                                    </div>
                                                </div>
                                                <div class="score-meter">
                                                    <span class="score-val"><?php echo $row['total_score']; ?> / 4</span>
                                                    <div class="progress" style="height: 4px;">
                                                        <div class="progress-bar <?php echo ($score >= 3) ? 'bg-success' : (($score >= 2) ? 'bg-primary' : 'bg-warning'); ?>" style="width: <?php echo min(100, max(0, ($score / 4) * 100)); ?>%;"></div>
                                                    </div>
                                                </div>
                                                <a href="<?php echo BASE_URL; ?>/supervisor/pending-endorsements.php" class="btn btn-primary btn-sm btn-review">Review</a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <div class="text-center pb-3">
                            <a href="<?php echo BASE_URL; ?>/supervisor/pending-endorsements.php" class="text-decoration-none small text-muted hover-primary">
                                View all pending validations <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php /* ── Non-Regular Personnel Watchlist (Supervisor) ── */ ?>
<style>
    /* ── Watchlist Card ─────────────────────────────────────────── */
    .watchlist-card {
        border-radius: 16px;
        border: none;
        overflow: visible; /* must NOT clip — dropdown menus escape the card */
        margin-bottom: 24px;
        box-shadow: 0 4px 20px rgba(4,61,7,.10);
    }
    .watchlist-header {
        background: linear-gradient(135deg, #043d07 0%, #074604 100%);
        color: #fff;
        padding: 18px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        border-radius: 16px 16px 0 0;
    }
    .watchlist-body {
        background: #fff;
        border-radius: 0 0 16px 16px;
        overflow: visible;
    }
    .watchlist-header-left {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        min-width: 0;
    }
    .watchlist-header h5 {
        margin: 0;
        font-weight: 700;
        font-size: 1rem;
        white-space: nowrap;
    }
    .watchlist-badge-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 700;
        white-space: nowrap;
    }
    .wb-overdue  { background: rgba(220,53,69,.25);  color: #ff6b7a; border: 1px solid rgba(220,53,69,.4); }
    .wb-critical { background: rgba(255,152,0,.2);   color: #ffb74d; border: 1px solid rgba(255,152,0,.4); }
    .wb-ok       { background: rgba(40,167,69,.15);  color: #66bb6a; border: 1px solid rgba(40,167,69,.3); }
    .watchlist-view-btn {
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 8px;
        font-size: 0.78rem;
        font-weight: 600;
        color: #fff;
        background: rgba(255,255,255,.15);
        border: 1px solid rgba(255,255,255,.25);
        text-decoration: none;
        white-space: nowrap;
        transition: background .15s;
    }
    .watchlist-view-btn:hover { background: rgba(255,255,255,.25); color: #fff; }
    .watchlist-department-select { width: min(100%, 260px); }
    .watchlist-empty {
        padding: 52px 24px;
        text-align: center;
        color: #8094ae;
    }
    .watchlist-empty i {
        font-size: 2.5rem;
        color: #d1fae5;
        margin-bottom: 12px;
        display: block;
    }

    /* ── Desktop Row ────────────────────────────────────────────── */
    .wl-row {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 14px 20px;
        border-bottom: 1px solid #f0f3f8;
        transition: background .15s;
        position: relative;
        z-index: 1;
    }
    .wl-row:last-child { border-bottom: none; }
    .wl-row:hover, .wl-row:focus-within { background: #f8faff; z-index: 100; }
    .wl-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
    .wl-info { flex: 1; min-width: 0; }
    .wl-name { font-weight: 700; font-size: 0.88rem; color: #1e2d40; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .wl-sub  { font-size: 0.73rem; color: #8094ae; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .wl-countdown { flex-shrink: 0; text-align: right; }
    .wl-days { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 20px; font-size: 0.73rem; font-weight: 700; white-space: nowrap; }
    .wl-overdue  { background: #fff1f2; color: #dc3545; border: 1px solid #f8c4c8; }
    .wl-critical { background: #fff8e1; color: #e65100; border: 1px solid #ffe0b2; }
    .wl-warning  { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
    .wl-upcoming { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
    .wl-actions { flex-shrink: 0; position: relative; }

    /* Mobile card sections — hidden on desktop */
    .wl-row-top    { display: none; }
    .wl-row-footer { display: none; }

    /* ── Mobile Card Layout (≤ 767px) ──────────────────────────── */
    @media (max-width: 767.98px) {
        .watchlist-header {
            flex-direction: column;
            align-items: stretch;
            padding: 14px 16px;
            gap: 10px;
        }
        .watchlist-header-left { gap: 6px; }
        .watchlist-header h5   { font-size: 0.9rem; white-space: normal; }
        .watchlist-view-btn {
            width: 100%;
            justify-content: center;
            padding: 9px 14px;
            font-size: 0.82rem;
            border-radius: 10px;
        }
        .watchlist-empty { padding: 36px 16px; }

        .watchlist-body { background: #f5f7f5; padding: 12px; overflow: visible; }

        .wl-row {
            flex-direction: column;
            align-items: stretch;
            gap: 0;
            padding: 0;
            border-bottom: none;
            border-radius: 14px;
            background: #fff;
            margin-bottom: 10px;
            box-shadow: 0 2px 10px rgba(4,61,7,.07);
            overflow: visible;
            position: relative;
            z-index: 1;
            transition: box-shadow .18s;
        }
        .wl-row:last-child { margin-bottom: 0; }
        .wl-row:hover, .wl-row:focus-within { background: #fff; box-shadow: 0 4px 16px rgba(4,61,7,.13); z-index: 1050 !important; }
        
        .wl-row-top {
            display: flex;
            border-radius: 14px 14px 0 0;
            overflow: hidden;
            align-items: center;
            gap: 12px;
            padding: 13px 14px 10px;
        }
        .wl-row-footer {
            display: flex;
            border-radius: 0 0 14px 14px;
            overflow: visible;
            position: relative;
            align-items: center;
            justify-content: space-between;
            padding: 8px 14px 12px;
            gap: 8px;
            border-top: 1px solid #f0f3f0;
            background: #fafcfa;
        }
        .wl-row-footer .dropdown { position: relative; }
        .wl-row-footer .dropdown-menu { z-index: 1060 !important; margin-top: 4px; }

        .wl-row > .wl-avatar,
        .wl-row > .wl-info,
        .wl-row > .wl-countdown,
        .wl-row > .wl-actions { display: none !important; }

        .wl-row-top .wl-avatar {
            display: block;
            width: 46px;
            height: 46px;
            border-radius: 50%;
            border: 2px solid #e3ede3;
            object-fit: cover;
            flex-shrink: 0;
        }
        .wl-row-top .wl-info { display: block; flex: 1; min-width: 0; }
        .wl-row-top .wl-name {
            font-size: 0.9rem;
            font-weight: 700;
            color: #1e2d40;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .wl-row-top .wl-sub {
            font-size: 0.72rem;
            color: #8094ae;
            white-space: normal;
            line-height: 1.45;
            margin-top: 3px;
        }

        .wl-row-footer .wl-countdown { text-align: left; flex: 1; min-width: 0; }
        .wl-row-footer .wl-days      { font-size: 0.72rem; padding: 4px 10px; }
        .wl-row-footer .wl-actions   { flex-shrink: 0; }
    }
</style>
<div class="watchlist-card">
    <div class="watchlist-header">
        <div class="watchlist-header-left">
            <h5><i class="fas fa-exclamation-triangle me-2" style="color:#FFD97D"></i>Non-Regular Personnel Watchlist</h5>
            <?php if ($overdue_count_watchlist > 0): ?>
                <span class="watchlist-badge-pill wb-overdue"><i class="fas fa-times-circle"></i><?php echo $overdue_count_watchlist; ?> Overdue</span>
            <?php endif; ?>
            <?php if ($critical_count_watchlist > 0): ?>
                <span class="watchlist-badge-pill wb-critical"><i class="fas fa-fire"></i><?php echo $critical_count_watchlist; ?> Critical</span>
            <?php endif; ?>
            <?php if ($expiring_count === 0): ?>
                <span class="watchlist-badge-pill wb-ok"><i class="fas fa-check-circle"></i>All Clear</span>
            <?php endif; ?>
        </div>
        <a href="<?php echo BASE_URL; ?>/supervisor/employees.php" class="watchlist-view-btn">
            <i class="fas fa-users"></i>View All Employees
        </a>
    </div>
    <div class="px-3 py-2 border-bottom bg-light d-flex flex-wrap align-items-center gap-2">
        <label for="watchlist_department_filter" class="small fw-semibold text-muted mb-0">Department</label>
        <select id="watchlist_department_filter" class="form-select form-select-sm watchlist-department-select">
            <option value="0">All departments</option>
            <?php if ($watchlist_departments): while ($watchlist_department = $watchlist_departments->fetch_assoc()): ?>
                <option value="<?php echo (int) $watchlist_department['department_id']; ?>">
                    <?php echo e($watchlist_department['department_name']); ?>
                </option>
            <?php endwhile; endif; ?>
        </select>
    </div>
    <div class="watchlist-body">
        <?php if ($expiring_count === 0): ?>
            <div class="watchlist-empty">
                <i class="fas fa-shield-alt"></i>
                <div class="fw-bold mb-1" style="color:#1e2d40;">No Non-Regular Personnel Found</div>
                <small>No active non-regular personnel match the selected department.</small>
            </div>
        <?php else: ?>
            <?php foreach ($expiring_staff as $ws): ?>
                <?php
                    $d = (int)$ws['days_remaining'];
                    if ($d < 0)       { $dayLabel = 'Overdue by ' . abs($d) . 'd'; $dayClass = 'wl-overdue';  $icon = 'fa-times-circle'; }
                    elseif ($d === 0) { $dayLabel = 'Ends Today!';                 $dayClass = 'wl-overdue';  $icon = 'fa-exclamation-circle'; }
                    elseif ($d <= 14) { $dayLabel = 'Ends in ' . $d . 'd';         $dayClass = 'wl-critical'; $icon = 'fa-fire'; }
                    elseif ($d <= 30) { $dayLabel = 'Ends in ' . $d . 'd';         $dayClass = 'wl-warning';  $icon = 'fa-exclamation-triangle'; }
                    else              { $dayLabel = 'Ends in ' . $d . 'd';          $dayClass = 'wl-upcoming'; $icon = 'fa-calendar-alt'; }
                ?>
                <div class="wl-row" data-watchlist-department="<?php echo (int) ($ws['department_id'] ?? 0); ?>">
                    <img src="<?php echo getEmployeeAvatar($ws['profile_picture']); ?>" class="wl-avatar" alt="Avatar">
                    <div class="wl-info">
                        <div class="wl-name"><?php echo e($ws['last_name'] . ', ' . $ws['first_name']); ?></div>
                        <div class="wl-sub mt-1">
                            <?php echo renderEmploymentStatusBadge($ws['employment_status']); ?>
                            &nbsp;<?php echo e($ws['job_title'] ?? 'N/A'); ?>
                            <?php if (!empty($ws['branch_name'])): ?>
                                &nbsp;·&nbsp;<?php echo e($ws['branch_name']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="wl-countdown">
                        <span class="wl-days <?php echo $dayClass; ?>">
                            <i class="fas <?php echo $icon; ?>"></i><?php echo $dayLabel; ?>
                        </span>
                    </div>
                    <div class="wl-actions">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" data-bs-display="static" style="border-radius:8px;font-size:0.75rem;">
                                <i class="fas fa-bolt me-1"></i>Action
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/supervisor/view-employee.php?id=<?php echo $ws['employee_id']; ?>"><i class="fas fa-eye me-2 text-info"></i>View Profile</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/supervisor/edit-employee.php?id=<?php echo $ws['employee_id']; ?>"><i class="fas fa-edit me-2 text-primary"></i>Edit Record</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="wl-row-top">
                        <img src="<?php echo getEmployeeAvatar($ws['profile_picture']); ?>" class="wl-avatar" alt="Avatar">
                        <div class="wl-info">
                            <div class="wl-name"><?php echo e($ws['last_name'] . ', ' . $ws['first_name']); ?></div>
                            <div class="wl-sub">
                                <?php echo renderEmploymentStatusBadge($ws['employment_status']); ?>
                                <?php echo e($ws['job_title'] ?? 'N/A'); ?>
                                <?php if (!empty($ws['branch_name'])): ?>
                                    &nbsp;·&nbsp;<?php echo e($ws['branch_name']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="wl-row-footer">
                        <div class="wl-countdown">
                            <span class="wl-days <?php echo $dayClass; ?>">
                                <i class="fas <?php echo $icon; ?>"></i><?php echo $dayLabel; ?>
                            </span>
                        </div>
                        <div class="wl-actions">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" data-bs-display="static" style="border-radius:8px;font-size:0.75rem;">
                                    <i class="fas fa-bolt me-1"></i>Action
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/supervisor/view-employee.php?id=<?php echo $ws['employee_id']; ?>"><i class="fas fa-eye me-2 text-info"></i>View Profile</a></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/supervisor/edit-employee.php?id=<?php echo $ws['employee_id']; ?>"><i class="fas fa-edit me-2 text-primary"></i>Edit Record</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <div id="watchlist_filter_empty" class="watchlist-empty d-none">
                <i class="fas fa-filter"></i>
                <div class="fw-bold mb-1" style="color:#1e2d40;">No Matching Personnel</div>
                <small>No active non-regular personnel are assigned to this department.</small>
            </div>
        <?php endif; ?>
    </div>
    <!-- Pagination Container -->
    <div id="watchlist_pagination" class="d-flex justify-content-between align-items-center px-4 py-3 border-top bg-light d-none" style="border-radius: 0 0 16px 16px;">
        <div class="small fw-semibold text-muted" id="watchlist_page_info"></div>
        <div class="d-flex gap-2">
            <button id="watchlist_prev_btn" class="btn btn-sm btn-outline-success" style="border-radius: 8px; font-weight: 600; padding: 5px 12px; font-size: 0.78rem;">
                <i class="fas fa-chevron-left me-1"></i>Prev
            </button>
            <button id="watchlist_next_btn" class="btn btn-sm btn-outline-success" style="border-radius: 8px; font-weight: 600; padding: 5px 12px; font-size: 0.78rem;">
                Next<i class="fas fa-chevron-right ms-1"></i>
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const departmentFilter = document.getElementById('watchlist_department_filter');
    const prevBtn = document.getElementById('watchlist_prev_btn');
    const nextBtn = document.getElementById('watchlist_next_btn');
    
    if (!departmentFilter) return;

    let currentWatchlistPage = 1;
    const itemsPerPage = 8;

    function updateWatchlistPagination() {
        const selectedDepartment = departmentFilter.value;
        const allRows = Array.from(document.querySelectorAll('.wl-row[data-watchlist-department]'));
        
        // Filter rows based on department selection
        const matchingRows = allRows.filter(row => {
            return selectedDepartment === '0' || row.dataset.watchlistDepartment === selectedDepartment;
        });

        const totalMatching = matchingRows.length;
        const paginationContainer = document.getElementById('watchlist_pagination');
        const emptyState = document.getElementById('watchlist_filter_empty');
        
        if (emptyState) {
            emptyState.classList.toggle('d-none', totalMatching > 0);
        }
        
        if (totalMatching === 0) {
            allRows.forEach(row => row.hidden = true);
            if (paginationContainer) paginationContainer.classList.add('d-none');
            return;
        }
        
        const totalPages = Math.ceil(totalMatching / itemsPerPage);
        
        if (currentWatchlistPage > totalPages) {
            currentWatchlistPage = totalPages;
        }
        if (currentWatchlistPage < 1) {
            currentWatchlistPage = 1;
        }
        
        const startIndex = (currentWatchlistPage - 1) * itemsPerPage;
        const endIndex = Math.min(startIndex + itemsPerPage, totalMatching);
        
        allRows.forEach(row => row.hidden = true);
        
        matchingRows.forEach((row, index) => {
            if (index >= startIndex && index < endIndex) {
                row.hidden = false;
            }
        });
        
        if (paginationContainer) {
            if (totalMatching <= itemsPerPage) {
                paginationContainer.classList.add('d-none');
            } else {
                paginationContainer.classList.remove('d-none');
                
                const pageInfo = document.getElementById('watchlist_page_info');
                if (pageInfo) {
                    pageInfo.textContent = `Showing ${startIndex + 1}-${endIndex} of ${totalMatching}`;
                }
                
                if (prevBtn) prevBtn.disabled = (currentWatchlistPage === 1);
                if (nextBtn) nextBtn.disabled = (currentWatchlistPage === totalPages);
            }
        }
    }

    departmentFilter.addEventListener('change', function () {
        currentWatchlistPage = 1;
        updateWatchlistPagination();
    });

    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            if (currentWatchlistPage > 1) {
                currentWatchlistPage--;
                updateWatchlistPagination();
            }
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            currentWatchlistPage++;
            updateWatchlistPagination();
        });
    }

    // Run initial pagination
    updateWatchlistPagination();
});
</script>

<?php require_once '../includes/footer.php'; ?>
