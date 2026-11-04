-- Office of the President test people. Run once on a fresh test database.
-- First import the schema and organization files shown in README.md.
USE raquel_hris;

-- The President and CEO creates and reviews Executive Assistant forms.
-- The CEO owns the cycle but is excluded from its evaluation roster.
INSERT INTO employees
  (employee_id,employee_code,first_name,middle_name,last_name,name_extension,
   date_of_birth,place_of_birth,gender,civil_status,hire_date,job_title,
   job_title_id,department_id,rank_category_id,branch_id,reports_to,
   employment_status,employment_type,contract_start_date,contract_end_date,
   profile_picture,is_active)
VALUES
  (99900,'OP-818','Isabella','Reyes','Villanueva','','1978-06-18',
   'Lucena City, Quezon','Female','Married','2007-04-02',
   'President and CEO',1100,10,1,102,NULL,'Regular','Full-time',
   '2007-04-02','2030-12-31','avatar_f.jpg',1),
  (99901,'OP-435','Mara','Cruz','Santos','','1982-02-15',
   'Lucena City, Quezon','Female','Married','2012-06-04',
   'Executive Assistant I',1101,10,5,102,99900,'Regular','Full-time',
   '2012-06-04','2030-12-31','avatar_f.jpg',1),
  (99902,'OP-448','Rico','Lopez','Reyes','','1988-05-21',
   'Tayabas City, Quezon','Male','Married','2016-08-01',
   'Executive Assistant II',1102,10,5,102,99900,'Regular','Full-time',
   '2016-08-01','2030-12-31','avatar_m.jpg',1),
  (99903,'OP-559','Dario','Garcia','Cruz','','1993-09-08',
   'Lucena City, Quezon','Male','Single','2020-03-02',
   'Executive Assistant III',1103,10,5,102,99900,'Regular','Full-time',
   '2020-03-02','2030-12-31','avatar_m.jpg',1),
  (99904,'OP-254','Rhea','Mendoza','Garcia','','1998-07-18',
   'Pagbilao, Quezon','Female','Single','2023-05-08',
   'Executive Assistant I',1101,10,5,102,99900,'Regular','Full-time',
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
  (99905,'OP-689','Ella','Santos','Navarro','','2002-03-12',
   'Lucena City, Quezon','Female','Single','2026-09-01',
   'Executive Assistant I',1101,10,5,102,99900,'Probationary','Initial','2026-11-30',
   'Full-time','2026-09-01','2027-02-28','avatar_f.jpg',1),
  (99906,'OP-985','Leo','Ramos','Dela Cruz','','2001-11-25',
   'Sariaya, Quezon','Male','Single','2026-10-01',
   'Executive Assistant I',1101,10,5,102,99900,'Probationary','Initial','2026-12-15',
   'Full-time','2026-10-01','2027-03-31','avatar_m.jpg',1);

-- Local test password for every account: password.
INSERT INTO users
  (employee_id,username,email,full_name,password_hash,role,branch_id,
   is_active,account_hold,first_login_completed,must_change_password)
VALUES
  (99900,'test.op.ceo','op.ceo@example.invalid','Isabella Villanueva',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','President and CEO',102,1,0,0,1),
  (99901,'test.op.assistant1','op.assistant1@example.invalid','Mara Santos',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99902,'test.op.assistant2','op.assistant2@example.invalid','Rico Reyes',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99903,'test.op.assistant3','op.assistant3@example.invalid','Dario Cruz',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99904,'test.op.assistant1b','op.assistant1b@example.invalid','Rhea Garcia',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99905,'test.op.ella','op.ella@example.invalid','Ella Navarro',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99906,'test.op.leo','op.leo@example.invalid','Leo Dela Cruz',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1);

INSERT INTO employee_contacts (employee_id,personal_email,mobile_number,telephone_number)
VALUES
  (99900,'op.ceo@example.invalid','09179990000','042-991-0000'),
  (99901,'op.assistant1@example.invalid','09179990001','042-991-0001'),
  (99902,'op.assistant2@example.invalid','09179990002','042-991-0002'),
  (99903,'op.assistant3@example.invalid','09179990003','042-991-0003'),
  (99904,'op.assistant1b@example.invalid','09179990004','042-991-0004'),
  (99905,'op.ella@example.invalid','09179990005','042-991-0005'),
  (99906,'op.leo@example.invalid','09179990006','042-991-0006');

INSERT INTO employee_details (employee_id,height_m,weight_kg,blood_type,citizenship)
VALUES (99900,1.64,59.00,'O+','Filipino'),(99901,1.62,57.00,'A+','Filipino'),
       (99902,1.73,70.00,'B+','Filipino'),(99903,1.71,68.00,'O+','Filipino'),
       (99904,1.59,53.00,'A+','Filipino'),(99905,1.60,55.00,'B+','Filipino'),
       (99906,1.74,69.00,'O+','Filipino');

INSERT INTO employee_government_ids (employee_id,sss_number,philhealth_number,pagibig_number,tin_number)
VALUES
  (99900,'MOCK-SSS-99900','MOCK-PH-99900','MOCK-PI-99900','MOCK-TIN-99900'),
  (99901,'MOCK-SSS-99901','MOCK-PH-99901','MOCK-PI-99901','MOCK-TIN-99901'),
  (99902,'MOCK-SSS-99902','MOCK-PH-99902','MOCK-PI-99902','MOCK-TIN-99902'),
  (99903,'MOCK-SSS-99903','MOCK-PH-99903','MOCK-PI-99903','MOCK-TIN-99903'),
  (99904,'MOCK-SSS-99904','MOCK-PH-99904','MOCK-PI-99904','MOCK-TIN-99904'),
  (99905,'MOCK-SSS-99905','MOCK-PH-99905','MOCK-PI-99905','MOCK-TIN-99905'),
  (99906,'MOCK-SSS-99906','MOCK-PH-99906','MOCK-PI-99906','MOCK-TIN-99906');

INSERT INTO employee_addresses
  (employee_id,address_type,region,house_no,street,subdivision,barangay,city,province,zip_code)
VALUES
  (99900,'Residential','CALABARZON','10','Quezon Avenue','City Proper','Barangay 1','Lucena City','Quezon','4301'),
  (99900,'Permanent','CALABARZON','10','Quezon Avenue','City Proper','Barangay 1','Lucena City','Quezon','4301'),
  (99901,'Residential','CALABARZON','11','Quezon Avenue','City Proper','Barangay 2','Lucena City','Quezon','4301'),
  (99901,'Permanent','CALABARZON','11','Quezon Avenue','City Proper','Barangay 2','Lucena City','Quezon','4301'),
  (99902,'Residential','CALABARZON','12','Rizal Street','City Proper','Barangay 3','Tayabas City','Quezon','4327'),
  (99902,'Permanent','CALABARZON','12','Rizal Street','City Proper','Barangay 3','Tayabas City','Quezon','4327'),
  (99903,'Residential','CALABARZON','13','Quezon Avenue','City Proper','Barangay 4','Lucena City','Quezon','4301'),
  (99903,'Permanent','CALABARZON','13','Quezon Avenue','City Proper','Barangay 4','Lucena City','Quezon','4301'),
  (99904,'Residential','CALABARZON','14','National Road','Town Proper','Barangay 5','Pagbilao','Quezon','4302'),
  (99904,'Permanent','CALABARZON','14','National Road','Town Proper','Barangay 5','Pagbilao','Quezon','4302'),
  (99905,'Residential','CALABARZON','15','Quezon Avenue','City Proper','Barangay 6','Lucena City','Quezon','4301'),
  (99905,'Permanent','CALABARZON','15','Quezon Avenue','City Proper','Barangay 6','Lucena City','Quezon','4301'),
  (99906,'Residential','CALABARZON','16','Rizal Street','Town Proper','Barangay 7','Sariaya','Quezon','4322'),
  (99906,'Permanent','CALABARZON','16','Rizal Street','Town Proper','Barangay 7','Sariaya','Quezon','4322');

INSERT INTO employee_emergency_contacts
  (employee_id,contact_name,relationship,contact_number,is_primary)
VALUES (99900,'Linda Reyes','Mother','09179911000',1),
       (99901,'Carmen Cruz','Mother','09179911001',1),
       (99902,'Rosa Lopez','Mother','09179911002',1),
       (99903,'Ana Garcia','Mother','09179911003',1),
       (99904,'Lina Mendoza','Mother','09179911004',1),
       (99905,'Maria Santos','Mother','09179911005',1),
       (99906,'Jose Ramos','Father','09179911006',1);

INSERT INTO employee_family (employee_id,member_type,surname,first_name,middle_name,occupation)
VALUES (99900,'Mother','Reyes','Linda','Garcia','Retired clerk'),
       (99901,'Mother','Cruz','Carmen','Lopez','Teacher'),
       (99902,'Mother','Lopez','Rosa','Reyes','Store owner'),
       (99903,'Mother','Garcia','Ana','Santos','Nurse'),
       (99904,'Mother','Mendoza','Lina','Cruz','Teacher'),
       (99905,'Mother','Santos','Maria','Reyes','Store owner'),
       (99906,'Father','Ramos','Jose','Dela Cruz','Mechanic');

INSERT INTO employee_education
  (employee_id,education_level,school_name,degree_course,period_from,period_to,year_graduated,honors_received)
VALUES
  (99900,'College','University of the Philippines','BS Psychology','1995-06-01','1999-04-30','1999','None'),
  (99901,'College','Southern Luzon State University','BS Office Administration','1999-06-01','2003-05-31','2003','None'),
  (99902,'College','Southern Luzon State University','BS Office Administration','2005-06-01','2009-05-31','2009','None'),
  (99903,'College','Southern Luzon State University','BS Office Administration','2010-06-01','2014-05-31','2014','None'),
  (99904,'College','Southern Luzon State University','BS Office Administration','2015-06-01','2019-05-31','2019','None'),
  (99905,'College','Southern Luzon State University','BS Office Administration','2019-06-01','2023-05-31','2023','None'),
  (99906,'College','Southern Luzon State University','BS Office Administration','2018-06-01','2022-05-31','2022','None');

INSERT INTO employee_work_experience
  (employee_id,date_from,date_to,job_title,company_name,monthly_salary,appointment_status,reason_for_leaving)
VALUES
  (99900,'1999-07-01','2007-03-31','Executive Director','Mock Quezon Retail Co.',40000,'Full-time','Career advancement'),
  (99901,'2004-06-01','2012-05-31','Executive Assistant','Mock Quezon Services Co.',32000,'Full-time','Career advancement'),
  (99902,'2010-06-01','2016-07-31','Office Coordinator','Mock Tayabas Services Co.',28000,'Full-time','Career advancement'),
  (99903,'2015-06-01','2020-02-29','Executive Assistant','Mock Lucena Services Co.',24000,'Full-time','Career advancement'),
  (99904,'2019-07-01','2023-04-30','Administrative Assistant','Mock Pagbilao Services Co.',21000,'Full-time','Career advancement'),
  (99905,'2023-07-01','2026-08-15','Administrative Assistant','Mock Lucena Services Co.',22000,'Full-time','Career advancement'),
  (99906,'2022-07-01','2026-09-15','Administrative Assistant','Mock Sariaya Services Co.',21000,'Full-time','Career advancement');

INSERT INTO employee_disclosures
  (employee_id,is_related_to_company,has_admin_offense,has_criminal_charge,
   has_criminal_conviction,has_been_separated,is_pwd,is_solo_parent,
   has_recent_hospital,has_current_treatment)
VALUES (99900,0,0,0,0,0,0,0,0,0),(99901,0,0,0,0,0,0,0,0,0),
       (99902,0,0,0,0,0,0,0,0,0),(99903,0,0,0,0,0,0,0,0,0),
       (99904,0,0,0,0,0,0,0,0,0),(99905,0,0,0,0,0,0,0,0,0),
       (99906,0,0,0,0,0,0,0,0,0);

INSERT INTO employee_trainings
  (employee_id,date_from,date_to,training_title,training_type,no_of_hours,conducted_by)
VALUES
  (99900,'2025-03-01','2025-03-02','Leadership Review','Internal',16,'Mock Training Team'),
  (99901,'2025-04-01','2025-04-02','Executive Office Planning','Internal',16,'Mock Training Team'),
  (99902,'2025-05-01','2025-05-02','Executive Support Review','Internal',16,'Mock Training Team'),
  (99903,'2025-06-01','2025-06-02','Executive Office Management','Internal',16,'Mock Training Team'),
  (99904,'2025-07-01','2025-07-02','Business Correspondence','Internal',16,'Mock Training Team'),
  (99905,'2026-09-15','2026-09-16','Executive Office Orientation','Internal',16,'Mock Training Team'),
  (99906,'2026-10-12','2026-10-13','Executive Office Orientation','Internal',16,'Mock Training Team');

INSERT INTO employee_skills (employee_id,skill_name)
VALUES (99900,'Executive leadership'),(99901,'Executive office planning'),
       (99902,'Administrative delivery'),(99903,'Office management'),
       (99904,'Business correspondence'),(99905,'Content review'),
       (99906,'Administrative reporting');

INSERT INTO employee_references
  (employee_id,reference_name,reference_address,reference_telephone)
VALUES (99900,'Luz Bautista','Lucena City, Quezon','042-991-0100'),
       (99901,'Rosa Villanueva','Lucena City, Quezon','042-991-0101'),
       (99902,'Maria Castillo','Tayabas City, Quezon','042-991-0102'),
       (99903,'Jose Mendoza','Lucena City, Quezon','042-991-0103'),
       (99904,'Ana Ramos','Pagbilao, Quezon','042-991-0104'),
       (99905,'Lina Navarro','Lucena City, Quezon','042-991-0105'),
       (99906,'Ramon Dela Cruz','Sariaya, Quezon','042-991-0106');

-- A second regular Executive Assistant II tests multiple employees sharing one position form.
INSERT INTO employees
  (employee_id,employee_code,first_name,middle_name,last_name,name_extension,
   date_of_birth,place_of_birth,gender,civil_status,hire_date,job_title,
   job_title_id,department_id,rank_category_id,branch_id,reports_to,
   employment_status,employment_type,contract_start_date,contract_end_date,
   profile_picture,is_active)
VALUES (99907,'OP-426','Faye','Santos','Mendoza','','1996-04-12',
        'Lucena City, Quezon','Female','Single','2021-08-02',
        'Executive Assistant II',1102,10,5,102,99900,'Regular','Full-time',
        '2021-08-02','2030-12-31','avatar_m.jpg',1);
INSERT INTO users
  (employee_id,username,email,full_name,password_hash,role,branch_id,
   is_active,account_hold,first_login_completed,must_change_password)
VALUES (99907,'test.op.assistant2b','op.assistant2b@example.invalid','Faye Mendoza',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'Employee',102,1,0,0,1);
INSERT INTO employee_contacts (employee_id,personal_email,mobile_number,telephone_number)
VALUES (99907,'op.assistant2b@example.invalid','09179990007','042-997-0007');
INSERT INTO employee_details (employee_id,height_m,weight_kg,blood_type,citizenship)
VALUES (99907,1.73,70.00,'O+','Filipino');
INSERT INTO employee_government_ids (employee_id,sss_number,philhealth_number,pagibig_number,tin_number)
VALUES (99907,'MOCK-SSS-99907','MOCK-PH-99907','MOCK-PI-99907','MOCK-TIN-99907');
INSERT INTO employee_addresses
  (employee_id,address_type,region,house_no,street,subdivision,barangay,city,province,zip_code)
VALUES (99907,'Residential','CALABARZON','17','Quezon Avenue','City Proper','Barangay 8','Lucena City','Quezon','4301'),
       (99907,'Permanent','CALABARZON','17','Quezon Avenue','City Proper','Barangay 8','Lucena City','Quezon','4301');
INSERT INTO employee_emergency_contacts
  (employee_id,contact_name,relationship,contact_number,is_primary)
VALUES (99907,'Ana Santos','Mother','09179971007',1);
INSERT INTO employee_family (employee_id,member_type,surname,first_name,middle_name,occupation)
VALUES (99907,'Mother','Santos','Ana','Cruz','Teacher');
INSERT INTO employee_education
  (employee_id,education_level,school_name,degree_course,period_from,period_to,year_graduated,honors_received)
VALUES (99907,'College','Southern Luzon State University','BS Office Administration',
        '2013-06-01','2017-05-31','2017','None');
INSERT INTO employee_work_experience
  (employee_id,date_from,date_to,job_title,company_name,monthly_salary,appointment_status,reason_for_leaving)
VALUES (99907,'2017-07-01','2021-07-15','Office Coordinator','Mock Lucena Services Co.',22000,
        'Full-time','Career advancement');
INSERT INTO employee_disclosures
  (employee_id,is_related_to_company,has_admin_offense,has_criminal_charge,
   has_criminal_conviction,has_been_separated,is_pwd,is_solo_parent,
   has_recent_hospital,has_current_treatment)
VALUES (99907,0,0,0,0,0,0,0,0,0);
INSERT INTO employee_trainings
  (employee_id,date_from,date_to,training_title,training_type,no_of_hours,conducted_by)
VALUES (99907,'2025-05-10','2025-05-11','Office Scheduling','Internal',16,'Mock Training Team');
INSERT INTO employee_skills (employee_id,skill_name)
VALUES (99907,'Executive support');
INSERT INTO employee_references
  (employee_id,reference_name,reference_address,reference_telephone)
VALUES (99907,'Maria Castillo','Lucena City, Quezon','042-997-0107');
