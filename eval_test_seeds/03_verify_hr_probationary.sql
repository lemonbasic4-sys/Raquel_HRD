-- Expected: 3 regular HRD employees and 6 probationary Acquired Properties employees.
-- The three regular employees have both HRIS and Employee accounts.
-- The six AP probationary employees have Employee accounts only.
USE raquel_hris;

SELECT e.employee_code, CONCAT_WS(' ', e.first_name, e.last_name) AS employee_name,
       d.department_name, jt.job_title AS position, e.employment_status,
       SUM(u.role = 'Employee' AND u.is_active = 1 AND u.deleted_at IS NULL) AS portal_accounts,
       SUM(u.role IN ('HR Manager', 'HR Supervisor', 'HR Staff') AND u.is_active = 1 AND u.deleted_at IS NULL) AS hris_accounts
FROM employees e
JOIN job_titles jt ON jt.job_title_id = e.job_title_id
JOIN departments d ON d.department_id = e.department_id
LEFT JOIN users u ON u.employee_id = e.employee_id
WHERE e.employee_code IN (
      'HRD-001', 'HRD-002', 'HRD-003',
      'AP-111', 'AP-222', 'AP-333', 'AP-444', 'AP-555', 'AP-777'
  )
GROUP BY e.employee_id, e.employee_code, e.first_name, e.last_name,
         d.department_name, jt.job_title, e.employment_status
ORDER BY e.employment_status, e.rank_category_id DESC, e.employee_code;

SELECT e.employee_code, u.username, u.role, u.first_login_completed
FROM employees e JOIN users u ON u.employee_id = e.employee_id
WHERE e.employee_code = 'OP-001' AND u.role = 'President and CEO';

SELECT e.employee_code AS probationary_employee, head.employee_code AS direct_report_code,
       CONCAT_WS(' ', head.first_name, head.last_name) AS direct_report_name
FROM employees e LEFT JOIN employees head ON head.employee_id = e.reports_to
WHERE e.employee_code IN ('AP-111', 'AP-222', 'AP-333', 'AP-444', 'AP-555', 'AP-777')
ORDER BY e.rank_category_id DESC, e.employee_code;

SELECT et.evaluation_type, et.template_name, creator.role AS creator_role,
       COUNT(DISTINCT positions.job_title_id) AS target_positions,
       COUNT(DISTINCT criteria.criterion_id) AS criteria_count
FROM evaluation_templates et
JOIN users creator ON creator.user_id = et.created_by
LEFT JOIN evaluation_template_positions positions ON positions.template_id = et.template_id
LEFT JOIN evaluation_criteria criteria ON criteria.template_id = et.template_id
WHERE et.target_department_id IN (1, 7) AND et.status = 'Active' AND et.deleted_at IS NULL
  AND et.evaluation_type IN ('Initial', 'Final')
GROUP BY et.template_id, et.evaluation_type, et.template_name, creator.role
ORDER BY creator.role, et.evaluation_type;

SELECT d.department_name, e.employment_status, e.rank_category_id, COUNT(*) AS employee_count
FROM employees e
JOIN departments d ON d.department_id = e.department_id
WHERE e.employee_code IN (
      'HRD-001', 'HRD-002', 'HRD-003',
      'AP-111', 'AP-222', 'AP-333', 'AP-444', 'AP-555', 'AP-777'
  )
GROUP BY d.department_name, e.employment_status, e.rank_category_id
ORDER BY d.department_name, e.employment_status, e.rank_category_id DESC;
