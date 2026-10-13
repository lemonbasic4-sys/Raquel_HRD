-- President/CEO-owned Initial and Final probationary templates for HR Managers.
-- Import after president_ceo_account.sql and the organization/schema seeds.
-- Uses generated IDs; preserves existing templates, criteria and evaluations on rerun.
USE raquel_hris;
START TRANSACTION;

SET @ceo_creator_id = (
    SELECT u.user_id FROM users u
    JOIN employees e ON e.employee_id = u.employee_id
    WHERE u.username = 'OP-001' AND u.role = 'President and CEO'
      AND u.is_active = 1 AND COALESCE(u.account_hold, 0) = 0
      AND u.deleted_at IS NULL AND e.is_active = 1 AND e.deleted_at IS NULL
    LIMIT 1
);
SET @hr_department_id = (
    SELECT department_id FROM departments
    WHERE department_name = 'Human Resources' AND deleted_at IS NULL LIMIT 1
);
SET @hr_manager_position_id = (
    SELECT MIN(job_title_id) FROM job_titles
    WHERE department_id = @hr_department_id AND rank_category_id = 3 AND is_active = 1
);

-- Missing prerequisites produce no inserts; check the result message below.
INSERT INTO evaluation_templates
    (template_name, description, target_department, target_department_id,
     target_job_title_id, evaluation_type, kra_weight, behavior_weight,
     form_code, revision_date, effective_date_form, status, created_by)
SELECT CONCAT(types.evaluation_type, ' Evaluation on Probationary HR Manager/s'),
    CONCAT(types.evaluation_type, ' probationary evaluation completed by the President and CEO to assess HR Manager performance and readiness for regularization.'),
    'Human Resources', @hr_department_id, @hr_manager_position_id,
    types.evaluation_type, 80.00, 20.00, types.form_code,
    '2026-10-13', '2026-10-13', 'Active', @ceo_creator_id
FROM (
    SELECT 'Initial' AS evaluation_type, 'ceo_hr_manager_initial_probi' AS form_code
    UNION ALL SELECT 'Final', 'ceo_hr_manager_final_probi'
) types
WHERE @ceo_creator_id IS NOT NULL AND @hr_manager_position_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM evaluation_templates existing
      WHERE existing.created_by = @ceo_creator_id AND existing.form_code = types.form_code
  );

-- All active HR Manager positions (rank 3), excluding supervisor/staff positions.
INSERT IGNORE INTO evaluation_template_positions (template_id, job_title_id)
SELECT et.template_id, jt.job_title_id
FROM evaluation_templates et
JOIN job_titles jt ON jt.department_id = @hr_department_id
    AND jt.rank_category_id = 3 AND jt.is_active = 1
WHERE et.created_by = @ceo_creator_id
  AND et.form_code IN ('ceo_hr_manager_initial_probi', 'ceo_hr_manager_final_probi')
  AND et.deleted_at IS NULL;

DROP TEMPORARY TABLE IF EXISTS ceo_probationary_seed_criteria;
CREATE TEMPORARY TABLE ceo_probationary_seed_criteria (
    section VARCHAR(20), criterion_name VARCHAR(150), description TEXT,
    kpi_description TEXT, weight DECIMAL(5,2), sort_order INT
);
INSERT INTO ceo_probationary_seed_criteria
    (section, criterion_name, description, kpi_description, weight, sort_order)
VALUES
    ('KRA', 'HR Strategy and Workforce Planning', 'Translates business priorities into practical HR plans, staffing requirements, and measurable department goals.', NULL, 15.00, 1),
    ('KRA', 'Department Leadership and Team Development', 'Directs HR supervisors and staff, delegates responsibilities, coaches performance, and follows through on team commitments.', NULL, 15.00, 2),
    ('KRA', 'HR Operations and Service Delivery', 'Ensures accurate and timely delivery of employee records, HR transactions, onboarding, and personnel services.', NULL, 12.00, 3),
    ('KRA', 'Policy Compliance and Risk Management', 'Implements approved HR policies, maintains audit-ready documentation, and escalates compliance risks to executive management.', NULL, 12.00, 4),
    ('KRA', 'Employee Relations and Issue Resolution', 'Resolves employee concerns fairly, handles sensitive cases discreetly, and recommends appropriate management actions.', NULL, 10.00, 5),
    ('KRA', 'Performance and Probationary Oversight', 'Monitors evaluation schedules, validates recommendations, and ensures probationary reviews are completed with adequate evidence.', NULL, 10.00, 6),
    ('KRA', 'Executive Reporting and Coordination', 'Provides the President and CEO with clear HR reports, recommendations, progress updates, and timely escalations.', NULL, 8.00, 7),
    ('KRA', 'HRIS Data Governance and Confidentiality', 'Ensures personnel data accuracy, appropriate access, confidentiality, and prompt resolution of data discrepancies.', NULL, 7.00, 8),
    ('KRA', 'Resource and Deadline Management', 'Manages department resources and priorities responsibly and meets agreed deliverables within approved timelines.', NULL, 6.00, 9),
    ('KRA', 'Learning Application and Process Improvement', 'Applies executive feedback and training and implements practical improvements to HR processes during probation.', NULL, 5.00, 10),
    ('Behavior', 'Positive Attitude', NULL, 'Displays positive attitude at work.', 0.00, 1),
    ('Behavior', 'Respect', NULL, 'Shows respect to all people in the organization.', 0.00, 2),
    ('Behavior', 'Accountability', NULL, 'Takes full responsibility of the job including special task or assignment.', 0.00, 3),
    ('Behavior', 'Commitment', NULL, 'Demonstrates strong commitment to the job.', 0.00, 4),
    ('Behavior', 'Teamwork', NULL, 'Works cooperatively with others in achieving the goals.', 0.00, 5),
    ('Behavior', 'Integrity', NULL, 'Exhibits honesty and strong moral uprightness.', 0.00, 6),
    ('Behavior', 'Continuous Improvement', NULL, 'Provides diligent effort to continuously focus on getting better.', 0.00, 7),
    ('Behavior', 'Excellent Client Experience', NULL, 'Delivers the service beyond the expectations of the internal and external clients.', 0.00, 8);

-- Do not overwrite criteria on an existing form that may already have scores.
INSERT INTO evaluation_criteria
    (template_id, section, criterion_name, description, kpi_description, weight, scoring_method, sort_order)
SELECT et.template_id, c.section, c.criterion_name, c.description,
    c.kpi_description, c.weight, 'Scale_1_4', c.sort_order
FROM evaluation_templates et CROSS JOIN ceo_probationary_seed_criteria c
WHERE et.created_by = @ceo_creator_id
  AND et.form_code IN ('ceo_hr_manager_initial_probi', 'ceo_hr_manager_final_probi')
  AND et.deleted_at IS NULL
  AND NOT EXISTS (SELECT 1 FROM evaluation_criteria existing WHERE existing.template_id = et.template_id);

DROP TEMPORARY TABLE ceo_probationary_seed_criteria;
COMMIT;

SELECT CASE
    WHEN @ceo_creator_id IS NULL THEN 'Not seeded: import president_ceo_account.sql first.'
    WHEN @hr_manager_position_id IS NULL THEN 'Not seeded: active Human Resources Manager positions are required.'
    ELSE 'President/CEO Initial and Final HR Manager templates are ready.'
END AS seed_result;
SELECT et.template_id, et.template_name, et.evaluation_type, u.username AS owner,
    et.kra_weight, et.behavior_weight,
    (SELECT COUNT(*) FROM evaluation_template_positions p WHERE p.template_id = et.template_id) AS target_positions,
    (SELECT COUNT(*) FROM evaluation_criteria c WHERE c.template_id = et.template_id) AS criteria_count,
    (SELECT SUM(c.weight) FROM evaluation_criteria c WHERE c.template_id = et.template_id AND c.section = 'KRA') AS kra_weight_total
FROM evaluation_templates et JOIN users u ON u.user_id = et.created_by
WHERE et.created_by = @ceo_creator_id
  AND et.form_code IN ('ceo_hr_manager_initial_probi', 'ceo_hr_manager_final_probi');
