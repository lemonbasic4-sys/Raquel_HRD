USE raquel_hris;
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
  IF v_creator_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Active MO26-AP-H AP Supervisor I account is required';
  END IF;

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
  COMMIT;
  SELECT v_template_id AS template_id, 'AP Quarterly R&F dry-run form imported' AS result;
END //
DELIMITER ;
CALL seed_ap_rank_and_file_quarterly_dry_run();
DROP PROCEDURE seed_ap_rank_and_file_quarterly_dry_run;
