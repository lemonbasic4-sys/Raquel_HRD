<?php
// Read-only activity monitor shared by every authorized HR role.
ensureAuditTrailSchema($conn);

$viewer_role = $_SESSION['role'] ?? '';
$is_admin = $viewer_role === 'Admin';
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

$filter_request = defined('AUDIT_TRAIL_EXPORT_ONLY') ? $_POST : $_GET;
$filters = [
    'search' => trim((string) ($filter_request['search'] ?? '')),
    'module' => trim((string) ($filter_request['module'] ?? '')),
    'action' => trim((string) ($filter_request['action'] ?? '')),
    'status' => trim((string) ($filter_request['status'] ?? '')),
    'actor' => max(0, (int) ($filter_request['actor'] ?? 0)),
    'employee' => trim((string) ($filter_request['employee'] ?? '')),
    'branch' => max(0, (int) ($filter_request['branch'] ?? 0)),
    'department' => max(0, (int) ($filter_request['department'] ?? 0)),
    'date_from' => trim((string) ($filter_request['date_from'] ?? '')),
    'date_to' => trim((string) ($filter_request['date_to'] ?? '')),
    'ip_address' => trim((string) ($filter_request['ip_address'] ?? '')),
    'entity_id' => max(0, (int) ($filter_request['entity_id'] ?? 0)),
    'record_type' => trim((string) ($filter_request['record_type'] ?? '')),
    'sort' => trim((string) ($filter_request['sort'] ?? 'DESC')),
    'page_size' => max(1, (int) ($filter_request['page_size'] ?? ($is_admin ? 10 : 50))),
    'view' => trim((string) ($filter_request['view'] ?? 'all')),
];
$allowed_modules = ['Authentication & Access', 'Employee Management', 'Performance & Evaluation', 'Career Progression', 'User & Access Management', 'System Administration', 'System Activity'];
$allowed_actions = ['LOGIN', 'LOGOUT', 'ACCESS_DENIED', 'SESSION_REVOKED', 'ACCOUNT_HOLD', 'ACCOUNT_HOLD_RELEASED', 'CREATE', 'UPDATE', 'DELETE', 'APPROVE', 'REJECT', 'RETURN', 'FINALIZE', 'SIGN', 'BACKUP_FAILED', 'EDIT_STEP', 'REQUEST_CHANGES', 'ENDORSE', 'IMPORT', 'ROLE_CHANGE', 'CHANGE_PASSWORD', 'EXPORT'];
$recorded_actions = $conn->query("SELECT DISTINCT action_type FROM audit_logs WHERE action_type <> '' ORDER BY action_type");
if ($recorded_actions) {
    foreach ($recorded_actions as $recorded_action) $allowed_actions[] = (string) $recorded_action['action_type'];
    $allowed_actions = array_values(array_unique($allowed_actions));
}
$recorded_modules = $conn->query("SELECT DISTINCT module_name FROM audit_logs WHERE module_name IS NOT NULL AND module_name <> '' ORDER BY module_name");
if ($recorded_modules) {
    foreach ($recorded_modules as $recorded_module) $allowed_modules[] = (string) $recorded_module['module_name'];
    $allowed_modules = array_values(array_unique($allowed_modules));
}
if ($is_admin && $conn->query("SHOW TABLES LIKE 'evaluation_package_audit'")->num_rows > 0) {
    $package_actions = $conn->query('SELECT DISTINCT action FROM evaluation_package_audit ORDER BY action');
    if ($package_actions) {
        foreach ($package_actions as $package_action) $allowed_actions[] = (string) $package_action['action'];
        $allowed_actions = array_values(array_unique($allowed_actions));
    }
}
$allowed_statuses = ['Successful', 'Failed', 'Cancelled'];
$allowed_views = ['all', 'security', 'users', 'employees', 'organization', 'templates', 'evaluations', 'career', 'backups', 'settings', 'system'];
$filter_errors = [];
if (!in_array($filters['module'], $allowed_modules, true)) $filters['module'] = '';
if (!in_array($filters['action'], $allowed_actions, true)) $filters['action'] = '';
if (!in_array($filters['status'], $allowed_statuses, true)) $filters['status'] = '';
if (!in_array($filters['view'], $allowed_views, true)) $filters['view'] = 'all';
if (!in_array($filters['sort'], ['ASC', 'DESC'], true)) $filters['sort'] = 'DESC';
if (!in_array($filters['page_size'], [10, 25, 50], true)) $filters['page_size'] = $is_admin ? 10 : 50;
foreach (['date_from', 'date_to'] as $date_key) {
    $date = DateTime::createFromFormat('!Y-m-d', $filters[$date_key]);
    if ($filters[$date_key] !== '' && (!$date || $date->format('Y-m-d') !== $filters[$date_key])) {
        $filter_errors[] = 'An invalid ' . ($date_key === 'date_from' ? 'start' : 'end') . ' date was ignored.';
        $filters[$date_key] = '';
    }
}
if ($filters['date_from'] !== '' && $filters['date_to'] !== '' && $filters['date_from'] > $filters['date_to']) {
    $filter_errors[] = 'The start date is after the end date.';
}

$page_size = $filters['page_size'];
$page = max(1, (int) ($filter_request['page'] ?? 1));

$conditions = [$scope_sql];
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
if ($filters['search'] !== '') { $conditions[] = "CONCAT_WS(' ', al.log_id, al.entity_id, al.action_type, al.entity_type, al.module_name, al.details, al.ip_address, al.user_agent, u.full_name, e.first_name, e.last_name, e.employee_code) LIKE ?"; $params[] = '%' . $filters['search'] . '%'; $types .= 's'; }
if ($filters['ip_address'] !== '') { $conditions[] = 'al.ip_address LIKE ?'; $params[] = '%' . $filters['ip_address'] . '%'; $types .= 's'; }
if ($filters['entity_id']) { $conditions[] = 'al.entity_id = ?'; $params[] = $filters['entity_id']; $types .= 'i'; }
if ($filters['record_type'] !== '') { $conditions[] = 'al.entity_type = ?'; $params[] = $filters['record_type']; $types .= 's'; }
if ($is_admin && $filters['view'] === 'security') {
    $conditions[] = "(al.module_name = 'Authentication & Access' OR al.action_type IN ('ROLE_CHANGE', 'CHANGE_PASSWORD', 'ACCESS_DENIED', 'SESSION_REVOKED'))";
} elseif ($is_admin && $filters['view'] === 'users') {
    $conditions[] = "al.module_name = 'User & Access Management'";
} elseif ($is_admin && $filters['view'] === 'employees') {
    $conditions[] = "(al.module_name = 'Employee Management' OR al.entity_type = 'EmployeeEditHistory')";
} elseif ($is_admin && $filters['view'] === 'organization') {
    $conditions[] = "al.entity_type IN ('Branch', 'Department', 'Position', 'Organization Structure')";
} elseif ($is_admin && $filters['view'] === 'templates') {
    $conditions[] = "al.entity_type = 'Template'";
} elseif ($is_admin && $filters['view'] === 'evaluations') {
    $conditions[] = "al.module_name = 'Performance & Evaluation'";
} elseif ($is_admin && $filters['view'] === 'career') {
    $conditions[] = "al.module_name = 'Career Progression'";
} elseif ($is_admin && $filters['view'] === 'backups') {
    $conditions[] = "al.entity_type IN ('Backup', 'AutoBackup', 'BackupSchedule')";
} elseif ($is_admin && $filters['view'] === 'settings') {
    $conditions[] = "al.entity_type IN ('Settings', 'Config', 'BackupSchedule')";
} elseif ($is_admin && $filters['view'] === 'system') {
    $conditions[] = "(al.module_name = 'System Administration' OR al.entity_type IN ('Backup', 'AutoBackup'))";
}
$where = implode(' AND ', $conditions);

$audit_source = 'audit_logs al';
if ($is_admin) {
    $source_queries = ["SELECT log_id, user_id, action_type, entity_type, module_name, entity_id, target_employee_id, details, previous_value, new_value, branch_id, department_id, ip_address, user_agent, action_status, timestamp FROM audit_logs"];
    if ($conn->query("SHOW TABLES LIKE 'evaluation_package_audit'")->num_rows > 0) {
        $source_queries[] = "SELECT 1000000000 + pa.package_audit_id AS log_id, pa.user_id, pa.action AS action_type,
            'EvaluationPackageHistory' AS entity_type, 'Performance & Evaluation' AS module_name,
            pa.package_id AS entity_id, NULL AS target_employee_id,
            CONCAT('Package #', pa.package_id, ' lifecycle event: ', REPLACE(pa.action, '_', ' '), '.') AS details,
            NULL AS previous_value, NULL AS new_value, NULL AS branch_id, NULL AS department_id,
            NULL AS ip_address, NULL AS user_agent,
            CASE WHEN pa.action LIKE '%CANCEL%' THEN 'Cancelled' ELSE 'Successful' END AS action_status,
            pa.created_at AS timestamp
            FROM evaluation_package_audit pa";
    }
    if ($conn->query("SHOW TABLES LIKE 'employee_edit_history'")->num_rows > 0) {
        $source_queries[] = "SELECT 2000000000 + eh.edit_id AS log_id, eh.edited_by AS user_id, 'EDIT_STEP' AS action_type,
            'EmployeeEditHistory' AS entity_type, 'Employee Management' AS module_name,
            eh.edit_id AS entity_id, eh.employee_id AS target_employee_id,
            CONCAT('Employee record edit', IF(eh.step_name IS NULL OR eh.step_name = '', '.', CONCAT(' in ', eh.step_name, '.'))) AS details,
            NULL AS previous_value, NULL AS new_value, NULL AS branch_id, NULL AS department_id,
            eh.ip_address, NULL AS user_agent, 'Successful' AS action_status, eh.created_at AS timestamp
            FROM employee_edit_history eh";
    }
    $audit_source = '(' . implode(' UNION ALL ', $source_queries) . ') al';
}
$base_from = "FROM $audit_source LEFT JOIN users u ON u.user_id = al.user_id LEFT JOIN employees e ON e.employee_id = al.target_employee_id LEFT JOIN branches b ON b.branch_id = al.branch_id LEFT JOIN departments d ON d.department_id = al.department_id";
$total = auditCount($conn, $base_from, $where, $types, $params);
$total_pages = max(1, (int) ceil($total / $page_size));
$page = min($page, $total_pages);
$offset = ($page - 1) * $page_size;
$order_direction = $filters['sort'] === 'ASC' ? 'ASC' : 'DESC';
$query = "SELECT al.*, u.full_name, u.role, e.employee_code, e.first_name, e.last_name, b.branch_name, d.department_name $base_from WHERE $where ORDER BY al.timestamp {$order_direction}, al.log_id {$order_direction} LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
$page_params = array_merge($params, [$page_size, $offset]);
$stmt->bind_param($types . 'ii', ...$page_params);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function auditCount($conn, $base_from, $where, $types, $params, $extra = ''): int {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total $base_from WHERE $where $extra");
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute(); $count = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0); $stmt->close();
    return $count;
}
$today_where = $where . ' AND DATE(al.timestamp) = CURDATE()';
$today = auditCount($conn, $base_from, $today_where, $types, $params);
$employee_changes = auditCount($conn, $base_from, $where . " AND al.module_name = 'Employee Management'", $types, $params);
$evaluations = auditCount($conn, $base_from, $where . " AND al.module_name = 'Performance & Evaluation'", $types, $params);
$movements = auditCount($conn, $base_from, $where . " AND al.module_name = 'Career Progression'", $types, $params);
$admin_changes = auditCount($conn, $base_from, $where . " AND al.module_name IN ('User & Access Management', 'System Administration')", $types, $params);
$failed_signins = auditCount($conn, $base_from, $where . " AND al.module_name = 'Authentication & Access' AND al.action_status = 'Failed'", $types, $params);
$user_access_changes = auditCount($conn, $base_from, $where . " AND al.module_name = 'User & Access Management'", $types, $params);
$system_changes = auditCount($conn, $base_from, $where . " AND (al.module_name = 'System Administration' OR al.entity_type IN ('Backup', 'AutoBackup'))", $types, $params);
$actor_stmt = $conn->prepare("SELECT DISTINCT u.user_id, u.full_name, u.role $base_from WHERE $scope_sql AND u.user_id IS NOT NULL ORDER BY u.full_name");
if ($scope_params) $actor_stmt->bind_param($scope_types, ...$scope_params);
$actor_stmt->execute();
$actors = $actor_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$actor_stmt->close();
$branches = $conn->query('SELECT branch_id, branch_name FROM branches ORDER BY branch_name')->fetch_all(MYSQLI_ASSOC);
$departments = $conn->query('SELECT department_id, department_name FROM departments ORDER BY department_name')->fetch_all(MYSQLI_ASSOC);
$record_type_rows = $conn->query("SELECT DISTINCT al.entity_type FROM $audit_source WHERE al.entity_type IS NOT NULL AND al.entity_type <> '' ORDER BY al.entity_type")->fetch_all(MYSQLI_ASSOC);
$record_types = array_column($record_type_rows, 'entity_type');

$admin_metrics = ['total' => 0, 'users' => 0, 'changes' => 0, 'logins' => 0];
$demo_audit_count = 0;
$action_summary = [];
$module_summary = [];
$top_users = [];
if ($is_admin) {
    $demo_audit_count = (int) ($conn->query("SELECT COUNT(*) AS total FROM audit_logs WHERE details LIKE '[DEMO AUDIT %'")->fetch_assoc()['total'] ?? 0);
}
if ($is_admin || in_array($viewer_role, ['HR Manager', 'HR Supervisor', 'HR Staff'], true)) {
    $metric_conditions = [];
    $metric_params = [];
    $metric_types = '';
    if (!$is_admin) { $metric_conditions[] = $scope_sql; $metric_params = $scope_params; $metric_types = $scope_types; }
    if ($filters['date_from'] !== '') { $metric_conditions[] = 'DATE(al.timestamp) >= ?'; $metric_params[] = $filters['date_from']; $metric_types .= 's'; }
    if ($filters['date_to'] !== '') { $metric_conditions[] = 'DATE(al.timestamp) <= ?'; $metric_params[] = $filters['date_to']; $metric_types .= 's'; }
    $metric_where = $metric_conditions ? ' WHERE ' . implode(' AND ', $metric_conditions) : '';
    $metric_from = "$audit_source LEFT JOIN users u ON u.user_id = al.user_id";
    $metric_stmt = $conn->prepare("SELECT COUNT(*) AS total, COUNT(DISTINCT al.user_id) AS users, SUM(al.action_type IN ('CREATE','UPDATE','DELETE','IMPORT','EDIT_STEP')) AS changes_count, SUM(al.action_type = 'LOGIN') AS logins_count, SUM(al.action_type = 'LOGIN' AND al.action_status = 'Failed') AS failed_signins FROM $metric_from{$metric_where}");
    if ($metric_params) $metric_stmt->bind_param($metric_types, ...$metric_params);
    $metric_stmt->execute();
    $metric_row = $metric_stmt->get_result()->fetch_assoc();
    $metric_stmt->close();
    $admin_metrics = [
        'total' => (int) ($metric_row['total'] ?? 0),
        'users' => (int) ($metric_row['users'] ?? 0),
        'changes' => (int) ($metric_row['changes_count'] ?? 0),
        'logins' => (int) ($metric_row['logins_count'] ?? 0),
        'failed_signins' => (int) ($metric_row['failed_signins'] ?? 0),
    ];

    $summary_scope = $metric_conditions ? ' AND ' . implode(' AND ', $metric_conditions) : '';
    $action_stmt = $conn->prepare("SELECT al.action_type, COUNT(*) AS total FROM $metric_from{$metric_where} GROUP BY al.action_type ORDER BY total DESC");
    if ($metric_params) $action_stmt->bind_param($metric_types, ...$metric_params);
    $action_stmt->execute();
    $action_summary = $action_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $action_stmt->close();
    $module_stmt = $conn->prepare("SELECT COALESCE(NULLIF(al.module_name,''), 'System Activity') AS module_name, COUNT(*) AS total FROM $metric_from{$metric_where} GROUP BY al.module_name ORDER BY total DESC LIMIT 7");
    if ($metric_params) $module_stmt->bind_param($metric_types, ...$metric_params);
    $module_stmt->execute();
    $module_summary = $module_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $module_stmt->close();
    $users_stmt = $conn->prepare("SELECT al.user_id, COALESCE(u.full_name, 'Demo actor') AS full_name, u.role, COUNT(*) AS total FROM $audit_source LEFT JOIN users u ON u.user_id = al.user_id WHERE (al.user_id IS NOT NULL OR al.details LIKE '[DEMO AUDIT %'){$summary_scope} GROUP BY al.user_id, u.full_name, u.role ORDER BY total DESC LIMIT 5");
    if ($metric_params) $users_stmt->bind_param($metric_types, ...$metric_params);
    $users_stmt->execute();
    $top_users = $users_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $users_stmt->close();
}

$lookup_name = static function (array $rows, string $id_key, string $name_key, int $id): string {
    foreach ($rows as $row) {
        if ((int) $row[$id_key] === $id) return (string) $row[$name_key];
    }
    return 'ID ' . $id;
};
$actor_names = [];
foreach ($actors as $actor) $actor_names[(int) $actor['user_id']] = $actor['full_name'] . ' - ' . $actor['role'];
$active_filter_labels = [];
foreach ([
    'search' => 'Search', 'module' => 'Module', 'action' => 'Action', 'status' => 'Outcome',
    'employee' => 'Target', 'record_type' => 'Record type', 'ip_address' => 'IP address', 'date_from' => 'From date', 'date_to' => 'To date',
] as $key => $label) {
    if ($filters[$key] !== '') $active_filter_labels[] = $label . ': ' . $filters[$key];
}
if ($filters['actor']) $active_filter_labels[] = 'Actor: ' . ($actor_names[$filters['actor']] ?? ('ID ' . $filters['actor']));
if ($filters['branch']) $active_filter_labels[] = 'Branch: ' . $lookup_name($branches, 'branch_id', 'branch_name', $filters['branch']);
if ($filters['department']) $active_filter_labels[] = 'Department: ' . $lookup_name($departments, 'department_id', 'department_name', $filters['department']);
if ($filters['entity_id']) $active_filter_labels[] = 'Entity ID: ' . $filters['entity_id'];
if ($is_admin && $filters['view'] !== 'all') $active_filter_labels[] = 'View: ' . ucfirst($filters['view']);

if (defined('AUDIT_TRAIL_EXPORT_ONLY')) {
    if (!$is_admin) {
        http_response_code(403);
        exit('Forbidden');
    }

    $export_stmt = $conn->prepare("SELECT al.*, u.full_name, u.role, e.employee_code, e.first_name, e.last_name, b.branch_name, d.department_name $base_from WHERE $where ORDER BY al.timestamp {$order_direction}, al.log_id {$order_direction}");
    if ($params) $export_stmt->bind_param($types, ...$params);
    $export_stmt->execute();
    $export_result = $export_stmt->get_result();
    $export_count = $export_result->num_rows;
    logAudit($conn, (int) $_SESSION['user_id'], 'EXPORT', 'Audit Trail', 0, "Exported {$export_count} audit event(s) to CSV.", ['module' => 'System Activity']);

    $csv_cell = static function ($value): string {
        $value = (string) ($value ?? '');
        return preg_match('/^[\t\r ]*[=+\-@]/', $value) ? "'" . $value : $value;
    };
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="audit-trail-' . date('Y-m-d-His') . '.csv"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Audit ID', 'Timestamp', 'Actor', 'Role', 'Action', 'Entity type', 'Entity ID', 'Module', 'Outcome', 'Details', 'Previous value', 'New value', 'Target employee', 'Branch', 'Department', 'IP address', 'Browser / device']);
    while ($log = $export_result->fetch_assoc()) {
        $target = trim(($log['employee_code'] ? $log['employee_code'] . ' - ' : '') . trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')));
        $row = [
            $log['log_id'], $log['timestamp'], $log['full_name'] ?? 'System process', $log['role'] ?? 'Unknown / not resolved',
            $log['action_type'], $log['entity_type'], $log['entity_id'], $log['module_name'], $log['action_status'],
            $log['details'], $log['previous_value'], $log['new_value'], $target, $log['branch_name'], $log['department_name'],
            $log['ip_address'], $log['user_agent'],
        ];
        fputcsv($output, array_map($csv_cell, $row));
    }
    fclose($output);
    $export_stmt->close();
    exit;
}
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
.audit-pill-failed { background:#fce8e6; border-color:#d93025; color:#a50e0e; }.audit-pill-cancelled { background:#fff4d6; border-color:#d6a500; color:#6b5200; }
.audit-view-tabs .btn.active { background:#234d08; color:#fff; border-color:#234d08; }.audit-page-link { min-width:38px; }.audit-stat-link { color:inherit; text-decoration:none; display:block; height:100%; }
@media(max-width:575px){.audit-hero{padding:22px}.audit-event summary{grid-template-columns:40px 1fr}.audit-event summary > .audit-pill{grid-column:2}.audit-details{padding-left:17px}}
</style>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin-audit-trail.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-audit-trail.css'); ?>">
<?php if ($is_admin || in_array($viewer_role, ['HR Manager', 'HR Supervisor', 'HR Staff'], true)): ?>
<div class="audit-admin">
    <div class="audit-admin-hero mb-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="audit-hero-copy"><div class="audit-admin-label"><i class="fas fa-home me-1"></i> <?php echo $is_admin ? 'System Administration' : e($viewer_role); ?> <i class="fas fa-chevron-right mx-1"></i> Audit Trail</div><div class="audit-hero-title"><i class="fas fa-shield-alt"></i><h2 class="fw-bold mb-0">Audit Trail</h2></div><p class="small"><?php echo $is_admin ? 'Track system activity, changes, and access for security and accountability.' : 'Review activity within your authorized audit scope.'; ?></p></div>
        <div class="d-flex align-items-center gap-2 audit-hero-actions">
            <details class="audit-date-picker"><summary><i class="far fa-calendar-alt"></i><span><?php echo $filters['date_from'] || $filters['date_to'] ? e(($filters['date_from'] ?: 'Start') . ' – ' . ($filters['date_to'] ?: 'Today')) : 'All dates'; ?></span><i class="fas fa-chevron-down"></i></summary><form method="get"><?php foreach ($filters as $key => $value): if (!in_array($key, ['date_from', 'date_to'], true)): ?><input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e($value); ?>"><?php endif; endforeach; ?><label>From<input type="date" name="date_from" value="<?php echo e($filters['date_from']); ?>"></label><label>To<input type="date" name="date_to" value="<?php echo e($filters['date_to']); ?>"></label><button type="submit">Apply date range</button></form></details>
            <?php if ($is_admin): ?><form method="post" action="export-audit-trail.php">
                <?php echo csrfField(); ?>
                <?php foreach ($filters as $key => $value): ?><input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e($value); ?>"><?php endforeach; ?>
                <button class="btn btn-sm btn-light"><i class="fas fa-download me-1"></i>Export</button>
            </form><?php endif; ?>
        </div>
    </div>
    <?php if ($demo_audit_count > 0): ?><div class="audit-demo-notice" role="note"><i class="fas fa-flask"></i><span><strong>Demo audit data is present.</strong> <?php echo number_format($demo_audit_count); ?> clearly labeled fictional event<?php echo $demo_audit_count === 1 ? '' : 's'; ?> were added for interface testing; they do not represent real actions.</span></div><?php endif; ?>
    <?php if ($filter_errors): ?><div class="alert alert-warning py-2"><?php echo e(implode(' ', $filter_errors)); ?></div><?php endif; ?>
    <div class="audit-admin-stats mb-3">
        <?php foreach ([['Total Events', $admin_metrics['total'], 'fa-file-alt', 'Events in selected date range'], ['Unique Users', $admin_metrics['users'], 'fa-user', 'Accounts with recorded activity'], ['Data Changes', $admin_metrics['changes'], 'fa-cog', 'Create, update, delete, and import'], ['Failed Sign-ins', $admin_metrics['failed_signins'], 'fa-shield-alt', 'Rejected login attempts']] as $stat): ?>
        <div class="audit-admin-stat"><div class="audit-admin-stat-icon"><i class="fas <?php echo $stat[2]; ?>"></i></div><div><div class="audit-admin-stat-label"><?php echo e($stat[0]); ?></div><div class="audit-admin-stat-value"><?php echo number_format($stat[1]); ?></div><div class="text-muted" style="font-size:.64rem"><?php echo e($stat[3]); ?></div></div></div>
        <?php endforeach; ?>
    </div>
    <details class="audit-admin-filter mb-3" <?php echo $filter_errors ? 'open' : ''; ?>>
        <summary class="audit-filter-toggle"><span><i class="fas fa-filter me-2"></i>Filters<?php if ($active_filter_labels): ?><span class="audit-filter-count"><?php echo count($active_filter_labels); ?> active</span><?php endif; ?></span><i class="fas fa-chevron-down audit-filter-chevron" aria-hidden="true"></i></summary>
        <div class="audit-filter-body"><div class="audit-filter-actions"><a href="audit-trail.php" class="small text-success"><i class="fas fa-undo me-1"></i>Reset filters</a></div>
        <form method="get" class="row g-2 align-items-end">
            <input type="hidden" name="view" value="<?php echo e($filters['view']); ?>">
            <div class="col-12 col-lg-6"><label class="form-label mb-1">Search</label><input class="form-control form-control-sm" name="search" value="<?php echo e($filters['search']); ?>" placeholder="Search user, employee ID, action, module, or details..."></div>
            <div class="col-12 col-lg-6"><label class="form-label mb-1">Date range</label><div class="d-flex gap-1"><input class="form-control form-control-sm" type="date" name="date_from" value="<?php echo e($filters['date_from']); ?>"><input class="form-control form-control-sm" type="date" name="date_to" value="<?php echo e($filters['date_to']); ?>"></div></div>
            <div class="col-6 col-lg-2"><label class="form-label mb-1">User</label><select class="form-select form-select-sm" name="actor"><option value="0">All users</option><?php foreach ($actors as $actor): ?><option value="<?php echo (int) $actor['user_id']; ?>" <?php echo $filters['actor'] === (int) $actor['user_id'] ? 'selected' : ''; ?>><?php echo e($actor['full_name']); ?></option><?php endforeach; ?></select></div>
            <div class="col-6 col-lg-2"><label class="form-label mb-1">Action type</label><select class="form-select form-select-sm" name="action"><option value="">All actions</option><?php foreach ($allowed_actions as $action): ?><option value="<?php echo e($action); ?>" <?php echo $filters['action'] === $action ? 'selected' : ''; ?>><?php echo e(ucwords(strtolower(str_replace('_', ' ', $action)))); ?></option><?php endforeach; ?></select></div>
            <div class="col-6 col-lg-2"><label class="form-label mb-1">Module</label><select class="form-select form-select-sm" name="module"><option value="">All modules</option><?php foreach ($allowed_modules as $module): ?><option value="<?php echo e($module); ?>" <?php echo $filters['module'] === $module ? 'selected' : ''; ?>><?php echo e($module); ?></option><?php endforeach; ?></select></div>
            <div class="col-6 col-lg-2"><label class="form-label mb-1">Record type</label><select class="form-select form-select-sm" name="record_type"><option value="">All record types</option><?php foreach ($record_types as $record_type): ?><option value="<?php echo e($record_type); ?>" <?php echo $filters['record_type'] === $record_type ? 'selected' : ''; ?>><?php echo e($record_type); ?></option><?php endforeach; ?></select></div>
            <div class="col-6 col-lg-2"><label class="form-label mb-1">Result</label><select class="form-select form-select-sm" name="status"><option value="">All results</option><?php foreach ($allowed_statuses as $status): ?><option value="<?php echo e($status); ?>" <?php echo $filters['status'] === $status ? 'selected' : ''; ?>><?php echo e($status); ?></option><?php endforeach; ?></select></div>
            <div class="col-6 col-lg-2"><label class="form-label mb-1">IP address</label><input class="form-control form-control-sm" name="ip_address" value="<?php echo e($filters['ip_address']); ?>" placeholder="Enter IP address"></div>
            <?php if (!$is_admin): ?>
            <div class="col-6 col-lg-2"><label class="form-label mb-1">Target employee</label><input class="form-control form-control-sm" name="employee" value="<?php echo e($filters['employee']); ?>" placeholder="Name or employee ID"></div>
            <div class="col-6 col-lg-2"><label class="form-label mb-1">Branch</label><select class="form-select form-select-sm" name="branch"><option value="0">All branches</option><?php foreach ($branches as $branch): ?><option value="<?php echo (int) $branch['branch_id']; ?>" <?php echo $filters['branch'] === (int) $branch['branch_id'] ? 'selected' : ''; ?>><?php echo e($branch['branch_name']); ?></option><?php endforeach; ?></select></div>
            <div class="col-6 col-lg-2"><label class="form-label mb-1">Department</label><select class="form-select form-select-sm" name="department"><option value="0">All departments</option><?php foreach ($departments as $department): ?><option value="<?php echo (int) $department['department_id']; ?>" <?php echo $filters['department'] === (int) $department['department_id'] ? 'selected' : ''; ?>><?php echo e($department['department_name']); ?></option><?php endforeach; ?></select></div>
            <?php endif; ?>
            <div class="col-6 col-lg-2"><label class="form-label mb-1">Sort by</label><select class="form-select form-select-sm" name="sort"><option value="DESC" <?php echo $filters['sort'] === 'DESC' ? 'selected' : ''; ?>>Newest first</option><option value="ASC" <?php echo $filters['sort'] === 'ASC' ? 'selected' : ''; ?>>Oldest first</option></select></div>
            <div class="col-12 col-lg-2"><button class="btn btn-sm btn-success w-100"><i class="fas fa-filter me-1"></i>Apply filters</button></div>
        </form>
        <?php if ($active_filter_labels): ?><div class="small text-muted mt-2">Active: <?php echo e(implode(' · ', $active_filter_labels)); ?></div><?php endif; ?>
        </div>
    </details>
    <div class="audit-admin-grid">
        <div>
            <section class="audit-admin-card">
                <div class="audit-admin-card-head d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div><h5 class="mb-0 fs-6 fw-bold"><i class="fas fa-chart-bar me-2 text-success"></i>Activity Logs (<?php echo number_format($total); ?>)</h5><small class="text-muted">Showing <?php echo $total === 0 ? '0' : number_format($offset + 1) . '–' . number_format($offset + count($logs)); ?> of <?php echo number_format($total); ?> records matching your filters</small></div>
                    <form method="get" class="d-flex align-items-center gap-2 small">
                        <?php foreach ($filters as $key => $value): if (!in_array($key, ['page_size'], true)): ?><input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e($value); ?>"><?php endif; endforeach; ?>
                        <label for="auditPageSize" class="mb-0">Show</label>
                        <select id="auditPageSize" name="page_size" class="form-select form-select-sm" style="width:72px" onchange="this.form.submit()"><?php foreach ([10, 25, 50] as $size): ?><option value="<?php echo $size; ?>" <?php echo $page_size === $size ? 'selected' : ''; ?>><?php echo $size; ?></option><?php endforeach; ?></select>
                        <span>entries</span>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table audit-admin-table">
                        <thead><tr><th>Date &amp; time</th><th>User</th><th>Action</th><th>Module</th><th>Record</th><th>Result</th><th>IP address</th><th aria-label="Actions"></th></tr></thead>
                        <tbody>
                        <?php foreach ($logs as $index => $log):
                            $is_demo = str_starts_with((string) ($log['details'] ?? ''), '[DEMO AUDIT ');
                            $actor_name = $is_demo ? 'Demo actor' : ($log['full_name'] ?? ($log['action_type'] === 'LOGIN' ? 'Unrecognized account' : 'System process'));
                            $target = trim(($log['employee_code'] ? $log['employee_code'] . ' - ' : '') . trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')));
                            $is_package_history = $log['entity_type'] === 'EvaluationPackageHistory';
                            $is_employee_history = $log['entity_type'] === 'EmployeeEditHistory';
                            $record_label = $is_package_history ? 'Evaluation package #' . $log['entity_id']
                                : ($is_employee_history ? ($target ?: 'Employee record') : ($target ?: (($log['entity_type'] ?? 'Record') . (!empty($log['entity_id']) ? ' #' . $log['entity_id'] : ''))));
                            $event_title = $is_package_history ? 'Package ' . ucwords(strtolower(str_replace('_', ' ', $log['action_type'])))
                                : ($is_employee_history ? 'Employee record edited' : ($log['action_type'] === 'LOGIN' ? 'Login activity' : ucwords(strtolower(str_replace('_', ' ', $log['action_type']))) . ' ' . ($log['entity_type'] ?? 'record')));
                            $audit_reference = $is_package_history ? 'PKG-' . ((int) $log['log_id'] - 1000000000)
                                : ($is_employee_history ? 'EDIT-' . ((int) $log['log_id'] - 2000000000)
                                    : 'AUD-' . date('Y', strtotime($log['timestamp'])) . '-' . str_pad((string) $log['log_id'], 6, '0', STR_PAD_LEFT));
                            $event_payload = [
                                'title' => $event_title,
                                'details' => $log['details'] ?: 'No additional description was recorded.',
                                'timestamp' => date('M j, Y g:i A', strtotime($log['timestamp'])),
                                'actor' => $actor_name . (!empty($log['role']) ? ' (' . $log['role'] . ')' : ''), 'role' => $is_demo ? 'Fictional' : ($log['role'] ?? 'Unknown / not resolved'),
                                'action' => ucwords(strtolower(str_replace('_', ' ', $log['action_type']))),
                                'module' => $log['module_name'] ?? 'System Activity', 'record' => $record_label,
                                'result' => $log['action_status'] ?? 'Successful', 'ip' => $log['ip_address'] ?? 'Not recorded',
                                'device' => $log['user_agent'] ?? 'Not recorded', 'branch' => $log['branch_name'] ?? 'Not applicable',
                                'department' => $log['department_name'] ?? 'Not applicable',
                                'id' => $audit_reference,
                                'previous' => $log['previous_value'] ?? '', 'new' => $log['new_value'] ?? '',
                            ];
                            $initial_status = strtolower($event_payload['result']);
                            $initial_avatar = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $actor_name) ?: 'S', 0, 2));
                        ?>
                            <tr class="audit-admin-row" tabindex="0" role="button" aria-expanded="false" aria-controls="audit-details-<?php echo $index; ?>" aria-label="View details for <?php echo e($event_payload['title']); ?>">
                                <td><span><?php echo date('M j, Y', strtotime($log['timestamp'])); ?></span><br><small class="text-muted"><?php echo date('g:i A', strtotime($log['timestamp'])); ?></small></td>
                                <td><div class="audit-user-cell"><span class="audit-avatar"><?php echo e($initial_avatar); ?></span><span class="text-truncate"><?php echo e($actor_name); ?></span></div></td>
                                <td><i class="fas <?php echo $log['action_type'] === 'LOGIN' ? 'fa-right-to-bracket' : 'fa-pen-to-square'; ?> me-1 text-muted"></i><?php echo e(ucwords(strtolower(str_replace('_', ' ', $log['action_type'])))); ?><?php if ($is_demo): ?><span class="audit-demo-badge">Demo</span><?php endif; ?></td>
                                <td><?php echo e($log['module_name'] ?? 'System Activity'); ?></td>
                                <td><?php echo e($record_label); ?></td>
                                <td><span class="audit-outcome <?php echo e($initial_status); ?>"><?php echo e($event_payload['result']); ?></span></td>
                                <td><?php echo e($log['ip_address'] ?? '—'); ?></td><td><span class="audit-row-action" aria-hidden="true"><i class="fas fa-chevron-down"></i></span></td>
                            </tr>
                            <tr class="audit-inline-detail-row" id="audit-details-<?php echo $index; ?>" hidden><td colspan="8">
                                <div class="audit-inline-detail">
                                    <div class="audit-inline-detail-head"><div><span class="audit-inline-kicker">Log Details <span><?php echo e($audit_reference); ?></span></span><h6><?php echo e($event_title); ?></h6><p><?php echo e($event_payload['details']); ?></p></div><span class="audit-outcome <?php echo e($initial_status); ?>"><?php echo e($event_payload['result']); ?></span></div>
                                    <div class="audit-inline-detail-grid">
                                        <?php foreach ([['Date & time', $event_payload['timestamp']], ['User', $event_payload['actor']], ['Action', $event_payload['action']], ['Module', $event_payload['module']], ['Record', $event_payload['record']], ['IP address', $event_payload['ip']], ['Device', $event_payload['device']], ['Branch', $event_payload['branch']], ['Department', $event_payload['department']]] as $detail): ?><div class="audit-inline-fact"><strong><?php echo e($detail[0]); ?></strong><span><?php echo e($detail[1] ?: 'Not recorded'); ?></span></div><?php endforeach; ?>
                                    </div>
                                    <?php if ($event_payload['previous'] !== '' || $event_payload['new'] !== ''): ?><div class="audit-inline-changes"><div><strong>Previous value</strong><p><?php echo e($event_payload['previous'] ?: 'Not recorded'); ?></p></div><div><strong>New value</strong><p><?php echo e($event_payload['new'] ?: 'Not recorded'); ?></p></div></div><?php endif; ?>
                                </div>
                            </td></tr>
                        <?php endforeach; ?>
                        <?php if (!$logs): ?><tr><td colspan="8" class="text-center text-muted py-5">No audit events match these filters.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pages > 1): ?><div class="audit-admin-footer d-flex flex-wrap justify-content-between align-items-center gap-2"><span class="small text-muted">Showing <?php echo number_format($offset + 1); ?> to <?php echo number_format($offset + count($logs)); ?> of <?php echo number_format($total); ?> entries</span><nav class="audit-pagination d-flex gap-1" aria-label="Audit event pages"><?php $page_start = max(1, $page - 2); $page_end = min($total_pages, $page + 2); ?><a class="btn btn-sm btn-outline-secondary <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="audit-trail.php?<?php echo e(http_build_query(array_merge($filters, ['page' => max(1, $page - 1)]))); ?>" aria-label="Previous page">&laquo;</a><?php for ($page_number = $page_start; $page_number <= $page_end; $page_number++): ?><a class="btn btn-sm <?php echo $page_number === $page ? 'btn-success' : 'btn-outline-secondary'; ?>" href="audit-trail.php?<?php echo e(http_build_query(array_merge($filters, ['page' => $page_number]))); ?>"><?php echo $page_number; ?></a><?php endfor; ?><a class="btn btn-sm btn-outline-secondary <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" href="audit-trail.php?<?php echo e(http_build_query(array_merge($filters, ['page' => min($total_pages, $page + 1)]))); ?>" aria-label="Next page">&raquo;</a></nav></div><?php endif; ?>
            </section>
            <?php
                $action_buckets = ['Login / Logout' => 0, 'Create' => 0, 'Update' => 0, 'View' => 0, 'Delete' => 0, 'Others' => 0];
                foreach ($action_summary as $summary) {
                    $action_name = strtoupper((string) $summary['action_type']);
                    $bucket = in_array($action_name, ['LOGIN', 'LOGOUT'], true) ? 'Login / Logout'
                        : (in_array($action_name, ['CREATE', 'UPDATE', 'VIEW', 'DELETE'], true) ? ucfirst(strtolower($action_name)) : 'Others');
                    $action_buckets[$bucket] += (int) $summary['total'];
                }
                $action_colors = ['Login / Logout' => '#648e52', 'Create' => '#2c5926', 'Update' => '#ebc774', 'View' => '#a88e5c', 'Delete' => '#b54148', 'Others' => '#c9cec7'];
                $action_total = array_sum($action_buckets);
                $pie_stops = [];
                $pie_position = 0;
                foreach ($action_buckets as $bucket => $bucket_count) {
                    if ($bucket_count === 0 || $action_total === 0) continue;
                    $next_position = $pie_position + $bucket_count * 100 / $action_total;
                    $pie_stops[] = $action_colors[$bucket] . ' ' . round($pie_position, 2) . '% ' . round($next_position, 2) . '%';
                    $pie_position = $next_position;
                }
                $pie_background = $pie_stops ? 'conic-gradient(' . implode(', ', $pie_stops) . ')' : '#e8ede6';
                $max_module_count = $module_summary ? max(array_map(static fn($row) => (int) $row['total'], $module_summary)) : 0;
                $max_user_count = $top_users ? max(array_map(static fn($row) => (int) $row['total'], $top_users)) : 0;
            ?>
            <div class="audit-summary-cards">
                <section class="audit-admin-card audit-summary-card">
                    <h5><i class="fas fa-chart-pie"></i> Events by Action Type</h5>
                    <div class="audit-action-chart"><div class="audit-donut" style="background:<?php echo e($pie_background); ?>"><div><strong><?php echo number_format($action_total); ?></strong><span>Total Events</span></div></div><div class="audit-action-legend"><?php foreach ($action_buckets as $bucket => $bucket_count): ?><div><i style="background:<?php echo e($action_colors[$bucket]); ?>"></i><span><?php echo e($bucket); ?></span><strong><?php echo number_format($bucket_count); ?> <small>(<?php echo $action_total ? round($bucket_count * 100 / $action_total) : 0; ?>%)</small></strong></div><?php endforeach; ?></div></div>
                </section>
                <section class="audit-admin-card audit-summary-card">
                    <h5><i class="fas fa-chart-bar"></i> Events by Module</h5>
                    <div class="audit-module-list"><?php foreach ($module_summary as $summary): $amount = (int) $summary['total']; ?><div class="audit-module-item"><span title="<?php echo e($summary['module_name']); ?>"><?php echo e($summary['module_name']); ?></span><div class="audit-module-track"><i style="width:<?php echo $max_module_count ? round($amount * 100 / $max_module_count) : 0; ?>%"></i></div><strong><?php echo number_format($amount); ?></strong></div><?php endforeach; ?><?php if (!$module_summary): ?><p class="text-muted small">No recorded modules yet.</p><?php endif; ?></div>
                </section>
                <section class="audit-admin-card audit-summary-card">
                    <h5><i class="fas fa-users"></i> Top 5 Active Users</h5>
                    <div class="audit-ranked-users"><?php foreach ($top_users as $rank => $user): $amount = (int) $user['total']; $initials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) $user['full_name']) ?: 'U', 0, 2)); ?><div class="audit-ranked-user"><span class="audit-user-rank"><?php echo $rank + 1; ?></span><span class="audit-avatar"><?php echo e($initials); ?></span><div><span><?php echo e($user['full_name']); ?></span><div class="audit-module-track"><i style="width:<?php echo $max_user_count ? round($amount * 100 / $max_user_count) : 0; ?>%"></i></div></div><strong><?php echo number_format($amount); ?></strong></div><?php endforeach; ?><?php if (!$top_users): ?><p class="text-muted small">No user activity in this period.</p><?php endif; ?></div>
                </section>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const rows = document.querySelectorAll('.audit-admin-row');
    function selectAuditRow(row) {
        const detail = document.getElementById(row.getAttribute('aria-controls'));
        if (!detail) return;
        const opening = detail.hidden;
        rows.forEach(function (item) {
            const itemDetail = document.getElementById(item.getAttribute('aria-controls'));
            item.classList.remove('is-selected');
            item.setAttribute('aria-expanded', 'false');
            if (itemDetail) itemDetail.hidden = true;
        });
        if (opening) {
            detail.hidden = false;
            row.classList.add('is-selected');
            row.setAttribute('aria-expanded', 'true');
        }
    }
    rows.forEach(function (row) {
        row.addEventListener('click', function () { selectAuditRow(row); });
        row.addEventListener('keydown', function (event) { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); selectAuditRow(row); } });
    });
});
</script>
<?php else: ?>
<div class="audit-hero fadeup mb-4">
    <div class="position-relative d-flex flex-wrap justify-content-between align-items-center gap-3" style="z-index:1"><div><div class="small text-uppercase" style="letter-spacing:.12em;color:#f4dc9d"><?php echo e($scope_label); ?></div><h2 class="fw-bold mb-1"><?php echo $is_admin ? 'Audit Trail' : 'System Activity Monitor'; ?></h2><p class="mb-0 text-white-50"><?php echo $is_admin ? 'Search and investigate changes, access events, and sign-in activity across the system.' : 'Review system changes and successful or failed sign-ins across your authorized activity.'; ?></p></div><?php if ($is_admin): ?><form method="post" action="export-audit-trail.php"><?php echo csrfField(); ?><?php foreach ($filters as $key => $value): ?><input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e($value); ?>"><?php endforeach; ?><button class="btn btn-light"><i class="fas fa-download me-1"></i>Export filtered</button></form><?php endif; ?></div>
</div>
<?php if ($is_admin): ?>
<div class="audit-view-tabs d-flex flex-wrap gap-2 mb-3 fadeup-1">
    <?php foreach (['all' => 'All events', 'security' => 'Security & access', 'users' => 'User changes', 'system' => 'System changes'] as $view_key => $view_label): ?>
        <a class="btn btn-sm <?php echo $filters['view'] === $view_key ? 'active' : 'btn-outline-success'; ?>" href="audit-trail.php?<?php echo e(http_build_query(array_merge($filters, ['view' => $view_key, 'page' => 1]))); ?>"><?php echo e($view_label); ?></a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<div class="row g-3 mb-4 fadeup-1">
<?php $stats = $is_admin
    ? [['Total events', $total, 'fa-wave-square', 'all'], ['Failed sign-ins', $failed_signins, 'fa-triangle-exclamation', 'security'], ['User/access changes', $user_access_changes, 'fa-users-gear', 'users'], ['System changes', $system_changes, 'fa-gear', 'system']]
    : [['Total Activities', $total, 'fa-wave-square', ''], ["Today's Activities", $today, 'fa-calendar-day', ''], ['Employee Changes', $employee_changes, 'fa-id-card', ''], ['Performance Activities', $evaluations, 'fa-clipboard-check', ''], ['Career Movements', $movements, 'fa-route', ''], ['Administrative Changes', $admin_changes, 'fa-shield-alt', '']];
foreach ($stats as $stat): ?>
<div class="<?php echo $is_admin ? 'col-6 col-lg-3' : 'col-6 col-lg-2'; ?>"><?php if ($is_admin && $stat[3] !== 'all'): ?><a class="audit-stat-link" href="audit-trail.php?<?php echo e(http_build_query(array_merge($filters, ['view' => $stat[3], 'page' => 1]))); ?>"><?php endif; ?><div class="audit-stat"><i class="fas <?php echo $stat[2]; ?> mb-2" style="color:#d3973e"></i><div class="value"><?php echo number_format($stat[1]); ?></div><div class="label"><?php echo e($stat[0]); ?></div></div><?php if ($is_admin && $stat[3] !== 'all'): ?></a><?php endif; ?></div>
<?php endforeach; ?></div>
<div class="audit-filter mb-4 fadeup-2"><form method="get" class="row g-2 align-items-end"><input type="hidden" name="view" value="<?php echo e($filters['view']); ?>"><div class="col-md-4"><label class="form-label small mb-1">Search investigation</label><input class="form-control form-control-sm" name="search" value="<?php echo e($filters['search']); ?>" placeholder="Actor, target, event ID, action, or description"></div><div class="col-6 col-md-2"><label class="form-label small mb-1">Module</label><select class="form-select form-select-sm" name="module"><option value="">All activity</option><?php foreach($allowed_modules as $module): ?><option value="<?php echo e($module); ?>" <?php echo $filters['module']===$module?'selected':''; ?>><?php echo e($module); ?></option><?php endforeach; ?></select></div><div class="col-6 col-md-2"><label class="form-label small mb-1">Action</label><select class="form-select form-select-sm" name="action"><option value="">All actions</option><?php foreach($allowed_actions as $action): ?><option value="<?php echo e($action); ?>" <?php echo $filters['action']===$action?'selected':''; ?>><?php echo e(ucwords(strtolower(str_replace('_', ' ', $action)))); ?></option><?php endforeach; ?></select></div><div class="col-6 col-md-2"><label class="form-label small mb-1">Outcome</label><select class="form-select form-select-sm" name="status"><option value="">Any outcome</option><?php foreach($allowed_statuses as $status): ?><option value="<?php echo e($status); ?>" <?php echo $filters['status']===$status?'selected':''; ?>><?php echo e($status); ?></option><?php endforeach; ?></select></div><div class="col-6 col-md-2"><label class="form-label small mb-1">From</label><input class="form-control form-control-sm" type="date" name="date_from" value="<?php echo e($filters['date_from']); ?>"></div><div class="col-6 col-md-2"><label class="form-label small mb-1">To</label><input class="form-control form-control-sm" type="date" name="date_to" value="<?php echo e($filters['date_to']); ?>"></div><div class="col-md-3"><label class="form-label small mb-1">Actor</label><select class="form-select form-select-sm" name="actor"><option value="0">All authorized actors</option><?php foreach($actors as $actor): ?><option value="<?php echo (int)$actor['user_id']; ?>" <?php echo $filters['actor']==$actor['user_id']?'selected':''; ?>><?php echo e($actor['full_name'].' - '.$actor['role']); ?></option><?php endforeach; ?></select></div><div class="col-md-3"><label class="form-label small mb-1">Target employee</label><input class="form-control form-control-sm" name="employee" value="<?php echo e($filters['employee']); ?>" placeholder="Name or employee ID"></div><div class="col-md-2"><label class="form-label small mb-1">Branch</label><select class="form-select form-select-sm" name="branch"><option value="0">All branches</option><?php foreach($branches as $branch): ?><option value="<?php echo (int)$branch['branch_id']; ?>" <?php echo $filters['branch']==$branch['branch_id']?'selected':''; ?>><?php echo e($branch['branch_name']); ?></option><?php endforeach; ?></select></div><div class="col-md-2"><label class="form-label small mb-1">Department</label><select class="form-select form-select-sm" name="department"><option value="0">All departments</option><?php foreach($departments as $department): ?><option value="<?php echo (int)$department['department_id']; ?>" <?php echo $filters['department']==$department['department_id']?'selected':''; ?>><?php echo e($department['department_name']); ?></option><?php endforeach; ?></select></div><?php if ($is_admin): ?><div class="col-md-3"><label class="form-label small mb-1">IP address</label><input class="form-control form-control-sm" name="ip_address" value="<?php echo e($filters['ip_address']); ?>" placeholder="Exact or partial IP"></div><div class="col-md-2"><label class="form-label small mb-1">Entity ID</label><input class="form-control form-control-sm" type="number" min="1" name="entity_id" value="<?php echo $filters['entity_id'] ?: ''; ?>" placeholder="Record ID"></div><?php endif; ?><div class="col-md-2"><button class="btn btn-sm btn-primary w-100"><i class="fas fa-filter me-1"></i>Apply</button></div><div class="col-md-2"><a class="btn btn-sm btn-outline-secondary w-100" href="audit-trail.php">Clear all</a></div></form></div>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h5 class="mb-0"><?php echo $is_admin ? 'Audit events' : 'Activity timeline'; ?></h5><small class="text-muted">Showing <?php echo $total === 0 ? '0' : number_format($offset + 1) . '–' . number_format($offset + count($logs)); ?> of <?php echo number_format($total); ?> matching records</small></div><div class="d-flex align-items-center gap-3"><span class="small text-muted"><i class="fas fa-lock me-1"></i>Read-only historical record</span><?php if ($active_filter_labels): ?><a class="btn btn-sm btn-outline-secondary" href="audit-trail.php">Clear all</a><?php endif; ?></div></div>
<?php if ($filter_errors): ?><div class="alert alert-warning"><?php echo e(implode(' ', $filter_errors)); ?></div><?php endif; ?>
<?php if ($active_filter_labels): ?><div class="small text-muted mb-3">Active filters: <?php echo e(implode(' · ', $active_filter_labels)); ?></div><?php endif; ?>
<?php if (!$logs): ?><div class="text-center text-muted bg-light rounded-4 py-5"><i class="fas fa-inbox fa-2x mb-3"></i><br><?php if ($active_filter_labels): ?>No activity matches the active filters. Clear filters to see all recorded activity.<?php else: ?>No activity has been recorded yet.<?php endif; ?></div><?php endif; ?>
<?php foreach ($logs as $log): $target=trim(($log['employee_code'] ? $log['employee_code'].' - ' : '').trim(($log['first_name']??'').' '.($log['last_name']??''))); $is_login = $log['action_type'] === 'LOGIN'; $icon=$is_login?'fa-right-to-bracket':(str_contains(strtolower($log['module_name'] ?? ''), 'performance')?'fa-clipboard-check':(str_contains(strtolower($log['module_name'] ?? ''), 'career')?'fa-route':'fa-pen-to-square')); $event_title=$is_login?'Login attempt':ucwords(strtolower(str_replace('_',' ',$log['action_type'])).' '.($log['entity_type'] ?? 'Record')); ?>
<details class="audit-event"><summary><div class="audit-icon"><i class="fas <?php echo $icon; ?>"></i></div><div><div class="fw-bold"><?php echo e($event_title); ?></div><div class="audit-meta"><?php echo e($log['full_name'] ?? ($is_login ? 'Unrecognized account' : 'System process')); ?> <?php echo !empty($log['role']) ? ' - '.e($log['role']) : ''; ?> | <?php echo e($log['module_name'] ?? 'System Activity'); ?> | <?php echo formatDateTime($log['timestamp']); ?></div><?php if($target): ?><div class="small mt-1"><i class="fas fa-user me-1 text-muted"></i><?php echo e($target); ?></div><?php endif; ?></div><span class="audit-pill audit-pill-<?php echo strtolower(e($log['action_status'] ?? 'Successful')); ?>"><?php echo e($log['action_status'] ?? 'Successful'); ?></span></summary><div class="audit-details"><div class="mb-3"><strong>What happened?</strong><div class="text-muted small mt-1"><?php echo e($log['details'] ?: 'System activity recorded.'); ?></div></div><?php if (!$is_login): ?><div class="row g-3 mb-3"><div class="col-md-6"><div class="audit-value"><strong>Previous value</strong><br><?php echo e($log['previous_value'] ?: 'Not recorded'); ?></div></div><div class="col-md-6"><div class="audit-value"><strong>New value</strong><br><?php echo e($log['new_value'] ?: 'Not recorded'); ?></div></div></div><?php endif; ?><div class="row g-3"><div class="col-md-6 small"><strong>Audit ID:</strong> AUD-<?php echo date('Y',strtotime($log['timestamp'])); ?>-<?php echo str_pad((string)$log['log_id'],6,'0',STR_PAD_LEFT); ?><br><strong>Target:</strong> <?php echo e($target ?: ($is_login ? ($log['full_name'] ?? 'Account identified in event details') : ($log['entity_type'].' #'.$log['entity_id']))); ?><br><strong>Branch / Department:</strong> <?php echo e(trim(($log['branch_name']??'').' '.($log['department_name']??'')) ?: 'Not applicable'); ?></div><div class="col-md-6 small"><strong>Actor:</strong> <?php echo e($log['full_name'] ?? ($is_login ? 'Unrecognized account' : 'System process')); ?><br><strong>Role:</strong> <?php echo e($log['role'] ?? 'Unknown / not resolved'); ?><br><strong>IP address seen by server:</strong> <?php echo e($log['ip_address'] ?? 'Not recorded'); ?><br><strong>Browser / device:</strong> <?php echo e($log['user_agent'] ?? 'Not recorded'); ?></div></div></div></details>
<?php endforeach; ?>
<?php if ($total_pages > 1): ?><nav class="d-flex flex-wrap justify-content-center gap-1 my-4" aria-label="Audit event pages"><?php $page_start = max(1, $page - 2); $page_end = min($total_pages, $page + 2); ?><a class="btn btn-sm btn-outline-secondary audit-page-link <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="audit-trail.php?<?php echo e(http_build_query(array_merge($filters, ['page' => max(1, $page - 1)]))); ?>" aria-label="Previous page">&laquo;</a><?php for ($page_number = $page_start; $page_number <= $page_end; $page_number++): ?><a class="btn btn-sm <?php echo $page_number === $page ? 'btn-success' : 'btn-outline-secondary'; ?> audit-page-link" href="audit-trail.php?<?php echo e(http_build_query(array_merge($filters, ['page' => $page_number]))); ?>"><?php echo $page_number; ?></a><?php endfor; ?><a class="btn btn-sm btn-outline-secondary audit-page-link <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" href="audit-trail.php?<?php echo e(http_build_query(array_merge($filters, ['page' => min($total_pages, $page + 1)]))); ?>" aria-label="Next page">&raquo;</a><span class="align-self-center small text-muted ms-2">Page <?php echo number_format($page); ?> of <?php echo number_format($total_pages); ?></span></nav><?php endif; ?>
<?php endif; ?>
