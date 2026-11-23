SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;
START TRANSACTION;

-- Add every section used by manager/supervisor/staff view-employee.php completion formula.
-- Only deterministic DEMO-* presentation employees are targeted.

INSERT INTO employee_family (employee_id,member_type,surname,first_name,middle_name,occupation)
SELECT e.employee_id,'Mother',e.last_name,'Maria','Santos','Self-employed'
FROM employees e
WHERE e.employee_code LIKE 'DEMO-%'
  AND NOT EXISTS (SELECT 1 FROM employee_family x WHERE x.employee_id=e.employee_id);

INSERT INTO employee_trainings
    (employee_id,date_from,date_to,training_title,training_type,no_of_hours,conducted_by)
SELECT e.employee_id,DATE_SUB(e.hire_date,INTERVAL 45 DAY),DATE_SUB(e.hire_date,INTERVAL 43 DAY),
       CASE e.department_id
         WHEN 1 THEN 'Property Documentation and Client Service'
         WHEN 2 THEN 'Internal Audit Fundamentals'
         WHEN 3 THEN 'Business Development and Market Research'
         WHEN 4 THEN 'Regulatory Compliance Fundamentals'
         WHEN 5 THEN 'Financial Controls and Cash Management'
         WHEN 6 THEN 'Workplace Safety and Facilities Operations'
         WHEN 7 THEN 'Human Resource Records and Data Privacy'
         WHEN 8 THEN 'Information Security Awareness'
         WHEN 9 THEN 'Responsible Marketing and Customer Engagement'
         WHEN 10 THEN 'Executive Office Records Management'
         WHEN 11 THEN 'Branch Operations and Customer Service'
         WHEN 12 THEN 'Ethical Procurement and Vendor Management'
       END,
       'Technical',16.00,'Raquel Learning and Development Center'
FROM employees e
WHERE e.employee_code LIKE 'DEMO-%'
  AND NOT EXISTS (SELECT 1 FROM employee_trainings x WHERE x.employee_id=e.employee_id);

INSERT INTO employee_eligibility
    (employee_id,license_title,date_from,date_to,license_number,date_of_exam,place_of_exam)
SELECT e.employee_id,
       CASE
         WHEN e.job_title LIKE 'Driver%' THEN 'Professional Driver License'
         WHEN e.department_id=5 THEN 'Bookkeeping NC III'
         WHEN e.department_id=8 THEN 'Information Technology Support Certification'
         ELSE 'Career Service Professional Eligibility'
       END,
       DATE_SUB(e.hire_date,INTERVAL 1 YEAR),NULL,
       CONCAT('DEMO-',REPLACE(e.employee_code,'DEMO-',''),'-ELIG'),
       DATE_SUB(e.hire_date,INTERVAL 13 MONTH),'Lucena City, Quezon'
FROM employees e
WHERE e.employee_code LIKE 'DEMO-%'
  AND NOT EXISTS (SELECT 1 FROM employee_eligibility x WHERE x.employee_id=e.employee_id);

INSERT INTO employee_skills (employee_id,skill_name)
SELECT e.employee_id,
       CASE e.department_id
         WHEN 1 THEN 'Property record verification'
         WHEN 2 THEN 'Audit documentation'
         WHEN 3 THEN 'Market research'
         WHEN 4 THEN 'Compliance monitoring'
         WHEN 5 THEN 'Financial reconciliation'
         WHEN 6 THEN 'Facilities coordination'
         WHEN 7 THEN 'Personnel records administration'
         WHEN 8 THEN 'Technical troubleshooting'
         WHEN 9 THEN 'Campaign coordination'
         WHEN 10 THEN 'Executive correspondence'
         WHEN 11 THEN 'Branch operations support'
         WHEN 12 THEN 'Purchase order documentation'
       END
FROM employees e
WHERE e.employee_code LIKE 'DEMO-%'
  AND NOT EXISTS (SELECT 1 FROM employee_skills x WHERE x.employee_id=e.employee_id);

INSERT INTO employee_government_ids (employee_id,sss_number,philhealth_number,pagibig_number,tin_number)
SELECT e.employee_id,
       CONCAT('DEMO-SSS-',LPAD(e.employee_id,6,'0')),
       CONCAT('DEMO-PHIC-',LPAD(e.employee_id,6,'0')),
       CONCAT('DEMO-HD-',LPAD(e.employee_id,6,'0')),
       CONCAT('DEMO-TIN-',LPAD(e.employee_id,6,'0'))
FROM employees e
WHERE e.employee_code LIKE 'DEMO-%'
  AND NOT EXISTS (SELECT 1 FROM employee_government_ids x WHERE x.employee_id=e.employee_id);

INSERT INTO employee_disclosures
    (employee_id,is_related_to_company,has_admin_offense,has_criminal_charge,
     has_criminal_conviction,has_been_separated,is_pwd,is_solo_parent,
     has_recent_hospital,has_current_treatment)
SELECT e.employee_id,0,0,0,0,0,0,0,0,0
FROM employees e
WHERE e.employee_code LIKE 'DEMO-%'
  AND NOT EXISTS (SELECT 1 FROM employee_disclosures x WHERE x.employee_id=e.employee_id);

INSERT INTO employee_personal_properties (employee_id,description,year_acquired,acquisition_cost)
SELECT e.employee_id,'Personal laptop computer and mobile phone',YEAR(e.hire_date),45000.00
FROM employees e
WHERE e.employee_code LIKE 'DEMO-%'
  AND NOT EXISTS (SELECT 1 FROM employee_personal_properties x WHERE x.employee_id=e.employee_id);

INSERT INTO employee_references (employee_id,reference_name,reference_address,reference_telephone)
SELECT e.employee_id,CONCAT('Roberto ',e.last_name),
       CONCAT('Demo Barangay, ',COALESCE(a.city,'Tayabas City'),', ',COALESCE(a.province,'Quezon')),
       CONCAT('0919',LPAD(e.employee_id,7,'0'))
FROM employees e
LEFT JOIN employee_addresses a ON a.employee_id=e.employee_id AND a.address_type='Permanent'
WHERE e.employee_code LIKE 'DEMO-%'
  AND NOT EXISTS (SELECT 1 FROM employee_references x WHERE x.employee_id=e.employee_id);

-- Probationary employees need a prior internship/work row for the profile's Work section.
-- Regular employees already receive two internal appointment-history rows in file 03.
INSERT INTO employee_work_experience
    (employee_id,date_from,date_to,job_title,company_name,monthly_salary,appointment_status,reason_for_leaving)
SELECT e.employee_id,DATE_SUB(e.hire_date,INTERVAL 6 MONTH),DATE_SUB(e.hire_date,INTERVAL 1 DAY),
       'Student Intern','Quezon Community Cooperative',8000.00,'Internship','Completed internship program'
FROM employees e
WHERE e.employee_code LIKE 'DEMO-%-P__' AND e.employment_status='Probationary'
  AND NOT EXISTS (SELECT 1 FROM employee_work_experience x WHERE x.employee_id=e.employee_id);

COMMIT;

SELECT 'PROFILE_SUPPORT_ROWS' AS result_name,
       (SELECT COUNT(*) FROM employee_family x JOIN employees e ON e.employee_id=x.employee_id WHERE e.employee_code LIKE 'DEMO-%') family_rows,
       (SELECT COUNT(*) FROM employee_trainings x JOIN employees e ON e.employee_id=x.employee_id WHERE e.employee_code LIKE 'DEMO-%') training_rows,
       (SELECT COUNT(*) FROM employee_eligibility x JOIN employees e ON e.employee_id=x.employee_id WHERE e.employee_code LIKE 'DEMO-%') eligibility_rows,
       (SELECT COUNT(*) FROM employee_skills x JOIN employees e ON e.employee_id=x.employee_id WHERE e.employee_code LIKE 'DEMO-%') skill_rows,
       (SELECT COUNT(*) FROM employee_government_ids x JOIN employees e ON e.employee_id=x.employee_id WHERE e.employee_code LIKE 'DEMO-%') government_id_rows,
       (SELECT COUNT(*) FROM employee_personal_properties x JOIN employees e ON e.employee_id=x.employee_id WHERE e.employee_code LIKE 'DEMO-%') asset_rows,
       (SELECT COUNT(*) FROM employee_references x JOIN employees e ON e.employee_id=x.employee_id WHERE e.employee_code LIKE 'DEMO-%') reference_rows;
