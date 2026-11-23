SET NAMES utf8mb4;

-- Workforce summaries consumed by dashboard and analytics pages.
SELECT 'WORKFORCE_BY_DEPARTMENT' AS check_name,d.department_name,e.employment_status,COUNT(*) AS employee_count
FROM employees e JOIN departments d ON d.department_id=e.department_id
WHERE e.employee_code LIKE 'DEMO-%' AND e.is_active=1 AND e.deleted_at IS NULL
GROUP BY d.department_id,d.department_name,e.employment_status
ORDER BY d.department_id,e.employment_status;

SELECT 'WORKFORCE_BY_BRANCH' AS check_name,b.branch_name,COUNT(*) AS employee_count
FROM employees e JOIN branches b ON b.branch_id=e.branch_id
WHERE e.employee_code LIKE 'DEMO-%' AND e.is_active=1 AND e.deleted_at IS NULL
GROUP BY b.branch_id,b.branch_name ORDER BY b.branch_id;

SELECT 'WORKFORCE_HEADLINE' AS check_name,COUNT(*) AS presentation_employees,
       SUM(e.employment_status='Regular') AS regular_employees,
       SUM(e.employment_status='Probationary') AS probationary_employees,
       COUNT(DISTINCT e.department_id) AS departments_covered,
       COUNT(DISTINCT e.branch_id) AS branches_covered
FROM employees e WHERE e.employee_code LIKE 'DEMO-%';

-- Finalized evaluation summaries consumed by performance analytics.
SELECT 'APPROVED_EVALUATIONS_BY_YEAR' AS check_name,YEAR(ev.approved_date) AS approval_year,
       ev.evaluation_type,COUNT(*) AS evaluation_count,ROUND(AVG(ev.total_score),2) AS average_score
FROM evaluations ev
WHERE ev.legacy_reference LIKE 'PRES-%' AND ev.status='Approved'
GROUP BY YEAR(ev.approved_date),ev.evaluation_type
ORDER BY approval_year,FIELD(ev.evaluation_type,'Initial','Final','Annual');

SELECT 'DEPARTMENT_ANNUAL_PERFORMANCE' AS check_name,d.department_name,
       COUNT(*) AS evaluation_count,ROUND(AVG(ev.total_score),2) AS average_score,
       ROUND(AVG(ev.kra_subtotal),2) AS average_kra,
       ROUND(AVG(ev.behavior_average),2) AS average_behavior
FROM evaluations ev JOIN employees e ON e.employee_id=ev.employee_id
JOIN departments d ON d.department_id=e.department_id
WHERE ev.legacy_reference LIKE 'PRES-ANNUAL-%' AND ev.status='Approved'
GROUP BY d.department_id,d.department_name ORDER BY d.department_id;

SELECT 'ANNUAL_HISTORICAL_COMPARISON' AS check_name,YEAR(ev.evaluation_period_end) AS evaluation_year,
       COUNT(*) AS evaluation_count,ROUND(AVG(ev.total_score),2) AS average_score,
       ROUND(AVG(ev.kra_subtotal),2) AS average_kra,
       ROUND(AVG(ev.behavior_average),2) AS average_behavior
FROM evaluations ev
WHERE ev.legacy_reference LIKE 'PRES-ANNUAL-%' AND ev.status='Approved'
GROUP BY YEAR(ev.evaluation_period_end) ORDER BY evaluation_year;

SELECT 'PERFORMANCE_LEVEL_DISTRIBUTION' AS check_name,ev.performance_level,COUNT(*) AS evaluation_count
FROM evaluations ev
WHERE ev.legacy_reference LIKE 'PRES-%' AND ev.status='Approved'
GROUP BY ev.performance_level ORDER BY MIN(ev.total_score);

-- Regularization, transfers, and promotion readiness.
SELECT 'REGULARIZATION_READINESS' AS check_name,
       COUNT(DISTINCT e.employee_id) AS regular_employees,
       COUNT(DISTINCT CASE WHEN p.employee_id IS NOT NULL THEN e.employee_id END) AS with_probationary_history,
       COUNT(DISTINCT CASE WHEN r.employee_id IS NOT NULL THEN e.employee_id END) AS with_regular_history
FROM employees e
LEFT JOIN employee_work_experience p ON p.employee_id=e.employee_id AND p.company_name='Raquel Pawnshop, Inc.' AND p.appointment_status='Probationary'
LEFT JOIN employee_work_experience r ON r.employee_id=e.employee_id AND r.company_name='Raquel Pawnshop, Inc.' AND r.appointment_status='Regular'
WHERE e.employee_code LIKE 'DEMO-%' AND e.employment_status='Regular';

SELECT 'TRANSFER_ANALYTICS' AS check_name,YEAR(cm.effective_date) AS movement_year,
       cm.approval_status,COUNT(*) AS movement_count
FROM career_movements cm JOIN employees e ON e.employee_id=cm.employee_id
WHERE e.employee_code LIKE 'DEMO-%' AND cm.reason LIKE '[PRESENTATION-SEED:P3-%'
GROUP BY YEAR(cm.effective_date),cm.approval_status ORDER BY movement_year,cm.approval_status;

SELECT 'PROMOTION_ANALYTICS' AS check_name,YEAR(COALESCE(se.applied_at,se.created_at)) AS event_year,
       se.status,COUNT(*) AS endorsement_count
FROM succession_endorsements se JOIN employees e ON e.employee_id=se.employee_id
WHERE e.employee_code LIKE 'DEMO-%' AND se.recommendation_reason LIKE '[PRESENTATION-SEED:P3-%'
GROUP BY YEAR(COALESCE(se.applied_at,se.created_at)),se.status ORDER BY event_year,se.status;

-- Every result below must be zero.
SELECT 'DUPLICATE_EMPLOYEE_CODES' AS check_name,COUNT(*) AS failures FROM (
    SELECT employee_code FROM employees WHERE employee_code LIKE 'DEMO-%'
    GROUP BY employee_code HAVING COUNT(*)>1
) duplicates;

SELECT 'DUPLICATE_PRESENTATION_EVALUATION_KEYS' AS check_name,COUNT(*) AS failures FROM (
    SELECT legacy_reference FROM evaluations WHERE legacy_reference LIKE 'PRES-%'
    GROUP BY legacy_reference HAVING COUNT(*)>1
) duplicates;

SELECT 'MISSING_EMPLOYEE_ORGANIZATION_REFERENCE' AS check_name,COUNT(*) AS failures
FROM employees e
LEFT JOIN departments d ON d.department_id=e.department_id
LEFT JOIN branches b ON b.branch_id=e.branch_id
LEFT JOIN job_titles j ON j.job_title_id=e.job_title_id
WHERE e.employee_code LIKE 'DEMO-%' AND (d.department_id IS NULL OR b.branch_id IS NULL OR j.job_title_id IS NULL);

SELECT 'JOB_TITLE_DEPARTMENT_MISMATCH' AS check_name,COUNT(*) AS failures
FROM employees e JOIN job_titles j ON j.job_title_id=e.job_title_id
WHERE e.employee_code LIKE 'DEMO-%' AND j.department_id<>e.department_id;

SELECT 'BROKEN_EVALUATION_REFERENCE' AS check_name,COUNT(*) AS failures
FROM evaluations ev
LEFT JOIN employees e ON e.employee_id=ev.employee_id
LEFT JOIN evaluation_templates t ON t.template_id=ev.template_id
WHERE ev.legacy_reference LIKE 'PRES-%' AND (e.employee_id IS NULL OR t.template_id IS NULL);

SELECT 'MISSING_APPROVED_EVALUATION_METRIC' AS check_name,COUNT(*) AS failures
FROM evaluations ev WHERE ev.legacy_reference LIKE 'PRES-%' AND ev.status='Approved'
  AND (ev.approved_date IS NULL OR ev.total_score IS NULL OR ev.kra_subtotal IS NULL OR ev.behavior_average IS NULL OR ev.performance_level IS NULL);

SELECT 'INVALID_EVALUATION_DATE' AS check_name,COUNT(*) AS failures
FROM evaluations ev JOIN employees e ON e.employee_id=ev.employee_id
WHERE ev.legacy_reference LIKE 'PRES-%'
  AND (ev.evaluation_period_start>ev.evaluation_period_end OR ev.evaluation_period_start<e.hire_date
       OR (ev.status='Approved' AND ev.approved_date<ev.evaluation_period_end));

SELECT 'BROKEN_MOVEMENT_REFERENCE' AS check_name,COUNT(*) AS failures
FROM career_movements cm
LEFT JOIN employees e ON e.employee_id=cm.employee_id
LEFT JOIN branches pb ON pb.branch_id=cm.previous_branch_id
LEFT JOIN branches nb ON nb.branch_id=cm.new_branch_id
WHERE cm.reason LIKE '[PRESENTATION-SEED:P3-%'
  AND (e.employee_id IS NULL OR (cm.previous_branch_id IS NOT NULL AND pb.branch_id IS NULL) OR (cm.new_branch_id IS NOT NULL AND nb.branch_id IS NULL));

SELECT 'BROKEN_PROMOTION_REFERENCE' AS check_name,COUNT(*) AS failures
FROM succession_endorsements se
LEFT JOIN employees e ON e.employee_id=se.employee_id
LEFT JOIN job_titles j ON j.job_title_id=se.target_job_title_id
LEFT JOIN users u ON u.user_id=se.recommended_by
WHERE se.recommendation_reason LIKE '[PRESENTATION-SEED:P3-%'
  AND (e.employee_id IS NULL OR j.job_title_id IS NULL OR u.user_id IS NULL);

SELECT 'PROTECTED_EMPLOYEE_PRESENTATION_DATA' AS check_name,
       (SELECT COUNT(*) FROM evaluations WHERE employee_id IN (101,301,302) AND legacy_reference LIKE 'PRES-%')
       +(SELECT COUNT(*) FROM career_movements WHERE employee_id IN (101,301,302) AND reason LIKE '[PRESENTATION-SEED:P3-%')
       +(SELECT COUNT(*) FROM succession_endorsements WHERE employee_id IN (101,301,302) AND recommendation_reason LIKE '[PRESENTATION-SEED:P3-%') AS failures;
