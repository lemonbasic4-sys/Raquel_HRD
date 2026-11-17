-- AP DEPARTMENT QUARTERLY DRY RUN (fresh-setup bundle)
-- Period: 2026-11-15 through 2027-02-14.
-- Sections 01-04 create four Active templates: Staff, Supervisors, Managers, VP.
-- Templates only. Optional assignment: seed_ap_supervisor_quarterly_evaluation.sql.
-- No scores, submissions, or approvals are generated.
-- Run only after organization, employees are seeded. Portal accounts are created manually later.
-- The preflight below stops if ANY bundled template already exists.
-- It is not a repair or reimport script for an existing dry run.
USE raquel_hris;

DROP PROCEDURE IF EXISTS ap_quarterly_bundle_preflight;
DELIMITER //
CREATE PROCEDURE ap_quarterly_bundle_preflight()
BEGIN
  IF EXISTS (SELECT 1 FROM evaluation_templates WHERE form_code IN ('AP-RF-Q-DRYRUN-20261115','AP-HEAD-Q-DRYRUN-20261115','AP-MGR-Q-DRYRUN-20261115','AP-VP-Q-DRYRUN-20261115')) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'AP Quarterly form exists; bundle requires a fresh setup';
  END IF;
END //
DELIMITER ;
CALL ap_quarterly_bundle_preflight();
DROP PROCEDURE ap_quarterly_bundle_preflight;

-- =====================================================================
-- 01  AP Staff I-V
-- =====================================================================
DROP PROCEDURE IF EXISTS seed_ap_rank_and_file_quarterly_dry_run;
DELIMITER //
CREATE PROCEDURE seed_ap_rank_and_file_quarterly_dry_run()
BEGIN
  DECLARE v_template_id INT;
  DECLARE v_creator_id INT;
  DECLARE v_position_count INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  IF EXISTS (SELECT 1 FROM evaluation_templates WHERE form_code = 'AP-RF-Q-DRYRUN-20261115') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'AP Quarterly dry-run form already exists; do not import twice';
  END IF;

  SELECT MIN(u.user_id) INTO v_creator_id
  FROM users u JOIN employees e ON e.employee_id = u.employee_id
  WHERE u.username = 'MO26-AP-H' AND e.department_id = 1
    AND e.job_title_id = 105 AND u.is_active = 1 AND u.deleted_at IS NULL;


  SELECT COUNT(*) INTO v_position_count FROM job_titles
  WHERE department_id = 1 AND job_title_id BETWEEN 108 AND 112
    AND reports_to = 105 AND is_active = 1;
  IF v_position_count <> 5 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Expected AP Staff I-V positions reporting to AP Supervisor I';
  END IF;

  START TRANSACTION;
  INSERT INTO evaluation_templates
    (template_name, description, target_department, target_department_id, target_job_title_id,
     behavior_framework_code, behavior_framework_version, evaluation_type, individual_workflow,
     assessment_from, assessment_to, kra_weight, behavior_weight, form_code,
     revision_date, effective_date_form, status, created_by)
  VALUES
    ('[DRY RUN] Quarterly - AP Rank and File - Main Office',
     'Dry-run AP Staff I-V form for Regular employees; use actual work evidence before production approval.',
     'Acquired Properties', 1, 108, 'QUARTERLY-CORE', '1', 'Quarterly', 1,
     '2026-11-15', '2027-02-14', 80, 20, 'AP-RF-Q-DRYRUN-20261115',
     '2026-11-15', '2026-11-15', 'Active', v_creator_id);
  SET v_template_id = LAST_INSERT_ID();

  INSERT INTO evaluation_template_positions (template_id, job_title_id)
  SELECT v_template_id, job_title_id FROM job_titles
  WHERE department_id = 1 AND job_title_id BETWEEN 108 AND 112
    AND reports_to = 105 AND is_active = 1;

  INSERT INTO evaluation_criteria
    (template_id, section, criterion_name, description, kpi_description, weight, scoring_method, sort_order)
  VALUES
    (v_template_id, 'KRA', 'Acquired property record accuracy', 'Keep assigned property records complete and consistent with source documents and the inventory register; correct discrepancies promptly.', 'Keep assigned property records complete and consistent with source documents and the inventory register; correct discrepancies promptly.', 20, 'Scale_1_4', 1),
    (v_template_id, 'KRA', 'Custody and inventory reconciliation', 'Document handoffs, location and status changes, and assigned reconciliation findings with a traceable audit trail.', 'Document handoffs, location and status changes, and assigned reconciliation findings with a traceable audit trail.', 15, 'Scale_1_4', 2),
    (v_template_id, 'KRA', 'Document completeness and control', 'Maintain required title, acquisition, valuation, and disposition documents; flag missing or expired records to the supervisor.', 'Maintain required title, acquisition, valuation, and disposition documents; flag missing or expired records to the supervisor.', 15, 'Scale_1_4', 3),
    (v_template_id, 'KRA', 'Valuation support preparation', 'Assemble current comparable evidence and supporting documents for valuation review without making unauthorized final valuation decisions.', 'Assemble current comparable evidence and supporting documents for valuation review without making unauthorized final valuation decisions.', 10, 'Scale_1_4', 4),
    (v_template_id, 'KRA', 'Disposition and transaction support', 'Prepare assigned sale, transfer, or release paperwork accurately and on schedule after required approvals.', 'Prepare assigned sale, transfer, or release paperwork accurately and on schedule after required approvals.', 10, 'Scale_1_4', 5),
    (v_template_id, 'KRA', 'Case turnaround and follow-through', 'Complete assigned AP cases by agreed deadlines, record pending items, and follow up on blockers.', 'Complete assigned AP cases by agreed deadlines, record pending items, and follow up on blockers.', 10, 'Scale_1_4', 6),
    (v_template_id, 'KRA', 'Risk and exception escalation', 'Promptly report document gaps, custody issues, legal concerns, or unusual transactions through the approved escalation path.', 'Promptly report document gaps, custody issues, legal concerns, or unusual transactions through the approved escalation path.', 10, 'Scale_1_4', 7),
    (v_template_id, 'KRA', 'Stakeholder coordination and service', 'Give timely, accurate updates to internal requesters and coordinate handoffs with other teams while protecting confidential information.', 'Give timely, accurate updates to internal requesters and coordinate handoffs with other teams while protecting confidential information.', 10, 'Scale_1_4', 8),
    (v_template_id, 'Behavior', 'Positive Attitude', NULL, 'Displays positive attitude at work.', 0, 'Scale_1_4', 1),
    (v_template_id, 'Behavior', 'Respect', NULL, 'Shows respect to all people in the organization.', 0, 'Scale_1_4', 2),
    (v_template_id, 'Behavior', 'Accountability', NULL, 'Takes full responsibility of the job including special task or assignment.', 0, 'Scale_1_4', 3),
    (v_template_id, 'Behavior', 'Commitment', NULL, 'Demonstrates strong commitment to the job.', 0, 'Scale_1_4', 4),
    (v_template_id, 'Behavior', 'Teamwork', NULL, 'Works cooperatively with others in achieving the goals.', 0, 'Scale_1_4', 5),
    (v_template_id, 'Behavior', 'Integrity', NULL, 'Exhibits honesty and strong moral uprightness.', 0, 'Scale_1_4', 6),
    (v_template_id, 'Behavior', 'Continuous Improvement', NULL, 'Provides diligent effort to continuously focus on getting better.', 0, 'Scale_1_4', 7),
    (v_template_id, 'Behavior', 'Excellent Client Experience', NULL, 'Delivers the service beyond the expectations of the internal and external clients.', 0, 'Scale_1_4', 8);

  IF (SELECT COUNT(*) FROM evaluation_template_positions WHERE template_id = v_template_id) <> 5
     OR (SELECT COUNT(*) FROM evaluation_criteria WHERE template_id = v_template_id AND section = 'KRA') <> 8
     OR (SELECT COALESCE(SUM(weight), 0) FROM evaluation_criteria WHERE template_id = v_template_id AND section = 'KRA') <> 100
     OR (SELECT COUNT(*) FROM evaluation_criteria WHERE template_id = v_template_id AND section = 'Behavior') <> 8 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'AP Quarterly dry-run form validation failed';
  END IF;

  -- BEGIN SEEDED OWNERS
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'AP-RF-Q-DRYRUN-20261115',employee_id,'Employee' FROM employees WHERE employee_code='MO26-AP-H'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'AP-HEAD-Q-DRYRUN-20261115',employee_id,'Employee' FROM employees WHERE employee_code='MO26-AP-M'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'AP-MGR-Q-DRYRUN-20261115',employee_id,'Employee' FROM employees WHERE employee_code='AP-001'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'AP-VP-Q-DRYRUN-20261115',employee_id,'President and CEO' FROM employees WHERE employee_code='CEO-001'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  UPDATE evaluation_templates t JOIN evaluation_seed_template_owners o ON o.form_code=t.form_code
  SET t.created_by=(SELECT MIN(u.user_id) FROM users u WHERE u.employee_id=o.employee_id
    AND (u.role=o.preferred_role OR (o.preferred_role='Employee' AND u.role IN ('HR Manager','HR Supervisor','HR Staff')))
    AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0)
  WHERE t.created_by IS NULL;
  -- END SEEDED OWNERS
  COMMIT;
  SELECT v_template_id AS template_id, 'AP Quarterly R&F dry-run form imported' AS result;
END //
DELIMITER ;
CALL seed_ap_rank_and_file_quarterly_dry_run();
DROP PROCEDURE seed_ap_rank_and_file_quarterly_dry_run;

-- =====================================================================
-- 02  AP Supervisor I-III
-- =====================================================================
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

  -- BEGIN SEEDED OWNERS
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'AP-RF-Q-DRYRUN-20261115',employee_id,'Employee' FROM employees WHERE employee_code='MO26-AP-H'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'AP-HEAD-Q-DRYRUN-20261115',employee_id,'Employee' FROM employees WHERE employee_code='MO26-AP-M'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'AP-MGR-Q-DRYRUN-20261115',employee_id,'Employee' FROM employees WHERE employee_code='AP-001'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'AP-VP-Q-DRYRUN-20261115',employee_id,'President and CEO' FROM employees WHERE employee_code='CEO-001'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  UPDATE evaluation_templates t JOIN evaluation_seed_template_owners o ON o.form_code=t.form_code
  SET t.created_by=(SELECT MIN(u.user_id) FROM users u WHERE u.employee_id=o.employee_id
    AND (u.role=o.preferred_role OR (o.preferred_role='Employee' AND u.role IN ('HR Manager','HR Supervisor','HR Staff')))
    AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0)
  WHERE t.created_by IS NULL;
  -- END SEEDED OWNERS
  COMMIT;
  SELECT v_template_id AS template_id, 'AP immediate-head Quarterly dry-run form imported' AS result;
END //
DELIMITER ;
CALL seed_ap_immediate_heads_quarterly_dry_run();
DROP PROCEDURE seed_ap_immediate_heads_quarterly_dry_run;

-- =====================================================================
-- 03  AP Manager I-IV
-- =====================================================================
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
    AND u.role = 'Employee'
    AND u.is_active = 1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold, 0) = 0;


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

  -- BEGIN SEEDED OWNERS
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'AP-RF-Q-DRYRUN-20261115',employee_id,'Employee' FROM employees WHERE employee_code='MO26-AP-H'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'AP-HEAD-Q-DRYRUN-20261115',employee_id,'Employee' FROM employees WHERE employee_code='MO26-AP-M'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'AP-MGR-Q-DRYRUN-20261115',employee_id,'Employee' FROM employees WHERE employee_code='AP-001'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'AP-VP-Q-DRYRUN-20261115',employee_id,'President and CEO' FROM employees WHERE employee_code='CEO-001'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  UPDATE evaluation_templates t JOIN evaluation_seed_template_owners o ON o.form_code=t.form_code
  SET t.created_by=(SELECT MIN(u.user_id) FROM users u WHERE u.employee_id=o.employee_id
    AND (u.role=o.preferred_role OR (o.preferred_role='Employee' AND u.role IN ('HR Manager','HR Supervisor','HR Staff')))
    AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0)
  WHERE t.created_by IS NULL;
  -- END SEEDED OWNERS
  COMMIT;
  SELECT v_template_id AS template_id, 'AP Manager Quarterly dry-run form imported' AS result;
END //
DELIMITER ;
CALL seed_ap_managers_quarterly_dry_run();
DROP PROCEDURE seed_ap_managers_quarterly_dry_run;

-- =====================================================================
-- 04  VP for Acquired Properties
-- =====================================================================
DROP PROCEDURE IF EXISTS seed_ap_vp_quarterly_dry_run;
DELIMITER //
CREATE PROCEDURE seed_ap_vp_quarterly_dry_run()
BEGIN
  DECLARE v_template_id INT;
  DECLARE v_creator_id INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  IF EXISTS (SELECT 1 FROM evaluation_templates WHERE form_code = 'AP-VP-Q-DRYRUN-20261115') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'AP VP Quarterly form already exists; do not import twice';
  END IF;

  IF NOT EXISTS (SELECT 1 FROM job_titles WHERE job_title_id = 100
                 AND job_title = 'VP for Acquired Properties' AND department_id = 1
                 AND reports_to = 1100 AND is_active = 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Active AP VP position reporting to the CEO is required';
  END IF;



  SELECT MIN(u.user_id) INTO v_creator_id
  FROM users u JOIN employees e ON e.employee_id = u.employee_id
  WHERE e.employee_code = 'CEO-001' AND e.job_title_id = 1100
    AND u.role = 'President and CEO' AND u.is_active = 1 AND u.deleted_at IS NULL
    AND COALESCE(u.account_hold, 0) = 0 AND e.is_active = 1 AND e.deleted_at IS NULL;


  START TRANSACTION;
  INSERT INTO evaluation_templates
    (template_name, description, target_department, target_department_id, target_job_title_id,
     behavior_framework_code, behavior_framework_version, evaluation_type, individual_workflow,
     assessment_from, assessment_to, kra_weight, behavior_weight, form_code,
     revision_date, effective_date_form, status, created_by)
  VALUES
    ('[DRY RUN] Quarterly - VP for Acquired Properties - Main Office',
     'Quarterly AP portfolio leadership review for the VP for Acquired Properties.',
     'Acquired Properties', 1, 100, 'QUARTERLY-CORE', '1', 'Quarterly', 1,
     '2026-11-15', '2027-02-14', 80, 20, 'AP-VP-Q-DRYRUN-20261115',
     '2026-11-15', '2026-11-15', 'Active', v_creator_id);
  SET v_template_id = LAST_INSERT_ID();

  INSERT INTO evaluation_template_positions (template_id, job_title_id) VALUES (v_template_id, 100);

  INSERT INTO evaluation_criteria
    (template_id, section, criterion_name, description, kpi_description, weight, scoring_method, sort_order)
  VALUES
    (v_template_id, 'KRA', 'Portfolio strategy and priorities', 'Set quarterly AP portfolio priorities, measurable targets, owners, and risk actions for executive review.', 'Document a quarterly plan with targets, owners, and risk actions.', 20, 'Scale_1_4', 1),
    (v_template_id, 'KRA', 'Disposition and recovery results', 'Monitor approved disposition and recovery targets; document material variances and corrective decisions.', 'Track approved disposition and recovery targets with variance explanations.', 15, 'Scale_1_4', 2),
    (v_template_id, 'KRA', 'Valuation and acquisition governance', 'Ensure material valuation and acquisition recommendations have evidence, review, and the required approvals.', 'Verify evidence and approval trails for material valuation and acquisition decisions.', 15, 'Scale_1_4', 3),
    (v_template_id, 'KRA', 'Legal and custody risk oversight', 'Escalate high-risk title, litigation, asset custody, and control exceptions with accountable resolution plans.', 'Maintain an exception register with owners and target resolution dates.', 15, 'Scale_1_4', 4),
    (v_template_id, 'KRA', 'Budget and resource stewardship', 'Monitor portfolio spending against approved budgets and obtain approval before material variances.', 'Report budget variances and approved resource actions.', 10, 'Scale_1_4', 5),
    (v_template_id, 'KRA', 'AP manager leadership', 'Review AP manager delivery, coach on gaps, and document follow-up commitments during the quarter.', 'Document manager reviews and follow-up commitments.', 10, 'Scale_1_4', 6),
    (v_template_id, 'KRA', 'Regulatory and policy compliance', 'Track applicable compliance obligations and close due actions with evidence by approved deadlines.', 'Track due compliance actions and closure evidence.', 10, 'Scale_1_4', 7),
    (v_template_id, 'KRA', 'Executive reporting and decisions', 'Provide timely, accurate portfolio reporting that identifies decisions needed and supporting evidence.', 'Submit portfolio reports with clear decision requests and supporting data.', 5, 'Scale_1_4', 8),
    (v_template_id, 'Behavior', 'Positive Attitude', NULL, 'Displays positive attitude at work.', 0, 'Scale_1_4', 1),
    (v_template_id, 'Behavior', 'Respect', NULL, 'Shows respect to all people in the organization.', 0, 'Scale_1_4', 2),
    (v_template_id, 'Behavior', 'Accountability', NULL, 'Takes full responsibility of the job including special task or assignment.', 0, 'Scale_1_4', 3),
    (v_template_id, 'Behavior', 'Commitment', NULL, 'Demonstrates strong commitment to the job.', 0, 'Scale_1_4', 4),
    (v_template_id, 'Behavior', 'Teamwork', NULL, 'Works cooperatively with others in achieving the goals.', 0, 'Scale_1_4', 5),
    (v_template_id, 'Behavior', 'Integrity', NULL, 'Exhibits honesty and strong moral uprightness.', 0, 'Scale_1_4', 6),
    (v_template_id, 'Behavior', 'Continuous Improvement', NULL, 'Provides diligent effort to continuously focus on getting better.', 0, 'Scale_1_4', 7),
    (v_template_id, 'Behavior', 'Excellent Client Experience', NULL, 'Delivers the service beyond the expectations of the internal and external clients.', 0, 'Scale_1_4', 8);

  IF (SELECT COUNT(*) FROM evaluation_template_positions WHERE template_id = v_template_id) <> 1
     OR (SELECT COUNT(*) FROM evaluation_criteria WHERE template_id = v_template_id AND section = 'KRA') <> 8
     OR (SELECT COALESCE(SUM(weight), 0) FROM evaluation_criteria WHERE template_id = v_template_id AND section = 'KRA') <> 100
     OR (SELECT COUNT(*) FROM evaluation_criteria WHERE template_id = v_template_id AND section = 'Behavior') <> 8 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'AP VP Quarterly form validation failed';
  END IF;

  -- BEGIN SEEDED OWNERS
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'AP-RF-Q-DRYRUN-20261115',employee_id,'Employee' FROM employees WHERE employee_code='MO26-AP-H'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'AP-HEAD-Q-DRYRUN-20261115',employee_id,'Employee' FROM employees WHERE employee_code='MO26-AP-M'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'AP-MGR-Q-DRYRUN-20261115',employee_id,'Employee' FROM employees WHERE employee_code='AP-001'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  INSERT INTO evaluation_seed_template_owners (form_code,employee_id,preferred_role)
  SELECT 'AP-VP-Q-DRYRUN-20261115',employee_id,'President and CEO' FROM employees WHERE employee_code='CEO-001'
  ON DUPLICATE KEY UPDATE form_code=VALUES(form_code);
  UPDATE evaluation_templates t JOIN evaluation_seed_template_owners o ON o.form_code=t.form_code
  SET t.created_by=(SELECT MIN(u.user_id) FROM users u WHERE u.employee_id=o.employee_id
    AND (u.role=o.preferred_role OR (o.preferred_role='Employee' AND u.role IN ('HR Manager','HR Supervisor','HR Staff')))
    AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0)
  WHERE t.created_by IS NULL;
  -- END SEEDED OWNERS
  COMMIT;
  SELECT v_template_id AS template_id, 'AP VP Quarterly dry-run form imported' AS result;
END //
DELIMITER ;
CALL seed_ap_vp_quarterly_dry_run();
DROP PROCEDURE seed_ap_vp_quarterly_dry_run;
