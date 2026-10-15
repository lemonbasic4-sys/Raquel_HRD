-- Fictional Acquired Properties probationary employees for evaluation targeting tests.
-- Requires database/1st_schema_tables.sql, 2nd_seed_organization.sql,
-- and 3rd_seed_HR_accounts_.sql, in that order.
USE raquel_hris;

INSERT INTO employees (
    employee_id, employee_code, first_name, last_name, middle_name,
    hire_date, date_of_birth, place_of_birth, gender, civil_status,
    job_title_id, job_title, department_id, rank_category_id, branch_id,
    employment_status, employment_type, contract_start_date, contract_end_date,
    reports_to, is_active
)
SELECT seed.employee_id, seed.employee_code, seed.first_name, seed.last_name, seed.middle_name,
       seed.hire_date, seed.date_of_birth, seed.place_of_birth, seed.gender, seed.civil_status,
       jt.job_title_id, jt.job_title, 1, seed.rank_category_id, 102,
       'Probationary', 'Full-time', seed.hire_date,
       DATE_SUB(DATE_ADD(seed.hire_date, INTERVAL 6 MONTH), INTERVAL 1 DAY),
       seed.reports_to, 1
FROM (
              SELECT 92001 employee_id, 'AP-111' employee_code, 'Liana' first_name, 'Mercado' last_name, 'Santos' middle_name, '2026-08-01' hire_date, '2001-04-12' date_of_birth, 'Lucena City' place_of_birth, 'Female' gender, 'Single' civil_status, 108 job_title_id, 5 rank_category_id, 92003 reports_to
    UNION ALL SELECT 92002, 'AP-222', 'Paolo', 'Villanueva', 'Cruz', '2026-09-01', '2000-11-23', 'Tayabas City', 'Male', 'Single', 109, 5, 92004
    UNION ALL SELECT 92003, 'AP-333', 'Mira', 'Bautista', 'Reyes', '2026-08-01', '1996-02-08', 'Lucena City', 'Female', 'Single', 105, 4, 92005
    UNION ALL SELECT 92004, 'AP-444', 'Tomas', 'Aquino', 'Lopez', '2026-09-01', '1995-07-19', 'Sariaya, Quezon', 'Male', 'Married', 106, 4, 92006
    UNION ALL SELECT 92005, 'AP-555', 'Celeste', 'Ramos', 'Dizon', '2026-08-01', '1990-03-14', 'Lucena City', 'Female', 'Married', 101, 3, NULL
    UNION ALL SELECT 92006, 'AP-777', 'Adrian', 'Velasco', 'Lim', '2026-09-01', '1989-12-05', 'Tayabas City', 'Male', 'Married', 102, 3, NULL
) AS seed
JOIN job_titles jt ON jt.job_title_id = seed.job_title_id AND jt.department_id = 1
WHERE NOT EXISTS (
    SELECT 1 FROM employees e
    WHERE e.employee_id = seed.employee_id OR e.employee_code = seed.employee_code
);

-- AP Managers have no seeded AP Vice President to report to yet.
-- Leave their direct-report assignment open for the next reviewer seed.

-- Backfill the probation period if these test employees were imported before
-- the date fields were added. Preserve any dates subsequently edited in the UI.
UPDATE employees e
SET e.contract_start_date = COALESCE(e.contract_start_date, e.hire_date),
    e.contract_end_date = COALESCE(
        e.contract_end_date,
        DATE_SUB(DATE_ADD(e.hire_date, INTERVAL 6 MONTH), INTERVAL 1 DAY)
    )
WHERE e.employee_code IN ('AP-111', 'AP-222', 'AP-333', 'AP-444', 'AP-555', 'AP-777')
  AND e.employment_status = 'Probationary'
  AND (e.contract_start_date IS NULL OR e.contract_end_date IS NULL);

INSERT INTO employee_contacts (employee_id, personal_email, mobile_number)
SELECT e.employee_id, LOWER(CONCAT(e.employee_code, '@test.local')),
       CONCAT('0917', LPAD(e.employee_id, 7, '0'))
FROM employees e
WHERE e.employee_code IN ('AP-111', 'AP-222', 'AP-333', 'AP-444', 'AP-555', 'AP-777')
  AND NOT EXISTS (SELECT 1 FROM employee_contacts c WHERE c.employee_id = e.employee_id);

INSERT INTO employee_details (employee_id, citizenship)
SELECT e.employee_id, 'Filipino'
FROM employees e
WHERE e.employee_code IN ('AP-111', 'AP-222', 'AP-333', 'AP-444', 'AP-555', 'AP-777')
  AND NOT EXISTS (SELECT 1 FROM employee_details d WHERE d.employee_id = e.employee_id);
