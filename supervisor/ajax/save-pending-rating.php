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
    echo json_encode(['success'=>false,'message'=>'Use Annual & 360 Ratings. Annual results use the dedicated peer-rating workflow.']);
    exit;
}
$employee_id = (int)$eval['employee_id'];
$employee_name = $eval['employee_name'];
$rank_category_id = (int)$eval['rank_category_id'];



if ($rank_category_id !== 5) {
    // Check if the employee is an HR Manager
    $emp_hr_role = getEmployeeHRRole($conn, $employee_id);
    if ($emp_hr_role !== 'HR Manager') {
        echo json_encode(['success' => false, 'message' => 'Rating adjustments are only permitted for Rank & File (R&F) employees or HR Manager evaluations.']);
        exit;
    }
}

if (!in_array($eval['status'], ['Pending Supervisor', 'Pending HR Consolidation'], true)) {
    echo json_encode(['success' => false, 'message' => 'Evaluation scores can only be edited while pending supervisor review or HR consolidation.']);
    exit;
}

$conn->begin_transaction();
try {
    // Save overrides and clear manager overrides so they don't block supervisor's edits
    $stmt = $conn->prepare("UPDATE evaluation_scores 
                            SET supervisor_override_score = ?, supervisor_override_by = ?, supervisor_override_at = NOW(),
                                manager_override_score = NULL, manager_override_by = NULL, manager_override_at = NULL
                            WHERE score_id = ? AND evaluation_id = ?");
    
    $altered_details = [];
    foreach ($ratings as $score_id => $rating_val) {
        $score_id = (int)$score_id;
        $rating_val = floatval($rating_val);
        if ($rating_val < 1.00) $rating_val = 1.00;
        if ($rating_val > 4.00) $rating_val = 4.00;

        // Fetch original score_value and previous overrides to determine the effective previous score
        $score_info_q = $conn->query("SELECT es.score_value, es.dept_manager_override_score, es.supervisor_override_score, ec.criterion_name FROM evaluation_scores es JOIN evaluation_criteria ec ON es.criterion_id = ec.criterion_id WHERE es.score_id = $score_id");
        if ($score_info_q && $score_info = $score_info_q->fetch_assoc()) {
            $orig_val = (float)($score_info['supervisor_override_score'] ?? $score_info['dept_manager_override_score'] ?? $score_info['score_value']);
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

    // Mark that supervisor altered the scores
    $conn->query("UPDATE evaluations SET supervisor_altered_scores = 1 WHERE evaluation_id = $evaluation_id");

    // Recalculate evaluation scores
    $recalc = recalculateEvaluationScores($conn, $evaluation_id);
    if (!$recalc) {
        throw new Exception("Failed to recalculate evaluation scores.");
    }

    // Log audit trail
    $audit_details = 'Supervisor override of pending rating scores for ' . $employee_name;
    if (!empty($altered_details)) {
        $audit_details .= ". Score adjustments:\n" . implode("\n", $altered_details);
    }
    logAudit($conn, (int)$_SESSION['user_id'], 'UPDATE', 'Evaluation', $evaluation_id, $audit_details);

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Ratings successfully updated and recalculated.']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
