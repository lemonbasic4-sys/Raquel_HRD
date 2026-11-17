-- Optional: assign one blank Quarterly evaluation after the AP templates exist.
USE raquel_hris;
-- =====================================================================
-- 05  Assign Adrian Reyes (AP Supervisor I) a Pending Self-Rating
-- =====================================================================
DROP PROCEDURE IF EXISTS seed_ap_supervisor_quarterly_evaluation;
DELIMITER //
CREATE PROCEDURE seed_ap_supervisor_quarterly_evaluation()
BEGIN
  DECLARE v_template_id INT DEFAULT NULL;
  DECLARE v_employee_id INT DEFAULT NULL;
  DECLARE v_assigner_id INT DEFAULT NULL;
  DECLARE v_evaluation_id INT DEFAULT NULL;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  SELECT MIN(template_id) INTO v_template_id
  FROM evaluation_templates
  WHERE form_code = 'AP-HEAD-Q-DRYRUN-20261115'
    AND evaluation_type = 'Quarterly' AND individual_workflow = 1
    AND target_department_id = 1 AND status = 'Active' AND deleted_at IS NULL
    AND assessment_from = '2026-11-15' AND assessment_to = '2027-02-14';
  IF v_template_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Import the AP immediate-head Quarterly template first';
  END IF;

  SELECT MIN(e.employee_id) INTO v_employee_id
  FROM employees e JOIN users u ON u.employee_id = e.employee_id
  JOIN evaluation_template_positions p ON p.job_title_id = e.job_title_id AND p.template_id = v_template_id
  WHERE e.employee_code = 'MO26-AP-H' AND u.username = 'MO26-AP-H'
    AND e.department_id = 1 AND e.job_title_id = 105 AND e.employment_status = 'Regular'
    AND e.is_active = 1 AND e.deleted_at IS NULL
    AND u.role = 'Employee' AND u.is_active = 1 AND u.deleted_at IS NULL
    AND COALESCE(u.account_hold, 0) = 0;
  IF v_employee_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Active AP Supervisor I portal account is required';
  END IF;

  SELECT MIN(u.user_id) INTO v_assigner_id
  FROM users u JOIN employees e ON e.employee_id = u.employee_id
  WHERE u.username = 'MO26-AP-M' AND e.employee_code = 'MO26-AP-M'
    AND e.department_id = 1 AND e.job_title_id = 101 AND e.employment_status = 'Regular'
    AND e.is_active = 1 AND e.deleted_at IS NULL
    AND u.is_active = 1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold, 0) = 0;
  IF v_assigner_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Active AP Manager I account is required to assign this evaluation';
  END IF;

  START TRANSACTION;
  IF EXISTS (SELECT 1 FROM evaluations
             WHERE employee_id = v_employee_id AND template_id = v_template_id AND deleted_at IS NULL) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Adrian already has an evaluation for this Quarterly template';
  END IF;
  INSERT INTO evaluations
    (employee_id, template_id, evaluation_type, evaluation_period_start,
     evaluation_period_end, assigned_by, assigned_at, status)
  VALUES
    (v_employee_id, v_template_id, 'Quarterly', '2026-11-15',
     '2027-02-14', v_assigner_id, NOW(), 'Pending Self-Rating');
  SET v_evaluation_id = LAST_INSERT_ID();
  COMMIT;
  SELECT v_evaluation_id AS evaluation_id, 'Adrian Reyes Quarterly self-rating assigned' AS result;
END //
DELIMITER ;
CALL seed_ap_supervisor_quarterly_evaluation();
DROP PROCEDURE seed_ap_supervisor_quarterly_evaluation;
