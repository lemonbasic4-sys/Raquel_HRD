-- ============================================================================
-- 03_test_governance_approvers.sql
-- Evaluation Routing & Governance — Full Department Routing Seed
-- Run AFTER 01_test_employees.sql and 02_test_hrd_portal_accounts.sql.
--
-- Assigns:
--   Division VP  per department  → from org chart
--   President    (company-wide)  → Gabriel Mendoza (OP-T02)
--   Audit Committee (company-wide) → GOV-AUD
--   Board of Directors (company-wide, Final Lock) → GOV-BOD
-- ============================================================================
USE raquel_hris;

-- Ensure table exists before seeding (safe for fresh DB or existing DB)
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

-- Ensure schema is up to date (in case table was created by older schema)
ALTER TABLE evaluation_governance_approvers
    MODIFY governance_type ENUM('Board of Directors','Audit Committee','President','Division VP') NOT NULL;
ALTER TABLE evaluation_governance_approvers
    ADD COLUMN IF NOT EXISTS department_id INT NULL AFTER governance_type;

-- Clear existing governance rows so this seed is idempotent / re-runnable.
TRUNCATE TABLE evaluation_governance_approvers;

-- ============================================================================
-- TIER 1: Division VP per Department
-- Each department gets one designated Division VP sign-off from the org chart.
-- ============================================================================

-- Acquired Properties (dept 1) → VP for Acquired Properties = AP-T04 (Eduardo Aquino)
INSERT INTO evaluation_governance_approvers (governance_type, department_id, user_id, is_active)
SELECT 'Division VP', 1, u.user_id, 1
FROM users u JOIN employees e ON e.employee_id = u.employee_id
WHERE e.employee_code = 'AP-T04' AND u.role = 'Employee' AND u.is_active = 1 LIMIT 1;

-- Audit (dept 2) → VP Operations = OPS-VP (Rodrigo Castillo)
INSERT INTO evaluation_governance_approvers (governance_type, department_id, user_id, is_active)
SELECT 'Division VP', 2, u.user_id, 1
FROM users u JOIN employees e ON e.employee_id = u.employee_id
WHERE e.employee_code = 'OPS-VP' AND u.role = 'Employee' AND u.is_active = 1 LIMIT 1;

-- Business Development (dept 3) → VP Operations = OPS-VP
INSERT INTO evaluation_governance_approvers (governance_type, department_id, user_id, is_active)
SELECT 'Division VP', 3, u.user_id, 1
FROM users u JOIN employees e ON e.employee_id = u.employee_id
WHERE e.employee_code = 'OPS-VP' AND u.role = 'Employee' AND u.is_active = 1 LIMIT 1;

-- Compliance (dept 4) → VP Operations = OPS-VP
INSERT INTO evaluation_governance_approvers (governance_type, department_id, user_id, is_active)
SELECT 'Division VP', 4, u.user_id, 1
FROM users u JOIN employees e ON e.employee_id = u.employee_id
WHERE e.employee_code = 'OPS-VP' AND u.role = 'Employee' AND u.is_active = 1 LIMIT 1;

-- Finance (dept 5) → VP Finance = FIN-VP (Teresa Reyes)
INSERT INTO evaluation_governance_approvers (governance_type, department_id, user_id, is_active)
SELECT 'Division VP', 5, u.user_id, 1
FROM users u JOIN employees e ON e.employee_id = u.employee_id
WHERE e.employee_code = 'FIN-VP' AND u.role = 'Employee' AND u.is_active = 1 LIMIT 1;

-- General Services (dept 6) → VP General Services = GS-VP (Ricardo Buenaventura)
INSERT INTO evaluation_governance_approvers (governance_type, department_id, user_id, is_active)
SELECT 'Division VP', 6, u.user_id, 1
FROM users u JOIN employees e ON e.employee_id = u.employee_id
WHERE e.employee_code = 'GS-VP' AND u.role = 'Employee' AND u.is_active = 1 LIMIT 1;

-- Human Resources (dept 7) → VP Operations = OPS-VP
INSERT INTO evaluation_governance_approvers (governance_type, department_id, user_id, is_active)
SELECT 'Division VP', 7, u.user_id, 1
FROM users u JOIN employees e ON e.employee_id = u.employee_id
WHERE e.employee_code = 'OPS-VP' AND u.role = 'Employee' AND u.is_active = 1 LIMIT 1;

-- Information Technology (dept 8) → VP Operations = OPS-VP
INSERT INTO evaluation_governance_approvers (governance_type, department_id, user_id, is_active)
SELECT 'Division VP', 8, u.user_id, 1
FROM users u JOIN employees e ON e.employee_id = u.employee_id
WHERE e.employee_code = 'OPS-VP' AND u.role = 'Employee' AND u.is_active = 1 LIMIT 1;

-- Marketing (dept 9) → VP Operations = OPS-VP
INSERT INTO evaluation_governance_approvers (governance_type, department_id, user_id, is_active)
SELECT 'Division VP', 9, u.user_id, 1
FROM users u JOIN employees e ON e.employee_id = u.employee_id
WHERE e.employee_code = 'OPS-VP' AND u.role = 'Employee' AND u.is_active = 1 LIMIT 1;

-- Operations (dept 11) → VP Operations = OPS-VP
INSERT INTO evaluation_governance_approvers (governance_type, department_id, user_id, is_active)
SELECT 'Division VP', 11, u.user_id, 1
FROM users u JOIN employees e ON e.employee_id = u.employee_id
WHERE e.employee_code = 'OPS-VP' AND u.role = 'Employee' AND u.is_active = 1 LIMIT 1;

-- Purchasing (dept 12) → VP Operations = OPS-VP
INSERT INTO evaluation_governance_approvers (governance_type, department_id, user_id, is_active)
SELECT 'Division VP', 12, u.user_id, 1
FROM users u JOIN employees e ON e.employee_id = u.employee_id
WHERE e.employee_code = 'OPS-VP' AND u.role = 'Employee' AND u.is_active = 1 LIMIT 1;

-- ============================================================================
-- TIER 2: Executive — President & CEO (company-wide, department_id = NULL)
-- ============================================================================
INSERT INTO evaluation_governance_approvers (governance_type, department_id, user_id, is_active)
SELECT 'President', NULL, u.user_id, 1
FROM users u JOIN employees e ON e.employee_id = u.employee_id
WHERE e.employee_code = 'OP-T02' AND u.role = 'Employee' AND u.is_active = 1 LIMIT 1;

-- ============================================================================
-- TIER 3: Independent Governance Bodies (company-wide, department_id = NULL)
-- ============================================================================

-- Audit Committee → GOV-AUD (Audit Approver)
INSERT INTO evaluation_governance_approvers (governance_type, department_id, user_id, is_active)
SELECT 'Audit Committee', NULL, u.user_id, 1
FROM users u JOIN employees e ON e.employee_id = u.employee_id
WHERE e.employee_code = 'GOV-AUD' AND u.is_active = 1 LIMIT 1;

-- Board of Directors → GOV-BOD (Board Approver) — Final Lock & Apply
INSERT INTO evaluation_governance_approvers (governance_type, department_id, user_id, is_active)
SELECT 'Board of Directors', NULL, u.user_id, 1
FROM users u JOIN employees e ON e.employee_id = u.employee_id
WHERE e.employee_code = 'GOV-BOD' AND u.is_active = 1 LIMIT 1;

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
