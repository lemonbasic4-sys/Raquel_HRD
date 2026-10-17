-- ============================================================================
-- Probationary workflow test accounts for the departments still to test:
-- Finance, General Services, Information Technology, Marketing, and Operations.
--
-- Prerequisites: run the schema, organization, HR accounts, and
-- president_ceo_complete_profile.sql first. CEO employee ID 15010 is used as
-- the head for direct-report positions configured to report to the CEO.
--
-- This seed creates the required regular immediate-head accounts and
-- probationary test employees at each workflow level. It creates Employee
-- Portal accounts directly (username = employee code; password = password).
-- It does not create evaluation templates; create Initial and Final templates
-- through the portal after importing.
--
-- IMPORTANT: Do not rerun after beginning evaluations. REPLACE INTO on the
-- seeded employee/account rows can reset their related workflow history.
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
    -- Finance: VP -> Accounting Manager -> Accounting Supervisor -> Staff
    (16001, 'FIN-T01', 'Gabriel', 'Mercado', 'Santos', '2018-04-02', '1981-03-14', 'Lucena City, Quezon', 'Male', 'Married', 500, 'VP for Finance', 5, 1, 102, 15010, 'Regular', 'Full-time', NULL, NULL, 'avatar_m.jpg', 1, 0, NULL, NULL, NULL, NULL),
    (16002, 'FIN-T02', 'Teresa', 'Villanueva', 'Cruz', '2020-02-10', '1985-06-22', 'Tayabas City, Quezon', 'Female', 'Married', 501, 'Accounting Manager I', 5, 3, 102, 16001, 'Regular', 'Full-time', NULL, NULL, 'avatar_f.jpg', 1, 0, NULL, NULL, NULL, NULL),
    (16003, 'FIN-T03', 'Ramon', 'Castillo', 'Reyes', '2021-08-16', '1989-11-09', 'Sariaya, Quezon', 'Male', 'Married', 502, 'Accounting Supervisor I', 5, 4, 102, 16002, 'Regular', 'Full-time', NULL, NULL, 'avatar_m.jpg', 1, 0, NULL, NULL, NULL, NULL),
    (16007, 'FIN-T04', 'Rosario', 'Perez', 'Aquino', '2022-03-07', '1986-01-25', 'Tayabas City, Quezon', 'Female', 'Married', 506, 'Treasury Supervisor I', 5, 4, 102, 16001, 'Regular', 'Full-time', NULL, NULL, 'avatar_f.jpg', 1, 0, NULL, NULL, NULL, NULL),
    (16004, 'FIN-P01', 'Lourdes', 'Navarro', 'Dizon', '2026-07-17', '1991-04-18', 'Lucena City, Quezon', 'Female', 'Single', 501, 'Accounting Manager I', 5, 3, 102, 16001, 'Probationary', 'Full-time', '2026-07-17', '2027-01-16', 'avatar_f.jpg', 1, 0, NULL, NULL, 'Initial', '2026-10-17'),
    (16005, 'FIN-P02', 'Miguel', 'Ramos', 'Garcia', '2026-07-17', '1993-02-27', 'Pagbilao, Quezon', 'Male', 'Single', 502, 'Accounting Supervisor I', 5, 4, 102, 16002, 'Probationary', 'Full-time', '2026-07-17', '2027-01-16', 'avatar_m.jpg', 1, 0, NULL, NULL, 'Initial', '2026-10-17'),
    (16006, 'FIN-P03', 'Andrea', 'Santos', 'Flores', '2026-07-17', '1998-08-05', 'Candelaria, Quezon', 'Female', 'Single', 511, 'Accounting Staff I', 5, 5, 102, 16003, 'Probationary', 'Full-time', '2026-07-17', '2027-01-16', 'avatar_f.jpg', 1, 0, NULL, NULL, 'Initial', '2026-10-17'),
    (16008, 'FIN-P04', 'Hector', 'Dela Cruz', 'Santos', '2026-07-17', '1992-03-21', 'Lucena City, Quezon', 'Male', 'Single', 506, 'Treasury Supervisor I', 5, 4, 102, 16001, 'Probationary', 'Full-time', '2026-07-17', '2027-01-16', 'avatar_m.jpg', 1, 0, NULL, NULL, 'Initial', '2026-10-17'),
    (16009, 'FIN-P05', 'Michelle', 'Ocampo', 'Mercado', '2026-07-17', '1997-09-16', 'Sariaya, Quezon', 'Female', 'Single', 516, 'Treasury Staff I', 5, 5, 102, 16007, 'Probationary', 'Full-time', '2026-07-17', '2027-01-16', 'avatar_f.jpg', 1, 0, NULL, NULL, 'Initial', '2026-10-17'),

    -- General Services: CEO -> VP -> Manager -> Supervisor -> Staff
    (16010, 'GS-T01', 'Arturo', 'Del Rosario', 'Mendoza', '2017-05-08', '1979-09-13', 'Lucena City, Quezon', 'Male', 'Married', 600, 'VP for General Services', 6, 1, 102, 15010, 'Regular', 'Full-time', NULL, NULL, 'avatar_m.jpg', 1, 0, NULL, NULL, NULL, NULL),
    (16011, 'GS-T02', 'Cecilia', 'Gonzales', 'Lim', '2019-03-18', '1984-12-03', 'Tayabas City, Quezon', 'Female', 'Married', 601, 'GS Manager I', 6, 3, 102, 16010, 'Regular', 'Full-time', NULL, NULL, 'avatar_f.jpg', 1, 0, NULL, NULL, NULL, NULL),
    (16012, 'GS-T03', 'Benjamin', 'Reyes', 'Ocampo', '2020-10-05', '1988-04-26', 'Sariaya, Quezon', 'Male', 'Married', 605, 'GS Supervisor I', 6, 4, 102, 16011, 'Regular', 'Full-time', NULL, NULL, 'avatar_m.jpg', 1, 0, NULL, NULL, NULL, NULL),
    (16013, 'GS-P01', 'Maricel', 'Aquino', 'Torres', '2026-07-17', '1987-01-19', 'Lucena City, Quezon', 'Female', 'Married', 600, 'VP for General Services', 6, 1, 102, 15010, 'Probationary', 'Full-time', '2026-07-17', '2027-01-16', 'avatar_f.jpg', 1, 0, NULL, NULL, 'Initial', '2026-10-17'),
    (16014, 'GS-P02', 'Dennis', 'Bautista', 'Rivera', '2026-07-17', '1990-07-11', 'Tayabas City, Quezon', 'Male', 'Single', 601, 'GS Manager I', 6, 3, 102, 16010, 'Probationary', 'Full-time', '2026-07-17', '2027-01-16', 'avatar_m.jpg', 1, 0, NULL, NULL, 'Initial', '2026-10-17'),
    (16015, 'GS-P03', 'Joyce', 'Mendoza', 'Garcia', '2026-07-17', '1995-05-24', 'Pagbilao, Quezon', 'Female', 'Single', 605, 'GS Supervisor I', 6, 4, 102, 16011, 'Probationary', 'Full-time', '2026-07-17', '2027-01-16', 'avatar_f.jpg', 1, 0, NULL, NULL, 'Initial', '2026-10-17'),
    (16016, 'GS-P04', 'Noel', 'Fernandez', 'Cruz', '2026-07-17', '1999-10-02', 'Candelaria, Quezon', 'Male', 'Single', 609, 'Driver I', 6, 5, 102, 16012, 'Probationary', 'Full-time', '2026-07-17', '2027-01-16', 'avatar_m.jpg', 1, 0, NULL, NULL, 'Initial', '2026-10-17'),

    -- Operations VP is also the configured immediate head of IT Manager I.
    (16020, 'OPS-T01', 'Leandro', 'Cabrera', 'Santos', '2016-01-11', '1978-02-16', 'Lucena City, Quezon', 'Male', 'Married', 1000, 'VP for Operations', 11, 1, 102, 15010, 'Regular', 'Full-time', NULL, NULL, 'avatar_m.jpg', 1, 0, NULL, NULL, NULL, NULL),
    -- Information Technology: VP Operations -> IT Manager -> Supervisor -> Staff
    (16021, 'IT-T01', 'Patricia', 'Lopez', 'Navarro', '2018-08-20', '1983-06-07', 'Tayabas City, Quezon', 'Female', 'Married', 800, 'IT Manager I', 8, 3, 102, 16020, 'Regular', 'Full-time', NULL, NULL, 'avatar_f.jpg', 1, 0, NULL, NULL, NULL, NULL),
    (16022, 'IT-T02', 'Rodrigo', 'Pascual', 'Dela Cruz', '2020-11-09', '1989-12-12', 'Sariaya, Quezon', 'Male', 'Married', 804, 'IT Supervisor I', 8, 4, 102, 16021, 'Regular', 'Full-time', NULL, NULL, 'avatar_m.jpg', 1, 0, NULL, NULL, NULL, NULL),
    (16023, 'IT-P01', 'Sheila', 'Valdez', 'Ramos', '2026-07-17', '1990-03-29', 'Lucena City, Quezon', 'Female', 'Single', 800, 'IT Manager I', 8, 3, 102, 16020, 'Probationary', 'Full-time', '2026-07-17', '2027-01-16', 'avatar_f.jpg', 1, 0, NULL, NULL, 'Initial', '2026-10-17'),
    (16024, 'IT-P02', 'Mark', 'Villanueva', 'Flores', '2026-07-17', '1994-08-15', 'Pagbilao, Quezon', 'Male', 'Single', 804, 'IT Supervisor I', 8, 4, 102, 16021, 'Probationary', 'Full-time', '2026-07-17', '2027-01-16', 'avatar_m.jpg', 1, 0, NULL, NULL, 'Initial', '2026-10-17'),
    (16025, 'IT-P03', 'Katrina', 'Manalo', 'Reyes', '2026-07-17', '1998-01-23', 'Candelaria, Quezon', 'Female', 'Single', 810, 'Technical Support Staff I', 8, 5, 102, 16022, 'Probationary', 'Full-time', '2026-07-17', '2027-01-16', 'avatar_f.jpg', 1, 0, NULL, NULL, 'Initial', '2026-10-17'),

    -- Marketing: CEO -> Manager -> Supervisor -> Staff
    (16030, 'MKT-T01', 'Rafael', 'Santiago', 'Torres', '2019-06-03', '1984-05-30', 'Lucena City, Quezon', 'Male', 'Married', 900, 'Marketing Manager I', 9, 3, 102, 15010, 'Regular', 'Full-time', NULL, NULL, 'avatar_m.jpg', 1, 0, NULL, NULL, NULL, NULL),
    (16031, 'MKT-T02', 'Angela', 'Dizon', 'Mercado', '2021-02-15', '1990-09-04', 'Tayabas City, Quezon', 'Female', 'Married', 902, 'Marketing Supervisor I', 9, 4, 102, 16030, 'Regular', 'Full-time', NULL, NULL, 'avatar_f.jpg', 1, 0, NULL, NULL, NULL, NULL),
    (16032, 'MKT-P01', 'Cristina', 'Ocampo', 'Garcia', '2026-07-17', '1991-11-14', 'Lucena City, Quezon', 'Female', 'Single', 900, 'Marketing Manager I', 9, 3, 102, 15010, 'Probationary', 'Full-time', '2026-07-17', '2027-01-16', 'avatar_f.jpg', 1, 0, NULL, NULL, 'Initial', '2026-10-17'),
    (16033, 'MKT-P02', 'Julio', 'Salazar', 'Reyes', '2026-07-17', '1994-02-08', 'Pagbilao, Quezon', 'Male', 'Single', 902, 'Marketing Supervisor I', 9, 4, 102, 16030, 'Probationary', 'Full-time', '2026-07-17', '2027-01-16', 'avatar_m.jpg', 1, 0, NULL, NULL, 'Initial', '2026-10-17'),
    (16034, 'MKT-P03', 'Erika', 'Domingo', 'Navarro', '2026-07-17', '1999-07-20', 'Sariaya, Quezon', 'Female', 'Single', 904, 'Marketing Staff I', 9, 5, 102, 16031, 'Probationary', 'Full-time', '2026-07-17', '2027-01-16', 'avatar_f.jpg', 1, 0, NULL, NULL, 'Initial', '2026-10-17'),

    -- Operations: CEO -> VP -> Regional Manager -> Area Coordinator -> Focal Person -> Staff
    (16040, 'OPS-T02', 'Wilfredo', 'Mendoza', 'Cruz', '2019-09-02', '1982-07-17', 'Lucena City, Quezon', 'Male', 'Married', 1001, 'Regional Manager I', 11, 3, 102, 16020, 'Regular', 'Full-time', NULL, NULL, 'avatar_m.jpg', 1, 0, NULL, NULL, NULL, NULL),
    (16041, 'OPS-T03', 'Rosalinda', 'Castillo', 'Santos', '2021-04-12', '1987-10-21', 'Tayabas City, Quezon', 'Female', 'Married', 1004, 'Area Coordinator I', 11, 4, 102, 16040, 'Regular', 'Full-time', NULL, NULL, 'avatar_f.jpg', 1, 0, NULL, NULL, NULL, NULL),
    (16042, 'OPS-T04', 'Edwin', 'Garcia', 'Lopez', '2022-08-01', '1990-01-31', 'Sariaya, Quezon', 'Male', 'Married', 1007, 'Focal Person I', 11, 4, 102, 16041, 'Regular', 'Full-time', NULL, NULL, 'avatar_m.jpg', 1, 0, NULL, NULL, NULL, NULL),
    (16043, 'OPS-P01', 'Monica', 'Rivera', 'Aquino', '2026-07-17', '1986-06-05', 'Lucena City, Quezon', 'Female', 'Married', 1000, 'VP for Operations', 11, 1, 102, 15010, 'Probationary', 'Full-time', '2026-07-17', '2027-01-16', 'avatar_f.jpg', 1, 0, NULL, NULL, 'Initial', '2026-10-17'),
    (16044, 'OPS-P02', 'Alvin', 'Torres', 'Navarro', '2026-07-17', '1992-12-18', 'Pagbilao, Quezon', 'Male', 'Single', 1001, 'Regional Manager I', 11, 3, 102, 16020, 'Probationary', 'Full-time', '2026-07-17', '2027-01-16', 'avatar_m.jpg', 1, 0, NULL, NULL, 'Initial', '2026-10-17'),
    (16045, 'OPS-P03', 'Daisy', 'Fernandez', 'Cabrera', '2026-07-17', '1995-03-12', 'Tayabas City, Quezon', 'Female', 'Single', 1004, 'Area Coordinator I', 11, 4, 102, 16040, 'Probationary', 'Full-time', '2026-07-17', '2027-01-16', 'avatar_f.jpg', 1, 0, NULL, NULL, 'Initial', '2026-10-17'),
    (16046, 'OPS-P04', 'Nestor', 'Reyes', 'Villanueva', '2026-07-17', '1997-08-29', 'Candelaria, Quezon', 'Male', 'Single', 1007, 'Focal Person I', 11, 4, 102, 16041, 'Probationary', 'Full-time', '2026-07-17', '2027-01-16', 'avatar_m.jpg', 1, 0, NULL, NULL, 'Initial', '2026-10-17'),
    (16047, 'OPS-P05', 'Beatriz', 'Lopez', 'Dela Cruz', '2026-07-17', '1999-04-03', 'Sariaya, Quezon', 'Female', 'Single', 1012, 'Branch Staff I', 11, 5, 102, 16042, 'Probationary', 'Full-time', '2026-07-17', '2027-01-16', 'avatar_f.jpg', 1, 0, NULL, NULL, 'Initial', '2026-10-17');

-- Seed Employee Portal accounts for both the probationary employees and their
-- regular immediate heads. The CEO account is provided by the CEO profile seed.
REPLACE INTO users
    (employee_id, username, email, password_hash, full_name, role, branch_id,
     is_active, first_login_completed, created_at)
VALUES
    (16001, 'FIN-T01', 'fin.t01@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Gabriel Santos Mercado', 'Employee', 102, 1, 0, NOW()),
    (16002, 'FIN-T02', 'fin.t02@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Teresa Cruz Villanueva', 'Employee', 102, 1, 0, NOW()),
    (16003, 'FIN-T03', 'fin.t03@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ramon Reyes Castillo', 'Employee', 102, 1, 0, NOW()),
    (16007, 'FIN-T04', 'fin.t04@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Rosario Aquino Perez', 'Employee', 102, 1, 0, NOW()),
    (16004, 'FIN-P01', 'fin.p01@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Lourdes Dizon Navarro', 'Employee', 102, 1, 0, NOW()),
    (16005, 'FIN-P02', 'fin.p02@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Miguel Garcia Ramos', 'Employee', 102, 1, 0, NOW()),
    (16006, 'FIN-P03', 'fin.p03@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Andrea Flores Santos', 'Employee', 102, 1, 0, NOW()),
    (16008, 'FIN-P04', 'fin.p04@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Hector Santos Dela Cruz', 'Employee', 102, 1, 0, NOW()),
    (16009, 'FIN-P05', 'fin.p05@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Michelle Mercado Ocampo', 'Employee', 102, 1, 0, NOW()),
    (16010, 'GS-T01', 'gs.t01@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Arturo Mendoza Del Rosario', 'Employee', 102, 1, 0, NOW()),
    (16011, 'GS-T02', 'gs.t02@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Cecilia Lim Gonzales', 'Employee', 102, 1, 0, NOW()),
    (16012, 'GS-T03', 'gs.t03@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Benjamin Ocampo Reyes', 'Employee', 102, 1, 0, NOW()),
    (16013, 'GS-P01', 'gs.p01@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Maricel Torres Aquino', 'Employee', 102, 1, 0, NOW()),
    (16014, 'GS-P02', 'gs.p02@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dennis Rivera Bautista', 'Employee', 102, 1, 0, NOW()),
    (16015, 'GS-P03', 'gs.p03@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Joyce Garcia Mendoza', 'Employee', 102, 1, 0, NOW()),
    (16016, 'GS-P04', 'gs.p04@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Noel Cruz Fernandez', 'Employee', 102, 1, 0, NOW()),
    (16020, 'OPS-T01', 'ops.t01@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Leandro Santos Cabrera', 'Employee', 102, 1, 0, NOW()),
    (16021, 'IT-T01', 'it.t01@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Patricia Navarro Lopez', 'Employee', 102, 1, 0, NOW()),
    (16022, 'IT-T02', 'it.t02@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Rodrigo Cruz Pascual', 'Employee', 102, 1, 0, NOW()),
    (16023, 'IT-P01', 'it.p01@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sheila Ramos Valdez', 'Employee', 102, 1, 0, NOW()),
    (16024, 'IT-P02', 'it.p02@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mark Flores Villanueva', 'Employee', 102, 1, 0, NOW()),
    (16025, 'IT-P03', 'it.p03@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Katrina Reyes Manalo', 'Employee', 102, 1, 0, NOW()),
    (16030, 'MKT-T01', 'mkt.t01@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Rafael Torres Santiago', 'Employee', 102, 1, 0, NOW()),
    (16031, 'MKT-T02', 'mkt.t02@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Angela Mercado Dizon', 'Employee', 102, 1, 0, NOW()),
    (16032, 'MKT-P01', 'mkt.p01@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Cristina Garcia Ocampo', 'Employee', 102, 1, 0, NOW()),
    (16033, 'MKT-P02', 'mkt.p02@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Julio Reyes Salazar', 'Employee', 102, 1, 0, NOW()),
    (16034, 'MKT-P03', 'mkt.p03@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Erika Navarro Domingo', 'Employee', 102, 1, 0, NOW()),
    (16040, 'OPS-T02', 'ops.t02@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Wilfredo Cruz Mendoza', 'Employee', 102, 1, 0, NOW()),
    (16041, 'OPS-T03', 'ops.t03@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Rosalinda Santos Castillo', 'Employee', 102, 1, 0, NOW()),
    (16042, 'OPS-T04', 'ops.t04@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Edwin Lopez Garcia', 'Employee', 102, 1, 0, NOW()),
    (16043, 'OPS-P01', 'ops.p01@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Monica Aquino Rivera', 'Employee', 102, 1, 0, NOW()),
    (16044, 'OPS-P02', 'ops.p02@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Alvin Navarro Torres', 'Employee', 102, 1, 0, NOW()),
    (16045, 'OPS-P03', 'ops.p03@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Daisy Cabrera Fernandez', 'Employee', 102, 1, 0, NOW()),
    (16046, 'OPS-P04', 'ops.p04@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Nestor Villanueva Reyes', 'Employee', 102, 1, 0, NOW()),
    (16047, 'OPS-P05', 'ops.p05@employee.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Beatriz Dela Cruz Lopez', 'Employee', 102, 1, 0, NOW());

-- Verify seeded probationary employees and their immediate heads.
SELECT e.employee_code,
       d.department_name,
       e.job_title,
       h.employee_code AS immediate_head_code,
       h.job_title AS immediate_head_position
FROM employees e
JOIN departments d ON d.department_id = e.department_id
LEFT JOIN employees h ON h.employee_id = e.reports_to
WHERE e.employee_id IN (16004, 16005, 16006, 16008, 16009, 16013, 16014, 16015, 16016,
                        16023, 16024, 16025, 16032, 16033, 16034,
                        16043, 16044, 16045, 16046, 16047)
ORDER BY d.department_name, e.rank_category_id, e.employee_code;
