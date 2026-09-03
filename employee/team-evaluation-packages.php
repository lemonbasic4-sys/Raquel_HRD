<?php
$page_title = 'Team Evaluation Packages';
require_once '../includes/session-check.php';
require_once '../includes/functions.php';

// HR Manager and HR Supervisor consolidate via HRIS (their sidebar links here directly).
// Only HR Staff and Employee-role users in HR dept are blocked from team management.
$_session_role = $_SESSION['role'] ?? '';
if ($_session_role === 'HR Staff' ||
    ($_session_role === 'Employee' && (function() use ($conn) {
        $employee_id = (int)($_SESSION['employee_id'] ?? 0);
        $s = $conn->prepare("SELECT d.department_name FROM employees e LEFT JOIN departments d ON e.department_id = d.department_id WHERE e.employee_id = ? LIMIT 1");
        $s->bind_param('i', $employee_id); $s->execute();
        $dept = $s->get_result()->fetch_assoc()['department_name'] ?? ''; $s->close();
        return strcasecmp($dept, 'Human Resources') === 0;
    })())) {
    redirectWith(BASE_URL . '/employee/dashboard.php', 'info', 'Human Resource personnel perform self-rating only on the Employee Portal. Team management is handled via HRIS.');
}

ensureOrganizationEvaluationPackageSchema($conn);
syncPendingOrganizationPackageGovernanceApprovers($conn);
$user_id = (int) $_SESSION['user_id'];
$reviewer_match = organizationPackageReviewerMatchSql('rs');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $package_id = (int) ($_POST['package_id'] ?? 0);
    $action = $_POST['package_action'] ?? '';
    $comments = trim($_POST['comments'] ?? '');

    if (isOrganizationPackageLocked($conn, $package_id)) {
        redirectWith(BASE_URL . '/employee/team-evaluation-packages.php', 'danger', 'This package is locked after Board approval. Scores and route actions can no longer be changed.');
    }

    $step_stmt = $conn->prepare("SELECT rs.*, ep.department_id, ep.template_id, ep.period_start, ep.period_end, ep.status AS package_status, d.department_name
        FROM evaluation_package_route_steps rs
        JOIN evaluation_packages ep ON ep.package_id = rs.package_id
        JOIN departments d ON d.department_id = ep.department_id
        WHERE rs.package_id = ? AND $reviewer_match AND rs.action_status = 'Pending' LIMIT 1");
    $step_stmt->bind_param('iii', $package_id, $user_id, $user_id);
    $step_stmt->execute();
    $step = $step_stmt->get_result()->fetch_assoc();
    $step_stmt->close();
    if (!$step) {
        redirectWith(BASE_URL . '/employee/team-evaluation-packages.php', 'danger', 'This package is not currently assigned to you for action.');
    }

    // F7 Action: Consolidator returns individual member self-rating back to employee
    if ($action === 'return_member') {
        $member_eval_id = (int) ($_POST['member_evaluation_id'] ?? 0);
        if ($member_eval_id <= 0) {
            redirectWith(BASE_URL . '/employee/team-evaluation-packages.php', 'danger', 'Select a member evaluation to return.');
        }
        if ((int)$step['step_order'] !== 1) {
            redirectWith(BASE_URL . '/employee/team-evaluation-packages.php', 'danger', 'Only the initial consolidator can return individual member evaluations to employees.');
        }

        $conn->query("UPDATE evaluations SET status = 'Returned' WHERE evaluation_id = $member_eval_id");
        $conn->query("DELETE FROM evaluation_package_members WHERE package_id = $package_id AND evaluation_id = $member_eval_id");
        $conn->query("UPDATE evaluation_packages SET status = 'Pending Self-Ratings', current_step_order = NULL WHERE package_id = $package_id");
        $conn->query("UPDATE evaluation_package_route_steps SET action_status = 'Waiting' WHERE package_id = $package_id AND step_order = 1");

        $audit = $conn->prepare("INSERT INTO evaluation_package_audit (package_id, user_id, action, remarks) VALUES (?, ?, 'MEMBER_RETURNED', ?)");
        $audit_remark = 'Returned member evaluation (ID: ' . $member_eval_id . ') to employee for revision.';
        $audit->bind_param('iis', $package_id, $user_id, $audit_remark);
        $audit->execute();
        $audit->close();

        $emp_user = $conn->query("SELECT u.user_id, e.first_name, e.last_name FROM evaluations ev JOIN users u ON u.employee_id = ev.employee_id WHERE ev.evaluation_id = $member_eval_id LIMIT 1")->fetch_assoc();
        if ($emp_user) {
            createNotification($conn, (int)$emp_user['user_id'], 'Self-Rating Returned for Revision', 'Your standing supervisor returned your self-rating for revision. Remarks: ' . ($comments ?: 'Please review and resubmit.'), BASE_URL . '/employee/self-rating.php?edit=' . $member_eval_id);
        }
        redirectWith(BASE_URL . '/employee/team-evaluation-packages.php', 'success', 'Member evaluation returned to employee for revision. Package reset to Pending Self-Ratings.');
    }

    // F7 Action: Consolidator cancels / drops entire package
    if ($action === 'cancel_package') {
        if ((int)$step['step_order'] !== 1) {
            redirectWith(BASE_URL . '/employee/team-evaluation-packages.php', 'danger', 'Only the initial consolidator can cancel or drop an evaluation package.');
        }

        // 1. Fetch affected members FIRST before any DELETE so we can notify them
        $member_users_rs = $conn->query("
            SELECT DISTINCT u.user_id, e.evaluation_id,
                   CONCAT(emp.first_name, ' ', emp.last_name) AS emp_name
            FROM evaluation_package_members pm
            JOIN evaluations e ON e.evaluation_id = pm.evaluation_id
            JOIN employees emp ON emp.employee_id = e.employee_id
            JOIN users u ON u.employee_id = emp.employee_id
            WHERE pm.package_id = $package_id
        ");
        $member_users = $member_users_rs ? $member_users_rs->fetch_all(MYSQLI_ASSOC) : [];

        // Also fetch supervisor name for the notification message
        $supervisor_row = $conn->query("SELECT CONCAT(e.first_name, ' ', e.last_name) AS sup_name FROM users u JOIN employees e ON e.employee_id = u.employee_id WHERE u.user_id = $user_id LIMIT 1")->fetch_assoc();
        $supervisor_name = $supervisor_row ? $supervisor_row['sup_name'] : 'Your supervisor';
        $drop_reason = $comments ?: 'No reason provided.';
        $dept_name = $step['department_name'] ?? 'your department';

        // 2. Cancel package and route steps
        $conn->query("UPDATE evaluation_packages SET status = 'Cancelled', current_step_order = NULL WHERE package_id = $package_id");
        $conn->query("UPDATE evaluation_package_route_steps SET action_status = 'Cancelled' WHERE package_id = $package_id");

        // 3. Reset all member evaluations back to Pending Self-Rating
        $conn->query("UPDATE evaluations e JOIN evaluation_package_members pm ON pm.evaluation_id = e.evaluation_id SET e.status = 'Pending Self-Rating', e.submitted_date = NULL WHERE pm.package_id = $package_id");
        $conn->query("UPDATE evaluations e JOIN employees emp ON emp.employee_id = e.employee_id JOIN evaluation_packages ep ON ep.department_id = emp.department_id AND ep.template_id = e.template_id SET e.status = 'Pending Self-Rating', e.submitted_date = NULL WHERE ep.package_id = $package_id AND e.status IN ('Pending Team Consolidation', 'Submitted', 'Pending Supervisor', 'Pending HR Consolidation')");

        // 4. Remove members from package
        $conn->query("DELETE FROM evaluation_package_members WHERE package_id = $package_id");

        $audit = $conn->prepare("INSERT INTO evaluation_package_audit (package_id, user_id, action, remarks) VALUES (?, ?, 'CANCELLED', ?)");
        $audit_remark = 'Consolidator cancelled and dropped evaluation package. Reason: ' . $drop_reason;
        $audit->bind_param('iis', $package_id, $user_id, $audit_remark);
        $audit->execute();
        $audit->close();

        // 5. Notify each affected employee individually
        foreach ($member_users as $mu) {
            $notif_title = 'Evaluation Package Dropped — Self-Rating Reset';
            $notif_body = "Your $dept_name evaluation package has been dropped by $supervisor_name. "
                . "Your self-rating has been reset to Pending and is open for revision. "
                . "Reason: $drop_reason";
            createNotification(
                $conn,
                (int)$mu['user_id'],
                $notif_title,
                $notif_body,
                BASE_URL . '/employee/self-rating.php?edit=' . (int)$mu['evaluation_id']
            );
        }

        redirectWith(BASE_URL . '/employee/team-evaluation-packages.php', 'warning', 'Evaluation package dropped. All ' . count($member_users) . ' team member(s) have been notified and their self-ratings reset.');
    }

    if ($action === 'return') {
        if ($comments === '') {
            redirectWith(BASE_URL . '/employee/team-evaluation-packages.php', 'danger', 'A return reason is required.');
        }
        if ((int) $step['step_order'] <= 1) {
            redirectWith(BASE_URL . '/employee/team-evaluation-packages.php', 'danger', 'The consolidator cannot return the whole package. Use Return on an individual member row or Cancel Package.');
        }
        $update = $conn->prepare("UPDATE evaluation_package_route_steps SET action_status = 'Returned', acted_at = NOW(), comments = ? WHERE package_route_step_id = ?");
        $update->bind_param('si', $comments, $step['package_route_step_id']);
        $update->execute();
        $update->close();

        $previous_order = (int) $step['step_order'] - 1;
        $previous_stmt = $conn->prepare('SELECT package_route_step_id, reviewer_user_id, reviewer_employee_id, step_label, step_type FROM evaluation_package_route_steps WHERE package_id = ? AND step_order = ? LIMIT 1');
        $previous_stmt->bind_param('ii', $package_id, $previous_order);
        $previous_stmt->execute();
        $previous = $previous_stmt->get_result()->fetch_assoc();
        $previous_stmt->close();
        if (!$previous) {
            redirectWith(BASE_URL . '/employee/team-evaluation-packages.php', 'danger', 'The previous reviewer could not be found in the route.');
        }

        $reopen = $conn->prepare("UPDATE evaluation_package_route_steps SET action_status = 'Pending', acted_at = NULL WHERE package_route_step_id = ?");
        $reopen->bind_param('i', $previous['package_route_step_id']);
        $reopen->execute();
        $reopen->close();

        $status = getOrganizationPackageStatusForStep($previous);
        $package_update = $conn->prepare('UPDATE evaluation_packages SET status = ?, current_step_order = ? WHERE package_id = ?');
        $package_update->bind_param('sii', $status, $previous_order, $package_id);
        $package_update->execute();
        $package_update->close();

        $audit = $conn->prepare("INSERT INTO evaluation_package_audit (package_id, user_id, action, remarks) VALUES (?, ?, 'RETURNED', ?)");
        $audit->bind_param('iis', $package_id, $user_id, $comments);
        $audit->execute();
        $audit->close();

        $prev_name = getOrganizationPackageReviewerDisplayName($conn, (int)($previous['reviewer_user_id'] ?? 0), (int)($previous['reviewer_employee_id'] ?? 0));
        if (!empty($previous['reviewer_employee_id'])) {
            notifyUsersForEmployee($conn, (int)$previous['reviewer_employee_id'], 'Team evaluation returned for revision', 'A package was returned to you by ' . $step['step_label'] . '. Reason: ' . $comments, BASE_URL . '/employee/team-evaluation-packages.php');
        } elseif (!empty($previous['reviewer_user_id'])) {
            createNotification($conn, (int) $previous['reviewer_user_id'], 'Team evaluation returned for revision', 'A package was returned to you by ' . $step['step_label'] . '. Reason: ' . $comments, BASE_URL . '/employee/team-evaluation-packages.php');
        }

        redirectWith(BASE_URL . '/employee/team-evaluation-packages.php', 'success', 'Evaluation package returned to ' . $prev_name . ' (' . $previous['step_label'] . ') for revision.');
    }

    if ($step['step_type'] === 'Consolidation') {
        $expected_stmt = $conn->prepare("SELECT COUNT(DISTINCT e.employee_id) AS total FROM employees e JOIN users u ON u.employee_id = e.employee_id AND u.is_active = 1 WHERE e.department_id = ? AND e.is_active = 1 AND e.deleted_at IS NULL");
        $expected_stmt->bind_param('i', $step['department_id']);
        $expected_stmt->execute();
        $expected = (int) $expected_stmt->get_result()->fetch_assoc()['total'];
        $expected_stmt->close();

        $submitted_stmt = $conn->prepare("SELECT COUNT(DISTINCT ev.employee_id) AS total FROM evaluations ev JOIN employees e ON e.employee_id = ev.employee_id
            WHERE e.department_id = ? AND ev.template_id = ? AND ev.evaluation_period_start = ? AND ev.evaluation_period_end = ?
              AND ev.deleted_at IS NULL AND ev.status NOT IN ('Draft', 'Pending Self-Rating', 'Returned', 'Rejected')");
        $submitted_stmt->bind_param('iiss', $step['department_id'], $step['template_id'], $step['period_start'], $step['period_end']);
        $submitted_stmt->execute();
        $submitted = (int) $submitted_stmt->get_result()->fetch_assoc()['total'];
        $submitted_stmt->close();

        if ($submitted < $expected) {
            redirectWith(BASE_URL . '/employee/team-evaluation-packages.php', 'danger', "Consolidation is blocked: $submitted of $expected required team self-ratings are submitted.");
        }
        recalculateOrganizationPackageBehaviorScore($conn, $package_id);
    }

    $approve = $conn->prepare("UPDATE evaluation_package_route_steps SET action_status = 'Approved', acted_at = NOW(), comments = ? WHERE package_route_step_id = ?");
    $approve->bind_param('si', $comments, $step['package_route_step_id']);
    $approve->execute();
    $approve->close();

    $next_order = (int) $step['step_order'] + 1;
    $next_stmt = $conn->prepare('SELECT package_route_step_id, reviewer_user_id, reviewer_employee_id, step_label, step_type FROM evaluation_package_route_steps WHERE package_id = ? AND step_order = ? LIMIT 1');
    $next_stmt->bind_param('ii', $package_id, $next_order);
    $next_stmt->execute();
    $next = $next_stmt->get_result()->fetch_assoc();
    $next_stmt->close();

    if ($next) {
        $next_update = $conn->prepare("UPDATE evaluation_package_route_steps SET action_status = 'Pending' WHERE package_route_step_id = ?");
        $next_update->bind_param('i', $next['package_route_step_id']);
        $next_update->execute();
        $next_update->close();

        $status = getOrganizationPackageStatusForStep($next);
        $package_update = $conn->prepare('UPDATE evaluation_packages SET current_step_order = ?, status = ? WHERE package_id = ?');
        $package_update->bind_param('isi', $next_order, $status, $package_id);
        $package_update->execute();
        $package_update->close();

        $next_name = getOrganizationPackageReviewerDisplayName($conn, (int)($next['reviewer_user_id'] ?? 0), (int)($next['reviewer_employee_id'] ?? 0));
        notifyOrganizationPackageStepAssignees($conn, $package_id, $next_order, 'Team evaluation package awaiting your review', 'The ' . $step['department_name'] . ' evaluation package was approved by ' . $step['step_label'] . ' and forwarded for your review: ' . $next['step_label'] . '.');

        $audit = $conn->prepare("INSERT INTO evaluation_package_audit (package_id, user_id, action, remarks) VALUES (?, ?, 'APPROVED', ?)");
        $audit->bind_param('iis', $package_id, $user_id, $comments);
        $audit->execute();
        $audit->close();

        redirectWith(BASE_URL . '/employee/team-evaluation-packages.php', 'success', 'Evaluation package successfully adjusted and forwarded to ' . $next_name . ' (' . $next['step_label'] . ').');
    } else {
        // Final governance step (Board of Directors) locks and applies.
        $is_final_board = ($step['step_type'] === 'Governance') && (stripos($step['step_label'], 'Board') !== false);
        if (!$is_final_board) {
            redirectWith(BASE_URL . '/employee/team-evaluation-packages.php', 'danger', 'Final application requires Board of Directors approval. Assign a Board approver under Evaluation Governance.');
        }

        $conn->begin_transaction();
        $applied = applyOrganizationPackageResults($conn, $package_id);
        if ($applied) {
            $package_update = $conn->prepare("UPDATE evaluation_packages SET status = 'Approved and Applied', current_step_order = NULL WHERE package_id = ?");
            $package_update->bind_param('i', $package_id);
            $package_update->execute();
            $package_update->close();

            $lock_audit = $conn->prepare("INSERT INTO evaluation_package_audit (package_id, user_id, action, remarks) VALUES (?, ?, 'LOCKED_AND_APPLIED', ?)");
            $lock_remark = 'Board approval locked and applied final package results to all team members.';
            $lock_audit->bind_param('iis', $package_id, $user_id, $lock_remark);
            $lock_audit->execute();
            $lock_audit->close();
            $conn->commit();

            $members_stmt = $conn->prepare('SELECT DISTINCT u.user_id FROM evaluation_package_members pm JOIN evaluations e ON e.evaluation_id = pm.evaluation_id JOIN users u ON u.employee_id = e.employee_id AND u.is_active = 1 WHERE pm.package_id = ?');
            $members_stmt->bind_param('i', $package_id);
            $members_stmt->execute();
            $member_users = $members_stmt->get_result();
            while ($member_user = $member_users->fetch_assoc()) {
                createNotification($conn, (int) $member_user['user_id'], 'Evaluation Approved & Finalized', 'Your team evaluation package has completed the organizational approval flow and is now locked.', BASE_URL . '/employee/evaluation-history.php');
            }
            $members_stmt->close();

            // F5: Also notify all route reviewers and HR Managers/Supervisors
            $reviewers_stmt = $conn->prepare('SELECT DISTINCT reviewer_user_id FROM evaluation_package_route_steps WHERE package_id = ? AND reviewer_user_id IS NOT NULL');
            $reviewers_stmt->bind_param('i', $package_id);
            $reviewers_stmt->execute();
            $route_revs = $reviewers_stmt->get_result();
            while ($r_row = $route_revs->fetch_assoc()) {
                createNotification($conn, (int)$r_row['reviewer_user_id'], 'Team Package Approved & Finalized', 'The ' . $step['department_name'] . ' evaluation package has received Board approval and all scores are locked and applied.', BASE_URL . '/employee/team-evaluation-history.php');
            }
            $reviewers_stmt->close();

            redirectWith(BASE_URL . '/employee/team-evaluation-packages.php', 'success', 'Evaluation package approved, locked, and applied to all team members.');
        } else {
            $conn->rollback();
            redirectWith(BASE_URL . '/employee/team-evaluation-packages.php', 'danger', 'The package could not be applied because its shared behavior score is incomplete.');
        }
    }
}

require_once '../includes/header.php';

// Pending packages awaiting this reviewer's action right now
$packages_stmt = $conn->prepare("SELECT ep.*, d.department_name, et.template_name, et.kra_weight, et.behavior_weight, rs.package_route_step_id, rs.step_label, rs.step_type, rs.action_status
    FROM evaluation_packages ep
    JOIN evaluation_package_route_steps rs ON rs.package_id = ep.package_id
    JOIN departments d ON d.department_id = ep.department_id
    JOIN evaluation_templates et ON et.template_id = ep.template_id
    WHERE $reviewer_match AND rs.action_status = 'Pending'
    ORDER BY ep.updated_at DESC");
$packages_stmt->bind_param('ii', $user_id, $user_id);
$packages_stmt->execute();
$packages = $packages_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$packages_stmt->close();

// F2/F8: Waiting packages (strictly step 1 consolidators whose teams are still submitting self-ratings)
$waiting_stmt = $conn->prepare("SELECT ep.*, d.department_name, et.template_name, et.kra_weight, et.behavior_weight, rs.step_label
    FROM evaluation_packages ep
    JOIN evaluation_package_route_steps rs ON rs.package_id = ep.package_id AND rs.step_order = 1
    JOIN departments d ON d.department_id = ep.department_id
    JOIN evaluation_templates et ON et.template_id = ep.template_id
    WHERE $reviewer_match AND rs.action_status = 'Waiting'
    ORDER BY ep.updated_at DESC");
$waiting_stmt->bind_param('ii', $user_id, $user_id);
$waiting_stmt->execute();
$waiting_packages = $waiting_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$waiting_stmt->close();
?>
<main class="evaluation-packages container-fluid py-4">
    <section class="package-hero">
        <p class="mb-1 text-uppercase fw-bold" style="letter-spacing:1px; color:var(--rp-primary-gold-light); font-size:0.85rem;">
            Organization-Driven Performance Review
        </p>
        <h1 class="h3 mb-2 fw-bold">Team Evaluation Packages</h1>
        <p class="mb-3">
            Review and adjust consolidated department evaluations assigned to you. Individual KRA remains employee-specific; Core Behaviors &amp; Values is consolidated as a shared team result.
        </p>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-sm btn-outline-light px-3 py-2 fw-semibold" href="<?php echo BASE_URL; ?>/employee/evaluation-history.php">
                <i class="fas fa-history me-1"></i>My Evaluation History
            </a>
            <a class="btn btn-sm btn-outline-light px-3 py-2 fw-semibold" href="<?php echo BASE_URL; ?>/employee/team-evaluation-history.php">
                <i class="fas fa-users me-1"></i>Team Evaluation History
            </a>
        </div>
    </section>

    <?php if (!$packages && !$waiting_packages): ?>
        <section class="package-empty">
            <i class="fas fa-layer-group fa-3x text-muted mb-3" style="opacity:0.4;"></i>
            <h2 class="h5 fw-bold">No team package is currently waiting for your review</h2>
            <p class="mb-0 text-muted">
                When all department members submit their self-ratings, the standing supervisor receives the package here. Higher reviewers will be notified when earlier evaluators complete their turn.
            </p>
        </section>
    <?php endif; ?>

    <!-- Waiting for Submissions (Step 1 Consolidators) -->
    <?php foreach ($waiting_packages as $package): ?>
        <?php
        $summary = getOrganizationPackageSubmissionSummary($conn, $package);
        ?>
        <article class="package-card" role="region" aria-label="<?php echo e($package['department_name']); ?> Pending Submissions">
            <header class="package-card__header">
                <div>
                    <h2 class="h5 mb-1 fw-bold"><?php echo e($package['department_name']); ?> &mdash; <?php echo e($package['template_name']); ?></h2>
                    <p class="mb-0 text-muted small"><i class="fas fa-calendar-alt me-1"></i>Cycle: <?php echo e($package['period_start']); ?> to <?php echo e($package['period_end']); ?></p>
                </div>
                <span class="pipeline-badge pipeline-badge--waiting">
                    <i class="fas fa-user-clock me-1"></i>Waiting for Team Self-Ratings (<?php echo $summary['submitted']; ?>/<?php echo $summary['required']; ?>)
                </span>
            </header>
            <div class="package-card__body">
                <div class="alert alert-warning py-2 px-3 small mb-3">
                    <i class="fas fa-exclamation-triangle me-1"></i><strong>Consolidation Pending:</strong> All team members must submit their self-ratings before you can consolidate and forward this package.
                </div>
                <div class="table-responsive">
                    <table class="package-table table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Team Member</th>
                                <th>Position</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($summary['members'] as $m): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo e($m['employee_name']); ?></td>
                                    <td><?php echo e($m['job_title']); ?></td>
                                    <td>
                                        <?php if ($m['is_submitted']): ?>
                                            <span class="badge bg-success"><i class="fas fa-check me-1"></i>Submitted Self-Rating</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>Pending Self-Rating</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </article>
    <?php endforeach; ?>

    <!-- Active Packages Awaiting Action -->
    <?php foreach ($packages as $package): ?>
        <?php
        $can_adjust = ($package['step_type'] !== 'Governance');
        $is_board_step = ($package['step_type'] === 'Governance') && (stripos($package['step_label'], 'Board') !== false);
        $members_stmt = $conn->prepare("SELECT e.evaluation_id, emp.first_name, emp.last_name, emp.job_title,
                e.kra_subtotal, e.behavior_average, e.total_score, e.status,
                EXISTS(SELECT 1 FROM evaluation_scores es WHERE es.evaluation_id = e.evaluation_id AND (es.supervisor_override_score IS NOT NULL OR es.dept_manager_override_score IS NOT NULL OR es.manager_override_score IS NOT NULL)) AS has_adjustments
            FROM evaluation_package_members pm
            JOIN evaluations e ON e.evaluation_id = pm.evaluation_id
            JOIN employees emp ON emp.employee_id = e.employee_id
            WHERE pm.package_id = ?
            ORDER BY emp.last_name, emp.first_name");
        $members_stmt->bind_param('i', $package['package_id']);
        $members_stmt->execute();
        $members = $members_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $members_stmt->close();

        $route_stmt = $conn->prepare('SELECT step_label, action_status, acted_at, comments FROM evaluation_package_route_steps WHERE package_id = ? ORDER BY step_order');
        $route_stmt->bind_param('i', $package['package_id']);
        $route_stmt->execute();
        $timeline = $route_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $route_stmt->close();

        // Next reviewer for modal summary
        $next_step_order = (int)$package['current_step_order'] + 1;
        $next_rev_stmt = $conn->prepare('SELECT step_label, reviewer_user_id, reviewer_employee_id FROM evaluation_package_route_steps WHERE package_id = ? AND step_order = ? LIMIT 1');
        $next_rev_stmt->bind_param('ii', $package['package_id'], $next_step_order);
        $next_rev_stmt->execute();
        $next_rev_info = $next_rev_stmt->get_result()->fetch_assoc();
        $next_rev_stmt->close();
        $next_reviewer_name = $next_rev_info ? getOrganizationPackageReviewerDisplayName($conn, (int)($next_rev_info['reviewer_user_id'] ?? 0), (int)($next_rev_info['reviewer_employee_id'] ?? 0)) : 'Board of Directors';
        ?>
        <article class="package-card" role="region" aria-label="<?php echo e($package['department_name']); ?> Evaluation Action Package">
            <header class="package-card__header">
                <div>
                    <h2 class="h5 mb-1 fw-bold"><?php echo e($package['department_name']); ?> &mdash; <?php echo e($package['template_name']); ?></h2>
                    <p class="mb-0 text-muted small"><i class="fas fa-calendar-alt me-1"></i>Period: <?php echo e($package['period_start']); ?> to <?php echo e($package['period_end']); ?></p>
                </div>
                <div>
                    <?php echo renderOrganizationPipelineBadge($conn, (int)$package['package_id']); ?>
                </div>
            </header>
            <div class="package-card__body">
                <div class="row g-3 mb-4">
                    <div class="col-sm-6">
                        <div class="package-stat">
                            <strong><?php echo count($members); ?> Members</strong>
                            Department Team Size
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="package-stat">
                            <strong><?php echo $package['shared_behavior_score'] !== null ? number_format((float)$package['shared_behavior_score'], 2) : 'Calculating…'; ?></strong>
                            Shared Behavior Score
                        </div>
                    </div>
                </div>

                <div class="table-responsive mb-4">
                    <table class="package-table table align-middle">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Position</th>
                                <th class="text-end">Individual KRA</th>
                                <th class="text-end">Self Behavior</th>
                                <th class="text-end">Total Score</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $member): ?>
                                <?php
                                $kra_w = isset($package['kra_weight']) && (float)$package['kra_weight'] > 0 ? (float)$package['kra_weight'] : 80;
                                $beh_w = isset($package['behavior_weight']) && (float)$package['behavior_weight'] > 0 ? (float)$package['behavior_weight'] : 20;
                                $beh_val = (float)$member['behavior_average'];
                                $total_score_val = calculateEvalTotal((float)$member['kra_subtotal'], $beh_val, $kra_w, $beh_w);
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?php echo e($member['first_name'] . ' ' . $member['last_name']); ?></div>
                                        <?php if (!empty($member['has_adjustments'])): ?>
                                            <span class="audit-chip audit-chip--adjusted">
                                                <i class="fas fa-pen-fancy me-1"></i>Adjusted by Evaluator
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo e($member['job_title']); ?></td>
                                    <td class="text-end tabular-nums fw-semibold"><?php echo number_format((float)$member['kra_subtotal'], 2); ?></td>
                                    <td class="text-end tabular-nums fw-semibold"><?php echo number_format((float)$member['behavior_average'], 2); ?></td>
                                    <td class="text-end tabular-nums fw-bold text-success" style="font-size: 1.05rem;"><?php echo number_format($total_score_val, 2); ?></td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo e($member['status']); ?></span>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($can_adjust): ?>
                                            <a class="btn-action-adjust btn btn-sm" href="<?php echo BASE_URL; ?>/employee/package-member-review.php?package_id=<?php echo (int)$package['package_id']; ?>&evaluation_id=<?php echo (int)$member['evaluation_id']; ?>" title="View ratings and make supervisor adjustments">
                                                <i class="fas fa-sliders-h me-1"></i>Adjust
                                            </a>
                                            <?php if ($package['step_type'] === 'Consolidation'): ?>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Return self-rating for <?php echo e($member['first_name'] . ' ' . $member['last_name']); ?> back to employee for revision?');">
                                                    <input type="hidden" name="package_id" value="<?php echo (int)$package['package_id']; ?>">
                                                    <input type="hidden" name="member_evaluation_id" value="<?php echo (int)$member['evaluation_id']; ?>">
                                                    <input type="hidden" name="package_action" value="return_member">
                                                    <?php echo csrfField(); ?>
                                                    <button type="submit" class="btn btn-sm btn-outline-warning ms-1" title="Return this member evaluation to employee for revision">
                                                        <i class="fas fa-undo me-1"></i>Return
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <!-- F4: Governance Read-Only Badge -->
                                            <span class="badge bg-light text-muted border me-1"><i class="fas fa-lock me-1"></i>Read-Only</span>
                                            <a class="btn-action-view btn btn-sm" href="<?php echo BASE_URL; ?>/employee/package-member-view.php?package_id=<?php echo (int)$package['package_id']; ?>&evaluation_id=<?php echo (int)$member['evaluation_id']; ?>" title="View read-only evaluation">
                                                <i class="fas fa-eye me-1"></i>View
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Approval Path Timeline -->
                <section class="package-history mb-4">
                    <h3 class="h6 fw-bold text-uppercase text-muted" style="letter-spacing:0.5px;">Approval Timeline &amp; History</h3>
                    <ol class="package-timeline">
                        <?php foreach ($timeline as $entry): ?>
                            <li class="<?php echo $entry['action_status'] === 'Approved' ? 'is-approved' : ($entry['action_status'] === 'Returned' ? 'is-returned' : ''); ?>">
                                <div class="fw-bold text-dark"><?php echo e($entry['step_label']); ?></div>
                                <div class="small text-muted">
                                    <span class="badge <?php echo $entry['action_status'] === 'Approved' ? 'bg-success' : ($entry['action_status'] === 'Returned' ? 'bg-danger' : 'bg-warning text-dark'); ?>">
                                        <?php echo e($entry['action_status']); ?>
                                    </span>
                                    <?php if (!empty($entry['acted_at'])): ?> &bull; <?php echo e(date('M d, Y h:i A', strtotime($entry['acted_at']))); ?><?php endif; ?>
                                </div>
                                <?php if (!empty($entry['comments'])): ?>
                                    <div class="small p-2 bg-light rounded mt-1 border">
                                        <i class="fas fa-comment-dots text-muted me-1"></i>Remarks: <em><?php echo e($entry['comments']); ?></em>
                                    </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </section>

                <!-- Action Panel with descriptive hand-off copy & F3 Pre-Submission Modal -->
                <form method="post" class="package-action-panel" id="pkgForm-<?php echo (int)$package['package_id']; ?>">
                    <input type="hidden" name="package_id" value="<?php echo (int)$package['package_id']; ?>">
                    <input type="hidden" name="package_action" id="actionInput-<?php echo (int)$package['package_id']; ?>" value="approve">
                    <?php echo csrfField(); ?>
                    
                    <?php if ($package['step_type'] === 'Consolidation'): ?>
                        <div class="alert alert-info py-2 px-3 small mb-3">
                            <i class="fas fa-info-circle me-1"></i><strong>Consolidation step:</strong> Shared Core Behaviors &amp; Values is calculated automatically from all team members. Use <strong>Adjust</strong> to adjust any member's score before submitting.
                        </div>
                    <?php elseif ($is_board_step): ?>
                        <div class="alert alert-warning py-2 px-3 small mb-3">
                            <i class="fas fa-shield-alt me-1"></i><strong>Final Board Step:</strong> Approving will lock this evaluation package, apply shared Behavior scores to all <?php echo count($members); ?> members, and publish final appraisal results.
                        </div>
                    <?php elseif ($package['step_type'] === 'Governance'): ?>
                        <div class="alert alert-secondary py-2 px-3 small mb-3">
                            <i class="fas fa-balance-scale me-1"></i>Audit Committee review stage. Ratings are read-only.
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label fw-bold" for="comments-<?php echo (int)$package['package_id']; ?>">
                            Review Remarks &amp; Feedback <span class="text-muted fw-normal">(Optional for approval, required for returns)</span>
                        </label>
                        <textarea class="form-control" id="comments-<?php echo (int)$package['package_id']; ?>" name="comments" rows="3" placeholder="Enter endorsement remarks or return instructions…"></textarea>
                    </div>

                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <!-- F3: Trigger Pre-Submission Confirmation Modal -->
                        <button class="btn-action-primary btn" type="button" data-bs-toggle="modal" data-bs-target="#confirmApproveModal-<?php echo (int)$package['package_id']; ?>">
                            <?php if ($is_board_step): ?>
                                <i class="fas fa-lock me-1"></i>Approve, Lock, and Apply Results
                            <?php else: ?>
                                <i class="fas fa-check-circle me-1"></i>Approve &amp; Forward to Next Reviewer
                            <?php endif; ?>
                        </button>

                        <?php if ($package['step_type'] !== 'Consolidation'): ?>
                            <button class="btn btn-outline-danger px-4 py-2 fw-bold" type="submit" onclick="document.getElementById('actionInput-<?php echo (int)$package['package_id']; ?>').value='return';" style="min-height:46px; border-radius:8px;">
                                <i class="fas fa-undo me-1"></i>Return for Revision
                            </button>
                        <?php endif; ?>

                        <?php if ($package['step_type'] === 'Consolidation'): ?>
                            <!-- F7: Drop / Cancel Package Action for Consolidator -->
                            <button class="btn btn-outline-secondary px-3 py-2 fw-bold ms-auto" type="submit" onclick="document.getElementById('actionInput-<?php echo (int)$package['package_id']; ?>').value='cancel_package'; return confirm('WARNING: Are you sure you want to cancel and drop this entire evaluation package? This will cancel the evaluation cycle for all members.');" style="min-height:46px; border-radius:8px;">
                                <i class="fas fa-ban me-1"></i>Cancel / Drop Package
                            </button>
                        <?php endif; ?>
                    </div>

                    <!-- F3: Pre-Submission Summary & Confirmation Modal -->
                    <div class="modal fade" id="confirmApproveModal-<?php echo (int)$package['package_id']; ?>" tabindex="-1" aria-labelledby="confirmModalLabel-<?php echo (int)$package['package_id']; ?>" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered">
                            <div class="modal-content border-0 shadow">
                                <div class="modal-header bg-dark text-white">
                                    <h5 class="modal-title fw-bold" id="confirmModalLabel-<?php echo (int)$package['package_id']; ?>">
                                        <i class="fas fa-clipboard-check me-2 text-warning"></i>Confirm Evaluation Package Hand-Off
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body p-4">
                                    <div class="alert alert-info py-2 px-3 small mb-3">
                                        <strong>Department:</strong> <?php echo e($package['department_name']); ?> &bull; 
                                        <strong>Cycle:</strong> <?php echo e($package['period_start']); ?> to <?php echo e($package['period_end']); ?><br>
                                        <strong>Next Stage:</strong> <?php echo e($next_rev_info['step_label'] ?? ($is_board_step ? 'Final Lock' : 'Next Reviewer')); ?> (<?php echo e($next_reviewer_name); ?>)
                                    </div>

                                    <h6 class="fw-bold mb-2 text-dark">Package Members Summary:</h6>
                                    <div class="table-responsive mb-3">
                                        <table class="table table-sm table-bordered align-middle mb-0" style="font-size:0.88rem;">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Member</th>
                                                    <th>Position</th>
                                                    <th class="text-end">KRA Subtotal</th>
                                                    <th class="text-end">Behavior Score</th>
                                                    <th>Adjustment Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($members as $m_sum): ?>
                                                    <tr>
                                                        <td class="fw-bold"><?php echo e($m_sum['first_name'] . ' ' . $m_sum['last_name']); ?></td>
                                                        <td><?php echo e($m_sum['job_title']); ?></td>
                                                        <td class="text-end tabular-nums"><?php echo number_format((float)$m_sum['kra_subtotal'], 2); ?></td>
                                                        <td class="text-end tabular-nums"><?php echo number_format((float)$m_sum['behavior_average'], 2); ?></td>
                                                        <td>
                                                            <?php if (!empty($m_sum['has_adjustments'])): ?>
                                                                <span class="badge bg-warning text-dark"><i class="fas fa-pen me-1"></i>Score Adjusted</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-light text-dark border">Original Self-Score</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="p-3 mb-3 rounded-3 shadow-sm" style="background-color: #fffbeb !important; border: 2px solid #f59e0b !important;">
                                        <div class="form-check d-flex align-items-start gap-3 ps-2">
                                            <input class="form-check-input flex-shrink-0 mt-1" type="checkbox" id="chkConfirm-<?php echo (int)$package['package_id']; ?>" style="width: 26px; height: 26px; cursor: pointer; border: 2px solid #d97706 !important;">
                                            <label class="form-check-label fw-bold text-dark mb-0 ms-2" for="chkConfirm-<?php echo (int)$package['package_id']; ?>" style="font-size: 0.98rem; line-height: 1.4; cursor: pointer;">
                                                <i class="fas fa-check-square me-1 text-warning fa-lg"></i>
                                                I confirm that I have thoroughly reviewed all member ratings, evaluator adjustments, and developmental plans for this department before forwarding to <span class="text-decoration-underline text-primary"><?php echo e($next_reviewer_name); ?></span>.
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer bg-light">
                                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-success px-4 fw-bold" onclick="submitPackageForm(<?php echo (int)$package['package_id']; ?>);">
                                        <i class="fas fa-paper-plane me-1"></i>Confirm &amp; Forward Package
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </article>
    <?php endforeach; ?>
</main>

<script>
function submitPackageForm(packageId) {
    const chk = document.getElementById('chkConfirm-' + packageId);
    if (chk && !chk.checked) {
        alert('Please check the confirmation checkbox below the members summary before forwarding the package.');
        chk.focus();
        return false;
    }
    const actionInp = document.getElementById('actionInput-' + packageId);
    if (actionInp) {
        actionInp.value = 'approve';
    }
    const form = document.getElementById('pkgForm-' + packageId);
    if (form) {
        form.submit();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
