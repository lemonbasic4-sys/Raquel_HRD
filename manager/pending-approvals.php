<?php
$page_title = 'Pending Approvals';
require_once '../includes/session-check.php';
checkRole(['HR Manager']);
require_once '../includes/functions.php';

// ── Employee Change Request: approve / reject ────────────────────────────────
ensureEmployeeChangeRequests($conn);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ecr_action'], $_POST['ecr_request_id'])) {
    $ecr_id     = (int) $_POST['ecr_request_id'];
    $ecr_action = $_POST['ecr_action'];
    $mgr_notes  = trim($_POST['manager_notes'] ?? '');
    $reviewer   = (int) ($_SESSION['user_id'] ?? 0);

    // Load the request
    $ecr_stmt = $conn->prepare("SELECT ecr.*, CONCAT(e.first_name,' ',e.last_name) AS emp_name, u.full_name AS staff_name, ecr.submitted_by
        FROM employee_change_requests ecr
        LEFT JOIN employees e ON ecr.employee_id = e.employee_id
        LEFT JOIN users u ON ecr.submitted_by = u.user_id
        WHERE ecr.request_id = ? AND ecr.status = 'Pending' LIMIT 1");
    $ecr_stmt->bind_param('i', $ecr_id);
    $ecr_stmt->execute();
    $ecr_row = $ecr_stmt->get_result()->fetch_assoc();
    $ecr_stmt->close();

    if (!$ecr_row) {
        redirectWith(BASE_URL . '/manager/pending-approvals.php?tab=changes', 'danger', 'Change request not found or already processed.');
    }

    if ($ecr_action === 'approve') {
        // Mark approved first so applyEmployeeChangeRequest can verify status
        $upd = $conn->prepare("UPDATE employee_change_requests SET status='Approved', reviewed_by=?, reviewed_at=NOW(), manager_notes=? WHERE request_id=?");
        $upd->bind_param('isi', $reviewer, $mgr_notes, $ecr_id);
        $upd->execute(); $upd->close();

        // Apply the diff to the live employee record
        applyEmployeeChangeRequest($conn, $ecr_id);

        // Notify HR Staff submitter
        createNotification($conn, (int)$ecr_row['submitted_by'],
            'Edit Request Approved',
            "Your change request for {$ecr_row['emp_name']} has been approved by the HR Manager." . ($mgr_notes ? ' Notes: ' . $mgr_notes : ''),
            BASE_URL . '/staff/employees.php'
        );
        logAudit($conn, $reviewer, 'APPROVE', 'EmployeeChangeRequest', $ecr_id, "Approved change request for {$ecr_row['emp_name']}");
        redirectWith(BASE_URL . '/manager/pending-approvals.php?tab=changes', 'success', "Changes for {$ecr_row['emp_name']} approved and applied.");

    } elseif ($ecr_action === 'reject') {
        $upd = $conn->prepare("UPDATE employee_change_requests SET status='Rejected', reviewed_by=?, reviewed_at=NOW(), manager_notes=? WHERE request_id=?");
        $upd->bind_param('isi', $reviewer, $mgr_notes, $ecr_id);
        $upd->execute(); $upd->close();

        createNotification($conn, (int)$ecr_row['submitted_by'],
            'Edit Request Rejected',
            "Your change request for {$ecr_row['emp_name']} was rejected." . ($mgr_notes ? ' Reason: ' . $mgr_notes : ''),
            BASE_URL . '/staff/employees.php'
        );
        logAudit($conn, $reviewer, 'REJECT', 'EmployeeChangeRequest', $ecr_id, "Rejected change request for {$ecr_row['emp_name']}");
        redirectWith(BASE_URL . '/manager/pending-approvals.php?tab=changes', 'warning', "Change request for {$ecr_row['emp_name']} rejected.");
    }
}
// ── End Employee Change Request handler ─────────────────────────────────────

// Handle approval/rejection (MUST be before header.php to allow redirect)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $eval_id = (int) $_POST['evaluation_id'];
    $action = $_POST['action'];
    $comments = trim($_POST['manager_comments'] ?? '');

    $manager_id = (int) ($_SESSION['user_id'] ?? 0);
    $manager_id_nullable = null;
    if ($manager_id > 0) {
        $uid_check = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? LIMIT 1");
        $uid_check->bind_param("i", $manager_id);
        $uid_check->execute();
        $uid_exists = (bool) $uid_check->get_result()->fetch_assoc();
        $uid_check->close();
        if ($uid_exists) {
            $manager_id_nullable = $manager_id;
        }
    }

    if ($action === 'approve') {
        // 1. Update status to Approved — guard: must be in Pending Manager status
        $stmt = $conn->prepare("UPDATE evaluations SET status = 'Approved', approved_by = ?, manager_comments = ? WHERE evaluation_id = ? AND status = 'Pending Manager'");
        $stmt->bind_param("isi", $manager_id_nullable, $comments, $eval_id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected === 0) {
            redirectWith(BASE_URL . '/manager/pending-approvals.php', 'danger', 'Approval failed: this evaluation is not in Pending Manager status or was already processed.');
        }

        // Recalculate evaluation scores to ensure manager overrides are reflected in total scores
        recalculateEvaluationScores($conn, $eval_id);

        // 2. Fetch evaluation details for notifications
        $eval_info_q = $conn->prepare("SELECT ev.*, CONCAT(e.first_name, ' ', e.last_name) as emp_name, e.branch_id as emp_branch_id, u_emp.user_id as emp_user_id, et.template_name
                                     FROM evaluations ev 
                                     LEFT JOIN employees e ON ev.employee_id = e.employee_id 
                                     LEFT JOIN users u_emp ON u_emp.employee_id = e.employee_id AND u_emp.role = 'Employee'
                                     LEFT JOIN evaluation_templates et ON ev.template_id = et.template_id
                                     WHERE ev.evaluation_id = ?");
        $eval_info_q->bind_param("i", $eval_id);
        $eval_info_q->execute();
        $eval_info = $eval_info_q->get_result()->fetch_assoc();
        $eval_info_q->close();

        // 3. Send notifications
        $app_msg = "Your evaluation for {$eval_info['template_name']} has been approved by the HR Manager.";
        if (!empty($comments)) {
            $app_msg .= " Remarks: " . $comments;
        }

        if ($eval_info['submitted_by']) {
            createNotification($conn, $eval_info['submitted_by'], 'Evaluation Approved', "Evaluation for {$eval_info['emp_name']} has been approved.", BASE_URL . '/staff/evaluation-history.php');
        }
        if ($eval_info['endorsed_by']) {
            createNotification($conn, $eval_info['endorsed_by'], 'Evaluation Approved', "Evaluation for {$eval_info['emp_name']} has been approved by the HR Manager.", BASE_URL . '/supervisor/evaluation-history.php');
        }
        // 4. Notify the employee being evaluated
        if (!empty($eval_info['emp_user_id'])) {
            createNotification($conn, $eval_info['emp_user_id'], 'Evaluation Approved', $app_msg, BASE_URL . '/employee/evaluation-history-view.php?id=' . $eval_id);
        }
        logAudit($conn, $_SESSION['user_id'], 'UPDATE', 'Evaluation', $eval_id, "Approved evaluation for {$eval_info['emp_name']}");
        redirectWith(BASE_URL . '/manager/pending-approvals.php', 'success', 'Evaluation approved successfully.');

    } elseif ($action === 'reject') {
        if (empty($comments)) {
            redirectWith(BASE_URL . '/manager/pending-approvals.php', 'danger', 'Comments are required when rejecting an evaluation.');
        }
        // Guard: must be in Pending Manager status
        $stmt = $conn->prepare("UPDATE evaluations SET status = 'Rejected', approved_by = ?, manager_comments = ? WHERE evaluation_id = ? AND status = 'Pending Manager'");
        $stmt->bind_param("isi", $manager_id_nullable, $comments, $eval_id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected === 0) {
            redirectWith(BASE_URL . '/manager/pending-approvals.php', 'danger', 'Rejection failed: this evaluation is not in Pending Manager status or was already processed.');
        }

        // Recalculate evaluation scores
        recalculateEvaluationScores($conn, $eval_id);

        // Fetch evaluation details for notifications
        $eval_info_q = $conn->prepare("SELECT ev.*, CONCAT(e.first_name, ' ', e.last_name) as emp_name, u_emp.user_id as emp_user_id, et.template_name
                                     FROM evaluations ev 
                                     LEFT JOIN employees e ON ev.employee_id = e.employee_id 
                                     LEFT JOIN users u_emp ON u_emp.employee_id = e.employee_id AND u_emp.role = 'Employee'
                                     LEFT JOIN evaluation_templates et ON ev.template_id = et.template_id
                                     WHERE ev.evaluation_id = ?");
        $eval_info_q->bind_param("i", $eval_id);
        $eval_info_q->execute();
        $eval_info = $eval_info_q->get_result()->fetch_assoc();
        $eval_info_q->close();

        $rej_msg = "Your evaluation for {$eval_info['template_name']} has been rejected by the HR Manager. Reason: " . $comments;

        if ($eval_info['submitted_by']) {
            createNotification($conn, $eval_info['submitted_by'], 'Evaluation Rejected', "Your evaluation for {$eval_info['emp_name']} has been rejected.", BASE_URL . '/staff/evaluation-history.php');
        }
        if ($eval_info['endorsed_by']) {
            createNotification($conn, $eval_info['endorsed_by'], 'Evaluation Rejected', "Evaluation for {$eval_info['emp_name']} has been rejected by the HR Manager.", BASE_URL . '/supervisor/evaluation-history.php');
        }
        // Notify the employee being evaluated
        if (!empty($eval_info['emp_user_id'])) {
            createNotification($conn, $eval_info['emp_user_id'], 'Evaluation Rejected', $rej_msg, BASE_URL . '/employee/evaluation-history-view.php?id=' . $eval_id);
        }

        logAudit($conn, $_SESSION['user_id'], 'UPDATE', 'Evaluation', $eval_id, "Rejected evaluation for {$eval_info['emp_name']}");
        redirectWith(BASE_URL . '/manager/pending-approvals.php', 'warning', 'Evaluation rejected.');

    } elseif ($action === 'revision') {
        if (empty($comments)) {
            redirectWith(BASE_URL . '/manager/pending-approvals.php', 'danger', 'Comments are required when requesting revision.');
        }
        // Guard: must be in Pending Manager status
        $stmt = $conn->prepare("UPDATE evaluations SET status = 'Returned', manager_comments = ? WHERE evaluation_id = ? AND status = 'Pending Manager'");
        $stmt->bind_param("si", $comments, $eval_id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected === 0) {
            redirectWith(BASE_URL . '/manager/pending-approvals.php', 'danger', 'Revision request failed: this evaluation is not in Pending Manager status or was already processed.');
        }

        // Recalculate evaluation scores
        recalculateEvaluationScores($conn, $eval_id);

        // Get details of the evaluation
        $eval_info_q = $conn->prepare("SELECT ev.*, CONCAT(e.first_name, ' ', e.last_name) as emp_name, u_emp.user_id as emp_user_id, et.template_name, u.role as submitter_role
                                     FROM evaluations ev 
                                     LEFT JOIN employees e ON ev.employee_id = e.employee_id 
                                     LEFT JOIN users u_emp ON u_emp.employee_id = e.employee_id AND u_emp.role = 'Employee'
                                     LEFT JOIN evaluation_templates et ON ev.template_id = et.template_id
                                     LEFT JOIN users u ON ev.submitted_by = u.user_id 
                                     WHERE ev.evaluation_id = ?");
        $eval_info_q->bind_param("i", $eval_id);
        $eval_info_q->execute();
        $eval_info = $eval_info_q->get_result()->fetch_assoc();
        $eval_info_q->close();

        $rev_msg = "Your evaluation for {$eval_info['template_name']} has been returned for revision by the HR Manager. Remarks: " . $comments;

        if ($eval_info['submitted_by']) {
            if ($eval_info['submitter_role'] === 'Employee') {
                createNotification($conn, $eval_info['submitted_by'], 'Revision Requested', $rev_msg, BASE_URL . '/employee/self-rating.php?edit=' . $eval_id);
            } else {
                createNotification($conn, $eval_info['submitted_by'], 'Revision Requested', "Your evaluation for {$eval_info['emp_name']} needs revision.", BASE_URL . '/staff/evaluation-history.php');
            }
        }

        if ($eval_info['endorsed_by']) {
            createNotification($conn, $eval_info['endorsed_by'], 'Revision Requested', "The evaluation for {$eval_info['emp_name']} has been returned by the HR Manager for revision.", BASE_URL . '/supervisor/evaluation-history.php');
        }

        // Notify the employee being evaluated (if not already notified as the submitter)
        if (!empty($eval_info['emp_user_id']) && ($eval_info['emp_user_id'] != $eval_info['submitted_by'] || $eval_info['submitter_role'] !== 'Employee')) {
            createNotification($conn, $eval_info['emp_user_id'], 'Revision Requested', $rev_msg, BASE_URL . '/employee/self-rating.php?edit=' . $eval_id);
        }

        logAudit($conn, $_SESSION['user_id'], 'UPDATE', 'Evaluation', $eval_id, "Requested revision for evaluation of {$eval_info['emp_name']}");
        redirectWith(BASE_URL . '/manager/pending-approvals.php', 'info', 'Revision requested.');
    }
}

require_once '../includes/header.php';

// Fetch options for dropdown filters
$branch_options = [];
$res = $conn->query("SELECT DISTINCT b.branch_id, b.branch_name
     FROM evaluations ev
     INNER JOIN employees e ON ev.employee_id = e.employee_id
     INNER JOIN branches b ON e.branch_id = b.branch_id
     WHERE ev.status = 'Pending Manager' AND e.is_active = 1
     ORDER BY b.branch_name");
while ($r = $res->fetch_assoc()) { $branch_options[] = $r; }

$department_options = [];
$res = $conn->query("SELECT DISTINCT d.department_id, d.department_name
     FROM evaluations ev
     INNER JOIN employees e ON ev.employee_id = e.employee_id
     INNER JOIN departments d ON e.department_id = d.department_id
     WHERE ev.status = 'Pending Manager' AND e.is_active = 1 AND d.is_active = 1
     ORDER BY d.department_name");
while ($r = $res->fetch_assoc()) { $department_options[] = $r; }

$template_options = [];
$res = $conn->query("SELECT DISTINCT et.template_id, et.template_name
     FROM evaluations ev
     INNER JOIN employees e ON ev.employee_id = e.employee_id
     INNER JOIN evaluation_templates et ON ev.template_id = et.template_id
     WHERE ev.status = 'Pending Manager' AND e.is_active = 1
     ORDER BY et.template_name");
while ($r = $res->fetch_assoc()) { $template_options[] = $r; }

$staff_options = [];
$res = $conn->query("SELECT DISTINCT u.user_id, u.full_name
     FROM evaluations ev
     INNER JOIN employees e ON ev.employee_id = e.employee_id
     INNER JOIN users u ON ev.submitted_by = u.user_id
     WHERE ev.status = 'Pending Manager' AND e.is_active = 1
     ORDER BY u.full_name");
while ($r = $res->fetch_assoc()) { $staff_options[] = $r; }

$all_eval_staff_options = [];
$res2 = $conn->query("SELECT DISTINCT u.user_id, u.full_name
     FROM evaluations ev
     INNER JOIN employees e ON ev.employee_id = e.employee_id
     INNER JOIN users u ON ev.submitted_by = u.user_id
     WHERE e.is_active = 1
     ORDER BY u.full_name");
while ($r = $res2->fetch_assoc()) { $all_eval_staff_options[] = $r; }

// Filter parameters mapping
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
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');
$score_min = (isset($_GET['score_min']) && $_GET['score_min'] !== '') ? max(0, min(4, (float)$_GET['score_min'])) : null;
$score_max = (isset($_GET['score_max']) && $_GET['score_max'] !== '') ? max(0, min(4, (float)$_GET['score_max'])) : null;

// Construct SQL filters
$pendingWhere = "WHERE ev.status = 'Pending Manager' AND e.is_active = 1";
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
        OR e.employee_code LIKE ?
        OR e.job_title LIKE ?
        OR et.template_name LIKE ?
    )";
    $pendingTypes .= "ssss";
    array_push($pendingParams, $like, $like, $like, $like);
}

// Fetch counts for summary stats
$branch_pending_count_res = $conn->query("SELECT COUNT(*) as c FROM evaluations ev INNER JOIN employees e ON ev.employee_id = e.employee_id WHERE ev.status = 'Pending Manager' AND e.is_active = 1");
$branch_pending_count = $branch_pending_count_res ? (int)$branch_pending_count_res->fetch_assoc()['c'] : 0;

$overdue_count_res = $conn->query("SELECT COUNT(*) as c FROM evaluations ev INNER JOIN employees e ON ev.employee_id = e.employee_id WHERE ev.status = 'Pending Manager' AND e.is_active = 1 AND COALESCE(DATEDIFF(CURRENT_DATE(), DATE(ev.submitted_date)), 0) >= 7");
$overdue_count = $overdue_count_res ? (int)$overdue_count_res->fetch_assoc()['c'] : 0;

$low_score_count_res = $conn->query("SELECT COUNT(*) as c FROM evaluations ev INNER JOIN employees e ON ev.employee_id = e.employee_id WHERE ev.status = 'Pending Manager' AND e.is_active = 1 AND ((ev.total_score IS NOT NULL AND ev.total_score < 2) OR ev.performance_level = 'Needs Improvement')");
$low_score_count = $low_score_count_res ? (int)$low_score_count_res->fetch_assoc()['c'] : 0;

$finalized_count_q = $conn->prepare("SELECT COUNT(*) as cnt FROM evaluations WHERE approved_by = ? AND status IN ('Approved', 'Rejected')");
$finalized_count_q->bind_param("i", $_SESSION['user_id']);
$finalized_count_q->execute();
$finalized_count = $finalized_count_q->get_result()->fetch_assoc()['cnt'];
$finalized_count_q->close();

// Fetch evaluations main query
$sql = "SELECT ev.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.job_title, e.profile_picture,
    u.full_name as submitted_by_name, et.template_name, et.kra_weight, et.behavior_weight,
    COALESCE(DATEDIFF(CURRENT_DATE(), DATE(ev.submitted_date)), 0) AS days_pending,
    b.branch_name
    FROM evaluations ev
    LEFT JOIN employees e ON ev.employee_id = e.employee_id
    LEFT JOIN branches b ON e.branch_id = b.branch_id
    LEFT JOIN users u ON ev.submitted_by = u.user_id
    LEFT JOIN evaluation_templates et ON ev.template_id = et.template_id
    $pendingWhere
    ORDER BY ev.submitted_date DESC";

if ($pendingTypes !== '') {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($pendingTypes, ...$pendingParams);
    $stmt->execute();
    $pending = $stmt->get_result();
    $stmt->close();
} else {
    $pending = $conn->query($sql);
}

$pending_count = $pending->num_rows;
$all_pending = [];
while ($row = $pending->fetch_assoc()) {
    $all_pending[] = $row;
}

$active_filter_count = 0;
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
foreach ($filter_params as $value) {
    if ($value !== '' && $value !== null) {
        $active_filter_count++;
    }
}
$filter_query = http_build_query(array_filter($filter_params));

// ── Fetch pending employee change requests ───────────────────────────────────
$active_tab = $_GET['tab'] ?? 'evaluations';
$ecr_pending = $conn->query("
    SELECT ecr.*, CONCAT(e.first_name,' ',e.last_name) AS emp_name,
           e.job_title, e.profile_picture,
           b.branch_name, d.department_name,
           u.full_name AS staff_name
    FROM employee_change_requests ecr
    LEFT JOIN employees e ON ecr.employee_id = e.employee_id
    LEFT JOIN branches b ON e.branch_id = b.branch_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN users u ON ecr.submitted_by = u.user_id
    WHERE ecr.status = 'Pending'
    ORDER BY ecr.created_at DESC
");
$ecr_pending_count = $ecr_pending ? (int)$ecr_pending->num_rows : 0;
$ecr_all_pending = [];
if ($ecr_pending) { while ($r = $ecr_pending->fetch_assoc()) $ecr_all_pending[] = $r; }
// ── End change request fetch ─────────────────────────────────────────────────
?>

<div class="page-hero fadeup">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
        <div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">HR Manager · Approvals</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-check-double me-2" style="color:#BD9414;"></i>Pending Approvals</h4>
            <p class="text-white-50 small mb-0 mt-2">Review pending employee changes and performance evaluations, then approve, return, or reject them with confidence.</p>
        </div>
        <div class="d-flex flex-column align-items-end gap-2">
            <div style="color:rgba(255,255,255,.6);font-size:.8rem;">
                <i class="fas fa-sync-alt me-1"></i>Data as of <?php echo date('F d, Y'); ?>
            </div>
            <!-- Quick Actions -->
            <div class="d-flex gap-2 flex-wrap justify-content-end">
                <a href="evaluation-history.php" class="btn btn-sm px-3 fw-semibold" style="background:rgba(255,255,255,.1);color:rgba(255,255,255,.8);border:1px solid rgba(255,255,255,.15);border-radius:20px;font-size:.78rem;">
                    <i class="fas fa-history me-1"></i>View History
                </a>
            </div>
        </div>
    </div>

    <div class="row g-3" id="managerApprovalSummary">
        <!-- Pending Actions -->
        <div class="col-6 col-md-3">
            <a href="pending-approvals.php" class="stat-card text-decoration-none d-block">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo $branch_pending_count; ?></div>
                        <div class="stat-label">Pending Actions</div>
                    </div>
                    <i class="fas fa-hourglass-half stat-icon" style="color:#ffc107;"></i>
                </div>
                <div class="mt-2" style="font-size:.72rem;color:rgba(255,255,255,.55);">
                    <i class="fas fa-filter me-1"></i><?php echo $pending_count; ?> filtered in view
                </div>
            </a>
        </div>
        <!-- Overdue -->
        <div class="col-6 col-md-3">
            <a href="pending-approvals.php?attention=overdue" class="stat-card text-decoration-none d-block">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value" style="<?php echo $overdue_count > 0 ? 'color:#ff6b6b;' : ''; ?>"><?php echo $overdue_count; ?></div>
                        <div class="stat-label">Overdue (7+ Days)</div>
                    </div>
                    <i class="fas fa-hourglass-end stat-icon" style="color:#dc3545;"></i>
                </div>
                <div class="mt-2" style="font-size:.72rem;color:rgba(255,255,255,.55);">
                    <?php if ($overdue_count > 0): ?>
                        <i class="fas fa-exclamation-circle me-1"></i>Needs prompt attention
                    <?php else: ?>
                        <i class="fas fa-check me-1"></i>No overdue items
                    <?php endif; ?>
                </div>
            </a>
        </div>
        <!-- Low Score -->
        <div class="col-6 col-md-3">
            <a href="pending-approvals.php?attention=low_score" class="stat-card text-decoration-none d-block">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value" style="<?php echo $low_score_count > 0 ? 'color:#ff6b6b;' : ''; ?>"><?php echo $low_score_count; ?></div>
                        <div class="stat-label">Low Score (&lt; 2.0)</div>
                    </div>
                    <i class="fas fa-arrow-trend-down stat-icon" style="color:#fd7e14;"></i>
                </div>
                <div class="mt-2" style="font-size:.72rem;color:rgba(255,255,255,.55);">
                    <i class="fas fa-triangle-exclamation me-1"></i>Requires audit/override check
                </div>
            </a>
        </div>
        <!-- Finalized -->
        <div class="col-6 col-md-3">
            <a href="evaluation-history.php" class="stat-card text-decoration-none d-block">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo $finalized_count; ?></div>
                        <div class="stat-label">Finalized</div>
                    </div>
                    <i class="fas fa-check-circle stat-icon" style="color:#28a745;"></i>
                </div>
                <div class="mt-2" style="font-size:.72rem;color:rgba(255,255,255,.55);">
                    <i class="fas fa-eye me-1"></i>Click to view history
                </div>
            </a>
        </div>
    </div>
</div>

<!-- ── Section Tab Switcher ──────────────────────────────────────────── -->
<div class="d-flex gap-2 mb-3 fadeup" style="border-bottom:2px solid #e8ecf1;padding-bottom:0;">
    <a href="#" data-pa-tab="evaluations"
       class="btn btn-sm fw-semibold rounded-0 border-0 border-bottom pb-2 px-3"
       style="margin-bottom:-2px;border-bottom-width:2px !important;">
        <i class="fas fa-check-double me-1"></i>Evaluation Approvals
        <?php if ($branch_pending_count > 0): ?>
            <span class="badge rounded-pill bg-primary ms-1"><?php echo $branch_pending_count > 9 ? '9+' : $branch_pending_count; ?></span>
        <?php endif; ?>
    </a>
    <a href="#" data-pa-tab="changes"
       class="btn btn-sm fw-semibold rounded-0 border-0 border-bottom pb-2 px-3"
       style="margin-bottom:-2px;border-bottom-width:2px !important;">
        <i class="fas fa-user-edit me-1"></i>Employee Edit Requests
        <?php if ($ecr_pending_count > 0): ?>
            <span class="badge rounded-pill bg-warning text-dark ms-1"><?php echo $ecr_pending_count > 9 ? '9+' : $ecr_pending_count; ?></span>
        <?php endif; ?>
    </a>
</div>

<!-- ── PANE: Evaluations ─────────────────────────────────────────────── -->
<div data-pa-pane data-pa-pane-id="evaluations">

<!-- Modern Filter System -->
<div class="pending-filter-card fadeup fadeup-1" style="background:#fff; border:1px solid #eef2e8; border-radius:14px; box-shadow:0 8px 22px rgba(12,32,8,0.06); margin-bottom:18px; padding:16px;">
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
                            <?php if (!empty($staff_options)): ?>
                                <optgroup label="Pending Approvals">
                                    <?php foreach ($staff_options as $staff): ?>
                                        <option value="<?php echo (int) $staff['user_id']; ?>" <?php echo $filter_staff === (int) $staff['user_id'] ? 'selected' : ''; ?>>
                                            <?php echo e($staff['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                            <?php
                            // Only show All Evaluations group if it has extra submitters not in pending
                            $pending_ids = array_column($staff_options, 'user_id');
                            $extra_staff = array_filter($all_eval_staff_options, fn($s) => !in_array($s['user_id'], $pending_ids));
                            ?>
                            <?php if (!empty($extra_staff)): ?>
                                <optgroup label="All Evaluations">
                                    <?php foreach ($extra_staff as $staff): ?>
                                        <option value="<?php echo (int) $staff['user_id']; ?>" <?php echo $filter_staff === (int) $staff['user_id'] ? 'selected' : ''; ?>>
                                            <?php echo e($staff['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
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
                <a href="pending-approvals.php" class="btn btn-sm btn-outline-secondary px-3">
                    <i class="fas fa-rotate-left me-1"></i>Reset
                </a>
                <button type="submit" class="btn btn-sm btn-primary px-4">
                    <i class="fas fa-filter me-1"></i>Apply Filters
                </button>
            </div>
        </div>
    </form>
</div>


<style>
    .approval-center-tabs {
        background: #fff;
        border-radius: 12px 12px 0 0;
        border: 1px solid #f0f0f0;
        border-bottom: none;
        padding: 5px 15px 0;
    }
    .approval-center-tabs .nav-link {
        border: none;
        padding: 15px 25px;
        font-weight: 600;
        color: var(--text-muted);
        position: relative;
        transition: all 0.3s;
    }
    .approval-center-tabs .nav-link.active {
        color: var(--primary-blue) !important;
        background: transparent !important;
    }
    .approval-center-tabs .nav-link.active::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 20px;
        right: 20px;
        height: 4px;
        background: var(--primary-blue);
        border-radius: 10px;
    }
    .approval-card-list {
        background: #fff;
        border-radius: 0 0 12px 12px;
        border: 1px solid #f0f0f0;
        min-height: 400px;
    }
    .modern-table thead th {
        background: rgba(41, 67, 6, 0.03);
        color: var(--primary-blue);
        font-weight: 700;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        border: none;
        padding: 15px 20px;
    }
    .modern-table tbody td {
        padding: 18px 20px;
        border-bottom: 1px solid #f8f9fa;
    }
    .emp-avatar {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: #fff;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.9rem;
        overflow: hidden;
        border: 1px solid #eef2e8;
        box-shadow: 0 2px 8px rgba(12, 32, 8, 0.08);
    }
    .emp-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
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
    
    /* TOTAL ROWS DESIGN UPGRADE FOR REVIEW CONSOLE */
    .total-row td {
        background: #fef3c7 !important;
        font-weight: 800 !important;
        border-top: 2px solid #fbbf24 !important;
        border-bottom: 3px double #fbbf24 !important;
        color: #92400e !important;
        font-size: 0.88rem !important;
    }
</style>

<div class="row mb-5" id="managerApprovalList" data-queue-auto-refresh="manager">
    <div class="col-12">
        <div class="approval-center-tabs d-flex justify-content-between align-items-center">
            <ul class="nav nav-tabs border-0" id="approvalTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" id="evaluations-tab" data-bs-toggle="tab" data-bs-target="#evaluations-pane" type="button" role="tab">
                        <i class="fas fa-file-signature me-2"></i>Evaluations
                        <span class="badge rounded-pill bg-primary ms-1"><?php echo $pending_count; ?></span>
                    </button>
                </li>
            </ul>
            <div class="search-box me-3">
                <i class="fas fa-search search-icon"></i>
                <input type="text" class="form-control form-control-sm border-0 bg-light" id="unifiedSearch" placeholder="Search approvals...">
            </div>
        </div>

        <div class="tab-content approval-card-list shadow-sm" id="approvalTabsContent">
            <!-- Evaluations Tab -->
            <div class="tab-pane fade show active" id="evaluations-pane" role="tabpanel">
                <div class="table-responsive">
                    <table class="table modern-table align-middle mb-0" id="evalTable">
                        <thead>
                            <tr>
                                <th class="ps-3">Employee</th>
                                <th>Department</th>
                                <th>Submitted By</th>
                                <th>Submitted</th>
                                <th>Type & Progress</th>
                                <th>Score & Alerts</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($all_pending)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <i class="fas fa-check-circle fa-3x text-light mb-3"></i>
                                        <p class="text-muted">No pending evaluations for review.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($all_pending as $row): 
                                    $avatar_url = getEmployeeAvatar($row['profile_picture'] ?? '');
                                    $score = (float)$row['total_score'];
                                    $has_score = $score > 0;
                                    $score_width = max(0, min(100, ($score / 4) * 100));
                                    $perf_level = $row['performance_level'] ?? '';
                                    if ($has_score && (empty($perf_level) || $perf_level === '0')) {
                                        $perf_level = getPerformanceLevel($score);
                                    }
                                    $badge_class = getPerformanceBadgeClass($perf_level);
                                    $days_pending = (int)(isset($row['submitted_date']) && $row['submitted_date']
                                        ? max(0, floor((time() - strtotime($row['submitted_date'])) / 86400))
                                        : 0);
                                    $is_overdue = $days_pending >= 7;
                                    $is_low_score = $has_score && $score < 2.0;
                                    $age_label = $days_pending === 0 ? 'Today' : $days_pending . ' day' . ($days_pending === 1 ? '' : 's');
                                    $age_class = $is_overdue ? 'bg-warning-subtle text-warning border border-warning-subtle' : 'bg-primary-subtle text-primary border border-primary-subtle';
                                    $score_label = $has_score ? number_format($score, 2) . ' / 4' : 'No score';
                                ?>
                                    <tr>
                                        <td class="ps-3">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="emp-avatar">
                                                    <img src="<?php echo e($avatar_url); ?>?v=<?php echo time(); ?>" alt="<?php echo e($row['employee_name']); ?> profile picture">
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark"><?php echo e($row['employee_name']); ?></div>
                                                    <small class="text-muted"><?php echo e($row['template_name']); ?></small>
                                                    <?php if ($is_overdue || $is_low_score): ?>
                                                        <div class="d-flex flex-wrap gap-1 mt-1">
                                                            <?php if ($is_overdue): ?><span class="badge bg-warning-subtle text-warning border border-warning-subtle" style="font-size:.68rem;">Overdue</span><?php endif; ?>
                                                            <?php if ($is_low_score): ?><span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size:.68rem;">Low Score</span><?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?php echo e($row['job_title'] ?? 'N/A'); ?></div>
                                            <small class="text-muted"><?php echo e($row['department_name'] ?? 'Unassigned'); ?></small>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['evaluator_comments']) || !empty($row['endorsed_by'])): ?>
                                                <div class="small fw-semibold text-success"><i class="fas fa-check-circle me-1"></i><?php echo e($row['submitted_by_name']); ?></div>
                                                <div class="small text-muted">HR Supervisor Endorsed</div>
                                            <?php else: ?>
                                                <div class="small fw-semibold"><?php echo e($row['submitted_by_name']); ?></div>
                                                <div class="small text-muted">Via HR Consolidation</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $age_class; ?>" style="font-size:.75rem;"><i class="fas fa-clock me-1"></i><?php echo e($age_label); ?></span>
                                            <div><small class="text-muted"><?php echo formatDate($row['submitted_date']); ?></small></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info-subtle text-info border border-info-subtle"><?php echo e($row['evaluation_type'] ?? 'Annual'); ?></span>
                                            <div class="small text-success mt-1"><i class="fas fa-check me-1"></i>HR Supervisor endorsed &rarr; <strong>Pending Approval</strong></div>
                                        </td>
                                        <td>
                                            <div class="d-flex justify-content-between align-items-center gap-2">
                                                <span class="fw-bold"><?php echo e($score_label); ?></span>
                                                <span class="badge <?php echo $badge_class; ?> rounded-pill px-2" style="font-size:.68rem;"><?php echo e($perf_level ?: ($has_score ? 'Unrated' : 'Unscored')); ?></span>
                                            </div>
                                            <?php if ($has_score): ?>
                                                <div class="progress mt-2" style="height: 5px;">
                                                    <div class="progress-bar <?php echo $is_low_score ? 'bg-danger' : 'bg-primary'; ?>" style="width: <?php echo $score_width; ?>%;"></div>
                                                </div>
                                            <?php else: ?>
                                                <div class="small text-muted mt-1">Score not calculated yet.</div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-primary px-3 rounded-pill shadow-sm" 
                                                    data-bs-toggle="modal" data-bs-target="#reviewModal<?php echo $row['evaluation_id']; ?>">
                                                <i class="fas fa-clipboard-check me-1"></i>Review
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="managerApprovalModals">
<?php 
// Render Modals at the end of the file
foreach ($all_pending as $row): 
    $initials = strtoupper(substr($row['employee_name'], 0, 1) . substr(explode(' ', $row['employee_name'])[1] ?? '', 0, 1));
    $modal_avatar_url = getEmployeeAvatar($row['profile_picture'] ?? '');
?>
    <div class="modal fade modal-premium" id="reviewModal<?php echo $row['evaluation_id']; ?>" tabindex="-1" aria-hidden="true" data-kra-weight="<?php echo (float)($row['kra_weight'] ?? 80); ?>" data-behavior-weight="<?php echo (float)($row['behavior_weight'] ?? 20); ?>">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-1">Performance Review</h5>
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
                            ['l' => 'Supervisor', 'a' => true, 'i' => 'fa-user-tie'],
                            ['l' => 'Review', 'a' => true, 'i' => 'fa-user-shield', 'c' => true],
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

                    <?php $can_edit_scores = ($row['status'] ?? '') === 'Pending Manager'; ?>
                    

                    <div class="eval-summary-header">
                        <div class="d-flex align-items-center gap-3">
                            <div class="emp-avatar" style="width: 55px; height: 55px; font-size: 1.2rem;">
                                <img src="<?php echo e($modal_avatar_url); ?>?v=<?php echo time(); ?>" alt="<?php echo e($row['employee_name']); ?> profile picture">
                            </div>
                            <div>
                                <h4 class="mb-0 fw-bold"><?php echo e($row['employee_name']); ?></h4>
                                <div class="text-muted"><?php echo e($row['job_title']); ?> &bull; <?php echo e($row['template_name']); ?></div>
                            </div>
                        </div>
                        <?php echo getEvaluationScoreCirclesHtml($conn, $row['evaluation_id'], $row['total_score']); ?>
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
                    $modal_audit_eval_id = (int)$row['evaluation_id'];
                    $modal_audit_stmt->bind_param("i", $modal_audit_eval_id);
                    $modal_audit_stmt->execute();
                    $modal_audit_logs = $modal_audit_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $modal_audit_stmt->close();
                    ?>
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold mb-2"
                            data-bs-toggle="collapse"
                            data-bs-target="#auditTrail<?php echo (int)$row['evaluation_id']; ?>"
                            aria-expanded="false">
                        <i class="fas fa-history me-1"></i>Audit Trail
                    </button>
                    <div class="collapse mb-3" id="auditTrail<?php echo (int)$row['evaluation_id']; ?>">
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
                        <i class="fas fa-bullseye"></i> I. Strategic Programs & Requirements
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
                                $kra_q = $conn->query("SELECT es.*, ec.criterion_name, ec.description, ec.weight FROM evaluation_scores es JOIN evaluation_criteria ec ON es.criterion_id = ec.criterion_id WHERE es.evaluation_id = {$row['evaluation_id']} AND ec.section = 'KRA' ORDER BY ec.sort_order");
                                $kra_num = 1;
                                while ($k = $kra_q->fetch_assoc()): ?>
                                    <tr class="kra-row" data-weight="<?php echo $k['weight']; ?>">
                                        <td class="ps-3">
                                            <div class="fw-bold">KRA <?php echo $kra_num++; ?>: <?php echo e($k['criterion_name']); ?></div>
                                            <?php if($k['description']): ?><div class="text-muted x-small"><?php echo e($k['description']); ?></div><?php endif; ?>
                                        </td>
                                        <td class="text-center"><?php echo $k['weight']; ?>%</td>
                                        <td class="text-center">
                                            <?php
                                            $effective_score = $k['score_value'];
                                            $supervisor_override_score = $k['supervisor_override_score'] ?? null;
                                            $manager_override_score = $k['manager_override_score'] ?? null;
                                            $badge_html = '';
                                            if ($supervisor_override_score !== null) {
                                                $effective_score = $supervisor_override_score;
                                                $sup_name_q = $conn->query("SELECT full_name FROM users WHERE user_id = " . (int)($k['supervisor_override_by'] ?? 0))->fetch_assoc();
                                                $sup_name = $sup_name_q['full_name'] ?? 'Supervisor';
                                                $formatted_date = formatDate($k['supervisor_override_at'] ?? '', 'M d, Y h:i A');
                                                $badge_html = '<span class="badge-audit ms-2" data-bs-toggle="tooltip" data-bs-html="true" title="<strong>Supervisor Override</strong><br>Edited by: ' . e($sup_name) . '<br>On: ' . $formatted_date . '<br>Original: ' . $k['score_value'] . '"><i class="fas fa-user-edit me-1"></i>Sup Override</span>';
                                            }
                                            if ($manager_override_score !== null) {
                                                $effective_score = $manager_override_score;
                                                $mgr_name_q = $conn->query("SELECT full_name FROM users WHERE user_id = " . (int)($k['manager_override_by'] ?? 0))->fetch_assoc();
                                                $mgr_name = $mgr_name_q['full_name'] ?? 'Manager';
                                                $formatted_date = formatDate($k['manager_override_at'] ?? '', 'M d, Y h:i A');
                                                $badge_html = '<span class="badge-audit ms-2" data-bs-toggle="tooltip" data-bs-html="true" title="<strong>Manager Override</strong><br>Edited by: ' . e($mgr_name) . '<br>On: ' . $formatted_date . '<br>Original: ' . $k['score_value'] . '"><i class="fas fa-user-edit me-1"></i>Mgr Override</span>';
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
                                        <td class="weighted-score text-center text-primary fw-bold"><?php echo number_format($k['weighted_score'], 2); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                                <tr class="total-row bg-light fw-bold border-top">
                                    <td class="ps-3">KRA Sub-total</td>
                                    <td class="text-center">100%</td>
                                    <td></td>
                                    <td class="text-center text-primary kra-subtotal-val"><?php echo number_format($row['kra_subtotal'], 2); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Behavior Section -->
                    <div class="section-premium-label mb-3 mt-5">
                        <i class="fas fa-heart"></i> II. Behavior & Values
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
                                $beh_q = $conn->query("SELECT es.*, ec.criterion_name, ec.kpi_description FROM evaluation_scores es JOIN evaluation_criteria ec ON es.criterion_id = ec.criterion_id WHERE es.evaluation_id = {$row['evaluation_id']} AND ec.section = 'Behavior' ORDER BY ec.sort_order");
                                while ($b = $beh_q->fetch_assoc()): ?>
                                    <tr class="beh-row">
                                        <td class="ps-3">
                                            <div class="fw-bold"><?php echo e($b['criterion_name']); ?></div>
                                            <div class="text-muted x-small"><?php echo e($b['kpi_description']); ?></div>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                            $effective_score = $b['score_value'];
                                            $supervisor_override_score = $b['supervisor_override_score'] ?? null;
                                            $manager_override_score = $b['manager_override_score'] ?? null;
                                            $badge_html = '';
                                            if ($supervisor_override_score !== null) {
                                                $effective_score = $supervisor_override_score;
                                                $sup_name_q = $conn->query("SELECT full_name FROM users WHERE user_id = " . (int)($b['supervisor_override_by'] ?? 0))->fetch_assoc();
                                                $sup_name = $sup_name_q['full_name'] ?? 'Supervisor';
                                                $formatted_date = formatDate($b['supervisor_override_at'] ?? '', 'M d, Y h:i A');
                                                $badge_html = '<span class="badge-audit ms-2" data-bs-toggle="tooltip" data-bs-html="true" title="<strong>Supervisor Override</strong><br>Edited by: ' . e($sup_name) . '<br>On: ' . $formatted_date . '<br>Original: ' . $b['score_value'] . '"><i class="fas fa-user-edit me-1"></i>Sup Override</span>';
                                            }
                                            if ($manager_override_score !== null) {
                                                $effective_score = $manager_override_score;
                                                $mgr_name_q = $conn->query("SELECT full_name FROM users WHERE user_id = " . (int)($b['manager_override_by'] ?? 0))->fetch_assoc();
                                                $mgr_name = $mgr_name_q['full_name'] ?? 'Manager';
                                                $formatted_date = formatDate($b['manager_override_at'] ?? '', 'M d, Y h:i A');
                                                $badge_html = '<span class="badge-audit ms-2" data-bs-toggle="tooltip" data-bs-html="true" title="<strong>Manager Override</strong><br>Edited by: ' . e($mgr_name) . '<br>On: ' . $formatted_date . '<br>Original: ' . $b['score_value'] . '"><i class="fas fa-user-edit me-1"></i>Mgr Override</span>';
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
                                    <td class="text-center text-primary beh-avg-val"><?php echo number_format($row['behavior_average'], 2); ?></td>
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

                    <!-- Career Growth -->
                    <?php $cg_suited = !empty($row['career_growth_suited']) ? 1 : (!empty($row['desired_position']) ? 1 : 0); ?>
                    <div class="section-premium-label mb-3 mt-5">
                        <i class="fas fa-chart-line"></i> III. Career Growth
                        <button type="button" class="btn btn-sm btn-outline-primary ms-2" id="editCareerBtn<?php echo $row['evaluation_id']; ?>" onclick="toggleCareerEdit(<?php echo $row['evaluation_id']; ?>)"><i class="fas fa-edit me-1"></i>Edit</button>
                    </div>
                    <div class="p-3 bg-light rounded-3 mb-4 border-start border-4 border-info">
                        <!-- View mode -->
                        <div id="careerView<?php echo $row['evaluation_id']; ?>">
                            <div class="mb-2 fw-semibold" style="font-size:0.9rem;">
                                Is the employee better suited for another job within the company?
                                <span class="badge ms-2 <?php echo $cg_suited ? 'bg-success' : 'bg-secondary'; ?>" id="careerSuitedBadge<?php echo $row['evaluation_id']; ?>">
                                    <?php echo $cg_suited ? '&#9745; Yes' : '&#9744; No'; ?>
                                </span>
                            </div>
                            <div class="cg-details-container mt-1 <?php echo !$cg_suited ? 'd-none' : ''; ?>" id="careerPositionContainer<?php echo $row['evaluation_id']; ?>">
                                <div class="small text-muted">
                                    <i class="fas fa-briefcase me-1 text-info"></i>
                                    <strong>Job Function / Department:</strong>
                                    <span class="text-dark fw-semibold ms-1" id="careerPositionText<?php echo $row['evaluation_id']; ?>"><?php echo e($row['desired_position'] ?? 'N/A'); ?></span>
                                </div>
                            </div>
                            <div class="cg-details-container mt-1 <?php echo (!$cg_suited || empty($row['target_date'])) ? 'd-none' : ''; ?>" id="careerDateContainer<?php echo $row['evaluation_id']; ?>">
                                <div class="small text-muted">
                                    <i class="fas fa-calendar-alt me-1 text-info"></i>
                                    <strong>Target Date:</strong>
                                    <span class="text-dark fw-semibold ms-1" id="careerDateText<?php echo $row['evaluation_id']; ?>"><?php echo e($row['target_date'] ?? 'N/A'); ?></span>
                                </div>
                            </div>
                            <div class="cg-details-container mt-1 <?php echo (!$cg_suited || empty($row['career_growth_details'])) ? 'd-none' : ''; ?>" id="careerDetailsContainer<?php echo $row['evaluation_id']; ?>">
                                <div class="small text-muted">
                                    <i class="fas fa-info-circle me-1 text-info"></i>
                                    <strong>Details:</strong>
                                    <span class="text-dark fw-semibold ms-1" id="careerDetailsText<?php echo $row['evaluation_id']; ?>"><?php echo e($row['career_growth_details'] ?? 'N/A'); ?></span>
                                </div>
                            </div>
                        </div>
                        <!-- Edit mode -->
                        <div id="careerEdit<?php echo $row['evaluation_id']; ?>" class="d-none">
                            <div class="mb-2">
                                <label class="form-label fw-semibold small">Is the employee better suited for another job within the company?</label>
                                <select class="form-select form-select-sm" id="careerSuitedInput<?php echo $row['evaluation_id']; ?>" onchange="toggleSuitedInputFields(<?php echo $row['evaluation_id']; ?>)">
                                    <option value="1" <?php echo $cg_suited ? 'selected' : ''; ?>>Yes</option>
                                    <option value="0" <?php echo !$cg_suited ? 'selected' : ''; ?>>No</option>
                                </select>
                            </div>
                            <div id="suitedInputsContainer<?php echo $row['evaluation_id']; ?>" class="<?php echo !$cg_suited ? 'd-none' : ''; ?>">
                                <div class="mb-2">
                                    <label class="form-label fw-semibold small">Desired Position / Department</label>
                                    <input type="text" class="form-control form-control-sm" id="careerPosition<?php echo $row['evaluation_id']; ?>" value="<?php echo e($row['desired_position'] ?? ''); ?>" placeholder="Enter desired position...">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label fw-semibold small">Target Date</label>
                                    <input type="date" class="form-control form-control-sm" id="careerDate<?php echo $row['evaluation_id']; ?>" value="<?php echo e($row['target_date'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Career Growth Details</label>
                                    <textarea class="form-control form-control-sm" id="careerDetails<?php echo $row['evaluation_id']; ?>" rows="3" placeholder="Enter career growth details..."><?php echo e($row['career_growth_details'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-success btn-sm" onclick="saveCareerGrowth(<?php echo $row['evaluation_id']; ?>)"><i class="fas fa-save me-1"></i>Save Career Growth</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleCareerEdit(<?php echo $row['evaluation_id']; ?>)">Cancel</button>
                            </div>
                        </div>
                    </div>

                    <!-- Developmental Plan -->
                    <div class="section-premium-label mb-3 mt-5">
                        <i class="fas fa-seedling"></i> IV. Developmental Plan
                        <button type="button" class="btn btn-sm btn-outline-primary ms-2" id="editDevBtn<?php echo $row['evaluation_id']; ?>" onclick="toggleDevEdit(<?php echo $row['evaluation_id']; ?>)"><i class="fas fa-edit me-1"></i>Edit</button>
                    </div>
                    <div class="p-3 bg-light rounded-3 mb-4 border-start border-4 border-success">
                        <!-- View mode -->
                        <div id="devView<?php echo $row['evaluation_id']; ?>">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle bg-white rounded border mb-0">
                                    <thead class="small text-muted bg-light">
                                        <tr>
                                            <th class="ps-3">Area of Improvement</th>
                                            <th>Support Needed</th>
                                            <th>Time Frame</th>
                                        </tr>
                                    </thead>
                                    <tbody class="small" id="devViewTableBody<?php echo $row['evaluation_id']; ?>">
                                        <?php
                                        $dev_q = $conn->query("SELECT * FROM evaluation_dev_plans WHERE evaluation_id = {$row['evaluation_id']} ORDER BY sort_order");
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
                        <div id="devEdit<?php echo $row['evaluation_id']; ?>" class="d-none">
                            <div class="table-responsive mb-3">
                                <table class="table table-sm table-hover align-middle bg-white rounded border mb-0" id="devEditTable<?php echo $row['evaluation_id']; ?>">
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
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addDevPlanRow(<?php echo $row['evaluation_id']; ?>)">
                                    <i class="fas fa-plus me-1"></i>Add Row
                                </button>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-success btn-sm" onclick="saveDevPlan(<?php echo $row['evaluation_id']; ?>)">
                                        <i class="fas fa-save me-1"></i>Save Developmental Plan
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleDevEdit(<?php echo $row['evaluation_id']; ?>, true)">Cancel</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Remarks -->
                    <div class="section-premium-label mb-3 mt-5">
                        <i class="fas fa-comments"></i> V. Remarks & Decisions
                    </div>
                    <?php if(!empty($row['supervisor_comments'])): ?>
                        <div class="mb-3">
                            <label class="x-small fw-bold text-muted text-uppercase mb-1">Department Supervisor Remarks</label>
                            <div class="p-3 bg-light rounded-3 border italic small"><?php echo nl2br(e($row['supervisor_comments'])); ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if(!empty($row['dept_manager_comments'])): ?>
                        <div class="mb-3">
                            <label class="x-small fw-bold text-muted text-uppercase mb-1">Department Manager Remarks</label>
                            <div class="p-3 bg-light rounded-3 border italic small"><?php echo nl2br(e($row['dept_manager_comments'])); ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if(!empty($row['evaluator_comments'])): ?>
                        <div class="mb-3">
                            <label class="x-small fw-bold text-muted text-uppercase mb-1">HR Supervisor Remarks</label>
                            <div class="p-3 bg-light rounded-3 border italic small"><?php echo nl2br(e($row['evaluator_comments'])); ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" onsubmit="return handleApprovalFormSubmit(event, <?php echo $row['evaluation_id']; ?>)">
                        <input type="hidden" name="evaluation_id" value="<?php echo $row['evaluation_id']; ?>">
                        <div class="mb-4">
                            <label class="form-label fw-bold">Manager's Final Comments</label>
                            <textarea class="form-control bg-light" name="manager_comments" rows="3" placeholder="Enter findings, recommendations, or reasons for rejection..."></textarea>
                        </div>
                        <div class="fixed-action-bar d-flex gap-2 justify-content-end">
                            <?php if ($can_edit_scores): ?>
                                <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-3 fw-bold btn-edit-ratings" onclick="toggleEditRatings(<?php echo (int) $row['evaluation_id']; ?>)">
                                    <i class="fas fa-edit me-1"></i>Edit Ratings
                                </button>
                                <button type="button" class="btn btn-sm btn-success rounded-pill px-3 fw-bold btn-save-ratings d-none" onclick="saveRatings(<?php echo (int) $row['evaluation_id']; ?>)">
                                    <i class="fas fa-save me-1"></i>Save Changes
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold btn-cancel-ratings d-none" onclick="toggleEditRatings(<?php echo (int) $row['evaluation_id']; ?>, true)">
                                    <i class="fas fa-times me-1"></i>Cancel
                                </button>
                            <?php endif; ?>
                            <button type="submit" name="action" value="reject" class="btn btn-outline-danger rounded-pill px-4 fw-bold">
                                <i class="fas fa-times me-2"></i>Reject
                            </button>
                            <button type="submit" name="action" value="approve" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">
                                <i class="fas fa-check-double me-2"></i>Approve Evaluation
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>

<script>
function initializeManagerApprovalUI(root = document) {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return bootstrap.Tooltip.getOrCreateInstance(tooltipTriggerEl);
    });

    const search = document.getElementById('unifiedSearch');
    if (search && search.dataset.queueBound !== '1') {
        search.dataset.queueBound = '1';
        search.addEventListener('input', function() {
        const filter = this.value.toLowerCase();
        const activePane = document.querySelector('.tab-pane.active');
        if (!activePane) return;
        const rows = activePane.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });

        const activeTable = activePane.querySelector('.table');
        if (activeTable && activeTable.id) {
            applyZebraStriping('#' + activeTable.id);
        }
        });
    }
}

function startManagerApprovalRefresh() {
    const root = document.querySelector('[data-queue-auto-refresh="manager"]');
    if (!root || root.dataset.refreshStarted === '1') {
        return;
    }
    root.dataset.refreshStarted = '1';

    let busy = false;
    const refresh = () => {
        if (busy || document.hidden || document.querySelector('.modal.show')) {
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
            ['managerApprovalSummary', 'managerApprovalList', 'managerApprovalModals'].forEach(id => {
                const current = document.getElementById(id);
                const next = doc.getElementById(id);
                if (current && next) {
                    current.replaceWith(next);
                }
            });
            initializeManagerApprovalUI(document);
        })
        .catch(() => {})
        .finally(() => {
            busy = false;
        });
    };

    setInterval(refresh, 10000);
}

document.addEventListener('DOMContentLoaded', function() {
    initializeManagerApprovalUI(document);
    startManagerApprovalRefresh();

    // Handle deep linking for review
    const urlParams = new URLSearchParams(window.location.search);
    const reviewId = urlParams.get('review');
    if (reviewId) {
        const modal = new bootstrap.Modal(document.getElementById('reviewModal' + reviewId));
        modal.show();
    }
});

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

    fetch('ajax/save-rating.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
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

function handleApprovalFormSubmit(event, evalId) {
    const form = event.target;
    const modal = document.querySelector(`#reviewModal${evalId}`);
    if (!modal) return true;

    const inputs = modal.querySelectorAll('.score-input');
    let modified = false;
    let hasError = false;
    const ratings = {};

    inputs.forEach(input => {
        const val = parseFloat(input.value);
        const origVal = parseFloat(input.getAttribute('data-original-val'));
        const scoreId = input.getAttribute('data-score-id');

        if (!scoreId || isNaN(val) || val < 1.00 || val > 4.00) {
            hasError = true;
            input.classList.add('is-invalid');
        } else {
            input.classList.remove('is-invalid');
            ratings[scoreId] = val;
            if (val !== origVal) {
                modified = true;
            }
        }
    });

    if (hasError) {
        if (inputs.length && inputs[0].classList.contains('d-none')) {
            toggleEditRatings(evalId);
        }
        alert('Please enter valid ratings between 1.00 and 4.00.');
        return false;
    }

    if (!modified) {
        return true;
    }

    event.preventDefault();

    const submitBtn = event.submitter;
    const originalBtnText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving & Submitting...';

    const otherBtns = form.querySelectorAll('.fixed-action-bar button');
    otherBtns.forEach(btn => {
        if (btn !== submitBtn) btn.disabled = true;
    });

    const formData = new FormData();
    formData.append('evaluation_id', evalId);
    for (const [key, value] of Object.entries(ratings)) {
        formData.append(`ratings[${key}]`, value);
    }

    fetch('ajax/save-rating.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const hiddenAction = document.createElement('input');
            hiddenAction.type = 'hidden';
            hiddenAction.name = 'action';
            hiddenAction.value = submitBtn.value;
            form.appendChild(hiddenAction);

            form.submit();
        } else {
            alert(data.message || 'An error occurred while saving ratings before submission.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
            otherBtns.forEach(btn => {
                btn.disabled = false;
            });
        }
    })
    .catch(error => {
        console.error('Error saving ratings:', error);
        alert('An unexpected error occurred while saving ratings before submission.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
        otherBtns.forEach(btn => {
            btn.disabled = false;
        });
    });

    return false;
}

// Live ratings recalculation for HRD review modals
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.modal-premium').forEach(modal => {
        const kraWeight = parseFloat(modal.getAttribute('data-kra-weight')) / 100;
        const behaviorWeight = parseFloat(modal.getAttribute('data-behavior-weight')) / 100;

        const inputs = modal.querySelectorAll('.score-input');
        inputs.forEach(input => {
            input.addEventListener('input', () => {
                let valCheck = parseFloat(input.value);
                if (valCheck > 4) {
                    input.value = "4.00";
                }
                // --- KRA Recalculation ---
                let kraTotal = 0;
                modal.querySelectorAll('.kra-row').forEach(row => {
                    const weight = parseFloat(row.getAttribute('data-weight')) / 100;
                    const inp = row.querySelector('.score-input');
                    const weightedCell = row.querySelector('.weighted-score');
                    if (inp) {
                        const val = parseFloat(inp.value) || 0;
                        const weighted = val * weight;
                        kraTotal += weighted;
                        if (weightedCell) {
                            weightedCell.textContent = weighted.toFixed(2);
                        }
                    }
                });

                // --- Behavior Recalculation ---
                let behSum = 0;
                let behCount = 0;
                modal.querySelectorAll('.beh-row').forEach(row => {
                    const inp = row.querySelector('.score-input');
                    if (inp) {
                        const val = parseFloat(inp.value) || 0;
                        behSum += val;
                        behCount++;
                    }
                });
                const behAvg = behCount > 0 ? (behSum / behCount) : 0;

                // --- Round & Sum ---
                const kraRounded = Math.round(kraTotal * 100) / 100;
                const behAvgRounded = Math.round(behAvg * 100) / 100;
                const finalScore = (kraRounded * kraWeight + behAvgRounded * behaviorWeight);
                const finalScoreRounded = Math.round(finalScore * 100) / 100;

                // --- Update UI ---
                const kraSubtotalVal = modal.querySelector('.kra-subtotal-val');
                if (kraSubtotalVal) kraSubtotalVal.textContent = kraRounded.toFixed(2);

                const behAvgVal = modal.querySelector('.beh-avg-val');
                if (behAvgVal) behAvgVal.textContent = behAvgRounded.toFixed(2);

                const totalScoreVal = modal.querySelector('.total-score-val');
                if (totalScoreVal) totalScoreVal.textContent = finalScoreRounded.toFixed(2) + '/4';
            });
        });
    });

    // Instant client-side search in table rows
    const unifiedSearch = document.getElementById('unifiedSearch');
    if (unifiedSearch) {
        unifiedSearch.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('#evalTable tbody tr');
            rows.forEach(row => {
                if (row.querySelector('td[colspan]')) return; // ignore empty state
                const text = row.textContent.toLowerCase();
                if (text.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});
</script>

<?php
// ── Tab switcher JS must come before footer ───────────────────────────────
?>

</div><!-- end evaluations pane -->

<!-- ── PANE: Employee Edit Requests ──────────────────────────────────────── -->
<div data-pa-pane data-pa-pane-id="changes">

<style>
.ecr-card { background:#fff; border:1px solid #eef2e8; border-radius:14px; box-shadow:0 4px 16px rgba(12,32,8,.06); margin-bottom:16px; overflow:hidden; }
.ecr-card-header { display:flex; align-items:center; gap:14px; padding:16px 20px; border-bottom:1px solid #f0f4eb; }
.ecr-emp-avatar { width:46px; height:46px; border-radius:12px; object-fit:cover; flex-shrink:0; }
.ecr-emp-info .emp-name { font-weight:700; font-size:.97rem; }
.ecr-emp-info .emp-meta { font-size:.75rem; color:#8094ae; }
.ecr-submitted-by { margin-left:auto; text-align:right; font-size:.75rem; color:#8094ae; }
.ecr-card-body { padding:16px 20px; }
.diff-table { width:100%; border-collapse:collapse; font-size:.83rem; }
.diff-table th { background:#f8f9fc; color:#8094ae; font-size:.7rem; text-transform:uppercase; letter-spacing:.4px; padding:7px 12px; border-bottom:1px solid #e8ecf1; }
.diff-table td { padding:8px 12px; border-bottom:1px solid #f4f6f9; vertical-align:top; }
.diff-table tr:last-child td { border-bottom:none; }
.diff-old { color:#dc3545; text-decoration:line-through; opacity:.8; }
.diff-new { color:#198754; font-weight:600; }
.diff-field { color:#344357; font-weight:600; }
.ecr-actions { display:flex; gap:10px; align-items:center; flex-wrap:wrap; padding-top:14px; border-top:1px solid #f0f4eb; margin-top:14px; }
.ecr-empty { text-align:center; padding:60px 20px; color:#8094ae; }
.ecr-empty i { font-size:3rem; opacity:.2; display:block; margin-bottom:16px; }
</style>

<div class="chart-card fadeup">
    <div class="cc-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-user-edit me-2"></i>Pending Employee Edit Requests
            <?php if ($ecr_pending_count > 0): ?>
                <span class="badge rounded-pill bg-warning text-dark ms-2"><?php echo $ecr_pending_count; ?></span>
            <?php endif; ?>
        </h5>
        <small class="text-muted">Changes submitted by HR Staff await your review before applying to the live record.</small>
    </div>
    <div class="cc-body">
        <?php if (empty($ecr_all_pending)): ?>
            <div class="ecr-empty">
                <i class="fas fa-inbox"></i>
                <p class="mb-0 fw-semibold">No pending employee edit requests</p>
                <p class="small mt-1">When HR Staff submits a change request, it will appear here for your review.</p>
            </div>
        <?php else: ?>
            <?php foreach ($ecr_all_pending as $ecr):
                $diff = json_decode($ecr['changes_json'] ?? '{}', true);
                $diff = is_array($diff) ? $diff : [];
                $field_labels = [
                    'employee_code' => 'Employee Code',
                    'first_name' => 'First Name', 'last_name' => 'Last Name',
                    'middle_name' => 'Middle Name', 'name_extension' => 'Name Extension',
                    'date_of_birth' => 'Date of Birth', 'place_of_birth' => 'Place of Birth',
                    'gender' => 'Gender', 'civil_status' => 'Civil Status',
                    'height_m' => 'Height (m)', 'weight_kg' => 'Weight (kg)',
                    'blood_type' => 'Blood Type', 'citizenship' => 'Citizenship',
                    'sss_number' => 'SSS No.', 'philhealth_number' => 'PhilHealth No.',
                    'pagibig_number' => 'Pag-IBIG No.', 'tin_number' => 'TIN',
                    'telephone_number' => 'Telephone', 'mobile_number' => 'Mobile',
                    'personal_email' => 'Email',
                    'hire_date' => 'Hire Date', 'employment_status' => 'Employment Status',
                    'employment_type' => 'Employment Type', 'job_title' => 'Job Title',
                    'branch_id' => 'Branch ID', 'department_id' => 'Department ID',
                    'rank_category_id' => 'Rank Category', 'contract_start_date' => 'Contract Start',
                    'contract_end_date' => 'Contract End',
                    'res_house_no' => 'Res. House No.', 'res_street' => 'Res. Street',
                    'res_subdivision' => 'Res. Subdivision', 'res_barangay' => 'Res. Barangay',
                    'res_city' => 'Res. City', 'res_province' => 'Res. Province', 'res_zip_code' => 'Res. ZIP',
                    'perm_house_no' => 'Perm. House No.', 'perm_street' => 'Perm. Street',
                    'perm_subdivision' => 'Perm. Subdivision', 'perm_barangay' => 'Perm. Barangay',
                    'perm_city' => 'Perm. City', 'perm_province' => 'Perm. Province', 'perm_zip_code' => 'Perm. ZIP',
                    'emergency_contact_name' => 'Emergency Contact', 'emergency_contact_relationship' => 'Emergency Relationship',
                    'emergency_contact_number' => 'Emergency Number',
                ];
            ?>
            <div class="ecr-card">
                <div class="ecr-card-header">
                    <img src="<?php echo getEmployeeAvatar($ecr['profile_picture'] ?? null); ?>" alt="Avatar" class="ecr-emp-avatar">
                    <div class="ecr-emp-info">
                        <div class="emp-name"><?php echo e($ecr['emp_name']); ?></div>
                        <div class="emp-meta">
                            <?php echo e($ecr['job_title'] ?? ''); ?>
                            <?php if (!empty($ecr['branch_name'])): ?> · <?php echo e($ecr['branch_name']); ?><?php endif; ?>
                        </div>
                    </div>
                    <div class="ecr-submitted-by">
                        <div><i class="fas fa-user-edit me-1"></i><?php echo e($ecr['staff_name'] ?? 'HR Staff'); ?></div>
                        <div class="text-muted" style="font-size:.7rem;"><?php echo $ecr['created_at'] ? formatDate($ecr['created_at']) : ''; ?></div>
                        <div class="mt-1"><span class="badge bg-warning text-dark" style="font-size:.65rem;"><?php echo count($diff); ?> field(s) changed</span></div>
                    </div>
                </div>

                <div class="ecr-card-body">
                    <?php if (!empty($ecr['change_summary'])): ?>
                        <p class="text-muted small mb-2"><i class="fas fa-info-circle me-1"></i><?php echo e($ecr['change_summary']); ?></p>
                    <?php endif; ?>

                    <!-- Toggle Button -->
                    <button type="button" class="btn btn-sm btn-outline-primary mb-3 ecr-diff-toggle" data-rid="<?php echo $ecr['request_id']; ?>">
                        <i class="fas fa-chevron-down me-1"></i>View Changes
                    </button>

                    <!-- Diff Table (collapsed by default) -->
                    <div id="ecr-diff-<?php echo $ecr['request_id']; ?>" style="display:none;">
                        <?php if (empty($diff)): ?>
                            <p class="text-muted small">No field-level diff available.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="diff-table">
                                    <thead>
                                        <tr>
                                            <th style="width:25%">Field</th>
                                            <th style="width:37.5%">Current Value</th>
                                            <th style="width:37.5%">Proposed Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($diff as $field => $vals): ?>
                                            <tr>
                                                <td class="diff-field"><?php echo e($field_labels[$field] ?? ucwords(str_replace('_', ' ', $field))); ?></td>
                                                <td><span class="diff-old"><?php echo e($vals['old'] !== '' ? $vals['old'] : '—'); ?></span></td>
                                                <td><span class="diff-new"><?php echo e($vals['new'] !== '' ? $vals['new'] : '—'); ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Approve / Reject Forms -->
                    <div class="ecr-actions">
                        <!-- Approve Form -->
                        <form method="POST" action="?tab=changes" class="d-flex gap-2 align-items-center flex-wrap">
                            <input type="hidden" name="ecr_request_id" value="<?php echo $ecr['request_id']; ?>">
                            <input type="hidden" name="ecr_action" value="approve">
                            <input type="text" name="manager_notes" class="form-control form-control-sm" placeholder="Optional notes..." style="max-width:220px;">
                            <button type="submit" class="btn btn-sm btn-success px-4"
                                onclick="return confirm('Approve and apply these changes to the employee record?')">
                                <i class="fas fa-check me-1"></i>Approve & Apply
                            </button>
                        </form>

                        <!-- Reject Form -->
                        <form method="POST" action="?tab=changes" class="d-flex gap-2 align-items-center flex-wrap">
                            <input type="hidden" name="ecr_request_id" value="<?php echo $ecr['request_id']; ?>">
                            <input type="hidden" name="ecr_action" value="reject">
                            <input type="text" name="manager_notes" class="form-control form-control-sm" placeholder="Reason for rejection..." style="max-width:220px;">
                            <button type="submit" class="btn btn-sm btn-outline-danger px-4"
                                onclick="return confirm('Reject this change request?')">
                                <i class="fas fa-times me-1"></i>Reject
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

</div><!-- end changes pane -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching (evaluations / changes)
    const tabLinks = document.querySelectorAll('[data-pa-tab]');
    const tabPanes = document.querySelectorAll('[data-pa-pane]');
    function activateTab(tabName) {
        tabLinks.forEach(t => t.classList.toggle('active', t.dataset.paTab === tabName));
        tabPanes.forEach(p => p.style.display = p.dataset.paPaneId === tabName ? '' : 'none');
        try { history.replaceState(null,'', '?tab=' + tabName); } catch(e){}
    }
    tabLinks.forEach(t => t.addEventListener('click', e => { e.preventDefault(); activateTab(t.dataset.paTab); }));
    // Honor URL param on load
    const urlTab = new URLSearchParams(window.location.search).get('tab') || 'evaluations';
    activateTab(urlTab);

    // Diff expand toggle
    document.querySelectorAll('.ecr-diff-toggle').forEach(btn => {
        btn.addEventListener('click', function() {
            const rid = this.dataset.rid;
            const diff = document.getElementById('ecr-diff-' + rid);
            if (diff) {
                const isVisible = diff.style.display !== 'none';
                diff.style.display = isVisible ? 'none' : '';
                this.innerHTML = isVisible
                    ? '<i class="fas fa-chevron-down me-1"></i>View Changes'
                    : '<i class="fas fa-chevron-up me-1"></i>Hide Changes';
            }
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
