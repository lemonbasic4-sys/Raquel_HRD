-- ============================================================================
-- 01_test_employees.sql — Active Lean Test Roster for Raquel HRD
-- Includes: Human Resources (HRD), Acquired Properties (AP), Audit (AUD),
--           and Office of the President (OP).
-- Import AFTER 3rd_seed_HR_accounts_.sql and BEFORE xPortal_accounts.sql.
-- Password for every Employee portal account: password
-- ============================================================================
USE raquel_hris_test_db;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. Wire the 3 HRD HRIS employees into their reporting chain:
--    Miguel Torres (302, Staff) -> Patricia Gomez (301, Supervisor / Consolidator) -> Elena Delgado (101, Manager)
-- ----------------------------------------------------------------------------
UPDATE employees SET reports_to = 301 WHERE employee_id = 302;
UPDATE employees SET reports_to = 101 WHERE employee_id = 301;
UPDATE employees SET reports_to = NULL WHERE employee_id = 101;

-- ----------------------------------------------------------------------------
-- 2. Seed Acquired Properties, Audit, and Office of the President Employees
--
-- AP Chain:
--   AP-T01 (Leonora Gomez, Staff) -> AP-T02 (Ronald Lopez, Supervisor/Consolidator) ->
--   AP-T03 (Christopher Tolentino, Manager) -> AP-T04 (Eduardo Aquino, Division VP)
--
-- Audit Chain:
--   AUD-S01 (Isabel Mendoza, Audit Supervisor I) -> AUD-M01 (Manuel Ramos, Audit Manager I)
--
-- President:
--   OP-T02 (Gabriel Mendoza, President and CEO)
-- ----------------------------------------------------------------------------
REPLACE INTO employees (
    employee_id, employee_code, first_name, last_name, middle_name,
    hire_date, date_of_birth, place_of_birth, gender, civil_status,
    job_title_id, job_title, department_id, rank_category_id, branch_id,
    employment_status, employment_type, reports_to, is_active
) VALUES
-- Acquired Properties (dept 1)
(90101, 'AP-T01', 'Leonora', 'Gomez', 'Cruz', '2023-02-01', '1998-11-04', 'Lucena City', 'Female', 'Single', 109, 'AP Staff I', 1, 5, 102, 'Regular', 'Full-time', 90102, 1),
(90102, 'AP-T02', 'Ronald', 'Lopez', 'Del Rosario', '2021-07-14', '1994-07-12', 'Lucena City', 'Male', 'Married', 105, 'AP Supervisor I', 1, 4, 102, 'Regular', 'Full-time', 90103, 1),
(90103, 'AP-T03', 'Christopher', 'Tolentino', 'Gomez', '2018-01-22', '1985-12-03', 'Lucena City', 'Male', 'Married', 101, 'AP Manager I', 1, 3, 102, 'Regular', 'Full-time', 90104, 1),
(90104, 'AP-T04', 'Eduardo', 'Aquino', 'Villanueva', '2014-09-03', '1979-11-24', 'Lucena City', 'Male', 'Married', 100, 'VP for Acquired Properties', 1, 1, 102, 'Regular', 'Full-time', NULL, 1),

-- Audit (dept 2)
(90202, 'AUD-S01', 'Isabel', 'Mendoza', 'Reyes', '2020-06-15', '1992-08-20', 'Lucena City', 'Female', 'Married', 203, 'Audit Supervisor I', 2, 4, 102, 'Regular', 'Full-time', 90203, 1),
(90203, 'AUD-M01', 'Manuel', 'Ramos', 'Rivera', '2018-04-16', '1982-07-25', 'Lucena City', 'Male', 'Married', 200, 'Audit Manager I', 2, 3, 102, 'Regular', 'Full-time', NULL, 1),

-- Office of the President (dept 10)
(91002, 'OP-T02', 'Gabriel', 'Mendoza', 'Santos', '2010-01-04', '1975-03-12', 'Lucena City', 'Male', 'Married', 1100, 'President and CEO', 10, 1, 102, 'Regular', 'Full-time', NULL, 1);

-- ----------------------------------------------------------------------------
-- 3. Employee Contacts
-- ----------------------------------------------------------------------------
REPLACE INTO employee_contacts (employee_id, personal_email, mobile_number, telephone_number) VALUES
(90101, 'ap.t01@test.local', '09170000001', '888-1001'),
(90102, 'ap.t02@test.local', '09170000002', '888-1002'),
(90103, 'ap.t03@test.local', '09170000003', '888-1003'),
(90104, 'ap.t04@test.local', '09170000004', '888-1004'),
(90202, 'isabel.mendoza@test.local', '09170000012', '888-2002'),
(90203, 'manuel.ramos@test.local', '09170000013', '888-2003'),
(91002, 'op.t02@test.local',  '09170000092', '888-0002');

-- ----------------------------------------------------------------------------
-- 4. Create Employee portal accounts (password: password)
-- ----------------------------------------------------------------------------
INSERT INTO users (employee_id, username, email, password_hash, full_name, role, branch_id, is_active, first_login_completed)
SELECT e.employee_id, e.employee_code,
    CONCAT(LOWER(e.employee_code), '@test.local'),
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    TRIM(CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name)),
    'Employee', e.branch_id, 1, 1
FROM employees e
WHERE e.employee_id IN (
    90101, 90102, 90103, 90104,
    90202, 90203,
    91002
)
AND NOT EXISTS (
    SELECT 1 FROM users u WHERE u.employee_id = e.employee_id AND u.role = 'Employee'
);

-- ----------------------------------------------------------------------------
-- 5. Sync full_name and mark test portal accounts first-login complete (skip PDS gate)
-- ----------------------------------------------------------------------------
UPDATE users u
JOIN employees e ON e.employee_id = u.employee_id
SET u.full_name = TRIM(CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name)),
    u.first_login_completed = 1
WHERE u.role = 'Employee'
  AND (
      e.employee_code LIKE 'AP-T%'
      OR e.employee_code LIKE 'AUD-%'
      OR e.employee_code = 'OP-T02'
      OR e.employee_id IN (101, 301, 302)
  );

SET FOREIGN_KEY_CHECKS = 1;
