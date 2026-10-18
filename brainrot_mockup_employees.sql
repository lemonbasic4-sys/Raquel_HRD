-- ============================================================================
-- Brainrot mockup employee seed (6 employee portal accounts)
--
-- Prerequisites: run database/1st_schema_tables.sql,
-- database/2nd_seed_organization.sql, and eval_test_seeds/president_ceo_complete_profile.sql.
-- The existing President and CEO employee is expected to be employee_id 15010
-- with job_title_id 1100. This seed creates Brainrot Test (department_id 13);
-- no full PDS/profile data is added.
--
-- Login: username below; password: password (must be changed on first login).
-- Do not re-import once evaluations or other activity have been recorded:
-- REPLACE INTO re-seeds these employees, positions, and accounts.
-- ============================================================================

USE raquel_hris;

-- Do not REPLACE an existing department row: job_titles may reference it,
-- and REPLACE would delete that parent row before recreating it. If ID 13
-- already exists (as in databases where the sandbox department was added
-- manually), leave it intact and use that row.
REPLACE INTO departments
    (department_id, department_name, description, is_active, created_at, updated_at)
SELECT 13, 'Brainrot Test', 'Sandbox department for mock employee workflow testing.', 1, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM departments WHERE department_id = 13);

-- Test-only positions. Each probationary position reports to an active head so
-- the Initial/Final evaluation review can be tested without relying on another
-- probationary employee as the reviewer.
REPLACE INTO job_titles
    (job_title_id, job_title, rank_category_id, department_id, is_active, is_head, reports_to, created_at, updated_at)
VALUES
    (19001, 'Manager - Brainrot Mockup',    3, 13, 1, 1, 1100, NOW(), NOW()),
    (19002, 'Supervisor - Brainrot Mockup', 4, 13, 1, 1, 19001, NOW(), NOW()),
    (19003, 'R&F - Brainrot Mockup',         5, 13, 1, 0, 19002, NOW(), NOW()),
    (19004, 'BR Staff',                      5, 13, 1, 0, 19002, NOW(), NOW()),
    (19005, 'BR Supervisor',                4, 13, 1, 1, 19001, NOW(), NOW()),
    (19006, 'BR Manager',                    3, 13, 1, 1, 1100, NOW(), NOW());

REPLACE INTO employees
    (employee_id, employee_code, first_name, last_name, hire_date,
     job_title_id, job_title, department_id, rank_category_id, branch_id,
     reports_to, employment_status, employment_type, contract_start_date,
     contract_end_date, is_active)
VALUES
    -- Regular hierarchy: CEO -> Trippi (Manager) -> Tralalelo (Supervisor) -> Tungtungtung (R&F)
    (19001, 'BR-REG-MGR', 'Trippi', 'Troppi', '2020-01-06',
     19001, 'Manager', 13, 3, 102, 15010, 'Regular', 'Full-time', NULL, NULL, 1),
    (19002, 'BR-REG-SUP', 'Tralalelo', 'Tralala', '2021-02-08',
     19002, 'Supervisor', 13, 4, 102, 19001, 'Regular', 'Full-time', NULL, NULL, 1),
    (19003, 'BR-REG-RF', 'Tungtungtung', 'Sahur', '2022-03-14',
     19003, 'R&F', 13, 5, 102, 19002, 'Regular', 'Full-time', NULL, NULL, 1),

    -- Probationary examples: each reports to its active immediate head.
    (19004, 'BR-PRO-STAFF', 'Bombardiro', 'Crocodilo', '2026-10-18',
     19004, 'BR Staff', 13, 5, 102, 19002, 'Probationary', 'Full-time', '2026-10-18', '2027-04-17', 1),
    (19005, 'BR-PRO-SUP', 'Ballerina', 'Cappuccina', '2026-10-18',
     19005, 'BR Supervisor', 13, 4, 102, 19001, 'Probationary', 'Full-time', '2026-10-18', '2027-04-17', 1),
    (19006, 'BR-PRO-MGR', 'Cappuccino', 'Assassino', '2026-10-18',
     19006, 'BR Manager', 13, 3, 102, 15010, 'Probationary', 'Full-time', '2026-10-18', '2027-04-17', 1);

-- Test Employee Portal accounts. Seeded password is "password".
REPLACE INTO users
    (employee_id, username, email, password_hash, full_name, role, branch_id,
     is_active, first_login_completed, must_change_password, created_at)
VALUES
    (19001, 'BR-REG-MGR', 'br.reg.mgr@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Trippi Troppi', 'Employee', 102, 1, 0, 1, NOW()),
    (19002, 'BR-REG-SUP', 'br.reg.sup@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Tralalelo Tralala', 'Employee', 102, 1, 0, 1, NOW()),
    (19003, 'BR-REG-RF', 'br.reg.rf@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Tungtungtung Sahur', 'Employee', 102, 1, 0, 1, NOW()),
    (19004, 'BR-PRO-STAFF', 'br.pro.staff@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Bombardiro Crocodilo', 'Employee', 102, 1, 0, 1, NOW()),
    (19005, 'BR-PRO-SUP', 'br.pro.sup@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ballerina Cappuccina', 'Employee', 102, 1, 0, 1, NOW()),
    (19006, 'BR-PRO-MGR', 'br.pro.mgr@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Cappuccino Assassino', 'Employee', 102, 1, 0, 1, NOW());

-- Verify employee reporting lines and account availability.
SELECT e.employee_code, CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
       e.job_title, e.employment_status,
       CONCAT(h.first_name, ' ', h.last_name) AS reports_to,
       u.username, u.is_active AS account_active
FROM employees e
LEFT JOIN employees h ON h.employee_id = e.reports_to
LEFT JOIN users u ON u.employee_id = e.employee_id
WHERE e.employee_id BETWEEN 19001 AND 19006
ORDER BY e.employee_id;
