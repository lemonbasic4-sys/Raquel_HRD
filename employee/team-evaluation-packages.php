<?php
$page_title = 'Performance Evaluation';
require_once '../includes/session-check.php';
require_once '../includes/functions.php';

// HR staff manage their own ratings from the staff portal, not this team page.
// Employee-role HR personnel use HRIS for team management.
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

// Default to the tab with the largest pending workload, matching the tab badges.
if ($_SERVER['REQUEST_METHOD']==='GET' && !isset($_GET['category'])
    && !isset($_GET['evaluation_id']) && !isset($_GET['cycle_id']) && !isset($_GET['view'])) {
    require_once __DIR__.'/../includes/quarterly-evaluations.php';
    require_once __DIR__.'/../includes/performance-evaluation-priority.php';
    $entryCategory=performanceEvaluationDefaultCategory($conn,(int)($_SESSION['user_id'] ?? 0),true);
    header('Location: '.BASE_URL.'/employee/team-evaluation-packages.php?category='.$entryCategory);
    exit;
}

// Managed regular-employee cycles use their own review engines.
if (($_GET['category'] ?? '') === 'quarterly') {
    $quarterly_in_performance_page = true;
    require_once __DIR__ . '/../includes/quarterly-evaluations.php';
    require __DIR__ . '/../includes/quarterly-evaluation-page.php';
    exit;
}

// The Regular tab uses the Annual engine, not the legacy package pipeline.
if (($_GET['category'] ?? '') === 'regular') {
    $annual_in_performance_page = true;
    require_once __DIR__ . '/../includes/annual-evaluations.php';
    require __DIR__ . '/../includes/annual-evaluation-page.php';
    exit;
}

ensureOrganizationEvaluationPackageSchema($conn);
ensureProbationaryEvaluationSchema($conn);
syncOpenHrProbationaryRoutes($conn);
syncOpenNonHrProbationaryRoutes($conn);
syncPendingOrganizationPackageGovernanceApprovers($conn);
syncWaitingOrganizationPackages($conn);
$user_id = (int) $_SESSION['user_id'];
$reviewer_match = organizationPackageReviewerMatchSql('rs');
$claim_profile_stmt = $conn->prepare("SELECT u.role, e.department_id, e.rank_category_id, e.employment_status, d.department_name
    FROM users u LEFT JOIN employees e ON e.employee_id = u.employee_id
    LEFT JOIN departments d ON d.department_id = e.department_id
    WHERE u.user_id = ? LIMIT 1");
$claim_profile_stmt->bind_param('i', $user_id);
$claim_profile_stmt->execute();
$claim_profile = $claim_profile_stmt->get_result()->fetch_assoc() ?: [];
$claim_profile_stmt->close();
$package_claim_role = in_array($claim_profile['role'] ?? '', ['HR Supervisor', 'HR Manager', 'President and CEO'], true)
    ? (string) $claim_profile['role'] : '';
$package_claim_rank_category = (($claim_profile['role'] ?? '') === 'Employee'
    && strcasecmp((string)($claim_profile['department_name'] ?? ''), 'Human Resources') !== 0
    && ($claim_profile['employment_status'] ?? '') === 'Regular'
    && in_array((int)($claim_profile['rank_category_id'] ?? 0), [1, 3, 4], true))
    ? (int) $claim_profile['rank_category_id'] : 0;
$package_claim_department_id = $package_claim_rank_category > 0 ? (int)($claim_profile['department_id'] ?? 0) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $package_id = (int) ($_POST['package_id'] ?? 0);
    // Keep post-action navigation on the category of the review just acted on.
    $return_category = ($_GET['category'] ?? '') === 'regular' ? 'regular' : 'probationary';
    if ($package_id > 0) {
        $category_stmt = $conn->prepare('SELECT evaluation_type FROM evaluation_packages WHERE package_id=? LIMIT 1');
        $category_stmt->bind_param('i', $package_id);
        $category_stmt->execute();
        $package_category_row = $category_stmt->get_result()->fetch_assoc();
        $category_stmt->close();
        if ($package_category_row) $return_category = in_array($package_category_row['evaluation_type'], ['Initial','Final'], true) ? 'probationary' : 'regular';
    }
    $package_return_url = BASE_URL . '/employee/team-evaluation-packages.php?category=' . $return_category;
    if ($package_id > 0 && $return_category === 'probationary') {
        $package_return_url .= '&open_package=' . $package_id . '#package-' . $package_id;
    }
    $action = $_POST['package_action'] ?? '';
    $comments = trim($_POST['comments'] ?? '');

    if ($action === 'claim') {
        $claim_role = $package_claim_role;
        $claim_rank_category = $package_claim_rank_category;
        $claim_employee_id = (int) ($_SESSION['employee_id'] ?? 0);
        if ((!in_array($claim_role, ['HR Supervisor', 'HR Manager', 'President and CEO'], true) && !in_array($claim_rank_category, [1, 3, 4], true))
            || $claim_employee_id <= 0 || $package_id <= 0) {
            redirectWith($package_return_url, 'danger', 'Only an eligible HR reviewer or department supervisor/manager can claim this evaluation review.');
        }

        if ($claim_role !== '') {
            $eligible_user = $conn->prepare("SELECT u.user_id FROM users u
                JOIN employees e ON e.employee_id = u.employee_id
                WHERE u.user_id = ? AND u.employee_id = ? AND u.role = ? AND u.is_active = 1
                  AND e.is_active = 1 AND e.deleted_at IS NULL
                  AND COALESCE(u.account_hold, 0) = 0 LIMIT 1");
            $eligible_user->bind_param('iis', $user_id, $claim_employee_id, $claim_role);
        } else {
            $eligible_user = $conn->prepare("SELECT u.user_id FROM users u
                JOIN employees e ON e.employee_id = u.employee_id
                WHERE u.user_id = ? AND u.employee_id = ? AND u.role = 'Employee' AND u.is_active = 1
                  AND e.is_active = 1 AND e.deleted_at IS NULL AND e.department_id = ?
                  AND e.rank_category_id = ? AND COALESCE(u.account_hold, 0) = 0 LIMIT 1");
            $eligible_user->bind_param('iiii', $user_id, $claim_employee_id, $package_claim_department_id, $claim_rank_category);
        }
        $eligible_user->execute();
        $is_eligible = (bool) $eligible_user->get_result()->fetch_assoc();
        $eligible_user->close();
        if (!$is_eligible) {
            redirectWith($package_return_url, 'danger', 'Your account is not eligible to accept this review stage.');
        }

        try {
            $conn->begin_transaction();
            if ($claim_role !== '') {
                $claim_stmt = $conn->prepare("UPDATE evaluation_package_route_steps rs
                    JOIN evaluation_packages ep ON ep.package_id = rs.package_id
                    SET rs.reviewer_employee_id = ?, rs.reviewer_user_id = ?, rs.claimed_at = NOW()
                    WHERE rs.package_id = ? AND rs.eligible_role = ? AND rs.eligible_rank_category_id IS NULL
                      AND rs.action_status = 'Pending' AND rs.claimed_at IS NULL
                      AND rs.reviewer_employee_id IS NULL AND rs.reviewer_user_id IS NULL
                      AND ep.current_step_order = rs.step_order
                      AND ep.status NOT IN ('Approved and Applied', 'Cancelled')");
                $claim_stmt->bind_param('iiis', $claim_employee_id, $user_id, $package_id, $claim_role);
            } else {
                $claim_stmt = $conn->prepare("UPDATE evaluation_package_route_steps rs
                    JOIN evaluation_packages ep ON ep.package_id = rs.package_id
                    SET rs.reviewer_employee_id = ?, rs.reviewer_user_id = ?, rs.claimed_at = NOW()
                    WHERE rs.package_id = ? AND rs.eligible_rank_category_id = ? AND rs.eligible_role IS NULL
                      AND ep.department_id = ? AND rs.action_status = 'Pending' AND rs.claimed_at IS NULL
                      AND rs.reviewer_employee_id IS NULL AND rs.reviewer_user_id IS NULL
                      AND ep.current_step_order = rs.step_order
                      AND EXISTS (SELECT 1 FROM users claimant_user
                          JOIN employees claimant ON claimant.employee_id = claimant_user.employee_id
                          WHERE claimant_user.user_id = ? AND claimant.employee_id = ?
                            AND claimant_user.role = 'Employee' AND claimant_user.is_active = 1
                            AND COALESCE(claimant_user.account_hold, 0) = 0
                            AND claimant.department_id = ep.department_id
                            AND claimant.rank_category_id = rs.eligible_rank_category_id
                            AND claimant.employment_status = 'Regular'
                            AND claimant.is_active = 1 AND claimant.deleted_at IS NULL)
                      AND ep.status NOT IN ('Approved and Applied', 'Cancelled')");
                $claim_stmt->bind_param('iiiiiii', $claim_employee_id, $user_id, $package_id, $claim_rank_category, $package_claim_department_id, $user_id, $claim_employee_id);
            }
            $claim_stmt->execute();
            $claimed = $claim_stmt->affected_rows === 1;
            $claim_stmt->close();

            if (!$claimed) {
                $conn->rollback();
                redirectWith($package_return_url, 'warning', 'Another eligible reviewer has already claimed this evaluation review, or it is no longer awaiting a reviewer.');
            }

            $step_info = $conn->prepare('SELECT step_type FROM evaluation_package_route_steps WHERE package_id = ? AND reviewer_user_id = ? AND claimed_at IS NOT NULL LIMIT 1');
            $step_info->bind_param('ii', $package_id, $user_id);
            $step_info->execute();
            $claimed_step = $step_info->get_result()->fetch_assoc();
            $step_info->close();
            if (($claimed_step['step_type'] ?? '') === 'Consolidation') {
                $owner_update = $conn->prepare('UPDATE evaluation_packages SET consolidator_employee_id = ? WHERE package_id = ?');
                $owner_update->bind_param('ii', $claim_employee_id, $package_id);
                $owner_update->execute();
                $owner_update->close();
            }

            $audit = $conn->prepare("INSERT INTO evaluation_package_audit (package_id, user_id, action, remarks)
                VALUES (?, ?, 'PACKAGE_ACCEPTED', ?)");
            $claimant_label = $claim_role !== '' ? $claim_role : ($claim_rank_category === 4 ? 'Department Supervisor' : 'Department Manager');
            $audit_remarks = $claimant_label . ' accepted responsibility for this evaluation review stage.';
            $audit->bind_param('iis', $package_id, $user_id, $audit_remarks);
            $audit->execute();
            $audit->close();
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('Unable to claim evaluation package: ' . $e->getMessage());
            redirectWith($package_return_url, 'danger', 'The evaluation review could not be claimed. Refresh the page and try again.');
        }

        redirectWith($package_return_url, 'success', 'Evaluation review claimed. It is now assigned to you.');
    }

    // Late Member Catch-Up Endorsement (Supervisor -> HR Manager -> Active Pipeline)
    if ($action === 'endorse_catchup') {
        $catchup_pkg_id  = (int)($_POST['catchup_package_id'] ?? 0);
        $catchup_eval_id = (int)($_POST['catchup_evaluation_id'] ?? 0);
        $catchup_remarks = trim($_POST['catchup_comments'] ?? '');
        $current_role    = $_SESSION['role'] ?? '';
        $current_emp_id  = (int)($_SESSION['employee_id'] ?? 0);

        // Fetch current member status to determine the appropriate endorser role
        $m_stmt = $conn->prepare("SELECT member_status FROM evaluation_package_members WHERE package_id = ? AND evaluation_id = ? LIMIT 1");
        $m_stmt->bind_param('ii', $catchup_pkg_id, $catchup_eval_id);
        $m_stmt->execute();
        $m_status = $m_stmt->get_result()->fetch_assoc()['member_status'] ?? '';
        $m_stmt->close();

        if ($m_status === 'Pending Supervisor Catchup') {
            $endorser_role = 'supervisor';
        } elseif (in_array($current_role, ['HR Manager', 'HR Supervisor', 'Admin'], true)) {
            $endorser_role = 'hr_manager';
        } else {
            $is_active_reviewer = (bool) $conn->query("SELECT 1 FROM evaluation_package_route_steps WHERE package_id = $catchup_pkg_id AND reviewer_employee_id = $current_emp_id AND action_status = 'Pending' LIMIT 1")->fetch_assoc();
            $endorser_role = $is_active_reviewer ? 'package_reviewer' : 'supervisor';
        }

        $res = endorseLatePackageMember($conn, $catchup_pkg_id, $catchup_eval_id, $endorser_role, $current_emp_id, $catchup_remarks);
        if ($res['ok']) {
            redirectWith($package_return_url, 'success', $res['message']);
        } else {
            redirectWith($package_return_url, 'danger', $res['message']);
        }
    }

    if (isOrganizationPackageLocked($conn, $package_id)) {
        redirectWith($package_return_url, 'danger', 'This evaluation review is locked after Board approval. Scores and route actions can no longer be changed.');
    }

    $step_stmt = $conn->prepare("SELECT rs.*, ep.department_id, ep.template_id, ep.evaluation_type, ep.period_start, ep.period_end, ep.status AS package_status, d.department_name
        FROM evaluation_package_route_steps rs
        JOIN evaluation_packages ep ON ep.package_id = rs.package_id
        JOIN departments d ON d.department_id = ep.department_id
        WHERE rs.package_id = ? AND $reviewer_match AND rs.action_status = 'Pending' LIMIT 1");
    $step_stmt->bind_param('iii', $package_id, $user_id, $user_id);
    $step_stmt->execute();
    $step = $step_stmt->get_result()->fetch_assoc();
    $step_stmt->close();
    if (!$step) {
        redirectWith($package_return_url, 'danger', 'This evaluation review is not currently assigned to you for action.');
    }

    $is_probationary_step = in_array($step['evaluation_type'] ?? '', ['Initial', 'Final'], true);
    $probationary_review = null;
    $probationary_eval_id = 0;
    $probationary_employee_id = 0;
    if ($is_probationary_step) {
        $review_data_stmt = $conn->prepare("SELECT ev.evaluation_id, ev.employee_id, ev.total_score, e.rank_category_id, per.recommendation,
                per.recommendation_reason, per.immediate_head_user_id, per.immediate_head_signature_data
            FROM evaluation_package_members pm
            JOIN evaluations ev ON ev.evaluation_id = pm.evaluation_id
            JOIN employees e ON e.employee_id = ev.employee_id
            LEFT JOIN probationary_evaluation_reviews per ON per.evaluation_id = ev.evaluation_id
            WHERE pm.package_id = ? LIMIT 1");
        $review_data_stmt->bind_param('i', $package_id);
        $review_data_stmt->execute();
        $probationary_review = $review_data_stmt->get_result()->fetch_assoc();
        $review_data_stmt->close();
        $probationary_eval_id = (int)($probationary_review['evaluation_id'] ?? 0);
        $probationary_employee_id = (int)($probationary_review['employee_id'] ?? 0);
    }
    $decision_review_url = BASE_URL . '/employee/package-member-review.php?package_id=' . $package_id
        . '&evaluation_id=' . $probationary_eval_id . '&attention=decision#tab-decision';

    if ($action === 'reject_regularization') {
        $decision_reason = trim($_POST['comments'] ?? '');
        if (!$is_probationary_step || $step['evaluation_type'] !== 'Final'
            || (int)($step['step_order'] ?? 0) !== 2
            || (int)($probationary_review['rank_category_id'] ?? 0) !== 5
            || isHumanResourcesPackageDepartment($conn, (int)$step['department_id'])
            || ($probationary_review['recommendation'] ?? '') !== 'Recommend regularization'
            || $decision_reason === '') {
            redirectWith($package_return_url, 'danger', 'A reason is required to decline this staff regularization recommendation.');
        }
        $reject_request = $conn->prepare("UPDATE regularization_requests
            SET status = 'Rejected', decided_by = ?, decided_at = NOW(), decision_reason = ?
            WHERE evaluation_id = ? AND status = 'Pending'");
        $reject_request->bind_param('isi', $user_id, $decision_reason, $probationary_eval_id);
        $reject_request->execute();
        $rejected = $reject_request->affected_rows === 1;
        $reject_request->close();
        if (!$rejected) {
            redirectWith($package_return_url, 'danger', 'The regularization request is no longer pending.');
        }
        $action = 'approve';
        $comments = $decision_reason;
    }

    if ($action === 'save_probationary_decision') {
        if (!$is_probationary_step
            || $step['evaluation_type'] !== 'Final'
            || (int)($step['step_order'] ?? 0) !== 1 || $probationary_eval_id <= 0) {
            redirectWith($package_return_url, 'danger', 'Only the assigned reviewer can record the probationary recommendation.');
        }
        $recommendation = trim($_POST['recommendation'] ?? '');
        $recommendation_reason = trim($_POST['recommendation_reason'] ?? '');
        $head_signature = trim($_POST['immediate_head_signature_data'] ?? '');
        if (!in_array($recommendation, ['Recommend regularization', 'Continue training'], true)
            || $recommendation_reason === ''
            || !preg_match('/^data:image\/png;base64,/', $head_signature)
            || strlen($head_signature) > 100000) {
            redirectWith($decision_review_url, 'danger', 'Choose a recommendation, enter its reason, and sign before saving.');
        }
        $decision = $conn->prepare("INSERT INTO probationary_evaluation_reviews
            (evaluation_id, recommendation, recommendation_reason, immediate_head_user_id, immediate_head_signature_data, immediate_head_signed_at)
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE recommendation = VALUES(recommendation), recommendation_reason = VALUES(recommendation_reason),
                immediate_head_user_id = VALUES(immediate_head_user_id), immediate_head_signature_data = VALUES(immediate_head_signature_data),
                immediate_head_signed_at = NOW()");
        $decision->bind_param('issis', $probationary_eval_id, $recommendation, $recommendation_reason, $user_id, $head_signature);
        $decision->execute();
        $decision->close();
        logAudit($conn, $user_id, 'SIGN', 'Evaluation', $probationary_eval_id,
            'Immediate head saved a probationary recommendation and digital signature.',
            ['module' => 'Performance & Evaluation', 'target_employee_id' => $probationary_employee_id,
                'new_value' => $recommendation]);
        redirectWith($package_return_url, 'success', 'Reviewer recommendation and signature saved. You can now approve the evaluation.');
    }

    // F7 Action: Consolidator returns individual member self-rating back to employee
    if ($action === 'return_member') {
        $member_eval_id = (int) ($_POST['member_evaluation_id'] ?? 0);
        $comments = trim((string)($_POST['return_reason'] ?? $comments));
        if ($member_eval_id <= 0) {
            redirectWith($package_return_url, 'danger', 'Select a member evaluation to return.');
        }
        if ((int)$step['step_order'] !== 1) {
            redirectWith($package_return_url, 'danger', 'Only the initial consolidator can return individual member evaluations to employees.');
        }
        if (in_array($step['evaluation_type'], ['Initial', 'Final'], true) && $comments === '') {
            redirectWith($package_return_url, 'danger', 'A return reason is required for the probationary employee.');
        }

        $member_check = $conn->prepare('SELECT 1 FROM evaluation_package_members WHERE package_id = ? AND evaluation_id = ? LIMIT 1');
        $member_check->bind_param('ii', $package_id, $member_eval_id);
        $member_check->execute();
        $belongs_to_package = (bool)$member_check->get_result()->fetch_assoc();
        $member_check->close();
        if (!$belongs_to_package) {
            redirectWith($package_return_url, 'danger', 'This evaluation is not part of the current review.');
        }
        $conn->query("UPDATE evaluations SET status = 'Returned' WHERE evaluation_id = $member_eval_id");
        if (!in_array($step['evaluation_type'], ['Initial', 'Final'], true)) {
            $conn->query("DELETE FROM evaluation_package_members WHERE package_id = $package_id AND evaluation_id = $member_eval_id");
        }
        $remaining_members = (int)($conn->query("SELECT COUNT(*) AS total FROM evaluation_package_members WHERE package_id = $package_id")->fetch_assoc()['total'] ?? 0);
        if ($remaining_members > 0 && !in_array($step['evaluation_type'], ['Initial', 'Final'], true)) {
            recalculateOrganizationPackageBehaviorScore($conn, $package_id);
            $msg = 'Member evaluation returned to employee for revision. Evaluation review consolidation remains active with remaining members.';
        } else {
            $conn->query("UPDATE evaluation_packages SET status = 'Pending Self-Ratings', current_step_order = NULL, shared_behavior_score = NULL WHERE package_id = $package_id");
            $conn->query("UPDATE evaluation_package_route_steps SET action_status = 'Waiting', acted_at = NULL, comments = NULL WHERE package_id = $package_id");
            $msg = in_array($step['evaluation_type'], ['Initial', 'Final'], true)
                ? 'Probationary evaluation returned to the employee for revision. Review will resume after resubmission.'
                : 'Member evaluation returned to employee for revision. Evaluation review reset to Waiting for Team Self-Ratings.';
        }

        $audit = $conn->prepare("INSERT INTO evaluation_package_audit (package_id, user_id, action, remarks) VALUES (?, ?, 'MEMBER_RETURNED', ?)");
        $audit_remark = 'Returned member evaluation (ID: ' . $member_eval_id . ') to employee for revision.';
        $audit->bind_param('iis', $package_id, $user_id, $audit_remark);
        $audit->execute();
        $audit->close();
        logAudit($conn, $user_id, 'RETURN', 'Evaluation', $member_eval_id,
            'Immediate head returned a member evaluation for revision from evaluation review #' . $package_id . '.',
            ['module' => 'Performance & Evaluation']);

        $emp_user = $conn->query("SELECT u.user_id, e.first_name, e.last_name FROM evaluations ev JOIN employees e ON e.employee_id = ev.employee_id JOIN users u ON u.employee_id = ev.employee_id WHERE ev.evaluation_id = $member_eval_id LIMIT 1")->fetch_assoc();
        if ($emp_user) {
            createNotification($conn, (int)$emp_user['user_id'], 'Self-Rating Returned for Revision', 'Your reviewer returned your self-rating for revision. Remarks: ' . ($comments ?: 'Please review and resubmit.'), BASE_URL . '/employee/self-rating.php?edit=' . $member_eval_id);
        }
        redirectWith($package_return_url, 'success', $msg);
    }



    // Catch-Up Endorsement: Supervisor -> HR Manager, or HR Manager -> Endorsed
    if ($action === 'endorse_catchup') {
        $catchup_eval_id  = (int) ($_POST['catchup_evaluation_id'] ?? 0);
        $catchup_pkg_id   = (int) ($_POST['catchup_package_id'] ?? 0);
        $catchup_comments = trim($_POST['catchup_comments'] ?? '');
        $endorser_emp_id  = (int) ($_SESSION['employee_id'] ?? 0);

        if ($catchup_eval_id <= 0 || $catchup_pkg_id <= 0) {
            redirectWith($package_return_url, 'danger', 'Invalid catch-up endorsement request.');
        }

        // Determine endorser role
        $session_role = $_SESSION['role'] ?? '';
        if (in_array($session_role, ['HR Manager', 'HR Supervisor'], true)) {
            $endorser_role = 'hr_manager';
        } else {
            // Must be the package consolidator (supervisor)
            $is_consolidator = (bool)$conn->query("SELECT package_id FROM evaluation_packages WHERE package_id = $catchup_pkg_id AND consolidator_employee_id = $endorser_emp_id LIMIT 1")->fetch_assoc();
            if (!$is_consolidator) {
                redirectWith($package_return_url, 'danger', 'You are not authorized to endorse this catch-up member.');
            }
            $endorser_role = 'supervisor';
        }

        $result = endorseLatePackageMember($conn, $catchup_pkg_id, $catchup_eval_id, $endorser_role, $endorser_emp_id, $catchup_comments);
        $type = $result['ok'] ? 'success' : 'danger';
        redirectWith($package_return_url, $type, $result['message']);
    }

    if ($action === 'return') {

        if ($comments === '') {
            redirectWith($package_return_url, 'danger', 'A return reason is required.');
        }
        if ((int) $step['step_order'] <= 1) {
            redirectWith($package_return_url, 'danger', 'The consolidator cannot return the whole evaluation review. Use Return on an individual member row or Cancel Review.');
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
            redirectWith($package_return_url, 'danger', 'The previous reviewer could not be found in the route.');
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
        logAudit($conn, $user_id, 'RETURN', 'EvaluationPackage', $package_id,
            'Reviewer returned the evaluation review to the preceding review step.',
            ['module' => 'Performance & Evaluation']);
        notifyHrStaffOfPackageProgress($conn, $package_id, (string) $step['department_name'], (string) $previous['step_label'], true);
        if (!empty($previous['reviewer_employee_id'])) {
            notifyUsersForEmployee($conn, (int)$previous['reviewer_employee_id'], 'Team evaluation returned for revision', 'An evaluation review was returned to you by ' . $step['step_label'] . '. Reason: ' . $comments, BASE_URL . '/employee/team-evaluation-packages.php');
        } elseif (!empty($previous['reviewer_user_id'])) {
            createNotification($conn, (int) $previous['reviewer_user_id'], 'Team evaluation returned for revision', 'An evaluation review was returned to you by ' . $step['step_label'] . '. Reason: ' . $comments, BASE_URL . '/employee/team-evaluation-packages.php');
        }
        if ($is_probationary_step && $probationary_employee_id > 0) {
            notifyUsersForEmployee($conn, $probationary_employee_id,
                'Your probationary evaluation was returned for revision',
                'The ' . $step['step_label'] . ' returned your probationary evaluation to the immediate head. Reason: ' . $comments,
                BASE_URL . '/employee/evaluation-history.php');
        }

        redirectWith($package_return_url, 'success', 'Evaluation review returned to ' . $prev_name . ' (' . $previous['step_label'] . ') for revision.');
    }

    if ($step['step_type'] === 'Consolidation') {
        // Recalculate shared behavior score from whoever submitted before the consolidator acts.
        // Members who have not submitted will follow the late-joiner catch-up path automatically.
        recalculateOrganizationPackageBehaviorScore($conn, $package_id);
    }

    if ($action === 'approve' && $is_probationary_step && $step['evaluation_type'] === 'Final') {
        if ($step['evaluation_type'] === 'Final'
            && !hasApprovedInitialProbationaryEvaluation($conn, $probationary_employee_id)) {
            redirectWith($package_return_url, 'danger',
                'Approve this employee’s Initial evaluation before reviewing the Final evaluation.');
        }
        if ((int)$step['step_order'] === 1) {
            if (empty($probationary_review['recommendation'])
                || empty($probationary_review['recommendation_reason'])
                || empty($probationary_review['immediate_head_signature_data'])
                || (int)($probationary_review['immediate_head_user_id'] ?? 0) !== $user_id) {
                redirectWith($decision_review_url, 'danger', 'Record the recommendation, reason, and signature here before approving this evaluation.');
            }
        }
    }

    // 🔒 Prevention: verify next consolidator/reviewer exists and is assigned before advancing
    if ($action === 'approve' && $is_probationary_step && $step['evaluation_type'] === 'Initial'
        && in_array((int)($probationary_review['rank_category_id'] ?? 0), [1, 2, 3, 4, 5], true)) {
        $submitted_signature = trim((string)($_POST['immediate_head_signature_data'] ?? ''));
        $has_saved_signature = !empty($probationary_review['immediate_head_signature_data'])
            && (int)($probationary_review['immediate_head_user_id'] ?? 0) === $user_id;
        if (!$has_saved_signature && $submitted_signature === '') {
            redirectWith($decision_review_url, 'danger', 'Sign this probationary Initial evaluation before approving it.');
        }
        if ($submitted_signature !== '' && (!preg_match('/^data:image\/png;base64,/', $submitted_signature)
            || strlen($submitted_signature) > 100000)) {
            redirectWith($decision_review_url, 'danger', 'The Immediate head signature is invalid. Sign again before approving.');
        }
    }

    $next_check = checkNextPackageStepIsAssigned($conn, $package_id, (int)$step['step_order']);
    if (!$next_check['ok']) {
        redirectWith($package_return_url, 'danger', $next_check['message']);
    }

    if ($action === 'approve' && !empty($_POST['review_form_submission'])
        && $is_probationary_step && $step['evaluation_type'] === 'Initial'
        && (int)$step['step_order'] === 1 && $probationary_eval_id > 0) {
        $ratings = $_POST['rating'] ?? [];
        $plans = $_POST['plans'] ?? [];
        if (!is_array($ratings) || !is_array($plans)) {
            redirectWith($decision_review_url, 'danger', 'Invalid review details. Please reload and try again.');
        }
        $score_stmt = $conn->prepare('SELECT es.score_id FROM evaluation_scores es WHERE es.evaluation_id = ?');
        $score_stmt->bind_param('i', $probationary_eval_id);
        $score_stmt->execute();
        $score_rows = $score_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $score_stmt->close();
        foreach ($score_rows as $score_row) {
            $score_id = (int)$score_row['score_id'];
            if (!isset($ratings[$score_id]) || $ratings[$score_id] === '') continue;
            if (!is_numeric($ratings[$score_id]) || (float)$ratings[$score_id] < 1 || (float)$ratings[$score_id] > 4) {
                redirectWith($decision_review_url, 'danger', 'Every adjusted rating must be between 1.00 and 4.00.');
            }
        }
        foreach ($score_rows as $score_row) {
            $score_id = (int)$score_row['score_id'];
            if (!isset($ratings[$score_id]) || $ratings[$score_id] === '') continue;
            $rating = (float)$ratings[$score_id];
            $update_score = $conn->prepare('UPDATE evaluation_scores SET supervisor_override_score = ?, supervisor_override_by = ?, supervisor_override_at = NOW() WHERE score_id = ?');
            $update_score->bind_param('dii', $rating, $user_id, $score_id);
            $update_score->execute();
            $update_score->close();
        }
        $delete_plans = $conn->prepare('DELETE FROM evaluation_dev_plans WHERE evaluation_id = ?');
        $delete_plans->bind_param('i', $probationary_eval_id);
        $delete_plans->execute();
        $delete_plans->close();
        $insert_plan = $conn->prepare('INSERT INTO evaluation_dev_plans (evaluation_id, improvement_area, support_needed, time_frame, sort_order) VALUES (?, ?, ?, ?, ?)');
        $sort_idx = 0;
        foreach ($plans as $plan) {
            if (!is_array($plan)) continue;
            $area = trim((string)($plan['improvement_area'] ?? ''));
            $support = trim((string)($plan['support_needed'] ?? ''));
            $time_frame = trim((string)($plan['time_frame'] ?? ''));
            if ($area === '' && $support === '' && $time_frame === '') continue;
            $insert_plan->bind_param('isssi', $probationary_eval_id, $area, $support, $time_frame, $sort_idx);
            $insert_plan->execute();
            $sort_idx++;
        }
        $insert_plan->close();
        recalculateEvaluationScores($conn, $probationary_eval_id);
        recalculateOrganizationPackageBehaviorScore($conn, $package_id);
    }

    if ($action === 'approve' && $is_probationary_step && $step['evaluation_type'] === 'Initial'
        && in_array((int)($probationary_review['rank_category_id'] ?? 0), [1, 2, 3, 4, 5], true)
        && $submitted_signature !== '') {
        ensureProbationaryEvaluationSchema($conn);
        $signature_stmt = $conn->prepare("INSERT INTO probationary_evaluation_reviews
            (evaluation_id, immediate_head_user_id, immediate_head_signature_data, immediate_head_signed_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE immediate_head_user_id = VALUES(immediate_head_user_id),
                immediate_head_signature_data = VALUES(immediate_head_signature_data), immediate_head_signed_at = NOW()");
        $signature_stmt->bind_param('iis', $probationary_eval_id, $user_id, $submitted_signature);
        $signature_stmt->execute();
        $signature_stmt->close();
    }

    $approve = $conn->prepare("UPDATE evaluation_package_route_steps SET action_status = 'Approved', acted_at = NOW(), comments = ? WHERE package_route_step_id = ?");
    $approve->bind_param('si', $comments, $step['package_route_step_id']);
    $approve->execute();
    $approve->close();

    $next_order = (int) $step['step_order'] + 1;
    $next_stmt = $conn->prepare('SELECT package_route_step_id, reviewer_user_id, reviewer_employee_id, step_label, step_type, eligible_role, eligible_rank_category_id, claimed_at FROM evaluation_package_route_steps WHERE package_id = ? AND step_order = ? LIMIT 1');
    $next_stmt->bind_param('ii', $package_id, $next_order);
    $next_stmt->execute();
    $next = $next_stmt->get_result()->fetch_assoc();
    $next_stmt->close();

    $is_probationary = in_array($step['evaluation_type'], ['Initial', 'Final'], true);
    $is_final_board = ($step['step_type'] === 'Governance') && (stripos($step['step_label'], 'Board') !== false);
    $is_final_probationary = $is_probationary && !$next;
    $is_final_hr = $is_final_probationary && isHumanResourcesPackageDepartment($conn, (int)$step['department_id']);

    if ($next && !$is_final_board) {
        $next_update = $conn->prepare("UPDATE evaluation_package_route_steps SET action_status = 'Pending' WHERE package_route_step_id = ? AND action_status IN ('Waiting', 'Returned')");
        $next_update->bind_param('i', $next['package_route_step_id']);
        $next_update->execute();
        $next_update->close();

        $status = getOrganizationPackageStatusForStep($next);
        $package_update = $conn->prepare('UPDATE evaluation_packages SET current_step_order = ?, status = ? WHERE package_id = ?');
        $package_update->bind_param('isi', $next_order, $status, $package_id);
        $package_update->execute();
        $package_update->close();

        // ── Late-member merge check ─────────────────────────────────────────────
        // Join a same-cycle sibling only at the exact same pending review stage.
        $merged_into = tryMergeLateMemberPackageIntoSibling($conn, $package_id, (int)$step['step_order']);
        $was_merged  = ($merged_into !== $package_id);

        // Keep the approval attached to the package whose reviewer acted.
        $audit_pkg_id = $package_id;
        $next_name = ((!empty($next['eligible_role']) || !empty($next['eligible_rank_category_id'])) && empty($next['claimed_at']))
            ? (!empty($next['eligible_role']) ? $next['eligible_role'] : ((int)$next['eligible_rank_category_id'] === 4 ? 'Department Supervisor' : 'Department Manager'))
            : getOrganizationPackageReviewerDisplayName($conn, (int)($next['reviewer_user_id'] ?? 0), (int)($next['reviewer_employee_id'] ?? 0));
        if (!$was_merged) {
            $staff_regularization_request = $is_probationary && $step['evaluation_type'] === 'Final'
                && (int)$step['step_order'] === 1 && (int)($probationary_review['rank_category_id'] ?? 0) === 5
                && ($probationary_review['recommendation'] ?? '') === 'Recommend regularization';
            notifyOrganizationPackageStepAssignees($conn, $package_id, $next_order,
                $staff_regularization_request ? 'Probationary regularization decision requested' : 'Team evaluation review awaiting your approval',
                $staff_regularization_request
                    ? 'The Department Supervisor recommends regularization for this probationary staff employee after the Final evaluation. Review the recommendation and approve or decline it.'
                    : 'The ' . $step['department_name'] . ' evaluation review was approved by ' . $step['step_label'] . ' and forwarded to you: ' . $next['step_label'] . '.');
        }

        if (!$was_merged && $is_probationary && (int)$step['step_order'] === 1 && $probationary_employee_id > 0) {
            $employee_users_stmt = $conn->prepare("SELECT user_id FROM users WHERE employee_id = ? AND role = 'Employee' AND is_active = 1");
            $employee_users_stmt->bind_param('i', $probationary_employee_id);
            $employee_users_stmt->execute();
            $employee_users = $employee_users_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $employee_users_stmt->close();
            foreach ($employee_users as $employee_user) {
                createNotification($conn, (int)$employee_user['user_id'], 'Immediate head reviewed your probationary evaluation',
                    'Your immediate head reviewed your ' . $step['evaluation_type'] . ' evaluation and forwarded it to ' . $next['step_label'] . '. Your original self-rating and signature remain on file; no additional signature is needed.',
                    BASE_URL . '/employee/evaluation-history-view.php?evaluation_id=' . $probationary_eval_id);
            }
        }

        $audit = $conn->prepare("INSERT INTO evaluation_package_audit (package_id, user_id, action, remarks) VALUES (?, ?, 'APPROVED', ?)");
        $audit->bind_param('iis', $audit_pkg_id, $user_id, $comments);
        $audit->execute();
        $audit->close();
        logAudit($conn, $user_id, 'APPROVE', 'EvaluationPackage', $package_id,
            'Reviewer approved and forwarded evaluation review at ' . $step['step_label'] . '.',
            ['module' => 'Performance & Evaluation']);

        if ($was_merged) {
            redirectWith($package_return_url, 'success', 'Evaluation approved. This member\'s evaluation has been merged into the main ' . $step['department_name'] . ' team evaluation review (Review #' . $merged_into . ') at the same approval stage.');
        }
        notifyHrStaffOfPackageProgress($conn, $package_id, (string) $step['department_name'], (string) $next['step_label']);
        redirectWith($package_return_url, 'success', 'Evaluation review successfully adjusted and forwarded to ' . $next_name . ' (' . $next['step_label'] . ').');
    } else {
        // Final governance step (Board of Directors) locks and applies.
        if (!$is_final_board && !$is_final_probationary) {
            redirectWith($package_return_url, 'danger', 'Final application requires Board of Directors approval. Please contact an administrator.');
        }

        $conn->begin_transaction();
        $applied = applyOrganizationPackageResults($conn, $package_id);
        if ($applied && $is_final_probationary && $probationary_eval_id > 0) {
            $applied = applyProbationaryEvaluationOutcome($conn, $probationary_eval_id);
        }
        if ($applied && $is_final_probationary && $probationary_eval_id > 0
            && (int)($probationary_review['rank_category_id'] ?? 0) === 5
            && ($probationary_review['recommendation'] ?? '') === 'Recommend regularization'
            && !isHumanResourcesPackageDepartment($conn, (int)$step['department_id'])) {
            $request_update = $conn->prepare("UPDATE regularization_requests
                SET status = 'Approved', decided_by = ?, decided_at = NOW(), effective_date = CURRENT_DATE(), decision_reason = 'Approved through department probationary evaluation workflow.'
                WHERE evaluation_id = ? AND status = 'Pending'");
            $request_update->bind_param('ii', $user_id, $probationary_eval_id);
            $request_update->execute();
            $request_update->close();
        }
        if ($applied) {
            $package_update = $conn->prepare("UPDATE evaluation_packages SET status = 'Approved and Applied', current_step_order = NULL WHERE package_id = ?");
            $package_update->bind_param('i', $package_id);
            $package_update->execute();
            $package_update->close();

            $lock_audit = $conn->prepare("INSERT INTO evaluation_package_audit (package_id, user_id, action, remarks) VALUES (?, ?, 'LOCKED_AND_APPLIED', ?)");
            $lock_remark = $is_final_probationary
                ? ($is_final_hr ? $step['step_label'] . ' approved and applied the HR probationary evaluation.' : 'Department Manager approved and applied the non-HR probationary evaluation; HR was notified for recordkeeping.')
                : 'Board approval locked and applied final evaluation review results to all team members.';
            $lock_audit->bind_param('iis', $package_id, $user_id, $lock_remark);
            $lock_audit->execute();
            $lock_audit->close();
            $conn->commit();
            logAudit($conn, $user_id, 'FINALIZE', 'EvaluationPackage', $package_id,
                'Final approval locked and applied the evaluation review.',
                ['module' => 'Performance & Evaluation', 'target_employee_id' => $is_final_probationary ? $probationary_employee_id : null]);

            // 1. Notify all package team members
            $members_stmt = $conn->prepare('SELECT DISTINCT u.user_id FROM evaluation_package_members pm JOIN evaluations e ON e.evaluation_id = pm.evaluation_id JOIN users u ON u.employee_id = e.employee_id AND u.is_active = 1 WHERE pm.package_id = ?');
            $members_stmt->bind_param('i', $package_id);
            $members_stmt->execute();
            $member_users = $members_stmt->get_result();
            while ($member_user = $member_users->fetch_assoc()) {
                $member_message = $is_final_probationary
                    ? 'Your probationary evaluation has completed final approval. Final score: ' . number_format((float)($probationary_review['total_score'] ?? 0), 2) . '.'
                    : 'Your team evaluation review (' . $step['department_name'] . ') has completed final Board approval and all scores are locked and applied.';
                createNotification($conn, (int) $member_user['user_id'], 'Evaluation Approved & Finalized', $member_message, BASE_URL . '/employee/evaluation-history.php');
            }
            $members_stmt->close();

            // 2. Notify all route reviewers who took part in evaluating or consolidating
            $reviewers_stmt = $conn->prepare('SELECT DISTINCT reviewer_user_id, reviewer_employee_id FROM evaluation_package_route_steps WHERE package_id = ?');
            $reviewers_stmt->bind_param('i', $package_id);
            $reviewers_stmt->execute();
            $route_revs = $reviewers_stmt->get_result();
            while ($r_row = $route_revs->fetch_assoc()) {
                $rev_uid = (int)($r_row['reviewer_user_id'] ?? 0);
                $rev_emp_id = (int)($r_row['reviewer_employee_id'] ?? 0);
                if ($rev_uid > 0) {
                    createNotification($conn, $rev_uid, 'Evaluation Approved & Finalized', $is_final_probationary ? 'The probationary evaluation received final approval from ' . $step['step_label'] . '.' : 'The ' . $step['department_name'] . ' evaluation review has received Board approval and all scores are locked and applied.', BASE_URL . '/employee/team-evaluation-history.php');
                } elseif ($rev_emp_id > 0) {
                    notifyUsersForEmployee($conn, $rev_emp_id, 'Evaluation Approved & Finalized', $is_final_probationary ? 'The probationary evaluation received final approval from ' . $step['step_label'] . '.' : 'The ' . $step['department_name'] . ' evaluation review has received Board approval and all scores are locked and applied.', BASE_URL . '/employee/team-evaluation-history.php');
                }
            }
            $reviewers_stmt->close();

            // Outside-department probationary results reach HR only after final approval.
            if ($is_final_probationary && $step['evaluation_type'] === 'Final' && $is_final_hr && $probationary_eval_id > 0) {
                notifyHrManagersOfStaffProbationaryApproval($conn, $probationary_eval_id);
            } elseif ($is_final_probationary && !$is_final_hr && $probationary_eval_id > 0) {
                notifyHRPersonnelOfCompletedProbationaryEvaluation($conn, $probationary_eval_id);
                if ($step['evaluation_type'] === 'Final') notifyDepartmentManagersOfProbationaryOutcome($conn, $probationary_eval_id);
            } elseif (!$is_probationary) {
                notifyHRPersonnelPackageFinalized($conn, $package_id, $step['department_name'], $step['template_name'] ?? 'Evaluation');
            }

            if ($is_final_probationary && $probationary_eval_id > 0) {
                $outcome_stmt = $conn->prepare("SELECT e.employment_status, e.probationary_eligible_evaluation_id,
                        e.first_name, e.last_name, e.job_title, e.rank_category_id, d.department_name, u.user_id
                    FROM evaluations ev
                    JOIN employees e ON e.employee_id = ev.employee_id
                    LEFT JOIN departments d ON d.department_id = e.department_id
                    LEFT JOIN users u ON u.employee_id = e.employee_id AND u.role = 'Employee' AND u.is_active = 1
                    WHERE ev.evaluation_id = ? LIMIT 1");
                $outcome_stmt->bind_param('i', $probationary_eval_id);
                $outcome_stmt->execute();
                $regularized_users = $outcome_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $outcome_stmt->close();
                foreach ($regularized_users as $regularized_user) {
                    if (($regularized_user['employment_status'] ?? '') !== 'Regular'
                        || (int)($regularized_user['probationary_eligible_evaluation_id'] ?? 0) !== $probationary_eval_id
                        || (int)($regularized_user['user_id'] ?? 0) <= 0) continue;
                    $is_hr_leader = strcasecmp((string)($regularized_user['department_name'] ?? ''), 'Human Resources') === 0
                        && in_array((int)($regularized_user['rank_category_id'] ?? 0), [3, 4], true);
                    $is_hr_manager = (int)($regularized_user['rank_category_id'] ?? 0) === 3;
                    $notification_title = $is_hr_leader
                        ? 'Regularization confirmed - HRIS access request process'
                        : 'Congratulations on your regularization!';
                    $notification_message = $is_hr_leader
                        ? 'Your probationary ' . ($is_hr_manager ? 'HR Manager' : 'HR Supervisor') . ' position has been regularized. HRIS portal access is separate and is not created automatically when you are regularized. To request access, contact ' . ($is_hr_manager ? 'another active HR Manager or an active HR Supervisor' : 'the HR Manager or an active HR Supervisor') . ' for guidance; they can coordinate with the administrator to create and activate your HRIS account. Continue using your Employee Portal account until HRIS access is confirmed. Do not share your login credentials.'
                        : 'Your final probationary evaluation was approved. Your employment status is now Regular, and regular employee evaluation features are available in the portal.';
                    createNotification($conn, (int)$regularized_user['user_id'], $notification_title,
                        $notification_message, BASE_URL . '/employee/my-employment.php');
                }
            }

            redirectWith($package_return_url, 'success', $is_final_probationary ? 'Probationary evaluation approved and finalized.' : 'Evaluation review approved, locked, and applied to all team members. HR personnel and team members have been notified.');
        } else {
            $conn->rollback();
            $restore = $conn->prepare("UPDATE evaluation_package_route_steps SET action_status = 'Pending', acted_at = NULL WHERE package_route_step_id = ? AND action_status = 'Approved'");
            $restore->bind_param('i', $step['package_route_step_id']);
            $restore->execute();
            $restore->close();
            redirectWith($package_return_url, 'danger', $is_probationary ? 'The individual probationary evaluation could not be applied.' : 'The evaluation review could not be applied because its shared behavior score is incomplete.');
        }
    }
}

require_once '../includes/header.php';

// Pending packages awaiting this reviewer's action right now (must have at least 1 submitted member)
$packages_stmt = $conn->prepare("SELECT ep.*, d.department_name, et.template_name, et.kra_weight, et.behavior_weight,
        rs.package_route_step_id, rs.step_label, rs.step_type, rs.action_status, rs.eligible_role,
        rs.eligible_rank_category_id, rs.claimed_at
    FROM evaluation_packages ep
    JOIN evaluation_package_route_steps rs ON rs.package_id = ep.package_id
    JOIN departments d ON d.department_id = ep.department_id
    JOIN evaluation_templates et ON et.template_id = ep.template_id
    WHERE ($reviewer_match OR (rs.eligible_role = ? AND rs.claimed_at IS NULL)
        OR (rs.eligible_rank_category_id = ? AND ep.department_id = ? AND rs.claimed_at IS NULL))
    AND rs.action_status = 'Pending' AND ep.current_step_order = rs.step_order
    AND ep.status NOT IN ('Cancelled', 'Approved and Applied')
    AND (SELECT COUNT(*) FROM evaluation_package_members pm WHERE pm.package_id = ep.package_id) > 0
    ORDER BY ep.updated_at DESC");
$packages_stmt->bind_param('iisii', $user_id, $user_id, $package_claim_role, $package_claim_rank_category, $package_claim_department_id);
$packages_stmt->execute();
$packages = $packages_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$packages_stmt->close();

// F2/F8: Waiting packages (strictly step 1 consolidators whose teams are still submitting self-ratings or where members were returned)
$waiting_stmt = $conn->prepare("SELECT ep.*, d.department_name, et.template_name, et.evaluation_type, et.kra_weight, et.behavior_weight, rs.step_label
    FROM evaluation_packages ep
    JOIN evaluation_package_route_steps rs ON rs.package_id = ep.package_id AND rs.step_order = 1
    JOIN departments d ON d.department_id = ep.department_id
    JOIN evaluation_templates et ON et.template_id = ep.template_id
    WHERE ($reviewer_match
        OR (rs.eligible_role = ? AND rs.claimed_at IS NULL)
        OR (rs.eligible_rank_category_id = ? AND ep.department_id = ? AND rs.claimed_at IS NULL))
    AND rs.action_status = 'Waiting'
    AND EXISTS (SELECT 1 FROM evaluation_package_members pm WHERE pm.package_id = ep.package_id)
    AND ep.status NOT IN ('Cancelled', 'Approved and Applied')
    ORDER BY ep.updated_at DESC");
$waiting_stmt->bind_param('iisii', $user_id, $user_id, $package_claim_role, $package_claim_rank_category, $package_claim_department_id);
$waiting_stmt->execute();
$waiting_packages = $waiting_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$waiting_stmt->close();

// Catch-Up Members: load based on role & employee assignments
$current_employee_id = (int) ($_SESSION['employee_id'] ?? 0);
$session_role = $_SESSION['role'] ?? '';
$catchup_supervisor_members = [];
$catchup_hr_members = [];
$catchup_reviewer_members = []; // Pending HR Catchup visible to the current active package reviewer

if ($current_employee_id > 0) {
    // If the employee is the department consolidator/supervisor for any package with late members
    $catchup_supervisor_members = getLatePackageMembersForSupervisor($conn, $current_employee_id);
    // Also show Pending HR Catchup for packages where this employee is the active reviewer
    $catchup_reviewer_members = getLatePackageMembersForCurrentReviewer($conn, $current_employee_id);
}

$employee_package_access = $session_role === 'Employee'
    && (in_array((int)($claim_profile['rank_category_id'] ?? 0), [1, 3, 4], true) || countPendingOrganizationPackagesForUser($conn, $user_id) > 0)
    && strcasecmp((string)($claim_profile['department_name'] ?? ''), 'Human Resources') !== 0;
if (in_array($session_role, ['HR Manager', 'HR Supervisor', 'Admin'], true) || $employee_package_access) {
    // HR managers/supervisors/admins can also endorse any package at the HR level
    $catchup_hr_members = getLatePackageMembersForHRManager($conn);
}
$probationary_package_count = count(array_filter($packages, static fn($package) => in_array($package['evaluation_type'] ?? '', ['Initial', 'Final'], true)))
    + count(array_filter($waiting_packages, static fn($package) => in_array($package['evaluation_type'] ?? '', ['Initial', 'Final'], true)));
$active_package_tab = 'probationary';
$open_package_id = max(0, (int)($_GET['open_package'] ?? 0));
?>
<div class="evaluation-packages">
    <?php $shared_performance_heading=true; require __DIR__ . '/../includes/performance-evaluation-heading.php'; ?>

    <?php if ($packages || $waiting_packages || in_array($_SESSION['role'] ?? '', ['HR Manager','HR Supervisor'],true)): ?>
        <style>
            .package-category-tabs{display:flex;gap:.75rem;align-items:stretch;margin:1rem 0 1.25rem;border-bottom:1px solid #dce5dc}
            .package-category-tab{display:flex;align-items:center;gap:.75rem;min-width:240px;padding:.8rem 1rem;background:#f3f4f6;border:0;border-bottom:3px solid transparent;border-radius:.5rem .5rem 0 0;color:#374151;text-align:left;transition:background .15s,color .15s,border-color .15s}
            .package-category-tab:hover:not(:disabled){background:#e9f2e9}
            .package-category-tab.active{background:#eef7ed;border-bottom-color:#d4a900;color:#082e06}
            .package-category-tab:disabled{opacity:.48;cursor:not-allowed}
            .package-category-tab__icon{display:grid;place-items:center;width:38px;height:38px;border-radius:50%;background:rgba(8,46,6,.08);font-size:1.1rem}
            .package-category-tab__copy{display:flex;flex:1;flex-direction:column;gap:.15rem}
            .package-category-tab__copy strong{font-size:.9rem}
            .package-category-tab__copy small{color:#6b7280;font-size:.72rem}
            .package-category-tab__count{display:grid;place-items:center;min-width:26px;height:26px;padding:0 .4rem;border-radius:999px;background:#fff;color:#374151;font-size:.76rem;font-weight:700}
            .package-category-tab.active .package-category-tab__count{background:#082e06;color:#fff}
            .package-card.package-card--probationary:not(.package-card--probationary-summary){border-color:#3730a3!important}
            .package-card.package-card--probationary:not(.package-card--probationary-summary) .package-card__header{border-left:4px solid #3730a3}
            @media(max-width:575.98px){.package-category-tabs{gap:.4rem}.package-category-tab{flex:1;min-width:0;padding:.65rem .5rem;gap:.45rem}.package-category-tab__icon{width:30px;height:30px}.package-category-tab__copy strong{font-size:.76rem}.package-category-tab__copy small{font-size:.65rem}}

@media(max-width:650px) {
 .evaluation-packages table.probationary-mobile-roster { display:block!important; min-width:0!important; width:100%!important; table-layout:auto; }
 .evaluation-packages table.probationary-mobile-roster thead { display:none; }
 .evaluation-packages table.probationary-mobile-roster tbody { display:block; width:100%; }
 .evaluation-packages table.probationary-mobile-roster tbody tr { display:block; width:100%; margin-bottom:10px; border:1px solid #dce5d7; border-radius:9px; overflow:hidden; }
 .evaluation-packages table.probationary-mobile-roster tbody td { display:flex!important; justify-content:space-between; align-items:center; gap:10px; width:100%!important; min-width:0!important; max-width:none; padding:9px 10px!important; font-size:12px!important; line-height:1.4; white-space:normal!important; word-break:normal; text-align:right; border:0; }
 .evaluation-packages table.probationary-mobile-roster tbody td::before { flex:1; text-align:left; font-weight:600; color:#536153; font-size:11px; }
 .evaluation-packages table.probationary-mobile-roster td:nth-child(1)::before { content:'Employee'; }
 .evaluation-packages table.probationary-mobile-roster td:nth-child(2)::before { content:'Position'; }
 .evaluation-packages table.probationary-mobile-roster td:nth-child(3)::before { content:'KRA score'; }
 .evaluation-packages table.probationary-mobile-roster td:nth-child(4)::before { content:'Core Values score'; }
 .evaluation-packages table.probationary-mobile-roster td:nth-child(5)::before { content:'Weighted score'; }
 .evaluation-packages table.probationary-mobile-roster td:nth-child(6)::before { content:'Final score'; }
 .evaluation-packages table.probationary-mobile-roster td:nth-child(7)::before { content:'Status'; }
 .evaluation-packages table.probationary-mobile-roster td:nth-child(8)::before { content:'Action'; }
 .evaluation-packages table.probationary-mobile-roster td:first-child { background:#edf4e8; }
 .evaluation-packages table.probationary-mobile-roster td.col-action { flex-wrap:wrap; }
 .evaluation-packages table.probationary-mobile-roster .btn { max-width:100%; min-height:40px; white-space:normal!important; font-size:12px; }
}
        </style>
        <?php if (in_array($_SESSION['role'] ?? '', ['HR Manager', 'HR Supervisor'], true)): ?>
        <nav class="d-flex flex-wrap gap-2 mb-3" aria-label="Probationary evaluation history">
            <a class="btn btn-success" style="background:#294306;border-color:#294306;color:#fff" href="<?php echo BASE_URL; ?>/employee/probationary-evaluation-history.php"><i class="fas fa-history me-2" aria-hidden="true"></i>Probationary Evaluation History</a>
        </nav>
        <?php endif; ?>
        <?php if ($packages || $waiting_packages): ?>
        <div id="packageFilterEmpty" class="package-empty d-none" role="status">
            <i class="fas fa-filter fa-2x text-muted mb-2" style="opacity:0.4;"></i>
            <h2 class="h6 fw-bold mb-1">No evaluation reviews match these filters</h2>
            <p class="mb-0 text-muted small">Choose another evaluation tab.</p>
        </div>
        <?php endif; ?>
    <?php endif; ?>


    <?php if (!$packages && !$waiting_packages): ?>
        <section class="package-empty">
            <i class="fas fa-layer-group fa-3x text-muted mb-3" style="opacity:0.4;"></i>
            <h2 class="h5 fw-bold">No probationary evaluation is currently waiting for your review</h2>
            <p class="mb-0 text-muted">
                Eligible supervisors and managers can view department evaluation reviews here. For an unclaimed stage, the first eligible reviewer to claim it becomes responsible for that stage.
            </p>
        </section>
    <?php endif; ?>

    <!-- Waiting for Submissions (Step 1 Consolidators) -->
    <?php foreach ($waiting_packages as $package): ?>
        <?php
        $summary = getOrganizationPackageSubmissionSummary($conn, $package);
        $is_probationary_waiting = in_array($package['evaluation_type'] ?? '', ['Initial', 'Final'], true);
        $waiting_category = $is_probationary_waiting ? 'probationary' : 'regular';
        ?>
        <article class="package-card <?php echo $waiting_category === 'probationary' ? 'package-card--probationary ' : ''; ?><?php echo $waiting_category === $active_package_tab ? '' : 'd-none'; ?>" data-package-category="<?php echo $waiting_category; ?>" data-package-department="<?php echo (int)$package['department_id']; ?>" role="region" aria-label="<?php echo e($package['department_name']); ?> Pending Submissions">
            <header class="package-card__header">
                <div>
                    <h2 class="h5 mb-1 fw-bold"><?php echo e($package['department_name']); ?> &mdash; <?php echo e($package['template_name']); ?></h2>
                    <p class="mb-0 text-muted small"><i class="fas fa-calendar-alt me-1"></i>Cycle: <?php echo e($package['period_start']); ?> to <?php echo e($package['period_end']); ?></p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="pipeline-badge pipeline-badge--waiting">
                        <i class="fas fa-user-clock me-1"></i>Waiting for Team Self-Ratings (<?php echo $summary['submitted']; ?>/<?php echo $summary['required']; ?>)
                    </span>
                    <button class="btn btn-sm btn-package-collapse collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#pkgBody-wait-<?php echo (int)$package['package_id']; ?>" aria-expanded="false" aria-controls="pkgBody-wait-<?php echo (int)$package['package_id']; ?>" title="Expand Review">
                        <i class="fas fa-chevron-up collapse-icon"></i> <span class="d-none d-sm-inline">Expand</span>
                    </button>
                </div>
            </header>
            <div class="collapse" id="pkgBody-wait-<?php echo (int)$package['package_id']; ?>">
                <div class="package-card__body">
                    <div class="alert alert-warning py-2 px-3 small mb-3">
                        <i class="fas fa-exclamation-triangle me-1"></i><strong>Consolidation Pending:</strong> All team members must submit their self-ratings before you can consolidate and forward this evaluation review.
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
            </div>
        </article>
    <?php endforeach; ?>

    <!-- Active Packages Awaiting Action -->
    <?php foreach ($packages as $package): ?>
        <?php
        $is_unclaimed_group_step = (!empty($package['eligible_role']) || !empty($package['eligible_rank_category_id'])) && empty($package['claimed_at']);
        $group_reviewer_label = !empty($package['eligible_role'])
            ? $package['eligible_role']
            : ((int)($package['eligible_rank_category_id'] ?? 0) === 4 ? 'Department Supervisor' : 'Department Manager');
        $is_probationary_package = in_array($package['evaluation_type'], ['Initial', 'Final'], true);
        $can_adjust = !$is_unclaimed_group_step && !isOrganizationPackageLocked($conn, (int)$package['package_id'])
            && (!$is_probationary_package || (int)$package['current_step_order'] === 1);
        $is_board_step = ($package['step_type'] === 'Governance') && (stripos($package['step_label'], 'Board') !== false);
        $route_count_stmt = $is_probationary_package ? $conn->prepare('SELECT COUNT(*) AS total FROM evaluation_package_route_steps WHERE package_id = ?') : null;
        $route_count = 0;
        if ($route_count_stmt) {
            $route_count_stmt->bind_param('i', $package['package_id']);
            $route_count_stmt->execute();
            $route_count = (int)($route_count_stmt->get_result()->fetch_assoc()['total'] ?? 0);
            $route_count_stmt->close();
        }
        $is_final_probationary_step = $is_probationary_package && (int)$package['current_step_order'] >= $route_count;
        $is_final_hr_step = $is_final_probationary_step && isHumanResourcesPackageDepartment($conn, (int)$package['department_id']);
        $members_stmt = $conn->prepare("SELECT e.evaluation_id, emp.first_name, emp.last_name, emp.job_title, emp.rank_category_id, emp.profile_picture,
                b.branch_name,
                e.kra_subtotal, e.behavior_average, e.total_score, e.status, pm.member_status,
                (SELECT AVG(COALESCE(es.manager_override_score, es.supervisor_override_score,
                                     es.dept_manager_override_score, es.score_value))
                 FROM evaluation_scores es JOIN evaluation_criteria ec ON ec.criterion_id = es.criterion_id
                 WHERE es.evaluation_id = e.evaluation_id AND ec.section = 'Behavior') AS individual_behavior,
                EXISTS(SELECT 1 FROM evaluation_scores es WHERE es.evaluation_id = e.evaluation_id AND (es.supervisor_override_score IS NOT NULL OR es.dept_manager_override_score IS NOT NULL OR es.manager_override_score IS NOT NULL)) AS has_adjustments,
                per.recommendation, per.recommendation_reason, per.immediate_head_user_id,
                per.immediate_head_signature_data, per.immediate_head_signed_at
            FROM evaluation_package_members pm
            JOIN evaluations e ON e.evaluation_id = pm.evaluation_id
            JOIN employees emp ON emp.employee_id = e.employee_id
            LEFT JOIN branches b ON b.branch_id = emp.branch_id
            LEFT JOIN probationary_evaluation_reviews per ON per.evaluation_id = e.evaluation_id
            WHERE pm.package_id = ? AND e.status != 'Approved'
            ORDER BY emp.last_name, emp.first_name");
        $members_stmt->bind_param('i', $package['package_id']);
        $members_stmt->execute();
        $members = $members_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $members_stmt->close();

        $requires_probationary_decision_signature = $is_probationary_package
            && (int)$package['current_step_order'] === 1
            && (($package['evaluation_type'] ?? '') === 'Final'
                || (($package['evaluation_type'] ?? '') === 'Initial'
                    && in_array((int)($members[0]['rank_category_id'] ?? 0), [1, 2, 3, 4, 5], true)));
        $has_current_reviewer_signature = $requires_probationary_decision_signature
            && !empty($members[0]['immediate_head_signature_data'])
            && (int)($members[0]['immediate_head_user_id'] ?? 0) === $user_id;
        $has_saved_final_recommendation = ($package['evaluation_type'] ?? '') !== 'Final'
            || (!empty($members[0]['recommendation']) && !empty($members[0]['recommendation_reason']));
        $signature_gate_locked = $requires_probationary_decision_signature
            && (!$has_current_reviewer_signature || !$has_saved_final_recommendation);

        // Load any late-rejoined members for informational alert in the member tab
        $late_rejoined_stmt = $conn->prepare("SELECT emp.first_name, emp.last_name, emp.job_title
            FROM evaluation_package_members pm
            JOIN evaluations e ON e.evaluation_id = pm.evaluation_id
            JOIN employees emp ON emp.employee_id = e.employee_id
            WHERE pm.package_id = ? AND pm.member_status = 'Late Rejoined'
            ORDER BY emp.last_name, emp.first_name");
        $late_rejoined_stmt->bind_param('i', $package['package_id']);
        $late_rejoined_stmt->execute();
        $late_rejoined_members = $late_rejoined_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $late_rejoined_stmt->close();

        // Next reviewer for modal summary
        $next_step_order = (int)$package['current_step_order'] + 1;
        $next_rev_stmt = $conn->prepare('SELECT step_label, reviewer_user_id, reviewer_employee_id, eligible_role, eligible_rank_category_id, claimed_at FROM evaluation_package_route_steps WHERE package_id = ? AND step_order = ? LIMIT 1');
        $next_rev_stmt->bind_param('ii', $package['package_id'], $next_step_order);
        $next_rev_stmt->execute();
        $next_rev_info = $next_rev_stmt->get_result()->fetch_assoc();
        $next_rev_stmt->close();
        $next_reviewer_name = $next_rev_info
            ? ((!empty($next_rev_info['eligible_role']) || !empty($next_rev_info['eligible_rank_category_id'])) && empty($next_rev_info['claimed_at'])
                ? (!empty($next_rev_info['eligible_role']) ? $next_rev_info['eligible_role'] : ((int)$next_rev_info['eligible_rank_category_id'] === 4 ? 'Department Supervisor' : 'Department Manager'))
                : getOrganizationPackageReviewerDisplayName($conn, (int)($next_rev_info['reviewer_user_id'] ?? 0), (int)($next_rev_info['reviewer_employee_id'] ?? 0)))
            : ($is_probationary_package ? 'Final approval' : 'Board of Directors');
        $package_next_check = checkNextPackageStepIsAssigned($conn, (int)$package['package_id'], (int)$package['current_step_order']);
        $team_size_stmt = $conn->prepare("SELECT COUNT(*) AS team_size FROM employees
            WHERE department_id = ? AND is_active = 1 AND deleted_at IS NULL");
        $team_size_stmt->bind_param('i', $package['department_id']);
        $team_size_stmt->execute();
        $department_team_size = (int)($team_size_stmt->get_result()->fetch_assoc()['team_size'] ?? 0);
        $team_size_stmt->close();
        $package_is_open = (int)$package['package_id'] === $open_package_id;
        ?>
        <article id="package-<?php echo (int)$package['package_id']; ?>" class="package-card <?php echo $is_probationary_package ? 'package-card--probationary package-card--probationary-summary ' : ''; ?><?php echo $is_probationary_package === ($active_package_tab === 'probationary') ? '' : 'd-none'; ?>" data-package-category="<?php echo $is_probationary_package ? 'probationary' : 'regular'; ?>" data-package-department="<?php echo (int)$package['department_id']; ?>" role="region" aria-label="<?php echo e($package['department_name']); ?> Evaluation Review">
            <header class="package-card__header">
                <div>
                    <h2 class="h5 mb-1 fw-bold"><?php echo e($package['department_name']); ?> &mdash; <?php echo e($package['template_name']); ?></h2>
                    <p class="mb-0 text-muted small"><i class="fas fa-calendar-alt me-1"></i>Period: <?php echo e($package['period_start']); ?> to <?php echo e($package['period_end']); ?></p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <?php if ($is_probationary_package): ?><span class="probationary-type-badge">Probationary</span><?php endif; ?>
                    <?php echo renderOrganizationPipelineBadge($conn, (int)$package['package_id']); ?>
                    <button class="btn btn-sm btn-package-collapse<?php echo $package_is_open ? '' : ' collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#pkgBody-act-<?php echo (int)$package['package_id']; ?>" aria-expanded="<?php echo $package_is_open ? 'true' : 'false'; ?>" aria-controls="pkgBody-act-<?php echo (int)$package['package_id']; ?>" title="<?php echo $package_is_open ? 'Minimize Review' : 'Expand Review'; ?>">
                        <i class="fas fa-chevron-up collapse-icon"></i> <span class="d-none d-sm-inline"><?php echo $package_is_open ? 'Minimize' : 'Expand'; ?></span>
                    </button>
                </div>
            </header>
            <div class="collapse<?php echo $package_is_open ? ' show' : ''; ?>" id="pkgBody-act-<?php echo (int)$package['package_id']; ?>">
                <div class="package-card__body">
                <?php if ($is_probationary_package && !empty($members)): ?>
                <?php foreach ($members as $summary_member):
                    $summary_kra = (float)$summary_member['kra_subtotal'];
                    $summary_behavior = (float)($summary_member['individual_behavior'] ?? $summary_member['behavior_average']);
                    $summary_total = calculateEvalTotal($summary_kra, $summary_behavior, 80, 20);
                ?>
                <section class="probationary-review-summary" aria-label="<?php echo e($summary_member['first_name'] . ' ' . $summary_member['last_name']); ?> score summary">
                    <div class="probationary-review-top">
                        <div class="probationary-review-person page-hero fadeup mb-0">
                            <img class="probationary-review-avatar" src="<?php echo e(getEmployeeAvatar($summary_member['profile_picture'] ?? '')); ?>" alt="<?php echo e($summary_member['first_name'] . ' ' . $summary_member['last_name']); ?> profile picture" data-default-avatar="<?php echo e(getEmployeeAvatar('')); ?>" onerror="this.onerror=null;this.src=this.dataset.defaultAvatar;">
                            <div><strong><?php echo e($summary_member['first_name'] . ' ' . $summary_member['last_name']); ?></strong><span><?php echo e($summary_member['job_title']); ?></span></div>
                            <div class="probationary-review-location"><span><i class="fas fa-building" aria-hidden="true"></i><?php echo e($summary_member['branch_name'] ?? ''); ?></span><span><i class="fas fa-sitemap" aria-hidden="true"></i><?php echo e($package['department_name']); ?></span></div>
                        </div>
                    <div class="probationary-review-score page-hero fadeup mb-0"><span>Current Overall Score</span><div><strong><?php echo number_format($summary_total, 2); ?></strong> / 4.00</div><small><i class="far fa-clock" aria-hidden="true"></i> Pending Review</small></div>
                    </div>
                    <div class="table-responsive"><table class="table probationary-review-breakdown mb-0"><thead><tr><th>Category</th><th class="text-end">Score</th></tr></thead><tbody>
                        <tr><td><i class="fas fa-bullseye" aria-hidden="true"></i> Key Result Areas (KRA)</td><td class="text-end"><?php echo number_format($summary_kra, 2); ?> / 4.00</td></tr>
                        <tr><td><i class="fas fa-award" aria-hidden="true"></i> Core Values / Behavior</td><td class="text-end"><?php echo number_format($summary_behavior, 2); ?> / 4.00</td></tr>
                        <tr class="probationary-review-total"><th>Current Overall Score</th><th class="text-end"><?php echo number_format($summary_total, 2); ?> / 4.00</th></tr>
                    </tbody></table></div>
                </section>
                <?php endforeach; ?>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <?php if ($is_unclaimed_group_step): ?>
                        <button class="btn btn-success fw-bold px-4" type="submit" form="pkgForm-<?php echo (int)$package['package_id']; ?>"><i class="fas fa-check me-2" aria-hidden="true"></i>Accept Review</button>
                    <?php endif; ?>
                    <?php if ($can_adjust): foreach ($members as $summary_member): ?>
                        <a class="btn btn-action-adjust" href="<?php echo BASE_URL; ?>/employee/package-member-review.php?package_id=<?php echo (int)$package['package_id']; ?>&evaluation_id=<?php echo (int)$summary_member['evaluation_id']; ?>">
                            <i class="fas fa-sliders-h me-1" aria-hidden="true"></i>Review &amp; Decide<?php echo count($members) > 1 ? ' — ' . e($summary_member['first_name'] . ' ' . $summary_member['last_name']) : ''; ?>
                        </a>
                    <?php endforeach; endif; ?>
                </div>
                <?php endif; ?>
                <?php if (!$is_probationary_package): ?>
                <div class="package-stat-grid mb-3">
                    <div class="package-stat">
                        <div class="package-stat-icon" style="background: rgba(8, 46, 6, 0.08); color: var(--rp-forest-green, #082E06);">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="package-stat-content">
                            <strong><?php echo $is_probationary_package ? count($members) : $department_team_size; ?> <?php echo $is_probationary_package ? 'Employee' : 'Employees'; ?></strong>
                            <span><?php echo $is_probationary_package ? 'Individual Review' : 'Department Team Size'; ?></span>
                        </div>
                    </div>
                    <div class="package-stat">
                        <div class="package-stat-icon" style="background: rgba(189, 148, 20, 0.12); color: var(--rp-primary-gold-dark, #8C6D0D);">
                            <i class="fas fa-handshake"></i>
                        </div>
                        <div class="package-stat-content">
                            <strong><?php echo $is_probationary_package ? number_format((float)($members[0]['individual_behavior'] ?? $members[0]['behavior_average'] ?? 0), 2) : ($package['shared_behavior_score'] !== null ? number_format((float)$package['shared_behavior_score'], 2) : 'Calculating…'); ?></strong>
                            <span><?php echo $is_probationary_package ? 'Individual Core Values Score' : 'Shared Behavior Score'; ?></span>
                            <?php if (!$is_probationary_package): ?>
                            <small class="d-block text-muted mt-1" style="font-size: 0.72rem; line-height: 1.25;">
                                Starts with the first submitted Individual Behavior score and updates as more department employees submit, including employees in other evaluation reviews.
                            </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!$is_probationary_package): ?>
                <section class="package-tab-content mb-4" aria-label="Evaluations">
                    <h3 class="h6 fw-bold mb-3"><i class="fas fa-users-cog me-2" aria-hidden="true"></i>Evaluations <span class="badge bg-secondary rounded-pill"><?php echo count($members); ?></span></h3>
                    <div>
                        <div class="table-responsive mb-3">
                            <table class="package-table table align-middle mb-0 probationary-mobile-roster">
                                <thead>
                                    <tr>
                                        <th class="col-employee">Employee</th>
                                        <th class="col-position">Position</th>
                                        <th class="text-end col-score"><?php echo $is_probationary_package ? 'KRA Score' : '<span class="d-none d-lg-inline">Individual </span>KRA'; ?></th>
                                        <th class="text-end col-score"><?php echo $is_probationary_package ? 'Core Values Score' : 'Individual Behavior'; ?></th>
                                        <th class="text-end col-score"><?php echo $is_probationary_package ? 'Weighted Score' : 'Individual<span class="d-none d-xl-inline"> Score</span>'; ?></th>
                                        <th class="text-end col-final-score">Final<span class="d-none d-xl-inline"> Score</span></th>
                                        <th class="col-status">Status</th>
                                        <th class="text-end col-action">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($members as $member): ?>
                                        <?php
                                        $kra_w = $is_probationary_package ? 80 : (isset($package['kra_weight']) && (float)$package['kra_weight'] > 0 ? (float)$package['kra_weight'] : 80);
                                        $beh_w = $is_probationary_package ? 20 : (isset($package['behavior_weight']) && (float)$package['behavior_weight'] > 0 ? (float)$package['behavior_weight'] : 20);
                                        $beh_val = (float)($member['individual_behavior'] ?? $member['behavior_average']);
                                        $total_score_val = calculateEvalTotal((float)$member['kra_subtotal'], $beh_val, $kra_w, $beh_w);
                                        $shared_beh_val = $is_probationary_package ? $beh_val : ($package['shared_behavior_score'] !== null ? (float)$package['shared_behavior_score'] : $beh_val);
                                        $final_score_val = calculateEvalTotal((float)$member['kra_subtotal'], $shared_beh_val, $kra_w, $beh_w);
                                        ?>
                                        <tr>
                                            <td class="col-employee">
                                                <div class="fw-bold"><?php echo e($member['first_name'] . ' ' . $member['last_name']); ?></div>
                                                <?php if (($member['member_status'] ?? 'Normal') === 'Late Rejoined'): ?>
                                                    <span class="badge py-1 px-2" style="background:rgba(234,179,8,0.2); border:1px solid rgba(234,179,8,0.5); color:#fde047; font-size:0.72rem;" title="This member submitted their self-rating after the evaluation review had already advanced. The review was re-opened for consolidation to include them.">
                                                        <i class="fas fa-user-clock me-1"></i>Late Joiner &mdash; Included in Re-Consolidation
                                                    </span>
                                                <?php elseif (($member['member_status'] ?? 'Normal') === 'Catchup Endorsed'): ?>
                                                    <span class="badge bg-success text-white py-1 px-2" style="font-size: 0.72rem;" title="Joined late; endorsed by Supervisor and verified by the evaluation reviewer">
                                                        <i class="fas fa-user-check me-1"></i>Late Addition &mdash; Verified
                                                    </span>
                                                <?php elseif (($member['member_status'] ?? 'Normal') === 'Catchup Complete'): ?>
                                                    <span class="badge bg-primary text-white py-1 px-2" style="font-size: 0.72rem;">
                                                        <i class="fas fa-check-double me-1"></i>Late Catch-Up Complete
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (!empty($member['has_adjustments'])): ?>
                                                    <span class="audit-chip audit-chip--adjusted">
                                                        <i class="fas fa-pen-fancy me-1"></i>Adjusted by Evaluator
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="col-position"><?php echo e($member['job_title']); ?></td>
                                            <td class="col-score text-end tabular-nums fw-semibold"><?php echo number_format((float)$member['kra_subtotal'], 2); ?></td>
                                            <td class="col-score text-end tabular-nums fw-semibold"><?php echo number_format($beh_val, 2); ?></td>
                                            <td class="col-score text-end tabular-nums fw-semibold text-muted"><?php echo number_format($total_score_val, 2); ?></td>
                                            <td class="col-final-score text-end tabular-nums fw-bold text-success" style="font-size: 1.05rem;"><?php echo number_format($final_score_val, 2); ?></td>
                                            <td class="col-status">
                                                <span class="badge bg-secondary"><?php echo e($member['status']); ?></span>
                                            </td>
                                            <td class="col-action text-end">
                                                <?php if ($can_adjust): ?>
                                                    <a class="btn-action-adjust btn btn-sm" href="<?php echo BASE_URL; ?>/employee/package-member-review.php?package_id=<?php echo (int)$package['package_id']; ?>&evaluation_id=<?php echo (int)$member['evaluation_id']; ?>" title="Review ratings<?php echo $is_probationary_package && (int)$package['current_step_order'] === 1 ? ' and record the reviewer decision' : ' and make adjustments'; ?>">
                                                        <i class="fas fa-sliders-h me-1"></i><?php echo $is_probationary_package && (int)$package['current_step_order'] === 1 ? 'Review &amp; Decide' : 'Adjust'; ?>
                                                    </a>
                                                    <?php if ($package['step_type'] === 'Consolidation' && !$is_probationary_package): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-warning ms-1" data-return-toggle="return-member-<?php echo (int)$package['package_id']; ?>-<?php echo (int)$member['evaluation_id']; ?>" aria-expanded="false" aria-controls="return-member-<?php echo (int)$package['package_id']; ?>-<?php echo (int)$member['evaluation_id']; ?>" title="Return this employee evaluation for revision">
                                                            <i class="fas fa-undo me-1"></i>Return
                                                        </button>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <a class="btn-action-view btn btn-sm" href="<?php echo BASE_URL; ?>/employee/package-member-view.php?package_id=<?php echo (int)$package['package_id']; ?>&evaluation_id=<?php echo (int)$member['evaluation_id']; ?>" title="View read-only evaluation">
                                                        <i class="fas fa-eye me-1"></i>View
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php if ($can_adjust && $package['step_type'] === 'Consolidation' && !$is_probationary_package): ?>
                                        <tr id="return-member-<?php echo (int)$package['package_id']; ?>-<?php echo (int)$member['evaluation_id']; ?>" class="package-return-row d-none">
                                            <td colspan="8">
                                                <form method="post" class="package-return-form" onsubmit="return confirm('Return self-rating for <?php echo e($member['first_name'] . ' ' . $member['last_name']); ?> back to employee for revision?');">
                                                    <input type="hidden" name="package_id" value="<?php echo (int)$package['package_id']; ?>">
                                                    <input type="hidden" name="member_evaluation_id" value="<?php echo (int)$member['evaluation_id']; ?>">
                                                    <input type="hidden" name="package_action" value="return_member">
                                                    <?php echo csrfField(); ?>
                                                    <div class="package-return-form__copy"><strong>Return <?php echo e($member['first_name'] . ' ' . $member['last_name']); ?> for revision</strong><span>Tell the employee what needs to be corrected.</span></div>
                                                    <label class="visually-hidden" for="return-reason-<?php echo (int)$member['evaluation_id']; ?>">Return reason</label>
                                                    <input type="text" id="return-reason-<?php echo (int)$member['evaluation_id']; ?>" name="comments" class="form-control form-control-sm" <?php echo $is_probationary_package ? 'required' : ''; ?> placeholder="Return reason<?php echo $is_probationary_package ? ' (required)' : ' (optional)'; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-warning">Confirm Return</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if (!empty($catchup_pending_members)): ?>
                        <?php
                        // Is the current user the active reviewer for this package?
                        $i_am_active_reviewer = !empty($catchup_reviewer_members) &&
                            count(array_filter($catchup_reviewer_members, fn($r) => (int)$r['package_id'] === (int)$package['package_id'])) > 0;
                        ?>
                        <div class="alert <?php echo $i_am_active_reviewer ? 'alert-success' : 'alert-warning'; ?> py-2 px-3 mb-2 small d-flex align-items-start gap-2">
                            <i class="fas fa-user-clock mt-1 flex-shrink-0"></i>
                            <div>
                                <?php if ($i_am_active_reviewer): ?>
                                    <strong>Action Required — Newly Regularized Member(s):</strong>
                                    The following employee(s) submitted their self-rating after this evaluation review began and have been endorsed by the Supervisor.
                                    <strong>Scroll up to the green endorsement card to add them to this review.</strong>
                                <?php else: ?>
                                    <strong>Newly Regularized Member(s) Pending Endorsement:</strong>
                                    The following employee(s) submitted their self-rating after this evaluation review began. They are awaiting endorsement before being officially added.
                                <?php endif; ?>
                                <ul class="mb-0 mt-1">
                                    <?php foreach ($catchup_pending_members as $cp): ?>
                                        <li>
                                            <strong><?php echo e($cp['first_name'] . ' ' . $cp['last_name']); ?></strong>
                                            &mdash; <span class="fw-semibold"><?php echo $i_am_active_reviewer ? '⬆ Scroll up to endorse' : 'Awaiting Endorsement'; ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                </section>
                <?php endif; ?>


                <!-- Action Panel with descriptive hand-off copy & F3 Pre-Submission Modal -->
                <form method="post" class="package-action-panel<?php echo $is_probationary_package ? ' package-action-panel--probationary' : ''; ?><?php echo $is_probationary_package && !empty($members) && ($is_unclaimed_group_step || (($package['evaluation_type'] ?? '') === 'Initial' && (int)$package['current_step_order'] === 1)) ? ' d-none' : ''; ?>" id="pkgForm-<?php echo (int)$package['package_id']; ?>">
                    <input type="hidden" name="package_id" value="<?php echo (int)$package['package_id']; ?>">
                    <input type="hidden" name="package_action" id="actionInput-<?php echo (int)$package['package_id']; ?>" value="<?php echo $is_unclaimed_group_step ? 'claim' : 'approve'; ?>">
                    <?php if ($is_probationary_package && (int)$package['current_step_order'] === 1 && count($members) === 1): ?>
                        <input type="hidden" name="member_evaluation_id" value="<?php echo (int)$members[0]['evaluation_id']; ?>">
                        <input type="hidden" name="return_reason" id="returnReason-<?php echo (int)$package['package_id']; ?>" value="">
                    <?php endif; ?>
                    <?php echo csrfField(); ?>

                    <?php if ($is_unclaimed_group_step): ?>
                        <div class="alert alert-info py-2 px-3 small mb-3">
                            <i class="fas fa-user-check me-1"></i>This evaluation review is available to any eligible active <strong><?php echo e($group_reviewer_label); ?></strong>. Accepting it assigns this review to you; other reviewers can no longer accept this stage.
                        </div>
                        <button class="btn btn-success px-4 py-2 fw-bold <?php echo $is_probationary_package && !empty($members) ? 'd-none' : ''; ?>" type="submit" style="min-height:46px; border-radius:8px;">
                            <i class="fas fa-hand-paper me-1"></i>Accept Review
                        </button>
                    <?php else: ?>
                    <?php if (!$package_next_check['ok']): ?>
                        <div class="alert alert-danger py-2 px-3 small mb-3 border-danger shadow-sm">
                            <i class="fas fa-exclamation-triangle me-1"></i><?php echo $package_next_check['message']; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($signature_gate_locked): ?>
                        <div class="alert alert-warning py-2 px-3 small mb-2" role="status">
                            <i class="fas fa-lock me-1"></i><strong>Approval locked.</strong>
                            Open <strong>Review &amp; Decide</strong><?php echo ($package['evaluation_type'] ?? '') === 'Final' ? ', save the recommendation and signature' : ', save the Immediate head signature'; ?> to unlock approval.
                        </div>
                    <?php endif; ?>

                    <?php if ($package['step_type'] === 'Consolidation'): ?>
                        <div class="alert alert-info py-2 px-3 small mb-3">
                            <i class="fas fa-info-circle me-1"></i><strong>Consolidation step:</strong> Shared Behavior averages submitted Individual Behavior scores across the department, including employees in other evaluation reviews. It updates as more eligible employees submit. Use <strong>Adjust</strong> to adjust a member's score before submitting.
                        </div>
                    <?php elseif ($is_board_step): ?>
                        <div class="alert alert-warning py-2 px-3 small mb-3">
                            <i class="fas fa-shield-alt me-1"></i><strong>Final Board Step:</strong> Approving will lock this evaluation review, apply shared Behavior scores to all <?php echo count($members); ?> members, and publish final appraisal results.
                        </div>
                    <?php elseif ($is_final_probationary_step): ?>
                        <details class="package-approval-details mb-2">
                            <summary><?php echo $is_final_hr_step ? e($package['step_label'] ?? 'Final HR approval') : 'What happens on final approval'; ?></summary>
                            <p class="mb-0 small">Approval records the individual KRA and Core Values score, applies the probationary outcome, and completes this evaluation. The HR Manager receives an informational notification after HR Staff approval.</p>
                        </details>
                    <?php elseif ($package['step_type'] === 'Governance'): ?>
                        <div class="alert alert-secondary py-2 px-3 small mb-3">
                            <i class="fas fa-balance-scale me-1"></i>Governance review stage. Review and adjust scores if necessary before forwarding or approving.
                        </div>
                    <?php endif; ?>

                    <div class="mb-2 package-review-remarks<?php echo $is_probationary_package && $signature_gate_locked ? ' d-none' : ''; ?>">
                        <label class="form-label fw-bold" for="comments-<?php echo (int)$package['package_id']; ?>">
                            Review Remarks &amp; Feedback <span class="text-muted fw-normal">(Optional for approval, required for returns)</span>
                        </label>
                        <textarea class="form-control form-control-sm" id="comments-<?php echo (int)$package['package_id']; ?>" name="comments" rows="2" style="min-height:76px;max-height:160px;resize:vertical;" placeholder="<?php echo $signature_gate_locked ? 'Sign from Review & Decide to unlock review actions.' : 'Enter endorsement remarks or return instructions…'; ?>" <?php echo $signature_gate_locked ? 'disabled aria-disabled="true"' : ''; ?>></textarea>
                    </div>

                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <?php if ($is_probationary_package && ($package['evaluation_type'] ?? '') === 'Final'
                            && (int)$package['current_step_order'] === 2 && !$is_final_hr_step): ?>
                            <button class="btn btn-outline-danger px-4 py-2 fw-bold" type="button"
                                onclick="return rejectProbationaryRegularization(<?php echo (int)$package['package_id']; ?>);" <?php echo $signature_gate_locked ? 'disabled' : ''; ?> style="min-height:46px; border-radius:8px;">
                                <i class="fas fa-times-circle me-1"></i>Decline Regularization
                            </button>
                        <?php endif; ?>
                        <?php if (!$is_probationary_package || !$signature_gate_locked): ?>
                        <?php if (!$package_next_check['ok']): ?>
                            <!-- Blocked Action Button with direct modal to explain reason and route to config -->
                            <button class="btn btn-warning px-4 py-2 fw-bold text-dark" type="button" data-bs-toggle="modal" data-bs-target="#confirmApproveModal-<?php echo (int)$package['package_id']; ?>" <?php echo $signature_gate_locked ? 'disabled' : ''; ?> style="min-height:46px; border-radius:8px;">
                                <i class="fas fa-exclamation-triangle me-1"></i>Review Blocked &mdash; Check Next Reviewer
                            </button>
                        <?php else: ?>
                            <!-- F3: Trigger Pre-Submission Confirmation Modal -->
                            <button class="btn-action-primary btn" type="button" data-bs-toggle="modal" data-bs-target="#confirmApproveModal-<?php echo (int)$package['package_id']; ?>" <?php echo $signature_gate_locked ? 'disabled' : ''; ?>>
                                <?php if ($is_board_step || $is_final_probationary_step): ?>
                                    <i class="fas fa-lock me-1"></i>Approve, Lock, and Apply Results
                                <?php else: ?>
                                    <i class="fas fa-check-circle me-1"></i>Approve &amp; Forward to Next Reviewer
                                <?php endif; ?>
                            </button>
                        <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($is_probationary_package && (int)$package['current_step_order'] === 1 && count($members) === 1): ?>
                            <button class="btn btn-outline-danger px-4 py-2 fw-bold" type="submit" formnovalidate onclick="return submitProbationaryReturn(<?php echo (int)$package['package_id']; ?>);" style="min-height:46px; border-radius:8px;">
                                <i class="fas fa-undo me-1"></i>Return for Revision
                            </button>
                        <?php elseif (!$signature_gate_locked && $package['step_type'] !== 'Consolidation' && !($is_probationary_package && (int)$package['current_step_order'] === 1)): ?>
                            <button class="btn btn-outline-danger px-4 py-2 fw-bold" type="submit" onclick="document.getElementById('actionInput-<?php echo (int)$package['package_id']; ?>').value='return';" <?php echo $signature_gate_locked ? 'disabled' : ''; ?> style="min-height:46px; border-radius:8px;">
                                <i class="fas fa-undo me-1"></i>Return for Revision
                            </button>
                        <?php endif; ?>


                    </div>

                    <!-- F3: Pre-Submission Summary & Confirmation Modal -->
                    <div class="modal fade" id="confirmApproveModal-<?php echo (int)$package['package_id']; ?>" tabindex="-1" aria-labelledby="confirmModalLabel-<?php echo (int)$package['package_id']; ?>" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                            <div class="modal-content border-0 shadow">
                                <div class="modal-header bg-dark text-white">
                                    <h5 class="modal-title fw-bold" id="confirmModalLabel-<?php echo (int)$package['package_id']; ?>">
                                        <i class="fas fa-clipboard-check me-2 text-warning"></i>Confirm Evaluation Review Hand-Off
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body p-4">
                                    <?php if (!$package_next_check['ok']): ?>
                                        <div class="alert alert-danger py-3 px-3 small mb-3 border-danger shadow-sm">
                                            <i class="fas fa-exclamation-triangle me-2 fa-lg text-danger"></i><strong>Forwarding Blocked:</strong> <?php echo $package_next_check['message']; ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="alert alert-info py-2 px-3 small mb-3">
                                        <strong>Department:</strong> <?php echo e($package['department_name']); ?> &bull; 
                                        <strong>Cycle:</strong> <?php echo e($package['period_start']); ?> to <?php echo e($package['period_end']); ?><br>
                                        <strong>Next Stage:</strong> <?php echo e($next_rev_info['step_label'] ?? (($is_board_step || $is_final_probationary_step) ? 'Final Approval' : 'Next Reviewer')); ?> (<?php echo e($next_reviewer_name); ?>)
                                    </div>

                                    <h6 class="fw-bold mb-2 text-dark">Evaluation Review Members:</h6>
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
                                            <input class="form-check-input flex-shrink-0 mt-1" type="checkbox" name="confirm_hand_off" value="1" id="chkConfirm-<?php echo (int)$package['package_id']; ?>" required style="width: 26px; height: 26px; min-height: 26px; cursor: pointer; accent-color: #d97706; appearance: auto; -webkit-appearance: checkbox; border: 2px solid #d97706 !important;" <?php echo (!$package_next_check['ok'] || $signature_gate_locked) ? 'disabled' : ''; ?>>
                                            <label class="form-check-label fw-bold text-dark mb-0 ms-2" for="chkConfirm-<?php echo (int)$package['package_id']; ?>" style="font-size: 0.98rem; line-height: 1.4; cursor: pointer;">
                                                I confirm that I have thoroughly reviewed all member ratings, evaluator adjustments, and developmental plans for this department before forwarding to <span class="text-decoration-underline text-primary"><?php echo e($next_reviewer_name); ?></span>.
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer bg-light">
                                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                                    <?php if ($package_next_check['ok']): ?>
                                        <button type="button" class="btn btn-success px-4 fw-bold" onclick="submitPackageForm(<?php echo (int)$package['package_id']; ?>);" <?php echo $signature_gate_locked ? 'disabled' : ''; ?>>
                                            <i class="fas fa-paper-plane me-1"></i>Confirm &amp; Forward Review
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-danger px-4 fw-bold" disabled>
                                            <i class="fas fa-exclamation-triangle me-1"></i>Routing Not Configured
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </article>
<?php endforeach; ?>
</div>

<script>
function submitProbationaryReturn(packageId) {
    const remarks = document.getElementById('comments-' + packageId);
    const returnReason = document.getElementById('returnReason-' + packageId);
    let reason = remarks && !remarks.disabled ? remarks.value.trim() : '';
    if (!reason) {
        reason = (window.prompt('Enter the reason for returning this evaluation for revision:') || '').trim();
    }
    if (!reason) {
        if (remarks && !remarks.disabled) remarks.focus();
        return false;
    }
    if (!confirm('Return this probationary evaluation to the employee for revision?')) return false;
    if (returnReason) returnReason.value = reason;
    document.getElementById('actionInput-' + packageId).value = 'return_member';
    return true;
}

function rejectProbationaryRegularization(packageId) {
    const comments = document.getElementById('comments-' + packageId);
    if (!comments || !comments.value.trim()) {
        alert('Enter a reason before declining the regularization recommendation.');
        comments?.focus();
        return false;
    }
    if (!confirm('Decline this regularization recommendation and complete the evaluation with probation continuing?')) return false;
    document.getElementById('actionInput-' + packageId).value = 'reject_regularization';
    document.getElementById('pkgForm-' + packageId).requestSubmit();
    return false;
}

const packageCategoryTabs = Array.from(document.querySelectorAll('[data-package-category-tab]'));
const packageCards = Array.from(document.querySelectorAll('[data-package-category]'));
const packageFilterEmpty = document.getElementById('packageFilterEmpty');

document.querySelectorAll('.btn-package-collapse').forEach((button) => {
    const body = document.getElementById(button.getAttribute('aria-controls'));
    if (!body) return;
    body.addEventListener('shown.bs.collapse', () => {
        button.title = 'Minimize Review';
        button.querySelector('span').textContent = 'Minimize';
    });
    body.addEventListener('hidden.bs.collapse', () => {
        button.title = 'Expand Review';
        button.querySelector('span').textContent = 'Expand';
    });
});

document.querySelectorAll('[data-return-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const row = document.getElementById(button.dataset.returnToggle);
        if (!row) return;
        const expanded = button.getAttribute('aria-expanded') === 'true';
        row.classList.toggle('d-none', expanded);
        button.setAttribute('aria-expanded', String(!expanded));
        if (!expanded) row.querySelector('input[name="comments"]')?.focus();
    });
});

function applyPackageFilters() {
    const activeTab = packageCategoryTabs.find((tab) => tab.classList.contains('active'));
    const activeCategory = activeTab ? activeTab.dataset.packageCategoryTab : <?php echo json_encode($active_package_tab); ?>;
    let visibleCardCount = 0;

    packageCards.forEach((card) => {
        const matchesCategory = card.dataset.packageCategory === activeCategory;
        const isVisible = matchesCategory;
        card.classList.toggle('d-none', !isVisible);
        if (isVisible) visibleCardCount++;
    });

    packageCategoryTabs.forEach((tab) => {
        const departmentCards = packageCards.filter((card) => card.dataset.packageCategory === tab.dataset.packageCategoryTab);
        const count = tab.querySelector('.package-category-tab__count');
        if (count) count.textContent = departmentCards.length;
    });

    if (packageFilterEmpty) packageFilterEmpty.classList.toggle('d-none', visibleCardCount > 0);
}

packageCategoryTabs.forEach((tab) => {
    tab.addEventListener('click', () => {
        if (tab.disabled) return;
        packageCategoryTabs.forEach((item) => {
            const active = item === tab;
            item.classList.toggle('active', active);
            item.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        applyPackageFilters();
    });
});
applyPackageFilters();

function submitPackageForm(packageId) {
    const chk = document.getElementById('chkConfirm-' + packageId);
    if (chk && !chk.checked) {
        alert('Please check the confirmation checkbox below the members summary before forwarding the evaluation review.');
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
