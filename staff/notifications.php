<?php
$page_title = 'Notifications';
require_once '../includes/session-check.php';
checkRole(['HR Staff']);
require_once '../includes/functions.php';

// ── Filters & Pagination ──
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;
$user_id = (int)$_SESSION['user_id'];

$where = "WHERE user_id = ? AND (link NOT LIKE '%/employee/%' OR link LIKE '%/employee/team-evaluation%' OR link LIKE '%/employee/package-member%' OR link IS NULL OR link = '')";
$types = "i";
$params = [$user_id];

if ($filter === 'unread') { $where .= " AND is_read = 0"; }
elseif ($filter === 'read') { $where .= " AND is_read = 1"; }

if ($search !== '') {
    $where .= " AND (title LIKE ? OR message LIKE ?)";
    $types .= "ss";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$count_all_stmt = $conn->prepare("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND (link NOT LIKE '%/employee/%' OR link LIKE '%/employee/team-evaluation%' OR link LIKE '%/employee/package-member%' OR link IS NULL OR link = '')");
$count_all_stmt->bind_param("i", $user_id); $count_all_stmt->execute();
$total_all = $count_all_stmt->get_result()->fetch_assoc()['c']; $count_all_stmt->close();

$count_unread_stmt = $conn->prepare("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0 AND (link NOT LIKE '%/employee/%' OR link LIKE '%/employee/team-evaluation%' OR link LIKE '%/employee/package-member%' OR link IS NULL OR link = '')");
$count_unread_stmt->bind_param("i", $user_id); $count_unread_stmt->execute();
$total_unread = $count_unread_stmt->get_result()->fetch_assoc()['c']; $count_unread_stmt->close();
$total_read = $total_all - $total_unread;

$count_stmt = $conn->prepare("SELECT COUNT(*) as c FROM notifications $where");
$count_stmt->bind_param($types, ...$params); $count_stmt->execute();
$total_filtered = $count_stmt->get_result()->fetch_assoc()['c']; $count_stmt->close();

$total_pages = max(1, ceil($total_filtered / $per_page));
if ($page > $total_pages) $page = $total_pages;

$stmt = $conn->prepare("SELECT * FROM notifications $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
$fetch_types = $types . "ii"; $fetch_params = array_merge($params, [$per_page, $offset]);
$stmt->bind_param($fetch_types, ...$fetch_params); $stmt->execute();
$result = $stmt->get_result(); $notifications = [];
while ($row = $result->fetch_assoc()) { $notifications[] = $row; }
$stmt->close();

require_once '../includes/header.php';

function getNotifIconClass($title) {
    $t = strtolower($title);
    if (str_contains($t, 'approved') || str_contains($t, 'approval')) return 'approve';
    if (str_contains($t, 'rejected') || str_contains($t, 'reject')) return 'reject';
    if (str_contains($t, 'returned') || str_contains($t, 'revision')) return 'return';
    if (str_contains($t, 'evaluation') || str_contains($t, 'endorsed') || str_contains($t, 'validation')) return 'eval';
    return 'system';
}
function getNotifFA($cls) {
    switch ($cls) { case 'approve': return 'fas fa-check-circle'; case 'reject': return 'fas fa-times-circle'; case 'return': return 'fas fa-undo-alt'; case 'eval': return 'fas fa-file-alt'; default: return 'fas fa-bell'; }
}
function timeAgo($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'Just now'; if ($diff < 3600) return floor($diff/60).'m ago';
    if ($diff < 86400) return floor($diff/3600).'h ago'; if ($diff < 604800) return floor($diff/86400).'d ago';
    return date('M d, Y', strtotime($datetime));
}
$base_url = strtok($_SERVER['REQUEST_URI'], '?');
?>

<style>
    /* Mobile Optimization for Notification Center */
    @media (max-width: 576px) {
        .notif-stats-row {
            gap: 8px;
            margin-bottom: 15px;
        }
        .notif-stat-card {
            padding: 10px 8px;
            min-width: 0;
            flex: 1;
        }
        .notif-stat-card .nsc-icon {
            width: 30px;
            height: 30px;
            font-size: 0.85rem;
            margin-right: 8px;
        }
        .notif-stat-card h4 {
            font-size: 1.1rem;
            margin-bottom: 0;
        }
        .notif-stat-card p {
            font-size: 0.65rem;
        }
        .notif-toolbar {
            background: #fff;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 15px;
        }
        .notif-filters {
            display: flex;
            overflow-x: auto;
            gap: 8px;
            padding-bottom: 10px;
            margin-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
            scrollbar-width: none;
        }
        .notif-filters::-webkit-scrollbar { display: none; }
        .notif-filter-btn {
            white-space: nowrap;
            padding: 6px 12px;
            font-size: 0.8rem;
        }
        .notif-search-box {
            width: 100%;
            margin-left: 0 !important;
        }
        .notif-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            width: 100%;
        }
        .notif-action-btn {
            justify-content: center;
            font-size: 0.75rem;
            padding: 8px;
        }
        .notif-card {
            padding: 12px;
            margin-bottom: 8px;
            border-radius: 12px;
        }
        .notif-card-icon {
            width: 38px;
            height: 38px;
            font-size: 0.9rem;
        }
        .notif-card-title {
            font-size: 0.85rem;
        }
        .notif-card-message {
            font-size: 0.78rem;
            line-height: 1.4;
        }
        .notif-card-actions {
            opacity: 1 !important;
            flex-direction: column;
            gap: 8px;
        }
    }
</style>

<div class="page-hero fadeup">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-0 gap-3">
        <div>
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.55);">Staff Portal · Inbox</div>
            <h4 class="text-white fw-bold mb-0 mt-1"><i class="fas fa-bell me-2" style="color:var(--primary-light);"></i>Notification Center</h4>
            <p class="text-white-50 small mb-0 mt-2 d-none d-sm-block">View approval status updates, rejection feedback, and system alerts for your submissions</p>
        </div>
        <a href="<?php echo BASE_URL; ?>/staff/dashboard.php" class="btn btn-outline-light btn-sm px-3" style="border-radius: 20px;">
            <i class="fas fa-arrow-left me-2"></i>Dashboard
        </a>
    </div>
</div>

<div class="notif-stats-row fadeup-1">
    <div class="notif-stat-card">
        <div class="nsc-icon" style="background:rgba(41,67,6,0.1); color:var(--primary-blue);"><i class="fas fa-layer-group"></i></div>
        <div class="nsc-info"><h4 id="statTotal"><?php echo $total_all; ?></h4><p>Total</p></div>
    </div>
    <div class="notif-stat-card">
        <div class="nsc-icon" style="background:rgba(189,148,20,0.1); color:var(--primary-light);"><i class="fas fa-envelope"></i></div>
        <div class="nsc-info"><h4 id="statUnread"><?php echo $total_unread; ?></h4><p>Unread</p></div>
    </div>
    <div class="notif-stat-card">
        <div class="nsc-icon" style="background:rgba(220,25,32,0.1); color:var(--accent);"><i class="fas fa-trash-alt"></i></div>
        <div class="nsc-info"><h4 id="statRead"><?php echo $total_read; ?></h4><p>Read</p></div>
    </div>
</div>

<div class="notif-toolbar">
    <div class="notif-filters">
        <a href="<?php echo $base_url; ?>?filter=all<?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="notif-filter-btn <?php echo $filter==='all'?'active':''; ?>">All</a>
        <a href="<?php echo $base_url; ?>?filter=unread<?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="notif-filter-btn <?php echo $filter==='unread'?'active':''; ?>">Unread <?php if($total_unread>0): ?><span style="background:rgba(220,53,69,0.15);color:#dc3545;padding:1px 7px;border-radius:10px;font-size:0.72rem;margin-left:3px;"><?php echo $total_unread; ?></span><?php endif; ?></a>
        <a href="<?php echo $base_url; ?>?filter=read<?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="notif-filter-btn <?php echo $filter==='read'?'active':''; ?>">Read</a>
        <form method="GET" action="" style="display:contents;"><input type="hidden" name="filter" value="<?php echo e($filter); ?>"><div class="notif-search-box"><i class="fas fa-search search-icon"></i><input type="text" name="search" value="<?php echo e($search); ?>" placeholder="Search notifications..."></div></form>
    </div>
    <div class="notif-actions">
        <?php if($total_unread>0): ?><button class="notif-action-btn" onclick="bulkAction('mark_all_read')"><i class="fas fa-check-double"></i> Mark All Read</button><?php endif; ?>
        <?php if($total_read>0): ?><button class="notif-action-btn danger" onclick="bulkAction('delete_all_read')"><i class="fas fa-trash-alt"></i> Clear Read</button><?php endif; ?>
    </div>
</div>

<?php if (empty($notifications)): ?>
    <div class="notif-empty-state"><div class="notif-empty-icon"><i class="fas fa-bell-slash"></i></div><h5>No notifications<?php echo $filter!=='all'||$search!==''?' matching your filter':''; ?></h5><p><?php echo ($filter!=='all'||$search!=='') ? 'Try changing your filter or search terms.' : "You're all caught up! New notifications will appear here when actions occur in the system."; ?></p></div>
<?php else: ?>
    <div class="notif-list" id="notifList">
        <?php foreach ($notifications as $i => $notif): 
            $icon_info = getNotifIconInfo($notif['title']); 
            $is_unread = !$notif['is_read']; 
        ?>
        <div class="notif-card <?php echo $is_unread ? 'unread' : ''; ?>" id="notif-<?php echo $notif['notification_id']; ?>" style="--i: <?php echo $i; ?>;" data-id="<?php echo $notif['notification_id']; ?>" data-read="<?php echo $notif['is_read']; ?>">
            <div class="notif-card-icon <?php echo $icon_info['class']; ?>">
                <i class="<?php echo $icon_info['icon']; ?>"></i>
            </div>
            <div class="notif-card-body" onclick="navigateNotif(<?php echo $notif['notification_id']; ?>, '<?php echo e($notif['link'] ?? ''); ?>', <?php echo $is_unread ? 'true' : 'false'; ?>)">
                <div class="notif-card-header-row">
                    <h5 class="notif-card-title"><?php echo e($notif['title']); ?></h5>
                    <?php if ($is_unread): ?>
                        <span class="badge rounded-pill bg-warning text-dark fw-bold px-2 py-1" style="font-size:0.7rem;">Unread</span>
                    <?php else: ?>
                        <span class="badge rounded-pill bg-secondary bg-opacity-25 text-secondary fw-medium px-2 py-1" style="font-size:0.7rem;">Read</span>
                    <?php endif; ?>
                </div>
                <div class="notif-card-message"><?php echo e($notif['message']); ?></div>
                <div class="notif-card-meta-row">
                    <div class="notif-card-time"><i class="far fa-clock me-1"></i><?php echo timeAgoFormat($notif['created_at']); ?></div>
                    <?php if ($notif['link']): ?>
                        <span class="notif-action-link">View Details <i class="fas fa-arrow-right" style="font-size:0.7rem;"></i></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="notif-card-actions">
                <?php if ($is_unread): ?>
                    <button onclick="notifAction(<?php echo $notif['notification_id']; ?>, 'mark_read')" title="Mark as read"><i class="fas fa-envelope-open"></i></button>
                <?php else: ?>
                    <button onclick="notifAction(<?php echo $notif['notification_id']; ?>, 'mark_unread')" title="Mark as unread"><i class="fas fa-envelope"></i></button>
                <?php endif; ?>
                <button class="btn-delete" onclick="notifAction(<?php echo $notif['notification_id']; ?>, 'delete')" title="Delete"><i class="fas fa-trash-alt"></i></button>
            </div>
        </div>
        <?php endforeach; ?>

    </div>
    <?php if ($total_pages > 1): ?>
    <div class="notif-pagination">
        <?php if($page>1): ?><a href="<?php echo $base_url; ?>?filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page-1; ?>"><i class="fas fa-chevron-left"></i></a><?php else: ?><span class="disabled"><i class="fas fa-chevron-left"></i></span><?php endif; ?>
        <?php $sp=max(1,$page-3);$ep=min($total_pages,$sp+6);if($ep-$sp<6)$sp=max(1,$ep-6);for($p=$sp;$p<=$ep;$p++): ?>
            <?php if($p===$page): ?><span class="current"><?php echo $p; ?></span><?php else: ?><a href="<?php echo $base_url; ?>?filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a><?php endif; ?>
        <?php endfor; ?>
        <?php if($page<$total_pages): ?><a href="<?php echo $base_url; ?>?filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page+1; ?>"><i class="fas fa-chevron-right"></i></a><?php else: ?><span class="disabled"><i class="fas fa-chevron-right"></i></span><?php endif; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>

<script>
const AJAX_URL = '<?php echo BASE_URL; ?>/includes/ajax/notification-action.php';
const NOTIF_CONTEXT = 'hr';

function notifAction(id, action) {
    if (action === 'delete' && !confirm('Delete this notification?')) return;
    const fd = new FormData(); 
    fd.append('notification_id', id); 
    fd.append('action', action);
    fd.append('context', NOTIF_CONTEXT);

    fetch(AJAX_URL, { method: 'POST', body: fd }).then(r => r.json()).then(data => {
        if (!data.success) return alert('Error: ' + (data.error || 'Unknown'));
        const card = document.getElementById('notif-' + id); if (!card) return;
        if (action === 'delete') {
            card.classList.add('removing');
            setTimeout(() => { card.remove(); updateStats(); if (!document.querySelectorAll('.notif-card').length) location.reload(); }, 380);
        } else if (action === 'mark_read') {
            card.classList.remove('unread'); card.classList.add('just-read'); card.dataset.read = '1';
            const dot = card.querySelector('.notif-unread-dot'); if (dot) dot.remove();
            const btn = card.querySelector('.notif-card-actions button:first-child');
            btn.setAttribute('onclick', `notifAction(${id}, 'mark_unread')`); btn.title = 'Mark as unread'; btn.innerHTML = '<i class="fas fa-envelope"></i>';
            updateStats();
        } else if (action === 'mark_unread') {
            card.classList.add('unread'); card.dataset.read = '0';
            if (!card.querySelector('.notif-unread-dot')) { const d = document.createElement('div'); d.className = 'notif-unread-dot'; d.title = 'Unread'; card.querySelector('.notif-card-actions').before(d); }
            const btn = card.querySelector('.notif-card-actions button:first-child');
            btn.setAttribute('onclick', `notifAction(${id}, 'mark_read')`); btn.title = 'Mark as read'; btn.innerHTML = '<i class="fas fa-envelope-open"></i>';
            updateStats();
        }
    });
}
function navigateNotif(id, link, isUnread) { 
    if (isUnread) { 
        const fd = new FormData(); 
        fd.append('notification_id', id); 
        fd.append('action', 'mark_read'); 
        fd.append('context', NOTIF_CONTEXT);
        fetch(AJAX_URL, { method: 'POST', body: fd }); 
    } 
    if (link) window.location.href = link; 
}
function bulkAction(action) { 
    if (action === 'delete_all_read' && !confirm('Delete all read notifications?')) return; 
    if (action === 'mark_all_read' && !confirm('Mark all as read?')) return; 
    
    if (action === 'mark_all_read') {
        markAllRead(); // Global
        return;
    }

    const fd = new FormData(); 
    fd.append('action', action); 
    fd.append('context', NOTIF_CONTEXT);
    fetch(AJAX_URL, { method: 'POST', body: fd }).then(r => r.json()).then(d => { if (d.success) location.reload(); }); 
}
function updateStats() {
    const cards = document.querySelectorAll('.notif-card'); 
    let u = 0;
    cards.forEach(c => { if (c.dataset.read === '0') u++; });
    
    if (document.getElementById('statTotal')) document.getElementById('statTotal').textContent = cards.length;
    if (document.getElementById('statUnread')) document.getElementById('statUnread').textContent = u;
    if (document.getElementById('statRead')) document.getElementById('statRead').textContent = cards.length - u;
    
    // Update the tab badge for unread filter
    const unreadTab = document.querySelector('a[href*="filter=unread"]');
    if (unreadTab) {
        let span = unreadTab.querySelector('span');
        if (u > 0) {
            if (!span) {
                span = document.createElement('span');
                span.style = "background:rgba(220,53,69,0.15);color:#dc3545;padding:1px 7px;border-radius:10px;font-size:0.72rem;margin-left:3px;";
                unreadTab.appendChild(span);
            }
            span.textContent = u;
        } else if (span) {
            span.remove();
        }
    }

    const badge = document.querySelector('.notification-badge');
    if (badge) { 
        badge.textContent = u > 9 ? '9+' : u; 
        badge.style.display = u === 0 ? 'none' : ''; 
    }
}
</script>
<?php require_once '../includes/footer.php'; ?>
