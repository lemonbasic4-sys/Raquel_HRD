-- Expected: nine HRD employees: 3 regular + 6 probationary.
-- The three regular employees have both HRIS and Employee accounts.
-- The six probationary employees have Employee accounts only.
USE raquel_hris;

SELECT e.employee_code, CONCAT_WS(' ', e.first_name, e.last_name) AS employee_name,
       jt.job_title AS position, e.employment_status,
       SUM(u.role = 'Employee' AND u.is_active = 1 AND u.deleted_at IS NULL) AS portal_accounts,
       SUM(u.role IN ('HR Manager', 'HR Supervisor', 'HR Staff') AND u.is_active = 1 AND u.deleted_at IS NULL) AS hris_accounts
FROM employees e
JOIN job_titles jt ON jt.job_title_id = e.job_title_id
LEFT JOIN users u ON u.employee_id = e.employee_id
WHERE e.department_id = 7
  AND e.employee_code IN (
      'HRD-001', 'HRD-002', 'HRD-003',
      'EVAL-HR-S01', 'EVAL-HR-S02', 'EVAL-HR-V01', 'EVAL-HR-V02', 'EVAL-HR-M01', 'EVAL-HR-M02'
  )
GROUP BY e.employee_id, e.employee_code, e.first_name, e.last_name,
         jt.job_title, e.employment_status
ORDER BY e.employment_status, e.rank_category_id DESC, e.employee_code;

SELECT e.employment_status, e.rank_category_id, COUNT(*) AS employee_count
FROM employees e
WHERE e.department_id = 7
  AND e.employee_code IN (
      'HRD-001', 'HRD-002', 'HRD-003',
      'EVAL-HR-S01', 'EVAL-HR-S02', 'EVAL-HR-V01', 'EVAL-HR-V02', 'EVAL-HR-M01', 'EVAL-HR-M02'
  )
GROUP BY e.employment_status, e.rank_category_id
ORDER BY e.employment_status, e.rank_category_id DESC;
