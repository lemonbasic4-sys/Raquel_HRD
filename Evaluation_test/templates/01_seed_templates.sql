-- Mock templates for one freshly imported Evaluation_test department fixture.
-- Run after that department's 01_*_test_people.sql, once per fresh test database.
-- Only positions with an eligible employee and their active immediate-head account receive forms.
USE raquel_hris;

CREATE TEMPORARY TABLE fixture_template_positions (
  department_id INT NOT NULL, job_title_id INT NOT NULL, probationary_position TINYINT(1) NOT NULL,
  PRIMARY KEY (job_title_id)
);
INSERT INTO fixture_template_positions (department_id,job_title_id,probationary_position) VALUES
  (1,100,0),
  (1,101,0),
  (1,105,0),
  (1,108,1),
  (2,200,0),
  (2,201,0),
  (2,203,0),
  (2,206,1),
  (3,300,0),
  (3,302,1),
  (3,303,0),
  (4,400,0),
  (4,401,0),
  (4,403,1),
  (4,404,0),
  (5,500,0),
  (5,501,0),
  (5,502,0),
  (5,506,0),
  (5,511,1),
  (5,516,0),
  (6,600,0),
  (6,601,0),
  (6,605,0),
  (6,609,0),
  (6,621,1),
  (7,700,0),
  (7,705,0),
  (7,710,1),
  (7,711,0),
  (8,800,0),
  (8,801,0),
  (8,804,0),
  (8,809,1),
  (8,810,0),
  (9,900,0),
  (9,901,0),
  (9,902,0),
  (9,903,0),
  (9,904,1),
  (10,1101,1),
  (10,1102,0),
  (10,1103,0),
  (11,1000,0),
  (11,1001,0),
  (11,1004,0),
  (11,1007,0),
  (11,1012,1),
  (12,1200,0),
  (12,1202,1);

CREATE TEMPORARY TABLE fixture_template_kras (
  department_id INT NOT NULL, sort_order INT NOT NULL, name VARCHAR(100) NOT NULL,
  description VARCHAR(255) NOT NULL, weight DECIMAL(5,2) NOT NULL,
  PRIMARY KEY (department_id,sort_order)
);
INSERT INTO fixture_template_kras (department_id,sort_order,name,description,weight) VALUES
  (1,1,'Property inventory accuracy','Complete assigned property inventory accuracy work accurately and on time.',8.34),
  (1,2,'Acquisition case completeness','Complete assigned acquisition case completeness work accurately and on time.',8.34),
  (1,3,'Title document tracking','Complete assigned title document tracking work accurately and on time.',8.34),
  (1,4,'Property valuation review','Complete assigned property valuation review work accurately and on time.',8.34),
  (1,5,'Disposition milestone delivery','Complete assigned disposition milestone delivery work accurately and on time.',8.33),
  (1,6,'Portfolio risk escalation','Complete assigned portfolio risk escalation work accurately and on time.',8.33),
  (1,7,'Approved pricing support','Complete assigned approved pricing support work accurately and on time.',8.33),
  (1,8,'Property inspection follow-up','Complete assigned property inspection follow-up work accurately and on time.',8.33),
  (1,9,'Stakeholder coordination','Complete assigned stakeholder coordination work accurately and on time.',8.33),
  (1,10,'Portfolio report timeliness','Complete assigned portfolio report timeliness work accurately and on time.',8.33),
  (1,11,'Acquired asset safeguarding','Complete assigned acquired asset safeguarding work accurately and on time.',8.33),
  (1,12,'Case handover quality','Complete assigned case handover quality work accurately and on time.',8.33),
  (2,1,'Audit plan coverage','Complete assigned audit plan coverage work accurately and on time.',8.34),
  (2,2,'Working paper completeness','Complete assigned working paper completeness work accurately and on time.',8.34),
  (2,3,'Evidence quality','Complete assigned evidence quality work accurately and on time.',8.34),
  (2,4,'Finding accuracy','Complete assigned finding accuracy work accurately and on time.',8.34),
  (2,5,'Control testing','Complete assigned control testing work accurately and on time.',8.33),
  (2,6,'Risk prioritization','Complete assigned risk prioritization work accurately and on time.',8.33),
  (2,7,'Report delivery','Complete assigned report delivery work accurately and on time.',8.33),
  (2,8,'Corrective action follow-up','Complete assigned corrective action follow-up work accurately and on time.',8.33),
  (2,9,'Independence safeguards','Complete assigned independence safeguards work accurately and on time.',8.33),
  (2,10,'Branch audit coordination','Complete assigned branch audit coordination work accurately and on time.',8.33),
  (2,11,'Issue escalation','Complete assigned issue escalation work accurately and on time.',8.33),
  (2,12,'Audit file closure','Complete assigned audit file closure work accurately and on time.',8.33),
  (3,1,'Prospect research','Complete assigned prospect research work accurately and on time.',8.34),
  (3,2,'Qualified lead generation','Complete assigned qualified lead generation work accurately and on time.',8.34),
  (3,3,'Partnership outreach','Complete assigned partnership outreach work accurately and on time.',8.34),
  (3,4,'Opportunity assessment','Complete assigned opportunity assessment work accurately and on time.',8.34),
  (3,5,'Proposal preparation','Complete assigned proposal preparation work accurately and on time.',8.33),
  (3,6,'Market intelligence','Complete assigned market intelligence work accurately and on time.',8.33),
  (3,7,'Pipeline record accuracy','Complete assigned pipeline record accuracy work accurately and on time.',8.33),
  (3,8,'Stakeholder follow-up','Complete assigned stakeholder follow-up work accurately and on time.',8.33),
  (3,9,'Growth initiative delivery','Complete assigned growth initiative delivery work accurately and on time.',8.33),
  (3,10,'Commercial risk escalation','Complete assigned commercial risk escalation work accurately and on time.',8.33),
  (3,11,'Conversion reporting','Complete assigned conversion reporting work accurately and on time.',8.33),
  (3,12,'Handover to operations','Complete assigned handover to operations work accurately and on time.',8.33),
  (4,1,'Regulatory requirement tracking','Complete assigned regulatory requirement tracking work accurately and on time.',8.34),
  (4,2,'Policy review','Complete assigned policy review work accurately and on time.',8.34),
  (4,3,'Compliance testing','Complete assigned compliance testing work accurately and on time.',8.34),
  (4,4,'Incident documentation','Complete assigned incident documentation work accurately and on time.',8.34),
  (4,5,'Exception escalation','Complete assigned exception escalation work accurately and on time.',8.33),
  (4,6,'Remediation follow-up','Complete assigned remediation follow-up work accurately and on time.',8.33),
  (4,7,'Training support','Complete assigned training support work accurately and on time.',8.33),
  (4,8,'Control evidence quality','Complete assigned control evidence quality work accurately and on time.',8.33),
  (4,9,'Deadline monitoring','Complete assigned deadline monitoring work accurately and on time.',8.33),
  (4,10,'Branch advisory response','Complete assigned branch advisory response work accurately and on time.',8.33),
  (4,11,'Regulatory report preparation','Complete assigned regulatory report preparation work accurately and on time.',8.33),
  (4,12,'Compliance register accuracy','Complete assigned compliance register accuracy work accurately and on time.',8.33),
  (5,1,'Transaction accuracy','Complete assigned transaction accuracy work accurately and on time.',8.34),
  (5,2,'Reconciliation completion','Complete assigned reconciliation completion work accurately and on time.',8.34),
  (5,3,'Financial record integrity','Complete assigned financial record integrity work accurately and on time.',8.34),
  (5,4,'Budget monitoring','Complete assigned budget monitoring work accurately and on time.',8.34),
  (5,5,'Variance explanation','Complete assigned variance explanation work accurately and on time.',8.33),
  (5,6,'Cash control','Complete assigned cash control work accurately and on time.',8.33),
  (5,7,'Report timeliness','Complete assigned report timeliness work accurately and on time.',8.33),
  (5,8,'Approval documentation','Complete assigned approval documentation work accurately and on time.',8.33),
  (5,9,'Tax and policy compliance','Complete assigned tax and policy compliance work accurately and on time.',8.33),
  (5,10,'Exception resolution','Complete assigned exception resolution work accurately and on time.',8.33),
  (5,11,'Forecast support','Complete assigned forecast support work accurately and on time.',8.33),
  (5,12,'Period close readiness','Complete assigned period close readiness work accurately and on time.',8.33),
  (6,1,'Facility request response','Complete assigned facility request response work accurately and on time.',8.34),
  (6,2,'Asset inventory accuracy','Complete assigned asset inventory accuracy work accurately and on time.',8.34),
  (6,3,'Maintenance follow-up','Complete assigned maintenance follow-up work accurately and on time.',8.34),
  (6,4,'Supplier coordination','Complete assigned supplier coordination work accurately and on time.',8.34),
  (6,5,'Logistics scheduling','Complete assigned logistics scheduling work accurately and on time.',8.33),
  (6,6,'Safety checks','Complete assigned safety checks work accurately and on time.',8.33),
  (6,7,'Service ticket closure','Complete assigned service ticket closure work accurately and on time.',8.33),
  (6,8,'Resource use','Complete assigned resource use work accurately and on time.',8.33),
  (6,9,'Document control','Complete assigned document control work accurately and on time.',8.33),
  (6,10,'Issue escalation','Complete assigned issue escalation work accurately and on time.',8.33),
  (6,11,'Delivery confirmation','Complete assigned delivery confirmation work accurately and on time.',8.33),
  (6,12,'Operations reporting','Complete assigned operations reporting work accurately and on time.',8.33),
  (7,1,'Employee record accuracy','Complete assigned employee record accuracy work accurately and on time.',8.34),
  (7,2,'Recruitment coordination','Complete assigned recruitment coordination work accurately and on time.',8.34),
  (7,3,'Onboarding completion','Complete assigned onboarding completion work accurately and on time.',8.34),
  (7,4,'Personnel document control','Complete assigned personnel document control work accurately and on time.',8.34),
  (7,5,'Employee inquiry response','Complete assigned employee inquiry response work accurately and on time.',8.33),
  (7,6,'Policy communication','Complete assigned policy communication work accurately and on time.',8.33),
  (7,7,'Training coordination','Complete assigned training coordination work accurately and on time.',8.33),
  (7,8,'Performance process support','Complete assigned performance process support work accurately and on time.',8.33),
  (7,9,'Confidentiality compliance','Complete assigned confidentiality compliance work accurately and on time.',8.33),
  (7,10,'Deadline tracking','Complete assigned deadline tracking work accurately and on time.',8.33),
  (7,11,'Case escalation','Complete assigned case escalation work accurately and on time.',8.33),
  (7,12,'HR report timeliness','Complete assigned hr report timeliness work accurately and on time.',8.33),
  (8,1,'Service availability','Complete assigned service availability work accurately and on time.',8.34),
  (8,2,'Incident response','Complete assigned incident response work accurately and on time.',8.34),
  (8,3,'Ticket documentation','Complete assigned ticket documentation work accurately and on time.',8.34),
  (8,4,'Change control','Complete assigned change control work accurately and on time.',8.34),
  (8,5,'Security patch follow-up','Complete assigned security patch follow-up work accurately and on time.',8.33),
  (8,6,'Backup verification','Complete assigned backup verification work accurately and on time.',8.33),
  (8,7,'User support quality','Complete assigned user support quality work accurately and on time.',8.33),
  (8,8,'System monitoring','Complete assigned system monitoring work accurately and on time.',8.33),
  (8,9,'Project milestone delivery','Complete assigned project milestone delivery work accurately and on time.',8.33),
  (8,10,'Technical documentation','Complete assigned technical documentation work accurately and on time.',8.33),
  (8,11,'Defect resolution','Complete assigned defect resolution work accurately and on time.',8.33),
  (8,12,'Access control review','Complete assigned access control review work accurately and on time.',8.33),
  (9,1,'Campaign planning','Complete assigned campaign planning work accurately and on time.',8.34),
  (9,2,'Brand guideline compliance','Complete assigned brand guideline compliance work accurately and on time.',8.34),
  (9,3,'Content quality','Complete assigned content quality work accurately and on time.',8.34),
  (9,4,'Publication schedule delivery','Complete assigned publication schedule delivery work accurately and on time.',8.34),
  (9,5,'Audience research','Complete assigned audience research work accurately and on time.',8.33),
  (9,6,'Channel performance tracking','Complete assigned channel performance tracking work accurately and on time.',8.33),
  (9,7,'Creative asset review','Complete assigned creative asset review work accurately and on time.',8.33),
  (9,8,'Lead handover','Complete assigned lead handover work accurately and on time.',8.33),
  (9,9,'Event coordination','Complete assigned event coordination work accurately and on time.',8.33),
  (9,10,'Campaign budget tracking','Complete assigned campaign budget tracking work accurately and on time.',8.33),
  (9,11,'Stakeholder approval','Complete assigned stakeholder approval work accurately and on time.',8.33),
  (9,12,'Results reporting','Complete assigned results reporting work accurately and on time.',8.33),
  (10,1,'Executive calendar accuracy','Complete assigned executive calendar accuracy work accurately and on time.',8.34),
  (10,2,'Meeting preparation','Complete assigned meeting preparation work accurately and on time.',8.34),
  (10,3,'Correspondence handling','Complete assigned correspondence handling work accurately and on time.',8.34),
  (10,4,'Decision record control','Complete assigned decision record control work accurately and on time.',8.34),
  (10,5,'Confidential file security','Complete assigned confidential file security work accurately and on time.',8.33),
  (10,6,'Action item follow-up','Complete assigned action item follow-up work accurately and on time.',8.33),
  (10,7,'Visitor coordination','Complete assigned visitor coordination work accurately and on time.',8.33),
  (10,8,'Travel arrangement accuracy','Complete assigned travel arrangement accuracy work accurately and on time.',8.33),
  (10,9,'Executive brief preparation','Complete assigned executive brief preparation work accurately and on time.',8.33),
  (10,10,'Document turnaround','Complete assigned document turnaround work accurately and on time.',8.33),
  (10,11,'Stakeholder communication','Complete assigned stakeholder communication work accurately and on time.',8.33),
  (10,12,'Office request resolution','Complete assigned office request resolution work accurately and on time.',8.33),
  (11,1,'Branch service delivery','Complete assigned branch service delivery work accurately and on time.',8.34),
  (11,2,'Transaction accuracy','Complete assigned transaction accuracy work accurately and on time.',8.34),
  (11,3,'Daily cash control','Complete assigned daily cash control work accurately and on time.',8.34),
  (11,4,'Customer issue response','Complete assigned customer issue response work accurately and on time.',8.34),
  (11,5,'Operational policy compliance','Complete assigned operational policy compliance work accurately and on time.',8.33),
  (11,6,'Staff coordination','Complete assigned staff coordination work accurately and on time.',8.33),
  (11,7,'Area visit follow-up','Complete assigned area visit follow-up work accurately and on time.',8.33),
  (11,8,'Inventory safeguarding','Complete assigned inventory safeguarding work accurately and on time.',8.33),
  (11,9,'Exception escalation','Complete assigned exception escalation work accurately and on time.',8.33),
  (11,10,'Target monitoring','Complete assigned target monitoring work accurately and on time.',8.33),
  (11,11,'Branch report timeliness','Complete assigned branch report timeliness work accurately and on time.',8.33),
  (11,12,'Corrective action completion','Complete assigned corrective action completion work accurately and on time.',8.33),
  (12,1,'Purchase request completeness','Complete assigned purchase request completeness work accurately and on time.',8.34),
  (12,2,'Supplier sourcing','Complete assigned supplier sourcing work accurately and on time.',8.34),
  (12,3,'Quotation comparison','Complete assigned quotation comparison work accurately and on time.',8.34),
  (12,4,'Approval documentation','Complete assigned approval documentation work accurately and on time.',8.34),
  (12,5,'Purchase order accuracy','Complete assigned purchase order accuracy work accurately and on time.',8.33),
  (12,6,'Delivery follow-up','Complete assigned delivery follow-up work accurately and on time.',8.33),
  (12,7,'Supplier performance review','Complete assigned supplier performance review work accurately and on time.',8.33),
  (12,8,'Cost control','Complete assigned cost control work accurately and on time.',8.33),
  (12,9,'Contract record accuracy','Complete assigned contract record accuracy work accurately and on time.',8.33),
  (12,10,'Procurement policy compliance','Complete assigned procurement policy compliance work accurately and on time.',8.33),
  (12,11,'Issue escalation','Complete assigned issue escalation work accurately and on time.',8.33),
  (12,12,'Purchasing report timeliness','Complete assigned purchasing report timeliness work accurately and on time.',8.33);

CREATE TEMPORARY TABLE fixture_template_behaviors (
  sort_order INT NOT NULL PRIMARY KEY, name VARCHAR(100) NOT NULL, kpi VARCHAR(255) NOT NULL
);
INSERT INTO fixture_template_behaviors (sort_order,name,kpi) VALUES
  (1,'Positive Attitude','Displays positive attitude at work.'),
  (2,'Respect','Shows respect to all people in the organization.'),
  (3,'Accountability','Takes full responsibility of the job including special task or assignment.'),
  (4,'Commitment','Demonstrates strong commitment to the job.'),
  (5,'Teamwork','Works cooperatively with others in achieving the goals.'),
  (6,'Integrity','Exhibits honesty and strong moral uprightness.'),
  (7,'Continuous Improvement','Provides diligent effort to continuously focus on getting better.'),
  (8,'Excellent Client Experience','Delivers the service beyond the expectations of the internal and external clients.');

CREATE TEMPORARY TABLE fixture_template_types (type_no INT PRIMARY KEY, type_name VARCHAR(12));
INSERT INTO fixture_template_types VALUES (1,'Initial'),(2,'Final'),(3,'Quarterly'),(4,'Annual');

-- Build forms only for the department represented by the currently imported employees.
CREATE TEMPORARY TABLE fixture_ready_targets AS
SELECT p.department_id,p.job_title_id,p.probationary_position,MIN(u.user_id) AS creator_user_id
FROM fixture_template_positions p
JOIN job_titles j ON j.job_title_id=p.job_title_id AND j.department_id=p.department_id
JOIN employees e ON e.job_title_id=p.job_title_id AND e.department_id=p.department_id
  AND e.is_active=1 AND e.deleted_at IS NULL AND e.employment_status IN ('Regular','Probationary')
JOIN employees h ON h.employee_id=e.reports_to AND h.job_title_id=j.reports_to
  AND h.is_active=1 AND h.deleted_at IS NULL AND h.employment_status='Regular'
JOIN users u ON u.employee_id=h.employee_id AND u.role IN ('Employee','President and CEO')
  AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0
GROUP BY p.department_id,p.job_title_id,p.probationary_position;

INSERT INTO evaluation_templates
  (template_id,template_name,description,target_department,target_department_id,
   target_job_title_id,behavior_framework_code,behavior_framework_version,
   evaluation_type,kra_weight,behavior_weight,form_code,status,created_by)
SELECT 200000+p.job_title_id*10+t.type_no,
  CONCAT('[TEST] ',j.job_title,' ',t.type_name),
  CONCAT('Mock ',t.type_name,' form for ',j.job_title,'. Replace KRAs with approved job requirements before production use.'),
  d.department_name,p.department_id,p.job_title_id,
  CASE WHEN t.type_no IN (3,4) THEN CONCAT(UPPER(t.type_name),'-CORE') ELSE NULL END,
  CASE WHEN t.type_no IN (3,4) THEN '1' ELSE NULL END,
  t.type_name,80.00,20.00,CONCAT('TEST-',p.department_id,'-',p.job_title_id,'-',t.type_no),
  'Active',p.creator_user_id
FROM fixture_ready_targets p
JOIN job_titles j ON j.job_title_id=p.job_title_id
JOIN departments d ON d.department_id=p.department_id
CROSS JOIN fixture_template_types t
WHERE t.type_no IN (3,4) OR (p.probationary_position=1 AND t.type_no IN (1,2));

INSERT INTO evaluation_template_positions (template_id,job_title_id)
SELECT t.template_id,t.target_job_title_id FROM evaluation_templates t
JOIN fixture_ready_targets p ON p.job_title_id=t.target_job_title_id
WHERE t.template_id BETWEEN 200000 AND 219999;

INSERT INTO evaluation_criteria
  (template_id,section,criterion_name,description,weight,scoring_method,sort_order,is_custom)
SELECT t.template_id,'KRA',CONCAT(j.job_title,' - ',k.name),k.description,
  k.weight,'Scale_1_4',k.sort_order,0
FROM evaluation_templates t
JOIN fixture_ready_targets p ON p.job_title_id=t.target_job_title_id
JOIN job_titles j ON j.job_title_id=p.job_title_id
JOIN fixture_template_kras k ON k.department_id=p.department_id
WHERE t.template_id BETWEEN 200000 AND 219999;

INSERT INTO evaluation_criteria
  (template_id,section,criterion_name,kpi_description,weight,scoring_method,sort_order,is_custom)
SELECT t.template_id,'Behavior',b.name,b.kpi,0,'Scale_1_4',12+b.sort_order,0
FROM evaluation_templates t
JOIN fixture_ready_targets p ON p.job_title_id=t.target_job_title_id
CROSS JOIN fixture_template_behaviors b
WHERE t.template_id BETWEEN 200000 AND 219999;

DROP TEMPORARY TABLE fixture_ready_targets;
DROP TEMPORARY TABLE fixture_template_types;
DROP TEMPORARY TABLE fixture_template_behaviors;
DROP TEMPORARY TABLE fixture_template_kras;
DROP TEMPORARY TABLE fixture_template_positions;
