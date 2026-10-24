<?php
$page_title = 'System Backup';
require_once '../includes/session-check.php';
checkRole(['Admin']);
require_once '../includes/functions.php';

function formatSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    for ($i = 0; $bytes > 1024; $i++) $bytes /= 1024;
    return round($bytes, 2) . ' ' . $units[$i];
}

// Handle Download
if (isset($_GET['download'])) {
    $file = basename($_GET['download']);
    $path = dirname(__DIR__) . '/backups/' . $file;
    if (file_exists($path)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}

// Handle deletion through a CSRF-protected POST request.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_backup') {
    verifyCsrfToken();
    $requested_file = (string) ($_POST['filename'] ?? '');
    $file = basename($requested_file);
    $backup_root = realpath(dirname(__DIR__) . '/backups');
    $path = $backup_root ? realpath($backup_root . DIRECTORY_SEPARATOR . $file) : false;
    $valid_path = $file === $requested_file
        && strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'sql'
        && $path !== false
        && dirname($path) === $backup_root;
    if ($valid_path && is_file($path) && unlink($path)) {
        logAudit($conn, $_SESSION['user_id'], 'DELETE', 'Backup', 0, 'Deleted backup file: ' . $file);
        notifyOtherAdmins($conn, (int) $_SESSION['user_id'], 'Backup Deleted',
            "An administrator deleted backup {$file}.", BASE_URL . '/admin/backup.php', 'backups');
        redirectWith(BASE_URL . '/admin/backup.php', 'success', 'Backup deleted successfully.');
    }
    redirectWith(BASE_URL . '/admin/backup.php', 'danger', 'Backup could not be deleted.');
}

require_once '../includes/header.php';

// Get all backups
$backup_dir = dirname(__DIR__) . '/backups/';
if (!is_dir($backup_dir)) {
    @mkdir($backup_dir, 0777, true);
}
$files = is_dir($backup_dir) ? @scandir($backup_dir) : false;
$files = is_array($files) ? array_diff($files, array('.', '..', '.htaccess')) : [];
$backups = [];
foreach ($files as $file) {
    if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
        if (strpos($file, '_schema_') !== false) {
            $btype = 'schema';
        } elseif (strpos($file, '_data_') !== false) {
            $btype = 'data';
        } else {
            $btype = 'full';
        }
        $backups[] = [
            'name' => $file,
            'size' => filesize($backup_dir . $file),
            'date' => filemtime($backup_dir . $file),
            'type' => $btype,
        ];
    }
}
usort($backups, function($a, $b) { return $b['date'] - $a['date']; });

// Load auto-backup schedule settings
$ab_enabled  = (int)getSetting($conn, 'auto_backup_enabled',   '0');
$ab_freq     = getSetting($conn, 'auto_backup_frequency', 'daily');
$ab_weekday  = (int)getSetting($conn, 'auto_backup_weekday',  '1');
$ab_monthday = (int)getSetting($conn, 'auto_backup_monthday', '1');
$ab_hour     = (int)getSetting($conn, 'auto_backup_hour',     '2');
$ab_time     = getSetting($conn, 'auto_backup_time', sprintf('%02d:00', $ab_hour));
$ab_time     = preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $ab_time) ? $ab_time : sprintf('%02d:00', $ab_hour);
$ab_type     = getSetting($conn, 'auto_backup_type',   'full');
$ab_keep     = (int)getSetting($conn, 'auto_backup_keep',     '7');
$ab_last_run = getSetting($conn, 'auto_backup_last_run', '');
$ab_next_run = getSetting($conn, 'auto_backup_next_run', '');

$days_of_week = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
?>

<div class="backup-module">
    <div class="page-hero fadeup">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-3">
            <div>
                <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">System Admin · Recovery Center</div>
                <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-database me-2" style="color:var(--primary-light);"></i>System Backup &amp; Recovery</h4>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-light text-primary fw-semibold" id="btnFullBackup" title="Full: Table structures + all data">
                    <i class="fas fa-database me-2"></i>Full Backup
                </button>
                <button class="btn btn-outline-light fw-semibold" id="btnSchemaBackup" title="Schema: Table structures only (no data)">
                    <i class="fas fa-sitemap me-2"></i>Schema Only
                </button>
                <button class="btn btn-outline-light fw-semibold" id="btnDataBackup" title="Data: Records/values only (no table structures)">
                    <i class="fas fa-table me-2"></i>Data Only
                </button>
            </div>
        </div>
        <p class="text-white-50 small mb-0"><i class="fas fa-shield-alt me-1"></i>Manage database snapshots and system exports.</p>
    </div>

    <!-- Stats Info -->
    <div class="row g-3 mb-4 fadeup-1">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-file-archive"></i></div>
                <div class="stat-info">
                    <h3><?php echo count($backups); ?></h3>
                    <p>Stored Backups</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon gold"><i class="fas fa-hdd"></i></div>
                <div class="stat-info">
                    <?php $total_size = array_sum(array_column($backups, 'size')); ?>
                    <h3><?php echo formatSize($total_size); ?></h3>
                    <p>Total Backup Size</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon <?php echo $ab_enabled ? 'green' : 'red'; ?>">
                    <i class="fas fa-<?php echo $ab_enabled ? 'robot' : 'clock'; ?>"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $ab_enabled ? ucfirst($ab_freq) : 'Off'; ?></h3>
                    <p>Auto-Backup Schedule</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Auto-Backup Scheduler Card ─────────────────────────────────────── -->
    <div class="content-card fadeup-1 mb-4" id="autoBackupCard">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5><i class="fas fa-robot me-2" style="color:var(--primary-dark);"></i>Automatic Backup Scheduler</h5>
            <div class="d-flex align-items-center gap-3">
                <span class="badge <?php echo $ab_enabled ? 'bg-success' : 'bg-secondary'; ?>" id="ab-status-badge">
                    <?php echo $ab_enabled ? 'Enabled' : 'Disabled'; ?>
                </span>
                <div class="form-check form-switch mb-0" title="Enable / disable automatic backups">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="abEnabledToggle" <?php echo $ab_enabled ? 'checked' : ''; ?>>
                </div>
            </div>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                <i class="fas fa-info-circle me-1"></i>
                Schedule automatic database backups. The backup runs when an admin page is opened after the scheduled time, and this page checks every minute while it stays open.
            </p>

            <div class="row g-3" id="abScheduleForm">
                <!-- Frequency -->
                <div class="col-sm-6 col-md-3">
                    <label class="form-label fw-semibold" for="abFrequency"><i class="fas fa-calendar-alt me-1"></i>Frequency</label>
                    <select class="form-select" id="abFrequency">
                        <option value="daily"   <?php echo $ab_freq==='daily'  ?'selected':''; ?>>Daily</option>
                        <option value="weekly"  <?php echo $ab_freq==='weekly' ?'selected':''; ?>>Weekly</option>
                        <option value="monthly" <?php echo $ab_freq==='monthly'?'selected':''; ?>>Monthly</option>
                    </select>
                </div>

                <!-- Weekday (weekly only) -->
                <div class="col-sm-6 col-md-2" id="abWeekdayGroup" <?php echo $ab_freq!=='weekly'?'style="display:none"':''; ?>>
                    <label class="form-label fw-semibold" for="abWeekday"><i class="fas fa-calendar-week me-1"></i>On Day</label>
                    <select class="form-select" id="abWeekday">
                        <?php foreach ($days_of_week as $i => $d): ?>
                            <option value="<?php echo $i; ?>" <?php echo $ab_weekday===$i?'selected':''; ?>><?php echo $d; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Month day (monthly only) -->
                <div class="col-sm-6 col-md-2" id="abMonthdayGroup" <?php echo $ab_freq!=='monthly'?'style="display:none"':''; ?>>
                    <label class="form-label fw-semibold" for="abMonthday"><i class="fas fa-calendar-day me-1"></i>Day of Month</label>
                    <select class="form-select" id="abMonthday">
                        <?php for ($d=1; $d<=28; $d++): ?>
                            <option value="<?php echo $d; ?>" <?php echo $ab_monthday===$d?'selected':''; ?>><?php echo $d; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <!-- Specific time -->
                <div class="col-sm-6 col-md-2">
                    <label class="form-label fw-semibold" for="abTime"><i class="fas fa-clock me-1"></i>Time</label>
                    <input type="time" class="form-control" id="abTime" value="<?php echo e($ab_time); ?>" step="60">
                    <div class="form-text">Choose the exact daily time.</div>
                </div>

                <!-- Backup type -->
                <div class="col-sm-6 col-md-2">
                    <label class="form-label fw-semibold" for="abType"><i class="fas fa-database me-1"></i>Backup Type</label>
                    <select class="form-select" id="abType">
                        <option value="full"   <?php echo $ab_type==='full'  ?'selected':''; ?>>Full Backup</option>
                        <option value="schema" <?php echo $ab_type==='schema'?'selected':''; ?>>Schema Only</option>
                        <option value="data"   <?php echo $ab_type==='data'  ?'selected':''; ?>>Data Only</option>
                    </select>
                </div>

                <!-- Keep last N -->
                <div class="col-sm-6 col-md-2">
                    <label class="form-label fw-semibold" for="abKeep"><i class="fas fa-archive me-1"></i>Keep Last</label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="abKeep" min="1" max="90" value="<?php echo $ab_keep; ?>">
                        <span class="input-group-text">files</span>
                    </div>
                </div>

                <!-- Info row + Save button -->
                <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2 mt-1">
                    <div class="small text-muted">
                        <?php if ($ab_last_run): ?>
                            <i class="fas fa-history me-1"></i>Last run: <strong><?php echo formatDateTime($ab_last_run); ?></strong>
                        <?php else: ?>
                            <i class="fas fa-history me-1"></i>No automatic backup has run yet.
                        <?php endif; ?>
                        &nbsp;|&nbsp;
                        <?php if ($ab_next_run && $ab_enabled): ?>
                            <i class="fas fa-forward me-1 text-success"></i>Next: <strong class="text-success"><?php echo formatDateTime($ab_next_run); ?></strong>
                        <?php else: ?>
                            <i class="fas fa-forward me-1 text-muted"></i>Next: <strong class="text-muted">Not scheduled</strong>
                        <?php endif; ?>
                    </div>
                    <button class="btn btn-primary fw-semibold px-4" id="btnSaveSchedule">
                        <i class="fas fa-save me-2"></i>Save Schedule
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Backup List -->
    <div class="content-card fadeup-2">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5><i class="fas fa-history me-2"></i>Backup History</h5>
            <span class="badge <?php echo $ab_enabled ? 'bg-success' : 'bg-secondary'; ?>">
                <i class="fas fa-<?php echo $ab_enabled ? 'robot' : 'times-circle'; ?> me-1"></i>
                Auto-cleanup: <?php echo $ab_enabled ? "Keep last {$ab_keep}" : 'Not Active'; ?>
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="backupTable">
                    <thead class="table-light">
                        <tr>
                            <th>Backup Filename</th>
                            <th>Type</th>
                            <th>Created Date</th>
                            <th>File Size</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($backups)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    <i class="fas fa-box-open fa-3x mb-3 d-block opacity-25"></i>
                                    No backups found. Use the buttons above to create a backup.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($backups as $b):
                                if ($b['type'] === 'schema') {
                                    $type_badge = '<span class="badge" style="background:#6366f1"><i class="fas fa-sitemap me-1"></i>Schema Only</span>';
                                    $file_icon  = 'fas fa-file-alt';
                                    $icon_color = 'color:#6366f1';
                                } elseif ($b['type'] === 'data') {
                                    $type_badge = '<span class="badge" style="background:#0891b2"><i class="fas fa-table me-1"></i>Data Only</span>';
                                    $file_icon  = 'fas fa-file-alt';
                                    $icon_color = 'color:#0891b2';
                                } else {
                                    $type_badge = '<span class="badge bg-success"><i class="fas fa-database me-1"></i>Full Backup</span>';
                                    $file_icon  = 'fas fa-file-code';
                                    $icon_color = 'color:#16a34a';
                                }
                            ?>
                                <tr>
                                    <td><strong><i class="<?php echo $file_icon; ?> me-2" style="<?php echo $icon_color; ?>"></i><?php echo e($b['name']); ?></strong></td>
                                    <td><?php echo $type_badge; ?></td>
                                    <td><?php echo formatDateTime(date('Y-m-d H:i:s', $b['date'])); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo formatSize($b['size']); ?></span></td>
                                    <td class="text-end">
                                        <div class="btn-group">
                                            <a href="?download=<?php echo urlencode($b['name']); ?>" class="btn btn-sm btn-outline-primary" title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <button class="btn btn-sm btn-outline-danger"
                                                    onclick="confirmDeleteBackup('<?php echo e($b['name']); ?>')" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<form method="post" id="deleteBackupForm" class="d-none">
    <?php echo csrfField(); ?>
    <input type="hidden" name="action" value="delete_backup">
    <input type="hidden" name="filename" id="deleteBackupFilename" value="">
</form>

<!-- Progress Modal -->
<div class="modal fade" id="backupProgressModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-5">
                <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status"></div>
                <h4 class="fw-bold" id="backupProgressLabel">Generating Backup...</h4>
                <p class="text-muted mb-0" id="backupProgressSub">Please wait while the system exports the database. This may take a few seconds.</p>
            </div>
        </div>
    </div>
</div>

<script>
const AJAX_URL     = '<?php echo BASE_URL; ?>/includes/ajax/system-backup.php';
const SCHEDULE_URL = '<?php echo BASE_URL; ?>/includes/ajax/save-backup-schedule.php';
const SCHEDULE_RUNNER_URL = '<?php echo BASE_URL; ?>/includes/ajax/run-scheduled-backup.php';
const CSRF_TOKEN   = '<?php echo generateCsrfToken(); ?>';

// ── Manual backup ────────────────────────────────────────────────────────────
function runBackup(type, labelText) {
    document.getElementById('backupProgressLabel').textContent = 'Generating ' + labelText + '...';
    document.getElementById('backupProgressSub').textContent  = 'Please wait while the system exports the database. This may take a few seconds.';
    const modal = new bootstrap.Modal(document.getElementById('backupProgressModal'));
    modal.show();
    const body = new FormData();
    body.append('type', type);
    body.append('csrf_token', CSRF_TOKEN);
    fetch(AJAX_URL, { method: 'POST', body })
        .then(r => r.json())
        .then(data => {
            modal.hide();
            if (data.success) { location.reload(); }
            else { alert('Backup Error: ' + (data.error || 'Unknown error occurred.')); }
        })
        .catch(err => { modal.hide(); alert('A system error occurred while generating the backup.'); console.error(err); });
}

document.getElementById('btnFullBackup').addEventListener('click',   () => runBackup('full',   'Full Backup'));
document.getElementById('btnSchemaBackup').addEventListener('click', () => runBackup('schema', 'Schema Only Backup'));
document.getElementById('btnDataBackup').addEventListener('click',   () => runBackup('data',   'Data Only Backup'));

function confirmDeleteBackup(filename) {
    if (confirm(`Are you sure you want to delete backup "${filename}"? This cannot be undone.`)) {
        document.getElementById('deleteBackupFilename').value = filename;
        document.getElementById('deleteBackupForm').submit();
    }
}

// ── Schedule UI ──────────────────────────────────────────────────────────────
const freqSel     = document.getElementById('abFrequency');
const weekdayGrp  = document.getElementById('abWeekdayGroup');
const monthdayGrp = document.getElementById('abMonthdayGroup');
const toggle      = document.getElementById('abEnabledToggle');
const statusBadge = document.getElementById('ab-status-badge');

// Keep the scheduler awake while this Admin page remains open.
function runScheduledBackupCheck() {
    const body = new FormData();
    body.append('csrf_token', CSRF_TOKEN);
    fetch(SCHEDULE_RUNNER_URL, { method: 'POST', body }).catch(() => {
        // A later interval can retry; no user action is needed for this check.
    });
}
runScheduledBackupCheck();
setInterval(runScheduledBackupCheck, 60000);

function updateFreqVisibility() {
    const f = freqSel.value;
    weekdayGrp.style.display  = f === 'weekly'  ? '' : 'none';
    monthdayGrp.style.display = f === 'monthly' ? '' : 'none';
}
freqSel.addEventListener('change', updateFreqVisibility);

toggle.addEventListener('change', function() {
    statusBadge.textContent = this.checked ? 'Enabled' : 'Disabled';
    statusBadge.className   = 'badge ' + (this.checked ? 'bg-success' : 'bg-secondary');
});

// ── Save schedule ────────────────────────────────────────────────────────────
document.getElementById('btnSaveSchedule').addEventListener('click', function() {
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';

    const body = new FormData();
    body.append('csrf_token', CSRF_TOKEN);
    body.append('enabled',  toggle.checked ? '1' : '0');
    body.append('frequency', freqSel.value);
    body.append('weekday',   document.getElementById('abWeekday').value);
    body.append('monthday',  document.getElementById('abMonthday').value);
    body.append('time',      document.getElementById('abTime').value);
    body.append('btype',     document.getElementById('abType').value);
    body.append('keep',      document.getElementById('abKeep').value);

    fetch(SCHEDULE_URL, { method: 'POST', body })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save me-2"></i>Save Schedule';
            if (data.success) {
                const toast = document.createElement('div');
                toast.className = 'alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm';
                toast.style.cssText = 'position:fixed;top:80px;right:20px;z-index:9999;max-width:420px;border-radius:12px;';
                toast.innerHTML = `<i class="fas fa-check-circle fa-lg"></i>
                    <div><strong>Schedule Saved!</strong><br>
                    ${data.enabled ? 'Auto-backup <strong>enabled</strong>. Next run: <strong>' + (data.next_run || 'N/A') + '</strong>' : 'Auto-backup is <strong>disabled</strong>.'}
                    </div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>`;
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 7000);
                setTimeout(() => location.reload(), 1400);
            } else {
                alert('Error saving schedule: ' + (data.error || 'Unknown error.'));
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save me-2"></i>Save Schedule';
            alert('A system error occurred while saving the schedule.');
            console.error(err);
        });
});
</script>

<?php
require_once '../includes/footer.php';
?>
