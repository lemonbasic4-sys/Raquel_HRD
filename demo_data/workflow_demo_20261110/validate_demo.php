<?php
/** CLI-only checks against a separately named disposable DB. No app config import. */
if (PHP_SAPI !== 'cli' || !preg_match('/^raquel_demo_[A-Za-z0-9_]+$/D', $argv[1] ?? '')) {
    fwrite(STDERR, "Pass an isolated raquel_demo_<suffix> database name.\n"); exit(1);
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
date_default_timezone_set('Asia/Manila');
define('BASE_URL', '/Raquel_HRD');
$conn = new mysqli('127.0.0.1', 'root', '', $argv[1]);
$conn->set_charset('utf8mb4');
$conn->query("SET time_zone='+08:00'");
$root = dirname(__DIR__, 2);
require_once $root . '/includes/functions.php';
require_once $root . '/includes/employee-performance-analysis.php';
$failures = [];
$checks = 0;
function verifyDemo(bool $ok, string $label): void {
    global $failures, $checks;
    $checks++;
    if (!$ok) $failures[] = $label;
}
ensureAnnualEvaluationSchema($conn);
ensureQuarterlyEvaluationSchema($conn);
verifyDemo(ensureOrganizationEvaluationPackageSchema($conn), 'Runtime package schema initialization');

$verificationSql = preg_replace('/^--.*$/m', '', file_get_contents(__DIR__ . '/02_verify_demo.sql'));
$conn->multi_query($verificationSql);
do {
    if ($result = $conn->store_result()) {
        while ($row = $result->fetch_assoc()) {
            if (isset($row['invalid_rows'])) verifyDemo((int)$row['invalid_rows'] === 0, 'SQL: ' . $row['check_name'] . ' (' . $row['invalid_rows'] . ')');
        }
        $result->free();
    }
} while ($conn->more_results() && $conn->next_result());

$standard = array_column(annualCoreValues(), 'name');
$templates = annualQuery($conn, 'SELECT template_id FROM evaluation_templates WHERE template_id BETWEEN 930001 AND 939999');
foreach ($templates as $template) {
    $names = array_column(annualQuery($conn, "SELECT criterion_name FROM evaluation_criteria WHERE template_id=? AND section='Behavior' ORDER BY sort_order", [(int)$template['template_id']]), 'criterion_name');
    verifyDemo($names === $standard, 'Eight exact standard core items: template ' . $template['template_id']);
}

$periodic = annualQuery($conn, "SELECT evaluation_id,employee_id,evaluation_type,kra_subtotal,behavior_average,total_score,performance_level,approved_by FROM evaluations WHERE evaluation_type IN ('Annual','Quarterly')");
foreach ($periodic as $ev) {
    $prefix = $ev['evaluation_type'] === 'Annual' ? 'annual' : 'quarterly';
    $id = (int)$ev['evaluation_id'];
    $member = ($prefix . 'Member')($conn, $id);
    verifyDemo((bool)$member, "Cycle member for $id");
    if (!$member) continue;
    ($prefix . 'AssertSnapshotActive')($member);
    $candidate = ($prefix . 'Candidate')($conn, $member);
    foreach (['kra'=>'kra_subtotal','core'=>'behavior_average','total'=>'total_score'] as $key=>$field) {
        verifyDemo(abs($candidate[$key] - (float)$ev[$field]) < .00001, "Actual $prefix calculator $key for $id");
    }
    verifyDemo(getPerformanceLevel($candidate['total']) === $ev['performance_level'], "Actual performance category $id");
    $head = ($prefix . 'Actor')($conn, (int)$ev['approved_by']);
    verifyDemo($head && ($prefix . 'CanFinalize')($conn, $head, $member), "Actual immediate-head authority $id");
    $expected = ($prefix . 'ExpectedPeers')($conn, (int)$member['cycle_id'], (int)$member['employee_id']);
    $version = annualQuery($conn, "SELECT snapshot_json FROM {$prefix}_evaluation_versions WHERE member_id=? AND version_number=?", [(int)$member['member_id'], (int)$member['current_version']])[0] ?? [];
    $snapshot = json_decode($version['snapshot_json'] ?? '{}', true);
    verifyDemo(($snapshot['score_complete'] ?? false) === true && ($snapshot['expected_peers'] ?? -1) === $expected && $candidate['peer_count'] >= $expected, "Final snapshot completeness $id");
    foreach (['kra','core','total','item_averages'] as $field) {
        verifyDemo(json_encode($snapshot[$field] ?? null, JSON_PRESERVE_ZERO_FRACTION) === json_encode($candidate[$field], JSON_PRESERVE_ZERO_FRACTION) || $snapshot[$field] == $candidate[$field], "Snapshot matches live calculation $field $id");
    }
    foreach (annualQuery($conn, "SELECT submitted_by,rater_employee_id FROM {$prefix}_peer_submissions WHERE cycle_id=? AND target_employee_id=?", [(int)$member['cycle_id'], (int)$member['employee_id']]) as $peer) {
        $actor = ($prefix . 'Actor')($conn, (int)$peer['submitted_by']);
        verifyDemo($actor && ($prefix . 'CanRate')($actor) && (int)$actor['employee_id'] === (int)$peer['rater_employee_id'], "Actual peer authority $id / " . $peer['rater_employee_id']);
    }
}

$probation = annualQuery($conn, "SELECT ev.*, p.package_id FROM evaluations ev JOIN evaluation_package_members pm ON pm.evaluation_id=ev.evaluation_id JOIN evaluation_packages p ON p.package_id=pm.package_id WHERE ev.evaluation_type IN ('Initial','Final')");
foreach ($probation as $ev) {
    $id = (int)$ev['evaluation_id'];
    $rows = annualQuery($conn, "SELECT c.section,c.weight,s.score_value,s.supervisor_override_score,s.manager_override_score,s.dept_manager_override_score,s.weighted_score FROM evaluation_scores s JOIN evaluation_criteria c ON c.criterion_id=s.criterion_id WHERE s.evaluation_id=?", [$id]);
    $kra = 0; $beh = [];
    foreach ($rows as $row) {
        $effective = $row['manager_override_score'] ?? $row['supervisor_override_score'] ?? $row['dept_manager_override_score'] ?? $row['score_value'];
        $weighted = $row['section'] === 'KRA' ? round((float)$row['weight'] / 100 * (float)$effective, 2) : (float)$effective;
        verifyDemo(abs($weighted - (float)$row['weighted_score']) < .00001, "Probationary row weighting $id");
        if ($row['section'] === 'KRA') $kra += $weighted; else $beh[] = (float)$effective;
    }
    $kra = round($kra, 2); $core = round(array_sum($beh) / count($beh), 2);
    verifyDemo(abs($kra - (float)$ev['kra_subtotal']) < .00001 && abs($core - (float)$ev['behavior_average']) < .00001 && abs(calculateEvalTotal($kra,$core) - (float)$ev['total_score']) < .00001, "Probationary actual formula $id");
    $signature = annualQuery($conn,'SELECT immediate_head_signature_data FROM probationary_evaluation_reviews WHERE evaluation_id=?',[$id])[0]['immediate_head_signature_data'];
    $image = getimagesizefromstring(base64_decode(substr($signature,strpos($signature,',')+1)));
    verifyDemo($image && $image[2] === IMAGETYPE_PNG, "Valid visibly synthetic PNG $id");

    // Exercise the real route builder on a temporary transaction, then roll it
    // back. No generated scratch package survives this validation.
    $conn->begin_transaction();
    try {
        annualQuery($conn,"INSERT INTO evaluation_packages (department_id,template_id,evaluation_type,period_start,period_end,status) SELECT department_id,template_id,evaluation_type,'1900-01-01','1900-01-02','Pending Review' FROM evaluation_packages WHERE package_id=?",[(int)$ev['package_id']]);
        $scratch=(int)$conn->insert_id;
        $saved=annualQuery($conn,'SELECT reviewer_employee_id,reviewer_user_id FROM evaluation_package_route_steps WHERE package_id=?',[(int)$ev['package_id']])[0];
        createOrganizationPackageRoute($conn,$scratch,(int)$saved['reviewer_employee_id'],$id);
        $routes=annualQuery($conn,'SELECT * FROM evaluation_package_route_steps WHERE package_id=? ORDER BY step_order',[$scratch]);
        verifyDemo(count($routes)===1,"Actual probationary route count $id");
        if ($routes) {
            $reviewer=annualQuery($conn,'SELECT u.role,e.rank_category_id,e.employee_id FROM users u JOIN employees e ON e.employee_id=u.employee_id WHERE user_id=?',[(int)$saved['reviewer_user_id']])[0];
            $route=$routes[0];
            verifyDemo((empty($route['reviewer_user_id']) || (int)$route['reviewer_user_id']===(int)$saved['reviewer_user_id']) && (empty($route['reviewer_employee_id']) || (int)$route['reviewer_employee_id']===(int)$saved['reviewer_employee_id']) && (empty($route['eligible_role']) || $route['eligible_role']===$reviewer['role']) && (empty($route['eligible_rank_category_id']) || (int)$route['eligible_rank_category_id']===(int)$reviewer['rank_category_id']),"Actual route accepts saved reviewer $id");
        }
    } finally { $conn->rollback(); }
}

$historyCount=0;
foreach (annualQuery($conn,"SELECT employee_id,job_title,employee_code FROM employees WHERE employee_code LIKE 'D26-%' AND job_title<>'President and CEO'") as $p) {
    $history=employeePerformanceHistory($conn,(int)$p['employee_id']);
    verifyDemo(count($history['evaluations'])===6 && !$history['pending_evaluations'], 'Employee history shows six finalized results: '.$p['employee_code']);
    $historyCount+=count($history['evaluations']);
}
foreach (annualQuery($conn,'SELECT department_id FROM departments WHERE is_active=1 AND deleted_at IS NULL') as $d) {
    foreach (['annual','quarterly'] as $prefix) {
        $roster=($prefix.'CycleRosterPreview')($conn,(int)$d['department_id']);
        verifyDemo((bool)$roster && !array_filter($roster,static fn($p)=>!empty($p['issues'])), 'Current '.$prefix.' form/readiness checks for department '.$d['department_id']);
    }
}
foreach (['annual','quarterly'] as $prefix) {
    foreach (annualQuery($conn,"SELECT * FROM {$prefix}_evaluation_cycles") as $cycle) {
        $owner=($prefix.'Actor')($conn,(int)$cycle['cycle_approved_by']);
        verifyDemo($owner && ($prefix.'CanOwnCycle')($conn,$owner,(int)$cycle['department_id']), 'Actual cycle-owner authority '.$prefix.' '.$cycle['cycle_id']);
        $before=annualQuery($conn,"SELECT member_id,evaluation_id FROM {$prefix}_evaluation_members WHERE cycle_id=? ORDER BY member_id",[(int)$cycle['cycle_id']]);
        $conn->begin_transaction();
        try {
            verifyDemo(($prefix.'UpdateCycleCompletion')($conn,(int)$cycle['cycle_id'])===true, 'Actual completion engine retains finalized '.$prefix.' '.$cycle['cycle_id']);
            $after=annualQuery($conn,"SELECT member_id,evaluation_id FROM {$prefix}_evaluation_members WHERE cycle_id=? ORDER BY member_id",[(int)$cycle['cycle_id']]);
            verifyDemo($before===$after, 'No unexpected late enrolment '.$prefix.' '.$cycle['cycle_id']);
        } finally { $conn->rollback(); }
    }
}
$filter=finalizedPerformanceSql('ev');
$visible=annualQuery($conn,"SELECT ev.evaluation_type,COUNT(*) AS n FROM evaluations ev WHERE ev.status='Approved' AND $filter GROUP BY ev.evaluation_type");
$analytics=[];
foreach ($visible as $row) $analytics[$row['evaluation_type']]=(int)$row['n'];
verifyDemo(array_sum($analytics)===336,'All 336 results pass actual manager analytics SQL');
verifyDemo((int)$conn->query('SELECT @@foreign_key_checks AS n')->fetch_assoc()['n']===1,'Foreign keys stayed enabled');
echo json_encode(['database'=>$argv[1],'checks'=>$checks,'failures'=>$failures,'periodic_calculations'=>count($periodic),'probationary_results'=>count($probation),'new_employee_history_results'=>$historyCount,'analytics_visible'=>$analytics],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
exit($failures ? 1 : 0);

