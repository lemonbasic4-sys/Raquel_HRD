-- TEST ONLY. Import after the organization and major employee seeds.
-- Each head creates the Annual form for the next AP position:
-- President and CEO -> VP for Acquired Properties (cross-department KRA head)
-- AP VP -> AP Manager I; AP Manager I -> AP Supervisor I;
-- AP Supervisor I -> AP Staff I.
-- Every form has 11 sample KRAs and eight Core Behaviors & Values.
-- This does not start a cycle or create any evaluation submissions.
-- Existing templates, ratings, and approvals are never replaced.
SET NAMES utf8mb4;
START TRANSACTION;

CREATE TEMPORARY TABLE ap_seed_targets (
    position_id INT PRIMARY KEY,
    head_position_id INT NOT NULL,
    creator_role VARCHAR(40) NOT NULL,
    template_name VARCHAR(150) NOT NULL
);
INSERT INTO ap_seed_targets VALUES
(100,1100,'President and CEO','[TEST AP ANNUAL] VP for Acquired Properties'),
(101,100,'Employee','[TEST AP ANNUAL] AP Manager I'),
(105,101,'Employee','[TEST AP ANNUAL] AP Supervisor I'),
(108,105,'Employee','[TEST AP ANNUAL] AP Staff I');

CREATE TEMPORARY TABLE ap_seed_new AS
SELECT target.position_id,target.template_name,department.department_id,
       department.department_name,MIN(creator.user_id) AS creator_id
FROM ap_seed_targets target
JOIN job_titles position ON position.job_title_id=target.position_id
JOIN departments department ON department.department_id=position.department_id
JOIN employees head ON head.job_title_id=target.head_position_id
JOIN users creator ON creator.employee_id=head.employee_id AND creator.role=target.creator_role
WHERE department.department_name='Acquired Properties'
  AND position.reports_to=target.head_position_id AND position.is_active=1
  AND head.is_active=1 AND head.deleted_at IS NULL AND head.employment_status='Regular'
  AND creator.is_active=1 AND creator.deleted_at IS NULL AND COALESCE(creator.account_hold,0)=0
  AND NOT EXISTS (SELECT 1 FROM evaluation_templates existing
                  WHERE existing.template_name=target.template_name)
GROUP BY target.position_id,target.template_name,department.department_id,department.department_name;

INSERT INTO evaluation_templates
    (template_name,description,target_department,target_department_id,target_job_title_id,
     behavior_framework_code,behavior_framework_version,evaluation_type,kra_weight,
     behavior_weight,status,created_by)
SELECT template_name,
       'Synthetic Acquired Properties Annual dry-run form. Replace these sample KRAs with approved job requirements before production use.',
       department_name,department_id,position_id,'ANNUAL-CORE','1','Annual',80,20,'Active',creator_id
FROM ap_seed_new;

INSERT INTO evaluation_template_positions (template_id,job_title_id)
SELECT template.template_id,seed.position_id
FROM ap_seed_new seed
JOIN evaluation_templates template ON template.template_name=seed.template_name
    AND template.created_by=seed.creator_id;

CREATE TEMPORARY TABLE ap_seed_kras (
    position_id INT NOT NULL,sort_order INT NOT NULL,
    criterion_name VARCHAR(150) NOT NULL,kpi_description TEXT NOT NULL,
    weight DECIMAL(5,2) NOT NULL
);
-- Eleven positive weights per form: ten at 9% and the final item at 10%.
INSERT INTO ap_seed_kras VALUES
(100,1,'AP strategy and annual plan','Set the acquired-properties plan and track approved annual priorities.',9),
(100,2,'Portfolio performance','Review property portfolio results against approved targets.',9),
(100,3,'Acquisition governance','Oversee property acquisition decisions and required approvals.',9),
(100,4,'Disposition strategy','Approve sales or disposition plans for eligible properties.',9),
(100,5,'Valuation oversight','Review valuation practices and material price recommendations.',9),
(100,6,'Risk and compliance oversight','Escalate legal, title, and compliance risks within agreed timelines.',9),
(100,7,'Budget stewardship','Monitor AP spending and resource use against the approved budget.',9),
(100,8,'Cross-functional coordination','Resolve priority work with Legal, Finance, Operations, and executives.',9),
(100,9,'Management reporting','Submit accurate portfolio and performance reports on schedule.',9),
(100,10,'Team leadership','Coach AP leaders and complete planned performance reviews.',9),
(100,11,'Client and stakeholder outcomes','Address material stakeholder issues and improve service outcomes.',10),
(101,1,'AP operating plan','Translate the approved AP strategy into team milestones and assignments.',9),
(101,2,'Property inventory control','Maintain a current and reconciled inventory of acquired properties.',9),
(101,3,'Acquisition case review','Review assigned acquisition cases for completeness and decision readiness.',9),
(101,4,'Disposition execution','Coordinate approved property sales and disposition activities.',9),
(101,5,'Valuation review','Check valuation evidence and recommend prices for approval.',9),
(101,6,'Title and document control','Track titles, records, and missing documents to resolution.',9),
(101,7,'Risk escalation','Raise legal, safety, and compliance concerns promptly.',9),
(101,8,'Budget monitoring','Monitor assigned AP costs and explain material variances.',9),
(101,9,'Management reporting','Provide complete AP progress and portfolio reports by deadline.',9),
(101,10,'Supervisor development','Guide AP supervisors and review their delivery against targets.',9),
(101,11,'Stakeholder coordination','Resolve assigned issues with branches, clients, and partner teams.',10),
(105,1,'Work allocation','Assign property cases and monitor completion against schedules.',9),
(105,2,'Inventory accuracy','Verify updates to property records and investigate discrepancies.',9),
(105,3,'Site inspection coordination','Schedule and document required site inspections.',9),
(105,4,'Document completeness','Check titles, forms, and supporting records before escalation.',9),
(105,5,'Valuation support','Compile property facts and evidence for valuation review.',9),
(105,6,'Disposition support','Track approved sale or disposal actions and outstanding requirements.',9),
(105,7,'Issue escalation','Escalate legal, safety, or ownership issues with clear evidence.',9),
(105,8,'Turnaround performance','Monitor case processing times and address avoidable delays.',9),
(105,9,'Operational reporting','Submit accurate case and team progress reports on schedule.',9),
(105,10,'Staff coaching','Provide AP staff with documented guidance and performance feedback.',9),
(105,11,'Service coordination','Respond to assigned branch and client requests within service targets.',10),
(108,1,'Property record updates','Record assigned property information accurately and promptly.',9),
(108,2,'Document filing','File property records and supporting documents in the correct case.',9),
(108,3,'Site visit preparation','Prepare schedules, checklists, and records for assigned site visits.',9),
(108,4,'Inspection documentation','Capture complete observations and required evidence from inspections.',9),
(108,5,'Inventory reconciliation','Compare assigned inventory entries with supporting records.',9),
(108,6,'Valuation data support','Gather complete property facts for the valuation reviewer.',9),
(108,7,'Disposition documentation','Prepare required paperwork for approved property dispositions.',9),
(108,8,'Issue reporting','Report missing documents, safety issues, and discrepancies promptly.',9),
(108,9,'Task turnaround','Complete assigned cases and requests within agreed deadlines.',9),
(108,10,'Progress reporting','Submit accurate updates on assigned property cases.',9),
(108,11,'Client service','Handle assigned internal and external inquiries professionally.',10);

INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
SELECT template.template_id,'KRA',kra.criterion_name,kra.kpi_description,
       kra.kpi_description,kra.weight,'Scale_1_4',kra.sort_order
FROM ap_seed_new seed
JOIN evaluation_templates template ON template.template_name=seed.template_name
    AND template.created_by=seed.creator_id
JOIN ap_seed_kras kra ON kra.position_id=seed.position_id;

CREATE TEMPORARY TABLE ap_seed_values (
    sort_order INT PRIMARY KEY,criterion_name VARCHAR(150) NOT NULL,kpi_description TEXT NOT NULL
);
INSERT INTO ap_seed_values VALUES
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
SELECT template.template_id,'Behavior',value.criterion_name,value.kpi_description,
       value.kpi_description,0,'Scale_1_4',value.sort_order
FROM ap_seed_new seed
JOIN evaluation_templates template ON template.template_name=seed.template_name
    AND template.created_by=seed.creator_id
CROSS JOIN ap_seed_values value;

COMMIT;
DROP TEMPORARY TABLE ap_seed_values;
DROP TEMPORARY TABLE ap_seed_kras;
DROP TEMPORARY TABLE ap_seed_new;
DROP TEMPORARY TABLE ap_seed_targets;

-- Expect four rows, each with 11 KRAs, KRA weight 100, and eight Behaviors.
SELECT template.template_name,CONCAT(head.first_name,' ',head.last_name) AS created_by,
       position.job_title AS target_position,
       SUM(criterion.section='KRA') AS kra_items,
       SUM(CASE WHEN criterion.section='KRA' THEN criterion.weight ELSE 0 END) AS kra_weight_total,
       SUM(criterion.section='Behavior') AS behavior_items
FROM evaluation_templates template
JOIN users creator ON creator.user_id=template.created_by
JOIN employees head ON head.employee_id=creator.employee_id
JOIN evaluation_template_positions target ON target.template_id=template.template_id
JOIN job_titles position ON position.job_title_id=target.job_title_id
JOIN evaluation_criteria criterion ON criterion.template_id=template.template_id
WHERE template.template_name LIKE '[TEST AP ANNUAL] %'
GROUP BY template.template_id,template.template_name,head.first_name,head.last_name,position.job_title
ORDER BY position.rank_category_id,position.job_title_id;
