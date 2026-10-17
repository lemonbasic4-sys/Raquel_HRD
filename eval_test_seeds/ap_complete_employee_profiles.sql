-- ============================================================================
-- Complete mock employee profiles: Acquired Properties Department
-- Employees: AP Vice President, AP Manager, AP Supervisor, AP Staff
-- Expected profile completion: 100% (12 of 12 personal-detail sections)
-- Prerequisite: database/1st_schema_tables.sql and database/2nd_seed_organization.sql
-- ============================================================================

USE raquel_hris;
SET FOREIGN_KEY_CHECKS = 0;

-- Replacing these fixed employee IDs also clears their prior dependent rows
-- through ON DELETE CASCADE, making this test seed safe to rerun.
REPLACE INTO employees
    (employee_id, employee_code, first_name, last_name, middle_name, hire_date,
     date_of_birth, place_of_birth, gender, civil_status, job_title_id, job_title,
     department_id, rank_category_id, branch_id, reports_to, employment_status,
     employment_type, profile_picture, is_active)
VALUES
(15004, 'AP-VP-001', 'Marisol', 'Alcantara', 'Reyes', '2018-06-04',
 '1981-11-24', 'Lucena City, Quezon', 'Female', 'Married', 100, 'VP for Acquired Properties',
 1, 1, 102, 15010, 'Regular', 'Full-time', 'avatar_f.jpg', 1),
(15001, 'AP-001', 'Adriana', 'Mercado', 'Villareal', '2017-03-06',
 '1985-08-19', 'Lucena City, Quezon', 'Female', 'Married', 101, 'AP Manager I',
 1, 3, 102, 15004, 'Regular', 'Full-time', 'avatar_f.jpg', 1),
(15002, 'AP-002', 'Nathaniel', 'Ramos', 'Castillo', '2019-07-15',
 '1990-02-11', 'Tayabas City, Quezon', 'Male', 'Married', 105, 'AP Supervisor I',
 1, 4, 102, 15001, 'Regular', 'Full-time', 'avatar_m.jpg', 1),
(15003, 'AP-003', 'Bianca', 'Soriano', 'Reyes', '2022-10-03',
 '1997-12-04', 'Sariaya, Quezon', 'Female', 'Single', 108, 'AP Staff I',
 1, 5, 102, 15002, 'Regular', 'Full-time', 'avatar_f.jpg', 1);

-- Contact and physical information
REPLACE INTO employee_contacts
    (employee_id, personal_email, mobile_number, telephone_number)
VALUES
(15004, 'marisol.alcantara@example.com', '09171501504', '042-710-1504'),
(15001, 'adriana.mercado@example.com', '09171501501', '042-710-1501'),
(15002, 'nathaniel.ramos@example.com', '09171501502', '042-710-1502'),
(15003, 'bianca.soriano@example.com', '09171501503', '042-710-1503');

REPLACE INTO employee_details
    (employee_id, height_m, weight_kg, blood_type, citizenship)
VALUES
(15004, 1.66, 61.00, 'B+', 'Filipino'),
(15001, 1.63, 59.00, 'A+', 'Filipino'),
(15002, 1.76, 74.00, 'O+', 'Filipino'),
(15003, 1.58, 53.00, 'B+', 'Filipino');

-- Residential and permanent addresses
REPLACE INTO employee_addresses
    (employee_id, address_type, region, house_no, street, subdivision,
     barangay, city, province, zip_code)
VALUES
(15004, 'Residential', 'Region IV-A (CALABARZON)', '16', 'A. Mabini Street', 'Villa Lourdes', 'Ibabang Dupay', 'Lucena City', 'Quezon', '4301'),
(15004, 'Permanent',   'Region IV-A (CALABARZON)', '16', 'A. Mabini Street', 'Villa Lourdes', 'Ibabang Dupay', 'Lucena City', 'Quezon', '4301'),
(15001, 'Residential', 'Region IV-A (CALABARZON)', '18', 'Maharlika Street', 'Greenview Subdivision', 'Ibabang Dupay', 'Lucena City', 'Quezon', '4301'),
(15001, 'Permanent',   'Region IV-A (CALABARZON)', '42', 'Quezon Avenue', NULL, 'Barangay 5', 'Lucena City', 'Quezon', '4301'),
(15002, 'Residential', 'Region IV-A (CALABARZON)', '27', 'Bonifacio Street', 'Villa Teresa', 'Isabang', 'Tayabas City', 'Quezon', '4327'),
(15002, 'Permanent',   'Region IV-A (CALABARZON)', '27', 'Bonifacio Street', 'Villa Teresa', 'Isabang', 'Tayabas City', 'Quezon', '4327'),
(15003, 'Residential', 'Region IV-A (CALABARZON)', '9', 'Rizal Street', 'Sampaguita Homes', 'Poblacion 2', 'Sariaya', 'Quezon', '4322'),
(15003, 'Permanent',   'Region IV-A (CALABARZON)', '61', 'Gomez Street', NULL, 'Poblacion 4', 'Sariaya', 'Quezon', '4322');

REPLACE INTO employee_emergency_contacts
    (emergency_id, employee_id, contact_name, relationship, contact_number, is_primary)
VALUES
(150041, 15004, 'Ramon Alcantara', 'Spouse', '09181501504', 1),
(150011, 15001, 'Luis Mercado', 'Spouse', '09181501501', 1),
(150021, 15002, 'Carmina Ramos', 'Spouse', '09181501502', 1),
(150031, 15003, 'Teresa Soriano', 'Mother', '09181501503', 1);

-- Family background
REPLACE INTO employee_family
    (employee_id, member_type, surname, first_name, middle_name, occupation)
VALUES
(15004, 'Father', 'Alcantara', 'Rogelio', 'Mendoza', 'Retired Civil Engineer'),
(15004, 'Mother', 'Alcantara', 'Estela', 'Reyes', 'Retired School Principal'),
(15004, 'Spouse', 'Alcantara', 'Ramon', 'Santos', 'Business Consultant'),
(15001, 'Father', 'Villareal', 'Roberto', 'Diaz', 'Retired Bank Officer'),
(15001, 'Mother', 'Villareal', 'Elena', 'Santos', 'Retired Teacher'),
(15001, 'Spouse', 'Mercado', 'Luis', 'Navarro', 'Civil Engineer'),
(15002, 'Father', 'Ramos', 'Danilo', 'Garcia', 'Property Appraiser'),
(15002, 'Mother', 'Ramos', 'Lourdes', 'Castillo', 'Homemaker'),
(15002, 'Spouse', 'Ramos', 'Carmina', 'Lopez', 'Accountant'),
(15003, 'Father', 'Soriano', 'Eduardo', 'Reyes', 'Sales Supervisor'),
(15003, 'Mother', 'Soriano', 'Teresa', 'Mendoza', 'Public School Teacher');

REPLACE INTO employee_children
    (child_id, employee_id, surname, first_name, middle_name, date_of_birth)
VALUES
(150041, 15004, 'Alcantara', 'Sofia', 'Reyes', '2008-07-19'),
(150042, 15004, 'Alcantara', 'Miguel', 'Reyes', '2011-02-10'),
(150011, 15001, 'Mercado', 'Sofia', 'Villareal', '2013-05-22'),
(150021, 15002, 'Ramos', 'Gabriel', 'Lopez', '2018-09-14'),
(150031, 15003, 'Soriano', 'Isabella', 'Reyes', '2021-11-09');

REPLACE INTO employee_siblings
    (sibling_id, employee_id, surname, first_name, middle_name, date_of_birth)
VALUES
(150041, 15004, 'Alcantara', 'Isabel', 'Mendoza', '1978-03-12'),
(150011, 15001, 'Villareal', 'Marco', 'Diaz', '1988-04-16'),
(150021, 15002, 'Ramos', 'Paolo', 'Castillo', '1993-07-27'),
(150031, 15003, 'Soriano', 'Daniel', 'Reyes', '2000-03-18');

-- Educational background
REPLACE INTO employee_education
    (education_id, employee_id, education_level, school_name, degree_course,
     period_from, period_to, year_graduated, honors_received)
VALUES
(150041, 15004, 'College', 'University of the Philippines Diliman', 'BS Business Administration', '1999-06-01', '2003-04-30', '2003', 'Cum Laude'),
(150042, 15004, 'Graduate Studies', 'Ateneo de Manila University', 'Master in Business Administration', '2008-06-01', '2010-04-30', '2010', NULL),
(150011, 15001, 'College', 'University of the Philippines Los Banos', 'BS Agribusiness Management', '2002-06-01', '2006-04-30', '2006', 'Cum Laude'),
(150012, 15001, 'Graduate Studies', 'Manuel S. Enverga University Foundation', 'Master in Business Administration', '2010-06-01', '2012-04-30', '2012', NULL),
(150021, 15002, 'College', 'Southern Luzon State University', 'BS Business Administration', '2007-06-01', '2011-04-30', '2011', NULL),
(150031, 15003, 'College', 'Manuel S. Enverga University Foundation', 'BS Real Estate Management', '2014-06-01', '2018-04-30', '2018', 'Dean''s Lister');

-- Work experience
REPLACE INTO employee_work_experience
    (work_id, employee_id, date_from, date_to, job_title, company_name,
     monthly_salary, appointment_status, reason_for_leaving)
VALUES
(150041, 15004, '2003-06-01', '2018-05-31', 'Regional Property Operations Director', 'Luzon Property Holdings', 165000.00, 'Regular', 'Appointed VP for Acquired Properties'),
(150011, 15001, '2012-05-01', '2017-02-28', 'Property Portfolio Officer', 'Southern Asset Management Corporation', 42000.00, 'Regular', 'Career advancement'),
(150021, 15002, '2014-01-06', '2019-07-12', 'Property Administration Officer', 'Quezon Realty Services', 32000.00, 'Regular', 'Accepted supervisory role'),
(150031, 15003, '2018-06-04', '2022-09-30', 'Real Estate Documentation Associate', 'Pacific Property Solutions', 24000.00, 'Regular', 'Career advancement');

-- Training and voluntary/civic involvement
REPLACE INTO employee_trainings
    (training_id, employee_id, date_from, date_to, training_title,
     training_type, no_of_hours, conducted_by)
VALUES
(150041, 15004, '2025-02-10', '2025-02-12', 'Executive Leadership in Property Management', 'Managerial', 24.00, 'Philippine Association of Property Managers'),
(150011, 15001, '2025-03-10', '2025-03-12', 'Advanced Property Portfolio Management', 'Managerial', 24.00, 'Philippine Association of Realty Consultants'),
(150021, 15002, '2025-05-19', '2025-05-20', 'Property Appraisal and Due Diligence', 'Supervisory', 16.00, 'Real Estate Training Institute'),
(150031, 15003, '2025-07-07', '2025-07-08', 'Land Title Verification and Records Management', 'Technical', 16.00, 'Land Registration Authority');

REPLACE INTO employee_voluntary_work
    (voluntary_id, employee_id, date_from, date_to, organization_name,
     organization_address, no_of_hours, position_nature)
VALUES
(150041, 15004, '2023-03-04', '2023-10-28', 'Quezon Community Housing Initiative', 'Lucena City, Quezon', 56.00, 'Volunteer Housing and Livelihood Adviser'),
(150011, 15001, '2024-02-03', '2024-06-29', 'Quezon Housing Assistance Network', 'Lucena City, Quezon', 40.00, 'Volunteer Property Adviser'),
(150021, 15002, '2024-08-17', '2024-08-18', 'Gawad Kalinga Quezon', 'Tayabas City, Quezon', 16.00, 'Community Build Volunteer'),
(150031, 15003, '2025-01-25', '2025-01-25', 'Sariaya Coastal Cleanup Council', 'Sariaya, Quezon', 8.00, 'Documentation Volunteer');

-- Eligibility, skills, recognitions and memberships
REPLACE INTO employee_eligibility
    (eligibility_id, employee_id, license_title, date_from, date_to,
     license_number, date_of_exam, place_of_exam)
VALUES
(150041, 15004, 'Licensed Real Estate Broker', '2005-09-15', NULL, 'REB-15004-2005', '2005-07-24', 'Manila'),
(150011, 15001, 'Licensed Real Estate Broker', '2009-09-15', NULL, 'REB-15001-2009', '2009-07-26', 'Manila'),
(150021, 15002, 'Career Service Professional Eligibility', '2012-06-18', NULL, 'CSP-15002-2012', '2012-05-27', 'Lucena City'),
(150031, 15003, 'Licensed Real Estate Appraiser', '2019-10-11', NULL, 'REA-15003-2019', '2019-08-25', 'Manila');

REPLACE INTO employee_skills (skill_id, employee_id, skill_name) VALUES
(150041, 15004, 'Property Portfolio Strategy'),
(150042, 15004, 'Executive Leadership'),
(150011, 15001, 'Property Portfolio Strategy'),
(150012, 15001, 'Contract Negotiation'),
(150021, 15002, 'Property Appraisal and Inspection'),
(150022, 15002, 'Team Supervision'),
(150031, 15003, 'Title Verification and Documentation'),
(150032, 15003, 'Property Records Management');

REPLACE INTO employee_recognitions
    (recognition_id, employee_id, recognition_title)
VALUES
(150041, 15004, 'Executive Excellence in Acquired Property Management - 2025'),
(150011, 15001, 'Outstanding Property Management Leader - 2025'),
(150021, 15002, 'Excellence in Property Operations - 2025'),
(150031, 15003, 'AP Service Excellence Award - 2025');

REPLACE INTO employee_memberships
    (membership_id, employee_id, organization_name)
VALUES
(150041, 15004, 'Philippine Association of Real Estate Boards'),
(150011, 15001, 'Philippine Association of Real Estate Boards'),
(150021, 15002, 'Philippine Real Estate Service Practitioners'),
(150031, 15003, 'Association of Property Professionals Philippines');

-- Disclosures and government identifiers
REPLACE INTO employee_disclosures
    (employee_id, is_related_to_company, has_admin_offense, has_criminal_charge,
     has_criminal_conviction, has_been_separated, is_pwd, is_solo_parent,
     has_recent_hospital, has_current_treatment)
VALUES
(15004, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(15001, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(15002, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(15003, 0, 0, 0, 0, 0, 0, 0, 0, 0);

REPLACE INTO employee_government_ids
    (employee_id, sss_number, philhealth_number, pagibig_number, tin_number)
VALUES
(15004, '15-0015004-4', '15-000015004-4', '1501-5004-0004', '150-015-004-000'),
(15001, '15-0015001-1', '15-000015001-1', '1501-5001-0001', '150-015-001-000'),
(15002, '15-0015002-2', '15-000015002-2', '1501-5002-0002', '150-015-002-000'),
(15003, '15-0015003-3', '15-000015003-3', '1501-5003-0003', '150-015-003-000');

-- Assets, personal properties and liabilities
REPLACE INTO employee_real_properties
    (property_id, employee_id, description, kind, exact_location,
     assessed_value, market_value, acquisition_year_mode, acquisition_cost)
VALUES
(150041, 15004, 'Residential House and Lot', 'Land and Building', 'Lucena City, Quezon', 2800000.00, 4100000.00, 'Purchased in 2012', 3300000.00),
(150011, 15001, 'Residential House and Lot', 'Land and Building', 'Lucena City, Quezon', 2100000.00, 3200000.00, 'Purchased in 2014', 2700000.00),
(150021, 15002, 'Residential Lot', 'Land', 'Tayabas City, Quezon', 950000.00, 1450000.00, 'Purchased in 2018', 1100000.00),
(150031, 15003, 'Agricultural Lot', 'Land', 'Sariaya, Quezon', 420000.00, 680000.00, 'Inherited in 2022', 0.00);

REPLACE INTO employee_personal_properties
    (property_id, employee_id, description, year_acquired, acquisition_cost)
VALUES
(150041, 15004, 'Toyota Fortuner', '2021', 1850000.00),
(150011, 15001, 'Toyota Corolla Cross', '2022', 1350000.00),
(150021, 15002, 'Honda City', '2021', 980000.00),
(150031, 15003, 'Honda Click Motorcycle', '2023', 85000.00);

REPLACE INTO employee_liabilities
    (liability_id, employee_id, nature_of_liability, creditor_name, outstanding_balance)
VALUES
(150041, 15004, 'Home Loan', 'Bank of the Philippine Islands', 1650000.00),
(150011, 15001, 'Housing Loan', 'Pag-IBIG Fund', 1250000.00),
(150021, 15002, 'Auto Loan', 'BDO Unibank', 420000.00),
(150031, 15003, 'Personal Loan', 'Land Bank of the Philippines', 95000.00);

-- Character references
REPLACE INTO employee_references
    (reference_id, employee_id, reference_name, reference_address, reference_telephone)
VALUES
(150041, 15004, 'Atty. Teresa Villanueva', 'Lucena City, Quezon', '042-710-2504'),
(150011, 15001, 'Atty. Regina Alvarez', 'Lucena City, Quezon', '042-710-2501'),
(150021, 15002, 'Engr. Manuel de Vera', 'Tayabas City, Quezon', '042-710-2502'),
(150031, 15003, 'Prof. Cecilia Navarro', 'Sariaya, Quezon', '042-710-2503');

SET FOREIGN_KEY_CHECKS = 1;

-- Verification: every result should be 12/12 and 100%.
SELECT
    profile.employee_id,
    profile.employee_code,
    profile.employee_name,
    profile.job_title,
    profile.completed_sections,
    12 AS total_sections,
    ROUND(profile.completed_sections / 12 * 100) AS completion_percentage
FROM (
    SELECT
        e.employee_id,
        e.employee_code,
        CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
        e.job_title,
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
    WHERE e.employee_id IN (15001, 15002, 15003, 15004)
) AS profile
ORDER BY profile.employee_id;
