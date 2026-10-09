<?php
$page_title = 'Reporting Reviews';
require_once '../includes/session-check.php';
require_once '../includes/functions.php';
checkRole(['Employee', 'HR Manager', 'HR Supervisor', 'HR Staff', 'President and CEO', 'Admin']);

if (!ensureHierarchicalEvaluationSchema($conn)) {
    http_response_code(500);
    exit('Evaluation reporting workflow is unavailable.');
}

$evaluation_id = (int)($_GET['evaluation_id'] ?? $_POST['evaluation_id'] ?? 0);
$user_id = (int)($_SESSION['user_id'] ?? 0);
$employee_id = (int)($_SESSION['employee_id'] ?? 0);
$can_reassign = in_array($_SESSION['role'] ?? '', ['HR Manager', 'President and CEO', 'Admin'], true);
if ($evaluation_id <= 0) {
    $blocked_clause = $can_reassign ? "OR rs.status = 'Blocked'" : '';
    $queue_stmt = $conn->prepare("SELECT DISTINCT ev.evaluation_id, ev.evaluation_type, e.first_name, e.last_name,
            e.job_title, d.department_name, et.template_name, rs.status, rs.step_order
        FROM evaluation_reporting_review_steps rs
        JOIN evaluations ev ON ev.evaluation_id = rs.evaluation_id AND ev.deleted_at IS NULL
        JOIN employees e ON e.employee_id = ev.employee_id
        LEFT JOIN departments d ON d.department_id = e.department_id
        JOIN evaluation_templates et ON et.template_id = ev.template_id
        LEFT JOIN employees reviewer ON reviewer.employee_id = ?
            AND reviewer.is_active = 1 AND reviewer.deleted_at IS NULL
        WHERE (rs.status = 'Pending' AND (
                rs.eligible_employee_id = ? OR
                (rs.eligible_job_title_id IS NOT NULL AND reviewer.job_title_id = rs.eligible_job_title_id)
            ) AND reviewer.employee_id <> ev.employee_id
                AND NOT EXISTS (SELECT 1 FROM evaluation_reporting_review_steps prior
                    WHERE prior.evaluation_id = rs.evaluation_id AND prior.step_order < rs.step_order
                      AND prior.status = 'Completed' AND prior.reviewer_employee_id = reviewer.employee_id))
            OR (rs.status = 'Claimed' AND rs.reviewer_user_id = ?)
            $blocked_clause
        ORDER BY ev.submitted_date DESC, ev.evaluation_id DESC");
    $queue_stmt->bind_param('iii', $employee_id, $employee_id, $user_id);
    $queue_stmt->execute();
    $queue = $queue_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $queue_stmt->close();
    require_once '../includes/header.php';
    ?>
    <div class="container-fluid py-4">
        <h1 class="h3 mb-3">Reporting-chain reviews</h1>
        <p class="text-muted">Claim and review self-ratings assigned to your active reporting position.</p>
        <?php if (!$queue): ?>
            <div class="alert alert-info">No reporting reviews are currently assigned to you.</div>
        <?php else: ?>
            <div class="table-responsive card">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Employee</th><th>Position / Department</th><th>Form</th><th>Stage</th><th></th></tr></thead>
                    <tbody><?php foreach ($queue as $item): ?>
                        <tr>
                            <td><?php echo e($item['first_name'] . ' ' . $item['last_name']); ?></td>
                            <td><?php echo e($item['job_title'] . ' / ' . ($item['department_name'] ?? '')); ?></td>
                            <td><?php echo e($item['template_name']); ?></td>
                            <td><?php echo e($item['status']); ?> · Step <?php echo (int)$item['step_order']; ?></td>
                            <td><a class="btn btn-sm btn-primary" href="<?php echo BASE_URL; ?>/employee/reporting-reviews.php?evaluation_id=<?php echo (int)$item['evaluation_id']; ?>">Open review</a></td>
                        </tr>
                    <?php endforeach; ?></tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php
    require_once '../includes/footer.php';
    exit;
}
$evaluation_stmt = $conn->prepare("SELECT ev.evaluation_id, ev.employee_id, ev.status, ev.submitted_date,
        e.first_name, e.last_name, e.employee_code, e.job_title, d.department_name,
        et.template_name, ev.evaluation_type, ev.evaluation_period_start, ev.evaluation_period_end
    FROM evaluations ev
    JOIN employees e ON e.employee_id = ev.employee_id
    LEFT JOIN departments d ON d.department_id = e.department_id
    JOIN evaluation_templates et ON et.template_id = ev.template_id
    WHERE ev.evaluation_id = ? AND ev.deleted_at IS NULL LIMIT 1");
$evaluation_stmt->bind_param('i', $evaluation_id);
$evaluation_stmt->execute();
$evaluation = $evaluation_stmt->get_result()->fetch_assoc();
$evaluation_stmt->close();
if (!$evaluation) redirectWith(BASE_URL . '/employee/reporting-reviews.php', 'danger', 'Evaluation not found.');

$step_stmt = $conn->prepare("SELECT * FROM evaluation_reporting_review_steps
    WHERE evaluation_id = ? AND status IN ('Pending','Claimed','Blocked')
    ORDER BY step_order DESC LIMIT 1");
$step_stmt->bind_param('i', $evaluation_id);
$step_stmt->execute();
$step = $step_stmt->get_result()->fetch_assoc();
$step_stmt->close();
if (!$step) redirectWith(BASE_URL . '/employee/reporting-reviews.php', 'info', 'There is no outstanding reporting review for this evaluation.');
$is_claimed_by_user = (int)($step['reviewer_user_id'] ?? 0) === $user_id;
$stored_target = !empty($step['eligible_employee_id'])
    ? ['kind' => 'employee', 'employee_id' => (int)$step['eligible_employee_id']]
    : ['kind' => 'position', 'job_title_id' => (int)($step['eligible_job_title_id'] ?? 0)];
$stored_eligible = isUserEligibleForEvaluationReportingTarget($conn, $user_id, $stored_target, $evaluation_id, (int)$step['step_order']);
if ($step['status'] === 'Claimed' && !$is_claimed_by_user) {
    redirectWith(BASE_URL . '/employee/reporting-reviews.php', 'danger', 'This review has already been claimed by another reviewer.');
}
if (in_array($step['status'], ['Pending', 'Blocked'], true) && !$stored_eligible && !$can_reassign) {
    if ((int)$step['step_order'] === 1) {
        $source_employee_id = (int)$evaluation['employee_id'];
    } else {
        $previous_order = (int)$step['step_order'] - 1;
        $source_stmt = $conn->prepare("SELECT reviewer_employee_id FROM evaluation_reporting_review_steps
            WHERE evaluation_id = ? AND step_order = ? AND status = 'Completed' LIMIT 1");
        $source_stmt->bind_param('ii', $evaluation_id, $previous_order);
        $source_stmt->execute();
        $source_employee_id = (int)($source_stmt->get_result()->fetch_assoc()['reviewer_employee_id'] ?? 0);
        $source_stmt->close();
    }
    $current_target = $source_employee_id > 0
        ? getEvaluationReportingTarget($conn, $source_employee_id)
        : ['kind' => 'none'];
    if (!isUserEligibleForEvaluationReportingTarget($conn, $user_id, $current_target, $evaluation_id, (int)$step['step_order'])) {
        redirectWith(BASE_URL . '/employee/reporting-reviews.php', 'danger', 'This evaluation is not assigned to your reporting position.');
    }
}
if (!refreshPendingEvaluationReportingStep($conn, $evaluation_id, $step)) {
    redirectWith(BASE_URL . '/employee/reporting-reviews.php', 'info', 'The reporting assignment changed and this evaluation was routed to its current workflow stage.');
}
$step_stmt = $conn->prepare("SELECT * FROM evaluation_reporting_review_steps
    WHERE evaluation_id = ? AND status IN ('Pending','Claimed','Blocked')
    ORDER BY step_order DESC LIMIT 1");
$step_stmt->bind_param('i', $evaluation_id);
$step_stmt->execute();
$step = $step_stmt->get_result()->fetch_assoc();
$step_stmt->close();
if (!$step) redirectWith(BASE_URL . '/employee/reporting-reviews.php', 'info', 'There is no outstanding reporting review for this evaluation.');
$current_target = !empty($step['eligible_employee_id'])
    ? ['kind' => 'employee', 'employee_id' => (int)$step['eligible_employee_id']]
    : (!empty($step['eligible_job_title_id'])
        ? ['kind' => 'position', 'job_title_id' => (int)$step['eligible_job_title_id']]
        : ['kind' => 'none']);
$is_eligible = isUserEligibleForEvaluationReportingTarget($conn, $user_id, $current_target, $evaluation_id, (int)$step['step_order']);
$is_claimed_by_user = (int)($step['reviewer_user_id'] ?? 0) === $user_id;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $action = $_POST['action'] ?? '';
    $comments = trim((string)($_POST['comments'] ?? ''));
    if ($action === 'reassign' && $step['status'] === 'Blocked'
        && in_array($_SESSION['role'] ?? '', ['HR Manager', 'President and CEO', 'Admin'], true)) {
        $new_reviewer_id = (int)($_POST['reviewer_employee_id'] ?? 0);
        $position_stmt = $conn->prepare('SELECT department_id, rank_category_id FROM job_titles WHERE job_title_id = ? LIMIT 1');
        $blocked_position_id = (int)($step['eligible_job_title_id'] ?? 0);
        $position_stmt->bind_param('i', $blocked_position_id);
        $position_stmt->execute();
        $blocked_position = $position_stmt->get_result()->fetch_assoc();
        $position_stmt->close();
        $validated_reviewer = null;
        if ($blocked_position) {
            $candidate_stmt = $conn->prepare("SELECT e.employee_id, u.user_id FROM employees e
                JOIN users u ON u.employee_id = e.employee_id
                WHERE e.employee_id = ? AND e.department_id = ? AND e.rank_category_id = ?
                  AND e.employee_id <> ? AND NOT EXISTS (
                    SELECT 1 FROM evaluation_reporting_review_steps prior
                    WHERE prior.evaluation_id = ? AND prior.step_order < ?
                      AND prior.status = 'Completed' AND prior.reviewer_employee_id = e.employee_id)
                  AND e.is_active = 1 AND e.deleted_at IS NULL AND u.is_active = 1
                  AND COALESCE(u.account_hold, 0) = 0 AND u.deleted_at IS NULL LIMIT 1");
            $candidate_stmt->bind_param('iiiiii', $new_reviewer_id, $blocked_position['department_id'],
                $blocked_position['rank_category_id'], $evaluation['employee_id'], $evaluation_id, $step['step_order']);
            $candidate_stmt->execute();
            $validated_reviewer = $candidate_stmt->get_result()->fetch_assoc();
            $candidate_stmt->close();
        }
        if ($validated_reviewer) {
            $reassign = $conn->prepare("UPDATE evaluation_reporting_review_steps
                SET eligible_employee_id = ?, eligible_job_title_id = NULL, status = 'Pending'
                WHERE reporting_review_step_id = ? AND status = 'Blocked'");
            $reassign->bind_param('ii', $new_reviewer_id, $step['reporting_review_step_id']);
            $reassign->execute();
            $reassigned = $reassign->affected_rows === 1;
            $reassign->close();
            if ($reassigned) {
                createNotification($conn, (int)$validated_reviewer['user_id'], 'Evaluation Review Reassigned',
                    'An evaluation is waiting for your review after HR reassigned a vacant reporting level.',
                    BASE_URL . '/employee/reporting-reviews.php?evaluation_id=' . $evaluation_id);
                redirectWith(BASE_URL . '/employee/reporting-reviews.php?evaluation_id=' . $evaluation_id, 'success', 'Reporting review reassigned.');
            }
        }
        redirectWith(BASE_URL . '/employee/reporting-reviews.php?evaluation_id=' . $evaluation_id, 'danger', 'The selected active reviewer does not match the vacant reporting level.');
    }
    if ($action === 'claim' && $is_eligible && $step['status'] === 'Pending') {
        $claim = $conn->prepare("UPDATE evaluation_reporting_review_steps
            SET status = 'Claimed', reviewer_employee_id = ?, reviewer_user_id = ?, claimed_at = NOW()
            WHERE reporting_review_step_id = ? AND status = 'Pending'");
        $claim->bind_param('iii', $employee_id, $user_id, $step['reporting_review_step_id']);
        $claim->execute();
        $claimed = $claim->affected_rows === 1;
        $claim->close();
        if ($claimed) logAudit($conn, $user_id, 'UPDATE', 'Evaluation', $evaluation_id, 'Claimed individual reporting review');
        redirectWith(BASE_URL . '/employee/reporting-reviews.php?evaluation_id=' . $evaluation_id,
            $claimed ? 'success' : 'warning',
            $claimed ? 'You claimed this evaluation review.' : 'Another eligible reviewer claimed this evaluation first.');
    }

    if (in_array($action, ['endorse', 'return'], true) && $is_claimed_by_user && $step['status'] === 'Claimed') {
        $conn->begin_transaction();
        try {
            $next_step_status = $action === 'endorse' ? 'Completed' : 'Returned';
            $update_step = $conn->prepare("UPDATE evaluation_reporting_review_steps
                SET status = ?, comments = ?, acted_at = NOW()
                WHERE reporting_review_step_id = ? AND status = 'Claimed' AND reviewer_user_id = ?");
            $update_step->bind_param('ssii', $next_step_status, $comments, $step['reporting_review_step_id'], $user_id);
            $update_step->execute();
            if ($update_step->affected_rows !== 1) throw new RuntimeException('Review assignment changed before action.');
            $update_step->close();

            if ($action === 'return') {
                $status = 'Returned';
                $update_eval = $conn->prepare('UPDATE evaluations SET status = ? WHERE evaluation_id = ?');
                $update_eval->bind_param('si', $status, $evaluation_id);
                $update_eval->execute();
                $update_eval->close();
                $conn->commit();
                logAudit($conn, $user_id, 'UPDATE', 'Evaluation', $evaluation_id, 'Returned self-rating for revision' . ($comments !== '' ? ': ' . $comments : ''));
                $employee_user = $conn->prepare('SELECT user_id FROM users WHERE employee_id = ? AND is_active = 1 LIMIT 1');
                $employee_user->bind_param('i', $evaluation['employee_id']);
                $employee_user->execute();
                $employee_user_row = $employee_user->get_result()->fetch_assoc();
                $employee_user->close();
                if ($employee_user_row) {
                    createNotification($conn, (int)$employee_user_row['user_id'], 'Evaluation Returned',
                        'Your self-rating was returned for revision.' . ($comments !== '' ? ' Reviewer comments: ' . $comments : ''),
                        BASE_URL . '/employee/self-rating.php?edit=' . $evaluation_id);
                }
                redirectWith(BASE_URL . '/employee/reporting-reviews.php', 'success', 'Evaluation returned to the employee.');
            }

            $next_order = (int)$step['step_order'] + 1;
            $has_next = createEvaluationReportingReviewStep($conn, $evaluation_id, $next_order, $employee_id);
            if (!$has_next) {
                $status = 'Pending Team Consolidation';
                $update_eval = $conn->prepare('UPDATE evaluations SET status = ? WHERE evaluation_id = ?');
                $update_eval->bind_param('si', $status, $evaluation_id);
                $update_eval->execute();
                $update_eval->close();
            } else {
                $status = 'Pending Reporting Review';
                $update_eval = $conn->prepare('UPDATE evaluations SET status = ? WHERE evaluation_id = ?');
                $update_eval->bind_param('si', $status, $evaluation_id);
                $update_eval->execute();
                $update_eval->close();
            }
            $conn->commit();
            logAudit($conn, $user_id, 'UPDATE', 'Evaluation', $evaluation_id, 'Endorsed reporting review step ' . (int)$step['step_order'] . ($comments !== '' ? ': ' . $comments : ''));
            if (!$has_next) syncEvaluationToOrganizationPackage($conn, $evaluation_id);
            redirectWith(BASE_URL . '/employee/reporting-reviews.php', 'success',
                $has_next ? 'Review endorsed and routed to the next active reporting level.' : 'Reporting review completed and evaluation added to team consolidation.');
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('Reporting review action failed: ' . $e->getMessage());
            redirectWith(BASE_URL . '/employee/reporting-reviews.php?evaluation_id=' . $evaluation_id, 'danger', 'Unable to complete this review. Please try again or contact HR.');
        }
    }
    redirectWith(BASE_URL . '/employee/reporting-reviews.php?evaluation_id=' . $evaluation_id, 'danger', 'You are not authorized to perform that action.');
}

$scores_stmt = $conn->prepare("SELECT c.section, c.criterion_name, s.score_value, s.weighted_score
    FROM evaluation_scores s JOIN evaluation_criteria c ON c.criterion_id = s.criterion_id
    WHERE s.evaluation_id = ? ORDER BY c.section, c.sort_order");
$scores_stmt->bind_param('i', $evaluation_id);
$scores_stmt->execute();
$scores = $scores_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$scores_stmt->close();
$reassignment_candidates = [];
if ($step['status'] === 'Blocked' && $can_reassign) {
    $position_stmt = $conn->prepare('SELECT department_id, rank_category_id FROM job_titles WHERE job_title_id = ? LIMIT 1');
    $blocked_position_id = (int)($step['eligible_job_title_id'] ?? 0);
    $position_stmt->bind_param('i', $blocked_position_id);
    $position_stmt->execute();
    $blocked_position = $position_stmt->get_result()->fetch_assoc();
    $position_stmt->close();
    if ($blocked_position) {
        $candidates = $conn->prepare("SELECT e.employee_id, CONCAT(e.first_name, ' ', e.last_name, ' — ', e.job_title) AS employee_name
            FROM employees e JOIN users u ON u.employee_id = e.employee_id
            WHERE e.department_id = ? AND e.rank_category_id = ? AND e.is_active = 1 AND e.deleted_at IS NULL
              AND e.employee_id <> ? AND NOT EXISTS (
                SELECT 1 FROM evaluation_reporting_review_steps prior
                WHERE prior.evaluation_id = ? AND prior.step_order < ?
                  AND prior.status = 'Completed' AND prior.reviewer_employee_id = e.employee_id)
              AND u.is_active = 1 AND COALESCE(u.account_hold, 0) = 0 AND u.deleted_at IS NULL
            ORDER BY e.last_name, e.first_name");
        $candidates->bind_param('iiiii', $blocked_position['department_id'], $blocked_position['rank_category_id'],
            $evaluation['employee_id'], $evaluation_id, $step['step_order']);
        $candidates->execute();
        $reassignment_candidates = $candidates->get_result()->fetch_all(MYSQLI_ASSOC);
        $candidates->close();
    }
}

require_once '../includes/header.php';
?>
<div class="container-fluid py-4">
    <h1 class="h3 mb-3">Reporting-chain evaluation review</h1>
    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h5"><?php echo e($evaluation['first_name'] . ' ' . $evaluation['last_name']); ?> · <?php echo e($evaluation['template_name']); ?></h2>
            <p class="text-muted mb-0"><?php echo e($evaluation['job_title']); ?> · <?php echo e($evaluation['department_name'] ?? ''); ?> · <?php echo e($evaluation['evaluation_type']); ?> · <?php echo e($evaluation['evaluation_period_start']); ?> to <?php echo e($evaluation['evaluation_period_end']); ?></p>
        </div>
    </div>
    <?php if ($step['status'] === 'Blocked'): ?>
        <div class="alert alert-warning">No active employee currently occupies the configured reporting position. This evaluation is held for HR reassignment; the workflow will not skip that level.</div>
        <?php if ($can_reassign && $reassignment_candidates): ?>
            <form method="post" class="card mb-4">
                <div class="card-body">
                    <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">
                    <input type="hidden" name="evaluation_id" value="<?php echo $evaluation_id; ?>">
                    <input type="hidden" name="action" value="reassign">
                    <label class="form-label" for="reviewerEmployee">Assign an active employee at the vacant reporting level</label>
                    <select class="form-select mb-3" id="reviewerEmployee" name="reviewer_employee_id" required>
                        <option value="">Select an active reviewer</option>
                        <?php foreach ($reassignment_candidates as $candidate): ?>
                            <option value="<?php echo (int)$candidate['employee_id']; ?>"><?php echo e($candidate['employee_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-warning">Reassign review</button>
                </div>
            </form>
        <?php endif; ?>
    <?php elseif ($step['status'] === 'Pending' && $is_eligible): ?>
        <form method="post" class="card mb-4">
            <div class="card-body">
                <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">
                <input type="hidden" name="evaluation_id" value="<?php echo $evaluation_id; ?>">
                <input type="hidden" name="action" value="claim">
                <button class="btn btn-primary">Accept review responsibility</button>
                <p class="small text-muted mt-2 mb-0">If multiple active employees are eligible, the first person to claim becomes the assigned reviewer.</p>
            </div>
        </form>
    <?php elseif ($step['status'] === 'Claimed' && $is_claimed_by_user): ?>
        <form method="post" class="card mb-4">
            <div class="card-body">
                <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">
                <input type="hidden" name="evaluation_id" value="<?php echo $evaluation_id; ?>">
                <label class="form-label" for="reviewComments">Reviewer comments</label>
                <textarea class="form-control mb-3" id="reviewComments" name="comments" rows="3"></textarea>
                <button class="btn btn-success me-2" name="action" value="endorse">Endorse and route onward</button>
                <button class="btn btn-outline-danger" name="action" value="return">Return for revision</button>
            </div>
        </form>
    <?php else: ?>
        <div class="alert alert-info">This reporting review is assigned to another eligible reviewer or is no longer available.</div>
    <?php endif; ?>
    <div class="card">
        <div class="card-header fw-semibold">Self-rated items</div>
        <div class="table-responsive"><table class="table table-striped mb-0">
            <thead><tr><th>Section</th><th>Criterion</th><th>Self-rating</th><th>Weighted score</th></tr></thead>
            <tbody><?php foreach ($scores as $score): ?>
                <tr><td><?php echo e($score['section']); ?></td><td><?php echo e($score['criterion_name']); ?></td><td><?php echo number_format((float)$score['score_value'], 2); ?></td><td><?php echo number_format((float)$score['weighted_score'], 2); ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
