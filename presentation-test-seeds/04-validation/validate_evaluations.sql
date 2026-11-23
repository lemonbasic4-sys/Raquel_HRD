SET NAMES utf8mb4;

SELECT 'PRESENTATION_TEMPLATE_COUNTS' check_name,evaluation_type,COUNT(*) template_count,
 MIN(kra_count) minimum_kras,MAX(kra_count) maximum_kras,MIN(behavior_count) minimum_behaviors,
 MIN(kra_weight) minimum_kra_weight,MAX(kra_weight) maximum_kra_weight
FROM (SELECT t.template_id,t.evaluation_type,SUM(c.section='KRA') kra_count,
 SUM(c.section='Behavior') behavior_count,SUM(IF(c.section='KRA',c.weight,0)) kra_weight
 FROM evaluation_templates t JOIN evaluation_criteria c ON c.template_id=t.template_id
 WHERE t.form_code LIKE 'PRES-%' GROUP BY t.template_id,t.evaluation_type) x
GROUP BY evaluation_type ORDER BY FIELD(evaluation_type,'Initial','Final','Annual');

SELECT 'INVALID_PRESENTATION_TEMPLATE' check_name,t.form_code,t.evaluation_type,
 SUM(c.section='KRA') kra_count,SUM(c.section='Behavior') behavior_count,
 SUM(IF(c.section='KRA',c.weight,0)) kra_weight,t.kra_weight,t.behavior_weight,t.created_by
FROM evaluation_templates t JOIN evaluation_criteria c ON c.template_id=t.template_id
WHERE t.form_code LIKE 'PRES-%' GROUP BY t.template_id
HAVING (t.evaluation_type IN('Initial','Final') AND kra_count<12)
 OR (t.evaluation_type='Annual' AND kra_count<15) OR behavior_count<>8 OR kra_weight<>100
 OR t.kra_weight<>80 OR t.behavior_weight<>20 OR t.created_by IS NULL;

SELECT 'PRESENTATION_EVALUATION_COUNTS' check_name,evaluation_type,status,COUNT(*) evaluation_count,
 ROUND(AVG(total_score),2) average_score
FROM evaluations WHERE legacy_reference LIKE 'PRES-%'
GROUP BY evaluation_type,status ORDER BY FIELD(evaluation_type,'Initial','Final','Annual'),status;

SELECT 'REGULAR_PROBATIONARY_HISTORY' check_name,
 COUNT(DISTINCT CASE WHEN i.evaluation_id IS NOT NULL AND f.evaluation_id IS NOT NULL THEN e.employee_id END) complete_histories,
 21 expected_histories
FROM employees e
LEFT JOIN evaluations i ON i.employee_id=e.employee_id AND i.evaluation_type='Initial' AND i.legacy_reference LIKE 'PRES-%'
LEFT JOIN evaluations f ON f.employee_id=e.employee_id AND f.evaluation_type='Final' AND f.legacy_reference LIKE 'PRES-%'
WHERE e.employee_code LIKE 'DEMO-%-REG%';

SELECT 'CURRENT_PROBATIONARY_INITIAL_STATUS' check_name,ev.status,COUNT(*) evaluation_count
FROM evaluations ev JOIN employees e ON e.employee_id=ev.employee_id
WHERE e.employee_code LIKE 'DEMO-%-P__' AND ev.evaluation_type='Initial' AND ev.legacy_reference LIKE 'PRES-%'
GROUP BY ev.status;

SELECT 'ANNUAL_WORKFLOW_COUNTS' check_name,
 (SELECT COUNT(*) FROM evaluations WHERE legacy_reference LIKE 'PRES-ANNUAL-%') annual_evaluations,
 (SELECT COUNT(*) FROM annual_evaluation_cycles c JOIN evaluations ev ON ev.evaluation_id=c.individual_evaluation_id WHERE ev.legacy_reference LIKE 'PRES-ANNUAL-%') annual_cycles,
 (SELECT COUNT(*) FROM annual_evaluation_members m JOIN evaluations ev ON ev.evaluation_id=m.evaluation_id WHERE ev.legacy_reference LIKE 'PRES-ANNUAL-%') annual_members,
 (SELECT COUNT(*) FROM annual_evaluation_versions v JOIN annual_evaluation_members m ON m.member_id=v.member_id JOIN evaluations ev ON ev.evaluation_id=m.evaluation_id WHERE ev.legacy_reference LIKE 'PRES-ANNUAL-%') result_versions,
 63 expected_each;

SELECT 'ANNUAL_360_COMPLETENESS' check_name,COUNT(*) expected_ratings,
 SUM(EXISTS(SELECT 1 FROM annual_peer_submissions ps WHERE ps.cycle_id=rr.cycle_id AND ps.target_employee_id=rr.target_employee_id AND ps.rater_employee_id=rr.rater_employee_id)) submitted_ratings,
 COUNT(DISTINCT rr.cycle_id) cycles_with_raters,63 expected_cycles_with_raters
FROM annual_required_raters rr JOIN annual_evaluation_cycles c ON c.cycle_id=rr.cycle_id
JOIN evaluations ev ON ev.evaluation_id=c.individual_evaluation_id WHERE ev.legacy_reference LIKE 'PRES-ANNUAL-%';

SELECT 'ANNUAL_SELF_RATING_SHAPE' check_name,COUNT(*) invalid_count,0 expected_count
FROM annual_evaluation_members m JOIN evaluations ev ON ev.evaluation_id=m.evaluation_id
WHERE ev.legacy_reference LIKE 'PRES-ANNUAL-%' AND (m.self_values_json IS NULL OR JSON_LENGTH(m.self_values_json)<>8);

SELECT 'ANNUAL_KRA_SCORE_COMPLETENESS' check_name,COUNT(*) invalid_count,0 expected_count
FROM (SELECT ev.evaluation_id,COUNT(*) score_count FROM evaluations ev
 JOIN evaluation_scores s ON s.evaluation_id=ev.evaluation_id
 JOIN evaluation_criteria c ON c.criterion_id=s.criterion_id AND c.section='KRA'
 WHERE ev.legacy_reference LIKE 'PRES-ANNUAL-%' GROUP BY ev.evaluation_id HAVING COUNT(*)<>15) invalid;

SELECT 'ANNUAL_APPROVAL_COMPLETENESS' check_name,COUNT(*) annual_members,
 SUM(m.head_approved_by IS NOT NULL AND m.head_approved_at IS NOT NULL) head_approved,
 SUM(a.approval_id IS NOT NULL) kra_approved,
 SUM(m.current_version=1 AND m.finalized_at IS NOT NULL) finalized,63 expected_each
FROM annual_evaluation_members m JOIN evaluations ev ON ev.evaluation_id=m.evaluation_id
LEFT JOIN annual_kra_approvals a ON a.member_id=m.member_id
WHERE ev.legacy_reference LIKE 'PRES-ANNUAL-%';

SELECT 'ANNUAL_FORMULA_MISMATCH' check_name,COUNT(*) mismatch_count,0 expected_count
FROM (SELECT ev.evaluation_id,ev.kra_subtotal,ev.behavior_average,ev.total_score,
 ROUND(k.kra_score,2) expected_kra,
 ROUND((CAST(JSON_UNQUOTE(JSON_EXTRACT(m.self_values_json,'$[0]')) AS DECIMAL(5,2))+p.peer_sum)/(1+p.peer_count),2) expected_behavior
 FROM evaluations ev JOIN annual_evaluation_members m ON m.evaluation_id=ev.evaluation_id
 JOIN annual_evaluation_cycles cy ON cy.cycle_id=m.cycle_id
 JOIN (SELECT s.evaluation_id,SUM(c.weight/100*COALESCE(s.supervisor_override_score,s.score_value)) kra_score
  FROM evaluation_scores s JOIN evaluation_criteria c ON c.criterion_id=s.criterion_id AND c.section='KRA' GROUP BY s.evaluation_id) k ON k.evaluation_id=ev.evaluation_id
 JOIN (SELECT cycle_id,target_employee_id,COUNT(*) peer_count,
  SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(scores_json,'$[0]')) AS DECIMAL(5,2))) peer_sum
  FROM annual_peer_submissions GROUP BY cycle_id,target_employee_id) p ON p.cycle_id=cy.cycle_id AND p.target_employee_id=ev.employee_id
 WHERE ev.legacy_reference LIKE 'PRES-ANNUAL-%') calc
WHERE ABS(kra_subtotal-expected_kra)>.01 OR ABS(behavior_average-expected_behavior)>.01
 OR ABS(total_score-ROUND(expected_kra*.80+expected_behavior*.20,2))>.01;

SELECT 'ANNUAL_PERFORMANCE_VARIATION' check_name,performance_level,COUNT(*) evaluation_count,
 MIN(total_score) minimum_score,MAX(total_score) maximum_score
FROM evaluations WHERE legacy_reference LIKE 'PRES-ANNUAL-%'
GROUP BY performance_level ORDER BY minimum_score;

SELECT 'ANNUAL_EMPLOYEE_TRENDS' check_name,trend_type,COUNT(*) employee_count
FROM (
 SELECT employee_id,
  CASE
   WHEN MAX(CASE WHEN YEAR(evaluation_period_end)=2025 THEN total_score END)
      > MAX(CASE WHEN YEAR(evaluation_period_end)=2023 THEN total_score END)+0.20 THEN 'Improving'
   WHEN MAX(CASE WHEN YEAR(evaluation_period_end)=2025 THEN total_score END)
      < MAX(CASE WHEN YEAR(evaluation_period_end)=2023 THEN total_score END)-0.20 THEN 'Declining'
   WHEN MAX(total_score)-MIN(total_score)>=0.40 THEN 'Inconsistent'
   ELSE 'Stable'
  END trend_type
 FROM evaluations WHERE legacy_reference LIKE 'PRES-ANNUAL-%' GROUP BY employee_id
) trends GROUP BY trend_type ORDER BY trend_type;

SELECT 'ANNUAL_KRA_BEHAVIOR_CONTRASTS' check_name,
 SUM(kra_subtotal>=3.50 AND behavior_average<=2.80) high_kra_low_behavior,
 SUM(kra_subtotal<=2.90 AND behavior_average>=3.40) low_kra_high_behavior
FROM evaluations WHERE legacy_reference LIKE 'PRES-ANNUAL-%';

SELECT 'PRESENTATION_QUARTERLY_RECORDS' check_name,COUNT(*) actual_count,0 expected_count
FROM evaluations WHERE legacy_reference LIKE 'PRES-%' AND evaluation_type='Quarterly';
SELECT 'PROTECTED_EMPLOYEE_OWN_EVALUATIONS' check_name,COUNT(*) actual_count,0 expected_count
FROM evaluations WHERE employee_id IN(101,301,302) AND legacy_reference LIKE 'PRES-%';

SELECT 'INVALID_EVALUATOR_RELATIONSHIP' check_name,COUNT(*) actual_count,0 expected_count
FROM annual_peer_submissions ps JOIN annual_evaluation_cycles c ON c.cycle_id=ps.cycle_id
JOIN evaluations ev ON ev.evaluation_id=c.individual_evaluation_id
JOIN employees target ON target.employee_id=ps.target_employee_id JOIN employees rater ON rater.employee_id=ps.rater_employee_id
WHERE ev.legacy_reference LIKE 'PRES-ANNUAL-%'
 AND (ps.target_employee_id=ps.rater_employee_id OR target.department_id<>rater.department_id OR rater.employment_status<>'Regular');

SELECT 'INVALID_EVALUATION_TIMELINE' check_name,COUNT(*) actual_count,0 expected_count
FROM evaluations ev JOIN employees e ON e.employee_id=ev.employee_id
WHERE ev.legacy_reference LIKE 'PRES-%' AND (ev.evaluation_period_start>ev.evaluation_period_end
 OR ev.evaluation_period_start<e.hire_date
 OR (ev.status='Approved' AND (ev.approved_date IS NULL OR ev.approved_date<ev.evaluation_period_end)));
