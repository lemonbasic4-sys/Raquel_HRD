-- ============================================================================
-- 02_test_hrd_portal_accounts.sql
-- 1) Give Elena / Patricia / Miguel Employee-portal logins so they can
--    self-rate (HRIS roles cannot open employee/self-rating.php).
-- 2) Skip first-login PDS friction for test accounts.
-- Portal password for these three: password
-- ============================================================================
USE raquel_hris_test_db;

INSERT INTO users (
    employee_id, username, email, password_hash, full_name, role, branch_id, is_active, first_login_completed
)
SELECT
    e.employee_id,
    e.employee_code,
    CONCAT(LOWER(e.employee_code), '@test.local'),
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    TRIM(CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name)),
    'Employee',
    e.branch_id,
    1,
    1
FROM employees e
WHERE e.employee_id IN (101, 301, 302)
  AND NOT EXISTS (
      SELECT 1 FROM users u
      WHERE u.employee_id = e.employee_id AND u.role = 'Employee'
  );

-- Mark all test portal accounts as first-login complete (skip PDS gate).
UPDATE users u
JOIN employees e ON e.employee_id = u.employee_id
SET u.first_login_completed = 1
WHERE u.role = 'Employee'
  AND (
      e.employee_code LIKE 'AP-T%'
      OR e.employee_id IN (101, 301, 302)
  );
