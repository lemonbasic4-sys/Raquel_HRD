<?php
/** Dry-run the organization, major employee, and AP template seeds in an isolated database. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
session_set_save_handler(static fn()=>true,static fn()=>true,static fn()=>'',static fn()=>true,static fn()=>true,static fn()=>0);
session_start();
require_once __DIR__.'/../config/database.php';
$conn->close();
require_once __DIR__.'/../includes/functions.php';

$admin = new mysqli('127.0.0.1', 'root', '');
$source = $argv[1] ?? 'raquel_hris';
if (!preg_match('/^[a-zA-Z0-9_]+$/', $source)) throw new RuntimeException('Invalid source schema.');
$testDb = 'raquel_ap_seed_test_'.bin2hex(random_bytes(5));
$created = false;

function apSeedImport(mysqli $db, string $path, string $testDb): void
{
    $sql = file_get_contents($path);
    if ($sql === false) throw new RuntimeException('Cannot read '.$path);
    $sql = str_replace('USE raquel_hris;', 'USE `'.$testDb.'`;', $sql);
    if (!$db->multi_query($sql)) throw new RuntimeException('Import failed: '.$path);
    do {
        $result = $db->store_result();
        if ($result) $result->free();
    } while ($db->more_results() && $db->next_result());
}

try {
    $admin->query("CREATE DATABASE `$testDb` CHARACTER SET utf8mb4");
    $created = true;
    foreach (array_column($admin->query("SHOW TABLES FROM `$source`")->fetch_all(MYSQLI_NUM), 0) as $table) {
        $admin->query("CREATE TABLE `$testDb`.`$table` LIKE `$source`.`$table`");
    }
    $db = new mysqli('127.0.0.1', 'root', '', $testDb);
    $db->set_charset('utf8mb4');
    apSeedImport($db, __DIR__.'/../database/2nd_seed_organization.sql', $testDb);
    apSeedImport($db, __DIR__.'/../test_database/major_employees_complete_profiles.sql', $testDb);
    apSeedImport($db, __DIR__.'/../test_database/ap_annual_head_templates.sql', $testDb);

    $employees = $db->query("SELECT COUNT(*) AS n FROM employees WHERE department_id=1 AND employee_code IN ('AP-001','AP-002','AP-003','AP-004')")->fetch_assoc();
    if ((int)$employees['n'] !== 4) throw new RuntimeException('Expected four AP employees.');
    $templates = $db->query("SELECT t.template_id,t.created_by,p.job_title_id,
            SUM(c.section='KRA') AS kra_items,
            SUM(CASE WHEN c.section='KRA' THEN c.weight ELSE 0 END) AS kra_total,
            SUM(c.section='Behavior') AS behavior_items
        FROM evaluation_templates t
        JOIN evaluation_template_positions p ON p.template_id=t.template_id
        JOIN evaluation_criteria c ON c.template_id=t.template_id
        WHERE t.template_name LIKE '[TEST AP ANNUAL] %'
        GROUP BY t.template_id,t.created_by,p.job_title_id")->fetch_all(MYSQLI_ASSOC);
    if (count($templates) !== 4) throw new RuntimeException('Expected four AP templates.');
    foreach ($templates as $template) {
        if ((int)$template['kra_items'] !== 11 || abs((float)$template['kra_total']-100) > .001
            || (int)$template['behavior_items'] !== 8) {
            throw new RuntimeException('Invalid criteria for AP position '.$template['job_title_id']);
        }
    }
    ensureAnnualEvaluationSchema($db);
    $roster = annualCycleRosterPreview($db, 1);
    if (count($roster) !== 4 || array_filter($roster, static fn($row) => !empty($row['issues']))) {
        throw new RuntimeException('AP cycle readiness failed: '.json_encode(array_column($roster, 'issues', 'job_title')));
    }
    apSeedImport($db, __DIR__.'/../test_database/ap_annual_head_templates.sql', $testDb);
    $after = (int)$db->query("SELECT COUNT(*) AS n FROM evaluation_templates WHERE template_name LIKE '[TEST AP ANNUAL] %'")->fetch_assoc()['n'];
    if ($after !== 4) throw new RuntimeException('Rerunning AP template seed created duplicates.');
    echo "PASS: four AP employees, four ready templates, 11 KRAs and eight behaviors per form; rerun adds no duplicates.\n";
    $db->close();
} finally {
    if ($created) $admin->query("DROP DATABASE `$testDb`");
    $admin->close();
}
