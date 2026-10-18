<?php
// Isolated controller-request helper. Never available over HTTP.
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
$fixture=$argv[1] ?? '';
if (!preg_match('/^raquel_annual_test_[a-f0-9]{12}$/',$fixture)) throw new RuntimeException('An isolated annual fixture schema is required.');
session_set_save_handler(static fn()=>true,static fn()=>true,static fn()=>'',static fn()=>true,static fn()=>true,static fn()=>0);
session_start();
require_once __DIR__.'/../config/database.php';
$conn->close();
$conn=new mysqli(DB_HOST,DB_USER,DB_PASS,$fixture); $conn->set_charset('utf8mb4');
require_once __DIR__.'/../includes/functions.php';
$case=$argv[2] ?? 'submit';
$role=$case==='legacy' ? 'HR Manager' : ($case==='template' ? 'HR Supervisor' : 'Employee');
$user=$case==='legacy' ? 2 : ($case==='template' ? 3 : ($case==='probationary' ? 9 : 7));
$_SESSION=['user_id'=>$user,'employee_id'=>$user===7 ? 4 : $user,'role'=>$role,'full_name'=>'Fixture User','branch_id'=>0,'csrf_token'=>'fixture-csrf-token'];
$_SERVER['REQUEST_METHOD']='POST'; $_SERVER['REMOTE_ADDR']='127.0.0.1'; $_SERVER['HTTP_USER_AGENT']='Annual controller fixture';
$_GET=[];
if ($case==='template') {
    $_POST=['csrf_token'=>'fixture-csrf-token','template_name'=>'Annual test created through controller',
        'target_department'=>'Human Resources','evaluation_type'=>'Annual','kra_weight'=>90,'behavior_weight'=>10,
        'kra_name'=>['Controller KRA 1','Controller KRA 2'],'kra_weight_item'=>[70,30],
        'behavior_name'=>['Untrusted extra behavior'],'behavior_kpi'=>['Must be ignored'],
        'revision_date'=>'','effective_date_form'=>''];
    $route='/manager/create-template.php';
} elseif ($case==='legacy') {
    $_POST=['csrf_token'=>'fixture-csrf-token','evaluation_id'=>4,'ratings'=>[]];
    foreach (annualKraRows($conn,4) as $row) $_POST['ratings'][$row['score_id']]=1;
    $route='/manager/ajax/save-rating.php';
} else {
    $templateId=$case==='probationary' ? 9 : 4;
    $_POST=['csrf_token'=>$case==='csrf' ? 'invalid-token' : 'fixture-csrf-token',
        'template_id'=>$templateId,'period_start'=>$case==='probationary' ? '2026-09-01' : '2027-01-01','period_end'=>$case==='probationary' ? '2026-12-01' : '2027-12-31',
        'submit_action'=>'submit','employee_consent_agreed'=>1,
        'employee_signature_data'=>'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        'kra_scores'=>[],'beh_scores'=>[]];
    foreach (annualQuery($conn,'SELECT criterion_id,section FROM evaluation_criteria WHERE template_id=?',[$templateId]) as $row) {
        $_POST[$row['section']==='KRA' ? 'kra_scores' : 'beh_scores'][$row['criterion_id']]=3;
    }
    $route='/employee/self-rating.php';
}
$_SERVER['SCRIPT_NAME']=BASE_URL.$route; $_SERVER['PHP_SELF']=$_SERVER['SCRIPT_NAME']; $_SERVER['REQUEST_URI']=$_SERVER['SCRIPT_NAME'];
chdir(dirname(dirname(__DIR__).$route));
require dirname(__DIR__).$route;
