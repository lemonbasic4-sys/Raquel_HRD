<?php
$page_title = 'My Performance';
require_once '../includes/session-check.php';
checkRole(['Employee']);
require_once '../includes/functions.php';

$employee_id = (int)($_SESSION['employee_id'] ?? 0);

// ── Employee core info ───────────────────────────────────────────────────────
$emp_stmt = $conn->prepare("
    SELECT e.employee_id, e.first_name, e.last_name, e.job_title,
           e.profile_picture, e.hire_date, e.employment_status,
           d.department_name, b.branch_name, rc.rank_name
    FROM employees e
    LEFT JOIN departments     d  ON e.department_id     = d.department_id
    LEFT JOIN branches        b  ON e.branch_id         = b.branch_id
    LEFT JOIN rank_categories rc ON e.rank_category_id = rc.rank_category_id
    WHERE e.employee_id = ?
");
$emp_stmt->bind_param("i", $employee_id);
$emp_stmt->execute();
$emp = $emp_stmt->get_result()->fetch_assoc() ?? [];
$emp_stmt->close();

$years_of_service = 0;
if (!empty($emp['hire_date'])) {
    $diff = (new DateTime($emp['hire_date']))->diff(new DateTime());
    $years_of_service = $diff->y;
}

// ── Quick stats for PHP-rendered hero (latest approved) ─────────────────────
// Hero and JSON response use the same finalized records and chronology.
require_once '../includes/employee-performance-analysis.php';
$performance_history = employeePerformanceHistory($conn, $employee_id);
$total_evals_count = count($performance_history['evaluations']);
$latest_eval = $total_evals_count ? $performance_history['evaluations'][$total_evals_count - 1] : null;

require_once '../includes/header.php';
?>

<style>
/* ── My Performance — brand-matched styles ── */
.score-ring           { width:110px; height:110px; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-direction:column; font-weight:800; position:relative; }
.score-ring-outstanding   { background: rgba(203,161,53,.18); border: 3px solid #CBA135; }
.score-ring-exceeds       { background: rgba(22,163,74,.18);  border: 3px solid #16a34a; }
.score-ring-meets         { background: rgba(203,161,53,.12); border: 3px solid #CBA13588; }
.score-ring-needs         { background: rgba(225,35,42,.15);  border: 3px solid #E1232A; }
.stat-pill            { background: rgba(255,255,255,.12); border-radius:.6rem; padding:.65rem 1rem; text-align:center; }
.section-card         { border: 0; border-radius: 1rem; box-shadow: 0 2px 16px rgba(8,46,6,.08); }
.history-badge-outstanding  { background:#d1e7dd; color:#0a3622; }
.history-badge-exceeds      { background:#dcfce7; color:#14532d; }
.history-badge-meets        { background:#fef9c3; color:#713f12; }
.history-badge-needs        { background:#fee2e2; color:#7f1d1d; }
.readiness-bar        { height: 14px; border-radius: 99px; }
.goal-progress        { height: 10px; border-radius: 99px; }
.insight-card         { background: linear-gradient(135deg, #f7fdf4, #eefae8); border-radius:.75rem; border-left: 4px solid #CBA135; }
.chart-wrapper        { position: relative; height: 280px; }
.empty-perf           { padding: 3rem; text-align:center; color: #6c757d; }
.min-width-0          { min-width: 0; }
.eval-history-shell   { display: grid; gap: .85rem; }
.eval-history-table   { display: block; }
.eval-history-mobile  { display: none; }
.eval-history-table .table {
    border-collapse: separate;
    border-spacing: 0 .45rem;
}
.eval-history-table thead th {
    border: 0;
    color: #5E6B5C;
    font-size: .72rem;
    font-weight: 800;
    letter-spacing: 0;
    text-transform: uppercase;
}
.eval-history-table tbody tr {
    box-shadow: 0 4px 14px rgba(8,46,6,.06);
}
.eval-history-table tbody td {
    background: #ffffff;
    border-top: 1px solid #edf1e9;
    border-bottom: 1px solid #edf1e9;
    vertical-align: middle;
}
.eval-history-table tbody td:first-child {
    border-left: 1px solid #edf1e9;
    border-radius: .65rem 0 0 .65rem;
}
.eval-history-table tbody td:last-child {
    border-right: 1px solid #edf1e9;
    border-radius: 0 .65rem .65rem 0;
}
.eval-history-card {
    background: #ffffff;
    border: 1px solid #e5ebdf;
    border-radius: .85rem;
    box-shadow: 0 5px 16px rgba(8,46,6,.07);
    padding: .95rem;
}
.eval-history-card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: .75rem;
    margin-bottom: .8rem;
}
.eval-history-period {
    color: #1C271B;
    font-size: .95rem;
    font-weight: 800;
    line-height: 1.25;
}
.eval-history-date {
    color: #6c757d;
    font-size: .72rem;
    margin-top: .18rem;
}
.eval-history-score {
    flex: 0 0 auto;
    min-width: 64px;
    text-align: right;
}
.eval-history-score-value {
    color: #082E06;
    font-size: 1.45rem;
    font-weight: 850;
    line-height: 1;
}
.eval-history-score-label {
    color: #6c757d;
    font-size: .68rem;
    font-weight: 700;
    margin-top: .15rem;
    text-transform: uppercase;
}
.eval-history-card .badge {
    max-width: 100%;
    white-space: normal;
    text-align: left;
}
.eval-history-notes {
    background: #f7faf4;
    border-radius: .65rem;
    color: #5E6B5C;
    font-size: .78rem;
    line-height: 1.45;
    margin-top: .75rem;
    padding: .7rem .8rem;
}
.eval-history-notes-title {
    color: #1C271B;
    font-size: .68rem;
    font-weight: 800;
    margin-bottom: .25rem;
    text-transform: uppercase;
}

@media (max-width: 575.98px) {
    .section-card .card-body {
        padding: 1rem !important;
    }
    .chart-wrapper {
        height: 240px;
    }
    .eval-history-table {
        display: none;
    }
    .eval-history-mobile {
        display: grid;
        gap: .85rem;
    }
}
.perf-cycle { border:1px solid #e1e8dc; border-radius:.75rem; margin-bottom:1rem; overflow:hidden; }
.perf-cycle summary { padding:1rem; cursor:pointer; background:#f7faf4; }
.perf-cycle[open] summary { border-bottom:1px solid #e1e8dc; }
.perf-cycle-body { padding:1rem; }
.perf-criteria { font-size:.8rem; min-width:760px; }
.perf-criteria th { white-space:nowrap; }
.perf-criteria td { vertical-align:top; }
.perf-criteria .criterion-description { max-width:280px; }
.perf-extreme { font-size:.68rem; }
.perf-change { font-variant-numeric:tabular-nums; white-space:nowrap; }
</style>

<?php
// ── Hero score ring class ────────────────────────────────────────────────────
$score_val = $latest_eval ? (float)$latest_eval['total_score'] : 0;
$ring_class = 'score-ring-needs';
if ($score_val >= 3.60)      $ring_class = 'score-ring-outstanding';
elseif ($score_val >= 2.60)  $ring_class = 'score-ring-exceeds';
elseif ($score_val >= 2.00)  $ring_class = 'score-ring-meets';
$score_label = $latest_eval['performance_level'] ?? 'N/A';
?>

<!-- ══ PAGE HERO ══════════════════════════════════════════════════════════════ -->
<div class="page-hero fadeup mb-4">
    <div class="row align-items-center g-3">
        <div class="col-auto">
            <?php
            $avatar_url = getEmployeeAvatar($emp['profile_picture'] ?? '');
            $default_avatar_url = getEmployeeAvatar('');
            ?>
                <img src="<?php echo e($avatar_url); ?>" alt="Employee profile picture"
                     class="rounded-circle shadow"
                     style="width:72px;height:72px;object-fit:cover;border:3px solid rgba(255,255,255,.35);"
                     data-default-avatar="<?php echo e($default_avatar_url); ?>"
                     onerror="this.onerror=null;this.src=this.dataset.defaultAvatar;">
        </div>

        <div class="col">
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">Employee Portal · My Performance</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><?php echo e(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')); ?></h4>
            <div class="text-white-50 small mt-1">
                <?php echo e($emp['job_title'] ?? 'N/A'); ?> &bull;
                <?php echo e($emp['department_name'] ?? 'N/A'); ?> &bull;
                <?php echo e($emp['branch_name'] ?? 'N/A'); ?>
            </div>
        </div>

        <!-- Score Ring -->
        <div class="col-auto d-none d-md-block">
            <div class="score-ring <?php echo $ring_class; ?>">
                <?php if ($latest_eval): ?>
                    <span style="font-size:1.6rem;color:#fff;"><?php echo number_format($score_val, 2); ?></span>
                    <span style="font-size:.6rem;color:rgba(255,255,255,.7);text-align:center;line-height:1.2;margin-top:2px;">Latest<br>Score</span>
                <?php else: ?>
                    <span style="font-size:.75rem;color:rgba(255,255,255,.6);text-align:center;">No Data<br>Yet</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Stat pills -->
    <div class="row g-2 mt-3">
        <div class="col-6 col-md-3">
            <div class="stat-pill">
                <div class="fw-bold text-white" style="font-size:1.2rem;"><?php echo $total_evals_count; ?></div>
                <div style="font-size:.7rem;color:rgba(255,255,255,.6);">Finalized Evaluations</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-pill">
                <div class="fw-bold text-white" style="font-size:1.2rem;"><?php echo $years_of_service; ?> yrs</div>
                <div style="font-size:.7rem;color:rgba(255,255,255,.6);">Years of Service</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-pill">
                <div class="fw-bold text-white" style="font-size:1.2rem;" id="hero-avg-score">—</div>
                <div style="font-size:.7rem;color:rgba(255,255,255,.6);">Avg Finalized Score (All Time)</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-pill">
                <div id="hero-trend-badge" style="font-size:.75rem;font-weight:600;">Loading...</div>
                <div style="font-size:.7rem;color:rgba(255,255,255,.6);">Performance Trend</div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="perfLoadingState" class="text-center py-5 my-4">
    <div class="spinner-border text-primary mb-3" role="status"></div>
    <div class="text-muted small">Loading your performance data...</div>
</div>

<!-- Main Content (shown after load) -->
<div id="pendingPerformanceNotice" class="alert alert-info" style="display:none;" role="status"></div>
<div id="perfContent" style="display:none;">
    <div class="row g-4">

        <!-- ── LEFT COLUMN ──────────────────────────────────────────────── -->
        <div class="col-lg-8">

            <!-- Trend Chart -->
            <div class="card section-card mb-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="fw-bold text-dark mb-0"><i class="fas fa-chart-line text-primary me-2"></i>Performance Trend</h6>
                            <div class="text-muted" style="font-size:.75rem;">Each finalized cycle ? ordered by approval date</div>
                        </div>
                        <span id="trendClassBadge" class="badge px-3 py-2" style="font-size:.75rem;"></span>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="perfTrendChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Evaluation History -->
            <div class="card section-card mb-4">
                <div class="card-body p-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h6 class="fw-bold text-dark mb-0"><i class="fas fa-history text-info me-2"></i>Evaluation Breakdown &amp; Progress</h6>
                        <a href="<?php echo BASE_URL; ?>/employee/evaluation-history.php" class="btn btn-outline-primary btn-sm">All Evaluation History</a>
                    </div>
                    <p class="small text-muted">Expand a cycle to see approved ratings, component weights, strengths, and changes in matching criteria. Percentages mean score ? 4 ? 100; they are not percentile ranks.</p>
                    <div id="evalHistoryContainer">
                        <div class="text-center py-4 text-muted">Loading...</div>
                    </div>
                </div>
            </div>

        </div>

        <!-- ── RIGHT COLUMN ─────────────────────────────────────────────── -->
        <div class="col-lg-4">

            <!-- Career Readiness -->
            <div class="card section-card mb-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-dark mb-1"><i class="fas fa-rocket text-success me-2"></i>Career Readiness</h6>
                    <div class="text-muted mb-3" style="font-size:.75rem;">Illustrative indicator from finalized scores, tenure &amp; history</div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="fw-bold" style="font-size:.85rem;" id="readinessLabel">Calculating...</span>
                        <span class="fw-bold text-primary" id="readinessPct">—</span>
                    </div>
                    <div class="bg-light rounded-pill mb-3" style="height:14px;overflow:hidden;">
                        <div id="readinessBar" class="readiness-bar bg-success" style="width:0%;transition:width 1s ease;"></div>
                    </div>
                    <div id="readinessBreakdown" class="text-muted" style="font-size:.73rem;"></div>
                </div>
            </div>

            <!-- Goal Tracking -->
            <div class="card section-card mb-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-dark mb-1"><i class="fas fa-bullseye text-warning me-2"></i>Goal Tracking</h6>
                    <div class="text-muted mb-3" style="font-size:.75rem;">Progress toward next performance level</div>
                    <div id="goalTrackingContent">
                        <div class="text-muted small text-center py-3">Calculating...</div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Empty State -->
<div id="perfEmptyState" style="display:none;">
    <div class="card section-card">
        <div class="empty-perf">
            <i class="fas fa-chart-bar fa-3x mb-3 opacity-25"></i>
            <h5 class="fw-semibold">No Evaluation Data Yet</h5>
            <p class="text-muted small mb-0">Your approved evaluation results will appear here once your HR team completes the evaluation process.</p>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/assets/vendor/chartjs/chart.umd.min.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/employee-performance.js?v=<?php echo filemtime(__DIR__.'/../assets/js/employee-performance.js'); ?>" data-base="<?php echo e(BASE_URL); ?>"></script>

<?php require_once '../includes/footer.php'; ?>

