-- Compliance fictional Main Office employees and required reporting heads. No user accounts.
-- Prerequisites: database seeds 1-4. No templates, ratings or approvals.
-- Matching existing fixture people are reused; conflicts abort. Existing users are untouched.
SET NAMES utf8mb4;
USE raquel_hris;
-- Fixture ownership is recorded without creating any user accounts.
CREATE TABLE IF NOT EXISTS evaluation_seed_template_owners (
  form_code VARCHAR(50) PRIMARY KEY,
  employee_id INT NOT NULL,
  preferred_role VARCHAR(30) NOT NULL,
  FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
DROP PROCEDURE IF EXISTS seed_compliance_employees;
DELIMITER //
CREATE PROCEDURE seed_compliance_employees()
BEGIN
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    DROP TEMPORARY TABLE IF EXISTS mo26_people,mo26_new;
    RESIGNAL;
  END;
  IF NOT EXISTS (SELECT 1 FROM branches WHERE branch_id=102 AND is_active=1)
     OR NOT EXISTS (SELECT 1 FROM employees WHERE employee_code='CEO-001' AND job_title_id=1100 AND branch_id=102 AND is_active=1 AND deleted_at IS NULL)
  THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Import base database seeds 1-4 first'; END IF;
  CREATE TEMPORARY TABLE mo26_people (
    employee_code VARCHAR(30) PRIMARY KEY,first_name VARCHAR(100),last_name VARCHAR(100),
    job_title_id INT,head_code VARCHAR(30),status_name VARCHAR(20),hire_date DATE
  );
  INSERT INTO mo26_people VALUES
  ('MO26-CO-H','Teresa','Garcia',400,'CEO-001','Regular','2019-09-09'),
  ('MO26-CO-S','Arnold','Perez',401,'MO26-CO-H','Regular','2021-11-03'),
  ('MO26-CO-P','Mira','Lopez',403,'MO26-CO-S','Probationary','2026-10-01'),
  ('MO26-CO-P2','Jonas','Herrera',403,'MO26-CO-S','Probationary','2026-10-15'),
  ('MO26-CO-R','Rhea','Solis',403,'MO26-CO-S','Regular','2020-01-06');
  IF EXISTS (SELECT 1 FROM mo26_people p LEFT JOIN job_titles j ON j.job_title_id=p.job_title_id AND j.is_active=1 WHERE j.job_title_id IS NULL)
     OR EXISTS (SELECT 1 FROM mo26_people p JOIN job_titles j ON j.job_title_id=p.job_title_id LEFT JOIN mo26_people h ON h.employee_code=p.head_code LEFT JOIN employees e ON e.employee_code=p.head_code AND e.is_active=1 AND e.deleted_at IS NULL WHERE NOT (j.reports_to <=> COALESCE(h.job_title_id,e.job_title_id)))
  THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Missing position or required reporting head'; END IF;
  START TRANSACTION;
  IF EXISTS (SELECT 1 FROM employees e JOIN mo26_people p ON p.employee_code=e.employee_code JOIN job_titles j ON j.job_title_id=p.job_title_id
    WHERE NOT (e.job_title_id <=> p.job_title_id) OR e.first_name<>p.first_name OR e.last_name<>p.last_name
      OR NOT (e.department_id <=> j.department_id) OR NOT (e.rank_category_id <=> j.rank_category_id)
      OR NOT (e.branch_id <=> 102) OR e.employment_status<>p.status_name OR e.hire_date<>p.hire_date OR NOT (e.is_active <=> 1) OR e.deleted_at IS NOT NULL)
  THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Existing MO26 employee conflicts with fictional fixture; nothing replaced'; END IF;
  CREATE TEMPORARY TABLE mo26_new AS SELECT p.employee_code FROM mo26_people p
    WHERE NOT EXISTS (SELECT 1 FROM employees e WHERE e.employee_code=p.employee_code);
  INSERT INTO employees (employee_code,first_name,last_name,hire_date,date_of_birth,place_of_birth,gender,civil_status,job_title_id,job_title,department_id,rank_category_id,branch_id,reports_to,employment_status,employment_type,is_active,regularization_effective_date,probationary_next_evaluation_type,probationary_next_evaluation_due_date)
  SELECT p.employee_code,p.first_name,p.last_name,p.hire_date,
    IF(p.status_name='Probationary','2001-03-15','1988-05-12'),'Tayabas City, Quezon',
    IF(p.employee_code IN ('MO26-AP-P','MO26-AU-M1','MO26-BD-H','MO26-CO-H','MO26-CO-P','MO26-FI-M','MO26-FI-H','MO26-GS-M','MO26-HR-P','MO26-IT-H','MO26-MK-M','MO26-MK-P','MO26-EX-P','MO26-OP-M','MO26-PU-H'),'Female','Male'),
    'Single',j.job_title_id,j.job_title,j.department_id,j.rank_category_id,102,h.employee_id,p.status_name,'Full-time',1,
    IF(p.status_name='Regular',DATE_ADD(p.hire_date,INTERVAL 6 MONTH),NULL),
    IF(p.status_name='Probationary','Initial',NULL),
    IF(p.status_name='Probationary',DATE_ADD(p.hire_date,INTERVAL 3 MONTH),NULL)
  FROM mo26_people p JOIN mo26_new n ON n.employee_code=p.employee_code JOIN job_titles j ON j.job_title_id=p.job_title_id
  LEFT JOIN employees h ON h.employee_code=p.head_code;
  -- The INSERT above resolves pre-existing heads. Resolve newly inserted heads now.
  UPDATE employees e JOIN mo26_new n ON n.employee_code=e.employee_code JOIN mo26_people p ON p.employee_code=e.employee_code
    JOIN employees h ON h.employee_code=p.head_code SET e.reports_to=h.employee_id;

  INSERT INTO employee_contacts (employee_id,personal_email,mobile_number)
  SELECT e.employee_id,CONCAT(LOWER(REPLACE(p.employee_code,'-','.')),'@example.com'),
    CONCAT('0918',LPAD(e.employee_id % 10000000,7,'0'))
  FROM mo26_people p JOIN mo26_new n ON n.employee_code=p.employee_code JOIN employees e ON e.employee_code=p.employee_code;


  IF EXISTS (SELECT 1 FROM employees e JOIN mo26_people p ON p.employee_code=e.employee_code
    JOIN employees h ON h.employee_code=p.head_code WHERE NOT (e.reports_to <=> h.employee_id))
  THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Existing fixture reporting line differs; nothing overwritten'; END IF;
  COMMIT;
  SELECT e.employee_code,e.first_name,e.last_name,e.employment_status,j.job_title,h.employee_code AS immediate_head,
    IF(j.department_id=4,'Selected department','Required external head') AS fixture_scope
  FROM mo26_people p JOIN employees e ON e.employee_code=p.employee_code JOIN job_titles j ON j.job_title_id=e.job_title_id
    LEFT JOIN employees h ON h.employee_id=e.reports_to ORDER BY fixture_scope,e.employee_code;
  DROP TEMPORARY TABLE mo26_people,mo26_new;
END //
DELIMITER ;
CALL seed_compliance_employees();
DROP PROCEDURE seed_compliance_employees;
