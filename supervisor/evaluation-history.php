<?php
$page_title = 'Evaluation History';
require_once '../includes/session-check.php';
checkRole(['HR Supervisor']);
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Fetch evaluation history 
$history = $conn->query("SELECT ev.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.job_title, e.rank_category_id, d.department_name,
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
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-3">
        <div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">HR Supervisor · Evaluation Records</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-history me-2" style="color:#BD9414;"></i>Evaluation History</h4>
            <p class="text-white-50 small mb-0 mt-2">Review evaluation progress and completed results for employees within your assigned responsibilities.</p>
        </div>
        <div style="color:rgba(255,255,255,.6);font-size:.8rem;">
            <i class="fas fa-archive me-1"></i><?php echo $total_c; ?> records
        </div>
    </div>
    <p class="text-white-50 small mb-0"><i class="fas fa-clipboard-list me-1"></i>Track completed evaluations, returned submissions, and approval outcomes across employee records.</p>

    <div class="row g-3 mb-4 mt-4">
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

<div class="content-card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center flex-wrap gap-2">
            <h5 class="mb-0 me-3"><i class="fas fa-history me-2 text-primary"></i>Evaluation History</h5>
            <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-outline-primary active" onclick="filterByStatus('All', this)">All</button>
                <button type="button" class="btn btn-outline-success" onclick="filterByStatus('Approved', this)">Approved</button>
                <button type="button" class="btn btn-outline-danger" onclick="filterByStatus('Rejected', this)">Rejected</button>
                <button type="button" class="btn btn-outline-warning" onclick="filterByStatus('Returned', this)">Returned</button>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap ms-auto">
            <div class="d-flex align-items-center gap-1">
                <span class="text-muted small fw-semibold"><i class="fas fa-building me-1"></i>Dept:</span>
                <select class="form-select form-select-sm" id="supervisorDeptFilter" style="min-width:150px; max-width:190px;">
                    <option value="All">All Departments</option>
                    <?php foreach ($existing_departments as $dept_name): ?>
                        <option value="<?php echo e($dept_name); ?>"><?php echo e($dept_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="d-flex align-items-center gap-1">
                <span class="text-muted small fw-semibold"><i class="fas fa-file-alt me-1"></i>Template:</span>
                <select class="form-select form-select-sm" id="supervisorTemplateFilter" style="min-width:170px; max-width:240px;">
                    <option value="All">All Templates</option>
                    <?php foreach ($existing_templates as $tmpl_name): ?>
                        <option value="<?php echo e($tmpl_name); ?>"><?php echo e($tmpl_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input type="text" class="form-control form-control-sm" id="customSearchEval" placeholder="Search employee, dept...">
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="evalTable">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-3" style="cursor: pointer;" onclick="sortTable(0)">Employee <i class="fas fa-sort text-muted ms-1 small"></i></th>
                        <th style="cursor: pointer;" onclick="sortTable(1)">Department <i class="fas fa-sort text-muted ms-1 small"></i></th>
                        <th style="cursor: pointer;" onclick="sortTable(3)">Date <i class="fas fa-sort text-muted ms-1 small"></i></th>
                        <th style="cursor: pointer;" onclick="sortTable(4)">Score <i class="fas fa-sort text-muted ms-1 small"></i></th>
                        <th style="cursor: pointer;" onclick="sortTable(6)">Status <i class="fas fa-sort text-muted ms-1 small"></i></th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($all_history)): ?>
                        <tr class="no-results-row text-center"><td colspan="6" class="text-muted py-5"><i class="fas fa-history fa-3x mb-3 d-block opacity-25"></i>No evaluation history found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($all_history as $row): ?>
                            <tr class="eval-row" 
                                data-status="<?php echo e($row['status']); ?>"
                                data-department="<?php echo e($row['department_name'] ?? ''); ?>"
                                data-template="<?php echo e($row['template_name'] ?? ''); ?>"
                                data-search="<?php echo strtolower(e($row['employee_name']) . ' ' . e($row['department_name'] ?? '') . ' ' . e($row['template_name']) . ' ' . e($row['job_title'])); ?>">
                                <td class="ps-3">
                                    <div class="fw-bold"><?php echo e($row['employee_name']); ?></div>
                                    <div class="text-muted x-small"><?php echo e($row['job_title']); ?></div>
                                </td>
                                <td><div class="small fw-bold text-dark"><?php echo e($row['department_name'] ?? 'N/A'); ?></div><div class="x-small text-muted"><?php echo e($row['template_name']); ?></div></td>
                                <td><small><?php echo formatDate($row['updated_at']); ?></small></td>
                                <td>
                                    <?php
                                    $h_score = (float)($row['total_score'] ?? 0);
                                    $h_perf = $row['performance_level'] ?? '';
                                    if ($h_score > 0 && (empty($h_perf) || $h_perf === '0')) {
                                        $h_perf = getPerformanceLevel($h_score);
                                    }
                                    $h_badge = getPerformanceBadgeClass($h_perf);
                                    ?>
                                    <strong><?php echo $h_score > 0 ? number_format($h_score, 2) . ' / 4' : '—'; ?></strong>
                                    <div style="font-size:0.65rem;">
                                        <?php if ($h_perf): ?>
                                            <span class="badge <?php echo $h_badge; ?> rounded-pill px-2" style="font-size:0.65rem;"><?php echo e($h_perf); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Unscored</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($row['package_id'])): ?>
                                        <?php echo renderOrganizationPipelineBadge($conn, (int)$row['package_id']); ?>
                                    <?php else: ?>
                                        <?php
                                        $statusClass = 'bg-secondary';
                                        if ($row['status'] === 'Approved') $statusClass = 'bg-success';
                                        if ($row['status'] === 'Rejected') $statusClass = 'bg-danger';
                                        if ($row['status'] === 'Returned') $statusClass = 'bg-warning text-dark';
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?> rounded-pill px-2" style="font-size:0.7rem;"><?php echo e($row['status']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#reviewModal<?php echo $row['evaluation_id']; ?>">
                                        <i class="fas fa-eye me-1"></i>View
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- Pagination Controls -->
        <div class="d-flex justify-content-between align-items-center p-3 border-top" id="paginationWrapper">
            <div id="paginationInfo" class="text-muted small"></div>
            <ul class="pagination pagination-sm mb-0" id="paginationNumbers">
            </ul>
        </div>
    </div>
</div>

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
                                <?php 
                                $can_edit_rating = ((int)($row['rank_category_id'] ?? 0) === 5) || (getEmployeeHRRole($conn, (int)$row['employee_id']) === 'HR Manager');
                                if ($can_edit_rating): ?>
                                <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-3 fw-bold btn-edit-ratings" onclick="toggleEditRatings(<?php echo $row['evaluation_id']; ?>)">
                                    <i class="fas fa-edit me-1"></i>Edit Ratings
                                </button>
                                <button type="button" class="btn btn-sm btn-success rounded-pill px-3 fw-bold btn-save-ratings d-none" onclick="saveRatings(<?php echo $row['evaluation_id']; ?>)">
                                    <i class="fas fa-save me-1"></i>Save Changes
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold btn-cancel-ratings d-none" onclick="toggleEditRatings(<?php echo $row['evaluation_id']; ?>, true)">
                                    <i class="fas fa-times me-1"></i>Cancel
                                </button>
                                <?php endif; ?>
                                <a href="../manager/print-evaluation.php?id=<?php echo $row['evaluation_id']; ?>" target="_blank" class="btn btn-sm btn-primary rounded-pill px-3 py-2 fw-bold d-inline-flex align-items-center gap-2 shadow-sm">
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
                                    <th class="text-center" style="width: 140px;">Rating</th>
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
                                            $is_overridden = false;
                                            $badge_html = '';
                                            
                                            if ($k['supervisor_override_score'] !== null) {
                                                $effective_score = $k['supervisor_override_score'];
                                                $is_overridden = true;
                                                
                                                $sup_name_q = $conn->query("SELECT full_name FROM users WHERE user_id = " . (int)$k['supervisor_override_by'])->fetch_assoc();
                                                $sup_name = $sup_name_q['full_name'] ?? 'Supervisor';
                                                $formatted_date = formatDate($k['supervisor_override_at'], 'M d, Y h:i A');
                                                $badge_html = '<span class="badge-audit ms-2" data-bs-toggle="tooltip" data-bs-html="true" title="<strong>Supervisor Override</strong><br>Edited by: ' . e($sup_name) . '<br>On: ' . $formatted_date . '<br>Original: ' . $k['score_value'] . '"><i class="fas fa-user-edit me-1"></i>Sup Override</span>';
                                            }
                                            
                                            if ($k['manager_override_score'] !== null) {
                                                $effective_score = $k['manager_override_score'];
                                                $is_overridden = true;
                                                
                                                $mgr_name_q = $conn->query("SELECT full_name FROM users WHERE user_id = " . (int)$k['manager_override_by'])->fetch_assoc();
                                                $mgr_name = $mgr_name_q['full_name'] ?? 'Manager';
                                                $formatted_date = formatDate($k['manager_override_at'], 'M d, Y h:i A');
                                                $badge_html = '<span class="badge-audit mgr ms-2" data-bs-toggle="tooltip" data-bs-html="true" title="<strong>Manager Override</strong><br>Edited by: ' . e($mgr_name) . '<br>On: ' . $formatted_date . '<br>Original: ' . $k['score_value'] . '"><i class="fas fa-user-shield me-1"></i>Mgr Override</span>';
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
                                    <th class="text-center" style="width: 140px;">Rating (1-4)</th>
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
                                        <td class="text-center text-primary fw-bold">
                                            <?php
                                            $effective_score = $b['score_value'];
                                            $is_overridden = false;
                                            $badge_html = '';
                                            
                                            if ($b['supervisor_override_score'] !== null) {
                                                $effective_score = $b['supervisor_override_score'];
                                                $is_overridden = true;
                                                
                                                $sup_name_q = $conn->query("SELECT full_name FROM users WHERE user_id = " . (int)$b['supervisor_override_by'])->fetch_assoc();
                                                $sup_name = $sup_name_q['full_name'] ?? 'Supervisor';
                                                $formatted_date = formatDate($b['supervisor_override_at'], 'M d, Y h:i A');
                                                $badge_html = '<span class="badge-audit ms-2" data-bs-toggle="tooltip" data-bs-html="true" title="<strong>Supervisor Override</strong><br>Edited by: ' . e($sup_name) . '<br>On: ' . $formatted_date . '<br>Original: ' . $b['score_value'] . '"><i class="fas fa-user-edit me-1"></i>Sup Override</span>';
                                            }
                                            
                                            if ($b['manager_override_score'] !== null) {
                                                $effective_score = $b['manager_override_score'];
                                                $is_overridden = true;
                                                
                                                $mgr_name_q = $conn->query("SELECT full_name FROM users WHERE user_id = " . (int)$b['manager_override_by'])->fetch_assoc();
                                                $mgr_name = $mgr_name_q['full_name'] ?? 'Manager';
                                                $formatted_date = formatDate($b['manager_override_at'], 'M d, Y h:i A');
                                                $badge_html = '<span class="badge-audit mgr ms-2" data-bs-toggle="tooltip" data-bs-html="true" title="<strong>Manager Override</strong><br>Edited by: ' . e($mgr_name) . '<br>On: ' . $formatted_date . '<br>Original: ' . $b['score_value'] . '"><i class="fas fa-user-shield me-1"></i>Mgr Override</span>';
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
                    <?php $cg_suited = !empty($row['career_growth_suited']) ? 1 : (!empty($row['desired_position']) ? 1 : 0); ?>
                    <div class="section-premium-label mb-3 mt-5">
                        <i class="fas fa-chart-line"></i> III. Career Growth
                    </div>
                    <div class="p-3 bg-light rounded-3 mb-4 border-start border-4 border-info">
                        <div class="mb-2 fw-semibold" style="font-size:0.9rem;">
                            Is the employee better suited for another job within the company?
                            <span class="badge ms-2 <?php echo $cg_suited ? 'bg-success' : 'bg-secondary'; ?>">
                                <?php echo $cg_suited ? '&#9745; Yes' : '&#9744; No'; ?>
                            </span>
                        </div>
                        <?php if ($cg_suited && !empty($row['desired_position'])): ?>
                        <div class="small text-muted mt-1">
                            <i class="fas fa-briefcase me-1 text-info"></i>
                            <strong>Job Function / Department:</strong>
                            <span class="text-dark fw-semibold ms-1"><?php echo e($row['desired_position']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

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

let supervisorActiveStatus = 'All';

function filterByStatus(status, btn) {
    supervisorActiveStatus = status;
    const container = btn.closest('.btn-group');
    if (container) {
        container.querySelectorAll('.btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    }
    applySupervisorFilters();
}

function applySupervisorFilters() {
    const q = (document.getElementById('customSearchEval')?.value || '').toLowerCase().trim();
    const selDept = document.getElementById('supervisorDeptFilter')?.value || 'All';
    const selTmpl = document.getElementById('supervisorTemplateFilter')?.value || 'All';
    const rows = document.querySelectorAll('#evalTable tbody tr.eval-row');
    let visible = 0;

    rows.forEach(row => {
        const statusMatch = supervisorActiveStatus === 'All' || row.dataset.status === supervisorActiveStatus;
        const deptMatch = selDept === 'All' || (row.dataset.department || '') === selDept;
        const tmplMatch = selTmpl === 'All' || (row.dataset.template || '') === selTmpl;
        const searchMatch = !q || (row.dataset.search || '').includes(q);
        const show = statusMatch && deptMatch && tmplMatch && searchMatch;
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    const noResRow = document.querySelector('#evalTable tbody tr.no-results-row');
    if (noResRow) {
        noResRow.style.display = (visible === 0) ? '' : 'none';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('customSearchEval')?.addEventListener('input', applySupervisorFilters);
    document.getElementById('supervisorDeptFilter')?.addEventListener('change', applySupervisorFilters);
    document.getElementById('supervisorTemplateFilter')?.addEventListener('change', applySupervisorFilters);
});
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
