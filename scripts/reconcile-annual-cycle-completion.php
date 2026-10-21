<?php
/** Reconcile existing Annual cycles after enabling completion/history tracking. */
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
session_set_save_handler(static fn()=>true,static fn()=>true,static fn()=>'',static fn()=>true,static fn()=>true,static fn()=>0);
session_start();
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/functions.php';
ensureAnnualEvaluationSchema($conn);
$completed=0;
foreach (annualQuery($conn,'SELECT cycle_id FROM annual_evaluation_cycles ORDER BY cycle_id') as $row) {
    $conn->begin_transaction();
    try {
        $cycleId=(int)$row['cycle_id'];
        annualQuery($conn,'SELECT cycle_id FROM annual_evaluation_cycles WHERE cycle_id=? FOR UPDATE',[$cycleId]);
        if (annualUpdateCycleCompletion($conn,$cycleId)) $completed++;
        $conn->commit();
    } catch (Throwable $error) { $conn->rollback(); throw $error; }
}
echo $completed." completed Annual cycle(s) available in history.\n";
