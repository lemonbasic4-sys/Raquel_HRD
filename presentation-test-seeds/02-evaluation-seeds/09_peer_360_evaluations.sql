SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;
START TRANSACTION;

-- Snapshot every other active Regular employee in the same department who has an active rating account.
INSERT INTO annual_required_raters(cycle_id,target_employee_id,rater_employee_id)
SELECT c.cycle_id,target.employee_id,rater.employee_id
FROM annual_evaluation_cycles c
JOIN evaluations ev ON ev.evaluation_id=c.individual_evaluation_id AND ev.legacy_reference LIKE 'PRES-ANNUAL-%'
JOIN employees target ON target.employee_id=ev.employee_id
JOIN employees rater ON rater.department_id=target.department_id AND rater.employee_id<>target.employee_id
 AND rater.employment_status='Regular' AND rater.is_active=1 AND rater.deleted_at IS NULL
WHERE EXISTS(SELECT 1 FROM users u WHERE u.employee_id=rater.employee_id AND u.is_active=1 AND u.deleted_at IS NULL AND COALESCE(u.account_hold,0)=0)
 AND NOT EXISTS(SELECT 1 FROM annual_required_raters x WHERE x.cycle_id=c.cycle_id AND x.target_employee_id=target.employee_id AND x.rater_employee_id=rater.employee_id);

INSERT INTO annual_peer_submissions
 (cycle_id,rater_employee_id,target_employee_id,submitted_by,scores_json,rater_signature_data,rater_signed_at,is_late,submitted_at)
SELECT rr.cycle_id,rr.rater_employee_id,rr.target_employee_id,
 (SELECT u.user_id FROM users u WHERE u.employee_id=rr.rater_employee_id AND u.is_active=1 AND u.deleted_at IS NULL
  ORDER BY (u.role='Employee') DESC,u.user_id LIMIT 1),
 JSON_ARRAY(p.peer_score,p.peer_score,p.peer_score,p.peer_score,p.peer_score,p.peer_score,p.peer_score,p.peer_score),
 CONCAT('presentation-signature:',rater.employee_code),DATE_SUB(c.period_end,INTERVAL 4 DAY),0,DATE_SUB(c.period_end,INTERVAL 4 DAY)
FROM annual_required_raters rr
JOIN annual_evaluation_cycles c ON c.cycle_id=rr.cycle_id
JOIN annual_evaluation_members m ON m.cycle_id=rr.cycle_id AND m.employee_id=rr.target_employee_id
JOIN employees rater ON rater.employee_id=rr.rater_employee_id
JOIN (
 SELECT m2.member_id,LEAST(4.00,GREATEST(1.00,
  CAST(JSON_UNQUOTE(JSON_EXTRACT(m2.self_values_json,'$[0]')) AS DECIMAL(5,2))
  +IF(MOD(m2.member_id,2)=0,0.10,-0.10))) peer_score
 FROM annual_evaluation_members m2
) p ON p.member_id=m.member_id
WHERE NOT EXISTS(SELECT 1 FROM annual_peer_submissions x WHERE x.cycle_id=rr.cycle_id AND x.rater_employee_id=rr.rater_employee_id AND x.target_employee_id=rr.target_employee_id);

COMMIT;

SELECT COUNT(*) AS annual_required_ratings FROM annual_required_raters rr JOIN annual_evaluation_cycles c ON c.cycle_id=rr.cycle_id JOIN evaluations ev ON ev.evaluation_id=c.individual_evaluation_id WHERE ev.legacy_reference LIKE 'PRES-ANNUAL-%';
SELECT COUNT(*) AS annual_submitted_peer_ratings FROM annual_peer_submissions ps JOIN annual_evaluation_cycles c ON c.cycle_id=ps.cycle_id JOIN evaluations ev ON ev.evaluation_id=c.individual_evaluation_id WHERE ev.legacy_reference LIKE 'PRES-ANNUAL-%';
