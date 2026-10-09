<?php
$page_title = 'Template Archive';
require_once '../includes/session-check.php';
checkRole(['HR Manager', 'HR Supervisor', 'HR Staff', 'Employee', 'President and CEO']);
require_once '../includes/functions.php';
if (!ensureHierarchicalEvaluationSchema($conn)) {
    http_response_code(500);
    exit('Evaluation template schema is unavailable.');
}
$creator_context = getEvaluationTemplateCreatorContext($conn, (int)($_SESSION['user_id'] ?? 0));
if (!canViewEvaluationTemplates($creator_context)) {
    redirectWith(BASE_URL . '/employee/dashboard.php', 'danger', 'Your account is not authorized to view archived templates.');
}
if (!empty($creator_context['is_ceo']) && basename(dirname($_SERVER['SCRIPT_NAME'])) !== 'employee') {
    redirectWith(BASE_URL . '/employee/evaluation-template-archive.php', 'info', 'President and CEO template archives are available in the Employee Portal.');
}
$template_list_url = !empty($creator_context['is_ceo'])
    ? BASE_URL . '/employee/evaluation-templates.php'
    : BASE_URL . '/manager/templates.php';
$template_archive_url = !empty($creator_context['is_ceo'])
    ? BASE_URL . '/employee/evaluation-template-archive.php'
    : BASE_URL . '/manager/template-archive.php';
$template_edit_url = !empty($creator_context['is_ceo'])
    ? BASE_URL . '/employee/edit-evaluation-template.php'
    : BASE_URL . '/manager/edit-template.php';
$template_scope_sql = '1 = 1';
$owned_template_scope_sql = 'et.created_by = ' . (int)($_SESSION['user_id'] ?? 0);

// Handle restore (reactivate)
if (isset($_GET['activate']) && is_numeric($_GET['activate'])) {
    $tid = (int)$_GET['activate'];
    if (!isEvaluationTemplateOwner($conn, $tid, (int)($_SESSION['user_id'] ?? 0))) {
        redirectWith($template_archive_url, 'danger', 'Only the template creator can restore this template.');
    }
    $conn->query("UPDATE evaluation_templates et SET status = 'Active' WHERE et.template_id = $tid AND $owned_template_scope_sql");
    logAudit($conn, $_SESSION['user_id'], 'UPDATE', 'Template', $tid, 'Restored archived template to Active');
    redirectWith($template_archive_url, 'success', 'Template restored to active successfully.');
}

// Handle permanent delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $tid = (int)$_GET['delete'];
    if (!isEvaluationTemplateOwner($conn, $tid, (int)($_SESSION['user_id'] ?? 0))) {
        redirectWith($template_archive_url, 'danger', 'Only the template creator can delete this template.');
    }
    // Delete related evaluation scores that reference this template's criteria
    $conn->query("DELETE es FROM evaluation_scores es INNER JOIN evaluation_criteria ec ON es.criterion_id = ec.criterion_id INNER JOIN evaluation_templates et ON et.template_id = ec.template_id WHERE ec.template_id = $tid AND $owned_template_scope_sql");
    // Delete evaluations using this template
    $conn->query("DELETE ev FROM evaluations ev INNER JOIN evaluation_templates et ON et.template_id = ev.template_id WHERE ev.template_id = $tid AND $owned_template_scope_sql");
    // Delete criteria
    $conn->query("DELETE ec FROM evaluation_criteria ec INNER JOIN evaluation_templates et ON et.template_id = ec.template_id WHERE ec.template_id = $tid AND $owned_template_scope_sql");
    // Delete the template
    $conn->query("DELETE et FROM evaluation_templates et WHERE et.template_id = $tid AND $owned_template_scope_sql");
    logAudit($conn, $_SESSION['user_id'], 'DELETE', 'Template', $tid, 'Permanently deleted archived template');
    redirectWith($template_archive_url, 'success', 'Template deleted permanently.');
}

require_once '../includes/header.php';

// Fetch only archived templates with criteria count
$templates = $conn->query("SELECT et.*, u.full_name as created_by_name, u.role AS created_by_role,
    (SELECT COUNT(*) FROM evaluation_criteria WHERE template_id = et.template_id) as criteria_count,
    (SELECT SUM(weight) FROM evaluation_criteria WHERE template_id = et.template_id) as total_weight
    FROM evaluation_templates et
    LEFT JOIN users u ON et.created_by = u.user_id
    WHERE et.status = 'Archived' AND $template_scope_sql
    ORDER BY et.updated_at DESC");
$archive_count = $templates->num_rows;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <p class="text-muted mb-0"><i class="fas fa-archive me-1"></i> Review and manage archived evaluation templates</p>
    </div>
    <a href="<?php echo $template_list_url; ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to Active Templates
    </a>
</div>

<!-- Archive Stats Banner -->
<div class="content-card mb-4" style="background: linear-gradient(135deg, #2c3e50 0%, #3d5066 100%); border: none;">
    <div class="card-body py-4">
        <div class="row align-items-center">
            <div class="col-auto">
                <div class="archive-stats-icon">
                    <i class="fas fa-archive"></i>
                </div>
            </div>
            <div class="col">
                <h5 class="text-white mb-1 fw-bold">Template Archive</h5>
                <p class="mb-0" style="color: rgba(255,255,255,0.7); font-size: 0.9rem;">
                    <?php echo $archive_count; ?> archived template<?php echo $archive_count !== 1 ? 's' : ''; ?>
                    — Templates here are inactive and won't appear in evaluations
                </p>
            </div>
        </div>
    </div>
</div>

<?php if ($archive_count === 0): ?>
    <div class="content-card">
        <div class="card-body text-center py-5">
            <div class="archive-empty-icon mb-3">
                <i class="fas fa-box-open"></i>
            </div>
            <h5 class="text-muted mb-2">Archive is Empty</h5>
            <p class="text-muted small mb-4">No templates have been archived yet. Active templates can be archived from the Templates page.</p>
            <a href="<?php echo $template_list_url; ?>" class="btn btn-outline-primary">
                <i class="fas fa-file-alt me-2"></i>View Active Templates
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php while ($t = $templates->fetch_assoc()):
            $tw = (float)($t['total_weight'] ?? 0);
            $wclass = abs($tw - 100) < 0.01 ? 'bg-success-subtle text-success border-success-subtle' : 'bg-warning-subtle text-warning border-warning-subtle';
            $archived_date = date('M d, Y', strtotime($t['updated_at']));
                    $is_template_owner = (int)($t['created_by'] ?? 0) === (int)($_SESSION['user_id'] ?? 0);
                ?>
            <div class="col-md-6 col-lg-4">
                <div class="content-card h-100 archive-card">
                    <div class="card-body p-4">
                        <!-- Status Ribbon -->
                        <div class="archive-ribbon">
                            <i class="fas fa-archive me-1"></i>Archived
                        </div>

                        <div class="d-flex justify-content-between align-items-start mb-3 mt-2">
                            <div class="archive-template-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <span class="badge bg-info-subtle text-info border border-info-subtle px-2">
                                <?php echo e($t['target_department'] ?: 'All Departments'); ?>
                            </span>
                        </div>

                        <h6 class="fw-bold text-dark mb-2"><?php echo e($t['template_name']); ?></h6>
                        <p class="text-muted small mb-3" style="line-height: 1.5;">
                            <?php echo e(substr($t['description'] ?? 'No description provided.', 0, 100)); ?><?php echo strlen($t['description'] ?? '') > 100 ? '...' : ''; ?>
                        </p>

                        <div class="d-flex gap-2 mb-3 flex-wrap">
                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2">
                                <i class="fas fa-list-ul me-1"></i><?php echo $t['criteria_count']; ?> criteria
                            </span>
                            <span class="badge <?php echo $wclass; ?> border px-2">
                                <i class="fas fa-balance-scale me-1"></i><?php echo $tw; ?>%
                            </span>
                        </div>

                        <div class="d-flex justify-content-between align-items-center text-muted small">
                            <?php if (!empty($t['created_by_name'])): ?>
                                <span><i class="fas fa-user-edit me-1 opacity-50"></i><?php echo e($t['created_by_name']); ?><?php if (!empty($t['created_by_role'])): ?> · <?php echo e($t['created_by_role']); ?><?php endif; ?></span>
                            <?php else: ?>
                                <span></span>
                            <?php endif; ?>
                            <span><i class="fas fa-calendar-alt me-1 opacity-50"></i><?php echo $archived_date; ?></span>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-top p-3">
                        <div class="d-flex gap-2">
                            <?php
                            $view_template_path = !empty($creator_context['is_ceo'])
                                ? '/employee/view-evaluation-template.php?id='
                                : (($creator_context['role'] ?? '') === 'HR Staff'
                                    ? '/staff/view-template.php?id='
                                    : '/manager/view-template.php?id=');
                            ?>
                            <a href="<?php echo BASE_URL . $view_template_path . (int)$t['template_id']; ?>" class="btn btn-outline-primary flex-fill">
                                <i class="fas fa-eye me-1"></i>View
                            </a>
                            <?php if ($is_template_owner): ?>
                            <button type="button" class="btn btn-outline-success flex-fill" title="Restore to Active"
                                    onclick="setRestoreTarget(<?php echo $t['template_id']; ?>, '<?php echo e(addslashes($t['template_name'])); ?>')"
                                    data-bs-toggle="modal" data-bs-target="#restoreModal">
                                <i class="fas fa-undo me-1"></i>Restore
                            </button>
                            <a href="<?php echo $template_edit_url; ?>?id=<?php echo (int)$t['template_id']; ?>" class="btn btn-outline-primary" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button type="button" class="btn btn-outline-danger" title="Delete Permanently"
                                    onclick="setDeleteTarget(<?php echo $t['template_id']; ?>, '<?php echo e(addslashes($t['template_name'])); ?>')"
                                    data-bs-toggle="modal" data-bs-target="#deleteModal">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php else: ?>
                            <span class="badge bg-light text-muted border align-self-center"><i class="fas fa-lock me-1"></i>Read only</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>

<!-- Restore Confirmation Modal -->
<div class="modal fade" id="restoreModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-undo me-2"></i>Restore Template</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div class="mb-3">
                    <div style="width: 60px; height: 60px; border-radius: 50%; background: #e8f5e9; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="fas fa-check-circle fa-2x text-success"></i>
                    </div>
                </div>
                <p>Restore <strong id="restoreTemplateName"></strong> to active status?</p>
                <p class="text-muted small">It will be available for evaluations again.</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="restoreConfirmBtn" class="btn btn-success"><i class="fas fa-undo me-1"></i>Restore</a>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Delete Template</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div class="mb-3">
                    <div style="width: 60px; height: 60px; border-radius: 50%; background: #ffebee; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
                    </div>
                </div>
                <p>Permanently delete <strong id="deleteTemplateName"></strong>?</p>
                <p class="text-danger small"><i class="fas fa-exclamation-circle me-1"></i>This will also remove all evaluations using this template. This cannot be undone!</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="deleteTemplateBtn" class="btn btn-danger"><i class="fas fa-trash me-1"></i>Delete Permanently</a>
            </div>
        </div>
    </div>
</div>

<script>
function setRestoreTarget(id, name) {
    document.getElementById('restoreTemplateName').textContent = name;
    document.getElementById('restoreConfirmBtn').href = '?activate=' + id;
}
function setDeleteTarget(id, name) {
    document.getElementById('deleteTemplateName').textContent = name;
    document.getElementById('deleteTemplateBtn').href = '?delete=' + id;
}
</script>

<style>
.archive-card { 
    transition: transform 0.2s ease, box-shadow 0.2s ease; 
    border: 1.5px solid #e8e8e8;
    position: relative;
    overflow: hidden;
}
.archive-card:hover { 
    transform: translateY(-4px); 
    box-shadow: 0 8px 25px rgba(0,0,0,0.08); 
}
.archive-ribbon {
    position: absolute;
    top: 12px;
    right: -2px;
    background: linear-gradient(135deg, #78909c, #607d8b);
    color: white;
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 3px 12px 3px 10px;
    border-radius: 4px 0 0 4px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}
.archive-template-icon { 
    width: 44px; height: 44px; border-radius: 12px; 
    background: linear-gradient(135deg, #eceff1, #cfd8dc); 
    display: flex; align-items: center; justify-content: center; 
    color: #607d8b; font-size: 1.1rem; 
}
.archive-stats-icon {
    width: 56px; height: 56px; border-radius: 14px;
    background: rgba(255,255,255,0.15);
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 1.4rem;
    backdrop-filter: blur(4px);
}
.archive-empty-icon {
    width: 80px; height: 80px; border-radius: 50%;
    background: linear-gradient(135deg, #f5f5f5, #eeeeee);
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 2rem; color: #bdbdbd;
}
.bg-info-subtle { background-color: #e0f7fa; }
.bg-secondary-subtle { background-color: #f5f5f5; }
.bg-success-subtle { background-color: #e8f5e9; }
.bg-warning-subtle { background-color: #fff9c4; }
.badge { border-radius: 6px; font-weight: 600; font-size: 0.7rem; }
</style>
