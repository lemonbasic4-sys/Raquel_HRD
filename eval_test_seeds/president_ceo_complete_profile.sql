-- ============================================================================
-- Fictional President and CEO employee + login account
-- Expected profile completion: 100% (12 of 12 personal-detail sections)
-- Prerequisite: database/1st_schema_tables.sql and database/2nd_seed_organization.sql
--
-- Login: ceo.isabella.villanueva
-- Password: password
-- ============================================================================

USE raquel_hris;
SET FOREIGN_KEY_CHECKS = 0;

REPLACE INTO employees
    (employee_id, employee_code, first_name, last_name, middle_name, hire_date,
     date_of_birth, place_of_birth, gender, civil_status, job_title_id, job_title,
     department_id, rank_category_id, branch_id, reports_to, employment_status,
     employment_type, profile_picture, is_active)
VALUES
(15010, 'CEO-001', 'Isabella', 'Villanueva', 'Montenegro', '2012-01-09',
 '1974-06-21', 'Lucena City, Quezon', 'Female', 'Married', 1100,
 'President and CEO', 10, 1, 102, NULL, 'Regular', 'Full-time', 'avatar_f.jpg', 1);

-- Link the seeded HR Manager to the CEO only after both employee rows exist.
UPDATE employees SET reports_to = 15010 WHERE employee_id = 101 AND department_id = 7;

-- Contact, physical information, and addresses
REPLACE INTO employee_contacts
    (employee_id, personal_email, mobile_number, telephone_number)
VALUES
(15010, 'isabella.villanueva@example.com', '09171501510', '042-710-1510');

REPLACE INTO employee_details
    (employee_id, height_m, weight_kg, blood_type, citizenship)
VALUES
(15010, 1.65, 58.00, 'AB+', 'Filipino');

REPLACE INTO employee_addresses
    (employee_id, address_type, region, house_no, street, subdivision,
     barangay, city, province, zip_code)
VALUES
(15010, 'Residential', 'Region IV-A (CALABARZON)', '8', 'Executive Drive', 'Montclair Heights', 'Ilayang Iyam', 'Lucena City', 'Quezon', '4301'),
(15010, 'Permanent', 'Region IV-A (CALABARZON)', '116', 'Mabini Street', NULL, 'Barangay 3', 'Lucena City', 'Quezon', '4301');

REPLACE INTO employee_emergency_contacts
    (emergency_id, employee_id, contact_name, relationship, contact_number, is_primary)
VALUES
(150101, 15010, 'Alejandro Villanueva', 'Spouse', '09181501510', 1);

-- Family background
REPLACE INTO employee_family
    (employee_id, member_type, surname, first_name, middle_name, occupation)
VALUES
(15010, 'Father', 'Montenegro', 'Rafael', 'Santos', 'Retired Entrepreneur'),
(15010, 'Mother', 'Montenegro', 'Corazon', 'Diaz', 'Retired University Professor'),
(15010, 'Spouse', 'Villanueva', 'Alejandro', 'Reyes', 'Architect');

REPLACE INTO employee_children
    (child_id, employee_id, surname, first_name, middle_name, date_of_birth)
VALUES
(150101, 15010, 'Villanueva', 'Camille', 'Montenegro', '2002-09-18'),
(150102, 15010, 'Villanueva', 'Lorenzo', 'Montenegro', '2006-03-07');

REPLACE INTO employee_siblings
    (sibling_id, employee_id, surname, first_name, middle_name, date_of_birth)
VALUES
(150101, 15010, 'Montenegro', 'Beatriz', 'Diaz', '1977-11-12');

-- Educational background
REPLACE INTO employee_education
    (education_id, employee_id, education_level, school_name, degree_course,
     period_from, period_to, year_graduated, honors_received)
VALUES
(150101, 15010, 'College', 'University of the Philippines Diliman', 'BS Business Administration', '1991-06-01', '1995-04-30', '1995', 'Magna Cum Laude'),
(150102, 15010, 'Graduate Studies', 'Asian Institute of Management', 'Master in Business Management', '1998-06-01', '2000-05-31', '2000', 'With Distinction');

-- Work experience
REPLACE INTO employee_work_experience
    (work_id, employee_id, date_from, date_to, job_title, company_name,
     monthly_salary, appointment_status, reason_for_leaving)
VALUES
(150101, 15010, '2000-06-01', '2006-12-31', 'Corporate Planning Director', 'Pacific Holdings Corporation', 95000.00, 'Regular', 'Accepted an executive leadership role'),
(150102, 15010, '2007-01-02', '2012-01-06', 'Chief Operating Officer', 'Southern Luzon Development Group', 185000.00, 'Executive', 'Appointed President and CEO');

-- Training and civic involvement
REPLACE INTO employee_trainings
    (training_id, employee_id, date_from, date_to, training_title,
     training_type, no_of_hours, conducted_by)
VALUES
(150101, 15010, '2024-05-13', '2024-05-15', 'Corporate Governance and Board Leadership', 'Executive', 24.00, 'Institute of Corporate Directors'),
(150102, 15010, '2025-02-17', '2025-02-18', 'Enterprise Risk and Strategic Resilience', 'Executive', 16.00, 'Management Association of the Philippines');

REPLACE INTO employee_voluntary_work
    (voluntary_id, employee_id, date_from, date_to, organization_name,
     organization_address, no_of_hours, position_nature)
VALUES
(150101, 15010, '2023-01-14', '2025-12-13', 'Quezon Enterprise Development Foundation', 'Lucena City, Quezon', 72.00, 'Volunteer Board Chair and Business Mentor');

-- Eligibility, skills, recognitions, and memberships
REPLACE INTO employee_eligibility
    (eligibility_id, employee_id, license_title, date_from, date_to,
     license_number, date_of_exam, place_of_exam)
VALUES
(150101, 15010, 'Career Service Executive Eligibility', '2008-10-20', NULL, 'CSEE-15010-2008', '2008-08-31', 'Quezon City');

REPLACE INTO employee_skills (skill_id, employee_id, skill_name) VALUES
(150101, 15010, 'Corporate Strategy and Governance'),
(150102, 15010, 'Enterprise Risk Management'),
(150103, 15010, 'Executive Leadership and Negotiation');

REPLACE INTO employee_recognitions
    (recognition_id, employee_id, recognition_title)
VALUES
(150101, 15010, 'Quezon Outstanding Business Leader - 2024'),
(150102, 15010, 'Excellence in Corporate Governance - 2025');

REPLACE INTO employee_memberships
    (membership_id, employee_id, organization_name)
VALUES
(150101, 15010, 'Management Association of the Philippines'),
(150102, 15010, 'Institute of Corporate Directors Philippines');

-- Disclosures and government identifiers
REPLACE INTO employee_disclosures
    (employee_id, is_related_to_company, has_admin_offense, has_criminal_charge,
     has_criminal_conviction, has_been_separated, is_pwd, is_solo_parent,
     has_recent_hospital, has_current_treatment)
VALUES
(15010, 0, 0, 0, 0, 0, 0, 0, 0, 0);

REPLACE INTO employee_government_ids
    (employee_id, sss_number, philhealth_number, pagibig_number, tin_number)
VALUES
(15010, '15-0015010-4', '15-000015010-4', '1501-5010-0010', '150-015-010-000');

-- Assets, properties, and liabilities
REPLACE INTO employee_real_properties
    (property_id, employee_id, description, kind, exact_location,
     assessed_value, market_value, acquisition_year_mode, acquisition_cost)
VALUES
(150101, 15010, 'Family Residence and Lot', 'Land and Building', 'Lucena City, Quezon', 6500000.00, 9800000.00, 'Purchased in 2010', 7200000.00),
(150102, 15010, 'Agricultural Property', 'Land', 'Pagbilao, Quezon', 2400000.00, 3900000.00, 'Inherited in 2018', 0.00);

REPLACE INTO employee_personal_properties
    (property_id, employee_id, description, year_acquired, acquisition_cost)
VALUES
(150101, 15010, 'Toyota Land Cruiser Prado', '2023', 4250000.00),
(150102, 15010, 'Art and Book Collection', '2020', 780000.00);

REPLACE INTO employee_liabilities
    (liability_id, employee_id, nature_of_liability, creditor_name, outstanding_balance)
VALUES
(150101, 15010, 'Home Improvement Loan', 'Bank of the Philippine Islands', 1350000.00);

-- Character reference
REPLACE INTO employee_references
    (reference_id, employee_id, reference_name, reference_address, reference_telephone)
VALUES
(150101, 15010, 'Atty. Victoria Hernandez', 'Makati City, Metro Manila', '02-8555-1510');

-- Linked system account. The hash below is the project's standard test password: password
REPLACE INTO users
    (user_id, employee_id, username, email, full_name, password_hash,
     role, branch_id, is_active, account_hold, first_login_completed)
VALUES
(15010, 15010, 'ceo.isabella.villanueva', 'ceo.isabella@company.com',
 'Isabella Villanueva', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'President and CEO', 102, 1, 0, 1);

SET FOREIGN_KEY_CHECKS = 1;

-- Verification: this employee should return 12/12 and 100%.
SELECT
    profile.employee_id,
    profile.employee_code,
    profile.employee_name,
    profile.job_title,
    profile.username,
    profile.role,
    profile.completed_sections,
    12 AS total_sections,
    ROUND(profile.completed_sections / 12 * 100) AS completion_percentage
FROM (
    SELECT
        e.employee_id,
        e.employee_code,
        CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
        e.job_title,
        u.username,
        u.role,
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
    JOIN users u ON u.employee_id = e.employee_id
    WHERE e.employee_id = 15010
) AS profile;
