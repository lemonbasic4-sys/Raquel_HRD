<?php
$page_title = 'Department Management';
require_once '../includes/session-check.php';
checkRole(['HR Supervisor']);
require_once '../includes/functions.php';

// ─── Handle ADD ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrfToken();

    if ($_POST['action'] === 'add') {
        $name = trim($_POST['department_name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($name === '') {
            redirectWith(BASE_URL . '/supervisor/departments.php', 'danger', 'Department name is required.');
        }

        $stmt = $conn->prepare("INSERT INTO departments (department_name, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $description);
        if ($stmt->execute()) {
            $new_id = $stmt->insert_id;
            logAudit($conn, $_SESSION['user_id'], 'CREATE', 'Department', $new_id, "Added department: $name");
            redirectWith(BASE_URL . '/supervisor/departments.php', 'success', "Department \"$name\" added successfully.");
        } else {
            redirectWith(BASE_URL . '/supervisor/departments.php', 'danger', 'Error adding department: ' . $conn->error);
        }
        $stmt->close();
    }

    // ─── Handle EDIT ─────────────────────────────────────
    if ($_POST['action'] === 'edit') {
        $id = (int) ($_POST['department_id'] ?? 0);
        $name = trim($_POST['department_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($id <= 0 || $name === '') {
            redirectWith(BASE_URL . '/supervisor/departments.php', 'danger', 'Department name is required.');
        }

        $stmt = $conn->prepare("UPDATE departments SET department_name = ?, description = ?, is_active = ? WHERE department_id = ?");
        $stmt->bind_param("ssii", $name, $description, $is_active, $id);
        if ($stmt->execute()) {
            logAudit($conn, $_SESSION['user_id'], 'UPDATE', 'Department', $id, "Updated department: $name");
            redirectWith(BASE_URL . '/supervisor/departments.php', 'success', "Department \"$name\" updated successfully.");
        } else {
            redirectWith(BASE_URL . '/supervisor/departments.php', 'danger', 'Error updating department: ' . $conn->error);
        }
        $stmt->close();
    }
}

// ─── Handle DELETE ───────────────────────────────────────
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $did = (int) $_GET['delete'];

    // Get name first for audit
    $res = $conn->query("SELECT department_name FROM departments WHERE department_id = $did");
    $dept = $res->fetch_assoc();

    if (!$dept) {
        redirectWith(BASE_URL . '/supervisor/departments.php', 'danger', 'Department not found.');
    }
    $dname = $dept['department_name'];

    // Check if employees are assigned (using foreign key)
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM employees WHERE department_id = ? AND employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)");
    $stmt->bind_param("i", $did);
    $stmt->execute();
    $emp_check = $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();

    if ($emp_check > 0) {
        redirectWith(BASE_URL . '/supervisor/departments.php', 'danger', "Cannot delete department \"$dname\" — it still has $emp_check employee(s) assigned to it.");
    }

    // Safe to delete
    $conn->query("DELETE FROM departments WHERE department_id = $did");
    logAudit($conn, $_SESSION['user_id'], 'DELETE', 'Department', $did, "Deleted department: $dname");
    redirectWith(BASE_URL . '/supervisor/departments.php', 'success', 'Department deleted successfully.');
}

require_once '../includes/header.php';

// Fetch departments with counts
$departments = $conn->query("
    SELECT d.*,
           (SELECT COUNT(*) FROM employees WHERE department_id = d.department_id AND employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)) as employee_count
    FROM departments d
    ORDER BY d.department_name
");
$department_count = $departments->num_rows;
$active_department_count = (int) $conn->query("SELECT COUNT(*) as cnt FROM departments WHERE is_active = 1")->fetch_assoc()['cnt'];
$inactive_department_count = max(0, $department_count - $active_department_count);
$department_employee_total = (int) $conn->query("SELECT COUNT(*) as cnt FROM employees WHERE department_id IS NOT NULL AND employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)")->fetch_assoc()['cnt'];
?>

<style>
    .student-list { display: flex; flex-direction: column; gap: 12px; }
    .student-item { background: #fff; border-radius: 12px; padding: 15px; display: flex; align-items: flex-start; gap: 12px; border: 1px solid #eee; box-shadow: 0 2px 8px rgba(0,0,0,0.03); }
    .student-name { font-weight: 700; color: var(--primary-blue); margin-bottom: 2px; }
    .student-meta { font-size: 0.85rem; color: #666; }
    .btn-xs { padding: 2px 8px; font-size: 0.75rem; }
</style>

<div class="page-hero fadeup">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
        <div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">HR Supervisor · Organization</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-sitemap me-2" style="color:#BD9414;"></i>Department Management</h4>
            <p class="text-white-50 small mb-0 mt-2">Organize departments and units to keep employee assignments, evaluation templates, and reports accurate.</p>
        </div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDeptModal">
            <i class="fas fa-plus me-1"></i>Add Department
        </button>
    </div>

    <div class="row g-3">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo $department_count; ?></div>
                        <div class="stat-label">Departments</div>
                    </div>
                    <i class="fas fa-sitemap stat-icon text-white-50"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo $active_department_count; ?></div>
                        <div class="stat-label">Active</div>
                    </div>
                    <i class="fas fa-check-circle stat-icon" style="color:#28a745;"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo $inactive_department_count; ?></div>
                        <div class="stat-label">Inactive</div>
                    </div>
                    <i class="fas fa-pause-circle stat-icon" style="color:#BD9414;"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo $department_employee_total; ?></div>
                        <div class="stat-label">Assigned Employees</div>
                    </div>
                    <i class="fas fa-users stat-icon" style="color:#17a2b8;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="chart-card fadeup-1">
    <div class="cc-header">
        <h5><i class="fas fa-sitemap me-2"></i>All Departments</h5>
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" class="form-control form-control-sm" id="searchDept" placeholder="Search departments...">
        </div>
    </div>
    <div class="cc-body p-0">
        <!-- Desktop Table (hidden on mobile) -->
        <div class="table-responsive d-none d-md-block">
            <table class="table table-hover" id="deptTable">
                <thead>
                    <tr>
                        <th style="cursor:pointer;" onclick="sortTable(0)">Department Name <i
                                class="fas fa-sort text-muted ms-1" style="font-size:0.8rem;"></i></th>
                        <th>Description</th>
                        <th style="cursor:pointer;" onclick="sortTable(2)">Employees <i
                                class="fas fa-sort text-muted ms-1" style="font-size:0.8rem;"></i></th>
                        <th style="cursor:pointer;" onclick="sortTable(3)">Status <i class="fas fa-sort text-muted ms-1"
                                style="font-size:0.8rem;"></i></th>
                        <th style="cursor:pointer;" onclick="sortTable(4)">Created <i
                                class="fas fa-sort text-muted ms-1" style="font-size:0.8rem;"></i></th>
                        <th style="min-width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($departments->num_rows === 0): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <i class="fas fa-sitemap fa-2x mb-2 d-block" style="opacity:0.3;"></i>
                                No departments found. Add your first department above.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php while ($d = $departments->fetch_assoc()): ?>
                            <tr>
                                <td data-label="Department">
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle me-2 text-white d-flex align-items-center justify-content-center"
                                            style="width:32px;height:32px;background:var(--primary-blue);font-size:0.8rem;font-weight:bold;">
                                            <i class="fas fa-sitemap" style="font-size:0.75rem;"></i>
                                        </div>
                                        <strong>
                                            <?php echo e($d['department_name']); ?>
                                        </strong>
                                    </div>
                                </td>
                                <td data-label="Description" class="small text-muted">
                                    <?php echo e($d['description'] ?: 'No description'); ?>
                                </td>
                                <td data-label="Employees">
                                    <span class="badge bg-info">
                                        <?php echo $d['employee_count']; ?>
                                    </span>
                                </td>
                                <td data-label="Status">
                                    <?php if ($d['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Created"><small>
                                        <?php echo formatDate($d['created_at']); ?>
                                    </small></td>
                                <td data-label="Actions">
                                    <div class="d-flex gap-1 align-items-center flex-nowrap">
                                        <button class="btn btn-sm btn-outline-primary" title="Edit"
                                            onclick="openEditModal(<?php echo $d['department_id']; ?>, '<?php echo e(addslashes($d['department_name'])); ?>', '<?php echo e(addslashes($d['description'] ?? '')); ?>', <?php echo $d['is_active']; ?>)"
                                            data-bs-toggle="modal" data-bs-target="#editDeptModal">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" title="Delete"
                                            onclick="setDeleteTarget(<?php echo $d['department_id']; ?>, '<?php echo e(addslashes($d['department_name'])); ?>', <?php echo $d['employee_count']; ?>)"
                                            data-bs-toggle="modal" data-bs-target="#deleteDeptModal">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Card List (visible only on mobile) -->
        <div class="mobile-list-view d-block d-md-none p-3">
            <div class="student-list">
                <?php 
                if ($department_count === 0): ?>
                    <div class="text-center py-4 text-muted">No departments found. Add your first department above.</div>
                <?php else:
                    $departments->data_seek(0);
                    while ($d = $departments->fetch_assoc()): ?>
                    <div class="student-item hr-mobile-card mb-3 p-3 bg-white rounded-3 shadow-sm border"
                         data-department-id="<?php echo $d['department_id']; ?>"
                         style="flex-direction: column; align-items: stretch; width: 100%; box-sizing: border-box;">
                        
                        <!-- Header Row: Dept Icon + Name + Description | Status Badge & Actions -->
                        <div class="d-flex align-items-center justify-content-between pb-2 mb-2 border-bottom gap-2">
                            <div class="d-flex align-items-center gap-2.5" style="min-width: 0;">
                                <div class="rounded-circle text-white d-flex align-items-center justify-content-center flex-shrink-0"
                                    style="width: 40px; height: 40px; background: var(--primary-blue, #2563eb); font-size: 0.95rem; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.08);">
                                    <i class="fas fa-sitemap"></i>
                                </div>
                                <div style="min-width: 0;">
                                    <h6 class="fw-bold mb-0 text-truncate" style="font-size: 0.92rem; color: #1c271b;">
                                        <?php echo e($d['department_name']); ?>
                                    </h6>
                                    <small class="text-muted text-truncate d-block" style="font-size: 0.74rem;">
                                        <?php echo e($d['description'] ?: 'No description'); ?>
                                    </small>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                <?php if ($d['is_active']): ?>
                                    <span class="badge bg-success" style="font-size: 0.68rem;">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary" style="font-size: 0.68rem;">Inactive</span>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-outline-primary py-1 px-2 ms-1" title="Edit"
                                    onclick="openEditModal(<?php echo $d['department_id']; ?>, '<?php echo e(addslashes($d['department_name'])); ?>', '<?php echo e(addslashes($d['description'] ?? '')); ?>', <?php echo $d['is_active']; ?>)"
                                    data-bs-toggle="modal" data-bs-target="#editDeptModal">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger py-1 px-2" title="Delete"
                                    onclick="setDeleteTarget(<?php echo $d['department_id']; ?>, '<?php echo e(addslashes($d['department_name'])); ?>', <?php echo $d['employee_count']; ?>)"
                                    data-bs-toggle="modal" data-bs-target="#deleteDeptModal">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Details Row: Employees Count + Created Date -->
                        <div class="d-flex justify-content-between align-items-center pt-1 text-muted small" style="font-size: 0.78rem;">
                            <span class="badge bg-info" style="font-size:0.7rem;"><i class="fas fa-users me-1"></i><?php echo $d['employee_count']; ?> Employees</span>
                            <span><i class="fas fa-calendar-alt me-1 text-warning"></i><?php echo formatDate($d['created_at']); ?></span>
                        </div>
                    </div>
                    <?php endwhile;
                endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Dept Modal -->
<div class="modal fade" id="addDeptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="add">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add Department</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Department Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="department_name" required
                            placeholder="e.g. Accounting">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"
                            placeholder="Optional description..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Dept Modal -->
<div class="modal fade" id="editDeptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="department_id" id="editDeptId">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Department</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Department Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="department_name" id="editDeptName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="editDeptDesc" rows="3"></textarea>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="editDeptActive" checked>
                        <label class="form-check-label" for="editDeptActive">Active Status</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteDeptModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Delete Department</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <p>Delete <strong id="deleteDeptName"></strong>?</p>
                <div id="deleteWarning" class="text-danger small"></div>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="deleteConfirmBtn" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</div>

<script>
    function openEditModal(id, name, desc, active) {
        document.getElementById('editDeptId').value = id;
        document.getElementById('editDeptName').value = name;
        document.getElementById('editDeptDesc').value = desc;
        document.getElementById('editDeptActive').checked = (active == 1);
    }

    function setDeleteTarget(id, name, count) {
        document.getElementById('deleteDeptName').textContent = name;
        const warn = document.getElementById('deleteWarning');
        if (count > 0) {
            warn.innerHTML = `<i class="fas fa-exclamation-triangle me-1"></i> Cannot delete: ${count} employees assigned.`;
            document.getElementById('deleteConfirmBtn').classList.add('disabled');
            document.getElementById('deleteConfirmBtn').href = '#';
        } else {
            warn.textContent = "This action cannot be undone.";
            document.getElementById('deleteConfirmBtn').classList.remove('disabled');
            document.getElementById('deleteConfirmBtn').href = '?delete=' + id;
        }
    }

    // Unified search
    document.getElementById('searchDept').addEventListener('input', function () {
        let filter = this.value.toLowerCase().trim();
        
        // Filter desktop table rows
        let rows = document.querySelectorAll('#deptTable tbody tr');
        rows.forEach(row => {
            let text = row.innerText.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });

        // Re-apply zebra striping after filtering
        applyZebraStriping('#deptTable');

        // Filter mobile card items
        let cards = document.querySelectorAll('.mobile-list-view .student-item');
        cards.forEach(card => {
            let text = card.innerText.toLowerCase();
            card.style.display = text.includes(filter) ? '' : 'none';
        });
    });
</script>

<?php require_once '../includes/footer.php'; ?>
