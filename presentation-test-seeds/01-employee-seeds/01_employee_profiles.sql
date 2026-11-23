SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;
START TRANSACTION;

-- Phase 1 presentation employees. This file never updates or replaces an existing employee.
-- Run 04-validation/validate_employees.sql before import and stop if CODE_COLLISION is returned.
DROP TEMPORARY TABLE IF EXISTS tmp_presentation_employees;
CREATE TEMPORARY TABLE tmp_presentation_employees (
    employee_code VARCHAR(30) PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) NULL,
    birth_date DATE NOT NULL,
    birth_place VARCHAR(255) NOT NULL,
    gender VARCHAR(30) NOT NULL,
    civil_status VARCHAR(30) NOT NULL,
    hire_date DATE NOT NULL,
    regularization_date DATE NULL,
    job_title_id INT NOT NULL,
    branch_id INT NOT NULL,
    reports_to_code VARCHAR(30) NULL,
    employment_status VARCHAR(30) NOT NULL,
    mobile_number VARCHAR(20) NOT NULL,
    personal_email VARCHAR(100) NOT NULL,
    city VARCHAR(150) NOT NULL,
    province VARCHAR(150) NOT NULL,
    emergency_name VARCHAR(150) NOT NULL,
    emergency_relationship VARCHAR(50) NOT NULL,
    emergency_number VARCHAR(20) NOT NULL,
    height_m DECIMAL(4,2) NOT NULL,
    weight_kg DECIMAL(5,2) NOT NULL,
    blood_type VARCHAR(5) NOT NULL
);

INSERT INTO tmp_presentation_employees VALUES
('DEMO-AP-REG','Adrian','Mendoza','Lopez','1988-03-12','Lucena City, Quezon','Male','Married','2018-02-05','2018-08-05',101,102,'AP-001','Regular','09171010001','adrian.mendoza@demo.raquel.local','Tayabas City','Quezon','Camille Mendoza','Spouse','09181010001',1.72,72.00,'O+'),
('DEMO-AP-P01','Bianca','Navarro','Reyes','2000-06-18','Sariaya, Quezon','Female','Single','2026-07-06',NULL,108,102,'DEMO-AP-REG','Probationary','09171010002','bianca.navarro@demo.raquel.local','Sariaya','Quezon','Lourdes Navarro','Mother','09181010002',1.58,52.00,'A+'),
('DEMO-AP-P02','Carlo','Villareal','Santos','1999-11-02','Tayabas City, Quezon','Male','Single','2026-08-03',NULL,113,102,'DEMO-AP-REG','Probationary','09171010003','carlo.villareal@demo.raquel.local','Tayabas City','Quezon','Ramon Villareal','Father','09181010003',1.70,68.00,'B+'),
('DEMO-AUD-REG','Diana','Mercado','Cruz','1987-09-21','Lucena City, Quezon','Female','Married','2017-05-15','2017-11-15',200,102,'CEO-001','Regular','09171020001','diana.mercado@demo.raquel.local','Lucena City','Quezon','Noel Mercado','Spouse','09181020001',1.60,55.00,'O+'),
('DEMO-AUD-P01','Enzo','Ramos','Garcia','2001-02-14','Candelaria, Quezon','Male','Single','2026-07-13',NULL,206,102,'DEMO-AUD-REG','Probationary','09171020002','enzo.ramos@demo.raquel.local','Candelaria','Quezon','Mila Ramos','Mother','09181020002',1.74,70.00,'A+'),
('DEMO-AUD-P02','Faith','Soriano','Diaz','2000-12-09','Pagbilao, Quezon','Female','Single','2026-08-10',NULL,207,102,'DEMO-AUD-REG','Probationary','09171020003','faith.soriano@demo.raquel.local','Pagbilao','Quezon','Edgar Soriano','Father','09181020003',1.57,50.00,'B+'),
('DEMO-BD-REG','Gabriel','Aquino','Flores','1989-01-30','San Pablo City, Laguna','Male','Married','2019-03-04','2019-09-04',300,102,'CEO-001','Regular','09171030001','gabriel.aquino@demo.raquel.local','San Pablo City','Laguna','Irene Aquino','Spouse','09181030001',1.75,76.00,'O-'),
('DEMO-BD-P01','Hazel','Castillo','Lim','2002-04-25','Lucban, Quezon','Female','Single','2026-07-20',NULL,302,102,'DEMO-BD-REG','Probationary','09171030002','hazel.castillo@demo.raquel.local','Lucban','Quezon','Rosa Castillo','Mother','09181030002',1.56,49.00,'A+'),
('DEMO-BD-P02','Ivan','Domingo','Tan','2001-08-17','Tiaong, Quezon','Male','Single','2026-08-17',NULL,303,102,'DEMO-BD-REG','Probationary','09171030003','ivan.domingo@demo.raquel.local','Tiaong','Quezon','Victor Domingo','Father','09181030003',1.71,66.00,'AB+'),
('DEMO-COM-REG','Julia','Fernandez','Ocampo','1988-07-07','Tayabas City, Quezon','Female','Married','2018-06-11','2018-12-11',400,102,'CEO-001','Regular','09171040001','julia.fernandez@demo.raquel.local','Tayabas City','Quezon','Paolo Fernandez','Spouse','09181040001',1.61,56.00,'O+'),
('DEMO-COM-P01','Kevin','Manalo','Uy','2000-10-11','Lucena City, Quezon','Male','Single','2026-07-27',NULL,403,102,'DEMO-COM-REG','Probationary','09171040002','kevin.manalo@demo.raquel.local','Lucena City','Quezon','Lina Manalo','Mother','09181040002',1.69,64.00,'A-'),
('DEMO-COM-P02','Lara','Pascual','Go','2002-01-19','Sariaya, Quezon','Female','Single','2026-08-24',NULL,404,102,'DEMO-COM-REG','Probationary','09171040003','lara.pascual@demo.raquel.local','Sariaya','Quezon','Dante Pascual','Father','09181040003',1.59,51.00,'B+'),
('DEMO-FIN-REG','Marco','Valdez','Chua','1986-05-23','Lipa City, Batangas','Male','Married','2016-09-01','2017-03-01',501,102,'CEO-001','Regular','09171050001','marco.valdez@demo.raquel.local','Tayabas City','Quezon','Andrea Valdez','Spouse','09181050001',1.73,73.00,'O+'),
('DEMO-FIN-P01','Nadine','Rosales','Sy','2001-03-08','Calamba, Laguna','Female','Single','2026-07-06',NULL,511,102,'DEMO-FIN-REG','Probationary','09171050002','nadine.rosales@demo.raquel.local','Tayabas City','Quezon','Grace Rosales','Mother','09181050002',1.60,53.00,'A+'),
('DEMO-FIN-P02','Owen','Salazar','Co','2000-09-16','Candelaria, Quezon','Male','Single','2026-08-03',NULL,516,102,'DEMO-FIN-REG','Probationary','09171050003','owen.salazar@demo.raquel.local','Candelaria','Quezon','Mario Salazar','Father','09181050003',1.76,71.00,'B+'),
('DEMO-GS-REG','Paula','Ignacio','Rivera','1987-12-04','Lucena City, Quezon','Female','Married','2017-11-06','2018-05-06',601,102,'CEO-001','Regular','09171060001','paula.ignacio@demo.raquel.local','Lucena City','Quezon','Rico Ignacio','Spouse','09181060001',1.62,57.00,'AB+'),
('DEMO-GS-P01','Rafael','Bonifacio','Cortez','1998-06-27','Tayabas City, Quezon','Male','Married','2026-07-13',NULL,609,102,'DEMO-GS-REG','Probationary','09171060002','rafael.bonifacio@demo.raquel.local','Tayabas City','Quezon','Mina Bonifacio','Spouse','09181060002',1.70,69.00,'O+'),
('DEMO-GS-P02','Sofia','Aguilar','Neri','2001-11-29','Pagbilao, Quezon','Female','Single','2026-08-10',NULL,618,102,'DEMO-GS-REG','Probationary','09171060003','sofia.aguilar@demo.raquel.local','Pagbilao','Quezon','Cora Aguilar','Mother','09181060003',1.58,52.00,'A+'),
('DEMO-HR-REG','Tristan','Dela Cruz','Molina','1994-02-20','Lucena City, Quezon','Male','Married','2022-04-18','2022-10-18',712,102,'HRD-002','Regular','09171070001','tristan.delacruz@demo.raquel.local','Lucena City','Quezon','Mae Dela Cruz','Spouse','09181070001',1.72,70.00,'O+'),
('DEMO-HR-P01','Uma','Reyes','Bautista','2002-05-13','Tayabas City, Quezon','Female','Single','2026-07-20',NULL,713,102,'HRD-002','Probationary','09171070002','uma.reyes@demo.raquel.local','Tayabas City','Quezon','Nora Reyes','Mother','09181070002',1.57,50.00,'B+'),
('DEMO-HR-P02','Vince','Lorenzo','David','2001-07-31','Sariaya, Quezon','Male','Single','2026-08-17',NULL,714,102,'HRD-002','Probationary','09171070003','vince.lorenzo@demo.raquel.local','Sariaya','Quezon','Rudy Lorenzo','Father','09181070003',1.74,68.00,'A+'),
('DEMO-IT-REG','Warren','Santiago','Lee','1989-10-05','Quezon City','Male','Married','2019-01-14','2019-07-14',800,102,'CEO-001','Regular','09171080001','warren.santiago@demo.raquel.local','Tayabas City','Quezon','Ella Santiago','Spouse','09181080001',1.75,74.00,'O+'),
('DEMO-IT-P01','Xenia','Torralba','Yu','2001-12-22','Lucena City, Quezon','Female','Single','2026-07-27',NULL,809,102,'DEMO-IT-REG','Probationary','09171080002','xenia.torralba@demo.raquel.local','Lucena City','Quezon','Linda Torralba','Mother','09181080002',1.60,54.00,'AB+'),
('DEMO-IT-P02','Yuri','Cabrera','Ong','2000-04-06','San Pablo City, Laguna','Male','Single','2026-08-24',NULL,810,102,'DEMO-IT-REG','Probationary','09171080003','yuri.cabrera@demo.raquel.local','San Pablo City','Laguna','Ben Cabrera','Father','09181080003',1.71,67.00,'B+'),
('DEMO-MKT-REG','Zara','Alcantara','Vega','1990-06-10','Lipa City, Batangas','Female','Married','2020-02-03','2020-08-03',900,102,'CEO-001','Regular','09171090001','zara.alcantara@demo.raquel.local','Tayabas City','Quezon','Luis Alcantara','Spouse','09181090001',1.63,56.00,'A+'),
('DEMO-MKT-P01','Alyssa','Padilla','Cua','2002-09-02','Lucena City, Quezon','Female','Single','2026-07-06',NULL,904,102,'DEMO-MKT-REG','Probationary','09171090002','alyssa.padilla@demo.raquel.local','Lucena City','Quezon','Tess Padilla','Mother','09181090002',1.58,51.00,'O+'),
('DEMO-MKT-P02','Bruno','Estrella','Kho','2001-01-15','Candelaria, Quezon','Male','Single','2026-08-03',NULL,905,102,'DEMO-MKT-REG','Probationary','09171090003','bruno.estrella@demo.raquel.local','Candelaria','Quezon','Joel Estrella','Father','09181090003',1.73,69.00,'B+'),
('DEMO-OOP-REG','Clarisse','Montemayor','Roxas','1993-08-28','Tayabas City, Quezon','Female','Single','2021-03-08','2021-09-08',1101,102,'CEO-001','Regular','09171100001','clarisse.montemayor@demo.raquel.local','Tayabas City','Quezon','Celia Montemayor','Mother','09181100001',1.61,54.00,'A+'),
('DEMO-OOP-P01','Diego','Marquez','Lo','2000-02-24','Lucena City, Quezon','Male','Single','2026-07-13',NULL,1102,102,'CEO-001','Probationary','09171100002','diego.marquez@demo.raquel.local','Lucena City','Quezon','Nestor Marquez','Father','09181100002',1.72,68.00,'O+'),
('DEMO-OOP-P02','Elise','Abad','Tiu','2002-10-30','Sariaya, Quezon','Female','Single','2026-08-10',NULL,1103,102,'CEO-001','Probationary','09171100003','elise.abad@demo.raquel.local','Sariaya','Quezon','Myrna Abad','Mother','09181100003',1.59,52.00,'B+'),
('DEMO-OPS-REG','Felix','Aragon','Pineda','1985-04-17','Lucena City, Quezon','Male','Married','2015-07-01','2016-01-01',1001,102,'CEO-001','Regular','09171110001','felix.aragon@demo.raquel.local','Lucena City','Quezon','Rina Aragon','Spouse','09181110001',1.76,78.00,'O+'),
('DEMO-OPS-P01','Gina','Malvar','Jose','1999-05-26','Tayabas City, Quezon','Female','Married','2026-07-20',NULL,1012,141,'DEMO-OPS-REG','Probationary','09171110002','gina.malvar@demo.raquel.local','Tayabas City','Quezon','Arman Malvar','Spouse','09181110002',1.60,55.00,'A+'),
('DEMO-OPS-P02','Hugo','Lacson','Magsino','1998-12-12','Sariaya, Quezon','Male','Single','2026-08-17',NULL,1013,123,'DEMO-OPS-REG','Probationary','09171110003','hugo.lacson@demo.raquel.local','Sariaya','Quezon','Luz Lacson','Mother','09181110003',1.74,72.00,'B+'),
('DEMO-PUR-REG','Iris','Samonte','Que','1991-03-03','Tayabas City, Quezon','Female','Married','2020-09-14','2021-03-14',1200,102,'DEMO-GS-REG','Regular','09171120001','iris.samonte@demo.raquel.local','Tayabas City','Quezon','Anton Samonte','Spouse','09181120001',1.62,56.00,'AB+'),
('DEMO-PUR-P01','Jonas','Guevarra','See','2001-06-09','Lucena City, Quezon','Male','Single','2026-07-27',NULL,1202,102,'DEMO-PUR-REG','Probationary','09171120002','jonas.guevarra@demo.raquel.local','Lucena City','Quezon','Fely Guevarra','Mother','09181120002',1.70,66.00,'O+'),
('DEMO-PUR-P02','Kyla','Natividad','Ang','2002-11-18','Pagbilao, Quezon','Female','Single','2026-08-24',NULL,1202,102,'DEMO-PUR-REG','Probationary','09171120003','kyla.natividad@demo.raquel.local','Pagbilao','Quezon','Rene Natividad','Father','09181120003',1.58,50.00,'A+');

-- Regular anchors first, so probationary employees can resolve reports_to safely.
INSERT INTO employees
    (employee_code,first_name,last_name,middle_name,date_of_birth,place_of_birth,gender,civil_status,
     hire_date,regularization_effective_date,job_title_id,job_title,department_id,rank_category_id,
     branch_id,reports_to,employment_status,employment_type,contract_start_date,is_active)
SELECT r.employee_code,r.first_name,r.last_name,r.middle_name,r.birth_date,r.birth_place,r.gender,r.civil_status,
       r.hire_date,r.regularization_date,j.job_title_id,j.job_title,j.department_id,j.rank_category_id,
       r.branch_id,manager.employee_id,r.employment_status,'Full-time',r.hire_date,1
FROM tmp_presentation_employees r
JOIN job_titles j ON j.job_title_id=r.job_title_id AND j.is_active=1
LEFT JOIN employees manager ON manager.employee_code=r.reports_to_code AND manager.is_active=1 AND manager.deleted_at IS NULL
WHERE r.employment_status='Regular'
  AND NOT EXISTS (SELECT 1 FROM employees e WHERE e.employee_code=r.employee_code);

-- Purchasing reports to the newly seeded General Services manager. Both anchors are inserted above,
-- so resolve this cross-department dependency after the anchor insert is complete.
UPDATE employees purchasing
JOIN employees gs_manager ON gs_manager.employee_code='DEMO-GS-REG'
SET purchasing.reports_to=gs_manager.employee_id
WHERE purchasing.employee_code='DEMO-PUR-REG'
  AND purchasing.first_name='Iris' AND purchasing.last_name='Samonte'
  AND purchasing.reports_to IS NULL;

INSERT INTO employees
    (employee_code,first_name,last_name,middle_name,date_of_birth,place_of_birth,gender,civil_status,
     hire_date,job_title_id,job_title,department_id,rank_category_id,branch_id,reports_to,
     employment_status,employment_type,contract_start_date,contract_end_date,
     probationary_next_evaluation_type,probationary_next_evaluation_due_date,is_active)
SELECT r.employee_code,r.first_name,r.last_name,r.middle_name,r.birth_date,r.birth_place,r.gender,r.civil_status,
       r.hire_date,j.job_title_id,j.job_title,j.department_id,j.rank_category_id,r.branch_id,manager.employee_id,
       r.employment_status,'Full-time',r.hire_date,DATE_ADD(r.hire_date,INTERVAL 6 MONTH),
       'Initial',DATE_ADD(r.hire_date,INTERVAL 3 MONTH),1
FROM tmp_presentation_employees r
JOIN job_titles j ON j.job_title_id=r.job_title_id AND j.is_active=1
LEFT JOIN employees manager ON manager.employee_code=r.reports_to_code AND manager.is_active=1 AND manager.deleted_at IS NULL
WHERE r.employment_status='Probationary'
  AND NOT EXISTS (SELECT 1 FROM employees e WHERE e.employee_code=r.employee_code);

INSERT INTO employee_details (employee_id,height_m,weight_kg,blood_type,citizenship)
SELECT e.employee_id,r.height_m,r.weight_kg,r.blood_type,'Filipino'
FROM tmp_presentation_employees r JOIN employees e ON e.employee_code=r.employee_code AND e.first_name=r.first_name AND e.last_name=r.last_name
WHERE NOT EXISTS (SELECT 1 FROM employee_details x WHERE x.employee_id=e.employee_id);

INSERT INTO employee_contacts (employee_id,mobile_number,personal_email)
SELECT e.employee_id,r.mobile_number,r.personal_email
FROM tmp_presentation_employees r JOIN employees e ON e.employee_code=r.employee_code AND e.first_name=r.first_name AND e.last_name=r.last_name
WHERE NOT EXISTS (SELECT 1 FROM employee_contacts x WHERE x.employee_id=e.employee_id);

INSERT INTO employee_addresses (employee_id,address_type,region,house_no,street,barangay,city,province,zip_code)
SELECT e.employee_id,a.address_type,'Region IV-A (CALABARZON)',
       IF(a.address_type='Residential','12','48'),'Presentation Seed Street','Demo Barangay',r.city,r.province,'4301'
FROM tmp_presentation_employees r
JOIN employees e ON e.employee_code=r.employee_code AND e.first_name=r.first_name AND e.last_name=r.last_name
CROSS JOIN (SELECT 'Residential' address_type UNION ALL SELECT 'Permanent') a
WHERE NOT EXISTS (SELECT 1 FROM employee_addresses x WHERE x.employee_id=e.employee_id AND x.address_type=a.address_type);

INSERT INTO employee_emergency_contacts (employee_id,contact_name,relationship,contact_number,is_primary)
SELECT e.employee_id,r.emergency_name,r.emergency_relationship,r.emergency_number,1
FROM tmp_presentation_employees r JOIN employees e ON e.employee_code=r.employee_code AND e.first_name=r.first_name AND e.last_name=r.last_name
WHERE NOT EXISTS (SELECT 1 FROM employee_emergency_contacts x WHERE x.employee_id=e.employee_id AND x.is_primary=1);

DROP TEMPORARY TABLE tmp_presentation_employees;
COMMIT;

SELECT COUNT(*) AS presentation_employee_count
FROM employees WHERE employee_code LIKE 'DEMO-%';
