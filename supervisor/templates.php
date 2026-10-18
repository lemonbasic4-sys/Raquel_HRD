<?php
// HR Supervisor template management shares the same implementation as the
// manager template library while keeping the role-specific /supervisor URL.
require_once __DIR__ . '/../includes/session-check.php';
checkRole(['HR Supervisor']);
require_once __DIR__ . '/../manager/templates.php';
