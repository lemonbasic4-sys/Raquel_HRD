-- Read-only checks after 01_seed_templates.sql.
USE raquel_hris;

-- Each row is one Active form. Every form should show 12 KRAs, 8 behaviors,
-- KRA item weight 100.00, and form weights 80.00 / 20.00.
SELECT d.department_name, j.job_title, t.evaluation_type,
       COUNT(CASE WHEN c.section='KRA' THEN 1 END) AS kra_items,
       COUNT(CASE WHEN c.section='Behavior' THEN 1 END) AS behavior_items,
       SUM(CASE WHEN c.section='KRA' THEN c.weight ELSE 0 END) AS kra_item_weight,
       t.kra_weight, t.behavior_weight,
       CONCAT(h.first_name,' ',h.last_name) AS form_author
FROM evaluation_templates t
JOIN departments d ON d.department_id=t.target_department_id
JOIN job_titles j ON j.job_title_id=t.target_job_title_id
JOIN users u ON u.user_id=t.created_by
JOIN employees h ON h.employee_id=u.employee_id
LEFT JOIN evaluation_criteria c ON c.template_id=t.template_id
WHERE t.template_id BETWEEN 200000 AND 219999
GROUP BY t.template_id,d.department_name,j.job_title,t.evaluation_type,
         t.kra_weight,t.behavior_weight,h.first_name,h.last_name
ORDER BY d.department_name,j.job_title,
         FIELD(t.evaluation_type,'Initial','Final','Quarterly','Annual');

-- Expect 0 invalid forms. A missing form author or position link is also invalid.
SELECT COUNT(*) AS invalid_forms
FROM (
  SELECT t.template_id
  FROM evaluation_templates t
  LEFT JOIN evaluation_template_positions p ON p.template_id=t.template_id
    AND p.job_title_id=t.target_job_title_id
  LEFT JOIN evaluation_criteria c ON c.template_id=t.template_id
  LEFT JOIN users u ON u.user_id=t.created_by
  LEFT JOIN employees h ON h.employee_id=u.employee_id
  LEFT JOIN job_titles j ON j.job_title_id=t.target_job_title_id
  WHERE t.template_id BETWEEN 200000 AND 219999
  GROUP BY t.template_id,t.target_department_id,t.target_job_title_id,
           t.kra_weight,t.behavior_weight,t.status
  HAVING COUNT(CASE WHEN c.section='KRA' THEN 1 END)<12
      OR COUNT(CASE WHEN c.section='Behavior' THEN 1 END)<>8
      OR COUNT(CASE WHEN c.section='Behavior' AND c.criterion_name IN
          ('Positive Attitude','Respect','Accountability','Commitment','Teamwork',
           'Integrity','Continuous Improvement','Excellent Client Experience')
          THEN 1 END)<>8
      OR ROUND(SUM(CASE WHEN c.section='KRA' THEN c.weight ELSE 0 END),2)<>100.00
      OR t.kra_weight<>80.00 OR t.behavior_weight<>20.00
      OR t.status<>'Active' OR MAX(p.job_title_id) IS NULL
      OR MAX(h.job_title_id) IS NULL OR MAX(h.job_title_id)<>MAX(j.reports_to)
) problems;

SELECT evaluation_type,COUNT(*) AS forms
FROM evaluation_templates
WHERE template_id BETWEEN 200000 AND 219999
GROUP BY evaluation_type
ORDER BY FIELD(evaluation_type,'Initial','Final','Quarterly','Annual');
