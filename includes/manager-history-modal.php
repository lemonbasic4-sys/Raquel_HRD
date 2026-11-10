<?php
if (!defined('HR_HISTORY_DETAIL_RENDER')) { http_response_code(403); exit; }
$status = $row['status'];
$initials = strtoupper(substr($row['employee_name'], 0, 1) . substr(explode(' ', $row['employee_name'])[1] ?? '', 0, 1));
?>
    <!-- History Modal for <?php echo $row['evaluation_id']; ?> -->
    <div class="modal fade modal-premium history-detail-modal" id="reviewModal<?php echo $row['evaluation_id']; ?>" tabindex="-1" aria-labelledby="reviewModalTitle<?php echo $row['evaluation_id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header history-modal-header">
                    <div class="history-modal-heading">
                        <span class="history-modal-icon"><i class="fas fa-file-circle-check"></i></span>
                        <div>
                            <h5 class="modal-title mb-1" id="reviewModalTitle<?php echo $row['evaluation_id']; ?>">Evaluation Details</h5>
                            <p class="history-modal-subtitle"><?php echo e($row['employee_name']); ?> <span aria-hidden="true">·</span> <?php echo e($row['template_name']); ?></p>
                        </div>
                    </div>
                    <span class="history-modal-status <?php echo e(strtolower($status)); ?>">
                        <i class="fas <?php echo $status === 'Imported' ? 'fa-file-import' : ($status === 'Approved' ? 'fa-circle-check' : ($status === 'Rejected' ? 'fa-circle-xmark' : 'fa-rotate-left')); ?>"></i>
                        <?php echo e($status); ?>
                    </span>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close evaluation details"></button>
                </div>
                <div class="modal-body">
                    <?php if ($status === 'Imported'): ?><div class="alert alert-info"><strong>Imported historical record.</strong> No new HRIS approval or employee consent was recorded.<?php if (!empty($row['legacy_reference'])): ?> Reference: <?php echo e($row['legacy_reference']); ?>.<?php endif; ?></div><?php endif; ?>
                    <?php if ($status === 'Imported' && ($row['kra_subtotal'] === null || $row['behavior_average'] === null)): ?><div class="alert alert-secondary">Only the available summary scores were supplied for this historical record.</div><?php endif; ?>
                    <!-- Status Stepper -->
                    <?php if ($status !== 'Imported'): ?>
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
                            <?php
                            $step_state = !$st['a']
                                ? 'is-upcoming'
                                : ($st['c'] ? ($status === 'Rejected' ? 'is-rejected' : 'is-terminal') : 'is-complete');
                            ?>
                            <div class="step-item text-center <?php echo $step_state; ?>">
                                <div class="history-step-icon">
                                    <i class="fas <?php echo e($st['i']); ?>"></i>
                                </div>
                                <div class="fw-bold"><?php echo e($st['l']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="eval-summary-header">
                        <div class="d-flex align-items-center gap-3">
                            <div class="history-employee-avatar">
                                <?php if (!empty($row['profile_picture'])): ?>
                                    <img src="<?php echo e(getEmployeeAvatar($row['profile_picture'])); ?>" alt="<?php echo e($row['employee_name']); ?> profile picture">
                                <?php else: ?>
                                    <?php echo e($initials); ?>
                                <?php endif; ?>
                            </div>
                            <div class="history-employee-copy">
                                <h4 class="mb-1 fw-bold text-dark"><?php echo e($row['employee_name']); ?></h4>
                                <div class="history-employee-meta d-flex align-items-center gap-2 flex-wrap">
                                    <span class="badge bg-white text-secondary border fw-semibold"><?php echo e($row['job_title'] ?? 'Staff'); ?></span>
                                    <span><?php echo e($row['department_name'] ?? 'Department not set'); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="history-summary-actions d-print-none">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <button type="button" class="btn btn-sm btn-success rounded-pill px-3 fw-bold btn-save-ratings d-none" onclick="saveRatings(<?php echo $row['evaluation_id']; ?>)">
                                    <i class="fas fa-save me-1"></i>Save Changes
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold btn-cancel-ratings d-none" onclick="toggleEditRatings(<?php echo $row['evaluation_id']; ?>, true)">
                                    <i class="fas fa-times me-1"></i>Cancel
                                </button>
                                <?php if ($status !== 'Imported'): ?><a href="print-evaluation.php?id=<?php echo $row['evaluation_id']; ?>" target="_blank" class="btn btn-sm btn-primary rounded-pill px-3 py-2 fw-bold d-inline-flex align-items-center gap-2 shadow-sm">
                                    <i class="fas fa-print"></i>
                                    <span>Print Form</span>
                                </a><?php endif; ?>
                            </div>
                            <div class="d-flex align-items-center gap-2"><?php echo getEvaluationScoreCirclesHtml($conn, $row['evaluation_id'], $row['total_score']); ?></div>
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
