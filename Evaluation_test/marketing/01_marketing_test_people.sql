-- Marketing test people. Run once on a fresh test database.
-- First import the schema and organization files shown in README.md.
USE raquel_hris;

-- The President and CEO creates and reviews the Marketing Manager I form.
-- The CEO is outside the Marketing cycle.
INSERT INTO employees
  (employee_id,employee_code,first_name,middle_name,last_name,name_extension,
   date_of_birth,place_of_birth,gender,civil_status,hire_date,job_title,
   job_title_id,department_id,rank_category_id,branch_id,reports_to,
   employment_status,employment_type,contract_start_date,contract_end_date,
   profile_picture,is_active)
VALUES
  (99800,'OP-788','Isabella','Reyes','Villanueva','','1978-06-18',
   'Lucena City, Quezon','Female','Married','2007-04-02',
   'President and CEO',1100,10,1,102,NULL,'Regular','Full-time',
   '2007-04-02','2030-12-31','avatar_f.jpg',1),
  (99801,'MKT-950','Celia','Cruz','Santos','','1982-02-15',
   'Lucena City, Quezon','Female','Married','2012-06-04',
   'Marketing Manager I',900,9,3,102,99800,'Regular','Full-time',
   '2012-06-04','2030-12-31','avatar_f.jpg',1),
  (99802,'MKT-467','Marco','Lopez','Reyes','','1988-05-21',
   'Tayabas City, Quezon','Male','Married','2016-08-01',
   'Marketing Manager II',901,9,3,102,99801,'Regular','Full-time',
   '2016-08-01','2030-12-31','avatar_m.jpg',1),
  (99803,'MKT-590','Dennis','Garcia','Cruz','','1993-09-08',
   'Lucena City, Quezon','Male','Single','2020-03-02',
   'Marketing Supervisor I',902,9,4,102,99801,'Regular','Full-time',
   '2020-03-02','2030-12-31','avatar_m.jpg',1),
  (99804,'MKT-496','Rina','Mendoza','Garcia','','1998-07-18',
   'Pagbilao, Quezon','Female','Single','2023-05-08',
   'Marketing Staff I',904,9,5,102,99803,'Regular','Full-time',
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
  (99805,'MKT-148','Erika','Santos','Navarro','','2002-03-12',
   'Lucena City, Quezon','Female','Single','2026-09-01',
   'Marketing Staff I',904,9,5,102,99803,'Probationary','Initial','2026-11-30',
   'Full-time','2026-09-01','2027-02-28','avatar_f.jpg',1),
  (99806,'MKT-914','Luis','Ramos','Dela Cruz','','2001-11-25',
   'Sariaya, Quezon','Male','Single','2026-10-01',
   'Marketing Staff I',904,9,5,102,99803,'Probationary','Initial','2026-12-15',
   'Full-time','2026-10-01','2027-03-31','avatar_m.jpg',1);

-- Local test password for every account: password.
INSERT INTO users
  (employee_id,username,email,full_name,password_hash,role,branch_id,
   is_active,account_hold,first_login_completed,must_change_password)
VALUES
  (99800,'test.marketing.ceo','marketing.ceo@example.invalid','Isabella Villanueva',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','President and CEO',102,1,0,0,1),
  (99801,'test.marketing.manager1','marketing.manager1@example.invalid','Celia Santos',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99802,'test.marketing.manager2','marketing.manager2@example.invalid','Marco Reyes',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99803,'test.marketing.supervisor','marketing.supervisor@example.invalid','Dennis Cruz',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99804,'test.marketing.staff1','marketing.staff1@example.invalid','Rina Garcia',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99805,'test.marketing.erika','marketing.erika@example.invalid','Erika Navarro',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99806,'test.marketing.luis','marketing.luis@example.invalid','Luis Dela Cruz',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1);

INSERT INTO employee_contacts (employee_id,personal_email,mobile_number,telephone_number)
VALUES
  (99800,'marketing.ceo@example.invalid','09179980000','042-991-0000'),
  (99801,'marketing.manager1@example.invalid','09179980001','042-991-0001'),
  (99802,'marketing.manager2@example.invalid','09179980002','042-991-0002'),
  (99803,'marketing.supervisor@example.invalid','09179980003','042-991-0003'),
  (99804,'marketing.staff1@example.invalid','09179980004','042-991-0004'),
  (99805,'marketing.erika@example.invalid','09179980005','042-991-0005'),
  (99806,'marketing.luis@example.invalid','09179980006','042-991-0006');

INSERT INTO employee_details (employee_id,height_m,weight_kg,blood_type,citizenship)
VALUES (99800,1.64,59.00,'O+','Filipino'),(99801,1.62,57.00,'A+','Filipino'),
       (99802,1.73,70.00,'B+','Filipino'),(99803,1.71,68.00,'O+','Filipino'),
       (99804,1.59,53.00,'A+','Filipino'),(99805,1.60,55.00,'B+','Filipino'),
       (99806,1.74,69.00,'O+','Filipino');

INSERT INTO employee_government_ids (employee_id,sss_number,philhealth_number,pagibig_number,tin_number)
VALUES
  (99800,'MOCK-SSS-99800','MOCK-PH-99800','MOCK-PI-99800','MOCK-TIN-99800'),
  (99801,'MOCK-SSS-99801','MOCK-PH-99801','MOCK-PI-99801','MOCK-TIN-99801'),
  (99802,'MOCK-SSS-99802','MOCK-PH-99802','MOCK-PI-99802','MOCK-TIN-99802'),
  (99803,'MOCK-SSS-99803','MOCK-PH-99803','MOCK-PI-99803','MOCK-TIN-99803'),
  (99804,'MOCK-SSS-99804','MOCK-PH-99804','MOCK-PI-99804','MOCK-TIN-99804'),
  (99805,'MOCK-SSS-99805','MOCK-PH-99805','MOCK-PI-99805','MOCK-TIN-99805'),
  (99806,'MOCK-SSS-99806','MOCK-PH-99806','MOCK-PI-99806','MOCK-TIN-99806');

INSERT INTO employee_addresses
  (employee_id,address_type,region,house_no,street,subdivision,barangay,city,province,zip_code)
VALUES
  (99800,'Residential','CALABARZON','10','Quezon Avenue','City Proper','Barangay 1','Lucena City','Quezon','4301'),
  (99800,'Permanent','CALABARZON','10','Quezon Avenue','City Proper','Barangay 1','Lucena City','Quezon','4301'),
  (99801,'Residential','CALABARZON','11','Quezon Avenue','City Proper','Barangay 2','Lucena City','Quezon','4301'),
  (99801,'Permanent','CALABARZON','11','Quezon Avenue','City Proper','Barangay 2','Lucena City','Quezon','4301'),
  (99802,'Residential','CALABARZON','12','Rizal Street','City Proper','Barangay 3','Tayabas City','Quezon','4327'),
  (99802,'Permanent','CALABARZON','12','Rizal Street','City Proper','Barangay 3','Tayabas City','Quezon','4327'),
  (99803,'Residential','CALABARZON','13','Quezon Avenue','City Proper','Barangay 4','Lucena City','Quezon','4301'),
  (99803,'Permanent','CALABARZON','13','Quezon Avenue','City Proper','Barangay 4','Lucena City','Quezon','4301'),
  (99804,'Residential','CALABARZON','14','National Road','Town Proper','Barangay 5','Pagbilao','Quezon','4302'),
  (99804,'Permanent','CALABARZON','14','National Road','Town Proper','Barangay 5','Pagbilao','Quezon','4302'),
  (99805,'Residential','CALABARZON','15','Quezon Avenue','City Proper','Barangay 6','Lucena City','Quezon','4301'),
  (99805,'Permanent','CALABARZON','15','Quezon Avenue','City Proper','Barangay 6','Lucena City','Quezon','4301'),
  (99806,'Residential','CALABARZON','16','Rizal Street','Town Proper','Barangay 7','Sariaya','Quezon','4322'),
  (99806,'Permanent','CALABARZON','16','Rizal Street','Town Proper','Barangay 7','Sariaya','Quezon','4322');

INSERT INTO employee_emergency_contacts
  (employee_id,contact_name,relationship,contact_number,is_primary)
VALUES (99800,'Linda Reyes','Mother','09179911000',1),
       (99801,'Carmen Cruz','Mother','09179911001',1),
       (99802,'Rosa Lopez','Mother','09179911002',1),
       (99803,'Ana Garcia','Mother','09179911003',1),
       (99804,'Lina Mendoza','Mother','09179911004',1),
       (99805,'Maria Santos','Mother','09179911005',1),
       (99806,'Jose Ramos','Father','09179911006',1);

INSERT INTO employee_family (employee_id,member_type,surname,first_name,middle_name,occupation)
VALUES (99800,'Mother','Reyes','Linda','Garcia','Retired clerk'),
       (99801,'Mother','Cruz','Carmen','Lopez','Teacher'),
       (99802,'Mother','Lopez','Rosa','Reyes','Store owner'),
       (99803,'Mother','Garcia','Ana','Santos','Nurse'),
       (99804,'Mother','Mendoza','Lina','Cruz','Teacher'),
       (99805,'Mother','Santos','Maria','Reyes','Store owner'),
       (99806,'Father','Ramos','Jose','Dela Cruz','Mechanic');

INSERT INTO employee_education
  (employee_id,education_level,school_name,degree_course,period_from,period_to,year_graduated,honors_received)
VALUES
  (99800,'College','University of the Philippines','BS Psychology','1995-06-01','1999-04-30','1999','None'),
  (99801,'College','Southern Luzon State University','BS Marketing','1999-06-01','2003-05-31','2003','None'),
  (99802,'College','Southern Luzon State University','BS Communication','2005-06-01','2009-05-31','2009','None'),
  (99803,'College','Southern Luzon State University','BS Marketing','2010-06-01','2014-05-31','2014','None'),
  (99804,'College','Southern Luzon State University','BS Communication','2015-06-01','2019-05-31','2019','None'),
  (99805,'College','Southern Luzon State University','BS Marketing','2019-06-01','2023-05-31','2023','None'),
  (99806,'College','Southern Luzon State University','BS Communication','2018-06-01','2022-05-31','2022','None');

INSERT INTO employee_work_experience
  (employee_id,date_from,date_to,job_title,company_name,monthly_salary,appointment_status,reason_for_leaving)
VALUES
  (99800,'1999-07-01','2007-03-31','Executive Director','Mock Quezon Retail Co.',40000,'Full-time','Career advancement'),
  (99801,'2004-06-01','2012-05-31','Marketing Lead','Mock Quezon Media Co.',32000,'Full-time','Career advancement'),
  (99802,'2010-06-01','2016-07-31','Marketing Coordinator','Mock Tayabas Media Co.',28000,'Full-time','Career advancement'),
  (99803,'2015-06-01','2020-02-29','Campaign Supervisor','Mock Lucena Services Co.',24000,'Full-time','Career advancement'),
  (99804,'2019-07-01','2023-04-30','Marketing Assistant','Mock Pagbilao Media Co.',21000,'Full-time','Career advancement'),
  (99805,'2023-07-01','2026-08-15','Marketing Assistant','Mock Lucena Media Co.',22000,'Full-time','Career advancement'),
  (99806,'2022-07-01','2026-09-15','Marketing Assistant','Mock Sariaya Media Co.',21000,'Full-time','Career advancement');

INSERT INTO employee_disclosures
  (employee_id,is_related_to_company,has_admin_offense,has_criminal_charge,
   has_criminal_conviction,has_been_separated,is_pwd,is_solo_parent,
   has_recent_hospital,has_current_treatment)
VALUES (99800,0,0,0,0,0,0,0,0,0),(99801,0,0,0,0,0,0,0,0,0),
       (99802,0,0,0,0,0,0,0,0,0),(99803,0,0,0,0,0,0,0,0,0),
       (99804,0,0,0,0,0,0,0,0,0),(99805,0,0,0,0,0,0,0,0,0),
       (99806,0,0,0,0,0,0,0,0,0);

INSERT INTO employee_trainings
  (employee_id,date_from,date_to,training_title,training_type,no_of_hours,conducted_by)
VALUES
  (99800,'2025-03-01','2025-03-02','Leadership Review','Internal',16,'Mock Training Team'),
  (99801,'2025-04-01','2025-04-02','Marketing Planning','Internal',16,'Mock Training Team'),
  (99802,'2025-05-01','2025-05-02','Campaign Delivery Review','Internal',16,'Mock Training Team'),
  (99803,'2025-06-01','2025-06-02','Campaign Management','Internal',16,'Mock Training Team'),
  (99804,'2025-07-01','2025-07-02','Brand Communications','Internal',16,'Mock Training Team'),
  (99805,'2026-09-15','2026-09-16','Marketing Orientation','Internal',16,'Mock Training Team'),
  (99806,'2026-10-12','2026-10-13','Marketing Orientation','Internal',16,'Mock Training Team');

INSERT INTO employee_skills (employee_id,skill_name)
VALUES (99800,'Executive leadership'),(99801,'Marketing planning'),
       (99802,'Campaign delivery'),(99803,'Campaign management'),
       (99804,'Brand communications'),(99805,'Content review'),
       (99806,'Campaign reporting');

INSERT INTO employee_references
  (employee_id,reference_name,reference_address,reference_telephone)
VALUES (99800,'Luz Bautista','Lucena City, Quezon','042-991-0100'),
       (99801,'Rosa Villanueva','Lucena City, Quezon','042-991-0101'),
       (99802,'Maria Castillo','Tayabas City, Quezon','042-991-0102'),
       (99803,'Jose Mendoza','Lucena City, Quezon','042-991-0103'),
       (99804,'Ana Ramos','Pagbilao, Quezon','042-991-0104'),
       (99805,'Lina Navarro','Lucena City, Quezon','042-991-0105'),
       (99806,'Ramon Dela Cruz','Sariaya, Quezon','042-991-0106');

-- A second regular supervisor tests a separate Marketing position form.
INSERT INTO employees
  (employee_id,employee_code,first_name,middle_name,last_name,name_extension,
   date_of_birth,place_of_birth,gender,civil_status,hire_date,job_title,
   job_title_id,department_id,rank_category_id,branch_id,reports_to,
   employment_status,employment_type,contract_start_date,contract_end_date,
   profile_picture,is_active)
VALUES (99807,'MKT-275','Felix','Santos','Mendoza','','1996-04-12',
        'Lucena City, Quezon','Male','Single','2021-08-02',
        'Marketing Supervisor II',903,9,4,102,99801,'Regular','Full-time',
        '2021-08-02','2030-12-31','avatar_m.jpg',1);
INSERT INTO users
  (employee_id,username,email,full_name,password_hash,role,branch_id,
   is_active,account_hold,first_login_completed,must_change_password)
VALUES (99807,'test.marketing.supervisor2','marketing.supervisor2@example.invalid','Felix Mendoza',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'Employee',102,1,0,0,1);
INSERT INTO employee_contacts (employee_id,personal_email,mobile_number,telephone_number)
VALUES (99807,'marketing.supervisor2@example.invalid','09179980007','042-997-0007');
INSERT INTO employee_details (employee_id,height_m,weight_kg,blood_type,citizenship)
VALUES (99807,1.73,70.00,'O+','Filipino');
INSERT INTO employee_government_ids (employee_id,sss_number,philhealth_number,pagibig_number,tin_number)
VALUES (99807,'MOCK-SSS-99807','MOCK-PH-99807','MOCK-PI-99807','MOCK-TIN-99807');
INSERT INTO employee_addresses
  (employee_id,address_type,region,house_no,street,subdivision,barangay,city,province,zip_code)
VALUES (99807,'Residential','CALABARZON','17','Quezon Avenue','City Proper','Barangay 8','Lucena City','Quezon','4301'),
       (99807,'Permanent','CALABARZON','17','Quezon Avenue','City Proper','Barangay 8','Lucena City','Quezon','4301');
INSERT INTO employee_emergency_contacts
  (employee_id,contact_name,relationship,contact_number,is_primary)
VALUES (99807,'Ana Santos','Mother','09179971007',1);
INSERT INTO employee_family (employee_id,member_type,surname,first_name,middle_name,occupation)
VALUES (99807,'Mother','Santos','Ana','Cruz','Teacher');
INSERT INTO employee_education
  (employee_id,education_level,school_name,degree_course,period_from,period_to,year_graduated,honors_received)
VALUES (99807,'College','Southern Luzon State University','BS Marketing',
        '2013-06-01','2017-05-31','2017','None');
INSERT INTO employee_work_experience
  (employee_id,date_from,date_to,job_title,company_name,monthly_salary,appointment_status,reason_for_leaving)
VALUES (99807,'2017-07-01','2021-07-15','Marketing Coordinator','Mock Lucena Media Co.',22000,
        'Full-time','Career advancement');
INSERT INTO employee_disclosures
  (employee_id,is_related_to_company,has_admin_offense,has_criminal_charge,
   has_criminal_conviction,has_been_separated,is_pwd,is_solo_parent,
   has_recent_hospital,has_current_treatment)
VALUES (99807,0,0,0,0,0,0,0,0,0);
INSERT INTO employee_trainings
  (employee_id,date_from,date_to,training_title,training_type,no_of_hours,conducted_by)
VALUES (99807,'2025-05-10','2025-05-11','Campaign Planning','Internal',16,'Mock Training Team');
INSERT INTO employee_skills (employee_id,skill_name)
VALUES (99807,'Campaign supervision');
INSERT INTO employee_references
  (employee_id,reference_name,reference_address,reference_telephone)
VALUES (99807,'Maria Castillo','Lucena City, Quezon','042-997-0107');
