<?php
/**
 * Silently checks whether a scheduled backup is due and runs it for Admin users.
 */

if (!isset($conn) || !isset($_SESSION['user_id'])) {
    return;
}

if (($_SESSION['role'] ?? '') !== 'Admin') {
    return;
}

require_once __DIR__ . '/backup-engine.php';
require_once __DIR__ . '/functions.php';

function ab_get_setting($conn, $key, $default = '') {
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    if (!$stmt) {
        return $default;
    }

    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? $row['setting_value'] : $default;
}

function ab_set_setting($conn, $key, $value) {
    $value = (string)$value;
    $stmt = $conn->prepare("
        INSERT INTO system_settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = ?
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('sss', $key, $value, $value);
    $stmt->execute();
    $stmt->close();
    return true;
}

function ab_normalize_time($time, $fallback_hour = 2) {
    $fallback = sprintf('%02d:00', max(0, min(23, (int)$fallback_hour)));
    return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string)$time) ? $time : $fallback;
}

function ab_next_run($frequency, $weekday, $monthday, $time) {
    [$hour, $minute] = array_map('intval', explode(':', $time));
    $now = new DateTime('now');
    $next = new DateTime('now');
    $next->setTime($hour, $minute, 0);

    if ($frequency === 'weekly') {
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $weekday = max(0, min(6, (int)$weekday));
        $today_run = new DateTime('now');
        $today_run->setTime($hour, $minute, 0);

        if ((int)$today_run->format('w') === $weekday && $today_run > $now) {
            return $today_run->format('Y-m-d H:i:s');
        }

        $next->modify("next {$days[$weekday]}");
        $next->setTime($hour, $minute, 0);
        return $next->format('Y-m-d H:i:s');
    }

    if ($frequency === 'monthly') {
        $monthday = max(1, min(28, (int)$monthday));
        $next->setDate((int)$now->format('Y'), (int)$now->format('n'), $monthday);
        $next->setTime($hour, $minute, 0);

        if ($next <= $now) {
            $next->modify('+1 month');
            $next->setDate((int)$next->format('Y'), (int)$next->format('n'), $monthday);
            $next->setTime($hour, $minute, 0);
        }

        return $next->format('Y-m-d H:i:s');
    }

    if ($next <= $now) {
        $next->modify('+1 day');
    }

    return $next->format('Y-m-d H:i:s');
}

function ab_run_due_backup($conn) {
    if ((int)ab_get_setting($conn, 'auto_backup_enabled', '0') !== 1) {
        return ['checked' => true, 'ran' => false, 'reason' => 'disabled'];
    }

    $next_run_str = ab_get_setting($conn, 'auto_backup_next_run', '');
    if ($next_run_str === '') {
        return ['checked' => true, 'ran' => false, 'reason' => 'not_scheduled'];
    }

    try {
        $now = new DateTime('now');
        $next_run = new DateTime($next_run_str);
    } catch (Exception $e) {
        return ['checked' => true, 'ran' => false, 'reason' => 'invalid_next_run'];
    }

    if ($now < $next_run) {
        return ['checked' => true, 'ran' => false, 'reason' => 'not_due', 'next_run' => $next_run_str];
    }

    $lock_key = 'ab_lock_' . date('YmdHi');
    if (!empty($_SESSION[$lock_key])) {
        return ['checked' => true, 'ran' => false, 'reason' => 'locked'];
    }
    $_SESSION[$lock_key] = true;

    $type = ab_get_setting($conn, 'auto_backup_type', 'full');
    $keep = (int)ab_get_setting($conn, 'auto_backup_keep', '7');
    $frequency = ab_get_setting($conn, 'auto_backup_frequency', 'daily');
    $weekday = (int)ab_get_setting($conn, 'auto_backup_weekday', '1');
    $monthday = (int)ab_get_setting($conn, 'auto_backup_monthday', '1');
    $hour = (int)ab_get_setting($conn, 'auto_backup_hour', '2');
    $time = ab_normalize_time(ab_get_setting($conn, 'auto_backup_time', sprintf('%02d:00', $hour)), $hour);

    $result = backup_create_database_snapshot($conn, $type);
    $next_run = ab_next_run($frequency, $weekday, $monthday, $time);

    ab_set_setting($conn, 'auto_backup_last_run', date('Y-m-d H:i:s'));
    ab_set_setting($conn, 'auto_backup_next_run', $next_run);

    if ($result['success']) {
        backup_cleanup_old_files($keep);
        logAudit($conn, $_SESSION['user_id'], 'CREATE', 'AutoBackup', 0,
            "Scheduled auto-backup ({$result['label']}): {$result['filename']}");
        notifyOtherAdmins($conn, 0, 'Scheduled backup completed',
            "Scheduled {$result['label']} backup completed: {$result['filename']}.", BASE_URL . '/admin/backup.php', 'backups');

        $_SESSION['auto_backup_toast'] = [
            'type' => 'success',
            'msg' => "Scheduled {$result['label']} auto-backup completed: <strong>{$result['filename']}</strong>",
        ];
    } else {
        notifyOtherAdmins($conn, 0, 'Backup failed',
            'A scheduled backup failed. Check backup settings and run a manual backup if needed.', BASE_URL . '/admin/backup.php', 'backups');
        $_SESSION['auto_backup_toast'] = [
            'type' => 'danger',
            'msg' => e($result['error'] ?? 'Scheduled auto-backup failed.'),
        ];
    }

    $result['checked'] = true;
    $result['ran'] = true;
    $result['next_run'] = $next_run;
    return $result;
}

$GLOBALS['auto_backup_check_result'] = ab_run_due_backup($conn);
?>
