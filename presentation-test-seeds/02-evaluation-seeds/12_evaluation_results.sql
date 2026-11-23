SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;
START TRANSACTION;

-- Finalize approved Initial/Final evaluations; current P02 Initial records stay pending.
DROP TEMPORARY TABLE IF EXISTS tmp_probationary_results;
CREATE TEMPORARY TABLE tmp_probationary_results AS
SELECT ev.evaluation_id,
 ROUND(SUM(IF(c.section='KRA',c.weight/100*COALESCE(s.supervisor_override_score,s.score_value),0)),2) kra_score,
 ROUND(AVG(IF(c.section='Behavior',COALESCE(s.supervisor_override_score,s.score_value),NULL)),2) behavior_score
FROM evaluations ev JOIN employees e ON e.employee_id=ev.employee_id
JOIN evaluation_scores s ON s.evaluation_id=ev.evaluation_id JOIN evaluation_criteria c ON c.criterion_id=s.criterion_id
WHERE ev.legacy_reference LIKE 'PRES-%' AND ev.evaluation_type IN('Initial','Final')
 AND e.employee_code NOT LIKE 'DEMO-%-P02'
GROUP BY ev.evaluation_id;

UPDATE evaluations ev JOIN tmp_probationary_results r ON r.evaluation_id=ev.evaluation_id
JOIN employees e ON e.employee_id=ev.employee_id
SET ev.kra_subtotal=r.kra_score,ev.behavior_average=r.behavior_score,
 ev.total_score=ROUND(r.kra_score*.80+r.behavior_score*.20,2),
 ev.performance_level=CASE WHEN ROUND(r.kra_score*.80+r.behavior_score*.20,2)>=3.60 THEN 'Outstanding' WHEN ROUND(r.kra_score*.80+r.behavior_score*.20,2)>=2.60 THEN 'Exceeds Expectations' WHEN ROUND(r.kra_score*.80+r.behavior_score*.20,2)>=2.00 THEN 'Meets Expectations' ELSE 'Needs Improvement' END,
 ev.status='Approved',ev.approved_by=(SELECT u.user_id FROM users u WHERE u.employee_id=e.reports_to AND u.is_active=1 AND u.deleted_at IS NULL ORDER BY (u.role='Employee') DESC,u.user_id LIMIT 1),
 ev.approved_date=ev.evaluation_period_end,ev.supervisor_comments='Immediate head validated the KRA evidence and probationary development progress.';

-- Compute Annual results from effective KRA scores plus self and every submitted required rater.
DROP TEMPORARY TABLE IF EXISTS tmp_annual_results;
CREATE TEMPORARY TABLE tmp_annual_results AS
SELECT ev.evaluation_id,m.member_id,c.cycle_id,
 ROUND(k.kra_score,2) kra_score,
 ROUND((CAST(JSON_UNQUOTE(JSON_EXTRACT(m.self_values_json,'$[0]')) AS DECIMAL(5,2))+COALESCE(p.peer_sum,0))/(1+COALESCE(p.peer_count,0)),2) behavior_score,
 COALESCE(p.peer_count,0) peer_count
FROM evaluations ev JOIN annual_evaluation_members m ON m.evaluation_id=ev.evaluation_id
JOIN annual_evaluation_cycles c ON c.cycle_id=m.cycle_id
JOIN (
 SELECT s.evaluation_id,SUM(ec.weight/100*COALESCE(s.supervisor_override_score,s.score_value)) kra_score
 FROM evaluation_scores s JOIN evaluation_criteria ec ON ec.criterion_id=s.criterion_id AND ec.section='KRA'
 GROUP BY s.evaluation_id
) k ON k.evaluation_id=ev.evaluation_id
LEFT JOIN (
 SELECT ps.cycle_id,ps.target_employee_id,COUNT(*) peer_count,
  SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(ps.scores_json,'$[0]')) AS DECIMAL(5,2))) peer_sum
 FROM annual_peer_submissions ps GROUP BY ps.cycle_id,ps.target_employee_id
) p ON p.cycle_id=c.cycle_id AND p.target_employee_id=ev.employee_id
WHERE ev.legacy_reference LIKE 'PRES-ANNUAL-%';

UPDATE evaluations ev JOIN tmp_annual_results r ON r.evaluation_id=ev.evaluation_id
JOIN employees e ON e.employee_id=ev.employee_id
SET ev.kra_subtotal=r.kra_score,ev.behavior_average=r.behavior_score,
 ev.total_score=ROUND(r.kra_score*.80+r.behavior_score*.20,2),
 ev.performance_level=CASE WHEN ROUND(r.kra_score*.80+r.behavior_score*.20,2)>=3.60 THEN 'Outstanding' WHEN ROUND(r.kra_score*.80+r.behavior_score*.20,2)>=2.60 THEN 'Exceeds Expectations' WHEN ROUND(r.kra_score*.80+r.behavior_score*.20,2)>=2.00 THEN 'Meets Expectations' ELSE 'Needs Improvement' END,
 ev.status='Approved',ev.approved_by=(SELECT u.user_id FROM users u WHERE u.employee_id=e.reports_to AND u.is_active=1 AND u.deleted_at IS NULL ORDER BY (u.role='Employee') DESC,u.user_id LIMIT 1),
 ev.approved_date=DATE_ADD(ev.evaluation_period_end,INTERVAL 5 DAY),
 ev.supervisor_comments='Immediate head finalized the Annual KRA review after all assigned 360 ratings were submitted.';

UPDATE annual_evaluation_members m JOIN tmp_annual_results r ON r.member_id=m.member_id
JOIN evaluations ev ON ev.evaluation_id=m.evaluation_id
SET m.current_version=1,m.revision_pending=0,m.finalized_at=DATE_ADD(ev.evaluation_period_end,INTERVAL 5 DAY);

UPDATE annual_evaluation_cycles c
JOIN tmp_annual_results r ON r.cycle_id=c.cycle_id
JOIN evaluations ev ON ev.evaluation_id=r.evaluation_id
SET c.first_finalized_at=DATE_ADD(ev.evaluation_period_end,INTERVAL 5 DAY),
 c.ready_for_approval_at=DATE_ADD(ev.evaluation_period_end,INTERVAL 5 DAY),
 c.completed_at=DATE_ADD(ev.evaluation_period_end,INTERVAL 5 DAY),
 c.first_completed_at=DATE_ADD(ev.evaluation_period_end,INTERVAL 5 DAY),
 c.cycle_approved_by=ev.approved_by;

INSERT INTO annual_evaluation_versions(member_id,version_number,finalized_by,finalized_at,remarks,snapshot_json)
SELECT m.member_id,1,ev.approved_by,ev.approved_date,'Historical presentation result finalized after complete KRA and 360 review.',
 JSON_OBJECT('kra',ev.kra_subtotal,'core',ev.behavior_average,'total',ev.total_score,
  'peer_count',r.peer_count,'expected_peers',(SELECT COUNT(*) FROM annual_required_raters rr WHERE rr.cycle_id=r.cycle_id AND rr.target_employee_id=ev.employee_id),
  'source','Presentation Seed')
FROM tmp_annual_results r JOIN annual_evaluation_members m ON m.member_id=r.member_id JOIN evaluations ev ON ev.evaluation_id=r.evaluation_id
WHERE NOT EXISTS(SELECT 1 FROM annual_evaluation_versions x WHERE x.member_id=m.member_id AND x.version_number=1);

DROP TEMPORARY TABLE tmp_annual_results;
DROP TEMPORARY TABLE tmp_probationary_results;
COMMIT;

SELECT evaluation_type,status,COUNT(*) AS evaluation_count,ROUND(AVG(total_score),2) AS average_score
FROM evaluations WHERE legacy_reference LIKE 'PRES-%' GROUP BY evaluation_type,status ORDER BY evaluation_type,status;
