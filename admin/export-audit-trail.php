<?php
ob_start();
require_once '../includes/session-check.php';
checkRole(['Admin']);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method not allowed.');
}
require_once '../includes/functions.php';
verifyCsrfToken();
$bootstrap_output = ob_get_clean();
if (trim($bootstrap_output) !== '') {
    error_log('Unexpected output during audit export initialization.');
    http_response_code(500);
    exit('Unable to prepare the audit export.');
}

define('AUDIT_TRAIL_EXPORT_ONLY', true);
require '../includes/audit-trail-monitor.php';
