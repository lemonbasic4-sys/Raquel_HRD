<?php
require_once __DIR__ . '/../includes/session-check.php';
checkRole(['HR Staff']);
require_once __DIR__ . '/../includes/quarterly-evaluations.php';
if (($_GET['category'] ?? 'quarterly') === 'regular') {
    $annual_in_performance_page = true;
    require __DIR__ . '/../includes/annual-evaluation-page.php';
} else {
    $quarterly_in_performance_page = true;
    require __DIR__ . '/../includes/quarterly-evaluation-page.php';
}
