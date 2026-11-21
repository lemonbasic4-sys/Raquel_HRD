-- COMPLIANCE ANNUAL DRY RUN
-- Base period: 2026-01-01 to 2026-12-31; covers all six positions.
-- Later Staff I-III period: 2027-01-01 to 2027-12-31 after probation.
-- Templates only: no scores, submissions, approvals, or assignments.
-- Actual period starts on the later of Jan 1 or regularization date.
USE raquel_hris;
DROP PROCEDURE IF EXISTS seed_compliance_annual_dry_run;
DELIMITER //
CREATE PROCEDURE seed_compliance_annual_dry_run()
BEGIN
  DECLARE v_ceo INT;
  DECLARE v_head INT;
  DECLARE v_supervisor INT;
  DECLARE v_head_form INT;
  DECLARE v_supervisor_form INT;
  DECLARE v_staff_form INT;
  DECLARE v_staff_2027_form INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  IF EXISTS (SELECT 1 FROM evaluation_templates WHERE form_code IN
    ('CO-HEAD-A-DRYRUN-2026','CO-SUP-A-DRYRUN-2026',
     'CO-STAFF-A-DRYRUN-2026','CO-STAFF-A-DRYRUN-2027')) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Compliance Annual form already exists; do not import twice';
  END IF;
  IF (SELECT COUNT(*) FROM job_titles WHERE department_id=4 AND job_title_id BETWEEN 400 AND 405 AND is_active=1)<>6
     OR NOT EXISTS (SELECT 1 FROM job_titles WHERE job_title_id=400 AND reports_to=1100 AND is_active=1)
     OR (SELECT COUNT(*) FROM job_titles WHERE department_id=4 AND job_title_id BETWEEN 401 AND 402 AND reports_to=400 AND is_active=1)<>2
     OR (SELECT COUNT(*) FROM job_titles WHERE department_id=4 AND job_title_id BETWEEN 403 AND 405 AND reports_to=401 AND is_active=1)<>3 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Expected all six Compliance positions and reporting lines';
  END IF;

  SELECT MIN(u.user_id) INTO v_ceo FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='CEO-001' AND e.job_title_id=1100 AND e.is_active=1 AND e.deleted_at IS NULL
    AND u.role='President and CEO' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0;
  SELECT MIN(u.user_id) INTO v_head FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='MO26-CO-H' AND e.department_id=4 AND e.job_title_id=400 AND e.employment_status='Regular'
    AND e.is_active=1 AND e.deleted_at IS NULL AND u.role='Employee' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0;
  SELECT MIN(u.user_id) INTO v_supervisor FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='MO26-CO-S' AND e.department_id=4 AND e.job_title_id=401 AND e.employment_status='Regular'
    AND e.is_active=1 AND e.deleted_at IS NULL AND u.role='Employee' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0;


  START TRANSACTION;
  INSERT INTO evaluation_templates
    (template_name,description,target_department,target_department_id,target_job_title_id,
     behavior_framework_code,behavior_framework_version,evaluation_type,individual_workflow,
     assessment_from,assessment_to,kra_weight,behavior_weight,form_code,
     revision_date,effective_date_form,status,created_by)
  VALUES
    ('[DRY RUN] Annual - Compliance Supervisor I - Main Office','Compliance program leadership and risk oversight.',
     'Compliance',4,400,'ANNUAL-CORE','1','Annual',1,'2026-01-01','2026-12-31',80,20,
     'CO-HEAD-A-DRYRUN-2026','2026-11-17','2026-11-17','Active',v_ceo),
    ('[DRY RUN] Annual - Compliance Supervisors II-III - Main Office','Compliance review, issue tracking, and staff guidance.',
     'Compliance',4,401,'ANNUAL-CORE','1','Annual',1,'2026-01-01','2026-12-31',80,20,
     'CO-SUP-A-DRYRUN-2026','2026-11-17','2026-11-17','Active',v_head),
    ('[DRY RUN] Annual - Compliance Staff I-III - Main Office','Policy checks, evidence, issue records, and follow-up.',
     'Compliance',4,403,'ANNUAL-CORE','1','Annual',1,'2026-01-01','2026-12-31',80,20,
     'CO-STAFF-A-DRYRUN-2026','2026-11-17','2026-11-17','Active',v_supervisor);
  SELECT template_id INTO v_head_form FROM evaluation_templates WHERE form_code='CO-HEAD-A-DRYRUN-2026';
  SELECT template_id INTO v_supervisor_form FROM evaluation_templates WHERE form_code='CO-SUP-A-DRYRUN-2026';
  SELECT template_id INTO v_staff_form FROM evaluation_templates WHERE form_code='CO-STAFF-A-DRYRUN-2026';

  INSERT INTO evaluation_template_positions (template_id,job_title_id)
  SELECT CASE WHEN job_title_id=400 THEN v_head_form WHEN job_title_id BETWEEN 401 AND 402 THEN v_supervisor_form ELSE v_staff_form END,job_title_id
  FROM job_titles WHERE department_id=4 AND job_title_id BETWEEN 400 AND 405 AND is_active=1;

  -- Role-specific KRA weights sum to 100%; template applies 80% overall.
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  VALUES
    (v_head_form,'KRA','Compliance plan','Set a risk-based compliance plan with owners, due dates, and required coverage.','Approved plan and progress reviews.',20,'Scale_1_4',1),
    (v_head_form,'KRA','Regulatory change oversight','Track applicable changes and assign policy or process updates.','Change register and approved actions.',15,'Scale_1_4',2),
    (v_head_form,'KRA','Control effectiveness','Review key controls and escalate material gaps to management.','Control review and escalation records.',15,'Scale_1_4',3),
    (v_head_form,'KRA','Issue remediation','Monitor high-risk findings until owners close them with evidence.','Issue register and closure evidence.',15,'Scale_1_4',4),
    (v_head_form,'KRA','Management advice','Provide timely, documented guidance on material compliance decisions.','Advice and decision records.',10,'Scale_1_4',5),
    (v_head_form,'KRA','Team leadership','Review supervisor delivery and document coaching and follow-up.','Supervisor review records.',10,'Scale_1_4',6),
    (v_head_form,'KRA','Compliance reporting','Report open risks, overdue actions, and trends to executive oversight.','Accurate scheduled management reports.',10,'Scale_1_4',7),
    (v_head_form,'KRA','Ethics and confidentiality','Protect sensitive information and escalate conflicts appropriately.','Documented ethical handling.',5,'Scale_1_4',8),
    (v_supervisor_form,'KRA','Review scheduling','Assign compliance checks with clear scope, owners, and deadlines.','Completed review schedule.',20,'Scale_1_4',1),
    (v_supervisor_form,'KRA','Evidence review','Check staff evidence for accuracy, completeness, and traceability.','Signed review notes and corrections.',15,'Scale_1_4',2),
    (v_supervisor_form,'KRA','Policy interpretation','Apply current policies consistently and escalate unclear requirements.','Documented guidance and escalations.',15,'Scale_1_4',3),
    (v_supervisor_form,'KRA','Finding validation','Verify exceptions and distinguish material issues from routine corrections.','Supported finding records.',15,'Scale_1_4',4),
    (v_supervisor_form,'KRA','Staff coaching','Give specific feedback and follow up on recurring gaps.','Coaching and follow-up records.',10,'Scale_1_4',5),
    (v_supervisor_form,'KRA','Remediation follow-up','Track owners and due dates for assigned compliance actions.','Updated action tracker.',10,'Scale_1_4',6),
    (v_supervisor_form,'KRA','Cross-team coordination','Coordinate evidence and corrective actions with affected departments.','Documented requests and handoffs.',10,'Scale_1_4',7),
    (v_supervisor_form,'KRA','Status reporting','Report material findings and delays to the Compliance head on time.','Accurate scheduled status reports.',5,'Scale_1_4',8),
    (v_staff_form,'KRA','Assigned compliance checks','Complete assigned policy and control checks using the approved checklist.','Completed checklists and conclusions.',20,'Scale_1_4',1),
    (v_staff_form,'KRA','Evidence collection','Collect relevant, complete, and traceable supporting records.','Indexed evidence files.',15,'Scale_1_4',2),
    (v_staff_form,'KRA','Record accuracy','Maintain correct dates, owners, references, and results in compliance records.','Accurate review logs.',15,'Scale_1_4',3),
    (v_staff_form,'KRA','Exception documentation','Record factual gaps and escalate material exceptions promptly.','Supported exception reports.',15,'Scale_1_4',4),
    (v_staff_form,'KRA','Action follow-up','Track assigned remediation requests and due dates.','Current follow-up log.',10,'Scale_1_4',5),
    (v_staff_form,'KRA','Deadline management','Submit assigned checks and documentation by agreed deadlines.','Timely completed outputs.',10,'Scale_1_4',6),
    (v_staff_form,'KRA','Confidentiality','Handle restricted employee, customer, and company information correctly.','Compliant information handling.',10,'Scale_1_4',7),
    (v_staff_form,'KRA','Feedback application','Correct review notes and apply coaching in subsequent checks.','Closed review notes.',5,'Scale_1_4',8);

  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  VALUES
    (v_head_form,'Behavior','Positive Attitude',NULL,'Displays positive attitude at work.',0,'Scale_1_4',1),
    (v_head_form,'Behavior','Respect',NULL,'Shows respect to all people in the organization.',0,'Scale_1_4',2),
    (v_head_form,'Behavior','Accountability',NULL,'Takes responsibility for assigned work.',0,'Scale_1_4',3),
    (v_head_form,'Behavior','Commitment',NULL,'Demonstrates commitment to the job.',0,'Scale_1_4',4),
    (v_head_form,'Behavior','Teamwork',NULL,'Works cooperatively with others.',0,'Scale_1_4',5),
    (v_head_form,'Behavior','Integrity',NULL,'Acts honestly and ethically.',0,'Scale_1_4',6),
    (v_head_form,'Behavior','Continuous Improvement',NULL,'Works consistently to improve.',0,'Scale_1_4',7),
    (v_head_form,'Behavior','Excellent Client Experience',NULL,'Serves internal and external clients well.',0,'Scale_1_4',8);
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  SELECT target.template_id,c.section,c.criterion_name,c.description,c.kpi_description,c.weight,c.scoring_method,c.sort_order
  FROM evaluation_criteria c CROSS JOIN (SELECT v_supervisor_form AS template_id UNION ALL SELECT v_staff_form) target
  WHERE c.template_id=v_head_form AND c.section='Behavior';

  -- Additional 2027 Annual form for staff after regularization.
  INSERT INTO evaluation_templates
    (template_name,description,target_department,target_department_id,target_job_title_id,
     behavior_framework_code,behavior_framework_version,evaluation_type,individual_workflow,
     assessment_from,assessment_to,kra_weight,behavior_weight,form_code,
     revision_date,effective_date_form,status,created_by)
  VALUES
    ('[DRY RUN] Annual - Compliance Staff - Post-Probation 2027',
     'Regular Compliance Staff I-III review after probation.',
     'Compliance',4,403,'ANNUAL-CORE','1','Annual',1,'2027-01-01','2027-12-31',80,20,
     'CO-STAFF-A-DRYRUN-2027','2026-11-17','2027-01-01','Active',v_supervisor);
  SET v_staff_2027_form=LAST_INSERT_ID();
  INSERT INTO evaluation_template_positions (template_id,job_title_id)
  SELECT v_staff_2027_form,job_title_id FROM job_titles WHERE department_id=4 AND job_title_id BETWEEN 403 AND 405 AND is_active=1;
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  SELECT v_staff_2027_form,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order
  FROM evaluation_criteria WHERE template_id=v_staff_form;

  IF (SELECT COUNT(*) FROM evaluation_template_positions WHERE template_id IN (v_head_form,v_supervisor_form,v_staff_form,v_staff_2027_form))<>9
     OR EXISTS (SELECT 1 FROM evaluation_templates t WHERE t.template_id IN (v_head_form,v_supervisor_form,v_staff_form,v_staff_2027_form)
       AND ((SELECT COUNT(*) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='KRA')<>8
        OR (SELECT COALESCE(SUM(c.weight),0) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='KRA')<>100
        OR (SELECT COUNT(*) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='Behavior')<>8)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Compliance Annual position or criteria validation failed';
  END IF;

  -- BEGIN SEEDED OWNERS
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'CO-HEAD-A-DRYRUN-2026',employee_id,'President and CEO' FROM employees WHERE employee_code='CEO-001'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'CO-SUP-A-DRYRUN-2026',employee_id,'Employee' FROM employees WHERE employee_code='MO26-CO-H'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'CO-STAFF-A-DRYRUN-2026',employee_id,'Employee' FROM employees WHERE employee_code='MO26-CO-S'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'CO-STAFF-A-DRYRUN-2027',employee_id,'Employee' FROM employees WHERE employee_code='MO26-CO-S'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  UPDATE evaluation_templates t JOIN evaluation_seed_template_owners o ON o.form_code=t.form_code
  SET t.created_by=(SELECT MIN(u.user_id) FROM users u WHERE u.employee_id=o.employee_id
    AND (u.role=o.preferred_role OR (o.preferred_role='Employee' AND u.role IN ('HR Manager','HR Supervisor','HR Staff')))
    AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0)
  WHERE t.created_by IS NULL;
  -- END SEEDED OWNERS
  COMMIT;
  SELECT 'Compliance Annual forms imported' AS result,4 AS forms,6 AS covered_positions;
END //
DELIMITER ;
CALL seed_compliance_annual_dry_run();
DROP PROCEDURE seed_compliance_annual_dry_run;
