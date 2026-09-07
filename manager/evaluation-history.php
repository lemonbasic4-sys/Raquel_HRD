<?php
$page_title = 'Evaluation History';
require_once '../includes/session-check.php';
checkRole(['HR Manager']);
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Fetch evaluation history
$history = $conn->query("SELECT ev.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.job_title, e.rank_category_id, e.profile_picture, d.department_name,
    u.full_name as submitted_by_name, u2.full_name as endorsed_by_name, u3.full_name as approved_by_name, et.template_name,
    ep.package_id, ep.status AS package_status
    FROM evaluations ev
    LEFT JOIN employees e ON ev.employee_id = e.employee_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN users u ON ev.submitted_by = u.user_id
    LEFT JOIN users u2 ON ev.endorsed_by = u2.user_id
    LEFT JOIN users u3 ON ev.approved_by = u3.user_id
    LEFT JOIN evaluation_templates et ON ev.template_id = et.template_id
    LEFT JOIN evaluation_package_members pm ON pm.evaluation_id = ev.evaluation_id
    LEFT JOIN evaluation_packages ep ON ep.package_id = pm.package_id
    WHERE ev.status IN ('Approved', 'Rejected', 'Returned')
    AND ev.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)
    ORDER BY ev.updated_at DESC");

$total_c = 0;
$approved_c = 0;
$rejected_c = 0;
$returned_c = 0;

$all_history = [];
while ($row = $history->fetch_assoc()) {
    $all_history[] = $row;
    $total_c++;
    if ($row['status'] === 'Approved') $approved_c++;
    elseif ($row['status'] === 'Rejected') $rejected_c++;
    elseif ($row['status'] === 'Returned') $returned_c++;
}

// Extract only existing departments & templates present in the history records
$existing_departments = [];
$existing_templates = [];
foreach ($all_history as $row) {
    if (!empty($row['department_name'])) {
        $existing_departments[$row['department_name']] = $row['department_name'];
    }
    if (!empty($row['template_name'])) {
        $existing_templates[$row['template_name']] = $row['template_name'];
    }
}
ksort($existing_departments);
ksort($existing_templates);
?>

<div class="page-hero fadeup">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
        <div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">HR Manager · Evaluations</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-history me-2" style="color:#BD9414;"></i>Evaluation History</h4>
            <p class="text-white-50 small mb-0 mt-2">Review completed and in-progress performance evaluations across employees, templates, and evaluation periods.</p>
        </div>
        <div style="color:rgba(255,255,255,.6);font-size:.8rem;">
            <i class="fas fa-sync-alt me-1"></i>Data as of <?php echo date('F d, Y'); ?>
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
.hist-status-pill.active-rejected { background: #fee2e2; color: #991b1b; border-color: #fca5a5; }
.hist-status-pill.active-returned { background: #fef3c7; color: #92400e; border-color: #fcd34d; }

/* ── History Cards ───────────────────────────────── */
.hist-card-list { display: flex; flex-direction: column; gap: 10px; }
.hist-card {
    background: #fff;
    border: 1px solid #eef2e8;
    border-radius: 14px;
    padding: 16px 20px;
    display: grid;
    grid-template-columns: 40px minmax(0,2fr) minmax(0,1.5fr) 130px 110px auto;
    align-items: center;
    gap: 16px;
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
    text-decoration: none;
    color: inherit;
    position: relative;
    overflow: hidden;
}
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

.hist-score-col { display: flex; flex-direction: column; gap: 4px; }
.hist-score-val { font-weight: 800; font-size: 1rem; }
.hist-score-bar { height: 5px; background: #e2e8f0; border-radius: 99px; overflow: hidden; }
.hist-score-bar .fill { height: 100%; border-radius: 99px; transition: width .4s ease; }

.hist-status-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 999px; font-size: .72rem; font-weight: 700; white-space: nowrap; }
.hist-status-badge.approved { background: #d1fae5; color: #065f46; }
.hist-status-badge.rejected { background: #fee2e2; color: #991b1b; }
.hist-status-badge.returned { background: #fef3c7; color: #92400e; }

/* Empty state */
.hist-empty { text-align: center; padding: 60px 20px; color: #94a3b8; }
.hist-empty i { font-size: 3rem; opacity: .15; display: block; margin-bottom: 16px; }

/* Results meta */
.hist-meta { font-size: .78rem; color: #94a3b8; padding: 6px 4px; }

@media (max-width: 767px) {
    .hist-card {
        grid-template-columns: 36px 1fr;
        grid-template-rows: auto auto auto;
    }
    .hist-card-dept, .hist-score-col, .hist-status-col { grid-column: 2; }
    .hist-card-action { grid-column: 1 / -1; }
}
</style>

<!-- Filter Bar -->
<div class="hist-filter-bar fadeup fadeup-1">
    <div class="hist-status-pills">
        <button class="hist-status-pill active-all" onclick="histFilter('All', this)">
            <i class="fas fa-list me-1"></i>All <span class="ms-1 opacity-75">(<?php echo $total_c; ?>)</span>
        </button>
        <button class="hist-status-pill" onclick="histFilter('Approved', this)">
            <i class="fas fa-check-circle me-1"></i>Approved <span class="ms-1 opacity-75">(<?php echo $approved_c; ?>)</span>
        </button>
        <button class="hist-status-pill" onclick="histFilter('Rejected', this)">
            <i class="fas fa-times-circle me-1"></i>Rejected <span class="ms-1 opacity-75">(<?php echo $rejected_c; ?>)</span>
        </button>
        <button class="hist-status-pill" onclick="histFilter('Returned', this)">
            <i class="fas fa-rotate-left me-1"></i>Returned <span class="ms-1 opacity-75">(<?php echo $returned_c; ?>)</span>
        </button>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <div class="d-flex align-items-center gap-1">
            <span class="text-muted small fw-semibold"><i class="fas fa-building me-1"></i>Dept:</span>
            <select class="form-select form-select-sm" id="histDeptFilter" style="min-width:160px; max-width:200px;">
                <option value="All">All Departments</option>
                <?php foreach ($existing_departments as $dept_name): ?>
                    <option value="<?php echo e($dept_name); ?>"><?php echo e($dept_name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="d-flex align-items-center gap-1">
            <span class="text-muted small fw-semibold"><i class="fas fa-file-alt me-1"></i>Template:</span>
            <select class="form-select form-select-sm" id="histTemplateFilter" style="min-width:180px; max-width:260px;">
                <option value="All">All Templates</option>
                <?php foreach ($existing_templates as $tmpl_name): ?>
                    <option value="<?php echo e($tmpl_name); ?>"><?php echo e($tmpl_name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="input-group input-group-sm" style="min-width:200px;">
            <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
            <input type="search" class="form-control border-start-0 ps-0" id="histSearchInput" placeholder="Search employee, job...">
        </div>
    </div>
</div>

<!-- Card List -->
<div class="hist-card-list fadeup fadeup-2" id="histCardList">
    <?php if (empty($all_history)): ?>
        <div class="hist-empty">
            <i class="fas fa-history"></i>
            <p class="fw-semibold mb-1">No evaluation records yet</p>
            <small>Approved, rejected, or returned evaluations will appear here.</small>
        </div>
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
            $status_lc  = strtolower($row['status']);
            $status_icon = match($row['status']) {
                'Approved' => 'fa-check-circle',
                'Rejected' => 'fa-times-circle',
                'Returned' => 'fa-rotate-left',
                default    => 'fa-circle',
            };
            $initials_h = strtoupper(
                substr($row['employee_name'], 0, 1) .
                substr(explode(' ', $row['employee_name'])[1] ?? '', 0, 1)
            );
            $avatar_h = getEmployeeAvatar($row['profile_picture'] ?? '');
        ?>
        <div class="hist-card" data-status="<?php echo e($row['status']); ?>"
             data-department="<?php echo e($row['department_name'] ?? ''); ?>"
             data-template="<?php echo e($row['template_name'] ?? ''); ?>"
             data-search="<?php echo strtolower(e($row['employee_name']) . ' ' . e($row['department_name'] ?? '') . ' ' . e($row['template_name']) . ' ' . e($row['job_title'])); ?>">
            <!-- Avatar -->
            <div class="hist-card-avatar">
                <img src="<?php echo e($avatar_h); ?>?v=<?php echo time(); ?>" alt="<?php echo e($row['employee_name']); ?>">
            </div>
            <!-- Employee -->
            <div class="hist-card-employee">
                <div class="name"><?php echo e($row['employee_name']); ?></div>
                <div class="sub"><?php echo e($row['job_title']); ?></div>
            </div>
            <!-- Dept + Template -->
            <div class="hist-card-dept">
                <div class="dept"><?php echo e($row['department_name'] ?? 'N/A'); ?></div>
                <div class="tpl"><?php echo e($row['template_name']); ?></div>
            </div>
            <!-- Score -->
            <div class="hist-score-col">
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
            </div>
            <!-- Status -->
            <div class="hist-status-col">
                <?php if (!empty($row['package_id'])): ?>
                    <?php echo renderOrganizationPipelineBadge($conn, (int)$row['package_id']); ?>
                <?php else: ?>
                    <span class="hist-status-badge <?php echo $status_lc; ?>">
                        <i class="fas <?php echo $status_icon; ?>"></i><?php echo e($row['status']); ?>
                    </span>
                <?php endif; ?>
            </div>
            <!-- Action -->
            <div class="hist-card-action">
                <button class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold shadow-sm"
                        data-bs-toggle="modal" data-bs-target="#reviewModal<?php echo $row['evaluation_id']; ?>">
                    <i class="fas fa-eye me-1"></i>View
                </button>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- No results (search) -->
        <div class="hist-empty d-none" id="histNoResults">
            <i class="fas fa-search"></i>
            <p class="fw-semibold mb-1">No results found</p>
            <small>Try a different search term or clear your filters.</small>
        </div>
    <?php endif; ?>
</div>

<!-- Meta row -->
<div class="hist-meta mt-2 px-1" id="histMeta">
    Showing <span id="histVisibleCount"><?php echo count($all_history); ?></span> of <?php echo count($all_history); ?> records
</div>

<script>
let histActiveStatus = 'All';

function histFilter(status, btn) {
    histActiveStatus = status;

    // Pills active state
    document.querySelectorAll('.hist-status-pill').forEach(p => {
        p.className = 'hist-status-pill';
    });
    const classMap = { All: 'active-all', Approved: 'active-approved', Rejected: 'active-rejected', Returned: 'active-returned' };
    btn.classList.add(classMap[status] ?? 'active-all');

    applyHistFilters();
}

function applyHistFilters() {
    const q = (document.getElementById('histSearchInput')?.value || '').toLowerCase().trim();
    const selDept = document.getElementById('histDeptFilter')?.value || 'All';
    const selTmpl = document.getElementById('histTemplateFilter')?.value || 'All';
    const cards = document.querySelectorAll('#histCardList .hist-card');
    let visible = 0;

    cards.forEach(card => {
        const statusMatch = histActiveStatus === 'All' || card.dataset.status === histActiveStatus;
        const deptMatch = selDept === 'All' || (card.dataset.department || '') === selDept;
        const tmplMatch = selTmpl === 'All' || (card.dataset.template || '') === selTmpl;
        const searchMatch = !q || (card.dataset.search || '').includes(q);
        const show = statusMatch && deptMatch && tmplMatch && searchMatch;
        card.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    const noRes = document.getElementById('histNoResults');
    if (noRes) noRes.classList.toggle('d-none', visible > 0);

    const meta = document.getElementById('histVisibleCount');
    if (meta) meta.textContent = visible;
}

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('histSearchInput')?.addEventListener('input', applyHistFilters);
    document.getElementById('histDeptFilter')?.addEventListener('change', applyHistFilters);
    document.getElementById('histTemplateFilter')?.addEventListener('change', applyHistFilters);
});
</script>


<?php 
// Render Modals at the end of the file
foreach ($all_history as $row): 
    $status = $row['status'];
    $initials = strtoupper(substr($row['employee_name'], 0, 1) . substr(explode(' ', $row['employee_name'])[1] ?? '', 0, 1));
?>
    <!-- History Modal for <?php echo $row['evaluation_id']; ?> -->
    <div class="modal fade modal-premium" id="reviewModal<?php echo $row['evaluation_id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-1">Evaluation Details</h5>
                        <p class="mb-0 opacity-75 small"><?php echo e($row['employee_name']); ?> - <?php echo e($row['template_name']); ?></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 pt-0">
                    <!-- Status Stepper -->
                    <div class="status-stepper d-flex justify-content-between mb-4 py-3 border-bottom overflow-hidden">
                        <?php
                        $steps = [
                            ['l' => 'Drafted', 'a' => true, 'i' => 'fa-pencil-alt', 'c' => false],
                            ['l' => 'Supervisor', 'a' => true, 'i' => 'fa-user-tie', 'c' => false],
                            ['l' => 'Review', 'a' => true, 'i' => 'fa-user-shield', 'c' => false],
                            ['l' => 'Final', 'a' => ($status === 'Approved'), 'i' => 'fa-check-double', 'c' => ($status === 'Approved')]
                        ];
                        if ($status === 'Rejected') {
                            $steps[3] = ['l' => 'Rejected', 'a' => true, 'i' => 'fa-times-circle', 'c' => true, 'cls' => 'text-danger'];
                        } elseif ($status === 'Returned') {
                            $steps[3] = ['l' => 'Returned', 'a' => true, 'i' => 'fa-undo', 'c' => true, 'cls' => 'text-warning'];
                        }
                        
                        foreach ($steps as $st): ?>
                            <div class="step-item text-center <?php echo $st['a'] ? ($st['cls'] ?? 'text-primary') : 'text-muted'; ?>" style="flex: 1;">
                                <div class="mb-1">
                                    <i class="fas <?php echo $st['i']; ?> <?php echo $st['c'] ? 'fa-pulse' : ''; ?>"></i>
                                </div>
                                <div style="font-size: 0.65rem; font-weight: 800; text-transform: uppercase;"><?php echo $st['l']; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="alert <?php echo $status === 'Approved' ? 'alert-success' : ($status === 'Rejected' ? 'alert-danger' : 'alert-warning'); ?> py-2 small d-flex align-items-center mb-4">
                        <i class="fas <?php echo $status === 'Approved' ? 'fa-check-circle' : ($status === 'Rejected' ? 'fa-times-circle' : 'fa-exclamation-circle'); ?> me-2"></i>
                        <span>Historical Status: <strong><?php echo $status; ?></strong></span>
                    </div>

                    <div class="eval-summary-header">
                        <div class="d-flex align-items-center gap-3">
                            <div class="emp-avatar bg-primary text-white d-flex align-items-center justify-content-center fw-bold rounded-3 shadow-sm" style="width: 54px; height: 54px; font-size: 1.2rem;"><?php echo $initials; ?></div>
                            <div>
                                <h4 class="mb-1 fw-bold text-dark" style="font-size: 1.2rem;"><?php echo e($row['employee_name']); ?></h4>
                                <div class="text-muted small d-flex align-items-center gap-2 flex-wrap">
                                    <span class="badge bg-white text-secondary border fw-semibold"><?php echo e($row['job_title'] ?? 'Staff'); ?></span>
                                    <span>&bull;</span>
                                    <span><?php echo e($row['template_name']); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-3 d-print-none">
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-sm btn-success rounded-pill px-3 fw-bold btn-save-ratings d-none" onclick="saveRatings(<?php echo $row['evaluation_id']; ?>)">
                                    <i class="fas fa-save me-1"></i>Save Changes
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold btn-cancel-ratings d-none" onclick="toggleEditRatings(<?php echo $row['evaluation_id']; ?>, true)">
                                    <i class="fas fa-times me-1"></i>Cancel
                                </button>
                                <a href="print-evaluation.php?id=<?php echo $row['evaluation_id']; ?>" target="_blank" class="btn btn-sm btn-primary rounded-pill px-3 py-2 fw-bold d-inline-flex align-items-center gap-2 shadow-sm">
                                    <i class="fas fa-print"></i>
                                    <span>Print Form</span>
                                </a>
                            </div>
                            <?php echo getEvaluationScoreCirclesHtml($conn, $row['evaluation_id'], $row['total_score']); ?>
                        </div>
                    </div>

                    <!-- KRA Section -->
                    <div class="section-premium-label mb-3 mt-4">
                        <i class="fas fa-bullseye"></i> I. Strategic Programs & Job Requirements
                    </div>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-hover align-middle border-start">
                            <thead class="small text-muted bg-light">
                                <tr>
                                    <th class="ps-3">Criterion</th>
                                    <th class="text-center" style="width: 80px;">Weight</th>
                                    <th class="text-center" style="width: 80px;">Rating</th>
                                    <th class="text-center" style="width: 80px;">Total</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <?php
                                $kra_q = $conn->query("SELECT es.*, ec.criterion_name, ec.description, ec.weight FROM evaluation_scores es JOIN evaluation_criteria ec ON es.criterion_id = ec.criterion_id WHERE es.evaluation_id = {$row['evaluation_id']} AND ec.section = 'KRA' ORDER BY ec.sort_order");
                                $kra_num = 1;
                                while ($k = $kra_q->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <div class="fw-bold">KRA <?php echo $kra_num++; ?>: <?php echo e($k['criterion_name']); ?></div>
                                            <?php if($k['description']): ?><div class="text-muted x-small"><?php echo e($k['description']); ?></div><?php endif; ?>
                                        </td>
                                        <td class="text-center"><?php echo $k['weight']; ?>%</td>
                                        <td class="text-center fw-bold">
                                            <?php
                                            $effective_score = $k['score_value'];
                                            $supervisor_override_score = $k['supervisor_override_score'] ?? null;
                                            $manager_override_score = $k['manager_override_score'] ?? null;
                                            $badge_html = '';
                                            
                                            if ($supervisor_override_score !== null) {
                                                $effective_score = $supervisor_override_score;
                                                
                                                $sup_name_q = $conn->query("SELECT full_name FROM users WHERE user_id = " . (int)($k['supervisor_override_by'] ?? 0))->fetch_assoc();
                                                $sup_name = $sup_name_q['full_name'] ?? 'Supervisor';
                                                $formatted_date = formatDate($k['supervisor_override_at'] ?? '', 'M d, Y h:i A');
                                                $badge_html .= '<span class="badge-audit ms-2" data-bs-toggle="tooltip" data-bs-html="true" title="<strong>Supervisor Override</strong><br>Edited by: ' . e($sup_name) . '<br>On: ' . $formatted_date . '<br>Original: ' . $k['score_value'] . '"><i class="fas fa-user-edit me-1"></i>Sup Override</span>';
                                            }
                                            
                                            if ($manager_override_score !== null) {
                                                $effective_score = $manager_override_score;
                                                
                                                $mgr_name_q = $conn->query("SELECT full_name FROM users WHERE user_id = " . (int)($k['manager_override_by'] ?? 0))->fetch_assoc();
                                                $mgr_name = $mgr_name_q['full_name'] ?? 'Manager';
                                                $formatted_date = formatDate($k['manager_override_at'] ?? '', 'M d, Y h:i A');
                                                $badge_html .= '<span class="badge-audit mgr ms-2" data-bs-toggle="tooltip" data-bs-html="true" title="<strong>Manager Override</strong><br>Edited by: ' . e($mgr_name) . '<br>On: ' . $formatted_date . '<br>Original: ' . $k['score_value'] . '"><i class="fas fa-user-shield me-1"></i>Mgr Override</span>';
                                            }
                                            ?>
                                            <span class="score-display"><?php echo number_format($effective_score, 2); ?></span>
                                            <input type="number" min="1.00" max="4.00" step="0.01" class="form-control form-control-sm score-input d-none text-center mx-auto" 
                                                   style="width: 75px;" value="<?php echo number_format($effective_score, 2); ?>" 
                                                   data-score-id="<?php echo $k['score_id']; ?>" data-original-val="<?php echo number_format($effective_score, 2); ?>">
                                            <?php echo $badge_html; ?>
                                        </td>
                                        <td class="text-center text-primary fw-bold weighted-score-display"><?php echo $k['weighted_score']; ?></td>
                                    </tr>
                                <?php endwhile; ?>
                                <tr class="total-row bg-light fw-bold border-top">
                                    <td class="ps-3">KRA Sub-total</td>
                                    <td class="text-center">100%</td>
                                    <td></td>
                                    <td class="text-center text-primary"><?php echo $row['kra_subtotal']; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Behavior Section -->
                    <div class="section-premium-label mb-3 mt-5">
                        <i class="fas fa-heart"></i> II. Behavior & Values
                    </div>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-hover align-middle border-start">
                            <thead class="small text-muted bg-light">
                                <tr>
                                    <th class="ps-3">Behavior KPI</th>
                                    <th class="text-center" style="width: 100px;">Rating (1-4)</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <?php
                                $beh_q = $conn->query("SELECT es.*, ec.criterion_name, ec.kpi_description FROM evaluation_scores es JOIN evaluation_criteria ec ON es.criterion_id = ec.criterion_id WHERE es.evaluation_id = {$row['evaluation_id']} AND ec.section = 'Behavior' ORDER BY ec.sort_order");
                                while ($b = $beh_q->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <div class="fw-bold"><?php echo e($b['criterion_name']); ?></div>
                                            <div class="text-muted x-small"><?php echo e($b['kpi_description']); ?></div>
                                        </td>
                                        <td class="text-center fw-bold">
                                            <?php
                                            $effective_score = $b['score_value'];
                                            $supervisor_override_score = $b['supervisor_override_score'] ?? null;
                                            $manager_override_score = $b['manager_override_score'] ?? null;
                                            $badge_html = '';
                                            
                                            if ($supervisor_override_score !== null) {
                                                $effective_score = $supervisor_override_score;
                                                
                                                $sup_name_q = $conn->query("SELECT full_name FROM users WHERE user_id = " . (int)($b['supervisor_override_by'] ?? 0))->fetch_assoc();
                                                $sup_name = $sup_name_q['full_name'] ?? 'Supervisor';
                                                $formatted_date = formatDate($b['supervisor_override_at'] ?? '', 'M d, Y h:i A');
                                                $badge_html .= '<span class="badge-audit ms-2" data-bs-toggle="tooltip" data-bs-html="true" title="<strong>Supervisor Override</strong><br>Edited by: ' . e($sup_name) . '<br>On: ' . $formatted_date . '<br>Original: ' . $b['score_value'] . '"><i class="fas fa-user-edit me-1"></i>Sup Override</span>';
                                            }
                                            
                                            if ($manager_override_score !== null) {
                                                $effective_score = $manager_override_score;
                                                
                                                $mgr_name_q = $conn->query("SELECT full_name FROM users WHERE user_id = " . (int)($b['manager_override_by'] ?? 0))->fetch_assoc();
                                                $mgr_name = $mgr_name_q['full_name'] ?? 'Manager';
                                                $formatted_date = formatDate($b['manager_override_at'] ?? '', 'M d, Y h:i A');
                                                $badge_html .= '<span class="badge-audit mgr ms-2" data-bs-toggle="tooltip" data-bs-html="true" title="<strong>Manager Override</strong><br>Edited by: ' . e($mgr_name) . '<br>On: ' . $formatted_date . '<br>Original: ' . $b['score_value'] . '"><i class="fas fa-user-shield me-1"></i>Mgr Override</span>';
                                            }
                                            ?>
                                            <span class="score-display"><?php echo number_format($effective_score, 2); ?></span>
                                            <input type="number" min="1.00" max="4.00" step="0.01" class="form-control form-control-sm score-input d-none text-center mx-auto" 
                                                   style="width: 75px;" value="<?php echo number_format($effective_score, 2); ?>" 
                                                   data-score-id="<?php echo $b['score_id']; ?>" data-original-val="<?php echo number_format($effective_score, 2); ?>">
                                            <?php echo $badge_html; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                <tr class="total-row bg-light fw-bold border-top">
                                    <td class="ps-3">Behavior Average</td>
                                    <td class="text-center text-primary"><?php echo $row['behavior_average']; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Career Growth -->
                    <?php if(!empty($row['desired_position']) || !empty($row['career_growth_details'])): ?>
                    <div class="section-premium-label mb-3 mt-5">
                        <i class="fas fa-chart-line"></i> III. Career Growth
                    </div>
                    <div class="p-3 bg-light rounded-3 mb-4 border-start border-4 border-info">
                        <div class="row align-items-center">
                            <div class="col-sm-6">
                                <small class="text-uppercase text-muted fw-bold d-block mb-1">Target Department</small>
                                <div class="fw-bold text-primary" style="font-size: 1.1rem;"><?php echo e($row['desired_position'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="col-sm-6 text-sm-end">
                                <small class="text-uppercase text-muted fw-bold d-block mb-1">Target Date</small>
                                <div class="fw-bold"><?php echo $row['target_date'] ? formatDate($row['target_date']) : 'N/A'; ?></div>
                            </div>
                        </div>
                        <?php if(!empty($row['career_growth_details'])): ?>
                            <hr class="my-3 opacity-25">
                            <div class="x-small text-muted"><span class="fw-bold">Notes:</span> <?php echo e($row['career_growth_details']); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Developmental Plan -->
                    <div class="section-premium-label mb-3 mt-5">
                        <i class="fas fa-seedling"></i> IV. Developmental Plan
                    </div>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-hover align-middle border-start">
                            <thead class="small text-muted bg-light">
                                <tr>
                                    <th class="ps-3">Area of Improvement</th>
                                    <th>Support Needed</th>
                                    <th>Time Frame</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <?php
                                $dev_q = $conn->query("SELECT * FROM evaluation_dev_plans WHERE evaluation_id = {$row['evaluation_id']} ORDER BY sort_order");
                                if ($dev_q->num_rows > 0):
                                    while ($dp = $dev_q->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-3"><?php echo e($dp['improvement_area']); ?></td>
                                        <td><?php echo e($dp['support_needed']); ?></td>
                                        <td class="text-center"><?php echo e($dp['time_frame']); ?></td>
                                    </tr>
                                <?php endwhile; else: ?>
                                    <tr><td colspan="3" class="text-center text-muted small py-3">No developmental plan recorded.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Comments Section -->
                    <div class="section-premium-label mb-3 mt-5">
                        <i class="fas fa-comments"></i> V. Comments & Decisions
                    </div>
                    <div class="row">
                        <?php if(!empty($row['staff_comments'])): ?>
                        <div class="col-md-6 mb-3">
                            <strong class="x-small text-uppercase text-muted d-block mb-2">Employee Remarks</strong>
                            <div class="p-3 bg-light rounded-3 border italic small" style="min-height:80px;"><?php echo nl2br(e($row['staff_comments'])); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if(!empty($row['supervisor_comments'])): ?>
                        <div class="col-md-6 mb-3">
                            <strong class="x-small text-uppercase text-muted d-block mb-2">Department Supervisor Feedback</strong>
                            <div class="p-3 bg-light rounded-3 border border-primary italic small" style="min-height:80px;"><?php echo nl2br(e($row['supervisor_comments'])); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if(!empty($row['dept_manager_comments'])): ?>
                        <div class="col-md-6 mb-3">
                            <strong class="x-small text-uppercase text-muted d-block mb-2">Department Manager Endorsement</strong>
                            <div class="p-3 bg-light rounded-3 border border-info italic small" style="min-height:80px;"><?php echo nl2br(e($row['dept_manager_comments'])); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if(!empty($row['evaluator_comments'])): ?>
                        <div class="col-md-6 mb-3">
                            <strong class="x-small text-uppercase text-muted d-block mb-2">HR Supervisor Remarks</strong>
                            <div class="p-3 bg-light rounded-3 border border-success italic small" style="min-height:80px;"><?php echo nl2br(e($row['evaluator_comments'])); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if(!empty($row['manager_comments'])): ?>
                        <div class="col-md-6 mb-3">
                            <strong class="x-small text-uppercase text-muted d-block mb-2">HR Manager Final Remarks</strong>
                            <div class="p-3 bg-light rounded-3 border border-warning italic small" style="min-height:80px;"><?php echo nl2br(e($row['manager_comments'])); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<script>
let sortDirection = false;
let currentSortColumn = -1;
let currentPage = 1;
const ITEMS_PER_PAGE = 10;

function filterByStatus(status, btn) {
    const buttons = btn.parentElement.querySelectorAll('.btn');
    buttons.forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const rows = document.querySelectorAll('#evalTable tbody tr:not(.no-results-row)');
    rows.forEach(row => {
        if (status === 'All') {
            row.setAttribute('data-visible-filter', 'true');
        } else {
            const rowStatus = row.getAttribute('data-status');
            row.setAttribute('data-visible-filter', (rowStatus === status) ? 'true' : 'false');
        }
    });

    currentPage = 1;
    renderTable();
}

function sortTable(columnIndex) {
    if (currentSortColumn === columnIndex) {
        sortDirection = !sortDirection;
    } else {
        currentSortColumn = columnIndex;
        sortDirection = false;
    }
    
    const ths = document.querySelectorAll("#evalTable thead th");
    ths.forEach((th, idx) => {
        const icon = th.querySelector("i.fas");
        if (icon) {
            if (idx === columnIndex) {
                icon.className = sortDirection ? "fas fa-sort-up ms-1" : "fas fa-sort-down ms-1";
                icon.classList.remove("text-muted");
                icon.classList.add("text-primary");
            } else {
                icon.className = "fas fa-sort text-muted ms-1";
                icon.classList.remove("text-primary");
            }
        }
    });

    renderTable();
}

document.getElementById('customSearchEval')?.addEventListener('input', function() {
    currentPage = 1;
    renderTable();
});

function goToPage(page) {
    currentPage = page;
    renderTable();
}

function renderTable() {
    const tbody = document.querySelector("#evalTable tbody");
    if(!tbody) return;
    const allRows = Array.from(tbody.querySelectorAll("tr:not(.no-results-row)"));
    const searchInput = document.getElementById('customSearchEval');
    const filterInput = searchInput ? searchInput.value.toLowerCase().trim() : '';
    
    let visibleRows = [];
    
    allRows.forEach(row => {
        const isFilterVisible = row.getAttribute('data-visible-filter') !== 'false';
        const cells = Array.from(row.querySelectorAll("td"));
        
        if (cells.length > 1) {
            const rowText = cells.slice(0, 5).map(td => td.textContent.trim().replace(/\s+/g, ' ')).join(' ').toLowerCase();
            if (isFilterVisible && (filterInput === "" || rowText.includes(filterInput))) {
                visibleRows.push(row);
                row.classList.remove('filtered-out');
            } else {
                row.classList.add('filtered-out');
                row.style.display = "none";
            }
        }
    });

    if (currentSortColumn !== -1) {
        visibleRows.sort((a, b) => {
            let valA = a.querySelectorAll("td")[currentSortColumn].textContent.trim();
            let valB = b.querySelectorAll("td")[currentSortColumn].textContent.trim();
            
            if (currentSortColumn === 2) {
                valA = new Date(valA).getTime() || valA;
                valB = new Date(valB).getTime() || valB;
            }
            if (currentSortColumn === 3) {
                valA = parseFloat(valA.replace('%', '')) || 0;
                valB = parseFloat(valB.replace('%', '')) || 0;
            }

            if (valA < valB) return sortDirection ? -1 : 1;
            if (valA > valB) return sortDirection ? 1 : -1;
            return 0;
        });
        
        visibleRows.forEach(row => tbody.appendChild(row));
    }

    const totalPages = Math.ceil(visibleRows.length / ITEMS_PER_PAGE);
    if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;

    const startIdx = (currentPage - 1) * ITEMS_PER_PAGE;
    const endIdx = startIdx + ITEMS_PER_PAGE;

    visibleRows.forEach((row, index) => {
        if (index >= startIdx && index < endIdx) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });

    updatePaginationUI(visibleRows.length, totalPages);
    handleNoResults(visibleRows.length, filterInput, tbody);
    applyZebraStriping('#evalTable');
}

function updatePaginationUI(totalItems, totalPages) {
    const info = document.getElementById("paginationInfo");
    const digits = document.getElementById("paginationNumbers");
    if (!info || !digits) return;
    
    if (totalItems === 0) {
        info.innerHTML = "Showing 0 entries";
        digits.innerHTML = "";
        return;
    }
    
    const start = (currentPage - 1) * ITEMS_PER_PAGE + 1;
    const end = Math.min(currentPage * ITEMS_PER_PAGE, totalItems);
    info.innerHTML = `Showing ${start} to ${end} of ${totalItems} entries`;
    
    let html = "";
    html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}"><button class="page-link" onclick="goToPage(${currentPage - 1})">Previous</button></li>`;
             
    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, startPage + 4);
    if (endPage - startPage < 4) startPage = Math.max(1, endPage - 4);
    
    if (startPage > 1) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
    for (let i = startPage; i <= endPage; i++) {
        html += `<li class="page-item ${i === currentPage ? 'active' : ''}"><button class="page-link" onclick="goToPage(${i})">${i}</button></li>`;
    }
    if (endPage < totalPages) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
    
    html += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}"><button class="page-link" onclick="goToPage(${currentPage + 1})">Next</button></li>`;
    digits.innerHTML = html;
}

function handleNoResults(totalItems, filterInput, tbody) {
    let noResultsRow = tbody.querySelector('.no-results-row.search-empty');
    if (totalItems === 0 && filterInput !== "") {
        if (!noResultsRow) {
            noResultsRow = document.createElement('tr');
            noResultsRow.className = 'no-results-row search-empty text-center';
            tbody.appendChild(noResultsRow);
        }
        noResultsRow.innerHTML = `<td colspan="6" class="py-4 text-muted"><i class="fas fa-search fa-2x mb-3 d-block"></i>No evaluations found matching "<strong>${filterInput}</strong>"</td>`;
        noResultsRow.style.display = '';
        const origNoResults = tbody.querySelector('.no-results-row:not(.search-empty)');
        if(origNoResults) origNoResults.style.display = 'none';
    } else if (noResultsRow) {
        noResultsRow.remove();
    }
}

document.addEventListener("DOMContentLoaded", function() {
    renderTable();
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Enforce max rating of 4.00 on score input fields
    document.querySelectorAll('.score-input').forEach(input => {
        input.addEventListener('input', function() {
            let val = parseFloat(this.value);
            if (val > 4) {
                this.value = "4.00";
            }
        });
    });
});

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

<?php require_once '../includes/footer.php'; ?>
