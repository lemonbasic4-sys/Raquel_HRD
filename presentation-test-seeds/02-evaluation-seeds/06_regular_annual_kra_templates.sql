SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;
START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS tmp_annual_template_targets;
CREATE TEMPORARY TABLE tmp_annual_template_targets (
 target_code VARCHAR(30) PRIMARY KEY,dept_id INT,dept_code VARCHAR(10),dept_name VARCHAR(100),
 creator_code VARCHAR(30),job_title_id INT,domain_label VARCHAR(120),form_code VARCHAR(50)
);
INSERT INTO tmp_annual_template_targets VALUES
('AP-001',1,'AP','Acquired Properties','CEO-001',100,'acquired properties executive leadership','PRES-AP-VP-ANNUAL'),
('DEMO-AP-REG',1,'AP','Acquired Properties','AP-001',101,'property portfolio management','PRES-AP-REG-ANNUAL'),
('DEMO-AUD-REG',2,'AUD','Audit','CEO-001',200,'department audit leadership','PRES-AUD-MGR-ANNUAL'),
('DEMO-AUD-REG2',2,'AUD','Audit','DEMO-AUD-REG',201,'audit planning and evidence review','PRES-AUD-PEER-ANNUAL'),
('DEMO-BD-REG',3,'BD','Business Development','CEO-001',300,'business development leadership','PRES-BD-MGR-ANNUAL'),
('DEMO-BD-REG2',3,'BD','Business Development','DEMO-BD-REG',302,'market research and opportunity support','PRES-BD-PEER-ANNUAL'),
('DEMO-COM-REG',4,'COM','Compliance','CEO-001',400,'compliance leadership and governance','PRES-COM-LEAD-ANNUAL'),
('DEMO-COM-REG2',4,'COM','Compliance','DEMO-COM-REG',401,'regulatory monitoring and reporting','PRES-COM-PEER-ANNUAL'),
('DEMO-FIN-REG',5,'FIN','Finance','CEO-001',501,'accounting management and controls','PRES-FIN-MGR-ANNUAL'),
('DEMO-FIN-REG2',5,'FIN','Finance','DEMO-FIN-REG',502,'accounting supervision and reconciliation','PRES-FIN-PEER-ANNUAL'),
('DEMO-GS-REG',6,'GS','General Services','CEO-001',601,'general services management','PRES-GS-MGR-ANNUAL'),
('DEMO-GS-REG2',6,'GS','General Services','DEMO-GS-REG',605,'facilities and support supervision','PRES-GS-PEER-ANNUAL'),
('HRD-001',7,'HR','Human Resources','CEO-001',700,'human resources management and governance','PRES-HR-MGR-ANNUAL'),
('HRD-002',7,'HR','Human Resources','HRD-001',705,'human resources supervision and coordination','PRES-HR-SUP-ANNUAL'),
('HRD-003',7,'HR','Human Resources','HRD-002',711,'human resources records and staff support','PRES-HR-STAFF-ANNUAL'),
('DEMO-HR-REG',7,'HR','Human Resources','HRD-002',712,'HR records and employee services','PRES-HR-REG-ANNUAL'),
('DEMO-IT-REG',8,'IT','Information Technology','CEO-001',800,'technology management and service delivery','PRES-IT-MGR-ANNUAL'),
('DEMO-IT-REG2',8,'IT','Information Technology','DEMO-IT-REG',801,'systems delivery and team coordination','PRES-IT-PEER-ANNUAL'),
('DEMO-MKT-REG',9,'MKT','Marketing','CEO-001',900,'marketing strategy and governance','PRES-MKT-MGR-ANNUAL'),
('DEMO-MKT-REG2',9,'MKT','Marketing','DEMO-MKT-REG',902,'campaign supervision and analysis','PRES-MKT-PEER-ANNUAL'),
('DEMO-OOP-REG',10,'OOP','Office of the President','CEO-001',1101,'executive office coordination','PRES-OOP-REG-ANNUAL'),
('DEMO-OPS-REG',11,'OPS','Operations','CEO-001',1001,'regional operations management','PRES-OPS-MGR-ANNUAL'),
('DEMO-OPS-REG2',11,'OPS','Operations','DEMO-OPS-REG',1004,'branch operations coordination','PRES-OPS-PEER-ANNUAL'),
('DEMO-PUR-REG',12,'PUR','Purchasing','DEMO-GS-REG',1200,'procurement supervision and governance','PRES-PUR-LEAD-ANNUAL'),
('DEMO-PUR-REG2',12,'PUR','Purchasing','DEMO-PUR-REG',1202,'purchasing documentation and vendor support','PRES-PUR-PEER-ANNUAL');

INSERT INTO evaluation_templates
 (template_name,description,target_department,target_department_id,target_job_title_id,
  behavior_framework_code,behavior_framework_version,evaluation_type,individual_workflow,
  assessment_from,assessment_to,kra_weight,behavior_weight,form_code,revision_date,effective_date_form,status,created_by)
SELECT CONCAT('[PRESENTATION] Annual - ',e.job_title),CONCAT('Annual presentation form for ',t.domain_label,'.'),
 t.dept_name,t.dept_id,t.job_title_id,'CORE-VALUES','1.0','Annual',1,'2023-01-01','2025-12-31',80,20,
 t.form_code,'2026-11-23','2026-11-23','Active',
 (SELECT u.user_id FROM users u JOIN employees c ON c.employee_id=u.employee_id
  WHERE c.employee_code=t.creator_code AND u.is_active=1 AND u.deleted_at IS NULL
  ORDER BY (u.role='Employee') DESC,u.user_id LIMIT 1)
FROM tmp_annual_template_targets t JOIN employees e ON e.employee_code=t.target_code
WHERE NOT EXISTS(SELECT 1 FROM evaluation_templates x WHERE x.form_code=t.form_code AND x.deleted_at IS NULL);

INSERT INTO evaluation_template_positions(template_id,job_title_id)
SELECT et.template_id,t.job_title_id FROM evaluation_templates et JOIN tmp_annual_template_targets t ON t.form_code=et.form_code
WHERE NOT EXISTS(SELECT 1 FROM evaluation_template_positions p WHERE p.template_id=et.template_id AND p.job_title_id=t.job_title_id);

DROP TEMPORARY TABLE IF EXISTS tmp_annual_kra_items;
CREATE TEMPORARY TABLE tmp_annual_kra_items(sort_order INT PRIMARY KEY,item_name VARCHAR(100),item_weight DECIMAL(5,2));
INSERT INTO tmp_annual_kra_items VALUES
(1,'Annual target delivery',7),(2,'Work quality and accuracy',7),(3,'Timeliness and productivity',7),(4,'Policy and procedure compliance',7),(5,'Risk identification and control',7),
(6,'Records and evidence quality',7),(7,'Stakeholder service',7),(8,'Resource and budget stewardship',7),(9,'Problem resolution',7),(10,'Planning and prioritization',7),
(11,'Team coordination',6),(12,'Communication and reporting',6),(13,'Learning and improvement',6),(14,'Data quality and confidentiality',6),(15,'Year-end commitments',6);

INSERT INTO evaluation_criteria(template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
SELECT et.template_id,'KRA',CONCAT(k.item_name,' - ',t.dept_code),
 CONCAT('Demonstrate ',LOWER(k.item_name),' in ',t.domain_label,'.'),
 CONCAT('Provide measurable, timely, and verifiable results for ',LOWER(k.item_name),' throughout the annual assessment period.'),
 k.item_weight,'Scale_1_4',k.sort_order
FROM evaluation_templates et JOIN tmp_annual_template_targets t ON t.form_code=et.form_code CROSS JOIN tmp_annual_kra_items k
WHERE NOT EXISTS(SELECT 1 FROM evaluation_criteria c WHERE c.template_id=et.template_id AND c.section='KRA');

DROP TEMPORARY TABLE IF EXISTS tmp_annual_core_behaviors;
CREATE TEMPORARY TABLE tmp_annual_core_behaviors(sort_order INT PRIMARY KEY,behavior_name VARCHAR(150),kpi TEXT);
INSERT INTO tmp_annual_core_behaviors VALUES
(1,'Positive Attitude','Displays positive attitude at work.'),(2,'Respect','Shows respect to all people in the organization.'),
(3,'Accountability','Takes full responsibility of the job including special task or assignment.'),(4,'Commitment','Demonstrates strong commitment to the job.'),
(5,'Teamwork','Works cooperatively with others in achieving the goals.'),(6,'Integrity','Exhibits honesty and strong moral uprightness.'),
(7,'Continuous Improvement','Provides diligent effort to continuously focus on getting better.'),(8,'Excellent Client Experience','Delivers the service beyond the expectations of internal and external clients.');
INSERT INTO evaluation_criteria(template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order)
SELECT et.template_id,'Behavior',b.behavior_name,b.kpi,b.kpi,0,'Scale_1_4',b.sort_order
FROM evaluation_templates et JOIN tmp_annual_template_targets t ON t.form_code=et.form_code CROSS JOIN tmp_annual_core_behaviors b
WHERE NOT EXISTS(SELECT 1 FROM evaluation_criteria c WHERE c.template_id=et.template_id AND c.section='Behavior');

DROP TEMPORARY TABLE tmp_annual_core_behaviors;
DROP TEMPORARY TABLE tmp_annual_kra_items;
DROP TEMPORARY TABLE tmp_annual_template_targets;
COMMIT;

SELECT COUNT(*) AS annual_presentation_templates FROM evaluation_templates WHERE form_code LIKE 'PRES-%-ANNUAL';
