<?php
/**
 * HR Staff Portal - Evaluation History
 * View-only access to all performance reviews (Approved, Rejected, Returned)
 */
$page_title = 'Evaluation History';
require_once '../includes/session-check.php';
checkRole(['HR Staff']);
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Fetch evaluation history
$history = $conn->query("
    SELECT ev.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.job_title, e.rank_category_id, d.department_name,
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
    ORDER BY ev.updated_at DESC
");

$total_c = 0;
$approved_c = 0;
$rejected_c = 0;
$returned_c = 0;

$all_history = [];
if ($history) {
    while ($row = $history->fetch_assoc()) {
        $all_history[] = $row;
        $total_c++;
        if ($row['status'] === 'Approved') $approved_c++;
        elseif ($row['status'] === 'Rejected') $rejected_c++;
        elseif ($row['status'] === 'Returned') $returned_c++;
    }
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
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">HR Staff · Archive</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-history me-2" style="color:#BD9414;"></i>Evaluation History</h4>
            <p class="text-white-50 small mb-0 mt-2">Review employee evaluation records and follow their status throughout the performance review cycle.</p>
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
                        <div class="stat-label">Total Records</div>
                    </div>
                    <i class="fas fa-archive stat-icon text-white-50"></i>
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
                    <i class="fas fa-undo stat-icon" style="color:#ffc107;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

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
    .badge-audit.mgr {
        background: rgba(13, 110, 253, 0.12);
        color: #0b5ed7;
        border: 1px solid rgba(13, 110, 253, 0.3);
    }
    .badge-audit:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
    }
</style>

<div class="content-card fadeup-1">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center flex-wrap gap-2">
            <h5 class="mb-0 me-3"><i class="fas fa-th-list me-2 text-primary"></i>Evaluation Directory</h5>
            <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-outline-secondary active" onclick="filterHistory('All', this)">All</button>
                <button type="button" class="btn btn-outline-secondary" onclick="filterHistory('Approved', this)">Approved</button>
                <button type="button" class="btn btn-outline-secondary" onclick="filterHistory('Returned', this)">Returned</button>
                <button type="button" class="btn btn-outline-secondary" onclick="filterHistory('Rejected', this)">Rejected</button>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap ms-auto">
            <div class="d-flex align-items-center gap-1">
                <span class="text-muted small fw-semibold"><i class="fas fa-building me-1"></i>Dept:</span>
                <select class="form-select form-select-sm" id="staffDeptFilter" style="min-width:150px; max-width:190px;">
                    <option value="All">All Departments</option>
                    <?php foreach ($existing_departments as $dept_name): ?>
                        <option value="<?php echo e($dept_name); ?>"><?php echo e($dept_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="d-flex align-items-center gap-1">
                <span class="text-muted small fw-semibold"><i class="fas fa-file-alt me-1"></i>Template:</span>
                <select class="form-select form-select-sm" id="staffTemplateFilter" style="min-width:170px; max-width:240px;">
                    <option value="All">All Templates</option>
                    <?php foreach ($existing_templates as $tmpl_name): ?>
                        <option value="<?php echo e($tmpl_name); ?>"><?php echo e($tmpl_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="search-box">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-transparent border-end-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" id="historySearch" class="form-control border-start-0" placeholder="Search records...">
                </div>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="historyTable">
                <thead class="table-light">
                    <tr>
                        <th>Employee & Template</th>
                        <th>Submissions Info</th>
                        <th>Workflow Status</th>
                        <th>Overall Rating</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($all_history)): ?>
                        <tr class="no-history-row">
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="fas fa-folder-open fa-3x mb-3 opacity-25"></i>
                                <p class="mb-0">No historical evaluation records found.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <tr class="no-history-row text-center py-5 text-muted" style="display:none;">
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="fas fa-search fa-3x mb-3 opacity-25"></i>
                                <p class="mb-0">No matching evaluation records found.</p>
                            </td>
                        </tr>
                        <?php foreach ($all_history as $row): 
                            $initials = strtoupper(substr($row['employee_name'], 0, 1) . substr(explode(' ', $row['employee_name'])[1] ?? '', 0, 1));
                            $score = (float)$row['total_score'];
                            $score_width = max(0, min(100, ($score / 4) * 100));
                            $status_class = getStatusBadgeClass($row['status']);
                            $perf_class = getPerformanceBadgeClass($row['performance_level']);
                        ?>
                            <tr class="history-row" 
                                data-status="<?php echo e($row['status']); ?>"
                                data-department="<?php echo e($row['department_name'] ?? ''); ?>"
                                data-template="<?php echo e($row['template_name'] ?? ''); ?>"
                                data-search="<?php echo strtolower(e($row['employee_name']) . ' ' . e($row['department_name'] ?? '') . ' ' . e($row['template_name']) . ' ' . e($row['job_title'])); ?>">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle-sm me-3" style="width:40px; height:40px; border-radius:10px; background:rgba(41, 67, 6, 0.06); color:var(--primary-blue); display:flex; align-items:center; justify-content:center; font-weight:700;">
                                            <?php echo $initials; ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?php echo e($row['employee_name']); ?></div>
                                            <small class="text-muted"><?php echo e($row['template_name']); ?><?php echo !empty($row['department_name']) ? ' &bull; ' . e($row['department_name']) : ''; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="small text-dark">Submitter: <?php echo e($row['submitted_by_name'] ?: 'N/A'); ?></div>
                                    <div class="small text-muted">Date: <?php echo $row['submitted_date'] ? formatDate($row['submitted_date']) : 'N/A'; ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($row['package_id'])): ?>
                                        <?php echo renderOrganizationPipelineBadge($conn, (int)$row['package_id']); ?>
                                    <?php else: ?>
                                        <span class="badge <?php echo $status_class; ?> px-3 py-2 rounded-pill"><?php echo e($row['status']); ?></span>
                                        <?php if ($row['approved_by_name']): ?>
                                             <div class="small text-muted mt-1">HRM: <?php echo e($row['approved_by_name']); ?></div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="fw-bold" style="min-width: 55px;"><?php echo number_format($score, 2); ?> / 4</div>
                                        <div class="progress flex-grow-1" style="height: 6px; max-width: 100px;">
                                            <div class="progress-bar <?php echo $perf_class; ?>" style="width: <?php echo $score_width; ?>%"></div>
                                        </div>
                                        <span class="badge <?php echo $perf_class; ?> rounded-pill"><?php echo e($row['performance_level']); ?></span>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#reviewModal<?php echo $row['evaluation_id']; ?>">
                                        <i class="fas fa-eye me-1"></i>View Details
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
// Modals for evaluation details (strictly read-only)
foreach ($all_history as $row): 
    $initials = strtoupper(substr($row['employee_name'], 0, 1) . substr(explode(' ', $row['employee_name'])[1] ?? '', 0, 1));
?>
    <div class="modal fade modal-premium" id="reviewModal<?php echo $row['evaluation_id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-1">Performance Details (Read Only)</h5>
                        <p class="mb-0 opacity-75 small">Comprehensive archive record for <?php echo e($row['employee_name']); ?></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 pt-0">
                    <div class="status-stepper d-flex justify-content-between mb-4 py-3 border-bottom overflow-hidden">
                        <?php
                        $steps = [
                            ['l' => 'Drafted', 'a' => true, 'i' => 'fa-pencil-alt'],
                            ['l' => 'Supervisor', 'a' => true, 'i' => 'fa-user-tie'],
                            ['l' => 'Approved', 'a' => ($row['status'] === 'Approved'), 'i' => 'fa-check-double']
                        ];
                        foreach ($steps as $st): ?>
                            <div class="step-item text-center <?php echo $st['a'] ? 'text-primary' : 'text-muted'; ?>" style="flex: 1;">
                                <div class="mb-1">
                                    <i class="fas <?php echo $st['i']; ?>"></i>
                                </div>
                                <div style="font-size: 0.65rem; font-weight: 800; text-transform: uppercase;"><?php echo $st['l']; ?></div>
                            </div>
                        <?php endforeach; ?>
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
                            <a href="../manager/print-evaluation.php?id=<?php echo $row['evaluation_id']; ?>" target="_blank" class="btn btn-sm btn-primary rounded-pill px-3 py-2 fw-bold d-inline-flex align-items-center gap-2 shadow-sm">
                                <i class="fas fa-print"></i>
                                <span>Print Form</span>
                            </a>
                            <?php echo getEvaluationScoreCirclesHtml($conn, $row['evaluation_id'], $row['total_score']); ?>
                        </div>
                    </div>

                    <!-- KRA Section -->
                    <div class="section-premium-label mb-3 mt-4 fw-bold text-primary small text-uppercase" style="letter-spacing: 1px; border-bottom: 2px solid rgba(41,67,6,0.08); padding-bottom: 6px;">
                        <i class="fas fa-bullseye me-2"></i> I. Key Result Areas
                    </div>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-hover align-middle border-start">
                            <thead class="small text-muted bg-light">
                                <tr>
                                    <th class="ps-3">Criterion</th>
                                    <th class="text-center" style="width: 80px;">Weight</th>
                                    <th class="text-center" style="width: 120px;">Rating</th>
                                    <th class="text-center" style="width: 80px;">Weighted</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <?php
                                $kra_q = $conn->query("
                                    SELECT es.*, ec.criterion_name, ec.description, ec.weight 
                                    FROM evaluation_scores es 
                                    JOIN evaluation_criteria ec ON es.criterion_id = ec.criterion_id 
                                    WHERE es.evaluation_id = {$row['evaluation_id']} AND ec.section = 'KRA' 
                                    ORDER BY ec.sort_order
                                ");
                                $kra_num = 1;
                                if ($kra_q && $kra_q->num_rows > 0):
                                    while ($k = $kra_q->fetch_assoc()): ?>
                                        <tr>
                                            <td class="ps-3">
                                                <div class="fw-bold">KRA <?php echo $kra_num++; ?>: <?php echo e($k['criterion_name']); ?></div>
                                                <?php if($k['description']): ?><div class="text-muted x-small"><?php echo e($k['description']); ?></div><?php endif; ?>
                                            </td>
                                            <td class="text-center"><?php echo $k['weight']; ?>%</td>
                                            <td class="text-center">
                                                <?php
                                                $effective_score = $k['score_value'];
                                                $badge_html = '';
                                                if ($k['supervisor_override_score'] !== null) {
                                                    $effective_score = $k['supervisor_override_score'];
                                                    $sup_name_q = $conn->query("SELECT full_name FROM users WHERE user_id = " . (int)$k['supervisor_override_by'])->fetch_assoc();
                                                    $sup_name = $sup_name_q['full_name'] ?? 'Supervisor';
                                                    $formatted_date = formatDate($k['supervisor_override_at'], 'M d, Y h:i A');
                                                    $badge_html = '<span class="badge-audit ms-2" data-bs-toggle="tooltip" data-bs-html="true" title="<strong>Supervisor Override</strong><br>Edited by: ' . e($sup_name) . '<br>On: ' . $formatted_date . '<br>Original: ' . $k['score_value'] . '"><i class="fas fa-user-edit me-1"></i>Sup Override</span>';
                                                }
                                                if ($k['manager_override_score'] !== null) {
                                                    $effective_score = $k['manager_override_score'];
                                                    $mgr_name_q = $conn->query("SELECT full_name FROM users WHERE user_id = " . (int)$k['manager_override_by'])->fetch_assoc();
                                                    $mgr_name = $mgr_name_q['full_name'] ?? 'Manager';
                                                    $formatted_date = formatDate($k['manager_override_at'], 'M d, Y h:i A');
                                                    $badge_html = '<span class="badge-audit mgr ms-2" data-bs-toggle="tooltip" data-bs-html="true" title="<strong>Manager Override</strong><br>Edited by: ' . e($mgr_name) . '<br>On: ' . $formatted_date . '<br>Original: ' . $k['score_value'] . '"><i class="fas fa-user-shield me-1"></i>Mgr Override</span>';
                                                }
                                                ?>
                                                <span class="fw-bold text-dark"><?php echo number_format($effective_score, 2); ?></span>
                                                <?php echo $badge_html; ?>
                                            </td>
                                            <td class="text-center text-primary fw-bold"><?php echo $k['weighted_score']; ?></td>
                                        </tr>
                                    <?php endwhile;
                                endif; ?>
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
                    <div class="section-premium-label mb-3 mt-4 fw-bold text-primary small text-uppercase" style="letter-spacing: 1px; border-bottom: 2px solid rgba(41,67,6,0.08); padding-bottom: 6px;">
                        <i class="fas fa-heart me-2"></i> II. Behavior & Values
                    </div>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-hover align-middle border-start">
                            <thead class="small text-muted bg-light">
                                <tr>
                                    <th class="ps-3">Behavior KPI</th>
                                    <th class="text-center" style="width: 120px;">Rating (1-4)</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <?php
                                $beh_q = $conn->query("
                                    SELECT es.*, ec.criterion_name, ec.kpi_description 
                                    FROM evaluation_scores es 
                                    JOIN evaluation_criteria ec ON es.criterion_id = ec.criterion_id 
                                    WHERE es.evaluation_id = {$row['evaluation_id']} AND ec.section = 'Behavior' 
                                    ORDER BY ec.sort_order
                                ");
                                if ($beh_q && $beh_q->num_rows > 0):
                                    while ($b = $beh_q->fetch_assoc()): ?>
                                        <tr>
                                            <td class="ps-3">
                                                <div class="fw-bold"><?php echo e($b['criterion_name']); ?></div>
                                                <div class="text-muted x-small"><?php echo e($b['kpi_description']); ?></div>
                                            </td>
                                            <td class="text-center">
                                                <?php
                                                $effective_score = $b['score_value'];
                                                $badge_html = '';
                                                if ($b['supervisor_override_score'] !== null) {
                                                    $effective_score = $b['supervisor_override_score'];
                                                    $sup_name_q = $conn->query("SELECT full_name FROM users WHERE user_id = " . (int)$b['supervisor_override_by'])->fetch_assoc();
                                                    $sup_name = $sup_name_q['full_name'] ?? 'Supervisor';
                                                    $formatted_date = formatDate($b['supervisor_override_at'], 'M d, Y h:i A');
                                                    $badge_html = '<span class="badge-audit ms-2" data-bs-toggle="tooltip" data-bs-html="true" title="<strong>Supervisor Override</strong><br>Edited by: ' . e($sup_name) . '<br>On: ' . $formatted_date . '<br>Original: ' . $b['score_value'] . '"><i class="fas fa-user-edit me-1"></i>Sup Override</span>';
                                                }
                                                if ($b['manager_override_score'] !== null) {
                                                    $effective_score = $b['manager_override_score'];
                                                    $mgr_name_q = $conn->query("SELECT full_name FROM users WHERE user_id = " . (int)$b['manager_override_by'])->fetch_assoc();
                                                    $mgr_name = $mgr_name_q['full_name'] ?? 'Manager';
                                                    $formatted_date = formatDate($b['manager_override_at'], 'M d, Y h:i A');
                                                    $badge_html = '<span class="badge-audit mgr ms-2" data-bs-toggle="tooltip" data-bs-html="true" title="<strong>Manager Override</strong><br>Edited by: ' . e($mgr_name) . '<br>On: ' . $formatted_date . '<br>Original: ' . $b['score_value'] . '"><i class="fas fa-user-shield me-1"></i>Mgr Override</span>';
                                                }
                                                ?>
                                                <span class="fw-bold text-dark"><?php echo number_format($effective_score, 2); ?></span>
                                                <?php echo $badge_html; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile;
                                endif; ?>
                                <tr class="total-row bg-light fw-bold border-top">
                                    <td class="ps-3">Behavior Average</td>
                                    <td class="text-center text-primary"><?php echo $row['behavior_average']; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Career Growth Info (Read-Only Representation) -->
                    <?php 
                    $cg_suited = !empty($row['career_growth_suited']) ? 1 : (!empty($row['desired_position']) ? 1 : 0);
                    ?>
                    <div class="section-premium-label mb-3 mt-4 fw-bold text-primary small text-uppercase" style="letter-spacing: 1px; border-bottom: 2px solid rgba(41,67,6,0.08); padding-bottom: 6px;">
                        <i class="fas fa-chart-line me-2"></i> III. Career Growth Recommendations
                    </div>
                    <div class="p-3 bg-light rounded-3 mb-4 border-start border-4 border-info">
                        <div class="mb-2 fw-semibold small text-dark">
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

                    <!-- Remarks & Feedback Block -->
                    <div class="section-premium-label mb-3 mt-4 fw-bold text-primary small text-uppercase" style="letter-spacing: 1px; border-bottom: 2px solid rgba(41,67,6,0.08); padding-bottom: 6px;">
                        <i class="fas fa-comments me-2"></i> IV. Remarks & Decisions
                    </div>
                    <?php if($row['supervisor_comments']): ?>
                        <div class="mb-3">
                            <label class="x-small fw-bold text-muted text-uppercase mb-1" style="font-size: 0.65rem;">Supervisor Feedback</label>
                            <div class="p-3 bg-light rounded-3 border small text-dark" style="font-style: italic;">
                                "<?php echo nl2br(e($row['supervisor_comments'])); ?>"
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if($row['manager_comments']): ?>
                        <div class="mb-3">
                            <label class="x-small fw-bold text-muted text-uppercase mb-1" style="font-size: 0.65rem;">Manager Final Comments</label>
                            <div class="p-3 bg-light rounded-3 border small text-dark" style="font-style: italic;">
                                "<?php echo nl2br(e($row['manager_comments'])); ?>"
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer border-0 pt-0 px-4 pb-4">
                    <div class="d-flex justify-content-between align-items-center w-100">
                        <div class="text-muted small">
                            <i class="fas fa-clock me-1"></i>
                            Last updated: <?php echo $row['updated_at'] ? formatDate($row['updated_at']) : 'N/A'; ?>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i>Close
                            </button>
                            <a href="../manager/print-evaluation.php?id=<?php echo $row['evaluation_id']; ?>" target="_blank" class="btn btn-primary btn-sm px-3">
                                <i class="fas fa-print me-1"></i>Print Form
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

let staffActiveStatus = 'All';

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    document.getElementById('historySearch')?.addEventListener('input', applyStaffFilters);
    document.getElementById('staffDeptFilter')?.addEventListener('change', applyStaffFilters);
    document.getElementById('staffTemplateFilter')?.addEventListener('change', applyStaffFilters);
});

function filterHistory(status, btn) {
    staffActiveStatus = status;
    const container = btn.closest('.btn-group');
    if (container) {
        container.querySelectorAll('.btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    }
    applyStaffFilters();
}

function applyStaffFilters() {
    const q = (document.getElementById('historySearch')?.value || '').toLowerCase().trim();
    const selDept = document.getElementById('staffDeptFilter')?.value || 'All';
    const selTmpl = document.getElementById('staffTemplateFilter')?.value || 'All';
    const rows = document.querySelectorAll('#historyTable tbody tr.history-row');
    let visible = 0;

    rows.forEach(row => {
        const statusMatch = staffActiveStatus === 'All' || row.dataset.status === staffActiveStatus;
        const deptMatch = selDept === 'All' || (row.dataset.department || '') === selDept;
        const tmplMatch = selTmpl === 'All' || (row.dataset.template || '') === selTmpl;
        const searchMatch = !q || (row.dataset.search || '').includes(q);
        const show = statusMatch && deptMatch && tmplMatch && searchMatch;
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    const noResRow = document.querySelector('#historyTable tbody tr.no-history-row');
    if (noResRow) {
        noResRow.style.display = (visible === 0) ? '' : 'none';
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
