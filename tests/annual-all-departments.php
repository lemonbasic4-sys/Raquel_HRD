<?php
/** Isolated all-department readiness and cycle start check. Never writes to raquel_hris. */
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
session_set_save_handler(static fn()=>true,static fn()=>true,static fn()=>'',static fn()=>true,static fn()=>true,static fn()=>0);
session_start();
require_once __DIR__.'/../config/database.php';
$conn->close();
require_once __DIR__.'/../includes/functions.php';
date_default_timezone_set('Asia/Manila');
$_SERVER['REMOTE_ADDR']='127.0.0.1';
$_SERVER['HTTP_USER_AGENT']='All departments annual test';
$admin=new mysqli('127.0.0.1','root','');
$source=$argv[1] ?? 'raquel_hris';
if (!preg_match('/^[a-zA-Z0-9_]+$/',$source)) throw new RuntimeException('Invalid source schema.');
$testDb='raquel_all_annual_test_'.bin2hex(random_bytes(5));
$created=false;
function allImport(mysqli $db,string $path,string $testDb): void {
    $sql=file_get_contents($path);
    if ($sql===false) throw new RuntimeException('Cannot read '.$path);
    $sql=str_replace('USE raquel_hris;', 'USE `'.$testDb.'`;', $sql);
    if (preg_match('/^\s*USE\s+(?!`'.preg_quote($testDb,'/').'`\s*;)[^;]+;/im',$sql)) {
        throw new RuntimeException('Seed selects a database outside the fixture: '.$path);
    }
    if ($db->query('SELECT DATABASE()')->fetch_row()[0]!==$testDb) throw new RuntimeException('Unsafe active database.');
    if (!$db->multi_query($sql)) throw new RuntimeException('Import failed: '.$path);
    do { $result=$db->store_result(); if ($result) $result->free(); }
    while ($db->more_results() && $db->next_result());
    if ($db->query('SELECT DATABASE()')->fetch_row()[0]!==$testDb) throw new RuntimeException('Seed changed active database: '.$path);
}
function allCheck(bool $ok,string $message): void {
    if (!$ok) throw new RuntimeException('FAIL: '.$message);
    echo 'PASS: '.$message.PHP_EOL;
}
try {
    $admin->query("CREATE DATABASE `$testDb` CHARACTER SET utf8mb4"); $created=true;
    foreach (array_column($admin->query("SHOW TABLES FROM `$source`")->fetch_all(MYSQLI_NUM),0) as $table) {
        $admin->query("CREATE TABLE `$testDb`.`$table` LIKE `$source`.`$table`");
    }
    $conn=new mysqli('127.0.0.1','root','',$testDb); $conn->set_charset('utf8mb4');
    allImport($conn,__DIR__.'/../database/2nd_seed_organization.sql',$testDb);
    allImport($conn,__DIR__.'/../test_database/major_employees_complete_profiles.sql',$testDb);
    allImport($conn,__DIR__.'/../test_database/ap_annual_head_templates.sql',$testDb);
    allImport($conn,__DIR__.'/../test_database/audit_annual_head_templates.sql',$testDb);
    allImport($conn,__DIR__.'/../test_database/remaining_department_annual_templates.sql',$testDb);
    ensureAnnualEvaluationSchema($conn);
    $expected=[
        'Acquired Properties'=>[4,'AP Manager'],
        'Audit'=>[4,'Audit Manager I'],
        'Business Development'=>[2,'Business Development Officer I'],
        'Compliance'=>[3,'Compliance Supervisor I'],
        'Finance'=>[6,'VP for Finance'],
        'General Services'=>[4,'VP for General Services'],
        'Human Resources'=>[3,'HR Manager'],
        'Information Technology'=>[3,'IT Manager I'],
        'Marketing'=>[3,'Marketing Manager I'],
        'Office of the President'=>[1,'President and CEO'],
        'Operations'=>[5,'VP for Operations'],
        'Purchasing'=>[2,'Procurement Supervisor I'],
    ];
    $started=[];
    foreach ($expected as $departmentName=>[$size,$ownerTitle]) {
        $departmentId=(int)annualQuery($conn,'SELECT department_id FROM departments WHERE department_name=?',[$departmentName])[0]['department_id'];
        $roster=annualCycleRosterPreview($conn,$departmentId);
        allCheck(count($roster)===$size && !array_filter($roster,static fn($row)=>!empty($row['issues'])),
            $departmentName.' has '.$size.' ready participants');
        allCheck(annualCycleOwnerName($conn,$departmentId)===$ownerTitle,$departmentName.' owner is '.$ownerTitle);
        $ownerUser=$departmentName==='Human Resources'
            ? (annualQuery($conn,"SELECT u.user_id FROM employees e JOIN users u ON u.employee_id=e.employee_id
                WHERE e.department_id=? AND u.role='HR Manager' LIMIT 1",[$departmentId])[0] ?? null)
            : (annualQuery($conn,"SELECT u.user_id FROM annual_cycle_department_owners o
            JOIN employees e ON e.job_title_id=o.owner_position_id AND e.department_id=o.department_id
            JOIN users u ON u.employee_id=e.employee_id AND u.role='Employee'
            WHERE o.department_id=? LIMIT 1",[$departmentId])[0] ?? null);
        $owner=$ownerUser ? annualActor($conn,(int)$ownerUser['user_id']) : null;
        allCheck($owner && annualCanOwnCycle($conn,$owner,$departmentId)
            && ($departmentName==='Human Resources' || annualApCycleReadyToStart($conn,$owner)),
            $departmentName.' owner can start only when ready');
        $cycleId=annualStartCycle($conn,$owner,$departmentId,'2026-01-01','2026-12-31','2026-12-31');
        $started[$departmentName]=[$cycleId,$departmentId,$owner];
        allCheck((int)annualQuery($conn,'SELECT COUNT(*) AS n FROM annual_evaluation_members WHERE cycle_id=?',[$cycleId])[0]['n']===$size,
            $departmentName.' starts with its own roster');
    }
    $scores=json_encode(array_fill(0,8,3));
    foreach ($started as $departmentName=>[$cycleId,$departmentId,$owner]) {
        $members=annualQuery($conn,'SELECT m.employee_id,m.position_id FROM annual_evaluation_members m
            WHERE m.cycle_id=? ORDER BY m.member_id',[$cycleId]);
        foreach ($members as $member) {
            $form=annualQuery($conn,"SELECT t.template_id FROM evaluation_templates t
                JOIN evaluation_template_positions p ON p.template_id=t.template_id
                WHERE t.target_department_id=? AND p.job_title_id=? AND t.evaluation_type='Annual'
                AND t.status='Active' AND t.deleted_at IS NULL LIMIT 1",
                [$departmentId,(int)$member['position_id']])[0];
            annualQuery($conn,"INSERT INTO evaluations (employee_id,template_id,evaluation_type,evaluation_period_start,
                evaluation_period_end,submitted_by,status,submitted_date) VALUES (?,?,'Annual','2026-01-01',
                '2026-12-31',?,'Approved',NOW())",
                [(int)$member['employee_id'],(int)$form['template_id'],(int)$member['employee_id']]);
            $evaluationId=(int)$conn->insert_id;
            annualQuery($conn,'UPDATE annual_evaluation_members SET evaluation_id=?,self_values_json=?,current_version=1
                WHERE cycle_id=? AND employee_id=?',[$evaluationId,$scores,$cycleId,(int)$member['employee_id']]);
        }
        foreach ($members as $rater) foreach ($members as $target) {
            if ($rater['employee_id']===$target['employee_id']) continue;
            $user=annualQuery($conn,"SELECT user_id FROM users WHERE employee_id=? AND role='Employee' LIMIT 1",
                [(int)$rater['employee_id']])[0];
            annualQuery($conn,'INSERT INTO annual_peer_submissions
                (cycle_id,rater_employee_id,target_employee_id,submitted_by,scores_json) VALUES (?,?,?,?,?)',
                [$cycleId,(int)$rater['employee_id'],(int)$target['employee_id'],(int)$user['user_id'],$scores]);
        }
        allCheck(!annualUpdateCycleCompletion($conn,$cycleId),$departmentName.' waits for owner approval');
        allCheck(annualUpdateCycleCompletion($conn,$cycleId,(int)$owner['user_id']),$departmentName.' owner archives complete cycle');
    }
    $itManager=annualQuery($conn,"SELECT m.* FROM annual_evaluation_members m JOIN employees e ON e.employee_id=m.employee_id
        JOIN departments d ON d.department_id=e.department_id WHERE d.department_name='Information Technology'
        AND e.job_title='IT Manager I' LIMIT 1")[0];
    $vpOperations=annualActor($conn,(int)annualQuery($conn,"SELECT u.user_id FROM users u JOIN employees e ON e.employee_id=u.employee_id
        WHERE e.job_title='VP for Operations' AND u.role='Employee' LIMIT 1")[0]['user_id']);
    $procurement=annualQuery($conn,"SELECT m.* FROM annual_evaluation_members m JOIN employees e ON e.employee_id=m.employee_id
        JOIN departments d ON d.department_id=e.department_id WHERE d.department_name='Purchasing'
        AND e.job_title='Procurement Supervisor I' LIMIT 1")[0];
    $gsManager=annualActor($conn,(int)annualQuery($conn,"SELECT u.user_id FROM users u JOIN employees e ON e.employee_id=u.employee_id
        WHERE e.job_title='GS Manager I' AND u.role='Employee' LIMIT 1")[0]['user_id']);
    allCheck(annualCanFinalize($conn,$vpOperations,$itManager)
        && annualCanFinalize($conn,$gsManager,$procurement),'Cross-department heads can review IT and Purchasing KRAs');
    allCheck(annualExpectedPeers($conn,(int)$itManager['cycle_id'],(int)$itManager['employee_id'])===2
        && annualExpectedPeers($conn,(int)$procurement['cycle_id'],(int)$procurement['employee_id'])===1,
        'IT and Purchasing 360 counts use their own departments');
    $president=annualActor($conn,(int)annualQuery($conn,"SELECT user_id FROM users WHERE role='President and CEO' LIMIT 1")[0]['user_id']);
    $presidentPreviews=annualExternalPreviewDepartments($conn,$president);
    allCheck(count($presidentPreviews)>=7,'CEO sees externally reviewed department readiness');
    allImport($conn,__DIR__.'/../test_database/remaining_department_annual_templates.sql',$testDb);
    allCheck((int)annualQuery($conn,"SELECT COUNT(*) AS n FROM evaluation_templates WHERE template_name LIKE '[TEST % ANNUAL] %'")[0]['n']===40,
        'Reimport leaves 40 department sample forms without duplicates');
} finally {
    if (isset($conn) && $conn instanceof mysqli) $conn->close();
    if ($created) $admin->query("DROP DATABASE `$testDb`");
    $admin->close();
}
