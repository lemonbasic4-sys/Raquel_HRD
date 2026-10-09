<?php
require_once '../includes/session-check.php';
checkRole(['HR Staff']);
if (!ensureHierarchicalEvaluationSchema($conn)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Evaluation template schema is unavailable.']);
    exit;
}

$template_id = isset($_GET['template_id']) ? (int)$_GET['template_id'] : 0;
if (!$template_id) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'A valid template ID is required.']);
    exit;
}

$employee_id = (int)($_SESSION['employee_id'] ?? 0);
$access = $conn->prepare("SELECT 1 FROM evaluation_templates et
    JOIN employees e ON e.employee_id = ? AND e.is_active = 1 AND e.deleted_at IS NULL
    LEFT JOIN departments d ON d.department_id = e.department_id
    WHERE et.template_id = ? AND et.status = 'Active' AND et.deleted_at IS NULL
      AND (et.target_department_id IS NULL OR et.target_department_id = e.department_id)
      AND ((EXISTS (SELECT 1 FROM evaluation_template_positions etp WHERE etp.template_id = et.template_id)
            AND EXISTS (SELECT 1 FROM evaluation_template_positions etp WHERE etp.template_id = et.template_id AND etp.job_title_id = e.job_title_id))
        OR (NOT EXISTS (SELECT 1 FROM evaluation_template_positions etp WHERE etp.template_id = et.template_id)
            AND (et.target_job_title_id IS NULL OR et.target_job_title_id = e.job_title_id)))
      AND (et.target_department IS NULL OR et.target_department = '' OR et.target_department = 'All Departments'
          OR et.target_department = d.department_name)
    LIMIT 1");
$access->bind_param('ii', $employee_id, $template_id);
$access->execute();
$can_view = (bool)$access->get_result()->fetch_assoc();
$access->close();
if (!$can_view) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Template is not assigned to your active department and position.']);
    exit;
}

$criteria_stmt = $conn->prepare("SELECT criterion_id, section, criterion_name, description, kpi_description, weight, scoring_method, sort_order
    FROM evaluation_criteria WHERE template_id = ? ORDER BY section, sort_order");
$criteria_stmt->bind_param('i', $template_id);
$criteria_stmt->execute();
$result = $criteria_stmt->get_result();

$criteria = ['kra' => [], 'behavior' => []];
while ($row = $result->fetch_assoc()) {
    if ($row['section'] === 'Behavior') {
        $criteria['behavior'][] = $row;
    } else {
        $criteria['kra'][] = $row;
    }
}
$criteria_stmt->close();

// Also return template weight split
$tmpl_stmt = $conn->prepare('SELECT kra_weight, behavior_weight FROM evaluation_templates WHERE template_id = ?');
$tmpl_stmt->bind_param('i', $template_id);
$tmpl_stmt->execute();
$tmpl = $tmpl_stmt->get_result()->fetch_assoc();
$tmpl_stmt->close();
$criteria['kra_weight'] = (float)($tmpl['kra_weight'] ?? 80);
$criteria['behavior_weight'] = (float)($tmpl['behavior_weight'] ?? 20);

header('Content-Type: application/json');
echo json_encode($criteria);
