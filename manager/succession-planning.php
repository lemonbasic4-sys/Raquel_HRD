<?php
$page_title = 'Succession Planning';
require_once '../includes/session-check.php';
checkRole(['HR Manager', 'HR Supervisor']);
require_once '../includes/functions.php';
require_once '../includes/succession-endorsements.php';
$isSuccessionSupervisor = ($_SESSION['role'] ?? '') === 'HR Supervisor';
$successionTableReady = successionEndorsementTableReady($conn);
successionHandlePost($conn);
require_once '../includes/header.php';

// Fetch departments, branches, and active job titles for filter dropdowns
$depts_res    = $conn->query("SELECT department_id, department_name FROM departments WHERE is_active = 1 ORDER BY department_name ASC");
$branches_res = $conn->query("SELECT branch_id, branch_name FROM branches WHERE is_active = 1 ORDER BY branch_name ASC");
$positions_res= $conn->query("SELECT DISTINCT job_title FROM employees WHERE job_title IS NOT NULL AND job_title != '' AND is_active = 1 ORDER BY job_title ASC");
$successionEndorsements = $successionTableReady ? successionListEndorsements($conn) : [];
$successionCeoAvailable = successionHasCeoAccount($conn);
?>

<div class="succession-page">
<?php if (!empty($_SESSION['succession_flash'])): $successionFlash = $_SESSION['succession_flash']; unset($_SESSION['succession_flash']); ?>
<div class="alert alert-<?php echo e($successionFlash[0]); ?>" role="status"><?php echo e($successionFlash[1]); ?></div>
<?php endif; ?>
<?php if (!$successionTableReady): ?><div class="alert alert-warning">Succession endorsements need the database migration: <code>database/succession_endorsements.sql</code>.</div><?php endif; ?>
<div class="page-hero succession-hero fadeup mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);"><?php echo $isSuccessionSupervisor ? 'HR Supervisor' : 'HR Manager'; ?> · Succession Planning</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-user-tie me-2" style="color:#BD9414;"></i>Succession Planning</h4>
            <p class="text-white-50 small mb-0 mt-1">Review promotion candidates, their supporting evidence, and endorsement decisions. A high rating alone does not approve a promotion.</p>
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

            <!-- Current Position -->
            <div class="col-md-4">
                <label class="form-label fw-bold text-dark small mb-1"><i class="fas fa-briefcase me-1 text-warning"></i> Current Position / Role</label>
                <select class="form-select form-select-sm rounded-3" id="filterPosition" name="position">
                    <option value="">All Current Positions</option>
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
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/succession-planning.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/succession-planning.css'); ?>">

<div class="succession-workspace">
    <!-- Candidates Leaderboard Table -->
    <div class="succession-panel leaderboard">
            <div class="succession-panel-header">
                <h6 class="succession-panel-title"><i class="fas fa-award"></i>Top 10 by Approved Evaluation Score</h6>
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
                                <th class="pe-3 text-end">Evaluation Signal</th>
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
<?php if ($successionTableReady): ?>
<?php require '../includes/succession-endorsements-view.php'; ?>
<?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const scopeSelect = document.getElementById('filterScope');
    const branchContainer = document.getElementById('branchFilterContainer');
    const deptContainer = document.getElementById('deptFilterContainer');
    const btnSearch = document.getElementById('btnSearchCandidates');
    const tableBody = document.getElementById('candidatesTableBody');

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

        const url = `<?php echo BASE_URL; ?>/manager/ajax/get-succession-candidates.php?scope=${scope}&branch_id=${branchId}&dept_id=${deptId}&position=${encodeURIComponent(position)}`;

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
                    return;
                }

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
                    if (c.badge === 'Top Evaluation Score') badgeClass = 'bg-success';
                    else if (c.badge === 'Strong Evaluation Score') badgeClass = 'bg-info text-dark';
                    else if (c.badge === 'Above Expectations') badgeClass = 'bg-warning text-dark';

                    const avatarHtml = getAvatarHtml(c, 34);

                    html += `
                        <tr class="candidate-row" data-href="<?php echo BASE_URL . '/' . ($isSuccessionSupervisor ? 'supervisor' : 'manager'); ?>/succession-candidate.php?employee_id=${c.employee_id}" style="cursor:pointer;">
                            <td class="ps-3 text-center">${medalBadge}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    ${avatarHtml}
                                    <div class="min-width-0">
                                        <a class="succession-candidate-name text-decoration-none" href="<?php echo BASE_URL . '/' . ($isSuccessionSupervisor ? 'supervisor' : 'manager'); ?>/succession-candidate.php?employee_id=${c.employee_id}">${escapeHtml(c.full_name)}</a>
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

                document.querySelectorAll('.candidate-row').forEach(row => {
                    row.addEventListener('click', event => {
                        if (!event.target.closest('a')) window.location.href = row.dataset.href;
                    });
                });
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

    function escapeHtml(str) {
        return (str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    btnSearch.addEventListener('click', loadCandidates);
    loadCandidates();
});
</script>

<?php require_once '../includes/footer.php'; ?>
