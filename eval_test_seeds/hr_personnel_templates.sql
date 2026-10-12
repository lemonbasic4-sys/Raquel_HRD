USE raquel_hris;
SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM evaluation_criteria WHERE template_id IN (1, 2);
DELETE FROM evaluation_template_positions WHERE template_id IN (1, 2);
DELETE FROM evaluation_templates WHERE template_id IN (1, 2);

REPLACE INTO evaluation_templates (
    template_id,
    template_name,
    description,
    target_department,
    target_department_id,
    target_job_title_id,
    behavior_framework_code,
    behavior_framework_version,
    evaluation_type,
    kra_weight,
    behavior_weight,
    form_code,
    revision_date,
    effective_date_form,
    status,
    created_by
) VALUES
    (
        1,
        'Initial Evaluation on Probationary Staff/s',
        'This is the initial evaluation for probationary staff.',
        'Human Resources',
        7,
        710,
        NULL,
        NULL,
        'Initial',
        80.00,
        20.00,
        'hrd_staff_probi_form_code',
        '2026-10-13',
        '2026-10-13',
        'Active',
        3
    ),
    (
        2,
        'Initial Evaluation on Probationary HR Supervisor/s',
        'HR Supervisor on probation',
        'Human Resources',
        7,
        705,
        NULL,
        NULL,
        'Initial',
        80.00,
        20.00,
        'hrd_supervisor_probi_form_code',
        '2026-10-13',
        '2026-10-13',
        'Active',
        2
    );

REPLACE INTO evaluation_template_positions (template_id, job_title_id) VALUES
    (1, 710), (1, 711), (1, 712), (1, 713), (1, 714),
    (2, 705), (2, 706), (2, 707), (2, 708), (2, 709);

REPLACE INTO evaluation_criteria (
    criterion_id,
    template_id,
    section,
    criterion_name,
    description,
    kpi_description,
    weight,
    scoring_method,
    sort_order
) VALUES
    (1, 1, 'KRA', 'Employee Record Accuracy', 'Creates and updates assigned employee records accurately, completely, and with the required supporting documents.', NULL, 15.00, 'Scale_1_4', 1),
    (2, 1, 'KRA', 'HR Transaction Timeliness', 'Completes assigned HR requests and routine transactions within agreed deadlines; promptly communicates delays.', NULL, 12.00, 'Scale_1_4', 2),
    (3, 1, 'KRA', 'Procedure and Documentation Compliance', 'Follows approved HR procedures, uses the correct forms, and maintains complete documentation for assigned work.', NULL, 12.00, 'Scale_1_4', 3),
    (4, 1, 'KRA', 'Employee Information Confidentiality', 'Handles personal and employment information discreetly and accesses or shares it only when authorized.', NULL, 10.00, 'Scale_1_4', 4),
    (5, 1, 'KRA', 'Attendance and Deadline Reliability', 'Reports to work as scheduled, manages assigned priorities, and consistently meets commitments.', NULL, 10.00, 'Scale_1_4', 5),
    (6, 1, 'KRA', 'Employee Inquiry Support', 'Responds to employee inquiries courteously and accurately, or refers questions to the appropriate HR personnel.', NULL, 10.00, 'Scale_1_4', 6),
    (7, 1, 'KRA', 'Onboarding Coordination', 'Helps prepare, track, and follow up on onboarding and probationary requirements assigned to the HR team.', NULL, 8.00, 'Scale_1_4', 7),
    (8, 1, 'KRA', 'Team and Supervisor Coordination', 'Provides clear updates, follows instructions, and coordinates effectively with supervisors and HR colleagues.', NULL, 7.00, 'Scale_1_4', 8),
    (9, 1, 'KRA', 'HRIS Data Entry', 'Enters and updates information in the HRIS correctly, following established naming, validation, and filing standards.', NULL, 6.00, 'Scale_1_4', 9),
    (10, 1, 'KRA', 'Record Discrepancy Resolution', 'Identifies missing or inconsistent information and reports or corrects it through the approved process.', NULL, 5.00, 'Scale_1_4', 10),
    (11, 1, 'KRA', 'Training Application', 'Learns assigned HR processes and applies feedback and training to improve the quality of work.', NULL, 5.00, 'Scale_1_4', 11),
    (12, 1, 'Behavior', 'Positive Attitude', NULL, 'Displays positive attitude at work.', 0.00, 'Scale_1_4', 1),
    (13, 1, 'Behavior', 'Respect', NULL, 'Shows respect to all people in the organization.', 0.00, 'Scale_1_4', 2),
    (14, 1, 'Behavior', 'Accountability', NULL, 'Takes full responsibility of the job including special task or assignment.', 0.00, 'Scale_1_4', 3),
    (15, 1, 'Behavior', 'Commitment', NULL, 'Demonstrates strong commitment to the job.', 0.00, 'Scale_1_4', 4),
    (16, 1, 'Behavior', 'Teamwork', NULL, 'Works cooperatively with others in achieving the goals.', 0.00, 'Scale_1_4', 5),
    (17, 1, 'Behavior', 'Integrity', NULL, 'Exhibits honesty and strong moral uprightness.', 0.00, 'Scale_1_4', 6),
    (18, 1, 'Behavior', 'Continuous Improvement', NULL, 'Provides diligent effort to continuously focus on getting better.', 0.00, 'Scale_1_4', 7),
    (19, 1, 'Behavior', 'Excellent Client Experience', NULL, 'Delivers the service beyond the expectations of the internal and external clients.', 0.00, 'Scale_1_4', 8),

    (20, 2, 'KRA', 'Employee Record Accuracy', 'Creates and updates assigned employee records accurately, completely, and with the required supporting documents.', NULL, 15.00, 'Scale_1_4', 1),
    (21, 2, 'KRA', 'HR Transaction Timeliness', 'Completes assigned HR requests and routine transactions within agreed deadlines; promptly communicates delays.', NULL, 12.00, 'Scale_1_4', 2),
    (22, 2, 'KRA', 'Procedure and Documentation Compliance', 'Follows approved HR procedures, uses the correct forms, and maintains complete documentation for assigned work.', NULL, 12.00, 'Scale_1_4', 3),
    (23, 2, 'KRA', 'Employee Information Confidentiality', 'Handles personal and employment information discreetly and accesses or shares it only when authorized.', NULL, 10.00, 'Scale_1_4', 4),
    (24, 2, 'KRA', 'Attendance and Deadline Reliability', 'Reports to work as scheduled, manages assigned priorities, and consistently meets commitments.', NULL, 10.00, 'Scale_1_4', 5),
    (25, 2, 'KRA', 'Employee Inquiry Support', 'Responds to employee inquiries courteously and accurately, or refers questions to the appropriate HR personnel.', NULL, 10.00, 'Scale_1_4', 6),
    (26, 2, 'KRA', 'Onboarding Coordination', 'Helps prepare, track, and follow up on onboarding and probationary requirements assigned to the HR team.', NULL, 8.00, 'Scale_1_4', 7),
    (27, 2, 'KRA', 'Team and Supervisor Coordination', 'Provides clear updates, follows instructions, and coordinates effectively with supervisors and HR colleagues.', NULL, 7.00, 'Scale_1_4', 8),
    (28, 2, 'KRA', 'HRIS Data Entry', 'Enters and updates information in the HRIS correctly, following established naming, validation, and filing standards.', NULL, 6.00, 'Scale_1_4', 9),
    (29, 2, 'KRA', 'Record Discrepancy Resolution', 'Identifies missing or inconsistent information and reports or corrects it through the approved process.', NULL, 5.00, 'Scale_1_4', 10),
    (30, 2, 'KRA', 'Training Application', 'Learns assigned HR processes and applies feedback and training to improve the quality of work.', NULL, 5.00, 'Scale_1_4', 11),
    (31, 2, 'Behavior', 'Positive Attitude', NULL, 'Displays positive attitude at work.', 0.00, 'Scale_1_4', 1),
    (32, 2, 'Behavior', 'Respect', NULL, 'Shows respect to all people in the organization.', 0.00, 'Scale_1_4', 2),
    (33, 2, 'Behavior', 'Accountability', NULL, 'Takes full responsibility of the job including special task or assignment.', 0.00, 'Scale_1_4', 3),
    (34, 2, 'Behavior', 'Commitment', NULL, 'Demonstrates strong commitment to the job.', 0.00, 'Scale_1_4', 4),
    (35, 2, 'Behavior', 'Teamwork', NULL, 'Works cooperatively with others in achieving the goals.', 0.00, 'Scale_1_4', 5),
    (36, 2, 'Behavior', 'Integrity', NULL, 'Exhibits honesty and strong moral uprightness.', 0.00, 'Scale_1_4', 6),
    (37, 2, 'Behavior', 'Continuous Improvement', NULL, 'Provides diligent effort to continuously focus on getting better.', 0.00, 'Scale_1_4', 7),
    (38, 2, 'Behavior', 'Excellent Client Experience', NULL, 'Delivers the service beyond the expectations of the internal and external clients.', 0.00, 'Scale_1_4', 8);

SET FOREIGN_KEY_CHECKS = 1;
