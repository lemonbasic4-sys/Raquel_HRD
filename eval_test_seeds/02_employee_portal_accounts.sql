-- Test-only Employee portal accounts for the six fictional HR employees.
-- Shared test password: password. Do not use these credentials in production.
USE raquel_hris;

INSERT INTO users (
    employee_id, username, email, password_hash, full_name, role,
    branch_id, is_active, first_login_completed
)
SELECT e.employee_id, e.employee_code, LOWER(CONCAT(e.employee_code, '@test.local')),
       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
       TRIM(CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name)),
       'Employee', e.branch_id, 1, 1
FROM employees e
WHERE e.employee_code IN ('EVAL-HR-S01', 'EVAL-HR-S02', 'EVAL-HR-V01', 'EVAL-HR-V02', 'EVAL-HR-M01', 'EVAL-HR-M02')
  AND NOT EXISTS (
      SELECT 1 FROM users u
      WHERE u.employee_id = e.employee_id
         OR u.username = e.employee_code
         OR u.email = LOWER(CONCAT(e.employee_code, '@test.local'))
  );
