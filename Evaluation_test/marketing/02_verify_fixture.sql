-- Read-only checks. Run after 01_marketing_test_people.sql.
USE raquel_hris;

-- Expect eight people: one CEO, five regular Marketing, two probationary Marketing.
SELECT employee_id,employee_code,first_name,last_name,job_title,
       department_id,employment_status,hire_date,reports_to
FROM employees
WHERE employee_id BETWEEN 99800 AND 99807
ORDER BY employee_id;

-- Expect eight active portal accounts.
SELECT employee_id,username,role,is_active
FROM users
WHERE employee_id BETWEEN 99800 AND 99807
ORDER BY employee_id;

-- Expect eight rows in each section and sixteen addresses.
SELECT 'Contacts' AS section,COUNT(*) AS rows_found
FROM employee_contacts WHERE employee_id BETWEEN 99800 AND 99807
UNION ALL SELECT 'Physical details',COUNT(*)
FROM employee_details WHERE employee_id BETWEEN 99800 AND 99807
UNION ALL SELECT 'Government IDs',COUNT(*)
FROM employee_government_ids WHERE employee_id BETWEEN 99800 AND 99807
UNION ALL SELECT 'Addresses (expect 16)',COUNT(*)
FROM employee_addresses WHERE employee_id BETWEEN 99800 AND 99807
UNION ALL SELECT 'Emergency contacts',COUNT(*)
FROM employee_emergency_contacts WHERE employee_id BETWEEN 99800 AND 99807
UNION ALL SELECT 'Family',COUNT(*)
FROM employee_family WHERE employee_id BETWEEN 99800 AND 99807
UNION ALL SELECT 'Education',COUNT(*)
FROM employee_education WHERE employee_id BETWEEN 99800 AND 99807
UNION ALL SELECT 'Work experience',COUNT(*)
FROM employee_work_experience WHERE employee_id BETWEEN 99800 AND 99807
UNION ALL SELECT 'Disclosures',COUNT(*)
FROM employee_disclosures WHERE employee_id BETWEEN 99800 AND 99807
UNION ALL SELECT 'Training',COUNT(*)
FROM employee_trainings WHERE employee_id BETWEEN 99800 AND 99807
UNION ALL SELECT 'Skills',COUNT(*)
FROM employee_skills WHERE employee_id BETWEEN 99800 AND 99807
UNION ALL SELECT 'References',COUNT(*)
FROM employee_references WHERE employee_id BETWEEN 99800 AND 99807;
