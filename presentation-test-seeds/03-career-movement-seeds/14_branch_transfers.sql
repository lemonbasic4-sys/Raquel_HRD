SET NAMES utf8mb4;
START TRANSACTION;
SET @hr_staff := (SELECT user_id FROM users WHERE username='demo.hr.tristan' LIMIT 1);
SET @hr_manager := (SELECT user_id FROM users WHERE username='elena.delgado' LIMIT 1);

INSERT INTO career_movements (employee_id,movement_type,previous_position,new_position,previous_branch_id,new_branch_id,previous_department_name,new_department_name,effective_date,reason,initiated_by_name,initiated_by_role,initiated_via,request_source,logged_by,approved_by,decision_date,manager_comments,approval_status,is_applied,created_at)
SELECT e.employee_id,'Transfer',e.job_title,e.job_title,123,141,d.department_name,d.department_name,'2024-06-01','[PRESENTATION-SEED:P3-BR-001] Reassigned to support the growing Tayabas service area.','Tristan Dela Cruz','HR Staff','Memo','HR Portal',@hr_staff,@hr_manager,'2024-05-20 10:00:00','Approved after branch staffing review.','Approved',1,'2024-05-15 09:00:00'
FROM employees e JOIN departments d ON d.department_id=e.department_id
WHERE e.employee_code='DEMO-OPS-REG2' AND NOT EXISTS (SELECT 1 FROM career_movements x WHERE x.reason LIKE '[PRESENTATION-SEED:P3-BR-001]%');

INSERT INTO career_movements (employee_id,movement_type,previous_position,new_position,previous_branch_id,new_branch_id,previous_department_name,new_department_name,effective_date,reason,initiated_by_name,initiated_by_role,initiated_via,request_source,logged_by,approved_by,decision_date,manager_comments,approval_status,is_applied,created_at)
SELECT e.employee_id,'Transfer',e.job_title,e.job_title,142,141,d.department_name,d.department_name,'2026-09-01','[PRESENTATION-SEED:P3-BR-002] Deployment aligned with probationary branch staffing needs.','Tristan Dela Cruz','HR Staff','Meeting','HR Portal',@hr_staff,@hr_manager,'2026-08-25 14:00:00','Approved with no change to position or employment status.','Approved',1,'2026-08-20 11:00:00'
FROM employees e JOIN departments d ON d.department_id=e.department_id
WHERE e.employee_code='DEMO-OPS-P01' AND NOT EXISTS (SELECT 1 FROM career_movements x WHERE x.reason LIKE '[PRESENTATION-SEED:P3-BR-002]%');

INSERT INTO career_movements (employee_id,movement_type,previous_position,new_position,previous_branch_id,new_branch_id,previous_department_name,new_department_name,effective_date,reason,initiated_by_name,initiated_by_role,initiated_via,request_source,logged_by,approval_status,is_applied,created_at)
SELECT e.employee_id,'Transfer',e.job_title,e.job_title,e.branch_id,124,d.department_name,d.department_name,'2026-12-15','[PRESENTATION-SEED:P3-BR-003] Proposed temporary coverage for Sariaya Branch 2.','Hugo Lacson','Employee','Employee Portal','Employee Portal',u.user_id,'Pending',0,'2026-11-18 08:30:00'
FROM employees e JOIN departments d ON d.department_id=e.department_id JOIN users u ON u.employee_id=e.employee_id AND u.role='Employee'
WHERE e.employee_code='DEMO-OPS-P02' AND NOT EXISTS (SELECT 1 FROM career_movements x WHERE x.reason LIKE '[PRESENTATION-SEED:P3-BR-003]%');
COMMIT;

SELECT approval_status,is_applied,COUNT(*) AS rows_created FROM career_movements WHERE reason LIKE '[PRESENTATION-SEED:P3-BR-%' GROUP BY approval_status,is_applied;
