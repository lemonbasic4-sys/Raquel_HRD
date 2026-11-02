<?php
require_once __DIR__ . '/../includes/session-check.php';
checkRole(['HR Staff']);
require_once __DIR__ . '/../includes/quarterly-evaluations.php';
require_once __DIR__ . '/../includes/quarterly-evaluation-page.php';
