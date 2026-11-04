-- Finance test people. Run once on a fresh test database.
-- First import the schema and organization files shown in README.md.
USE raquel_hris;

-- The CEO is needed to create/review the VP for Finance form. The CEO is outside the Finance cycle.
INSERT INTO employees
  (employee_id,employee_code,first_name,middle_name,last_name,name_extension,
   date_of_birth,place_of_birth,gender,civil_status,hire_date,job_title,
   job_title_id,department_id,rank_category_id,branch_id,reports_to,
   employment_status,employment_type,contract_start_date,contract_end_date,
   profile_picture,is_active)
VALUES
  (99500,'OP-984','Isabella','Reyes','Villanueva','','1978-06-18',
   'Lucena City, Quezon','Female','Married','2007-04-02',
   'President and CEO',1100,10,1,102,NULL,'Regular','Full-time',
   '2007-04-02','2030-12-31','avatar_f.jpg',1),
  (99501,'FIN-841','Veronica','Cruz','Santos','','1982-02-15',
   'Lucena City, Quezon','Female','Married','2012-06-04',
   'VP for Finance',500,5,1,102,99500,'Regular','Full-time',
   '2012-06-04','2030-12-31','avatar_f.jpg',1),
  (99502,'FIN-831','Mateo','Lopez','Reyes','','1988-05-21',
   'Tayabas City, Quezon','Male','Married','2016-08-01',
   'Accounting Manager I',501,5,3,102,99501,'Regular','Full-time',
   '2016-08-01','2030-12-31','avatar_m.jpg',1),
  (99503,'FIN-240','Julian','Garcia','Cruz','','1993-09-08',
   'Lucena City, Quezon','Male','Single','2020-03-02',
   'Accounting Supervisor I',502,5,4,102,99502,'Regular','Full-time',
   '2020-03-02','2030-12-31','avatar_m.jpg',1),
  (99504,'FIN-538','Diana','Mendoza','Garcia','','1998-07-18',
   'Pagbilao, Quezon','Female','Single','2023-05-08',
   'Accounting Staff I',511,5,5,102,99503,'Regular','Full-time',
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
  (99505,'FIN-562','Alma','Santos','Navarro','','2002-03-12',
   'Lucena City, Quezon','Female','Single','2026-09-01',
   'Accounting Staff I',511,5,5,102,99503,'Probationary','Initial','2026-11-30',
   'Full-time','2026-09-01','2027-02-28','avatar_f.jpg',1),
  (99506,'FIN-320','Leo','Ramos','Dela Cruz','','2001-11-25',
   'Sariaya, Quezon','Male','Single','2026-10-01',
   'Accounting Staff I',511,5,5,102,99503,'Probationary','Initial','2026-12-15',
   'Full-time','2026-10-01','2027-03-31','avatar_m.jpg',1);

-- Local test password for every account: password.
INSERT INTO users
  (employee_id,username,email,full_name,password_hash,role,branch_id,
   is_active,account_hold,first_login_completed,must_change_password)
VALUES
  (99500,'test.finance.ceo','finance.ceo@example.invalid','Isabella Villanueva',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','President and CEO',102,1,0,0,1),
  (99501,'test.finance.vp','finance.vp@example.invalid','Veronica Santos',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99502,'test.finance.accounting.manager','finance.accounting.manager@example.invalid','Mateo Reyes',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99503,'test.finance.accounting.supervisor','finance.accounting.supervisor@example.invalid','Julian Cruz',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99504,'test.finance.accounting.staff','finance.accounting.staff@example.invalid','Diana Garcia',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99505,'test.finance.alma','finance.alma@example.invalid','Alma Navarro',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
  (99506,'test.finance.leo','finance.leo@example.invalid','Leo Dela Cruz',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1);

INSERT INTO employee_contacts (employee_id,personal_email,mobile_number,telephone_number)
VALUES
  (99500,'finance.ceo@example.invalid','09179950000','042-991-0000'),
  (99501,'finance.vp@example.invalid','09179950001','042-991-0001'),
  (99502,'finance.accounting.manager@example.invalid','09179950002','042-991-0002'),
  (99503,'finance.accounting.supervisor@example.invalid','09179950003','042-991-0003'),
  (99504,'finance.accounting.staff@example.invalid','09179950004','042-991-0004'),
  (99505,'finance.alma@example.invalid','09179950005','042-991-0005'),
  (99506,'finance.leo@example.invalid','09179950006','042-991-0006');

INSERT INTO employee_details (employee_id,height_m,weight_kg,blood_type,citizenship)
VALUES (99500,1.64,59.00,'O+','Filipino'),(99501,1.62,57.00,'A+','Filipino'),
       (99502,1.73,70.00,'B+','Filipino'),(99503,1.71,68.00,'O+','Filipino'),
       (99504,1.59,53.00,'A+','Filipino'),(99505,1.60,55.00,'B+','Filipino'),
       (99506,1.74,69.00,'O+','Filipino');

INSERT INTO employee_government_ids (employee_id,sss_number,philhealth_number,pagibig_number,tin_number)
VALUES
  (99500,'MOCK-SSS-99500','MOCK-PH-99500','MOCK-PI-99500','MOCK-TIN-99500'),
  (99501,'MOCK-SSS-99501','MOCK-PH-99501','MOCK-PI-99501','MOCK-TIN-99501'),
  (99502,'MOCK-SSS-99502','MOCK-PH-99502','MOCK-PI-99502','MOCK-TIN-99502'),
  (99503,'MOCK-SSS-99503','MOCK-PH-99503','MOCK-PI-99503','MOCK-TIN-99503'),
  (99504,'MOCK-SSS-99504','MOCK-PH-99504','MOCK-PI-99504','MOCK-TIN-99504'),
  (99505,'MOCK-SSS-99505','MOCK-PH-99505','MOCK-PI-99505','MOCK-TIN-99505'),
  (99506,'MOCK-SSS-99506','MOCK-PH-99506','MOCK-PI-99506','MOCK-TIN-99506');

INSERT INTO employee_addresses
  (employee_id,address_type,region,house_no,street,subdivision,barangay,city,province,zip_code)
VALUES
  (99500,'Residential','CALABARZON','10','Quezon Avenue','City Proper','Barangay 1','Lucena City','Quezon','4301'),
  (99500,'Permanent','CALABARZON','10','Quezon Avenue','City Proper','Barangay 1','Lucena City','Quezon','4301'),
  (99501,'Residential','CALABARZON','11','Quezon Avenue','City Proper','Barangay 2','Lucena City','Quezon','4301'),
  (99501,'Permanent','CALABARZON','11','Quezon Avenue','City Proper','Barangay 2','Lucena City','Quezon','4301'),
  (99502,'Residential','CALABARZON','12','Rizal Street','City Proper','Barangay 3','Tayabas City','Quezon','4327'),
  (99502,'Permanent','CALABARZON','12','Rizal Street','City Proper','Barangay 3','Tayabas City','Quezon','4327'),
  (99503,'Residential','CALABARZON','13','Quezon Avenue','City Proper','Barangay 4','Lucena City','Quezon','4301'),
  (99503,'Permanent','CALABARZON','13','Quezon Avenue','City Proper','Barangay 4','Lucena City','Quezon','4301'),
  (99504,'Residential','CALABARZON','14','National Road','Town Proper','Barangay 5','Pagbilao','Quezon','4302'),
  (99504,'Permanent','CALABARZON','14','National Road','Town Proper','Barangay 5','Pagbilao','Quezon','4302'),
  (99505,'Residential','CALABARZON','15','Quezon Avenue','City Proper','Barangay 6','Lucena City','Quezon','4301'),
  (99505,'Permanent','CALABARZON','15','Quezon Avenue','City Proper','Barangay 6','Lucena City','Quezon','4301'),
  (99506,'Residential','CALABARZON','16','Rizal Street','Town Proper','Barangay 7','Sariaya','Quezon','4322'),
  (99506,'Permanent','CALABARZON','16','Rizal Street','Town Proper','Barangay 7','Sariaya','Quezon','4322');

INSERT INTO employee_emergency_contacts
  (employee_id,contact_name,relationship,contact_number,is_primary)
VALUES (99500,'Linda Reyes','Mother','09179911000',1),
       (99501,'Carmen Cruz','Mother','09179911001',1),
       (99502,'Rosa Lopez','Mother','09179911002',1),
       (99503,'Ana Garcia','Mother','09179911003',1),
       (99504,'Lina Mendoza','Mother','09179911004',1),
       (99505,'Maria Santos','Mother','09179911005',1),
       (99506,'Jose Ramos','Father','09179911006',1);

INSERT INTO employee_family (employee_id,member_type,surname,first_name,middle_name,occupation)
VALUES (99500,'Mother','Reyes','Linda','Garcia','Retired clerk'),
       (99501,'Mother','Cruz','Carmen','Lopez','Teacher'),
       (99502,'Mother','Lopez','Rosa','Reyes','Store owner'),
       (99503,'Mother','Garcia','Ana','Santos','Nurse'),
       (99504,'Mother','Mendoza','Lina','Cruz','Teacher'),
       (99505,'Mother','Santos','Maria','Reyes','Store owner'),
       (99506,'Father','Ramos','Jose','Dela Cruz','Mechanic');

INSERT INTO employee_education
  (employee_id,education_level,school_name,degree_course,period_from,period_to,year_graduated,honors_received)
VALUES
  (99500,'College','University of the Philippines','BS Psychology','1995-06-01','1999-04-30','1999','None'),
  (99501,'College','Southern Luzon State University','BS Business Administration','1999-06-01','2003-05-31','2003','None'),
  (99502,'College','Southern Luzon State University','BS Management','2005-06-01','2009-05-31','2009','None'),
  (99503,'College','Southern Luzon State University','BS Management','2010-06-01','2014-05-31','2014','None'),
  (99504,'College','Southern Luzon State University','BS Business Administration','2015-06-01','2019-05-31','2019','None'),
  (99505,'College','Southern Luzon State University','BS Business Administration','2019-06-01','2023-05-31','2023','None'),
  (99506,'College','Southern Luzon State University','BS Business Administration','2018-06-01','2022-05-31','2022','None');

INSERT INTO employee_work_experience
  (employee_id,date_from,date_to,job_title,company_name,monthly_salary,appointment_status,reason_for_leaving)
VALUES
  (99500,'1999-07-01','2007-03-31','Operations Lead','Mock Quezon Retail Co.',40000,'Full-time','Career advancement'),
  (99501,'2004-06-01','2012-05-31','Property Coordinator','Mock Quezon Realty Co.',32000,'Full-time','Career advancement'),
  (99502,'2010-06-01','2016-07-31','Property Officer','Mock Tayabas Realty Co.',28000,'Full-time','Career advancement'),
  (99503,'2015-06-01','2020-02-29','Property Assistant','Mock Lucena Services Co.',24000,'Full-time','Career advancement'),
  (99504,'2019-07-01','2023-04-30','Administrative Assistant','Mock Pagbilao Services Co.',21000,'Full-time','Career advancement'),
  (99505,'2023-07-01','2026-08-15','Records Assistant','Mock Lucena Services Co.',22000,'Full-time','Career advancement'),
  (99506,'2022-07-01','2026-09-15','Office Assistant','Mock Sariaya Services Co.',21000,'Full-time','Career advancement');

INSERT INTO employee_disclosures
  (employee_id,is_related_to_company,has_admin_offense,has_criminal_charge,
   has_criminal_conviction,has_been_separated,is_pwd,is_solo_parent,
   has_recent_hospital,has_current_treatment)
VALUES (99500,0,0,0,0,0,0,0,0,0),(99501,0,0,0,0,0,0,0,0,0),
       (99502,0,0,0,0,0,0,0,0,0),(99503,0,0,0,0,0,0,0,0,0),
       (99504,0,0,0,0,0,0,0,0,0),(99505,0,0,0,0,0,0,0,0,0),
       (99506,0,0,0,0,0,0,0,0,0);

INSERT INTO employee_trainings
  (employee_id,date_from,date_to,training_title,training_type,no_of_hours,conducted_by)
VALUES
  (99500,'2025-03-01','2025-03-02','Leadership Review','Internal',16,'Mock Training Team'),
  (99501,'2025-04-01','2025-04-02','Finance Planning','Internal',16,'Mock Training Team'),
  (99502,'2025-05-01','2025-05-02','Finance Case Review','Internal',16,'Mock Training Team'),
  (99503,'2025-06-01','2025-06-02','Finance Records Control','Internal',16,'Mock Training Team'),
  (99504,'2025-07-01','2025-07-02','Finance Evidence Basics','Internal',16,'Mock Training Team'),
  (99505,'2026-09-15','2026-09-16','Finance Records Orientation','Internal',16,'Mock Training Team'),
  (99506,'2026-10-12','2026-10-13','Finance Records Orientation','Internal',16,'Mock Training Team');

INSERT INTO employee_skills (employee_id,skill_name)
VALUES (99500,'Executive leadership'),(99501,'Finance planning'),
       (99502,'Finance case review'),(99503,'Finance records control'),
       (99504,'Finance evidence review'),(99505,'Document tracking'),
       (99506,'Records organization');

INSERT INTO employee_references
  (employee_id,reference_name,reference_address,reference_telephone)
VALUES (99500,'Luz Bautista','Lucena City, Quezon','042-991-0100'),
       (99501,'Rosa Villanueva','Lucena City, Quezon','042-991-0101'),
       (99502,'Maria Castillo','Tayabas City, Quezon','042-991-0102'),
       (99503,'Jose Mendoza','Lucena City, Quezon','042-991-0103'),
       (99504,'Ana Ramos','Pagbilao, Quezon','042-991-0104'),
       (99505,'Lina Navarro','Lucena City, Quezon','042-991-0105'),
       (99506,'Ramon Dela Cruz','Sariaya, Quezon','042-991-0106');

-- Treasury is a second Finance reporting branch under the VP.
INSERT INTO employees
  (employee_id,employee_code,first_name,middle_name,last_name,name_extension,
   date_of_birth,place_of_birth,gender,civil_status,hire_date,job_title,
   job_title_id,department_id,rank_category_id,branch_id,reports_to,
   employment_status,employment_type,contract_start_date,contract_end_date,
   profile_picture,is_active)
VALUES
  (99507,'FIN-137','Grace','Santos','Valdez','','1990-08-14',
   'Lucena City, Quezon','Female','Married','2018-02-05',
   'Treasury Supervisor I',506,5,4,102,99501,'Regular','Full-time',
   '2018-02-05','2030-12-31','avatar_f.jpg',1),
  (99508,'FIN-518','Nico','Ramos','Flores','','1998-03-17',
   'Tayabas City, Quezon','Male','Single','2022-08-01',
   'Treasury Staff I',516,5,5,102,99507,'Regular','Full-time',
   '2022-08-01','2030-12-31','avatar_m.jpg',1);

INSERT INTO users
  (employee_id,username,email,full_name,password_hash,role,branch_id,
   is_active,account_hold,first_login_completed,must_change_password)
VALUES
  (99507,'test.finance.treasury.supervisor','finance.treasury.supervisor@example.invalid',
   'Grace Valdez','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
   'Employee',102,1,0,0,1),
  (99508,'test.finance.treasury.staff','finance.treasury.staff@example.invalid',
   'Nico Flores','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
   'Employee',102,1,0,0,1);

INSERT INTO employee_contacts (employee_id,personal_email,mobile_number,telephone_number)
VALUES (99507,'finance.treasury.supervisor@example.invalid','09179950007','042-995-0007'),
       (99508,'finance.treasury.staff@example.invalid','09179950008','042-995-0008');
INSERT INTO employee_details (employee_id,height_m,weight_kg,blood_type,citizenship)
VALUES (99507,1.63,58.00,'A+','Filipino'),(99508,1.72,67.00,'O+','Filipino');
INSERT INTO employee_government_ids (employee_id,sss_number,philhealth_number,pagibig_number,tin_number)
VALUES (99507,'MOCK-SSS-99507','MOCK-PH-99507','MOCK-PI-99507','MOCK-TIN-99507'),
       (99508,'MOCK-SSS-99508','MOCK-PH-99508','MOCK-PI-99508','MOCK-TIN-99508');
INSERT INTO employee_addresses
  (employee_id,address_type,region,house_no,street,subdivision,barangay,city,province,zip_code)
VALUES
  (99507,'Residential','CALABARZON','17','Quezon Avenue','City Proper','Barangay 8','Lucena City','Quezon','4301'),
  (99507,'Permanent','CALABARZON','17','Quezon Avenue','City Proper','Barangay 8','Lucena City','Quezon','4301'),
  (99508,'Residential','CALABARZON','18','Rizal Street','City Proper','Barangay 9','Tayabas City','Quezon','4327'),
  (99508,'Permanent','CALABARZON','18','Rizal Street','City Proper','Barangay 9','Tayabas City','Quezon','4327');
INSERT INTO employee_emergency_contacts
  (employee_id,contact_name,relationship,contact_number,is_primary)
VALUES (99507,'Lina Santos','Mother','09179951007',1),
       (99508,'Rosa Ramos','Mother','09179951008',1);
INSERT INTO employee_family (employee_id,member_type,surname,first_name,middle_name,occupation)
VALUES (99507,'Mother','Santos','Lina','Cruz','Teacher'),
       (99508,'Mother','Ramos','Rosa','Flores','Store owner');
INSERT INTO employee_education
  (employee_id,education_level,school_name,degree_course,period_from,period_to,year_graduated,honors_received)
VALUES (99507,'College','Southern Luzon State University','BS Accountancy',
        '2007-06-01','2011-05-31','2011','None'),
       (99508,'College','Southern Luzon State University','BS Accountancy',
        '2015-06-01','2019-05-31','2019','None');
INSERT INTO employee_work_experience
  (employee_id,date_from,date_to,job_title,company_name,monthly_salary,appointment_status,reason_for_leaving)
VALUES (99507,'2012-06-01','2018-01-31','Treasury Officer','Mock Quezon Services Co.',30000,
        'Full-time','Career advancement'),
       (99508,'2019-07-01','2022-07-15','Finance Assistant','Mock Tayabas Services Co.',22000,
        'Full-time','Career advancement');
INSERT INTO employee_disclosures
  (employee_id,is_related_to_company,has_admin_offense,has_criminal_charge,
   has_criminal_conviction,has_been_separated,is_pwd,is_solo_parent,
   has_recent_hospital,has_current_treatment)
VALUES (99507,0,0,0,0,0,0,0,0,0),(99508,0,0,0,0,0,0,0,0,0);
INSERT INTO employee_trainings
  (employee_id,date_from,date_to,training_title,training_type,no_of_hours,conducted_by)
VALUES (99507,'2025-05-10','2025-05-11','Treasury Controls','Internal',16,'Mock Training Team'),
       (99508,'2025-06-10','2025-06-11','Cash Reconciliation','Internal',16,'Mock Training Team');
INSERT INTO employee_skills (employee_id,skill_name)
VALUES (99507,'Treasury controls'),(99508,'Cash reconciliation');
INSERT INTO employee_references
  (employee_id,reference_name,reference_address,reference_telephone)
VALUES (99507,'Maria Castillo','Lucena City, Quezon','042-995-0107'),
       (99508,'Jose Mendoza','Tayabas City, Quezon','042-995-0108');
