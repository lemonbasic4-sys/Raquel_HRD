SET NAMES utf8mb4;

SELECT 'DATABASE' AS check_name,DATABASE() AS actual_value,'explicit -D target' AS expected_value;
SELECT 'SERVER_VERSION' AS check_name,VERSION() AS actual_value,'MariaDB 10.4 compatible' AS expected_value;

DROP TEMPORARY TABLE IF EXISTS tmp_expected_presentation_employees;
CREATE TEMPORARY TABLE tmp_expected_presentation_employees (
    employee_code VARCHAR(30) PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    department_id INT NOT NULL,
    expected_status VARCHAR(30) NOT NULL
);

INSERT INTO tmp_expected_presentation_employees VALUES
('DEMO-AP-REG','Adrian','Mendoza',1,'Regular'),('DEMO-AP-P01','Bianca','Navarro',1,'Probationary'),('DEMO-AP-P02','Carlo','Villareal',1,'Probationary'),
('DEMO-AUD-REG','Diana','Mercado',2,'Regular'),('DEMO-AUD-P01','Enzo','Ramos',2,'Probationary'),('DEMO-AUD-P02','Faith','Soriano',2,'Probationary'),
('DEMO-BD-REG','Gabriel','Aquino',3,'Regular'),('DEMO-BD-P01','Hazel','Castillo',3,'Probationary'),('DEMO-BD-P02','Ivan','Domingo',3,'Probationary'),
('DEMO-COM-REG','Julia','Fernandez',4,'Regular'),('DEMO-COM-P01','Kevin','Manalo',4,'Probationary'),('DEMO-COM-P02','Lara','Pascual',4,'Probationary'),
('DEMO-FIN-REG','Marco','Valdez',5,'Regular'),('DEMO-FIN-P01','Nadine','Rosales',5,'Probationary'),('DEMO-FIN-P02','Owen','Salazar',5,'Probationary'),
('DEMO-GS-REG','Paula','Ignacio',6,'Regular'),('DEMO-GS-P01','Rafael','Bonifacio',6,'Probationary'),('DEMO-GS-P02','Sofia','Aguilar',6,'Probationary'),
('DEMO-HR-REG','Tristan','Dela Cruz',7,'Regular'),('DEMO-HR-P01','Uma','Reyes',7,'Probationary'),('DEMO-HR-P02','Vince','Lorenzo',7,'Probationary'),
('DEMO-IT-REG','Warren','Santiago',8,'Regular'),('DEMO-IT-P01','Xenia','Torralba',8,'Probationary'),('DEMO-IT-P02','Yuri','Cabrera',8,'Probationary'),
('DEMO-MKT-REG','Zara','Alcantara',9,'Regular'),('DEMO-MKT-P01','Alyssa','Padilla',9,'Probationary'),('DEMO-MKT-P02','Bruno','Estrella',9,'Probationary'),
('DEMO-OOP-REG','Clarisse','Montemayor',10,'Regular'),('DEMO-OOP-P01','Diego','Marquez',10,'Probationary'),('DEMO-OOP-P02','Elise','Abad',10,'Probationary'),
('DEMO-OPS-REG','Felix','Aragon',11,'Regular'),('DEMO-OPS-P01','Gina','Malvar',11,'Probationary'),('DEMO-OPS-P02','Hugo','Lacson',11,'Probationary'),
('DEMO-PUR-REG','Iris','Samonte',12,'Regular'),('DEMO-PUR-P01','Jonas','Guevarra',12,'Probationary'),('DEMO-PUR-P02','Kyla','Natividad',12,'Probationary');

INSERT INTO tmp_expected_presentation_employees VALUES
('DEMO-AUD-REG2','Liam','Herrera',2,'Regular'),('DEMO-BD-REG2','Mia','Robles',3,'Regular'),
('DEMO-COM-REG2','Noah','Evangelista',4,'Regular'),('DEMO-FIN-REG2','Olivia','Lim',5,'Regular'),
('DEMO-GS-REG2','Paolo','Serrano',6,'Regular'),('DEMO-IT-REG2','Regina','Velasco',8,'Regular'),
('DEMO-MKT-REG2','Samuel','Cortez',9,'Regular'),('DEMO-OPS-REG2','Therese','Villamor',11,'Regular'),
('DEMO-PUR-REG2','Ulysses','Tan',12,'Regular');

-- PRE-IMPORT GATE: zero rows is PASS. Any returned row is a code collision; stop before import.
SELECT 'CODE_COLLISION_STOP' AS check_name,e.employee_code,e.first_name,e.last_name,
       x.first_name AS expected_first_name,x.last_name AS expected_last_name
FROM employees e JOIN tmp_expected_presentation_employees x ON x.employee_code=e.employee_code
WHERE e.first_name<>x.first_name OR e.last_name<>x.last_name;

-- Protected baseline. Save this output before import and compare it byte-for-byte after import.
SELECT 'PROTECTED_EMPLOYEE_BASELINE' AS check_name,e.employee_id,e.employee_code,e.first_name,e.last_name,
       e.job_title_id,e.job_title,e.department_id,e.rank_category_id,e.branch_id,e.reports_to,
       e.employment_status,e.hire_date,e.regularization_effective_date,e.is_active,e.deleted_at,e.updated_at
FROM employees e WHERE e.employee_id IN (101,301,302) ORDER BY e.employee_id;

SELECT 'PROTECTED_USER_BASELINE' AS check_name,u.user_id,u.employee_id,u.username,u.email,u.full_name,u.role,
       u.branch_id,u.is_active,u.account_hold,u.first_login_completed,u.must_change_password,u.deleted_at,u.updated_at
FROM users u WHERE u.employee_id IN (101,301,302) ORDER BY u.employee_id,u.user_id;

SELECT 'PROTECTED_RELATED_COUNTS' AS check_name,t.table_name,t.row_count
FROM (
 SELECT 'employee_details' table_name,COUNT(*) row_count FROM employee_details WHERE employee_id IN (101,301,302)
 UNION ALL SELECT 'employee_contacts',COUNT(*) FROM employee_contacts WHERE employee_id IN (101,301,302)
 UNION ALL SELECT 'employee_addresses',COUNT(*) FROM employee_addresses WHERE employee_id IN (101,301,302)
 UNION ALL SELECT 'employee_emergency_contacts',COUNT(*) FROM employee_emergency_contacts WHERE employee_id IN (101,301,302)
 UNION ALL SELECT 'employee_education',COUNT(*) FROM employee_education WHERE employee_id IN (101,301,302)
 UNION ALL SELECT 'employee_work_experience',COUNT(*) FROM employee_work_experience WHERE employee_id IN (101,301,302)
) t ORDER BY t.table_name;

-- Organization inventory.
SELECT 'ACTIVE_DEPARTMENTS' AS check_name,COUNT(*) AS actual_count,12 AS expected_count
FROM departments WHERE is_active=1 AND deleted_at IS NULL;
SELECT 'ACTIVE_BRANCHES' AS check_name,COUNT(*) AS actual_count,151 AS discovered_count
FROM branches WHERE is_active=1 AND deleted_at IS NULL;
SELECT 'ACTIVE_DEPARTMENT_INVENTORY' AS check_name,department_id,department_name
FROM departments WHERE is_active=1 AND deleted_at IS NULL ORDER BY department_id;
SELECT 'ACTIVE_BRANCH_INVENTORY' AS check_name,branch_id,branch_name,location
FROM branches WHERE is_active=1 AND deleted_at IS NULL ORDER BY branch_id;

-- Post-import totals and coverage.
SELECT 'PRESENTATION_EMPLOYEES' AS check_name,COUNT(e.employee_id) AS actual_count,45 AS expected_count
FROM tmp_expected_presentation_employees x LEFT JOIN employees e ON e.employee_code=x.employee_code
 AND e.first_name=x.first_name AND e.last_name=x.last_name;

SELECT d.department_id,d.department_name,
       COUNT(CASE WHEN e.employee_code LIKE 'DEMO-%' THEN 1 END) AS new_employee_count,
       COUNT(CASE WHEN e.employee_code LIKE 'DEMO-%' AND e.employment_status='Probationary' THEN 1 END) AS new_probationary_count,
       2 AS minimum_probationary_required,
       IF(COUNT(CASE WHEN e.employee_code LIKE 'DEMO-%' AND e.employment_status='Probationary' THEN 1 END)>=2,'PASS','FAIL') AS result
FROM departments d
LEFT JOIN employees e ON e.department_id=d.department_id AND e.is_active=1 AND e.deleted_at IS NULL
WHERE d.is_active=1 AND d.deleted_at IS NULL
GROUP BY d.department_id,d.department_name ORDER BY d.department_id;

SELECT 'MISSING_REQUIRED_PROFILE_FIELDS' AS check_name,COUNT(*) AS actual_count,0 AS expected_count
FROM employees e
WHERE e.employee_code LIKE 'DEMO-%' AND (
 e.first_name='' OR e.last_name='' OR e.hire_date IS NULL OR e.job_title_id IS NULL OR e.job_title IS NULL
 OR e.department_id IS NULL OR e.rank_category_id IS NULL OR e.branch_id IS NULL OR e.employment_status IS NULL
 OR e.reports_to IS NULL
);

SELECT 'INVALID_OR_MISMATCHED_REFERENCES' AS check_name,COUNT(*) AS actual_count,0 AS expected_count
FROM employees e
LEFT JOIN job_titles j ON j.job_title_id=e.job_title_id
LEFT JOIN departments d ON d.department_id=e.department_id
LEFT JOIN branches b ON b.branch_id=e.branch_id
LEFT JOIN employees manager ON manager.employee_id=e.reports_to
WHERE e.employee_code LIKE 'DEMO-%' AND (
 j.job_title_id IS NULL OR j.department_id<>e.department_id OR j.rank_category_id<>e.rank_category_id
 OR d.department_id IS NULL OR b.branch_id IS NULL OR manager.employee_id IS NULL
);

SELECT 'PROFILE_CHILD_ROWS' AS check_name,
       SUM(ed.employee_id IS NOT NULL) detail_rows,SUM(ec.employee_id IS NOT NULL) contact_rows,
       SUM(addr.address_count=2) employees_with_two_addresses,SUM(emg.employee_id IS NOT NULL) emergency_rows,
       45 AS expected_each
FROM employees e
LEFT JOIN employee_details ed ON ed.employee_id=e.employee_id
LEFT JOIN employee_contacts ec ON ec.employee_id=e.employee_id
LEFT JOIN (SELECT employee_id,COUNT(*) address_count FROM employee_addresses GROUP BY employee_id) addr ON addr.employee_id=e.employee_id
LEFT JOIN (SELECT employee_id,MIN(emergency_id) emergency_id FROM employee_emergency_contacts WHERE is_primary=1 GROUP BY employee_id) emg ON emg.employee_id=e.employee_id
WHERE e.employee_code LIKE 'DEMO-%';

SELECT 'EDUCATION_COVERAGE' AS check_name,
       COUNT(DISTINCT e.employee_id) AS employees_with_education,45 AS expected_count,
       COUNT(DISTINCT CASE WHEN NOT EXISTS (
         SELECT 1 FROM employee_education c WHERE c.employee_id=e.employee_id AND c.education_level='College'
       ) THEN e.employee_id END) AS employees_without_college,
       'at least 1' AS non_college_requirement
FROM employees e JOIN employee_education ee ON ee.employee_id=e.employee_id
WHERE e.employee_code LIKE 'DEMO-%';

SELECT 'REGULARIZATION_HISTORY' AS check_name,
       COUNT(DISTINCT CASE WHEN p.work_id IS NOT NULL AND r.work_id IS NOT NULL THEN e.employee_id END) AS complete_regular_histories,
       21 AS expected_count
FROM employees e
LEFT JOIN employee_work_experience p ON p.employee_id=e.employee_id AND p.company_name='Raquel Pawnshop, Inc.' AND p.appointment_status='Probationary'
LEFT JOIN employee_work_experience r ON r.employee_id=e.employee_id AND r.company_name='Raquel Pawnshop, Inc.' AND r.appointment_status='Regular'
WHERE e.employee_code LIKE 'DEMO-%-REG%' AND e.employment_status='Regular';

SELECT 'EMPLOYEE_PORTAL_ACCOUNTS' AS check_name,
       COUNT(DISTINCT CASE WHEN u.role='Employee' THEN e.employee_id END) AS actual_count,45 AS expected_count
FROM employees e LEFT JOIN users u ON u.employee_id=e.employee_id AND u.is_active=1 AND u.deleted_at IS NULL
WHERE e.employee_code LIKE 'DEMO-%';

SELECT 'REGULAR_HR_DUAL_ACCESS' AS check_name,
       COUNT(DISTINCT u.role) AS actual_role_count,2 AS expected_role_count,
       GROUP_CONCAT(u.role ORDER BY u.role) AS actual_roles
FROM employees e LEFT JOIN users u ON u.employee_id=e.employee_id AND u.is_active=1 AND u.deleted_at IS NULL
WHERE e.employee_code='DEMO-HR-REG' AND u.role IN ('Employee','HR Staff');

-- Mirrors the 12-section completion formula in manager/supervisor/staff view-employee.php.
SELECT 'PROFILE_COMPLETENESS_SUMMARY' AS check_name,
       COUNT(*) AS employee_count,
       MIN(completed_sections) AS minimum_sections,
       MAX(completed_sections) AS maximum_sections,
       SUM(completed_sections=12) AS employees_at_100_percent,
       45 AS expected_employees_at_100_percent
FROM (
    SELECT e.employee_id,
      ((!ISNULL(e.date_of_birth) OR !ISNULL(e.gender))
       + EXISTS(SELECT 1 FROM employee_contacts c WHERE c.employee_id=e.employee_id AND (c.mobile_number IS NOT NULL OR c.telephone_number IS NOT NULL))
       + EXISTS(SELECT 1 FROM employee_family f WHERE f.employee_id=e.employee_id)
       + EXISTS(SELECT 1 FROM employee_education ed WHERE ed.employee_id=e.employee_id)
       + EXISTS(SELECT 1 FROM employee_work_experience w WHERE w.employee_id=e.employee_id)
       + EXISTS(SELECT 1 FROM employee_trainings t WHERE t.employee_id=e.employee_id)
       + EXISTS(SELECT 1 FROM employee_eligibility el WHERE el.employee_id=e.employee_id)
       + (EXISTS(SELECT 1 FROM employee_skills s WHERE s.employee_id=e.employee_id)
          OR EXISTS(SELECT 1 FROM employee_recognitions r WHERE r.employee_id=e.employee_id)
          OR EXISTS(SELECT 1 FROM employee_memberships m WHERE m.employee_id=e.employee_id))
       + EXISTS(SELECT 1 FROM employee_government_ids g WHERE g.employee_id=e.employee_id AND (g.sss_number IS NOT NULL OR g.philhealth_number IS NOT NULL))
       + (EXISTS(SELECT 1 FROM employee_real_properties rp WHERE rp.employee_id=e.employee_id)
          OR EXISTS(SELECT 1 FROM employee_personal_properties pp WHERE pp.employee_id=e.employee_id))
       + EXISTS(SELECT 1 FROM employee_references rf WHERE rf.employee_id=e.employee_id)
       + EXISTS(SELECT 1 FROM employee_addresses a WHERE a.employee_id=e.employee_id)) AS completed_sections
    FROM employees e WHERE e.employee_code LIKE 'DEMO-%'
) completion;

SELECT 'INCOMPLETE_PRESENTATION_PROFILE' AS check_name,employee_code,completed_sections
FROM (
    SELECT e.employee_code,
      ((!ISNULL(e.date_of_birth) OR !ISNULL(e.gender))
       + EXISTS(SELECT 1 FROM employee_contacts c WHERE c.employee_id=e.employee_id AND (c.mobile_number IS NOT NULL OR c.telephone_number IS NOT NULL))
       + EXISTS(SELECT 1 FROM employee_family f WHERE f.employee_id=e.employee_id)
       + EXISTS(SELECT 1 FROM employee_education ed WHERE ed.employee_id=e.employee_id)
       + EXISTS(SELECT 1 FROM employee_work_experience w WHERE w.employee_id=e.employee_id)
       + EXISTS(SELECT 1 FROM employee_trainings t WHERE t.employee_id=e.employee_id)
       + EXISTS(SELECT 1 FROM employee_eligibility el WHERE el.employee_id=e.employee_id)
       + (EXISTS(SELECT 1 FROM employee_skills s WHERE s.employee_id=e.employee_id)
          OR EXISTS(SELECT 1 FROM employee_recognitions r WHERE r.employee_id=e.employee_id)
          OR EXISTS(SELECT 1 FROM employee_memberships m WHERE m.employee_id=e.employee_id))
       + EXISTS(SELECT 1 FROM employee_government_ids g WHERE g.employee_id=e.employee_id AND (g.sss_number IS NOT NULL OR g.philhealth_number IS NOT NULL))
       + (EXISTS(SELECT 1 FROM employee_real_properties rp WHERE rp.employee_id=e.employee_id)
          OR EXISTS(SELECT 1 FROM employee_personal_properties pp WHERE pp.employee_id=e.employee_id))
       + EXISTS(SELECT 1 FROM employee_references rf WHERE rf.employee_id=e.employee_id)
       + EXISTS(SELECT 1 FROM employee_addresses a WHERE a.employee_id=e.employee_id)) AS completed_sections
    FROM employees e WHERE e.employee_code LIKE 'DEMO-%'
) completion
WHERE completed_sections<>12;

SELECT 'DUPLICATE_EMPLOYEE_CODES' AS check_name,employee_code,COUNT(*) AS duplicate_count
FROM employees WHERE employee_code IS NOT NULL GROUP BY employee_code HAVING COUNT(*)>1;
SELECT 'DUPLICATE_USERNAMES' AS check_name,username,COUNT(*) AS duplicate_count
FROM users GROUP BY username HAVING COUNT(*)>1;
SELECT 'DUPLICATE_ACCOUNT_EMAILS' AS check_name,email,COUNT(*) AS duplicate_count
FROM users GROUP BY email HAVING COUNT(*)>1;

DROP TEMPORARY TABLE tmp_expected_presentation_employees;
