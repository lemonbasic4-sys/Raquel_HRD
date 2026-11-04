-- Read-only checks. Run after 01_finance_test_people.sql.
USE raquel_hris;

-- Expect nine people: one CEO, six regular Finance, two probationary Finance.
SELECT employee_id,employee_code,first_name,last_name,job_title,
       department_id,employment_status,hire_date,reports_to
FROM employees
WHERE employee_id BETWEEN 99500 AND 99508
ORDER BY employee_id;

-- Expect nine active portal accounts.
SELECT employee_id,username,role,is_active
FROM users
WHERE employee_id BETWEEN 99500 AND 99508
ORDER BY employee_id;

-- Expect nine rows in each section and eighteen addresses.
SELECT 'Contacts' AS section,COUNT(*) AS rows_found
FROM employee_contacts WHERE employee_id BETWEEN 99500 AND 99508
UNION ALL SELECT 'Physical details',COUNT(*)
FROM employee_details WHERE employee_id BETWEEN 99500 AND 99508
UNION ALL SELECT 'Government IDs',COUNT(*)
FROM employee_government_ids WHERE employee_id BETWEEN 99500 AND 99508
UNION ALL SELECT 'Addresses (expect 18)',COUNT(*)
FROM employee_addresses WHERE employee_id BETWEEN 99500 AND 99508
UNION ALL SELECT 'Emergency contacts',COUNT(*)
FROM employee_emergency_contacts WHERE employee_id BETWEEN 99500 AND 99508
UNION ALL SELECT 'Family',COUNT(*)
FROM employee_family WHERE employee_id BETWEEN 99500 AND 99508
UNION ALL SELECT 'Education',COUNT(*)
FROM employee_education WHERE employee_id BETWEEN 99500 AND 99508
UNION ALL SELECT 'Work experience',COUNT(*)
FROM employee_work_experience WHERE employee_id BETWEEN 99500 AND 99508
UNION ALL SELECT 'Disclosures',COUNT(*)
FROM employee_disclosures WHERE employee_id BETWEEN 99500 AND 99508
UNION ALL SELECT 'Training',COUNT(*)
FROM employee_trainings WHERE employee_id BETWEEN 99500 AND 99508
UNION ALL SELECT 'Skills',COUNT(*)
FROM employee_skills WHERE employee_id BETWEEN 99500 AND 99508
UNION ALL SELECT 'References',COUNT(*)
FROM employee_references WHERE employee_id BETWEEN 99500 AND 99508;
