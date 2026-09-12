-- Mockup Employee Seeds for Office of the President Department
USE raquel_hris;
SET FOREIGN_KEY_CHECKS = 0;

-- ====================================
-- 1. EMPLOYEES (Office of the President Team)
-- ====================================
REPLACE INTO employees (employee_id, employee_code, first_name, last_name, middle_name, hire_date, date_of_birth, place_of_birth, gender, civil_status, job_title_id, job_title, department_id, rank_category_id, branch_id, employment_status, employment_type, profile_picture, reports_to) VALUES

(10001, 'OP-001', 'Bernadette', 'Salvador', 'Mendoza', '2018-07-18', '1979-06-07', 'Lucena City, Quezon', 'Female', 'Widowed', 1100, 'President and CEO', 10, 1, 102, 'Regular', 'Full-time', 'avatar_f.jpg', NULL),
(10002, 'OP-002', 'Ricardo', 'Gonzales', 'Santos', '2022-04-11', '2002-01-16', 'Lucena City, Quezon', 'Male', 'Widowed', 1101, 'Executive Assistant I', 10, 5, 102, 'Regular', 'Full-time', 'avatar_m.jpg', 10001),
(10003, 'OP-003', 'Susan', 'Cruz', 'Ocampo', '2024-04-12', '2004-11-10', 'Lucena City, Quezon', 'Female', 'Married', 1102, 'Executive Assistant II', 10, 5, 102, 'Regular', 'Full-time', 'avatar_f.jpg', 10001),
(10004, 'OP-004', 'Arthur', 'Salvador', 'Gonzales', '2022-07-12', '1993-01-23', 'Lucena City, Quezon', 'Male', 'Separated', 1103, 'Executive Assistant III', 10, 5, 102, 'Regular', 'Full-time', 'avatar_m.jpg', 10001),
(999, 'JUICE-999', 'Jarad', 'Higgins', 'Anthony', '2017-01-01', '1990-01-01', 'Lucena City, Quezon', 'Male', 'Married', 1103, 'Executive Assistant III', 10, 5, 102, 'Regular', 'Full-time', 'juice.png', 10001),

-- Probationary
(10009, 'OP-009', 'Stephen', 'Ocampo', 'Fernandez', '2026-01-18', '2002-11-09', 'Lucena City, Quezon', 'Male', 'Separated', 1101, 'Executive Assistant I', 10, 5, 102, 'Probationary', 'Full-time', 'avatar_m.jpg', 10001),
(10010, 'OP-010', 'Catherine', 'Torres', 'Santiago', '2026-01-18', '2005-10-08', 'Lucena City, Quezon', 'Female', 'Single', 1101, 'Executive Assistant I', 10, 5, 102, 'Probationary', 'Full-time', 'avatar_f.jpg', 10001),

-- OJT
(10005, 'OP-005', 'Christopher', 'De Leon', 'Aquino', '2026-08-01', '2001-06-02', 'Lucena City, Quezon', 'Male', 'Separated', 1101, 'Executive Assistant I', 10, 5, 102, 'OJT', 'Full-time', 'avatar_m.jpg', 10001),
(10006, 'OP-006', 'Emilio', 'Gonzales', 'Pascual', '2026-08-01', '2003-08-05', 'Lucena City, Quezon', 'Male', 'Widowed', 1101, 'Executive Assistant I', 10, 5, 102, 'OJT', 'Full-time', 'avatar_m.jpg', 10001),

-- Trainee
(10007, 'OP-007', 'Robert', 'Santiago', 'Soriano', '2026-08-10', '1997-09-21', 'Lucena City, Quezon', 'Male', 'Widowed', 1101, 'Executive Assistant I', 10, 5, 102, 'Trainee', 'Full-time', 'avatar_m.jpg', 10001),
(10008, 'OP-008', 'Teresa', 'Valenzuela', 'Tolentino', '2026-08-10', '2003-06-22', 'Lucena City, Quezon', 'Female', 'Separated', 1101, 'Executive Assistant I', 10, 5, 102, 'Trainee', 'Full-time', 'avatar_f.jpg', 10001),

-- Project Based
(10011, 'OP-011', 'Leonora', 'Aquino', 'Ocampo', '2026-08-01', '1995-12-11', 'Lucena City, Quezon', 'Female', 'Married', 1101, 'Executive Assistant I', 10, 5, 102, 'Project Based', 'Full-time', 'avatar_f.jpg', 10001),
(10012, 'OP-012', 'Andrea', 'Santos', 'Santiago', '2026-08-01', '2002-05-06', 'Lucena City, Quezon', 'Female', 'Single', 1101, 'Executive Assistant I', 10, 5, 102, 'Project Based', 'Full-time', 'avatar_f.jpg', 10001);

REPLACE INTO employee_contacts (employee_id, personal_email, mobile_number, telephone_number) VALUES
(10001, 'bernadette.salvador@example.com', '09178333427', '888-10001'),
(10002, 'ricardo.gonzales@example.com', '09178050508', '888-10002'),
(10003, 'susan.cruz@example.com', '09171873883', '888-10003'),
(10004, 'arthur.salvador@example.com', '09173260430', '888-10004'),
(999, 'jarad.higgins@example.com', '09171234567', '888-10005'),
(10005, 'christopher.de leon@example.com', '09177082139', '888-10005'),
(10006, 'emilio.gonzales@example.com', '09176344047', '888-10006'),
(10007, 'robert.santiago@example.com', '09173479264', '888-10007'),
(10008, 'teresa.valenzuela@example.com', '09179123194', '888-10008'),
(10009, 'stephen.ocampo@example.com', '09175557052', '888-10009'),
(10010, 'catherine.torres@example.com', '09173937099', '888-10010'),
(10011, 'leonora.aquino@example.com', '09173944316', '888-10011'),
(10012, 'andrea.santos@example.com', '09172969909', '888-10012');

REPLACE INTO employee_details (employee_id, height_m, weight_kg, blood_type, citizenship) VALUES
(10001, 1.64, 53.6, 'A-', 'Filipino'),
(10002, 1.62, 75.2, 'B+', 'Filipino'),
(10003, 1.79, 55.4, 'O+', 'Filipino'),
(10004, 1.6, 49.3, 'A-', 'Filipino'),
(999, 1.75, 70.0, 'O+', 'Filipino'),
(10005, 1.64, 67.8, 'AB+', 'Filipino'),
(10006, 1.71, 65.6, 'B-', 'Filipino'),
(10007, 1.75, 76.0, 'O-', 'Filipino'),
(10008, 1.77, 73.8, 'A-', 'Filipino'),
(10009, 1.76, 53.6, 'A-', 'Filipino'),
(10010, 1.69, 73.9, 'A+', 'Filipino'),
(10011, 1.78, 67.7, 'AB+', 'Filipino'),
(10012, 1.64, 65.1, 'A-', 'Filipino');

REPLACE INTO employee_family (employee_id, member_type, surname, first_name, middle_name, occupation) VALUES
(10001, 'Father', 'Bautista', 'Albert', 'Perez', 'Retired'),
(10001, 'Mother', 'Lopez', 'Elena', 'Villanueva', 'Homemaker'),
(10002, 'Father', 'De Leon', 'David', 'Cruz', 'Retired'),
(10002, 'Mother', 'Santos', 'Christina', 'Garcia', 'Homemaker'),
(10003, 'Father', 'Tolentino', 'Michael', 'Rivera', 'Retired'),
(10003, 'Mother', 'Dela Cruz', 'Grace', 'Aquino', 'Homemaker'),
(10003, 'Spouse', 'Aquino', 'Ronald', 'Soriano', 'Office Employee'),
(10004, 'Father', 'Cruz', 'David', 'Mendoza', 'Retired'),
(10004, 'Mother', 'De Leon', 'Imelda', 'Pascual', 'Homemaker'),
(999, 'Father', 'Higgins', 'Antonio', 'Anthony', 'Retired'),
(10005, 'Father', 'Mendoza', 'Patricia', 'Lopez', 'Retired'),
(10005, 'Mother', 'Bautista', 'Rhea', 'Aquino', 'Homemaker'),
(10006, 'Father', 'Garcia', 'Michael', 'Salvador', 'Retired'),
(10006, 'Mother', 'Gonzales', 'Angelica', 'Lopez', 'Homemaker'),
(10007, 'Father', 'Soriano', 'Albert', 'Ocampo', 'Retired'),
(10007, 'Mother', 'Ramos', 'Gabriel', 'Tolentino', 'Homemaker'),
(10008, 'Father', 'Aquino', 'David', 'Bautista', 'Retired'),
(10008, 'Mother', 'Gomez', 'Sarah', 'Rivera', 'Homemaker'),
(10009, 'Father', 'Garcia', 'Kenneth', 'Salvador', 'Retired'),
(10009, 'Mother', 'Santiago', 'David', 'Cruz', 'Homemaker'),
(10010, 'Father', 'Santiago', 'Jessica', 'Mendoza', 'Retired'),
(10010, 'Mother', 'Salvador', 'Jessica', 'Gomez', 'Homemaker'),
(10011, 'Father', 'Lopez', 'Divina', 'Gonzales', 'Retired'),
(10011, 'Mother', 'Cruz', 'Michael', 'Perez', 'Homemaker'),
(10012, 'Father', 'Garcia', 'Gloria', 'Santos', 'Retired'),
(10012, 'Mother', 'Del Rosario', 'Eduardo', 'De Leon', 'Homemaker');

REPLACE INTO employee_education (employee_id, education_level, school_name, degree_course, year_graduated) VALUES
(10001, 'College', 'Southern Luzon State University', 'BS Management', '2000'),
(10002, 'College', 'Mapua University', 'BS Business Administration', '2023'),
(10003, 'College', 'Ateneo de Manila University', 'BS Accountancy', '2025'),
(10004, 'College', 'University of Santo Tomas', 'BS Management', '2014'),
(999, 'College', 'Polytechnic University of the Philippines', 'BS Business Administration', '2011'),
(10005, 'College', 'Southern Luzon State University', 'BS Business Administration', 'Present'),
(10006, 'College', 'Southern Luzon State University', 'BS Business Administration', 'Present'),
(10007, 'College', 'Southern Luzon State University', 'BS Business Administration', 'Present'),
(10008, 'College', 'Southern Luzon State University', 'BS Business Administration', 'Present'),
(10009, 'College', 'Southern Luzon State University', 'BS Business Administration', '2023'),
(10010, 'College', 'Southern Luzon State University', 'BS Business Administration', '2015'),
(10011, 'College', 'Southern Luzon State University', 'BS Business Administration', '2017'),
(10012, 'College', 'Southern Luzon State University', 'BS Business Administration', '2022');

REPLACE INTO employee_work_experience (employee_id, date_from, date_to, job_title, company_name, monthly_salary) VALUES
(10001, '2014-01-15', '2017-12-15', 'Previous CEO Role', 'Summit Property Management', 27328),
(10002, '2018-01-15', '2021-12-15', 'Previous I Role', 'United Services Group', 23950),
(10003, '2020-01-15', '2023-12-15', 'Previous II Role', 'Secure Tech Philippines', 18292),
(10004, '2018-01-15', '2021-12-15', 'Previous III Role', 'Global Retail Corp.', 35813),
(999, '2013-06-01', '2016-12-31', 'Administrative Officer', 'Quezon Cooperative Bank', 18500),
(10005, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 22211),
(10006, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 20351),
(10007, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 17139),
(10008, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 16735),
(10009, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 15697),
(10010, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 12151),
(10011, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 14303),
(10012, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 24250);

REPLACE INTO employee_trainings (employee_id, training_title, conducted_by, no_of_hours) VALUES
(10001, 'Financial Management and Tax Audits', 'Corporate Training Dept', 16.0),
(10002, 'IT Infrastructure and Security', 'Corporate Training Dept', 16.0),
(10003, 'Advanced Management & Leadership', 'Corporate Training Dept', 16.0),
(10004, 'Customer Service Excellence', 'Corporate Training Dept', 16.0),
(999, 'Business Process Improvement', 'Corporate Training Dept', 16.0),
(10005, 'Workplace Orientation', 'Corporate Training Dept', 8.0),
(10006, 'Workplace Orientation', 'Corporate Training Dept', 8.0),
(10007, 'Workplace Orientation', 'Corporate Training Dept', 16.0),
(10008, 'Workplace Orientation', 'Corporate Training Dept', 16.0),
(10009, 'Workplace Orientation', 'Corporate Training Dept', 16.0),
(10010, 'Workplace Orientation', 'Corporate Training Dept', 16.0),
(10011, 'Workplace Orientation', 'Corporate Training Dept', 16.0),
(10012, 'Workplace Orientation', 'Corporate Training Dept', 16.0);

REPLACE INTO employee_disclosures (employee_id, is_related_to_company, has_admin_offense, has_criminal_charge) VALUES
(10001, 0, 0, 0),
(10002, 0, 0, 0),
(10003, 0, 0, 0),
(10004, 0, 0, 0),
(999, 0, 0, 0),
(10005, 0, 0, 0),
(10006, 0, 0, 0),
(10007, 0, 0, 0),
(10008, 0, 0, 0),
(10009, 0, 0, 0),
(10010, 0, 0, 0),
(10011, 0, 0, 0),
(10012, 0, 0, 0);

REPLACE INTO employee_government_ids (employee_id, sss_number, philhealth_number, pagibig_number, tin_number) VALUES
(10001, '62-4154127-1', '33-246698943-4', '2808-8460-8418', '378-817-737-000'),
(10002, '42-7401806-0', '25-924790131-3', '7304-8257-5049', '855-134-303-000'),
(10003, '84-8973718-9', '29-498786278-1', '5075-3583-9190', '193-802-733-000'),
(10004, '51-8858359-4', '86-695411648-2', '2128-9702-3539', '560-402-939-000'),
(999, '73-5621048-2', '41-378294561-7', '3916-7204-6183', '712-304-581-000'),
(10005, '97-2993318-2', '22-522950895-5', '6555-6990-3363', '303-716-621-000'),
(10006, '78-9218263-9', '48-609912042-0', '7035-6427-2795', '526-697-415-000'),
(10007, '63-3540594-6', '98-318927025-6', '9221-8726-2020', '822-241-631-000'),
(10008, '36-9204110-5', '71-474524558-8', '5480-5715-2996', '687-792-656-000'),
(10009, '91-4906349-0', '22-543778963-5', '8739-2647-3252', '105-663-261-000'),
(10010, '48-2800206-5', '46-587853601-8', '9590-9092-3198', '971-616-579-000'),
(10011, '71-6429135-3', '10-380016058-6', '4858-8319-5374', '437-409-696-000'),
(10012, '87-7268384-6', '99-689736058-7', '9806-4578-5054', '796-872-710-000');

REPLACE INTO employee_addresses (employee_id, address_type, region, barangay, city, province) VALUES
(10001, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 8', 'Tayabas City', 'Quezon'),
(10001, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 8', 'Tayabas City', 'Quezon'),
(10002, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 8', 'Lucena City', 'Quezon'),
(10002, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 8', 'Lucena City', 'Quezon'),
(10003, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 6', 'Lucena City', 'Quezon'),
(10003, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 6', 'Lucena City', 'Quezon'),
(10004, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 5', 'Pagbilao', 'Quezon'),
(10004, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 5', 'Pagbilao', 'Quezon'),
(999, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 3', 'Lucena City', 'Quezon'),
(999, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 3', 'Lucena City', 'Quezon'),
(10005, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 7', 'Candelaria', 'Quezon'),
(10005, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 7', 'Candelaria', 'Quezon'),
(10006, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 13', 'Lucena City', 'Quezon'),
(10006, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 13', 'Lucena City', 'Quezon'),
(10007, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 4', 'Candelaria', 'Quezon'),
(10007, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 4', 'Candelaria', 'Quezon'),
(10008, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 7', 'Pagbilao', 'Quezon'),
(10008, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 7', 'Pagbilao', 'Quezon'),
(10009, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 7', 'Pagbilao', 'Quezon'),
(10009, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 7', 'Pagbilao', 'Quezon'),
(10010, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 5', 'Tayabas City', 'Quezon'),
(10010, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 5', 'Tayabas City', 'Quezon'),
(10011, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 12', 'Candelaria', 'Quezon'),
(10011, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 12', 'Candelaria', 'Quezon'),
(10012, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 14', 'Lucena City', 'Quezon'),
(10012, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 14', 'Lucena City', 'Quezon');

REPLACE INTO employee_emergency_contacts (employee_id, contact_name, relationship, contact_number) VALUES
(10001, 'Albert Bautista', 'Father', '09186379509'),
(10002, 'David De Leon', 'Father', '09189743263'),
(10003, 'Michael Tolentino', 'Father', '09182002860'),
(10004, 'David Cruz', 'Father', '09187159129'),
(999, 'Antonio Higgins', 'Father', '09185671234'),
(10005, 'Patricia Mendoza', 'Father', '09177082139'),
(10006, 'Michael Garcia', 'Father', '09176344047'),
(10007, 'Albert Soriano', 'Father', '09173479264'),
(10008, 'David Aquino', 'Father', '09179123194'),
(10009, 'Kenneth Garcia', 'Father', '09175557052'),
(10010, 'Jessica Santiago', 'Father', '09173937099'),
(10011, 'Divina Lopez', 'Father', '09173944316'),
(10012, 'Gloria Garcia', 'Father', '09172969909');

REPLACE INTO employee_real_properties (employee_id, description, kind, acquisition_cost) VALUES
(10001, 'Residential House and Lot', 'Building and Land', 1540675.00),
(10002, 'Residential House and Lot', 'Building and Land', 2896719.00),
(10003, 'Residential House and Lot', 'Building and Land', 2245574.00),
(10004, 'Residential House and Lot', 'Building and Land', 2452272.00),
(999, 'Residential House and Lot', 'Building and Land', 1875000.00),
(10005, 'Family Residence Share', 'Building and Land', 250000.0),
(10006, 'Residential House and Lot', 'Building and Land', 1500000.0),
(10007, 'Family Residence Share', 'Building and Land', 300000.0),
(10008, 'Family Residence Share', 'Building and Land', 300000.0),
(10009, 'Family Residence Share', 'Building and Land', 300000.0),
(10010, 'Family Residence Share', 'Building and Land', 250000.0),
(10011, 'Family Residence Share', 'Building and Land', 250000.0),
(10012, 'Residential House and Lot', 'Building and Land', 1500000.0);

REPLACE INTO employee_personal_properties (employee_id, description, acquisition_cost) VALUES
(10001, 'Personal Effects and Savings', 179770.00),
(10002, 'Personal Effects and Savings', 209286.00),
(10003, 'Personal Effects and Savings', 437942.00),
(10004, 'Personal Effects and Savings', 100377.00),
(999, 'Personal Effects and Savings', 154320.00),
(10005, 'Personal Effects and Savings', 20938),
(10006, 'Personal Effects and Savings', 77205),
(10007, 'Personal Effects and Savings', 77701),
(10008, 'Personal Effects and Savings', 34198),
(10009, 'Personal Effects and Savings', 41143),
(10010, 'Personal Effects and Savings', 58343),
(10011, 'Personal Effects and Savings', 49282),
(10012, 'Personal Effects and Savings', 73556);

REPLACE INTO employee_liabilities (employee_id, nature_of_liability, creditor_name, outstanding_balance) VALUES
(10001, 'Personal Loan', 'Bank', 23010.00),
(10002, 'Personal Loan', 'Bank', 133948.00),
(10003, 'Personal Loan', 'Bank', 50348.00),
(10004, 'Personal Loan', 'Bank', 142633.00),
(999, 'Personal Loan', 'Bank', 87500.00),
(10005, 'Personal Loan', 'Bank', 7550),
(10006, 'Personal Loan', 'Bank', 22401),
(10007, 'Personal Loan', 'Bank', 39437),
(10008, 'Personal Loan', 'Bank', 24035),
(10009, 'Personal Loan', 'Bank', 23824),
(10010, 'Personal Loan', 'Bank', 15649),
(10011, 'Personal Loan', 'Bank', 47839),
(10012, 'Personal Loan', 'Bank', 39614);

REPLACE INTO employee_references (employee_id, reference_name, reference_address, reference_telephone) VALUES
(10001, 'Reference Arthur Dela Cruz', 'Quezon Province', '09204119515'),
(10002, 'Reference Paul Evangelista', 'Quezon Province', '09206236326'),
(10003, 'Reference Ricardo Cruz', 'Quezon Province', '09202801733'),
(10004, 'Reference Paul Pascual', 'Quezon Province', '09204112748'),
(999    , 'Reference Maria Santos', 'Quezon Province', '09201358924'),
(10005, 'Reference Patricia Mendoza', 'Quezon Province', '09177082139'),
(10006, 'Reference Michael Garcia', 'Quezon Province', '09176344047'),
(10007, 'Reference Albert Soriano', 'Quezon Province', '09173479264'),
(10008, 'Reference David Aquino', 'Quezon Province', '09179123194'),
(10009, 'Reference Kenneth Garcia', 'Quezon Province', '09175557052'),
(10010, 'Reference Jessica Santiago', 'Quezon Province', '09173937099'),
(10011, 'Reference Divina Lopez', 'Quezon Province', '09173944316'),
(10012, 'Reference Gloria Garcia', 'Quezon Province', '09172969909');

SET FOREIGN_KEY_CHECKS = 1;
