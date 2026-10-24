<?php
$page_title = 'System Configuration';
require_once '../includes/session-check.php';
checkRole(['Admin']);
require_once '../includes/functions.php';

// Handle Post Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    verifyCsrfToken();

    $settings_to_save = [
        'session_timeout'   => $_POST['session_timeout'],
        'pwd_min_length'    => $_POST['pwd_min_length'],
        'pwd_require_special' => isset($_POST['pwd_require_special']) ? '1' : '0',
        'pwd_require_number'  => isset($_POST['pwd_require_number'])  ? '1' : '0',
        'pwd_require_upper'   => isset($_POST['pwd_require_upper'])   ? '1' : '0',
    ];

    $conn->begin_transaction();
    try {
        foreach ($settings_to_save as $key => $value) {
            updateSetting($conn, $key, $value);
        }
        $conn->commit();
        logAudit($conn, $_SESSION['user_id'], 'UPDATE', 'Settings', 0, 'Updated security and authentication settings');
        notifyOtherAdmins($conn, (int) $_SESSION['user_id'], 'System Settings Updated',
            'An administrator changed security and authentication settings. Review the configuration and audit trail if needed.',
            BASE_URL . '/admin/config.php', 'settings');
        redirectWith(BASE_URL . '/admin/config.php', 'success', 'System settings updated successfully.');
    } catch (Exception $e) {
        $conn->rollback();
        redirectWith(BASE_URL . '/admin/config.php', 'danger', 'Failed to update settings: ' . $e->getMessage());
    }
}

require_once '../includes/header.php';

// Fetch all current settings
$settings_res = $conn->query("SELECT * FROM system_settings");
$current_settings = [];
while ($row = $settings_res->fetch_assoc()) {
    $current_settings[$row['setting_key']] = $row['setting_value'];
}
?>

<div class="config-module">
    <div class="page-hero fadeup">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-3">
            <div>
                <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">System Admin · Configuration</div>
                <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-sliders-h me-2" style="color:var(--primary-light);"></i>System Configuration</h4>
            </div>
            <div style="color:rgba(255,255,255,.6);font-size:.8rem;">
                <i class="fas fa-lock me-1"></i>Admin only
            </div>
        </div>
        <p class="text-white-50 small mb-0"><i class="fas fa-shield-alt me-1"></i>Manage system-wide security and authentication policies.</p>
    </div>

    <form method="POST" action="" class="fadeup-1">
        <?php echo csrfField(); ?>
        <div class="row g-4">
            <!-- Security Policies -->
            <div class="col-lg-8 mx-auto">
                <div class="content-card h-100">
                    <div class="card-header">
                        <h5><i class="fas fa-shield-alt me-2"></i>Security & Authentication</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <label class="form-label">Session Timeout (Minutes)</label>
                            <input type="number" class="form-control" name="session_timeout" 
                                   value="<?php echo e($current_settings['session_timeout'] ?? '240'); ?>" min="5" max="1440">
                            <div class="form-text">Idle time before automatic logout.</div>
                        </div>

                        <h6 class="fw-bold mb-3"><i class="fas fa-key me-2"></i>Password Complexity</h6>
                        <div class="mb-3">
                            <label class="form-label">Minimum Character Length</label>
                            <input type="number" class="form-control" name="pwd_min_length" 
                                   value="<?php echo e($current_settings['pwd_min_length'] ?? '8'); ?>" min="6" max="32">
                        </div>
                        
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="pwd_require_upper" id="reqUpper" 
                                   <?php echo ($current_settings['pwd_require_upper'] ?? '1') == '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="reqUpper">Require Uppercase Letters</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="pwd_require_number" id="reqNumber" 
                                   <?php echo ($current_settings['pwd_require_number'] ?? '1') == '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="reqNumber">Require Numbers</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="pwd_require_special" id="reqSpecial" 
                                   <?php echo ($current_settings['pwd_require_special'] ?? '1') == '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="reqSpecial">Require Special Characters (#?!@$%^&*)</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Save Actions -->
            <div class="col-12 mt-4 text-center">
                <button type="submit" name="save_settings" class="btn btn-primary btn-lg px-5">
                    <i class="fas fa-save me-2"></i>Save Security Configuration
                </button>
            </div>
        </div>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>
