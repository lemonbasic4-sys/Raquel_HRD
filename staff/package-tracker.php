<?php
/**
 * HR Staff Portal - read-only evaluation package tracker.
 */
$page_title = 'Evaluation Tracker';
require_once '../includes/session-check.php';
checkRole(['HR Staff']);
require_once '../includes/functions.php';
ensureOrganizationEvaluationPackageSchema($conn);

$packages_result = $conn->query("
    SELECT ep.package_id, ep.department_id, ep.evaluation_type, ep.period_start, ep.period_end,
           ep.current_step_order, ep.status, ep.updated_at, d.department_name, et.template_name,
           (SELECT COUNT(*) FROM evaluation_package_members pm WHERE pm.package_id = ep.package_id) AS member_count
    FROM evaluation_packages ep
    LEFT JOIN departments d ON d.department_id = ep.department_id
    LEFT JOIN evaluation_templates et ON et.template_id = ep.template_id
    ORDER BY ep.updated_at DESC, ep.package_id DESC
");
$packages = $packages_result ? $packages_result->fetch_all(MYSQLI_ASSOC) : [];

$routes = [];
if ($packages) {
    $route_result = $conn->query("
        SELECT rs.package_id, rs.step_order, rs.reviewer_employee_id, rs.reviewer_user_id,
               rs.step_label, rs.eligible_role, rs.eligible_rank_category_id,
               rs.action_status, rs.claimed_at, rs.acted_at,
               TRIM(CONCAT(e.first_name, ' ', IFNULL(CONCAT(e.middle_name, ' '), ''), e.last_name)) AS employee_name,
               u.full_name AS user_name
        FROM evaluation_package_route_steps rs
        LEFT JOIN employees e ON e.employee_id = rs.reviewer_employee_id
        LEFT JOIN users u ON u.user_id = rs.reviewer_user_id
        ORDER BY rs.package_id, rs.step_order
    ");
    if ($route_result) {
        while ($route = $route_result->fetch_assoc()) {
            $routes[(int)$route['package_id']][] = $route;
        }
        $route_result->free();
    }
}

$department_result = $conn->query("SELECT department_id, department_name FROM departments WHERE is_active = 1 ORDER BY department_name");
$departments = $department_result ? $department_result->fetch_all(MYSQLI_ASSOC) : [];
$counts = ['total' => count($packages), 'active' => 0, 'returned' => 0, 'completed' => 0];
foreach ($packages as $package) {
    if ($package['status'] === 'Approved and Applied') $counts['completed']++;
    elseif ($package['status'] === 'Returned') $counts['returned']++;
    elseif ($package['status'] !== 'Cancelled') $counts['active']++;
}

require_once '../includes/header.php';
?>

<div class="page-hero fadeup mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <div class="small text-uppercase text-white-50">HR Staff · Monitoring</div>
            <h1 class="h4 text-white fw-bold mb-1"><i class="fas fa-layer-group me-2 text-warning"></i>Performance Evaluation Tracker</h1>
            <p class="text-white-50 small mb-0">Read-only status and approval-route overview across departments.</p>
        </div>
        <span class="badge bg-light text-dark px-3 py-2"><i class="fas fa-eye me-1"></i>View only</span>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['All Packages', $counts['total'], 'fa-boxes-stacked', 'text-primary'],
        ['In Progress', $counts['active'], 'fa-hourglass-half', 'text-warning'],
        ['Returned', $counts['returned'], 'fa-rotate-left', 'text-danger'],
        ['Completed', $counts['completed'], 'fa-circle-check', 'text-success'],
    ] as [$label, $count, $icon, $color]): ?>
        <div class="col-6 col-xl-3">
            <div class="stat-card h-100">
                <div><div class="stat-value"><?php echo (int)$count; ?></div><div class="stat-label"><?php echo e($label); ?></div></div>
                <i class="fas <?php echo e($icon); ?> stat-icon <?php echo e($color); ?> ms-auto"></i>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<section class="content-card">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h2 class="h5 fw-bold mb-1"><i class="fas fa-list-check me-2 text-primary"></i>Packages</h2>
            <span class="small text-muted"><?php echo count($packages); ?> package<?php echo count($packages) === 1 ? '' : 's'; ?> found</span>
        </div>
        <div class="d-flex flex-wrap gap-2 ms-auto">
            <input type="search" class="form-control form-control-sm" id="packageSearch" placeholder="Search department, template, or ID" aria-label="Search packages" style="min-width:230px;">
            <select class="form-select form-select-sm" id="packageStatusFilter" aria-label="Filter by package status" style="min-width:150px;">
                <option value="">All statuses</option>
                <option value="active">In progress</option>
                <option value="Returned">Returned</option>
                <option value="Approved and Applied">Completed</option>
                <option value="Cancelled">Cancelled</option>
            </select>
            <select class="form-select form-select-sm" id="packageDepartmentFilter" aria-label="Filter by department" style="min-width:170px;">
                <option value="">All departments</option>
                <?php foreach ($departments as $department): ?>
                    <option value="<?php echo (int)$department['department_id']; ?>"><?php echo e($department['department_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Package</th><th>Department / Template</th><th>Period</th><th>Team</th><th>Status / Stage</th><th class="text-end">Route</th></tr></thead>
            <tbody>
            <?php if (!$packages): ?>
                <tr><td colspan="6" class="text-center py-5 text-muted"><i class="fas fa-folder-open fa-2x d-block mb-2 opacity-50"></i>No evaluation packages have been created yet.</td></tr>
            <?php else: ?>
                <?php foreach ($packages as $package):
                    $id = (int)$package['package_id'];
                    $steps = $routes[$id] ?? [];
                    $status = (string)$package['status'];
                    $current_step = null;
                    foreach ($steps as $step) {
                        if ((int)$step['step_order'] === (int)$package['current_step_order']) {
                            $current_step = $step;
                            break;
                        }
                    }
                    $stage = $current_step['step_label'] ?? 'Route step not available';
                    if ($status === 'Approved and Applied') $stage = 'Finalized';
                    elseif ($status === 'Cancelled') $stage = 'Cancelled';
                    elseif ($status === 'Returned') $stage = 'Returned for revision';
                    elseif ($status === 'Pending Self-Ratings') $stage = 'Waiting for team self-ratings';
                    $search_text = strtolower($id . ' ' . ($package['department_name'] ?? '') . ' ' . ($package['template_name'] ?? ''));
                    $collapse_id = 'package-route-' . $id;
                    $approved = count(array_filter($steps, static fn($step) => in_array($step['action_status'], ['Approved', 'Skipped'], true)));
                    $progress = count($steps) ? (int)round($approved / count($steps) * 100) : 0;
                    $status_classes = [
                        'Pending Self-Ratings' => 'bg-secondary-subtle text-secondary border',
                        'Pending Consolidation' => 'bg-warning-subtle text-warning-emphasis border',
                        'Pending Review' => 'bg-primary-subtle text-primary border',
                        'Pending Audit Approval' => 'bg-info-subtle text-info-emphasis border',
                        'Pending Board Approval' => 'bg-success-subtle text-success border',
                        'Approved and Applied' => 'bg-success text-white',
                        'Returned' => 'bg-danger-subtle text-danger border',
                        'Cancelled' => 'bg-secondary text-white',
                    ];
                ?>
                    <tr class="package-row" data-search="<?php echo e($search_text); ?>" data-status="<?php echo e($status); ?>" data-department="<?php echo (int)$package['department_id']; ?>">
                        <td><div class="fw-bold">#<?php echo $id; ?></div><small class="text-muted"><?php echo e($package['evaluation_type']); ?></small></td>
                        <td><div class="fw-semibold"><?php echo e($package['department_name'] ?? 'Department unavailable'); ?></div><small class="text-muted"><?php echo e($package['template_name'] ?? 'Template unavailable'); ?></small></td>
                        <td><div><?php echo e(formatDate($package['period_start'])); ?> – <?php echo e(formatDate($package['period_end'])); ?></div><small class="text-muted">Updated <?php echo e(formatDate($package['updated_at'], 'M d, Y h:i A')); ?></small></td>
                        <td><span class="badge bg-light text-dark border"><?php echo (int)$package['member_count']; ?> member<?php echo (int)$package['member_count'] === 1 ? '' : 's'; ?></span></td>
                        <td><span class="badge <?php echo e($status_classes[$status] ?? 'bg-secondary text-white'); ?>"><?php echo e($status); ?></span><div class="small text-muted mt-1"><?php echo e($stage); ?></div></td>
                        <td class="text-end"><button class="btn btn-sm btn-outline-primary rounded-pill" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo e($collapse_id); ?>" aria-expanded="false" aria-controls="<?php echo e($collapse_id); ?>"><i class="fas fa-route me-1"></i><?php echo count($steps); ?> step<?php echo count($steps) === 1 ? '' : 's'; ?></button></td>
                    </tr>
                    <tr class="package-route-row">
                        <td colspan="6" class="p-0 border-0"><div class="collapse" id="<?php echo e($collapse_id); ?>"><div class="p-3 p-md-4 bg-light border-top">
                            <?php if (!$steps): ?>
                                <div class="alert alert-warning mb-0"><i class="fas fa-triangle-exclamation me-2"></i>No route steps are configured for this package.</div>
                            <?php else: ?>
                                <div class="d-flex justify-content-between small text-muted mb-2"><strong class="text-uppercase">Approval route</strong><span><?php echo $progress; ?>% completed</span></div>
                                <div class="progress mb-3" role="progressbar" aria-label="Approval route progress" aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100" style="height:6px;"><div class="progress-bar bg-success" style="width:<?php echo $progress; ?>%;"></div></div>
                                <ol class="list-group list-group-numbered">
                                    <?php foreach ($steps as $step):
                                        if (!empty($step['eligible_role']) && empty($step['claimed_at'])) $reviewer = 'Available to ' . $step['eligible_role'];
                                        elseif (!empty($step['eligible_rank_category_id']) && empty($step['claimed_at'])) $reviewer = (int)$step['eligible_rank_category_id'] === 4 ? 'Available to Department Supervisors' : 'Available to Department Managers';
                                        else $reviewer = $step['employee_name'] ?: ($step['user_name'] ?: 'Reviewer not assigned');
                                        $step_class = ['Approved' => 'text-success', 'Pending' => 'text-primary fw-bold', 'Returned' => 'text-danger fw-bold', 'Skipped' => 'text-muted'][$step['action_status']] ?? 'text-secondary';
                                    ?>
                                        <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                                            <div><span class="fw-semibold"><?php echo e($step['step_label']); ?></span><div class="small text-muted"><?php echo e($reviewer); ?></div></div>
                                            <div class="text-end"><span class="<?php echo e($step_class); ?>"><?php echo e($step['action_status']); ?></span><?php if ($step['acted_at']): ?><div class="small text-muted"><?php echo e(formatDate($step['acted_at'], 'M d, Y h:i A')); ?></div><?php endif; ?></div>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php endif; ?>
                        </div></div></td>
                    </tr>
                <?php endforeach; ?>
                <tr id="noPackageMatches" style="display:none;"><td colspan="6" class="text-center py-4 text-muted">No packages match these filters.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const search = document.getElementById('packageSearch');
    const status = document.getElementById('packageStatusFilter');
    const department = document.getElementById('packageDepartmentFilter');
    const rows = Array.from(document.querySelectorAll('.package-row'));
    const noMatches = document.getElementById('noPackageMatches');

    function filterPackages() {
        const query = search.value.trim().toLowerCase();
        let visible = 0;
        rows.forEach(function (row) {
            const selectedStatus = status.value;
            const matchesStatus = !selectedStatus || (selectedStatus === 'active'
                ? !['Approved and Applied', 'Returned', 'Cancelled'].includes(row.dataset.status)
                : row.dataset.status === selectedStatus);
            const matches = (!query || row.dataset.search.includes(query)) &&
                matchesStatus &&
                (!department.value || row.dataset.department === department.value);
            row.hidden = !matches;
            const routeRow = row.nextElementSibling;
            if (routeRow && routeRow.classList.contains('package-route-row')) {
                routeRow.hidden = !matches;
                if (!matches) {
                    const collapse = routeRow.querySelector('.collapse.show');
                    if (collapse && window.bootstrap) window.bootstrap.Collapse.getOrCreateInstance(collapse).hide();
                }
            }
            if (matches) visible++;
        });
        if (noMatches) noMatches.style.display = visible ? 'none' : '';
    }

    search.addEventListener('input', filterPackages);
    status.addEventListener('change', filterPackages);
    department.addEventListener('change', filterPackages);
});
</script>

<?php require_once '../includes/footer.php'; ?>
