-- ACQUIRED PROPERTIES ANNUAL DRY RUN - 2026
-- One file, five forms, all active AP positions (job_title_id 100-114).
-- VP: 100 | Managers: 101-104 | Supervisors: 105-107
-- AP Staff: 108-112 | Sales Associates: 113-114
-- Annual assessment: 2026-01-01 to 2026-12-31. The portal starts each
-- employee's actual period on the later of Jan 1 or regularization date.
-- Templates only: no employee scores, submissions, or approvals are inserted.
-- Run after organization and employee seeds; no portal accounts needed for import. Reimport fails.
USE raquel_hris;
DROP PROCEDURE IF EXISTS seed_ap_department_annual_dry_run;
DELIMITER //
CREATE PROCEDURE seed_ap_department_annual_dry_run()
BEGIN
  DECLARE v_ceo INT;
  DECLARE v_vp INT;
  DECLARE v_manager INT;
  DECLARE v_supervisor INT;
  DECLARE v_vp_form INT;
  DECLARE v_manager_form INT;
  DECLARE v_supervisor_form INT;
  DECLARE v_staff_form INT;
  DECLARE v_sales_form INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  -- Stop before inserting anything if any form from this bundle exists.
  IF EXISTS (SELECT 1 FROM evaluation_templates WHERE form_code IN
    ('AP-VP-A-DRYRUN-2026','AP-MGR-A-DRYRUN-2026','AP-SUP-A-DRYRUN-2026',
     'AP-STAFF-A-DRYRUN-2026','AP-SALES-A-DRYRUN-2026')) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'AP Annual 2026 form already exists; do not import twice';
  END IF;

  IF (SELECT COUNT(*) FROM job_titles WHERE department_id=1 AND is_active=1
      AND job_title_id BETWEEN 100 AND 114) <> 15
     OR EXISTS (SELECT 1 FROM job_titles WHERE department_id=1 AND is_active=1
                AND job_title_id BETWEEN 101 AND 104 AND reports_to<>100)
     OR EXISTS (SELECT 1 FROM job_titles WHERE department_id=1 AND is_active=1
                AND job_title_id BETWEEN 105 AND 107 AND reports_to<>101)
     OR EXISTS (SELECT 1 FROM job_titles WHERE department_id=1 AND is_active=1
                AND job_title_id BETWEEN 108 AND 114 AND reports_to<>105) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Expected all 15 active AP positions and reporting lines';
  END IF;

  -- Creator accounts follow the AP reporting line.
  SELECT MIN(u.user_id) INTO v_ceo FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='CEO-001' AND e.job_title_id=1100 AND e.is_active=1 AND e.deleted_at IS NULL
    AND u.role='President and CEO' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0;
  SELECT MIN(u.user_id) INTO v_vp FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='AP-001' AND e.department_id=1 AND e.job_title_id=100
    AND e.employment_status='Regular' AND e.is_active=1 AND e.deleted_at IS NULL
    AND u.role='Employee' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0;
  SELECT MIN(u.user_id) INTO v_manager FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='MO26-AP-M' AND e.department_id=1 AND e.job_title_id=101
    AND e.employment_status='Regular' AND e.is_active=1 AND e.deleted_at IS NULL
    AND u.role='Employee' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0;
  SELECT MIN(u.user_id) INTO v_supervisor FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code='MO26-AP-H' AND e.department_id=1 AND e.job_title_id=105
    AND e.employment_status='Regular' AND e.is_active=1 AND e.deleted_at IS NULL
    AND u.role='Employee' AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0;


  START TRANSACTION;
  -- 1. Five Annual forms share the year and 80/20 scoring split.
  INSERT INTO evaluation_templates
    (template_name,description,target_department,target_department_id,target_job_title_id,
     behavior_framework_code,behavior_framework_version,evaluation_type,individual_workflow,
     assessment_from,assessment_to,kra_weight,behavior_weight,form_code,
     revision_date,effective_date_form,status,created_by)
  VALUES
    ('[DRY RUN] Annual - VP for Acquired Properties - 2026','AP portfolio strategy and executive stewardship.',
     'Acquired Properties',1,100,'ANNUAL-CORE','1','Annual',1,'2026-01-01','2026-12-31',80,20,
     'AP-VP-A-DRYRUN-2026','2026-11-17','2026-11-17','Active',v_ceo),
    ('[DRY RUN] Annual - AP Managers - 2026','AP operational leadership and portfolio execution.',
     'Acquired Properties',1,101,'ANNUAL-CORE','1','Annual',1,'2026-01-01','2026-12-31',80,20,
     'AP-MGR-A-DRYRUN-2026','2026-11-17','2026-11-17','Active',v_vp),
    ('[DRY RUN] Annual - AP Supervisors - 2026','AP team supervision, controls, and delivery.',
     'Acquired Properties',1,105,'ANNUAL-CORE','1','Annual',1,'2026-01-01','2026-12-31',80,20,
     'AP-SUP-A-DRYRUN-2026','2026-11-17','2026-11-17','Active',v_manager),
    ('[DRY RUN] Annual - AP Staff - 2026','AP casework, records, controls, and service delivery.',
     'Acquired Properties',1,108,'ANNUAL-CORE','1','Annual',1,'2026-01-01','2026-12-31',80,20,
     'AP-STAFF-A-DRYRUN-2026','2026-11-17','2026-11-17','Active',v_supervisor),
    ('[DRY RUN] Annual - AP Sales Associates - 2026','Approved property sale support and customer handling.',
     'Acquired Properties',1,113,'ANNUAL-CORE','1','Annual',1,'2026-01-01','2026-12-31',80,20,
     'AP-SALES-A-DRYRUN-2026','2026-11-17','2026-11-17','Active',v_supervisor);

  SELECT template_id INTO v_vp_form FROM evaluation_templates WHERE form_code='AP-VP-A-DRYRUN-2026';
  SELECT template_id INTO v_manager_form FROM evaluation_templates WHERE form_code='AP-MGR-A-DRYRUN-2026';
  SELECT template_id INTO v_supervisor_form FROM evaluation_templates WHERE form_code='AP-SUP-A-DRYRUN-2026';
  SELECT template_id INTO v_staff_form FROM evaluation_templates WHERE form_code='AP-STAFF-A-DRYRUN-2026';
  SELECT template_id INTO v_sales_form FROM evaluation_templates WHERE form_code='AP-SALES-A-DRYRUN-2026';

  -- 2. Exact position coverage; no overlapping forms.
  INSERT INTO evaluation_template_positions (template_id,job_title_id)
  SELECT CASE
    WHEN job_title_id=100 THEN v_vp_form
    WHEN job_title_id BETWEEN 101 AND 104 THEN v_manager_form
    WHEN job_title_id BETWEEN 105 AND 107 THEN v_supervisor_form
    WHEN job_title_id BETWEEN 108 AND 112 THEN v_staff_form
    ELSE v_sales_form END, job_title_id
  FROM job_titles WHERE department_id=1 AND is_active=1 AND job_title_id BETWEEN 100 AND 114;

  -- 3. Role-specific KRAs. Each section totals 100%; the template applies 80% overall.
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  VALUES
    -- VP: portfolio direction and executive accountability.
    (v_vp_form,'KRA','Portfolio strategy','Set annual AP portfolio targets, priorities, and risk actions with documented executive review.','Approved annual portfolio plan and progress reviews.',20,'Scale_1_4',1),
    (v_vp_form,'KRA','Disposition and recovery results','Lead approved property disposition and recovery targets; explain material variances.','Annual disposition and recovery results against approved targets.',15,'Scale_1_4',2),
    (v_vp_form,'KRA','Valuation and acquisition governance','Ensure material valuations and acquisition proposals have evidence and required approvals.','Documented decisions and approval trails.',15,'Scale_1_4',3),
    (v_vp_form,'KRA','Legal and custody risk oversight','Direct resolution of high-risk title, litigation, custody, and control exceptions.','Risk register with accountable owners and closures.',15,'Scale_1_4',4),
    (v_vp_form,'KRA','Budget stewardship','Manage AP portfolio costs within approved budgets or obtain variance approval.','Budget reports and approved variance actions.',10,'Scale_1_4',5),
    (v_vp_form,'KRA','Manager leadership','Review AP manager performance and follow through on documented development actions.','Completed manager reviews and follow-up records.',10,'Scale_1_4',6),
    (v_vp_form,'KRA','Compliance governance','Close due regulatory and policy actions with evidence.','Compliance action tracking and closure evidence.',10,'Scale_1_4',7),
    (v_vp_form,'KRA','Executive reporting','Submit accurate portfolio results and decision requests on schedule.','Timely executive reports with supporting data.',5,'Scale_1_4',8),
    -- Managers: operational planning and control.
    (v_manager_form,'KRA','Annual work plan delivery','Translate portfolio goals into team targets and track completion across the year.','Annual team plan, milestones, and results.',20,'Scale_1_4',1),
    (v_manager_form,'KRA','Inventory and custody control','Oversee reconciliations, custody records, and resolution of material exceptions.','Reconciliation reviews and exception closure.',15,'Scale_1_4',2),
    (v_manager_form,'KRA','Disposition execution','Deliver approved disposition and recovery actions with complete records.','Results against approved disposition commitments.',15,'Scale_1_4',3),
    (v_manager_form,'KRA','Valuation and file quality','Review valuation support, title files, and transaction documents before endorsement.','Complete and supported AP case files.',15,'Scale_1_4',4),
    (v_manager_form,'KRA','Supervisor development','Set expectations, review supervisor results, and document coaching.','Supervisor review and coaching records.',10,'Scale_1_4',5),
    (v_manager_form,'KRA','Risk and compliance','Track AP legal, policy, and control risks with owners and due dates.','Risk actions and closure evidence.',10,'Scale_1_4',6),
    (v_manager_form,'KRA','Budget and resources','Monitor AP spending and staffing within approved plans.','Budget variances and resource actions.',10,'Scale_1_4',7),
    (v_manager_form,'KRA','VP reporting','Provide timely AP status reports and escalations to the VP.','Reports and decision requests delivered on time.',5,'Scale_1_4',8),
    -- Supervisors: team execution and frontline review.
    (v_supervisor_form,'KRA','Team assignment delivery','Assign AP work, monitor deadlines, and document delayed items.','Completed work plan and escalations.',20,'Scale_1_4',1),
    (v_supervisor_form,'KRA','Inventory and custody review','Check inventory reconciliations and property handoff records.','Reviewed custody records and exception actions.',15,'Scale_1_4',2),
    (v_supervisor_form,'KRA','Document quality review','Check AP case files for completeness before manager approval.','Documented file reviews and corrections.',15,'Scale_1_4',3),
    (v_supervisor_form,'KRA','Staff coaching','Review staff output and document coaching or follow-up.','Staff feedback and follow-up records.',15,'Scale_1_4',4),
    (v_supervisor_form,'KRA','Disposition coordination','Coordinate approved valuation, sale, transfer, and release steps.','Timely documented handoffs.',10,'Scale_1_4',5),
    (v_supervisor_form,'KRA','Risk escalation','Escalate legal, custody, safety, and transaction concerns with evidence.','Timely exception reports.',10,'Scale_1_4',6),
    (v_supervisor_form,'KRA','Cross-team coordination','Coordinate AP cases and blockers with the manager and other teams.','Documented coordination and resolution.',10,'Scale_1_4',7),
    (v_supervisor_form,'KRA','Management reporting','Submit accurate staff and case status updates as scheduled.','On-time status reports.',5,'Scale_1_4',8),
    -- Staff: AP casework and records.
    (v_staff_form,'KRA','Work plan delivery','Complete assigned AP casework by agreed deadlines.','Completed assignments and documented outputs.',20,'Scale_1_4',1),
    (v_staff_form,'KRA','Property record accuracy','Maintain accurate asset details and update approved changes promptly.','Accurate property records.',15,'Scale_1_4',2),
    (v_staff_form,'KRA','Custody documentation','Record property transfers, releases, and custody changes with required evidence.','Complete custody and handoff records.',15,'Scale_1_4',3),
    (v_staff_form,'KRA','Case file completeness','Keep title, valuation, acquisition, and disposition files complete.','Traceable and complete case files.',15,'Scale_1_4',4),
    (v_staff_form,'KRA','Control compliance','Follow required AP procedures and escalate exceptions.','Documented compliance and escalations.',10,'Scale_1_4',5),
    (v_staff_form,'KRA','Service response','Acknowledge assigned internal and client requests on time.','Timely recorded responses.',10,'Scale_1_4',6),
    (v_staff_form,'KRA','Team coordination','Communicate blockers and handoffs before deadlines.','Clear handoff records.',10,'Scale_1_4',7),
    (v_staff_form,'KRA','Learning and improvement','Apply documented feedback and complete assigned role training.','Training and improvement evidence.',5,'Scale_1_4',8),
    -- Sales Associates: property sales support, not generic casework.
    (v_sales_form,'KRA','Approved property presentation','Present approved properties accurately using current authorized terms.','Accurate approved property information shared.',20,'Scale_1_4',1),
    (v_sales_form,'KRA','Lead and inquiry follow-up','Record and follow up qualified buyer inquiries within agreed time frames.','Documented lead follow-up and outcomes.',15,'Scale_1_4',2),
    (v_sales_form,'KRA','Transaction document accuracy','Prepare complete buyer and property documents for review and approval.','Complete and accurate transaction files.',15,'Scale_1_4',3),
    (v_sales_form,'KRA','Approved sales execution','Support sales only within approved price, release, and payment controls.','Compliant sales and release records.',15,'Scale_1_4',4),
    (v_sales_form,'KRA','Customer communication','Provide clear and timely updates to interested buyers and internal teams.','Recorded responses and handoffs.',10,'Scale_1_4',5),
    (v_sales_form,'KRA','Property viewing coordination','Arrange approved viewings and document property condition and custody handoffs.','Viewing logs and custody records.',10,'Scale_1_4',6),
    (v_sales_form,'KRA','Sales reporting','Submit accurate pipeline and transaction status reports on schedule.','Timely and accurate sales reports.',10,'Scale_1_4',7),
    (v_sales_form,'KRA','Policy and ethics','Follow customer, privacy, conflict, and anti-fraud requirements.','Documented compliance and escalations.',5,'Scale_1_4',8);

  -- 4. Same eight Core Behaviors in every role-specific form.
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  VALUES
    (v_vp_form,'Behavior','Positive Attitude',NULL,'Displays positive attitude at work.',0,'Scale_1_4',1),
    (v_vp_form,'Behavior','Respect',NULL,'Shows respect to all people in the organization.',0,'Scale_1_4',2),
    (v_vp_form,'Behavior','Accountability',NULL,'Takes full responsibility for assigned work.',0,'Scale_1_4',3),
    (v_vp_form,'Behavior','Commitment',NULL,'Demonstrates commitment to the job.',0,'Scale_1_4',4),
    (v_vp_form,'Behavior','Teamwork',NULL,'Works cooperatively with others.',0,'Scale_1_4',5),
    (v_vp_form,'Behavior','Integrity',NULL,'Acts honestly and ethically.',0,'Scale_1_4',6),
    (v_vp_form,'Behavior','Continuous Improvement',NULL,'Works consistently to improve.',0,'Scale_1_4',7),
    (v_vp_form,'Behavior','Excellent Client Experience',NULL,'Serves internal and external clients well.',0,'Scale_1_4',8);
  INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
  SELECT target.template_id,c.section,c.criterion_name,c.description,c.kpi_description,c.weight,c.scoring_method,c.sort_order
  FROM evaluation_criteria c
  CROSS JOIN (
    SELECT v_manager_form AS template_id UNION ALL SELECT v_supervisor_form
    UNION ALL SELECT v_staff_form UNION ALL SELECT v_sales_form
  ) target
  WHERE c.template_id=v_vp_form AND c.section='Behavior';

  -- 5. Check every position and every form before commit.
  IF (SELECT COUNT(*) FROM evaluation_template_positions WHERE template_id IN
      (v_vp_form,v_manager_form,v_supervisor_form,v_staff_form,v_sales_form)) <> 15
     OR EXISTS (
       SELECT 1 FROM evaluation_templates t WHERE t.template_id IN
         (v_vp_form,v_manager_form,v_supervisor_form,v_staff_form,v_sales_form)
       AND ((SELECT COUNT(*) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='KRA')<>8
         OR (SELECT COALESCE(SUM(c.weight),0) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='KRA')<>100
         OR (SELECT COUNT(*) FROM evaluation_criteria c WHERE c.template_id=t.template_id AND c.section='Behavior')<>8)
     ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'AP Annual position or criteria validation failed';
  END IF;

  -- BEGIN SEEDED OWNERS
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'AP-VP-A-DRYRUN-2026',employee_id,'President and CEO' FROM employees WHERE employee_code='CEO-001'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'AP-MGR-A-DRYRUN-2026',employee_id,'Employee' FROM employees WHERE employee_code='AP-001'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'AP-SUP-A-DRYRUN-2026',employee_id,'Employee' FROM employees WHERE employee_code='MO26-AP-M'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'AP-STAFF-A-DRYRUN-2026',employee_id,'Employee' FROM employees WHERE employee_code='MO26-AP-H'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'AP-SALES-A-DRYRUN-2026',employee_id,'Employee' FROM employees WHERE employee_code='MO26-AP-H'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  UPDATE evaluation_templates t JOIN evaluation_seed_template_owners o ON o.form_code=t.form_code
  SET t.created_by=(SELECT MIN(u.user_id) FROM users u WHERE u.employee_id=o.employee_id
    AND (u.role=o.preferred_role OR (o.preferred_role='Employee' AND u.role IN ('HR Manager','HR Supervisor','HR Staff')))
    AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0)
  WHERE t.created_by IS NULL;
  -- END SEEDED OWNERS
  COMMIT;
  SELECT 'AP Annual 2026 dry-run forms imported' AS result, 5 AS forms, 15 AS covered_positions;
END //
DELIMITER ;
CALL seed_ap_department_annual_dry_run();
DROP PROCEDURE seed_ap_department_annual_dry_run;
