SET NAMES utf8mb4;
START TRANSACTION;
SET @hr_staff := (SELECT user_id FROM users WHERE username='demo.hr.tristan' LIMIT 1);
SET @hr_manager := (SELECT user_id FROM users WHERE username='elena.delgado' LIMIT 1);

INSERT INTO career_movements (employee_id,movement_type,previous_position,new_position,previous_branch_id,new_branch_id,previous_department_name,new_department_name,effective_date,reason,initiated_by_name,initiated_by_role,initiated_via,request_source,logged_by,approved_by,decision_date,manager_comments,approval_status,is_applied,created_at)
SELECT e.employee_id,'Transfer','Marketing Staff I',e.job_title,e.branch_id,e.branch_id,'Marketing',d.department_name,'2024-02-01','[PRESENTATION-SEED:P3-DEPT-001] Skills matched the business development research workload.','Tristan Dela Cruz','HR Staff','Meeting','HR Portal',@hr_staff,@hr_manager,'2024-01-22 15:00:00','Approved after receiving-department review.','Approved',1,'2024-01-12 09:15:00'
FROM employees e JOIN departments d ON d.department_id=e.department_id
WHERE e.employee_code='DEMO-BD-REG2' AND NOT EXISTS (SELECT 1 FROM career_movements x WHERE x.reason LIKE '[PRESENTATION-SEED:P3-DEPT-001]%');

INSERT INTO career_movements (employee_id,movement_type,previous_position,new_position,previous_branch_id,new_branch_id,previous_department_name,new_department_name,effective_date,reason,initiated_by_name,initiated_by_role,initiated_via,request_source,logged_by,approved_by,decision_date,manager_comments,approval_status,is_applied,created_at)
SELECT e.employee_id,'Transfer','Auditor I',e.job_title,e.branch_id,e.branch_id,'Audit',d.department_name,'2025-03-01','[PRESENTATION-SEED:P3-DEPT-002] Internal controls experience supports compliance monitoring.','Tristan Dela Cruz','HR Staff','Letter','HR Portal',@hr_staff,@hr_manager,'2025-02-18 13:30:00','Approved following role and qualification review.','Approved',1,'2025-02-10 10:00:00'
FROM employees e JOIN departments d ON d.department_id=e.department_id
WHERE e.employee_code='DEMO-COM-REG2' AND NOT EXISTS (SELECT 1 FROM career_movements x WHERE x.reason LIKE '[PRESENTATION-SEED:P3-DEPT-002]%');

INSERT INTO career_movements (employee_id,movement_type,previous_position,new_position,previous_branch_id,new_branch_id,previous_department_name,new_department_name,effective_date,reason,initiated_by_name,initiated_by_role,initiated_via,request_source,logged_by,approved_by,decision_date,manager_comments,approval_status,is_applied,created_at)
SELECT e.employee_id,'Transfer',e.job_title,'GS Supervisor I',e.branch_id,e.branch_id,d.department_name,'General Services','2026-10-01','[PRESENTATION-SEED:P3-DEPT-003] Requested cross-functional move to facilities administration.','Olivia Lim','Employee','Employee Portal','Employee Portal',u.user_id,@hr_manager,'2026-09-20 16:00:00','Rejected because the receiving role requires facilities operations experience.','Rejected',0,'2026-09-10 08:45:00'
FROM employees e JOIN departments d ON d.department_id=e.department_id JOIN users u ON u.employee_id=e.employee_id AND u.role='Employee'
WHERE e.employee_code='DEMO-FIN-REG2' AND NOT EXISTS (SELECT 1 FROM career_movements x WHERE x.reason LIKE '[PRESENTATION-SEED:P3-DEPT-003]%');
COMMIT;

SELECT approval_status,is_applied,COUNT(*) AS rows_created FROM career_movements WHERE reason LIKE '[PRESENTATION-SEED:P3-DEPT-%' GROUP BY approval_status,is_applied;
