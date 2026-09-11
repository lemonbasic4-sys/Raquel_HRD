<?php
$page_title = 'Pending Endorsements';
require_once '../includes/session-check.php';
checkRole(['HR Supervisor']);
require_once '../includes/functions.php';

ensureEvaluationWorkflowSchema($conn);

$supervisor_id = (int) ($_SESSION['user_id'] ?? 0);
$branch_id = (int) ($_SESSION['branch_id'] ?? 0);

// Validate supervisor_id exists in users (FK endorsed_by → users.user_id is ON DELETE SET NULL)
if ($supervisor_id > 0) {
    $uid_check = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? LIMIT 1");
    $uid_check->bind_param("i", $supervisor_id);
    $uid_check->execute();
    $uid_exists = (bool) $uid_check->get_result()->fetch_assoc();
    $uid_check->close();
    if (!$uid_exists) {
        $supervisor_id = 0; // will be treated as NULL below
    }
}
$supervisor_id_nullable = $supervisor_id > 0 ? $supervisor_id : null;

function supervisorPendingDate(string $value): string
{
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
}

function supervisorPendingScore($value): ?float
{
    if ($value === '' || $value === null || !is_numeric($value)) {
        return null;
    }

    return max(0, min(4, (float) $value));
}

function supervisorPendingRows(mysqli $conn, string $sql, string $types = '', array $params = []): array
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

function supervisorPendingScalar(mysqli $conn, string $sql, string $types = '', array $params = [])
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

function supervisorPendingQueryString(array $params): string
{
    $clean = [];
    foreach ($params as $key => $value) {
        if ($value !== '' && $value !== null) {
            $clean[$key] = $value;
        }
    }
    return http_build_query($clean);
}

function supervisorPendingHasScore(array $row): bool
{
    return isset($row['total_score']) && $row['total_score'] !== '' && $row['total_score'] !== null;
}

function supervisorPendingAttentionFlags(array $row): array
{
    $flags = [];
    $has_score = supervisorPendingHasScore($row);
    $score = $has_score ? (float) $row['total_score'] : null;
    $performance_level = $row['performance_level'] ?? '';
    if ($has_score && (empty($performance_level) || $performance_level === '0')) {
        $performance_level = getPerformanceLevel($score);
    }
    $days_pending = (int) ($row['days_pending'] ?? 0);

    if (!$has_score) {
        $flags[] = 'No score';
    }
    if (($has_score && $score < 2) || $performance_level === 'Needs Improvement') {
        $flags[] = 'Low score';
    }
    if ($days_pending >= 7) {
        $flags[] = 'Overdue';
    }

    return $flags;
}

function supervisorPendingGroupByEmployee(array $rows): array
{
    $groups = [];

    foreach ($rows as $row) {
        $employeeId = (int) ($row['employee_id'] ?? 0);
        if ($employeeId <= 0) {
            continue;
        }

        if (!isset($groups[$employeeId])) {
            $groups[$employeeId] = [
                'employee_id' => $employeeId,
                'employee_name' => $row['employee_name'] ?? '',
                'employee_code' => $row['employee_code'] ?? '',
                'job_title' => $row['job_title'] ?? '',
                'department_name' => $row['department_name'] ?? '',
                'profile_picture' => $row['profile_picture'] ?? '',
                'evaluations' => [],
                'max_days_pending' => 0,
                'attention_flags' => [],
            ];
        }

        $groups[$employeeId]['evaluations'][] = $row;
        $daysPending = (int) ($row['days_pending'] ?? 0);
        if ($daysPending > $groups[$employeeId]['max_days_pending']) {
            $groups[$employeeId]['max_days_pending'] = $daysPending;
        }

        foreach (supervisorPendingAttentionFlags($row) as $flag) {
            if (!in_array($flag, $groups[$employeeId]['attention_flags'], true)) {
                $groups[$employeeId]['attention_flags'][] = $flag;
            }
        }
    }

    $grouped = array_values($groups);
    usort($grouped, static function (array $a, array $b): int {
        if ($a['max_days_pending'] !== $b['max_days_pending']) {
            return $b['max_days_pending'] <=> $a['max_days_pending'];
        }

        return strcasecmp($a['employee_name'], $b['employee_name']);
    });

    return $grouped;
}

function supervisorPendingRenderEvaluationRow(array $row, string $rowLayout = 'full'): void
{
    $rowLayout = $rowLayout === 'nested' ? 'nested' : 'full';
    include __DIR__ . '/partials/pending-evaluation-row.php';
}

$allowed_eval_types = ['Initial', 'Final', 'Quarterly', 'Annual'];
$attention_filters = [
    'low_score' => 'Low Score',
    'overdue' => 'Overdue 7+ Days',
    'missing_score' => 'No Score',
];

$filter_search = trim($_GET['q'] ?? '');
if (strlen($filter_search) > 80) {
    $filter_search = substr($filter_search, 0, 80);
}
$filter_department = isset($_GET['department']) && $_GET['department'] !== '' ? max(0, (int) $_GET['department']) : 0;
$filter_branch = isset($_GET['branch']) && $_GET['branch'] !== '' ? max(0, (int) $_GET['branch']) : 0;
$filter_staff = isset($_GET['submitted_by']) && $_GET['submitted_by'] !== '' ? max(0, (int) $_GET['submitted_by']) : 0;
$filter_template = isset($_GET['template']) && $_GET['template'] !== '' ? max(0, (int) $_GET['template']) : 0;
$filter_type = in_array($_GET['evaluation_type'] ?? '', $allowed_eval_types, true) ? $_GET['evaluation_type'] : '';
$filter_attention = array_key_exists($_GET['attention'] ?? '', $attention_filters) ? $_GET['attention'] : '';
$date_from = supervisorPendingDate(trim($_GET['date_from'] ?? ''));
$date_to = supervisorPendingDate(trim($_GET['date_to'] ?? ''));
if ($date_from !== '' && $date_to !== '' && $date_from > $date_to) {
    [$date_from, $date_to] = [$date_to, $date_from];
}
$score_min = supervisorPendingScore($_GET['score_min'] ?? null);
$score_max = supervisorPendingScore($_GET['score_max'] ?? null);
if ($score_min !== null && $score_max !== null && $score_min > $score_max) {
    [$score_min, $score_max] = [$score_max, $score_min];
}

$filter_params = [
    'q' => $filter_search,
    'branch' => $filter_branch ?: '',
    'department' => $filter_department ?: '',
    'submitted_by' => $filter_staff ?: '',
    'template' => $filter_template ?: '',
    'evaluation_type' => $filter_type,
    'attention' => $filter_attention,
    'date_from' => $date_from,
    'date_to' => $date_to,
    'score_min' => $score_min,
    'score_max' => $score_max,
];
$filter_query = supervisorPendingQueryString($filter_params);
$redirect_url = BASE_URL . '/supervisor/pending-endorsements.php' . ($filter_query ? '?' . $filter_query : '');
$form_action = 'pending-endorsements.php' . ($filter_query ? '?' . $filter_query : '');
$export_url = 'pending-endorsements.php?' . supervisorPendingQueryString(array_merge($filter_params, ['export' => 'csv']));

// Handle endorsement/return (MUST be before header.php to allow redirect)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    $eval_id = (int)$_POST['evaluation_id'];
    $action = $_POST['action'];
    $comments = trim($_POST['supervisor_comments'] ?? '');

    $eval_stmt = $conn->prepare("
        SELECT ev.evaluation_id, ev.employee_id, ev.submitted_by, CONCAT(e.first_name, ' ', e.last_name) AS emp_name, u.role AS submitter_role, et.template_name, u_emp.user_id AS emp_user_id
        FROM evaluations ev
        INNER JOIN employees e ON ev.employee_id = e.employee_id
        LEFT JOIN users u ON ev.submitted_by = u.user_id
        LEFT JOIN evaluation_templates et ON ev.template_id = et.template_id
        LEFT JOIN users u_emp ON ev.employee_id = u_emp.employee_id AND u_emp.role = 'Employee'
        WHERE ev.evaluation_id = ?
          AND ev.status IN ('Pending Supervisor', 'Pending HR Consolidation')
          AND e.is_active = 1
          AND e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)
        LIMIT 1
    ");
    $eval_stmt->bind_param("i", $eval_id);
    $eval_stmt->execute();
    $eval_info = $eval_stmt->get_result()->fetch_assoc();
    $eval_stmt->close();

    if (!$eval_info) {
        redirectWith($redirect_url, 'danger', 'Pending evaluation could not be found.');
    }

    $emp_hr_role = getEmployeeHRRole($conn, $eval_info['employee_id']);

    if ($action === 'endorse') {
        if ($emp_hr_role === 'HR Manager') {
            $stmt = $conn->prepare("
                UPDATE evaluations ev
                INNER JOIN employees e ON ev.employee_id = e.employee_id
                SET ev.status = 'Approved',
                    ev.endorsed_by = ?,
                    ev.endorsed_date = NOW(),
                    ev.approved_by = ?,
                    ev.approved_date = NOW(),
                    ev.evaluator_comments = ?
                WHERE ev.evaluation_id = ?
                  AND ev.status IN ('Pending Supervisor', 'Pending HR Consolidation')
                  AND e.is_active = 1
            ");
            $stmt->bind_param("iisi", $supervisor_id_nullable, $supervisor_id_nullable, $comments, $eval_id);
            $stmt->execute();
            $stmt->close();

            // Notify HR Manager employee directly
            $target_user_id = !empty($eval_info['submitted_by']) ? (int)$eval_info['submitted_by'] : (!empty($eval_info['emp_user_id']) ? (int)$eval_info['emp_user_id'] : null);
            if ($target_user_id) {
                createNotification(
                    $conn,
                    $target_user_id,
                    'Evaluation Approved',
                    "Your evaluation has been approved by the HR Supervisor.",
                    BASE_URL . '/employee/evaluation-history-view.php?id=' . $eval_id
                );
            }

            logAudit($conn, $supervisor_id, 'UPDATE', 'Evaluation', $eval_id, "Approved evaluation for HR Manager {$eval_info['emp_name']}");
            redirectWith($redirect_url, 'success', 'Evaluation approved successfully.');
        } else {
            $stmt = $conn->prepare("
                UPDATE evaluations ev
                INNER JOIN employees e ON ev.employee_id = e.employee_id
                SET ev.status = 'Pending Manager',
                    ev.endorsed_by = ?,
                    ev.endorsed_date = NOW(),
                    ev.evaluator_comments = ?
                WHERE ev.evaluation_id = ?
                  AND ev.status IN ('Pending Supervisor', 'Pending HR Consolidation')
                  AND e.is_active = 1
            ");
            $stmt->bind_param("isi", $supervisor_id_nullable, $comments, $eval_id);
            $stmt->execute();
            $stmt->close();

            // Notify all HR Managers
            $managers = $conn->query("SELECT user_id FROM users WHERE role = 'HR Manager' AND is_active = 1");
            while ($mgr = $managers->fetch_assoc()) {
                createNotification($conn, $mgr['user_id'], 'Evaluation Endorsed', "Evaluation for {$eval_info['emp_name']} has been endorsed and requires your approval.", BASE_URL . '/manager/pending-approvals.php');
            }
            logAudit($conn, $supervisor_id, 'UPDATE', 'Evaluation', $eval_id, "Endorsed evaluation for {$eval_info['emp_name']}");
            redirectWith($redirect_url, 'success', 'Evaluation endorsed and forwarded to HR Manager.');
        }

    } elseif ($action === 'return') {
        if (empty($comments)) {
            redirectWith($redirect_url, 'danger', 'Comments/rejection reason is required.');
        }

        if ($emp_hr_role === 'HR Manager') {
            $stmt = $conn->prepare("
                UPDATE evaluations ev
                INNER JOIN employees e ON ev.employee_id = e.employee_id
                SET ev.status = 'Returned',
                    ev.endorsed_by = ?,
                    ev.endorsed_date = NOW(),
                    ev.evaluator_comments = ?
                WHERE ev.evaluation_id = ?
                  AND ev.status IN ('Pending Supervisor', 'Pending HR Consolidation')
                  AND e.is_active = 1
            ");
            $stmt->bind_param("isi", $supervisor_id_nullable, $comments, $eval_id);
            $stmt->execute();
            $stmt->close();

            // Notify HR Manager employee directly
            $target_user_id = !empty($eval_info['submitted_by']) ? (int)$eval_info['submitted_by'] : (!empty($eval_info['emp_user_id']) ? (int)$eval_info['emp_user_id'] : null);
            if ($target_user_id) {
                createNotification(
                    $conn,
                    $target_user_id,
                    'Evaluation Returned for Revision',
                    "Your evaluation has been returned by the HR Supervisor for revision. Remarks: " . $comments,
                    BASE_URL . '/employee/self-rating.php?edit=' . $eval_id
                );
            }

            logAudit($conn, $supervisor_id, 'UPDATE', 'Evaluation', $eval_id, "Returned evaluation for HR Manager {$eval_info['emp_name']} for revision");
            redirectWith($redirect_url, 'warning', 'Evaluation returned to HR Manager for revision.');
        } else {
            $stmt = $conn->prepare("
                UPDATE evaluations ev
                INNER JOIN employees e ON ev.employee_id = e.employee_id
                SET ev.status = 'Returned',
                    ev.endorsed_by = ?,
                    ev.endorsed_date = NOW(),
                    ev.evaluator_comments = ?
                WHERE ev.evaluation_id = ?
                  AND ev.status IN ('Pending Supervisor', 'Pending HR Consolidation')
                  AND e.is_active = 1
            ");
            $stmt->bind_param("isi", $supervisor_id_nullable, $comments, $eval_id);
            $stmt->execute();
            $stmt->close();

            // Return the evaluation to the employee who submitted the self-rating.
            $emp_name = $eval_info['emp_name'];
            $target_user_id = !empty($eval_info['submitted_by']) ? (int)$eval_info['submitted_by'] : (!empty($eval_info['emp_user_id']) ? (int)$eval_info['emp_user_id'] : null);
            if ($target_user_id) {
                createNotification(
                    $conn,
                    $target_user_id,
                    'Evaluation Returned for Revision',
                    "Your evaluation has been returned by the HR Supervisor for revision. Remarks: " . $comments,
                    BASE_URL . '/employee/self-rating.php?edit=' . $eval_id
                );
            }

            logAudit($conn, $supervisor_id, 'UPDATE', 'Evaluation', $eval_id, "Returned/rejected evaluation for {$emp_name} to employee for revision");
            redirectWith($redirect_url, 'warning', 'Evaluation returned to employee for revision.');
        }
    }
}

$branchPendingBase = "
    FROM evaluations ev
    INNER JOIN employees e ON ev.employee_id = e.employee_id
    WHERE ev.status IN ('Pending Supervisor', 'Pending HR Consolidation')
      AND e.is_active = 1
      AND e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)
";

$branch_options = supervisorPendingRows(
    $conn,
    "SELECT DISTINCT b.branch_id, b.branch_name
     FROM evaluations ev
     INNER JOIN employees e ON ev.employee_id = e.employee_id
     INNER JOIN branches b ON e.branch_id = b.branch_id
     WHERE ev.status IN ('Pending Supervisor', 'Pending HR Consolidation')
       AND e.is_active = 1
       AND e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)
     ORDER BY b.branch_name"
);

$department_options = supervisorPendingRows(
    $conn,
    "SELECT DISTINCT d.department_id, d.department_name
     FROM evaluations ev
     INNER JOIN employees e ON ev.employee_id = e.employee_id
     INNER JOIN departments d ON e.department_id = d.department_id
     WHERE ev.status IN ('Pending Supervisor', 'Pending HR Consolidation')
       AND e.is_active = 1
       AND d.is_active = 1
       AND e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)
     ORDER BY d.department_name"
);

$staff_options = supervisorPendingRows(
    $conn,
    "SELECT DISTINCT u.user_id, u.full_name
     FROM evaluations ev
     INNER JOIN employees e ON ev.employee_id = e.employee_id
     INNER JOIN users u ON ev.submitted_by = u.user_id
     WHERE ev.status IN ('Pending Supervisor', 'Pending HR Consolidation')
       AND e.is_active = 1
       AND e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)
     ORDER BY u.full_name"
);

$template_options = supervisorPendingRows(
    $conn,
    "SELECT DISTINCT et.template_id, et.template_name
     FROM evaluations ev
     INNER JOIN employees e ON ev.employee_id = e.employee_id
     INNER JOIN evaluation_templates et ON ev.template_id = et.template_id
     WHERE ev.status IN ('Pending Supervisor', 'Pending HR Consolidation')
       AND e.is_active = 1
       AND e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)
     ORDER BY et.template_name"
);

$pendingWhere = "WHERE ev.status IN ('Pending Supervisor', 'Pending HR Consolidation')
    AND e.is_active = 1
    AND e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)";
$pendingTypes = "";
$pendingParams = [];

if ($filter_branch > 0) {
    $pendingWhere .= " AND e.branch_id = ?";
    $pendingTypes .= "i";
    $pendingParams[] = $filter_branch;
}

if ($filter_department > 0) {
    $pendingWhere .= " AND e.department_id = ?";
    $pendingTypes .= "i";
    $pendingParams[] = $filter_department;
}
if ($filter_staff > 0) {
    $pendingWhere .= " AND ev.submitted_by = ?";
    $pendingTypes .= "i";
    $pendingParams[] = $filter_staff;
}
if ($filter_template > 0) {
    $pendingWhere .= " AND ev.template_id = ?";
    $pendingTypes .= "i";
    $pendingParams[] = $filter_template;
}
if ($filter_type !== '') {
    $pendingWhere .= " AND ev.evaluation_type = ?";
    $pendingTypes .= "s";
    $pendingParams[] = $filter_type;
}
if ($date_from !== '') {
    $pendingWhere .= " AND ev.submitted_date >= ?";
    $pendingTypes .= "s";
    $pendingParams[] = $date_from;
}
if ($date_to !== '') {
    $pendingWhere .= " AND ev.submitted_date <= ?";
    $pendingTypes .= "s";
    $pendingParams[] = $date_to . ' 23:59:59';
}
if ($score_min !== null) {
    $pendingWhere .= " AND ev.total_score >= ?";
    $pendingTypes .= "d";
    $pendingParams[] = $score_min;
}
if ($score_max !== null) {
    $pendingWhere .= " AND ev.total_score <= ?";
    $pendingTypes .= "d";
    $pendingParams[] = $score_max;
}
if ($filter_attention === 'low_score') {
    $pendingWhere .= " AND ((ev.total_score IS NOT NULL AND ev.total_score < 2) OR ev.performance_level = 'Needs Improvement')";
} elseif ($filter_attention === 'overdue') {
    $pendingWhere .= " AND COALESCE(DATEDIFF(CURRENT_DATE(), DATE(ev.submitted_date)), 0) >= 7";
} elseif ($filter_attention === 'missing_score') {
    $pendingWhere .= " AND ev.total_score IS NULL";
}
if ($filter_search !== '') {
    $like = '%' . $filter_search . '%';
    $pendingWhere .= " AND (
        CONCAT(e.first_name, ' ', e.last_name) LIKE ?
        OR CONCAT(e.last_name, ', ', e.first_name) LIKE ?
        OR e.employee_code LIKE ?
        OR e.job_title LIKE ?
        OR et.template_name LIKE ?
    )";
    $pendingTypes .= "sssss";
    array_push($pendingParams, $like, $like, $like, $like, $like);
}

$all_pending = supervisorPendingRows(
    $conn,
    "SELECT ev.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
            e.employee_code, e.job_title, e.rank_category_id, e.profile_picture, d.department_name,
            u.full_name AS submitted_by_name, et.template_name,
            COALESCE(DATEDIFF(CURRENT_DATE(), DATE(ev.submitted_date)), 0) AS days_pending,
            sup.full_name AS supervisor_confirmed_by_name,
            ev.supervisor_confirmed_date,
            ev.supervisor_altered_scores,
            ev.sent_to_hr_date,
            ev.status AS eval_status,
            dm.full_name AS dept_manager_endorsed_by_name,
            b.branch_name
     FROM evaluations ev
     INNER JOIN employees e ON ev.employee_id = e.employee_id
     LEFT JOIN branches b ON e.branch_id = b.branch_id
     LEFT JOIN departments d ON e.department_id = d.department_id
     LEFT JOIN users u ON ev.submitted_by = u.user_id
     LEFT JOIN users sup ON ev.supervisor_confirmed_by = sup.user_id
     LEFT JOIN users dm ON ev.dept_manager_endorsed_by = dm.user_id
     LEFT JOIN evaluation_templates et ON ev.template_id = et.template_id
     $pendingWhere
     ORDER BY 
        CASE WHEN ev.status = 'Pending HR Consolidation' THEN 0 ELSE 1 END,
        days_pending DESC, ev.submitted_date ASC",
    $pendingTypes,
    $pendingParams
);

$branch_pending_count = (int) supervisorPendingScalar(
    $conn,
    "SELECT COUNT(*) $branchPendingBase"
);

$history_count = (int) supervisorPendingScalar(
    $conn,
    "SELECT COUNT(*)
     FROM evaluations ev
     INNER JOIN employees e ON ev.employee_id = e.employee_id
     WHERE ev.endorsed_by = ?
       AND ev.status IN ('Pending Manager', 'Approved', 'Rejected', 'Returned')",
    "i",
    [$supervisor_id]
);

$pending_count = count($all_pending);
$pending_groups = supervisorPendingGroupByEmployee($all_pending);
$employee_group_count = count($pending_groups);
$low_score_count = 0;
$overdue_count = 0;
$missing_score_count = 0;
$attention_count = 0;
$oldest_days = 0;
foreach ($all_pending as $row) {
    $has_score = supervisorPendingHasScore($row);
    $score = $has_score ? (float) $row['total_score'] : null;
    $_perf = $row['performance_level'] ?? '';
    if ($has_score && (empty($_perf) || $_perf === '0')) {
        $_perf = getPerformanceLevel($score);
    }
    $is_low = ($has_score && $score < 2) || $_perf === 'Needs Improvement';
    $is_overdue = (int) ($row['days_pending'] ?? 0) >= 7;
    $is_missing_score = !$has_score;
    if ($is_low) {
        $low_score_count++;
    }
    if ($is_overdue) {
        $overdue_count++;
    }
    if ($is_missing_score) {
        $missing_score_count++;
    }
    if ($is_low || $is_overdue || $is_missing_score) {
        $attention_count++;
    }
    $oldest_days = max($oldest_days, (int) ($row['days_pending'] ?? 0));
}



$active_filter_count = 0;
foreach ($filter_params as $value) {
    if ($value !== '' && $value !== null) {
        $active_filter_count++;
    }
}

require_once '../includes/header.php';
?>

<style>
    .pending-endorsements .pending-filter-card {
        background: #fff;
        border: 1px solid #eef2e8;
        border-radius: 14px;
        box-shadow: 0 8px 22px rgba(12, 32, 8, 0.06);
        margin-bottom: 18px;
        padding: 16px;
    }

    .pending-endorsements .pending-filter-card .form-label {
        color: var(--text-muted);
        font-size: 0.72rem;
        font-weight: 800;
        margin-bottom: 6px;
        text-transform: uppercase;
    }

    .pending-endorsements .pending-avatar {
        align-items: center;
        background: rgba(41, 67, 6, 0.08);
        border-radius: 12px;
        color: var(--primary-blue);
        display: inline-flex;
        flex: 0 0 42px;
        font-weight: 800;
        height: 42px;
        justify-content: center;
        overflow: hidden;
        width: 42px;
    }

    .pending-endorsements .pending-avatar img,
    .pending-endorsements .review-avatar img {
        height: 100%;
        object-fit: cover;
        width: 100%;
    }

    .pending-endorsements .pending-age-badge {
        border-radius: 999px;
        display: inline-flex;
        font-size: 0.72rem;
        font-weight: 800;
        gap: 6px;
        padding: 6px 10px;
        white-space: nowrap;
    }

    .pending-endorsements .pending-stage {
        color: var(--text-muted);
        font-size: 0.72rem;
        font-weight: 700;
        margin-top: 6px;
    }

    .pending-endorsements .score-meter {
        min-width: 96px;
    }

    .pending-endorsements .attention-chip {
        border-radius: 999px;
        border: 1px solid #e5e7eb;
        color: #4b5563;
        display: inline-flex;
        font-size: 0.66rem;
        font-weight: 800;
        padding: 4px 8px;
    }

    .pending-endorsements .attention-chip.is-danger {
        background: rgba(220, 53, 69, 0.1);
        border-color: rgba(220, 53, 69, 0.2);
        color: #dc3545;
    }

    .pending-endorsements .attention-chip.is-warning {
        background: rgba(255, 193, 7, 0.16);
        border-color: rgba(255, 193, 7, 0.28);
        color: #8a6400;
    }

    .pending-endorsements .attention-chip.is-muted {
        background: #f3f4f6;
        color: #4b5563;
    }

    .pending-endorsements .pending-table tbody tr {
        transition: background 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
    }

    .pending-endorsements .pending-table tbody tr:hover {
        background: #fbfcf8;
    }

    .pending-endorsements .pending-table tbody tr.pending-low-score {
        box-shadow: inset 4px 0 0 rgba(220, 53, 69, 0.7);
    }

    .pending-endorsements .pending-table tbody tr.pending-overdue {
        box-shadow: inset 4px 0 0 rgba(255, 193, 7, 0.85);
    }

    .pending-endorsements .pending-table tbody tr.pending-missing-score {
        box-shadow: inset 4px 0 0 rgba(108, 117, 125, 0.5);
    }

    .pending-endorsements .pending-table tbody tr.pending-low-score.pending-overdue {
        box-shadow: inset 4px 0 0 rgba(220, 53, 69, 0.7), inset 8px 0 0 rgba(255, 193, 7, 0.85);
    }

    .pending-endorsements .filter-meta {
        color: var(--text-muted);
        font-size: 0.78rem;
        font-weight: 700;
    }

    @media (max-width: 768px) {
        .pending-endorsements .pending-filter-card .btn,
        .pending-endorsements .pending-filter-card a.btn {
            width: 100%;
        }

        .pending-endorsements .table-responsive {
            overflow: visible;
        }

        .pending-endorsements .pending-table thead {
            display: none;
        }

        .pending-endorsements .pending-table,
        .pending-endorsements .pending-table tbody,
        .pending-endorsements .pending-table tr,
        .pending-endorsements .pending-table td {
            display: block;
            width: 100%;
        }

        .pending-endorsements .pending-table tbody {
            padding: 12px;
        }

        .pending-endorsements .pending-table tbody tr {
            background: #fff;
            border: 1px solid #eef2e8;
            border-radius: 14px;
            box-shadow: 0 8px 18px rgba(12, 32, 8, 0.06);
            margin: 0 0 14px;
            padding: 12px;
        }

        .pending-endorsements .pending-table tbody tr.pending-low-score,
        .pending-endorsements .pending-table tbody tr.pending-overdue,
        .pending-endorsements .pending-table tbody tr.pending-missing-score,
        .pending-endorsements .pending-table tbody tr.pending-low-score.pending-overdue {
            box-shadow: 0 8px 18px rgba(12, 32, 8, 0.06);
        }

        .pending-endorsements .pending-table td {
            align-items: center;
            border: 0;
            display: flex;
            gap: 14px;
            justify-content: space-between;
            padding: 8px 0 !important;
            text-align: right;
        }

        .pending-endorsements .pending-table td::before {
            color: var(--text-muted);
            content: attr(data-label);
            flex: 0 0 auto;
            font-size: 0.68rem;
            font-weight: 800;
            text-align: left;
            text-transform: uppercase;
        }

        .pending-endorsements .pending-table td.pending-primary {
            align-items: flex-start;
            display: block;
            text-align: left;
        }

        .pending-endorsements .pending-table td.pending-primary::before {
            display: none;
        }

        .pending-endorsements .pending-table td[data-label="Actions"] .btn {
            width: 100%;
        }

        .pending-endorsements .fixed-action-bar {
            flex-direction: column;
        }

        .pending-endorsements .fixed-action-bar .btn {
            width: 100%;
        }
    }

    .badge-audit {
        background: rgba(255, 193, 7, 0.15);
        color: #d39e00;
        border: 1px solid rgba(255, 193, 7, 0.4);
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        backdrop-filter: blur(4px);
        margin-left: 5px;
        vertical-align: middle;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .badge-audit:hover {
        background: rgba(255, 193, 7, 0.25);
        transform: translateY(-1px);
    }
    .score-input {
        transition: all 0.3s ease;
        background: rgba(255, 255, 255, 0.95);
        border: 1px solid #ced4da;
    }
    .score-input:focus {
        background: #fff;
        border-color: #BD9414;
        box-shadow: 0 0 0 0.2rem rgba(189, 148, 20, 0.25);
    }

    .pending-endorsements .pending-group-row {
        background: #fbfcf8;
        cursor: pointer;
    }

    .pending-endorsements .pending-group-row:hover {
        background: #f4f8ef;
    }

    .pending-endorsements .pending-group-row .group-toggle-icon {
        transition: transform 0.2s ease;
    }

    .pending-endorsements .pending-group-row[aria-expanded="true"] .group-toggle-icon {
        transform: rotate(180deg);
    }

    .pending-endorsements .pending-nested-wrap {
        background: #f8faf5;
        border-top: 1px solid #eef2e8;
        padding: 0 12px 12px;
    }

    .pending-endorsements .pending-nested-table thead th {
        background: transparent;
        border-bottom: 1px solid #e5ebe0;
        color: var(--text-muted);
        font-size: 0.68rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    .pending-endorsements .pending-nested-table tbody tr {
        background: #fff;
    }

    .pending-endorsements .pending-nested-table tbody tr + tr {
        border-top: 1px solid #eef2e8;
    }

    .pending-endorsements .pending-eval-count-badge {
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 800;
        padding: 4px 10px;
    }
</style>

<div class="pending-endorsements" data-queue-auto-refresh="supervisor">
<div class="page-hero fadeup">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-3">
        <div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:0;color:rgba(255,255,255,.55);">HR Supervisor · Evaluation Review</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-clipboard-check me-2" style="color:#BD9414;"></i>Pending Endorsements</h4>
        </div>
        <div style="color:rgba(255,255,255,.6);font-size:.8rem;">
            <i class="fas fa-hourglass-half me-1"></i><?php echo number_format($branch_pending_count); ?> total pending
        </div>
    </div>
    <p class="text-white-50 small mb-0"><i class="fas fa-check-double me-1"></i>Review staff evaluations, add supervisor feedback, and forward endorsed records to HR Manager.</p>

    <div class="row g-3 mb-4 mt-4" id="supervisorQueueSummary">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo number_format($branch_pending_count); ?></div>
                        <div class="stat-label">Total Pending</div>
                    </div>
                    <i class="fas fa-hourglass-half stat-icon" style="color:#ffc107;"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo number_format($pending_count); ?></div>
                        <div class="stat-label">Filtered View</div>
                    </div>
                    <i class="fas fa-filter stat-icon" style="color:#17a2b8;"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <a href="pending-endorsements.php?attention=low_score" class="stat-card text-decoration-none">
                <div class="d-flex justify-content-between align-items-start w-100">
                    <div>
                        <div class="stat-value"><?php echo number_format($attention_count); ?></div>
                        <div class="stat-label">Needs Attention</div>
                    </div>
                    <i class="fas fa-triangle-exclamation stat-icon" style="color:#dc3545;"></i>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="evaluation-history.php" class="stat-card text-decoration-none">
                <div class="d-flex justify-content-between align-items-start w-100">
                    <div>
                        <div class="stat-value"><?php echo number_format($history_count); ?></div>
                        <div class="stat-label">Processed</div>
                    </div>
                    <i class="fas fa-history stat-icon" style="color:#28a745;"></i>
                </div>
            </a>
        </div>
    </div>
</div>

<div class="pending-filter-card fadeup fadeup-1">
    <form method="GET" action="" class="w-100">
        <!-- Top Row: Search, Quick Filter Toggle, and Advanced button -->
        <div class="row g-2 align-items-center mb-3">
            <div class="col-md-6 col-lg-7">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
                    <input type="search" class="form-control border-start-0 ps-0" name="q" value="<?php echo e($filter_search); ?>" placeholder="Search employee name, code, position, template...">
                </div>
            </div>
            <div class="col-md-3 col-lg-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-bell"></i></span>
                    <select class="form-select border-start-0 ps-0" name="attention">
                        <option value="">Status/Alerts: All</option>
                        <option value="low_score" <?php echo $filter_attention === 'low_score' ? 'selected' : ''; ?>>Low Score (&lt; 2.0)</option>
                        <option value="overdue" <?php echo $filter_attention === 'overdue' ? 'selected' : ''; ?>>Overdue (7+ Days)</option>
                        <option value="missing_score" <?php echo $filter_attention === 'missing_score' ? 'selected' : ''; ?>>No Score</option>
                    </select>
                </div>
            </div>
            <div class="col-md-3 col-lg-2 d-grid">
                <?php 
                $adv_filter_count = 0;
                if ($filter_branch > 0) $adv_filter_count++;
                if ($filter_department > 0) $adv_filter_count++;
                if ($filter_staff > 0) $adv_filter_count++;
                if ($filter_template > 0) $adv_filter_count++;
                if ($filter_type !== '') $adv_filter_count++;
                if ($date_from !== '') $adv_filter_count++;
                if ($date_to !== '') $adv_filter_count++;
                if ($score_min !== null) $adv_filter_count++;
                if ($score_max !== null) $adv_filter_count++;
                ?>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFiltersCollapse" aria-expanded="<?php echo ($adv_filter_count > 0) ? 'true' : 'false'; ?>" aria-controls="advancedFiltersCollapse">
                    <i class="fas fa-sliders me-1"></i> Advanced
                    <?php if ($adv_filter_count > 0): ?>
                        <span class="badge bg-primary text-white ms-1"><?php echo $adv_filter_count; ?></span>
                    <?php endif; ?>
                </button>
            </div>
        </div>

        <!-- Collapsible Advanced Filters Drawer -->
        <div class="collapse <?php echo ($adv_filter_count > 0) ? 'show' : ''; ?>" id="advancedFiltersCollapse">
            <div class="card card-body bg-light border-0 p-3 mb-3 rounded-3">
                <div class="row g-3">
                    <!-- Branch -->
                    <div class="col-md-4 col-lg-3">
                        <label class="form-label text-muted small fw-bold mb-1 d-block" style="font-size: 0.65rem;">Branch</label>
                        <select class="form-select form-select-sm" name="branch">
                            <option value="">All Branches</option>
                            <?php foreach ($branch_options as $branch): ?>
                                <option value="<?php echo (int) $branch['branch_id']; ?>" <?php echo $filter_branch === (int) $branch['branch_id'] ? 'selected' : ''; ?>>
                                    <?php echo e($branch['branch_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Department -->
                    <div class="col-md-4 col-lg-3">
                        <label class="form-label text-muted small fw-bold mb-1 d-block" style="font-size: 0.65rem;">Department</label>
                        <select class="form-select form-select-sm" name="department">
                            <option value="">All Departments</option>
                            <?php foreach ($department_options as $dept): ?>
                                <option value="<?php echo (int) $dept['department_id']; ?>" <?php echo $filter_department === (int) $dept['department_id'] ? 'selected' : ''; ?>>
                                    <?php echo e($dept['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Evaluation Type -->
                    <div class="col-md-4 col-lg-3">
                        <label class="form-label text-muted small fw-bold mb-1 d-block" style="font-size: 0.65rem;">Evaluation Type</label>
                        <select class="form-select form-select-sm" name="evaluation_type">
                            <option value="">All Types</option>
                            <?php foreach ($allowed_eval_types as $type): ?>
                                <option value="<?php echo e($type); ?>" <?php echo $filter_type === $type ? 'selected' : ''; ?>>
                                    <?php echo e($type); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Template -->
                    <div class="col-md-4 col-lg-3">
                        <label class="form-label text-muted small fw-bold mb-1 d-block" style="font-size: 0.65rem;">Template</label>
                        <select class="form-select form-select-sm" name="template">
                            <option value="">All Templates</option>
                            <?php foreach ($template_options as $tpl): ?>
                                <option value="<?php echo (int) $tpl['template_id']; ?>" <?php echo $filter_template === (int) $tpl['template_id'] ? 'selected' : ''; ?>>
                                    <?php echo e($tpl['template_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Submitted By -->
                    <div class="col-md-4 col-lg-3">
                        <label class="form-label text-muted small fw-bold mb-1 d-block" style="font-size: 0.65rem;">Submitted By</label>
                        <select class="form-select form-select-sm" name="submitted_by">
                            <option value="">All Staff</option>
                            <?php foreach ($staff_options as $staff): ?>
                                <option value="<?php echo (int) $staff['user_id']; ?>" <?php echo $filter_staff === (int) $staff['user_id'] ? 'selected' : ''; ?>>
                                    <?php echo e($staff['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Score Range -->
                    <div class="col-md-4 col-lg-3">
                        <label class="form-label text-muted small fw-bold mb-1 d-block" style="font-size: 0.65rem;">Score Range</label>
                        <div class="input-group input-group-sm">
                            <input type="number" step="0.01" min="0" max="4" class="form-control" name="score_min" value="<?php echo $score_min !== null ? e($score_min) : ''; ?>" placeholder="Min">
                            <span class="input-group-text bg-white">-</span>
                            <input type="number" step="0.01" min="0" max="4" class="form-control" name="score_max" value="<?php echo $score_max !== null ? e($score_max) : ''; ?>" placeholder="Max">
                        </div>
                    </div>
                    <!-- Submitted Date Range -->
                    <div class="col-md-8 col-lg-6">
                        <label class="form-label text-muted small fw-bold mb-1 d-block" style="font-size: 0.65rem;">Date Submitted Range</label>
                        <div class="input-group input-group-sm">
                            <input type="date" class="form-control" name="date_from" value="<?php echo e($date_from); ?>">
                            <span class="input-group-text bg-white">to</span>
                            <input type="date" class="form-control" name="date_to" value="<?php echo e($date_to); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Row -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="filter-meta">
                <?php if ($active_filter_count > 0): ?>
                    <span class="text-primary fw-bold" style="font-size: 0.78rem;"><i class="fas fa-circle-info me-1"></i><?php echo $active_filter_count; ?> active filter<?php echo $active_filter_count === 1 ? '' : 's'; ?></span>
                <?php else: ?>
                    <span class="text-muted small"><i class="fas fa-circle-check me-1"></i>Showing all records</span>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2">
                <a href="pending-endorsements.php" class="btn btn-sm btn-outline-secondary px-3">
                    <i class="fas fa-rotate-left me-1"></i>Reset
                </a>
                <button type="submit" class="btn btn-sm btn-primary px-4">
                    <i class="fas fa-filter me-1"></i>Apply Filters
                </button>
            </div>
        </div>
    </form>
</div>

<div class="content-card border-0 shadow-sm fadeup fadeup-2" id="supervisorQueueList">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <h5 class="mb-0"><i class="fas fa-clipboard-check me-2 text-primary"></i>Evaluations Pending Endorsement</h5>
        <span class="badge bg-light text-muted border"><?php echo number_format($employee_group_count); ?> employee<?php echo $employee_group_count === 1 ? '' : 's'; ?> · <?php echo number_format($pending_count); ?> evaluation<?php echo $pending_count === 1 ? '' : 's'; ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 pending-table" id="pendingTable">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-3">Employee</th>
                        <th>Department</th>
                        <th>Submitted By</th>
                        <th>Submitted</th>
                        <th>Type & Progress</th>
                        <th>Score & Alerts</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pending_groups)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-5"><i class="fas fa-check-circle fa-3x mb-3 d-block opacity-25"></i>No pending endorsements in this view.</td></tr>
                    <?php else: ?>
                        <?php foreach ($pending_groups as $group): ?>
                            <?php
                            $groupEvaluations = $group['evaluations'];
                            $groupCount = count($groupEvaluations);
                            $groupId = (int) $group['employee_id'];
                            $groupCollapseId = 'pendingGroup' . $groupId;
                            $avatar_url = getEmployeeAvatar($group['profile_picture'] ?? '');
                            $groupDays = (int) ($group['max_days_pending'] ?? 0);
                            $groupAgeLabel = $groupDays === 0 ? 'Today' : $groupDays . ' day' . ($groupDays === 1 ? '' : 's');
                            $groupIsOverdue = $groupDays >= 7;
                            $groupAgeClass = $groupIsOverdue ? 'bg-warning-subtle text-warning border border-warning-subtle' : 'bg-primary-subtle text-primary border border-primary-subtle';
                            $groupAttentionFlags = $group['attention_flags'];
                            $groupRowClass = trim(
                                (in_array('Low score', $groupAttentionFlags, true) ? 'pending-low-score ' : '')
                                . (in_array('Overdue', $groupAttentionFlags, true) ? 'pending-overdue ' : '')
                                . (in_array('No score', $groupAttentionFlags, true) ? 'pending-missing-score' : '')
                            );
                            ?>
                            <?php if ($groupCount === 1): ?>
                                <?php supervisorPendingRenderEvaluationRow($groupEvaluations[0], 'full'); ?>
                            <?php else: ?>
                                <tr class="pending-group-row <?php echo e($groupRowClass); ?>">
                                    <td class="ps-3 pending-primary" data-label="Employee">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="pending-avatar">
                                                <img src="<?php echo e($avatar_url); ?>" alt="<?php echo e($group['employee_name']); ?> profile picture">
                                            </div>
                                            <div class="min-w-0">
                                                <div class="fw-bold"><?php echo e($group['employee_name']); ?></div>
                                                <div class="small company-id-text">Company ID: <span class="company-id-value"><?php echo e(getEmployeeDisplayId($group)); ?></span></div>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle pending-eval-count-badge mt-1">
                                                    <i class="fas fa-layer-group me-1"></i><?php echo $groupCount; ?> evaluations pending
                                                </span>
                                                <?php if (!empty($groupAttentionFlags)): ?>
                                                    <div class="d-flex flex-wrap gap-1 mt-2">
                                                        <?php foreach ($groupAttentionFlags as $flag): ?>
                                                            <span class="attention-chip <?php echo $flag === 'Low score' ? 'is-danger' : ($flag === 'Overdue' ? 'is-warning' : 'is-muted'); ?>"><?php echo e($flag); ?></span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td data-label="Department">
                                        <div class="fw-semibold"><?php echo e($group['job_title'] ?? 'N/A'); ?></div>
                                        <small class="text-muted"><?php echo e($group['department_name'] ?? 'Unassigned'); ?></small>
                                    </td>
                                    <td data-label="Submitted By" colspan="2">
                                        <div class="small text-muted">Expand to review each submission.</div>
                                    </td>
                                    <td data-label="Type & Progress">
                                        <span class="badge bg-info-subtle text-info border border-info-subtle"><?php echo $groupCount; ?> templates</span>
                                        <div class="pending-stage">Multiple evaluations awaiting validation</div>
                                    </td>
                                    <td data-label="Score & Alerts">
                                        <span class="pending-age-badge <?php echo e($groupAgeClass); ?>"><i class="fas fa-clock"></i><?php echo e($groupAgeLabel); ?> oldest</span>
                                    </td>
                                    <td class="text-end pe-3" data-label="Actions">
                                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-sm group-toggle-btn" data-bs-toggle="collapse" data-bs-target="#<?php echo e($groupCollapseId); ?>" aria-expanded="false" aria-controls="<?php echo e($groupCollapseId); ?>">
                                            <i class="fas fa-chevron-down group-toggle-icon me-1"></i><span class="group-toggle-label">Show</span>
                                        </button>
                                    </td>
                                </tr>
                                <tr class="collapse pending-group-collapse" id="<?php echo e($groupCollapseId); ?>">
                                    <td colspan="7" class="p-0 border-0">
                                        <div class="pending-nested-wrap">
                                            <table class="table table-sm align-middle mb-0 pending-nested-table">
                                                <thead>
                                                    <tr>
                                                        <th class="ps-3">Template</th>
                                                        <th>Submitted By</th>
                                                        <th>Submitted</th>
                                                        <th>Type &amp; Progress</th>
                                                        <th>Score &amp; Alerts</th>
                                                        <th class="text-end pe-3">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($groupEvaluations as $row): ?>
                                                        <?php supervisorPendingRenderEvaluationRow($row, 'nested'); ?>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="supervisorQueueModals">
<?php 
// Render Modals at the end of the file
foreach ($all_pending as $row): 
    $modal_eval_id = (int) $row['evaluation_id'];
    $initials = strtoupper(substr($row['employee_name'], 0, 1) . substr(explode(' ', $row['employee_name'])[1] ?? '', 0, 1));
    $modal_avatar_url = BASE_URL . '/assets/img/logo/logo.png';
    $modal_has_score = supervisorPendingHasScore($row);
?>
    <div class="modal fade modal-premium" id="reviewModal<?php echo $modal_eval_id; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-1">Review Evaluation</h5>
                        <p class="mb-0 opacity-75 small">Reviewing evaluation for <?php echo e($row['employee_name']); ?> (<?php echo e($row['branch_name'] ?? 'No Branch'); ?>)</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 pt-0">
                    <!-- Status Stepper -->
                    <div class="status-stepper d-flex justify-content-between mb-4 py-3 border-bottom overflow-hidden">
                        <?php
                        $steps = [
                            ['l' => 'Drafted', 'a' => true, 'i' => 'fa-pencil-alt'],
                            ['l' => 'Supervisor', 'a' => true, 'i' => 'fa-user-tie', 'c' => true],
                            ['l' => 'Review', 'a' => false, 'i' => 'fa-user-shield'],
                            ['l' => 'Final', 'a' => false, 'i' => 'fa-check-double']
                        ];
                        foreach ($steps as $st): ?>
                            <div class="step-item text-center <?php echo $st['a'] ? 'text-primary' : 'text-muted'; ?>" style="flex: 1;">
                                <div class="mb-1">
                                    <i class="fas <?php echo $st['i']; ?> <?php echo isset($st['c']) ? 'fa-pulse' : ''; ?>"></i>
                                </div>
                                <div style="font-size: 0.65rem; font-weight: 800; text-transform: uppercase;"><?php echo $st['l']; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php
                    $can_edit_scores = in_array($row['eval_status'] ?? $row['status'] ?? '', ['Pending Supervisor', 'Pending HR Consolidation'], true) && ((int)($row['rank_category_id'] ?? 0) === 5 || getEmployeeHRRole($conn, $row['employee_id']) === 'HR Manager');
                    ?>
                    

                    <div class="eval-summary-header">
                        <div class="d-flex align-items-center gap-3">
                            <div class="review-avatar bg-white border d-flex align-items-center justify-content-center fw-bold rounded overflow-hidden" style="width: 55px; height: 55px; font-size: 1.2rem;">
                                <img src="<?php echo e($modal_avatar_url); ?>?v=<?php echo time(); ?>" alt="Raquel Pawnshop logo">
                            </div>
                            <div>
                                <h4 class="mb-0 fw-bold"><?php echo e($row['employee_name']); ?></h4>
                                <div class="text-muted"><?php echo e($row['job_title'] ?? 'Staff'); ?> &bull; <?php echo e($row['template_name']); ?></div>
                            </div>
                        </div>
                        <?php if ($modal_has_score): ?>
                            <?php echo getEvaluationScoreCirclesHtml($conn, $modal_eval_id, $row['total_score']); ?>
                        <?php else: ?>
                            <div class="score-circle">
                                <div class="val">No score</div>
                                <div class="lbl">Score</div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Audit Trail Collapsible Panel -->
                    <?php
                    $modal_audit_stmt = $conn->prepare("
                        SELECT al.*, u.full_name, u.role, e.job_title
                        FROM audit_logs al
                        LEFT JOIN users u ON al.user_id = u.user_id
                        LEFT JOIN employees e ON u.employee_id = e.employee_id
                        WHERE al.entity_type = 'Evaluation' AND al.entity_id = ?
                        ORDER BY al.timestamp ASC, al.log_id ASC
                    ");
                    $modal_audit_stmt->bind_param("i", $modal_eval_id);
                    $modal_audit_stmt->execute();
                    $modal_audit_logs = $modal_audit_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $modal_audit_stmt->close();
                    ?>
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold mb-2"
                            data-bs-toggle="collapse"
                            data-bs-target="#auditTrail<?php echo $modal_eval_id; ?>"
                            aria-expanded="false">
                        <i class="fas fa-history me-1"></i>Audit Trail
                    </button>
                    <div class="collapse mb-3" id="auditTrail<?php echo $modal_eval_id; ?>">
                        <div class="border rounded p-3 mt-2" style="background:#f8fafc;">
                            <div class="fw-semibold text-secondary mb-3" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.5px;">
                                <i class="fas fa-history me-1"></i>Evaluation Audit Trail
                            </div>
                            <?php if (empty($modal_audit_logs)): ?>
                                <p class="text-muted small mb-0">No audit logs found for this evaluation.</p>
                            <?php else: ?>
                                <div style="border-left:2px solid #e2e8f0;padding-left:18px;position:relative;">
                                    <?php foreach ($modal_audit_logs as $log): ?>
                                        <div style="position:relative;margin-bottom:12px;">
                                            <div style="width:10px;height:10px;border-radius:50%;background:#3b82f6;position:absolute;left:-25px;top:4px;"></div>
                                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-1 mb-1">
                                                <span class="fw-bold text-dark" style="font-size:.8rem;word-break:break-word;">
                                                    <?php echo e($log['full_name'] ?? 'System'); ?>
                                                    <span class="text-muted fw-normal">(<?php echo e(!empty($log['job_title']) ? $log['job_title'] : ($log['role'] ?? 'System')); ?>)</span>
                                                </span>
                                                <span class="text-muted ms-auto" style="font-size:.72rem;white-space:nowrap;"><?php echo formatDateTime($log['timestamp']); ?></span>
                                            </div>
                                            <div class="text-secondary fw-semibold" style="font-size:.78rem;"><?php echo e($log['action_type']); ?> — <?php echo e(explode('.', $log['details'])[0]); ?></div>
                                            <?php if (strpos($log['details'], 'Score adjustments:') !== false): ?>
                                                <div class="mt-1 p-2 bg-white rounded border text-muted" style="white-space:pre-wrap;font-family:monospace;font-size:.72rem;"><?php echo e(substr($log['details'], strpos($log['details'], 'Score adjustments:'))); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- KRA Section -->
                    <div class="section-premium-label mb-3 mt-4">
                        <i class="fas fa-bullseye"></i> I. Strategic Programs &amp; Requirements
                    </div>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-hover align-middle border-start">
                            <thead class="small text-muted bg-light">
                                <tr>
                                    <th class="ps-3">Criterion</th>
                                    <th class="text-center" style="width: 80px;">Weight</th>
                                    <th class="text-center" style="width: 80px;">Rating</th>
                                    <th class="text-center" style="width: 80px;">Total</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <?php
                                $kra_q = $conn->query("SELECT es.*, ec.criterion_name, ec.description, ec.weight FROM evaluation_scores es JOIN evaluation_criteria ec ON es.criterion_id = ec.criterion_id WHERE es.evaluation_id = $modal_eval_id AND ec.section = 'KRA' ORDER BY ec.sort_order");
                                $kra_num = 1;
                                while ($k = $kra_q->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <div class="fw-bold">KRA <?php echo $kra_num++; ?>: <?php echo e($k['criterion_name']); ?></div>
                                            <?php if($k['description']): ?><div class="text-muted x-small"><?php echo e($k['description']); ?></div><?php endif; ?>
                                        </td>
                                        <td class="text-center"><?php echo $k['weight']; ?>%</td>
                                        <td class="text-center">
                                            <?php
                                            $effective_score = $k['score_value'];
                                            $dept_mgr_override_score = $k['dept_manager_override_score'] ?? null;
                                            $supervisor_override_score = $k['supervisor_override_score'] ?? null;
                                            $badge_html = '';
                                            if ($dept_mgr_override_score !== null) {
                                                $effective_score = $dept_mgr_override_score;
                                                $dm_name_q = $conn->query("SELECT full_name FROM users WHERE user_id = " . (int)($k['dept_manager_override_by'] ?? 0))->fetch_assoc();
                                                $dm_name = $dm_name_q['full_name'] ?? 'Dept Manager';
                                                $dm_formatted_date = formatDate($k['dept_manager_override_at'] ?? '', 'M d, Y h:i A');
                                                $badge_html .= '<span class="badge-audit ms-2" style="background:rgba(23,162,184,.15);color:#0c7a96;border-color:rgba(23,162,184,.4);" data-bs-toggle="tooltip" data-bs-html="true" title="<strong>Dept Manager Adjustment</strong><br>Adjusted by: ' . e($dm_name) . '<br>On: ' . $dm_formatted_date . '<br>Original Self-Rating: ' . $k['score_value'] . '"><i class="fas fa-user-tie me-1"></i>Mgr Adjusted</span>';
                                            }
                                            if ($supervisor_override_score !== null) {
                                                $effective_score = $supervisor_override_score;
                                                $sup_name_q = $conn->query("SELECT full_name FROM users WHERE user_id = " . (int)($k['supervisor_override_by'] ?? 0))->fetch_assoc();
                                                $sup_name = $sup_name_q['full_name'] ?? 'Supervisor';
                                                $formatted_date = formatDate($k['supervisor_override_at'] ?? '', 'M d, Y h:i A');
                                                $badge_html .= '<span class="badge-audit ms-2" data-bs-toggle="tooltip" data-bs-html="true" title="<strong>Supervisor Override</strong><br>Edited by: ' . e($sup_name) . '<br>On: ' . $formatted_date . '<br>Original: ' . $k['score_value'] . '"><i class="fas fa-user-edit me-1"></i>Sup Override</span>';
                                            }
                                            ?>
                                            <?php if ($can_edit_scores): ?>
                                            <span class="score-display fw-bold"><?php echo number_format($effective_score, 2); ?></span>
                                            <input type="number" step="0.01" min="1.00" max="4.00" class="form-control form-control-sm score-input d-none text-center mx-auto" data-score-id="<?php echo $k['score_id']; ?>" data-original-val="<?php echo number_format($effective_score, 2); ?>" value="<?php echo number_format($effective_score, 2); ?>" style="width:75px;margin:0 auto;">
                                            <?php else: ?>
                                            <span class="fw-bold"><?php echo number_format($effective_score, 2); ?></span>
                                            <?php endif; ?>
                                            <?php echo $badge_html; ?>
                                        </td>
                                        <td class="text-center text-primary fw-bold"><?php echo $k['weighted_score']; ?></td>
                                    </tr>
                                <?php endwhile; ?>
                                <tr class="total-row bg-light fw-bold border-top">
                                    <td class="ps-3">KRA Sub-total</td>
                                    <td class="text-center">100%</td>
                                    <td></td>
                                    <td class="text-center text-primary"><?php echo $row['kra_subtotal']; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Behavior Section -->
                    <div class="section-premium-label mb-3 mt-5">
                        <i class="fas fa-heart"></i> II. Behavior &amp; Values
                    </div>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-hover align-middle border-start">
                            <thead class="small text-muted bg-light">
                                <tr>
                                    <th class="ps-3">Behavior KPI</th>
                                    <th class="text-center" style="width: 100px;">Rating (1-4)</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <?php
                                $beh_q = $conn->query("SELECT es.*, ec.criterion_name, ec.kpi_description FROM evaluation_scores es JOIN evaluation_criteria ec ON es.criterion_id = ec.criterion_id WHERE es.evaluation_id = $modal_eval_id AND ec.section = 'Behavior' ORDER BY ec.sort_order");
                                while ($b = $beh_q->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <div class="fw-bold"><?php echo e($b['criterion_name']); ?></div>
                                            <div class="text-muted x-small"><?php echo e($b['kpi_description']); ?></div>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                            $effective_score = $b['score_value'];
                                            $dept_mgr_override_score = $b['dept_manager_override_score'] ?? null;
                                            $supervisor_override_score = $b['supervisor_override_score'] ?? null;
                                            $badge_html = '';
                                            if ($dept_mgr_override_score !== null) {
                                                $effective_score = $dept_mgr_override_score;
                                                $dm_name_q = $conn->query("SELECT full_name FROM users WHERE user_id = " . (int)($b['dept_manager_override_by'] ?? 0))->fetch_assoc();
                                                $dm_name = $dm_name_q['full_name'] ?? 'Dept Manager';
                                                $dm_formatted_date = formatDate($b['dept_manager_override_at'] ?? '', 'M d, Y h:i A');
                                                $badge_html .= '<span class="badge-audit ms-2" style="background:rgba(23,162,184,.15);color:#0c7a96;border-color:rgba(23,162,184,.4);" data-bs-toggle="tooltip" data-bs-html="true" title="<strong>Dept Manager Adjustment</strong><br>Adjusted by: ' . e($dm_name) . '<br>On: ' . $dm_formatted_date . '<br>Original Self-Rating: ' . $b['score_value'] . '"><i class="fas fa-user-tie me-1"></i>Mgr Adjusted</span>';
                                            }
                                            if ($supervisor_override_score !== null) {
                                                $effective_score = $supervisor_override_score;
                                                $sup_name_q = $conn->query("SELECT full_name FROM users WHERE user_id = " . (int)($b['supervisor_override_by'] ?? 0))->fetch_assoc();
                                                $sup_name = $sup_name_q['full_name'] ?? 'Supervisor';
                                                $formatted_date = formatDate($b['supervisor_override_at'] ?? '', 'M d, Y h:i A');
                                                $badge_html .= '<span class="badge-audit ms-2" data-bs-toggle="tooltip" data-bs-html="true" title="<strong>Supervisor Override</strong><br>Edited by: ' . e($sup_name) . '<br>On: ' . $formatted_date . '<br>Original: ' . $b['score_value'] . '"><i class="fas fa-user-edit me-1"></i>Sup Override</span>';
                                            }
                                            ?>
                                            <?php if ($can_edit_scores): ?>
                                            <span class="score-display text-primary fw-bold"><?php echo number_format($effective_score, 2); ?></span>
                                            <input type="number" step="0.01" min="1.00" max="4.00" class="form-control form-control-sm score-input d-none text-center mx-auto" data-score-id="<?php echo $b['score_id']; ?>" data-original-val="<?php echo number_format($effective_score, 2); ?>" value="<?php echo number_format($effective_score, 2); ?>" style="width:75px;margin:0 auto;">
                                            <?php else: ?>
                                            <span class="text-primary fw-bold"><?php echo number_format($effective_score, 2); ?></span>
                                            <?php endif; ?>
                                            <?php echo $badge_html; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                <tr class="total-row bg-light fw-bold border-top">
                                    <td class="ps-3">Behavior Average</td>
                                    <td class="text-center text-primary"><?php echo $row['behavior_average']; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Employee Comments -->
                    <?php if (!empty($row['staff_comments'])): ?>
                    <div class="section-premium-label mb-3 mt-5">
                        <i class="fas fa-comment-dots"></i> Employee Comments / Notes
                    </div>
                    <div class="p-3 bg-light rounded-3 mb-4 border-start border-4 border-primary">
                        <p class="mb-0 text-dark small" style="white-space: pre-wrap;"><?php echo e($row['staff_comments']); ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Department Level Comments -->
                    <?php if (!empty($row['supervisor_comments']) || !empty($row['dept_manager_comments'])): ?>
                    <div class="section-premium-label mb-3 mt-5">
                        <i class="fas fa-sitemap"></i> Department-Level Feedback
                    </div>
                    <?php if (!empty($row['supervisor_comments'])): ?>
                    <div class="p-3 bg-light rounded-3 mb-3 border-start border-4 border-warning">
                        <div class="fw-bold small text-warning mb-1">Department Supervisor Comments (<?php echo e($row['supervisor_confirmed_by_name'] ?? 'Immediate Head'); ?>):</div>
                        <p class="mb-0 text-dark small" style="white-space: pre-wrap;"><?php echo e($row['supervisor_comments']); ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($row['dept_manager_comments'])): ?>
                    <div class="p-3 bg-light rounded-3 mb-4 border-start border-4 border-info">
                        <div class="fw-bold small text-info mb-1">Department Manager Comments (<?php echo e($row['dept_manager_endorsed_by_name'] ?? 'Dept Manager'); ?>):</div>
                        <p class="mb-0 text-dark small" style="white-space: pre-wrap;"><?php echo e($row['dept_manager_comments']); ?></p>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>

                    <!-- Career Growth -->
                    <?php $cg_suited = !empty($row['career_growth_suited']) ? 1 : (!empty($row['desired_position']) ? 1 : 0); ?>
                    <div class="section-premium-label mb-3 mt-5">
                        <i class="fas fa-chart-line"></i> III. Career Growth
                        <button type="button" class="btn btn-sm btn-outline-primary ms-2" id="editCareerBtn<?php echo $modal_eval_id; ?>" onclick="toggleCareerEdit(<?php echo $modal_eval_id; ?>)"><i class="fas fa-edit me-1"></i>Edit</button>
                    </div>
                    <div class="p-3 bg-light rounded-3 mb-4 border-start border-4 border-info">
                        <!-- View mode -->
                        <div id="careerView<?php echo $modal_eval_id; ?>">
                            <div class="mb-2 fw-semibold" style="font-size:0.9rem;">
                                Is the employee better suited for another job within the company?
                                <span class="badge ms-2 <?php echo $cg_suited ? 'bg-success' : 'bg-secondary'; ?>" id="careerSuitedBadge<?php echo $modal_eval_id; ?>">
                                    <?php echo $cg_suited ? '&#9745; Yes' : '&#9744; No'; ?>
                                </span>
                            </div>
                            <div class="cg-details-container mt-1 <?php echo !$cg_suited ? 'd-none' : ''; ?>" id="careerPositionContainer<?php echo $modal_eval_id; ?>">
                                <div class="small text-muted">
                                    <i class="fas fa-briefcase me-1 text-info"></i>
                                    <strong>Job Function / Department:</strong>
                                    <span class="text-dark fw-semibold ms-1" id="careerPositionText<?php echo $modal_eval_id; ?>"><?php echo e($row['desired_position'] ?? 'N/A'); ?></span>
                                </div>
                            </div>
                            <div class="cg-details-container mt-1 <?php echo (!$cg_suited || empty($row['target_date'])) ? 'd-none' : ''; ?>" id="careerDateContainer<?php echo $modal_eval_id; ?>">
                                <div class="small text-muted">
                                    <i class="fas fa-calendar-alt me-1 text-info"></i>
                                    <strong>Target Date:</strong>
                                    <span class="text-dark fw-semibold ms-1" id="careerDateText<?php echo $modal_eval_id; ?>"><?php echo e($row['target_date'] ?? 'N/A'); ?></span>
                                </div>
                            </div>
                            <div class="cg-details-container mt-1 <?php echo (!$cg_suited || empty($row['career_growth_details'])) ? 'd-none' : ''; ?>" id="careerDetailsContainer<?php echo $modal_eval_id; ?>">
                                <div class="small text-muted">
                                    <i class="fas fa-info-circle me-1 text-info"></i>
                                    <strong>Details:</strong>
                                    <span class="text-dark fw-semibold ms-1" id="careerDetailsText<?php echo $modal_eval_id; ?>"><?php echo e($row['career_growth_details'] ?? 'N/A'); ?></span>
                                </div>
                            </div>
                        </div>
                        <!-- Edit mode -->
                        <div id="careerEdit<?php echo $modal_eval_id; ?>" class="d-none">
                            <div class="mb-2">
                                <label class="form-label fw-semibold small">Is the employee better suited for another job within the company?</label>
                                <select class="form-select form-select-sm" id="careerSuitedInput<?php echo $modal_eval_id; ?>" onchange="toggleSuitedInputFields(<?php echo $modal_eval_id; ?>)">
                                    <option value="1" <?php echo $cg_suited ? 'selected' : ''; ?>>Yes</option>
                                    <option value="0" <?php echo !$cg_suited ? 'selected' : ''; ?>>No</option>
                                </select>
                            </div>
                            <div id="suitedInputsContainer<?php echo $modal_eval_id; ?>" class="<?php echo !$cg_suited ? 'd-none' : ''; ?>">
                                <div class="mb-2">
                                    <label class="form-label fw-semibold small">Desired Position / Department</label>
                                    <input type="text" class="form-control form-control-sm" id="careerPosition<?php echo $modal_eval_id; ?>" value="<?php echo e($row['desired_position'] ?? ''); ?>" placeholder="Enter desired position...">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label fw-semibold small">Target Date</label>
                                    <input type="date" class="form-control form-control-sm" id="careerDate<?php echo $modal_eval_id; ?>" value="<?php echo e($row['target_date'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Career Growth Details</label>
                                    <textarea class="form-control form-control-sm" id="careerDetails<?php echo $modal_eval_id; ?>" rows="3" placeholder="Enter career growth details..."><?php echo e($row['career_growth_details'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-success btn-sm" onclick="saveCareerGrowth(<?php echo $modal_eval_id; ?>)"><i class="fas fa-save me-1"></i>Save Career Growth</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleCareerEdit(<?php echo $modal_eval_id; ?>)">Cancel</button>
                            </div>
                        </div>
                    </div>

                    <!-- Developmental Plan -->
                    <div class="section-premium-label mb-3 mt-5">
                        <i class="fas fa-seedling"></i> IV. Developmental Plan
                        <button type="button" class="btn btn-sm btn-outline-primary ms-2" id="editDevBtn<?php echo $modal_eval_id; ?>" onclick="toggleDevEdit(<?php echo $modal_eval_id; ?>)"><i class="fas fa-edit me-1"></i>Edit</button>
                    </div>
                    <div class="p-3 bg-light rounded-3 mb-4 border-start border-4 border-success">
                        <!-- View mode -->
                        <div id="devView<?php echo $modal_eval_id; ?>">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle bg-white rounded border mb-0">
                                    <thead class="small text-muted bg-light">
                                        <tr>
                                            <th class="ps-3">Area of Improvement</th>
                                            <th>Support Needed</th>
                                            <th>Time Frame</th>
                                        </tr>
                                    </thead>
                                    <tbody class="small" id="devViewTableBody<?php echo $modal_eval_id; ?>">
                                        <?php
                                        $dev_q = $conn->query("SELECT * FROM evaluation_dev_plans WHERE evaluation_id = $modal_eval_id ORDER BY sort_order");
                                        $has_dev = $dev_q->num_rows > 0;
                                        if ($has_dev):
                                            while ($dp = $dev_q->fetch_assoc()): ?>
                                            <tr>
                                                <td class="ps-3"><?php echo e($dp['improvement_area']); ?></td>
                                                <td><?php echo e($dp['support_needed']); ?></td>
                                                <td><?php echo e($dp['time_frame']); ?></td>
                                            </tr>
                                        <?php endwhile; else: ?>
                                            <tr class="no-dev-row"><td colspan="3" class="text-center text-muted small py-3">No developmental plan recorded.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Edit mode -->
                        <div id="devEdit<?php echo $modal_eval_id; ?>" class="d-none">
                            <div class="table-responsive mb-3">
                                <table class="table table-sm table-hover align-middle bg-white rounded border mb-0" id="devEditTable<?php echo $modal_eval_id; ?>">
                                    <thead class="small text-muted bg-light">
                                        <tr>
                                            <th class="ps-3">Area of Improvement</th>
                                            <th>Support Needed</th>
                                            <th>Time Frame</th>
                                            <th style="width: 50px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="small">
                                        <?php
                                        if ($has_dev) {
                                            $dev_q->data_seek(0);
                                            while ($dp = $dev_q->fetch_assoc()): ?>
                                                <tr class="dev-edit-row">
                                                    <td class="ps-2">
                                                        <input type="text" class="form-control form-control-sm dev-improvement" value="<?php echo e($dp['improvement_area']); ?>" placeholder="e.g. Technical writing skill">
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm dev-support" value="<?php echo e($dp['support_needed']); ?>" placeholder="e.g. Online course or mentoring">
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm dev-timeframe" value="<?php echo e($dp['time_frame']); ?>" placeholder="e.g. Q3 2026">
                                                    </td>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="this.closest('tr').remove()"><i class="fas fa-trash-alt"></i></button>
                                                    </td>
                                                </tr>
                                            <?php endwhile;
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addDevPlanRow(<?php echo $modal_eval_id; ?>)">
                                    <i class="fas fa-plus me-1"></i>Add Row
                                </button>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-success btn-sm" onclick="saveDevPlan(<?php echo $modal_eval_id; ?>)">
                                        <i class="fas fa-save me-1"></i>Save Developmental Plan
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleDevEdit(<?php echo $modal_eval_id; ?>, true)">Cancel</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Section -->
                    <div class="section-premium-label mb-3 mt-5">
                        <i class="fas fa-comments"></i> V. Remarks & Decisions
                    </div>
                    <form method="POST" action="<?php echo e($form_action); ?>">
                        <input type="hidden" name="evaluation_id" value="<?php echo $modal_eval_id; ?>">
                        <div class="mb-4">
                            <label class="form-label fw-bold">Supervisor Comments / Feedback</label>
                            <textarea class="form-control bg-light" name="supervisor_comments" rows="3" placeholder="Required for rejections, optional for endorsements..."></textarea>
                            <div class="form-text x-small text-danger">* Comments are required when rejecting an evaluation.</div>
                        </div>
                        <div class="fixed-action-bar d-flex gap-2 justify-content-end">
                            <?php if ($can_edit_scores): ?>
                                <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-3 fw-bold btn-edit-ratings" onclick="toggleEditRatings(<?php echo $modal_eval_id; ?>)">
                                    <i class="fas fa-edit me-1"></i>Edit Ratings
                                </button>
                                <button type="button" class="btn btn-sm btn-success rounded-pill px-3 fw-bold btn-save-ratings d-none" onclick="saveRatings(<?php echo $modal_eval_id; ?>)">
                                    <i class="fas fa-save me-1"></i>Save Changes
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold btn-cancel-ratings d-none" onclick="toggleEditRatings(<?php echo $modal_eval_id; ?>, true)">
                                    <i class="fas fa-times me-1"></i>Cancel
                                </button>
                            <?php endif; ?>
                            <button type="submit" name="action" value="return" class="btn btn-outline-danger rounded-pill px-4 fw-bold shadow-sm">
                                <i class="fas fa-times me-2"></i>Reject
                            </button>
                            <button type="submit" name="action" value="endorse" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">
                                <i class="fas fa-check-double me-2"></i>Validate & Forward
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>
</div>

<script>
function initializeSupervisorQueueUI(root = document) {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return bootstrap.Tooltip.getOrCreateInstance(tooltipTriggerEl);
    });

    root.querySelectorAll('.score-input').forEach(input => {
        input.addEventListener('input', function() {
            let val = parseFloat(this.value);
            if (val > 4) {
                this.value = "4.00";
            }
        });
    });

    root.querySelectorAll('.pending-group-collapse').forEach((collapseEl) => {
        const collapseId = collapseEl.id;
        if (!collapseId || collapseEl.dataset.queueBound === '1') {
            return;
        }

        collapseEl.dataset.queueBound = '1';
        const toggleRow = document.querySelector(`.pending-group-row[data-bs-target="#${collapseId}"]`);
        const syncExpanded = (expanded) => {
            if (toggleRow) {
                toggleRow.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            }
            collapseEl.querySelectorAll('[data-bs-target="#' + collapseId + '"]').forEach((btn) => {
                btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                const label = btn.querySelector('.group-toggle-label');
                if (label) {
                    label.textContent = expanded ? 'Hide' : 'Show';
                }
            });
        };

        collapseEl.addEventListener('show.bs.collapse', () => syncExpanded(true));
        collapseEl.addEventListener('hide.bs.collapse', () => syncExpanded(false));
    });
}

document.addEventListener("DOMContentLoaded", function() {
    initializeSupervisorQueueUI(document);
    startSupervisorQueueRefresh();
});

function startSupervisorQueueRefresh() {
    const root = document.querySelector('[data-queue-auto-refresh="supervisor"]');
    if (!root || root.dataset.refreshStarted === '1') {
        return;
    }
    root.dataset.refreshStarted = '1';

    let busy        = false;
    let userActive  = false;   // true while supervisor is reading / interacting
    let activityTimer = null;

    // Mark user as active for 30 s after any meaningful interaction
    const markActive = () => {
        userActive = true;
        clearTimeout(activityTimer);
        activityTimer = setTimeout(() => { userActive = false; }, 30000);
    };

    // Pause refresh while user hovers over any evaluation row/card
    document.addEventListener('mouseover', e => {
        if (e.target.closest('[data-eval-id], .pending-eval-card, .table tbody tr')) {
            markActive();
        }
    }, { passive: true });

    // Pause refresh while user is scrolling
    let scrollTimer = null;
    document.addEventListener('scroll', () => {
        markActive();
        clearTimeout(scrollTimer);
        scrollTimer = setTimeout(() => { userActive = false; }, 5000);
    }, { passive: true });

    const refresh = () => {
        // Skip if: page hidden, modal open, busy, or user is actively reading
        if (busy || document.hidden || userActive || document.querySelector('.modal.show')) {
            return;
        }
        busy = true;
        fetch(window.location.href, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            cache: 'no-store'
        })
        .then(response => response.text())
        .then(html => {
            // Only replace if there is no modal open (guard against race)
            if (document.querySelector('.modal.show')) return;
            const doc = new DOMParser().parseFromString(html, 'text/html');
            ['supervisorQueueSummary', 'supervisorQueueList', 'supervisorQueueModals'].forEach(id => {
                const current = document.getElementById(id);
                const next = doc.getElementById(id);
                if (current && next) {
                    current.replaceWith(next);
                }
            });
            initializeSupervisorQueueUI(document);
        })
        .catch(() => {})
        .finally(() => {
            busy = false;
        });
    };

    // Refresh every 60 seconds (instead of 10) — only when supervisor is idle
    setInterval(refresh, 60000);
}

function toggleEditRatings(evalId, cancel = false) {
    const modal = document.querySelector(`#reviewModal${evalId}`);
    if (!modal) return;

    const displays = modal.querySelectorAll('.score-display');
    const inputs = modal.querySelectorAll('.score-input');
    const badgeAudits = modal.querySelectorAll('.badge-audit');
    const editBtn = modal.querySelector('.btn-edit-ratings');
    const saveBtn = modal.querySelector('.btn-save-ratings');
    const cancelBtn = modal.querySelector('.btn-cancel-ratings');
    const actionBtns = modal.querySelectorAll('.fixed-action-bar button:not(.btn-edit-ratings):not(.btn-save-ratings):not(.btn-cancel-ratings)');

    if (!inputs.length) {
        alert('No rating fields are available to edit for this evaluation.');
        return;
    }

    if (cancel) {
        inputs.forEach(input => {
            input.value = input.getAttribute('data-original-val');
            input.classList.remove('is-invalid');
        });
    }

    const isEditing = inputs[0].classList.contains('d-none');

    if (isEditing) {
        displays.forEach(d => d.classList.add('d-none'));
        badgeAudits.forEach(b => b.classList.add('d-none'));
        inputs.forEach(i => i.classList.remove('d-none'));

        if (editBtn) editBtn.classList.add('d-none');
        if (saveBtn) saveBtn.classList.remove('d-none');
        if (cancelBtn) cancelBtn.classList.remove('d-none');
        actionBtns.forEach(btn => btn.classList.add('d-none'));
    } else {
        displays.forEach(d => d.classList.remove('d-none'));
        badgeAudits.forEach(b => b.classList.remove('d-none'));
        inputs.forEach(i => i.classList.add('d-none'));

        if (editBtn) editBtn.classList.remove('d-none');
        if (saveBtn) saveBtn.classList.add('d-none');
        if (cancelBtn) cancelBtn.classList.add('d-none');
        actionBtns.forEach(btn => btn.classList.remove('d-none'));
    }
}

function saveRatings(evalId) {
    const modal = document.querySelector(`#reviewModal${evalId}`);
    if (!modal) return;

    const inputs = modal.querySelectorAll('.score-input');
    const ratings = {};
    let hasError = false;

    inputs.forEach(input => {
        const val = parseFloat(input.value);
        const scoreId = input.getAttribute('data-score-id');
        if (!scoreId || isNaN(val) || val < 1.00 || val > 4.00) {
            hasError = true;
            input.classList.add('is-invalid');
        } else {
            input.classList.remove('is-invalid');
            ratings[scoreId] = val;
        }
    });

    if (hasError || Object.keys(ratings).length === 0) {
        alert('Please enter valid ratings between 1.00 and 4.00.');
        return;
    }

    const saveBtn = modal.querySelector('.btn-save-ratings');
    if (!saveBtn) return;

    const originalBtnText = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';

    const formData = new FormData();
    formData.append('evaluation_id', evalId);
    for (const [key, value] of Object.entries(ratings)) {
        formData.append(`ratings[${key}]`, value);
    }

    fetch('ajax/save-pending-rating.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            sessionStorage.setItem('open_evaluation_modal', evalId);
            alert(data.message);
            location.reload();
        } else {
            alert(data.message || 'An error occurred while saving ratings.');
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalBtnText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An unexpected error occurred.');
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalBtnText;
    });
}

function toggleCareerEdit(evalId) {
    const viewDiv = document.getElementById(`careerView${evalId}`);
    const editDiv = document.getElementById(`careerEdit${evalId}`);
    if (viewDiv && editDiv) {
        viewDiv.classList.toggle('d-none');
        editDiv.classList.toggle('d-none');
    }
}

function toggleSuitedInputFields(evalId) {
    const suitedInput = document.getElementById(`careerSuitedInput${evalId}`);
    const inputsContainer = document.getElementById(`suitedInputsContainer${evalId}`);
    if (suitedInput && inputsContainer) {
        if (suitedInput.value === '1') {
            inputsContainer.classList.remove('d-none');
        } else {
            inputsContainer.classList.add('d-none');
        }
    }
}

function saveCareerGrowth(evalId) {
    const suitedInput = document.getElementById(`careerSuitedInput${evalId}`);
    const positionInput = document.getElementById(`careerPosition${evalId}`);
    const dateInput = document.getElementById(`careerDate${evalId}`);
    const detailsInput = document.getElementById(`careerDetails${evalId}`);

    if (!suitedInput) return;

    const suited = parseInt(suitedInput.value) || 0;
    const position = positionInput ? positionInput.value.trim() : '';
    const date = dateInput ? dateInput.value : '';
    const details = detailsInput ? detailsInput.value.trim() : '';

    const formData = new FormData();
    formData.append('evaluation_id', evalId);
    formData.append('career_growth_suited', suited);
    formData.append('desired_position', position);
    formData.append('target_date', date);
    formData.append('career_growth_details', details);

    const saveBtn = document.querySelector(`#careerEdit${evalId} button.btn-success`);
    const originalText = saveBtn ? saveBtn.innerHTML : '';
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';
    }

    fetch('ajax/save-career-growth.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalText;
        }

        if (data.success) {
            alert(data.message);
            
            // Update view DOM text elements
            const suitedBadge = document.getElementById(`careerSuitedBadge${evalId}`);
            if (suitedBadge) {
                if (suited === 1) {
                    suitedBadge.innerHTML = '&#9745; Yes';
                    suitedBadge.classList.remove('bg-secondary');
                    suitedBadge.classList.add('bg-success');
                } else {
                    suitedBadge.innerHTML = '&#9744; No';
                    suitedBadge.classList.remove('bg-success');
                    suitedBadge.classList.add('bg-secondary');
                }
            }

            const positionText = document.getElementById(`careerPositionText${evalId}`);
            if (positionText) {
                positionText.textContent = position || 'N/A';
            }
            const dateText = document.getElementById(`careerDateText${evalId}`);
            if (dateText) {
                dateText.textContent = date || 'N/A';
            }
            const detailsText = document.getElementById(`careerDetailsText${evalId}`);
            if (detailsText) {
                detailsText.textContent = details || 'N/A';
            }

            // Toggle containers' d-none state based on suited value and content presence
            const positionContainer = document.getElementById(`careerPositionContainer${evalId}`);
            if (positionContainer) {
                if (suited === 1) {
                    positionContainer.classList.remove('d-none');
                } else {
                    positionContainer.classList.add('d-none');
                }
            }

            const dateContainer = document.getElementById(`careerDateContainer${evalId}`);
            if (dateContainer) {
                if (suited === 1 && date) {
                    dateContainer.classList.remove('d-none');
                } else {
                    dateContainer.classList.add('d-none');
                }
            }

            const detailsContainer = document.getElementById(`careerDetailsContainer${evalId}`);
            if (detailsContainer) {
                if (suited === 1 && details) {
                    detailsContainer.classList.remove('d-none');
                } else {
                    detailsContainer.classList.add('d-none');
                }
            }

            // Switch back to view mode
            toggleCareerEdit(evalId);
        } else {
            alert(data.message || 'An error occurred while saving career growth details.');
        }
    })
    .catch(error => {
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalText;
        }
        console.error('Error:', error);
        alert('An unexpected error occurred.');
    });
}

function toggleDevEdit(evalId, cancel = false) {
    const viewDiv = document.getElementById(`devView${evalId}`);
    const editDiv = document.getElementById(`devEdit${evalId}`);
    if (!viewDiv || !editDiv) return;

    if (cancel) {
        const viewRows = viewDiv.querySelectorAll('tbody tr');
        const editTbody = editDiv.querySelector('tbody');
        editTbody.innerHTML = '';
        
        viewRows.forEach(row => {
            if (row.classList.contains('no-dev-row')) return;
            const imp = row.cells[0].textContent;
            const sup = row.cells[1].textContent;
            const time = row.cells[2].textContent;
            
            const newRow = document.createElement('tr');
            newRow.className = 'dev-edit-row';
            newRow.innerHTML = `
                <td class="ps-2">
                    <input type="text" class="form-control form-control-sm dev-improvement" value="${escapeHtml(imp)}" placeholder="e.g. Technical writing skill">
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm dev-support" value="${escapeHtml(sup)}" placeholder="e.g. Online course or mentoring">
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm dev-timeframe" value="${escapeHtml(time)}" placeholder="e.g. Q3 2026">
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="this.closest('tr').remove()"><i class="fas fa-trash-alt"></i></button>
                </td>
            `;
            editTbody.appendChild(newRow);
        });
    }

    viewDiv.classList.toggle('d-none');
    editDiv.classList.toggle('d-none');
}

function escapeHtml(text) {
    if (!text) return '';
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function addDevPlanRow(evalId) {
    const editTbody = document.querySelector(`#devEditTable${evalId} tbody`);
    if (!editTbody) return;
    
    const newRow = document.createElement('tr');
    newRow.className = 'dev-edit-row';
    newRow.innerHTML = `
        <td class="ps-2">
            <input type="text" class="form-control form-control-sm dev-improvement" value="" placeholder="e.g. Technical writing skill">
        </td>
        <td>
            <input type="text" class="form-control form-control-sm dev-support" value="" placeholder="e.g. Online course or mentoring">
        </td>
        <td>
            <input type="text" class="form-control form-control-sm dev-timeframe" value="" placeholder="e.g. Q3 2026">
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="this.closest('tr').remove()"><i class="fas fa-trash-alt"></i></button>
        </td>
    `;
    editTbody.appendChild(newRow);
}

function saveDevPlan(evalId) {
    const editDiv = document.getElementById(`devEdit${evalId}`);
    if (!editDiv) return;
    
    const rows = editDiv.querySelectorAll('.dev-edit-row');
    const plans = [];
    
    rows.forEach((row, index) => {
        const imp = row.querySelector('.dev-improvement').value.trim();
        const sup = row.querySelector('.dev-support').value.trim();
        const time = row.querySelector('.dev-timeframe').value.trim();
        
        if (imp || sup || time) {
            plans.push({
                improvement_area: imp,
                support_needed: sup,
                time_frame: time
            });
        }
    });

    const saveBtn = editDiv.querySelector('button.btn-success');
    const originalText = saveBtn ? saveBtn.innerHTML : '';
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';
    }

    const formData = new FormData();
    formData.append('evaluation_id', evalId);
    plans.forEach((plan, i) => {
        formData.append(`plans[${i}][improvement_area]`, plan.improvement_area);
        formData.append(`plans[${i}][support_needed]`, plan.support_needed);
        formData.append(`plans[${i}][time_frame]`, plan.time_frame);
    });

    fetch('ajax/save-dev-plan.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalText;
        }

        if (data.success) {
            alert(data.message);
            
            const viewTbody = document.getElementById(`devViewTableBody${evalId}`);
            if (viewTbody) {
                viewTbody.innerHTML = '';
                if (plans.length > 0) {
                    plans.forEach(plan => {
                        const r = document.createElement('tr');
                        r.innerHTML = `
                            <td class="ps-3">${escapeHtml(plan.improvement_area)}</td>
                            <td>${escapeHtml(plan.support_needed)}</td>
                            <td>${escapeHtml(plan.time_frame)}</td>
                        `;
                        viewTbody.appendChild(r);
                    });
                } else {
                    viewTbody.innerHTML = '<tr class="no-dev-row"><td colspan="3" class="text-center text-muted small py-3">No developmental plan recorded.</td></tr>';
                }
            }
            
            toggleDevEdit(evalId);
        } else {
            alert(data.message || 'An error occurred while saving developmental plan details.');
        }
    })
    .catch(error => {
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalText;
        }
        console.error('Error:', error);
        alert('An unexpected error occurred.');
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Dynamic Show/Hide button label toggle for grouped pending evaluations
    document.querySelectorAll('.pending-group-collapse').forEach(function(collapseEl) {
        const groupRow = collapseEl.previousElementSibling;
        if (!groupRow) return;
        const btn = groupRow.querySelector('.group-toggle-btn');
        if (!btn) return;

        collapseEl.addEventListener('show.bs.collapse', function() {
            const icon = btn.querySelector('.group-toggle-icon');
            const label = btn.querySelector('.group-toggle-label');
            if (icon) icon.className = 'fas fa-chevron-up group-toggle-icon me-1';
            if (label) label.textContent = 'Hide';
        });

        collapseEl.addEventListener('hide.bs.collapse', function() {
            const icon = btn.querySelector('.group-toggle-icon');
            const label = btn.querySelector('.group-toggle-label');
            if (icon) icon.className = 'fas fa-chevron-down group-toggle-icon me-1';
            if (label) label.textContent = 'Show';
        });
    });

    const openEvalId = sessionStorage.getItem('open_evaluation_modal');
    if (openEvalId) {
        sessionStorage.removeItem('open_evaluation_modal');
        const modalEl = document.getElementById('reviewModal' + openEvalId);
        if (modalEl) {
            setTimeout(() => {
                const modalInstance = new bootstrap.Modal(modalEl);
                modalInstance.show();
            }, 150);
        }
    }
});
</script>


<?php require_once '../includes/footer.php'; ?>
