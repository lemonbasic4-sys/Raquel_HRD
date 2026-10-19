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
    $submissionTitle='Annual self-rating ready for KRA review';
    $staffReviewLink=annualUrl($actors[3],'?view=review&evaluation_id=4');
    checkAnnual(count(annualQuery($conn,'SELECT notification_id FROM notifications WHERE user_id=3 AND title=? AND link=?',[$submissionTitle,$staffReviewLink]))===1,
        'HR Staff Annual submission immediately notifies the configured HR Supervisor before peers complete');
    checkAnnual(!annualQuery($conn,'SELECT notification_id FROM notifications WHERE user_id=2 AND title=? AND link LIKE ?',[$submissionTitle,'%evaluation_id=4']),
        'staff Annual submission notification does not bypass the immediate head to HR Manager');
    checkAnnual(annualPendingReviewCount($conn,$actors[3])===2,
        'Performance Evaluation badge counts submitted direct-report Annual reviews');
    checkAnnual(count(annualQuery($conn,'SELECT member_id FROM annual_evaluation_members WHERE cycle_id=?',[$cycle]))===4,'department roster supports four members and multiple people in one position');
    checkAnnual((int)annualMember($conn,5)['cycle_id']!==$cycle,'same dates in a different department create a separate cycle');
    checkAnnual(annualCanFinalize($conn,$actors[3],$m),'HR Supervisor finalizes HR Staff');
    checkAnnual(!annualCanFinalize($conn,$actors[2],$m),'HR Manager cannot bypass the configured immediate head');
    checkAnnual(annualCanFinalize($conn,$actors[2],annualMember($conn,3)),'HR Manager finalizes HR Supervisor');
    checkAnnual(annualCanFinalize($conn,$actors[1],annualMember($conn,2)),'President/CEO finalizes HR Manager across departments');
    checkAnnual(!annualCanFinalize($conn,$actors[2],annualMember($conn,2)),'a head cannot finalize their own evaluation');
    rejectsAnnual(fn()=>annualSubmitPeers($conn,$actors[4],4,array_fill(0,8,3)),'self peer-rating is rejected');
    rejectsAnnual(fn()=>annualSubmitPeers($conn,$actors[5],4,array_fill(0,8,3)),'cross-department peer-rating is rejected');
    rejectsAnnual(fn()=>annualSubmitPeers($conn,$actors[7],3,array_fill(0,8,3)),'HRD Employee account cannot submit HRIS-only peer ratings');
    foreach ([[],array_fill(0,7,3),array_fill(0,9,3),array_fill(0,8,5),array_fill(0,8,'NaN'),array_fill(0,8,3.141)] as $bad) {
        rejectsAnnual(fn()=>annualValidateScores($bad),'invalid/missing/out-of-scale peer scores are rejected');
    }
    $seventeen=annualCalculate(array_map(static fn($w,$r)=>['weight'=>$w,'rating'=>$r],
        [5,10,5,5,5,20,10,2,3,5,3,2,5,5,5,5,5],[2,3,4,3,4,4,4,4,3,3,2,4,2,4,4,3,2]),[4,4,3,2,2,3,3,4],[]);
    checkAnnual($seventeen['kra']===3.36 && $seventeen['core']===3.13 && $seventeen['total']===3.31,'17-item Excel example reproduces 3.36 KRA, 3.13 Core and 3.31 total');
    rejectsAnnual(fn()=>annualValidateTemplateKras([],[]),'Annual template requires KRA items');
    rejectsAnnual(fn()=>annualValidateTemplateKras(['One'],[99]),'Annual template requires KRA item weights totaling 100%');
    rejectsAnnual(fn()=>annualValidateTemplateKras([''],[100]),'Annual template cannot publish an unnamed KRA');
    foreach ([2,3,4] as $rater) foreach ([2,3,4,6] as $target) {
        if ($rater===$target) continue;
        annualSubmitPeers($conn,$actors[$rater],$target,array_fill(0,8,$rater===2 ? 4.0 : 3.0));
    }
    rejectsAnnual(fn()=>annualSubmitPeers($conn,$actors[2],4,array_fill(0,8,1)),'submitted peer ratings cannot be edited or submitted twice');
    checkAnnual(!array_key_exists('rater_name',annualPeers($conn,$m)[0]),'employee response does not fetch named rater data');
    checkAnnual(array_key_exists('rater_name',annualPeers($conn,$m,true)[0]),'authorized consolidation response can fetch rater names');
    checkAnnual(!annualCanSeeNames($conn,$actors[2],annualMember($conn,2)),'HR Manager cannot see raters of their own evaluation');
    checkAnnual(!annualCanSeeNames($conn,$actors[3],annualMember($conn,2))
        && !annualCanSeeNames($conn,$actors[2],annualMember($conn,4)),
        'same-department HR role does not grant access to non-direct-report consolidation or named ratings');
    $candidate=annualCandidate($conn,$m);
    checkAnnual($candidate['core']===3.67,'individual combined score includes self exactly once plus each received peer rating');
    $other=annualCandidate($conn,annualMember($conn,3));
    checkAnnual($candidate['core']!==$other['core'],'different employees retain different behavior averages');
    $ratings=[];foreach (annualKraRows($conn,4) as $row) $ratings[$row['score_id']]=$row['rating'];
    rejectsAnnual(fn()=>annualFinalize($conn,$actors[2],4,$ratings,'Review',annualReviewToken($conn,$m)),'unauthorized finalization is rejected');
    rejectsAnnual(fn()=>annualFinalize($conn,$actors[3],4,$ratings,'Attempt with explanation',annualReviewToken($conn,$m)),'incomplete participation blocks finalization even with review remarks');
    checkAnnual(annualKraRows($conn,4)[0]['rating']==3.0,'failed finalization rolls back KRA updates');
    $adjusted=$ratings;
    $firstKra=(int)array_key_first($adjusted);
    $adjusted[$firstKra]=3.5;
    rejectsAnnual(fn()=>annualApproveKra($conn,$actors[2],4,$adjusted,'Review',annualReviewToken($conn,$m)),
        'non-immediate head cannot approve KRA independently');
    rejectsAnnual(fn()=>annualApproveKra($conn,$actors[3],4,$adjusted,'',annualReviewToken($conn,$m)),
        'KRA adjustments require remarks and rollback on failure');
    annualApproveKra($conn,$actors[3],4,$adjusted,'Validated job output.',annualReviewToken($conn,$m));
    $approvedKra=annualKraRows($conn,4)[0];
    checkAnnual((float)$approvedKra['rating']===3.5 && (float)$approvedKra['self_rating']===3.0,
        'immediate head can change and approve KRA while preserving original self-rating');
    checkAnnual((int)annualMember($conn,4)['current_version']===0
        && annualCandidate($conn,$m)['peer_count']<annualExpectedPeers($conn,$cycle,4)
        && count(annualQuery($conn,'SELECT approval_id FROM annual_kra_approvals WHERE member_id=?',[(int)$m['member_id']]))===1,
        'KRA approval is recorded without finalizing Annual or requiring completed peer ratings');
    rejectsAnnual(fn()=>annualFinalize($conn,$actors[3],4,$adjusted,'KRA already approved',annualReviewToken($conn,$m)),
        'approved KRA does not bypass the full-participation finalization guard');
    annualApproveKra($conn,$actors[3],4,$ratings,'Rechecked original KRA.',annualReviewToken($conn,$m));
    annualSubmitPeers($conn,$actors[6],4,array_fill(0,8,3.67));
    $stale=annualReviewToken($conn,$m);
    annualFinalize($conn,$actors[3],4,$ratings,'All expected raters submitted; adjusted behavior scores during consolidation.',$stale,array_fill(0,8,3.5));
    $official=annualMember($conn,4);
    $firstSnapshot=json_decode(annualQuery($conn,'SELECT snapshot_json FROM annual_evaluation_versions WHERE member_id=? AND version_number=1',[(int)$official['member_id']])[0]['snapshot_json'],true);
    checkAnnual((int)$official['current_version']===1 && (float)$official['official_score']===3.34
        && $firstSnapshot['core']===3.5 && $firstSnapshot['core_adjustments']===array_fill(0,8,3.5),
        'immediate-head Core Values adjustments affect and persist in the finalized score version');
    checkAnnual((bool)annualQuery($conn,"SELECT notification_id FROM notifications WHERE user_id=4 AND title='Annual evaluation finalized' AND link LIKE ?",['%evaluation_id=4']),
        'employee receives an Annual finalization notification');
    $stale=annualReviewToken($conn,$official);
    annualQuery($conn,"INSERT INTO employees (employee_id,employee_code,first_name,last_name,hire_date,job_title_id,job_title,department_id,employment_status) VALUES (8,'TEST-8','Late','Joiner','2020-01-01',4,'HR Staff',1,'Regular')");
    annualQuery($conn,"INSERT INTO users (user_id,employee_id,username,email,password_hash,full_name,role) VALUES (8,8,'late','late@example.invalid','unused','Late Joiner','HR Staff')");
    $makeEvaluation(8,4,8);
    annualSubmitPeers($conn,annualActor($conn,8),4,array_fill(0,8,2.0));
    $revised=annualMember($conn,4);
    checkAnnual((int)$revised['revision_pending']===1 && (float)$revised['official_score']===3.34,'late submission flags revision without changing the official result');
    checkAnnual(annualCandidate($conn,$revised)['total']===3.31,'late peer data produces a separate revised candidate');
    $late=annualQuery($conn,'SELECT is_late FROM annual_peer_submissions WHERE cycle_id=? AND rater_employee_id=8 AND target_employee_id=4',[$cycle])[0];
    checkAnnual((int)$late['is_late']===1,'late is determined by first finalized batch rather than a calendar cutoff');
    rejectsAnnual(fn()=>annualFinalize($conn,$actors[3],4,$ratings,'Late review',$stale),'stale review token cannot approve unseen late ratings');
    annualFinalize($conn,$actors[3],4,$ratings,'Approved revised 360 result.',annualReviewToken($conn,$revised));
    $final=annualMember($conn,4);
    checkAnnual((int)$final['current_version']===2 && !$final['revision_pending'] && (float)$final['official_score']===3.31,'immediate-head re-finalization makes revised score current');
    $history=annualQuery($conn,'SELECT snapshot_json FROM annual_evaluation_versions WHERE member_id=? ORDER BY version_number',[(int)$final['member_id']]);
    checkAnnual(count($history)===2 && json_decode($history[0]['snapshot_json'],true)['total']===3.34,'previous finalized score is preserved in immutable version history');
    rejectsAnnual(fn()=>annualFinalize($conn,$actors[3],4,$ratings,'Repeat',annualReviewToken($conn,$final)),'an unchanged finalized result cannot be finalized repeatedly');
    annualQuery($conn,'UPDATE employees SET department_id=3 WHERE employee_id=6');
    rejectsAnnual(fn()=>annualSubmitPeers($conn,annualActor($conn,6),3,array_fill(0,8,3)),'a transfer cannot silently move the old department peer task');
    annualQuery($conn,'UPDATE employees SET department_id=1 WHERE employee_id=6');
    checkAnnual(count(annualQuery($conn,'SELECT member_id FROM annual_evaluation_members WHERE cycle_id=?',[$cycle]))===5,'late self-rating can add another eligible participant without a fixed department size');
    annualSubmitPeers($conn,$actors[2],8,array_fill(0,8,4));
    checkAnnual(count(annualPeers($conn,annualMember($conn,8)))===1,'a rater can rate a late newcomer without editing their prior locked submissions');
    checkAnnual($conn->query('SELECT COUNT(*) n FROM annual_evaluation_versions')->fetch_assoc()['n']==2,'late newcomer registration leaves earlier finalized versions intact');
    function renderAnnualFixture(int $userId,string $role,string $portal,int $evaluationId,string $view): string {
        global $conn;
        $_SESSION=['user_id'=>$userId,'employee_id'=>$userId===7 ? 4 : $userId,'role'=>$role,'full_name'=>'Test User','branch_id'=>0];
        $_SERVER['REQUEST_METHOD']='GET';
        $annual_in_performance_page=in_array($role,['HR Manager','HR Supervisor'],true);
        $_SERVER['SCRIPT_NAME']=$annual_in_performance_page
            ? BASE_URL.'/employee/team-evaluation-packages.php'
            : BASE_URL.'/'.$portal.'/annual-evaluations.php';
        $_SERVER['PHP_SELF']=$_SERVER['SCRIPT_NAME'];
        $_SERVER['REQUEST_URI']=$_SERVER['SCRIPT_NAME'].'?view='.$view.'&evaluation_id='.$evaluationId;
        $_GET=['view'=>$view,'evaluation_id'=>$evaluationId]; $_POST=[];
        ob_start(); include __DIR__.'/../includes/annual-evaluation-page.php'; return ob_get_clean();
    }
    $noticeCount=(int)$conn->query('SELECT COUNT(*) n FROM notifications')->fetch_assoc()['n'];
    $html=renderAnnualFixture(3,'HR Supervisor','supervisor',8,'rate');
    checkAnnual(strpos($html,'Performance Evaluation Process')!==false
        && strpos($html,'name="action" value="rate"')!==false
        && strpos(annualUrl(['role'=>'HR Supervisor'],'?view=peers'),'team-evaluation-packages.php?category=regular&view=peers')!==false,
        'HR Annual workflow uses Performance Evaluation while preserving peer actions');
    preg_match('/<form[^>]*id="annualRatingForm"[^>]*>(.*?)<\/form>/s',$html,$formHtml);
    checkAnnual(isset($formHtml[1]) && substr_count($formHtml[1],'name="scores[')===8
        && strpos($formHtml[1],'name="kra[')!==false && strpos($formHtml[1],'type="radio"')===false,
        'immediate-head rating page combines KRA inputs with eight numeric Core Values fields');
    checkAnnual(substr_count($formHtml[1],'max="4"')>=10 && strpos($html,'Number(input.value)>4')!==false,
        'KRA and Core Values inputs cap ratings at 4 while the server rejects out-of-range peer scores');
    preg_match('/<table class="table align-middle annual-rating-table annual-score-table">(.*?)<\/table>/s',$formHtml[1],$behaviorRatingTable);
    checkAnnual(isset($behaviorRatingTable[1]) && strpos($behaviorRatingTable[1],'<th class="text-end">Weight</th>')===false
        && strpos($behaviorRatingTable[1],'2.50%')===false,
        'Core Behaviors 360 rating table omits its per-item Weight column');
    checkAnnual(strpos($formHtml[1],'data-bs-target="#annualRateKraPanel"')!==false
          && strpos($formHtml[1],'data-bs-target="#annualRateCorePanel"')!==false
          && strpos($formHtml[1],'data-bs-target="#annualRateReviewPanel"')!==false
          && strpos($formHtml[1],'id="annualRatingPreviewKraRows"')!==false
          && strpos($formHtml[1],'id="annualRatingPreviewBehaviorRows"')!==false
          && strpos($formHtml[1],'id="annualRateKraNext"')!==false
          && strpos($formHtml[1],'id="annualRateCoreBack"')!==false
          && strpos($formHtml[1],'id="annualRateCoreNext"')!==false
          && strpos($formHtml[1],'id="annualRateReviewBack"')!==false
          && strpos($formHtml[1],'id="annualRaterSignatureCanvas"')!==false
          && strpos($formHtml[1],'name="rater_signature_data"')!==false
          && strpos($formHtml[1],'Employee self-rating')!==false
          && strpos($formHtml[1],'leave the submitted ratings unchanged; the system will retain them as-is')!==false,
          'immediate-head KRA review, self-rating comparison, 360 Core Values rating, and review/submit are shown in tabs');
    checkAnnual(strpos($html,'id="annualRatingSummary"')!==false
        && strpos($html,'Are you sure you want to submit?')!==false
        && strpos($html,'This can’t be undone.')!==false
        && strpos($html,'Yes, submit and lock')!==false
        && strpos($html,'id="annualSummaryKraRows"')!==false
        && strpos($html,'id="annualSummaryBehaviorRows"')!==false,
        'confirmation separately summarizes KRA and behavior ratings and states submission cannot be undone');
    checkAnnual(strpos($html,'data-draft-key="annual-rating-3-8"')!==false
        && strpos($html,'sessionStorage.setItem(annualDraftKey')!==false
        && strpos($html,'sessionStorage.removeItem(annualDraftKey)')!==false
        && strpos($html,'activePanel')!==false
        && strpos($html,'document.addEventListener(\'DOMContentLoaded\',restoreAnnualRatingDraft')!==false
        && strpos($html,'saveAnnualRatingDraft(trigger.dataset.bsTarget)')!==false,
        'rating drafts and active review tab survive refresh until submission is completed');
    annualQuery($conn,'UPDATE annual_evaluation_members SET evaluation_id=NULL WHERE cycle_id=? AND employee_id=3',[$cycle]);
    $awaitingHeadHtml=renderAnnualFixture(3,'HR Supervisor','supervisor',0,'peers');
    annualQuery($conn,'UPDATE annual_evaluation_members SET evaluation_id=3 WHERE cycle_id=? AND employee_id=3',[$cycle]);
    checkAnnual(strpos($awaitingHeadHtml,'Self-rating required')!==false
        && strpos($awaitingHeadHtml,'Submit your Annual self-rating first to unlock peer ratings.')!==false,
        'peer action clearly explains that a head must submit their self-rating before Rate & review unlocks');
    $html=renderAnnualFixture(7,'Employee','employee',4,'results');
    checkAnnual(strpos($html,'Employee 1')!==false && strpos($html,'Test2 Employee')===false && strpos($html,'Test3 Employee')===false,'employee result HTML anonymizes peers and contains no rater names');
    $html=renderAnnualFixture(3,'HR Supervisor','supervisor',4,'review');
    checkAnnual(strpos($html,'Test2 Employee')!==false && strpos($html,'Version 2')!==false,'authorized consolidation HTML includes named ratings and previous result versions');
    $managerView=renderAnnualFixture(3,'HR Supervisor','supervisor',2,'review');
    checkAnnual(strpos($managerView,'Core Behaviors &amp; Values')!==false
        && strpos($managerView,'Key Result Areas (KRA)')===false
        && strpos($managerView,'Consolidation &amp; Final Score')===false
        && strpos($managerView,'Employee 1')!==false && strpos($managerView,'Test3 Employee')===false,
        'HR Supervisor sees only anonymous HR Manager behavior results and cannot change or finalize them');
    $headView=renderAnnualFixture(2,'HR Manager','manager',3,'review');
    checkAnnual(strpos($headView,'name="core_adjustments[0]"')!==false
        && strpos($headView,'name="kra[')!==false,
        'immediate-head consolidation allows KRA and Core Values adjustments together');
    checkAnnual((int)$conn->query('SELECT COUNT(*) n FROM notifications')->fetch_assoc()['n']===$noticeCount,'viewing rating and result pages does not generate duplicate notifications');
    $incompleteHtml=renderAnnualFixture(2,'HR Manager','manager',3,'review');
    checkAnnual((bool)preg_match('/<button[^>]*type="submit"[^>]*disabled[^>]*>Approve &amp; finalize Annual evaluation<\/button>/', $incompleteHtml),
        'review disables finalization while expected peer ratings are missing');
    checkAnnual(strpos($incompleteHtml,'value="approve_kra"')===false
        && strpos($incompleteHtml,'<label for="annualKraRemarks"')===false
        && strpos($incompleteHtml,'Consolidation &amp; Final Score')!==false,
        'consolidation uses a single final approval action without a separate KRA-only approval section');
    checkAnnual(strpos($incompleteHtml,'Finalized versions')===false && strpos($incompleteHtml,'No finalized version yet.')===false,
        'empty finalized-version history is hidden until a version exists');
    $progressHtml=renderAnnualFixture(3,'HR Supervisor','supervisor',0,'list');
    preg_match('/id="annualRoster"[^>]*>.*?<tbody>(.*?)<\/tbody>/s',$progressHtml,$progressRows);
    checkAnnual(isset($progressRows[1]) && strpos($progressRows[1],'Test4 Employee')!==false
        && strpos($progressRows[1],'Test2 Employee')!==false && strpos($progressRows[1],'Test3 Employee')!==false,
        'Annual default and former progress entry merge into the department peer list');
    $peerHtml=renderAnnualFixture(3,'HR Supervisor','supervisor',0,'peers');
    preg_match('/id="annualRoster"[^>]*>.*?<tbody>(.*?)<\/tbody>/s',$peerHtml,$peerRows);
    checkAnnual(isset($peerRows[1]) && strpos($peerRows[1],'Test2 Employee')!==false
        && strpos($peerRows[1],'Test3 Employee')!==false && strpos($peerRows[1],'Test4 Employee')!==false,
        '360 peer list still includes all department participants including the rater');
    preg_match_all('/<tr[^>]*>.*?<\/tr>/s',$peerRows[1] ?? '',$allPeerRows);
    $staffPeerRow=array_values(array_filter($allPeerRows[0] ?? [],static fn($row)=>strpos($row,'Test4 Employee')!==false));
    checkAnnual(count($staffPeerRow)===1 && substr_count($staffPeerRow[0], 'class="btn btn-sm')===1
        && strpos($staffPeerRow[0],'View submitted review')!==false
        && strpos($staffPeerRow[0],'view=head-review')!==false
        && strpos($staffPeerRow[0],'Consolidation &amp; result')===false,
        'a finalized direct report has one action to reopen the exact submitted head review');
    $submittedReviewRow=array_values(array_filter($allPeerRows[0] ?? [],static fn($row)=>strpos($row,'Test6 Employee')!==false));
    checkAnnual(count($submittedReviewRow)===1 && strpos($submittedReviewRow[0],'View submitted review')!==false
        && strpos($submittedReviewRow[0],'view=head-review')!==false
        && strpos($submittedReviewRow[0],'Consolidation &amp; result')===false,
        'a submitted head review that awaits Annual finalization opens the submitted review, not the retired consolidation page');
    $headReviewRows=[]; foreach (annualKraRows($conn,8) as $row) $headReviewRows[$row['score_id']]=$row['rating'];
    $headReviewRows[array_key_first($headReviewRows)]=3.25;
    $raterSignature='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/6wAAAABJRU5ErkJggg==';
    rejectsAnnual(fn()=>annualReviewAndRate($conn,$actors[3],8,$headReviewRows,annualReviewToken($conn,annualMember($conn,8)),array_fill(0,8,3.5),''),
        'immediate-head combined review requires a rater signature');
    annualReviewAndRate($conn,$actors[3],8,$headReviewRows,annualReviewToken($conn,annualMember($conn,8)),array_fill(0,8,3.5),$raterSignature);
    checkAnnual((float)annualKraRows($conn,8)[0]['rating']===3.25
        && (annualQuery($conn,'SELECT rater_signature_data,rater_signed_at FROM annual_peer_submissions WHERE cycle_id=? AND rater_employee_id=3 AND target_employee_id=8',[$cycle])[0]['rater_signature_data'] ?? '')===$raterSignature,
        'immediate head can submit adjusted KRA and 360 ratings with a saved printable rater signature');
    $headReviewHtml=renderAnnualFixture(3,'HR Supervisor','supervisor',8,'head-review');
    checkAnnual(strpos($headReviewHtml,'Immediate-head submission · View only')!==false
        && strpos($headReviewHtml,'Head-reviewed rating')!==false
        && strpos($headReviewHtml,'Head\'s submitted rating')!==false
        && strpos($headReviewHtml,'src="'.$raterSignature.'"')!==false
        && strpos($headReviewHtml,'Provisional overall score · Head submission')!==false
        && strpos($headReviewHtml,'annual-head-review-score-icon')!==false
        && strpos($headReviewHtml,'KRA <b>80%</b></span><strong>3.48</strong>')!==false
        && strpos($headReviewHtml,'Behavior <b>20%</b></span><strong>3.50</strong>')!==false
        && strpos($headReviewHtml,'<strong>3.48</strong><small>Out of 4.00</small>')!==false
        && strpos($headReviewHtml,'name="kra[')===false
        && strpos($headReviewHtml,'name="scores[')===false,
        'head can reopen the exact read-only submission with reviewed KRAs, 360 ratings and saved signature');
    $headSnapshotCandidate=annualCandidate($conn,annualMember($conn,8));
    annualSubmitPeers($conn,$actors[4],8,array_fill(0,8,1.5));
    $liveCandidateAfterPeer=annualCandidate($conn,annualMember($conn,8));
    $headReviewAfterPeer=renderAnnualFixture(3,'HR Supervisor','supervisor',8,'head-review');
    checkAnnual($headSnapshotCandidate['core']!==$liveCandidateAfterPeer['core']
        && strpos($headReviewAfterPeer,'3.50')!==false
        && strpos($headReviewAfterPeer,'1.50')===false
        && strpos($headReviewAfterPeer,'Combined average')===false,
        'later peer submissions change the live candidate but never alter the head review snapshot');
    checkAnnual(strpos(file_get_contents(__DIR__.'/../manager/print-evaluation.php'),'annual_rater_submission.rater_signature_data')!==false,
        'traditional printed evaluation includes the immediate head Annual rater signature');
    $request=function(string $case) use ($testDb): string {
        $process=proc_open([PHP_BINARY,__DIR__.'/annual-request.php',$testDb,$case],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if (!is_resource($process)) throw new RuntimeException('Cannot launch fixture request.');
        $output=stream_get_contents($pipes[1]); $errors=stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); $code=proc_close($process);
        if ($code!==0 || $errors!=='') throw new RuntimeException('Controller request failed: '.$errors.$output);
        return $output;
    };
    $request('template');
    $template=annualQuery($conn,"SELECT * FROM evaluation_templates WHERE template_name='Annual test created through controller'")[0] ?? null;
    checkAnnual($template && (float)$template['kra_weight']===80.0 && (float)$template['behavior_weight']===20.0,'template controller enforces Annual 80/20 weights');
    $values=annualQuery($conn,"SELECT criterion_name FROM evaluation_criteria WHERE template_id=? AND section='Behavior' ORDER BY sort_order",[(int)$template['template_id']]);
    checkAnnual(array_column($values,'criterion_name')===array_column(annualCoreValues(),'name'),'template controller enforces the standard eight values despite a tampered form');
    $request('submit');
    $year=annualQuery($conn,"SELECT ev.evaluation_id,m.member_id FROM evaluations ev LEFT JOIN annual_evaluation_members m ON m.evaluation_id=ev.evaluation_id WHERE ev.employee_id=4 AND ev.evaluation_type='Annual' AND ev.evaluation_period_start='2027-01-01'");
    checkAnnual(count($year)===1 && $year[0]['member_id']!==null,'existing self-rating controller registers the next Annual period in the 360 workflow');
    checkAnnual(!annualQuery($conn,'SELECT 1 FROM evaluation_package_members WHERE evaluation_id=?',[(int)$year[0]['evaluation_id']]),'Annual self-rating bypasses the old department/shared-score package pipeline');
    $request('submit');
    checkAnnual((int)$conn->query("SELECT COUNT(*) n FROM evaluations WHERE employee_id=4 AND evaluation_period_start='2027-01-01'")->fetch_assoc()['n']===1,'self-rating controller prevents duplicate submissions for the same Annual period');
    $blocked=$request('legacy');
    checkAnnual(strpos($blocked,'Annual & 360 Ratings')!==false && (float)annualMember($conn,4)['official_score']===3.31,'legacy override endpoint cannot bypass Annual versioned finalization');
    $blocked=$request('csrf');
    checkAnnual(stripos($blocked,'csrf')!==false,'self-rating POST rejects an invalid CSRF token');
    foreach ([1=>'Executive',2=>'Vice President',3=>'Manager',4=>'Supervisor',5=>'Rank and File'] as $id=>$name) annualQuery($conn,'INSERT INTO rank_categories (rank_category_id,rank_name,level_order) VALUES (?,?,?)',[$id,$name,$id]);
    annualQuery($conn,'UPDATE job_titles SET rank_category_id=CASE job_title_id WHEN 1 THEN 1 WHEN 2 THEN 3 WHEN 3 THEN 4 ELSE 5 END');
    annualQuery($conn,'UPDATE employees e JOIN job_titles j ON j.job_title_id=e.job_title_id SET e.rank_category_id=j.rank_category_id');
    annualQuery($conn,"INSERT INTO employees (employee_id,employee_code,first_name,last_name,hire_date,job_title_id,job_title,rank_category_id,department_id,reports_to,employment_status) VALUES (9,'TEST-PROB','Probationary','Fixture','2026-09-01',4,'HR Staff',5,1,3,'Probationary')");
    annualQuery($conn,"INSERT INTO users (user_id,employee_id,username,email,password_hash,full_name,role) VALUES (9,9,'probationary','probationary@example.invalid','unused','Probationary Fixture','Employee')");
    annualQuery($conn,"INSERT INTO evaluation_templates (template_id,template_name,target_department,target_department_id,target_job_title_id,evaluation_type,status,created_by) VALUES (9,'Probationary regression','Human Resources',1,4,'Initial','Active',3)");
    annualQuery($conn,'INSERT INTO evaluation_template_positions (template_id,job_title_id) VALUES (9,4)');
    annualQuery($conn,'INSERT INTO evaluation_criteria (template_id,section,criterion_name,kpi_description,weight,sort_order) SELECT 9,section,criterion_name,kpi_description,weight,sort_order FROM evaluation_criteria WHERE template_id=4');
    $request('probationary');
    $prob=annualQuery($conn,"SELECT evaluation_id FROM evaluations WHERE employee_id=9 AND evaluation_type='Initial'")[0] ?? null;
    checkAnnual($prob && annualQuery($conn,'SELECT package_id FROM evaluation_package_members WHERE evaluation_id=?',[(int)$prob['evaluation_id']]),'probationary Initial submission still enters the existing review package workflow');
    checkAnnual(!annualUsesPeerWorkflow($conn,(int)$prob['evaluation_id']),'probationary Initial is not registered in the Annual 360 workflow');
    $expectedBefore=annualExpectedPeers($conn,$cycle,4);
    $conn->begin_transaction();
    annualQuery($conn,"UPDATE employees SET employment_status='Death',is_active=0 WHERE employee_id=6");
    annualQuery($conn,'UPDATE annual_evaluation_members SET evaluation_id=NULL,self_values_json=NULL WHERE cycle_id=? AND employee_id=6',[$cycle]);
    $visible=annualQuery($conn,'SELECT m.employee_id FROM annual_evaluation_members m JOIN employees e ON e.employee_id=m.employee_id WHERE m.cycle_id=? AND '.annualRosterEligibilitySql(),[$cycle]);
    checkAnnual(!in_array(6,array_map('intval',array_column($visible,'employee_id')),true),'death before self-rating removes employee from the active peer list');
    checkAnnual(annualExpectedPeers($conn,$cycle,4)===$expectedBefore-1,'death before self-rating does not block the cycle as an expected missing rater');
    checkAnnual((bool)annualQuery($conn,'SELECT member_id FROM annual_evaluation_members WHERE cycle_id=? AND employee_id=6',[$cycle]),'excluding an unsubmitted separated employee preserves the original roster record');
    $conn->rollback();
    $conn->begin_transaction();
    annualQuery($conn,"UPDATE employees SET employment_status='Death',is_active=0 WHERE employee_id=6");
    checkAnnual(annualExpectedPeers($conn,$cycle,4)===$expectedBefore && annualMember($conn,6)!==null,'separation after submission preserves the submitted evaluation for HR review');
    $conn->rollback();
    foreach (['AWOL','Retirement','Death','Permanent or Total Disability','Resignation','Failed in Training','Termination for Cause','Others'] as $reason) {
        $conn->begin_transaction();
        annualQuery($conn,'UPDATE employees SET employment_status=?,is_active=0 WHERE employee_id=6',[$reason==='Others' ? 'Separated' : $reason]);
        annualQuery($conn,'UPDATE annual_evaluation_members SET evaluation_id=NULL,self_values_json=NULL WHERE cycle_id=? AND employee_id=6',[$cycle]);
        $visible=annualQuery($conn,'SELECT m.employee_id FROM annual_evaluation_members m JOIN employees e ON e.employee_id=m.employee_id WHERE m.cycle_id=? AND '.annualRosterEligibilitySql(),[$cycle]);
        checkAnnual(!in_array(6,array_map('intval',array_column($visible,'employee_id')),true)
            && annualExpectedPeers($conn,$cycle,4)===$expectedBefore-1,
            $reason.' before self-rating is excluded from active peers and expected participation');
        $conn->rollback();
    }
    $importHrdAnnualSeed=static function() use ($conn): void {
        $sql=file_get_contents(__DIR__.'/../eval_test_seeds/hrd_annual_evaluation_templates.sql');
        $sql=preg_replace('/^--.*$/m','',$sql);
        foreach (explode(';',$sql) as $statement) {
            if (trim($statement)==='') continue;
            $result=$conn->query($statement);
            if ($result instanceof mysqli_result) $result->free();
        }
    };
    $importHrdAnnualSeed();
    $seeded=annualQuery($conn,"SELECT template_id,created_by,kra_weight,behavior_weight FROM evaluation_templates WHERE template_name LIKE '[TEST HRD ANNUAL] %' ORDER BY template_id");
    checkAnnual(count($seeded)===3,'optional HRD seed creates CEO-to-manager, manager-to-supervisor and supervisor-to-staff Annual templates');
    foreach ($seeded as $t) {
        $criteria=annualQuery($conn,'SELECT * FROM evaluation_criteria WHERE template_id=? ORDER BY section,sort_order',[(int)$t['template_id']]);
        $kras=array_values(array_filter($criteria,static fn($c)=>$c['section']==='KRA'));
        $values=array_values(array_filter($criteria,static fn($c)=>$c['section']==='Behavior'));
        checkAnnual(count($kras)===4 && array_sum(array_column($kras,'weight'))==100
            && array_column($values,'criterion_name')===array_column(annualCoreValues(),'name')
            && (float)$t['kra_weight']===80.0 && (float)$t['behavior_weight']===20.0,
            'seeded Annual template supplies valid sample KRAs, canonical eight values and 80/20 weights');
        $badTargets=annualQuery($conn,'SELECT 1 FROM evaluation_template_positions tp JOIN job_titles j ON j.job_title_id=tp.job_title_id JOIN users u ON u.user_id=? JOIN employees e ON e.employee_id=u.employee_id WHERE tp.template_id=? AND NOT (j.reports_to <=> e.job_title_id)',[(int)$t['created_by'],(int)$t['template_id']]);
        checkAnnual(!$badTargets,'seeded Annual target positions follow their creator reporting line');
    }
    $criterionIds=annualQuery($conn,"SELECT ec.criterion_id FROM evaluation_criteria ec JOIN evaluation_templates t ON t.template_id=ec.template_id WHERE t.template_name LIKE '[TEST HRD ANNUAL] %' ORDER BY ec.criterion_id");
    $importHrdAnnualSeed();
    checkAnnual($criterionIds===annualQuery($conn,"SELECT ec.criterion_id FROM evaluation_criteria ec JOIN evaluation_templates t ON t.template_id=ec.template_id WHERE t.template_name LIKE '[TEST HRD ANNUAL] %' ORDER BY ec.criterion_id"),
        're-importing optional HRD seed preserves existing template criteria and IDs');
    $reminderTitle='Probationary Initial Evaluation Due Soon';
    $reminderLink=BASE_URL.'/staff/view-employee.php?id=9';
    createProbationReminderOnce($conn,4,$reminderTitle,'Fixture reminder due on Oct 20, 2026.',$reminderLink);
    createProbationReminderOnce($conn,4,$reminderTitle,'Fixture reminder due on Oct 20, 2026.',$reminderLink);
    checkAnnual(count(annualQuery($conn,'SELECT notification_id FROM notifications WHERE user_id=4 AND title=? AND message=?',[$reminderTitle,'Fixture reminder due on Oct 20, 2026.']))===1,
        'repeated portal reminder generation delivers the same milestone only once');
    annualQuery($conn,'DELETE FROM notifications WHERE user_id=4 AND title=? AND message=?',[$reminderTitle,'Fixture reminder due on Oct 20, 2026.']);
    createProbationReminderOnce($conn,4,$reminderTitle,'Fixture reminder due on Oct 20, 2026.',$reminderLink);
    checkAnnual(!annualQuery($conn,'SELECT notification_id FROM notifications WHERE user_id=4 AND title=? AND message=?',[$reminderTitle,'Fixture reminder due on Oct 20, 2026.']),
        'deleted automatic reminder does not reappear when the portal is visited again');
    createProbationReminderOnce($conn,4,'Probationary Initial Evaluation Overdue','Fixture reminder due on Oct 20, 2026.',$reminderLink);
    checkAnnual(count(annualQuery($conn,'SELECT notification_id FROM notifications WHERE user_id=4 AND title=? AND message=?',['Probationary Initial Evaluation Overdue','Fixture reminder due on Oct 20, 2026.']))===1,
        'a new overdue milestone can notify after an earlier reminder was deleted');
    createNotification($conn,4,'Probationary Final Evaluation Due Soon','Legacy fixture reminder',$reminderLink);
    createProbationReminderOnce($conn,4,'Probationary Final Evaluation Due Soon','Legacy fixture reminder',$reminderLink);
    checkAnnual(count(annualQuery($conn,'SELECT notification_id FROM notifications WHERE user_id=4 AND title=? AND message=?',['Probationary Final Evaluation Due Soon','Legacy fixture reminder']))===1,
        'existing legacy reminder is registered without creating a duplicate');
    echo "SUCCESS: $checks checks passed in isolated database $testDb".PHP_EOL;
} finally {
    if (isset($conn)) $conn->close();
    // This name was generated above, and is never the application/source schema.
    if ($created && preg_match('/^raquel_annual_test_[a-f0-9]{12}$/',$testDb)) $admin->query("DROP DATABASE IF EXISTS `$testDb`");
    $admin->close();
}
