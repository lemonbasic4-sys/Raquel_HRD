<?php
/** CLI regression test. Uses a disposable database; never reads/writes HR records.
 * Run: C:\xampp\php\php.exe sample_db_seeds/test_package_stage_merge.php
 * Optional connection overrides: TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli(getenv('TEST_DB_HOST') ?: '127.0.0.1', getenv('TEST_DB_USER') ?: 'root', getenv('TEST_DB_PASS') ?: '');
$database = 'test_package_stage_merge_' . bin2hex(random_bytes(6));
$conn->query("CREATE DATABASE `$database`");
$conn->select_db($database);
define('BASE_URL', '/test');
// Isolate the production merge functions from unrelated scoring/notification services.
$source = file_get_contents(__DIR__ . '/../includes/functions.php');
$start = strpos($source, 'function tryMergeLateMemberPackageIntoSibling(');
$end = strpos($source, 'function syncEvaluationToOrganizationPackage(', $start);
eval(substr($source, $start, $end - $start));
function recalculateOrganizationPackageBehaviorScore($conn, $id) {}
function createNotification(...$args) {}
function notifyUsersForEmployee(...$args) {}
function check($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
function fixture($conn, $label = 'HR Manager', $type = 'Review') {
    foreach (['evaluation_package_audit', 'evaluation_package_members', 'evaluation_package_route_steps', 'evaluation_packages', 'evaluations'] as $table) {
        $conn->query("DELETE FROM $table");
    }
    $conn->query("INSERT INTO evaluation_packages VALUES
        (1,1,1,'Annual','2026-01-01','2026-12-31',2,'Pending Review'),
        (2,1,1,'Annual','2026-01-01','2026-12-31',2,'Pending Review')");
    $label = $conn->real_escape_string($label);
    $type = $conn->real_escape_string($type);
    $conn->query("INSERT INTO evaluation_package_route_steps
        (package_id,step_order,step_label,step_type,action_status,reviewer_user_id) VALUES
        (1,1,'Earlier review','Consolidation','Approved',10),
        (2,1,'Earlier review','Consolidation','Approved',10),
        (1,2,'$label','$type','Pending',20), (2,2,'$label','$type','Pending',20)");
    $conn->query("INSERT INTO evaluations VALUES (11,101),(12,102),(13,103),(14,101)");
    $conn->query("INSERT INTO evaluation_package_members VALUES (1,11,'Normal',1),(1,12,'Normal',1),(2,13,'Catchup Complete',1)");
}
try {
    $conn->query("CREATE TABLE evaluation_packages (package_id INT PRIMARY KEY, department_id INT, template_id INT,
        evaluation_type VARCHAR(30), period_start DATE, period_end DATE, current_step_order INT NULL, status VARCHAR(40)) ENGINE=InnoDB");
    $conn->query("CREATE TABLE evaluation_package_route_steps (package_id INT, step_order INT, step_label VARCHAR(160),
        step_type VARCHAR(30), action_status VARCHAR(30), reviewer_user_id INT NULL, reviewer_employee_id INT NULL,
        eligible_role VARCHAR(50) NULL, eligible_rank_category_id INT NULL, claimed_at DATETIME NULL,
        PRIMARY KEY(package_id,step_order)) ENGINE=InnoDB");
    $conn->query("CREATE TABLE evaluation_package_members (package_id INT, evaluation_id INT, member_status VARCHAR(40),
        joined_at_step INT, PRIMARY KEY(package_id,evaluation_id)) ENGINE=InnoDB");
    $conn->query("CREATE TABLE evaluations (evaluation_id INT PRIMARY KEY, employee_id INT) ENGINE=InnoDB");
    $conn->query("CREATE TABLE evaluation_package_audit (package_id INT, action VARCHAR(80), remarks TEXT) ENGINE=InnoDB");
    foreach (['Supervisor', 'HR Manager', 'Department Manager', 'Division VP', 'President', 'Audit Committee', 'Board of Directors'] as $label) {
        fixture($conn, $label, in_array($label, ['Supervisor','HR Manager','Department Manager']) ? 'Review' : 'Governance');
        check(tryMergeLateMemberPackageIntoSibling($conn, 2, 1) === 1, "$label merge failed");
        $count = $conn->query('SELECT COUNT(*) c FROM evaluation_package_members WHERE package_id=1')->fetch_assoc()['c'];
        check((int)$count === 3, "$label team incomplete");
        check($conn->query('SELECT member_status FROM evaluation_package_members WHERE package_id=1 AND evaluation_id=13')->fetch_assoc()['member_status'] === 'Catchup Complete', 'Member status changed');
        check($conn->query('SELECT action_status FROM evaluation_package_route_steps WHERE package_id=2 AND step_order=1')->fetch_assoc()['action_status'] === 'Approved', 'Approval history lost');
        check((int)$conn->query('SELECT COUNT(*) c FROM evaluation_package_members WHERE package_id=2')->fetch_assoc()['c'] === 1, 'Source membership lost');
        check(tryMergeLateMemberPackageIntoSibling($conn, 2, 1) === 2, 'Cancelled package merged twice');
        echo "PASS: $label, preserved history/status, idempotency\n";
    }
    foreach ([
        "UPDATE evaluation_package_route_steps SET step_label='President' WHERE package_id=1 AND step_order=2",
        "UPDATE evaluation_packages SET evaluation_type='Quarterly' WHERE package_id=1",
        "UPDATE evaluation_packages SET department_id=2 WHERE package_id=1",
        "UPDATE evaluation_packages SET template_id=2 WHERE package_id=1",
        "UPDATE evaluation_packages SET period_end='2026-06-30' WHERE package_id=1",
        "UPDATE evaluation_packages SET status='Approved and Applied' WHERE package_id=1",
        "UPDATE evaluation_package_route_steps SET reviewer_user_id=99 WHERE package_id=1 AND step_order=2"
    ] as $sql) {
        fixture($conn); $conn->query($sql);
        check(tryMergeLateMemberPackageIntoSibling($conn, 2, 1) === 2, "Incorrect merge: $sql");
    }
    echo "PASS: stage, cycle, department, template, period, finalized and reviewer separation\n";
    fixture($conn);
    $conn->query("INSERT INTO evaluation_package_members VALUES (2,11,'Normal',1),(2,14,'Normal',1)");
    check(tryMergeLateMemberPackageIntoSibling($conn, 2, 1) === 1, 'Duplicate fixture failed');
    check((int)$conn->query('SELECT COUNT(*) c FROM evaluation_package_members WHERE package_id=1')->fetch_assoc()['c'] === 3, 'Duplicate employee added');
    echo "PASS: duplicate evaluation and employee prevention\n";
    fixture($conn);
    $conn->query("UPDATE evaluation_package_route_steps SET eligible_role='HR Manager' WHERE step_order=2");
    $conn->query("UPDATE evaluation_package_route_steps SET reviewer_user_id=NULL WHERE package_id=2 AND step_order=2");
    check(tryMergeLateMemberPackageIntoSibling($conn, 2, 1) === 1, 'Role-based claim grouping failed');
    echo "PASS: claimed/unclaimed role-based grouping\n";
    fixture($conn, 'President', 'Governance');
    $conn->query('UPDATE evaluation_package_route_steps SET step_order=3 WHERE package_id=1 AND step_order=2');
    $conn->query('UPDATE evaluation_packages SET current_step_order=3 WHERE package_id=1');
    check(tryMergeLateMemberPackageIntoSibling($conn, 2, 1) === 1, 'Equivalent stage with different route orders failed');
    echo "PASS: stage identity independent of route order (VP bypass)\n";
    fixture($conn);
    $conn->query("CREATE TRIGGER fail_merge_audit BEFORE INSERT ON evaluation_package_audit FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Test rollback'");
    $failed = false;
    try { tryMergeLateMemberPackageIntoSibling($conn, 2, 1); }
    catch (mysqli_sql_exception $e) { $failed = true; }
    check($failed, 'Failure fixture did not throw');
    check($conn->query('SELECT status FROM evaluation_packages WHERE package_id=2')->fetch_assoc()['status'] === 'Pending Review', 'Failed merge cancelled source');
    check((int)$conn->query('SELECT COUNT(*) c FROM evaluation_package_members WHERE package_id=1')->fetch_assoc()['c'] === 2, 'Failed merge partially copied members');
    echo "PASS: atomic rollback on merge failure\n";
} finally {
    $conn->query("DROP DATABASE `$database`");
    $conn->close();
}
