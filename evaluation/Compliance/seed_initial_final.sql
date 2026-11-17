-- Compliance: one shared Initial and one Final form for the two sample probationary employees.
-- Run seed_employees.sql first. Templates only; repeat import deliberately fails.
SET NAMES utf8mb4;
USE raquel_hris;
DROP PROCEDURE IF EXISTS seed_compliance_initial_final;
DELIMITER //
CREATE PROCEDURE seed_compliance_initial_final()
BEGIN
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN
    ROLLBACK; DROP TEMPORARY TABLE IF EXISTS mo26_people; RESIGNAL;
  END;
  IF EXISTS (SELECT 1 FROM evaluation_templates WHERE target_department_id=4 AND form_code IN ('MO26-403-INITIAL','MO26-403-FINAL'))
  THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Department Initial/Final forms already exist; do not import twice'; END IF;
  CREATE TEMPORARY TABLE mo26_people (employee_code VARCHAR(30),head_code VARCHAR(30),job_title_id INT);
  INSERT INTO mo26_people VALUES
  ('MO26-CO-P','MO26-CO-S',403),
  ('MO26-CO-P2','MO26-CO-S',403);
  IF (SELECT COUNT(*) FROM mo26_people p JOIN employees e ON e.employee_code=p.employee_code
       JOIN employees h ON h.employee_code=p.head_code AND e.reports_to=h.employee_id
       WHERE e.job_title_id=p.job_title_id AND e.employment_status='Probationary' AND e.is_active=1 AND e.deleted_at IS NULL
         AND h.is_active=1 AND h.deleted_at IS NULL)<>2
  THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Import department fictional employees first'; END IF;
  START TRANSACTION;
  -- Two position-specific forms per probationary employee. Eight KRAs total 100%.
  INSERT INTO evaluation_templates (template_name,description,target_department,target_department_id,target_job_title_id,behavior_framework_code,behavior_framework_version,evaluation_type,individual_workflow,kra_weight,behavior_weight,form_code,revision_date,effective_date_form,status,created_by)
  SELECT DISTINCT CONCAT('[SAMPLE] ',t.evaluation_type,' - ',j.job_title,' - Main Office'),
    'Fictional probationary test form. Agree actual role targets before use.',d.department_name,d.department_id,j.job_title_id,
    'CORE-VALUES','1.0',t.evaluation_type,0,80,20,CONCAT('MO26-',p.job_title_id,'-',UPPER(t.evaluation_type)),
    '2026-11-15','2026-11-15','Active',u.user_id
  FROM mo26_people p JOIN job_titles j ON j.job_title_id=p.job_title_id
  JOIN departments d ON d.department_id=j.department_id
  JOIN employees h ON h.employee_code=p.head_code LEFT JOIN users u ON u.user_id=(SELECT MIN(u2.user_id) FROM users u2 WHERE u2.employee_id=h.employee_id AND u2.is_active=1 AND u2.deleted_at IS NULL AND u2.account_hold=0)
  CROSS JOIN (SELECT 'Initial' evaluation_type UNION ALL SELECT 'Final') t
  ;
  INSERT INTO evaluation_template_positions (template_id,job_title_id)
  SELECT template_id,target_job_title_id FROM evaluation_templates WHERE target_department_id=4 AND form_code LIKE 'MO26-%';

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
  ) k WHERE t.target_department_id=4 AND t.form_code LIKE 'MO26-%';
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
  ) b WHERE t.target_department_id=4 AND t.form_code LIKE 'MO26-%';


  IF (SELECT COUNT(*) FROM evaluation_templates WHERE target_department_id=4 AND form_code IN ('MO26-403-INITIAL','MO26-403-FINAL'))<>2
     OR EXISTS (SELECT 1 FROM evaluation_templates t WHERE t.target_department_id=4 AND t.form_code IN ('MO26-403-INITIAL','MO26-403-FINAL')
       AND ((SELECT COUNT(*) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='KRA')<>8
         OR (SELECT SUM(c.weight) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='KRA')<>100
         OR (SELECT COUNT(*) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='Behavior')<>8))
  THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Initial/Final criteria validation failed'; END IF;
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT DISTINCT t.form_code,h.employee_id,IF(h.employee_code='CEO-001','President and CEO','Employee')
  FROM evaluation_templates t JOIN mo26_people p ON p.job_title_id=t.target_job_title_id
    JOIN employees h ON h.employee_code=p.head_code
  WHERE t.target_department_id=4 AND t.form_code IN ('MO26-403-INITIAL','MO26-403-FINAL')
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  COMMIT;
  DROP TEMPORARY TABLE mo26_people;
END //
DELIMITER ;
CALL seed_compliance_initial_final();
DROP PROCEDURE seed_compliance_initial_final;
