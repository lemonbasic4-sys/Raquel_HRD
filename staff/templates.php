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
            <i class="fas fa-lock me-1"></i>Read-only access
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
                        <div class="stat-value" style="font-size:1.25rem;">Read only</div>
                        <div class="stat-label">Template access</div>
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
    <div class="staff-template-grid">
        <?php while ($t = $templates->fetch_assoc()): 
            $tw = (float)($t['total_weight'] ?? 0);
            $wclass = abs($tw - 100) < 0.01 ? 'bg-success-subtle text-success border-success-subtle' : 'bg-warning-subtle text-warning border-warning-subtle';
        ?>
            <div class="staff-template-grid-item">
                <div class="content-card h-100 template-card">
                    <div class="card-body p-4">
                        <div class="staff-template-card-header mb-3">
                            <div class="template-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="staff-template-tags">
                            <?php if (!empty($t['target_department'])): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2"><?php echo e($t['target_department']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($t['target_position_name'])): ?>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2"><?php echo e($t['target_position_name']); ?></span>
                            <?php endif; ?>
                            </div>
                        </div>
                        <h6 class="fw-bold text-dark mb-2"><?php echo e($t['template_name']); ?></h6>
                        <p class="text-muted small mb-3 staff-template-description"><?php echo e($t['description'] ?: 'No description provided.'); ?></p>
                        
                        <div class="d-flex flex-wrap gap-2 mb-3">
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
.staff-template-page { min-width:0; width:100%; }
.staff-template-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr)); gap:20px; }
.staff-template-grid-item { min-width:0; }
.staff-template-page .template-card { display:flex; flex-direction:column; min-width:0; padding:0; overflow:hidden; transition:box-shadow .2s ease; border:1px solid #e1e8dc; border-radius:14px; }
.staff-template-page .template-card:hover { box-shadow:0 8px 25px rgba(20,59,18,.08); }
.staff-template-page .template-card .card-body { flex:1; min-width:0; }
.staff-template-card-header { display:flex; align-items:flex-start; gap:12px; }
.staff-template-tags { display:flex; flex-wrap:wrap; gap:6px; min-width:0; flex:1; }
.staff-template-tags .badge { max-width:100%; white-space:normal; overflow-wrap:anywhere; text-align:left; line-height:1.5; padding:5px 9px; }
.staff-template-page .template-card h6 { line-height:1.45; overflow-wrap:anywhere; font-size:1rem; }
.staff-template-description { line-height:1.6; overflow-wrap:anywhere; }
.staff-template-page .template-card .card-footer { margin-top:auto; }
.staff-template-page .template-icon {
    width: 44px; height: 44px; border-radius: 12px; 
    flex:0 0 44px; background:#edf3e8;
    display: flex; align-items: center; justify-content: center; 
    color:#185425; font-size:1.1rem;
}
.staff-template-page .bg-secondary-subtle { background-color:#f0f2ee; }
.staff-template-page .bg-success-subtle { background-color:#e8f5e9; }
.staff-template-page .bg-warning-subtle { background-color:#fff5d6; }
.staff-template-page .badge { border-radius:6px; font-weight:600; font-size:.72rem; letter-spacing:0; }
.staff-template-page .page-hero .stat-icon { color:#d7c77c!important; font-size:1.5rem; opacity:.8; }
.staff-template-page .page-hero .stat-card { height:100%; padding:16px; }
.staff-template-page .page-hero .stat-label { font-size:.75rem; white-space:normal; }
.staff-template-page .page-hero .row { margin-top:8px!important; }
@media(max-width:575px) { .staff-template-grid { gap:14px; } .staff-template-page .template-card .card-body { padding:18px!important; } .staff-template-page .template-card .card-footer { padding:0 18px 18px!important; } .staff-template-page .page-hero { padding:20px!important; } }
@media(prefers-reduced-motion:reduce) { .staff-template-page .template-card { transition:none; } }
</style>

<?php require_once '../includes/footer.php'; ?>
