<?php
/** CLI regression test for the probationary route and individual 80/20 score.
 * Uses and drops a uniquely named disposable database; never touches HR records.
 * Run: C:\xampp\php\php.exe sample_db_seeds/test_probationary_workflow.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli(getenv('TEST_DB_HOST') ?: '127.0.0.1', getenv('TEST_DB_USER') ?: 'root', getenv('TEST_DB_PASS') ?: '');
$database = 'test_probationary_flow_' . bin2hex(random_bytes(6));
$conn->query("CREATE DATABASE `$database`");
$conn->select_db($database);

$source = file_get_contents(__DIR__ . '/../includes/functions.php');
$route_start = strpos($source, 'function createOrganizationPackageRoute(');
$route_end = strpos($source, '/**', $route_start);
$score_start = strpos($source, 'function calculateEvalTotal(');
$score_end = strpos($source, '/**', $score_start);
eval(substr($source, $route_start, $route_end - $route_start));
eval(substr($source, $score_start, $score_end - $score_start));

function isHumanResourcesPackageDepartment($conn, $department_id) {
    $stmt = $conn->prepare("SELECT 1 FROM departments WHERE department_id = ? AND department_name = 'Human Resources'");
    $stmt->bind_param('i', $department_id); $stmt->execute();
    $found = (bool)$stmt->get_result()->fetch_assoc(); $stmt->close();
    return $found;
}
function expect($condition, $message) { if (!$condition) throw new RuntimeException($message); }

try {
    $conn->query('CREATE TABLE departments (department_id INT PRIMARY KEY, department_name VARCHAR(100))');
    $conn->query("CREATE TABLE evaluation_packages (package_id INT PRIMARY KEY, department_id INT, evaluation_type VARCHAR(20))");
    $conn->query("CREATE TABLE evaluation_package_route_steps (
        package_id INT, step_order INT, step_label VARCHAR(160), step_type VARCHAR(30),
        eligible_role VARCHAR(50) NULL, eligible_rank_category_id INT NULL, action_status VARCHAR(20),
        PRIMARY KEY(package_id, step_order))");
    $conn->query("INSERT INTO departments VALUES (7, 'Human Resources'), (1, 'Acquired Properties')");
    $conn->query("INSERT INTO evaluation_packages VALUES (1, 7, 'Initial'), (2, 1, 'Final')");

    createOrganizationPackageRoute($conn, 1, 0);
    createOrganizationPackageRoute($conn, 2, 0);
    $routes = [];
    foreach ([1, 2] as $package_id) {
        $stmt = $conn->prepare('SELECT step_order, step_label, step_type, eligible_role, eligible_rank_category_id FROM evaluation_package_route_steps WHERE package_id = ? ORDER BY step_order');
        $stmt->bind_param('i', $package_id); $stmt->execute();
        $routes[$package_id] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    }
    expect(array_column($routes[1], 'step_label') === ['HR Supervisor review', 'HR Manager final approval'], 'HR route should be HR Supervisor then HR Manager.');
    expect(array_column($routes[2], 'step_label') === ['Department Supervisor review', 'Department Manager review', 'HR Supervisor review', 'HR Manager final approval'], 'Non-HR route should include department and HR reviewers in order.');
    expect((int)$routes[2][0]['eligible_rank_category_id'] === 4 && (int)$routes[2][1]['eligible_rank_category_id'] === 3, 'Non-HR route must map Supervisor before Manager.');
    expect(count(array_filter(array_merge($routes[1], $routes[2]), static fn($step) => $step['step_type'] === 'Governance')) === 0, 'Probationary routes must not append governance stages.');
    echo "PASS: HR and non-HR probationary routes are ordered and independent of governance configuration\n";

    expect(calculateEvalTotal(3.25, 3.50, 80, 20) === 3.3, 'Probationary final score must use individual KRA 80% and Core Values 20%.');
    echo "PASS: individual probationary score uses the 80/20 weighting\n";
} finally {
    $conn->query("DROP DATABASE `$database`");
    $conn->close();
}
