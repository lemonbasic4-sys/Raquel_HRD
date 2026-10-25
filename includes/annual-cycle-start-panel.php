<?php
// Included for the configured cycle owner or AP VP/Supervisor in read-only mode.
$hrdDepartmentId=(int)$actor['department_id'];
$cycleSetupReadOnly=!annualCanOwnCycle($conn,$actor,$hrdDepartmentId);
$cycleDepartmentName=(string)$actor['department_name'];
$cycleActionName=annualIsHrDepartment($actor) ? 'HRD' : $cycleDepartmentName;
$annual_in_performance_page=$annual_in_performance_page ?? false;
$periodFrom=(string)($_POST['period_start'] ?? $_GET['cycle_from'] ?? date('Y').'-01-01');
$periodTo=(string)($_POST['period_end'] ?? $_GET['cycle_to'] ?? date('Y').'-12-31');
$defaultDeadline=date('Y-m-d',max(strtotime('+14 days'),strtotime($periodTo.' +14 days')));
$selfDeadline=(string)($_POST['self_rating_deadline'] ?? $_GET['cycle_deadline'] ?? $defaultDeadline);
$hrdOpenCycle=annualQuery($conn,'SELECT cycle_id,period_start,period_end FROM annual_evaluation_cycles
    WHERE department_id=? AND started_at IS NOT NULL AND completed_at IS NULL ORDER BY period_end DESC LIMIT 1',[$hrdDepartmentId])[0] ?? null;
$hrdOverlap=null;
if (preg_match('/^\d{4}-\d{2}-\d{2}$/',$periodFrom) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$periodTo)) {
    $hrdOverlap=annualQuery($conn,'SELECT cycle_id,period_start,period_end FROM annual_evaluation_cycles
        WHERE department_id=? AND period_start<=? AND period_end>=? LIMIT 1',[$hrdDepartmentId,$periodTo,$periodFrom])[0] ?? null;
}
$hrdRoster=annualCycleRosterPreview($conn,$hrdDepartmentId);
$hrdReady=$hrdRoster && !array_filter($hrdRoster,static fn($row)=>!empty($row['issues']));
$positionRoster=annualCyclePositionReadinessRows($hrdRoster);
?>
<section class="content-card p-3 p-md-4 mb-4" id="annual-cycle-start" aria-labelledby="hrdCycleStartTitle">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap <?php echo $cycleSetupReadOnly ? 'mb-0' : 'mb-3'; ?>">
        <div>
            <h2 class="h5 fw-bold mb-1" id="hrdCycleStartTitle"><i class="fas fa-calendar-check me-2" aria-hidden="true"></i><?php echo e($cycleDepartmentName); ?> Annual cycle</h2>
            <p class="small text-muted mb-0"><?php echo $cycleSetupReadOnly ? ($hrdOpenCycle ? formatDate($hrdOpenCycle['period_start']).' to '.formatDate($hrdOpenCycle['period_end']).' · View cycle details below.' : 'Read-only form readiness. The AP Manager starts the cycle.') : 'Start this department cycle after each immediate head has created the Annual form for their direct reports.'; ?></p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <?php if ($cycleSetupReadOnly): ?><span class="badge bg-secondary">Read only</span><?php endif; ?>
            <span class="badge <?php echo $hrdOpenCycle ? 'bg-info text-dark' : ($hrdReady && !$hrdOverlap ? 'bg-success' : 'bg-warning text-dark'); ?>">
                <?php echo $hrdOpenCycle ? 'Cycle open' : ($hrdOverlap ? 'Period already exists' : ($hrdReady ? 'Forms ready' : 'Forms or accounts needed')); ?>
            </span>
            <?php if ($cycleSetupReadOnly): ?><button class="btn btn-outline-success btn-sm collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#annualCycleReadOnlyDetails" aria-expanded="false" aria-controls="annualCycleReadOnlyDetails"><i class="fas fa-chevron-down me-1" aria-hidden="true"></i>Details</button><?php endif; ?>
        </div>
    </div>
    <div<?php if ($cycleSetupReadOnly): ?> class="collapse pt-3" id="annualCycleReadOnlyDetails"<?php endif; ?>>
    <?php if ($hrdOpenCycle): ?>
        <p class="mb-0">The <?php echo e(formatDate($hrdOpenCycle['period_start'])); ?> to <?php echo e(formatDate($hrdOpenCycle['period_end'])); ?> cycle is open. <?php echo $cycleSetupReadOnly ? 'The AP Manager will approve it after all required ratings are complete.' : 'Complete and approve it before starting another period.'; ?></p>
    <?php elseif (!$cycleSetupReadOnly): ?>
        <form method="get" class="row g-2 align-items-end mb-3">
            <?php if ($annual_in_performance_page): ?><input type="hidden" name="category" value="regular"><?php endif; ?><input type="hidden" name="view" value="peers">
            <div class="col-sm-6 col-lg-3"><label class="form-label" for="hrdCycleFrom">Period From</label><input class="form-control" type="date" id="hrdCycleFrom" name="cycle_from" value="<?php echo e($periodFrom); ?>" required></div>
            <div class="col-sm-6 col-lg-3"><label class="form-label" for="hrdCycleTo">Period To</label><input class="form-control" type="date" id="hrdCycleTo" name="cycle_to" value="<?php echo e($periodTo); ?>" required></div>
            <div class="col-sm-6 col-lg-3"><label class="form-label" for="hrdCycleDeadline">Self-rating deadline</label><input class="form-control" type="date" id="hrdCycleDeadline" name="cycle_deadline" value="<?php echo e($selfDeadline); ?>" required></div>
            <div class="col-sm-6 col-lg-3"><button class="btn btn-outline-success w-100" type="submit">Preview period</button></div>
        </form>
        <?php if ($hrdOverlap): ?><p class="alert alert-info mb-3">A <?php echo e($cycleDepartmentName); ?> cycle already covers this period. Choose another From/To period.</p><?php endif; ?>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead><tr><th>Position</th><th>Employees</th><th>Annual form created by</th><th>Readiness</th></tr></thead>
            <tbody>
                <?php foreach ($positionRoster as $position): ?>
                <tr><td class="fw-semibold"><?php echo e($position['job_title']); ?></td>
                    <td><?php echo (int)$position['employee_count']; ?></td>
                    <td><?php echo e($position['reviewer_position_name'] ?: 'Immediate head missing'); ?></td>
                    <td><?php if ($position['issues']): ?><span class="text-danger"><?php echo (int)$position['blocked_count']; ?> of <?php echo (int)$position['employee_count']; ?> need attention: <?php echo e(implode('; ',$position['issues'])); ?></span><?php else: ?><span class="text-success fw-semibold"><i class="fas fa-check-circle me-1" aria-hidden="true"></i>Ready</span><?php endif; ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$positionRoster): ?><tr><td colspan="4" class="text-muted text-center py-3">No active regular employees found.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
        <?php if ($hrdReady && !$hrdOverlap): ?>
            <div class="mt-3 d-flex justify-content-center">
                <button class="btn btn-success<?php echo annualIsApDepartment($actor) ? ' annual-cycle-start-breathe' : ''; ?>" type="button" data-bs-toggle="modal" data-bs-target="#annualCycleStartConfirmModal"><i class="fas fa-play me-2" aria-hidden="true"></i>Start <?php echo e($cycleActionName); ?> cycle and notify employees</button>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <p class="small text-muted mb-3">The AP Manager has not started a cycle. The current Annual form readiness is shown below.</p>
    <?php endif; ?>
    <?php if ($cycleSetupReadOnly): ?>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead><tr><th>Position</th><th>Employees</th><th>Annual form created by</th><th>Readiness</th></tr></thead>
            <tbody>
                <?php foreach ($positionRoster as $position): ?>
                <tr><td class="fw-semibold"><?php echo e($position['job_title']); ?></td>
                    <td><?php echo (int)$position['employee_count']; ?></td>
                    <td><?php echo e($position['reviewer_position_name'] ?: 'Immediate head missing'); ?></td>
                    <td><?php if ($position['issues']): ?><span class="text-danger"><?php echo (int)$position['blocked_count']; ?> of <?php echo (int)$position['employee_count']; ?> need attention: <?php echo e(implode('; ',$position['issues'])); ?></span><?php else: ?><span class="text-success fw-semibold"><i class="fas fa-check-circle me-1" aria-hidden="true"></i>Ready</span><?php endif; ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$positionRoster): ?><tr><td colspan="4" class="text-muted text-center py-3">No active regular AP employees found.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
        <?php if (!$hrdOpenCycle): ?>
            <p class="alert alert-info mt-3 mb-0" role="status"><i class="fas fa-clock me-2" aria-hidden="true"></i><?php echo $hrdReady ? 'All Annual forms are ready. Please wait for the AP Manager to start the evaluation cycle.' : 'Please wait for the AP Manager to start the evaluation cycle after the required Annual forms and accounts are ready.'; ?></p>
        <?php endif; ?>
    <?php endif; ?>
    </div>
</section>
<?php if (!$cycleSetupReadOnly && !$hrdOpenCycle && $hrdReady && !$hrdOverlap): ?>
<div class="modal fade" id="annualCycleStartConfirmModal" tabindex="-1" aria-labelledby="annualCycleStartConfirmTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5 mb-0" id="annualCycleStartConfirmTitle"><i class="fas fa-calendar-check me-2" aria-hidden="true"></i>Start Annual cycle?</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Start the department Annual cycle and notify the employees shown above?</p>
                <dl class="row mb-0 small">
                    <dt class="col-5">Department</dt><dd class="col-7"><?php echo e($cycleDepartmentName); ?></dd>
                    <dt class="col-5">Period</dt><dd class="col-7"><?php echo e(formatDate($periodFrom)); ?> to <?php echo e(formatDate($periodTo)); ?></dd>
                    <dt class="col-5">Self-rating deadline</dt><dd class="col-7"><?php echo e(formatDate($selfDeadline)); ?></dd>
                    <dt class="col-5">Employees</dt><dd class="col-7"><?php echo count($hrdRoster); ?></dd>
                </dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="post" class="m-0">
                    <?php echo csrfField(); ?><input type="hidden" name="action" value="start_annual_cycle">
                    <input type="hidden" name="period_start" value="<?php echo e($periodFrom); ?>">
                    <input type="hidden" name="period_end" value="<?php echo e($periodTo); ?>">
                    <input type="hidden" name="self_rating_deadline" value="<?php echo e($selfDeadline); ?>">
                    <button class="btn btn-success" type="submit"><i class="fas fa-play me-2" aria-hidden="true"></i>Start cycle and notify</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
