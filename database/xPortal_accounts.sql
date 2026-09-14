-- Import this after seed employees
USE raquel_hris;
SET FOREIGN_KEY_CHECKS = 0;

-- Clean up Employee-portal duplicates left by older versions of this seed.
-- These three employees use the dedicated HRIS accounts defined below.
DELETE FROM users
WHERE employee_id IN (101, 301, 302)
  AND role = 'Employee';

-- =============================================
--              Employee Accounts
-- =============================================

REPLACE INTO users (
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
    e.employee_code AS username,
    e.employee_code AS email,
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' AS password_hash,
    TRIM(CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name, e.name_extension)) AS full_name,
    'Employee' AS role,
    e.branch_id,
    1 AS is_active,
    0 AS first_login_completed,
    NOW() AS created_at
FROM employees e
WHERE e.employee_code IS NOT NULL
  AND TRIM(e.employee_code) <> ''
  -- These employees receive their dedicated HRIS accounts below. Excluding
  -- them here prevents duplicate Employee and HRIS accounts for one person.
  AND e.employee_id NOT IN (101, 301, 302)
  AND (
      NOT EXISTS (
          SELECT 1
          FROM users u
          WHERE u.employee_id = e.employee_id
            AND u.role = 'Employee'
      )
      OR EXISTS (
          SELECT 1
          FROM users u
          WHERE u.employee_id = e.employee_id
            AND u.role = 'Employee'
            AND u.username = e.employee_code
      )
  );

-- =============================================
--               User Management
-- =============================================
REPLACE INTO users (employee_id, username, email, full_name, password_hash, role, branch_id, is_active, first_login_completed) 
VALUES
(101, 'elena.delgado', 'elena@company.com', 'Elena Delgado', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'HR Manager', 102, 1, 0),
(301, 'patricia.gomez', 'patricia@company.com', 'Patricia Gomez', '$2y$10$ShFYgbhwbnwUnO9BE0/Ny.Tdohwd2rFgJQ4XtCZJh.tkJylDBLw9e', 'HR Supervisor', 102, 1, 0),
(302, 'miguel.torres', 'miguel@company.com', 'Miguel Torres', '$2y$10$.ZTuiD7q3wdnDCHbEiYQQOyqFpn4IKK4d6G6SLbuLMFTVmKBl8GLK', 'HR Staff', 102, 1, 0);

SET FOREIGN_KEY_CHECKS = 1;
