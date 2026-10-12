-- Backfill position scope for legacy probationary forms that were saved without targets.
-- Safe to re-run: templates with explicit positions are untouched.
USE raquel_hris;

INSERT IGNORE INTO evaluation_template_positions (template_id, job_title_id)
SELECT et.template_id, jt.job_title_id
FROM evaluation_templates et
JOIN users creator ON creator.user_id = et.created_by
LEFT JOIN employees creator_employee ON creator_employee.employee_id = creator.employee_id
JOIN departments d ON d.department_id = et.target_department_id
    OR (et.target_department_id IS NULL AND d.department_name = et.target_department)
JOIN job_titles jt ON jt.department_id = d.department_id
    AND jt.rank_category_id = CASE
        WHEN creator.role = 'HR Supervisor' THEN 5
        WHEN creator.role IN ('HR Manager', 'President and CEO') THEN 3
        WHEN creator_employee.rank_category_id = 4 THEN 5
        WHEN creator_employee.rank_category_id = 3 THEN 4
        ELSE 0
    END
    AND jt.is_active = 1
WHERE et.evaluation_type IN ('Initial', 'Final')
  AND et.deleted_at IS NULL
  AND et.target_job_title_id IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM evaluation_template_positions existing
      WHERE existing.template_id = et.template_id
  );
