<?php
$page_title = 'View Template';
require_once '../includes/session-check.php';
checkRole(['HR Staff']);
require_once '../includes/functions.php';

// Validate template ID
$tid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($tid <= 0) {
    redirectWith(BASE_URL . '/staff/templates.php', 'danger', 'Invalid template ID.');
}

// Fetch template
$stmt = $conn->prepare("SELECT * FROM evaluation_templates WHERE template_id = ? AND status = 'Active' AND deleted_at IS NULL");
$stmt->bind_param("i", $tid);
$stmt->execute();
$template = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$template) {
    redirectWith(BASE_URL . '/staff/templates.php', 'danger', 'Template not found or is no longer active.');
}

// Fetch criteria
$criteria_stmt = $conn->prepare("SELECT * FROM evaluation_criteria WHERE template_id = ? ORDER BY sort_order");
$criteria_stmt->bind_param("i", $tid);
$criteria_stmt->execute();
$criteria = $criteria_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$criteria_stmt->close();
$kra_count = 0;
$behavior_count = 0;
$kra_weight = 0;
$behavior_weight = 0;
foreach ($criteria as $criterion) {
    if (($criterion['section'] ?? '') === 'KRA') {
        $kra_count++;
        $kra_weight += (float)$criterion['weight'];
    } else {
        $behavior_count++;
        $behavior_weight += (float)$criterion['weight'];
    }
}
$total_weight = $kra_weight + $behavior_weight;

require_once '../includes/header.php';
?>

<div class="template-view-page">
    <div class="template-view-hero mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div class="min-w-0">
                <div class="template-eyebrow"><i class="fas fa-file-signature me-2"></i>HR Staff · Template Library</div>
                <h1 class="template-title"><?php echo e($template['template_name']); ?></h1>
                <p class="template-description mb-0"><?php echo e($template['description'] ?: 'No description provided for this evaluation template.'); ?></p>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="template-readonly"><i class="fas fa-lock me-1"></i>Read-only</span>
                <a href="<?php echo BASE_URL; ?>/staff/templates.php" class="btn btn-light btn-sm template-back-link">
                    <i class="fas fa-arrow-left me-2"></i>Template Library
                </a>
            </div>
        </div>
        <div class="template-meta-row">
            <span><i class="fas fa-building me-1"></i><?php echo e($template['target_department'] ?: 'All Departments'); ?></span>
            <?php if (!empty($template['evaluation_type'])): ?><span><i class="fas fa-calendar-check me-1"></i><?php echo e($template['evaluation_type']); ?></span><?php endif; ?>
            <?php if (!empty($template['form_code'])): ?><span><i class="fas fa-hashtag me-1"></i><?php echo e($template['form_code']); ?></span><?php endif; ?>
        </div>
    </div>

    <div class="template-stat-grid mb-4">
        <div class="template-stat"><span class="template-stat-icon blue"><i class="fas fa-list-check"></i></span><div><strong><?php echo count($criteria); ?></strong><small>Total criteria</small></div></div>
        <div class="template-stat"><span class="template-stat-icon green"><i class="fas fa-bullseye"></i></span><div><strong><?php echo $kra_count; ?></strong><small>KRA items</small></div></div>
        <div class="template-stat"><span class="template-stat-icon gold"><i class="fas fa-heart"></i></span><div><strong><?php echo $behavior_count; ?></strong><small>Behavior items</small></div></div>
        <div class="template-stat"><span class="template-stat-icon <?php echo abs($total_weight - 100) < 0.01 ? 'green' : 'red'; ?>"><i class="fas fa-scale-balanced"></i></span><div><strong><?php echo number_format($total_weight, 2); ?>%</strong><small>Configured weight</small></div></div>
    </div>

<div class="row">
    <!-- Template Information -->
    <div class="col-lg-4 mb-4">
        <div class="content-card h-100 template-panel">
            <div class="card-header template-panel-header">
                <h6 class="mb-0 fw-bold"><i class="fas fa-circle-info me-2 text-primary"></i>Template Information</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="small text-muted d-block">Template Name</label>
                    <span class="fw-bold text-dark"><?php echo e($template['template_name']); ?></span>
                </div>
                <div class="mb-3">
                    <label class="small text-muted d-block">Target Department</label>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2"><?php echo e($template['target_department'] ?: 'All Departments'); ?></span>
                </div>
                <div class="mb-3">
                    <label class="small text-muted d-block">Description</label>
                    <p class="small text-muted mb-0"><?php echo nl2br(e($template['description'] ?: 'No description provided.')); ?></p>
                </div>
                <hr class="my-3 opacity-10">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <label class="small text-muted d-block">Total Weight</label>
                        <?php 
                        $w_class = abs($total_weight - 100) < 0.01 ? 'text-success' : 'text-warning';
                        ?>
                        <span class="fw-bold <?php echo $w_class; ?>"><?php echo number_format($total_weight, 2); ?>%</span>
                    </div>
                    <div>
                        <label class="small text-muted d-block text-end">Criteria Count</label>
                        <span class="fw-bold text-dark d-block text-end"><?php echo count($criteria); ?></span>
                    </div>
                </div>

                <?php if (abs($total_weight - 100) >= 0.01): ?>
                    <div class="alert alert-warning small mt-3 mb-0 py-2">
                        <i class="fas fa-exclamation-triangle me-1"></i>Total weight does not equal 100%. Contact your supervisor or manager.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Scoring Guide -->
        <div class="content-card mt-4 template-panel">
            <div class="card-header template-panel-header">
                <h6 class="mb-0 fw-bold"><i class="fas fa-gauge-high me-2 text-info"></i>Scoring Guide</h6>
            </div>
            <div class="card-body">
                <div class="small">
                    <div class="d-flex justify-content-between align-items-center mb-2 py-1 px-2 rounded" style="background:#e8f5e9;">
                        <span class="fw-semibold text-success">Excellent</span>
                        <span class="text-muted">90 – 100%</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2 py-1 px-2 rounded" style="background:#e0f7fa;">
                        <span class="fw-semibold text-info">Above Average</span>
                        <span class="text-muted">80 – 89%</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2 py-1 px-2 rounded" style="background:#fff9c4;">
                        <span class="fw-semibold text-warning">Average</span>
                        <span class="text-muted">70 – 79%</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-1 px-2 rounded" style="background:#ffebee;">
                        <span class="fw-semibold text-danger">Needs Improvement</span>
                        <span class="text-muted">Below 70%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Criteria List -->
    <div class="col-lg-8 mb-4">
        <div class="content-card h-100 template-panel">
            <div class="card-header d-flex justify-content-between align-items-center template-panel-header">
                <h6 class="mb-0 fw-bold"><i class="fas fa-list-check me-2 text-primary"></i>Evaluation Criteria</h6>
                <span class="text-muted small">Configured scoring structure</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($criteria)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-clipboard fa-2x mb-2 opacity-25 d-block"></i>
                        <p>No criteria defined for this template.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th style="width: 115px;">Section</th>
                                    <th>Criterion Name</th>
                                    <th class="text-center">Weight</th>
                                    <th>Scoring Method</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($criteria as $i => $c): ?>
                                    <tr>
                                        <td><span class="text-muted small fw-bold"><?php echo $i + 1; ?></span></td>
                                        <td>
                                            <span class="section-pill <?php echo ($c['section'] ?? '') === 'KRA' ? 'kra' : 'behavior'; ?>">
                                                <i class="fas <?php echo ($c['section'] ?? '') === 'KRA' ? 'fa-bullseye' : 'fa-heart'; ?> me-1"></i><?php echo e($c['section'] ?: 'Behavior'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark small"><?php echo e($c['criterion_name']); ?></div>
                                            <?php if(!empty($c['description'])): ?>
                                                <div class="text-muted" style="font-size: 0.75rem; line-height: 1.2;"><?php echo e($c['description']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2"><?php echo (float)$c['weight']; ?>%</span>
                                        </td>
                                        <td>
                                            <div class="small">
                                                <?php
                                                $method = $c['scoring_method'];
                                                $icon = 'fa-star';
                                                $label = 'Scale 1-5';
                                                if ($method === 'Scale_1_10') { $icon = 'fa-list-ol'; $label = 'Scale 1-10'; }
                                                elseif ($method === 'Percentage') { $icon = 'fa-percent'; $label = 'Percentage (0-100%)'; }
                                                ?>
                                                <i class="fas <?php echo $icon; ?> me-1 text-muted opacity-50"></i> <?php echo $label; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-light">
                                <tr>
                                    <td colspan="3" class="text-end fw-bold small">Total Weight</td>
                                    <td class="text-center">
                                        <span class="badge <?php echo abs($total_weight - 100) < 0.01 ? 'bg-success' : 'bg-warning text-dark'; ?> px-2"><?php echo number_format($total_weight, 2); ?>%</span>
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</div>

<style>
.template-view-page { max-width: 1440px; margin: 0 auto; }
.template-view-hero { background: linear-gradient(135deg, #183006 0%, #294306 58%, #456b17 100%); border-radius: 16px; color: #fff; padding: 28px 30px 24px; box-shadow: 0 12px 26px rgba(24,48,6,.14); }
.template-eyebrow { color: #dbe9c3; font-size: .72rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
.template-title { color: #fff; font-size: clamp(1.5rem, 3vw, 2.2rem); font-weight: 800; line-height: 1.15; margin: 10px 0 8px; }
.template-description { color: rgba(255,255,255,.78); max-width: 760px; font-size: .92rem; line-height: 1.55; }
.template-readonly { background: rgba(255,255,255,.13); border: 1px solid rgba(255,255,255,.22); border-radius: 999px; color: #fff; font-size: .75rem; font-weight: 700; padding: 8px 12px; white-space: nowrap; }
.template-back-link { border: 0; border-radius: 999px; color: #294306; font-weight: 700; white-space: nowrap; }
.template-meta-row { border-top: 1px solid rgba(255,255,255,.16); color: rgba(255,255,255,.78); display: flex; flex-wrap: wrap; gap: 18px; margin-top: 22px; padding-top: 14px; font-size: .78rem; }
.template-stat-grid { display: grid; gap: 14px; grid-template-columns: repeat(4, minmax(0, 1fr)); }
.template-stat { align-items: center; background: #fff; border: 1px solid #edf1ea; border-radius: 12px; display: flex; gap: 12px; min-height: 82px; padding: 14px 16px; }
.template-stat strong { color: #20301a; display: block; font-size: 1.25rem; line-height: 1; }
.template-stat small { color: #7a8774; display: block; font-size: .72rem; margin-top: 5px; }
.template-stat-icon { align-items: center; border-radius: 10px; display: inline-flex; height: 38px; justify-content: center; width: 38px; }
.template-stat-icon.blue { background: #e8f0ff; color: #2864c7; }.template-stat-icon.green { background: #e8f5e9; color: #238443; }.template-stat-icon.gold { background: #fff5d9; color: #a97800; }.template-stat-icon.red { background: #ffebee; color: #c62828; }
.template-panel { border: 1px solid #edf1ea; box-shadow: 0 5px 18px rgba(20,35,10,.05); }
.template-panel-header { background: #fff; border-bottom: 1px solid #edf1ea; padding: 16px 20px; }
.section-pill { border-radius: 999px; display: inline-block; font-size: .66rem; font-weight: 800; letter-spacing: .3px; padding: 5px 8px; text-transform: uppercase; white-space: nowrap; }
.section-pill.kra { background: #e8f5e9; color: #237a3b; }.section-pill.behavior { background: #e8f0ff; color: #2864c7; }
.bg-info-subtle { background-color: #e0f7fa; }.bg-secondary-subtle { background-color: #f5f5f5; }
.badge { border-radius: 6px; font-weight: 600; font-size: 0.7rem; }
.table th { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.5px; padding: 12px 16px; border-bottom: 2px solid #f1f1f1; }
.table td { padding: 14px 16px; border-bottom: 1px solid #f1f4ef; }
.table tfoot td { padding: 12px 16px; font-size: 0.85rem; }
@media (max-width: 767.98px) { .template-view-hero { border-radius: 12px; padding: 22px 18px; } .template-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .template-panel-header { align-items: flex-start !important; flex-direction: column; gap: 6px; } }
@media (max-width: 420px) { .template-stat { padding: 12px 10px; } .template-stat-icon { height: 32px; width: 32px; } .template-stat strong { font-size: 1.05rem; } }
</style>

<?php require_once '../includes/footer.php'; ?>
