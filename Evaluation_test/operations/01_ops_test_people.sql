-- Operations test people. Run once on a fresh test database.
-- First import the schema and organization files shown in README.md.
USE raquel_hris;

-- The CEO is needed to create/review the VP for Operations form. The CEO is outside the Operations cycle.
INSERT INTO employees
  (employee_id,employee_code,first_name,middle_name,last_name,name_extension,
   date_of_birth,place_of_birth,gender,civil_status,hire_date,job_title,
   job_title_id,department_id,rank_category_id,branch_id,reports_to,
   employment_status,employment_type,contract_start_date,contract_end_date,
   profile_picture,is_active)
VALUES
  (99910,'OP-618','Isabella','Reyes','Villanueva','','1978-06-18',
   'Lucena City, Quezon','Female','Married','2007-04-02',
   'President and CEO',1100,10,1,102,NULL,'Regular','Full-time',
   '2007-04-02','2030-12-31','avatar_f.jpg',1),
  (99911,'OPS-338','Vera','Cruz','Santos','','1982-02-15',
   'Lucena City, Quezon','Female','Married','2012-06-04',
   'VP for Operations',1000,11,1,102,99910,'Regular','Full-time',
   '2012-06-04','2030-12-31','avatar_f.jpg',1),
  (99912,'OPS-530','Ramon','Lopez','Reyes','','1988-05-21',
   'Tayabas City, Quezon','Male','Married','2016-08-01',
   'Regional Manager I',1001,11,3,102,99911,'Regular','Full-time',
   '2016-08-01','2030-12-31','avatar_m.jpg',1),
  (99913,'OPS-176','Arman','Garcia','Cruz','','1993-09-08',
   'Lucena City, Quezon','Male','Single','2020-03-02',
   'Area Coordinator I',1004,11,4,102,99912,'Regular','Full-time',
   '2020-03-02','2030-12-31','avatar_m.jpg',1),
  (99914,'OPS-436','Fiona','Mendoza','Garcia','','1998-07-18',
   'Pagbilao, Quezon','Female','Single','2023-05-08',
   'Focal Person I',1007,11,4,102,99913,'Regular','Full-time',
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
  (99915,'OPS-935','Nica','Santos','Navarro','','2002-03-12',
   'Lucena City, Quezon','Female','Single','2026-09-01',
   'Branch Staff I',1012,11,5,102,99914,'Probationary','Initial','2026-11-30',
   'Full-time','2026-09-01','2027-02-28','avatar_f.jpg',1),
  (99916,'OPS-870','Enzo','Ramos','Dela Cruz','','2001-11-25',
   'Sariaya, Quezon','Male','Single','2026-10-01',
   'Branch Staff I',1012,11,5,102,99914,'Probationary','Initial','2026-12-15',
   'Full-time','2026-10-01','2027-03-31','avatar_m.jpg',1);

-- Local test password for every account: password.
INSERT INTO users
  (employee_id,username,email,full_name,password_hash,role,branch_id,
   is_active,account_hold,first_login_completed,must_change_password)
VALUES
  (99910,'test.ops.ceo','ops.ceo@example.invalid','Isabella Villanueva',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','President and CEO',102,1,0,0,1),
  (99911,'test.ops.vp','ops.vp@example.invalid','Vera Santos',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99912,'test.ops.manager','ops.manager@example.invalid','Ramon Reyes',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99913,'test.ops.supervisor','ops.supervisor@example.invalid','Arman Cruz',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99914,'test.ops.focal','ops.focal@example.invalid','Fiona Garcia',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99915,'test.ops.nica','ops.nica@example.invalid','Nica Navarro',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99916,'test.ops.enzo','ops.enzo@example.invalid','Enzo Dela Cruz',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1);

INSERT INTO employee_contacts (employee_id,personal_email,mobile_number,telephone_number)
VALUES
  (99910,'ops.ceo@example.invalid','09179991000','042-991-0000'),
  (99911,'ops.vp@example.invalid','09179991001','042-991-0001'),
  (99912,'ops.manager@example.invalid','09179991002','042-991-0002'),
  (99913,'ops.supervisor@example.invalid','09179991003','042-991-0003'),
  (99914,'ops.focal@example.invalid','09179991004','042-991-0004'),
  (99915,'ops.nica@example.invalid','09179991005','042-991-0005'),
  (99916,'ops.enzo@example.invalid','09179991006','042-991-0006');

INSERT INTO employee_details (employee_id,height_m,weight_kg,blood_type,citizenship)
VALUES (99910,1.64,59.00,'O+','Filipino'),(99911,1.62,57.00,'A+','Filipino'),
       (99912,1.73,70.00,'B+','Filipino'),(99913,1.71,68.00,'O+','Filipino'),
       (99914,1.59,53.00,'A+','Filipino'),(99915,1.60,55.00,'B+','Filipino'),
       (99916,1.74,69.00,'O+','Filipino');

INSERT INTO employee_government_ids (employee_id,sss_number,philhealth_number,pagibig_number,tin_number)
VALUES
  (99910,'MOCK-SSS-99910','MOCK-PH-99910','MOCK-PI-99910','MOCK-TIN-99910'),
  (99911,'MOCK-SSS-99911','MOCK-PH-99911','MOCK-PI-99911','MOCK-TIN-99911'),
  (99912,'MOCK-SSS-99912','MOCK-PH-99912','MOCK-PI-99912','MOCK-TIN-99912'),
  (99913,'MOCK-SSS-99913','MOCK-PH-99913','MOCK-PI-99913','MOCK-TIN-99913'),
  (99914,'MOCK-SSS-99914','MOCK-PH-99914','MOCK-PI-99914','MOCK-TIN-99914'),
  (99915,'MOCK-SSS-99915','MOCK-PH-99915','MOCK-PI-99915','MOCK-TIN-99915'),
  (99916,'MOCK-SSS-99916','MOCK-PH-99916','MOCK-PI-99916','MOCK-TIN-99916');

INSERT INTO employee_addresses
  (employee_id,address_type,region,house_no,street,subdivision,barangay,city,province,zip_code)
VALUES
  (99910,'Residential','CALABARZON','10','Quezon Avenue','City Proper','Barangay 1','Lucena City','Quezon','4301'),
  (99910,'Permanent','CALABARZON','10','Quezon Avenue','City Proper','Barangay 1','Lucena City','Quezon','4301'),
  (99911,'Residential','CALABARZON','11','Quezon Avenue','City Proper','Barangay 2','Lucena City','Quezon','4301'),
  (99911,'Permanent','CALABARZON','11','Quezon Avenue','City Proper','Barangay 2','Lucena City','Quezon','4301'),
  (99912,'Residential','CALABARZON','12','Rizal Street','City Proper','Barangay 3','Tayabas City','Quezon','4327'),
  (99912,'Permanent','CALABARZON','12','Rizal Street','City Proper','Barangay 3','Tayabas City','Quezon','4327'),
  (99913,'Residential','CALABARZON','13','Quezon Avenue','City Proper','Barangay 4','Lucena City','Quezon','4301'),
  (99913,'Permanent','CALABARZON','13','Quezon Avenue','City Proper','Barangay 4','Lucena City','Quezon','4301'),
  (99914,'Residential','CALABARZON','14','National Road','Town Proper','Barangay 5','Pagbilao','Quezon','4302'),
  (99914,'Permanent','CALABARZON','14','National Road','Town Proper','Barangay 5','Pagbilao','Quezon','4302'),
  (99915,'Residential','CALABARZON','15','Quezon Avenue','City Proper','Barangay 6','Lucena City','Quezon','4301'),
  (99915,'Permanent','CALABARZON','15','Quezon Avenue','City Proper','Barangay 6','Lucena City','Quezon','4301'),
  (99916,'Residential','CALABARZON','16','Rizal Street','Town Proper','Barangay 7','Sariaya','Quezon','4322'),
  (99916,'Permanent','CALABARZON','16','Rizal Street','Town Proper','Barangay 7','Sariaya','Quezon','4322');

INSERT INTO employee_emergency_contacts
  (employee_id,contact_name,relationship,contact_number,is_primary)
VALUES (99910,'Linda Reyes','Mother','09179911000',1),
       (99911,'Carmen Cruz','Mother','09179911001',1),
       (99912,'Rosa Lopez','Mother','09179911002',1),
       (99913,'Ana Garcia','Mother','09179911003',1),
       (99914,'Lina Mendoza','Mother','09179911004',1),
       (99915,'Maria Santos','Mother','09179911005',1),
       (99916,'Jose Ramos','Father','09179911006',1);

INSERT INTO employee_family (employee_id,member_type,surname,first_name,middle_name,occupation)
VALUES (99910,'Mother','Reyes','Linda','Garcia','Retired clerk'),
       (99911,'Mother','Cruz','Carmen','Lopez','Teacher'),
       (99912,'Mother','Lopez','Rosa','Reyes','Store owner'),
       (99913,'Mother','Garcia','Ana','Santos','Nurse'),
       (99914,'Mother','Mendoza','Lina','Cruz','Teacher'),
       (99915,'Mother','Santos','Maria','Reyes','Store owner'),
       (99916,'Father','Ramos','Jose','Dela Cruz','Mechanic');

INSERT INTO employee_education
  (employee_id,education_level,school_name,degree_course,period_from,period_to,year_graduated,honors_received)
VALUES
  (99910,'College','University of the Philippines','BS Psychology','1995-06-01','1999-04-30','1999','None'),
  (99911,'College','Southern Luzon State University','BS Business Administration','1999-06-01','2003-05-31','2003','None'),
  (99912,'College','Southern Luzon State University','BS Management','2005-06-01','2009-05-31','2009','None'),
  (99913,'College','Southern Luzon State University','BS Management','2010-06-01','2014-05-31','2014','None'),
  (99914,'College','Southern Luzon State University','BS Business Administration','2015-06-01','2019-05-31','2019','None'),
  (99915,'College','Southern Luzon State University','BS Business Administration','2019-06-01','2023-05-31','2023','None'),
  (99916,'College','Southern Luzon State University','BS Business Administration','2018-06-01','2022-05-31','2022','None');

INSERT INTO employee_work_experience
  (employee_id,date_from,date_to,job_title,company_name,monthly_salary,appointment_status,reason_for_leaving)
VALUES
  (99910,'1999-07-01','2007-03-31','Operations Lead','Mock Quezon Retail Co.',40000,'Full-time','Career advancement'),
  (99911,'2004-06-01','2012-05-31','Property Coordinator','Mock Quezon Realty Co.',32000,'Full-time','Career advancement'),
  (99912,'2010-06-01','2016-07-31','Property Officer','Mock Tayabas Realty Co.',28000,'Full-time','Career advancement'),
  (99913,'2015-06-01','2020-02-29','Property Assistant','Mock Lucena Services Co.',24000,'Full-time','Career advancement'),
  (99914,'2019-07-01','2023-04-30','Administrative Assistant','Mock Pagbilao Services Co.',21000,'Full-time','Career advancement'),
  (99915,'2023-07-01','2026-08-15','Records Assistant','Mock Lucena Services Co.',22000,'Full-time','Career advancement'),
  (99916,'2022-07-01','2026-09-15','Office Assistant','Mock Sariaya Services Co.',21000,'Full-time','Career advancement');

INSERT INTO employee_disclosures
  (employee_id,is_related_to_company,has_admin_offense,has_criminal_charge,
   has_criminal_conviction,has_been_separated,is_pwd,is_solo_parent,
   has_recent_hospital,has_current_treatment)
VALUES (99910,0,0,0,0,0,0,0,0,0),(99911,0,0,0,0,0,0,0,0,0),
       (99912,0,0,0,0,0,0,0,0,0),(99913,0,0,0,0,0,0,0,0,0),
       (99914,0,0,0,0,0,0,0,0,0),(99915,0,0,0,0,0,0,0,0,0),
       (99916,0,0,0,0,0,0,0,0,0);

INSERT INTO employee_trainings
  (employee_id,date_from,date_to,training_title,training_type,no_of_hours,conducted_by)
VALUES
  (99910,'2025-03-01','2025-03-02','Leadership Review','Internal',16,'Mock Training Team'),
  (99911,'2025-04-01','2025-04-02','Operations Planning','Internal',16,'Mock Training Team'),
  (99912,'2025-05-01','2025-05-02','Operations Case Review','Internal',16,'Mock Training Team'),
  (99913,'2025-06-01','2025-06-02','Operations Records Control','Internal',16,'Mock Training Team'),
  (99914,'2025-07-01','2025-07-02','Operations Evidence Basics','Internal',16,'Mock Training Team'),
  (99915,'2026-09-15','2026-09-16','Operations Records Orientation','Internal',16,'Mock Training Team'),
  (99916,'2026-10-12','2026-10-13','Operations Records Orientation','Internal',16,'Mock Training Team');

INSERT INTO employee_skills (employee_id,skill_name)
VALUES (99910,'Executive leadership'),(99911,'Operations planning'),
       (99912,'Operations case review'),(99913,'Operations records control'),
       (99914,'Operations evidence review'),(99915,'Document tracking'),
       (99916,'Records organization');

INSERT INTO employee_references
  (employee_id,reference_name,reference_address,reference_telephone)
VALUES (99910,'Luz Bautista','Lucena City, Quezon','042-991-0100'),
       (99911,'Rosa Villanueva','Lucena City, Quezon','042-991-0101'),
       (99912,'Maria Castillo','Tayabas City, Quezon','042-991-0102'),
       (99913,'Jose Mendoza','Lucena City, Quezon','042-991-0103'),
       (99914,'Ana Ramos','Pagbilao, Quezon','042-991-0104'),
       (99915,'Lina Navarro','Lucena City, Quezon','042-991-0105'),
       (99916,'Ramon Dela Cruz','Sariaya, Quezon','042-991-0106');

-- Branch Staff I is reviewed by the Focal Person. The two probationary
-- employees share the same position but remain outside regular cycles.
INSERT INTO employees
  (employee_id,employee_code,first_name,middle_name,last_name,name_extension,
   date_of_birth,place_of_birth,gender,civil_status,hire_date,job_title,
   job_title_id,department_id,rank_category_id,branch_id,reports_to,
   employment_status,employment_type,contract_start_date,contract_end_date,
   profile_picture,is_active)
VALUES (99917,'OPS-532','Berto','Santos','Mendoza','','1996-04-12',
        'Lucena City, Quezon','Male','Single','2021-08-02',
        'Branch Staff I',1012,11,5,102,99914,'Regular','Full-time',
        '2021-08-02','2030-12-31','avatar_m.jpg',1);
INSERT INTO users
  (employee_id,username,email,full_name,password_hash,role,branch_id,
   is_active,account_hold,first_login_completed,must_change_password)
VALUES (99917,'test.ops.branch','ops.branch@example.invalid','Berto Mendoza',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'Employee',102,1,0,0,1);
INSERT INTO employee_contacts (employee_id,personal_email,mobile_number,telephone_number)
VALUES (99917,'ops.branch@example.invalid','09179991007','042-996-0007');
INSERT INTO employee_details (employee_id,height_m,weight_kg,blood_type,citizenship)
VALUES (99917,1.73,70.00,'O+','Filipino');
INSERT INTO employee_government_ids (employee_id,sss_number,philhealth_number,pagibig_number,tin_number)
VALUES (99917,'MOCK-SSS-99917','MOCK-PH-99917','MOCK-PI-99917','MOCK-TIN-99917');
INSERT INTO employee_addresses
  (employee_id,address_type,region,house_no,street,subdivision,barangay,city,province,zip_code)
VALUES (99917,'Residential','CALABARZON','17','Quezon Avenue','City Proper','Barangay 8','Lucena City','Quezon','4301'),
       (99917,'Permanent','CALABARZON','17','Quezon Avenue','City Proper','Barangay 8','Lucena City','Quezon','4301');
INSERT INTO employee_emergency_contacts
  (employee_id,contact_name,relationship,contact_number,is_primary)
VALUES (99917,'Ana Santos','Mother','09179961007',1);
INSERT INTO employee_family (employee_id,member_type,surname,first_name,middle_name,occupation)
VALUES (99917,'Mother','Santos','Ana','Cruz','Teacher');
INSERT INTO employee_education
  (employee_id,education_level,school_name,degree_course,period_from,period_to,year_graduated,honors_received)
VALUES (99917,'College','Southern Luzon State University','BS Business Administration',
        '2013-06-01','2017-05-31','2017','None');
INSERT INTO employee_work_experience
  (employee_id,date_from,date_to,job_title,company_name,monthly_salary,appointment_status,reason_for_leaving)
VALUES (99917,'2017-07-01','2021-07-15','Branch Associate','Mock Lucena Services Co.',22000,
        'Full-time','Career advancement');
INSERT INTO employee_disclosures
  (employee_id,is_related_to_company,has_admin_offense,has_criminal_charge,
   has_criminal_conviction,has_been_separated,is_pwd,is_solo_parent,
   has_recent_hospital,has_current_treatment)
VALUES (99917,0,0,0,0,0,0,0,0,0);
INSERT INTO employee_trainings
  (employee_id,date_from,date_to,training_title,training_type,no_of_hours,conducted_by)
VALUES (99917,'2025-05-10','2025-05-11','Branch Service Review','Internal',16,'Mock Training Team');
INSERT INTO employee_skills (employee_id,skill_name)
VALUES (99917,'Branch operations');
INSERT INTO employee_references
  (employee_id,reference_name,reference_address,reference_telephone)
VALUES (99917,'Maria Castillo','Lucena City, Quezon','042-996-0107');
