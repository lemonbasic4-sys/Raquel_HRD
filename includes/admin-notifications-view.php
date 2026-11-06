<?php
// Admin-only presentation; filtering and all mutations remain in manager/notifications.php.
$adminNotificationUrl = static fn(array $changes = []) => e(managerNotifUrl(array_merge(['page' => 1, 'id' => null], $changes)));
?>
<style>
.admin-notice-page { min-width: 0; }
.admin-notice-page .admin-notice-hero { background:linear-gradient(135deg,var(--hero-from) 0%,var(--hero-mid) 50%,var(--hero-to) 100%); color:#fff; }
.admin-notice-page .admin-notice-hero::before,
.admin-notice-page .admin-notice-hero::after { content:none; background:none; }
.admin-notice-hero p { color:#d9e6d2; margin:0; }
.admin-notice-page .admin-notice-stat.page-hero { display:block; height:100%; margin:0; padding:18px; background:linear-gradient(135deg,var(--hero-from) 0%,var(--hero-mid) 50%,var(--hero-to) 100%); border:1px solid rgba(255,255,255,.12); border-radius:15px; color:#fff; text-decoration:none; box-shadow:0 8px 22px rgba(23,75,18,.16); }
.admin-notice-page .admin-notice-stat::before,
.admin-notice-page .admin-notice-stat::after { content:none; background:none; }
.admin-notice-stat:hover { color:#fff; filter:brightness(1.08); }
.admin-notice-stat strong { display:block; font-size:1.55rem; line-height:1.2; }
.admin-notice-stat span { color:rgba(255,255,255,.75); font-size:.85rem; }
.admin-notice-tabs .btn.active { background:#234d08; color:#fff; border-color:#234d08; }
.admin-notice-filter { background:#f4f7ef; border:1px solid #bdcba9; border-radius:16px; padding:16px; }
.admin-notice-event { background:#fff; border:1px solid #cbd8b9; border-radius:16px; margin-bottom:12px; overflow:hidden; box-shadow:0 5px 16px rgba(8,46,6,.06); }
.admin-notice-event[open] { border-color:#649457; }
.admin-notice-event summary { list-style:none; cursor:pointer; padding:17px; display:grid; grid-template-columns:42px minmax(0,1fr) auto; gap:13px; align-items:start; }
.admin-notice-event summary::-webkit-details-marker { display:none; }
.admin-notice-icon { display:grid; place-items:center; width:40px; height:40px; border-radius:11px; background:#e6f0e1; color:#225a26; }
.admin-notice-title { font-weight:700; color:#17391d; overflow-wrap:anywhere; }
.admin-notice-meta { color:#68776a; font-size:.8rem; margin-top:3px; }
.admin-notice-preview { color:#405047; font-size:.88rem; margin-top:7px; overflow-wrap:anywhere; }
.admin-notice-pill { border-radius:999px; background:#edf1ec; color:#526252; padding:4px 10px; font-size:.75rem; font-weight:700; white-space:nowrap; }
.admin-notice-pill.unread { background:#def3e2; color:#166d32; }
.admin-notice-detail { padding:0 17px 18px 72px; border-top:1px solid #e3eadd; overflow-wrap:anywhere; }
.admin-notice-detail p { white-space:pre-line; margin:15px 0; }
.admin-notice-actions { display:flex; flex-wrap:wrap; gap:8px; }
@media(max-width:575px) { .admin-notice-hero { padding:20px; } .admin-notice-event summary { grid-template-columns:40px minmax(0,1fr); } .admin-notice-pill { grid-column:2; justify-self:start; } .admin-notice-detail { padding-left:17px; } }
</style>
<div class="admin-notice-page">
    <div class="page-hero fadeup admin-notice-hero mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div><div class="small text-uppercase mb-1" style="letter-spacing:.12em;color:#f4dc9d">System Administration · Inbox</div><h1 class="h3 fw-bold mb-1"><i class="fas fa-bell me-2"></i>Notifications</h1><p>Review updates and open the related record when action is needed.</p></div>
        <?php if ($total_unread): ?><form method="post" class="m-0"><?php echo csrfField(); ?><input type="hidden" name="inbox_action" value="mark_all_read"><button class="btn btn-light" type="submit"><i class="fas fa-check-double me-1"></i>Mark all as read</button></form><?php endif; ?>
    </div>
    <div class="row g-3 mb-4">
        <?php foreach ([['All notifications', $total_all, 'all', 'fa-inbox'], ['Unread', $total_unread, 'unread', 'fa-envelope'], ['Needs my action', $action_count, 'action', 'fa-circle-exclamation']] as $stat): ?>
            <div class="col-6 col-lg-4"><a class="page-hero fadeup admin-notice-stat" href="<?php echo $adminNotificationUrl(['filter' => $stat[2]]); ?>"><i class="fas <?php echo $stat[3]; ?> mb-2" style="color:#f4cb53"></i><strong><?php echo number_format($stat[1]); ?></strong><span><?php echo e($stat[0]); ?></span></a></div>
        <?php endforeach; ?>
    </div>
    <nav class="admin-notice-tabs d-flex flex-wrap gap-2 mb-3" aria-label="Notification status">
        <?php foreach (['all' => 'All', 'unread' => 'Unread', 'read' => 'Read', 'action' => 'Needs my action'] as $key => $label): ?><a class="btn btn-sm <?php echo $filter === $key ? 'active' : 'btn-outline-success'; ?>" href="<?php echo $adminNotificationUrl(['filter' => $key]); ?>"><?php echo e($label); ?></a><?php endforeach; ?>
    </nav>
    <div class="admin-notice-filter mb-4">
        <form method="get" class="row g-2 align-items-end">
            <input type="hidden" name="filter" value="<?php echo e($filter); ?>">
            <div class="col-12 col-lg-5"><label class="form-label small mb-1">Search notifications</label><input class="form-control form-control-sm" name="search" value="<?php echo e($search); ?>" placeholder="Title or message"></div>
            <div class="col-6 col-lg-3"><label class="form-label small mb-1">Category</label><select class="form-select form-select-sm" name="category"><?php foreach ($categories as $key => $meta): ?><option value="<?php echo e($key); ?>" <?php echo $category === $key ? 'selected' : ''; ?>><?php echo e($meta[0]); ?></option><?php endforeach; ?></select></div>
            <div class="col-6 col-lg-2"><label class="form-label small mb-1">Date</label><select class="form-select form-select-sm" name="date"><?php foreach (['all' => 'All time', 'today' => 'Today', '7' => 'Last 7 days', '30' => 'Last 30 days'] as $key => $label): ?><option value="<?php echo $key; ?>" <?php echo $date_filter === $key ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></div>
            <div class="col-6 col-lg-1"><button class="btn btn-sm btn-primary w-100" type="submit">Apply</button></div>
            <div class="col-6 col-lg-1"><a class="btn btn-sm btn-outline-secondary w-100" href="<?php echo e(BASE_URL); ?>/admin/notifications.php">Clear</a></div>
        </form>
    </div>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h2 class="h5 mb-0">Notification activity</h2><small class="text-muted">Showing <?php echo $total_filtered ? number_format($offset + 1) . '–' . number_format($offset + count($manager_notif_items)) : '0'; ?> of <?php echo number_format($total_filtered); ?> matching notifications</small></div><span class="small text-muted"><i class="fas fa-circle-info me-1"></i>Expand a notice to view details and actions</span></div>
    <?php if (!$manager_notif_items): ?><div class="text-center text-muted bg-light rounded-4 py-5"><i class="far fa-bell-slash fa-2x mb-3"></i><br>No notifications match these filters.</div><?php endif; ?>
    <?php foreach ($manager_notif_items as $item): $id = (int)$item['notification_id']; $meta = $categories[$item['category'] ?? 'system'] ?? $categories['system']; $target = managerNotifLink($item['link']); ?>
        <details class="admin-notice-event" <?php echo $selected_id === $id ? 'open' : ''; ?>><summary><div class="admin-notice-icon"><i class="<?php echo e($meta[1]); ?>"></i></div><div><div class="admin-notice-title"><?php echo e($item['title']); ?></div><div class="admin-notice-meta"><?php echo e($meta[0]); ?> · <?php echo e(formatDateTime($item['created_at'])); ?></div><div class="admin-notice-preview"><?php echo e(mb_strimwidth($item['message'], 0, 150, '…')); ?></div></div><span class="admin-notice-pill <?php echo $item['is_read'] ? '' : 'unread'; ?>"><?php echo $item['is_read'] ? 'Read' : 'Unread'; ?></span></summary>
            <div class="admin-notice-detail"><p><?php echo e($item['message']); ?></p><div class="admin-notice-actions">
                <?php if ($target): ?><form method="post"><?php echo csrfField(); ?><input type="hidden" name="notification_id" value="<?php echo $id; ?>"><button class="btn btn-sm btn-success" name="inbox_action" value="open_related"><i class="fas fa-arrow-up-right-from-square me-1"></i>Open related page</button></form><?php endif; ?>
                <form method="post"><?php echo csrfField(); ?><input type="hidden" name="notification_id" value="<?php echo $id; ?>"><button class="btn btn-sm btn-outline-secondary" name="inbox_action" value="<?php echo $item['is_read'] ? 'mark_unread' : 'mark_read'; ?>"><?php echo $item['is_read'] ? 'Mark unread' : 'Mark read'; ?></button></form>
                <form method="post" onsubmit="return confirm('Delete this notification?')"><?php echo csrfField(); ?><input type="hidden" name="notification_id" value="<?php echo $id; ?>"><button class="btn btn-sm btn-outline-danger" name="inbox_action" value="delete">Delete</button></form>
            </div></div>
        </details>
    <?php endforeach; ?>
    <?php if ($total_pages > 1): ?><nav class="d-flex justify-content-center align-items-center gap-2 my-4" aria-label="Notification pages"><a class="btn btn-sm btn-outline-secondary <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="<?php echo e(managerNotifUrl(['page' => max(1, $page - 1), 'id' => null])); ?>">Previous</a><span class="small text-muted">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span><a class="btn btn-sm btn-outline-secondary <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" href="<?php echo e(managerNotifUrl(['page' => min($total_pages, $page + 1), 'id' => null])); ?>">Next</a></nav><?php endif; ?>
</div>
