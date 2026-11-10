<?php
// CLI rendering smoke check, hardwired to the isolated test database.
if(PHP_SAPI!=='cli') {http_response_code(404);exit;}
define('DB_HOST','127.0.0.1');define('DB_USER','root');define('DB_PASS','');define('DB_NAME','raquel_individual_workflow_test');define('BASE_URL','/Raquel_HRD');
set_error_handler(function($n,$text){if(str_contains($text,'Constant ') && str_contains($text,'already defined'))return true;return false;});
ini_set('session.save_path',sys_get_temp_dir());session_start();
$c=new mysqli(DB_HOST,DB_USER,DB_PASS,DB_NAME);
$code=$argv[1]??'HRD-001';$kind=$argv[2]??'annual';$view=$argv[3]??'peers';
$role=$argv[5]??'Employee';
$u=$c->query("SELECT u.* FROM users u JOIN employees e USING(employee_id) WHERE e.employee_code='".$c->real_escape_string($code)."' AND u.role='".$c->real_escape_string($role)."' LIMIT 1")->fetch_assoc();
$_SESSION=['user_id'=>(int)$u['user_id'],'employee_id'=>(int)$u['employee_id'],'role'=>$role,'full_name'=>$u['full_name'],'last_activity'=>time(),'login_time'=>time()];
$_SERVER['REQUEST_METHOD']='GET';$_SERVER['SCRIPT_NAME']='/Raquel_HRD/employee/'.$kind.'-evaluations.php';$_SERVER['PHP_SELF']=$_SERVER['SCRIPT_NAME'];$_SERVER['REQUEST_URI']=$_SERVER['SCRIPT_NAME'];$_SERVER['HTTP_HOST']='localhost';
$_GET=['view'=>$view];
if($kind==='self-rating') {
 $_GET=[];
 if(!empty($argv[4]))$_GET['template']=(int)$argv[4];
 $_SERVER['SCRIPT_NAME']='/Raquel_HRD/employee/self-rating.php';$_SERVER['PHP_SELF']=$_SERVER['SCRIPT_NAME'];$_SERVER['REQUEST_URI']=$_SERVER['SCRIPT_NAME'];
}
if(!empty($argv[4]) && $kind!=='self-rating')$_GET['evaluation_id']=(int)$argv[4];
if($role!=='Employee') { $annual_in_performance_page=$kind==='annual';$quarterly_in_performance_page=$kind==='quarterly'; }
if($kind==='create-template') {
 $_SERVER['SCRIPT_NAME']='/Raquel_HRD/manager/create-template.php';$_SERVER['PHP_SELF']=$_SERVER['SCRIPT_NAME'];$_SERVER['REQUEST_URI']=$_SERVER['SCRIPT_NAME'];
 chdir(__DIR__.'/../../manager');require 'create-template.php';
} else {
 chdir(__DIR__.'/../../employee');require $kind==='self-rating' ? 'self-rating.php' : $kind.'-evaluations.php';
}
