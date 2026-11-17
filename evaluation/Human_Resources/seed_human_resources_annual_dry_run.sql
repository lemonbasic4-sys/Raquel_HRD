-- HUMAN RESOURCES ANNUAL DRY RUN
-- Base period: 2026-01-01 to 2026-12-31; all 15 HR positions.
-- HR Staff post-probation period: 2027-01-01 to 2027-12-31.
-- Templates only. No scores, submissions, approvals, or assignments.
-- Annual self-rating requires an Approved Quarterly in the same year.
-- The employee must have an actual regularization effective date recorded.
USE raquel_hris;
DROP PROCEDURE IF EXISTS seed_human_resources_annual_dry_run;
DELIMITER //
CREATE PROCEDURE seed_human_resources_annual_dry_run()
BEGIN
  DECLARE v_ceo INT;
  DECLARE v_mgr INT;
  DECLARE v_sup INT;
  DECLARE v_mgr_form INT;
  DECLARE v_sup_form INT;
  DECLARE v_staff_form INT;
  DECLARE v_staff_2027_form INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  IF EXISTS (SELECT 1 FROM evaluation_templates WHERE form_code IN
    ('HR-MGR-A-DRYRUN-2026','HR-SUP-A-DRYRUN-2026',
     'HR-STAFF-A-DRYRUN-2026','HR-STAFF-A-DRYRUN-2027')) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='HR Annual form already exists; do not import twice';
  END IF;
  IF (SELECT COUNT(*) FROM job_titles WHERE department_id=7 AND job_title_id BETWEEN 700 AND 714 AND is_active=1)<>15
     OR (SELECT COUNT(*) FROM job_titles WHERE department_id=7 AND job_title_id BETWEEN 700 AND 704 AND reports_to=1100 AND is_active=1)<>5
     OR (SELECT COUNT(*) FROM job_titles WHERE department_id=7 AND job_title_id BETWEEN 705 AND 709 AND reports_to=700 AND is_active=1)<>5
     OR (SELECT COUNT(*) FROM job_titles WHERE department_id=7 AND job_title_id BETWEEN 710 AND 714 AND reports_to=705 AND is_active=1)<>5 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Expected all 15 HR positions and reporting lines';
  END IF;

  SELECT MIN(u.user_id) INTO v_ceo FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='CEO-001' AND e.job_title_id=1100 AND e.is_active=1 AND e.deleted_at IS NULL
    AND u.role='President and CEO' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0;
  SELECT MIN(u.user_id) INTO v_mgr FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='HRD-001' AND e.job_title_id=700 AND e.is_active=1 AND e.deleted_at IS NULL
    AND u.role='Employee' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0 AND e.department_id=7 AND e.employment_status='Regular';
  SELECT MIN(u.user_id) INTO v_sup FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='HRD-002' AND e.job_title_id=705 AND e.is_active=1 AND e.deleted_at IS NULL
    AND u.role='Employee' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0 AND e.department_id=7 AND e.employment_status='Regular';
  IF v_ceo IS NULL OR v_mgr IS NULL OR v_sup IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Active CEO, HR Manager, and HR Supervisor portal accounts are required';
  END IF;

  START TRANSACTION;
  INSERT INTO evaluation_templates
    (template_name,description,target_department,target_department_id,target_job_title_id,
     behavior_framework_code,behavior_framework_version,evaluation_type,individual_workflow,
     assessment_from,assessment_to,kra_weight,behavior_weight,form_code,
     revision_date,effective_date_form,status,created_by)
  VALUES
    ('[DRY RUN] Annual - HR Managers I-V - Main Office',
     'Human Resources role performance and documented service outputs.',
     'Human Resources',7,700,'ANNUAL-CORE','1','Annual',1,
     '2026-01-01','2026-12-31',80,20,'HR-MGR-A-DRYRUN-2026',
     '2026-11-17','2026-11-17','Active',v_ceo),
    ('[DRY RUN] Annual - HR Supervisors I-V - Main Office',
     'Human Resources role performance and documented service outputs.',
     'Human Resources',7,705,'ANNUAL-CORE','1','Annual',1,
     '2026-01-01','2026-12-31',80,20,'HR-SUP-A-DRYRUN-2026',
     '2026-11-17','2026-11-17','Active',v_mgr),
    ('[DRY RUN] Annual - HR Staff I-V - Main Office',
     'Human Resources role performance and documented service outputs.',
     'Human Resources',7,710,'ANNUAL-CORE','1','Annual',1,
     '2026-01-01','2026-12-31',80,20,'HR-STAFF-A-DRYRUN-2026',
     '2026-11-17','2026-11-17','Active',v_sup);
  SELECT template_id INTO v_mgr_form FROM evaluation_templates WHERE form_code='HR-MGR-A-DRYRUN-2026';
  SELECT template_id INTO v_sup_form FROM evaluation_templates WHERE form_code='HR-SUP-A-DRYRUN-2026';
  SELECT template_id INTO v_staff_form FROM evaluation_templates WHERE form_code='HR-STAFF-A-DRYRUN-2026';
  INSERT INTO evaluation_template_positions (template_id,job_title_id)
  SELECT CASE WHEN job_title_id BETWEEN 700 AND 704 THEN v_mgr_form
              WHEN job_title_id BETWEEN 705 AND 709 THEN v_sup_form ELSE v_staff_form END,job_title_id
  FROM job_titles WHERE department_id=7 AND job_title_id BETWEEN 700 AND 714 AND is_active=1;

  -- Eight role-specific KRAs total 100% inside the KRA section.
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  VALUES
    -- HR Managers I-V
    (v_mgr_form,'KRA','Workforce plan','Set documented staffing priorities and service targets.','Set documented staffing priorities and service targets.',20,'Scale_1_4',1),
    (v_mgr_form,'KRA','Recruitment oversight','Review hiring delivery against approved requisitions.','Review hiring delivery against approved requisitions.',15,'Scale_1_4',2),
    (v_mgr_form,'KRA','Employee records governance','Ensure personnel records are complete and controlled.','Ensure personnel records are complete and controlled.',15,'Scale_1_4',3),
    (v_mgr_form,'KRA','Performance process oversight','Monitor evaluation cycles and resolve process exceptions.','Monitor evaluation cycles and resolve process exceptions.',15,'Scale_1_4',4),
    (v_mgr_form,'KRA','Employee relations','Guide fair and documented handling of employee issues.','Guide fair and documented handling of employee issues.',10,'Scale_1_4',5),
    (v_mgr_form,'KRA','Policy compliance','Review HR legal and policy obligations and due actions.','Review HR legal and policy obligations and due actions.',10,'Scale_1_4',6),
    (v_mgr_form,'KRA','Team leadership','Review HR supervisor performance and development.','Review HR supervisor performance and development.',10,'Scale_1_4',7),
    (v_mgr_form,'KRA','Executive reporting','Submit accurate workforce and HR risk reports.','Submit accurate workforce and HR risk reports.',5,'Scale_1_4',8),
    -- HR Supervisors I-V
    (v_sup_form,'KRA','HR service delivery','Assign and track HR requests against agreed deadlines.','Assign and track HR requests against agreed deadlines.',20,'Scale_1_4',1),
    (v_sup_form,'KRA','Recruitment coordination','Monitor screening, interviews, and onboarding records.','Monitor screening, interviews, and onboarding records.',15,'Scale_1_4',2),
    (v_sup_form,'KRA','Employee data quality','Review personnel changes for accuracy and approval.','Review personnel changes for accuracy and approval.',15,'Scale_1_4',3),
    (v_sup_form,'KRA','Evaluation administration','Track submitted forms and reviewer follow-up.','Track submitted forms and reviewer follow-up.',15,'Scale_1_4',4),
    (v_sup_form,'KRA','Staff coaching','Review HR staff work and document feedback.','Review HR staff work and document feedback.',10,'Scale_1_4',5),
    (v_sup_form,'KRA','Case documentation','Check employee relations case files for completeness.','Check employee relations case files for completeness.',10,'Scale_1_4',6),
    (v_sup_form,'KRA','Policy escalation','Escalate unresolved compliance or confidentiality concerns.','Escalate unresolved compliance or confidentiality concerns.',10,'Scale_1_4',7),
    (v_sup_form,'KRA','Manager reporting','Submit timely HR workload and issue updates.','Submit timely HR workload and issue updates.',5,'Scale_1_4',8),
    -- HR Staff I-V
    (v_staff_form,'KRA','Employee record accuracy','Update assigned personnel records with approved evidence.','Update assigned personnel records with approved evidence.',20,'Scale_1_4',1),
    (v_staff_form,'KRA','Recruitment support','Prepare applicant tracking and interview documentation.','Prepare applicant tracking and interview documentation.',15,'Scale_1_4',2),
    (v_staff_form,'KRA','Onboarding coordination','Complete assigned new-hire checklists and handoffs.','Complete assigned new-hire checklists and handoffs.',15,'Scale_1_4',3),
    (v_staff_form,'KRA','Evaluation records','File and track evaluation documents accurately.','File and track evaluation documents accurately.',15,'Scale_1_4',4),
    (v_staff_form,'KRA','HR service response','Answer assigned employee requests within agreed time frames.','Answer assigned employee requests within agreed time frames.',10,'Scale_1_4',5),
    (v_staff_form,'KRA','Confidentiality','Protect employee information and follow access controls.','Protect employee information and follow access controls.',10,'Scale_1_4',6),
    (v_staff_form,'KRA','Deadline management','Submit assigned HR reports and records on time.','Submit assigned HR reports and records on time.',10,'Scale_1_4',7),
    (v_staff_form,'KRA','Feedback application','Close review comments and improve later work.','Close review comments and improve later work.',5,'Scale_1_4',8);

  -- Core Behaviors contribute 20% overall.
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  VALUES
    (v_mgr_form,'Behavior','Positive Attitude',NULL,'Displays positive attitude at work.',0,'Scale_1_4',1),
    (v_mgr_form,'Behavior','Respect',NULL,'Shows respect to all people in the organization.',0,'Scale_1_4',2),
    (v_mgr_form,'Behavior','Accountability',NULL,'Takes responsibility for assigned work.',0,'Scale_1_4',3),
    (v_mgr_form,'Behavior','Commitment',NULL,'Demonstrates commitment to the job.',0,'Scale_1_4',4),
    (v_mgr_form,'Behavior','Teamwork',NULL,'Works cooperatively with others.',0,'Scale_1_4',5),
    (v_mgr_form,'Behavior','Integrity',NULL,'Acts honestly and ethically.',0,'Scale_1_4',6),
    (v_mgr_form,'Behavior','Continuous Improvement',NULL,'Works consistently to improve.',0,'Scale_1_4',7),
    (v_mgr_form,'Behavior','Excellent Client Experience',NULL,'Serves internal and external clients well.',0,'Scale_1_4',8);
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  SELECT target.template_id,c.section,c.criterion_name,c.description,c.kpi_description,c.weight,c.scoring_method,c.sort_order
  FROM evaluation_criteria c CROSS JOIN (SELECT v_sup_form AS template_id UNION ALL SELECT v_staff_form) target
  WHERE c.template_id=v_mgr_form AND c.section='Behavior';

  -- Later HR Staff form for Sofia and Celine after probation.
  INSERT INTO evaluation_templates
    (template_name,description,target_department,target_department_id,target_job_title_id,
     behavior_framework_code,behavior_framework_version,evaluation_type,individual_workflow,
     assessment_from,assessment_to,kra_weight,behavior_weight,form_code,
     revision_date,effective_date_form,status,created_by)
  VALUES
    ('[DRY RUN] Annual - HR Staff - Post-Probation 2027',
     'Regular HR Staff I-V review after probation.',
     'Human Resources',7,710,'ANNUAL-CORE','1','Annual',1,
     '2027-01-01','2027-12-31',80,20,'HR-STAFF-A-DRYRUN-2027',
     '2026-11-17','2027-01-01','Active',v_sup);
  SET v_staff_2027_form=LAST_INSERT_ID();
  INSERT INTO evaluation_template_positions (template_id,job_title_id)
  SELECT v_staff_2027_form,job_title_id FROM job_titles WHERE department_id=7 AND job_title_id BETWEEN 710 AND 714 AND is_active=1;
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  SELECT v_staff_2027_form,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order
  FROM evaluation_criteria WHERE template_id=v_staff_form;

  IF (SELECT COUNT(*) FROM evaluation_template_positions WHERE template_id IN (v_mgr_form,v_sup_form,v_staff_form,v_staff_2027_form))<>20
     OR EXISTS (SELECT 1 FROM evaluation_templates t WHERE t.template_id IN (v_mgr_form,v_sup_form,v_staff_form,v_staff_2027_form)
       AND ((SELECT COUNT(*) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='KRA')<>8
        OR (SELECT COALESCE(SUM(c.weight),0) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='KRA')<>100
        OR (SELECT COUNT(*) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='Behavior')<>8)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='HR Annual position or criteria validation failed';
  END IF;
  COMMIT;
  SELECT 'HR Annual forms imported' AS result,4 AS forms,15 AS covered_positions;
END //
DELIMITER ;
CALL seed_human_resources_annual_dry_run();
DROP PROCEDURE seed_human_resources_annual_dry_run;

