<?php
require_once '../../includes/session-check.php';
checkRole(['Employee']);
require_once '../../includes/functions.php';

header('Content-Type: application/json');

$employee_id = (int)($_SESSION['employee_id'] ?? 0);
if ($employee_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

require_once '../../includes/employee-performance-analysis.php';
$history = employeePerformanceHistory($conn, $employee_id);
$chronological = $history['evaluations'];
$all_evals = array_reverse($chronological);
$total_count = count($all_evals);
$avg_score = $total_count ? round(array_sum(array_column($all_evals, 'total_score')) / $total_count, 2) : null;
$latest_eval = $all_evals[0] ?? null;
$latest_score = $latest_eval['total_score'] ?? null;
$prev_score = $all_evals[1]['total_score'] ?? null;
$change = employeePerformanceChange($latest_score, $prev_score);
$trend = $change['direction'];
$chart_labels = array_column($chronological, 'cycle_label');
$chart_scores = array_column($chronological, 'total_score');

// ── Career readiness (out of 100) ────────────────────────────────────────────
$readiness = 0;
if ($avg_score !== null) {
    $eval_weight = min(($avg_score / 4.00) * 60, 60);

    $hire_stmt = $conn->prepare("SELECT hire_date FROM employees WHERE employee_id = ?");
    $hire_stmt->bind_param("i", $employee_id);
    $hire_stmt->execute();
    $hire_row = $hire_stmt->get_result()->fetch_assoc();
    $hire_stmt->close();

    $years_service = 0;
    if (!empty($hire_row['hire_date'])) {
        $d = (new DateTime($hire_row['hire_date']))->diff(new DateTime());
        $years_service = $d->y + ($d->m / 12);
    }

    $service_weight    = min(($years_service / 5) * 25, 25);
    $eval_count_weight = min(($total_count / 3) * 15, 15);
    $readiness = min((int)round($eval_weight + $service_weight + $eval_count_weight), 100);
}

// ── Next level target ────────────────────────────────────────────────────────
$next_level    = null;
$points_needed = null;
if ($latest_score !== null) {
    if ($latest_score < 2.00)     { $next_level = 'Meets Expectations';   $points_needed = round(2.00 - $latest_score, 2); }
    elseif ($latest_score < 2.60) { $next_level = 'Exceeds Expectations'; $points_needed = round(2.60 - $latest_score, 2); }
    elseif ($latest_score < 3.60) { $next_level = 'Outstanding';          $points_needed = round(3.60 - $latest_score, 2); }
    else                       { $next_level = 'Outstanding';           $points_needed = 0; }
}

echo json_encode([
    'success'       => true,
    'evaluations'   => $all_evals,
    'chart_labels'  => $chart_labels,
    'chart_scores'  => $chart_scores,
    'avg_score'     => $avg_score,
    'latest_score'  => $latest_score,
    'prev_score'    => $prev_score,
    'total_count'   => $total_count,
    'trend'         => $trend,
    'readiness'     => $readiness,
    'readiness_breakdown' => ['evaluation'=>round($eval_weight ?? 0, 2), 'service'=>round($service_weight ?? 0, 2), 'history'=>round($eval_count_weight ?? 0, 2)],
    'pending_evaluations' => $history['pending_evaluations'],
    'change' => $change,
    'next_level'    => $next_level,
    'points_needed' => $points_needed,
    'latest_eval'   => $latest_eval,
]);
