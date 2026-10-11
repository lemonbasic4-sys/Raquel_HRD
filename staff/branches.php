<?php
$page_title = 'Branches & Roster Monitor';
require_once '../includes/session-check.php';
checkRole(['HR Staff']);
require_once '../includes/functions.php';

$search = trim($_GET['search'] ?? '');
$where = ["b.is_active = 1"];
$params = [];
$types = "";

if ($search !== '') {
    $where[] = "(b.branch_name LIKE ? OR b.location LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

$where_clause = implode(" AND ", $where);
$sql = "SELECT b.*,
    (SELECT COUNT(*) FROM employees e WHERE e.branch_id = b.branch_id AND e.is_active = 1 AND e.deleted_at IS NULL AND e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)) as active_employees
    FROM branches b
    WHERE $where_clause
    ORDER BY b.branch_name ASC";

$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$branches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total_branches = count($branches);
$total_headcount = (int)($conn->query("SELECT COUNT(*) as c FROM employees e WHERE e.is_active = 1 AND e.deleted_at IS NULL AND e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)")->fetch_assoc()['c'] ?? 0);

require_once '../includes/header.php';
?>

<div class="staff-branches staff-branches-page container-fluid py-4">
    <div class="page-hero fadeup mb-4" style="background: linear-gradient(135deg, #1b2e04 0%, #294306 100%); border-radius: 16px; padding: 24px; color: #fff;">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.6);">HR Staff &middot; Organizational Directory</div>
                <h3 class="fw-bold mb-1 mt-1 text-white"><i class="fas fa-building me-2" style="color:#BD9414;"></i>Branches &amp; Headcount Roster</h3>
                <p class="text-white-50 small mb-0">Monitor branch office locations, active headcount distributions, and employee assignments across Raquel Pawnshop network.</p>
            </div>
            <div class="badge bg-white text-dark border-0 py-2 px-3" style="border-radius:20px;font-size:.8rem;box-shadow:0 4px 10px rgba(0,0,0,.15);">
                <i class="fas fa-eye me-1 text-primary"></i>Read-Only Directory Access
            </div>
        </div>

        <div class="row g-3 mt-3">
            <div class="col-6 col-md-6">
                <div class="p-3 rounded-3" style="background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.12);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="h3 fw-bold mb-0 text-white"><?php echo $total_branches; ?></div>
                            <div class="small text-white-50">Active Branches</div>
                        </div>
                        <i class="fas fa-building fa-2x text-warning" style="opacity:0.8;"></i>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-6">
                <div class="p-3 rounded-3" style="background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.12);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="h3 fw-bold mb-0 text-info"><?php echo $total_headcount; ?></div>
                            <div class="small text-white-50">Total Active Employees</div>
                        </div>
                        <i class="fas fa-users fa-2x text-info" style="opacity:0.8;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-9">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search branch name or location..." value="<?php echo e($search); ?>">
                    </div>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Search</button>
                    <?php if ($search !== ''): ?>
                        <a href="branches.php" class="btn btn-outline-secondary"><i class="fas fa-redo"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Branch Cards Grid -->
    <div class="row g-4">
        <?php foreach ($branches as $branch): ?>
            <?php
            $b_id = (int)$branch['branch_id'];
            $emp_stmt = $conn->prepare("SELECT e.employee_id, e.first_name, e.last_name, e.job_title, d.department_name
                FROM employees e
                LEFT JOIN departments d ON d.department_id = e.department_id
                WHERE e.branch_id = ? AND e.is_active = 1 AND e.deleted_at IS NULL AND e.employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)
                ORDER BY e.last_name, e.first_name LIMIT 5");
            $emp_stmt->bind_param('i', $b_id);
            $emp_stmt->execute();
            $roster = $emp_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $emp_stmt->close();
            ?>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill">
                                    <i class="fas fa-users me-1"></i><?php echo $branch['active_employees']; ?> Employee<?php echo $branch['active_employees'] === 1 ? '' : 's'; ?>
                                </span>
                                <span class="badge bg-success-subtle text-success">Active</span>
                            </div>
                            <h5 class="fw-bold text-dark mb-1"><?php echo e($branch['branch_name']); ?></h5>
                            <div class="small text-muted mb-3"><i class="fas fa-map-marker-alt me-1 text-danger"></i><?php echo e($branch['location'] ?: 'Unspecified Location'); ?></div>

                            <div class="border-top pt-3">
                                <div class="x-small text-muted text-uppercase fw-bold mb-2">Branch Roster Preview</div>
                                <?php if (empty($roster)): ?>
                                    <div class="small text-muted fst-italic">No active employees assigned to this branch.</div>
                                <?php else: ?>
                                    <ul class="list-unstyled mb-0 small">
                                        <?php foreach ($roster as $emp): ?>
                                            <li class="d-flex justify-content-between align-items-center py-1 border-bottom-subtle">
                                                <span class="fw-semibold text-dark"><?php echo e($emp['first_name'] . ' ' . $emp['last_name']); ?></span>
                                                <span class="x-small text-muted"><?php echo e($emp['job_title']); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="pt-3 mt-3 border-top text-end">
                            <a href="employees.php?search=<?php echo urlencode($branch['branch_name']); ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                View Full Roster <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
