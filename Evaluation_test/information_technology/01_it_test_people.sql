-- Information Technology test people. Run once on a fresh test database.
-- First import the schema and organization files shown in README.md.
USE raquel_hris;

-- The VP for Operations creates and reviews the IT Manager I form.
-- The VP is outside the IT cycle.
INSERT INTO employees
  (employee_id,employee_code,first_name,middle_name,last_name,name_extension,
   date_of_birth,place_of_birth,gender,civil_status,hire_date,job_title,
   job_title_id,department_id,rank_category_id,branch_id,reports_to,
   employment_status,employment_type,contract_start_date,contract_end_date,
   profile_picture,is_active)
VALUES
  (99700,'OPS-177','Olivia','Reyes','Villanueva','','1978-06-18',
   'Lucena City, Quezon','Female','Married','2007-04-02',
   'VP for Operations',1000,11,1,102,NULL,'Regular','Full-time',
   '2007-04-02','2030-12-31','avatar_f.jpg',1),
  (99701,'IT-803','Nadine','Cruz','Santos','','1982-02-15',
   'Lucena City, Quezon','Female','Married','2012-06-04',
   'IT Manager I',800,8,3,102,99700,'Regular','Full-time',
   '2012-06-04','2030-12-31','avatar_f.jpg',1),
  (99702,'IT-341','Rafael','Lopez','Reyes','','1988-05-21',
   'Tayabas City, Quezon','Male','Married','2016-08-01',
   'IT Manager II',801,8,3,102,99701,'Regular','Full-time',
   '2016-08-01','2030-12-31','avatar_m.jpg',1),
  (99703,'IT-913','Jonas','Garcia','Cruz','','1993-09-08',
   'Lucena City, Quezon','Male','Single','2020-03-02',
   'IT Supervisor I',804,8,4,102,99701,'Regular','Full-time',
   '2020-03-02','2030-12-31','avatar_m.jpg',1),
  (99704,'IT-500','Tessa','Mendoza','Garcia','','1998-07-18',
   'Pagbilao, Quezon','Female','Single','2023-05-08',
   'Programmer I',809,8,5,102,99703,'Regular','Full-time',
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
  (99705,'IT-241','Iris','Santos','Navarro','','2002-03-12',
   'Lucena City, Quezon','Female','Single','2026-09-01',
   'Programmer I',809,8,5,102,99703,'Probationary','Initial','2026-11-30',
   'Full-time','2026-09-01','2027-02-28','avatar_f.jpg',1),
  (99706,'IT-618','Paolo','Ramos','Dela Cruz','','2001-11-25',
   'Sariaya, Quezon','Male','Single','2026-10-01',
   'Programmer I',809,8,5,102,99703,'Probationary','Initial','2026-12-15',
   'Full-time','2026-10-01','2027-03-31','avatar_m.jpg',1);

-- Local test password for every account: password.
INSERT INTO users
  (employee_id,username,email,full_name,password_hash,role,branch_id,
   is_active,account_hold,first_login_completed,must_change_password)
VALUES
  (99700,'test.it.ops_vp','it.ops_vp@example.invalid','Olivia Villanueva',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99701,'test.it.manager1','it.manager1@example.invalid','Nadine Santos',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99702,'test.it.manager2','it.manager2@example.invalid','Rafael Reyes',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99703,'test.it.supervisor','it.supervisor@example.invalid','Jonas Cruz',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99704,'test.it.programmer','it.programmer@example.invalid','Tessa Garcia',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99705,'test.it.iris','it.iris@example.invalid','Iris Navarro',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99706,'test.it.paolo','it.paolo@example.invalid','Paolo Dela Cruz',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1);

INSERT INTO employee_contacts (employee_id,personal_email,mobile_number,telephone_number)
VALUES
  (99700,'it.ops_vp@example.invalid','09179970000','042-991-0000'),
  (99701,'it.manager1@example.invalid','09179970001','042-991-0001'),
  (99702,'it.manager2@example.invalid','09179970002','042-991-0002'),
  (99703,'it.supervisor@example.invalid','09179970003','042-991-0003'),
  (99704,'it.programmer@example.invalid','09179970004','042-991-0004'),
  (99705,'it.iris@example.invalid','09179970005','042-991-0005'),
  (99706,'it.paolo@example.invalid','09179970006','042-991-0006');

INSERT INTO employee_details (employee_id,height_m,weight_kg,blood_type,citizenship)
VALUES (99700,1.64,59.00,'O+','Filipino'),(99701,1.62,57.00,'A+','Filipino'),
       (99702,1.73,70.00,'B+','Filipino'),(99703,1.71,68.00,'O+','Filipino'),
       (99704,1.59,53.00,'A+','Filipino'),(99705,1.60,55.00,'B+','Filipino'),
       (99706,1.74,69.00,'O+','Filipino');

INSERT INTO employee_government_ids (employee_id,sss_number,philhealth_number,pagibig_number,tin_number)
VALUES
  (99700,'MOCK-SSS-99700','MOCK-PH-99700','MOCK-PI-99700','MOCK-TIN-99700'),
  (99701,'MOCK-SSS-99701','MOCK-PH-99701','MOCK-PI-99701','MOCK-TIN-99701'),
  (99702,'MOCK-SSS-99702','MOCK-PH-99702','MOCK-PI-99702','MOCK-TIN-99702'),
  (99703,'MOCK-SSS-99703','MOCK-PH-99703','MOCK-PI-99703','MOCK-TIN-99703'),
  (99704,'MOCK-SSS-99704','MOCK-PH-99704','MOCK-PI-99704','MOCK-TIN-99704'),
  (99705,'MOCK-SSS-99705','MOCK-PH-99705','MOCK-PI-99705','MOCK-TIN-99705'),
  (99706,'MOCK-SSS-99706','MOCK-PH-99706','MOCK-PI-99706','MOCK-TIN-99706');

INSERT INTO employee_addresses
  (employee_id,address_type,region,house_no,street,subdivision,barangay,city,province,zip_code)
VALUES
  (99700,'Residential','CALABARZON','10','Quezon Avenue','City Proper','Barangay 1','Lucena City','Quezon','4301'),
  (99700,'Permanent','CALABARZON','10','Quezon Avenue','City Proper','Barangay 1','Lucena City','Quezon','4301'),
  (99701,'Residential','CALABARZON','11','Quezon Avenue','City Proper','Barangay 2','Lucena City','Quezon','4301'),
  (99701,'Permanent','CALABARZON','11','Quezon Avenue','City Proper','Barangay 2','Lucena City','Quezon','4301'),
  (99702,'Residential','CALABARZON','12','Rizal Street','City Proper','Barangay 3','Tayabas City','Quezon','4327'),
  (99702,'Permanent','CALABARZON','12','Rizal Street','City Proper','Barangay 3','Tayabas City','Quezon','4327'),
  (99703,'Residential','CALABARZON','13','Quezon Avenue','City Proper','Barangay 4','Lucena City','Quezon','4301'),
  (99703,'Permanent','CALABARZON','13','Quezon Avenue','City Proper','Barangay 4','Lucena City','Quezon','4301'),
  (99704,'Residential','CALABARZON','14','National Road','Town Proper','Barangay 5','Pagbilao','Quezon','4302'),
  (99704,'Permanent','CALABARZON','14','National Road','Town Proper','Barangay 5','Pagbilao','Quezon','4302'),
  (99705,'Residential','CALABARZON','15','Quezon Avenue','City Proper','Barangay 6','Lucena City','Quezon','4301'),
  (99705,'Permanent','CALABARZON','15','Quezon Avenue','City Proper','Barangay 6','Lucena City','Quezon','4301'),
  (99706,'Residential','CALABARZON','16','Rizal Street','Town Proper','Barangay 7','Sariaya','Quezon','4322'),
  (99706,'Permanent','CALABARZON','16','Rizal Street','Town Proper','Barangay 7','Sariaya','Quezon','4322');

INSERT INTO employee_emergency_contacts
  (employee_id,contact_name,relationship,contact_number,is_primary)
VALUES (99700,'Linda Reyes','Mother','09179911000',1),
       (99701,'Carmen Cruz','Mother','09179911001',1),
       (99702,'Rosa Lopez','Mother','09179911002',1),
       (99703,'Ana Garcia','Mother','09179911003',1),
       (99704,'Lina Mendoza','Mother','09179911004',1),
       (99705,'Maria Santos','Mother','09179911005',1),
       (99706,'Jose Ramos','Father','09179911006',1);

INSERT INTO employee_family (employee_id,member_type,surname,first_name,middle_name,occupation)
VALUES (99700,'Mother','Reyes','Linda','Garcia','Retired clerk'),
       (99701,'Mother','Cruz','Carmen','Lopez','Teacher'),
       (99702,'Mother','Lopez','Rosa','Reyes','Store owner'),
       (99703,'Mother','Garcia','Ana','Santos','Nurse'),
       (99704,'Mother','Mendoza','Lina','Cruz','Teacher'),
       (99705,'Mother','Santos','Maria','Reyes','Store owner'),
       (99706,'Father','Ramos','Jose','Dela Cruz','Mechanic');

INSERT INTO employee_education
  (employee_id,education_level,school_name,degree_course,period_from,period_to,year_graduated,honors_received)
VALUES
  (99700,'College','University of the Philippines','BS Psychology','1995-06-01','1999-04-30','1999','None'),
  (99701,'College','Southern Luzon State University','BS Information Technology','1999-06-01','2003-05-31','2003','None'),
  (99702,'College','Southern Luzon State University','BS Computer Science','2005-06-01','2009-05-31','2009','None'),
  (99703,'College','Southern Luzon State University','BS Information Technology','2010-06-01','2014-05-31','2014','None'),
  (99704,'College','Southern Luzon State University','BS Computer Science','2015-06-01','2019-05-31','2019','None'),
  (99705,'College','Southern Luzon State University','BS Information Technology','2019-06-01','2023-05-31','2023','None'),
  (99706,'College','Southern Luzon State University','BS Computer Science','2018-06-01','2022-05-31','2022','None');

INSERT INTO employee_work_experience
  (employee_id,date_from,date_to,job_title,company_name,monthly_salary,appointment_status,reason_for_leaving)
VALUES
  (99700,'1999-07-01','2007-03-31','Operations Lead','Mock Quezon Retail Co.',40000,'Full-time','Career advancement'),
  (99701,'2004-06-01','2012-05-31','Systems Analyst','Mock Quezon Tech Co.',32000,'Full-time','Career advancement'),
  (99702,'2010-06-01','2016-07-31','Software Engineer','Mock Tayabas Tech Co.',28000,'Full-time','Career advancement'),
  (99703,'2015-06-01','2020-02-29','IT Support Lead','Mock Lucena Services Co.',24000,'Full-time','Career advancement'),
  (99704,'2019-07-01','2023-04-30','Junior Programmer','Mock Pagbilao Tech Co.',21000,'Full-time','Career advancement'),
  (99705,'2023-07-01','2026-08-15','Software Assistant','Mock Lucena Tech Co.',22000,'Full-time','Career advancement'),
  (99706,'2022-07-01','2026-09-15','Junior Developer','Mock Sariaya Tech Co.',21000,'Full-time','Career advancement');

INSERT INTO employee_disclosures
  (employee_id,is_related_to_company,has_admin_offense,has_criminal_charge,
   has_criminal_conviction,has_been_separated,is_pwd,is_solo_parent,
   has_recent_hospital,has_current_treatment)
VALUES (99700,0,0,0,0,0,0,0,0,0),(99701,0,0,0,0,0,0,0,0,0),
       (99702,0,0,0,0,0,0,0,0,0),(99703,0,0,0,0,0,0,0,0,0),
       (99704,0,0,0,0,0,0,0,0,0),(99705,0,0,0,0,0,0,0,0,0),
       (99706,0,0,0,0,0,0,0,0,0);

INSERT INTO employee_trainings
  (employee_id,date_from,date_to,training_title,training_type,no_of_hours,conducted_by)
VALUES
  (99700,'2025-03-01','2025-03-02','Leadership Review','Internal',16,'Mock Training Team'),
  (99701,'2025-04-01','2025-04-02','Information Technology Planning','Internal',16,'Mock Training Team'),
  (99702,'2025-05-01','2025-05-02','Software Delivery Review','Internal',16,'Mock Training Team'),
  (99703,'2025-06-01','2025-06-02','IT Service Management','Internal',16,'Mock Training Team'),
  (99704,'2025-07-01','2025-07-02','Secure Coding Basics','Internal',16,'Mock Training Team'),
  (99705,'2026-09-15','2026-09-16','Development Environment Orientation','Internal',16,'Mock Training Team'),
  (99706,'2026-10-12','2026-10-13','Development Environment Orientation','Internal',16,'Mock Training Team');

INSERT INTO employee_skills (employee_id,skill_name)
VALUES (99700,'Executive leadership'),(99701,'Information Technology planning'),
       (99702,'Software delivery'),(99703,'IT service management'),
       (99704,'Programming'),(99705,'Code review'),
       (99706,'Software testing');

INSERT INTO employee_references
  (employee_id,reference_name,reference_address,reference_telephone)
VALUES (99700,'Luz Bautista','Lucena City, Quezon','042-991-0100'),
       (99701,'Rosa Villanueva','Lucena City, Quezon','042-991-0101'),
       (99702,'Maria Castillo','Tayabas City, Quezon','042-991-0102'),
       (99703,'Jose Mendoza','Lucena City, Quezon','042-991-0103'),
       (99704,'Ana Ramos','Pagbilao, Quezon','042-991-0104'),
       (99705,'Lina Navarro','Lucena City, Quezon','042-991-0105'),
       (99706,'Ramon Dela Cruz','Sariaya, Quezon','042-991-0106');

-- A second regular staff position checks that the IT Supervisor's forms cover
-- each position, while the probationary employees remain Programmer I.
INSERT INTO employees
  (employee_id,employee_code,first_name,middle_name,last_name,name_extension,
   date_of_birth,place_of_birth,gender,civil_status,hire_date,job_title,
   job_title_id,department_id,rank_category_id,branch_id,reports_to,
   employment_status,employment_type,contract_start_date,contract_end_date,
   profile_picture,is_active)
VALUES (99707,'IT-998','Gabriel','Santos','Mendoza','','1996-04-12',
        'Lucena City, Quezon','Male','Single','2021-08-02',
        'Technical Support Staff I',810,8,5,102,99703,'Regular','Full-time',
        '2021-08-02','2030-12-31','avatar_m.jpg',1);
INSERT INTO users
  (employee_id,username,email,full_name,password_hash,role,branch_id,
   is_active,account_hold,first_login_completed,must_change_password)
VALUES (99707,'test.it.techsupport','it.techsupport@example.invalid','Gabriel Mendoza',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'Employee',102,1,0,0,1);
INSERT INTO employee_contacts (employee_id,personal_email,mobile_number,telephone_number)
VALUES (99707,'it.techsupport@example.invalid','09179970007','042-997-0007');
INSERT INTO employee_details (employee_id,height_m,weight_kg,blood_type,citizenship)
VALUES (99707,1.73,70.00,'O+','Filipino');
INSERT INTO employee_government_ids (employee_id,sss_number,philhealth_number,pagibig_number,tin_number)
VALUES (99707,'MOCK-SSS-99707','MOCK-PH-99707','MOCK-PI-99707','MOCK-TIN-99707');
INSERT INTO employee_addresses
  (employee_id,address_type,region,house_no,street,subdivision,barangay,city,province,zip_code)
VALUES (99707,'Residential','CALABARZON','17','Quezon Avenue','City Proper','Barangay 8','Lucena City','Quezon','4301'),
       (99707,'Permanent','CALABARZON','17','Quezon Avenue','City Proper','Barangay 8','Lucena City','Quezon','4301');
INSERT INTO employee_emergency_contacts
  (employee_id,contact_name,relationship,contact_number,is_primary)
VALUES (99707,'Ana Santos','Mother','09179971007',1);
INSERT INTO employee_family (employee_id,member_type,surname,first_name,middle_name,occupation)
VALUES (99707,'Mother','Santos','Ana','Cruz','Teacher');
INSERT INTO employee_education
  (employee_id,education_level,school_name,degree_course,period_from,period_to,year_graduated,honors_received)
VALUES (99707,'College','Southern Luzon State University','BS Information Technology',
        '2013-06-01','2017-05-31','2017','None');
INSERT INTO employee_work_experience
  (employee_id,date_from,date_to,job_title,company_name,monthly_salary,appointment_status,reason_for_leaving)
VALUES (99707,'2017-07-01','2021-07-15','IT Support Assistant','Mock Lucena Tech Co.',22000,
        'Full-time','Career advancement');
INSERT INTO employee_disclosures
  (employee_id,is_related_to_company,has_admin_offense,has_criminal_charge,
   has_criminal_conviction,has_been_separated,is_pwd,is_solo_parent,
   has_recent_hospital,has_current_treatment)
VALUES (99707,0,0,0,0,0,0,0,0,0);
INSERT INTO employee_trainings
  (employee_id,date_from,date_to,training_title,training_type,no_of_hours,conducted_by)
VALUES (99707,'2025-05-10','2025-05-11','Helpdesk Troubleshooting','Internal',16,'Mock Training Team');
INSERT INTO employee_skills (employee_id,skill_name)
VALUES (99707,'Technical support');
INSERT INTO employee_references
  (employee_id,reference_name,reference_address,reference_telephone)
VALUES (99707,'Maria Castillo','Lucena City, Quezon','042-997-0107');
