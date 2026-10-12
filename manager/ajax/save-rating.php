<?php
header('Content-Type: application/json');
require_once '../../includes/session-check.php';
checkRole(['HR Manager']);
require_once '../../config/database.php';
require_once '../../includes/functions.php';
ensureEvaluationWorkflowSchema($conn);

// Check if it is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

verifyCsrfToken();

$evaluation_id = isset($_POST['evaluation_id']) ? (int)$_POST['evaluation_id'] : 0;
$ratings = isset($_POST['ratings']) ? $_POST['ratings'] : [];

if (!$evaluation_id || empty($ratings)) {
    echo json_encode(['success' => false, 'message' => 'Evaluation ID and ratings are required.']);
    exit;
}

// Fetch evaluation details
$eval_q = $conn->query("SELECT ev.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.employee_id 
                        FROM evaluations ev 
                        JOIN employees e ON ev.employee_id = e.employee_id 
                        WHERE ev.evaluation_id = $evaluation_id");

if (!$eval_q || $eval_q->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Evaluation record not found.']);
    exit;
}

$eval = $eval_q->fetch_assoc();
$employee_id = (int)$eval['employee_id'];
$employee_name = $eval['employee_name'];

if (isEvaluationInLockedOrganizationPackage($conn, $evaluation_id)) {
    echo json_encode(['success' => false, 'message' => 'This evaluation belongs to a locked evaluation review and can no longer be edited.']);
    exit;
}

// Check if the evaluation is in Approved, Rejected, Returned, Pending Manager, or Pending HR Consolidation state
if (!in_array($eval['status'], ['Approved', 'Rejected', 'Returned', 'Pending Manager', 'Pending HR Consolidation'])) {
    echo json_encode(['success' => false, 'message' => 'Evaluation scores can only be edited for approved, rejected, returned, pending manager, or pending HR consolidation records.']);
    exit;
}

$conn->begin_transaction();
try {
    // Save manager overrides
    $stmt = $conn->prepare("UPDATE evaluation_scores 
                            SET manager_override_score = ?, manager_override_by = ?, manager_override_at = NOW() 
                            WHERE score_id = ? AND evaluation_id = ?");
    
    $altered_details = [];
    foreach ($ratings as $score_id => $rating_val) {
        $score_id = (int)$score_id;
        $rating_val = floatval($rating_val);
        if ($rating_val < 1.00) $rating_val = 1.00;
        if ($rating_val > 4.00) $rating_val = 4.00;

        // Fetch original score_value and previous overrides to determine the effective previous score
        $score_info_q = $conn->query("SELECT es.score_value, es.dept_manager_override_score, es.supervisor_override_score, es.manager_override_score, ec.criterion_name FROM evaluation_scores es JOIN evaluation_criteria ec ON es.criterion_id = ec.criterion_id WHERE es.score_id = $score_id");
        if ($score_info_q && $score_info = $score_info_q->fetch_assoc()) {
            $orig_val = (float)($score_info['manager_override_score'] ?? $score_info['supervisor_override_score'] ?? $score_info['dept_manager_override_score'] ?? $score_info['score_value']);
            if (abs($rating_val - $orig_val) > 0.01) {
                $criterion_name = $score_info['criterion_name'];
                $altered_details[] = "$criterion_name (Previous: " . number_format($orig_val, 2) . " -> Adjusted: " . number_format($rating_val, 2) . ")";
            }
        }

        $user_id = (int)$_SESSION['user_id'];
        $stmt->bind_param("diii", $rating_val, $user_id, $score_id, $evaluation_id);
        $stmt->execute();
    }
    $stmt->close();

    // Recalculate evaluation scores
    $recalc = recalculateEvaluationScores($conn, $evaluation_id);
    if (!$recalc) {
        throw new Exception("Failed to recalculate evaluation scores.");
    }

    // Send notifications
    // 1. Notify Evaluated Employee
    $emp_user = $conn->query("SELECT user_id FROM users WHERE employee_id = $employee_id AND role = 'Employee' AND is_active = 1 LIMIT 1")->fetch_assoc();
    if ($emp_user) {
        createNotification(
            $conn,
            (int)$emp_user['user_id'],
            'Performance Evaluation Adjusted',
            'Your performance evaluation scores were adjusted by HR Manager ' . $_SESSION['full_name'] . '.',
            BASE_URL . '/employee/self-rating.php'
        );
    }

    // 2. Notify endorsement supervisor, if any
    if (!empty($eval['endorsed_by'])) {
        createNotification(
            $conn,
            (int)$eval['endorsed_by'],
            'Performance Evaluation Adjusted by Manager',
            'Evaluation scores for ' . $employee_name . ' have been adjusted by HR Manager ' . $_SESSION['full_name'] . '.',
            BASE_URL . '/supervisor/evaluation-history.php'
        );
    }

    // Log audit trail
    $audit_details = 'Manager override of rating scores for ' . $employee_name;
    if (!empty($altered_details)) {
        $audit_details .= ". Score adjustments:\n" . implode("\n", $altered_details);
    }
    logAudit($conn, (int)$_SESSION['user_id'], 'UPDATE', 'Evaluation', $evaluation_id, $audit_details);

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Ratings successfully overridden and recalculated.']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
