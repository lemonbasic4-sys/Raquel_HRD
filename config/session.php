<?php
/** Start sessions before output, using this application's private storage. */
function startAppSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $directory = dirname(__DIR__) . '/storage/sessions';
    if (ini_get('session.save_handler') === 'files') {
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            error_log('HRIS session directory could not be created: ' . $directory);
            http_response_code(503);
            exit('Session storage is unavailable. Please contact the administrator.');
        }
        if (!is_writable($directory)) {
            error_log('HRIS session directory is not writable: ' . $directory);
            http_response_code(503);
            exit('Session storage is unavailable. Please contact the administrator.');
        }
        session_save_path($directory);
    }

    if (!@session_start()) {
        $error = error_get_last();
        error_log('HRIS session startup failed: ' . ($error['message'] ?? 'unknown error'));
        http_response_code(503);
        exit('Unable to start a secure session. Please contact the administrator.');
    }
}
