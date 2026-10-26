<?php
/** Readiness report. Initializes missing schema/owner mappings, then only reads roster data. */
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
session_set_save_handler(static fn()=>true,static fn()=>true,static fn()=>'',static fn()=>true,static fn()=>true,static fn()=>0);
session_start();
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/functions.php';
ensureAnnualEvaluationSchema($conn);
foreach (annualQuery($conn,'SELECT department_id,department_name FROM departments WHERE is_active=1 AND deleted_at IS NULL ORDER BY department_id') as $department) {
    $id=(int)$department['department_id'];
    $roster=annualCycleRosterPreview($conn,$id);
    $blocked=array_values(array_filter($roster,static fn($person)=>!empty($person['issues'])));
    $owner=annualCycleOwnerName($conn,$id);
    echo $department['department_name'].': '.count($roster).' participants, '.count($blocked).' blocked, owner '.$owner.PHP_EOL;
    foreach ($blocked as $person) {
        echo '  - '.$person['first_name'].' '.$person['last_name'].': '.implode('; ',$person['issues']).PHP_EOL;
    }
}
