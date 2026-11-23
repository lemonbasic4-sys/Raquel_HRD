SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;
START TRANSACTION;

-- The current schema has no dedicated employment-status-history table.
-- employee_work_experience is the existing structure used to preserve the two appointment periods.
INSERT INTO employee_work_experience
    (employee_id,date_from,date_to,job_title,company_name,monthly_salary,appointment_status,reason_for_leaving)
SELECT e.employee_id,e.hire_date,DATE_SUB(e.regularization_effective_date,INTERVAL 1 DAY),e.job_title,
       'Raquel Pawnshop, Inc.',
       CASE e.rank_category_id WHEN 1 THEN 70000 WHEN 3 THEN 50000 WHEN 4 THEN 38000 ELSE 28000 END,
       'Probationary','Successfully completed the probationary appointment period'
FROM employees e
WHERE e.employee_code LIKE 'DEMO-%-REG'
  AND e.employment_status='Regular'
  AND e.regularization_effective_date IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM employee_work_experience x
      WHERE x.employee_id=e.employee_id AND x.company_name='Raquel Pawnshop, Inc.' AND x.appointment_status='Probationary'
  );

INSERT INTO employee_work_experience
    (employee_id,date_from,date_to,job_title,company_name,monthly_salary,appointment_status,reason_for_leaving)
SELECT e.employee_id,e.regularization_effective_date,NULL,e.job_title,'Raquel Pawnshop, Inc.',
       CASE e.rank_category_id WHEN 1 THEN 75000 WHEN 3 THEN 55000 WHEN 4 THEN 42000 ELSE 32000 END,
       'Regular',NULL
FROM employees e
WHERE e.employee_code LIKE 'DEMO-%-REG'
  AND e.employment_status='Regular'
  AND e.regularization_effective_date IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM employee_work_experience x
      WHERE x.employee_id=e.employee_id AND x.company_name='Raquel Pawnshop, Inc.' AND x.appointment_status='Regular'
  );

COMMIT;

SELECT COUNT(*) AS presentation_internal_history_rows
FROM employee_work_experience x JOIN employees e ON e.employee_id=x.employee_id
WHERE e.employee_code LIKE 'DEMO-%' AND x.company_name='Raquel Pawnshop, Inc.';
