-- Mockup Employee Seeds for Business Development Department
USE raquel_hris_test_db;
SET FOREIGN_KEY_CHECKS = 0;

-- ====================================
-- 1. EMPLOYEES (Business Development Team)
-- ====================================
REPLACE INTO employees (employee_id, employee_code, first_name, last_name, middle_name, hire_date, date_of_birth, place_of_birth, gender, civil_status, job_title_id, job_title, department_id, rank_category_id, branch_id, employment_status, employment_type, profile_picture) VALUES

(3001, 'BD-001', 'Antonio', 'Dela Cruz', 'Gonzales', '2017-05-02', '1987-01-06', 'Lucena City, Quezon', 'Male', 'Single', 300, 'Business Development Officer I', 3, 3, 102, 'Regular', 'Full-time', 'avatar_m.jpg'),
(3003, 'BD-003', 'Emilio', 'Cruz', 'Salvador', '2021-11-25', '2000-07-12', 'Lucena City, Quezon', 'Male', 'Single', 302, 'Business Development Staff I', 3, 5, 102, 'Regular', 'Full-time', 'avatar_m.jpg'),
(3004, 'BD-004', 'Eduardo', 'Tolentino', 'Santos', '2025-07-15', '2004-04-13', 'Lucena City, Quezon', 'Male', 'Married', 303, 'Business Development Staff II', 3, 5, 102, 'Regular', 'Full-time', 'avatar_m.jpg'),

-- Probationary
(3008, 'BD-008', 'Rhea', 'De Leon', 'Torres', '2026-01-18', '1997-12-15', 'Lucena City, Quezon', 'Female', 'Separated', 302, 'Business Development Staff I', 3, 5, 102, 'Probationary', 'Full-time', 'avatar_f.jpg'),
(3009, 'BD-009', 'Arthur', 'Pascual', 'Pascual', '2026-01-18', '2001-01-06', 'Lucena City, Quezon', 'Male', 'Married', 302, 'Business Development Staff I', 3, 5, 102, 'Probationary', 'Full-time', 'avatar_m.jpg'),

-- OJT
(3005, 'BD-005', 'Maria', 'Del Rosario', 'Evangelista', '2026-08-01', '1995-03-13', 'Lucena City, Quezon', 'Female', 'Widowed', 302, 'Business Development Staff I', 3, 5, 102, 'OJT', 'Full-time', 'avatar_f.jpg'),
(3006, 'BD-006', 'Michael', 'Perez', 'Soriano', '2026-08-01', '2004-10-02', 'Lucena City, Quezon', 'Male', 'Married', 302, 'Business Development Staff I', 3, 5, 102, 'OJT', 'Full-time', 'avatar_m.jpg'),

-- Trainee
(3002, 'BD-002', 'Santiago', 'Tolentino', 'Soriano', '2023-09-10', '1997-07-20', 'Lucena City, Quezon', 'Male', 'Widowed', 301, 'Business Development Staff on Training', 3, 5, 102, 'Trainee', 'Full-time', 'avatar_m.jpg'),
(3007, 'BD-007', 'Mark', 'Gomez', 'Valenzuela', '2026-08-10', '2003-12-10', 'Lucena City, Quezon', 'Male', 'Widowed', 301, 'Business Development Staff on Training', 3, 5, 102, 'Trainee', 'Full-time', 'avatar_m.jpg'),

-- Project Based
(3010, 'BD-010', 'Carmelita', 'Bautista', 'Gonzales', '2026-08-01', '1996-05-06', 'Lucena City, Quezon', 'Female', 'Single', 302, 'Business Development Staff I', 3, 5, 102, 'Project Based', 'Full-time', 'avatar_f.jpg'),
(3011, 'BD-011', 'Virginia', 'Gonzales', 'Cruz', '2026-08-01', '1999-09-10', 'Lucena City, Quezon', 'Female', 'Single', 302, 'Business Development Staff I', 3, 5, 102, 'Project Based', 'Full-time', 'avatar_f.jpg');

REPLACE INTO employee_contacts (employee_id, personal_email, mobile_number, telephone_number) VALUES
(3001, 'antonio.delacruz@example.com', '09173126655', '888-3001'),
(3002, 'santiago.tolentino@example.com', '09178612081', '888-3002'),
(3003, 'emilio.cruz@example.com', '09178095702', '888-3003'),
(3004, 'eduardo.tolentino@example.com', '09176719285', '888-3004'),
(3005, 'maria.del rosario@example.com', '09175785687', '888-3005'),
(3006, 'michael.perez@example.com', '09175374922', '888-3006'),
(3007, 'mark.gomez@example.com', '09176022741', '888-3007'),
(3008, 'rhea.de leon@example.com', '09172876932', '888-3008'),
(3009, 'arthur.pascual@example.com', '09175163555', '888-3009'),
(3010, 'carmelita.bautista@example.com', '09172818692', '888-3010'),
(3011, 'virginia.gonzales@example.com', '09173135534', '888-3011');

REPLACE INTO employee_details (employee_id, height_m, weight_kg, blood_type, citizenship) VALUES
(3001, 1.75, 65.9, 'A-', 'Filipino'),
(3002, 1.68, 62.6, 'A-', 'Filipino'),
(3003, 1.79, 72.5, 'B+', 'Filipino'),
(3004, 1.57, 58.6, 'B+', 'Filipino'),
(3005, 1.5, 60.6, 'AB-', 'Filipino'),
(3006, 1.72, 61.8, 'B-', 'Filipino'),
(3007, 1.71, 84.5, 'A+', 'Filipino'),
(3008, 1.69, 62.1, 'A+', 'Filipino'),
(3009, 1.76, 58.3, 'O-', 'Filipino'),
(3010, 1.7, 84.3, 'A+', 'Filipino'),
(3011, 1.73, 58.0, 'A-', 'Filipino');

REPLACE INTO employee_family (employee_id, member_type, surname, first_name, middle_name, occupation) VALUES
(3001, 'Father', 'Villanueva', 'Manuel', 'Torres', 'Retired'),
(3001, 'Mother', 'Flores', 'Rosario', 'Ocampo', 'Homemaker'),
(3002, 'Father', 'Gomez', 'Danilo', 'Salvador', 'Retired'),
(3002, 'Mother', 'Gomez', 'Cecilia', 'Del Rosario', 'Homemaker'),
(3003, 'Father', 'Garcia', 'Santiago', 'Gonzales', 'Retired'),
(3003, 'Mother', 'Madrigal', 'Patricia', 'Ocampo', 'Homemaker'),
(3004, 'Father', 'Madrigal', 'George', 'Gomez', 'Retired'),
(3004, 'Mother', 'Castillo', 'Elena', 'Cruz', 'Homemaker'),
(3004, 'Spouse', 'Perez', 'Christina', 'Gomez', 'Office Employee'),
(3005, 'Father', 'Salvador', 'Andrea', 'Pascual', 'Retired'),
(3005, 'Mother', 'Evangelista', 'Mary', 'Gomez', 'Homemaker'),
(3006, 'Father', 'Ocampo', 'Santiago', 'Gonzales', 'Retired'),
(3006, 'Mother', 'Mendoza', 'Francis', 'De Leon', 'Homemaker'),
(3007, 'Father', 'Aquino', 'Eduardo', 'Perez', 'Retired'),
(3007, 'Mother', 'Bautista', 'Robert', 'De Leon', 'Homemaker'),
(3008, 'Father', 'De Leon', 'Emilio', 'Bautista', 'Retired'),
(3008, 'Mother', 'Soriano', 'Mark', 'Bautista', 'Homemaker'),
(3009, 'Father', 'Pascual', 'Jessica', 'Ocampo', 'Retired'),
(3009, 'Mother', 'Santiago', 'Michael', 'Del Rosario', 'Homemaker'),
(3010, 'Father', 'Salvador', 'Gloria', 'Pascual', 'Retired'),
(3010, 'Mother', 'Garcia', 'Divina', 'Lopez', 'Homemaker'),
(3011, 'Father', 'Cruz', 'Christopher', 'Mendoza', 'Retired'),
(3011, 'Mother', 'Soriano', 'Kevin', 'Gomez', 'Homemaker');

REPLACE INTO employee_education (employee_id, education_level, school_name, degree_course, year_graduated) VALUES
(3001, 'College', 'Holy Angel University', 'BS Business Administration', '2008'),
(3002, 'College', 'Mapua University', 'AB Communication', '2018'),
(3003, 'College', 'University of Santo Tomas', 'BS Information Technology', '2021'),
(3004, 'College', 'University of the Philippines', 'BS Business Administration', '2025'),
(3005, 'College', 'Southern Luzon State University', 'BS Business Administration', 'Present'),
(3006, 'College', 'Southern Luzon State University', 'BS Business Administration', 'Present'),
(3007, 'College', 'Southern Luzon State University', 'BS Business Administration', 'Present'),
(3008, 'College', 'Southern Luzon State University', 'BS Business Administration', '2017'),
(3009, 'College', 'Southern Luzon State University', 'BS Business Administration', '2018'),
(3010, 'College', 'Southern Luzon State University', 'BS Business Administration', '2016'),
(3011, 'College', 'Southern Luzon State University', 'BS Business Administration', '2021');

REPLACE INTO employee_work_experience (employee_id, date_from, date_to, job_title, company_name, monthly_salary) VALUES
(3001, '2013-01-15', '2016-12-15', 'Previous I Role', 'United Services Group', 34509),
(3002, '2019-01-15', '2022-12-15', 'Previous Training Role', 'Secure Tech Philippines', 31559),
(3003, '2017-01-15', '2020-12-15', 'Previous I Role', 'United Services Group', 25348),
(3004, '2021-01-15', '2024-12-15', 'Previous II Role', 'Summit Property Management', 32519),
(3005, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 15566),
(3006, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 23004),
(3007, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 16462),
(3008, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 19041),
(3009, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 15269),
(3010, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 18311),
(3011, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 20977);

REPLACE INTO employee_trainings (employee_id, training_title, conducted_by, no_of_hours) VALUES
(3001, 'IT Infrastructure and Security', 'Corporate Training Dept', 16.0),
(3002, 'Customer Service Excellence', 'Corporate Training Dept', 16.0),
(3003, 'Professional Ethics in Workplace', 'Corporate Training Dept', 16.0),
(3004, 'Occupational Safety and Health', 'Corporate Training Dept', 16.0),
(3005, 'Workplace Orientation', 'Corporate Training Dept', 8.0),
(3006, 'Workplace Orientation', 'Corporate Training Dept', 8.0),
(3007, 'Workplace Orientation', 'Corporate Training Dept', 16.0),
(3008, 'Workplace Orientation', 'Corporate Training Dept', 16.0),
(3009, 'Workplace Orientation', 'Corporate Training Dept', 16.0),
(3010, 'Workplace Orientation', 'Corporate Training Dept', 16.0),
(3011, 'Workplace Orientation', 'Corporate Training Dept', 16.0);

REPLACE INTO employee_disclosures (employee_id, is_related_to_company, has_admin_offense, has_criminal_charge) VALUES
(3001, 0, 0, 0),
(3002, 0, 0, 0),
(3003, 0, 0, 0),
(3004, 0, 0, 0),
(3005, 0, 0, 0),
(3006, 0, 0, 0),
(3007, 0, 0, 0),
(3008, 0, 0, 0),
(3009, 0, 0, 0),
(3010, 0, 0, 0),
(3011, 0, 0, 0);

REPLACE INTO employee_government_ids (employee_id, sss_number, philhealth_number, pagibig_number, tin_number) VALUES
(3001, '88-1727544-8', '48-591819409-0', '1996-8847-7580', '536-802-210-000'),
(3002, '68-7689582-6', '22-435596237-6', '6120-5176-7132', '256-803-585-000'),
(3003, '54-7166651-1', '45-716435189-3', '8030-1430-5381', '129-284-379-000'),
(3004, '72-8416270-6', '44-331465414-8', '2864-6655-8043', '213-390-794-000'),
(3005, '17-2022698-5', '17-153839920-9', '8811-9238-9701', '261-158-620-000'),
(3006, '92-6033115-7', '50-907308348-1', '1152-8508-2638', '175-650-318-000'),
(3007, '46-4533779-5', '36-838194420-4', '9280-9004-5114', '966-152-194-000'),
(3008, '26-1701771-4', '56-954829815-0', '6862-4441-5088', '782-205-462-000'),
(3009, '68-6866294-4', '39-339362341-0', '4164-7528-6378', '385-985-171-000'),
(3010, '83-4188992-4', '15-861052404-6', '1027-9518-9821', '803-836-859-000'),
(3011, '10-6098175-4', '36-561588882-9', '6279-8618-8238', '552-791-318-000');

REPLACE INTO employee_addresses (employee_id, address_type, region, barangay, city, province) VALUES
(3001, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 8', 'Candelaria', 'Quezon'),
(3001, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 8', 'Candelaria', 'Quezon'),
(3002, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 2', 'Lucena City', 'Quezon'),
(3002, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 2', 'Lucena City', 'Quezon'),
(3003, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 12', 'Sariaya', 'Quezon'),
(3003, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 12', 'Sariaya', 'Quezon'),
(3004, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 11', 'Pagbilao', 'Quezon'),
(3004, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 11', 'Pagbilao', 'Quezon'),
(3005, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 2', 'Tayabas City', 'Quezon'),
(3005, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 2', 'Tayabas City', 'Quezon'),
(3006, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 9', 'Sariaya', 'Quezon'),
(3006, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 9', 'Sariaya', 'Quezon'),
(3007, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 11', 'Pagbilao', 'Quezon'),
(3007, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 11', 'Pagbilao', 'Quezon'),
(3008, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 13', 'Candelaria', 'Quezon'),
(3008, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 13', 'Candelaria', 'Quezon'),
(3009, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 13', 'Sariaya', 'Quezon'),
(3009, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 13', 'Sariaya', 'Quezon'),
(3010, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 12', 'Tayabas City', 'Quezon'),
(3010, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 12', 'Tayabas City', 'Quezon'),
(3011, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 9', 'Pagbilao', 'Quezon'),
(3011, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 9', 'Pagbilao', 'Quezon');

REPLACE INTO employee_emergency_contacts (employee_id, contact_name, relationship, contact_number) VALUES
(3001, 'Manuel Villanueva', 'Father', '09182232969'),
(3002, 'Danilo Gomez', 'Father', '09182432176'),
(3003, 'Santiago Garcia', 'Father', '09186701311'),
(3004, 'George Madrigal', 'Father', '09189164342'),
(3005, 'Andrea Salvador', 'Father', '09175785687'),
(3006, 'Santiago Ocampo', 'Father', '09175374922'),
(3007, 'Eduardo Aquino', 'Father', '09176022741'),
(3008, 'Emilio De Leon', 'Father', '09172876932'),
(3009, 'Jessica Pascual', 'Father', '09175163555'),
(3010, 'Gloria Salvador', 'Father', '09172818692'),
(3011, 'Christopher Cruz', 'Father', '09173135534');

REPLACE INTO employee_real_properties (employee_id, description, kind, acquisition_cost) VALUES
(3001, 'Residential House and Lot', 'Building and Land', 3385899.00),
(3002, 'Residential House and Lot', 'Building and Land', 1695517.00),
(3003, 'Residential House and Lot', 'Building and Land', 2236149.00),
(3004, 'Residential House and Lot', 'Building and Land', 2605060.00),
(3005, 'Family Residence Share', 'Building and Land', 250000.0),
(3006, 'Family Residence Share', 'Building and Land', 250000.0),
(3007, 'Family Residence Share', 'Building and Land', 300000.0),
(3008, 'Family Residence Share', 'Building and Land', 300000.0),
(3009, 'Family Residence Share', 'Building and Land', 300000.0),
(3010, 'Family Residence Share', 'Building and Land', 300000.0),
(3011, 'Residential House and Lot', 'Building and Land', 1500000.0);

REPLACE INTO employee_personal_properties (employee_id, description, acquisition_cost) VALUES
(3001, 'Personal Effects and Savings', 142357.00),
(3002, 'Personal Effects and Savings', 326427.00),
(3003, 'Personal Effects and Savings', 103201.00),
(3004, 'Personal Effects and Savings', 449769.00),
(3005, 'Personal Effects and Savings', 92992),
(3006, 'Personal Effects and Savings', 60745),
(3007, 'Personal Effects and Savings', 20778),
(3008, 'Personal Effects and Savings', 96351),
(3009, 'Personal Effects and Savings', 99080),
(3010, 'Personal Effects and Savings', 71531),
(3011, 'Personal Effects and Savings', 37241);

REPLACE INTO employee_liabilities (employee_id, nature_of_liability, creditor_name, outstanding_balance) VALUES
(3001, 'Personal Loan', 'Bank', 94439.00),
(3002, 'Personal Loan', 'Bank', 35313.00),
(3003, 'Personal Loan', 'Bank', 57554.00),
(3004, 'Personal Loan', 'Bank', 90850.00),
(3005, 'Personal Loan', 'Bank', 9453),
(3006, 'Personal Loan', 'Bank', 9508),
(3007, 'Personal Loan', 'Bank', 5232),
(3008, 'Personal Loan', 'Bank', 15128),
(3009, 'Personal Loan', 'Bank', 38384),
(3010, 'Personal Loan', 'Bank', 9585),
(3011, 'Personal Loan', 'Bank', 48178);

REPLACE INTO employee_references (employee_id, reference_name, reference_address, reference_telephone) VALUES
(3001, 'Reference Santiago Mendoza', 'Quezon Province', '09202101965'),
(3002, 'Reference Eduardo Pascual', 'Quezon Province', '09207251976'),
(3003, 'Reference Kenneth Mendoza', 'Quezon Province', '09207724045'),
(3004, 'Reference Jose Ramos', 'Quezon Province', '09207631419'),
(3005, 'Reference Andrea Salvador', 'Quezon Province', '09175785687'),
(3006, 'Reference Santiago Ocampo', 'Quezon Province', '09175374922'),
(3007, 'Reference Eduardo Aquino', 'Quezon Province', '09176022741'),
(3008, 'Reference Emilio De Leon', 'Quezon Province', '09172876932'),
(3009, 'Reference Jessica Pascual', 'Quezon Province', '09175163555'),
(3010, 'Reference Gloria Salvador', 'Quezon Province', '09172818692'),
(3011, 'Reference Christopher Cruz', 'Quezon Province', '09173135534');

SET FOREIGN_KEY_CHECKS = 1;