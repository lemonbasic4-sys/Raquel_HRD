USE raquel_hris;
DROP PROCEDURE IF EXISTS seed_ap_immediate_heads_quarterly_dry_run;
DELIMITER //
CREATE PROCEDURE seed_ap_immediate_heads_quarterly_dry_run()
BEGIN
  DECLARE v_template_id INT;
  DECLARE v_creator_id INT;
  DECLARE v_position_count INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  IF EXISTS (SELECT 1 FROM evaluation_templates WHERE form_code = 'AP-HEAD-Q-DRYRUN-20261115') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'AP immediate-head Quarterly form already exists; do not import twice';
  END IF;

  SELECT MIN(u.user_id) INTO v_creator_id
  FROM users u JOIN employees e ON e.employee_id = u.employee_id
  WHERE u.username = 'MO26-AP-M' AND e.department_id = 1
    AND e.job_title_id = 101 AND e.employment_status = 'Regular'
    AND e.is_active = 1 AND e.deleted_at IS NULL
    AND u.is_active = 1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold, 0) = 0;
  IF v_creator_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Active MO26-AP-M AP Manager I account is required';
  END IF;

  SELECT COUNT(*) INTO v_position_count FROM job_titles
  WHERE department_id = 1 AND job_title_id BETWEEN 105 AND 107
    AND reports_to = 101 AND is_active = 1;
  IF v_position_count <> 3 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Expected AP Supervisor I-III positions reporting to AP Manager I';
  END IF;

  START TRANSACTION;
  INSERT INTO evaluation_templates
    (template_name, description, target_department, target_department_id, target_job_title_id,
     behavior_framework_code, behavior_framework_version, evaluation_type, individual_workflow,
     assessment_from, assessment_to, kra_weight, behavior_weight, form_code,
     revision_date, effective_date_form, status, created_by)
  VALUES
    ('[DRY RUN] Quarterly - AP Immediate Heads - Main Office',
     'Dry-run AP Supervisor I-III form for Regular employees. Review documented supervision, control, and team delivery.',
     'Acquired Properties', 1, 105, 'QUARTERLY-CORE', '1', 'Quarterly', 1,
     '2026-11-15', '2027-02-14', 80, 20, 'AP-HEAD-Q-DRYRUN-20261115',
     '2026-11-15', '2026-11-15', 'Active', v_creator_id);
  SET v_template_id = LAST_INSERT_ID();

  INSERT INTO evaluation_template_positions (template_id, job_title_id)
  SELECT v_template_id, job_title_id FROM job_titles
  WHERE department_id = 1 AND job_title_id BETWEEN 105 AND 107
    AND reports_to = 101 AND is_active = 1;

  INSERT INTO evaluation_criteria
    (template_id, section, criterion_name, description, kpi_description, weight, scoring_method, sort_order)
  VALUES
    (v_template_id, 'KRA', 'Team work plan and delivery', 'Set clear weekly assignments for AP staff, monitor agreed deadlines, and document completion or escalation of delayed work.', 'Set clear weekly assignments for AP staff, monitor agreed deadlines, and document completion or escalation of delayed work.', 20, 'Scale_1_4', 1),
    (v_template_id, 'KRA', 'Inventory and custody control', 'Review property inventory reconciliations, verify handoff records and location changes, and resolve or escalate exceptions.', 'Review property inventory reconciliations, verify handoff records and location changes, and resolve or escalate exceptions.', 15, 'Scale_1_4', 2),
    (v_template_id, 'KRA', 'Document quality assurance', 'Check acquisition, title, valuation, and disposition files for completeness before forwarding them for required approval.', 'Check acquisition, title, valuation, and disposition files for completeness before forwarding them for required approval.', 15, 'Scale_1_4', 3),
    (v_template_id, 'KRA', 'Staff review and coaching', 'Review submitted AP staff work, give specific feedback, and record coaching or follow-up actions during the quarter.', 'Review submitted AP staff work, give specific feedback, and record coaching or follow-up actions during the quarter.', 15, 'Scale_1_4', 4),
    (v_template_id, 'KRA', 'Valuation and disposition coordination', 'Verify supporting evidence for valuation recommendations and coordinate approved sale, transfer, or release steps.', 'Verify supporting evidence for valuation recommendations and coordinate approved sale, transfer, or release steps.', 10, 'Scale_1_4', 5),
    (v_template_id, 'KRA', 'Risk and compliance escalation', 'Identify legal, custody, safety, or transaction concerns promptly and escalate them with supporting records.', 'Identify legal, custody, safety, or transaction concerns promptly and escalate them with supporting records.', 10, 'Scale_1_4', 6),
    (v_template_id, 'KRA', 'Cross-team coordination', 'Coordinate AP cases with the manager and relevant departments; communicate blockers and handoffs in time for decisions.', 'Coordinate AP cases with the manager and relevant departments; communicate blockers and handoffs in time for decisions.', 10, 'Scale_1_4', 7),
    (v_template_id, 'KRA', 'Management reporting', 'Submit concise, accurate AP work status and exception reports to the AP Manager by the agreed schedule.', 'Submit concise, accurate AP work status and exception reports to the AP Manager by the agreed schedule.', 5, 'Scale_1_4', 8),
    (v_template_id, 'Behavior', 'Positive Attitude', NULL, 'Displays positive attitude at work.', 0, 'Scale_1_4', 1),
    (v_template_id, 'Behavior', 'Respect', NULL, 'Shows respect to all people in the organization.', 0, 'Scale_1_4', 2),
    (v_template_id, 'Behavior', 'Accountability', NULL, 'Takes full responsibility of the job including special task or assignment.', 0, 'Scale_1_4', 3),
    (v_template_id, 'Behavior', 'Commitment', NULL, 'Demonstrates strong commitment to the job.', 0, 'Scale_1_4', 4),
    (v_template_id, 'Behavior', 'Teamwork', NULL, 'Works cooperatively with others in achieving the goals.', 0, 'Scale_1_4', 5),
    (v_template_id, 'Behavior', 'Integrity', NULL, 'Exhibits honesty and strong moral uprightness.', 0, 'Scale_1_4', 6),
    (v_template_id, 'Behavior', 'Continuous Improvement', NULL, 'Provides diligent effort to continuously focus on getting better.', 0, 'Scale_1_4', 7),
    (v_template_id, 'Behavior', 'Excellent Client Experience', NULL, 'Delivers the service beyond the expectations of the internal and external clients.', 0, 'Scale_1_4', 8);

  IF (SELECT COUNT(*) FROM evaluation_template_positions WHERE template_id = v_template_id) <> 3
     OR (SELECT COUNT(*) FROM evaluation_criteria WHERE template_id = v_template_id AND section = 'KRA') <> 8
     OR (SELECT COALESCE(SUM(weight), 0) FROM evaluation_criteria WHERE template_id = v_template_id AND section = 'KRA') <> 100
     OR (SELECT COUNT(*) FROM evaluation_criteria WHERE template_id = v_template_id AND section = 'Behavior') <> 8 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'AP immediate-head Quarterly form validation failed';
  END IF;
  COMMIT;
  SELECT v_template_id AS template_id, 'AP immediate-head Quarterly dry-run form imported' AS result;
END //
DELIMITER ;
CALL seed_ap_immediate_heads_quarterly_dry_run();
DROP PROCEDURE seed_ap_immediate_heads_quarterly_dry_run;
