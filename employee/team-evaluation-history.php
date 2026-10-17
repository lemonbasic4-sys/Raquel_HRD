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
$claim_profile_stmt = $conn->prepare("SELECT u.role, e.department_id, e.rank_category_id, e.employment_status, d.department_name
    FROM users u LEFT JOIN employees e ON e.employee_id = u.employee_id
    LEFT JOIN departments d ON d.department_id = e.department_id
    WHERE u.user_id = ? LIMIT 1");
$claim_profile_stmt->bind_param('i', $user_id);
$claim_profile_stmt->execute();
$claim_profile = $claim_profile_stmt->get_result()->fetch_assoc() ?: [];
$claim_profile_stmt->close();
$package_claim_role = in_array($claim_profile['role'] ?? '', ['HR Supervisor', 'HR Manager', 'President and CEO'], true)
    ? (string)$claim_profile['role'] : '';
$package_claim_rank_category = (($claim_profile['role'] ?? '') === 'Employee'
    && strcasecmp((string)($claim_profile['department_name'] ?? ''), 'Human Resources') !== 0
    && ($claim_profile['employment_status'] ?? '') === 'Regular'
    && in_array((int)($claim_profile['rank_category_id'] ?? 0), [3, 4], true))
    ? (int)$claim_profile['rank_category_id'] : 0;
$package_claim_department_id = $package_claim_rank_category > 0 ? (int)($claim_profile['department_id'] ?? 0) : 0;

// Show every non-cancelled package that has a route step assigned to this reviewer,
// including in-progress and waiting stages, so the page can show review history/progress
// before the entire package is finalized.
$packages_stmt = $conn->prepare("SELECT DISTINCT ep.package_id, ep.status, ep.evaluation_type, ep.period_start, ep.period_end, ep.shared_behavior_score,
        ep.department_id, ep.template_id, d.department_name, et.template_name, et.kra_weight, et.behavior_weight
    FROM evaluation_packages ep
    JOIN evaluation_package_route_steps rs ON rs.package_id = ep.package_id
    JOIN departments d ON d.department_id = ep.department_id
    JOIN evaluation_templates et ON et.template_id = ep.template_id
    WHERE ($reviewer_match
        OR (rs.eligible_role = ? AND rs.claimed_at IS NULL)
        OR (rs.eligible_rank_category_id = ? AND ep.department_id = ? AND rs.claimed_at IS NULL))
      AND ep.status <> 'Cancelled'
    ORDER BY ep.updated_at DESC");
$packages_stmt->bind_param('iisii', $user_id, $user_id, $package_claim_role, $package_claim_rank_category, $package_claim_department_id);
$packages_stmt->execute();
$packages = $packages_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$packages_stmt->close();

$filter_value = static function (string $key): string {
    return isset($_GET[$key]) && is_string($_GET[$key]) ? trim($_GET[$key]) : '';
};
$filters = [];
foreach (['search', 'department', 'type', 'status', 'from', 'to'] as $key) {
    $filters[$key] = $filter_value($key);
}
$filter_errors = [];
foreach (['from', 'to'] as $key) {
    if ($filters[$key] !== '') {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $filters[$key]);
        if (!$date || $date->format('Y-m-d') !== $filters[$key]) {
            $filter_errors[] = 'Enter a valid cycle date.';
            $filters[$key] = '';
        }
    }
}
if ($filters['from'] !== '' && $filters['to'] !== '' && $filters['from'] > $filters['to']) {
    $filter_errors[] = 'The cycle end date must be on or after the start date.';
}
$filter_options = ['department' => [], 'type' => [], 'status' => []];
foreach ($packages as $package) {
    $filter_options['department'][(string)$package['department_id']] = $package['department_name'];
    $filter_options['type'][$package['evaluation_type']] = $package['evaluation_type'];
    $filter_options['status'][$package['status']] = $package['status'];
}
foreach ($filter_options as &$options) {
    asort($options, SORT_NATURAL | SORT_FLAG_CASE);
}
unset($options);
$total_packages = count($packages);
// Search members without changing the reviewer access restrictions above.
$member_matches = [];
if ($filters['search'] !== '') {
    $search_stmt = $conn->prepare("SELECT DISTINCT pm.package_id
        FROM evaluation_package_members pm
        JOIN evaluations ev ON ev.evaluation_id = pm.evaluation_id
        JOIN employees emp ON emp.employee_id = ev.employee_id
        WHERE LOCATE(?, CONCAT_WS(' ', emp.first_name, emp.last_name, emp.job_title)) > 0");
    $search_stmt->bind_param('s', $filters['search']);
    $search_stmt->execute();
    $member_matches = array_fill_keys(array_column($search_stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'package_id'), true);
    $search_stmt->close();
}
$packages = array_values(array_filter($packages, static function (array $package) use ($filters, $filter_errors, $member_matches): bool {
    if ($filter_errors) return false;
    if ($filters['department'] !== '' && (string)$package['department_id'] !== $filters['department']) return false;
    if ($filters['type'] !== '' && $package['evaluation_type'] !== $filters['type']) return false;
    if ($filters['status'] !== '' && $package['status'] !== $filters['status']) return false;
    // Include cycles overlapping the selected date range.
    if ($filters['from'] !== '' && $package['period_end'] < $filters['from']) return false;
    if ($filters['to'] !== '' && $package['period_start'] > $filters['to']) return false;
    if ($filters['search'] !== '') {
        $text = implode(' ', [$package['package_id'], $package['department_name'], $package['template_name']]);
        if (stripos($text, $filters['search']) === false && !isset($member_matches[$package['package_id']])) return false;
    }
    return true;
}));

require_once '../includes/header.php';
?>
<div class="evaluation-packages team-evaluation-history">
    <section class="package-hero">
        <a class="history-back-link" href="<?php echo BASE_URL; ?>/employee/team-evaluation-packages.php">
            <i class="fas fa-arrow-left"></i> Back to assigned reviews
        </a>
        <p class="mb-1 text-uppercase fw-bold" style="letter-spacing:1px; color:var(--rp-primary-gold-light); font-size:0.85rem;">
            Organization-Driven Performance History
        </p>
        <h1 class="h3 mb-2 fw-bold">Team Evaluation History &amp; Progress</h1>
        <p class="mb-0">
            Inspect evaluation cycles, current review progress, historical consolidator adjustments, and audit records for your teams.
        </p>
    </section>

    <!-- ── Evaluation History Packages ────────────────────────────────────── -->
    <section class="package-card p-3" aria-label="Filter evaluation history">
        <form method="get" action="team-evaluation-history.php">
            <div class="row g-3">
                <div class="col-12 col-lg-6">
                    <label for="history-search" class="form-label">Search</label>
                    <input type="search" id="history-search" name="search" class="form-control" value="<?php echo e($filters['search']); ?>" placeholder="Employee, position, department, template or package ID">
                </div>
                <?php foreach (['department' => 'Department', 'type' => 'Evaluation type', 'status' => 'Package status'] as $key => $label): ?>
                    <div class="col-12 col-md-4 col-lg-2">
                        <label for="history-<?php echo $key; ?>" class="form-label"><?php echo $label; ?></label>
                        <select id="history-<?php echo $key; ?>" name="<?php echo $key; ?>" class="form-select">
                            <option value="">All</option>
                            <?php foreach ($filter_options[$key] as $value => $name): ?>
                                <option value="<?php echo e((string)$value); ?>" <?php echo $filters[$key] === (string)$value ? 'selected' : ''; ?>><?php echo e($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; ?>
                <div class="col-12 col-md-4">
                    <label for="history-from" class="form-label">Cycle from</label>
                    <input type="date" id="history-from" name="from" class="form-control" value="<?php echo e($filters['from']); ?>">
                </div>
                <div class="col-12 col-md-4">
                    <label for="history-to" class="form-label">Cycle to</label>
                    <input type="date" id="history-to" name="to" class="form-control" value="<?php echo e($filters['to']); ?>">
                </div>
                <div class="col-12 col-md-4 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-success"><i class="fas fa-filter me-1" aria-hidden="true"></i>Apply filters</button>
                    <a href="team-evaluation-history.php" class="btn btn-outline-secondary">Clear</a>
                </div>
            </div>
            <p class="small text-muted mt-3 mb-0">Showing <?php echo count($packages); ?> of <?php echo $total_packages; ?> evaluation packages. Date filters include cycles overlapping the selected range. Employee matches show the complete package.</p>
            <?php foreach (array_unique($filter_errors) as $error): ?>
                <p class="text-danger mt-2 mb-0" role="alert"><?php echo e($error); ?></p>
            <?php endforeach; ?>
        </form>
    </section>
    <?php if (!$packages): ?>
        <section class="package-empty">
            <i class="fas fa-folder-open fa-3x text-muted mb-3" style="opacity:0.4;"></i>
            <h2 class="h5 fw-bold"><?php echo $total_packages ? 'No evaluation reviews match your filters' : 'No evaluation reviews in your history yet'; ?></h2>
            <p class="mb-0 text-muted">
                <?php echo $total_packages ? 'Adjust your filters or clear them to view all evaluation packages.' : 'Evaluation reviews will appear here once you act on them or when they reach your stage in the sequential approval pipeline.'; ?>
            </p>
        </section>
    <?php endif; ?>

    <?php foreach ($packages as $package): ?>
        <?php
        $package_id = (int)$package['package_id'];
        $members_stmt = $conn->prepare("SELECT e.evaluation_id, emp.first_name, emp.last_name, emp.job_title,
                e.kra_subtotal, e.behavior_average, e.total_score, e.status,
                (SELECT AVG(COALESCE(es.manager_override_score, es.supervisor_override_score,
                                     es.dept_manager_override_score, es.score_value))
                 FROM evaluation_scores es JOIN evaluation_criteria ec ON ec.criterion_id = es.criterion_id
                 WHERE es.evaluation_id = e.evaluation_id AND ec.section = 'Behavior') AS individual_behavior,
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
        $is_probationary_package = in_array($package['evaluation_type'], ['Initial', 'Final'], true);
        ?>
        <section class="package-card" role="region" aria-label="<?php echo e($package['department_name']); ?> Evaluation Review">
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
                    <button class="btn btn-sm btn-package-collapse collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#pkgHistBody-<?php echo $package_id; ?>" aria-expanded="false" aria-controls="pkgHistBody-<?php echo $package_id; ?>" title="Expand Review">
                        <i class="fas fa-chevron-up collapse-icon"></i> <span class="d-none d-sm-inline">Expand</span>
                    </button>
                </div>
            </header>
            <div class="collapse" id="pkgHistBody-<?php echo $package_id; ?>">
                <div class="package-card__body">
                    <div class="shared-behavior-banner d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div>
                            <i class="fas fa-users-cog me-2"></i>
                            <?php echo $is_probationary_package ? 'Individual Core Values Score:' : 'Shared Core Behaviors &amp; Values Score:'; ?>
                            <strong><?php echo $is_probationary_package ? number_format((float)($members[0]['individual_behavior'] ?? $members[0]['behavior_average'] ?? 0), 2) : ($package['shared_behavior_score'] !== null ? number_format((float) $package['shared_behavior_score'], 2) : 'Pending Consolidation'); ?></strong>
                        </div>
                        <div class="small text-muted">
                            <?php echo $is_probationary_package ? 'Finalized upon HR Manager approval.' : 'Applied across all ' . count($members) . ' review members upon Board approval.'; ?>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="package-table table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="col-employee">Employee</th>
                                    <th class="col-position">Position</th>
                                    <th class="text-end col-score"><?php echo $is_probationary_package ? 'KRA Score' : 'Individual KRA'; ?></th>
                                    <th class="text-end col-score"><?php echo $is_probationary_package ? 'Core Values Score' : 'Individual Behavior'; ?></th>
                                    <th class="text-end col-score"><?php echo $is_probationary_package ? 'Weighted Score' : 'Individual Score'; ?></th>
                                    <th class="text-end col-final-score">Final Score</th>
                                    <th class="col-status">Status</th>
                                    <th class="text-end col-action">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($members as $member): ?>
                                    <?php
                                    $kra_w = $is_probationary_package ? 80 : (isset($package['kra_weight']) && (float)$package['kra_weight'] > 0 ? (float)$package['kra_weight'] : 80);
                                    $beh_w = $is_probationary_package ? 20 : (isset($package['behavior_weight']) && (float)$package['behavior_weight'] > 0 ? (float)$package['behavior_weight'] : 20);
                                    $beh_val = (float)($member['individual_behavior'] ?? $member['behavior_average']);
                                    $total_score_val = calculateEvalTotal((float)$member['kra_subtotal'], $beh_val, $kra_w, $beh_w);
                                    $shared_beh_val = $is_probationary_package ? $beh_val : ($package['shared_behavior_score'] !== null ? (float)$package['shared_behavior_score'] : $beh_val);
                                    $final_score_val = calculateEvalTotal((float)$member['kra_subtotal'], $shared_beh_val, $kra_w, $beh_w);
                                    ?>
                                    <tr>
                                        <td class="col-employee">
                                            <div class="fw-bold"><?php echo e($member['first_name'] . ' ' . $member['last_name']); ?></div>
                                            <?php if (!empty($member['has_adjustments'])): ?>
                                                <span class="audit-chip audit-chip--adjusted">
                                                    <i class="fas fa-pen-fancy me-1"></i>Scores Adjusted by Evaluator
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="col-position"><?php echo e($member['job_title']); ?></td>
                                        <td class="col-score text-end fw-semibold tabular-nums">
                                            <?php echo $member['kra_subtotal'] !== null ? number_format((float) $member['kra_subtotal'], 2) : '&mdash;'; ?>
                                        </td>
                                        <td class="col-score text-end fw-semibold tabular-nums">
                                            <?php echo $member['individual_behavior'] !== null ? number_format((float) $member['individual_behavior'], 2) : '&mdash;'; ?>
                                        </td>
                                        <td class="col-score text-end fw-semibold text-muted tabular-nums">
                                            <?php echo number_format($total_score_val, 2); ?>
                                        </td>
                                        <td class="col-final-score text-end fw-bold text-success tabular-nums" style="font-size: 1.05rem;">
                                            <?php echo number_format($final_score_val, 2); ?>
                                        </td>
                                        <td class="col-status">
                                            <span class="badge <?php echo $member['status'] === 'Approved' ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo e($member['status']); ?>
                                            </span>
                                        </td>
                                        <td class="col-action text-end">
                                            <a class="btn-action-view btn btn-sm" href="<?php echo BASE_URL; ?>/employee/package-member-view.php?package_id=<?php echo $package_id; ?>&evaluation_id=<?php echo (int) $member['evaluation_id']; ?>">
                                                <i class="fas fa-eye me-1"></i>View
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
</div>
<script>
document.querySelectorAll('.team-evaluation-history .btn-package-collapse').forEach(function (button) {
    const body = document.getElementById(button.getAttribute('aria-controls'));
    function updateLabel(expanded) {
        button.querySelector('span').textContent = expanded ? 'Minimize' : 'Expand';
        button.title = expanded ? 'Minimize Review' : 'Expand Review';
    }
    body.addEventListener('show.bs.collapse', function (event) {
        if (event.target === body) updateLabel(true);
    });
    body.addEventListener('hide.bs.collapse', function (event) {
        if (event.target === body) updateLabel(false);
    });
});
</script>
<?php require_once '../includes/footer.php'; ?>
