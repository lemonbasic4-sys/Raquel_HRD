<?php
$page_title = 'System Configuration';
require_once '../includes/session-check.php';
checkRole(['Admin']);
require_once '../includes/functions.php';

// Handle Post Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    verifyCsrfToken();

    // --- Handle logo upload ---
    $logo_path = getSetting($conn, 'system_logo', 'assets/img/logo/logo.png'); // keep current by default

    if (isset($_FILES['system_logo_file']) && $_FILES['system_logo_file']['error'] === UPLOAD_ERR_OK) {
        $file     = $_FILES['system_logo_file'];
        $allowed  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        $ext_map  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'image/svg+xml' => 'svg'];

        // Validate MIME type from actual file content (not browser-supplied type)
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mime     = $finfo->file($file['tmp_name']);

        if (!in_array($mime, $allowed, true)) {
            redirectWith(BASE_URL . '/admin/config.php', 'danger', 'Invalid file type. Please upload a JPG, PNG, GIF, WEBP, or SVG image.');
            exit;
        }

        if ($file['size'] > 2 * 1024 * 1024) { // 2 MB cap
            redirectWith(BASE_URL . '/admin/config.php', 'danger', 'Logo file is too large. Maximum size is 2 MB.');
            exit;
        }

        $ext          = $ext_map[$mime];
        $new_filename = 'system_logo_' . time() . '.' . $ext;
        $upload_dir   = __DIR__ . '/../assets/img/logo/';
        $dest         = $upload_dir . $new_filename;

        if (move_uploaded_file($file['tmp_name'], $dest)) {
            // Delete old custom logo (but never delete the default logo.png)
            $old_logo = getSetting($conn, 'system_logo', '');
            if (!empty($old_logo) && $old_logo !== 'assets/img/logo/logo.png') {
                $old_path = __DIR__ . '/../' . $old_logo;
                if (file_exists($old_path)) {
                    @unlink($old_path);
                }
            }
            $logo_path = 'assets/img/logo/' . $new_filename;
        } else {
            redirectWith(BASE_URL . '/admin/config.php', 'danger', 'Failed to save the uploaded logo. Check folder permissions.');
            exit;
        }
    }

    $settings_to_save = [
        'company_name'      => $_POST['company_name'],
        'contact_email'     => $_POST['contact_email'],
        'session_timeout'   => $_POST['session_timeout'],
        'pwd_min_length'    => $_POST['pwd_min_length'],
        'pwd_require_special' => isset($_POST['pwd_require_special']) ? '1' : '0',
        'pwd_require_number'  => isset($_POST['pwd_require_number'])  ? '1' : '0',
        'pwd_require_upper'   => isset($_POST['pwd_require_upper'])   ? '1' : '0',
        'system_logo'       => $logo_path,
    ];

    $conn->begin_transaction();
    try {
        foreach ($settings_to_save as $key => $value) {
            updateSetting($conn, $key, $value);
        }
        $conn->commit();
        logAudit($conn, $_SESSION['user_id'], 'UPDATE', 'Settings', 0, 'Updated global system settings');
        notifyOtherAdmins($conn, (int) $_SESSION['user_id'], 'System Settings Updated',
            'An administrator changed global system settings. Review the configuration and audit trail if needed.',
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
        <p class="text-white-50 small mb-0"><i class="fas fa-cog me-1"></i>Manage global variables and security protocols.</p>
    </div>

    <form method="POST" action="" enctype="multipart/form-data" class="fadeup-1">
        <?php echo csrfField(); ?>
        <div class="row g-4">
            <!-- General Settings -->
            <div class="col-lg-6">
                <div class="content-card h-100">
                    <div class="card-header">
                        <h5><i class="fas fa-globe me-2"></i>General Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Company Name</label>
                            <input type="text" class="form-control" name="company_name" 
                                   value="<?php echo e($current_settings['company_name'] ?? ''); ?>" required>
                            <div class="form-text">Used across the system in headers and reports.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">System Support Email</label>
                            <input type="email" class="form-control" name="contact_email" 
                                   value="<?php echo e($current_settings['contact_email'] ?? ''); ?>" required>
                            <div class="form-text">Primary contact for technical issues.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">System Logo</label>
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <?php 
                                $logo_src = BASE_URL . '/' . (isset($current_settings['system_logo']) ? $current_settings['system_logo'] : 'assets/img/logo/logo.png');
                                ?>
                                <img src="<?php echo $logo_src; ?>" 
                                     alt="Logo" class="img-thumbnail" style="height: 80px; width: 80px; object-fit: contain; background: #fff;" id="logo-preview">
                                <div class="flex-grow-1">
                                    <input type="file" class="form-control" name="system_logo_file" id="logo-file-input" 
                                           accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml">
                                    <div class="form-text mt-1">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Upload JPG, PNG, GIF, WEBP, or SVG. Max size: 2 MB.
                                        <br>
                                        <small class="text-muted">Current: <?php echo e($current_settings['system_logo'] ?? 'assets/img/logo/logo.png'); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Security Policies -->
            <div class="col-lg-6">
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
                    <i class="fas fa-save me-2"></i>Save Global Configuration
                </button>
            </div>
        </div>
    </form>
</div>

<script>
// Logo upload preview
document.getElementById('logo-file-input').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        // Validate file size (2 MB)
        if (file.size > 2 * 1024 * 1024) {
            alert('File is too large. Maximum size is 2 MB.');
            e.target.value = '';
            return;
        }

        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        if (!allowedTypes.includes(file.type)) {
            alert('Invalid file type. Please upload a JPG, PNG, GIF, WEBP, or SVG image.');
            e.target.value = '';
            return;
        }

        // Preview the image
        const reader = new FileReader();
        reader.onload = function(event) {
            document.getElementById('logo-preview').src = event.target.result;
        };
        reader.readAsDataURL(file);
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
