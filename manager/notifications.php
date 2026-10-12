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
$per_page = 20;
$scope = $notification_center_role === 'Employee'
    ? "user_id = ? AND (link LIKE '%/employee/%' OR link IS NULL OR link = '')"
    : "user_id = ? AND (link NOT LIKE '%/employee/%' OR link LIKE '%/employee/team-evaluation%' OR link LIKE '%/employee/package-member%' OR link IS NULL OR link = '')";

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

function managerNotifUrl(array $overrides = []): string {
    $params = ['category' => $_GET['category'] ?? 'all', 'filter' => $_GET['filter'] ?? 'all', 'date' => $_GET['date'] ?? 'all', 'search' => $_GET['search'] ?? '', 'page' => $_GET['page'] ?? 1];
    foreach ($overrides as $key => $value) $params[$key] = $value;
    return BASE_URL . '/' . $GLOBALS['notification_center_portal'] . '/notifications.php?' . http_build_query($params);
}
function managerNotifLink($link): ?string {
    $link = trim((string) $link);
    return str_starts_with($link, BASE_URL . '/') ? $link : null;
}
require_once '../includes/header.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/manager-notifications.css">
<div class="page-hero mn-hero">
    <div><span class="mn-eyebrow"><?php echo e($notification_center_role); ?> · Inbox</span><h1><i class="fas fa-bell"></i> Notifications</h1><p>Stay updated with important activities, reviews, and system updates.</p></div>
    <div class="mn-hero-count"><strong><?php echo $total_unread; ?></strong><span>unread</span></div>
</div>
<div class="mn-layout">
    <aside class="mn-categories" aria-label="Notification categories">
        <h2>Categories</h2><nav>
        <?php foreach ($categories as $key => $meta): $unread = $key === 'all' ? $total_unread : ($counts[$key] ?? 0); ?>
            <a class="mn-category <?php echo $category === $key ? 'active' : ''; ?>" href="<?php echo e(managerNotifUrl(['category' => $key, 'page' => 1, 'id' => null])); ?>"><i class="<?php echo $meta[1]; ?>"></i><span><?php echo e($meta[0]); ?></span><b><?php echo (int) $unread; ?></b></a>
        <?php endforeach; ?>
        </nav><p class="mn-count-note">Counts show unread notifications.</p>
    </aside>
    <section class="mn-list-panel" aria-label="Notification list">
        <form class="mn-search-row" method="get" action="<?php echo BASE_URL . '/' . $notification_center_portal; ?>/notifications.php">
            <input type="hidden" name="category" value="<?php echo e($category); ?>"><input type="hidden" name="filter" value="<?php echo e($filter); ?>">
            <label class="mn-search"><i class="fas fa-search"></i><input name="search" value="<?php echo e($search); ?>" placeholder="Search notifications" aria-label="Search notifications"></label>
            <select name="date" aria-label="Date range" onchange="this.form.submit()"><option value="all" <?php echo $date_filter === 'all' ? 'selected' : ''; ?>>All time</option><option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option><option value="7" <?php echo $date_filter === '7' ? 'selected' : ''; ?>>Last 7 days</option><option value="30" <?php echo $date_filter === '30' ? 'selected' : ''; ?>>Last 30 days</option></select>
            <button type="submit" class="mn-search-submit" aria-label="Search"><i class="fas fa-arrow-right"></i></button>
        </form>
        <div class="mn-toolbar"><nav class="mn-tabs" aria-label="Read status">
            <?php foreach (['all' => 'All', 'unread' => 'Unread', 'read' => 'Read', 'action' => in_array($notification_center_role, ['HR Staff', 'Employee'], true) ? 'My Follow-Ups' : 'Needs My Action'] as $key => $label): ?><a class="<?php echo $filter === $key ? 'active' : ''; ?>" href="<?php echo e(managerNotifUrl(['filter' => $key, 'page' => 1, 'id' => null])); ?>"><?php echo $label; ?></a><?php endforeach; ?>
        </nav><?php if ($total_unread > 0): ?><button type="button" class="mn-mark-all" onclick="managerNotifAction('mark_all_read')"><i class="fas fa-check-double"></i> Mark all as read</button><?php endif; ?></div>
        <?php if ($filter === 'action'): ?><p class="mn-action-note"><?php if ($notification_center_role === 'Admin'): ?>Unread security and access notices requiring administrative review. <a href="<?php echo BASE_URL; ?>/admin/employee-accounts.php">Review employee accounts</a>.<?php elseif ($notification_center_role === 'Employee'): ?>Unread self-rating reminders and returned evaluations needing your attention.<?php elseif ($notification_center_role === 'HR Staff'): ?>Unread returned work and approaching deadlines assigned to you. Open an item for its related page.<?php else: ?>Unread requests for your attention. <a href="<?php echo BASE_URL . '/' . $notification_center_portal . '/' . ($notification_center_role === 'HR Supervisor' ? 'pending-endorsements.php' : 'pending-approvals.php'); ?>">Check the current review queue</a> for open decisions.<?php endif; ?></p><?php endif; ?>
        <div class="mn-results-label"><?php echo $total_filtered; ?> notification<?php echo $total_filtered === 1 ? '' : 's'; ?></div>
        <?php if (!$manager_notif_items): ?><div class="mn-empty"><i class="far fa-bell-slash"></i><strong>No notifications found</strong><p>Try another category, date range, or search.</p></div>
        <?php else: ?><div class="mn-list">
            <?php foreach ($manager_notif_items as $item): $id = (int) $item['notification_id']; $meta = $categories[$item['category'] ?? 'system'] ?? $categories['system']; ?>
            <a href="<?php echo e(managerNotifUrl(['id' => $id])); ?>" class="mn-item <?php echo $selected_id === $id ? 'selected' : ''; ?> <?php echo $item['is_read'] ? '' : 'unread'; ?>"><span class="mn-item-icon"><i class="<?php echo $meta[1]; ?>"></i></span><span class="mn-item-content"><strong><?php echo e($item['title']); ?></strong><small><?php echo e($meta[0]); ?></small><span><?php echo e($item['message']); ?></span></span><span class="mn-item-meta"><time><?php echo e(timeAgoFormat($item['created_at'])); ?></time><?php if (!$item['is_read']): ?><i class="mn-unread-dot" aria-label="Unread"></i><?php endif; ?></span></a>
            <?php endforeach; ?>
        </div><?php if ($total_pages > 1): ?><nav class="mn-pages" aria-label="Notification pages"><?php if ($page > 1): ?><a href="<?php echo e(managerNotifUrl(['page' => $page - 1, 'id' => null])); ?>">Previous</a><?php endif; ?><span>Page <?php echo $page; ?> of <?php echo $total_pages; ?></span><?php if ($page < $total_pages): ?><a href="<?php echo e(managerNotifUrl(['page' => $page + 1, 'id' => null])); ?>">Next</a><?php endif; ?></nav><?php endif; ?><?php endif; ?>
    </section>
    <aside class="mn-detail" aria-label="Selected notification">
        <?php if ($selected): $meta = $categories[$selected['category'] ?? 'system'] ?? $categories['system']; $target = managerNotifLink($selected['link']); ?>
        <div class="mn-detail-head"><span class="mn-detail-icon"><i class="<?php echo $meta[1]; ?>"></i></span><div><h2><?php echo e($selected['title']); ?></h2><time><?php echo e(date('M j, Y · g:i A', strtotime($selected['created_at']))); ?></time></div><button type="button" class="mn-close" onclick="managerNotifAction('delete', <?php echo $selected_id; ?>)" title="Delete notification" aria-label="Delete notification">&times;</button></div>
        <div class="mn-detail-labels"><span><?php echo e($meta[0]); ?></span><span class="<?php echo $selected['is_read'] ? 'read' : 'unread'; ?>"><?php echo $selected['is_read'] ? 'Read' : 'Unread'; ?></span></div>
        <div class="mn-detail-message"><?php echo nl2br(e($selected['message'])); ?></div>
        <?php if ($target): ?><a class="mn-primary-action" href="<?php echo e($target); ?>"><i class="fas fa-external-link-alt"></i> Open related page</a><?php endif; ?>
        <button type="button" class="mn-secondary-action" onclick="managerNotifAction('<?php echo $selected['is_read'] ? 'mark_unread' : 'mark_read'; ?>', <?php echo $selected_id; ?>)"><i class="fas fa-<?php echo $selected['is_read'] ? 'envelope' : 'check'; ?>"></i> Mark as <?php echo $selected['is_read'] ? 'unread' : 'read'; ?></button>
        <?php else: ?><div class="mn-detail-empty"><i class="far fa-envelope-open"></i><strong>Select a notification</strong><p>Its full message and related action will appear here.</p></div><?php endif; ?>
    </aside>
</div>
<script>
async function managerNotifAction(action, id = 0) {
    if (action === 'delete' && !confirm('Delete this notification?')) return;
    if (action === 'mark_all_read' && !confirm('Mark all notifications as read?')) return;
    const data = new FormData();
    data.append('action', action); data.append('notification_id', id); data.append('context', <?php echo json_encode($notification_center_role === 'Employee' ? 'employee' : 'hr'); ?>);
    data.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
    try {
        const response = await fetch('<?php echo BASE_URL; ?>/includes/ajax/notification-action.php', {method: 'POST', body: data});
        const result = await response.json();
        if (!result.success) throw new Error(result.error || 'Action failed');
        location.href = <?php echo json_encode(managerNotifUrl(['id' => null]), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    } catch (error) { alert(error.message); }
}
</script>
<?php require_once '../includes/footer.php'; ?>
