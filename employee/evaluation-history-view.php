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
        ep.package_id, ep.status AS package_status, ep.shared_behavior_score, d.department_name,
        CONCAT(emp.first_name, ' ', emp.last_name) AS employee_name
    FROM evaluations ev
    JOIN users u ON u.employee_id = ev.employee_id AND u.user_id = ?
    JOIN evaluation_templates et ON et.template_id = ev.template_id
    JOIN employees emp ON emp.employee_id = ev.employee_id
    LEFT JOIN departments d ON d.department_id = emp.department_id
    LEFT JOIN evaluation_package_members pm ON pm.evaluation_id = ev.evaluation_id
    LEFT JOIN evaluation_packages ep ON ep.package_id = pm.package_id
    WHERE ev.evaluation_id = ? AND ev.deleted_at IS NULL
    LIMIT 1");
$evaluation_stmt->bind_param('ii', $user_id, $evaluation_id);
$evaluation_stmt->execute();
$evaluation = $evaluation_stmt->get_result()->fetch_assoc();
$evaluation_stmt->close();
if (!$evaluation) {
    redirectWith(BASE_URL . '/employee/evaluation-history.php', 'danger', 'That evaluation is unavailable.');
}

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

// Calculate original self totals vs reviewed totals
$orig_kra_subtotal = 0.0;
$orig_beh_total = 0.0;
$orig_beh_count = 0;
$has_any_adjustment = false;

foreach ($criteria as $c) {
    $val = (float)$c['score_value'];
    $rev = (float)$c['reviewed_score'];
    if (abs($rev - $val) > 0.001) {
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
<main class="evaluation-packages container-fluid py-4">
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
                <h2 class="h5 mb-0 fw-bold"><i class="fas fa-balance-scale me-2"></i>Original Self-Rating vs. Final / Reviewed Rating</h2>
                <div class="small text-muted mt-1">Audit comparison between your submitted rating and officer adjustments.</div>
            </div>
            <div>
                <?php if (!empty($evaluation['package_id'])): ?>
                    <?php echo renderOrganizationPipelineBadge($conn, (int)$evaluation['package_id']); ?>
                <?php else: ?>
                    <span class="badge <?php echo $evaluation['status'] === 'Approved' ? 'bg-success' : 'bg-secondary'; ?>">
                        <?php echo e($evaluation['status']); ?>
                    </span>
                <?php endif; ?>
            </div>
        </header>
        <div class="package-card__body">
            <div class="row g-3">
                <div class="col-sm-6">
                    <div class="package-stat h-100 d-flex flex-column justify-content-between" style="border:2px solid var(--bs-border-color,#dee2e6);border-radius:10px;padding:14px 18px;">
                        <span class="text-muted small text-uppercase fw-bold">Overall Score</span>
                        <div class="d-flex align-items-baseline gap-2 mt-1">
                            <strong class="tabular-nums text-success" style="font-size:1.8rem;"><?php echo $evaluation['total_score'] !== null ? number_format((float)$evaluation['total_score'], 2) : '&mdash;'; ?></strong>
                            <?php if ($evaluation['total_score'] !== null && abs((float)$evaluation['total_score'] - $orig_total_score) > 0.001): ?>
                                <span class="badge bg-light text-dark border small" title="Original Self Total: <?php echo number_format($orig_total_score, 2); ?>">
                                    Orig: <?php echo number_format($orig_total_score, 2); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="small text-muted mt-1">Performance Level: <strong><?php echo e($evaluation['performance_level'] ?: '&mdash;'); ?></strong></div>
                    </div>
                </div>

                <div class="col-sm-6">
                    <div class="package-stat h-100 d-flex flex-column justify-content-between" style="border:2px solid var(--bs-border-color,#dee2e6);border-radius:10px;padding:14px 18px;">
                        <span class="text-muted small text-uppercase fw-bold">Shared Team Behavior</span>
                        <strong class="tabular-nums mt-1" style="color:var(--rp-primary-gold-dark);font-size:1.8rem;">
                            <?php echo $evaluation['shared_behavior_score'] !== null ? number_format((float)$evaluation['shared_behavior_score'], 2) : 'Pending'; ?>
                        </strong>
                        <div class="small text-muted mt-1">Applied at Board approval</div>
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
            <?php if (!empty($hist_dev_plans)): ?>
            <button class="eval-tab-btn"        role="tab" aria-selected="false" onclick="switchHistoryTab(event,'tab-devplan')" id="htab-devplan">
                <i class="fas fa-seedling me-1"></i>Developmental Plan
                <span class="eval-tab-badge"><?php echo count($hist_dev_plans); ?></span>
            </button>
            <?php endif; ?>
            <?php if ($remarks || !empty($evaluation['employee_signature_data']) || !empty($evaluation['employee_consent_agreed'])): ?>
            <button class="eval-tab-btn"        role="tab" aria-selected="false" onclick="switchHistoryTab(event,'tab-remarks')" id="htab-remarks">
                <i class="fas fa-comments me-1"></i>Remarks &amp; Signature
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
                            <th style="width:40%;">Criterion</th>
                            <th class="text-end" style="width:10%;">Weight</th>
                            <th class="text-end" style="width:18%;">Original Self-Rating</th>
                            <th class="text-end" style="width:18%;">Reviewed / Adjusted</th>
                            <th class="text-center" style="width:14%;">Variance</th>
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
                            <td>
                                <div class="fw-bold text-dark" style="font-size:1.05rem;"><?php echo e($criterion['criterion_name']); ?></div>
                                <?php if (!empty($criterion['description'])): ?>
                                    <div class="small text-muted mt-1"><?php echo e($criterion['description']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end tabular-nums fw-bold text-muted"><?php echo number_format((float)$criterion['weight'], 2); ?>%</td>
                            <td class="text-end tabular-nums fw-semibold" style="font-size:1.05rem;"><?php echo number_format($orig, 2); ?></td>
                            <td class="text-end tabular-nums fw-bold text-dark" style="font-size:1.1rem;"><?php echo number_format($rev, 2); ?></td>
                            <td class="text-center">
                                <?php if ($is_adj): ?>
                                    <span class="badge <?php echo $diff > 0 ? 'bg-success' : 'bg-danger'; ?> px-2 py-1" style="font-size:.85rem;">
                                        <?php echo ($diff > 0 ? '+' : '') . number_format($diff, 2); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">&mdash;</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-end align-items-center gap-3 mt-3 pt-3 border-top">
                <span class="text-muted small text-uppercase fw-bold">Key Result Areas (KRA) Total:</span>
                <span class="fw-bold tabular-nums" style="font-size:1.15rem;"><?php echo number_format((float)$evaluation['kra_subtotal'], 2); ?>
                    <?php if (abs((float)$evaluation['kra_subtotal'] - $orig_kra_subtotal) > 0.001): ?>
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
                            <th style="width:40%;">Criterion</th>
                            <th class="text-end" style="width:20%;">Original Self-Rating</th>
                            <th class="text-end" style="width:20%;">Reviewed / Adjusted</th>
                            <th class="text-center" style="width:20%;">Variance</th>
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
                            <td>
                                <div class="fw-bold text-dark" style="font-size:1.05rem;"><?php echo e($criterion['criterion_name']); ?></div>
                                <?php if (!empty($criterion['description'])): ?>
                                    <div class="small text-muted mt-1"><?php echo e($criterion['description']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end tabular-nums fw-semibold" style="font-size:1.05rem;"><?php echo number_format($orig, 2); ?></td>
                            <td class="text-end tabular-nums fw-bold text-dark" style="font-size:1.1rem;"><?php echo number_format($rev, 2); ?></td>
                            <td class="text-center">
                                <?php if ($is_adj): ?>
                                    <span class="badge <?php echo $diff > 0 ? 'bg-success' : 'bg-danger'; ?> px-2 py-1" style="font-size:.85rem;">
                                        <?php echo ($diff > 0 ? '+' : '') . number_format($diff, 2); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">&mdash;</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-end align-items-center gap-3 mt-3 pt-3 border-top">
                <span class="text-muted small text-uppercase fw-bold">Core Behaviors &amp; Values Total:</span>
                <span class="fw-bold tabular-nums" style="font-size:1.15rem;"><?php echo number_format((float)$evaluation['behavior_average'], 2); ?>
                    <?php if (abs((float)$evaluation['behavior_average'] - $orig_beh_average) > 0.001): ?>
                        <span class="badge bg-warning text-dark ms-1" title="Original: <?php echo number_format($orig_beh_average, 2); ?>">Self: <?php echo number_format($orig_beh_average, 2); ?></span>
                    <?php endif; ?>
                </span>
                <span class="text-muted small">(Weight: <?php echo number_format($beh_weight, 0); ?>%)</span>
            </div>
            <?php else: ?>
                <p class="text-muted small mb-0"><i class="fas fa-info-circle me-1"></i>No behavior criteria recorded.</p>
            <?php endif; ?>
        </div>

        <!-- Tab 3: Developmental Plan (conditionally rendered) -->
        <?php if (!empty($hist_dev_plans)): ?>
        <div class="eval-tab-content" id="tab-devplan" role="tabpanel">
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
                            <td class="fw-semibold text-dark"><?php echo e($dp['improvement_area'] ?: '—'); ?></td>
                            <td><?php echo e($dp['support_needed'] ?: '—'); ?></td>
                            <td><span class="badge bg-light text-dark border"><?php echo e($dp['time_frame'] ?: '—'); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tab 4: Remarks & Signature -->
        <?php if ($remarks || !empty($evaluation['employee_signature_data']) || !empty($evaluation['employee_consent_agreed'])): ?>
        <div class="eval-tab-content" id="tab-remarks" role="tabpanel">

            <?php if ($remarks): ?>
            <h3 class="h6 fw-bold text-uppercase text-muted mb-3" style="letter-spacing:.5px;">
                <i class="fas fa-comments me-1"></i>Evaluator Comments &amp; Review Remarks
            </h3>
            <?php foreach ($remarks as $remark): ?>
                <div class="p-3 bg-light rounded-3 mb-2 border">
                    <i class="fas fa-quote-left text-muted me-2"></i><?php echo nl2br(e($remark)); ?>
                </div>
            <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($evaluation['employee_signature_data']) || !empty($evaluation['employee_consent_agreed'])): ?>
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
            var wrapper = e.target.closest('.eval-tabs-wrapper');
            wrapper.querySelectorAll('.eval-tab-btn').forEach(function(b){ b.classList.remove('active'); b.setAttribute('aria-selected','false'); });
            wrapper.querySelectorAll('.eval-tab-content').forEach(function(c){ c.classList.remove('active'); });
            e.target.classList.add('active');
            e.target.setAttribute('aria-selected','true');
            var panel = wrapper.querySelector('#' + tabId);
            if (panel) panel.classList.add('active');
        }
        window.switchHistoryTab = switchHistoryTab;
    })();
    </script>

</main>
<?php require_once '../includes/footer.php'; ?>

