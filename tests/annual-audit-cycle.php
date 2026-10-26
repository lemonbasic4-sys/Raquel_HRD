<?php
/** Isolated Audit seed and cycle check. Never writes to the application database. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
session_set_save_handler(static fn()=>true,static fn()=>true,static fn()=>'',static fn()=>true,static fn()=>true,static fn()=>0);
session_start();
require_once __DIR__.'/../config/database.php';
$conn->close();
require_once __DIR__.'/../includes/functions.php';
date_default_timezone_set('Asia/Manila');
$_SERVER['REMOTE_ADDR']='127.0.0.1';
$_SERVER['HTTP_USER_AGENT']='Audit annual cycle test';
$admin=new mysqli('127.0.0.1','root','');
$source=$argv[1] ?? 'raquel_hris';
if (!preg_match('/^[a-zA-Z0-9_]+$/',$source)) throw new RuntimeException('Invalid source schema.');
$testDb='raquel_audit_cycle_test_'.bin2hex(random_bytes(5));
$created=false;
function auditImport(mysqli $db,string $path,string $testDb): void {
    $sql=file_get_contents($path);
    if ($sql===false) throw new RuntimeException('Cannot read '.$path);
    $sql=str_replace('USE raquel_hris;', 'USE `'.$testDb.'`;', $sql);
    if (preg_match('/^\s*USE\s+(?!`'.preg_quote($testDb,'/').'`\s*;)[^;]+;/im',$sql)) {
        throw new RuntimeException('Seed selects a database outside the isolated fixture: '.$path);
    }
    if (!$db->multi_query($sql)) throw new RuntimeException('Import failed: '.$path);
    do {
        $result=$db->store_result();
        if ($result) $result->free();
    } while ($db->more_results() && $db->next_result());
    if ($db->query('SELECT DATABASE()')->fetch_row()[0]!==$testDb) {
        throw new RuntimeException('Seed changed the active database: '.$path);
    }
}
function auditCheck(bool $ok,string $message): void {
    if (!$ok) throw new RuntimeException('FAIL: '.$message);
    echo 'PASS: '.$message.PHP_EOL;
}
function auditReject(callable $action,string $message): void {
    try { $action(); } catch (DomainException $error) { auditCheck(true,$message); return; }
    throw new RuntimeException('FAIL: '.$message);
}
try {
    $admin->query("CREATE DATABASE `$testDb` CHARACTER SET utf8mb4"); $created=true;
    foreach (array_column($admin->query("SHOW TABLES FROM `$source`")->fetch_all(MYSQLI_NUM),0) as $table) {
        $admin->query("CREATE TABLE `$testDb`.`$table` LIKE `$source`.`$table`");
    }
    $conn=new mysqli('127.0.0.1','root','',$testDb); $conn->set_charset('utf8mb4');
    auditImport($conn,__DIR__.'/../database/2nd_seed_organization.sql',$testDb);
    auditImport($conn,__DIR__.'/../test_database/major_employees_complete_profiles.sql',$testDb);
    auditImport($conn,__DIR__.'/../test_database/audit_annual_head_templates.sql',$testDb);
    ensureAnnualEvaluationSchema($conn);
    $departmentId=(int)annualQuery($conn,"SELECT department_id FROM departments WHERE department_name='Audit'")[0]['department_id'];
    $people=annualQuery($conn,"SELECT e.employee_id,e.job_title_id,e.employee_code,u.user_id FROM employees e
        JOIN users u ON u.employee_id=e.employee_id AND u.role='Employee'
        WHERE e.department_id=? ORDER BY e.job_title_id",[$departmentId]);
    auditCheck(array_column($people,'employee_code')===['AUD-001','AUD-002','AUD-003','AUD-004'],'Audit seed has four ordered employees');
    $roster=annualCycleRosterPreview($conn,$departmentId);
    auditCheck(count($roster)===4 && !array_filter($roster,static fn($row)=>!empty($row['issues'])),'Four Audit head-created forms and accounts are ready');
    $forms=annualQuery($conn,"SELECT t.template_id,p.job_title_id,COUNT(IF(c.section='KRA',1,NULL)) AS kras,
        SUM(IF(c.section='KRA',c.weight,0)) AS kra_weight,COUNT(IF(c.section='Behavior',1,NULL)) AS behaviors
        FROM evaluation_templates t JOIN evaluation_template_positions p ON p.template_id=t.template_id
        JOIN evaluation_criteria c ON c.template_id=t.template_id
        WHERE t.target_department_id=? AND t.template_name LIKE '[TEST AUDIT ANNUAL] %'
        GROUP BY t.template_id,p.job_title_id",[$departmentId]);
    auditCheck(count($forms)===4 && !array_filter($forms,static fn($form)=>(int)$form['kras']!==11
        || abs((float)$form['kra_weight']-100)>.001 || (int)$form['behaviors']!==8),'Each Audit form has 11 KRAs and eight Core Values');
    auditImport($conn,__DIR__.'/../test_database/audit_annual_head_templates.sql',$testDb);
    auditCheck((int)annualQuery($conn,"SELECT COUNT(*) AS n FROM evaluation_templates WHERE template_name LIKE '[TEST AUDIT ANNUAL] %'")[0]['n']===4,'Audit form seed rerun does not duplicate forms');
    $owner=annualActor($conn,(int)$people[0]['user_id']);
    $manager2=annualActor($conn,(int)$people[1]['user_id']);
    $supervisor=annualActor($conn,(int)$people[2]['user_id']);
    $auditor=annualActor($conn,(int)$people[3]['user_id']);
    auditCheck(annualCanOwnCycle($conn,$owner,$departmentId)
        && !annualCanOwnCycle($conn,$manager2,$departmentId)
        && !annualCanOwnCycle($conn,$supervisor,$departmentId),'Only Audit Manager I owns the Audit cycle');
    auditCheck(annualCanViewCycleSetup($conn,$manager2,$departmentId)
        && annualCanViewCycleSetup($conn,$supervisor,$departmentId)
        && !annualCanViewCycleSetup($conn,$auditor,$departmentId),'Other Audit heads see readiness read only');
    $readyToStart=annualApCycleReadyToStart($conn,$owner);
    $pendingCount=annualPendingCount($conn,$owner);
    auditCheck($readyToStart && $pendingCount===1,'Ready Audit cycle adds one owner sidebar task');
    annualSyncApCycleReadinessNotification($conn,$owner);
    auditCheck((int)annualQuery($conn,"SELECT COUNT(*) AS n FROM notifications WHERE user_id=? AND title LIKE 'Audit Annual cycle ready to start (%)' AND needs_action=1",[(int)$owner['user_id']])[0]['n']===1,'Audit owner receives readiness notice');
    auditReject(fn()=>annualStartCycle($conn,$manager2,$departmentId,'2026-01-01','2026-12-31','2026-12-31'),'Audit Manager II cannot start cycle');
    $cycleId=annualStartCycle($conn,$owner,$departmentId,'2026-01-01','2026-12-31','2026-12-31');
    auditCheck((int)annualQuery($conn,'SELECT COUNT(*) AS n FROM annual_evaluation_members WHERE cycle_id=?',[$cycleId])[0]['n']===4,'Starting Audit cycle enrolls all four members');
    auditCheck(!annualApCycleReadyToStart($conn,$owner)
        && annualCycleForSelfRating($conn,$departmentId,(int)$people[3]['employee_id'])!==null,'Started cycle opens employee self-rating and clears start task');
    $score=annualCalculate([['rating'=>4,'weight'=>70],['rating'=>2,'weight'=>30]],array_fill(0,8,3),[array_fill(0,8,4),array_fill(0,8,2)]);
    auditCheck($score['kra']===3.4 && $score['core']===3.0 && $score['total']===3.32,'Audit uses the same 80/20 calculator and counts self once');
    auditReject(fn()=>annualUpdateCycleCompletion($conn,$cycleId,(int)$owner['user_id']),'Audit owner cannot approve incomplete cycle');
    $scores=json_encode(array_fill(0,8,3));
    foreach ($people as $person) {
        $templateId=(int)array_values(array_filter($forms,static fn($form)=>(int)$form['job_title_id']===(int)$person['job_title_id']))[0]['template_id'];
        $employeeId=(int)$person['employee_id'];
        annualQuery($conn,"INSERT INTO evaluations (employee_id,template_id,evaluation_type,evaluation_period_start,
            evaluation_period_end,submitted_by,status,submitted_date) VALUES (?,?,'Annual','2026-01-01',
            '2026-12-31',?,'Approved',NOW())",[$employeeId,$templateId,$employeeId]);
        $evaluationId=(int)$conn->insert_id;
        annualQuery($conn,'UPDATE annual_evaluation_members SET evaluation_id=?,self_values_json=?,current_version=1
            WHERE cycle_id=? AND employee_id=?',[$evaluationId,$scores,$cycleId,$employeeId]);
    }
    $topMember=annualQuery($conn,'SELECT * FROM annual_evaluation_members WHERE cycle_id=? AND employee_id=?',
        [$cycleId,(int)$people[0]['employee_id']])[0];
    $secondMember=annualQuery($conn,'SELECT * FROM annual_evaluation_members WHERE cycle_id=? AND employee_id=?',
        [$cycleId,(int)$people[1]['employee_id']])[0];
    $ceo=annualActor($conn,(int)annualQuery($conn,"SELECT user_id FROM users WHERE role='President and CEO' LIMIT 1")[0]['user_id']);
    auditCheck(annualPresidentAuditPreviewDepartmentId($conn,$ceo)===$departmentId
        && !annualCanOwnCycle($conn,$ceo,$departmentId),'CEO can inspect Audit readiness without owning its cycle');
    auditCheck(annualCanFinalize($conn,$ceo,$topMember)
        && annualCanFinalize($conn,$owner,$secondMember)
        && !annualCanFinalize($conn,$ceo,$secondMember),'CEO reviews Audit Manager I; Audit Manager I reviews Manager II');
    foreach ($people as $rater) foreach ($people as $target) {
        if ($rater['employee_id']===$target['employee_id']) continue;
        annualQuery($conn,'INSERT INTO annual_peer_submissions (cycle_id,rater_employee_id,target_employee_id,submitted_by,scores_json)
            VALUES (?,?,?,?,?)',[$cycleId,(int)$rater['employee_id'],(int)$target['employee_id'],(int)$rater['user_id'],$scores]);
    }
    auditCheck(!annualUpdateCycleCompletion($conn,$cycleId),'Completed Audit ratings wait for Audit Manager I approval');
    auditCheck(annualPendingCount($conn,$owner)>=1,'Audit owner sees approval sidebar task');
    auditReject(fn()=>annualUpdateCycleCompletion($conn,$cycleId,(int)$manager2['user_id']),'Audit Manager II cannot approve cycle');
    auditCheck(annualUpdateCycleCompletion($conn,$cycleId,(int)$owner['user_id']),'Audit Manager I approves cycle into history');
    annualQuery($conn,"INSERT INTO employees (employee_id,employee_code,first_name,last_name,hire_date,job_title_id,
        job_title,department_id,employment_status,regularization_effective_date)
        VALUES (29999,'AUD-LATE','Late','Auditor','2026-02-01',206,'Auditor I',?,'Regular','2026-10-20')",[$departmentId]);
    annualQuery($conn,"INSERT INTO users (user_id,employee_id,username,email,password_hash,full_name,role)
        VALUES (29999,29999,'audit_late','audit_late@example.invalid','unused','Late Auditor','Employee')");
    auditCheck(!annualUpdateCycleCompletion($conn,$cycleId)
        && (int)annualQuery($conn,'SELECT COUNT(*) AS n FROM annual_evaluation_members WHERE cycle_id=?',[$cycleId])[0]['n']===5,
        'In-period regularization reopens Audit cycle with the new member');
    auditCheck(annualCycleForSelfRating($conn,$departmentId,29999)!==null,'New Audit member can self-rate the original period');
    auditReject(fn()=>annualUpdateCycleCompletion($conn,$cycleId,(int)$owner['user_id']),'Audit owner cannot bypass late member requirements');
    $auditorTemplate=(int)array_values(array_filter($forms,static fn($form)=>(int)$form['job_title_id']===206))[0]['template_id'];
    annualQuery($conn,"INSERT INTO evaluations (employee_id,template_id,evaluation_type,evaluation_period_start,
        evaluation_period_end,submitted_by,status,submitted_date) VALUES (29999,?,'Annual','2026-01-01',
        '2026-12-31',29999,'Approved',NOW())",[$auditorTemplate]);
    $lateEvaluationId=(int)$conn->insert_id;
    annualQuery($conn,'UPDATE annual_evaluation_members SET evaluation_id=?,self_values_json=?,current_version=1
        WHERE cycle_id=? AND employee_id=29999',[$lateEvaluationId,$scores,$cycleId]);
    foreach ($people as $person) {
        foreach ([[(int)$person['employee_id'],29999,(int)$person['user_id']], [29999,(int)$person['employee_id'],29999]] as [$rater,$target,$userId]) {
            annualQuery($conn,'INSERT INTO annual_peer_submissions (cycle_id,rater_employee_id,target_employee_id,submitted_by,scores_json,is_late)
                VALUES (?,?,?,?,?,1)',[$cycleId,$rater,$target,$userId,$scores]);
        }
    }
    auditCheck(!annualUpdateCycleCompletion($conn,$cycleId) && annualPendingCount($conn,$owner)>=1,
        'Late Audit ratings require another owner approval');
    auditCheck(annualUpdateCycleCompletion($conn,$cycleId,(int)$owner['user_id']),
        'Audit Manager I reapproves the expanded cycle');
} finally {
    if (isset($conn) && $conn instanceof mysqli) $conn->close();
    if ($created) $admin->query("DROP DATABASE `$testDb`");
    $admin->close();
}
