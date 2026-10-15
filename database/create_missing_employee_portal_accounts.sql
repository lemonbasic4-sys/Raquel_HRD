-- Create Employee Portal accounts for eligible employees who do not have one.
-- HR personnel and the President/CEO receive a separate Employee account in
-- addition to their dedicated HRIS or executive account.
USE raquel_hris;

INSERT INTO users (
    employee_id,
    username,
    email,
    password_hash,
    full_name,
    role,
    branch_id,
    is_active,
    first_login_completed,
    created_at
)
SELECT
    e.employee_id,
    e.employee_code,
    CONCAT(LOWER(REPLACE(e.employee_code, '-', '.')), '@employee.test'),
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    TRIM(CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name, e.name_extension)),
    'Employee',
    e.branch_id,
    1,
    0,
    NOW()
FROM employees e
WHERE e.is_active = 1
  AND e.deleted_at IS NULL
  AND e.employee_code IS NOT NULL
  AND TRIM(e.employee_code) <> ''
  AND NOT EXISTS (
      SELECT 1
      FROM users u
      WHERE u.employee_id = e.employee_id
        AND u.role = 'Employee'
        AND u.deleted_at IS NULL
  )
ORDER BY e.employee_id;

