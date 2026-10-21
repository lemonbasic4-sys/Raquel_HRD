<?php
/** Correct old flags that treated any submission after one approval as late. */
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
session_set_save_handler(static fn()=>true,static fn()=>true,static fn()=>'',static fn()=>true,static fn()=>true,static fn()=>0);
session_start();
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/functions.php';
ensureAnnualEvaluationSchema($conn);
$conn->begin_transaction();
try {
    $conn->query('UPDATE annual_peer_submissions s
        JOIN annual_evaluation_members target ON target.cycle_id=s.cycle_id AND target.employee_id=s.target_employee_id
        LEFT JOIN annual_evaluation_members rater ON rater.cycle_id=s.cycle_id AND rater.employee_id=s.rater_employee_id
        SET s.is_late=IF(target.is_late_participant=1 OR COALESCE(rater.is_late_participant,0)=1,1,0)
        WHERE s.is_late<>IF(target.is_late_participant=1 OR COALESCE(rater.is_late_participant,0)=1,1,0)');
    $flags=$conn->affected_rows;
    $notices=0;
    foreach (annualQuery($conn,"SELECT notification_id,link FROM notifications WHERE title='Late participant: annual peer rating available'") as $row) {
        parse_str(parse_url($row['link'],PHP_URL_QUERY) ?: '',$query);
        $id=(int)($query['evaluation_id'] ?? 0);
        $member=$id ? annualMember($conn,$id) : null;
        if (!$member || $member['is_late_participant']) continue;
        annualQuery($conn,'UPDATE notifications SET title=?,message=? WHERE notification_id=?',
            ['Department 360 ratings available','A department coworker has submitted their Annual self-rating. Review your department peer-rating tasks and submit your Core Behaviors & Values ratings.',(int)$row['notification_id']]);
        $notices++;
    }
    $conn->commit();
    echo $flags.' incorrect late flag(s) corrected; '.$notices." notification(s) relabeled. No ratings or notifications deleted.\n";
} catch (Throwable $error) { $conn->rollback(); throw $error; }
