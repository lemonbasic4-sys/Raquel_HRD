<?php
// Read-only activity monitor shared by every authorized HR role.
ensureAuditTrailSchema($conn);

$viewer_role = $_SESSION['role'] ?? '';
$scope_label = $viewer_role === 'Admin' ? 'System-wide oversight' : ($viewer_role === 'HR Manager' ? 'HR activity oversight' : 'Your authorized activity');
$scope_sql = '1=1';
$scope_params = [];
$scope_types = '';
if ($viewer_role === 'HR Manager') {
    $scope_sql = "COALESCE(u.role, '') IN ('HR Manager', 'HR Supervisor', 'HR Staff')";
} elseif ($viewer_role === 'HR Supervisor' || $viewer_role === 'HR Staff') {
    $scope_sql = 'al.user_id = ?';
    $scope_params[] = (int) $_SESSION['user_id'];
    $scope_types .= 'i';
}

$filters = [
    'search' => trim($_GET['search'] ?? ''),
    'module' => trim($_GET['module'] ?? ''),
    'action' => trim($_GET['action'] ?? ''),
    'status' => trim($_GET['status'] ?? ''),
    'actor' => (int) ($_GET['actor'] ?? 0),
    'employee' => trim($_GET['employee'] ?? ''),
    'branch' => (int) ($_GET['branch'] ?? 0),
    'department' => (int) ($_GET['department'] ?? 0),
    'date_from' => trim($_GET['date_from'] ?? ''),
    'date_to' => trim($_GET['date_to'] ?? ''),
];
$allowed_modules = ['Employee Management', 'Performance & Evaluation', 'Career Progression', 'User & Access Management', 'System Administration', 'System Activity'];
$allowed_actions = ['CREATE', 'UPDATE', 'DELETE', 'APPROVE', 'REJECT', 'REQUEST_CHANGES', 'ENDORSE', 'IMPORT', 'ROLE_CHANGE', 'CHANGE_PASSWORD'];
$allowed_statuses = ['Successful', 'Failed', 'Cancelled'];
if (!in_array($filters['module'], $allowed_modules, true)) $filters['module'] = '';
if (!in_array($filters['action'], $allowed_actions, true)) $filters['action'] = '';
if (!in_array($filters['status'], $allowed_statuses, true)) $filters['status'] = '';

$conditions = ["al.action_type NOT IN ('LOGIN', 'LOGOUT')", $scope_sql];
$params = $scope_params;
$types = $scope_types;
foreach (['module' => 'al.module_name', 'action' => 'al.action_type', 'status' => 'al.action_status'] as $key => $column) {
    if ($filters[$key] !== '') { $conditions[] = "$column = ?"; $params[] = $filters[$key]; $types .= 's'; }
}
if ($filters['actor']) { $conditions[] = 'al.user_id = ?'; $params[] = $filters['actor']; $types .= 'i'; }
if ($filters['branch']) { $conditions[] = 'al.branch_id = ?'; $params[] = $filters['branch']; $types .= 'i'; }
if ($filters['department']) { $conditions[] = 'al.department_id = ?'; $params[] = $filters['department']; $types .= 'i'; }
if ($filters['employee'] !== '') { $conditions[] = "CONCAT(COALESCE(e.first_name,''), ' ', COALESCE(e.last_name,''), ' ', COALESCE(e.employee_code,'')) LIKE ?"; $params[] = '%' . $filters['employee'] . '%'; $types .= 's'; }
if ($filters['date_from'] !== '') { $conditions[] = 'DATE(al.timestamp) >= ?'; $params[] = $filters['date_from']; $types .= 's'; }
if ($filters['date_to'] !== '') { $conditions[] = 'DATE(al.timestamp) <= ?'; $params[] = $filters['date_to']; $types .= 's'; }
if ($filters['search'] !== '') { $conditions[] = "CONCAT_WS(' ', al.action_type, al.entity_type, al.module_name, al.details, u.full_name, e.first_name, e.last_name, e.employee_code) LIKE ?"; $params[] = '%' . $filters['search'] . '%'; $types .= 's'; }
$where = implode(' AND ', $conditions);

$base_from = "FROM audit_logs al LEFT JOIN users u ON u.user_id = al.user_id LEFT JOIN employees e ON e.employee_id = al.target_employee_id LEFT JOIN branches b ON b.branch_id = al.branch_id LEFT JOIN departments d ON d.department_id = al.department_id";
$query = "SELECT al.*, u.full_name, u.role, e.employee_code, e.first_name, e.last_name, b.branch_name, d.department_name $base_from WHERE $where ORDER BY al.timestamp DESC LIMIT 250";
$stmt = $conn->prepare($query);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function auditCount($conn, $base_from, $where, $types, $params, $extra = ''): int {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total $base_from WHERE $where $extra");
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute(); $count = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0); $stmt->close();
    return $count;
}
$total = auditCount($conn, $base_from, $where, $types, $params);
$today_where = $where . ' AND DATE(al.timestamp) = CURDATE()';
$today = auditCount($conn, $base_from, $today_where, $types, $params);
$employee_changes = auditCount($conn, $base_from, $where . " AND al.module_name = 'Employee Management'", $types, $params);
$evaluations = auditCount($conn, $base_from, $where . " AND al.module_name = 'Performance & Evaluation'", $types, $params);
$movements = auditCount($conn, $base_from, $where . " AND al.module_name = 'Career Progression'", $types, $params);
$admin_changes = auditCount($conn, $base_from, $where . " AND al.module_name IN ('User & Access Management', 'System Administration')", $types, $params);
$actor_stmt = $conn->prepare("SELECT DISTINCT u.user_id, u.full_name, u.role FROM audit_logs al JOIN users u ON u.user_id = al.user_id WHERE al.action_type NOT IN ('LOGIN', 'LOGOUT') AND $scope_sql ORDER BY u.full_name");
if ($scope_params) $actor_stmt->bind_param($scope_types, ...$scope_params);
$actor_stmt->execute();
$actors = $actor_stmt->get_result();
$branches = $conn->query('SELECT branch_id, branch_name FROM branches ORDER BY branch_name');
$departments = $conn->query('SELECT department_id, department_name FROM departments ORDER BY department_name');
?>
<style>
.audit-hero { background:linear-gradient(135deg,#082e06 0%,#234d08 62%,#59751e 100%); border-left:6px solid #bd9414; border-radius:0 20px 20px 0; padding:28px; color:#fff; position:relative; overflow:hidden; box-shadow:0 8px 24px rgba(8,46,6,.18); }
.audit-hero:before { content:''; position:absolute; inset:0; background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E'); pointer-events:none; }
.audit-hero:after { content:''; position:absolute; width:280px; height:280px; right:-90px; top:-150px; border:45px solid rgba(203,161,53,.18); border-radius:50%; }
.audit-stat { background:#edf3df; border:1px solid #b9c98f; border-radius:16px; padding:16px; height:100%; box-shadow:0 8px 24px rgba(8,46,6,.08); }
.audit-stat .value { color:#234d08; font-size:1.45rem; font-weight:800; }.audit-stat .label { color:#59684d; font-size:.75rem; text-transform:uppercase; letter-spacing:.06em; }
.audit-filter { background:#f4f7ef; border:1px solid #bdcba9; border-radius:16px; padding:16px; }.audit-event { background:#fff; border:1px solid #cbd8b9; border-radius:16px; margin-bottom:12px; overflow:hidden; box-shadow:0 5px 16px rgba(8,46,6,.06); }
.audit-event summary { list-style:none; cursor:pointer; padding:17px; display:grid; grid-template-columns:44px 1fr auto; gap:13px; align-items:start; }.audit-event summary::-webkit-details-marker{display:none}.audit-event summary:after{content:'+';font-size:1.2rem;color:#17666d}.audit-event[open] summary:after{content:'-'}
.audit-icon { width:40px; height:40px; border-radius:12px; display:grid; place-items:center; background:#e7eedf; color:#234d08; }.audit-meta { color:#66745f; font-size:.78rem; }.audit-pill { border-radius:99px; padding:4px 9px; font-size:.7rem; font-weight:700; background:#e7eedf; color:#234d08; border:1px solid #bdcba9; }.audit-details { border-top:1px solid #dfe8d3; padding:17px 17px 19px 73px; background:#f8faf5; }.audit-value { border-left:3px solid #bd9414; padding:8px 12px; background:#fff8e1; border-radius:0 8px 8px 0; font-size:.84rem; }
@media(max-width:575px){.audit-hero{padding:22px}.audit-event summary{grid-template-columns:40px 1fr}.audit-event summary > .audit-pill{grid-column:2}.audit-details{padding-left:17px}}
</style>
<div class="audit-hero fadeup mb-4">
    <div class="position-relative" style="z-index:1"><div class="small text-uppercase" style="letter-spacing:.12em;color:#f4dc9d"><?php echo e($scope_label); ?></div><h2 class="fw-bold mb-1">System Activity Monitor</h2><p class="mb-0 text-white-50">Immutable records of meaningful HRIS and Employee Portal changes. Login and logout events are intentionally excluded.</p></div>
</div>
<div class="row g-3 mb-4 fadeup-1">
<?php foreach ([['Total Activities',$total,'fa-wave-square'],["Today's Activities",$today,'fa-calendar-day'],['Employee Changes',$employee_changes,'fa-id-card'],['Performance Activities',$evaluations,'fa-clipboard-check'],['Career Movements',$movements,'fa-route'],['Administrative Changes',$admin_changes,'fa-shield-alt']] as $stat): ?>
<div class="col-6 col-lg-2"><div class="audit-stat"><i class="fas <?php echo $stat[2]; ?> mb-2" style="color:#d3973e"></i><div class="value"><?php echo number_format($stat[1]); ?></div><div class="label"><?php echo e($stat[0]); ?></div></div></div>
<?php endforeach; ?></div>
<div class="audit-filter mb-4 fadeup-2"><form method="get" class="row g-2 align-items-end"><div class="col-md-4"><label class="form-label small mb-1">Search investigation</label><input class="form-control form-control-sm" name="search" value="<?php echo e($filters['search']); ?>" placeholder="Employee, actor, action, or description"></div><div class="col-6 col-md-2"><label class="form-label small mb-1">Module</label><select class="form-select form-select-sm" name="module"><option value="">All activity</option><?php foreach($allowed_modules as $module): ?><option value="<?php echo e($module); ?>" <?php echo $filters['module']===$module?'selected':''; ?>><?php echo e($module); ?></option><?php endforeach; ?></select></div><div class="col-6 col-md-2"><label class="form-label small mb-1">Action</label><select class="form-select form-select-sm" name="action"><option value="">All actions</option><?php foreach($allowed_actions as $action): ?><option value="<?php echo e($action); ?>" <?php echo $filters['action']===$action?'selected':''; ?>><?php echo e(ucwords(strtolower(str_replace('_', ' ', $action)))); ?></option><?php endforeach; ?></select></div><div class="col-6 col-md-2"><label class="form-label small mb-1">Status</label><select class="form-select form-select-sm" name="status"><option value="">Any status</option><?php foreach($allowed_statuses as $status): ?><option value="<?php echo e($status); ?>" <?php echo $filters['status']===$status?'selected':''; ?>><?php echo e($status); ?></option><?php endforeach; ?></select></div><div class="col-6 col-md-2"><label class="form-label small mb-1">From</label><input class="form-control form-control-sm" type="date" name="date_from" value="<?php echo e($filters['date_from']); ?>"></div><div class="col-6 col-md-2"><label class="form-label small mb-1">To</label><input class="form-control form-control-sm" type="date" name="date_to" value="<?php echo e($filters['date_to']); ?>"></div><div class="col-md-3"><label class="form-label small mb-1">Actor</label><select class="form-select form-select-sm" name="actor"><option value="0">All authorized actors</option><?php while($actor=$actors->fetch_assoc()): ?><option value="<?php echo (int)$actor['user_id']; ?>" <?php echo $filters['actor']==$actor['user_id']?'selected':''; ?>><?php echo e($actor['full_name'].' - '.$actor['role']); ?></option><?php endwhile; ?></select></div><div class="col-md-3"><label class="form-label small mb-1">Employee</label><input class="form-control form-control-sm" name="employee" value="<?php echo e($filters['employee']); ?>" placeholder="Name or employee ID"></div><div class="col-md-2"><label class="form-label small mb-1">Branch</label><select class="form-select form-select-sm" name="branch"><option value="0">All branches</option><?php while($branch=$branches->fetch_assoc()): ?><option value="<?php echo (int)$branch['branch_id']; ?>" <?php echo $filters['branch']==$branch['branch_id']?'selected':''; ?>><?php echo e($branch['branch_name']); ?></option><?php endwhile; ?></select></div><div class="col-md-2"><label class="form-label small mb-1">Department</label><select class="form-select form-select-sm" name="department"><option value="0">All departments</option><?php while($department=$departments->fetch_assoc()): ?><option value="<?php echo (int)$department['department_id']; ?>" <?php echo $filters['department']==$department['department_id']?'selected':''; ?>><?php echo e($department['department_name']); ?></option><?php endwhile; ?></select></div><div class="col-md-2"><button class="btn btn-sm btn-primary w-100"><i class="fas fa-filter me-1"></i>Investigate</button></div><div class="col-md-2"><a class="btn btn-sm btn-outline-secondary w-100" href="audit-trail.php">Clear filters</a></div></form></div>
<div class="d-flex justify-content-between align-items-center mb-3"><div><h5 class="mb-0">Activity timeline</h5><small class="text-muted">Showing <?php echo count($logs); ?> of <?php echo number_format($total); ?> matching records</small></div><span class="small text-muted"><i class="fas fa-lock me-1"></i>Read-only historical record</span></div>
<?php if (!$logs): ?><div class="text-center text-muted bg-light rounded-4 py-5"><i class="fas fa-inbox fa-2x mb-3"></i><br>No meaningful activity matches these filters.</div><?php endif; ?>
<?php foreach ($logs as $log): $target=trim(($log['employee_code'] ? $log['employee_code'].' - ' : '').trim(($log['first_name']??'').' '.($log['last_name']??''))); $icon=str_contains(strtolower($log['module_name']), 'performance')?'fa-clipboard-check':(str_contains(strtolower($log['module_name']), 'career')?'fa-route':'fa-pen-to-square'); ?>
<details class="audit-event"><summary><div class="audit-icon"><i class="fas <?php echo $icon; ?>"></i></div><div><div class="fw-bold"><?php echo e(ucwords(strtolower(str_replace('_',' ',$log['action_type']))).' '.($log['entity_type'] ?? 'Record')); ?></div><div class="audit-meta"><?php echo e($log['full_name'] ?? 'System'); ?> <?php echo $log['role'] ? ' - '.e($log['role']) : ''; ?> | <?php echo e($log['module_name'] ?? 'System Activity'); ?> | <?php echo formatDateTime($log['timestamp']); ?></div><?php if($target): ?><div class="small mt-1"><i class="fas fa-user me-1 text-muted"></i><?php echo e($target); ?></div><?php endif; ?></div><span class="audit-pill"><?php echo e($log['action_status'] ?? 'Successful'); ?></span></summary><div class="audit-details"><div class="mb-3"><strong>What happened?</strong><div class="text-muted small mt-1"><?php echo e($log['details'] ?: 'System activity recorded.'); ?></div></div><div class="row g-3"><div class="col-md-6"><div class="audit-value"><strong>Previous value</strong><br><?php echo e($log['previous_value'] ?: 'Not captured for this record'); ?></div></div><div class="col-md-6"><div class="audit-value"><strong>New value</strong><br><?php echo e($log['new_value'] ?: 'Not captured for this record'); ?></div></div><div class="col-md-6 small"><strong>Audit ID:</strong> AUD-<?php echo date('Y',strtotime($log['timestamp'])); ?>-<?php echo str_pad((string)$log['log_id'],6,'0',STR_PAD_LEFT); ?><br><strong>Target:</strong> <?php echo e($target ?: $log['entity_type'].' #'.$log['entity_id']); ?><br><strong>Branch / Department:</strong> <?php echo e(trim(($log['branch_name']??'').' '.($log['department_name']??'')) ?: 'Not applicable'); ?></div><div class="col-md-6 small"><strong>Actor:</strong> <?php echo e($log['full_name'] ?? 'System'); ?><br><strong>Role:</strong> <?php echo e($log['role'] ?? 'System'); ?><br><strong>Source:</strong> <?php echo e($log['ip_address'] ?? 'Not recorded'); ?> | <?php echo e($log['user_agent'] ?? 'Legacy record'); ?></div></div></div></details>
<?php endforeach; ?>
