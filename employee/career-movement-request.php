<?php
/**
 * Employee Portal - Career Movement Request
 * Branch Supervisors (rank_category_id = 4) submit Transfer-only requests
 * for employees in their branch. Four-step approval chain:
 *   Branch Supervisor → Branch Manager → HR Supervisor → HR Manager
 *
 * Tasks implemented: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.8
 */
$page_title = 'Career Movement Request';
require_once '../includes/session-check.php';
checkRole(['Employee']);
require_once '../includes/functions.php';

$supervisor_employee_id = (int) ($_SESSION['employee_id'] ?? 0);
$supervisor_branch_id   = (int) ($_SESSION['branch_id']   ?? 0);
$user_id                = (int) ($_SESSION['user_id']      ?? 0);
$full_name              = trim($_SESSION['full_name'] ?? '');
$job_title_session      = trim($_SESSION['job_title'] ?? 'Branch Supervisor');

// ── Ensure portal schema columns exist before any queries reference them ─────
ensureCareerProgressionMovements($conn);

// ── Fetch supervisor's own employee record for hero display ──────────────────
$sup_stmt = $conn->prepare(
    "SELECT e.first_name, e.last_name, e.job_title, e.profile_picture,
            e.rank_category_id, e.branch_id, e.department_id, b.branch_name
     FROM employees e
     LEFT JOIN branches b ON e.branch_id = b.branch_id
     WHERE e.employee_id = ? LIMIT 1"
);
$sup_stmt->bind_param("i", $supervisor_employee_id);
$sup_stmt->execute();
$sup_emp = $sup_stmt->get_result()->fetch_assoc() ?? [];
$sup_stmt->close();

// ── Task 4.1 — Access guard: rank_category_id = 4 AND at least one branch employee ──
$is_branch_supervisor = ($sup_emp && (int) ($sup_emp['rank_category_id'] ?? 0) === 4);

// Use branch_id and department_id from the DB record — session values may be stale or 0
$sup_branch_fetch = $conn->prepare("SELECT branch_id, department_id FROM employees WHERE employee_id = ? LIMIT 1");
$sup_branch_fetch->bind_param("i", $supervisor_employee_id);
$sup_branch_fetch->execute();
$sup_branch_row = $sup_branch_fetch->get_result()->fetch_assoc();
$sup_branch_fetch->close();
if ($sup_branch_row) {
    if ((int)$sup_branch_row['branch_id'] > 0) {
        $supervisor_branch_id = (int)$sup_branch_row['branch_id'];
    }
    $supervisor_department_id = (int)($sup_branch_row['department_id'] ?? 0);
} else {
    $supervisor_department_id = 0;
}

// Only bother fetching branch employees when rank check passes
$branch_employees = [];
$no_employees_in_branch = false;
if ($is_branch_supervisor) {
    $branch_employees = getBranchEmployeesForDropdown(
        $conn,
        $supervisor_employee_id,
        $supervisor_branch_id,
        $supervisor_department_id
    );
    if (empty($branch_employees)) {
        $no_employees_in_branch = true;
    }
}

$errors   = [];
$success  = false;

// ── Task 4.5 — POST handler ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    if ($is_branch_supervisor && !$no_employees_in_branch) {
        $employee_id       = (int)   ($_POST['employee_id']        ?? 0);
        $movement_type     = trim(    $_POST['movement_type']       ?? 'Transfer');
        $new_position      = trim(    $_POST['new_position']        ?? '');
        $new_branch_id     = !empty($_POST['new_branch_id']) ? (int) $_POST['new_branch_id'] : null;
        $new_department_id = !empty($_POST['new_department_id']) ? (int) $_POST['new_department_id'] : null;
        $effective_date    = trim(    $_POST['effective_date']      ?? '');
        $reason            = trim(    $_POST['reason']              ?? '');
        $allowed_types     = ['Promotion', 'Transfer', 'Demotion', 'Role Change'];

        if (!in_array($movement_type, $allowed_types, true)) {
            $movement_type = 'Transfer';
        }

        if ($movement_type === 'Transfer') {
            if (empty($new_branch_id) && empty($new_position)) {
                $errors[] = 'Destination Branch or New Position is required for a Transfer.';
            }
        } else {
            if ($new_position === '') {
                $errors[] = 'New Position is required for ' . $movement_type . '.';
            }
        }

        // Require effective date & reason
        if (empty($effective_date)) {
            $errors[] = 'Please enter an effective date.';
        }
        if (empty($reason)) {
            $errors[] = 'Please enter a justification for this request.';
        }

        // Run the business validation function
        if (empty($errors) && $movement_type === 'Transfer' && !empty($new_branch_id)) {
            $validation_error = validateTransferSubmission(
                $conn,
                $supervisor_branch_id,
                $supervisor_employee_id,
                $employee_id,
                $new_branch_id
            );
            if ($validation_error !== null) {
                $errors[] = $validation_error;
            }
        }

        // ── Task 4.6 — Insertion with portal_workflow_stage ──────────────────
        if (empty($errors)) {
            ensureCareerProgressionMovements($conn);

            // Fetch target employee's current position and branch
            $emp_stmt = $conn->prepare(
                "SELECT job_title, branch_id, department_id FROM employees WHERE employee_id = ? LIMIT 1"
            );
            $emp_stmt->bind_param("i", $employee_id);
            $emp_stmt->execute();
            $emp_row = $emp_stmt->get_result()->fetch_assoc();
            $emp_stmt->close();

            $previous_position  = $emp_row['job_title']  ?? null;
            $previous_branch_id = (int) ($emp_row['branch_id'] ?? $supervisor_branch_id);

            // Build initiated_by_name from session or employee record
            $initiated_by_name = $full_name;
            if (empty($initiated_by_name)) {
                $initiated_by_name = trim(
                    ($sup_emp['first_name'] ?? '') . ' ' . ($sup_emp['last_name'] ?? '')
                );
            }
            $initiated_by_role = $sup_emp['job_title'] ?? $job_title_session;

            // ── Look up Branch Manager for the submitting branch ──────────
            $bm_stmt = $conn->prepare(
                "SELECT employee_id FROM employees
                 WHERE branch_id = ? AND rank_category_id = 3 AND is_active = 1
                 LIMIT 1"
            );
            $bm_stmt->bind_param("i", $supervisor_branch_id);
            $bm_stmt->execute();
            $bm_row = $bm_stmt->get_result()->fetch_assoc();
            $bm_stmt->close();

            $bm_user_id = null;
            if ($bm_row) {
                $bm_emp_id  = (int) $bm_row['employee_id'];
                $bm_user_id = getPreferredLinkedUserId($conn, $bm_emp_id, 'employee_portal');
            }

            // If Branch Manager exists, stage starts at Pending_Branch_Manager.
            // If no Branch Manager is assigned/active, directly route to Pending_HR_Supervisor.
            $initial_stage = $bm_user_id ? 'Pending_Branch_Manager' : 'Pending_HR_Supervisor';

            $insert = $conn->prepare(
                "INSERT INTO career_movements
                    (employee_id, movement_type, previous_position, new_position,
                     previous_branch_id, new_branch_id, effective_date, reason,
                     logged_by, initiated_by_name, initiated_by_role,
                     request_source, approval_status, portal_workflow_stage,
                     created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Employee Portal', 'Pending',
                         ?, NOW())"
            );
            $insert->bind_param(
                "isssiississs",
                $employee_id,        // i - int
                $movement_type,      // s - string
                $previous_position,  // s - string
                $new_position,       // s - string
                $previous_branch_id, // i - int
                $new_branch_id,      // i - int
                $effective_date,     // s - string
                $reason,             // s - string
                $user_id,            // i - int
                $initiated_by_name,  // s - string
                $initiated_by_role,  // s - string
                $initial_stage       // s - string
            );

            if ($insert->execute()) {
                $movement_id = (int) $conn->insert_id;
                $insert->close();

                // Fetch target employee name for notification messages
                $tgt_stmt = $conn->prepare(
                    "SELECT first_name, last_name FROM employees WHERE employee_id = ? LIMIT 1"
                );
                $tgt_stmt->bind_param("i", $employee_id);
                $tgt_stmt->execute();
                $tgt_row = $tgt_stmt->get_result()->fetch_assoc();
                $tgt_stmt->close();
                $target_emp_name = trim(
                    ($tgt_row['first_name'] ?? '') . ' ' . ($tgt_row['last_name'] ?? '')
                );

                if ($bm_user_id) {
                    // BM found — notify BM; stage is Pending_Branch_Manager
                    createNotification(
                        $conn,
                        $bm_user_id,
                        'Career Movement Request Pending Your Approval',
                        $initiated_by_name . ' has submitted a ' . $movement_type . ' request for ' . $target_emp_name . '.',
                        BASE_URL . '/employee/branch-manager-approvals.php'
                    );
                } else {
                    // No BM user — routed directly to HR Supervisor
                    $hr_sup_res = $conn->query(
                        "SELECT user_id FROM users WHERE role = 'HR Supervisor' AND is_active = 1"
                    );
                    if ($hr_sup_res) {
                        while ($hr_row = $hr_sup_res->fetch_assoc()) {
                            createNotification(
                                $conn,
                                (int) $hr_row['user_id'],
                                'Career Movement Request Pending Your Approval',
                                $initiated_by_name . ' submitted a ' . $movement_type . ' request for ' .
                                    $target_emp_name . ' (no Branch Manager found for branch).',
                                BASE_URL . '/supervisor/career-movements.php'
                            );
                        }
                        $hr_sup_res->free();
                    }
                }

                // Audit log
                logAudit(
                    $conn,
                    $user_id,
                    'CREATE',
                    'Career Movement',
                    $movement_id,
                    'Portal ' . $movement_type . ' request submitted for employee_id=' . $employee_id . ' (Initial Stage: ' . $initial_stage . ')',
                    ['module' => 'Career Progression', 'target_employee_id' => $employee_id,
                     'branch_id' => $supervisor_branch_id]
                );

                // Confirmation and redirect
                $_SESSION['career_confirmation'] = [
                    'ref'       => str_pad($movement_id, 6, '0', STR_PAD_LEFT),
                    'employee'  => $target_emp_name,
                    'effective' => $effective_date,
                    'type'      => $movement_type,
                ];

                header('Location: ' . $_SERVER['PHP_SELF']);
                exit();

            } else {
                $insert->close();
                $errors[] = 'A database error occurred while saving your request. Please try again.';
            }
        }

    } // end if $is_branch_supervisor
} // end POST

// Pop confirmation from session (for display after redirect)
$career_confirmation = null;
if (!empty($_SESSION['career_confirmation'])) {
    $career_confirmation = $_SESSION['career_confirmation'];
    unset($_SESSION['career_confirmation']);
}

// ── Load active departments ───────────────────────────────────────────
$dept_res = $conn->query("SELECT department_id, department_name FROM departments WHERE is_active = 1 ORDER BY department_name ASC");
$departments = [];
if ($dept_res) {
    while ($dr = $dept_res->fetch_assoc()) $departments[] = $dr;
    $dept_res->free();
}

// ── Load active job titles grouped by department with rank levels ──────
$jt_res = $conn->query("
    SELECT jt.job_title_id, jt.job_title, jt.department_id, jt.rank_category_id, rc.level_order
    FROM job_titles jt
    LEFT JOIN rank_categories rc ON jt.rank_category_id = rc.rank_category_id
    WHERE jt.is_active = 1
    ORDER BY jt.department_id, rc.level_order ASC, jt.job_title ASC
");
$dept_positions = [];
$active_positions = [];
if ($jt_res) {
    while ($row = $jt_res->fetch_assoc()) {
        $active_positions[] = $row['job_title'];
        $dept_positions[$row['department_id']][] = [
            'id'          => $row['job_title_id'],
            'title'       => $row['job_title'],
            'level_order' => (int)($row['level_order'] ?? 5)
        ];
    }
    $jt_res->free();
}

// ── Load active branches (excluding supervisor's own branch) ──────
$dest_branches = [];
$br_stmt = $conn->prepare(
    "SELECT branch_id, branch_name FROM branches
     WHERE is_active = 1 AND branch_id != ?
     ORDER BY branch_name"
);
$br_stmt->bind_param("i", $supervisor_branch_id);
$br_stmt->execute();
$br_res = $br_stmt->get_result();
while ($br_row = $br_res->fetch_assoc()) {
    $dest_branches[] = $br_row;
}
$br_stmt->close();

// ── Load all branch names mapping for JS ──────────────────────────────
$branch_names = [];
$all_br_res = $conn->query("SELECT branch_id, branch_name FROM branches ORDER BY branch_name");
if ($all_br_res) {
    while ($br_row = $all_br_res->fetch_assoc()) {
        $branch_names[(string)$br_row['branch_id']] = $br_row['branch_name'];
    }
    $all_br_res->free();
}

// ── Map branch employees by ID for JS preview ─────────────────────────
$branch_emp_map = [];
foreach ($branch_employees as $be) {
    $branch_emp_map[$be['employee_id']] = $be;
}

// ── Task 4.8 — Status table query ────────────────────────────────────────────
$my_requests = [];
$status_stmt = $conn->prepare(
    "SELECT cm.movement_id, cm.portal_workflow_stage, cm.approval_status,
            cm.effective_date, cm.created_at,
            cm.branch_manager_comments, cm.hr_supervisor_comments, cm.manager_comments,
            e.first_name, e.last_name,
            b.branch_name AS dest_branch_name
     FROM career_movements cm
     JOIN employees e ON cm.employee_id = e.employee_id
     LEFT JOIN branches b ON cm.new_branch_id = b.branch_id
     WHERE cm.request_source = 'Employee Portal'
       AND cm.logged_by = ?
       AND cm.portal_workflow_stage IS NOT NULL
     ORDER BY cm.created_at DESC
     LIMIT 20"
);
$status_stmt->bind_param("i", $user_id);
$status_stmt->execute();
$status_res = $status_stmt->get_result();
while ($sr = $status_res->fetch_assoc()) {
    $my_requests[] = $sr;
}
$status_stmt->close();

require_once '../includes/header.php';
?>

<!-- ── Page Hero ─────────────────────────────────────────────────────────── -->
<div class="page-hero fadeup">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-0 gap-4">
        <div class="d-flex align-items-center gap-4 flex-wrap">
            <img src="<?php echo getEmployeeAvatar($sup_emp['profile_picture'] ?? ''); ?>"
                 loading="lazy"
                 alt="Profile photo"
                 style="width:100px;height:100px;border-radius:50%;object-fit:cover;border:4px solid rgba(255,255,255,.3);box-shadow:0 4px 15px rgba(0,0,0,.2);">
            <div>
                <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">
                    Employee Portal &middot; Career Management
                </div>
                <h2 class="text-white fw-bold mb-1 mt-1">Career Movement Request</h2>
                <p class="mb-2 text-white-50 small">
                    <i class="fas fa-briefcase me-1"></i><?php echo e($sup_emp['job_title'] ?? '—'); ?>
                    &bull; <?php echo e($sup_emp['branch_name'] ?? '—'); ?>
                </p>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0" style="font-size:.78rem;">
                        <li class="breadcrumb-item">
                            <a href="<?php echo BASE_URL; ?>/employee/dashboard.php"
                               class="text-white-50 text-decoration-none">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item text-white active" aria-current="page">
                            Career Movement Request
                        </li>
                    </ol>
                </nav>
            </div>
        </div>
        <div class="d-none d-md-block text-end">
            <a href="<?php echo BASE_URL; ?>/employee/dashboard.php"
               class="btn btn-outline-light btn-sm rounded-pill px-3">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>
</div>

<!-- ── Mobile back button ──────────────────────────────────────────────────── -->
<div class="d-md-none mt-3 mb-2 fadeup" style="animation-delay:.1s;">
    <a href="<?php echo BASE_URL; ?>/employee/dashboard.php"
       class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
    </a>
</div>

<div class="container-fluid px-3 py-4">

    <?php if ($career_confirmation): ?>
    <!-- ── Success confirmation panel ───────────────────────────────────────── -->
    <div class="alert alert-success alert-dismissible fade show shadow-sm mb-4" role="alert">
        <div class="d-flex align-items-start gap-3">
            <i class="fas fa-check-circle fa-2x text-success mt-1"></i>
            <div>
                <h6 class="alert-heading mb-1">Transfer Request Submitted</h6>
                <p class="mb-1">
                    Your request for <strong><?php echo e($career_confirmation['employee']); ?></strong>
                    has been submitted successfully.
                </p>
                <p class="mb-0 small text-muted">
                    Reference #: <strong><?php echo e($career_confirmation['ref']); ?></strong>
                    &bull; Effective Date: <strong><?php echo formatDate($career_confirmation['effective']); ?></strong>
                </p>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if (!$is_branch_supervisor): ?>
    <!-- ── Task 4.1: Access restricted message ──────────────────────────────── -->
    <div class="row justify-content-center">
        <div class="col-12 col-lg-7">
            <div class="alert alert-info shadow-sm" role="alert">
                <div class="d-flex align-items-start gap-3">
                    <i class="fas fa-info-circle fa-2x text-info mt-1"></i>
                    <div>
                        <h6 class="alert-heading mb-1">Feature Restricted</h6>
                        <p class="mb-0">
                            Career Movement Requests through the Employee Portal are available only to
                            <strong>Branch Supervisors</strong> (rank level 4). If you believe this is
                            an error, please contact your HR department.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php elseif ($no_employees_in_branch): ?>
    <!-- ── Task 4.3: No eligible employees message ──────────────────────────── -->
    <div class="row justify-content-center">
        <div class="col-12 col-lg-7">
            <div class="alert alert-info shadow-sm" role="alert">
                <div class="d-flex align-items-start gap-3">
                    <i class="fas fa-users fa-2x text-info mt-1"></i>
                    <div>
                        <h6 class="alert-heading mb-1">No Eligible Employees</h6>
                        <p class="mb-0">
                            There are currently no active employees in your branch available for
                            Transfer. Please contact HR if you believe this is incorrect.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- ── Main content: form + status table ─────────────────────────────────── -->
    <div class="row g-4">

        <!-- ── Submission Form ─────────────────────────────────────────────── -->
        <div class="col-12 col-xl-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header border-bottom py-3" style="background:linear-gradient(135deg, #082E06 0%, #163e12 100%);">
                    <h6 class="mb-0 fw-semibold text-white">
                        <i class="fas fa-paper-plane me-2" style="color:#CBA135;"></i>File Career Movement Request
                    </h6>
                </div>
                <div class="card-body p-4">

                    <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong><i class="fas fa-exclamation-triangle me-1"></i>Please fix the following:</strong>
                        <ul class="mb-0 mt-2 ps-3">
                            <?php foreach ($errors as $err): ?>
                                <li><?php echo e($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="portalMovementForm" novalidate>
                        <?php echo csrfField(); ?>

                        <!-- Step 1: Target Employee Selection -->
                        <div class="mb-4 p-3 rounded-3" style="background:#fafdfa;border:1px solid rgba(8,46,6,0.08);">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <span class="badge rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm" style="width:26px;height:26px;background:#082E06;color:#CBA135;font-size:.8rem;font-weight:700;">1</span>
                                <h6 class="fw-bold mb-0 text-dark" style="letter-spacing:-0.2px;">Target Branch Employee</h6>
                            </div>

                            <div class="mb-3">
                                <label for="employee_id" class="form-label fw-semibold text-secondary small">Select Branch Employee <span class="text-danger">*</span></label>
                                <select name="employee_id" id="employee_id" class="form-select shadow-sm" required style="border-radius:10px;border-color:#d0d7ce;">
                                    <option value="">— Choose Employee —</option>
                                    <?php foreach ($branch_employees as $be): ?>
                                        <option value="<?php echo (int) $be['employee_id']; ?>"
                                            <?php echo (isset($_POST['employee_id']) && (int) $_POST['employee_id'] === (int) $be['employee_id']) ? 'selected' : ''; ?>>
                                            <?php echo e($be['last_name'] . ', ' . $be['first_name'] . ' — ' . ($be['job_title'] ?? '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Raquel HRIS Branded Employee Profile Card -->
                            <div id="assignmentPreview" style="display:none;">
                                <div class="p-3.5 rounded-3 shadow-sm position-relative overflow-hidden" style="background:linear-gradient(135deg, #082E06 0%, #163e12 100%); border:1px solid rgba(203, 161, 53, 0.45);">
                                    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                                        <div id="empAvatar" style="width:54px;height:54px;border-radius:50%;background:linear-gradient(135deg,#BD9414,#f0c040);display:flex;align-items:center;justify-content:center;font-size:1.35rem;font-weight:800;color:#082E06;flex-shrink:0;box-shadow:0 0 0 3px rgba(203, 161, 53, 0.4);">?</div>
                                        <div class="flex-grow-1" style="min-width:180px;">
                                            <div class="fw-bold text-white fs-6" id="previewName" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">—</div>
                                            <div class="d-flex align-items-center gap-2 mt-1">
                                                <span class="badge" style="background:rgba(203, 161, 53, 0.22);color:#f0c040;border:1px solid rgba(203, 161, 53, 0.4);font-size:.72rem;" id="previewCode">—</span>
                                                <span class="badge" style="background:rgba(255,255,255,0.1);color:rgba(255,255,255,0.7);font-size:.68rem;"><i class="fas fa-user-check text-success me-1"></i>Active Staff</span>
                                            </div>
                                        </div>
                                        <div class="ms-auto text-end">
                                            <span class="badge rounded-pill px-3 py-1.5" style="background:rgba(255,255,255,0.12);color:#fff;border:1px solid rgba(255,255,255,0.22);font-size:.72rem;"><i class="fas fa-id-card me-1" style="color:#CBA135;"></i>Current Profile</span>
                                        </div>
                                    </div>
                                    <div class="row g-2 pt-2" style="border-top:1px solid rgba(255,255,255,0.12);">
                                        <div class="col-4">
                                            <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:rgba(255,255,255,0.6);"><i class="fas fa-sitemap me-1" style="color:#CBA135;"></i>Department</div>
                                            <div class="fw-semibold text-white small" id="previewDept">—</div>
                                        </div>
                                        <div class="col-4">
                                            <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:rgba(255,255,255,0.6);"><i class="fas fa-user-tie me-1" style="color:#CBA135;"></i>Position</div>
                                            <div class="fw-semibold text-white small" id="previewPos">—</div>
                                        </div>
                                        <div class="col-4">
                                            <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:rgba(255,255,255,0.6);"><i class="fas fa-store me-1" style="color:#CBA135;"></i>Branch</div>
                                            <div class="fw-semibold text-white small" id="previewBranch">—</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Movement Details -->
                        <div class="mb-4 p-3 rounded-3" style="background:#fafdfa;border:1px solid rgba(8,46,6,0.08);">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <span class="badge rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm" style="width:26px;height:26px;background:#082E06;color:#CBA135;font-size:.8rem;font-weight:700;">2</span>
                                <h6 class="fw-bold mb-0 text-dark" style="letter-spacing:-0.2px;">Movement Action Details</h6>
                            </div>

                            <div class="row g-3 mb-3">
                                <!-- Movement Type -->
                                <div class="col-md-6">
                                    <label for="movement_type" class="form-label fw-semibold text-secondary small">Movement Type <span class="text-danger">*</span></label>
                                    <select name="movement_type" id="movement_type" class="form-select shadow-sm" required style="border-radius:10px;border-color:#d0d7ce;">
                                        <option value="Transfer" <?php echo (($_POST['movement_type'] ?? '') === 'Transfer') ? 'selected' : ''; ?>>Transfer</option>
                                        <option value="Promotion" <?php echo (($_POST['movement_type'] ?? '') === 'Promotion') ? 'selected' : ''; ?>>Promotion</option>
                                        <option value="Demotion" <?php echo (($_POST['movement_type'] ?? '') === 'Demotion') ? 'selected' : ''; ?>>Demotion</option>
                                        <option value="Role Change" <?php echo (($_POST['movement_type'] ?? '') === 'Role Change') ? 'selected' : ''; ?>>Role Change</option>
                                    </select>
                                </div>

                                <!-- Destination Branch -->
                                <div class="col-md-6">
                                    <label for="new_branch_id" class="form-label fw-semibold text-secondary small" id="branchLabel">Destination Branch</label>
                                    <select name="new_branch_id" id="new_branch_id" class="form-select shadow-sm" style="border-radius:10px;border-color:#d0d7ce;">
                                        <option value="">Same branch / No branch change</option>
                                        <?php foreach ($dest_branches as $db): ?>
                                            <option value="<?php echo (int) $db['branch_id']; ?>"
                                                <?php echo (isset($_POST['new_branch_id']) && (int) $_POST['new_branch_id'] === (int) $db['branch_id']) ? 'selected' : ''; ?>>
                                                <?php echo e($db['branch_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text text-muted small" id="branchHint">The employee's current branch is excluded.</div>
                                </div>

                                <!-- Target Department -->
                                <div class="col-md-6">
                                    <label for="new_department_id" class="form-label fw-semibold text-secondary small">Target Department</label>
                                    <select name="new_department_id" id="new_department_id" class="form-select shadow-sm" style="border-radius:10px;border-color:#d0d7ce;">
                                        <option value="">Same Department</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo (int)$dept['department_id']; ?>" <?php echo (isset($_POST['new_department_id']) && (int)$_POST['new_department_id'] === (int)$dept['department_id']) ? 'selected' : ''; ?>>
                                                <?php echo e($dept['department_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text text-muted small"><i class="fas fa-sitemap me-1" style="color:#082E06;"></i>Employee can be moved to another department.</div>
                                </div>

                                <!-- New Position -->
                                <div class="col-md-6">
                                    <label for="new_position" class="form-label fw-semibold text-secondary small" id="positionLabel">New Position / Role <span class="text-danger" id="positionAsterisk">*</span></label>
                                    <select name="new_position" id="new_position" class="form-select shadow-sm" style="border-radius:10px;border-color:#d0d7ce;">
                                        <option value="">-- Select New Position --</option>
                                        <?php foreach ($active_positions as $pos): ?>
                                            <option value="<?php echo e($pos); ?>"
                                                <?php echo (isset($_POST['new_position']) && $_POST['new_position'] === $pos) ? 'selected' : ''; ?>>
                                                <?php echo e($pos); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text text-muted small" id="positionHint">Valid positions for target department are shown.</div>
                                </div>
                            </div>

                            <!-- Raquel HRIS Brand Aligned Before vs. After Impact Preview Component -->
                            <div id="impactPreview" style="display:none;">
                                <div class="rounded-3 overflow-hidden shadow-sm" style="border:1px solid rgba(8, 46, 6, 0.18);">
                                    <div class="px-3.5 py-2.5 d-flex align-items-center justify-content-between" style="background:linear-gradient(135deg, #082E06 0%, #153e12 100%);">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="fas fa-exchange-alt" style="color:#CBA135;"></i>
                                            <span style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#ffffff;">Career Movement Impact Preview</span>
                                        </div>
                                        <span class="badge px-3 py-1 shadow-sm" id="impactTypeBadge" style="font-size:.7rem;border-radius:12px;"></span>
                                    </div>
                                    <div class="d-flex flex-column flex-md-row align-items-stretch">
                                        <!-- BEFORE -->
                                        <div class="p-3.5 flex-grow-1" style="min-width:0;background:#f8fcf8;border-right:1px solid rgba(0,0,0,0.06);">
                                            <div class="mb-2 d-flex align-items-center gap-1.5" style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#60735f;">
                                                <i class="fas fa-circle me-1" style="font-size:.5rem;color:#889987;"></i>Current Status
                                            </div>
                                            <div class="fw-bold text-dark fs-6" id="impactOldPos">—</div>
                                            <div class="text-muted mt-1 small" id="impactOldBranch"><i class="fas fa-store me-1 text-muted"></i>—</div>
                                        </div>
                                        <!-- CONNECTOR ARROW -->
                                        <div class="d-flex align-items-center justify-content-center px-3 py-2" style="background:#ffffff;border-left:1px solid rgba(0,0,0,0.04);border-right:1px solid rgba(0,0,0,0.04);">
                                            <div class="shadow-sm rounded-circle d-flex align-items-center justify-content-center" style="width:38px;height:38px;background:#082E06;color:#CBA135;border:1.5px solid #CBA135;">
                                                <i class="fas fa-arrow-right fs-6 d-none d-md-block"></i>
                                                <i class="fas fa-arrow-down fs-6 d-block d-md-none"></i>
                                            </div>
                                        </div>
                                        <!-- AFTER -->
                                        <div class="p-3.5 flex-grow-1" style="min-width:0;background:linear-gradient(135deg,#edf7ec 0%,#f4fbf3 100%);">
                                            <div class="mb-2 d-flex align-items-center gap-1.5" style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#082E06;">
                                                <i class="fas fa-check-circle me-1 text-success"></i>Proposed Assignment
                                            </div>
                                            <div class="fw-bold text-success fs-6" id="impactNewPos">—</div>
                                            <div class="text-dark fw-semibold mt-1 small" id="impactNewBranch"><i class="fas fa-map-marker-alt me-1 text-danger"></i>—</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Justification & Schedule -->
                        <div class="mb-4 p-3 rounded-3" style="background:#fafdfa;border:1px solid rgba(8,46,6,0.08);">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <span class="badge rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm" style="width:26px;height:26px;background:#082E06;color:#CBA135;font-size:.8rem;font-weight:700;">3</span>
                                <h6 class="fw-bold mb-0 text-dark" style="letter-spacing:-0.2px;">Schedule & Justification</h6>
                            </div>

                            <!-- Effective Date -->
                            <div class="mb-3">
                                <label for="effective_date" class="form-label fw-semibold text-secondary small">Effective Date <span class="text-danger">*</span></label>
                                <input type="date" name="effective_date" id="effective_date"
                                       class="form-control shadow-sm" required
                                       min="<?php echo date('Y-m-d'); ?>"
                                       value="<?php echo e($_POST['effective_date'] ?? ''); ?>" style="border-radius:10px;border-color:#d0d7ce;">
                            </div>

                            <!-- Reason -->
                            <div class="mb-2">
                                <label for="reason" class="form-label fw-semibold text-secondary small">Reason / Justification <span class="text-danger">*</span></label>
                                <textarea name="reason" id="reason" class="form-control shadow-sm" rows="3"
                                          maxlength="1000" required
                                          placeholder="Explain the reason for this career movement..." style="border-radius:10px;border-color:#d0d7ce;"><?php echo e($_POST['reason'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn text-white fw-semibold py-2.5 shadow-sm" style="background:linear-gradient(135deg, #082E06 0%, #163e12 100%);border:1px solid #CBA135;border-radius:10px;">
                                <i class="fas fa-paper-plane me-2" style="color:#CBA135;"></i>Submit Movement Request
                            </button>
                        </div>
                    </form>

                </div><!-- /.card-body -->
            </div><!-- /.card -->
        </div><!-- /.col (form) -->

        <!-- ── Task 4.8: Status table ────────────────────────────────────────── -->
        <div class="col-12 col-xl-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 fw-semibold text-dark">
                        <i class="fas fa-list-alt me-2 text-primary"></i>My Submitted Requests
                    </h6>
                    <span class="badge bg-primary rounded-pill"><?php echo count($my_requests); ?></span>
                </div>
                <div class="card-body p-0">

                    <?php if (empty($my_requests)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-inbox fa-3x mb-3 d-block opacity-25"></i>
                        <p class="mb-0">You have not submitted any Transfer requests yet.</p>
                    </div>

                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size:.85rem;">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Ref #</th>
                                    <th>Employee</th>
                                    <th>Destination</th>
                                    <th>Effective</th>
                                    <th>Submitted</th>
                                    <th>Stage</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($my_requests as $req): ?>
                                <?php
                                $stage = $req['portal_workflow_stage'] ?? '';
                                $badge_class = match($stage) {
                                    'Pending_Branch_Manager' => 'badge bg-warning text-dark',
                                    'Pending_HR_Supervisor'  => 'badge bg-primary text-white',
                                    'Pending_HR_Manager'     => 'badge bg-info text-dark',
                                    'Approved'               => 'badge bg-success',
                                    'Rejected'               => 'badge bg-danger',
                                    default                  => 'badge bg-secondary',
                                };
                                $stage_icon = match($stage) {
                                    'Pending_Branch_Manager' => 'fa-user-tie',
                                    'Pending_HR_Supervisor'  => 'fa-user-shield',
                                    'Pending_HR_Manager'     => 'fa-paper-plane',
                                    'Approved'               => 'fa-check-circle',
                                    'Rejected'               => 'fa-times-circle',
                                    default                  => 'fa-clock',
                                };

                                $rejection_reason = null;
                                if ($stage === 'Rejected') {
                                    foreach (['branch_manager_comments', 'hr_supervisor_comments', 'manager_comments'] as $fld) {
                                        if (!empty($req[$fld])) {
                                            $rejection_reason = $req[$fld];
                                            break;
                                        }
                                    }
                                    if ($rejection_reason === null) {
                                        $rejection_reason = 'No reason provided';
                                    }
                                }
                                ?>
                                <tr>
                                    <td class="ps-3 fw-semibold text-muted" style="white-space:nowrap;">
                                        #<?php echo str_pad($req['movement_id'], 6, '0', STR_PAD_LEFT); ?>
                                    </td>
                                    <td>
                                        <?php echo e(trim(($req['last_name'] ?? '') . ', ' . ($req['first_name'] ?? ''))); ?>
                                    </td>
                                    <td><?php echo e($req['dest_branch_name'] ?? '—'); ?></td>
                                    <td style="white-space:nowrap;">
                                        <?php echo formatDate($req['effective_date'] ?? ''); ?>
                                    </td>
                                    <td style="white-space:nowrap;">
                                        <?php echo formatDate($req['created_at'] ?? ''); ?>
                                    </td>
                                    <td>
                                        <span class="<?php echo $badge_class; ?>" style="font-size:.75rem;">
                                            <i class="fas <?php echo $stage_icon; ?> me-1"></i><?php echo e(getPortalStageLabel($stage) ?: ($req['approval_status'] ?? 'Pending')); ?>
                                        </span>
                                        <?php if ($rejection_reason !== null): ?>
                                        <div class="mt-1 text-danger small" style="font-size:.72rem;max-width:180px;">
                                            <i class="fas fa-comment-alt me-1"></i><?php echo e($rejection_reason); ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>

                </div><!-- /.card-body -->
            </div><!-- /.card -->
        </div><!-- /.col (status table) -->

    </div><!-- /.row -->
    <?php endif; // end $is_branch_supervisor && !$no_employees_in_branch ?>

</div><!-- /.container-fluid -->

<script>
document.addEventListener('DOMContentLoaded', function () {
    const branchEmployeesMap = <?php echo json_encode($branch_emp_map, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_QUOT); ?>;
    const deptPositions      = <?php echo json_encode($dept_positions, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_QUOT); ?>;
    const branchNames        = <?php echo json_encode($branch_names, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_QUOT); ?>;

    const empSel       = document.getElementById('employee_id');
    const movTypeSel   = document.getElementById('movement_type');
    const branchSel    = document.getElementById('new_branch_id');
    const newDeptSel   = document.getElementById('new_department_id');
    const posSel       = document.getElementById('new_position');

    const preview      = document.getElementById('assignmentPreview');
    const empAvatarEl  = document.getElementById('empAvatar');
    const empNameEl    = document.getElementById('previewName');
    const empCodeEl    = document.getElementById('previewCode');
    const prevDept     = document.getElementById('previewDept');
    const prevPos      = document.getElementById('previewPos');
    const prevBranch   = document.getElementById('previewBranch');

    const impactPanel  = document.getElementById('impactPreview');
    const impactOldPos = document.getElementById('impactOldPos');
    const impactOldBr  = document.getElementById('impactOldBranch');
    const impactNewPos = document.getElementById('impactNewPos');
    const impactNewBr  = document.getElementById('impactNewBranch');
    const impactTypeBadge = document.getElementById('impactTypeBadge');

    const movTypeBadgeStyles = {
        'Promotion'  : 'background:rgba(40,167,69,0.22);color:#2ebd59;border:1px solid rgba(40,167,69,0.45);font-weight:700;',
        'Transfer'   : 'background:rgba(13,202,240,0.22);color:#2cd5f6;border:1px solid rgba(13,202,240,0.45);font-weight:700;',
        'Demotion'   : 'background:rgba(220,53,69,0.22);color:#ff7878;border:1px solid rgba(220,53,69,0.45);font-weight:700;',
        'Role Change': 'background:rgba(203,161,53,0.25);color:#f0c040;border:1px solid rgba(203,161,53,0.5);font-weight:700;',
    };

    const avatarGradients = [
        ['#BD9414','#f0c040'], ['#1565c0','#42a5f5'], ['#6a1b9a','#ab47bc'],
        ['#b71c1c','#ef5350'], ['#2e7d32','#66bb6a'], ['#e65100','#ffa726'],
        ['#00695c','#26a69a'], ['#4527a0','#7c4dff']
    ];
    function empAvatarGradient(name) {
        if (!name) return ['#BD9414','#f0c040'];
        const code = name.charCodeAt(0) % avatarGradients.length;
        return avatarGradients[code];
    }

    function parseSubRankScore(title) {
        if (!title) return 1;
        const t = title.trim();
        if (/probation|training|trainee/i.test(t)) return 0;
        const romanMatch = t.match(/\s+(VII|VI|V|IV|III|II|I)$/i);
        if (romanMatch) {
            const r = romanMatch[1].toUpperCase();
            const map = { 'VII': 7, 'VI': 6, 'V': 5, 'IV': 4, 'III': 3, 'II': 2, 'I': 1 };
            return map[r] || 1;
        }
        const numMatch = t.match(/\s+(\d+)$/);
        return numMatch ? parseInt(numMatch[1], 10) : 1;
    }

    function getPositionRankScore(title, levelOrder) {
        const lOrder = parseInt(levelOrder, 10) || 5;
        const categoryWeight = (10 - lOrder) * 100;
        return categoryWeight + parseSubRankScore(title);
    }

    function updateFilteredPositions() {
        if (!empSel || !posSel) return;
        const empId = empSel.value;
        const emp = branchEmployeesMap[empId] || null;
        const targetDid = (newDeptSel && newDeptSel.value) ? newDeptSel.value : (emp ? emp.department_id : '');

        if (!targetDid) {
            posSel.innerHTML = '<option value="">-- Select Target Dept First --</option>';
            posSel.disabled = true;
            return;
        }

        const rawPositions = deptPositions[targetDid] || [];
        if (rawPositions.length === 0) {
            posSel.innerHTML = '<option value="">No positions defined for target department</option>';
            posSel.disabled = true;
            return;
        }

        const movType  = movTypeSel ? movTypeSel.value : '';
        const empTitle = emp ? (emp.job_title || '') : '';
        const empLOrder= emp ? (emp.level_order || 5) : 5;
        const empScore = emp ? getPositionRankScore(empTitle, empLOrder) : null;
        const prevSelectedVal = posSel.value;

        let validPositions = rawPositions.filter(pos => {
            if (!empScore || !movType) return true;
            const pScore = getPositionRankScore(pos.title, pos.level_order);
            if (movType === 'Demotion') return pScore < empScore;
            if (movType === 'Promotion') return pScore > empScore;
            return true;
        });

        posSel.innerHTML = '<option value="">-- Select New Position --</option>';

        if (validPositions.length === 0) {
            if (movType === 'Demotion') {
                posSel.innerHTML = '<option value="">No lower rank positions available in target department</option>';
            } else if (movType === 'Promotion') {
                posSel.innerHTML = '<option value="">No higher rank positions available in target department</option>';
            } else {
                posSel.innerHTML = '<option value="">No valid positions found</option>';
            }
            posSel.disabled = true;
            return;
        }

        let isStillValid = false;
        validPositions.forEach(pos => {
            const opt = document.createElement('option');
            opt.value = pos.title;
            opt.textContent = pos.title;
            if (pos.title === prevSelectedVal) {
                opt.selected = true;
                isStillValid = true;
            }
            posSel.appendChild(opt);
        });

        if (!isStillValid && prevSelectedVal) posSel.value = '';
        posSel.disabled = false;
    }

    function updateImpactPreview() {
        if (!empSel) return;
        const empId = empSel.value;
        const emp   = branchEmployeesMap[empId] || null;
        const movType = movTypeSel ? movTypeSel.value : '';
        const newPos  = posSel  ? posSel.value  : '';
        const newBrId = branchSel ? branchSel.value : '';
        const newDeptOpt = newDeptSel ? newDeptSel.options[newDeptSel.selectedIndex] : null;

        if (!emp || !movType || (!newPos && !newBrId && (!newDeptOpt || !newDeptOpt.value))) {
            if (impactPanel) impactPanel.style.display = 'none';
            return;
        }

        const currentPos    = emp.job_title || '—';
        const currentBranch = emp.branch_name || branchNames[emp.branch_id] || '—';
        const currentDept   = emp.department_name || '—';

        const targetDeptName = (newDeptOpt && newDeptOpt.value) ? newDeptOpt.textContent.trim() : currentDept;
        const isCrossDept    = newDeptOpt && newDeptOpt.value && (String(newDeptOpt.value) !== String(emp.department_id));

        const afterPos      = newPos   || currentPos;
        const afterBranch   = newBrId  ? (branchNames[newBrId] || 'Branch #' + newBrId) : currentBranch;

        if (impactOldPos)  impactOldPos.textContent  = currentPos;
        if (impactOldBr)   impactOldBr.innerHTML   = `<i class="fas fa-store me-1 text-muted"></i>${currentBranch} <span class="text-muted small">(${currentDept})</span>`;
        if (impactNewPos)  impactNewPos.textContent  = afterPos;
        if (impactNewBr) {
            const crossBadge = isCrossDept ? ` <span class="badge bg-warning text-dark ms-1" style="font-size:.65rem;"><i class="fas fa-random me-1"></i>Cross-Dept</span>` : '';
            impactNewBr.innerHTML = `<i class="fas fa-map-marker-alt me-1 text-danger"></i>${afterBranch} <span class="text-muted small">(${targetDeptName})</span>${crossBadge}`;
        }

        if (impactTypeBadge) {
            const style = movTypeBadgeStyles[movType] || 'background:#e2e3e5;color:#383d41;border:1px solid #d6d8db;';
            impactTypeBadge.setAttribute('style', 'font-size:.68rem;' + style);
            impactTypeBadge.textContent = movType;
        }

        if (impactPanel) impactPanel.style.display = 'block';
    }

    if (empSel) {
        empSel.addEventListener('change', function () {
            const empId = this.value;
            const emp   = branchEmployeesMap[empId] || null;
            if (!emp) {
                if (preview) preview.style.display = 'none';
                updateFilteredPositions();
                updateImpactPreview();
                return;
            }

            const initials = ((emp.first_name ? emp.first_name.charAt(0) : '') + (emp.last_name ? emp.last_name.charAt(0) : '')).toUpperCase() || '?';
            const [c1, c2] = empAvatarGradient(emp.last_name || '');
            if (empAvatarEl) {
                empAvatarEl.textContent = initials;
                empAvatarEl.style.background = `linear-gradient(135deg,${c1},${c2})`;
            }

            if (empNameEl) empNameEl.textContent = (emp.first_name && emp.last_name) ? `${emp.first_name} ${emp.last_name}` : '—';
            if (empCodeEl) empCodeEl.textContent = emp.employee_code || ('ID #' + emp.employee_id);

            if (prevDept)   prevDept.textContent   = emp.department_name || '—';
            if (prevPos)    prevPos.textContent    = emp.job_title || '—';
            if (prevBranch) prevBranch.textContent = emp.branch_name || branchNames[emp.branch_id] || '—';
            if (preview)    preview.style.display  = 'block';

            if (newDeptSel && emp.department_id) {
                newDeptSel.value = emp.department_id;
            }

            updateFilteredPositions();
            updateImpactPreview();
        });
    }

    if (movTypeSel) {
        movTypeSel.addEventListener('change', function () {
            const isTransfer = this.value === 'Transfer';
            const posAsterisk = document.getElementById('positionAsterisk');
            const posHint     = document.getElementById('positionHint');
            const branchLabel = document.getElementById('branchLabel');
            const branchHint  = document.getElementById('branchHint');

            if (isTransfer) {
                if (posSel) posSel.removeAttribute('required');
                if (posAsterisk) posAsterisk.style.display = 'none';
                if (posHint) posHint.textContent = 'Optional for Transfer — leave blank to keep current position.';
                if (branchSel) branchSel.setAttribute('required', 'required');
                if (branchLabel) branchLabel.innerHTML = 'Destination Branch <span class="text-danger">*</span>';
                if (branchHint) branchHint.textContent = 'Destination branch is required for a Transfer.';
            } else {
                if (posSel) posSel.setAttribute('required', 'required');
                if (posAsterisk) posAsterisk.style.display = '';
                if (posHint) posHint.textContent = 'Positions for target department are shown.';
                if (branchSel) branchSel.removeAttribute('required');
                if (branchLabel) branchLabel.innerHTML = 'Destination Branch';
                if (branchHint) branchHint.textContent = 'Optional branch change.';
            }

            updateFilteredPositions();
            updateImpactPreview();
        });
    }

    if (newDeptSel) newDeptSel.addEventListener('change', function () { updateFilteredPositions(); updateImpactPreview(); });
    if (posSel)     posSel.addEventListener('change', updateImpactPreview);
    if (branchSel)  branchSel.addEventListener('change', updateImpactPreview);

    // Initial trigger if POST values pre-selected
    if (empSel && empSel.value) {
        empSel.dispatchEvent(new Event('change'));
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
