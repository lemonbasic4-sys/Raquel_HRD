<?php
require_once __DIR__ . '/../includes/session-check.php';
checkRole(['HR Manager']);
require_once __DIR__ . '/../includes/annual-evaluation-page.php';
