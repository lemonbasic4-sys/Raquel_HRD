<?php
/**
 * HR Staff Portal - Dashboard (Observer View)
 */
$page_title = 'Staff Dashboard';
require_once '../includes/session-check.php';
checkRole(['HR Staff']);
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Fetch operational overview data for the observer role.
$employee_scope = "employee_id NOT IN (SELECT employee_id FROM users WHERE role = 'Admin' AND employee_id IS NOT NULL)";
$total_employees = (int)$conn->query("SELECT COUNT(*) as c FROM employees WHERE is_active = 1 AND $employee_scope")->fetch_assoc()['c'];
$pending_changes = (int)$conn->query("SELECT COUNT(*) as c FROM employee_change_requests WHERE status = 'Pending'")->fetch_assoc()['c'];
$active_packages = (int)$conn->query("SELECT COUNT(*) as c FROM evaluation_packages WHERE status <> 'Approved and Applied'")->fetch_assoc()['c'];

$evaluation_status_counts = ['Approved' => 0, 'Returned' => 0, 'Rejected' => 0];
$evaluation_status_result = $conn->query("SELECT status, COUNT(*) as total FROM evaluations WHERE status IN ('Approved', 'Returned', 'Rejected') GROUP BY status");
if ($evaluation_status_result) {
    while ($status_row = $evaluation_status_result->fetch_assoc()) {
        $evaluation_status_counts[$status_row['status']] = (int)$status_row['total'];
    }
}

$pending_requests = $conn->query("SELECT ecr.request_id, ecr.change_summary, ecr.created_at,
        CONCAT(e.first_name, ' ', e.last_name) AS employee_name
    FROM employee_change_requests ecr
    JOIN employees e ON e.employee_id = ecr.employee_id
    WHERE ecr.status = 'Pending'
    ORDER BY ecr.created_at DESC LIMIT 4");

$unread_notifications = 0;
if (isset($_SESSION['user_id'])) {
    $notification_stmt = $conn->prepare("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0");
    $notification_stmt->bind_param('i', $_SESSION['user_id']);
    $notification_stmt->execute();
    $unread_notifications = (int)($notification_stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $notification_stmt->close();
}

// Recent finalized evaluations in the system.
$recent = $conn->query("
    SELECT ev.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name, et.template_name
    FROM evaluations ev
    LEFT JOIN employees e ON ev.employee_id = e.employee_id
    LEFT JOIN evaluation_templates et ON ev.template_id = et.template_id
    WHERE ev.status IN ('Approved', 'Rejected', 'Returned') AND e.$employee_scope
    ORDER BY ev.updated_at DESC LIMIT 5
");
?>

<style>
    .staff-dashboard .attention-list { padding: 8px 20px 18px; }
    .staff-dashboard .attention-item {
        align-items: center;
        border-bottom: 1px solid #f0f4eb;
        display: flex;
        gap: 12px;
        padding: 14px 0;
    }
    .staff-dashboard .attention-item:last-child { border-bottom: 0; }
    .staff-dashboard .attention-icon {
        align-items: center;
        background: rgba(189, 148, 20, 0.12);
        border-radius: 10px;
        color: #a97800;
        display: inline-flex;
        flex-shrink: 0;
        height: 36px;
        justify-content: center;
        width: 36px;
    }
    .staff-dashboard .attention-copy { flex: 1; min-width: 0; }
    .staff-dashboard .attention-copy strong { display: block; font-size: .86rem; }
    .staff-dashboard .attention-copy small { color: var(--text-muted); display: block; margin-top: 2px; }
    .staff-dashboard .status-row { margin: 0 20px; padding: 14px 0; }
    .staff-dashboard .status-row + .status-row { border-top: 1px solid #f0f4eb; }
    .staff-dashboard .status-label { display: flex; justify-content: space-between; font-size: .82rem; font-weight: 600; margin-bottom: 6px; }
    .staff-dashboard .status-track { background: #edf1ea; border-radius: 99px; height: 7px; overflow: hidden; }
    .staff-dashboard .status-fill { border-radius: inherit; height: 100%; }

    .staff-dashboard .submission-list {
        padding: 15px;
    }

    .staff-dashboard .submission-item {
        align-items: center;
        background: #fff;
        border: 1px solid #f0f0f0;
        border-radius: 14px;
        display: grid;
        gap: 16px;
        grid-template-columns: minmax(0, 1.4fr) 150px 120px;
        margin-bottom: 12px;
        padding: 15px;
        transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .staff-dashboard .submission-item:hover {
        border-color: var(--primary-light);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        transform: translateX(5px);
    }

    .staff-dashboard .submission-main {
        align-items: center;
        display: flex;
        gap: 12px;
        min-width: 0;
    }

    .staff-dashboard .avatar-circle {
        align-items: center;
        background: rgba(41, 67, 6, 0.06);
        border-radius: 12px;
        color: var(--primary-blue);
        display: inline-flex;
        flex-shrink: 0;
        font-weight: 800;
        height: 42px;
        justify-content: center;
        width: 42px;
    }

    .staff-dashboard .submission-details {
        min-width: 0;
    }

    .staff-dashboard .submission-details h6 {
        font-size: 0.95rem;
        font-weight: 700;
        margin: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .staff-dashboard .submission-details span {
        color: var(--text-muted);
        display: block;
        font-size: 0.75rem;
        margin-top: 2px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .staff-dashboard .submission-meta {
        color: var(--text-muted);
        font-size: 0.75rem;
    }

    .staff-dashboard .submission-score {
        text-align: right;
    }

    .staff-dashboard .score-value {
        display: block;
        font-size: 0.9rem;
        font-weight: 800;
        margin-bottom: 5px;
    }

    .staff-dashboard .workflow-card {
        min-height: 100%;
    }

    .staff-dashboard .workflow-step {
        display: grid;
        gap: 12px;
        grid-template-columns: 38px minmax(0, 1fr);
        padding: 12px 0;
    }

    .staff-dashboard .workflow-step + .workflow-step {
        border-top: 1px solid #f0f4eb;
    }

    .staff-dashboard .workflow-step .step-icon {
        align-items: center;
        background: rgba(41, 67, 6, 0.08);
        border-radius: 10px;
        color: var(--primary-blue);
        display: inline-flex;
        height: 38px;
        justify-content: center;
        width: 38px;
    }

    .staff-dashboard .empty-state-card {
        color: var(--text-muted);
        padding: 42px 20px;
        text-align: center;
    }

    .staff-dashboard .empty-state-card i {
        display: block;
        font-size: 2.6rem;
        margin-bottom: 14px;
        opacity: 0.2;
    }

    @media (max-width: 768px) {
        .staff-dashboard .submission-item {
            grid-template-columns: 1fr;
        }

        .staff-dashboard .submission-item {
            align-items: stretch;
            gap: 12px;
        }

        .staff-dashboard .submission-item:hover {
            transform: none;
        }

        .staff-dashboard .submission-score,
        .staff-dashboard .submission-meta {
            text-align: left;
        }

        .staff-dashboard .submission-details h6,
        .staff-dashboard .submission-details span {
            white-space: normal;
        }
    }
</style>

<div class="staff-dashboard">
<div class="page-hero fadeup">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
        <div>
            <div class="mb-1" style="color:#FFD97D;font-size:.88rem;font-weight:600;letter-spacing:.3px;"><?php echo getGreeting($_SESSION['full_name'] ?? ''); ?></div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">HR Staff · Observer</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-eye me-2" style="color:#BD9414;"></i>Staff Workspace</h4>
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
                        <div class="stat-value"><?php echo $total_employees; ?></div>
                        <div class="stat-label">Active Employees</div>
                    </div>
                    <i class="fas fa-users stat-icon text-white-50"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo $pending_changes; ?></div>
                        <div class="stat-label">Pending Changes</div>
                    </div>
                    <i class="fas fa-clock stat-icon" style="color:#ffc107;"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo $active_packages; ?></div>
                        <div class="stat-label">Active Packages</div>
                    </div>
                    <i class="fas fa-layer-group stat-icon" style="color:#BD9414;"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?php echo $unread_notifications; ?></div>
                        <div class="stat-label">Unread Notifications</div>
                    </div>
                    <i class="fas fa-bell stat-icon" style="color:#0d6efd;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="chart-card h-100">
            <div class="cc-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-inbox me-2"></i>Needs Attention</h5>
                <span class="text-muted small">Current workload</span>
            </div>
            <div class="attention-list">
                <div class="attention-item">
                    <span class="attention-icon"><i class="fas fa-user-pen"></i></span>
                    <div class="attention-copy">
                        <strong>Employee change requests</strong>
                        <small><?php echo $pending_changes; ?> pending request<?php echo $pending_changes === 1 ? '' : 's'; ?> in the approval queue.</small>
                    </div>
                    <a href="<?php echo BASE_URL; ?>/staff/employees.php" class="btn btn-sm btn-outline-primary" title="View employee change requests"><i class="fas fa-arrow-up-right-from-square"></i></a>
                </div>
                <div class="attention-item">
                    <span class="attention-icon"><i class="fas fa-layer-group"></i></span>
                    <div class="attention-copy">
                        <strong>Evaluation packages</strong>
                        <small><?php echo $active_packages; ?> package<?php echo $active_packages === 1 ? '' : 's'; ?> still in progress.</small>
                    </div>
                    <a href="<?php echo BASE_URL; ?>/staff/package-tracker.php" class="btn btn-sm btn-outline-primary" title="View evaluation packages"><i class="fas fa-arrow-up-right-from-square"></i></a>
                </div>
                <div class="attention-item">
                    <span class="attention-icon"><i class="fas fa-bell"></i></span>
                    <div class="attention-copy">
                        <strong>Notifications</strong>
                        <small><?php echo $unread_notifications; ?> unread notification<?php echo $unread_notifications === 1 ? '' : 's'; ?> for your account.</small>
                    </div>
                    <a href="<?php echo BASE_URL; ?>/staff/notifications.php" class="btn btn-sm btn-outline-primary" title="View notifications"><i class="fas fa-arrow-up-right-from-square"></i></a>
                </div>
                <?php if ($pending_changes === 0 && $active_packages === 0 && $unread_notifications === 0): ?>
                    <div class="text-center text-muted small py-3"><i class="fas fa-check-circle text-success me-1"></i>No outstanding items.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="chart-card h-100">
            <div class="cc-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Evaluation Status</h5>
                <a href="<?php echo BASE_URL; ?>/staff/evaluation-history.php" class="text-primary small">View history</a>
            </div>
            <?php $evaluation_total = array_sum($evaluation_status_counts); ?>
            <?php foreach (['Approved' => ['bg-success', 'fa-check-circle'], 'Returned' => ['bg-warning', 'fa-undo'], 'Rejected' => ['bg-danger', 'fa-times-circle']] as $status_label => $status_meta): ?>
                <?php $status_total = $evaluation_status_counts[$status_label]; $status_width = $evaluation_total > 0 ? ($status_total / $evaluation_total) * 100 : 0; ?>
                <div class="status-row">
                    <div class="status-label"><span><?php echo $status_label; ?></span><span><?php echo $status_total; ?></span></div>
                    <div class="status-track"><div class="status-fill <?php echo $status_meta[0]; ?>" style="width:<?php echo $status_width; ?>%;"></div></div>
                </div>
            <?php endforeach; ?>
            <div class="text-muted small px-4 pb-3">Finalized records available to the HR Staff archive: <strong><?php echo $evaluation_total; ?></strong></div>
        </div>
    </div>
</div>

<div class="chart-card">
    <div class="cc-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Finalized Evaluations</h5>
        <a href="<?php echo BASE_URL; ?>/staff/evaluation-history.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">View All</a>
    </div>
    <div class="cc-body p-0">
        <div class="submission-list">
            <?php if (!$recent || $recent->num_rows === 0): ?>
                <div class="empty-state-card">
                    <i class="fas fa-folder-open"></i>
                    <p class="mb-0">No finalized evaluations in the system yet.</p>
                </div>
            <?php else: ?>
                <?php while ($row = $recent->fetch_assoc()):
                    $name_parts = preg_split('/\s+/', trim($row['employee_name'] ?? ''));
                    $initials = strtoupper(substr($name_parts[0] ?? 'U', 0, 1) . substr($name_parts[1] ?? '', 0, 1));
                    $score = $row['total_score'] !== null ? (float) $row['total_score'] : null;
                    $score_width = $score !== null ? min(100, max(0, ($score / 4) * 100)) : 0;
                    $score_label = $score !== null ? number_format($score, 2) . ' / 4' : '-';
                ?>
                    <div class="submission-item">
                        <div class="submission-main">
                            <div class="avatar-circle"><?php echo e($initials ?: 'U'); ?></div>
                            <div class="submission-details">
                                <h6><?php echo e($row['employee_name']); ?></h6>
                                <span><?php echo e($row['template_name'] ?? 'Evaluation template'); ?></span>
                            </div>
                        </div>
                        <div class="submission-meta">
                            <span class="badge <?php echo getStatusBadgeClass($row['status']); ?>"><?php echo e($row['status']); ?></span>
                            <div class="mt-2"><?php echo $row['updated_at'] ? formatDate($row['updated_at']) : formatDate($row['submitted_date']); ?></div>
                        </div>
                        <div class="submission-score">
                            <span class="score-value"><?php echo $score_label; ?></span>
                            <div class="progress" style="height: 4px;">
                                <div class="progress-bar <?php echo ($score >= 3.0) ? 'bg-success' : (($score >= 2.0) ? 'bg-primary' : 'bg-warning'); ?>" style="width: <?php echo $score_width; ?>%;"></div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>

<?php require_once '../includes/footer.php'; ?>
