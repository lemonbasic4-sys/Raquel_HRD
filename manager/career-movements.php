<?php
$page_title = 'Career Movements';
require_once '../includes/session-check.php';
checkRole(['HR Manager']);
require_once '../includes/functions.php';

$movement_ready  = ensureCareerProgressionMovements($conn);
if ($movement_ready) {
    applyDueCareerProgressionMovements($conn);
}
$current_user_id = (int) ($_SESSION['user_id'] ?? 0);

// ── POST: Approve / Reject ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['movement_action'])) {
    verifyCsrfToken();
    if (!$movement_ready) {
        redirectWith(BASE_URL . '/manager/career-movements.php', 'danger', 'Career Movements could not be initialized.');
    }
    $movement_id = (int) ($_POST['movement_id'] ?? 0);
    $action      = trim($_POST['movement_action'] ?? '');

    if ($movement_id <= 0 || !in_array($action, ['Approve', 'Reject'], true)) {
        redirectWith(BASE_URL . '/manager/career-movements.php', 'danger', 'Invalid career movement request.');
    }

    // Task 8.1: Extend pending queue to include Portal_Requests at Pending_HR_Manager
    $stmt = $conn->prepare("
        SELECT cm.*, CONCAT(e.first_name,' ',e.last_name) AS employee_name
        FROM career_movements cm
        JOIN employees e ON cm.employee_id = e.employee_id
        WHERE cm.movement_id = ?
          AND (
            (cm.approval_status = 'Pending' AND cm.portal_workflow_stage IS NULL)
            OR
            (cm.request_source = 'Employee Portal' AND cm.portal_workflow_stage = 'Pending_HR_Manager')
          )
        LIMIT 1
    ");
    $stmt->bind_param("i", $movement_id);
    $stmt->execute();
    $movement = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$movement) {
        redirectWith(BASE_URL . '/manager/career-movements.php', 'danger', 'Career movement not found or already processed.');
    }

    $target_user_stmt = $conn->prepare("SELECT user_id FROM users WHERE employee_id = ? AND user_id = ? LIMIT 1");
    $target_user_stmt->bind_param("ii", $movement['employee_id'], $current_user_id);
    $target_user_stmt->execute();
    $is_self_movement = (bool)$target_user_stmt->get_result()->fetch_assoc();
    $target_user_stmt->close();
    if ($is_self_movement) {
        redirectWith(BASE_URL . '/manager/career-movements.php', 'danger', 'You cannot approve or reject a career movement for your own employee record.');
    }

    // Task 8.1: Detect whether this is a Portal_Request
    $is_portal_request = (
        ($movement['request_source'] ?? '') === 'Employee Portal' &&
        ($movement['portal_workflow_stage'] ?? null) !== null
    );

    // Task 8.2 & 8.3: Differentiated approve/reject for Portal_Requests vs HR Portal requests
    if ($is_portal_request) {
        // ── Portal_Request (Employee Portal, at Pending_HR_Manager) ─────────
        if (!checkApprovalAuthorization($movement, $current_user_id, 0, 'HR Manager', 'Pending_HR_Manager')) {
            redirectWith(BASE_URL . '/manager/career-movements.php', 'danger', 'You are not authorized to approve this request.');
        }

        $comments = trim($_POST['manager_comments'] ?? '');

        if ($action === 'Approve') {
            // Task 8.2: Portal_Request Approve
            $upd = $conn->prepare("
                UPDATE career_movements
                SET portal_workflow_stage = 'Approved',
                    approval_status       = 'Approved',
                    approved_by           = ?,
                    decision_date         = NOW(),
                    manager_comments      = ?,
                    is_applied            = 0
                WHERE movement_id = ?
            ");
            $upd->bind_param("isi", $current_user_id, $comments, $movement_id);
            $upd->execute();
            $upd->close();

            if ($movement['effective_date'] <= date('Y-m-d')) {
                executeCareerMovementApplication($conn, $movement, $movement_id);
            }

            // Notify submitting Branch Supervisor directly via logged_by (already a user_id)
            $logged_by = (int) $movement['logged_by'];
            if ($logged_by > 0) {
                createNotification($conn, $logged_by,
                    'Transfer Request Fully Approved',
                    "Your Transfer request for {$movement['employee_name']} has been fully approved.",
                    BASE_URL . '/employee/dashboard.php');
            }

            logAudit($conn, $current_user_id, 'APPROVE', 'Career Movement', $movement_id,
                "Approved Transfer (Portal Request) for {$movement['employee_name']}.");

            redirectWith(BASE_URL . '/manager/career-movements.php', 'success', 'Transfer request fully approved.');

        } else {
            // Task 8.3: Portal_Request Reject
            $upd = $conn->prepare("
                UPDATE career_movements
                SET portal_workflow_stage = 'Rejected',
                    approval_status       = 'Rejected',
                    approved_by           = ?,
                    decision_date         = NOW(),
                    manager_comments      = ?
                WHERE movement_id = ?
            ");
            $upd->bind_param("isi", $current_user_id, $comments, $movement_id);
            $upd->execute();
            $upd->close();

            // Notify submitting Branch Supervisor
            $logged_by = (int) $movement['logged_by'];
            if ($logged_by > 0) {
                createNotification($conn, $logged_by,
                    'Transfer Request Rejected',
                    "Your Transfer request for {$movement['employee_name']} has been rejected by HR Manager. Reason: {$comments}",
                    BASE_URL . '/employee/dashboard.php');
            }

            logAudit($conn, $current_user_id, 'REJECT', 'Career Movement', $movement_id,
                "Rejected Transfer (Portal Request) for {$movement['employee_name']}.");

            redirectWith(BASE_URL . '/manager/career-movements.php', 'success', 'Transfer request rejected.');
        }

    } else {
        // ── HR Portal request — existing flow unchanged ────────────────────
        $status   = $action === 'Approve' ? 'Approved' : 'Rejected';
        $comments = trim($_POST['manager_comments'] ?? '');

        $upd = $conn->prepare("UPDATE career_movements SET approval_status=?, approved_by=?, decision_date=NOW(), manager_comments=?, is_applied=0 WHERE movement_id=?");
        $upd->bind_param("sisi", $status, $current_user_id, $comments, $movement_id);
        $upd->execute();
        $upd->close();

        if ($status === 'Approved' && $movement['effective_date'] <= date('Y-m-d')) {
            executeCareerMovementApplication($conn, $movement, $movement_id);
        }

        if (!empty($movement['logged_by'])) {
            createNotification($conn, (int) $movement['logged_by'],
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

        logAudit($conn, $current_user_id, strtoupper($action), 'Career Movement', $movement_id,
            "{$action}d {$movement['movement_type']} for {$movement['employee_name']}.");

        $msg = $status === 'Approved' ? 'Career movement approved. It will apply on the effective date.' : 'Career movement rejected.';
        redirectWith(BASE_URL . '/manager/career-movements.php', $status === 'Approved' ? 'success' : 'warning', $msg);
    }
}

// ── Fetch data ────────────────────────────────────────────────────────────────
require_once '../includes/header.php';

$movements = [];
$counts    = ['total' => 0, 'Pending' => 0, 'Approved' => 0, 'Rejected' => 0, 'Applied' => 0];

if ($movement_ready) {
    // Task 8.4: Add JOINs for BM and HR Supervisor approver names (approval chain history)
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
            hrs_u.full_name AS hrs_approver_name,
            cm.branch_manager_decision_date,
            cm.hr_supervisor_decision_date,
            cm.branch_manager_approved_by,
            cm.hr_supervisor_approved_by
        FROM career_movements cm
        JOIN employees e        ON cm.employee_id         = e.employee_id
        LEFT JOIN departments d ON e.department_id        = d.department_id
        LEFT JOIN branches pb   ON cm.previous_branch_id  = pb.branch_id
        LEFT JOIN branches nb   ON cm.new_branch_id       = nb.branch_id
        LEFT JOIN users u1      ON cm.logged_by           = u1.user_id
        LEFT JOIN users u2      ON cm.approved_by         = u2.user_id
        LEFT JOIN users bm_u    ON cm.branch_manager_approved_by   = bm_u.user_id
        LEFT JOIN users hrs_u   ON cm.hr_supervisor_approved_by    = hrs_u.user_id
        ORDER BY FIELD(cm.approval_status,'Pending','Approved','Rejected'), cm.created_at DESC
    ");
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $movements[] = $row;
        $counts['total']++;
        if (isset($counts[$row['approval_status']])) $counts[$row['approval_status']]++;
        if ((int) ($row['is_applied'] ?? 0) === 1) $counts['Applied']++;
    }
    $stmt->close();
}

// Filter movements that specifically require HR Manager decision
$pending_movements  = array_filter($movements, function($m) {
    if (($m['request_source'] ?? '') === 'Employee Portal' && !empty($m['portal_workflow_stage'])) {
        return $m['portal_workflow_stage'] === 'Pending_HR_Manager';
    }
    return $m['approval_status'] === 'Pending' && empty($m['portal_workflow_stage']);
});
$pending_count_for_mgr = count($pending_movements);

function mgrCmTypeClass($t)  { return match($t) { 'Promotion' => 'bg-success', 'Transfer' => 'bg-info text-dark', 'Demotion' => 'bg-danger', 'Role Change' => 'bg-primary', default => 'bg-secondary' }; }
function mgrCmStatusClass($s){ return match($s) { 'Approved' => 'bg-success', 'Rejected' => 'bg-danger', default => 'bg-warning text-dark' }; }
?>

<div class="page-hero fadeup">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
        <div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">HR Manager &middot; Career</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-route me-2" style="color:#BD9414;"></i>Career Movements</h4>
            <p class="text-white-50 small mb-0 mt-2">Review and decide on employee promotions, transfers, role changes, and other career progression requests.</p>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-value"><?php echo $counts['total']; ?></div><div class="stat-label">Total</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-value" style="<?php echo $pending_count_for_mgr > 0 ? 'color:#ffc107;' : ''; ?>"><?php echo $pending_count_for_mgr; ?></div><div class="stat-label">Pending Approval</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-value" style="color:#28a745;"><?php echo $counts['Approved']; ?></div><div class="stat-label">Approved</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-value"><?php echo $counts['Applied']; ?></div><div class="stat-label">Applied</div></div></div>
    </div>
</div>

<?php if (!$movement_ready): ?>
    <div class="alert alert-danger">Career Movements could not be initialized. Please check the database.</div>
<?php endif; ?>

<div class="chart-card fadeup shadow-sm border-0 rounded-3 overflow-hidden">
    <div class="cc-header d-flex flex-wrap align-items-center justify-content-between gap-3 p-3" style="background:#ffffff;border-bottom:2px solid #082E06;">
        <ul class="nav nav-tabs cc-header-tabs border-0" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-bold px-3 py-2 border-0" data-bs-toggle="tab" data-bs-target="#mgrAllTab" type="button" role="tab" style="border-radius:8px;font-size:.85rem;">
                    <i class="fas fa-list me-2" style="color:#082E06;"></i>All Movements
                    <span class="badge rounded-pill ms-2" style="background:#082E06;color:#CBA135;"><?php echo count($movements); ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold px-3 py-2 border-0" data-bs-toggle="tab" data-bs-target="#mgrPendingTab" type="button" role="tab" style="border-radius:8px;font-size:.85rem;">
                    <i class="fas fa-clock me-2" style="color:#CBA135;"></i>Pending Approval
                    <?php if ($pending_count_for_mgr > 0): ?>
                        <span class="badge rounded-pill bg-warning text-dark ms-2"><?php echo $pending_count_for_mgr; ?></span>
                    <?php endif; ?>
                </button>
            </li>
        </ul>
        <div class="search-box position-relative" style="min-width:260px;">
            <i class="fas fa-search search-icon text-muted" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:.82rem;"></i>
            <input type="text" class="form-control form-control-sm ps-4" id="mgrMovSearch" placeholder="Search employee, position, status..." style="border-radius:20px;border-color:rgba(203,161,53,0.4);font-size:.82rem;">
        </div>
    </div>

    <div class="cc-body p-0">
        <div class="tab-content">

            <!-- ALL MOVEMENTS TAB -->
            <div class="tab-pane fade show active" id="mgrAllTab" role="tabpanel">
                <div class="table-responsive" style="overflow-x:auto;">
                    <table class="table align-middle mb-0" id="mgrMovTable" style="font-size:.84rem;width:100%;table-layout:auto;">
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
                                    $is_portal_req  = (($mv['request_source'] ?? '') === 'Employee Portal' && !empty($mv['portal_workflow_stage']));
                                    $is_hr_staff_req = ($mv['request_source'] === 'HR Portal' && ($mv['initiated_by_role'] ?? '') === 'HR Staff');
                                    $can_mgr_act    = ($mv['approval_status'] === 'Pending' && (empty($mv['portal_workflow_stage']) || $mv['portal_workflow_stage'] === 'Pending_HR_Manager'));

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
                                                <span class="badge rounded-pill bg-primary text-white px-2.5 py-1" style="font-size:.7rem;"><i class="fas fa-user-shield me-1"></i>Pending HR Sup</span>
                                            <?php elseif ($p_stage === 'Pending_HR_Manager'): ?>
                                                <span class="badge rounded-pill px-2.5 py-1 text-white shadow-sm" style="background:linear-gradient(135deg, #082E06, #163e12);border:1px solid #CBA135;font-size:.7rem;"><i class="fas fa-clock me-1" style="color:#CBA135;"></i>Pending HR Mgr</span>
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
                                            <span class="badge rounded-pill <?php echo mgrCmStatusClass($mv['approval_status']); ?> px-2.5 py-1" style="font-size:.7rem;"><?php echo e($mv['approval_status']); ?></span>
                                            <?php if ($mv['approval_status']==='Approved' && (int)($mv['is_applied']??0)===1): ?>
                                                <span class="badge rounded-pill bg-success ms-1" style="font-size:.62rem;">Applied</span>
                                            <?php elseif ($mv['approval_status']==='Approved'): ?>
                                                <span class="badge rounded-pill bg-secondary ms-1" style="font-size:.62rem;">Scheduled</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Column 5: Actions -->
                                    <td class="py-3 text-end pe-3">
                                        <?php if ($can_mgr_act): ?>
                                            <div class="d-inline-flex gap-1">
                                                <form method="POST" class="d-inline">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="movement_id" value="<?php echo (int)$mv['movement_id']; ?>">
                                                    <input type="hidden" name="movement_action" value="Approve">
                                                    <button type="submit" class="btn btn-sm text-white fw-bold px-2.5 shadow-sm" style="background:linear-gradient(135deg, #082E06 0%, #163e12 100%);border:1px solid #CBA135;border-radius:6px;font-size:.72rem;" onclick="return confirm('Are you sure you want to approve the career movement for <?php echo e(addslashes($mv['employee_name'])); ?>? This approval cannot be undone. The movement will be applied on the effective date, and the employee\\'s linked accounts may be placed on hold for Admin credential review.');">
                                                        <i class="fas fa-check me-1" style="color:#CBA135;"></i>Approve
                                                    </button>
                                                </form>
                                                <button class="btn btn-sm btn-outline-danger fw-semibold px-2" style="border-radius:6px;font-size:.72rem;"
                                                    data-bs-toggle="modal" data-bs-target="#mgrRejectModal"
                                                    data-mvid="<?php echo (int)$mv['movement_id']; ?>"
                                                    data-empname="<?php echo e($mv['employee_name']); ?>">
                                                    <i class="fas fa-times me-1"></i>Reject
                                                </button>
                                            </div>
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
                                                                <div class="rounded-circle shadow-sm mx-auto d-flex align-items-center justify-content-center" style="width:26px;height:26px;background:#ffc107;color:#212529;font-size:.7rem;"><i class="fas fa-clock"></i></div>
                                                                <div class="fw-bold mt-1 text-secondary" style="font-size:.66rem;">HR Sup</div>
                                                                <div class="text-muted" style="font-size:.6rem;">Pending</div>
                                                            <?php else: ?>
                                                                <div class="rounded-circle shadow-sm mx-auto d-flex align-items-center justify-content-center" style="width:26px;height:26px;background:#e9ecef;color:#6c757d;font-size:.7rem;"><i class="fas fa-clock"></i></div>
                                                                <div class="fw-bold mt-1 text-secondary" style="font-size:.66rem;">HR Sup</div>
                                                                <div class="text-muted" style="font-size:.6rem;">Completed</div>
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
                                                                <div class="rounded-circle shadow-sm mx-auto d-flex align-items-center justify-content-center pulse-animation" style="width:26px;height:26px;background:#082E06;color:#CBA135;border:2px solid #CBA135;font-size:.7rem;"><i class="fas fa-user-check"></i></div>
                                                                <div class="fw-bold mt-1" style="color:#082E06;font-size:.66rem;">HR Mgr</div>
                                                                <div class="badge rounded-pill bg-warning text-dark" style="font-size:.58rem;">Pending Action</div>
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

            <!-- PENDING TAB -->
            <div class="tab-pane fade" id="mgrPendingTab" role="tabpanel">
                <div class="table-responsive" style="overflow-x:auto;">
                    <table class="table align-middle mb-0" id="mgrPendingTable" style="font-size:.84rem;width:100%;table-layout:auto;">
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
                            <?php if (empty($pending_movements)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="fas fa-check-circle d-block mb-3" style="font-size:2.5rem;color:#198754;opacity:.3;"></i>
                                        <div class="fw-bold fs-6 text-dark mb-1">No Pending Approval Requests</div>
                                        <p class="mb-0 small text-muted">All career movement requisitions have been processed.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pending_movements as $mv):
                                    $is_hr_staff_req = ($mv['request_source'] === 'HR Portal' && ($mv['initiated_by_role'] ?? '') === 'HR Staff');

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
                                        <span class="badge rounded-pill px-2.5 py-1 text-white shadow-sm" style="background:linear-gradient(135deg, #082E06, #163e12);border:1px solid #CBA135;font-size:.7rem;">
                                            <i class="fas fa-clock me-1" style="color:#CBA135;"></i>Pending Your Approval
                                        </span>
                                    </td>

                                    <!-- Column 5: Actions -->
                                    <td class="py-3 text-end pe-3">
                                        <div class="d-inline-flex gap-1">
                                            <form method="POST" class="d-inline">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="movement_id" value="<?php echo (int) $mv['movement_id']; ?>">
                                                <input type="hidden" name="movement_action" value="Approve">
                                                <button type="submit" class="btn btn-sm text-white fw-bold px-2.5 shadow-sm" style="background:linear-gradient(135deg, #082E06 0%, #163e12 100%);border:1px solid #CBA135;border-radius:6px;font-size:.72rem;" onclick="return confirm('Approve this career movement request?');">
                                                    <i class="fas fa-check me-1" style="color:#CBA135;"></i>Approve
                                                </button>
                                            </form>
                                            <button class="btn btn-sm btn-outline-danger fw-semibold px-2" style="border-radius:6px;font-size:.72rem;"
                                                data-bs-toggle="modal" data-bs-target="#mgrRejectModal"
                                                data-mvid="<?php echo (int) $mv['movement_id']; ?>"
                                                data-empname="<?php echo e($mv['employee_name']); ?>">
                                                <i class="fas fa-times me-1"></i>Reject
                                            </button>
                                        </div>
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
                                                            <?php else: ?>
                                                                <div class="rounded-circle shadow-sm mx-auto d-flex align-items-center justify-content-center" style="width:26px;height:26px;background:#e9ecef;color:#6c757d;font-size:.7rem;"><i class="fas fa-clock"></i></div>
                                                                <div class="fw-bold mt-1 text-secondary" style="font-size:.66rem;">HR Sup</div>
                                                                <div class="text-muted" style="font-size:.6rem;">Direct HRD</div>
                                                            <?php endif; ?>
                                                        </div>

                                                        <!-- Node 3: HR Manager -->
                                                        <div class="text-center position-relative" style="z-index:2;">
                                                            <div class="rounded-circle shadow-sm mx-auto d-flex align-items-center justify-content-center pulse-animation" style="width:26px;height:26px;background:#082E06;color:#CBA135;border:2px solid #CBA135;font-size:.7rem;"><i class="fas fa-user-check"></i></div>
                                                            <div class="fw-bold mt-1" style="color:#082E06;font-size:.66rem;">HR Mgr</div>
                                                            <div class="badge rounded-pill bg-warning text-dark" style="font-size:.58rem;">Pending Action</div>
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

        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="mgrRejectModal" tabindex="-1" aria-labelledby="mgrRejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mgrRejectModalLabel"><i class="fas fa-times-circle text-danger me-2"></i>Reject Career Movement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="movement_id" id="mgrRejectMvId">
                <input type="hidden" name="movement_action" value="Reject">
                <div class="modal-body">
                    <p class="mb-2">Rejecting movement for: <strong id="mgrRejectEmpName"></strong></p>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Search filters both tables simultaneously
    const searchInput   = document.getElementById('mgrMovSearch');
    const allRows       = document.querySelectorAll('#mgrMovTable tbody tr');
    const pendingRows   = document.querySelectorAll('#mgrPendingTable tbody tr');

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const term = this.value.trim().toLowerCase();
            [allRows, pendingRows].forEach(rows => {
                rows.forEach(r => { r.style.display = r.textContent.toLowerCase().includes(term) ? '' : 'none'; });
            });
            if (typeof applyZebraStriping === 'function') {
                applyZebraStriping('#mgrMovTable');
                applyZebraStriping('#mgrPendingTable');
            }
        });
    }

    // Reject modal
    const rejectModal = document.getElementById('mgrRejectModal');
    if (rejectModal) {
        rejectModal.addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget;
            document.getElementById('mgrRejectMvId').value          = btn.dataset.mvid;
            document.getElementById('mgrRejectEmpName').textContent = btn.dataset.empname;
        });
    }

    // Auto-open Pending tab if there are pending items and URL says so
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('tab') === 'pending') {
        const pendingTabBtn = document.querySelector('[data-bs-target="#mgrPendingTab"]');
        if (pendingTabBtn) bootstrap.Tab.getOrCreateInstance(pendingTabBtn).show();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
