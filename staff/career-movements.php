<?php
/**
 * HR Staff Portal — Career Movements
 * - View all incoming career movement requests (from Branch Heads & HR Staff)
 * - Initiate requests for leaderless branches
 * - Approve/Reject requests (except own submissions — self-approval blocked)
 */
$page_title = 'Career Movements';
require_once '../includes/session-check.php';
checkRole(['HR Staff']);
require_once '../includes/functions.php';

$movement_ready = ensureCareerProgressionMovements($conn);
if ($movement_ready) {
    applyDueCareerProgressionMovements($conn);
}

$current_user_id = (int) ($_SESSION['user_id'] ?? 0);

//  POST: Create movement (HR Staff for leaderless branch)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_movement'])) {
    if (!$movement_ready) {
        redirectWith(BASE_URL . '/staff/career-movements.php', 'danger', 'Career Movements could not be initialized.');
    }

    $employee_id    = (int) ($_POST['employee_id']    ?? 0);
    $movement_type  = trim($_POST['movement_type']    ?? '');
    $new_position   = trim($_POST['new_position']     ?? '');
    $new_branch_id  = ($_POST['new_branch_id'] ?? '') !== '' ? (int) $_POST['new_branch_id'] : null;
    $effective_date = trim($_POST['effective_date']   ?? '');
    $reason         = trim($_POST['reason']           ?? '');
    $allowed_types  = ['Promotion', 'Transfer', 'Demotion', 'Role Change'];

    if ($employee_id <= 0 || !in_array($movement_type, $allowed_types, true) || $new_position === '' || $effective_date === '') {
        redirectWith(BASE_URL . '/staff/career-movements.php', 'danger', 'Please complete all required fields.');
    }

    $emp_stmt = $conn->prepare("SELECT employee_id, first_name, last_name, job_title, branch_id FROM employees WHERE employee_id = ? AND is_active = 1 LIMIT 1");
    $emp_stmt->bind_param("i", $employee_id);
    $emp_stmt->execute();
    $employee = $emp_stmt->get_result()->fetch_assoc();
    $emp_stmt->close();

    if (!$employee) {
        redirectWith(BASE_URL . '/staff/career-movements.php', 'danger', 'Employee not found.');
    }

    if (!isBranchLeaderless($conn, (int) $employee['branch_id'])) {
        redirectWith(BASE_URL . '/staff/career-movements.php', 'danger', 'That branch already has a Manager or Supervisor. Requests must be submitted by the Branch Head.');
    }

    $previous_position  = $employee['job_title'] ?? '';
    $previous_branch_id = !empty($employee['branch_id']) ? (int) $employee['branch_id'] : null;
    if ($new_branch_id === $previous_branch_id) { $new_branch_id = null; }

    $staff_name = $_SESSION['full_name'] ?? 'HR Staff';

    $insert = $conn->prepare("
        INSERT INTO career_movements
            (employee_id, movement_type, previous_position, new_position,
             previous_branch_id, new_branch_id, effective_date, reason,
             logged_by, approval_status, initiated_by_name, initiated_by_role,
             initiated_via, request_source)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, 'HR Staff', 'Memo', 'HR Portal')
    ");
    $insert->bind_param("isssiissis",
        $employee_id, $movement_type, $previous_position, $new_position,
        $previous_branch_id, $new_branch_id, $effective_date, $reason,
        $current_user_id, $staff_name
    );

    if (!$insert->execute()) {
        $insert->close();
        redirectWith(BASE_URL . '/staff/career-movements.php', 'danger', 'Failed to create movement request.');
    }
    $movement_id   = $insert->insert_id;
    $insert->close();
    $employee_name = trim($employee['first_name'] . ' ' . $employee['last_name']);

    $notify = $conn->query("SELECT user_id FROM users WHERE role IN ('HR Supervisor','HR Manager') AND is_active = 1");
    while ($nr = $notify->fetch_assoc()) {
        createNotification($conn, (int)$nr['user_id'],
            'Career Movement Request (Leaderless Branch)',
            "HR Staff submitted a {$movement_type} request for {$employee_name}.",
            BASE_URL . '/manager/career-movements.php');
    }

    logAudit($conn, $current_user_id, 'CREATE', 'Career Movement', $movement_id,
        "HR Staff submitted {$movement_type} for {$employee_name} (leaderless branch).");
    redirectWith(BASE_URL . '/staff/career-movements.php', 'success', 'Request submitted for HR Supervisor / Manager review.');
}

// ── POST: Approve / Reject ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['movement_action'])) {
    if (!$movement_ready) {
        redirectWith(BASE_URL . '/staff/career-movements.php', 'danger', 'Career Movements could not be initialized.');
    }
    $movement_id = (int)($_POST['movement_id'] ?? 0);
    $action      = trim($_POST['movement_action'] ?? '');

    if ($movement_id <= 0 || !in_array($action, ['Approve','Reject'], true)) {
        redirectWith(BASE_URL . '/staff/career-movements.php', 'danger', 'Invalid action.');
    }

    $stmt = $conn->prepare("
        SELECT cm.*, CONCAT(e.first_name,' ',e.last_name) AS employee_name
        FROM career_movements cm
        JOIN employees e ON cm.employee_id = e.employee_id
        WHERE cm.movement_id = ? AND cm.approval_status = 'Pending' LIMIT 1
    ");
    $stmt->bind_param("i", $movement_id);
    $stmt->execute();
    $movement = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$movement) {
        redirectWith(BASE_URL . '/staff/career-movements.php', 'danger', 'Movement not found or already processed.');
    }
    if ((int)$movement['logged_by'] === $current_user_id) {
        redirectWith(BASE_URL . '/staff/career-movements.php', 'danger', 'You cannot approve or reject a request you submitted.');
    }

    $status   = $action === 'Approve' ? 'Approved' : 'Rejected';
    $comments = trim($_POST['manager_comments'] ?? '');

    $upd = $conn->prepare("UPDATE career_movements SET approval_status=?, approved_by=?, decision_date=NOW(), manager_comments=?, is_applied=0 WHERE movement_id=?");
    $upd->bind_param("sisi", $status, $current_user_id, $comments, $movement_id);
    $upd->execute(); $upd->close();

    if ($status === 'Approved' && $movement['effective_date'] <= date('Y-m-d')) {
        $eid = (int)$movement['employee_id'];
        if (!empty($movement['new_branch_id'])) {
            $nbid = (int)$movement['new_branch_id'];
            $eu = $conn->prepare("UPDATE employees SET job_title=?, branch_id=? WHERE employee_id=?");
            $eu->bind_param("sii", $movement['new_position'], $nbid, $eid);
        } else {
            $eu = $conn->prepare("UPDATE employees SET job_title=? WHERE employee_id=?");
            $eu->bind_param("si", $movement['new_position'], $eid);
        }
        $eu->execute(); $eu->close();
        $mk = $conn->prepare("UPDATE career_movements SET is_applied=1 WHERE movement_id=?");
        $mk->bind_param("i", $movement_id); $mk->execute(); $mk->close();
    }

    if (!empty($movement['logged_by'])) {
        createNotification($conn, (int)$movement['logged_by'],
            "Career Movement {$status}",
            "The {$movement['movement_type']} for {$movement['employee_name']} has been {$status}.",
            BASE_URL . '/staff/career-movements.php');
    }

    logAudit($conn, $current_user_id, strtoupper($action), 'Career Movement', $movement_id,
        "{$action}d {$movement['movement_type']} for {$movement['employee_name']}.");
    $msg = $status === 'Approved' ? 'Career movement approved.' : 'Career movement rejected.';
    redirectWith(BASE_URL . '/staff/career-movements.php', $status === 'Approved' ? 'success' : 'warning', $msg);
}

// ── Fetch display data ────────────────────────────────────────────────────────
require_once '../includes/header.php';
?>
<div class="staff-career-movements-page">
<?php

$movements = [];
$counts    = ['Pending'=>0,'Approved'=>0,'Rejected'=>0,'Applied'=>0];
if ($movement_ready) {
    $res = $conn->query("
        SELECT cm.*,
            e.employee_code,
            e.job_title AS current_job_title,
            CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
            pb.branch_name AS previous_branch_name,
            nb.branch_name AS new_branch_name,
            u1.full_name   AS logged_by_name,
            u2.full_name   AS approved_by_name
        FROM career_movements cm
        JOIN employees  e  ON cm.employee_id       = e.employee_id
        LEFT JOIN branches pb ON cm.previous_branch_id = pb.branch_id
        LEFT JOIN branches nb ON cm.new_branch_id       = nb.branch_id
        LEFT JOIN users   u1 ON cm.logged_by            = u1.user_id
        LEFT JOIN users   u2 ON cm.approved_by          = u2.user_id
        ORDER BY cm.created_at DESC
    ");
    while ($row = $res->fetch_assoc()) {
        $movements[] = $row;
        if (isset($counts[$row['approval_status']])) $counts[$row['approval_status']]++;
        if ((int)($row['is_applied']??0)===1) $counts['Applied']++;
    }
}

$all_branches = [];
$br = $conn->query("SELECT branch_id, branch_name FROM branches WHERE is_active=1 ORDER BY branch_name");
while ($row = $br->fetch_assoc()) $all_branches[] = $row;

$leaderless_employees = [];
$branch_name_map      = [];
foreach ($all_branches as $branch) {
    $branch_name_map[(string)$branch['branch_id']] = $branch['branch_name'];
    if (isBranchLeaderless($conn, (int)$branch['branch_id'])) {
        $le = $conn->prepare("SELECT employee_id, employee_code, first_name, last_name, job_title, branch_id FROM employees WHERE branch_id=? AND is_active=1 AND deleted_at IS NULL ORDER BY last_name, first_name");
        $le->bind_param("i", $branch['branch_id']);
        $le->execute();
        $rows = $le->get_result()->fetch_all(MYSQLI_ASSOC);
        $le->close();
        foreach ($rows as $r) {
            $r['_branch_name'] = $branch['branch_name'];
            $leaderless_employees[] = $r;
        }
    }
}

function staffCmTypeClass($t){return match($t){'Promotion'=>'bg-success','Transfer'=>'bg-info text-dark','Demotion'=>'bg-danger','Role Change'=>'bg-primary',default=>'bg-secondary'};}
function staffCmStatusClass($s){return match($s){'Approved'=>'bg-success','Rejected'=>'bg-danger',default=>'bg-warning text-dark'};}
?>

<div class="page-hero fadeup">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
        <div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">HR Staff &middot; Career</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-route me-2" style="color:#BD9414;"></i>Career Movements</h4>
            <p class="text-white-50 small mb-0 mt-2">View employee career movement records and track approved changes to positions, branches, and roles.</p>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-value"><?php echo $counts['Pending']; ?></div><div class="stat-label">Pending</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-value"><?php echo $counts['Approved']; ?></div><div class="stat-label">Approved</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-value"><?php echo $counts['Applied']; ?></div><div class="stat-label">Applied</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-value"><?php echo $counts['Rejected']; ?></div><div class="stat-label">Rejected</div></div></div>
    </div>
</div>

<?php if (!empty($leaderless_employees)): ?>
<div class="chart-card fadeup mb-3">
    <div class="cc-body d-flex align-items-center gap-3 py-3">
        <i class="fas fa-exclamation-triangle text-warning fa-lg flex-shrink-0"></i>
        <div>
            <div class="fw-semibold">Leaderless Branch Detected</div>
            <div class="small text-muted">Some branches have no active Manager or Supervisor. As HR Staff, you may submit career movement requests for those employees. HR Supervisor or Manager will approve.</div>
        </div>
        <button class="btn btn-warning btn-sm ms-auto text-nowrap" onclick="document.getElementById('createTabBtn')?.click();">
            <i class="fas fa-plus me-1"></i>Create Request
        </button>
    </div>
</div>
<?php endif; ?>

<div class="chart-card fadeup">
    <div class="cc-header d-flex flex-wrap align-items-center justify-content-between gap-3">
        <ul class="nav nav-tabs cc-header-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#listTab" type="button" role="tab">
                    <i class="fas fa-list me-1"></i>All Movements
                </button>
            </li>
            <?php if (!empty($leaderless_employees)): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#createTab" id="createTabBtn" type="button" role="tab">
                    <i class="fas fa-plus me-1"></i>Create Request <span class="badge bg-warning text-dark ms-1">Leaderless</span>
                </button>
            </li>
            <?php endif; ?>
        </ul>
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" class="form-control form-control-sm" id="staffMovSearch" placeholder="Search movements...">
        </div>
    </div>

    <div class="cc-body p-0">
        <div class="tab-content">

            <!-- LIST TAB -->
            <div class="tab-pane fade show active" id="listTab" role="tabpanel">
                <div class="table-responsive">
                    <table class="table modern-table align-middle mb-0" id="staffMovTable">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Type</th>
                                <th>Position Change</th>
                                <th>Branch Change</th>
                                <th>Effective</th>
                                <th>Source</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($movements)): ?>
                                <tr><td colspan="8" class="text-center py-5 text-muted"><i class="fas fa-route d-block mb-2" style="font-size:2rem;opacity:.2;"></i>No career movements yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($movements as $mv):
                                    $is_mine    = (int)($mv['logged_by']??0) === $current_user_id;
                                    $is_pending = $mv['approval_status'] === 'Pending';
                                    $is_hr_staff_req = ($mv['request_source']==='HR Portal' && ($mv['initiated_by_role']??'')==='HR Staff');
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?php echo e($mv['employee_name']); ?></div>
                                        <small class="text-muted"><?php echo e(getEmployeeDisplayId($mv)); ?> &middot; <?php echo e($mv['current_job_title']); ?></small>
                                    </td>
                                    <td><span class="badge <?php echo staffCmTypeClass($mv['movement_type']); ?>"><?php echo e($mv['movement_type']); ?></span></td>
                                    <td>
                                        <div class="small text-muted"><?php echo e($mv['previous_position']?:'—'); ?></div>
                                        <div class="fw-semibold"><?php echo e($mv['new_position']); ?></div>
                                    </td>
                                    <td>
                                        <?php if (!empty($mv['new_branch_id'])): ?>
                                            <div class="small text-muted"><?php echo e($mv['previous_branch_name']?:'N/A'); ?></div>
                                            <div class="fw-semibold"><?php echo e($mv['new_branch_name']?:'N/A'); ?></div>
                                        <?php else: ?>
                                            <span class="text-muted small">No branch change</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="small"><?php echo formatDate($mv['effective_date']); ?></span></td>
                                    <td>
                                        <?php if ($is_hr_staff_req): ?>
                                            <span class="badge bg-warning text-dark"><i class="fas fa-user-shield me-1"></i>HR Staff Requisition</span>
                                            <div class="small text-muted mt-1">Leaderless Branch</div>
                                        <?php elseif ($mv['request_source']==='Employee Portal'): ?>
                                            <span class="badge bg-info text-dark"><i class="fas fa-user-tie me-1"></i>Branch Head Requisition</span>
                                            <?php if (!empty($mv['initiated_by_name'])): ?>
                                                <div class="small text-muted mt-1">by <?php echo e($mv['initiated_by_name']); ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><i class="fas fa-building me-1"></i>HR Portal</span>
                                            <div class="small text-muted mt-1">by <?php echo e($mv['logged_by_name']?:'HRD'); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo staffCmStatusClass($mv['approval_status']); ?>"><?php echo e($mv['approval_status']); ?></span>
                                        <?php if ($mv['approval_status']==='Approved' && (int)$mv['is_applied']===1): ?>
                                            <span class="badge bg-success ms-1">Applied</span>
                                        <?php elseif ($mv['approval_status']==='Approved'): ?>
                                            <span class="badge bg-secondary ms-1">Scheduled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($is_pending && !$is_mine): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="movement_id" value="<?php echo (int)$mv['movement_id']; ?>">
                                                <input type="hidden" name="movement_action" value="Approve">
                                                <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Approve this career movement?');"><i class="fas fa-check me-1"></i>Approve</button>
                                            </form>
                                            <button class="btn btn-sm btn-outline-danger ms-1"
                                                data-bs-toggle="modal" data-bs-target="#rejectModal"
                                                data-mvid="<?php echo (int)$mv['movement_id']; ?>"
                                                data-empname="<?php echo e($mv['employee_name']); ?>">
                                                <i class="fas fa-times me-1"></i>Reject
                                            </button>
                                        <?php elseif ($is_pending && $is_mine): ?>
                                            <span class="badge bg-light text-dark border" title="Awaiting HR Supervisor or Manager approval."><i class="fas fa-clock me-1"></i>Awaiting Review</span>
                                        <?php else: ?>
                                            <span class="small text-muted"><?php echo e($mv['approved_by_name']?:'Processed'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php if (!empty($mv['reason'])||!empty($mv['manager_comments'])): ?>
                                <tr class="bg-light">
                                    <td colspan="8" class="small text-muted py-2">
                                        <?php if (!empty($mv['reason'])): ?><span class="fw-semibold">Reason:</span> <?php echo e($mv['reason']); ?><?php endif; ?>
                                        <?php if (!empty($mv['manager_comments'])): ?><span class="ms-3 fw-semibold">Decision Notes:</span> <?php echo e($mv['manager_comments']); ?><?php endif; ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- CREATE TAB -->
            <?php if (!empty($leaderless_employees)): ?>
            <div class="tab-pane fade" id="createTab" role="tabpanel">
                <div class="p-4">
                    <div class="alert alert-warning d-flex gap-2 align-items-start mb-4">
                        <i class="fas fa-exclamation-triangle mt-1 flex-shrink-0"></i>
                        <div>
                            <strong>Leaderless Branch — HR Staff Requisition</strong><br>
                            <span class="small">You are submitting on behalf of a branch with no active Manager or Supervisor. This request will be reviewed and approved by the <strong>HR Supervisor or HR Manager</strong>.</span>
                        </div>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="create_movement" value="1">
                        <div class="row g-3">
                            <div class="col-lg-6">
                                <label class="form-label fw-semibold">Employee <span class="text-danger">*</span></label>
                                <select class="form-select" name="employee_id" id="staffEmpSelect" required>
                                    <option value="">Select employee from leaderless branch</option>
                                    <?php foreach ($leaderless_employees as $emp): ?>
                                        <option value="<?php echo (int)$emp['employee_id']; ?>"
                                            data-branch="<?php echo (int)$emp['branch_id']; ?>"
                                            data-jobtitle="<?php echo e($emp['job_title']); ?>">
                                            <?php echo e($emp['last_name'].', '.$emp['first_name'].' — '.$emp['_branch_name'].' — '.$emp['job_title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text text-warning"><i class="fas fa-info-circle me-1"></i>Only employees from leaderless branches are listed.</div>
                            </div>
                            <div class="col-lg-6">
                                <div class="p-3 rounded border bg-light h-100" id="staffEmpInfo" style="display:none;">
                                    <div class="small text-muted">Current Assignment</div>
                                    <div class="fw-bold" id="staffCurrentPos"></div>
                                    <div class="small text-muted" id="staffCurrentBranch"></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Movement Type <span class="text-danger">*</span></label>
                                <select class="form-select" name="movement_type" required>
                                    <option value="">-- Select type --</option>
                                    <option value="Transfer">Transfer</option>
                                    <option value="Promotion">Promotion</option>
                                    <option value="Demotion">Demotion</option>
                                    <option value="Role Change">Role Change</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">New Position <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="new_position" placeholder="e.g. Branch Teller" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">New Branch</label>
                                <select class="form-select" name="new_branch_id">
                                    <option value="">No branch change</option>
                                    <?php foreach ($all_branches as $b): ?>
                                        <option value="<?php echo (int)$b['branch_id']; ?>"><?php echo e($b['branch_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Effective Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="effective_date" required>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Reason</label>
                                <textarea class="form-control" name="reason" rows="3" placeholder="Provide justification for this career movement..."></textarea>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" class="btn btn-warning text-dark px-4">
                                <i class="fas fa-paper-plane me-1"></i>Submit for HR Supervisor / Manager Review
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rejectModalLabel"><i class="fas fa-times-circle text-danger me-2"></i>Reject Career Movement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="movement_id" id="rejectMvId">
                <input type="hidden" name="movement_action" value="Reject">
                <div class="modal-body">
                    <p class="mb-2">Rejecting movement for: <strong id="rejectEmpName"></strong></p>
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
    const branchNames = <?php echo json_encode($branch_name_map, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_QUOT); ?>;
    const empSel      = document.getElementById('staffEmpSelect');
    const empInfo     = document.getElementById('staffEmpInfo');
    const curPos      = document.getElementById('staffCurrentPos');
    const curBranch   = document.getElementById('staffCurrentBranch');

    if (empSel) {
        empSel.addEventListener('change', function () {
            const opt = this.options[this.selectedIndex];
            if (!opt.value) { empInfo.style.display = 'none'; return; }
            curPos.textContent    = opt.dataset.jobtitle || 'N/A';
            curBranch.textContent = branchNames[opt.dataset.branch] || 'N/A';
            empInfo.style.display = 'block';
        });
    }

    const searchInput = document.getElementById('staffMovSearch');
    const tableRows   = document.querySelectorAll('#staffMovTable tbody tr');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const term = this.value.trim().toLowerCase();
            tableRows.forEach(r => { r.style.display = r.textContent.toLowerCase().includes(term) ? '' : 'none'; });
            if (typeof applyZebraStriping === 'function') applyZebraStriping('#staffMovTable');
        });
    }

    const rejectModal = document.getElementById('rejectModal');
    if (rejectModal) {
        rejectModal.addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget;
            document.getElementById('rejectMvId').value         = btn.dataset.mvid;
            document.getElementById('rejectEmpName').textContent = btn.dataset.empname;
        });
    }
});
</script>

</div>
<?php require_once '../includes/footer.php'; ?>
