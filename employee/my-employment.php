<?php
$page_title = 'My Employment';
require_once '../includes/session-check.php';
checkRole(['Employee']);
require_once '../includes/functions.php';
ensureProbationaryEvaluationSchema($conn);

$employee_id = (int) ($_SESSION['employee_id'] ?? 0);
if ($employee_id <= 0) {
    redirectWith(BASE_URL . '/employee/dashboard.php', 'danger', 'No employment record is linked to your account.');
}
clearRegularizedEmployeeEligibility($conn, $employee_id);

$emp_stmt = $conn->prepare("SELECT e.*, d.department_name, b.branch_name, rc.rank_name,
        ed.height_m, ed.weight_kg, ed.blood_type, ed.citizenship,
        ec.mobile_number, ec.telephone_number, ec.personal_email
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN branches b ON e.branch_id = b.branch_id
    LEFT JOIN rank_categories rc ON e.rank_category_id = rc.rank_category_id
    LEFT JOIN employee_details ed ON e.employee_id = ed.employee_id
    LEFT JOIN employee_contacts ec ON e.employee_id = ec.employee_id
    WHERE e.employee_id = ? AND e.deleted_at IS NULL LIMIT 1");
$emp_stmt->bind_param('i', $employee_id);
$emp_stmt->execute();
$emp = $emp_stmt->get_result()->fetch_assoc();
$emp_stmt->close();
if (!$emp) {
    redirectWith(BASE_URL . '/employee/dashboard.php', 'danger', 'No employment record was found for your account.');
}

$correction_fields = [
    'name' => 'Name', 'position' => 'Position or rank', 'department' => 'Department or branch',
    'employment' => 'Employment status or type', 'hire_date' => 'Hire date',
    'contact' => 'Contact information', 'address' => 'Address',
    'emergency' => 'Emergency contact', 'personal' => 'Other personal detail',
];
$corrections_ready = ensureEmployeeProfileCorrectionRequests($conn);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'report_correction') {
    verifyCsrfToken();
    $field_key = (string) ($_POST['correction_field'] ?? '');
    $description = trim((string) ($_POST['correction_description'] ?? ''));
    $description = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $description);
    if (!$corrections_ready || !isset($correction_fields[$field_key])) {
        redirectWith(BASE_URL . '/employee/my-employment.php', 'danger', 'Unable to submit the correction report. Please contact HR directly.');
    }
    if (mb_strlen($description) < 15 || mb_strlen($description) > 1000) {
        redirectWith(BASE_URL . '/employee/my-employment.php', 'danger', 'Please describe the incorrect detail in 15 to 1,000 characters.');
    }
    $user_id = (int) $_SESSION['user_id'];
    $recipients = $conn->query("SELECT user_id, role FROM users WHERE role IN ('HR Staff', 'HR Manager') AND is_active = 1 AND deleted_at IS NULL ORDER BY CASE role WHEN 'HR Staff' THEN 0 ELSE 1 END");
    $hr_users = $recipients ? $recipients->fetch_all(MYSQLI_ASSOC) : [];
    if (!$hr_users) {
        redirectWith(BASE_URL . '/employee/my-employment.php', 'danger', 'No HR recipient is available. Please contact your HR office directly.');
    }
    $recent = $conn->prepare('SELECT request_id FROM employee_profile_correction_requests WHERE employee_id = ? AND submitted_by = ? AND field_key = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) LIMIT 1');
    $recent->bind_param('iis', $employee_id, $user_id, $field_key);
    $recent->execute();
    $already_reported = (bool) $recent->get_result()->fetch_assoc();
    $recent->close();
    if ($already_reported) {
        redirectWith(BASE_URL . '/employee/my-employment.php', 'info', 'You already reported this field within the last 24 hours. HR has the report.');
    }
    $insert = $conn->prepare('INSERT INTO employee_profile_correction_requests (employee_id, submitted_by, field_key, description) VALUES (?, ?, ?, ?)');
    $insert->bind_param('iiss', $employee_id, $user_id, $field_key, $description);
    $saved = $insert->execute();
    $request_id = (int) $insert->insert_id;
    $insert->close();
    if (!$saved) {
        redirectWith(BASE_URL . '/employee/my-employment.php', 'danger', 'The correction report could not be saved. Please contact HR directly.');
    }
    $name = trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''));
    foreach ($hr_users as $hr_user) {
        $hr_role = (string) $hr_user['role'];
        $link = BASE_URL . ($hr_role === 'HR Staff' ? '/staff/correction-request.php?id=' : '/manager/correction-request.php?id=') . $request_id;
        createNotification($conn, (int) $hr_user['user_id'], 'Employee Record Correction Reported',
            "{$name} (ID " . getEmployeeDisplayId($emp) . ") reported a possible issue with {$correction_fields[$field_key]}. Open the report to read the description and follow up through the HR process.",
            $link, $hr_role === 'HR Staff' ? 'records' : 'employees', 1);
    }
    logAudit($conn, $user_id, 'CREATE', 'EmployeeProfileCorrectionRequest', $request_id, 'Employee reported a possible issue with ' . $correction_fields[$field_key] . '.');
    redirectWith(BASE_URL . '/employee/my-employment.php', 'success', 'Your report was sent to HR. Your profile has not been changed.');
}

$correction_reports = [];
if ($corrections_ready) {
    $history_stmt = $conn->prepare('SELECT field_key, created_at FROM employee_profile_correction_requests WHERE employee_id = ? AND submitted_by = ? ORDER BY created_at DESC LIMIT 5');
    $user_id = (int) $_SESSION['user_id'];
    $history_stmt->bind_param('ii', $employee_id, $user_id);
    $history_stmt->execute();
    $correction_reports = $history_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $history_stmt->close();
}

$addr_stmt = $conn->prepare('SELECT * FROM employee_addresses WHERE employee_id = ?');
$addr_stmt->bind_param('i', $employee_id);
$addr_stmt->execute();
$addresses = [];
foreach ($addr_stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $addresses[$row['address_type']] = $row;
$addr_stmt->close();

$emerg_stmt = $conn->prepare('SELECT * FROM employee_emergency_contacts WHERE employee_id = ? ORDER BY is_primary DESC, emergency_id ASC');
$emerg_stmt->bind_param('i', $employee_id);
$emerg_stmt->execute();
$emergency_contacts = $emerg_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$emerg_stmt->close();

$cm_history = [];
$cm_check = $conn->query("SHOW TABLES LIKE 'career_movements'");
if ($cm_check && $cm_check->num_rows > 0) {
    $cm_stmt = $conn->prepare("SELECT cm.*, pb.branch_name AS from_branch_name, nb.branch_name AS to_branch_name
        FROM career_movements cm
        LEFT JOIN branches pb ON cm.previous_branch_id = pb.branch_id
        LEFT JOIN branches nb ON cm.new_branch_id = nb.branch_id
        WHERE cm.employee_id = ? AND cm.approval_status = 'Approved'
        ORDER BY cm.effective_date DESC, cm.created_at DESC");
    $cm_stmt->bind_param('i', $employee_id);
    $cm_stmt->execute();
    $cm_history = $cm_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $cm_stmt->close();
}

if (!function_exists('employmentValue')) {
    function employmentValue($value): string {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? 'Not provided' : $value;
    }
}
if (!function_exists('employmentAddress')) {
    function employmentAddress($address): string {
        if (!$address) return 'Not provided';
        $parts = [];
        foreach (['house_no', 'street', 'subdivision'] as $key) if (!empty($address[$key])) $parts[] = $address[$key];
        if (!empty($address['barangay'])) $parts[] = 'Brgy. ' . $address['barangay'];
        foreach (['city', 'province', 'zip_code'] as $key) if (!empty($address[$key])) $parts[] = $address[$key];
        return $parts ? implode(', ', $parts) : 'Not provided';
    }
}
if (!function_exists('employmentField')) {
    function employmentField(string $label, $value): void {
        echo '<div class="me-field"><dt>' . e($label) . '</dt><dd>' . e(employmentValue($value)) . '</dd></div>';
    }
}

$full_name = trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''));
$avatar = getEmployeeAvatar($emp['profile_picture'] ?? '');
require_once '../includes/header.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/my-employment.css">

<section class="me-identity" aria-labelledby="me-name">
    <img class="me-avatar" src="<?php echo e($avatar); ?>" alt="Profile photo of <?php echo e($full_name); ?>" loading="lazy">
    <div class="me-identity-main">
        <span class="me-eyebrow">Employee profile</span>
        <h1 id="me-name"><?php echo e($full_name); ?></h1>
        <p><?php echo e(employmentValue($emp['job_title'] ?? null)); ?> <span aria-hidden="true">·</span> <?php echo e(employmentValue($emp['rank_name'] ?? null)); ?> <span aria-hidden="true">·</span> <?php echo e(employmentValue($emp['department_name'] ?? null)); ?></p>
        <div class="me-badges">
            <span class="me-badge me-badge-status"><?php echo !empty($emp['is_active']) ? 'Active' : 'Inactive'; ?></span>
            <span class="me-badge"><?php echo e(employmentValue($emp['employment_status'] ?? null)); ?></span>
            <?php if (($emp['employment_status'] ?? '') === 'Probationary' && !empty($emp['probationary_regularization_eligible'])): ?><span class="me-badge">Eligible for regularization</span><?php endif; ?>
        </div>
        <div class="me-identity-meta"><span><i class="fas fa-id-badge" aria-hidden="true"></i> <?php echo e(getEmployeeDisplayId($emp)); ?></span><span><i class="fas fa-building" aria-hidden="true"></i> <?php echo e(employmentValue($emp['branch_name'] ?? null)); ?></span><span><i class="fas fa-calendar-alt" aria-hidden="true"></i> Hired <?php echo !empty($emp['hire_date']) ? e(formatDate($emp['hire_date'])) : 'Not provided'; ?></span></div>
    </div>
    <div class="me-identity-actions"><a href="<?php echo BASE_URL; ?>/employee/dashboard.php" class="me-back"><i class="fas fa-arrow-left" aria-hidden="true"></i> Back to Dashboard</a><small>Read-only profile</small></div>
</section>

<div class="me-tab-shell">
    <nav class="me-tabs" role="tablist" aria-label="My employment sections">
        <button id="me-tab-overview" type="button" role="tab" aria-controls="me-panel-overview" aria-selected="true" tabindex="0" data-me-tab="overview"><i class="fas fa-th-large" aria-hidden="true"></i> Overview</button>
        <button id="me-tab-employment" type="button" role="tab" aria-controls="me-panel-employment" aria-selected="false" tabindex="-1" data-me-tab="employment"><i class="fas fa-briefcase" aria-hidden="true"></i> Employment</button>
        <button id="me-tab-personal" type="button" role="tab" aria-controls="me-panel-personal" aria-selected="false" tabindex="-1" data-me-tab="personal"><i class="fas fa-user" aria-hidden="true"></i> Personal Information</button>
        <button id="me-tab-help" type="button" role="tab" aria-controls="me-panel-help" aria-selected="false" tabindex="-1" data-me-tab="help"><i class="fas fa-life-ring" aria-hidden="true"></i> Help & Corrections</button>
        <button id="me-tab-career" type="button" role="tab" aria-controls="me-panel-career" aria-selected="false" tabindex="-1" data-me-tab="career"><i class="fas fa-route" aria-hidden="true"></i> Career History</button>
    </nav>
</div>

<section id="me-panel-overview" class="me-panel" role="tabpanel" aria-labelledby="me-tab-overview" data-me-panel="overview">
    <div class="me-overview-grid">
        <article class="me-card"><h2><i class="fas fa-briefcase" aria-hidden="true"></i> Employment Summary</h2><dl class="me-fields">
            <?php employmentField('Position', $emp['job_title'] ?? null); employmentField('Department', $emp['department_name'] ?? null); employmentField('Employment Type', $emp['employment_type'] ?? null); employmentField('Employment Status', $emp['employment_status'] ?? null); ?>
        </dl></article>
        <article class="me-card"><h2><i class="fas fa-phone" aria-hidden="true"></i> Contact Details</h2><dl class="me-fields">
            <?php employmentField('Mobile', $emp['mobile_number'] ?? null); employmentField('Telephone', $emp['telephone_number'] ?? null); employmentField('Personal Email', $emp['personal_email'] ?? null); employmentField('Citizenship', $emp['citizenship'] ?? null); ?>
        </dl></article>
    </div>
    <?php if (($emp['employment_status'] ?? '') === 'Probationary'): ?>
    <article class="me-card me-probation-note"><h2><i class="fas fa-user-clock" aria-hidden="true"></i> Probationary Milestone</h2>
        <?php if (!empty($emp['probationary_regularization_eligible'])): ?><p>You are marked eligible for regularization. HR will confirm any official employment-status change.</p>
        <?php elseif (!empty($emp['probationary_next_evaluation_type']) && !empty($emp['probationary_next_evaluation_due_date'])): ?><p>Your <?php echo e($emp['probationary_next_evaluation_type']); ?> evaluation is due <?php echo e(formatDate($emp['probationary_next_evaluation_due_date'])); ?>.</p>
        <?php else: ?><p>Your next evaluation date is not recorded here yet. Check Self Rating or contact HR for the current schedule.</p><?php endif; ?>
        <a href="<?php echo BASE_URL; ?>/employee/self-rating.php">Go to Self Rating <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
    </article>
    <?php endif; ?>
    <article class="me-card me-preview"><div class="me-card-heading"><h2><i class="fas fa-route" aria-hidden="true"></i> Recent Career Activity</h2><button type="button" class="me-text-action" data-me-open="career">View history <i class="fas fa-arrow-right" aria-hidden="true"></i></button></div>
        <?php if (!$cm_history): ?><p class="me-empty">No approved career movements recorded yet.</p><?php else: ?><ol class="me-preview-list"><?php foreach (array_slice($cm_history, 0, 2) as $movement): ?><li><strong><?php echo e($movement['movement_type']); ?></strong><span><?php echo e(employmentValue($movement['new_position'] ?? null)); ?> · <?php echo !empty($movement['effective_date']) ? e(formatDate($movement['effective_date'])) : 'Date not provided'; ?></span></li><?php endforeach; ?></ol><?php endif; ?>
    </article>
</section>

<section id="me-panel-employment" class="me-panel" role="tabpanel" aria-labelledby="me-tab-employment" data-me-panel="employment" hidden>
    <div class="me-overview-grid">
        <article class="me-card"><h2><i class="fas fa-sitemap" aria-hidden="true"></i> Position & Assignment</h2><dl class="me-fields">
            <?php employmentField('Company ID', getEmployeeDisplayId($emp)); employmentField('Rank', $emp['rank_name'] ?? null); employmentField('Full Name', $full_name); employmentField('Position', $emp['job_title'] ?? null); employmentField('Department', $emp['department_name'] ?? null); employmentField('Branch', $emp['branch_name'] ?? null); ?>
        </dl></article>
        <article class="me-card"><h2><i class="fas fa-calendar-check" aria-hidden="true"></i> Employment Details</h2><dl class="me-fields">
            <?php employmentField('Hire Date', !empty($emp['hire_date']) ? formatDate($emp['hire_date']) : null); employmentField('Employment Status', $emp['employment_status'] ?? null); employmentField('Employment Type', $emp['employment_type'] ?? null); ?>
            <?php if (!empty($emp['probationary_next_evaluation_type'])) employmentField('Next Probationary Evaluation', $emp['probationary_next_evaluation_type']); ?>
            <?php if (!empty($emp['probationary_next_evaluation_due_date'])) employmentField('Next Evaluation Due', formatDate($emp['probationary_next_evaluation_due_date'])); ?>
        </dl></article>
    </div>
</section>

<section id="me-panel-personal" class="me-panel" role="tabpanel" aria-labelledby="me-tab-personal" data-me-panel="personal" hidden>
    <div class="me-overview-grid">
        <article class="me-card"><h2><i class="fas fa-phone" aria-hidden="true"></i> Contact Information</h2><dl class="me-fields"><?php employmentField('Mobile Number', $emp['mobile_number'] ?? null); employmentField('Telephone', $emp['telephone_number'] ?? null); employmentField('Personal Email', $emp['personal_email'] ?? null); ?></dl></article>
        <article class="me-card"><h2><i class="fas fa-user" aria-hidden="true"></i> Profile Summary</h2><dl class="me-fields">
            <?php employmentField('Citizenship', $emp['citizenship'] ?? null); employmentField('Civil Status', $emp['civil_status'] ?? null);
            $birth_display = null;
            if (!empty($emp['date_of_birth'])) {
                $birth_display = formatDate($emp['date_of_birth']) . ' (' . (new DateTime())->diff(new DateTime($emp['date_of_birth']))->y . ' years old)';
            }
            employmentField('Date of Birth', $birth_display); employmentField('Gender', $emp['gender'] ?? null); employmentField('Place of Birth', $emp['place_of_birth'] ?? null);
            employmentField('Height', !empty($emp['height_m']) ? $emp['height_m'] . ' m' : null); employmentField('Weight', !empty($emp['weight_kg']) ? $emp['weight_kg'] . ' kg' : null); employmentField('Blood Type', $emp['blood_type'] ?? null); employmentField('Account Status', !empty($emp['is_active']) ? 'Active' : 'Inactive'); ?>
        </dl></article>
        <article class="me-card"><h2><i class="fas fa-map-marker-alt" aria-hidden="true"></i> Addresses</h2><dl class="me-fields"><?php employmentField('Residential Address', employmentAddress($addresses['Residential'] ?? null)); employmentField('Permanent Address', employmentAddress($addresses['Permanent'] ?? null)); ?></dl></article>
        <article class="me-card"><h2><i class="fas fa-exclamation-circle" aria-hidden="true"></i> Emergency Contacts</h2>
            <?php if (!$emergency_contacts): ?><p class="me-empty">No emergency contacts listed.</p><?php else: ?><?php foreach ($emergency_contacts as $contact): ?><div class="me-contact"><strong><?php echo e(employmentValue($contact['contact_name'] ?? null)); ?><?php if (!empty($contact['is_primary'])): ?> <span class="me-primary">Primary</span><?php endif; ?></strong><span><?php echo e(employmentValue($contact['relationship'] ?? null)); ?> · <?php echo e(employmentValue($contact['contact_number'] ?? null)); ?></span></div><?php endforeach; ?><?php endif; ?>
        </article>
    </div>
</section>

<section id="me-panel-help" class="me-panel" role="tabpanel" aria-labelledby="me-tab-help" data-me-panel="help" hidden>
    <article class="me-card"><h2><i class="fas fa-life-ring" aria-hidden="true"></i> Help & Corrections</h2>
        <p class="me-help-intro">This profile is read-only. Report an incorrect or missing field to HR; HR will follow up and make any approved changes through its normal process.</p>
        <?php if ($corrections_ready): ?>
        <form method="post" action="<?php echo BASE_URL; ?>/employee/my-employment.php" class="me-correction-form">
            <?php echo csrfField(); ?><input type="hidden" name="action" value="report_correction">
            <label for="me-correction-field">Which field needs review?</label>
            <div class="me-correction-controls"><select id="me-correction-field" name="correction_field" required><option value="">Select a field</option><?php foreach ($correction_fields as $key => $label): ?><option value="<?php echo e($key); ?>"><?php echo e($label); ?></option><?php endforeach; ?></select></div>
            <label for="me-correction-description">What is incorrect?</label>
            <textarea id="me-correction-description" name="correction_description" rows="4" minlength="15" maxlength="1000" required placeholder="Describe the current detail and what HR should check. Do not include government ID numbers."></textarea>
            <small>15–1,000 characters. Do not include government ID numbers or attach documents here; HR will contact you if evidence is needed.</small>
            <button type="submit" class="me-correction-submit">Send report to HR</button>
        </form>
        <?php else: ?><p class="me-empty">In-app reporting is temporarily unavailable. Please contact your HR office directly.</p><?php endif; ?>
        <?php if ($correction_reports): ?><h3 class="me-report-heading">Recent reports</h3><ul class="me-report-list"><?php foreach ($correction_reports as $report): ?><li><span><?php echo e($correction_fields[$report['field_key']] ?? 'Profile detail'); ?></span><time><?php echo e(formatDateTime($report['created_at'])); ?></time></li><?php endforeach; ?></ul><?php endif; ?>
        <ol class="me-help-steps"><li>Review the field shown in Employment or Personal Information.</li><li>Send a report above. HR will contact you if supporting documents are needed.</li><li>Return after HR confirms a change to verify your updated record.</li></ol>
        <p class="me-help-security"><i class="fas fa-shield-alt" aria-hidden="true"></i> For government identifiers or other sensitive records, contact HR directly. Do not send full ID numbers through unsecured messages.</p>
    </article>
</section>

<section id="me-panel-career" class="me-panel" role="tabpanel" aria-labelledby="me-tab-career" data-me-panel="career" hidden>
    <article class="me-card"><h2><i class="fas fa-route" aria-hidden="true"></i> Career History</h2>
        <?php if (!$cm_history): ?><p class="me-empty">No approved career movements recorded yet.</p><?php else: ?><ol class="me-timeline">
            <?php foreach ($cm_history as $movement): ?><li><div class="me-timeline-date"><?php echo !empty($movement['effective_date']) ? e(formatDate($movement['effective_date'])) : 'Date not provided'; ?></div><div class="me-timeline-body"><h3><?php echo e(employmentValue($movement['movement_type'] ?? null)); ?></h3><p><span>Position:</span> <?php echo e(employmentValue($movement['previous_position'] ?? null)); ?> <span aria-hidden="true">→</span> <?php echo e(employmentValue($movement['new_position'] ?? null)); ?></p><?php if (!empty($movement['new_branch_id'])): ?><p><span>Branch:</span> <?php echo e(employmentValue($movement['from_branch_name'] ?? null)); ?> <span aria-hidden="true">→</span> <?php echo e(employmentValue($movement['to_branch_name'] ?? null)); ?></p><?php else: ?><p><span>Branch:</span> Same branch</p><?php endif; ?><p><span>Reason:</span> <?php echo e(employmentValue($movement['reason'] ?? null)); ?></p></div></li><?php endforeach; ?>
        </ol><?php endif; ?>
    </article>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const tabs = [...document.querySelectorAll('[data-me-tab]')];
    const panels = [...document.querySelectorAll('[data-me-panel]')];

    function activate(tab, focus = false) {
        tabs.forEach(item => {
            const active = item === tab;
            item.setAttribute('aria-selected', active ? 'true' : 'false');
            item.tabIndex = active ? 0 : -1;
        });
        panels.forEach(panel => { panel.hidden = panel.dataset.mePanel !== tab.dataset.meTab; });
        if (focus) tab.focus();
    }
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => activate(tab));
        tab.addEventListener('keydown', event => {
            let next = null;
            if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
            if (event.key === 'ArrowLeft') next = (index - 1 + tabs.length) % tabs.length;
            if (event.key === 'Home') next = 0;
            if (event.key === 'End') next = tabs.length - 1;
            if (next !== null) { event.preventDefault(); activate(tabs[next], true); }
        });
    });
    document.querySelectorAll('[data-me-open]').forEach(button => button.addEventListener('click', () => {
        const tab = tabs.find(item => item.dataset.meTab === button.dataset.meOpen);
        if (tab) { activate(tab, true); tab.scrollIntoView({block: 'nearest', inline: 'nearest'}); }
    }));

});
</script>
<?php require_once '../includes/footer.php'; ?>
