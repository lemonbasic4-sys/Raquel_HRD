<?php
require_once '../../includes/session-check.php';
checkRole(['HR Manager', 'HR Supervisor', 'Employee', 'President and CEO']);
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/succession-endorsements.php';

header('Content-Type: application/json');

$position   = trim($_GET['position']   ?? '');
$branch_id  = (int)($_GET['branch_id']  ?? 0);
$dept_id    = (int)($_GET['dept_id']    ?? 0);
$employee_id = (int)($_GET['employee_id'] ?? 0);
$isCeoViewer = in_array(($_SESSION['role'] ?? ''), ['Employee', 'President and CEO'], true);
if ($isCeoViewer) {
    if (!successionIsCeoUser($conn, (int)$_SESSION['user_id']) || $employee_id < 1) { http_response_code(403); echo json_encode(['success'=>false]); exit; }
    $access = $conn->prepare("SELECT 1 FROM succession_endorsements WHERE employee_id=? AND status IN ('Pending CEO Approval','Approved','Rejected') LIMIT 1");
    $access->bind_param('i', $employee_id);
    $access->execute();
    $canView = $access->get_result()->num_rows > 0;
    $access->close();
    if (!$canView) { http_response_code(403); echo json_encode(['success'=>false]); exit; }
}
$scope      = trim($_GET['scope']       ?? 'company'); // 'company' | 'branch' | 'department'

/* ── Build WHERE for scope ── */
$scope_where = '';
$params = [];
$types  = '';

if ($scope === 'branch' && $branch_id > 0) {
    $scope_where = ' AND e.branch_id = ?';
    $params[] = $branch_id;
    $types   .= 'i';
} elseif ($scope === 'department' && $dept_id > 0) {
    $scope_where = ' AND e.department_id = ?';
    $params[] = $dept_id;
    $types   .= 'i';
}

/* ── Exclude admins ── */
$exclude_admins = " AND e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)";

if ($position !== '') {
    $scope_where .= ' AND e.job_title = ?';
    $params[] = $position;
    $types .= 's';
}
if ($employee_id > 0) {
    $scope_where .= ' AND e.employee_id = ?';
    $params[] = $employee_id;
    $types .= 'i';
}

$sql = "
    SELECT
        e.employee_id,
        e.employee_code,
        CONCAT(e.first_name, ' ', COALESCE(e.middle_name, ''), ' ', e.last_name) AS full_name,
        CONCAT(e.first_name, ' ', e.last_name) AS short_name,
        e.job_title,
        e.job_title_id,
        e.department_id,
        e.profile_picture,
        (SELECT u.profile_picture FROM users u
         WHERE u.employee_id = e.employee_id
           AND u.profile_picture IS NOT NULL AND u.profile_picture <> ''
         ORDER BY CASE WHEN u.role = 'Employee' THEN 0 ELSE 1 END, u.user_id
         LIMIT 1) AS user_profile_picture,
        b.branch_name,
        d.department_name,
        TIMESTAMPDIFF(YEAR, e.hire_date, CURDATE()) AS years_of_service,
        COUNT(ev.evaluation_id)                       AS eval_count,
        ROUND(AVG(ev.total_score), 2)                 AS avg_score,
        ROUND(STDDEV(ev.total_score), 2)              AS score_stddev,
        MAX(ev.total_score)                           AS best_score,
        MIN(ev.total_score)                           AS worst_score,
        /* Trend: compare last eval to second-to-last */
        (SELECT ev2.total_score FROM evaluations ev2
            WHERE ev2.employee_id = e.employee_id AND ev2.status = 'Approved'
            ORDER BY ev2.approved_date DESC LIMIT 1
        ) AS latest_score,
        (SELECT ev3.total_score FROM evaluations ev3
            WHERE ev3.employee_id = e.employee_id AND ev3.status = 'Approved'
            ORDER BY ev3.approved_date DESC LIMIT 1 OFFSET 1
        ) AS prev_score
    FROM employees e
    LEFT JOIN branches    b  ON e.branch_id    = b.branch_id
    LEFT JOIN departments d  ON e.department_id = d.department_id
    INNER JOIN evaluations ev ON ev.employee_id = e.employee_id AND ev.status = 'Approved'
    WHERE e.is_active = 1
    $scope_where
    $exclude_admins
    GROUP BY e.employee_id
    HAVING eval_count >= 1
    ORDER BY avg_score DESC, years_of_service DESC
    LIMIT 10
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$candidates = [];

while ($row = $result->fetch_assoc()) {
    $avg   = (float)$row['avg_score'];
    $latest = (float)($row['latest_score'] ?? $avg);
    $prev   = (float)($row['prev_score']   ?? $latest);

    /* Performance Level */
    if ($avg >= 3.60)      $level = 'Outstanding';
    elseif ($avg >= 2.60)  $level = 'Exceeds Expectations';
    elseif ($avg >= 2.00)  $level = 'Meets Expectations';
    else                   $level = 'Needs Improvement';

    /* Evaluation signal only; promotion requires separate evidence review. */
    if ($avg >= 3.80)      $badge = 'Top Evaluation Score';
    elseif ($avg >= 3.40)  $badge = 'Strong Evaluation Score';
    elseif ($avg >= 2.60)  $badge = 'Above Expectations';
    else                   $badge = 'Review Evidence';

    /* Trend */
    $trend = 'stable';
    if ($row['prev_score'] !== null) {
        $diff = $latest - $prev;
        if ($diff >= 0.15)      $trend = 'improving';
        elseif ($diff <= -0.15) $trend = 'declining';
    }

    $employee_avatar = getEmployeeAvatar($row['profile_picture']);
    $avatar_url = !str_ends_with($employee_avatar, '/logo/logo.png')
        ? $employee_avatar
        : getUserAvatar($row['user_profile_picture'], $row['profile_picture']);

    $candidates[] = [
        'employee_id'     => (int)$row['employee_id'],
        'employee_code'   => $row['employee_code'],
        'full_name'       => $row['short_name'],
        'job_title'       => $row['job_title'],
        'job_title_id'    => (int)($row['job_title_id'] ?? 0),
        'department_id'   => (int)($row['department_id'] ?? 0),
        'profile_picture' => $row['profile_picture'],
        'avatar_url'      => $avatar_url,
        'branch_name'     => $row['branch_name'],
        'department_name' => $row['department_name'],
        'years_of_service'=> (int)$row['years_of_service'],
        'eval_count'      => (int)$row['eval_count'],
        'avg_score'       => $avg,
        'score_stddev'    => (float)($row['score_stddev'] ?? 0),
        'performance_level'=> $level,
        'badge'           => $badge,
        'trend'           => $trend,
        'latest_score'    => $latest,
    ];
}
$stmt->close();

echo json_encode(['success' => true, 'candidates' => $candidates]);
