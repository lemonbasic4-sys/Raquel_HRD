<?php
$page_title = 'Profile & Settings';
require_once '../includes/session-check.php';
checkRole(['Admin']);
require_once '../includes/profile-settings-shared.php';
