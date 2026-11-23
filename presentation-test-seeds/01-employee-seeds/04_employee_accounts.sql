SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;
START TRANSACTION;

-- Development-only shared password: DemoHRMS!2026
-- Every presentation employee receives an Employee Portal account.
INSERT INTO users
    (employee_id,username,email,password_hash,full_name,role,branch_id,is_active,
     account_hold,first_login_completed,must_change_password)
SELECT e.employee_id,
       LOWER(CONCAT('demo.',REPLACE(e.employee_code,'DEMO-',''))),
       LOWER(CONCAT(REPLACE(e.employee_code,'DEMO-',''),'@portal.demo.raquel.local')),
       '$2y$10$tp.59OeLYLyc/KGE3WN1Vu7RaLL3EcNfx3IhMa8GAFX9p99xBnSga',
       CONCAT(e.first_name,' ',e.last_name),'Employee',e.branch_id,1,0,0,1
FROM employees e
WHERE e.employee_code LIKE 'DEMO-%'
  AND NOT EXISTS (SELECT 1 FROM users u WHERE u.employee_id=e.employee_id AND u.role='Employee');

-- The new regular HR employee also receives authorized HR Portal access.
-- Probationary HR employees remain Employee-only, matching admin/add-user.php eligibility.
INSERT INTO users
    (employee_id,username,email,password_hash,full_name,role,branch_id,is_active,
     account_hold,first_login_completed,must_change_password)
SELECT e.employee_id,'demo.hr.tristan','tristan.delacruz@hr.demo.raquel.local',
       '$2y$10$tp.59OeLYLyc/KGE3WN1Vu7RaLL3EcNfx3IhMa8GAFX9p99xBnSga',
       CONCAT(e.first_name,' ',e.last_name),'HR Staff',e.branch_id,1,0,0,1
FROM employees e
WHERE e.employee_code='DEMO-HR-REG' AND e.employment_status='Regular'
  AND NOT EXISTS (SELECT 1 FROM users u WHERE u.employee_id=e.employee_id AND u.role='HR Staff');

COMMIT;

SELECT COUNT(*) AS presentation_account_count
FROM users u JOIN employees e ON e.employee_id=u.employee_id
WHERE e.employee_code LIKE 'DEMO-%';
