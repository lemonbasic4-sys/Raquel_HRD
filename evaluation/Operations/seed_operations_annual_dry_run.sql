-- OPERATIONS ANNUAL DRY RUN
-- Base: 2026-01-01 to 2026-12-31; all 16 active Operations positions.
-- Post-probation Focal Persons: 2027-01-01 to 2027-12-31.
-- Annual self-rating requires an Approved Quarterly evaluation in the same year.
-- Templates only; no ratings, submissions, or approvals.
USE raquel_hris;
DROP PROCEDURE IF EXISTS seed_operations_annual_dry_run;
DELIMITER //
CREATE PROCEDURE seed_operations_annual_dry_run()
BEGIN
  DECLARE v_template INT;
  DECLARE v_source INT;
  DECLARE v_creator INT;
  DECLARE v_count INT DEFAULT 0;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  IF EXISTS (SELECT 1 FROM evaluation_templates WHERE form_code IN ('OP-VP-A-DRYRUN-2026','OP-REG-A-DRYRUN-2026','OP-AREA-A-DRYRUN-2026','OP-FOCAL-A-DRYRUN-2026','OP-STAFF-A-DRYRUN-2026','OP-FOCAL-A-DRYRUN-2027')) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Operations Annual form already exists; do not import twice';
  END IF;
  IF (SELECT COUNT(*) FROM job_titles WHERE department_id=11 AND job_title_id BETWEEN 1000 AND 1016 AND is_active=1)<>16
     OR NOT EXISTS (SELECT 1 FROM job_titles WHERE job_title_id=1000 AND reports_to=1100 AND is_active=1)
     OR (SELECT COUNT(*) FROM job_titles WHERE department_id=11 AND job_title_id IN (1001,1002) AND reports_to=1000 AND is_active=1)<>2
     OR (SELECT COUNT(*) FROM job_titles WHERE department_id=11 AND job_title_id IN (1004,1005,1006) AND reports_to=1001 AND is_active=1)<>3
     OR (SELECT COUNT(*) FROM job_titles WHERE department_id=11 AND job_title_id BETWEEN 1007 AND 1011 AND reports_to=1004 AND is_active=1)<>5
     OR (SELECT COUNT(*) FROM job_titles WHERE department_id=11 AND job_title_id BETWEEN 1012 AND 1016 AND reports_to=1007 AND is_active=1)<>5 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Expected Operations positions and reporting lines';
  END IF;
  START TRANSACTION;

  -- VP for Operations.
  SELECT MIN(u.user_id) INTO v_creator FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='CEO-001' AND e.is_active=1 AND e.deleted_at IS NULL
    AND u.role='President and CEO' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0;

  INSERT INTO evaluation_templates
    (template_name,description,target_department,target_department_id,target_job_title_id,
     behavior_framework_code,behavior_framework_version,evaluation_type,individual_workflow,
     assessment_from,assessment_to,kra_weight,behavior_weight,form_code,
     revision_date,effective_date_form,status,created_by)
  VALUES ('[DRY RUN] Annual - VP for Operations',
    'Operations role targets and documented branch service.',
    'Operations',11,1000,'ANNUAL-CORE','1','Annual',1,
    '2026-01-01','2026-12-31',80,20,'OP-VP-A-DRYRUN-2026',
    '2026-11-17','2026-11-17','Active',v_creator);
  SET v_template=LAST_INSERT_ID();
  INSERT INTO evaluation_template_positions (template_id,job_title_id)
  SELECT v_template,job_title_id FROM job_titles WHERE department_id=11 AND job_title_id IN (1000) AND is_active=1;
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  VALUES
    (v_template,'KRA','Regional operating plan delivery','Complete and document regional operating plan delivery.','Review assigned outputs and records.',20,'Scale_1_4',1),
    (v_template,'KRA','Branch performance monitoring','Complete and document branch performance monitoring.','Review assigned outputs and records.',15,'Scale_1_4',2),
    (v_template,'KRA','Policy and control oversight','Complete and document policy and control oversight.','Review assigned outputs and records.',15,'Scale_1_4',3),
    (v_template,'KRA','Regional issue resolution','Complete and document regional issue resolution.','Review assigned outputs and records.',15,'Scale_1_4',4),
    (v_template,'KRA','People and succession leadership','Complete and document people and succession leadership.','Review assigned outputs and records.',10,'Scale_1_4',5),
    (v_template,'KRA','Cross-department coordination','Complete and document cross-department coordination.','Review assigned outputs and records.',10,'Scale_1_4',6),
    (v_template,'KRA','Resource allocation','Complete and document resource allocation.','Review assigned outputs and records.',10,'Scale_1_4',7),
    (v_template,'KRA','Executive reporting','Complete and document executive reporting.','Review assigned outputs and records.',5,'Scale_1_4',8),
    (v_template,'Behavior','Positive Attitude',NULL,'Displays positive attitude at work.',0,'Scale_1_4',1),
    (v_template,'Behavior','Respect',NULL,'Shows respect to all people in the organization.',0,'Scale_1_4',2),
    (v_template,'Behavior','Accountability',NULL,'Takes responsibility for assigned work.',0,'Scale_1_4',3),
    (v_template,'Behavior','Commitment',NULL,'Demonstrates commitment to the job.',0,'Scale_1_4',4),
    (v_template,'Behavior','Teamwork',NULL,'Works cooperatively with others.',0,'Scale_1_4',5),
    (v_template,'Behavior','Integrity',NULL,'Acts honestly and ethically.',0,'Scale_1_4',6),
    (v_template,'Behavior','Continuous Improvement',NULL,'Works consistently to improve.',0,'Scale_1_4',7),
    (v_template,'Behavior','Excellent Client Experience',NULL,'Serves internal and external clients well.',0,'Scale_1_4',8);
  SET v_count=v_count+1;
  IF (SELECT COUNT(*) FROM evaluation_template_positions WHERE template_id=v_template)<>1
     OR (SELECT COUNT(*) FROM evaluation_criteria WHERE template_id=v_template AND section='KRA')<>8
     OR (SELECT COALESCE(SUM(weight),0) FROM evaluation_criteria WHERE template_id=v_template AND section='KRA')<>100
     OR (SELECT COUNT(*) FROM evaluation_criteria WHERE template_id=v_template AND section='Behavior')<>8 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Operations template validation failed';
  END IF;

  -- Regional Managers I-II.
  SELECT MIN(u.user_id) INTO v_creator FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='MO26-OP-V' AND e.is_active=1 AND e.deleted_at IS NULL
    AND u.role='Employee' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0;

  INSERT INTO evaluation_templates
    (template_name,description,target_department,target_department_id,target_job_title_id,
     behavior_framework_code,behavior_framework_version,evaluation_type,individual_workflow,
     assessment_from,assessment_to,kra_weight,behavior_weight,form_code,
     revision_date,effective_date_form,status,created_by)
  VALUES ('[DRY RUN] Annual - Regional Managers I-II',
    'Operations role targets and documented branch service.',
    'Operations',11,1001,'ANNUAL-CORE','1','Annual',1,
    '2026-01-01','2026-12-31',80,20,'OP-REG-A-DRYRUN-2026',
    '2026-11-17','2026-11-17','Active',v_creator);
  SET v_template=LAST_INSERT_ID();
  INSERT INTO evaluation_template_positions (template_id,job_title_id)
  SELECT v_template,job_title_id FROM job_titles WHERE department_id=11 AND job_title_id IN (1001,1002) AND is_active=1;
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  VALUES
    (v_template,'KRA','Regional target delivery','Complete and document regional target delivery.','Review assigned outputs and records.',20,'Scale_1_4',1),
    (v_template,'KRA','Branch results review','Complete and document branch results review.','Review assigned outputs and records.',15,'Scale_1_4',2),
    (v_template,'KRA','Area coordinator coaching','Complete and document area coordinator coaching.','Review assigned outputs and records.',15,'Scale_1_4',3),
    (v_template,'KRA','Operational compliance','Complete and document operational compliance.','Review assigned outputs and records.',15,'Scale_1_4',4),
    (v_template,'KRA','Customer issue escalation','Complete and document customer issue escalation.','Review assigned outputs and records.',10,'Scale_1_4',5),
    (v_template,'KRA','Staffing coordination','Complete and document staffing coordination.','Review assigned outputs and records.',10,'Scale_1_4',6),
    (v_template,'KRA','Risk and incident response','Complete and document risk and incident response.','Review assigned outputs and records.',10,'Scale_1_4',7),
    (v_template,'KRA','Regional reporting','Complete and document regional reporting.','Review assigned outputs and records.',5,'Scale_1_4',8),
    (v_template,'Behavior','Positive Attitude',NULL,'Displays positive attitude at work.',0,'Scale_1_4',1),
    (v_template,'Behavior','Respect',NULL,'Shows respect to all people in the organization.',0,'Scale_1_4',2),
    (v_template,'Behavior','Accountability',NULL,'Takes responsibility for assigned work.',0,'Scale_1_4',3),
    (v_template,'Behavior','Commitment',NULL,'Demonstrates commitment to the job.',0,'Scale_1_4',4),
    (v_template,'Behavior','Teamwork',NULL,'Works cooperatively with others.',0,'Scale_1_4',5),
    (v_template,'Behavior','Integrity',NULL,'Acts honestly and ethically.',0,'Scale_1_4',6),
    (v_template,'Behavior','Continuous Improvement',NULL,'Works consistently to improve.',0,'Scale_1_4',7),
    (v_template,'Behavior','Excellent Client Experience',NULL,'Serves internal and external clients well.',0,'Scale_1_4',8);
  SET v_count=v_count+1;
  IF (SELECT COUNT(*) FROM evaluation_template_positions WHERE template_id=v_template)<>2
     OR (SELECT COUNT(*) FROM evaluation_criteria WHERE template_id=v_template AND section='KRA')<>8
     OR (SELECT COALESCE(SUM(weight),0) FROM evaluation_criteria WHERE template_id=v_template AND section='KRA')<>100
     OR (SELECT COUNT(*) FROM evaluation_criteria WHERE template_id=v_template AND section='Behavior')<>8 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Operations template validation failed';
  END IF;

  -- Area Coordinators I-III.
  SELECT MIN(u.user_id) INTO v_creator FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='MO26-OP-M' AND e.is_active=1 AND e.deleted_at IS NULL
    AND u.role='Employee' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0;

  INSERT INTO evaluation_templates
    (template_name,description,target_department,target_department_id,target_job_title_id,
     behavior_framework_code,behavior_framework_version,evaluation_type,individual_workflow,
     assessment_from,assessment_to,kra_weight,behavior_weight,form_code,
     revision_date,effective_date_form,status,created_by)
  VALUES ('[DRY RUN] Annual - Area Coordinators I-III',
    'Operations role targets and documented branch service.',
    'Operations',11,1004,'ANNUAL-CORE','1','Annual',1,
    '2026-01-01','2026-12-31',80,20,'OP-AREA-A-DRYRUN-2026',
    '2026-11-17','2026-11-17','Active',v_creator);
  SET v_template=LAST_INSERT_ID();
  INSERT INTO evaluation_template_positions (template_id,job_title_id)
  SELECT v_template,job_title_id FROM job_titles WHERE department_id=11 AND job_title_id IN (1004,1005,1006) AND is_active=1;
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  VALUES
    (v_template,'KRA','Area work plan delivery','Complete and document area work plan delivery.','Review assigned outputs and records.',20,'Scale_1_4',1),
    (v_template,'KRA','Branch visit completion','Complete and document branch visit completion.','Review assigned outputs and records.',15,'Scale_1_4',2),
    (v_template,'KRA','Focal person coaching','Complete and document focal person coaching.','Review assigned outputs and records.',15,'Scale_1_4',3),
    (v_template,'KRA','Operational control checks','Complete and document operational control checks.','Review assigned outputs and records.',15,'Scale_1_4',4),
    (v_template,'KRA','Issue and incident follow-up','Complete and document issue and incident follow-up.','Review assigned outputs and records.',10,'Scale_1_4',5),
    (v_template,'KRA','Service standard monitoring','Complete and document service standard monitoring.','Review assigned outputs and records.',10,'Scale_1_4',6),
    (v_template,'KRA','Resource coordination','Complete and document resource coordination.','Review assigned outputs and records.',10,'Scale_1_4',7),
    (v_template,'KRA','Area reporting','Complete and document area reporting.','Review assigned outputs and records.',5,'Scale_1_4',8),
    (v_template,'Behavior','Positive Attitude',NULL,'Displays positive attitude at work.',0,'Scale_1_4',1),
    (v_template,'Behavior','Respect',NULL,'Shows respect to all people in the organization.',0,'Scale_1_4',2),
    (v_template,'Behavior','Accountability',NULL,'Takes responsibility for assigned work.',0,'Scale_1_4',3),
    (v_template,'Behavior','Commitment',NULL,'Demonstrates commitment to the job.',0,'Scale_1_4',4),
    (v_template,'Behavior','Teamwork',NULL,'Works cooperatively with others.',0,'Scale_1_4',5),
    (v_template,'Behavior','Integrity',NULL,'Acts honestly and ethically.',0,'Scale_1_4',6),
    (v_template,'Behavior','Continuous Improvement',NULL,'Works consistently to improve.',0,'Scale_1_4',7),
    (v_template,'Behavior','Excellent Client Experience',NULL,'Serves internal and external clients well.',0,'Scale_1_4',8);
  SET v_count=v_count+1;
  IF (SELECT COUNT(*) FROM evaluation_template_positions WHERE template_id=v_template)<>3
     OR (SELECT COUNT(*) FROM evaluation_criteria WHERE template_id=v_template AND section='KRA')<>8
     OR (SELECT COALESCE(SUM(weight),0) FROM evaluation_criteria WHERE template_id=v_template AND section='KRA')<>100
     OR (SELECT COUNT(*) FROM evaluation_criteria WHERE template_id=v_template AND section='Behavior')<>8 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Operations template validation failed';
  END IF;

  -- Focal Persons I-V.
  SELECT MIN(u.user_id) INTO v_creator FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='MO26-OP-H' AND e.is_active=1 AND e.deleted_at IS NULL
    AND u.role='Employee' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0;

  INSERT INTO evaluation_templates
    (template_name,description,target_department,target_department_id,target_job_title_id,
     behavior_framework_code,behavior_framework_version,evaluation_type,individual_workflow,
     assessment_from,assessment_to,kra_weight,behavior_weight,form_code,
     revision_date,effective_date_form,status,created_by)
  VALUES ('[DRY RUN] Annual - Focal Persons I-V',
    'Operations role targets and documented branch service.',
    'Operations',11,1007,'ANNUAL-CORE','1','Annual',1,
    '2026-01-01','2026-12-31',80,20,'OP-FOCAL-A-DRYRUN-2026',
    '2026-11-17','2026-11-17','Active',v_creator);
  SET v_template=LAST_INSERT_ID();
  INSERT INTO evaluation_template_positions (template_id,job_title_id)
  SELECT v_template,job_title_id FROM job_titles WHERE department_id=11 AND job_title_id IN (1007,1008,1009,1010,1011) AND is_active=1;
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  VALUES
    (v_template,'KRA','Branch work plan delivery','Complete and document branch work plan delivery.','Review assigned outputs and records.',20,'Scale_1_4',1),
    (v_template,'KRA','Daily operations oversight','Complete and document daily operations oversight.','Review assigned outputs and records.',15,'Scale_1_4',2),
    (v_template,'KRA','Transaction control checks','Complete and document transaction control checks.','Review assigned outputs and records.',15,'Scale_1_4',3),
    (v_template,'KRA','Branch staff guidance','Complete and document branch staff guidance.','Review assigned outputs and records.',15,'Scale_1_4',4),
    (v_template,'KRA','Customer concern handling','Complete and document customer concern handling.','Review assigned outputs and records.',10,'Scale_1_4',5),
    (v_template,'KRA','Cash and inventory coordination','Complete and document cash and inventory coordination.','Review assigned outputs and records.',10,'Scale_1_4',6),
    (v_template,'KRA','Incident escalation','Complete and document incident escalation.','Review assigned outputs and records.',10,'Scale_1_4',7),
    (v_template,'KRA','Branch reporting','Complete and document branch reporting.','Review assigned outputs and records.',5,'Scale_1_4',8),
    (v_template,'Behavior','Positive Attitude',NULL,'Displays positive attitude at work.',0,'Scale_1_4',1),
    (v_template,'Behavior','Respect',NULL,'Shows respect to all people in the organization.',0,'Scale_1_4',2),
    (v_template,'Behavior','Accountability',NULL,'Takes responsibility for assigned work.',0,'Scale_1_4',3),
    (v_template,'Behavior','Commitment',NULL,'Demonstrates commitment to the job.',0,'Scale_1_4',4),
    (v_template,'Behavior','Teamwork',NULL,'Works cooperatively with others.',0,'Scale_1_4',5),
    (v_template,'Behavior','Integrity',NULL,'Acts honestly and ethically.',0,'Scale_1_4',6),
    (v_template,'Behavior','Continuous Improvement',NULL,'Works consistently to improve.',0,'Scale_1_4',7),
    (v_template,'Behavior','Excellent Client Experience',NULL,'Serves internal and external clients well.',0,'Scale_1_4',8);
  SET v_source=v_template;
  SET v_count=v_count+1;
  IF (SELECT COUNT(*) FROM evaluation_template_positions WHERE template_id=v_template)<>5
     OR (SELECT COUNT(*) FROM evaluation_criteria WHERE template_id=v_template AND section='KRA')<>8
     OR (SELECT COALESCE(SUM(weight),0) FROM evaluation_criteria WHERE template_id=v_template AND section='KRA')<>100
     OR (SELECT COUNT(*) FROM evaluation_criteria WHERE template_id=v_template AND section='Behavior')<>8 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Operations template validation failed';
  END IF;

  -- Branch Staff I-V.
  SELECT MIN(u.user_id) INTO v_creator FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='MO26-OP-H' AND e.is_active=1 AND e.deleted_at IS NULL
    AND u.role='Employee' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0;

  INSERT INTO evaluation_templates
    (template_name,description,target_department,target_department_id,target_job_title_id,
     behavior_framework_code,behavior_framework_version,evaluation_type,individual_workflow,
     assessment_from,assessment_to,kra_weight,behavior_weight,form_code,
     revision_date,effective_date_form,status,created_by)
  VALUES ('[DRY RUN] Annual - Branch Staff I-V',
    'Operations role targets and documented branch service.',
    'Operations',11,1012,'ANNUAL-CORE','1','Annual',1,
    '2026-01-01','2026-12-31',80,20,'OP-STAFF-A-DRYRUN-2026',
    '2026-11-17','2026-11-17','Active',v_creator);
  SET v_template=LAST_INSERT_ID();
  INSERT INTO evaluation_template_positions (template_id,job_title_id)
  SELECT v_template,job_title_id FROM job_titles WHERE department_id=11 AND job_title_id IN (1012,1013,1014,1015,1016) AND is_active=1;
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  VALUES
    (v_template,'KRA','Assigned branch work delivery','Complete and document assigned branch work delivery.','Review assigned outputs and records.',20,'Scale_1_4',1),
    (v_template,'KRA','Transaction accuracy','Complete and document transaction accuracy.','Review assigned outputs and records.',15,'Scale_1_4',2),
    (v_template,'KRA','Customer service','Complete and document customer service.','Review assigned outputs and records.',15,'Scale_1_4',3),
    (v_template,'KRA','Cash and item handling','Complete and document cash and item handling.','Review assigned outputs and records.',15,'Scale_1_4',4),
    (v_template,'KRA','Record completeness','Complete and document record completeness.','Review assigned outputs and records.',10,'Scale_1_4',5),
    (v_template,'KRA','Policy compliance','Complete and document policy compliance.','Review assigned outputs and records.',10,'Scale_1_4',6),
    (v_template,'KRA','Team coordination','Complete and document team coordination.','Review assigned outputs and records.',10,'Scale_1_4',7),
    (v_template,'KRA','Issue escalation','Complete and document issue escalation.','Review assigned outputs and records.',5,'Scale_1_4',8),
    (v_template,'Behavior','Positive Attitude',NULL,'Displays positive attitude at work.',0,'Scale_1_4',1),
    (v_template,'Behavior','Respect',NULL,'Shows respect to all people in the organization.',0,'Scale_1_4',2),
    (v_template,'Behavior','Accountability',NULL,'Takes responsibility for assigned work.',0,'Scale_1_4',3),
    (v_template,'Behavior','Commitment',NULL,'Demonstrates commitment to the job.',0,'Scale_1_4',4),
    (v_template,'Behavior','Teamwork',NULL,'Works cooperatively with others.',0,'Scale_1_4',5),
    (v_template,'Behavior','Integrity',NULL,'Acts honestly and ethically.',0,'Scale_1_4',6),
    (v_template,'Behavior','Continuous Improvement',NULL,'Works consistently to improve.',0,'Scale_1_4',7),
    (v_template,'Behavior','Excellent Client Experience',NULL,'Serves internal and external clients well.',0,'Scale_1_4',8);
  SET v_count=v_count+1;
  IF (SELECT COUNT(*) FROM evaluation_template_positions WHERE template_id=v_template)<>5
     OR (SELECT COUNT(*) FROM evaluation_criteria WHERE template_id=v_template AND section='KRA')<>8
     OR (SELECT COALESCE(SUM(weight),0) FROM evaluation_criteria WHERE template_id=v_template AND section='KRA')<>100
     OR (SELECT COUNT(*) FROM evaluation_criteria WHERE template_id=v_template AND section='Behavior')<>8 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Operations template validation failed';
  END IF;

  -- Focal Persons I-V after probation.
  SELECT MIN(u.user_id) INTO v_creator FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='MO26-OP-H' AND e.is_active=1 AND e.deleted_at IS NULL
    AND u.role='Employee' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0;

  INSERT INTO evaluation_templates
    (template_name,description,target_department,target_department_id,target_job_title_id,
     behavior_framework_code,behavior_framework_version,evaluation_type,individual_workflow,
     assessment_from,assessment_to,kra_weight,behavior_weight,form_code,
     revision_date,effective_date_form,status,created_by)
  VALUES ('[DRY RUN] Annual - Focal Persons I-V - Post-Probation 2027',
    'Operations role targets and documented branch service.',
    'Operations',11,1007,'ANNUAL-CORE','1','Annual',1,
    '2027-01-01','2027-12-31',80,20,'OP-FOCAL-A-DRYRUN-2027',
    '2026-11-17','2027-01-01','Active',v_creator);
  SET v_template=LAST_INSERT_ID();
  INSERT INTO evaluation_template_positions (template_id,job_title_id)
  SELECT v_template,job_title_id FROM job_titles WHERE department_id=11 AND job_title_id IN (1007,1008,1009,1010,1011) AND is_active=1;
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  SELECT v_template,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order
  FROM evaluation_criteria WHERE template_id=v_source;
  SET v_count=v_count+1;
  IF (SELECT COUNT(*) FROM evaluation_template_positions WHERE template_id=v_template)<>5
     OR (SELECT COUNT(*) FROM evaluation_criteria WHERE template_id=v_template AND section='KRA')<>8
     OR (SELECT COALESCE(SUM(weight),0) FROM evaluation_criteria WHERE template_id=v_template AND section='KRA')<>100
     OR (SELECT COUNT(*) FROM evaluation_criteria WHERE template_id=v_template AND section='Behavior')<>8 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Operations template validation failed';
  END IF;
  IF v_count<>6 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Operations template count failed'; END IF;

  -- BEGIN SEEDED OWNERS
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'OP-VP-A-DRYRUN-2026',employee_id,'President and CEO' FROM employees WHERE employee_code='CEO-001'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'OP-REG-A-DRYRUN-2026',employee_id,'Employee' FROM employees WHERE employee_code='MO26-OP-V'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'OP-AREA-A-DRYRUN-2026',employee_id,'Employee' FROM employees WHERE employee_code='MO26-OP-M'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'OP-FOCAL-A-DRYRUN-2026',employee_id,'Employee' FROM employees WHERE employee_code='MO26-OP-H'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'OP-STAFF-A-DRYRUN-2026',employee_id,'Employee' FROM employees WHERE employee_code='MO26-OP-H'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'OP-FOCAL-A-DRYRUN-2027',employee_id,'Employee' FROM employees WHERE employee_code='MO26-OP-H'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  UPDATE evaluation_templates t JOIN evaluation_seed_template_owners o ON o.form_code=t.form_code
  SET t.created_by=(SELECT MIN(u.user_id) FROM users u WHERE u.employee_id=o.employee_id
    AND (u.role=o.preferred_role OR (o.preferred_role='Employee' AND u.role IN ('HR Manager','HR Supervisor','HR Staff')))
    AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0)
  WHERE t.created_by IS NULL;
  -- END SEEDED OWNERS
  COMMIT;
  SELECT 'Operations Annual forms imported' AS result,6 AS forms,16 AS covered_positions;
END //
DELIMITER ;
CALL seed_operations_annual_dry_run();
DROP PROCEDURE seed_operations_annual_dry_run;
