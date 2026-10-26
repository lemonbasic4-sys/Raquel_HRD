-- TEST ONLY. Import after the organization and major employee seeds.
-- Adds a sample Annual form for each seeded regular position outside AP and Audit.
-- These generic KRAs test routing and scoring; replace them with approved job requirements.
-- Does not start cycles or replace existing forms and results.
SET NAMES utf8mb4;
START TRANSACTION;

CREATE TEMPORARY TABLE remaining_seed_new AS
SELECT e.job_title_id AS position_id,j.job_title,d.department_id,d.department_name,
       CONCAT('[TEST ',d.department_name,' ANNUAL] ',j.job_title) AS template_name,
       COALESCE(MIN(IF(creator.role IN ('President and CEO','HR Manager','HR Supervisor'),creator.user_id,NULL)),
                MIN(IF(creator.role='Employee',creator.user_id,NULL))) AS creator_id
FROM employees e
JOIN job_titles j ON j.job_title_id=e.job_title_id AND j.is_active=1
JOIN departments d ON d.department_id=e.department_id
JOIN employees head ON head.job_title_id=j.reports_to
    AND head.is_active=1 AND head.deleted_at IS NULL AND head.employment_status='Regular'
JOIN users creator ON creator.employee_id=head.employee_id
    AND creator.role IN ('Employee','President and CEO','HR Manager','HR Supervisor')
    AND creator.is_active=1 AND creator.deleted_at IS NULL AND COALESCE(creator.account_hold,0)=0
WHERE e.employee_id BETWEEN 20000 AND 21999
  AND e.is_active=1 AND e.deleted_at IS NULL AND e.employment_status='Regular'
  AND j.reports_to IS NOT NULL
  AND d.department_name IN ('Business Development','Compliance','Finance',
       'General Services','Human Resources','Information Technology','Marketing','Office of the President',
       'Operations','Purchasing')
  AND NOT EXISTS (SELECT 1 FROM evaluation_templates existing
      WHERE existing.template_name=CONCAT('[TEST ',d.department_name,' ANNUAL] ',j.job_title))
GROUP BY e.job_title_id,j.job_title,d.department_id,d.department_name;

INSERT INTO evaluation_templates
    (template_name,description,target_department,target_department_id,target_job_title_id,
     behavior_framework_code,behavior_framework_version,evaluation_type,kra_weight,
     behavior_weight,status,created_by)
SELECT template_name,
       'Synthetic department Annual dry-run form. Replace all sample KRAs with approved position requirements before production use.',
       department_name,department_id,position_id,'ANNUAL-CORE','1','Annual',80,20,'Active',creator_id
FROM remaining_seed_new WHERE creator_id IS NOT NULL;

INSERT INTO evaluation_template_positions (template_id,job_title_id)
SELECT t.template_id,s.position_id FROM remaining_seed_new s
JOIN evaluation_templates t ON t.template_name=s.template_name AND t.created_by=s.creator_id;

CREATE TEMPORARY TABLE remaining_seed_kras (
    sort_order INT PRIMARY KEY, criterion_name VARCHAR(120) NOT NULL,
    kpi_description VARCHAR(500) NOT NULL, weight DECIMAL(5,2) NOT NULL
);
INSERT INTO remaining_seed_kras VALUES
(1,'Work planning','Plan assigned work and agree on measurable priorities.',9),
(2,'Task delivery','Complete assigned responsibilities within agreed timelines.',9),
(3,'Work quality','Produce accurate and complete work for review.',9),
(4,'Policy compliance','Follow applicable company policies and controls.',9),
(5,'Risk escalation','Raise material risks or blockers promptly.',9),
(6,'Record keeping','Maintain traceable records for assigned work.',9),
(7,'Stakeholder service','Respond to assigned internal and external requests.',9),
(8,'Team coordination','Coordinate required handoffs with colleagues and heads.',9),
(9,'Progress reporting','Provide timely and accurate progress updates.',9),
(10,'Problem resolution','Investigate and resolve assigned issues.',9),
(11,'Role development','Apply feedback and improve position-specific skills.',10);

INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
SELECT t.template_id,'KRA',CONCAT(k.criterion_name,' - ',s.job_title),
       CONCAT(k.kpi_description,' Position: ',s.job_title,'.'),
       CONCAT(k.kpi_description,' Position: ',s.job_title,'.'),
       k.weight,'Scale_1_4',k.sort_order
FROM remaining_seed_new s JOIN evaluation_templates t
    ON t.template_name=s.template_name AND t.created_by=s.creator_id
CROSS JOIN remaining_seed_kras k;

CREATE TEMPORARY TABLE remaining_seed_values (
    sort_order INT PRIMARY KEY,criterion_name VARCHAR(150) NOT NULL,kpi_description TEXT NOT NULL
);
INSERT INTO remaining_seed_values VALUES
(1,'Positive Attitude','Displays positive attitude at work.'),
(2,'Respect','Shows respect to all people in the organization.'),
(3,'Accountability','Takes full responsibility of the job including special task or assignment.'),
(4,'Commitment','Demonstrates strong commitment to the job.'),
(5,'Teamwork','Works cooperatively with others in achieving the goals.'),
(6,'Integrity','Exhibits honesty and strong moral uprightness.'),
(7,'Continuous Improvement','Provides diligent effort to continuously focus on getting better.'),
(8,'Excellent Client Experience','Delivers the service beyond the expectations of the internal and external clients.');

INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
SELECT t.template_id,'Behavior',v.criterion_name,v.kpi_description,
       v.kpi_description,0,'Scale_1_4',v.sort_order
FROM remaining_seed_new s JOIN evaluation_templates t
    ON t.template_name=s.template_name AND t.created_by=s.creator_id
CROSS JOIN remaining_seed_values v;

COMMIT;
DROP TEMPORARY TABLE remaining_seed_values;
DROP TEMPORARY TABLE remaining_seed_kras;
DROP TEMPORARY TABLE remaining_seed_new;

SELECT d.department_name,COUNT(DISTINCT t.template_id) AS sample_forms
FROM evaluation_templates t JOIN departments d ON d.department_id=t.target_department_id
WHERE t.template_name LIKE '[TEST % ANNUAL] %'
  AND d.department_name IN ('Business Development','Compliance','Finance',
       'General Services','Human Resources','Information Technology','Marketing','Office of the President',
       'Operations','Purchasing')
GROUP BY d.department_id,d.department_name ORDER BY d.department_name;
