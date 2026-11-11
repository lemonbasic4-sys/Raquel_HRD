<?php
// Run only against the explicitly named disposable database. Never loads app DB config.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$conn=new mysqli('127.0.0.1','root','','raquel_individual_workflow_test');
$conn->set_charset('utf8mb4');date_default_timezone_set('Asia/Manila');
define('BASE_URL','/Raquel_HRD');$_SESSION=[];
require __DIR__.'/../../includes/functions.php';
function check($test,$message){if(!$test)throw new RuntimeException($message);echo "PASS $message\n";}
ensureAnnualEvaluationSchema($conn);ensureQuarterlyEvaluationSchema($conn);ensureAuditTrailSchema($conn);
$conn->query("UPDATE employees SET regularization_effective_date=CASE employee_code WHEN 'HRD-003' THEN '2026-11-15' ELSE '2020-01-01' END WHERE employee_code IN ('HRD-001','HRD-002','HRD-003')");
$employees=[];foreach($conn->query("SELECT * FROM employees WHERE employee_code IN ('HRD-001','HRD-002','HRD-003')") as $e)$employees[$e['employee_code']]=$e;
foreach($employees as $e) {
 if(!$conn->query("SELECT user_id FROM users WHERE employee_id=".(int)$e['employee_id']." AND role='Employee'")->num_rows) {
  $name=$conn->real_escape_string($e['first_name'].' '.$e['last_name']);$code=$conn->real_escape_string($e['employee_code']);$hash=password_hash('workflow-test',PASSWORD_DEFAULT);
  $conn->query("INSERT INTO users(employee_id,username,email,password_hash,full_name,role,is_active) VALUES (".(int)$e['employee_id'].",'workflow-$code','$code@example.invalid','$hash','$name','Employee',1)");
 }
}

$legacy=[];
foreach(['annual','quarterly'] as $kind){
 annualQuery($conn,"INSERT INTO {$kind}_evaluation_cycles(department_id,period_start,period_end,started_at) VALUES(7,'2024-01-01','2024-12-31',NOW())");$id=(int)$conn->insert_id;
 foreach($employees as $e)annualQuery($conn,"INSERT INTO {$kind}_evaluation_members(cycle_id,employee_id,position_id,reviewer_position_id,position_name) VALUES(?,?,?,?,?)",[$id,(int)$e['employee_id'],(int)$e['job_title_id'],null,$e['job_title']]);
 $legacy[$kind]=annualQuery($conn,"SELECT * FROM {$kind}_evaluation_cycles WHERE cycle_id=?",[$id])[0];
}
$staff=$employees['HRD-003'];$supervisorId=(int)$employees['HRD-002']['employee_id'];$managerId=(int)$employees['HRD-001']['employee_id'];
$headUser=(int)$conn->query("SELECT user_id FROM users WHERE employee_id=$supervisorId AND role='HR Supervisor'")->fetch_assoc()['user_id'];
$peerUser=(int)$conn->query("SELECT user_id FROM users WHERE employee_id=$managerId AND role='HR Manager'")->fetch_assoc()['user_id'];
$ownUser=(int)$conn->query('SELECT user_id FROM users WHERE employee_id='.(int)$staff['employee_id']." AND role='Employee'")->fetch_assoc()['user_id'];
$head=annualActor($conn,$headUser);$peer=annualActor($conn,$peerUser);
$signature='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=';
check(individualAssessmentDates($staff,['evaluation_type'=>'Annual','assessment_from'=>'2026-01-01','assessment_to'=>'2026-12-31'])===['2026-11-15','2026-12-31'],'November regularization stays in same Annual year');
check(individualAssessmentDates($staff,['evaluation_type'=>'Annual','assessment_from'=>'2027-01-01','assessment_to'=>'2027-12-31'])===['2027-01-01','2027-12-31'],'following Annual year covers January to December');
foreach(['annual','quarterly'] as $kind) {
 $type=ucfirst($kind);$from=$kind==='annual'?'2026-01-01':'2026-07-12';$to=$kind==='annual'?'2026-12-31':'2026-11-20';
 annualQuery($conn,"INSERT INTO evaluation_templates(template_name,target_department,target_department_id,evaluation_type,kra_weight,behavior_weight,status,created_by,individual_workflow,assessment_from,assessment_to) VALUES(?,?,?,?,80,20,'Active',?,1,?,?)",['Individual test '.$type,'Human Resources',(int)$staff['department_id'],$type,$headUser,$from,$to]);$template=(int)$conn->insert_id;
 annualQuery($conn,'INSERT INTO evaluation_template_positions(template_id,job_title_id) VALUES(?,?)',[$template,(int)$staff['job_title_id']]);
 for($i=0;$i<13;$i++)annualQuery($conn,"INSERT INTO evaluation_criteria(template_id,section,criterion_name,weight,scoring_method,sort_order) VALUES(?,'KRA',?,?,'Scale_1_4',?)",[$template,'KRA test '.($i+1),$i===12?4:8,$i]);
 foreach(annualCoreValues() as $i=>$value)annualQuery($conn,"INSERT INTO evaluation_criteria(template_id,section,criterion_name,kpi_description,weight,scoring_method,sort_order) VALUES(?,'Behavior',?,?,0,'Scale_1_4',?)",[$template,$value['name'],$value['kpi'],$i]);
 $t=annualQuery($conn,'SELECT * FROM evaluation_templates WHERE template_id=?',[$template])[0];[$start,$end]=individualAssessmentDates($staff,$t);
 $before=(int)annualQuery($conn,'SELECT COUNT(*) n FROM notifications')[0]['n'];individualNotifyTemplateAvailability($conn,$template);$once=(int)annualQuery($conn,'SELECT COUNT(*) n FROM notifications')[0]['n'];individualNotifyTemplateAvailability($conn,$template);check($once===(int)annualQuery($conn,'SELECT COUNT(*) n FROM notifications')[0]['n'] && $once>$before,"$type availability notifications are deduplicated");
 annualQuery($conn,"INSERT INTO evaluations(employee_id,template_id,evaluation_type,evaluation_period_start,evaluation_period_end,submitted_by,status,submitted_date) VALUES(?,?,?,?,?,?,'Pending Team Consolidation',NOW())",[(int)$staff['employee_id'],$template,$type,$start,$end,$ownUser]);$ev=(int)$conn->insert_id;
 annualQuery($conn,"INSERT INTO evaluation_scores(evaluation_id,criterion_id,score_value,weighted_score) SELECT ?,criterion_id,3,CASE WHEN section='KRA' THEN ROUND(3*weight/100,2) ELSE 0 END FROM evaluation_criteria WHERE template_id=?",[$ev,$template]);
 $conn->begin_transaction();
 annualQuery($conn,"UPDATE evaluations SET evaluation_period_start='2000-01-01' WHERE evaluation_id=?",[$ev]);
 try{($kind.'RegisterSubmission')($conn,$ev);throw new Exception('Tampered period accepted');}catch(DomainException $error){check(str_contains($error->getMessage(),'period'),"$type rejects tampered period");}finally{$conn->rollback();}
 $conn->begin_transaction();($kind.'RegisterSubmission')($conn,$ev);$conn->commit();$m=($kind.'Member')($conn,$ev);
 check((int)$m['individual_evaluation_id']===$ev,"$type submission auto-registers its own evaluation");
 check(($kind.'ExpectedPeers')($conn,(int)$m['cycle_id'],(int)$staff['employee_id'])===2,"$type includes active Regular department peers");
 check(($kind.'CanRateBeforeSelf')($conn,$peer,$m),"$type peer can rate without own self-rating");
 $outsider=$peer;$outsider['department_id']=999999;
 try{($kind.'SubmitPeers')($conn,$outsider,$ev,[3,3,3,3,3,3,3,3]);throw new Exception('Cross-department peer accepted');}catch(DomainException $e){check(true,"$type rejects cross-department rating");}
 $self=annualActor($conn,$ownUser);
 try{($kind.'SubmitPeers')($conn,$self,$ev,[3,3,3,3,3,3,3,3]);throw new Exception('Self-peer accepted');}catch(DomainException $e){check(true,"$type rejects self as peer");}

 $kraRatings=[];foreach(($kind.'KraRows')($conn,$ev) as $kr)$kraRatings[(int)$kr['score_id']]=(float)$kr['rating'];
 try{($kind.'Finalize')($conn,$head,$ev,$kraRatings,'',($kind.'ReviewToken')($conn,$m),[]);throw new Exception('Premature approval accepted');}catch(DomainException $e){check(str_contains($e->getMessage(),'raters'),"$type rejects approval before required ratings");}
 ($kind.'ReviewAndRate')($conn,$head,$ev,[],($kind.'ReviewToken')($conn,$m),[],$signature,[],'Checked evidence.');
 $m=($kind.'Member')($conn,$ev);check($m['evaluation_status']!=='Approved',"$type head review alone does not approve");
 ($kind.'ReturnEvaluation')($conn,$head,$ev,'Clarify the supporting evidence.');
 annualQuery($conn,"UPDATE evaluations SET status='Pending Team Consolidation',submitted_date=NOW() WHERE evaluation_id=?",[$ev]);
 $conn->begin_transaction();($kind.'RegisterSubmission')($conn,$ev);$conn->commit();
 check((int)annualQuery($conn,"SELECT COUNT(*) n FROM {$kind}_peer_submissions WHERE cycle_id=?",[(int)$m['cycle_id']])[0]['n']===0,"$type resubmission clears old ratings");
 $m=($kind.'Member')($conn,$ev);($kind.'ReviewAndRate')($conn,$head,$ev,[],($kind.'ReviewToken')($conn,$m),[],$signature,[],'Evidence clarified.');

 ($kind.'SubmitPeers')($conn,$peer,$ev,[4,3,3,2,4,3,3,3]);
 check(individualApprovalReady($conn,$kind,$m),"$type all required peer ratings complete");
 try{($kind.'SubmitPeers')($conn,$peer,$ev,[3,3,3,3,3,3,3,3]);throw new Exception('Duplicate peer accepted');}catch(DomainException $e){check(true,"$type locks duplicate peer ratings");}

 $m=($kind.'Member')($conn,$ev);
 check($m['evaluation_status']!=='Approved' && (int)$m['current_version']===0,"$type last peer waits for explicit head approval");
 check(($kind.'PendingReviewCount')($conn,$head)>0,"$type final approval appears in head pending count");
 $kraRatings=[];foreach(($kind.'KraRows')($conn,$ev) as $kr)$kraRatings[(int)$kr['score_id']]=(float)$kr['rating'];
 try{($kind.'Finalize')($conn,$peer,$ev,$kraRatings,'',($kind.'ReviewToken')($conn,$m),[]);throw new Exception('Peer approved result');}catch(DomainException $e){check(true,"$type peer cannot approve final result");}
 ($kind.'Finalize')($conn,$head,$ev,$kraRatings,'Final results checked.',($kind.'ReviewToken')($conn,$m),[]);
 $m=($kind.'Member')($conn,$ev);check($m['evaluation_status']==='Approved' && (int)$m['current_version']===1,"$type explicit head approval finalizes result");
 check((bool)annualQuery($conn,"SELECT completed_at FROM {$kind}_evaluation_cycles WHERE cycle_id=? AND completed_at IS NOT NULL",[(int)$m['cycle_id']]),"$type record archived without department approval");

 check(abs((float)$m['official_score']-3.01)<.001,"$type preserves 80/20 calculation");
 // A different Quarterly date range remains exactly what the head supplied.
 if($kind==='quarterly')check([$m['period_start'],$m['period_end']]===[$from,$to],'Quarterly dates are not forced into calendar quarters');
 echo "$type evaluation $ev template $template cycle {$m['cycle_id']} score {$m['official_score']}\n";
}

foreach($legacy as $kind=>$before){
 $after=annualQuery($conn,"SELECT * FROM {$kind}_evaluation_cycles WHERE cycle_id=?",[(int)$before['cycle_id']])[0];
 check($after===$before,ucfirst($kind).' legacy open cycle remains unchanged');
 check(($kind.'ExpectedPeers')($conn,(int)$before['cycle_id'],(int)$staff['employee_id'])===2,ucfirst($kind).' legacy peer-count rule preserved');
}
