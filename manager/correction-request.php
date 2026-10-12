<?php
$page_title = 'Employee Correction Report';
$correction_view_role = $correction_view_role ?? 'HR Manager';
require_once '../includes/session-check.php';
checkRole([$correction_view_role]);
require_once '../includes/functions.php';

if (!ensureEmployeeProfileCorrectionRequests($conn)) {
    http_response_code(500);
    exit('Correction reports are unavailable.');
}
$request_id = max(0, (int) ($_GET['id'] ?? 0));
$stmt = $conn->prepare('SELECT r.request_id, r.employee_id, r.field_key, r.description, r.created_at,
        e.first_name, e.last_name, e.employee_code, d.department_name, b.branch_name
    FROM employee_profile_correction_requests r
    JOIN employees e ON e.employee_id = r.employee_id
    LEFT JOIN departments d ON d.department_id = e.department_id
    LEFT JOIN branches b ON b.branch_id = e.branch_id
    WHERE r.request_id = ? LIMIT 1');
$stmt->bind_param('i', $request_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$report) {
    http_response_code(404);
    exit('Correction report not found.');
}
$field_labels = [
    'name' => 'Name', 'position' => 'Position or rank', 'department' => 'Department or branch',
    'employment' => 'Employment status or type', 'hire_date' => 'Hire date',
    'contact' => 'Contact information', 'address' => 'Address',
    'emergency' => 'Emergency contact', 'personal' => 'Other personal detail',
];
$portal = $correction_view_role === 'HR Staff' ? 'staff' : 'manager';
$employee_name = trim($report['first_name'] . ' ' . $report['last_name']);
logAudit($conn, (int) $_SESSION['user_id'], 'VIEW', 'EmployeeProfileCorrectionRequest', $request_id,
    'HR viewed an employee correction report.', ['target_employee_id' => (int) $report['employee_id']]);
require_once '../includes/header.php';
?>
<div class="page-hero mb-4"><span class="text-white-50 small">Employee records</span><h1 class="text-white mb-1">Correction Report #<?php echo $request_id; ?></h1><p class="text-white-50 mb-0">Review the employee's description and follow up through the existing HR process.</p></div>
<div class="card border-0 shadow-sm mb-3"><div class="card-body p-4">
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
        <div><div class="text-muted small">Employee</div><h2 class="h5 mb-1"><?php echo e($employee_name); ?></h2><div class="small text-muted"><?php echo e(getEmployeeDisplayId($report)); ?> · <?php echo e($report['department_name'] ?? 'Department not provided'); ?> · <?php echo e($report['branch_name'] ?? 'Branch not provided'); ?></div></div>
        <div class="text-muted small">Submitted <?php echo e(formatDateTime($report['created_at'])); ?></div>
    </div>
    <div class="mb-3"><div class="text-muted small">Field to review</div><strong><?php echo e($field_labels[$report['field_key']] ?? 'Profile detail'); ?></strong></div>
    <div><div class="text-muted small mb-1">Employee's description</div><div class="border rounded p-3 bg-light" style="white-space:pre-wrap;overflow-wrap:anywhere;"><?php echo e($report['description'] ?: 'No description was provided with this older report.'); ?></div></div>
</div></div>
<p class="small text-muted">This report does not change the employee record. Verify the information and request evidence through an approved HR channel; do not reply with sensitive identifiers in notifications.</p>
<a class="btn btn-outline-success" href="<?php echo BASE_URL . '/' . $portal; ?>/view-employee.php?id=<?php echo (int) $report['employee_id']; ?>"><i class="fas fa-user me-1"></i> View Employee Record</a>
<?php require_once '../includes/footer.php'; ?>
