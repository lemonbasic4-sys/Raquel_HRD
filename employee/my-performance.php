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

require_once '../includes/header.php';
$avatar_url = getEmployeeAvatar($emp['profile_picture'] ?? '');
$default_avatar_url = getEmployeeAvatar('');
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/employee-performance.css?v=<?php echo filemtime(__DIR__.'/../assets/css/employee-performance.css'); ?>">
<div class="my-performance">
    <header class="mp-header page-hero fadeup">
        <img class="mp-avatar" src="<?php echo e($avatar_url); ?>" alt="Employee profile picture"
             data-default-avatar="<?php echo e($default_avatar_url); ?>"
             onerror="this.onerror=null;this.src=this.dataset.defaultAvatar;">
        <div class="mp-identity">
            <h1>My Performance</h1>
            <p><?php echo e(trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''))); ?> &middot; <?php echo e($emp['job_title'] ?? ''); ?></p>
        </div>
        <a href="<?php echo BASE_URL; ?>/employee/evaluation-history.php" class="btn btn-outline-light btn-sm">Evaluation history</a>
    </header>

    <div id="perfLoadingState" class="mp-empty" role="status">Loading your performance...</div>
    <div id="pendingPerformanceNotice" class="mp-notice" hidden></div>
    <div id="perfEmptyState" class="mp-card mp-empty" hidden>
        <h2>No finalized evaluations yet</h2>
        <p>Your results will appear when your evaluation is approved and its score is complete.</p>
    </div>

    <div id="perfContent" hidden>
        <div class="mp-tabs" role="tablist" aria-label="Performance sections">
            <button type="button" id="performance-tab" role="tab" aria-selected="true" aria-controls="performance-panel" data-panel="performance-panel">Performance</button>
            <button type="button" id="development-tab" role="tab" aria-selected="false" aria-controls="development-panel" tabindex="-1" data-panel="development-panel">Career &amp; Goals</button>
        </div>

        <section id="performance-panel" role="tabpanel" aria-labelledby="performance-tab">
            <div class="mp-summary" aria-label="Latest performance summary">
                <article class="mp-card mp-stat mp-stat-latest">
                    <h2>Latest score</h2><div class="mp-score"><span id="latest-score">&mdash;</span><small>/ 4</small></div>
                    <p id="latest-level"></p><span id="latest-cycle" class="mp-muted"></span>
                </article>
                <article class="mp-card mp-stat">
                    <h2>Previous score</h2><div class="mp-score"><span id="previous-score">&mdash;</span><small id="previous-scale">/ 4</small></div>
                    <p id="previous-level"></p><span id="previous-cycle" class="mp-muted"></span>
                </article>
                <article class="mp-card mp-stat">
                    <h2>Performance change</h2><div class="mp-score" id="latest-change">&mdash;</div>
                    <p id="latest-direction"></p><span class="mp-muted">Latest versus previous finalized cycle</span>
                </article>
            </div>

            <div class="mp-overview">
                <section class="mp-card">
                    <div class="mp-card-heading"><div><h2>Performance trend</h2><p>Approved, finalized cycles</p></div><span id="finalized-count" class="mp-muted"></span></div>
                    <div class="mp-chart"><canvas id="perfTrendChart" role="img" aria-label="Scores for finalized evaluation cycles"></canvas></div>
                    <p class="mp-muted mp-chart-hint">Select a point to review that cycle.</p>
                </section>
                <section class="mp-card" aria-label="Latest evaluation highlights">
                    <div class="mp-card-heading"><div><h2>What to focus on</h2><p>From your latest evaluation</p></div></div>
                    <div id="latest-insights"></div>
                </section>
            </div>

            <section class="mp-card mp-comparison" aria-labelledby="comparison-heading">
                <div class="mp-card-heading"><div><h2 id="comparison-heading">Compare your evaluations</h2><p>Ratings are out of 4. Only equivalent criteria show a change.</p></div></div>
                <div class="mp-selectors">
                    <div><label for="review-cycle">Cycle to review</label><select id="review-cycle" class="form-select form-select-sm"></select></div>
                    <div><label for="compare-cycle">Compare against</label><select id="compare-cycle" class="form-select form-select-sm"></select></div>
                </div>
                <div id="comparison-summary" class="mp-comparison-summary" aria-live="polite"></div>
                <div id="comparison-notice" class="mp-muted"></div>
                <div class="mp-criteria-grid">
                    <section aria-labelledby="kra-heading"><h3 id="kra-heading">Key Result Areas <span id="kra-count"></span></h3><div id="kra-criteria"></div></section>
                    <section aria-labelledby="behavior-heading"><h3 id="behavior-heading">Behavioral Competencies <span id="behavior-count"></span></h3><div id="behavior-criteria"></div></section>
                </div>
                <details class="mp-technical" id="scoring-details">
                    <summary>Scoring details &amp; audit information</summary>
                    <div id="technical-content"></div>
                </details>
            </section>
        </section>

        <section id="development-panel" role="tabpanel" aria-labelledby="development-tab" hidden>
            <div class="mp-development-grid">
                <section class="mp-card"><div class="mp-card-heading"><div><h2>Career Readiness</h2><p>A planning indicator based on finalized scores, tenure, and history.</p></div></div>
                    <div class="mp-readiness"><strong id="readinessLabel"></strong><strong id="readinessPct"></strong></div>
                    <div class="progress my-3"><div id="readinessBar" class="progress-bar bg-success" style="width:0%"></div></div>
                    <details class="mp-technical"><summary>How this indicator is calculated</summary><div id="readinessBreakdown"></div></details>
                </section>
                <section class="mp-card"><div class="mp-card-heading"><div><h2>Goal Tracking</h2><p>Progress toward the next performance level.</p></div></div><div id="goalTrackingContent"></div></section>
            </div>
            <section class="mp-card mp-development-plan"><h2>Your latest development plan</h2><div id="development-plan"></div></section>
        </section>
    </div>
</div>
<script src="<?php echo BASE_URL; ?>/assets/vendor/chartjs/chart.umd.min.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/employee-performance.js?v=<?php echo filemtime(__DIR__.'/../assets/js/employee-performance.js'); ?>" data-base="<?php echo e(BASE_URL); ?>"></script>
<?php require_once '../includes/footer.php'; ?>
