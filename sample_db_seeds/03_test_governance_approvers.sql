-- ============================================================================
-- 03_test_governance_approvers.sql
-- Evaluation Routing & Governance — HRD & Acquired Properties Routing Seed
-- Run AFTER 01_test_employees.sql and 02_test_hrd_portal_accounts.sql.
--
-- Assigns:
--   Division VP for Acquired Properties → Eduardo Aquino (AP-T04)
-- ============================================================================
USE raquel_hris_test_db;

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
-- Division VP per Department
-- Acquired Properties (dept 1) → VP for Acquired Properties = AP-T04 (Eduardo Aquino)
-- ============================================================================
INSERT INTO evaluation_governance_approvers (governance_type, department_id, user_id, is_active)
SELECT 'Division VP', 1, u.user_id, 1
FROM users u JOIN employees e ON e.employee_id = u.employee_id
WHERE e.employee_code = 'AP-T04' AND u.role = 'Employee' AND u.is_active = 1 LIMIT 1;

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
