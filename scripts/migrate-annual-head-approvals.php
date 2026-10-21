<?php
/** One-time upgrade of signed head reviews saved before immediate approval. */
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
session_set_save_handler(static fn()=>true,static fn()=>true,static fn()=>'',static fn()=>true,static fn()=>true,static fn()=>0);
session_start();
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/functions.php';
ensureAnnualEvaluationSchema($conn);
ensureAuditTrailSchema($conn);
$apply=in_array('--apply',$argv,true);
$rows=annualQuery($conn,"SELECT DISTINCT m.evaluation_id,a.approved_by
    FROM annual_evaluation_members m
    JOIN annual_kra_approvals a ON a.member_id=m.member_id
    JOIN annual_peer_submissions s ON s.cycle_id=m.cycle_id AND s.target_employee_id=m.employee_id AND s.submitted_by=a.approved_by
    WHERE m.current_version=0 AND s.rater_signature_data IS NOT NULL AND s.rater_signature_data<>''
    ORDER BY m.evaluation_id");
$count=0;
foreach ($rows as $row) {
    $conn->begin_transaction();
    try {
        $member=annualMember($conn,(int)$row['evaluation_id']);
        if (!$member || $member['current_version']) { $conn->rollback(); continue; }
        annualQuery($conn,'SELECT cycle_id FROM annual_evaluation_cycles WHERE cycle_id=? FOR UPDATE',[(int)$member['cycle_id']]);
        $member=annualMember($conn,(int)$row['evaluation_id']);
        $actor=annualActor($conn,(int)$row['approved_by']);
        if (!$actor || !annualCanFinalize($conn,$actor,$member) || $member['current_version']) { $conn->rollback(); continue; }
        annualAssertSnapshotActive($member);
        if ($apply) annualRefreshApprovedResult($conn,$member,(int)$row['approved_by'],true);
        $apply ? $conn->commit() : $conn->rollback();
        $count++;
        echo ($apply ? 'Approved existing signed review: ' : 'Eligible signed review: ').(int)$row['evaluation_id'].PHP_EOL;
    } catch (Throwable $error) { $conn->rollback(); throw $error; }
}
echo $count.($apply ? ' existing signed review(s) upgraded.' : ' review(s) eligible. Use --apply to upgrade.').PHP_EOL;
