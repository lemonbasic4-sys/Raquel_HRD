<?php
$page_title = 'View Template';
require_once '../includes/session-check.php';
checkRole(['HR Manager', 'HR Supervisor', 'HR Staff', 'Employee', 'President and CEO']);
require_once '../includes/functions.php';
if (!ensureHierarchicalEvaluationSchema($conn)) {
    http_response_code(500);
    exit('Evaluation template schema is unavailable.');
}
$viewer_context = getEvaluationTemplateCreatorContext($conn, (int)($_SESSION['user_id'] ?? 0));
if (!canViewEvaluationTemplates($viewer_context)) {
    redirectWith(BASE_URL . '/employee/dashboard.php', 'danger', 'Your account is not authorized to view evaluation templates.');
}

// Validate template ID
$tid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($tid <= 0) {
    redirectWith(BASE_URL . '/staff/templates.php', 'danger', 'Invalid template ID.');
}

// Fetch template
$stmt = $conn->prepare("SELECT et.*, u.full_name AS created_by_name, u.role AS created_by_role
    FROM evaluation_templates et
    LEFT JOIN users u ON u.user_id = et.created_by
    WHERE et.template_id = ? AND et.status IN ('Active', 'Archived') AND et.deleted_at IS NULL");
$stmt->bind_param("i", $tid);
$stmt->execute();
$template = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$template) {
    redirectWith(BASE_URL . '/staff/templates.php', 'danger', 'Template not found or is no longer active.');
}
$portal_dir = basename(dirname($_SERVER['SCRIPT_NAME']));
$template_list_url = !empty($viewer_context['is_ceo'])
    ? BASE_URL . '/employee/evaluation-templates.php'
    : (in_array($viewer_context['role'] ?? '', ['HR Manager', 'HR Supervisor'], true)
        ? BASE_URL . '/manager/templates.php'
        : (($viewer_context['role'] ?? '') === 'HR Staff'
            ? BASE_URL . '/staff/templates.php'
            : BASE_URL . '/employee/dashboard.php'));

$viewer_role = (string)($viewer_context['role'] ?? '');
$can_view_all_departments = !empty($viewer_context['is_ceo'])
    || in_array($viewer_role, ['HR Manager', 'HR Supervisor', 'HR Staff'], true);
if (!$can_view_all_departments) {
    $viewer_department_id = (int)($viewer_context['department_id'] ?? 0);
    $viewer_department_name = '';
    if ($viewer_department_id > 0) {
        $viewer_department_stmt = $conn->prepare('SELECT department_name FROM departments WHERE department_id = ? LIMIT 1');
        $viewer_department_stmt->bind_param('i', $viewer_department_id);
        $viewer_department_stmt->execute();
        $viewer_department_name = (string)($viewer_department_stmt->get_result()->fetch_assoc()['department_name'] ?? '');
        $viewer_department_stmt->close();
    }
    $template_department_id = (int)($template['target_department_id'] ?? 0);
    $template_department_name = trim((string)($template['target_department'] ?? ''));
    $department_id_matches = $template_department_id === 0 || $template_department_id === $viewer_department_id;
    $department_name_matches = $template_department_name === ''
        || strcasecmp($template_department_name, 'All Departments') === 0
        || ($viewer_department_name !== '' && strcasecmp($template_department_name, $viewer_department_name) === 0);
    if ($viewer_department_id <= 0 || !$department_id_matches || !$department_name_matches) {
        redirectWith($template_list_url, 'danger', 'This template is not available to your department.');
    }
}

// Fetch criteria
$criteria_stmt = $conn->prepare("SELECT * FROM evaluation_criteria WHERE template_id = ? ORDER BY sort_order");
$criteria_stmt->bind_param("i", $tid);
$criteria_stmt->execute();
$criteria = $criteria_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$criteria_stmt->close();
$kra_criteria = [];
$behavior_criteria = [];
foreach ($criteria as $criterion) {
    if (($criterion['section'] ?? '') === 'KRA') {
        $kra_criteria[] = $criterion;
    } else {
        $behavior_criteria[] = $criterion;
    }
}
$kra_count = count($kra_criteria);
$behavior_count = count($behavior_criteria);
$kra_master_weight = (float)($template['kra_weight'] ?? 80);
$behavior_master_weight = (float)($template['behavior_weight'] ?? 20);
$master_weight_total = $kra_master_weight + $behavior_master_weight;
$target_position_ids = getEvaluationTemplateTargetPositionIds($conn, $tid);
$target_positions = [];
if ($target_position_ids) {
    $position_id_list = implode(',', array_map('intval', $target_position_ids));
    $position_result = $conn->query("SELECT job_title FROM job_titles WHERE job_title_id IN ($position_id_list) AND is_active = 1 ORDER BY job_title");
    if ($position_result) {
        $target_positions = array_column($position_result->fetch_all(MYSQLI_ASSOC), 'job_title');
    }
}

require_once '../includes/header.php';
?>

<div class="template-view-page">
    <div class="template-view-hero mb-4">
        <div class="template-breadcrumb"><a href="<?php echo $template_list_url; ?>">Template Library</a><i class="fas fa-chevron-right"></i><span>Template details</span></div>
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-4">
            <div class="min-w-0">
                <div class="template-eyebrow"><i class="fas fa-file-signature me-2"></i>Evaluation template</div>
                <h1 class="template-title"><?php echo e($template['template_name']); ?></h1>
                <p class="template-description mb-0"><?php echo nl2br(e($template['description'] ?: 'No description has been added to this template.')); ?></p>
            </div>
            <div class="template-hero-actions">
                <span class="template-readonly"><i class="fas fa-lock me-1"></i>View only</span>
                <a href="<?php echo $template_list_url; ?>" class="btn btn-light template-back-link"><i class="fas fa-arrow-left me-2"></i>Back to templates</a>
            </div>
        </div>
        <div class="template-meta-row">
            <span><i class="fas fa-building"></i><strong>Department</strong><?php echo e($template['target_department'] ?: 'All Departments'); ?></span>
            <span><i class="fas fa-calendar-check"></i><strong>Evaluation type</strong><?php echo e($template['evaluation_type'] ?? 'Annual'); ?></span>
            <?php if (!empty($template['form_code'])): ?><span><i class="fas fa-hashtag"></i><strong>Form code</strong><?php echo e($template['form_code']); ?></span><?php endif; ?>
            <span><i class="fas fa-circle-check"></i><strong>Status</strong><?php echo e($template['status']); ?></span>
        </div>
    </div>

    <div class="template-stat-grid mb-4">
        <div class="template-stat"><span class="template-stat-icon green"><i class="fas fa-list-check"></i></span><div><strong><?php echo count($criteria); ?></strong><small>Scoring criteria</small></div></div>
        <div class="template-stat"><span class="template-stat-icon green"><i class="fas fa-bullseye"></i></span><div><strong><?php echo $kra_count; ?></strong><small>KRA criteria</small></div></div>
        <div class="template-stat"><span class="template-stat-icon gold"><i class="fas fa-heart"></i></span><div><strong><?php echo $behavior_count; ?></strong><small>Behavior criteria</small></div></div>
        <div class="template-stat"><span class="template-stat-icon <?php echo abs($master_weight_total - 100) < 0.01 ? 'green' : 'gold'; ?>"><i class="fas fa-scale-balanced"></i></span><div><strong><?php echo number_format($master_weight_total, 0); ?>%</strong><small>Weight allocation</small></div></div>
    </div>

    <div class="row g-4">
        <aside class="col-lg-4 order-2">
            <section class="content-card template-panel mb-4">
                <div class="template-panel-header"><h2><i class="fas fa-circle-info"></i>Template overview</h2></div>
                <div class="card-body">
                    <div class="detail-item"><span>Created by</span><strong><?php echo e($template['created_by_name'] ?: 'Not specified'); ?><?php if (!empty($template['created_by_role'])): ?><small><?php echo e($template['created_by_role']); ?></small><?php endif; ?></strong></div>
                    <div class="detail-item"><span>Target department</span><strong><?php echo e($template['target_department'] ?: 'All Departments'); ?></strong></div>
                    <div class="detail-item"><span>Evaluation type</span><strong><?php echo e($template['evaluation_type'] ?? 'Annual'); ?></strong></div>
                    <div class="detail-item"><span>Form code</span><strong><?php echo e($template['form_code'] ?: 'Not specified'); ?></strong></div>
                    <div class="detail-item"><span>Revision date</span><strong><?php echo !empty($template['revision_date']) ? formatDate($template['revision_date']) : 'Not specified'; ?></strong></div>
                    <div class="detail-item"><span>Effective date</span><strong><?php echo !empty($template['effective_date_form']) ? formatDate($template['effective_date_form']) : 'Not specified'; ?></strong></div>
                </div>
            </section>

            <section class="content-card template-panel mb-4">
                <div class="template-panel-header"><h2><i class="fas fa-bullseye"></i>Target positions</h2><span class="template-count"><?php echo count($target_positions); ?></span></div>
                <div class="card-body">
                    <?php if ($target_positions): ?>
                        <div class="template-position-list">
                            <?php foreach ($target_positions as $position): ?><span><i class="fas fa-briefcase"></i><?php echo e($position); ?></span><?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted small mb-0">No target positions are linked to this template.</p>
                    <?php endif; ?>
                </div>
            </section>

            <section class="content-card template-panel">
                <div class="template-panel-header"><h2><i class="fas fa-scale-balanced"></i>Scoring allocation</h2></div>
                <div class="card-body">
                    <div class="allocation-row"><div><span>KRA</span><strong><?php echo number_format($kra_master_weight, 0); ?>%</strong></div><div class="allocation-track"><span class="allocation-kra" style="width:<?php echo max(0, min(100, $kra_master_weight)); ?>%"></span></div></div>
                    <div class="allocation-row"><div><span>Behavior &amp; values</span><strong><?php echo number_format($behavior_master_weight, 0); ?>%</strong></div><div class="allocation-track"><span class="allocation-behavior" style="width:<?php echo max(0, min(100, $behavior_master_weight)); ?>%"></span></div></div>
                    <div class="allocation-total"><span>Total allocation</span><strong class="<?php echo abs($master_weight_total - 100) < 0.01 ? 'text-success' : 'text-warning'; ?>"><?php echo number_format($master_weight_total, 0); ?>%</strong></div>
                    <hr>
                    <div class="framework-detail"><i class="fas fa-code-branch"></i><div><span>Behavior framework</span><strong><?php echo e($template['behavior_framework_code'] ?: 'Not specified'); ?> · v<?php echo e($template['behavior_framework_version'] ?: '—'); ?></strong></div></div>
                </div>
            </section>
        </aside>

        <section class="col-lg-8 order-1">
            <div class="content-card template-panel">
                <div class="template-panel-header criteria-heading"><div><h2><i class="fas fa-list-check"></i>Evaluation criteria</h2><p>Switch between the KRA and behavior sections to review their criteria.</p></div><span class="template-count"><?php echo count($criteria); ?> total</span></div>
                <div class="criteria-tabs-wrap">
                    <ul class="nav nav-tabs criteria-tabs" id="templateCriteriaTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $kra_count > 0 || $behavior_count === 0 ? 'active' : ''; ?>" id="kra-tab" data-bs-toggle="tab" data-bs-target="#kra-panel" type="button" role="tab" aria-controls="kra-panel" aria-selected="<?php echo $kra_count > 0 || $behavior_count === 0 ? 'true' : 'false'; ?>">
                                <i class="fas fa-bullseye me-2"></i>Key Result Areas <span class="criteria-tab-count"><?php echo $kra_count; ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $kra_count === 0 && $behavior_count > 0 ? 'active' : ''; ?>" id="behavior-tab" data-bs-toggle="tab" data-bs-target="#behavior-panel" type="button" role="tab" aria-controls="behavior-panel" aria-selected="<?php echo $kra_count === 0 && $behavior_count > 0 ? 'true' : 'false'; ?>">
                                <i class="fas fa-heart me-2"></i>Behavior &amp; Values <span class="criteria-tab-count"><?php echo $behavior_count; ?></span>
                            </button>
                        </li>
                    </ul>
                    <div class="tab-content criteria-tab-content">
                        <div class="tab-pane fade <?php echo $kra_count > 0 || $behavior_count === 0 ? 'show active' : ''; ?>" id="kra-panel" role="tabpanel" aria-labelledby="kra-tab" tabindex="0">
                            <?php if ($kra_criteria): ?>
                                <div class="criteria-table-wrap">
                                    <table class="table criteria-table align-middle mb-0">
                                        <thead><tr><th scope="col">#</th><th scope="col">Criteria</th><th scope="col">Description</th><th scope="col">Method</th><th scope="col" class="text-end">Weight</th></tr></thead>
                                        <tbody>
                                    <?php foreach ($kra_criteria as $i => $c):
                                        $method = (string)($c['scoring_method'] ?? '');
                                        $method_labels = ['Scale_1_4' => 'Scale 1–4', 'Scale_1_5' => 'Scale 1–5', 'Scale_1_10' => 'Scale 1–10', 'Percentage' => 'Percentage'];
                                        $method_label = $method_labels[$method] ?? ($method !== '' ? str_replace('_', ' ', $method) : 'Not specified');
                                    ?>
                                        <tr>
                                            <td data-label="#"><span class="criteria-index"><?php echo str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT); ?></span></td>
                                            <td class="criteria-name" data-label="Criteria"><?php echo e($c['criterion_name']); ?></td>
                                            <td class="criteria-description" data-label="Description"><?php echo !empty($c['description']) ? nl2br(e($c['description'])) : '<span class="text-muted">—</span>'; ?></td>
                                            <td data-label="Method"><span class="criteria-method"><i class="fas fa-sliders-h"></i><?php echo e($method_label); ?></span></td>
                                            <td class="criteria-weight text-end" data-label="Weight"><?php echo number_format((float)$c['weight'], 2); ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="criteria-empty"><i class="fas fa-bullseye"></i><strong>No KRA criteria</strong><span>This template does not define any Key Result Areas.</span></div>
                            <?php endif; ?>
                        </div>
                        <div class="tab-pane fade <?php echo $kra_count === 0 && $behavior_count > 0 ? 'show active' : ''; ?>" id="behavior-panel" role="tabpanel" aria-labelledby="behavior-tab" tabindex="0">
                            <?php if ($behavior_criteria): ?>
                                <div class="criteria-table-wrap">
                                    <table class="table criteria-table align-middle mb-0">
                                        <thead><tr><th scope="col">#</th><th scope="col">Criteria</th><th scope="col">Description</th><th scope="col">Method</th><th scope="col" class="text-end">Weight</th></tr></thead>
                                        <tbody>
                                    <?php foreach ($behavior_criteria as $i => $c):
                                        $method = (string)($c['scoring_method'] ?? '');
                                        $method_labels = ['Scale_1_4' => 'Scale 1–4', 'Scale_1_5' => 'Scale 1–5', 'Scale_1_10' => 'Scale 1–10', 'Percentage' => 'Percentage'];
                                        $method_label = $method_labels[$method] ?? ($method !== '' ? str_replace('_', ' ', $method) : 'Not specified');
                                        $criterion_detail = $c['kpi_description'] ?? '';
                                        if ($criterion_detail === '') $criterion_detail = $c['description'] ?? '';
                                    ?>
                                        <tr>
                                            <td data-label="#"><span class="criteria-index"><?php echo str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT); ?></span></td>
                                            <td class="criteria-name" data-label="Criteria"><?php echo e($c['criterion_name']); ?></td>
                                            <td class="criteria-description" data-label="Description"><?php echo $criterion_detail !== '' ? nl2br(e($criterion_detail)) : '<span class="text-muted">—</span>'; ?></td>
                                            <td data-label="Method"><span class="criteria-method"><i class="fas fa-sliders-h"></i><?php echo e($method_label); ?></span></td>
                                            <td class="criteria-weight text-end" data-label="Weight">Equal</td>
                                        </tr>
                                    <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="criteria-empty"><i class="fas fa-heart"></i><strong>No behavior criteria</strong><span>This template does not define any Behavior &amp; Values criteria.</span></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<style>
.template-view-page { --template-green: #082e06; --template-green-mid: #0f6b2e; --template-gold: #bd9414; --template-ink: #1c271b; --template-muted: #687466; --template-line: #e6ebe3; max-width: 1440px; margin: 0 auto; }
.template-view-hero { background: linear-gradient(135deg, var(--hero-from) 0%, var(--hero-mid) 50%, var(--hero-to) 100%); border: 1px solid rgba(255,255,255,.12); border-left: 4px solid var(--template-gold); border-radius: 12px; color: #fff; overflow: hidden; padding: 18px 22px 16px; position: relative; box-shadow: 0 8px 20px rgba(8,46,6,.1); }
.template-view-hero::before { background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E"); content: ''; inset: 0; pointer-events: none; position: absolute; }
.template-view-hero > * { position: relative; z-index: 1; }
.template-breadcrumb { align-items: center; color: rgba(255,255,255,.68); display: flex; font-size: .72rem; gap: 9px; margin-bottom: 15px; }
.template-breadcrumb a { color: #fff; font-weight: 600; text-decoration: none; }
.template-breadcrumb a:hover { text-decoration: underline; }
.template-breadcrumb i { font-size: .62rem; }
.template-eyebrow { color: #f0d775; font-size: .68rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.template-title { color: #fff; font-size: clamp(1.35rem, 2.25vw, 1.8rem); font-weight: 750; line-height: 1.2; margin: 6px 0 7px; overflow-wrap: anywhere; }
.template-description { color: rgba(255,255,255,.8); max-width: 760px; font-size: .84rem; line-height: 1.5; }
.template-hero-actions { align-items: center; display: flex; flex-direction: row; flex-wrap: wrap; gap: 8px; }
.template-readonly { background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.24); border-radius: 999px; color: #fff; font-size: .7rem; font-weight: 700; padding: 6px 10px; white-space: nowrap; }
.template-back-link { border: 1px solid rgba(255,255,255,.25); border-radius: 7px; color: var(--template-green); font-size: .78rem; font-weight: 700; padding: 7px 10px; white-space: nowrap; }
.template-meta-row { border-top: 1px solid rgba(255,255,255,.18); color: rgba(255,255,255,.94); display: flex; flex-wrap: wrap; gap: 9px 22px; margin-top: 15px; padding-top: 12px; font-size: .77rem; }
.template-meta-row span { align-items: center; display: inline-flex; gap: 7px; }
.template-meta-row span > i { color: #e3bf51; }
.template-meta-row strong { color: rgba(255,255,255,.64); font-size: .72rem; font-weight: 500; margin-right: 2px; }
.template-stat-grid { display: grid; gap: 10px; grid-template-columns: repeat(4, minmax(0, 1fr)); }
.template-stat { align-items: center; background: #fff; border: 1px solid var(--template-line); border-radius: 10px; display: flex; gap: 10px; min-height: 68px; padding: 10px 12px; }
.template-stat strong { color: var(--template-ink); display: block; font-size: 1.08rem; line-height: 1; }
.template-stat small { color: var(--template-muted); display: block; font-size: .68rem; margin-top: 4px; }
.template-stat-icon { align-items: center; border-radius: 9px; display: inline-flex; flex: 0 0 34px; height: 34px; justify-content: center; width: 34px; }
.template-stat-icon.green { background: #e8f3e8; color: var(--template-green-mid); }.template-stat-icon.gold { background: #faf2d9; color: #8c6c0b; }
.template-panel { background: #fff; border: 1px solid var(--template-line); border-radius: 12px; box-shadow: 0 4px 16px rgba(20,35,10,.045); overflow: hidden; }
.template-panel-header { align-items: center; background: #fff; border-bottom: 1px solid var(--template-line); display: flex; justify-content: space-between; padding: 14px 16px; }
.template-panel-header h2 { align-items: center; color: var(--template-ink); display: flex; font-size: .98rem; font-weight: 700; gap: 9px; margin: 0; }
.template-panel-header h2 i { color: var(--template-green-mid); }
.template-panel-header p { color: var(--template-muted); font-size: .8rem; margin: 6px 0 0; }
.template-count { background: #edf4eb; border-radius: 999px; color: var(--template-green); font-size: .68rem; font-weight: 700; padding: 4px 9px; white-space: nowrap; }
.criteria-tabs-wrap { padding: 0 20px; }
.criteria-tabs { border-bottom: 1px solid var(--template-line); display: flex; gap: 8px; margin: 0; }
.criteria-tabs .nav-link { align-items: center; background: transparent; border: 0; border-bottom: 3px solid transparent; border-radius: 0; color: var(--template-muted); display: inline-flex; font-size: .84rem; font-weight: 650; gap: 2px; margin-bottom: -1px; padding: 15px 12px 12px; }
.criteria-tabs .nav-link:hover { border-color: transparent; color: var(--template-green-mid); }
.criteria-tabs .nav-link.active { background: transparent; border-bottom-color: var(--template-green-mid); color: var(--template-green); }
.criteria-tabs .nav-link:focus-visible { outline: 3px solid rgba(15,107,46,.25); outline-offset: 2px; }
.criteria-tabs .nav-link .fa-heart { color: var(--template-gold); }
.criteria-tab-count { background: #eef2eb; border-radius: 999px; color: var(--template-muted); font-size: .68rem; font-weight: 700; margin-left: 5px; min-width: 22px; padding: 3px 7px; text-align: center; }
.criteria-tabs .nav-link.active .criteria-tab-count { background: #e8f3e8; color: var(--template-green); }
.criteria-tab-content { min-height: 190px; }
.criteria-empty { align-items: center; color: var(--template-muted); display: flex; flex-direction: column; gap: 7px; padding: 48px 20px; text-align: center; }
.criteria-empty > i { color: #91a18c; font-size: 1.5rem; margin-bottom: 4px; }
.criteria-empty strong { color: var(--template-ink); font-size: .9rem; }
.criteria-empty span { font-size: .78rem; }
.detail-item { align-items: flex-start; border-bottom: 1px solid #f0f2ee; display: flex; justify-content: space-between; gap: 16px; padding: 11px 0; }
.detail-item:first-child { padding-top: 0; }.detail-item:last-child { border-bottom: 0; padding-bottom: 0; }
.detail-item > span, .framework-detail span { color: var(--template-muted); font-size: .78rem; }
.detail-item strong { color: var(--template-ink); font-size: .8rem; text-align: right; }
.detail-item strong small { color: var(--template-muted); display: block; font-size: .69rem; font-weight: 500; margin-top: 2px; }
.template-position-list { display: flex; flex-wrap: wrap; gap: 8px; }
.template-position-list span { align-items: center; background: #f5f8f3; border: 1px solid #e6ede2; border-radius: 7px; color: var(--template-ink); display: inline-flex; font-size: .75rem; gap: 7px; padding: 7px 9px; }
.template-position-list i { color: var(--template-green-mid); font-size: .68rem; }
.allocation-row { margin-bottom: 14px; }
.allocation-row > div:first-child, .allocation-total { align-items: center; display: flex; justify-content: space-between; }
.allocation-row > div:first-child { color: var(--template-ink); font-size: .8rem; margin-bottom: 6px; }
.allocation-row strong, .allocation-total strong { font-size: .8rem; }
.allocation-track { background: #edf0ea; border-radius: 999px; height: 8px; overflow: hidden; }
.allocation-track span { border-radius: inherit; display: block; height: 100%; }
.allocation-kra { background: var(--template-green-mid); }.allocation-behavior { background: var(--template-gold); }
.allocation-total { border-top: 1px solid var(--template-line); color: var(--template-ink); padding-top: 12px; }
.framework-detail { align-items: flex-start; display: flex; gap: 10px; }
.framework-detail > i { color: var(--template-gold); margin-top: 3px; }
.framework-detail strong { color: var(--template-ink); display: block; font-size: .78rem; margin-top: 3px; }
.criteria-heading { align-items: flex-start; }
.criteria-table-wrap { max-width: 100%; overflow-x: hidden; padding: 0 12px 8px; }
.criteria-table { --bs-table-bg: transparent; min-width: 0; table-layout: fixed; width: 100%; }
.criteria-table thead th { background: #f4f7f2; border-bottom: 1px solid #e5ebe1; color: var(--template-muted); font-size: .7rem; font-weight: 700; letter-spacing: .025em; padding: 11px 10px; white-space: nowrap; }
.criteria-table tbody td { border-color: #edf0eb; color: var(--template-ink); font-size: .78rem; padding: 10px; }
.criteria-table tbody tr:last-child td { border-bottom: 0; }
.criteria-table th:nth-child(1), .criteria-table td:nth-child(1) { width: 8%; }
.criteria-table th:nth-child(2), .criteria-table td:nth-child(2) { width: 25%; }
.criteria-table th:nth-child(3), .criteria-table td:nth-child(3) { width: 37%; }
.criteria-table th:nth-child(4), .criteria-table td:nth-child(4) { width: 17%; }
.criteria-table th:nth-child(5), .criteria-table td:nth-child(5) { width: 13%; }
.criteria-name { font-weight: 700; overflow-wrap: anywhere; }
.criteria-description { color: #596457 !important; line-height: 1.45; overflow-wrap: anywhere; }
.criteria-table .criteria-index { background: #e9f4e7; border: 0; border-radius: 999px; flex-basis: 36px; height: 30px; width: 36px; }
.criteria-table .criteria-method { color: var(--template-muted); font-size: .74rem; white-space: normal; overflow-wrap: anywhere; }
.criteria-table .criteria-method i { margin-right: 7px; }
.criteria-table .criteria-weight { color: var(--template-ink); font-size: .78rem; font-weight: 750; white-space: normal; overflow-wrap: anywhere; }
.criteria-list { padding: 0 20px; }
.criteria-item { align-items: flex-start; border-bottom: 1px solid #edf0eb; display: flex; gap: 14px; padding: 20px 0; }
.criteria-item:last-child { border-bottom: 0; }
.criteria-index { align-items: center; background: #f3f7f0; border: 1px solid #e5eddf; border-radius: 9px; color: var(--template-green); display: inline-flex; flex: 0 0 38px; font-size: .76rem; font-weight: 800; height: 38px; justify-content: center; }
.criteria-content { flex: 1; min-width: 0; }
.criteria-item-top { align-items: center; display: flex; justify-content: space-between; gap: 12px; }
.section-pill { align-items: center; border-radius: 999px; display: inline-flex; font-size: .67rem; font-weight: 700; gap: 6px; padding: 5px 9px; text-transform: uppercase; }
.section-pill.kra { background: #e9f4e7; color: #145b27; }.section-pill.behavior { background: #faf2d9; color: #80630a; }
.criteria-weight { color: var(--template-green); font-size: .82rem; font-weight: 800; text-align: right; white-space: nowrap; }
.criteria-weight small { color: var(--template-muted); display: block; font-size: .64rem; font-weight: 500; }
.criteria-content h3 { color: var(--template-ink); font-size: .96rem; font-weight: 700; margin: 11px 0 5px; }
.criteria-content p { color: #596457; font-size: .82rem; line-height: 1.55; margin: 0 0 11px; }
.criteria-method { align-items: center; color: var(--template-muted); display: flex; flex-wrap: wrap; font-size: .73rem; gap: 6px; }
.criteria-method i { color: var(--template-green-mid); }.criteria-method strong { color: var(--template-ink); font-weight: 600; }
@media (max-width: 767.98px) { .template-view-hero { border-radius: 12px; padding: 21px 18px; } .template-hero-actions { align-items: flex-start; flex-direction: row; flex-wrap: wrap; } .template-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .template-panel-header { gap: 10px; padding: 15px 16px; } .criteria-list { padding: 0 16px; } .criteria-tabs-wrap { padding: 0 12px; } .criteria-tabs .nav-link { font-size: .75rem; padding-left: 6px; padding-right: 6px; } .template-meta-row { gap: 12px 18px; } .criteria-table-wrap { padding: 0 10px 10px; } .criteria-table, .criteria-table tbody, .criteria-table tr, .criteria-table td { display: block; width: 100% !important; } .criteria-table thead { display: none; } .criteria-table tr { background: #fff; border: 1px solid #e8ede5; border-radius: 9px; margin: 10px 0; padding: 8px 12px; } .criteria-table tbody td { align-items: baseline; border: 0; display: grid; font-size: .76rem; gap: 8px; grid-template-columns: 92px minmax(0, 1fr); padding: 5px 0; text-align: left !important; } .criteria-table tbody td::before { color: var(--template-muted); content: attr(data-label); font-size: .68rem; font-weight: 700; } .criteria-table tbody td:first-child { display: none; } .criteria-table .criteria-name { font-size: .82rem; } .criteria-table .criteria-description { line-height: 1.4; } .criteria-table .criteria-weight { font-size: .76rem; } }
@media (max-width: 420px) { .template-stat { gap: 9px; padding: 12px 10px; } .template-stat-icon { flex-basis: 32px; height: 32px; width: 32px; } .template-stat strong { font-size: 1.05rem; } .criteria-item { gap: 10px; } .criteria-index { flex-basis: 32px; height: 32px; } }
@media (min-width: 1600px) { .template-view-page { max-width: none; margin: 0; } }
</style>

<?php require_once '../includes/footer.php'; ?>
