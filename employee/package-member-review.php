<?php
$page_title = 'Review Team Member Evaluation';
require_once '../includes/session-check.php';
require_once '../includes/functions.php';
ensureOrganizationEvaluationPackageSchema($conn);

$user_id = (int) $_SESSION['user_id'];
$package_id = (int) ($_GET['package_id'] ?? $_POST['package_id'] ?? 0);
$evaluation_id = (int) ($_GET['evaluation_id'] ?? $_POST['evaluation_id'] ?? 0);

$reviewer_match = organizationPackageReviewerMatchSql('rs');
$access = $conn->prepare("SELECT rs.step_label, rs.step_type, ep.department_id, ep.status
    FROM evaluation_package_route_steps rs
    JOIN evaluation_packages ep ON ep.package_id = rs.package_id
    JOIN evaluation_package_members pm ON pm.package_id = ep.package_id
    WHERE ep.package_id = ? AND pm.evaluation_id = ? AND $reviewer_match AND rs.action_status = 'Pending'
    LIMIT 1");
$access->bind_param('iiii', $package_id, $evaluation_id, $user_id, $user_id);
$access->execute();
$review_step = $access->get_result()->fetch_assoc();
$access->close();

if (!$review_step) {
    redirectWith(BASE_URL . '/employee/team-evaluation-packages.php', 'danger', 'This team-member evaluation is not currently assigned to you for review.');
}
if (($review_step['status'] ?? '') === 'Approved and Applied' || isOrganizationPackageLocked($conn, $package_id)) {
    redirectWith(BASE_URL . '/employee/team-evaluation-packages.php', 'danger', 'This package is locked after Board approval. Ratings can no longer be adjusted.');
}
if (($review_step['step_type'] ?? '') === 'Governance') {
    redirectWith(BASE_URL . '/employee/package-member-view.php?package_id=' . $package_id . '&evaluation_id=' . $evaluation_id, 'info', 'Governance steps are read-only. Use View for member details, then Approve or Return on the package.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    if (isOrganizationPackageLocked($conn, $package_id)) {
        redirectWith(BASE_URL . '/employee/team-evaluation-packages.php', 'danger', 'This package is locked after Board approval. Ratings can no longer be adjusted.');
    }
    $ratings = $_POST['rating'] ?? [];
    $score_stmt = $conn->prepare("SELECT es.score_id, es.score_value, es.supervisor_override_score, ec.criterion_id, ec.criterion_name FROM evaluation_scores es JOIN evaluation_criteria ec ON ec.criterion_id = es.criterion_id WHERE es.evaluation_id = ?");
    $score_stmt->bind_param('i', $evaluation_id);
    $score_stmt->execute();
    $scores = $score_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $score_stmt->close();
    $changes = [];

    foreach ($scores as $score) {
        $score_id = (int) $score['score_id'];
        if (!isset($ratings[$score_id]) || $ratings[$score_id] === '') continue;
        $new_rating = (float) $ratings[$score_id];
        if ($new_rating < 1 || $new_rating > 4) {
            redirectWith($_SERVER['REQUEST_URI'], 'danger', 'Every adjusted rating must be between 1.00 and 4.00.');
        }
        $current = $score['supervisor_override_score'] !== null ? (float) $score['supervisor_override_score'] : (float) $score['score_value'];
        if (abs($new_rating - $current) < 0.001) continue;

        $update = $conn->prepare('UPDATE evaluation_scores SET supervisor_override_score = ?, supervisor_override_by = ?, supervisor_override_at = NOW() WHERE score_id = ?');
        $update->bind_param('dii', $new_rating, $user_id, $score_id);
        $update->execute();
        $update->close();
        $changes[] = $score['criterion_name'] . ': ' . number_format($current, 2) . ' → ' . number_format($new_rating, 2);
    }

    // Process Developmental Plan updates
    $dev_plans = $_POST['plans'] ?? [];
    $conn->query("DELETE FROM evaluation_dev_plans WHERE evaluation_id = $evaluation_id");
    if (!empty($dev_plans) && is_array($dev_plans)) {
        $ins_plan = $conn->prepare("INSERT INTO evaluation_dev_plans (evaluation_id, improvement_area, support_needed, time_frame, sort_order) VALUES (?, ?, ?, ?, ?)");
        $sort_idx = 0;
        foreach ($dev_plans as $p) {
            $ia = trim($p['improvement_area'] ?? '');
            $sn = trim($p['support_needed'] ?? '');
            $tf = trim($p['time_frame'] ?? '');
            if ($ia !== '' || $sn !== '' || $tf !== '') {
                $ins_plan->bind_param('isssi', $evaluation_id, $ia, $sn, $tf, $sort_idx);
                $ins_plan->execute();
                $sort_idx++;
            }
        }
        $ins_plan->close();
    }

    if ($changes) {
        recalculateEvaluationScores($conn, $evaluation_id);
        recalculateOrganizationPackageBehaviorScore($conn, $package_id);
        $remarks = 'Adjusted ' . implode('; ', $changes);
        $audit = $conn->prepare("INSERT INTO evaluation_package_audit (package_id, user_id, action, remarks) VALUES (?, ?, 'MEMBER_SCORES_ADJUSTED', ?)");
        $audit->bind_param('iis', $package_id, $user_id, $remarks);
        $audit->execute();
        $audit->close();
        redirectWith(BASE_URL . '/employee/package-member-review.php?package_id=' . $package_id . '&evaluation_id=' . $evaluation_id, 'success', 'Individual ratings, shared Behavior score, and Developmental Plan were saved successfully.');
    }
    redirectWith(BASE_URL . '/employee/package-member-review.php?package_id=' . $package_id . '&evaluation_id=' . $evaluation_id, 'success', 'Developmental plan and adjustments saved successfully.');
}

require_once '../includes/header.php';
$evaluation_stmt = $conn->prepare("SELECT ev.*, emp.first_name, emp.last_name, emp.job_title, et.template_name, et.kra_weight, et.behavior_weight, ep.shared_behavior_score
    FROM evaluations ev
    JOIN evaluation_package_members pm ON pm.evaluation_id = ev.evaluation_id
    JOIN evaluation_packages ep ON ep.package_id = pm.package_id
    JOIN employees emp ON emp.employee_id = ev.employee_id
    JOIN evaluation_templates et ON et.template_id = ev.template_id
    WHERE ev.evaluation_id = ? AND ep.package_id = ?
    LIMIT 1");
$evaluation_stmt->bind_param('ii', $evaluation_id, $package_id);
$evaluation_stmt->execute();
$evaluation = $evaluation_stmt->get_result()->fetch_assoc();
$evaluation_stmt->close();

$criteria_stmt = $conn->prepare("SELECT es.score_id, es.score_value, es.supervisor_override_score, ec.section, ec.criterion_name, ec.description, ec.weight
    FROM evaluation_scores es
    JOIN evaluation_criteria ec ON ec.criterion_id = es.criterion_id
    WHERE es.evaluation_id = ?
    ORDER BY ec.section, ec.sort_order");
$criteria_stmt->bind_param('i', $evaluation_id);
$criteria_stmt->execute();
$criteria = $criteria_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$criteria_stmt->close();

// Fetch Developmental Plan
$dev_plan_stmt = $conn->prepare("SELECT * FROM evaluation_dev_plans WHERE evaluation_id = ? ORDER BY sort_order");
$dev_plan_stmt->bind_param('i', $evaluation_id);
$dev_plan_stmt->execute();
$existing_dev_plans = $dev_plan_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$dev_plan_stmt->close();

$kra_w = (float)($evaluation['kra_weight'] ?? 80);
$beh_w = (float)($evaluation['behavior_weight'] ?? 20);
$shared_beh = $evaluation['shared_behavior_score'] !== null ? (float)$evaluation['shared_behavior_score'] : (float)$evaluation['behavior_average'];
$beh_val = (float)$evaluation['behavior_average'];

$self_kra_subtotal = 0.0;
$adj_kra_subtotal = 0.0;
$kra_total_weight = 0.0;

$self_beh_total = 0.0;
$adj_beh_total = 0.0;
$beh_count = 0;

foreach ($criteria as $criterion) {
    $self_score = (float)$criterion['score_value'];
    $adj_score = $criterion['supervisor_override_score'] !== null ? (float)$criterion['supervisor_override_score'] : (float)$criterion['score_value'];
    $weight = (float)$criterion['weight'];

    if ($criterion['section'] === 'KRA') {
        $kra_total_weight += $weight;
        $self_kra_subtotal += ($weight / 100.0) * $self_score;
        $adj_kra_subtotal += ($weight / 100.0) * $adj_score;
    } else {
        $beh_count++;
        $self_beh_total += $self_score;
        $adj_beh_total += $adj_score;
    }
}
$self_kra_subtotal = round($self_kra_subtotal, 2);
$adj_kra_subtotal = round($adj_kra_subtotal, 2);
$self_beh_avg = $beh_count > 0 ? round($self_beh_total / $beh_count, 2) : 0.0;
$adj_beh_avg = $beh_count > 0 ? round($adj_beh_total / $beh_count, 2) : 0.0;

$est_final_score = calculateEvalTotal($adj_kra_subtotal, $shared_beh, $kra_w, $beh_w);
$est_perf_level = getPerformanceLevel($est_final_score);
?>
<main class="evaluation-packages container-fluid py-4">
    <section class="package-hero">
        <a class="history-back-link" href="<?php echo BASE_URL; ?>/employee/team-evaluation-packages.php">
            <i class="fas fa-arrow-left"></i> Back to evaluation package
        </a>
        <p class="mb-1 text-uppercase fw-bold" style="letter-spacing:1px; color:var(--rp-primary-gold-light); font-size:0.85rem;">
            <?php echo e($review_step['step_label']); ?>
        </p>
        <h1 class="h3 mb-2 fw-bold">Review &amp; Adjust: <?php echo e($evaluation['first_name'] . ' ' . $evaluation['last_name']); ?></h1>
        <p class="mb-0">
            <strong><?php echo e($evaluation['job_title']); ?></strong> &bull; Template: <?php echo e($evaluation['template_name']); ?> &bull; Period: <?php echo e($evaluation['evaluation_period_start']); ?> to <?php echo e($evaluation['evaluation_period_end']); ?>
        </p>
    </section>

    <!-- Score Summary Stats (F1: Computed Final Score Badge) -->
    <section class="package-card" aria-label="Score Summary">
        <div class="package-card__body">
            <div class="row g-3">
                <div class="col-md-6 col-sm-6">
                    <div class="package-stat h-100 d-flex flex-column justify-content-between" style="background: #F4FBF7; border: 2px solid #86EFAC; border-radius: 12px; padding: 1.1rem 1.25rem;">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                            <strong class="tabular-nums text-success m-0" id="stat-shared-behavior" style="font-size: 1.6rem; line-height: 1;"><?php echo number_format((float)$shared_beh, 2); ?></strong>
                            <span class="badge bg-success-subtle text-success border border-success px-2 py-1 small">Department Shared</span>
                        </div>
                        <div class="text-muted fw-semibold" style="font-size: 0.85rem;">Shared Department Behavior Result</div>
                    </div>
                </div>
                <div class="col-md-6 col-sm-6">
                    <div class="package-stat h-100 d-flex flex-column justify-content-between" style="background: #FAF8F0; border: 2px solid var(--rp-primary-gold); border-radius: 12px; padding: 1.1rem 1.25rem;">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                            <strong class="tabular-nums text-primary m-0" id="stat-est-total-score" style="font-size: 1.6rem; line-height: 1;"><?php echo number_format($est_final_score, 2); ?></strong>
                            <span class="badge bg-success px-2 py-1 small" id="stat-perf-level"><?php echo e($est_perf_level); ?></span>
                        </div>
                        <div class="text-muted fw-semibold" style="font-size: 0.85rem;">Estimated Current / Final Score</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <form method="post" class="package-card" id="reviewForm">
        <div class="package-card__body">
            <input type="hidden" name="package_id" value="<?php echo $package_id; ?>">
            <input type="hidden" name="evaluation_id" value="<?php echo $evaluation_id; ?>">
            <?php echo csrfField(); ?>

            <div class="alert alert-info border border-info d-flex align-items-center gap-3 p-3 mb-4 rounded-3 shadow-sm">
                <i class="fas fa-edit fa-2x text-info"></i>
                <div class="small">
                    <strong class="d-block text-dark mb-1" style="font-size:1rem;">How to Adjust Ratings:</strong>
                    Enter your adjusted rating score directly in the input box under <strong>Evaluator Adjusted Rating</strong> (supports decimal values between 1.00 and 4.00). Subtotals and Estimated Final Score recalculate automatically in real time.
                </div>
            </div>

            <?php foreach (['KRA' => 'Key Result Areas (KRA)', 'Behavior' => 'Core Behaviors & Values (Shared Score Component)'] as $section => $label): ?>
                <h2 class="h5 fw-bold mt-4 mb-3" style="color:var(--rp-forest-green); border-bottom:2px solid #E2E8F0; padding-bottom:0.5rem;">
                    <i class="fas <?php echo $section === 'KRA' ? 'fa-chart-pie' : 'fa-users'; ?> me-2"></i><?php echo $label; ?>
                </h2>
                <div class="table-responsive mb-4">
                    <table class="package-table table align-middle">
                        <thead>
                            <tr>
                                <th style="width: 42%;">Criterion</th>
                                <?php if ($section === 'KRA'): ?>
                                    <th class="text-end" style="width: 12%;">Weight</th>
                                <?php endif; ?>
                                <th class="text-end" style="width: 18%;">Employee Self-Rating</th>
                                <th style="width: 28%;">Evaluator Adjusted Rating</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($criteria as $criterion): ?>
                                <?php
                                if ($criterion['section'] !== $section) continue;
                                $effective = $criterion['supervisor_override_score'] !== null ? (float)$criterion['supervisor_override_score'] : (float)$criterion['score_value'];
                                $is_modified = ($criterion['supervisor_override_score'] !== null && abs((float)$criterion['supervisor_override_score'] - (float)$criterion['score_value']) > 0.001);
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark" style="font-size:1.05rem;"><?php echo e($criterion['criterion_name']); ?></div>
                                        <?php if (!empty($criterion['description'])): ?>
                                            <div class="small text-muted mt-1"><?php echo e($criterion['description']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($is_modified): ?>
                                            <span class="audit-chip audit-chip--adjusted mt-1">
                                                <i class="fas fa-edit me-1"></i>Adjusted from <?php echo number_format((float)$criterion['score_value'], 2); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($section === 'KRA'): ?>
                                        <td class="text-end tabular-nums fw-bold text-muted"><?php echo number_format((float)$criterion['weight'], 2); ?>%</td>
                                    <?php endif; ?>
                                    <td class="text-end tabular-nums fw-semibold" style="font-size:1.1rem;">
                                        <?php echo number_format((float)$criterion['score_value'], 2); ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <input class="form-control fw-bold text-center eval-score-input"
                                                   id="rating_<?php echo (int)$criterion['score_id']; ?>"
                                                   name="rating[<?php echo (int)$criterion['score_id']; ?>]"
                                                   type="number"
                                                   min="1.00"
                                                   max="4.00"
                                                   step="0.01"
                                                   data-section="<?php echo e($section); ?>"
                                                   data-weight="<?php echo (float)$criterion['weight']; ?>"
                                                   value="<?php echo e(number_format($effective, 2, '.', '')); ?>"
                                                   aria-label="Rating for <?php echo e($criterion['criterion_name']); ?>"
                                                   style="width: 110px !important; height: 44px !important; font-size: 1.25rem !important; font-weight: 700 !important; color: #0f172a !important; background-color: #ffffff !important; border: 2px solid #0f5132 !important; border-radius: 8px !important; display: inline-block !important; opacity: 1 !important; visibility: visible !important;">
                                            <span class="fw-bold text-secondary" style="font-size: 1rem;">/ 4.00</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Section Total Score Lookup Below Table -->
                <?php if ($section === 'KRA'): ?>
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 px-3 py-2 rounded-3 border mb-4 shadow-sm" style="background:#F8FAFC; border-color:#E2E8F0 !important;">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-chart-pie text-success fa-lg"></i>
                            <span class="fw-bold text-dark" style="font-size: 1.05rem;">Key Result Areas (KRA) Total:</span>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <span class="text-muted small fw-semibold">Self-Rating: <strong class="text-secondary tabular-nums"><?php echo number_format($self_kra_subtotal, 2); ?></strong></span>
                            <div class="d-flex align-items-center gap-1 bg-white px-3 py-1 rounded-2 border" style="border-color:#0f5132 !important;">
                                <span class="text-muted small me-1">Total Score:</span>
                                <strong class="text-success tabular-nums fs-5" id="lookup-kra-subtotal"><?php echo number_format($adj_kra_subtotal, 2); ?></strong>
                                <span class="text-muted small fw-bold">/ 4.00</span>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 px-3 py-2 rounded-3 border mb-4 shadow-sm" style="background:#F8FAFC; border-color:#E2E8F0 !important;">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-users text-primary fa-lg"></i>
                            <span class="fw-bold text-dark" style="font-size: 1.05rem;">Core Behaviors &amp; Values Total:</span>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <span class="text-muted small fw-semibold">Self-Rating: <strong class="text-secondary tabular-nums"><?php echo number_format($self_beh_avg, 2); ?></strong></span>
                            <div class="d-flex align-items-center gap-1 bg-white px-3 py-1 rounded-2 border" style="border-color:#0f5132 !important;">
                                <span class="text-muted small me-1">Total Score:</span>
                                <strong class="text-success tabular-nums fs-5" id="lookup-beh-indiv"><?php echo number_format($adj_beh_avg, 2); ?></strong>
                                <span class="text-muted small fw-bold">/ 4.00</span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>

            <!-- F6: Developmental Plan Section -->
            <div class="mt-5 pt-3 border-top">
                <h2 class="h5 fw-bold mb-3" style="color:var(--rp-forest-green);">
                    <i class="fas fa-seedling me-2"></i>Developmental Plan &amp; Recommendations
                </h2>
                <p class="text-muted small mb-3">
                    Specify growth areas, required support, and target completion timelines for this team member during consolidation.
                </p>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="devPlanTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 35%;">Area for Improvement / Development</th>
                                <th style="width: 35%;">Support Needed / Action Plan</th>
                                <th style="width: 20%;">Target Time Frame</th>
                                <th style="width: 10%;" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="devPlanBody">
                            <?php
                            $rows = !empty($existing_dev_plans) ? $existing_dev_plans : [
                                ['improvement_area' => '', 'support_needed' => '', 'time_frame' => '']
                            ];
                            foreach ($rows as $idx => $dp):
                            ?>
                                <tr>
                                    <td>
                                        <input type="text" class="form-control" name="plans[<?php echo $idx; ?>][improvement_area]" value="<?php echo e($dp['improvement_area'] ?? ''); ?>" placeholder="e.g. Advanced Excel / Financial Analysis">
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" name="plans[<?php echo $idx; ?>][support_needed]" value="<?php echo e($dp['support_needed'] ?? ''); ?>" placeholder="e.g. External Training / Peer Mentoring">
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" name="plans[<?php echo $idx; ?>][time_frame]" value="<?php echo e($dp['time_frame'] ?? ''); ?>" placeholder="e.g. Q3 2026 / 3 Months">
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-dev-row" title="Remove Row">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-sm btn-outline-success mt-2" id="btnAddDevRow">
                    <i class="fas fa-plus me-1"></i>Add Development Item
                </button>
            </div>

            <div class="d-flex flex-wrap gap-3 mt-4 pt-3 border-top align-items-center">
                <button class="btn-action-primary btn" type="submit">
                    <i class="fas fa-save me-1"></i>Save Adjustments &amp; Developmental Plan
                </button>
                <a class="btn btn-outline-secondary px-4 py-2 fw-semibold" href="<?php echo BASE_URL; ?>/employee/team-evaluation-packages.php" style="min-height:46px; border-radius:8px;">
                    Back to Package Overview
                </a>
            </div>
        </div>
    </form>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const kraWeightPct = <?php echo $kra_w; ?>;
    const behWeightPct = <?php echo $beh_w; ?>;
    const sharedBehVal = <?php echo $shared_beh; ?>;

    const ratingInputs = document.querySelectorAll('.eval-score-input');
    const statEstTotal = document.getElementById('stat-est-total-score');
    const statPerfLevel = document.getElementById('stat-perf-level');

    function getPerfLevel(score) {
        if (score >= 3.60) return 'Outstanding';
        if (score >= 3.00) return 'Very Satisfactory';
        if (score >= 2.50) return 'Satisfactory';
        if (score >= 2.00) return 'Fair';
        return 'Unsatisfactory';
    }

    function recalcLiveScore() {
        let kraSubtotal = 0;
        let behTotal = 0;
        let behCount = 0;

        ratingInputs.forEach(function (inp) {
            const val = parseFloat(inp.value) || 0;
            if (inp.dataset.section === 'KRA') {
                const weight = parseFloat(inp.dataset.weight) || 0;
                kraSubtotal += (weight / 100) * val;
            } else if (inp.dataset.section === 'Behavior') {
                behTotal += val;
                behCount++;
            }
        });

        const behAverage = behCount > 0 ? (behTotal / behCount) : 0;
        const kraWeighted = kraSubtotal * (kraWeightPct / 100);
        const behWeighted = sharedBehVal * (behWeightPct / 100);
        const estTotal = kraWeighted + behWeighted;
        const estTotalRounded = estTotal.toFixed(2);
        const level = getPerfLevel(estTotal);

        // Update Top Score Stat Cards
        if (statEstTotal) statEstTotal.textContent = estTotalRounded;
        if (statPerfLevel) statPerfLevel.textContent = level;

        // Update KRA Section Lookup Total
        const lookupKraSub = document.getElementById('lookup-kra-subtotal');
        if (lookupKraSub) lookupKraSub.textContent = kraSubtotal.toFixed(2);

        // Update Behavior Section Lookup Total
        const lookupBehIndiv = document.getElementById('lookup-beh-indiv');
        if (lookupBehIndiv) lookupBehIndiv.textContent = behAverage.toFixed(2);
    }

    ratingInputs.forEach(function (inp) {
        inp.addEventListener('input', recalcLiveScore);
        inp.addEventListener('change', recalcLiveScore);
    });


    // Dev Plan Dynamic Table Rows
    const btnAddDevRow = document.getElementById('btnAddDevRow');
    const devPlanBody = document.getElementById('devPlanBody');

    if (btnAddDevRow && devPlanBody) {
        btnAddDevRow.addEventListener('click', function () {
            const rowCount = devPlanBody.children.length;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><input type="text" class="form-control" name="plans[${rowCount}][improvement_area]" placeholder="e.g. Area for Improvement"></td>
                <td><input type="text" class="form-control" name="plans[${rowCount}][support_needed]" placeholder="e.g. Action Plan / Support"></td>
                <td><input type="text" class="form-control" name="plans[${rowCount}][time_frame]" placeholder="e.g. Target Date"></td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-dev-row" title="Remove Row">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            `;
            devPlanBody.appendChild(tr);
        });

        devPlanBody.addEventListener('click', function (e) {
            if (e.target.closest('.btn-remove-dev-row')) {
                const tr = e.target.closest('tr');
                if (devPlanBody.children.length > 1) {
                    tr.remove();
                } else {
                    tr.querySelectorAll('input').forEach(i => i.value = '');
                }
            }
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
