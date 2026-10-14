-- Local sample President/CEO, using the employee identity from OP_seed.sql.
-- Employee Portal: username OP-001, initial password password.
-- Re-running preserves the account ID and password.
USE raquel_hris;
START TRANSACTION;

INSERT INTO employees (employee_code, first_name, middle_name, last_name,
    hire_date, job_title_id, job_title, department_id, rank_category_id,
    branch_id, employment_status, employment_type, profile_picture, is_active)
SELECT 'OP-001', 'Bernadette', 'Mendoza', 'Salvador', '2018-07-18',
    1100, 'President and CEO', 10, 1, 102, 'Regular', 'Full-time', 'avatar_f.jpg', 1
WHERE NOT EXISTS (SELECT 1 FROM employees WHERE employee_code = 'OP-001');

INSERT INTO users (employee_id, username, email, password_hash, full_name,
    role, branch_id, is_active, first_login_completed)
SELECT e.employee_id, e.employee_code, e.employee_code,
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name),
    'President and CEO', e.branch_id, 1, 1
FROM employees e
WHERE e.employee_code = 'OP-001'
  AND NOT EXISTS (SELECT 1 FROM users u WHERE u.employee_id = e.employee_id);

UPDATE users u JOIN employees e ON e.employee_id = u.employee_id
SET u.role = 'President and CEO'
WHERE e.employee_code = 'OP-001' AND u.role = 'Employee';

INSERT INTO evaluation_governance_approvers
    (governance_type, department_id, employee_id, user_id, is_active)
SELECT 'President', NULL, e.employee_id, u.user_id, 1
FROM employees e JOIN users u ON u.employee_id = e.employee_id
WHERE e.employee_code = 'OP-001' AND u.role = 'President and CEO'
  AND u.is_active = 1 AND u.deleted_at IS NULL
  AND NOT EXISTS (SELECT 1 FROM evaluation_governance_approvers
      WHERE governance_type = 'President' AND department_id IS NULL AND is_active = 1);

COMMIT;
