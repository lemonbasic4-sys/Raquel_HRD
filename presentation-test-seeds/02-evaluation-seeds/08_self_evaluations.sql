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
  WHEN MOD(CRC32(e.employee_code),6)=1 THEN LEAST(4.00,2.90+MOD(GREATEST(0,YEAR(ev.evaluation_period_end)-YEAR(e.regularization_effective_date)-1),4)*0.20)
  WHEN MOD(CRC32(e.employee_code),6)=2 THEN 2.70
  WHEN MOD(CRC32(e.employee_code),6)=3 THEN 3.70
  WHEN MOD(CRC32(e.employee_code),6)=4 THEN GREATEST(2.60,3.80-MOD(GREATEST(0,YEAR(ev.evaluation_period_end)-YEAR(e.regularization_effective_date)-1),4)*0.25)
  ELSE CASE MOD(GREATEST(0,YEAR(ev.evaluation_period_end)-YEAR(e.regularization_effective_date)-1),3) WHEN 0 THEN 3.20 WHEN 1 THEN 3.70 ELSE 2.95 END
 END,
 CASE
  WHEN ev.evaluation_type<>'Annual' THEN LEAST(4.00,2.80+MOD(CRC32(CONCAT(e.employee_code,'B')),6)*0.16+IF(ev.evaluation_type='Final',0.15,0))
  WHEN MOD(CRC32(e.employee_code),6)=0 THEN 3.60
  WHEN MOD(CRC32(e.employee_code),6)=1 THEN LEAST(4.00,3.00+MOD(GREATEST(0,YEAR(ev.evaluation_period_end)-YEAR(e.regularization_effective_date)-1),4)*0.18)
  WHEN MOD(CRC32(e.employee_code),6)=2 THEN 3.60
  WHEN MOD(CRC32(e.employee_code),6)=3 THEN 2.50
  WHEN MOD(CRC32(e.employee_code),6)=4 THEN GREATEST(2.60,3.60-MOD(GREATEST(0,YEAR(ev.evaluation_period_end)-YEAR(e.regularization_effective_date)-1),4)*0.20)
  ELSE CASE MOD(GREATEST(0,YEAR(ev.evaluation_period_end)-YEAR(e.regularization_effective_date)-1),3) WHEN 0 THEN 3.45 WHEN 1 THEN 3.05 ELSE 3.70 END
 END
FROM evaluations ev JOIN employees e ON e.employee_id=ev.employee_id
WHERE ev.legacy_reference LIKE 'PRES-%'
 AND (ev.evaluation_type<>'Annual' OR YEAR(ev.evaluation_period_end)<=2025);

INSERT INTO evaluation_scores(evaluation_id,criterion_id,score_value,weighted_score)
SELECT ev.evaluation_id,c.criterion_id,
 IF(c.section='KRA',p.kra_score,p.behavior_score),
 IF(c.section='KRA',ROUND(p.kra_score*c.weight/100,2),p.behavior_score)
FROM evaluations ev JOIN tmp_presentation_score_profiles p ON p.evaluation_id=ev.evaluation_id
JOIN evaluation_criteria c ON c.template_id=ev.template_id
WHERE (ev.evaluation_type<>'Annual' OR YEAR(ev.evaluation_period_end)<=2025)
 AND NOT EXISTS(SELECT 1 FROM evaluation_scores s WHERE s.evaluation_id=ev.evaluation_id AND s.criterion_id=c.criterion_id);

UPDATE annual_evaluation_members m
JOIN tmp_presentation_score_profiles p ON p.evaluation_id=m.evaluation_id
SET m.self_values_json=JSON_ARRAY(p.behavior_score,p.behavior_score,p.behavior_score,p.behavior_score,
 p.behavior_score,p.behavior_score,p.behavior_score,p.behavior_score)
WHERE m.self_values_json IS NULL;

DROP TEMPORARY TABLE tmp_presentation_score_profiles;
COMMIT;

SELECT COUNT(*) AS presentation_score_rows FROM evaluation_scores s JOIN evaluations ev ON ev.evaluation_id=s.evaluation_id WHERE ev.legacy_reference LIKE 'PRES-%';
