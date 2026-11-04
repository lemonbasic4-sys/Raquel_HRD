-- Read-only checks. Run after 01_hr_test_people.sql.
USE raquel_hris;

-- Check 1: Expect six people. Mara and Luis are Probationary.
SELECT employee_id, employee_code, first_name, last_name,
       job_title, employment_status, hire_date, department_id
FROM employees
WHERE employee_id IN (101, 301, 302, 99001, 99002, 99003)
ORDER BY employee_id;

-- Check 2: Expect nine accounts.
-- Elena, Patricia, and Miguel each have one HRIS and one Employee account.
-- Isabella, Mara, and Luis each have one account.
SELECT employee_id, username, role
FROM users
WHERE employee_id IN (101, 301, 302, 99001, 99002, 99003)
ORDER BY employee_id, role;

-- Check 3: Expect at least six rows per section.
-- Addresses should have twelve rows: two for each person.
SELECT 'Contacts' AS section, COUNT(*) AS rows_found
FROM employee_contacts WHERE employee_id IN (101, 301, 302, 99001, 99002, 99003)
UNION ALL
SELECT 'Physical details', COUNT(*)
FROM employee_details WHERE employee_id IN (101, 301, 302, 99001, 99002, 99003)
UNION ALL
SELECT 'Government IDs', COUNT(*)
FROM employee_government_ids WHERE employee_id IN (101, 301, 302, 99001, 99002, 99003)
UNION ALL
SELECT 'Addresses (expect 12)', COUNT(*)
FROM employee_addresses WHERE employee_id IN (101, 301, 302, 99001, 99002, 99003)
UNION ALL
SELECT 'Emergency contacts', COUNT(*)
FROM employee_emergency_contacts WHERE employee_id IN (101, 301, 302, 99001, 99002, 99003)
UNION ALL
SELECT 'Family', COUNT(*)
FROM employee_family WHERE employee_id IN (101, 301, 302, 99001, 99002, 99003)
UNION ALL
SELECT 'Education', COUNT(*)
FROM employee_education WHERE employee_id IN (101, 301, 302, 99001, 99002, 99003)
UNION ALL
SELECT 'Work experience', COUNT(*)
FROM employee_work_experience WHERE employee_id IN (101, 301, 302, 99001, 99002, 99003)
UNION ALL
SELECT 'Disclosures', COUNT(*)
FROM employee_disclosures WHERE employee_id IN (101, 301, 302, 99001, 99002, 99003);
