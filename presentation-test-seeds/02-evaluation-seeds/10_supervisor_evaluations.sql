SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;
START TRANSACTION;

-- Immediate-head KRA review. Adjust alternating records slightly to preserve realistic review evidence.
UPDATE evaluation_scores s
JOIN evaluations ev ON ev.evaluation_id=s.evaluation_id AND ev.legacy_reference LIKE 'PRES-%'
JOIN evaluation_criteria c ON c.criterion_id=s.criterion_id AND c.section='KRA'
JOIN employees e ON e.employee_id=ev.employee_id
SET s.supervisor_override_score=LEAST(4.00,GREATEST(1.00,s.score_value+IF(MOD(ev.evaluation_id,2)=0,0.10,-0.05))),
 s.supervisor_override_by=(SELECT u.user_id FROM users u WHERE u.employee_id=e.reports_to AND u.is_active=1 AND u.deleted_at IS NULL ORDER BY (u.role='Employee') DESC,u.user_id LIMIT 1),
 s.supervisor_override_at=DATE_SUB(ev.evaluation_period_end,INTERVAL 2 DAY)
WHERE s.supervisor_override_score IS NULL;

INSERT INTO annual_kra_approvals(member_id,approved_by,approved_at,remarks,ratings_json,rater_signature_data,rater_signed_at)
SELECT m.member_id,
 (SELECT u.user_id FROM users u JOIN employees target ON target.employee_id=m.employee_id
  WHERE u.employee_id=target.reports_to AND u.is_active=1 AND u.deleted_at IS NULL ORDER BY (u.role='Employee') DESC,u.user_id LIMIT 1),
 DATE_SUB(c.period_end,INTERVAL 2 DAY),'Immediate head reviewed all Annual KRA ratings and supporting evidence.',
 JSON_OBJECT('source','Presentation Seed','reviewed_items',15),
 CONCAT('presentation-head-signature:',manager.employee_code),DATE_SUB(c.period_end,INTERVAL 2 DAY)
FROM annual_evaluation_members m JOIN annual_evaluation_cycles c ON c.cycle_id=m.cycle_id
JOIN employees target ON target.employee_id=m.employee_id JOIN employees manager ON manager.employee_id=target.reports_to
JOIN evaluations ev ON ev.evaluation_id=m.evaluation_id AND ev.legacy_reference LIKE 'PRES-ANNUAL-%'
WHERE NOT EXISTS(SELECT 1 FROM annual_kra_approvals x WHERE x.member_id=m.member_id);

INSERT INTO evaluation_dev_plans(evaluation_id,improvement_area,support_needed,time_frame,sort_order)
SELECT ev.evaluation_id,
 CASE MOD(CRC32(e.employee_code),3) WHEN 0 THEN 'Strengthen evidence-based planning and prioritization.' WHEN 1 THEN 'Improve cross-team communication and follow-through.' ELSE 'Deepen technical knowledge and quality checks.' END,
 CASE MOD(CRC32(e.employee_code),3) WHEN 0 THEN 'Monthly coaching and planning review with the immediate head.' WHEN 1 THEN 'Structured stakeholder feedback and communication practice.' ELSE 'Targeted refresher training and peer review.' END,
 'Next 90 days',1
FROM evaluations ev JOIN employees e ON e.employee_id=ev.employee_id
WHERE ev.legacy_reference LIKE 'PRES-%'
 AND NOT EXISTS(SELECT 1 FROM evaluation_dev_plans x WHERE x.evaluation_id=ev.evaluation_id);

COMMIT;

SELECT COUNT(*) AS annual_head_kra_approvals FROM annual_kra_approvals a JOIN annual_evaluation_members m ON m.member_id=a.member_id JOIN evaluations ev ON ev.evaluation_id=m.evaluation_id WHERE ev.legacy_reference LIKE 'PRES-ANNUAL-%';
