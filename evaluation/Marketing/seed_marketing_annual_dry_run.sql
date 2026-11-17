-- MARKETING ANNUAL DRY RUN
-- Base period: 2026-01-01 to 2026-12-31; all six positions.
-- Marketing Staff post-probation period: 2027-01-01 to 2027-12-31.
-- Templates only. No scores, submissions, approvals, or assignments.
-- Annual self-rating requires an Approved Quarterly in the same year.
-- Actual period starts on the later of Jan 1 or regularization date.
USE raquel_hris;
DROP PROCEDURE IF EXISTS seed_marketing_annual_dry_run;
DELIMITER //
CREATE PROCEDURE seed_marketing_annual_dry_run()
BEGIN
  DECLARE v_ceo INT;
  DECLARE v_lead INT;
  DECLARE v_sup INT;
  DECLARE v_lead_form INT;
  DECLARE v_mgr_form INT;
  DECLARE v_sup_form INT;
  DECLARE v_staff_form INT;
  DECLARE v_staff_2027_form INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  IF EXISTS (SELECT 1 FROM evaluation_templates WHERE form_code IN
    ('MK-LEAD-A-DRYRUN-2026',
     'MK-MGR-A-DRYRUN-2026',
     'MK-SUP-A-DRYRUN-2026',
     'MK-STAFF-A-DRYRUN-2026',
     'MK-STAFF-A-DRYRUN-2027')) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Marketing Annual form already exists; do not import twice';
  END IF;
  IF (SELECT COUNT(*) FROM job_titles WHERE department_id=9 AND job_title_id BETWEEN 900 AND 905 AND is_active=1)<>6
     OR NOT EXISTS (SELECT 1 FROM job_titles WHERE job_title_id=900 AND reports_to=1100 AND is_active=1)
     OR NOT EXISTS (SELECT 1 FROM job_titles WHERE job_title_id=901 AND reports_to=900 AND is_active=1)
     OR (SELECT COUNT(*) FROM job_titles WHERE department_id=9 AND job_title_id BETWEEN 902 AND 903 AND reports_to=900 AND is_active=1)<>2
     OR (SELECT COUNT(*) FROM job_titles WHERE department_id=9 AND job_title_id BETWEEN 904 AND 905 AND reports_to=902 AND is_active=1)<>2 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Expected all six Marketing positions and reporting lines';
  END IF;

  SELECT MIN(u.user_id) INTO v_ceo FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='CEO-001' AND e.job_title_id=1100 AND e.is_active=1 AND e.deleted_at IS NULL
    AND u.role='President and CEO' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0;
  SELECT MIN(u.user_id) INTO v_lead FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='MO26-MK-M' AND e.job_title_id=900 AND e.is_active=1 AND e.deleted_at IS NULL
    AND u.role='Employee' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0 AND e.department_id=9 AND e.employment_status='Regular';
  SELECT MIN(u.user_id) INTO v_sup FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='MO26-MK-H' AND e.job_title_id=902 AND e.is_active=1 AND e.deleted_at IS NULL
    AND u.role='Employee' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0 AND e.department_id=9 AND e.employment_status='Regular';
  IF v_ceo IS NULL OR v_lead IS NULL OR v_sup IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Active CEO, Marketing Manager, and Supervisor portal accounts are required';
  END IF;

  START TRANSACTION;
  INSERT INTO evaluation_templates
    (template_name,description,target_department,target_department_id,target_job_title_id,
     behavior_framework_code,behavior_framework_version,evaluation_type,individual_workflow,
     assessment_from,assessment_to,kra_weight,behavior_weight,form_code,
     revision_date,effective_date_form,status,created_by)
  VALUES
    ('[DRY RUN] Annual - Marketing Manager I - Main Office',
     'Marketing role performance and documented campaign outputs.',
     'Marketing',9,900,'ANNUAL-CORE','1','Annual',1,
     '2026-01-01','2026-12-31',80,20,'MK-LEAD-A-DRYRUN-2026',
     '2026-11-17','2026-11-17','Active',v_ceo),
    ('[DRY RUN] Annual - Marketing Manager II - Main Office',
     'Marketing role performance and documented campaign outputs.',
     'Marketing',9,901,'ANNUAL-CORE','1','Annual',1,
     '2026-01-01','2026-12-31',80,20,'MK-MGR-A-DRYRUN-2026',
     '2026-11-17','2026-11-17','Active',v_lead),
    ('[DRY RUN] Annual - Marketing Supervisors I-II - Main Office',
     'Marketing role performance and documented campaign outputs.',
     'Marketing',9,902,'ANNUAL-CORE','1','Annual',1,
     '2026-01-01','2026-12-31',80,20,'MK-SUP-A-DRYRUN-2026',
     '2026-11-17','2026-11-17','Active',v_lead),
    ('[DRY RUN] Annual - Marketing Staff I-II - Main Office',
     'Marketing role performance and documented campaign outputs.',
     'Marketing',9,904,'ANNUAL-CORE','1','Annual',1,
     '2026-01-01','2026-12-31',80,20,'MK-STAFF-A-DRYRUN-2026',
     '2026-11-17','2026-11-17','Active',v_sup);
  SELECT template_id INTO v_lead_form FROM evaluation_templates WHERE form_code='MK-LEAD-A-DRYRUN-2026';
  SELECT template_id INTO v_mgr_form FROM evaluation_templates WHERE form_code='MK-MGR-A-DRYRUN-2026';
  SELECT template_id INTO v_sup_form FROM evaluation_templates WHERE form_code='MK-SUP-A-DRYRUN-2026';
  SELECT template_id INTO v_staff_form FROM evaluation_templates WHERE form_code='MK-STAFF-A-DRYRUN-2026';
  INSERT INTO evaluation_template_positions (template_id,job_title_id)
  SELECT CASE WHEN job_title_id=900 THEN v_lead_form WHEN job_title_id=901 THEN v_mgr_form
              WHEN job_title_id BETWEEN 902 AND 903 THEN v_sup_form ELSE v_staff_form END,job_title_id
  FROM job_titles WHERE department_id=9 AND job_title_id BETWEEN 900 AND 905 AND is_active=1;

  -- Eight role-specific KRAs total 100% inside the KRA section.
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  VALUES
    -- Marketing Manager I
    (v_lead_form,'KRA','Marketing plan','Set approved campaign priorities, audiences, and measurable objectives.','Set approved campaign priorities, audiences, and measurable objectives.',20,'Scale_1_4',1),
    (v_lead_form,'KRA','Brand governance','Ensure published materials follow approved identity and claims.','Ensure published materials follow approved identity and claims.',15,'Scale_1_4',2),
    (v_lead_form,'KRA','Campaign performance','Review results against approved campaign targets.','Review results against approved campaign targets.',15,'Scale_1_4',3),
    (v_lead_form,'KRA','Budget stewardship','Monitor spending and explain material campaign variances.','Monitor spending and explain material campaign variances.',15,'Scale_1_4',4),
    (v_lead_form,'KRA','Channel strategy','Select channels using audience and performance evidence.','Select channels using audience and performance evidence.',10,'Scale_1_4',5),
    (v_lead_form,'KRA','Team leadership','Review manager and supervisor delivery and development.','Review manager and supervisor delivery and development.',10,'Scale_1_4',6),
    (v_lead_form,'KRA','Risk and approvals','Escalate reputational, privacy, and compliance issues.','Escalate reputational, privacy, and compliance issues.',10,'Scale_1_4',7),
    (v_lead_form,'KRA','Executive reporting','Present accurate campaign results and decisions needed.','Present accurate campaign results and decisions needed.',5,'Scale_1_4',8),
    -- Marketing Manager II
    (v_mgr_form,'KRA','Campaign delivery','Translate approved plans into schedules, owners, and outputs.','Translate approved plans into schedules, owners, and outputs.',20,'Scale_1_4',1),
    (v_mgr_form,'KRA','Audience insight','Use documented research to guide campaign decisions.','Use documented research to guide campaign decisions.',15,'Scale_1_4',2),
    (v_mgr_form,'KRA','Content quality','Review claims, accuracy, and brand alignment before release.','Review claims, accuracy, and brand alignment before release.',15,'Scale_1_4',3),
    (v_mgr_form,'KRA','Performance analysis','Explain reach, engagement, and response trends.','Explain reach, engagement, and response trends.',15,'Scale_1_4',4),
    (v_mgr_form,'KRA','Vendor coordination','Track approved creative or media suppliers against deliverables.','Track approved creative or media suppliers against deliverables.',10,'Scale_1_4',5),
    (v_mgr_form,'KRA','Supervisor coaching','Review supervisor work and document feedback.','Review supervisor work and document feedback.',10,'Scale_1_4',6),
    (v_mgr_form,'KRA','Risk escalation','Report sensitive claims and public issues promptly.','Report sensitive claims and public issues promptly.',10,'Scale_1_4',7),
    (v_mgr_form,'KRA','Manager reporting','Submit accurate campaign status and variance reports.','Submit accurate campaign status and variance reports.',5,'Scale_1_4',8),
    -- Marketing Supervisors I-II
    (v_sup_form,'KRA','Production scheduling','Assign content and campaign tasks with deadlines.','Assign content and campaign tasks with deadlines.',20,'Scale_1_4',1),
    (v_sup_form,'KRA','Material review','Check deliverables against briefs and brand guidelines.','Check deliverables against briefs and brand guidelines.',15,'Scale_1_4',2),
    (v_sup_form,'KRA','Publishing control','Confirm required approvals before posting or distribution.','Confirm required approvals before posting or distribution.',15,'Scale_1_4',3),
    (v_sup_form,'KRA','Channel execution','Monitor scheduled placements and correct delivery issues.','Monitor scheduled placements and correct delivery issues.',15,'Scale_1_4',4),
    (v_sup_form,'KRA','Staff coaching','Review staff work and follow up on corrections.','Review staff work and follow up on corrections.',10,'Scale_1_4',5),
    (v_sup_form,'KRA','Asset organization','Keep approved creative files and versions traceable.','Keep approved creative files and versions traceable.',10,'Scale_1_4',6),
    (v_sup_form,'KRA','Issue escalation','Report inaccurate claims or campaign incidents promptly.','Report inaccurate claims or campaign incidents promptly.',10,'Scale_1_4',7),
    (v_sup_form,'KRA','Manager updates','Provide timely progress and performance notes.','Provide timely progress and performance notes.',5,'Scale_1_4',8),
    -- Marketing Staff I-II
    (v_staff_form,'KRA','Assigned content delivery','Complete approved content tasks by agreed deadlines.','Complete approved content tasks by agreed deadlines.',20,'Scale_1_4',1),
    (v_staff_form,'KRA','Content accuracy','Check facts, links, dates, and approved claims.','Check facts, links, dates, and approved claims.',15,'Scale_1_4',2),
    (v_staff_form,'KRA','Brand consistency','Apply current visual and language guidelines.','Apply current visual and language guidelines.',15,'Scale_1_4',3),
    (v_staff_form,'KRA','Campaign documentation','Keep briefs, assets, and approval records complete.','Keep briefs, assets, and approval records complete.',15,'Scale_1_4',4),
    (v_staff_form,'KRA','Publishing support','Prepare scheduled posts and distribution files correctly.','Prepare scheduled posts and distribution files correctly.',10,'Scale_1_4',5),
    (v_staff_form,'KRA','Audience response','Route customer and public inquiries to the right owner.','Route customer and public inquiries to the right owner.',10,'Scale_1_4',6),
    (v_staff_form,'KRA','Performance records','Record campaign results using agreed measures.','Record campaign results using agreed measures.',10,'Scale_1_4',7),
    (v_staff_form,'KRA','Feedback application','Apply review comments to subsequent work.','Apply review comments to subsequent work.',5,'Scale_1_4',8);

  -- Shared Core Behaviors contribute 20% overall.
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
  FROM evaluation_criteria c CROSS JOIN (SELECT v_mgr_form AS template_id UNION ALL SELECT v_sup_form UNION ALL SELECT v_staff_form) target
  WHERE c.template_id=v_lead_form AND c.section='Behavior';

  -- Later Staff form for Lea and Joel after probation.
  INSERT INTO evaluation_templates
    (template_name,description,target_department,target_department_id,target_job_title_id,
     behavior_framework_code,behavior_framework_version,evaluation_type,individual_workflow,
     assessment_from,assessment_to,kra_weight,behavior_weight,form_code,
     revision_date,effective_date_form,status,created_by)
  VALUES
    ('[DRY RUN] Annual - Marketing Staff - Post-Probation 2027',
     'Regular Marketing Staff I-II review after probation.',
     'Marketing',9,904,'ANNUAL-CORE','1','Annual',1,
     '2027-01-01','2027-12-31',80,20,'MK-STAFF-A-DRYRUN-2027',
     '2026-11-17','2027-01-01','Active',v_sup);
  SET v_staff_2027_form=LAST_INSERT_ID();
  INSERT INTO evaluation_template_positions (template_id,job_title_id)
  SELECT v_staff_2027_form,job_title_id FROM job_titles WHERE department_id=9 AND job_title_id BETWEEN 904 AND 905 AND is_active=1;
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  SELECT v_staff_2027_form,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order
  FROM evaluation_criteria WHERE template_id=v_staff_form;

  IF (SELECT COUNT(*) FROM evaluation_template_positions WHERE template_id IN (v_lead_form,v_mgr_form,v_sup_form,v_staff_form,v_staff_2027_form))<>8
     OR EXISTS (SELECT 1 FROM evaluation_templates t WHERE t.template_id IN (v_lead_form,v_mgr_form,v_sup_form,v_staff_form,v_staff_2027_form)
       AND ((SELECT COUNT(*) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='KRA')<>8
        OR (SELECT COALESCE(SUM(c.weight),0) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='KRA')<>100
        OR (SELECT COUNT(*) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='Behavior')<>8)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Marketing Annual position or criteria validation failed';
  END IF;
  COMMIT;
  SELECT 'Marketing Annual forms imported' AS result,5 AS forms,6 AS covered_positions;
END //
DELIMITER ;
CALL seed_marketing_annual_dry_run();
DROP PROCEDURE seed_marketing_annual_dry_run;

