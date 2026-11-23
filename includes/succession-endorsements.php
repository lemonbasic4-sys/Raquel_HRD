<?php

function successionEndorsementTableReady(mysqli $conn): bool
{
    return $conn->query("SHOW TABLES LIKE 'succession_endorsements'")->num_rows > 0;
}

function successionHasCeoAccount(mysqli $conn): bool
{
    return $conn->query("SELECT u.user_id FROM users u LEFT JOIN employees e ON e.employee_id=u.employee_id
        WHERE u.role IN ('Employee','President and CEO') AND u.is_active=1 AND u.account_hold=0 AND u.deleted_at IS NULL
          AND (u.role='President and CEO' OR (e.job_title='President and CEO' AND e.is_active=1 AND e.deleted_at IS NULL)) LIMIT 1")->num_rows > 0;
}

function successionIsCeoUser(mysqli $conn, int $userId): bool
{
    $stmt = $conn->prepare("SELECT u.user_id FROM users u LEFT JOIN employees e ON e.employee_id=u.employee_id
        WHERE u.user_id=? AND u.role IN ('Employee','President and CEO') AND u.is_active=1 AND u.account_hold=0 AND u.deleted_at IS NULL
          AND (u.role='President and CEO' OR (e.job_title='President and CEO' AND e.is_active=1 AND e.deleted_at IS NULL)) LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $found = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $found;
}

function successionNotifyCeo(mysqli $conn, string $title, string $message, string $link): void
{
    $result = $conn->query("SELECT u.user_id FROM users u LEFT JOIN employees e ON e.employee_id=u.employee_id
        WHERE u.role IN ('Employee','President and CEO') AND u.is_active=1 AND u.account_hold=0 AND u.deleted_at IS NULL
          AND (u.role='President and CEO' OR (e.job_title='President and CEO' AND e.is_active=1 AND e.deleted_at IS NULL))");
    foreach ($result->fetch_all(MYSQLI_ASSOC) as $recipient) {
        createNotification($conn, (int)$recipient['user_id'], $title, $message, $link, 'career', true);
    }
}

function successionNotifyRole(mysqli $conn, string $role, string $title, string $message, string $link, bool $needsAction = true): void
{
    $stmt = $conn->prepare('SELECT user_id FROM users WHERE role=? AND is_active=1 AND account_hold=0 AND deleted_at IS NULL');
    $stmt->bind_param('s', $role);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $recipient) {
        createNotification($conn, (int)$recipient['user_id'], $title, $message, $link, 'career', $needsAction);
    }
    $stmt->close();
}

function successionHandlePost(mysqli $conn): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['succession_action'])) return;
    verifyCsrfToken();
    $role = (string)($_SESSION['role'] ?? '');
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $action = (string)$_POST['succession_action'];
    $reason = trim((string)($_POST['reason'] ?? ''));
    $id = (int)($_POST['endorsement_id'] ?? 0);
    $portal = $role === 'HR Supervisor' ? 'supervisor' : ($role === 'HR Manager' ? 'manager' : 'employee');
    $returnUrl = BASE_URL . '/' . $portal . '/succession-planning.php';
    try {
        if (!successionEndorsementTableReady($conn)) throw new DomainException('Install database/succession_endorsements.sql first.');
        if ($action === 'endorse' && $role === 'HR Supervisor') {
            $employeeId = (int)($_POST['employee_id'] ?? 0);
            if ($employeeId > 0) $returnUrl = BASE_URL . '/supervisor/succession-candidate.php?employee_id=' . $employeeId . '#tab-endorsement';
            $targetId = (int)($_POST['target_job_title_id'] ?? 0);
            if ($reason === '') throw new DomainException('Enter a recommendation before submitting the endorsement.');
            $check = $conn->prepare("SELECT e.employee_id, CONCAT(e.first_name,' ',e.last_name) employee_name, j.job_title target_title
                FROM employees e JOIN job_titles j ON j.job_title_id=? AND j.is_active=1
                WHERE e.employee_id=? AND e.is_active=1 AND e.deleted_at IS NULL AND (e.job_title_id IS NULL OR e.job_title_id<>j.job_title_id)
                  AND EXISTS (SELECT 1 FROM evaluations ev WHERE ev.employee_id=e.employee_id AND ev.status='Approved')");
            $check->bind_param('ii', $targetId, $employeeId);
            $check->execute();
            $employee = $check->get_result()->fetch_assoc();
            $check->close();
            if (!$employee) throw new DomainException('Select an eligible candidate and a different active target position.');
            $duplicate = $conn->prepare("SELECT endorsement_id FROM succession_endorsements WHERE employee_id=? AND status IN ('Pending HR Review','Pending CEO Approval') LIMIT 1");
            $duplicate->bind_param('i', $employeeId);
            $duplicate->execute();
            $exists = $duplicate->get_result()->num_rows > 0;
            $duplicate->close();
            if ($exists) throw new DomainException('This employee already has an endorsement awaiting a decision.');
            $stmt = $conn->prepare('INSERT INTO succession_endorsements (employee_id,target_job_title_id,recommended_by,recommendation_reason) VALUES (?,?,?,?)');
            $stmt->bind_param('iiis', $employeeId, $targetId, $userId, $reason);
            $stmt->execute();
            $id = $conn->insert_id;
            $stmt->close();
            successionNotifyRole($conn, 'HR Manager', 'Succession endorsement needs HR review', $employee['employee_name'] . ' was endorsed for ' . $employee['target_title'] . '.', BASE_URL . '/manager/succession-planning.php#endorsements');
            $message = 'Endorsement submitted for HR review.';
        } elseif (in_array($action, ['forward','return_hr','reject_hr'], true) && $role === 'HR Manager') {
            if ($id < 1 || ($action === 'forward' && strlen($reason) < 20) || ($action !== 'forward' && strlen($reason) < 10)) throw new DomainException('Explain the HR review basis before forwarding, returning, or rejecting.');
            if ($action === 'forward' && (empty($_POST['qualifications_verified']) || empty($_POST['opportunity_verified']) || empty($_POST['records_verified']))) {
                throw new DomainException('Verify qualifications, the position opportunity, and supporting records before forwarding.');
            }
            if ($action === 'forward' && !successionHasCeoAccount($conn)) {
                throw new DomainException('An active President/CEO portal account is required before forwarding.');
            }
            $next = ['forward'=>'Pending CEO Approval','return_hr'=>'Returned','reject_hr'=>'Rejected'][$action];
            $verified = $action === 'forward' ? 1 : 0;
            $stmt = $conn->prepare("UPDATE succession_endorsements SET status=?,hr_reviewed_by=?,hr_reviewed_at=NOW(),hr_review_notes=?,qualifications_verified=?,opportunity_verified=?,records_verified=? WHERE endorsement_id=? AND status='Pending HR Review'");
            $stmt->bind_param('sisiiii', $next, $userId, $reason, $verified, $verified, $verified, $id);
            $stmt->execute();
            $changed = $stmt->affected_rows;
            $stmt->close();
            if (!$changed) throw new DomainException('This endorsement has already been reviewed.');
            if ($action === 'forward') successionNotifyCeo($conn, 'Succession endorsement needs final decision', 'HR reviewed a promotion endorsement. Open it for your decision.', BASE_URL . '/employee/succession-planning.php#endorsements');
            else successionNotifyRole($conn, 'HR Supervisor', 'Succession endorsement ' . strtolower($next), 'HR reviewed endorsement #' . $id . '. Open Succession Planning for the decision and notes.', BASE_URL . '/supervisor/succession-planning.php#endorsements', false);
            $message = 'HR review saved.';
        } elseif (in_array($action, ['approve_ceo','reject_ceo'], true) && successionIsCeoUser($conn, $userId)) {
            if ($id < 1 || ($action === 'reject_ceo' && strlen($reason) < 10)) throw new DomainException('Enter a decision reason before rejecting.');
            $next = $action === 'approve_ceo' ? 'Approved' : 'Rejected';
            $conn->begin_transaction();
            try {
                if ($action === 'approve_ceo') {
                    $candidateStmt = $conn->prepare("SELECT se.employee_id,e.job_title,e.job_title_id,j.job_title AS target_title,j.job_title_id AS target_id,j.department_id AS target_department_id,j.rank_category_id AS target_rank_id
                        FROM succession_endorsements se JOIN employees e ON e.employee_id=se.employee_id JOIN job_titles j ON j.job_title_id=se.target_job_title_id
                        WHERE se.endorsement_id=? AND se.status='Pending CEO Approval' AND e.is_active=1 AND e.deleted_at IS NULL AND j.is_active=1 FOR UPDATE");
                    $candidateStmt->bind_param('i', $id);
                    $candidateStmt->execute();
                    $candidate = $candidateStmt->get_result()->fetch_assoc();
                    $candidateStmt->close();
                    if (!$candidate) throw new DomainException('This endorsement is no longer available for approval.');
                    if ((int)$candidate['job_title_id'] === (int)$candidate['target_id']) throw new DomainException('The employee already holds the target position.');
                    $stmt = $conn->prepare("UPDATE succession_endorsements SET status='Approved',ceo_decided_by=?,ceo_decided_at=NOW(),ceo_decision_notes=?,previous_job_title=?,applied_at=NOW() WHERE endorsement_id=? AND status='Pending CEO Approval'");
                    $stmt->bind_param('issi', $userId, $reason, $candidate['job_title'], $id);
                    $stmt->execute();
                    $changed = $stmt->affected_rows;
                    $stmt->close();
                    if (!$changed) throw new DomainException('This endorsement has already been decided.');
                    $employeeStmt = $conn->prepare("UPDATE employees SET job_title=?,job_title_id=?,department_id=COALESCE(?,department_id),rank_category_id=COALESCE(?,rank_category_id) WHERE employee_id=?");
                    $employeeStmt->bind_param('siiii', $candidate['target_title'], $candidate['target_id'], $candidate['target_department_id'], $candidate['target_rank_id'], $candidate['employee_id']);
                    $employeeStmt->execute();
                    $employeeStmt->close();
                } else {
                    $stmt = $conn->prepare("UPDATE succession_endorsements SET status='Rejected',ceo_decided_by=?,ceo_decided_at=NOW(),ceo_decision_notes=? WHERE endorsement_id=? AND status='Pending CEO Approval'");
                    $stmt->bind_param('isi', $userId, $reason, $id);
                    $stmt->execute();
                    $changed = $stmt->affected_rows;
                    $stmt->close();
                    if (!$changed) throw new DomainException('This endorsement is no longer awaiting a CEO decision.');
                }
                $conn->commit();
            } catch (Throwable $error) {
                $conn->rollback();
                throw $error;
            }
            foreach (['HR Manager'=>'manager','HR Supervisor'=>'supervisor'] as $notifyRole=>$notifyPortal) {
                successionNotifyRole($conn, $notifyRole, 'Succession endorsement ' . strtolower($next), 'The President/CEO decided endorsement #' . $id . '. See the decision in Succession Planning.', BASE_URL . '/' . $notifyPortal . '/succession-planning.php#endorsements', false);
            }
            $message = $action === 'approve_ceo' ? 'Promotion approved. The employee position has been updated.' : 'Endorsement rejected. Employee assignment is unchanged.';
        } else {
            throw new DomainException('Action not allowed for this role.');
        }
        logAudit($conn, $userId, strtoupper('SUCCESSION_' . $action), 'Succession Endorsement', $id, $message, ['module'=>'Career & Succession']);
        $_SESSION['succession_selected_status'] = match ($action) {
            'approve_ceo' => 'approved',
            'reject_ceo', 'reject_hr' => 'rejected',
            'return_hr' => 'returned',
            default => 'ongoing',
        };
        $_SESSION['flash_type'] = 'success';
        $_SESSION['flash_message'] = $message;
    } catch (DomainException $e) {
        $_SESSION['flash_type'] = 'danger';
        $_SESSION['flash_message'] = $e->getMessage();
    }
    header('Location: ' . $returnUrl . (str_contains($returnUrl, '#') ? '' : '#endorsements'));
    exit;
}

function successionListEndorsements(mysqli $conn): array
{
    $sql = "SELECT se.*,CONCAT(e.first_name,' ',e.last_name) employee_name,COALESCE(se.previous_job_title,e.job_title) current_title,
        e.profile_picture,e.department_id,d.department_name,b.branch_name,j.job_title target_title,
        u.full_name recommender_name, h.full_name hr_reviewer_name, c.full_name ceo_name
        FROM succession_endorsements se
        JOIN employees e ON e.employee_id=se.employee_id
        JOIN job_titles j ON j.job_title_id=se.target_job_title_id
        LEFT JOIN departments d ON d.department_id=e.department_id
        LEFT JOIN branches b ON b.branch_id=e.branch_id
        JOIN users u ON u.user_id=se.recommended_by
        LEFT JOIN users h ON h.user_id=se.hr_reviewed_by
        LEFT JOIN users c ON c.user_id=se.ceo_decided_by
        ORDER BY FIELD(se.status,'Pending HR Review','Pending CEO Approval','Returned','Approved','Rejected'),se.created_at DESC";
    return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}
