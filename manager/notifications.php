<?php
$page_title = 'Notifications';
require_once '../includes/session-check.php';
$notification_center_role = $notification_center_role ?? 'HR Manager';
checkRole($notification_center_role === 'Employee' ? ['Employee', 'President and CEO'] : [$notification_center_role]);
require_once '../includes/functions.php';
$notification_center_portal = ['HR Manager' => 'manager', 'HR Supervisor' => 'supervisor', 'HR Staff' => 'staff', 'Employee' => 'employee', 'Admin' => 'admin'][$notification_center_role] ?? 'manager';
$schema_ready = match ($notification_center_role) {
    'HR Supervisor' => ensureSupervisorNotificationSchema($conn),
    'HR Staff' => ensureStaffNotificationSchema($conn),
    'Employee' => ensureEmployeeNotificationSchema($conn),
    'Admin' => ensureAdminNotificationSchema($conn),
    default => ensureManagerNotificationSchema($conn),
};
if (!$schema_ready) {
    http_response_code(500);
    exit('Notification categories are unavailable.');
}
$user_id = (int) $_SESSION['user_id'];
$categories = $notification_center_role === 'Admin' ? [
    'all' => ['All Notifications', 'fas fa-inbox'],
    'security' => ['Security & Account Holds', 'fas fa-shield-alt'],
    'accounts' => ['Users & Access', 'fas fa-user-shield'],
    'portal' => ['Employee Portal Accounts', 'fas fa-id-card'],
    'employees' => ['Employee Records', 'fas fa-users'],
    'evaluations' => ['Evaluations & Governance', 'fas fa-clipboard-list'],
    'career' => ['Career Movements', 'fas fa-exchange-alt'],
    'backups' => ['Backups & Recovery', 'fas fa-database'],
    'settings' => ['System Settings', 'fas fa-cogs'],
    'audit' => ['Audit & Compliance', 'fas fa-history'],
    'system' => ['System & Announcements', 'fas fa-bullhorn'],
] : ($notification_center_role === 'Employee' ? [
    'all' => ['All Notifications', 'fas fa-inbox'],
    'probation' => ['Probationary Evaluation', 'fas fa-user-clock'],
    'approvals' => ['Reviews & Approvals', 'fas fa-clipboard-check'],
    'evaluations' => ['Evaluations', 'fas fa-clipboard-list'],
    'career' => ['Career Movements', 'fas fa-exchange-alt'],
    'profile' => ['My Profile & Account', 'fas fa-id-card'],
    'system' => ['System & Announcements', 'fas fa-bullhorn'],
] : ($notification_center_role === 'HR Staff' ? [
    'all' => ['All Notifications', 'fas fa-inbox'],
    'records' => ['Employee Record Requests', 'fas fa-user-edit'],
    'evaluations' => ['Evaluation Tracking', 'fas fa-clipboard-list'],
    'probation' => ['Probation Tracking', 'fas fa-user-clock'],
    'career' => ['Career Movements', 'fas fa-exchange-alt'],
    'templates' => ['Templates', 'fas fa-file-alt'],
    'system' => ['System & Announcements', 'fas fa-bullhorn'],
] : ($notification_center_role === 'HR Supervisor' ? [
    'all' => ['All Notifications', 'fas fa-inbox'],
    'validations' => ['Pending Validations', 'fas fa-clipboard-check'],
    'probation' => ['Probation & Regularization', 'fas fa-user-clock'],
    'evaluations' => ['Evaluation Updates', 'fas fa-clipboard-list'],
    'employees' => ['Employee Records', 'fas fa-users'],
    'career' => ['Career Movements', 'fas fa-exchange-alt'],
    'system' => ['System & Announcements', 'fas fa-bullhorn'],
] : [
    'all' => ['All Notifications', 'fas fa-inbox'],
    'approvals' => ['Pending Approvals', 'fas fa-check-circle'],
    'evaluations' => ['Evaluations', 'fas fa-clipboard-list'],
    'employees' => ['Employee Records', 'fas fa-users'],
    'probation' => ['Probation & Regularization', 'fas fa-user-clock'],
    'career' => ['Career Movements', 'fas fa-exchange-alt'],
    'accounts' => ['Accounts & Access', 'fas fa-user-shield'],
    'system' => ['System & Announcements', 'fas fa-bullhorn'],
])));
$category = (string) ($_GET['category'] ?? 'all');
if (!isset($categories[$category])) $category = 'all';
$filter = (string) ($_GET['filter'] ?? 'all');
if (!in_array($filter, ['all', 'unread', 'read', 'action'], true)) $filter = 'all';
$date_filter = (string) ($_GET['date'] ?? 'all');
if (!in_array($date_filter, ['all', 'today', '7', '30'], true)) $date_filter = 'all';
$search = mb_substr(trim((string) ($_GET['search'] ?? '')), 0, 120);
$page = max(1, (int) ($_GET['page'] ?? 1));
$selected_id = max(0, (int) ($_GET['id'] ?? 0));
$per_page = 10;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $is_ajax_selection = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
        && (string)($_POST['inbox_action'] ?? '') === 'open_or_select';
    $ajax_selection_succeeded = false;
    $inbox_action = (string)($_POST['inbox_action'] ?? '');
    $action_id = max(0, (int)($_POST['notification_id'] ?? 0));
    $next_id = $action_id;
    if (in_array($inbox_action, ['bulk_read', 'bulk_delete'], true)) {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['notification_ids'] ?? [])), static fn($id) => $id > 0)));
        $ids = array_slice($ids, 0, $per_page);
        if ($ids) {
            if ($inbox_action === 'bulk_delete') ensureNotificationReminderDeliverySchema($conn);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = $inbox_action === 'bulk_delete' ? 'DELETE FROM notifications' : 'UPDATE notifications SET is_read=1';
            $bulk = $conn->prepare($sql . " WHERE user_id=? AND notification_id IN ($placeholders)");
            $bulkParams = [$user_id, ...$ids];
            $bulk->bind_param(str_repeat('i', count($bulkParams)), ...$bulkParams);
            $bulk->execute();
            $bulk->close();
        }
        $next_id = 0;
    } elseif ($inbox_action === 'mark_all_read') {
        $mark_all = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
        $mark_all->bind_param('i', $user_id);
        $mark_all->execute();
        $mark_all->close();
        $next_id = 0;
    } elseif ($action_id > 0 && in_array($inbox_action, ['open_or_select', 'open_related', 'mark_read', 'mark_unread', 'delete'], true)) {
        $owned = $conn->prepare('SELECT link FROM notifications WHERE notification_id = ? AND user_id = ? LIMIT 1');
        $owned->bind_param('ii', $action_id, $user_id);
        $owned->execute();
        $owned_row = $owned->get_result()->fetch_assoc();
        $owned->close();
        if ($owned_row) {
            if ($inbox_action === 'delete') {
                ensureNotificationReminderDeliverySchema($conn);
                $update = $conn->prepare('DELETE FROM notifications WHERE notification_id = ? AND user_id = ?');
                $next_id = 0;
            } else {
                $read_value = $inbox_action === 'mark_unread' ? 0 : 1;
                $update = $conn->prepare('UPDATE notifications SET is_read = ? WHERE notification_id = ? AND user_id = ?');
                $update->bind_param('iii', $read_value, $action_id, $user_id);
            }
            if ($inbox_action === 'delete') $update->bind_param('ii', $action_id, $user_id);
            $update->execute();
            $update->close();
            if ($inbox_action === 'open_or_select' && $is_ajax_selection) {
                $ajax_selection_succeeded = true;
            }
            if (!$is_ajax_selection && ($inbox_action === 'open_related' || ($inbox_action === 'open_or_select' && !empty($_POST['already_selected'])))) {
                $destination = managerNotifLink($owned_row['link']);
                if ($destination) {
                    header('Location: ' . $destination);
                    exit;
                }
            }
        }
    }
    if ($is_ajax_selection) {
        $inbox_scope_sql = $notification_center_role === 'Employee'
            ? " AND (link LIKE '%/employee/%' OR link IS NULL OR link = '')"
            : " AND (link NOT LIKE '%/employee/%' OR link LIKE '%/employee/team-evaluation%' OR link LIKE '%/employee/package-member%' OR link IS NULL OR link = '')";
        $unread_stmt = $conn->prepare("SELECT category, COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0" . $inbox_scope_sql . notificationRouteRepairDuplicateFilterSql() . " GROUP BY category");
        $unread_stmt->bind_param('i', $user_id);
        $unread_stmt->execute();
        $unread_rows = $unread_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $unread_stmt->close();
        $unread_total = 0;
        $unread_categories = [];
        foreach ($unread_rows as $unread_row) {
            $unread_categories[(string)($unread_row['category'] ?? 'system')] = (int)$unread_row['total'];
            $unread_total += (int)$unread_row['total'];
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $ajax_selection_succeeded,
            'unread_count' => $unread_total,
            'unread_categories' => $unread_categories,
        ]);
        exit;
    }
    header('Location: ' . managerNotifUrl(['id' => $next_id ?: null]));
    exit;
}
$scope = $notification_center_role === 'Employee'
    ? "user_id = ? AND (link LIKE '%/employee/%' OR link IS NULL OR link = '')"
    : "user_id = ? AND (link NOT LIKE '%/employee/%' OR link LIKE '%/employee/team-evaluation%' OR link LIKE '%/employee/package-member%' OR link IS NULL OR link = '')";
$scope .= notificationRouteRepairDuplicateFilterSql();

$summary = $conn->prepare("SELECT category, COUNT(*) AS total, SUM(is_read = 0) AS unread FROM notifications WHERE $scope GROUP BY category");
$summary->bind_param('i', $user_id);
$summary->execute();
$counts = [];
$total_all = $total_unread = 0;
$summary_result = $summary->get_result();
while ($row = $summary_result->fetch_assoc()) {
    $key = isset($categories[$row['category'] ?? '']) ? $row['category'] : 'system';
    $counts[$key] = ($counts[$key] ?? 0) + (int) $row['unread'];
    $total_all += (int) $row['total'];
    $total_unread += (int) $row['unread'];
}
$summary->close();
$action_stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE $scope AND needs_action = 1 AND is_read = 0");
$action_stmt->bind_param('i', $user_id);
$action_stmt->execute();
$action_count = (int)$action_stmt->get_result()->fetch_row()[0];
$action_stmt->close();

$where = $scope;
$types = 'i';
$params = [$user_id];
if ($category !== 'all') { $where .= ' AND category = ?'; $types .= 's'; $params[] = $category; }
if ($filter === 'unread') $where .= ' AND is_read = 0';
if ($filter === 'read') $where .= ' AND is_read = 1';
if ($filter === 'action') $where .= ' AND needs_action = 1 AND is_read = 0';
if ($date_filter === 'today') $where .= ' AND created_at >= CURDATE()';
if ($date_filter === '7' || $date_filter === '30') $where .= ' AND created_at >= DATE_SUB(NOW(), INTERVAL ' . (int) $date_filter . ' DAY)';
if ($search !== '') { $where .= ' AND (title LIKE ? OR message LIKE ?)'; $types .= 'ss'; $params[] = '%' . $search . '%'; $params[] = '%' . $search . '%'; }
$count_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM notifications WHERE $where");
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_filtered = (int) $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();
$total_pages = max(1, (int) ceil($total_filtered / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;
$stmt = $conn->prepare("SELECT notification_id, title, message, link, category, needs_action, is_read, created_at FROM notifications WHERE $where ORDER BY created_at DESC, notification_id DESC LIMIT ? OFFSET ?");
$fetch_params = [...$params, $per_page, $offset];
$stmt->bind_param($types . 'ii', ...$fetch_params);
$stmt->execute();
$manager_notif_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$selected = null;
foreach ($manager_notif_items as $item) if ((int) $item['notification_id'] === $selected_id) $selected = $item;
if (!$selected && $selected_id > 0) {
    $selected_stmt = $conn->prepare("SELECT notification_id, title, message, link, category, needs_action, is_read, created_at FROM notifications WHERE $scope AND notification_id = ? LIMIT 1");
    $selected_stmt->bind_param('ii', $user_id, $selected_id);
    $selected_stmt->execute();
    $selected = $selected_stmt->get_result()->fetch_assoc() ?: null;
    $selected_stmt->close();
}
if (!$selected) $selected = $manager_notif_items[0] ?? null;
$selected_id = (int) ($selected['notification_id'] ?? 0);
$notificationGroups = [];
foreach ($manager_notif_items as $item) {
    $groupKey = ($item['category'] ?? '') === 'probation' ? $item['title'] : 'item-' . $item['notification_id'];
    $notificationGroups[$groupKey][] = $item;
}

function managerNotifUrl(array $overrides = []): string {
    $params = ['category' => $_GET['category'] ?? 'all', 'filter' => $_GET['filter'] ?? 'all', 'date' => $_GET['date'] ?? 'all', 'search' => $_GET['search'] ?? '', 'page' => $_GET['page'] ?? 1];
    foreach ($overrides as $key => $value) $params[$key] = $value;
    return BASE_URL . '/' . $GLOBALS['notification_center_portal'] . '/notifications.php?' . http_build_query($params);
}
function managerNotifLink($link): ?string {
    $link = trim((string) $link);
    if (!str_starts_with($link, BASE_URL . '/')) return null;
    if ($GLOBALS['notification_center_role'] === 'HR Supervisor') {
        $link = str_replace(BASE_URL . '/manager/evaluation-history.php', BASE_URL . '/supervisor/evaluation-history.php', $link);
    }
    return $link;
}
require_once '../includes/header.php';
require __DIR__ . '/../includes/admin-notifications-view.php';
require_once '../includes/footer.php';
