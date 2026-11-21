<?php
$movement_employee_id = filter_input(INPUT_GET, 'movement_employee', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$movement_employees = [];
$movement_history = [];
if (ensureCareerProgressionMovements($conn)) {
    $employee_result = $conn->query("SELECT DISTINCT e.employee_id, e.employee_code, e.first_name, e.last_name
        FROM career_movements cm JOIN employees e ON e.employee_id = cm.employee_id
        WHERE cm.approval_status = 'Approved' AND cm.is_applied = 1
        ORDER BY e.last_name, e.first_name, e.employee_id");
    $movement_employees = $employee_result->fetch_all(MYSQLI_ASSOC);
    if ($movement_employee_id) {
        $history_stmt = $conn->prepare("SELECT cm.movement_type, cm.effective_date,
                cm.previous_department_name, cm.new_department_name,
                pb.branch_name AS previous_branch, nb.branch_name AS new_branch,
                cm.previous_position, cm.new_position
            FROM career_movements cm
            LEFT JOIN branches pb ON pb.branch_id = cm.previous_branch_id
            LEFT JOIN branches nb ON nb.branch_id = cm.new_branch_id
            WHERE cm.employee_id = ? AND cm.approval_status = 'Approved' AND cm.is_applied = 1
            ORDER BY cm.effective_date DESC, cm.movement_id DESC");
        $history_stmt->bind_param('i', $movement_employee_id);
        $history_stmt->execute();
        $movement_history = $history_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $history_stmt->close();
    }
}
?>
<section class="chart-card mb-3" aria-labelledby="movement-history-title">
    <div class="cc-header"><h6 id="movement-history-title"><i class="fas fa-route me-2"></i>Employee Movement History</h6></div>
    <div class="cc-body">
        <form method="get" class="d-flex flex-wrap align-items-end gap-2 mb-3">
            <input type="hidden" name="tab" value="mobility">
            <label class="form-label mb-0" for="movementEmployee">Employee
                <select class="form-select mt-1" id="movementEmployee" name="movement_employee" required>
                    <option value="">Select an employee</option>
                    <?php foreach ($movement_employees as $employee): ?>
                    <option value="<?php echo (int)$employee['employee_id']; ?>" <?php echo $movement_employee_id === (int)$employee['employee_id'] ? 'selected' : ''; ?>><?php echo e($employee['last_name'] . ', ' . $employee['first_name'] . ' (' . $employee['employee_code'] . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="btn btn-success" type="submit">View movements</button>
        </form>
        <?php if ($movement_employee_id && !$movement_history): ?>
            <p class="text-muted mb-0">No applied, approved movements found for this employee.</p>
        <?php elseif ($movement_history): ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Date</th><th>Movement</th><th>Origin department</th><th>Destination department</th><th>Origin branch</th><th>Destination branch</th><th>Position</th></tr></thead>
                    <tbody><?php foreach ($movement_history as $movement): ?>
                    <tr>
                        <td><?php echo e(formatDate($movement['effective_date'])); ?></td>
                        <td><?php echo e($movement['movement_type']); ?></td>
                        <td><?php echo e($movement['previous_department_name'] ?: 'Not recorded'); ?></td>
                        <td><?php echo e($movement['new_department_name'] ?: 'Not recorded'); ?></td>
                        <td><?php echo e($movement['previous_branch'] ?: 'Not recorded'); ?></td>
                        <td><?php echo e($movement['new_branch'] ?: ($movement['previous_branch'] ?: 'Not recorded')); ?></td>
                        <td><?php echo e(($movement['previous_position'] ?: 'Not recorded') . ' → ' . ($movement['new_position'] ?: 'Not recorded')); ?></td>
                    </tr>
                    <?php endforeach; ?></tbody>
                </table>
            </div>
            <p class="small text-muted mt-2 mb-0">Older movements may have no saved department history.</p>
        <?php else: ?>
            <p class="text-muted mb-0">Select an employee to view where each approved movement originated and went.</p>
        <?php endif; ?>
    </div>
</section>
