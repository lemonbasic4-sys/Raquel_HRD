-- COMPLIANCE QUARTERLY DRY RUN
-- Base period: 2026-11-15 to 2027-02-14; covers all six positions.
-- Later Staff I-III period: 2027-04-15 to 2027-07-14 after probation.
-- Templates only: no scores, submissions, approvals, or assignments.
USE raquel_hris;
DROP PROCEDURE IF EXISTS seed_compliance_quarterly_dry_run;
DELIMITER //
CREATE PROCEDURE seed_compliance_quarterly_dry_run()
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
    ('CO-HEAD-Q-DRYRUN-20261115','CO-SUP-Q-DRYRUN-20261115',
     'CO-STAFF-Q-DRYRUN-20261115','CO-STAFF-Q-DRYRUN-20270415')) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Compliance Quarterly form already exists; do not import twice';
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
  IF v_ceo IS NULL OR v_head IS NULL OR v_supervisor IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Active CEO and Compliance supervisor portal accounts are required';
  END IF;

  START TRANSACTION;
  INSERT INTO evaluation_templates
    (template_name,description,target_department,target_department_id,target_job_title_id,
     behavior_framework_code,behavior_framework_version,evaluation_type,individual_workflow,
     assessment_from,assessment_to,kra_weight,behavior_weight,form_code,
     revision_date,effective_date_form,status,created_by)
  VALUES
    ('[DRY RUN] Quarterly - Compliance Supervisor I - Main Office','Compliance program leadership and risk oversight.',
     'Compliance',4,400,'QUARTERLY-CORE','1','Quarterly',1,'2026-11-15','2027-02-14',80,20,
     'CO-HEAD-Q-DRYRUN-20261115','2026-11-17','2026-11-17','Active',v_ceo),
    ('[DRY RUN] Quarterly - Compliance Supervisors II-III - Main Office','Compliance review, issue tracking, and staff guidance.',
     'Compliance',4,401,'QUARTERLY-CORE','1','Quarterly',1,'2026-11-15','2027-02-14',80,20,
     'CO-SUP-Q-DRYRUN-20261115','2026-11-17','2026-11-17','Active',v_head),
    ('[DRY RUN] Quarterly - Compliance Staff I-III - Main Office','Policy checks, evidence, issue records, and follow-up.',
     'Compliance',4,403,'QUARTERLY-CORE','1','Quarterly',1,'2026-11-15','2027-02-14',80,20,
     'CO-STAFF-Q-DRYRUN-20261115','2026-11-17','2026-11-17','Active',v_supervisor);
  SELECT template_id INTO v_head_form FROM evaluation_templates WHERE form_code='CO-HEAD-Q-DRYRUN-20261115';
  SELECT template_id INTO v_supervisor_form FROM evaluation_templates WHERE form_code='CO-SUP-Q-DRYRUN-20261115';
  SELECT template_id INTO v_staff_form FROM evaluation_templates WHERE form_code='CO-STAFF-Q-DRYRUN-20261115';

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

  -- Additional Regular cycle for staff after Final approval and regularization.
  INSERT INTO evaluation_templates
    (template_name,description,target_department,target_department_id,target_job_title_id,
     behavior_framework_code,behavior_framework_version,evaluation_type,individual_workflow,
     assessment_from,assessment_to,kra_weight,behavior_weight,form_code,
     revision_date,effective_date_form,status,created_by)
  VALUES
    ('[DRY RUN] Quarterly - Compliance Staff - Post-Probation 2027',
     'Regular Compliance Staff I-III review after probation.',
     'Compliance',4,403,'QUARTERLY-CORE','1','Quarterly',1,'2027-04-15','2027-07-14',80,20,
     'CO-STAFF-Q-DRYRUN-20270415','2026-11-17','2027-04-15','Active',v_supervisor);
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
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Compliance Quarterly position or criteria validation failed';
  END IF;
  COMMIT;
  SELECT 'Compliance Quarterly forms imported' AS result,4 AS forms,6 AS covered_positions;
END //
DELIMITER ;
CALL seed_compliance_quarterly_dry_run();
DROP PROCEDURE seed_compliance_quarterly_dry_run;
