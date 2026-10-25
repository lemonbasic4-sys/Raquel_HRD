<?php
// Included only for the HRD manager inside Performance Evaluation > Regular Employee.
$hrdDepartmentId=(int)$actor['department_id'];
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
?>
<section class="content-card p-3 p-md-4 mb-4" id="annual-cycle-start" aria-labelledby="hrdCycleStartTitle">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
        <div>
            <h2 class="h5 fw-bold mb-1" id="hrdCycleStartTitle"><i class="fas fa-calendar-check me-2" aria-hidden="true"></i>Human Resources Annual cycle</h2>
            <p class="small text-muted mb-0">The HR Manager starts this cycle after the President/CEO, HR Manager, and HR Supervisor have created the Annual forms for their direct reports.</p>
        </div>
        <span class="badge <?php echo $hrdOpenCycle ? 'bg-info text-dark' : ($hrdReady && !$hrdOverlap ? 'bg-success' : 'bg-warning text-dark'); ?>">
            <?php echo $hrdOpenCycle ? 'Cycle open' : ($hrdOverlap ? 'Period already exists' : ($hrdReady ? 'Forms ready' : 'Forms or accounts needed')); ?>
        </span>
    </div>
    <?php if ($hrdOpenCycle): ?>
        <p class="mb-0">The <?php echo e(formatDate($hrdOpenCycle['period_start'])); ?> to <?php echo e(formatDate($hrdOpenCycle['period_end'])); ?> cycle is open. Complete and approve it before starting another period.</p>
    <?php else: ?>
        <form method="get" class="row g-2 align-items-end mb-3">
            <input type="hidden" name="category" value="regular"><input type="hidden" name="view" value="peers">
            <div class="col-sm-6 col-lg-3"><label class="form-label" for="hrdCycleFrom">Period From</label><input class="form-control" type="date" id="hrdCycleFrom" name="cycle_from" value="<?php echo e($periodFrom); ?>" required></div>
            <div class="col-sm-6 col-lg-3"><label class="form-label" for="hrdCycleTo">Period To</label><input class="form-control" type="date" id="hrdCycleTo" name="cycle_to" value="<?php echo e($periodTo); ?>" required></div>
            <div class="col-sm-6 col-lg-3"><label class="form-label" for="hrdCycleDeadline">Self-rating deadline</label><input class="form-control" type="date" id="hrdCycleDeadline" name="cycle_deadline" value="<?php echo e($selfDeadline); ?>" required></div>
            <div class="col-sm-6 col-lg-3"><button class="btn btn-outline-success w-100" type="submit">Preview period</button></div>
        </form>
        <?php if ($hrdOverlap): ?><p class="alert alert-info mb-3">An HRD cycle already covers this period. Choose another From/To period.</p><?php endif; ?>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead><tr><th>Employee</th><th>Annual form created by</th><th>Readiness</th></tr></thead>
            <tbody>
                <?php foreach ($hrdRoster as $person): ?>
                <tr><td><?php echo e($person['first_name'].' '.$person['last_name'].' · '.$person['job_title']); ?></td>
                    <td><?php echo e($person['reviewer_position_name'] ?: 'Immediate head missing'); ?></td>
                    <td><?php if ($person['issues']): ?><span class="text-danger"><?php echo e(implode('; ',$person['issues'])); ?></span><?php else: ?><span class="text-success fw-semibold"><i class="fas fa-check-circle me-1" aria-hidden="true"></i>Ready</span><?php endif; ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$hrdRoster): ?><tr><td colspan="3" class="text-muted text-center py-3">No active regular HRD employees found.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
        <?php if ($hrdReady && !$hrdOverlap): ?>
            <form method="post" class="mt-3 d-flex justify-content-end" onsubmit="return confirm('Start the HRD Annual cycle and notify the employees shown above?');">
                <?php echo csrfField(); ?><input type="hidden" name="action" value="start_hrd_cycle">
                <input type="hidden" name="period_start" value="<?php echo e($periodFrom); ?>">
                <input type="hidden" name="period_end" value="<?php echo e($periodTo); ?>">
                <input type="hidden" name="self_rating_deadline" value="<?php echo e($selfDeadline); ?>">
                <button class="btn btn-success" type="submit"><i class="fas fa-play me-2" aria-hidden="true"></i>Start HRD cycle and notify employees</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</section>
