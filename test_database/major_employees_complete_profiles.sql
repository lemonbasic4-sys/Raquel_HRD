-- TEST ONLY: complete Regular employees and accounts for all departments.
-- Select the intended database first. Prerequisites: base schema + organization.
-- No evaluation/template/notification records are created. Reruns insert missing
-- records only; existing profile values and credentials are preserved.
SET NAMES utf8mb4;
DROP TEMPORARY TABLE IF EXISTS test_major_roster;
CREATE TEMPORARY TABLE test_major_roster (
 employee_id INT PRIMARY KEY, employee_code VARCHAR(30), first_name VARCHAR(100),
 last_name VARCHAR(100), job_title_id INT, head_id INT NULL
);
INSERT INTO test_major_roster VALUES
(20100,'AP-001','Marisol','Santos',100,21100),
(20101,'AP-002','Adrian','Reyes',101,20100),
(20105,'AP-003','Nathan','Cruz',105,20101),
(20108,'AP-004','Bianca','Garcia',108,20105),
(20200,'AUD-001','Rafael','Mendoza',200,21100),
(20201,'AUD-002','Teresa','Santos',201,20200),
(20203,'AUD-003','Carlo','Reyes',203,20201),
(20206,'AUD-004','Diana','Cruz',206,20203),
(20300,'BD-001','Ernesto','Garcia',300,21100),
(20302,'BD-002','Fiona','Mendoza',302,20300),
(20400,'COM-001','Gabriel','Santos',400,21100),
(20401,'COM-002','Helena','Reyes',401,20400),
(20403,'COM-003','Ivan','Cruz',403,20401),
(20500,'FIN-001','Julia','Garcia',500,21100),
(20501,'FIN-002','Kevin','Mendoza',501,20500),
(20502,'FIN-003','Lara','Santos',502,20501),
(20506,'FIN-004','Marco','Reyes',506,20500),
(20511,'FIN-005','Nina','Cruz',511,20502),
(20516,'FIN-006','Oscar','Garcia',516,20506),
(20600,'GS-001','Paula','Mendoza',600,21100),
(20601,'GS-002','Quentin','Santos',601,20600),
(20605,'GS-003','Rosa','Reyes',605,20601),
(20609,'GS-004','Samuel','Cruz',609,20605),
(20700,'HRD-001','Elena','Delgado',700,21100),
(20705,'HRD-002','Patricia','Gomez',705,20700),
(20710,'HRD-003','Miguel','Torres',710,20705),
(20800,'IT-001','Tristan','Reyes',800,21000),
(20804,'IT-002','Una','Cruz',804,20800),
(20810,'IT-003','Victor','Garcia',810,20804),
(20900,'MKT-001','Wendy','Mendoza',900,21100),
(20902,'MKT-002','Xavier','Santos',902,20900),
(20904,'MKT-003','Yasmin','Reyes',904,20902),
(21100,'OP-001','Isabella','Cruz',1100,NULL),
(21101,'OP-002','Zachary','Garcia',1101,21100),
(21000,'OPS-001','Andres','Mendoza',1000,21100),
(21001,'OPS-002','Beatrice','Santos',1001,21000),
(21004,'OPS-003','Cesar','Reyes',1004,21001),
(21007,'OPS-004','Danica','Cruz',1007,21004),
(21012,'OPS-005','Emilio','Garcia',1012,21007),
(21200,'PUR-001','Flora','Mendoza',1200,20601),
(21202,'PUR-002','Gino','Santos',1202,21200);

START TRANSACTION;
INSERT INTO employees (employee_id,employee_code,first_name,last_name,middle_name,
 hire_date,date_of_birth,place_of_birth,gender,civil_status,job_title_id,job_title,
 department_id,rank_category_id,branch_id,reports_to,employment_status,employment_type,
 profile_picture,is_active)
SELECT r.employee_id,r.employee_code,r.first_name,r.last_name,'Test',
 '2020-01-06','1985-06-15','Lucena City, Quezon','Male','Single',
 j.job_title_id,j.job_title,j.department_id,j.rank_category_id,102,r.head_id,
 'Regular','Full-time','avatar_m.jpg',1
FROM test_major_roster r JOIN job_titles j ON j.job_title_id=r.job_title_id
WHERE NOT EXISTS (SELECT 1 FROM employees e WHERE e.employee_id=r.employee_id AND e.employee_code=r.employee_code);
-- A conflicting fixed ID/code fails instead of overwriting an existing person.

INSERT INTO employee_contacts (employee_id,personal_email,mobile_number,telephone_number)
SELECT r.employee_id,CONCAT(LOWER(r.employee_code),'@example.invalid'),CONCAT('09170',r.employee_id),'042-700-0000' FROM test_major_roster r
WHERE NOT EXISTS (SELECT 1 FROM employee_contacts p WHERE p.employee_id=r.employee_id);

INSERT INTO employee_details (employee_id,height_m,weight_kg,blood_type,citizenship)
SELECT r.employee_id,1.70,65.00,'O+','Filipino' FROM test_major_roster r
WHERE NOT EXISTS (SELECT 1 FROM employee_details p WHERE p.employee_id=r.employee_id);

INSERT INTO employee_emergency_contacts (employee_id,contact_name,relationship,contact_number,is_primary)
SELECT r.employee_id,CONCAT('Teresa ',r.last_name),'Mother',CONCAT('09180',r.employee_id),1 FROM test_major_roster r
WHERE NOT EXISTS (SELECT 1 FROM employee_emergency_contacts p WHERE p.employee_id=r.employee_id);

INSERT INTO employee_family (employee_id,member_type,surname,first_name,middle_name,occupation)
SELECT r.employee_id,'Mother',r.last_name,'Teresa','Test','Retired teacher' FROM test_major_roster r
WHERE NOT EXISTS (SELECT 1 FROM employee_family p WHERE p.employee_id=r.employee_id);

INSERT INTO employee_siblings (employee_id,surname,first_name,middle_name,date_of_birth)
SELECT r.employee_id,r.last_name,'Alex','Test','1988-03-12' FROM test_major_roster r
WHERE NOT EXISTS (SELECT 1 FROM employee_siblings p WHERE p.employee_id=r.employee_id);

INSERT INTO employee_education (employee_id,education_level,school_name,degree_course,period_from,period_to,year_graduated,honors_received)
SELECT r.employee_id,'College','Mock Business College','Bachelor of Science in Business Administration','2002-06-01','2006-04-01','2006','None' FROM test_major_roster r
WHERE NOT EXISTS (SELECT 1 FROM employee_education p WHERE p.employee_id=r.employee_id);

INSERT INTO employee_work_experience (employee_id,date_from,date_to,job_title,company_name,monthly_salary,appointment_status,reason_for_leaving)
SELECT r.employee_id,'2007-01-08','2019-12-31','Office Associate','Mock Services Company',22000.00,'Regular','Career development' FROM test_major_roster r
WHERE NOT EXISTS (SELECT 1 FROM employee_work_experience p WHERE p.employee_id=r.employee_id);

INSERT INTO employee_trainings (employee_id,date_from,date_to,training_title,training_type,no_of_hours,conducted_by)
SELECT r.employee_id,'2025-06-02','2025-06-03','Data Privacy and Workplace Ethics','Professional development',16,'Mock Training Institute' FROM test_major_roster r
WHERE NOT EXISTS (SELECT 1 FROM employee_trainings p WHERE p.employee_id=r.employee_id);

INSERT INTO employee_voluntary_work (employee_id,date_from,date_to,organization_name,organization_address,no_of_hours,position_nature)
SELECT r.employee_id,'2025-07-01','2025-07-02','Mock Community Group','Lucena City, Quezon',8,'Volunteer' FROM test_major_roster r
WHERE NOT EXISTS (SELECT 1 FROM employee_voluntary_work p WHERE p.employee_id=r.employee_id);

INSERT INTO employee_eligibility (employee_id,license_title,date_from,date_to,license_number,date_of_exam,place_of_exam)
SELECT r.employee_id,'Mock Professional Eligibility','2015-01-01','2030-12-31',CONCAT('TEST-',r.employee_id),'2014-10-19','Lucena City' FROM test_major_roster r
WHERE NOT EXISTS (SELECT 1 FROM employee_eligibility p WHERE p.employee_id=r.employee_id);

INSERT INTO employee_skills (employee_id,skill_name)
SELECT r.employee_id,'Office administration and digital records management' FROM test_major_roster r
WHERE NOT EXISTS (SELECT 1 FROM employee_skills p WHERE p.employee_id=r.employee_id);

INSERT INTO employee_recognitions (employee_id,recognition_title)
SELECT r.employee_id,'Mock Service Recognition 2025' FROM test_major_roster r
WHERE NOT EXISTS (SELECT 1 FROM employee_recognitions p WHERE p.employee_id=r.employee_id);

INSERT INTO employee_memberships (employee_id,organization_name)
SELECT r.employee_id,'Mock Professional Development Association' FROM test_major_roster r
WHERE NOT EXISTS (SELECT 1 FROM employee_memberships p WHERE p.employee_id=r.employee_id);

INSERT INTO employee_disclosures (employee_id,is_related_to_company,has_admin_offense,has_criminal_charge,has_criminal_conviction,has_been_separated,is_pwd,is_solo_parent,has_recent_hospital,has_current_treatment)
SELECT r.employee_id,0,0,0,0,0,0,0,0,0 FROM test_major_roster r
WHERE NOT EXISTS (SELECT 1 FROM employee_disclosures p WHERE p.employee_id=r.employee_id);

INSERT INTO employee_government_ids (employee_id,sss_number,philhealth_number,pagibig_number,tin_number)
SELECT r.employee_id,CONCAT('SSS-TEST-',r.employee_id),CONCAT('PH-TEST-',r.employee_id),CONCAT('HDMF-TEST-',r.employee_id),CONCAT('TIN-TEST-',r.employee_id) FROM test_major_roster r
WHERE NOT EXISTS (SELECT 1 FROM employee_government_ids p WHERE p.employee_id=r.employee_id);

INSERT INTO employee_personal_properties (employee_id,description,year_acquired,acquisition_cost)
SELECT r.employee_id,'Mock personal laptop','2024',35000 FROM test_major_roster r
WHERE NOT EXISTS (SELECT 1 FROM employee_personal_properties p WHERE p.employee_id=r.employee_id);

INSERT INTO employee_liabilities (employee_id,nature_of_liability,creditor_name,outstanding_balance)
SELECT r.employee_id,'None','Not applicable',0 FROM test_major_roster r
WHERE NOT EXISTS (SELECT 1 FROM employee_liabilities p WHERE p.employee_id=r.employee_id);

INSERT INTO employee_references (employee_id,reference_name,reference_address,reference_telephone)
SELECT r.employee_id,'Roberto Test Reference','Lucena City, Quezon','042-700-0001' FROM test_major_roster r
WHERE NOT EXISTS (SELECT 1 FROM employee_references p WHERE p.employee_id=r.employee_id);

INSERT INTO employee_addresses (employee_id,address_type,region,house_no,street,subdivision,barangay,city,province,zip_code)
SELECT r.employee_id,'Residential','Region IV-A (CALABARZON)',CAST(r.employee_id AS CHAR),'Mock Street','Test Village','Ibabang Dupay','Lucena City','Quezon','4301'
FROM test_major_roster r WHERE NOT EXISTS (SELECT 1 FROM employee_addresses p WHERE p.employee_id=r.employee_id AND p.address_type='Residential');

INSERT INTO employee_addresses (employee_id,address_type,region,house_no,street,subdivision,barangay,city,province,zip_code)
SELECT r.employee_id,'Permanent','Region IV-A (CALABARZON)',CAST(r.employee_id AS CHAR),'Mock Street','Test Village','Ibabang Dupay','Lucena City','Quezon','4301'
FROM test_major_roster r WHERE NOT EXISTS (SELECT 1 FROM employee_addresses p WHERE p.employee_id=r.employee_id AND p.address_type='Permanent');

-- Employee Portal account for EVERY roster member (including HR and CEO).
INSERT INTO users (employee_id,username,email,password_hash,full_name,profile_picture,role,branch_id,is_active,first_login_completed,must_change_password)
SELECT r.employee_id,r.employee_code,CONCAT(LOWER(r.employee_code),'@portal.example.invalid'),
'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',CONCAT(r.first_name,' ',r.last_name),'avatar_m.jpg','Employee',102,1,1,0
FROM test_major_roster r WHERE NOT EXISTS (SELECT 1 FROM users u WHERE u.employee_id=r.employee_id AND u.role='Employee');

-- Separate work-role accounts; Employee Portal accounts remain available.
-- Work usernames follow firstname.lastname; portal usernames remain department codes.
INSERT INTO users (employee_id,username,email,password_hash,full_name,profile_picture,role,branch_id,is_active,first_login_completed,must_change_password)
SELECT r.employee_id,LOWER(CONCAT(r.first_name,'.',r.last_name)),
CONCAT(LOWER(r.employee_code),'@work.example.invalid'),'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
CONCAT(r.first_name,' ',r.last_name),'avatar_m.jpg',
CASE r.job_title_id WHEN 700 THEN 'HR Manager' WHEN 705 THEN 'HR Supervisor' WHEN 710 THEN 'HR Staff' ELSE 'President and CEO' END,102,1,1,0
FROM test_major_roster r WHERE r.job_title_id IN (700,705,710,1100)
AND NOT EXISTS (SELECT 1 FROM users u WHERE u.employee_id=r.employee_id AND u.role=
CASE r.job_title_id WHEN 700 THEN 'HR Manager' WHEN 705 THEN 'HR Supervisor' WHEN 710 THEN 'HR Staff' ELSE 'President and CEO' END);

-- Standalone administrator: no employee/profile or department membership.
-- Login: TDB-admin / password (local testing only).
-- Reruns preserve the existing account and its password.
INSERT INTO users (employee_id,username,email,password_hash,full_name,role,branch_id,is_active,account_hold,first_login_completed,must_change_password)
SELECT NULL,'admin','tdb-admin@example.invalid','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Test Database Administrator','Admin',102,1,0,1,0
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username='TDB-admin');
COMMIT;
DROP TEMPORARY TABLE test_major_roster;

