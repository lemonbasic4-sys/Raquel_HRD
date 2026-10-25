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
$is_employee_template_portal = basename(dirname($_SERVER['SCRIPT_NAME'])) === 'employee';
if (!canViewEvaluationTemplates($creator_context)) {
    redirectWith(BASE_URL . '/employee/dashboard.php', 'danger', 'Your account is not authorized to view archived templates.');
}
if (!empty($creator_context['is_ceo']) && basename(dirname($_SERVER['SCRIPT_NAME'])) !== 'employee') {
    redirectWith(BASE_URL . '/employee/evaluation-template-archive.php', 'info', 'President and CEO template archives are available in the Employee Portal.');
}
$template_list_url = (!empty($creator_context['is_ceo']) || $is_employee_template_portal)
    ? BASE_URL . '/employee/evaluation-templates.php'
    : ((($_SESSION['role'] ?? '') === 'HR Supervisor')
        ? BASE_URL . '/supervisor/templates.php'
        : BASE_URL . '/manager/templates.php');
$template_archive_url = (!empty($creator_context['is_ceo']) || $is_employee_template_portal)
    ? BASE_URL . '/employee/evaluation-template-archive.php'
    : BASE_URL . '/manager/template-archive.php';
$template_edit_url = (!empty($creator_context['is_ceo']) || $is_employee_template_portal)
    ? BASE_URL . '/employee/edit-evaluation-template.php'
    : BASE_URL . '/manager/edit-template.php';
$current_user_id = (int)($_SESSION['user_id'] ?? 0);
$owned_template_scope_sql = 'et.created_by = ' . $current_user_id;
$viewer_role = (string)($creator_context['role'] ?? '');
$viewer_department_id = (int)($creator_context['department_id'] ?? 0);
$viewer_rank = (int)($creator_context['rank_category_id'] ?? 0);
$viewer_employee_id = (int)($creator_context['employee_id'] ?? 0);
if (!empty($creator_context['is_ceo']) && $viewer_employee_id > 0) {
    // The CEO's HRIS and Employee Portal accounts belong to one employee.
    $owned_template_scope_sql = "et.created_by IN (
        SELECT owner_account.user_id FROM users owner_account
        WHERE owner_account.employee_id = $viewer_employee_id
          AND owner_account.role IN ('President and CEO', 'Employee')
          AND owner_account.deleted_at IS NULL
    )";
}
$same_rank_scope_sql = '1 = 0';
if (in_array($viewer_role, ['Employee','HR Manager','HR Supervisor'], true)
    && in_array($viewer_rank, [3,4], true) && $viewer_department_id > 0 && $viewer_employee_id > 0) {
    $same_rank_scope_sql = "et.created_by IN (
        SELECT peer_user.user_id FROM users peer_user
        JOIN employees peer_employee ON peer_employee.employee_id=peer_user.employee_id
        WHERE peer_employee.department_id=$viewer_department_id
          AND peer_employee.rank_category_id=$viewer_rank
          AND peer_employee.employee_id<>$viewer_employee_id
          AND peer_employee.is_active=1 AND peer_employee.deleted_at IS NULL
          AND peer_user.role IN ('Employee','HR Manager','HR Supervisor')
          AND peer_user.is_active=1 AND COALESCE(peer_user.account_hold,0)=0
          AND peer_user.deleted_at IS NULL
    )";
}
$template_scope_sql = !empty($creator_context['is_ceo'])
    ? $owned_template_scope_sql
    : (in_array($viewer_role, ['Employee','HR Manager','HR Supervisor'], true)
        ? "($owned_template_scope_sql OR $same_rank_scope_sql)" : '1 = 1');
// Handle restore (reactivate)
if (isset($_GET['activate']) && is_numeric($_GET['activate'])) {
    $tid = (int)$_GET['activate'];
    if (!canManageEvaluationTemplate($conn, $tid, $current_user_id)) {
        redirectWith($template_archive_url, 'danger', 'You can only restore templates you own.');
    }
    $conn->query("UPDATE evaluation_templates et SET status = 'Active' WHERE et.template_id = $tid AND $template_scope_sql");
    if ($conn->affected_rows > 0) notifyHrStaffOfTemplateChange($conn, $tid, 'Restored');
    logAudit($conn, $_SESSION['user_id'], 'UPDATE', 'Template', $tid, 'Restored archived template to Active');
    redirectWith($template_archive_url, 'success', 'Template restored to active successfully.');
}

// Handle permanent delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $tid = (int)$_GET['delete'];
    if (!canManageEvaluationTemplate($conn, $tid, $current_user_id)) {
        redirectWith($template_archive_url, 'danger', 'You can only delete templates you own.');
    }
    // Delete related evaluation scores that reference this template's criteria
    $conn->query("DELETE es FROM evaluation_scores es INNER JOIN evaluation_criteria ec ON es.criterion_id = ec.criterion_id INNER JOIN evaluation_templates et ON et.template_id = ec.template_id WHERE ec.template_id = $tid AND $template_scope_sql");
    // Delete evaluations using this template
    $conn->query("DELETE ev FROM evaluations ev INNER JOIN evaluation_templates et ON et.template_id = ev.template_id WHERE ev.template_id = $tid AND $template_scope_sql");
    // Delete criteria
    $conn->query("DELETE ec FROM evaluation_criteria ec INNER JOIN evaluation_templates et ON et.template_id = ec.template_id WHERE ec.template_id = $tid AND $template_scope_sql");
    // Delete the template
    $conn->query("DELETE et FROM evaluation_templates et WHERE et.template_id = $tid AND $template_scope_sql");
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

<div class="template-archive-page">
<header class="page-hero fadeup archive-hero">
    <div class="archive-hero-content">
        <div>
            <div class="archive-eyebrow"><?php echo !empty($creator_context['is_ceo']) ? 'President and CEO' : e($viewer_role); ?> · Evaluations</div>
            <h1 class="h4 text-white fw-bold mb-2"><i class="fas fa-archive me-2" aria-hidden="true"></i>Evaluation Template Archive</h1>
            <p class="text-white-50 small mb-0">Review inactive forms and restore the ones you need again.</p>
        </div>
        <a href="<?php echo e($template_list_url); ?>" class="btn btn-outline-light btn-sm archive-back"><i class="fas fa-arrow-left me-2" aria-hidden="true"></i>Active templates</a>
    </div>
    <div class="archive-hero-count" aria-label="<?php echo $archive_count; ?> archived templates">
        <span class="archive-hero-count-icon"><i class="fas fa-archive" aria-hidden="true"></i></span>
        <span><strong><?php echo $archive_count; ?></strong><small>Archived template<?php echo $archive_count===1 ? '' : 's'; ?></small></span>
    </div>
</header>

<div class="content-card archive-toolbar fadeup-1 mb-4">
    <div><h2 class="h6 fw-bold mb-1">Archived forms</h2><p class="small text-muted mb-0">Archived templates are unavailable for new evaluations.</p></div>
    <?php if ($archive_count > 0): ?>
    <label class="archive-search" for="archiveSearch"><i class="fas fa-search" aria-hidden="true"></i><input type="search" id="archiveSearch" placeholder="Find archived template" aria-label="Find archived template"></label>
    <?php endif; ?>
</div>

<?php if ($archive_count === 0): ?>
    <div class="content-card archive-empty fadeup-2">
        <div class="card-body text-center py-5">
            <div class="archive-empty-icon mb-3">
                <i class="fas fa-box-open"></i>
            </div>
            <h3 class="h5 fw-bold mb-2">No archived templates</h3>
            <p class="text-muted small mb-4">Templates you archive will appear here for review or restoration.</p>
            <a href="<?php echo e($template_list_url); ?>" class="btn btn-outline-success">
                <i class="fas fa-file-alt me-2"></i>View Active Templates
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3 archive-grid fadeup-2" id="archiveGrid">
        <?php while ($t = $templates->fetch_assoc()):
            $tw = (float)($t['total_weight'] ?? 0);
            $archived_date = date('M d, Y', strtotime($t['updated_at']));
            $is_template_manager = canManageEvaluationTemplate($conn, (int)$t['template_id'], $current_user_id);
                ?>
            <div class="col-md-6 col-xl-4 archive-grid-item" data-search="<?php echo e(mb_strtolower(($t['template_name'] ?? '').' '.($t['target_department'] ?? '').' '.($t['created_by_name'] ?? ''))); ?>">
                <article class="content-card h-100 archive-card">
                    <div class="card-body">
                        <div class="archive-card-top">
                            <div class="archive-template-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <span class="archive-status"><i class="fas fa-archive me-1" aria-hidden="true"></i>Archived</span>
                        </div>

                        <h3 class="archive-card-title"><?php echo e($t['template_name']); ?></h3>
                        <p class="archive-card-description">
                            <?php echo e(substr($t['description'] ?? 'No description provided.', 0, 100)); ?><?php echo strlen($t['description'] ?? '') > 100 ? '...' : ''; ?>
                        </p>

                        <div class="archive-card-tags">
                            <span><i class="fas fa-building" aria-hidden="true"></i><?php echo e($t['target_department'] ?: 'All Departments'); ?></span>
                            <span><i class="fas fa-list-ul" aria-hidden="true"></i><?php echo (int)$t['criteria_count']; ?> criteria</span>
                            <span><i class="fas fa-balance-scale" aria-hidden="true"></i><?php echo number_format($tw, 0); ?>% weight</span>
                        </div>

                        <div class="archive-card-meta">
                            <?php if (!empty($t['created_by_name'])): ?>
                                <span><i class="fas fa-user-edit" aria-hidden="true"></i><?php echo e($t['created_by_name']); ?></span>
                            <?php endif; ?>
                            <span><i class="far fa-calendar-alt" aria-hidden="true"></i><?php echo e($archived_date); ?></span>
                        </div>
                    </div>
                    <div class="archive-card-actions">
                        <div class="archive-action-row">
                            <?php
                            $view_template_path = $is_employee_template_portal
                                ? '/employee/view-evaluation-template.php?id='
                                : (($creator_context['role'] ?? '') === 'HR Staff'
                                    ? '/staff/view-template.php?id='
                                    : '/manager/view-template.php?id=');
                            ?>
                            <a href="<?php echo e(BASE_URL . $view_template_path . (int)$t['template_id']); ?>" class="btn btn-outline-success archive-main-action">
                                <i class="fas fa-eye me-1"></i>View template
                            </a>
                            <?php if ($is_template_manager): ?>
                            <button type="button" class="btn btn-success archive-main-action archive-restore" title="Restore to Active"
                                    data-template-id="<?php echo (int)$t['template_id']; ?>" data-template-name="<?php echo e($t['template_name']); ?>"
                                    data-bs-toggle="modal" data-bs-target="#restoreModal">
                                <i class="fas fa-undo me-1"></i>Restore
                            </button>
                            <a href="<?php echo e($template_edit_url); ?>?id=<?php echo (int)$t['template_id']; ?>" class="btn btn-outline-secondary archive-icon-action" title="Edit template" aria-label="Edit <?php echo e($t['template_name']); ?>">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button type="button" class="btn btn-outline-danger archive-icon-action archive-delete" title="Delete permanently" aria-label="Delete <?php echo e($t['template_name']); ?> permanently"
                                    data-template-id="<?php echo (int)$t['template_id']; ?>" data-template-name="<?php echo e($t['template_name']); ?>"
                                    data-bs-toggle="modal" data-bs-target="#deleteModal">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php else: ?>
                            <span class="archive-readonly"><i class="fas fa-lock me-1"></i>Read only</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            </div>
        <?php endwhile; ?>
    </div>
    <div id="archiveSearchEmpty" class="content-card archive-search-empty d-none text-center p-4 mt-3" role="status">No archived templates match your search.</div>
<?php endif; ?>

</div>

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
document.querySelectorAll('.archive-restore').forEach(button => button.addEventListener('click', () => {
    document.getElementById('restoreTemplateName').textContent = button.dataset.templateName;
    document.getElementById('restoreConfirmBtn').href = '?activate=' + encodeURIComponent(button.dataset.templateId);
}));
document.querySelectorAll('.archive-delete').forEach(button => button.addEventListener('click', () => {
    document.getElementById('deleteTemplateName').textContent = button.dataset.templateName;
    document.getElementById('deleteTemplateBtn').href = '?delete=' + encodeURIComponent(button.dataset.templateId);
}));
const archiveSearch = document.getElementById('archiveSearch');
archiveSearch?.addEventListener('input', () => {
    const query = archiveSearch.value.trim().toLocaleLowerCase();
    let shown = 0;
    document.querySelectorAll('.archive-grid-item').forEach(item => {
        const matches = (item.dataset.search || '').includes(query);
        item.classList.toggle('d-none', !matches);
        if (matches) shown++;
    });
    document.getElementById('archiveSearchEmpty')?.classList.toggle('d-none', shown > 0);
});
</script>

<style>
.template-archive-page .archive-hero { padding: 24px 28px; }
.archive-hero-content { position: relative; display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; flex-wrap: wrap; }
.archive-eyebrow { color: rgba(255,255,255,.62); font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; margin-bottom: 5px; }
.archive-back { white-space: nowrap; }
.archive-hero-count { position: relative; display: inline-flex; align-items: center; gap: 13px; min-width: 190px; margin-top: 22px; padding: 12px 16px; border: 1px solid rgba(255,255,255,.23); border-radius: 13px; background: rgba(255,255,255,.12); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); color: #fff; }
.archive-hero-count-icon { display: grid; place-items: center; width: 42px; height: 42px; border-radius: 11px; background: rgba(255,255,255,.13); color: #f4d984; }
.archive-hero-count strong { display: block; font-size: 1.6rem; line-height: 1; }
.archive-hero-count small { display: block; margin-top: 4px; color: rgba(255,255,255,.8); font-weight: 600; }
.archive-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; padding: 16px 20px; }
.archive-search { display: flex; align-items: center; gap: 9px; min-width: min(100%,260px); padding: 8px 12px; border: 1px solid #d6e0d1; border-radius: 10px; background: #f8faf7; color: #62735d; }
.archive-search:focus-within { border-color: #426e30; box-shadow: 0 0 0 3px rgba(66,110,48,.12); }
.archive-search input { flex: 1; min-width: 0; border: 0; outline: 0; background: transparent; color: #233b23; font-size: .88rem; }
.archive-card { display: flex; flex-direction: column; min-width: 0; border: 1px solid #e0e8dc; border-radius: 15px; overflow: hidden; box-shadow: 0 5px 20px rgba(22,55,24,.06); transition: transform .18s ease, box-shadow .18s ease; }
.archive-card:hover { transform: translateY(-3px); box-shadow: 0 10px 26px rgba(22,55,24,.11); }
.archive-card .card-body { flex: 1; padding: 20px; }
.archive-card-top { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 16px; }
.archive-template-icon { display: grid; place-items: center; width: 43px; height: 43px; flex: 0 0 43px; border-radius: 11px; background: #eaf2e7; color: #28572b; font-size: 1.1rem; }
.archive-status { border: 1px solid #d8e2d4; border-radius: 999px; padding: 5px 10px; background: #f1f5ef; color: #53684f; font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .035em; }
.archive-card-title { color: #1b361d; font-size: 1.03rem; font-weight: 750; line-height: 1.35; margin: 0 0 8px; overflow-wrap: anywhere; }
.archive-card-description { min-height: 42px; color: #657166; font-size: .82rem; line-height: 1.55; margin: 0 0 15px; overflow-wrap: anywhere; }
.archive-card-tags { display: flex; flex-wrap: wrap; gap: 7px; margin-bottom: 16px; }
.archive-card-tags span { display: inline-flex; align-items: center; gap: 6px; max-width: 100%; border-radius: 7px; padding: 5px 8px; background: #f2f6ef; color: #4e634d; font-size: .71rem; font-weight: 650; overflow-wrap: anywhere; }
.archive-card-tags i { color: #638b54; }
.archive-card-meta { display: flex; justify-content: space-between; gap: 8px 14px; flex-wrap: wrap; padding-top: 12px; border-top: 1px solid #edf1eb; color: #718071; font-size: .72rem; }
.archive-card-meta span { display: inline-flex; align-items: center; gap: 6px; min-width: 0; overflow-wrap: anywhere; }
.archive-card-actions { padding: 12px 16px; border-top: 1px solid #e7ede4; background: #fafcf9; }
.archive-action-row { display: flex; flex-wrap: wrap; gap: 7px; }
.archive-main-action { flex: 1 1 120px; white-space: nowrap; font-size: .77rem; font-weight: 700; }
.archive-icon-action { display: inline-grid; place-items: center; width: 36px; min-width: 36px; padding: 0; }
.archive-readonly { display: inline-flex; align-items: center; color: #6b7769; font-size: .73rem; }
.archive-empty-icon { display: inline-grid; place-items: center; width: 68px; height: 68px; margin-bottom: 8px; border-radius: 16px; background: #eef4ea; color: #618554; font-size: 1.6rem; }
@media (max-width: 575.98px) { .template-archive-page .archive-hero { padding: 21px; } .archive-back, .archive-search { width: 100%; } .archive-hero-count { width: 100%; } }
</style>
<?php require_once '../includes/footer.php'; ?>
