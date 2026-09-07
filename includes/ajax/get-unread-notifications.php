<?php
// ============================================
// AJAX Endpoint: Get Unread Notifications
// ============================================
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$last_seen_id = isset($_GET['last_seen_id']) ? (int) $_GET['last_seen_id'] : 0;
$context = $_GET['context'] ?? ''; // 'employee' or 'hr'

// 1. Get the unread count
// Filter count based on context if needed
$count_sql = "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0";
if ($context === 'employee') {
    $count_sql .= " AND (link LIKE '%/employee/%' OR link IS NULL OR link = '')";
} elseif ($context === 'hr') {
    $count_sql .= " AND (link NOT LIKE '%/employee/%' OR link LIKE '%/employee/team-evaluation%' OR link LIKE '%/employee/package-member%' OR link IS NULL OR link = '')";
}

$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$unread_count = (int) ($count_stmt->get_result()->fetch_assoc()['unread_count'] ?? 0);
$count_stmt->close();

// 2. Fetch new unread notifications since last_seen_id (for live popups/banners)
$new_notifications = [];
$notif_sql = "SELECT notification_id, title, message, link, created_at FROM notifications WHERE user_id = ? AND is_read = 0";
if ($last_seen_id > 0) {
    $notif_sql .= " AND notification_id > ?";
}
if ($context === 'employee') {
    $notif_sql .= " AND (link LIKE '%/employee/%' OR link IS NULL OR link = '')";
} elseif ($context === 'hr') {
    $notif_sql .= " AND (link NOT LIKE '%/employee/%' OR link LIKE '%/employee/team-evaluation%' OR link LIKE '%/employee/package-member%' OR link IS NULL OR link = '')";
}
$notif_sql .= " ORDER BY notification_id ASC";

$notif_stmt = $conn->prepare($notif_sql);
if ($last_seen_id > 0) {
    $notif_stmt->bind_param("ii", $user_id, $last_seen_id);
} else {
    $notif_stmt->bind_param("i", $user_id);
}
$notif_stmt->execute();
$res = $notif_stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $new_notifications[] = [
        'id' => (int) $row['notification_id'],
        'title' => $row['title'],
        'message' => $row['message'],
        'link' => $row['link'] ? (strpos($row['link'], 'http') === 0 ? $row['link'] : $row['link']) : '',
        'created_at' => $row['created_at']
    ];
}
$notif_stmt->close();

// 3. Fetch the recent 5 unread/read notifications for the dropdown list
// (We fetch recent 5 notifications to update the dropdown list dynamically)
$recent_notifications = [];
$recent_sql = "SELECT notification_id, title, message, link, is_read, created_at FROM notifications WHERE user_id = ?";
if ($context === 'employee') {
    $recent_sql .= " AND (link LIKE '%/employee/%' OR link IS NULL OR link = '')";
} elseif ($context === 'hr') {
    $recent_sql .= " AND (link NOT LIKE '%/employee/%' OR link LIKE '%/employee/team-evaluation%' OR link LIKE '%/employee/package-member%' OR link IS NULL OR link = '')";
}
$recent_sql .= " ORDER BY created_at DESC, notification_id DESC LIMIT 5";

$recent_stmt = $conn->prepare($recent_sql);
$recent_stmt->bind_param("i", $user_id);
$recent_stmt->execute();
$res_recent = $recent_stmt->get_result();
while ($row = $res_recent->fetch_assoc()) {
    $recent_notifications[] = [
        'id' => (int) $row['notification_id'],
        'title' => $row['title'],
        'message' => $row['message'],
        'link' => $row['link'] ?: '',
        'is_read' => (int) $row['is_read'],
        'created_at' => $row['created_at']
    ];
}
$recent_stmt->close();

echo json_encode([
    'success' => true,
    'unread_count' => $unread_count,
    'new_notifications' => $new_notifications,
    'recent_notifications' => $recent_notifications
]);
exit;
