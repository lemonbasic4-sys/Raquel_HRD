<?php
$page_title = 'View Employee';
require_once '../includes/session-check.php';
checkRole(['HR Staff']);
require_once '../includes/functions.php';

$eid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($eid <= 0) redirectWith(BASE_URL . '/staff/search-employees.php', 'danger', 'Invalid employee ID.');

// Fetch employee details (no branch restriction for staff — read-only access to all)
$stmt = $conn->prepare("SELECT e.*, b.branch_name, d.department_name,
    ed.height_m, ed.weight_kg, ed.blood_type, ed.citizenship,
    eg.sss_number, eg.philhealth_number, eg.pagibig_number, eg.tin_number,
    ec.telephone_number, ec.mobile_number, ec.personal_email
    FROM employees e 
    LEFT JOIN branches b ON e.branch_id = b.branch_id 
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN employee_details ed ON e.employee_id = ed.employee_id
    LEFT JOIN employee_government_ids eg ON e.employee_id = eg.employee_id
    LEFT JOIN employee_contacts ec ON e.employee_id = ec.employee_id
    WHERE e.employee_id = ? AND e.is_active = 1");
$stmt->bind_param("i", $eid);
$stmt->execute();
$emp = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$emp) redirectWith(BASE_URL . '/staff/search-employees.php', 'danger', 'Employee not found.');

// Load Addresses
$res_addr = $conn->query("SELECT * FROM employee_addresses WHERE employee_id=$eid AND address_type='Residential'")->fetch_assoc();
$perm_addr = $conn->query("SELECT * FROM employee_addresses WHERE employee_id=$eid AND address_type='Permanent'")->fetch_assoc();

$addr_fields = ['region', 'house_no', 'street', 'subdivision', 'barangay', 'city', 'province', 'zip_code'];
foreach ($addr_fields as $f) {
    $emp['res_' . $f] = $res_addr[$f] ?? '';
    $emp['perm_' . $f] = $perm_addr[$f] ?? '';
}

// Load Emergency Contacts
$emerg = $conn->query("SELECT * FROM employee_emergency_contacts WHERE employee_id=$eid LIMIT 1")->fetch_assoc();
if ($emerg) {
    $emp['emergency_contact_name'] = $emerg['contact_name'];
    $emp['emergency_contact_relationship'] = $emerg['relationship'];
    $emp['emergency_contact_number'] = $emerg['contact_number'];
} else {
    $emp['emergency_contact_name'] = $emp['emergency_contact_relationship'] = $emp['emergency_contact_number'] = '';
}

// Load Education, Work, Skills, Trainings (Read Only)
$education = $conn->query("SELECT * FROM employee_education WHERE employee_id=$eid ORDER BY education_id")->fetch_all(MYSQLI_ASSOC);
$work = $conn->query("SELECT * FROM employee_work_experience WHERE employee_id=$eid ORDER BY work_id")->fetch_all(MYSQLI_ASSOC);
$skills = $conn->query("SELECT * FROM employee_skills WHERE employee_id=$eid")->fetch_all(MYSQLI_ASSOC);
$trainings = $conn->query("SELECT * FROM employee_trainings WHERE employee_id=$eid ORDER BY training_id")->fetch_all(MYSQLI_ASSOC);

// Read-only approved records for the Performance & Career tabs.
$perf_history_q = $conn->prepare("
    SELECT evaluation_id, total_score, performance_level, approved_date,
           COALESCE(approved_date, evaluation_period_end) AS display_date, evaluation_type
    FROM evaluations
    WHERE employee_id = ? AND status = 'Approved'
    ORDER BY COALESCE(approved_date, evaluation_period_end) ASC
");
$perf_history_q->bind_param("i", $eid);
$perf_history_q->execute();
$perf_history_data = $perf_history_q->get_result()->fetch_all(MYSQLI_ASSOC);
$perf_history_q->close();
$chart_labels = array_map(fn($row) => date('M Y', strtotime($row['display_date'])) . ' (' . ($row['evaluation_type'] ?? 'Eval') . ')', $perf_history_data);
$chart_scores = array_map(fn($row) => (float)$row['total_score'], $perf_history_data);
$avg_5yr_score = $perf_history_data ? round(array_sum($chart_scores) / count($chart_scores), 2) : 0;
$classification = 'No Evaluation Record';
$class_badge = 'bg-secondary';
if ($perf_history_data) {
    $difference = end($chart_scores) - reset($chart_scores);
    if ($avg_5yr_score >= 3.60) { $classification = 'Consistently Outstanding'; $class_badge = 'bg-success'; }
    elseif ($difference >= 0.30) { $classification = 'Improving Performance'; $class_badge = 'bg-info text-dark'; }
    elseif ($difference <= -0.30) { $classification = 'Declining / Needing Intervention'; $class_badge = 'bg-danger'; }
    else { $classification = 'Stable Performance'; $class_badge = 'bg-primary'; }
}

$cm_history = [];
$cm_check = $conn->query("SHOW TABLES LIKE 'career_movements'");
if ($cm_check && $cm_check->num_rows > 0) {
    $cm_stmt = $conn->prepare("
        SELECT cm.*, pb.branch_name AS from_branch_name, nb.branch_name AS to_branch_name,
               u1.full_name AS logged_by_name, u2.full_name AS approved_by_name
        FROM career_movements cm
        LEFT JOIN branches pb ON cm.previous_branch_id = pb.branch_id
        LEFT JOIN branches nb ON cm.new_branch_id = nb.branch_id
        LEFT JOIN users u1 ON cm.logged_by = u1.user_id
        LEFT JOIN users u2 ON cm.approved_by = u2.user_id
        WHERE cm.employee_id = ? AND cm.approval_status = 'Approved'
        ORDER BY cm.effective_date DESC, cm.created_at DESC
    ");
    $cm_stmt->bind_param("i", $eid);
    $cm_stmt->execute();
    $cm_history = $cm_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $cm_stmt->close();
}

require_once '../includes/header.php';
echo '<link rel="stylesheet" href="' . BASE_URL . '/assets/css/employee-profile-reference.css">';

// Helper for UI
function field($label, $value, $escape = true) {
    $is_company_id = strcasecmp($label, 'Company ID') === 0;
    $val = !empty($value) ? ($escape ? e($value) : $value) : '<span class="text-muted">N/A</span>';
    $label_class = $is_company_id ? 'company-id-text detail-label' : 'detail-label';
    $value_class = $is_company_id ? 'company-id-value detail-value' : 'detail-value';
    return "<div class='detail-item'><div class='$label_class'>$label</div><div class='$value_class'>$val</div></div>";
}
function govField($label, $value)
{
    $has_val = !empty(trim((string)$value));
    $raw = $has_val ? e(trim($value)) : '<span class="text-muted">N/A</span>';
    $masked = $has_val ? '••••••••••••' : '<span class="text-muted">N/A</span>';
    $eye_btn = $has_val ? '<i class="fas fa-eye text-muted cursor-pointer single-id-toggle ms-auto" onclick="toggleSingleId(this)" title="Toggle '.$label.'" style="font-size:0.82rem; opacity: 0.55; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.opacity=\'1\'" onmouseout="this.style.opacity=\'0.55\'"></i>' : '';

    return "<div class='detail-item'>
        <div class='detail-label'>$label</div>
        <div class='detail-value d-flex align-items-center gap-2'>
            <span class='gov-id-val' data-raw='$raw' data-masked='$masked'>$masked</span>
            $eye_btn
        </div>
    </div>";
}
?>

<?php
$fullName = $emp['first_name'] . ' ' . ($emp['middle_name'] ? $emp['middle_name'] . ' ' : '') . $emp['last_name'];
$resAddr = trim(implode(', ', array_filter([$emp['res_house_no'], $emp['res_street'], $emp['res_subdivision'] ?? '', $emp['res_barangay'], $emp['res_city'], $emp['res_province'], $emp['res_zip_code'] ?? ''])));
$permAddr = trim(implode(', ', array_filter([$emp['perm_house_no'], $emp['perm_street'], $emp['perm_subdivision'] ?? '', $emp['perm_barangay'], $emp['perm_city'], $emp['perm_province'], $emp['perm_zip_code'] ?? ''])));
?>

<style>
@media (min-width: 992px) {
    .profile-sticky-col {
        position: sticky;
        top: calc(var(--header-height) + 18px);
        align-self: flex-start;
    }
}

.employee-page-title {
    font-size: 1.65rem;
    font-weight: 700;
    color: var(--text-dark);
}

.employee-profile-card,
.employee-section-card {
    border: 1px solid rgba(15, 23, 42, 0.08);
    box-shadow: 0 14px 30px rgba(15, 23, 42, 0.06);
    overflow: hidden;
}

.employee-section-header {
    padding: 1.25rem 1.5rem 0;
}

.employee-section-kicker {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--primary-blue);
    margin-bottom: 0.4rem;
}

.employee-section-card .card-body,
.employee-profile-card .card-body {
    padding: 1.5rem;
}

.performance-career-panel { animation: performance-career-panel-in 0.2s ease-out; }
@keyframes performance-career-panel-in { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }

.employee-subsection {
    border: 1px solid #edf2f7;
    border-radius: 16px;
    background: #fbfcfe;
    padding: 1.15rem;
    margin-bottom: 1rem;
}

.employee-subsection:last-child {
    margin-bottom: 0;
}

.employee-subsection-title {
    font-size: 0.82rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-muted);
    margin-bottom: 0.9rem;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 0.9rem;
    min-width: 0;
}

/* Contact Channels: Telephone + Mobile side by side, Email full width below */
.contact-channels-grid {
    grid-template-columns: 1fr 1fr;
}

.contact-channels-grid .detail-item:last-child {
    grid-column: 1 / -1;
}

.detail-item {
    padding: 0.9rem 1rem;
    border-radius: 14px;
    border: 1px solid #edf2f7;
    background: #fff;
    min-width: 0;
    overflow: hidden;
}

.detail-label {
    color: var(--text-muted);
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    margin-bottom: 0.4rem;
}

.detail-value {
    color: var(--text-dark);
    font-size: 0.95rem;
    font-weight: 600;
    line-height: 1.45;
    word-break: break-word;
    overflow-wrap: anywhere;
}

.profile-meta-list {
    display: grid;
    gap: 0.85rem;
    min-width: 0;
}

.profile-meta-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    text-align: left;
    padding: 0.8rem 0.9rem;
    border-radius: 14px;
    background: #f8fafc;
    border: 1px solid #edf2f7;
    min-width: 0;
}

.profile-meta-item > div {
    min-width: 0;
    flex: 1;
    overflow: hidden;
}

.profile-meta-icon {
    width: 2rem;
    height: 2rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(13, 110, 253, 0.12);
    color: var(--primary-blue);
    flex-shrink: 0;
}

.profile-meta-label {
    display: block;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted);
}

.profile-meta-value {
    display: block;
    font-size: 0.92rem;
    font-weight: 600;
    color: var(--text-dark);
    line-height: 1.4;
    word-break: break-word;
    overflow-wrap: anywhere;
}

.profile-email-value {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.badge-cloud {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.employee-table-wrap {
    border: 1px solid #edf2f7;
    border-radius: 16px;
    overflow-x: auto;
    overflow-y: hidden;
    background: #fff;
    -webkit-overflow-scrolling: touch;
}

.employee-table-wrap .table {
    margin-bottom: 0;
}

.employee-table-wrap thead th {
    border-bottom: 1px solid #edf2f7;
    background: #f8fafc;
    color: var(--text-muted);
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.empty-state {
    border: 1px dashed #d7e0ea;
    border-radius: 16px;
    padding: 2rem 1.25rem;
    text-align: center;
    color: var(--text-muted);
    background: #fbfcfe;
}

.empty-state i {
    font-size: 1.9rem;
    opacity: 0.35;
    margin-bottom: 0.75rem;
}

.empty-state p {
    margin-bottom: 0;
}

@media (max-width: 767.98px) {
    .employee-section-header {
        padding: 1.1rem 1.1rem 0;
    }

    .employee-section-card .card-body,
    .employee-profile-card .card-body {
        padding: 1.1rem;
    }

    .employee-table-wrap,
    .employee-table-wrap table,
    .employee-table-wrap tbody,
    .employee-table-wrap tr,
    .employee-table-wrap td {
        display: block;
        width: 100%;
    }

    .employee-table-wrap thead {
        display: none;
    }

    .employee-table-wrap tr {
        padding: 1rem;
        border-bottom: 1px solid #edf2f7;
    }

    .employee-table-wrap tr:last-child {
        border-bottom: none;
    }

    .employee-table-wrap td {
        border: none !important;
        padding: 0.45rem 0;
    }

    .employee-table-wrap td::before {
        content: attr(data-label);
        display: block;
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
        margin-bottom: 0.2rem;
    }
}
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1"><i class="fas fa-lock me-1"></i>Employee Profile (Read Only)</p>
        <h1 class="employee-page-title mb-0">Employee Information</h1>
    </div>
    <a href="<?php echo BASE_URL; ?>/staff/search-employees.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to Search
    </a>
</div>

<?php
$heroHireDate = !empty($emp['hire_date']) ? new DateTime($emp['hire_date']) : null;
$heroTenure = $heroHireDate ? (($diff = $heroHireDate->diff(new DateTime()))->y . ' years ' . $diff->m . ' months') : 'N/A';
?>
<div class="employee-reference-hero">
    <div class="employee-reference-identity">
        <img src="<?php echo getEmployeeAvatar($emp['profile_picture']); ?>" class="employee-reference-avatar" alt="Employee profile photo">
        <div class="employee-reference-copy">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h2><?php echo e($fullName); ?></h2>
                <span class="badge rounded-pill bg-success-subtle text-success"><i class="fas fa-circle me-1" style="font-size:.45rem;vertical-align:middle;"></i>Active</span>
            </div>
            <p><?php echo e($emp['job_title'] ?? 'N/A'); ?></p>
            <p><?php echo e($emp['branch_name'] ?: 'N/A'); ?></p>
            <div class="employee-reference-contact">
                <span><i class="fas fa-id-badge me-1"></i><?php echo e($emp['employee_code'] ?: 'N/A'); ?></span>
                <span><i class="fas fa-envelope me-1"></i><?php echo e($emp['personal_email'] ?: 'N/A'); ?></span>
                <span><i class="fas fa-phone me-1"></i><?php echo e($emp['mobile_number'] ?: 'N/A'); ?></span>
            </div>
        </div>
        <div class="employee-reference-summary">
            <div class="employee-reference-summary-item"><small><i class="fas fa-briefcase me-1"></i>Hire Date</small><strong><?php echo formatDate($emp['hire_date']); ?></strong></div>
            <div class="employee-reference-summary-item"><small><i class="fas fa-calendar me-1"></i>Tenure</small><strong><?php echo e($heroTenure); ?></strong></div>
            <div class="employee-reference-summary-item"><small><i class="fas fa-building me-1"></i>Department</small><strong><?php echo e($emp['department_name'] ?: 'N/A'); ?></strong></div>
        </div>
    </div>
</div>

<nav class="employee-information-tabs" role="tablist" aria-label="Employee information sections">
    <button class="employee-information-tab" type="button" role="tab" aria-selected="true" tabindex="0" data-profile-tab="all"><i class="fas fa-th-large"></i>All Info</button>
    <button class="employee-information-tab" type="button" role="tab" aria-selected="false" tabindex="-1" data-profile-tab="personal"><i class="fas fa-user"></i>Personal Info</button>
    <button class="employee-information-tab" type="button" role="tab" aria-selected="false" tabindex="-1" data-profile-tab="employment"><i class="fas fa-briefcase"></i>Employment</button>
    <button class="employee-information-tab" type="button" role="tab" aria-selected="false" tabindex="-1" data-profile-tab="contact"><i class="fas fa-envelope"></i>Contact</button>
    <button class="employee-information-tab" type="button" role="tab" aria-selected="false" tabindex="-1" data-profile-tab="education"><i class="fas fa-graduation-cap"></i>Education</button>
    <button class="employee-information-tab" type="button" role="tab" aria-selected="false" tabindex="-1" data-profile-tab="training"><i class="fas fa-certificate"></i>Skills &amp; Training</button>
    <button class="employee-information-tab" type="button" role="tab" aria-selected="false" tabindex="-1" data-profile-tab="performance"><i class="fas fa-chart-line"></i>Performance</button>
    <button class="employee-information-tab" type="button" role="tab" aria-selected="false" tabindex="-1" data-profile-tab="documents"><i class="fas fa-folder"></i>Documents</button>
    <button class="employee-information-tab" type="button" role="tab" aria-selected="false" tabindex="-1" data-profile-tab="timeline"><i class="fas fa-calendar"></i>Profile Audit Trail</button>
</nav>

<div class="row g-4">
    <div class="col-lg-4 col-xl-3 profile-sticky-col legacy-profile-rail">
        <div class="content-card employee-profile-card h-100 text-center">
            <div class="card-body py-4">
                <img src="<?php echo getEmployeeAvatar($emp['profile_picture']); ?>" class="rounded-circle img-thumbnail shadow-sm mb-3" style="width:120px;height:120px;object-fit:cover;">
                <h5 class="mb-1"><?php echo e($fullName); ?></h5>
                <p class="text-muted mb-2"><?php echo e($emp['job_title']); ?></p>
                <p class="company-id-text small mb-3">Company ID: <span class="company-id-value"><?php echo e($emp['employee_code'] ?: 'N/A'); ?></span></p>
                <div class="d-flex justify-content-center flex-wrap gap-2 mb-3">
                    <span class="badge bg-success px-3 py-2">Active</span>
                    <?php if (!empty($emp['employment_status'])): ?>
                        <span class="badge bg-primary px-3 py-2"><?php echo e($emp['employment_status']); ?></span>
                    <?php endif; ?>
                </div>
                <div class="profile-meta-list">
                    <div class="profile-meta-item">
                        <span class="profile-meta-icon"><i class="fas fa-envelope"></i></span>
                        <div>
                            <span class="profile-meta-label">Email</span>
                            <span class="profile-meta-value profile-email-value"><?php echo e($emp['personal_email'] ?: 'N/A'); ?></span>
                        </div>
                    </div>
                    <div class="profile-meta-item">
                        <span class="profile-meta-icon"><i class="fas fa-phone"></i></span>
                        <div>
                            <span class="profile-meta-label">Mobile</span>
                            <span class="profile-meta-value"><?php echo e($emp['mobile_number'] ?: 'N/A'); ?></span>
                        </div>
                    </div>
                    <div class="profile-meta-item">
                        <span class="profile-meta-icon"><i class="fas fa-building"></i></span>
                        <div>
                            <span class="profile-meta-label">Branch</span>
                            <span class="profile-meta-value"><?php echo e($emp['branch_name'] ?: 'N/A'); ?></span>
                        </div>
                    </div>
                    <div class="profile-meta-item">
                        <span class="profile-meta-icon"><i class="fas fa-sitemap"></i></span>
                        <div>
                            <span class="profile-meta-label">Department</span>
                            <span class="profile-meta-value"><?php echo e($emp['department_name'] ?: 'N/A'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="content-card employee-section-card mb-4">
            <div class="employee-section-header">
                <div><div class="employee-section-kicker"><i class="fas fa-chart-line text-warning"></i>Employee Insights</div><h5 class="mb-0">Performance &amp; Career</h5></div>
            </div>
            <div class="performance-career-panel" id="performance-panel">
                <div class="employee-section-header">
                    <div><div class="employee-section-kicker"><i class="fas fa-chart-line text-warning"></i>Performance Analytics</div><h5 class="mb-0">5-Year Historical Performance Trend</h5></div>
                    <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end"><span class="badge <?php echo $class_badge; ?> px-3 py-2" style="font-size:0.82rem;"><i class="fas fa-robot me-1"></i><?php echo $classification; ?></span><span class="badge bg-dark text-warning px-3 py-2" style="font-size:0.85rem;">Avg Score: <?php echo number_format($avg_5yr_score, 2); ?> / 4.00</span></div>
                </div>
                <div class="card-body">
                    <?php if (empty($perf_history_data)): ?>
                        <div class="empty-state py-4"><i class="fas fa-chart-line"></i><p>No approved performance evaluation history recorded for this employee yet.</p></div>
                    <?php else: ?>
                        <div class="row align-items-center g-3 mb-3">
                            <div class="col-lg-8"><div style="height:220px;position:relative;"><canvas id="empPerformanceTrendChartStaff"></canvas></div></div>
                            <div class="col-lg-4 border-start"><h6 class="fw-bold small text-muted uppercase mb-3">Evaluation History Summary</h6><div class="d-grid gap-2">
                                <?php foreach (array_reverse($perf_history_data) as $phItem): $scoreVal = (float)$phItem['total_score']; $lvl = $phItem['performance_level'] ?? getPerformanceLevel($scoreVal); $lvlBadge = $scoreVal >= 3.6 ? 'bg-success' : ($scoreVal >= 2.6 ? 'bg-info text-dark' : ($scoreVal >= 2.0 ? 'bg-warning text-dark' : 'bg-danger')); ?>
                                    <div class="p-2 bg-light rounded-3 d-flex justify-content-between align-items-center"><div><div class="fw-bold small"><?php echo date('F d, Y', strtotime($phItem['display_date'])); ?></div><div class="text-muted" style="font-size:0.72rem;"><?php echo e($phItem['evaluation_type'] ?? 'Annual'); ?> Evaluation</div></div><div class="text-end"><span class="badge <?php echo $lvlBadge; ?>"><?php echo number_format($scoreVal, 2); ?></span><div class="text-muted" style="font-size:0.68rem;"><?php echo e($lvl); ?></div></div></div>
                                <?php endforeach; ?>
                            </div></div>
                        </div>
                        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                        <script>document.addEventListener('DOMContentLoaded', function () { const ctx = document.getElementById('empPerformanceTrendChartStaff').getContext('2d'); new Chart(ctx, { type: 'line', data: { labels: <?php echo json_encode($chart_labels); ?>, datasets: [{ label: 'Evaluation Score (1.00 - 4.00)', data: <?php echo json_encode($chart_scores); ?>, borderColor: '#BD9414', backgroundColor: 'rgba(189, 148, 20, 0.15)', borderWidth: 3, fill: true, tension: 0.35, pointBackgroundColor: '#294306', pointRadius: 5, pointHoverRadius: 7 }] }, options: { responsive: true, maintainAspectRatio: false, scales: { y: { min: 1.0, max: 4.0, ticks: { stepSize: 0.5 } } }, plugins: { legend: { display: false } } } }); });</script>
                    <?php endif; ?>
                </div>
            </div>
            <div class="performance-career-panel" id="career-panel">
                <div class="employee-section-header"><div><div class="employee-section-kicker"><i class="fas fa-route text-warning me-1"></i>Career Progression</div><h5 class="mb-0">Career Movement History</h5></div><span class="badge bg-secondary px-3 py-2"><?php echo count($cm_history); ?> Record<?php echo count($cm_history) !== 1 ? 's' : ''; ?></span></div>
                <div class="card-body">
                    <?php if (empty($cm_history)): ?>
                        <div class="empty-state"><i class="fas fa-route d-block mb-2" style="font-size:1.8rem;opacity:.3;"></i><p class="mb-0 text-muted">No approved career movements on record for this employee.</p></div>
                    <?php else: ?>
                        <div class="employee-table-wrap"><table class="table table-sm align-middle mb-0"><thead><tr><th>Effective Date</th><th>Type</th><th>From Position</th><th>To Position</th><th>From Branch</th><th>To Branch</th><th>Processed By</th><th>Reason</th></tr></thead><tbody>
                        <?php foreach ($cm_history as $cm): $typeBadge = match($cm['movement_type']) { 'Promotion' => 'bg-success', 'Transfer' => 'bg-info text-dark', 'Demotion' => 'bg-danger', 'Role Change' => 'bg-primary', default => 'bg-secondary' }; ?>
                            <tr><td data-label="Effective Date" class="fw-semibold"><?php echo formatDate($cm['effective_date']); ?></td><td data-label="Type"><span class="badge <?php echo $typeBadge; ?>"><?php echo e($cm['movement_type']); ?></span></td><td data-label="From Position" class="text-muted small"><?php echo e($cm['previous_position'] ?: '—'); ?></td><td data-label="To Position" class="fw-bold text-success"><?php echo e($cm['new_position']); ?></td><td data-label="From Branch" class="text-muted small"><?php echo e($cm['from_branch_name'] ?: 'N/A'); ?></td><td data-label="To Branch" class="fw-semibold"><?php echo e($cm['to_branch_name'] ?: 'Same Branch'); ?></td><td data-label="Processed By" class="small"><?php echo e($cm['approved_by_name'] ?: ($cm['logged_by_name'] ?: 'HR Manager')); ?></td><td data-label="Reason" class="small"><?php echo e($cm['reason'] ?: 'N/A'); ?></td></tr>
                        <?php endforeach; ?>
                        </tbody></table></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-xl-6">
                <div class="content-card employee-section-card h-100">
                    <div class="employee-section-header">
                        <div>
                            <div class="employee-section-kicker"><i class="fas fa-user"></i>Personal</div>
                            <h5 class="mb-0">Personal Information</h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="detail-grid">
                            <?php
                            echo field('Full Name', $fullName);
                            $age = '';
                            if (!empty($emp['date_of_birth'])) {
                                $dob = new DateTime($emp['date_of_birth']);
                                $today = new DateTime();
                                $age = $today->diff($dob)->y;
                            }
                            echo field('Date of Birth', $emp['date_of_birth'] ? formatDate($emp['date_of_birth']) . " ($age years old)" : '');
                            echo field('Place of Birth', $emp['place_of_birth']);
                            echo field('Gender', $emp['gender']);
                            echo field('Civil Status', $emp['civil_status']);
                            echo field('Citizenship', $emp['citizenship']);
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="content-card employee-section-card h-100">
                    <div class="employee-section-header">
                        <div>
                            <div class="employee-section-kicker"><i class="fas fa-address-card"></i>Contact</div>
                            <h5 class="mb-0">Contact & Address</h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="employee-subsection">
                            <div class="employee-subsection-title">Contact Channels</div>
                            <div class="detail-grid contact-channels-grid">
                                <?php
                                echo field('Telephone', $emp['telephone_number']);
                                echo field('Mobile', $emp['mobile_number']);
                                echo field('Email', $emp['personal_email']);
                                ?>
                            </div>
                        </div>
                        <div class="employee-subsection">
                            <div class="employee-subsection-title">Residential Address</div>
                            <div class="detail-grid"><?php echo field('Address', $resAddr); ?></div>
                        </div>
                        <div class="employee-subsection">
                            <div class="employee-subsection-title">Permanent Address</div>
                            <div class="detail-grid"><?php echo field('Address', $permAddr); ?></div>
                        </div>
                        <div class="employee-subsection">
                            <div class="employee-subsection-title">Emergency Contacts</div>
                            <?php 
                            $emergContacts = $conn->query("SELECT * FROM employee_emergency_contacts WHERE employee_id=$eid ORDER BY is_primary DESC, emergency_id ASC")->fetch_all(MYSQLI_ASSOC);
                            if (!empty($emergContacts)):
                                foreach ($emergContacts as $c):
                            ?>
                                <div class="detail-grid mb-3 pb-2 <?php echo $c['is_primary'] ? 'border-start border-3 border-warning ps-2' : ''; ?>" style="grid-gap: 8px;">
                                    <?php
                                    echo field('Name', e($c['contact_name']) . ($c['is_primary'] ? ' <span class="badge bg-warning text-dark ms-1" style="font-size:0.68rem; padding: 2px 6px;"><i class="fas fa-star"></i> Primary</span>' : ''), false);
                                    echo field('Relationship', e($c['relationship']));
                                    echo field('Number', e($c['contact_number']));
                                    ?>
                                </div>
                            <?php 
                                endforeach;
                            else:
                                echo '<p class="text-muted small">No emergency contacts listed.</p>';
                            endif;
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="content-card employee-section-card h-100">
                    <div class="employee-section-header">
                        <div>
                            <div class="employee-section-kicker"><i class="fas fa-briefcase"></i>Employment</div>
                            <h5 class="mb-0">Employment Details</h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="employee-subsection">
                            <div class="employee-subsection-title">Employment Profile</div>
                            <div class="detail-grid">
                                <?php
                                echo field('Company ID', $emp['employee_code']);
                                echo field('Department', $emp['department_name']);
                                echo field('Job Title', $emp['job_title']);
                                echo field('Branch', $emp['branch_name']);
                                echo field('Employment Status', $emp['employment_status']);
                                echo field('Employment Type', $emp['employment_type']);
                                echo field('Date Hired', formatDate($emp['hire_date']));
                                ?>
                            </div>
                        </div>
                        <div class="employee-subsection">
                            <div class="employee-subsection-title d-flex align-items-center justify-content-between">
                                <span>Government IDs</span>
                                <button type="button" class="btn btn-sm btn-light border py-1 px-2 rounded-pill shadow-none" id="toggleGovIdsBtn" onclick="toggleGovIds()" style="font-size: 0.75rem; font-weight: 600; color: #475569; background: #f8fafc; transition: all 0.2s ease;" title="Toggle Confidential IDs">
                                    <i class="fas fa-eye me-1" id="govIdsEyeIcon" style="color: #64748b;"></i><span id="govIdsBtnText">Show IDs</span>
                                </button>
                            </div>
                            <div class="detail-grid">
                                <?php
                                echo govField('SSS Number', $emp['sss_number'] ?? '');
                                echo govField('PhilHealth Number', $emp['philhealth_number'] ?? '');
                                echo govField('Pag-IBIG Number', $emp['pagibig_number'] ?? '');
                                echo govField('TIN Number', $emp['tin_number'] ?? '');
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="content-card employee-section-card h-100">
                    <div class="employee-section-header">
                        <div>
                            <div class="employee-section-kicker"><i class="fas fa-graduation-cap"></i>Education</div>
                            <h5 class="mb-0">Education Background</h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($education)): ?>
                            <div class="empty-state">
                                <i class="fas fa-graduation-cap d-block"></i>
                                <p>No education records available.</p>
                            </div>
                        <?php else: ?>
                            <div class="employee-table-wrap">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Level</th>
                                            <th>School</th>
                                            <th>Degree</th>
                                            <th>Year</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($education as $ed): ?>
                                            <tr>
                                                <td data-label="Level"><?php echo e($ed['education_level']); ?></td>
                                                <td data-label="School"><?php echo e($ed['school_name']); ?></td>
                                                <td data-label="Degree"><?php echo e($ed['degree_course'] ?: 'N/A'); ?></td>
                                                <td data-label="Year"><?php echo e($ed['year_graduated'] ?: 'N/A'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="content-card employee-section-card">
                    <div class="employee-section-header">
                        <div>
                            <div class="employee-section-kicker"><i class="fas fa-history"></i>Work</div>
                            <h5 class="mb-0">Work History</h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($work)): ?>
                            <div class="empty-state">
                                <i class="fas fa-briefcase d-block"></i>
                                <p>No work history records available.</p>
                            </div>
                        <?php else: ?>
                            <div class="employee-table-wrap">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Period</th>
                                            <th>Position</th>
                                            <th>Company</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($work as $w): ?>
                                            <tr>
                                                <td data-label="Period"><?php echo formatDate($w['date_from'], 'Y') . ' - ' . ($w['date_to'] ? formatDate($w['date_to'], 'Y') : 'Present'); ?></td>
                                                <td data-label="Position"><?php echo e($w['job_title']); ?></td>
                                                <td data-label="Company"><?php echo e($w['company_name']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="content-card employee-section-card">
                    <div class="employee-section-header">
                        <div>
                            <div class="employee-section-kicker"><i class="fas fa-certificate"></i>Development</div>
                            <h5 class="mb-0">Skills & Training</h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-lg-5">
                                <div class="employee-subsection h-100">
                                    <div class="employee-subsection-title">Skills</div>
                                    <?php if (empty($skills)): ?>
                                        <div class="empty-state">
                                            <i class="fas fa-star d-block"></i>
                                            <p>No skills recorded.</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="badge-cloud">
                                            <?php foreach($skills as $sk): ?>
                                                <span class="badge bg-info"><?php echo e($sk['skill_name']); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-lg-7">
                                <div class="employee-subsection h-100">
                                    <div class="employee-subsection-title">Trainings & Seminars</div>
                                    <?php if (empty($trainings)): ?>
                                        <div class="empty-state">
                                            <i class="fas fa-chalkboard-teacher d-block"></i>
                                            <p>No training records available.</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="employee-table-wrap">
                                            <table class="table table-sm align-middle mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Training</th>
                                                        <th>Date</th>
                                                        <th>Type</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach($trainings as $tr): ?>
                                                        <tr>
                                                            <td data-label="Training"><?php echo e($tr['training_title']); ?></td>
                                                            <td data-label="Date"><?php echo formatDate($tr['date_from']); ?></td>
                                                            <td data-label="Type"><?php echo e($tr['training_type'] ?: 'General'); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php require_once '../includes/employee-edit-history-card.php'; ?>
        </div>
    </div>

<script>
    let govIdsVisible = false;
    function toggleGovIds() {
        govIdsVisible = !govIdsVisible;
        const btnText = document.getElementById('govIdsBtnText');
        const eyeIcon = document.getElementById('govIdsEyeIcon');
        const elements = document.querySelectorAll('.gov-id-val');
        
        if (govIdsVisible) {
            if (btnText) btnText.textContent = 'Hide IDs';
            if (eyeIcon) eyeIcon.className = 'fas fa-eye-slash me-1';
            elements.forEach(el => {
                const raw = el.getAttribute('data-raw');
                if (raw && raw !== '<span class="text-muted">N/A</span>') {
                    el.innerHTML = raw;
                    el.classList.add('fw-bold');
                }
            });
            document.querySelectorAll('.single-id-toggle').forEach(icon => {
                icon.className = 'fas fa-eye-slash text-primary cursor-pointer single-id-toggle ms-auto';
            });
        } else {
            if (btnText) btnText.textContent = 'Show IDs';
            if (eyeIcon) eyeIcon.className = 'fas fa-eye me-1';
            elements.forEach(el => {
                const masked = el.getAttribute('data-masked');
                if (masked) {
                    el.innerHTML = masked;
                    el.classList.remove('fw-bold');
                }
            });
            document.querySelectorAll('.single-id-toggle').forEach(icon => {
                icon.className = 'fas fa-eye text-muted cursor-pointer single-id-toggle ms-auto';
            });
        }
    }

    function toggleSingleId(iconEl) {
        const parent = iconEl.closest('.detail-value');
        const valEl = parent ? parent.querySelector('.gov-id-val') : null;
        if (!valEl) return;
        
        const raw = valEl.getAttribute('data-raw');
        const masked = valEl.getAttribute('data-masked');
        
        if (valEl.innerHTML === masked) {
            valEl.innerHTML = raw;
            valEl.classList.add('fw-bold');
            iconEl.className = 'fas fa-eye-slash text-primary cursor-pointer single-id-toggle ms-auto';
        } else {
            valEl.innerHTML = masked;
            valEl.classList.remove('fw-bold');
            iconEl.className = 'fas fa-eye text-muted cursor-pointer single-id-toggle ms-auto';
        }
    }

</script>

<script src="<?php echo BASE_URL; ?>/assets/js/employee-profile-tabs.js"></script>
<?php require_once '../includes/footer.php'; ?>
