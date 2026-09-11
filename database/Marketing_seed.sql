-- Mockup Employee Seeds for Marketing Department
USE raquel_hris_Test;
SET FOREIGN_KEY_CHECKS = 0;

-- ====================================
-- 1. EMPLOYEES (Marketing Team)
-- ====================================
REPLACE INTO employees (employee_id, employee_code, first_name, last_name, middle_name, hire_date, date_of_birth, place_of_birth, gender, civil_status, job_title_id, job_title, department_id, rank_category_id, branch_id, employment_status, employment_type, profile_picture) VALUES

(9001, 'MKT-001', 'Manuel', 'Valenzuela', 'Reyes', '2020-06-12', '1977-09-19', 'Lucena City, Quezon', 'Male', 'Single', 900, 'Marketing Manager I', 9, 3, 102, 'Regular', 'Full-time', 'avatar_m.jpg'),
(9002, 'MKT-002', 'Rose', 'Tolentino', 'Villanueva', '2018-09-01', '1987-06-17', 'Lucena City, Quezon', 'Female', 'Single', 901, 'Marketing Manager II', 9, 3, 102, 'Regular', 'Full-time', 'avatar_f.jpg'),
(9003, 'MKT-003', 'Mark', 'Sarmiento', 'Castro', '2021-09-09', '1984-03-25', 'Lucena City, Quezon', 'Male', 'Single', 902, 'Marketing Supervisor I', 9, 4, 102, 'Regular', 'Full-time', 'avatar_m.jpg'),
(9004, 'MKT-004', 'Francis', 'Santiago', 'Rivera', '2021-03-19', '1988-05-15', 'Lucena City, Quezon', 'Male', 'Separated', 903, 'Marketing Supervisor II', 9, 4, 102, 'Regular', 'Full-time', 'avatar_m.jpg'),
(9006, 'MKT-006', 'Gloria', 'Tolentino', 'Perez', '2023-09-15', '2001-12-19', 'Lucena City, Quezon', 'Female', 'Widowed', 905, 'Marketing Staff I', 9, 5, 102, 'Regular', 'Full-time', 'avatar_f.jpg'),
(9007, 'MKT-007', 'Juan', 'Santos', 'Gonzales', '2024-09-15', '2000-09-09', 'Lucena City, Quezon', 'Male', 'Married', 906, 'Marketing Staff II', 9, 5, 102, 'Regular', 'Full-time', 'avatar_m.jpg'),

-- Probationary
(9005, 'MKT-005', 'Teresa', 'Pascual', 'Castro', '2023-05-12', '2001-09-18', 'Lucena City, Quezon', 'Female', 'Single', 904, 'Marketing Staff on Probation', 9, 5, 102, 'Probationary', 'Full-time', 'avatar_f.jpg'),
(9012, 'MKT-012', 'Jessica', 'Valenzuela', 'Gonzales', '2026-01-18', '2000-09-10', 'Lucena City, Quezon', 'Female', 'Married', 904, 'Marketing Staff on Probation', 9, 5, 102, 'Probationary', 'Full-time', 'avatar_f.jpg'),

-- OJT
(9008, 'MKT-008', 'Gloria', 'Pascual', 'Lopez', '2026-08-01', '1996-12-14', 'Lucena City, Quezon', 'Female', 'Separated', 905, 'Marketing Staff I', 9, 5, 102, 'OJT', 'Full-time', 'avatar_f.jpg'),
(9009, 'MKT-009', 'Ramon', 'De Leon', 'Garcia', '2026-08-01', '1995-03-03', 'Lucena City, Quezon', 'Male', 'Widowed', 905, 'Marketing Staff I', 9, 5, 102, 'OJT', 'Full-time', 'avatar_m.jpg'),

-- Trainee
(9010, 'MKT-010', 'Rhea', 'Aquino', 'Ocampo', '2026-08-10', '1998-10-10', 'Lucena City, Quezon', 'Female', 'Widowed', 905, 'Marketing Staff I', 9, 5, 102, 'Trainee', 'Full-time', 'avatar_f.jpg'),
(9011, 'MKT-011', 'Rose', 'Castillo', 'Bautista', '2026-08-10', '2002-09-05', 'Lucena City, Quezon', 'Female', 'Separated', 905, 'Marketing Staff I', 9, 5, 102, 'Trainee', 'Full-time', 'avatar_f.jpg'),

-- Project Based
(9013, 'MKT-013', 'Leonora', 'Bautista', 'Gomez', '2026-08-01', '2002-04-26', 'Lucena City, Quezon', 'Female', 'Separated', 905, 'Marketing Staff I', 9, 5, 102, 'Project Based', 'Full-time', 'avatar_f.jpg'),
(9014, 'MKT-014', 'Stephen', 'Mendoza', 'Garcia', '2026-08-01', '1998-03-27', 'Lucena City, Quezon', 'Male', 'Widowed', 905, 'Marketing Staff I', 9, 5, 102, 'Project Based', 'Full-time', 'avatar_m.jpg');

REPLACE INTO employee_contacts (employee_id, personal_email, mobile_number, telephone_number) VALUES
(9001, 'manuel.valenzuela@example.com', '09175639031', '888-9001'),
(9002, 'rose.tolentino@example.com', '09175418274', '888-9002'),
(9003, 'mark.sarmiento@example.com', '09177675310', '888-9003'),
(9004, 'francis.santiago@example.com', '09173200150', '888-9004'),
(9005, 'teresa.pascual@example.com', '09171332781', '888-9005'),
(9006, 'gloria.tolentino@example.com', '09177945583', '888-9006'),
(9007, 'juan.santos@example.com', '09173266851', '888-9007'),
(9008, 'gloria.pascual@example.com', '09175928582', '888-9008'),
(9009, 'ramon.de leon@example.com', '09177423230', '888-9009'),
(9010, 'rhea.aquino@example.com', '09173057514', '888-9010'),
(9011, 'rose.castillo@example.com', '09177680015', '888-9011'),
(9012, 'jessica.valenzuela@example.com', '09173100009', '888-9012'),
(9013, 'leonora.bautista@example.com', '09172224284', '888-9013'),
(9014, 'stephen.mendoza@example.com', '09174184887', '888-9014');

REPLACE INTO employee_details (employee_id, height_m, weight_kg, blood_type, citizenship) VALUES
(9001, 1.59, 80.7, 'AB+', 'Filipino'),
(9002, 1.6, 51.2, 'A-', 'Filipino'),
(9003, 1.59, 67.6, 'O+', 'Filipino'),
(9004, 1.82, 71.3, 'O+', 'Filipino'),
(9005, 1.65, 59.9, 'A-', 'Filipino'),
(9006, 1.57, 77.0, 'A+', 'Filipino'),
(9007, 1.82, 71.7, 'A+', 'Filipino'),
(9008, 1.53, 69.1, 'O+', 'Filipino'),
(9009, 1.58, 70.8, 'B-', 'Filipino'),
(9010, 1.74, 84.9, 'AB+', 'Filipino'),
(9011, 1.71, 48.4, 'A-', 'Filipino'),
(9012, 1.8, 55.0, 'A-', 'Filipino'),
(9013, 1.78, 61.2, 'AB-', 'Filipino'),
(9014, 1.61, 68.1, 'B-', 'Filipino');

REPLACE INTO employee_family (employee_id, member_type, surname, first_name, middle_name, occupation) VALUES
(9001, 'Father', 'Bautista', 'Anthony', 'Soriano', 'Retired'),
(9001, 'Mother', 'Aquino', 'Maria', 'Aquino', 'Homemaker'),
(9002, 'Father', 'Soriano', 'Eduardo', 'Castro', 'Retired'),
(9002, 'Mother', 'Cruz', 'Elena', 'Aquino', 'Homemaker'),
(9003, 'Father', 'Villanueva', 'Francis', 'Salvador', 'Retired'),
(9003, 'Mother', 'Fernandez', 'Ana', 'Gonzales', 'Homemaker'),
(9004, 'Father', 'Ramos', 'Ronald', 'Salvador', 'Retired'),
(9004, 'Mother', 'Gomez', 'Bernadette', 'Perez', 'Homemaker'),
(9005, 'Father', 'Santiago', 'Santiago', 'Soriano', 'Retired'),
(9005, 'Mother', 'Tolentino', 'Divina', 'Salvador', 'Homemaker'),
(9006, 'Father', 'Ramos', 'Eduardo', 'Garcia', 'Retired'),
(9006, 'Mother', 'De Leon', 'Cecilia', 'Reyes', 'Homemaker'),
(9007, 'Father', 'Sarmiento', 'Emilio', 'Torres', 'Retired'),
(9007, 'Mother', 'Pascual', 'Lourdes', 'Soriano', 'Homemaker'),
(9007, 'Spouse', 'Fernandez', 'Bernadette', 'Salvador', 'Office Employee'),
(9008, 'Father', 'Fernandez', 'Susan', 'Gonzales', 'Retired'),
(9008, 'Mother', 'Tolentino', 'Catherine', 'Garcia', 'Homemaker'),
(9009, 'Father', 'De Leon', 'Kevin', 'Cruz', 'Retired'),
(9009, 'Mother', 'Castillo', 'Gloria', 'Tolentino', 'Homemaker'),
(9010, 'Father', 'Tolentino', 'Jose', 'Valenzuela', 'Retired'),
(9010, 'Mother', 'Pascual', 'Patricia', 'Cruz', 'Homemaker'),
(9011, 'Father', 'Sarmiento', 'Elena', 'Rivera', 'Retired'),
(9011, 'Mother', 'Fernandez', 'Angelica', 'De Leon', 'Homemaker'),
(9012, 'Father', 'Bautista', 'Virginia', 'Fernandez', 'Retired'),
(9012, 'Mother', 'Soriano', 'Joshua', 'De Leon', 'Homemaker'),
(9013, 'Father', 'Tolentino', 'Arthur', 'Sarmiento', 'Retired'),
(9013, 'Mother', 'Evangelista', 'Corazon', 'Gonzales', 'Homemaker'),
(9014, 'Father', 'Lopez', 'Edward', 'Tolentino', 'Retired'),
(9014, 'Mother', 'Del Rosario', 'Catherine', 'Ramos', 'Homemaker');

REPLACE INTO employee_education (employee_id, education_level, school_name, degree_course, year_graduated) VALUES
(9001, 'College', 'Mapua University', 'BS Finance', '1998'),
(9002, 'College', 'Pamantasan ng Lungsod ng Maynila', 'BS Hotel and Restaurant Management', '2008'),
(9003, 'College', 'Mapua University', 'BS Information Technology', '2005'),
(9004, 'College', 'University of Santo Tomas', 'BS Information Technology', '2009'),
(9005, 'College', 'De La Salle University', 'BS Civil Engineering', '2022'),
(9006, 'College', 'University of Santo Tomas', 'BS Information Technology', '2022'),
(9007, 'College', 'Polytechnic University of the Philippines', 'BS Hotel and Restaurant Management', '2021'),
(9008, 'College', 'Southern Luzon State University', 'BS Business Administration', 'Present'),
(9009, 'College', 'Southern Luzon State University', 'BS Business Administration', 'Present'),
(9010, 'College', 'Southern Luzon State University', 'BS Business Administration', 'Present'),
(9011, 'College', 'Southern Luzon State University', 'BS Business Administration', 'Present'),
(9012, 'College', 'Southern Luzon State University', 'BS Business Administration', '2017'),
(9013, 'College', 'Southern Luzon State University', 'BS Business Administration', '2024'),
(9014, 'College', 'Southern Luzon State University', 'BS Business Administration', '2016');

REPLACE INTO employee_work_experience (employee_id, date_from, date_to, job_title, company_name, monthly_salary) VALUES
(9001, '2016-01-15', '2019-12-15', 'Previous I Role', 'Prime Logistics Co.', 36305),
(9002, '2014-01-15', '2017-12-15', 'Previous II Role', 'Metro Finance and Accounting', 25161),
(9003, '2017-01-15', '2020-12-15', 'Previous I Role', 'United Services Group', 21944),
(9004, '2017-01-15', '2020-12-15', 'Previous II Role', 'Global Retail Corp.', 33498),
(9005, '2019-01-15', '2022-12-15', 'Previous Probation Role', 'Metro Finance and Accounting', 28652),
(9006, '2019-01-15', '2022-12-15', 'Previous I Role', 'Pacific Marketing Group', 21375),
(9007, '2020-01-15', '2023-12-15', 'Previous II Role', 'United Services Group', 35683),
(9008, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 23758),
(9009, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 24502),
(9010, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 17236),
(9011, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 17194),
(9012, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 22792),
(9013, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 14353),
(9014, '2024-01-15', '2025-12-15', 'Previous Assistant', 'Local Retail Corp.', 18442);

REPLACE INTO employee_trainings (employee_id, training_title, conducted_by, no_of_hours) VALUES
(9001, 'Professional Ethics in Workplace', 'Corporate Training Dept', 16.0),
(9002, 'Financial Management and Tax Audits', 'Corporate Training Dept', 16.0),
(9003, 'Strategic HR & Talent Development', 'Corporate Training Dept', 16.0),
(9004, 'Advanced Management & Leadership', 'Corporate Training Dept', 16.0),
(9005, 'Strategic HR & Talent Development', 'Corporate Training Dept', 16.0),
(9006, 'Professional Ethics in Workplace', 'Corporate Training Dept', 16.0),
(9007, 'IT Infrastructure and Security', 'Corporate Training Dept', 16.0),
(9008, 'Workplace Orientation', 'Corporate Training Dept', 8.0),
(9009, 'Workplace Orientation', 'Corporate Training Dept', 8.0),
(9010, 'Workplace Orientation', 'Corporate Training Dept', 16.0),
(9011, 'Workplace Orientation', 'Corporate Training Dept', 16.0),
(9012, 'Workplace Orientation', 'Corporate Training Dept', 16.0),
(9013, 'Workplace Orientation', 'Corporate Training Dept', 16.0),
(9014, 'Workplace Orientation', 'Corporate Training Dept', 16.0);

REPLACE INTO employee_disclosures (employee_id, is_related_to_company, has_admin_offense, has_criminal_charge) VALUES
(9001, 0, 0, 0),
(9002, 0, 0, 0),
(9003, 0, 0, 0),
(9004, 0, 0, 0),
(9005, 0, 0, 0),
(9006, 0, 0, 0),
(9007, 0, 0, 0),
(9008, 0, 0, 0),
(9009, 0, 0, 0),
(9010, 0, 0, 0),
(9011, 0, 0, 0),
(9012, 0, 0, 0),
(9013, 0, 0, 0),
(9014, 0, 0, 0);

REPLACE INTO employee_government_ids (employee_id, sss_number, philhealth_number, pagibig_number, tin_number) VALUES
(9001, '74-5936726-5', '24-962152669-8', '5222-9813-5098', '810-553-486-000'),
(9002, '33-4190481-7', '35-352180476-3', '5348-4387-4755', '307-954-355-000'),
(9003, '50-1141240-0', '96-326293650-6', '2650-6331-6924', '727-982-365-000'),
(9004, '32-8071943-7', '27-777157408-8', '9748-6172-8972', '407-356-675-000'),
(9005, '67-2315459-6', '88-407652347-7', '3373-3745-6527', '531-746-727-000'),
(9006, '18-8938740-8', '16-128309105-6', '9584-2363-2702', '368-732-259-000'),
(9007, '21-8413185-4', '56-542600470-4', '3551-5973-1172', '100-635-934-000'),
(9008, '64-3795835-7', '54-580101819-0', '6776-8126-5498', '754-910-158-000'),
(9009, '19-3310140-8', '56-527385805-5', '5566-5089-2858', '126-853-290-000'),
(9010, '95-6828829-1', '80-682152066-4', '5914-3580-3852', '912-470-620-000'),
(9011, '92-2244282-7', '69-830261466-8', '6644-3102-3981', '886-232-543-000'),
(9012, '89-6664316-9', '15-986090690-0', '2330-1744-5336', '766-315-885-000'),
(9013, '50-6357240-5', '61-240409084-5', '9437-2739-6228', '347-577-225-000'),
(9014, '74-8671057-3', '37-726360971-5', '1797-1828-5609', '606-711-963-000');

REPLACE INTO employee_addresses (employee_id, address_type, region, barangay, city, province) VALUES
(9001, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 10', 'Candelaria', 'Quezon'),
(9001, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 10', 'Candelaria', 'Quezon'),
(9002, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 14', 'Pagbilao', 'Quezon'),
(9002, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 14', 'Pagbilao', 'Quezon'),
(9003, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 3', 'Pagbilao', 'Quezon'),
(9003, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 3', 'Pagbilao', 'Quezon'),
(9004, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 14', 'Pagbilao', 'Quezon'),
(9004, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 14', 'Pagbilao', 'Quezon'),
(9005, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 10', 'Candelaria', 'Quezon'),
(9005, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 10', 'Candelaria', 'Quezon'),
(9006, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 2', 'Candelaria', 'Quezon'),
(9006, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 2', 'Candelaria', 'Quezon'),
(9007, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 13', 'Tayabas City', 'Quezon'),
(9007, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 13', 'Tayabas City', 'Quezon'),
(9008, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 2', 'Pagbilao', 'Quezon'),
(9008, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 2', 'Pagbilao', 'Quezon'),
(9009, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 8', 'Candelaria', 'Quezon'),
(9009, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 8', 'Candelaria', 'Quezon'),
(9010, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 4', 'Lucena City', 'Quezon'),
(9010, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 4', 'Lucena City', 'Quezon'),
(9011, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 9', 'Lucena City', 'Quezon'),
(9011, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 9', 'Lucena City', 'Quezon'),
(9012, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 10', 'Pagbilao', 'Quezon'),
(9012, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 10', 'Pagbilao', 'Quezon'),
(9013, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 5', 'Pagbilao', 'Quezon'),
(9013, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 5', 'Pagbilao', 'Quezon'),
(9014, 'Residential', 'Region IV-A (CALABARZON)', 'Barangay 11', 'Pagbilao', 'Quezon'),
(9014, 'Permanent', 'Region IV-A (CALABARZON)', 'Barangay 11', 'Pagbilao', 'Quezon');

REPLACE INTO employee_emergency_contacts (employee_id, contact_name, relationship, contact_number) VALUES
(9001, 'Anthony Bautista', 'Father', '09185081447'),
(9002, 'Eduardo Soriano', 'Father', '09186973148'),
(9003, 'Francis Villanueva', 'Father', '09185409875'),
(9004, 'Ronald Ramos', 'Father', '09181800429'),
(9005, 'Santiago Santiago', 'Father', '09182196955'),
(9006, 'Eduardo Ramos', 'Father', '09186190378'),
(9007, 'Emilio Sarmiento', 'Father', '09186769431'),
(9008, 'Susan Fernandez', 'Father', '09175928582'),
(9009, 'Kevin De Leon', 'Father', '09177423230'),
(9010, 'Jose Tolentino', 'Father', '09173057514'),
(9011, 'Elena Sarmiento', 'Father', '09177680015'),
(9012, 'Virginia Bautista', 'Father', '09173100009'),
(9013, 'Arthur Tolentino', 'Father', '09172224284'),
(9014, 'Edward Lopez', 'Father', '09174184887');

REPLACE INTO employee_real_properties (employee_id, description, kind, acquisition_cost) VALUES
(9001, 'Residential House and Lot', 'Building and Land', 3088536.00),
(9002, 'Residential House and Lot', 'Building and Land', 1827146.00),
(9003, 'Residential House and Lot', 'Building and Land', 1997316.00),
(9004, 'Residential House and Lot', 'Building and Land', 2335509.00),
(9005, 'Residential House and Lot', 'Building and Land', 1665909.00),
(9006, 'Residential House and Lot', 'Building and Land', 1980994.00),
(9007, 'Residential House and Lot', 'Building and Land', 2101384.00),
(9008, 'Family Residence Share', 'Building and Land', 300000.0),
(9009, 'Family Residence Share', 'Building and Land', 300000.0),
(9010, 'Family Residence Share', 'Building and Land', 250000.0),
(9011, 'Family Residence Share', 'Building and Land', 250000.0),
(9012, 'Residential House and Lot', 'Building and Land', 1500000.0),
(9013, 'Family Residence Share', 'Building and Land', 250000.0),
(9014, 'Family Residence Share', 'Building and Land', 300000.0);

REPLACE INTO employee_personal_properties (employee_id, description, acquisition_cost) VALUES
(9001, 'Personal Effects and Savings', 278080.00),
(9002, 'Personal Effects and Savings', 155935.00),
(9003, 'Personal Effects and Savings', 241247.00),
(9004, 'Personal Effects and Savings', 151891.00),
(9005, 'Personal Effects and Savings', 184934.00),
(9006, 'Personal Effects and Savings', 229707.00),
(9007, 'Personal Effects and Savings', 345994.00),
(9008, 'Personal Effects and Savings', 82251),
(9009, 'Personal Effects and Savings', 88619),
(9010, 'Personal Effects and Savings', 33202),
(9011, 'Personal Effects and Savings', 82908),
(9012, 'Personal Effects and Savings', 98753),
(9013, 'Personal Effects and Savings', 33465),
(9014, 'Personal Effects and Savings', 85345);

REPLACE INTO employee_liabilities (employee_id, nature_of_liability, creditor_name, outstanding_balance) VALUES
(9001, 'Personal Loan', 'Bank', 130284.00),
(9002, 'Personal Loan', 'Bank', 41425.00),
(9003, 'Personal Loan', 'Bank', 133560.00),
(9004, 'Personal Loan', 'Bank', 52819.00),
(9005, 'Personal Loan', 'Bank', 100079.00),
(9006, 'Personal Loan', 'Bank', 84494.00),
(9007, 'Personal Loan', 'Bank', 12180.00),
(9008, 'Personal Loan', 'Bank', 49525),
(9009, 'Personal Loan', 'Bank', 12717),
(9010, 'Personal Loan', 'Bank', 20514),
(9011, 'Personal Loan', 'Bank', 15028),
(9012, 'Personal Loan', 'Bank', 6984),
(9013, 'Personal Loan', 'Bank', 11344),
(9014, 'Personal Loan', 'Bank', 5535);

REPLACE INTO employee_references (employee_id, reference_name, reference_address, reference_telephone) VALUES
(9001, 'Reference Edward Gonzales', 'Quezon Province', '09201458868'),
(9002, 'Reference Anthony Reyes', 'Quezon Province', '09209265544'),
(9003, 'Reference Santiago Torres', 'Quezon Province', '09207139188'),
(9004, 'Reference Jose Santiago', 'Quezon Province', '09205266272'),
(9005, 'Reference Michael Torres', 'Quezon Province', '09206316334'),
(9006, 'Reference David Tolentino', 'Quezon Province', '09203120836'),
(9007, 'Reference Ricardo Valenzuela', 'Quezon Province', '09209253243'),
(9008, 'Reference Susan Fernandez', 'Quezon Province', '09175928582'),
(9009, 'Reference Kevin De Leon', 'Quezon Province', '09177423230'),
(9010, 'Reference Jose Tolentino', 'Quezon Province', '09173057514'),
(9011, 'Reference Elena Sarmiento', 'Quezon Province', '09177680015'),
(9012, 'Reference Virginia Bautista', 'Quezon Province', '09173100009'),
(9013, 'Reference Arthur Tolentino', 'Quezon Province', '09172224284'),
(9014, 'Reference Edward Lopez', 'Quezon Province', '09174184887');

SET FOREIGN_KEY_CHECKS = 1;
