-- Fictional, clearly marked events for reviewing Admin > Audit Trail.
-- Safe to re-run: each demo key is inserted only once.
-- These entries are not evidence that the described actions occurred.
-- To remove only this fixture later:
-- DELETE FROM audit_logs WHERE details LIKE '[DEMO AUDIT %';

INSERT INTO audit_logs
    (user_id, action_type, entity_type, module_name, entity_id, target_employee_id,
     details, previous_value, new_value, branch_id, department_id, ip_address,
     user_agent, action_status, timestamp)
SELECT NULL, sample.action_type, sample.entity_type, sample.module_name, NULL, NULL,
       CONCAT('[DEMO AUDIT ', sample.demo_key, '] ', sample.event_summary),
       sample.previous_value, sample.new_value, NULL, NULL, '192.0.2.15',
       'Demo browser (fictional event)', sample.action_status,
       DATE_SUB(NOW(), INTERVAL sample.hours_ago HOUR)
FROM (
    SELECT 'demo-001' AS demo_key, 'LOGIN' AS action_type, 'Authentication' AS entity_type,
           'Authentication & Access' AS module_name, 'Fictional Admin sign-in succeeded.' AS event_summary,
           NULL AS previous_value, NULL AS new_value, 'Successful' AS action_status, 2 AS hours_ago
    UNION ALL SELECT 'demo-002', 'LOGIN', 'Authentication', 'Authentication & Access',
           'Fictional sign-in was rejected after an incorrect password.', NULL, NULL, 'Failed', 4
    UNION ALL SELECT 'demo-003', 'ACCESS_DENIED', 'Authentication', 'Authentication & Access',
           'Fictional account tried to open an unauthorized page.', NULL, NULL, 'Failed', 6
    UNION ALL SELECT 'demo-004', 'LOGOUT', 'Authentication', 'Authentication & Access',
           'Fictional Admin session ended.', NULL, NULL, 'Successful', 7
    UNION ALL SELECT 'demo-005', 'CREATE', 'User', 'User & Access Management',
           'Fictional Employee Portal account was created.', NULL, 'Employee account', 'Successful', 24
    UNION ALL SELECT 'demo-006', 'ROLE_CHANGE', 'User', 'User & Access Management',
           'Fictional account role was changed.', 'HR Staff', 'HR Supervisor', 'Successful', 27
    UNION ALL SELECT 'demo-007', 'UPDATE', 'User', 'User & Access Management',
           'Fictional account was deactivated.', 'Active', 'Inactive', 'Successful', 31
    UNION ALL SELECT 'demo-008', 'CREATE', 'Employee', 'Employee Management',
           'Fictional employee record was added.', NULL, 'Probationary employee', 'Successful', 48
    UNION ALL SELECT 'demo-009', 'EDIT_STEP', 'EmployeeEditHistory', 'Employee Management',
           'Fictional employee profile information was corrected.', 'Previous profile version', 'Updated profile version', 'Successful', 51
    UNION ALL SELECT 'demo-010', 'CREATE', 'Branch', 'System Administration',
           'Fictional branch was added.', NULL, 'Demo branch', 'Successful', 72
    UNION ALL SELECT 'demo-011', 'UPDATE', 'Template', 'Performance & Evaluation',
           'Fictional probationary evaluation template was published.', 'Draft', 'Active', 'Successful', 76
    UNION ALL SELECT 'demo-012', 'SIGN', 'Evaluation', 'Performance & Evaluation',
           'Fictional immediate head signed a probationary recommendation.', NULL, 'Recommendation recorded', 'Successful', 96
    UNION ALL SELECT 'demo-013', 'RETURN', 'EvaluationPackage', 'Performance & Evaluation',
           'Fictional evaluation package was returned for revision.', 'Pending approval', 'Returned', 'Successful', 99
    UNION ALL SELECT 'demo-014', 'FINALIZE', 'EvaluationPackage', 'Performance & Evaluation',
           'Fictional evaluation package received final approval.', 'Pending final review', 'Approved and applied', 'Successful', 120
    UNION ALL SELECT 'demo-015', 'APPROVE', 'Career Movement', 'Career Progression',
           'Fictional career movement was approved.', 'Pending', 'Approved', 'Successful', 144
    UNION ALL SELECT 'demo-016', 'CREATE', 'Backup', 'System Administration',
           'Fictional manual database backup completed.', NULL, 'Backup available', 'Successful', 168
    UNION ALL SELECT 'demo-017', 'BACKUP_FAILED', 'AutoBackup', 'System Administration',
           'Fictional scheduled database backup failed.', NULL, NULL, 'Failed', 192
    UNION ALL SELECT 'demo-018', 'UPDATE', 'Settings', 'System Administration',
           'Fictional system setting was changed.', 'Previous demo setting', 'Updated demo setting', 'Successful', 216
    UNION ALL SELECT 'demo-019', 'EXPORT', 'Audit Trail', 'System Activity',
           'Fictional audit report was exported.', NULL, NULL, 'Successful', 240
) AS sample
WHERE NOT EXISTS (
    SELECT 1 FROM audit_logs existing
    WHERE existing.details LIKE CONCAT('[DEMO AUDIT ', sample.demo_key, ']%')
);
