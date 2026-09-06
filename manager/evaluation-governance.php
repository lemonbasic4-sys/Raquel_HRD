<?php
$page_title = 'Evaluation Routing & Governance';
require_once '../includes/session-check.php';
checkRole(['HR Manager']);
require_once '../includes/functions.php';
ensureOrganizationEvaluationPackageSchema($conn);

// ─── Handle Form Submissions ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    // Auto-Detect & Sync All Governance Roles (DO NOT auto-generate user accounts)
    if (isset($_POST['action']) && $_POST['action'] === 'auto_detect_all') {
        $linked = autoDetectAndSyncAllGovernanceApprovers($conn);
        logAudit($conn, (int)$_SESSION['user_id'], 'UPDATE', 'Evaluation Governance', 0, "Auto-detected and synced $linked governance approver(s)");
        redirectWith(BASE_URL . '/manager/evaluation-governance.php', 'success', "Smart Detection complete: $linked governance official(s) auto-assigned from employee job titles.");
    }

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
    $type                 = $_POST['governance_type'] ?? '';
    $reviewer_employee_id = (int)($_POST['reviewer_employee_id'] ?? $_POST['reviewer_user_id'] ?? 0);
    $department_id        = isset($_POST['department_id']) && is_numeric($_POST['department_id']) ? (int)$_POST['department_id'] : null;

    $valid_types = ['Board of Directors', 'Audit Committee', 'President', 'Division VP'];
    if (!in_array($type, $valid_types, true) || $reviewer_employee_id <= 0) {
        redirectWith(BASE_URL . '/manager/evaluation-governance.php', 'danger', 'Choose a governance role and an employee.');
    }

    // Division VP requires a specific department; Board/Audit/President are company-wide (NULL).
    if ($type === 'Division VP' && (!$department_id || $department_id <= 0)) {
        redirectWith(BASE_URL . '/manager/evaluation-governance.php', 'danger', 'Please select a department for the Division VP assignment.');
    }
    if (in_array($type, ['Board of Directors', 'Audit Committee', 'President'], true)) {
        $department_id = null;
    }

    // Verify employee is active
    $eligible_stmt = $conn->prepare("SELECT employee_id, first_name, last_name FROM employees WHERE employee_id = ? AND is_active = 1 AND deleted_at IS NULL LIMIT 1");
    $eligible_stmt->bind_param('i', $reviewer_employee_id);
    $eligible_stmt->execute();
    $eligible = $eligible_stmt->get_result()->fetch_assoc();
    $eligible_stmt->close();

    if (!$eligible) {
        redirectWith(BASE_URL . '/manager/evaluation-governance.php', 'danger', 'Selected employee is not found or not active.');
    }

    // Resolve user_id if already provisioned by Administrator (DO NOT auto-generate accounts)
    $u_stmt = $conn->prepare("SELECT user_id FROM users WHERE employee_id = ? AND is_active = 1 AND deleted_at IS NULL ORDER BY (role != 'Employee') DESC LIMIT 1");
    $u_stmt->bind_param('i', $reviewer_employee_id);
    $u_stmt->execute();
    $user_row = $u_stmt->get_result()->fetch_assoc();
    $u_stmt->close();
    $reviewer_user_id = $user_row ? (int)$user_row['user_id'] : null;

    $null_dept = is_null($department_id) ? null : $department_id;

    // ── Prevent same employee from holding two different corporate governance roles ──
    $corporate_roles = ['President', 'Audit Committee', 'Board of Directors'];
    if (in_array($type, $corporate_roles, true)) {
        $conflict_chk = $conn->prepare("
            SELECT governance_type FROM evaluation_governance_approvers
            WHERE employee_id = ? AND governance_type != ? AND governance_type IN ('President','Audit Committee','Board of Directors') AND is_active = 1
            LIMIT 1
        ");
        $conflict_chk->bind_param('is', $reviewer_employee_id, $type);
        $conflict_chk->execute();
        $conflict = $conflict_chk->get_result()->fetch_assoc();
        $conflict_chk->close();
        if ($conflict) {
            redirectWith(BASE_URL . '/manager/evaluation-governance.php', 'danger',
                "This employee is already assigned as <strong>{$conflict['governance_type']}</strong>. Corporate governance roles (President, Audit Committee, Board of Directors) must each be a <strong>different person</strong>.");
        }
    }

    // Deactivate previous active official for this slot so the newly assigned one takes effect

    if (is_null($null_dept)) {
        $deact = $conn->prepare('UPDATE evaluation_governance_approvers SET is_active = 0 WHERE governance_type = ? AND department_id IS NULL');
        $deact->bind_param('s', $type);
        $deact->execute();
        $deact->close();

        $stmt = $conn->prepare('INSERT INTO evaluation_governance_approvers (governance_type, department_id, employee_id, user_id, is_active)
            VALUES (?, NULL, ?, ?, 1)
            ON DUPLICATE KEY UPDATE employee_id = VALUES(employee_id), user_id = VALUES(user_id), is_active = 1');
        $stmt->bind_param('sii', $type, $reviewer_employee_id, $reviewer_user_id);
    } else {
        $deact = $conn->prepare('UPDATE evaluation_governance_approvers SET is_active = 0 WHERE governance_type = ? AND department_id = ?');
        $deact->bind_param('si', $type, $null_dept);
        $deact->execute();
        $deact->close();

        $stmt = $conn->prepare('INSERT INTO evaluation_governance_approvers (governance_type, department_id, employee_id, user_id, is_active)
            VALUES (?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE employee_id = VALUES(employee_id), user_id = VALUES(user_id), is_active = 1');
        $stmt->bind_param('siii', $type, $null_dept, $reviewer_employee_id, $reviewer_user_id);
    }
    $stmt->execute();
    $stmt->close();

    syncPendingOrganizationPackageGovernanceApprovers($conn);
    logAudit($conn, (int)$_SESSION['user_id'], 'CREATE', 'Evaluation Governance', $reviewer_employee_id, "Assigned $type approver: " . $eligible['first_name'] . ' ' . $eligible['last_name'] . ($department_id ? " for dept $department_id" : " (company-wide)"));
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

// All active employees for selector with metadata (deduplicated by employee)
$all_raw_employees = $conn->query("SELECT e.employee_id, e.employee_code,
    TRIM(CONCAT(e.first_name, ' ', IFNULL(CONCAT(e.middle_name, ' '), ''), e.last_name, IFNULL(CONCAT(' ', e.name_extension), ''))) AS full_name,
    e.job_title, e.rank_category_id, e.department_id, d.department_name, rc.rank_name, rc.level_order,
    u.user_id, u.username, u.role
    FROM employees e
    LEFT JOIN departments d ON d.department_id = e.department_id
    LEFT JOIN rank_categories rc ON rc.rank_category_id = e.rank_category_id
    LEFT JOIN users u ON u.employee_id = e.employee_id AND u.is_active = 1 AND u.deleted_at IS NULL
    WHERE e.is_active = 1 AND e.deleted_at IS NULL
    ORDER BY COALESCE(rc.level_order, 99), e.last_name, e.first_name, (u.role != 'Employee') DESC")->fetch_all(MYSQLI_ASSOC);

$deduped_map = [];
foreach ($all_raw_employees as $row) {
    $eid = (int)$row['employee_id'];
    if (!isset($deduped_map[$eid])) {
        $deduped_map[$eid] = $row;
    } else {
        // If an employee has multiple user accounts provisioned by Admin, prioritize administrative roles
        if (!empty($row['role']) && $row['role'] !== 'Employee') {
            $deduped_map[$eid] = $row;
        }
    }
}
$raw_users = array_values($deduped_map);

$users = [];
$recommended_users = [];
foreach ($raw_users as $user) {
    $detected = autoDetectGovernanceRoleFromJobTitle($user['job_title'] ?? '');
    $user['detected_role'] = $detected;
    $users[] = $user;
    if ($detected) {
        $recommended_users[] = $user;
    }
}

$departments = $conn->query("SELECT department_id, department_name FROM departments WHERE is_active = 1 ORDER BY department_name")->fetch_all(MYSQLI_ASSOC);

// All approvers with department name and employee info
$approvers = $conn->query("SELECT ega.*, 
    e.employee_code,
    TRIM(CONCAT(e.first_name, ' ', IFNULL(CONCAT(e.middle_name, ' '), ''), e.last_name)) AS full_name,
    e.job_title,
    u.username, u.role,
    IFNULL(d.department_name, '(All Departments / Corporate)') AS department_name
    FROM evaluation_governance_approvers ega
    JOIN employees e ON e.employee_id = ega.employee_id
    LEFT JOIN users u ON u.user_id = ega.user_id AND u.is_active = 1
    LEFT JOIN departments d ON d.department_id = ega.department_id
    ORDER BY FIELD(ega.governance_type,'Division VP','President','Audit Committee','Board of Directors'),
             ega.department_id, e.last_name")->fetch_all(MYSQLI_ASSOC);

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
            $dept_matrix[$dept_id]['division_vp'] = $a['full_name'] . ' — ' . ($a['job_title'] ?: ($a['role'] ?? 'Division VP'));
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

// Build map of employee_id => governance_type for active corporate assignments (used by JS to filter dropdown)
$assigned_corporate = [];
foreach ($approvers as $a) {
    if ($a['is_active'] && in_array($a['governance_type'], ['President','Audit Committee','Board of Directors'], true)) {
        $assigned_corporate[(int)$a['employee_id']] = $a['governance_type'];
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
    /* Tab Navigation */
    .eval-gov-tabs-wrapper {
        margin-top: -8px;
    }
    .custom-eval-tabs {
        background: #ffffff;
        border-radius: 14px !important;
        border: 1px solid #e2e8f0 !important;
        box-shadow: 0 4px 20px rgba(0,0,0,0.04);
        gap: 0.5rem;
    }
    .custom-eval-tabs .nav-link {
        color: #475569 !important;
        background: transparent !important;
        border-radius: 10px !important;
        font-weight: 600;
        font-size: 0.88rem;
        padding: 0.75rem 1.1rem !important;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid transparent !important;
    }
    .custom-eval-tabs .nav-link:hover {
        background: #f8fafc !important;
        color: #0f172a !important;
        border-color: #e2e8f0 !important;
    }
    .custom-eval-tabs .nav-link.active {
        background: var(--rp-forest-green, #082E06) !important;
        color: #ffffff !important;
        box-shadow: 0 4px 12px rgba(8, 46, 6, 0.25);
        border-color: var(--rp-forest-green, #082E06) !important;
    }
    .custom-eval-tabs .nav-link.active i {
        color: #facc15 !important;
    }
    .custom-eval-tabs .nav-link.active .badge {
        background: rgba(255, 255, 255, 0.2) !important;
        color: #ffffff !important;
        border-color: rgba(255, 255, 255, 0.3) !important;
    }

    /* Tom Select Dropdown visibility & layering */
    .ts-dropdown {
        z-index: 99999 !important;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15) !important;
        border-radius: 8px !important;
        border: 1px solid #cbd5e1 !important;
    }
    .ts-dropdown .ts-dropdown-content {
        max-height: 280px !important;
    }
    .ts-wrapper .ts-control {
        border-radius: 0.375rem !important;
        min-height: 38px !important;
        padding: 0.375rem 0.75rem !important;
        font-size: 0.9rem !important;
        border-color: #dee2e6 !important;
    }
    .ts-wrapper.focus .ts-control {
        border-color: #86b7fe !important;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
    }
    #assignSection,
    #assignSection .package-card__body,
    #tab-assign {
        overflow: visible !important;
    }

    /* Governance Role Badges (High-Contrast & Vibrant Design) */
    .badge-gov {
        display: inline-flex !important;
        align-items: center !important;
        gap: 0.45rem !important;
        font-size: 0.78rem !important;
        font-weight: 700 !important;
        letter-spacing: 0.03em !important;
        padding: 0.4rem 0.85rem !important;
        border-radius: 9999px !important;
        border: 1.5px solid transparent !important;
        white-space: nowrap !important;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06) !important;
        text-transform: uppercase !important;
    }
    .badge-gov i {
        font-size: 0.82rem !important;
    }
    .badge-gov-vp {
        background-color: #dbeafe !important;
        color: #1e40af !important;
        border-color: #93c5fd !important;
    }
    .badge-gov-president {
        background-color: #ede9fe !important;
        color: #5b21b6 !important;
        border-color: #c4b5fd !important;
    }
    .badge-gov-audit {
        background-color: #fef3c7 !important;
        color: #92400e !important;
        border-color: #fcd34d !important;
    }
    .badge-gov-board {
        background-color: #dcfce7 !important;
        color: #166534 !important;
        border-color: #86efac !important;
    }
</style>

<main class="evaluation-packages container-fluid py-4">

    <!-- Hero Header -->
    <section class="package-hero fadeup">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <p class="mb-1 small text-uppercase tracking-wider opacity-75"><i class="fas fa-shield-alt me-1"></i>Approval Flow & Governance Matrix</p>
                <h1 class="h4 mb-1 fw-bold"><i class="fas fa-route me-2 text-warning"></i>Evaluation Routing & Governance</h1>
                <p class="mb-0 text-white-50 small">Configure sign-off authorities for each step of evaluation packages: <strong>Consolidation → Manager → Division VP → President & CEO → Audit Committee → Board of Directors (Final Lock).</strong></p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <form method="post" action="" class="m-0">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="auto_detect_all">
                    <button type="submit" class="btn btn-warning rounded-pill shadow-sm px-3 fw-bold text-dark" title="Scan all employee job titles and automatically assign matching governance roles">
                        <i class="fas fa-bolt me-1 text-danger"></i>Auto-Detect & Sync Governance
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- Tab Navigation Bar -->
    <div class="eval-gov-tabs-wrapper mb-4">
        <ul class="nav nav-pills custom-eval-tabs p-2 bg-white rounded-4 shadow-sm border" id="govTabs" role="tablist">
            <li class="nav-item flex-fill" role="presentation">
                <button class="nav-link active w-100 py-2 px-3 text-start d-flex align-items-center justify-content-between" id="tab-corp-btn" data-bs-toggle="tab" data-bs-target="#tab-corp" type="button" role="tab" aria-controls="tab-corp" aria-selected="true">
                    <span><i class="fas fa-globe me-2 text-success"></i><strong>Corporate Governance Officials (Company-Wide)</strong></span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle ms-2">Steps 5–7</span>
                </button>
            </li>
            <li class="nav-item flex-fill" role="presentation">
                <button class="nav-link w-100 py-2 px-3 text-start d-flex align-items-center justify-content-between" id="tab-matrix-btn" data-bs-toggle="tab" data-bs-target="#tab-matrix" type="button" role="tab" aria-controls="tab-matrix" aria-selected="false">
                    <span><i class="fas fa-sitemap me-2 text-primary"></i><strong>Department Division VP Matrix</strong></span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-2"><?php echo count($departments); ?> Depts</span>
                </button>
            </li>
            <li class="nav-item flex-fill" role="presentation">
                <button class="nav-link w-100 py-2 px-3 text-start d-flex align-items-center justify-content-between" id="tab-assign-btn" data-bs-toggle="tab" data-bs-target="#tab-assign" type="button" role="tab" aria-controls="tab-assign" aria-selected="false">
                    <span><i class="fas fa-user-plus me-2 text-warning"></i><strong>Assign Routing Official</strong></span>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle ms-2">Setup</span>
                </button>
            </li>
            <li class="nav-item flex-fill" role="presentation">
                <button class="nav-link w-100 py-2 px-3 text-start d-flex align-items-center justify-content-between" id="tab-configured-btn" data-bs-toggle="tab" data-bs-target="#tab-configured" type="button" role="tab" aria-controls="tab-configured" aria-selected="false">
                    <span><i class="fas fa-user-shield me-2 text-info"></i><strong>Configured Routing Officials</strong></span>
                    <span class="badge bg-secondary-subtle text-secondary border ms-2"><?php echo count($approvers); ?> Total</span>
                </button>
            </li>
        </ul>
    </div>

    <!-- Tab Content Panes -->
    <div class="tab-content" id="govTabsContent">

        <!-- ================================================================= -->
        <!-- TAB 1: Corporate Governance Officials (Company-Wide)             -->
        <!-- ================================================================= -->
        <div class="tab-pane fade show active" id="tab-corp" role="tabpanel" aria-labelledby="tab-corp-btn">
            <section class="package-card fadeup-1 mb-4">
                <header class="package-card__header d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="h5 mb-0 fw-bold"><i class="fas fa-globe me-2 text-success"></i>Corporate Governance Officials (Company-Wide)</h2>
                        <span class="small text-muted">Approval steps 4 to 6 that apply to all departments company-wide</span>
                    </div>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1">Steps 4–6</span>
                </header>
                <div class="package-card__body p-4">
                    <div class="corp-strip mb-4">
                        <!-- President -->
                        <div class="corp-card president d-flex flex-column justify-content-between shadow-sm">
                            <div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="corp-label mb-0"><i class="fas fa-user-tie me-1"></i>President & CEO</div>
                                    <?php if ($president_row): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" style="font-size:.68rem;"><i class="fas fa-check-circle me-1"></i>Assigned</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1" style="font-size:.68rem;"><i class="fas fa-exclamation-triangle me-1"></i>Unassigned</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($president_row): ?>
                                    <div class="corp-name"><?php echo e($president_row['full_name']); ?></div>
                                    <div class="corp-title mb-1"><?php echo e($president_row['job_title'] ?: $president_row['role']); ?></div>
                                    <div class="small text-muted font-monospace"><i class="fas fa-user-circle me-1"></i>@<?php echo e(!empty($president_row['username']) ? $president_row['username'] : ($president_row['employee_code'] ?? '')); ?></div>
                                <?php else: ?>
                                    <div class="missing text-danger small mb-2"><i class="fas fa-times-circle me-1"></i>No President assigned.</div>
                                <?php endif; ?>
                            </div>
                            <div class="mt-3 pt-2 border-top border-light-subtle">
                                <button type="button" class="btn btn-sm btn-outline-primary w-100 rounded-pill" onclick="selectGovernanceRole('President')">
                                    <i class="fas fa-user-edit me-1"></i><?php echo $president_row ? 'Change President' : 'Assign President'; ?>
                                </button>
                            </div>
                        </div>

                        <!-- Audit Committee -->
                        <div class="corp-card audit d-flex flex-column justify-content-between shadow-sm">
                            <div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="corp-label mb-0"><i class="fas fa-search-dollar me-1"></i>Audit Committee</div>
                                    <?php if ($audit_row): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" style="font-size:.68rem;"><i class="fas fa-check-circle me-1"></i>Assigned</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1" style="font-size:.68rem;"><i class="fas fa-exclamation-triangle me-1"></i>Unassigned</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($audit_row): ?>
                                    <div class="corp-name"><?php echo e($audit_row['full_name']); ?></div>
                                    <div class="corp-title mb-1"><?php echo e($audit_row['job_title'] ?: $audit_row['role']); ?></div>
                                    <div class="small text-muted font-monospace"><i class="fas fa-user-circle me-1"></i>@<?php echo e(!empty($audit_row['username']) ? $audit_row['username'] : ($audit_row['employee_code'] ?? '')); ?></div>
                                <?php else: ?>
                                    <div class="missing text-danger small mb-2"><i class="fas fa-times-circle me-1"></i>No Audit official assigned.</div>
                                <?php endif; ?>
                            </div>
                            <div class="mt-3 pt-2 border-top border-light-subtle">
                                <button type="button" class="btn btn-sm btn-outline-warning w-100 rounded-pill text-dark" onclick="selectGovernanceRole('Audit Committee')">
                                    <i class="fas fa-user-edit me-1"></i><?php echo $audit_row ? 'Change Audit' : 'Assign Audit'; ?>
                                </button>
                            </div>
                        </div>

                        <!-- Board of Directors -->
                        <div class="corp-card board d-flex flex-column justify-content-between shadow-sm">
                            <div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="corp-label mb-0"><i class="fas fa-gavel me-1"></i>Board of Directors <span class="badge bg-success ms-1" style="font-size:.6rem;">Final Lock</span></div>
                                    <?php if ($board_row): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" style="font-size:.68rem;"><i class="fas fa-check-circle me-1"></i>Assigned</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1" style="font-size:.68rem;"><i class="fas fa-exclamation-triangle me-1"></i>Unassigned</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($board_row): ?>
                                    <div class="corp-name"><?php echo e($board_row['full_name']); ?></div>
                                    <div class="corp-title mb-1"><?php echo e($board_row['job_title'] ?: $board_row['role']); ?></div>
                                    <div class="small text-muted font-monospace"><i class="fas fa-user-circle me-1"></i>@<?php echo e(!empty($board_row['username']) ? $board_row['username'] : ($board_row['employee_code'] ?? '')); ?></div>
                                <?php else: ?>
                                    <div class="missing text-danger small mb-2"><i class="fas fa-times-circle me-1"></i>No Board approver assigned.</div>
                                <?php endif; ?>
                            </div>
                            <div class="mt-3 pt-2 border-top border-light-subtle">
                                <button type="button" class="btn btn-sm btn-outline-success w-100 rounded-pill" onclick="selectGovernanceRole('Board of Directors')">
                                    <i class="fas fa-user-edit me-1"></i><?php echo $board_row ? 'Change Board' : 'Assign Board'; ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Flow Diagram Legend -->
                    <div class="p-3 bg-light rounded-3 border">
                        <p class="fw-bold mb-2 text-secondary small text-uppercase" style="letter-spacing:1px;"><i class="fas fa-project-diagram me-1"></i>Complete Organizational Approval Route</p>
                        <div class="d-flex flex-wrap align-items-center gap-2" style="font-size:.85rem;">
                            <span class="badge bg-white text-dark border px-3 py-2 shadow-sm"><i class="fas fa-users me-1 text-secondary"></i>1. Team Self-Ratings</span>
                            <i class="fas fa-arrow-right text-muted"></i>
                            <span class="badge bg-white text-dark border px-3 py-2 shadow-sm"><i class="fas fa-clipboard-check me-1 text-secondary"></i>2. Supervisor Consolidation</span>
                            <i class="fas fa-arrow-right text-muted"></i>
                            <span class="badge bg-white text-dark border px-3 py-2 shadow-sm"><i class="fas fa-user-check me-1 text-secondary"></i>3. Manager Review</span>
                            <i class="fas fa-arrow-right text-muted"></i>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2" title="Skipped for departments without a Division VP (e.g. Human Resources)"><i class="fas fa-sitemap me-1"></i>4. Division VP <span class="text-muted small">(if applicable)</span></span>
                            <i class="fas fa-arrow-right text-muted"></i>
                            <span class="badge bg-purple-subtle border px-3 py-2" style="background:#eef2ff;color:#4f46e5;border-color:#a5b4fc!important;"><i class="fas fa-user-tie me-1"></i>5. President &amp; CEO</span>
                            <i class="fas fa-arrow-right text-muted"></i>
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2"><i class="fas fa-search-dollar me-1"></i>6. Audit Committee</span>
                            <i class="fas fa-arrow-right text-muted"></i>
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2"><i class="fas fa-gavel me-1"></i>7. Board of Directors <i class="fas fa-lock ms-1"></i></span>
                        </div>
                        <div class="small text-muted mt-2 pt-2 border-top">
                            <i class="fas fa-info-circle me-1 text-primary"></i><strong>Direct to President:</strong> Departments reporting directly to the President (e.g. <strong>Human Resources</strong>, <strong>Marketing</strong>, <strong>Business Development</strong>) automatically skip Step 4 and advance directly from Department Manager to Step 5 (President &amp; CEO).
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- ================================================================= -->
        <!-- TAB 2: Department Division VP Matrix                             -->
        <!-- ================================================================= -->
        <div class="tab-pane fade" id="tab-matrix" role="tabpanel" aria-labelledby="tab-matrix-btn">
            <section class="package-card fadeup-2 mb-4">
                <header class="package-card__header d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="h5 mb-0 fw-bold"><i class="fas fa-sitemap me-2 text-primary"></i>Department Division VP Matrix</h2>
                        <span class="small text-muted">Configure designated Division VPs for departments that have an executive VP tier</span>
                    </div>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1"><?php echo count($departments); ?> Departments</span>
                </header>
                <div class="package-card__body p-4">
                    <div class="alert alert-info py-2 px-3 small mb-3 border-info">
                        <i class="fas fa-info-circle me-1 text-primary"></i><strong>Routing Policy:</strong> Division VP sign-off is department-specific. Departments with a Division VP (e.g. <em>Operations</em>, <em>Finance</em>, <em>General Services</em>, <em>Acquired Properties</em>) route through their VP. Departments reporting directly to the President (e.g. <strong>Human Resources</strong>) do <strong>not</strong> require a VP and automatically route directly to the President &amp; CEO.
                    </div>
                    <div class="dept-matrix-grid">
                        <?php foreach ($dept_matrix as $dept_id => $info): ?>
                        <div class="dept-matrix-card d-flex flex-column justify-content-between">
                            <div>
                                <div class="dept-name"><i class="fas fa-building me-1 text-secondary"></i><?php echo e($info['department_name']); ?></div>
                                <?php if ($info['division_vp']): ?>
                                    <div class="official-name mb-1"><i class="fas fa-check-circle me-1 text-success"></i><?php echo e($info['division_vp']); ?></div>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-0 small" style="font-size:0.72rem;">Step 4: Division VP Active</span>
                                <?php else: ?>
                                    <div class="d-flex align-items-center gap-1 mb-1">
                                        <span class="badge bg-light text-dark border px-2 py-1 small" style="font-size:0.78rem;">
                                            <i class="fas fa-level-up-alt me-1 text-primary"></i>Direct to President &amp; CEO
                                        </span>
                                    </div>
                                    <div class="text-muted small fst-italic">No Division VP (bypasses Step 4)</div>
                                <?php endif; ?>
                            </div>
                            <div class="mt-3 pt-2 text-end border-top border-light-subtle">
                                <button type="button" class="btn btn-xs btn-outline-primary rounded-pill px-3 py-1" onclick="selectGovernanceRole('Division VP', <?php echo (int)$dept_id; ?>)">
                                    <i class="fas fa-edit me-1"></i><?php echo $info['division_vp'] ? 'Change VP' : 'Assign VP (Optional)'; ?>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        </div>

        <!-- ================================================================= -->
        <!-- TAB 3: Assign Routing Official                                   -->
        <!-- ================================================================= -->
        <div class="tab-pane fade" id="tab-assign" role="tabpanel" aria-labelledby="tab-assign-btn">
            <section class="package-card fadeup-3 mb-4" id="assignSection">
                <header class="package-card__header d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="h5 mb-0 fw-bold"><i class="fas fa-user-plus me-2 text-primary"></i>Assign Routing Official</h2>
                        <span class="small text-muted">Bind an active employee account to a specific governance role or department</span>
                    </div>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-1">Role Assignment</span>
                </header>
                <div class="package-card__body p-4">

                    <form method="post" class="row g-3 align-items-end" id="assignForm">
                        <?php echo csrfField(); ?>

                        <!-- Step 1: Governance Role -->
                        <div class="col-md-3" id="govRoleCol">
                            <label class="form-label fw-bold small text-uppercase text-secondary mb-1" for="governance-type">
                                <span class="badge bg-secondary me-1">1</span> Governance Role <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="governance-type" name="governance_type" required>
                                <option value="">-- Select Role --</option>
                                <optgroup label="— Department Level (Step 4) —">
                                    <option value="Division VP">Division VP / Executive Sign-off</option>
                                </optgroup>
                                <optgroup label="— Corporate Governance (Steps 5–7) —">
                                    <option value="President">Step 5: President &amp; CEO</option>
                                    <option value="Audit Committee">Step 6: Audit Committee</option>
                                    <option value="Board of Directors">Step 7: Board of Directors (Final Lock)</option>
                                </optgroup>
                            </select>
                        </div>

                        <!-- Step 2: Department (Division VP only) -->
                        <div class="col-md-3 d-none" id="departmentCol">
                            <label class="form-label fw-bold small text-uppercase text-secondary mb-1" for="governance-department">
                                <span class="badge bg-secondary me-1">2</span> Department <span class="text-danger" id="deptRequired">*</span>
                            </label>
                            <select class="form-select" id="governance-department" name="department_id">
                                <option value="0">All Depts / Corporate</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo (int)$dept['department_id']; ?>"><?php echo e($dept['department_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Step 3: Employee Selector (enhanced by Tom Select) -->
                        <div class="col" id="employeeCol">
                            <label class="form-label fw-bold small text-uppercase text-secondary mb-1" for="governance-user">
                                <span class="badge bg-secondary me-1" id="employeeStepBadge">2</span> Employee / Official <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="governance-user" name="reviewer_employee_id" required>
                                <option value="">-- Select Employee / Official --</option>
                                <?php
                                $prevRank = null;
                                foreach ($users as $user):
                                    $rankLabel    = $user['rank_name'] ?? 'Unclassified';
                                    $assignedRole = $assigned_corporate[(int)$user['employee_id']] ?? '';
                                    if ($rankLabel !== $prevRank):
                                        if ($prevRank !== null) echo '</optgroup>';
                                        echo '<optgroup label="── ' . e($rankLabel) . ' ──">';
                                        $prevRank = $rankLabel;
                                    endif;
                                ?>
                                    <option value="<?php echo (int)$user['employee_id']; ?>"
                                        data-department-id="<?php echo (int)($user['department_id'] ?? 0); ?>"
                                        data-department-name="<?php echo e(htmlspecialchars($user['department_name'] ?? '')); ?>"
                                        data-job-title="<?php echo e(htmlspecialchars($user['job_title'] ?? '')); ?>"
                                        data-rank-category-id="<?php echo (int)($user['rank_category_id'] ?? 0); ?>"
                                        data-rank-name="<?php echo e(htmlspecialchars($rankLabel)); ?>"
                                        data-suggested-role="<?php echo e($user['detected_role'] ?? ''); ?>"
                                        data-assigned-role="<?php echo e($assignedRole); ?>"
                                        <?php if ($assignedRole) echo 'data-already="1"'; ?>>
                                        <?php
                                            echo e($user['full_name'] . ' — ' . ($user['job_title'] ?: ($user['role'] ?? 'Official')));
                                            if (!empty($user['department_name'])) echo ' (' . e($user['department_name']) . ')';
                                            if ($assignedRole) echo ' ⚠ [' . e($assignedRole) . ']';
                                        ?>
                                    </option>
                                <?php endforeach; if ($prevRank !== null) echo '</optgroup>'; ?>
                            </select>
                        </div>

                        <!-- Submit Button -->
                        <div class="col-auto">
                            <button class="btn btn-primary rounded-pill shadow-sm fw-semibold px-4" type="submit" style="padding-top:.6rem;padding-bottom:.6rem;white-space:nowrap;">
                                <i class="fas fa-check-circle me-1"></i>Save Routing
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <!-- ================================================================= -->
        <!-- TAB 4: Configured Routing Officials                              -->
        <!-- ================================================================= -->
        <div class="tab-pane fade" id="tab-configured" role="tabpanel" aria-labelledby="tab-configured-btn">
            <section class="package-card fadeup-4 mb-4">
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
                                                No routing officials configured yet. Use the <strong>Assign Routing Official</strong> tab to assign.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php
                                        // Role badge styles
                                        $role_styles = [
                                            'Division VP'        => ['badge-gov badge-gov-vp', 'fa-sitemap'],
                                            'President'          => ['badge-gov badge-gov-president', 'fa-crown'],
                                            'Audit Committee'    => ['badge-gov badge-gov-audit', 'fa-search-dollar'],
                                            'Board of Directors' => ['badge-gov badge-gov-board', 'fa-gavel'],
                                        ];
                                        $prev_type = null;
                                        foreach ($approvers as $approver):
                                            $style = $role_styles[$approver['governance_type']] ?? ['badge-gov badge-gov-vp', 'fa-user-shield'];
                                            if ($approver['governance_type'] !== $prev_type):
                                                $prev_type = $approver['governance_type'];
                                        ?>
                                        <tr class="table-light">
                                            <td colspan="7" class="fw-bold text-uppercase small py-2 ps-3" style="font-size:.72rem;letter-spacing:1px;color:#475569;background:#f8fafc;border-top:2px solid #e2e8f0;">
                                                <i class="fas <?php echo $style[1]; ?> me-2 text-secondary"></i><?php echo e($approver['governance_type']); ?> Officials
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                        <tr>
                                            <td class="text-center">
                                                <input type="checkbox" class="form-check-input approver-checkbox" name="approver_ids[]" value="<?php echo (int)$approver['governance_approver_id']; ?>">
                                            </td>
                                            <td>
                                                <span class="<?php echo $style[0]; ?>">
                                                    <i class="fas <?php echo $style[1]; ?>"></i><?php echo e($approver['governance_type']); ?>
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
        </div>

    </div><!-- /.tab-content -->

</main>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
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
