<?php
$page_title = 'Career Movements';
require_once '../includes/session-check.php';
checkRole(['HR Supervisor']);
require_once '../includes/functions.php';

$movement_ready = ensureCareerProgressionMovements($conn);
if ($movement_ready) {
    applyDueCareerProgressionMovements($conn);
}

$current_user_id = (int) ($_SESSION['user_id'] ?? 0);
$sup_branch_id   = (int) ($_SESSION['branch_id'] ?? 0);

// Resolve this supervisor's own employee_id so we can block self-movements
$sup_emp_id = 0;
$sup_emp_stmt = $conn->prepare("SELECT employee_id FROM users WHERE user_id = ? LIMIT 1");
$sup_emp_stmt->bind_param("i", $current_user_id);
$sup_emp_stmt->execute();
$sup_emp_row = $sup_emp_stmt->get_result()->fetch_assoc();
$sup_emp_stmt->close();
if ($sup_emp_row) {
    $sup_emp_id = (int)$sup_emp_row['employee_id'];
}

// ── Helper: apply movement immediately + RBAC ───────────────────────────────
function applyMovementNow($conn, array $movement, int $movement_id): void
{
    executeCareerMovementApplication($conn, $movement, $movement_id);
}

// ── POST: Create movement (HR Supervisor direct submission) ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_movement'])) {
    if (!$movement_ready) {
        redirectWith(BASE_URL . '/supervisor/career-movements.php', 'danger', 'Career Movements could not be initialized.');
    }

    $department_id     = (int)  ($_POST['department_id']     ?? 0);
    $new_department_id = (int)  ($_POST['new_department_id'] ?? $department_id);
    $employee_id       = (int)  ($_POST['employee_id']        ?? 0);
    $movement_type     = trim(  $_POST['movement_type']       ?? '');
    $new_position      = trim(  $_POST['new_position']        ?? '');
    $new_branch_id     = ($_POST['new_branch_id'] ?? '') !== '' ? (int) $_POST['new_branch_id'] : null;
    $effective_date    = trim(  $_POST['effective_date']      ?? '');
    $reason            = trim(  $_POST['reason']              ?? '');
    $allowed_types     = ['Promotion', 'Transfer', 'Demotion', 'Role Change'];

    // Basic validation
    if ($employee_id <= 0 || !in_array($movement_type, $allowed_types, true) || $effective_date === '' || $reason === '') {
        redirectWith(BASE_URL . '/supervisor/career-movements.php', 'danger', 'Please complete all required Career Movement fields.');
    }

    if ($movement_type === 'Transfer') {
        if (empty($new_branch_id) && empty($new_position)) {
            redirectWith(BASE_URL . '/supervisor/career-movements.php', 'danger', 'New Branch or New Position is required for a Transfer.');
        }
    } else {
        if ($new_position === '') {
            redirectWith(BASE_URL . '/supervisor/career-movements.php', 'danger', 'New Position is required for ' . $movement_type . '.');
        }
    }

    // Safeguard: cannot file for themselves
    if ($sup_emp_id > 0 && $employee_id === $sup_emp_id) {
        redirectWith(BASE_URL . '/supervisor/career-movements.php', 'danger', 'You cannot file a career movement for yourself.');
    }

    // Validate employee exists and is active (and within authorized branch if supervisor is branch-scoped)
    if ($sup_branch_id > 0) {
        $emp_chk = $conn->prepare("SELECT employee_id, job_title, branch_id, department_id FROM employees WHERE employee_id=? AND branch_id=? AND is_active=1 LIMIT 1");
        $emp_chk->bind_param("ii", $employee_id, $sup_branch_id);
    } else {
        $emp_chk = $conn->prepare("SELECT employee_id, job_title, branch_id, department_id FROM employees WHERE employee_id=? AND is_active=1 LIMIT 1");
        $emp_chk->bind_param("i", $employee_id);
    }
    $emp_chk->execute();
    $employee = $emp_chk->get_result()->fetch_assoc();
    $emp_chk->close();

    if (!$employee) {
        redirectWith(BASE_URL . '/supervisor/career-movements.php', 'danger', 'Selected employee is not valid or not within your authorized scope.');
    }

    $previous_branch_id = !empty($employee['branch_id']) ? (int)$employee['branch_id'] : null;
    if ($new_branch_id === $previous_branch_id) { $new_branch_id = null; }
    $previous_position  = $employee['job_title'] ?? '';

    // Fetch full employee name
    $emp_name_stmt = $conn->prepare("SELECT first_name, last_name FROM employees WHERE employee_id=? LIMIT 1");
    $emp_name_stmt->bind_param("i", $employee_id);
    $emp_name_stmt->execute();
    $emp_name_row = $emp_name_stmt->get_result()->fetch_assoc();
    $emp_name_stmt->close();
    $employee_name = trim(($emp_name_row['first_name'] ?? '') . ' ' . ($emp_name_row['last_name'] ?? ''));

    $insert = $conn->prepare("
        INSERT INTO career_movements
            (employee_id, movement_type, previous_position, new_position,
             previous_branch_id, new_branch_id, effective_date, reason,
             logged_by, approval_status, initiated_by_name, initiated_by_role,
             initiated_via, request_source)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 'HR Supervisor', 'HR Supervisor', 'Memo', 'HR Portal')
    ");
    $insert->bind_param("isssiissi",
        $employee_id, $movement_type, $previous_position, $new_position,
        $previous_branch_id, $new_branch_id, $effective_date, $reason, $current_user_id
    );

    if (!$insert->execute()) {
        $insert->close();
        redirectWith(BASE_URL . '/supervisor/career-movements.php', 'danger', 'Unable to create the career movement.');
    }
    $movement_id = $insert->insert_id;
    $insert->close();

    $managers = $conn->query("SELECT user_id FROM users WHERE role='HR Manager' AND is_active=1");
    while ($mgr = $managers->fetch_assoc()) {
        createNotification($conn, (int)$mgr['user_id'],
            'Career Movement Submitted',
            "A {$movement_type} has been submitted for {$employee_name}.",
            BASE_URL . '/manager/career-movements.php');
    }
    logAudit($conn, $current_user_id, 'CREATE', 'Career Movement', $movement_id, "Submitted {$movement_type} for {$employee_name}.");
    redirectWith(BASE_URL . '/supervisor/career-movements.php', 'success', 'Career movement submitted for HR Manager review.');
}

// ── POST: Approve / Reject ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['movement_action'])) {
    if (!$movement_ready) {
        redirectWith(BASE_URL . '/supervisor/career-movements.php', 'danger', 'Career Movements could not be initialized.');
    }
    $movement_id = (int)($_POST['movement_id'] ?? 0);
    $action      = trim($_POST['movement_action'] ?? '');

    if ($movement_id <= 0 || !in_array($action, ['Approve','Reject'], true)) {
        redirectWith(BASE_URL . '/supervisor/career-movements.php', 'danger', 'Invalid action.');
    }

    // Task 7.3/7.4 — Fetch movement; accept both HR Portal pending AND Portal_Requests at Pending_HR_Supervisor
    $stmt = $conn->prepare("
        SELECT cm.*, CONCAT(e.first_name,' ',e.last_name) AS employee_name
        FROM career_movements cm
        JOIN employees e ON cm.employee_id = e.employee_id
        WHERE cm.movement_id = ?
          AND (
            (cm.approval_status = 'Pending' AND (cm.portal_workflow_stage IS NULL OR cm.request_source = 'HR Portal'))
            OR
            (cm.request_source = 'Employee Portal' AND cm.portal_workflow_stage = 'Pending_HR_Supervisor')
          )
        LIMIT 1
    ");
    $stmt->bind_param("i", $movement_id);
    $stmt->execute();
    $movement = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$movement) {
        redirectWith(BASE_URL . '/supervisor/career-movements.php', 'danger', 'Movement not found or already processed.');
    }

    // Self-approval block
    if ((int)$movement['logged_by'] === $current_user_id) {
        redirectWith(BASE_URL . '/supervisor/career-movements.php', 'danger', 'You cannot approve or reject a request you submitted.');
    }

    // Detect whether this is an Employee Portal request (Portal_Request) or an HR Portal request
    $is_portal_request = (
        ($movement['request_source'] ?? '') === 'Employee Portal' &&
        ($movement['portal_workflow_stage'] ?? null) !== null
    );

    // ── Task 7.3 — APPROVE ────────────────────────────────────────────────────
    if ($action === 'Approve') {

        if ($is_portal_request) {
            // Portal_Request: stage guard via checkApprovalAuthorization
            if (!checkApprovalAuthorization($movement, $current_user_id, $sup_branch_id, 'HR Supervisor', 'Pending_HR_Supervisor')) {
                redirectWith(BASE_URL . '/supervisor/career-movements.php', 'danger', 'You are not authorized to approve this request.');
            }

            // Advance stage to Pending_HR_Manager, record HR Supervisor decision
            $upd = $conn->prepare("
                UPDATE career_movements
                SET portal_workflow_stage       = 'Pending_HR_Manager',
                    hr_supervisor_approved_by   = ?,
                    hr_supervisor_decision_date = NOW()
                WHERE movement_id = ?
            ");
            $upd->bind_param("ii", $current_user_id, $movement_id);
            $upd->execute();
            $upd->close();

            // Notify all active HR Managers
            $hr_mgr_res = $conn->query("SELECT user_id FROM users WHERE role = 'HR Manager' AND is_active = 1");
            if ($hr_mgr_res && $hr_mgr_res->num_rows > 0) {
                while ($hm = $hr_mgr_res->fetch_assoc()) {
                    createNotification(
                        $conn,
                        (int) $hm['user_id'],
                        'Transfer Request Pending Your Approval',
                        "A Transfer request for {$movement['employee_name']} has been approved by HR Supervisor and requires your final decision.",
                        BASE_URL . '/manager/career-movements.php'
                    );
                }
                $hr_mgr_res->free();
            } else {
                error_log("Career movement notification skipped: no active HR Manager users found for movement_id={$movement_id}.");
            }

            logAudit($conn, $current_user_id, 'APPROVE', 'Career Movement', $movement_id,
                "HR Supervisor approved portal Transfer request for {$movement['employee_name']}.",
                ['module' => 'Career Progression']);
            redirectWith(BASE_URL . '/supervisor/career-movements.php', 'success', 'Transfer request approved and forwarded to HR Manager.');

        } else {
            // ── Existing HR Portal single-step approval flow (unchanged) ─────
            $status   = 'Approved';
            $comments = trim($_POST['manager_comments'] ?? '');

            $upd = $conn->prepare("UPDATE career_movements SET approval_status=?, approved_by=?, decision_date=NOW(), manager_comments=?, is_applied=0 WHERE movement_id=?");
            $upd->bind_param("sisi", $status, $current_user_id, $comments, $movement_id);
            $upd->execute();
            $upd->close();

            // Apply immediately if effective date has already passed / is today
            if ($movement['effective_date'] <= date('Y-m-d')) {
                applyMovementNow($conn, $movement, $movement_id);
            }

            if (!empty($movement['logged_by'])) {
                createNotification($conn, (int)$movement['logged_by'],
                    "Career Movement {$status}",
                    "The {$movement['movement_type']} for {$movement['employee_name']} has been {$status}.",
                    BASE_URL . '/supervisor/career-movements.php');
            }
            $emp_user = getPreferredLinkedUserId($conn, $movement['employee_id'], 'employee_portal');
            if ($emp_user) {
                createNotification($conn, $emp_user,
                    "Career Movement {$status}",
                    "Your career movement ({$movement['movement_type']}) has been {$status}.",
                    BASE_URL . '/employee/notifications.php');
            }

            logAudit($conn, $current_user_id, 'APPROVE', 'Career Movement', $movement_id,
                "Approved {$movement['movement_type']} for {$movement['employee_name']}.");
            redirectWith(BASE_URL . '/supervisor/career-movements.php', 'success', 'Career movement approved.');
        }
    }

    // ── Task 7.4 — REJECT ─────────────────────────────────────────────────────
    if ($action === 'Reject') {

        if ($is_portal_request) {
            // Portal_Request: stage guard via checkApprovalAuthorization
            if (!checkApprovalAuthorization($movement, $current_user_id, $sup_branch_id, 'HR Supervisor', 'Pending_HR_Supervisor')) {
                redirectWith(BASE_URL . '/supervisor/career-movements.php', 'danger', 'You are not authorized to reject this request.');
            }

            // Require non-empty rejection comments (1–1000 chars)
            $hr_supervisor_comments = trim($_POST['manager_comments'] ?? '');
            if (strlen($hr_supervisor_comments) === 0 || strlen($hr_supervisor_comments) > 1000) {
                redirectWith(BASE_URL . '/supervisor/career-movements.php', 'danger', 'A rejection reason is required (1–1000 characters).');
            }

            // Terminate stage at Rejected, set approval_status = Rejected
            $upd = $conn->prepare("
                UPDATE career_movements
                SET portal_workflow_stage       = 'Rejected',
                    approval_status             = 'Rejected',
                    hr_supervisor_approved_by   = ?,
                    hr_supervisor_decision_date = NOW(),
                    hr_supervisor_comments      = ?
                WHERE movement_id = ?
            ");
            $upd->bind_param("isi", $current_user_id, $hr_supervisor_comments, $movement_id);
            $upd->execute();
            $upd->close();

            // Resolve submitter's Employee Portal user for rejection notification
            $submitter_portal_user_id = null;
            $logged_by_user_id = (int)($movement['logged_by'] ?? 0);
            if ($logged_by_user_id > 0) {
                $sub_stmt = $conn->prepare("SELECT employee_id FROM users WHERE user_id = ? LIMIT 1");
                $sub_stmt->bind_param("i", $logged_by_user_id);
                $sub_stmt->execute();
                $sub_row = $sub_stmt->get_result()->fetch_assoc();
                $sub_stmt->close();
                if ($sub_row && !empty($sub_row['employee_id'])) {
                    $submitter_portal_user_id = getPreferredLinkedUserId($conn, (int)$sub_row['employee_id'], 'employee_portal');
                }
            }

            if ($submitter_portal_user_id) {
                createNotification(
                    $conn,
                    $submitter_portal_user_id,
                    'Transfer Request Rejected',
                    "Your Transfer request for {$movement['employee_name']} has been rejected by HR Supervisor. Reason: {$hr_supervisor_comments}",
                    BASE_URL . '/employee/dashboard.php'
                );
            } else {
                error_log("Rejection notification skipped: could not resolve submitter portal user for movement_id={$movement_id}.");
            }

            logAudit($conn, $current_user_id, 'REJECT', 'Career Movement', $movement_id,
                "HR Supervisor rejected portal Transfer request for {$movement['employee_name']}. Reason: {$hr_supervisor_comments}",
                ['module' => 'Career Progression']);
            redirectWith(BASE_URL . '/supervisor/career-movements.php', 'success', 'Transfer request rejected.');

        } else {
            // ── Existing HR Portal single-step rejection flow (unchanged) ────
            $status   = 'Rejected';
            $comments = trim($_POST['manager_comments'] ?? '');

            $upd = $conn->prepare("UPDATE career_movements SET approval_status=?, approved_by=?, decision_date=NOW(), manager_comments=?, is_applied=0 WHERE movement_id=?");
            $upd->bind_param("sisi", $status, $current_user_id, $comments, $movement_id);
            $upd->execute();
            $upd->close();

            if (!empty($movement['logged_by'])) {
                createNotification($conn, (int)$movement['logged_by'],
                    "Career Movement {$status}",
                    "The {$movement['movement_type']} for {$movement['employee_name']} has been {$status}.",
                    BASE_URL . '/supervisor/career-movements.php');
            }
            $emp_user = getPreferredLinkedUserId($conn, $movement['employee_id'], 'employee_portal');
            if ($emp_user) {
                createNotification($conn, $emp_user,
                    "Career Movement {$status}",
                    "Your career movement ({$movement['movement_type']}) has been {$status}.",
                    BASE_URL . '/employee/notifications.php');
            }

            logAudit($conn, $current_user_id, 'REJECT', 'Career Movement', $movement_id,
                "Rejected {$movement['movement_type']} for {$movement['employee_name']}.");
            redirectWith(BASE_URL . '/supervisor/career-movements.php', 'warning', 'Career movement rejected.');
        }
    }
}

// ── POST: Cancel movement (HR Supervisor cancels own pending submission) ────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_movement'])) {
    if (!$movement_ready) {
        redirectWith(BASE_URL . '/supervisor/career-movements.php', 'danger', 'Career Movements could not be initialized.');
    }
    $movement_id = (int)($_POST['movement_id'] ?? 0);
    if ($movement_id <= 0) {
        redirectWith(BASE_URL . '/supervisor/career-movements.php', 'danger', 'Invalid movement.');
    }

    // Fetch movement — must be Pending, from HR Portal, submitted by current user
    $stmt = $conn->prepare("
        SELECT cm.*, CONCAT(e.first_name,' ',e.last_name) AS employee_name
        FROM career_movements cm
        JOIN employees e ON cm.employee_id = e.employee_id
        WHERE cm.movement_id = ?
          AND cm.logged_by = ?
          AND cm.approval_status = 'Pending'
          AND cm.request_source = 'HR Portal'
        LIMIT 1
    ");
    $stmt->bind_param("ii", $movement_id, $current_user_id);
    $stmt->execute();
    $movement = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$movement) {
        redirectWith(BASE_URL . '/supervisor/career-movements.php', 'danger', 'Movement not found, already processed, or you are not authorized to cancel it.');
    }

    $del = $conn->prepare("DELETE FROM career_movements WHERE movement_id = ? AND logged_by = ? AND approval_status = 'Pending'");
    $del->bind_param("ii", $movement_id, $current_user_id);
    $del->execute();
    $del->close();

    logAudit($conn, $current_user_id, 'DELETE', 'Career Movement', $movement_id,
        "HR Supervisor cancelled pending {$movement['movement_type']} request for {$movement['employee_name']}.");
    redirectWith(BASE_URL . '/supervisor/career-movements.php', 'warning', 'Career movement cancelled successfully.');
}

// ── POST: Edit movement (HR Supervisor edits own HR Portal submission) ────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_movement'])) {
    if (!$movement_ready) {
        redirectWith(BASE_URL . '/supervisor/career-movements.php', 'danger', 'Career Movements could not be initialized.');
    }
    $movement_id       = (int)  ($_POST['movement_id']       ?? 0);
    $movement_type     = trim(  $_POST['movement_type']       ?? '');
    $new_position      = trim(  $_POST['new_position']        ?? '');
    $new_branch_id     = ($_POST['new_branch_id'] ?? '') !== '' ? (int) $_POST['new_branch_id'] : null;
    $effective_date    = trim(  $_POST['effective_date']      ?? '');
    $reason            = trim(  $_POST['reason']              ?? '');
    $allowed_types     = ['Promotion', 'Transfer', 'Demotion', 'Role Change'];

    if ($movement_id <= 0 || !in_array($movement_type, $allowed_types, true) || $effective_date === '' || $reason === '') {
        redirectWith(BASE_URL . '/supervisor/career-movements.php', 'danger', 'Please complete all required fields for the edit.');
    }

    if ($movement_type !== 'Transfer' && $new_position === '') {
        redirectWith(BASE_URL . '/supervisor/career-movements.php', 'danger', 'New Position is required for ' . $movement_type . '.');
    }

    // Fetch movement — must be from HR Portal and submitted by current user (any status)
    $stmt = $conn->prepare("
        SELECT cm.*, CONCAT(e.first_name,' ',e.last_name) AS employee_name
        FROM career_movements cm
        JOIN employees e ON cm.employee_id = e.employee_id
        WHERE cm.movement_id = ?
          AND cm.logged_by = ?
          AND cm.request_source = 'HR Portal'
        LIMIT 1
    ");
    $stmt->bind_param("ii", $movement_id, $current_user_id);
    $stmt->execute();
    $movement = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$movement) {
        redirectWith(BASE_URL . '/supervisor/career-movements.php', 'danger', 'Movement not found or you are not authorized to edit it.');
    }

    $upd = $conn->prepare("
        UPDATE career_movements
        SET movement_type  = ?,
            new_position   = ?,
            new_branch_id  = ?,
            effective_date = ?,
            reason         = ?
        WHERE movement_id = ? AND logged_by = ?
    ");
    $upd->bind_param("ssissii", $movement_type, $new_position, $new_branch_id, $effective_date, $reason, $movement_id, $current_user_id);
    $upd->execute();
    $upd->close();

    // If already approved and applied, re-apply the position/branch update directly to the employee's profile
    if ($movement['approval_status'] === 'Approved' && (int)($movement['is_applied'] ?? 0) === 1) {
        $updated_mv = array_merge($movement, [
            'movement_type'  => $movement_type,
            'new_position'   => $new_position,
            'new_branch_id'  => $new_branch_id,
            'effective_date' => $effective_date,
            'reason'         => $reason
        ]);
        executeCareerMovementApplication($conn, $updated_mv, $movement_id);
    }

    logAudit($conn, $current_user_id, 'UPDATE', 'Career Movement', $movement_id,
        "HR Supervisor edited {$movement['approval_status']} {$movement_type} request for {$movement['employee_name']}.");
    redirectWith(BASE_URL . '/supervisor/career-movements.php', 'success', 'Career movement updated successfully.');
}

// ── Fetch display data ───────────────────────────────────────────────────────
require_once '../includes/header.php';

$branches    = [];
$branch_names = [];
$movements   = [];
$counts      = ['Submitted'=>0,'Pending'=>0,'Approved'=>0,'Rejected'=>0,'Applied'=>0];

// Departments
$dept_result = $conn->query("SELECT department_id, department_name FROM departments WHERE is_active=1 ORDER BY department_name");
$departments = [];
while ($row = $dept_result->fetch_assoc()) $departments[] = $row;

// Job titles grouped by department (for JS)
$jt_result = $conn->query("
    SELECT jt.job_title_id, jt.job_title, jt.department_id, jt.rank_category_id, rc.level_order
    FROM job_titles jt
    LEFT JOIN rank_categories rc ON jt.rank_category_id = rc.rank_category_id
    WHERE jt.is_active=1
    ORDER BY jt.department_id, rc.level_order ASC, jt.job_title ASC
");
$dept_positions = []; // dept_id => [positions]
while ($row = $jt_result->fetch_assoc()) {
    $dept_positions[$row['department_id']][] = [
        'id'          => $row['job_title_id'],
        'title'       => $row['job_title'],
        'level_order' => (int)($row['level_order'] ?? 5)
    ];
}

// Employees grouped by department (+ branch filter)
// We send to JS so the cascade works client-side
$emp_sql_params = "";
$emp_sql_where  = "e.is_active = 1
      AND e.employee_id NOT IN (SELECT employee_id FROM users WHERE role='Admin' AND employee_id IS NOT NULL)";
if ($sup_branch_id > 0) {
    $emp_sql_where .= " AND e.branch_id = {$sup_branch_id}";
}
$emp_result = $conn->query("
    SELECT e.employee_id, e.employee_code, e.first_name, e.last_name, e.job_title, e.job_title_id,
           e.branch_id, e.department_id, e.rank_category_id,
           b.branch_name, d.department_name,
           rc.rank_name, rc.level_order
    FROM employees e
    LEFT JOIN branches b ON e.branch_id = b.branch_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN rank_categories rc ON e.rank_category_id = rc.rank_category_id
    WHERE {$emp_sql_where}
    ORDER BY e.last_name, e.first_name
");
$dept_employees = []; // dept_id => [employees]
$all_employees  = [];
while ($row = $emp_result->fetch_assoc()) {
    $did = (int)$row['department_id'];
    $dept_employees[$did][] = $row;
    $all_employees[$row['employee_id']] = $row;
}

$branch_result = $conn->query("SELECT branch_id, branch_name FROM branches ORDER BY branch_name");
while ($row = $branch_result->fetch_assoc()) {
    $branches[]  = $row;
    $branch_names[(string)$row['branch_id']] = $row['branch_name'];
}

if ($movement_ready) {
    $stmt = $conn->prepare("
        SELECT cm.*,
            e.employee_code,
            e.job_title AS current_job_title,
            CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
            d.department_name,
            pb.branch_name AS previous_branch_name,
            nb.branch_name AS new_branch_name,
            u1.full_name   AS logged_by_name,
            u2.full_name   AS approved_by_name,
            bm_u.full_name  AS bm_approver_name,
            hrs_u.full_name AS hrs_approver_name
        FROM career_movements cm
        JOIN employees  e  ON cm.employee_id       = e.employee_id
        LEFT JOIN departments d ON e.department_id = d.department_id
        LEFT JOIN branches pb ON cm.previous_branch_id = pb.branch_id
        LEFT JOIN branches nb ON cm.new_branch_id       = nb.branch_id
        LEFT JOIN users   u1 ON cm.logged_by            = u1.user_id
        LEFT JOIN users   u2 ON cm.approved_by          = u2.user_id
        LEFT JOIN users   bm_u ON cm.branch_manager_approved_by = bm_u.user_id
        LEFT JOIN users   hrs_u ON cm.hr_supervisor_approved_by = hrs_u.user_id
        ORDER BY cm.created_at DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $movements[] = $row;
        $counts['Submitted']++;
        if (isset($counts[$row['approval_status']])) $counts[$row['approval_status']]++;
        if ((int)($row['is_applied']??0)===1) $counts['Applied']++;
    }
    $stmt->close();
}

function supCmTypeClass($t){return match($t){'Promotion'=>'bg-success','Transfer'=>'bg-info text-dark','Demotion'=>'bg-danger','Role Change'=>'bg-primary',default=>'bg-secondary'};}
function supCmStatusClass($s){return match($s){'Approved'=>'bg-success','Rejected'=>'bg-danger',default=>'bg-warning text-dark'};}
?>

<style>
/* Career Movements page theme: keep HRIS green/gold consistent with the shared portal. */
.career-movements-page .chart-card {
    border: 1px solid rgba(8, 46, 6, .12) !important;
    box-shadow: 0 8px 24px rgba(8, 46, 6, .08) !important;
}
.career-movements-page .cc-header {
    background: #f8fbf7 !important;
    border-bottom-color: #cba135 !important;
}
.career-movements-page .cc-header-tabs .nav-link {
    color: #3d4d3a;
    transition: background .2s ease, color .2s ease;
}
.career-movements-page .cc-header-tabs .nav-link:hover,
.career-movements-page .cc-header-tabs .nav-link.active {
    background: #082e06 !important;
    color: #fff !important;
}
.career-movements-page .cc-header-tabs .nav-link:hover i,
.career-movements-page .cc-header-tabs .nav-link.active i {
    color: #f0c040 !important;
}
.career-movements-page #supMovSearch,
.career-movements-page .form-select,
.career-movements-page .form-control {
    border-color: #c8d3c5 !important;
    color: #1c271b;
    box-shadow: 0 2px 8px rgba(8, 46, 6, .06) !important;
}
.career-movements-page #supMovSearch:focus,
.career-movements-page .form-select:focus,
.career-movements-page .form-control:focus {
    border-color: #cba135 !important;
    box-shadow: 0 0 0 .2rem rgba(203, 161, 53, .2) !important;
}
.career-movements-page .table thead th {
    background: #082e06 !important;
    color: #fff !important;
}
.career-movements-page .table tbody tr:hover {
    background: #f4f8f2 !important;
}
.career-movements-page .table tbody tr:nth-child(even) {
    background: #fbfdfb;
}
.career-movements-page .btn-primary,
.career-movements-page .btn-success {
    background: #082e06 !important;
    border-color: #cba135 !important;
}
.career-movements-page .btn-primary:hover,
.career-movements-page .btn-success:hover {
    background: #174d12 !important;
    border-color: #f0c040 !important;
}
.career-movements-page .btn-outline-primary {
    color: #082e06 !important;
    border-color: #082e06 !important;
}
.career-movements-page .btn-outline-primary:hover {
    background: #082e06 !important;
    color: #fff !important;
}
.career-movements-page .sup-form-section {
    background: #f8fbf7 !important;
    border-color: rgba(8, 46, 6, .16) !important;
}
@media (max-width: 767.98px) {
    .career-movements-page .cc-header {
        padding: 1rem !important;
    }
    .career-movements-page .search-box {
        min-width: 100% !important;
    }
    .career-movements-page .page-hero {
        padding: 1.25rem !important;
    }
}
</style>

<div class="career-movements-page">
<div class="page-hero fadeup">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
        <div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">HR Supervisor &middot; Career</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-route me-2" style="color:#BD9414;"></i>Career Movements</h4>
            <p class="text-white-50 small mb-0 mt-2">Submit and monitor employee promotion, transfer, and role-change requests for managerial review.</p>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-value"><?php echo $counts['Submitted']; ?></div><div class="stat-label">Total</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-value"><?php echo $counts['Pending']; ?></div><div class="stat-label">Pending</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-value"><?php echo $counts['Approved']; ?></div><div class="stat-label">Approved</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-value"><?php echo $counts['Applied']; ?></div><div class="stat-label">Applied</div></div></div>
    </div>
</div>

<?php if (!$movement_ready): ?>
    <div class="alert alert-danger">Career Movements could not be initialized.</div>
<?php endif; ?>

<div class="chart-card fadeup shadow-sm border-0 rounded-3 overflow-hidden">
    <div class="cc-header d-flex flex-wrap align-items-center justify-content-between gap-3 p-3" style="background:#ffffff;border-bottom:2px solid #082E06;">
        <ul class="nav nav-tabs cc-header-tabs border-0" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-bold px-3 py-2 border-0" data-bs-toggle="tab" data-bs-target="#supListTab" type="button" role="tab" style="border-radius:8px;font-size:.85rem;">
                    <i class="fas fa-list me-2" style="color:#082E06;"></i>All Movements
                    <span class="badge rounded-pill ms-2" style="background:#082E06;color:#CBA135;"><?php echo count($movements); ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold px-3 py-2 border-0" data-bs-toggle="tab" data-bs-target="#supCreateTab" type="button" role="tab" id="supCreateTabBtn" style="border-radius:8px;font-size:.85rem;">
                    <i class="fas fa-plus me-2" style="color:#CBA135;"></i>File New Movement
                </button>
            </li>
        </ul>
        <div class="search-box position-relative" style="min-width:260px;">
            <i class="fas fa-search search-icon text-muted" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:.82rem;"></i>
            <input type="text" class="form-control form-control-sm ps-4" id="supMovSearch" placeholder="Search employee, position, status..." style="border-radius:20px;border-color:rgba(203,161,53,0.4);font-size:.82rem;">
        </div>
    </div>

    <div class="cc-body p-0">
        <div class="tab-content">

            <!-- LIST TAB -->
            <div class="tab-pane fade show active" id="supListTab" role="tabpanel">
                <div class="table-responsive" style="overflow-x:auto;">
                    <table class="table align-middle mb-0" id="supMovTable" style="font-size:.84rem;width:100%;table-layout:auto;">
                        <thead>
                            <tr style="background:#082E06;color:#ffffff;font-size:.72rem;letter-spacing:.06em;text-transform:uppercase;">
                                <th class="py-3 ps-3" style="min-width:210px;">Employee & Source</th>
                                <th class="py-3" style="min-width:240px;">Movement & Transition</th>
                                <th class="py-3" style="width:110px;">Effective</th>
                                <th class="py-3" style="min-width:160px;">Workflow Status</th>
                                <th class="py-3 text-end pe-3" style="width:140px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($movements)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="fas fa-route d-block mb-3" style="font-size:2.5rem;color:#082E06;opacity:.25;"></i>
                                        <div class="fw-bold fs-6 text-dark mb-1">No Career Movements Found</div>
                                        <p class="mb-0 small text-muted">Career progression & transfer requests will appear here.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($movements as $mv):
                                    $is_mine        = (int)($mv['logged_by']??0) === $current_user_id;
                                    $is_portal_req  = (($mv['request_source'] ?? '') === 'Employee Portal' && !empty($mv['portal_workflow_stage']));
                                    $is_pending     = $mv['approval_status'] === 'Pending';
                                    $is_hr_staff_req = ($mv['request_source']==='HR Portal' && ($mv['initiated_by_role']??'')==='HR Staff');

                                    // Authorize HR Supervisor actions
                                    $can_sup_act = false;
                                    if ($is_portal_req) {
                                        $can_sup_act = ($mv['portal_workflow_stage'] === 'Pending_HR_Supervisor' && !$is_mine);
                                    } else {
                                        $can_sup_act = ($is_pending && !$is_mine);
                                    }

                                    // Initials for avatar
                                    $emp_name_parts = explode(',', $mv['employee_name']);
                                    $l_name = trim($emp_name_parts[0] ?? '');
                                    $f_name = trim($emp_name_parts[1] ?? '');
                                    $initials = strtoupper(substr($f_name, 0, 1) . substr($l_name, 0, 1)) ?: 'EM';

                                    // Movement Type Badge styling
                                    $type_style = match($mv['movement_type']) {
                                        'Promotion'   => 'background:rgba(40,167,69,0.15);color:#198754;border:1px solid rgba(40,167,69,0.35);',
                                        'Transfer'    => 'background:rgba(13,202,240,0.15);color:#087990;border:1px solid rgba(13,202,240,0.35);',
                                        'Demotion'    => 'background:rgba(220,53,69,0.15);color:#dc3545;border:1px solid rgba(220,53,69,0.35);',
                                        'Role Change' => 'background:rgba(203,161,53,0.18);color:#b38615;border:1px solid rgba(203,161,53,0.4);',
                                        default       => 'background:#e2e3e5;color:#383d41;border:1px solid #d6d8db;',
                                    };
                                    $type_icon = match($mv['movement_type']) {
                                        'Promotion'   => 'fa-arrow-up',
                                        'Transfer'    => 'fa-random',
                                        'Demotion'    => 'fa-arrow-down',
                                        'Role Change' => 'fa-sync-alt',
                                        default       => 'fa-exchange-alt',
                                    };
                                ?>
                                <tr class="border-bottom" style="background:#ffffff;transition:background 0.15s ease-in-out;">
                                    <!-- Column 1: Employee & Source -->
                                    <td class="py-3 ps-3">
                                        <div class="d-flex align-items-center gap-2.5">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width:36px;height:36px;background:linear-gradient(135deg, #082E06 0%, #163e12 100%);color:#CBA135;font-size:.8rem;flex-shrink:0;border:1px solid #CBA135;">
                                                <?php echo e($initials); ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold" style="color:#082E06;font-size:.86rem;line-height:1.2;"><?php echo e($mv['employee_name']); ?></div>
                                                <div class="d-flex flex-wrap align-items-center gap-1 mt-1">
                                                    <span class="badge" style="background:rgba(8,46,6,0.08);color:#082E06;font-size:.65rem;font-weight:600;"><?php echo e(getEmployeeDisplayId($mv)); ?></span>
                                                    <?php if (!empty($mv['department_name'])): ?>
                                                        <span class="badge bg-light text-secondary border" style="font-size:.65rem;"><?php echo e($mv['department_name']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="mt-1" style="font-size:.68rem;">
                                                    <?php if ($is_hr_staff_req): ?>
                                                        <span class="text-warning fw-semibold"><i class="fas fa-user-shield me-1"></i>HR Staff Requisition</span>
                                                    <?php elseif ($mv['request_source']==='Employee Portal'): ?>
                                                        <span class="text-info fw-semibold"><i class="fas fa-user-tie me-1"></i>Branch Head Requisition</span>
                                                        <?php if (!empty($mv['initiated_by_name'])): ?>
                                                            <span class="text-muted">by <?php echo e($mv['initiated_by_name']); ?></span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-secondary fw-semibold"><i class="fas fa-building me-1"></i>HR Portal</span>
                                                        <span class="text-muted">by <?php echo e($mv['logged_by_name']?:'HRD'); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Column 2: Movement & Transition Details -->
                                    <td class="py-3">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="badge px-2 py-1 rounded-pill fw-bold" style="<?php echo $type_style; ?>font-size:.7rem;">
                                                <i class="fas <?php echo $type_icon; ?> me-1"></i><?php echo e($mv['movement_type']); ?>
                                            </span>
                                        </div>
                                        <div class="fw-bold text-dark" style="font-size:.82rem;">
                                            <span class="text-muted fw-normal" style="font-size:.76rem;"><?php echo e($mv['previous_position'] ?: $mv['current_job_title'] ?: '—'); ?></span>
                                            <i class="fas fa-long-arrow-alt-right mx-1" style="color:#CBA135;"></i>
                                            <span style="color:#082E06;"><?php echo e($mv['new_position']); ?></span>
                                        </div>
                                        <?php if (!empty($mv['new_branch_id'])): ?>
                                            <div class="small text-muted mt-0.5" style="font-size:.72rem;">
                                                <i class="fas fa-store me-1"></i><?php echo e($mv['previous_branch_name']?:'Current Branch'); ?>
                                                <i class="fas fa-arrow-right mx-1 text-danger"></i>
                                                <strong class="text-dark"><?php echo e($mv['new_branch_name']?:'N/A'); ?></strong>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-muted small mt-0.5" style="font-size:.7rem;"><i class="fas fa-minus me-1"></i>No branch change</div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Column 3: Effective Date -->
                                    <td class="py-3">
                                        <span class="badge bg-light text-dark border px-2 py-1" style="font-size:.72rem;font-weight:600;">
                                            <i class="fas fa-calendar-alt me-1 text-secondary"></i><?php echo formatDate($mv['effective_date']); ?>
                                        </span>
                                    </td>

                                    <!-- Column 4: Workflow Status -->
                                    <td class="py-3">
                                        <?php if ($is_portal_req): ?>
                                            <?php
                                            $p_stage = $mv['portal_workflow_stage'];
                                            if ($p_stage === 'Pending_Branch_Manager'): ?>
                                                <span class="badge rounded-pill bg-warning text-dark px-2.5 py-1" style="font-size:.7rem;"><i class="fas fa-user-tie me-1"></i>Pending BM</span>
                                            <?php elseif ($p_stage === 'Pending_HR_Supervisor'): ?>
                                                <span class="badge rounded-pill px-2.5 py-1 text-white shadow-sm" style="background:linear-gradient(135deg, #082E06, #163e12);border:1px solid #CBA135;font-size:.7rem;"><i class="fas fa-user-shield me-1" style="color:#CBA135;"></i>Pending HR Sup</span>
                                            <?php elseif ($p_stage === 'Pending_HR_Manager'): ?>
                                                <span class="badge rounded-pill bg-info text-dark px-2.5 py-1" style="font-size:.7rem;"><i class="fas fa-paper-plane me-1"></i>Endorsed HR Mgr</span>
                                            <?php elseif ($p_stage === 'Approved' || $mv['approval_status'] === 'Approved'): ?>
                                                <span class="badge rounded-pill bg-success px-2.5 py-1" style="font-size:.7rem;"><i class="fas fa-check-circle me-1"></i>Approved</span>
                                                <?php if ((int)($mv['is_applied']??0) === 1): ?>
                                                    <span class="badge rounded-pill bg-success ms-1" style="font-size:.62rem;">Applied</span>
                                                <?php else: ?>
                                                    <span class="badge rounded-pill bg-secondary ms-1" style="font-size:.62rem;">Scheduled</span>
                                                <?php endif; ?>
                                            <?php elseif ($p_stage === 'Rejected' || $mv['approval_status'] === 'Rejected'): ?>
                                                <span class="badge rounded-pill bg-danger px-2.5 py-1" style="font-size:.7rem;"><i class="fas fa-times-circle me-1"></i>Rejected</span>
                                            <?php else: ?>
                                                <span class="badge rounded-pill bg-secondary px-2.5 py-1" style="font-size:.7rem;"><?php echo e($mv['approval_status']); ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge rounded-pill <?php echo supCmStatusClass($mv['approval_status']); ?> px-2.5 py-1" style="font-size:.7rem;"><?php echo e($mv['approval_status']); ?></span>
                                            <?php if ($mv['approval_status']==='Approved' && (int)$mv['is_applied']===1): ?>
                                                <span class="badge rounded-pill bg-success ms-1" style="font-size:.62rem;">Applied</span>
                                            <?php elseif ($mv['approval_status']==='Approved'): ?>
                                                <span class="badge rounded-pill bg-secondary ms-1" style="font-size:.62rem;">Scheduled</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Column 5: Actions -->
                                    <td class="py-3 text-end pe-3">
                                        <?php if ($can_sup_act): ?>
                                            <div class="d-inline-flex gap-1">
                                                <form method="POST" class="d-inline">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="movement_id" value="<?php echo (int)$mv['movement_id']; ?>">
                                                    <input type="hidden" name="movement_action" value="Approve">
                                                    <button type="submit" class="btn btn-sm text-white fw-bold px-2.5 shadow-sm" style="background:linear-gradient(135deg, #082E06 0%, #163e12 100%);border:1px solid #CBA135;border-radius:6px;font-size:.72rem;" onclick="return confirm('<?php echo $is_portal_req ? 'Approve and endorse this transfer request to HR Manager?' : 'Approve this career movement?'; ?>');">
                                                        <i class="fas fa-check me-1" style="color:#CBA135;"></i><?php echo $is_portal_req ? 'Endorse' : 'Approve'; ?>
                                                    </button>
                                                </form>
                                                <button class="btn btn-sm btn-outline-danger fw-semibold px-2" style="border-radius:6px;font-size:.72rem;"
                                                    data-bs-toggle="modal" data-bs-target="#supRejectModal"
                                                    data-mvid="<?php echo (int)$mv['movement_id']; ?>"
                                                    data-empname="<?php echo e($mv['employee_name']); ?>">
                                                    <i class="fas fa-times me-1"></i>Reject
                                                </button>
                                            </div>
                                        <?php elseif ($is_portal_req && $mv['portal_workflow_stage'] === 'Pending_HR_Manager'): ?>
                                            <span class="small fw-semibold text-success" style="font-size:.72rem;"><i class="fas fa-check-double me-1"></i>Endorsed</span>
                                        <?php elseif ($is_portal_req && $mv['portal_workflow_stage'] === 'Pending_Branch_Manager'): ?>
                                            <span class="small text-muted fst-italic" style="font-size:.72rem;">Awaiting BM</span>
                                        <?php elseif ($is_pending && $is_mine): ?>
                                            <div class="d-inline-flex gap-1">
                                                <button class="btn btn-sm fw-semibold px-2"
                                                    style="background:linear-gradient(135deg,#0d4d0a,#1a6617);color:#fff;border:1px solid #CBA135;border-radius:6px;font-size:.72rem;"
                                                    data-bs-toggle="modal" data-bs-target="#supEditModal"
                                                    data-mvid="<?php echo (int)$mv['movement_id']; ?>"
                                                    data-type="<?php echo e($mv['movement_type']); ?>"
                                                    data-position="<?php echo e($mv['new_position']); ?>"
                                                    data-branchid="<?php echo (int)($mv['new_branch_id'] ?? 0); ?>"
                                                    data-date="<?php echo e($mv['effective_date']); ?>"
                                                    data-reason="<?php echo e($mv['reason']); ?>"
                                                    data-empname="<?php echo e($mv['employee_name']); ?>">
                                                    <i class="fas fa-pencil-alt me-1" style="color:#CBA135;"></i>Edit
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger fw-semibold px-2"
                                                    style="border-radius:6px;font-size:.72rem;"
                                                    data-bs-toggle="modal" data-bs-target="#supCancelModal"
                                                    data-mvid="<?php echo (int)$mv['movement_id']; ?>"
                                                    data-empname="<?php echo e($mv['employee_name']); ?>"
                                                    data-type="<?php echo e($mv['movement_type']); ?>">
                                                    <i class="fas fa-ban me-1"></i>Cancel
                                                </button>
                                            </div>
                                        <?php elseif ($is_mine): ?>
                                            <!-- Own movement (non-pending): show Edit only -->
                                            <button class="btn btn-sm fw-semibold px-2"
                                                style="background:linear-gradient(135deg,#0d4d0a,#1a6617);color:#fff;border:1px solid #CBA135;border-radius:6px;font-size:.72rem;"
                                                data-bs-toggle="modal" data-bs-target="#supEditModal"
                                                data-mvid="<?php echo (int)$mv['movement_id']; ?>"
                                                data-type="<?php echo e($mv['movement_type']); ?>"
                                                data-position="<?php echo e($mv['new_position']); ?>"
                                                data-branchid="<?php echo (int)($mv['new_branch_id'] ?? 0); ?>"
                                                data-date="<?php echo e($mv['effective_date']); ?>"
                                                data-reason="<?php echo e($mv['reason']); ?>"
                                                data-empname="<?php echo e($mv['employee_name']); ?>">
                                                <i class="fas fa-pencil-alt me-1" style="color:#CBA135;"></i>Edit
                                            </button>
                                        <?php else: ?>
                                            <span class="small text-muted" style="font-size:.72rem;"><?php echo e($mv['approved_by_name']?:'Processed'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <!-- Sub-row: Justification & Visual Approval Timeline Stepper -->
                                <tr style="background:#fafdfa;border-bottom:1px solid rgba(8,46,6,0.08);">
                                    <td colspan="5" class="p-2.5 ps-3 pe-3">
                                        <div class="p-2.5 rounded-3 shadow-sm" style="background:#ffffff;border:1px solid rgba(8,46,6,0.1);">
                                            <div class="row g-2 align-items-center">
                                                <!-- Reason Quote Box -->
                                                <div class="col-12 col-md-6">
                                                    <div class="p-2 rounded-2" style="background:#f4fbf3;border-left:3px solid #082E06;">
                                                        <div class="d-flex align-items-center gap-1 mb-0.5" style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#082E06;">
                                                            <i class="fas fa-comment-dots" style="color:#CBA135;"></i>Requisition Justification
                                                        </div>
                                                        <div class="text-dark small" style="font-style:italic;font-size:.78rem;">
                                                            "<?php echo e($mv['reason'] ?: 'No justification recorded.'); ?>"
                                                        </div>
                                                        <?php if (!empty($mv['manager_comments'])): ?>
                                                            <div class="mt-1 text-danger small pt-1" style="border-top:1px dashed rgba(220,53,69,0.3);font-size:.75rem;">
                                                                <strong>Decision Note:</strong> <?php echo e($mv['manager_comments']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <!-- Approval Stepper Timeline -->
                                                <div class="col-12 col-md-6">
                                                    <div class="d-flex align-items-center justify-content-between position-relative px-2">
                                                        <!-- Line Connector -->
                                                        <div style="position:absolute;top:13px;left:30px;right:30px;height:2px;background:#e2e8e1;z-index:1;"></div>

                                                        <!-- Node 1: Branch Manager -->
                                                        <div class="text-center position-relative" style="z-index:2;">
                                                            <?php if (!empty($mv['branch_manager_approved_by'])): ?>
                                                                <div class="rounded-circle shadow-sm mx-auto d-flex align-items-center justify-content-center" style="width:26px;height:26px;background:#198754;color:#fff;font-size:.7rem;"><i class="fas fa-check"></i></div>
                                                                <div class="fw-bold mt-1 text-dark" style="font-size:.66rem;">Branch Mgr</div>
                                                                <div class="text-muted" style="font-size:.6rem;"><?php echo e($mv['bm_approver_name'] ?? 'Approved'); ?></div>
                                                            <?php else: ?>
                                                                <div class="rounded-circle shadow-sm mx-auto d-flex align-items-center justify-content-center" style="width:26px;height:26px;background:#e9ecef;color:#6c757d;font-size:.7rem;"><i class="fas fa-minus"></i></div>
                                                                <div class="fw-bold mt-1 text-secondary" style="font-size:.66rem;">Branch Mgr</div>
                                                                <div class="text-muted fst-italic" style="font-size:.6rem;">Bypassed / N/A</div>
                                                            <?php endif; ?>
                                                        </div>

                                                        <!-- Node 2: HR Supervisor -->
                                                        <div class="text-center position-relative" style="z-index:2;">
                                                            <?php if (!empty($mv['hr_supervisor_approved_by'])): ?>
                                                                <div class="rounded-circle shadow-sm mx-auto d-flex align-items-center justify-content-center" style="width:26px;height:26px;background:#198754;color:#fff;font-size:.7rem;"><i class="fas fa-check"></i></div>
                                                                <div class="fw-bold mt-1 text-dark" style="font-size:.66rem;">HR Sup</div>
                                                                <div class="text-muted" style="font-size:.6rem;"><?php echo e($mv['hrs_approver_name'] ?? 'Endorsed'); ?></div>
                                                            <?php elseif ($mv['portal_workflow_stage'] === 'Pending_HR_Supervisor'): ?>
                                                                <div class="rounded-circle shadow-sm mx-auto d-flex align-items-center justify-content-center pulse-animation" style="width:26px;height:26px;background:#082E06;color:#CBA135;border:2px solid #CBA135;font-size:.7rem;"><i class="fas fa-user-shield"></i></div>
                                                                <div class="fw-bold mt-1" style="color:#082E06;font-size:.66rem;">HR Sup</div>
                                                                <div class="badge rounded-pill bg-warning text-dark" style="font-size:.58rem;">Pending</div>
                                                            <?php else: ?>
                                                                <div class="rounded-circle shadow-sm mx-auto d-flex align-items-center justify-content-center" style="width:26px;height:26px;background:#e9ecef;color:#6c757d;font-size:.7rem;"><i class="fas fa-clock"></i></div>
                                                                <div class="fw-bold mt-1 text-secondary" style="font-size:.66rem;">HR Sup</div>
                                                                <div class="text-muted" style="font-size:.6rem;">Waiting</div>
                                                            <?php endif; ?>
                                                        </div>

                                                        <!-- Node 3: HR Manager -->
                                                        <div class="text-center position-relative" style="z-index:2;">
                                                            <?php if ($mv['approval_status'] === 'Approved'): ?>
                                                                <div class="rounded-circle shadow-sm mx-auto d-flex align-items-center justify-content-center" style="width:26px;height:26px;background:#198754;color:#fff;font-size:.7rem;"><i class="fas fa-check-double"></i></div>
                                                                <div class="fw-bold mt-1 text-success" style="font-size:.66rem;">HR Mgr</div>
                                                                <div class="text-success fw-semibold" style="font-size:.6rem;">Approved</div>
                                                            <?php elseif ($mv['approval_status'] === 'Rejected'): ?>
                                                                <div class="rounded-circle shadow-sm mx-auto d-flex align-items-center justify-content-center" style="width:26px;height:26px;background:#dc3545;color:#fff;font-size:.7rem;"><i class="fas fa-times"></i></div>
                                                                <div class="fw-bold mt-1 text-danger" style="font-size:.66rem;">HR Mgr</div>
                                                                <div class="text-danger fw-semibold" style="font-size:.6rem;">Rejected</div>
                                                            <?php else: ?>
                                                                <div class="rounded-circle shadow-sm mx-auto d-flex align-items-center justify-content-center" style="width:26px;height:26px;background:#e9ecef;color:#6c757d;font-size:.7rem;"><i class="fas fa-flag-checkered"></i></div>
                                                                <div class="fw-bold mt-1 text-secondary" style="font-size:.66rem;">HR Mgr</div>
                                                                <div class="text-muted" style="font-size:.6rem;">Final</div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- CREATE TAB -->
            <div class="tab-pane fade" id="supCreateTab" role="tabpanel">
                <div class="p-4">
                    <form method="POST" id="supCreateForm">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="create_movement" value="1">

                        <!-- Step 1: Target Selection -->
                        <div class="mb-4 p-3 rounded-3 sup-form-section" style="background:#fafdfa;border:1px solid rgba(8,46,6,0.08);">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <span class="badge rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm" style="width:26px;height:26px;background:#082E06;color:#CBA135;font-size:.8rem;font-weight:700;">1</span>
                                <h6 class="fw-bold mb-0 text-dark" style="letter-spacing:-0.2px;">Target Employee Selection</h6>
                            </div>
                            <div class="row g-3">
                                <!-- Department -->
                                <div class="col-lg-6">
                                    <label class="form-label fw-semibold text-secondary small">Filter Employee by Department <span class="text-muted small">(optional)</span></label>
                                    <select class="form-select shadow-sm" name="department_id" id="supDeptSelect" <?php echo !$movement_ready ? 'disabled' : ''; ?> style="border-radius:10px;border-color:#d0d7ce;">
                                        <option value="all">-- All Departments --</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo (int)$dept['department_id']; ?>">
                                                <?php echo e($dept['department_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text text-muted small"><i class="fas fa-info-circle me-1" style="color:#082E06;"></i>Select department filter or pick employee directly.</div>
                                </div>

                                <!-- Employee -->
                                <div class="col-lg-6">
                                    <label class="form-label fw-semibold text-secondary small">Select Employee <span class="text-danger">*</span></label>
                                    <select class="form-select shadow-sm" name="employee_id" id="supEmpSelect" required <?php echo !$movement_ready ? 'disabled' : ''; ?> style="border-radius:10px;border-color:#d0d7ce;">
                                        <option value="">-- Choose Employee --</option>
                                    </select>
                                </div>

                                <!-- Raquel HRIS Branded Employee Profile Card -->
                                <div class="col-12" id="supAssignmentPreview" style="display:none;">
                                    <div class="p-3.5 rounded-3 shadow-sm position-relative overflow-hidden" style="background:linear-gradient(135deg, #082E06 0%, #163e12 100%); border:1px solid rgba(203, 161, 53, 0.45);">
                                        <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                                            <div id="supEmpAvatar" style="width:54px;height:54px;border-radius:50%;background:linear-gradient(135deg,#BD9414,#f0c040);display:flex;align-items:center;justify-content:center;font-size:1.35rem;font-weight:800;color:#082E06;flex-shrink:0;box-shadow:0 0 0 3px rgba(203, 161, 53, 0.4);">?</div>
                                            <div class="flex-grow-1" style="min-width:180px;">
                                                <div class="fw-bold text-white fs-6" id="supPreviewName" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">—</div>
                                                <div class="d-flex align-items-center gap-2 mt-1">
                                                    <span class="badge" style="background:rgba(203, 161, 53, 0.22);color:#f0c040;border:1px solid rgba(203, 161, 53, 0.4);font-size:.72rem;" id="supPreviewCode">—</span>
                                                    <span class="badge" style="background:rgba(255,255,255,0.1);color:rgba(255,255,255,0.7);font-size:.68rem;"><i class="fas fa-user-check text-success me-1"></i>Active Staff</span>
                                                </div>
                                            </div>
                                            <div class="ms-auto text-end">
                                                <span class="badge rounded-pill px-3 py-1.5" style="background:rgba(255,255,255,0.12);color:#fff;border:1px solid rgba(255,255,255,0.22);font-size:.72rem;"><i class="fas fa-id-card me-1" style="color:#CBA135;"></i>Current Profile</span>
                                            </div>
                                        </div>
                                        <div class="row g-2 pt-2" style="border-top:1px solid rgba(255,255,255,0.12);">
                                            <div class="col-4">
                                                <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:rgba(255,255,255,0.6);"><i class="fas fa-sitemap me-1" style="color:#CBA135;"></i>Department</div>
                                                <div class="fw-semibold text-white small" id="supPreviewDept">—</div>
                                            </div>
                                            <div class="col-4">
                                                <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:rgba(255,255,255,0.6);"><i class="fas fa-user-tie me-1" style="color:#CBA135;"></i>Position</div>
                                                <div class="fw-semibold text-white small" id="supPreviewPos">—</div>
                                            </div>
                                            <div class="col-4">
                                                <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:rgba(255,255,255,0.6);"><i class="fas fa-store me-1" style="color:#CBA135;"></i>Branch</div>
                                                <div class="fw-semibold text-white small" id="supPreviewBranch">—</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Movement Details -->
                        <div class="mb-4 p-3 rounded-3 sup-form-section" style="background:#fafdfa;border:1px solid rgba(8,46,6,0.08);">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <span class="badge rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm" style="width:26px;height:26px;background:#082E06;color:#CBA135;font-size:.8rem;font-weight:700;">2</span>
                                <h6 class="fw-bold mb-0 text-dark" style="letter-spacing:-0.2px;">Movement Action Details</h6>
                            </div>
                            <div class="row g-3">
                                <!-- Movement Type -->
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold text-secondary small">Movement Type <span class="text-danger">*</span></label>
                                    <select class="form-select shadow-sm" name="movement_type" id="supMovTypeSelect" required <?php echo !$movement_ready ? 'disabled' : ''; ?> style="border-radius:10px;border-color:#d0d7ce;">
                                        <option value="">-- Select type --</option>
                                        <option value="Transfer">Transfer</option>
                                        <option value="Promotion">Promotion</option>
                                        <option value="Demotion">Demotion</option>
                                        <option value="Role Change">Role Change</option>
                                    </select>
                                </div>

                                <!-- Target / New Department -->
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold text-secondary small">Target Department <span class="text-danger">*</span></label>
                                    <select class="form-select shadow-sm" name="new_department_id" id="supNewDeptSelect" required <?php echo !$movement_ready ? 'disabled' : ''; ?> style="border-radius:10px;border-color:#d0d7ce;">
                                        <option value="">-- Target Department --</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo (int)$dept['department_id']; ?>">
                                                <?php echo e($dept['department_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text text-muted small"><i class="fas fa-sitemap me-1" style="color:#082E06;"></i>Employee can be moved to another department.</div>
                                </div>

                                <!-- New Position (dynamic dropdown) -->
                                <div class="col-md-3" id="supPositionWrapper">
                                    <label class="form-label fw-semibold text-secondary small" id="supPositionLabel">New Position <span class="text-danger" id="supPositionAsterisk">*</span></label>
                                    <select class="form-select shadow-sm" name="new_position" id="supPositionSelect" required disabled <?php echo !$movement_ready ? 'disabled' : ''; ?> style="border-radius:10px;border-color:#d0d7ce;">
                                        <option value="">-- Select Target Dept First --</option>
                                    </select>
                                    <div class="form-text text-muted small" id="supPositionHint">Positions for target department are shown.</div>
                                </div>

                                <!-- New Branch -->
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold text-secondary small" id="supBranchLabel">New Branch</label>
                                    <select class="form-select shadow-sm" name="new_branch_id" id="supBranchSelect" <?php echo !$movement_ready ? 'disabled' : ''; ?> style="border-radius:10px;border-color:#d0d7ce;">
                                        <option value="">No branch change</option>
                                        <?php foreach ($branches as $br): ?>
                                            <option value="<?php echo (int)$br['branch_id']; ?>"><?php echo e($br['branch_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text text-muted small" id="supBranchHint"></div>
                                </div>

                                <!-- Raquel HRIS Brand Aligned Before vs. After Impact Preview Component -->
                                <div class="col-12" id="supImpactPreview" style="display:none;">
                                    <div class="rounded-3 overflow-hidden shadow-sm" style="border:1px solid rgba(8, 46, 6, 0.18);">
                                        <div class="px-3.5 py-2.5 d-flex align-items-center justify-content-between" style="background:linear-gradient(135deg, #082E06 0%, #153e12 100%);">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="fas fa-exchange-alt" style="color:#CBA135;"></i>
                                                <span style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#ffffff;">Career Movement Impact Preview</span>
                                            </div>
                                            <span class="badge px-3 py-1 shadow-sm" id="supImpactTypeBadge" style="font-size:.7rem;border-radius:12px;"></span>
                                        </div>
                                        <div class="d-flex flex-column flex-md-row align-items-stretch">
                                            <!-- BEFORE -->
                                            <div class="p-3.5 flex-grow-1" style="min-width:0;background:#f8fcf8;border-right:1px solid rgba(0,0,0,0.06);">
                                                <div class="mb-2 d-flex align-items-center gap-1.5" style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#60735f;">
                                                    <i class="fas fa-circle me-1" style="font-size:.5rem;color:#889987;"></i>Current Status
                                                </div>
                                                <div class="fw-bold text-dark fs-6" id="supImpactOldPos">—</div>
                                                <div class="text-muted mt-1 small" id="supImpactOldBranch"><i class="fas fa-store me-1 text-muted"></i>—</div>
                                            </div>
                                            <!-- CONNECTOR ARROW -->
                                            <div class="d-flex align-items-center justify-content-center px-3 py-2" style="background:#ffffff;border-left:1px solid rgba(0,0,0,0.04);border-right:1px solid rgba(0,0,0,0.04);">
                                                <div class="shadow-sm rounded-circle d-flex align-items-center justify-content-center" style="width:38px;height:38px;background:#082E06;color:#CBA135;border:1.5px solid #CBA135;">
                                                    <i class="fas fa-arrow-right fs-6 d-none d-md-block"></i>
                                                    <i class="fas fa-arrow-down fs-6 d-block d-md-none"></i>
                                                </div>
                                            </div>
                                            <!-- AFTER -->
                                            <div class="p-3.5 flex-grow-1" style="min-width:0;background:linear-gradient(135deg,#edf7ec 0%,#f4fbf3 100%);">
                                                <div class="mb-2 d-flex align-items-center gap-1.5" style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#082E06;">
                                                    <i class="fas fa-check-circle me-1 text-success"></i>Proposed Assignment
                                                </div>
                                                <div class="fw-bold text-success fs-6" id="supImpactNewPos">—</div>
                                                <div class="text-dark fw-semibold mt-1 small" id="supImpactNewBranch"><i class="fas fa-map-marker-alt me-1 text-danger"></i>—</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Justification & Schedule -->
                        <div class="mb-4 p-3 rounded-3" style="background:#fafdfa;border:1px solid rgba(8,46,6,0.08);">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <span class="badge rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm" style="width:26px;height:26px;background:#082E06;color:#CBA135;font-size:.8rem;font-weight:700;">3</span>
                                <h6 class="fw-bold mb-0 text-dark" style="letter-spacing:-0.2px;">Schedule & Justification</h6>
                            </div>
                            <div class="row g-3">
                                <!-- Effective Date -->
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold text-secondary small">Effective Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control shadow-sm" name="effective_date" required <?php echo !$movement_ready ? 'disabled' : ''; ?> style="border-radius:10px;border-color:#d0d7ce;">
                                </div>

                                <!-- Reason -->
                                <div class="col-md-8">
                                    <label class="form-label fw-semibold text-secondary small">Reason & Justification <span class="text-danger">*</span></label>
                                    <textarea class="form-control shadow-sm" name="reason" rows="3" placeholder="Provide clear justification for this request..." required <?php echo !$movement_ready ? 'disabled' : ''; ?> style="border-radius:10px;border-color:#d0d7ce;"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex align-items-center justify-content-between pt-3 border-top pb-5">
                            <span class="text-muted small"><i class="fas fa-shield-alt text-success me-1"></i>Submits request for HR Manager review.</span>
                            <button type="submit" class="btn px-4 py-2.5 fw-semibold shadow-sm text-white" <?php echo !$movement_ready ? 'disabled' : ''; ?> style="background:linear-gradient(135deg, #082E06 0%, #163e12 100%);border:1px solid #CBA135;border-radius:10px;">
                                <i class="fas fa-paper-plane me-2" style="color:#CBA135;"></i>Submit for Review
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="supRejectModal" tabindex="-1" aria-labelledby="supRejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="supRejectModalLabel"><i class="fas fa-times-circle text-danger me-2"></i>Reject Career Movement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="movement_id" id="supRejectMvId">
                <input type="hidden" name="movement_action" value="Reject">
                <div class="modal-body">
                    <p class="mb-2">Rejecting movement for: <strong id="supRejectEmpName"></strong></p>
                    <label class="form-label fw-semibold">Reason for Rejection <span class="text-muted small">(optional)</span></label>
                    <textarea class="form-control" name="manager_comments" rows="3" placeholder="Provide a brief reason..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-times me-1"></i>Confirm Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Cancel Confirmation Modal -->
<div class="modal fade" id="supCancelModal" tabindex="-1" aria-labelledby="supCancelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0" style="background:linear-gradient(135deg,#6c1c1c,#991f1f);">
                <h5 class="modal-title text-white fw-bold" id="supCancelModalLabel">
                    <i class="fas fa-ban me-2 text-warning"></i>Cancel Career Movement
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-3">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3 shadow-sm" style="width:56px;height:56px;background:rgba(220,53,69,0.1);border:2px solid rgba(220,53,69,0.3);">
                        <i class="fas fa-exclamation-triangle text-danger" style="font-size:1.4rem;"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-1">Are you sure you want to cancel this request?</h6>
                    <p class="text-muted small mb-0">This will permanently withdraw the <strong id="supCancelMvType"></strong> request for <strong id="supCancelEmpName"></strong>. This action cannot be undone.</p>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 px-4 pb-4 d-flex gap-2">
                <button type="button" class="btn btn-light fw-semibold flex-fill" style="border-radius:8px;" data-bs-dismiss="modal">
                    <i class="fas fa-arrow-left me-1"></i>Keep Request
                </button>
                <form method="POST" class="flex-fill">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="movement_id" id="supCancelMvId">
                    <input type="hidden" name="cancel_movement" value="1">
                    <button type="submit" class="btn btn-danger fw-semibold w-100" style="border-radius:8px;">
                        <i class="fas fa-ban me-1"></i>Yes, Cancel It
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Movement Modal -->
<div class="modal fade" id="supEditModal" tabindex="-1" aria-labelledby="supEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0" style="background:linear-gradient(135deg,#082E06,#163e12);">
                <h5 class="modal-title text-white fw-bold" id="supEditModalLabel">
                    <i class="fas fa-pencil-alt me-2" style="color:#CBA135;"></i>Edit Career Movement
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="supEditForm">
                <?php echo csrfField(); ?>
                <input type="hidden" name="movement_id" id="supEditMvId">
                <input type="hidden" name="edit_movement" value="1">
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3"><i class="fas fa-user-tie me-1" style="color:#082E06;"></i>Editing request for: <strong id="supEditEmpName" class="text-dark"></strong></p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-secondary small">Movement Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="movement_type" id="supEditMovType" required style="border-radius:8px;border-color:#c8d3c5;">
                                <option value="Transfer">Transfer</option>
                                <option value="Promotion">Promotion</option>
                                <option value="Demotion">Demotion</option>
                                <option value="Role Change">Role Change</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-secondary small">New Position <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="new_position" id="supEditPosition" placeholder="e.g. Senior Sales Associate" style="border-radius:8px;border-color:#c8d3c5;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-secondary small">New Branch <span class="text-muted small">(optional)</span></label>
                            <select class="form-select" name="new_branch_id" id="supEditBranch" style="border-radius:8px;border-color:#c8d3c5;">
                                <option value="">No branch change</option>
                                <?php foreach ($branches as $br): ?>
                                    <option value="<?php echo (int)$br['branch_id']; ?>"><?php echo e($br['branch_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-secondary small">Effective Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="effective_date" id="supEditDate" required style="border-radius:8px;border-color:#c8d3c5;">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold text-secondary small">Reason &amp; Justification <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="reason" id="supEditReason" rows="3" placeholder="Provide clear justification for this request..." required style="border-radius:8px;border-color:#c8d3c5;"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4" style="background:#f8fbf7;">
                    <button type="button" class="btn btn-light fw-semibold" style="border-radius:8px;" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Discard Changes
                    </button>
                    <button type="submit" class="btn fw-semibold text-white px-4" style="background:linear-gradient(135deg,#082E06,#163e12);border:1px solid #CBA135;border-radius:8px;">
                        <i class="fas fa-save me-1" style="color:#CBA135;"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

</div><!-- /.career-movements-page -->

<script>
document.addEventListener('DOMContentLoaded', function () {
    // ── Data from PHP ─────────────────────────────────────────────────────────
    const branchNames    = <?php echo json_encode($branch_names, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_QUOT); ?>;
    const deptEmployees  = <?php echo json_encode($dept_employees, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_QUOT); ?>;
    const deptPositions  = <?php echo json_encode($dept_positions, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_QUOT); ?>;
    const allEmployees   = <?php echo json_encode($all_employees, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_QUOT); ?>;

    // ── Elements ──────────────────────────────────────────────────────────────
    const deptSel    = document.getElementById('supDeptSelect');
    const empSel     = document.getElementById('supEmpSelect');
    const newDeptSel = document.getElementById('supNewDeptSelect');
    const posSel     = document.getElementById('supPositionSelect');
    const preview    = document.getElementById('supAssignmentPreview');
    const prevDept   = document.getElementById('supPreviewDept');
    const prevPos    = document.getElementById('supPreviewPos');
    const prevBranch = document.getElementById('supPreviewBranch');

    // ── Rank calculation helper for filtering Demotion / Promotion ─────────────
    function parseSubRankScore(title) {
        if (!title) return 1;
        const t = title.trim();
        if (/probation|training|trainee/i.test(t)) return 0;

        const romanMatch = t.match(/\s+(VII|VI|V|IV|III|II|I)$/i);
        if (romanMatch) {
            const r = romanMatch[1].toUpperCase();
            const map = { 'VII': 7, 'VI': 6, 'V': 5, 'IV': 4, 'III': 3, 'II': 2, 'I': 1 };
            return map[r] || 1;
        }

        const numMatch = t.match(/\s+(\d+)$/);
        if (numMatch) {
            return parseInt(numMatch[1], 10);
        }

        return 1;
    }

    function getPositionRankScore(title, levelOrder) {
        const lOrder = parseInt(levelOrder, 10) || 5;
        // 1 = Executive, 2 = Mgmt, 3 = Manager, 4 = Supervisor, 5 = R&F
        const categoryWeight = (10 - lOrder) * 100;
        const subRank = parseSubRankScore(title);
        return categoryWeight + subRank;
    }

    function updateFilteredPositions() {
        const targetDid = (newDeptSel && newDeptSel.value) ? newDeptSel.value : (deptSel ? deptSel.value : '');
        if (!targetDid || targetDid === 'all') {
            posSel.innerHTML = '<option value="">-- Select Target Dept First --</option>';
            posSel.disabled = true;
            return;
        }

        const rawPositions = deptPositions[targetDid] || [];
        if (rawPositions.length === 0) {
            posSel.innerHTML = '<option value="">No positions defined for target department</option>';
            posSel.disabled = true;
            return;
        }

        const movType  = movTypeSel ? movTypeSel.value : '';
        const empOpt   = empSel.options[empSel.selectedIndex];
        const empTitle = (empOpt && empOpt.value) ? (empOpt.dataset.jobtitle || '') : '';
        const empLOrder= (empOpt && empOpt.value) ? (empOpt.dataset.levelorder || 5) : 5;

        const empScore = (empOpt && empOpt.value) ? getPositionRankScore(empTitle, empLOrder) : null;
        const prevSelectedVal = posSel.value;

        let validPositions = rawPositions.filter(pos => {
            if (!empScore || !movType) return true;
            const pScore = getPositionRankScore(pos.title, pos.level_order);
            if (movType === 'Demotion') {
                return pScore < empScore; // Demotion: ONLY LOWER RANKS
            }
            if (movType === 'Promotion') {
                return pScore > empScore; // Promotion: ONLY HIGHER RANKS
            }
            return true;
        });

        posSel.innerHTML = '<option value="">-- Select New Position --</option>';

        if (validPositions.length === 0) {
            if (movType === 'Demotion') {
                posSel.innerHTML = '<option value="">No lower rank positions available in target department</option>';
            } else if (movType === 'Promotion') {
                posSel.innerHTML = '<option value="">No higher rank positions available in target department</option>';
            } else {
                posSel.innerHTML = '<option value="">No valid positions found</option>';
            }
            posSel.disabled = true;
            return;
        }

        let isStillValid = false;
        validPositions.forEach(pos => {
            const opt = document.createElement('option');
            opt.value = pos.title;
            opt.textContent = pos.title;
            if (pos.title === prevSelectedVal) {
                opt.selected = true;
                isStillValid = true;
            }
            posSel.appendChild(opt);
        });

        if (!isStillValid && prevSelectedVal) {
            posSel.value = '';
        }

        posSel.disabled = false;
    }

    // ── Department filter change → load employees ────────────────────────────
    deptSel.addEventListener('change', function () {
        const did = this.value;

        empSel.innerHTML = '<option value="">-- Choose Employee --</option>';
        preview.style.display = 'none';

        let empsToRender = [];
        if (!did || did === 'all') {
            Object.keys(deptEmployees).forEach(dId => {
                empsToRender = empsToRender.concat(deptEmployees[dId] || []);
            });
            empsToRender.sort((a, b) => (a.last_name + a.first_name).localeCompare(b.last_name + b.first_name));
        } else {
            empsToRender = deptEmployees[did] || [];
        }

        if (empsToRender.length === 0) {
            empSel.innerHTML = '<option value="">No employees found</option>';
            empSel.disabled  = true;
        } else {
            empsToRender.forEach(function (emp) {
                const opt = document.createElement('option');
                opt.value              = emp.employee_id;
                const rank             = emp.rank_name ? ' [' + emp.rank_name + ']' : '';
                const deptBadge        = (!did || did === 'all') ? ' (' + (emp.department_name || 'Dept #' + emp.department_id) + ')' : '';
                opt.textContent        = emp.last_name + ', ' + emp.first_name + rank + deptBadge + ' – ' + (emp.employee_code || emp.employee_id);
                opt.dataset.jobtitle   = emp.job_title || '';
                opt.dataset.levelorder = emp.level_order || 5;
                opt.dataset.deptid     = emp.department_id || '';
                opt.dataset.branch     = emp.branch_id || '';
                opt.dataset.branchname = emp.branch_name || '';
                opt.dataset.deptname   = emp.department_name || '';
                empSel.appendChild(opt);
            });
            empSel.disabled = false;
        }

        updateFilteredPositions();
    });

    // ── Avatar color palette (initials-based) ─────────────────────────────────
    const avatarGradients = [
        ['#BD9414','#f0c040'], ['#1565c0','#42a5f5'], ['#6a1b9a','#ab47bc'],
        ['#b71c1c','#ef5350'], ['#2e7d32','#66bb6a'], ['#e65100','#ffa726'],
        ['#00695c','#26a69a'], ['#4527a0','#7c4dff']
    ];
    function empAvatarGradient(name) {
        if (!name) return ['#BD9414','#f0c040'];
        const code = name.charCodeAt(0) % avatarGradients.length;
        return avatarGradients[code];
    }

    // ── Employee change → preview card & set target department ────────────────
    const empAvatarEl   = document.getElementById('supEmpAvatar');
    const empNameEl     = document.getElementById('supPreviewName');
    const empCodeEl     = document.getElementById('supPreviewCode');

    empSel.addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        if (!opt.value) {
            preview.style.display = 'none';
            updateFilteredPositions();
            updateImpactPreview();
            return;
        }
        // Build initials
        const parts    = opt.textContent.split('–')[0].trim().split(',');
        const lastName = parts[0] ? parts[0].trim() : '';
        const firstName= parts[1] ? parts[1].trim().split(' ')[0] : '';
        const initials = (lastName.charAt(0) + (firstName.charAt(0)||'')).toUpperCase() || '?';
        const [c1, c2] = empAvatarGradient(lastName);
        if (empAvatarEl) {
            empAvatarEl.textContent = initials;
            empAvatarEl.style.background = `linear-gradient(135deg,${c1},${c2})`;
        }
        // Full name display
        if (empNameEl) empNameEl.textContent = (lastName && firstName) ? `${firstName} ${lastName}` : opt.textContent.split('–')[0].trim();
        // Code (after the dash)
        const codePart = opt.textContent.split('–')[1];
        if (empCodeEl) empCodeEl.textContent = codePart ? codePart.trim() : (opt.dataset.jobtitle || '');

        prevDept.textContent   = opt.dataset.deptname   || '—';
        prevPos.textContent    = opt.dataset.jobtitle   || '—';
        prevBranch.textContent = opt.dataset.branchname || branchNames[opt.dataset.branch] || '—';
        preview.style.display  = 'block';

        // Default Target Department in Step 2 to match employee's current department
        if (newDeptSel && opt.dataset.deptid) {
            newDeptSel.value = opt.dataset.deptid;
        }

        updateFilteredPositions();
        updateImpactPreview();
    });

    // ── Target Department change handler ──────────────────────────────────────
    if (newDeptSel) {
        newDeptSel.addEventListener('change', function() {
            updateFilteredPositions();
            updateImpactPreview();
        });
    }

    // ── Movement Type change → toggle Position required / Branch required ──────
    const movTypeSel     = document.getElementById('supMovTypeSelect');
    const posAsterisk    = document.getElementById('supPositionAsterisk');
    const posHint        = document.getElementById('supPositionHint');
    const branchLabel    = document.getElementById('supBranchLabel');
    const branchHint     = document.getElementById('supBranchHint');
    const branchSel      = document.getElementById('supBranchSelect');

    function applyMovTypeRules() {
        const isTransfer = movTypeSel.value === 'Transfer';

        // New Position: optional for Transfer, required for everything else
        if (isTransfer) {
            posSel.removeAttribute('required');
            posAsterisk.style.display = 'none';
            posHint.textContent = 'Optional for Transfer — leave blank to keep current position.';
        } else {
            // Only mark required if it isn't still disabled (i.e. dept was already picked)
            if (!posSel.disabled || posSel.options.length > 1) {
                posSel.setAttribute('required', 'required');
            }
            posAsterisk.style.display = '';
            posHint.textContent = 'Positions for target department are shown.';
        }

        // New Branch: required for Transfer
        if (isTransfer) {
            branchSel.setAttribute('required', 'required');
            branchLabel.innerHTML = 'New Branch <span class="text-danger">*</span>';
            branchHint.textContent = 'Branch is required for a Transfer.';
        } else {
            branchSel.removeAttribute('required');
            branchLabel.innerHTML = 'New Branch';
            branchHint.textContent = '';
        }
    }

    if (movTypeSel) {
        movTypeSel.addEventListener('change', function() {
            applyMovTypeRules();
            updateFilteredPositions();
            updateImpactPreview();
        });
    }

    // ── Before vs. After impact preview ──────────────────────────────────────
    const impactPanel    = document.getElementById('supImpactPreview');
    const impactOldPos   = document.getElementById('supImpactOldPos');
    const impactOldBr    = document.getElementById('supImpactOldBranch');
    const impactNewPos   = document.getElementById('supImpactNewPos');
    const impactNewBr    = document.getElementById('supImpactNewBranch');
    const impactTypeBadge= document.getElementById('supImpactTypeBadge');

    const movTypeBadgeStyles = {
        'Promotion'  : 'background:rgba(40,167,69,0.22);color:#2ebd59;border:1px solid rgba(40,167,69,0.45);font-weight:700;',
        'Transfer'   : 'background:rgba(13,202,240,0.22);color:#2cd5f6;border:1px solid rgba(13,202,240,0.45);font-weight:700;',
        'Demotion'   : 'background:rgba(220,53,69,0.22);color:#ff7878;border:1px solid rgba(220,53,69,0.45);font-weight:700;',
        'Role Change': 'background:rgba(203,161,53,0.25);color:#f0c040;border:1px solid rgba(203,161,53,0.5);font-weight:700;',
    };

    function updateImpactPreview() {
        const empOpt = empSel.options[empSel.selectedIndex];
        const movType = movTypeSel ? movTypeSel.value : '';
        const newPos  = posSel  ? posSel.value  : '';
        const newBrId = branchSel ? branchSel.value : '';
        const newDeptOpt = newDeptSel ? newDeptSel.options[newDeptSel.selectedIndex] : null;

        // Only show panel when an employee is selected AND at least one change is selected
        if (!empOpt || !empOpt.value || !movType || (!newPos && !newBrId && (!newDeptOpt || !newDeptOpt.value))) {
            if (impactPanel) impactPanel.style.display = 'none';
            return;
        }

        const currentPos    = empOpt.dataset.jobtitle   || '—';
        const currentBranch = empOpt.dataset.branchname || branchNames[empOpt.dataset.branch] || '—';
        const currentDept   = empOpt.dataset.deptname   || '—';

        const targetDeptName = (newDeptOpt && newDeptOpt.value) ? newDeptOpt.textContent.trim() : currentDept;
        const isCrossDept    = newDeptOpt && newDeptOpt.value && (String(newDeptOpt.value) !== String(empOpt.dataset.deptid));

        const afterPos      = newPos   || currentPos;
        const afterBranch   = newBrId  ? (branchNames[newBrId] || 'Branch #' + newBrId) : currentBranch;

        if (impactOldPos)  impactOldPos.textContent  = currentPos;
        if (impactOldBr)   impactOldBr.innerHTML   = `<i class="fas fa-store me-1 text-muted"></i>${currentBranch} <span class="text-muted small">(${currentDept})</span>`;
        if (impactNewPos)  impactNewPos.textContent  = afterPos;
        if (impactNewBr) {
            const crossBadge = isCrossDept ? ` <span class="badge bg-warning text-dark ms-1" style="font-size:.65rem;"><i class="fas fa-random me-1"></i>Cross-Dept</span>` : '';
            impactNewBr.innerHTML = `<i class="fas fa-map-marker-alt me-1 text-danger"></i>${afterBranch} <span class="text-muted small">(${targetDeptName})</span>${crossBadge}`;
        }

        if (impactTypeBadge) {
            const style = movTypeBadgeStyles[movType] || 'background:#e2e3e5;color:#383d41;border:1px solid #d6d8db;';
            impactTypeBadge.setAttribute('style', 'font-size:.68rem;' + style);
            impactTypeBadge.textContent = movType;
        }

        if (impactPanel) impactPanel.style.display = 'block';
    }

    // Wire position and branch changes to impact preview
    if (posSel)    posSel.addEventListener('change',    updateImpactPreview);
    if (branchSel) branchSel.addEventListener('change', updateImpactPreview);

    // ── Search ────────────────────────────────────────────────────────────────
    const searchInput = document.getElementById('supMovSearch');
    const tableRows   = document.querySelectorAll('#supMovTable tbody tr');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const term = this.value.trim().toLowerCase();
            tableRows.forEach(r => { r.style.display = r.textContent.toLowerCase().includes(term) ? '' : 'none'; });
            if (typeof applyZebraStriping === 'function') applyZebraStriping('#supMovTable');
        });
    }

    // ── Reject Modal ──────────────────────────────────────────────────────────
    const rejectModal = document.getElementById('supRejectModal');
    if (rejectModal) {
        rejectModal.addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget;
            document.getElementById('supRejectMvId').value          = btn.dataset.mvid;
            document.getElementById('supRejectEmpName').textContent = btn.dataset.empname;
        });
    }

    // ── Cancel Modal ──────────────────────────────────────────────────────────
    const cancelModal = document.getElementById('supCancelModal');
    if (cancelModal) {
        cancelModal.addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget;
            document.getElementById('supCancelMvId').value          = btn.dataset.mvid;
            document.getElementById('supCancelEmpName').textContent = btn.dataset.empname;
            document.getElementById('supCancelMvType').textContent  = btn.dataset.type;
        });
    }

    // ── Edit Modal ────────────────────────────────────────────────────────────
    const editModal = document.getElementById('supEditModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget;
            document.getElementById('supEditMvId').value         = btn.dataset.mvid;
            document.getElementById('supEditEmpName').textContent= btn.dataset.empname;
            document.getElementById('supEditPosition').value     = btn.dataset.position || '';
            document.getElementById('supEditDate').value         = btn.dataset.date || '';
            document.getElementById('supEditReason').value       = btn.dataset.reason || '';

            const movTypeSel = document.getElementById('supEditMovType');
            if (movTypeSel) movTypeSel.value = btn.dataset.type || '';

            const branchSel = document.getElementById('supEditBranch');
            if (branchSel) branchSel.value = btn.dataset.branchid && btn.dataset.branchid !== '0' ? btn.dataset.branchid : '';
        });
    }

    // ── Pre-select employee from career-progression.php deep-link ─────────────
    // URL: ?new_movement=1&emp_id=123
    const urlParams   = new URLSearchParams(window.location.search);
    const preEmpId    = urlParams.get('emp_id');
    const preNewMov   = urlParams.get('new_movement');

    if (preNewMov === '1' && preEmpId && allEmployees[preEmpId]) {
        const emp = allEmployees[preEmpId];

        // 1. Switch to the Create tab
        const createTabBtn = document.getElementById('supCreateTabBtn');
        if (createTabBtn) {
            bootstrap.Tab.getOrCreateInstance(createTabBtn).show();
        }

        // 2. Select the correct department (triggers cascade)
        if (emp.department_id && deptSel) {
            deptSel.value = emp.department_id;
            deptSel.dispatchEvent(new Event('change'));

            // 3. After cascade populates employees, select this employee
            // Use a short delay so the options are rendered first
            setTimeout(function () {
                if (empSel) {
                    empSel.value = preEmpId;
                    empSel.dispatchEvent(new Event('change'));
                }
                // Clean up the URL so a page refresh doesn't re-trigger
                const cleanUrl = window.location.pathname;
                window.history.replaceState({}, '', cleanUrl);
            }, 50);
        }
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
