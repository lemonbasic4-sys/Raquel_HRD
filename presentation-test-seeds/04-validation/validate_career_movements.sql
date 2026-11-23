SET NAMES utf8mb4;

SELECT 'career_movement_counts' AS check_name,cm.approval_status AS status,cm.is_applied,COUNT(*) AS actual
FROM career_movements cm JOIN employees e ON e.employee_id=cm.employee_id
WHERE e.employee_code LIKE 'DEMO-%' AND cm.reason LIKE '[PRESENTATION-SEED:P3-%'
GROUP BY cm.approval_status,cm.is_applied;

SELECT 'promotion_counts' AS check_name,se.status,IF(se.applied_at IS NULL,0,1) AS is_applied,COUNT(*) AS actual
FROM succession_endorsements se JOIN employees e ON e.employee_id=se.employee_id
WHERE e.employee_code LIKE 'DEMO-%' AND se.recommendation_reason LIKE '[PRESENTATION-SEED:P3-%'
GROUP BY se.status,IF(se.applied_at IS NULL,0,1);

SELECT 'completed_transfer_assignment_mismatch' AS check_name,COUNT(*) AS failures
FROM career_movements cm JOIN employees e ON e.employee_id=cm.employee_id
WHERE cm.reason LIKE '[PRESENTATION-SEED:P3-%' AND cm.approval_status='Approved' AND cm.is_applied=1
  AND (NOT (cm.new_branch_id <=> e.branch_id) OR cm.new_position<>e.job_title OR cm.new_department_name<>(SELECT d.department_name FROM departments d WHERE d.department_id=e.department_id));

SELECT 'pending_rejected_movement_applied' AS check_name,COUNT(*) AS failures
FROM career_movements WHERE reason LIKE '[PRESENTATION-SEED:P3-%' AND approval_status IN ('Pending','Rejected') AND is_applied<>0;

SELECT 'approved_promotion_assignment_mismatch' AS check_name,COUNT(*) AS failures
FROM succession_endorsements se JOIN employees e ON e.employee_id=se.employee_id
WHERE se.recommendation_reason LIKE '[PRESENTATION-SEED:P3-%' AND se.status='Approved' AND (se.applied_at IS NULL OR se.target_job_title_id<>e.job_title_id);

SELECT 'movement_chronology_errors' AS check_name,COUNT(*) AS failures
FROM career_movements WHERE reason LIKE '[PRESENTATION-SEED:P3-%' AND (decision_date<created_at OR (approval_status='Approved' AND effective_date<DATE(decision_date)));

SELECT 'protected_employee_phase3_rows' AS check_name,
       (SELECT COUNT(*) FROM career_movements WHERE employee_id IN (101,301,302) AND reason LIKE '[PRESENTATION-SEED:P3-%')
       +(SELECT COUNT(*) FROM succession_endorsements WHERE employee_id IN (101,301,302) AND recommendation_reason LIKE '[PRESENTATION-SEED:P3-%') AS failures;

SELECT 'duplicate_regularization_requests' AS check_name,COUNT(*) AS failures
FROM regularization_requests rr JOIN employees e ON e.employee_id=rr.employee_id WHERE e.employee_code LIKE 'DEMO-%';
