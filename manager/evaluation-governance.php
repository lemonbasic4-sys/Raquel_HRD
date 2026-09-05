<?php
$page_title = 'Evaluation Routing & Governance';
require_once '../includes/session-check.php';
checkRole(['HR Manager']);
require_once '../includes/functions.php';
ensureOrganizationEvaluationPackageSchema($conn);

// ─── Handle Form Submissions ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    // Batch Actions (Enable, Disable, Delete)
    if (isset($_POST['action']) && !empty($_POST['action'])) {
        $action      = $_POST['action'];
        $approver_ids = $_POST['approver_ids'] ?? [];
        if (!is_array($approver_ids) || empty($approver_ids)) {
            redirectWith(BASE_URL . '/manager/evaluation-governance.php', 'warning', 'Please select at least one approver to perform this action.');
        }
        $ids = array_filter(array_map('intval', $approver_ids), fn($id) => $id > 0);
        if (!empty($ids)) {
            $in_clause = implode(',', $ids);
            if ($action === 'batch_delete') {
                $conn->query("DELETE FROM evaluation_governance_approvers WHERE governance_approver_id IN ($in_clause)");
                syncPendingOrganizationPackageGovernanceApprovers($conn);
                logAudit($conn, (int)$_SESSION['user_id'], 'DELETE', 'Evaluation Governance', 0, "Batch deleted governance approver IDs: $in_clause");
                redirectWith(BASE_URL . '/manager/evaluation-governance.php', 'success', count($ids) . ' approver(s) deleted successfully.');
            } elseif ($action === 'batch_disable') {
                $conn->query("UPDATE evaluation_governance_approvers SET is_active = 0 WHERE governance_approver_id IN ($in_clause)");
                syncPendingOrganizationPackageGovernanceApprovers($conn);
                logAudit($conn, (int)$_SESSION['user_id'], 'UPDATE', 'Evaluation Governance', 0, "Batch disabled governance approver IDs: $in_clause");
                redirectWith(BASE_URL . '/manager/evaluation-governance.php', 'success', count($ids) . ' approver(s) disabled.');
            } elseif ($action === 'batch_enable') {
                $conn->query("UPDATE evaluation_governance_approvers SET is_active = 1 WHERE governance_approver_id IN ($in_clause)");
                syncPendingOrganizationPackageGovernanceApprovers($conn);
                logAudit($conn, (int)$_SESSION['user_id'], 'UPDATE', 'Evaluation Governance', 0, "Batch enabled governance approver IDs: $in_clause");
                redirectWith(BASE_URL . '/manager/evaluation-governance.php', 'success', count($ids) . ' approver(s) enabled.');
            }
        }
        redirectWith(BASE_URL . '/manager/evaluation-governance.php', 'warning', 'No valid approver IDs selected.');
    }

    // Assign Single Approver
    $type           = $_POST['governance_type']   ?? '';
    $reviewer_user_id = (int)($_POST['reviewer_user_id'] ?? 0);
    $department_id  = isset($_POST['department_id']) && is_numeric($_POST['department_id']) ? (int)$_POST['department_id'] : null;

    $valid_types = ['Board of Directors', 'Audit Committee', 'President', 'Division VP'];
    if (!in_array($type, $valid_types, true) || $reviewer_user_id <= 0) {
        redirectWith(BASE_URL . '/manager/evaluation-governance.php', 'danger', 'Choose a governance role and an active user.');
    }

    // Division VP requires a specific department; Board/Audit/President are company-wide (NULL).
    if ($type === 'Division VP' && (!$department_id || $department_id <= 0)) {
        redirectWith(BASE_URL . '/manager/evaluation-governance.php', 'danger', 'Please select a department for the Division VP assignment.');
    }
    if (in_array($type, ['Board of Directors', 'Audit Committee', 'President'], true)) {
        $department_id = null;
    }

    // Verify user is active
    $eligible_stmt = $conn->prepare("SELECT u.user_id FROM users u
        LEFT JOIN employees e ON e.employee_id = u.employee_id
        WHERE u.user_id = ? AND u.is_active = 1 AND (e.is_active = 1 OR e.employee_id IS NULL) LIMIT 1");
    $eligible_stmt->bind_param('i', $reviewer_user_id);
    $eligible_stmt->execute();
    $eligible = $eligible_stmt->get_result()->fetch_assoc();
    $eligible_stmt->close();

    if (!$eligible) {
        redirectWith(BASE_URL . '/manager/evaluation-governance.php', 'danger', 'Selected user is not found or not active.');
    }

    $null_dept = is_null($department_id) ? null : $department_id;

    // Deactivate previous active official for this slot so the newly assigned one takes effect
    if (is_null($null_dept)) {
        $deact = $conn->prepare('UPDATE evaluation_governance_approvers SET is_active = 0 WHERE governance_type = ? AND department_id IS NULL');
        $deact->bind_param('s', $type);
        $deact->execute();
        $deact->close();
    } else {
        $deact = $conn->prepare('UPDATE evaluation_governance_approvers SET is_active = 0 WHERE governance_type = ? AND department_id = ?');
        $deact->bind_param('si', $type, $null_dept);
        $deact->execute();
        $deact->close();
    }

    if (is_null($null_dept)) {
        $stmt = $conn->prepare('INSERT INTO evaluation_governance_approvers (governance_type, department_id, user_id, is_active)
            VALUES (?, NULL, ?, 1)
            ON DUPLICATE KEY UPDATE is_active = 1');
        $stmt->bind_param('si', $type, $reviewer_user_id);
    } else {
        $stmt = $conn->prepare('INSERT INTO evaluation_governance_approvers (governance_type, department_id, user_id, is_active)
            VALUES (?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE is_active = 1');
        $stmt->bind_param('sii', $type, $null_dept, $reviewer_user_id);
    }
    $stmt->execute();
    $stmt->close();

    syncPendingOrganizationPackageGovernanceApprovers($conn);
    logAudit($conn, (int)$_SESSION['user_id'], 'CREATE', 'Evaluation Governance', $reviewer_user_id, "Assigned $type approver" . ($department_id ? " for dept $department_id" : " (company-wide)"));
    redirectWith(BASE_URL . '/manager/evaluation-governance.php', 'success', 'Routing official assigned and active packages synced.');
}

// ─── Handle GET Actions (Disable, Enable, Delete Single) ─────────────────────
if (isset($_GET['disable']) && is_numeric($_GET['disable'])) {
    $id = (int)$_GET['disable'];
    $stmt = $conn->prepare('UPDATE evaluation_governance_approvers SET is_active = 0 WHERE governance_approver_id = ?');
    $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();
    syncPendingOrganizationPackageGovernanceApprovers($conn);
    logAudit($conn, (int)$_SESSION['user_id'], 'UPDATE', 'Evaluation Governance', $id, "Disabled governance approver ID $id");
    redirectWith(BASE_URL . '/manager/evaluation-governance.php', 'success', 'Official disabled. Existing package routes remain unchanged.');
}
if (isset($_GET['enable']) && is_numeric($_GET['enable'])) {
    $id = (int)$_GET['enable'];
    $stmt = $conn->prepare('UPDATE evaluation_governance_approvers SET is_active = 1 WHERE governance_approver_id = ?');
    $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();
    syncPendingOrganizationPackageGovernanceApprovers($conn);
    logAudit($conn, (int)$_SESSION['user_id'], 'UPDATE', 'Evaluation Governance', $id, "Enabled governance approver ID $id");
    redirectWith(BASE_URL . '/manager/evaluation-governance.php', 'success', 'Official enabled and packages synced.');
}
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare('DELETE FROM evaluation_governance_approvers WHERE governance_approver_id = ?');
    $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();
    syncPendingOrganizationPackageGovernanceApprovers($conn);
    logAudit($conn, (int)$_SESSION['user_id'], 'DELETE', 'Evaluation Governance', $id, "Deleted governance approver ID $id");
    redirectWith(BASE_URL . '/manager/evaluation-governance.php', 'success', 'Official deleted successfully.');
}

require_once '../includes/header.php';

// ─── Data Queries ─────────────────────────────────────────────────────────────

// All active users for selector
$users = $conn->query("SELECT u.user_id, u.full_name, u.role, e.job_title, e.rank_category_id,
    e.department_id, rc.rank_name, rc.level_order
    FROM users u
    JOIN employees e ON e.employee_id = u.employee_id
    LEFT JOIN rank_categories rc ON rc.rank_category_id = e.rank_category_id
    WHERE u.is_active = 1 AND e.is_active = 1 AND e.deleted_at IS NULL
    ORDER BY COALESCE(rc.level_order, 99), u.full_name")->fetch_all(MYSQLI_ASSOC);

$departments = $conn->query("SELECT department_id, department_name FROM departments WHERE is_active = 1 ORDER BY department_name")->fetch_all(MYSQLI_ASSOC);

// All approvers with department name
$approvers = $conn->query("SELECT ega.*, u.full_name, u.role, e.job_title,
    IFNULL(d.department_name, '(All Departments / Corporate)') AS department_name
    FROM evaluation_governance_approvers ega
    JOIN users u ON u.user_id = ega.user_id
    LEFT JOIN employees e ON e.employee_id = u.employee_id
    LEFT JOIN departments d ON d.department_id = ega.department_id
    ORDER BY FIELD(ega.governance_type,'Division VP','President','Audit Committee','Board of Directors'),
             ega.department_id, u.full_name")->fetch_all(MYSQLI_ASSOC);

// Department Matrix: for each dept, show assigned Division VP
$dept_matrix = [];
foreach ($departments as $dept) {
    $dept_id = (int)$dept['department_id'];
    $dept_matrix[$dept_id] = [
        'department_name' => $dept['department_name'],
        'division_vp'     => null,
    ];
}
foreach ($approvers as $a) {
    if ($a['governance_type'] === 'Division VP' && $a['department_id']) {
        $dept_id = (int)$a['department_id'];
        if (isset($dept_matrix[$dept_id]) && $a['is_active']) {
            $dept_matrix[$dept_id]['division_vp'] = $a['full_name'] . ' — ' . ($a['job_title'] ?: $a['role']);
        }
    }
}

// Corporate officials (company-wide)
$president_row  = null;
$audit_row      = null;
$board_row      = null;
foreach ($approvers as $a) {
    if (!$a['department_id'] && $a['is_active']) {
        if ($a['governance_type'] === 'President'          && !$president_row) $president_row = $a;
        if ($a['governance_type'] === 'Audit Committee'   && !$audit_row)     $audit_row     = $a;
        if ($a['governance_type'] === 'Board of Directors' && !$board_row)    $board_row     = $a;
    }
}
?>
<style>
    /* Checkboxes */
    .package-table input[type="checkbox"].form-check-input,
    .approver-checkbox, #selectAllApprovers {
        width: 1.25rem !important; height: 1.25rem !important;
        min-width: 1.25rem !important; min-height: 1.25rem !important;
        cursor: pointer !important; border-radius: 4px !important;
        border: 2px solid #94a3b8 !important; aspect-ratio: 1/1 !important;
        box-sizing: border-box !important; padding: 0 !important;
        display: inline-block !important; vertical-align: middle !important;
    }
    .package-table input[type="checkbox"].form-check-input:checked,
    .approver-checkbox:checked, #selectAllApprovers:checked {
        background-color: var(--rp-forest-green, #082E06) !important;
        border-color: var(--rp-forest-green, #082E06) !important;
    }

    /* Matrix table */
    .dept-matrix-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem; }
    .dept-matrix-card { border-radius: .75rem; border: 1px solid #e2e8f0; background: #fff; padding: 1rem 1.25rem; transition: box-shadow .2s; }
    .dept-matrix-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.08); }
    .dept-matrix-card .dept-name { font-weight: 700; font-size: .85rem; color: #374151; margin-bottom: .4rem; }
    .dept-matrix-card .official-name { font-size: .82rem; color: #1d4ed8; }
    .dept-matrix-card .missing { font-size: .82rem; color: #dc2626; font-style: italic; }

    /* Corporate strip */
    .corp-strip { display: flex; flex-wrap: wrap; gap: 1rem; }
    .corp-card { flex: 1 1 200px; border-radius: .75rem; padding: .9rem 1.1rem; border: 1.5px solid; }
    .corp-card.president  { border-color: #6366f1; background: #eef2ff; }
    .corp-card.audit      { border-color: #f59e0b; background: #fffbeb; }
    .corp-card.board      { border-color: #10b981; background: #ecfdf5; }
    .corp-card .corp-label { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: .3rem; }
    .corp-card.president  .corp-label { color: #4f46e5; }
    .corp-card.audit      .corp-label { color: #b45309; }
    .corp-card.board      .corp-label { color: #065f46; }
    .corp-card .corp-name  { font-weight: 700; font-size: .88rem; color: #1e293b; }
    .corp-card .corp-title { font-size: .78rem; color: #64748b; }
</style>

<main class="evaluation-packages container-fluid py-4">

    <!-- Hero Header -->
    <section class="package-hero fadeup">
        <p class="mb-1 small text-uppercase tracking-wider opacity-75">Organizational approval flow configuration</p>
        <h1 class="h4 mb-2 fw-bold"><i class="fas fa-route me-2 text-warning"></i>Evaluation Routing & Governance</h1>
        <p class="mb-0">Configure the evaluation approval route per department. Packages flow: <strong>Supervisor → Manager → Division VP → President → Audit Committee → Board of Directors (Final Lock).</strong></p>
    </section>

    <!-- Corporate Governance Strip -->
    <section class="package-card fadeup-1 mb-3">
        <header class="package-card__header">
            <h2 class="h5 mb-0 fw-bold"><i class="fas fa-globe me-2 text-success"></i>Corporate Governance Officials</h2>
        </header>
        <div class="package-card__body p-4">
            <div class="corp-strip">
                <!-- President -->
                <div class="corp-card president">
                    <div class="corp-label"><i class="fas fa-user-tie me-1"></i>President & CEO</div>
                    <?php if ($president_row): ?>
                        <div class="corp-name"><?php echo e($president_row['full_name']); ?></div>
                        <div class="corp-title"><?php echo e($president_row['job_title'] ?: $president_row['role']); ?></div>
                    <?php else: ?>
                        <div class="missing text-danger small"><i class="fas fa-exclamation-circle me-1"></i>Not assigned</div>
                    <?php endif; ?>
                </div>
                <!-- Audit Committee -->
                <div class="corp-card audit">
                    <div class="corp-label"><i class="fas fa-search-dollar me-1"></i>Audit Committee</div>
                    <?php if ($audit_row): ?>
                        <div class="corp-name"><?php echo e($audit_row['full_name']); ?></div>
                        <div class="corp-title"><?php echo e($audit_row['job_title'] ?: $audit_row['role']); ?></div>
                    <?php else: ?>
                        <div class="missing text-danger small"><i class="fas fa-exclamation-circle me-1"></i>Not assigned</div>
                    <?php endif; ?>
                </div>
                <!-- Board of Directors -->
                <div class="corp-card board">
                    <div class="corp-label"><i class="fas fa-gavel me-1"></i>Board of Directors <span class="badge bg-success ms-1" style="font-size:.6rem;">Final Lock</span></div>
                    <?php if ($board_row): ?>
                        <div class="corp-name"><?php echo e($board_row['full_name']); ?></div>
                        <div class="corp-title"><?php echo e($board_row['job_title'] ?: $board_row['role']); ?></div>
                    <?php else: ?>
                        <div class="missing text-danger small"><i class="fas fa-exclamation-circle me-1"></i>Not assigned</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Department Routing Matrix -->
    <section class="package-card fadeup-2 mb-3">
        <header class="package-card__header">
            <h2 class="h5 mb-0 fw-bold"><i class="fas fa-sitemap me-2 text-primary"></i>Department Division VP Matrix</h2>
        </header>
        <div class="package-card__body p-4">
            <p class="text-muted small mb-3">Each department's evaluation package will automatically include a Division VP sign-off step (after Manager, before President).</p>
            <div class="dept-matrix-grid">
                <?php foreach ($dept_matrix as $dept_id => $info): ?>
                <div class="dept-matrix-card">
                    <div class="dept-name"><i class="fas fa-building me-1 text-secondary"></i><?php echo e($info['department_name']); ?></div>
                    <?php if ($info['division_vp']): ?>
                        <div class="official-name"><i class="fas fa-check-circle me-1 text-success"></i><?php echo e($info['division_vp']); ?></div>
                    <?php else: ?>
                        <div class="missing"><i class="fas fa-exclamation-circle me-1"></i>Division VP not assigned</div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Assign Official Form -->
    <section class="package-card fadeup-3">
        <header class="package-card__header">
            <h2 class="h5 mb-0 fw-bold"><i class="fas fa-user-plus me-2 text-primary"></i>Assign Routing Official</h2>
        </header>
        <div class="package-card__body p-4">
            <form method="post" class="row g-3 align-items-end" id="assignForm">
                <?php echo csrfField(); ?>

                <!-- Governance Role -->
                <div class="col-md-3">
                    <label class="form-label fw-semibold" for="governance-type">Governance Role <span class="text-danger">*</span></label>
                    <select class="form-select" id="governance-type" name="governance_type" required>
                        <option value="">Select role</option>
                        <optgroup label="— Department Level —">
                            <option value="Division VP">Division VP / Executive Sign-off</option>
                        </optgroup>
                        <optgroup label="— Corporate Level —">
                            <option value="President">President & CEO</option>
                            <option value="Audit Committee">Audit Committee</option>
                            <option value="Board of Directors">Board of Directors</option>
                        </optgroup>
                    </select>
                </div>

                <!-- Department (shown only for Division VP) -->
                <div class="col-md-3" id="departmentCol">
                    <label class="form-label fw-semibold" for="governance-department">Department <span class="text-danger" id="deptRequired">*</span></label>
                    <select class="form-select" id="governance-department" name="department_id">
                        <option value="0">All Departments / Corporate</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo (int)$dept['department_id']; ?>"><?php echo e($dept['department_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text text-muted" id="deptHint">Required for Division VP roles.</div>
                </div>

                <!-- User Selector -->
                <div class="col-md-4">
                    <label class="form-label fw-semibold" for="governance-user">Authorized Official <span class="text-danger">*</span></label>
                    <select class="form-select" id="governance-user" name="reviewer_user_id" required>
                        <option value="">Select official</option>
                        <?php
                        $prevRank = null;
                        foreach ($users as $user):
                            $rankLabel = $user['rank_name'] ?? 'Unclassified';
                            if ($rankLabel !== $prevRank):
                        ?>
                            <option disabled style="font-weight:600;color:#6c757d;background:#f8f9fa;">── <?php echo e($rankLabel); ?> ──</option>
                        <?php
                                $prevRank = $rankLabel;
                            endif;
                        ?>
                            <option value="<?php echo (int)$user['user_id']; ?>"
                                data-department-id="<?php echo (int)$user['department_id']; ?>"
                                data-rank="<?php echo e($rankLabel); ?>">
                                <?php echo e($user['full_name'] . ' — ' . ($user['job_title'] ?: $user['role'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Submit -->
                <div class="col-md-2">
                    <button class="btn btn-primary w-100 rounded-pill shadow-sm" type="submit">
                        <i class="fas fa-check me-1"></i>Assign Official
                    </button>
                </div>
            </form>
        </div>
    </section>

    <!-- Configured Officials Table -->
    <section class="package-card fadeup-4 mt-3">
        <header class="package-card__header d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-2">
                <h2 class="h5 mb-0 fw-bold"><i class="fas fa-user-shield me-2 text-primary"></i>Configured Routing Officials</h2>
                <span class="badge bg-secondary-subtle text-secondary border px-3 py-1"><?php echo count($approvers); ?> Total</span>
            </div>
            <!-- Batch Action Toolbar -->
            <div id="batchActionToolbar" class="d-flex align-items-center gap-2 d-none">
                <span class="small fw-semibold text-dark me-2" id="selectedCountText">0 selected</span>
                <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3" onclick="submitBatchForm('batch_enable')">
                    <i class="fas fa-check-circle me-1"></i>Enable Selected
                </button>
                <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-3" onclick="submitBatchForm('batch_disable')">
                    <i class="fas fa-ban me-1"></i>Disable Selected
                </button>
                <button type="button" class="btn btn-sm btn-danger rounded-pill px-3 shadow-sm" onclick="submitBatchForm('batch_delete')">
                    <i class="fas fa-trash-alt me-1"></i>Delete Selected
                </button>
            </div>
        </header>

        <div class="package-card__body p-0">
            <form method="post" action="" id="batchApproversForm">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" id="batchActionInput" value="">
                <div class="table-responsive">
                    <table class="table package-table align-middle mb-0">
                        <thead class="table-light small text-uppercase">
                            <tr>
                                <th style="width:44px;" class="text-center">
                                    <input type="checkbox" class="form-check-input" id="selectAllApprovers" title="Select All">
                                </th>
                                <th style="width:20%;">Governance Role</th>
                                <th style="width:22%;">Department</th>
                                <th style="width:22%;">Official</th>
                                <th>Position / Role</th>
                                <th style="width:120px;">Status</th>
                                <th style="width:190px;" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($approvers)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">
                                        <i class="fas fa-user-slash fa-2x mb-2 d-block text-black-50"></i>
                                        No routing officials configured yet. Use the form above to assign.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php
                                // Role badge styles
                                $role_styles = [
                                    'Division VP'       => ['bg-primary-subtle text-primary border-primary-subtle', 'fa-sitemap'],
                                    'President'         => ['bg-purple-subtle text-purple border-purple-subtle', 'fa-user-tie'],
                                    'Audit Committee'   => ['bg-warning-subtle text-warning border-warning-subtle', 'fa-search-dollar'],
                                    'Board of Directors'=> ['bg-success-subtle text-success border-success-subtle', 'fa-gavel'],
                                ];
                                $prev_type = null;
                                foreach ($approvers as $approver):
                                    $style = $role_styles[$approver['governance_type']] ?? ['bg-secondary-subtle text-secondary border-secondary-subtle', 'fa-user'];
                                    if ($approver['governance_type'] !== $prev_type):
                                        $prev_type = $approver['governance_type'];
                                ?>
                                <tr class="table-light">
                                    <td colspan="7" class="fw-bold text-uppercase small py-2 ps-3" style="font-size:.7rem;letter-spacing:1px;color:#64748b;">
                                        <?php echo e($approver['governance_type']); ?> Officials
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td class="text-center">
                                        <input type="checkbox" class="form-check-input approver-checkbox" name="approver_ids[]" value="<?php echo (int)$approver['governance_approver_id']; ?>">
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $style[0]; ?> border px-2 py-1">
                                            <i class="fas <?php echo $style[1]; ?> me-1"></i><?php echo e($approver['governance_type']); ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted"><?php echo e($approver['department_name']); ?></td>
                                    <td class="fw-bold text-dark"><?php echo e($approver['full_name']); ?></td>
                                    <td class="text-muted small"><?php echo e($approver['job_title'] ?: $approver['role']); ?></td>
                                    <td>
                                        <?php if ($approver['is_active']): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1">
                                                <i class="fas fa-check-circle me-1"></i>Active
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-1">
                                                <i class="fas fa-ban me-1"></i>Disabled
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <?php if ($approver['is_active']): ?>
                                                <a class="btn btn-sm btn-outline-warning rounded-pill px-3" href="?disable=<?php echo (int)$approver['governance_approver_id']; ?>" title="Disable">
                                                    <i class="fas fa-ban me-1"></i>Disable
                                                </a>
                                                <a class="btn btn-sm btn-outline-danger rounded-pill px-2" href="?delete=<?php echo (int)$approver['governance_approver_id']; ?>" onclick="return confirm('Delete this routing official?');" title="Delete">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            <?php else: ?>
                                                <a class="btn btn-sm btn-outline-success rounded-pill px-3" href="?enable=<?php echo (int)$approver['governance_approver_id']; ?>" title="Enable">
                                                    <i class="fas fa-check-circle me-1"></i>Enable
                                                </a>
                                                <a class="btn btn-sm btn-outline-danger rounded-pill px-3" href="?delete=<?php echo (int)$approver['governance_approver_id']; ?>" onclick="return confirm('Delete this disabled routing official?');" title="Delete">
                                                    <i class="fas fa-trash-alt me-1"></i>Delete
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </section>

    <!-- Route Diagram Legend -->
    <section class="package-card fadeup-5 mt-3">
        <div class="package-card__body p-4">
            <p class="fw-semibold mb-3 text-muted small text-uppercase" style="letter-spacing:1px;"><i class="fas fa-info-circle me-1"></i>Standard Evaluation Flow</p>
            <div class="d-flex flex-wrap align-items-center gap-2" style="font-size:.85rem;">
                <span class="badge bg-light text-dark border px-3 py-2"><i class="fas fa-users me-1 text-secondary"></i>Team Self-Ratings</span>
                <i class="fas fa-arrow-right text-muted"></i>
                <span class="badge bg-light text-dark border px-3 py-2"><i class="fas fa-clipboard-check me-1 text-secondary"></i>Supervisor Consolidation</span>
                <i class="fas fa-arrow-right text-muted"></i>
                <span class="badge bg-light text-dark border px-3 py-2"><i class="fas fa-user-check me-1 text-secondary"></i>Manager Review</span>
                <i class="fas fa-arrow-right text-muted"></i>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2"><i class="fas fa-sitemap me-1"></i>Division VP</span>
                <i class="fas fa-arrow-right text-muted"></i>
                <span class="badge bg-purple-subtle border px-3 py-2" style="background:#eef2ff;color:#4f46e5;border-color:#a5b4fc!important;"><i class="fas fa-user-tie me-1"></i>President</span>
                <i class="fas fa-arrow-right text-muted"></i>
                <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2"><i class="fas fa-search-dollar me-1"></i>Audit Committee</span>
                <i class="fas fa-arrow-right text-muted"></i>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2"><i class="fas fa-gavel me-1"></i>Board of Directors <i class="fas fa-lock ms-1"></i></span>
            </div>
        </div>
    </section>

</main>

<script src="<?php echo BASE_URL; ?>/assets/js/evaluation-governance.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // ─── Batch Checkbox Toolbar ───────────────────────────────────────────
    const selectAll  = document.getElementById('selectAllApprovers');
    const checkboxes = document.querySelectorAll('.approver-checkbox');
    const toolbar    = document.getElementById('batchActionToolbar');
    const countText  = document.getElementById('selectedCountText');

    function updateToolbar() {
        const checked = document.querySelectorAll('.approver-checkbox:checked');
        const count   = checked.length;
        if (count > 0) { toolbar.classList.remove('d-none'); countText.textContent = count + ' selected'; }
        else           { toolbar.classList.add('d-none'); }
        if (selectAll) selectAll.checked = checkboxes.length > 0 && count === checkboxes.length;
    }
    if (selectAll) {
        selectAll.addEventListener('change', () => { checkboxes.forEach(cb => cb.checked = selectAll.checked); updateToolbar(); });
    }
    checkboxes.forEach(cb => cb.addEventListener('change', updateToolbar));
});

function submitBatchForm(action) {
    const checked = document.querySelectorAll('.approver-checkbox:checked');
    if (checked.length === 0) { alert('Please select at least one official.'); return; }
    const msgs = { batch_delete: `DELETE the ${checked.length} selected official(s)?`, batch_disable: `Disable ${checked.length} official(s)?`, batch_enable: `Enable ${checked.length} official(s)?` };
    if (msgs[action] && !confirm(msgs[action])) return;
    document.getElementById('batchActionInput').value = action;
    document.getElementById('batchApproversForm').submit();
}
</script>

<?php require_once '../includes/footer.php'; ?>
