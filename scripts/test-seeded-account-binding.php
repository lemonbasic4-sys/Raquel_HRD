<?php
// Integration test; only allowed against the disposable department fixture instance.
require_once __DIR__ . '/../includes/seeded-evaluation-forms.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$port = (int)($argv[1] ?? 0);
$conn = new mysqli('127.0.0.1', 'root', '', 'raquel_hris', $port);
$actual = str_replace('\\', '/', rtrim($conn->query('SELECT @@datadir')->fetch_row()[0], '\\/'));
$expected = str_replace('\\', '/', rtrim($argv[2] ?? '', '\\/'));
if ($port === 3306 || strcasecmp($actual, $expected) !== 0 || !str_ends_with($actual, '/.tmp-department-tests/data')) {
    throw new RuntimeException('Refusing to test outside isolated fixture database');
}
// Only an Admin action creates accounts. Use arbitrary credentials to verify no username dependency.
$owners = $conn->query('SELECT DISTINCT employee_id,preferred_role FROM evaluation_seed_template_owners')->fetch_all(MYSQLI_ASSOC);
foreach ($owners as $owner) {
    $id = (int)$owner['employee_id'];
    $role = $owner['preferred_role'];
    $username = 'chosen.' . $id;
    $email = $username . '@admin-test.example';
    $password = 'chosen-password-' . $id;
    $stmt = $conn->prepare("INSERT INTO users (employee_id,username,email,password_hash,full_name,role,branch_id)
        VALUES (?,?,?,?,'Admin managed sample',?,102)");
    $stmt->bind_param('issss', $id, $username, $email, $password, $role);
    $stmt->execute();
    $userId = (int)$conn->insert_id;
    bindSeededEvaluationFormsToAccount($conn, $userId);
    if (bindSeededEvaluationFormsToAccount($conn, $userId) !== 0) {
        throw new RuntimeException('Binding must be idempotent');
    }
}
$missing = $conn->query('SELECT COUNT(*) FROM evaluation_templates WHERE created_by IS NULL')->fetch_row()[0];
if ((int)$missing !== 0) { throw new RuntimeException("$missing imported forms did not bind after manual account creation"); }
$wrong = $conn->query('SELECT COUNT(*) FROM evaluation_templates t JOIN evaluation_seed_template_owners o ON o.form_code=t.form_code
    JOIN users u ON u.user_id=t.created_by WHERE u.employee_id<>o.employee_id')->fetch_row()[0];
if ((int)$wrong !== 0) { throw new RuntimeException('A form was assigned to the wrong reviewer'); }
// An account without fixture ownership must never acquire seeded forms.
$conn->query("INSERT INTO users (username,email,password_hash,full_name,role) VALUES ('unrelated','unrelated@test.example','unchanged','Other','Employee')");
if (bindSeededEvaluationFormsToAccount($conn, (int)$conn->insert_id) !== 0) {
    throw new RuntimeException('Unrelated account acquired a form');
}
echo "PASS Admin-created reviewer accounts bind all imported forms to the intended people\n";
