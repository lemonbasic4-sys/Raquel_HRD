<?php
$page_title = 'Evaluation History';
require_once '../includes/session-check.php';
checkRole(['HR Supervisor']);
require_once '../includes/functions.php';
require_once '../includes/hr-evaluation-history-data.php';
ensureHistoricalImportSchema($conn);
if (isset($_GET['detail_id'])) {
    $detailId=filter_var($_GET['detail_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $row=$detailId ? hrHistoryDetail($conn,'HR Supervisor',(int)$detailId) : null;
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: private, no-store');
    if (!$row) { http_response_code(404); exit('This evaluation history record is unavailable.'); }
    define('HR_HISTORY_DETAIL_RENDER',true);
    require __DIR__.'/../includes/supervisor-history-modal.php';
    exit;
}
$historyPage=hrHistoryPage($conn,'HR Supervisor',$_GET);
$all_history=$historyPage['rows'];
$total_c=$historyPage['totals']['All'];
$approved_c=$historyPage['totals']['Approved'];
$imported_c=$historyPage['totals']['Imported'];
$rejected_c=$historyPage['totals']['Rejected'];
$returned_c=$historyPage['totals']['Returned'];
$existing_departments=$historyPage['options']['department'];
$existing_templates=$historyPage['options']['template'];
$show_progressive_notice=hasOpenProgressiveEvaluationCohort($conn);
require_once '../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/evaluation-history-table.css?v=<?php echo filemtime(__DIR__.'/../assets/css/evaluation-history-table.css'); ?>">
<div class="evaluation-history-page supervisor-evaluation-history-page">
<div class="page-hero fadeup supervisor-history-hero">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
        <div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">HR Supervisor · Evaluation Records</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-history me-2" style="color:#BD9414;"></i>Evaluation History</h4>
            <p class="text-white-50 small mb-0 mt-2">Review completed performance evaluations across employees, templates, and evaluation periods.</p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span style="color:rgba(255,255,255,.6);font-size:.8rem;">
                <i class="fas fa-sync-alt me-1"></i>Data as of <?php echo date('F d, Y'); ?>
            </span>
            <a href="historical-import.php" class="btn btn-light btn-sm fw-semibold rounded-pill">
                <i class="fas fa-file-import me-1"></i>Import Historical
            </a>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo $total_c; ?></div>
                        <div class="stat-label">Total Evaluations</div>
                    </div>
                    <i class="fas fa-clipboard-list stat-icon text-white-50"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo $approved_c; ?></div>
                        <div class="stat-label">Approved</div>
                    </div>
                    <i class="fas fa-check-circle stat-icon" style="color:#28a745;"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo $rejected_c; ?></div>
                        <div class="stat-label">Rejected</div>
                    </div>
                    <i class="fas fa-times-circle stat-icon" style="color:#dc3545;"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo $returned_c; ?></div>
                        <div class="stat-label">Returned</div>
                    </div>
                    <i class="fas fa-undo-alt stat-icon" style="color:#ffc107;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($show_progressive_notice): ?>
<div class="alert alert-warning d-flex align-items-start gap-2 mt-3" role="status">
    <i class="fas fa-hourglass-half mt-1"></i>
    <div><strong>Scores are provisional for active evaluation cycles.</strong> At least one employee is still in evaluation for a department, template, and period with approved results. When the remaining evaluation is finalized, the progressive Shared Behavior Score and final scores for that completed cohort may be updated. Individual Behavior remains each employee’s own rating.</div>
</div>
<?php endif; ?>

<style>
.history-detail-modal .modal-dialog{margin:auto;max-width:min(1120px,calc(100vw - 32px));width:calc(100vw - 32px)}
.history-detail-modal .modal-content{height:min(820px,calc(100dvh - 32px));max-height:calc(100dvh - 32px);overflow:hidden}
.history-detail-modal .modal-header{flex:0 0 auto}
.history-detail-modal .modal-body{min-height:0}
@media(max-width:767.98px){.history-detail-modal .modal-dialog{margin:8px;max-width:calc(100vw - 16px);width:calc(100vw - 16px)}.history-detail-modal .modal-content{height:calc(100dvh - 16px);max-height:calc(100dvh - 16px)}}
.supervisor-history-hero .stat-card{height:100%;}
.history-review-tabs{align-items:end;border-bottom:1px solid #dce4d8;display:flex;gap:8px;margin:0 0 14px;}
.history-review-tab{align-items:center;background:#f1f3f2;border:1px solid transparent;border-bottom:0;border-radius:10px 10px 0 0;color:#64748b;display:flex;gap:10px;min-width:205px;padding:12px 16px;text-align:left;}
.history-review-tab.active{background:#fff;border-color:#dbe5d8;box-shadow:inset 0 3px #2e7d32;color:#173b1b;font-weight:700;}
.history-review-tab.probationary.active{box-shadow:inset 0 3px #3730a3;}
.history-review-tab .tab-count{align-items:center;background:#fff;border-radius:50%;display:inline-flex;font-size:.72rem;height:26px;justify-content:center;margin-left:auto;min-width:26px;}
.history-section-heading{background:#fff;border:1px solid #edf0eb;border-bottom:0;border-radius:12px 12px 0 0;padding:14px 16px 8px;}
.supervisor-history-filters{background:#fff;border:1px solid #edf0eb;border-bottom:0;padding:12px 16px;}
.hist-status-pills{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;}
.hist-status-pill{background:#f1f3f2;border:1px solid transparent;border-radius:999px;color:#667085;font-size:.78rem;font-weight:700;padding:9px 14px;transition:.15s;}
.hist-status-pill:hover{background:#e8ede0;}
.hist-status-pill.active-all{background:#082e06;border-color:#082e06;color:#fff;}
.hist-status-pill.active-approved{background:#d1fae5;border-color:#6ee7b7;color:#065f46;}
.hist-status-pill.active-imported{background:#e0f2fe;border-color:#7dd3fc;color:#075985;}
.hist-status-pill.active-rejected{background:#fee2e2;border-color:#fca5a5;color:#991b1b;}
.hist-status-pill.active-returned{background:#fef3c7;border-color:#fcd34d;color:#92400e;}
.history-table-wrap{background:#fff;border:1px solid #edf0eb;border-radius:0 0 12px 12px;overflow:auto;}
.hist-card-avatar{align-items:center;background:#f1f5f1;border:1px solid #e2e8e0;border-radius:50%;display:flex;flex:0 0 34px;height:34px;justify-content:center;overflow:hidden;width:34px;}
.hist-card-avatar img{height:100%;object-fit:cover;width:100%;}
@media(max-width:767.98px){.history-review-tabs{gap:4px}.history-review-tab{flex:1;min-width:0;padding:10px}.supervisor-history-filters .form-select{max-width:none!important;width:100%}}
</style>

<div class="history-review-tabs fadeup fadeup-1" role="tablist" aria-label="Evaluation type">
    <button class="history-review-tab probationary active" type="button" role="tab" aria-selected="true" onclick="setSupervisorHistoryType('probationary', this)"><i class="fas fa-users"></i><span><strong>Probationary Evaluation</strong><small class="d-block text-muted fw-normal">Initial and Final evaluations</small></span><span class="tab-count" id="supervisorProbationaryCount">0</span></button>
    <button class="history-review-tab regular" type="button" role="tab" aria-selected="false" onclick="setSupervisorHistoryType('regular', this)"><i class="fas fa-user"></i><span><strong>Regular Employee</strong><small class="d-block text-muted fw-normal">Annual team evaluations</small></span><span class="tab-count" id="supervisorRegularCount">0</span></button>
</div>
<section class="history-section-heading"><h5 class="fw-bold mb-1" id="supervisorHistoryHeading">Probationary Evaluation</h5><div class="text-muted small" id="supervisorHistorySubheading">Initial and Final employee evaluations</div></section>
<div class="supervisor-history-filters fadeup fadeup-1">
    <div class="hist-status-pills" role="group" aria-label="Filter by evaluation status">
        <button class="hist-status-pill active-all" onclick="filterByStatus('All', this)"><i class="fas fa-list me-1"></i>All <span data-history-status-count="All">(0)</span></button>
        <button class="hist-status-pill" onclick="filterByStatus('Approved', this)"><i class="fas fa-check-circle me-1"></i>Approved <span data-history-status-count="Approved">(0)</span></button>
        <button class="hist-status-pill" onclick="filterByStatus('Imported', this)"><i class="fas fa-file-import me-1"></i>Imported <span data-history-status-count="Imported">(0)</span></button>
        <button class="hist-status-pill" onclick="filterByStatus('Rejected', this)"><i class="fas fa-times-circle me-1"></i>Rejected <span data-history-status-count="Rejected">(0)</span></button>
        <button class="hist-status-pill" onclick="filterByStatus('Returned', this)"><i class="fas fa-rotate-left me-1"></i>Returned <span data-history-status-count="Returned">(0)</span></button>
    </div>
    <div class="history-filter-grid">
        <div class="history-filter-field"><label class="text-muted small fw-semibold" for="supervisorBranchFilter"><i class="fas fa-code-branch me-1" aria-hidden="true"></i>Branch:</label><select class="form-select form-select-sm" id="supervisorBranchFilter"><option value="All">All Branches</option></select></div>
        <div class="history-filter-field"><label class="text-muted small fw-semibold" for="supervisorDeptFilter"><i class="fas fa-building me-1"></i>Department</label><select class="form-select form-select-sm" id="supervisorDeptFilter"><option value="All">All Departments</option><?php foreach ($existing_departments as $dept_name): ?><option value="<?php echo e($dept_name); ?>"><?php echo e($dept_name); ?></option><?php endforeach; ?></select></div>
        <div class="history-filter-field"><label class="text-muted small fw-semibold" for="supervisorTemplateFilter"><i class="fas fa-file-alt me-1"></i>Template:</label><select class="form-select form-select-sm" id="supervisorTemplateFilter"><option value="All">All Templates</option><?php foreach ($existing_templates as $tmpl_name): ?><option value="<?php echo e($tmpl_name); ?>"><?php echo e($tmpl_name); ?></option><?php endforeach; ?></select></div>
        <div class="history-filter-field"><label class="text-muted small fw-semibold" for="supervisorEvalTypeFilter"><i class="fas fa-clipboard-check me-1"></i>Evaluation type</label><select class="form-select form-select-sm" id="supervisorEvalTypeFilter"><option value="All">All Types</option><option value="Annual">Annual</option><option value="Quarterly">Quarterly</option><option value="Initial">Initial</option><option value="Final">Final</option></select></div>
        <div class="input-group input-group-sm history-filter-search"><span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span><input type="search" class="form-control border-start-0 ps-0" id="customSearchEval" placeholder="Search employee, position..."></div>
    </div>
</div>
<div class="history-table-wrap hr-history-table-container fadeup fadeup-2">
            <table class="table history-table hr-history-table" id="evalTable">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-3">Employee Name</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Form Code</th>
                        <th>Overall Rating</th>
                        <th>Status</th>
                        <th>Current Reviewer</th><th>Date Submitted</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($all_history)): ?>
                        <tr class="no-results-row text-center"><td colspan="9" class="text-muted py-5"><i class="fas fa-history fa-3x mb-3 d-block opacity-25"></i>No evaluations match these filters.</td></tr>
                    <?php else: ?>
                        <?php foreach ($all_history as $row): ?>
                            <tr class="eval-row" data-type="<?php echo in_array($row['package_evaluation_type'] ?? '', ['Initial', 'Final'], true) ? 'probationary' : 'regular'; ?>"
                                data-status="<?php echo e($row['status']); ?>"
                                data-department="<?php echo e($row['department_name'] ?? ''); ?>"
             data-branch="<?php echo e($row['branch_name'] ?? 'Unassigned'); ?>"
                                data-template="<?php echo e($row['template_name'] ?? ''); ?>"
                                data-evaluation-type="<?php echo e($row['package_evaluation_type'] ?? 'Annual'); ?>"
                                data-search="<?php echo strtolower(e($row['employee_name']) . ' ' . e($row['department_name'] ?? '') . ' ' . e($row['template_name']) . ' ' . e($row['job_title']) . ' ' . e($row['form_code'] ?? '')); ?>">
                                <td><div class="employee-cell">
                                    <div class="hist-card-avatar"><img src="<?php echo e(getEmployeeAvatar($row['profile_picture'] ?? '')); ?>" alt="<?php echo e($row['employee_name']); ?>"></div>
                                    <div class="employee-copy"><strong><?php echo e($row['employee_name']); ?></strong><small>ID <?php echo e($row['employee_id']); ?></small></div>
                                </div></td>
                                <td><?php echo e($row['department_name'] ?? 'N/A'); ?></td>
                                <td><?php echo e($row['job_title'] ?? 'N/A'); ?></td><td class="history-form-code"><?php echo e($row['form_code'] ?: 'N/A'); ?></td>
                                <td class="text-center">
                                    <?php
                                    $h_score = (float)($row['total_score'] ?? 0);
                                    $h_perf = $row['performance_level'] ?? '';
                                    if ($h_score > 0 && (empty($h_perf) || $h_perf === '0')) {
                                        $h_perf = getPerformanceLevel($h_score);
                                    }
                                    $h_badge = getPerformanceBadgeClass($h_perf);
                                    ?>
                                    <strong class="d-block"><?php echo $h_score > 0 ? number_format($h_score, 2) : '—'; ?></strong>
                                    <div>
                                        <?php if ($h_perf): ?>
                                            <?php $rating_class = str_contains($h_badge, 'success') ? 'rating-excellent' : (str_contains($h_badge, 'info') ? 'rating-exceeds' : (str_contains($h_badge, 'warning') ? 'rating-meets' : (str_contains($h_badge, 'danger') ? 'rating-needs' : ''))); ?>
                                            <span class="rating-pill <?php echo $rating_class; ?>"><?php echo e($h_perf); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Unscored</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($row['package_id'])): ?>
                                        <?php echo renderOrganizationPipelineBadge($conn, (int)$row['package_id']); ?>
                                    <?php else: ?>
                                        <span class="hist-status-badge <?php echo e(strtolower($row['status'])); ?>"><?php echo e($row['status']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><div class="current-reviewer"><?php echo e($row['status'] === 'Imported' ? 'Historical import' : (($row['approved_by_name'] ?? '') ?: (($row['endorsed_by_name'] ?? '') ?: 'Completed'))); ?></div></td>
                                <td><small><?php echo formatDate($row['submitted_date'] ?? $row['updated_at']); ?></small></td>
                                <td>
                                    <button class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold shadow-sm" data-history-detail="<?php echo (int)$row['evaluation_id']; ?>">
                                        <i class="fas fa-eye me-1"></i>View
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
    </div>
<?php require __DIR__.'/../includes/hr-history-pagination.php'; ?>

<style>
    .badge-audit {
        background: rgba(255, 193, 7, 0.15);
        color: #d39e00;
        border: 1px solid rgba(255, 193, 7, 0.4);
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        backdrop-filter: blur(4px);
        margin-left: 5px;
        vertical-align: middle;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .badge-audit:hover {
        background: rgba(255, 193, 7, 0.25);
        transform: translateY(-1px);
    }
    .badge-audit.mgr {
        background: rgba(23, 162, 184, 0.15);
        color: #17a2b8;
        border: 1px solid rgba(23, 162, 184, 0.4);
    }
    .badge-audit.mgr:hover {
        background: rgba(23, 162, 184, 0.25);
    }
    .score-input {
        transition: all 0.3s ease;
        background: rgba(255, 255, 255, 0.95);
        border: 1px solid #ced4da;
    }
    .score-input:focus {
        background: #fff;
        border-color: #BD9414;
        box-shadow: 0 0 0 0.2rem rgba(189, 148, 20, 0.25);
    }
    .status-stepper .stepper-line {
        position: absolute;
        top: 15px;
        left: 10%;
        right: 10%;
        height: 2px;
        background: #e9ecef;
        z-index: 0;
    }
    .step-item .step-icon {
        width: 32px;
        height: 32px;
        line-height: 32px;
        background: #fff;
        border: 2px solid #e9ecef;
        border-radius: 50%;
        margin: 0 auto;
        color: #adb5bd;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .step-item.active .step-icon {
        background: var(--primary-blue);
        border-color: var(--primary-blue);
        color: #fff;
    }
    .step-item.active .step-label {
        color: var(--primary-blue);
    }
    .x-small { font-size: 0.65rem !important; }
@media print {
    body * {
        visibility: hidden;
    }
    .content-card, .content-card * {
        visibility: visible;
    }
    .content-card {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        box-shadow: none;
        border: none;
    }
    .search-box, .btn, #paginationWrapper, th:last-child, td:last-child {
        display: none !important;
    }
}
</style>

</div>
<script>
window.hrHistoryState = <?php $historyClientState=$historyPage; unset($historyClientState['rows']); $historyClientState['role']='HR Supervisor'; echo json_encode($historyClientState, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE); ?>;
function toggleEditRatings(evalId, cancel = false) {
    const modal = document.querySelector(`#reviewModal${evalId}`);
    if (!modal) return;

    const displays = modal.querySelectorAll('.score-display');
    const inputs = modal.querySelectorAll('.score-input');
    const badgeAudits = modal.querySelectorAll('.badge-audit');
    const editBtn = modal.querySelector('.btn-edit-ratings');
    const saveBtn = modal.querySelector('.btn-save-ratings');
    const cancelBtn = modal.querySelector('.btn-cancel-ratings');

    if (cancel) {
        inputs.forEach(input => {
            input.value = input.getAttribute('data-original-val');
            input.classList.remove('is-invalid');
        });
    }

    const isEditing = inputs[0].classList.contains('d-none');

    if (isEditing) {
        displays.forEach(d => d.classList.add('d-none'));
        badgeAudits.forEach(b => b.classList.add('d-none'));
        inputs.forEach(i => i.classList.remove('d-none'));
        
        editBtn.classList.add('d-none');
        saveBtn.classList.remove('d-none');
        cancelBtn.classList.remove('d-none');
    } else {
        displays.forEach(d => d.classList.remove('d-none'));
        badgeAudits.forEach(b => b.classList.remove('d-none'));
        inputs.forEach(i => i.classList.add('d-none'));
        
        editBtn.classList.remove('d-none');
        saveBtn.classList.add('d-none');
        cancelBtn.classList.add('d-none');
    }
}

function saveRatings(evalId) {
    const modal = document.querySelector(`#reviewModal${evalId}`);
    if (!modal) return;

    const inputs = modal.querySelectorAll('.score-input');
    const ratings = {};
    let hasError = false;

    inputs.forEach(input => {
        const val = parseFloat(input.value);
        const scoreId = input.getAttribute('data-score-id');
        if (isNaN(val) || val < 1.00 || val > 4.00) {
            hasError = true;
            input.classList.add('is-invalid');
        } else {
            input.classList.remove('is-invalid');
            ratings[scoreId] = val;
        }
    });

    if (hasError) {
        alert('Please enter valid ratings between 1.00 and 4.00.');
        return;
    }

    const saveBtn = modal.querySelector('.btn-save-ratings');
    const originalBtnText = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';

    const formData = new FormData();
    formData.append('evaluation_id', evalId);
    for (const [key, value] of Object.entries(ratings)) {
        formData.append(`ratings[${key}]`, value);
    }

    fetch('ajax/save-rating.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message || 'An error occurred while saving ratings.');
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalBtnText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An unexpected error occurred.');
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalBtnText;
    });
}


</script>
<script src="<?php echo BASE_URL; ?>/assets/js/hr-evaluation-history.js?v=<?php echo filemtime(__DIR__.'/../assets/js/hr-evaluation-history.js'); ?>" defer></script>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/hr-history-details.css?v=<?php echo filemtime(__DIR__.'/../assets/css/hr-history-details.css'); ?>">
<script src="<?php echo BASE_URL; ?>/assets/js/hr-history-details.js?v=<?php echo filemtime(__DIR__.'/../assets/js/hr-history-details.js'); ?>"></script>
<?php require_once '../includes/footer.php'; ?>
