<?php
$page_title = 'View Employee';
require_once '../includes/session-check.php';
checkRole(['HR Supervisor']);
require_once '../includes/functions.php';

$eid = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$return_to = $_GET['return'] ?? (BASE_URL . '/supervisor/employees.php');
if (strpos($return_to, BASE_URL . '/supervisor/employees.php') !== 0 && strpos($return_to, '/supervisor/employees.php') !== 0) {
    $return_to = BASE_URL . '/supervisor/employees.php';
}
if ($eid <= 0)
    redirectWith(BASE_URL . '/supervisor/employees.php', 'danger', 'Invalid employee ID.');

$stmt = $conn->prepare("SELECT e.*, b.branch_name, d.department_name, rc.rank_name,
    ed.height_m, ed.weight_kg, ed.blood_type, ed.citizenship,
    eg.sss_number, eg.philhealth_number, eg.pagibig_number, eg.tin_number,
    ec.telephone_number, ec.mobile_number, ec.personal_email,
    edi.is_related_to_company, edi.related_details, edi.has_admin_offense, edi.admin_offense_details,
    edi.has_criminal_charge, edi.criminal_charge_details, edi.has_criminal_conviction, edi.criminal_conviction_details,
    edi.has_been_separated, edi.separation_details, edi.is_pwd, edi.pwd_details,
    edi.is_solo_parent, edi.solo_parent_details, edi.has_recent_hospital, edi.hospital_details,
    edi.has_current_treatment, edi.treatment_details
    FROM employees e 
    LEFT JOIN branches b ON e.branch_id = b.branch_id 
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN rank_categories rc ON e.rank_category_id = rc.rank_category_id
    LEFT JOIN employee_details ed ON e.employee_id = ed.employee_id
    LEFT JOIN employee_government_ids eg ON e.employee_id = eg.employee_id
    LEFT JOIN employee_contacts ec ON e.employee_id = ec.employee_id
    LEFT JOIN employee_disclosures edi ON e.employee_id = edi.employee_id
    WHERE e.employee_id = ?");
$stmt->bind_param("i", $eid);
$stmt->execute();
$emp = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$emp)
    redirectWith(BASE_URL . '/supervisor/employees.php', 'danger', 'Employee not found.');

// Strictly prevent viewing of System Admin profiles in the employee management module
$adminCheck = $conn->prepare("SELECT user_id FROM users WHERE employee_id = ? AND role = 'Admin'");
$adminCheck->bind_param("i", $eid);
$adminCheck->execute();
if ($adminCheck->get_result()->num_rows > 0) {
    $adminCheck->close();
    redirectWith(BASE_URL . '/supervisor/employees.php', 'danger', 'Access denied to system administrator profiles.');
}
$adminCheck->close();

// Map contacts/email to legacy fields for UI compatibility
$emp['email'] = $emp['personal_email'];
$emp['contact_number'] = $emp['mobile_number'];

// Load Residential and Permanent Addresses
$res_addr = $conn->query("SELECT * FROM employee_addresses WHERE employee_id=$eid AND address_type='Residential'")->fetch_assoc();
$perm_addr = $conn->query("SELECT * FROM employee_addresses WHERE employee_id=$eid AND address_type='Permanent'")->fetch_assoc();

// Flatten address fields into $emp for legacy UI compatibility
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

// Initialize family fields to avoid warnings
$family_presets = ['spouse', 'father', 'mother_maiden'];
foreach ($family_presets as $pf) {
    $emp[$pf . '_surname'] = $emp[$pf . '_first_name'] = $emp[$pf . '_middle_name'] = $emp[$pf . '_occupation'] = '';
    if ($pf !== 'mother_maiden')
        $emp[$pf . '_name_ext'] = '';
}

// Load Family (Spouse, Father, Mother)
$family = $conn->query("SELECT * FROM employee_family WHERE employee_id=$eid")->fetch_all(MYSQLI_ASSOC);
foreach ($family as $member) {
    $pre = strtolower($member['member_type']);
    if ($pre === 'mother') {
        $emp['mother_maiden_surname'] = $member['surname'];
        $emp['mother_first_name'] = $member['first_name'];
        $emp['mother_middle_name'] = $member['middle_name'];
        $emp['mother_occupation'] = $member['occupation'];
    } else {
        $emp[$pre . '_surname'] = $member['surname'];
        $emp[$pre . '_first_name'] = $member['first_name'];
        $emp[$pre . '_middle_name'] = $member['middle_name'];
        if (isset($member['name_extension']))
            $emp[$pre . '_name_ext'] = $member['name_extension'];
        $emp[$pre . '_occupation'] = $member['occupation'];
    }
}

// Load child data
$children = $conn->query("SELECT * FROM employee_children WHERE employee_id=$eid ORDER BY child_id")->fetch_all(MYSQLI_ASSOC);
$siblings = $conn->query("SELECT * FROM employee_siblings WHERE employee_id=$eid ORDER BY sibling_id")->fetch_all(MYSQLI_ASSOC);
$education = $conn->query("SELECT * FROM employee_education WHERE employee_id=$eid ORDER BY education_id")->fetch_all(MYSQLI_ASSOC);
$work = $conn->query("SELECT * FROM employee_work_experience WHERE employee_id=$eid ORDER BY work_id")->fetch_all(MYSQLI_ASSOC);
$trainings = $conn->query("SELECT * FROM employee_trainings WHERE employee_id=$eid ORDER BY training_id")->fetch_all(MYSQLI_ASSOC);
$voluntary = $conn->query("SELECT * FROM employee_voluntary_work WHERE employee_id=$eid ORDER BY voluntary_id")->fetch_all(MYSQLI_ASSOC);
$eligibility = $conn->query("SELECT * FROM employee_eligibility WHERE employee_id=$eid ORDER BY eligibility_id")->fetch_all(MYSQLI_ASSOC);
$skills = $conn->query("SELECT * FROM employee_skills WHERE employee_id=$eid")->fetch_all(MYSQLI_ASSOC);
$recognitions = $conn->query("SELECT * FROM employee_recognitions WHERE employee_id=$eid")->fetch_all(MYSQLI_ASSOC);
$memberships = $conn->query("SELECT * FROM employee_memberships WHERE employee_id=$eid")->fetch_all(MYSQLI_ASSOC);
$real_props = $conn->query("SELECT * FROM employee_real_properties WHERE employee_id=$eid")->fetch_all(MYSQLI_ASSOC);
$personal_props = $conn->query("SELECT * FROM employee_personal_properties WHERE employee_id=$eid")->fetch_all(MYSQLI_ASSOC);
$liabilities = $conn->query("SELECT * FROM employee_liabilities WHERE employee_id=$eid")->fetch_all(MYSQLI_ASSOC);
$refs = $conn->query("SELECT * FROM employee_references WHERE employee_id=$eid ORDER BY reference_id")->fetch_all(MYSQLI_ASSOC);

require_once '../includes/header.php';

// Helper
if (!function_exists('field')) {
    function field($label, $value, $escape = true)
    {
        $val = !empty($value) ? ($escape ? e($value) : $value) : '<span class="text-muted">N/A</span>';
        return "<div class='detail-item'><div class='detail-label'>$label</div><div class='detail-value'>$val</div></div>";
    }
}
if (!function_exists('govField')) {
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
}
if (!function_exists('yn')) {
    function yn($v)
    {
        return $v ? '<span class="badge bg-warning text-dark">Yes</span>' : '<span class="badge bg-secondary">No</span>';
    }
}

$rankBadgeClassMap = [
    'Executives' => 'rank-badge-executives',
    'Management Team' => 'rank-badge-management',
    'Manager' => 'rank-badge-manager',
    'R&F' => 'rank-badge-rf',
    'Supervisor' => 'rank-badge-supervisor',
];
$rankBadgeClass = $rankBadgeClassMap[$emp['rank_name'] ?? ''] ?? 'rank-badge-default';
?>

<?php
$resAddr = trim(implode(', ', array_filter([$emp['res_house_no'], $emp['res_street'], $emp['res_subdivision'], $emp['res_barangay'], $emp['res_city'], $emp['res_province'], $emp['res_zip_code']])));
$permAddr = trim(implode(', ', array_filter([$emp['perm_house_no'], $emp['perm_street'], $emp['perm_subdivision'], $emp['perm_barangay'], $emp['perm_city'], $emp['perm_province'], $emp['perm_zip_code']])));
$spouseName = trim(($emp['spouse_first_name'] ?? '') . ' ' . ($emp['spouse_middle_name'] ?? '') . ' ' . ($emp['spouse_surname'] ?? '') . ($emp['spouse_name_ext'] ? ' ' . $emp['spouse_name_ext'] : ''));
$fatherName = trim(($emp['father_first_name'] ?? '') . ' ' . ($emp['father_middle_name'] ?? '') . ' ' . ($emp['father_surname'] ?? '') . ($emp['father_name_ext'] ? ' ' . $emp['father_name_ext'] : ''));
$motherName = trim(($emp['mother_first_name'] ?? '') . ' ' . ($emp['mother_middle_name'] ?? '') . ' ' . ($emp['mother_maiden_surname'] ?? ''));
$discList = [
    ['is_related_to_company', 'related_details', 'Related to company employee (3rd degree)'],
    ['has_admin_offense', 'admin_offense_details', 'Found guilty of admin offense'],
    ['has_criminal_charge', 'criminal_charge_details', 'Criminally charged before court'],
    ['has_criminal_conviction', 'criminal_conviction_details', 'Convicted of crime'],
    ['has_been_separated', 'separation_details', 'Separated from service'],
    ['is_pwd', 'pwd_details', 'Person with disability'],
    ['is_solo_parent', 'solo_parent_details', 'Solo parent'],
    ['has_recent_hospital', 'hospital_details', 'Hospitalized in last 6 months'],
    ['has_current_treatment', 'treatment_details', 'Currently under treatment'],
];
?>

<style>
    @media (min-width: 992px) {
        .profile-sticky-col {
            position: sticky;
            top: calc(var(--header-height) + 18px);
            align-self: flex-start;
        }
    }

    .cursor-pointer {
        cursor: pointer;
    }

    .hover-zoom:hover {
        transform: scale(1.05);
    }

    .employee-page-title {
        font-size: 1.65rem;
        font-weight: 700;
        color: var(--text-dark);
    }

    .employee-reference-hero {
        background: #fff;
        border: 1px solid #e5ebe7;
        border-radius: 14px;
        box-shadow: 0 10px 28px rgba(15, 23, 42, .06);
        padding: 1rem 1.1rem;
        margin-bottom: .55rem;
    }

    .employee-reference-identity {
        display: flex;
        align-items: center;
        gap: 1.1rem;
        min-width: 0;
    }

    .employee-reference-avatar {
        width: 98px;
        height: 98px;
        flex: 0 0 98px;
        object-fit: cover;
        border-radius: 50%;
        border: 4px solid #f0f1ef;
        box-shadow: 0 4px 12px rgba(15, 23, 42, .12);
    }

    .employee-reference-copy {
        min-width: 0;
        flex: 1;
    }

    .employee-reference-copy h2 {
        color: #142236;
        font-size: 1.25rem;
        line-height: 1.25;
        font-weight: 800;
        margin: 0 0 .2rem;
    }

    .employee-reference-copy p {
        color: #61706b;
        font-size: .82rem;
        line-height: 1.4;
        margin: 0;
    }

    .employee-reference-contact {
        display: flex;
        flex-wrap: wrap;
        gap: .65rem 1.1rem;
        margin-top: .7rem;
        color: #33453d;
        font-size: .76rem;
    }

    .employee-reference-contact i,
    .employee-reference-summary i {
        color: #12613a;
    }

    .employee-reference-summary {
        display: grid;
        grid-template-columns: repeat(3, minmax(125px, 1fr));
        gap: .65rem;
        width: min(42%, 420px);
        margin-left: auto;
    }

    .employee-reference-summary-item {
        min-width: 0;
        padding: .65rem .7rem;
        border: 1px solid #e9efeb;
        border-radius: 8px;
        background: #f8faf9;
    }

    .employee-reference-summary-item small,
    .employee-reference-summary-item strong {
        display: block;
    }

    .employee-reference-summary-item small {
        color: #718078;
        font-size: .67rem;
        margin-bottom: .15rem;
    }

    .employee-reference-summary-item strong {
        color: #1c2c24;
        font-size: .78rem;
        line-height: 1.3;
        overflow-wrap: anywhere;
    }

    .legacy-profile-rail {
        display: none !important;
    }

    .employee-information-tabs {
        display: flex;
        gap: .15rem;
        overflow-x: auto;
        margin-bottom: 1rem;
        padding: 0 .35rem;
        background: #fff;
        border: 1px solid #e5ebe7;
        border-radius: 10px;
        scrollbar-width: thin;
    }

    .employee-information-tab {
        flex: 0 0 auto;
        appearance: none;
        border: 0;
        border-bottom: 2px solid transparent;
        border-radius: 8px 8px 0 0;
        background: transparent;
        color: #60706a;
        font-size: .74rem;
        font-weight: 700;
        padding: .7rem .8rem .62rem;
        white-space: nowrap;
        transition: color .18s ease, background-color .18s ease, border-color .18s ease;
    }

    .employee-information-tab:hover,
    .employee-information-tab:focus-visible,
    .employee-information-tab[aria-selected="true"] {
        color: #12613a;
        background: #f5faf7;
        border-bottom-color: #12613a;
        outline: none;
    }

    .employee-information-tab i {
        margin-right: .35rem;
    }

    [data-profile-panel][hidden] {
        display: none !important;
    }

    .profile-panel-active {
        flex: 0 0 100% !important;
        max-width: 100% !important;
    }

    .profile-panel-active > .employee-section-card,
    [data-profile-panel].profile-panel-active {
        animation: profile-panel-in .22s ease-out;
    }

    @keyframes profile-panel-in {
        from { opacity: .3; transform: translateY(5px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .employee-card-grid {
        display: grid;
        gap: 1.5rem;
    }

    .employee-section-card,
    .employee-profile-card {
        border: 1px solid rgba(15, 23, 42, 0.08);
        box-shadow: 0 14px 30px rgba(15, 23, 42, 0.06);
        overflow: hidden;
        background: #fff;
    }

    .employee-section-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
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

    .employee-section-card .card-body {
        padding: 1.5rem;
    }

    .employee-section-card .employee-section-header h5 {
        color: #17251f;
        font-size: 1.05rem;
        letter-spacing: -.01em;
    }

    .employee-section-card .employee-section-kicker {
        color: #12613a;
    }

    .performance-career-panel {
        animation: performance-career-panel-in 0.2s ease-out;
    }

    @keyframes performance-career-panel-in {
        from { opacity: 0; transform: translateY(4px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .employee-subsection {
        border: 1px solid #edf2f7;
        border-radius: 16px;
        background: #fbfcfe;
        padding: 1.15rem;
        margin-bottom: 1rem;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .8);
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
        box-shadow: 0 2px 8px rgba(15, 23, 42, .025);
        transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
    }

    .detail-item:hover {
        border-color: #cfe3d7;
        box-shadow: 0 7px 16px rgba(18, 97, 58, .08);
        transform: translateY(-1px);
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

    .rank-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        padding: 0.45rem 0.7rem;
        border-radius: 999px;
    }

    .rank-badge-executives {
        background: #6f42c1;
        color: #fff;
    }

    .rank-badge-management {
        background: #0d6efd;
        color: #fff;
    }

    .rank-badge-manager {
        background: #198754;
        color: #fff;
    }

    .rank-badge-rf {
        background: #fd7e14;
        color: #fff;
    }

    .rank-badge-supervisor {
        background: #20c997;
        color: #073b35;
    }

    .rank-badge-default {
        background: #6c757d;
        color: #fff;
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

    .employee-table-wrap tbody td {
        vertical-align: top;
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

    .disclosure-list {
        display: grid;
        gap: 0.85rem;
    }

    .disclosure-item {
        padding: 1rem 1.1rem;
        border-radius: 16px;
        border: 1px solid #edf2f7;
        background: #fbfcfe;
    }

    .recognition-list .list-group-item {
        border-left: 0;
        border-right: 0;
    }

    .recognition-list .list-group-item:first-child {
        border-top: 0;
    }

    .recognition-list .list-group-item:last-child {
        border-bottom: 0;
    }

    @media (max-width: 767.98px) {
        .employee-reference-identity {
            align-items: flex-start;
            flex-wrap: wrap;
        }

        .employee-reference-avatar {
            width: 78px;
            height: 78px;
            flex-basis: 78px;
        }

        .employee-reference-summary {
            width: 100%;
            margin-left: 0;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

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

<?php
$hero_hire_date = !empty($emp['hire_date']) ? new DateTime($emp['hire_date']) : null;
$hero_tenure = 'N/A';
if ($hero_hire_date) {
    $hero_tenure_diff = $hero_hire_date->diff(new DateTime());
    $hero_tenure = $hero_tenure_diff->y . ' years ' . $hero_tenure_diff->m . ' months';
}
$hero_name = trim($emp['first_name'] . ' ' . ($emp['middle_name'] ? $emp['middle_name'] . ' ' : '') . $emp['last_name'] . ($emp['name_extension'] ? ' ' . $emp['name_extension'] : ''));
?>

        <?php
        // Query approved evaluations for 5-Year performance trend
        $perf_history_q = $conn->prepare("
            SELECT evaluation_id, total_score, performance_level, approved_date,
                   COALESCE(approved_date, evaluation_period_end) AS display_date,
                   YEAR(COALESCE(approved_date, evaluation_period_end)) as eval_year,
                   evaluation_type
            FROM evaluations
            WHERE employee_id = ? AND status = 'Approved'
            ORDER BY COALESCE(approved_date, evaluation_period_end) ASC
        ");
        $perf_history_q->bind_param("i", $eid);
        $perf_history_q->execute();
        $perf_history_res = $perf_history_q->get_result();
        $perf_history_data = [];
        $chart_labels = [];
        $chart_scores = [];
        while ($ph = $perf_history_res->fetch_assoc()) {
            $perf_history_data[] = $ph;
            $chart_labels[] = date('M Y', strtotime($ph['display_date'])) . ' (' . ($ph['evaluation_type'] ?? 'Eval') . ')';
            $chart_scores[] = (float)$ph['total_score'];
        }
        $perf_history_q->close();

        $avg_5yr_score = 0;
        $classification = 'No Evaluation Record';
        $class_badge = 'bg-secondary';
        if (!empty($perf_history_data)) {
            $sum_scores = array_sum(array_column($perf_history_data, 'total_score'));
            $avg_5yr_score = round($sum_scores / count($perf_history_data), 2);
            
            $n = count($perf_history_data);
            $first = (float)$perf_history_data[0]['total_score'];
            $last = (float)$perf_history_data[$n - 1]['total_score'];
            $diff = $last - $first;
            
            if ($avg_5yr_score >= 3.60) {
                $classification = 'Consistently Outstanding';
                $class_badge = 'bg-success';
            } elseif ($diff >= 0.30) {
                $classification = 'Improving Performance';
                $class_badge = 'bg-info text-dark';
            } elseif ($diff <= -0.30) {
                $classification = 'Declining / Needing Intervention';
                $class_badge = 'bg-danger';
            } else {
                $classification = 'Stable Performance';
                $class_badge = 'bg-primary';
            }
        }

        // Keep the existing approved career movement lookup, now rendered in its related tab.
        $cm_history = [];
        $cm_check = $conn->query("SHOW TABLES LIKE 'career_movements'");
        if ($cm_check && $cm_check->num_rows > 0) {
            $cm_stmt = $conn->prepare("
                SELECT cm.*,
                    pb.branch_name AS from_branch_name,
                    nb.branch_name AS to_branch_name,
                    u1.full_name   AS logged_by_name,
                    u2.full_name   AS approved_by_name
                FROM career_movements cm
                LEFT JOIN branches pb ON cm.previous_branch_id = pb.branch_id
                LEFT JOIN branches nb ON cm.new_branch_id       = nb.branch_id
                LEFT JOIN users   u1 ON cm.logged_by            = u1.user_id
                LEFT JOIN users   u2 ON cm.approved_by          = u2.user_id
                WHERE cm.employee_id = ? AND cm.approval_status = 'Approved'
                ORDER BY cm.effective_date DESC, cm.created_at DESC
            ");
            $cm_stmt->bind_param("i", $eid);
            $cm_stmt->execute();
            $cm_history = $cm_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $cm_stmt->close();
        }
        ?>

<style>
/* ═══════════════════════ EMPLOYEE PROFILE REWORK STYLES ═══════════════════════ */

/* ── Hero Card ── */
.ep-hero-card {
    background: #fff;
    border-radius: 16px;
    padding: 24px 28px;
    box-shadow: 0 1px 4px rgba(0,0,0,.08), 0 6px 20px rgba(0,0,0,.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 24px;
    margin-bottom: 18px;
    border: 1px solid #e8edf0;
}
.ep-avatar {
    width: 90px; height: 90px; border-radius: 50%;
    object-fit: cover;
    border: 4px solid #f0f4ee;
    box-shadow: 0 4px 12px rgba(15,23,42,.12);
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    flex-shrink: 0;
}
.ep-avatar:hover { transform: scale(1.05); box-shadow: 0 6px 18px rgba(41,67,6,.2); }
.ep-name {
    font-size: 1.35rem;
    font-weight: 800;
    color: #142236;
    margin: 0;
    line-height: 1.25;
}
.ep-sub {
    font-size: .82rem;
    color: #61706b;
    margin: 3px 0 0;
    line-height: 1.4;
}
.ep-status-pills { display: flex; gap: 7px; margin-top: 10px; flex-wrap: wrap; }
.ep-pill {
    padding: 4px 12px;
    border-radius: 99px;
    font-size: .74rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    letter-spacing: .01em;
}
.ep-pill-outline { border: 1.5px solid currentColor; background: transparent; }
.ep-pill-green  { color: #294306; border-color: #a3c77a; background: #f4fae8; }
.ep-pill-blue   { color: #1e40af; border-color: #93c5fd; background: #eff6ff; }
.ep-pill-gray   { color: #4b5563; border-color: #d1d5db; background: #f9fafb; }
.ep-hire-row {
    display: flex;
    gap: 20px;
    margin-top: 12px;
    font-size: .8rem;
    color: #61706b;
}
.ep-hire-row strong { color: #1a2e06; font-weight: 700; }

/* Years of service badge */
.ep-service-years {
    text-align: center;
    background: linear-gradient(135deg, #f4fae8, #e6f2cc);
    border: 1.5px solid #c6e0a0;
    border-radius: 14px;
    padding: 14px 22px;
    flex-shrink: 0;
    min-width: 90px;
}
.ep-service-years .number {
    font-size: 2.8rem;
    font-weight: 900;
    color: #294306;
    line-height: 1;
    display: block;
}
.ep-service-years .label {
    font-size: .65rem;
    font-weight: 700;
    text-transform: uppercase;
    color: #5a7a30;
    letter-spacing: .06em;
    display: block;
    margin-top: 3px;
}

/* ── Completion Banner ── */
.ep-completion-banner {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 10px;
    padding: 13px 18px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    font-size: .84rem;
}
.ep-completion-banner.banner-warning {
    background: #fffbeb;
    border-color: #fde68a;
}

/* ── Section Accordion Rows ── */
.ep-section-row {
    background: #fff;
    border-radius: 12px;
    margin-bottom: 10px;
    box-shadow: 0 1px 2px rgba(0,0,0,.06);
    overflow: hidden;
    border: 1px solid #eef2f0;
    transition: box-shadow .2s;
}
.ep-section-row:hover { box-shadow: 0 3px 10px rgba(41,67,6,.08); }
.ep-section-header {
    padding: 14px 18px;
    display: flex;
    align-items: center;
    gap: 14px;
    cursor: pointer;
    transition: background-color .18s;
    user-select: none;
}
.ep-section-header:hover { background-color: #f8faf8; }
.ep-section-icon {
    width: 38px; height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}
.ep-section-title-wrap { flex: 1; min-width: 0; }
.ep-section-title {
    font-size: .92rem;
    font-weight: 700;
    margin: 0;
    color: #111827;
    line-height: 1.3;
}
.ep-section-desc {
    font-size: .74rem;
    color: #6b7280;
    margin: 2px 0 0;
    line-height: 1.3;
}
.ep-section-actions { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
.ep-badge-count {
    font-size: .72rem;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 99px;
    white-space: nowrap;
}
.ep-badge-none   { background: #f3f4f6; color: #6b7280; }
.ep-badge-has    { background: #dcfce7; color: #166534; }
.ep-badge-complete { background: #dcfce7; color: #166534; }
.ep-section-btn {
    font-size: .74rem;
    font-weight: 600;
    padding: 5px 13px;
    border-radius: 7px;
    border: 1.5px solid #294306;
    color: #294306;
    background: transparent;
    cursor: pointer;
    white-space: nowrap;
    transition: background .15s, color .15s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.ep-section-btn:hover { background: #294306; color: #fff; }
.ep-chevron {
    color: #9ca3af;
    font-size: .8rem;
    transition: transform .28s cubic-bezier(.4,0,.2,1);
    flex-shrink: 0;
    margin-left: 4px;
}
.ep-chevron.open { transform: rotate(180deg); color: #294306; }
.ep-section-content {
    display: none;
    padding: 18px 20px 20px;
    border-top: 1px solid #f0f4ee;
    background: #fafafa;
}
.ep-section-content.active { display: block; }

/* ── Sidebar ── */
.ep-sidebar-card {
    background: #fff;
    border-radius: 12px;
    padding: 18px;
    margin-bottom: 18px;
    box-shadow: 0 1px 3px rgba(0,0,0,.08);
    border: 1px solid #eef2f0;
}
.ep-sidebar-title {
    font-size: .75rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: #374151;
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 7px;
    border-bottom: 1px solid #f3f4f6;
    padding-bottom: 10px;
}
.ep-sidebar-title i { color: #294306; }

/* Quick Nav */
.ep-quick-nav { list-style: none; padding: 0; margin: 0; }
.ep-quick-nav li { margin-bottom: 2px; }
.ep-quick-nav a {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #4b5563;
    text-decoration: none;
    padding: 7px 10px;
    border-radius: 8px;
    transition: all .15s;
    font-size: .8rem;
    font-weight: 500;
}
.ep-quick-nav a:hover { background: #f0f7e8; color: #294306; }
.ep-quick-nav-icon {
    width: 22px; height: 22px;
    border-radius: 6px;
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .72rem;
    color: #4b5563;
    flex-shrink: 0;
}
.ep-quick-nav a:hover .ep-quick-nav-icon { background: #e6f2cc; color: #294306; }
.ep-quick-nav a i.fa-chevron-right { margin-left: auto; font-size: .65rem; color: #d1d5db; }
.ep-quick-nav a:hover i.fa-chevron-right { color: #294306; }

/* Completion ring */
.ep-completion-ring { position: relative; display: inline-block; }
.ep-completion-ring svg circle { transition: stroke-dashoffset .6s ease; }
.ep-ring-center {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-size: .75rem;
    font-weight: 800;
    color: #294306;
    line-height: 1;
}
.ep-ring-center span { font-size: .6rem; font-weight: 600; color: #6b7280; }

/* Sidebar detail items */
.ep-emp-detail-item, .ep-gov-id-item {
    padding: 8px 0;
    border-bottom: 1px solid #f3f4f6;
    display: flex;
    align-items: flex-start;
    gap: 9px;
}
.ep-emp-detail-item:last-child, .ep-gov-id-item:last-child { border-bottom: none; padding-bottom: 0; }
.ep-emp-detail-item i, .ep-gov-id-item i { color: #294306; font-size: .82rem; margin-top: 2px; flex-shrink: 0; width: 14px; }
.ep-emp-detail-label, .ep-gov-id-label {
    font-size: .7rem;
    color: #6b7280;
    display: block;
    margin-bottom: 1px;
    text-transform: uppercase;
    letter-spacing: .03em;
    font-weight: 600;
}
.ep-emp-detail-value, .ep-gov-id-value { font-size: .84rem; font-weight: 700; color: #1a2e06; }

/* Missing section alert items */
.ep-missing-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 10px;
    border-radius: 7px;
    font-size: .78rem;
    font-weight: 600;
    color: #92400e;
    background: #fef3c7;
    margin-bottom: 5px;
    cursor: pointer;
}
.ep-missing-item:hover { background: #fde68a; }
.ep-missing-item i { color: #d97706; }

/* Icon color helpers */
.bg-light-primary { background-color: #e0e7ff; color: #4338ca; }
.bg-light-success { background-color: #dcfce7; color: #166534; }
.bg-light-info    { background-color: #e0f2fe; color: #0369a1; }
.bg-light-warning { background-color: #fef9c3; color: #a16207; }
.bg-light-danger  { background-color: #fee2e2; color: #991b1b; }
.bg-light-purple  { background-color: #f3e8ff; color: #6b21a8; }
.bg-light-green   { background-color: #f0fdf4; color: #15803d; }
.bg-light-teal    { background-color: #f0fdfa; color: #0f766e; }
.bg-light-rose    { background-color: #fff1f2; color: #9f1239; }
.bg-light-amber   { background-color: #fffbeb; color: #b45309; }
.bg-light-indigo  { background-color: #eef2ff; color: #3730a3; }
.bg-light-brand   { background-color: #f4fae8; color: #294306; }

/* ── OVERRIDE FIXES to match reference image ── */

/* 1. Section action badges — override Bootstrap badges inside accordion rows */
.ep-section-actions .badge {
    font-size: .72rem !important;
    font-weight: 600 !important;
    padding: 4px 11px !important;
    border-radius: 99px !important;
    letter-spacing: .01em;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.ep-section-actions .badge.bg-secondary {
    background: #f3f4f6 !important;
    color: #4b5563 !important;
    border: 1px solid #e5e7eb;
}
.ep-section-actions .badge.bg-success {
    background: #dcfce7 !important;
    color: #166534 !important;
    border: 1px solid #bbf7d0;
}
.ep-section-actions .badge.bg-info {
    background: #e0f2fe !important;
    color: #0369a1 !important;
    border: 1px solid #bae6fd;
}
.ep-section-actions .badge.bg-warning {
    background: #fef9c3 !important;
    color: #854d0e !important;
    border: 1px solid #fde047;
}

/* 2. Section action buttons — override Bootstrap btn inside accordion rows */
.ep-section-actions .btn-outline-success,
.ep-section-actions .btn-outline-primary {
    font-size: .74rem !important;
    font-weight: 600 !important;
    padding: 4px 13px !important;
    border-radius: 7px !important;
    border: 1.5px solid #294306 !important;
    color: #294306 !important;
    background: transparent !important;
    transition: background .15s, color .15s !important;
    white-space: nowrap;
}
.ep-section-actions .btn-outline-success:hover,
.ep-section-actions .btn-outline-primary:hover {
    background: #294306 !important;
    color: #fff !important;
}
/* + Add Record button */
.ep-section-actions .btn-outline-secondary {
    font-size: .74rem !important;
    font-weight: 600 !important;
    padding: 4px 13px !important;
    border-radius: 7px !important;
    border: 1.5px solid #294306 !important;
    color: #294306 !important;
    background: transparent !important;
}
.ep-section-actions .btn-outline-secondary:hover {
    background: #294306 !important;
    color: #fff !important;
}

/* 3. Section icon — square with rounded corners, not full circle */
.ep-section-icon {
    border-radius: 10px !important;
    width: 40px !important;
    height: 40px !important;
}

/* 4. SVG completion ring — rotate so gap starts at top */
.ep-completion-ring svg {
    transform: rotate(-90deg);
    display: block;
}

/* 5. Hero sub — department on own line, smaller text */
.ep-sub {
    font-size: .8rem !important;
    color: #61706b !important;
    margin: 2px 0 0 !important;
}

/* 6. Hero card inner left column — limit width so service years box doesn't squish */
.ep-hero-card > .d-flex:first-child {
    flex: 1;
    min-width: 0;
}

/* 7. Active badge in hero — subtle green pill */
.ep-hero-card .badge.bg-success {
    background: #dcfce7 !important;
    color: #166534 !important;
    font-size: .72rem !important;
    font-weight: 700 !important;
    padding: 3px 10px !important;
    border-radius: 99px !important;
    border: 1px solid #86efac;
}
.ep-hero-card .badge.bg-danger {
    background: #fee2e2 !important;
    color: #991b1b !important;
    border: 1px solid #fca5a5;
    font-size: .72rem !important;
    padding: 3px 10px !important;
    border-radius: 99px !important;
}

/* 8. Completion banner — force info-blue look, remove default Bootstrap alert colors */
.ep-completion-banner {
    border: 1px solid #bfdbfe !important;
    background: #eff6ff !important;
    color: #1e40af;
}
.ep-completion-banner.banner-warning {
    background: #fffbeb !important;
    border-color: #fde68a !important;
    color: #92400e;
}
.ep-completion-banner h6 { color: #1e3a5f; }
.ep-completion-banner.banner-warning h6 { color: #78350f; }
.ep-completion-banner .btn-outline-primary {
    font-size: .78rem !important;
    border-color: #294306 !important;
    color: #294306 !important;
    padding: 4px 14px !important;
    border-radius: 7px !important;
    font-weight: 600 !important;
    white-space: nowrap;
}
.ep-completion-banner .btn-outline-primary:hover {
    background: #294306 !important;
    color: #fff !important;
}

/* 9. Sidebar sticky — ensure it appears alongside left column */
@media (min-width: 992px) {
    .profile-sticky-col {
        position: sticky;
        top: calc(var(--header-height, 70px) + 18px);
        align-self: flex-start;
        max-height: calc(100vh - 110px);
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: #e5e7eb transparent;
    }
}

/* 10. Section header chevron alignment */
.ep-section-header {
    border: none !important;
    background: none !important;
}
.ep-chevron {
    color: #9ca3af;
    font-size: .8rem;
    transition: transform .28s cubic-bezier(.4,0,.2,1);
    margin-left: 6px;
    flex-shrink: 0;
}
.ep-chevron.open { transform: rotate(180deg) !important; color: #294306; }

/* 11. Quick nav chevron arrow on right */
.ep-quick-nav li a { justify-content: flex-start; }

/* 12. Sidebar completion ring center text absolute positioning */
.ep-completion-ring {
    width: 80px;
    height: 80px;
    position: relative;
    flex-shrink: 0;
}
.ep-ring-center {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

/* 13. Exact Hero Layout & Meta Box styles */
.ep-hero-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 24px;
    padding: 24px 28px;
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
}
.ep-hero-left {
    display: flex;
    align-items: center;
    gap: 20px;
    flex: 1;
    min-width: 320px;
}
.ep-hero-info {
    flex: 1;
}
.ep-hero-role {
    font-size: 0.95rem;
    font-weight: 700;
    color: #1e293b;
}
.ep-hero-sub {
    font-size: 0.8rem;
    color: #64748b;
}
.ep-hero-right {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-shrink: 0;
}
.ep-meta-box {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 10px 14px;
    border-radius: 12px;
}
.ep-meta-icon {
    font-size: 1.1rem;
    color: #294306;
}
.ep-badge-status {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 999px;
}
.ep-status-active {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
}
.ep-status-inactive {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}
.ep-pill-purple {
    background: #f5f3ff;
    color: #6d28d9;
    border: 1px solid #ddd6fe;
}
.ep-pill-blue {
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
}
.ep-pill-gray {
    background: #f8fafc;
    color: #475569;
    border: 1px solid #e2e8f0;
}
.ep-service-years {
    background: #f0fdf4;
    border: 1.5px solid #bbf7d0;
    border-radius: 14px;
    padding: 10px 18px;
    text-align: center;
    min-width: 90px;
}
.ep-service-years .number {
    font-size: 2.2rem;
    font-weight: 900;
    color: #294306;
    line-height: 1;
}
.ep-service-years .label {
    font-size: 0.65rem;
    font-weight: 800;
    color: #15803d;
    letter-spacing: 0.05em;
    line-height: 1.1;
    margin-top: 2px;
}
</style>


<?php
$completionSections = [
    'personal'     => !empty($emp['date_of_birth']) || !empty($emp['gender']),
    'contact'      => !empty($emp['mobile_number']) || !empty($emp['telephone_number']),
    'family'       => !empty($family) || !empty($children),
    'education'    => !empty($education),
    'work'         => !empty($work),
    'training'     => !empty($trainings),
    'eligibility'  => !empty($eligibility),
    'skills'       => !empty($skills) || !empty($recognitions) || !empty($memberships),
    'disclosures'  => !empty($emp['sss_number']) || !empty($emp['philhealth_number']),
    'assets'       => !empty($real_props) || !empty($personal_props),
    'references'   => !empty($refs),
    'address'      => !empty($resAddr) || !empty($permAddr),
    'performance'  => !empty($perf_history_data),
];
$completedCount = count(array_filter($completionSections));
$totalSections  = count($completionSections);
$completionPct  = round($completedCount / $totalSections * 100);
$missingSections = [];
$sectionNames = [
    'personal'=>'Personal Information','contact'=>'Contact & Address',
    'family'=>'Family Information','education'=>'Education Background',
    'work'=>'Work Experience','training'=>'Training & Eligibility',
    'eligibility'=>'Professional Licenses','skills'=>'Skills & Recognitions',
    'disclosures'=>'Government IDs','assets'=>'Assets & Properties',
    'references'=>'Character References','address'=>'Address Records',
    'performance'=>'Performance Records',
];
foreach ($completionSections as $k => $done) {
    if (!$done) $missingSections[] = $sectionNames[$k];
}
?>


<div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-3">
    <div>
        <div class="small text-muted mb-1"><a href="<?php echo htmlspecialchars($return_to, ENT_QUOTES, 'UTF-8'); ?>" class="text-decoration-none text-muted"><i class="fas fa-users me-1"></i>Employees</a> <span class="mx-1">&rsaquo;</span> Employee Profile</div>
        <h1 class="employee-page-title mb-0">Employee Profile</h1>
        <p class="text-muted small mb-0">View and manage employee information, employment details, and performance records.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?php echo BASE_URL; ?>/supervisor/edit-employee.php?id=<?php echo $eid; ?>&return=<?php echo urlencode($return_to); ?>" class="btn btn-primary"><i class="fas fa-pen me-2"></i>Edit Profile</a>
        <button type="button" class="btn btn-light border" onclick="window.print()"><i class="fas fa-print me-2"></i>Print</button>
        <a href="<?php echo htmlspecialchars($return_to, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-light border" title="Back to employees"><i class="fas fa-arrow-left"></i></a>
        <div class="dropdown">
            <button class="btn btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-ellipsis-h"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="javascript:void(0)" onclick="window.print()"><i class="fas fa-download me-2"></i>Download / Print PDS</a></li>
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/supervisor/evaluation-history.php?employee_id=<?php echo $eid; ?>"><i class="fas fa-star me-2"></i>View Evaluations</a></li>
            </ul>
        </div>
    </div>
</div>

<div class="ep-hero-card">
    <div class="ep-hero-left">
        <div class="position-relative cursor-pointer" onclick="viewFullImage('<?php echo getEmployeeAvatar($emp['profile_picture']); ?>', '<?php echo e($hero_name); ?>')">
            <img src="<?php echo getEmployeeAvatar($emp['profile_picture']); ?>" class="ep-avatar" alt="Employee profile photo">
        </div>
        <div class="ep-hero-info">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h2 class="ep-name"><?php echo e($hero_name); ?></h2>
                <span class="badge ep-badge-status <?php echo $emp['is_active'] ? 'ep-status-active' : 'ep-status-inactive'; ?>">
                    <i class="fas fa-circle me-1" style="font-size:0.4rem; vertical-align: middle;"></i><?php echo $emp['is_active'] ? 'Active' : 'Inactive'; ?>
                </span>
            </div>
            <div class="ep-hero-role mt-1">
                <?php echo e($emp['job_title']); ?>

            </div>
            <div class="ep-hero-sub text-muted">
                <?php echo e($emp['department_name'] ?: 'General Department'); ?> &bull; Employee ID: <?php echo e(getEmployeeDisplayId($emp)); ?>

            </div>
            <div class="ep-status-pills mt-2">
                <span class="ep-pill ep-pill-purple"><i class="fas fa-user-check me-1"></i><?php echo e($emp['employment_status']); ?></span>
                <span class="ep-pill ep-pill-blue"><?php echo e($emp['employment_type'] ?: 'Full-Time'); ?></span>
                <span class="ep-pill ep-pill-gray"><i class="fas fa-building me-1"></i><?php echo e($emp['branch_name'] ?: 'Head Office'); ?></span>
            </div>
        </div>
    </div>
    <div class="ep-hero-right">
        <div class="ep-meta-box">
            <div class="ep-meta-icon"><i class="fas fa-calendar-alt"></i></div>
            <div>
                <small class="text-muted d-block text-uppercase" style="font-size: 0.68rem; font-weight: 700; letter-spacing: 0.05em;">Date Hired</small>
                <strong><?php echo formatDate($emp['hire_date']); ?></strong>
            </div>
        </div>
        <div class="ep-meta-box">
            <div class="ep-meta-icon"><i class="fas fa-clock"></i></div>
            <div>
                <small class="text-muted d-block text-uppercase" style="font-size: 0.68rem; font-weight: 700; letter-spacing: 0.05em;">Tenure</small>
                <strong><?php echo e($hero_tenure); ?></strong>
            </div>
        </div>
        <div class="ep-service-years">
            <?php 
                $years = 0;
                if ($hero_hire_date) {
                    $years = $hero_hire_date->diff(new DateTime())->y;
                }
            ?>
            <div class="number"><?php echo $years; ?></div>
            <div class="label">YEARS<br><span style="font-size:0.6rem; font-weight:600; text-transform:none; color:#777;">of Service</span></div>
        </div>
    </div>
</div>

<?php if (!$emp['is_active'] || !empty($emp['separation_date'])): ?>
    <div class="alert alert-warning text-start py-2 px-3 mb-4 border-start border-4 border-warning shadow-sm" style="background-color: #fff9db; border-color: #f59f00 !important;">
        <div class="fw-bold text-dark mb-1 d-flex align-items-center gap-1" style="font-size: 0.85rem;">
            <i class="fas fa-user-slash text-warning me-1"></i>Separation Notice
        </div>
        <div class="d-flex justify-content-between gap-2 small mb-1">
            <span class="text-muted">Status:</span>
            <span class="fw-bold text-dark"><?php echo e($emp['employment_status']); ?></span>
        </div>
        <?php if (!empty($emp['separation_date'])): ?>
            <div class="d-flex justify-content-between gap-2 small mb-1">
                <span class="text-muted">Effective:</span>
                <span class="fw-bold text-danger"><?php echo formatDate($emp['separation_date']); ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($emp['separation_remarks'])): ?>
            <div class="small mt-1 pt-1 border-top border-warning-subtle text-muted" style="font-size: 0.75rem;">
                <strong>Notes:</strong> <?php echo nl2br(e($emp['separation_remarks'])); ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="ep-completion-banner <?php echo $completionPct < 100 ? 'banner-warning' : ''; ?>">
    <div class="d-flex align-items-center gap-3">
        <i class="fas <?php echo $completionPct == 100 ? 'fa-check-circle text-success' : 'fa-info-circle'; ?>" style="font-size:1.5rem;color:#3b82f6;"></i>
        <div>
            <h6 class="mb-1 fw-bold" style="font-size:.9rem;">This profile is <?php echo $completionPct; ?>% complete.</h6>
            <p class="mb-0 small text-muted"><?php echo $totalSections - $completedCount; ?> sections have missing information.</p>
        </div>
    </div>
    <button class="btn btn-sm btn-outline-primary" onclick="document.querySelector('.profile-sticky-col').scrollIntoView({behavior: 'smooth'})">View Details &rarr;</button>
</div>

<div class="row g-4">
    <div class="col-lg-8">


    <div class="ep-section-row" id="section-performance">
        <div class="ep-section-header" onclick="toggleSection('performance')">
            <div class="ep-section-icon bg-light-warning"><i class="fas fa-chart-line"></i></div>
            <div class="ep-section-title-wrap">
                <h3 class="ep-section-title">Performance & Career</h3>
                <p class="ep-section-desc">Latest evaluation, performance trend, and career movement history.</p>
            </div>
            <div class="ep-section-actions">
                <?php echo empty($perf_history_data) && empty($cm_history) ? '<span class="badge bg-secondary">No records</span>' : '<span class="badge bg-success">' . (count($perf_history_data) + count($cm_history)) . ' records</span>'; ?>
                <button class='btn btn-sm btn-outline-success'>View Details</button>
                <i class="fas fa-chevron-down ep-chevron open" id="chevron-performance"></i>
            </div>
        </div>
        <div class="ep-section-content active" id="content-performance">
                    <!-- Performance and career data share one full-width, client-side tab interface. -->
        <div class="content-card employee-section-card mb-4" data-profile-panel="performance">
            <div class="employee-section-header">
                <div>
                    <div class="employee-section-kicker"><i class="fas fa-chart-line text-warning"></i>Employee Insights</div>
                    <h5 class="mb-0">Performance &amp; Career</h5>
                </div>
            </div>
            <div class="performance-career-panel" id="performance-panel">
                <div class="employee-section-header">
                    <div>
                        <div class="employee-section-kicker"><i class="fas fa-chart-line text-warning"></i>Performance Analytics</div>
                        <h5 class="mb-0">5-Year Historical Performance Trend</h5>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                        <span class="badge <?php echo $class_badge; ?> px-3 py-2" style="font-size:0.82rem;">
                            <i class="fas fa-robot me-1"></i><?php echo $classification; ?>
                        </span>
                        <span class="badge bg-dark text-warning px-3 py-2" style="font-size:0.85rem;">
                            Avg Score: <?php echo number_format($avg_5yr_score, 2); ?> / 4.00
                        </span>
                    </div>
                </div>
                <div class="card-body">
                <?php if (empty($perf_history_data)): ?>
                    <div class="empty-state py-4">
                        <i class="fas fa-chart-line"></i>
                        <p>No approved performance evaluation history recorded for this employee yet.</p>
                    </div>
                <?php else: ?>
                    <div class="row align-items-center g-3 mb-3">
                        <div class="col-lg-8">
                            <div style="height: 220px; position: relative;">
                                <canvas id="empPerformanceTrendChart"></canvas>
                            </div>
                        </div>
                        <div class="col-lg-4 border-start">
                            <h6 class="fw-bold small text-muted uppercase mb-3">Evaluation History Summary</h6>
                            <div class="d-grid gap-2">
                                <?php foreach (array_reverse($perf_history_data) as $phItem):
                                    $scoreVal = (float)$phItem['total_score'];
                                    $lvl = $phItem['performance_level'] ?? getPerformanceLevel($scoreVal);
                                    $lvlBadge = ($scoreVal >= 3.6) ? 'bg-success' : (($scoreVal >= 2.6) ? 'bg-info text-dark' : (($scoreVal >= 2.0) ? 'bg-warning text-dark' : 'bg-danger'));
                                ?>
                                    <div class="p-2 bg-light rounded-3 d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-bold small"><?php echo date('F d, Y', strtotime($phItem['display_date'])); ?></div>
                                            <div class="text-muted" style="font-size:0.72rem;"><?php echo e($phItem['evaluation_type'] ?? 'Annual'); ?> Evaluation</div>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge <?php echo $lvlBadge; ?>"><?php echo number_format($scoreVal, 2); ?></span>
                                            <div class="text-muted" style="font-size:0.68rem;"><?php echo e($lvl); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <script src="<?php echo BASE_URL; ?>/assets/vendor/chartjs/chart.umd.min.js"></script>
                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const ctx = document.getElementById('empPerformanceTrendChart').getContext('2d');
                        new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: <?php echo json_encode($chart_labels); ?>,
                                datasets: [{
                                    label: 'Evaluation Score (1.00 - 4.00)',
                                    data: <?php echo json_encode($chart_scores); ?>,
                                    borderColor: '#BD9414',
                                    backgroundColor: 'rgba(189, 148, 20, 0.15)',
                                    borderWidth: 3,
                                    fill: true,
                                    tension: 0.35,
                                    pointBackgroundColor: '#294306',
                                    pointRadius: 5,
                                    pointHoverRadius: 7,
                                    clip: false
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                layout: {
                                    padding: { top: 14, right: 16, bottom: 6, left: 6 }
                                },
                                scales: {
                                    x: {
                                        offset: true
                                    },
                                    y: {
                                        min: 1.0,
                                        max: 4.0,
                                        ticks: { stepSize: 0.5 }
                                    }
                                },
                                plugins: {
                                    legend: { display: false }
                                }
                            }
                        });
                    });
                    </script>
                <?php endif; ?>
                </div>
            </div>

            <div class="performance-career-panel" id="career-panel">
                <div class="employee-section-header">
                    <div>
                        <div class="employee-section-kicker"><i class="fas fa-route text-warning me-1"></i>Career Progression</div>
                        <h5 class="mb-0">Career Movement History</h5>
                    </div>
                    <span class="badge bg-secondary px-3 py-2"><?php echo count($cm_history); ?> Record<?php echo count($cm_history) !== 1 ? 's' : ''; ?></span>
                </div>
                <div class="card-body">
                    <?php if (empty($cm_history)): ?>
                        <div class="empty-state">
                            <i class="fas fa-route d-block mb-2" style="font-size:1.8rem;opacity:.3;"></i>
                            <p class="mb-0 text-muted">No approved career movements on record for this employee.</p>
                        </div>
                    <?php else: ?>
                        <div class="employee-table-wrap">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Effective Date</th>
                                        <th>Type</th>
                                        <th>From Position</th>
                                        <th>To Position</th>
                                        <th>From Branch</th>
                                        <th>To Branch</th>
                                        <th>Processed By</th>
                                        <th>Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cm_history as $cm):
                                        $typeBadge = match($cm['movement_type']) {
                                            'Promotion'   => 'bg-success',
                                            'Transfer'    => 'bg-info text-dark',
                                            'Demotion'    => 'bg-danger',
                                            'Role Change' => 'bg-primary',
                                            default       => 'bg-secondary',
                                        };
                                    ?>
                                    <tr>
                                        <td data-label="Effective Date" class="fw-semibold"><?php echo formatDate($cm['effective_date']); ?></td>
                                        <td data-label="Type"><span class="badge <?php echo $typeBadge; ?>"><?php echo e($cm['movement_type']); ?></span></td>
                                        <td data-label="From Position" class="text-muted small"><?php echo e($cm['previous_position'] ?: '—'); ?></td>
                                        <td data-label="To Position" class="fw-bold text-success"><?php echo e($cm['new_position']); ?></td>
                                        <td data-label="From Branch" class="text-muted small"><?php echo e($cm['from_branch_name'] ?: 'N/A'); ?></td>
                                        <td data-label="To Branch" class="fw-semibold"><?php echo e($cm['to_branch_name'] ?: 'Same Branch'); ?></td>
                                        <td data-label="Processed By" class="small"><?php echo e($cm['approved_by_name'] ?: ($cm['logged_by_name'] ?: 'HR Manager')); ?></td>
                                        <td data-label="Reason" class="small"><?php echo e($cm['reason'] ?: 'N/A'); ?></td>
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
    
    <div class="ep-section-row" id="section-personal">
        <div class="ep-section-header" onclick="toggleSection('personal')">
            <div class="ep-section-icon bg-light-primary"><i class="fas fa-user"></i></div>
            <div class="ep-section-title-wrap">
                <h3 class="ep-section-title">Personal Information</h3>
                <p class="ep-section-desc">Basic personal details such as name, birth date, gender, and civil status.</p>
            </div>
            <div class="ep-section-actions">
                <?php echo (!empty($emp['date_of_birth']) || !empty($emp['gender'])) ? '<span class="badge bg-success">Complete</span>' : '<span class="badge bg-secondary">Incomplete</span>'; ?>
                <button class='btn btn-sm btn-outline-success'>View Details</button>
                <i class="fas fa-chevron-down ep-chevron " id="chevron-personal"></i>
            </div>
        </div>
        <div class="ep-section-content " id="content-personal">
                        <div class="col-12">
                <div class="content-card employee-section-card h-100" data-profile-panel="personal">
                    <div class="employee-section-header">
                        <div>
                            <div class="employee-section-kicker"><i class="fas fa-user"></i>Personal</div>
                            <h5 class="mb-0">Personal Information</h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="detail-grid">
                            <?php
                            echo field('Surname', $emp['last_name']);
                            echo field('First Name', $emp['first_name']);
                            echo field('Middle Name', $emp['middle_name']);
                            echo field('Name Extension', $emp['name_extension']);
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
                            echo field('Height', $emp['height_m'] ? $emp['height_m'] . ' m' : '');
                            echo field('Weight', $emp['weight_kg'] ? $emp['weight_kg'] . ' kg' : '');
                            echo field('Blood Type', $emp['blood_type']);
                            echo field('Citizenship', $emp['citizenship']);
                            ?>
                        </div>
                    </div>
                </div>
        </div>
    </div>
</div>
    
    <div class="ep-section-row" id="section-contact">
        <div class="ep-section-header" onclick="toggleSection('contact')">
            <div class="ep-section-icon bg-light-info"><i class="fas fa-phone"></i></div>
            <div class="ep-section-title-wrap">
                <h3 class="ep-section-title">Contact & Address</h3>
                <p class="ep-section-desc">Contact information, current and permanent address.</p>
            </div>
            <div class="ep-section-actions">
                <?php echo (!empty($emp['mobile_number']) || !empty($resAddr)) ? '<span class="badge bg-success">Complete</span>' : '<span class="badge bg-secondary">Incomplete</span>'; ?>
                <button class='btn btn-sm btn-outline-success'>View Details</button>
                <i class="fas fa-chevron-down ep-chevron " id="chevron-contact"></i>
            </div>
        </div>
        <div class="ep-section-content " id="content-contact">
                        <div class="col-12">
                <div class="content-card employee-section-card h-100" data-profile-panel="contact">
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
                                echo field('Mobile', $emp['contact_number']);
                                echo field('Email', $emp['email']);
                                ?>
                            </div>
                        </div>
                        <div class="employee-subsection">
                            <div class="employee-subsection-title">Residential Address</div>
                            <div class="detail-grid">
                                <?php echo field('Address', $resAddr); ?>
                            </div>
                        </div>
                        <div class="employee-subsection">
                            <div class="employee-subsection-title">Permanent Address</div>
                            <div class="detail-grid">
                                <?php echo field('Address', $permAddr); ?>
                            </div>
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
    </div>
</div>
    
    <div class="ep-section-row" id="section-family">
        <div class="ep-section-header" onclick="toggleSection('family')">
            <div class="ep-section-icon bg-light-danger"><i class="fas fa-people-group"></i></div>
            <div class="ep-section-title-wrap">
                <h3 class="ep-section-title">Family Information</h3>
                <p class="ep-section-desc">Spouse, children, parents, and siblings.</p>
            </div>
            <div class="ep-section-actions">
                <?php echo (empty($family) && empty($children)) ? '<span class="badge bg-secondary">No records</span>' : '<span class="badge bg-success">Has records</span>'; ?>
                <button class='btn btn-sm btn-outline-success'>+ Add Record</button>
                <i class="fas fa-chevron-down ep-chevron " id="chevron-family"></i>
            </div>
        </div>
        <div class="ep-section-content " id="content-family">
                        <div class="col-12">
                <div class="content-card employee-section-card" data-profile-panel="family">
                    <div class="employee-section-header">
                        <div>
                            <div class="employee-section-kicker"><i class="fas fa-heart"></i>Family</div>
                            <h5 class="mb-0">Family Information</h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 mb-3">
                            <div class="col-lg-4">
                                <div class="employee-subsection h-100">
                                    <div class="employee-subsection-title">Spouse</div>
                                    <div class="detail-grid">
                                        <?php
                                        echo field('Name', $spouseName);
                                        echo field('Occupation', $emp['spouse_occupation']);
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="employee-subsection h-100">
                                    <div class="employee-subsection-title">Father</div>
                                    <div class="detail-grid">
                                        <?php
                                        echo field('Name', $fatherName);
                                        echo field('Occupation', $emp['father_occupation']);
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="employee-subsection h-100">
                                    <div class="employee-subsection-title">Mother (Maiden)</div>
                                    <div class="detail-grid">
                                        <?php
                                        echo field('Name', $motherName);
                                        echo field('Occupation', $emp['mother_occupation'] ?? '');
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-xl-6">
                                <div class="employee-subsection h-100">
                                    <div class="employee-subsection-title">Children<?php echo !empty($children) ? ' (' . count($children) . ')' : ''; ?></div>
                                    <?php if (!empty($children)): ?>
                                        <div class="employee-table-wrap">
                                            <table class="table table-sm align-middle mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>Date of Birth</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($children as $ch): ?>
                                                        <tr>
                                                            <td data-label="Name"><?php echo e(trim($ch['first_name'] . ' ' . $ch['middle_name'] . ' ' . $ch['surname'])); ?></td>
                                                            <td data-label="Date of Birth"><?php echo $ch['date_of_birth'] ? formatDate($ch['date_of_birth']) : 'N/A'; ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="empty-state"><i class="fas fa-child d-block"></i><p>No children recorded.</p></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="col-xl-6">
                                <div class="employee-subsection h-100">
                                    <div class="employee-subsection-title">Siblings<?php echo !empty($siblings) ? ' (' . count($siblings) . ')' : ''; ?></div>
                                    <?php if (!empty($siblings)): ?>
                                        <div class="employee-table-wrap">
                                            <table class="table table-sm align-middle mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>Date of Birth</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($siblings as $sb): ?>
                                                        <tr>
                                                            <td data-label="Name"><?php echo e(trim($sb['first_name'] . ' ' . $sb['middle_name'] . ' ' . $sb['surname'])); ?></td>
                                                            <td data-label="Date of Birth"><?php echo $sb['date_of_birth'] ? formatDate($sb['date_of_birth']) : 'N/A'; ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="empty-state"><i class="fas fa-users d-block"></i><p>No siblings recorded.</p></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
        </div>
    </div>
</div>
    
    <div class="ep-section-row" id="section-education">
        <div class="ep-section-header" onclick="toggleSection('education')">
            <div class="ep-section-icon bg-light-success"><i class="fas fa-graduation-cap"></i></div>
            <div class="ep-section-title-wrap">
                <h3 class="ep-section-title">Education Background</h3>
                <p class="ep-section-desc">Educational attainment and academic records.</p>
            </div>
            <div class="ep-section-actions">
                <?php echo empty($education) ? '<span class="badge bg-secondary">No records</span>' : '<span class="badge bg-success">' . count($education) . ' records</span>'; ?>
                <button class='btn btn-sm btn-outline-success'>+ Add Record</button>
                <i class="fas fa-chevron-down ep-chevron " id="chevron-education"></i>
            </div>
        </div>
        <div class="ep-section-content " id="content-education">
            <div class="col-12">
                <div class="content-card employee-section-card h-100" data-profile-panel="education">
                    <div class="employee-section-header">
                        <div>
                            <div class="employee-section-kicker"><i class="fas fa-graduation-cap"></i>Education</div>
                            <h5 class="mb-0">Education Background</h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($education)): ?>
                            <div class="employee-table-wrap">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Level</th>
                                            <th>School</th>
                                            <th>Degree</th>
                                            <th>Period</th>
                                            <th>Year Grad</th>
                                            <th>Honors</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($education as $ed): ?>
                                            <tr>
                                                <td data-label="Level"><span class="badge bg-info"><?php echo e($ed['education_level']); ?></span></td>
                                                <td data-label="School" class="fw-bold"><?php echo e($ed['school_name']); ?></td>
                                                <td data-label="Degree"><?php echo e($ed['degree_course'] ?: 'N/A'); ?></td>
                                                <td data-label="Period"><?php echo ($ed['period_from'] ? formatDate($ed['period_from'], 'Y') : '') . ' - ' . ($ed['period_to'] ? formatDate($ed['period_to'], 'Y') : ''); ?></td>
                                                <td data-label="Year Grad"><?php echo e($ed['year_graduated'] ?: 'N/A'); ?></td>
                                                <td data-label="Honors"><?php echo e($ed['honors_received'] ?: 'N/A'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state"><i class="fas fa-graduation-cap d-block"></i><p>No education records.</p></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="ep-section-row" id="section-work">
        <div class="ep-section-header" onclick="toggleSection('work')">
            <div class="ep-section-icon bg-light-primary"><i class="fas fa-briefcase"></i></div>
            <div class="ep-section-title-wrap">
                <h3 class="ep-section-title">Work Experience</h3>
                <p class="ep-section-desc">Previous and current work experience.</p>
            </div>
            <div class="ep-section-actions">
                <?php echo empty($work) ? '<span class="badge bg-secondary">No records</span>' : '<span class="badge bg-success">' . count($work) . ' records</span>'; ?>
                <button class='btn btn-sm btn-outline-success'>+ Add Record</button>
                <i class="fas fa-chevron-down ep-chevron " id="chevron-work"></i>
            </div>
        </div>
        <div class="ep-section-content " id="content-work">
            <div class="col-12">
                <div class="content-card employee-section-card h-100" data-profile-panel="training">
                    <div class="employee-section-header">
                        <div>
                            <div class="employee-section-kicker"><i class="fas fa-briefcase"></i>Work</div>
                            <h5 class="mb-0">Work Experience</h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($work)): ?>
                            <div class="employee-table-wrap">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Period</th>
                                            <th>Position & Company</th>
                                            <th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($work as $w): ?>
                                            <tr>
                                                <td data-label="Period">
                                                    <div class="fw-bold text-primary"><?php echo ($w['date_from'] ? formatDate($w['date_from'], 'Y') : 'N/A'); ?></div>
                                                    <div class="small text-muted">to</div>
                                                    <div class="fw-bold"><?php echo ($w['date_to'] ? formatDate($w['date_to'], 'Y') : 'Present'); ?></div>
                                                </td>
                                                <td data-label="Position & Company">
                                                    <div class="fw-bold mb-1"><?php echo e($w['job_title']); ?></div>
                                                    <div class="text-muted small"><i class="fas fa-building me-1"></i><?php echo e($w['company_name']); ?></div>
                                                </td>
                                                <td data-label="Details">
                                                    <div class="small"><strong>Salary:</strong> <?php echo $w['monthly_salary'] ? '₱' . number_format($w['monthly_salary'], 2) : 'N/A'; ?></div>
                                                    <div class="small"><strong>Status:</strong> <?php echo e($w['appointment_status'] ?: 'N/A'); ?></div>
                                                    <?php if ($w['reason_for_leaving']): ?>
                                                        <div class="small text-danger"><strong>Leaving:</strong> <?php echo e($w['reason_for_leaving']); ?></div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state"><i class="fas fa-briefcase d-block"></i><p>No work experience recorded.</p></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="ep-section-row" id="section-training">
        <div class="ep-section-header" onclick="toggleSection('training')">
            <div class="ep-section-icon bg-light-warning"><i class="fas fa-certificate"></i></div>
            <div class="ep-section-title-wrap">
                <h3 class="ep-section-title">Training, Eligibility & Licenses</h3>
                <p class="ep-section-desc">Trainings, seminars, eligibility and professional licenses.</p>
            </div>
            <div class="ep-section-actions">
                <?php echo (empty($trainings) && empty($eligibility)) ? '<span class="badge bg-secondary">No records</span>' : '<span class="badge bg-success">' . (count($trainings) + count($eligibility)) . ' records</span>'; ?>
                <button class='btn btn-sm btn-outline-success'>+ Add Record</button>
                <i class="fas fa-chevron-down ep-chevron " id="chevron-training"></i>
            </div>
        </div>
        <div class="ep-section-content " id="content-training">
            <div class="col-12">
                <div class="content-card employee-section-card" data-profile-panel="training">
                    <div class="employee-section-header">
                        <div>
                            <div class="employee-section-kicker"><i class="fas fa-certificate"></i>Development</div>
                            <h5 class="mb-0">Training, Eligibility & Professional Development</h5>
                        </div>
                    </div>
                    <div class="card-body employee-card-grid">
                        <div class="employee-subsection">
                            <div class="employee-subsection-title">Training Programs</div>
                            <?php if (!empty($trainings)): ?>
                                <div class="employee-table-wrap">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Period</th>
                                                <th>Training Details</th>
                                                <th>Hours & Conducted By</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($trainings as $t): ?>
                                                <tr>
                                                    <td data-label="Period">
                                                        <div class="fw-bold text-primary"><?php echo ($t['date_from'] ? formatDate($t['date_from'], 'Y') : 'N/A'); ?></div>
                                                        <div class="small text-muted">to</div>
                                                        <div class="fw-bold"><?php echo ($t['date_to'] ? formatDate($t['date_to'], 'Y') : 'N/A'); ?></div>
                                                    </td>
                                                    <td data-label="Training Details">
                                                        <div class="fw-bold mb-1"><?php echo e($t['training_title']); ?></div>
                                                        <div class="badge bg-secondary small"><?php echo e($t['training_type'] ?: 'General'); ?></div>
                                                    </td>
                                                    <td data-label="Hours & Conducted By">
                                                        <div class="small"><strong>Duration:</strong> <?php echo $t['no_of_hours'] ? (float) $t['no_of_hours'] . ' hrs' : 'N/A'; ?></div>
                                                        <div class="small"><strong>Conducted By:</strong> <?php echo e($t['conducted_by'] ?: 'N/A'); ?></div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state"><i class="fas fa-chalkboard-teacher d-block"></i><p>No training records.</p></div>
                            <?php endif; ?>
                        </div>

                        <div class="employee-subsection">
                            <div class="employee-subsection-title">Voluntary Work</div>
                            <?php if (!empty($voluntary)): ?>
                                <div class="employee-table-wrap">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Period</th>
                                                <th>Organization & Position</th>
                                                <th>Details</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($voluntary as $vol): ?>
                                                <tr>
                                                    <td data-label="Period">
                                                        <div class="fw-bold text-primary"><?php echo ($vol['date_from'] ? formatDate($vol['date_from'], 'Y') : 'N/A'); ?></div>
                                                        <div class="small text-muted">to</div>
                                                        <div class="fw-bold"><?php echo ($vol['date_to'] ? formatDate($vol['date_to'], 'Y') : 'N/A'); ?></div>
                                                    </td>
                                                    <td data-label="Organization & Position">
                                                        <div class="fw-bold mb-1"><?php echo e($vol['organization_name']); ?></div>
                                                        <div class="small text-muted"><i class="fas fa-user-tag me-1"></i><?php echo e($vol['position_nature'] ?: 'N/A'); ?></div>
                                                    </td>
                                                    <td data-label="Details">
                                                        <div class="small"><strong>Total Hours:</strong> <?php echo $vol['no_of_hours'] ? (float) $vol['no_of_hours'] . ' hrs' : 'N/A'; ?></div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state"><i class="fas fa-hands-helping d-block"></i><p>No voluntary work records.</p></div>
                            <?php endif; ?>
                        </div>

                        <div class="employee-subsection">
                            <div class="employee-subsection-title">Eligibility & Licenses</div>
                            <?php if (!empty($eligibility)): ?>
                                <div class="employee-table-wrap">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Title & License</th>
                                                <th>Validity</th>
                                                <th>Exam Info</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($eligibility as $el): ?>
                                                <tr>
                                                    <td data-label="Title & License">
                                                        <div class="fw-bold text-primary mb-1"><?php echo e($el['license_title']); ?></div>
                                                        <div class="small text-muted">No: <?php echo e($el['license_number'] ?: 'N/A'); ?></div>
                                                    </td>
                                                    <td data-label="Validity">
                                                        <div class="fw-bold text-primary"><?php echo ($el['date_from'] ? formatDate($el['date_from'], 'Y') : 'N/A'); ?></div>
                                                        <div class="small text-muted">to</div>
                                                        <div class="fw-bold"><?php echo ($el['date_to'] ? formatDate($el['date_to'], 'Y') : 'N/A'); ?></div>
                                                    </td>
                                                    <td data-label="Exam Info">
                                                        <div class="small"><strong>Date:</strong> <?php echo $el['date_of_exam'] ? formatDate($el['date_of_exam']) : 'N/A'; ?></div>
                                                        <div class="small"><strong>Place:</strong> <?php echo e($el['place_of_exam'] ?: 'N/A'); ?></div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state"><i class="fas fa-id-badge d-block"></i><p>No eligibility or license records.</p></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
    <div class="ep-section-row" id="section-skills">
        <div class="ep-section-header" onclick="toggleSection('skills')">
            <div class="ep-section-icon bg-light-purple"><i class="fas fa-star"></i></div>
            <div class="ep-section-title-wrap">
                <h3 class="ep-section-title">Skills, Hobbies, Recognitions & Memberships</h3>
                <p class="ep-section-desc">Skills, hobbies, awards and professional memberships.</p>
            </div>
            <div class="ep-section-actions">
                <?php echo (empty($skills) && empty($recognitions) && empty($memberships)) ? '<span class="badge bg-secondary">No records</span>' : '<span class="badge bg-success">' . (count($skills) + count($recognitions) + count($memberships)) . ' records</span>'; ?>
                <button class='btn btn-sm btn-outline-success'>+ Add Record</button>
                <i class="fas fa-chevron-down ep-chevron " id="chevron-skills"></i>
            </div>
        </div>
        <div class="ep-section-content " id="content-skills">
            
                        <div class="row g-3">
                            <div class="col-lg-4">
                                <div class="employee-subsection h-100">
                                    <div class="employee-subsection-title">Skills & Hobbies</div>
                                    <?php if (!empty($skills)): ?>
                                        <div class="badge-cloud">
                                            <?php foreach ($skills as $sk): ?>
                                                <span class="badge bg-info"><?php echo e($sk['skill_name']); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="empty-state"><i class="fas fa-star d-block"></i><p>No skills recorded.</p></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="employee-subsection h-100">
                                    <div class="employee-subsection-title">Recognitions</div>
                                    <?php if (!empty($recognitions)): ?>
                                        <div class="list-group list-group-flush border rounded-3 recognition-list">
                                            <?php foreach ($recognitions as $rc): ?>
                                                <div class="list-group-item">
                                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                                        <h6 class="mb-1 fw-bold"><?php echo e($rc['recognition_title']); ?></h6>
                                                        <?php if (!empty($rc['date_awarded'])): ?>
                                                            <small class="text-muted"><?php echo formatDate($rc['date_awarded'], 'M d, Y'); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if (!empty($rc['issued_by'])): ?>
                                                        <small class="text-primary"><?php echo e($rc['issued_by']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="empty-state"><i class="fas fa-award d-block"></i><p>No recognitions recorded.</p></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="employee-subsection h-100">
                                    <div class="employee-subsection-title">Memberships</div>
                                    <?php if (!empty($memberships)): ?>
                                        <div class="badge-cloud">
                                            <?php foreach ($memberships as $mb): ?>
                                                <span class="badge bg-secondary"><?php echo e($mb['organization_name']); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="empty-state"><i class="fas fa-users-cog d-block"></i><p>No memberships recorded.</p></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
    </div>
    
    <div class="ep-section-row" id="section-disclosures">
        <div class="ep-section-header" onclick="toggleSection('disclosures')">
            <div class="ep-section-icon bg-light-danger"><i class="fas fa-clipboard-list"></i></div>
            <div class="ep-section-title-wrap">
                <h3 class="ep-section-title">Disclosures & Government IDs</h3>
                <p class="ep-section-desc">Personal disclosures, government IDs and employment details.</p>
            </div>
            <div class="ep-section-actions">
                <?php echo empty($discList) ? '<span class="badge bg-success">Complete</span>' : '<span class="badge bg-success">' . count($discList) . ' records</span>'; ?>
                <button class='btn btn-sm btn-outline-success'>View Details</button>
                <i class="fas fa-chevron-down ep-chevron " id="chevron-disclosures"></i>
            </div>
        </div>
        <div class="ep-section-content " id="content-disclosures">
            <div class="row g-4">
                <div class="col-xl-6">
                <div class="content-card employee-section-card h-100" data-profile-panel="documents">
                    <div class="employee-section-header">
                        <div>
                            <div class="employee-section-kicker"><i class="fas fa-clipboard-list"></i>Disclosures</div>
                            <h5 class="mb-0">Personal Disclosures</h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="disclosure-list">
                            <?php foreach ($discList as $d): ?>
                                <div class="disclosure-item">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <?php echo yn($emp[$d[0]]); ?>
                                        <span class="fw-semibold"><?php echo $d[2]; ?></span>
                                    </div>
                                    <?php if (!empty($emp[$d[1]])): ?>
                                        <div class="small text-muted"><?php echo e($emp[$d[1]]); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="content-card employee-section-card h-100" data-profile-panel="employment">
                    <div class="employee-section-header">
                        <div>
                            <div class="employee-section-kicker"><i class="fas fa-id-card"></i>Employment</div>
                            <h5 class="mb-0">Government IDs & Employment</h5>
                        </div>
                    </div>
                    <div class="card-body">
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
                        <div class="employee-subsection">
                            <div class="employee-subsection-title">Employment Details</div>
                            <div class="detail-grid">
                                <?php
                                echo field('Department', $emp['department_name']);
                                echo field('Job Title', $emp['job_title']);
                                echo field('Branch', $emp['branch_name']);
                                echo field('Hire Date', formatDate($emp['hire_date']));
                                echo field('Employment Status', $emp['employment_status']);
                                echo field('Employment Type', $emp['employment_type']);
                                if (!$emp['is_active'] || !empty($emp['separation_date'])) {
                                    echo field('Separation Effective Date', !empty($emp['separation_date']) ? formatDate($emp['separation_date']) : 'N/A');
                                    if (!empty($emp['separation_remarks'])) {
                                        echo field('Separation Remarks', $emp['separation_remarks']);
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
        </div>
    </div>
    </div>
</div>
    
    <div class="ep-section-row" id="section-assets">
        <div class="ep-section-header" onclick="toggleSection('assets')">
            <div class="ep-section-icon bg-light-info"><i class="fas fa-home"></i></div>
            <div class="ep-section-title-wrap">
                <h3 class="ep-section-title">Assets, Properties & Liabilities</h3>
                <p class="ep-section-desc">Declared assets, properties, and liabilities.</p>
            </div>
            <div class="ep-section-actions">
                <?php echo (empty($real_props) && empty($personal_props) && empty($liabilities)) ? '<span class="badge bg-secondary">No records</span>' : '<span class="badge bg-success">' . (count($real_props) + count($personal_props) + count($liabilities)) . ' records</span>'; ?>
                <button class='btn btn-sm btn-outline-success'>+ Add Record</button>
                <i class="fas fa-chevron-down ep-chevron " id="chevron-assets"></i>
            </div>
        </div>
        <div class="ep-section-content " id="content-assets">
                        <div class="col-12">
                <div class="content-card employee-section-card" data-profile-panel="documents">
                    <div class="employee-section-header">
                        <div>
                            <div class="employee-section-kicker"><i class="fas fa-file-invoice-dollar"></i>SALN</div>
                            <h5 class="mb-0">Assets, Properties & Liabilities</h5>
                        </div>
                    </div>
                    <div class="card-body employee-card-grid">
                        <div class="employee-subsection">
                            <div class="employee-subsection-title">Real Properties</div>
                            <?php if (!empty($real_props)): ?>
                                <div class="employee-table-wrap">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Description & Kind</th>
                                                <th>Location</th>
                                                <th>Values</th>
                                                <th>Acquisition</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($real_props as $rp): ?>
                                                <tr>
                                                    <td data-label="Description & Kind">
                                                        <div class="fw-bold"><?php echo e($rp['description']); ?></div>
                                                        <div class="small text-muted"><?php echo e($rp['kind']); ?></div>
                                                    </td>
                                                    <td data-label="Location"><?php echo e($rp['exact_location']); ?></td>
                                                    <td data-label="Values">
                                                        <div class="small">Assessed: ₱<?php echo number_format($rp['assessed_value'], 2); ?></div>
                                                        <div class="small">Market: ₱<?php echo number_format($rp['market_value'], 2); ?></div>
                                                    </td>
                                                    <td data-label="Acquisition">
                                                        <div class="small"><?php echo e($rp['acquisition_year_mode']); ?></div>
                                                        <div class="small fw-bold">Cost: ₱<?php echo number_format($rp['acquisition_cost'], 2); ?></div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state"><i class="fas fa-home d-block"></i><p>No real properties recorded.</p></div>
                            <?php endif; ?>
                        </div>

                        <div class="employee-subsection">
                            <div class="employee-subsection-title">Personal Properties</div>
                            <?php if (!empty($personal_props)): ?>
                                <div class="employee-table-wrap">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Description</th>
                                                <th>Year Acquired</th>
                                                <th>Acquisition Cost</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($personal_props as $pp): ?>
                                                <tr>
                                                    <td data-label="Description"><?php echo e($pp['description']); ?></td>
                                                    <td data-label="Year Acquired"><?php echo e($pp['year_acquired']); ?></td>
                                                    <td data-label="Acquisition Cost">₱<?php echo number_format($pp['acquisition_cost'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state"><i class="fas fa-car d-block"></i><p>No personal properties recorded.</p></div>
                            <?php endif; ?>
                        </div>

                        <div class="employee-subsection">
                            <div class="employee-subsection-title">Liabilities</div>
                            <?php if (!empty($liabilities)): ?>
                                <div class="employee-table-wrap">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Nature of Liability</th>
                                                <th>Name of Creditor</th>
                                                <th>Outstanding Balance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($liabilities as $liab): ?>
                                                <tr>
                                                    <td data-label="Nature of Liability"><?php echo e($liab['nature_of_liability']); ?></td>
                                                    <td data-label="Name of Creditor"><?php echo e($liab['creditor_name']); ?></td>
                                                    <td data-label="Outstanding Balance">₱<?php echo number_format($liab['outstanding_balance'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state"><i class="fas fa-file-invoice-dollar d-block"></i><p>No liabilities recorded.</p></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="ep-section-row" id="section-references">
        <div class="ep-section-header" onclick="toggleSection('references')">
            <div class="ep-section-icon bg-light-success"><i class="fas fa-address-book"></i></div>
            <div class="ep-section-title-wrap">
                <h3 class="ep-section-title">Character References</h3>
                <p class="ep-section-desc">Character references.</p>
            </div>
            <div class="ep-section-actions">
                <?php echo empty($refs) ? '<span class="badge bg-secondary">No records</span>' : '<span class="badge bg-success">' . count($refs) . ' records</span>'; ?>
                <button class='btn btn-sm btn-outline-success'>+ Add Record</button>
                <i class="fas fa-chevron-down ep-chevron " id="chevron-references"></i>
            </div>
        </div>
        <div class="ep-section-content " id="content-references">
                        <div class="col-12">
                <div class="content-card employee-section-card" data-profile-panel="documents">
                    <div class="employee-section-header">
                        <div>
                            <div class="employee-section-kicker"><i class="fas fa-address-book"></i>References</div>
                            <h5 class="mb-0">Character References</h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($refs)): ?>
                            <div class="employee-table-wrap">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Full Name</th>
                                            <th>Address</th>
                                            <th>Contact Number</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($refs as $rf): ?>
                                            <tr>
                                                <td data-label="Full Name" class="fw-bold"><?php echo e($rf['reference_name']); ?></td>
                                                <td data-label="Address"><?php echo e($rf['reference_address'] ?: 'N/A'); ?></td>
                                                <td data-label="Contact Number"><?php echo e($rf['reference_telephone'] ?: 'N/A'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state"><i class="fas fa-address-book d-block"></i><p>No character references recorded.</p></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="ep-section-row" id="section-history">
        <div class="ep-section-header" onclick="toggleSection('history')">
            <div class="ep-section-icon bg-light-gray"><i class="fas fa-clock-rotate-left"></i></div>
            <div class="ep-section-title-wrap">
                <h3 class="ep-section-title">Profile Edit History</h3>
                <p class="ep-section-desc">Track changes made to this employee's profile.</p>
            </div>
            <div class="ep-section-actions">
                <span class="badge bg-success">History</span>
                <button class='btn btn-sm btn-outline-success'>View History</button>
                <i class="fas fa-chevron-down ep-chevron " id="chevron-history"></i>
            </div>
        </div>
        <div class="ep-section-content " id="content-history">
                        <?php require_once '../includes/employee-edit-history-card.php'; ?>
        </div>
    </div>
    

    </div>
    
    <div class="col-lg-4 profile-sticky-col">
        <!-- 1. Quick Navigation Card (Top) -->
        <div class="ep-sidebar-card">
            <h4 class="ep-sidebar-title"><i class="fas fa-compass text-primary"></i> Quick Navigation</h4>
            <ul class="ep-quick-nav">
                <li><a href="javascript:void(0)" onclick="scrollToSection('performance')"><div class="ep-quick-nav-icon"><i class="fas fa-chart-line"></i></div> <span>Performance & Career</span> <i class="fas fa-chevron-right ms-auto" style="font-size:0.65rem; color:#9ca3af;"></i></a></li>
                <li><a href="javascript:void(0)" onclick="scrollToSection('personal')"><div class="ep-quick-nav-icon"><i class="fas fa-user"></i></div> <span>Personal Information</span> <i class="fas fa-chevron-right ms-auto" style="font-size:0.65rem; color:#9ca3af;"></i></a></li>
                <li><a href="javascript:void(0)" onclick="scrollToSection('contact')"><div class="ep-quick-nav-icon"><i class="fas fa-phone"></i></div> <span>Contact & Address</span> <i class="fas fa-chevron-right ms-auto" style="font-size:0.65rem; color:#9ca3af;"></i></a></li>
                <li><a href="javascript:void(0)" onclick="scrollToSection('family')"><div class="ep-quick-nav-icon"><i class="fas fa-people-group"></i></div> <span>Family Information</span> <i class="fas fa-chevron-right ms-auto" style="font-size:0.65rem; color:#9ca3af;"></i></a></li>
                <li><a href="javascript:void(0)" onclick="scrollToSection('education')"><div class="ep-quick-nav-icon"><i class="fas fa-graduation-cap"></i></div> <span>Education Background</span> <i class="fas fa-chevron-right ms-auto" style="font-size:0.65rem; color:#9ca3af;"></i></a></li>
                <li><a href="javascript:void(0)" onclick="scrollToSection('work')"><div class="ep-quick-nav-icon"><i class="fas fa-briefcase"></i></div> <span>Work Experience</span> <i class="fas fa-chevron-right ms-auto" style="font-size:0.65rem; color:#9ca3af;"></i></a></li>
                <li><a href="javascript:void(0)" onclick="scrollToSection('training')"><div class="ep-quick-nav-icon"><i class="fas fa-certificate"></i></div> <span>Training, Eligibility & Licenses</span> <i class="fas fa-chevron-right ms-auto" style="font-size:0.65rem; color:#9ca3af;"></i></a></li>
                <li><a href="javascript:void(0)" onclick="scrollToSection('skills')"><div class="ep-quick-nav-icon"><i class="fas fa-star"></i></div> <span>Skills, Hobbies, Recognitions</span> <i class="fas fa-chevron-right ms-auto" style="font-size:0.65rem; color:#9ca3af;"></i></a></li>
                <li><a href="javascript:void(0)" onclick="scrollToSection('disclosures')"><div class="ep-quick-nav-icon"><i class="fas fa-clipboard-list"></i></div> <span>Disclosures & Government IDs</span> <i class="fas fa-chevron-right ms-auto" style="font-size:0.65rem; color:#9ca3af;"></i></a></li>
                <li><a href="javascript:void(0)" onclick="scrollToSection('assets')"><div class="ep-quick-nav-icon"><i class="fas fa-home"></i></div> <span>Assets, Properties & Liabilities</span> <i class="fas fa-chevron-right ms-auto" style="font-size:0.65rem; color:#9ca3af;"></i></a></li>
                <li><a href="javascript:void(0)" onclick="scrollToSection('references')"><div class="ep-quick-nav-icon"><i class="fas fa-address-book"></i></div> <span>Character References</span> <i class="fas fa-chevron-right ms-auto" style="font-size:0.65rem; color:#9ca3af;"></i></a></li>
                <li><a href="javascript:void(0)" onclick="scrollToSection('history')"><div class="ep-quick-nav-icon"><i class="fas fa-clock-rotate-left"></i></div> <span>Profile Edit History</span> <i class="fas fa-chevron-right ms-auto" style="font-size:0.65rem; color:#9ca3af;"></i></a></li>
            </ul>
        </div>

        <!-- 2. Profile Completion Card -->
        <div class="ep-sidebar-card">
            <h4 class="ep-sidebar-title"><i class="fas fa-shield-check text-primary"></i> Profile Completion</h4>
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="ep-completion-ring">
                    <svg width="80" height="80" viewBox="0 0 80 80">
                      <circle cx="40" cy="40" r="32" fill="none" stroke="#eef2e8" stroke-width="8"/>
                      <circle cx="40" cy="40" r="32" fill="none" stroke="#294306" stroke-width="8"
                        stroke-dasharray="201" stroke-dashoffset="<?php echo round(201 * (1 - $completionPct/100)); ?>"
                        stroke-linecap="round"/>
                    </svg>
                    <div class="position-absolute top-50 start-50 translate-middle fw-bold" style="font-size: 1.1rem; color: #294306;"><?php echo $completionPct; ?>%</div>
                </div>
                <div>
                    <div class="text-muted small" style="font-size:0.75rem; text-transform:uppercase; font-weight:700; letter-spacing:0.04em;">Profile Completion</div>
                    <div class="fw-bold fs-5 text-dark lh-1 mb-1"><?php echo $completionPct; ?>%</div>
                    <div class="small text-muted" style="font-size:0.75rem;"><?php echo $completedCount; ?> of <?php echo $totalSections; ?> sections completed</div>
                </div>
            </div>
            <div class="progress mb-3" style="height: 6px; border-radius: 99px; background:#eef2e8;">
                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $completionPct; ?>%; border-radius: 99px;" aria-valuenow="<?php echo $completionPct; ?>" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <?php if (!empty($missingSections)): ?>
                <div class="p-3 rounded-3" style="background-color: #fffbeb; border: 1px solid #fef3c7;">
                    <div class="fw-bold mb-2 text-danger small d-flex align-items-center gap-1" style="font-size:0.78rem;">
                        <i class="fas fa-exclamation-triangle text-warning"></i>
                        <span>Missing Information</span>
                    </div>
                    <div class="small text-muted mb-2" style="font-size:0.72rem;"><?php echo count($missingSections); ?> sections need attention:</div>
                    <div class="d-flex flex-column gap-1">
                        <?php 
                        $secMap = [
                            'Personal Information' => 'personal',
                            'Contact & Address' => 'contact',
                            'Family Information' => 'family',
                            'Education Background' => 'education',
                            'Work Experience' => 'work',
                            'Training & Eligibility' => 'training',
                            'Professional Licenses' => 'training',
                            'Skills & Recognitions' => 'skills',
                            'Government IDs' => 'disclosures',
                            'Assets & Properties' => 'assets',
                            'Character References' => 'references',
                            'Address Records' => 'contact',
                            'Performance Records' => 'performance'
                        ];
                        foreach ($missingSections as $ms): 
                            $targetSec = $secMap[$ms] ?? 'personal';
                        ?>
                            <a href="javascript:void(0)" onclick="scrollToSection('<?php echo $targetSec; ?>')" class="d-flex align-items-center justify-content-between text-decoration-none py-1 px-2 rounded" style="font-size:0.75rem; color:#b45309; background:rgba(251, 191, 36, 0.15);">
                                <span class="d-flex align-items-center gap-1"><i class="fas fa-exclamation-circle text-danger" style="font-size:0.7rem;"></i> <?php echo $ms; ?></span>
                                <i class="fas fa-chevron-right" style="font-size:0.65rem;"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="ep-sidebar-card">
            <h4 class="ep-sidebar-title"><i class="fas fa-briefcase text-primary"></i> Employment Details</h4>
            <div class="ep-emp-detail-item">
                <div class="ep-emp-detail-label">Position</div>
                <div class="ep-emp-detail-value"><?php echo e($emp['job_title']); ?></div>
            </div>
            <div class="ep-emp-detail-item">
                <div class="ep-emp-detail-label">Department</div>
                <div class="ep-emp-detail-value"><?php echo e($emp['department_name'] ?: 'N/A'); ?></div>
            </div>
            <div class="ep-emp-detail-item">
                <div class="ep-emp-detail-label">Employment Status</div>
                <div class="ep-emp-detail-value"><?php echo e($emp['employment_status']); ?></div>
            </div>
            <div class="ep-emp-detail-item">
                <div class="ep-emp-detail-label">Employment Type</div>
                <div class="ep-emp-detail-value"><?php echo e($emp['employment_type']); ?></div>
            </div>
            <div class="ep-emp-detail-item">
                <div class="ep-emp-detail-label">Work Location</div>
                <div class="ep-emp-detail-value"><?php echo e($emp['branch_name'] ?: 'N/A'); ?></div>
            </div>
        </div>

        <div class="ep-sidebar-card">
            <h4 class="ep-sidebar-title"><i class="fas fa-id-card text-primary"></i> Government IDs</h4>
            <div class="ep-gov-id-item">
                <div class="ep-gov-id-label"><i class="fas fa-id-card me-1 text-muted"></i>SSS Number</div>
                <div class="ep-gov-id-value d-flex align-items-center gap-2">
                    <span class="gov-id-mask" data-value="<?php echo e($emp['sss_number'] ?: 'N/A'); ?>">
                        <?php echo !empty($emp['sss_number']) ? '&bull;&bull;&bull;&bull;-&bull;&bull;&bull;&bull;&bull;&bull;&bull;-&bull;' : 'N/A'; ?>
                    </span>
                    <?php if(!empty($emp['sss_number'])): ?>
                    <button class="btn btn-sm btn-link p-0 text-muted toggle-gov-id"><i class="fas fa-eye"></i></button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="ep-gov-id-item">
                <div class="ep-gov-id-label"><i class="fas fa-id-card me-1 text-muted"></i>PhilHealth Number</div>
                <div class="ep-gov-id-value d-flex align-items-center gap-2">
                    <span class="gov-id-mask" data-value="<?php echo e($emp['philhealth_number'] ?: 'N/A'); ?>">
                        <?php echo !empty($emp['philhealth_number']) ? '&bull;&bull;&bull;&bull;-&bull;&bull;&bull;&bull;-&bull;&bull;&bull;&bull;' : 'N/A'; ?>
                    </span>
                    <?php if(!empty($emp['philhealth_number'])): ?>
                    <button class="btn btn-sm btn-link p-0 text-muted toggle-gov-id"><i class="fas fa-eye"></i></button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="ep-gov-id-item">
                <div class="ep-gov-id-label"><i class="fas fa-id-card me-1 text-muted"></i>Pag-IBIG Number</div>
                <div class="ep-gov-id-value d-flex align-items-center gap-2">
                    <span class="gov-id-mask" data-value="<?php echo e($emp['pagibig_number'] ?: 'N/A'); ?>">
                        <?php echo !empty($emp['pagibig_number']) ? '&bull;&bull;&bull;&bull;-&bull;&bull;&bull;&bull;-&bull;&bull;&bull;&bull;' : 'N/A'; ?>
                    </span>
                    <?php if(!empty($emp['pagibig_number'])): ?>
                    <button class="btn btn-sm btn-link p-0 text-muted toggle-gov-id"><i class="fas fa-eye"></i></button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="ep-gov-id-item">
                <div class="ep-gov-id-label"><i class="fas fa-id-card me-1 text-muted"></i>TIN Number</div>
                <div class="ep-gov-id-value d-flex align-items-center gap-2">
                    <span class="gov-id-mask" data-value="<?php echo e($emp['tin_number'] ?: 'N/A'); ?>">
                        <?php echo !empty($emp['tin_number']) ? '&bull;&bull;&bull;-&bull;&bull;&bull;-&bull;&bull;&bull;-&bull;&bull;&bull;&bull;' : 'N/A'; ?>
                    </span>
                    <?php if(!empty($emp['tin_number'])): ?>
                    <button class="btn btn-sm btn-link p-0 text-muted toggle-gov-id"><i class="fas fa-eye"></i></button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>




<!-- Full Image View Modal -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-transparent border-0 shadow-lg position-relative">
            <button type="button" class="btn-close btn-close-white p-2 rounded-circle shadow position-absolute" 
                style="top: 15px; right: 15px; z-index: 1100; background-color: rgba(0,0,0,0.6); border: none;"
                data-bs-dismiss="modal" aria-label="Close"></button>
            <div class="modal-body p-0 text-center">
                <img id="fullImage" src="" class="img-fluid rounded shadow" style="max-height: 85vh; background-color: #ffffff;">
                <h6 id="fullImageName" class="text-white mt-3 fw-bold"></h6>
            </div>
        </div>
    </div>
</div>

<script>
    function viewFullImage(src, name) {
        const modal = new bootstrap.Modal(document.getElementById('imageModal'));
        document.getElementById('fullImage').src = src;
        document.getElementById('fullImageName').textContent = name;
        modal.show();
    }

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

    document.addEventListener('DOMContentLoaded', function () {
        const profileTabs = Array.from(document.querySelectorAll('.employee-information-tab'));
        const profilePanels = Array.from(document.querySelectorAll('[data-profile-panel]'));

        function getPanelTarget(panel) {
            const parent = panel.parentElement;
            const siblingPanels = parent ? parent.querySelectorAll('[data-profile-panel]') : [];
            return parent && parent.matches('.col-xl-6, .col-12') && siblingPanels.length === 1 ? parent : panel;
        }

        function activateProfileTab(tab, moveFocus) {
            const selectedPanel = tab.getAttribute('data-profile-tab');
            profileTabs.forEach(function (item) {
                const isActive = item === tab;
                item.setAttribute('aria-selected', isActive ? 'true' : 'false');
                item.tabIndex = isActive ? 0 : -1;
            });
            profilePanels.forEach(function (panel) {
                const target = getPanelTarget(panel);
                const isSelected = selectedPanel === 'all' || panel.getAttribute('data-profile-panel') === selectedPanel;
                target.hidden = !isSelected;
                target.classList.toggle('profile-panel-active', selectedPanel !== 'all' && isSelected);
            });
            if (moveFocus) tab.focus({ preventScroll: true });
        }

        profileTabs.forEach(function (tab, index) {
            tab.addEventListener('click', function () {
                activateProfileTab(tab, false);
            });
            tab.addEventListener('keydown', function (event) {
                let nextIndex = null;
                if (event.key === 'ArrowRight') nextIndex = (index + 1) % profileTabs.length;
                if (event.key === 'ArrowLeft') nextIndex = (index - 1 + profileTabs.length) % profileTabs.length;
                if (event.key === 'Home') nextIndex = 0;
                if (event.key === 'End') nextIndex = profileTabs.length - 1;
                if (nextIndex !== null) {
                    event.preventDefault();
                    profileTabs[nextIndex].click();
                    profileTabs[nextIndex].focus({ preventScroll: true });
                }
            });
        });

        const initialProfileTab = profileTabs.find(function (tab) {
            return tab.getAttribute('aria-selected') === 'true';
        }) || profileTabs[0];
        if (initialProfileTab) activateProfileTab(initialProfileTab, false);

    });

function toggleSection(id) {
    const content = document.getElementById('content-' + id);
    const chevron = document.getElementById('chevron-' + id);
    if (content.classList.contains('active')) {
        content.classList.remove('active');
        chevron.classList.remove('open');
    } else {
        content.classList.add('active');
        chevron.classList.add('open');
    }
}
function scrollToSection(id) {
    const section = document.getElementById('section-' + id);
    if (section) {
        section.scrollIntoView({behavior: 'smooth', block: 'start'});
        const content = document.getElementById('content-' + id);
        const chevron = document.getElementById('chevron-' + id);
        if (!content.classList.contains('active')) {
            content.classList.add('active');
            chevron.classList.add('open');
        }
    }
}

</script>

<?php require_once '../includes/footer.php'; ?>
