SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;
START TRANSACTION;

-- The live demonstration period. Historical records end in 2025; these
-- assignments intentionally remain Pending Self-Rating for employee action.
UPDATE evaluation_templates
SET assessment_from='2026-01-01',assessment_to='2026-12-31',revision_date='2026-11-24',effective_date_form='2026-11-24'
WHERE form_code LIKE 'PRES-%-ANNUAL' AND evaluation_type='Annual' AND deleted_at IS NULL;

INSERT INTO evaluations
 (employee_id,template_id,evaluation_type,evaluation_period_start,evaluation_period_end,
  assigned_by,assigned_at,status,current_position,months_in_position,
  is_historical,record_source,legacy_reference,historical_remarks)
SELECT e.employee_id,t.template_id,'Annual','2026-01-01','2026-12-31',
 (SELECT u.user_id FROM users u WHERE u.employee_id=COALESCE(e.reports_to,(SELECT employee_id FROM employees WHERE employee_code='CEO-001' LIMIT 1)) AND u.is_active=1 AND u.deleted_at IS NULL
  ORDER BY (u.role='Employee') DESC,u.user_id LIMIT 1),
 '2026-11-24 09:00:00','Pending Self-Rating',e.job_title,
 TIMESTAMPDIFF(MONTH,e.hire_date,'2026-12-31'),0,'Presentation Seed',
 CONCAT('PRES-ANNUAL-',e.employee_code,'-2026'),
 'Open 2026 Annual evaluation reserved for the live presentation workflow.'
FROM employees e
JOIN evaluation_templates t ON t.target_department_id=e.department_id AND t.evaluation_type='Annual'
 AND t.form_code LIKE 'PRES-%-ANNUAL' AND t.target_job_title_id=e.job_title_id AND t.deleted_at IS NULL
WHERE (e.employee_code LIKE 'DEMO-%-REG%' OR e.employee_code IN('AP-001','HRD-001','HRD-002','HRD-003')) AND e.employment_status='Regular'
 AND NOT EXISTS(SELECT 1 FROM evaluations x WHERE x.legacy_reference=CONCAT('PRES-ANNUAL-',e.employee_code,'-2026'));

COMMIT;

SELECT status,COUNT(*) AS annual_2026_assignments
FROM evaluations WHERE legacy_reference LIKE 'PRES-ANNUAL-%-2026' GROUP BY status;
