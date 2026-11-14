-- Fictional executive employee records. Import once after the three base seeds, on a fresh database.
-- Order: 1st_schema_tables.sql -> 2nd_seed_organization.sql ->
--        3rd_seed_HR_accounts_.sql -> 4th_seed_executive_portal_accounts.sql
-- Adds CEO-001 and AP-001 as employees only; create portal accounts in Admin when needed.
-- No HRIS executive accounts, templates, or completed evaluations are created.
-- Duplicate Company IDs cause an error; existing employees are never replaced.
SET NAMES utf8mb4;
USE raquel_hris;
SET FOREIGN_KEY_CHECKS = 1;
START TRANSACTION;

-- President and CEO: Office of the President (organization job title 1100).
INSERT INTO employees
(employee_code,first_name,last_name,middle_name,hire_date,regularization_effective_date,
 date_of_birth,place_of_birth,gender,civil_status,job_title_id,job_title,
 department_id,rank_category_id,branch_id,reports_to,employment_status,employment_type,is_active)
VALUES
('CEO-001','Isabella','Villanueva','Montenegro','2005-01-10','2005-07-10',
 '1974-08-19','Lucena City, Quezon','Female','Married',1100,
 (SELECT job_title FROM job_titles WHERE job_title_id=1100),
 (SELECT department_id FROM job_titles WHERE job_title_id=1100),
 (SELECT rank_category_id FROM job_titles WHERE job_title_id=1100),
 102,NULL,'Regular','Full-time',1);
SET @ceo_employee_id = LAST_INSERT_ID();

-- VP for Acquired Properties: Acquired Properties (organization job title 100).
INSERT INTO employees
(employee_code,first_name,last_name,middle_name,hire_date,regularization_effective_date,
 date_of_birth,place_of_birth,gender,civil_status,job_title_id,job_title,
 department_id,rank_category_id,branch_id,reports_to,employment_status,employment_type,is_active)
VALUES
('AP-001','Marisol','Santos','Reyes','2012-04-16','2012-10-16',
 '1981-02-23','Tayabas City, Quezon','Female','Married',100,
 (SELECT job_title FROM job_titles WHERE job_title_id=100),
 (SELECT department_id FROM job_titles WHERE job_title_id=100),
 (SELECT rank_category_id FROM job_titles WHERE job_title_id=100),
 102,@ceo_employee_id,'Regular','Full-time',1);
SET @ap_vp_employee_id = LAST_INSERT_ID();

INSERT INTO employee_contacts (employee_id,personal_email,mobile_number,telephone_number) VALUES
(@ceo_employee_id,'isabella.villanueva@example.com','09170005001','042-555-5001'),
(@ap_vp_employee_id,'marisol.santos@example.com','09170005002','042-555-5002');

INSERT INTO employee_details (employee_id,height_m,weight_kg,blood_type,citizenship) VALUES
(@ceo_employee_id,1.64,60.0,'A+','Filipino'),
(@ap_vp_employee_id,1.60,57.0,'O+','Filipino');

INSERT INTO employee_addresses (employee_id,address_type,region,barangay,city,province) VALUES
(@ceo_employee_id,'Residential','Region IV-A (CALABARZON)','Mayuwi','Tayabas City','Quezon'),
(@ceo_employee_id,'Permanent','Region IV-A (CALABARZON)','Mayuwi','Tayabas City','Quezon'),
(@ap_vp_employee_id,'Residential','Region IV-A (CALABARZON)','Isabang','Tayabas City','Quezon'),
(@ap_vp_employee_id,'Permanent','Region IV-A (CALABARZON)','Isabang','Tayabas City','Quezon');

INSERT INTO employee_emergency_contacts (employee_id,contact_name,relationship,contact_number,is_primary) VALUES
(@ceo_employee_id,'Gabriel Villanueva','Spouse','09170005003',1),
(@ap_vp_employee_id,'Rafael Santos','Spouse','09170005004',1);

COMMIT;

SELECT employee_code,first_name,last_name
FROM employees
WHERE employee_code IN ('CEO-001','AP-001');
