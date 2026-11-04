-- Compliance test people. Run once on a fresh test database.
-- First import the schema and organization files shown in README.md.
USE raquel_hris;

-- The CEO is needed to create/review the Compliance Supervisor I form. The CEO is outside the Compliance cycle.
INSERT INTO employees
  (employee_id,employee_code,first_name,middle_name,last_name,name_extension,
   date_of_birth,place_of_birth,gender,civil_status,hire_date,job_title,
   job_title_id,department_id,rank_category_id,branch_id,reports_to,
   employment_status,employment_type,contract_start_date,contract_end_date,
   profile_picture,is_active)
VALUES
  (99400,'OP-469','Isabella','Reyes','Villanueva','','1978-06-18',
   'Lucena City, Quezon','Female','Married','2007-04-02',
   'President and CEO',1100,10,1,102,NULL,'Regular','Full-time',
   '2007-04-02','2030-12-31','avatar_f.jpg',1),
  (99401,'CMP-901','Michelle','Cruz','Santos','','1982-02-15',
   'Lucena City, Quezon','Female','Married','2012-06-04',
   'Compliance Supervisor I',400,4,4,102,99400,'Regular','Full-time',
   '2012-06-04','2030-12-31','avatar_f.jpg',1),
  (99402,'CMP-412','Victor','Lopez','Reyes','','1988-05-21',
   'Tayabas City, Quezon','Male','Married','2016-08-01',
   'Compliance Supervisor II',401,4,4,102,99401,'Regular','Full-time',
   '2016-08-01','2030-12-31','avatar_m.jpg',1),
  (99403,'CMP-235','Daniel','Garcia','Cruz','','1993-09-08',
   'Lucena City, Quezon','Male','Single','2020-03-02',
   'Compliance Staff I',403,4,5,102,99402,'Regular','Full-time',
   '2020-03-02','2030-12-31','avatar_m.jpg',1),
  (99404,'CMP-495','Rosario','Mendoza','Garcia','','1998-07-18',
   'Pagbilao, Quezon','Female','Single','2023-05-08',
   'Compliance Staff II',404,4,5,102,99402,'Regular','Full-time',
   '2023-05-08','2030-12-31','avatar_f.jpg',1);

-- Two recent hires for separate probationary Initial and Final tests.
INSERT INTO employees
  (employee_id,employee_code,first_name,middle_name,last_name,name_extension,
   date_of_birth,place_of_birth,gender,civil_status,hire_date,job_title,
   job_title_id,department_id,rank_category_id,branch_id,reports_to,
   employment_status,probationary_next_evaluation_type,
   probationary_next_evaluation_due_date,employment_type,contract_start_date,
   contract_end_date,profile_picture,is_active)
VALUES
  (99405,'CMP-566','Carla','Santos','Navarro','','2002-03-12',
   'Lucena City, Quezon','Female','Single','2026-09-01',
   'Compliance Staff I',403,4,5,102,99402,'Probationary','Initial','2026-11-30',
   'Full-time','2026-09-01','2027-02-28','avatar_f.jpg',1),
  (99406,'CMP-588','George','Ramos','Dela Cruz','','2001-11-25',
   'Sariaya, Quezon','Male','Single','2026-10-01',
   'Compliance Staff I',403,4,5,102,99402,'Probationary','Initial','2026-12-15',
   'Full-time','2026-10-01','2027-03-31','avatar_m.jpg',1);

-- Local test password for every account: password.
INSERT INTO users
  (employee_id,username,email,full_name,password_hash,role,branch_id,
   is_active,account_hold,first_login_completed,must_change_password)
VALUES
  (99400,'test.compliance.ceo','compliance.ceo@example.invalid','Isabella Villanueva',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','President and CEO',102,1,0,0,1),
  (99401,'test.compliance.supervisor1','compliance.supervisor1@example.invalid','Michelle Santos',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99402,'test.compliance.supervisor2','compliance.supervisor2@example.invalid','Victor Reyes',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99403,'test.compliance.staff1','compliance.staff1@example.invalid','Daniel Cruz',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99404,'test.compliance.staff2','compliance.staff2@example.invalid','Rosario Garcia',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99405,'test.compliance.carla','compliance.carla@example.invalid','Carla Navarro',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99406,'test.compliance.george','compliance.george@example.invalid','George Dela Cruz',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1);

INSERT INTO employee_contacts (employee_id,personal_email,mobile_number,telephone_number)
VALUES
  (99400,'compliance.ceo@example.invalid','09179940000','042-991-0000'),
  (99401,'compliance.supervisor1@example.invalid','09179940001','042-991-0001'),
  (99402,'compliance.supervisor2@example.invalid','09179940002','042-991-0002'),
  (99403,'compliance.staff1@example.invalid','09179940003','042-991-0003'),
  (99404,'compliance.staff2@example.invalid','09179940004','042-991-0004'),
  (99405,'compliance.carla@example.invalid','09179940005','042-991-0005'),
  (99406,'compliance.george@example.invalid','09179940006','042-991-0006');

INSERT INTO employee_details (employee_id,height_m,weight_kg,blood_type,citizenship)
VALUES (99400,1.64,59.00,'O+','Filipino'),(99401,1.62,57.00,'A+','Filipino'),
       (99402,1.73,70.00,'B+','Filipino'),(99403,1.71,68.00,'O+','Filipino'),
       (99404,1.59,53.00,'A+','Filipino'),(99405,1.60,55.00,'B+','Filipino'),
       (99406,1.74,69.00,'O+','Filipino');

INSERT INTO employee_government_ids (employee_id,sss_number,philhealth_number,pagibig_number,tin_number)
VALUES
  (99400,'MOCK-SSS-99400','MOCK-PH-99400','MOCK-PI-99400','MOCK-TIN-99400'),
  (99401,'MOCK-SSS-99401','MOCK-PH-99401','MOCK-PI-99401','MOCK-TIN-99401'),
  (99402,'MOCK-SSS-99402','MOCK-PH-99402','MOCK-PI-99402','MOCK-TIN-99402'),
  (99403,'MOCK-SSS-99403','MOCK-PH-99403','MOCK-PI-99403','MOCK-TIN-99403'),
  (99404,'MOCK-SSS-99404','MOCK-PH-99404','MOCK-PI-99404','MOCK-TIN-99404'),
  (99405,'MOCK-SSS-99405','MOCK-PH-99405','MOCK-PI-99405','MOCK-TIN-99405'),
  (99406,'MOCK-SSS-99406','MOCK-PH-99406','MOCK-PI-99406','MOCK-TIN-99406');

INSERT INTO employee_addresses
  (employee_id,address_type,region,house_no,street,subdivision,barangay,city,province,zip_code)
VALUES
  (99400,'Residential','CALABARZON','10','Quezon Avenue','City Proper','Barangay 1','Lucena City','Quezon','4301'),
  (99400,'Permanent','CALABARZON','10','Quezon Avenue','City Proper','Barangay 1','Lucena City','Quezon','4301'),
  (99401,'Residential','CALABARZON','11','Quezon Avenue','City Proper','Barangay 2','Lucena City','Quezon','4301'),
  (99401,'Permanent','CALABARZON','11','Quezon Avenue','City Proper','Barangay 2','Lucena City','Quezon','4301'),
  (99402,'Residential','CALABARZON','12','Rizal Street','City Proper','Barangay 3','Tayabas City','Quezon','4327'),
  (99402,'Permanent','CALABARZON','12','Rizal Street','City Proper','Barangay 3','Tayabas City','Quezon','4327'),
  (99403,'Residential','CALABARZON','13','Quezon Avenue','City Proper','Barangay 4','Lucena City','Quezon','4301'),
  (99403,'Permanent','CALABARZON','13','Quezon Avenue','City Proper','Barangay 4','Lucena City','Quezon','4301'),
  (99404,'Residential','CALABARZON','14','National Road','Town Proper','Barangay 5','Pagbilao','Quezon','4302'),
  (99404,'Permanent','CALABARZON','14','National Road','Town Proper','Barangay 5','Pagbilao','Quezon','4302'),
  (99405,'Residential','CALABARZON','15','Quezon Avenue','City Proper','Barangay 6','Lucena City','Quezon','4301'),
  (99405,'Permanent','CALABARZON','15','Quezon Avenue','City Proper','Barangay 6','Lucena City','Quezon','4301'),
  (99406,'Residential','CALABARZON','16','Rizal Street','Town Proper','Barangay 7','Sariaya','Quezon','4322'),
  (99406,'Permanent','CALABARZON','16','Rizal Street','Town Proper','Barangay 7','Sariaya','Quezon','4322');

INSERT INTO employee_emergency_contacts
  (employee_id,contact_name,relationship,contact_number,is_primary)
VALUES (99400,'Linda Reyes','Mother','09179911000',1),
       (99401,'Carmen Cruz','Mother','09179911001',1),
       (99402,'Rosa Lopez','Mother','09179911002',1),
       (99403,'Ana Garcia','Mother','09179911003',1),
       (99404,'Lina Mendoza','Mother','09179911004',1),
       (99405,'Maria Santos','Mother','09179911005',1),
       (99406,'Jose Ramos','Father','09179911006',1);

INSERT INTO employee_family (employee_id,member_type,surname,first_name,middle_name,occupation)
VALUES (99400,'Mother','Reyes','Linda','Garcia','Retired clerk'),
       (99401,'Mother','Cruz','Carmen','Lopez','Teacher'),
       (99402,'Mother','Lopez','Rosa','Reyes','Store owner'),
       (99403,'Mother','Garcia','Ana','Santos','Nurse'),
       (99404,'Mother','Mendoza','Lina','Cruz','Teacher'),
       (99405,'Mother','Santos','Maria','Reyes','Store owner'),
       (99406,'Father','Ramos','Jose','Dela Cruz','Mechanic');

INSERT INTO employee_education
  (employee_id,education_level,school_name,degree_course,period_from,period_to,year_graduated,honors_received)
VALUES
  (99400,'College','University of the Philippines','BS Psychology','1995-06-01','1999-04-30','1999','None'),
  (99401,'College','Southern Luzon State University','BS Business Administration','1999-06-01','2003-05-31','2003','None'),
  (99402,'College','Southern Luzon State University','BS Management','2005-06-01','2009-05-31','2009','None'),
  (99403,'College','Southern Luzon State University','BS Management','2010-06-01','2014-05-31','2014','None'),
  (99404,'College','Southern Luzon State University','BS Business Administration','2015-06-01','2019-05-31','2019','None'),
  (99405,'College','Southern Luzon State University','BS Business Administration','2019-06-01','2023-05-31','2023','None'),
  (99406,'College','Southern Luzon State University','BS Business Administration','2018-06-01','2022-05-31','2022','None');

INSERT INTO employee_work_experience
  (employee_id,date_from,date_to,job_title,company_name,monthly_salary,appointment_status,reason_for_leaving)
VALUES
  (99400,'1999-07-01','2007-03-31','Operations Lead','Mock Quezon Retail Co.',40000,'Full-time','Career advancement'),
  (99401,'2004-06-01','2012-05-31','Property Coordinator','Mock Quezon Realty Co.',32000,'Full-time','Career advancement'),
  (99402,'2010-06-01','2016-07-31','Property Officer','Mock Tayabas Realty Co.',28000,'Full-time','Career advancement'),
  (99403,'2015-06-01','2020-02-29','Property Assistant','Mock Lucena Services Co.',24000,'Full-time','Career advancement'),
  (99404,'2019-07-01','2023-04-30','Administrative Assistant','Mock Pagbilao Services Co.',21000,'Full-time','Career advancement'),
  (99405,'2023-07-01','2026-08-15','Records Assistant','Mock Lucena Services Co.',22000,'Full-time','Career advancement'),
  (99406,'2022-07-01','2026-09-15','Office Assistant','Mock Sariaya Services Co.',21000,'Full-time','Career advancement');

INSERT INTO employee_disclosures
  (employee_id,is_related_to_company,has_admin_offense,has_criminal_charge,
   has_criminal_conviction,has_been_separated,is_pwd,is_solo_parent,
   has_recent_hospital,has_current_treatment)
VALUES (99400,0,0,0,0,0,0,0,0,0),(99401,0,0,0,0,0,0,0,0,0),
       (99402,0,0,0,0,0,0,0,0,0),(99403,0,0,0,0,0,0,0,0,0),
       (99404,0,0,0,0,0,0,0,0,0),(99405,0,0,0,0,0,0,0,0,0),
       (99406,0,0,0,0,0,0,0,0,0);

INSERT INTO employee_trainings
  (employee_id,date_from,date_to,training_title,training_type,no_of_hours,conducted_by)
VALUES
  (99400,'2025-03-01','2025-03-02','Leadership Review','Internal',16,'Mock Training Team'),
  (99401,'2025-04-01','2025-04-02','Compliance Planning','Internal',16,'Mock Training Team'),
  (99402,'2025-05-01','2025-05-02','Compliance Case Review','Internal',16,'Mock Training Team'),
  (99403,'2025-06-01','2025-06-02','Compliance Records Control','Internal',16,'Mock Training Team'),
  (99404,'2025-07-01','2025-07-02','Compliance Evidence Basics','Internal',16,'Mock Training Team'),
  (99405,'2026-09-15','2026-09-16','Compliance Records Orientation','Internal',16,'Mock Training Team'),
  (99406,'2026-10-12','2026-10-13','Compliance Records Orientation','Internal',16,'Mock Training Team');

INSERT INTO employee_skills (employee_id,skill_name)
VALUES (99400,'Executive leadership'),(99401,'Compliance planning'),
       (99402,'Compliance case review'),(99403,'Compliance records control'),
       (99404,'Compliance evidence review'),(99405,'Document tracking'),
       (99406,'Records organization');

INSERT INTO employee_references
  (employee_id,reference_name,reference_address,reference_telephone)
VALUES (99400,'Luz Bautista','Lucena City, Quezon','042-991-0100'),
       (99401,'Rosa Villanueva','Lucena City, Quezon','042-991-0101'),
       (99402,'Maria Castillo','Tayabas City, Quezon','042-991-0102'),
       (99403,'Jose Mendoza','Lucena City, Quezon','042-991-0103'),
       (99404,'Ana Ramos','Pagbilao, Quezon','042-991-0104'),
       (99405,'Lina Navarro','Lucena City, Quezon','042-991-0105'),
       (99406,'Ramon Dela Cruz','Sariaya, Quezon','042-991-0106');
