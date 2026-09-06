-- ============================================================================
-- 03_test_governance_approvers.sql
-- Evaluation Routing & Governance — Configured Governance Routing Seed
-- Run AFTER 01_test_employees.sql and 02_test_hrd_portal_accounts.sql.
--
-- Assigns:
--   Step 4: Division VP for Acquired Properties -> Eduardo Aquino (AP-T04)
--   Step 5: President & CEO                     -> Gabriel Mendoza (OP-T02)
--   Step 6: Audit Committee                    -> Isabel Mendoza (AUD-S01)
--   Step 7: Board of Directors (Final Lock)     -> Manuel Ramos (AUD-M01)
-- ============================================================================
USE raquel_hris_test_db;

-- Ensure table exists before seeding
CREATE TABLE IF NOT EXISTS evaluation_governance_approvers (
    governance_approver_id INT AUTO_INCREMENT PRIMARY KEY,
    governance_type ENUM('Board of Directors','Audit Committee','President','Division VP') NOT NULL,
    department_id INT NULL,
    user_id INT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_governance_user (governance_type, department_id, user_id),
    CONSTRAINT fk_governance_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_governance_department FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ensure schema is up to date
ALTER TABLE evaluation_governance_approvers
    MODIFY governance_type ENUM('Board of Directors','Audit Committee','President','Division VP') NOT NULL;
ALTER TABLE evaluation_governance_approvers
    ADD COLUMN IF NOT EXISTS department_id INT NULL AFTER governance_type;

-- Clear existing governance rows so seed is clean and idempotent
TRUNCATE TABLE evaluation_governance_approvers;

-- ============================================================================
-- 1. Step 4: Division VP per Department
-- Acquired Properties (dept 1) -> VP for Acquired Properties = AP-T04 (Eduardo Aquino)
-- ============================================================================
INSERT INTO evaluation_governance_approvers (governance_type, department_id, user_id, is_active)
SELECT 'Division VP', 1, u.user_id, 1
FROM users u JOIN employees e ON e.employee_id = u.employee_id
WHERE e.employee_code = 'AP-T04' AND u.role = 'Employee' AND u.is_active = 1 LIMIT 1;

-- ============================================================================
-- 2. Step 5: President & CEO (company-wide, department_id = NULL)
-- OP-T02 (Gabriel Mendoza)
-- ============================================================================
INSERT INTO evaluation_governance_approvers (governance_type, department_id, user_id, is_active)
SELECT 'President', NULL, u.user_id, 1
FROM users u JOIN employees e ON e.employee_id = u.employee_id
WHERE e.employee_code = 'OP-T02' AND u.role = 'Employee' AND u.is_active = 1 LIMIT 1;

-- ============================================================================
-- 3. Step 6: Audit Committee (company-wide, department_id = NULL)
-- AUD-S01 (Isabel Mendoza)
-- ============================================================================
INSERT INTO evaluation_governance_approvers (governance_type, department_id, user_id, is_active)
SELECT 'Audit Committee', NULL, u.user_id, 1
FROM users u JOIN employees e ON e.employee_id = u.employee_id
WHERE e.employee_code = 'AUD-S01' AND u.role = 'Employee' AND u.is_active = 1 LIMIT 1;

-- ============================================================================
-- 4. Step 7: Board of Directors (company-wide, department_id = NULL) [Final Lock]
-- AUD-M01 (Manuel Ramos)
-- ============================================================================
INSERT INTO evaluation_governance_approvers (governance_type, department_id, user_id, is_active)
SELECT 'Board of Directors', NULL, u.user_id, 1
FROM users u JOIN employees e ON e.employee_id = u.employee_id
WHERE e.employee_code = 'AUD-M01' AND u.role = 'Employee' AND u.is_active = 1 LIMIT 1;

-- ============================================================================
-- Verify: show what was seeded
-- ============================================================================
SELECT
    ega.governance_approver_id,
    ega.governance_type,
    IFNULL(d.department_name, '(All Departments)') AS department,
    u.full_name,
    e.job_title,
    IF(ega.is_active, 'Active', 'Disabled') AS status
FROM evaluation_governance_approvers ega
JOIN users u ON u.user_id = ega.user_id
LEFT JOIN employees e ON e.employee_id = u.employee_id
LEFT JOIN departments d ON d.department_id = ega.department_id
ORDER BY FIELD(ega.governance_type,'Division VP','President','Audit Committee','Board of Directors'),
         ega.department_id;
