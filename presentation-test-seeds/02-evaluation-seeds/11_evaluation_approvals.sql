SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;
START TRANSACTION;

-- Probationary package route: current P02 records remain Pending Review to demonstrate an open workflow.
INSERT INTO evaluation_package_route_steps
 (package_id,step_order,reviewer_employee_id,reviewer_user_id,step_label,step_type,
  action_status,acted_at,comments)
SELECT p.package_id,1,e.reports_to,
 (SELECT u.user_id FROM users u WHERE u.employee_id=e.reports_to AND u.is_active=1 AND u.deleted_at IS NULL ORDER BY (u.role='Employee') DESC,u.user_id LIMIT 1),
 'Immediate Head Review','Review',
 IF(e.employee_code LIKE 'DEMO-%-P02','Pending','Approved'),
 IF(e.employee_code LIKE 'DEMO-%-P02',NULL,DATE_SUB(ev.evaluation_period_end,INTERVAL 1 DAY)),
 IF(e.employee_code LIKE 'DEMO-%-P02',NULL,'Reviewed against documented KRA evidence and Core Values ratings.')
FROM evaluation_package_members pm JOIN evaluation_packages p ON p.package_id=pm.package_id
JOIN evaluations ev ON ev.evaluation_id=pm.evaluation_id AND ev.legacy_reference LIKE 'PRES-%'
JOIN employees e ON e.employee_id=ev.employee_id JOIN employees manager ON manager.employee_id=e.reports_to
WHERE ev.evaluation_type IN('Initial','Final')
 AND NOT EXISTS(SELECT 1 FROM evaluation_package_route_steps x WHERE x.package_id=p.package_id AND x.step_order=1);

UPDATE evaluation_packages p
JOIN evaluation_package_members pm ON pm.package_id=p.package_id
JOIN evaluations ev ON ev.evaluation_id=pm.evaluation_id AND ev.legacy_reference LIKE 'PRES-%'
JOIN employees e ON e.employee_id=ev.employee_id
SET p.status=IF(e.employee_code LIKE 'DEMO-%-P02','Pending Review','Approved and Applied'),
 p.current_step_order=1
WHERE ev.evaluation_type IN('Initial','Final');

INSERT INTO probationary_evaluation_reviews
 (evaluation_id,recommendation,recommendation_reason,immediate_head_user_id,immediate_head_signature_data,
  immediate_head_signed_at,employee_acknowledged_by,employee_ack_signature_data,employee_acknowledged_at)
SELECT ev.evaluation_id,
 IF(ev.evaluation_type='Final','Recommend Regularization','Continue Probation'),
 IF(ev.evaluation_type='Final','The employee completed the probationary requirements and demonstrated sustained role readiness.','The employee is progressing and should continue the documented probationary development plan.'),
 (SELECT u.user_id FROM users u WHERE u.employee_id=e.reports_to AND u.is_active=1 AND u.deleted_at IS NULL ORDER BY (u.role='Employee') DESC,u.user_id LIMIT 1),
 CONCAT('presentation-head-signature:',manager.employee_code),DATE_SUB(ev.evaluation_period_end,INTERVAL 1 DAY),
 (SELECT u.user_id FROM users u WHERE u.employee_id=e.employee_id AND u.role='Employee' AND u.is_active=1 AND u.deleted_at IS NULL ORDER BY u.user_id LIMIT 1),
 CONCAT('presentation-employee-signature:',e.employee_code),ev.evaluation_period_end
FROM evaluations ev JOIN employees e ON e.employee_id=ev.employee_id JOIN employees manager ON manager.employee_id=e.reports_to
WHERE ev.legacy_reference LIKE 'PRES-%' AND ev.evaluation_type IN('Initial','Final')
 AND e.employee_code NOT LIKE 'DEMO-%-P02'
 AND NOT EXISTS(SELECT 1 FROM probationary_evaluation_reviews x WHERE x.evaluation_id=ev.evaluation_id);

INSERT INTO evaluation_package_audit(package_id,user_id,action,remarks,created_at)
SELECT p.package_id,rs.reviewer_user_id,
 IF(rs.action_status='Approved','APPROVED','CREATED'),
 IF(rs.action_status='Approved','Immediate head approved the presentation probationary evaluation.','Presentation probationary evaluation is awaiting immediate-head review.'),
 COALESCE(rs.acted_at,p.created_at)
FROM evaluation_packages p JOIN evaluation_package_route_steps rs ON rs.package_id=p.package_id AND rs.step_order=1
JOIN evaluation_package_members pm ON pm.package_id=p.package_id JOIN evaluations ev ON ev.evaluation_id=pm.evaluation_id
WHERE ev.legacy_reference LIKE 'PRES-%'
 AND NOT EXISTS(SELECT 1 FROM evaluation_package_audit x WHERE x.package_id=p.package_id);

-- Annual head approval follows complete required peer submissions.
UPDATE annual_evaluation_members m
JOIN evaluations ev ON ev.evaluation_id=m.evaluation_id AND ev.legacy_reference LIKE 'PRES-ANNUAL-%'
JOIN employees e ON e.employee_id=m.employee_id
SET m.head_approved_by=(SELECT u.user_id FROM users u WHERE u.employee_id=e.reports_to AND u.is_active=1 AND u.deleted_at IS NULL ORDER BY (u.role='Employee') DESC,u.user_id LIMIT 1),
 m.head_approved_at=DATE_SUB(ev.evaluation_period_end,INTERVAL 1 DAY);

COMMIT;

SELECT p.status,COUNT(*) AS package_count FROM evaluation_packages p JOIN evaluation_package_members pm ON pm.package_id=p.package_id JOIN evaluations ev ON ev.evaluation_id=pm.evaluation_id WHERE ev.legacy_reference LIKE 'PRES-%' GROUP BY p.status;
