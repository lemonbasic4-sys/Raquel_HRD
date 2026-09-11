<?php
$page_title = 'Evaluation Templates';
require_once '../includes/session-check.php';
checkRole(['HR Manager']);
require_once '../includes/functions.php';

// Handle archive action
if (isset($_GET['archive']) && is_numeric($_GET['archive'])) {
    $tid = (int)$_GET['archive'];
    $conn->query("UPDATE evaluation_templates SET status = 'Archived' WHERE template_id = $tid");
    logAudit($conn, $_SESSION['user_id'], 'UPDATE', 'Template', $tid, 'Archived evaluation template');
    redirectWith(BASE_URL . '/manager/templates.php', 'success', 'Template archived successfully.');
}

// Handle independent delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $tid = (int)$_GET['delete'];
    $usage = $conn->query("SELECT COUNT(*) as cnt FROM evaluations WHERE template_id = $tid")->fetch_assoc()['cnt'];
    if ($usage > 0) {
        redirectWith(BASE_URL . '/manager/templates.php', 'danger', "Cannot delete template. It is being used in $usage evaluation(s).");
    } else {
        $conn->query("DELETE FROM evaluation_criteria WHERE template_id = $tid");
        $conn->query("DELETE FROM evaluation_templates WHERE template_id = $tid");
        logAudit($conn, $_SESSION['user_id'], 'DELETE', 'Template', $tid, 'Deleted evaluation template');
        redirectWith(BASE_URL . '/manager/templates.php', 'success', 'Template deleted successfully.');
    }
}

// Handle batch delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'batch_delete' && isset($_POST['template_ids'])) {
    $ids = $_POST['template_ids'];
    $success = 0;
    $failed = 0;
    if (is_array($ids)) {
        foreach ($ids as $id) {
            $tid = (int)$id;
            $usage = $conn->query("SELECT COUNT(*) as cnt FROM evaluations WHERE template_id = $tid")->fetch_assoc()['cnt'];
            if ($usage > 0) {
                $failed++;
            } else {
                $conn->query("DELETE FROM evaluation_criteria WHERE template_id = $tid");
                $conn->query("DELETE FROM evaluation_templates WHERE template_id = $tid");
                logAudit($conn, $_SESSION['user_id'], 'DELETE', 'Template', $tid, 'Deleted evaluation template via batch');
                $success++;
            }
        }
    }
    $msg = "$success template(s) deleted successfully.";
    if ($failed > 0) $msg .= " $failed template(s) could not be deleted because they are in use.";
    redirectWith(BASE_URL . '/manager/templates.php', $failed > 0 ? 'warning' : 'success', $msg);
}

// Handle broadcast notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'broadcast_notification') {
    $tid = (int)($_POST['template_id'] ?? 0);
    $custom_title = trim($_POST['notification_title'] ?? '');
    $custom_message = trim($_POST['notification_message'] ?? '');
    
    // Fetch template details
    $stmt = $conn->prepare("SELECT template_name, target_department, evaluation_type FROM evaluation_templates WHERE template_id = ? AND status = 'Active'");
    $stmt->bind_param("i", $tid);
    $stmt->execute();
    $template = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($template) {
        $target_dept = $template['target_department'];
        $eval_type = $template['evaluation_type'];
        $temp_name = $template['template_name'];
        
        $dept_cond = "";
        if (!empty($target_dept) && $target_dept !== 'All Departments') {
            $dept_stmt = $conn->prepare("SELECT department_id FROM departments WHERE department_name = ? AND deleted_at IS NULL");
            $dept_stmt->bind_param("s", $target_dept);
            $dept_stmt->execute();
            $dept = $dept_stmt->get_result()->fetch_assoc();
            $dept_stmt->close();
            if ($dept) {
                $dept_id = (int)$dept['department_id'];
                $dept_cond = " AND e.department_id = $dept_id";
            }
        }
        
        // Find all active employees who have a portal user account with role='Employee'
        $query = "
            SELECT e.employee_id, e.first_name, e.last_name, u.user_id
            FROM employees e
            INNER JOIN users u ON e.employee_id = u.employee_id
            WHERE e.is_active = 1 
              AND e.deleted_at IS NULL 
              AND u.role = 'Employee'
              AND u.is_active = 1
              $dept_cond
        ";
        $employees_res = $conn->query($query);
        
        $notified_count = 0;
        if ($employees_res) {
            while ($emp = $employees_res->fetch_assoc()) {
                $uid = (int)$emp['user_id'];
                $first_name = $emp['first_name'];
                $last_name = $emp['last_name'];
                $full_name = $first_name . " " . $last_name;
                
                // Replace placeholders in Title and Message
                // Only replace {name}
                $title = str_replace('{name}', $full_name, $custom_title);
                $message = str_replace('{name}', $full_name, $custom_message);
                
                $link = BASE_URL . "/employee/self-rating.php";
                
                createNotification($conn, $uid, $title, $message, $link);
                $notified_count++;
            }
        }
        
        logAudit($conn, $_SESSION['user_id'], 'CREATE', 'Notification', $tid, "Broadcasted custom notifications for template: $temp_name to $notified_count employee(s)");
        
        redirectWith(BASE_URL . '/manager/templates.php', 'success', "Notifications successfully broadcasted to $notified_count employee(s) in " . htmlspecialchars($target_dept) . ".");
    } else {
        redirectWith(BASE_URL . '/manager/templates.php', 'danger', "Template not found or inactive.");
    }
}

require_once '../includes/header.php';

$selected_department = trim($_GET['department'] ?? '');
if (strlen($selected_department) > 100) {
    $selected_department = substr($selected_department, 0, 100);
}

$department_options = $conn->query("
    SELECT department_name
    FROM departments
    WHERE deleted_at IS NULL AND is_active = 1
    ORDER BY department_name
");

$template_where = "WHERE et.status = 'Active'";
if ($selected_department !== '') {
    $safe_department = $conn->real_escape_string($selected_department);
    $template_where .= " AND (et.target_department = '$safe_department' OR et.target_department = 'All Departments')";
}

// Fetch active templates with criteria counts
$templates = $conn->query("SELECT et.*, u.full_name as created_by_name,
    (SELECT COUNT(*) FROM evaluation_criteria WHERE template_id = et.template_id AND section='KRA') as kra_count,
    (SELECT COUNT(*) FROM evaluation_criteria WHERE template_id = et.template_id AND section='Behavior') as behavior_count,
    (SELECT SUM(weight) FROM evaluation_criteria WHERE template_id = et.template_id AND section='KRA') as kra_total_weight,
    (SELECT COUNT(*) FROM evaluations WHERE template_id = et.template_id AND deleted_at IS NULL) as usage_count
    FROM evaluation_templates et
    LEFT JOIN users u ON et.created_by = u.user_id
    $template_where
    ORDER BY et.updated_at DESC");
$filtered_template_count = $templates->num_rows;
$active_template_count = (int) $conn->query("SELECT COUNT(*) as cnt FROM evaluation_templates WHERE status = 'Active'")->fetch_assoc()['cnt'];
$archived_template_count = (int) $conn->query("SELECT COUNT(*) as cnt FROM evaluation_templates WHERE status = 'Archived'")->fetch_assoc()['cnt'];
$used_template_count = (int) $conn->query("SELECT COUNT(DISTINCT template_id) as cnt FROM evaluations WHERE template_id IS NOT NULL AND deleted_at IS NULL")->fetch_assoc()['cnt'];
?>

<div class="page-hero fadeup">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
        <div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">HR Manager · Evaluations</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-file-alt me-2" style="color:#BD9414;"></i>Evaluation Templates</h4>
            <p class="text-white-50 small mb-0 mt-2">Create and maintain standardized evaluation templates for fair, consistent employee performance reviews.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-outline-danger d-none shadow-sm" id="batchDeleteBtn" onclick="confirmBatchDelete()">
            <i class="fas fa-trash-alt me-1"></i>Batch Delete (<span id="deleteCount">0</span>)
        </button>
        <a href="<?php echo BASE_URL; ?>/manager/template-archive.php" class="btn btn-outline-light btn-sm">
            <i class="fas fa-archive me-1"></i>Archive
        </a>
        <a href="<?php echo BASE_URL; ?>/manager/create-template.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-1"></i>Create Template
        </a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo $active_template_count; ?></div>
                        <div class="stat-label">Active Templates</div>
                    </div>
                    <i class="fas fa-file-alt stat-icon text-white-50"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo $archived_template_count; ?></div>
                        <div class="stat-label">Archived</div>
                    </div>
                    <i class="fas fa-archive stat-icon" style="color:#BD9414;"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo $used_template_count; ?></div>
                        <div class="stat-label">Used in Evaluations</div>
                    </div>
                    <i class="fas fa-chart-bar stat-icon" style="color:#17a2b8;"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo $filtered_template_count; ?></div>
                        <div class="stat-label">Shown</div>
                    </div>
                    <i class="fas fa-filter stat-icon" style="color:#28a745;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="content-card fadeup-1 mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-8 col-lg-5">
                <label class="form-label">Department</label>
                <select class="form-select" name="department" onchange="this.form.submit()">
                    <option value="">All Departments</option>
                    <?php while ($department = $department_options->fetch_assoc()): ?>
                        <option value="<?php echo e($department['department_name']); ?>" <?php echo $selected_department === $department['department_name'] ? 'selected' : ''; ?>>
                            <?php echo e($department['department_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-4 col-lg-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter me-1"></i>Apply
                </button>
                <a href="<?php echo BASE_URL; ?>/manager/templates.php" class="btn btn-outline-secondary">
                    <i class="fas fa-rotate-left me-1"></i>Reset
                </a>
            </div>
            <div class="col-12">
                <div class="small text-muted">
                    <?php if ($selected_department !== ''): ?>
                        Showing <?php echo number_format($filtered_template_count); ?> template<?php echo $filtered_template_count === 1 ? '' : 's'; ?> for <?php echo e($selected_department); ?>.
                    <?php else: ?>
                        Showing all active department templates.
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if ($templates->num_rows === 0): ?>
    <div class="chart-card fadeup-1">
        <div class="card-body text-center py-5">
            <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#e8f5e9,#c8e6c9);display:inline-flex;align-items:center;justify-content:center;font-size:2rem;color:#388e3c;margin-bottom:16px;">
                <i class="fas fa-file-alt"></i>
            </div>
            <h5 class="text-muted mb-2">No Active Templates Found</h5>
            <p class="text-muted small mb-4">
                <?php echo $selected_department !== '' ? 'No active templates match the selected department.' : 'Create your first evaluation template to get started.'; ?>
            </p>
            <a href="<?php echo BASE_URL; ?>/manager/create-template.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Create Template
            </a>
        </div>
    </div>
<?php else: ?>
    <form method="POST" action="" id="batchDeleteForm">
        <input type="hidden" name="action" value="batch_delete">
        <div class="row g-4 fadeup-1">
            <?php while ($t = $templates->fetch_assoc()):
            $kra_w = (float)($t['kra_total_weight'] ?? 0);
            $wclass = abs($kra_w - 100) < 0.01 ? 'bg-success' : 'bg-warning text-dark';
        ?>
            <div class="col-md-6 col-lg-4">
                <div class="chart-card fadeup h-100 position-relative" style="transition:transform 0.2s,box-shadow 0.2s;cursor:pointer;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 8px 25px rgba(0,0,0,0.08)'" onmouseout="this.style.transform='';this.style.boxShadow=''">
                    <!-- Checkbox for Batch Delete -->
                    <div class="position-absolute" style="top: 15px; right: 15px; z-index: 10;">
                        <input class="template-checkbox" type="checkbox" name="template_ids[]" value="<?php echo $t['template_id']; ?>" aria-label="Select <?php echo e($t['template_name']); ?> for batch deletion" onchange="toggleBatchDeleteBtn()">
                    </div>
                    <div class="card-body p-4 pt-5">
                        <!-- Top -->
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#e8f5e9,#c8e6c9);display:flex;align-items:center;justify-content:center;color:#2e7d32;font-size:1.1rem;">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="d-flex gap-1">
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2" style="font-size:0.65rem;">
                                    <?php echo e($t['evaluation_type'] ?? 'Annual'); ?>
                                </span>
                                <?php if (!empty($t['target_department'])): ?>
                                    <span class="badge bg-success-subtle text-success border px-2" style="font-size:0.65rem;">
                                        <?php echo e($t['target_department']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <h6 class="fw-bold mb-2"><?php echo e($t['template_name']); ?></h6>
                        <p class="text-muted small mb-3" style="line-height:1.5;">
                            <?php echo e(substr($t['description'] ?? 'No description.', 0, 80)); ?><?php echo strlen($t['description'] ?? '') > 80 ? '...' : ''; ?>
                        </p>

                        <!-- Stats -->
                        <div class="d-flex gap-2 mb-3 flex-wrap">
                            <span class="badge bg-success-subtle text-success border px-2" style="font-size:0.7rem;">
                                <i class="fas fa-bullseye me-1"></i><?php echo $t['kra_count']; ?> KRA
                            </span>
                            <span class="badge bg-primary-subtle text-primary border px-2" style="font-size:0.7rem;">
                                <i class="fas fa-heart me-1"></i><?php echo $t['behavior_count']; ?> Behavior
                            </span>
                            <span class="badge <?php echo $wclass; ?> px-2" style="font-size:0.7rem;">
                                <i class="fas fa-balance-scale me-1"></i><?php echo $kra_w; ?>%
                            </span>
                            <span class="badge bg-secondary px-2" style="font-size:0.7rem;">
                                <i class="fas fa-chart-bar me-1"></i><?php echo $t['usage_count']; ?> used
                            </span>
                        </div>

                        <!-- Weight split -->
                        <div class="d-flex gap-1 mb-3" style="height:6px;">
                            <div style="flex:<?php echo $t['kra_weight'] ?? 80; ?>;background:linear-gradient(90deg,#2e7d32,#4caf50);border-radius:3px;" title="KRA <?php echo $t['kra_weight'] ?? 80; ?>%"></div>
                            <div style="flex:<?php echo $t['behavior_weight'] ?? 20; ?>;background:linear-gradient(90deg,#1565c0,#42a5f5);border-radius:3px;" title="Behavior <?php echo $t['behavior_weight'] ?? 20; ?>%"></div>
                        </div>
                        <div class="d-flex justify-content-between" style="font-size:0.65rem;color:#888;">
                            <span>KRA <?php echo $t['kra_weight'] ?? 80; ?>%</span>
                            <span>Behavior <?php echo $t['behavior_weight'] ?? 20; ?>%</span>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-top p-3">
                        <div class="d-flex gap-2">
                            <a href="<?php echo BASE_URL; ?>/manager/edit-template.php?id=<?php echo $t['template_id']; ?>" class="btn btn-sm btn-outline-primary flex-fill">
                                <i class="fas fa-edit me-1"></i>Edit
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-success flex-fill" onclick="setBroadcastTarget(<?php echo $t['template_id']; ?>, '<?php echo e(addslashes($t['template_name'])); ?>', '<?php echo e(addslashes($t['target_department'])); ?>', '<?php echo e(addslashes($t['evaluation_type'] ?? 'Annual')); ?>')" data-bs-toggle="modal" data-bs-target="#broadcastModal" title="Notify Employees">
                                <i class="fas fa-paper-plane me-1"></i>Notify
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-warning" onclick="setArchiveTarget(<?php echo $t['template_id']; ?>, '<?php echo e(addslashes($t['template_name'])); ?>')" data-bs-toggle="modal" data-bs-target="#archiveModal" title="Archive">
                                <i class="fas fa-archive"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="setDeleteTarget(<?php echo $t['template_id']; ?>, '<?php echo e(addslashes($t['template_name'])); ?>')" data-bs-toggle="modal" data-bs-target="#deleteModal" title="Delete">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
    </form>
<?php endif; ?>

<!-- Broadcast Modal -->
<div class="modal fade" id="broadcastModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="" class="w-100">
            <input type="hidden" name="action" value="broadcast_notification">
            <input type="hidden" name="template_id" id="broadcastTemplateId">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
                <div class="modal-header text-white border-0 py-3" style="border-top-left-radius: 16px; border-top-right-radius: 16px; background: linear-gradient(135deg, #2e7d32, #4caf50) !important;">
                    <h5 class="modal-title fw-bold"><i class="fas fa-paper-plane me-2"></i>Customize & Send Notification</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4 px-4">
                    <div class="text-center mb-3">
                        <div style="width:70px;height:70px;border-radius:50%;background:linear-gradient(135deg, #e8f5e9, #c8e6c9);display:inline-flex;align-items:center;justify-content:center;box-shadow: 0 4px 15px rgba(46, 125, 50, 0.15);">
                            <i class="fas fa-paper-plane fa-2x text-success" style="transform: rotate(-10deg);"></i>
                        </div>
                    </div>
                    <h5 class="fw-bold mb-2 text-dark text-center">Broadcast Evaluation Alert</h5>
                    <p class="text-muted small mb-4 text-center">Customize the message before notifying eligible employees about this active evaluation template.</p>
                    
                    <div class="card bg-light border-0 mb-3" style="border-radius: 12px;">
                        <div class="card-body p-3">
                            <div class="mb-2 d-flex justify-content-between align-items-center">
                                <span class="text-muted small fw-bold"><i class="fas fa-file-alt me-1 text-success"></i>Template:</span>
                                <span class="badge bg-success bg-opacity-10 text-success fw-bold px-2 py-1" id="broadcastTemplateName" style="font-size: 0.8rem;"></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted small fw-bold"><i class="fas fa-users me-1 text-success"></i>Target Group:</span>
                                <span class="badge bg-primary bg-opacity-10 text-primary fw-bold px-2 py-1" id="broadcastTargetGroup" style="font-size: 0.8rem;"></span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark"><i class="fas fa-heading me-1 text-success"></i>Notification Title</label>
                        <input type="text" name="notification_title" id="broadcastTitleInput" class="form-control shadow-sm" style="border-radius: 8px; border: 1px solid #ced4da;" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark"><i class="fas fa-comment-alt me-1 text-success"></i>Notification Message</label>
                        <textarea name="notification_message" id="broadcastMessageInput" class="form-control shadow-sm" rows="4" style="border-radius: 8px; border: 1px solid #ced4da; resize: none;" required></textarea>
                    </div>
                </div>
                <div class="modal-footer justify-content-center border-0 pb-4">
                    <button type="button" class="btn btn-outline-secondary px-4 py-2" data-bs-dismiss="modal" style="border-radius: 10px;">Cancel</button>
                    <button type="submit" class="btn btn-success px-4 py-2 text-white shadow-sm" style="border-radius: 10px; background: linear-gradient(135deg, #2e7d32, #4caf50); border: none;">
                        <i class="fas fa-paper-plane me-2"></i>Send Notifications
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Archive Modal -->
<div class="modal fade" id="archiveModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark"><h5 class="modal-title"><i class="fas fa-archive me-2"></i>Archive Template</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body text-center">
                <div class="mb-3"><div style="width:60px;height:60px;border-radius:50%;background:#fff9c4;display:inline-flex;align-items:center;justify-content:center;"><i class="fas fa-archive fa-2x text-warning"></i></div></div>
                <p>Archive <strong id="archiveTemplateName"></strong>?</p>
                <p class="text-muted small">It will no longer appear in available templates.</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="archiveConfirmBtn" class="btn btn-warning"><i class="fas fa-archive me-1"></i>Archive</a>
            </div>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger text-white"><h5 class="modal-title"><i class="fas fa-trash-alt me-2"></i>Delete Template</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body text-center">
                <div class="mb-3"><div style="width:60px;height:60px;border-radius:50%;background:#ffebee;display:inline-flex;align-items:center;justify-content:center;"><i class="fas fa-trash-alt fa-2x text-danger"></i></div></div>
                <p>Are you sure you want to permanently delete <strong id="deleteTemplateName"></strong>?</p>
                <p class="text-danger small mb-0"><i class="fas fa-exclamation-triangle me-1"></i>This action cannot be undone. Templates currently in use by evaluations cannot be deleted.</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="deleteConfirmBtn" class="btn btn-danger"><i class="fas fa-trash-alt me-1"></i>Yes, Delete</a>
            </div>
        </div>
    </div>
</div>

<script>
function setBroadcastTarget(id, name, targetDept, evalType) {
    document.getElementById('broadcastTemplateId').value = id;
    document.getElementById('broadcastTemplateName').textContent = name;
    document.getElementById('broadcastTargetGroup').textContent = targetDept || 'All Departments';
    
    // Set default customizable title & message values
    const defaultTitle = "Evaluation Ready: " + name;
    const defaultMessage = "Hello! {name} the evaluation for " + name + " is ready! you may proceed 360 degree evaluation before it expires!";
    
    document.getElementById('broadcastTitleInput').value = defaultTitle;
    document.getElementById('broadcastMessageInput').value = defaultMessage;
}

function setArchiveTarget(id, name) {
    document.getElementById('archiveTemplateName').textContent = name;
    document.getElementById('archiveConfirmBtn').href = '?archive=' + id;
}

function setDeleteTarget(id, name) {
    document.getElementById('deleteTemplateName').textContent = name;
    document.getElementById('deleteConfirmBtn').href = '?delete=' + id;
}

function toggleBatchDeleteBtn() {
    const checkboxes = document.querySelectorAll('.template-checkbox:checked');
    const deleteBtn = document.getElementById('batchDeleteBtn');
    const deleteCount = document.getElementById('deleteCount');
    if (checkboxes.length > 0) {
        deleteBtn.classList.remove('d-none');
        deleteCount.textContent = checkboxes.length;
    } else {
        deleteBtn.classList.add('d-none');
    }
}

function confirmBatchDelete() {
    if (confirm("Are you sure you want to delete all selected templates? Templates currently in use by evaluations will be safely skipped.")) {
        document.getElementById('batchDeleteForm').submit();
    }
}
</script>

<style>
.bg-primary-subtle { background-color: #e3f2fd; }
.bg-success-subtle { background-color: #e8f5e9; }
.bg-info-subtle { background-color: #e0f7fa; }
.template-checkbox {
    appearance: none;
    -webkit-appearance: none;
    background: #fff;
    border: 2px solid #8a98a8;
    border-radius: 5px;
    cursor: pointer;
    display: block;
    height: 20px !important;
    margin: 0;
    min-height: 20px !important;
    min-width: 20px;
    position: relative;
    width: 20px !important;
}
.template-checkbox:hover { border-color: #294306; }
.template-checkbox:checked { background: #294306; border-color: #294306; }
.template-checkbox:checked::after {
    border: solid #fff;
    border-width: 0 2px 2px 0;
    content: '';
    height: 10px;
    left: 6px;
    position: absolute;
    top: 2px;
    transform: rotate(45deg);
    width: 5px;
}
.template-checkbox:focus-visible {
    outline: 3px solid rgba(189, 148, 20, .4);
    outline-offset: 2px;
}
</style>

<?php require_once '../includes/footer.php'; ?>
