<?php
/** Isolated integration check. Run: C:\xampp\php\php.exe tests/annual-cycle-start.php */
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
session_set_save_handler(static fn()=>true,static fn()=>true,static fn()=>'',static fn()=>true,static fn()=>true,static fn()=>0);
session_start();
require_once __DIR__.'/../config/database.php';
$conn->close();
require_once __DIR__.'/../includes/functions.php';
date_default_timezone_set('Asia/Manila');
$_SERVER['REMOTE_ADDR']='127.0.0.1';
$_SERVER['HTTP_USER_AGENT']='Annual cycle start test';
$admin=new mysqli('127.0.0.1','root','');
$source=$argv[1] ?? 'raquel_hris';
if (!preg_match('/^[a-zA-Z0-9_]+$/',$source)) throw new RuntimeException('Invalid source schema.');
$testDb='raquel_cycle_test_'.bin2hex(random_bytes(5));
$created=false;
function cycleCheck(bool $condition,string $description): void {
    if (!$condition) throw new RuntimeException('FAIL: '.$description);
    echo 'PASS: '.$description.PHP_EOL;
}
function cycleReject(callable $operation,string $description): void {
    try { $operation(); } catch (DomainException $error) { cycleCheck(true,$description); return; }
    throw new RuntimeException('FAIL: '.$description);
}
try {
    $admin->query("CREATE DATABASE `$testDb` CHARACTER SET utf8mb4"); $created=true;
    foreach (array_column($admin->query("SHOW TABLES FROM `$source`")->fetch_all(MYSQLI_NUM),0) as $table) {
        $admin->query("CREATE TABLE `$testDb`.`$table` LIKE `$source`.`$table`");
    }
    $conn=new mysqli('127.0.0.1','root','',$testDb); $conn->set_charset('utf8mb4');
    ensureAnnualEvaluationSchema($conn); ensureAuditTrailSchema($conn);
    annualQuery($conn,"INSERT INTO departments (department_id,department_name) VALUES (1,'Human Resources'),(2,'Office of the President'),(3,'Research')");
    foreach ([[1,'President and CEO',2,null],[2,'HR Manager',1,1],[3,'HR Supervisor',1,2],[4,'HR Staff',1,3],[5,'Research Officer',3,1]] as $position) {
        annualQuery($conn,'INSERT INTO job_titles (job_title_id,job_title,department_id,reports_to) VALUES (?,?,?,?)',$position);
    }
    foreach ([[1,1,2,'President and CEO'],[2,2,1,'HR Manager'],[3,3,1,'HR Supervisor'],[4,4,1,'HR Staff']] as [$id,$position,$department,$title]) {
        annualQuery($conn,"INSERT INTO employees (employee_id,employee_code,first_name,last_name,hire_date,job_title_id,job_title,department_id,employment_status) VALUES (?,?,?,?,'2020-01-01',?,?,?,'Regular')",
            [$id,'TEST-'.$id,'Test'.$id,'Employee',$position,$title,$department]);
        annualQuery($conn,'INSERT INTO users (user_id,employee_id,username,email,password_hash,full_name,role) VALUES (?,?,?,?,?,?,?)',
            [$id,$id,'hris'.$id,'hris'.$id.'@example.invalid','unused','Test '.$id,$title]);
        if ($id>1) annualQuery($conn,'INSERT INTO users (user_id,employee_id,username,email,password_hash,full_name,role) VALUES (?,?,?,?,?,?,?)',
            [$id+10,$id,'portal'.$id,'portal'.$id.'@example.invalid','unused','Test '.$id,'Employee']);
    }
    foreach ([2,3,4] as $position) {
        annualQuery($conn,"INSERT INTO evaluation_templates (template_id,template_name,evaluation_type,status,target_department_id,kra_weight,behavior_weight,created_by) VALUES (?,?,'Annual','Active',1,80,20,?)",[$position,'Annual '.$position,$position-1]);
        annualQuery($conn,'INSERT INTO evaluation_template_positions (template_id,job_title_id) VALUES (?,?)',[$position,$position]);
        foreach ([70,30] as $index=>$weight) annualQuery($conn,"INSERT INTO evaluation_criteria (template_id,section,criterion_name,weight,sort_order) VALUES (?,'KRA',?,?,?)",[$position,'KRA '.($index+1),$weight,$index+1]);
        foreach (annualCoreValues() as $index=>$value) annualQuery($conn,"INSERT INTO evaluation_criteria (template_id,section,criterion_name,kpi_description,weight,sort_order) VALUES (?,'Behavior',?,?,0,?)",[$position,$value['name'],$value['kpi'],$index+1]);
    }
    annualQuery($conn,"INSERT INTO employees (employee_id,employee_code,first_name,last_name,hire_date,job_title_id,job_title,department_id,employment_status) VALUES (5,'TEST-5','Research','Officer','2020-01-01',5,'Research Officer',3,'Regular')");
    annualQuery($conn,"INSERT INTO users (user_id,employee_id,username,email,password_hash,full_name,role) VALUES (5,5,'research','research@example.invalid','unused','Research Officer','Employee')");
    annualQuery($conn,"INSERT INTO evaluation_templates (template_id,template_name,evaluation_type,status,target_department_id,kra_weight,behavior_weight,created_by) VALUES (5,'Research Annual','Annual','Active',3,80,20,1)");
    annualQuery($conn,'INSERT INTO evaluation_template_positions (template_id,job_title_id) VALUES (5,5)');
    foreach ([70,30] as $index=>$weight) annualQuery($conn,"INSERT INTO evaluation_criteria (template_id,section,criterion_name,weight,sort_order) VALUES (5,'KRA',?,?,?)",['Research KRA '.($index+1),$weight,$index+1]);
    foreach (annualCoreValues() as $index=>$value) annualQuery($conn,"INSERT INTO evaluation_criteria (template_id,section,criterion_name,kpi_description,weight,sort_order) VALUES (5,'Behavior',?,?,0,?)",[$value['name'],$value['kpi'],$index+1]);
    annualQuery($conn,"INSERT INTO evaluations (evaluation_id,employee_id,template_id,evaluation_type,evaluation_period_start,evaluation_period_end,submitted_by,status,submitted_date) VALUES (4,4,4,'Annual','2026-01-01','2026-12-31',14,'Pending Team Consolidation',NOW())");
    foreach (annualQuery($conn,'SELECT criterion_id FROM evaluation_criteria WHERE template_id=4') as $criterion) {
        annualQuery($conn,'INSERT INTO evaluation_scores (evaluation_id,criterion_id,score_value,weighted_score) VALUES (4,?,3,3)',[(int)$criterion['criterion_id']]);
    }
    annualQuery($conn,"INSERT INTO evaluations (evaluation_id,employee_id,template_id,evaluation_type,evaluation_period_start,evaluation_period_end,submitted_by,status,submitted_date) VALUES (5,5,5,'Annual','2026-01-01','2026-12-31',5,'Pending Team Consolidation',NOW())");
    foreach (annualQuery($conn,'SELECT criterion_id FROM evaluation_criteria WHERE template_id=5') as $criterion) {
        annualQuery($conn,'INSERT INTO evaluation_scores (evaluation_id,criterion_id,score_value,weighted_score) VALUES (5,?,3,3)',[(int)$criterion['criterion_id']]);
    }
    annualRegisterSubmission($conn,5);
    cycleCheck((int)annualMember($conn,5)['department_id']===3,'Non-HRD department retains submission-led Annual cycle');
    cycleReject(fn()=>annualRegisterSubmission($conn,4),'Self-rating cannot create its own cycle');
    annualQuery($conn,'UPDATE evaluation_templates SET created_by=1 WHERE template_id=4');
    cycleCheck((bool)array_filter(annualCycleRosterPreview($conn,1),static fn($row)=>!empty($row['issues'])),'A form created by the wrong head blocks HRD start');
    annualQuery($conn,'UPDATE evaluation_templates SET created_by=3 WHERE template_id=4');
    $roster=annualCycleRosterPreview($conn,1);
    cycleCheck(count($roster)===3 && !array_filter($roster,static fn($row)=>!empty($row['issues'])),'HRD roster is ready');
    $actor=annualActor($conn,2);
    $_GET=[]; $_POST=[];
    ob_start(); include __DIR__.'/../includes/annual-cycle-start-panel.php'; $panelHtml=ob_get_clean();
    cycleCheck(str_contains($panelHtml,'Start HRD cycle and notify employees') && !str_contains($panelHtml,'Research'),
        'Integrated start panel shows only the Human Resources roster');
    cycleReject(fn()=>annualStartCycle($conn,annualActor($conn,2),2,'2026-01-01','2026-12-31','2027-01-15'),'HR Manager cannot start another department');
    $cycleId=annualStartCycle($conn,annualActor($conn,2),1,'2026-01-01','2026-12-31','2027-01-15');
    cycleCheck($cycleId>0 && (int)annualQuery($conn,'SELECT COUNT(*) AS n FROM annual_evaluation_members WHERE cycle_id=?',[$cycleId])[0]['n']===3,'Manager starts cycle with full roster');
    cycleCheck((int)annualQuery($conn,"SELECT COUNT(*) AS n FROM notifications WHERE title='Annual self-rating is open'")[0]['n']===3,'Each portal account receives a start notification');
    cycleReject(fn()=>annualStartCycle($conn,annualActor($conn,2),1,'2026-01-01','2026-12-31','2027-01-15'),'Overlapping cycle is rejected');
    cycleReject(fn()=>annualStartCycle($conn,annualActor($conn,2),1,'2027-01-01','2027-12-31','2028-01-15'),'A second period cannot start while the current cycle is open');
    annualRegisterSubmission($conn,4);
    cycleCheck((int)annualMember($conn,4)['cycle_id']===$cycleId,'Self-rating joins the started department cycle');
    cycleCheck(annualCycleForSelfRating($conn,1,3)['period_start']==='2026-01-01','Remaining employee receives manager-selected dates');
} finally {
    if (isset($conn) && $conn instanceof mysqli) $conn->close();
    if ($created) $admin->query("DROP DATABASE `$testDb`");
    $admin->close();
}
