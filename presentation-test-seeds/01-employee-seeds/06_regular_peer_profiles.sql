SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;
START TRANSACTION;

-- Phase 1 extension approved for Annual 360 eligibility.
-- Nine departments had only one eligible Regular presentation employee.
DROP TEMPORARY TABLE IF EXISTS tmp_annual_peer_employees;
CREATE TEMPORARY TABLE tmp_annual_peer_employees (
  employee_code VARCHAR(30) PRIMARY KEY, first_name VARCHAR(100), last_name VARCHAR(100), middle_name VARCHAR(100),
  date_of_birth DATE, place_of_birth VARCHAR(255), gender VARCHAR(20), civil_status VARCHAR(20),
  hire_date DATE, regularization_date DATE, job_title_id INT, branch_id INT, reports_to_code VARCHAR(30),
  mobile_number VARCHAR(20), email VARCHAR(100), skill_name VARCHAR(255), training_title VARCHAR(255)
);

INSERT INTO tmp_annual_peer_employees VALUES
('DEMO-AUD-REG2','Liam','Herrera','Santos','1992-04-19','Lucena City, Quezon','Male','Married','2022-02-07','2022-08-07',201,102,'DEMO-AUD-REG','09172020001','liam.herrera@demo.raquel.local','Audit evidence review','Risk-based Internal Audit'),
('DEMO-BD-REG2','Mia','Robles','Cruz','1994-09-08','Tayabas City, Quezon','Female','Single','2022-03-14','2022-09-14',302,102,'DEMO-BD-REG','09172030001','mia.robles@demo.raquel.local','Market opportunity analysis','Business Development Planning'),
('DEMO-COM-REG2','Noah','Evangelista','Reyes','1991-12-11','Sariaya, Quezon','Male','Married','2022-04-04','2022-10-04',401,102,'DEMO-COM-REG','09172040001','noah.evangelista@demo.raquel.local','Regulatory review','Compliance Monitoring and Reporting'),
('DEMO-FIN-REG2','Olivia','Lim','Garcia','1993-07-22','Lipa City, Batangas','Female','Married','2022-01-17','2022-07-17',502,102,'DEMO-FIN-REG','09172050001','olivia.lim@demo.raquel.local','Account reconciliation','Financial Controls Workshop'),
('DEMO-GS-REG2','Paolo','Serrano','Diaz','1990-05-16','Lucena City, Quezon','Male','Married','2022-05-09','2022-11-09',605,102,'DEMO-GS-REG','09172060001','paolo.serrano@demo.raquel.local','Facilities coordination','Occupational Safety and Facilities Management'),
('DEMO-IT-REG2','Regina','Velasco','Torres','1992-10-03','San Pablo City, Laguna','Female','Single','2022-02-21','2022-08-21',801,102,'DEMO-IT-REG','09172080001','regina.velasco@demo.raquel.local','Systems delivery management','IT Service Management Foundations'),
('DEMO-MKT-REG2','Samuel','Cortez','Aquino','1993-06-14','Candelaria, Quezon','Male','Married','2022-03-07','2022-09-07',902,102,'DEMO-MKT-REG','09172090001','samuel.cortez@demo.raquel.local','Campaign performance analysis','Integrated Marketing Communications'),
('DEMO-OPS-REG2','Therese','Villamor','Bautista','1991-08-27','Tayabas City, Quezon','Female','Married','2022-01-10','2022-07-10',1004,141,'DEMO-OPS-REG','09172110001','therese.villamor@demo.raquel.local','Branch operations coordination','Branch Operations and Controls'),
('DEMO-PUR-REG2','Ulysses','Tan','Mendoza','1994-01-29','Pagbilao, Quezon','Male','Single','2022-04-18','2022-10-18',1202,102,'DEMO-PUR-REG','09172120001','ulysses.tan@demo.raquel.local','Vendor documentation','Ethical Procurement and Vendor Management');

INSERT INTO employees
 (employee_code,first_name,last_name,middle_name,date_of_birth,place_of_birth,gender,civil_status,
  hire_date,regularization_effective_date,job_title_id,job_title,department_id,rank_category_id,
  branch_id,reports_to,employment_status,employment_type,contract_start_date,is_active)
SELECT r.employee_code,r.first_name,r.last_name,r.middle_name,r.date_of_birth,r.place_of_birth,r.gender,r.civil_status,
 r.hire_date,r.regularization_date,j.job_title_id,j.job_title,j.department_id,j.rank_category_id,
 r.branch_id,m.employee_id,'Regular','Full-time',r.hire_date,1
FROM tmp_annual_peer_employees r
JOIN job_titles j ON j.job_title_id=r.job_title_id AND j.is_active=1
JOIN employees m ON m.employee_code=r.reports_to_code AND m.is_active=1 AND m.deleted_at IS NULL
WHERE NOT EXISTS(SELECT 1 FROM employees e WHERE e.employee_code=r.employee_code);

INSERT INTO employee_details(employee_id,height_m,weight_kg,blood_type,citizenship)
SELECT e.employee_id,1.68,65.00,'O+','Filipino' FROM tmp_annual_peer_employees r JOIN employees e ON e.employee_code=r.employee_code AND e.first_name=r.first_name AND e.last_name=r.last_name
WHERE NOT EXISTS(SELECT 1 FROM employee_details x WHERE x.employee_id=e.employee_id);
INSERT INTO employee_contacts(employee_id,mobile_number,personal_email)
SELECT e.employee_id,r.mobile_number,r.email FROM tmp_annual_peer_employees r JOIN employees e ON e.employee_code=r.employee_code AND e.first_name=r.first_name AND e.last_name=r.last_name
WHERE NOT EXISTS(SELECT 1 FROM employee_contacts x WHERE x.employee_id=e.employee_id);
INSERT INTO employee_addresses(employee_id,address_type,region,house_no,street,barangay,city,province,zip_code)
SELECT e.employee_id,a.address_type,'Region IV-A (CALABARZON)','25','Annual Seed Street','Demo Barangay','Tayabas City','Quezon','4327'
FROM tmp_annual_peer_employees r JOIN employees e ON e.employee_code=r.employee_code AND e.first_name=r.first_name AND e.last_name=r.last_name
CROSS JOIN(SELECT 'Residential' address_type UNION ALL SELECT 'Permanent') a
WHERE NOT EXISTS(SELECT 1 FROM employee_addresses x WHERE x.employee_id=e.employee_id AND x.address_type=a.address_type);
INSERT INTO employee_emergency_contacts(employee_id,contact_name,relationship,contact_number,is_primary)
SELECT e.employee_id,CONCAT('Maria ',e.last_name),'Mother',CONCAT('0918',LPAD(e.employee_id,7,'0')),1
FROM tmp_annual_peer_employees r JOIN employees e ON e.employee_code=r.employee_code
WHERE NOT EXISTS(SELECT 1 FROM employee_emergency_contacts x WHERE x.employee_id=e.employee_id AND x.is_primary=1);
INSERT INTO employee_family(employee_id,member_type,surname,first_name,middle_name,occupation)
SELECT e.employee_id,'Mother',e.last_name,'Maria','Santos','Self-employed' FROM tmp_annual_peer_employees r JOIN employees e ON e.employee_code=r.employee_code
WHERE NOT EXISTS(SELECT 1 FROM employee_family x WHERE x.employee_id=e.employee_id);

INSERT INTO employee_education(employee_id,education_level,school_name,period_from,period_to,year_graduated)
SELECT e.employee_id,'Elementary','Quezon Provincial Elementary School',MAKEDATE(YEAR(e.date_of_birth)+6,1),MAKEDATE(YEAR(e.date_of_birth)+12,1),YEAR(e.date_of_birth)+12
FROM tmp_annual_peer_employees r JOIN employees e ON e.employee_code=r.employee_code WHERE NOT EXISTS(SELECT 1 FROM employee_education x WHERE x.employee_id=e.employee_id AND x.education_level='Elementary');
INSERT INTO employee_education(employee_id,education_level,school_name,period_from,period_to,year_graduated)
SELECT e.employee_id,'Secondary','Quezon Provincial National High School',MAKEDATE(YEAR(e.date_of_birth)+12,1),MAKEDATE(YEAR(e.date_of_birth)+16,1),YEAR(e.date_of_birth)+16
FROM tmp_annual_peer_employees r JOIN employees e ON e.employee_code=r.employee_code WHERE NOT EXISTS(SELECT 1 FROM employee_education x WHERE x.employee_id=e.employee_id AND x.education_level='Secondary');
INSERT INTO employee_education(employee_id,education_level,school_name,degree_course,period_from,period_to,year_graduated)
SELECT e.employee_id,'College','Southern Luzon State University','BS Business Administration',MAKEDATE(YEAR(e.date_of_birth)+16,1),MAKEDATE(YEAR(e.date_of_birth)+20,1),YEAR(e.date_of_birth)+20
FROM tmp_annual_peer_employees r JOIN employees e ON e.employee_code=r.employee_code WHERE NOT EXISTS(SELECT 1 FROM employee_education x WHERE x.employee_id=e.employee_id AND x.education_level='College');

INSERT INTO employee_work_experience(employee_id,date_from,date_to,job_title,company_name,monthly_salary,appointment_status,reason_for_leaving)
SELECT e.employee_id,e.hire_date,DATE_SUB(e.regularization_effective_date,INTERVAL 1 DAY),e.job_title,'Raquel Pawnshop, Inc.',30000,'Probationary','Successfully completed probationary appointment'
FROM tmp_annual_peer_employees r JOIN employees e ON e.employee_code=r.employee_code
WHERE NOT EXISTS(SELECT 1 FROM employee_work_experience x WHERE x.employee_id=e.employee_id AND x.company_name='Raquel Pawnshop, Inc.' AND x.appointment_status='Probationary');
INSERT INTO employee_work_experience(employee_id,date_from,date_to,job_title,company_name,monthly_salary,appointment_status)
SELECT e.employee_id,e.regularization_effective_date,NULL,e.job_title,'Raquel Pawnshop, Inc.',36000,'Regular'
FROM tmp_annual_peer_employees r JOIN employees e ON e.employee_code=r.employee_code
WHERE NOT EXISTS(SELECT 1 FROM employee_work_experience x WHERE x.employee_id=e.employee_id AND x.company_name='Raquel Pawnshop, Inc.' AND x.appointment_status='Regular');

INSERT INTO employee_trainings(employee_id,date_from,date_to,training_title,training_type,no_of_hours,conducted_by)
SELECT e.employee_id,DATE_SUB(e.hire_date,INTERVAL 30 DAY),DATE_SUB(e.hire_date,INTERVAL 28 DAY),r.training_title,'Technical',16,'Raquel Learning and Development Center'
FROM tmp_annual_peer_employees r JOIN employees e ON e.employee_code=r.employee_code WHERE NOT EXISTS(SELECT 1 FROM employee_trainings x WHERE x.employee_id=e.employee_id);
INSERT INTO employee_eligibility(employee_id,license_title,date_from,license_number,date_of_exam,place_of_exam)
SELECT e.employee_id,'Career Service Professional Eligibility',DATE_SUB(e.hire_date,INTERVAL 1 YEAR),CONCAT('DEMO-',REPLACE(e.employee_code,'DEMO-',''),'-ELIG'),DATE_SUB(e.hire_date,INTERVAL 13 MONTH),'Lucena City, Quezon'
FROM tmp_annual_peer_employees r JOIN employees e ON e.employee_code=r.employee_code WHERE NOT EXISTS(SELECT 1 FROM employee_eligibility x WHERE x.employee_id=e.employee_id);
INSERT INTO employee_skills(employee_id,skill_name)
SELECT e.employee_id,r.skill_name FROM tmp_annual_peer_employees r JOIN employees e ON e.employee_code=r.employee_code WHERE NOT EXISTS(SELECT 1 FROM employee_skills x WHERE x.employee_id=e.employee_id);
INSERT INTO employee_government_ids(employee_id,sss_number,philhealth_number,pagibig_number,tin_number)
SELECT e.employee_id,CONCAT('DEMO-SSS-',LPAD(e.employee_id,6,'0')),CONCAT('DEMO-PHIC-',LPAD(e.employee_id,6,'0')),CONCAT('DEMO-HD-',LPAD(e.employee_id,6,'0')),CONCAT('DEMO-TIN-',LPAD(e.employee_id,6,'0'))
FROM tmp_annual_peer_employees r JOIN employees e ON e.employee_code=r.employee_code WHERE NOT EXISTS(SELECT 1 FROM employee_government_ids x WHERE x.employee_id=e.employee_id);
INSERT INTO employee_disclosures(employee_id,is_related_to_company,has_admin_offense,has_criminal_charge,has_criminal_conviction,has_been_separated,is_pwd,is_solo_parent,has_recent_hospital,has_current_treatment)
SELECT e.employee_id,0,0,0,0,0,0,0,0,0 FROM tmp_annual_peer_employees r JOIN employees e ON e.employee_code=r.employee_code WHERE NOT EXISTS(SELECT 1 FROM employee_disclosures x WHERE x.employee_id=e.employee_id);
INSERT INTO employee_personal_properties(employee_id,description,year_acquired,acquisition_cost)
SELECT e.employee_id,'Personal laptop computer and mobile phone',YEAR(e.hire_date),45000 FROM tmp_annual_peer_employees r JOIN employees e ON e.employee_code=r.employee_code WHERE NOT EXISTS(SELECT 1 FROM employee_personal_properties x WHERE x.employee_id=e.employee_id);
INSERT INTO employee_references(employee_id,reference_name,reference_address,reference_telephone)
SELECT e.employee_id,CONCAT('Roberto ',e.last_name),'Demo Barangay, Tayabas City, Quezon',CONCAT('0919',LPAD(e.employee_id,7,'0')) FROM tmp_annual_peer_employees r JOIN employees e ON e.employee_code=r.employee_code WHERE NOT EXISTS(SELECT 1 FROM employee_references x WHERE x.employee_id=e.employee_id);

-- Employee Portal accounts for new peers.
INSERT INTO users(employee_id,username,email,password_hash,full_name,role,branch_id,is_active,account_hold,first_login_completed,must_change_password)
SELECT e.employee_id,LOWER(CONCAT('demo.',REPLACE(e.employee_code,'DEMO-',''))),LOWER(CONCAT(REPLACE(e.employee_code,'DEMO-',''),'@portal.demo.raquel.local')),
 '$2y$10$tp.59OeLYLyc/KGE3WN1Vu7RaLL3EcNfx3IhMa8GAFX9p99xBnSga',CONCAT(e.first_name,' ',e.last_name),'Employee',e.branch_id,1,0,0,1
FROM tmp_annual_peer_employees r JOIN employees e ON e.employee_code=r.employee_code
WHERE NOT EXISTS(SELECT 1 FROM users u WHERE u.employee_id=e.employee_id AND u.role='Employee');

-- Existing executive reporting heads need active accounts for Annual rating/finalization.
INSERT INTO users(employee_id,username,email,password_hash,full_name,role,branch_id,is_active,account_hold,first_login_completed,must_change_password)
SELECT e.employee_id,'demo.ceo.isabella','isabella.villanueva@executive.demo.raquel.local','$2y$10$tp.59OeLYLyc/KGE3WN1Vu7RaLL3EcNfx3IhMa8GAFX9p99xBnSga',CONCAT(e.first_name,' ',e.last_name),'President and CEO',e.branch_id,1,0,0,1
FROM employees e WHERE e.employee_code='CEO-001' AND NOT EXISTS(SELECT 1 FROM users u WHERE u.employee_id=e.employee_id AND u.is_active=1 AND u.deleted_at IS NULL);
INSERT INTO users(employee_id,username,email,password_hash,full_name,role,branch_id,is_active,account_hold,first_login_completed,must_change_password)
SELECT e.employee_id,'demo.ap.marisol','marisol.santos@portal.demo.raquel.local','$2y$10$tp.59OeLYLyc/KGE3WN1Vu7RaLL3EcNfx3IhMa8GAFX9p99xBnSga',CONCAT(e.first_name,' ',e.last_name),'Employee',e.branch_id,1,0,0,1
FROM employees e WHERE e.employee_code='AP-001' AND NOT EXISTS(SELECT 1 FROM users u WHERE u.employee_id=e.employee_id AND u.role='Employee' AND u.is_active=1 AND u.deleted_at IS NULL);

DROP TEMPORARY TABLE tmp_annual_peer_employees;
COMMIT;

SELECT COUNT(*) AS additional_regular_peers FROM employees WHERE employee_code LIKE 'DEMO-%-REG2';
