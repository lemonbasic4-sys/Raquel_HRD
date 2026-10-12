-- Fictional HR probationary employees for evaluation targeting tests.
-- Requires database/1st_schema_tables.sql, 2nd_seed_organization.sql,
-- and 3rd_seed_HR_accounts_.sql, in that order.
USE raquel_hris;

INSERT INTO employees (
    employee_id, employee_code, first_name, last_name, middle_name,
    hire_date, date_of_birth, place_of_birth, gender, civil_status,
    job_title_id, job_title, department_id, rank_category_id, branch_id,
    employment_status, employment_type, reports_to, is_active
)
SELECT seed.employee_id, seed.employee_code, seed.first_name, seed.last_name, seed.middle_name,
       seed.hire_date, seed.date_of_birth, seed.place_of_birth, seed.gender, seed.civil_status,
       jt.job_title_id, jt.job_title, 7, seed.rank_category_id, 102,
       'Probationary', 'Full-time', seed.reports_to, 1
FROM (
    SELECT 92001 employee_id, 'EVAL-HR-S01' employee_code, 'Liana' first_name, 'Mercado' last_name, 'Santos' middle_name, '2026-08-01' hire_date, '2001-04-12' date_of_birth, 'Lucena City' place_of_birth, 'Female' gender, 'Single' civil_status, 710 job_title_id, 5 rank_category_id, 301 reports_to
    UNION ALL SELECT 92002, 'EVAL-HR-S02', 'Paolo', 'Villanueva', 'Cruz', '2026-09-01', '2000-11-23', 'Tayabas City', 'Male', 'Single', 711, 5, 301
    UNION ALL SELECT 92003, 'EVAL-HR-V01', 'Mira', 'Bautista', 'Reyes', '2026-08-01', '1996-02-08', 'Lucena City', 'Female', 'Single', 705, 4, 101
    UNION ALL SELECT 92004, 'EVAL-HR-V02', 'Tomas', 'Aquino', 'Lopez', '2026-09-01', '1995-07-19', 'Sariaya, Quezon', 'Male', 'Married', 706, 4, 101
    UNION ALL SELECT 92005, 'EVAL-HR-M01', 'Celeste', 'Ramos', 'Dizon', '2026-08-01', '1990-03-14', 'Lucena City', 'Female', 'Married', 700, 3, NULL
    UNION ALL SELECT 92006, 'EVAL-HR-M02', 'Adrian', 'Velasco', 'Lim', '2026-09-01', '1989-12-05', 'Tayabas City', 'Male', 'Married', 701, 3, NULL
) AS seed
JOIN job_titles jt ON jt.job_title_id = seed.job_title_id AND jt.department_id = 7
WHERE NOT EXISTS (
    SELECT 1 FROM employees e
    WHERE e.employee_id = seed.employee_id OR e.employee_code = seed.employee_code
);

INSERT INTO employee_contacts (employee_id, personal_email, mobile_number)
SELECT e.employee_id, LOWER(CONCAT(e.employee_code, '@test.local')),
       CONCAT('0917', LPAD(e.employee_id, 7, '0'))
FROM employees e
WHERE e.employee_code IN ('EVAL-HR-S01', 'EVAL-HR-S02', 'EVAL-HR-V01', 'EVAL-HR-V02', 'EVAL-HR-M01', 'EVAL-HR-M02')
  AND NOT EXISTS (SELECT 1 FROM employee_contacts c WHERE c.employee_id = e.employee_id);

INSERT INTO employee_details (employee_id, citizenship)
SELECT e.employee_id, 'Filipino'
FROM employees e
WHERE e.employee_code IN ('EVAL-HR-S01', 'EVAL-HR-S02', 'EVAL-HR-V01', 'EVAL-HR-V02', 'EVAL-HR-M01', 'EVAL-HR-M02')
  AND NOT EXISTS (SELECT 1 FROM employee_details d WHERE d.employee_id = e.employee_id);
