-- PURCHASING QUARTERLY DRY RUN
-- Base: 2026-11-15 to 2027-02-14; both Purchasing positions.
-- Post-probation Staff: 2027-04-15 to 2027-07-14.
-- Templates only; no ratings, submissions, or approvals.
USE raquel_hris;
DROP PROCEDURE IF EXISTS seed_purchasing_quarterly_dry_run;
DELIMITER //
CREATE PROCEDURE seed_purchasing_quarterly_dry_run()
BEGIN
  DECLARE v_creator INT;
  DECLARE v_template INT;
  DECLARE v_staff_base INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  IF EXISTS (SELECT 1 FROM evaluation_templates WHERE form_code IN ('PU-SUP-Q-DRYRUN-20261115','PU-STAFF-Q-DRYRUN-20261115','PU-STAFF-Q-DRYRUN-20270415')) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Purchasing Quarterly form already exists; do not import twice';
  END IF;
  IF (SELECT COUNT(*) FROM job_titles WHERE department_id=12 AND job_title_id IN (1200,1202) AND is_active=1)<>2
     OR NOT EXISTS (SELECT 1 FROM job_titles WHERE job_title_id=1200 AND reports_to=601 AND is_active=1)
     OR NOT EXISTS (SELECT 1 FROM job_titles WHERE job_title_id=1202 AND reports_to=1200 AND is_active=1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Expected Purchasing positions and reporting lines';
  END IF;
  START TRANSACTION;

  -- Procurement Supervisor I.
  SELECT MIN(u.user_id) INTO v_creator FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='MO26-GS-M' AND e.employment_status='Regular' AND e.is_active=1 AND e.deleted_at IS NULL
    AND u.role='Employee' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0;
  IF v_creator IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Required active reviewer account missing'; END IF;
  INSERT INTO evaluation_templates
    (template_name,description,target_department,target_department_id,target_job_title_id,
     behavior_framework_code,behavior_framework_version,evaluation_type,individual_workflow,
     assessment_from,assessment_to,kra_weight,behavior_weight,form_code,
     revision_date,effective_date_form,status,created_by)
  VALUES ('[DRY RUN] Quarterly - Procurement Supervisor I',
    'Procurement targets, controls, and documented purchasing service.',
    'Purchasing',12,1200,'QUARTERLY-CORE','1','Quarterly',1,
    '2026-11-15','2027-02-14',80,20,'PU-SUP-Q-DRYRUN-20261115',
    '2026-11-17','2026-11-17','Active',v_creator);
  SET v_template=LAST_INSERT_ID();
  INSERT INTO evaluation_template_positions (template_id,job_title_id)
  VALUES (v_template,1200);
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  VALUES
    (v_template,'KRA','Procurement plan delivery','Complete and document procurement plan delivery.','Review assigned outputs and records.',20,'Scale_1_4',1),
    (v_template,'KRA','Purchase request review','Complete and document purchase request review.','Review assigned outputs and records.',15,'Scale_1_4',2),
    (v_template,'KRA','Supplier selection controls','Complete and document supplier selection controls.','Review assigned outputs and records.',15,'Scale_1_4',3),
    (v_template,'KRA','Purchase order approval accuracy','Complete and document purchase order approval accuracy.','Review assigned outputs and records.',15,'Scale_1_4',4),
    (v_template,'KRA','Budget and policy compliance','Complete and document budget and policy compliance.','Review assigned outputs and records.',10,'Scale_1_4',5),
    (v_template,'KRA','Supplier performance monitoring','Complete and document supplier performance monitoring.','Review assigned outputs and records.',10,'Scale_1_4',6),
    (v_template,'KRA','Team guidance and workload','Complete and document team guidance and workload.','Review assigned outputs and records.',10,'Scale_1_4',7),
    (v_template,'KRA','Procurement reporting','Complete and document procurement reporting.','Review assigned outputs and records.',5,'Scale_1_4',8),
    (v_template,'Behavior','Positive Attitude',NULL,'Displays positive attitude at work.',0,'Scale_1_4',1),
    (v_template,'Behavior','Respect',NULL,'Shows respect to all people in the organization.',0,'Scale_1_4',2),
    (v_template,'Behavior','Accountability',NULL,'Takes responsibility for assigned work.',0,'Scale_1_4',3),
    (v_template,'Behavior','Commitment',NULL,'Demonstrates commitment to the job.',0,'Scale_1_4',4),
    (v_template,'Behavior','Teamwork',NULL,'Works cooperatively with others.',0,'Scale_1_4',5),
    (v_template,'Behavior','Integrity',NULL,'Acts honestly and ethically.',0,'Scale_1_4',6),
    (v_template,'Behavior','Continuous Improvement',NULL,'Works consistently to improve.',0,'Scale_1_4',7),
    (v_template,'Behavior','Excellent Client Experience',NULL,'Serves internal and external clients well.',0,'Scale_1_4',8);
  IF (SELECT COUNT(*) FROM evaluation_template_positions WHERE template_id=v_template)<>1
     OR (SELECT COUNT(*) FROM evaluation_criteria WHERE template_id=v_template AND section='KRA')<>8
     OR (SELECT COALESCE(SUM(weight),0) FROM evaluation_criteria WHERE template_id=v_template AND section='KRA')<>100
     OR (SELECT COUNT(*) FROM evaluation_criteria WHERE template_id=v_template AND section='Behavior')<>8 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Purchasing template validation failed';
  END IF;

  -- Purchasing Staff I.
  SELECT MIN(u.user_id) INTO v_creator FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='MO26-PU-H' AND e.employment_status='Regular' AND e.is_active=1 AND e.deleted_at IS NULL
    AND u.role='Employee' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0;
  IF v_creator IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Required active reviewer account missing'; END IF;
  INSERT INTO evaluation_templates
    (template_name,description,target_department,target_department_id,target_job_title_id,
     behavior_framework_code,behavior_framework_version,evaluation_type,individual_workflow,
     assessment_from,assessment_to,kra_weight,behavior_weight,form_code,
     revision_date,effective_date_form,status,created_by)
  VALUES ('[DRY RUN] Quarterly - Purchasing Staff I',
    'Procurement targets, controls, and documented purchasing service.',
    'Purchasing',12,1202,'QUARTERLY-CORE','1','Quarterly',1,
    '2026-11-15','2027-02-14',80,20,'PU-STAFF-Q-DRYRUN-20261115',
    '2026-11-17','2026-11-17','Active',v_creator);
  SET v_template=LAST_INSERT_ID();
  INSERT INTO evaluation_template_positions (template_id,job_title_id)
  VALUES (v_template,1202);
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  VALUES
    (v_template,'KRA','Purchase request processing','Complete and document purchase request processing.','Review assigned outputs and records.',20,'Scale_1_4',1),
    (v_template,'KRA','Quotation completeness','Complete and document quotation completeness.','Review assigned outputs and records.',15,'Scale_1_4',2),
    (v_template,'KRA','Supplier coordination','Complete and document supplier coordination.','Review assigned outputs and records.',15,'Scale_1_4',3),
    (v_template,'KRA','Purchase order accuracy','Complete and document purchase order accuracy.','Review assigned outputs and records.',15,'Scale_1_4',4),
    (v_template,'KRA','Delivery follow-up','Complete and document delivery follow-up.','Review assigned outputs and records.',10,'Scale_1_4',5),
    (v_template,'KRA','Procurement record keeping','Complete and document procurement record keeping.','Review assigned outputs and records.',10,'Scale_1_4',6),
    (v_template,'KRA','Policy compliance','Complete and document policy compliance.','Review assigned outputs and records.',10,'Scale_1_4',7),
    (v_template,'KRA','Issue escalation','Complete and document issue escalation.','Review assigned outputs and records.',5,'Scale_1_4',8),
    (v_template,'Behavior','Positive Attitude',NULL,'Displays positive attitude at work.',0,'Scale_1_4',1),
    (v_template,'Behavior','Respect',NULL,'Shows respect to all people in the organization.',0,'Scale_1_4',2),
    (v_template,'Behavior','Accountability',NULL,'Takes responsibility for assigned work.',0,'Scale_1_4',3),
    (v_template,'Behavior','Commitment',NULL,'Demonstrates commitment to the job.',0,'Scale_1_4',4),
    (v_template,'Behavior','Teamwork',NULL,'Works cooperatively with others.',0,'Scale_1_4',5),
    (v_template,'Behavior','Integrity',NULL,'Acts honestly and ethically.',0,'Scale_1_4',6),
    (v_template,'Behavior','Continuous Improvement',NULL,'Works consistently to improve.',0,'Scale_1_4',7),
    (v_template,'Behavior','Excellent Client Experience',NULL,'Serves internal and external clients well.',0,'Scale_1_4',8);
  SET v_staff_base=v_template;
  IF (SELECT COUNT(*) FROM evaluation_template_positions WHERE template_id=v_template)<>1
     OR (SELECT COUNT(*) FROM evaluation_criteria WHERE template_id=v_template AND section='KRA')<>8
     OR (SELECT COALESCE(SUM(weight),0) FROM evaluation_criteria WHERE template_id=v_template AND section='KRA')<>100
     OR (SELECT COUNT(*) FROM evaluation_criteria WHERE template_id=v_template AND section='Behavior')<>8 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Purchasing template validation failed';
  END IF;

  -- Purchasing Staff I after probation.
  SELECT MIN(u.user_id) INTO v_creator FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='MO26-PU-H' AND e.employment_status='Regular' AND e.is_active=1 AND e.deleted_at IS NULL
    AND u.role='Employee' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0;
  IF v_creator IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Required active reviewer account missing'; END IF;
  INSERT INTO evaluation_templates
    (template_name,description,target_department,target_department_id,target_job_title_id,
     behavior_framework_code,behavior_framework_version,evaluation_type,individual_workflow,
     assessment_from,assessment_to,kra_weight,behavior_weight,form_code,
     revision_date,effective_date_form,status,created_by)
  VALUES ('[DRY RUN] Quarterly - Purchasing Staff I - Post-Probation 2027',
    'Procurement targets, controls, and documented purchasing service.',
    'Purchasing',12,1202,'QUARTERLY-CORE','1','Quarterly',1,
    '2027-04-15','2027-07-14',80,20,'PU-STAFF-Q-DRYRUN-20270415',
    '2026-11-17','2027-04-15','Active',v_creator);
  SET v_template=LAST_INSERT_ID();
  INSERT INTO evaluation_template_positions (template_id,job_title_id)
  VALUES (v_template,1202);
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  SELECT v_template,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order
  FROM evaluation_criteria WHERE template_id=v_staff_base;
  IF (SELECT COUNT(*) FROM evaluation_template_positions WHERE template_id=v_template)<>1
     OR (SELECT COUNT(*) FROM evaluation_criteria WHERE template_id=v_template AND section='KRA')<>8
     OR (SELECT COALESCE(SUM(weight),0) FROM evaluation_criteria WHERE template_id=v_template AND section='KRA')<>100
     OR (SELECT COUNT(*) FROM evaluation_criteria WHERE template_id=v_template AND section='Behavior')<>8 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Purchasing template validation failed';
  END IF;
  COMMIT;
  SELECT 'Purchasing Quarterly forms imported' AS result,3 AS forms,2 AS covered_positions;
END //
DELIMITER ;
CALL seed_purchasing_quarterly_dry_run();
DROP PROCEDURE seed_purchasing_quarterly_dry_run;
