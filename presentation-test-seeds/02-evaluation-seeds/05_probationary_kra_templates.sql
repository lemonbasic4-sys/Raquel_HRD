SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;
START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS tmp_probationary_template_targets;
CREATE TEMPORARY TABLE tmp_probationary_template_targets (
 dept_id INT, dept_code VARCHAR(10), dept_name VARCHAR(100), creator_code VARCHAR(30),
 position_1 INT, position_2 INT, domain_label VARCHAR(100), PRIMARY KEY(dept_id)
);
INSERT INTO tmp_probationary_template_targets VALUES
(1,'AP','Acquired Properties','DEMO-AP-REG',108,113,'property records and client service'),
(2,'AUD','Audit','DEMO-AUD-REG',206,207,'audit evidence and control testing'),
(3,'BD','Business Development','DEMO-BD-REG',302,303,'market research and business development'),
(4,'COM','Compliance','DEMO-COM-REG',403,404,'regulatory monitoring and compliance records'),
(5,'FIN','Finance','DEMO-FIN-REG',511,516,'financial processing and reconciliation'),
(6,'GS','General Services','DEMO-GS-REG',609,618,'facilities, safety, and support services'),
(7,'HR','Human Resources','HRD-002',713,714,'employee records and HR service delivery'),
(8,'IT','Information Technology','DEMO-IT-REG',809,810,'technology delivery and user support'),
(9,'MKT','Marketing','DEMO-MKT-REG',904,905,'campaign execution and customer communication'),
(10,'OOP','Office of the President','CEO-001',1102,1103,'executive office coordination and records'),
(11,'OPS','Operations','DEMO-OPS-REG',1012,1013,'branch operations and customer service'),
(12,'PUR','Purchasing','DEMO-PUR-REG',1202,1202,'purchase documentation and vendor coordination');

DROP TEMPORARY TABLE IF EXISTS tmp_probationary_types;
CREATE TEMPORARY TABLE tmp_probationary_types(eval_type VARCHAR(20) PRIMARY KEY, phase_label VARCHAR(50));
INSERT INTO tmp_probationary_types VALUES ('Initial','third-month Initial'),('Final','fifth-month Final');

INSERT INTO evaluation_templates
 (template_name,description,target_department,target_department_id,target_job_title_id,
  behavior_framework_code,behavior_framework_version,evaluation_type,individual_workflow,
  assessment_from,assessment_to,kra_weight,behavior_weight,form_code,revision_date,effective_date_form,status,created_by)
SELECT CONCAT('[PRESENTATION] ',p.eval_type,' - ',t.dept_name),
 CONCAT('Presentation probationary ',p.eval_type,' form for ',t.domain_label,'.'),
 t.dept_name,t.dept_id,t.position_1,'CORE-VALUES','1.0',p.eval_type,1,NULL,NULL,80,20,
 CONCAT('PRES-',t.dept_code,'-',UPPER(p.eval_type),'-2026'),'2026-11-23','2026-11-23','Active',
 (SELECT u.user_id FROM users u JOIN employees e ON e.employee_id=u.employee_id
  WHERE e.employee_code=t.creator_code AND u.is_active=1 AND u.deleted_at IS NULL
  ORDER BY (u.role='Employee') DESC,u.user_id LIMIT 1)
FROM tmp_probationary_template_targets t CROSS JOIN tmp_probationary_types p
WHERE NOT EXISTS(SELECT 1 FROM evaluation_templates x WHERE x.form_code=CONCAT('PRES-',t.dept_code,'-',UPPER(p.eval_type),'-2026') AND x.deleted_at IS NULL);

INSERT INTO evaluation_template_positions(template_id,job_title_id)
SELECT et.template_id,pos.job_title_id
FROM evaluation_templates et
JOIN tmp_probationary_template_targets t ON et.form_code IN(CONCAT('PRES-',t.dept_code,'-INITIAL-2026'),CONCAT('PRES-',t.dept_code,'-FINAL-2026'))
JOIN (
 SELECT dept_id,position_1 job_title_id FROM tmp_probationary_template_targets
 UNION SELECT dept_id,position_2 FROM tmp_probationary_template_targets
) pos ON pos.dept_id=t.dept_id
WHERE NOT EXISTS(SELECT 1 FROM evaluation_template_positions x WHERE x.template_id=et.template_id AND x.job_title_id=pos.job_title_id);

-- Historical Initial/Final records for employees who are now Regular retain their current seeded position.
INSERT INTO evaluation_template_positions(template_id,job_title_id)
SELECT DISTINCT et.template_id,e.job_title_id
FROM evaluation_templates et
JOIN tmp_probationary_template_targets t ON et.form_code IN(CONCAT('PRES-',t.dept_code,'-INITIAL-2026'),CONCAT('PRES-',t.dept_code,'-FINAL-2026'))
JOIN employees e ON e.department_id=t.dept_id AND e.employee_code LIKE 'DEMO-%-REG%'
WHERE NOT EXISTS(SELECT 1 FROM evaluation_template_positions x WHERE x.template_id=et.template_id AND x.job_title_id=e.job_title_id);

DROP TEMPORARY TABLE IF EXISTS tmp_probationary_kra_items;
CREATE TEMPORARY TABLE tmp_probationary_kra_items(sort_order INT PRIMARY KEY,item_name VARCHAR(100),item_weight DECIMAL(5,2));
INSERT INTO tmp_probationary_kra_items VALUES
(1,'Work accuracy',9),(2,'Timeliness and productivity',9),(3,'Procedure compliance',9),(4,'Record completeness',9),
(5,'Service responsiveness',8),(6,'Communication quality',8),(7,'Problem identification',8),(8,'Resource stewardship',8),
(9,'Safety and risk awareness',8),(10,'Learning application',8),(11,'Team coordination',8),(12,'Reporting and follow-through',8);

INSERT INTO evaluation_criteria(template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
SELECT et.template_id,'KRA',CONCAT(k.item_name,' - ',t.dept_code),
 CONCAT('Demonstrate ',LOWER(k.item_name),' in assigned ',t.domain_label,' responsibilities.'),
 CONCAT('Provide complete and verifiable evidence of ',LOWER(k.item_name),' during the probationary assessment period.'),
 k.item_weight,'Scale_1_4',k.sort_order
FROM evaluation_templates et JOIN tmp_probationary_template_targets t
 ON et.form_code IN(CONCAT('PRES-',t.dept_code,'-INITIAL-2026'),CONCAT('PRES-',t.dept_code,'-FINAL-2026'))
CROSS JOIN tmp_probationary_kra_items k
WHERE NOT EXISTS(SELECT 1 FROM evaluation_criteria c WHERE c.template_id=et.template_id AND c.section='KRA');

DROP TEMPORARY TABLE IF EXISTS tmp_core_behaviors;
CREATE TEMPORARY TABLE tmp_core_behaviors(sort_order INT PRIMARY KEY,behavior_name VARCHAR(150),kpi TEXT);
INSERT INTO tmp_core_behaviors VALUES
(1,'Positive Attitude','Displays positive attitude at work.'),(2,'Respect','Shows respect to all people in the organization.'),
(3,'Accountability','Takes full responsibility of the job including special task or assignment.'),(4,'Commitment','Demonstrates strong commitment to the job.'),
(5,'Teamwork','Works cooperatively with others in achieving the goals.'),(6,'Integrity','Exhibits honesty and strong moral uprightness.'),
(7,'Continuous Improvement','Provides diligent effort to continuously focus on getting better.'),(8,'Excellent Client Experience','Delivers the service beyond the expectations of internal and external clients.');

INSERT INTO evaluation_criteria(template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
SELECT et.template_id,'Behavior',b.behavior_name,b.kpi,b.kpi,0,'Scale_1_4',b.sort_order
FROM evaluation_templates et JOIN tmp_probationary_template_targets t
 ON et.form_code IN(CONCAT('PRES-',t.dept_code,'-INITIAL-2026'),CONCAT('PRES-',t.dept_code,'-FINAL-2026'))
CROSS JOIN tmp_core_behaviors b
WHERE NOT EXISTS(SELECT 1 FROM evaluation_criteria c WHERE c.template_id=et.template_id AND c.section='Behavior');

DROP TEMPORARY TABLE tmp_core_behaviors;
DROP TEMPORARY TABLE tmp_probationary_kra_items;
DROP TEMPORARY TABLE tmp_probationary_types;
DROP TEMPORARY TABLE tmp_probationary_template_targets;
COMMIT;

SELECT COUNT(*) AS probationary_presentation_templates FROM evaluation_templates WHERE form_code LIKE 'PRES-%' AND evaluation_type IN('Initial','Final');
