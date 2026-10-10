-- Narrow test seed: create only the Initial probationary templates needed by
-- the HRD-P01/P02 and AP-P01/P02 evaluation-flow test employees.
-- Does not replace or delete any existing templates or criteria.
USE raquel_hris;

INSERT INTO evaluation_templates (
    template_name, description, target_department, target_department_id,
    target_job_title_id, behavior_framework_code, behavior_framework_version,
    evaluation_type, kra_weight, behavior_weight, form_code,
    revision_date, effective_date_form, status, created_by
)
SELECT
    seed.template_name, seed.description, seed.department_name, d.department_id,
    seed.job_title_id, NULL, NULL, 'Initial', 80.00, 20.00, seed.form_code,
    '2026-10-10', '2026-10-10', 'Active',
    (SELECT u.user_id FROM users u
     WHERE u.role = 'HR Manager' AND u.employee_id = 101 AND u.is_active = 1
       AND COALESCE(u.account_hold, 0) = 0 LIMIT 1)
FROM (
    SELECT 'Human Resources Initial Probationary Evaluation' AS template_name,
           'Early employment review of role understanding, onboarding progress, and initial performance in people operations, recruitment, employee relations, learning, compensation, records, and policy support.' AS description,
           'Human Resources' AS department_name, 711 AS job_title_id, 'HR-INI-2026' AS form_code
    UNION ALL
    SELECT 'Acquired Properties Initial Probationary Evaluation',
           'Early employment review of role understanding, onboarding progress, and initial performance in acquired asset disposition, appraisal, documentation, inventory, custody, and preservation.',
           'Acquired Properties', 109, 'AP-INI-2026'
) AS seed
JOIN departments d ON d.department_name = seed.department_name
WHERE NOT EXISTS (
    SELECT 1 FROM evaluation_templates existing
    WHERE existing.template_name = seed.template_name
      AND existing.evaluation_type = 'Initial'
      AND existing.deleted_at IS NULL
);

INSERT INTO evaluation_criteria (
    template_id, section, criterion_name, description, kpi_description,
    weight, scoring_method, sort_order
)
SELECT et.template_id, c.section, c.criterion_name, c.description, c.description,
       c.weight, 'Scale_1_4', c.sort_order
FROM evaluation_templates et
JOIN (
    SELECT 'Human Resources Initial Probationary Evaluation' AS template_name, 'KRA' AS section,
           'Role Knowledge and Onboarding Completion' AS criterion_name,
           'Measures understanding of assigned duties, tools, forms, policies, and department procedures. Scope: people operations, recruitment, employee relations, learning, compensation, records, and policy support.' AS description,
           20.00 AS weight, 1 AS sort_order
    UNION ALL SELECT 'Human Resources Initial Probationary Evaluation','KRA','Initial Work Output Quality','Evaluates accuracy, completeness, and timeliness of early assigned outputs under normal supervision. Scope: people operations, recruitment, employee relations, learning, compensation, records, and policy support.',20.00,2
    UNION ALL SELECT 'Human Resources Initial Probationary Evaluation','KRA','Learning Agility and Coachability','Assesses ability to absorb feedback, apply corrections, ask appropriate questions, and improve quickly. Scope: people operations, recruitment, employee relations, learning, compensation, records, and policy support.',20.00,3
    UNION ALL SELECT 'Human Resources Initial Probationary Evaluation','KRA','Attendance, Reliability, and Work Discipline','Reviews punctuality, attendance, dependability, preparation, and compliance with work rules. Scope: people operations, recruitment, employee relations, learning, compensation, records, and policy support.',20.00,4
    UNION ALL SELECT 'Human Resources Initial Probationary Evaluation','KRA','Team Fit and Service Orientation','Measures cooperation with teammates, communication habits, and readiness to serve internal or external clients. Scope: people operations, recruitment, employee relations, learning, compensation, records, and policy support.',20.00,5
    UNION ALL SELECT 'Human Resources Initial Probationary Evaluation','Behavior','Positive Attitude','Displays positive attitude at work.',12.50,6
    UNION ALL SELECT 'Human Resources Initial Probationary Evaluation','Behavior','Respect','Shows respect to all people in the organization.',12.50,7
    UNION ALL SELECT 'Human Resources Initial Probationary Evaluation','Behavior','Accountability','Takes full responsibility of the job including special task or assignment.',12.50,8
    UNION ALL SELECT 'Human Resources Initial Probationary Evaluation','Behavior','Commitment','Demonstrates strong commitment to the job.',12.50,9
    UNION ALL SELECT 'Human Resources Initial Probationary Evaluation','Behavior','Teamwork','Works cooperatively with others in achieving the goals.',12.50,10
    UNION ALL SELECT 'Human Resources Initial Probationary Evaluation','Behavior','Integrity','Exhibits honesty and strong moral uprightness.',12.50,11
    UNION ALL SELECT 'Human Resources Initial Probationary Evaluation','Behavior','Continuous Improvement','Provides diligent effort to continuously focus on getting better.',12.50,12
    UNION ALL SELECT 'Human Resources Initial Probationary Evaluation','Behavior','Excellent Client Experience','Delivers the service beyond the expectations of the internal and external clients.',12.50,13
    UNION ALL SELECT 'Acquired Properties Initial Probationary Evaluation','KRA','Role Knowledge and Onboarding Completion','Measures understanding of assigned duties, tools, forms, policies, and department procedures. Scope: acquired asset disposition, appraisal, documentation, inventory, custody, and preservation.',20.00,1
    UNION ALL SELECT 'Acquired Properties Initial Probationary Evaluation','KRA','Initial Work Output Quality','Evaluates accuracy, completeness, and timeliness of early assigned outputs under normal supervision. Scope: acquired asset disposition, appraisal, documentation, inventory, custody, and preservation.',20.00,2
    UNION ALL SELECT 'Acquired Properties Initial Probationary Evaluation','KRA','Learning Agility and Coachability','Assesses ability to absorb feedback, apply corrections, ask appropriate questions, and improve quickly. Scope: acquired asset disposition, appraisal, documentation, inventory, custody, and preservation.',20.00,3
    UNION ALL SELECT 'Acquired Properties Initial Probationary Evaluation','KRA','Attendance, Reliability, and Work Discipline','Reviews punctuality, attendance, dependability, preparation, and compliance with work rules. Scope: acquired asset disposition, appraisal, documentation, inventory, custody, and preservation.',20.00,4
    UNION ALL SELECT 'Acquired Properties Initial Probationary Evaluation','KRA','Team Fit and Service Orientation','Measures cooperation with teammates, communication habits, and readiness to serve internal or external clients. Scope: acquired asset disposition, appraisal, documentation, inventory, custody, and preservation.',20.00,5
    UNION ALL SELECT 'Acquired Properties Initial Probationary Evaluation','Behavior','Positive Attitude','Displays positive attitude at work.',12.50,6
    UNION ALL SELECT 'Acquired Properties Initial Probationary Evaluation','Behavior','Respect','Shows respect to all people in the organization.',12.50,7
    UNION ALL SELECT 'Acquired Properties Initial Probationary Evaluation','Behavior','Accountability','Takes full responsibility of the job including special task or assignment.',12.50,8
    UNION ALL SELECT 'Acquired Properties Initial Probationary Evaluation','Behavior','Commitment','Demonstrates strong commitment to the job.',12.50,9
    UNION ALL SELECT 'Acquired Properties Initial Probationary Evaluation','Behavior','Teamwork','Works cooperatively with others in achieving the goals.',12.50,10
    UNION ALL SELECT 'Acquired Properties Initial Probationary Evaluation','Behavior','Integrity','Exhibits honesty and strong moral uprightness.',12.50,11
    UNION ALL SELECT 'Acquired Properties Initial Probationary Evaluation','Behavior','Continuous Improvement','Provides diligent effort to continuously focus on getting better.',12.50,12
    UNION ALL SELECT 'Acquired Properties Initial Probationary Evaluation','Behavior','Excellent Client Experience','Delivers the service beyond the expectations of the internal and external clients.',12.50,13
) AS c ON c.template_name = et.template_name
WHERE et.evaluation_type = 'Initial'
  AND et.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM evaluation_criteria existing
      WHERE existing.template_id = et.template_id
        AND existing.section = c.section
        AND existing.criterion_name = c.criterion_name
  );
