-- ============================================================================
-- Business Development probationary workflow test employees
-- Prerequisites: organization seed and President/CEO profile seed have run.
-- Employee Portal accounts are created separately by
-- database/create_missing_employee_portal_accounts.sql. Existing Employee
-- accounts are relinked by employee code after the employee rows are replaced.
--
-- Test chain:
--   Probationary Business Development Officer -> President/CEO
--   Probationary Business Development Staff -> Regular BD Officer
-- ============================================================================

USE raquel_hris;

REPLACE INTO employees
    (employee_id, employee_code, first_name, last_name, middle_name,
     hire_date, date_of_birth, place_of_birth, gender, civil_status,
     job_title_id, job_title, department_id, rank_category_id, branch_id,
     reports_to, employment_status, employment_type, contract_start_date,
     contract_end_date, profile_picture, is_active,
     probationary_regularization_eligible, probationary_eligible_evaluation_id,
     probationary_eligible_at, probationary_next_evaluation_type,
     probationary_next_evaluation_due_date)
VALUES
    (15201, 'BD-T01', 'Antonio', 'Dela Cruz', 'Gonzales',
     '2019-04-15', '1987-01-06', 'Lucena City, Quezon', 'Male', 'Married',
     300, 'Business Development Officer I', 3, 3, 102,
     15010, 'Regular', 'Full-time', NULL, NULL, 'avatar_m.jpg', 1,
     0, NULL, NULL, NULL, NULL),
    (15202, 'BD-P01', 'Rhea', 'De Leon', 'Torres',
     '2026-07-20', '1992-12-15', 'Lucena City, Quezon', 'Female', 'Single',
     300, 'Business Development Officer I', 3, 3, 102,
     15010, 'Probationary', 'Full-time', '2026-07-20', '2027-01-19', 'avatar_f.jpg', 1,
     0, NULL, NULL, 'Initial', '2026-10-20'),
    (15203, 'BD-T02', 'Emilio', 'Cruz', 'Salvador',
     '2022-02-14', '1995-07-12', 'Tayabas City, Quezon', 'Male', 'Married',
     302, 'Business Development Staff I', 3, 5, 102,
     15201, 'Regular', 'Full-time', NULL, NULL, 'avatar_m.jpg', 1,
     0, NULL, NULL, NULL, NULL),
    (15204, 'BD-P02', 'Natividad', 'Salvador', 'Ocampo',
     '2026-08-03', '1999-12-01', 'Sariaya, Quezon', 'Female', 'Single',
     302, 'Business Development Staff I', 3, 5, 102,
     15201, 'Probationary', 'Full-time', '2026-08-03', '2027-02-02', 'avatar_f.jpg', 1,
     0, NULL, NULL, 'Initial', '2026-11-03');

-- REPLACE deletes/recreates employee rows; restore any existing portal account
-- links by their stable employee-code usernames before generating missing ones.
UPDATE users u
JOIN employees e ON e.employee_code = u.username
SET u.employee_id = e.employee_id,
    u.branch_id = e.branch_id
WHERE e.employee_id IN (15201, 15202, 15203, 15204)
  AND u.role = 'Employee'
  AND u.deleted_at IS NULL;

REPLACE INTO employee_contacts (employee_id, personal_email, mobile_number, telephone_number)
VALUES
    (15201, 'antonio.delacruz.bd@example.com', '09171515201', '042-710-5201'),
    (15202, 'rhea.deleon.bd@example.com', '09171515202', '042-710-5202'),
    (15203, 'emilio.cruz.bd@example.com', '09171515203', '042-710-5203'),
    (15204, 'natividad.salvador.bd@example.com', '09171515204', '042-710-5204');

-- Verify employee identity and immediate-head links after import.
SELECT e.employee_code,
       CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) AS employee_name,
       e.job_title,
       e.employment_status,
       h.employee_code AS immediate_head_code,
       CONCAT_WS(' ', h.first_name, h.last_name) AS immediate_head
FROM employees e
LEFT JOIN employees h ON h.employee_id = e.reports_to
WHERE e.employee_id IN (15201, 15202, 15203, 15204)
ORDER BY e.employee_id;
