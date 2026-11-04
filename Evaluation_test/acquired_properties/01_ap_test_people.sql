-- Acquired Properties test people. Run once on a fresh test database.
-- First import the schema and organization files shown in README.md.
USE raquel_hris;

-- The CEO is needed to create/review the VP form. The CEO is outside the AP cycle.
INSERT INTO employees
  (employee_id,employee_code,first_name,middle_name,last_name,name_extension,
   date_of_birth,place_of_birth,gender,civil_status,hire_date,job_title,
   job_title_id,department_id,rank_category_id,branch_id,reports_to,
   employment_status,employment_type,contract_start_date,contract_end_date,
   profile_picture,is_active)
VALUES
  (99100,'OP-210','Isabella','Reyes','Villanueva','','1978-06-18',
   'Lucena City, Quezon','Female','Married','2007-04-02',
   'President and CEO',1100,10,1,102,NULL,'Regular','Full-time',
   '2007-04-02','2030-12-31','avatar_f.jpg',1),
  (99101,'AP-150','Marisol','Cruz','Santos','','1982-02-15',
   'Lucena City, Quezon','Female','Married','2012-06-04',
   'VP for Acquired Properties',100,1,1,102,99100,'Regular','Full-time',
   '2012-06-04','2030-12-31','avatar_f.jpg',1),
  (99102,'AP-735','Adrian','Lopez','Reyes','','1988-05-21',
   'Tayabas City, Quezon','Male','Married','2016-08-01',
   'AP Manager I',101,1,3,102,99101,'Regular','Full-time',
   '2016-08-01','2030-12-31','avatar_m.jpg',1),
  (99103,'AP-936','Nathan','Garcia','Cruz','','1993-09-08',
   'Lucena City, Quezon','Male','Single','2020-03-02',
   'AP Supervisor I',105,1,4,102,99102,'Regular','Full-time',
   '2020-03-02','2030-12-31','avatar_m.jpg',1),
  (99104,'AP-933','Bianca','Mendoza','Garcia','','1998-07-18',
   'Pagbilao, Quezon','Female','Single','2023-05-08',
   'AP Staff I',108,1,5,102,99103,'Regular','Full-time',
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
  (99105,'AP-480','Lea','Santos','Navarro','','2002-03-12',
   'Lucena City, Quezon','Female','Single','2026-09-01',
   'AP Staff I',108,1,5,102,99103,'Probationary','Initial','2026-11-30',
   'Full-time','2026-09-01','2027-02-28','avatar_f.jpg',1),
  (99106,'AP-931','Marco','Ramos','Dela Cruz','','2001-11-25',
   'Sariaya, Quezon','Male','Single','2026-10-01',
   'AP Staff I',108,1,5,102,99103,'Probationary','Initial','2026-12-15',
   'Full-time','2026-10-01','2027-03-31','avatar_m.jpg',1);

-- Local test password for every account: password.
INSERT INTO users
  (employee_id,username,email,full_name,password_hash,role,branch_id,
   is_active,account_hold,first_login_completed,must_change_password)
VALUES
  (99100,'test.ap.ceo','ap.ceo@example.invalid','Isabella Villanueva',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','President and CEO',102,1,0,0,1),
  (99101,'test.ap.vp','ap.vp@example.invalid','Marisol Santos',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99102,'test.ap.manager','ap.manager@example.invalid','Adrian Reyes',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99103,'test.ap.supervisor','ap.supervisor@example.invalid','Nathan Cruz',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99104,'test.ap.staff','ap.staff@example.invalid','Bianca Garcia',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99105,'test.ap.lea','ap.lea@example.invalid','Lea Navarro',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99106,'test.ap.marco','ap.marco@example.invalid','Marco Dela Cruz',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1);

INSERT INTO employee_contacts (employee_id,personal_email,mobile_number,telephone_number)
VALUES
  (99100,'ap.ceo@example.invalid','09179910000','042-991-0000'),
  (99101,'ap.vp@example.invalid','09179910001','042-991-0001'),
  (99102,'ap.manager@example.invalid','09179910002','042-991-0002'),
  (99103,'ap.supervisor@example.invalid','09179910003','042-991-0003'),
  (99104,'ap.staff@example.invalid','09179910004','042-991-0004'),
  (99105,'ap.lea@example.invalid','09179910005','042-991-0005'),
  (99106,'ap.marco@example.invalid','09179910006','042-991-0006');

INSERT INTO employee_details (employee_id,height_m,weight_kg,blood_type,citizenship)
VALUES (99100,1.64,59.00,'O+','Filipino'),(99101,1.62,57.00,'A+','Filipino'),
       (99102,1.73,70.00,'B+','Filipino'),(99103,1.71,68.00,'O+','Filipino'),
       (99104,1.59,53.00,'A+','Filipino'),(99105,1.60,55.00,'B+','Filipino'),
       (99106,1.74,69.00,'O+','Filipino');

INSERT INTO employee_government_ids (employee_id,sss_number,philhealth_number,pagibig_number,tin_number)
VALUES
  (99100,'MOCK-SSS-99100','MOCK-PH-99100','MOCK-PI-99100','MOCK-TIN-99100'),
  (99101,'MOCK-SSS-99101','MOCK-PH-99101','MOCK-PI-99101','MOCK-TIN-99101'),
  (99102,'MOCK-SSS-99102','MOCK-PH-99102','MOCK-PI-99102','MOCK-TIN-99102'),
  (99103,'MOCK-SSS-99103','MOCK-PH-99103','MOCK-PI-99103','MOCK-TIN-99103'),
  (99104,'MOCK-SSS-99104','MOCK-PH-99104','MOCK-PI-99104','MOCK-TIN-99104'),
  (99105,'MOCK-SSS-99105','MOCK-PH-99105','MOCK-PI-99105','MOCK-TIN-99105'),
  (99106,'MOCK-SSS-99106','MOCK-PH-99106','MOCK-PI-99106','MOCK-TIN-99106');

INSERT INTO employee_addresses
  (employee_id,address_type,region,house_no,street,subdivision,barangay,city,province,zip_code)
VALUES
  (99100,'Residential','CALABARZON','10','Quezon Avenue','City Proper','Barangay 1','Lucena City','Quezon','4301'),
  (99100,'Permanent','CALABARZON','10','Quezon Avenue','City Proper','Barangay 1','Lucena City','Quezon','4301'),
  (99101,'Residential','CALABARZON','11','Quezon Avenue','City Proper','Barangay 2','Lucena City','Quezon','4301'),
  (99101,'Permanent','CALABARZON','11','Quezon Avenue','City Proper','Barangay 2','Lucena City','Quezon','4301'),
  (99102,'Residential','CALABARZON','12','Rizal Street','City Proper','Barangay 3','Tayabas City','Quezon','4327'),
  (99102,'Permanent','CALABARZON','12','Rizal Street','City Proper','Barangay 3','Tayabas City','Quezon','4327'),
  (99103,'Residential','CALABARZON','13','Quezon Avenue','City Proper','Barangay 4','Lucena City','Quezon','4301'),
  (99103,'Permanent','CALABARZON','13','Quezon Avenue','City Proper','Barangay 4','Lucena City','Quezon','4301'),
  (99104,'Residential','CALABARZON','14','National Road','Town Proper','Barangay 5','Pagbilao','Quezon','4302'),
  (99104,'Permanent','CALABARZON','14','National Road','Town Proper','Barangay 5','Pagbilao','Quezon','4302'),
  (99105,'Residential','CALABARZON','15','Quezon Avenue','City Proper','Barangay 6','Lucena City','Quezon','4301'),
  (99105,'Permanent','CALABARZON','15','Quezon Avenue','City Proper','Barangay 6','Lucena City','Quezon','4301'),
  (99106,'Residential','CALABARZON','16','Rizal Street','Town Proper','Barangay 7','Sariaya','Quezon','4322'),
  (99106,'Permanent','CALABARZON','16','Rizal Street','Town Proper','Barangay 7','Sariaya','Quezon','4322');

INSERT INTO employee_emergency_contacts
  (employee_id,contact_name,relationship,contact_number,is_primary)
VALUES (99100,'Linda Reyes','Mother','09179911000',1),
       (99101,'Carmen Cruz','Mother','09179911001',1),
       (99102,'Rosa Lopez','Mother','09179911002',1),
       (99103,'Ana Garcia','Mother','09179911003',1),
       (99104,'Lina Mendoza','Mother','09179911004',1),
       (99105,'Maria Santos','Mother','09179911005',1),
       (99106,'Jose Ramos','Father','09179911006',1);

INSERT INTO employee_family (employee_id,member_type,surname,first_name,middle_name,occupation)
VALUES (99100,'Mother','Reyes','Linda','Garcia','Retired clerk'),
       (99101,'Mother','Cruz','Carmen','Lopez','Teacher'),
       (99102,'Mother','Lopez','Rosa','Reyes','Store owner'),
       (99103,'Mother','Garcia','Ana','Santos','Nurse'),
       (99104,'Mother','Mendoza','Lina','Cruz','Teacher'),
       (99105,'Mother','Santos','Maria','Reyes','Store owner'),
       (99106,'Father','Ramos','Jose','Dela Cruz','Mechanic');

INSERT INTO employee_education
  (employee_id,education_level,school_name,degree_course,period_from,period_to,year_graduated,honors_received)
VALUES
  (99100,'College','University of the Philippines','BS Psychology','1995-06-01','1999-04-30','1999','None'),
  (99101,'College','Southern Luzon State University','BS Business Administration','1999-06-01','2003-05-31','2003','None'),
  (99102,'College','Southern Luzon State University','BS Management','2005-06-01','2009-05-31','2009','None'),
  (99103,'College','Southern Luzon State University','BS Management','2010-06-01','2014-05-31','2014','None'),
  (99104,'College','Southern Luzon State University','BS Business Administration','2015-06-01','2019-05-31','2019','None'),
  (99105,'College','Southern Luzon State University','BS Business Administration','2019-06-01','2023-05-31','2023','None'),
  (99106,'College','Southern Luzon State University','BS Business Administration','2018-06-01','2022-05-31','2022','None');

INSERT INTO employee_work_experience
  (employee_id,date_from,date_to,job_title,company_name,monthly_salary,appointment_status,reason_for_leaving)
VALUES
  (99100,'1999-07-01','2007-03-31','Operations Lead','Mock Quezon Retail Co.',40000,'Full-time','Career advancement'),
  (99101,'2004-06-01','2012-05-31','Property Coordinator','Mock Quezon Realty Co.',32000,'Full-time','Career advancement'),
  (99102,'2010-06-01','2016-07-31','Property Officer','Mock Tayabas Realty Co.',28000,'Full-time','Career advancement'),
  (99103,'2015-06-01','2020-02-29','Property Assistant','Mock Lucena Services Co.',24000,'Full-time','Career advancement'),
  (99104,'2019-07-01','2023-04-30','Administrative Assistant','Mock Pagbilao Services Co.',21000,'Full-time','Career advancement'),
  (99105,'2023-07-01','2026-08-15','Records Assistant','Mock Lucena Services Co.',22000,'Full-time','Career advancement'),
  (99106,'2022-07-01','2026-09-15','Office Assistant','Mock Sariaya Services Co.',21000,'Full-time','Career advancement');

INSERT INTO employee_disclosures
  (employee_id,is_related_to_company,has_admin_offense,has_criminal_charge,
   has_criminal_conviction,has_been_separated,is_pwd,is_solo_parent,
   has_recent_hospital,has_current_treatment)
VALUES (99100,0,0,0,0,0,0,0,0,0),(99101,0,0,0,0,0,0,0,0,0),
       (99102,0,0,0,0,0,0,0,0,0),(99103,0,0,0,0,0,0,0,0,0),
       (99104,0,0,0,0,0,0,0,0,0),(99105,0,0,0,0,0,0,0,0,0),
       (99106,0,0,0,0,0,0,0,0,0);

INSERT INTO employee_trainings
  (employee_id,date_from,date_to,training_title,training_type,no_of_hours,conducted_by)
VALUES
  (99100,'2025-03-01','2025-03-02','Leadership Review','Internal',16,'Mock Training Team'),
  (99101,'2025-04-01','2025-04-02','Property Portfolio Planning','Internal',16,'Mock Training Team'),
  (99102,'2025-05-01','2025-05-02','Acquisition Case Review','Internal',16,'Mock Training Team'),
  (99103,'2025-06-01','2025-06-02','Property Records Control','Internal',16,'Mock Training Team'),
  (99104,'2025-07-01','2025-07-02','Property Inventory Basics','Internal',16,'Mock Training Team'),
  (99105,'2026-09-15','2026-09-16','AP Records Orientation','Internal',16,'Mock Training Team'),
  (99106,'2026-10-12','2026-10-13','AP Records Orientation','Internal',16,'Mock Training Team');

INSERT INTO employee_skills (employee_id,skill_name)
VALUES (99100,'Executive leadership'),(99101,'Portfolio planning'),
       (99102,'Acquisition case review'),(99103,'Property records control'),
       (99104,'Property inventory'),(99105,'Document tracking'),
       (99106,'Records organization');

INSERT INTO employee_references
  (employee_id,reference_name,reference_address,reference_telephone)
VALUES (99100,'Luz Bautista','Lucena City, Quezon','042-991-0100'),
       (99101,'Rosa Villanueva','Lucena City, Quezon','042-991-0101'),
       (99102,'Maria Castillo','Tayabas City, Quezon','042-991-0102'),
       (99103,'Jose Mendoza','Lucena City, Quezon','042-991-0103'),
       (99104,'Ana Ramos','Pagbilao, Quezon','042-991-0104'),
       (99105,'Lina Navarro','Lucena City, Quezon','042-991-0105'),
       (99106,'Ramon Dela Cruz','Sariaya, Quezon','042-991-0106');
