-- Mockup Employee Seeds for Compliance Department
USE raquel_hris;
SET FOREIGN_KEY_CHECKS = 0;

-- ====================================
-- 1. EMPLOYEES (Compliance Team)
-- ====================================
REPLACE INTO employees (employee_id, employee_code, first_name, last_name, middle_name, hire_date, date_of_birth, place_of_birth, gender, civil_status, job_title_id, job_title, department_id, rank_category_id, branch_id, employment_status, employment_type, profile_picture) VALUES

(4001, 'COMP-001', 'Robert', 'Garcia', 'Del Rosario', '2020-01-16', '1995-06-04', 'Lucena City, Quezon', 'Male', 'Single', 400, 'Compliance Supervisor I', 4, 4, 102, 'Regular', 'Full-time', 'avatar_m.jpg'),
(4002, 'COMP-002', 'Emilio', 'Bautista', 'Ocampo', '2020-04-11', '1997-12-11', 'Lucena City, Quezon', 'Male', 'Separated', 401, 'Compliance Supervisor II', 4, 4, 102, 'Regular', 'Full-time', 'avatar_m.jpg'),
(4003, 'COMP-003', 'Michelle', 'Gonzales', 'Torres', '2020-11-28', '1998-05-03', 'Lucena City, Quezon', 'Female', 'Single', 402, 'Compliance Supervisor III', 4, 4, 102, 'Regular', 'Full-time', 'avatar_f.jpg'),
(4004, 'COMP-004', 'Rosario', 'Torres', 'Gomez', '2023-02-18', '2005-02-11', 'Lucena City, Quezon', 'Female', 'Separated', 403, 'Compliance Staff I', 4, 5, 102, 'Regular', 'Full-time', 'avatar_f.jpg'),
(4005, 'COMP-005', 'Antonio', 'Mendoza', 'Reyes', '2025-12-17', '2001-09-27', 'Lucena City, Quezon', 'Male', 'Married', 404, 'Compliance Staff II', 4, 5, 102, 'Regular', 'Full-time', 'avatar_m.jpg'),
(4006, 'COMP-006', 'Kenneth', 'Aquino', 'Cruz', '2023-12-28', '1995-09-04', 'Lucena City, Quezon', 'Male', 'Married', 405, 'Compliance Staff III', 4, 5, 102, 'Regular', 'Full-time', 'avatar_m.jpg'),

-- Probationary
(4011, 'COMP-011', 'Paul', 'Ramos', 'Santos', '2026-01-18', '1995-10-05', 'Lucena City, Quezon', 'Male', 'Married', 403, 'Compliance Staff I', 4, 5, 102, 'Probationary', 'Full-time', 'avatar_m.jpg'),
(4012, 'COMP-012', 'George', 'Soriano', 'Pascual', '2026-01-18', '2000-09-14', 'Lucena City, Quezon', 'Male', 'Single', 403, 'Compliance Staff I', 4, 5, 102, 'Probationary', 'Full-time', 'avatar_m.jpg'),

-- OJT
(4007, 'COMP-007', 'Stephen', 'Lopez', 'Cruz', '2026-08-01', '1996-04-22', 'Lucena City, Quezon', 'Male', 'Separated', 403, 'Compliance Staff I', 4, 5, 102, 'OJT', 'Full-time', 'avatar_m.jpg'),
(4008, 'COMP-008', 'Kenneth', 'Tolentino', 'De Leon', '2026-08-01', '2005-09-18', 'Lucena City, Quezon', 'Male', 'Widowed', 403, 'Compliance Staff I', 4, 5, 102, 'OJT', 'Full-time', 'avatar_m.jpg'),

-- Trainee
(4009, 'COMP-009', 'Eduardo', 'Santos', 'Mendoza', '2026-08-10', '1998-02-14', 'Lucena City, Quezon', 'Male', 'Married', 403, 'Compliance Staff I', 4, 5, 102, 'Trainee', 'Full-time', 'avatar_m.jpg'),
(4010, 'COMP-010', 'Carla', 'Valenzuela', 'Tolentino', '2026-08-10', '2004-09-01', 'Lucena City, Quezon', 'Female', 'Married', 403, 'Compliance Staff I', 4, 5, 102, 'Trainee', 'Full-time', 'avatar_f.jpg'),

-- Project Based
(4013, 'COMP-013', 'Sarah', 'Rivera', 'Tolentino', '2026-08-01', '2000-01-16', 'Lucena City, Quezon', 'Female', 'Widowed', 403, 'Compliance Staff I', 4, 5, 102, 'Project Based', 'Full-time', 'avatar_f.jpg'),
(4014, 'COMP-014', 'Maria', 'Bautista', 'Lopez', '2026-08-01', '2001-12-18', 'Lucena City, Quezon', 'Female', 'Separated', 403, 'Compliance Staff I', 4, 5, 102, 'Project Based', 'Full-time', 'avatar_f.jpg');

REPLACE INTO employee_contacts (employee_id, personal_email, mobile_number, telephone_number) VALUES
(4001, 'robert.garcia@example.com', '09178225192', '888-4001'),
(4002, 'emilio.bautista@example.com', '09173820798', '888-4002'),
(4003, 'michelle.gonzales@example.com', '09172514923', '888-4003'),
(4004, 'rosario.torres@example.com', '09177485471', '888-4004'),
(4005, 'antonio.mendoza@example.com', '09171633297', '888-4005'),
(4006, 'kenneth.aquino@example.com', '09179655523', '888-4006'),
(4007, 'stephen.lopez@example.com', '09171409790', '888-4007'),
(4008, 'kenneth.tolentino@example.com', '09179468772', '888-4008'),
(4009, 'eduardo.santos@example.com', '09172044645', '888-4009'),
(4010, 'carla.valenzuela@example.com', '09172408404', '888-4010'),
(4011, 'paul.ramos@example.com', '09174656838', '888-4011'),
(4012, 'george.soriano@example.com', '09171212267', '888-4012'),
(4013, 'sarah.rivera@example.com', '09176952975', '888-4013'),
(4014, 'maria.bautista@example.com', '09176144280', '888-4014');

REPLACE INTO employee_details (employee_id, height_m, weight_kg, blood_type, citizenship) VALUES
(4001, 1.57, 60.9, 'A-', 'Filipino'),
(4002, 1.54, 69.5, 'O-', 'Filipino'),
(4003, 1.71, 65.2, 'AB+', 'Filipino'),
(4004, 1.61, 72.6, 'A-', 'Filipino'),
(4005, 1.73, 77.8, 'O-', 'Filipino'),
(4006, 1.78, 57.7, 'A+', 'Filipino'),
(4007, 1.61, 77.3, 'O+', 'Filipino'),
(4008, 1.71, 83.0, 'AB-', 'Filipino'),
(4009, 1.64, 66.7, 'O-', 'Filipino'),
(4010, 1.82, 68.7, 'A+', 'Filipino'),
(4011, 1.58, 63.9, 'B+', 'Filipino'),
(4012, 1.73, 47.8, 'B-', 'Filipino'),
(4013, 1.8, 52.3, 'AB+', 'Filipino'),
(4014, 1.62, 73.1, 'A-', 'Filipino');

REPLACE INTO employee_family (employee_id, member_type, surname, first_name, middle_name, occupation) VALUES
(4001, 'Father', 'Fernandez', 'Michael', 'Salvador', 'Retired'),
(4001, 'Mother', 'Evangelista', 'Aurora', 'Gonzales', 'Homemaker'),
(4002, 'Father', 'Santiago', 'Joseph', 'Gonzales', 'Retired'),
(4002, 'Mother', 'Tolentino', 'Aurora', 'Ocampo', 'Homemaker'),
(4003, 'Father', 'Pascual', 'Ronald', 'Cruz', 'Retired'),
(4003, 'Mother', 'Valenzuela', 'Teresa', 'Soriano', 'Homemaker'),
(4004, 'Father', 'Sarmiento', 'Anthony', 'Ocampo', 'Retired'),
(4004, 'Mother', 'Reyes', 'Grace', 'Soriano', 'Homemaker'),
(4005, 'Father', 'Sarmiento', 'Antonio', 'Perez', 'Retired'),
(4005, 'Mother', 'Soriano', 'Elizabeth', 'Gomez', 'Homemaker'),
(4005, 'Spouse', 'Castillo', 'Leonora', 'Aquino', 'Office Employee'),
(4006, 'Father', 'Gonzales', 'Angelo', 'Ocampo', 'Retired'),
(4006, 'Mother', 'Soriano', 'Christina', 'Rivera', 'Homemaker'),
(4006, 'Spouse', 'Valenzuela', 'Elena', 'Villanueva', 'Office Employee'),
(4007, 'Father', 'Del Rosario', 'David', 'Garcia', 'Retired'),
(4007, 'Mother', 'Sarmiento', 'Ronald', 'Torres', 'Homemaker'),
(4008, 'Father', 'Tolentino', 'Michelle', 'Valenzuela', 'Retired'),
(4008, 'Mother', 'Aquino', 'Corazon', 'Rivera', 'Homemaker'),
(4009, 'Father', 'Mendoza', 'Christopher', 'Rivera', 'Retired'),
(4009, 'Mother', 'Soriano', 'Paul', 'Mendoza', 'Homemaker'),
(4010, 'Father', 'Tolentino', 'Mary', 'Valenzuela', 'Retired'),
(4010, 'Mother', 'Mendoza', 'Francis', 'Gonzales', 'Homemaker'),
(4011, 'Father', 'Aquino', 'Andrea', 'Rivera', 'Retired'),
(4011, 'Mother', 'Garcia', 'Jessica', 'Garcia', 'Homemaker'),
(4012, 'Father', 'Perez', 'Maria', 'Sarmiento', 'Retired'),
(4012, 'Mother', 'Tolentino', 'Leonora', 'Ocampo', 'Homemaker'),
(4013, 'Father', 'Aquino', 'Teresa', 'Garcia', 'Retired'),
(4013, 'Mother', 'Ramos', 'Michelle', 'Lopez', 'Homemaker'),
(4014, 'Father', 'Perez', 'Sarah', 'Salvador', 'Retired'),
(4014, 'Mother', 'Perez', 'John', 'Salvador', 'Homemaker');

REPLACE INTO employee_education (employee_id, education_level, school_name, degree_course, year_graduated) VALUES
(4001, 'College', 'Ateneo de Manila University', 'BS Accountancy', '2016'),
(4002, 'College', 'University of Santo Tomas', 'BS Business Administration', '2018'),
(4003, 'College', 'University of Santo Tomas', 'BS Hotel and Restaurant Management', '2019'),
(4004, 'College', 'De La Salle University', 'BS Hotel and Restaurant Management', '2026'),
(4005, 'College', 'Pamantasan ng Lungsod ng Maynila', 'BS Psychology', '2022'),
(4006, 'College', 'Pamantasan ng Lungsod ng Maynila', 'BS Hotel and Restaurant Management', '2016'),
(4007, 'College', 'Southern Luzon State University', 'BS Business Administration', 'Present'),
(4008, 'College', 'Southern Luzon State University', 'BS Business Administration', 'Present'),
(4009, 'College', 'Southern Luzon State University', 'BS Business Administration', 'Present'),
(4010, 'College', 'Southern Luzon State University', 'BS Business Administration', 'Present'),
(4011, 'College', 'Southern Luzon State University', 'BS Business Administration', '2016'),
(4012, 'College', 'Southern Luzon State University', 'BS Business Administration', '2017'),
(4013, 'College', 'Southern Luzon State University', 'BS Business Administration', '2018'),
(4014, 'College', 'Southern Luzon State University', 'BS Business Administration', '2023');

REPLACE INTO employee_work_experience (employee_id, date_from, date_to, job_title, company_name, monthly_salary) VALUES
(4001, '2016-01-15', '2019-12-15', 'Previous I Role', 'BPO Solutions Inc.', 32294),
(4002, '2016-01-15', '2019-12-15', 'Previous II Role', 'Global Retail Corp.', 26387),
(4003, '2016-01-15', '2019-12-15', 'Previous III Role', 'United Services Group', 34428),
(4004, '2019-01-15', '2022-12-15', 'Previous I Role', 'Summit Property Management', 39497),
(4005, '2021-01-15', '2024-12-15', 'Previous II Role', 'Pacific Marketing Group', 29937),
(4006, '2019-01-15', '2022-12-15', 'Previous III Role', 'BPO Solutions Inc.', 31595),
(4007, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 21431),
(4008, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 16050),
(4009, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 19814),
(4010, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 15467),
(4011, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 24743),
(4012, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 19135),
(4013, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 13402),
(4014, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 24487);

REPLACE INTO employee_trainings (employee_id, training_title, conducted_by, no_of_hours) VALUES
(4001, 'Advanced Management & Leadership', 'Corporate Training Dept', 16.0),
(4002, 'IT Infrastructure and Security', 'Corporate Training Dept', 16.0),
(4003, 'ISO 9001:2015 Quality Management', 'Corporate Training Dept', 16.0),
(4004, 'Occupational Safety and Health', 'Corporate Training Dept', 16.0),
(4005, 'IT Infrastructure and Security', 'Corporate Training Dept', 16.0),
(4006, 'Advanced Management & Leadership', 'Corporate Training Dept', 16.0),
(4007, 'Workplace Orientation', 'Corporate Training Dept', 8.0),
(4008, 'Workplace Orientation', 'Corporate Training Dept', 8.0),
(4009, 'Workplace Orientation', 'Corporate Training Dept', 16.0),
(4010, 'Workplace Orientation', 'Corporate Training Dept', 16.0),
(4011, 'Workplace Orientation', 'Corporate Training Dept', 16.0),
(4012, 'Workplace Orientation', 'Corporate Training Dept', 16.0),
(4013, 'Workplace Orientation', 'Corporate Training Dept', 16.0),
(4014, 'Workplace Orientation', 'Corporate Training Dept', 16.0);

REPLACE INTO employee_disclosures (employee_id, is_related_to_company, has_admin_offense, has_criminal_charge) VALUES
(4001, 0, 0, 0),
(4002, 0, 0, 0),
(4003, 0, 0, 0),
(4004, 0, 0, 0),
(4005, 0, 0, 0),
(4006, 0, 0, 0),
(4007, 0, 0, 0),
(4008, 0, 0, 0),
(4009, 0, 0, 0),
(4010, 0, 0, 0),
(4011, 0, 0, 0),
(4012, 0, 0, 0),
(4013, 0, 0, 0),
(4014, 0, 0, 0);

REPLACE INTO employee_government_ids (employee_id, sss_number, philhealth_number, pagibig_number, tin_number) VALUES
(4001, '68-2306317-5', '83-560940492-9', '7626-7843-5743', '217-514-121-000'),
(4002, '32-3564729-1', '32-929839316-7', '8601-8354-6295', '984-742-423-000'),
(4003, '17-8558523-1', '53-866990485-1', '9266-3827-1641', '353-824-548-000'),
(4004, '26-2421349-4', '81-504456327-5', '3093-9624-2531', '761-786-533-000'),
(4005, '66-5780297-7', '39-673390109-3', '6070-8684-4178', '476-794-684-000'),
(4006, '60-9422281-5', '40-514642708-1', '7142-4678-1461', '426-201-959-000'),
(4007, '34-7442089-7', '61-361976301-2', '1090-2746-7965', '324-280-923-000'),
(4008, '91-5652512-8', '72-772966010-3', '5499-8206-2269', '830-392-340-000'),
(4009, '10-6901533-4', '59-997356828-6', '9818-9947-4613', '599-324-379-000'),
(4010, '68-6483562-5', '58-398775766-6', '5133-2341-8705', '119-867-652-000'),
(4011, '30-6218367-1', '84-127571992-4', '7149-7498-4249', '177-706-807-000'),
(4012, '32-9753457-4', '88-967797300-8', '8921-8616-8136', '945-848-706-000'),
(4013, '40-7818732-7', '81-914073098-3', '8800-9041-8342', '911-117-195-000'),
(4014, '98-4106298-3', '37-893070706-7', '5530-9595-5636', '202-952-298-000');

REPLACE INTO employee_addresses (employee_id, address_type, region, barangay, city, province) VALUES
(4001, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 6', 'Tayabas City', 'Quezon'),
(4001, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 6', 'Tayabas City', 'Quezon'),
(4002, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 3', 'Candelaria', 'Quezon'),
(4002, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 3', 'Candelaria', 'Quezon'),
(4003, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 8', 'Pagbilao', 'Quezon'),
(4003, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 8', 'Pagbilao', 'Quezon'),
(4004, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 9', 'Sariaya', 'Quezon'),
(4004, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 9', 'Sariaya', 'Quezon'),
(4005, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 8', 'Candelaria', 'Quezon'),
(4005, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 8', 'Candelaria', 'Quezon'),
(4006, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 12', 'Sariaya', 'Quezon'),
(4006, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 12', 'Sariaya', 'Quezon'),
(4007, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 12', 'Candelaria', 'Quezon'),
(4007, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 12', 'Candelaria', 'Quezon'),
(4008, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 5', 'Sariaya', 'Quezon'),
(4008, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 5', 'Sariaya', 'Quezon'),
(4009, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 7', 'Pagbilao', 'Quezon'),
(4009, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 7', 'Pagbilao', 'Quezon'),
(4010, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 1', 'Sariaya', 'Quezon'),
(4010, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 1', 'Sariaya', 'Quezon'),
(4011, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 14', 'Tayabas City', 'Quezon'),
(4011, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 14', 'Tayabas City', 'Quezon'),
(4012, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 5', 'Sariaya', 'Quezon'),
(4012, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 5', 'Sariaya', 'Quezon'),
(4013, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 5', 'Tayabas City', 'Quezon'),
(4013, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 5', 'Tayabas City', 'Quezon'),
(4014, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 5', 'Tayabas City', 'Quezon'),
(4014, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 5', 'Tayabas City', 'Quezon');

REPLACE INTO employee_emergency_contacts (employee_id, contact_name, relationship, contact_number) VALUES
(4001, 'Michael Fernandez', 'Father', '09188719145'),
(4002, 'Joseph Santiago', 'Father', '09182145653'),
(4003, 'Ronald Pascual', 'Father', '09189770256'),
(4004, 'Anthony Sarmiento', 'Father', '09181305857'),
(4005, 'Antonio Sarmiento', 'Father', '09185727345'),
(4006, 'Angelo Gonzales', 'Father', '09183450300'),
(4007, 'David Del Rosario', 'Father', '09171409790'),
(4008, 'Michelle Tolentino', 'Father', '09179468772'),
(4009, 'Christopher Mendoza', 'Father', '09172044645'),
(4010, 'Mary Tolentino', 'Father', '09172408404'),
(4011, 'Andrea Aquino', 'Father', '09174656838'),
(4012, 'Maria Perez', 'Father', '09171212267'),
(4013, 'Teresa Aquino', 'Father', '09176952975'),
(4014, 'Sarah Perez', 'Father', '09176144280');

REPLACE INTO employee_real_properties (employee_id, description, kind, acquisition_cost) VALUES
(4001, 'Residential House and Lot', 'Building and Land', 3244884.00),
(4002, 'Residential House and Lot', 'Building and Land', 2483398.00),
(4003, 'Residential House and Lot', 'Building and Land', 2778854.00),
(4004, 'Residential House and Lot', 'Building and Land', 2260422.00),
(4005, 'Residential House and Lot', 'Building and Land', 3131844.00),
(4006, 'Residential House and Lot', 'Building and Land', 1788567.00),
(4007, 'Family Residence Share', 'Building and Land', 300000.0),
(4008, 'Family Residence Share', 'Building and Land', 300000.0),
(4009, 'Family Residence Share', 'Building and Land', 250000.0),
(4010, 'Family Residence Share', 'Building and Land', 250000.0),
(4011, 'Family Residence Share', 'Building and Land', 250000.0),
(4012, 'Family Residence Share', 'Building and Land', 250000.0),
(4013, 'Family Residence Share', 'Building and Land', 300000.0),
(4014, 'Family Residence Share', 'Building and Land', 300000.0);

REPLACE INTO employee_personal_properties (employee_id, description, acquisition_cost) VALUES
(4001, 'Personal Effects and Savings', 461554.00),
(4002, 'Personal Effects and Savings', 331844.00),
(4003, 'Personal Effects and Savings', 183228.00),
(4004, 'Personal Effects and Savings', 261934.00),
(4005, 'Personal Effects and Savings', 300211.00),
(4006, 'Personal Effects and Savings', 120081.00),
(4007, 'Personal Effects and Savings', 21582),
(4008, 'Personal Effects and Savings', 85798),
(4009, 'Personal Effects and Savings', 65968),
(4010, 'Personal Effects and Savings', 23993),
(4011, 'Personal Effects and Savings', 54529),
(4012, 'Personal Effects and Savings', 26359),
(4013, 'Personal Effects and Savings', 46890),
(4014, 'Personal Effects and Savings', 38519);

REPLACE INTO employee_liabilities (employee_id, nature_of_liability, creditor_name, outstanding_balance) VALUES
(4001, 'Personal Loan', 'Bank', 104857.00),
(4002, 'Personal Loan', 'Bank', 89382.00),
(4003, 'Personal Loan', 'Bank', 105393.00),
(4004, 'Personal Loan', 'Bank', 57254.00),
(4005, 'Personal Loan', 'Bank', 141833.00),
(4006, 'Personal Loan', 'Bank', 85203.00),
(4007, 'Personal Loan', 'Bank', 41530),
(4008, 'Personal Loan', 'Bank', 10280),
(4009, 'Personal Loan', 'Bank', 27028),
(4010, 'Personal Loan', 'Bank', 47713),
(4011, 'Personal Loan', 'Bank', 49843),
(4012, 'Personal Loan', 'Bank', 23279),
(4013, 'Personal Loan', 'Bank', 25067),
(4014, 'Personal Loan', 'Bank', 24809);

REPLACE INTO employee_references (employee_id, reference_name, reference_address, reference_telephone) VALUES
(4001, 'Reference Manuel Lopez', 'Quezon Province', '09202776088'),
(4002, 'Reference Ronald Aquino', 'Quezon Province', '09201942411'),
(4003, 'Reference Mark Valenzuela', 'Quezon Province', '09205746166'),
(4004, 'Reference Robert Torres', 'Quezon Province', '09209158272'),
(4005, 'Reference Joseph Lopez', 'Quezon Province', '09203718884'),
(4006, 'Reference Anthony Soriano', 'Quezon Province', '09208926478'),
(4007, 'Reference David Del Rosario', 'Quezon Province', '09171409790'),
(4008, 'Reference Michelle Tolentino', 'Quezon Province', '09179468772'),
(4009, 'Reference Christopher Mendoza', 'Quezon Province', '09172044645'),
(4010, 'Reference Mary Tolentino', 'Quezon Province', '09172408404'),
(4011, 'Reference Andrea Aquino', 'Quezon Province', '09174656838'),
(4012, 'Reference Maria Perez', 'Quezon Province', '09171212267'),
(4013, 'Reference Teresa Aquino', 'Quezon Province', '09176952975'),
(4014, 'Reference Sarah Perez', 'Quezon Province', '09176144280');

SET FOREIGN_KEY_CHECKS = 1;
