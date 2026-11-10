<?php
/** Shared read queries for the existing HR Manager and HR Supervisor histories. */
require_once __DIR__ . '/functions.php';

function hrHistorySql(string $role): array
{
    if (!in_array($role, ['HR Manager', 'HR Supervisor'], true)) throw new InvalidArgumentException('Unsupported history role.');
    $from = " FROM evaluations ev
        LEFT JOIN employees e ON e.employee_id=ev.employee_id
        LEFT JOIN departments d ON d.department_id=e.department_id
        LEFT JOIN branches b ON b.branch_id=e.branch_id
        LEFT JOIN evaluation_templates et ON et.template_id=ev.template_id
        LEFT JOIN evaluation_package_members pm ON pm.evaluation_id=ev.evaluation_id
            AND pm.package_id=(SELECT MAX(recent_pm.package_id)
                FROM evaluation_package_members recent_pm
                JOIN evaluation_packages recent_pkg ON recent_pkg.package_id=recent_pm.package_id
                WHERE recent_pm.evaluation_id=ev.evaluation_id AND recent_pkg.status<>'Cancelled')
        LEFT JOIN evaluation_packages ep ON ep.package_id=pm.package_id";
    $where = $role === 'HR Manager'
        ? "((ep.package_id IS NOT NULL AND ep.status='Approved and Applied')
            OR (ep.package_id IS NULL AND ev.status IN ('Approved','Imported','Rejected','Returned')))
            AND ev.employee_id NOT IN (SELECT employee_id FROM users WHERE role='Admin' AND employee_id IS NOT NULL)"
        : "ev.status IN ('Approved','Imported','Rejected','Returned')";
    $type = 'COALESCE(ep.evaluation_type,et.evaluation_type,ev.evaluation_type)';
    $category = "CASE WHEN $type IN ('Initial','Final') THEN 'probationary' ELSE 'regular' END";
    $status = $role === 'HR Manager'
        ? "CASE WHEN ep.status='Approved and Applied' THEN 'Approved' ELSE COALESCE(ep.status,ev.status) END"
        : 'ev.status';
    return compact('from', 'where', 'type', 'category', 'status');
}

function hrHistoryRows($conn, string $sql, array $params = []): array
{
    $stmt = $conn->prepare($sql);
    if ($params) {
        $types = implode('', array_map(static fn($p) => is_int($p) ? 'i' : 's', $params));
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function hrHistorySelect(bool $detail = false): string
{
    $fields = $detail ? 'ev.*' : 'ev.evaluation_id,ev.employee_id,ev.evaluation_type,ev.status,ev.total_score,
        ev.kra_subtotal,ev.behavior_average,ev.performance_level,ev.is_historical,
        ev.created_at,ev.updated_at,ev.submitted_date,ev.approved_date';
    return "SELECT $fields,ev.submitted_date AS submitted_at,e.employee_code,
        CONCAT(e.first_name,' ',e.last_name) AS employee_name,e.job_title,e.rank_category_id,e.profile_picture,
        d.department_name,b.branch_name,et.template_name,et.form_code,
        ep.package_id,ep.status AS package_status,
        COALESCE(ep.evaluation_type,et.evaluation_type,ev.evaluation_type) AS package_evaluation_type,
        ep.period_start,ep.period_end,ep.current_step_order,
        (SELECT full_name FROM users WHERE user_id=ev.submitted_by) AS submitted_by_name,
        (SELECT full_name FROM users WHERE user_id=ev.endorsed_by) AS endorsed_by_name,
        (SELECT full_name FROM users WHERE user_id=ev.approved_by) AS approved_by_name";
}

function hrHistoryDetail($conn, string $role, int $evaluationId): ?array
{
    $s = hrHistorySql($role);
    return hrHistoryRows($conn, hrHistorySelect(true) . $s['from'] . ' WHERE (' . $s['where'] . ') AND ev.evaluation_id=? LIMIT 1', [$evaluationId])[0] ?? null;
}

function hrHistoryPage($conn, string $role, array $input): array
{
    $s = hrHistorySql($role);
    $category = ($input['category'] ?? '') === 'regular' ? 'regular' : 'probationary';
    $statuses = ['All', 'Approved', 'Imported', 'Rejected', 'Returned'];
    $status = in_array($input['status'] ?? '', $statuses, true) ? $input['status'] : 'All';
    $filters = ['category'=>$category, 'status'=>$status];
    foreach (['branch', 'department', 'template', 'evaluation_type', 'q'] as $key) {
        $filters[$key] = is_string($input[$key] ?? null) ? mb_substr(trim($input[$key]), 0, 200) : ($key === 'q' ? '' : 'All');
    }
    $counts = ['probationary'=>0, 'regular'=>0];
    $totals = array_fill_keys($statuses, 0);
    $statusCounts = array_fill_keys($statuses, 0);
    foreach (hrHistoryRows($conn, "SELECT {$s['category']} AS category,{$s['status']} AS status,COUNT(*) AS n" . $s['from'] . " WHERE {$s['where']} GROUP BY category,status") as $row) {
        $n = (int)$row['n'];
        $counts[$row['category']] += $n;
        $totals['All'] += $n;
        $totals[$row['status']] = ($totals[$row['status']] ?? 0) + $n;
        if ($row['category'] === $category) {
            $statusCounts['All'] += $n;
            $statusCounts[$row['status']] = ($statusCounts[$row['status']] ?? 0) + $n;
        }
    }
    // Options come from all eligible history in this category, never just page 1.
    $options = ['branch'=>[], 'department'=>[], 'template'=>[], 'evaluation_type'=>[]];
    $metadata = hrHistoryRows($conn, "SELECT DISTINCT COALESCE(b.branch_name,'Unassigned') AS branch,
        COALESCE(d.department_name,'Unassigned') AS department,COALESCE(et.template_name,'Unassigned') AS template,
        {$s['type']} AS evaluation_type" . $s['from'] . " WHERE ({$s['where']}) AND ({$s['category']})=?", [$category]);
    foreach ($metadata as $row) foreach ($options as $key=>$_) {
        if ($row[$key] !== null && $row[$key] !== '') $options[$key][$row[$key]] = $row[$key];
    }
    foreach ($options as $key=>&$values) {
        natcasesort($values);
        $values = array_values($values);
        if (!in_array($filters[$key], $values, true)) $filters[$key] = 'All';
    }
    unset($values);
    $where = "({$s['where']}) AND ({$s['category']})=?";
    $params = [$category];
    $columns = ['branch'=>"COALESCE(b.branch_name,'Unassigned')", 'department'=>"COALESCE(d.department_name,'Unassigned')", 'template'=>"COALESCE(et.template_name,'Unassigned')", 'evaluation_type'=>$s['type']];
    foreach ($columns as $key=>$column) if ($filters[$key] !== 'All') {
        $where .= " AND ($column)=?";
        $params[] = $filters[$key];
    }
    if ($status !== 'All') { $where .= " AND ({$s['status']})=?"; $params[] = $status; }
    if ($filters['q'] !== '') {
        $where .= " AND CONCAT_WS(' ',e.first_name,e.last_name,e.employee_code,e.job_title,d.department_name,et.template_name,et.form_code) LIKE ? ESCAPE '!'";
        $params[] = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']) . '%';
    }
    $total = (int)hrHistoryRows($conn, 'SELECT COUNT(*) AS n' . $s['from'] . ' WHERE ' . $where, $params)[0]['n'];
    $pageSize = 10;
    $pages = max(1, (int)ceil($total / $pageSize));
    $page = min($pages, max(1, (int)($input['page'] ?? 1)));
    $offset = ($page - 1) * $pageSize;
    $rows = hrHistoryRows($conn, hrHistorySelect() . $s['from'] . ' WHERE ' . $where . ' ORDER BY ev.updated_at DESC,ev.evaluation_id DESC LIMIT ? OFFSET ?', [...$params, $pageSize, $offset]);
    return ['rows'=>$rows, 'filters'=>$filters, 'options'=>$options, 'counts'=>$counts, 'totals'=>$totals,
        'statusCounts'=>$statusCounts, 'total'=>$total, 'page'=>$page, 'pages'=>$pages, 'pageSize'=>$pageSize,
        'first'=>$total ? $offset + 1 : 0, 'last'=>$offset + count($rows)];
}

function hrHistoryPageUrl(array $filters, int $page): string
{
    $query = array_filter($filters, static fn($value) => $value !== '' && $value !== 'All');
    $query['page'] = $page;
    return '?' . http_build_query($query);
}
