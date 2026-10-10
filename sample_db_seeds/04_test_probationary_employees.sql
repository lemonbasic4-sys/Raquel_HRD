-- Four individual Initial/Final evaluation test subjects (two per department).
-- Import after 01_test_employees.sql and 02_test_hrd_portal_accounts.sql.
-- Employee-portal password for all accounts: password
USE raquel_hris;

INSERT INTO employees (
    employee_id, employee_code, first_name, last_name, middle_name,
    hire_date, date_of_birth, place_of_birth, gender, civil_status,
    job_title_id, job_title, department_id, rank_category_id, branch_id,
    employment_status, employment_type, reports_to, is_active
)
SELECT 90301, 'HRD-P01', 'Sofia', 'Navarro', 'Reyes',
       '2026-07-01', '2001-02-16', 'Lucena City', 'Female', 'Single',
       711, 'HR Staff I', 7, 5, 102, 'Probationary', 'Full-time', 301, 1
WHERE NOT EXISTS (SELECT 1 FROM employees WHERE employee_id = 90301 OR employee_code = 'HRD-P01');

INSERT INTO employees (
    employee_id, employee_code, first_name, last_name, middle_name,
    hire_date, date_of_birth, place_of_birth, gender, civil_status,
    job_title_id, job_title, department_id, rank_category_id, branch_id,
    employment_status, employment_type, reports_to, is_active
)
SELECT 90302, 'AP-P01', 'Marco', 'Salcedo', 'Cruz',
       '2026-07-01', '2000-08-24', 'Lucena City', 'Male', 'Single',
       109, 'AP Staff I', 1, 5, 102, 'Probationary', 'Full-time', 90102, 1
WHERE NOT EXISTS (SELECT 1 FROM employees WHERE employee_id = 90302 OR employee_code = 'AP-P01');

INSERT INTO employees (
    employee_id, employee_code, first_name, last_name, middle_name,
    hire_date, date_of_birth, place_of_birth, gender, civil_status,
    job_title_id, job_title, department_id, rank_category_id, branch_id,
    employment_status, employment_type, reports_to, is_active
)
SELECT 90303, 'HRD-P02', 'Alyssa', 'Reyes', 'Mae',
       '2026-08-01', '2002-05-19', 'Lucena City', 'Female', 'Single',
       711, 'HR Staff I', 7, 5, 102, 'Probationary', 'Full-time', 301, 1
WHERE NOT EXISTS (SELECT 1 FROM employees WHERE employee_id = 90303 OR employee_code = 'HRD-P02');

INSERT INTO employees (
    employee_id, employee_code, first_name, last_name, middle_name,
    hire_date, date_of_birth, place_of_birth, gender, civil_status,
    job_title_id, job_title, department_id, rank_category_id, branch_id,
    employment_status, employment_type, reports_to, is_active
)
SELECT 90304, 'AP-P02', 'Nathan', 'Garcia', 'Luis',
       '2026-08-01', '2001-10-07', 'Lucena City', 'Male', 'Single',
       109, 'AP Staff I', 1, 5, 102, 'Probationary', 'Full-time', 90102, 1
WHERE NOT EXISTS (SELECT 1 FROM employees WHERE employee_id = 90304 OR employee_code = 'AP-P02');

INSERT INTO employee_contacts (employee_id, personal_email, mobile_number, telephone_number)
SELECT 90301, 'hrd.p01@test.local', '09170000301', '888-9301'
WHERE EXISTS (SELECT 1 FROM employees WHERE employee_id = 90301 AND employee_code = 'HRD-P01')
  AND NOT EXISTS (SELECT 1 FROM employee_contacts WHERE employee_id = 90301);

INSERT INTO employee_contacts (employee_id, personal_email, mobile_number, telephone_number)
SELECT 90302, 'ap.p01@test.local', '09170000302', '888-9302'
WHERE EXISTS (SELECT 1 FROM employees WHERE employee_id = 90302 AND employee_code = 'AP-P01')
  AND NOT EXISTS (SELECT 1 FROM employee_contacts WHERE employee_id = 90302);

INSERT INTO employee_contacts (employee_id, personal_email, mobile_number, telephone_number)
SELECT 90303, 'hrd.p02@test.local', '09170000303', '888-9303'
WHERE EXISTS (SELECT 1 FROM employees WHERE employee_id = 90303 AND employee_code = 'HRD-P02')
  AND NOT EXISTS (SELECT 1 FROM employee_contacts WHERE employee_id = 90303);

INSERT INTO employee_contacts (employee_id, personal_email, mobile_number, telephone_number)
SELECT 90304, 'ap.p02@test.local', '09170000304', '888-9304'
WHERE EXISTS (SELECT 1 FROM employees WHERE employee_id = 90304 AND employee_code = 'AP-P02')
  AND NOT EXISTS (SELECT 1 FROM employee_contacts WHERE employee_id = 90304);

INSERT INTO employee_details (employee_id, height_m, weight_kg, blood_type, citizenship)
SELECT 90301, 1.63, 55.0, 'O+', 'Filipino'
WHERE EXISTS (SELECT 1 FROM employees WHERE employee_id = 90301 AND employee_code = 'HRD-P01')
  AND NOT EXISTS (SELECT 1 FROM employee_details WHERE employee_id = 90301);

INSERT INTO employee_details (employee_id, height_m, weight_kg, blood_type, citizenship)
SELECT 90302, 1.72, 68.0, 'A+', 'Filipino'
WHERE EXISTS (SELECT 1 FROM employees WHERE employee_id = 90302 AND employee_code = 'AP-P01')
  AND NOT EXISTS (SELECT 1 FROM employee_details WHERE employee_id = 90302);

INSERT INTO employee_details (employee_id, height_m, weight_kg, blood_type, citizenship)
SELECT 90303, 1.60, 52.0, 'A+', 'Filipino'
WHERE EXISTS (SELECT 1 FROM employees WHERE employee_id = 90303 AND employee_code = 'HRD-P02')
  AND NOT EXISTS (SELECT 1 FROM employee_details WHERE employee_id = 90303);

INSERT INTO employee_details (employee_id, height_m, weight_kg, blood_type, citizenship)
SELECT 90304, 1.75, 70.0, 'B+', 'Filipino'
WHERE EXISTS (SELECT 1 FROM employees WHERE employee_id = 90304 AND employee_code = 'AP-P02')
  AND NOT EXISTS (SELECT 1 FROM employee_details WHERE employee_id = 90304);

INSERT INTO users (
    employee_id, username, email, password_hash, full_name, role,
    branch_id, is_active, first_login_completed
)
SELECT e.employee_id, e.employee_code, LOWER(CONCAT(e.employee_code, '@test.local')),
       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
       TRIM(CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name)),
       'Employee', e.branch_id, 1, 1
FROM employees e
WHERE e.employee_id IN (90301, 90302, 90303, 90304)
  AND e.employee_code IN ('HRD-P01', 'AP-P01', 'HRD-P02', 'AP-P02')
  AND NOT EXISTS (SELECT 1 FROM users u WHERE u.employee_id = e.employee_id OR u.username = e.employee_code);
