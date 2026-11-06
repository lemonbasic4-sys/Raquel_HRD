<?php
$page_title = 'Supervisor Reports';
require_once '../includes/session-check.php';
checkRole(['HR Supervisor']);
require_once '../includes/functions.php';
redirectWith(BASE_URL . '/supervisor/dashboard.php', 'info', 'Reports module is disabled.');

$supervisor_id = (int) ($_SESSION['user_id'] ?? 0);
$branch_id = (int) ($_SESSION['branch_id'] ?? 0);

function supervisorReportDate(string $value): string
{
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
}

function supervisorReportRows(mysqli $conn, string $sql, string $types = '', array $params = []): array
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

function supervisorReportScalar(mysqli $conn, string $sql, string $types = '', array $params = [])
{
    $rows = supervisorReportRows($conn, $sql, $types, $params);
    return $rows ? array_values($rows[0])[0] : null;
}

function supervisorReportValue($value): string
{
    if ($value === null || $value === '') {
        return 'N/A';
    }
    return (string) $value;
}

function supervisorReportBadgeClass(string $key, string $value): string
{
    if ($key === 'status') {
        if (in_array($value, ['Approved', 'Applied', 'Regular'], true)) {
            return 'bg-success';
        }
        if (in_array($value, ['Pending Supervisor', 'Pending', 'Probationary'], true)) {
            return 'bg-warning text-dark';
        }
        if (in_array($value, ['Returned', 'Rejected', 'Needs Improvement'], true)) {
            return 'bg-danger';
        }
        if (in_array($value, ['Pending Manager', 'Contractual'], true)) {
            return 'bg-info';
        }
    }

    if ($key === 'performance_level') {
        return getPerformanceBadgeClass($value);
    }

    return 'bg-secondary';
}

function supervisorBuildReport(mysqli $conn, string $report_type, int $branch_id, int $supervisor_id, int $department_id, string $date_from, string $date_to, string $branch_name): array
{
    $admin_filter = "e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)";

    if ($report_type === 'pending_endorsements') {
        $where = "WHERE ev.status = 'Pending Supervisor' AND ev.deleted_at IS NULL AND e.branch_id = ? AND e.is_active = 1 AND $admin_filter";
        $types = "i";
        $params = [$branch_id];
        if ($department_id > 0) {
            $where .= " AND e.department_id = ?";
            $types .= "i";
            $params[] = $department_id;
        }
        if ($date_from !== '') {
            $where .= " AND ev.submitted_date >= ?";
            $types .= "s";
            $params[] = $date_from;
        }
        if ($date_to !== '') {
            $where .= " AND ev.submitted_date <= ?";
            $types .= "s";
            $params[] = $date_to . ' 23:59:59';
        }

        $source = supervisorReportRows(
            $conn,
            "SELECT ev.evaluation_id, ev.evaluation_type, ev.total_score, ev.submitted_date,
                    e.employee_id, e.employee_code, CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
                    e.job_title, d.department_name, et.template_name, u.full_name AS submitted_by_name
             FROM evaluations ev
             JOIN employees e ON ev.employee_id = e.employee_id
             LEFT JOIN departments d ON e.department_id = d.department_id
             LEFT JOIN evaluation_templates et ON ev.template_id = et.template_id
             LEFT JOIN users u ON ev.submitted_by = u.user_id
             $where
             ORDER BY ev.submitted_date ASC, e.last_name ASC",
            $types,
            $params
        );

        $rows = [];
        $staff = [];
        $oldest = '';
        foreach ($source as $index => $row) {
            if (!$oldest && !empty($row['submitted_date'])) {
                $oldest = formatDate($row['submitted_date']);
            }
            if (!empty($row['submitted_by_name'])) {
                $staff[$row['submitted_by_name']] = true;
            }
            $rows[] = [
                'number' => $index + 1,
                'company_id' => getEmployeeDisplayId($row),
                'employee' => $row['employee_name'],
                'department' => supervisorReportValue($row['department_name']),
                'template' => supervisorReportValue($row['template_name']),
                'evaluation_type' => supervisorReportValue($row['evaluation_type']),
                'submitted_by' => supervisorReportValue($row['submitted_by_name']),
                'submitted_date' => !empty($row['submitted_date']) ? formatDateTime($row['submitted_date']) : 'N/A',
                'score' => $row['total_score'] !== null ? number_format((float) $row['total_score'], 2) : 'N/A',
                'status' => 'Pending Supervisor',
            ];
        }

        return [
            'title' => 'Pending Validation Report',
            'subtitle' => 'Branch evaluations waiting for Supervisor action.',
            'columns' => [
                'number' => '#',
                'company_id' => 'Company ID',
                'employee' => 'Employee',
                'department' => 'Department',
                'template' => 'Template',
                'evaluation_type' => 'Type',
                'submitted_by' => 'Submitted By',
                'submitted_date' => 'Submitted',
                'score' => 'Score',
                'status' => 'Status',
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Pending', 'value' => count($rows)],
                ['label' => 'Staff Submitters', 'value' => count($staff)],
                ['label' => 'Oldest Pending', 'value' => $oldest ?: 'N/A'],
                ['label' => 'Branch', 'value' => $branch_name],
            ],
        ];
    }

    if ($report_type === 'returned_evaluations') {
        $date_expr = "COALESCE(ev.approved_date, ev.endorsed_date, ev.submitted_date, ev.updated_at)";
        $where = "WHERE ev.status = 'Returned' AND ev.deleted_at IS NULL AND e.branch_id = ? AND e.is_active = 1 AND $admin_filter";
        $types = "i";
        $params = [$branch_id];
        if ($department_id > 0) {
            $where .= " AND e.department_id = ?";
            $types .= "i";
            $params[] = $department_id;
        }
        if ($date_from !== '') {
            $where .= " AND $date_expr >= ?";
            $types .= "s";
            $params[] = $date_from;
        }
        if ($date_to !== '') {
            $where .= " AND $date_expr <= ?";
            $types .= "s";
            $params[] = $date_to . ' 23:59:59';
        }

        $source = supervisorReportRows(
            $conn,
            "SELECT ev.evaluation_id, ev.evaluation_type, ev.total_score, ev.performance_level,
                    ev.submitted_date, ev.endorsed_date, ev.approved_date, ev.manager_comments, ev.supervisor_comments,
                    e.employee_id, e.employee_code, CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
                    e.job_title, d.department_name, et.template_name, u.full_name AS submitted_by_name
             FROM evaluations ev
             JOIN employees e ON ev.employee_id = e.employee_id
             LEFT JOIN departments d ON e.department_id = d.department_id
             LEFT JOIN evaluation_templates et ON ev.template_id = et.template_id
             LEFT JOIN users u ON ev.submitted_by = u.user_id
             $where
             ORDER BY $date_expr DESC, e.last_name ASC",
            $types,
            $params
        );

        $rows = [];
        $employees = [];
        foreach ($source as $index => $row) {
            $employees[(int) $row['employee_id']] = true;
            $return_note = trim((string) ($row['manager_comments'] ?: $row['supervisor_comments'] ?: 'No return note recorded.'));
            $rows[] = [
                'number' => $index + 1,
                'company_id' => getEmployeeDisplayId($row),
                'employee' => $row['employee_name'],
                'department' => supervisorReportValue($row['department_name']),
                'template' => supervisorReportValue($row['template_name']),
                'submitted_by' => supervisorReportValue($row['submitted_by_name']),
                'submitted_date' => !empty($row['submitted_date']) ? formatDateTime($row['submitted_date']) : 'N/A',
                'score' => $row['total_score'] !== null ? number_format((float) $row['total_score'], 2) : 'N/A',
                'status' => 'Returned',
                'notes' => $return_note,
            ];
        }

        return [
            'title' => 'Returned Evaluation Report',
            'subtitle' => 'Returned evaluations in the assigned branch.',
            'columns' => [
                'number' => '#',
                'company_id' => 'Company ID',
                'employee' => 'Employee',
                'department' => 'Department',
                'template' => 'Template',
                'submitted_by' => 'Submitted By',
                'submitted_date' => 'Submitted',
                'score' => 'Score',
                'status' => 'Status',
                'notes' => 'Return Notes',
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Returned', 'value' => count($rows)],
                ['label' => 'Employees', 'value' => count($employees)],
                ['label' => 'Branch', 'value' => $branch_name],
                ['label' => 'Date Scope', 'value' => ($date_from !== '' || $date_to !== '') ? trim(($date_from ?: 'Start') . ' to ' . ($date_to ?: 'Today')) : 'All Dates'],
            ],
        ];
    }

    if ($report_type === 'score_summary') {
        $join = "LEFT JOIN evaluations ev ON ev.employee_id = e.employee_id AND ev.status = 'Approved' AND ev.deleted_at IS NULL";
        $join_types = "";
        $join_params = [];
        if ($date_from !== '') {
            $join .= " AND ev.approved_date >= ?";
            $join_types .= "s";
            $join_params[] = $date_from;
        }
        if ($date_to !== '') {
            $join .= " AND ev.approved_date <= ?";
            $join_types .= "s";
            $join_params[] = $date_to . ' 23:59:59';
        }

        $where = "WHERE e.branch_id = ? AND e.is_active = 1 AND $admin_filter";
        $where_types = "i";
        $where_params = [$branch_id];
        if ($department_id > 0) {
            $where .= " AND e.department_id = ?";
            $where_types .= "i";
            $where_params[] = $department_id;
        }

        $source = supervisorReportRows(
            $conn,
            "SELECT COALESCE(d.department_name, 'Unassigned') AS department_name,
                    COUNT(DISTINCT e.employee_id) AS employee_count,
                    COUNT(ev.evaluation_id) AS approved_count,
                    ROUND(AVG(ev.total_score), 2) AS avg_score,
                    SUM(ev.performance_level = 'Outstanding') AS outstanding_count,
                    SUM(ev.performance_level = 'Exceeds Expectations') AS exceeds_count,
                    SUM(ev.performance_level = 'Meets Expectations') AS meets_count,
                    SUM(ev.performance_level = 'Needs Improvement') AS needs_count
             FROM employees e
             LEFT JOIN departments d ON e.department_id = d.department_id
             $join
             $where
             GROUP BY d.department_id, d.department_name
             ORDER BY department_name ASC",
            $join_types . $where_types,
            array_merge($join_params, $where_params)
        );

        $rows = [];
        $total_employees = 0;
        $total_evals = 0;
        $score_sum = 0;
        $score_count = 0;
        foreach ($source as $index => $row) {
            $avg = $row['avg_score'] !== null ? (float) $row['avg_score'] : null;
            $eval_count = (int) $row['approved_count'];
            $total_employees += (int) $row['employee_count'];
            $total_evals += $eval_count;
            if ($avg !== null && $eval_count > 0) {
                $score_sum += $avg * $eval_count;
                $score_count += $eval_count;
            }
            $rows[] = [
                'number' => $index + 1,
                'department' => $row['department_name'],
                'employees' => (int) $row['employee_count'],
                'approved_evaluations' => $eval_count,
                'average_score' => $avg !== null ? number_format($avg, 2) : 'N/A',
                'outstanding' => (int) $row['outstanding_count'],
                'exceeds_expectations' => (int) $row['exceeds_count'],
                'meets_expectations' => (int) $row['meets_count'],
                'needs_improvement' => (int) $row['needs_count'],
            ];
        }

        return [
            'title' => 'Evaluation Score Summary',
            'subtitle' => 'Approved evaluation scores grouped by department.',
            'columns' => [
                'number' => '#',
                'department' => 'Department',
                'employees' => 'Employees',
                'approved_evaluations' => 'Approved Evaluations',
                'average_score' => 'Average Score',
                'outstanding' => 'Outstanding',
                'exceeds_expectations' => 'Exceeds',
                'meets_expectations' => 'Meets',
                'needs_improvement' => 'Needs Improvement',
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Departments', 'value' => count($rows)],
                ['label' => 'Employees', 'value' => $total_employees],
                ['label' => 'Approved Evaluations', 'value' => $total_evals],
                ['label' => 'Average Score', 'value' => $score_count > 0 ? number_format($score_sum / $score_count, 2) : 'N/A'],
            ],
        ];
    }

    $where = "WHERE e.branch_id = ? AND e.is_active = 1 AND e.deleted_at IS NULL AND $admin_filter";
    $types = "i";
    $params = [$branch_id];
    if ($department_id > 0) {
        $where .= " AND e.department_id = ?";
        $types .= "i";
        $params[] = $department_id;
    }
    if ($date_from !== '') {
        $where .= " AND e.hire_date >= ?";
        $types .= "s";
        $params[] = $date_from;
    }
    if ($date_to !== '') {
        $where .= " AND e.hire_date <= ?";
        $types .= "s";
        $params[] = $date_to;
    }

    $source = supervisorReportRows(
        $conn,
        "SELECT e.employee_id, e.employee_code, e.last_name, e.first_name, e.middle_name, e.job_title,
                e.hire_date, e.employment_status, e.employment_type,
                d.department_name, c.mobile_number, c.personal_email
         FROM employees e
         LEFT JOIN departments d ON e.department_id = d.department_id
         LEFT JOIN employee_contacts c ON e.employee_id = c.employee_id
         $where
         ORDER BY e.last_name, e.first_name",
        $types,
        $params
    );

    $rows = [];
    $departments = [];
    $regular = 0;
    $probationary = 0;
    foreach ($source as $index => $row) {
        if (!empty($row['department_name'])) {
            $departments[$row['department_name']] = true;
        }
        if ($row['employment_status'] === 'Regular') {
            $regular++;
        }
        if ($row['employment_status'] === 'Probationary') {
            $probationary++;
        }
        $full_name = $row['last_name'] . ', ' . $row['first_name'] . (!empty($row['middle_name']) ? ' ' . $row['middle_name'] : '');
        $rows[] = [
            'number' => $index + 1,
            'company_id' => getEmployeeDisplayId($row),
            'employee' => $full_name,
            'position' => supervisorReportValue($row['job_title']),
            'department' => supervisorReportValue($row['department_name']),
            'hire_date' => !empty($row['hire_date']) ? formatDate($row['hire_date']) : 'N/A',
            'status' => supervisorReportValue($row['employment_status']),
            'employment_type' => supervisorReportValue($row['employment_type']),
            'mobile' => supervisorReportValue($row['mobile_number']),
            'email' => supervisorReportValue($row['personal_email']),
        ];
    }

    return [
        'title' => 'Branch Employee Roster',
        'subtitle' => 'Active employees in the assigned branch.',
        'columns' => [
            'number' => '#',
            'company_id' => 'Company ID',
            'employee' => 'Employee',
            'position' => 'Position',
            'department' => 'Department',
            'hire_date' => 'Hire Date',
            'status' => 'Status',
            'employment_type' => 'Type',
            'mobile' => 'Mobile',
            'email' => 'Email',
        ],
        'rows' => $rows,
        'summary' => [
            ['label' => 'Active Employees', 'value' => count($rows)],
            ['label' => 'Departments', 'value' => count($departments)],
            ['label' => 'Regular', 'value' => $regular],
            ['label' => 'Probationary', 'value' => $probationary],
        ],
    ];
}

$report_definitions = [
    'branch_roster' => [
        'label' => 'Branch Employee Roster',
        'icon' => 'fas fa-address-book',
        'description' => 'Active employees, assignments, contact details, and employment status.',
    ],
    'pending_endorsements' => [
        'label' => 'Pending Validation Report',
        'icon' => 'fas fa-clipboard-check',
        'description' => 'Evaluations waiting for Supervisor validation.',
    ],
    'returned_evaluations' => [
        'label' => 'Returned Evaluation Report',
        'icon' => 'fas fa-undo',
        'description' => 'Returned evaluations and recorded notes.',
    ],
    'score_summary' => [
        'label' => 'Evaluation Score Summary',
        'icon' => 'fas fa-chart-line',
        'description' => 'Approved score breakdown by department.',
    ],
];

$report_type = array_key_exists($_GET['report_type'] ?? '', $report_definitions) ? $_GET['report_type'] : 'branch_roster';
$department_id = isset($_GET['department']) && $_GET['department'] !== '' ? max(0, (int) $_GET['department']) : 0;
$date_from = supervisorReportDate(trim($_GET['date_from'] ?? ''));
$date_to = supervisorReportDate(trim($_GET['date_to'] ?? ''));
$export_type = strtolower(trim($_GET['export'] ?? ''));

$branch_name = supervisorReportScalar(
    $conn,
    "SELECT branch_name FROM branches WHERE branch_id = ?",
    "i",
    [$branch_id]
) ?: 'Assigned Branch';

$department_options = supervisorReportRows(
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

$report = supervisorBuildReport($conn, $report_type, $branch_id, $supervisor_id, $department_id, $date_from, $date_to, $branch_name);
$date_scope_label = ($date_from !== '' || $date_to !== '') ? trim(($date_from ?: 'Start') . ' to ' . ($date_to ?: 'Today')) : 'All Dates';

if ($export_type === 'csv') {
    $filename = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $report['title'])) . '_' . date('Y-m-d_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, [$report['title']]);
    fputcsv($output, ['Branch', $branch_name]);
    fputcsv($output, ['Generated', date('M d, Y h:i A')]);
    fputcsv($output, ['Date Scope', $date_scope_label]);
    fputcsv($output, []);
    fputcsv($output, array_values($report['columns']));

    foreach ($report['rows'] as $row) {
        $csv_row = [];
        foreach (array_keys($report['columns']) as $key) {
            $csv_row[] = $row[$key] ?? '';
        }
        fputcsv($output, $csv_row);
    }

    fputcsv($output, []);
    fputcsv($output, ['Total Records', count($report['rows'])]);
    fclose($output);
    exit;
}

$export_params = [
    'report_type' => $report_type,
    'department' => $department_id ?: '',
    'date_from' => $date_from,
    'date_to' => $date_to,
    'export' => 'csv',
];

require_once '../includes/header.php';
?>

<style>
    .supervisor-reports .scope-card {
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.14);
        border-radius: 14px;
        color: #fff;
        padding: 14px 16px;
    }

    .supervisor-reports .report-type-list {
        display: grid;
        gap: 10px;
    }

    .supervisor-reports .report-filter-card .form-label {
        color: #344357;
        font-size: 0.76rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    .supervisor-reports .report-summary-grid {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        margin-bottom: 16px;
    }

    .supervisor-reports .report-summary-card {
        background: #f8faf7;
        border: 1px solid #eef2e8;
        border-radius: 12px;
        padding: 13px 14px;
    }

    .supervisor-reports .report-summary-card span {
        color: var(--text-muted);
        display: block;
        font-size: 0.72rem;
        font-weight: 800;
        margin-bottom: 4px;
        text-transform: uppercase;
    }

    .supervisor-reports .report-summary-card strong {
        color: #1a2e06;
        font-size: 1rem;
    }

    .supervisor-reports .report-preview-table th {
        color: #4b5563;
        font-size: 0.72rem;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .supervisor-reports .report-preview-table td {
        font-size: 0.85rem;
        vertical-align: middle;
    }

    .supervisor-reports .report-empty {
        color: var(--text-muted);
        padding: 42px 18px;
        text-align: center;
    }

    @media (max-width: 992px) {
        .supervisor-reports .report-summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 768px) {
        .supervisor-reports .report-builder-grid {
            grid-template-columns: 1fr;
        }

        .supervisor-reports .report-filter-card .btn,
        .supervisor-reports .report-action-bar .btn {
            width: 100%;
        }

        .supervisor-reports .report-preview-table thead {
            display: none;
        }

        .supervisor-reports .report-preview-table,
        .supervisor-reports .report-preview-table tbody,
        .supervisor-reports .report-preview-table tr,
        .supervisor-reports .report-preview-table td {
            display: block;
            width: 100%;
        }

        .supervisor-reports .report-preview-table tbody {
            padding: 12px;
        }

        .supervisor-reports .report-preview-table tr {
            background: #fff;
            border: 1px solid #eef2e8;
            border-radius: 14px;
            box-shadow: 0 8px 18px rgba(12, 32, 8, 0.06);
            margin-bottom: 14px;
            padding: 12px;
        }

        .supervisor-reports .report-preview-table td {
            align-items: flex-start;
            border: 0;
            display: flex;
            gap: 12px;
            justify-content: space-between;
            padding: 8px 0 !important;
            text-align: right;
        }

        .supervisor-reports .report-preview-table td::before {
            color: var(--text-muted);
            content: attr(data-label);
            flex: 0 0 116px;
            font-size: 0.68rem;
            font-weight: 800;
            text-align: left;
            text-transform: uppercase;
        }
    }

    @media (max-width: 576px) {
        .supervisor-reports .report-summary-grid {
            grid-template-columns: 1fr;
        }
    }

    @media print {
        .sidebar,
        .sidebar-overlay,
        .top-navbar,
        .page-hero,
        .report-builder-grid,
        .report-action-bar {
            display: none !important;
        }

        .main-content {
            margin: 0 !important;
            padding: 0 !important;
        }

        .content-card {
            border: 0 !important;
            box-shadow: none !important;
        }

        .supervisor-reports .report-preview-table {
            font-size: 10px;
        }
    }
</style>

<div class="supervisor-reports reports-module">
    <div class="page-hero fadeup">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
            <div>
                <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:0;color:rgba(255,255,255,.55);">HR Supervisor · Reports</div>
                <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-file-alt me-2" style="color:#BD9414;"></i>Branch Reports</h4>
                <p class="text-white-50 small mb-0 mt-2">Generate branch-focused reports to support workforce monitoring, evaluation follow-up, and HR decisions.</p>
            </div>
            <div class="scope-card">
                <div class="small opacity-75">Assigned Branch</div>
                <div class="fw-bold"><?php echo e($branch_name); ?></div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-value">5</div>
                            <div class="stat-label">Report Types</div>
                        </div>
                        <i class="fas fa-layer-group stat-icon text-white-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-value"><?php echo number_format(count($report['rows'])); ?></div>
                            <div class="stat-label">Rows Shown</div>
                        </div>
                        <i class="fas fa-table stat-icon" style="color:#BD9414;"></i>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-value">CSV</div>
                            <div class="stat-label">Export</div>
                        </div>
                        <i class="fas fa-file-csv stat-icon" style="color:#28a745;"></i>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-value">Print</div>
                            <div class="stat-label">Printable</div>
                        </div>
                        <i class="fas fa-print stat-icon" style="color:#17a2b8;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form method="GET" action="" class="fadeup fadeup-1">
        <input type="hidden" name="report_type" id="reportType" value="<?php echo e($report_type); ?>">

        <div class="report-builder-grid mb-4">
            <section class="content-card report-picker-card">
                <div class="card-header">
                    <h5><i class="fas fa-layer-group me-2"></i>Report Type</h5>
                    <span class="report-step-badge">1</span>
                </div>
                <div class="card-body">
                    <div class="report-type-list" id="reportTypeCards">
                        <?php foreach ($report_definitions as $key => $definition): ?>
                            <button type="button" class="report-type-card <?php echo $report_type === $key ? 'active' : ''; ?>" data-type="<?php echo e($key); ?>" data-label="<?php echo e($definition['label']); ?>">
                                <span class="rtc-icon"><i class="<?php echo e($definition['icon']); ?>"></i></span>
                                <span class="rtc-info">
                                    <strong><?php echo e($definition['label']); ?></strong>
                                    <small><?php echo e($definition['description']); ?></small>
                                </span>
                                <span class="rtc-check"><i class="fas fa-check"></i></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="content-card report-filter-card">
                <div class="card-header">
                    <h5><i class="fas fa-sliders-h me-2"></i>Filters</h5>
                    <span class="report-step-badge">2</span>
                </div>
                <div class="card-body">
                    <div class="report-selected-summary mb-3">
                        <div>
                            <span class="text-muted small d-block">Selected report</span>
                            <strong id="selectedReportName"><?php echo e($report_definitions[$report_type]['label']); ?></strong>
                        </div>
                        <span class="report-format-pill"><i class="fas fa-building me-1"></i><?php echo e($branch_name); ?></span>
                    </div>

                    <div class="row g-3">
                        <div class="col-lg-4">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="department">
                                <option value="">All Departments</option>
                                <?php foreach ($department_options as $department): ?>
                                    <option value="<?php echo (int) $department['department_id']; ?>" <?php echo $department_id === (int) $department['department_id'] ? 'selected' : ''; ?>>
                                        <?php echo e($department['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label">Date From</label>
                            <input type="date" class="form-control" name="date_from" value="<?php echo e($date_from); ?>">
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label">Date To</label>
                            <input type="date" class="form-control" name="date_to" value="<?php echo e($date_to); ?>">
                        </div>
                    </div>

                    <div class="report-filter-actions mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i>Generate Preview
                        </button>
                        <a href="reports.php" class="btn btn-outline-secondary">
                            <i class="fas fa-rotate-left me-1"></i>Reset
                        </a>
                    </div>
                </div>
            </section>
        </div>
    </form>

    <div class="report-action-bar mb-3 fadeup fadeup-2">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="report-result-badge">
                    <i class="fas fa-table me-1"></i><?php echo number_format(count($report['rows'])); ?> records
                </span>
                <span class="report-scope-badge"><?php echo e($branch_name); ?></span>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-success btn-sm" href="reports.php?<?php echo e(http_build_query($export_params)); ?>">
                    <i class="fas fa-file-csv me-1"></i>CSV
                </a>
                <button class="btn btn-info btn-sm text-white" type="button" onclick="window.print()">
                    <i class="fas fa-print me-1"></i>Print
                </button>
            </div>
        </div>
    </div>

    <div class="content-card fadeup fadeup-3">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h5 class="mb-1"><i class="fas fa-eye me-2 text-primary"></i><?php echo e($report['title']); ?></h5>
                <small class="text-muted"><?php echo e($report['subtitle']); ?></small>
            </div>
            <span class="badge bg-light text-muted border"><?php echo e($date_scope_label); ?></span>
        </div>
        <div class="card-body">
            <div class="report-summary-grid">
                <?php foreach ($report['summary'] as $item): ?>
                    <div class="report-summary-card">
                        <span><?php echo e($item['label']); ?></span>
                        <strong><?php echo e($item['value']); ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($report['rows'])): ?>
                <div class="report-empty">
                    <i class="fas fa-file-alt fa-3x mb-3 d-block opacity-25"></i>
                    <div class="fw-bold">No records found</div>
                    <p class="small mb-0">Adjust the selected report or filters and generate again.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle report-preview-table mb-0">
                        <thead class="bg-light">
                            <tr>
                                <?php foreach ($report['columns'] as $label): ?>
                                    <th><?php echo e($label); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report['rows'] as $row): ?>
                                <tr>
                                    <?php foreach ($report['columns'] as $key => $label): ?>
                                        <?php $cell_value = supervisorReportValue($row[$key] ?? ''); ?>
                                        <td data-label="<?php echo e($label); ?>">
                                            <?php if (in_array($key, ['status', 'performance_level'], true)): ?>
                                                <span class="badge <?php echo supervisorReportBadgeClass($key, $cell_value); ?>"><?php echo e($cell_value); ?></span>
                                            <?php elseif ($key === 'company_id'): ?>
                                                <span class="company-id-value"><?php echo e($cell_value); ?></span>
                                            <?php else: ?>
                                                <?php echo e($cell_value); ?>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const reportTypeInput = document.getElementById('reportType');
    const selectedReportName = document.getElementById('selectedReportName');
    document.querySelectorAll('.supervisor-reports .report-type-card').forEach(function (card) {
        card.addEventListener('click', function () {
            document.querySelectorAll('.supervisor-reports .report-type-card').forEach(function (item) {
                item.classList.remove('active');
            });
            card.classList.add('active');
            reportTypeInput.value = card.dataset.type;
            selectedReportName.textContent = card.dataset.label;
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
