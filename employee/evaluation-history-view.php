<?php
$page_title = 'Evaluation Details & Audit Log';
require_once '../includes/session-check.php';
require_once '../includes/functions.php';

ensureOrganizationEvaluationPackageSchema($conn);
$user_id = (int) ($_SESSION['user_id'] ?? 0);
$evaluation_id = (int) ($_GET['evaluation_id'] ?? 0);
if ($evaluation_id <= 0) {
    redirectWith(BASE_URL . '/employee/evaluation-history.php', 'danger', 'Choose an evaluation to view.');
}

$evaluation_stmt = $conn->prepare("SELECT ev.*, et.template_name, et.kra_weight, et.behavior_weight,
        ep.package_id, ep.status AS package_status, ep.shared_behavior_score, ep.current_step_order, d.department_name,
        per.recommendation AS probationary_recommendation, per.recommendation_reason AS probationary_recommendation_reason,
        per.immediate_head_signature_data, per.immediate_head_signed_at,
        head_route.action_status AS probationary_head_step_status,
        CONCAT(emp.first_name, ' ', emp.last_name) AS employee_name
    FROM evaluations ev
    JOIN users u ON u.employee_id = ev.employee_id AND u.user_id = ?
    JOIN evaluation_templates et ON et.template_id = ev.template_id
    JOIN employees emp ON emp.employee_id = ev.employee_id
    LEFT JOIN departments d ON d.department_id = emp.department_id
    LEFT JOIN evaluation_package_members pm ON pm.evaluation_id = ev.evaluation_id
    LEFT JOIN evaluation_packages ep ON ep.package_id = pm.package_id
    LEFT JOIN probationary_evaluation_reviews per ON per.evaluation_id = ev.evaluation_id
    LEFT JOIN evaluation_package_route_steps head_route ON head_route.package_id = ep.package_id AND head_route.step_order = 1
    WHERE ev.evaluation_id = ? AND ev.deleted_at IS NULL
    LIMIT 1");
$evaluation_stmt->bind_param('ii', $user_id, $evaluation_id);
$evaluation_stmt->execute();
$evaluation = $evaluation_stmt->get_result()->fetch_assoc();
$evaluation_stmt->close();
if (!$evaluation) {
    redirectWith(BASE_URL . '/employee/evaluation-history.php', 'danger', 'That evaluation is unavailable.');
}

$is_probationary_evaluation = in_array($evaluation['evaluation_type'] ?? '', ['Initial', 'Final'], true);
$is_annual_peer_evaluation = ($evaluation['evaluation_type'] ?? '') === 'Annual'
    && annualUsesPeerWorkflow($conn, $evaluation_id);
$criteria_stmt = $conn->prepare("SELECT ec.section, ec.criterion_name, ec.description, ec.weight, es.score_value,
        es.dept_manager_override_score, es.supervisor_override_score, es.manager_override_score,
        COALESCE(es.supervisor_override_score, es.dept_manager_override_score, es.manager_override_score, es.score_value) AS reviewed_score
    FROM evaluation_scores es
    JOIN evaluation_criteria ec ON ec.criterion_id = es.criterion_id
    WHERE es.evaluation_id = ?
    ORDER BY ec.section, ec.sort_order");
$criteria_stmt->bind_param('i', $evaluation_id);
$criteria_stmt->execute();
$criteria = $criteria_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$criteria_stmt->close();

$kra_weight = (float)($evaluation['kra_weight'] ?? 80);
$beh_weight = (float)($evaluation['behavior_weight'] ?? 20);

$is_package_completed = ($evaluation['package_status'] === 'Approved and Applied' || $evaluation['status'] === 'Approved');
$show_reviewed_scores = $is_package_completed || ($is_probationary_evaluation
    && ($evaluation['probationary_head_step_status'] ?? '') === 'Approved'
    && !empty($evaluation['immediate_head_signature_data']));

// Calculate original self totals vs reviewed totals
$orig_kra_subtotal = 0.0;
$orig_beh_total = 0.0;
$orig_beh_count = 0;
$has_any_adjustment = false;

foreach ($criteria as &$c) {
    $val = (float)$c['score_value'];
    if (!$show_reviewed_scores) {
        $c['reviewed_score'] = $val;
    }
    $rev = (float)$c['reviewed_score'];
    if ($show_reviewed_scores && abs($rev - $val) > 0.001) {
        $has_any_adjustment = true;
    }
    if ($c['section'] === 'KRA') {
        $w = (float)$c['weight'];
        $orig_kra_subtotal += ($w / 100) * $val;
    } else {
        $orig_beh_total += $val;
        $orig_beh_count++;
    }
}
unset($c);

$orig_kra_subtotal = round($orig_kra_subtotal, 2);
$orig_beh_average = $orig_beh_count > 0 ? round($orig_beh_total / $orig_beh_count, 2) : 0.0;
$orig_total_score = calculateEvalTotal($orig_kra_subtotal, $orig_beh_average, $kra_weight, $beh_weight);

$kra = array_values(array_filter($criteria, static fn($criterion) => $criterion['section'] === 'KRA'));
$behavior = array_values(array_filter($criteria, static fn($criterion) => $criterion['section'] !== 'KRA'));

$remarks = array_filter([
    $evaluation['staff_comments'] ?? '',
    $evaluation['supervisor_comments'] ?? '',
    $evaluation['dept_manager_comments'] ?? '',
    $evaluation['evaluator_comments'] ?? '',
    $evaluation['manager_comments'] ?? '',
], static fn($remark) => trim((string) $remark) !== '');

require_once '../includes/header.php';
?>
<main class="evaluation-packages evaluation-history-view container-fluid py-4">
    <section class="package-hero">
        <a class="history-back-link" href="<?php echo BASE_URL; ?>/employee/evaluation-history.php">
            <i class="fas fa-arrow-left"></i> Back to Evaluation History
        </a>
        <p class="mb-1 text-uppercase fw-bold" style="letter-spacing:1px; color:var(--rp-primary-gold-light); font-size:0.85rem;">
            Evaluation Audit Trail &amp; Score Comparison
        </p>
        <h1 class="h3 mb-2 fw-bold"><?php echo e($evaluation['template_name']); ?></h1>
        <p class="mb-0">
            <i class="fas fa-calendar-alt me-1"></i>Period: <?php echo e($evaluation['evaluation_period_start']); ?> to <?php echo e($evaluation['evaluation_period_end']); ?> &bull; Type: <strong><?php echo e($evaluation['evaluation_type']); ?></strong>
        </p>
    </section>

    <!-- ── Original vs. Modified Score Comparison (Req 4) ──────────────────── -->
    <section class="package-card" aria-label="Original vs Modified Score Comparison">
        <header class="package-card__header">
            <div>
                <h2 class="h5 mb-0 fw-bold">
                    <i class="fas fa-balance-scale me-2"></i>
                    <?php echo $is_package_completed ? 'Original Self-Rating vs. Final / Reviewed Rating' : ($show_reviewed_scores ? 'Original Self-Rating vs. Immediate Head Review' : 'Submitted Self-Rating Summary'); ?>
                </h2>
                <div class="small text-muted mt-1">
                    <?php echo $is_package_completed ? 'Audit comparison between your submitted rating and officer adjustments.' : ($show_reviewed_scores ? 'Compare your original ratings with the immediate head’s reviewed ratings.' : 'Your submitted self-ratings are shown below while the evaluation review is in progress.'); ?>
                </div>
            </div>
            <div>
                <?php if (!empty($evaluation['package_id'])): ?>
                    <?php echo renderOrganizationPipelineBadge($conn, (int)$evaluation['package_id'], '', (int)($evaluation['evaluation_id'] ?? 0)); ?>
                <?php else: ?>
                    <span class="badge <?php echo $evaluation['status'] === 'Approved' ? 'bg-success' : 'bg-secondary'; ?>">
                        <?php echo e($evaluation['status']); ?>
                    </span>
                <?php endif; ?>
            </div>
        </header>
        <div class="package-card__body">
            <?php if (!$is_package_completed): ?>
                <div class="alert alert-info d-flex align-items-center gap-2 p-3 mb-3 rounded-3">
                    <i class="fas fa-info-circle fa-lg text-primary"></i>
                    <div class="small">
                        <?php if ($is_probationary_evaluation): ?>
                            <strong>Probationary evaluation in progress:</strong> The evaluation is moving through its immediate-head and final approval steps. This is an individual review; it does not use a shared department behavior score.
                        <?php elseif ($is_annual_peer_evaluation): ?>
                            <strong>Annual evaluation in progress:</strong> Your original submitted self-ratings are shown here. Department 360 ratings and immediate-head consolidation are handled separately in Annual &amp; 360 Ratings. Official final scores become available after your immediate head finalizes the evaluation.
                        <?php else: ?>
                            <strong>Consolidation &amp; Management Review in Progress:</strong> Your self-evaluation is currently undergoing departmental consolidation and governance routing. Official final scores, shared behavior metrics, and officer endorsements will be unlocked here once the Board of Directors approves the cycle.
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-sm-6">
                    <?php
                    $ts_val = $show_reviewed_scores && $evaluation['total_score'] !== null ? (float)$evaluation['total_score'] : (float)$orig_total_score;
                    if ($show_reviewed_scores && $ts_val !== null) {
                        if ($ts_val >= 3.60)     { $score_bg = 'linear-gradient(135deg,#146c43,#198754)'; $score_border = '#146c43'; }
                        elseif ($ts_val >= 2.60) { $score_bg = 'linear-gradient(135deg,#087990,#0dcaf0)'; $score_border = '#087990'; }
                        elseif ($ts_val >= 2.00) { $score_bg = 'linear-gradient(135deg,#cc8800,#ffc107)'; $score_border = '#cc8800'; }
                        else                     { $score_bg = 'linear-gradient(135deg,#a71d2a,#dc3545)'; $score_border = '#a71d2a'; }
                        $score_card_style = "background:{$score_bg};border:2px solid {$score_border};border-radius:10px;padding:14px 18px;";
                        $score_label_style = 'color:rgba(255,255,255,.75)';
                        $score_value_style = 'font-size:1.8rem;color:#fff;';
                        $score_sub_style   = 'color:rgba(255,255,255,.8)';
                    } else {
                        $score_card_style  = 'border:2px solid var(--bs-border-color,#dee2e6);border-radius:10px;padding:14px 18px;background:#F8FAFC;';
                        $score_label_style = 'color:#6c757d';
                        $score_value_style = 'font-size:1.8rem;color:#0F172A;';
                        $score_sub_style   = 'color:#6c757d';
                    }
                    ?>
                    <div class="package-stat h-100 d-flex flex-column justify-content-between" style="<?php echo $score_card_style; ?>">
                        <span class="small text-uppercase fw-bold" style="<?php echo $score_label_style; ?>">
                            <?php echo $is_package_completed ? 'Overall Score' : ($show_reviewed_scores ? 'Head-Reviewed Score' : 'Submitted Self Total'); ?>
                        </span>
                        <div class="d-flex align-items-baseline gap-2 mt-1">
                            <strong class="tabular-nums" style="<?php echo $score_value_style; ?>"><?php echo number_format($ts_val, 2); ?></strong>
                            <?php if ($show_reviewed_scores && abs($ts_val - $orig_total_score) > 0.001): ?>
                                <span class="badge bg-light text-dark border small" title="Original Self Total: <?php echo number_format($orig_total_score, 2); ?>">
                                    Orig: <?php echo number_format($orig_total_score, 2); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="small mt-1" style="<?php echo $score_sub_style; ?>">
                            Performance Level: <strong><?php echo e($show_reviewed_scores ? ($evaluation['performance_level'] ?: getPerformanceLevel($ts_val)) : 'Pending Final Approval'); ?></strong>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6">
                    <div class="package-stat h-100 d-flex flex-column justify-content-between" style="border:2px solid var(--bs-border-color,#dee2e6);border-radius:10px;padding:14px 18px;">
                        <span class="text-muted small text-uppercase fw-bold"><?php echo $is_probationary_evaluation ? 'Individual Core Values' : 'Shared Team Behavior'; ?></span>
                        <strong class="tabular-nums mt-1" style="color:var(--rp-primary-gold-dark);font-size:1.8rem;">
                            <?php echo $is_probationary_evaluation
                                ? number_format($show_reviewed_scores ? (float)$evaluation['behavior_average'] : $orig_beh_average, 2)
                                : (($is_package_completed && $evaluation['shared_behavior_score'] !== null) ? number_format((float)$evaluation['shared_behavior_score'], 2) : 'Pending'); ?>
                        </strong>
                        <div class="small text-muted mt-1">
                            <?php echo $is_probationary_evaluation ? 'Individual score (20%)' : ($is_package_completed ? 'Finalized department shared score' : 'Calculated and applied at final Board approval'); ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($has_any_adjustment): ?>
                <div class="alert alert-warning d-flex align-items-center gap-2 p-3 mt-3 mb-0" style="border-radius:10px;">
                    <i class="fas fa-info-circle fa-lg text-warning"></i>
                    <div class="small">
                        <strong>Audit Notice:</strong> Ratings marked with <span class="audit-chip audit-chip--adjusted">Adjusted</span> were modified by an evaluator or supervisor during the consolidation review process.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ── Tabbed Sections ──────────────────────────────────────────────── -->
    <style>
        .eval-tabs-wrapper{background:#fff;border-radius:14px;border:1px solid var(--bs-border-color,#dee2e6);box-shadow:0 2px 12px rgba(0,0,0,.07);overflow:hidden;display:flex;flex-direction:column;}
        .eval-tab-nav{display:flex;gap:6px;padding:14px 16px 0;border-bottom:1px solid #e2e8f0;background:#f8fafc;flex-shrink:0;flex-wrap:wrap;}
        .eval-tab-btn{background:none;border:none;padding:8px 18px 10px;border-radius:8px 8px 0 0;font-size:.82rem;font-weight:600;color:#64748b;cursor:pointer;letter-spacing:.3px;transition:color .18s,background .18s,border-bottom .18s;border-bottom:3px solid transparent;position:relative;top:1px;}
        .eval-tab-btn:hover{color:#0a3a2a;background:#edf7f2;}
        .eval-tab-btn.active{color:#0a3a2a;border-bottom:3px solid #0a3a2a;background:#fff;}
        .eval-tab-badge{display:inline-flex;align-items:center;justify-content:center;background:#e2e8f0;color:#475569;border-radius:20px;font-size:.7rem;font-weight:700;padding:1px 7px;margin-left:6px;min-width:22px;}
        .eval-tab-btn.active .eval-tab-badge{background:#0a3a2a;color:#fff;}
        .eval-tab-content{display:none;padding:20px;}
        .eval-tab-content.active{display:block;}
        @media (max-width: 575.98px) {
            .evaluation-history-view { padding: .75rem !important; }
            .evaluation-history-view .package-hero {
                margin-bottom: .85rem;
                padding: 1rem .9rem;
                border-left-width: 4px;
                border-radius: 0 12px 12px 0;
            }
            .evaluation-history-view .package-hero .history-back-link {
                margin-bottom: .6rem;
                font-size: .8rem;
            }
            .evaluation-history-view .package-hero p {
                font-size: .78rem;
                line-height: 1.45;
                overflow-wrap: anywhere;
            }
            .evaluation-history-view .package-hero h1 {
                font-size: 1.1rem;
                line-height: 1.3;
                overflow-wrap: anywhere;
                word-break: normal;
            }
            .evaluation-history-view .package-card { margin-bottom: .9rem; border-radius: 11px; }
            .evaluation-history-view .package-card__header {
                align-items: flex-start;
                flex-direction: column;
                gap: .55rem;
                padding: .85rem .9rem;
            }
            .evaluation-history-view .package-card__header h2 {
                font-size: .95rem;
                line-height: 1.35;
                overflow-wrap: anywhere;
            }
            .evaluation-history-view .package-card__header .small { line-height: 1.4; }
            .evaluation-history-view .package-card__header > div:last-child { max-width: 100%; }
            .evaluation-history-view .package-card__header .pipeline-badge { max-width: 100%; }
            .evaluation-history-view .package-card__body { padding: .85rem; }
            .evaluation-history-view .package-card__body .alert { padding: .7rem !important; font-size: .78rem; line-height: 1.45; }
            .evaluation-history-view .package-stat {
                min-width: 0;
                padding: .75rem .85rem !important;
                gap: .3rem;
                overflow-wrap: anywhere;
            }
            .evaluation-history-view .package-stat strong { font-size: 1.45rem !important; }
            .evaluation-history-view .package-stat .small { line-height: 1.4; }
            .evaluation-history-view .eval-tab-nav {
                flex-wrap: nowrap;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: thin;
                gap: 4px;
                padding: 8px 8px 0;
            }
            .evaluation-history-view .eval-tab-btn {
                flex: 0 0 auto;
                white-space: nowrap;
                padding: 8px 10px 9px;
                font-size: .72rem;
            }
            .evaluation-history-view .eval-tab-content { padding: 12px 10px; }
            .evaluation-history-view .eval-tab-content .table-responsive { overflow: visible; }
            .evaluation-history-view .eval-tab-content table,
            .evaluation-history-view .eval-tab-content tbody,
            .evaluation-history-view .eval-tab-content tr,
            .evaluation-history-view .eval-tab-content td { display: block; width: 100%; }
            .evaluation-history-view .eval-tab-content table { border: 0; }
            .evaluation-history-view .eval-tab-content thead { display: none; }
            .evaluation-history-view .eval-tab-content tbody tr {
                overflow: hidden;
                margin-bottom: .7rem;
                border: 1px solid #e2e8f0;
                border-radius: 10px;
                background: #fff;
            }
            .evaluation-history-view .eval-tab-content td {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: .75rem;
                padding: .55rem .65rem !important;
                border: 0;
                border-bottom: 1px solid #eef2f5;
                text-align: right !important;
                overflow-wrap: anywhere;
                font-size: .84rem;
            }
            .evaluation-history-view .eval-tab-content td:last-child { border-bottom: 0; }
            .evaluation-history-view .eval-tab-content td::before {
                content: attr(data-label);
                flex: 0 0 38%;
                color: #64748b;
                font-size: .65rem;
                font-weight: 800;
                line-height: 1.35;
                text-align: left;
                text-transform: uppercase;
                letter-spacing: .25px;
            }
            .evaluation-history-view .eval-tab-content td[data-label="Criterion"] {
                display: block;
                text-align: left !important;
                background: #f8fafc;
            }
            .evaluation-history-view .eval-tab-content td[data-label="Criterion"]::before {
                display: block;
                margin-bottom: .3rem;
            }
            .evaluation-history-view .eval-tab-content td[data-label="Criterion"] .fw-bold { font-size: .92rem !important; }
            .evaluation-history-view .eval-tab-content td[data-label="Criterion"] .small { line-height: 1.4; }
            .evaluation-history-view .eval-tab-content .d-flex.justify-content-end {
                justify-content: space-between !important;
                flex-wrap: wrap;
                gap: .45rem .7rem !important;
                font-size: .82rem;
            }
        }
    </style>

    <div class="eval-tabs-wrapper">
        <nav class="eval-tab-nav" role="tablist" aria-label="Evaluation Sections">
            <button class="eval-tab-btn active" role="tab" aria-selected="true"  onclick="switchHistoryTab(event,'tab-kra')"   id="htab-kra">
                <i class="fas fa-chart-pie me-1"></i>Key Result Areas
                <span class="eval-tab-badge"><?php echo count($kra); ?></span>
            </button>
            <button class="eval-tab-btn"        role="tab" aria-selected="false" onclick="switchHistoryTab(event,'tab-beh')"   id="htab-beh">
                <i class="fas fa-users me-1"></i>Core Behaviors &amp; Values
                <span class="eval-tab-badge"><?php echo count($behavior); ?></span>
            </button>
            <?php
            $dev_plan_stmt = $conn->prepare("SELECT * FROM evaluation_dev_plans WHERE evaluation_id = ? ORDER BY sort_order");
            $dev_plan_stmt->bind_param('i', $evaluation_id);
            $dev_plan_stmt->execute();
            $hist_dev_plans = $dev_plan_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $dev_plan_stmt->close();
            ?>
            <button class="eval-tab-btn"        role="tab" aria-selected="false" onclick="switchHistoryTab(event,'tab-devplan')" id="htab-devplan">
                <i class="fas fa-seedling me-1"></i>Developmental Plan
                <?php if ($is_package_completed && !empty($hist_dev_plans)): ?>
                    <span class="eval-tab-badge"><?php echo count($hist_dev_plans); ?></span>
                <?php endif; ?>
            </button>
            <?php if (($is_package_completed && $remarks) || !empty($evaluation['employee_signature_data']) || !empty($evaluation['employee_consent_agreed']) || !empty($evaluation['immediate_head_signature_data'])): ?>
            <button class="eval-tab-btn"        role="tab" aria-selected="false" onclick="switchHistoryTab(event,'tab-remarks')" id="htab-remarks">
                <i class="fas fa-file-signature me-1"></i><?php echo $is_probationary_evaluation ? 'Digital Signature' : ($is_package_completed && $remarks ? 'Remarks & Signature' : 'Digital Signature'); ?>
            </button>
            <?php endif; ?>
        </nav>

        <!-- Tab 1: KRA -->
        <div class="eval-tab-content active" id="tab-kra" role="tabpanel">
            <?php if (!empty($kra)): ?>
            <div class="table-responsive">
                <table class="package-table table align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="<?php echo $show_reviewed_scores ? 'width:40%;' : 'width:60%;'; ?>">Criterion</th>
                            <th class="text-end" style="<?php echo $show_reviewed_scores ? 'width:10%;' : 'width:20%;'; ?>">Weight</th>
                            <th class="text-end" style="<?php echo $show_reviewed_scores ? 'width:18%;' : 'width:20%;'; ?>">
                                <?php echo $show_reviewed_scores ? 'Original Self-Rating' : 'Submitted Rating'; ?>
                            </th>
                            <?php if ($show_reviewed_scores): ?>
                                <th class="text-end" style="width:18%;">Reviewed / Adjusted</th>
                                <th class="text-center" style="width:14%;">Variance</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kra as $criterion): ?>
                        <?php
                            $orig = (float)$criterion['score_value'];
                            $rev  = (float)$criterion['reviewed_score'];
                            $diff = round($rev - $orig, 2);
                            $is_adj = abs($diff) > 0.001;
                        ?>
                        <tr>
                            <td data-label="Criterion">
                                <div class="fw-bold text-dark" style="font-size:1.05rem;"><?php echo e($criterion['criterion_name']); ?></div>
                                <?php if (!empty($criterion['description'])): ?>
                                    <div class="small text-muted mt-1"><?php echo e($criterion['description']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Weight" class="text-end tabular-nums fw-bold text-muted"><?php echo number_format((float)$criterion['weight'], 2); ?>%</td>
                            <td data-label="<?php echo $show_reviewed_scores ? 'Original Self-Rating' : 'Submitted Rating'; ?>" class="text-end tabular-nums fw-semibold" style="font-size:1.05rem;"><?php echo number_format($orig, 2); ?></td>
                            <?php if ($show_reviewed_scores): ?>
                                <td data-label="Reviewed / Adjusted" class="text-end tabular-nums fw-bold text-dark" style="font-size:1.1rem;"><?php echo number_format($rev, 2); ?></td>
                                <td data-label="Variance" class="text-center">
                                    <?php if ($is_adj): ?>
                                        <span class="badge <?php echo $diff > 0 ? 'bg-success' : 'bg-danger'; ?> px-2 py-1" style="font-size:.85rem;">
                                            <?php echo ($diff > 0 ? '+' : '') . number_format($diff, 2); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-end align-items-center gap-3 mt-3 pt-3 border-top">
                <span class="text-muted small text-uppercase fw-bold">Key Result Areas (KRA) Total:</span>
                <span class="fw-bold tabular-nums" style="font-size:1.15rem;">
                    <?php echo number_format($show_reviewed_scores ? (float)$evaluation['kra_subtotal'] : $orig_kra_subtotal, 2); ?>
                    <?php if ($show_reviewed_scores && abs((float)$evaluation['kra_subtotal'] - $orig_kra_subtotal) > 0.001): ?>
                        <span class="badge bg-warning text-dark ms-1" title="Original: <?php echo number_format($orig_kra_subtotal, 2); ?>">Self: <?php echo number_format($orig_kra_subtotal, 2); ?></span>
                    <?php endif; ?>
                </span>
                <span class="text-muted small">(Weight: <?php echo number_format($kra_weight, 0); ?>%)</span>
            </div>
            <?php else: ?>
                <p class="text-muted small mb-0"><i class="fas fa-info-circle me-1"></i>No KRA criteria recorded.</p>
            <?php endif; ?>
        </div>

        <!-- Tab 2: Core Behaviors -->
        <div class="eval-tab-content" id="tab-beh" role="tabpanel">
            <?php if (!empty($behavior)): ?>
            <div class="table-responsive">
                <table class="package-table table align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="<?php echo $show_reviewed_scores ? 'width:40%;' : 'width:70%;'; ?>">Criterion</th>
                            <th class="text-end" style="<?php echo $show_reviewed_scores ? 'width:20%;' : 'width:30%;'; ?>">
                                <?php echo $show_reviewed_scores ? 'Original Self-Rating' : 'Submitted Rating'; ?>
                            </th>
                            <?php if ($show_reviewed_scores): ?>
                                <th class="text-end" style="width:20%;">Reviewed / Adjusted</th>
                                <th class="text-center" style="width:20%;">Variance</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($behavior as $criterion): ?>
                        <?php
                            $orig = (float)$criterion['score_value'];
                            $rev  = (float)$criterion['reviewed_score'];
                            $diff = round($rev - $orig, 2);
                            $is_adj = abs($diff) > 0.001;
                        ?>
                        <tr>
                            <td data-label="Criterion">
                                <div class="fw-bold text-dark" style="font-size:1.05rem;"><?php echo e($criterion['criterion_name']); ?></div>
                                <?php if (!empty($criterion['description'])): ?>
                                    <div class="small text-muted mt-1"><?php echo e($criterion['description']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td data-label="<?php echo $show_reviewed_scores ? 'Original Self-Rating' : 'Submitted Rating'; ?>" class="text-end tabular-nums fw-semibold" style="font-size:1.05rem;"><?php echo number_format($orig, 2); ?></td>
                            <?php if ($show_reviewed_scores): ?>
                                <td data-label="Reviewed / Adjusted" class="text-end tabular-nums fw-bold text-dark" style="font-size:1.1rem;"><?php echo number_format($rev, 2); ?></td>
                                <td data-label="Variance" class="text-center">
                                    <?php if ($is_adj): ?>
                                        <span class="badge <?php echo $diff > 0 ? 'bg-success' : 'bg-danger'; ?> px-2 py-1" style="font-size:.85rem;">
                                            <?php echo ($diff > 0 ? '+' : '') . number_format($diff, 2); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-end align-items-center gap-3 mt-3 pt-3 border-top">
                <span class="text-muted small text-uppercase fw-bold">Core Behaviors &amp; Values Total:</span>
                <span class="fw-bold tabular-nums" style="font-size:1.15rem;">
                    <?php echo number_format($show_reviewed_scores ? (float)$evaluation['behavior_average'] : $orig_beh_average, 2); ?>
                    <?php if ($show_reviewed_scores && abs((float)$evaluation['behavior_average'] - $orig_beh_average) > 0.001): ?>
                        <span class="badge bg-warning text-dark ms-1" title="Original: <?php echo number_format($orig_beh_average, 2); ?>">Self: <?php echo number_format($orig_beh_average, 2); ?></span>
                    <?php endif; ?>
                </span>
                <span class="text-muted small">(Weight: <?php echo number_format($beh_weight, 0); ?>%)</span>
            </div>
            <?php else: ?>
                <p class="text-muted small mb-0"><i class="fas fa-info-circle me-1"></i>No behavior criteria recorded.</p>
            <?php endif; ?>
        </div>

        <!-- Tab 3: Developmental Plan -->
        <div class="eval-tab-content" id="tab-devplan" role="tabpanel">
            <?php if ($is_package_completed): ?>
                <?php if (!empty($hist_dev_plans)): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Area for Improvement / Development</th>
                                    <th>Support Needed / Action Plan</th>
                                    <th>Target Time Frame</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($hist_dev_plans as $dp): ?>
                                <tr>
                                    <td data-label="Area for Improvement / Development" class="fw-semibold text-dark"><?php echo e($dp['improvement_area'] ?: '—'); ?></td>
                                    <td data-label="Support Needed / Action Plan"><?php echo e($dp['support_needed'] ?: '—'); ?></td>
                                    <td data-label="Target Time Frame"><span class="badge bg-light text-dark border"><?php echo e($dp['time_frame'] ?: '—'); ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-light border text-muted py-4 text-center mb-0 rounded-3">
                        <i class="fas fa-seedling fa-2x mb-2 text-secondary" style="opacity:0.5;"></i>
                        <p class="mb-0">No developmental plan items were recorded for this evaluation cycle.</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-info border d-flex align-items-center gap-3 p-4 mb-0 rounded-3 shadow-sm" style="background-color: #f0fdf4; border-color: #86efac !important; color: #166534;">
                    <i class="fas fa-lock fa-2x text-success"></i>
                    <div>
                        <h6 class="fw-bold mb-1" style="color: #14532d;">Developmental Plan &amp; Recommendations Under Review</h6>
                        <p class="mb-0 small" style="color: #15803d; line-height: 1.5;">
                            Supervisory guidance, developmental recommendations, and support action plans are currently being formulated during the consolidation review. Official developmental goals will be published here upon final Board approval.
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tab 4: Remarks & Signature -->
        <?php if (($is_package_completed && $remarks) || !empty($evaluation['employee_signature_data']) || !empty($evaluation['employee_consent_agreed']) || !empty($evaluation['immediate_head_signature_data'])): ?>
        <div class="eval-tab-content" id="tab-remarks" role="tabpanel">

            <?php if ($is_probationary_evaluation && !empty($evaluation['immediate_head_signature_data'])): ?>
            <section class="card border-primary-subtle mb-4">
                <div class="card-body p-4">
                    <h3 class="h5 fw-bold mb-3"><i class="fas fa-file-signature me-2 text-primary"></i>Probationary Decision &amp; Signatures</h3>
                    <div class="row g-3 mb-4">
                        <div class="col-lg-6">
                            <div class="border rounded p-3 h-100">
                                <div class="small text-muted">Immediate head recommendation</div>
                                <strong><?php echo e($evaluation['probationary_recommendation'] ?? 'Not recorded'); ?></strong>
                                <?php if (!empty($evaluation['probationary_recommendation_reason'])): ?><p class="mb-0 mt-2"><?php echo nl2br(e($evaluation['probationary_recommendation_reason'])); ?></p><?php endif; ?>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="border rounded p-3 h-100">
                                <div class="small text-muted mb-2">Immediate head’s signature</div>
                                <?php if (!empty($evaluation['immediate_head_signature_data'])): ?>
                                    <img src="<?php echo e($evaluation['immediate_head_signature_data']); ?>" alt="Immediate head signature" style="display:block;max-height:64px;max-width:260px;">
                                    <small class="text-muted">Signed <?php echo e($evaluation['immediate_head_signed_at'] ?? ''); ?></small>
                                <?php else: ?><span class="text-muted">Awaiting signature</span><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="border rounded p-3">
                        <div class="small text-muted mb-2">Employee’s self-rating signature</div>
                        <?php if (!empty($evaluation['employee_signature_data'])): ?>
                            <img src="<?php echo e($evaluation['employee_signature_data']); ?>" alt="Employee self-rating signature" style="display:block;max-height:64px;max-width:260px;">
                            <small class="text-muted">Signed <?php echo e($evaluation['employee_signed_at'] ?? ''); ?></small>
                            <div class="small text-muted mt-2">This is the signature captured with your original self-rating. No second signature is required.</div>
                        <?php else: ?><span class="text-muted">No employee signature on file</span><?php endif; ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($is_package_completed && $remarks): ?>
            <h3 class="h6 fw-bold text-uppercase text-muted mb-3" style="letter-spacing:.5px;">
                <i class="fas fa-comments me-1"></i>Evaluator Comments &amp; Review Remarks
            </h3>
            <?php foreach ($remarks as $remark): ?>
                <div class="p-3 bg-light rounded-3 mb-2 border">
                    <i class="fas fa-quote-left text-muted me-2"></i><?php echo nl2br(e($remark)); ?>
                </div>
            <?php endforeach; ?>
            <?php endif; ?>

            <?php if ((!$is_probationary_evaluation || empty($evaluation['immediate_head_signature_data'])) && (!empty($evaluation['employee_signature_data']) || !empty($evaluation['employee_consent_agreed']))): ?>
            <h3 class="h6 fw-bold text-uppercase text-muted mb-3 <?php echo $remarks ? 'mt-4' : ''; ?>" style="letter-spacing:.5px;">
                <i class="fas fa-file-signature me-1"></i>Employee Declaration &amp; Digital Signature
            </h3>
            <div class="row align-items-center g-3">
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded border">
                        <span class="badge bg-success mb-2"><i class="fas fa-check-circle me-1"></i>Declaration Verified</span>
                        <div class="small text-secondary mb-0">
                            <em>"I hereby declare and certify that the scores, self-assessment ratings, and comments provided in this form are accurate, complete, and submitted voluntarily."</em>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 text-md-center">
                    <div class="p-3 bg-white rounded border d-inline-block text-center shadow-sm">
                        <div class="small text-muted mb-1 fw-bold text-uppercase" style="font-size:.65rem;">Employee Signature</div>
                        <?php if (!empty($evaluation['employee_signature_data'])): ?>
                            <img src="<?php echo e($evaluation['employee_signature_data']); ?>" alt="Employee Digital Signature" style="max-height:70px;max-width:250px;display:block;margin:0 auto 5px;">
                        <?php endif; ?>
                        <div class="fw-bold small text-dark border-top pt-1"><?php echo e($evaluation['employee_name']); ?></div>
                        <div class="small text-muted" style="font-size:.75rem;">Signed on: <?php echo !empty($evaluation['employee_signed_at']) ? formatDateTime($evaluation['employee_signed_at']) : formatDateTime($evaluation['submitted_date']); ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
        <?php endif; ?>

    </div><!-- /.eval-tabs-wrapper -->

    <script>
    (function () {
        function switchHistoryTab(e, tabId) {
            var button = e.currentTarget;
            var wrapper = button.closest('.eval-tabs-wrapper');
            wrapper.querySelectorAll('.eval-tab-btn').forEach(function(b){ b.classList.remove('active'); b.setAttribute('aria-selected','false'); });
            wrapper.querySelectorAll('.eval-tab-content').forEach(function(c){ c.classList.remove('active'); });
            button.classList.add('active');
            button.setAttribute('aria-selected','true');
            var panel = wrapper.querySelector('#' + tabId);
            if (panel) panel.classList.add('active');
        }
        window.switchHistoryTab = switchHistoryTab;
    })();
    document.addEventListener('DOMContentLoaded', function () {
    });
    </script>

</main>
<?php require_once '../includes/footer.php'; ?>

