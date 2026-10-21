<?php
/** Run: C:\xampp\php\php.exe tests/annual-evaluations.php
 * Clones table STRUCTURES into a uniquely named temporary database; never imports
 * employees or ratings into the application's database. Removes its test DB afterward.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
// In-memory CLI session; do not write fixture sessions into XAMPP's login folder.
session_set_save_handler(static fn()=>true, static fn()=>true, static fn()=>'', static fn()=>true, static fn()=>true, static fn()=>0);
session_start();
require_once __DIR__ . '/../config/database.php';
// Mark the normal configuration as loaded, then replace its connection with
// the isolated fixture before any application/session/header code is rendered.
$conn->close();
require_once __DIR__ . '/../includes/functions.php';
date_default_timezone_set('Asia/Manila');
$_SERVER['REMOTE_ADDR']='127.0.0.1'; $_SERVER['HTTP_USER_AGENT']='Annual workflow integration test';
$admin=new mysqli('127.0.0.1','root','');
$source=$argv[1] ?? 'raquel_hris';
if (!preg_match('/^[a-zA-Z0-9_]+$/',$source)) throw new RuntimeException('Invalid source schema.');
$testDb='raquel_annual_test_'.bin2hex(random_bytes(6));
$checks=0;
$created=false;
function checkAnnual(bool $condition,string $description): void {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: '.$description);
    $checks++; echo 'PASS: '.$description.PHP_EOL;
}
function rejectsAnnual(callable $operation,string $description): void {
    try { $operation(); } catch (DomainException $e) { checkAnnual(true,$description); return; }
    throw new RuntimeException('FAIL (was accepted): '.$description);
}
try {
    $admin->query("CREATE DATABASE `$testDb` CHARACTER SET utf8mb4");
    $created=true;
    $sourceTables=$admin->query("SHOW TABLES FROM `$source`")->fetch_all(MYSQLI_NUM);
    foreach (array_column($sourceTables,0) as $table) {
        $admin->query("CREATE TABLE `$testDb`.`$table` LIKE `$source`.`$table`");
    }
    $conn=new mysqli('127.0.0.1','root','',$testDb); $conn->set_charset('utf8mb4');
    ensureAnnualEvaluationSchema($conn); ensureAuditTrailSchema($conn);
    foreach ([1=>'Human Resources',2=>'Office of the President',3=>'Research'] as $id=>$name) annualQuery($conn,'INSERT INTO departments (department_id,department_name) VALUES (?,?)',[$id,$name]);
    $positions=[[1,'President and CEO',2,null],[2,'HR Manager',1,1],[3,'HR Supervisor',1,2],[4,'HR Staff',1,3],[5,'Research Officer',3,1]];
    foreach ($positions as $p) annualQuery($conn,'INSERT INTO job_titles (job_title_id,job_title,department_id,reports_to) VALUES (?,?,?,?)',$p);
    $employees=[[1,1,2,'President and CEO'],[2,2,1,'HR Manager'],[3,3,1,'HR Supervisor'],[4,4,1,'HR Staff'],[5,5,3,'Research Officer'],[6,4,1,'HR Staff']];
    $roles=[1=>'President and CEO',2=>'HR Manager',3=>'HR Supervisor',4=>'HR Staff',5=>'Employee',6=>'HR Staff'];
    foreach ($employees as [$id,$position,$dept,$title]) {
        annualQuery($conn,"INSERT INTO employees (employee_id,employee_code,first_name,last_name,hire_date,job_title_id,job_title,department_id,employment_status) VALUES (?,?,?,?,'2020-01-01',?,?,?,'Regular')",[$id,'TEST-'.$id,'Test'.$id,'Employee',$position,$title,$dept]);
        annualQuery($conn,"INSERT INTO users (user_id,employee_id,username,email,password_hash,full_name,role) VALUES (?,?,?,?,?,?,?)",[$id,$id,'test'.$id,'test'.$id.'@example.invalid','unused','Test Employee '.$id,$roles[$id]]);
    }
    annualQuery($conn,"INSERT INTO users (user_id,employee_id,username,email,password_hash,full_name,role) VALUES (7,4,'staff_self','staff_self@example.invalid','unused','HR Staff Self','Employee')");
    foreach ([2,3,4,5] as $position) {
        $dept=$position===5 ? 3 : 1;
        annualQuery($conn,"INSERT INTO evaluation_templates (template_id,template_name,evaluation_type,status,target_department_id,kra_weight,behavior_weight) VALUES (?,?,'Annual','Active',?,80,20)",[$position,'Annual '.$position,$dept]);
        annualQuery($conn,'INSERT INTO evaluation_template_positions (template_id,job_title_id) VALUES (?,?)',[$position,$position]);
        foreach ([70,30] as $i=>$weight) annualQuery($conn,"INSERT INTO evaluation_criteria (template_id,section,criterion_name,weight,sort_order) VALUES (?,'KRA',?,?,?)",[$position,'KRA '.($i+1),$weight,$i+1]);
        foreach (annualCoreValues() as $i=>$value) annualQuery($conn,"INSERT INTO evaluation_criteria (template_id,section,criterion_name,kpi_description,weight,sort_order) VALUES (?,'Behavior',?,?,0,?)",[$position,$value['name'],$value['kpi'],$i+1]);
    }
    $self=[2=>3.6,3=>3.4,4=>4.0,5=>3.0,6=>3.0];
    $evals=[];
    $makeEvaluation=function(int $employee,int $position,int $evaluationId) use ($conn,$self): void {
        annualQuery($conn,"INSERT INTO evaluations (evaluation_id,employee_id,template_id,evaluation_type,evaluation_period_start,evaluation_period_end,submitted_by,status,submitted_date) VALUES (?,?,?,'Annual','2026-01-01','2026-12-31',?,'Pending Team Consolidation',NOW())",[$evaluationId,$employee,$position,$employee]);
        $criteria=annualQuery($conn,'SELECT * FROM evaluation_criteria WHERE template_id=? ORDER BY section,sort_order',[$position]);
        foreach ($criteria as $c) {
            $score=$c['section']==='Behavior' ? ($self[$employee] ?? 3.0) : ($c['sort_order']==1 ? 3.0 : 4.0);
            annualQuery($conn,'INSERT INTO evaluation_scores (evaluation_id,criterion_id,score_value,weighted_score) VALUES (?,?,?,?)',[$evaluationId,(int)$c['criterion_id'],$score,$score]);
        }
        $conn->begin_transaction();
        try { annualRegisterSubmission($conn,$evaluationId); $conn->commit(); } catch (Throwable $e) { $conn->rollback(); throw $e; }
    };
    foreach ([2=>2,3=>3,4=>4,5=>5,6=>4] as $employee=>$position) { $evals[$employee]=$employee; $makeEvaluation($employee,$position,$employee); }
    $actors=[]; foreach (range(1,7) as $id) $actors[$id]=annualActor($conn,$id);
    $m=annualMember($conn,4); $cycle=(int)$m['cycle_id'];

    $signature='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/6wAAAABJRU5ErkJggg==';
    $plans=[['improvement_area'=>'Employee records accuracy','support_needed'=>'Monthly coaching and checklist review','time_frame'=>'Within 3 months']];
    annualReviewAndRate($conn,$actors[3],4,[],annualReviewToken($conn,annualMember($conn,4)),[],$signature,$plans,'Continue accuracy checks and attend monthly coaching.');
    checkAnnual(annualMember($conn,4)['head_comments']==='Continue accuracy checks and attend monthly coaching.','Head comments save to the print-form comments field');
    checkAnnual(annualQuery($conn,'SELECT improvement_area,support_needed,time_frame FROM evaluation_dev_plans WHERE evaluation_id=4')===$plans,'Developmental plan saves with signed head approval');
    $approved=annualMember($conn,4);
    checkAnnual($approved['evaluation_status']==='Approved' && (int)$approved['current_version']===1,'Head submission immediately approves Annual evaluation before peers complete');
    checkAnnual((float)$approved['official_score']===3.44,'Approval persists effective default KRA and self/head behavior');
    checkAnnual(annualPendingReviewCount($conn,$actors[3])===1,'Approved employee no longer waits for another head review');
    $v=json_decode(annualQuery($conn,'SELECT snapshot_json FROM annual_evaluation_versions WHERE member_id=? ORDER BY version_number DESC LIMIT 1',[(int)$approved['member_id']])[0]['snapshot_json'],true);
    checkAnnual($v['score_complete']===false,'Initial approval is explicitly provisional');
    annualSubmitPeers($conn,$actors[2],4,array_fill(0,8,1.0));
    $updated=annualMember($conn,4);
    checkAnnual($updated['evaluation_status']==='Approved' && !$updated['revision_pending'],'Later peers keep approval and require no new review');
    checkAnnual((float)$updated['official_score']===3.24,'Later peer score automatically changes the official overall score');
    checkAnnual((float)annualKraRows($conn,4)[0]['rating']===3.0,'Later peers do not alter approved KRA');
    annualSubmitPeers($conn,$actors[6],4,array_fill(0,8,2.0));
    $complete=annualMember($conn,4);
    $v=json_decode(annualQuery($conn,'SELECT snapshot_json FROM annual_evaluation_versions WHERE member_id=? ORDER BY version_number DESC LIMIT 1',[(int)$complete['member_id']])[0]['snapshot_json'],true);
    checkAnnual($v['score_complete']===true && (float)$complete['official_score']===3.19,'Score becomes complete after every expected rater submits');
    checkAnnual($v['development_plans']===$plans,'Later peer score updates preserve the approved developmental plan');
    checkAnnual($v['head_comments']==='Continue accuracy checks and attend monthly coaching.','Later peer updates preserve head comments in score history');
    checkAnnual(count(annualQuery($conn,'SELECT version_id FROM annual_evaluation_versions WHERE member_id=?',[(int)$complete['member_id']]))===3,'Every peer update preserves a score version');
    checkAnnual((bool)annualQuery($conn,"SELECT notification_id FROM notifications WHERE user_id=4 AND title='Annual score updated' AND message LIKE '%3.44%3.24%'"),'Employee receives previous-to-updated score notice');
    checkAnnual((bool)annualQuery($conn,"SELECT notification_id FROM notifications WHERE user_id=4 AND title='Annual score complete'"),'Employee receives score-complete notice');
    annualQuery($conn,'UPDATE annual_evaluation_members SET evaluation_id=NULL,self_values_json=NULL WHERE cycle_id=? AND employee_id=2',[$cycle]);
    annualSubmitPeers($conn,$actors[2],6,array_fill(0,8,3.0));
    checkAnnual((bool)annualQuery($conn,'SELECT submission_id FROM annual_peer_submissions WHERE cycle_id=? AND target_employee_id=6 AND rater_employee_id=2',[$cycle]),'HR Manager can rate a department peer before submitting their own self-rating');
    rejectsAnnual(fn()=>annualReviewAndRate($conn,$actors[2],6,[],annualReviewToken($conn,annualMember($conn,6)),[],$signature),'HR Manager peer access does not grant KRA approval over HR Staff');
    echo "Approved Annual live-score integration checks passed.\n";
} finally {
    if (isset($conn) && $conn instanceof mysqli) $conn->close();
    if ($created && preg_match('/^raquel_annual_test_[a-f0-9]{12}$/',$testDb)) $admin->query("DROP DATABASE IF EXISTS `$testDb`");
    $admin->close();
}
