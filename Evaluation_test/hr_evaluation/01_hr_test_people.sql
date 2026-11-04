-- Run once, after the three database setup files listed in README.md.
-- Use a fresh test database. Running this file twice will cause duplicate errors.
USE raquel_hris;

UPDATE employees SET name_extension='', profile_picture='avatar_f.jpg',
  contract_start_date=hire_date, contract_end_date='2030-12-31'
  WHERE employee_id IN (101,301);
UPDATE employees SET name_extension='', job_title='HR Staff II', profile_picture='avatar_m.jpg',
  contract_start_date=hire_date, contract_end_date='2030-12-31'
  WHERE employee_id=302;
UPDATE employee_addresses SET house_no='14',street='Quezon Avenue',
  subdivision='City Proper',zip_code='4301' WHERE employee_id=101;
UPDATE employee_addresses SET house_no='25',street='Ortigas Avenue',
  subdivision='Central District',zip_code='1605' WHERE employee_id=301;
UPDATE employee_addresses SET house_no='38',street='General Luna Street',
  subdivision='Central District',zip_code='1630' WHERE employee_id=302;
UPDATE employee_work_experience SET appointment_status='Full-time',
  reason_for_leaving='Career advancement'
  WHERE employee_id IN (101,301,302);

-- The CEO is a form author and HR Manager's KRA reviewer only. No CEO cycle is seeded.
INSERT INTO employees (employee_id,employee_code,first_name,middle_name,last_name,
  name_extension,date_of_birth,place_of_birth,gender,civil_status,hire_date,
  job_title,job_title_id,department_id,rank_category_id,branch_id,reports_to,
  employment_status,employment_type,contract_start_date,contract_end_date,
  profile_picture,is_active)
VALUES (99001,'OP-433','Isabella','Reyes','Villanueva','',
  '1978-06-18','Lucena City, Quezon','Female','Married','2007-04-02',
  'President and CEO',1100,10,1,102,NULL,'Regular','Full-time',
  '2007-04-02','2030-12-31','avatar_f.jpg',1);

-- Keep the seeded HR Manager's employee reporting line aligned with her position.
UPDATE employees SET reports_to=99001 WHERE employee_id=101;

-- Recent hire is intentional: probationary Initial and Final can be tested in 2026.
INSERT INTO employees (employee_id,employee_code,first_name,middle_name,last_name,
  name_extension,date_of_birth,place_of_birth,gender,civil_status,hire_date,
  job_title,job_title_id,department_id,rank_category_id,branch_id,reports_to,
  employment_status,probationary_next_evaluation_type,
  probationary_next_evaluation_due_date,employment_type,contract_start_date,
  contract_end_date,profile_picture,is_active)
VALUES (99002,'HRD-569','Mara','Santos','Reyes','',
  '2001-09-12','Lucena City, Quezon','Female','Single','2026-09-01',
  'HR Staff I',710,7,5,102,301,'Probationary','Initial','2026-11-30',
  'Full-time','2026-09-01','2027-02-28','avatar_f.jpg',1),
 (99003,'HRD-735','Luis','Garcia','Navarro','',
  '2000-04-20','Tayabas City, Quezon','Male','Single','2026-10-01',
  'HR Staff I',710,7,5,102,301,'Probationary','Initial','2026-12-15',
  'Full-time','2026-10-01','2027-03-31','avatar_m.jpg',1);

INSERT INTO users (employee_id,username,email,full_name,password_hash,role,
  branch_id,is_active,account_hold,first_login_completed,must_change_password)
VALUES
 (101,'test.elena.employee','test.elena@example.invalid','Elena Delgado',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
 (301,'test.patricia.employee','test.patricia@example.invalid','Patricia Gomez',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
 (302,'test.miguel.employee','test.miguel@example.invalid','Miguel Torres',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
 (99002,'test.mara.employee','test.mara@example.invalid','Mara Reyes',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
 (99003,'test.luis.employee','test.luis@example.invalid','Luis Navarro',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Employee',102,1,0,0,1),
 (99001,'test.isabella.ceo','test.isabella@example.invalid','Isabella Villanueva',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','President and CEO',102,1,0,0,1);

INSERT INTO employee_contacts (employee_id,personal_email,mobile_number,telephone_number)
VALUES (99001,'isabella@example.invalid','09170099001','042-990-0001'),
       (99002,'mara@example.invalid','09170099002','042-990-0002'),
       (99003,'luis@example.invalid','09170099003','042-990-0003');
INSERT INTO employee_details (employee_id,height_m,weight_kg,blood_type,citizenship)
VALUES (99001,1.64,59.00,'O+','Filipino'),
       (99002,1.61,54.00,'A+','Filipino'),
       (99003,1.73,68.00,'B+','Filipino');
INSERT INTO employee_government_ids (employee_id,sss_number,philhealth_number,pagibig_number,tin_number)
VALUES (99001,'TEST-SSS-99001','TEST-PH-99001','TEST-PI-99001','TEST-TIN-99001'),
       (99002,'TEST-SSS-99002','TEST-PH-99002','TEST-PI-99002','TEST-TIN-99002'),
       (99003,'TEST-SSS-99003','TEST-PH-99003','TEST-PI-99003','TEST-TIN-99003');
INSERT INTO employee_addresses (employee_id,address_type,region,house_no,street,
  subdivision,barangay,city,province,zip_code)
VALUES
 (99001,'Residential','CALABARZON','12','Maharlika Street','City Proper','Barangay 1','Lucena City','Quezon','4301'),
 (99001,'Permanent','CALABARZON','12','Maharlika Street','City Proper','Barangay 1','Lucena City','Quezon','4301'),
 (99002,'Residential','CALABARZON','28','Quezon Avenue','City Proper','Barangay 5','Lucena City','Quezon','4301'),
 (99002,'Permanent','CALABARZON','28','Quezon Avenue','City Proper','Barangay 5','Lucena City','Quezon','4301'),
 (99003,'Residential','CALABARZON','6','Rizal Street','City Proper','Barangay 3','Tayabas City','Quezon','4327'),
 (99003,'Permanent','CALABARZON','6','Rizal Street','City Proper','Barangay 3','Tayabas City','Quezon','4327');
INSERT INTO employee_disclosures (employee_id,is_related_to_company,has_admin_offense,
  has_criminal_charge,has_criminal_conviction,has_been_separated,is_pwd,
  is_solo_parent,has_recent_hospital,has_current_treatment)
VALUES (99001,0,0,0,0,0,0,0,0,0),
       (99002,0,0,0,0,0,0,0,0,0),
       (99003,0,0,0,0,0,0,0,0,0);
INSERT INTO employee_family (employee_id,member_type,surname,first_name,middle_name,occupation)
VALUES (99001,'Father','Reyes','Antonio','Cruz','Retired teacher'),
       (99001,'Mother','Reyes','Linda','Garcia','Retired clerk'),
       (99002,'Father','Santos','Roberto','Dela Cruz','Driver'),
       (99002,'Mother','Santos','Ana','Lopez','Store owner'),
       (99003,'Father','Navarro','Jose','Ramos','Mechanic'),
       (99003,'Mother','Navarro','Luz','Garcia','Teacher');
INSERT INTO employee_emergency_contacts (employee_id,contact_name,relationship,contact_number,is_primary)
VALUES (99001,'Linda Reyes','Mother','09170099101',1),
       (99002,'Ana Santos','Mother','09170099102',1),
       (99003,'Luz Navarro','Mother','09170099103',1);
INSERT INTO employee_education (employee_id,education_level,school_name,degree_course,
  period_from,period_to,year_graduated,honors_received)
VALUES (99001,'College','University of the Philippines','BS Psychology',
        '1995-06-01','1999-04-30','1999','None'),
       (99002,'College','Southern Luzon State University','BS Human Resource Management',
        '2019-06-01','2023-05-31','2023','None'),
       (99003,'College','Southern Luzon State University','BS Business Administration',
        '2018-06-01','2022-05-31','2022','None');
INSERT INTO employee_work_experience (employee_id,date_from,date_to,job_title,company_name,
  monthly_salary,appointment_status,reason_for_leaving)
VALUES (99001,'1999-07-01','2007-03-31','Operations Lead','Mock Quezon Retail Co.',
        40000,'Full-time','Career advancement'),
       (99002,'2023-07-01','2026-08-15','HR Assistant','Mock Lucena Services Co.',
        22000,'Full-time','Career advancement'),
       (99003,'2022-07-01','2026-09-15','Administrative Assistant','Mock Tayabas Services Co.',
        21000,'Full-time','Career advancement');
INSERT INTO employee_trainings (employee_id,date_from,date_to,training_title,training_type,
  no_of_hours,conducted_by)
VALUES (99002,'2026-09-15','2026-09-16','HR Records Orientation','Internal',
        16,'Mock HR Training Team'),
       (99003,'2026-10-12','2026-10-13','HR Records Orientation','Internal',
        16,'Mock HR Training Team');
INSERT INTO employee_skills (employee_id,skill_name)
VALUES (99002,'HR records administration'),
       (99003,'Personnel file organization');
INSERT INTO employee_references (employee_id,reference_name,reference_address,reference_telephone)
VALUES (99002,'Rosa Fernandez','Lucena City, Quezon','042-990-0123'),
       (99003,'Maria Castillo','Tayabas City, Quezon','042-990-0124');
