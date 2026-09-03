<?php
$page_title = 'Evaluation History & Audit Trail';
require_once '../includes/session-check.php';
require_once '../includes/functions.php';

ensureEvaluationWorkflowSchema($conn);
ensureOrganizationEvaluationPackageSchema($conn);
$user_id = (int) ($_SESSION['user_id'] ?? 0);
$employee_stmt = $conn->prepare('SELECT employee_id FROM users WHERE user_id = ? LIMIT 1');
$employee_stmt->bind_param('i', $user_id);
$employee_stmt->execute();
$employee = $employee_stmt->get_result()->fetch_assoc();
$employee_stmt->close();
$employee_id = (int) ($employee['employee_id'] ?? 0);

$evaluations = [];
if ($employee_id > 0) {
    $history_stmt = $conn->prepare("SELECT ev.evaluation_id, ev.evaluation_type, ev.evaluation_period_start, ev.evaluation_period_end,
            ev.kra_subtotal, ev.behavior_average, ev.total_score, ev.performance_level, ev.status, ev.submitted_date,
            et.template_name, ep.package_id, ep.status AS package_status, ep.shared_behavior_score,
            EXISTS(SELECT 1 FROM evaluation_scores es WHERE es.evaluation_id = ev.evaluation_id AND (es.supervisor_override_score IS NOT NULL OR es.dept_manager_override_score IS NOT NULL OR es.manager_override_score IS NOT NULL)) AS has_adjustments
        FROM evaluations ev
        JOIN evaluation_templates et ON et.template_id = ev.template_id
        LEFT JOIN evaluation_package_members pm ON pm.evaluation_id = ev.evaluation_id
        LEFT JOIN evaluation_packages ep ON ep.package_id = pm.package_id
        WHERE ev.employee_id = ? AND ev.deleted_at IS NULL
        ORDER BY COALESCE(ev.submitted_date, ev.created_at) DESC, ev.evaluation_id DESC");
    $history_stmt->bind_param('i', $employee_id);
    $history_stmt->execute();
    $evaluations = $history_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $history_stmt->close();
}

require_once '../includes/header.php';
?>
<style>
    /* ── Evaluation History Card-List Revamp ───────────────────────────── */
    .eh-list { display: flex; flex-direction: column; gap: 10px; }

    .eh-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 18px 22px;
        display: flex;
        align-items: center;
        gap: 18px;
        transition: box-shadow .2s, border-color .2s, transform .15s;
        position: relative;
        overflow: hidden;
    }
    .eh-card::before {
        content: '';
        position: absolute;
        left: 0; top: 0; bottom: 0;
        width: 4px;
        background: linear-gradient(180deg, #0a3a2a, #1a6648);
        border-radius: 14px 0 0 14px;
        opacity: 0;
        transition: opacity .2s;
    }
    .eh-card:hover { box-shadow: 0 6px 24px rgba(0,0,0,.10); border-color: #c6d4cc; transform: translateY(-1px); }
    .eh-card:hover::before { opacity: 1; }
    .eh-card__icon {
        width: 46px; height: 46px; flex-shrink: 0;
        background: linear-gradient(135deg, #0a3a2a 0%, #1a6648 100%);
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: 1.1rem;
    }
    .eh-card__main { flex: 1; min-width: 0; }
    .eh-card__title { font-size: 1.05rem; font-weight: 700; color: #1a2233; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .eh-card__meta { font-size: .8rem; color: #64748b; margin-top: 3px; display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
    .eh-card__badges { margin-top: 8px; display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
    .eh-adj-chip {
        display: inline-flex; align-items: center; gap: 4px;
        background: #fff8e1; border: 1px solid #ffd54f; color: #795000;
        border-radius: 20px; font-size: .68rem; font-weight: 700;
        padding: 2px 8px; white-space: nowrap;
    }
    .eh-card__scores { display: flex; gap: 8px; flex-wrap: nowrap; align-items: stretch; flex-shrink: 0; }
    .eh-score-pill {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        background: #f8fafc; border-radius: 10px;
        padding: 8px 14px; min-width: 68px; text-align: center;
        border: 1px solid #e2e8f0;
    }
    .eh-score-pill__label { font-size: .63rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #94a3b8; line-height: 1; }
    .eh-score-pill__value { font-size: 1.1rem; font-weight: 800; color: #1a2233; font-variant-numeric: tabular-nums; line-height: 1.3; margin-top: 2px; }
    .eh-score-pill--total { background: linear-gradient(135deg, #0a3a2a, #1a6648); border-color: transparent; }
    .eh-score-pill--total .eh-score-pill__label { color: rgba(255,255,255,.65); }
    .eh-score-pill--total .eh-score-pill__value { color: #fff; font-size: 1.2rem; }
    .eh-score-pill--pending .eh-score-pill__value { color: #94a3b8; font-size: .85rem; }
    .eh-card__cta { flex-shrink: 0; }
    .eh-audit-btn {
        display: inline-flex; align-items: center; gap: 7px;
        background: linear-gradient(135deg, #0a3a2a, #1a6648);
        color: #fff; border: none; border-radius: 9px;
        padding: 9px 18px; font-size: .82rem; font-weight: 700;
        text-decoration: none; white-space: nowrap;
        transition: opacity .15s, transform .15s, box-shadow .15s;
        box-shadow: 0 2px 8px rgba(10,58,42,.25);
    }
    .eh-audit-btn:hover { opacity: .9; color: #fff; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(10,58,42,.35); text-decoration: none; }
    @media (max-width: 860px) {
        .eh-card { flex-wrap: wrap; }
        .eh-card__scores { width: 100%; justify-content: flex-start; }
        .eh-card__cta { width: 100%; }
        .eh-audit-btn { width: 100%; justify-content: center; }
    }
</style>
<main class="evaluation-packages container-fluid py-4">
    <section class="package-hero">
        <p class="mb-1 text-uppercase fw-bold" style="letter-spacing:1px; color:var(--rp-primary-gold-light); font-size:0.85rem;">
            Performance Audit Trail &amp; Revision Log
        </p>
        <h1 class="h3 mb-2 fw-bold">My Evaluation History &amp; Audit Trail</h1>
        <p class="mb-0">
            Audit and review your past and active evaluation cycles. Transparently inspect your original submitted self-ratings alongside supervisor adjustments, reviewer remarks, and sequential workflow progress.
        </p>
    </section>

    <?php if (!$evaluations): ?>
        <section class="package-empty">
            <i class="fas fa-history fa-3x text-muted mb-3" style="opacity:0.4;"></i>
            <h2 class="h5 fw-bold">No evaluation history found</h2>
            <p class="mb-0 text-muted">Your submitted evaluations and audit logs will appear here.</p>
        </section>
    <?php else: ?>
        <section class="package-card" role="region" aria-label="Evaluation History Audit List">
            <header class="package-card__header">
                <h2 class="h5 mb-0 fw-bold">
                    <i class="fas fa-list-alt me-2"></i>Submitted Evaluation Cycles
                    <span class="badge bg-secondary ms-2" style="font-size:.75rem;"><?php echo count($evaluations); ?></span>
                </h2>
                <div class="small text-muted">Original self-ratings and officer adjustments are tracked for every cycle.</div>
            </header>
            <div class="package-card__body">
                <div class="eh-list">
                    <?php foreach ($evaluations as $ev): ?>
                    <?php
                        $has_total = $ev['total_score'] !== null;
                        $level     = $ev['performance_level'] ?: null;
                        $submitted = !empty($ev['submitted_date']) ? date('M d, Y', strtotime($ev['submitted_date'])) : null;
                    ?>
                    <div class="eh-card">
                        <div class="eh-card__icon"><i class="fas fa-file-alt"></i></div>
                        <div class="eh-card__main">
                            <div class="eh-card__title"><?php echo e($ev['template_name']); ?></div>
                            <div class="eh-card__meta">
                                <span><i class="fas fa-tag me-1"></i><?php echo e($ev['evaluation_type']); ?></span>
                                <span><i class="fas fa-calendar-alt me-1"></i><?php echo e($ev['evaluation_period_start']); ?> &ndash; <?php echo e($ev['evaluation_period_end']); ?></span>
                                <?php if ($submitted): ?>
                                    <span><i class="fas fa-paper-plane me-1"></i>Submitted <?php echo $submitted; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="eh-card__badges">
                                <?php if (!empty($ev['package_id'])): ?>
                                    <?php echo renderOrganizationPipelineBadge($conn, (int)$ev['package_id']); ?>
                                <?php else: ?>
                                    <span class="badge <?php echo $ev['status'] === 'Approved' ? 'bg-success' : 'bg-secondary'; ?>"><?php echo e($ev['status']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($ev['has_adjustments'])): ?>
                                    <span class="eh-adj-chip"><i class="fas fa-pen-fancy"></i>Score Adjustments Recorded</span>
                                <?php endif; ?>
                                <?php if ($level): ?>
                                    <span class="badge bg-light text-dark border"><i class="fas fa-star me-1 text-warning"></i><?php echo e($level); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="eh-card__scores">
                            <div class="eh-score-pill">
                                <span class="eh-score-pill__label">KRA</span>
                                <span class="eh-score-pill__value"><?php echo $ev['kra_subtotal'] !== null ? number_format((float)$ev['kra_subtotal'], 2) : '&mdash;'; ?></span>
                            </div>
                            <div class="eh-score-pill">
                                <span class="eh-score-pill__label">Behavior</span>
                                <span class="eh-score-pill__value"><?php echo $ev['behavior_average'] !== null ? number_format((float)$ev['behavior_average'], 2) : '&mdash;'; ?></span>
                            </div>
                            <div class="eh-score-pill <?php echo $has_total ? 'eh-score-pill--total' : 'eh-score-pill--pending'; ?>">
                                <span class="eh-score-pill__label">Total</span>
                                <span class="eh-score-pill__value"><?php echo $has_total ? number_format((float)$ev['total_score'], 2) : 'Pending'; ?></span>
                            </div>
                        </div>
                        <div class="eh-card__cta">
                            <a class="eh-audit-btn"
                               href="<?php echo BASE_URL; ?>/employee/evaluation-history-view.php?evaluation_id=<?php echo (int)$ev['evaluation_id']; ?>"
                               title="Inspect original vs adjusted ratings and audit trail">
                                <i class="fas fa-search-plus"></i>Audit Details
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
</main>
<?php require_once '../includes/footer.php'; ?>

