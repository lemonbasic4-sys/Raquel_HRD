<?php
header('Content-Type: application/json');
require_once '../../includes/session-check.php';
checkRole(['HR Supervisor']);
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

// Fetch evaluation details and check if employee is Rank & File (rank_category_id = 5)
$eval_q = $conn->query("SELECT ev.*, e.rank_category_id, CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.employee_id 
                        FROM evaluations ev 
                        JOIN employees e ON ev.employee_id = e.employee_id 
                        WHERE ev.evaluation_id = $evaluation_id");

if (!$eval_q || $eval_q->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Evaluation record not found.']);
    exit;
}

$eval = $eval_q->fetch_assoc();
if (($eval['evaluation_type'] ?? '') === 'Annual' && annualUsesPeerWorkflow($conn,$evaluation_id)) {
    http_response_code(409);
    echo json_encode(['success'=>false,'message'=>'Use Annual & 360 Ratings. Annual revisions require immediate-head finalization.']);
    exit;
}
$employee_id = (int)$eval['employee_id'];
$employee_name = $eval['employee_name'];
$rank_category_id = (int)$eval['rank_category_id'];



if ($rank_category_id !== 5) {
    // Also allow overrides for HR Manager evaluations
    $emp_hr_role = getEmployeeHRRole($conn, $employee_id);
    if ($emp_hr_role !== 'HR Manager') {
        echo json_encode(['success' => false, 'message' => 'Rating adjustments are only permitted for Rank & File (R&F) employees or HR Manager evaluations.']);
        exit;
    }
}

// Check if the evaluation is in Approved, Rejected, or Returned state
if (!in_array($eval['status'], ['Approved', 'Rejected', 'Returned'])) {
    echo json_encode(['success' => false, 'message' => 'Evaluation scores can only be edited for approved, rejected, or returned records.']);
    exit;
}

$conn->begin_transaction();
try {
    // Save overrides and clear manager overrides so they don't block supervisor's edits
    $stmt = $conn->prepare("UPDATE evaluation_scores 
                            SET supervisor_override_score = ?, supervisor_override_by = ?, supervisor_override_at = NOW(),
                                manager_override_score = NULL, manager_override_by = NULL, manager_override_at = NULL
                            WHERE score_id = ? AND evaluation_id = ?");
    
    foreach ($ratings as $score_id => $rating_val) {
        $score_id = (int)$score_id;
        $rating_val = floatval($rating_val);
        if ($rating_val < 1.00) $rating_val = 1.00;
        if ($rating_val > 4.00) $rating_val = 4.00;

        $user_id = (int)$_SESSION['user_id'];
        $stmt->bind_param("diii", $rating_val, $user_id, $score_id, $evaluation_id);
        $stmt->execute();
    }
    $stmt->close();

    // Mark that supervisor altered the scores
    $conn->query("UPDATE evaluations SET supervisor_altered_scores = 1 WHERE evaluation_id = $evaluation_id");

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
            'Your performance evaluation scores were adjusted by HR Supervisor ' . $_SESSION['full_name'] . '.',
            BASE_URL . '/employee/self-rating.php'
        );
    }

    // 2. Notify HR Managers
    $managers = $conn->query("SELECT user_id FROM users WHERE role = 'HR Manager' AND is_active = 1");
    while ($mgr = $managers->fetch_assoc()) {
        createNotification(
            $conn,
            (int)$mgr['user_id'],
            'Supervisor Override Alert',
            'HR Supervisor ' . $_SESSION['full_name'] . ' adjusted rating scores for R&F employee ' . $employee_name . '.',
            BASE_URL . '/manager/evaluation-history.php'
        );
    }

    // Log audit trail
    logAudit($conn, (int)$_SESSION['user_id'], 'UPDATE', 'Evaluation', $evaluation_id, 'Supervisor override of rating scores for ' . $employee_name);

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Ratings successfully overridden and recalculated.']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
