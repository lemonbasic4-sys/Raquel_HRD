<?php
require_once __DIR__ . '/../includes/historical-import-controller.php';

$stat_records_stmt = $conn->query("SELECT COUNT(*) AS total FROM evaluations WHERE is_historical = 1 AND deleted_at IS NULL");
$historical_total_count = $stat_records_stmt ? (int)($stat_records_stmt->fetch_assoc()['total'] ?? 0) : 0;

$stat_emps_stmt = $conn->query("SELECT COUNT(DISTINCT employee_id) AS total FROM evaluations WHERE is_historical = 1 AND deleted_at IS NULL");
$historical_employees_count = $stat_emps_stmt ? (int)($stat_emps_stmt->fetch_assoc()['total'] ?? 0) : 0;

$stat_batches_stmt = $conn->query("SELECT COUNT(*) AS total FROM evaluation_import_batches WHERE status = 'Imported'");
$historical_batches_count = $stat_batches_stmt ? (int)($stat_batches_stmt->fetch_assoc()['total'] ?? 0) : 0;

$stat_templates_stmt = $conn->query("SELECT COUNT(*) AS total FROM evaluation_templates WHERE status = 'Active' AND deleted_at IS NULL");
$active_templates_count = $stat_templates_stmt ? (int)($stat_templates_stmt->fetch_assoc()['total'] ?? 0) : 0;

// Fetch recent import batches
$recent_batches = [];
$rb_stmt = $conn->query("
    SELECT b.*, u.full_name AS importer_name 
    FROM evaluation_import_batches b
    LEFT JOIN users u ON b.imported_by = u.user_id
    ORDER BY b.imported_at DESC 
    LIMIT 5
");
if ($rb_stmt) {
    while ($r = $rb_stmt->fetch_assoc()) {
        $recent_batches[] = $r;
    }
}

require_once '../includes/header.php';
?>

<style>
    /* Raquel HRIS Design System Enhancements for Historical Import */
    .import-dropzone {
        border: 2px dashed #b8c8b4;
        background: #fbfdfa;
        border-radius: 16px;
        padding: 38px 24px;
        text-align: center;
        transition: all 0.25s ease;
        cursor: pointer;
        position: relative;
    }
    .import-dropzone:hover,
    .import-dropzone.drag-over {
        border-color: #082E06;
        background: #f0f7ee;
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(8, 46, 6, 0.08);
    }
    .import-dropzone-icon {
        width: 64px;
        height: 64px;
        line-height: 64px;
        margin: 0 auto 16px;
        border-radius: 50%;
        background: rgba(8, 46, 6, 0.06);
        color: #082E06;
        font-size: 28px;
        transition: all 0.25s ease;
    }
    .import-dropzone:hover .import-dropzone-icon {
        background: #082E06;
        color: #BD9414;
        transform: scale(1.08);
    }
    .selected-file-card {
        display: none;
        background: #ffffff;
        border: 1px solid #d7e4d3;
        border-radius: 12px;
        padding: 14px 18px;
        margin-top: 16px;
        animation: fadeIn 0.25s ease-in-out;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(6px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .spec-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.76rem;
        font-weight: 600;
        margin-bottom: 6px;
        margin-right: 6px;
    }
    .spec-pill.required {
        background: #fdf2e9;
        color: #b75508;
        border: 1px solid #fed7aa;
    }
    .spec-pill.optional {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #cbd5e1;
    }
    .instruction-step {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        margin-bottom: 18px;
    }
    .instruction-step:last-child {
        margin-bottom: 0;
    }
    .step-num {
        width: 28px;
        height: 28px;
        line-height: 28px;
        text-align: center;
        border-radius: 50%;
        background: #082E06;
        color: #BD9414;
        font-weight: 700;
        font-size: 0.8rem;
        flex-shrink: 0;
    }
    .step-content h6 {
        font-size: 0.88rem;
        font-weight: 700;
        color: #1a2e06;
        margin-bottom: 2px;
    }
    .step-content p {
        font-size: 0.8rem;
        color: #64748b;
        margin-bottom: 0;
        line-height: 1.45;
    }
</style>

<!-- Standard Page Hero Header -->
<div class="historical-import-page">
<div class="page-hero fadeup">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-3">
        <div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">HR Supervisor · Historical Evaluations</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-file-import me-2" style="color:#BD9414;"></i>Historical Evaluation Import</h4>
            <p class="text-white-50 small mb-0 mt-2">Bulk import legacy ratings, past performance records, and historical review scores into the evaluation repository.</p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="historical-import.php?download=template" class="btn btn-outline-light btn-sm fw-semibold rounded-pill px-3">
                <i class="fas fa-download me-1 text-warning"></i>Download CSV Template
            </a>
            <a href="evaluation-history.php" class="btn btn-light btn-sm fw-semibold rounded-pill px-3">
                <i class="fas fa-arrow-left me-1" style="color:#082E06;"></i>Back to Evaluation History
            </a>
        </div>
    </div>

    <!-- Glass Stat Cards in Hero -->
    <div class="row g-3 mt-2">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo number_format($historical_total_count); ?></div>
                        <div class="stat-label">Historical Records</div>
                    </div>
                    <i class="fas fa-database stat-icon text-white-50"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo number_format($historical_employees_count); ?></div>
                        <div class="stat-label">Employees Covered</div>
                    </div>
                    <i class="fas fa-user-check stat-icon" style="color:#28a745;"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo number_format($historical_batches_count); ?></div>
                        <div class="stat-label">Batches Processed</div>
                    </div>
                    <i class="fas fa-layer-group stat-icon" style="color:#BD9414;"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo number_format($active_templates_count); ?></div>
                        <div class="stat-label">Active Templates</div>
                    </div>
                    <i class="fas fa-clipboard-check stat-icon" style="color:#17a2b8;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo e($message_type ?? 'info'); ?> alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
        <div class="d-flex align-items-center">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : ($message_type === 'danger' ? 'times-circle' : 'info-circle')); ?> fs-4 me-3"></i>
            <div>
                <strong class="d-block mb-1"><?php echo $message_type === 'success' ? 'Operation Successful' : ($message_type === 'danger' ? 'Action Failed' : 'Notice'); ?></strong>
                <span class="small"><?php echo nl2br(e($message)); ?></span>
            </div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
<?php endif; ?>

<?php if ($import_summary): ?>
    <div class="content-card mb-4 fadeup">
        <div class="card-header">
            <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-chart-pie me-2 text-primary"></i>Import Results Summary</h6>
            <span class="badge bg-dark rounded-pill px-3">Batch #<?php echo (int) $import_summary['batch_id']; ?></span>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="p-3 rounded-3 text-center border" style="background:#f8fafc; border-color:#e2e8f0 !important;">
                        <div class="text-muted small text-uppercase fw-bold mb-1">Total Rows Scanned</div>
                        <div class="fs-2 fw-bold text-dark"><?php echo number_format((int) $import_summary['total_rows']); ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 rounded-3 text-center border" style="background:#f0fdf4; border-color:#bbf7d0 !important;">
                        <div class="text-success small text-uppercase fw-bold mb-1">Successfully Imported</div>
                        <div class="fs-2 fw-bold text-success"><?php echo number_format((int) $import_summary['successful_rows']); ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 rounded-3 text-center border" style="background:#fef2f2; border-color:#fecaca !important;">
                        <div class="text-danger small text-uppercase fw-bold mb-1">Failed / Rejected</div>
                        <div class="fs-2 fw-bold text-danger"><?php echo number_format((int) $import_summary['failed_rows']); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <!-- Left Column: Upload Dropzone Card -->
    <div class="col-12 col-lg-7">
        <div class="content-card h-100 fadeup">
            <div class="card-header">
                <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-cloud-upload-alt me-2 text-success"></i>Upload Historical CSV File</h6>
                <span class="badge bg-light text-muted border">UTF-8 Encoded</span>
            </div>
            <div class="card-body p-4">
                <form method="post" enctype="multipart/form-data" id="historicalUploadForm">
                    <?php echo csrfField(); ?>

                    <div class="import-dropzone" id="dropzoneContainer" onclick="document.getElementById('historicalCsvInput').click();">
                        <div class="import-dropzone-icon">
                            <i class="fas fa-file-csv"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">Choose CSV File or Drag & Drop Here</h6>
                        <p class="text-muted small mb-0">Standardized CSV template with historical scores and evaluation periods.</p>
                        <div class="text-secondary small mt-2" style="font-size:0.75rem;">
                            <i class="fas fa-info-circle me-1"></i>Supported format: <strong>.csv</strong> (Maximum file size: 10MB)
                        </div>
                        <input type="file" name="historical_csv" id="historicalCsvInput" class="d-none" accept=".csv,text/csv" required>
                    </div>

                    <!-- Selected File Info Banner -->
                    <div class="selected-file-card" id="selectedFileCard">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-3">
                                <div class="p-2 rounded-3 bg-success bg-opacity-10 text-success">
                                    <i class="fas fa-file-excel fs-4"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark text-truncate" style="max-width: 280px;" id="selectedFileName">filename.csv</div>
                                    <div class="text-muted small" id="selectedFileSize">0 KB</div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3" id="clearFileBtn" title="Remove selected file">
                                <i class="fas fa-times me-1"></i>Remove
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 d-flex justify-content-end gap-2">
                        <a href="evaluation-history.php" class="btn btn-light rounded-pill px-4 fw-semibold border">Cancel</a>
                        <button type="submit" name="import_action" value="preview" class="btn btn-primary rounded-pill px-4 fw-bold" id="submitImportBtn">
                            <i class="fas fa-clipboard-check me-2"></i>Validate & Preview
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: Step Guide & Schema Quick Reference -->
    <div class="col-12 col-lg-5">
        <div class="content-card h-100 fadeup">
            <div class="card-header">
                <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-book-reader me-2 text-primary"></i>Import Instructions & Format Guide</h6>
            </div>
            <div class="card-body p-4">
                <!-- 3 Step Guide -->
                <div class="instruction-step">
                    <div class="step-num">1</div>
                    <div class="step-content">
                        <h6>Download Template</h6>
                        <p>Click <strong>Download CSV Template</strong> to get the verified header columns. Do not rename the column titles.</p>
                    </div>
                </div>

                <div class="instruction-step">
                    <div class="step-num">2</div>
                    <div class="step-content">
                        <h6>Fill Evaluation Data</h6>
                        <p>Ensure <code>employee_code</code> matches registered employees and dates follow standard <code>YYYY-MM-DD</code> format.</p>
                    </div>
                </div>

                <div class="instruction-step">
                    <div class="step-num">3</div>
                    <div class="step-content">
                        <h6>Upload & Review</h6>
                        <p>Upload the CSV to check every row. Nothing is saved until you review the preview and confirm the import.</p>
                    </div>
                </div>

                <hr class="my-4" style="border-color: var(--glass-border);">

                <!-- Required & Optional Columns -->
                <h6 class="fw-bold text-dark small text-uppercase mb-2" style="letter-spacing:0.5px;">Required Columns</h6>
                <div class="mb-3">
                    <span class="spec-pill required"><i class="fas fa-asterisk me-1" style="font-size:0.6rem;"></i>employee_code</span>
                    <span class="spec-pill required"><i class="fas fa-asterisk me-1" style="font-size:0.6rem;"></i>evaluation_type</span>
                    <span class="spec-pill required"><i class="fas fa-asterisk me-1" style="font-size:0.6rem;"></i>evaluation_period_start</span>
                    <span class="spec-pill required"><i class="fas fa-asterisk me-1" style="font-size:0.6rem;"></i>evaluation_period_end</span>
                    <span class="spec-pill required"><i class="fas fa-asterisk me-1" style="font-size:0.6rem;"></i>total_score</span>
                </div>

                <h6 class="fw-bold text-dark small text-uppercase mb-2" style="letter-spacing:0.5px;">Optional Columns</h6>
                <div>
                    <span class="spec-pill optional">kra_subtotal</span>
                    <span class="spec-pill optional">behavior_average</span>
                    <span class="spec-pill optional">performance_level</span>
                    <span class="spec-pill optional">template_name</span>
                    <span class="spec-pill optional">legacy_reference</span>
                    <span class="spec-pill optional">employee_comments</span>
                    <span class="spec-pill optional">supervisor_comments</span>
                    <span class="spec-pill optional">manager_comments</span>
                    <span class="spec-pill optional">kra_scores</span>
                    <span class="spec-pill optional">behavior_scores</span>
                </div>
                <p class="small text-muted mt-3 mb-0">For detailed records, enter ratings in form order separated by semicolons: all KRA items in <code>kra_scores</code> and all eight Core Behaviors in <code>behavior_scores</code>. Summary rows leave both blank. Specify <code>template_name</code> when more than one form matches.</p>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($preview_rows)): ?>
    <!-- Validation Results Table -->
    <div class="content-card mb-4 fadeup">
        <div class="card-header">
            <div class="d-flex align-items-center gap-2">
                <i class="fas fa-clipboard-check text-primary fs-5"></i>
                <h6 class="mb-0 fw-bold text-dark">Validation & Processing Results</h6>
            </div>
            <span class="badge bg-secondary rounded-pill px-3"><?php echo count($preview_rows); ?> Rows Checked</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead style="background:#f8fafc; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px; color:#475569;">
                        <tr>
                            <th class="ps-4" style="width:70px;">Row</th>
                            <th>Employee</th>
                            <th>Evaluation Type</th>
                            <th>Evaluation Period</th>
                            <th>Score</th>
                            <th>Validation Status</th>
                            <th class="pe-4">Validation Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($preview_rows as $row): ?>
                            <tr style="<?php echo $row['valid'] ? '' : 'background-color:#fffdfd;'; ?>">
                                <td class="ps-4 fw-bold text-secondary">#<?php echo (int) $row['row_number']; ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo e($row['employee_code'] ?: '—'); ?></div>
                                    <?php if (!empty($row['employee_name'])): ?>
                                        <div class="small text-muted"><?php echo e($row['employee_name']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row['template_name'])): ?><div class="small text-muted">Form: <?php echo e($row['template_name']); ?></div><?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $type_badges = [
                                        'Annual' => 'bg-primary-subtle text-primary border border-primary-subtle',
                                        'Quarterly' => 'bg-info-subtle text-info-emphasis border border-info-subtle',
                                        'Initial' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                                        'Final' => 'bg-success-subtle text-success-emphasis border border-success-subtle',
                                    ];
                                    $badge_cls = $type_badges[$row['evaluation_type']] ?? 'bg-secondary-subtle text-secondary border border-secondary-subtle';
                                    ?>
                                    <span class="badge rounded-pill <?php echo $badge_cls; ?> px-2 py-1"><?php echo e($row['evaluation_type'] ?: 'Unknown'); ?></span>
                                </td>
                                <td class="small text-secondary">
                                    <i class="far fa-calendar-alt me-1 text-muted"></i>
                                    <?php echo e($row['evaluation_period_start'] ?: '—') . ' &rarr; ' . e($row['evaluation_period_end'] ?: '—'); ?>
                                </td>
                                <td>
                                    <span class="fw-bold text-dark"><?php echo e($row['total_score'] ?: '—'); ?></span>
                                    <?php if (!empty($row['performance_level'])): ?>
                                        <div class="small text-muted" style="font-size:0.72rem;"><?php echo e($row['performance_level']); ?></div>
                                    <?php endif; ?>
                                    <div class="small text-muted"><?php echo e($row['import_mode']); ?><?php if ($row['kra_subtotal'] !== '' && $row['behavior_average'] !== ''): ?> · KRA <?php echo e(number_format((float)$row['kra_subtotal'], 2)); ?> · Behavior <?php echo e(number_format((float)$row['behavior_average'], 2)); ?><?php endif; ?></div>
                                </td>
                                <td>
                                    <?php if ($row['valid']): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1">
                                            <i class="fas fa-check-circle me-1"></i>Valid
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-1">
                                            <i class="fas fa-times-circle me-1"></i>Rejected
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-4">
                                    <?php if (!empty($row['errors'])): ?>
                                        <ul class="mb-0 ps-3 small text-danger" style="list-style-type:circle;">
                                            <?php foreach ($row['errors'] as $error): ?>
                                                <li><?php echo e($error); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <span class="small text-success"><i class="fas fa-check me-1"></i><?php echo $import_summary ? 'Imported.' : 'Ready for import.'; ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($historical_preview_ready && !$import_summary): ?>
            <div class="card-footer d-flex flex-wrap align-items-center justify-content-between gap-2 p-3">
                <span class="small text-muted">All <?php echo count($preview_rows); ?> rows are valid. Confirming saves them together as historical records.</span>
                <form method="post" class="m-0"><?php echo csrfField(); ?><input type="hidden" name="import_action" value="confirm"><input type="hidden" name="preview_token" value="<?php echo e($historical_preview_token); ?>"><button type="submit" class="btn btn-success fw-bold"><i class="fas fa-check-circle me-1"></i>Confirm Import</button></form>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Recent Import Batches Audit History -->
<div class="content-card fadeup">
    <div class="card-header">
        <div class="d-flex align-items-center gap-2">
            <i class="fas fa-history text-secondary"></i>
            <h6 class="mb-0 fw-bold text-dark">Recent Import Batches</h6>
        </div>
        <span class="text-muted small">Latest historical imports</span>
    </div>
    <div class="card-body p-0">
        <?php if (!empty($recent_batches)): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead style="background:#f8fafc; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px; color:#475569;">
                        <tr>
                            <th class="ps-4">Batch ID</th>
                            <th>Uploaded File</th>
                            <th>Imported By</th>
                            <th>Date & Time</th>
                            <th>Total Rows</th>
                            <th>Success</th>
                            <th>Failed</th>
                            <th class="pe-4">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_batches as $batch): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-primary">#<?php echo (int) $batch['import_batch_id']; ?></td>
                                <td>
                                    <i class="fas fa-file-csv text-muted me-1"></i>
                                    <span class="fw-semibold text-dark"><?php echo e($batch['uploaded_filename']); ?></span>
                                </td>
                                <td>
                                    <i class="fas fa-user-circle text-muted me-1"></i>
                                    <span><?php echo e($batch['importer_name'] ?? 'System User'); ?></span>
                                </td>
                                <td class="small text-secondary">
                                    <?php echo date('M d, Y h:i A', strtotime($batch['imported_at'])); ?>
                                </td>
                                <td><span class="fw-semibold"><?php echo number_format((int) $batch['total_rows']); ?></span></td>
                                <td><span class="badge bg-success-subtle text-success rounded-pill px-2"><?php echo number_format((int) $batch['successful_rows']); ?></span></td>
                                <td>
                                    <?php if ((int)$batch['failed_rows'] > 0): ?>
                                        <span class="badge bg-danger-subtle text-danger rounded-pill px-2"><?php echo number_format((int) $batch['failed_rows']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-4">
                                    <span class="badge bg-success rounded-pill px-3 py-1"><?php echo e($batch['status']); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-inbox text-muted opacity-25" style="font-size:3rem;"></i>
                <h6 class="fw-bold text-secondary mt-3 mb-1">No Historical Batches Recorded Yet</h6>
                <p class="text-muted small mb-0">When you import historical evaluations using a CSV file, the batch history will appear here.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropzone = document.getElementById('dropzoneContainer');
    const fileInput = document.getElementById('historicalCsvInput');
    const selectedFileCard = document.getElementById('selectedFileCard');
    const selectedFileName = document.getElementById('selectedFileName');
    const selectedFileSize = document.getElementById('selectedFileSize');
    const clearFileBtn = document.getElementById('clearFileBtn');
    const submitBtn = document.getElementById('submitImportBtn');

    function formatBytes(bytes, decimals = 1) {
        if (!+bytes) return '0 Bytes';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`;
    }

    function handleFile(file) {
        if (!file) return;
        if (!file.name.toLowerCase().endsWith('.csv')) {
            alert('Please select a valid CSV file (.csv).');
            fileInput.value = '';
            selectedFileCard.style.display = 'none';
            return;
        }

        selectedFileName.textContent = file.name;
        selectedFileSize.textContent = formatBytes(file.size);
        selectedFileCard.style.display = 'block';
    }

    fileInput.addEventListener('change', function() {
        if (this.files && this.files.length > 0) {
            handleFile(this.files[0]);
        }
    });

    clearFileBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        fileInput.value = '';
        selectedFileCard.style.display = 'none';
    });

    // Drag and drop event handlers
    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.add('drag-over');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('drag-over');
        }, false);
    });

    dropzone.addEventListener('drop', function(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files && files.length > 0) {
            fileInput.files = files;
            handleFile(files[0]);
        }
    }, false);
});
</script>

</div>

