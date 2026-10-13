USE raquel_hris;
SET FOREIGN_KEY_CHECKS = 0;

-- Final probationary evaluation completed by an HR Manager for HR Supervisors.
-- Template 5 is separate from the initial probationary templates (1 and 2).
DELETE FROM evaluation_criteria WHERE template_id = 5;
DELETE FROM evaluation_template_positions WHERE template_id = 5;
DELETE FROM evaluation_templates WHERE template_id = 5;

REPLACE INTO evaluation_templates (
    template_id, template_name, description, target_department, target_department_id,
    target_job_title_id, behavior_framework_code, behavior_framework_version,
    evaluation_type, kra_weight, behavior_weight, form_code, revision_date,
    effective_date_form, status, created_by
) VALUES (
    5,
    'Final Evaluation on Probationary HR Supervisor/s',
    'Final probationary evaluation completed by the HR Manager to determine the HR Supervisor''s readiness for regularization.',
    'Human Resources', 7, 705, NULL, NULL, 'Final', 80.00, 20.00,
    'hrd_supervisor_final_probi_form_code', '2026-10-13', '2026-10-13', 'Active', 2
);

REPLACE INTO evaluation_template_positions (template_id, job_title_id) VALUES
    (5, 705), (5, 706), (5, 707), (5, 708), (5, 709);

REPLACE INTO evaluation_criteria
    (criterion_id, template_id, section, criterion_name, description, kpi_description, weight, scoring_method, sort_order)
VALUES
    (65, 5, 'KRA', 'Team Supervision and Staff Guidance', 'Directs, coaches, and monitors HR staff; provides clear instructions, feedback, and follow-through on assigned work.', NULL, 15.00, 'Scale_1_4', 1),
    (66, 5, 'KRA', 'HR Operations and Transaction Control', 'Ensures HR requests, records, reports, and routine transactions are processed accurately and within agreed timelines.', NULL, 12.00, 'Scale_1_4', 2),
    (67, 5, 'KRA', 'Procedure and Documentation Compliance', 'Applies approved HR procedures, verifies supporting documents, and maintains complete and audit-ready records.', NULL, 12.00, 'Scale_1_4', 3),
    (68, 5, 'KRA', 'Employee Relations and Issue Resolution', 'Handles employee concerns professionally, resolves routine issues, and escalates sensitive or complex matters appropriately.', NULL, 10.00, 'Scale_1_4', 4),
    (69, 5, 'KRA', 'Attendance, Reliability, and Work Discipline', 'Maintains dependable attendance, plans team priorities, and consistently meets commitments and deadlines.', NULL, 10.00, 'Scale_1_4', 5),
    (70, 5, 'KRA', 'HRIS and Personnel Data Accuracy', 'Reviews HRIS entries and personnel records for accuracy, completeness, confidentiality, and timely correction of discrepancies.', NULL, 10.00, 'Scale_1_4', 6),
    (71, 5, 'KRA', 'Onboarding and Probationary Monitoring', 'Coordinates onboarding requirements and monitors probationary staff progress, documentation, and follow-up actions.', NULL, 8.00, 'Scale_1_4', 7),
    (72, 5, 'KRA', 'Reporting and Manager Coordination', 'Provides clear status updates, recommendations, and escalations to the HR Manager and coordinates effectively with HR colleagues.', NULL, 7.00, 'Scale_1_4', 8),
    (73, 5, 'KRA', 'Final Deliverables and Turnover Readiness', 'Closes pending work and prepares complete endorsements, files, and handover information before the final review.', NULL, 6.00, 'Scale_1_4', 9),
    (74, 5, 'KRA', 'Learning Application and Continuous Improvement', 'Applies feedback and training, identifies process gaps, and implements practical improvements in team operations.', NULL, 5.00, 'Scale_1_4', 10),
    (75, 5, 'Behavior', 'Positive Attitude', NULL, 'Displays positive attitude at work.', 0.00, 'Scale_1_4', 1),
    (76, 5, 'Behavior', 'Respect', NULL, 'Shows respect to all people in the organization.', 0.00, 'Scale_1_4', 2),
    (77, 5, 'Behavior', 'Accountability', NULL, 'Takes full responsibility of the job including special task or assignment.', 0.00, 'Scale_1_4', 3),
    (78, 5, 'Behavior', 'Commitment', NULL, 'Demonstrates strong commitment to the job.', 0.00, 'Scale_1_4', 4),
    (79, 5, 'Behavior', 'Teamwork', NULL, 'Works cooperatively with others in achieving the goals.', 0.00, 'Scale_1_4', 5),
    (80, 5, 'Behavior', 'Integrity', NULL, 'Exhibits honesty and strong moral uprightness.', 0.00, 'Scale_1_4', 6),
    (81, 5, 'Behavior', 'Continuous Improvement', NULL, 'Provides diligent effort to continuously focus on getting better.', 0.00, 'Scale_1_4', 7),
    (82, 5, 'Behavior', 'Excellent Client Experience', NULL, 'Delivers the service beyond the expectations of the internal and external clients.', 0.00, 'Scale_1_4', 8);

SET FOREIGN_KEY_CHECKS = 1;


-- HR Supervisor final probationary evaluation for HR Staff is included in the companion seed: hr_supervisor_final_staff_probationary.sql
USE raquel_hris;
SET FOREIGN_KEY_CHECKS = 0;

-- Final probationary evaluation completed by an HR Supervisor for HR Staff.
-- Template 6 is separate from the initial probationary templates (1 and 2).
DELETE FROM evaluation_criteria WHERE template_id = 6;
DELETE FROM evaluation_template_positions WHERE template_id = 6;
DELETE FROM evaluation_templates WHERE template_id = 6;

REPLACE INTO evaluation_templates (
    template_id, template_name, description, target_department, target_department_id,
    target_job_title_id, behavior_framework_code, behavior_framework_version,
    evaluation_type, kra_weight, behavior_weight, form_code, revision_date,
    effective_date_form, status, created_by
) VALUES (
    6,
    'Final Evaluation on Probationary HR Staff/s',
    'Final probationary evaluation completed by the HR Supervisor to determine the HR Staff member''s readiness for regularization.',
    'Human Resources', 7, 710, NULL, NULL, 'Final', 80.00, 20.00,
    'hrd_staff_final_probi_form_code', '2026-10-13', '2026-10-13', 'Active', 3
);

REPLACE INTO evaluation_template_positions (template_id, job_title_id) VALUES
    (6, 710), (6, 711), (6, 712), (6, 713), (6, 714);

REPLACE INTO evaluation_criteria
    (criterion_id, template_id, section, criterion_name, description, kpi_description, weight, scoring_method, sort_order)
VALUES
    (83, 6, 'KRA', 'Employee Record Accuracy', 'Creates and updates assigned employee records accurately, completely, and with required supporting documents through the final review period.', NULL, 15.00, 'Scale_1_4', 1),
    (84, 6, 'KRA', 'HR Transaction Timeliness', 'Completes assigned HR requests and routine transactions within agreed deadlines and promptly communicates delays.', NULL, 12.00, 'Scale_1_4', 2),
    (85, 6, 'KRA', 'Procedure and Documentation Compliance', 'Follows approved HR procedures, uses the correct forms, and maintains complete documentation for assigned work.', NULL, 12.00, 'Scale_1_4', 3),
    (86, 6, 'KRA', 'Employee Information Confidentiality', 'Handles personal and employment information discreetly and accesses or shares it only when authorized.', NULL, 10.00, 'Scale_1_4', 4),
    (87, 6, 'KRA', 'Attendance and Deadline Reliability', 'Reports to work as scheduled, manages priorities, and consistently meets commitments and deadlines.', NULL, 10.00, 'Scale_1_4', 5),
    (88, 6, 'KRA', 'Employee Inquiry Support', 'Responds to employee inquiries courteously and accurately, or refers questions to the appropriate HR personnel.', NULL, 10.00, 'Scale_1_4', 6),
    (89, 6, 'KRA', 'Onboarding and Probationary Completion', 'Completes and follows up on onboarding, probationary, and other assigned HR requirements before the final review.', NULL, 8.00, 'Scale_1_4', 7),
    (90, 6, 'KRA', 'Team and Supervisor Coordination', 'Provides clear updates, follows instructions, and coordinates effectively with supervisors and HR colleagues.', NULL, 7.00, 'Scale_1_4', 8),
    (91, 6, 'KRA', 'HRIS Data Entry and Record Resolution', 'Enters HRIS data correctly and identifies, reports, or corrects missing and inconsistent information through the approved process.', NULL, 11.00, 'Scale_1_4', 9),
    (92, 6, 'KRA', 'Final Turnover and Training Application', 'Closes assigned work, organizes files, applies training and feedback, and supports a complete handover.', NULL, 5.00, 'Scale_1_4', 10),
    (93, 6, 'Behavior', 'Positive Attitude', NULL, 'Displays positive attitude at work.', 0.00, 'Scale_1_4', 1),
    (94, 6, 'Behavior', 'Respect', NULL, 'Shows respect to all people in the organization.', 0.00, 'Scale_1_4', 2),
    (95, 6, 'Behavior', 'Accountability', NULL, 'Takes full responsibility of the job including special task or assignment.', 0.00, 'Scale_1_4', 3),
    (96, 6, 'Behavior', 'Commitment', NULL, 'Demonstrates strong commitment to the job.', 0.00, 'Scale_1_4', 4),
    (97, 6, 'Behavior', 'Teamwork', NULL, 'Works cooperatively with others in achieving the goals.', 0.00, 'Scale_1_4', 5),
    (98, 6, 'Behavior', 'Integrity', NULL, 'Exhibits honesty and strong moral uprightness.', 0.00, 'Scale_1_4', 6),
    (99, 6, 'Behavior', 'Continuous Improvement', NULL, 'Provides diligent effort to continuously focus on getting better.', 0.00, 'Scale_1_4', 7),
    (100, 6, 'Behavior', 'Excellent Client Experience', NULL, 'Delivers the service beyond the expectations of the internal and external clients.', 0.00, 'Scale_1_4', 8);

SET FOREIGN_KEY_CHECKS = 1;

