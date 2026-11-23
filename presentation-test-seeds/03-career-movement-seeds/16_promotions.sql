SET NAMES utf8mb4;
START TRANSACTION;
SET @recommender := (SELECT user_id FROM users WHERE username='demo.hr.tristan' LIMIT 1);
SET @hr_reviewer := (SELECT user_id FROM users WHERE username='elena.delgado' LIMIT 1);
SET @ceo := (SELECT user_id FROM users WHERE role='President and CEO' AND is_active=1 ORDER BY user_id DESC LIMIT 1);

INSERT INTO succession_endorsements (employee_id,target_job_title_id,recommended_by,recommendation_reason,status,hr_reviewed_by,hr_reviewed_at,hr_review_notes,qualifications_verified,opportunity_verified,records_verified,ceo_decided_by,ceo_decided_at,ceo_decision_notes,previous_job_title,applied_at,created_at)
SELECT e.employee_id,e.job_title_id,@recommender,'[PRESENTATION-SEED:P3-PROMO-001] Sustained audit quality and readiness for broader review responsibility.','Approved',@hr_reviewer,'2024-07-12 10:00:00','Qualifications and records verified.',1,1,1,@ceo,'2024-07-18 14:00:00','Approved based on performance history.','Auditor V','2024-08-01 08:00:00','2024-07-05 09:00:00'
FROM employees e WHERE e.employee_code='DEMO-AUD-REG2' AND NOT EXISTS (SELECT 1 FROM succession_endorsements x WHERE x.recommendation_reason LIKE '[PRESENTATION-SEED:P3-PROMO-001]%');

INSERT INTO succession_endorsements (employee_id,target_job_title_id,recommended_by,recommendation_reason,status,hr_reviewed_by,hr_reviewed_at,hr_review_notes,qualifications_verified,opportunity_verified,records_verified,ceo_decided_by,ceo_decided_at,ceo_decision_notes,previous_job_title,applied_at,created_at)
SELECT e.employee_id,e.job_title_id,@recommender,'[PRESENTATION-SEED:P3-PROMO-002] Demonstrated leadership in infrastructure support and incident response.','Approved',@hr_reviewer,'2025-05-14 09:30:00','Promotion evidence verified.',1,1,1,@ceo,'2025-05-20 11:00:00','Approved for the next management level.','IT Manager I','2025-06-01 08:00:00','2025-05-06 13:00:00'
FROM employees e WHERE e.employee_code='DEMO-IT-REG2' AND NOT EXISTS (SELECT 1 FROM succession_endorsements x WHERE x.recommendation_reason LIKE '[PRESENTATION-SEED:P3-PROMO-002]%');

INSERT INTO succession_endorsements (employee_id,target_job_title_id,recommended_by,recommendation_reason,status,created_at)
SELECT e.employee_id,901,@recommender,'[PRESENTATION-SEED:P3-PROMO-003] Recommended for Marketing Manager II based on campaign ownership.','Pending HR Review','2026-11-15 10:00:00'
FROM employees e WHERE e.employee_code='DEMO-MKT-REG2' AND NOT EXISTS (SELECT 1 FROM succession_endorsements x WHERE x.recommendation_reason LIKE '[PRESENTATION-SEED:P3-PROMO-003]%');

INSERT INTO succession_endorsements (employee_id,target_job_title_id,recommended_by,recommendation_reason,status,hr_reviewed_by,hr_reviewed_at,hr_review_notes,qualifications_verified,opportunity_verified,records_verified,ceo_decided_by,ceo_decided_at,ceo_decision_notes,created_at)
SELECT e.employee_id,601,@recommender,'[PRESENTATION-SEED:P3-PROMO-004] Considered for GS Manager I after facilities coordination assignments.','Rejected',@hr_reviewer,'2026-08-08 09:00:00','Records verified; leadership exposure remains limited.',1,1,1,@ceo,'2026-08-15 15:00:00','Not approved at this cycle; reassess after expanded team leadership.','2026-08-01 09:00:00'
FROM employees e WHERE e.employee_code='DEMO-GS-REG2' AND NOT EXISTS (SELECT 1 FROM succession_endorsements x WHERE x.recommendation_reason LIKE '[PRESENTATION-SEED:P3-PROMO-004]%');
COMMIT;

SELECT status,COUNT(*) AS rows_created FROM succession_endorsements WHERE recommendation_reason LIKE '[PRESENTATION-SEED:P3-PROMO-%' GROUP BY status;
