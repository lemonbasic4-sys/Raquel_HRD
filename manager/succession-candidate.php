<?php
$page_title = 'Succession Candidate Details';
require_once '../includes/session-check.php';
checkRole(['HR Manager', 'HR Supervisor', 'Employee', 'President and CEO']);
require_once '../includes/functions.php';
require_once '../includes/succession-endorsements.php';
$isSuccessionSupervisor = ($_SESSION['role'] ?? '') === 'HR Supervisor';
$isSuccessionCeo = in_array(($_SESSION['role'] ?? ''), ['Employee', 'President and CEO'], true);
if ($isSuccessionCeo && !successionIsCeoUser($conn, (int)$_SESSION['user_id'])) {
    http_response_code(403);
    exit('Candidate evidence is available only to authorized reviewers.');
}
$successionTableReady = successionEndorsementTableReady($conn);
successionHandlePost($conn);
$employeeId = (int)($_GET['employee_id'] ?? 0);
$pendingEndorsement = null;
if ($isSuccessionSupervisor && $successionTableReady && $employeeId > 0) {
    $pendingStmt = $conn->prepare("SELECT se.endorsement_id,se.status,j.job_title AS target_title FROM succession_endorsements se JOIN job_titles j ON j.job_title_id=se.target_job_title_id WHERE se.employee_id=? AND se.status IN ('Pending HR Review','Pending CEO Approval') ORDER BY se.endorsement_id DESC LIMIT 1");
    $pendingStmt->bind_param('i', $employeeId);
    $pendingStmt->execute();
    $pendingEndorsement = $pendingStmt->get_result()->fetch_assoc();
    $pendingStmt->close();
}
if ($isSuccessionCeo) {
    $access = $conn->prepare("SELECT 1 FROM succession_endorsements WHERE employee_id=? AND status IN ('Pending CEO Approval','Approved','Rejected') LIMIT 1");
    $access->bind_param('i', $employeeId);
    $access->execute();
    $canView = $access->get_result()->num_rows > 0;
    $access->close();
    if (!$canView) { http_response_code(403); exit('No reviewed endorsement is available for this candidate.'); }
}
$successionTargetPositions = $conn->query("SELECT j.job_title_id,j.job_title,j.department_id,d.department_name FROM job_titles j LEFT JOIN departments d ON d.department_id=j.department_id WHERE j.is_active=1 ORDER BY d.department_name,j.job_title")->fetch_all(MYSQLI_ASSOC);
require_once '../includes/header.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/succession-planning.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/succession-planning.css'); ?>">
<div class="succession-page">
    <div class="page-hero succession-hero fadeup mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="small text-white-50 text-uppercase"><?php echo $isSuccessionCeo ? 'President and CEO' : ($isSuccessionSupervisor ? 'HR Supervisor' : 'HR Manager'); ?> · Succession Planning</div>
                <h4 class="text-white fw-bold mb-1"><i class="fas fa-user-check me-2" style="color:#efcf6b"></i>Candidate Details</h4>
                <p class="text-white-50 small mb-0">Performance, position history, and recorded credentials for endorsement review.</p>
            </div>
            <a class="btn btn-outline-light btn-sm" href="succession-planning.php"><i class="fas fa-arrow-left me-1"></i>Back to Candidates</a>
        </div>
    </div>
    <?php if (!empty($_SESSION['succession_flash'])): $successionFlash = $_SESSION['succession_flash']; unset($_SESSION['succession_flash']); ?>
    <div class="alert alert-<?php echo e($successionFlash[0]); ?>" role="status"><?php echo e($successionFlash[1]); ?></div>
    <?php endif; ?>
    <?php if (!$successionTableReady): ?><div class="alert alert-warning">Succession endorsements need the database migration: <code>database/succession_endorsements.sql</code>.</div><?php endif; ?>
    <div class="succession-panel succession-detail-panel fadeup" id="candidateDetailPanel">
        <div class="succession-panel-header"><h6 class="succession-panel-title"><i class="fas fa-user-check"></i>Candidate Details</h6></div>
        <div class="succession-detail-body" id="candidateDetailCard"><div class="succession-empty"><div class="spinner-border spinner-border-sm text-success me-2" role="status"></div>Loading candidate evidence...</div></div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const detailCard = document.getElementById('candidateDetailCard');
    const detailPanel = document.getElementById('candidateDetailPanel');
    const targetPositions = <?php echo json_encode($successionTargetPositions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const employeeId = <?php echo $employeeId; ?>;
    let selectedEvidenceEmployeeId = 0;

    function getAvatarHtml(c, size = 34) {
        const avatarUrl = c ? (c.avatar_url || '') : '';
        const fallback = `<span class="avatar-fallback"${avatarUrl && !avatarUrl.includes('/logo/logo.png') ? ' hidden' : ''}><i class="fas fa-user" aria-hidden="true"></i></span>`;
        if (avatarUrl && !avatarUrl.includes('/logo/logo.png')) {
            return `<span class="succession-avatar" style="--avatar-size:${size}px"><img src="${escapeHtml(avatarUrl)}" alt="" onerror="this.hidden=true;this.nextElementSibling.hidden=false">${fallback}</span>`;
        }
        return `<span class="succession-avatar" style="--avatar-size:${size}px">${fallback}</span>`;
    }

    window.renderCandidateDetails = function(c) {
        if (detailPanel) detailPanel.classList.remove('is-empty');
        const detailAvatarHtml = getAvatarHtml(c, 65);
        detailCard.innerHTML = `
            <div class="succession-detail-profile">
                ${detailAvatarHtml}
                <div class="succession-detail-info">
                    <h6 class="succession-detail-name">${escapeHtml(c.full_name)}</h6>
                    <div class="succession-profile-meta"><span><i class="fas fa-id-badge me-1"></i>${escapeHtml(c.employee_code || 'No employee ID')}</span><span><i class="fas fa-briefcase me-1"></i>${escapeHtml(c.job_title || 'N/A')}</span><span><i class="fas fa-sitemap me-1"></i>${escapeHtml(c.department_name || 'N/A')}</span><span><i class="fas fa-map-marker-alt me-1"></i>${escapeHtml(c.branch_name || 'N/A')}</span></div>
                </div>
                <span class="succession-active-badge"><i class="fas fa-circle me-1" style="font-size:.5rem"></i>Active Employee</span>
            </div>

            <div id="successionSummarySource"><div class="succession-metric-grid">
                <div class="succession-metric page-hero fadeup">
                    <span class="metric-icon"><i class="fas fa-briefcase" aria-hidden="true"></i></span>
                    <span>Current Position</span>
                    <strong>${escapeHtml(c.job_title || 'N/A')}</strong>
                    <small>Tenure: <span id="candidatePositionTenure">Loading...</span></small>
                </div>
                <div class="succession-metric page-hero fadeup">
                    <span class="metric-icon"><i class="fas fa-calendar-alt" aria-hidden="true"></i></span>
                    <span>Company Service</span>
                    <strong>${c.years_of_service} Years</strong>
                    <small>${c.eval_count} approved evaluation${c.eval_count === 1 ? '' : 's'}</small>
                </div>
                <div class="succession-metric score page-hero fadeup">
                    <span class="metric-icon"><i class="fas fa-star" aria-hidden="true"></i></span>
                    <span>Approved Evaluation Average</span>
                    <strong>${c.avg_score.toFixed(2)} / 4.00</strong>
                    <small>${escapeHtml(c.performance_level || 'N/A')}</small>
                </div>
            </div></div>
            <div class="succession-evidence" id="successionEvidence" aria-live="polite"><span class="text-muted small">Loading recorded evidence...</span></div>
            <div id="successionEndorsementSource">
            <?php if ($pendingEndorsement): ?>
            <div class="succession-evidence-card mt-3" role="status">
                <h6 class="small mb-2"><i class="fas fa-hourglass-half me-2" aria-hidden="true"></i>Endorsement already pending</h6>
                <p class="small mb-2">This employee has an endorsement for <strong><?php echo e($pendingEndorsement['target_title']); ?></strong> awaiting a decision. Status: <strong><?php echo e($pendingEndorsement['status']); ?></strong>.</p>
                <a class="btn btn-outline-success btn-sm" href="succession-planning.php#endorsements">View endorsement</a>
            </div>
            <?php elseif ($isSuccessionSupervisor && $successionTableReady): ?>
            <form method="post" class="succession-endorse-form mt-3 p-3 rounded-3" id="endorseCandidateForm">
                <?php echo csrfField(); ?>
                <input type="hidden" name="succession_action" value="endorse">
                <input type="hidden" name="employee_id" value="${c.employee_id}">
                <h6 class="text-white fw-bold mb-2"><i class="fas fa-paper-plane me-2"></i>Endorse for HR Review</h6>
                <p class="text-white-50 small">Choose the proposed position and explain the employee's qualifications and the promotion opportunity. This does not change the employee's assignment.</p>
                <div class="row g-2">
                    <div class="col-md-4"><label class="form-label text-white small" for="targetDepartment">Target department</label><select class="form-select" id="targetDepartment"><option value="">All departments</option></select></div>
                    <div class="col-md-4"><label class="form-label text-white small" for="targetPositionSearch">Search position</label><input class="form-control" type="search" id="targetPositionSearch" placeholder="Search job title"></div>
                    <div class="col-md-4"><label class="form-label text-white small" for="targetJobTitle">Target position</label><select class="form-select" name="target_job_title_id" id="targetJobTitle" required><option value="">Select position</option></select><small class="d-block mt-1" id="targetPositionCount" aria-live="polite"></small></div>
                    <div class="col-12"><label class="form-label text-white small" for="endorsementReason">Recommendation and evidence</label><textarea class="form-control" name="reason" id="endorsementReason" rows="3" required placeholder="Briefly explain the recommendation."></textarea></div>
                </div>
                <button class="btn btn-light fw-bold mt-3" type="submit"><i class="fas fa-paper-plane me-1"></i>Submit Endorsement</button>
            </form>
            <?php endif; ?>
            </div>`;
        <?php if ($isSuccessionSupervisor && $successionTableReady): ?>
        initializeTargetPositionFilter(c);
        <?php endif; ?>
        loadCandidateEvidence(c);
    };

    function initializeTargetPositionFilter(candidate) {
        const departmentSelect = document.getElementById('targetDepartment');
        const searchInput = document.getElementById('targetPositionSearch');
        const positionSelect = document.getElementById('targetJobTitle');
        const count = document.getElementById('targetPositionCount');
        if (!departmentSelect || !searchInput || !positionSelect || !count) return;

        const available = targetPositions.filter(position =>
            Number(position.job_title_id) !== Number(candidate.job_title_id) &&
            !(Number(candidate.job_title_id) === 0 &&
                Number(position.department_id) === Number(candidate.department_id) &&
                position.job_title === candidate.job_title)
        );
        const departments = new Map();
        available.forEach(position => departments.set(String(position.department_id || 0), position.department_name || 'Other'));
        [...departments].sort((a, b) => a[1].localeCompare(b[1])).forEach(([id, name]) => departmentSelect.add(new Option(name, id)));
        if (candidate.department_id && departments.has(String(candidate.department_id))) {
            departmentSelect.value = String(candidate.department_id);
        }

        const updatePositions = () => {
            const selected = positionSelect.value;
            const query = searchInput.value.trim().toLocaleLowerCase();
            const matches = available.filter(position =>
                (!departmentSelect.value || String(position.department_id || 0) === departmentSelect.value) &&
                (!query || position.job_title.toLocaleLowerCase().includes(query))
            );
            positionSelect.replaceChildren(new Option(matches.length ? 'Select position' : 'No matching positions', ''));
            matches.forEach(position => positionSelect.add(new Option(position.job_title, position.job_title_id)));
            if (matches.some(position => String(position.job_title_id) === selected)) positionSelect.value = selected;
            count.textContent = `${matches.length} position${matches.length === 1 ? '' : 's'} available`;
        };
        departmentSelect.addEventListener('change', updatePositions);
        searchInput.addEventListener('input', updatePositions);
        updatePositions();
    }

    function formatMonths(value) {
        if (value === null || value === undefined) return 'Duration not recorded';
        const months = Number(value);
        if (months < 1) return 'Less than 1 month';
        const years = Math.floor(months / 12);
        const rest = months % 12;
        return [years ? `${years} yr${years === 1 ? '' : 's'}` : '', rest ? `${rest} mo${rest === 1 ? '' : 's'}` : ''].filter(Boolean).join(' ');
    }

    function evidenceItem(title, detail) {
        return `<div class="succession-evidence-item"><strong class="small">${escapeHtml(String(title || 'Not specified'))}</strong><div class="small text-muted">${escapeHtml(String(detail || ''))}</div></div>`;
    }

    function evidenceCard(icon, title, content) {
        return `<div class="succession-evidence-card"><h6 class="small mb-2"><i class="fas ${icon} me-2" aria-hidden="true"></i>${title}</h6>${content}</div>`;
    }

    function loadCandidateEvidence(candidate) {
        const employeeId = candidate.employee_id;
        selectedEvidenceEmployeeId = Number(employeeId);
        fetch(`<?php echo BASE_URL; ?>/manager/ajax/get-succession-evidence.php?employee_id=${encodeURIComponent(employeeId)}`)
            .then(response => response.json())
            .then(data => {
                if (selectedEvidenceEmployeeId !== Number(employeeId)) return;
                const panel = document.getElementById('successionEvidence');
                if (!panel) return;
                if (!data.success) { panel.textContent = data.message || 'Evidence unavailable.'; return; }
                const current = data.current_position;
                const tenure = document.getElementById('candidatePositionTenure');
                if (tenure) tenure.textContent = formatMonths(current.months);
                const currentDetail = `${formatMonths(current.months)} · ${current.from || 'Start date not recorded'} · ${current.based_on}`;
                const previous = data.prior_positions.length
                    ? data.prior_positions.map(p => evidenceItem(p.position, `${formatMonths(p.months)} · ${p.from || 'Start date not recorded'} to ${p.to}`)).join('')
                    : '<div class="small text-muted">No applied prior position recorded.</div>';
                const performance = data.evaluations.length
                    ? data.evaluations.map(ev => evidenceItem(`${ev.evaluation_type} · ${Number(ev.total_score).toFixed(2)} / 4.00`, `${ev.evaluation_period_start || 'Period not recorded'} to ${ev.evaluation_period_end || 'Period not recorded'} · Approved ${ev.approved_date || 'date not recorded'}`)).join('')
                    : '<div class="small text-muted">No approved score recorded.</div>';
                const education = data.education.length
                    ? data.education.map(row => evidenceItem(row.degree_course || row.education_level, `${row.school_name || 'School not recorded'}${row.year_graduated ? ' · ' + row.year_graduated : ''}`)).join('')
                    : '<div class="small text-muted">No educational background recorded.</div>';
                const honors = data.education.filter(row => row.honors_received && row.honors_received.trim()).map(row => evidenceItem(row.honors_received, row.school_name || row.education_level)).join('') || '<div class="small text-muted">No honors or distinctions recorded.</div>';
                const trainings = data.trainings.length
                    ? data.trainings.map(row => evidenceItem(row.training_title, `${row.date_from || 'Date not recorded'}${row.conducted_by ? ' · ' + row.conducted_by : ''}${row.no_of_hours ? ' · ' + row.no_of_hours + ' hours' : ''}`)).join('')
                    : '<div class="small text-muted">No training programs recorded.</div>';
                const voluntary = data.voluntary_work.length
                    ? data.voluntary_work.map(row => evidenceItem(row.organization_name, `${row.position_nature || 'Role not recorded'}${row.date_from ? ' · ' + row.date_from : ''}`)).join('')
                    : '<div class="small text-muted">No voluntary work recorded.</div>';
                const work = data.work_experience.length
                    ? data.work_experience.map(row => evidenceItem(row.job_title, `${row.company_name || 'Organization not recorded'} · ${row.date_from || 'Start not recorded'} to ${row.date_to || 'Present'} · ${formatMonths(row.months)}`)).join('')
                    : '<div class="small text-muted">No other work experience recorded.</div>';
                const scorePercent = Math.max(0, Math.min(100, candidate.avg_score / 4 * 100));
                const trendLabel = candidate.trend === 'improving' ? 'Improving' : candidate.trend === 'declining' ? 'Declining' : 'Stable';
                panel.innerHTML = `<div class="succession-tab-bar" role="tablist" aria-label="Candidate details sections">
                        <button type="button" class="succession-tab" role="tab" id="succession-tab-overview" data-succession-tab="overview" aria-controls="succession-panel-overview"><i class="fas fa-chart-pie" aria-hidden="true"></i>Overview</button>
                        <button type="button" class="succession-tab" role="tab" id="succession-tab-evaluations" data-succession-tab="evaluations" aria-controls="succession-panel-evaluations"><i class="fas fa-chart-line" aria-hidden="true"></i>Evaluations</button>
                        <button type="button" class="succession-tab" role="tab" id="succession-tab-career" data-succession-tab="career" aria-controls="succession-panel-career"><i class="fas fa-briefcase" aria-hidden="true"></i>Career History</button>
                        <button type="button" class="succession-tab" role="tab" id="succession-tab-credentials" data-succession-tab="credentials" aria-controls="succession-panel-credentials"><i class="fas fa-graduation-cap" aria-hidden="true"></i>Credentials</button>
                        <button type="button" class="succession-tab" role="tab" id="succession-tab-endorsement" data-succession-tab="endorsement" aria-controls="succession-panel-endorsement"><i class="fas fa-paper-plane" aria-hidden="true"></i>Endorsement</button>
                    </div>
                    <section class="succession-tab-panel" role="tabpanel" id="succession-panel-overview" data-succession-panel="overview" aria-labelledby="succession-tab-overview">
                        <div class="succession-evidence-overview">
                            <div class="succession-evidence-card text-center">
                                <h6 class="small text-start mb-2"><i class="fas fa-chart-pie me-2"></i>Performance Overview</h6>
                                <div class="succession-score-ring" style="--score-percent:${scorePercent}%"><strong>${candidate.avg_score.toFixed(2)}</strong></div>
                                <strong class="small">Average approved score</strong><div class="small text-muted">out of 4.00 · ${data.evaluations.length} recent shown</div>
                            </div>
                            <div class="succession-evidence-card">
                                <h6 class="small mb-2"><i class="fas fa-clipboard-check me-2"></i>Performance Interpretation</h6>
                                <div class="small mb-2"><strong>${escapeHtml(candidate.performance_level || 'Not classified')}</strong> <span class="badge bg-light text-success border ms-1">${trendLabel} trend</span></div>
                                <p class="small text-muted mb-0">This score reflects approved evaluations. Review the employee's position history and credentials before making an endorsement.</p>
                            </div>
                        </div>
                    </section>
                    <section class="succession-tab-panel" role="tabpanel" id="succession-panel-evaluations" data-succession-panel="evaluations" aria-labelledby="succession-tab-evaluations" hidden>
                        ${evidenceCard('fa-chart-line', 'Approved Evaluation Performance', performance)}
                    </section>
                    <section class="succession-tab-panel" role="tabpanel" id="succession-panel-career" data-succession-panel="career" aria-labelledby="succession-tab-career" hidden>
                        <div class="succession-evidence-grid">
                            ${evidenceCard('fa-briefcase', 'Position & Service History', evidenceItem(current.title, currentDetail) + previous)}
                            ${evidenceCard('fa-building', 'Other Recorded Work Experience', work)}
                        </div>
                    </section>
                    <section class="succession-tab-panel" role="tabpanel" id="succession-panel-credentials" data-succession-panel="credentials" aria-labelledby="succession-tab-credentials" hidden>
                        <div class="succession-evidence-grid">
                            ${evidenceCard('fa-graduation-cap', 'Educational Background', education)}
                            ${evidenceCard('fa-award', 'Honors / Awards / Distinctions', honors)}
                            ${evidenceCard('fa-chalkboard-teacher', 'Training Programs Attended', trainings)}
                            ${evidenceCard('fa-hands-helping', 'Voluntary Work / Civic Involvement', voluntary)}
                        </div>
                    </section>
                    <section class="succession-tab-panel" role="tabpanel" id="succession-panel-endorsement" data-succession-panel="endorsement" aria-labelledby="succession-tab-endorsement" hidden>
                        <?php if ($isSuccessionSupervisor): ?>
                        <p class="small text-muted mb-2">Review the recorded evidence before submitting an endorsement. This does not automatically change the employee's position.</p>
                        <?php if (!$successionTableReady): ?><div class="alert alert-warning">Endorsement is unavailable until the database migration is installed.</div><?php endif; ?>
                        <?php else: ?>
                        <div class="succession-evidence-card"><h6 class="small"><?php echo $isSuccessionCeo ? 'Final Decision' : 'HR Review'; ?></h6><p class="small text-muted mb-2">Open the endorsement to review the recommendation and decision notes.</p><a href="succession-planning.php#endorsements" class="btn btn-outline-success btn-sm">View Promotion Endorsements</a></div>
                        <?php endif; ?>
                    </section>`;
                const formSource = document.getElementById('successionEndorsementSource');
                const endorsementPanel = panel.querySelector('[data-succession-panel="endorsement"]');
                if (formSource && formSource.firstElementChild) endorsementPanel.appendChild(formSource.firstElementChild);
                if (formSource) formSource.remove();
                const summarySource = document.getElementById('successionSummarySource');
                const overviewPanel = panel.querySelector('[data-succession-panel="overview"]');
                if (summarySource && summarySource.firstElementChild) overviewPanel.insertBefore(summarySource.firstElementChild, overviewPanel.firstChild);
                if (summarySource) summarySource.remove();
                initializeCandidateTabs(panel);
            })
            .catch(() => {
                if (selectedEvidenceEmployeeId === Number(employeeId)) {
                    const panel = document.getElementById('successionEvidence');
                    if (panel) panel.textContent = 'Unable to load recorded evidence. Please retry.';
                }
            });
    }

    function initializeCandidateTabs(container) {
        const tabs = Array.from(container.querySelectorAll('[data-succession-tab]'));
        const panels = Array.from(container.querySelectorAll('[data-succession-panel]'));
        const keys = tabs.map(tab => tab.dataset.successionTab);
        const activate = (key, updateUrl = false) => {
            const selected = keys.includes(key) ? key : 'overview';
            tabs.forEach(tab => {
                const active = tab.dataset.successionTab === selected;
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
                tab.tabIndex = active ? 0 : -1;
            });
            panels.forEach(panel => { panel.hidden = panel.dataset.successionPanel !== selected; });
            if (updateUrl) history.replaceState(null, '', `#tab-${selected}`);
        };
        tabs.forEach((tab, index) => {
            tab.addEventListener('click', () => {
                activate(tab.dataset.successionTab, true);
            });
            tab.addEventListener('keydown', event => {
                let next = index;
                if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
                else if (event.key === 'ArrowLeft') next = (index - 1 + tabs.length) % tabs.length;
                else if (event.key === 'Home') next = 0;
                else if (event.key === 'End') next = tabs.length - 1;
                else return;
                event.preventDefault();
                tabs[next].focus();
                activate(keys[next], true);
            });
        });
        activate(location.hash.startsWith('#tab-') ? location.hash.slice(5) : 'overview');
    }

    function escapeHtml(str) {
        return (str || '').replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    if (employeeId < 1) {
        detailCard.innerHTML = '<div class="succession-empty">Select a candidate from Succession Planning.</div>';
        return;
    }
    fetch(`<?php echo BASE_URL; ?>/manager/ajax/get-succession-candidates.php?employee_id=${employeeId}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success || !data.candidates || !data.candidates.length) {
                detailCard.innerHTML = '<div class="succession-empty">Candidate not found or no approved evaluation is recorded.</div>';
                return;
            }
            renderCandidateDetails(data.candidates[0]);
        })
        .catch(() => {
            detailCard.innerHTML = '<div class="succession-empty">Unable to load candidate details. Please retry.</div>';
        });
});
</script>
<?php require_once '../includes/footer.php'; ?>
