<?php
$page_title = 'Manager Dashboard';
require_once '../includes/session-check.php';
checkRole(['HR Manager']);
require_once '../includes/header.php';

// Fetch stats
$branch_id = $_SESSION['branch_id'];

// Resolve the user's assigned branch name for auto-selection in the UI
$user_branch_name = '';
if (!empty($branch_id)) {
    $br_stmt = $conn->prepare("SELECT branch_name FROM branches WHERE branch_id = ? LIMIT 1");
    $br_stmt->bind_param("i", $branch_id);
    $br_stmt->execute();
    $br_res = $br_stmt->get_result();
    if ($br_row = $br_res->fetch_assoc()) {
        $user_branch_name = $br_row['branch_name'];
    }
    $br_stmt->close();
}

$total_employees = $conn->query("SELECT COUNT(*) as c FROM employees WHERE is_active = 1 AND employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)")->fetch_assoc()['c'];
$pending_evals = $conn->query("SELECT COUNT(*) as c FROM evaluations WHERE status = 'Pending Manager'")->fetch_assoc()['c'];
$avg_score_result = $conn->query("SELECT AVG(total_score) as avg FROM evaluations WHERE status = 'Approved'");
$avg_score = round($avg_score_result->fetch_assoc()['avg'] ?? 0, 1);
$new_evals_month = $conn->query("SELECT COUNT(*) as c FROM evaluations WHERE MONTH(submitted_date) = MONTH(CURRENT_DATE()) AND YEAR(submitted_date) = YEAR(CURRENT_DATE())")->fetch_assoc()['c'];
$approved_evals = $conn->query("SELECT COUNT(*) as c FROM evaluations WHERE status = 'Approved'")->fetch_assoc()['c'];
$total_branches_res = $conn->query("SELECT COUNT(*) as c FROM branches");
$total_branches = $total_branches_res->fetch_assoc()['c'];

// Fetch branches with employee counts for the insights explorer
$branches_insights_res = $conn->query("
    SELECT b.branch_id, b.branch_name, b.location, 
    (SELECT COUNT(*) FROM employees e WHERE e.branch_id = b.branch_id AND e.is_active = 1 AND e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)) as emp_count
    FROM branches b
    ORDER BY b.branch_name ASC
");
$branches_insights = $branches_insights_res->fetch_all(MYSQLI_ASSOC);
$departments_dashboard = $conn->query("SELECT department_id, department_name FROM departments WHERE is_active = 1 AND deleted_at IS NULL ORDER BY department_name")->fetch_all(MYSQLI_ASSOC);
$total_emp_calc = $total_employees > 0 ? $total_employees : 1;

$recent_evaluations = $conn->query("SELECT ev.evaluation_id, ev.evaluation_type, ev.status, ev.total_score,
        ev.submitted_date, CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
        e.job_title, d.department_name
    FROM evaluations ev
    JOIN employees e ON e.employee_id = ev.employee_id
    LEFT JOIN departments d ON d.department_id = e.department_id
    ORDER BY COALESCE(ev.submitted_date, ev.created_at) DESC, ev.evaluation_id DESC
    LIMIT 5")->fetch_all(MYSQLI_ASSOC);


// Gender Counts (Excluding Admins)
$male_count = $conn->query("SELECT COUNT(*) as c FROM employees WHERE gender = 'Male' AND is_active = 1 AND employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)")->fetch_assoc()['c'];
$female_count = $conn->query("SELECT COUNT(*) as c FROM employees WHERE gender = 'Female' AND is_active = 1 AND employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)")->fetch_assoc()['c'];

$pending_rows = [];
$pending_result = $conn->query("SELECT ev.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
    e.job_title, e.profile_picture, et.template_name, u.full_name AS submitted_by_name
    FROM evaluations ev
    LEFT JOIN employees e ON ev.employee_id = e.employee_id
    LEFT JOIN users u ON ev.submitted_by = u.user_id
    LEFT JOIN evaluation_templates et ON ev.template_id = et.template_id
    WHERE ev.status = 'Pending Manager'
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
            'job_title' => $row['job_title'] ?? '',
            'evaluations' => [],
        ];
    }
    $pending_groups[$employeeId]['evaluations'][] = $row;
}
$pending_groups = array_values($pending_groups);
$queue_preview_groups = array_slice($pending_groups, 0, 5);
$queue_employee_count = count($pending_groups);

// Organization evaluation packages use routed approval steps, not the legacy
// evaluations.status = 'Pending Manager' queue above. Include direct and
// unclaimed HR Manager role assignments so the dashboard agrees with its badge.
$pending_packages = [];
if (ensureOrganizationEvaluationPackageSchema($conn)) {
    syncPendingOrganizationPackageGovernanceApprovers($conn);
    syncWaitingOrganizationPackages($conn);
    $dashboard_user_id = (int)($_SESSION['user_id'] ?? 0);
    if ($dashboard_user_id > 0) {
        $dashboard_reviewer_match = organizationPackageReviewerMatchSql('rs');
        $package_stmt = $conn->prepare("SELECT DISTINCT ep.*, d.department_name, et.template_name,
                rs.step_label, rs.step_type, rs.action_status
            FROM evaluation_packages ep
            JOIN evaluation_package_route_steps rs ON rs.package_id = ep.package_id
            JOIN departments d ON d.department_id = ep.department_id
            JOIN evaluation_templates et ON et.template_id = ep.template_id
            WHERE ($dashboard_reviewer_match OR (rs.eligible_role = ? AND rs.claimed_at IS NULL))
              AND ((rs.step_order = 1 AND rs.action_status IN ('Pending', 'Waiting'))
                   OR (rs.step_order > 1 AND rs.action_status = 'Pending'))
              AND ep.status NOT IN ('Approved and Applied', 'Cancelled')
            ORDER BY ep.updated_at DESC");
        $dashboard_role = (string)($_SESSION['role'] ?? 'HR Manager');
        $package_stmt->bind_param('iis', $dashboard_user_id, $dashboard_user_id, $dashboard_role);
        $package_stmt->execute();
        $pending_packages = $package_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $package_stmt->close();
    }
}
$pending_package_count = count($pending_packages);
$dashboard_queue_count = $queue_employee_count + $pending_package_count;

// 1. Performance Distribution Data
$perf_dist = $conn->query("SELECT performance_level, COUNT(*) as count FROM evaluations WHERE status = 'Approved' AND performance_level IS NOT NULL GROUP BY performance_level");
$perf_data = ['Outstanding' => 0, 'Exceeds Expectations' => 0, 'Meets Expectations' => 0, 'Needs Improvement' => 0];
while ($row = $perf_dist->fetch_assoc()) {
    if (isset($perf_data[$row['performance_level']])) {
        $perf_data[$row['performance_level']] = (int) $row['count'];
    }
}
$has_queue_content = $queue_employee_count > 0;

// 2. Evaluation Status Data
$status_dist = $conn->query("SELECT status, COUNT(*) as count FROM evaluations GROUP BY status");
$status_labels = [];
$status_counts = [];
while ($row = $status_dist->fetch_assoc()) {
    $status_labels[] = $row['status'];
    $status_counts[] = (int) $row['count'];
}

// 3. Top Performers Data
$top_performers = $conn->query("
    SELECT ev.total_score, ev.performance_level, e.employee_id,
           CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.job_title, d.department_name
    FROM evaluations ev
    JOIN employees e ON ev.employee_id = e.employee_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    WHERE ev.status = 'Approved'
    ORDER BY ev.total_score DESC, ev.submitted_date DESC
    LIMIT 5
");

// 4. Monthly Trends (last 6 months)
$monthly_data = [];
for ($i = 5; $i >= 0; $i--) {
    $month_label = date('M Y', strtotime("-$i months"));
    $month_num = date('n', strtotime("-$i months"));
    $year_num = date('Y', strtotime("-$i months"));
    $avg_q = $conn->query("SELECT AVG(total_score) as avg_score FROM evaluations WHERE status = 'Approved' AND MONTH(approved_date) = $month_num AND YEAR(approved_date) = $year_num");
    $avg_val = round($avg_q->fetch_assoc()['avg_score'] ?? 0, 1);
    $monthly_data[] = ['label' => $month_label, 'value' => $avg_val];
}

// 5. Branch Comparison
$branch_comp_data = [];
$branch_q = $conn->query("SELECT b.branch_name, AVG(ev.total_score) as avg_score
    FROM evaluations ev
    LEFT JOIN employees e ON ev.employee_id = e.employee_id
    LEFT JOIN branches b ON e.branch_id = b.branch_id
    WHERE ev.status = 'Approved' AND b.branch_name IS NOT NULL
    GROUP BY b.branch_id, b.branch_name
    ORDER BY avg_score DESC LIMIT 10");
while ($row = $branch_q->fetch_assoc()) {
    $branch_comp_data[] = ['label' => $row['branch_name'], 'value' => round($row['avg_score'], 1)];
}

// 6. Age Distribution Data
$age_dist_q = $conn->query("
    SELECT
        CASE
            WHEN age < 25 THEN '18-24'
            WHEN age BETWEEN 25 AND 34 THEN '25-34'
            WHEN age BETWEEN 35 AND 44 THEN '35-44'
            WHEN age BETWEEN 45 AND 54 THEN '45-54'
            ELSE '55+'
        END as age_group,
        COUNT(*) as count
    FROM (
        SELECT FLOOR(DATEDIFF(CURRENT_DATE, date_of_birth) / 365.25) as age
        FROM employees
        WHERE is_active = 1
        AND employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)
    ) as employee_ages
    GROUP BY age_group
    ORDER BY age_group ASC
");

$age_labels = ['18-24', '25-34', '35-44', '45-54', '55+'];
$age_counts = array_fill(0, count($age_labels), 0);
$age_map = array_flip($age_labels);

while ($row = $age_dist_q->fetch_assoc()) {
    if (isset($age_map[$row['age_group']])) {
        $age_counts[$age_map[$row['age_group']]] = (int)$row['count'];
    }
}
$total_age_tracked = array_sum($age_counts);

// ── Non-Regular Watchlist ────────────────────────────────────────────────────
$watchlist_departments = $conn->query("SELECT department_id, department_name FROM departments WHERE is_active = 1 AND deleted_at IS NULL ORDER BY department_name");
$expiring_staff = getExpiringNonRegularEmployees($conn, 60);
$expiring_count = count($expiring_staff);
$overdue_count_watchlist  = count(array_filter($expiring_staff, fn($r) => $r['urgency'] === 'overdue'));
$critical_count_watchlist = count(array_filter($expiring_staff, fn($r) => $r['urgency'] === 'critical'));
?>


<style>
    /* Premium Approval Tabs */
    .approval-tabs .nav-link {
        border: none;
        padding: 12px 20px;
        color: var(--text-muted);
        font-weight: 600;
        font-size: 0.9rem;
        position: relative;
        transition: all 0.3s;
    }
    .approval-tabs .nav-link.active {
        color: var(--primary-blue) !important;
        background: transparent !important;
    }
    .approval-tabs .nav-link.active::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 20px;
        right: 20px;
        height: 3px;
        background: var(--primary-blue);
        border-radius: 10px;
    }

    /* Give the dashboard queue the same strong section-heading treatment as
       the other HR dashboard panels, using the established system colors. */
    #approvalQueueSection > .col-12 > .chart-card {
        overflow: hidden;
        border-radius: 16px;
    }

    #approvalQueueSection .approval-queue-heading {
        min-height: 58px;
        padding: 12px 20px;
        background: #1b2e04;
        color: #fff;
    }

    #approvalQueueSection .approval-queue-heading h5 {
        color: #fff;
        font-size: 1rem;
        font-weight: 700;
    }

    #approvalQueueSection .approval-queue-heading .queue-count {
        display: inline-flex;
        min-width: 24px;
        height: 24px;
        align-items: center;
        justify-content: center;
        margin-left: 7px;
        padding: 0 7px;
        border-radius: 20px;
        background: #bd9414;
        color: #17220d;
        font-size: .72rem;
        font-weight: 800;
    }

    #approvalQueueSection .approval-queue-heading .queue-view-all {
        border: 1px solid rgba(255, 255, 255, .35);
        border-radius: 8px;
        color: #fff;
        font-size: .76rem;
        font-weight: 600;
        padding: 7px 13px;
        text-decoration: none;
        transition: background .15s ease;
    }

    #approvalQueueSection .approval-queue-heading .queue-view-all:hover {
        background: rgba(255, 255, 255, .12);
    }

    #approvalQueueSection .approval-list {
        padding: 14px 18px;
        background: #f8faf7;
    }

    #approvalQueueSection .approval-item,
    #approvalQueueSection .approval-group-wrap {
        border-color: #e6ebe3;
        box-shadow: 0 2px 8px rgba(23, 50, 13, .045);
    }

    #approvalQueueSection .approval-item:hover {
        border-color: #bd9414;
        transform: translateY(-1px);
        box-shadow: 0 5px 14px rgba(23, 50, 13, .09);
    }

    /* Approval List Cards */
    .approval-list {
        padding: 15px;
    }
    .approval-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 15px;
        background: #fff;
        border-radius: 12px;
        margin-bottom: 12px;
        border: 1px solid #f0f0f0;
        transition: all 0.2s ease;
    }
    .approval-item:hover {
        transform: translateX(5px);
        border-color: var(--primary-light);
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    }
    .approval-item .emp-info {
        display: flex;
        align-items: center;
        gap: 12px;
        flex: 1;
        min-width: 0;
    }
    .approval-item .avatar-circle,
    .approval-group-header .avatar-circle {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        background: rgba(41, 67, 6, 0.08);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        color: var(--primary-blue);
        flex-shrink: 0;
        overflow: hidden;
    }

    .approval-item .avatar-circle img,
    .approval-group-header .avatar-circle img {
        height: 100%;
        object-fit: cover;
        width: 100%;
    }

    .approval-item .avatar-initials,
    .approval-group-header .avatar-initials {
        font-size: 0.85rem;
        font-weight: 800;
        line-height: 1;
    }

    .approval-group-wrap {
        background: #fff;
        border: 1px solid #f0f0f0;
        border-radius: 12px;
        margin-bottom: 12px;
        overflow: hidden;
    }

    .approval-group-header {
        align-items: center;
        cursor: pointer;
        display: flex;
        gap: 16px;
        justify-content: space-between;
        padding: 15px;
        transition: background 0.2s ease;
    }

    .approval-group-header:hover {
        background: #fbfcf8;
    }

    .approval-group-header[aria-expanded="true"] .group-chevron {
        transform: rotate(180deg);
    }

    .group-chevron {
        transition: transform 0.2s ease;
    }

    .approval-group-entries {
        background: #f8faf5;
        border-top: 1px solid #eef2e8;
        padding: 0 15px 12px;
    }

    .approval-group-entry {
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

    .approval-group-entry .entry-template {
        font-size: 0.82rem;
        font-weight: 700;
        min-width: 0;
    }
    .approval-item .details {
        min-width: 0;
    }
    .approval-item .details h6 {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 700;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .approval-item .details span {
        color: var(--text-muted);
        font-size: 0.75rem;
        display: block;
    }
    .approval-item .score-meter {
        width: 140px;
        padding: 0 20px;
        flex-shrink: 0;
    }
    .approval-item .score-val {
        font-weight: 700;
        display: block;
        margin-bottom: 4px;
        font-size: 0.85rem;
    }
    .approval-item .status-meta {
        font-size: 0.75rem;
        color: var(--text-muted);
        text-align: right;
        flex-shrink: 0;
        padding-right: 15px;
    }
    .approval-item .btn-review {
        border-radius: 20px;
        padding: 6px 18px;
        font-size: 0.75rem;
        font-weight: 600;
        flex-shrink: 0;
    }
    .empty-state-card {
        padding: 40px 20px;
        text-align: center;
        color: var(--text-muted);
    }
    .empty-state-card i {
        font-size: 2.5rem;
        opacity: 0.2;
        margin-bottom: 15px;
        display: block;
    }
    .premium-branch-bg {
        /* background-image is always set via inline style by JS */
        background-size: cover !important;
        background-position: center center !important;
        background-repeat: no-repeat !important;
        color: #ffffff !important;
        transition: color 0.3s ease;
    }
    .premium-branch-bg h4,
    .premium-branch-bg p,
    .premium-branch-bg .insight-label,
    .premium-branch-bg #brNameDisplay,
    .premium-branch-bg #brLocationDisplay {
        color: #ffffff !important;
        text-shadow: 0px 2px 5px rgba(0, 0, 0, 0.8) !important;
    }
    .premium-branch-bg .text-muted {
        color: rgba(255, 255, 255, 0.85) !important;
        text-shadow: 0px 1px 3px rgba(0, 0, 0, 0.8) !important;
    }
    .premium-branch-bg .text-primary {
        color: #00d2ff !important;
    }
    .premium-branch-bg .bg-primary {
        background-color: #00d2ff !important;
    }
    .premium-branch-bg .card-bg-icon {
        color: rgba(255, 255, 255, 0.1) !important;
    }
    .premium-branch-bg .btn-explore {
        background-color: #00d2ff !important;
        border-color: #00d2ff !important;
        color: #000 !important;
    }
    .premium-branch-bg .btn-explore:hover {
        background-color: #00c0eb !important;
    }

    /* Responsive Filter Group */
    @media (max-width: 768px) {
        .approval-item,
        .approval-group-header {
            align-items: stretch;
            flex-direction: column;
        }

        .approval-item .status-meta,
        .approval-group-header .status-meta {
            text-align: left;
        }

        .approval-item .btn-review,
        .approval-group-header .btn-review {
            width: 100%;
            text-align: center;
        }

        .approval-group-entry {
            flex-direction: column;
            align-items: stretch;
        }

        .cc-header.d-flex.justify-content-between {
            flex-direction: column;
            align-items: flex-start !important;
            gap: 15px;
        }
    }

    .chart-label-premium {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted);
        margin-bottom: 15px;
        display: block;
    }

    /* HR Manager dashboard: constrain cards and let columns reflow with the
       available content width (the sidebar reduces the usable viewport). */
    .hr-manager-dashboard,
    .hr-manager-dashboard .row > *,
    .hr-manager-dashboard .chart-card,
    .hr-manager-dashboard .card,
    .hr-manager-dashboard .insight-card {
        min-width: 0;
        max-width: 100%;
    }
    .hr-manager-dashboard .row { min-width: 0; }
    .hr-manager-dashboard { width: 100%; }
    @media (min-width: 1600px) {
        /* The global ultra-wide rule caps direct main-content children at
           1650px. Let this dashboard use the full workspace instead. */
        .main-content > .hr-manager-dashboard { max-width: none; margin-right: 0; width: 100%; }
    }
    .hr-manager-dashboard canvas { max-width: 100%; }
    .hr-manager-dashboard .chart-container,
    .hr-manager-dashboard .dashboard-chart-wrap { min-width: 0; position: relative; width: 100%; }
    .hr-manager-dashboard .chart-card .cc-header,
    .hr-manager-dashboard .watchlist-header { flex-wrap: wrap; }
    .hr-manager-dashboard .approval-item .details h6 { white-space: normal; overflow-wrap: anywhere; }
    .hr-manager-dashboard .approval-item .details,
    .hr-manager-dashboard .approval-item .status-meta { min-width: 0; overflow-wrap: anywhere; }
    .hr-manager-dashboard .dashboard-quick-search .form-select { min-width: 0; }
    .hr-manager-dashboard .custom-select-wrapper,
    .hr-manager-dashboard .select-trigger { max-width: 100%; min-width: 0; }
    .hr-manager-dashboard .hub-quick-links {
        display: grid !important;
        gap: 16px;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 250px), 1fr));
        margin: 0 !important;
        padding-top: 8px !important;
    }
    .hr-manager-dashboard .hub-quick-links > [class*="col-"] {
        max-width: none;
        padding: 0;
        width: 100%;
    }

    @media (max-width: 1199.98px) {
        .hr-manager-dashboard .chart-container { height: clamp(220px, 30vw, 280px) !important; }
        .hr-manager-dashboard .dashboard-chart-wrap { height: clamp(220px, 30vw, 280px) !important; }
        .hr-manager-dashboard #topPerformerContainer { height: clamp(240px, 34vw, 320px) !important; }
        .hr-manager-dashboard .watchlist-header { align-items: flex-start; }
        .hr-manager-dashboard .wl-row { flex-wrap: wrap; }
    }
    @media (max-width: 767.98px) {
        .hr-manager-dashboard .page-hero { border-radius: 12px; padding: 20px 16px 16px; }
        .hr-manager-dashboard .page-hero > .d-flex { align-items: flex-start !important; margin-bottom: 14px !important; }
        .hr-manager-dashboard .page-hero .stat-card { height: 100%; min-height: 82px; padding: 12px; }
        .hr-manager-dashboard .page-hero .stat-value { font-size: clamp(1.15rem, 5vw, 1.6rem); }
        .hr-manager-dashboard .page-hero .stat-label { font-size: .66rem; line-height: 1.3; }
        .hr-manager-dashboard .page-hero .stat-icon { font-size: 1.2rem; }
        .hr-manager-dashboard .card-body { padding: 16px !important; }
        .hr-manager-dashboard .approval-queue-heading { padding: 12px 14px !important; }
        .hr-manager-dashboard #approvalQueueSection .approval-list { padding: 10px; }
        .hr-manager-dashboard .approval-item,
        .hr-manager-dashboard .approval-group-header { gap: 10px; padding: 12px; }
        .hr-manager-dashboard .approval-item .emp-info { align-items: flex-start; }
        .hr-manager-dashboard .approval-item .btn-review,
        .hr-manager-dashboard .approval-group-header .btn-review { align-self: flex-start; width: auto; }
        .hr-manager-dashboard .chart-card .cc-header { align-items: flex-start !important; gap: 8px; padding: 14px; }
        .hr-manager-dashboard .chart-card .cc-header h5 { font-size: .95rem; line-height: 1.35; }
        .hr-manager-dashboard #topPerformerBranchFilter { max-width: 100%; width: 100% !important; }
        .hr-manager-dashboard .chart-container,
        .hr-manager-dashboard .dashboard-chart-wrap { height: 230px !important; }
        .hr-manager-dashboard #topPerformerContainer { height: 290px !important; }
        .hr-manager-dashboard .insight-card { min-height: 280px; padding: 20px 16px; }
        .hr-manager-dashboard .watchlist-header { padding: 14px 16px; }
        .hr-manager-dashboard .watchlist-header-left { flex: 1 1 100%; }
        .hr-manager-dashboard .watchlist-view-btn { margin-left: auto; }
        .hr-manager-dashboard #watchlist_pagination { align-items: flex-start !important; flex-direction: column; gap: 10px; padding: 14px 16px !important; }
        .hr-manager-dashboard #watchlist_pagination > .d-flex { width: 100%; }
        .hr-manager-dashboard #watchlist_pagination button { flex: 1; }
        .hr-manager-dashboard .list-group-item { min-width: 0; }
    }
    @media (max-width: 420px) {
        .hr-manager-dashboard .page-hero { padding-left: 12px; padding-right: 12px; }
        .hr-manager-dashboard .page-hero .row { --bs-gutter-x: .6rem; --bs-gutter-y: .6rem; }
        .hr-manager-dashboard .hub-card-body { padding: 14px !important; }
        .hr-manager-dashboard .hub-link-card { min-height: 112px; padding: 10px 7px !important; }
        .hr-manager-dashboard .hub-link-card i { font-size: 1.35rem; }
        .hr-manager-dashboard .hub-link-card .hub-link-title { font-size: .7rem; line-height: 1.25; }
        .hr-manager-dashboard .hub-link-card .hub-link-subtitle { font-size: .61rem !important; line-height: 1.25; }
        .hr-manager-dashboard .approval-item .avatar-circle { height: 36px; width: 36px; }
        .hr-manager-dashboard .watchlist-body { padding: 10px; }
    }
</style>

<div class="hr-manager-dashboard">
<!-- Dashboard Hero -->
<div class="page-hero fadeup">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
        <div>
            <div class="mb-1" style="color:#FFD97D;font-size:.88rem;font-weight:600;letter-spacing:.3px;"><?php echo getGreeting($_SESSION['full_name'] ?? ''); ?></div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">HR Manager · Dashboard</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-tachometer-alt me-2" style="color:#BD9414;"></i>System Overview</h4>
        </div>
        <div style="color:rgba(255,255,255,.6);font-size:.8rem;">
            <i class="fas fa-sync-alt me-1"></i>Data as of <?php echo date('F d, Y'); ?>
        </div>
    </div>
    <div class="row g-3">
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value"><?php echo $total_employees; ?></div>
                    <div class="stat-label">Total Employees</div>
                </div>
                <i class="fas fa-users stat-icon text-white-50"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value"><?php echo $male_count; ?></div>
                    <div class="stat-label">Male Employees</div>
                </div>
                <i class="fas fa-mars stat-icon" style="color:#17a2b8;"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value"><?php echo $female_count; ?></div>
                    <div class="stat-label">Female Employees</div>
                </div>
                <i class="fas fa-venus stat-icon" style="color:#e83e8c;"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <a href="branches.php" class="text-decoration-none">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo $total_branches; ?></div>
                        <div class="stat-label">Total Branches</div>
                    </div>
                    <i class="fas fa-building stat-icon text-white-50"></i>
                </div>
            </div>
        </a>
    </div>

    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value"><?php echo $pending_evals; ?></div>
                    <div class="stat-label">Pending Evals</div>
                </div>
                <i class="fas fa-clock stat-icon" style="color:#ffc107;"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value"><?php echo $new_evals_month; ?></div>
                    <div class="stat-label">Evals This Month</div>
                </div>
                <i class="fas fa-file-alt stat-icon text-white-50"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value"><?php echo number_format($avg_score, 2); ?> / 4</div>
                    <div class="stat-label">Average Score</div>
                </div>
                <i class="fas fa-star stat-icon" style="color:#28a745;"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value"><?php echo $approved_evals; ?></div>
                    <div class="stat-label">Approved Evals</div>
                </div>
                <i class="fas fa-check-circle stat-icon" style="color:#20c997;"></i>
            </div>
        </div>
    </div>
    </div>
</div>

<!-- CENTRALIZED HR SYSTEM COMMAND CENTER HUB -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden" style="background: linear-gradient(135deg, #1b2e04 0%, #294306 100%);">
            <div class="card-body p-4 text-white hub-card-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
                    <div>
                        <div class="badge bg-warning text-dark px-3 py-1 mb-2 fw-bold text-uppercase" style="letter-spacing: 0.08em; font-size: 0.72rem;">Centralized HR Management Hub</div>
                        <h5 class="fw-bold text-white mb-1"><i class="fas fa-sitemap me-2" style="color:#BD9414;"></i>Raquel Pawnshop HR Integrated System</h5>
                        <p class="text-white-50 small mb-0">Access all core employee records, analytics dashboards, succession planning, and digital evaluation forms in one central platform.</p>
                    </div>
                </div>
                <div class="row g-3 pt-2 hub-quick-links">
                    <div class="col-6 col-md-4 col-xl-3 col-xxl-2">
                        <a href="employees.php" class="text-decoration-none">
                            <div class="p-3 rounded-3 text-center bg-white bg-opacity-10 border border-white border-opacity-10 text-white hover-zoom hub-link-card" style="transition: all 0.2s;">
                                <i class="fas fa-users fa-2x mb-2 text-warning"></i>
                                <div class="fw-bold small hub-link-title">Employees</div>
                                <div class="text-white-50 hub-link-subtitle" style="font-size:0.68rem;">Directory &amp; PDS</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-xl-3 col-xxl-2">
                        <a href="succession-planning.php" class="text-decoration-none">
                            <div class="p-3 rounded-3 text-center bg-white bg-opacity-10 border border-white border-opacity-10 text-white hover-zoom hub-link-card" style="transition: all 0.2s;">
                                <i class="fas fa-user-tie fa-2x mb-2" style="color:#9de0ec;"></i>
                                <div class="fw-bold small hub-link-title">Succession</div>
                                <div class="text-white-50 hub-link-subtitle" style="font-size:0.68rem;">Top 10 Candidates</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-xl-3 col-xxl-2">
                        <a href="career-movements.php" class="text-decoration-none">
                            <div class="p-3 rounded-3 text-center bg-white bg-opacity-10 border border-white border-opacity-10 text-white hover-zoom hub-link-card" style="transition: all 0.2s;">
                                <i class="fas fa-route fa-2x mb-2 text-info"></i>
                                <div class="fw-bold small hub-link-title">Movements</div>
                                <div class="text-white-50 hub-link-subtitle" style="font-size:0.68rem;">Promotions &amp; Transfers</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-xl-3 col-xxl-2">
                        <a href="analytics.php" class="text-decoration-none">
                            <div class="p-3 rounded-3 text-center bg-white bg-opacity-10 border border-white border-opacity-10 text-white hover-zoom hub-link-card" style="transition: all 0.2s;">
                                <i class="fas fa-chart-bar fa-2x mb-2 text-success"></i>
                                <div class="fw-bold small hub-link-title">Analytics</div>
                                <div class="text-white-50 hub-link-subtitle" style="font-size:0.68rem;">Performance &amp; Trends</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-xl-3 col-xxl-2">
                        <a href="templates.php" class="text-decoration-none">
                            <div class="p-3 rounded-3 text-center bg-white bg-opacity-10 border border-white border-opacity-10 text-white hover-zoom hub-link-card" style="transition: all 0.2s;">
                                <i class="fas fa-file-alt fa-2x mb-2 text-light"></i>
                                <div class="fw-bold small hub-link-title">Evaluations</div>
                                <div class="text-white-50 hub-link-subtitle" style="font-size:0.68rem;">Templates &amp; History</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-xl-3 col-xxl-2">
                        <a href="<?php echo BASE_URL; ?>/employee/team-evaluation-packages.php" class="text-decoration-none">
                            <div class="p-3 rounded-3 text-center bg-white bg-opacity-10 border border-white border-opacity-10 text-white hover-zoom hub-link-card" style="transition: all 0.2s;">
                                <i class="fas fa-layer-group fa-2x mb-2" style="color:#f5a3ab;"></i>
                                <div class="fw-bold small hub-link-title">Performance Evaluation</div>
                                <div class="text-white-50 hub-link-subtitle" style="font-size:0.68rem;">Consolidation Flow</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="approvalQueueTop"></div>

<div class="row manager-dashboard-queue" id="approvalQueueSection">
    <div class="col-12">
        <div class="chart-card">
            <div class="approval-queue-heading d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0"><i class="fas fa-clock me-2" style="color:#BD9414;"></i>Approval Queue
                    <?php if ($pending_evals + $pending_package_count > 0): ?>
                        <span class="queue-count"><?php echo $pending_evals + $pending_package_count; ?></span>
                    <?php endif; ?>
                </h5>
                <div class="d-flex align-items-center gap-2">
                    <a href="<?php echo BASE_URL; ?>/<?php echo $pending_package_count > 0 ? 'employee/team-evaluation-packages.php' : 'manager/pending-approvals.php'; ?>" class="queue-view-all">
                        View All <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
            <div class="cc-body p-0">
                <div class="tab-content" id="pendingTabsContent">
                    <div class="tab-pane fade show active" id="evals" role="tabpanel">
                        <div class="approval-list">
                            <?php foreach ($pending_packages as $package):
                                $package_waiting = ($package['action_status'] ?? '') === 'Waiting';
                            ?>
                                <div class="approval-item">
                                    <div class="emp-info">
                                        <div class="avatar-circle"><span class="avatar-initials"><i class="fas fa-layer-group"></i></span></div>
                                        <div class="details">
                                            <h6><?php echo e($package['department_name']); ?> &mdash; <?php echo e($package['template_name']); ?></h6>
                                            <span><?php echo e($package['evaluation_type']); ?> &middot; <?php echo formatDate($package['period_start']); ?> to <?php echo formatDate($package['period_end']); ?></span>
                                        </div>
                                    </div>
                                    <div class="status-meta d-none d-sm-block">
                                        <div class="fw-bold text-dark"><?php echo $package_waiting ? 'Waiting for submissions' : 'Pending HR Manager'; ?></div>
                                        <div class="x-small"><?php echo e($package['step_label']); ?> &middot; You are the next reviewer</div>
                                    </div>
                                    <a href="<?php echo BASE_URL; ?>/employee/team-evaluation-packages.php" class="btn btn-primary btn-review"><?php echo $package_waiting ? 'View Progress' : 'Review'; ?></a>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($queue_preview_groups) && empty($pending_packages)): ?>
                                <div class="empty-state-card">
                                    <i class="fas fa-clipboard-check"></i>
                                    <p class="mb-0">All evaluations have been processed.</p>
                                </div>
                            <?php elseif (!empty($queue_preview_groups)): ?>
                                <?php foreach ($queue_preview_groups as $group):
                                    $evaluations = $group['evaluations'];
                                    $evalCount = count($evaluations);
                                    $name_parts = preg_split('/\s+/', trim($group['employee_name'] ?? ''));
                                    $initials = strtoupper(substr($name_parts[0] ?? 'U', 0, 1) . substr($name_parts[1] ?? '', 0, 1));
                                    $avatar_url = getEmployeeAvatar($group['profile_picture'] ?? '');
                                    $has_photo = !empty($group['profile_picture']) && strpos($avatar_url, '/logo/logo.png') === false;
                                    $groupCollapseId = 'mgrDashQueue' . (int) $group['employee_id'];
                                ?>
                                    <?php if ($evalCount === 1):
                                        $row = $evaluations[0];
                                        $score = (float) ($row['total_score'] ?? 0);
                                        $score_width = min(100, max(0, ($score / 4) * 100));
                                    ?>
                                        <div class="approval-item">
                                            <div class="emp-info">
                                                <div class="avatar-circle">
                                                    <?php if ($has_photo): ?>
                                                        <img src="<?php echo e($avatar_url); ?>?v=<?php echo time(); ?>" alt="<?php echo e($group['employee_name']); ?>">
                                                    <?php else: ?>
                                                        <span class="avatar-initials"><?php echo e($initials ?: 'U'); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="details">
                                                    <h6><?php echo e($group['employee_name']); ?></h6>
                                                    <span><?php echo e($row['template_name'] ?? 'Evaluation'); ?> · Endorsed for manager review</span>
                                                </div>
                                            </div>
                                            <div class="score-meter d-none d-md-block">
                                                <span class="score-val"><?php echo e($row['total_score'] ?? '0.00'); ?> / 4</span>
                                                <div class="progress" style="height: 4px;">
                                                    <div class="progress-bar <?php echo ($score >= 3) ? 'bg-success' : (($score >= 2) ? 'bg-primary' : 'bg-warning'); ?>" style="width: <?php echo $score_width; ?>%;"></div>
                                                </div>
                                            </div>
                                            <div class="status-meta d-none d-sm-block">
                                                <div class="fw-bold text-dark"><?php echo formatDate($row['submitted_date']); ?></div>
                                                <div class="x-small">Pending Manager</div>
                                            </div>
                                            <a href="<?php echo BASE_URL; ?>/manager/pending-approvals.php?review=<?php echo (int) $row['evaluation_id']; ?>" class="btn btn-primary btn-review">Review</a>
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
                                                    <div class="avatar-circle">
                                                        <?php if ($has_photo): ?>
                                                            <img src="<?php echo e($avatar_url); ?>?v=<?php echo time(); ?>" alt="<?php echo e($group['employee_name']); ?>">
                                                        <?php else: ?>
                                                            <span class="avatar-initials"><?php echo e($initials ?: 'U'); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="details">
                                                        <h6><?php echo e($group['employee_name']); ?></h6>
                                                        <span>
                                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?php echo $evalCount; ?> evaluations pending</span>
                                                            <?php if (!empty($group['job_title'])): ?>
                                                                <span class="text-muted ms-1"><?php echo e($group['job_title']); ?></span>
                                                            <?php endif; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="status-meta d-none d-sm-block">
                                                    <div class="fw-bold text-dark">Pending Manager</div>
                                                    <div class="x-small">Click to expand</div>
                                                </div>
                                                <button type="button" class="btn btn-outline-primary btn-review" data-bs-toggle="collapse" data-bs-target="#<?php echo e($groupCollapseId); ?>" aria-expanded="false" onclick="event.stopPropagation();">
                                                    <i class="fas fa-chevron-down group-chevron me-1"></i>Show
                                                </button>
                                            </div>
                                            <div class="collapse approval-group-entries" id="<?php echo e($groupCollapseId); ?>">
                                                <?php foreach ($evaluations as $row):
                                                    $score = (float) ($row['total_score'] ?? 0);
                                                    $score_width = min(100, max(0, ($score / 4) * 100));
                                                ?>
                                                    <div class="approval-group-entry">
                                                        <div class="flex-grow-1 min-w-0">
                                                            <div class="entry-template"><?php echo e($row['template_name'] ?? 'Evaluation'); ?></div>
                                                            <div class="small text-muted">
                                                                <?php echo e($row['evaluation_type'] ?? 'Annual'); ?> ·
                                                                Submitted <?php echo formatDate($row['submitted_date']); ?>
                                                            </div>
                                                        </div>
                                                        <div class="score-meter">
                                                            <span class="score-val"><?php echo e($row['total_score'] ?? '0.00'); ?> / 4</span>
                                                            <div class="progress" style="height: 4px;">
                                                                <div class="progress-bar <?php echo ($score >= 3) ? 'bg-success' : (($score >= 2) ? 'bg-primary' : 'bg-warning'); ?>" style="width: <?php echo $score_width; ?>%;"></div>
                                                            </div>
                                                        </div>
                                                        <a href="<?php echo BASE_URL; ?>/manager/pending-approvals.php?review=<?php echo (int) $row['evaluation_id']; ?>" class="btn btn-primary btn-sm btn-review">Review</a>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <div class="text-center pb-3">
                                    <a href="<?php echo BASE_URL; ?>/manager/pending-approvals.php" class="text-decoration-none small text-muted hover-primary">
                                        View all pending evaluations <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Branch Distribution Insights (Professional Section) -->
<div class="row g-4 mb-4 branch-insights-section">
    <div class="col-12 col-xl-4">
        <div class="chart-card allow-overflow h-100">
            <div class="cc-header">
                <h5><i class="fas fa-search me-2"></i>Quick Search</h5>
            </div>
            <div class="cc-body">
                <form action="employees.php" method="get" class="dashboard-quick-search mb-4">
                    <label class="small text-muted mb-2" for="dashboardEmployeeSearch">Find an employee</label>
                    <div class="input-group mb-2">
                        <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                        <input type="search" class="form-control" name="search" id="dashboardEmployeeSearch" placeholder="Name, position, or department">
                    </div>
                    <div class="d-flex gap-2">
                        <select class="form-select" name="department" aria-label="Filter by department">
                            <option value="">All Departments</option>
                            <?php foreach ($departments_dashboard as $department): ?>
                                <option value="<?php echo e($department['department_name']); ?>"><?php echo e($department['department_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-outline-primary" type="submit" aria-label="Search employees"><i class="fas fa-arrow-right"></i></button>
                    </div>
                </form>
                <div class="border-top pt-3">
                    <h6 class="small fw-bold mb-2"><i class="fas fa-map-marker-alt me-2"></i>Branch Workforce</h6>
                    <p class="text-muted small mb-3">Choose a location to view detailed workforce distribution.</p>

                    <div id="branchSelector">
                        <div class="custom-select-wrapper">
                            <div class="select-trigger" id="customSelectTrigger">
                                <span class="selected-text text-muted">Pick a branch...</span>
                                <i class="fas fa-chevron-down ms-2"></i>
                            </div>
                            <div class="select-dropdown" id="customSelectDropdown">
                                <div class="search-container">
                                    <input type="text" placeholder="Search branches..." id="branchSearchInput">
                                </div>
                                <div class="results-container" id="branchResultsList">
                                <?php foreach ($branches_insights as $br): 
                                    // Resolve background image server-side — no JS probing needed
                                    $bg_dir   = __DIR__ . '/../assets/img/logo/branch_background_images/';
                                    $bg_url   = '';
                                    $bid      = $br['branch_id'];
                                    foreach (['jpg','jpeg','png','webp'] as $ext) {
                                        if (file_exists($bg_dir . 'branch_' . $bid . '.' . $ext)) {
                                            $bg_url = BASE_URL . '/assets/img/logo/branch_background_images/branch_' . $bid . '.' . $ext;
                                            break;
                                        }
                                    }
                                    // Main Office always uses its dedicated image
                                    if ($br['branch_name'] === 'Raquel Pawnshop Main Office') {
                                        $bg_url = BASE_URL . '/assets/img/logo/branch_background_images/main_branch.jpg';
                                    }
                                ?>
                                    <div class="select-option" data-id="<?php echo $br['branch_id']; ?>"
                                        data-name="<?php echo e($br['branch_name']); ?>"
                                        data-location="<?php echo e($br['location']); ?>"
                                        data-count="<?php echo $br['emp_count']; ?>"
                                        data-percent="<?php echo round(($br['emp_count'] / $total_emp_calc) * 100, 1); ?>"
                                        data-bg="<?php echo e($bg_url); ?>">
                                        <div class="branch-name"><?php echo e($br['branch_name']); ?></div>
                                        <div class="emp-badge"><?php echo $br['emp_count']; ?> Staff</div>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-8">
        <div class="insight-card" id="branchInsightCard">
            <i class="fas fa-building card-bg-icon"></i>
            <span class="insight-label">Workforce Insight</span>

            <div id="insightPlaceholder" class="text-center py-5">
                <i class="fas fa-mouse-pointer fa-3x mb-3 text-muted" style="opacity: 0.3;"></i>
                <p class="text-muted">Select a branch to see detailed statistics.</p>
            </div>

            <div id="insightContent" style="display: none;">
                <div class="branch-identity">
                    <h4 id="brNameDisplay">Branch Name</h4>
                    <p id="brLocationDisplay"><i class="fas fa-map-marker-alt me-1"></i> Location Address</p>
                </div>

                <div class="stats-grid">
                    <div class="stat-box">
                        <small>ASSIGNED EMPLOYEES</small>
                        <div class="stat-val" id="brCountDisplay">0</div>
                    </div>
                    <div class="stat-box">
                        <small>WORKFORCE PERCENTAGE</small>
                        <div class="stat-val"><span id="brPercentDisplay">0</span>%</div>
                    </div>
                </div>

                <div class="distribution-bar">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small fw-600 text-muted">Density Overview</span>
                        <span class="small fw-600 text-primary"><span id="brDensityText">0</span>% vs Total</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-primary" id="brProgressBar" role="progressbar" style="width: 0%">
                        </div>
                    </div>
                </div>

                <a href="employees.php" class="btn btn-primary btn-explore" id="viewBranchEmployeesLink">
                    <i class="fas fa-users me-2"></i>View Branch Staff
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Performance Distribution -->
    <div class="col-12 col-xl-6 col-xxl-4">
        <div class="chart-card h-100">
            <div class="cc-header">
                <h5><i class="fas fa-chart-pie me-2"></i>Performance Distribution</h5>
            </div>
            <div class="cc-body">
                <div class="chart-container" style="height:300px;">
                    <canvas id="perfPieChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Performers -->
    <div class="col-12 col-xl-6 col-xxl-4">
        <div class="chart-card h-100">
            <div class="cc-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-trophy text-warning me-2"></i>Top Performers</h5>
                <select id="topPerformerBranchFilter" class="form-select form-select-sm" style="width: 150px; font-size: 0.75rem; border-radius: 20px; border-color: #eee;">
                    <option value="">All Branches</option>
                    <?php foreach ($branches_insights as $br): ?>
                        <option value="<?php echo $br['branch_id']; ?>"><?php echo e($br['branch_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="cc-body p-0" style="height: 330px; overflow-y: auto;" id="topPerformerContainer">
                <?php if ($top_performers->num_rows === 0): ?>
                    <div class="empty-state-card py-5">
                        <i class="fas fa-medal text-muted" style="opacity: 0.1; font-size: 3rem;"></i>
                        <p class="mb-0 mt-3 small">No approved evaluations yet.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush pt-2">
                        <?php 
                        $rank = 1;
                        while ($tp = $top_performers->fetch_assoc()): 
                            $medal_color = ($rank == 1) ? '#ffd700' : (($rank == 2) ? '#c0c0c0' : (($rank == 3) ? '#cd7f32' : '#adb5bd'));
                            // Fallback rendering for avatar initials safely
                            $names = explode(' ', $tp['employee_name']);
                            $fn = isset($names[0]) ? substr($names[0], 0, 1) : '';
                            $ln = isset($names[1]) ? substr($names[1], 0, 1) : substr($names[0], 1, 1);
                            $initials = strtoupper($fn . $ln);
                        ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 border-0 border-bottom border-light" style="background: transparent;">
                                <div class="d-flex align-items-center gap-3 w-100">
                                    <div class="fw-bold" style="color: <?php echo $medal_color; ?>; width: 22px; text-align: center;">#<?php echo $rank; ?></div>
                                    <div style="width: 38px; height: 38px; border-radius: 50%; font-size: 0.85rem; background: rgba(13, 110, 253, 0.08); display:flex; align-items:center; justify-content:center; color: var(--primary-blue); font-weight: 800; flex-shrink: 0;">
                                        <?php echo $initials; ?>
                                    </div>
                                    <div style="min-width: 0; flex: 1;">
                                        <h6 class="mb-0 fw-bold text-truncate" style="font-size: 0.9rem;">
                                            <a href="view-employee.php?id=<?php echo $tp['employee_id']; ?>" class="text-decoration-none text-dark hover-primary-text">
                                                <?php echo e($tp['employee_name']); ?>
                                            </a>
                                        </h6>
                                        <small class="text-muted d-block text-truncate" style="font-size: 0.75rem;"><?php echo e($tp['job_title']); ?> &bull; <?php echo e($tp['department_name'] ?? 'N/A'); ?></small>
                                    </div>
                                    <div class="text-end ps-2">
                                        <div class="badge bg-success rounded-pill px-2 py-1" style="font-size: 0.8rem; box-shadow: 0 2px 4px rgba(25, 135, 84, 0.2);"><?php echo $tp['total_score']; ?>%</div>
                                    </div>
                                </div>
                            </div>
                        <?php $rank++; endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Evaluation Status -->
    <div class="col-12 col-xl-6 col-xxl-4">
        <div class="chart-card h-100">
            <div class="cc-header">
                <h5><i class="fas fa-tasks me-2"></i>Status Overview</h5>
            </div>
            <div class="cc-body">
                <div class="chart-container" style="height:300px;">
                    <canvas id="statusDonutChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- COMPREHENSIVE GRAPHS ROW -->
<div class="row g-4 mb-4">
    <div class="col-12 col-xxl-7">
        <div class="chart-card h-100">
            <div class="cc-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-chart-line me-2 text-primary"></i>Performance Trends</h5>
                <span class="badge bg-light text-dark fw-normal" style="font-size: 0.7rem;">Average Score (Last 6 Months)</span>
            </div>
            <div class="cc-body">
                <div class="dashboard-chart-wrap" style="height: 300px;">
                    <canvas id="trendLineChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xxl-5">
        <div class="chart-card h-100">
            <div class="cc-header">
                <h5><i class="fas fa-chart-bar me-2 text-info"></i>Branch Comparison</h5>
            </div>
            <div class="cc-body">
                <span class="chart-label-premium">Top 10 Performing Branches (Avg Score)</span>
                <div class="dashboard-chart-wrap" style="height: 270px;">
                    <canvas id="branchComparisonChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- AGE DISTRIBUTION ROW -->
<div class="row g-4 mb-4">
    <div class="col-12 col-xxl-6">
        <div class="chart-card">
            <div class="cc-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-chart-pie me-2 text-danger"></i>Employee Age Distribution</h5>
                <span class="badge bg-light text-dark fw-normal" style="font-size: 0.7rem;">Workforce Demographics</span>
            </div>
            <div class="cc-body">
                <div class="row align-items-center g-4">
                    <div class="col-12 col-xxl-7">
                        <div class="dashboard-chart-wrap" style="height: 320px; position: relative;">
                            <canvas id="ageDistChart"></canvas>
                        </div>
                    </div>
                    <div class="col-12 col-xxl-5">
                        <div class="p-3 bg-light rounded-3 border">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.5px;">Active Headcount</span>
                                <span class="badge bg-danger rounded-pill px-3 py-1 fw-bold fs-6"><?php echo $total_age_tracked; ?></span>
                            </div>
                            <div class="d-flex flex-column gap-2">
                                <?php 
                                $age_palette = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899'];
                                foreach ($age_labels as $idx => $label): 
                                    $c = $age_counts[$idx];
                                    $pct = $total_age_tracked > 0 ? round(($c / $total_age_tracked) * 100, 1) : 0;
                                    $dot_color = $age_palette[$idx];
                                ?>
                                <div class="d-flex align-items-center justify-content-between py-1 border-bottom border-light">
                                    <div class="d-flex align-items-center">
                                        <span class="d-inline-block rounded-circle me-2" style="width: 10px; height: 10px; background-color: <?php echo $dot_color; ?>;"></span>
                                        <span class="small fw-semibold text-secondary"><?php echo $label; ?> yrs</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="small fw-bold text-dark"><?php echo $c; ?></span>
                                        <span class="badge bg-white text-muted border px-2 py-1" style="font-size: 0.72rem; min-width: 48px; text-align: right;"><?php echo $pct; ?>%</span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xxl-6">
        <div class="chart-card h-100">
            <div class="cc-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>Recent Evaluations</h5>
                <a href="evaluation-history.php" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="cc-body p-0">
                <?php if (empty($recent_evaluations)): ?>
                    <div class="empty-state-card py-5"><i class="fas fa-file-alt"></i><p class="mb-0 small">No evaluations have been submitted yet.</p></div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_evaluations as $recent): ?>
                            <div class="list-group-item d-flex align-items-center justify-content-between gap-3 py-3">
                                <div class="min-w-0">
                                    <div class="fw-bold text-truncate"><?php echo e($recent['employee_name']); ?></div>
                                    <small class="text-muted text-truncate d-block"><?php echo e($recent['department_name'] ?? ''); ?> &middot; <?php echo e($recent['evaluation_type'] ?? 'Evaluation'); ?></small>
                                </div>
                                <div class="text-end flex-shrink-0">
                                    <span class="badge bg-light text-dark border"><?php echo e($recent['status']); ?></span>
                                    <small class="text-muted d-block mt-1"><?php echo formatDate($recent['submitted_date']); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div id="dashboardDeferredSections"></div>

<script>
    // User's assigned branch (from session), injected server-side
    const USER_ASSIGNED_BRANCH = <?php echo json_encode($user_branch_name); ?>;
    const HAS_DASHBOARD_QUEUE_ITEMS = <?php echo json_encode($dashboard_queue_count > 0); ?>;
    document.addEventListener('DOMContentLoaded', function () {
        const deferredSectionsHost = document.getElementById('dashboardDeferredSections');
        const approvalQueueTop = document.getElementById('approvalQueueTop');
        const approvalQueueSection = document.getElementById('approvalQueueSection');

        if (approvalQueueSection) {
            if (HAS_DASHBOARD_QUEUE_ITEMS && approvalQueueTop) {
                // Active approvals should be visible immediately below the hub.
                approvalQueueTop.appendChild(approvalQueueSection);
            } else if (deferredSectionsHost) {
                // Keep the empty state below analytics when there is nothing to action.
                deferredSectionsHost.appendChild(approvalQueueSection);
            }
        }

        // --- Professional Branch Selector Logic ---
        const trigger = document.getElementById('customSelectTrigger');
        const dropdown = document.getElementById('customSelectDropdown');
        const searchInput = document.getElementById('branchSearchInput');
        const options = document.querySelectorAll('.select-option');
        const selectedText = trigger.querySelector('.selected-text');

        const insightCard = document.getElementById('branchInsightCard');
        const insightPlaceholder = document.getElementById('insightPlaceholder');
        const insightContent = document.getElementById('insightContent');

        // Toggle Dropdown
        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.classList.toggle('show');
            trigger.classList.toggle('active');
            if (dropdown.classList.contains('show')) searchInput.focus();
        });

        // Search Filter
        searchInput.addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase();
            options.forEach(opt => {
                const name = opt.dataset.name.toLowerCase();
                opt.style.display = name.includes(term) ? 'flex' : 'none';
            });
        });

        // Selection Handler
        options.forEach(opt => {
            opt.addEventListener('click', function () {
                // Update UI
                options.forEach(o => o.classList.remove('selected'));
                this.classList.add('selected');
                selectedText.innerText = this.dataset.name;
                selectedText.classList.remove('text-muted');
                dropdown.classList.remove('show');
                trigger.classList.remove('active');

                // Animate Insight Card
                updateInsightCard(this.dataset);
            });
        });

        // Base URL for branch background images
        const BRANCH_BG_BASE = window.APP_BASE_URL + '/assets/img/logo/branch_background_images/';

        /**
         * Applies branch background using the server-resolved data-bg URL.
         * No probing — PHP already confirmed the file exists.
         */
        function applyBranchBackground(bgUrl) {
            insightCard.classList.remove('premium-branch-bg');
            insightCard.style.backgroundImage    = '';
            insightCard.style.backgroundSize     = '';
            insightCard.style.backgroundPosition = '';
            insightCard.style.backgroundRepeat   = '';

            if (!bgUrl) return; // no image — leave as default card style

            insightCard.style.backgroundImage    = `linear-gradient(135deg, rgba(9,32,63,0.85), rgba(83,120,149,0.85)), url('${bgUrl}')`;
            insightCard.style.backgroundSize     = 'cover';
            insightCard.style.backgroundPosition = 'center center';
            insightCard.style.backgroundRepeat   = 'no-repeat';
            insightCard.classList.add('premium-branch-bg');
        }

        function updateInsightCard(data) {
            insightPlaceholder.style.display = 'none';
            insightContent.style.display = 'block';
            insightCard.classList.add('updated-pulse');
            setTimeout(() => insightCard.classList.remove('updated-pulse'), 500);

            // Apply branch background (image if available, gradient fallback otherwise)
            applyBranchBackground(data.bg);

            document.getElementById('brNameDisplay').innerText = data.name;
            document.getElementById('brLocationDisplay').innerHTML = `<i class="fas fa-map-marker-alt me-1"></i> ${data.location}`;
            document.getElementById('brCountDisplay').innerText = data.count;
            document.getElementById('brPercentDisplay').innerText = data.percent;
            document.getElementById('brDensityText').innerText = data.percent;
            document.getElementById('brProgressBar').style.width = data.percent + '%';
            document.getElementById('viewBranchEmployeesLink').href = `employees.php?branch=${encodeURIComponent(data.name)}`;
        }

        // --- Top Performer Branch Filter ---
        const topPerformerFilter = document.getElementById('topPerformerBranchFilter');
        const topPerformerContainer = document.getElementById('topPerformerContainer');

        if (topPerformerFilter) {
            topPerformerFilter.addEventListener('change', function() {
                const branchId = this.value;
                
                // Visual feedback
                topPerformerContainer.style.transition = 'opacity 0.2s ease';
                topPerformerContainer.style.opacity = '0.4';
                
                fetch(`ajax/get-top-performers.php?branch_id=${branchId}`)
                    .then(response => response.text())
                    .then(html => {
                        topPerformerContainer.innerHTML = html;
                        topPerformerContainer.style.opacity = '1';
                    })
                    .catch(err => {
                        console.error('Error fetching top performers:', err);
                        topPerformerContainer.style.opacity = '1';
                    });
            });
        }

        // Close dropdown on click outside
        document.addEventListener('click', () => {
            dropdown.classList.remove('show');
            trigger.classList.remove('active');
        });

        // --- Auto-select the user's assigned branch on page load ---
        if (USER_ASSIGNED_BRANCH) {
            const defaultOption = Array.from(options).find(
                opt => opt.dataset.name === USER_ASSIGNED_BRANCH
            );
            if (defaultOption) {
                // Mark as selected
                options.forEach(o => o.classList.remove('selected'));
                defaultOption.classList.add('selected');
                // Update the trigger text
                selectedText.innerText = defaultOption.dataset.name;
                selectedText.classList.remove('text-muted');
                // Populate the Workforce Insight card
                updateInsightCard(defaultOption.dataset);
            }
        }

        const commonOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, padding: 15 } }
            }
        };

        // 1. Performance Distribution (Pie)
        new Chart(document.getElementById('perfPieChart'), {
            type: 'pie',
            data: {
                labels: ['Outstanding', 'Exceeds Expectations', 'Meets Expectations', 'Needs Improvement'],
                datasets: [{
                    data: [<?php echo $perf_data['Outstanding']; ?>, <?php echo $perf_data['Exceeds Expectations']; ?>, <?php echo $perf_data['Meets Expectations']; ?>, <?php echo $perf_data['Needs Improvement']; ?>],
                    backgroundColor: ['#28a745', '#17a2b8', '#ffc107', '#dc3545'],
                    borderWidth: 2,
                    borderColor: '#fff',
                    hoverOffset: 8
                }]
            },
            options: {
                ...commonOptions,
                animation: {
                    animateRotate: true,
                    animateScale: true,
                    duration: 1500,
                    easing: 'easeInOutQuart'
                }
            }
        });

        // 2. Evaluation Status (Doughnut)
        new Chart(document.getElementById('statusDonutChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($status_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($status_counts); ?>,
                    backgroundColor: ['#6c757d', '#ffc107', '#17a2b8', '#28a745', '#dc3545', '#007bff'],
                    hoverOffset: 8,
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                ...commonOptions,
                cutout: '65%',
                animation: {
                    animateRotate: true,
                    animateScale: true,
                    duration: 1500,
                    easing: 'easeInOutQuart'
                }
            }
        });

        // 3. Performance Trends (Line Chart)
        new Chart(document.getElementById('trendLineChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($monthly_data, 'label')); ?>,
                datasets: [{
                    label: 'Average Performance Score',
                    data: <?php echo json_encode(array_column($monthly_data, 'value')); ?>,
                    borderColor: '#294306',
                    backgroundColor: 'rgba(41, 67, 6, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#294306',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                ...commonOptions,
                plugins: {
                    ...commonOptions.plugins,
                    legend: { display: false }
                },
                scales: {
                    y: { 
                        beginAtZero: false, 
                        min: 1.0, max: 4.0, 
                        grid: { display: true, drawBorder: false, color: '#f0f0f0' },
                        ticks: { 
                            stepSize: 1.0,
                            callback: value => value.toFixed(1)
                        }
                    },
                    x: { grid: { display: false } }
                },
                animation: {
                    duration: 2000,
                    easing: 'easeInOutQuart',
                    onComplete: function() {
                        // Optional callback when animation completes
                    }
                }
            }
        });

        // 4. Branch Comparison (Horizontal Bar Chart)
        new Chart(document.getElementById('branchComparisonChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($branch_comp_data, 'label')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($branch_comp_data, 'value')); ?>,
                    backgroundColor: 'rgba(23, 162, 184, 0.7)',
                    borderColor: '#17a2b8',
                    borderWidth: 1,
                    borderRadius: 5,
                    barThickness: 12
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { max: 4.0, grid: { display: true, color: '#f8f9fa' } },
                    y: { grid: { display: false } }
                },
                animation: {
                    duration: 1800,
                    easing: 'easeInOutQuart',
                    delay: (context) => {
                        // Stagger animation for each bar
                        return context.dataIndex * 100;
                    }
                }
            }
        });

        // 5. Age Distribution (Pie Chart)
        new Chart(document.getElementById('ageDistChart'), {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($age_labels); ?>,
                datasets: [{
                    label: 'Employees',
                    data: <?php echo json_encode($age_counts); ?>,
                    backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899'],
                    borderColor: '#ffffff',
                    borderWidth: 2,
                    hoverOffset: 10
                }]
            },
            options: {
                ...commonOptions,
                plugins: {
                    ...commonOptions.plugins,
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 16,
                            font: { size: 12 }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const dataset = context.dataset;
                                const total = dataset.data.reduce((a, b) => a + b, 0);
                                const value = context.raw || 0;
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return ` ${context.label} yrs: ${value} Employees (${percentage}%)`;
                            }
                        }
                    }
                },
                animation: {
                    animateRotate: true,
                    animateScale: true,
                    duration: 1500,
                    easing: 'easeInOutQuart'
                }
            }
        });

    });
</script>

<?php /* ── Non-Regular Personnel Watchlist ── */ ?>
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
        background: #1b2e04;
        color: #fff;
        padding: 18px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        border-radius: 16px 16px 0 0;
    }
    .watchlist-body {
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
    .watchlist-body { background: #fff; }
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
        /* Header stacks: title+badges → button full-width */
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

        /* Body becomes a card-list gutter */
        .watchlist-body { background: #f5f7f5; padding: 12px; overflow: visible; }

        /* Each row becomes a standalone card */
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
            border-radius: 14px 14px 0 0;
            overflow: hidden;
        }
        .wl-row-footer {
            border-radius: 0 0 14px 14px;
            overflow: visible;
            position: relative;
        }
        .wl-row-footer .dropdown {
            position: relative;
        }
        .wl-row-footer .dropdown-menu {
            z-index: 1060 !important;
            margin-top: 4px;
        }

        /* Hide the desktop inline children */
        .wl-row > .wl-avatar,
        .wl-row > .wl-info,
        .wl-row > .wl-countdown,
        .wl-row > .wl-actions { display: none !important; }

        /* Card top: avatar + name/meta */
        .wl-row-top {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 13px 14px 10px;
        }
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

        /* Card footer: urgency badge left, action btn right */
        .wl-row-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 14px 12px;
            gap: 8px;
            border-top: 1px solid #f0f3f0;
            background: #fafcfa;
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
        <a href="<?php echo BASE_URL; ?>/manager/employees.php" class="watchlist-view-btn">
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
                    $urgency = $ws['urgency'];
                    if ($d < 0)       { $dayLabel = 'Overdue by ' . abs($d) . 'd';  $dayClass = 'wl-overdue'; $icon = 'fa-times-circle'; }
                    elseif ($d === 0) { $dayLabel = 'Ends Today!';                  $dayClass = 'wl-overdue'; $icon = 'fa-exclamation-circle'; }
                    elseif ($d <= 14) { $dayLabel = 'Ends in ' . $d . 'd';          $dayClass = 'wl-critical'; $icon = 'fa-fire'; }
                    elseif ($d <= 30) { $dayLabel = 'Ends in ' . $d . 'd';          $dayClass = 'wl-warning'; $icon = 'fa-exclamation-triangle'; }
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
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/manager/view-employee.php?id=<?php echo $ws['employee_id']; ?>"><i class="fas fa-eye me-2 text-info"></i>View Profile</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/manager/edit-employee.php?id=<?php echo $ws['employee_id']; ?>"><i class="fas fa-edit me-2 text-primary"></i>Edit Record</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/manager/career-movements.php?employee_id=<?php echo $ws['employee_id']; ?>"><i class="fas fa-exchange-alt me-2 text-success"></i>Log Career Movement</a></li>
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
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/manager/view-employee.php?id=<?php echo $ws['employee_id']; ?>"><i class="fas fa-eye me-2 text-info"></i>View Profile</a></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/manager/edit-employee.php?id=<?php echo $ws['employee_id']; ?>"><i class="fas fa-edit me-2 text-primary"></i>Edit Record</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/manager/career-movements.php?employee_id=<?php echo $ws['employee_id']; ?>"><i class="fas fa-exchange-alt me-2 text-success"></i>Log Career Movement</a></li>
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

</div><!-- /.hr-manager-dashboard -->

<?php require_once '../includes/footer.php'; ?>
