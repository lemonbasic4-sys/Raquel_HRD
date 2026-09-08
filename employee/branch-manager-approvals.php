<?php
/**
 * Employee Portal - Branch Manager Approvals
 * Allows Branch Managers (rank_category_id = 3) to approve or reject
 * Transfer requests submitted by Branch Supervisors in their branch.
 *
 * Tasks implemented: 5.1, 5.2, 5.3
 * Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 9.2, 9.5, 9.6, 11.1, 11.2
 */
$page_title = 'Branch Manager Approvals';
require_once '../includes/session-check.php';
checkRole(['Employee']);
require_once '../includes/functions.php';

// Older installations may not yet have the Employee Portal workflow columns.
// Upgrade the career-movement schema before any query references those fields.
if (!ensureCareerProgressionMovements($conn)) {
    redirectWith(
        BASE_URL . '/employee/dashboard.php',
        'danger',
        'Career movement approvals are temporarily unavailable because the required database update could not be completed.'
    );
}

$bm_employee_id = (int) ($_SESSION['employee_id'] ?? 0);
$bm_branch_id   = (int) ($_SESSION['branch_id']   ?? 0);
$user_id        = (int) ($_SESSION['user_id']      ?? 0);

// ── Task 5.1 — Access guard: must be rank_category_id = 3 (Branch Manager) ──
$rank_stmt = $conn->prepare(
    "SELECT rank_category_id, branch_id, first_name, last_name, job_title, profile_picture
     FROM employees WHERE employee_id = ? LIMIT 1"
);
$rank_stmt->bind_param("i", $bm_employee_id);
$rank_stmt->execute();
$bm_emp = $rank_stmt->get_result()->fetch_assoc();
$rank_stmt->close();

if (!$bm_emp || (int) ($bm_emp['rank_category_id'] ?? 0) !== 3) {
    redirectWith(
        BASE_URL . '/employee/dashboard.php',
        'danger',
        'Access restricted. This page is for Branch Managers only.'
    );
}

// Use the DB branch_id as authoritative source (may differ from session if session is stale)
$bm_branch_id = (int) ($bm_emp['branch_id'] ?? $bm_branch_id);

// ── Task 5.2 / 5.3 — POST handlers ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    $action      = trim($_POST['action'] ?? '');
    $movement_id = (int) ($_POST['movement_id'] ?? 0);

    // Fetch the target career_movements row
    $mv_stmt = $conn->prepare(
        "SELECT cm.movement_id, cm.portal_workflow_stage, cm.previous_branch_id,
                cm.logged_by, cm.employee_id,
                e.first_name AS emp_first, e.last_name AS emp_last
         FROM career_movements cm
         JOIN employees e ON cm.employee_id = e.employee_id
         WHERE cm.movement_id = ? LIMIT 1"
    );
    $mv_stmt->bind_param("i", $movement_id);
    $mv_stmt->execute();
    $movement_row = $mv_stmt->get_result()->fetch_assoc();
    $mv_stmt->close();

    if (!$movement_row) {
        redirectWith(BASE_URL . '/employee/branch-manager-approvals.php', 'danger', 'Career movement record not found.');
    }

    $target_emp_name = trim(($movement_row['emp_first'] ?? '') . ' ' . ($movement_row['emp_last'] ?? ''));

    // ── Task 5.2 — Approve ───────────────────────────────────────────────────
    if ($action === 'approve') {

        if (!checkApprovalAuthorization($movement_row, $user_id, $bm_branch_id, 'Employee', 'Pending_Branch_Manager')) {
            redirectWith(BASE_URL . '/employee/branch-manager-approvals.php', 'danger', 'You are not authorized to approve this request.');
        }

        // UPDATE stage → Pending_HR_Supervisor
        $upd = $conn->prepare(
            "UPDATE career_movements
             SET portal_workflow_stage        = 'Pending_HR_Supervisor',
                 branch_manager_approved_by   = ?,
                 branch_manager_decision_date = NOW()
             WHERE movement_id = ?"
        );
        $upd->bind_param("ii", $user_id, $movement_id);
        $upd->execute();
        $upd->close();

        // Notify all active HR Supervisors
        $hr_sup_res = $conn->query(
            "SELECT user_id FROM users WHERE role = 'HR Supervisor' AND is_active = 1"
        );
        if ($hr_sup_res && $hr_sup_res->num_rows > 0) {
            $bm_name = trim(($bm_emp['first_name'] ?? '') . ' ' . ($bm_emp['last_name'] ?? ''));
            while ($hs = $hr_sup_res->fetch_assoc()) {
                createNotification(
                    $conn,
                    (int) $hs['user_id'],
                    'Transfer Request Pending Your Approval',
                    $bm_name . ' (Branch Manager) has approved a Transfer request for ' .
                        $target_emp_name . '. Please review and take action.',
                    BASE_URL . '/supervisor/career-movements.php'
                );
            }
            $hr_sup_res->free();
        } else {
            error_log(
                "Career movement notification skipped: no active HR Supervisor users found for movement_id=$movement_id."
            );
        }

        logAudit(
            $conn,
            $user_id,
            'APPROVE',
            'Career Movement',
            $movement_id,
            'Branch Manager approved portal Transfer request for ' . $target_emp_name,
            ['module' => 'Career Progression', 'branch_id' => $bm_branch_id]
        );

        redirectWith(
            BASE_URL . '/employee/branch-manager-approvals.php',
            'success',
            'Transfer request approved and forwarded to HR Supervisor.'
        );
    }

    // ── Task 5.3 — Reject ────────────────────────────────────────────────────
    if ($action === 'reject') {

        $comments = trim($_POST['branch_manager_comments'] ?? '');

        if (!checkApprovalAuthorization($movement_row, $user_id, $bm_branch_id, 'Employee', 'Pending_Branch_Manager')) {
            redirectWith(BASE_URL . '/employee/branch-manager-approvals.php', 'danger', 'You are not authorized to reject this request.');
        }

        if (empty($comments)) {
            redirectWith(BASE_URL . '/employee/branch-manager-approvals.php', 'danger', 'A rejection reason is required.');
        }

        // UPDATE stage → Rejected
        $upd = $conn->prepare(
            "UPDATE career_movements
             SET portal_workflow_stage        = 'Rejected',
                 approval_status              = 'Rejected',
                 branch_manager_approved_by   = ?,
                 branch_manager_decision_date = NOW(),
                 branch_manager_comments      = ?
             WHERE movement_id = ?"
        );
        $upd->bind_param("isi", $user_id, $comments, $movement_id);
        $upd->execute();
        $upd->close();

        // Resolve submitter's Employee Portal user to send rejection notification
        $submitter_user_id = null;
        $logged_by         = (int) ($movement_row['logged_by'] ?? 0);
        if ($logged_by > 0) {
            // Fetch submitter's employee_id from users table
            $sub_stmt = $conn->prepare(
                "SELECT employee_id FROM users WHERE user_id = ? LIMIT 1"
            );
            $sub_stmt->bind_param("i", $logged_by);
            $sub_stmt->execute();
            $sub_row = $sub_stmt->get_result()->fetch_assoc();
            $sub_stmt->close();

            if ($sub_row && !empty($sub_row['employee_id'])) {
                $submitter_emp_id  = (int) $sub_row['employee_id'];
                $submitter_user_id = getPreferredLinkedUserId($conn, $submitter_emp_id, 'employee_portal');
            }
        }

        if ($submitter_user_id) {
            $bm_name = trim(($bm_emp['first_name'] ?? '') . ' ' . ($bm_emp['last_name'] ?? ''));
            createNotification(
                $conn,
                $submitter_user_id,
                'Transfer Request Rejected',
                'Your Transfer request for ' . $target_emp_name .
                    ' has been rejected by the Branch Manager. Reason: ' . $comments,
                BASE_URL . '/employee/career-movement-request.php'
            );
        } else {
            error_log(
                "Rejection notification skipped: could not resolve submitter portal user for movement_id=$movement_id"
            );
        }

        logAudit(
            $conn,
            $user_id,
            'REJECT',
            'Career Movement',
            $movement_id,
            'Branch Manager rejected portal Transfer request for ' . $target_emp_name . '. Reason: ' . $comments,
            ['module' => 'Career Progression', 'branch_id' => $bm_branch_id]
        );

        redirectWith(
            BASE_URL . '/employee/branch-manager-approvals.php',
            'success',
            'Transfer request has been rejected.'
        );
    }

    // Unknown action — redirect gracefully
    redirectWith(BASE_URL . '/employee/branch-manager-approvals.php', 'danger', 'Invalid action.');
}

// ── Task 5.1 — Pending queue query ──────────────────────────────────────────
$pending_requests = [];
$pq_stmt = $conn->prepare(
    "SELECT cm.movement_id, cm.effective_date, cm.reason, cm.created_at,
            cm.portal_workflow_stage, cm.logged_by,
            cm.previous_branch_id, cm.new_branch_id,
            e.first_name, e.last_name, e.job_title AS current_position,
            prev_b.branch_name AS current_branch_name,
            new_b.branch_name  AS destination_branch_name,
            submitter_e.first_name AS sub_first, submitter_e.last_name AS sub_last
     FROM career_movements cm
     JOIN employees e ON cm.employee_id = e.employee_id
     LEFT JOIN branches prev_b ON cm.previous_branch_id = prev_b.branch_id
     LEFT JOIN branches new_b  ON cm.new_branch_id = new_b.branch_id
     LEFT JOIN users sub_u ON cm.logged_by = sub_u.user_id
     LEFT JOIN employees submitter_e ON sub_u.employee_id = submitter_e.employee_id
     WHERE cm.portal_workflow_stage = 'Pending_Branch_Manager'
       AND cm.previous_branch_id = ?
     ORDER BY cm.created_at ASC"
);
$pq_stmt->bind_param("i", $bm_branch_id);
$pq_stmt->execute();
$pq_res = $pq_stmt->get_result();
while ($row = $pq_res->fetch_assoc()) {
    $pending_requests[] = $row;
}
$pq_stmt->close();

$pending_count = count($pending_requests);

require_once '../includes/header.php';
?>

<!-- ── Page Hero ─────────────────────────────────────────────────────────── -->
<div class="page-hero fadeup">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-0 gap-4">
        <div class="d-flex align-items-center gap-4 flex-wrap">
            <img src="<?php echo getEmployeeAvatar($bm_emp['profile_picture'] ?? ''); ?>"
                 loading="lazy"
                 alt="Profile photo"
                 style="width:100px;height:100px;border-radius:50%;object-fit:cover;border:4px solid rgba(255,255,255,.3);box-shadow:0 4px 15px rgba(0,0,0,.2);">
            <div>
                <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">
                    Employee Portal &middot; Branch Manager
                </div>
                <h2 class="text-white fw-bold mb-1 mt-1">Transfer Request Approvals</h2>
                <p class="mb-2 text-white-50 small">
                    <i class="fas fa-briefcase me-1"></i><?php echo e($bm_emp['job_title'] ?? '—'); ?>
                    &bull;
                    <?php
                    // Display branch name from query join
                    $branch_name_for_hero = '—';
                    if (!empty($pending_requests)) {
                        $branch_name_for_hero = $pending_requests[0]['current_branch_name'] ?? '—';
                    } else {
                        // Fetch branch name separately when queue is empty
                        $bn_stmt = $conn->prepare("SELECT branch_name FROM branches WHERE branch_id = ? LIMIT 1");
                        $bn_stmt->bind_param("i", $bm_branch_id);
                        $bn_stmt->execute();
                        $bn_row = $bn_stmt->get_result()->fetch_assoc();
                        $bn_stmt->close();
                        $branch_name_for_hero = $bn_row['branch_name'] ?? '—';
                    }
                    ?>
                    <i class="fas fa-map-marker-alt me-1"></i><?php echo e($branch_name_for_hero); ?>
                </p>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0" style="font-size:.78rem;">
                        <li class="breadcrumb-item">
                            <a href="<?php echo BASE_URL; ?>/employee/dashboard.php"
                               class="text-white-50 text-decoration-none">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item text-white active" aria-current="page">
                            Transfer Approvals
                        </li>
                    </ol>
                </nav>
            </div>
        </div>
        <div class="d-none d-md-block text-end">
            <a href="<?php echo BASE_URL; ?>/employee/dashboard.php"
               class="btn btn-outline-light btn-sm rounded-pill px-3">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>
</div>

<!-- ── Mobile back button ──────────────────────────────────────────────────── -->
<div class="d-md-none mt-3 mb-2 fadeup" style="animation-delay:.1s;">
    <a href="<?php echo BASE_URL; ?>/employee/dashboard.php"
       class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
    </a>
</div>

<div class="container-fluid px-3 py-4">

    <?php displayFlashMessage(); ?>

    <!-- ── Pending Requests Card ─────────────────────────────────────────────── -->
    <div class="card shadow-sm border-0 fadeup" style="animation-delay:.1s;">
        <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
            <h6 class="mb-0 fw-semibold text-dark">
                <i class="fas fa-clipboard-list me-2 text-primary"></i>Pending Transfer Requests
            </h6>
            <span class="badge <?php echo $pending_count > 0 ? 'bg-warning text-dark' : 'bg-secondary'; ?> rounded-pill">
                <?php echo $pending_count; ?> pending
            </span>
        </div>

        <div class="card-body p-0">

            <?php if (empty($pending_requests)): ?>
            <!-- ── Empty state ──────────────────────────────────────────────── -->
            <div class="text-center py-5 text-muted">
                <i class="fas fa-check-circle fa-3x mb-3 d-block text-success opacity-50"></i>
                <h6 class="fw-semibold">All caught up!</h6>
                <p class="mb-0 small">No pending Transfer requests for your branch at this time.</p>
            </div>

            <?php else: ?>
            <!-- ── Requests table ───────────────────────────────────────────── -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size:.85rem;">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Employee</th>
                            <th>Current Position</th>
                            <th>Current Branch</th>
                            <th>Destination Branch</th>
                            <th>Effective Date</th>
                            <th>Submitted By</th>
                            <th>Reason / Notes</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pending_requests as $req): ?>
                        <?php
                        $employee_full_name = e(trim(($req['first_name'] ?? '') . ' ' . ($req['last_name'] ?? '')));
                        $submitted_by_name  = e(trim(($req['sub_first'] ?? '') . ' ' . ($req['sub_last'] ?? '')));
                        if (empty(trim(strip_tags($submitted_by_name)))) {
                            $submitted_by_name = '<span class="text-muted fst-italic">Unknown</span>';
                        }
                        $mvid = (int) $req['movement_id'];
                        ?>
                        <tr>
                            <td class="ps-3 fw-semibold"><?php echo $employee_full_name; ?></td>
                            <td><?php echo e($req['current_position'] ?? '—'); ?></td>
                            <td><?php echo e($req['current_branch_name'] ?? '—'); ?></td>
                            <td>
                                <span class="badge bg-info text-dark" style="font-size:.75rem;">
                                    <i class="fas fa-arrow-right me-1"></i><?php echo e($req['destination_branch_name'] ?? '—'); ?>
                                </span>
                            </td>
                            <td style="white-space:nowrap;"><?php echo formatDate($req['effective_date'] ?? ''); ?></td>
                            <td><?php echo $submitted_by_name; ?></td>
                            <td style="max-width:180px;">
                                <?php if (!empty($req['reason'])): ?>
                                <span class="d-inline-block text-truncate" style="max-width:160px;"
                                      title="<?php echo e($req['reason']); ?>">
                                    <?php echo e($req['reason']); ?>
                                </span>
                                <?php else: ?>
                                <span class="text-muted fst-italic small">No reason provided</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center" style="white-space:nowrap;">
                                <!-- Approve button → triggers modal -->
                                <button type="button"
                                        class="btn btn-success btn-sm me-1"
                                        data-bs-toggle="modal"
                                        data-bs-target="#approveModal<?php echo $mvid; ?>"
                                        title="Approve this request">
                                    <i class="fas fa-check me-1"></i>Approve
                                </button>
                                <!-- Reject button → triggers modal -->
                                <button type="button"
                                        class="btn btn-danger btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#rejectModal<?php echo $mvid; ?>"
                                        title="Reject this request">
                                    <i class="fas fa-times me-1"></i>Reject
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

        </div><!-- /.card-body -->
    </div><!-- /.card -->

</div><!-- /.container-fluid -->


<?php
// ── Modals — rendered outside the table for valid HTML ──────────────────────
foreach ($pending_requests as $req):
    $mvid = (int) $req['movement_id'];
    $employee_display = e(trim(($req['first_name'] ?? '') . ' ' . ($req['last_name'] ?? '')));
    $dest_branch_display = e($req['destination_branch_name'] ?? '—');
?>

<!-- ══ Approve Modal #<?php echo $mvid; ?> ══════════════════════════════════════════════ -->
<div class="modal fade" id="approveModal<?php echo $mvid; ?>" tabindex="-1"
     aria-labelledby="approveModalLabel<?php echo $mvid; ?>" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="approveModalLabel<?php echo $mvid; ?>">
                    <i class="fas fa-check-circle me-2"></i>Approve Transfer Request
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">
                    You are about to <strong class="text-success">approve</strong> the Transfer request for:
                </p>
                <ul class="list-unstyled mb-3 ps-2">
                    <li><i class="fas fa-user me-2 text-muted"></i><strong>Employee:</strong> <?php echo $employee_display; ?></li>
                    <li><i class="fas fa-map-marker-alt me-2 text-muted"></i><strong>Destination:</strong> <?php echo $dest_branch_display; ?></li>
                    <li><i class="fas fa-calendar-alt me-2 text-muted"></i><strong>Effective Date:</strong> <?php echo e(formatDate($req['effective_date'] ?? '')); ?></li>
                </ul>
                <p class="text-muted small mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    This request will be forwarded to HR Supervisor for further review.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="movement_id" value="<?php echo $mvid; ?>">
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="fas fa-check me-1"></i>Confirm Approval
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ══ Reject Modal #<?php echo $mvid; ?> ═══════════════════════════════════════════════ -->
<div class="modal fade" id="rejectModal<?php echo $mvid; ?>" tabindex="-1"
     aria-labelledby="rejectModalLabel<?php echo $mvid; ?>" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="rejectModalLabel<?php echo $mvid; ?>">
                    <i class="fas fa-times-circle me-2"></i>Reject Transfer Request
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">
                    You are about to <strong class="text-danger">reject</strong> the Transfer request for:
                </p>
                <ul class="list-unstyled mb-3 ps-2">
                    <li><i class="fas fa-user me-2 text-muted"></i><strong>Employee:</strong> <?php echo $employee_display; ?></li>
                    <li><i class="fas fa-map-marker-alt me-2 text-muted"></i><strong>Destination:</strong> <?php echo $dest_branch_display; ?></li>
                    <li><i class="fas fa-calendar-alt me-2 text-muted"></i><strong>Effective Date:</strong> <?php echo e(formatDate($req['effective_date'] ?? '')); ?></li>
                </ul>
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="rejectForm<?php echo $mvid; ?>">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="movement_id" value="<?php echo $mvid; ?>">
                    <div class="mb-3">
                        <label for="branch_manager_comments_<?php echo $mvid; ?>" class="form-label fw-semibold">
                            Rejection Reason <span class="text-danger">*</span>
                        </label>
                        <textarea name="branch_manager_comments"
                                  id="branch_manager_comments_<?php echo $mvid; ?>"
                                  class="form-control"
                                  rows="4"
                                  maxlength="1000"
                                  required
                                  aria-required="true"
                                  placeholder="Provide a clear reason for rejecting this transfer request…"></textarea>
                        <div class="form-text text-muted">Required. Max 1,000 characters.</div>
                    </div>
            </div><!-- /.modal-body -->
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger btn-sm" form="rejectForm<?php echo $mvid; ?>">
                    <i class="fas fa-times me-1"></i>Confirm Rejection
                </button>
            </div>
                </form>
        </div>
    </div>
</div>

<?php endforeach; ?>

<?php require_once '../includes/footer.php'; ?>
