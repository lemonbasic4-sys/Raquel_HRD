-- Main Office probationary workflow fixture. Import after base seeds 1-4.
-- Fictional people and accounts. No evaluations or regularization are seeded.
SET NAMES utf8mb4;
USE raquel_hris;
DELIMITER //
CREATE PROCEDURE seed_main_office_probationary()
BEGIN
  DECLARE v_conflicts INT DEFAULT 0;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;
  SELECT COUNT(*) INTO v_conflicts FROM employees WHERE employee_code LIKE 'MO26-%';
  IF v_conflicts > 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MO26 employee code already exists; import only once'; END IF;
  SELECT COUNT(*) INTO v_conflicts FROM users WHERE username LIKE 'mo26.%';
  IF v_conflicts > 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MO26 account already exists; import only once'; END IF;
  SELECT COUNT(*) INTO v_conflicts FROM evaluation_templates WHERE form_code LIKE 'MO26-%';
  IF v_conflicts > 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MO26 template already exists; import only once'; END IF;
  IF (SELECT COUNT(*) FROM departments WHERE is_active=1 AND deleted_at IS NULL) <> 12
    OR (SELECT COUNT(*) FROM branches WHERE branch_id=102 AND is_active=1) <> 1
    OR (SELECT COUNT(*) FROM employees WHERE employee_code IN ('CEO-001','AP-001') AND branch_id=102 AND is_active=1) <> 2
    OR (SELECT COUNT(*) FROM employees WHERE employee_id IN (101,301) AND branch_id=102 AND is_active=1) <> 2
  THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Expected fresh base organization, HR, CEO and AP seeds'; END IF;

  -- Specific position holders follow the job_titles.reports_to chain.
  CREATE TEMPORARY TABLE mo26_people (
    employee_code VARCHAR(30) PRIMARY KEY, first_name VARCHAR(100), last_name VARCHAR(100),
    job_title_id INT, head_code VARCHAR(30), status_name VARCHAR(20), hire_date DATE
  );
  INSERT INTO mo26_people VALUES
  ('MO26-AP-M','Marina','Alvarez',101,'AP-001','Regular','2017-02-06'),
  ('MO26-AP-H','Adrian','Reyes',105,'MO26-AP-M','Regular','2020-03-02'),
  ('MO26-AP-P','Lara','Mendoza',108,'MO26-AP-H','Probationary','2026-10-01'),
  ('MO26-AP-P2','Daphne','Rosales',108,'MO26-AP-H','Probationary','2026-10-15'),
  ('MO26-AU-M1','Bianca','Cruz',200,'CEO-001','Regular','2016-06-06'),
  ('MO26-AU-M2','Ramon','Santos',201,'MO26-AU-M1','Regular','2018-08-13'),
  ('MO26-AU-H','Celia','Navarro',203,'MO26-AU-M2','Regular','2021-02-08'),
  ('MO26-AU-P','Nico','Bautista',206,'MO26-AU-H','Probationary','2026-10-01'),
  ('MO26-AU-P2','Katrina','Vergara',206,'MO26-AU-H','Probationary','2026-10-15'),
  ('MO26-BD-H','Diana','Flores',300,'CEO-001','Regular','2019-04-15'),
  ('MO26-BD-P','Marco','Aquino',302,'MO26-BD-H','Probationary','2026-10-01'),
  ('MO26-BD-P2','Andrea','Pineda',302,'MO26-BD-H','Probationary','2026-10-15'),
  ('MO26-CO-H','Teresa','Garcia',400,'CEO-001','Regular','2019-09-09'),
  ('MO26-CO-S','Arnold','Perez',401,'MO26-CO-H','Regular','2021-11-03'),
  ('MO26-CO-P','Mira','Lopez',403,'MO26-CO-S','Probationary','2026-10-01'),
  ('MO26-CO-P2','Jonas','Herrera',403,'MO26-CO-S','Probationary','2026-10-15'),
  ('MO26-FI-V','Victor','Salazar',500,'CEO-001','Regular','2014-07-07'),
  ('MO26-FI-M','Alma','Rivera',501,'MO26-FI-V','Regular','2018-03-20'),
  ('MO26-FI-H','Joanna','Castro',502,'MO26-FI-M','Regular','2020-05-18'),
  ('MO26-FI-P','Carlo','Dela Cruz',511,'MO26-FI-H','Probationary','2026-10-01'),
  ('MO26-FI-P2','Alyssa','Baring',511,'MO26-FI-H','Probationary','2026-10-15'),
  ('MO26-GS-V','Rafael','Soriano',600,'CEO-001','Regular','2013-02-11'),
  ('MO26-GS-M','Nadine','Velasco',601,'MO26-GS-V','Regular','2018-06-04'),
  ('MO26-GS-H','Dennis','Ocampo',605,'MO26-GS-M','Regular','2020-08-24'),
  ('MO26-GS-P','Paolo','Ramos',618,'MO26-GS-H','Probationary','2026-10-01'),
  ('MO26-GS-P2','Jerome','Abad',618,'MO26-GS-H','Probationary','2026-10-15'),
  ('MO26-HR-P','Sofia','Villanueva',710,'HRD-002','Probationary','2026-10-01'),
  ('MO26-HR-P2','Celine','Mata',710,'HRD-002','Probationary','2026-10-15'),
  ('MO26-IT-M','Felix','Manalo',800,'MO26-OP-V','Regular','2017-01-16'),
  ('MO26-IT-H','Grace','Lim',804,'MO26-IT-M','Regular','2020-07-13'),
  ('MO26-IT-P','Ethan','Tan',810,'MO26-IT-H','Probationary','2026-10-01'),
  ('MO26-IT-P2','Rina','Chua',810,'MO26-IT-H','Probationary','2026-10-15'),
  ('MO26-MK-M','Camille','Diaz',900,'CEO-001','Regular','2017-05-22'),
  ('MO26-MK-H','Oscar','Pascual',902,'MO26-MK-M','Regular','2021-01-11'),
  ('MO26-MK-P','Lea','Fernandez',904,'MO26-MK-H','Probationary','2026-10-01'),
  ('MO26-MK-P2','Joel','Marquez',904,'MO26-MK-H','Probationary','2026-10-15'),
  ('MO26-EX-P','Iris','Morales',1101,'CEO-001','Probationary','2026-10-01'),
  ('MO26-EX-P2','Nina','Escobar',1101,'CEO-001','Probationary','2026-10-15'),
  ('MO26-OP-V','Gabriel','Mercado',1000,'CEO-001','Regular','2012-02-20'),
  ('MO26-OP-M','Monica','Gonzales',1001,'MO26-OP-V','Regular','2018-10-08'),
  ('MO26-OP-H','Edgar','Valdez',1004,'MO26-OP-M','Regular','2020-04-27'),
  ('MO26-OP-P','Jules','Santiago',1007,'MO26-OP-H','Probationary','2026-10-01'),
  ('MO26-OP-P2','Lorenzo','Fajardo',1007,'MO26-OP-H','Probationary','2026-10-15'),
  ('MO26-PU-H','Angela','Domingo',1200,'MO26-GS-M','Regular','2021-03-01'),
  ('MO26-PU-P','Rico','Magsino',1202,'MO26-PU-H','Probationary','2026-10-01'),
  ('MO26-PU-P2','Hannah','Yap',1202,'MO26-PU-H','Probationary','2026-10-15');

  IF EXISTS (SELECT 1 FROM mo26_people p LEFT JOIN job_titles j ON j.job_title_id=p.job_title_id AND j.is_active=1 WHERE j.job_title_id IS NULL)
    OR EXISTS (SELECT 1 FROM mo26_people p JOIN job_titles j ON j.job_title_id=p.job_title_id LEFT JOIN mo26_people h ON h.employee_code=p.head_code LEFT JOIN employees e ON e.employee_code=p.head_code WHERE j.reports_to <> COALESCE(h.job_title_id,e.job_title_id))
  THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Seed position or reporting chain differs from organization seed'; END IF;

  START TRANSACTION;
  INSERT INTO employees (employee_code,first_name,last_name,hire_date,date_of_birth,place_of_birth,gender,civil_status,job_title_id,job_title,department_id,rank_category_id,branch_id,reports_to,employment_status,employment_type,is_active,regularization_effective_date,probationary_next_evaluation_type,probationary_next_evaluation_due_date)
  SELECT p.employee_code,p.first_name,p.last_name,p.hire_date,
    IF(p.status_name='Probationary','2001-03-15','1988-05-12'),'Tayabas City, Quezon',
    IF(p.employee_code IN ('MO26-AP-P','MO26-AU-M1','MO26-BD-H','MO26-CO-H','MO26-CO-P','MO26-FI-M','MO26-FI-H','MO26-GS-M','MO26-HR-P','MO26-IT-H','MO26-MK-M','MO26-MK-P','MO26-EX-P','MO26-OP-M','MO26-PU-H'),'Female','Male'),
    'Single',j.job_title_id,j.job_title,j.department_id,j.rank_category_id,102,h.employee_id,p.status_name,'Full-time',1,
    IF(p.status_name='Regular',DATE_ADD(p.hire_date,INTERVAL 6 MONTH),NULL),
    IF(p.status_name='Probationary','Initial',NULL),
    IF(p.status_name='Probationary',DATE_ADD(p.hire_date,INTERVAL 3 MONTH),NULL)
  FROM mo26_people p JOIN job_titles j ON j.job_title_id=p.job_title_id
  LEFT JOIN employees h ON h.employee_code=p.head_code;
  -- The INSERT above resolves pre-existing heads. Resolve newly inserted heads now.
  UPDATE employees e JOIN mo26_people p ON p.employee_code=e.employee_code
    JOIN employees h ON h.employee_code=p.head_code SET e.reports_to=h.employee_id;

  INSERT INTO employee_contacts (employee_id,personal_email,mobile_number)
  SELECT e.employee_id,CONCAT(LOWER(REPLACE(p.employee_code,'-','.')),'@example.com'),
    CONCAT('0918',LPAD(e.employee_id % 10000000,7,'0'))
  FROM mo26_people p JOIN employees e ON e.employee_code=p.employee_code;

  INSERT INTO users (employee_id,username,email,password_hash,full_name,role,branch_id,is_active,must_change_password)
  SELECT e.employee_id,p.employee_code,
    CONCAT(LOWER(REPLACE(p.employee_code,'-','.')),'@example.com'),
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    CONCAT(p.first_name,' ',p.last_name),'Employee',102,1,1
  FROM mo26_people p JOIN employees e ON e.employee_code=p.employee_code;
  INSERT INTO users (employee_id,username,email,password_hash,full_name,role,branch_id,is_active,must_change_password)
  SELECT e.employee_id,LOWER(REPLACE(e.employee_code,'-','.')),
    CONCAT(LOWER(REPLACE(e.employee_code,'-','.')),'@example.com'),
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    CONCAT(e.first_name,' ',e.last_name),'President and CEO',102,1,1
  FROM employees e WHERE e.employee_code='CEO-001' AND NOT EXISTS (SELECT 1 FROM users u WHERE u.employee_id=e.employee_id);
  -- No executive Employee Portal accounts. The Admin creates these manually.

  -- Two position-specific forms per probationary employee. Eight KRAs total 100%.
  INSERT INTO evaluation_templates (template_name,description,target_department,target_department_id,target_job_title_id,behavior_framework_code,behavior_framework_version,evaluation_type,individual_workflow,kra_weight,behavior_weight,form_code,revision_date,effective_date_form,status,created_by)
  SELECT DISTINCT CONCAT('[SAMPLE] ',t.evaluation_type,' - ',j.job_title,' - Main Office'),
    'Fictional probationary test form. Agree actual role targets before use.',d.department_name,d.department_id,j.job_title_id,
    'CORE-VALUES','1.0',t.evaluation_type,0,80,20,CONCAT('MO26-',p.job_title_id,'-',UPPER(t.evaluation_type)),
    '2026-11-15','2026-11-15','Active',u.user_id
  FROM mo26_people p JOIN job_titles j ON j.job_title_id=p.job_title_id
  JOIN departments d ON d.department_id=j.department_id
  JOIN employees h ON h.employee_code=p.head_code JOIN users u ON u.employee_id=h.employee_id
  CROSS JOIN (SELECT 'Initial' evaluation_type UNION ALL SELECT 'Final') t
  WHERE p.status_name='Probationary';
  INSERT INTO evaluation_template_positions (template_id,job_title_id)
  SELECT template_id,target_job_title_id FROM evaluation_templates WHERE form_code LIKE 'MO26-%';

  INSERT INTO evaluation_criteria (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  SELECT t.template_id,'KRA',k.criterion_name,k.description,k.description,12.5,'Scale_1_4',k.sort_order
  FROM evaluation_templates t
  CROSS JOIN (
    SELECT 1 sort_order,'Work plan delivery' criterion_name,'Complete assigned work by the agreed deadline and document outputs.' description
    UNION ALL SELECT 2,'Accuracy','Submit work with no unresolved material errors.'
    UNION ALL SELECT 3,'Role knowledge','Apply documented procedures to routine tasks correctly.'
    UNION ALL SELECT 4,'Compliance','Follow required controls and escalate exceptions promptly.'
    UNION ALL SELECT 5,'Service response','Acknowledge assigned internal or client requests within one working day.'
    UNION ALL SELECT 6,'Documentation','Keep complete and traceable records for assigned work.'
    UNION ALL SELECT 7,'Collaboration','Coordinate handoffs and communicate blockers before deadlines.'
    UNION ALL SELECT 8,'Learning progress','Complete assigned onboarding activities and apply feedback in the next review.'
  ) k WHERE t.form_code LIKE 'MO26-%';
  INSERT INTO evaluation_criteria (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  SELECT t.template_id,'Behavior',b.criterion_name,b.description,b.description,0,'Scale_1_4',b.sort_order
  FROM evaluation_templates t
  CROSS JOIN (
    SELECT 1 sort_order,'Positive Attitude' criterion_name,'Displays positive attitude at work.' description
    UNION ALL SELECT 2,'Respect','Shows respect to all people in the organization.'
    UNION ALL SELECT 3,'Accountability','Takes full responsibility for assigned work.'
    UNION ALL SELECT 4,'Commitment','Demonstrates commitment to the job.'
    UNION ALL SELECT 5,'Teamwork','Works cooperatively with others.'
    UNION ALL SELECT 6,'Integrity','Exhibits honesty and moral uprightness.'
    UNION ALL SELECT 7,'Continuous Improvement','Makes a consistent effort to improve.'
    UNION ALL SELECT 8,'Excellent Client Experience','Serves internal and external clients well.'
  ) b WHERE t.form_code LIKE 'MO26-%';

  IF (SELECT COUNT(*) FROM employees e JOIN mo26_people p ON p.employee_code=e.employee_code WHERE p.status_name='Probationary' AND e.branch_id=102 AND e.reports_to IS NOT NULL) <> 24
    OR (SELECT COUNT(DISTINCT e.department_id) FROM employees e JOIN mo26_people p ON p.employee_code=e.employee_code WHERE p.status_name='Probationary') <> 12
    OR EXISTS (SELECT 1 FROM employees e JOIN mo26_people p ON p.employee_code=e.employee_code WHERE p.status_name='Probationary' GROUP BY e.department_id HAVING COUNT(*) <> 2)
    OR (SELECT COUNT(*) FROM evaluation_templates WHERE form_code LIKE 'MO26-%' AND individual_workflow=0) <> 24
  THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Seed coverage check failed'; END IF;
  COMMIT;
  DROP TEMPORARY TABLE mo26_people;
END//
DELIMITER ;
CALL seed_main_office_probationary();
DROP PROCEDURE seed_main_office_probationary;
SELECT d.department_name,e.employee_code,e.first_name,e.last_name,h.employee_code AS immediate_head,
       COUNT(DISTINCT t.template_id) AS probationary_templates
FROM employees e JOIN departments d ON d.department_id=e.department_id
JOIN employees h ON h.employee_id=e.reports_to
JOIN evaluation_templates t ON t.target_department_id=e.department_id AND t.target_job_title_id=e.job_title_id AND t.form_code LIKE 'MO26-%'
WHERE e.employee_code LIKE 'MO26-%' AND e.employment_status='Probationary'
GROUP BY d.department_name,e.employee_code,e.first_name,e.last_name,h.employee_code
ORDER BY d.department_name;
