-- FINANCE QUARTERLY DRY RUN
-- Base period: 2026-11-15 to 2027-02-14; all 18 Finance positions.
-- Accounting Staff post-probation period: 2027-04-15 to 2027-07-14.
-- Templates only. No scores, submissions, approvals, or assignments.
USE raquel_hris;
DROP PROCEDURE IF EXISTS seed_finance_quarterly_dry_run;
DELIMITER //
CREATE PROCEDURE seed_finance_quarterly_dry_run()
BEGIN
  DECLARE v_ceo INT;
  DECLARE v_vp INT;
  DECLARE v_manager INT;
  DECLARE v_acc_head INT;
  DECLARE v_vp_form INT;
  DECLARE v_manager_form INT;
  DECLARE v_acc_sup_form INT;
  DECLARE v_treas_sup_form INT;
  DECLARE v_acc_staff_form INT;
  DECLARE v_treas_staff_form INT;
  DECLARE v_acc_staff_2027_form INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  IF EXISTS (SELECT 1 FROM evaluation_templates WHERE form_code IN
    ('FI-VP-Q-DRYRUN-20261115','FI-MGR-Q-DRYRUN-20261115',
     'FI-ACC-SUP-Q-DRYRUN-20261115','FI-TREAS-SUP-Q-DRYRUN-20261115',
     'FI-ACC-STAFF-Q-DRYRUN-20261115','FI-TREAS-STAFF-Q-DRYRUN-20261115',
     'FI-ACC-STAFF-Q-DRYRUN-20270415')) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Finance Quarterly form already exists; do not import twice';
  END IF;
  IF (SELECT COUNT(*) FROM job_titles WHERE department_id=5 AND job_title_id BETWEEN 500 AND 517 AND is_active=1)<>18
     OR NOT EXISTS (SELECT 1 FROM job_titles WHERE job_title_id=500 AND reports_to=1100 AND is_active=1)
     OR NOT EXISTS (SELECT 1 FROM job_titles WHERE job_title_id=501 AND reports_to=500 AND is_active=1)
     OR (SELECT COUNT(*) FROM job_titles WHERE department_id=5 AND job_title_id BETWEEN 502 AND 505 AND reports_to=501 AND is_active=1)<>4
     OR (SELECT COUNT(*) FROM job_titles WHERE department_id=5 AND job_title_id BETWEEN 506 AND 510 AND reports_to=500 AND is_active=1)<>5
     OR (SELECT COUNT(*) FROM job_titles WHERE department_id=5 AND job_title_id BETWEEN 511 AND 515 AND reports_to=502 AND is_active=1)<>5
     OR (SELECT COUNT(*) FROM job_titles WHERE department_id=5 AND job_title_id BETWEEN 516 AND 517 AND reports_to=506 AND is_active=1)<>2 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Expected all 18 Finance positions and reporting lines';
  END IF;

  SELECT MIN(u.user_id) INTO v_ceo FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='CEO-001' AND e.job_title_id=1100 AND e.is_active=1 AND e.deleted_at IS NULL
    AND u.role='President and CEO' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0;
  SELECT MIN(u.user_id) INTO v_vp FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='MO26-FI-V' AND e.department_id=5 AND e.job_title_id=500 AND e.employment_status='Regular'
    AND e.is_active=1 AND e.deleted_at IS NULL AND u.role='Employee' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0;
  SELECT MIN(u.user_id) INTO v_manager FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='MO26-FI-M' AND e.department_id=5 AND e.job_title_id=501 AND e.employment_status='Regular'
    AND e.is_active=1 AND e.deleted_at IS NULL AND u.role='Employee' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0;
  SELECT MIN(u.user_id) INTO v_acc_head FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='MO26-FI-H' AND e.department_id=5 AND e.job_title_id=502 AND e.employment_status='Regular'
    AND e.is_active=1 AND e.deleted_at IS NULL AND u.role='Employee' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0;
  IF v_ceo IS NULL OR v_vp IS NULL OR v_manager IS NULL OR v_acc_head IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Active CEO, Finance VP, Manager, and Accounting Supervisor portal accounts are required';
  END IF;

  START TRANSACTION;
  -- Six role groups cover every Finance position. Treasury Staff form is
  -- created by the Finance VP until a Treasury Supervisor portal user exists.
  INSERT INTO evaluation_templates
    (template_name,description,target_department,target_department_id,target_job_title_id,
     behavior_framework_code,behavior_framework_version,evaluation_type,individual_workflow,
     assessment_from,assessment_to,kra_weight,behavior_weight,form_code,
     revision_date,effective_date_form,status,created_by)
  VALUES
    ('[DRY RUN] Quarterly - VP for Finance','Financial stewardship, control, and executive reporting.',
     'Finance',5,500,'QUARTERLY-CORE','1','Quarterly',1,'2026-11-15','2027-02-14',80,20,
     'FI-VP-Q-DRYRUN-20261115','2026-11-17','2026-11-17','Active',v_ceo),
    ('[DRY RUN] Quarterly - Accounting Manager I','Accounting close, reporting quality, and team leadership.',
     'Finance',5,501,'QUARTERLY-CORE','1','Quarterly',1,'2026-11-15','2027-02-14',80,20,
     'FI-MGR-Q-DRYRUN-20261115','2026-11-17','2026-11-17','Active',v_vp),
    ('[DRY RUN] Quarterly - Accounting Supervisors I-IV','Ledger review, close controls, and staff coaching.',
     'Finance',5,502,'QUARTERLY-CORE','1','Quarterly',1,'2026-11-15','2027-02-14',80,20,
     'FI-ACC-SUP-Q-DRYRUN-20261115','2026-11-17','2026-11-17','Active',v_manager),
    ('[DRY RUN] Quarterly - Treasury Supervisors I-V','Cash control, payment review, and liquidity reporting.',
     'Finance',5,506,'QUARTERLY-CORE','1','Quarterly',1,'2026-11-15','2027-02-14',80,20,
     'FI-TREAS-SUP-Q-DRYRUN-20261115','2026-11-17','2026-11-17','Active',v_vp),
    ('[DRY RUN] Quarterly - Accounting Staff I-V','Transaction accuracy, reconciliations, and supporting records.',
     'Finance',5,511,'QUARTERLY-CORE','1','Quarterly',1,'2026-11-15','2027-02-14',80,20,
     'FI-ACC-STAFF-Q-DRYRUN-20261115','2026-11-17','2026-11-17','Active',v_acc_head),
    ('[DRY RUN] Quarterly - Treasury Staff I-II','Cash records, payment support, and custody controls.',
     'Finance',5,516,'QUARTERLY-CORE','1','Quarterly',1,'2026-11-15','2027-02-14',80,20,
     'FI-TREAS-STAFF-Q-DRYRUN-20261115','2026-11-17','2026-11-17','Active',v_vp);
  SELECT template_id INTO v_vp_form FROM evaluation_templates WHERE form_code='FI-VP-Q-DRYRUN-20261115';
  SELECT template_id INTO v_manager_form FROM evaluation_templates WHERE form_code='FI-MGR-Q-DRYRUN-20261115';
  SELECT template_id INTO v_acc_sup_form FROM evaluation_templates WHERE form_code='FI-ACC-SUP-Q-DRYRUN-20261115';
  SELECT template_id INTO v_treas_sup_form FROM evaluation_templates WHERE form_code='FI-TREAS-SUP-Q-DRYRUN-20261115';
  SELECT template_id INTO v_acc_staff_form FROM evaluation_templates WHERE form_code='FI-ACC-STAFF-Q-DRYRUN-20261115';
  SELECT template_id INTO v_treas_staff_form FROM evaluation_templates WHERE form_code='FI-TREAS-STAFF-Q-DRYRUN-20261115';

  INSERT INTO evaluation_template_positions (template_id,job_title_id)
  SELECT CASE WHEN job_title_id=500 THEN v_vp_form WHEN job_title_id=501 THEN v_manager_form
              WHEN job_title_id BETWEEN 502 AND 505 THEN v_acc_sup_form
              WHEN job_title_id BETWEEN 506 AND 510 THEN v_treas_sup_form
              WHEN job_title_id BETWEEN 511 AND 515 THEN v_acc_staff_form ELSE v_treas_staff_form END,job_title_id
  FROM job_titles WHERE department_id=5 AND job_title_id BETWEEN 500 AND 517 AND is_active=1;

  -- Eight role-specific KRAs per form, totaling 100% within KRA section.
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  VALUES
    (v_vp_form,'KRA','Financial plan and priorities','Set approved financial priorities and monitor material variances.','Financial plan and variance reviews.',20,'Scale_1_4',1),
    (v_vp_form,'KRA','Reporting integrity','Ensure financial reports are accurate and supported before executive use.','Reviewed financial reports.',15,'Scale_1_4',2),
    (v_vp_form,'KRA','Liquidity oversight','Monitor cash needs and funding risks with documented actions.','Liquidity forecasts and decisions.',15,'Scale_1_4',3),
    (v_vp_form,'KRA','Internal controls','Direct remediation of material accounting and treasury control gaps.','Control actions and closure evidence.',15,'Scale_1_4',4),
    (v_vp_form,'KRA','Budget stewardship','Review spending against approved budgets and authorize needed escalations.','Budget variance decisions.',10,'Scale_1_4',5),
    (v_vp_form,'KRA','Manager leadership','Review Finance manager delivery and development actions.','Manager review records.',10,'Scale_1_4',6),
    (v_vp_form,'KRA','Compliance oversight','Track finance-related statutory and policy obligations.','Compliance action register.',10,'Scale_1_4',7),
    (v_vp_form,'KRA','Executive advice','Provide timely financial analysis for material decisions.','Documented executive advice.',5,'Scale_1_4',8),
    (v_manager_form,'KRA','Close management','Plan and complete accounting close tasks with accountable owners.','On-time close checklist.',20,'Scale_1_4',1),
    (v_manager_form,'KRA','Ledger integrity','Review material balances and adjustments for support and accuracy.','Reviewed ledger and journal records.',15,'Scale_1_4',2),
    (v_manager_form,'KRA','Financial statements','Prepare accurate management and statutory reporting inputs.','Complete reviewed reporting schedules.',15,'Scale_1_4',3),
    (v_manager_form,'KRA','Reconciliation control','Ensure key accounts are reconciled and exceptions resolved.','Signed reconciliations and actions.',15,'Scale_1_4',4),
    (v_manager_form,'KRA','Supervisor coaching','Review Accounting supervisor work and document feedback.','Review and coaching records.',10,'Scale_1_4',5),
    (v_manager_form,'KRA','Audit support','Provide traceable records and resolve audit findings on schedule.','Evidence and issue closure records.',10,'Scale_1_4',6),
    (v_manager_form,'KRA','Policy compliance','Apply accounting policies and escalate material exceptions.','Documented compliance decisions.',10,'Scale_1_4',7),
    (v_manager_form,'KRA','VP reporting','Report close status, issues, and financial insights on time.','Accurate scheduled reports.',5,'Scale_1_4',8),
    (v_acc_sup_form,'KRA','Close task delivery','Assign and monitor accounting tasks against the close calendar.','Completed task tracker.',20,'Scale_1_4',1),
    (v_acc_sup_form,'KRA','Journal review','Check entries for classification, approval, and supporting evidence.','Reviewed journal records.',15,'Scale_1_4',2),
    (v_acc_sup_form,'KRA','Reconciliation review','Review account reconciliations and clear material differences.','Signed reconciliation review.',15,'Scale_1_4',3),
    (v_acc_sup_form,'KRA','Document quality','Check invoices, schedules, and ledgers for completeness.','Complete accounting support files.',15,'Scale_1_4',4),
    (v_acc_sup_form,'KRA','Staff coaching','Give specific feedback and follow through on repeated errors.','Staff review and coaching notes.',10,'Scale_1_4',5),
    (v_acc_sup_form,'KRA','Control exceptions','Escalate unsupported or unusual transactions promptly.','Exception and resolution log.',10,'Scale_1_4',6),
    (v_acc_sup_form,'KRA','Cross-team coordination','Resolve required inputs with Treasury and other departments.','Documented handoffs.',10,'Scale_1_4',7),
    (v_acc_sup_form,'KRA','Manager reporting','Submit accurate close and issue updates on time.','Scheduled status reports.',5,'Scale_1_4',8),
    (v_treas_sup_form,'KRA','Cash position review','Monitor bank balances, cash needs, and shortfalls.','Reviewed cash position reports.',20,'Scale_1_4',1),
    (v_treas_sup_form,'KRA','Payment control','Review payments against approvals and supporting documents.','Compliant approved payments.',15,'Scale_1_4',2),
    (v_treas_sup_form,'KRA','Bank reconciliation','Review bank movements and resolve unmatched items.','Signed reconciliation review.',15,'Scale_1_4',3),
    (v_treas_sup_form,'KRA','Fund custody','Protect cash instruments, access, and transfer records.','Custody and access records.',15,'Scale_1_4',4),
    (v_treas_sup_form,'KRA','Liquidity forecasting','Provide documented short-term cash forecasts and variance notes.','Forecast reports and actions.',10,'Scale_1_4',5),
    (v_treas_sup_form,'KRA','Staff oversight','Review Treasury staff outputs and coach on exceptions.','Staff review notes.',10,'Scale_1_4',6),
    (v_treas_sup_form,'KRA','Fraud risk escalation','Escalate unusual transactions and control breaches promptly.','Incident and escalation records.',10,'Scale_1_4',7),
    (v_treas_sup_form,'KRA','VP reporting','Report cash risks and payment status on schedule.','Accurate scheduled treasury reports.',5,'Scale_1_4',8),
    (v_acc_staff_form,'KRA','Transaction recording','Record assigned transactions correctly and on time.','Accurate posted transactions.',20,'Scale_1_4',1),
    (v_acc_staff_form,'KRA','Supporting documents','Attach complete approvals and source records to entries.','Traceable transaction files.',15,'Scale_1_4',2),
    (v_acc_staff_form,'KRA','Account reconciliations','Prepare assigned reconciliations and explain differences.','Complete reconciliations.',15,'Scale_1_4',3),
    (v_acc_staff_form,'KRA','Close deadlines','Submit assigned schedules by the agreed close calendar.','On-time close outputs.',15,'Scale_1_4',4),
    (v_acc_staff_form,'KRA','Accuracy checks','Review calculations, coding, and dates before submission.','Low correction rate and review notes.',10,'Scale_1_4',5),
    (v_acc_staff_form,'KRA','Control compliance','Follow approval and segregation-of-duties rules.','Compliant processing and escalations.',10,'Scale_1_4',6),
    (v_acc_staff_form,'KRA','Service response','Respond to assigned finance queries with supporting details.','Timely documented responses.',10,'Scale_1_4',7),
    (v_acc_staff_form,'KRA','Feedback application','Resolve review comments and apply corrections in later work.','Closed review notes.',5,'Scale_1_4',8),
    (v_treas_staff_form,'KRA','Cash record accuracy','Record assigned cash and bank activity accurately.','Accurate cash records.',20,'Scale_1_4',1),
    (v_treas_staff_form,'KRA','Payment preparation','Prepare complete payment files with required approvals.','Reviewed payment packets.',15,'Scale_1_4',2),
    (v_treas_staff_form,'KRA','Bank documentation','Maintain complete bank advice, deposit, and transfer records.','Traceable bank files.',15,'Scale_1_4',3),
    (v_treas_staff_form,'KRA','Reconciliation support','Match assigned bank and cash items and explain exceptions.','Resolved matching items.',15,'Scale_1_4',4),
    (v_treas_staff_form,'KRA','Custody and access','Follow cash, instrument, and system-access controls.','Compliant custody records.',10,'Scale_1_4',5),
    (v_treas_staff_form,'KRA','Deadline management','Complete payment and cash reports within approved cutoffs.','On-time treasury outputs.',10,'Scale_1_4',6),
    (v_treas_staff_form,'KRA','Exception escalation','Report unusual transactions or missing support promptly.','Documented exception reports.',10,'Scale_1_4',7),
    (v_treas_staff_form,'KRA','Feedback application','Close review comments and improve subsequent records.','Closed review notes.',5,'Scale_1_4',8);

  -- Core Behaviors contribute 20% overall; copy identical items to all roles.
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  VALUES
    (v_vp_form,'Behavior','Positive Attitude',NULL,'Displays positive attitude at work.',0,'Scale_1_4',1),
    (v_vp_form,'Behavior','Respect',NULL,'Shows respect to all people in the organization.',0,'Scale_1_4',2),
    (v_vp_form,'Behavior','Accountability',NULL,'Takes responsibility for assigned work.',0,'Scale_1_4',3),
    (v_vp_form,'Behavior','Commitment',NULL,'Demonstrates commitment to the job.',0,'Scale_1_4',4),
    (v_vp_form,'Behavior','Teamwork',NULL,'Works cooperatively with others.',0,'Scale_1_4',5),
    (v_vp_form,'Behavior','Integrity',NULL,'Acts honestly and ethically.',0,'Scale_1_4',6),
    (v_vp_form,'Behavior','Continuous Improvement',NULL,'Works consistently to improve.',0,'Scale_1_4',7),
    (v_vp_form,'Behavior','Excellent Client Experience',NULL,'Serves internal and external clients well.',0,'Scale_1_4',8);
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  SELECT target.template_id,c.section,c.criterion_name,c.description,c.kpi_description,c.weight,c.scoring_method,c.sort_order
  FROM evaluation_criteria c CROSS JOIN
    (SELECT v_manager_form AS template_id UNION ALL SELECT v_acc_sup_form UNION ALL SELECT v_treas_sup_form
     UNION ALL SELECT v_acc_staff_form UNION ALL SELECT v_treas_staff_form) target
  WHERE c.template_id=v_vp_form AND c.section='Behavior';

  -- Post-probation Accounting Staff cycle for Carlo and Alyssa once Regular.
  INSERT INTO evaluation_templates
    (template_name,description,target_department,target_department_id,target_job_title_id,
     behavior_framework_code,behavior_framework_version,evaluation_type,individual_workflow,
     assessment_from,assessment_to,kra_weight,behavior_weight,form_code,
     revision_date,effective_date_form,status,created_by)
  VALUES
    ('[DRY RUN] Quarterly - Accounting Staff - Post-Probation 2027',
     'Regular Accounting Staff I-V review after probation.',
     'Finance',5,511,'QUARTERLY-CORE','1','Quarterly',1,'2027-04-15','2027-07-14',80,20,
     'FI-ACC-STAFF-Q-DRYRUN-20270415','2026-11-17','2027-04-15','Active',v_acc_head);
  SET v_acc_staff_2027_form=LAST_INSERT_ID();
  INSERT INTO evaluation_template_positions (template_id,job_title_id)
  SELECT v_acc_staff_2027_form,job_title_id FROM job_titles
  WHERE department_id=5 AND job_title_id BETWEEN 511 AND 515 AND is_active=1;
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  SELECT v_acc_staff_2027_form,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order
  FROM evaluation_criteria WHERE template_id=v_acc_staff_form;

  IF (SELECT COUNT(*) FROM evaluation_template_positions WHERE template_id IN
      (v_vp_form,v_manager_form,v_acc_sup_form,v_treas_sup_form,v_acc_staff_form,v_treas_staff_form,v_acc_staff_2027_form))<>23
     OR EXISTS (SELECT 1 FROM evaluation_templates t WHERE t.template_id IN
       (v_vp_form,v_manager_form,v_acc_sup_form,v_treas_sup_form,v_acc_staff_form,v_treas_staff_form,v_acc_staff_2027_form)
       AND ((SELECT COUNT(*) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='KRA')<>8
        OR (SELECT COALESCE(SUM(c.weight),0) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='KRA')<>100
        OR (SELECT COUNT(*) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='Behavior')<>8)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Finance Quarterly position or criteria validation failed';
  END IF;
  COMMIT;
  SELECT 'Finance Quarterly forms imported' AS result,7 AS forms,18 AS covered_positions;
END //
DELIMITER ;
CALL seed_finance_quarterly_dry_run();
DROP PROCEDURE seed_finance_quarterly_dry_run;
