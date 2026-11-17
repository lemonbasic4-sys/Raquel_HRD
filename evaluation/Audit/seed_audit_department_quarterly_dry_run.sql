-- AUDIT QUARTERLY DRY RUN
-- Period: 2026-11-15 to 2027-02-14 for all 11 Audit positions.
-- A second Auditor I-V cycle runs 2027-04-15 to 2027-07-14 so employees
-- who complete probation in April 2027 can progress to Regular evaluations.
-- Templates only: no scores, submissions, approvals, or employee assignments.
-- Regular employees with active portal accounts can start self-rating.
USE raquel_hris;
DROP PROCEDURE IF EXISTS seed_audit_department_quarterly_dry_run;
DELIMITER //
CREATE PROCEDURE seed_audit_department_quarterly_dry_run()
BEGIN
  DECLARE v_ceo INT;
  DECLARE v_manager1 INT;
  DECLARE v_manager2 INT;
  DECLARE v_supervisor INT;
  DECLARE v_lead_form INT;
  DECLARE v_manager_form INT;
  DECLARE v_supervisor_form INT;
  DECLARE v_auditor_form INT;
  DECLARE v_auditor_2027_form INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  IF EXISTS (SELECT 1 FROM evaluation_templates WHERE form_code IN
    ('AUD-LEAD-Q-DRYRUN-20261115','AUD-MGR-Q-DRYRUN-20261115',
     'AUD-SUP-Q-DRYRUN-20261115','AUD-RF-Q-DRYRUN-20261115',
     'AUD-RF-Q-DRYRUN-20270415')) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Audit Quarterly form already exists; do not import twice';
  END IF;
  IF (SELECT COUNT(*) FROM job_titles WHERE department_id=2 AND job_title_id BETWEEN 200 AND 210 AND is_active=1)<>11
     OR NOT EXISTS (SELECT 1 FROM job_titles WHERE job_title_id=200 AND reports_to=1100)
     OR (SELECT COUNT(*) FROM job_titles WHERE department_id=2 AND job_title_id BETWEEN 201 AND 202 AND reports_to=200 AND is_active=1)<>2
     OR (SELECT COUNT(*) FROM job_titles WHERE department_id=2 AND job_title_id BETWEEN 203 AND 205 AND reports_to=201 AND is_active=1)<>3
     OR (SELECT COUNT(*) FROM job_titles WHERE department_id=2 AND job_title_id BETWEEN 206 AND 210 AND reports_to=203 AND is_active=1)<>5 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Expected all 11 active Audit positions and reporting lines';
  END IF;

  -- Creators follow the Audit reporting chain.
  SELECT MIN(u.user_id) INTO v_ceo FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='CEO-001' AND e.job_title_id=1100 AND e.is_active=1 AND e.deleted_at IS NULL
    AND u.role='President and CEO' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0;
  SELECT MIN(u.user_id) INTO v_manager1 FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='MO26-AU-M1' AND e.job_title_id=200 AND e.department_id=2 AND e.employment_status='Regular'
    AND e.is_active=1 AND e.deleted_at IS NULL AND u.role='Employee' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0;
  SELECT MIN(u.user_id) INTO v_manager2 FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='MO26-AU-M2' AND e.job_title_id=201 AND e.department_id=2 AND e.employment_status='Regular'
    AND e.is_active=1 AND e.deleted_at IS NULL AND u.role='Employee' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0;
  SELECT MIN(u.user_id) INTO v_supervisor FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='MO26-AU-H' AND e.job_title_id=203 AND e.department_id=2 AND e.employment_status='Regular'
    AND e.is_active=1 AND e.deleted_at IS NULL AND u.role='Employee' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0;


  START TRANSACTION;
  -- Audit Manager I; Audit Manager II-III; Supervisors I-III; Auditors I-V.
  INSERT INTO evaluation_templates
    (template_name,description,target_department,target_department_id,target_job_title_id,
     behavior_framework_code,behavior_framework_version,evaluation_type,individual_workflow,
     assessment_from,assessment_to,kra_weight,behavior_weight,form_code,
     revision_date,effective_date_form,status,created_by)
  VALUES
    ('[DRY RUN] Quarterly - Audit Manager I - Main Office','Audit program leadership and independence.',
     'Audit',2,200,'QUARTERLY-CORE','1','Quarterly',1,'2026-11-15','2027-02-14',80,20,
     'AUD-LEAD-Q-DRYRUN-20261115','2026-11-17','2026-11-17','Active',v_ceo),
    ('[DRY RUN] Quarterly - Audit Managers II-III - Main Office','Audit engagement management and quality review.',
     'Audit',2,201,'QUARTERLY-CORE','1','Quarterly',1,'2026-11-15','2027-02-14',80,20,
     'AUD-MGR-Q-DRYRUN-20261115','2026-11-17','2026-11-17','Active',v_manager1),
    ('[DRY RUN] Quarterly - Audit Supervisors - Main Office','Audit fieldwork supervision and evidence quality.',
     'Audit',2,203,'QUARTERLY-CORE','1','Quarterly',1,'2026-11-15','2027-02-14',80,20,
     'AUD-SUP-Q-DRYRUN-20261115','2026-11-17','2026-11-17','Active',v_manager2),
    ('[DRY RUN] Quarterly - Auditors - Main Office','Audit testing, evidence, and workpaper delivery.',
     'Audit',2,206,'QUARTERLY-CORE','1','Quarterly',1,'2026-11-15','2027-02-14',80,20,
     'AUD-RF-Q-DRYRUN-20261115','2026-11-17','2026-11-17','Active',v_supervisor);

  SELECT template_id INTO v_lead_form FROM evaluation_templates WHERE form_code='AUD-LEAD-Q-DRYRUN-20261115';
  SELECT template_id INTO v_manager_form FROM evaluation_templates WHERE form_code='AUD-MGR-Q-DRYRUN-20261115';
  SELECT template_id INTO v_supervisor_form FROM evaluation_templates WHERE form_code='AUD-SUP-Q-DRYRUN-20261115';
  SELECT template_id INTO v_auditor_form FROM evaluation_templates WHERE form_code='AUD-RF-Q-DRYRUN-20261115';

  INSERT INTO evaluation_template_positions (template_id,job_title_id)
  SELECT CASE WHEN job_title_id=200 THEN v_lead_form WHEN job_title_id BETWEEN 201 AND 202 THEN v_manager_form
              WHEN job_title_id BETWEEN 203 AND 205 THEN v_supervisor_form ELSE v_auditor_form END,job_title_id
  FROM job_titles WHERE department_id=2 AND job_title_id BETWEEN 200 AND 210 AND is_active=1;

  -- Each KRA group totals 100%, then contributes 80% to the overall score.
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  VALUES
    -- Audit Manager I: department program and independence.
    (v_lead_form,'KRA','Risk-based audit plan','Set a documented plan that prioritizes material risks and approved coverage.','Approved plan and coverage milestones.',20,'Scale_1_4',1),
    (v_lead_form,'KRA','Audit independence','Protect audit objectivity and document conflicts or scope limits.','Independence declarations and resolved constraints.',15,'Scale_1_4',2),
    (v_lead_form,'KRA','Engagement delivery','Monitor audits against plan and explain material delays.','Completed engagements versus approved plan.',15,'Scale_1_4',3),
    (v_lead_form,'KRA','Report quality','Ensure findings and recommendations have sufficient evidence and clear risk impact.','Reviewed final reports with supported findings.',15,'Scale_1_4',4),
    (v_lead_form,'KRA','Issue follow-up','Track management actions and escalate overdue high-risk findings.','Issue register and escalation records.',10,'Scale_1_4',5),
    (v_lead_form,'KRA','Audit team leadership','Review manager performance and document coaching and development.','Manager reviews and follow-up actions.',10,'Scale_1_4',6),
    (v_lead_form,'KRA','Stakeholder reporting','Provide timely audit results and unresolved risks to executive oversight.','Executive reports and decision requests.',10,'Scale_1_4',7),
    (v_lead_form,'KRA','Methodology compliance','Maintain audit standards, quality checks, and workpaper retention.','Quality review and methodology records.',5,'Scale_1_4',8),
    -- Audit Managers II-III: engagement ownership.
    (v_manager_form,'KRA','Engagement planning','Define audit objectives, scope, tests, and schedule from assessed risks.','Approved engagement plan and test program.',20,'Scale_1_4',1),
    (v_manager_form,'KRA','Fieldwork delivery','Complete assigned audit engagements within approved milestones.','Fieldwork progress and variance notes.',15,'Scale_1_4',2),
    (v_manager_form,'KRA','Evidence sufficiency','Review testing evidence for relevance, completeness, and traceability.','Reviewed workpapers and evidence corrections.',15,'Scale_1_4',3),
    (v_manager_form,'KRA','Finding quality','Validate findings, root causes, risk ratings, and practical recommendations.','Supported findings and action owners.',15,'Scale_1_4',4),
    (v_manager_form,'KRA','Supervisor coaching','Set review expectations and document feedback to supervisors.','Review notes and coaching follow-up.',10,'Scale_1_4',5),
    (v_manager_form,'KRA','Issue follow-up','Monitor assigned corrective actions and escalate overdue risks.','Updated issue register and escalations.',10,'Scale_1_4',6),
    (v_manager_form,'KRA','Audit coordination','Coordinate scope and evidence requests without compromising independence.','Documented auditee requests and handoffs.',10,'Scale_1_4',7),
    (v_manager_form,'KRA','Manager reporting','Submit timely status and draft reports to Audit Manager I.','On-time status and report submissions.',5,'Scale_1_4',8),
    -- Supervisors: fieldwork supervision.
    (v_supervisor_form,'KRA','Fieldwork assignment','Assign tests with clear objectives, owners, and deadlines.','Completed fieldwork assignments.',20,'Scale_1_4',1),
    (v_supervisor_form,'KRA','Workpaper review','Review auditor workpapers for accuracy, completeness, and audit trail.','Signed review notes and corrections.',15,'Scale_1_4',2),
    (v_supervisor_form,'KRA','Control testing quality','Check that samples, procedures, and conclusions match the approved program.','Supported test results.',15,'Scale_1_4',3),
    (v_supervisor_form,'KRA','Finding validation','Verify exceptions and discuss factual accuracy before escalation.','Validated exception records.',15,'Scale_1_4',4),
    (v_supervisor_form,'KRA','Auditor coaching','Give specific feedback and follow up on recurring workpaper issues.','Coaching and improvement records.',10,'Scale_1_4',5),
    (v_supervisor_form,'KRA','Risk escalation','Escalate material control failures and scope limitations promptly.','Timely documented escalations.',10,'Scale_1_4',6),
    (v_supervisor_form,'KRA','Auditee coordination','Track evidence requests and resolve access blockers professionally.','Request log and resolution records.',10,'Scale_1_4',7),
    (v_supervisor_form,'KRA','Status reporting','Report test progress and open issues to Audit Managers.','Accurate scheduled status reports.',5,'Scale_1_4',8),
    -- Auditors: evidence-based testing.
    (v_auditor_form,'KRA','Assigned audit tests','Complete assigned procedures according to the approved audit program.','Completed test steps and conclusions.',20,'Scale_1_4',1),
    (v_auditor_form,'KRA','Evidence collection','Gather relevant, sufficient, and traceable supporting records.','Indexed evidence linked to test results.',15,'Scale_1_4',2),
    (v_auditor_form,'KRA','Workpaper accuracy','Prepare clear workpapers with sources, samples, and calculations.','Reviewed workpapers with minimal corrections.',15,'Scale_1_4',3),
    (v_auditor_form,'KRA','Exception documentation','Record factual exceptions and discuss them with the supervisor.','Supported exception notes.',15,'Scale_1_4',4),
    (v_auditor_form,'KRA','Deadline management','Complete tests and evidence requests by agreed deadlines.','On-time assigned outputs.',10,'Scale_1_4',5),
    (v_auditor_form,'KRA','Confidentiality','Protect sensitive records and follow access and retention rules.','Documented handling of audit information.',10,'Scale_1_4',6),
    (v_auditor_form,'KRA','Auditee communication','Make clear evidence requests and report blockers promptly.','Request and follow-up logs.',10,'Scale_1_4',7),
    (v_auditor_form,'KRA','Feedback application','Correct review notes and apply audit methodology feedback.','Closed review notes and improved workpapers.',5,'Scale_1_4',8);

  -- Same eight Core Behaviors in each form (20% overall).
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  VALUES
    (v_lead_form,'Behavior','Positive Attitude',NULL,'Displays positive attitude at work.',0,'Scale_1_4',1),
    (v_lead_form,'Behavior','Respect',NULL,'Shows respect to all people in the organization.',0,'Scale_1_4',2),
    (v_lead_form,'Behavior','Accountability',NULL,'Takes responsibility for assigned work.',0,'Scale_1_4',3),
    (v_lead_form,'Behavior','Commitment',NULL,'Demonstrates commitment to the job.',0,'Scale_1_4',4),
    (v_lead_form,'Behavior','Teamwork',NULL,'Works cooperatively with others.',0,'Scale_1_4',5),
    (v_lead_form,'Behavior','Integrity',NULL,'Acts honestly and ethically.',0,'Scale_1_4',6),
    (v_lead_form,'Behavior','Continuous Improvement',NULL,'Works consistently to improve.',0,'Scale_1_4',7),
    (v_lead_form,'Behavior','Excellent Client Experience',NULL,'Serves internal and external clients well.',0,'Scale_1_4',8);
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  SELECT target.template_id,c.section,c.criterion_name,c.description,c.kpi_description,c.weight,c.scoring_method,c.sort_order
  FROM evaluation_criteria c CROSS JOIN
    (SELECT v_manager_form AS template_id UNION ALL SELECT v_supervisor_form UNION ALL SELECT v_auditor_form) target
  WHERE c.template_id=v_lead_form AND c.section='Behavior';

  -- A later Auditor cycle covers the two current probationary employees
  -- after their Final evaluations and regularization. It uses the same role KRAs.
  INSERT INTO evaluation_templates
    (template_name,description,target_department,target_department_id,target_job_title_id,
     behavior_framework_code,behavior_framework_version,evaluation_type,individual_workflow,
     assessment_from,assessment_to,kra_weight,behavior_weight,form_code,
     revision_date,effective_date_form,status,created_by)
  VALUES
    ('[DRY RUN] Quarterly - Auditors - Post-Probation 2027',
     'Regular Auditor I-V review after the probationary Initial and Final stages.',
     'Audit',2,206,'QUARTERLY-CORE','1','Quarterly',1,'2027-04-15','2027-07-14',80,20,
     'AUD-RF-Q-DRYRUN-20270415','2026-11-17','2027-04-15','Active',v_supervisor);
  SET v_auditor_2027_form=LAST_INSERT_ID();
  INSERT INTO evaluation_template_positions (template_id,job_title_id)
  SELECT v_auditor_2027_form,job_title_id FROM job_titles
  WHERE department_id=2 AND job_title_id BETWEEN 206 AND 210 AND is_active=1;
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  SELECT v_auditor_2027_form,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order
  FROM evaluation_criteria WHERE template_id=v_auditor_form;

  IF (SELECT COUNT(*) FROM evaluation_template_positions WHERE template_id IN
      (v_lead_form,v_manager_form,v_supervisor_form,v_auditor_form,v_auditor_2027_form))<>16
     OR EXISTS (SELECT 1 FROM evaluation_templates t WHERE t.template_id IN
       (v_lead_form,v_manager_form,v_supervisor_form,v_auditor_form,v_auditor_2027_form)
       AND ((SELECT COUNT(*) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='KRA')<>8
        OR (SELECT COALESCE(SUM(c.weight),0) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='KRA')<>100
        OR (SELECT COUNT(*) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='Behavior')<>8)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Audit Quarterly position or criteria validation failed';
  END IF;

  -- BEGIN SEEDED OWNERS
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'AUD-LEAD-Q-DRYRUN-20261115',employee_id,'President and CEO' FROM employees WHERE employee_code='CEO-001'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'AUD-MGR-Q-DRYRUN-20261115',employee_id,'Employee' FROM employees WHERE employee_code='MO26-AU-M1'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'AUD-SUP-Q-DRYRUN-20261115',employee_id,'Employee' FROM employees WHERE employee_code='MO26-AU-M2'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'AUD-RF-Q-DRYRUN-20261115',employee_id,'Employee' FROM employees WHERE employee_code='MO26-AU-H'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'AUD-RF-Q-DRYRUN-20270415',employee_id,'Employee' FROM employees WHERE employee_code='MO26-AU-H'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  UPDATE evaluation_templates t JOIN evaluation_seed_template_owners o ON o.form_code=t.form_code
  SET t.created_by=(SELECT MIN(u.user_id) FROM users u WHERE u.employee_id=o.employee_id
    AND (u.role=o.preferred_role OR (o.preferred_role='Employee' AND u.role IN ('HR Manager','HR Supervisor','HR Staff')))
    AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0)
  WHERE t.created_by IS NULL;
  -- END SEEDED OWNERS
  COMMIT;
  SELECT 'Audit Quarterly forms imported' AS result,5 AS forms,11 AS covered_positions;
END //
DELIMITER ;
CALL seed_audit_department_quarterly_dry_run();
DROP PROCEDURE seed_audit_department_quarterly_dry_run;
