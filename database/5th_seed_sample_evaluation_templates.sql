-- Sample Quarterly and Annual templates for the fresh testing setup.
-- Import AFTER base seeds 1, 2, 3 and 4th_seed_executive_portal_accounts.sql.
-- 10 templates: 5 target role groups x Quarterly/Annual.
-- Each has 10 KRA (10% each), 8 standard behaviors, and overall weights 80/20.
-- Creator accounts must exist. Import once per fresh setup; no employees are replaced.
-- Templates only: no scores, evaluations, cycles, or approvals are fabricated.
-- Revision/effective dates are form metadata, not evaluation Period From/To.
SET NAMES utf8mb4;
USE raquel_hris;
SET FOREIGN_KEY_CHECKS = 1;
START TRANSACTION;

-- CEO-001 creates Quarterly for HR Manager.
INSERT INTO evaluation_templates
(template_name,description,target_department,target_department_id,target_job_title_id,
 behavior_framework_code,behavior_framework_version,evaluation_type,kra_weight,behavior_weight,
 form_code,revision_date,effective_date_form,status,created_by)
VALUES ('[SAMPLE] Quarterly - HR Manager',
 'Fictional testing form. Review performance against documented role targets.',
 (SELECT department_name FROM departments WHERE department_id=7),7,700,
 'CORE-VALUES','1.0','Quarterly',80,20,'SAMPLE-HR-700-QUARTERLY','2026-11-11','2026-11-11','Active',
 (SELECT user_id FROM users WHERE username='CEO-001'));
SET @sample_template_id = LAST_INSERT_ID();

INSERT INTO evaluation_template_positions (template_id,job_title_id)
SELECT @sample_template_id,job_title_id FROM job_titles
WHERE department_id=7 AND job_title_id BETWEEN 700 AND 704 AND is_active=1;

INSERT INTO evaluation_criteria
(template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order) VALUES

(@sample_template_id,'KRA','Workforce planning','Submit an approved staffing forecast and explain headcount variances each month.','Submit an approved staffing forecast and explain headcount variances each month.',10,'Scale_1_4',1),
(@sample_template_id,'KRA','Recruitment delivery','Fill at least 90% of approved vacancies within the agreed hiring lead time.','Fill at least 90% of approved vacancies within the agreed hiring lead time.',10,'Scale_1_4',2),
(@sample_template_id,'KRA','Employee relations','Resolve at least 90% of documented employee concerns within the agreed service period.','Resolve at least 90% of documented employee concerns within the agreed service period.',10,'Scale_1_4',3),
(@sample_template_id,'KRA','HR policy compliance','Review all scheduled HR policies and close audit findings by their due dates.','Review all scheduled HR policies and close audit findings by their due dates.',10,'Scale_1_4',4),
(@sample_template_id,'KRA','Performance management','Complete all due evaluation reviews and follow up overdue submissions weekly.','Complete all due evaluation reviews and follow up overdue submissions weekly.',10,'Scale_1_4',5),
(@sample_template_id,'KRA','Training strategy','Deliver at least 90% of approved training activities and evaluate learning outcomes.','Deliver at least 90% of approved training activities and evaluate learning outcomes.',10,'Scale_1_4',6),
(@sample_template_id,'KRA','Payroll oversight','Validate all payroll endorsements before cutoff with no unresolved material errors.','Validate all payroll endorsements before cutoff with no unresolved material errors.',10,'Scale_1_4',7),
(@sample_template_id,'KRA','HR records governance','Maintain complete employee records and resolve identified gaps within 10 working days.','Maintain complete employee records and resolve identified gaps within 10 working days.',10,'Scale_1_4',8),
(@sample_template_id,'KRA','Team development','Document monthly coaching and quarterly development goals for each direct report.','Document monthly coaching and quarterly development goals for each direct report.',10,'Scale_1_4',9),
(@sample_template_id,'KRA','Management reporting','Submit accurate monthly HR dashboards and action recommendations by deadline.','Submit accurate monthly HR dashboards and action recommendations by deadline.',10,'Scale_1_4',10),
(@sample_template_id,'Behavior','Positive Attitude','Displays positive attitude at work.','Displays positive attitude at work.',0,'Scale_1_4',1),
(@sample_template_id,'Behavior','Respect','Shows respect to all people in the organization.','Shows respect to all people in the organization.',0,'Scale_1_4',2),
(@sample_template_id,'Behavior','Accountability','Takes full responsibility of the job including special task or assignment.','Takes full responsibility of the job including special task or assignment.',0,'Scale_1_4',3),
(@sample_template_id,'Behavior','Commitment','Demonstrates strong commitment to the job.','Demonstrates strong commitment to the job.',0,'Scale_1_4',4),
(@sample_template_id,'Behavior','Teamwork','Works cooperatively with others in achieving the goals.','Works cooperatively with others in achieving the goals.',0,'Scale_1_4',5),
(@sample_template_id,'Behavior','Integrity','Exhibits honesty and strong moral uprightness.','Exhibits honesty and strong moral uprightness.',0,'Scale_1_4',6),
(@sample_template_id,'Behavior','Continuous Improvement','Provides diligent effort to continuously focus on getting better.','Provides diligent effort to continuously focus on getting better.',0,'Scale_1_4',7),
(@sample_template_id,'Behavior','Excellent Client Experience','Delivers the service beyond the expectations of the internal and external clients.','Delivers the service beyond the expectations of the internal and external clients.',0,'Scale_1_4',8);

-- CEO-001 creates Annual for HR Manager.
INSERT INTO evaluation_templates
(template_name,description,target_department,target_department_id,target_job_title_id,
 behavior_framework_code,behavior_framework_version,evaluation_type,kra_weight,behavior_weight,
 form_code,revision_date,effective_date_form,status,created_by)
VALUES ('[SAMPLE] Annual - HR Manager',
 'Fictional testing form. Review performance against documented role targets.',
 (SELECT department_name FROM departments WHERE department_id=7),7,700,
 'CORE-VALUES','1.0','Annual',80,20,'SAMPLE-HR-700-ANNUAL','2026-11-11','2026-11-11','Active',
 (SELECT user_id FROM users WHERE username='CEO-001'));
SET @sample_template_id = LAST_INSERT_ID();

INSERT INTO evaluation_template_positions (template_id,job_title_id)
SELECT @sample_template_id,job_title_id FROM job_titles
WHERE department_id=7 AND job_title_id BETWEEN 700 AND 704 AND is_active=1;

INSERT INTO evaluation_criteria
(template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order) VALUES

(@sample_template_id,'KRA','Workforce planning','Submit an approved staffing forecast and explain headcount variances each month.','Submit an approved staffing forecast and explain headcount variances each month.',10,'Scale_1_4',1),
(@sample_template_id,'KRA','Recruitment delivery','Fill at least 90% of approved vacancies within the agreed hiring lead time.','Fill at least 90% of approved vacancies within the agreed hiring lead time.',10,'Scale_1_4',2),
(@sample_template_id,'KRA','Employee relations','Resolve at least 90% of documented employee concerns within the agreed service period.','Resolve at least 90% of documented employee concerns within the agreed service period.',10,'Scale_1_4',3),
(@sample_template_id,'KRA','HR policy compliance','Review all scheduled HR policies and close audit findings by their due dates.','Review all scheduled HR policies and close audit findings by their due dates.',10,'Scale_1_4',4),
(@sample_template_id,'KRA','Performance management','Complete all due evaluation reviews and follow up overdue submissions weekly.','Complete all due evaluation reviews and follow up overdue submissions weekly.',10,'Scale_1_4',5),
(@sample_template_id,'KRA','Training strategy','Deliver at least 90% of approved training activities and evaluate learning outcomes.','Deliver at least 90% of approved training activities and evaluate learning outcomes.',10,'Scale_1_4',6),
(@sample_template_id,'KRA','Payroll oversight','Validate all payroll endorsements before cutoff with no unresolved material errors.','Validate all payroll endorsements before cutoff with no unresolved material errors.',10,'Scale_1_4',7),
(@sample_template_id,'KRA','HR records governance','Maintain complete employee records and resolve identified gaps within 10 working days.','Maintain complete employee records and resolve identified gaps within 10 working days.',10,'Scale_1_4',8),
(@sample_template_id,'KRA','Team development','Document monthly coaching and quarterly development goals for each direct report.','Document monthly coaching and quarterly development goals for each direct report.',10,'Scale_1_4',9),
(@sample_template_id,'KRA','Management reporting','Submit accurate monthly HR dashboards and action recommendations by deadline.','Submit accurate monthly HR dashboards and action recommendations by deadline.',10,'Scale_1_4',10),
(@sample_template_id,'Behavior','Positive Attitude','Displays positive attitude at work.','Displays positive attitude at work.',0,'Scale_1_4',1),
(@sample_template_id,'Behavior','Respect','Shows respect to all people in the organization.','Shows respect to all people in the organization.',0,'Scale_1_4',2),
(@sample_template_id,'Behavior','Accountability','Takes full responsibility of the job including special task or assignment.','Takes full responsibility of the job including special task or assignment.',0,'Scale_1_4',3),
(@sample_template_id,'Behavior','Commitment','Demonstrates strong commitment to the job.','Demonstrates strong commitment to the job.',0,'Scale_1_4',4),
(@sample_template_id,'Behavior','Teamwork','Works cooperatively with others in achieving the goals.','Works cooperatively with others in achieving the goals.',0,'Scale_1_4',5),
(@sample_template_id,'Behavior','Integrity','Exhibits honesty and strong moral uprightness.','Exhibits honesty and strong moral uprightness.',0,'Scale_1_4',6),
(@sample_template_id,'Behavior','Continuous Improvement','Provides diligent effort to continuously focus on getting better.','Provides diligent effort to continuously focus on getting better.',0,'Scale_1_4',7),
(@sample_template_id,'Behavior','Excellent Client Experience','Delivers the service beyond the expectations of the internal and external clients.','Delivers the service beyond the expectations of the internal and external clients.',0,'Scale_1_4',8);

-- elena.delgado creates Quarterly for HR Supervisor.
INSERT INTO evaluation_templates
(template_name,description,target_department,target_department_id,target_job_title_id,
 behavior_framework_code,behavior_framework_version,evaluation_type,kra_weight,behavior_weight,
 form_code,revision_date,effective_date_form,status,created_by)
VALUES ('[SAMPLE] Quarterly - HR Supervisor',
 'Fictional testing form. Review performance against documented role targets.',
 (SELECT department_name FROM departments WHERE department_id=7),7,705,
 'CORE-VALUES','1.0','Quarterly',80,20,'SAMPLE-HR-705-QUARTERLY','2026-11-11','2026-11-11','Active',
 (SELECT user_id FROM users WHERE username='elena.delgado'));
SET @sample_template_id = LAST_INSERT_ID();

INSERT INTO evaluation_template_positions (template_id,job_title_id)
SELECT @sample_template_id,job_title_id FROM job_titles
WHERE department_id=7 AND job_title_id BETWEEN 705 AND 709 AND is_active=1;

INSERT INTO evaluation_criteria
(template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order) VALUES

(@sample_template_id,'KRA','Records quality control','Check all assigned employee file updates within three working days.','Check all assigned employee file updates within three working days.',10,'Scale_1_4',1),
(@sample_template_id,'KRA','Recruitment coordination','Deliver complete candidate screening records for every endorsed applicant.','Deliver complete candidate screening records for every endorsed applicant.',10,'Scale_1_4',2),
(@sample_template_id,'KRA','Onboarding supervision','Complete required onboarding checklists within five working days of each start date.','Complete required onboarding checklists within five working days of each start date.',10,'Scale_1_4',3),
(@sample_template_id,'KRA','Attendance validation','Resolve all reported attendance discrepancies before payroll cutoff.','Resolve all reported attendance discrepancies before payroll cutoff.',10,'Scale_1_4',4),
(@sample_template_id,'KRA','Benefits coordination','Submit complete benefits documentation within published processing deadlines.','Submit complete benefits documentation within published processing deadlines.',10,'Scale_1_4',5),
(@sample_template_id,'KRA','Evaluation monitoring','Track all due evaluations weekly and escalate overdue reviews within two working days.','Track all due evaluations weekly and escalate overdue reviews within two working days.',10,'Scale_1_4',6),
(@sample_template_id,'KRA','Employee support','Acknowledge assigned employee requests within one working day.','Acknowledge assigned employee requests within one working day.',10,'Scale_1_4',7),
(@sample_template_id,'KRA','Compliance checks','Complete scheduled HR compliance checks and document corrective actions.','Complete scheduled HR compliance checks and document corrective actions.',10,'Scale_1_4',8),
(@sample_template_id,'KRA','Staff coaching','Conduct and document one coaching session per month for each assigned staff member.','Conduct and document one coaching session per month for each assigned staff member.',10,'Scale_1_4',9),
(@sample_template_id,'KRA','HR operational reporting','Submit reconciled HR operations reports by the agreed monthly deadline.','Submit reconciled HR operations reports by the agreed monthly deadline.',10,'Scale_1_4',10),
(@sample_template_id,'Behavior','Positive Attitude','Displays positive attitude at work.','Displays positive attitude at work.',0,'Scale_1_4',1),
(@sample_template_id,'Behavior','Respect','Shows respect to all people in the organization.','Shows respect to all people in the organization.',0,'Scale_1_4',2),
(@sample_template_id,'Behavior','Accountability','Takes full responsibility of the job including special task or assignment.','Takes full responsibility of the job including special task or assignment.',0,'Scale_1_4',3),
(@sample_template_id,'Behavior','Commitment','Demonstrates strong commitment to the job.','Demonstrates strong commitment to the job.',0,'Scale_1_4',4),
(@sample_template_id,'Behavior','Teamwork','Works cooperatively with others in achieving the goals.','Works cooperatively with others in achieving the goals.',0,'Scale_1_4',5),
(@sample_template_id,'Behavior','Integrity','Exhibits honesty and strong moral uprightness.','Exhibits honesty and strong moral uprightness.',0,'Scale_1_4',6),
(@sample_template_id,'Behavior','Continuous Improvement','Provides diligent effort to continuously focus on getting better.','Provides diligent effort to continuously focus on getting better.',0,'Scale_1_4',7),
(@sample_template_id,'Behavior','Excellent Client Experience','Delivers the service beyond the expectations of the internal and external clients.','Delivers the service beyond the expectations of the internal and external clients.',0,'Scale_1_4',8);

-- elena.delgado creates Annual for HR Supervisor.
INSERT INTO evaluation_templates
(template_name,description,target_department,target_department_id,target_job_title_id,
 behavior_framework_code,behavior_framework_version,evaluation_type,kra_weight,behavior_weight,
 form_code,revision_date,effective_date_form,status,created_by)
VALUES ('[SAMPLE] Annual - HR Supervisor',
 'Fictional testing form. Review performance against documented role targets.',
 (SELECT department_name FROM departments WHERE department_id=7),7,705,
 'CORE-VALUES','1.0','Annual',80,20,'SAMPLE-HR-705-ANNUAL','2026-11-11','2026-11-11','Active',
 (SELECT user_id FROM users WHERE username='elena.delgado'));
SET @sample_template_id = LAST_INSERT_ID();

INSERT INTO evaluation_template_positions (template_id,job_title_id)
SELECT @sample_template_id,job_title_id FROM job_titles
WHERE department_id=7 AND job_title_id BETWEEN 705 AND 709 AND is_active=1;

INSERT INTO evaluation_criteria
(template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order) VALUES

(@sample_template_id,'KRA','Records quality control','Check all assigned employee file updates within three working days.','Check all assigned employee file updates within three working days.',10,'Scale_1_4',1),
(@sample_template_id,'KRA','Recruitment coordination','Deliver complete candidate screening records for every endorsed applicant.','Deliver complete candidate screening records for every endorsed applicant.',10,'Scale_1_4',2),
(@sample_template_id,'KRA','Onboarding supervision','Complete required onboarding checklists within five working days of each start date.','Complete required onboarding checklists within five working days of each start date.',10,'Scale_1_4',3),
(@sample_template_id,'KRA','Attendance validation','Resolve all reported attendance discrepancies before payroll cutoff.','Resolve all reported attendance discrepancies before payroll cutoff.',10,'Scale_1_4',4),
(@sample_template_id,'KRA','Benefits coordination','Submit complete benefits documentation within published processing deadlines.','Submit complete benefits documentation within published processing deadlines.',10,'Scale_1_4',5),
(@sample_template_id,'KRA','Evaluation monitoring','Track all due evaluations weekly and escalate overdue reviews within two working days.','Track all due evaluations weekly and escalate overdue reviews within two working days.',10,'Scale_1_4',6),
(@sample_template_id,'KRA','Employee support','Acknowledge assigned employee requests within one working day.','Acknowledge assigned employee requests within one working day.',10,'Scale_1_4',7),
(@sample_template_id,'KRA','Compliance checks','Complete scheduled HR compliance checks and document corrective actions.','Complete scheduled HR compliance checks and document corrective actions.',10,'Scale_1_4',8),
(@sample_template_id,'KRA','Staff coaching','Conduct and document one coaching session per month for each assigned staff member.','Conduct and document one coaching session per month for each assigned staff member.',10,'Scale_1_4',9),
(@sample_template_id,'KRA','HR operational reporting','Submit reconciled HR operations reports by the agreed monthly deadline.','Submit reconciled HR operations reports by the agreed monthly deadline.',10,'Scale_1_4',10),
(@sample_template_id,'Behavior','Positive Attitude','Displays positive attitude at work.','Displays positive attitude at work.',0,'Scale_1_4',1),
(@sample_template_id,'Behavior','Respect','Shows respect to all people in the organization.','Shows respect to all people in the organization.',0,'Scale_1_4',2),
(@sample_template_id,'Behavior','Accountability','Takes full responsibility of the job including special task or assignment.','Takes full responsibility of the job including special task or assignment.',0,'Scale_1_4',3),
(@sample_template_id,'Behavior','Commitment','Demonstrates strong commitment to the job.','Demonstrates strong commitment to the job.',0,'Scale_1_4',4),
(@sample_template_id,'Behavior','Teamwork','Works cooperatively with others in achieving the goals.','Works cooperatively with others in achieving the goals.',0,'Scale_1_4',5),
(@sample_template_id,'Behavior','Integrity','Exhibits honesty and strong moral uprightness.','Exhibits honesty and strong moral uprightness.',0,'Scale_1_4',6),
(@sample_template_id,'Behavior','Continuous Improvement','Provides diligent effort to continuously focus on getting better.','Provides diligent effort to continuously focus on getting better.',0,'Scale_1_4',7),
(@sample_template_id,'Behavior','Excellent Client Experience','Delivers the service beyond the expectations of the internal and external clients.','Delivers the service beyond the expectations of the internal and external clients.',0,'Scale_1_4',8);

-- patricia.gomez creates Quarterly for HR Staff.
INSERT INTO evaluation_templates
(template_name,description,target_department,target_department_id,target_job_title_id,
 behavior_framework_code,behavior_framework_version,evaluation_type,kra_weight,behavior_weight,
 form_code,revision_date,effective_date_form,status,created_by)
VALUES ('[SAMPLE] Quarterly - HR Staff',
 'Fictional testing form. Review performance against documented role targets.',
 (SELECT department_name FROM departments WHERE department_id=7),7,710,
 'CORE-VALUES','1.0','Quarterly',80,20,'SAMPLE-HR-710-QUARTERLY','2026-11-11','2026-11-11','Active',
 (SELECT user_id FROM users WHERE username='patricia.gomez'));
SET @sample_template_id = LAST_INSERT_ID();

INSERT INTO evaluation_template_positions (template_id,job_title_id)
SELECT @sample_template_id,job_title_id FROM job_titles
WHERE department_id=7 AND job_title_id BETWEEN 710 AND 714 AND is_active=1;

INSERT INTO evaluation_criteria
(template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order) VALUES

(@sample_template_id,'KRA','Personnel file maintenance','File approved personnel changes within two working days with at least 98% accuracy.','File approved personnel changes within two working days with at least 98% accuracy.',10,'Scale_1_4',1),
(@sample_template_id,'KRA','Applicant tracking','Update applicant status within one working day of each recruitment decision.','Update applicant status within one working day of each recruitment decision.',10,'Scale_1_4',2),
(@sample_template_id,'KRA','Onboarding documents','Collect and check all required onboarding documents using the approved checklist.','Collect and check all required onboarding documents using the approved checklist.',10,'Scale_1_4',3),
(@sample_template_id,'KRA','Attendance encoding','Encode attendance adjustments before cutoff with at least 98% accuracy.','Encode attendance adjustments before cutoff with at least 98% accuracy.',10,'Scale_1_4',4),
(@sample_template_id,'KRA','Benefits documentation','Prepare complete benefit application packets within three working days.','Prepare complete benefit application packets within three working days.',10,'Scale_1_4',5),
(@sample_template_id,'KRA','Contract tracking','Flag employee contracts due to expire at least 30 days before expiry.','Flag employee contracts due to expire at least 30 days before expiry.',10,'Scale_1_4',6),
(@sample_template_id,'KRA','Evaluation administration','Issue due evaluation reminders and maintain an accurate submission tracker weekly.','Issue due evaluation reminders and maintain an accurate submission tracker weekly.',10,'Scale_1_4',7),
(@sample_template_id,'KRA','Employee request handling','Log and acknowledge employee requests within one working day.','Log and acknowledge employee requests within one working day.',10,'Scale_1_4',8),
(@sample_template_id,'KRA','Training logistics','Prepare attendance sheets and training materials before every assigned session.','Prepare attendance sheets and training materials before every assigned session.',10,'Scale_1_4',9),
(@sample_template_id,'KRA','HR data confidentiality','Use approved access controls and record all authorized personnel file releases.','Use approved access controls and record all authorized personnel file releases.',10,'Scale_1_4',10),
(@sample_template_id,'Behavior','Positive Attitude','Displays positive attitude at work.','Displays positive attitude at work.',0,'Scale_1_4',1),
(@sample_template_id,'Behavior','Respect','Shows respect to all people in the organization.','Shows respect to all people in the organization.',0,'Scale_1_4',2),
(@sample_template_id,'Behavior','Accountability','Takes full responsibility of the job including special task or assignment.','Takes full responsibility of the job including special task or assignment.',0,'Scale_1_4',3),
(@sample_template_id,'Behavior','Commitment','Demonstrates strong commitment to the job.','Demonstrates strong commitment to the job.',0,'Scale_1_4',4),
(@sample_template_id,'Behavior','Teamwork','Works cooperatively with others in achieving the goals.','Works cooperatively with others in achieving the goals.',0,'Scale_1_4',5),
(@sample_template_id,'Behavior','Integrity','Exhibits honesty and strong moral uprightness.','Exhibits honesty and strong moral uprightness.',0,'Scale_1_4',6),
(@sample_template_id,'Behavior','Continuous Improvement','Provides diligent effort to continuously focus on getting better.','Provides diligent effort to continuously focus on getting better.',0,'Scale_1_4',7),
(@sample_template_id,'Behavior','Excellent Client Experience','Delivers the service beyond the expectations of the internal and external clients.','Delivers the service beyond the expectations of the internal and external clients.',0,'Scale_1_4',8);

-- patricia.gomez creates Annual for HR Staff.
INSERT INTO evaluation_templates
(template_name,description,target_department,target_department_id,target_job_title_id,
 behavior_framework_code,behavior_framework_version,evaluation_type,kra_weight,behavior_weight,
 form_code,revision_date,effective_date_form,status,created_by)
VALUES ('[SAMPLE] Annual - HR Staff',
 'Fictional testing form. Review performance against documented role targets.',
 (SELECT department_name FROM departments WHERE department_id=7),7,710,
 'CORE-VALUES','1.0','Annual',80,20,'SAMPLE-HR-710-ANNUAL','2026-11-11','2026-11-11','Active',
 (SELECT user_id FROM users WHERE username='patricia.gomez'));
SET @sample_template_id = LAST_INSERT_ID();

INSERT INTO evaluation_template_positions (template_id,job_title_id)
SELECT @sample_template_id,job_title_id FROM job_titles
WHERE department_id=7 AND job_title_id BETWEEN 710 AND 714 AND is_active=1;

INSERT INTO evaluation_criteria
(template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order) VALUES

(@sample_template_id,'KRA','Personnel file maintenance','File approved personnel changes within two working days with at least 98% accuracy.','File approved personnel changes within two working days with at least 98% accuracy.',10,'Scale_1_4',1),
(@sample_template_id,'KRA','Applicant tracking','Update applicant status within one working day of each recruitment decision.','Update applicant status within one working day of each recruitment decision.',10,'Scale_1_4',2),
(@sample_template_id,'KRA','Onboarding documents','Collect and check all required onboarding documents using the approved checklist.','Collect and check all required onboarding documents using the approved checklist.',10,'Scale_1_4',3),
(@sample_template_id,'KRA','Attendance encoding','Encode attendance adjustments before cutoff with at least 98% accuracy.','Encode attendance adjustments before cutoff with at least 98% accuracy.',10,'Scale_1_4',4),
(@sample_template_id,'KRA','Benefits documentation','Prepare complete benefit application packets within three working days.','Prepare complete benefit application packets within three working days.',10,'Scale_1_4',5),
(@sample_template_id,'KRA','Contract tracking','Flag employee contracts due to expire at least 30 days before expiry.','Flag employee contracts due to expire at least 30 days before expiry.',10,'Scale_1_4',6),
(@sample_template_id,'KRA','Evaluation administration','Issue due evaluation reminders and maintain an accurate submission tracker weekly.','Issue due evaluation reminders and maintain an accurate submission tracker weekly.',10,'Scale_1_4',7),
(@sample_template_id,'KRA','Employee request handling','Log and acknowledge employee requests within one working day.','Log and acknowledge employee requests within one working day.',10,'Scale_1_4',8),
(@sample_template_id,'KRA','Training logistics','Prepare attendance sheets and training materials before every assigned session.','Prepare attendance sheets and training materials before every assigned session.',10,'Scale_1_4',9),
(@sample_template_id,'KRA','HR data confidentiality','Use approved access controls and record all authorized personnel file releases.','Use approved access controls and record all authorized personnel file releases.',10,'Scale_1_4',10),
(@sample_template_id,'Behavior','Positive Attitude','Displays positive attitude at work.','Displays positive attitude at work.',0,'Scale_1_4',1),
(@sample_template_id,'Behavior','Respect','Shows respect to all people in the organization.','Shows respect to all people in the organization.',0,'Scale_1_4',2),
(@sample_template_id,'Behavior','Accountability','Takes full responsibility of the job including special task or assignment.','Takes full responsibility of the job including special task or assignment.',0,'Scale_1_4',3),
(@sample_template_id,'Behavior','Commitment','Demonstrates strong commitment to the job.','Demonstrates strong commitment to the job.',0,'Scale_1_4',4),
(@sample_template_id,'Behavior','Teamwork','Works cooperatively with others in achieving the goals.','Works cooperatively with others in achieving the goals.',0,'Scale_1_4',5),
(@sample_template_id,'Behavior','Integrity','Exhibits honesty and strong moral uprightness.','Exhibits honesty and strong moral uprightness.',0,'Scale_1_4',6),
(@sample_template_id,'Behavior','Continuous Improvement','Provides diligent effort to continuously focus on getting better.','Provides diligent effort to continuously focus on getting better.',0,'Scale_1_4',7),
(@sample_template_id,'Behavior','Excellent Client Experience','Delivers the service beyond the expectations of the internal and external clients.','Delivers the service beyond the expectations of the internal and external clients.',0,'Scale_1_4',8);

-- CEO-001 creates Quarterly for VP for Acquired Properties.
INSERT INTO evaluation_templates
(template_name,description,target_department,target_department_id,target_job_title_id,
 behavior_framework_code,behavior_framework_version,evaluation_type,kra_weight,behavior_weight,
 form_code,revision_date,effective_date_form,status,created_by)
VALUES ('[SAMPLE] Quarterly - VP for Acquired Properties',
 'Fictional testing form. Review performance against documented role targets.',
 (SELECT department_name FROM departments WHERE department_id=1),1,100,
 'CORE-VALUES','1.0','Quarterly',80,20,'SAMPLE-AP-100-QUARTERLY','2026-11-11','2026-11-11','Active',
 (SELECT user_id FROM users WHERE username='CEO-001'));
SET @sample_template_id = LAST_INSERT_ID();

INSERT INTO evaluation_template_positions (template_id,job_title_id)
SELECT @sample_template_id,job_title_id FROM job_titles
WHERE department_id=1 AND job_title_id BETWEEN 100 AND 100 AND is_active=1;

INSERT INTO evaluation_criteria
(template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order) VALUES

(@sample_template_id,'KRA','Portfolio strategy','Present a documented portfolio plan with quarterly targets and risk priorities.','Present a documented portfolio plan with quarterly targets and risk priorities.',10,'Scale_1_4',1),
(@sample_template_id,'KRA','Disposition performance','Achieve approved disposition targets and explain material monthly variances.','Achieve approved disposition targets and explain material monthly variances.',10,'Scale_1_4',2),
(@sample_template_id,'KRA','Valuation governance','Ensure every proposed sale has a documented valuation and approval trail.','Ensure every proposed sale has a documented valuation and approval trail.',10,'Scale_1_4',3),
(@sample_template_id,'KRA','Acquisition oversight','Review all escalated acquisition proposals against approved investment criteria.','Review all escalated acquisition proposals against approved investment criteria.',10,'Scale_1_4',4),
(@sample_template_id,'KRA','Legal risk oversight','Track high-risk title and litigation issues and assign documented resolution actions.','Track high-risk title and litigation issues and assign documented resolution actions.',10,'Scale_1_4',5),
(@sample_template_id,'KRA','Budget stewardship','Keep approved portfolio expenses within budget or obtain prior variance approval.','Keep approved portfolio expenses within budget or obtain prior variance approval.',10,'Scale_1_4',6),
(@sample_template_id,'KRA','Manager performance','Conduct documented quarterly reviews of AP managers and their delivery commitments.','Conduct documented quarterly reviews of AP managers and their delivery commitments.',10,'Scale_1_4',7),
(@sample_template_id,'KRA','Regulatory compliance','Close all due portfolio compliance actions within approved deadlines.','Close all due portfolio compliance actions within approved deadlines.',10,'Scale_1_4',8),
(@sample_template_id,'KRA','Executive reporting','Submit accurate monthly portfolio reports with decisions required and supporting evidence.','Submit accurate monthly portfolio reports with decisions required and supporting evidence.',10,'Scale_1_4',9),
(@sample_template_id,'KRA','Stakeholder governance','Resolve escalated partner and branch issues within agreed service timelines.','Resolve escalated partner and branch issues within agreed service timelines.',10,'Scale_1_4',10),
(@sample_template_id,'Behavior','Positive Attitude','Displays positive attitude at work.','Displays positive attitude at work.',0,'Scale_1_4',1),
(@sample_template_id,'Behavior','Respect','Shows respect to all people in the organization.','Shows respect to all people in the organization.',0,'Scale_1_4',2),
(@sample_template_id,'Behavior','Accountability','Takes full responsibility of the job including special task or assignment.','Takes full responsibility of the job including special task or assignment.',0,'Scale_1_4',3),
(@sample_template_id,'Behavior','Commitment','Demonstrates strong commitment to the job.','Demonstrates strong commitment to the job.',0,'Scale_1_4',4),
(@sample_template_id,'Behavior','Teamwork','Works cooperatively with others in achieving the goals.','Works cooperatively with others in achieving the goals.',0,'Scale_1_4',5),
(@sample_template_id,'Behavior','Integrity','Exhibits honesty and strong moral uprightness.','Exhibits honesty and strong moral uprightness.',0,'Scale_1_4',6),
(@sample_template_id,'Behavior','Continuous Improvement','Provides diligent effort to continuously focus on getting better.','Provides diligent effort to continuously focus on getting better.',0,'Scale_1_4',7),
(@sample_template_id,'Behavior','Excellent Client Experience','Delivers the service beyond the expectations of the internal and external clients.','Delivers the service beyond the expectations of the internal and external clients.',0,'Scale_1_4',8);

-- CEO-001 creates Annual for VP for Acquired Properties.
INSERT INTO evaluation_templates
(template_name,description,target_department,target_department_id,target_job_title_id,
 behavior_framework_code,behavior_framework_version,evaluation_type,kra_weight,behavior_weight,
 form_code,revision_date,effective_date_form,status,created_by)
VALUES ('[SAMPLE] Annual - VP for Acquired Properties',
 'Fictional testing form. Review performance against documented role targets.',
 (SELECT department_name FROM departments WHERE department_id=1),1,100,
 'CORE-VALUES','1.0','Annual',80,20,'SAMPLE-AP-100-ANNUAL','2026-11-11','2026-11-11','Active',
 (SELECT user_id FROM users WHERE username='CEO-001'));
SET @sample_template_id = LAST_INSERT_ID();

INSERT INTO evaluation_template_positions (template_id,job_title_id)
SELECT @sample_template_id,job_title_id FROM job_titles
WHERE department_id=1 AND job_title_id BETWEEN 100 AND 100 AND is_active=1;

INSERT INTO evaluation_criteria
(template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order) VALUES

(@sample_template_id,'KRA','Portfolio strategy','Present a documented portfolio plan with quarterly targets and risk priorities.','Present a documented portfolio plan with quarterly targets and risk priorities.',10,'Scale_1_4',1),
(@sample_template_id,'KRA','Disposition performance','Achieve approved disposition targets and explain material monthly variances.','Achieve approved disposition targets and explain material monthly variances.',10,'Scale_1_4',2),
(@sample_template_id,'KRA','Valuation governance','Ensure every proposed sale has a documented valuation and approval trail.','Ensure every proposed sale has a documented valuation and approval trail.',10,'Scale_1_4',3),
(@sample_template_id,'KRA','Acquisition oversight','Review all escalated acquisition proposals against approved investment criteria.','Review all escalated acquisition proposals against approved investment criteria.',10,'Scale_1_4',4),
(@sample_template_id,'KRA','Legal risk oversight','Track high-risk title and litigation issues and assign documented resolution actions.','Track high-risk title and litigation issues and assign documented resolution actions.',10,'Scale_1_4',5),
(@sample_template_id,'KRA','Budget stewardship','Keep approved portfolio expenses within budget or obtain prior variance approval.','Keep approved portfolio expenses within budget or obtain prior variance approval.',10,'Scale_1_4',6),
(@sample_template_id,'KRA','Manager performance','Conduct documented quarterly reviews of AP managers and their delivery commitments.','Conduct documented quarterly reviews of AP managers and their delivery commitments.',10,'Scale_1_4',7),
(@sample_template_id,'KRA','Regulatory compliance','Close all due portfolio compliance actions within approved deadlines.','Close all due portfolio compliance actions within approved deadlines.',10,'Scale_1_4',8),
(@sample_template_id,'KRA','Executive reporting','Submit accurate monthly portfolio reports with decisions required and supporting evidence.','Submit accurate monthly portfolio reports with decisions required and supporting evidence.',10,'Scale_1_4',9),
(@sample_template_id,'KRA','Stakeholder governance','Resolve escalated partner and branch issues within agreed service timelines.','Resolve escalated partner and branch issues within agreed service timelines.',10,'Scale_1_4',10),
(@sample_template_id,'Behavior','Positive Attitude','Displays positive attitude at work.','Displays positive attitude at work.',0,'Scale_1_4',1),
(@sample_template_id,'Behavior','Respect','Shows respect to all people in the organization.','Shows respect to all people in the organization.',0,'Scale_1_4',2),
(@sample_template_id,'Behavior','Accountability','Takes full responsibility of the job including special task or assignment.','Takes full responsibility of the job including special task or assignment.',0,'Scale_1_4',3),
(@sample_template_id,'Behavior','Commitment','Demonstrates strong commitment to the job.','Demonstrates strong commitment to the job.',0,'Scale_1_4',4),
(@sample_template_id,'Behavior','Teamwork','Works cooperatively with others in achieving the goals.','Works cooperatively with others in achieving the goals.',0,'Scale_1_4',5),
(@sample_template_id,'Behavior','Integrity','Exhibits honesty and strong moral uprightness.','Exhibits honesty and strong moral uprightness.',0,'Scale_1_4',6),
(@sample_template_id,'Behavior','Continuous Improvement','Provides diligent effort to continuously focus on getting better.','Provides diligent effort to continuously focus on getting better.',0,'Scale_1_4',7),
(@sample_template_id,'Behavior','Excellent Client Experience','Delivers the service beyond the expectations of the internal and external clients.','Delivers the service beyond the expectations of the internal and external clients.',0,'Scale_1_4',8);

-- AP-001 creates Quarterly for AP Manager.
INSERT INTO evaluation_templates
(template_name,description,target_department,target_department_id,target_job_title_id,
 behavior_framework_code,behavior_framework_version,evaluation_type,kra_weight,behavior_weight,
 form_code,revision_date,effective_date_form,status,created_by)
VALUES ('[SAMPLE] Quarterly - AP Manager',
 'Fictional testing form. Review performance against documented role targets.',
 (SELECT department_name FROM departments WHERE department_id=1),1,101,
 'CORE-VALUES','1.0','Quarterly',80,20,'SAMPLE-AP-101-QUARTERLY','2026-11-11','2026-11-11','Active',
 (SELECT user_id FROM users WHERE username='AP-001'));
SET @sample_template_id = LAST_INSERT_ID();

INSERT INTO evaluation_template_positions (template_id,job_title_id)
SELECT @sample_template_id,job_title_id FROM job_titles
WHERE department_id=1 AND job_title_id BETWEEN 101 AND 104 AND is_active=1;

INSERT INTO evaluation_criteria
(template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order) VALUES

(@sample_template_id,'KRA','Operating plan execution','Translate approved AP targets into monthly assignments and track completion.','Translate approved AP targets into monthly assignments and track completion.',10,'Scale_1_4',1),
(@sample_template_id,'KRA','Inventory accuracy','Reconcile acquired property inventory monthly with at least 98% record accuracy.','Reconcile acquired property inventory monthly with at least 98% record accuracy.',10,'Scale_1_4',2),
(@sample_template_id,'KRA','Case documentation','Ensure all submitted acquisition cases pass the completeness checklist.','Ensure all submitted acquisition cases pass the completeness checklist.',10,'Scale_1_4',3),
(@sample_template_id,'KRA','Disposition coordination','Complete assigned property sale milestones within approved schedules.','Complete assigned property sale milestones within approved schedules.',10,'Scale_1_4',4),
(@sample_template_id,'KRA','Valuation review','Validate supporting market evidence for each valuation recommendation.','Validate supporting market evidence for each valuation recommendation.',10,'Scale_1_4',5),
(@sample_template_id,'KRA','Title document control','Track missing title documents weekly and escalate unresolved gaps.','Track missing title documents weekly and escalate unresolved gaps.',10,'Scale_1_4',6),
(@sample_template_id,'KRA','Risk escalation','Escalate identified legal or safety risks within one working day.','Escalate identified legal or safety risks within one working day.',10,'Scale_1_4',7),
(@sample_template_id,'KRA','Cost monitoring','Reconcile monthly AP expenses and explain material budget variances.','Reconcile monthly AP expenses and explain material budget variances.',10,'Scale_1_4',8),
(@sample_template_id,'KRA','Supervisor coaching','Document monthly coaching and delivery reviews for assigned supervisors.','Document monthly coaching and delivery reviews for assigned supervisors.',10,'Scale_1_4',9),
(@sample_template_id,'KRA','Management reporting','Submit complete monthly portfolio progress reports by deadline.','Submit complete monthly portfolio progress reports by deadline.',10,'Scale_1_4',10),
(@sample_template_id,'Behavior','Positive Attitude','Displays positive attitude at work.','Displays positive attitude at work.',0,'Scale_1_4',1),
(@sample_template_id,'Behavior','Respect','Shows respect to all people in the organization.','Shows respect to all people in the organization.',0,'Scale_1_4',2),
(@sample_template_id,'Behavior','Accountability','Takes full responsibility of the job including special task or assignment.','Takes full responsibility of the job including special task or assignment.',0,'Scale_1_4',3),
(@sample_template_id,'Behavior','Commitment','Demonstrates strong commitment to the job.','Demonstrates strong commitment to the job.',0,'Scale_1_4',4),
(@sample_template_id,'Behavior','Teamwork','Works cooperatively with others in achieving the goals.','Works cooperatively with others in achieving the goals.',0,'Scale_1_4',5),
(@sample_template_id,'Behavior','Integrity','Exhibits honesty and strong moral uprightness.','Exhibits honesty and strong moral uprightness.',0,'Scale_1_4',6),
(@sample_template_id,'Behavior','Continuous Improvement','Provides diligent effort to continuously focus on getting better.','Provides diligent effort to continuously focus on getting better.',0,'Scale_1_4',7),
(@sample_template_id,'Behavior','Excellent Client Experience','Delivers the service beyond the expectations of the internal and external clients.','Delivers the service beyond the expectations of the internal and external clients.',0,'Scale_1_4',8);

-- AP-001 creates Annual for AP Manager.
INSERT INTO evaluation_templates
(template_name,description,target_department,target_department_id,target_job_title_id,
 behavior_framework_code,behavior_framework_version,evaluation_type,kra_weight,behavior_weight,
 form_code,revision_date,effective_date_form,status,created_by)
VALUES ('[SAMPLE] Annual - AP Manager',
 'Fictional testing form. Review performance against documented role targets.',
 (SELECT department_name FROM departments WHERE department_id=1),1,101,
 'CORE-VALUES','1.0','Annual',80,20,'SAMPLE-AP-101-ANNUAL','2026-11-11','2026-11-11','Active',
 (SELECT user_id FROM users WHERE username='AP-001'));
SET @sample_template_id = LAST_INSERT_ID();

INSERT INTO evaluation_template_positions (template_id,job_title_id)
SELECT @sample_template_id,job_title_id FROM job_titles
WHERE department_id=1 AND job_title_id BETWEEN 101 AND 104 AND is_active=1;

INSERT INTO evaluation_criteria
(template_id,section,criterion_name,description,kpi_description,weight,scoring_method,sort_order) VALUES

(@sample_template_id,'KRA','Operating plan execution','Translate approved AP targets into monthly assignments and track completion.','Translate approved AP targets into monthly assignments and track completion.',10,'Scale_1_4',1),
(@sample_template_id,'KRA','Inventory accuracy','Reconcile acquired property inventory monthly with at least 98% record accuracy.','Reconcile acquired property inventory monthly with at least 98% record accuracy.',10,'Scale_1_4',2),
(@sample_template_id,'KRA','Case documentation','Ensure all submitted acquisition cases pass the completeness checklist.','Ensure all submitted acquisition cases pass the completeness checklist.',10,'Scale_1_4',3),
(@sample_template_id,'KRA','Disposition coordination','Complete assigned property sale milestones within approved schedules.','Complete assigned property sale milestones within approved schedules.',10,'Scale_1_4',4),
(@sample_template_id,'KRA','Valuation review','Validate supporting market evidence for each valuation recommendation.','Validate supporting market evidence for each valuation recommendation.',10,'Scale_1_4',5),
(@sample_template_id,'KRA','Title document control','Track missing title documents weekly and escalate unresolved gaps.','Track missing title documents weekly and escalate unresolved gaps.',10,'Scale_1_4',6),
(@sample_template_id,'KRA','Risk escalation','Escalate identified legal or safety risks within one working day.','Escalate identified legal or safety risks within one working day.',10,'Scale_1_4',7),
(@sample_template_id,'KRA','Cost monitoring','Reconcile monthly AP expenses and explain material budget variances.','Reconcile monthly AP expenses and explain material budget variances.',10,'Scale_1_4',8),
(@sample_template_id,'KRA','Supervisor coaching','Document monthly coaching and delivery reviews for assigned supervisors.','Document monthly coaching and delivery reviews for assigned supervisors.',10,'Scale_1_4',9),
(@sample_template_id,'KRA','Management reporting','Submit complete monthly portfolio progress reports by deadline.','Submit complete monthly portfolio progress reports by deadline.',10,'Scale_1_4',10),
(@sample_template_id,'Behavior','Positive Attitude','Displays positive attitude at work.','Displays positive attitude at work.',0,'Scale_1_4',1),
(@sample_template_id,'Behavior','Respect','Shows respect to all people in the organization.','Shows respect to all people in the organization.',0,'Scale_1_4',2),
(@sample_template_id,'Behavior','Accountability','Takes full responsibility of the job including special task or assignment.','Takes full responsibility of the job including special task or assignment.',0,'Scale_1_4',3),
(@sample_template_id,'Behavior','Commitment','Demonstrates strong commitment to the job.','Demonstrates strong commitment to the job.',0,'Scale_1_4',4),
(@sample_template_id,'Behavior','Teamwork','Works cooperatively with others in achieving the goals.','Works cooperatively with others in achieving the goals.',0,'Scale_1_4',5),
(@sample_template_id,'Behavior','Integrity','Exhibits honesty and strong moral uprightness.','Exhibits honesty and strong moral uprightness.',0,'Scale_1_4',6),
(@sample_template_id,'Behavior','Continuous Improvement','Provides diligent effort to continuously focus on getting better.','Provides diligent effort to continuously focus on getting better.',0,'Scale_1_4',7),
(@sample_template_id,'Behavior','Excellent Client Experience','Delivers the service beyond the expectations of the internal and external clients.','Delivers the service beyond the expectations of the internal and external clients.',0,'Scale_1_4',8);

COMMIT;

-- Expected: 10 templates, each with 10 KRA, 8 Behavior, KRA total 100.
SELECT t.template_name,u.full_name AS created_by,t.evaluation_type,
 SUM(c.section='KRA') AS kra_items,SUM(c.section='Behavior') AS behavior_items,
 SUM(CASE WHEN c.section='KRA' THEN c.weight ELSE 0 END) AS kra_total
FROM evaluation_templates t
JOIN users u ON u.user_id=t.created_by
JOIN evaluation_criteria c ON c.template_id=t.template_id
WHERE t.form_code LIKE 'SAMPLE-%'
GROUP BY t.template_id,t.template_name,u.full_name,t.evaluation_type
ORDER BY t.template_id;
