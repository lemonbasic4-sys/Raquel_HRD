<?php
$page_title = 'Export Employee Directory';
require_once '../includes/session-check.php';
checkRole(['HR Supervisor']);
require_once '../includes/functions.php';

function supervisorRosterValue($value, string $fallback = 'N/A'): string
{
    $value = trim((string) ($value ?? ''));
    return $value !== '' ? $value : $fallback;
}

function supervisorRosterDate($value): string
{
    $value = trim((string) ($value ?? ''));
    return $value !== '' ? formatDate($value) : 'N/A';
}

function supervisorRosterFilterValue(string $value, int $limit = 80): string
{
    $value = trim($value);
    return mb_strlen($value, 'UTF-8') > $limit ? mb_substr($value, 0, $limit, 'UTF-8') : $value;
}

$search = supervisorRosterFilterValue($_GET['search'] ?? '');
$department_id = isset($_GET['department']) && $_GET['department'] !== '' ? max(0, (int) $_GET['department']) : 0;
$position = supervisorRosterFilterValue($_GET['position'] ?? '');
$status = supervisorRosterFilterValue($_GET['status'] ?? '', 50);
$type = supervisorRosterFilterValue($_GET['type'] ?? '', 50);

$where = "
    WHERE e.employee_id NOT IN (
          SELECT employee_id
          FROM users
          WHERE role = 'Admin'
            AND employee_id IS NOT NULL
      )
";
$types = '';
$params = [];
$filter_summary = [];

if ($search !== '') {
    $where .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_code LIKE ? OR e.employee_id LIKE ?)";
    $search_term = '%' . $search . '%';
    array_push($params, $search_term, $search_term, $search_term, $search_term);
    $types .= 'ssss';
    $filter_summary[] = 'Search: ' . $search;
}

if ($department_id > 0) {
    $where .= " AND e.department_id = ?";
    $params[] = $department_id;
    $types .= 'i';
}

if ($position !== '') {
    $where .= " AND e.job_title LIKE ?";
    $params[] = '%' . $position . '%';
    $types .= 's';
    $filter_summary[] = 'Position: ' . $position;
}

if ($status !== '') {
    $where .= " AND e.employment_status = ?";
    $params[] = $status;
    $types .= 's';
    $filter_summary[] = 'Status: ' . $status;
}

if ($type !== '') {
    $where .= " AND e.employment_type = ?";
    $params[] = $type;
    $types .= 's';
    $filter_summary[] = 'Type: ' . $type;
}

if ($department_id > 0) {
    $department_stmt = $conn->prepare("SELECT department_name FROM departments WHERE department_id = ? LIMIT 1");
    $department_stmt->bind_param('i', $department_id);
    $department_stmt->execute();
    $department_row = $department_stmt->get_result()->fetch_assoc();
    $department_stmt->close();
    $filter_summary[] = 'Department: ' . supervisorRosterValue($department_row['department_name'] ?? '', 'Selected Department');
}

$stmt = $conn->prepare("
    SELECT e.employee_id, e.employee_code, e.last_name, e.first_name, e.middle_name,
           e.job_title, e.hire_date, e.employment_status, e.employment_type,
           b.branch_name, d.department_name
    FROM employees e
    LEFT JOIN branches b ON e.branch_id = b.branch_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    $where
    ORDER BY e.last_name, e.first_name, e.employee_id
");
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$employees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$filename_parts = ['supervisor_employee_directory'];
if ($search !== '') {
    $filename_parts[] = 'filtered';
}
$filename_parts[] = date('Y-m-d_His');
$filename = implode('_', $filename_parts) . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, ['Supervisor Employee Directory']);
fputcsv($output, ['Scope', 'All non-admin employees']);
fputcsv($output, ['Generated', date('M d, Y h:i A')]);
fputcsv($output, ['Filters', $filter_summary ? implode(' | ', $filter_summary) : 'All employees']);
fputcsv($output, []);
fputcsv($output, [
    '#',
    'Company ID',
    'Employee Name',
    'Job Title',
    'Department',
    'Branch',
    'Employment Status',
    'Employment Type',
    'Hire Date',
]);

foreach ($employees as $index => $employee) {
    $full_name = supervisorRosterValue($employee['last_name'] ?? '') . ', ' . supervisorRosterValue($employee['first_name'] ?? '');
    if (!empty($employee['middle_name'])) {
        $full_name .= ' ' . trim((string) $employee['middle_name']);
    }

    fputcsv($output, [
        $index + 1,
        getEmployeeDisplayId($employee),
        $full_name,
        supervisorRosterValue($employee['job_title'] ?? ''),
        supervisorRosterValue($employee['department_name'] ?? ''),
        supervisorRosterValue($employee['branch_name'] ?? ''),
        supervisorRosterValue($employee['employment_status'] ?? ''),
        supervisorRosterValue($employee['employment_type'] ?? ''),
        supervisorRosterDate($employee['hire_date'] ?? ''),
    ]);
}

fputcsv($output, []);
fputcsv($output, ['Total Records', count($employees)]);
fclose($output);
exit;
