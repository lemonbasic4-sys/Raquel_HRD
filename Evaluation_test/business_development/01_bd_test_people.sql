-- Business Development test people. Run once on a fresh test database.
-- First import the schema and organization files shown in README.md.
USE raquel_hris;

-- The CEO creates and reviews the Business Development Officer's form.
-- The CEO does not join the Business Development cycle.
INSERT INTO employees
  (employee_id,employee_code,first_name,middle_name,last_name,name_extension,
   date_of_birth,place_of_birth,gender,civil_status,hire_date,job_title,
   job_title_id,department_id,rank_category_id,branch_id,reports_to,
   employment_status,employment_type,contract_start_date,contract_end_date,
   profile_picture,is_active)
VALUES
  (99300,'OP-542','Isabella','Reyes','Villanueva','','1978-06-18',
   'Lucena City, Quezon','Female','Married','2007-04-02',
   'President and CEO',1100,10,1,102,NULL,'Regular','Full-time',
   '2007-04-02','2030-12-31','avatar_f.jpg',1),
  (99301,'BD-751','Antonio','Gonzales','Dela Cruz','','1987-01-06',
   'Lucena City, Quezon','Male','Single','2017-05-02',
   'Business Development Officer I',300,3,3,102,99300,'Regular','Full-time',
   '2017-05-02','2030-12-31','avatar_m.jpg',1),
  (99302,'BD-726','Emilio','Salvador','Cruz','','1997-07-12',
   'Tayabas City, Quezon','Male','Single','2021-11-25',
   'Business Development Staff I',302,3,5,102,99301,'Regular','Full-time',
   '2021-11-25','2030-12-31','avatar_m.jpg',1),
  (99303,'BD-539','Rina','Santos','Torres','','1999-04-13',
   'Lucena City, Quezon','Female','Single','2023-07-03',
   'Business Development Staff II',303,3,5,102,99301,'Regular','Full-time',
   '2023-07-03','2030-12-31','avatar_f.jpg',1);

-- Two recent hires for separate probationary Initial and Final tests.
INSERT INTO employees
  (employee_id,employee_code,first_name,middle_name,last_name,name_extension,
   date_of_birth,place_of_birth,gender,civil_status,hire_date,job_title,
   job_title_id,department_id,rank_category_id,branch_id,reports_to,
   employment_status,probationary_next_evaluation_type,
   probationary_next_evaluation_due_date,employment_type,contract_start_date,
   contract_end_date,profile_picture,is_active)
VALUES
  (99304,'BD-206','Celia','Reyes','Navarro','','2002-03-12',
   'Lucena City, Quezon','Female','Single','2026-09-01',
   'Business Development Staff I',302,3,5,102,99301,'Probationary',
   'Initial','2026-11-30','Full-time','2026-09-01','2027-02-28','avatar_f.jpg',1),
  (99305,'BD-239','Paolo','Ramos','Castillo','','2001-11-25',
   'Sariaya, Quezon','Male','Single','2026-10-01',
   'Business Development Staff I',302,3,5,102,99301,'Probationary',
   'Initial','2026-12-15','Full-time','2026-10-01','2027-03-31','avatar_m.jpg',1);

-- Local test password for every account: password.
INSERT INTO users
  (employee_id,username,email,full_name,password_hash,role,branch_id,
   is_active,account_hold,first_login_completed,must_change_password)
VALUES
  (99300,'test.bd.ceo','bd.ceo@example.invalid','Isabella Villanueva',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','President and CEO',102,1,0,0,1),
  (99301,'test.bd.officer','bd.officer@example.invalid','Antonio Dela Cruz',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99302,'test.bd.staff1','bd.staff1@example.invalid','Emilio Cruz',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99303,'test.bd.staff2','bd.staff2@example.invalid','Rina Torres',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99304,'test.bd.celia','bd.celia@example.invalid','Celia Navarro',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99305,'test.bd.paolo','bd.paolo@example.invalid','Paolo Castillo',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1);

INSERT INTO employee_contacts (employee_id,personal_email,mobile_number,telephone_number)
VALUES (99300,'bd.ceo@example.invalid','09179930000','042-993-0000'),
       (99301,'bd.officer@example.invalid','09179930001','042-993-0001'),
       (99302,'bd.staff1@example.invalid','09179930002','042-993-0002'),
       (99303,'bd.staff2@example.invalid','09179930003','042-993-0003'),
       (99304,'bd.celia@example.invalid','09179930004','042-993-0004'),
       (99305,'bd.paolo@example.invalid','09179930005','042-993-0005');

INSERT INTO employee_details (employee_id,height_m,weight_kg,blood_type,citizenship)
VALUES (99300,1.64,59.00,'O+','Filipino'),(99301,1.73,70.00,'A+','Filipino'),
       (99302,1.71,68.00,'B+','Filipino'),(99303,1.60,55.00,'O+','Filipino'),
       (99304,1.61,54.00,'A+','Filipino'),(99305,1.74,69.00,'B+','Filipino');

INSERT INTO employee_government_ids (employee_id,sss_number,philhealth_number,pagibig_number,tin_number)
VALUES
  (99300,'MOCK-SSS-99300','MOCK-PH-99300','MOCK-PI-99300','MOCK-TIN-99300'),
  (99301,'MOCK-SSS-99301','MOCK-PH-99301','MOCK-PI-99301','MOCK-TIN-99301'),
  (99302,'MOCK-SSS-99302','MOCK-PH-99302','MOCK-PI-99302','MOCK-TIN-99302'),
  (99303,'MOCK-SSS-99303','MOCK-PH-99303','MOCK-PI-99303','MOCK-TIN-99303'),
  (99304,'MOCK-SSS-99304','MOCK-PH-99304','MOCK-PI-99304','MOCK-TIN-99304'),
  (99305,'MOCK-SSS-99305','MOCK-PH-99305','MOCK-PI-99305','MOCK-TIN-99305');

INSERT INTO employee_addresses
  (employee_id,address_type,region,house_no,street,subdivision,barangay,city,province,zip_code)
VALUES
  (99300,'Residential','CALABARZON','20','Quezon Avenue','City Proper','Barangay 1','Lucena City','Quezon','4301'),
  (99300,'Permanent','CALABARZON','20','Quezon Avenue','City Proper','Barangay 1','Lucena City','Quezon','4301'),
  (99301,'Residential','CALABARZON','21','Quezon Avenue','City Proper','Barangay 2','Lucena City','Quezon','4301'),
  (99301,'Permanent','CALABARZON','21','Quezon Avenue','City Proper','Barangay 2','Lucena City','Quezon','4301'),
  (99302,'Residential','CALABARZON','22','Rizal Street','City Proper','Barangay 3','Tayabas City','Quezon','4327'),
  (99302,'Permanent','CALABARZON','22','Rizal Street','City Proper','Barangay 3','Tayabas City','Quezon','4327'),
  (99303,'Residential','CALABARZON','23','Quezon Avenue','City Proper','Barangay 4','Lucena City','Quezon','4301'),
  (99303,'Permanent','CALABARZON','23','Quezon Avenue','City Proper','Barangay 4','Lucena City','Quezon','4301'),
  (99304,'Residential','CALABARZON','24','Quezon Avenue','City Proper','Barangay 5','Lucena City','Quezon','4301'),
  (99304,'Permanent','CALABARZON','24','Quezon Avenue','City Proper','Barangay 5','Lucena City','Quezon','4301'),
  (99305,'Residential','CALABARZON','25','Rizal Street','Town Proper','Barangay 6','Sariaya','Quezon','4322'),
  (99305,'Permanent','CALABARZON','25','Rizal Street','Town Proper','Barangay 6','Sariaya','Quezon','4322');

INSERT INTO employee_emergency_contacts
  (employee_id,contact_name,relationship,contact_number,is_primary)
VALUES (99300,'Linda Reyes','Mother','09179931000',1),
       (99301,'Rosa Dela Cruz','Mother','09179931001',1),
       (99302,'Ana Salvador','Mother','09179931002',1),
       (99303,'Lina Santos','Mother','09179931003',1),
       (99304,'Maria Reyes','Mother','09179931004',1),
       (99305,'Jose Ramos','Father','09179931005',1);

INSERT INTO employee_family (employee_id,member_type,surname,first_name,middle_name,occupation)
VALUES (99300,'Mother','Reyes','Linda','Garcia','Retired clerk'),
       (99301,'Mother','Dela Cruz','Rosa','Santos','Teacher'),
       (99302,'Mother','Salvador','Ana','Cruz','Store owner'),
       (99303,'Mother','Santos','Lina','Torres','Nurse'),
       (99304,'Mother','Reyes','Maria','Navarro','Teacher'),
       (99305,'Father','Ramos','Jose','Castillo','Mechanic');

INSERT INTO employee_education
  (employee_id,education_level,school_name,degree_course,period_from,period_to,year_graduated,honors_received)
VALUES
  (99300,'College','University of the Philippines','BS Psychology','1995-06-01','1999-04-30','1999','None'),
  (99301,'College','Southern Luzon State University','BS Business Administration','2004-06-01','2008-05-31','2008','None'),
  (99302,'College','Southern Luzon State University','BS Management','2014-06-01','2018-05-31','2018','None'),
  (99303,'College','Southern Luzon State University','BS Business Administration','2016-06-01','2020-05-31','2020','None'),
  (99304,'College','Southern Luzon State University','BS Business Administration','2019-06-01','2023-05-31','2023','None'),
  (99305,'College','Southern Luzon State University','BS Business Administration','2018-06-01','2022-05-31','2022','None');

INSERT INTO employee_work_experience
  (employee_id,date_from,date_to,job_title,company_name,monthly_salary,appointment_status,reason_for_leaving)
VALUES
  (99300,'1999-07-01','2007-03-31','Operations Lead','Mock Quezon Retail Co.',40000,'Full-time','Career advancement'),
  (99301,'2009-06-01','2017-04-30','Business Analyst','Mock Quezon Services Co.',32000,'Full-time','Career advancement'),
  (99302,'2018-07-01','2021-11-15','Research Assistant','Mock Tayabas Services Co.',24000,'Full-time','Career advancement'),
  (99303,'2020-07-01','2023-06-15','Sales Coordinator','Mock Lucena Services Co.',23000,'Full-time','Career advancement'),
  (99304,'2023-07-01','2026-08-15','Office Assistant','Mock Lucena Services Co.',21000,'Full-time','Career advancement'),
  (99305,'2022-07-01','2026-09-15','Research Assistant','Mock Sariaya Services Co.',21000,'Full-time','Career advancement');

INSERT INTO employee_disclosures
  (employee_id,is_related_to_company,has_admin_offense,has_criminal_charge,
   has_criminal_conviction,has_been_separated,is_pwd,is_solo_parent,
   has_recent_hospital,has_current_treatment)
VALUES (99300,0,0,0,0,0,0,0,0,0),(99301,0,0,0,0,0,0,0,0,0),
       (99302,0,0,0,0,0,0,0,0,0),(99303,0,0,0,0,0,0,0,0,0),
       (99304,0,0,0,0,0,0,0,0,0),(99305,0,0,0,0,0,0,0,0,0);

INSERT INTO employee_trainings
  (employee_id,date_from,date_to,training_title,training_type,no_of_hours,conducted_by)
VALUES
  (99300,'2025-03-01','2025-03-02','Leadership Review','Internal',16,'Mock Training Team'),
  (99301,'2025-04-01','2025-04-02','Business Planning','Internal',16,'Mock Training Team'),
  (99302,'2025-05-01','2025-05-02','Market Research','Internal',16,'Mock Training Team'),
  (99303,'2025-06-01','2025-06-02','Client Coordination','Internal',16,'Mock Training Team'),
  (99304,'2026-09-15','2026-09-16','BD Orientation','Internal',16,'Mock Training Team'),
  (99305,'2026-10-12','2026-10-13','BD Orientation','Internal',16,'Mock Training Team');

INSERT INTO employee_skills (employee_id,skill_name)
VALUES (99300,'Executive leadership'),(99301,'Growth planning'),
       (99302,'Market research'),(99303,'Client coordination'),
       (99304,'Business records'),(99305,'Research support');

INSERT INTO employee_references
  (employee_id,reference_name,reference_address,reference_telephone)
VALUES (99300,'Luz Bautista','Lucena City, Quezon','042-993-0100'),
       (99301,'Rosa Villanueva','Lucena City, Quezon','042-993-0101'),
       (99302,'Maria Castillo','Tayabas City, Quezon','042-993-0102'),
       (99303,'Jose Mendoza','Lucena City, Quezon','042-993-0103'),
       (99304,'Ana Ramos','Lucena City, Quezon','042-993-0104'),
       (99305,'Ramon Dela Cruz','Sariaya, Quezon','042-993-0105');
