<?php
/**
 * HR Staff Portal - Submit Employee Edit Request
 * Captures a JSON diff and saves to employee_change_requests.
 * The live employee record is NOT touched until the HR Manager approves.
 */
$page_title = 'Submit Edit Request';
require_once '../includes/session-check.php';
checkRole(['HR Staff']);
require_once '../includes/functions.php';

ensureEmployeeChangeRequests($conn);

$eid = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($eid <= 0) {
    redirectWith(BASE_URL . '/staff/employees.php', 'danger', 'Invalid employee ID.');
}

// Load employee record (same as manager/edit-employee.php)
$stmt = $conn->prepare("SELECT e.*,
    ed.height_m, ed.weight_kg, ed.blood_type, ed.citizenship,
    eg.sss_number, eg.philhealth_number, eg.pagibig_number, eg.tin_number,
    ec.telephone_number, ec.mobile_number, ec.personal_email,
    edi.is_related_to_company, edi.related_details, edi.has_admin_offense, edi.admin_offense_details,
    edi.has_criminal_charge, edi.criminal_charge_details, edi.has_criminal_conviction, edi.criminal_conviction_details,
    edi.has_been_separated, edi.separation_details, edi.is_pwd, edi.pwd_details,
    edi.is_solo_parent, edi.solo_parent_details, edi.has_recent_hospital, edi.hospital_details,
    edi.has_current_treatment, edi.treatment_details
    FROM employees e
    LEFT JOIN employee_details ed ON e.employee_id = ed.employee_id
    LEFT JOIN employee_government_ids eg ON e.employee_id = eg.employee_id
    LEFT JOIN employee_contacts ec ON e.employee_id = ec.employee_id
    LEFT JOIN employee_disclosures edi ON e.employee_id = edi.employee_id
    WHERE e.employee_id = ?");
$stmt->bind_param("i", $eid);
$stmt->execute();
$emp = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$emp) {
    redirectWith(BASE_URL . '/staff/employees.php', 'danger', 'Employee not found.');
}

// Block editing Admin profiles
$adminCheck = $conn->prepare("SELECT user_id FROM users WHERE employee_id = ? AND role = 'Admin'");
$adminCheck->bind_param("i", $eid);
$adminCheck->execute();
if ($adminCheck->get_result()->num_rows > 0) {
    $adminCheck->close();
    redirectWith(BASE_URL . '/staff/employees.php', 'danger', 'Access denied to system administrator profiles.');
}
$adminCheck->close();

// Check for already-pending request for this employee
$pendingCheck = $conn->prepare("SELECT request_id FROM employee_change_requests WHERE employee_id = ? AND status = 'Pending' LIMIT 1");
$pendingCheck->bind_param("i", $eid);
$pendingCheck->execute();
$existingPending = $pendingCheck->get_result()->fetch_assoc();
$pendingCheck->close();

// Flatten emp array for form pre-fill (same as manager/edit-employee.php)
$emp['email']          = $emp['personal_email'];
$emp['contact_number'] = $emp['mobile_number'];

$res_addr  = $conn->query("SELECT * FROM employee_addresses WHERE employee_id=$eid AND address_type='Residential'")->fetch_assoc();
$perm_addr = $conn->query("SELECT * FROM employee_addresses WHERE employee_id=$eid AND address_type='Permanent'")->fetch_assoc();
foreach (['region','house_no','street','subdivision','barangay','city','province','zip_code'] as $f) {
    $emp['res_'.$f]  = $res_addr[$f]  ?? '';
    $emp['perm_'.$f] = $perm_addr[$f] ?? '';
}
$emerg = $conn->query("SELECT * FROM employee_emergency_contacts WHERE employee_id=$eid LIMIT 1")->fetch_assoc();
if ($emerg) {
    $emp['emergency_contact_name']         = $emerg['contact_name'];
    $emp['emergency_contact_relationship'] = $emerg['relationship'];
    $emp['emergency_contact_number']       = $emerg['contact_number'];
}
$family = $conn->query("SELECT * FROM employee_family WHERE employee_id=$eid")->fetch_all(MYSQLI_ASSOC);
foreach ($family as $m) {
    $pre = strtolower($m['member_type']);
    if ($pre === 'mother') {
        $emp['mother_maiden_surname'] = $m['surname'];
        $emp['mother_first_name']     = $m['first_name'];
        $emp['mother_middle_name']    = $m['middle_name'];
        $emp['mother_occupation']     = $m['occupation'];
    } else {
        $emp[$pre.'_surname']     = $m['surname'];
        $emp[$pre.'_first_name']  = $m['first_name'];
        $emp[$pre.'_middle_name'] = $m['middle_name'];
        if (isset($m['name_extension'])) $emp[$pre.'_name_ext'] = $m['name_extension'];
        $emp[$pre.'_occupation']  = $m['occupation'];
    }
}
// Load child tables for form display
$employeeChildren     = $conn->query("SELECT * FROM employee_children WHERE employee_id=$eid ORDER BY child_id")->fetch_all(MYSQLI_ASSOC);
$employeeSiblings     = $conn->query("SELECT * FROM employee_siblings WHERE employee_id=$eid ORDER BY sibling_id")->fetch_all(MYSQLI_ASSOC);
$employeeEducation    = $conn->query("SELECT * FROM employee_education WHERE employee_id=$eid ORDER BY education_id")->fetch_all(MYSQLI_ASSOC);
$employeeWork         = $conn->query("SELECT * FROM employee_work_experience WHERE employee_id=$eid ORDER BY work_id")->fetch_all(MYSQLI_ASSOC);
$employeeTrainings    = $conn->query("SELECT * FROM employee_trainings WHERE employee_id=$eid ORDER BY training_id")->fetch_all(MYSQLI_ASSOC);
$employeeVoluntary    = $conn->query("SELECT * FROM employee_voluntary_work WHERE employee_id=$eid ORDER BY voluntary_id")->fetch_all(MYSQLI_ASSOC);
$employeeEligibility  = $conn->query("SELECT * FROM employee_eligibility WHERE employee_id=$eid ORDER BY eligibility_id")->fetch_all(MYSQLI_ASSOC);
$employeeSkills       = $conn->query("SELECT * FROM employee_skills WHERE employee_id=$eid ORDER BY skill_id")->fetch_all(MYSQLI_ASSOC);
$employeeRecognitions = $conn->query("SELECT * FROM employee_recognitions WHERE employee_id=$eid ORDER BY recognition_id")->fetch_all(MYSQLI_ASSOC);
$employeeMemberships  = $conn->query("SELECT * FROM employee_memberships WHERE employee_id=$eid ORDER BY membership_id")->fetch_all(MYSQLI_ASSOC);
$employeeRealProps    = $conn->query("SELECT * FROM employee_real_properties WHERE employee_id=$eid ORDER BY property_id")->fetch_all(MYSQLI_ASSOC);
$employeePersonalProps = $conn->query("SELECT * FROM employee_personal_properties WHERE employee_id=$eid ORDER BY property_id")->fetch_all(MYSQLI_ASSOC);
$employeeLiabilities  = $conn->query("SELECT * FROM employee_liabilities WHERE employee_id=$eid ORDER BY liability_id")->fetch_all(MYSQLI_ASSOC);
$employeeRefs         = $conn->query("SELECT * FROM employee_references WHERE employee_id=$eid ORDER BY reference_id")->fetch_all(MYSQLI_ASSOC);

// ──────────────────────────────────────────────────────────────
// POST: capture diff and save change request
// ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existingPending) {
    verifyCsrfToken();

    $submitted_by = (int) $_SESSION['user_id'];

    // Resolve job_title from job_title_id (same logic as manager/edit-employee.php)
    // The form submits job_title_id via dropdown; the raw job_title text may be empty or stale.
    $submitted_job_title_id = !empty($_POST['job_title_id']) ? (int) $_POST['job_title_id'] : null;
    $submitted_job_title    = trim($_POST['job_title'] ?? '');
    if ($submitted_job_title_id !== null) {
        $jtStmt = $conn->prepare("SELECT job_title FROM job_titles WHERE job_title_id = ? AND is_active = 1");
        $jtStmt->bind_param("i", $submitted_job_title_id);
        $jtStmt->execute();
        $jtRow = $jtStmt->get_result()->fetch_assoc();
        $jtStmt->close();
        if ($jtRow) {
            $submitted_job_title = (string) $jtRow['job_title'];
        }
    }

    // Collect submitted values (flat fields tracked for diff)
    $submitted = [
        'employee_code'        => strtoupper(trim($_POST['employee_code'] ?? '')),
        'first_name'           => trim($_POST['first_name'] ?? ''),
        'last_name'            => trim($_POST['last_name'] ?? ''),
        'middle_name'          => trim($_POST['middle_name'] ?? ''),
        'name_extension'       => trim($_POST['name_extension'] ?? ''),
        'date_of_birth'        => trim($_POST['date_of_birth'] ?? ''),
        'place_of_birth'       => trim($_POST['place_of_birth'] ?? ''),
        'gender'               => trim($_POST['gender'] ?? ''),
        'civil_status'         => trim($_POST['civil_status'] ?? ''),
        'height_m'             => trim($_POST['height_m'] ?? ''),
        'weight_kg'            => trim($_POST['weight_kg'] ?? ''),
        'blood_type'           => trim($_POST['blood_type'] ?? ''),
        'citizenship'          => trim($_POST['citizenship'] ?? 'Filipino'),
        'sss_number'           => trim($_POST['sss_number'] ?? ''),
        'philhealth_number'    => trim($_POST['philhealth_number'] ?? ''),
        'pagibig_number'       => trim($_POST['pagibig_number'] ?? ''),
        'tin_number'           => trim($_POST['tin_number'] ?? ''),
        'telephone_number'     => trim($_POST['telephone_number'] ?? ''),
        'mobile_number'        => formatPHMobileNumber(trim($_POST['contact_number'] ?? '')),
        'personal_email'       => trim($_POST['email'] ?? ''),
        'hire_date'            => trim($_POST['hire_date'] ?? ''),
        'employment_status'    => trim($_POST['employment_status'] ?? ''),
        'employment_type'      => trim($_POST['employment_type'] ?? ''),
        'job_title'            => $submitted_job_title,   // resolved from job_title_id
        'branch_id'            => trim($_POST['branch_id'] ?? ''),
        'department_id'        => trim($_POST['department_id'] ?? ''),
        'rank_category_id'     => trim($_POST['rank_category_id'] ?? ''),
        'contract_start_date'  => trim($_POST['contract_start_date'] ?? ''),
        'contract_end_date'    => trim($_POST['contract_end_date'] ?? ''),
        // Addresses
        'res_house_no'     => trim($_POST['res_house_no'] ?? ''),
        'res_street'       => trim($_POST['res_street'] ?? ''),
        'res_subdivision'  => trim($_POST['res_subdivision'] ?? ''),
        'res_barangay'     => trim($_POST['res_barangay'] ?? ''),
        'res_city'         => trim($_POST['res_city'] ?? ''),
        'res_province'     => trim($_POST['res_province'] ?? ''),
        'res_zip_code'     => trim($_POST['res_zip_code'] ?? ''),
        'perm_house_no'    => trim($_POST['perm_house_no'] ?? ''),
        'perm_street'      => trim($_POST['perm_street'] ?? ''),
        'perm_subdivision' => trim($_POST['perm_subdivision'] ?? ''),
        'perm_barangay'    => trim($_POST['perm_barangay'] ?? ''),
        'perm_city'        => trim($_POST['perm_city'] ?? ''),
        'perm_province'    => trim($_POST['perm_province'] ?? ''),
        'perm_zip_code'    => trim($_POST['perm_zip_code'] ?? ''),
        // Emergency (Read primary contact from array inputs)
        'emergency_contact_name'         => isset($_POST['emergency_contact_name']) ? (is_array($_POST['emergency_contact_name']) ? trim($_POST['emergency_contact_name'][(isset($_POST['emergency_is_primary']) ? (int)$_POST['emergency_is_primary'] - 1 : 0)] ?? '') : trim($_POST['emergency_contact_name'])) : '',
        'emergency_contact_relationship' => isset($_POST['emergency_contact_relationship']) ? (is_array($_POST['emergency_contact_relationship']) ? trim($_POST['emergency_contact_relationship'][(isset($_POST['emergency_is_primary']) ? (int)$_POST['emergency_is_primary'] - 1 : 0)] ?? '') : trim($_POST['emergency_contact_relationship'])) : '',
        'emergency_contact_number'       => isset($_POST['emergency_contact_number']) ? (is_array($_POST['emergency_contact_number']) ? trim($_POST['emergency_contact_number'][(isset($_POST['emergency_is_primary']) ? (int)$_POST['emergency_is_primary'] - 1 : 0)] ?? '') : trim($_POST['emergency_contact_number'])) : '',
    ];

    // Current (old) values from DB
    $current = [
        'employee_code'        => $emp['employee_code'] ?? '',
        'first_name'           => $emp['first_name'] ?? '',
        'last_name'            => $emp['last_name'] ?? '',
        'middle_name'          => $emp['middle_name'] ?? '',
        'name_extension'       => $emp['name_extension'] ?? '',
        'date_of_birth'        => $emp['date_of_birth'] ?? '',
        'place_of_birth'       => $emp['place_of_birth'] ?? '',
        'gender'               => $emp['gender'] ?? '',
        'civil_status'         => $emp['civil_status'] ?? '',
        'height_m'             => $emp['height_m'] ?? '',
        'weight_kg'            => $emp['weight_kg'] ?? '',
        'blood_type'           => $emp['blood_type'] ?? '',
        'citizenship'          => $emp['citizenship'] ?? 'Filipino',
        'sss_number'           => $emp['sss_number'] ?? '',
        'philhealth_number'    => $emp['philhealth_number'] ?? '',
        'pagibig_number'       => $emp['pagibig_number'] ?? '',
        'tin_number'           => $emp['tin_number'] ?? '',
        'telephone_number'     => $emp['telephone_number'] ?? '',
        'mobile_number'        => $emp['mobile_number'] ?? '',
        'personal_email'       => $emp['personal_email'] ?? '',
        'hire_date'            => $emp['hire_date'] ?? '',
        'employment_status'    => $emp['employment_status'] ?? '',
        'employment_type'      => $emp['employment_type'] ?? '',
        'job_title'            => $emp['job_title'] ?? '',
        'branch_id'            => (string)($emp['branch_id'] ?? ''),
        'department_id'        => (string)($emp['department_id'] ?? ''),
        'rank_category_id'     => (string)($emp['rank_category_id'] ?? ''),
        'contract_start_date'  => $emp['contract_start_date'] ?? '',
        'contract_end_date'    => $emp['contract_end_date'] ?? '',
        'res_house_no'     => $emp['res_house_no'] ?? '',
        'res_street'       => $emp['res_street'] ?? '',
        'res_subdivision'  => $emp['res_subdivision'] ?? '',
        'res_barangay'     => $emp['res_barangay'] ?? '',
        'res_city'         => $emp['res_city'] ?? '',
        'res_province'     => $emp['res_province'] ?? '',
        'res_zip_code'     => $emp['res_zip_code'] ?? '',
        'perm_house_no'    => $emp['perm_house_no'] ?? '',
        'perm_street'      => $emp['perm_street'] ?? '',
        'perm_subdivision' => $emp['perm_subdivision'] ?? '',
        'perm_barangay'    => $emp['perm_barangay'] ?? '',
        'perm_city'        => $emp['perm_city'] ?? '',
        'perm_province'    => $emp['perm_province'] ?? '',
        'perm_zip_code'    => $emp['perm_zip_code'] ?? '',
        'emergency_contact_name'         => $emp['emergency_contact_name'] ?? '',
        'emergency_contact_relationship' => $emp['emergency_contact_relationship'] ?? '',
        'emergency_contact_number'       => $emp['emergency_contact_number'] ?? '',
    ];

    // Build diff — only changed fields
    $diff = [];
    foreach ($submitted as $field => $new_val) {
        $old_val = (string)($current[$field] ?? '');
        if ($new_val !== $old_val) {
            $diff[$field] = ['old' => $old_val, 'new' => $new_val];
        }
    }

    if (empty($diff)) {
        redirectWith(BASE_URL . '/staff/edit-employee.php?id=' . $eid, 'warning', 'No changes detected — nothing was submitted.');
    }

    // Human-readable summary of changed fields
    $field_labels = [
        'first_name' => 'First Name', 'last_name' => 'Last Name', 'middle_name' => 'Middle Name',
        'date_of_birth' => 'Date of Birth', 'gender' => 'Gender', 'civil_status' => 'Civil Status',
        'sss_number' => 'SSS No.', 'philhealth_number' => 'PhilHealth No.',
        'pagibig_number' => 'Pag-IBIG No.', 'tin_number' => 'TIN',
        'mobile_number' => 'Mobile', 'telephone_number' => 'Telephone', 'personal_email' => 'Email',
        'job_title' => 'Job Title', 'branch_id' => 'Branch', 'department_id' => 'Department',
        'employment_status' => 'Employment Status', 'hire_date' => 'Hire Date',
        'res_street' => 'Residential Address', 'perm_street' => 'Permanent Address',
        'emergency_contact_name' => 'Emergency Contact',
    ];
    $summary_parts = [];
    foreach (array_keys($diff) as $f) {
        if (isset($field_labels[$f])) $summary_parts[] = $field_labels[$f];
    }
    $summary = count($summary_parts) > 0
        ? 'Changed: ' . implode(', ', array_unique($summary_parts))
        : count($diff) . ' field(s) changed';

    // Insert change request
    $json = json_encode($diff, JSON_UNESCAPED_UNICODE);
    $staff_name = $_SESSION['full_name'] ?? 'HR Staff';
    $ins = $conn->prepare("INSERT INTO employee_change_requests (employee_id, submitted_by, changes_json, change_summary, status) VALUES (?, ?, ?, ?, 'Pending')");
    $ins->bind_param("iiss", $eid, $submitted_by, $json, $summary);
    $success = $ins->execute();
    $request_id = $ins->insert_id;
    $ins->close();

    if (!$success) {
        redirectWith(BASE_URL . '/staff/edit-employee.php?id=' . $eid, 'danger', 'Failed to submit change request. Please try again.');
    }

    // Notify all HR Managers
    $emp_name = trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''));
    $managers = $conn->query("SELECT user_id FROM users WHERE role = 'HR Manager' AND is_active = 1");
    while ($mgr = $managers->fetch_assoc()) {
        createNotification(
            $conn,
            (int) $mgr['user_id'],
            'Employee Edit Request',
            "{$staff_name} submitted changes for {$emp_name}. {$summary}",
            BASE_URL . '/manager/pending-approvals.php?tab=changes'
        );
    }

    logAudit($conn, $submitted_by, 'CREATE', 'EmployeeChangeRequest', $request_id,
        "Staff submitted edit request for employee #{$eid} ({$emp_name}). {$summary}");

    redirectWith(BASE_URL . '/staff/employees.php', 'success',
        "Edit request for {$emp_name} submitted successfully. Awaiting HR Manager approval.");
}

// ──────────────────────────────────────────────────────────────
// View: render the form
// ──────────────────────────────────────────────────────────────
require_once '../includes/header.php';
$branches = $conn->query("SELECT * FROM branches ORDER BY branch_name");
$departments_result = $conn->query("SELECT department_id, department_name FROM departments WHERE is_active = 1 ORDER BY department_name");
$departments = $departments_result ? $departments_result->fetch_all(MYSQLI_ASSOC) : [];
$job_titles_result = $conn->query("SELECT job_title_id, job_title, department_id, rank_category_id, is_head, reports_to FROM job_titles WHERE is_active = 1 ORDER BY department_id, is_head DESC, job_title");
$jobTitles = $job_titles_result ? $job_titles_result->fetch_all(MYSQLI_ASSOC) : [];

$stepLabels = [
    '1' => 'Personal Info','2' => 'Family','3' => 'Education',
    '4' => 'Work Exp.','5' => 'Training','6' => 'Voluntary',
    '7' => 'Eligibility','8' => 'Skills','9' => 'Assets',
    '10' => 'Disclosures','11' => 'References','12' => 'Employment',
];
?>

<div class="employee-edit-page">
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <p class="text-muted mb-0">Changes will be reviewed by the HR Manager before being applied.</p>
    </div>
    <a href="<?php echo BASE_URL; ?>/staff/employees.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to Employees
    </a>
</div>

<?php if ($existingPending): ?>
<div class="alert alert-warning d-flex align-items-start gap-3 mb-4">
    <i class="fas fa-clock fa-2x mt-1 flex-shrink-0"></i>
    <div>
        <strong>A change request is already pending for this employee.</strong><br>
        <span class="text-muted small">You cannot submit another request until the HR Manager reviews the existing one. Check back after it has been approved or rejected.</span>
    </div>
</div>
<?php endif; ?>

<div class="alert alert-info d-flex align-items-start gap-3 mb-4" style="border-left:4px solid #0d6efd;">
    <i class="fas fa-info-circle fa-lg mt-1 flex-shrink-0" style="color:#0d6efd;"></i>
    <div>
        <strong>HR Staff Edit Mode</strong> — You are editing <strong><?php echo e($emp['first_name'] . ' ' . $emp['last_name']); ?></strong>.
        <br><span class="text-muted small">Your changes will be saved as a <em>pending request</em> and will only be applied after HR Manager approval. The live record will not change until then.</span>
    </div>
</div>

<div class="content-card">
    <div class="card-header">
        <h5><i class="fas fa-user-edit me-2"></i>Edit Request: <?php echo e($emp['first_name'] . ' ' . $emp['last_name']); ?></h5>
    </div>
    <div class="card-body">
        <form method="POST" action="" id="editEmployeeForm" enctype="multipart/form-data" data-is-edit="true"
            <?php echo $existingPending ? 'style="pointer-events:none;opacity:.55;"' : ''; ?>>
            <?php echo csrfField(); ?>

            <!-- Wizard Header / Progress -->
            <div class="pds-progress-container mb-3">
                <div class="pds-progress-wrapper">
                    <div id="pdsProgressBar" class="pds-progress-bar" style="width:8.33%;"></div>
                </div>
                <div class="pds-progress-percent" id="pdsProgressPercent">8%</div>
            </div>

            <!-- Portal Tabs -->
            <div class="portal-tabs">
                <div class="portal-tab active" id="portal-tab-1" onclick="showPortal(1)">
                    <div class="portal-num">1</div>
                    <div class="portal-label-wrapper">
                        <span class="portal-label">Core Identity</span>
                        <div class="portal-sub-steps" id="portal-sub-1"></div>
                    </div>
                </div>
                <div class="portal-tab" id="portal-tab-2" onclick="showPortal(2)">
                    <div class="portal-num">2</div>
                    <div class="portal-label-wrapper">
                        <span class="portal-label">Background</span>
                        <div class="portal-sub-steps" id="portal-sub-2"></div>
                    </div>
                </div>
                <div class="portal-tab" id="portal-tab-3" onclick="showPortal(3)">
                    <div class="portal-num">3</div>
                    <div class="portal-label-wrapper">
                        <span class="portal-label">Qualifications</span>
                        <div class="portal-sub-steps" id="portal-sub-3"></div>
                    </div>
                </div>
                <div class="portal-tab" id="portal-tab-4" onclick="showPortal(4)">
                    <div class="portal-num">4</div>
                    <div class="portal-label-wrapper">
                        <span class="portal-label">Final</span>
                        <div class="portal-sub-steps" id="portal-sub-4"></div>
                    </div>
                </div>
            </div>

            <?php include __DIR__ . '/../includes/employee-form-steps.php'; ?>

            <!-- Sticky Wizard Footer -->
            <div class="wizard-footer mt-4">
                <button type="button" id="prevBtn" onclick="prevStep()" class="btn btn-outline-secondary px-4 shadow-sm" style="display:none;">
                    <i class="fas fa-arrow-left me-2"></i>Back
                </button>
                <div class="text-muted small d-none d-md-block" id="wizardProgressLabel">Portal 1 of 4 · Step 1 of 12</div>
                <div class="d-flex gap-2">
                    <button type="button" id="nextBtn" onclick="nextStep()" class="btn btn-primary px-4 shadow-sm">
                        Next <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                    <?php if (!$existingPending): ?>
                    <button type="submit" id="submitBtn" class="btn btn-warning px-4 shadow-sm" style="display:none;">
                        <i class="fas fa-paper-plane me-2"></i>Submit for Manager Review
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/assets/js/employee-form.js?v=<?php echo time(); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const step = parseInt(urlParams.get('step'), 10) || 1;
    showStep(step);
});
</script>



</div>
<?php require_once '../includes/footer.php'; ?>
