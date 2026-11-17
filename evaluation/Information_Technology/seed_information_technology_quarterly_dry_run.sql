-- INFORMATION TECHNOLOGY QUARTERLY DRY RUN
-- Base period: 2026-11-15 to 2027-02-14; all 18 IT positions.
-- Technical Support post-probation period: 2027-04-15 to 2027-07-14.
-- Templates only. No scores, submissions, approvals, or assignments.
USE raquel_hris;
DROP PROCEDURE IF EXISTS seed_information_technology_quarterly_dry_run;
DELIMITER //
CREATE PROCEDURE seed_information_technology_quarterly_dry_run()
BEGIN
  DECLARE v_ops INT;
  DECLARE v_mgr INT;
  DECLARE v_sup INT;
  DECLARE v_lead_form INT;
  DECLARE v_mgr_form INT;
  DECLARE v_sup_form INT;
  DECLARE v_programmer_form INT;
  DECLARE v_tech_form INT;
  DECLARE v_help_form INT;
  DECLARE v_tech_2027_form INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  IF EXISTS (SELECT 1 FROM evaluation_templates WHERE form_code IN
    ('IT-LEAD-Q-DRYRUN-20261115',
     'IT-MGR-Q-DRYRUN-20261115',
     'IT-SUP-Q-DRYRUN-20261115',
     'IT-PROGRAMMER-Q-DRYRUN-20261115',
     'IT-TECH-Q-DRYRUN-20261115',
     'IT-HELP-Q-DRYRUN-20261115',
     'IT-TECH-Q-DRYRUN-20270415')) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='IT Quarterly form already exists; do not import twice';
  END IF;
  IF (SELECT COUNT(*) FROM job_titles WHERE department_id=8 AND job_title_id BETWEEN 800 AND 817 AND is_active=1)<>18
     OR NOT EXISTS (SELECT 1 FROM job_titles WHERE job_title_id=800 AND reports_to=1000 AND is_active=1)
     OR (SELECT COUNT(*) FROM job_titles WHERE department_id=8 AND job_title_id BETWEEN 801 AND 803 AND reports_to=800 AND is_active=1)<>3
     OR (SELECT COUNT(*) FROM job_titles WHERE department_id=8 AND job_title_id BETWEEN 804 AND 808 AND reports_to=800 AND is_active=1)<>5
     OR (SELECT COUNT(*) FROM job_titles WHERE department_id=8 AND job_title_id BETWEEN 809 AND 817 AND reports_to=804 AND is_active=1)<>9 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Expected all 18 IT positions and reporting lines';
  END IF;

  SELECT MIN(u.user_id) INTO v_ops FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='MO26-OP-V' AND e.department_id=11 AND e.job_title_id=1000 AND e.employment_status='Regular'
    AND e.is_active=1 AND e.deleted_at IS NULL AND u.role='Employee' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0;
  SELECT MIN(u.user_id) INTO v_mgr FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='MO26-IT-M' AND e.department_id=8 AND e.job_title_id=800 AND e.employment_status='Regular'
    AND e.is_active=1 AND e.deleted_at IS NULL AND u.role='Employee' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0;
  SELECT MIN(u.user_id) INTO v_sup FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='MO26-IT-H' AND e.department_id=8 AND e.job_title_id=804 AND e.employment_status='Regular'
    AND e.is_active=1 AND e.deleted_at IS NULL AND u.role='Employee' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0;


  START TRANSACTION;
  INSERT INTO evaluation_templates
    (template_name,description,target_department,target_department_id,target_job_title_id,
     behavior_framework_code,behavior_framework_version,evaluation_type,individual_workflow,
     assessment_from,assessment_to,kra_weight,behavior_weight,form_code,
     revision_date,effective_date_form,status,created_by)
  VALUES
    ('[DRY RUN] Quarterly - IT Manager I - Main Office',
     'Information Technology role performance and documented service outputs.',
     'Information Technology',8,800,'QUARTERLY-CORE','1','Quarterly',1,
     '2026-11-15','2027-02-14',80,20,'IT-LEAD-Q-DRYRUN-20261115',
     '2026-11-17','2026-11-17','Active',v_ops),
    ('[DRY RUN] Quarterly - IT Managers II-IV - Main Office',
     'Information Technology role performance and documented service outputs.',
     'Information Technology',8,801,'QUARTERLY-CORE','1','Quarterly',1,
     '2026-11-15','2027-02-14',80,20,'IT-MGR-Q-DRYRUN-20261115',
     '2026-11-17','2026-11-17','Active',v_mgr),
    ('[DRY RUN] Quarterly - IT Supervisors I-V - Main Office',
     'Information Technology role performance and documented service outputs.',
     'Information Technology',8,804,'QUARTERLY-CORE','1','Quarterly',1,
     '2026-11-15','2027-02-14',80,20,'IT-SUP-Q-DRYRUN-20261115',
     '2026-11-17','2026-11-17','Active',v_mgr),
    ('[DRY RUN] Quarterly - Programmer I - Main Office',
     'Information Technology role performance and documented service outputs.',
     'Information Technology',8,809,'QUARTERLY-CORE','1','Quarterly',1,
     '2026-11-15','2027-02-14',80,20,'IT-PROGRAMMER-Q-DRYRUN-20261115',
     '2026-11-17','2026-11-17','Active',v_sup),
    ('[DRY RUN] Quarterly - Technical Support Staff I-V - Main Office',
     'Information Technology role performance and documented service outputs.',
     'Information Technology',8,810,'QUARTERLY-CORE','1','Quarterly',1,
     '2026-11-15','2027-02-14',80,20,'IT-TECH-Q-DRYRUN-20261115',
     '2026-11-17','2026-11-17','Active',v_sup),
    ('[DRY RUN] Quarterly - Helpdesk Assistants I-III - Main Office',
     'Information Technology role performance and documented service outputs.',
     'Information Technology',8,815,'QUARTERLY-CORE','1','Quarterly',1,
     '2026-11-15','2027-02-14',80,20,'IT-HELP-Q-DRYRUN-20261115',
     '2026-11-17','2026-11-17','Active',v_sup);
  SELECT template_id INTO v_lead_form FROM evaluation_templates WHERE form_code='IT-LEAD-Q-DRYRUN-20261115';
  SELECT template_id INTO v_mgr_form FROM evaluation_templates WHERE form_code='IT-MGR-Q-DRYRUN-20261115';
  SELECT template_id INTO v_sup_form FROM evaluation_templates WHERE form_code='IT-SUP-Q-DRYRUN-20261115';
  SELECT template_id INTO v_programmer_form FROM evaluation_templates WHERE form_code='IT-PROGRAMMER-Q-DRYRUN-20261115';
  SELECT template_id INTO v_tech_form FROM evaluation_templates WHERE form_code='IT-TECH-Q-DRYRUN-20261115';
  SELECT template_id INTO v_help_form FROM evaluation_templates WHERE form_code='IT-HELP-Q-DRYRUN-20261115';
  INSERT INTO evaluation_template_positions (template_id,job_title_id)
  SELECT CASE
    WHEN job_title_id =800 THEN v_lead_form
    WHEN job_title_id BETWEEN 801 AND 803 THEN v_mgr_form
    WHEN job_title_id BETWEEN 804 AND 808 THEN v_sup_form
    WHEN job_title_id =809 THEN v_programmer_form
    WHEN job_title_id BETWEEN 810 AND 814 THEN v_tech_form
    ELSE v_help_form END,job_title_id
  FROM job_titles WHERE department_id=8 AND job_title_id BETWEEN 800 AND 817 AND is_active=1;

  -- Eight role-specific KRAs total 100% inside the KRA section.
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  VALUES
    -- IT Manager I
    (v_lead_form,'KRA','Technology service plan','Set IT service priorities, owners, and measurable delivery targets.','Set IT service priorities, owners, and measurable delivery targets.',20,'Scale_1_4',1),
    (v_lead_form,'KRA','System availability','Monitor critical systems and address material outages.','Monitor critical systems and address material outages.',15,'Scale_1_4',2),
    (v_lead_form,'KRA','Security oversight','Direct response to access, vulnerability, and data risks.','Direct response to access, vulnerability, and data risks.',15,'Scale_1_4',3),
    (v_lead_form,'KRA','Change governance','Review material system changes and rollback readiness.','Review material system changes and rollback readiness.',15,'Scale_1_4',4),
    (v_lead_form,'KRA','Budget stewardship','Monitor approved IT spending and resource use.','Monitor approved IT spending and resource use.',10,'Scale_1_4',5),
    (v_lead_form,'KRA','Team leadership','Review IT managers and supervisors with documented feedback.','Review IT managers and supervisors with documented feedback.',10,'Scale_1_4',6),
    (v_lead_form,'KRA','Incident escalation','Escalate major incidents and continuity risks promptly.','Escalate major incidents and continuity risks promptly.',10,'Scale_1_4',7),
    (v_lead_form,'KRA','Executive reporting','Provide accurate IT status and decisions needed.','Provide accurate IT status and decisions needed.',5,'Scale_1_4',8),
    -- IT Managers II-IV
    (v_mgr_form,'KRA','Service delivery plan','Translate IT priorities into team work plans and milestones.','Translate IT priorities into team work plans and milestones.',20,'Scale_1_4',1),
    (v_mgr_form,'KRA','System operations','Monitor assigned systems, jobs, and service health.','Monitor assigned systems, jobs, and service health.',15,'Scale_1_4',2),
    (v_mgr_form,'KRA','Incident management','Coordinate diagnosis, resolution, and post-incident actions.','Coordinate diagnosis, resolution, and post-incident actions.',15,'Scale_1_4',3),
    (v_mgr_form,'KRA','Security controls','Review access and control exceptions in assigned services.','Review access and control exceptions in assigned services.',15,'Scale_1_4',4),
    (v_mgr_form,'KRA','Change delivery','Ensure tested and approved changes are deployed safely.','Ensure tested and approved changes are deployed safely.',10,'Scale_1_4',5),
    (v_mgr_form,'KRA','Supervisor coaching','Review supervisor work and document feedback.','Review supervisor work and document feedback.',10,'Scale_1_4',6),
    (v_mgr_form,'KRA','Vendor coordination','Track contracted support and escalations against commitments.','Track contracted support and escalations against commitments.',10,'Scale_1_4',7),
    (v_mgr_form,'KRA','Manager reporting','Submit accurate delivery and risk updates on time.','Submit accurate delivery and risk updates on time.',5,'Scale_1_4',8),
    -- IT Supervisors I-V
    (v_sup_form,'KRA','Team task assignment','Assign support and development tasks with deadlines.','Assign support and development tasks with deadlines.',20,'Scale_1_4',1),
    (v_sup_form,'KRA','Ticket quality review','Check resolution notes and evidence for completed tickets.','Check resolution notes and evidence for completed tickets.',15,'Scale_1_4',2),
    (v_sup_form,'KRA','Incident coordination','Lead assigned incident triage and escalation.','Lead assigned incident triage and escalation.',15,'Scale_1_4',3),
    (v_sup_form,'KRA','Change verification','Check testing, approvals, and deployment records.','Check testing, approvals, and deployment records.',15,'Scale_1_4',4),
    (v_sup_form,'KRA','Staff coaching','Review frontline work and document feedback.','Review frontline work and document feedback.',10,'Scale_1_4',5),
    (v_sup_form,'KRA','Access control checks','Escalate unusual access or security requests.','Escalate unusual access or security requests.',10,'Scale_1_4',6),
    (v_sup_form,'KRA','Service communication','Update affected users about outages and restoration.','Update affected users about outages and restoration.',10,'Scale_1_4',7),
    (v_sup_form,'KRA','Manager updates','Report backlog, service risks, and blockers.','Report backlog, service risks, and blockers.',5,'Scale_1_4',8),
    -- Programmer I
    (v_programmer_form,'KRA','Assigned feature delivery','Complete approved development tasks by agreed milestones.','Complete approved development tasks by agreed milestones.',20,'Scale_1_4',1),
    (v_programmer_form,'KRA','Code quality','Submit maintainable code with review comments addressed.','Submit maintainable code with review comments addressed.',15,'Scale_1_4',2),
    (v_programmer_form,'KRA','Testing','Document meaningful tests for changed behavior.','Document meaningful tests for changed behavior.',15,'Scale_1_4',3),
    (v_programmer_form,'KRA','Defect resolution','Investigate and fix assigned defects with evidence.','Investigate and fix assigned defects with evidence.',15,'Scale_1_4',4),
    (v_programmer_form,'KRA','Security practices','Apply approved secure coding and access requirements.','Apply approved secure coding and access requirements.',10,'Scale_1_4',5),
    (v_programmer_form,'KRA','Documentation','Keep implementation and handoff notes current.','Keep implementation and handoff notes current.',10,'Scale_1_4',6),
    (v_programmer_form,'KRA','Release coordination','Follow change approval and rollback procedures.','Follow change approval and rollback procedures.',10,'Scale_1_4',7),
    (v_programmer_form,'KRA','Team communication','Raise blockers and estimates promptly.','Raise blockers and estimates promptly.',5,'Scale_1_4',8),
    -- Technical Support Staff I-V
    (v_tech_form,'KRA','Ticket response','Acknowledge assigned requests within service targets.','Acknowledge assigned requests within service targets.',20,'Scale_1_4',1),
    (v_tech_form,'KRA','Issue diagnosis','Document symptoms, causes, and troubleshooting steps.','Document symptoms, causes, and troubleshooting steps.',15,'Scale_1_4',2),
    (v_tech_form,'KRA','Resolution quality','Resolve assigned incidents without recurring material errors.','Resolve assigned incidents without recurring material errors.',15,'Scale_1_4',3),
    (v_tech_form,'KRA','Endpoint maintenance','Complete approved device and software support tasks.','Complete approved device and software support tasks.',15,'Scale_1_4',4),
    (v_tech_form,'KRA','Access request handling','Verify authorization before account or permission changes.','Verify authorization before account or permission changes.',10,'Scale_1_4',5),
    (v_tech_form,'KRA','User communication','Give clear progress and resolution updates.','Give clear progress and resolution updates.',10,'Scale_1_4',6),
    (v_tech_form,'KRA','Knowledge records','Maintain useful ticket notes and support articles.','Maintain useful ticket notes and support articles.',10,'Scale_1_4',7),
    (v_tech_form,'KRA','Escalation','Escalate unresolved or security-sensitive issues promptly.','Escalate unresolved or security-sensitive issues promptly.',5,'Scale_1_4',8),
    -- Helpdesk Assistants I-III
    (v_help_form,'KRA','Request intake','Record user requests with complete contact and issue details.','Record user requests with complete contact and issue details.',20,'Scale_1_4',1),
    (v_help_form,'KRA','Ticket classification','Categorize priority and route tickets correctly.','Categorize priority and route tickets correctly.',15,'Scale_1_4',2),
    (v_help_form,'KRA','Initial response','Acknowledge requests and explain next steps promptly.','Acknowledge requests and explain next steps promptly.',15,'Scale_1_4',3),
    (v_help_form,'KRA','Basic troubleshooting','Perform approved first-line checks and record results.','Perform approved first-line checks and record results.',15,'Scale_1_4',4),
    (v_help_form,'KRA','Escalation routing','Transfer issues to the correct team with context.','Transfer issues to the correct team with context.',10,'Scale_1_4',5),
    (v_help_form,'KRA','Status follow-up','Track open requests and provide user updates.','Track open requests and provide user updates.',10,'Scale_1_4',6),
    (v_help_form,'KRA','Service records','Close tickets only with documented resolution or handoff.','Close tickets only with documented resolution or handoff.',10,'Scale_1_4',7),
    (v_help_form,'KRA','Customer care','Handle internal users respectfully and clearly.','Handle internal users respectfully and clearly.',5,'Scale_1_4',8);

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
  FROM evaluation_criteria c CROSS JOIN
    (SELECT v_mgr_form AS template_id UNION ALL SELECT v_sup_form UNION ALL SELECT v_programmer_form UNION ALL SELECT v_tech_form UNION ALL SELECT v_help_form) target
  WHERE c.template_id=v_lead_form AND c.section='Behavior';

  -- Later Technical Support form for Ethan and Rina after probation.
  INSERT INTO evaluation_templates
    (template_name,description,target_department,target_department_id,target_job_title_id,
     behavior_framework_code,behavior_framework_version,evaluation_type,individual_workflow,
     assessment_from,assessment_to,kra_weight,behavior_weight,form_code,
     revision_date,effective_date_form,status,created_by)
  VALUES
    ('[DRY RUN] Quarterly - Technical Support - Post-Probation 2027',
     'Regular Technical Support Staff I-V review after probation.',
     'Information Technology',8,810,'QUARTERLY-CORE','1','Quarterly',1,
     '2027-04-15','2027-07-14',80,20,'IT-TECH-Q-DRYRUN-20270415',
     '2026-11-17','2027-04-15','Active',v_sup);
  SET v_tech_2027_form=LAST_INSERT_ID();
  INSERT INTO evaluation_template_positions (template_id,job_title_id)
  SELECT v_tech_2027_form,job_title_id FROM job_titles WHERE department_id=8 AND job_title_id BETWEEN 810 AND 814 AND is_active=1;
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  SELECT v_tech_2027_form,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order
  FROM evaluation_criteria WHERE template_id=v_tech_form;

  IF (SELECT COUNT(*) FROM evaluation_template_positions WHERE template_id IN (v_lead_form,v_mgr_form,v_sup_form,v_programmer_form,v_tech_form,v_help_form,v_tech_2027_form))<>23
     OR EXISTS (SELECT 1 FROM evaluation_templates t WHERE t.template_id IN (v_lead_form,v_mgr_form,v_sup_form,v_programmer_form,v_tech_form,v_help_form,v_tech_2027_form)
       AND ((SELECT COUNT(*) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='KRA')<>8
        OR (SELECT COALESCE(SUM(c.weight),0) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='KRA')<>100
        OR (SELECT COUNT(*) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='Behavior')<>8)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='IT Quarterly position or criteria validation failed';
  END IF;

  -- BEGIN SEEDED OWNERS
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'IT-LEAD-Q-DRYRUN-20261115',employee_id,'Employee' FROM employees WHERE employee_code='MO26-OP-V'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'IT-MGR-Q-DRYRUN-20261115',employee_id,'Employee' FROM employees WHERE employee_code='MO26-IT-M'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'IT-SUP-Q-DRYRUN-20261115',employee_id,'Employee' FROM employees WHERE employee_code='MO26-IT-M'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'IT-PROGRAMMER-Q-DRYRUN-20261115',employee_id,'Employee' FROM employees WHERE employee_code='MO26-IT-H'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'IT-TECH-Q-DRYRUN-20261115',employee_id,'Employee' FROM employees WHERE employee_code='MO26-IT-H'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'IT-HELP-Q-DRYRUN-20261115',employee_id,'Employee' FROM employees WHERE employee_code='MO26-IT-H'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'IT-TECH-Q-DRYRUN-20270415',employee_id,'Employee' FROM employees WHERE employee_code='MO26-IT-H'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  UPDATE evaluation_templates t JOIN evaluation_seed_template_owners o ON o.form_code=t.form_code
  SET t.created_by=(SELECT MIN(u.user_id) FROM users u WHERE u.employee_id=o.employee_id
    AND (u.role=o.preferred_role OR (o.preferred_role='Employee' AND u.role IN ('HR Manager','HR Supervisor','HR Staff')))
    AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0)
  WHERE t.created_by IS NULL;
  -- END SEEDED OWNERS
  COMMIT;
  SELECT 'IT Quarterly forms imported' AS result,7 AS forms,18 AS covered_positions;
END //
DELIMITER ;
CALL seed_information_technology_quarterly_dry_run();
DROP PROCEDURE seed_information_technology_quarterly_dry_run;

