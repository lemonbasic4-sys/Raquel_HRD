-- Correct the unused HR Manager-created probationary form that was initially
-- scoped to HR Manager positions. Do not change templates with evaluations.
-- Safe to re-run: after the first run the old template name no longer matches.
USE raquel_hris;

START TRANSACTION;

DELETE target
FROM evaluation_template_positions AS target
JOIN evaluation_templates AS form ON form.template_id = target.template_id
JOIN users AS creator ON creator.user_id = form.created_by
JOIN departments AS department ON department.department_id = form.target_department_id
WHERE form.template_name = 'Probationary Exclusive HRD Manager Level'
  AND form.evaluation_type IN ('Initial', 'Final')
  AND form.deleted_at IS NULL
  AND creator.role = 'HR Manager'
  AND department.department_name = 'Human Resources'
  AND NOT EXISTS (SELECT 1 FROM evaluations AS review WHERE review.template_id = form.template_id)
  AND EXISTS (SELECT 1 FROM job_titles AS position
              WHERE position.department_id = department.department_id
                AND position.rank_category_id = 4 AND position.is_active = 1);

INSERT IGNORE INTO evaluation_template_positions (template_id, job_title_id)
SELECT form.template_id, position.job_title_id
FROM evaluation_templates AS form
JOIN users AS creator ON creator.user_id = form.created_by
JOIN departments AS department ON department.department_id = form.target_department_id
JOIN job_titles AS position ON position.department_id = department.department_id
    AND position.rank_category_id = 4 AND position.is_active = 1
WHERE form.template_name = 'Probationary Exclusive HRD Manager Level'
  AND form.evaluation_type IN ('Initial', 'Final')
  AND form.deleted_at IS NULL
  AND creator.role = 'HR Manager'
  AND department.department_name = 'Human Resources'
  AND NOT EXISTS (SELECT 1 FROM evaluations AS review WHERE review.template_id = form.template_id);

UPDATE evaluation_templates AS form
JOIN users AS creator ON creator.user_id = form.created_by
JOIN departments AS department ON department.department_id = form.target_department_id
SET form.template_name = 'Probationary Exclusive HRD Supervisor Level',
    form.target_job_title_id = (SELECT MIN(position.job_title_id)
                                FROM job_titles AS position
                                WHERE position.department_id = department.department_id
                                  AND position.rank_category_id = 4 AND position.is_active = 1)
WHERE form.template_name = 'Probationary Exclusive HRD Manager Level'
  AND form.evaluation_type IN ('Initial', 'Final')
  AND form.deleted_at IS NULL
  AND creator.role = 'HR Manager'
  AND department.department_name = 'Human Resources'
  AND NOT EXISTS (SELECT 1 FROM evaluations AS review WHERE review.template_id = form.template_id)
  AND EXISTS (SELECT 1 FROM job_titles AS position
              WHERE position.department_id = department.department_id
                AND position.rank_category_id = 4 AND position.is_active = 1);

COMMIT;
