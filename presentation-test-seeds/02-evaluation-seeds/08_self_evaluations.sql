SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;
START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS tmp_presentation_score_profiles;
CREATE TEMPORARY TABLE tmp_presentation_score_profiles(
 evaluation_id INT PRIMARY KEY,kra_score DECIMAL(5,2),behavior_score DECIMAL(5,2)
);

INSERT INTO tmp_presentation_score_profiles
SELECT ev.evaluation_id,
 CASE
  WHEN ev.evaluation_type<>'Annual' THEN LEAST(4.00,2.70+MOD(CRC32(e.employee_code),6)*0.18+IF(ev.evaluation_type='Final',0.20,0))
  WHEN MOD(CRC32(e.employee_code),6)=0 THEN 3.80
  WHEN MOD(CRC32(e.employee_code),6)=1 THEN 3.00+(YEAR(ev.evaluation_period_end)-2023)*0.25
  WHEN MOD(CRC32(e.employee_code),6)=2 THEN 2.70
  WHEN MOD(CRC32(e.employee_code),6)=3 THEN 3.70
  WHEN MOD(CRC32(e.employee_code),6)=4 THEN 3.80-(YEAR(ev.evaluation_period_end)-2023)*0.40
  ELSE CASE YEAR(ev.evaluation_period_end) WHEN 2023 THEN 3.20 WHEN 2024 THEN 3.80 ELSE 2.90 END
 END,
 CASE
  WHEN ev.evaluation_type<>'Annual' THEN LEAST(4.00,2.80+MOD(CRC32(CONCAT(e.employee_code,'B')),6)*0.16+IF(ev.evaluation_type='Final',0.15,0))
  WHEN MOD(CRC32(e.employee_code),6)=0 THEN 3.60
  WHEN MOD(CRC32(e.employee_code),6)=1 THEN 3.10+(YEAR(ev.evaluation_period_end)-2023)*0.20
  WHEN MOD(CRC32(e.employee_code),6)=2 THEN 3.60
  WHEN MOD(CRC32(e.employee_code),6)=3 THEN 2.50
  WHEN MOD(CRC32(e.employee_code),6)=4 THEN 3.60-(YEAR(ev.evaluation_period_end)-2023)*0.30
  ELSE CASE YEAR(ev.evaluation_period_end) WHEN 2023 THEN 3.50 WHEN 2024 THEN 3.00 ELSE 3.70 END
 END
FROM evaluations ev JOIN employees e ON e.employee_id=ev.employee_id
WHERE ev.legacy_reference LIKE 'PRES-%';

INSERT INTO evaluation_scores(evaluation_id,criterion_id,score_value,weighted_score)
SELECT ev.evaluation_id,c.criterion_id,
 IF(c.section='KRA',p.kra_score,p.behavior_score),
 IF(c.section='KRA',ROUND(p.kra_score*c.weight/100,2),p.behavior_score)
FROM evaluations ev JOIN tmp_presentation_score_profiles p ON p.evaluation_id=ev.evaluation_id
JOIN evaluation_criteria c ON c.template_id=ev.template_id
WHERE NOT EXISTS(SELECT 1 FROM evaluation_scores s WHERE s.evaluation_id=ev.evaluation_id AND s.criterion_id=c.criterion_id);

UPDATE annual_evaluation_members m
JOIN tmp_presentation_score_profiles p ON p.evaluation_id=m.evaluation_id
SET m.self_values_json=JSON_ARRAY(p.behavior_score,p.behavior_score,p.behavior_score,p.behavior_score,
 p.behavior_score,p.behavior_score,p.behavior_score,p.behavior_score)
WHERE m.self_values_json IS NULL;

DROP TEMPORARY TABLE tmp_presentation_score_profiles;
COMMIT;

SELECT COUNT(*) AS presentation_score_rows FROM evaluation_scores s JOIN evaluations ev ON ev.evaluation_id=s.evaluation_id WHERE ev.legacy_reference LIKE 'PRES-%';
