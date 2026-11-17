-- BUSINESS DEVELOPMENT QUARTERLY DRY RUN
-- Period: 2026-11-15 to 2027-02-14 for all three BD positions.
-- A second Staff I-II cycle runs 2027-04-15 to 2027-07-14 after probation.
-- Templates only: no scores, submissions, approvals, or assignments.
USE raquel_hris;
DROP PROCEDURE IF EXISTS seed_business_development_quarterly_dry_run;
DELIMITER //
CREATE PROCEDURE seed_business_development_quarterly_dry_run()
BEGIN
  DECLARE v_ceo INT;
  DECLARE v_officer INT;
  DECLARE v_officer_form INT;
  DECLARE v_staff_form INT;
  DECLARE v_staff_2027_form INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  IF EXISTS (SELECT 1 FROM evaluation_templates WHERE form_code IN
    ('BD-OFFICER-Q-DRYRUN-20261115','BD-STAFF-Q-DRYRUN-20261115',
     'BD-STAFF-Q-DRYRUN-20270415')) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='BD Quarterly form already exists; do not import twice';
  END IF;
  IF (SELECT COUNT(*) FROM job_titles WHERE department_id=3 AND job_title_id IN (300,302,303) AND is_active=1)<>3
     OR NOT EXISTS (SELECT 1 FROM job_titles WHERE job_title_id=300 AND reports_to=1100 AND is_active=1)
     OR (SELECT COUNT(*) FROM job_titles WHERE department_id=3 AND job_title_id IN (302,303) AND reports_to=300 AND is_active=1)<>2
     OR (SELECT COUNT(*) FROM job_titles WHERE department_id=3 AND is_active=1)<>3 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Expected BD Officer I and Staff I-II positions';
  END IF;

  SELECT MIN(u.user_id) INTO v_ceo FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='CEO-001' AND e.job_title_id=1100 AND e.is_active=1 AND e.deleted_at IS NULL
    AND u.role='President and CEO' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0;
  SELECT MIN(u.user_id) INTO v_officer FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='MO26-BD-H' AND e.department_id=3 AND e.job_title_id=300
    AND e.employment_status='Regular' AND e.is_active=1 AND e.deleted_at IS NULL
    AND u.role='Employee' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0;
  IF v_ceo IS NULL OR v_officer IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Active CEO and BD Officer portal accounts are required';
  END IF;

  START TRANSACTION;
  INSERT INTO evaluation_templates
    (template_name,description,target_department,target_department_id,target_job_title_id,
     behavior_framework_code,behavior_framework_version,evaluation_type,individual_workflow,
     assessment_from,assessment_to,kra_weight,behavior_weight,form_code,
     revision_date,effective_date_form,status,created_by)
  VALUES
    ('[DRY RUN] Quarterly - Business Development Officer I - Main Office',
     'Business opportunity planning, partner coordination, and proposal quality.',
     'Business Development',3,300,'QUARTERLY-CORE','1','Quarterly',1,
     '2026-11-15','2027-02-14',80,20,'BD-OFFICER-Q-DRYRUN-20261115',
     '2026-11-17','2026-11-17','Active',v_ceo),
    ('[DRY RUN] Quarterly - Business Development Staff I-II - Main Office',
     'Opportunity research, records, partner follow-up, and proposal support.',
     'Business Development',3,302,'QUARTERLY-CORE','1','Quarterly',1,
     '2026-11-15','2027-02-14',80,20,'BD-STAFF-Q-DRYRUN-20261115',
     '2026-11-17','2026-11-17','Active',v_officer);
  SELECT template_id INTO v_officer_form FROM evaluation_templates WHERE form_code='BD-OFFICER-Q-DRYRUN-20261115';
  SELECT template_id INTO v_staff_form FROM evaluation_templates WHERE form_code='BD-STAFF-Q-DRYRUN-20261115';

  INSERT INTO evaluation_template_positions (template_id,job_title_id)
  SELECT CASE WHEN job_title_id=300 THEN v_officer_form ELSE v_staff_form END,job_title_id
  FROM job_titles WHERE department_id=3 AND job_title_id IN (300,302,303) AND is_active=1;

  -- Role-specific KRA weights total 100% within the KRA section.
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  VALUES
    (v_officer_form,'KRA','Opportunity plan','Set a documented pipeline plan with target segments, owners, and review milestones.','Approved pipeline plan and progress reviews.',20,'Scale_1_4',1),
    (v_officer_form,'KRA','Opportunity qualification','Review leads against approved commercial, operational, and risk criteria.','Documented qualification decisions.',15,'Scale_1_4',2),
    (v_officer_form,'KRA','Proposal quality','Ensure proposals use current approved terms and supporting analysis before endorsement.','Complete reviewed proposals.',15,'Scale_1_4',3),
    (v_officer_form,'KRA','Partner coordination','Manage agreed partner meetings, follow-ups, and issue escalation.','Documented partner actions and outcomes.',15,'Scale_1_4',4),
    (v_officer_form,'KRA','Team leadership','Assign staff research and follow-up work and give documented feedback.','Staff work reviews and coaching records.',10,'Scale_1_4',5),
    (v_officer_form,'KRA','Cross-team feasibility','Coordinate opportunities with Finance, Operations, Legal, and other required reviewers.','Documented feasibility inputs and blockers.',10,'Scale_1_4',6),
    (v_officer_form,'KRA','Pipeline reporting','Report current opportunity stages, risks, and decisions needed on time.','Accurate scheduled pipeline reports.',10,'Scale_1_4',7),
    (v_officer_form,'KRA','Compliance and records','Keep approval, confidentiality, and opportunity records complete.','Traceable decision and document files.',5,'Scale_1_4',8),
    (v_staff_form,'KRA','Opportunity research','Research assigned markets, clients, or partners using traceable sources.','Accurate research briefs.',20,'Scale_1_4',1),
    (v_staff_form,'KRA','Lead record accuracy','Maintain complete and current opportunity contact and status records.','Updated lead and pipeline records.',15,'Scale_1_4',2),
    (v_staff_form,'KRA','Qualification support','Collect required facts for officer review against approved criteria.','Complete qualification summaries.',15,'Scale_1_4',3),
    (v_staff_form,'KRA','Proposal documentation','Prepare accurate proposal inputs and version-controlled attachments.','Reviewed proposal support files.',15,'Scale_1_4',4),
    (v_staff_form,'KRA','Partner follow-up','Track agreed follow-ups and communicate responses within deadlines.','Logged follow-ups and outcomes.',10,'Scale_1_4',5),
    (v_staff_form,'KRA','Meeting coordination','Prepare agendas, notes, and action trackers for approved discussions.','Timely meeting notes and action logs.',10,'Scale_1_4',6),
    (v_staff_form,'KRA','Confidentiality and compliance','Handle partner and company information under approved controls.','Compliant handling and prompt escalation.',10,'Scale_1_4',7),
    (v_staff_form,'KRA','Feedback and learning','Apply review comments and complete assigned role learning.','Closed feedback items and training records.',5,'Scale_1_4',8);

  -- Shared eight Core Behaviors contribute 20% to the overall score.
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  VALUES
    (v_officer_form,'Behavior','Positive Attitude',NULL,'Displays positive attitude at work.',0,'Scale_1_4',1),
    (v_officer_form,'Behavior','Respect',NULL,'Shows respect to all people in the organization.',0,'Scale_1_4',2),
    (v_officer_form,'Behavior','Accountability',NULL,'Takes responsibility for assigned work.',0,'Scale_1_4',3),
    (v_officer_form,'Behavior','Commitment',NULL,'Demonstrates commitment to the job.',0,'Scale_1_4',4),
    (v_officer_form,'Behavior','Teamwork',NULL,'Works cooperatively with others.',0,'Scale_1_4',5),
    (v_officer_form,'Behavior','Integrity',NULL,'Acts honestly and ethically.',0,'Scale_1_4',6),
    (v_officer_form,'Behavior','Continuous Improvement',NULL,'Works consistently to improve.',0,'Scale_1_4',7),
    (v_officer_form,'Behavior','Excellent Client Experience',NULL,'Serves internal and external clients well.',0,'Scale_1_4',8);
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  SELECT v_staff_form,c.section,c.criterion_name,c.description,c.kpi_description,c.weight,c.scoring_method,c.sort_order
  FROM evaluation_criteria c WHERE c.template_id=v_officer_form AND c.section='Behavior';

  -- Later Regular cycle for Staff I-II after approved Final and regularization.
  INSERT INTO evaluation_templates
    (template_name,description,target_department,target_department_id,target_job_title_id,
     behavior_framework_code,behavior_framework_version,evaluation_type,individual_workflow,
     assessment_from,assessment_to,kra_weight,behavior_weight,form_code,
     revision_date,effective_date_form,status,created_by)
  VALUES
    ('[DRY RUN] Quarterly - BD Staff - Post-Probation 2027',
     'Regular Business Development Staff I-II review after probation.',
     'Business Development',3,302,'QUARTERLY-CORE','1','Quarterly',1,
     '2027-04-15','2027-07-14',80,20,'BD-STAFF-Q-DRYRUN-20270415',
     '2026-11-17','2027-04-15','Active',v_officer);
  SET v_staff_2027_form=LAST_INSERT_ID();
  INSERT INTO evaluation_template_positions (template_id,job_title_id)
  SELECT v_staff_2027_form,job_title_id FROM job_titles
  WHERE department_id=3 AND job_title_id IN (302,303) AND is_active=1;
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  SELECT v_staff_2027_form,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order
  FROM evaluation_criteria WHERE template_id=v_staff_form;

  IF (SELECT COUNT(*) FROM evaluation_template_positions WHERE template_id IN (v_officer_form,v_staff_form,v_staff_2027_form))<>5
     OR EXISTS (SELECT 1 FROM evaluation_templates t WHERE t.template_id IN (v_officer_form,v_staff_form,v_staff_2027_form)
       AND ((SELECT COUNT(*) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='KRA')<>8
        OR (SELECT COALESCE(SUM(c.weight),0) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='KRA')<>100
        OR (SELECT COUNT(*) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='Behavior')<>8)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='BD Quarterly position or criteria validation failed';
  END IF;
  COMMIT;
  SELECT 'Business Development Quarterly forms imported' AS result,3 AS forms,3 AS covered_positions;
END //
DELIMITER ;
CALL seed_business_development_quarterly_dry_run();
DROP PROCEDURE seed_business_development_quarterly_dry_run;
