-- TEST ONLY. Import after organization and major employee seeds.
-- President/CEO -> Audit Manager I -> Audit Manager II -> Audit Supervisor I -> Auditor I.
-- Sample KRAs require review against the client's approved job requirements.
-- Does not start a cycle or alter existing forms, scores, or history.
SET NAMES utf8mb4;
START TRANSACTION;

CREATE TEMPORARY TABLE audit_seed_targets (
    position_id INT PRIMARY KEY, head_position_id INT NOT NULL,
    creator_role VARCHAR(40) NOT NULL, template_name VARCHAR(150) NOT NULL
);
INSERT INTO audit_seed_targets VALUES
(200,1100,'President and CEO','[TEST AUDIT ANNUAL] Audit Manager I'),
(201,200,'Employee','[TEST AUDIT ANNUAL] Audit Manager II'),
(203,201,'Employee','[TEST AUDIT ANNUAL] Audit Supervisor I'),
(206,203,'Employee','[TEST AUDIT ANNUAL] Auditor I');

CREATE TEMPORARY TABLE audit_seed_new AS
SELECT target.position_id,target.template_name,department.department_id,
       department.department_name,MIN(creator.user_id) AS creator_id
FROM audit_seed_targets target
JOIN job_titles position ON position.job_title_id=target.position_id
JOIN departments department ON department.department_id=position.department_id
JOIN employees head ON head.job_title_id=target.head_position_id
JOIN users creator ON creator.employee_id=head.employee_id AND creator.role=target.creator_role
WHERE department.department_name='Audit'
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
       'Synthetic Audit Annual dry-run form. Replace these sample KRAs with approved job requirements before production use.',
       department_name,department_id,position_id,'ANNUAL-CORE','1','Annual',80,20,'Active',creator_id
FROM audit_seed_new;

INSERT INTO evaluation_template_positions (template_id,job_title_id)
SELECT template.template_id,seed.position_id
FROM audit_seed_new seed
JOIN evaluation_templates template ON template.template_name=seed.template_name
    AND template.created_by=seed.creator_id;

CREATE TEMPORARY TABLE audit_seed_kras (
    position_id INT NOT NULL,sort_order INT NOT NULL,
    criterion_name VARCHAR(150) NOT NULL,kpi_description TEXT NOT NULL,
    weight DECIMAL(5,2) NOT NULL
);
INSERT INTO audit_seed_kras VALUES
(200,1,'Annual audit plan','Set risk-based audit priorities and obtain required approval.',9),
(200,2,'Audit coverage','Track completion of approved audits across assigned areas.',9),
(200,3,'Risk assessment','Review material operational and control risks for the audit plan.',9),
(200,4,'Audit methodology','Maintain consistent evidence and review standards.',9),
(200,5,'Finding quality','Approve clear, supported findings and practical recommendations.',9),
(200,6,'Management reporting','Issue complete audit reports within agreed timelines.',9),
(200,7,'Corrective action oversight','Monitor resolution of significant findings.',9),
(200,8,'Independence and ethics','Protect audit independence and confidential information.',9),
(200,9,'Stakeholder coordination','Coordinate priority audits with executives and process owners.',9),
(200,10,'Team development','Coach audit leaders and review their performance.',9),
(200,11,'Resource stewardship','Use audit resources against the approved plan and budget.',10),
(201,1,'Audit work program','Translate the annual plan into executable audit assignments.',9),
(201,2,'Engagement scheduling','Manage audit milestones and team capacity.',9),
(201,3,'Control review','Assess key controls in assigned engagements.',9),
(201,4,'Evidence review','Check evidence for sufficiency and traceability.',9),
(201,5,'Finding validation','Confirm findings with process owners before reporting.',9),
(201,6,'Report drafting','Prepare accurate reports and recommendations.',9),
(201,7,'Remediation follow-up','Track agreed corrective actions to closure.',9),
(201,8,'Risk escalation','Escalate material control failures promptly.',9),
(201,9,'Stakeholder communication','Keep audit clients informed of scope and progress.',9),
(201,10,'Supervisor coaching','Guide supervisors and review assigned outputs.',9),
(201,11,'Quality assurance','Resolve review comments and maintain audit file quality.',10),
(203,1,'Fieldwork planning','Plan assigned audit fieldwork against scope and deadlines.',9),
(203,2,'Test design','Prepare tests that address identified control risks.',9),
(203,3,'Work allocation','Assign audit procedures and monitor completion.',9),
(203,4,'Evidence sufficiency','Review working papers and supporting records.',9),
(203,5,'Exception validation','Confirm exceptions and their causes with process owners.',9),
(203,6,'Finding documentation','Record criteria, condition, cause, and impact clearly.',9),
(203,7,'Issue escalation','Raise significant findings to the audit manager promptly.',9),
(203,8,'Fieldwork timeliness','Meet agreed engagement milestones.',9),
(203,9,'Follow-up testing','Verify implementation of assigned corrective actions.',9),
(203,10,'Auditor coaching','Provide feedback and review auditor work.',9),
(203,11,'File completion','Close audit files with complete evidence and approvals.',10),
(206,1,'Audit preparation','Understand assigned procedures and engagement scope.',9),
(206,2,'Document request','Obtain complete records needed for assigned tests.',9),
(206,3,'Control testing','Perform assigned control tests accurately.',9),
(206,4,'Evidence recording','Document sources and results in working papers.',9),
(206,5,'Data reconciliation','Compare records and explain discrepancies.',9),
(206,6,'Exception reporting','Report identified exceptions with supporting facts.',9),
(206,7,'Interview notes','Capture accurate discussions with process owners.',9),
(206,8,'Confidentiality','Protect sensitive records and audit information.',9),
(206,9,'Task turnaround','Complete assigned procedures within agreed deadlines.',9),
(206,10,'Review response','Resolve supervisor review comments promptly.',9),
(206,11,'Follow-up support','Assist in verifying corrective actions and closure evidence.',10);

INSERT INTO evaluation_criteria
    (template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
SELECT template.template_id,'KRA',kra.criterion_name,kra.kpi_description,
       kra.kpi_description,kra.weight,'Scale_1_4',kra.sort_order
FROM audit_seed_new seed
JOIN evaluation_templates template ON template.template_name=seed.template_name
    AND template.created_by=seed.creator_id
JOIN audit_seed_kras kra ON kra.position_id=seed.position_id;

CREATE TEMPORARY TABLE audit_seed_values (
    sort_order INT PRIMARY KEY,criterion_name VARCHAR(150) NOT NULL,kpi_description TEXT NOT NULL
);
INSERT INTO audit_seed_values VALUES
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
FROM audit_seed_new seed
JOIN evaluation_templates template ON template.template_name=seed.template_name
    AND template.created_by=seed.creator_id
CROSS JOIN audit_seed_values value;

COMMIT;
DROP TEMPORARY TABLE audit_seed_values;
DROP TEMPORARY TABLE audit_seed_kras;
DROP TEMPORARY TABLE audit_seed_new;
DROP TEMPORARY TABLE audit_seed_targets;

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
WHERE template.template_name LIKE '[TEST AUDIT ANNUAL] %'
GROUP BY template.template_id,template.template_name,head.first_name,head.last_name,position.job_title
ORDER BY position.rank_category_id,position.job_title_id;
