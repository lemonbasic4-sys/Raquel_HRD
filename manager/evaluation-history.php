<?php
$page_title = 'Evaluation History';
require_once '../includes/session-check.php';
checkRole(['HR Manager']);
require_once '../includes/functions.php';
require_once '../includes/hr-evaluation-history-data.php';
ensureHistoricalImportSchema($conn);
if (isset($_GET['detail_id'])) {
    $detailId=filter_var($_GET['detail_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $row=$detailId ? hrHistoryDetail($conn,'HR Manager',(int)$detailId) : null;
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: private, no-store');
    if (!$row) { http_response_code(404); exit('This evaluation history record is unavailable.'); }
    define('HR_HISTORY_DETAIL_RENDER',true);
    require __DIR__.'/../includes/manager-history-modal.php';
    exit;
}
$historyPage=hrHistoryPage($conn,'HR Manager',$_GET);
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
<div class="evaluation-history-page">
<div class="page-hero fadeup">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
        <div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">HR Manager · Evaluations</div>
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
                    <i class="fas fa-file-alt stat-icon text-white-50"></i>
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
                    <i class="fas fa-undo stat-icon" style="color:#BD9414;"></i>
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
/* ── History Filter Bar ───────────────────────────── */
.hist-filter-bar {
    background: #fff;
    border: 1px solid #eef2e8;
    border-radius: 14px;
    box-shadow: 0 4px 18px rgba(12,32,8,.05);
    padding: 14px 18px;
    margin-bottom: 16px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.hist-status-pills {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}
.hist-status-pill {
    padding: 5px 16px;
    border-radius: 999px;
    font-size: .78rem;
    font-weight: 700;
    cursor: pointer;
    border: 2px solid transparent;
    transition: all .2s ease;
    background: #f4f6f0;
    color: #6c757d;
}
.hist-status-pill:hover { background: #e8ede0; }
.hist-status-pill.active-all    { background: var(--primary-blue); color: #fff; border-color: var(--primary-blue); }
.hist-status-pill.active-approved { background: #d1fae5; color: #065f46; border-color: #6ee7b7; }
.hist-status-pill.active-imported { background: #e8f1e5; color: #24592a; border-color: #a8c99e; }
.hist-status-pill.active-rejected { background: #fee2e2; color: #991b1b; border-color: #fca5a5; }
.hist-status-pill.active-returned { background: #fef3c7; color: #92400e; border-color: #fcd34d; }
.hist-status-pill.active-pending { background: #dbeafe; color: #1d4ed8; border-color: #93c5fd; }

.history-review-tabs { align-items: end; border-bottom: 1px solid #dce4d8; display:flex; gap:8px; margin:0 0 14px; }
.history-review-tab { align-items:center; background:#f1f3f2; border:1px solid transparent; border-bottom:0; border-radius:10px 10px 0 0; color:#64748b; display:flex; gap:10px; min-width:205px; padding:12px 16px; text-align:left; }
.history-review-tab.active { background:#fff; border-color:#dbe5d8; box-shadow:inset 0 3px #2e7d32; color:#173b1b; font-weight:700; }
.history-review-tab.probationary.active { box-shadow:inset 0 3px #3730a3; }
.history-review-tab .tab-count { align-items:center; background:#fff; border-radius:50%; display:inline-flex; font-size:.72rem; height:26px; justify-content:center; margin-left:auto; min-width:26px; }
.history-section-heading { background:#fff; border:1px solid #edf0eb; border-bottom:0; border-radius:12px 12px 0 0; padding:14px 16px 8px; }
.history-table-wrap { background:#fff; border:1px solid #edf0eb; border-radius:0 0 12px 12px; overflow:auto; }
.history-table { margin:0; min-width:940px; }
.history-table thead th { background:#f0f4ee; border-bottom:1px solid #e1e7df; color:#536052; font-size:.68rem; padding:10px 9px; text-transform:uppercase; white-space:nowrap; }
.history-table tbody td { border-color:#edf0eb; font-size:.76rem; padding:9px; vertical-align:middle; }
.history-table .employee-cell { align-items:center; display:flex; gap:8px; min-width:165px; }
.history-table .hist-card-avatar { height:34px; width:34px; }
.history-table .employee-copy { min-width:0; }
.history-table .employee-copy strong, .history-table .employee-copy small { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.history-table .employee-copy strong { color:#253425; font-size:.75rem; }
.history-table .employee-copy small { color:#94a3b8; font-size:.64rem; }
.history-table .current-reviewer { color:#475569; font-size:.71rem; }
.history-period { color:#64748b; font-size:.72rem; }
.history-table .rating-pill { border-radius:999px; display:inline-block; font-size:.68rem; font-weight:700; padding:4px 9px; }
.history-table .rating-excellent { background:#dff7e8; color:#166534; }
.history-table .rating-exceeds { background:#dbeafe; color:#1d4ed8; }
.history-table .rating-meets { background:#fef3c7; color:#92400e; }
.history-table .rating-needs { background:#fee2e2; color:#991b1b; }
.history-table .history-empty-row { color:#94a3b8; padding:40px !important; text-align:center; }

/* ── History Cards ───────────────────────────────── */
.history-review-tabs { align-items:end; border-bottom:1px solid #dce4d8; display:flex; gap:8px; margin:0 0 14px; }
.history-review-tab { align-items:center; background:#f1f3f2; border:1px solid transparent; border-bottom:0; border-radius:10px 10px 0 0; color:#64748b; display:flex; gap:10px; min-width:205px; padding:12px 16px; text-align:left; }
.history-review-tab.active { background:#fff; border-color:#dbe5d8; box-shadow:inset 0 3px #2e7d32; color:#173b1b; font-weight:700; }
.history-review-tab.probationary.active { box-shadow:inset 0 3px #3730a3; }
.history-review-tab .tab-count { align-items:center; background:#fff; border-radius:50%; display:inline-flex; font-size:.72rem; height:26px; justify-content:center; margin-left:auto; min-width:26px; }
.history-section-heading { background:#fff; border:1px solid #edf0eb; border-bottom:0; border-radius:12px 12px 0 0; padding:14px 16px 8px; }
.history-table-wrap { background:#fff; border:1px solid #edf0eb; border-radius:0 0 12px 12px; overflow:auto; }
.history-table { margin:0; min-width:940px; }
.history-table thead th { background:#f0f4ee; border-bottom:1px solid #e1e7df; color:#536052; font-size:.68rem; padding:10px 9px; text-transform:uppercase; white-space:nowrap; }
.history-table tbody td { border-color:#edf0eb; font-size:.76rem; padding:9px; vertical-align:middle; }
.history-table .employee-cell { align-items:center; display:flex; gap:8px; min-width:165px; }
.history-table .hist-card-avatar { height:34px; width:34px; }
.history-table .employee-copy { min-width:0; }
.history-table .employee-copy strong,.history-table .employee-copy small { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.history-table .employee-copy strong { color:#253425; font-size:.75rem; }
.history-table .employee-copy small,.current-reviewer { color:#64748b; font-size:.68rem; }
.history-table .rating-pill { border-radius:999px; display:inline-block; font-size:.65rem; font-weight:700; padding:4px 9px; }
.history-table .rating-excellent { background:#dff7e8; color:#166534; }
.history-table .rating-exceeds { background:#dbeafe; color:#1d4ed8; }
.history-table .rating-meets { background:#fef3c7; color:#92400e; }
.history-table .rating-needs { background:#fee2e2; color:#991b1b; }
.history-table .history-empty-row { color:#94a3b8; padding:40px !important; text-align:center; }
.hist-card-list { display: flex; flex-direction: column; gap: 10px; }
.hist-card {
    background: #fff;
    border: 1px solid #eef2e8;
    border-radius: 14px;
    padding: 16px 20px;
    display: grid;
    grid-template-columns: 40px minmax(0,2fr) minmax(0,1.5fr) 130px minmax(125px,max-content) max-content;
    align-items: center;
    gap: 16px;
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
    text-decoration: none;
    color: inherit;
    position: relative;
    overflow: hidden;
}
.hist-card > * { min-width: 0; }
.hist-card::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 4px;
    border-radius: 14px 0 0 14px;
    background: #e2e8f0;
    transition: background .2s ease;
}
.hist-card[data-status="Approved"]::before  { background: #22c55e; }
.hist-card[data-status="Rejected"]::before  { background: #ef4444; }
.hist-card[data-status="Returned"]::before  { background: #f59e0b; }
.hist-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(12,32,8,.09); border-color: #c8d8b0; }

.hist-card-avatar {
    width: 40px; height: 40px;
    border-radius: 10px;
    background: rgba(41,67,6,.08);
    color: var(--primary-blue);
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: .9rem;
    flex-shrink: 0;
    overflow: hidden;
}
.hist-card-avatar img { width: 100%; height: 100%; object-fit: cover; }

.hist-card-employee .name { font-weight: 700; font-size: .95rem; color: #1a2e05; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.hist-card-employee .sub  { font-size: .72rem; color: #94a3b8; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.hist-card-dept .dept     { font-weight: 600; font-size: .82rem; color: #374151; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.hist-card-dept .tpl      { font-size: .7rem; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.hist-score-col { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.hist-score-col > .d-flex { flex-wrap: wrap; column-gap: 6px !important; row-gap: 2px; }
.hist-score-val { font-weight: 800; font-size: 1rem; }
.hist-score-col .badge { max-width: 100%; white-space: normal; line-height: 1.2; text-align: center; }
.hist-score-bar { height: 5px; background: #e2e8f0; border-radius: 99px; overflow: hidden; }
.hist-score-bar .fill { height: 100%; border-radius: 99px; transition: width .4s ease; }
.hist-status-col { min-width: 0; overflow-wrap: normal; }
.hist-status-col .pipeline-badge { max-width: 100%; }
.hist-card-action { justify-self: end; }
.hist-card-action .btn { white-space: nowrap; }

/* Evaluation details modal */
.history-detail-modal .modal-dialog {
    margin: auto;
    max-width: min(1120px, calc(100vw - 32px));
    width: calc(100vw - 32px);
}
.history-detail-modal .modal-content {
    height: min(820px, calc(100dvh - 32px));
    max-height: calc(100dvh - 32px);
    border-radius: 20px !important;
    overflow: hidden;
    box-shadow: 0 24px 80px rgba(15, 23, 42, .28);
}
.history-detail-modal .modal-header { flex: 0 0 auto; }
.history-detail-modal .modal-body { min-height: 0; }
.history-detail-modal .history-modal-header {
    align-items: center;
    background: linear-gradient(120deg, #0b2d0a, #164e24);
    border: 0;
    color: #fff;
    display: flex;
    justify-content: space-between;
    padding: 20px 26px;
}
.history-modal-heading { align-items: center; display: flex; gap: 14px; min-width: 0; }
.history-modal-icon {
    align-items: center;
    background: rgba(255,255,255,.12);
    border: 1px solid rgba(255,255,255,.16);
    border-radius: 13px;
    color: #f5d36e;
    display: inline-flex;
    flex: 0 0 44px;
    height: 44px;
    justify-content: center;
}
.history-modal-heading .modal-title { color: #fff; font-size: 1.08rem; }
.history-modal-subtitle { color: rgba(255,255,255,.68); font-size: .78rem; margin: 3px 0 0; }
.history-modal-status {
    border: 1px solid rgba(255,255,255,.24);
    border-radius: 999px;
    display: inline-flex;
    flex: 0 0 auto;
    font-size: .74rem;
    font-weight: 700;
    gap: 7px;
    margin-left: auto;
    margin-right: 16px;
    padding: 7px 12px;
}
.history-modal-status.approved { background: rgba(34,197,94,.17); color: #bbf7d0; }
.history-modal-status.rejected { background: rgba(239,68,68,.17); color: #fecaca; }
.history-modal-status.returned { background: rgba(245,158,11,.17); color: #fde68a; }
.history-detail-modal .btn-close { opacity: .8; }
.history-detail-modal .modal-body { background: #f6f8f5; overflow-y: auto; padding: 22px 26px !important; }
.history-detail-modal .status-stepper {
    align-items: flex-start;
    border: 0 !important;
    gap: 0;
    margin: 0 0 18px !important;
    overflow: visible !important;
    padding: 0 !important;
}
.history-detail-modal .status-stepper .step-item {
    color: #94a3b8 !important;
    flex: 1;
    font-size: .66rem;
    letter-spacing: .06em;
    position: relative;
    text-transform: uppercase;
}
.history-detail-modal .status-stepper .step-item:not(:last-child)::after {
    background: #dbe4d8;
    content: '';
    height: 2px;
    left: calc(50% + 20px);
    position: absolute;
    right: calc(-50% + 20px);
    top: 17px;
}
.history-detail-modal .status-stepper .step-item.is-complete { color: #166534 !important; }
.history-detail-modal .status-stepper .step-item.is-terminal { color: #a16207 !important; }
.history-detail-modal .status-stepper .step-item.is-rejected { color: #b91c1c !important; }
.history-detail-modal .history-step-icon {
    align-items: center;
    background: #fff;
    border: 1px solid #dbe4d8;
    border-radius: 50%;
    color: #94a3b8;
    display: flex;
    height: 36px;
    justify-content: center;
    margin: 0 auto 7px;
    position: relative;
    width: 36px;
    z-index: 1;
}
.history-detail-modal .step-item.is-complete .history-step-icon { background: #e8f5e9; border-color: #a7d7ac; color: #15803d; }
.history-detail-modal .step-item.is-terminal .history-step-icon { background: #fff7df; border-color: #f5d36e; color: #a16207; }
.history-detail-modal .step-item.is-rejected .history-step-icon { background: #fff1f2; border-color: #fecdd3; color: #be123c; }
.history-detail-modal .eval-summary-header {
    align-items: center;
    background: #fff;
    border: 1px solid #e5ebe2;
    border-radius: 16px;
    box-shadow: 0 4px 14px rgba(15,23,42,.04);
    flex-wrap: wrap;
    margin-bottom: 24px;
    padding: 16px 18px;
}
.history-detail-modal .history-employee-avatar {
    align-items: center;
    background: linear-gradient(145deg, #155e2c, #0b2d0a);
    border-radius: 14px;
    color: #fff;
    display: inline-flex;
    flex: 0 0 52px;
    font-size: 1rem;
    height: 52px;
    justify-content: center;
}
.history-detail-modal .history-employee-copy { min-width: 0; }
.history-detail-modal .history-employee-copy h4 { font-size: 1.02rem !important; }
.history-detail-modal .history-employee-meta { color: #64748b; font-size: .76rem; }
.history-detail-modal .history-summary-actions { align-items: center; display: flex; flex-wrap: wrap; gap: 14px; margin-left: auto; }
.history-detail-modal .history-summary-actions .score-circle { height: 68px; min-width: 68px; width: 68px; }
.history-detail-modal .history-summary-actions .score-circle .val { font-size: 1.08rem; }
.history-detail-modal .section-premium-label {
    align-items: center;
    color: #164e24;
    display: flex;
    font-size: .79rem;
    gap: 9px;
    letter-spacing: .07em;
    margin: 22px 0 12px !important;
}
.history-detail-modal .table-responsive { background: #fff; border: 1px solid #e4e9e1; border-radius: 12px; }
.history-detail-modal .table { margin: 0; }
.history-detail-modal .table thead th {
    background: #eff4ed;
    border-bottom: 1px solid #e0e8dc;
    color: #475569;
    font-size: .68rem;
    letter-spacing: .05em;
    padding-bottom: 11px;
    padding-top: 11px;
    text-transform: uppercase;
    white-space: nowrap;
}
.history-detail-modal .table tbody td { padding-bottom: 11px; padding-top: 11px; }
.history-detail-modal .table tbody tr:last-child td { border-bottom: 0; }

.hist-status-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 999px; font-size: .72rem; font-weight: 700; white-space: nowrap; }
.hist-status-badge.approved { background: #d1fae5; color: #065f46; }
.hist-status-badge.imported { background: #e8f1e5; color: #24592a; }
.hist-status-badge.rejected { background: #fee2e2; color: #991b1b; }
.hist-status-badge.returned { background: #fef3c7; color: #92400e; }

/* Empty state */
.hist-empty { text-align: center; padding: 60px 20px; color: #94a3b8; }
.hist-empty i { font-size: 3rem; opacity: .15; display: block; margin-bottom: 16px; }

/* Results meta */
.hist-meta { font-size: .78rem; color: #94a3b8; padding: 6px 4px; }

@media (min-width: 768px) and (max-width: 1199px) {
    .hist-card {
        grid-template-columns: 40px minmax(0, 1.3fr) minmax(0, 1fr) minmax(110px, .8fr);
        gap: 12px;
        padding: 14px 16px;
    }
    .hist-card-employee { grid-column: 2; }
    .hist-card-dept { grid-column: 3; }
    .hist-score-col { grid-column: 4; }
    .hist-status-col { grid-column: 2 / 4; }
    .hist-card-action { grid-column: 4; grid-row: 2; }
}

@media (max-width: 767px) {
    .history-review-tabs { gap:4px; }
    .history-review-tab { flex:1; min-width:0; padding:10px; }
    .history-detail-modal .modal-dialog { margin: 8px; max-width: calc(100vw - 16px); width: calc(100vw - 16px); }
    .history-detail-modal .modal-content { height: calc(100dvh - 16px); max-height: calc(100dvh - 16px); border-radius: 16px !important; }
    .history-detail-modal .history-modal-header { flex-wrap: wrap; gap: 12px; padding: 16px; }
    .history-modal-heading { gap: 10px; }
    .history-modal-icon { flex-basis: 38px; height: 38px; }
    .history-detail-modal .history-modal-status { margin-left: 48px; margin-right: 0; order: 3; }
    .history-detail-modal .modal-body { padding: 16px !important; }
    .history-detail-modal .eval-summary-header { align-items: flex-start; }
    .history-detail-modal .history-summary-actions { margin-left: 0; width: 100%; }
    .history-detail-modal .history-summary-actions .score-circle { height: 60px; min-width: 60px; width: 60px; }
    .history-detail-modal .history-step-icon { height: 32px; width: 32px; }
    .history-detail-modal .status-stepper .step-item:not(:last-child)::after { left: calc(50% + 17px); right: calc(-50% + 17px); top: 15px; }
    .hist-card {
        grid-template-columns: 36px 1fr;
        grid-template-rows: auto auto auto;
        gap: 10px 12px;
        padding: 14px 16px;
    }
    .hist-card-employee { grid-column: 2; }
    .hist-card-dept, .hist-score-col, .hist-status-col { grid-column: 2; }
    .hist-card-action { grid-column: 1 / -1; justify-self: stretch; }
    .hist-card-action .btn { width: 100%; }
}
</style>

<!-- Evaluation type tabs -->
<div class="history-review-tabs fadeup fadeup-1" role="tablist" aria-label="Evaluation type">
    <button class="history-review-tab probationary active" type="button" role="tab" aria-selected="true" onclick="histSetType('probationary', this)"><i class="fas fa-users"></i><span><strong>Probationary Evaluation</strong><small class="d-block text-muted fw-normal">Initial and Final evaluations</small></span><span class="tab-count" id="histProbationaryCount">0</span></button>
    <button class="history-review-tab regular" type="button" role="tab" aria-selected="false" onclick="histSetType('regular', this)"><i class="fas fa-user"></i><span><strong>Regular Employee</strong><small class="d-block text-muted fw-normal">Quarterly and Annual team evaluations</small></span><span class="tab-count" id="histRegularCount">0</span></button>
</div>
<section class="history-section-heading"><h5 class="fw-bold mb-1" id="historyTypeHeading">Probationary Evaluation</h5><div class="history-period" id="historyTypePeriod">Initial and Final employee evaluations</div></section>

<!-- Filter Bar -->
<div class="hist-filter-bar fadeup fadeup-1">
    <div class="hist-status-pills">
        <button class="hist-status-pill active-all" onclick="histFilter('All', this)">
            <i class="fas fa-list me-1"></i>All <span class="ms-1 opacity-75" data-status-count="All">(<?php echo $total_c; ?>)</span>
        </button>
        <button class="hist-status-pill" onclick="histFilter('Approved', this)">
            <i class="fas fa-check-circle me-1"></i>Approved <span class="ms-1 opacity-75" data-status-count="Approved">(<?php echo $approved_c; ?>)</span>
        </button>
        <button class="hist-status-pill" onclick="histFilter('Imported', this)">
            <i class="fas fa-file-import me-1"></i>Imported <span class="ms-1 opacity-75" data-status-count="Imported">(<?php echo $imported_c; ?>)</span>
        </button>
        <button class="hist-status-pill" onclick="histFilter('Rejected', this)">
            <i class="fas fa-times-circle me-1"></i>Rejected <span class="ms-1 opacity-75" data-status-count="Rejected">(<?php echo $rejected_c; ?>)</span>
        </button>
        <button class="hist-status-pill" onclick="histFilter('Returned', this)">
            <i class="fas fa-rotate-left me-1"></i>Returned <span class="ms-1 opacity-75" data-status-count="Returned">(<?php echo $returned_c; ?>)</span>
        </button>
    </div>
    <div class="history-filter-grid">
        <div class="history-filter-field">
            <label class="text-muted small fw-semibold" for="histBranchFilter"><i class="fas fa-code-branch me-1" aria-hidden="true"></i>Branch:</label>
            <select class="form-select form-select-sm" id="histBranchFilter"><option value="All">All Branches</option></select>
        </div>
        <div class="history-filter-field">
            <label class="text-muted small fw-semibold" for="histDeptFilter"><i class="fas fa-building me-1"></i>Department</label><select class="form-select form-select-sm" id="histDeptFilter">
                <option value="All">All Departments</option>
                <?php foreach ($existing_departments as $dept_name): ?>
                    <option value="<?php echo e($dept_name); ?>"><?php echo e($dept_name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="history-filter-field">
            <label class="text-muted small fw-semibold" for="histTemplateFilter"><i class="fas fa-file-alt me-1"></i>Template:</label><select class="form-select form-select-sm" id="histTemplateFilter">
                <option value="All">All Templates</option>
                <?php foreach ($existing_templates as $tmpl_name): ?>
                    <option value="<?php echo e($tmpl_name); ?>"><?php echo e($tmpl_name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="history-filter-field">
            <label class="text-muted small fw-semibold" for="histEvalTypeFilter"><i class="fas fa-clipboard-check me-1"></i>Evaluation type</label><select class="form-select form-select-sm" id="histEvalTypeFilter">
                <option value="All">All Types</option>
                <option value="Annual">Annual</option>
                <option value="Quarterly">Quarterly</option>
                <option value="Initial">Initial</option>
                <option value="Final">Final</option>
            </select>
        </div>
        <div class="input-group input-group-sm history-filter-search">
            <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
            <input type="search" class="form-control border-start-0 ps-0" id="histSearchInput" placeholder="Search employee, position...">
        </div>
    </div>
</div>

<!-- Evaluation table -->
<div class="history-table-wrap hr-history-table-container fadeup fadeup-2"><table class="table history-table hr-history-table" id="histTable"><thead><tr><th>Employee Name</th><th>Department</th><th>Position</th><th>Overall Rating</th><th>Status</th><th>Date Submitted</th><th>Actions</th></tr></thead><tbody id="histTableBody">
    <?php if (empty($all_history)): ?>
        <tr><td colspan="7" class="history-empty-row">No evaluations match these filters.</td></tr>
    <?php else: ?>
        <?php foreach ($all_history as $row):
            $h_score = (float)($row['total_score'] ?? 0);
            $h_perf  = $row['performance_level'] ?? '';
            if ($h_score > 0 && (empty($h_perf) || $h_perf === '0')) {
                $h_perf = getPerformanceLevel($h_score);
            }
            $h_badge_class = getPerformanceBadgeClass($h_perf);
            $score_pct  = min(100, ($h_score / 4) * 100);
            $bar_color  = match(true) {
                str_contains($h_badge_class, 'success') => '#22c55e',
                str_contains($h_badge_class, 'info')    => '#0ea5e9',
                str_contains($h_badge_class, 'warning') => '#f59e0b',
                str_contains($h_badge_class, 'danger')  => '#ef4444',
                default                                  => '#94a3b8',
            };
            $is_probationary_history = in_array($row['package_evaluation_type'] ?? '', ['Initial', 'Final'], true);
            $history_status = ($row['package_status'] ?? '') === 'Approved and Applied' ? 'Approved' : (($row['package_status'] ?? '') ?: $row['status']);
            $history_status_label = $history_status === 'Approved and Applied' ? 'Approved' : $history_status;
            $status_filter_key = in_array($history_status_label, ['Approved', 'Imported', 'Rejected', 'Returned'], true) ? $history_status_label : 'Pending';
            $status_display = $status_filter_key === 'Pending' ? 'Pending' : $history_status_label;
            $status_lc  = strtolower($status_display);
            $status_icon = $history_status_label === 'Pending Self-Ratings' ? 'fa-hourglass-half' : match($history_status_label) {
                'Approved' => 'fa-check-circle',
                'Imported' => 'fa-file-import',
                'Rejected' => 'fa-times-circle',
                'Returned' => 'fa-rotate-left',
                default    => 'fa-circle',
            };
            $is_historical = !empty($row['is_historical']);
            $reviewer_name = ($row['package_status'] ?? '') === 'Approved and Applied' ? (($row['last_reviewer_name'] ?? '') ?: 'Completed') : trim((string)($row['current_reviewer_name'] ?? ''));
            $reviewer_position = ($row['package_status'] ?? '') === 'Approved and Applied' ? (string)($row['last_reviewer_position'] ?? '') : (string)($row['current_reviewer_position'] ?? '');
            if ($reviewer_name === '') $reviewer_name = ($row['package_status'] ?? '') === 'Returned' ? (($row['last_reviewer_name'] ?? '') ?: 'Returned for revision') : (($row['current_step_label'] ?? '') ?: (($row['last_reviewer_name'] ?? '') ?: 'In review'));
            if ($history_status_label === 'Imported') $reviewer_name = 'Historical import';
            $submitted_date = $row['submitted_at'] ?? $row['created_at'] ?? $row['updated_at'] ?? null;
            $initials_h = strtoupper(
                substr($row['employee_name'], 0, 1) .
                substr(explode(' ', $row['employee_name'])[1] ?? '', 0, 1)
            );
            $avatar_h = getEmployeeAvatar($row['profile_picture'] ?? '');
        ?>
        <tr class="history-row" data-type="<?php echo $is_probationary_history ? 'probationary' : 'regular'; ?>" data-status="<?php echo e($status_filter_key); ?>"
             data-department="<?php echo e($row['department_name'] ?? ''); ?>"
             data-branch="<?php echo e($row['branch_name'] ?? 'Unassigned'); ?>"
             data-template="<?php echo e($row['template_name'] ?? ''); ?>"
             data-evaluation-type="<?php echo e($row['package_evaluation_type'] ?? 'Annual'); ?>"
             data-search="<?php echo strtolower(e($row['employee_name']) . ' ' . e($row['department_name'] ?? '') . ' ' . e($row['template_name']) . ' ' . e($row['job_title']) . ' ' . e($row['form_code'] ?? '')); ?>">
            <td><div class="employee-cell"><div class="hist-card-avatar">
                <img src="<?php echo e($avatar_h); ?>" alt="<?php echo e($row['employee_name']); ?>">
            </div><div class="employee-copy"><strong><?php echo e($row['employee_name']); ?></strong><small><?php echo e($row['employee_code'] ?? ('ID ' . $row['employee_id'])); ?></small></div></div></td>
            <!-- Dept + Template -->
            <td><?php echo e($row['department_name'] ?? 'N/A'); ?></td><td><?php echo e($row['job_title'] ?? 'N/A'); ?></td>
            <!-- Score -->
            <td class="text-center"><strong class="d-block"><?php echo $h_score > 0 ? number_format($h_score, 2) : '—'; ?></strong><?php if ($h_perf): $rating_class = str_contains($h_badge_class, 'success') ? 'rating-excellent' : (str_contains($h_badge_class, 'info') ? 'rating-exceeds' : (str_contains($h_badge_class, 'warning') ? 'rating-meets' : (str_contains($h_badge_class, 'danger') ? 'rating-needs' : ''))); ?><span class="rating-pill <?php echo $rating_class; ?>"><?php echo e($h_perf); ?></span><?php endif; ?></td>
            <!--
                <div class="d-flex align-items-center gap-2">
                    <span class="hist-score-val"><?php echo $h_score > 0 ? number_format($h_score, 2) : '—'; ?></span>
                    <?php if ($h_perf): ?>
                        <span class="badge <?php echo $h_badge_class; ?> rounded-pill px-2" style="font-size:.65rem;"><?php echo e($h_perf); ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($h_score > 0): ?>
                <div class="hist-score-bar">
                    <div class="fill" style="width:<?php echo $score_pct; ?>%;background:<?php echo $bar_color; ?>;"></div>
                </div>
                <?php endif; ?>
                <div style="font-size:.68rem;color:#94a3b8;"><?php echo formatDate($row['updated_at']); ?></div>
            </div>-->
            <!-- Status -->
            <td>
                <?php if (!empty($row['package_id'])): ?>
                    <?php echo renderOrganizationPipelineBadge($conn, (int)$row['package_id'], '', (int)$row['evaluation_id']); ?>
                <?php else: ?>
                    <span class="hist-status-badge <?php echo $status_lc; ?>">
                        <i class="fas <?php echo $status_icon; ?>"></i><?php echo e($status_display); ?>
                    </span>
                <?php endif; ?>
                <?php if ($is_historical): ?>
                    <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle ms-2">
                        <i class="fas fa-history me-1"></i>Historical Import
                    </span>
                <?php endif; ?>
            </td><td><?php echo $submitted_date ? formatDate($submitted_date) : '—'; ?></td><td>
                <button class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold shadow-sm"
                        data-history-detail="<?php echo (int)$row['evaluation_id']; ?>">
                    <i class="fas fa-eye me-1"></i>View
                </button>
            </td>
        </tr>
        <?php endforeach; ?>

        <!-- No results (search) -->
    <tr class="d-none" id="histNoResults"><td colspan="7" class="history-empty-row">No evaluations match these filters.</td></tr>
    <?php endif; ?>
</tbody></table></div>

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
window.hrHistoryState = <?php $historyClientState=$historyPage; unset($historyClientState['rows']); $historyClientState['role']='HR Manager'; echo json_encode($historyClientState, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE); ?>;
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

<?php require_once '../includes/footer.php'; ?>
