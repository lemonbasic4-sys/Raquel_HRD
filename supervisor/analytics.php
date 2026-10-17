<?php
$page_title = 'Branch Analytics';
require_once '../includes/session-check.php';
checkRole(['HR Supervisor']);
require_once '../includes/functions.php';

$supervisor_id = (int) ($_SESSION['user_id'] ?? 0);
$branch_id = (int) ($_SESSION['branch_id'] ?? 0);

function supervisorAnalyticsDate(string $value): string
{
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
}

function supervisorAnalyticsScalar(mysqli $conn, string $sql, string $types = '', array $params = [])
{
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? array_values($row)[0] : null;
}

function supervisorAnalyticsRows(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

$date_from = supervisorAnalyticsDate(trim($_GET['date_from'] ?? ''));
$date_to = supervisorAnalyticsDate(trim($_GET['date_to'] ?? ''));
$filter_dept = isset($_GET['department']) && $_GET['department'] !== '' ? (int) $_GET['department'] : 0;
$cohort = (($_GET['cohort'] ?? 'regular') === 'probationary') ? 'probationary' : 'regular';
$evaluation_type_filter = $cohort === 'probationary'
    ? "ev.evaluation_type IN ('Initial','Final')"
    : "ev.evaluation_type IN ('Annual','Quarterly')";

$branch_name = supervisorAnalyticsScalar(
    $conn,
    "SELECT branch_name FROM branches WHERE branch_id = ?",
    "i",
    [$branch_id]
) ?: 'Assigned Branch';

$department_options = supervisorAnalyticsRows(
    $conn,
    "SELECT DISTINCT d.department_id, d.department_name
     FROM employees e
     JOIN departments d ON e.department_id = d.department_id
     WHERE e.branch_id = ? AND e.is_active = 1 AND d.is_active = 1
       AND e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)
     ORDER BY d.department_name",
    "i",
    [$branch_id]
);

$employeeWhere = "WHERE e.branch_id = ? AND e.is_active = 1
    AND e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)";
$employeeTypes = "i";
$employeeParams = [$branch_id];
if ($filter_dept > 0) {
    $employeeWhere .= " AND e.department_id = ?";
    $employeeTypes .= "i";
    $employeeParams[] = $filter_dept;
}

$evalWhere = "WHERE e.branch_id = ? AND e.is_active = 1
    AND e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)";
$evalTypes = "i";
$evalParams = [$branch_id];
if ($filter_dept > 0) {
    $evalWhere .= " AND e.department_id = ?";
    $evalTypes .= "i";
    $evalParams[] = $filter_dept;
}
$evalWhere .= " AND $evaluation_type_filter";
if ($date_from !== '') {
    $evalWhere .= " AND ev.submitted_date >= ?";
    $evalTypes .= "s";
    $evalParams[] = $date_from;
}
if ($date_to !== '') {
    $evalWhere .= " AND ev.submitted_date <= ?";
    $evalTypes .= "s";
    $evalParams[] = $date_to . ' 23:59:59';
}

$approvedWhere = "WHERE ev.status = 'Approved' AND e.branch_id = ? AND e.is_active = 1
    AND e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)";
$approvedTypes = "i";
$approvedParams = [$branch_id];
if ($filter_dept > 0) {
    $approvedWhere .= " AND e.department_id = ?";
    $approvedTypes .= "i";
    $approvedParams[] = $filter_dept;
}
$approvedWhere .= " AND $evaluation_type_filter";
if ($date_from !== '') {
    $approvedWhere .= " AND ev.approved_date >= ?";
    $approvedTypes .= "s";
    $approvedParams[] = $date_from;
}
if ($date_to !== '') {
    $approvedWhere .= " AND ev.approved_date <= ?";
    $approvedTypes .= "s";
    $approvedParams[] = $date_to . ' 23:59:59';
}

$total_employees = (int) supervisorAnalyticsScalar(
    $conn,
    "SELECT COUNT(*) FROM employees e $employeeWhere",
    $employeeTypes,
    $employeeParams
);

$pending_validations = (int) supervisorAnalyticsScalar(
    $conn,
    "SELECT COUNT(*) FROM evaluations ev
     JOIN employees e ON ev.employee_id = e.employee_id
     $evalWhere AND ev.status = 'Pending Supervisor'",
    $evalTypes,
    $evalParams
);

$returned_evaluations = (int) supervisorAnalyticsScalar(
    $conn,
    "SELECT COUNT(*) FROM evaluations ev
     JOIN employees e ON ev.employee_id = e.employee_id
     $evalWhere AND ev.status = 'Returned'",
    $evalTypes,
    $evalParams
);

$validated_month = (int) supervisorAnalyticsScalar(
    $conn,
    "SELECT COUNT(*) FROM evaluations ev
     JOIN employees e ON ev.employee_id = e.employee_id
     WHERE ev.endorsed_by = ?
       AND e.branch_id = ?
       AND e.is_active = 1
       AND e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)
       AND $evaluation_type_filter
       AND MONTH(ev.endorsed_date) = MONTH(CURRENT_DATE())
       AND YEAR(ev.endorsed_date) = YEAR(CURRENT_DATE())"
       . ($filter_dept > 0 ? " AND e.department_id = ?" : ""),
    $filter_dept > 0 ? "iii" : "ii",
    $filter_dept > 0 ? [$supervisor_id, $branch_id, $filter_dept] : [$supervisor_id, $branch_id]
);

$approved_stats = supervisorAnalyticsRows(
    $conn,
    "SELECT COUNT(*) AS total, ROUND(AVG(ev.total_score), 2) AS avg_score
     FROM evaluations ev
     JOIN employees e ON ev.employee_id = e.employee_id
     $approvedWhere",
    $approvedTypes,
    $approvedParams
)[0] ?? ['total' => 0, 'avg_score' => 0];

$approved_count = (int) ($approved_stats['total'] ?? 0);
$avg_score = (float) ($approved_stats['avg_score'] ?? 0);

$status_counts = [
    'Pending Supervisor' => 0,
    'Pending Manager' => 0,
    'Approved' => 0,
    'Returned' => 0,
    'Rejected' => 0,
];
$status_rows = supervisorAnalyticsRows(
    $conn,
    "SELECT ev.status, COUNT(*) AS total
     FROM evaluations ev
     JOIN employees e ON ev.employee_id = e.employee_id
     $evalWhere
     GROUP BY ev.status",
    $evalTypes,
    $evalParams
);
foreach ($status_rows as $row) {
    if (array_key_exists($row['status'], $status_counts)) {
        $status_counts[$row['status']] = (int) $row['total'];
    }
}

$performance_counts = [
    'Outstanding' => 0,
    'Exceeds Expectations' => 0,
    'Meets Expectations' => 0,
    'Needs Improvement' => 0,
];
$performance_rows = supervisorAnalyticsRows(
    $conn,
    "SELECT ev.performance_level, COUNT(*) AS total
     FROM evaluations ev
     JOIN employees e ON ev.employee_id = e.employee_id
     $approvedWhere AND ev.performance_level IS NOT NULL
     GROUP BY ev.performance_level",
    $approvedTypes,
    $approvedParams
);
foreach ($performance_rows as $row) {
    if (array_key_exists($row['performance_level'], $performance_counts)) {
        $performance_counts[$row['performance_level']] = (int) $row['total'];
    }
}

$top_performers = supervisorAnalyticsRows(
    $conn,
    "SELECT e.employee_id, e.employee_code, CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
            e.job_title, d.department_name, ev.total_score, ev.performance_level, ev.approved_date
     FROM evaluations ev
     JOIN employees e ON ev.employee_id = e.employee_id
     LEFT JOIN departments d ON e.department_id = d.department_id
     $approvedWhere
     ORDER BY ev.total_score DESC, ev.approved_date DESC
     LIMIT 5",
    $approvedTypes,
    $approvedParams
);

$needs_improvement = supervisorAnalyticsRows(
    $conn,
    "SELECT e.employee_id, e.employee_code, CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
            e.job_title, d.department_name, ev.total_score, ev.performance_level, ev.approved_date
     FROM evaluations ev
     JOIN employees e ON ev.employee_id = e.employee_id
     LEFT JOIN departments d ON e.department_id = d.department_id
     $approvedWhere AND (ev.performance_level = 'Needs Improvement' OR ev.total_score < 2.00)
     ORDER BY ev.total_score ASC, ev.approved_date DESC
     LIMIT 5",
    $approvedTypes,
    $approvedParams
);

$deptJoinDate = '';
$deptJoinTypes = '';
$deptJoinParams = [];
if ($date_from !== '') {
    $deptJoinDate .= " AND ev.approved_date >= ?";
    $deptJoinTypes .= "s";
    $deptJoinParams[] = $date_from;
}
if ($date_to !== '') {
    $deptJoinDate .= " AND ev.approved_date <= ?";
    $deptJoinTypes .= "s";
    $deptJoinParams[] = $date_to . ' 23:59:59';
}
$deptJoinDate .= " AND $evaluation_type_filter";
$deptWhere = "WHERE e.branch_id = ?
       AND e.is_active = 1
       AND e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)";
$deptTypes = $deptJoinTypes . "i";
$deptParams = array_merge($deptJoinParams, [$branch_id]);
if ($filter_dept > 0) {
    $deptWhere .= " AND e.department_id = ?";
    $deptTypes .= "i";
    $deptParams[] = $filter_dept;
}
$department_breakdown = supervisorAnalyticsRows(
    $conn,
    "SELECT COALESCE(d.department_name, 'Unassigned') AS department_name,
            COUNT(DISTINCT e.employee_id) AS employee_count,
            COUNT(ev.evaluation_id) AS approved_count,
            ROUND(AVG(ev.total_score), 2) AS avg_score
     FROM employees e
     LEFT JOIN departments d ON e.department_id = d.department_id
     LEFT JOIN evaluations ev ON ev.employee_id = e.employee_id AND ev.status = 'Approved' $deptJoinDate
     $deptWhere
     GROUP BY d.department_id, d.department_name
     ORDER BY employee_count DESC, department_name ASC",
    $deptTypes,
    $deptParams
);

$staff_activity = supervisorAnalyticsRows(
    $conn,
    "SELECT COALESCE(u.full_name, 'Unknown Staff') AS staff_name,
            COUNT(*) AS submitted_count,
            SUM(ev.status = 'Pending Supervisor') AS pending_count,
            SUM(ev.status IN ('Pending Manager', 'Approved')) AS forwarded_count
     FROM evaluations ev
     JOIN employees e ON ev.employee_id = e.employee_id
     LEFT JOIN users u ON ev.submitted_by = u.user_id
     $evalWhere
     GROUP BY ev.submitted_by, u.full_name
     ORDER BY submitted_count DESC
     LIMIT 6",
    $evalTypes,
    $evalParams
);

$trend_labels = [];
$trend_values = [];
for ($i = 5; $i >= 0; $i--) {
    $month_ts = strtotime("-$i months");
    $trend_labels[] = date('M Y', $month_ts);
    $month = (int) date('n', $month_ts);
    $year = (int) date('Y', $month_ts);
    $trend_values[] = (int) supervisorAnalyticsScalar(
        $conn,
        "SELECT COUNT(*)
         FROM evaluations ev
         JOIN employees e ON ev.employee_id = e.employee_id
         WHERE ev.endorsed_by = ?
           AND e.branch_id = ?
           AND e.is_active = 1
           AND e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)
           AND $evaluation_type_filter
           AND MONTH(ev.endorsed_date) = ?
           AND YEAR(ev.endorsed_date) = ?"
           . ($filter_dept > 0 ? " AND e.department_id = ?" : ""),
        $filter_dept > 0 ? "iiiii" : "iiii",
        $filter_dept > 0 ? [$supervisor_id, $branch_id, $month, $year, $filter_dept] : [$supervisor_id, $branch_id, $month, $year]
    );
}

require_once '../includes/header.php';
?>

<style>
    .supervisor-analytics .filter-card {
        background: #fff;
        border: 1px solid #eef2e8;
        border-radius: 16px;
        box-shadow: 0 8px 24px rgba(12, 32, 8, 0.06);
        margin-bottom: 18px;
        padding: 16px;
    }

    .supervisor-analytics .insight-list {
        padding: 14px;
    }

    .supervisor-analytics .insight-item {
        align-items: center;
        background: #fff;
        border: 1px solid #f0f4eb;
        border-radius: 14px;
        display: grid;
        gap: 12px;
        grid-template-columns: 44px minmax(0, 1fr) auto;
        margin-bottom: 10px;
        padding: 12px;
    }

    .supervisor-analytics .insight-icon {
        align-items: center;
        background: rgba(41, 67, 6, 0.08);
        border-radius: 12px;
        color: var(--primary-blue);
        display: inline-flex;
        height: 44px;
        justify-content: center;
        width: 44px;
    }

    .supervisor-analytics .insight-title {
        font-weight: 800;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .supervisor-analytics .insight-meta {
        color: var(--text-muted);
        font-size: 0.74rem;
    }

    .supervisor-analytics .score-chip {
        border-radius: 999px;
        font-size: 0.78rem;
        font-weight: 800;
        padding: 6px 10px;
        text-align: center;
        white-space: nowrap;
    }

    .supervisor-analytics .dept-row,
    .supervisor-analytics .staff-row {
        border-bottom: 1px solid #eef2e8;
        padding: 12px 0;
    }

    .supervisor-analytics .dept-row:last-child,
    .supervisor-analytics .staff-row:last-child {
        border-bottom: 0;
    }

    .supervisor-analytics .chart-box {
        min-height: 260px;
        position: relative;
    }

    @media (max-width: 768px) {
        .supervisor-analytics .filter-card .btn {
            width: 100%;
        }

        .supervisor-analytics .insight-item {
            align-items: stretch;
            grid-template-columns: 42px minmax(0, 1fr);
        }

        .supervisor-analytics .score-chip {
            grid-column: 1 / -1;
            width: 100%;
        }

        .supervisor-analytics .insight-title {
            white-space: normal;
        }
    }
</style>

<div class="supervisor-analytics">
<div class="page-hero fadeup">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
        <div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:0;color:rgba(255,255,255,.55);">HR Supervisor · Branch Analytics · <?php echo $cohort === 'probationary' ? 'Probationary pathway' : 'Regular employees'; ?></div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-chart-bar me-2" style="color:#BD9414;"></i><?php echo e($branch_name); ?> — <?php echo $cohort === 'probationary' ? 'Probationary Analytics' : 'Regular Employee Analytics'; ?></h4>
            <p class="text-white-50 small mb-0 mt-2"><?php echo $cohort === 'probationary' ? 'Initial and Final evaluation results, shown separately from annual and quarterly employee performance.' : 'Annual and Quarterly evaluation results, shown separately from probationary regularization evaluations.'; ?></p>
        </div>
        <div style="color:rgba(255,255,255,.65);font-size:.8rem;">
            <i class="fas fa-sync-alt me-1"></i>Data as of <?php echo date('F d, Y'); ?>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-6 col-md-4 col-xl">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo number_format($total_employees); ?></div>
                        <div class="stat-label">Branch Employees</div>
                    </div>
                    <i class="fas fa-users stat-icon text-white-50"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo number_format($pending_validations); ?></div>
                        <div class="stat-label">Pending Validations</div>
                    </div>
                    <i class="fas fa-clipboard-check stat-icon" style="color:#ffc107;"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo number_format($validated_month); ?></div>
                        <div class="stat-label">Validated This Month</div>
                    </div>
                    <i class="fas fa-check-circle stat-icon" style="color:#28a745;"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo number_format($avg_score, 2); ?></div>
                        <div class="stat-label">Avg Approved Score</div>
                    </div>
                    <i class="fas fa-chart-line stat-icon" style="color:#17a2b8;"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo number_format($returned_evaluations); ?></div>
                        <div class="stat-label">Returned Evaluations</div>
                    </div>
                    <i class="fas fa-undo stat-icon" style="color:#dc3545;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="filter-card fadeup fadeup-1">
    <form method="GET" action="" class="row align-items-end g-3">
        <div class="col-md-2 col-6">
            <label class="form-label fw-semibold small">Date From</label>
            <input type="date" class="form-control form-control-sm" name="date_from" value="<?php echo e($date_from); ?>">
        </div>
        <div class="col-md-2 col-6">
            <label class="form-label fw-semibold small">Date To</label>
            <input type="date" class="form-control form-control-sm" name="date_to" value="<?php echo e($date_to); ?>">
        </div>
        <div class="col-md-3 col-6">
            <label class="form-label fw-semibold small">Department</label>
            <select class="form-select form-select-sm" name="department">
                <option value="">All Departments</option>
                <?php foreach ($department_options as $dept): ?>
                    <option value="<?php echo (int) $dept['department_id']; ?>" <?php echo $filter_dept === (int) $dept['department_id'] ? 'selected' : ''; ?>>
                        <?php echo e($dept['department_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 col-6">
            <label class="form-label fw-semibold small">Evaluation Group</label>
            <select class="form-select form-select-sm" name="cohort">
                <option value="regular" <?php echo $cohort === 'regular' ? 'selected' : ''; ?>>Regular employees</option>
                <option value="probationary" <?php echo $cohort === 'probationary' ? 'selected' : ''; ?>>Probationary → Regularization</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="fas fa-filter me-1"></i>Apply</button>
            <a href="analytics.php" class="btn btn-sm btn-outline-secondary px-3">Reset</a>
        </div>
    </form>
</div>

<p class="text-muted small mb-3">Evaluation metrics use only <?php echo $cohort === 'probationary' ? 'Initial and Final probationary evaluations' : 'Annual and Quarterly regular-employee evaluations'; ?>. Branch workforce headcount remains inclusive of all active employees.</p>

<div class="row g-4 mb-4 fadeup fadeup-2">
    <div class="col-lg-6">
        <div class="chart-card h-100">
            <div class="cc-header">
                <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Evaluation Status</h5>
            </div>
            <div class="cc-body">
                <div class="chart-box">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="chart-card h-100">
            <div class="cc-header">
                <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Endorsement Trend</h5>
            </div>
            <div class="cc-body">
                <div class="chart-box">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4 fadeup fadeup-3">
    <div class="col-lg-4">
        <div class="chart-card h-100">
            <div class="cc-header">
                <h5 class="mb-0"><i class="fas fa-medal me-2"></i>Performance Levels</h5>
            </div>
            <div class="cc-body">
                <div class="chart-box">
                    <canvas id="performanceChart"></canvas>
                </div>
                <div class="text-muted small mt-2"><?php echo number_format($approved_count); ?> approved evaluations in scope.</div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="chart-card h-100">
            <div class="cc-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Top Performers</h5>
                <span class="badge bg-light text-muted">Approved only</span>
            </div>
            <div class="cc-body p-0">
                <div class="insight-list">
                    <?php if (empty($top_performers)): ?>
                        <div class="empty-state-card text-center text-muted py-4">
                            <i class="fas fa-chart-bar d-block mb-2 opacity-25"></i>
                            No approved evaluations yet.
                        </div>
                    <?php else: ?>
                        <?php foreach ($top_performers as $row): ?>
                            <div class="insight-item">
                                <div class="insight-icon"><i class="fas fa-user-check"></i></div>
                                <div>
                                    <div class="insight-title"><?php echo e($row['employee_name']); ?></div>
                                    <div class="insight-meta">
                                        <span class="company-id-value"><?php echo e(getEmployeeDisplayId($row)); ?></span> &middot; <?php echo e($row['job_title'] ?? 'N/A'); ?> &middot; <?php echo e($row['department_name'] ?? 'N/A'); ?>
                                    </div>
                                </div>
                                <div class="score-chip bg-success-subtle text-success"><?php echo number_format((float) $row['total_score'], 2); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4 fadeup fadeup-4">
    <div class="col-lg-6">
        <div class="chart-card h-100">
            <div class="cc-header">
                <h5 class="mb-0"><i class="fas fa-exclamation-circle me-2"></i>Needs Attention</h5>
            </div>
            <div class="cc-body p-0">
                <div class="insight-list">
                    <?php if (empty($needs_improvement)): ?>
                        <div class="empty-state-card text-center text-muted py-4">
                            <i class="fas fa-check-circle d-block mb-2 opacity-25"></i>
                            No low-score approved evaluations in scope.
                        </div>
                    <?php else: ?>
                        <?php foreach ($needs_improvement as $row): ?>
                            <div class="insight-item">
                                <div class="insight-icon"><i class="fas fa-triangle-exclamation"></i></div>
                                <div>
                                    <div class="insight-title"><?php echo e($row['employee_name']); ?></div>
                                    <div class="insight-meta">
                                        <span class="company-id-value"><?php echo e(getEmployeeDisplayId($row)); ?></span> &middot; <?php echo e($row['performance_level'] ?: 'Low Score'); ?> &middot; <?php echo e($row['department_name'] ?? 'N/A'); ?>
                                    </div>
                                </div>
                                <div class="score-chip bg-danger-subtle text-danger"><?php echo number_format((float) $row['total_score'], 2); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="chart-card h-100">
            <div class="cc-header">
                <h5 class="mb-0"><i class="fas fa-sitemap me-2"></i>Department Breakdown</h5>
            </div>
            <div class="cc-body">
                <?php if (empty($department_breakdown)): ?>
                    <div class="empty-state-card text-center text-muted py-4">No department data available.</div>
                <?php else: ?>
                    <?php foreach ($department_breakdown as $row): ?>
                        <div class="dept-row">
                            <div class="d-flex justify-content-between gap-3">
                                <div>
                                    <div class="fw-bold"><?php echo e($row['department_name']); ?></div>
                                    <small class="text-muted"><?php echo (int) $row['employee_count']; ?> employees · <?php echo (int) $row['approved_count']; ?> approved evaluations</small>
                                </div>
                                <div class="fw-bold text-primary"><?php echo $row['avg_score'] !== null ? number_format((float) $row['avg_score'], 2) : '-'; ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="chart-card fadeup fadeup-5">
    <div class="cc-header">
        <h5 class="mb-0"><i class="fas fa-user-edit me-2"></i>Staff Submission Activity</h5>
    </div>
    <div class="cc-body">
        <?php if (empty($staff_activity)): ?>
            <div class="empty-state-card text-center text-muted py-4">No staff submissions in this scope.</div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($staff_activity as $row): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="staff-row">
                            <div class="fw-bold"><?php echo e($row['staff_name']); ?></div>
                            <small class="text-muted d-block mb-2"><?php echo (int) $row['submitted_count']; ?> submissions</small>
                            <div class="d-flex gap-2 flex-wrap">
                                <span class="badge bg-warning text-dark"><?php echo (int) $row['pending_count']; ?> pending</span>
                                <span class="badge bg-success"><?php echo (int) $row['forwarded_count']; ?> forwarded/approved</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;

    const statusLabels = <?php echo json_encode(array_keys($status_counts)); ?>;
    const statusValues = <?php echo json_encode(array_values($status_counts)); ?>;
    const performanceLabels = <?php echo json_encode(array_keys($performance_counts)); ?>;
    const performanceValues = <?php echo json_encode(array_values($performance_counts)); ?>;
    const trendLabels = <?php echo json_encode($trend_labels); ?>;
    const trendValues = <?php echo json_encode($trend_values); ?>;

    const gridColor = 'rgba(41, 67, 6, 0.08)';

    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusValues,
                backgroundColor: ['#ffc107', '#0d6efd', '#28a745', '#dc3545', '#6c757d'],
                borderWidth: 0
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            cutout: '62%'
        }
    });

    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [{
                label: 'Endorsed Evaluations',
                data: trendValues,
                borderColor: '#294306',
                backgroundColor: 'rgba(41, 67, 6, 0.12)',
                fill: true,
                tension: 0.35,
                pointRadius: 4
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: gridColor }, ticks: { precision: 0 } },
                x: { grid: { display: false } }
            }
        }
    });

    new Chart(document.getElementById('performanceChart'), {
        type: 'bar',
        data: {
            labels: performanceLabels,
            datasets: [{
                data: performanceValues,
                backgroundColor: ['#28a745', '#17a2b8', '#ffc107', '#dc3545'],
                borderRadius: 8
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: gridColor }, ticks: { precision: 0 } },
                x: { grid: { display: false }, ticks: { font: { size: 10 } } }
            }
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
