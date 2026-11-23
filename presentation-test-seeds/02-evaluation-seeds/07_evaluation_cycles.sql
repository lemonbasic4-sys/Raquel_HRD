SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;
START TRANSACTION;

-- Historical Initial and Final evaluations for all Regular presentation employees.
INSERT INTO evaluations
 (employee_id,template_id,evaluation_type,evaluation_period_start,evaluation_period_end,
  submitted_by,assigned_by,assigned_at,status,submitted_date,staff_comments,current_position,
  months_in_position,employee_consent_agreed,is_historical,record_source,legacy_reference,historical_remarks)
SELECT e.employee_id,t.template_id,p.eval_type,
 e.hire_date,
 CASE p.eval_type WHEN 'Initial' THEN DATE_ADD(e.hire_date,INTERVAL 3 MONTH) ELSE DATE_ADD(e.hire_date,INTERVAL 5 MONTH) END,
 (SELECT u.user_id FROM users u WHERE u.employee_id=e.employee_id AND u.is_active=1 AND u.deleted_at IS NULL ORDER BY (u.role='Employee') DESC,u.user_id LIMIT 1),
 (SELECT u.user_id FROM users u WHERE u.employee_id=COALESCE(e.reports_to,(SELECT employee_id FROM employees WHERE employee_code='CEO-001' LIMIT 1)) AND u.is_active=1 AND u.deleted_at IS NULL ORDER BY (u.role='Employee') DESC,u.user_id LIMIT 1),
 DATE_ADD(e.hire_date,INTERVAL 5 DAY),'Pending Reporting Review',
 CASE p.eval_type WHEN 'Initial' THEN DATE_ADD(e.hire_date,INTERVAL 3 MONTH) ELSE DATE_ADD(e.hire_date,INTERVAL 5 MONTH) END,
 CONCAT('Historical ',p.eval_type,' self-assessment for the presentation dataset.'),e.job_title,
 CASE p.eval_type WHEN 'Initial' THEN 3 ELSE 5 END,1,1,'Presentation Seed',
 CONCAT('PRES-',UPPER(p.eval_type),'-',e.employee_code),
 'Synthetic historical probationary record created for presentation use.'
FROM employees e
CROSS JOIN(SELECT 'Initial' eval_type UNION ALL SELECT 'Final') p
JOIN evaluation_templates t ON t.target_department_id=e.department_id AND t.evaluation_type=p.eval_type
 AND t.form_code LIKE 'PRES-%' AND t.deleted_at IS NULL
WHERE (e.employee_code LIKE 'DEMO-%-REG%' OR e.employee_code IN('AP-001','HRD-001','HRD-002','HRD-003'))
 AND NOT EXISTS(SELECT 1 FROM evaluations x WHERE x.legacy_reference=CONCAT('PRES-',UPPER(p.eval_type),'-',e.employee_code));

-- Current probationary employees receive an Initial record appropriate to current tenure.
INSERT INTO evaluations
 (employee_id,template_id,evaluation_type,evaluation_period_start,evaluation_period_end,
  submitted_by,assigned_by,assigned_at,status,submitted_date,staff_comments,current_position,
  months_in_position,employee_consent_agreed,is_historical,record_source,legacy_reference,historical_remarks)
SELECT e.employee_id,t.template_id,'Initial',e.hire_date,DATE_ADD(e.hire_date,INTERVAL 3 MONTH),
 (SELECT u.user_id FROM users u WHERE u.employee_id=e.employee_id AND u.is_active=1 AND u.deleted_at IS NULL ORDER BY (u.role='Employee') DESC,u.user_id LIMIT 1),
 (SELECT u.user_id FROM users u WHERE u.employee_id=COALESCE(e.reports_to,(SELECT employee_id FROM employees WHERE employee_code='CEO-001' LIMIT 1)) AND u.is_active=1 AND u.deleted_at IS NULL ORDER BY (u.role='Employee') DESC,u.user_id LIMIT 1),
 DATE_ADD(e.hire_date,INTERVAL 5 DAY),'Pending Reporting Review',DATE_ADD(e.hire_date,INTERVAL 3 MONTH),
 'Initial probationary self-assessment for the presentation dataset.',e.job_title,3,1,0,'Presentation Seed',
 CONCAT('PRES-INITIAL-',e.employee_code),'Current probationary Initial evaluation.'
FROM employees e JOIN evaluation_templates t ON t.target_department_id=e.department_id AND t.evaluation_type='Initial'
 AND t.form_code LIKE 'PRES-%' AND t.deleted_at IS NULL
WHERE e.employee_code LIKE 'DEMO-%-P__' AND e.employment_status='Probationary'
 AND NOT EXISTS(SELECT 1 FROM evaluations x WHERE x.legacy_reference=CONCAT('PRES-INITIAL-',e.employee_code));

-- Complete Annual history from the calendar year after regularization through 2025.
INSERT INTO evaluations
 (employee_id,template_id,evaluation_type,evaluation_period_start,evaluation_period_end,
  submitted_by,assigned_by,assigned_at,status,submitted_date,staff_comments,current_position,
  months_in_position,employee_consent_agreed,is_historical,record_source,legacy_reference,historical_remarks)
SELECT e.employee_id,t.template_id,'Annual',MAKEDATE(y.eval_year,1),STR_TO_DATE(CONCAT(y.eval_year,'-12-31'),'%Y-%m-%d'),
 (SELECT u.user_id FROM users u WHERE u.employee_id=e.employee_id AND u.is_active=1 AND u.deleted_at IS NULL ORDER BY (u.role='Employee') DESC,u.user_id LIMIT 1),
 (SELECT u.user_id FROM users u WHERE u.employee_id=COALESCE(e.reports_to,(SELECT employee_id FROM employees WHERE employee_code='CEO-001' LIMIT 1)) AND u.is_active=1 AND u.deleted_at IS NULL ORDER BY (u.role='Employee') DESC,u.user_id LIMIT 1),
 STR_TO_DATE(CONCAT(y.eval_year,'-01-05'),'%Y-%m-%d'),'Pending Reporting Review',
 STR_TO_DATE(CONCAT(y.eval_year,'-12-15'),'%Y-%m-%d'),
 CONCAT('Annual self-assessment for ',y.eval_year,'.'),e.job_title,TIMESTAMPDIFF(MONTH,e.hire_date,STR_TO_DATE(CONCAT(y.eval_year,'-12-31'),'%Y-%m-%d')),
 1,1,'Presentation Seed',CONCAT('PRES-ANNUAL-',e.employee_code,'-',y.eval_year),
 'Synthetic Annual workflow record with underlying KRA and 360 ratings.'
FROM employees e
JOIN evaluation_templates t ON t.target_department_id=e.department_id AND t.evaluation_type='Annual'
 AND t.form_code LIKE 'PRES-%-ANNUAL' AND t.target_job_title_id=e.job_title_id AND t.deleted_at IS NULL
CROSS JOIN(
 SELECT 2013 eval_year UNION ALL SELECT 2014 UNION ALL SELECT 2015 UNION ALL SELECT 2016 UNION ALL SELECT 2017
 UNION ALL SELECT 2018 UNION ALL SELECT 2019 UNION ALL SELECT 2020
 UNION ALL SELECT 2021 UNION ALL SELECT 2022 UNION ALL SELECT 2023 UNION ALL SELECT 2024 UNION ALL SELECT 2025
) y
WHERE (e.employee_code LIKE 'DEMO-%-REG%' OR e.employee_code IN('AP-001','HRD-001','HRD-002','HRD-003'))
 AND YEAR(COALESCE(e.regularization_effective_date,DATE_ADD(e.hire_date,INTERVAL 6 MONTH)))<y.eval_year
 AND NOT EXISTS(SELECT 1 FROM evaluations x WHERE x.legacy_reference=CONCAT('PRES-ANNUAL-',e.employee_code,'-',y.eval_year));

-- One individual Annual cycle and member per Annual evaluation.
INSERT INTO annual_evaluation_cycles
 (department_id,period_start,period_end,started_at,self_rating_deadline,first_finalized_at,
  completed_at,first_completed_at,ready_for_approval_at,cycle_approved_by,individual_evaluation_id)
SELECT e.department_id,ev.evaluation_period_start,ev.evaluation_period_end,ev.assigned_at,
 DATE_SUB(ev.evaluation_period_end,INTERVAL 10 DAY),NULL,NULL,NULL,NULL,NULL,ev.evaluation_id
FROM evaluations ev JOIN employees e ON e.employee_id=ev.employee_id
WHERE ev.legacy_reference LIKE 'PRES-ANNUAL-%' AND YEAR(ev.evaluation_period_end)<=2025
 AND NOT EXISTS(SELECT 1 FROM annual_evaluation_cycles c WHERE c.individual_evaluation_id=ev.evaluation_id);

INSERT INTO annual_evaluation_members
 (cycle_id,employee_id,evaluation_id,position_id,reviewer_position_id,position_name,self_values_json,
  current_version,revision_pending,finalized_at,is_late_participant,head_approved_by,head_approved_at)
SELECT c.cycle_id,e.employee_id,ev.evaluation_id,e.job_title_id,manager.job_title_id,e.job_title,NULL,0,0,NULL,0,NULL,NULL
FROM evaluations ev JOIN employees e ON e.employee_id=ev.employee_id
JOIN employees manager ON manager.employee_id=COALESCE(e.reports_to,(SELECT employee_id FROM employees WHERE employee_code='CEO-001' LIMIT 1))
JOIN annual_evaluation_cycles c ON c.individual_evaluation_id=ev.evaluation_id
WHERE ev.legacy_reference LIKE 'PRES-ANNUAL-%' AND YEAR(ev.evaluation_period_end)<=2025
 AND NOT EXISTS(SELECT 1 FROM annual_evaluation_members m WHERE m.evaluation_id=ev.evaluation_id);

-- A package preserves the approval trail for every Initial/Final evaluation.
INSERT INTO evaluation_packages(department_id,template_id,evaluation_type,period_start,period_end,consolidator_employee_id,current_step_order,status)
SELECT e.department_id,ev.template_id,ev.evaluation_type,ev.evaluation_period_start,ev.evaluation_period_end,
 COALESCE(e.reports_to,(SELECT employee_id FROM employees WHERE employee_code='CEO-001' LIMIT 1)),1,'Pending Review'
FROM evaluations ev JOIN employees e ON e.employee_id=ev.employee_id
WHERE ev.legacy_reference LIKE 'PRES-%' AND ev.evaluation_type IN('Initial','Final')
 AND NOT EXISTS(SELECT 1 FROM evaluation_package_members pm WHERE pm.evaluation_id=ev.evaluation_id);

INSERT INTO evaluation_package_members(package_id,evaluation_id,member_status,joined_at_step)
SELECT p.package_id,ev.evaluation_id,'Normal',1
FROM evaluations ev JOIN evaluation_packages p ON p.template_id=ev.template_id AND p.department_id=(SELECT department_id FROM employees WHERE employee_id=ev.employee_id)
 AND p.evaluation_type=ev.evaluation_type AND p.period_start=ev.evaluation_period_start AND p.period_end=ev.evaluation_period_end
WHERE ev.legacy_reference LIKE 'PRES-%' AND ev.evaluation_type IN('Initial','Final')
 AND NOT EXISTS(SELECT 1 FROM evaluation_package_members pm WHERE pm.evaluation_id=ev.evaluation_id);

COMMIT;

SELECT evaluation_type,COUNT(*) AS evaluation_count FROM evaluations WHERE legacy_reference LIKE 'PRES-%' GROUP BY evaluation_type;
