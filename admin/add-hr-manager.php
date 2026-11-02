<?php
$page_title = 'Set Up HR Manager';
require_once '../includes/session-check.php';
checkRole(['Admin']);

$hr = $conn->query("SELECT department_id FROM departments WHERE department_name = 'Human Resources' AND is_active = 1 AND deleted_at IS NULL LIMIT 1")->fetch_assoc();
$department_id = (int) ($hr['department_id'] ?? 0);
$titles = [];
if ($department_id) {
    $stmt = $conn->prepare("SELECT jt.job_title_id, jt.job_title, jt.rank_category_id FROM job_titles jt JOIN rank_categories rc ON rc.rank_category_id = jt.rank_category_id WHERE jt.department_id = ? AND jt.is_active = 1 AND rc.is_active = 1 AND (LOWER(jt.job_title) LIKE '%manager%' OR LOWER(rc.rank_name) LIKE '%manager%') ORDER BY jt.job_title");
    $stmt->bind_param('i', $department_id);
    $stmt->execute();
    $titles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
$branches = $conn->query("SELECT branch_id, branch_name FROM branches WHERE is_active = 1 AND deleted_at IS NULL ORDER BY branch_name")->fetch_all(MYSQLI_ASSOC);
$existing = $conn->query("SELECT e.employee_id FROM employees e JOIN departments d ON d.department_id = e.department_id JOIN job_titles jt ON jt.job_title_id = e.job_title_id JOIN rank_categories rc ON rc.rank_category_id = e.rank_category_id WHERE d.department_name = 'Human Resources' AND (LOWER(jt.job_title) LIKE '%manager%' OR LOWER(rc.rank_name) LIKE '%manager%') AND e.deleted_at IS NULL LIMIT 1")->fetch_assoc();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $middle = trim($_POST['middle_name'] ?? '');
    $extension = trim($_POST['name_extension'] ?? '');
    $code = strtoupper(trim($_POST['employee_code'] ?? ''));
    $email = trim($_POST['email'] ?? '');
    $hire = trim($_POST['hire_date'] ?? '');
    $title_id = (int) ($_POST['job_title_id'] ?? 0);
    $branch_id = (int) ($_POST['branch_id'] ?? 0);
    $type = $_POST['employment_type'] ?? '';
    $title = null;
    foreach ($titles as $candidate) if ((int) $candidate['job_title_id'] === $title_id) $title = $candidate;
    $valid_branch = false;
    foreach ($branches as $branch) if ((int) $branch['branch_id'] === $branch_id) $valid_branch = true;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $hire);

    if ($existing) $errors[] = 'An HR Manager employee already exists. Admin setup is available only once.';
    if (!$department_id || !$title) $errors[] = 'Select an active Human Resources manager position.';
    if (!$valid_branch) $errors[] = 'Select an active branch.';
    if ($first === '' || $last === '' || $code === '') $errors[] = 'First name, last name, and employee ID are required.';
    if (strlen($code) > 30) $errors[] = 'Employee ID must be 30 characters or fewer.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) $errors[] = 'Enter a valid email address.';
    if (!$date || $date->format('Y-m-d') !== $hire) $errors[] = 'Enter a valid hire date.';
    if (!in_array($type, ['Full-time', 'Part-time'], true)) $errors[] = 'Select an employment type.';

    if (!$errors) {
        try {
            $conn->begin_transaction();
            // Lock the department row so two setup submissions cannot create two managers.
            $lock = $conn->prepare('SELECT department_id FROM departments WHERE department_id = ? FOR UPDATE');
            $lock->bind_param('i', $department_id);
            $lock->execute();
            $lock->close();
            $check = $conn->prepare("SELECT e.employee_id FROM employees e JOIN job_titles jt ON jt.job_title_id = e.job_title_id JOIN rank_categories rc ON rc.rank_category_id = e.rank_category_id WHERE e.department_id = ? AND (LOWER(jt.job_title) LIKE '%manager%' OR LOWER(rc.rank_name) LIKE '%manager%') AND e.deleted_at IS NULL LIMIT 1");
            $check->bind_param('i', $department_id);
            $check->execute();
            $already = $check->get_result()->fetch_assoc();
            $check->close();
            if ($already) throw new RuntimeException('An HR Manager employee already exists.');
            $rank_id = (int) $title['rank_category_id'];
            $position = $title['job_title'];
            $stmt = $conn->prepare("INSERT INTO employees (employee_code, first_name, last_name, middle_name, name_extension, hire_date, job_title, job_title_id, department_id, rank_category_id, branch_id, employment_status, employment_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Regular', ?)");
            $stmt->bind_param('sssssssiiiis', $code, $first, $last, $middle, $extension, $hire, $position, $title_id, $department_id, $rank_id, $branch_id, $type);
            $stmt->execute();
            $employee_id = $stmt->insert_id;
            $stmt->close();
            $stmt = $conn->prepare('INSERT INTO employee_contacts (employee_id, personal_email) VALUES (?, ?)');
            $stmt->bind_param('is', $employee_id, $email);
            $stmt->execute();
            $stmt->close();
            logAudit($conn, (int) $_SESSION['user_id'], 'CREATE', 'Employee', $employee_id, "Admin created initial HR Manager employee: $first $last");
            $conn->commit();
            redirectWith(BASE_URL . '/admin/users.php?setup_employee=' . $employee_id, 'success', 'HR Manager employee created. Add their user account to grant HRIS access.');
        } catch (Throwable $error) {
            $conn->rollback();
            $errors[] = $error instanceof mysqli_sql_exception && $error->getCode() === 1062 ? 'Employee ID already exists.' : 'Could not create the HR Manager employee. Check the details and try again.';
        }
    }
}

require_once '../includes/header.php';
?>
<style>
    .hr-setup { max-width: 980px; margin: 0 auto; padding: 1.5rem 1rem 3rem; }
    .hr-setup .setup-hero { padding: 1.7rem 2rem; border-radius: 16px; background: linear-gradient(135deg, var(--hero-from, #123d28), var(--hero-mid, #18502e), var(--hero-to, #236337)); color: #fff; box-shadow: 0 12px 28px rgba(12, 58, 24, .16); }
    .hr-setup .setup-eyebrow { font-size: .72rem; letter-spacing: .09em; text-transform: uppercase; color: #d5e8d1; font-weight: 700; }
    .hr-setup .setup-hero h3 { color: #fff; font-weight: 750; margin: .35rem 0; }
    .hr-setup .setup-hero p { color: #dbe9d8; max-width: 650px; margin: 0; }
    .hr-setup .setup-hero .btn { border-color: rgba(255,255,255,.7); color: #fff; }
    .hr-setup .setup-hero .btn:hover { background: #fff; color: #174b23; }
    .hr-setup .setup-progress { display: flex; gap: 1rem; margin: 1.25rem 0; }
    .hr-setup .setup-step { flex: 1; display: flex; align-items: center; gap: .7rem; padding: .8rem 1rem; border: 1px solid #dfe9dd; border-radius: 12px; background: #fff; color: #426346; font-size: .86rem; font-weight: 650; }
    .hr-setup .setup-step i { display: grid; place-items: center; width: 32px; height: 32px; border-radius: 50%; background: #e7f3e4; color: #257237; }
    .hr-setup .setup-step.next { color: #788a78; }
    .hr-setup .setup-card { border: 1px solid #dfe8dc; border-radius: 16px; background: #fff; box-shadow: 0 8px 24px rgba(21, 66, 27, .07); overflow: hidden; }
    .hr-setup .setup-section { padding: 1.5rem 1.7rem; }
    .hr-setup .setup-section + .setup-section { border-top: 1px solid #e8eee6; }
    .hr-setup .setup-section h5 { color: #244c2d; font-weight: 750; margin-bottom: .25rem; }
    .hr-setup .setup-section p { color: #708072; font-size: .84rem; margin-bottom: 1.15rem; }
    .hr-setup .form-label { color: #344c37; font-size: .82rem; font-weight: 700; }
    .hr-setup .form-control, .hr-setup .form-select { min-height: 43px; border-color: #d6e2d3; border-radius: 9px; }
    .hr-setup .form-control:focus, .hr-setup .form-select:focus { border-color: #4c9554; box-shadow: 0 0 0 .2rem rgba(50, 130, 58, .12); }
    .hr-setup .form-control[readonly] { background: #f3f7f1; color: #57715a; }
    .hr-setup .setup-actions { padding: 1.1rem 1.7rem; border-top: 1px solid #e8eee6; background: #f8fbf7; display: flex; justify-content: flex-end; }
    .hr-setup .setup-actions .btn { padding: .65rem 1.25rem; font-weight: 700; border-radius: 9px; }
    @media (max-width: 640px) { .hr-setup .setup-hero { padding: 1.3rem; } .hr-setup .setup-progress { flex-direction: column; gap: .55rem; } .hr-setup .setup-section { padding: 1.2rem; } .hr-setup .setup-actions { padding: 1rem 1.2rem; } .hr-setup .setup-actions .btn { width: 100%; } }
</style>
<div class="hr-setup">
    <div class="setup-hero">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
            <div><div class="setup-eyebrow">System Admin · Initial setup</div><h3><i class="fas fa-user-tie me-2"></i>Add HR Manager Employee</h3><p>Create the first Human Resources manager employee, then give them HRIS access in User Management.</p></div>
            <a href="<?php echo BASE_URL; ?>/admin/users.php" class="btn btn-outline-light"><i class="fas fa-arrow-left me-2"></i>User Management</a>
        </div>
    </div>
    <div class="setup-progress"><div class="setup-step"><i class="fas fa-id-card"></i><span>1. Create employee record</span></div><div class="setup-step next"><i class="fas fa-key"></i><span>2. Create HRIS user account</span></div></div>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endforeach; ?>
    <?php if ($existing): ?>
        <div class="alert alert-info">An HR Manager employee already exists. Use User Management to give them access.</div>
    <?php elseif (!$department_id || !$titles || !$branches): ?>
        <div class="alert alert-warning">Set up an active Human Resources department, manager position, and branch before creating this employee.</div>
    <?php else: ?>
    <form method="post" class="setup-card">
        <?php echo csrfField(); ?>
        <section class="setup-section"><h5><i class="fas fa-address-card me-2"></i>Basic identity</h5><p>Enter the employee's name and email used for their account.</p>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">First name *</label><input class="form-control" name="first_name" maxlength="100" required value="<?php echo e($_POST['first_name'] ?? ''); ?>"></div>
            <div class="col-md-6"><label class="form-label">Last name *</label><input class="form-control" name="last_name" maxlength="100" required value="<?php echo e($_POST['last_name'] ?? ''); ?>"></div>
            <div class="col-md-6"><label class="form-label">Middle name</label><input class="form-control" name="middle_name" maxlength="100" value="<?php echo e($_POST['middle_name'] ?? ''); ?>"></div>
            <div class="col-md-6"><label class="form-label">Name extension</label><input class="form-control" name="name_extension" maxlength="10" value="<?php echo e($_POST['name_extension'] ?? ''); ?>"></div>
            <div class="col-md-6"><label class="form-label">Email *</label><input class="form-control" type="email" name="email" maxlength="100" required value="<?php echo e($_POST['email'] ?? ''); ?>"></div>
        </div></section>
        <section class="setup-section"><h5><i class="fas fa-briefcase me-2"></i>Employment Details</h5><p>The department and Regular status are set for the initial HR Manager.</p>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Employee ID *</label><input class="form-control" name="employee_code" maxlength="30" required value="<?php echo e($_POST['employee_code'] ?? ''); ?>"></div>
            <div class="col-md-6"><label class="form-label">Hire date *</label><input class="form-control" type="date" name="hire_date" required value="<?php echo e($_POST['hire_date'] ?? ''); ?>"></div>
            <div class="col-md-6"><label class="form-label">Department</label><input class="form-control" value="Human Resources" readonly></div>
            <div class="col-md-6"><label class="form-label">Position *</label><select class="form-select" name="job_title_id" required><option value="">Select manager position</option><?php foreach ($titles as $title): ?><option value="<?php echo (int) $title['job_title_id']; ?>" <?php echo (int) ($_POST['job_title_id'] ?? 0) === (int) $title['job_title_id'] ? 'selected' : ''; ?>><?php echo e($title['job_title']); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Branch *</label><select class="form-select" name="branch_id" required><option value="">Select branch</option><?php foreach ($branches as $branch): ?><option value="<?php echo (int) $branch['branch_id']; ?>" <?php echo (int) ($_POST['branch_id'] ?? 0) === (int) $branch['branch_id'] ? 'selected' : ''; ?>><?php echo e($branch['branch_name']); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Employment type *</label><select class="form-select" name="employment_type"><option value="Full-time">Full-time</option><option value="Part-time" <?php echo ($_POST['employment_type'] ?? '') === 'Part-time' ? 'selected' : ''; ?>>Part-time</option></select></div>
            <div class="col-md-6"><label class="form-label">Employment status</label><input class="form-control" value="Regular" readonly></div>
        </div></section>
        <div class="setup-actions"><button class="btn btn-primary" type="submit"><i class="fas fa-arrow-right me-2"></i>Create Employee and Continue</button></div>
    </form>
    <?php endif; ?>
</div>
<?php require_once '../includes/footer.php'; ?>
