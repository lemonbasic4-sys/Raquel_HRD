<?php
$page_title = 'Succession Planning';
require_once '../includes/session-check.php';
checkRole(['HR Manager']);
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Fetch departments, branches, and active job titles for filter dropdowns
$depts_res    = $conn->query("SELECT department_id, department_name FROM departments WHERE is_active = 1 ORDER BY department_name ASC");
$branches_res = $conn->query("SELECT branch_id, branch_name FROM branches WHERE is_active = 1 ORDER BY branch_name ASC");
$positions_res= $conn->query("SELECT DISTINCT job_title FROM employees WHERE job_title IS NOT NULL AND job_title != '' AND is_active = 1 ORDER BY job_title ASC");
?>

<div class="succession-page">
<div class="page-hero succession-hero fadeup mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">HR Manager · Career Movements</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-user-tie me-2" style="color:#BD9414;"></i>Career Movements & Succession Planning</h4>
            <p class="text-white-50 small mb-0 mt-1">Identify top performing candidates for key positions, promotions, and internal transfers based on historical performance evaluations.</p>
        </div>
        <div>
            <a href="career-movements.php" class="btn btn-outline-light btn-sm"><i class="fas fa-exchange-alt me-1"></i> View Movement Logs</a>
        </div>
    </div>

    <!-- Rating Scale Legend -->
    <div class="d-flex flex-wrap gap-2 pt-2 border-top border-white-10">
        <span class="badge" style="background:rgba(40,167,69,0.2); border:1px solid rgba(40,167,69,0.4); color:#96e0a8;">
            <i class="fas fa-star me-1"></i> Outstanding (3.60 – 4.00)
        </span>
        <span class="badge" style="background:rgba(23,162,184,0.2); border:1px solid rgba(23,162,184,0.4); color:#9de0ec;">
            <i class="fas fa-thumbs-up me-1"></i> Exceeds Expectations (2.60 – 3.59)
        </span>
        <span class="badge" style="background:rgba(255,193,7,0.2); border:1px solid rgba(255,193,7,0.4); color:#ffe699;">
            <i class="fas fa-check-circle me-1"></i> Meets Expectations (2.00 – 2.59)
        </span>
        <span class="badge" style="background:rgba(220,53,69,0.2); border:1px solid rgba(220,53,69,0.4); color:#f5a3ab;">
            <i class="fas fa-exclamation-triangle me-1"></i> Needs Improvement (1.00 – 1.99)
        </span>
    </div>
</div>

<!-- Controls & Filters -->
<div class="succession-filter-card mb-4">
    <div class="card-body p-3 p-xl-4">
        <form id="successionFilterForm" class="row g-3 align-items-end">
            <!-- Scope Filter -->
            <div class="col-md-3">
                <label class="form-label fw-bold text-dark small mb-1"><i class="fas fa-globe me-1 text-primary"></i> Evaluation Scope</label>
                <select class="form-select form-select-sm rounded-3" id="filterScope" name="scope">
                    <option value="company" selected>Company-Wide (All Employees)</option>
                    <option value="branch">By Branch</option>
                    <option value="department">By Department</option>
                </select>
            </div>

            <!-- Branch Select (shown if branch scope) -->
            <div class="col-md-3" id="branchFilterContainer" style="display:none;">
                <label class="form-label fw-bold text-dark small mb-1"><i class="fas fa-building me-1 text-success"></i> Branch</label>
                <select class="form-select form-select-sm rounded-3" id="filterBranch" name="branch_id">
                    <option value="0">Select Branch...</option>
                    <?php while ($b = $branches_res->fetch_assoc()): ?>
                        <option value="<?php echo $b['branch_id']; ?>"><?php echo e($b['branch_name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <!-- Department Select (shown if department scope) -->
            <div class="col-md-3" id="deptFilterContainer" style="display:none;">
                <label class="form-label fw-bold text-dark small mb-1"><i class="fas fa-sitemap me-1 text-info"></i> Department</label>
                <select class="form-select form-select-sm rounded-3" id="filterDept" name="dept_id">
                    <option value="0">Select Department...</option>
                    <?php while ($d = $depts_res->fetch_assoc()): ?>
                        <option value="<?php echo $d['department_id']; ?>"><?php echo e($d['department_name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <!-- Target Position -->
            <div class="col-md-4">
                <label class="form-label fw-bold text-dark small mb-1"><i class="fas fa-briefcase me-1 text-warning"></i> Target Position / Role</label>
                <select class="form-select form-select-sm rounded-3" id="filterPosition" name="position">
                    <option value="">All Qualified Positions</option>
                    <?php while ($p = $positions_res->fetch_assoc()): ?>
                        <option value="<?php echo e($p['job_title']); ?>"><?php echo e($p['job_title']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="col-md-2 text-end">
                <button type="button" id="btnSearchCandidates" class="btn btn-primary btn-sm rounded-3 w-100 fw-bold succession-analyze-btn">
                    <i class="fas fa-search me-1"></i> Analyze Candidates
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Results Section -->
<style>
.succession-page {
    --succession-ink: #1C271B;
    --succession-muted: #5E6B5C;
    --succession-line: #E3E9DD;
    --succession-soft: #F7FAF4;
    --succession-primary: #294306;
    --succession-gold: #BD9414;
}
.succession-hero .badge {
    border-radius: 999px;
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: 0;
    padding: .4rem .7rem;
}
.succession-filter-card,
.succession-panel {
    background: #ffffff;
    border: 1px solid var(--succession-line);
    border-radius: 16px;
    box-shadow: 0 12px 30px rgba(8, 46, 6, .07);
}
.succession-filter-card .form-label {
    color: var(--succession-ink) !important;
    font-size: .74rem;
    letter-spacing: 0;
}
.succession-filter-card .form-select {
    min-height: 40px;
    border-color: #DCE5D6;
    box-shadow: none;
}
.succession-filter-card .form-select:focus {
    border-color: var(--succession-primary);
    box-shadow: 0 0 0 .18rem rgba(41, 67, 6, .12);
}
.succession-analyze-btn {
    min-height: 40px;
}
.succession-page .min-width-0 {
    min-width: 0;
}
.succession-workspace {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
    align-items: start;
    margin-bottom: 1.5rem;
}
.succession-panel {
    overflow: hidden;
}
.succession-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 1.1rem;
    border-bottom: 1px solid var(--succession-line);
    background: linear-gradient(180deg, #ffffff 0%, var(--succession-soft) 100%);
}
.succession-panel-title {
    display: flex;
    align-items: center;
    gap: .55rem;
    color: var(--succession-ink);
    font-size: .98rem;
    font-weight: 800;
    margin: 0;
}
.succession-panel-title i {
    color: var(--succession-gold);
}
.succession-count-badge {
    background: #F1F5EC !important;
    border: 1px solid #D8E2D0 !important;
    color: var(--succession-muted) !important;
    font-size: .72rem;
    font-weight: 800;
    letter-spacing: 0;
}
.succession-table-wrap {
    overflow-x: auto;
}
.succession-table {
    min-width: 980px;
    border-collapse: separate;
    border-spacing: 0 .55rem;
    margin: -.25rem 0 .35rem;
    padding: 0 .65rem;
}
.succession-table thead th {
    border: 0;
    color: var(--succession-muted);
    font-size: .7rem;
    font-weight: 800;
    letter-spacing: 0;
    padding: .65rem .7rem .35rem;
    text-transform: uppercase;
    white-space: nowrap;
}
.succession-table tbody td {
    background: #ffffff;
    border-top: 1px solid var(--succession-line);
    border-bottom: 1px solid var(--succession-line);
    padding: .9rem .7rem;
    vertical-align: middle;
}
.succession-table tbody td:first-child {
    border-left: 1px solid var(--succession-line);
    border-radius: 12px 0 0 12px;
}
.succession-table tbody td:last-child {
    border-right: 1px solid var(--succession-line);
    border-radius: 0 12px 12px 0;
}
.candidate-row:hover {
    transform: translateY(-1px);
}
.candidate-row {
    transition: transform .16s ease;
}
.candidate-row.table-active {
    outline: 2px solid rgba(41, 67, 6, .18);
    outline-offset: -2px;
}
.candidate-row.table-active td {
    background: #F6FAF1;
}
.succession-rank {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: #F1F5EC;
    color: var(--succession-ink);
    font-weight: 850;
}
.succession-candidate-name {
    color: var(--succession-ink);
    font-size: .9rem;
    font-weight: 800;
    line-height: 1.2;
}
.succession-subtext {
    color: var(--succession-muted);
    font-size: .74rem;
    line-height: 1.35;
}
.succession-score-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 58px;
    border-radius: 999px;
    background: rgba(15, 107, 46, .12);
    border: 1px solid rgba(15, 107, 46, .35);
    color: #0F6B2E;
    font-size: .9rem;
    font-weight: 850;
    padding: .28rem .65rem;
}
.succession-detail-panel {
    position: relative;
}
.succession-detail-body {
    padding: 1.25rem;
}
.succession-empty {
    display: grid;
    place-items: center;
    min-height: 330px;
    color: var(--succession-muted);
    text-align: center;
}
.succession-detail-panel.is-empty .succession-detail-body {
    padding: 1rem 1.1rem;
}
.succession-detail-panel.is-empty .succession-empty {
    min-height: 110px;
}
.succession-detail-profile {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0 0 1.15rem;
    border-bottom: 1px solid rgba(255,255,255,.18);
    min-width: 0;
}
.succession-detail-info { flex: 1; min-width: 0; }
.succession-avatar {
    width: var(--avatar-size);
    height: var(--avatar-size);
    flex: 0 0 var(--avatar-size);
    border-radius: 50%;
    overflow: hidden;
    display: inline-grid;
    place-items: center;
    background: rgba(255, 255, 255, .2);
    color: #fff;
    font-weight: 800;
}
.succession-avatar img { display: block; width: 100%; height: 100%; object-fit: cover; }
.succession-avatar img[hidden] { display: none; }
.succession-avatar .avatar-fallback { display: grid; width: 100%; height: 100%; place-items: center; }
.succession-avatar .avatar-fallback[hidden] { display: none; }
.succession-table .succession-avatar { background: var(--succession-primary); }
.succession-avatar .avatar-fallback i { font-size: calc(var(--avatar-size) * .38); }
.succession-detail-panel.page-hero { padding: 0; background: linear-gradient(135deg, var(--hero-from), var(--hero-mid), var(--hero-to)); }
.succession-detail-panel .succession-panel-header { background: rgba(255,255,255,.08); border-color: rgba(255,255,255,.18); }
.succession-detail-panel .succession-panel-title,
.succession-detail-panel .succession-detail-name,
.succession-detail-panel .succession-metric strong { color: #fff; }
.succession-detail-panel .succession-panel-title i { color: #efcf6b; }
.succession-detail-panel .succession-detail-body { position: relative; }
.succession-detail-panel .succession-detail-profile .badge,
.succession-detail-panel .succession-metric { background: rgba(255,255,255,.12) !important; border-color: rgba(255,255,255,.18) !important; color: #fff !important; }
.succession-detail-panel .succession-metric span { color: rgba(255,255,255,.72); }
.succession-detail-panel .succession-metric.score strong { color: #fff; }
.succession-detail-panel .succession-empty { color: rgba(255,255,255,.78); }
.succession-detail-panel .succession-detail-actions .btn-outline-primary { color: #fff; border-color: rgba(255,255,255,.65); }
.succession-detail-panel .succession-detail-actions .btn-outline-primary:hover { background: rgba(255,255,255,.18); }
.succession-detail-name {
    color: var(--succession-ink);
    font-size: 1.15rem;
    font-weight: 850;
    margin: 0 0 .25rem;
}
.succession-metric-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: .75rem;
    margin-top: 1rem;
}
.succession-metric {
    background: var(--succession-soft);
    border: 1px solid var(--succession-line);
    border-radius: 12px;
    padding: .85rem;
    min-width: 0;
}
.succession-metric span {
    color: var(--succession-muted);
    display: block;
    font-size: .68rem;
    font-weight: 800;
    margin-bottom: .25rem;
    text-transform: uppercase;
}
.succession-metric strong {
    color: var(--succession-ink);
    display: block;
    font-size: .86rem;
    line-height: 1.25;
}
.succession-metric.score strong {
    color: #0F6B2E;
    font-size: 1.15rem;
}
.succession-detail-actions .btn {
    min-height: 42px;
    white-space: nowrap;
}
.succession-detail-panel:not(.is-empty) .succession-detail-body { min-height: 0; }
@media (max-width: 1199.98px) {
    .succession-panel.leaderboard {
        min-height: 560px;
    }
    .succession-metric-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 767.98px) {
    .succession-detail-profile { flex-wrap: wrap; }
    .succession-detail-actions { width: 100%; }
    .succession-detail-actions .btn { width: 100%; }
    .succession-metric-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .succession-metric:last-child { grid-column: 1 / -1; }
}
@media (max-width: 420px) {
    .succession-metric-grid { grid-template-columns: 1fr; }
    .succession-metric:last-child { grid-column: auto; }
}
</style>

<div class="succession-workspace">
    <!-- Candidate Breakdown & Recommendation Panel -->
    <div class="succession-panel succession-detail-panel page-hero fadeup is-empty" id="candidateDetailPanel">
            <div class="succession-panel-header">
                <h6 class="succession-panel-title"><i class="fas fa-user-check"></i>Candidate Details</h6>
            </div>
            <div class="succession-detail-body" id="candidateDetailCard">
                <div class="succession-empty">
                    <div>
                    <i class="fas fa-hand-pointer fa-2x mb-2 opacity-50"></i>
                    <p class="small mb-0">Select a candidate from the table to inspect their evaluation summary.</p>
                    </div>
                </div>
            </div>
    </div>

    <!-- Candidates Leaderboard Table -->
    <div class="succession-panel leaderboard">
            <div class="succession-panel-header">
                <h6 class="succession-panel-title"><i class="fas fa-award"></i>Top 10 Succession Candidates</h6>
                <span class="badge succession-count-badge px-2 py-1 small" id="resultCountBadge">10 Candidates Ranked</span>
            </div>
            <div class="p-0">
                <div class="succession-table-wrap">
                    <table class="table table-hover align-middle mb-0 succession-table" id="candidatesTable">
                        <thead class="bg-light text-muted small uppercase">
                            <tr>
                                <th class="ps-3" style="width: 50px;">Rank</th>
                                <th>Candidate</th>
                                <th>Current Position & Dept</th>
                                <th class="text-center">Yrs Service</th>
                                <th class="text-center">Avg Rating</th>
                                <th class="text-center">Trend</th>
                                <th class="pe-3 text-end">Recommendation</th>
                            </tr>
                        </thead>
                        <tbody id="candidatesTableBody">
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                    Analyzing performance evaluation records...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
    </div>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const scopeSelect = document.getElementById('filterScope');
    const branchContainer = document.getElementById('branchFilterContainer');
    const deptContainer = document.getElementById('deptFilterContainer');
    const btnSearch = document.getElementById('btnSearchCandidates');
    const tableBody = document.getElementById('candidatesTableBody');
    const detailCard = document.getElementById('candidateDetailCard');
    const detailPanel = document.getElementById('candidateDetailPanel');

    function getAvatarHtml(c, size = 34) {
        const avatarUrl = c ? (c.avatar_url || '') : '';
        const fallback = `<span class="avatar-fallback"${avatarUrl && !avatarUrl.includes('/logo/logo.png') ? ' hidden' : ''}><i class="fas fa-user" aria-hidden="true"></i></span>`;
        if (avatarUrl && !avatarUrl.includes('/logo/logo.png')) {
            return `<span class="succession-avatar" style="--avatar-size:${size}px"><img src="${escapeHtml(avatarUrl)}" alt="" onerror="this.hidden=true;this.nextElementSibling.hidden=false">${fallback}</span>`;
        }
        return `<span class="succession-avatar" style="--avatar-size:${size}px">${fallback}</span>`;
    }

    scopeSelect.addEventListener('change', function() {
        if (this.value === 'branch') {
            branchContainer.style.display = 'block';
            deptContainer.style.display = 'none';
        } else if (this.value === 'department') {
            branchContainer.style.display = 'none';
            deptContainer.style.display = 'block';
        } else {
            branchContainer.style.display = 'none';
            deptContainer.style.display = 'none';
        }
    });

    function loadCandidates() {
        tableBody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-5 text-muted">
                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                    Calculating evaluation ratings and succession scores...
                </td>
            </tr>`;

        const scope = scopeSelect.value;
        const branchId = document.getElementById('filterBranch').value;
        const deptId = document.getElementById('filterDept').value;
        const position = document.getElementById('filterPosition').value;

        const url = `ajax/get-succession-candidates.php?scope=${scope}&branch_id=${branchId}&dept_id=${deptId}&position=${encodeURIComponent(position)}`;

        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (!data.success || !data.candidates || data.candidates.length === 0) {
                    document.getElementById('resultCountBadge').textContent = '0 Candidates Ranked';
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fas fa-folder-open fa-2x mb-2 opacity-50"></i>
                                <p class="mb-0">No eligible employees found with approved evaluations for the selected filter.</p>
                            </td>
                        </tr>`;
                    detailCard.innerHTML = `<div class="text-center py-5 text-muted"><p class="small">No candidates available.</p></div>`;
                    if (detailPanel) detailPanel.classList.add('is-empty');
                    return;
                }

                window.candidatesList = data.candidates;
                document.getElementById('resultCountBadge').textContent = `${data.candidates.length} Candidate${data.candidates.length === 1 ? '' : 's'} Ranked`;
                let html = '';
                data.candidates.forEach((c, idx) => {
                    const rank = idx + 1;
                    let medalBadge = `<span class="succession-rank">#${rank}</span>`;
                    if (rank === 1) medalBadge = `<i class="fas fa-crown text-warning fa-lg" title="Top 1 Candidate"></i>`;
                    else if (rank === 2) medalBadge = `<i class="fas fa-medal text-secondary fa-lg" title="Rank 2"></i>`;
                    else if (rank === 3) medalBadge = `<i class="fas fa-medal fa-lg" style="color:#cd7f32;" title="Rank 3"></i>`;

                    let trendIcon = '<span class="badge bg-light text-dark"><i class="fas fa-minus text-muted me-1"></i> Stable</span>';
                    if (c.trend === 'improving') trendIcon = '<span class="badge bg-success-subtle text-success"><i class="fas fa-arrow-up me-1"></i> Improving</span>';
                    else if (c.trend === 'declining') trendIcon = '<span class="badge bg-danger-subtle text-danger"><i class="fas fa-arrow-down me-1"></i> Declining</span>';

                    let badgeClass = 'bg-primary';
                    if (c.badge === 'Highly Recommended') badgeClass = 'bg-success';
                    else if (c.badge === 'Recommended') badgeClass = 'bg-info text-dark';
                    else if (c.badge === 'Qualified') badgeClass = 'bg-warning text-dark';

                    const avatarHtml = getAvatarHtml(c, 34);

                    html += `
                        <tr class="candidate-row" data-index="${idx}" style="cursor:pointer;">
                            <td class="ps-3 text-center">${medalBadge}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    ${avatarHtml}
                                    <div class="min-width-0">
                                        <div class="succession-candidate-name">${escapeHtml(c.full_name)}</div>
                                        <div class="succession-subtext">${escapeHtml(c.branch_name || 'Main')}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="small fw-bold text-dark">${escapeHtml(c.job_title || 'N/A')}</div>
                                <div class="succession-subtext">${escapeHtml(c.department_name || 'N/A')}</div>
                            </td>
                            <td class="text-center small">${c.years_of_service} yrs</td>
                            <td class="text-center">
                                <span class="succession-score-pill">${c.avg_score.toFixed(2)}</span>
                                <div class="text-muted" style="font-size:0.68rem;">(${c.eval_count} eval${c.eval_count > 1 ? 's' : ''})</div>
                            </td>
                            <td class="text-center">${trendIcon}</td>
                            <td class="pe-3 text-end">
                                <span class="badge ${badgeClass} px-2 py-1">${c.badge}</span>
                            </td>
                        </tr>`;
                });

                tableBody.innerHTML = html;

                // Add row click listeners
                document.querySelectorAll('.candidate-row').forEach(row => {
                    row.addEventListener('click', function() {
                        document.querySelectorAll('.candidate-row').forEach(r => r.classList.remove('table-active'));
                        this.classList.add('table-active');
                        const index = parseInt(this.getAttribute('data-index'));
                        if (window.candidatesList && window.candidatesList[index]) {
                            renderCandidateDetails(window.candidatesList[index]);
                        }
                    });
                });

                // Auto select first candidate
                if (data.candidates.length > 0) {
                    const firstRow = document.querySelector('.candidate-row[data-index="0"]');
                    if (firstRow) firstRow.classList.add('table-active');
                    renderCandidateDetails(data.candidates[0]);
                }
            })
            .catch(err => {
                document.getElementById('resultCountBadge').textContent = 'Unable to Load';
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-4 text-danger">
                            <i class="fas fa-exclamation-triangle me-1"></i> Error loading succession candidates.
                        </td>
                    </tr>`;
            });
    }

    window.renderCandidateDetails = function(c) {
        if (detailPanel) detailPanel.classList.remove('is-empty');
        const detailAvatarHtml = getAvatarHtml(c, 65);
        detailCard.innerHTML = `
            <div class="succession-detail-profile">
                ${detailAvatarHtml}
                <div class="succession-detail-info">
                    <h6 class="succession-detail-name">${escapeHtml(c.full_name)}</h6>
                    <span class="badge bg-light text-dark border small">${escapeHtml(c.job_title || 'N/A')}</span>
                </div>
                <div class="succession-detail-actions">
                    <a href="view-employee.php?id=${c.employee_id}" class="btn btn-outline-primary btn-sm rounded-3">
                        <i class="fas fa-user me-1"></i> View Full Employee Profile
                    </a>
                </div>
            </div>

            <div class="succession-metric-grid">
                <div class="succession-metric">
                    <span><i class="fas fa-sitemap me-1" aria-hidden="true"></i>Department</span>
                    <strong>${escapeHtml(c.department_name || 'N/A')}</strong>
                </div>
                <div class="succession-metric">
                    <span><i class="fas fa-building me-1" aria-hidden="true"></i>Branch</span>
                    <strong>${escapeHtml(c.branch_name || 'N/A')}</strong>
                </div>
                <div class="succession-metric">
                    <span><i class="fas fa-calendar-alt me-1" aria-hidden="true"></i>Service</span>
                    <strong>${c.years_of_service} Years</strong>
                </div>
                <div class="succession-metric score">
                    <span><i class="fas fa-star me-1" aria-hidden="true"></i>Average Rating</span>
                    <strong>${c.avg_score.toFixed(2)} / 4.00</strong>
                </div>
                <div class="succession-metric">
                    <span><i class="fas fa-award me-1" aria-hidden="true"></i>Performance Classification</span>
                    <strong>${escapeHtml(c.performance_level || 'N/A')}</strong>
                </div>
            </div>`;
    };

    function escapeHtml(str) {
        return (str || '').replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    btnSearch.addEventListener('click', loadCandidates);
    loadCandidates();
});
</script>

<?php require_once '../includes/footer.php'; ?>
