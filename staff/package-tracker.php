<?php
$page_title = 'Evaluation Package Tracker';
require_once '../includes/session-check.php';
checkRole(['HR Staff']);
require_once '../includes/functions.php';

ensureOrganizationEvaluationPackageSchema($conn);
syncPendingOrganizationPackageGovernanceApprovers($conn);
syncWaitingOrganizationPackages($conn);

// Filter params
$status_filter = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

$where = ["1=1"];
$params = [];
$types = "";

if ($status_filter !== '') {
    if ($status_filter === 'Active') {
        $where[] = "ep.status <> 'Approved and Applied'";
    } elseif ($status_filter === 'Completed') {
        $where[] = "ep.status = 'Approved and Applied'";
    } else {
        $where[] = "ep.status = ?";
        $params[] = $status_filter;
        $types .= "s";
    }
}

if ($search !== '') {
    $where[] = "(d.department_name LIKE ? OR et.template_name LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

$where_clause = implode(" AND ", $where);
$sql = "SELECT ep.*, d.department_name, et.template_name, et.kra_weight, et.behavior_weight
    FROM evaluation_packages ep
    JOIN departments d ON d.department_id = ep.department_id
    JOIN evaluation_templates et ON et.template_id = ep.template_id
    WHERE $where_clause
    ORDER BY ep.updated_at DESC";

$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$packages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Metrics
$total_packages = (int)($conn->query("SELECT COUNT(*) as c FROM evaluation_packages")->fetch_assoc()['c'] ?? 0);
$active_packages = (int)($conn->query("SELECT COUNT(*) as c FROM evaluation_packages WHERE status <> 'Approved and Applied'")->fetch_assoc()['c'] ?? 0);
$completed_packages = (int)($conn->query("SELECT COUNT(*) as c FROM evaluation_packages WHERE status = 'Approved and Applied'")->fetch_assoc()['c'] ?? 0);

require_once '../includes/header.php';
?>

<div class="staff-package-tracker container-fluid py-4">
    <div class="page-hero package-tracker-hero fadeup mb-4" style="background: linear-gradient(135deg, #1b2e04 0%, #294306 100%); border-radius: 16px; padding: 24px; color: #fff;">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="package-tracker-hero-copy">
                <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.6);">HR Staff &middot; Evaluation Governance Monitor</div>
                <h3 class="fw-bold mb-1 mt-1 text-white"><i class="fas fa-tasks me-2" style="color:#BD9414;"></i>Evaluation Package Progress Tracker</h3>
                <p class="text-white-50 small mb-0">Monitor department evaluation package consolidation progress, member self-ratings, and governance approval pipelines.</p>
            </div>
            <div class="badge package-tracker-access bg-white text-dark border-0 py-2 px-3" style="border-radius:20px;font-size:.8rem;box-shadow:0 4px 10px rgba(0,0,0,.15);">
                <i class="fas fa-eye me-1 text-primary"></i>Read-Only Monitoring Access
            </div>
        </div>

        <div class="row g-3 mt-3">
            <div class="col-6 col-md-4">
                <div class="package-tracker-metric p-3 rounded-3" style="background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.12);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="h3 fw-bold mb-0 text-white"><?php echo $total_packages; ?></div>
                            <div class="small text-white-50">Total Packages</div>
                        </div>
                        <i class="fas fa-layer-group fa-2x text-warning" style="opacity:0.8;"></i>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="package-tracker-metric p-3 rounded-3" style="background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.12);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="h3 fw-bold mb-0 text-warning"><?php echo $active_packages; ?></div>
                            <div class="small text-white-50">In Progress / Active</div>
                        </div>
                        <i class="fas fa-hourglass-half fa-2x text-warning" style="opacity:0.8;"></i>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="package-tracker-metric p-3 rounded-3" style="background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.12);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="h3 fw-bold mb-0 text-success"><?php echo $completed_packages; ?></div>
                            <div class="small text-white-50">Finalized & Locked</div>
                        </div>
                        <i class="fas fa-check-circle fa-2x text-success" style="opacity:0.8;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="package-tracker-filter card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search department or template name..." value="<?php echo e($search); ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="Active" <?php echo $status_filter === 'Active' ? 'selected' : ''; ?>>Active / In Progress</option>
                        <option value="Completed" <?php echo $status_filter === 'Completed' ? 'selected' : ''; ?>>Completed / Approved & Applied</option>
                        <option value="Pending Self-Ratings" <?php echo $status_filter === 'Pending Self-Ratings' ? 'selected' : ''; ?>>Pending Self-Ratings</option>
                        <option value="Pending Consolidation" <?php echo $status_filter === 'Pending Consolidation' ? 'selected' : ''; ?>>Pending Consolidation</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Filter</button>
                    <?php if ($search !== '' || $status_filter !== ''): ?>
                        <a href="package-tracker.php" class="btn btn-outline-secondary"><i class="fas fa-redo"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Package Cards List -->
    <?php if (empty($packages)): ?>
        <div class="card border-0 shadow-sm rounded-4 text-center py-5">
            <div class="card-body">
                <i class="fas fa-inbox fa-3x text-muted mb-3" style="opacity:0.4;"></i>
                <h5 class="fw-bold">No evaluation packages found</h5>
                <p class="text-muted small mb-0">Try clearing your search query or filters.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($packages as $pkg): ?>
                <?php
                $pkg_id = (int)$pkg['package_id'];
                $summary = getOrganizationPackageSubmissionSummary($conn, $pkg);
                
                // Fetch route steps for pipeline label
                $route_stmt = $conn->prepare("SELECT step_order, step_label, action_status, acted_at, comments FROM evaluation_package_route_steps WHERE package_id = ? ORDER BY step_order");
                $route_stmt->bind_param('i', $pkg_id);
                $route_stmt->execute();
                $steps = $route_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $route_stmt->close();
                ?>
                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden" style="border-left: 4px solid <?php echo $pkg['status'] === 'Approved and Applied' ? '#28a745' : '#ffc107'; ?> !important;">
                        <div class="package-card-body card-body p-4 d-flex flex-column justify-content-between">
                            <div>
                                <div class="package-card-header-row d-flex align-items-start justify-content-between gap-2 mb-2">
                                    <div class="package-card-title">
                                        <h5 class="fw-bold mb-1 text-dark"><?php echo e($pkg['department_name']); ?></h5>
                                        <div class="small text-muted"><i class="fas fa-file-alt me-1"></i><?php echo e($pkg['template_name']); ?></div>
                                    </div>
                                    <div class="package-card-status">
                                        <?php echo renderOrganizationPipelineBadge($conn, $pkg_id); ?>
                                    </div>
                                </div>

                                <div class="package-stat-strip bg-light p-3 rounded-3 mb-3 my-3">
                                    <div class="row text-center g-2">
                                        <div class="col-4">
                                            <div class="x-small text-muted text-uppercase fw-bold">Period</div>
                                            <div class="small fw-semibold text-dark mt-1"><?php echo e($pkg['period_start']); ?></div>
                                        </div>
                                        <div class="col-4 border-start border-end">
                                            <div class="x-small text-muted text-uppercase fw-bold">Submissions</div>
                                            <div class="small fw-bold text-dark mt-1"><?php echo $summary['submitted']; ?> / <?php echo $summary['required']; ?> Members</div>
                                        </div>
                                        <div class="col-4">
                                            <div class="x-small text-muted text-uppercase fw-bold">Shared Behavior</div>
                                            <div class="small fw-bold text-success mt-1"><?php echo $pkg['shared_behavior_score'] !== null ? number_format((float)$pkg['shared_behavior_score'], 2) : 'Pending'; ?></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Route Steps Mini Pipeline -->
                                <div class="mb-3">
                                    <div class="x-small text-muted text-uppercase fw-bold mb-2"><i class="fas fa-route me-1"></i>Review Pipeline Timeline</div>
                                    <div class="package-route d-flex flex-wrap gap-1">
                                        <?php foreach ($steps as $st): ?>
                                            <?php
                                            $st_class = 'bg-secondary-subtle text-secondary';
                                            if ($st['action_status'] === 'Approved') $st_class = 'bg-success-subtle text-success border border-success-subtle';
                                            elseif ($st['action_status'] === 'Pending') $st_class = 'bg-warning-subtle text-dark border border-warning-subtle fw-bold';
                                            elseif ($st['action_status'] === 'Returned') $st_class = 'bg-danger-subtle text-danger border border-danger-subtle';
                                            ?>
                                            <span class="badge <?php echo $st_class; ?>" style="font-size:0.72rem; padding: 4px 8px;">
                                                <?php echo e($st['step_label']); ?> &middot; <?php echo e($st['action_status']); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="package-card-footer-row pt-2 border-top d-flex justify-content-between align-items-center">
                                <span class="x-small text-muted"><i class="fas fa-clock me-1"></i>Updated: <?php echo formatDate($pkg['updated_at']); ?></span>
                                <a href="<?php echo BASE_URL; ?>/staff/evaluation-history.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                    <i class="fas fa-eye me-1"></i>View History
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
    .staff-package-tracker {
        min-width: 0;
        overflow-x: hidden;
    }

    .staff-package-tracker .package-tracker-hero-copy {
        min-width: 0;
    }

    .staff-package-tracker .package-tracker-hero-copy h3,
    .staff-package-tracker .package-tracker-hero-copy p {
        overflow-wrap: anywhere;
    }

    .staff-package-tracker .package-tracker-filter {
        border-radius: 14px !important;
    }

    .staff-package-tracker .package-card-header-row,
    .staff-package-tracker .package-card-footer-row {
        min-width: 0;
    }

    .staff-package-tracker .package-card-title {
        min-width: 0;
        overflow-wrap: anywhere;
    }

    .staff-package-tracker .package-card-title h5 {
        overflow-wrap: anywhere;
    }

    .staff-package-tracker .package-card-status {
        flex-shrink: 1;
        max-width: 100%;
        text-align: right;
    }

    .staff-package-tracker .package-card-status .badge {
        display: inline-block;
        line-height: 1.25;
        max-width: 100%;
        overflow-wrap: anywhere;
        white-space: normal;
    }

    .staff-package-tracker .package-stat-strip {
        min-width: 0;
    }

    .staff-package-tracker .package-stat-strip > div {
        min-width: 0;
    }

    .staff-package-tracker .package-stat-strip .small {
        overflow-wrap: anywhere;
    }

    .staff-package-tracker .package-route {
        display: flex;
        flex-wrap: wrap;
        gap: .35rem;
    }

    .staff-package-tracker .package-route .badge {
        line-height: 1.3;
        white-space: normal;
    }

    @media (max-width: 767.98px) {
        .staff-package-tracker {
            padding: 1rem .75rem !important;
        }

        .staff-package-tracker .package-tracker-hero {
            border-radius: 12px !important;
            padding: 1.15rem !important;
        }

        .staff-package-tracker .package-tracker-hero-copy h3 {
            font-size: 1.25rem;
            line-height: 1.25;
        }

        .staff-package-tracker .package-tracker-hero-copy p {
            font-size: .8rem;
            line-height: 1.45;
        }

        .staff-package-tracker .package-tracker-access {
            align-self: flex-start;
            font-size: .7rem !important;
            white-space: normal;
        }

        .staff-package-tracker .package-tracker-metric {
            padding: .75rem !important;
        }

        .staff-package-tracker .package-tracker-metric .h3 {
            font-size: 1.35rem;
        }

        .staff-package-tracker .package-tracker-filter .card-body {
            padding: .85rem !important;
        }

        .staff-package-tracker .package-tracker-filter .btn {
            min-height: 42px;
        }

        .staff-package-tracker .package-card-header-row {
            align-items: flex-start !important;
            flex-direction: column;
        }

        .staff-package-tracker .package-card-status {
            text-align: left;
            width: 100%;
        }

        .staff-package-tracker .package-card-body {
            padding: 1rem !important;
        }

        .staff-package-tracker .package-stat-strip {
            margin-left: 0;
            margin-right: 0;
        }

        .staff-package-tracker .package-route .badge {
            flex: 1 1 100%;
        }

        .staff-package-tracker .package-card-footer-row {
            align-items: flex-start !important;
            flex-direction: column;
            gap: .75rem;
        }

        .staff-package-tracker .package-card-footer-row .btn {
            width: 100%;
        }
    }

    @media (max-width: 420px) {
        .staff-package-tracker {
            padding-left: .5rem !important;
            padding-right: .5rem !important;
        }

        .staff-package-tracker .package-tracker-metric .fa-2x {
            font-size: 1.35em;
        }

        .staff-package-tracker .package-stat-strip .col-4 {
            padding-left: .25rem;
            padding-right: .25rem;
        }

        .staff-package-tracker .package-stat-strip .x-small {
            font-size: .58rem;
        }
    }
</style>

<?php require_once '../includes/footer.php'; ?>
