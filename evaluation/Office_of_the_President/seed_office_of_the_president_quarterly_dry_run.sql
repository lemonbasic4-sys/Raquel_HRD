-- OFFICE OF THE PRESIDENT QUARTERLY DRY RUN
-- Executive Assistant I-III only. The CEO has no configured reporting head.
-- Base period: 2026-11-15 to 2027-02-14.
-- Post-probation period: 2027-04-15 to 2027-07-14.
-- Templates only. No scores, submissions, approvals, or assignments.
USE raquel_hris;
DROP PROCEDURE IF EXISTS seed_office_of_the_president_quarterly_dry_run;
DELIMITER //
CREATE PROCEDURE seed_office_of_the_president_quarterly_dry_run()
BEGIN
  DECLARE v_ceo INT;
  DECLARE v_base_form INT;
  DECLARE v_later_form INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  IF EXISTS (SELECT 1 FROM evaluation_templates WHERE form_code IN
    ('EX-EA-Q-DRYRUN-20261115','EX-EA-Q-DRYRUN-20270415')) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Executive Assistant Quarterly form already exists; do not import twice';
  END IF;
  IF (SELECT COUNT(*) FROM job_titles WHERE department_id=10 AND job_title_id BETWEEN 1100 AND 1103 AND is_active=1)<>4
     OR NOT EXISTS (SELECT 1 FROM job_titles WHERE job_title_id=1100 AND reports_to IS NULL AND is_active=1)
     OR (SELECT COUNT(*) FROM job_titles WHERE department_id=10 AND job_title_id BETWEEN 1101 AND 1103 AND reports_to=1100 AND is_active=1)<>3 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Expected CEO and Executive Assistant I-III reporting line';
  END IF;

  SELECT MIN(u.user_id) INTO v_ceo FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='CEO-001' AND e.department_id=10 AND e.job_title_id=1100
    AND e.employment_status='Regular' AND e.is_active=1 AND e.deleted_at IS NULL
    AND u.role='President and CEO' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0;


  START TRANSACTION;
  INSERT INTO evaluation_templates
    (template_name,description,target_department,target_department_id,target_job_title_id,
     behavior_framework_code,behavior_framework_version,evaluation_type,individual_workflow,
     assessment_from,assessment_to,kra_weight,behavior_weight,form_code,
     revision_date,effective_date_form,status,created_by)
  VALUES
    ('[DRY RUN] Quarterly - Executive Assistants I-III - Main Office',
     'Executive schedule, correspondence, records, and confidential support.',
     'Office of the President',10,1101,'QUARTERLY-CORE','1','Quarterly',1,
     '2026-11-15','2027-02-14',80,20,'EX-EA-Q-DRYRUN-20261115',
     '2026-11-17','2026-11-17','Active',v_ceo);
  SET v_base_form=LAST_INSERT_ID();
  INSERT INTO evaluation_template_positions (template_id,job_title_id)
  SELECT v_base_form,job_title_id FROM job_titles WHERE department_id=10 AND job_title_id BETWEEN 1101 AND 1103 AND is_active=1;

  -- Eight executive-support KRAs total 100% within the KRA section.
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  VALUES
    (v_base_form,'KRA','Executive calendar coordination','Maintain accurate schedules, priorities, and meeting changes.','Current calendar and timely updates.',20,'Scale_1_4',1),
    (v_base_form,'KRA','Correspondence accuracy','Prepare complete and accurate executive correspondence for review.','Reviewed correspondence and revisions.',15,'Scale_1_4',2),
    (v_base_form,'KRA','Meeting preparation','Arrange approved agendas, materials, and logistics before meetings.','Complete meeting packets and arrangements.',15,'Scale_1_4',3),
    (v_base_form,'KRA','Confidential records','Protect executive files and control access to sensitive information.','Secure and traceable records.',15,'Scale_1_4',4),
    (v_base_form,'KRA','Action tracking','Track assigned executive decisions and follow-up deadlines.','Current action log and escalations.',10,'Scale_1_4',5),
    (v_base_form,'KRA','Visitor and stakeholder service','Coordinate approved visitors and respond professionally.','Timely documented coordination.',10,'Scale_1_4',6),
    (v_base_form,'KRA','Document organization','Maintain searchable versions, approvals, and filing records.','Complete document index.',10,'Scale_1_4',7),
    (v_base_form,'KRA','Issue escalation','Raise schedule conflicts and urgent risks to the CEO promptly.','Timely issue notices.',5,'Scale_1_4',8),
    (v_base_form,'Behavior','Positive Attitude',NULL,'Displays positive attitude at work.',0,'Scale_1_4',1),
    (v_base_form,'Behavior','Respect',NULL,'Shows respect to all people in the organization.',0,'Scale_1_4',2),
    (v_base_form,'Behavior','Accountability',NULL,'Takes responsibility for assigned work.',0,'Scale_1_4',3),
    (v_base_form,'Behavior','Commitment',NULL,'Demonstrates commitment to the job.',0,'Scale_1_4',4),
    (v_base_form,'Behavior','Teamwork',NULL,'Works cooperatively with others.',0,'Scale_1_4',5),
    (v_base_form,'Behavior','Integrity',NULL,'Acts honestly and ethically.',0,'Scale_1_4',6),
    (v_base_form,'Behavior','Continuous Improvement',NULL,'Works consistently to improve.',0,'Scale_1_4',7),
    (v_base_form,'Behavior','Excellent Client Experience',NULL,'Serves internal and external clients well.',0,'Scale_1_4',8);

  -- Later Regular cycle for Iris and Nina after Final and regularization.
  INSERT INTO evaluation_templates
    (template_name,description,target_department,target_department_id,target_job_title_id,
     behavior_framework_code,behavior_framework_version,evaluation_type,individual_workflow,
     assessment_from,assessment_to,kra_weight,behavior_weight,form_code,
     revision_date,effective_date_form,status,created_by)
  VALUES
    ('[DRY RUN] Quarterly - Executive Assistants - Post-Probation 2027',
     'Regular Executive Assistant I-III review after probation.',
     'Office of the President',10,1101,'QUARTERLY-CORE','1','Quarterly',1,
     '2027-04-15','2027-07-14',80,20,'EX-EA-Q-DRYRUN-20270415',
     '2026-11-17','2027-04-15','Active',v_ceo);
  SET v_later_form=LAST_INSERT_ID();
  INSERT INTO evaluation_template_positions (template_id,job_title_id)
  SELECT v_later_form,job_title_id FROM job_titles WHERE department_id=10 AND job_title_id BETWEEN 1101 AND 1103 AND is_active=1;
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  SELECT v_later_form,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order
  FROM evaluation_criteria WHERE template_id=v_base_form;

  IF (SELECT COUNT(*) FROM evaluation_template_positions WHERE template_id IN (v_base_form,v_later_form))<>6
     OR EXISTS (SELECT 1 FROM evaluation_templates t WHERE t.template_id IN (v_base_form,v_later_form)
       AND ((SELECT COUNT(*) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='KRA')<>8
        OR (SELECT COALESCE(SUM(c.weight),0) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='KRA')<>100
        OR (SELECT COUNT(*) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='Behavior')<>8)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Executive Assistant Quarterly form validation failed';
  END IF;

  -- BEGIN SEEDED OWNERS
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'EX-EA-Q-DRYRUN-20261115',employee_id,'President and CEO' FROM employees WHERE employee_code='CEO-001'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'EX-EA-Q-DRYRUN-20270415',employee_id,'President and CEO' FROM employees WHERE employee_code='CEO-001'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  UPDATE evaluation_templates t JOIN evaluation_seed_template_owners o ON o.form_code=t.form_code
  SET t.created_by=(SELECT MIN(u.user_id) FROM users u WHERE u.employee_id=o.employee_id
    AND (u.role=o.preferred_role OR (o.preferred_role='Employee' AND u.role IN ('HR Manager','HR Supervisor','HR Staff')))
    AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0)
  WHERE t.created_by IS NULL;
  -- END SEEDED OWNERS
  COMMIT;
  SELECT 'Executive Assistant Quarterly forms imported' AS result,2 AS forms,3 AS covered_positions;
END //
DELIMITER ;
CALL seed_office_of_the_president_quarterly_dry_run();
DROP PROCEDURE seed_office_of_the_president_quarterly_dry_run;
