<?php
require_once __DIR__ . '/../includes/session-check.php';
checkRole(['HR Staff']);
require_once __DIR__ . '/../includes/annual-evaluation-page.php';
