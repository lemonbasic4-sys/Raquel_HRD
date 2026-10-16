-- ============================================================================
-- Probationary evaluation templates for the HR and AP reporting hierarchy
--
-- President/CEO -> HR Manager and AP Manager
-- HR Manager     -> HR Supervisor
-- HR Supervisor  -> HR Staff
-- AP Manager     -> AP Supervisor
-- AP Supervisor  -> AP Staff
--
-- Prerequisites:
--   1. database/1st_schema_tables.sql
--   2. database/2nd_seed_organization.sql
--   3. HR, AP, and President/CEO employee seeds
--   4. database/create_missing_employee_portal_accounts.sql
-- ============================================================================

USE raquel_hris;

SET @ceo_user_id = (
    SELECT user_id FROM users
    WHERE employee_id = 15010 AND role = 'President and CEO' AND deleted_at IS NULL
    LIMIT 1
);
SET @hr_manager_user_id = (
    SELECT user_id FROM users
    WHERE employee_id = 101 AND role = 'HR Manager' AND deleted_at IS NULL
    LIMIT 1
);
SET @hr_supervisor_user_id = (
    SELECT user_id FROM users
    WHERE employee_id = 301 AND role = 'HR Supervisor' AND deleted_at IS NULL
    LIMIT 1
);
SET @ap_manager_user_id = (
    SELECT user_id FROM users
    WHERE employee_id = 15001 AND role = 'Employee' AND deleted_at IS NULL
    LIMIT 1
);
SET @ap_supervisor_user_id = (
    SELECT user_id FROM users
    WHERE employee_id = 15002 AND role = 'Employee' AND deleted_at IS NULL
    LIMIT 1
);

START TRANSACTION;

-- Fixed IDs make this test seed safe and predictable when it is imported again.
INSERT INTO evaluation_templates
    (template_id, template_name, description, target_department,
     target_department_id, target_job_title_id, evaluation_type,
     kra_weight, behavior_weight, form_code, revision_date,
     effective_date_form, status, created_by)
VALUES
(16001, 'CEO - HR Manager Probationary Initial Evaluation',
 'Initial probationary assessment of an HR Manager by the President and CEO.',
 'Human Resources', 7, 700, 'Initial', 80.00, 20.00, 'PROBI-CEO-HRM-I', '2026-10-16', '2026-10-16', 'Active', @ceo_user_id),
(16002, 'CEO - HR Manager Probationary Final Evaluation',
 'Final probationary assessment of an HR Manager by the President and CEO.',
 'Human Resources', 7, 700, 'Final', 80.00, 20.00, 'PROBI-CEO-HRM-F', '2026-10-16', '2026-10-16', 'Active', @ceo_user_id),
(16003, 'CEO - AP Manager Probationary Initial Evaluation',
 'Initial probationary assessment of an AP Manager by the President and CEO.',
 'Acquired Properties', 1, 101, 'Initial', 80.00, 20.00, 'PROBI-CEO-APM-I', '2026-10-16', '2026-10-16', 'Active', @ceo_user_id),
(16004, 'CEO - AP Manager Probationary Final Evaluation',
 'Final probationary assessment of an AP Manager by the President and CEO.',
 'Acquired Properties', 1, 101, 'Final', 80.00, 20.00, 'PROBI-CEO-APM-F', '2026-10-16', '2026-10-16', 'Active', @ceo_user_id),
(16005, 'HR Manager - HR Supervisor Probationary Initial Evaluation',
 'Initial probationary assessment of an HR Supervisor by the HR Manager.',
 'Human Resources', 7, 705, 'Initial', 80.00, 20.00, 'PROBI-HRM-HRS-I', '2026-10-16', '2026-10-16', 'Active', @hr_manager_user_id),
(16006, 'HR Manager - HR Supervisor Probationary Final Evaluation',
 'Final probationary assessment of an HR Supervisor by the HR Manager.',
 'Human Resources', 7, 705, 'Final', 80.00, 20.00, 'PROBI-HRM-HRS-F', '2026-10-16', '2026-10-16', 'Active', @hr_manager_user_id),
(16007, 'HR Supervisor - HR Staff Probationary Initial Evaluation',
 'Initial probationary assessment of an HR Staff member by the HR Supervisor.',
 'Human Resources', 7, 710, 'Initial', 80.00, 20.00, 'PROBI-HRS-HRST-I', '2026-10-16', '2026-10-16', 'Active', @hr_supervisor_user_id),
(16008, 'HR Supervisor - HR Staff Probationary Final Evaluation',
 'Final probationary assessment of an HR Staff member by the HR Supervisor.',
 'Human Resources', 7, 710, 'Final', 80.00, 20.00, 'PROBI-HRS-HRST-F', '2026-10-16', '2026-10-16', 'Active', @hr_supervisor_user_id),
(16009, 'AP Manager - AP Supervisor Probationary Initial Evaluation',
 'Initial probationary assessment of an AP Supervisor by the AP Manager.',
 'Acquired Properties', 1, 105, 'Initial', 80.00, 20.00, 'PROBI-APM-APS-I', '2026-10-16', '2026-10-16', 'Active', @ap_manager_user_id),
(16010, 'AP Manager - AP Supervisor Probationary Final Evaluation',
 'Final probationary assessment of an AP Supervisor by the AP Manager.',
 'Acquired Properties', 1, 105, 'Final', 80.00, 20.00, 'PROBI-APM-APS-F', '2026-10-16', '2026-10-16', 'Active', @ap_manager_user_id),
(16011, 'AP Supervisor - AP Staff Probationary Initial Evaluation',
 'Initial probationary assessment of an AP Staff member by the AP Supervisor.',
 'Acquired Properties', 1, 108, 'Initial', 80.00, 20.00, 'PROBI-APS-APST-I', '2026-10-16', '2026-10-16', 'Active', @ap_supervisor_user_id),
(16012, 'AP Supervisor - AP Staff Probationary Final Evaluation',
 'Final probationary assessment of an AP Staff member by the AP Supervisor.',
 'Acquired Properties', 1, 108, 'Final', 80.00, 20.00, 'PROBI-APS-APST-F', '2026-10-16', '2026-10-16', 'Active', @ap_supervisor_user_id)
ON DUPLICATE KEY UPDATE
    template_name = VALUES(template_name),
    description = VALUES(description),
    target_department = VALUES(target_department),
    target_department_id = VALUES(target_department_id),
    target_job_title_id = VALUES(target_job_title_id),
    evaluation_type = VALUES(evaluation_type),
    kra_weight = VALUES(kra_weight),
    behavior_weight = VALUES(behavior_weight),
    form_code = VALUES(form_code),
    revision_date = VALUES(revision_date),
    effective_date_form = VALUES(effective_date_form),
    status = VALUES(status),
    created_by = VALUES(created_by),
    deleted_at = NULL;

DELETE FROM evaluation_template_positions
WHERE template_id BETWEEN 16001 AND 16012;

INSERT INTO evaluation_template_positions (template_id, job_title_id) VALUES
(16001, 700), (16002, 700),
(16003, 101), (16004, 101),
(16005, 705), (16006, 705),
(16007, 710), (16008, 710),
(16009, 105), (16010, 105),
(16011, 108), (16012, 108);

DELETE FROM evaluation_criteria
WHERE template_id BETWEEN 16001 AND 16012;

-- Manager-level KRA criteria (CEO-created templates).
INSERT INTO evaluation_criteria
    (template_id, section, criterion_name, description, weight, scoring_method, sort_order)
SELECT template_id, 'KRA', criterion_name, description, weight, 'Scale_1_4', sort_order
FROM (
    SELECT 1 sort_order, 'Strategic Execution' criterion_name, 'Translates company priorities into clear departmental plans and measurable results.' description, 25.00 weight
    UNION ALL SELECT 2, 'Department Leadership', 'Provides direction, sound decisions, and consistent leadership to the department.', 20.00
    UNION ALL SELECT 3, 'Operational Control', 'Maintains accurate, timely, compliant, and well-controlled departmental operations.', 20.00
    UNION ALL SELECT 4, 'People Management', 'Develops employees, delegates effectively, and addresses performance concerns promptly.', 20.00
    UNION ALL SELECT 5, 'Process Improvement', 'Identifies and implements practical improvements that support company objectives.', 15.00
) criteria
CROSS JOIN (
    SELECT 16001 template_id UNION ALL SELECT 16002 UNION ALL
    SELECT 16003 UNION ALL SELECT 16004
) manager_templates;

-- Supervisor-level KRA criteria (manager-created templates).
INSERT INTO evaluation_criteria
    (template_id, section, criterion_name, description, weight, scoring_method, sort_order)
SELECT template_id, 'KRA', criterion_name, description, weight, 'Scale_1_4', sort_order
FROM (
    SELECT 1 sort_order, 'Work Planning and Coordination' criterion_name, 'Plans assignments, balances workloads, and keeps daily operations on schedule.' description, 25.00 weight
    UNION ALL SELECT 2, 'Quality and Compliance Control', 'Checks work accuracy and ensures procedures and company policies are followed.', 20.00
    UNION ALL SELECT 3, 'Team Supervision and Coaching', 'Provides clear instructions, useful feedback, and support to team members.', 20.00
    UNION ALL SELECT 4, 'Reports and Issue Resolution', 'Submits reliable reports and resolves or escalates operational issues promptly.', 20.00
    UNION ALL SELECT 5, 'Initiative and Improvement', 'Takes ownership and recommends workable improvements to team processes.', 15.00
) criteria
CROSS JOIN (
    SELECT 16005 template_id UNION ALL SELECT 16006 UNION ALL
    SELECT 16009 UNION ALL SELECT 16010
) supervisor_templates;

-- Staff-level KRA criteria (supervisor-created templates).
INSERT INTO evaluation_criteria
    (template_id, section, criterion_name, description, weight, scoring_method, sort_order)
SELECT template_id, 'KRA', criterion_name, description, weight, 'Scale_1_4', sort_order
FROM (
    SELECT 1 sort_order, 'Job Knowledge' criterion_name, 'Understands assigned duties, procedures, tools, and required documentation.' description, 25.00 weight
    UNION ALL SELECT 2, 'Quality and Accuracy', 'Produces complete, accurate, and properly documented work.', 25.00
    UNION ALL SELECT 3, 'Productivity and Timeliness', 'Completes assigned work within expected deadlines and service standards.', 20.00
    UNION ALL SELECT 4, 'Communication and Teamwork', 'Communicates professionally and works effectively with colleagues and clients.', 15.00
    UNION ALL SELECT 5, 'Learning and Initiative', 'Applies feedback, learns quickly, and takes appropriate initiative.', 15.00
) criteria
CROSS JOIN (
    SELECT 16007 template_id UNION ALL SELECT 16008 UNION ALL
    SELECT 16011 UNION ALL SELECT 16012
) staff_templates;

-- Core behavior criteria used by every probationary form.
INSERT INTO evaluation_criteria
    (template_id, section, criterion_name, kpi_description, weight, scoring_method, sort_order)
SELECT template_id, 'Behavior', criterion_name, kpi_description, 0.00, 'Scale_1_4', sort_order
FROM (
    SELECT 1 sort_order, 'Integrity' criterion_name, 'Acts honestly, protects confidential information, and follows company policies.' kpi_description
    UNION ALL SELECT 2, 'Accountability', 'Takes ownership of decisions, commitments, results, and corrective actions.'
    UNION ALL SELECT 3, 'Customer Service', 'Responds respectfully and provides timely, practical assistance to internal and external clients.'
    UNION ALL SELECT 4, 'Teamwork and Respect', 'Cooperates with others and maintains a professional and respectful workplace.'
) behavior_criteria
CROSS JOIN (
    SELECT 16001 template_id UNION ALL SELECT 16002 UNION ALL
    SELECT 16003 UNION ALL SELECT 16004 UNION ALL
    SELECT 16005 UNION ALL SELECT 16006 UNION ALL
    SELECT 16007 UNION ALL SELECT 16008 UNION ALL
    SELECT 16009 UNION ALL SELECT 16010 UNION ALL
    SELECT 16011 UNION ALL SELECT 16012
) all_templates;

COMMIT;

-- Verification: 12 active templates, each with 5 KRA and 4 Behavior criteria.
SELECT
    et.template_id,
    et.template_name,
    et.evaluation_type,
    et.target_department,
    jt.job_title AS target_position,
    u.username AS created_by_username,
    SUM(ec.section = 'KRA') AS kra_items,
    SUM(ec.section = 'Behavior') AS behavior_items,
    SUM(CASE WHEN ec.section = 'KRA' THEN ec.weight ELSE 0 END) AS kra_item_weight_total
FROM evaluation_templates et
JOIN evaluation_template_positions etp ON etp.template_id = et.template_id
JOIN job_titles jt ON jt.job_title_id = etp.job_title_id
LEFT JOIN users u ON u.user_id = et.created_by
LEFT JOIN evaluation_criteria ec ON ec.template_id = et.template_id
WHERE et.template_id BETWEEN 16001 AND 16012
GROUP BY et.template_id, et.template_name, et.evaluation_type,
         et.target_department, jt.job_title, u.username
ORDER BY et.template_id;
