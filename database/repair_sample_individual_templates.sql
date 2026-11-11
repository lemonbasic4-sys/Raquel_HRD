-- Repair only this fixture's unused sample templates and known fictional HR employees.
-- Run from C:\xampp\htdocs\Raquel_HRD after the five testing imports.
-- Does not convert templates with submitted evaluations or existing cycle members.
SET NAMES utf8mb4;
USE raquel_hris;
START TRANSACTION;
UPDATE evaluation_templates t
SET individual_workflow=1,
 assessment_from=CASE WHEN evaluation_type='Annual' THEN '2026-01-01' ELSE '2026-11-11' END,
 assessment_to=CASE WHEN evaluation_type='Annual' THEN '2026-12-31' ELSE '2027-02-11' END
WHERE t.form_code IN ('SAMPLE-HR-700-QUARTERLY','SAMPLE-HR-700-ANNUAL',
 'SAMPLE-HR-705-QUARTERLY','SAMPLE-HR-705-ANNUAL',
 'SAMPLE-HR-710-QUARTERLY','SAMPLE-HR-710-ANNUAL',
 'SAMPLE-AP-100-QUARTERLY','SAMPLE-AP-100-ANNUAL',
 'SAMPLE-AP-101-QUARTERLY','SAMPLE-AP-101-ANNUAL')
 AND t.individual_workflow=0
 AND NOT EXISTS (SELECT 1 FROM evaluations ev WHERE ev.template_id=t.template_id
                 AND ev.status NOT IN ('Draft','Pending Self-Rating'));

-- Explicit fictional fixture dates, six months after each original seed hire date.
-- These are test data, not a rule for calculating real employee regularization.
UPDATE employees SET regularization_effective_date='2020-07-15'
WHERE employee_code='HRD-001' AND first_name='Elena' AND last_name='Delgado'
 AND hire_date='2020-01-15' AND regularization_effective_date IS NULL;
UPDATE employees SET regularization_effective_date='2017-08-14'
WHERE employee_code='HRD-002' AND first_name='Patricia' AND last_name='Gomez'
 AND hire_date='2017-02-14' AND regularization_effective_date IS NULL;
UPDATE employees SET regularization_effective_date='2022-07-05'
WHERE employee_code='HRD-003' AND first_name='Miguel' AND last_name='Torres'
 AND hire_date='2022-01-05' AND regularization_effective_date IS NULL;
COMMIT;
