-- ============================================================================
-- Complete probationary employee profiles: Human Resources + Acquired Properties
-- Expected profile completion: 100% (12 of 12 personal-detail sections)
-- Prerequisite: database/1st_schema_tables.sql and database/2nd_seed_organization.sql
-- ============================================================================

USE raquel_hris;
SET FOREIGN_KEY_CHECKS = 0;

-- Employee hierarchy:
-- HRD-P01 Manager -> HRD-P02 Supervisor -> HRD-P03 Staff
-- AP-P01  Manager -> AP-P02  Supervisor -> AP-P03  Staff
REPLACE INTO employees
    (employee_id, employee_code, first_name, last_name, middle_name, hire_date,
     date_of_birth, place_of_birth, gender, civil_status, job_title_id, job_title,
     department_id, rank_category_id, branch_id, reports_to, employment_status,
     employment_type, contract_start_date, contract_end_date, profile_picture, is_active)
VALUES
(15101, 'HRD-P01', 'Marianne', 'Aguilar', 'Santos', '2026-07-06', '1988-04-14', 'Lucena City, Quezon', 'Female', 'Married', 700, 'HR Manager I', 7, 3, 102, NULL,  'Probationary', 'Full-time', '2026-07-06', '2027-01-05', 'avatar_f.jpg', 1),
(15102, 'HRD-P02', 'Dominic', 'Navarro', 'Cruz', '2026-08-03', '1992-09-23', 'Tayabas City, Quezon', 'Male', 'Single', 705, 'HR Supervisor I', 7, 4, 102, 15101, 'Probationary', 'Full-time', '2026-08-03', '2027-02-02', 'avatar_m.jpg', 1),
(15103, 'HRD-P03', 'Alyssa', 'Fernandez', 'Reyes', '2026-09-01', '1998-01-30', 'Sariaya, Quezon', 'Female', 'Single', 710, 'HR Staff I', 7, 5, 102, 15102, 'Probationary', 'Full-time', '2026-09-01', '2027-02-28', 'avatar_f.jpg', 1),
(15104, 'AP-P01', 'Victor', 'Salcedo', 'Mendoza', '2026-07-13', '1987-12-08', 'Lucena City, Quezon', 'Male', 'Married', 101, 'AP Manager I', 1, 3, 102, NULL,  'Probationary', 'Full-time', '2026-07-13', '2027-01-12', 'avatar_m.jpg', 1),
(15105, 'AP-P02', 'Clarisse', 'Domingo', 'Lopez', '2026-08-10', '1993-05-17', 'Pagbilao, Quezon', 'Female', 'Married', 105, 'AP Supervisor I', 1, 4, 102, 15104, 'Probationary', 'Full-time', '2026-08-10', '2027-02-09', 'avatar_f.jpg', 1),
(15106, 'AP-P03', 'Paolo', 'Gonzales', 'Rivera', '2026-09-07', '1999-10-26', 'Candelaria, Quezon', 'Male', 'Single', 108, 'AP Staff I', 1, 5, 102, 15105, 'Probationary', 'Full-time', '2026-09-07', '2027-03-06', 'avatar_m.jpg', 1);

-- Contact and physical details
REPLACE INTO employee_contacts (employee_id, personal_email, mobile_number, telephone_number) VALUES
(15101, 'marianne.aguilar@example.com', '09171510101', '042-710-5101'),
(15102, 'dominic.navarro@example.com', '09171510102', '042-710-5102'),
(15103, 'alyssa.fernandez@example.com', '09171510103', '042-710-5103'),
(15104, 'victor.salcedo@example.com', '09171510104', '042-710-5104'),
(15105, 'clarisse.domingo@example.com', '09171510105', '042-710-5105'),
(15106, 'paolo.gonzales@example.com', '09171510106', '042-710-5106');

REPLACE INTO employee_details (employee_id, height_m, weight_kg, blood_type, citizenship) VALUES
(15101, 1.64, 60.00, 'A+', 'Filipino'),
(15102, 1.75, 72.00, 'O+', 'Filipino'),
(15103, 1.59, 54.00, 'B+', 'Filipino'),
(15104, 1.77, 78.00, 'AB+', 'Filipino'),
(15105, 1.62, 57.00, 'A-', 'Filipino'),
(15106, 1.73, 70.00, 'O-', 'Filipino');

-- Residential and permanent addresses
REPLACE INTO employee_addresses
    (employee_id, address_type, region, house_no, street, subdivision, barangay, city, province, zip_code)
VALUES
(15101, 'Residential', 'Region IV-A (CALABARZON)', '21', 'Rizal Street', 'Green Meadows', 'Ibabang Dupay', 'Lucena City', 'Quezon', '4301'),
(15101, 'Permanent',   'Region IV-A (CALABARZON)', '21', 'Rizal Street', 'Green Meadows', 'Ibabang Dupay', 'Lucena City', 'Quezon', '4301'),
(15102, 'Residential', 'Region IV-A (CALABARZON)', '14', 'Bonifacio Street', NULL, 'Isabang', 'Tayabas City', 'Quezon', '4327'),
(15102, 'Permanent',   'Region IV-A (CALABARZON)', '44', 'Quezon Avenue', NULL, 'Barangay 4', 'Tayabas City', 'Quezon', '4327'),
(15103, 'Residential', 'Region IV-A (CALABARZON)', '7', 'Gomez Street', 'Sampaguita Homes', 'Poblacion 2', 'Sariaya', 'Quezon', '4322'),
(15103, 'Permanent',   'Region IV-A (CALABARZON)', '7', 'Gomez Street', 'Sampaguita Homes', 'Poblacion 2', 'Sariaya', 'Quezon', '4322'),
(15104, 'Residential', 'Region IV-A (CALABARZON)', '33', 'Mabini Street', 'Villa Esperanza', 'Ilayang Iyam', 'Lucena City', 'Quezon', '4301'),
(15104, 'Permanent',   'Region IV-A (CALABARZON)', '33', 'Mabini Street', 'Villa Esperanza', 'Ilayang Iyam', 'Lucena City', 'Quezon', '4301'),
(15105, 'Residential', 'Region IV-A (CALABARZON)', '10', 'Del Pilar Street', NULL, 'Poblacion', 'Pagbilao', 'Quezon', '4302'),
(15105, 'Permanent',   'Region IV-A (CALABARZON)', '58', 'Jacinto Street', NULL, 'Silangang Malicboy', 'Pagbilao', 'Quezon', '4302'),
(15106, 'Residential', 'Region IV-A (CALABARZON)', '5', 'Luna Street', 'Rosewood Village', 'Poblacion', 'Candelaria', 'Quezon', '4323'),
(15106, 'Permanent',   'Region IV-A (CALABARZON)', '5', 'Luna Street', 'Rosewood Village', 'Poblacion', 'Candelaria', 'Quezon', '4323');

REPLACE INTO employee_emergency_contacts
    (emergency_id, employee_id, contact_name, relationship, contact_number, is_primary)
VALUES
(151011, 15101, 'Ramon Aguilar', 'Spouse', '09181510101', 1),
(151021, 15102, 'Teresa Navarro', 'Mother', '09181510102', 1),
(151031, 15103, 'Roberto Fernandez', 'Father', '09181510103', 1),
(151041, 15104, 'Angela Salcedo', 'Spouse', '09181510104', 1),
(151051, 15105, 'Marco Domingo', 'Spouse', '09181510105', 1),
(151061, 15106, 'Lourdes Gonzales', 'Mother', '09181510106', 1);

-- Family background
REPLACE INTO employee_family
    (employee_id, member_type, surname, first_name, middle_name, occupation)
VALUES
(15101, 'Father', 'Santos', 'Ernesto', 'Diaz', 'Retired Government Employee'),
(15101, 'Mother', 'Santos', 'Cecilia', 'Reyes', 'Retired Teacher'),
(15101, 'Spouse', 'Aguilar', 'Ramon', 'Flores', 'Accountant'),
(15102, 'Father', 'Navarro', 'Eduardo', 'Cruz', 'Business Owner'),
(15102, 'Mother', 'Navarro', 'Teresa', 'Garcia', 'Nurse'),
(15103, 'Father', 'Fernandez', 'Roberto', 'Reyes', 'Civil Engineer'),
(15103, 'Mother', 'Fernandez', 'Elena', 'Santos', 'Teacher'),
(15104, 'Father', 'Salcedo', 'Manuel', 'Mendoza', 'Retired Property Broker'),
(15104, 'Mother', 'Salcedo', 'Corazon', 'Rivera', 'Homemaker'),
(15104, 'Spouse', 'Salcedo', 'Angela', 'Torres', 'Architect'),
(15105, 'Father', 'Domingo', 'Ricardo', 'Lopez', 'Contractor'),
(15105, 'Mother', 'Domingo', 'Patricia', 'Garcia', 'Accountant'),
(15105, 'Spouse', 'Domingo', 'Marco', 'Reyes', 'Bank Officer'),
(15106, 'Father', 'Gonzales', 'Danilo', 'Rivera', 'Sales Manager'),
(15106, 'Mother', 'Gonzales', 'Lourdes', 'Castillo', 'Homemaker');

-- Education
REPLACE INTO employee_education
    (education_id, employee_id, education_level, school_name, degree_course, year_graduated, honors_received)
VALUES
(151011, 15101, 'College', 'University of Santo Tomas', 'BS Psychology', '2009', 'Cum Laude'),
(151021, 15102, 'College', 'Polytechnic University of the Philippines', 'BS Human Resource Management', '2013', NULL),
(151031, 15103, 'College', 'Southern Luzon State University', 'BS Business Administration major in Human Resource Management', '2019', 'Dean''s Lister'),
(151041, 15104, 'College', 'De La Salle University', 'BS Real Estate Management', '2008', NULL),
(151051, 15105, 'College', 'University of the Philippines Los Banos', 'BS Agribusiness Management', '2014', NULL),
(151061, 15106, 'College', 'Manuel S. Enverga University Foundation', 'BS Real Estate Management', '2020', 'Dean''s Lister');

-- Prior work experience
REPLACE INTO employee_work_experience
    (work_id, employee_id, date_from, date_to, job_title, company_name, monthly_salary, appointment_status, reason_for_leaving)
VALUES
(151011, 15101, '2018-01-08', '2026-06-30', 'HR Business Partner', 'Southern Luzon Retail Group', 52000.00, 'Regular', 'Accepted management opportunity'),
(151021, 15102, '2016-04-04', '2026-07-31', 'Senior HR Officer', 'Quezon Workforce Solutions', 39000.00, 'Regular', 'Accepted supervisory opportunity'),
(151031, 15103, '2020-01-06', '2026-08-28', 'HR Assistant', 'Pacific Talent Services', 25000.00, 'Regular', 'Career advancement'),
(151041, 15104, '2015-02-02', '2026-07-10', 'Property Portfolio Manager', 'Prime Asset Holdings', 58000.00, 'Regular', 'Accepted management opportunity'),
(151051, 15105, '2017-06-05', '2026-08-07', 'Property Appraisal Officer', 'Southpoint Realty Corporation', 41000.00, 'Regular', 'Accepted supervisory opportunity'),
(151061, 15106, '2021-03-01', '2026-09-04', 'Property Documentation Associate', 'Quezon Realty Services', 27000.00, 'Regular', 'Career advancement');

-- Training
REPLACE INTO employee_trainings
    (training_id, employee_id, date_from, date_to, training_title, training_type, no_of_hours, conducted_by)
VALUES
(151011, 15101, '2025-11-10', '2025-11-12', 'Strategic Human Resource Leadership', 'Managerial', 24.00, 'People Management Association of the Philippines'),
(151021, 15102, '2025-09-15', '2025-09-16', 'Supervisory Coaching and Performance Management', 'Supervisory', 16.00, 'Philippine HR Institute'),
(151031, 15103, '2026-03-09', '2026-03-10', 'HR Records and Recruitment Fundamentals', 'Technical', 16.00, 'HR Development Center'),
(151041, 15104, '2025-10-20', '2025-10-22', 'Strategic Property Portfolio Management', 'Managerial', 24.00, 'Philippine Association of Realty Consultants'),
(151051, 15105, '2026-01-19', '2026-01-20', 'Property Appraisal and Team Supervision', 'Supervisory', 16.00, 'Real Estate Training Institute'),
(151061, 15106, '2026-04-13', '2026-04-14', 'Title Verification and Property Documentation', 'Technical', 16.00, 'Land Registration Authority');

-- Voluntary work / civic involvement
REPLACE INTO employee_voluntary_work
    (voluntary_id, employee_id, date_from, date_to, organization_name, organization_address, no_of_hours, position_nature)
VALUES
(151011, 15101, '2025-02-08', '2025-05-31', 'Quezon Career Readiness Network', 'Lucena City, Quezon', 32.00, 'Volunteer Career Coach'),
(151021, 15102, '2025-06-14', '2025-06-15', 'Tayabas Youth Employment Council', 'Tayabas City, Quezon', 16.00, 'Resume Workshop Facilitator'),
(151031, 15103, '2026-02-21', '2026-02-21', 'Sariaya Community Outreach Program', 'Sariaya, Quezon', 8.00, 'Registration Volunteer'),
(151041, 15104, '2025-03-01', '2025-06-28', 'Quezon Housing Assistance Network', 'Lucena City, Quezon', 36.00, 'Volunteer Property Adviser'),
(151051, 15105, '2025-08-16', '2025-08-17', 'Gawad Kalinga Quezon', 'Pagbilao, Quezon', 16.00, 'Community Build Coordinator'),
(151061, 15106, '2026-01-24', '2026-01-24', 'Candelaria Community Land Mapping Project', 'Candelaria, Quezon', 8.00, 'Documentation Volunteer');

-- Eligibility / licenses
REPLACE INTO employee_eligibility
    (eligibility_id, employee_id, license_title, date_from, date_to, license_number, date_of_exam, place_of_exam)
VALUES
(151011, 15101, 'Career Service Professional Eligibility', '2010-06-18', NULL, 'CSP-HRDP01-2010', '2010-05-23', 'Quezon City'),
(151021, 15102, 'Certified Human Resource Professional', '2021-08-20', '2027-08-19', 'CHRP-HRDP02-2021', '2021-07-31', 'Manila'),
(151031, 15103, 'Certified Human Resource Associate', '2020-09-18', '2027-09-17', 'CHRA-HRDP03-2020', '2020-08-29', 'Manila'),
(151041, 15104, 'Licensed Real Estate Broker', '2011-09-16', NULL, 'REB-APP01-2011', '2011-07-31', 'Manila'),
(151051, 15105, 'Licensed Real Estate Appraiser', '2016-10-14', NULL, 'REA-APP02-2016', '2016-08-28', 'Manila'),
(151061, 15106, 'Career Service Professional Eligibility', '2021-06-21', NULL, 'CSP-APP03-2021', '2021-05-23', 'Lucena City');

-- Skills, recognitions, memberships
REPLACE INTO employee_skills (skill_id, employee_id, skill_name) VALUES
(151011, 15101, 'Strategic Workforce Planning'),
(151021, 15102, 'Employee Coaching and Labor Relations'),
(151031, 15103, 'Recruitment and HR Records Management'),
(151041, 15104, 'Property Portfolio Strategy'),
(151051, 15105, 'Property Appraisal and Team Supervision'),
(151061, 15106, 'Title Verification and Property Documentation');

REPLACE INTO employee_recognitions (recognition_id, employee_id, recognition_title) VALUES
(151011, 15101, 'HR Leadership Excellence Award - 2025'),
(151021, 15102, 'Outstanding People Supervisor - 2025'),
(151031, 15103, 'HR Service Award - 2025'),
(151041, 15104, 'Property Management Excellence Award - 2025'),
(151051, 15105, 'Outstanding Property Supervisor - 2025'),
(151061, 15106, 'Property Documentation Excellence Award - 2025');

REPLACE INTO employee_memberships (membership_id, employee_id, organization_name) VALUES
(151011, 15101, 'People Management Association of the Philippines'),
(151021, 15102, 'Philippine Society for Talent Development'),
(151031, 15103, 'Human Resource Educators Association of the Philippines'),
(151041, 15104, 'Philippine Association of Real Estate Boards'),
(151051, 15105, 'Philippine Real Estate Service Practitioners'),
(151061, 15106, 'Association of Property Professionals Philippines');

-- Disclosures and government IDs
REPLACE INTO employee_disclosures
    (employee_id, is_related_to_company, has_admin_offense, has_criminal_charge,
     has_criminal_conviction, has_been_separated, is_pwd, is_solo_parent,
     has_recent_hospital, has_current_treatment)
VALUES
(15101, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(15102, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(15103, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(15104, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(15105, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(15106, 0, 0, 0, 0, 0, 0, 0, 0, 0);

REPLACE INTO employee_government_ids (employee_id, sss_number, philhealth_number, pagibig_number, tin_number) VALUES
(15101, '15-0015101-1', '15-000015101-1', '1510-5101-0001', '151-015-101-000'),
(15102, '15-0015102-2', '15-000015102-2', '1510-5102-0002', '151-015-102-000'),
(15103, '15-0015103-3', '15-000015103-3', '1510-5103-0003', '151-015-103-000'),
(15104, '15-0015104-4', '15-000015104-4', '1510-5104-0004', '151-015-104-000'),
(15105, '15-0015105-5', '15-000015105-5', '1510-5105-0005', '151-015-105-000'),
(15106, '15-0015106-6', '15-000015106-6', '1510-5106-0006', '151-015-106-000');

-- Assets and liabilities
REPLACE INTO employee_real_properties
    (property_id, employee_id, description, kind, exact_location, acquisition_year_mode, acquisition_cost)
VALUES
(151011, 15101, 'Residential House and Lot', 'Land and Building', 'Lucena City, Quezon', 'Purchased in 2018', 2850000.00),
(151021, 15102, 'Residential Lot', 'Land', 'Tayabas City, Quezon', 'Purchased in 2021', 980000.00),
(151031, 15103, 'Agricultural Lot', 'Land', 'Sariaya, Quezon', 'Inherited in 2024', 0.00),
(151041, 15104, 'Residential House and Lot', 'Land and Building', 'Lucena City, Quezon', 'Purchased in 2017', 3200000.00),
(151051, 15105, 'Residential Lot', 'Land', 'Pagbilao, Quezon', 'Purchased in 2020', 1150000.00),
(151061, 15106, 'Residential Lot', 'Land', 'Candelaria, Quezon', 'Purchased in 2025', 720000.00);

REPLACE INTO employee_personal_properties (property_id, employee_id, description, year_acquired, acquisition_cost) VALUES
(151011, 15101, 'Toyota Vios', '2022', 920000.00),
(151021, 15102, 'Honda City', '2021', 880000.00),
(151031, 15103, 'Yamaha Motorcycle', '2024', 125000.00),
(151041, 15104, 'Mitsubishi Montero Sport', '2022', 1750000.00),
(151051, 15105, 'Toyota Raize', '2023', 1050000.00),
(151061, 15106, 'Honda Click Motorcycle', '2024', 89000.00);

REPLACE INTO employee_liabilities (liability_id, employee_id, nature_of_liability, creditor_name, outstanding_balance) VALUES
(151011, 15101, 'Housing Loan', 'Pag-IBIG Fund', 1450000.00),
(151021, 15102, 'Auto Loan', 'BDO Unibank', 310000.00),
(151031, 15103, 'Personal Loan', 'Land Bank of the Philippines', 65000.00),
(151041, 15104, 'Housing Loan', 'Bank of the Philippine Islands', 1850000.00),
(151051, 15105, 'Auto Loan', 'Metrobank', 480000.00),
(151061, 15106, 'Personal Loan', 'Philippine National Bank', 75000.00);

-- Character references
REPLACE INTO employee_references (reference_id, employee_id, reference_name, reference_address, reference_telephone) VALUES
(151011, 15101, 'Dr. Regina Santos', 'Lucena City, Quezon', '042-710-6101'),
(151021, 15102, 'Atty. Carlo Reyes', 'Tayabas City, Quezon', '042-710-6102'),
(151031, 15103, 'Prof. Melissa Cruz', 'Sariaya, Quezon', '042-710-6103'),
(151041, 15104, 'Engr. Lorenzo Diaz', 'Lucena City, Quezon', '042-710-6104'),
(151051, 15105, 'Atty. Vanessa Torres', 'Pagbilao, Quezon', '042-710-6105'),
(151061, 15106, 'Broker Antonio Garcia', 'Candelaria, Quezon', '042-710-6106');

SET FOREIGN_KEY_CHECKS = 1;

-- Verification: all six employees should return 12/12 and 100%.
SELECT
    profile.employee_id,
    profile.employee_code,
    profile.employee_name,
    profile.job_title,
    profile.employment_status,
    profile.contract_end_date,
    profile.completed_sections,
    12 AS total_sections,
    ROUND(profile.completed_sections / 12 * 100) AS completion_percentage
FROM (
    SELECT
        e.employee_id,
        e.employee_code,
        CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
        e.job_title,
        e.employment_status,
        e.contract_end_date,
        (
            (e.date_of_birth IS NOT NULL OR e.gender IS NOT NULL)
            + EXISTS(SELECT 1 FROM employee_contacts c WHERE c.employee_id = e.employee_id AND (c.mobile_number <> '' OR c.telephone_number <> ''))
            + (EXISTS(SELECT 1 FROM employee_family f WHERE f.employee_id = e.employee_id) OR EXISTS(SELECT 1 FROM employee_children ch WHERE ch.employee_id = e.employee_id))
            + EXISTS(SELECT 1 FROM employee_education ed WHERE ed.employee_id = e.employee_id)
            + EXISTS(SELECT 1 FROM employee_work_experience w WHERE w.employee_id = e.employee_id)
            + EXISTS(SELECT 1 FROM employee_trainings t WHERE t.employee_id = e.employee_id)
            + EXISTS(SELECT 1 FROM employee_eligibility el WHERE el.employee_id = e.employee_id)
            + (EXISTS(SELECT 1 FROM employee_skills s WHERE s.employee_id = e.employee_id) OR EXISTS(SELECT 1 FROM employee_recognitions r WHERE r.employee_id = e.employee_id) OR EXISTS(SELECT 1 FROM employee_memberships m WHERE m.employee_id = e.employee_id))
            + EXISTS(SELECT 1 FROM employee_government_ids g WHERE g.employee_id = e.employee_id AND (g.sss_number <> '' OR g.philhealth_number <> ''))
            + (EXISTS(SELECT 1 FROM employee_real_properties rp WHERE rp.employee_id = e.employee_id) OR EXISTS(SELECT 1 FROM employee_personal_properties pp WHERE pp.employee_id = e.employee_id))
            + EXISTS(SELECT 1 FROM employee_references ref WHERE ref.employee_id = e.employee_id)
            + EXISTS(SELECT 1 FROM employee_addresses a WHERE a.employee_id = e.employee_id AND a.address_type IN ('Residential', 'Permanent'))
        ) AS completed_sections
    FROM employees e
    WHERE e.employee_id IN (15101, 15102, 15103, 15104, 15105, 15106)
) AS profile
ORDER BY profile.employee_id;
