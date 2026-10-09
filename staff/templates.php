<?php
$page_title = 'Template Viewing';
require_once '../includes/session-check.php';
checkRole(['HR Staff']);
require_once '../includes/functions.php';
if (!ensureHierarchicalEvaluationSchema($conn)) {
    http_response_code(500);
    exit('Evaluation template schema is unavailable.');
}
require_once '../includes/header.php';

// Fetch active templates with criteria count and total weight
$template_scope = '1 = 1';
$templates = $conn->query("SELECT et.*,
    (SELECT GROUP_CONCAT(DISTINCT jt.job_title ORDER BY jt.job_title SEPARATOR ', ')
        FROM job_titles jt
        WHERE jt.job_title_id IN (
            SELECT etp.job_title_id FROM evaluation_template_positions etp WHERE etp.template_id = et.template_id
            UNION
            SELECT et.target_job_title_id WHERE et.target_job_title_id IS NOT NULL
              AND NOT EXISTS (SELECT 1 FROM evaluation_template_positions etp WHERE etp.template_id = et.template_id)
        )) AS target_position_name,
    u.full_name as created_by_name,
    (SELECT COUNT(*) FROM evaluation_criteria WHERE template_id = et.template_id) as criteria_count,
    (SELECT SUM(weight) FROM evaluation_criteria WHERE template_id = et.template_id) as total_weight
    FROM evaluation_templates et
    LEFT JOIN users u ON et.created_by = u.user_id
    WHERE et.status = 'Active' AND et.deleted_at IS NULL AND $template_scope
    ORDER BY et.template_name ASC");
$template_total = (int) $templates->num_rows;
$criteria_total = (int) ($conn->query("SELECT COUNT(*) as c FROM evaluation_criteria ec JOIN evaluation_templates et ON ec.template_id = et.template_id WHERE et.status = 'Active' AND et.deleted_at IS NULL AND $template_scope")->fetch_assoc()['c'] ?? 0);
?>

<div class="staff-template-page">
<div class="page-hero fadeup">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-3">
        <div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:0;color:rgba(255,255,255,.55);">HR Staff · Template Library</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-file-alt me-2" style="color:#BD9414;"></i>Template Viewing</h4>
        </div>
        <div class="badge bg-white text-dark border-0 py-2 px-3" style="border-radius:20px;font-size:.75rem;box-shadow:0 4px 10px rgba(0,0,0,.1);">
            <i class="fas fa-lock me-1 text-primary"></i>Read-only access
        </div>
    </div>
    <p class="text-white-50 small mb-0"><i class="fas fa-eye me-1"></i>Browse active evaluation templates and review their scoring criteria before creating evaluations.</p>

    <div class="row g-3 mt-4">
        <div class="col-6 col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo $template_total; ?></div>
                        <div class="stat-label">Active Templates</div>
                    </div>
                    <i class="fas fa-layer-group stat-icon" style="color:#0d6efd;"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo $criteria_total; ?></div>
                        <div class="stat-label">Scoring Criteria</div>
                    </div>
                    <i class="fas fa-list-check stat-icon" style="color:#28a745;"></i>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><i class="fas fa-eye"></i></div>
                        <div class="stat-label">View Details</div>
                    </div>
                    <i class="fas fa-file-signature stat-icon" style="color:#ffc107;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($templates->num_rows === 0): ?>
    <div class="content-card">
        <div class="card-body text-center py-5">
            <i class="fas fa-file-alt fa-3x mb-3 text-muted opacity-25"></i>
            <h5 class="text-muted">No Active Templates</h5>
            <p class="text-muted small">There are currently no active evaluation templates available for viewing.</p>
        </div>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php while ($t = $templates->fetch_assoc()): 
            $tw = (float)($t['total_weight'] ?? 0);
            $wclass = abs($tw - 100) < 0.01 ? 'bg-success-subtle text-success border-success-subtle' : 'bg-warning-subtle text-warning border-warning-subtle';
        ?>
            <div class="col-md-6 col-lg-4">
                <div class="content-card h-100 template-card">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="template-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <?php if (!empty($t['target_department'])): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2"><?php echo e($t['target_department']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($t['target_position_name'])): ?>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2"><?php echo e($t['target_position_name']); ?></span>
                            <?php endif; ?>
                        </div>
                        <h6 class="fw-bold text-dark mb-2"><?php echo e($t['template_name']); ?></h6>
                        <p class="text-muted small mb-3" style="line-height: 1.5;"><?php echo e(substr($t['description'] ?? 'No description provided.', 0, 120)); ?><?php echo strlen($t['description'] ?? '') > 120 ? '...' : ''; ?></p>
                        
                        <div class="d-flex gap-2 mb-3">
                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2">
                                <i class="fas fa-list-ul me-1"></i><?php echo $t['criteria_count']; ?> criteria
                            </span>
                            <span class="badge <?php echo $wclass; ?> border px-2">
                                <i class="fas fa-balance-scale me-1"></i><?php echo $tw; ?>%
                            </span>
                        </div>

                        <?php if (!empty($t['created_by_name'])): ?>
                            <div class="text-muted small mb-3">
                                <i class="fas fa-user-edit me-1 opacity-50"></i>Created by <?php echo e($t['created_by_name']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-transparent border-top-0 p-4 pt-0">
                        <a href="<?php echo BASE_URL; ?>/staff/view-template.php?id=<?php echo $t['template_id']; ?>" class="btn btn-outline-primary w-100">
                            <i class="fas fa-eye me-2"></i>View Details
                        </a>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
<?php endif; ?>
</div>

<style>
.staff-template-page .row.g-4 {
    align-items: stretch;
}
.template-card { transition: transform 0.2s ease, box-shadow 0.2s ease; border: 1.5px solid #f0f0f0; }
.template-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
.template-icon { 
    width: 44px; height: 44px; border-radius: 12px; 
    background: linear-gradient(135deg, #e7f1ff, #c9dfff); 
    display: flex; align-items: center; justify-content: center; 
    color: var(--primary-blue, #0d6efd); font-size: 1.1rem; 
}
.bg-info-subtle { background-color: #e0f7fa; }
.bg-secondary-subtle { background-color: #f5f5f5; }
.bg-success-subtle { background-color: #e8f5e9; }
.bg-warning-subtle { background-color: #fff9c4; }
.badge { border-radius: 6px; font-weight: 600; font-size: 0.7rem; letter-spacing: 0; }
</style>

<?php require_once '../includes/footer.php'; ?>
