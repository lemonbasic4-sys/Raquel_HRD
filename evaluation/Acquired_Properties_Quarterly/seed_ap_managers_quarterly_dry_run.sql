USE raquel_hris;
DROP PROCEDURE IF EXISTS seed_ap_managers_quarterly_dry_run;
DELIMITER //
CREATE PROCEDURE seed_ap_managers_quarterly_dry_run()
BEGIN
  DECLARE v_template_id INT;
  DECLARE v_creator_id INT;
  DECLARE v_position_count INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  IF EXISTS (SELECT 1 FROM evaluation_templates WHERE form_code = 'AP-MGR-Q-DRYRUN-20261115') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'AP Manager Quarterly form already exists; do not import twice';
  END IF;

  SELECT COUNT(*) INTO v_position_count FROM job_titles
  WHERE department_id = 1 AND job_title_id BETWEEN 101 AND 104
    AND reports_to = 100 AND is_active = 1;
  IF v_position_count <> 4 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Expected AP Manager I-IV positions reporting to AP VP';
  END IF;

  SELECT MIN(u.user_id) INTO v_creator_id
  FROM users u JOIN employees e ON e.employee_id = u.employee_id
  WHERE e.employee_code = 'AP-001' AND e.job_title_id = 100 AND e.department_id = 1
    AND e.employment_status = 'Regular' AND e.is_active = 1 AND e.deleted_at IS NULL
    AND u.username = 'AP-001' AND u.role = 'Employee'
    AND u.is_active = 1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold, 0) = 0;
  IF v_creator_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Active AP-001 VP employee portal account is required';
  END IF;

  START TRANSACTION;
  INSERT INTO evaluation_templates
    (template_name, description, target_department, target_department_id, target_job_title_id,
     behavior_framework_code, behavior_framework_version, evaluation_type, individual_workflow,
     assessment_from, assessment_to, kra_weight, behavior_weight, form_code,
     revision_date, effective_date_form, status, created_by)
  VALUES
    ('[DRY RUN] Quarterly - AP Managers - Main Office',
     'Quarterly Acquired Properties manager review covering staff delivery, asset controls, and portfolio execution.',
     'Acquired Properties', 1, 101, 'QUARTERLY-CORE', '1', 'Quarterly', 1,
     '2026-11-15', '2027-02-14', 80, 20, 'AP-MGR-Q-DRYRUN-20261115',
     '2026-11-15', '2026-11-15', 'Active', v_creator_id);
  SET v_template_id = LAST_INSERT_ID();

  INSERT INTO evaluation_template_positions (template_id, job_title_id)
  SELECT v_template_id, job_title_id FROM job_titles
  WHERE department_id = 1 AND job_title_id BETWEEN 101 AND 104
    AND reports_to = 100 AND is_active = 1;

  INSERT INTO evaluation_criteria
    (template_id, section, criterion_name, description, kpi_description, weight, scoring_method, sort_order)
  VALUES
    (v_template_id, 'KRA', 'Quarterly work plan delivery', 'Translate AP portfolio priorities into a quarterly team work plan with owners, milestones, and documented follow-up.', 'Document team targets, owners, milestones, and delivery status.', 20, 'Scale_1_4', 1),
    (v_template_id, 'KRA', 'Asset inventory and custody', 'Oversee property inventory reconciliations and custody records; resolve or escalate material exceptions.', 'Review reconciliations and record resolution of custody exceptions.', 15, 'Scale_1_4', 2),
    (v_template_id, 'KRA', 'Disposition and recovery execution', 'Deliver approved property disposition and recovery actions with complete records and timely variance reporting.', 'Track disposition and recovery commitments against approved targets.', 15, 'Scale_1_4', 3),
    (v_template_id, 'KRA', 'Valuation and document quality', 'Review valuation support, title files, and transaction documents before forwarding material recommendations for approval.', 'Verify evidence and completeness of material AP case files.', 15, 'Scale_1_4', 4),
    (v_template_id, 'KRA', 'Supervisor coaching and accountability', 'Set expectations for AP supervisors, review their results, and document coaching and corrective actions.', 'Record supervisor reviews and follow-up actions.', 10, 'Scale_1_4', 5),
    (v_template_id, 'KRA', 'Risk and compliance management', 'Track legal, regulatory, policy, and control risks; escalate material issues with owners and due dates.', 'Maintain documented risk actions and closure evidence.', 10, 'Scale_1_4', 6),
    (v_template_id, 'KRA', 'Budget and resource management', 'Monitor approved AP spending and team resources, explaining variances and seeking required approvals.', 'Report budget variances and resource actions.', 10, 'Scale_1_4', 7),
    (v_template_id, 'KRA', 'VP reporting and coordination', 'Provide the AP VP with accurate status reports, decisions needed, and cross-team blockers by agreed deadlines.', 'Submit timely reports and documented escalation requests.', 5, 'Scale_1_4', 8),
    (v_template_id, 'Behavior', 'Positive Attitude', NULL, 'Displays positive attitude at work.', 0, 'Scale_1_4', 1),
    (v_template_id, 'Behavior', 'Respect', NULL, 'Shows respect to all people in the organization.', 0, 'Scale_1_4', 2),
    (v_template_id, 'Behavior', 'Accountability', NULL, 'Takes full responsibility of the job including special task or assignment.', 0, 'Scale_1_4', 3),
    (v_template_id, 'Behavior', 'Commitment', NULL, 'Demonstrates strong commitment to the job.', 0, 'Scale_1_4', 4),
    (v_template_id, 'Behavior', 'Teamwork', NULL, 'Works cooperatively with others in achieving the goals.', 0, 'Scale_1_4', 5),
    (v_template_id, 'Behavior', 'Integrity', NULL, 'Exhibits honesty and strong moral uprightness.', 0, 'Scale_1_4', 6),
    (v_template_id, 'Behavior', 'Continuous Improvement', NULL, 'Provides diligent effort to continuously focus on getting better.', 0, 'Scale_1_4', 7),
    (v_template_id, 'Behavior', 'Excellent Client Experience', NULL, 'Delivers the service beyond the expectations of the internal and external clients.', 0, 'Scale_1_4', 8);

  IF (SELECT COUNT(*) FROM evaluation_template_positions WHERE template_id = v_template_id) <> 4
     OR (SELECT COUNT(*) FROM evaluation_criteria WHERE template_id = v_template_id AND section = 'KRA') <> 8
     OR (SELECT COALESCE(SUM(weight), 0) FROM evaluation_criteria WHERE template_id = v_template_id AND section = 'KRA') <> 100
     OR (SELECT COUNT(*) FROM evaluation_criteria WHERE template_id = v_template_id AND section = 'Behavior') <> 8 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'AP Manager Quarterly form validation failed';
  END IF;
  COMMIT;
  SELECT v_template_id AS template_id, 'AP Manager Quarterly dry-run form imported' AS result;
END //
DELIMITER ;
CALL seed_ap_managers_quarterly_dry_run();
DROP PROCEDURE seed_ap_managers_quarterly_dry_run;
