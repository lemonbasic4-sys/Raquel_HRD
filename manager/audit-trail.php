<?php
$page_title = 'Audit Trail';
require_once '../includes/session-check.php';
checkRole(['HR Manager']);
require_once '../includes/functions.php';
require_once '../includes/header.php';
?>
<div class="audit-trail-page admin-audit-trail-page">
<?php
require_once '../includes/audit-trail-monitor.php';
?>
</div>
<?php
require_once '../includes/footer.php';
