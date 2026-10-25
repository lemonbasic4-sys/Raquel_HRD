<?php
// Older links lead to the integrated HRD start panel.
require_once '../includes/session-check.php';
checkRole(['HR Manager']);
require_once '../includes/functions.php';
$actor=annualActor($conn,(int)($_SESSION['user_id'] ?? 0));
if (!$actor || !annualIsHrDepartment($actor)) { http_response_code(403); exit('This page is available only to the Human Resources manager.'); }
header('Location: '.BASE_URL.'/employee/team-evaluation-packages.php?category=regular&view=peers#annual-cycle-start');
exit;
