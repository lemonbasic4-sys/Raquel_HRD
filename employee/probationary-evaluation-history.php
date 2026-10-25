<?php
$page_title = 'Probationary Evaluation History';
require_once '../includes/session-check.php';
require_once '../includes/functions.php';
ensureOrganizationEvaluationPackageSchema($conn);
ensureProbationaryEvaluationSchema($conn);

$userId = (int)($_SESSION['user_id'] ?? 0);
$reviewerMatch = organizationPackageReviewerMatchSql('rs');
$stmt = $conn->prepare("SELECT ep.package_id, ep.evaluation_type, ep.period_start, ep.period_end,
        ep.updated_at AS completed_at, d.department_name, et.template_name,
        ev.evaluation_id, ev.total_score, ev.status AS evaluation_status,
        CONCAT(e.first_name, ' ', e.last_name) AS employee_name, e.job_title
    FROM evaluation_packages ep
    JOIN evaluation_package_members pm ON pm.package_id = ep.package_id
    JOIN evaluations ev ON ev.evaluation_id = pm.evaluation_id AND ev.deleted_at IS NULL
    JOIN employees e ON e.employee_id = ev.employee_id
    JOIN departments d ON d.department_id = ep.department_id
    JOIN evaluation_templates et ON et.template_id = ep.template_id
    WHERE ep.evaluation_type IN ('Initial', 'Final')
      AND ep.status = 'Approved and Applied'
      AND EXISTS (SELECT 1 FROM evaluation_package_route_steps rs
                  WHERE rs.package_id = ep.package_id AND $reviewerMatch)
    ORDER BY ep.updated_at DESC, ep.package_id DESC, e.last_name, e.first_name");
$stmt->bind_param('ii', $userId, $userId);
$stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
require_once '../includes/header.php';
?>
<style>
.prob-history-shell{max-width:1200px;margin:auto}
.prob-history-shell .page-hero{background:#294306;border:1px solid #294306;box-shadow:0 8px 20px rgba(18,45,9,.14)}
.prob-history-shell .page-hero::before{display:none}
.prob-history-nav{display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:1.25rem}
.prob-history-nav a{display:inline-flex;align-items:center;gap:.5rem;padding:.7rem 1rem;border:1px solid #294306;border-radius:10px;color:#294306;background:#fff;text-decoration:none;font-weight:700}
.prob-history-nav a:hover,.prob-history-nav a.active{background:#294306;color:#fff;border-color:#294306}
.prob-history-card{background:#fff;border:1px solid #e1e8df;border-radius:16px;box-shadow:0 10px 25px rgba(22,49,15,.06);overflow:hidden}
.prob-history-head{display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;padding:1.2rem 1.4rem;border-bottom:1px solid #e9eee8}
.prob-history-head h2{font-size:1.18rem;margin:0;color:#173b1b}
.prob-history-head p{margin:.2rem 0 0;color:#667466;font-size:.85rem}
.prob-history-count{border-radius:999px;padding:.3rem .65rem;background:#294306;color:#fff;font-size:.75rem;font-weight:700}
.prob-history-table{margin:0;min-width:830px}
.prob-history-table th{background:#eef3eb;color:#345039;text-transform:uppercase;font-size:.72rem;letter-spacing:.03em;white-space:nowrap}
.prob-history-table td,.prob-history-table th{padding:.85rem 1rem;vertical-align:middle}
.prob-history-table td{font-size:.84rem}
.prob-history-type{border-radius:999px;padding:.25rem .55rem;background:#bd9414;color:#172b09;font-weight:700;font-size:.75rem}
.prob-history-table .btn-outline-success{background:#294306;border-color:#294306;color:#fff}
.prob-history-table .btn-outline-success:hover{background:#193403;border-color:#193403;color:#fff}
.prob-history-empty{padding:3rem 1rem;text-align:center;color:#667466}
</style>
<div class="prob-history-shell">
    <div class="page-hero fadeup mb-4">
        <div class="small text-white-50 text-uppercase mb-1">Performance Evaluation · Probationary Employees</div>
        <h1 class="h3 text-white mb-2"><i class="fas fa-history me-2" aria-hidden="true"></i>Probationary Evaluation History</h1>
        <p class="text-white-50 mb-0">Completed Initial and Final evaluations available to your reviewer account.</p>
    </div>
    <nav class="prob-history-nav" aria-label="Probationary evaluation views">
        <a href="<?php echo BASE_URL; ?>/employee/team-evaluation-packages.php?category=probationary"><i class="fas fa-users" aria-hidden="true"></i>Active evaluations</a>
        <a class="active" aria-current="page" href="<?php echo BASE_URL; ?>/employee/probationary-evaluation-history.php"><i class="fas fa-history" aria-hidden="true"></i>Probationary Evaluation History</a>
    </nav>
    <section class="prob-history-card">
        <div class="prob-history-head"><div><h2>Completed Evaluations</h2><p>Read only records from approved probationary packages.</p></div><span class="prob-history-count"><?php echo count($history); ?> records</span></div>
        <?php if (!$history): ?>
            <div class="prob-history-empty"><i class="fas fa-folder-open fa-2x mb-3" aria-hidden="true"></i><p class="mb-0">No completed probationary evaluations are available to your account yet.</p></div>
        <?php else: ?>
            <div class="table-responsive"><table class="table table-hover prob-history-table"><thead><tr><th>Employee</th><th>Type</th><th>Department</th><th>Period</th><th>Final score</th><th>Completed</th><th>Action</th></tr></thead><tbody>
            <?php foreach ($history as $row): ?><tr>
                <td><strong><?php echo e($row['employee_name']); ?></strong><small class="d-block text-muted"><?php echo e($row['job_title']); ?></small></td>
                <td><span class="prob-history-type"><?php echo e($row['evaluation_type']); ?></span></td>
                <td><?php echo e($row['department_name']); ?></td>
                <td><?php echo e($row['period_start'].' to '.$row['period_end']); ?></td>
                <td><strong><?php echo $row['total_score'] === null ? '—' : number_format((float)$row['total_score'],2); ?></strong><small class="text-muted"> / 4.00</small></td>
                <td><?php echo e(formatDate($row['completed_at'])); ?></td>
                <td><a class="btn btn-sm btn-outline-success" href="<?php echo BASE_URL; ?>/employee/package-member-view.php?package_id=<?php echo (int)$row['package_id']; ?>&amp;evaluation_id=<?php echo (int)$row['evaluation_id']; ?>">View evaluation</a></td>
            </tr><?php endforeach; ?></tbody></table></div>
        <?php endif; ?>
    </section>
</div>
<?php require_once '../includes/footer.php'; ?>
