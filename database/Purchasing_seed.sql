-- Mockup Employee Seeds for Purchasing Department
USE raquel_hris_test_db;
SET FOREIGN_KEY_CHECKS = 0;

-- ====================================
-- 1. EMPLOYEES (Purchasing Team)
-- ====================================
REPLACE INTO employees (employee_id, employee_code, first_name, last_name, middle_name, hire_date, date_of_birth, place_of_birth, gender, civil_status, job_title_id, job_title, department_id, rank_category_id, branch_id, employment_status, employment_type, profile_picture) VALUES

(12001, 'PUR-001', 'Divina', 'Lopez', 'Villanueva', '2021-06-07', '1992-07-03', 'Lucena City, Quezon', 'Female', 'Separated', 1200, 'Purchasing Supervisor I', 12, 4, 102, 'Regular', 'Full-time', 'avatar_f.jpg'),
(12003, 'PUR-003', 'Antonio', 'Santiago', 'Salvador', '2024-11-05', '1999-03-10', 'Lucena City, Quezon', 'Male', 'Separated', 1202, 'Purchasing Staff I', 12, 5, 102, 'Regular', 'Full-time', 'avatar_m.jpg'),

-- Probationary
(12007, 'PUR-007', 'Santiago', 'Aquino', 'Bautista', '2026-01-18', '1997-06-01', 'Lucena City, Quezon', 'Male', 'Single', 1202, 'Purchasing Staff I', 12, 5, 102, 'Probationary', 'Full-time', 'avatar_m.jpg'),
(12008, 'PUR-008', 'Josefina', 'Tolentino', 'Del Rosario', '2026-01-18', '2000-09-13', 'Lucena City, Quezon', 'Female', 'Married', 1202, 'Purchasing Staff I', 12, 5, 102, 'Probationary', 'Full-time', 'avatar_f.jpg'),

-- OJT
(12004, 'PUR-004', 'Francis', 'Bautista', 'Soriano', '2026-08-01', '1999-03-07', 'Lucena City, Quezon', 'Male', 'Widowed', 1202, 'Purchasing Staff I', 12, 5, 102, 'OJT', 'Full-time', 'avatar_m.jpg'),
(12005, 'PUR-005', 'Jose', 'Ocampo', 'De Leon', '2026-08-01', '1998-09-15', 'Lucena City, Quezon', 'Male', 'Single', 1202, 'Purchasing Staff I', 12, 5, 102, 'OJT', 'Full-time', 'avatar_m.jpg'),

-- Trainee
(12002, 'PUR-002', 'Rose', 'Sarmiento', 'Soriano', '2019-05-19', '1985-11-11', 'Lucena City, Quezon', 'Female', 'Widowed', 1201, 'Purchasing Supervisor on Training', 12, 4, 102, 'Trainee', 'Full-time', 'avatar_f.jpg'),
(12006, 'PUR-006', 'Josefina', 'Mendoza', 'Fernandez', '2026-08-10', '2001-08-19', 'Lucena City, Quezon', 'Female', 'Separated', 1201, 'Purchasing Supervisor on Training', 12, 4, 102, 'Trainee', 'Full-time', 'avatar_f.jpg'),

-- Project Based
(12009, 'PUR-009', 'Corazon', 'Bautista', 'Bautista', '2026-08-01', '1996-01-03', 'Lucena City, Quezon', 'Female', 'Widowed', 1202, 'Purchasing Staff I', 12, 5, 102, 'Project Based', 'Full-time', 'avatar_f.jpg'),
(12010, 'PUR-010', 'Lourdes', 'Santos', 'Salvador', '2026-08-01', '2004-10-07', 'Lucena City, Quezon', 'Female', 'Widowed', 1202, 'Purchasing Staff I', 12, 5, 102, 'Project Based', 'Full-time', 'avatar_f.jpg');

REPLACE INTO employee_contacts (employee_id, personal_email, mobile_number, telephone_number) VALUES
(12001, 'divina.lopez@example.com', '09178233471', '888-12001'),
(12002, 'rose.sarmiento@example.com', '09176109503', '888-12002'),
(12003, 'antonio.santiago@example.com', '09173252801', '888-12003'),
(12004, 'francis.bautista@example.com', '09179376888', '888-12004'),
(12005, 'jose.ocampo@example.com', '09179184838', '888-12005'),
(12006, 'josefina.mendoza@example.com', '09172352527', '888-12006'),
(12007, 'santiago.aquino@example.com', '09172290796', '888-12007'),
(12008, 'josefina.tolentino@example.com', '09172437708', '888-12008'),
(12009, 'corazon.bautista@example.com', '09177463219', '888-12009'),
(12010, 'lourdes.santos@example.com', '09177638609', '888-12010');

REPLACE INTO employee_details (employee_id, height_m, weight_kg, blood_type, citizenship) VALUES
(12001, 1.76, 62.8, 'B+', 'Filipino'),
(12002, 1.8, 79.8, 'A-', 'Filipino'),
(12003, 1.52, 70.0, 'A+', 'Filipino'),
(12004, 1.62, 60.2, 'AB+', 'Filipino'),
(12005, 1.63, 69.7, 'AB-', 'Filipino'),
(12006, 1.59, 72.9, 'A-', 'Filipino'),
(12007, 1.84, 68.5, 'O-', 'Filipino'),
(12008, 1.65, 72.2, 'O-', 'Filipino'),
(12009, 1.62, 48.7, 'B-', 'Filipino'),
(12010, 1.66, 50.4, 'A+', 'Filipino');

REPLACE INTO employee_family (employee_id, member_type, surname, first_name, middle_name, occupation) VALUES
(12001, 'Father', 'De Leon', 'Arthur', 'Villanueva', 'Retired'),
(12001, 'Mother', 'Evangelista', 'Elizabeth', 'Reyes', 'Homemaker'),
(12002, 'Father', 'Flores', 'Anthony', 'Reyes', 'Retired'),
(12002, 'Mother', 'Dela Cruz', 'Divina', 'Gomez', 'Homemaker'),
(12003, 'Father', 'Mendoza', 'Francis', 'Reyes', 'Retired'),
(12003, 'Mother', 'Tolentino', 'Christina', 'Rivera', 'Homemaker'),
(12004, 'Father', 'Rivera', 'Lourdes', 'Del Rosario', 'Retired'),
(12004, 'Mother', 'Valenzuela', 'Corazon', 'Salvador', 'Homemaker'),
(12005, 'Father', 'Bautista', 'Ramon', 'Bautista', 'Retired'),
(12005, 'Mother', 'Ramos', 'Rose', 'Lopez', 'Homemaker'),
(12006, 'Father', 'Mendoza', 'Jose', 'Mendoza', 'Retired'),
(12006, 'Mother', 'Del Rosario', 'Michelle', 'Santiago', 'Homemaker'),
(12007, 'Father', 'Soriano', 'Jessica', 'Santos', 'Retired'),
(12007, 'Mother', 'Bautista', 'Carmelita', 'Gonzales', 'Homemaker'),
(12008, 'Father', 'Santos', 'Josefina', 'Ocampo', 'Retired'),
(12008, 'Mother', 'Aquino', 'Jose', 'De Leon', 'Homemaker'),
(12009, 'Father', 'Sarmiento', 'Stephen', 'Mendoza', 'Retired'),
(12009, 'Mother', 'Bautista', 'Robert', 'Santos', 'Homemaker'),
(12010, 'Father', 'Mendoza', 'Virginia', 'Pascual', 'Retired'),
(12010, 'Mother', 'Gonzales', 'Corazon', 'Sarmiento', 'Homemaker');

REPLACE INTO employee_education (employee_id, education_level, school_name, degree_course, year_graduated) VALUES
(12001, 'College', 'Holy Angel University', 'AB Communication', '2013'),
(12002, 'College', 'Southern Luzon State University', 'BS Accountancy', '2006'),
(12003, 'College', 'Pamantasan ng Lungsod ng Maynila', 'BS Management', '2020'),
(12004, 'College', 'Southern Luzon State University', 'BS Business Administration', 'Present'),
(12005, 'College', 'Southern Luzon State University', 'BS Business Administration', 'Present'),
(12006, 'College', 'Southern Luzon State University', 'BS Business Administration', 'Present'),
(12007, 'College', 'Southern Luzon State University', 'BS Business Administration', '2021'),
(12008, 'College', 'Southern Luzon State University', 'BS Business Administration', '2021'),
(12009, 'College', 'Southern Luzon State University', 'BS Business Administration', '2024'),
(12010, 'College', 'Southern Luzon State University', 'BS Business Administration', '2025');

REPLACE INTO employee_work_experience (employee_id, date_from, date_to, job_title, company_name, monthly_salary) VALUES
(12001, '2017-01-15', '2020-12-15', 'Previous I Role', 'Prime Logistics Co.', 37404),
(12002, '2015-01-15', '2018-12-15', 'Previous Training Role', 'Global Retail Corp.', 27639),
(12003, '2020-01-15', '2023-12-15', 'Previous I Role', 'Pacific Marketing Group', 38422),
(12004, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 17770),
(12005, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 23832),
(12006, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 22432),
(12007, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 24379),
(12008, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 23660),
(12009, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 20537),
(12010, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 21738);

REPLACE INTO employee_trainings (employee_id, training_title, conducted_by, no_of_hours) VALUES
(12001, 'Strategic HR & Talent Development', 'Corporate Training Dept', 16.0),
(12002, 'Advanced Management & Leadership', 'Corporate Training Dept', 16.0),
(12003, 'ISO 9001:2015 Quality Management', 'Corporate Training Dept', 16.0),
(12004, 'Workplace Orientation', 'Corporate Training Dept', 8.0),
(12005, 'Workplace Orientation', 'Corporate Training Dept', 8.0),
(12006, 'Workplace Orientation', 'Corporate Training Dept', 16.0),
(12007, 'Workplace Orientation', 'Corporate Training Dept', 16.0),
(12008, 'Workplace Orientation', 'Corporate Training Dept', 16.0),
(12009, 'Workplace Orientation', 'Corporate Training Dept', 16.0),
(12010, 'Workplace Orientation', 'Corporate Training Dept', 16.0);

REPLACE INTO employee_disclosures (employee_id, is_related_to_company, has_admin_offense, has_criminal_charge) VALUES
(12001, 0, 0, 0),
(12002, 0, 0, 0),
(12003, 0, 0, 0),
(12004, 0, 0, 0),
(12005, 0, 0, 0),
(12006, 0, 0, 0),
(12007, 0, 0, 0),
(12008, 0, 0, 0),
(12009, 0, 0, 0),
(12010, 0, 0, 0);

REPLACE INTO employee_government_ids (employee_id, sss_number, philhealth_number, pagibig_number, tin_number) VALUES
(12001, '51-2638360-7', '32-430750834-6', '2140-2781-7208', '435-606-760-000'),
(12002, '57-1891328-0', '81-981277125-5', '2797-5623-1510', '340-542-819-000'),
(12003, '68-9546564-6', '80-980544989-7', '7303-6821-4698', '290-534-719-000'),
(12004, '31-3348114-8', '72-297619784-8', '1962-9584-1554', '959-967-176-000'),
(12005, '45-9988193-4', '12-639250352-6', '3937-2753-2578', '636-252-346-000'),
(12006, '11-3875339-1', '73-565906941-5', '2562-9653-1697', '335-316-988-000'),
(12007, '27-2315798-8', '54-157656107-1', '8161-4803-2245', '448-719-887-000'),
(12008, '91-3588336-6', '50-487298927-1', '2499-1085-6050', '555-468-879-000'),
(12009, '31-7387623-2', '27-388749542-4', '5397-9079-3390', '164-271-545-000'),
(12010, '27-6040467-5', '87-846564420-3', '8848-6147-3910', '507-426-398-000');

REPLACE INTO employee_addresses (employee_id, address_type, region, barangay, city, province) VALUES
(12001, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 11', 'Lucena City', 'Quezon'),
(12001, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 11', 'Lucena City', 'Quezon'),
(12002, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 10', 'Candelaria', 'Quezon'),
(12002, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 10', 'Candelaria', 'Quezon'),
(12003, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 1', 'Pagbilao', 'Quezon'),
(12003, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 1', 'Pagbilao', 'Quezon'),
(12004, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 14', 'Lucena City', 'Quezon'),
(12004, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 14', 'Lucena City', 'Quezon'),
(12005, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 4', 'Candelaria', 'Quezon'),
(12005, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 4', 'Candelaria', 'Quezon'),
(12006, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 12', 'Candelaria', 'Quezon'),
(12006, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 12', 'Candelaria', 'Quezon'),
(12007, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 10', 'Candelaria', 'Quezon'),
(12007, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 10', 'Candelaria', 'Quezon'),
(12008, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 5', 'Lucena City', 'Quezon'),
(12008, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 5', 'Lucena City', 'Quezon'),
(12009, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 5', 'Pagbilao', 'Quezon'),
(12009, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 5', 'Pagbilao', 'Quezon'),
(12010, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 12', 'Pagbilao', 'Quezon'),
(12010, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 12', 'Pagbilao', 'Quezon');

REPLACE INTO employee_emergency_contacts (employee_id, contact_name, relationship, contact_number) VALUES
(12001, 'Arthur De Leon', 'Father', '09182518891'),
(12002, 'Anthony Flores', 'Father', '09186337290'),
(12003, 'Francis Mendoza', 'Father', '09189357686'),
(12004, 'Lourdes Rivera', 'Father', '09179376888'),
(12005, 'Ramon Bautista', 'Father', '09179184838'),
(12006, 'Jose Mendoza', 'Father', '09172352527'),
(12007, 'Jessica Soriano', 'Father', '09172290796'),
(12008, 'Josefina Santos', 'Father', '09172437708'),
(12009, 'Stephen Sarmiento', 'Father', '09177463219'),
(12010, 'Virginia Mendoza', 'Father', '09177638609');

REPLACE INTO employee_real_properties (employee_id, description, kind, acquisition_cost) VALUES
(12001, 'Residential House and Lot', 'Building and Land', 1956364.00),
(12002, 'Residential House and Lot', 'Building and Land', 2992697.00),
(12003, 'Residential House and Lot', 'Building and Land', 3275876.00),
(12004, 'Family Residence Share', 'Building and Land', 250000.0),
(12005, 'Residential House and Lot', 'Building and Land', 1500000.0),
(12006, 'Family Residence Share', 'Building and Land', 300000.0),
(12007, 'Family Residence Share', 'Building and Land', 300000.0),
(12008, 'Family Residence Share', 'Building and Land', 250000.0),
(12009, 'Family Residence Share', 'Building and Land', 300000.0),
(12010, 'Residential House and Lot', 'Building and Land', 1500000.0);

REPLACE INTO employee_personal_properties (employee_id, description, acquisition_cost) VALUES
(12001, 'Personal Effects and Savings', 172832.00),
(12002, 'Personal Effects and Savings', 300692.00),
(12003, 'Personal Effects and Savings', 483209.00),
(12004, 'Personal Effects and Savings', 69053),
(12005, 'Personal Effects and Savings', 48084),
(12006, 'Personal Effects and Savings', 50532),
(12007, 'Personal Effects and Savings', 57778),
(12008, 'Personal Effects and Savings', 26442),
(12009, 'Personal Effects and Savings', 78469),
(12010, 'Personal Effects and Savings', 46775);

REPLACE INTO employee_liabilities (employee_id, nature_of_liability, creditor_name, outstanding_balance) VALUES
(12001, 'Personal Loan', 'Bank', 10048.00),
(12002, 'Personal Loan', 'Bank', 116439.00),
(12003, 'Personal Loan', 'Bank', 72062.00),
(12004, 'Personal Loan', 'Bank', 14010),
(12005, 'Personal Loan', 'Bank', 28228),
(12006, 'Personal Loan', 'Bank', 8051),
(12007, 'Personal Loan', 'Bank', 6941),
(12008, 'Personal Loan', 'Bank', 17280),
(12009, 'Personal Loan', 'Bank', 10041),
(12010, 'Personal Loan', 'Bank', 26357);

REPLACE INTO employee_references (employee_id, reference_name, reference_address, reference_telephone) VALUES
(12001, 'Reference George Mendoza', 'Quezon Province', '09203503436'),
(12002, 'Reference Paul Lopez', 'Quezon Province', '09204543617'),
(12003, 'Reference Ricardo Fernandez', 'Quezon Province', '09205517272'),
(12004, 'Reference Lourdes Rivera', 'Quezon Province', '09179376888'),
(12005, 'Reference Ramon Bautista', 'Quezon Province', '09179184838'),
(12006, 'Reference Jose Mendoza', 'Quezon Province', '09172352527'),
(12007, 'Reference Jessica Soriano', 'Quezon Province', '09172290796'),
(12008, 'Reference Josefina Santos', 'Quezon Province', '09172437708'),
(12009, 'Reference Stephen Sarmiento', 'Quezon Province', '09177463219'),
(12010, 'Reference Virginia Mendoza', 'Quezon Province', '09177638609');

SET FOREIGN_KEY_CHECKS = 1;