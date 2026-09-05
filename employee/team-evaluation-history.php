<?php
$page_title = 'Team Evaluation History';
require_once '../includes/session-check.php';
require_once '../includes/functions.php';

// HR Manager and HR Supervisor access evaluation history from HRIS sidebar.
// Only HR Staff and Employee-role users in the HR dept are blocked here.
$_session_role = $_SESSION['role'] ?? '';
if ($_session_role === 'HR Staff') {
    redirectWith(BASE_URL . '/employee/dashboard.php', 'info', 'Human Resource personnel view evaluation history directly on the HRIS portal.');
}
if ($_session_role === 'Employee') {
    $_hdr_emp_id_hist = (int)($_SESSION['employee_id'] ?? 0);
    $s = $conn->prepare("SELECT d.department_name FROM employees e LEFT JOIN departments d ON e.department_id = d.department_id WHERE e.employee_id = ? LIMIT 1");
    $s->bind_param('i', $_hdr_emp_id_hist); $s->execute();
    $_hdr_dept_hist = $s->get_result()->fetch_assoc()['department_name'] ?? ''; $s->close();
    if (strcasecmp($_hdr_dept_hist, 'Human Resources') === 0) {
        redirectWith(BASE_URL . '/employee/dashboard.php', 'info', 'Human Resource personnel view evaluation history directly on the HRIS portal.');
    }
}

ensureOrganizationEvaluationPackageSchema($conn);
$user_id = (int) ($_SESSION['user_id'] ?? 0);
$reviewer_match = organizationPackageReviewerMatchSql('rs');

// History Packages Query:
// A reviewer sees packages in Team Evaluation History ONLY if:
// 1. They have already acted on the package step (rs.action_status IN ('Approved', 'Returned'))
// 2. The package is fully finalized (ep.status = 'Approved and Applied')
// Current working packages pending action belong on Team Evaluation Packages (team-evaluation-packages.php).
$packages_stmt = $conn->prepare("SELECT DISTINCT ep.package_id, ep.status, ep.period_start, ep.period_end, ep.shared_behavior_score,
        ep.department_id, ep.template_id, d.department_name, et.template_name, et.kra_weight, et.behavior_weight
    FROM evaluation_packages ep
    JOIN evaluation_package_route_steps rs ON rs.package_id = ep.package_id
    JOIN departments d ON d.department_id = ep.department_id
    JOIN evaluation_templates et ON et.template_id = ep.template_id
    WHERE $reviewer_match
      AND (
          rs.action_status IN ('Approved', 'Returned')
          OR ep.status = 'Approved and Applied'
      )
      AND ep.status <> 'Cancelled'
    ORDER BY ep.updated_at DESC");
$packages_stmt->bind_param('ii', $user_id, $user_id);
$packages_stmt->execute();
$packages = $packages_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$packages_stmt->close();

require_once '../includes/header.php';
?>
<main class="evaluation-packages container-fluid py-4">
    <section class="package-hero">
        <a class="history-back-link" href="<?php echo BASE_URL; ?>/employee/team-evaluation-packages.php">
            <i class="fas fa-arrow-left"></i> Back to assigned packages
        </a>
        <p class="mb-1 text-uppercase fw-bold" style="letter-spacing:1px; color:var(--rp-primary-gold-light); font-size:0.85rem;">
            Organization-Driven Performance History
        </p>
        <h1 class="h3 mb-2 fw-bold">Team Evaluation History &amp; Progress</h1>
        <p class="mb-0">
            Inspect past evaluation cycles, historical consolidator adjustments, and complete audit records for your teams.
        </p>
    </section>

    <!-- ── Evaluation History Packages ────────────────────────────────────── -->
    <?php if (!$packages): ?>
        <section class="package-empty">
            <i class="fas fa-folder-open fa-3x text-muted mb-3" style="opacity:0.4;"></i>
            <h2 class="h5 fw-bold">No evaluation packages in your history yet</h2>
            <p class="mb-0 text-muted">
                Packages will appear here once you act on them or when they reach your stage in the sequential approval pipeline.
            </p>
        </section>
    <?php endif; ?>

    <?php foreach ($packages as $package): ?>
        <?php
        $package_id = (int)$package['package_id'];
        $members_stmt = $conn->prepare("SELECT e.evaluation_id, emp.first_name, emp.last_name, emp.job_title,
                e.kra_subtotal, e.behavior_average, e.total_score, e.status,
                EXISTS(SELECT 1 FROM evaluation_scores es WHERE es.evaluation_id = e.evaluation_id AND (es.supervisor_override_score IS NOT NULL OR es.dept_manager_override_score IS NOT NULL OR es.manager_override_score IS NOT NULL)) AS has_adjustments
            FROM evaluation_package_members pm
            JOIN evaluations e ON e.evaluation_id = pm.evaluation_id
            JOIN employees emp ON emp.employee_id = e.employee_id
            WHERE pm.package_id = ?
            ORDER BY emp.last_name, emp.first_name");
        $members_stmt->bind_param('i', $package_id);
        $members_stmt->execute();
        $members = $members_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $members_stmt->close();
        ?>
        <section class="package-card" role="region" aria-label="<?php echo e($package['department_name']); ?> Evaluation Package">
            <header class="package-card__header">
                <div>
                    <h2 class="h5 mb-1 fw-bold">
                        <?php echo e($package['department_name']); ?> &mdash; <?php echo e($package['template_name']); ?>
                    </h2>
                    <p class="mb-0 small text-muted">
                        <i class="fas fa-calendar-alt me-1"></i>Cycle Period: <?php echo e($package['period_start']); ?> to <?php echo e($package['period_end']); ?>
                    </p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <?php echo renderOrganizationPipelineBadge($conn, $package_id); ?>
                    <button class="btn btn-sm btn-package-collapse" type="button" data-bs-toggle="collapse" data-bs-target="#pkgHistBody-<?php echo $package_id; ?>" aria-expanded="true" aria-controls="pkgHistBody-<?php echo $package_id; ?>" title="Minimize / Expand Package">
                        <i class="fas fa-chevron-up collapse-icon"></i> <span class="d-none d-sm-inline">Minimize</span>
                    </button>
                </div>
            </header>
            <div class="collapse show" id="pkgHistBody-<?php echo $package_id; ?>">
                <div class="package-card__body">
                    <div class="shared-behavior-banner d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div>
                            <i class="fas fa-users-cog me-2"></i>
                            Shared Core Behaviors &amp; Values Score:
                            <strong><?php echo $package['shared_behavior_score'] !== null ? number_format((float) $package['shared_behavior_score'], 2) : 'Pending Consolidation'; ?></strong>
                        </div>
                        <div class="small text-muted">
                            Applied across all <?php echo count($members); ?> package members upon Board approval.
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="package-table table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Position</th>
                                    <th class="text-end">Individual KRA</th>
                                    <th class="text-end">Self Behavior</th>
                                    <th class="text-end">Total Score</th>
                                    <th class="text-end">Final Score</th>
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($members as $member): ?>
                                    <?php
                                    $kra_w = isset($package['kra_weight']) && (float)$package['kra_weight'] > 0 ? (float)$package['kra_weight'] : 80;
                                    $beh_w = isset($package['behavior_weight']) && (float)$package['behavior_weight'] > 0 ? (float)$package['behavior_weight'] : 20;
                                    $beh_val = (float)$member['behavior_average'];
                                    $total_score_val = calculateEvalTotal((float)$member['kra_subtotal'], $beh_val, $kra_w, $beh_w);
                                    $shared_beh_val = $package['shared_behavior_score'] !== null ? (float)$package['shared_behavior_score'] : $beh_val;
                                    $final_score_val = calculateEvalTotal((float)$member['kra_subtotal'], $shared_beh_val, $kra_w, $beh_w);
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?php echo e($member['first_name'] . ' ' . $member['last_name']); ?></div>
                                            <?php if (!empty($member['has_adjustments'])): ?>
                                                <span class="audit-chip audit-chip--adjusted">
                                                    <i class="fas fa-pen-fancy me-1"></i>Scores Adjusted by Evaluator
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo e($member['job_title']); ?></td>
                                        <td class="text-end fw-semibold tabular-nums">
                                            <?php echo $member['kra_subtotal'] !== null ? number_format((float) $member['kra_subtotal'], 2) : '&mdash;'; ?>
                                        </td>
                                        <td class="text-end fw-semibold tabular-nums">
                                            <?php echo $member['behavior_average'] !== null ? number_format((float) $member['behavior_average'], 2) : '&mdash;'; ?>
                                        </td>
                                        <td class="text-end fw-semibold text-muted tabular-nums">
                                            <?php echo number_format($total_score_val, 2); ?>
                                        </td>
                                        <td class="text-end fw-bold text-success tabular-nums" style="font-size: 1.05rem;">
                                            <?php echo number_format($final_score_val, 2); ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $member['status'] === 'Approved' ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo e($member['status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a class="btn-action-view btn btn-sm" href="<?php echo BASE_URL; ?>/employee/package-member-view.php?package_id=<?php echo $package_id; ?>&evaluation_id=<?php echo (int) $member['evaluation_id']; ?>">
                                                <i class="fas fa-file-signature me-1"></i>View Details
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Bottom Audit Trail & Revision History Section -->
                    <?php echo renderOrganizationPackageAuditTrail($conn, $package_id); ?>
                </div>
            </div>
        </section>
    <?php endforeach; ?>
</main>
<?php require_once '../includes/footer.php'; ?>
