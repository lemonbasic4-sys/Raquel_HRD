-- ============================================================================
-- 03_test_governance_approvers.sql
-- Evaluation Routing & Governance — Configured Governance Routing Seed
-- Run AFTER 01_test_employees.sql and 02_test_hrd_portal_accounts.sql.
--
-- Matches the live test DB assignments (employee_id + portal user_id):
--   Step 4: Division VP for Acquired Properties -> Eduardo Aquino (AP-T04, 90104)
--   Step 5: President & CEO                     -> Gabriel Mendoza (OP-T02, 91002)
--   Step 6: Audit Committee                    -> Isabel Mendoza (AUD-S01, 90202)
--   Step 7: Board of Directors (Final Lock)     -> Manuel Ramos (AUD-M01, 90203)
-- ============================================================================
USE raquel_hris;

-- Ensure table exists before seeding (same shape as includes/functions.php)
CREATE TABLE IF NOT EXISTS evaluation_governance_approvers (
    governance_approver_id INT AUTO_INCREMENT PRIMARY KEY,
    governance_type ENUM('Board of Directors','Audit Committee','President','Division VP') NOT NULL,
    department_id INT NULL,
    employee_id INT NULL,
    user_id INT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_governance_employee (governance_type, department_id, employee_id),
    CONSTRAINT fk_governance_department FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ensure older installs pick up employee_id / nullable user_id
ALTER TABLE evaluation_governance_approvers
    MODIFY governance_type ENUM('Board of Directors','Audit Committee','President','Division VP') NOT NULL;
ALTER TABLE evaluation_governance_approvers
    ADD COLUMN IF NOT EXISTS department_id INT NULL AFTER governance_type;
ALTER TABLE evaluation_governance_approvers
    ADD COLUMN IF NOT EXISTS employee_id INT NULL AFTER department_id;
ALTER TABLE evaluation_governance_approvers
    MODIFY user_id INT NULL;

-- Clear existing governance rows so seed is clean and idempotent
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE evaluation_governance_approvers;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- 1. Step 4: Division VP per Department
-- Acquired Properties (dept 1) -> VP for Acquired Properties = AP-T04 (Eduardo Aquino)
-- ============================================================================
INSERT INTO evaluation_governance_approvers (governance_type, department_id, employee_id, user_id, is_active)
SELECT 'Division VP', 1, e.employee_id, u.user_id, 1
FROM employees e
LEFT JOIN users u ON u.employee_id = e.employee_id AND u.role = 'Employee' AND u.is_active = 1
WHERE e.employee_code = 'AP-T04' AND e.is_active = 1
LIMIT 1;

-- ============================================================================
-- 2. Step 5: President & CEO (company-wide, department_id = NULL)
-- OP-T02 (Gabriel Mendoza)
-- ============================================================================
INSERT INTO evaluation_governance_approvers (governance_type, department_id, employee_id, user_id, is_active)
SELECT 'President', NULL, e.employee_id, u.user_id, 1
FROM employees e
LEFT JOIN users u ON u.employee_id = e.employee_id AND u.role = 'Employee' AND u.is_active = 1
WHERE e.employee_code = 'OP-T02' AND e.is_active = 1
LIMIT 1;

-- ============================================================================
-- 3. Step 6: Audit Committee (company-wide, department_id = NULL)
-- AUD-S01 (Isabel Mendoza)
-- ============================================================================
INSERT INTO evaluation_governance_approvers (governance_type, department_id, employee_id, user_id, is_active)
SELECT 'Audit Committee', NULL, e.employee_id, u.user_id, 1
FROM employees e
LEFT JOIN users u ON u.employee_id = e.employee_id AND u.role = 'Employee' AND u.is_active = 1
WHERE e.employee_code = 'AUD-S01' AND e.is_active = 1
LIMIT 1;

-- ============================================================================
-- 4. Step 7: Board of Directors (company-wide, department_id = NULL) [Final Lock]
-- AUD-M01 (Manuel Ramos)
-- ============================================================================
INSERT INTO evaluation_governance_approvers (governance_type, department_id, employee_id, user_id, is_active)
SELECT 'Board of Directors', NULL, e.employee_id, u.user_id, 1
FROM employees e
LEFT JOIN users u ON u.employee_id = e.employee_id AND u.role = 'Employee' AND u.is_active = 1
WHERE e.employee_code = 'AUD-M01' AND e.is_active = 1
LIMIT 1;

-- ============================================================================
-- Verify: show what was seeded
-- ============================================================================
SELECT
    ega.governance_approver_id,
    ega.governance_type,
    IFNULL(d.department_name, '(All Departments)') AS department,
    e.employee_code,
    TRIM(CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name)) AS full_name,
    e.job_title,
    u.username AS portal_login,
    IF(ega.is_active, 'Active', 'Disabled') AS status
FROM evaluation_governance_approvers ega
JOIN employees e ON e.employee_id = ega.employee_id
LEFT JOIN users u ON u.user_id = ega.user_id
LEFT JOIN departments d ON d.department_id = ega.department_id
ORDER BY FIELD(ega.governance_type,'Division VP','President','Audit Committee','Board of Directors'),
         ega.department_id;
