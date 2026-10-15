-- ============================================================================
-- HRIS user accounts and employee data seed file
-- Standalone admin account
-- Essential file for creating the initial admin account and HRD team employees
-- ============================================================================

USE raquel_hris;
SET FOREIGN_KEY_CHECKS = 0;

-- ====================================
-- 1. System Administrator (Standalone)
-- ====================================
REPLACE INTO users (user_id, employee_id, username, email, full_name, password_hash, role, branch_id, is_active, created_at) VALUES
(1, NULL, 'admin', 'admin@company.com', 'System Admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 102, 1, NOW());

-- ====================================
-- 2. EMPLOYEES (HRD Team)
-- ====================================
REPLACE INTO employees (employee_id, employee_code, first_name, last_name, middle_name, hire_date, date_of_birth, place_of_birth, gender, civil_status, job_title_id, job_title, department_id, rank_category_id, branch_id, employment_status, employment_type) VALUES
-- HR Manager
(101, 'HRD-001', 'Elena', 'Delgado', 'Santos', '2020-01-15', '1990-05-15', 'Tayabas, Quezon', 'Female', 'Married', 700, 'HR Manager I', 7, 3, 102, 'Regular', 'Full-time'),
-- HR Supervisor
(301, 'HRD-002', 'Patricia', 'Gomez', 'Luna', '2017-02-14', '1989-11-08', 'Pasig, Metro Manila', 'Female', 'Widowed', 705, 'HR Supervisor I', 7, 4, 102, 'Regular', 'Full-time'),
-- HR Staff
(302, 'HRD-003', 'Miguel', 'Torres', 'Cruz', '2022-01-05', '2000-04-17', 'Caloocan, Metro Manila', 'Male', 'Single', 711, 'HR Staff I', 7, 5, 102, 'Regular', 'Full-time');

-- =====================================================
-- 3. EMPLOYEE CONTACTS (Required for Portal Validation)
-- =====================================================
REPLACE INTO employee_contacts (employee_id, personal_email, mobile_number, telephone_number) VALUES
(101, 'elena.delgado@example.com', '09161234567', '888-0101'),
(301, 'patricia.gomez@example.com', '09201234567', '888-3001'),
(302, 'miguel.torres@example.com', '09211234567', '888-3002');

-- ============================================
-- 4. EMPLOYEE DETAILS (Physical & Citizenship)
-- ============================================
REPLACE INTO employee_details (employee_id, height_m, weight_kg, blood_type, citizenship) VALUES
(101, 1.68, 62.0, 'A+', 'Filipino'),
(301, 1.58, 52.0, 'AB+', 'Filipino'),
(302, 1.72, 70.0, 'O-', 'Filipino');

-- ====================
-- 5. FAMILY BACKGROUND
-- ====================
REPLACE INTO employee_family (employee_id, member_type, surname, first_name, middle_name, occupation) VALUES
(101, 'Father', 'Delgado', 'Santiago', 'Rodriguez', 'Retired Government Officer'),
(101, 'Mother', 'Delgado', 'Carmen', 'Santos', 'Retired Teacher'),
(101, 'Spouse', 'Delgado', 'Marco', 'Antonio', 'Civil Engineer'),
(301, 'Father', 'Gomez', 'Carlos', 'Reyes', 'Businessman'),
(301, 'Mother', 'Gomez', 'Rosario', 'Luna', 'Educator'),
(301, 'Spouse', 'Gomez', 'Fernando', 'Valdez', 'Architect'),
(302, 'Father', 'Torres', 'Manuel', 'Pascual', 'Government Employee'),
(302, 'Mother', 'Torres', 'Rosa', 'Cruz', 'Nurse');

REPLACE INTO employee_children (employee_id, surname, first_name, middle_name, date_of_birth) VALUES
(101, 'Delgado', 'Sofia', 'Santos', '2018-03-04'),
(301, 'Gomez', 'Patricia', 'Valdez', '2015-11-28'),
(302, 'Torres', 'Miguel Jr.', 'Santos', '2020-02-14');

REPLACE INTO employee_siblings (employee_id, surname, first_name, middle_name, date_of_birth) VALUES
(101, 'Delgado', 'Miguel', 'Santos', '1992-08-10'),
(301, 'Gomez', 'Gabriela', 'Luna', '1988-12-03'),
(302, 'Torres', 'Emmanuel', 'Cruz', '1995-06-18');

-- =========================
-- 6. EDUCATIONAL BACKGROUND
-- =========================
REPLACE INTO employee_education (employee_id, education_level, school_name, degree_course, year_graduated) VALUES
(101, 'College', 'University of the Philippines', 'BS Psychology', '2012'),
(101, 'Graduate Studies', 'Ateneo de Manila University', 'MA Human Resource Management', '2016'),
(301, 'College', 'Ateneo de Manila University', 'BS Management', '2012'),
(302, 'College', 'University of Santo Tomas', 'BS Human Resource Management', '2019');

-- ==================
-- 7. WORK EXPERIENCE
-- ==================
REPLACE INTO employee_work_experience (employee_id, date_from, date_to, job_title, company_name, monthly_salary) VALUES
(101, '2012-07-01', '2020-01-14', 'HR Specialist', 'Business Process Outsourcing Inc.', 35000.00),
(301, '2012-09-01', '2017-03-31', 'HR Coordinator', 'Global Staffing Solutions', 30000.00),
(302, '2019-02-01', '2022-12-15', 'HR Assistant', 'Talent Quest Recruitment', 22000.00);

-- ===================================
-- 8. VOLUNTARY WORK / CIVIC INVOLVEMENT
-- ===================================
REPLACE INTO employee_voluntary_work
    (voluntary_id, employee_id, date_from, date_to, organization_name,
     organization_address, no_of_hours, position_nature)
VALUES
(30101, 101, '2023-06-10', '2023-12-16', 'Quezon Community Skills Development Foundation',
 'Tayabas City, Quezon', 48.00, 'Volunteer HR and Career Development Adviser'),
(30102, 101, '2024-04-06', '2024-04-07', 'Philippine Red Cross - Quezon Chapter',
 'Lucena City, Quezon', 16.00, 'Community Outreach and Registration Volunteer');

-- =========================
-- 9. TRAININGS & SEMINARS
-- =========================
REPLACE INTO employee_trainings (employee_id, training_title, conducted_by, no_of_hours) VALUES
(101, 'Advanced HR Management Seminar', 'Philippine HR Association', 40.0),
(101, 'Employee Relations & Conflict Resolution', 'Training & Development Center', 24.0),
(301, 'HR Compliance and Labor Law Update', 'Department of Labor', 24.0),
(301, 'Performance Management Workshop', 'Human Resources Institute', 16.0),
(302, 'Recruitment Best Practices', 'HR Development Forum', 20.0),
(302, 'Employee Relations Fundamentals', 'Training and Development Center', 18.0);

-- =====================================
-- 10. ELIGIBILITY & PROFESSIONAL LICENSES
-- =====================================
REPLACE INTO employee_eligibility (eligibility_id, employee_id, license_title, date_from, date_to, license_number, date_of_exam, place_of_exam) VALUES
(30101, 101, 'Career Service Professional Eligibility', '2012-06-15', NULL, 'CSP-12-101845', '2012-05-27', 'Quezon City'),
(30301, 301, 'Career Service Professional Eligibility', '2011-11-18', NULL, 'CSP-11-301672', '2011-10-16', 'Pasig City'),
(30302, 302, 'Certified Human Resource Associate', '2021-08-20', '2027-08-19', 'CHRA-2021-30219', '2021-07-31', 'Manila');

-- =====================================
-- 11. SKILLS, RECOGNITIONS & MEMBERSHIPS
-- =====================================
REPLACE INTO employee_skills (skill_id, employee_id, skill_name) VALUES
(30101, 101, 'Strategic Human Resource Management'),
(30102, 101, 'Labor Relations and Conflict Resolution'),
(30301, 301, 'Performance Management'),
(30302, 301, 'Employee Coaching and Development'),
(30303, 302, 'Recruitment and Applicant Screening'),
(30304, 302, 'HR Records Administration');

REPLACE INTO employee_recognitions (recognition_id, employee_id, recognition_title) VALUES
(30101, 101, 'Outstanding HR Leadership Award - 2025'),
(30301, 301, 'Excellence in Employee Relations - 2025'),
(30302, 302, 'HR Service Excellence Award - 2025');

REPLACE INTO employee_memberships (membership_id, employee_id, organization_name) VALUES
(30101, 101, 'People Management Association of the Philippines'),
(30301, 301, 'Philippine Society for Talent Development'),
(30302, 302, 'Human Resource Educators Association of the Philippines');

-- ==================
-- 12. DISCLOSURES
-- ==================
REPLACE INTO employee_disclosures (employee_id, is_related_to_company, has_admin_offense, has_criminal_charge) VALUES
(101, 0, 0, 0), (301, 0, 0, 0), (302, 0, 0, 0);

-- ==================
-- 13. GOVERNMENT IDS
-- ==================
REPLACE INTO employee_government_ids (employee_id, sss_number, philhealth_number, pagibig_number, tin_number) VALUES
(101, '33-0234567-0', '12-787654321-5', '1210-6666-7788', '023-456-789-001'),
(301, '33-4234567-4', '12-687654321-3', '1210-2266-7788', '423-456-789-002'),
(302, '33-5234567-5', '12-587654321-4', '1210-1166-7788', '523-456-789-003');

-- ==================================
-- 14. ADDRESSES & EMERGENCY CONTACTS
-- ==================================
REPLACE INTO employee_addresses (employee_id, address_type, region, barangay, city, province) VALUES
(101, 'Residential', 'Region IV-A (CALABARZON)', 'San Isidro', 'Tayabas City', 'Quezon'),
(101, 'Permanent', 'Region IV-A (CALABARZON)', 'San Isidro', 'Tayabas City', 'Quezon'),
(301, 'Residential', 'National Capital Region (NCR)', 'Quezon City', 'Quezon City', 'Metro Manila'),
(301, 'Permanent', 'Region III (Central Luzon)', 'Nueva Ecija', 'Cabanatuan City', 'Nueva Ecija'),
(302, 'Residential', 'National Capital Region (NCR)', 'Taguig', 'Taguig City', 'Metro Manila'),
(302, 'Permanent', 'Region IV-A (CALABARZON)', 'Cavite', 'Dasmariñas', 'Cavite');

REPLACE INTO employee_emergency_contacts (employee_id, contact_name, relationship, contact_number) VALUES
(101, 'Marco Antonio Delgado', 'Spouse', '09161111111'),
(301, 'Carlos Gomez', 'Father', '09175556666'),
(302, 'Rosa Torres', 'Mother', '09185557777');

-- =========================================================
-- 15. SALN INFORMATION (Real/Personal Property/Liabilities)
-- =========================================================
REPLACE INTO employee_real_properties (employee_id, description, kind, acquisition_cost) VALUES
(101, 'Residential House', 'Building', 2500000.00),
(301, 'Residential House', 'Building', 2800000.00),
(302, 'Vacant Lot', 'Land', 950000.00);

REPLACE INTO employee_personal_properties (employee_id, description, acquisition_cost) VALUES
(101, 'Toyota Fortuner 2019', 1350000.00),
(301, 'Hyundai Elantra 2021', 1100000.00),
(302, 'Yamaha Motorcycle', 120000.00);

REPLACE INTO employee_liabilities (employee_id, nature_of_liability, creditor_name, outstanding_balance) VALUES
(101, 'Housing Loan', 'BDO Unibank', 1800000.00),
(301, 'Housing Loan', 'Banco de Oro', 1800000.00),
(302, 'Personal Loan', 'Philippine National Bank', 280000.00);

-- ========================
-- 16. CHARACTER REFERENCES
-- ========================
REPLACE INTO employee_references (employee_id, reference_name, reference_address, reference_telephone) VALUES
(101, 'Dr. Reynaldo Cruz', 'Pasig City', '02-9876-5432'),
(301, 'Atty. Rosa Fernandez', 'Pasig City', '02-5432-1098'),
(302, 'Prof. Antonio Lopez', 'Caloocan City', '02-3456-7891');

-- ========================
-- 17. HRIS USER ACCOUNTS
-- ========================
REPLACE INTO users
    (user_id, employee_id, username, email, full_name, password_hash, role,
     branch_id, is_active, account_hold, first_login_completed, created_at)
VALUES
(2, 101, 'elena.delgado', 'elena@company.com', 'Elena Delgado','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'HR Manager', 102, 1, 0, 0, NOW()),
(3, 301, 'patricia.gomez', 'patricia@company.com', 'Patricia Gomez','$2y$10$ShFYgbhwbnwUnO9BE0/Ny.Tdohwd2rFgJQ4XtCZJh.tkJylDBLw9e',
 'HR Supervisor', 102, 1, 0, 0, NOW()),
(4, 302, 'miguel.torres', 'miguel@company.com', 'Miguel Torres','$2y$10$.ZTuiD7q3wdnDCHbEiYQQOyqFpn4IKK4d6G6SLbuLMFTVmKBl8GLK',
 'HR Staff', 102, 1, 0, 0, NOW());

SET FOREIGN_KEY_CHECKS = 1;