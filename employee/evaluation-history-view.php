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
                <div class="col-sm-6 col-md-3">
                    <div class="package-stat">
                        <span class="text-muted small text-uppercase fw-bold">Individual KRA</span>
                        <div class="d-flex align-items-baseline gap-2 mt-1">
                            <strong class="tabular-nums text-dark"><?php echo number_format((float)$evaluation['kra_subtotal'], 2); ?></strong>
                            <?php if (abs((float)$evaluation['kra_subtotal'] - $orig_kra_subtotal) > 0.001): ?>
                                <span class="badge bg-warning text-dark small" title="Original Self-Rating: <?php echo number_format($orig_kra_subtotal, 2); ?>">
                                    Self: <?php echo number_format($orig_kra_subtotal, 2); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="small text-muted">Weight: <?php echo number_format($kra_weight, 0); ?>%</div>
                    </div>
                </div>

                <div class="col-sm-6 col-md-3">
                    <div class="package-stat">
                        <span class="text-muted small text-uppercase fw-bold">Behavior Rating</span>
                        <div class="d-flex align-items-baseline gap-2 mt-1">
                            <strong class="tabular-nums text-dark"><?php echo number_format((float)$evaluation['behavior_average'], 2); ?></strong>
                            <?php if (abs((float)$evaluation['behavior_average'] - $orig_beh_average) > 0.001): ?>
                                <span class="badge bg-warning text-dark small" title="Original Self-Rating: <?php echo number_format($orig_beh_average, 2); ?>">
                                    Self: <?php echo number_format($orig_beh_average, 2); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="small text-muted">Weight: <?php echo number_format($beh_weight, 0); ?>%</div>
                    </div>
                </div>

                <div class="col-sm-6 col-md-3">
                    <div class="package-stat">
                        <span class="text-muted small text-uppercase fw-bold">Overall Score</span>
                        <div class="d-flex align-items-baseline gap-2 mt-1">
                            <strong class="tabular-nums text-success" style="font-size:1.8rem;"><?php echo $evaluation['total_score'] !== null ? number_format((float)$evaluation['total_score'], 2) : '&mdash;'; ?></strong>
                            <?php if ($evaluation['total_score'] !== null && abs((float)$evaluation['total_score'] - $orig_total_score) > 0.001): ?>
                                <span class="badge bg-light text-dark border small" title="Original Self Total: <?php echo number_format($orig_total_score, 2); ?>">
                                    Orig: <?php echo number_format($orig_total_score, 2); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="small text-muted">Performance Level: <strong><?php echo e($evaluation['performance_level'] ?: '&mdash;'); ?></strong></div>
                    </div>
                </div>

                <div class="col-sm-6 col-md-3">
                    <div class="package-stat">
                        <span class="text-muted small text-uppercase fw-bold">Shared Team Behavior</span>
                        <strong class="tabular-nums" style="color:var(--rp-primary-gold-dark);">
                            <?php echo $evaluation['shared_behavior_score'] !== null ? number_format((float)$evaluation['shared_behavior_score'], 2) : 'Pending'; ?>
                        </strong>
                        <div class="small text-muted">Applied at Board approval</div>
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

    <!-- ── Criteria Breakdown Tables ──────────────────────────────────────── -->
    <?php foreach (['KRA' => ['title' => 'Key Result Areas (KRA)', 'items' => $kra], 'Behavior' => ['title' => 'Core Behaviors & Values', 'items' => $behavior]] as $sec_key => $sec_data): ?>
        <?php if (!empty($sec_data['items'])): ?>
            <section class="package-card" role="region" aria-label="<?php echo e($sec_data['title']); ?>">
                <header class="package-card__header">
                    <h2 class="h5 mb-0 fw-bold"><i class="fas <?php echo $sec_key === 'KRA' ? 'fa-chart-pie' : 'fa-users'; ?> me-2"></i><?php echo e($sec_data['title']); ?></h2>
                </header>
                <div class="package-card__body">
                    <div class="table-responsive">
                        <table class="package-table table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 40%;">Criterion</th>
                                    <?php if ($sec_key === 'KRA'): ?>
                                        <th class="text-end" style="width: 10%;">Weight</th>
                                    <?php endif; ?>
                                    <th class="text-end" style="width: 18%;">Original Self-Rating</th>
                                    <th class="text-end" style="width: 18%;">Reviewed / Adjusted Rating</th>
                                    <th class="text-center" style="width: 14%;">Variance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sec_data['items'] as $criterion): ?>
                                    <?php
                                    $orig = (float)$criterion['score_value'];
                                    $rev = (float)$criterion['reviewed_score'];
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
                                        <?php if ($sec_key === 'KRA'): ?>
                                            <td class="text-end tabular-nums fw-bold text-muted"><?php echo number_format((float)$criterion['weight'], 2); ?>%</td>
                                        <?php endif; ?>
                                        <td class="text-end tabular-nums fw-semibold" style="font-size:1.05rem;">
                                            <?php echo number_format($orig, 2); ?>
                                        </td>
                                        <td class="text-end tabular-nums fw-bold text-dark" style="font-size:1.1rem;">
                                            <?php echo number_format($rev, 2); ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($is_adj): ?>
                                                <span class="badge <?php echo $diff > 0 ? 'bg-success' : 'bg-danger'; ?> px-2 py-1" style="font-size:0.85rem;">
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
                </div>
            </section>
        <?php endif; ?>
    <?php endforeach; ?>

    <!-- Developmental Plan Section -->
    <?php
    $dev_plan_stmt = $conn->prepare("SELECT * FROM evaluation_dev_plans WHERE evaluation_id = ? ORDER BY sort_order");
    $dev_plan_stmt->bind_param('i', $evaluation_id);
    $dev_plan_stmt->execute();
    $hist_dev_plans = $dev_plan_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $dev_plan_stmt->close();
    ?>
    <?php if (!empty($hist_dev_plans)): ?>
        <section class="package-card" aria-label="Developmental Plan">
            <header class="package-card__header">
                <h2 class="h5 mb-0 fw-bold"><i class="fas fa-seedling me-2"></i>Developmental Plan &amp; Recommendations</h2>
            </header>
            <div class="package-card__body">
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
        </section>
    <?php endif; ?>

    <!-- Remarks / Comments Audit Log -->
    <?php if ($remarks): ?>
        <section class="package-card" aria-label="Review Remarks">
            <header class="package-card__header">
                <h2 class="h5 mb-0 fw-bold"><i class="fas fa-comments me-2"></i>Evaluator Comments &amp; Review Remarks</h2>
            </header>
            <div class="package-card__body">
                <?php foreach ($remarks as $remark): ?>
                    <div class="p-3 bg-light rounded-3 mb-2 border">
                        <i class="fas fa-quote-left text-muted me-2"></i><?php echo nl2br(e($remark)); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
    <!-- Digital Signature & Declaration Audit -->
    <?php if (!empty($evaluation['employee_signature_data']) || !empty($evaluation['employee_consent_agreed'])): ?>
        <section class="package-card" aria-label="Digital Signature & Declaration">
            <header class="package-card__header">
                <h2 class="h5 mb-0 fw-bold"><i class="fas fa-file-signature me-2"></i>Employee Declaration & Digital Signature</h2>
            </header>
            <div class="package-card__body">
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
                            <div class="small text-muted mb-1 fw-bold text-uppercase" style="font-size:0.65rem;">Employee Signature</div>
                            <?php if (!empty($evaluation['employee_signature_data'])): ?>
                                <img src="<?php echo e($evaluation['employee_signature_data']); ?>" alt="Employee Digital Signature" style="max-height: 70px; max-width: 250px; display: block; margin: 0 auto 5px;">
                            <?php endif; ?>
                            <div class="fw-bold small text-dark border-top pt-1"><?php echo e($evaluation['employee_name']); ?></div>
                            <div class="small text-muted" style="font-size:0.75rem;">Signed on: <?php echo !empty($evaluation['employee_signed_at']) ? formatDateTime($evaluation['employee_signed_at']) : formatDateTime($evaluation['submitted_date']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>
</main>
<?php require_once '../includes/footer.php'; ?>
