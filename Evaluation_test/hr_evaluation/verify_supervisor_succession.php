<?php
/** CLI regression check. All fixture changes run in one transaction and are rolled back. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
require_once __DIR__ . '/../../includes/functions.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli(getenv('HRIS_TEST_HOST') ?: '127.0.0.1', getenv('HRIS_TEST_USER') ?: 'root',
    getenv('HRIS_TEST_PASSWORD') ?: '', getenv('HRIS_TEST_DATABASE') ?: 'raquel_hris');
$conn->set_charset('utf8mb4');
function successionCheck(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
    echo "PASS: $message\n";
}
$primary = annualQuery($conn, "SELECT e.*,u.user_id FROM employees e
    JOIN job_titles j ON j.job_title_id=e.job_title_id JOIN departments d ON d.department_id=e.department_id
    JOIN users u ON u.employee_id=e.employee_id
    WHERE d.department_name='Human Resources' AND j.job_title='HR Supervisor I'
      AND e.employment_status='Regular' AND e.is_active=1 AND e.deleted_at IS NULL
      AND u.role='HR Supervisor' AND u.is_active=1 AND u.deleted_at IS NULL AND u.account_hold=0 LIMIT 1")[0] ?? null;
if (!$primary) throw new RuntimeException('Use the HR fixture database with an active regular HR Supervisor I.');
$department = (int)$primary['department_id'];
$positions = array_column(annualQuery($conn, 'SELECT job_title_id,job_title FROM job_titles WHERE department_id=? AND is_active=1', [$department]), 'job_title_id', 'job_title');
$primaryPosition = (int)$positions['HR Supervisor I'];
$nextPosition = (int)$positions['HR Supervisor II'];
$managerPosition = (int)$positions['HR Manager I'];
$staff = annualQuery($conn, "SELECT e.* FROM employees e JOIN job_titles j ON j.job_title_id=e.job_title_id
    WHERE e.department_id=? AND j.reports_to=? AND e.is_active=1 AND e.deleted_at IS NULL
      AND e.employment_status='Regular'
      AND EXISTS(SELECT 1 FROM users u WHERE u.employee_id=e.employee_id AND u.role='Employee'
          AND u.is_active=1 AND u.deleted_at IS NULL AND u.account_hold=0) LIMIT 1", [$department,$primaryPosition])[0] ?? null;
if (!$staff) throw new RuntimeException('The HR fixture needs a regular staff employee reporting to HR Supervisor I.');
$staffId = (int)$staff['employee_id'];
$staffPosition = (int)$staff['job_title_id'];
$before = annualQuery($conn, 'SELECT employee_id,job_title_id,rank_category_id FROM employees ORDER BY employee_id');
$beforeResults = annualQuery($conn, 'SELECT evaluation_id,total_score,status FROM evaluations ORDER BY evaluation_id');
$beforeTree = annualQuery($conn, 'SELECT job_title_id,reports_to FROM job_titles ORDER BY job_title_id');
$beforeAccounts = annualQuery($conn, 'SELECT user_id,role,account_hold FROM users ORDER BY user_id');
if (!$conn->query("SHOW COLUMNS FROM employees LIKE 'reports_to'")->num_rows) {
    throw new RuntimeException('Import the current schema before running this transactional check.');
}
$suffix = bin2hex(random_bytes(5));
$conn->begin_transaction();
try {
    // Isolate the fixture successor if this test database already contains Supervisor II holders.
    annualQuery($conn, 'UPDATE users u JOIN employees e ON e.employee_id=u.employee_id
        SET u.account_hold=1 WHERE e.department_id=? AND e.job_title_id=?', [$department,$nextPosition]);
    $makeSupervisor = function (int $position, string $label) use ($conn,$department,$primary,$suffix): array {
        annualQuery($conn, "INSERT INTO employees(first_name,last_name,hire_date,job_title,job_title_id,department_id,
            rank_category_id,branch_id,employment_status,is_active) VALUES ('Succession',?,CURDATE(),?,?,?,4,?,'Regular',1)",
            [$label,'HR Supervisor '.$label,$position,$department,(int)$primary['branch_id']]);
        $employee = (int)$conn->insert_id;
        $username = 'succession-'.$label.'-'.$suffix;
        annualQuery($conn, "INSERT INTO users(employee_id,username,email,password_hash,full_name,role,is_active,account_hold)
            VALUES (?,?,?,'!disabled-test-password','Succession fixture','HR Supervisor',1,0)",
            [$employee,$username,$username.'@example.invalid']);
        return [$employee,(int)$conn->insert_id];
    };
    [$nextEmployee,$nextUser] = $makeSupervisor($nextPosition,'II');
    successionCheck(effectiveHRSupervisorPosition($conn,$primaryPosition,$department)===$primaryPosition,
        'Occupied Supervisor I remains the head while Supervisor II is present');
    $fixtures = [];
    foreach (['annual','quarterly'] as $prefix) {
        annualQuery($conn, "INSERT INTO evaluation_templates(template_name,target_department,target_department_id,
                evaluation_type,kra_weight,behavior_weight,status,created_by)
            VALUES (?,'Human Resources',?,?,80,20,'Active',?)",
            ['Succession '.$prefix.' '.$suffix,$department,ucfirst($prefix),(int)$primary['user_id']]);
        $template = (int)$conn->insert_id;
        annualQuery($conn, 'INSERT INTO evaluation_template_positions(template_id,job_title_id) VALUES (?,?)', [$template,$staffPosition]);
        annualQuery($conn, "INSERT INTO evaluation_criteria(template_id,section,criterion_name,weight,sort_order)
            VALUES (?,'KRA','Succession KRA',100,1)", [$template]);
        foreach (annualCoreValues() as $i=>$value) {
            annualQuery($conn, "INSERT INTO evaluation_criteria(template_id,section,criterion_name,kpi_description,weight,sort_order)
                VALUES (?,'Behavior',?,?,0,?)", [$template,$value['name'],$value['kpi'],$i+1]);
        }
        annualQuery($conn, "INSERT INTO {$prefix}_evaluation_cycles(department_id,period_start,period_end,started_at)
            VALUES (?,'2097-01-01','2097-12-31',NOW())", [$department]);
        $cycle = (int)$conn->insert_id;
        annualQuery($conn, "INSERT INTO evaluations(employee_id,template_id,evaluation_type,evaluation_period_start,
                evaluation_period_end,status) VALUES (?,?,?,'2097-01-01','2097-12-31','Pending Supervisor')",
            [$staffId,$template,ucfirst($prefix)]);
        $evaluation = (int)$conn->insert_id;
        annualQuery($conn, "INSERT INTO {$prefix}_evaluation_members(cycle_id,employee_id,evaluation_id,position_id,
                reviewer_position_id,position_name) VALUES (?,?,?,?,?,?)",
            [$cycle,$staffId,$evaluation,$staffPosition,$primaryPosition,$staff['job_title']]);
        $fixtures[$prefix] = [$evaluation,(int)$conn->insert_id,$template];
    }
    // Simulate the applied promotion state, including the HRIS account hold.
    annualQuery($conn, "UPDATE employees SET job_title='HR Manager I',job_title_id=?,rank_category_id=3
        WHERE department_id=? AND job_title_id=?", [$managerPosition,$department,$primaryPosition]);
    annualQuery($conn, 'UPDATE users SET account_hold=1 WHERE user_id=?', [(int)$primary['user_id']]);
    successionCheck(effectiveHRSupervisorPosition($conn,$primaryPosition,$department)===$nextPosition,
        'Promotion activates Supervisor II coverage');
    $line = getHRDirectReportByPosition($conn,$staffId);
    successionCheck((int)($line['supervisor']['supervisor_employee_id'] ?? 0)===$nextEmployee,
        'Staff reporting lookup resolves to Supervisor II');
    successionCheck(in_array($staffId,array_map('intval',array_column(getEmployeeSubordinates($conn,$nextEmployee),'employee_id')),true),
        'Staff appears in acting Supervisor II direct reports');
    $context = getEvaluationTemplateCreatorContext($conn,$nextUser);
    successionCheck($context['can_create_templates'] && in_array($staffPosition,
        array_map('intval',array_column(getEvaluationTemplateTargetPositions($conn,$context,$department),'job_title_id')),true),
        'Acting Supervisor II can create forms for Supervisor I target positions');
    foreach ($fixtures as $prefix=>[$evaluation,$memberId,$template]) {
        $actor = ($prefix.'Actor')($conn,$nextUser);
        $member = ($prefix.'Member')($conn,$evaluation);
        successionCheck((int)$member['reviewer_position_id']===$nextPosition
            && (int)$member['original_reviewer_position_id']===$primaryPosition
            && ($prefix.'CanFinalize')($conn,$actor,$member), "$prefix unfinished review is available to acting Supervisor II");
        successionCheck(($prefix.'PendingReviewCount')($conn,$actor)>0, "$prefix review appears in the pending workload");
        // New forms use the same allowed positions, with the acting supervisor as creator.
        annualQuery($conn,'UPDATE evaluation_templates SET created_by=? WHERE template_id=?',[$nextUser,$template]);
        $roster = ($prefix.'CycleRosterPreview')($conn,$department);
        $staffRow = array_values(array_filter($roster,static fn($r)=>(int)$r['employee_id']===$staffId))[0];
        successionCheck($staffRow['reviewer_position_name']==='HR Supervisor II'
            && !in_array('Immediate head account missing',$staffRow['issues'],true)
            && !array_filter($staffRow['issues'],static fn($issue)=>str_contains($issue,'form from immediate head')),
            "$prefix readiness accepts the acting head and their form");
        annualQuery($conn,"UPDATE {$prefix}_evaluation_members SET current_version=1 WHERE member_id=?",[$memberId]);
        annualQuery($conn,"UPDATE evaluations SET status='Approved',total_score=3.75 WHERE evaluation_id=?",[$evaluation]);
        $approved = ($prefix.'Member')($conn,$evaluation);
        successionCheck((int)$approved['reviewer_position_id']===$primaryPosition && (float)$approved['official_score']===3.75,
            "$prefix completed approval retains original reviewer and official score");
        annualQuery($conn,"UPDATE {$prefix}_evaluation_members SET current_version=0 WHERE member_id=?",[$memberId]);
        annualQuery($conn,"UPDATE evaluations SET status='Pending Supervisor',total_score=NULL WHERE evaluation_id=?",[$evaluation]);
    }
    annualQuery($conn,'UPDATE users SET account_hold=1 WHERE user_id=?',[$nextUser]);
    successionCheck(getHRDirectReportByPosition($conn,$staffId)['supervisor']===null,
        'A held Supervisor II account cannot inherit reporting authority');
    annualQuery($conn,'UPDATE users SET account_hold=0 WHERE user_id=?',[$nextUser]);
    [$replacementEmployee,$replacementUser] = $makeSupervisor($primaryPosition,'I');
    successionCheck(effectiveHRSupervisorPosition($conn,$primaryPosition,$department)===$primaryPosition
        && (int)getHRDirectReportByPosition($conn,$staffId)['supervisor']['supervisor_employee_id']===$replacementEmployee,
        'An eligible replacement Supervisor I automatically resumes direct reports');
    $context = getEvaluationTemplateCreatorContext($conn,$nextUser);
    successionCheck(!in_array($primaryPosition,getEvaluationTemplateCreatorPositionIds($conn,$context),true),
        'Supervisor II loses acting template targets when Supervisor I returns');
    foreach ($fixtures as $prefix=>[$evaluation]) {
        $member = ($prefix.'Member')($conn,$evaluation);
        successionCheck(!($prefix.'CanFinalize')($conn,($prefix.'Actor')($conn,$nextUser),$member)
            && ($prefix.'CanFinalize')($conn,($prefix.'Actor')($conn,$replacementUser),$member),
            "$prefix unfinished review returns to the replacement Supervisor I");
    }
    annualQuery($conn,'UPDATE users SET account_hold=1 WHERE user_id=?',[$replacementUser]);
    successionCheck(effectiveHRSupervisorPosition($conn,$primaryPosition,$department)===$nextPosition,
        'A replacement must have an eligible active HRIS account before acting coverage ends');
} finally {
    $conn->rollback();
}
successionCheck($before===annualQuery($conn,'SELECT employee_id,job_title_id,rank_category_id FROM employees ORDER BY employee_id')
    && $beforeResults===annualQuery($conn,'SELECT evaluation_id,total_score,status FROM evaluations ORDER BY evaluation_id')
    && $beforeTree===annualQuery($conn,'SELECT job_title_id,reports_to FROM job_titles ORDER BY job_title_id'),
    'Fixture changes rolled back; employees, evaluation results, and reporting structure preserved');
successionCheck($beforeAccounts===annualQuery($conn,'SELECT user_id,role,account_hold FROM users ORDER BY user_id'),
    'Account roles and holds preserved after rollback');
