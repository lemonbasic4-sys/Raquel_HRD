SET NAMES utf8mb4;

-- Phase 1 already represents regularization through employee fields and work experience.
-- Do not duplicate it in the evaluation-linked regularization request workflow.
SELECT COUNT(DISTINCT e.employee_id) AS regular_demo_employees,
       COUNT(DISTINCT CASE WHEN p.employee_id IS NOT NULL THEN e.employee_id END) AS with_probationary_history,
       COUNT(DISTINCT CASE WHEN r.employee_id IS NOT NULL THEN e.employee_id END) AS with_regular_history
FROM employees e
LEFT JOIN employee_work_experience p ON p.employee_id=e.employee_id AND p.company_name='Raquel Pawnshop, Inc.' AND p.appointment_status='Probationary'
LEFT JOIN employee_work_experience r ON r.employee_id=e.employee_id AND r.company_name='Raquel Pawnshop, Inc.' AND r.appointment_status='Regular'
WHERE e.employee_code LIKE 'DEMO-%' AND e.employment_status='Regular';

SELECT COUNT(*) AS duplicate_regularization_requests_created
FROM regularization_requests rr JOIN employees e ON e.employee_id=rr.employee_id
WHERE e.employee_code LIKE 'DEMO-%';
