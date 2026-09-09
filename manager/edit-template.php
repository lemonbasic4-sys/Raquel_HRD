<?php
$page_title = 'Edit Evaluation Template';
require_once '../includes/session-check.php';
checkRole(['HR Manager']);
require_once '../includes/functions.php';

$template_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$template_id) {
    redirectWith(BASE_URL . '/manager/templates.php', 'danger', 'Invalid template ID.');
}

// Fetch template
$tmpl = $conn->query("SELECT * FROM evaluation_templates WHERE template_id = $template_id")->fetch_assoc();
if (!$tmpl) {
    redirectWith(BASE_URL . '/manager/templates.php', 'danger', 'Template not found.');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $template_name = trim($_POST['template_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $target_department = trim($_POST['target_department'] ?? '');
    $evaluation_type = $_POST['evaluation_type'] ?? 'Annual';
    $kra_weight = floatval($_POST['kra_weight'] ?? 80);
    $behavior_weight = floatval($_POST['behavior_weight'] ?? 20);
    $form_code = trim($_POST['form_code'] ?? '');
    $revision_date = $_POST['revision_date'] ?: null;
    $effective_date_form = $_POST['effective_date_form'] ?: null;

    $kra_names = $_POST['kra_name'] ?? [];
    $kra_descriptions = $_POST['kra_description'] ?? [];
    $kra_weights = $_POST['kra_weight_item'] ?? [];

    $beh_names = $_POST['behavior_name'] ?? [];
    $beh_kpis = $_POST['behavior_kpi'] ?? [];

    if (empty($template_name)) {
        redirectWith(BASE_URL . "/manager/edit-template.php?id=$template_id", 'danger', 'Template name is required.');
    }

    $kra_total_weight = array_sum(array_map('floatval', $kra_weights));
    if (!empty($kra_names) && abs($kra_total_weight - 100) > 0.01) {
        redirectWith(BASE_URL . "/manager/edit-template.php?id=$template_id", 'danger', 'KRA weights must total 100%. Current: ' . $kra_total_weight . '%');
    }

    if (abs(($kra_weight + $behavior_weight) - 100) > 0.01) {
        redirectWith(BASE_URL . "/manager/edit-template.php?id=$template_id", 'danger', 'KRA weight + Behavior weight must equal 100%.');
    }

    // Update template
    $stmt = $conn->prepare("UPDATE evaluation_templates SET template_name=?, description=?, target_department=?, evaluation_type=?, kra_weight=?, behavior_weight=?, form_code=?, revision_date=?, effective_date_form=? WHERE template_id=?");
    $stmt->bind_param("ssssddsssi", $template_name, $description, $target_department, $evaluation_type, $kra_weight, $behavior_weight, $form_code, $revision_date, $effective_date_form, $template_id);
    $stmt->execute();
    $stmt->close();

    // Delete old criteria and re-insert
    $conn->query("DELETE FROM evaluation_criteria WHERE template_id = $template_id");

    $crit_stmt = $conn->prepare("INSERT INTO evaluation_criteria (template_id, section, criterion_name, description, weight, scoring_method, sort_order) VALUES (?, 'KRA', ?, ?, ?, 'Scale_1_4', ?)");
    for ($i = 0; $i < count($kra_names); $i++) {
        $name = trim($kra_names[$i]);
        $desc = trim($kra_descriptions[$i] ?? '');
        $weight = floatval($kra_weights[$i] ?? 0);
        $order = $i + 1;
        if (!empty($name)) {
            $crit_stmt->bind_param("issdi", $template_id, $name, $desc, $weight, $order);
            $crit_stmt->execute();
        }
    }
    $crit_stmt->close();

    $beh_stmt = $conn->prepare("INSERT INTO evaluation_criteria (template_id, section, criterion_name, kpi_description, weight, scoring_method, sort_order) VALUES (?, 'Behavior', ?, ?, 0, 'Scale_1_4', ?)");
    for ($i = 0; $i < count($beh_names); $i++) {
        $name = trim($beh_names[$i]);
        $kpi = trim($beh_kpis[$i] ?? '');
        $order = $i + 1;
        if (!empty($name)) {
            $beh_stmt->bind_param("issi", $template_id, $name, $kpi, $order);
            $beh_stmt->execute();
        }
    }
    $beh_stmt->close();

    logAudit($conn, $_SESSION['user_id'], 'UPDATE', 'Template', $template_id, "Updated template: $template_name");
    redirectWith(BASE_URL . '/manager/templates.php', 'success', "Template '$template_name' updated successfully.");
}

// Fetch existing criteria
$kra_criteria = [];
$beh_criteria = [];
$crit_q = $conn->query("SELECT * FROM evaluation_criteria WHERE template_id = $template_id ORDER BY section, sort_order");
while ($c = $crit_q->fetch_assoc()) {
    if ($c['section'] === 'Behavior') {
        $beh_criteria[] = $c;
    } else {
        $kra_criteria[] = $c;
    }
}

// Fetch departments for dropdown
$dept_result = $conn->query("SELECT department_name FROM departments WHERE deleted_at IS NULL AND is_active = 1 ORDER BY department_name");
$departments = [];
while ($d = $dept_result->fetch_assoc()) {
    $departments[] = $d['department_name'];
}

require_once '../includes/header.php';
?>

<style>
    /* ===== Continuous Non-Stop Full Circle Marquee ===== */
    .stat-card-id {
        overflow: hidden !important;
        position: relative !important;
        contain: paint !important;
        clip-path: inset(0px) !important;
    }
    
    .stat-id-marquee-wrap {
        overflow: hidden !important;
        position: relative !important;
        width: 100% !important;
        max-width: 100% !important;
        white-space: nowrap !important;
        clip-path: inset(0px) !important;
        contain: paint !important;
        display: block !important;
    }

    .stat-id-marquee-track {
        display: inline-flex !important;
        white-space: nowrap !important;
        will-change: transform;
    }

    .stat-id-marquee-track.scrolling {
        animation: statMarqueeContinuous 10s linear infinite !important;
    }

    .stat-id-marquee-track.scrolling:hover {
        animation-play-state: paused !important;
        cursor: default;
    }

    .stat-id-marquee-content {
        font-size: 1.25rem;
        font-weight: 700;
        color: #fff;
        line-height: 1.2;
        padding-right: 50px;
        display: inline-block;
        white-space: nowrap;
    }

    .stat-id-marquee-content-static {
        font-size: 1.25rem;
        font-weight: 700;
        color: #fff;
        line-height: 1.2;
        display: inline-block;
        white-space: nowrap;
    }

    @keyframes statMarqueeContinuous {
        0% {
            transform: translate3d(0, 0, 0);
        }
        100% {
            transform: translate3d(-50%, 0, 0);
        }
    }

    /* Wizard Stepper Styling */
    .wizard-nav-wrapper {
        background: #ffffff;
        border-radius: 16px;
        padding: 20px 24px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        margin-bottom: 25px;
        border: 1px solid #e9ecef;
    }
    
    .wizard-stepper {
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: relative;
    }
    
    .wizard-step-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        position: relative;
        z-index: 2;
        cursor: pointer;
        flex: 1;
        text-align: center;
        transition: all 0.3s ease;
    }

    .wizard-step-circle {
        width: 46px;
        height: 46px;
        border-radius: 50%;
        background: #f1f3f5;
        color: #6c757d;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1.05rem;
        border: 2px solid #dee2e6;
        transition: all 0.3s ease;
        box-shadow: 0 2px 6px rgba(0,0,0,0.04);
    }

    .wizard-step-label {
        font-size: 0.82rem;
        font-weight: 600;
        color: #6c757d;
        margin-top: 8px;
        transition: all 0.3s ease;
    }

    .wizard-step-sublabel {
        font-size: 0.7rem;
        color: #adb5bd;
        margin-top: 2px;
    }

    /* Active Step */
    .wizard-step-item.active .wizard-step-circle {
        background: linear-gradient(135deg, #1565c0, #1e88e5);
        color: #ffffff;
        border-color: #1565c0;
        box-shadow: 0 4px 14px rgba(21, 101, 192, 0.4);
        transform: scale(1.1);
    }
    .wizard-step-item.active .wizard-step-label {
        color: #1565c0;
        font-weight: 700;
    }

    /* Completed Step */
    .wizard-step-item.completed .wizard-step-circle {
        background: #2e7d32;
        color: #ffffff;
        border-color: #2e7d32;
        box-shadow: 0 3px 10px rgba(46, 125, 50, 0.25);
    }
    .wizard-step-item.completed .wizard-step-label {
        color: #2e7d32;
    }

    /* Stepper Connecting Lines */
    .wizard-line-bg {
        position: absolute;
        top: 23px;
        left: 5%;
        right: 5%;
        height: 4px;
        background: #e9ecef;
        z-index: 1;
    }

    .wizard-line-progress {
        position: absolute;
        top: 23px;
        left: 5%;
        height: 4px;
        background: linear-gradient(90deg, #2e7d32, #1565c0);
        z-index: 1;
        transition: width 0.4s ease-in-out;
        width: 0%;
    }

    /* Wizard Pane Transitions */
    .wizard-pane {
        display: none;
        animation: wizardFadeIn 0.35s ease-in-out forwards;
    }
    .wizard-pane.active {
        display: block;
    }

    @keyframes wizardFadeIn {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 768px) {
        .wizard-nav-wrapper {
            padding: 15px 10px;
        }
        .wizard-step-label {
            font-size: 0.72rem;
        }
        .wizard-step-sublabel {
            display: none;
        }
        .wizard-step-circle {
            width: 36px;
            height: 36px;
            font-size: 0.85rem;
        }
        .wizard-line-bg, .wizard-line-progress {
            top: 18px;
        }

        .rating-scale-table thead { display: none; }
        .rating-scale-table tr { display: block; border-bottom: 1px solid #eee; padding: 12px 8px; }
        .rating-scale-table td { display: block; border: none; padding: 3px 0; text-align: center; }
        .rating-scale-table td:nth-child(1) .badge { font-size: 0.9rem; width: 100%; }
    }
</style>

<div class="page-hero fadeup">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
        <div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">HR Manager · Evaluations</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-edit me-2" style="color:#BD9414;"></i>Edit Evaluation Template Wizard</h4>
        </div>
        <a href="<?php echo BASE_URL; ?>/manager/templates.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
            <i class="fas fa-arrow-left me-1"></i>Back to Templates
        </a>
    </div>

    <!-- Stats Header Cards -->
    <div class="row g-3">
        <div class="col-6 col-md-3">
            <div class="stat-card stat-card-id" style="overflow: hidden !important; position: relative !important; clip-path: inset(0px) !important; contain: paint !important;">
                <div class="d-flex justify-content-between align-items-start" style="overflow: hidden !important; min-width: 0 !important; width: 100% !important;">
                    <div style="overflow: hidden !important; min-width: 0 !important; flex: 1;" class="me-2">
                        <div class="stat-id-marquee-wrap" id="statTemplateIdentifierContainer">
                            <?php
                                $tmpl_id_text = $tmpl['template_name'] ?: ($tmpl['form_code'] ?: 'ID #'.$tmpl['template_id']);
                                $tmpl_id_scroll = strlen($tmpl_id_text) > 10;
                            ?>
                            <?php if ($tmpl_id_scroll): ?>
                                <div class="stat-id-marquee-track scrolling">
                                    <span class="stat-id-marquee-content"><?php echo e($tmpl_id_text); ?></span>
                                    <span class="stat-id-marquee-content"><?php echo e($tmpl_id_text); ?></span>
                                </div>
                            <?php else: ?>
                                <span class="stat-id-marquee-content-static" id="statTemplateIdentifier"><?php echo e($tmpl_id_text); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="stat-label">Template Identifier</div>
                    </div>
                    <i class="fas fa-barcode stat-icon text-white-50" style="flex-shrink:0;"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo (float)$tmpl['kra_weight']; ?>%</div>
                        <div class="stat-label">KRA Weight Split</div>
                    </div>
                    <i class="fas fa-bullseye stat-icon" style="color:#BD9414;"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo (float)$tmpl['behavior_weight']; ?>%</div>
                        <div class="stat-label">Behavior Weight Split</div>
                    </div>
                    <i class="fas fa-heart stat-icon" style="color:#dc3545;"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value">100%</div>
                        <div class="stat-label">Required Total Weight</div>
                    </div>
                    <i class="fas fa-balance-scale stat-icon" style="color:#28a745;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== WIZARD STEPPER NAVIGATION ===== -->
<div class="wizard-nav-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 me-2" id="wizardCurrentBadge">Step 1 of 5</span>
            <span class="fw-bold text-dark" id="wizardCurrentTitle">Template Information</span>
        </div>
        <div class="text-muted small" id="wizardProgressPercentage">20% Completed</div>
    </div>
    
    <div class="wizard-stepper">
        <div class="wizard-line-bg"></div>
        <div class="wizard-line-progress" id="wizardProgressBar"></div>

        <!-- Step 1 -->
        <div class="wizard-step-item active" id="stepIndicator1" onclick="jumpToStep(1)">
            <div class="wizard-step-circle" id="stepCircle1">1</div>
            <div class="wizard-step-label">Template Info</div>
            <div class="wizard-step-sublabel">Basic Details</div>
        </div>

        <!-- Step 2 -->
        <div class="wizard-step-item" id="stepIndicator2" onclick="jumpToStep(2)">
            <div class="wizard-step-circle" id="stepCircle2">2</div>
            <div class="wizard-step-label">Master Weight</div>
            <div class="wizard-step-sublabel">Split Ratio</div>
        </div>

        <!-- Step 3 -->
        <div class="wizard-step-item" id="stepIndicator3" onclick="jumpToStep(3)">
            <div class="wizard-step-circle" id="stepCircle3">3</div>
            <div class="wizard-step-label">Key Result Areas</div>
            <div class="wizard-step-sublabel">KRA Items</div>
        </div>

        <!-- Step 4 -->
        <div class="wizard-step-item" id="stepIndicator4" onclick="jumpToStep(4)">
            <div class="wizard-step-circle" id="stepCircle4">4</div>
            <div class="wizard-step-label">Core Behaviors</div>
            <div class="wizard-step-sublabel">Values &amp; KPIs</div>
        </div>

        <!-- Step 5 -->
        <div class="wizard-step-item" id="stepIndicator5" onclick="jumpToStep(5)">
            <div class="wizard-step-circle" id="stepCircle5">5</div>
            <div class="wizard-step-label">Status &amp; Pop-up</div>
            <div class="wizard-step-sublabel">Full Summary</div>
        </div>
    </div>
</div>

<form method="POST" action="" id="templateForm">
<?php echo csrfField(); ?>

<!-- ============================================================ -->
<!-- STAGE 1: TEMPLATE INFORMATION -->
<!-- ============================================================ -->
<div class="wizard-pane active" id="wizardStep1">
    <div class="content-card mb-4 border-0 shadow-sm border-start border-4 border-primary">
        <div class="card-header bg-white border-bottom pb-3 d-flex align-items-center justify-content-between">
            <h5 class="mb-0 text-primary fw-bold">
                <i class="fas fa-info-circle me-2"></i>Stage 1: Template Information
            </h5>
            <span class="badge bg-primary px-3 py-2">Stage 1 of 5</span>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-4">Review and modify basic details for this evaluation template.</p>
            
            <div class="row mb-3">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Template Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="template_name" id="inputTemplateName" required value="<?php echo e($tmpl['template_name']); ?>" placeholder="e.g., Annual Performance Review 2026">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-semibold">Evaluation Type</label>
                    <select class="form-select" name="evaluation_type" id="inputEvalType">
                        <?php foreach (['Annual', 'Quarterly', 'Initial', 'Final'] as $t): ?>
                            <option value="<?php echo $t; ?>" <?php echo ($tmpl['evaluation_type'] ?? 'Annual') === $t ? 'selected' : ''; ?>><?php echo $t; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-semibold">Target Department</label>
                    <select class="form-select" name="target_department" id="inputTargetDept">
                        <option value="All Departments" <?php echo ($tmpl['target_department'] ?? '') === 'All Departments' ? 'selected' : ''; ?>>All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo e($dept); ?>" <?php echo ($tmpl['target_department'] ?? '') === $dept ? 'selected' : ''; ?>><?php echo e($dept); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-12 mb-3">
                    <label class="form-label fw-semibold">Description</label>
                    <textarea class="form-control" name="description" id="inputDescription" rows="3" placeholder="Brief description of this evaluation template purpose..."><?php echo e($tmpl['description'] ?? ''); ?></textarea>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">Form Code</label>
                    <input type="text" class="form-control" name="form_code" id="inputFormCode" value="<?php echo e($tmpl['form_code'] ?? ''); ?>" placeholder="e.g., HRD Form-013.01">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">Revision Date</label>
                    <input type="date" class="form-control" name="revision_date" id="inputRevDate" value="<?php echo e($tmpl['revision_date'] ?? ''); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">Effective Date</label>
                    <input type="date" class="form-control" name="effective_date_form" id="inputEffDate" value="<?php echo e($tmpl['effective_date_form'] ?? ''); ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- Action Buttons for Step 1 -->
    <div class="content-card mb-4 border-0 shadow-sm bg-light">
        <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center">
                <a href="<?php echo BASE_URL; ?>/manager/templates.php" class="btn btn-outline-secondary rounded-pill px-4">
                    <i class="fas fa-arrow-left me-2"></i>Cancel
                </a>
                <button type="button" class="btn btn-primary rounded-pill px-5 shadow-sm" onclick="nextStep(1)">
                    Next: Master Weight Configuration <i class="fas fa-arrow-right ms-2"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- STAGE 2: MASTER WEIGHT CONFIGURATION -->
<!-- ============================================================ -->
<div class="wizard-pane" id="wizardStep2">
    <div class="content-card mb-4 border-0 shadow-sm border-start border-4 border-success">
        <div class="card-header bg-white border-bottom pb-3 d-flex align-items-center justify-content-between">
            <h5 class="mb-0 text-success fw-bold">
                <i class="fas fa-balance-scale me-2"></i>Stage 2: Master Weight Configuration
            </h5>
            <span class="badge bg-success px-3 py-2">Stage 2 of 5</span>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-4">Define the overall percentage split between Section I (Key Result Areas) and Section II (Core Behaviors &amp; Values). The sum must equal exactly 100%.</p>
            
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">I. KRA Weight (%)</label>
                    <div class="input-group">
                        <input type="number" class="form-control form-control-lg" name="kra_weight" id="kraWeight" value="<?php echo (float)($tmpl['kra_weight'] ?? 80); ?>" min="0" max="100" step="1" oninput="syncWeights('kra')">
                        <span class="input-group-text bg-success-subtle text-success fw-bold">%</span>
                    </div>
                    <small class="text-muted d-block mt-1">Strategic Programs &amp; Job Performance</small>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">II. Behavior &amp; Values Weight (%)</label>
                    <div class="input-group">
                        <input type="number" class="form-control form-control-lg" name="behavior_weight" id="behaviorWeight" value="<?php echo (float)($tmpl['behavior_weight'] ?? 20); ?>" min="0" max="100" step="1" oninput="syncWeights('behavior')">
                        <span class="input-group-text bg-info-subtle text-info fw-bold">%</span>
                    </div>
                    <small class="text-muted d-block mt-1">Behavioral Competencies &amp; Core Values</small>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label d-none d-md-block text-muted">&nbsp;</label>
                    <div class="d-flex align-items-center justify-content-between p-3 rounded-3 border shadow-sm" id="weightSplitStatus" style="background:#e8f5e9;">
                        <strong class="text-dark">Split Total:</strong>
                        <div class="d-flex align-items-center gap-2">
                            <span id="weightSplitBadge" class="badge bg-success fs-6" style="font-size:1rem;">100%</span>
                            <strong id="weightSplitMsg" class="text-success mb-0" style="font-size:0.9rem;"><i class="fas fa-check-circle me-1"></i>Valid Split</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Guides and Formula Reference -->
            <div class="row g-3">
                <div class="col-md-6">
                    <!-- Score Computation Guide -->
                    <div class="p-3 bg-light rounded-3 border h-100">
                        <h6 class="fw-bold text-dark mb-2"><i class="fas fa-calculator text-primary me-2"></i>Evaluation Score Formula</h6>
                        <p class="small text-muted mb-2">Each KRA item carries a weight % (summing to 100% in Section I). Shared core values ratings are averaged across all Section II items.</p>
                        <div class="p-3 bg-white rounded border font-monospace small text-dark">
                            <strong>KRA Subtotal</strong> = &Sigma;(KRA Item Weight &times; Rating) &divide; 100<br>
                            <strong>Shared Core Values Avg</strong> = &Sigma;(Shared Core Values Ratings) &divide; Total Shared Core Values Items<br>
                            <strong>Final Score</strong> = (KRA Subtotal &times; KRA%) + (Shared Core Values Avg &times; Shared Core Values%)
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <!-- Performance Rating Scale Reference -->
                    <div class="p-3 rounded-3 border h-100 bg-white">
                        <h6 class="fw-bold text-dark mb-2"><i class="fas fa-star text-warning me-2"></i>Rating Scale Guide</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0 rating-scale-table small">
                                <thead class="table-light">
                                    <tr>
                                        <th>Scale</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td><span class="badge bg-success">3.60 – 4.00</span></td><td><strong>Outstanding</strong></td></tr>
                                    <tr><td><span class="badge bg-info">2.60 – 3.59</span></td><td><strong>Exceeds Expectations</strong></td></tr>
                                    <tr><td><span class="badge bg-warning text-dark">2.00 – 2.59</span></td><td><strong>Meets Expectations</strong></td></tr>
                                    <tr><td><span class="badge bg-danger">1.00 – 1.99</span></td><td><strong>Needs Improvement</strong></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Action Buttons for Step 2 -->
    <div class="content-card mb-4 border-0 shadow-sm bg-light">
        <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" onclick="prevStep(2)">
                    <i class="fas fa-arrow-left me-2"></i>Previous
                </button>
                <button type="button" class="btn btn-success rounded-pill px-5 shadow-sm" onclick="nextStep(2)">
                    Next: Key Result Areas (KRA) <i class="fas fa-arrow-right ms-2"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- STAGE 3: KEY RESULT AREAS (KRA) -->
<!-- ============================================================ -->
<div class="wizard-pane" id="wizardStep3">
    <div class="content-card mb-4 border-0 shadow-sm border-start border-4 border-success">
        <div class="card-header bg-white border-bottom pb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="mb-0 text-success fw-bold">
                <i class="fas fa-bullseye me-2"></i>Stage 3: Key Result Areas (KRA)
            </h5>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary px-3 py-2 shadow-sm fs-6" id="kraWeightBadge">Total: 0%</span>
                <button type="button" class="btn btn-sm btn-success rounded-pill px-3 shadow-sm" onclick="addKRA()">
                    <i class="fas fa-plus me-1"></i>Add KRA Item
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="alert alert-info py-2 px-3 small border-0 mb-3" style="background:#e3f2fd; color:#0d47a1;">
                <i class="fas fa-info-circle me-2"></i>The sum of all KRA item weights MUST equal exactly 100%.
            </div>

            <div id="kraContainer">
                <!-- KRA rows inserted by JS -->
            </div>
        </div>
    </div>

    <!-- Action Buttons for Step 3 -->
    <div class="content-card mb-4 border-0 shadow-sm bg-light">
        <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" onclick="prevStep(3)">
                    <i class="fas fa-arrow-left me-2"></i>Previous
                </button>
                <button type="button" class="btn btn-success rounded-pill px-5 shadow-sm" onclick="nextStep(3)">
                    Next: Core Behaviors &amp; Values <i class="fas fa-arrow-right ms-2"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- STAGE 4: CORE BEHAVIORS & VALUES -->
<!-- ============================================================ -->
<div class="wizard-pane" id="wizardStep4">
    <div class="content-card mb-4 border-0 shadow-sm border-start border-4 border-info">
        <div class="card-header bg-white border-bottom pb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="mb-0 text-info fw-bold">
                <i class="fas fa-heart me-2"></i>Stage 4: Core Behaviors &amp; Values
            </h5>
            <button type="button" class="btn btn-sm btn-info text-white rounded-pill px-3 shadow-sm" onclick="addBehavior()">
                <i class="fas fa-plus me-1"></i>Add Behavior Item
            </button>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">Add organizational values, soft skills, and behavioral key performance indicators (KPIs).</p>
            
            <div id="behaviorContainer">
                <!-- Behavior rows inserted by JS -->
            </div>
        </div>
    </div>

    <!-- Action Buttons for Step 4 -->
    <div class="content-card mb-4 border-0 shadow-sm bg-light">
        <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" onclick="prevStep(4)">
                    <i class="fas fa-arrow-left me-2"></i>Previous
                </button>
                <button type="button" class="btn btn-primary rounded-pill px-5 shadow" onclick="nextStep(4)">
                    Review Template Status &amp; Pop-up (Stage 5) <i class="fas fa-clipboard-check ms-2"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- STAGE 5: STATUS & POP-UP (WHOLE DETAILS OF TEMPLATE STATUS) -->
<!-- ============================================================ -->
<div class="wizard-pane" id="wizardStep5">
    <div class="content-card mb-4 border-0 shadow-sm border-start border-4 border-dark">
        <div class="card-header bg-white border-bottom pb-3 d-flex align-items-center justify-content-between">
            <h5 class="mb-0 text-dark fw-bold">
                <i class="fas fa-list-check me-2 text-primary"></i>Stage 5: Template Status &amp; Final Review Pop-up
            </h5>
            <span class="badge bg-dark px-3 py-2">Stage 5 of 5</span>
        </div>
        <div class="card-body">
            <div class="p-4 rounded-3 text-center mb-4" style="background: linear-gradient(135deg, #f8f9fa, #e9ecef); border: 2px dashed #ced4da;">
                <div style="width:64px;height:64px;border-radius:50%;background:#1565c0;color:#fff;display:inline-flex;align-items:center;justify-content:center;" class="mb-3 shadow">
                    <i class="fas fa-clipboard-list fa-2x"></i>
                </div>
                <h4 class="fw-bold text-dark mb-2">Template Ready for Final Changes</h4>
                <p class="text-muted small mx-auto" style="max-width:550px;">
                    All modified configuration steps have been reviewed. Click the button below to trigger the pop-up modal showing the <strong>whole details of the template status</strong>.
                </p>
                <button type="button" class="btn btn-primary btn-lg rounded-pill px-5 shadow-lg mt-2" onclick="openTemplateStatusModal()">
                    <i class="fas fa-external-link-alt me-2"></i>Open Full Template Status Pop-up
                </button>
            </div>

            <!-- Quick Inline Summary Card -->
            <div id="inlineStatusSummary"></div>
        </div>
    </div>

    <!-- Action Buttons for Step 5 -->
    <div class="content-card mb-4 border-0 shadow-sm bg-light">
        <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" onclick="prevStep(5)">
                    <i class="fas fa-arrow-left me-2"></i>Back to Edit (Stage 4)
                </button>
                <button type="button" class="btn btn-success btn-lg rounded-pill px-5 shadow" onclick="openTemplateStatusModal()">
                    <i class="fas fa-save me-2"></i>Review &amp; Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

</form>

<!-- ============================================================ -->
<!-- STAGE 5 POP-UP MODAL: WHOLE DETAILS OF THE TEMPLATE STATUS -->
<!-- ============================================================ -->
<div class="modal fade" id="templateStatusModal" tabindex="-1" aria-labelledby="templateStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow-lg" style="border-radius:20px; overflow:hidden;">
            <!-- Modal Header -->
            <div class="modal-header border-0 p-4 text-white" style="background: linear-gradient(135deg, #102a43, #243b53);">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:52px;height:52px;border-radius:16px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;backdrop-filter:blur(4px);">
                        <i class="fas fa-clipboard-check fa-2x text-warning"></i>
                    </div>
                    <div>
                        <h4 class="modal-title fw-bold mb-0 text-white" id="templateStatusModalLabel">Template Status &amp; Specification Overview</h4>
                        <div class="text-white-50 small mt-1">Review full template configuration &amp; system integrity checks before saving changes</div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body p-4 style-custom-scrollbar" style="max-height: 75vh; overflow-y: auto; background:#f8f9fa;">
                
                <!-- System Status Checks Banner -->
                <div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2 pb-2 border-bottom">
                            <h6 class="fw-bold text-dark mb-0"><i class="fas fa-shield-alt text-success me-2"></i>System Validation &amp; Configuration Readiness</h6>
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1" id="statusOverallBadge">
                                <i class="fas fa-check-circle me-1"></i>All Checks Passed
                            </span>
                        </div>
                        <div class="row g-2" id="statusCheckGrid">
                            <!-- Dynamic Status Badges -->
                        </div>
                    </div>
                </div>

                <!-- Section 1: Basic Information -->
                <div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="fw-bold text-primary mb-0"><i class="fas fa-info-circle me-2"></i>1. Template Basic Information</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3" id="modalTemplateInfo">
                            <!-- Injected by JS -->
                        </div>
                    </div>
                </div>

                <!-- Section 2: Master Weight Configuration -->
                <div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="fw-bold text-success mb-0"><i class="fas fa-balance-scale me-2"></i>2. Master Weight Configuration</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row align-items-center g-3" id="modalMasterWeight">
                            <!-- Injected by JS -->
                        </div>
                    </div>
                </div>

                <!-- Section 3: Key Result Areas (KRA) -->
                <div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold text-success mb-0"><i class="fas fa-bullseye me-2"></i>3. Key Result Areas (KRA) Items</h6>
                        <span class="badge bg-success px-3 py-1" id="modalKraBadge">Total Weight: 100%</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="modalKraTable">
                                <thead class="table-light small text-uppercase">
                                    <tr>
                                        <th style="width:60px;" class="text-center">#</th>
                                        <th style="width:30%;">KRA Item Name</th>
                                        <th>Description</th>
                                        <th style="width:120px;" class="text-end">Weight (%)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Injected by JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Section 4: Core Behaviors & Values -->
                <div class="card border-0 shadow-sm mb-2" style="border-radius:14px;">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold text-info mb-0"><i class="fas fa-heart me-2"></i>4. Core Behaviors &amp; Values Items</h6>
                        <span class="badge bg-info text-white px-3 py-1" id="modalBehaviorBadge">0 Items</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="modalBehaviorTable">
                                <thead class="table-light small text-uppercase">
                                    <tr>
                                        <th style="width:60px;" class="text-center">#</th>
                                        <th style="width:30%;">Behavior Name</th>
                                        <th>Key Performance Indicator (KPI)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Injected by JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Modal Footer -->
            <div class="modal-footer border-0 p-4 bg-white d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">
                    <i class="fas fa-pencil-alt me-2"></i>Back to Edit Form
                </button>
                <button type="button" class="btn btn-success btn-lg rounded-pill px-5 shadow-lg" id="modalFinalSubmitBtn" onclick="doFinalSubmit()">
                    <i class="fas fa-check-circle me-2"></i>Looks Good — Save Changes Now
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ===== VALIDATION ERROR MODAL ===== -->
<div class="modal fade" id="validationErrorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:18px;overflow:hidden;">
            <div class="modal-header border-0" style="background:linear-gradient(135deg,#b71c1c,#c62828);">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:44px;height:44px;border-radius:12px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-exclamation-triangle fa-lg text-white"></i>
                    </div>
                    <h5 class="modal-title text-white fw-bold mb-0">Cannot Proceed to Next Stage</h5>
                </div>
                <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="validationErrorList"></div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-danger rounded-pill px-4" data-bs-dismiss="modal">
                    <i class="fas fa-pencil-alt me-2"></i>Fix Requirements
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentStep = 1;
let kraCount = 0;
let behaviorCount = 0;

const stepTitles = {
    1: 'Template Information',
    2: 'Master Weight Configuration',
    3: 'Key Result Areas (KRA)',
    4: 'Core Behaviors & Values',
    5: 'Template Status & Pop-up Review'
};

// ============================================================
// WIZARD NAVIGATION FUNCTIONS
// ============================================================
function updateWizardUI() {
    for (let i = 1; i <= 5; i++) {
        const pane = document.getElementById('wizardStep' + i);
        const indicator = document.getElementById('stepIndicator' + i);
        const circle = document.getElementById('stepCircle' + i);

        if (pane) {
            if (i === currentStep) {
                pane.classList.add('active');
            } else {
                pane.classList.remove('active');
            }
        }

        if (indicator) {
            indicator.classList.remove('active', 'completed');
            if (i === currentStep) {
                indicator.classList.add('active');
                circle.innerHTML = i;
            } else if (i < currentStep) {
                indicator.classList.add('completed');
                circle.innerHTML = '<i class="fas fa-check"></i>';
            } else {
                circle.innerHTML = i;
            }
        }
    }

    const percent = (currentStep / 5) * 100;
    const bar = document.getElementById('wizardProgressBar');
    if (bar) bar.style.width = ((currentStep - 1) / 4 * 90) + '%';

    document.getElementById('wizardCurrentBadge').textContent = `Step ${currentStep} of 5`;
    document.getElementById('wizardCurrentTitle').textContent = stepTitles[currentStep] || '';
    document.getElementById('wizardProgressPercentage').textContent = `${percent}% Completed`;

    window.scrollTo({ top: 120, behavior: 'smooth' });

    if (currentStep === 5) {
        compileInlineStatusSummary();
    }
}

function validateStep(step) {
    const errors = [];

    if (step === 1) {
        const name = document.querySelector('[name="template_name"]')?.value?.trim();
        if (!name) {
            errors.push('Template Name is required before proceeding to Step 2.');
        }
    } else if (step === 2) {
        const kra = parseFloat(document.getElementById('kraWeight')?.value) || 0;
        const beh = parseFloat(document.getElementById('behaviorWeight')?.value) || 0;
        if (Math.abs((kra + beh) - 100) > 0.01) {
            errors.push(`Master weight split must equal exactly 100%. Current total: <strong>${(kra + beh)}%</strong>.`);
        }
    } else if (step === 3) {
        const rows = document.querySelectorAll('#kraContainer .kra-criterion-row');
        if (rows.length === 0) {
            errors.push('At least one Key Result Area (KRA) item is required.');
        } else {
            let kraSum = 0;
            let missingName = false;
            rows.forEach(r => {
                const n = r.querySelector('input[name="kra_name[]"]')?.value?.trim();
                const w = parseFloat(r.querySelector('input[name="kra_weight_item[]"]')?.value) || 0;
                if (!n) missingName = true;
                kraSum += w;
            });
            if (missingName) {
                errors.push('All KRA items must have a KRA Name specified.');
            }
            if (Math.abs(kraSum - 100) > 0.01) {
                errors.push(`KRA item weights must sum to exactly 100%. Current sum: <strong>${kraSum.toFixed(1)}%</strong>.`);
            }
        }
    } else if (step === 4) {
        const rows = document.querySelectorAll('#behaviorContainer .behavior-criterion-row');
        if (rows.length === 0) {
            errors.push('At least one Core Behavior item is required.');
        } else {
            let missingName = false;
            rows.forEach(r => {
                const n = r.querySelector('input[name="behavior_name[]"]')?.value?.trim();
                if (!n) missingName = true;
            });
            if (missingName) {
                errors.push('All Core Behavior items must have a Behavior Name specified.');
            }
        }
    }

    if (errors.length > 0) {
        showValidationErrors(errors);
        return false;
    }
    return true;
}

function nextStep(fromStep) {
    if (validateStep(fromStep)) {
        currentStep = fromStep + 1;
        if (currentStep > 5) currentStep = 5;
        updateWizardUI();
        if (currentStep === 5) {
            openTemplateStatusModal();
        }
    }
}

function prevStep(fromStep) {
    currentStep = fromStep - 1;
    if (currentStep < 1) currentStep = 1;
    updateWizardUI();
}

function jumpToStep(targetStep) {
    if (targetStep === currentStep) return;
    
    if (targetStep > currentStep) {
        for (let s = 1; s < targetStep; s++) {
            if (!validateStep(s)) return;
        }
    }
    currentStep = targetStep;
    updateWizardUI();
    if (currentStep === 5) {
        openTemplateStatusModal();
    }
}

function showValidationErrors(errors) {
    let html = '<ul class="list-unstyled mb-0">';
    errors.forEach(e => {
        html += `<li class="d-flex align-items-start gap-3 mb-3 p-3 rounded" style="background:#fff5f5;border:1.5px solid #ffcdd2;">
            <i class="fas fa-times-circle text-danger mt-1" style="font-size:1.1rem;"></i>
            <span>${e}</span></li>`;
    });
    html += '</ul>';
    document.getElementById('validationErrorList').innerHTML = html;
    const veModal = new bootstrap.Modal(document.getElementById('validationErrorModal'));
    veModal.show();
}

// ============================================================
// DYNAMIC ITEM ADD / REMOVE
// ============================================================
function addKRA(name = '', desc = '', weight = '') {
    kraCount++;
    const container = document.getElementById('kraContainer');
    const html = `
        <div class="kra-criterion-row border border-success rounded p-3 mb-3 position-relative bg-white shadow-sm" id="kra_${kraCount}" style="border-left: 4px solid var(--bs-success) !important;">
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                <strong class="text-success fw-bold"><i class="fas fa-bullseye me-2"></i>KRA Item #${container.children.length + 1}</strong>
                <button type="button" class="btn btn-sm btn-outline-danger rounded-circle" onclick="removeKRA(${kraCount})" title="Remove Item">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="row">
                <div class="col-md-4 mb-2">
                    <label class="form-label small fw-semibold">KRA Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="kra_name[]" value="${escAttr(name)}" required placeholder="e.g., Sales Target Achievement">
                </div>
                <div class="col-md-5 mb-2">
                    <label class="form-label small fw-semibold">Description</label>
                    <input type="text" class="form-control" name="kra_description[]" value="${escAttr(desc)}" placeholder="Detailed description of deliverables & standards">
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label small fw-semibold">Weight (%) <span class="text-danger">*</span></label>
                    <input type="number" class="form-control kra-weight-input" name="kra_weight_item[]" value="${weight}" required min="1" max="100" step="0.01" placeholder="e.g., 25" oninput="updateKRAWeight()">
                </div>
            </div>
        </div>`;
    container.insertAdjacentHTML('beforeend', html);
    updateKRAWeight();
}

function removeKRA(id) {
    const el = document.getElementById('kra_' + id);
    if (el) { el.remove(); renumberKRA(); updateKRAWeight(); }
}

function renumberKRA() {
    document.querySelectorAll('#kraContainer .kra-criterion-row').forEach((row, idx) => {
        const label = row.querySelector('strong');
        if (label) label.innerHTML = '<i class="fas fa-bullseye me-2"></i>KRA Item #' + (idx + 1);
    });
}

function updateKRAWeight() {
    let total = 0;
    document.querySelectorAll('.kra-weight-input').forEach(input => {
        total += parseFloat(input.value) || 0;
    });
    const badge = document.getElementById('kraWeightBadge');
    if (badge) {
        badge.textContent = 'Total: ' + total.toFixed(1) + '%';
        badge.className = 'badge px-3 py-2 shadow-sm fs-6 ' + (Math.abs(total - 100) < 0.01 ? 'bg-success' : 'bg-danger');
    }
}

function addBehavior(name = '', kpi = '') {
    behaviorCount++;
    const container = document.getElementById('behaviorContainer');
    const num = container.children.length + 1;
    const html = `
        <div class="behavior-criterion-row border border-info rounded p-3 mb-3 position-relative bg-white shadow-sm" id="behavior_${behaviorCount}" style="border-left: 4px solid var(--bs-info) !important;">
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                <strong class="text-info fw-bold"><i class="fas fa-heart me-2"></i>#${num}. <span class="behavior-title-display">${name || 'Behavior Item'}</span></strong>
                <button type="button" class="btn btn-sm btn-outline-danger rounded-circle" onclick="removeBehavior(${behaviorCount})" title="Remove Item">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="row">
                <div class="col-md-4 mb-2">
                    <label class="form-label small fw-semibold">Behavior Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="behavior_name[]" value="${escAttr(name)}" required placeholder="e.g., Positive Attitude" oninput="updateBehaviorTitle(this, ${behaviorCount})">
                </div>
                <div class="col-md-8 mb-2">
                    <label class="form-label small fw-semibold">Key Performance Indicator (KPI)</label>
                    <input type="text" class="form-control" name="behavior_kpi[]" value="${escAttr(kpi)}" placeholder="e.g., Displays positive attitude at work.">
                </div>
            </div>
        </div>`;
    container.insertAdjacentHTML('beforeend', html);
}

function removeBehavior(id) {
    const el = document.getElementById('behavior_' + id);
    if (el) { el.remove(); renumberBehavior(); }
}

function renumberBehavior() {
    document.querySelectorAll('#behaviorContainer .behavior-criterion-row').forEach((row, idx) => {
        const label = row.querySelector('strong');
        const nameInput = row.querySelector('input[name="behavior_name[]"]');
        if (label) label.innerHTML = '<i class="fas fa-heart me-2"></i>#' + (idx + 1) + '. <span class="behavior-title-display">' + (nameInput?.value || 'Behavior Item') + '</span>';
    });
}

function updateBehaviorTitle(input, id) {
    const row = document.getElementById('behavior_' + id);
    if(row) {
        const display = row.querySelector('.behavior-title-display');
        if(display) display.textContent = input.value || 'Behavior Item';
    }
}

function syncWeights(source) {
    const kraInput = document.getElementById('kraWeight');
    const behInput = document.getElementById('behaviorWeight');
    if (source === 'kra') {
        behInput.value = 100 - (parseFloat(kraInput.value) || 0);
    } else {
        kraInput.value = 100 - (parseFloat(behInput.value) || 0);
    }
    updateWeightSplit();
}

function updateWeightSplit() {
    const kra = parseFloat(document.getElementById('kraWeight').value) || 0;
    const beh = parseFloat(document.getElementById('behaviorWeight').value) || 0;
    const total = kra + beh;
    const badge = document.getElementById('weightSplitBadge');
    const msg = document.getElementById('weightSplitMsg');
    const status = document.getElementById('weightSplitStatus');
    if (badge) badge.textContent = total + '%';
    if (Math.abs(total - 100) < 0.01) {
        if (badge) badge.className = 'badge bg-success shadow-sm fs-6';
        if (msg) { msg.innerHTML = '<i class="fas fa-check-circle me-1"></i>Valid Split'; msg.className = 'text-success mb-0'; }
        if (status) status.style.background = '#e8f5e9';
    } else {
        if (badge) badge.className = 'badge bg-danger shadow-sm fs-6';
        if (msg) { msg.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i>Invalid Split'; msg.className = 'text-danger mb-0'; }
        if (status) status.style.background = '#ffebee';
    }
}

function escAttr(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML.replace(/"/g, '&quot;');
}

// ============================================================
// STAGE 5 POP-UP MODAL (WHOLE DETAILS OF TEMPLATE STATUS)
// ============================================================
function openTemplateStatusModal() {
    const step1Valid = validateStep(1);
    const step2Valid = validateStep(2);
    const step3Valid = validateStep(3);
    const step4Valid = validateStep(4);

    const overallValid = step1Valid && step2Valid && step3Valid && step4Valid;

    const checkGrid = document.getElementById('statusCheckGrid');
    checkGrid.innerHTML = `
        <div class="col-md-3 col-6">
            <div class="p-2 px-3 rounded border d-flex align-items-center justify-content-between ${step1Valid ? 'bg-success-subtle border-success-subtle text-success' : 'bg-danger-subtle border-danger-subtle text-danger'}">
                <span class="small fw-semibold">1. Basic Info</span>
                <i class="fas ${step1Valid ? 'fa-check-circle' : 'fa-times-circle'}"></i>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="p-2 px-3 rounded border d-flex align-items-center justify-content-between ${step2Valid ? 'bg-success-subtle border-success-subtle text-success' : 'bg-danger-subtle border-danger-subtle text-danger'}">
                <span class="small fw-semibold">2. Master Split</span>
                <i class="fas ${step2Valid ? 'fa-check-circle' : 'fa-times-circle'}"></i>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="p-2 px-3 rounded border d-flex align-items-center justify-content-between ${step3Valid ? 'bg-success-subtle border-success-subtle text-success' : 'bg-danger-subtle border-danger-subtle text-danger'}">
                <span class="small fw-semibold">3. KRA Items (100%)</span>
                <i class="fas ${step3Valid ? 'fa-check-circle' : 'fa-times-circle'}"></i>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="p-2 px-3 rounded border d-flex align-items-center justify-content-between ${step4Valid ? 'bg-success-subtle border-success-subtle text-success' : 'bg-danger-subtle border-danger-subtle text-danger'}">
                <span class="small fw-semibold">4. Core Behaviors</span>
                <i class="fas ${step4Valid ? 'fa-check-circle' : 'fa-times-circle'}"></i>
            </div>
        </div>
    `;

    const overallBadge = document.getElementById('statusOverallBadge');
    if (overallValid) {
        overallBadge.className = 'badge bg-success-subtle text-success border border-success-subtle px-3 py-1';
        overallBadge.innerHTML = '<i class="fas fa-check-circle me-1"></i>All Checks Passed';
        document.getElementById('modalFinalSubmitBtn').disabled = false;
    } else {
        overallBadge.className = 'badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1';
        overallBadge.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>Action Required';
        document.getElementById('modalFinalSubmitBtn').disabled = true;
    }

    const tplName = document.querySelector('[name="template_name"]')?.value || '—';
    const evalType = document.querySelector('[name="evaluation_type"]')?.value || '—';
    const targetDept = document.querySelector('[name="target_department"]')?.value || '—';
    const formCode = document.querySelector('[name="form_code"]')?.value || '—';
    const revDate = document.querySelector('[name="revision_date"]')?.value || '—';
    const effDate = document.querySelector('[name="effective_date_form"]')?.value || '—';
    const desc = document.querySelector('[name="description"]')?.value || 'No description provided.';

    document.getElementById('modalTemplateInfo').innerHTML = `
        <div class="col-md-6">
            <div class="text-muted small text-uppercase">Template Name</div>
            <div class="fw-bold text-dark fs-6">${escAttr(tplName)}</div>
        </div>
        <div class="col-md-3">
            <div class="text-muted small text-uppercase">Evaluation Type</div>
            <div class="fw-semibold text-dark"><span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1">${escAttr(evalType)}</span></div>
        </div>
        <div class="col-md-3">
            <div class="text-muted small text-uppercase">Target Department</div>
            <div class="fw-semibold text-dark"><span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-1">${escAttr(targetDept)}</span></div>
        </div>
        <div class="col-md-4">
            <div class="text-muted small text-uppercase">Form Control Code</div>
            <div class="fw-semibold text-dark">${escAttr(formCode)}</div>
        </div>
        <div class="col-md-4">
            <div class="text-muted small text-uppercase">Revision Date</div>
            <div class="fw-semibold text-dark">${escAttr(revDate)}</div>
        </div>
        <div class="col-md-4">
            <div class="text-muted small text-uppercase">Effective Date</div>
            <div class="fw-semibold text-dark">${escAttr(effDate)}</div>
        </div>
        <div class="col-12 border-top pt-2 mt-2">
            <div class="text-muted small text-uppercase">Description</div>
            <div class="text-secondary small">${escAttr(desc)}</div>
        </div>
    `;

    const kraW = parseFloat(document.getElementById('kraWeight')?.value) || 0;
    const behW = parseFloat(document.getElementById('behaviorWeight')?.value) || 0;
    document.getElementById('modalMasterWeight').innerHTML = `
        <div class="col-md-6">
            <div class="p-3 bg-success-subtle rounded-3 border border-success-subtle d-flex align-items-center justify-content-between">
                <div>
                    <div class="fw-bold text-success">Section I: Key Result Areas (KRA)</div>
                    <div class="small text-muted">Strategic Programs &amp; Performance</div>
                </div>
                <div class="fs-4 fw-bold text-success">${kraW}%</div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="p-3 bg-info-subtle rounded-3 border border-info-subtle d-flex align-items-center justify-content-between">
                <div>
                    <div class="fw-bold text-info">Section II: Core Behaviors &amp; Values</div>
                    <div class="small text-muted">Behavioral Competencies &amp; Values</div>
                </div>
                <div class="fs-4 fw-bold text-info">${behW}%</div>
            </div>
        </div>
    `;

    const kraRows = document.querySelectorAll('#kraContainer .kra-criterion-row');
    let kraTbody = '';
    let kraSum = 0;
    kraRows.forEach((row, idx) => {
        const name = row.querySelector('input[name="kra_name[]"]')?.value || '—';
        const desc = row.querySelector('input[name="kra_description[]"]')?.value || '—';
        const wt = parseFloat(row.querySelector('input[name="kra_weight_item[]"]')?.value) || 0;
        kraSum += wt;
        kraTbody += `
            <tr>
                <td class="text-center fw-bold text-muted">${idx + 1}</td>
                <td class="fw-semibold text-dark">${escAttr(name)}</td>
                <td class="text-muted small">${escAttr(desc)}</td>
                <td class="text-end fw-bold text-success">${wt.toFixed(1)}%</td>
            </tr>
        `;
    });
    if (kraRows.length === 0) {
        kraTbody = '<tr><td colspan="4" class="text-center text-muted py-3">No KRA items configured.</td></tr>';
    }
    document.querySelector('#modalKraTable tbody').innerHTML = kraTbody;
    const modalKraBadge = document.getElementById('modalKraBadge');
    modalKraBadge.textContent = `Total KRA Weight: ${kraSum.toFixed(1)}%`;
    modalKraBadge.className = 'badge px-3 py-1 ' + (Math.abs(kraSum - 100) < 0.01 ? 'bg-success' : 'bg-danger');

    const behRows = document.querySelectorAll('#behaviorContainer .behavior-criterion-row');
    let behTbody = '';
    behRows.forEach((row, idx) => {
        const name = row.querySelector('input[name="behavior_name[]"]')?.value || '—';
        const kpi = row.querySelector('input[name="behavior_kpi[]"]')?.value || '—';
        behTbody += `
            <tr>
                <td class="text-center fw-bold text-muted">${idx + 1}</td>
                <td class="fw-semibold text-dark">${escAttr(name)}</td>
                <td class="text-muted small">${escAttr(kpi)}</td>
            </tr>
        `;
    });
    if (behRows.length === 0) {
        behTbody = '<tr><td colspan="3" class="text-center text-muted py-3">No behavior items configured.</td></tr>';
    }
    document.querySelector('#modalBehaviorTable tbody').innerHTML = behTbody;
    document.getElementById('modalBehaviorBadge').textContent = `${behRows.length} Behavior Items`;

    const modal = new bootstrap.Modal(document.getElementById('templateStatusModal'));
    modal.show();
}

function compileInlineStatusSummary() {
    const tplName = document.querySelector('[name="template_name"]')?.value || 'Not set';
    const kraW = parseFloat(document.getElementById('kraWeight')?.value) || 0;
    const behW = parseFloat(document.getElementById('behaviorWeight')?.value) || 0;
    const kraRows = document.querySelectorAll('#kraContainer .kra-criterion-row').length;
    const behRows = document.querySelectorAll('#behaviorContainer .behavior-criterion-row').length;

    const html = `
        <div class="row g-3">
            <div class="col-md-3">
                <div class="p-3 bg-white rounded-3 border shadow-sm text-center">
                    <div class="text-muted small text-uppercase fw-semibold">Template Name</div>
                    <div class="fw-bold text-primary text-truncate mt-1">${escAttr(tplName)}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3 bg-white rounded-3 border shadow-sm text-center">
                    <div class="text-muted small text-uppercase fw-semibold">Master Split</div>
                    <div class="fw-bold text-success mt-1">KRA ${kraW}% / Beh ${behW}%</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3 bg-white rounded-3 border shadow-sm text-center">
                    <div class="text-muted small text-uppercase fw-semibold">KRA Items</div>
                    <div class="fw-bold text-dark mt-1">${kraRows} Items Configured</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3 bg-white rounded-3 border shadow-sm text-center">
                    <div class="text-muted small text-uppercase fw-semibold">Behavior Items</div>
                    <div class="fw-bold text-info mt-1">${behRows} Items Configured</div>
                </div>
            </div>
        </div>
    `;
    document.getElementById('inlineStatusSummary').innerHTML = html;
}

function doFinalSubmit() {
    const modalEl = document.getElementById('templateStatusModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();
    
    const btn = document.getElementById('modalFinalSubmitBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving Changes...';
    }
    
    document.getElementById('templateForm').submit();
}

function updateTemplateIdentifierMarquee() {
    const wrap = document.getElementById('statTemplateIdentifierContainer');
    if (!wrap) return;

    const tplNameInput = document.querySelector('[name="template_name"]');
    const formCodeInput = document.querySelector('[name="form_code"]');
    const fallback = '<?php echo e($tmpl['template_name'] ?: ($tmpl['form_code'] ?: 'ID #'.$tmpl['template_id'])); ?>';
    let text = tplNameInput?.value?.trim() || formCodeInput?.value?.trim() || fallback;

    const needsScroll = text.length > 10;
    if (needsScroll) {
        wrap.innerHTML = `
            <div class="stat-id-marquee-track scrolling">
                <span class="stat-id-marquee-content">${escAttr(text)}</span>
                <span class="stat-id-marquee-content">${escAttr(text)}</span>
            </div>`;
    } else {
        wrap.innerHTML = `<span class="stat-id-marquee-content-static" id="statTemplateIdentifier">${escAttr(text)}</span>`;
    }
}

// Initial Data Population
document.addEventListener('DOMContentLoaded', function() {
    <?php foreach ($kra_criteria as $k): ?>
        addKRA(<?php echo json_encode($k['criterion_name']); ?>, <?php echo json_encode($k['description'] ?? ''); ?>, '<?php echo $k['weight']; ?>');
    <?php endforeach; ?>
    <?php if (empty($kra_criteria)): ?>
        addKRA('', '', ''); addKRA('', '', ''); addKRA('', '', '');
    <?php endif; ?>

    <?php foreach ($beh_criteria as $b): ?>
        addBehavior(<?php echo json_encode($b['criterion_name']); ?>, <?php echo json_encode($b['kpi_description'] ?? ''); ?>);
    <?php endforeach; ?>

    updateWeightSplit();
    updateWizardUI();
    updateTemplateIdentifierMarquee();

    const form = document.getElementById('templateForm');
    if (form) {
        form.addEventListener('input', updateTemplateIdentifierMarquee);
        form.addEventListener('change', updateTemplateIdentifierMarquee);
    }
    window.addEventListener('resize', updateTemplateIdentifierMarquee);
});
</script>

<?php require_once '../includes/footer.php'; ?>
