-- Optional HRD Annual template seed. Select raquel_hris before importing.
-- Requires organization positions and active Regular head accounts first.
-- Test KRAs only, not client-approved job requirements.
-- Uses current Position Management Reports To, never fixed employee/position IDs.
-- Does not modify employees, reporting lines, submissions, or existing templates.
-- Re-importing skips templates already created by this seed, even if used/archived.
-- REPLACE is used only for NEW rows, never existing parent/criterion rows.

START TRANSACTION;

CREATE TEMPORARY TABLE hrd_annual_seed_heads AS
SELECT u.user_id, e.job_title_id AS head_position_id, d.department_id,
       d.department_name,
       CASE u.role
           WHEN 'President and CEO' THEN 'HR Manager'
           WHEN 'HR Manager' THEN 'HR Supervisor'
           ELSE 'HR Staff'
       END AS target_prefix,
       CASE u.role
           WHEN 'President and CEO' THEN '[TEST HRD ANNUAL] HR Manager'
           WHEN 'HR Manager' THEN '[TEST HRD ANNUAL] HR Supervisor'
           ELSE '[TEST HRD ANNUAL] HR Staff'
       END AS template_name
FROM users u
JOIN employees e ON e.employee_id=u.employee_id
JOIN departments d ON LOWER(TRIM(d.department_name)) IN ('human resources','human resource','hrd')
WHERE u.role IN ('President and CEO','HR Manager','HR Supervisor')
  AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0
  AND e.is_active=1 AND e.deleted_at IS NULL AND e.employment_status='Regular'
  AND (u.role='President and CEO' OR e.department_id=d.department_id)
  AND u.user_id=(SELECT MIN(u2.user_id) FROM users u2
      JOIN employees e2 ON e2.employee_id=u2.employee_id
      WHERE u2.role=u.role AND u2.is_active=1 AND u2.deleted_at IS NULL
        AND COALESCE(u2.account_hold,0)=0 AND e2.is_active=1
        AND e2.deleted_at IS NULL AND e2.employment_status='Regular'
        AND (u2.role='President and CEO' OR e2.department_id=d.department_id));

CREATE TEMPORARY TABLE hrd_annual_seed_new AS
SELECT h.*, MIN(j.job_title_id) AS first_target
FROM hrd_annual_seed_heads h
JOIN job_titles j ON j.reports_to=h.head_position_id
    AND j.department_id=h.department_id AND j.is_active=1
    AND (j.job_title=h.target_prefix OR j.job_title LIKE CONCAT(h.target_prefix,' %'))
WHERE NOT EXISTS (SELECT 1 FROM evaluation_templates t WHERE t.template_name=h.template_name)
GROUP BY h.user_id,h.head_position_id,h.department_id,h.department_name,h.target_prefix,h.template_name;

REPLACE INTO evaluation_templates
    (template_name,description,target_department,target_department_id,target_job_title_id,
     behavior_framework_code,behavior_framework_version,evaluation_type,kra_weight,
     behavior_weight,status,created_by)
SELECT template_name,'Synthetic HRD Annual workflow test template. Replace sample KRAs with approved job requirements before production use.',
    department_name,department_id,first_target,'ANNUAL-CORE','1','Annual',80,20,'Active',user_id
FROM hrd_annual_seed_new;

REPLACE INTO evaluation_template_positions (template_id,job_title_id)
SELECT t.template_id,j.job_title_id
FROM hrd_annual_seed_new h
JOIN evaluation_templates t ON t.template_name=h.template_name AND t.created_by=h.user_id
JOIN job_titles j ON j.reports_to=h.head_position_id AND j.department_id=h.department_id
    AND j.is_active=1 AND (j.job_title=h.target_prefix OR j.job_title LIKE CONCAT(h.target_prefix,' %'));

-- Four sample KRAs per position, totaling 100% within the KRA section.
CREATE TEMPORARY TABLE hrd_annual_seed_kras (
    target_prefix VARCHAR(30), criterion_name VARCHAR(150), kpi_description TEXT,
    weight DECIMAL(5,2), sort_order INT
);
REPLACE INTO hrd_annual_seed_kras VALUES
('HR Manager','HR planning and execution','Deliver the agreed annual HR plan and report progress against approved milestones.',30,1),
('HR Manager','HR operations oversight','Review recruitment, personnel administration, and employee service delivery against agreed targets.',30,2),
('HR Manager','Team development','Coach the HR team and complete scheduled performance and development reviews.',20,3),
('HR Manager','Reporting and records governance','Submit accurate HR management reports and maintain required records within agreed deadlines.',20,4),
('HR Supervisor','Personnel administration','Review assigned personnel records and resolve identified documentation gaps within agreed deadlines.',30,1),
('HR Supervisor','HR service coordination','Coordinate assigned recruitment and employee service activities against agreed schedules.',30,2),
('HR Supervisor','Staff coaching and supervision','Monitor HR staff outputs and provide documented coaching and feedback.',20,3),
('HR Supervisor','HR reporting','Prepare accurate HR operational reports and escalate unresolved issues promptly.',20,4),
('HR Staff','Employee records maintenance','Maintain accurate and complete assigned employee records within agreed turnaround times.',30,1),
('HR Staff','Employee service requests','Process assigned employee requests accurately within agreed service deadlines.',30,2),
('HR Staff','Recruitment and onboarding support','Complete assigned recruitment and onboarding documentation within agreed schedules.',20,3),
('HR Staff','Administrative reporting','Submit complete and accurate assigned HR reports within agreed deadlines.',20,4);

REPLACE INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
SELECT t.template_id,'KRA',k.criterion_name,k.kpi_description,k.kpi_description,k.weight,'Scale_1_4',k.sort_order
FROM hrd_annual_seed_new h
JOIN evaluation_templates t ON t.template_name=h.template_name AND t.created_by=h.user_id
JOIN hrd_annual_seed_kras k ON k.target_prefix=h.target_prefix;

CREATE TEMPORARY TABLE hrd_annual_seed_values (
    sort_order INT, criterion_name VARCHAR(150), kpi_description TEXT
);
REPLACE INTO hrd_annual_seed_values VALUES
(1,'Positive Attitude','Displays positive attitude at work.'),
(2,'Respect','Shows respect to all people in the organization.'),
(3,'Accountability','Takes full responsibility of the job including special task or assignment.'),
(4,'Commitment','Demonstrates strong commitment to the job.'),
(5,'Teamwork','Works cooperatively with others in achieving the goals.'),
(6,'Integrity','Exhibits honesty and strong moral uprightness.'),
(7,'Continuous Improvement','Provides diligent effort to continuously focus on getting better.'),
(8,'Excellent Client Experience','Delivers the service beyond the expectations of the internal and external clients.');

REPLACE INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
SELECT t.template_id,'Behavior',v.criterion_name,v.kpi_description,v.kpi_description,0,'Scale_1_4',v.sort_order
FROM hrd_annual_seed_new h
JOIN evaluation_templates t ON t.template_name=h.template_name AND t.created_by=h.user_id
CROSS JOIN hrd_annual_seed_values v;

COMMIT;

-- Verification: expect three templates when all three head accounts and reporting
-- lines exist. Missing templates mean the required active head/position is absent.
SELECT t.template_name,u.role AS creator_role,
       GROUP_CONCAT(j.job_title ORDER BY j.job_title SEPARATOR ', ') AS target_positions
FROM evaluation_templates t
JOIN users u ON u.user_id=t.created_by
JOIN evaluation_template_positions tp ON tp.template_id=t.template_id
JOIN job_titles j ON j.job_title_id=tp.job_title_id
WHERE t.template_name IN ('[TEST HRD ANNUAL] HR Manager','[TEST HRD ANNUAL] HR Supervisor','[TEST HRD ANNUAL] HR Staff')
GROUP BY t.template_id,t.template_name,u.role;

DROP TEMPORARY TABLE hrd_annual_seed_values;
DROP TEMPORARY TABLE hrd_annual_seed_kras;
DROP TEMPORARY TABLE hrd_annual_seed_new;
DROP TEMPORARY TABLE hrd_annual_seed_heads;
