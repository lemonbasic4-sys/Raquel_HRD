<?php
require_once '../includes/session-check.php';
checkRole(['Employee']);
require_once '../includes/functions.php';

// Keep older notification links working by opening the evaluation details.
$evaluation_id = (int)($_GET['id'] ?? $_GET['evaluation_id'] ?? $_POST['evaluation_id'] ?? 0);
if ($evaluation_id <= 0) {
    redirectWith(BASE_URL . '/employee/evaluation-history.php', 'danger', 'Choose an evaluation to view.');
}

header('Location: ' . BASE_URL . '/employee/evaluation-history-view.php?evaluation_id=' . $evaluation_id);
exit;
