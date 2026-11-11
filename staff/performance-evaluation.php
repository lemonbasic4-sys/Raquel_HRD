<?php
require_once __DIR__ . '/../includes/session-check.php';
checkRole(['HR Staff']);
require_once __DIR__ . '/../includes/quarterly-evaluations.php';
require_once __DIR__ . '/../includes/performance-evaluation-priority.php';
if ($_SERVER['REQUEST_METHOD']==='GET' && !isset($_GET['category'])
    && !isset($_GET['evaluation_id']) && !isset($_GET['cycle_id']) && !isset($_GET['view'])) {
    $entryCategory=performanceEvaluationDefaultCategory($conn,(int)($_SESSION['user_id'] ?? 0),false);
    header('Location: '.BASE_URL.'/staff/performance-evaluation.php?category='.$entryCategory);
    exit;
}

if (($_GET['category'] ?? 'quarterly') === 'regular') {
    $annual_in_performance_page = true;
    require __DIR__ . '/../includes/annual-evaluation-page.php';
} else {
    $quarterly_in_performance_page = true;
    require __DIR__ . '/../includes/quarterly-evaluation-page.php';
}
