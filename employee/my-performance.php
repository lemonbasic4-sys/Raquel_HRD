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
$latest_stmt = $conn->prepare("
    SELECT total_score, performance_level, approved_date,
           YEAR(evaluation_period_end) AS eval_year
    FROM evaluations
    WHERE employee_id = ? AND status = 'Approved' AND deleted_at IS NULL
    ORDER BY evaluation_period_end DESC, approved_date DESC
    LIMIT 1
");
$latest_stmt->bind_param("i", $employee_id);
$latest_stmt->execute();
$latest_eval = $latest_stmt->get_result()->fetch_assoc();
$latest_stmt->close();

$total_evals_count = (int)($conn->query("SELECT COUNT(*) AS c FROM evaluations WHERE employee_id=$employee_id AND status='Approved' AND deleted_at IS NULL")->fetch_assoc()['c'] ?? 0);

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
            $initials   = strtoupper(substr($emp['first_name'] ?? 'U', 0, 1) . substr($emp['last_name'] ?? '', 0, 1));
            if ($avatar_url && strpos($avatar_url, '/logo/logo.png') === false):
            ?>
                <img src="<?php echo $avatar_url; ?>" alt="Profile"
                     class="rounded-circle shadow"
                     style="width:72px;height:72px;object-fit:cover;border:3px solid rgba(255,255,255,.35);"
                     onerror="this.onerror=null;this.style.display='none';document.getElementById('hero-initials').style.display='flex';">
                <div id="hero-initials" class="rounded-circle d-none align-items-center justify-content-center fw-bold fs-4"
                     style="width:72px;height:72px;background:rgba(255,255,255,.2);border:3px solid rgba(255,255,255,.35);color:#fff;">
                    <?php echo $initials; ?>
                </div>
            <?php else: ?>
                <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold fs-4"
                     style="width:72px;height:72px;background:rgba(255,255,255,.2);border:3px solid rgba(255,255,255,.35);color:#fff;">
                    <?php echo $initials; ?>
                </div>
            <?php endif; ?>
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
                <div style="font-size:.7rem;color:rgba(255,255,255,.6);">Evaluations Done</div>
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
                <div style="font-size:.7rem;color:rgba(255,255,255,.6);">Avg Score (All Time)</div>
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
                            <div class="text-muted" style="font-size:.75rem;">Last 5 evaluation years (average per year)</div>
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
                    <h6 class="fw-bold text-dark mb-3"><i class="fas fa-history text-info me-2"></i>Evaluation History</h6>
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
                    <div class="text-muted mb-3" style="font-size:.75rem;">Based on evaluation scores, tenure & history</div>
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

            <!-- Supervisor Feedback -->
            <div class="card section-card mb-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-dark mb-3"><i class="fas fa-comment-dots text-primary me-2"></i>Latest Feedback</h6>
                    <div id="feedbackContent">
                        <div class="text-muted small text-center py-3">Loading...</div>
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
<script>
(function () {
    const BASE = '<?php echo BASE_URL; ?>';

    // ── Helpers ────────────────────────────────────────────────────────────
    function scoreClass(s) {
        if (s >= 3.60) return 'outstanding';
        if (s >= 2.60) return 'exceeds';
        if (s >= 2.00) return 'meets';
        return 'needs';
    }
    function scoreBadgeHtml(s, label) {
        const cls = scoreClass(s);
        const map = {
            outstanding: 'history-badge-outstanding',
            exceeds:     'history-badge-exceeds',
            meets:       'history-badge-meets',
            needs:       'history-badge-needs',
        };
        return `<span class="badge px-2 py-1 ${map[cls]}" style="font-size:.72rem;">${escHtml(label || s.toFixed(2))}</span>`;
    }
    function escHtml(s) {
        if (!s) return '—';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function fmtDate(d) {
        if (!d) return '—';
        try { return new Date(d).toLocaleDateString('en-PH', {year:'numeric',month:'short',day:'numeric'}); } catch(e){return d;}
    }
    function fmtPeriod(start, end) {
        if (!start && !end) return '—';
        if (!start) return fmtDate(end);
        try {
            const s = new Date(start).toLocaleDateString('en-PH', {month:'short', year:'numeric'});
            const e = new Date(end).toLocaleDateString('en-PH', {month:'short', year:'numeric'});
            return `${s} – ${e}`;
        } catch(ex) { return end || start; }
    }

    // ── Fetch ──────────────────────────────────────────────────────────────
    fetch(`${BASE}/employee/ajax/get-my-performance.php`)
        .then(r => r.json())
        .then(data => {
            document.getElementById('perfLoadingState').style.display = 'none';

            if (!data.success || data.total_count === 0) {
                document.getElementById('perfEmptyState').style.display = 'block';
                return;
            }

            document.getElementById('perfContent').style.display = 'block';

            // ── Hero pills ───────────────────────────────────────────────
            document.getElementById('hero-avg-score').textContent = data.avg_score
                ? data.avg_score.toFixed(2) : '—';

            const trendMap = {
                consistently_outstanding: { label: '⭐ Consistently Outstanding', bg: 'linear-gradient(90deg,#CBA135,#a07a1a)', color: '#fff' },
                improving:               { label: '←‘ Improving',  bg: '#dcfce7', color: '#14532d' },
                stable:                  { label: '←’ Stable',     bg: '#e2e3e5', color: '#41464b' },
                declining:               { label: '←“ Declining',  bg: '#fee2e2', color: '#7f1d1d' },
            };
            const tr = trendMap[data.trend] || trendMap['stable'];
            const trendPill = document.getElementById('hero-trend-badge');
            trendPill.textContent = tr.label;
            trendPill.style.cssText = `background:${tr.bg};color:${tr.color};padding:.3rem .75rem;border-radius:.5rem;`;

            // ── Trend chart ──────────────────────────────────────────────
            renderChart(data.chart_labels, data.chart_scores);

            // Trend class badge
            const tcb = document.getElementById('trendClassBadge');
            tcb.textContent = tr.label;
            tcb.style.cssText = `background:${tr.bg};color:${tr.color};`;

            // ── Evaluation history table ─────────────────────────────────
            renderHistory(data.evaluations);

            // ── Career readiness ─────────────────────────────────────────
            renderReadiness(data.readiness);

            // ── Goal tracking ─────────────────────────────────────────────
            renderGoal(data.avg_score, data.next_level, data.points_needed);

            // ── Feedback ─────────────────────────────────────────────────
            renderFeedback(data.latest_eval);
        })
        .catch(() => {
            document.getElementById('perfLoadingState').style.display = 'none';
            document.getElementById('perfEmptyState').style.display = 'block';
        });

    // ── Chart ─────────────────────────────────────────────────────────────
    function renderChart(labels, scores) {
        if (!labels || labels.length === 0) return;
        const ctx = document.getElementById('perfTrendChart').getContext('2d');

        // Color gradient on the line
        const gradient = ctx.createLinearGradient(0, 0, 0, 280);
        gradient.addColorStop(0, 'rgba(8,46,6,0.35)');
        gradient.addColorStop(1, 'rgba(203,161,53,0.05)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Avg Score',
                    data: scores,
                    borderColor: '#082E06',
                    backgroundColor: gradient,
                    borderWidth: 2.5,
                    pointBackgroundColor: scores.map(s =>
                        s >= 3.60 ? '#28a745' : s >= 2.60 ? '#17a2b8' : s >= 2.00 ? '#ffc107' : '#dc3545'
                    ),
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    tension: 0.4,
                    fill: true,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => {
                                const s = ctx.raw;
                                let lvl = s >= 3.60 ? 'Outstanding'
                                    : s >= 2.60 ? 'Exceeds Expectations'
                                    : s >= 2.00 ? 'Meets Expectations'
                                    : 'Needs Improvement';
                                return ` Score: ${s.toFixed(2)} (${lvl})`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        min: 1.0, max: 4.0,
                        ticks: { stepSize: 0.5, font: { size: 11 } },
                        grid: { color: '#f0f0f0' },
                    },
                    x: { ticks: { font: { size: 11 } }, grid: { display: false } }
                }
            }
        });
    }

    // ── History table ─────────────────────────────────────────────────────
    function renderHistory(evals) {
        if (!evals || evals.length === 0) {
            document.getElementById('evalHistoryContainer').innerHTML =
                '<div class="text-muted text-center py-3 small">No approved evaluations found.</div>';
            return;
        }

        let rows = evals.map(ev => {
            const s = parseFloat(ev.total_score) || 0;
            const badgeHtml = scoreBadgeHtml(s, ev.performance_level || s.toFixed(2));
            const shortComments = ev.supervisor_comments
                ? escHtml(ev.supervisor_comments.substring(0, 60)) + (ev.supervisor_comments.length > 60 ? '...' : '')
                : '<span class="opacity-50">—</span>';
            return `
                <tr>
                    <td class="small">${escHtml(fmtPeriod(ev.evaluation_period_start, ev.evaluation_period_end))}</td>
                    <td class="fw-bold text-center">${s.toFixed(2)}</td>
                    <td class="text-center">${badgeHtml}</td>
                    <td class="text-muted small" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${escHtml(ev.supervisor_comments)}">${shortComments}</td>
                </tr>`;
        }).join('');

        let cards = evals.map(ev => {
            const s = parseFloat(ev.total_score) || 0;
            const badgeHtml = scoreBadgeHtml(s, ev.performance_level || s.toFixed(2));
            const period = escHtml(fmtPeriod(ev.evaluation_period_start, ev.evaluation_period_end));
            const approvedDate = ev.approved_date ? `<div class="eval-history-date"><i class="fas fa-check-circle me-1"></i>Approved ${escHtml(fmtDate(ev.approved_date))}</div>` : '';
            const comments = ev.supervisor_comments ? escHtml(ev.supervisor_comments) : '<span class="opacity-50">No supervisor notes recorded.</span>';
            return `
                <article class="eval-history-card">
                    <div class="eval-history-card-head">
                        <div class="min-width-0">
                            <div class="eval-history-period">${period}</div>
                            ${approvedDate}
                        </div>
                        <div class="eval-history-score">
                            <div class="eval-history-score-value">${s.toFixed(2)}</div>
                            <div class="eval-history-score-label">Score</div>
                        </div>
                    </div>
                    <div>${badgeHtml}</div>
                    <div class="eval-history-notes">
                        <div class="eval-history-notes-title">Supervisor Notes</div>
                        <div>${comments}</div>
                    </div>
                </article>`;
        }).join('');

        document.getElementById('evalHistoryContainer').innerHTML = `
            <div class="eval-history-shell">
            <div class="eval-history-table table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size:.85rem;">
                    <thead class="table-light">
                        <tr>
                            <th>Period</th>
                            <th class="text-center">Score</th>
                            <th class="text-center">Level</th>
                            <th>Supervisor Notes</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
            <div class="eval-history-mobile">${cards}</div>
            </div>`;
    }

    // ── Readiness ─────────────────────────────────────────────────────────
    function renderReadiness(pct) {
        const bar   = document.getElementById('readinessBar');
        const label = document.getElementById('readinessLabel');
        const pctEl = document.getElementById('readinessPct');
        const bkEl  = document.getElementById('readinessBreakdown');

        pctEl.textContent = pct + '%';
        setTimeout(() => { bar.style.width = pct + '%'; }, 100);

        let lbl = 'Developing';
        let barColor = '#E1232A';
        if (pct >= 80)      { lbl = 'Ready for Advancement'; barColor = '#082E06'; }
        else if (pct >= 55) { lbl = 'Approaching Readiness'; barColor = '#CBA135'; }
        else if (pct >= 35) { lbl = 'Progressing';           barColor = '#16a34a'; }
        label.textContent = lbl;
        bar.style.background = barColor;

        bkEl.innerHTML = `
            <div class="d-flex justify-content-between"><span>Evaluation Score (60%)</span><span id="r-eval">—</span></div>
            <div class="d-flex justify-content-between"><span>Years of Service (25%)</span><span id="r-svc">—</span></div>
            <div class="d-flex justify-content-between"><span>Evaluation History (15%)</span><span id="r-hist">—</span></div>`;
    }

    // ── Goal ──────────────────────────────────────────────────────────────
    function renderGoal(avgScore, nextLevel, pointsNeeded) {
        const el = document.getElementById('goalTrackingContent');
        if (!avgScore) { el.innerHTML = '<div class="text-muted small text-center py-3">No data yet.</div>'; return; }

        const levels = [
            { label: 'Needs Improvement',  min: 1.00, max: 2.00, color: '#E1232A' },
            { label: 'Meets Expectations', min: 2.00, max: 2.60, color: '#CBA135' },
            { label: 'Exceeds Expectations', min: 2.60, max: 3.60, color: '#16a34a' },
            { label: 'Outstanding',        min: 3.60, max: 4.00, color: '#082E06' },
        ];

        // Full 1–4 progress bar
        const totalRange = 3.0;
        const pct = Math.min(((avgScore - 1.0) / totalRange) * 100, 100);

        let nextMsg = '';
        if (pointsNeeded > 0) {
            nextMsg = `<div class="alert alert-info py-2 px-3 small mt-3 mb-0"><i class="fas fa-info-circle me-1"></i>You need <strong>+${pointsNeeded.toFixed(2)}</strong> more points to reach <strong>${escHtml(nextLevel)}</strong>.</div>`;
        } else {
            nextMsg = `<div class="alert alert-success py-2 px-3 small mt-3 mb-0"><i class="fas fa-star me-1"></i>You've reached the <strong>Outstanding</strong> level! Keep it up!</div>`;
        }

        const levelDots = levels.map(l => {
            const active = avgScore >= l.min;
            return `<div class="text-center" style="flex:1;font-size:.65rem;">
                        <div style="width:10px;height:10px;border-radius:50%;background:${active ? l.color : '#dee2e6'};margin:0 auto 3px;"></div>
                        <span style="color:${active ? l.color : '#aaa'};font-weight:${active?'600':'400'};">${l.label.split(' ')[0]}</span>
                    </div>`;
        }).join('');

        el.innerHTML = `
            <div class="mb-2">
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Current Avg</span>
                    <span class="fw-bold">${avgScore.toFixed(2)}</span>
                </div>
                <div class="bg-light rounded-pill" style="height:10px;overflow:hidden;">
                    <div class="goal-progress" style="width:${pct}%;background:${avgScore>=3.60?'#082E06':avgScore>=2.60?'#16a34a':avgScore>=2.00?'#CBA135':'#E1232A'};transition:width 1s ease;"></div>
                </div>
                <div class="d-flex mt-1" style="gap:0;">${levelDots}</div>
            </div>
            ${nextMsg}`;
        // Animate
        setTimeout(() => {
            const bar = el.querySelector('.goal-progress');
            if (bar) bar.style.width = pct + '%';
        }, 200);
    }

    // ── Feedback ──────────────────────────────────────────────────────────
    function renderFeedback(latestEval) {
        const el = document.getElementById('feedbackContent');
        if (!latestEval) {
            el.innerHTML = '<div class="text-muted small text-center py-3">No feedback available yet.</div>';
            return;
        }

        const fields = [
            { key: 'supervisor_comments',  label: 'Supervisor', icon: 'fas fa-user-tie' },
            { key: 'manager_comments',     label: 'Manager',    icon: 'fas fa-briefcase' },
            { key: 'evaluator_comments',   label: 'HR Notes',   icon: 'fas fa-clipboard' },
        ];

        let html = '';
        let hasAny = false;
        fields.forEach(f => {
            const val = latestEval[f.key];
            if (val && val.trim()) {
                hasAny = true;
                html += `
                    <div class="insight-card p-3 mb-3">
                        <div class="d-flex align-items-center mb-2 gap-2">
                            <i class="${f.icon} text-primary" style="font-size:.8rem;"></i>
                            <span class="fw-semibold small">${f.label}</span>
                        </div>
                        <p class="mb-0 small text-muted" style="line-height:1.6;">${escHtml(val)}</p>
                    </div>`;
            }
        });

        if (!hasAny) {
            el.innerHTML = '<div class="text-muted small text-center py-3">No written feedback from your latest evaluation.</div>';
        } else {
            const period = fmtPeriod(latestEval.evaluation_period_start, latestEval.evaluation_period_end);
            el.innerHTML = `<div class="text-muted mb-2" style="font-size:.72rem;"><i class="fas fa-calendar-alt me-1"></i>From evaluation: ${period}</div>${html}`;
        }
    }
})();
</script>

<?php require_once '../includes/footer.php'; ?>

