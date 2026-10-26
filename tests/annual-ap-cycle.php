<?php
/** Isolated AP cycle integration check. Run: C:\xampp\php\php.exe tests/annual-ap-cycle.php */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
session_set_save_handler(static fn()=>true,static fn()=>true,static fn()=>'',static fn()=>true,static fn()=>true,static fn()=>0);
session_start();
require_once __DIR__.'/../config/database.php';
$conn->close();
require_once __DIR__.'/../includes/functions.php';
date_default_timezone_set('Asia/Manila');
$_SERVER['REMOTE_ADDR']='127.0.0.1';
$_SERVER['HTTP_USER_AGENT']='AP annual test';
$admin=new mysqli('127.0.0.1','root','');
$source=$argv[1] ?? 'raquel_hris';
if (!preg_match('/^[a-zA-Z0-9_]+$/',$source)) throw new RuntimeException('Invalid source schema.');
$testDb='raquel_ap_cycle_test_'.bin2hex(random_bytes(5));
$created=false;
function apCheck(bool $condition,string $description): void {
    if (!$condition) throw new RuntimeException('FAIL: '.$description);
    echo 'PASS: '.$description.PHP_EOL;
}
function apReject(callable $operation,string $description): void {
    try { $operation(); } catch (DomainException $error) { apCheck(true,$description); return; }
    throw new RuntimeException('FAIL: '.$description);
}
try {
    $admin->query("CREATE DATABASE `$testDb` CHARACTER SET utf8mb4"); $created=true;
    foreach (array_column($admin->query("SHOW TABLES FROM `$source`")->fetch_all(MYSQLI_NUM),0) as $table) {
        $admin->query("CREATE TABLE `$testDb`.`$table` LIKE `$source`.`$table`");
    }
    $conn=new mysqli('127.0.0.1','root','',$testDb); $conn->set_charset('utf8mb4');
    ensureAnnualEvaluationSchema($conn); ensureAuditTrailSchema($conn);
    annualQuery($conn,"INSERT INTO departments (department_id,department_name) VALUES (1,'Acquired Properties'),(2,'Office of the President'),(3,'Human Resources')");
    foreach ([[100,'VP for Acquired Properties',1,1100],[101,'AP Manager I',1,100],[105,'AP Supervisor I',1,101],[108,'AP Staff I',1,105],[1100,'President and CEO',2,null]] as $job) {
        annualQuery($conn,'INSERT INTO job_titles (job_title_id,job_title,department_id,reports_to) VALUES (?,?,?,?)',$job);
    }
    annualQuery($conn,'INSERT INTO annual_cycle_department_owners (department_id,owner_position_id) VALUES (1,101)');
    foreach ([[1,1100,2,'President and CEO','Regular'],[2,100,1,'VP for Acquired Properties','Regular'],
              [3,101,1,'AP Manager I','Regular'],[4,105,1,'AP Supervisor I','Regular'],
              [5,108,1,'AP Staff I','Regular'],[6,108,1,'AP Staff I','Probationary'],
              [7,108,1,'AP Staff I','Probationary']] as [$id,$position,$department,$title,$status]) {
        annualQuery($conn,'INSERT INTO employees (employee_id,employee_code,first_name,last_name,hire_date,job_title_id,job_title,department_id,employment_status) VALUES (?,?,?,?,?,?,?,?,?)',
            [$id,'APTEST-'.$id,'Test'.$id,'Person','2020-01-01',$position,$title,$department,$status]);
        annualQuery($conn,'INSERT INTO users (user_id,employee_id,username,email,password_hash,full_name,role) VALUES (?,?,?,?,?,?,?)',
            [$id,$id,'aptest'.$id,'aptest'.$id.'@example.invalid','unused','Test '.$id,$id===1 ? 'President and CEO' : 'Employee']);
    }
    foreach ([8=>'HR Manager',9=>'HR Supervisor',10=>'HR Staff'] as $id=>$role) {
        annualQuery($conn,'INSERT INTO employees (employee_id,employee_code,first_name,last_name,hire_date,job_title,department_id,employment_status) VALUES (?,?,?,?,?,?,?,?)',
            [$id,'HRTEST-'.$id,'HR'.$id,'Person','2020-01-01',$role,3,'Regular']);
        annualQuery($conn,'INSERT INTO users (user_id,employee_id,username,email,password_hash,full_name,role) VALUES (?,?,?,?,?,?,?)',
            [$id,$id,'hrtest'.$id,'hrtest'.$id.'@example.invalid','unused','HR '.$id,$role]);
    }
    annualQuery($conn,'INSERT INTO users (user_id,employee_id,username,email,password_hash,full_name,role) VALUES (?,?,?,?,?,?,?)',
        [11,1,'ceoportal','ceoportal@example.invalid','unused','CEO Employee Portal','Employee']);
    foreach ([[100,1],[101,2],[105,3],[108,4]] as [$position,$creator]) {
        annualQuery($conn,"INSERT INTO evaluation_templates (template_id,template_name,evaluation_type,status,target_department_id,kra_weight,behavior_weight,created_by) VALUES (?,?,'Annual','Active',1,80,20,?)",
            [$position,'AP Annual '.$position,$creator]);
        annualQuery($conn,'INSERT INTO evaluation_template_positions (template_id,job_title_id) VALUES (?,?)',[$position,$position]);
        foreach ([70,30] as $index=>$weight) annualQuery($conn,"INSERT INTO evaluation_criteria (template_id,section,criterion_name,weight,sort_order) VALUES (?,'KRA',?,?,?)",[$position,'KRA '.($index+1),$weight,$index+1]);
        foreach (annualCoreValues() as $index=>$value) annualQuery($conn,"INSERT INTO evaluation_criteria (template_id,section,criterion_name,kpi_description,weight,sort_order) VALUES (?,'Behavior',?,?,0,?)",[$position,$value['name'],$value['kpi'],$index+1]);
    }
    $owner=annualActor($conn,3);
    apCheck(array_column(annualCycleRosterPreview($conn,1),'job_title')===
        ['VP for Acquired Properties','AP Manager I','AP Supervisor I','AP Staff I'],
        'AP readiness lists VP, Manager, Supervisor, then Staff');
    $sampleRoster=annualCycleRosterPreview($conn,1);
    $anotherStaff=$sampleRoster[3];
    $anotherStaff['employee_id']=99;
    $anotherStaff['issues']=['Employee Portal account missing'];
    $grouped=annualCyclePositionReadinessRows([...$sampleRoster,$anotherStaff]);
    apCheck(count($grouped)===4 && $grouped[3]['employee_count']===2
        && $grouped[3]['blocked_count']===1
        && in_array('Employee Portal account missing',$grouped[3]['issues'],true),
        'Multiple employees share one position row while individual blockers remain visible');
    apCheck(annualCanOwnCycle($conn,$owner,1) && !annualCanOwnCycle($conn,annualActor($conn,2),1),'Only AP Manager owns the AP cycle');
    apCheck(annualApCycleReadyToStart($conn,$owner) && annualPendingCount($conn,$owner)===1,
        'Ready AP cycle adds one AP Manager sidebar task');
    annualSyncApCycleReadinessNotification($conn,$owner);
    annualSyncApCycleReadinessNotification($conn,$owner);
    apCheck((int)annualQuery($conn,"SELECT COUNT(*) AS n FROM notifications WHERE user_id=3 AND title LIKE 'AP Annual cycle ready to start (%)' AND needs_action=1")[0]['n']===1,
        'Ready AP cycle sends one actionable notification');
    annualQuery($conn,"UPDATE notifications SET needs_action=0 WHERE user_id=3 AND title LIKE 'AP Annual cycle ready to start (%)'");
    annualSyncApCycleReadinessNotification($conn,$owner);
    apCheck((int)annualQuery($conn,"SELECT COUNT(*) AS n FROM notifications WHERE user_id=3 AND title LIKE 'AP Annual cycle ready to start (%)'")[0]['n']===1
        && annualPendingCount($conn,$owner)===1,'Dismissing the alert does not duplicate it or hide the sidebar task');
    $supervisor=annualActor($conn,4);
    $vp=annualActor($conn,2);
    apCheck(annualCanViewCycleSetup($conn,$supervisor,1) && !annualCanOwnCycle($conn,$supervisor,1)
        && annualCanViewCycleSetup($conn,$vp,1) && !annualCanOwnCycle($conn,$vp,1)
        && !annualCanViewCycleSetup($conn,annualActor($conn,5),1),'AP VP and Supervisor see setup read only; AP Staff does not');
    apReject(fn()=>annualStartCycle($conn,$supervisor,1,'2026-01-01','2026-12-31','2026-12-31'),'AP Supervisor cannot start a cycle through the server');
    apReject(fn()=>annualStartCycle($conn,$vp,1,'2026-01-01','2026-12-31','2026-12-31'),'AP VP cannot start a cycle through the server');
    $actor=$supervisor;
    ob_start(); require __DIR__.'/../includes/annual-cycle-start-panel.php'; $supervisorPanel=ob_get_clean();
    apCheck(str_contains($supervisorPanel,'Read only') && str_contains($supervisorPanel,'AP Manager has not started')
        && str_contains($supervisorPanel,'Please wait for the AP Manager to start the evaluation cycle')
        && !str_contains($supervisorPanel,'name="action" value="start_annual_cycle"'),'Supervisor setup panel has no Start action');
    $actor=$vp;
    ob_start(); require __DIR__.'/../includes/annual-cycle-start-panel.php'; $vpPanel=ob_get_clean();
    apCheck(str_contains($vpPanel,'Read only') && str_contains($vpPanel,'Please wait for the AP Manager to start the evaluation cycle')
        && !str_contains($vpPanel,'name="action" value="start_annual_cycle"'),'AP VP setup panel has no Start action');
    $actor=$owner;
    ob_start(); require __DIR__.'/../includes/annual-cycle-start-panel.php'; $ownerPanel=ob_get_clean();
    apCheck(str_contains($ownerPanel,'annual-cycle-start-breathe') && str_contains($ownerPanel,'Start Acquired Properties cycle')
        && str_contains($ownerPanel,'data-bs-target="#annualCycleStartConfirmModal"')
        && str_contains($ownerPanel,'Start the department Annual cycle and notify the employees shown above?')
        && !str_contains($ownerPanel,'onsubmit="return confirm('),
        'Ready AP Manager Start button opens the confirmation modal');
    $president=annualActor($conn,1);
    apCheck(annualPresidentApPreviewDepartmentId($conn,$president)===1
        && annualPresidentApPreviewDepartmentId($conn,annualActor($conn,11))===1
        && !annualCanOwnCycle($conn,$president,1),
        'President work and Employee Portal accounts can preview AP readiness without owning the cycle');
    $actor=annualActor($conn,11);
    $cycleSetupDepartmentId=1;
    ob_start(); require __DIR__.'/../includes/annual-cycle-start-panel.php'; $presidentPanel=ob_get_clean();
    apCheck(str_contains($presidentPanel,'Acquired Properties Annual cycle')
        && str_contains($presidentPanel,'Read only')
        && str_contains($presidentPanel,'All Annual forms are ready')
        && !str_contains($presidentPanel,'name="action" value="start_annual_cycle"'),
        'President Employee Portal shows the AP readiness panel but no Start action');
    unset($cycleSetupDepartmentId);
    $actor=$supervisor;
    $cycleId=annualStartCycle($conn,$owner,1,'2026-01-01','2026-12-31','2026-12-31');
    apCheck(!annualApCycleReadyToStart($conn,$owner)
        && (int)annualQuery($conn,"SELECT COUNT(*) AS n FROM notifications WHERE user_id=3 AND title LIKE 'AP Annual cycle ready to start (%)' AND needs_action=1")[0]['n']===0,
        'Starting the AP cycle clears the readiness task and notification');
    ob_start(); require __DIR__.'/../includes/annual-cycle-start-panel.php'; $openSupervisorPanel=ob_get_clean();
    apCheck(str_contains($openSupervisorPanel,'Cycle open') && str_contains($openSupervisorPanel,'Read only')
        && str_contains($openSupervisorPanel,'class="collapse pt-3" id="annualCycleReadOnlyDetails"')
        && str_contains($openSupervisorPanel,'aria-expanded="false" aria-controls="annualCycleReadOnlyDetails"')
        && !str_contains($openSupervisorPanel,'name="action" value="start_annual_cycle"'),'Supervisor sees open AP cycle without owner controls');
    apCheck((int)annualQuery($conn,'SELECT COUNT(*) AS n FROM annual_evaluation_members WHERE cycle_id=?',[$cycleId])[0]['n']===4,'AP cycle starts with all regular AP members');
    $supervisorMember=annualQuery($conn,'SELECT * FROM annual_evaluation_members WHERE cycle_id=? AND employee_id=4',[$cycleId])[0];
    $staffMember=annualQuery($conn,'SELECT * FROM annual_evaluation_members WHERE cycle_id=? AND employee_id=5',[$cycleId])[0];
    apCheck(annualCanSeeOwnPeerNames($conn,$supervisor,$supervisorMember)
        && annualCanSeeOwnPeerNames($conn,$owner,annualQuery($conn,'SELECT * FROM annual_evaluation_members WHERE cycle_id=? AND employee_id=3',[$cycleId])[0]),
        'AP heads can identify coworkers who rated their own evaluation');
    apCheck(!annualCanSeeOwnPeerNames($conn,annualActor($conn,5),$staffMember)
        && !annualCanSeeOwnPeerNames($conn,annualActor($conn,5),$supervisorMember),
        'AP staff cannot identify coworkers through their own result');
    $managerMember=annualQuery($conn,'SELECT * FROM annual_evaluation_members WHERE cycle_id=? AND employee_id=3',[$cycleId])[0];
    apCheck(annualCanFinalize($conn,annualActor($conn,2),$managerMember),'AP VP can review AP Manager KRA as immediate head');
    apCheck(!annualCanFinalize($conn,annualActor($conn,1),$managerMember),'Outside executive cannot review AP Manager without position assignment');
    apCheck(annualCycleForSelfRating($conn,1,5)['cycle_id']===$cycleId,'AP self-rating inherits the started cycle period');
    $scores=json_encode(array_fill(0,8,3));
    foreach ([2=>100,3=>101,4=>105,5=>108] as $employee=>$position) {
        annualQuery($conn,"INSERT INTO evaluations (evaluation_id,employee_id,template_id,evaluation_type,evaluation_period_start,evaluation_period_end,submitted_by,status,submitted_date)
            VALUES (?,?,?,'Annual','2026-01-01','2026-12-31',?,'Approved',NOW())",[$employee,$employee,$position,$employee]);
        annualQuery($conn,'UPDATE annual_evaluation_members SET evaluation_id=?,self_values_json=?,current_version=1 WHERE cycle_id=? AND employee_id=?',
            [$employee,$scores,$cycleId,$employee]);
    }
    foreach ([2,3,4,5] as $rater) foreach ([2,3,4,5] as $target) if ($rater!==$target) {
        annualQuery($conn,'INSERT INTO annual_peer_submissions (cycle_id,rater_employee_id,target_employee_id,submitted_by,scores_json) VALUES (?,?,?,?,?)',
            [$cycleId,$rater,$target,$rater,$scores]);
    }
    $pendingBeforeApproval=annualPendingCount($conn,$owner);
    apCheck(!annualUpdateCycleCompletion($conn,$cycleId),'Completed AP ratings wait for AP Manager approval');
    apCheck(annualPendingCount($conn,$owner)===$pendingBeforeApproval+1,
        'Ready AP Manager approval adds a sidebar task');
    apReject(fn()=>annualUpdateCycleCompletion($conn,$cycleId,2),'VP cannot approve AP cycle');
    apCheck(annualUpdateCycleCompletion($conn,$cycleId,3),'AP Manager approves the completed cycle');
    apCheck(annualPendingCount($conn,$owner)===$pendingBeforeApproval,
        'Approving the AP cycle clears its sidebar task');
    apCheck((int)annualQuery($conn,"SELECT COUNT(*) AS n FROM notifications WHERE user_id IN (8,9,10) AND title='Acquired Properties Annual cycle approved'")[0]['n']===3,
        'AP approval informs HR Manager, Supervisor, and Staff once');
    apCheck((int)annualQuery($conn,"SELECT COUNT(*) AS n FROM notifications WHERE user_id IN (8,9,10) AND title='Acquired Properties Annual cycle approved' AND needs_action=1")[0]['n']===0,
        'HR approval notices are informational');
    annualQuery($conn,"UPDATE employees SET employment_status='Regular',regularization_effective_date='2026-10-20' WHERE employee_id=6");
    annualQuery($conn,"UPDATE employees SET employment_status='Regular',regularization_effective_date='2027-01-01' WHERE employee_id=7");
    apCheck(!annualUpdateCycleCompletion($conn,$cycleId),'Eligible late regularization reopens approved AP cycle');
    apCheck((int)annualQuery($conn,'SELECT COUNT(*) AS n FROM annual_evaluation_members WHERE cycle_id=?',[$cycleId])[0]['n']===5,'Late in-period employee joins; out-of-period employee is excluded');
    apCheck(annualCycleForSelfRating($conn,1,6)['cycle_id']===$cycleId && annualCycleForSelfRating($conn,1,7)===null,'Only in-period late employee can self-rate');
    annualQuery($conn,"UPDATE employees SET regularization_effective_date=NULL,probationary_eligible_at='2026-10-20 12:00:00' WHERE employee_id=6");
    apCheck(annualCycleForSelfRating($conn,1,6)['cycle_id']===$cycleId,'Prior regularizations use their recorded eligibility date');
    apReject(fn()=>annualUpdateCycleCompletion($conn,$cycleId,3),'AP Manager cannot bypass late evaluation requirements');
    apCheck((int)annualQuery($conn,'SELECT COUNT(*) AS n FROM annual_peer_submissions WHERE cycle_id=?',[$cycleId])[0]['n']===12,'Earlier department 360 submissions remain locked in place');
    annualQuery($conn,"INSERT INTO evaluations (evaluation_id,employee_id,template_id,evaluation_type,evaluation_period_start,evaluation_period_end,submitted_by,status,submitted_date)
        VALUES (6,6,108,'Annual','2026-01-01','2026-12-31',6,'Approved',NOW())");
    annualQuery($conn,'UPDATE annual_evaluation_members SET evaluation_id=6,self_values_json=?,current_version=1 WHERE cycle_id=? AND employee_id=6',[$scores,$cycleId]);
    foreach ([2,3,4,5] as $peer) {
        foreach ([[$peer,6],[6,$peer]] as [$rater,$target]) {
            annualQuery($conn,'INSERT INTO annual_peer_submissions (cycle_id,rater_employee_id,target_employee_id,submitted_by,scores_json,is_late) VALUES (?,?,?,?,?,1)',
                [$cycleId,$rater,$target,$rater,$scores]);
        }
    }
    apCheck(!annualUpdateCycleCompletion($conn,$cycleId),'Late ratings require a fresh AP Manager approval');
    apCheck(annualPendingCount($conn,$owner)===$pendingBeforeApproval+1,
        'Late AP ratings restore the approval sidebar task');
    apCheck(annualUpdateCycleCompletion($conn,$cycleId,3),'AP Manager reapproves and archives the expanded cycle');
    apCheck(annualPendingCount($conn,$owner)===$pendingBeforeApproval,
        'Reapproving the expanded AP cycle clears its sidebar task');
    apCheck((int)annualQuery($conn,"SELECT COUNT(*) AS n FROM notifications WHERE user_id IN (8,9,10) AND title='Acquired Properties Annual cycle approved'")[0]['n']===6,
        'Reapproval after late ratings sends one fresh HR notice per role');
    apCheck((int)annualQuery($conn,'SELECT COUNT(*) AS n FROM annual_peer_submissions WHERE cycle_id=?',[$cycleId])[0]['n']===20,'Original and late department ratings are preserved');
} finally {
    if (isset($conn) && $conn instanceof mysqli) $conn->close();
    if ($created) $admin->query("DROP DATABASE `$testDb`");
    $admin->close();
}
