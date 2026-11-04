-- General Services test people. Run once on a fresh test database.
-- First import the schema and organization files shown in README.md.
USE raquel_hris;

-- The CEO is needed to create/review the VP for General Services form. The CEO is outside the General Services cycle.
INSERT INTO employees
  (employee_id,employee_code,first_name,middle_name,last_name,name_extension,
   date_of_birth,place_of_birth,gender,civil_status,hire_date,job_title,
   job_title_id,department_id,rank_category_id,branch_id,reports_to,
   employment_status,employment_type,contract_start_date,contract_end_date,
   profile_picture,is_active)
VALUES
  (99600,'OP-478','Isabella','Reyes','Villanueva','','1978-06-18',
   'Lucena City, Quezon','Female','Married','2007-04-02',
   'President and CEO',1100,10,1,102,NULL,'Regular','Full-time',
   '2007-04-02','2030-12-31','avatar_f.jpg',1),
  (99601,'GS-360','Nadine','Cruz','Santos','','1982-02-15',
   'Lucena City, Quezon','Female','Married','2012-06-04',
   'VP for General Services',600,6,1,102,99600,'Regular','Full-time',
   '2012-06-04','2030-12-31','avatar_f.jpg',1),
  (99602,'GS-980','Rafael','Lopez','Reyes','','1988-05-21',
   'Tayabas City, Quezon','Male','Married','2016-08-01',
   'GS Manager I',601,6,3,102,99601,'Regular','Full-time',
   '2016-08-01','2030-12-31','avatar_m.jpg',1),
  (99603,'GS-126','Jonas','Garcia','Cruz','','1993-09-08',
   'Lucena City, Quezon','Male','Single','2020-03-02',
   'GS Supervisor I',605,6,4,102,99602,'Regular','Full-time',
   '2020-03-02','2030-12-31','avatar_m.jpg',1),
  (99604,'GS-669','Tessa','Mendoza','Garcia','','1998-07-18',
   'Pagbilao, Quezon','Female','Single','2023-05-08',
   'Warehouse Staff I',621,6,5,102,99603,'Regular','Full-time',
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
  (99605,'GS-169','Iris','Santos','Navarro','','2002-03-12',
   'Lucena City, Quezon','Female','Single','2026-09-01',
   'Warehouse Staff I',621,6,5,102,99603,'Probationary','Initial','2026-11-30',
   'Full-time','2026-09-01','2027-02-28','avatar_f.jpg',1),
  (99606,'GS-484','Paolo','Ramos','Dela Cruz','','2001-11-25',
   'Sariaya, Quezon','Male','Single','2026-10-01',
   'Warehouse Staff I',621,6,5,102,99603,'Probationary','Initial','2026-12-15',
   'Full-time','2026-10-01','2027-03-31','avatar_m.jpg',1);

-- Local test password for every account: password.
INSERT INTO users
  (employee_id,username,email,full_name,password_hash,role,branch_id,
   is_active,account_hold,first_login_completed,must_change_password)
VALUES
  (99600,'test.gs.ceo','gs.ceo@example.invalid','Isabella Villanueva',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','President and CEO',102,1,0,0,1),
  (99601,'test.gs.vp','gs.vp@example.invalid','Nadine Santos',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99602,'test.gs.manager','gs.manager@example.invalid','Rafael Reyes',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99603,'test.gs.supervisor','gs.supervisor@example.invalid','Jonas Cruz',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99604,'test.gs.warehouse','gs.warehouse@example.invalid','Tessa Garcia',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99605,'test.gs.iris','gs.iris@example.invalid','Iris Navarro',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99606,'test.gs.paolo','gs.paolo@example.invalid','Paolo Dela Cruz',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1);

INSERT INTO employee_contacts (employee_id,personal_email,mobile_number,telephone_number)
VALUES
  (99600,'gs.ceo@example.invalid','09179960000','042-991-0000'),
  (99601,'gs.vp@example.invalid','09179960001','042-991-0001'),
  (99602,'gs.manager@example.invalid','09179960002','042-991-0002'),
  (99603,'gs.supervisor@example.invalid','09179960003','042-991-0003'),
  (99604,'gs.warehouse@example.invalid','09179960004','042-991-0004'),
  (99605,'gs.iris@example.invalid','09179960005','042-991-0005'),
  (99606,'gs.paolo@example.invalid','09179960006','042-991-0006');

INSERT INTO employee_details (employee_id,height_m,weight_kg,blood_type,citizenship)
VALUES (99600,1.64,59.00,'O+','Filipino'),(99601,1.62,57.00,'A+','Filipino'),
       (99602,1.73,70.00,'B+','Filipino'),(99603,1.71,68.00,'O+','Filipino'),
       (99604,1.59,53.00,'A+','Filipino'),(99605,1.60,55.00,'B+','Filipino'),
       (99606,1.74,69.00,'O+','Filipino');

INSERT INTO employee_government_ids (employee_id,sss_number,philhealth_number,pagibig_number,tin_number)
VALUES
  (99600,'MOCK-SSS-99600','MOCK-PH-99600','MOCK-PI-99600','MOCK-TIN-99600'),
  (99601,'MOCK-SSS-99601','MOCK-PH-99601','MOCK-PI-99601','MOCK-TIN-99601'),
  (99602,'MOCK-SSS-99602','MOCK-PH-99602','MOCK-PI-99602','MOCK-TIN-99602'),
  (99603,'MOCK-SSS-99603','MOCK-PH-99603','MOCK-PI-99603','MOCK-TIN-99603'),
  (99604,'MOCK-SSS-99604','MOCK-PH-99604','MOCK-PI-99604','MOCK-TIN-99604'),
  (99605,'MOCK-SSS-99605','MOCK-PH-99605','MOCK-PI-99605','MOCK-TIN-99605'),
  (99606,'MOCK-SSS-99606','MOCK-PH-99606','MOCK-PI-99606','MOCK-TIN-99606');

INSERT INTO employee_addresses
  (employee_id,address_type,region,house_no,street,subdivision,barangay,city,province,zip_code)
VALUES
  (99600,'Residential','CALABARZON','10','Quezon Avenue','City Proper','Barangay 1','Lucena City','Quezon','4301'),
  (99600,'Permanent','CALABARZON','10','Quezon Avenue','City Proper','Barangay 1','Lucena City','Quezon','4301'),
  (99601,'Residential','CALABARZON','11','Quezon Avenue','City Proper','Barangay 2','Lucena City','Quezon','4301'),
  (99601,'Permanent','CALABARZON','11','Quezon Avenue','City Proper','Barangay 2','Lucena City','Quezon','4301'),
  (99602,'Residential','CALABARZON','12','Rizal Street','City Proper','Barangay 3','Tayabas City','Quezon','4327'),
  (99602,'Permanent','CALABARZON','12','Rizal Street','City Proper','Barangay 3','Tayabas City','Quezon','4327'),
  (99603,'Residential','CALABARZON','13','Quezon Avenue','City Proper','Barangay 4','Lucena City','Quezon','4301'),
  (99603,'Permanent','CALABARZON','13','Quezon Avenue','City Proper','Barangay 4','Lucena City','Quezon','4301'),
  (99604,'Residential','CALABARZON','14','National Road','Town Proper','Barangay 5','Pagbilao','Quezon','4302'),
  (99604,'Permanent','CALABARZON','14','National Road','Town Proper','Barangay 5','Pagbilao','Quezon','4302'),
  (99605,'Residential','CALABARZON','15','Quezon Avenue','City Proper','Barangay 6','Lucena City','Quezon','4301'),
  (99605,'Permanent','CALABARZON','15','Quezon Avenue','City Proper','Barangay 6','Lucena City','Quezon','4301'),
  (99606,'Residential','CALABARZON','16','Rizal Street','Town Proper','Barangay 7','Sariaya','Quezon','4322'),
  (99606,'Permanent','CALABARZON','16','Rizal Street','Town Proper','Barangay 7','Sariaya','Quezon','4322');

INSERT INTO employee_emergency_contacts
  (employee_id,contact_name,relationship,contact_number,is_primary)
VALUES (99600,'Linda Reyes','Mother','09179911000',1),
       (99601,'Carmen Cruz','Mother','09179911001',1),
       (99602,'Rosa Lopez','Mother','09179911002',1),
       (99603,'Ana Garcia','Mother','09179911003',1),
       (99604,'Lina Mendoza','Mother','09179911004',1),
       (99605,'Maria Santos','Mother','09179911005',1),
       (99606,'Jose Ramos','Father','09179911006',1);

INSERT INTO employee_family (employee_id,member_type,surname,first_name,middle_name,occupation)
VALUES (99600,'Mother','Reyes','Linda','Garcia','Retired clerk'),
       (99601,'Mother','Cruz','Carmen','Lopez','Teacher'),
       (99602,'Mother','Lopez','Rosa','Reyes','Store owner'),
       (99603,'Mother','Garcia','Ana','Santos','Nurse'),
       (99604,'Mother','Mendoza','Lina','Cruz','Teacher'),
       (99605,'Mother','Santos','Maria','Reyes','Store owner'),
       (99606,'Father','Ramos','Jose','Dela Cruz','Mechanic');

INSERT INTO employee_education
  (employee_id,education_level,school_name,degree_course,period_from,period_to,year_graduated,honors_received)
VALUES
  (99600,'College','University of the Philippines','BS Psychology','1995-06-01','1999-04-30','1999','None'),
  (99601,'College','Southern Luzon State University','BS Business Administration','1999-06-01','2003-05-31','2003','None'),
  (99602,'College','Southern Luzon State University','BS Management','2005-06-01','2009-05-31','2009','None'),
  (99603,'College','Southern Luzon State University','BS Management','2010-06-01','2014-05-31','2014','None'),
  (99604,'College','Southern Luzon State University','BS Business Administration','2015-06-01','2019-05-31','2019','None'),
  (99605,'College','Southern Luzon State University','BS Business Administration','2019-06-01','2023-05-31','2023','None'),
  (99606,'College','Southern Luzon State University','BS Business Administration','2018-06-01','2022-05-31','2022','None');

INSERT INTO employee_work_experience
  (employee_id,date_from,date_to,job_title,company_name,monthly_salary,appointment_status,reason_for_leaving)
VALUES
  (99600,'1999-07-01','2007-03-31','Operations Lead','Mock Quezon Retail Co.',40000,'Full-time','Career advancement'),
  (99601,'2004-06-01','2012-05-31','Property Coordinator','Mock Quezon Realty Co.',32000,'Full-time','Career advancement'),
  (99602,'2010-06-01','2016-07-31','Property Officer','Mock Tayabas Realty Co.',28000,'Full-time','Career advancement'),
  (99603,'2015-06-01','2020-02-29','Property Assistant','Mock Lucena Services Co.',24000,'Full-time','Career advancement'),
  (99604,'2019-07-01','2023-04-30','Administrative Assistant','Mock Pagbilao Services Co.',21000,'Full-time','Career advancement'),
  (99605,'2023-07-01','2026-08-15','Records Assistant','Mock Lucena Services Co.',22000,'Full-time','Career advancement'),
  (99606,'2022-07-01','2026-09-15','Office Assistant','Mock Sariaya Services Co.',21000,'Full-time','Career advancement');

INSERT INTO employee_disclosures
  (employee_id,is_related_to_company,has_admin_offense,has_criminal_charge,
   has_criminal_conviction,has_been_separated,is_pwd,is_solo_parent,
   has_recent_hospital,has_current_treatment)
VALUES (99600,0,0,0,0,0,0,0,0,0),(99601,0,0,0,0,0,0,0,0,0),
       (99602,0,0,0,0,0,0,0,0,0),(99603,0,0,0,0,0,0,0,0,0),
       (99604,0,0,0,0,0,0,0,0,0),(99605,0,0,0,0,0,0,0,0,0),
       (99606,0,0,0,0,0,0,0,0,0);

INSERT INTO employee_trainings
  (employee_id,date_from,date_to,training_title,training_type,no_of_hours,conducted_by)
VALUES
  (99600,'2025-03-01','2025-03-02','Leadership Review','Internal',16,'Mock Training Team'),
  (99601,'2025-04-01','2025-04-02','General Services Planning','Internal',16,'Mock Training Team'),
  (99602,'2025-05-01','2025-05-02','General Services Case Review','Internal',16,'Mock Training Team'),
  (99603,'2025-06-01','2025-06-02','General Services Records Control','Internal',16,'Mock Training Team'),
  (99604,'2025-07-01','2025-07-02','General Services Evidence Basics','Internal',16,'Mock Training Team'),
  (99605,'2026-09-15','2026-09-16','General Services Records Orientation','Internal',16,'Mock Training Team'),
  (99606,'2026-10-12','2026-10-13','General Services Records Orientation','Internal',16,'Mock Training Team');

INSERT INTO employee_skills (employee_id,skill_name)
VALUES (99600,'Executive leadership'),(99601,'General Services planning'),
       (99602,'General Services case review'),(99603,'General Services records control'),
       (99604,'General Services evidence review'),(99605,'Document tracking'),
       (99606,'Records organization');

INSERT INTO employee_references
  (employee_id,reference_name,reference_address,reference_telephone)
VALUES (99600,'Luz Bautista','Lucena City, Quezon','042-991-0100'),
       (99601,'Rosa Villanueva','Lucena City, Quezon','042-991-0101'),
       (99602,'Maria Castillo','Tayabas City, Quezon','042-991-0102'),
       (99603,'Jose Mendoza','Lucena City, Quezon','042-991-0103'),
       (99604,'Ana Ramos','Pagbilao, Quezon','042-991-0104'),
       (99605,'Lina Navarro','Lucena City, Quezon','042-991-0105'),
       (99606,'Ramon Dela Cruz','Sariaya, Quezon','042-991-0106');

-- A second regular staff position checks that the GS Supervisor's forms cover
-- each position, while the probationary employees remain Warehouse Staff I.
INSERT INTO employees
  (employee_id,employee_code,first_name,middle_name,last_name,name_extension,
   date_of_birth,place_of_birth,gender,civil_status,hire_date,job_title,
   job_title_id,department_id,rank_category_id,branch_id,reports_to,
   employment_status,employment_type,contract_start_date,contract_end_date,
   profile_picture,is_active)
VALUES (99607,'GS-144','Gabriel','Santos','Mendoza','','1996-04-12',
        'Lucena City, Quezon','Male','Single','2021-08-02',
        'Driver I',609,6,5,102,99603,'Regular','Full-time',
        '2021-08-02','2030-12-31','avatar_m.jpg',1);
INSERT INTO users
  (employee_id,username,email,full_name,password_hash,role,branch_id,
   is_active,account_hold,first_login_completed,must_change_password)
VALUES (99607,'test.gs.driver','gs.driver@example.invalid','Gabriel Mendoza',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'Employee',102,1,0,0,1);
INSERT INTO employee_contacts (employee_id,personal_email,mobile_number,telephone_number)
VALUES (99607,'gs.driver@example.invalid','09179960007','042-996-0007');
INSERT INTO employee_details (employee_id,height_m,weight_kg,blood_type,citizenship)
VALUES (99607,1.73,70.00,'O+','Filipino');
INSERT INTO employee_government_ids (employee_id,sss_number,philhealth_number,pagibig_number,tin_number)
VALUES (99607,'MOCK-SSS-99607','MOCK-PH-99607','MOCK-PI-99607','MOCK-TIN-99607');
INSERT INTO employee_addresses
  (employee_id,address_type,region,house_no,street,subdivision,barangay,city,province,zip_code)
VALUES (99607,'Residential','CALABARZON','17','Quezon Avenue','City Proper','Barangay 8','Lucena City','Quezon','4301'),
       (99607,'Permanent','CALABARZON','17','Quezon Avenue','City Proper','Barangay 8','Lucena City','Quezon','4301');
INSERT INTO employee_emergency_contacts
  (employee_id,contact_name,relationship,contact_number,is_primary)
VALUES (99607,'Ana Santos','Mother','09179961007',1);
INSERT INTO employee_family (employee_id,member_type,surname,first_name,middle_name,occupation)
VALUES (99607,'Mother','Santos','Ana','Cruz','Teacher');
INSERT INTO employee_education
  (employee_id,education_level,school_name,degree_course,period_from,period_to,year_graduated,honors_received)
VALUES (99607,'College','Southern Luzon State University','BS Business Administration',
        '2013-06-01','2017-05-31','2017','None');
INSERT INTO employee_work_experience
  (employee_id,date_from,date_to,job_title,company_name,monthly_salary,appointment_status,reason_for_leaving)
VALUES (99607,'2017-07-01','2021-07-15','Company Driver','Mock Lucena Services Co.',22000,
        'Full-time','Career advancement');
INSERT INTO employee_disclosures
  (employee_id,is_related_to_company,has_admin_offense,has_criminal_charge,
   has_criminal_conviction,has_been_separated,is_pwd,is_solo_parent,
   has_recent_hospital,has_current_treatment)
VALUES (99607,0,0,0,0,0,0,0,0,0);
INSERT INTO employee_trainings
  (employee_id,date_from,date_to,training_title,training_type,no_of_hours,conducted_by)
VALUES (99607,'2025-05-10','2025-05-11','Fleet Safety','Internal',16,'Mock Training Team');
INSERT INTO employee_skills (employee_id,skill_name)
VALUES (99607,'Fleet operations');
INSERT INTO employee_references
  (employee_id,reference_name,reference_address,reference_telephone)
VALUES (99607,'Maria Castillo','Lucena City, Quezon','042-996-0107');
