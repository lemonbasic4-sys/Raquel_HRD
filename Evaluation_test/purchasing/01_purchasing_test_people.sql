-- Purchasing test people. Run once on a fresh test database.
-- First import the schema and organization files shown in README.md.
USE raquel_hris;

-- The GS Manager I creates and reviews the Procurement Supervisor I form.
-- The GS Manager is outside the Purchasing cycle.
INSERT INTO employees
  (employee_id,employee_code,first_name,middle_name,last_name,name_extension,
   date_of_birth,place_of_birth,gender,civil_status,hire_date,job_title,
   job_title_id,department_id,rank_category_id,branch_id,reports_to,
   employment_status,employment_type,contract_start_date,contract_end_date,
   profile_picture,is_active)
VALUES
  (99920,'GS-314','Isabella','Reyes','Villanueva','','1978-06-18',
   'Lucena City, Quezon','Female','Married','2007-04-02',
   'GS Manager I',601,6,3,102,NULL,'Regular','Full-time',
   '2007-04-02','2030-12-31','avatar_f.jpg',1),
  (99921,'PUR-518','Mila','Cruz','Santos','','1982-02-15',
   'Lucena City, Quezon','Female','Married','2012-06-04',
   'Procurement Supervisor I',1200,12,4,102,99920,'Regular','Full-time',
   '2012-06-04','2030-12-31','avatar_f.jpg',1),
  (99922,'PUR-267','Vince','Lopez','Reyes','','1988-05-21',
   'Tayabas City, Quezon','Male','Married','2016-08-01',
   'Purchasing Staff I',1202,12,5,102,99921,'Regular','Full-time',
   '2016-08-01','2030-12-31','avatar_m.jpg',1),
  (99923,'PUR-844','Dante','Garcia','Cruz','','1993-09-08',
   'Lucena City, Quezon','Male','Single','2020-03-02',
   'Purchasing Staff I',1202,12,5,102,99921,'Regular','Full-time',
   '2020-03-02','2030-12-31','avatar_m.jpg',1),
  (99924,'PUR-341','Rosalie','Mendoza','Garcia','','1998-07-18',
   'Pagbilao, Quezon','Female','Single','2023-05-08',
   'Purchasing Staff I',1202,12,5,102,99921,'Regular','Full-time',
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
  (99925,'PUR-317','Cora','Santos','Navarro','','2002-03-12',
   'Lucena City, Quezon','Female','Single','2026-09-01',
   'Purchasing Staff I',1202,12,5,102,99921,'Probationary','Initial','2026-11-30',
   'Full-time','2026-09-01','2027-02-28','avatar_f.jpg',1),
  (99926,'PUR-419','Gio','Ramos','Dela Cruz','','2001-11-25',
   'Sariaya, Quezon','Male','Single','2026-10-01',
   'Purchasing Staff I',1202,12,5,102,99921,'Probationary','Initial','2026-12-15',
   'Full-time','2026-10-01','2027-03-31','avatar_m.jpg',1);

-- Local test password for every account: password.
INSERT INTO users
  (employee_id,username,email,full_name,password_hash,role,branch_id,
   is_active,account_hold,first_login_completed,must_change_password)
VALUES
  (99920,'test.purchasing.gs_manager','purchasing.gs_manager@example.invalid','Isabella Villanueva',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99921,'test.purchasing.supervisor','purchasing.supervisor@example.invalid','Mila Santos',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99922,'test.purchasing.staff1','purchasing.staff1@example.invalid','Vince Reyes',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99923,'test.purchasing.staff2','purchasing.staff2@example.invalid','Dante Cruz',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99924,'test.purchasing.staff3','purchasing.staff3@example.invalid','Rosalie Garcia',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99925,'test.purchasing.cora','purchasing.cora@example.invalid','Cora Navarro',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99926,'test.purchasing.gio','purchasing.gio@example.invalid','Gio Dela Cruz',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1);

INSERT INTO employee_contacts (employee_id,personal_email,mobile_number,telephone_number)
VALUES
  (99920,'purchasing.gs_manager@example.invalid','09179992000','042-991-0000'),
  (99921,'purchasing.supervisor@example.invalid','09179992001','042-991-0001'),
  (99922,'purchasing.staff1@example.invalid','09179992002','042-991-0002'),
  (99923,'purchasing.staff2@example.invalid','09179992003','042-991-0003'),
  (99924,'purchasing.staff3@example.invalid','09179992004','042-991-0004'),
  (99925,'purchasing.cora@example.invalid','09179992005','042-991-0005'),
  (99926,'purchasing.gio@example.invalid','09179992006','042-991-0006');

INSERT INTO employee_details (employee_id,height_m,weight_kg,blood_type,citizenship)
VALUES (99920,1.64,59.00,'O+','Filipino'),(99921,1.62,57.00,'A+','Filipino'),
       (99922,1.73,70.00,'B+','Filipino'),(99923,1.71,68.00,'O+','Filipino'),
       (99924,1.59,53.00,'A+','Filipino'),(99925,1.60,55.00,'B+','Filipino'),
       (99926,1.74,69.00,'O+','Filipino');

INSERT INTO employee_government_ids (employee_id,sss_number,philhealth_number,pagibig_number,tin_number)
VALUES
  (99920,'MOCK-SSS-99920','MOCK-PH-99920','MOCK-PI-99920','MOCK-TIN-99920'),
  (99921,'MOCK-SSS-99921','MOCK-PH-99921','MOCK-PI-99921','MOCK-TIN-99921'),
  (99922,'MOCK-SSS-99922','MOCK-PH-99922','MOCK-PI-99922','MOCK-TIN-99922'),
  (99923,'MOCK-SSS-99923','MOCK-PH-99923','MOCK-PI-99923','MOCK-TIN-99923'),
  (99924,'MOCK-SSS-99924','MOCK-PH-99924','MOCK-PI-99924','MOCK-TIN-99924'),
  (99925,'MOCK-SSS-99925','MOCK-PH-99925','MOCK-PI-99925','MOCK-TIN-99925'),
  (99926,'MOCK-SSS-99926','MOCK-PH-99926','MOCK-PI-99926','MOCK-TIN-99926');

INSERT INTO employee_addresses
  (employee_id,address_type,region,house_no,street,subdivision,barangay,city,province,zip_code)
VALUES
  (99920,'Residential','CALABARZON','10','Quezon Avenue','City Proper','Barangay 1','Lucena City','Quezon','4301'),
  (99920,'Permanent','CALABARZON','10','Quezon Avenue','City Proper','Barangay 1','Lucena City','Quezon','4301'),
  (99921,'Residential','CALABARZON','11','Quezon Avenue','City Proper','Barangay 2','Lucena City','Quezon','4301'),
  (99921,'Permanent','CALABARZON','11','Quezon Avenue','City Proper','Barangay 2','Lucena City','Quezon','4301'),
  (99922,'Residential','CALABARZON','12','Rizal Street','City Proper','Barangay 3','Tayabas City','Quezon','4327'),
  (99922,'Permanent','CALABARZON','12','Rizal Street','City Proper','Barangay 3','Tayabas City','Quezon','4327'),
  (99923,'Residential','CALABARZON','13','Quezon Avenue','City Proper','Barangay 4','Lucena City','Quezon','4301'),
  (99923,'Permanent','CALABARZON','13','Quezon Avenue','City Proper','Barangay 4','Lucena City','Quezon','4301'),
  (99924,'Residential','CALABARZON','14','National Road','Town Proper','Barangay 5','Pagbilao','Quezon','4302'),
  (99924,'Permanent','CALABARZON','14','National Road','Town Proper','Barangay 5','Pagbilao','Quezon','4302'),
  (99925,'Residential','CALABARZON','15','Quezon Avenue','City Proper','Barangay 6','Lucena City','Quezon','4301'),
  (99925,'Permanent','CALABARZON','15','Quezon Avenue','City Proper','Barangay 6','Lucena City','Quezon','4301'),
  (99926,'Residential','CALABARZON','16','Rizal Street','Town Proper','Barangay 7','Sariaya','Quezon','4322'),
  (99926,'Permanent','CALABARZON','16','Rizal Street','Town Proper','Barangay 7','Sariaya','Quezon','4322');

INSERT INTO employee_emergency_contacts
  (employee_id,contact_name,relationship,contact_number,is_primary)
VALUES (99920,'Linda Reyes','Mother','09179911000',1),
       (99921,'Carmen Cruz','Mother','09179911001',1),
       (99922,'Rosa Lopez','Mother','09179911002',1),
       (99923,'Ana Garcia','Mother','09179911003',1),
       (99924,'Lina Mendoza','Mother','09179911004',1),
       (99925,'Maria Santos','Mother','09179911005',1),
       (99926,'Jose Ramos','Father','09179911006',1);

INSERT INTO employee_family (employee_id,member_type,surname,first_name,middle_name,occupation)
VALUES (99920,'Mother','Reyes','Linda','Garcia','Retired clerk'),
       (99921,'Mother','Cruz','Carmen','Lopez','Teacher'),
       (99922,'Mother','Lopez','Rosa','Reyes','Store owner'),
       (99923,'Mother','Garcia','Ana','Santos','Nurse'),
       (99924,'Mother','Mendoza','Lina','Cruz','Teacher'),
       (99925,'Mother','Santos','Maria','Reyes','Store owner'),
       (99926,'Father','Ramos','Jose','Dela Cruz','Mechanic');

INSERT INTO employee_education
  (employee_id,education_level,school_name,degree_course,period_from,period_to,year_graduated,honors_received)
VALUES
  (99920,'College','University of the Philippines','BS Psychology','1995-06-01','1999-04-30','1999','None'),
  (99921,'College','Southern Luzon State University','BS Business Administration','1999-06-01','2003-05-31','2003','None'),
  (99922,'College','Southern Luzon State University','BS Management','2005-06-01','2009-05-31','2009','None'),
  (99923,'College','Southern Luzon State University','BS Management','2010-06-01','2014-05-31','2014','None'),
  (99924,'College','Southern Luzon State University','BS Business Administration','2015-06-01','2019-05-31','2019','None'),
  (99925,'College','Southern Luzon State University','BS Business Administration','2019-06-01','2023-05-31','2023','None'),
  (99926,'College','Southern Luzon State University','BS Business Administration','2018-06-01','2022-05-31','2022','None');

INSERT INTO employee_work_experience
  (employee_id,date_from,date_to,job_title,company_name,monthly_salary,appointment_status,reason_for_leaving)
VALUES
  (99920,'1999-07-01','2007-03-31','Operations Lead','Mock Quezon Retail Co.',40000,'Full-time','Career advancement'),
  (99921,'2004-06-01','2012-05-31','Property Coordinator','Mock Quezon Realty Co.',32000,'Full-time','Career advancement'),
  (99922,'2010-06-01','2016-07-31','Property Officer','Mock Tayabas Realty Co.',28000,'Full-time','Career advancement'),
  (99923,'2015-06-01','2020-02-29','Property Assistant','Mock Lucena Services Co.',24000,'Full-time','Career advancement'),
  (99924,'2019-07-01','2023-04-30','Administrative Assistant','Mock Pagbilao Services Co.',21000,'Full-time','Career advancement'),
  (99925,'2023-07-01','2026-08-15','Records Assistant','Mock Lucena Services Co.',22000,'Full-time','Career advancement'),
  (99926,'2022-07-01','2026-09-15','Office Assistant','Mock Sariaya Services Co.',21000,'Full-time','Career advancement');

INSERT INTO employee_disclosures
  (employee_id,is_related_to_company,has_admin_offense,has_criminal_charge,
   has_criminal_conviction,has_been_separated,is_pwd,is_solo_parent,
   has_recent_hospital,has_current_treatment)
VALUES (99920,0,0,0,0,0,0,0,0,0),(99921,0,0,0,0,0,0,0,0,0),
       (99922,0,0,0,0,0,0,0,0,0),(99923,0,0,0,0,0,0,0,0,0),
       (99924,0,0,0,0,0,0,0,0,0),(99925,0,0,0,0,0,0,0,0,0),
       (99926,0,0,0,0,0,0,0,0,0);

INSERT INTO employee_trainings
  (employee_id,date_from,date_to,training_title,training_type,no_of_hours,conducted_by)
VALUES
  (99920,'2025-03-01','2025-03-02','Leadership Review','Internal',16,'Mock Training Team'),
  (99921,'2025-04-01','2025-04-02','Purchasing Planning','Internal',16,'Mock Training Team'),
  (99922,'2025-05-01','2025-05-02','Procurement Case Review','Internal',16,'Mock Training Team'),
  (99923,'2025-06-01','2025-06-02','Procurement Records Control','Internal',16,'Mock Training Team'),
  (99924,'2025-07-01','2025-07-02','Purchasing Controls','Internal',16,'Mock Training Team'),
  (99925,'2026-09-15','2026-09-16','Purchasing Orientation','Internal',16,'Mock Training Team'),
  (99926,'2026-10-12','2026-10-13','Purchasing Orientation','Internal',16,'Mock Training Team');

INSERT INTO employee_skills (employee_id,skill_name)
VALUES (99920,'Executive leadership'),(99921,'Purchasing planning'),
       (99922,'Purchasing case review'),(99923,'Purchasing records control'),
       (99924,'Purchasing evidence review'),(99925,'Document tracking'),
       (99926,'Records organization');

INSERT INTO employee_references
  (employee_id,reference_name,reference_address,reference_telephone)
VALUES (99920,'Luz Bautista','Lucena City, Quezon','042-991-0100'),
       (99921,'Rosa Villanueva','Lucena City, Quezon','042-991-0101'),
       (99922,'Maria Castillo','Tayabas City, Quezon','042-991-0102'),
       (99923,'Jose Mendoza','Lucena City, Quezon','042-991-0103'),
       (99924,'Ana Ramos','Pagbilao, Quezon','042-991-0104'),
       (99925,'Lina Navarro','Lucena City, Quezon','042-991-0105'),
       (99926,'Ramon Dela Cruz','Sariaya, Quezon','042-991-0106');
