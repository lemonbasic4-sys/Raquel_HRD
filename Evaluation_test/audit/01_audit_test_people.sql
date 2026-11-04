-- Audit test people. Run once on a fresh test database.
-- First import the schema and organization files shown in README.md.
USE raquel_hris;

-- The CEO is needed to create/review the Audit Manager I form. The CEO is outside the Audit cycle.
INSERT INTO employees
  (employee_id,employee_code,first_name,middle_name,last_name,name_extension,
   date_of_birth,place_of_birth,gender,civil_status,hire_date,job_title,
   job_title_id,department_id,rank_category_id,branch_id,reports_to,
   employment_status,employment_type,contract_start_date,contract_end_date,
   profile_picture,is_active)
VALUES
  (99200,'OP-560','Isabella','Reyes','Villanueva','','1978-06-18',
   'Lucena City, Quezon','Female','Married','2007-04-02',
   'President and CEO',1100,10,1,102,NULL,'Regular','Full-time',
   '2007-04-02','2030-12-31','avatar_f.jpg',1),
  (99201,'AUD-466','Nadine','Cruz','Santos','','1982-02-15',
   'Lucena City, Quezon','Female','Married','2012-06-04',
   'Audit Manager I',200,2,3,102,99200,'Regular','Full-time',
   '2012-06-04','2030-12-31','avatar_f.jpg',1),
  (99202,'AUD-501','Rafael','Lopez','Reyes','','1988-05-21',
   'Tayabas City, Quezon','Male','Married','2016-08-01',
   'Audit Manager II',201,2,3,102,99201,'Regular','Full-time',
   '2016-08-01','2030-12-31','avatar_m.jpg',1),
  (99203,'AUD-647','Jonas','Garcia','Cruz','','1993-09-08',
   'Lucena City, Quezon','Male','Single','2020-03-02',
   'Audit Supervisor I',203,2,4,102,99202,'Regular','Full-time',
   '2020-03-02','2030-12-31','avatar_m.jpg',1),
  (99204,'AUD-312','Tessa','Mendoza','Garcia','','1998-07-18',
   'Pagbilao, Quezon','Female','Single','2023-05-08',
   'Auditor I',206,2,5,102,99203,'Regular','Full-time',
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
  (99205,'AUD-661','Iris','Santos','Navarro','','2002-03-12',
   'Lucena City, Quezon','Female','Single','2026-09-01',
   'Auditor I',206,2,5,102,99203,'Probationary','Initial','2026-11-30',
   'Full-time','2026-09-01','2027-02-28','avatar_f.jpg',1),
  (99206,'AUD-270','Paolo','Ramos','Dela Cruz','','2001-11-25',
   'Sariaya, Quezon','Male','Single','2026-10-01',
   'Auditor I',206,2,5,102,99203,'Probationary','Initial','2026-12-15',
   'Full-time','2026-10-01','2027-03-31','avatar_m.jpg',1);

-- Local test password for every account: password.
INSERT INTO users
  (employee_id,username,email,full_name,password_hash,role,branch_id,
   is_active,account_hold,first_login_completed,must_change_password)
VALUES
  (99200,'test.audit.ceo','audit.ceo@example.invalid','Isabella Villanueva',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','President and CEO',102,1,0,0,1),
  (99201,'test.audit.manager1','audit.manager1@example.invalid','Nadine Santos',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99202,'test.audit.manager2','audit.manager2@example.invalid','Rafael Reyes',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99203,'test.audit.supervisor','audit.supervisor@example.invalid','Jonas Cruz',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99204,'test.audit.auditor','audit.auditor@example.invalid','Tessa Garcia',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99205,'test.audit.iris','audit.iris@example.invalid','Iris Navarro',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99206,'test.audit.paolo','audit.paolo@example.invalid','Paolo Dela Cruz',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1);

INSERT INTO employee_contacts (employee_id,personal_email,mobile_number,telephone_number)
VALUES
  (99200,'audit.ceo@example.invalid','09179920000','042-991-0000'),
  (99201,'audit.manager1@example.invalid','09179920001','042-991-0001'),
  (99202,'audit.manager2@example.invalid','09179920002','042-991-0002'),
  (99203,'audit.supervisor@example.invalid','09179920003','042-991-0003'),
  (99204,'audit.auditor@example.invalid','09179920004','042-991-0004'),
  (99205,'audit.iris@example.invalid','09179920005','042-991-0005'),
  (99206,'audit.paolo@example.invalid','09179920006','042-991-0006');

INSERT INTO employee_details (employee_id,height_m,weight_kg,blood_type,citizenship)
VALUES (99200,1.64,59.00,'O+','Filipino'),(99201,1.62,57.00,'A+','Filipino'),
       (99202,1.73,70.00,'B+','Filipino'),(99203,1.71,68.00,'O+','Filipino'),
       (99204,1.59,53.00,'A+','Filipino'),(99205,1.60,55.00,'B+','Filipino'),
       (99206,1.74,69.00,'O+','Filipino');

INSERT INTO employee_government_ids (employee_id,sss_number,philhealth_number,pagibig_number,tin_number)
VALUES
  (99200,'MOCK-SSS-99200','MOCK-PH-99200','MOCK-PI-99200','MOCK-TIN-99200'),
  (99201,'MOCK-SSS-99201','MOCK-PH-99201','MOCK-PI-99201','MOCK-TIN-99201'),
  (99202,'MOCK-SSS-99202','MOCK-PH-99202','MOCK-PI-99202','MOCK-TIN-99202'),
  (99203,'MOCK-SSS-99203','MOCK-PH-99203','MOCK-PI-99203','MOCK-TIN-99203'),
  (99204,'MOCK-SSS-99204','MOCK-PH-99204','MOCK-PI-99204','MOCK-TIN-99204'),
  (99205,'MOCK-SSS-99205','MOCK-PH-99205','MOCK-PI-99205','MOCK-TIN-99205'),
  (99206,'MOCK-SSS-99206','MOCK-PH-99206','MOCK-PI-99206','MOCK-TIN-99206');

INSERT INTO employee_addresses
  (employee_id,address_type,region,house_no,street,subdivision,barangay,city,province,zip_code)
VALUES
  (99200,'Residential','CALABARZON','10','Quezon Avenue','City Proper','Barangay 1','Lucena City','Quezon','4301'),
  (99200,'Permanent','CALABARZON','10','Quezon Avenue','City Proper','Barangay 1','Lucena City','Quezon','4301'),
  (99201,'Residential','CALABARZON','11','Quezon Avenue','City Proper','Barangay 2','Lucena City','Quezon','4301'),
  (99201,'Permanent','CALABARZON','11','Quezon Avenue','City Proper','Barangay 2','Lucena City','Quezon','4301'),
  (99202,'Residential','CALABARZON','12','Rizal Street','City Proper','Barangay 3','Tayabas City','Quezon','4327'),
  (99202,'Permanent','CALABARZON','12','Rizal Street','City Proper','Barangay 3','Tayabas City','Quezon','4327'),
  (99203,'Residential','CALABARZON','13','Quezon Avenue','City Proper','Barangay 4','Lucena City','Quezon','4301'),
  (99203,'Permanent','CALABARZON','13','Quezon Avenue','City Proper','Barangay 4','Lucena City','Quezon','4301'),
  (99204,'Residential','CALABARZON','14','National Road','Town Proper','Barangay 5','Pagbilao','Quezon','4302'),
  (99204,'Permanent','CALABARZON','14','National Road','Town Proper','Barangay 5','Pagbilao','Quezon','4302'),
  (99205,'Residential','CALABARZON','15','Quezon Avenue','City Proper','Barangay 6','Lucena City','Quezon','4301'),
  (99205,'Permanent','CALABARZON','15','Quezon Avenue','City Proper','Barangay 6','Lucena City','Quezon','4301'),
  (99206,'Residential','CALABARZON','16','Rizal Street','Town Proper','Barangay 7','Sariaya','Quezon','4322'),
  (99206,'Permanent','CALABARZON','16','Rizal Street','Town Proper','Barangay 7','Sariaya','Quezon','4322');

INSERT INTO employee_emergency_contacts
  (employee_id,contact_name,relationship,contact_number,is_primary)
VALUES (99200,'Linda Reyes','Mother','09179911000',1),
       (99201,'Carmen Cruz','Mother','09179911001',1),
       (99202,'Rosa Lopez','Mother','09179911002',1),
       (99203,'Ana Garcia','Mother','09179911003',1),
       (99204,'Lina Mendoza','Mother','09179911004',1),
       (99205,'Maria Santos','Mother','09179911005',1),
       (99206,'Jose Ramos','Father','09179911006',1);

INSERT INTO employee_family (employee_id,member_type,surname,first_name,middle_name,occupation)
VALUES (99200,'Mother','Reyes','Linda','Garcia','Retired clerk'),
       (99201,'Mother','Cruz','Carmen','Lopez','Teacher'),
       (99202,'Mother','Lopez','Rosa','Reyes','Store owner'),
       (99203,'Mother','Garcia','Ana','Santos','Nurse'),
       (99204,'Mother','Mendoza','Lina','Cruz','Teacher'),
       (99205,'Mother','Santos','Maria','Reyes','Store owner'),
       (99206,'Father','Ramos','Jose','Dela Cruz','Mechanic');

INSERT INTO employee_education
  (employee_id,education_level,school_name,degree_course,period_from,period_to,year_graduated,honors_received)
VALUES
  (99200,'College','University of the Philippines','BS Psychology','1995-06-01','1999-04-30','1999','None'),
  (99201,'College','Southern Luzon State University','BS Business Administration','1999-06-01','2003-05-31','2003','None'),
  (99202,'College','Southern Luzon State University','BS Management','2005-06-01','2009-05-31','2009','None'),
  (99203,'College','Southern Luzon State University','BS Management','2010-06-01','2014-05-31','2014','None'),
  (99204,'College','Southern Luzon State University','BS Business Administration','2015-06-01','2019-05-31','2019','None'),
  (99205,'College','Southern Luzon State University','BS Business Administration','2019-06-01','2023-05-31','2023','None'),
  (99206,'College','Southern Luzon State University','BS Business Administration','2018-06-01','2022-05-31','2022','None');

INSERT INTO employee_work_experience
  (employee_id,date_from,date_to,job_title,company_name,monthly_salary,appointment_status,reason_for_leaving)
VALUES
  (99200,'1999-07-01','2007-03-31','Operations Lead','Mock Quezon Retail Co.',40000,'Full-time','Career advancement'),
  (99201,'2004-06-01','2012-05-31','Property Coordinator','Mock Quezon Realty Co.',32000,'Full-time','Career advancement'),
  (99202,'2010-06-01','2016-07-31','Property Officer','Mock Tayabas Realty Co.',28000,'Full-time','Career advancement'),
  (99203,'2015-06-01','2020-02-29','Property Assistant','Mock Lucena Services Co.',24000,'Full-time','Career advancement'),
  (99204,'2019-07-01','2023-04-30','Administrative Assistant','Mock Pagbilao Services Co.',21000,'Full-time','Career advancement'),
  (99205,'2023-07-01','2026-08-15','Records Assistant','Mock Lucena Services Co.',22000,'Full-time','Career advancement'),
  (99206,'2022-07-01','2026-09-15','Office Assistant','Mock Sariaya Services Co.',21000,'Full-time','Career advancement');

INSERT INTO employee_disclosures
  (employee_id,is_related_to_company,has_admin_offense,has_criminal_charge,
   has_criminal_conviction,has_been_separated,is_pwd,is_solo_parent,
   has_recent_hospital,has_current_treatment)
VALUES (99200,0,0,0,0,0,0,0,0,0),(99201,0,0,0,0,0,0,0,0,0),
       (99202,0,0,0,0,0,0,0,0,0),(99203,0,0,0,0,0,0,0,0,0),
       (99204,0,0,0,0,0,0,0,0,0),(99205,0,0,0,0,0,0,0,0,0),
       (99206,0,0,0,0,0,0,0,0,0);

INSERT INTO employee_trainings
  (employee_id,date_from,date_to,training_title,training_type,no_of_hours,conducted_by)
VALUES
  (99200,'2025-03-01','2025-03-02','Leadership Review','Internal',16,'Mock Training Team'),
  (99201,'2025-04-01','2025-04-02','Audit Planning','Internal',16,'Mock Training Team'),
  (99202,'2025-05-01','2025-05-02','Audit Case Review','Internal',16,'Mock Training Team'),
  (99203,'2025-06-01','2025-06-02','Audit Records Control','Internal',16,'Mock Training Team'),
  (99204,'2025-07-01','2025-07-02','Audit Evidence Basics','Internal',16,'Mock Training Team'),
  (99205,'2026-09-15','2026-09-16','Audit Records Orientation','Internal',16,'Mock Training Team'),
  (99206,'2026-10-12','2026-10-13','Audit Records Orientation','Internal',16,'Mock Training Team');

INSERT INTO employee_skills (employee_id,skill_name)
VALUES (99200,'Executive leadership'),(99201,'Audit planning'),
       (99202,'Audit case review'),(99203,'Audit records control'),
       (99204,'Audit evidence review'),(99205,'Document tracking'),
       (99206,'Records organization');

INSERT INTO employee_references
  (employee_id,reference_name,reference_address,reference_telephone)
VALUES (99200,'Luz Bautista','Lucena City, Quezon','042-991-0100'),
       (99201,'Rosa Villanueva','Lucena City, Quezon','042-991-0101'),
       (99202,'Maria Castillo','Tayabas City, Quezon','042-991-0102'),
       (99203,'Jose Mendoza','Lucena City, Quezon','042-991-0103'),
       (99204,'Ana Ramos','Pagbilao, Quezon','042-991-0104'),
       (99205,'Lina Navarro','Lucena City, Quezon','042-991-0105'),
       (99206,'Ramon Dela Cruz','Sariaya, Quezon','042-991-0106');
