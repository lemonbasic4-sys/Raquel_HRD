<?php
require_once '../../includes/session-check.php';
checkRole(['HR Manager', 'HR Supervisor', 'Employee', 'President and CEO']);
require_once '../../includes/succession-endorsements.php';

header('Content-Type: application/json; charset=utf-8');
$employeeId = (int)($_GET['employee_id'] ?? 0);
if (in_array(($_SESSION['role'] ?? ''), ['Employee', 'President and CEO'], true)) {
    if (!successionIsCeoUser($conn, (int)$_SESSION['user_id']) || $employeeId < 1) { http_response_code(403); echo json_encode(['success'=>false]); exit; }
    $access = $conn->prepare("SELECT 1 FROM succession_endorsements WHERE employee_id=? AND status IN ('Pending CEO Approval','Approved','Rejected') LIMIT 1");
    $access->bind_param('i', $employeeId);
    $access->execute();
    $canView = $access->get_result()->num_rows > 0;
    $access->close();
    if (!$canView) { http_response_code(403); echo json_encode(['success'=>false]); exit; }
}
if ($employeeId < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid employee.']);
    exit;
}

$employeeStmt = $conn->prepare("SELECT employee_id, job_title, hire_date FROM employees WHERE employee_id=? AND is_active=1 AND deleted_at IS NULL AND EXISTS (SELECT 1 FROM evaluations WHERE employee_id=employees.employee_id AND status='Approved')");
$employeeStmt->bind_param('i', $employeeId);
$employeeStmt->execute();
$employee = $employeeStmt->get_result()->fetch_assoc();
$employeeStmt->close();
if (!$employee) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Candidate not found.']);
    exit;
}

function successionEvidenceRows(mysqli $conn, string $sql, int $employeeId): array
{
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function successionEvidenceMonths(?string $from, ?string $to): ?int
{
    if (!$from || !$to || $to < $from) return null;
    $interval = (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to));
    return $interval->y * 12 + $interval->m;
}

$evaluations = successionEvidenceRows($conn, "SELECT evaluation_type,evaluation_period_start,evaluation_period_end,total_score,approved_date FROM evaluations WHERE employee_id=? AND status='Approved' AND total_score IS NOT NULL ORDER BY approved_date DESC,evaluation_id DESC LIMIT 6", $employeeId);
$education = successionEvidenceRows($conn, 'SELECT education_level,school_name,degree_course,year_graduated,honors_received FROM employee_education WHERE employee_id=? ORDER BY education_id DESC', $employeeId);
$trainings = successionEvidenceRows($conn, 'SELECT training_title,training_type,date_from,date_to,no_of_hours,conducted_by FROM employee_trainings WHERE employee_id=? ORDER BY date_from DESC,training_id DESC', $employeeId);
$voluntary = successionEvidenceRows($conn, 'SELECT organization_name,position_nature,date_from,date_to,no_of_hours FROM employee_voluntary_work WHERE employee_id=? ORDER BY date_from DESC,voluntary_id DESC', $employeeId);
$work = successionEvidenceRows($conn, 'SELECT job_title,company_name,date_from,date_to,appointment_status FROM employee_work_experience WHERE employee_id=? ORDER BY date_from DESC,work_id DESC', $employeeId);
foreach ($work as &$job) $job['months'] = successionEvidenceMonths($job['date_from'], $job['date_to'] ?: date('Y-m-d'));
unset($job);
$movements = successionEvidenceRows($conn, "SELECT previous_position,new_position,effective_date FROM career_movements WHERE employee_id=? AND approval_status='Approved' AND is_applied=1 AND effective_date<=CURDATE() ORDER BY effective_date,movement_id", $employeeId);
$promotions = successionEvidenceRows($conn, "SELECT se.previous_job_title AS previous_position,j.job_title AS new_position,DATE(se.applied_at) AS effective_date FROM succession_endorsements se JOIN job_titles j ON j.job_title_id=se.target_job_title_id WHERE se.employee_id=? AND se.status='Approved' AND se.applied_at IS NOT NULL ORDER BY se.applied_at,se.endorsement_id", $employeeId);
$movements = array_merge($movements, $promotions);
usort($movements, static fn(array $a, array $b): int => strcmp((string)$a['effective_date'], (string)$b['effective_date']));

$priorPositions = [];
$startDate = $employee['hire_date'];
$previousNewPosition = null;
foreach ($movements as $movement) {
    $position = trim((string)$movement['previous_position']);
    $from = $previousNewPosition === null || strcasecmp($previousNewPosition, $position) === 0 ? $startDate : null;
    if ($position !== '') {
        $priorPositions[] = [
            'position' => $position,
            'from' => $from,
            'to' => $movement['effective_date'],
            'months' => successionEvidenceMonths($from, $movement['effective_date']),
        ];
    }
    $startDate = $movement['effective_date'];
    $previousNewPosition = trim((string)$movement['new_position']);
}

$currentStart = !$movements || strcasecmp((string)$previousNewPosition, (string)$employee['job_title']) === 0 ? $startDate : null;
echo json_encode([
    'success' => true,
    'current_position' => [
        'title' => $employee['job_title'],
        'from' => $currentStart,
        'months' => successionEvidenceMonths($currentStart, date('Y-m-d')),
        'based_on' => $movements ? 'Last applied movement' : 'Hire date; no applied movement history recorded',
    ],
    'prior_positions' => array_reverse($priorPositions),
    'evaluations' => $evaluations,
    'education' => $education,
    'trainings' => $trainings,
    'voluntary_work' => $voluntary,
    'work_experience' => $work,
]);
