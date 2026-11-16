USE raquel_hris;
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

  IF NOT EXISTS (SELECT 1 FROM employees e JOIN users u ON u.employee_id = e.employee_id
                 WHERE e.employee_code = 'AP-001' AND e.job_title_id = 100 AND e.department_id = 1
                   AND e.employment_status = 'Regular' AND e.is_active = 1 AND e.deleted_at IS NULL
                   AND u.username = 'AP-001' AND u.role = 'Employee'
                   AND u.is_active = 1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold, 0) = 0) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Active AP-001 VP employee portal account is required';
  END IF;

  SELECT MIN(u.user_id) INTO v_creator_id
  FROM users u JOIN employees e ON e.employee_id = u.employee_id
  WHERE e.employee_code = 'CEO-001' AND e.job_title_id = 1100
    AND u.role = 'President and CEO' AND u.is_active = 1 AND u.deleted_at IS NULL
    AND COALESCE(u.account_hold, 0) = 0 AND e.is_active = 1 AND e.deleted_at IS NULL;
  IF v_creator_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Active CEO-001 President and CEO account is required';
  END IF;

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
  COMMIT;
  SELECT v_template_id AS template_id, 'AP VP Quarterly dry-run form imported' AS result;
END //
DELIMITER ;
CALL seed_ap_vp_quarterly_dry_run();
DROP PROCEDURE seed_ap_vp_quarterly_dry_run;
