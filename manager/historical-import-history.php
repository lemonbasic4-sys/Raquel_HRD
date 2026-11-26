<?php
$page_title = 'Historical Import History';
require_once '../includes/session-check.php';
checkRole(['HR Manager']);
require_once '../includes/functions.php';
if (!ensureHistoricalImportSchema($conn)) {
    http_response_code(500);
    exit('Historical import storage is unavailable.');
}

$pageSize = 25;
$count = (int)$conn->query("SELECT COUNT(*) FROM evaluation_import_batches")->fetch_row()[0];
$pages = max(1, (int)ceil($count / $pageSize));
$page = min($pages, max(1, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * $pageSize;
$rows = $conn->query("SELECT b.*, u.full_name, ev.employee_id, ev.evaluation_type,
        ev.evaluation_period_start, ev.evaluation_period_end, ev.total_score,
        e.first_name, e.last_name, e.employee_code
    FROM (SELECT * FROM evaluation_import_batches ORDER BY imported_at DESC, import_batch_id DESC LIMIT $pageSize OFFSET $offset) b
    LEFT JOIN users u ON u.user_id=b.imported_by
    LEFT JOIN evaluations ev ON ev.import_batch_id=b.import_batch_id AND ev.is_historical=1
    LEFT JOIN employees e ON e.employee_id=ev.employee_id
    ORDER BY b.imported_at DESC, b.import_batch_id DESC, e.last_name, ev.evaluation_period_start, ev.evaluation_id");
require_once '../includes/header.php';
?>
<div class="page-hero">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <div class="small text-white-50">HR Manager &middot; Historical Evaluations</div>
            <h1 class="h4 text-white fw-bold mt-1 mb-0"><i class="fas fa-history me-2 text-warning" aria-hidden="true"></i>Historical Import History</h1>
        </div>
        <a class="btn btn-light btn-sm rounded-pill fw-semibold px-3" href="historical-import.php"><i class="fas fa-file-import me-1" aria-hidden="true"></i>Import Evaluations</a>
    </div>
</div>

<section class="content-card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h2 class="h6 fw-bold mb-0">Import Batches</h2>
        <span class="small text-muted"><?= number_format($count) ?> <?= $count === 1 ? 'batch' : 'batches' ?></span>
    </div>
    <?php if (!$count): ?>
        <div class="card-body text-center py-5">
            <i class="fas fa-folder-open fa-2x text-secondary mb-3" aria-hidden="true"></i>
            <h3 class="h6 fw-bold">No historical imports yet</h3>
            <p class="small text-muted mb-0">Confirmed CSV imports will appear here.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" aria-label="Imported evaluations by batch">
                <thead class="table-light small">
                    <tr><th scope="col">Employee</th><th scope="col">Evaluation</th><th scope="col">Period</th><th scope="col" class="text-end">Rating</th></tr>
                </thead>
                <?php $previous = null; foreach ($rows as $row): ?>
                    <?php if ($previous !== $row['import_batch_id']): ?>
                        <?php if ($previous !== null): ?></tbody><?php endif; ?>
                        <?php $previous = $row['import_batch_id']; ?>
                        <tbody>
                            <tr class="table-light"><th colspan="4" scope="rowgroup">
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <span class="badge bg-secondary">Batch #<?= (int)$previous ?></span>
                                    <span class="text-break"><?= e($row['uploaded_filename']) ?></span>
                                </div>
                                <div class="small fw-normal text-muted mt-1">Imported by <?= e($row['full_name'] ?? 'Unknown user') ?> &middot; <?= e(date('M j, Y g:i A', strtotime($row['imported_at']))) ?> &middot; <?= (int)$row['successful_rows'] ?> of <?= (int)$row['total_rows'] ?> rows imported</div>
                            </th></tr>
                    <?php endif; ?>
                    <?php if ($row['employee_id']): ?>
                        <tr>
                            <td><a class="fw-semibold" href="view-employee.php?id=<?= (int)$row['employee_id'] ?>"><?= e($row['first_name'].' '.$row['last_name']) ?></a><div class="small text-muted"><?= e($row['employee_code']) ?></div></td>
                            <td><?= e($row['evaluation_type']) ?></td>
                            <td><?= e($row['evaluation_period_start']) ?> to <?= e($row['evaluation_period_end']) ?></td>
                            <td class="text-end fw-semibold"><?= e($row['total_score']) ?></td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if ($previous !== null): ?></tbody><?php endif; ?>
            </table>
        </div>
    <?php endif; ?>
    <?php if ($pages > 1): ?>
        <nav class="card-footer d-flex flex-wrap align-items-center gap-2" aria-label="Import history pages">
            <span class="me-auto small text-muted">Page <?= $page ?> of <?= $pages ?></span>
            <?php if ($page > 1): ?><a class="btn btn-outline-secondary btn-sm" href="?page=<?= $page-1 ?>">Newer batches</a><?php endif; ?>
            <?php if ($page < $pages): ?><a class="btn btn-outline-secondary btn-sm" href="?page=<?= $page+1 ?>">Older batches</a><?php endif; ?>
        </nav>
    <?php endif; ?>
</section>
<?php require_once '../includes/footer.php'; ?>
