<?php
// CLI-only check of session persistence across separate PHP requests.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../config/session.php';
$mode = $argv[1] ?? '';
$id = $argv[2] ?? '';
if (!in_array($mode, ['write', 'read'], true) || !preg_match('/^hris-check-[a-f0-9]{32}$/', $id)) {
    throw new RuntimeException('Expected write/read and a unique hris-check session ID');
}
session_id($id);
startAppSession();
startAppSession(); // Repeated calls must preserve the active session.
if (realpath(session_save_path()) !== realpath(__DIR__ . '/../storage/sessions')) {
    throw new RuntimeException('Wrong session storage directory');
}
if ($mode === 'write') {
    $_SESSION['session_storage_check'] = $id;
    if (!session_write_close()) {
        throw new RuntimeException('Session write failed');
    }
    echo "PASS session write and repeated startup\n";
} else {
    if (($_SESSION['session_storage_check'] ?? '') !== $id) {
        throw new RuntimeException('Session did not persist between PHP requests');
    }
    session_destroy();
    echo "PASS session read and cleanup\n";
}
