<?php
$page_title = 'System Activity Monitor';
require_once '../includes/session-check.php';
checkRole(['Admin']);
require_once '../includes/functions.php';
require_once '../includes/header.php';
?>
<div class="admin-audit-trail-page">
<?php
require_once '../includes/audit-trail-monitor.php';
?>
</div>
<?php
require_once '../includes/footer.php';
