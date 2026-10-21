<?php
$page_title = 'Team Member Evaluation';
require_once '../includes/session-check.php';
require_once '../includes/functions.php';

ensureOrganizationEvaluationPackageSchema($conn);
ensureProbationaryEvaluationSchema($conn);
$user_id = (int) ($_SESSION['user_id'] ?? 0);
$package_id = (int) ($_GET['package_id'] ?? 0);
$evaluation_id = (int) ($_GET['evaluation_id'] ?? 0);
$package_back_stmt=$conn->prepare('SELECT evaluation_type FROM evaluation_packages WHERE package_id=? LIMIT 1');
$package_back_stmt->bind_param('i',$package_id);
$package_back_stmt->execute();
$package_back_row=$package_back_stmt->get_result()->fetch_assoc();
$package_back_stmt->close();
$package_back_category=$package_back_row && !in_array($package_back_row['evaluation_type'],['Initial','Final'],true) ? 'regular' : 'probationary';
$package_back_url=BASE_URL.'/employee/team-evaluation-packages.php?category='.$package_back_category;

if ($package_id <= 0 || $evaluation_id <= 0) {
    redirectWith($package_back_url, 'danger', 'Choose a package member to view.');
}

// Sequential turn-based check: A reviewer can view member details ONLY if:
// 1. Their step is currently Pending
// 2. They already acted on it (Approved/Returned)
// 3. The package is finalized (Approved and Applied)
$reviewer_match = organizationPackageReviewerMatchSql('rs');
$access_stmt = $conn->prepare("SELECT rs.action_status, ep.status AS package_status
    FROM evaluation_package_route_steps rs
    JOIN evaluation_packages ep ON ep.package_id = rs.package_id
    WHERE rs.package_id = ? AND $reviewer_match
    LIMIT 1");
$access_stmt->bind_param('iii', $package_id, $user_id, $user_id);
$access_stmt->execute();
$access_row = $access_stmt->get_result()->fetch_assoc();
$access_stmt->close();

if (!$access_row) {
    $peer_profile_stmt = $conn->prepare("SELECT u.role, e.employee_id, e.department_id, e.rank_category_id, e.employment_status
        FROM users u JOIN employees e ON e.employee_id = u.employee_id
        WHERE u.user_id = ? AND u.is_active = 1 AND COALESCE(u.account_hold, 0) = 0
          AND u.deleted_at IS NULL AND e.is_active = 1 AND e.deleted_at IS NULL LIMIT 1");
    $peer_profile_stmt->bind_param('i', $user_id);
    $peer_profile_stmt->execute();
    $peer_profile = $peer_profile_stmt->get_result()->fetch_assoc() ?: [];
    $peer_profile_stmt->close();
    $peer_rank = (int)($peer_profile['rank_category_id'] ?? 0);
    $peer_department = (int)($peer_profile['department_id'] ?? 0);
    $peer_employee_id = (int)($peer_profile['employee_id'] ?? 0);
    if (($peer_profile['role'] ?? '') !== 'HR Staff'
        && ($peer_profile['employment_status'] ?? '') === 'Regular'
        && in_array($peer_rank, [3, 4], true) && $peer_department > 0 && $peer_employee_id > 0) {
        $peer_access_stmt = $conn->prepare("SELECT peer_rs.action_status, ep.status AS package_status
            FROM evaluation_package_route_steps peer_rs
            JOIN evaluation_packages ep ON ep.package_id = peer_rs.package_id
            JOIN employees peer_employee ON peer_employee.employee_id = COALESCE(
                peer_rs.reviewer_employee_id,
                (SELECT peer_account.employee_id FROM users peer_account WHERE peer_account.user_id = peer_rs.reviewer_user_id LIMIT 1)
            )
            WHERE peer_rs.package_id = ? AND peer_employee.department_id = ep.department_id
              AND peer_employee.department_id = ?
              AND peer_employee.rank_category_id = ? AND peer_employee.employee_id <> ?
              AND peer_employee.employment_status = 'Regular'
              AND peer_employee.is_active = 1 AND peer_employee.deleted_at IS NULL
            LIMIT 1");
        $peer_access_stmt->bind_param('iiii', $package_id, $peer_department, $peer_rank, $peer_employee_id);
        $peer_access_stmt->execute();
        $access_row = $peer_access_stmt->get_result()->fetch_assoc();
        $peer_access_stmt->close();
    }
    if (!$access_row) {
        redirectWith($package_back_url, 'danger', 'You are not a designated reviewer or same-level department peer for this package.');
    }
}

if ($access_row['action_status'] === 'Waiting' && $access_row['package_status'] !== 'Approved and Applied') {
    redirectWith($package_back_url, 'warning', 'This package is currently with prior evaluators and has not reached your review stage yet.');
}

$evaluation_stmt = $conn->prepare("SELECT ev.*, emp.first_name, emp.last_name, emp.job_title, et.template_name, ep.shared_behavior_score, ep.status AS package_status,
        per.recommendation, per.recommendation_reason, per.immediate_head_signature_data, per.immediate_head_signed_at
    FROM evaluations ev
    JOIN evaluation_package_members pm ON pm.evaluation_id = ev.evaluation_id
    JOIN evaluation_packages ep ON ep.package_id = pm.package_id
    JOIN employees emp ON emp.employee_id = ev.employee_id
    JOIN evaluation_templates et ON et.template_id = ev.template_id
    LEFT JOIN probationary_evaluation_reviews per ON per.evaluation_id = ev.evaluation_id
    WHERE pm.package_id = ? AND ev.evaluation_id = ? AND ev.deleted_at IS NULL LIMIT 1");
$evaluation_stmt->bind_param('ii', $package_id, $evaluation_id);
$evaluation_stmt->execute();
$evaluation = $evaluation_stmt->get_result()->fetch_assoc();
$evaluation_stmt->close();

if (!$evaluation) {
    redirectWith($package_back_url, 'danger', 'That package member evaluation is unavailable.');
}

$criteria_stmt = $conn->prepare("SELECT ec.section, ec.criterion_name, ec.description, ec.weight, es.score_value,
        COALESCE(es.supervisor_override_score, es.dept_manager_override_score, es.manager_override_score, es.score_value) AS reviewed_score
    FROM evaluation_scores es
    JOIN evaluation_criteria ec ON ec.criterion_id = es.criterion_id
    WHERE es.evaluation_id = ?
    ORDER BY ec.section, ec.sort_order");
$criteria_stmt->bind_param('i', $evaluation_id);
$criteria_stmt->execute();
$criteria = $criteria_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$criteria_stmt->close();

$kra = array_values(array_filter($criteria, static fn($criterion) => $criterion['section'] === 'KRA'));
$behavior = array_values(array_filter($criteria, static fn($criterion) => $criterion['section'] !== 'KRA'));

$is_probationary = in_array($evaluation['evaluation_type'], ['Initial', 'Final'], true);
$kra_w = $is_probationary ? 80 : (float)($evaluation['kra_weight'] ?? 80);
$beh_w = $is_probationary ? 20 : (float)($evaluation['behavior_weight'] ?? 20);
$shared_beh = $evaluation['shared_behavior_score'] !== null ? (float)$evaluation['shared_behavior_score'] : (float)$evaluation['behavior_average'];

$self_kra_subtotal = 0.0;
$rev_kra_subtotal = 0.0;
$kra_total_weight = 0.0;
foreach ($kra as $k) {
    $w = (float)$k['weight'];
    $kra_total_weight += $w;
    $self_kra_subtotal += ($w / 100.0) * (float)$k['score_value'];
    $rev_kra_subtotal += ($w / 100.0) * (float)$k['reviewed_score'];
}
$self_kra_subtotal = round($self_kra_subtotal, 2);
$rev_kra_subtotal = round($rev_kra_subtotal, 2);

$self_beh_total = 0.0;
$rev_beh_total = 0.0;
$beh_count = count($behavior);
foreach ($behavior as $b) {
    $self_beh_total += (float)$b['score_value'];
    $rev_beh_total += (float)$b['reviewed_score'];
}
$self_beh_avg = $beh_count > 0 ? round($self_beh_total / $beh_count, 2) : 0.0;
$rev_beh_avg = $beh_count > 0 ? round($rev_beh_total / $beh_count, 2) : 0.0;

$kra_count = count($kra);
$beh_count = count($behavior);

// Fetch Developmental Plan
$dev_plan_stmt = $conn->prepare("SELECT * FROM evaluation_dev_plans WHERE evaluation_id = ? ORDER BY sort_order");
$dev_plan_stmt->bind_param('i', $evaluation_id);
$dev_plan_stmt->execute();
$view_dev_plans = $dev_plan_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$dev_plan_stmt->close();
$devplan_count = count($view_dev_plans);

require_once '../includes/header.php';
?>
<main class="evaluation-packages container-fluid py-4">
    <section class="package-hero">
        <a class="history-back-link" href="<?php echo e($package_back_url); ?>">
            <i class="fas fa-arrow-left"></i> Back to evaluation reviews
        </a>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <p class="mb-1 text-uppercase fw-bold" style="letter-spacing:1px; color:var(--rp-primary-gold-light); font-size:0.85rem;">
                    Member Evaluation View
                </p>
                <h1 class="h3 mb-2 fw-bold"><?php echo e($evaluation['first_name'] . ' ' . $evaluation['last_name']); ?></h1>
                <p class="mb-0">
                    <strong><?php echo e($evaluation['job_title']); ?></strong> &bull; Template: <?php echo e($evaluation['template_name']); ?> &bull; Period: <?php echo e($evaluation['evaluation_period_start']); ?> to <?php echo e($evaluation['evaluation_period_end']); ?>
                </p>
            </div>
        </div>
    </section>

    <!-- Score Summary Stats Card -->
    <section class="package-card" aria-label="Score Summary Card">
        <div class="package-card__body">
            <div class="row g-3 mb-3">
                <div class="col-sm-6 col-xl-4">
                    <div class="package-stat h-100 d-flex flex-column justify-content-between" style="background:#FFF8EB;border:2px solid #FCD34D;border-radius:12px;padding:1.1rem 1.25rem;">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                            <strong class="tabular-nums text-warning-emphasis m-0" style="font-size:1.6rem;line-height:1;"><?php echo number_format($rev_kra_subtotal, 2); ?></strong>
                            <span class="badge text-bg-warning px-2 py-1 small"><?php echo number_format($kra_w, 0); ?>%</span>
                        </div>
                        <div class="text-muted fw-semibold" style="font-size:0.85rem;">Individual KRA</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-4">
                    <div class="package-stat h-100 d-flex flex-column justify-content-between" style="background: #F4FBF7; border: 2px solid #86EFAC; border-radius: 12px; padding: 1.1rem 1.25rem;">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                            <strong class="tabular-nums text-success m-0" style="font-size: 1.6rem; line-height: 1;"><?php echo $is_probationary ? number_format((float)$evaluation['behavior_average'], 2) : ($evaluation['shared_behavior_score'] !== null ? number_format((float) $evaluation['shared_behavior_score'], 2) : 'Pending'); ?></strong>
                            <span class="badge bg-success-subtle text-success border border-success px-2 py-1 small"><?php echo $is_probationary ? '20%' : 'Department Shared'; ?></span>
                        </div>
                        <div class="text-muted fw-semibold" style="font-size: 0.85rem;"><?php echo $is_probationary ? 'Individual Core Values' : 'Shared Department Behavior'; ?></div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-4">
                    <div class="package-stat h-100 d-flex flex-column justify-content-between" style="background: #FAF8F0; border: 2px solid var(--rp-primary-gold); border-radius: 12px; padding: 1.1rem 1.25rem;">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                            <strong class="tabular-nums text-primary m-0" style="font-size: 1.6rem; line-height: 1;"><?php echo number_format((float) $evaluation['total_score'], 2); ?></strong>
                            <span class="badge bg-success px-2 py-1 small"><?php echo e(getPerformanceLevel((float)$evaluation['total_score'])); ?></span>
                        </div>
                        <div class="text-muted fw-semibold" style="font-size: 0.85rem;">Overall Total Score</div>
                    </div>
                </div>
            </div>

            <div class="shared-behavior-banner d-flex align-items-center justify-content-between flex-wrap gap-2 mb-0">
                <div>
                    <i class="fas fa-users me-2"></i><?php echo $is_probationary ? 'Individual Core Values Score:' : 'Current Shared Core Behaviors &amp; Values Score:'; ?>
                    <strong><?php echo $is_probationary ? number_format((float)$evaluation['behavior_average'], 2) : ($evaluation['shared_behavior_score'] !== null ? number_format((float) $evaluation['shared_behavior_score'], 2) : 'Pending'); ?></strong>
                </div>
                <div>
                    <?php echo renderOrganizationPipelineBadge($conn, (int)$package_id); ?>
                </div>
            </div>
        </div>
    </section>

    <?php if ($is_probationary): ?>
    <section class="probationary-decision-section card border-0 shadow-sm mb-4"><div class="card-body p-4">
        <h2 class="h5 fw-bold mb-3">Probationary decision and signatures</h2>
        <div class="row g-3">
            <div class="col-lg-7"><div class="border rounded p-3 h-100"><div class="small text-muted">Immediate head’s recommendation</div><strong><?php echo e($evaluation['recommendation'] ?? 'Not yet recorded'); ?></strong><?php if (!empty($evaluation['recommendation_reason'])): ?><p class="mb-0 mt-2"><?php echo nl2br(e($evaluation['recommendation_reason'])); ?></p><?php endif; ?></div></div>
            <div class="col-lg-5"><div class="border rounded p-3 h-100"><div class="small text-muted mb-2">Immediate head’s signature</div><?php if (!empty($evaluation['immediate_head_signature_data'])): ?><img src="<?php echo e($evaluation['immediate_head_signature_data']); ?>" alt="Immediate head signature" style="max-height:65px;max-width:260px;display:block;"><small class="text-muted">Signed <?php echo e($evaluation['immediate_head_signed_at'] ?? ''); ?></small><?php else: ?><span class="text-muted">Awaiting signature</span><?php endif; ?></div></div>
            <div class="col-12"><div class="border rounded p-3"><div class="small text-muted mb-2">Employee’s self-rating signature</div><?php if (!empty($evaluation['employee_signature_data'])): ?><img src="<?php echo e($evaluation['employee_signature_data']); ?>" alt="Employee self-rating signature" style="max-height:65px;max-width:260px;display:block;"><small class="text-muted">Signed <?php echo e($evaluation['employee_signed_at'] ?? ''); ?></small><div class="small text-muted mt-2">Captured with the original self-rating; no second signature is required.</div><?php else: ?><span class="text-muted">No employee signature on file</span><?php endif; ?></div></div>
        </div>
    </div></section>
    <?php endif; ?>

    <style>
    .probationary-decision-section .row.g-3 {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px !important;
        margin: 0;
    }
    .probationary-decision-section .row.g-3 > [class*="col-"] {
        width: 100%;
        max-width: 100%;
        padding: 0;
    }
    .probationary-decision-section .row.g-3 > [class*="col-"] > .border {
        padding: 12px !important;
        overflow-wrap: anywhere;
    }
    .probationary-decision-section img[alt$="signature"] {
        max-width: min(100%, 180px) !important;
        max-height: 55px !important;
        object-fit: contain;
        object-position: left center;
    }
    @media (max-width: 767.98px) {
        .probationary-decision-section .row.g-3 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .probationary-decision-section .row.g-3 > [class*="col-"]:first-child {
            grid-column: 1 / -1;
        }
    }
    @media (max-width: 479.98px) {
        .probationary-decision-section .row.g-3 {
            grid-template-columns: minmax(0, 1fr);
        }
        .probationary-decision-section .row.g-3 > [class*="col-"]:first-child {
            grid-column: auto;
        }
    }
    /* Tabbed Layout for Member View Sections */
    .eval-tabs-wrapper {
        position: relative;
        background-color: #f8fafc;
        border-bottom: 2px solid #e2e8f0;
        border-radius: 12px 12px 0 0;
    }
    .eval-tabs-wrapper::after {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        bottom: 0;
        width: 32px;
        background: linear-gradient(to right, rgba(248, 250, 252, 0), rgba(248, 250, 252, 1));
        pointer-events: none;
    }
    .eval-tabs-header {
        display: flex;
        padding: 12px 16px;
        gap: 8px;
        overflow-x: auto;
        scrollbar-width: none;
        -ms-overflow-style: none;
    }
    .eval-tabs-header::-webkit-scrollbar {
        display: none;
    }
    .eval-tab-btn {
        background: transparent;
        border: none;
        outline: none;
        padding: 10px 18px;
        font-size: 14px;
        font-weight: 600;
        color: #64748b;
        cursor: pointer;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
        white-space: nowrap;
    }
    .eval-tab-btn:hover {
        color: var(--rp-forest-green, #0f5132);
        background-color: rgba(15, 81, 50, 0.08);
    }
    .eval-tab-btn.active {
        color: #ffffff;
        background-color: var(--rp-forest-green, #0f5132);
        box-shadow: 0 2px 8px rgba(15, 81, 50, 0.25);
    }
    .eval-tab-badge {
        background-color: #e2e8f0;
        color: #64748b;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 12px;
        font-weight: 700;
        transition: all 0.2s ease;
    }
    .eval-tab-btn.active .eval-tab-badge {
        background-color: rgba(255, 255, 255, 0.25);
        color: #ffffff;
    }
    .eval-tab-content {
        display: none;
        opacity: 0;
        transform: translateY(8px);
        transition: opacity 0.25s ease, transform 0.25s ease;
    }
    .eval-tab-content.active {
        display: block;
        opacity: 1;
        transform: translateY(0);
    }
    .eval-section-title {
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--rp-forest-green, #0f5132);
        margin-top: 0;
        margin-bottom: 16px;
        padding-bottom: 10px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 8px;
    }
    </style>

    <div class="package-card p-0 shadow-sm mb-4" style="border-radius:12px; border:1px solid #E2E8F0; overflow:hidden;">
        <!-- Tabs Header -->
        <div class="eval-tabs-wrapper">
            <div class="eval-tabs-header" role="tablist">
                <button type="button" class="eval-tab-btn active" id="btn-tab-kra" onclick="switchViewTab(event, 'tab-kra')" role="tab" aria-selected="true" aria-controls="tab-kra">
                    <i class="fas fa-chart-pie me-1"></i>Key Result Areas (KRA) <span class="eval-tab-badge ms-1"><?php echo $kra_count; ?></span>
                </button>
                <button type="button" class="eval-tab-btn" id="btn-tab-behaviors" onclick="switchViewTab(event, 'tab-behaviors')" role="tab" aria-selected="false" aria-controls="tab-behaviors">
                    <i class="fas fa-users me-1"></i>Core Behaviors &amp; Values <span class="eval-tab-badge ms-1"><?php echo $beh_count; ?></span>
                </button>
                <button type="button" class="eval-tab-btn" id="btn-tab-devplan" onclick="switchViewTab(event, 'tab-devplan')" role="tab" aria-selected="false" aria-controls="tab-devplan">
                    <i class="fas fa-seedling me-1"></i>Developmental Plan &amp; Recommendations <span class="eval-tab-badge ms-1"><?php echo $devplan_count; ?></span>
                </button>
                <?php if ($is_probationary): ?>
                    <button type="button" class="eval-tab-btn" id="btn-tab-decision" onclick="switchViewTab(event, 'tab-decision')" role="tab" aria-selected="false" aria-controls="tab-decision">
                        <i class="fas fa-file-signature me-1"></i>Decision &amp; Signatures
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="package-card__body p-4">
            <!-- TAB 1: Key Result Areas (KRA) -->
            <div id="tab-kra" class="eval-tab-content active" role="tabpanel" aria-labelledby="btn-tab-kra">
                <div class="eval-section-title">
                    <span><i class="fas fa-chart-pie me-2"></i>Key Result Areas (KRA)</span>
                    <span class="badge bg-light text-secondary border px-3 py-2 fw-semibold" style="font-size:0.85rem;">
                        KRA Weight: <strong class="text-dark"><?php echo $kra_w; ?>%</strong>
                    </span>
                </div>

                <div class="table-responsive mb-3">
                    <table class="package-table table align-middle">
                        <thead>
                            <tr>
                                <th style="width: 45%;">Criterion</th>
                                <th class="text-end" style="width: 12%;">Weight</th>
                                <th class="text-end" style="width: 20%;">Employee Self-Rating</th>
                                <th class="text-end" style="width: 23%;">Reviewed / Adjusted Rating</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($kra as $criterion): ?>
                                <?php $adjusted = abs((float) $criterion['reviewed_score'] - (float) $criterion['score_value']) > 0.001; ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark" style="font-size:1.05rem;"><?php echo e($criterion['criterion_name']); ?></div>
                                        <?php if (!empty($criterion['description'])): ?>
                                            <div class="small text-muted mt-1"><?php echo e($criterion['description']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($adjusted): ?>
                                            <span class="audit-chip audit-chip--adjusted">
                                                <i class="fas fa-edit me-1"></i>Adjusted during evaluation
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end tabular-nums fw-bold text-muted"><?php echo number_format((float) $criterion['weight'], 2); ?>%</td>
                                    <td class="text-end tabular-nums fw-semibold" style="font-size:1.05rem;">
                                        <?php echo number_format((float) $criterion['score_value'], 2); ?>
                                    </td>
                                    <td class="text-end tabular-nums fw-bold text-dark" style="font-size:1.1rem;">
                                        <?php echo number_format((float) $criterion['reviewed_score'], 2); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Section Total Score Lookup Below Table -->
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 px-3 py-2 rounded-3 border mb-2 shadow-sm" style="background:#F8FAFC; border-color:#E2E8F0 !important;">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-chart-pie text-success fa-lg"></i>
                        <span class="fw-bold text-dark" style="font-size: 1.05rem;">Key Result Areas (KRA) Total:</span>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span class="text-muted small fw-semibold">Self-Rating: <strong class="text-secondary tabular-nums"><?php echo number_format($self_kra_subtotal, 2); ?></strong></span>
                        <div class="d-flex align-items-center gap-1 bg-white px-3 py-1 rounded-2 border" style="border-color:#0f5132 !important;">
                            <span class="text-muted small me-1">Total Score:</span>
                            <strong class="text-success tabular-nums fs-5"><?php echo number_format($rev_kra_subtotal, 2); ?></strong>
                            <span class="text-muted small fw-bold">/ 4.00</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: Core Behaviors & Values -->
            <div id="tab-behaviors" class="eval-tab-content" role="tabpanel" aria-labelledby="btn-tab-behaviors">
                <div class="eval-section-title">
                    <span><i class="fas fa-users me-2"></i>Core Behaviors &amp; Values</span>
                    <span class="badge bg-light text-secondary border px-3 py-2 fw-semibold" style="font-size:0.85rem;">
                        Behavior Weight: <strong class="text-dark"><?php echo $beh_w; ?>%</strong>
                    </span>
                </div>

                <div class="table-responsive mb-3">
                    <table class="package-table table align-middle">
                        <thead>
                            <tr>
                                <th style="width: 55%;">Criterion</th>
                                <th class="text-end" style="width: 22%;">Employee Self-Rating</th>
                                <th class="text-end" style="width: 23%;">Reviewed / Adjusted Rating</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($behavior as $criterion): ?>
                                <?php $adjusted = abs((float) $criterion['reviewed_score'] - (float) $criterion['score_value']) > 0.001; ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark" style="font-size:1.05rem;"><?php echo e($criterion['criterion_name']); ?></div>
                                        <?php if (!empty($criterion['description'])): ?>
                                            <div class="small text-muted mt-1"><?php echo e($criterion['description']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($adjusted): ?>
                                            <span class="audit-chip audit-chip--adjusted">
                                                <i class="fas fa-edit me-1"></i>Adjusted during evaluation
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end tabular-nums fw-semibold" style="font-size:1.05rem;">
                                        <?php echo number_format((float) $criterion['score_value'], 2); ?>
                                    </td>
                                    <td class="text-end tabular-nums fw-bold text-dark" style="font-size:1.1rem;">
                                        <?php echo number_format((float) $criterion['reviewed_score'], 2); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Section Total Score Lookup Below Table -->
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 px-3 py-2 rounded-3 border mb-2 shadow-sm" style="background:#F8FAFC; border-color:#E2E8F0 !important;">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-users text-primary fa-lg"></i>
                        <span class="fw-bold text-dark" style="font-size: 1.05rem;">Core Behaviors &amp; Values Total:</span>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span class="text-muted small fw-semibold">Self-Rating: <strong class="text-secondary tabular-nums"><?php echo number_format($self_beh_avg, 2); ?></strong></span>
                        <div class="d-flex align-items-center gap-1 bg-white px-3 py-1 rounded-2 border" style="border-color:#0f5132 !important;">
                            <span class="text-muted small me-1">Total Score:</span>
                            <strong class="text-success tabular-nums fs-5"><?php echo number_format($rev_beh_avg, 2); ?></strong>
                            <span class="text-muted small fw-bold">/ 4.00</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 3: Developmental Plan & Recommendations -->
            <div id="tab-devplan" class="eval-tab-content" role="tabpanel" aria-labelledby="btn-tab-devplan">
                <div class="eval-section-title">
                    <span><i class="fas fa-seedling me-2"></i>Developmental Plan &amp; Recommendations</span>
                </div>

                <?php if (empty($view_dev_plans)): ?>
                    <div class="alert alert-light border text-muted py-4 text-center mb-0 rounded-3">
                        <i class="fas fa-seedling fa-2x mb-2 text-secondary" style="opacity:0.5;"></i>
                        <p class="mb-0">No developmental plan items specified for this evaluation cycle.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 35%;">Area for Improvement / Development</th>
                                    <th style="width: 45%;">Support Needed / Action Plan</th>
                                    <th style="width: 20%;">Target Time Frame</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($view_dev_plans as $dp): ?>
                                    <tr>
                                        <td class="fw-semibold text-dark"><?php echo e($dp['improvement_area'] ?: '—'); ?></td>
                                        <td><?php echo e($dp['support_needed'] ?: '—'); ?></td>
                                        <td><span class="badge bg-light text-dark border"><?php echo e($dp['time_frame'] ?: '—'); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($is_probationary): ?>
                <div id="tab-decision" class="eval-tab-content" role="tabpanel" aria-labelledby="btn-tab-decision">
                    <script>
                        (function () {
                            const decisionSection = document.querySelector('.probationary-decision-section');
                            const decisionTab = document.getElementById('tab-decision');
                            if (decisionSection && decisionTab) {
                                decisionTab.appendChild(decisionSection);
                                decisionSection.classList.remove('mb-4');
                            }
                        })();
                    </script>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
function switchViewTab(event, tabId) {
    if (event) event.preventDefault();
    const contents = document.querySelectorAll('.eval-tab-content');
    const buttons = document.querySelectorAll('.eval-tab-btn');

    contents.forEach(function (content) {
        content.classList.remove('active');
    });
    buttons.forEach(function (btn) {
        btn.classList.remove('active');
        btn.setAttribute('aria-selected', 'false');
    });

    const activeContent = document.getElementById(tabId);
    if (activeContent) activeContent.classList.add('active');
    if (event && event.currentTarget) {
        event.currentTarget.classList.add('active');
        event.currentTarget.setAttribute('aria-selected', 'true');
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
