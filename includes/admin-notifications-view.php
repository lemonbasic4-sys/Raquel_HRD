<?php
// Shared presentation; filtering and mutations remain in manager/notifications.php.
$adminNotificationUrl = static fn(array $changes = []) => e(managerNotifUrl(array_merge(['page' => 1, 'id' => null], $changes)));
$notificationInboxLabel = $notification_center_role === 'Admin' ? 'System Administration' : $notification_center_role;
$notificationActionLabel = in_array($notification_center_role, ['HR Staff', 'Employee'], true) ? 'My follow-ups' : 'Needs my action';
?>
<style>
.admin-notice-page { min-width: 0; }
.admin-notice-page .admin-notice-overview.page-hero { background:linear-gradient(135deg,var(--hero-from) 0%,var(--hero-mid) 50%,var(--hero-to) 100%); border-radius:16px; padding:22px 26px; color:#fff; }
.admin-notice-page .admin-notice-overview::before,
.admin-notice-page .admin-notice-overview::after { content:none; background:none; }
.admin-notice-page .admin-notice-hero { color:#fff; min-width:0; }
.admin-notice-page .admin-notice-hero::before,
.admin-notice-page .admin-notice-hero::after { content:none; background:none; }
.admin-notice-hero p { color:#d9e6d2; margin:0; }
.admin-notice-page .admin-notice-stat.page-hero { display:flex; align-items:center; gap:10px; height:100%; margin:0; padding:10px 12px; background:rgba(255,255,255,.14); border:1px solid rgba(255,255,255,.23); border-radius:11px; color:#fff; text-decoration:none; box-shadow:none; }
.admin-notice-page .admin-notice-stat::before,
.admin-notice-page .admin-notice-stat::after { content:none; background:none; }
.admin-notice-stat:hover { border-color:rgba(255,255,255,.55) !important; color:#fff; background:rgba(255,255,255,.2) !important; }
.admin-notice-stat .stat-icon { width:31px; height:31px; display:grid; place-items:center; border-radius:8px; background:rgba(255,255,255,.18); color:#f4cb53; }
.admin-notice-stat strong { display:block; font-size:1.15rem; line-height:1.1; }
.admin-notice-stat span { color:#e1ebdb; font-size:.73rem; }
.admin-notice-overview { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; gap:20px; }
.admin-notice-overview .admin-notice-hero { min-width:0; }
.admin-notice-stats { display:grid; grid-template-columns:repeat(3,minmax(90px,1fr)); gap:8px; min-width:min(100%,390px); }
.admin-notice-mark-all { margin:0 0 14px; }
.admin-notice-mark-all .btn { font-size:.75rem; }
.admin-notice-tabs .btn.active { background:#234d08; color:#fff; border-color:#234d08; }
.admin-notice-filter { background:#fff; border:1px solid #e1e8e4; border-radius:12px; margin-bottom:15px !important; box-shadow:0 2px 9px rgba(8,46,6,.04); }
.admin-notice-filter summary { display:flex; justify-content:space-between; align-items:center; padding:14px 16px; cursor:pointer; list-style:none; font-weight:700; color:#17391d; }
.admin-notice-filter summary::-webkit-details-marker { display:none; }
.admin-notice-filter summary .fa-chevron-down { transition:transform .2s ease; }
.admin-notice-filter[open] summary .fa-chevron-down { transform:rotate(180deg); }
.admin-notice-filter form { padding:0 16px 16px; }
.admin-notice-filter .form-label { display:none; }
.admin-notice-filter .form-control,.admin-notice-filter .form-select { min-height:36px; }
.admin-notice-activity.page-hero { padding:18px 22px; margin-bottom:16px; color:#fff; }
.admin-notice-activity .activity-subtitle,.admin-notice-activity .activity-help { color:#e1ebdb; }
.admin-notice-event { position:relative; background:#fff; border:1px solid #dfe9df; border-radius:11px; margin-bottom:8px; overflow:hidden; box-shadow:0 2px 10px rgba(8,46,6,.04); }
.admin-notice-event.is-action { border-left:4px solid #649a27; background:linear-gradient(90deg,#f7fbf5 0%,#fff 60%); }
.admin-notice-event[open] { border-color:#649457; }
.admin-notice-event summary { list-style:none; cursor:pointer; padding:12px 155px 12px 13px; display:grid; grid-template-columns:36px minmax(0,1fr); gap:11px; align-items:center; min-height:72px; }
.admin-notice-event summary::-webkit-details-marker { display:none; }
.admin-notice-icon { display:grid; place-items:center; width:34px; height:34px; border-radius:9px; background:#e6f0e1; color:#225a26; }
.admin-notice-icon i { font-size:1rem; }
.admin-notice-icon.login { background:#e4f3e6; color:#14733a; }
.admin-notice-icon.security, .admin-notice-icon.reject { background:#fde8e6; color:#b42e32; }
.admin-notice-icon.approve { background:#e4f3e6; color:#14733a; }
.admin-notice-icon.return { background:#fff1d5; color:#946500; }
.admin-notice-icon.peer { background:#e4f1e8; color:#145d32; }
.admin-notice-icon.kra { background:#f7edcd; color:#8a6400; }
.admin-notice-icon.self-rating { background:#eef4df; color:#4d7015; }
.admin-notice-icon.probation { background:#e9f0e3; color:#315c21; }
.admin-notice-icon.eval { background:#e8f1e5; color:#2c6728; }
.admin-notice-icon.accounts, .admin-notice-icon.employees { background:#e7f0e6; color:#2f612f; }
.admin-notice-icon.career { background:#edf3df; color:#637615; }
.admin-notice-icon.backups, .admin-notice-icon.settings, .admin-notice-icon.audit { background:#eeeede; color:#626d2b; }
.admin-notice-title { font-weight:700; color:#17391d; font-size:.86rem; overflow-wrap:anywhere; }
.admin-notice-meta { color:#68776a; font-size:.72rem; margin-top:2px; }
.admin-notice-preview { color:#405047; font-size:.76rem; margin-top:3px; overflow-wrap:anywhere; }
.admin-notice-row-actions { position:absolute; right:12px; top:9px; display:flex; flex-direction:column; align-items:stretch; gap:5px; width:116px; z-index:1; }
.admin-notice-row-actions form { margin:0; }
.admin-notice-row-actions .btn { width:100%; padding:3px 7px; font-size:.73rem; }
.admin-notice-pill { border-radius:999px; background:#edf1ec; color:#526252; padding:3px 8px; font-size:.7rem; font-weight:700; white-space:nowrap; text-align:center; }
.admin-notice-pill.unread { background:#def3e2; color:#166d32; }
.admin-notice-pill.action { background:#fff0d5; color:#a36000; }
.admin-notice-detail { padding:0 17px 18px 72px; border-top:1px solid #e3eadd; overflow-wrap:anywhere; }
.admin-notice-detail p { white-space:pre-line; margin:15px 0; }
.admin-notice-actions { display:flex; flex-wrap:wrap; gap:8px; }
@media(max-width:800px) { .admin-notice-overview { grid-template-columns:1fr; gap:5px; } .admin-notice-stats { min-width:0; } }
@media(max-width:575px) { .admin-notice-event summary { padding-right:115px; grid-template-columns:34px minmax(0,1fr); } .admin-notice-row-actions { right:8px; width:96px; } .admin-notice-detail { padding-left:17px; } }
</style>
<div class="admin-notice-page">
    <div class="admin-notice-overview page-hero fadeup mb-3">
        <div class="admin-notice-hero"><div class="small mb-1"><?php echo e($notificationInboxLabel); ?> · Inbox</div><h1 class="h3 fw-bold mb-1"><i class="fas fa-bell me-2"></i>Notifications</h1><p>Review updates and open the related record when action is needed.</p></div>
        <div class="admin-notice-stats">
        <?php foreach ([['All notifications', $total_all, 'all', 'fa-inbox'], ['Unread', $total_unread, 'unread', 'fa-envelope'], [$notificationActionLabel, $action_count, 'action', 'fa-circle-exclamation']] as $stat): ?>
            <a class="admin-notice-stat page-hero" href="<?php echo $adminNotificationUrl(['filter' => $stat[2]]); ?>"><span class="stat-icon"><i class="fas <?php echo $stat[3]; ?>" aria-hidden="true"></i></span><span><span><?php echo e($stat[0]); ?></span><strong><?php echo number_format($stat[1]); ?></strong></span></a>
        <?php endforeach; ?>
        </div>
    </div>
    <?php if ($total_unread): ?><form method="post" class="admin-notice-mark-all"><?php echo csrfField(); ?><input type="hidden" name="inbox_action" value="mark_all_read"><button class="btn btn-sm btn-outline-success" type="submit"><i class="fas fa-check-double me-1"></i>Mark all as read</button></form><?php endif; ?>
    <nav class="admin-notice-tabs d-flex flex-wrap gap-2 mb-3" aria-label="Notification status">
        <?php foreach (['all' => 'All', 'unread' => 'Unread', 'read' => 'Read', 'action' => $notificationActionLabel] as $key => $label): ?><a class="btn btn-sm <?php echo $filter === $key ? 'active' : 'btn-outline-success'; ?>" href="<?php echo $adminNotificationUrl(['filter' => $key]); ?>"><?php echo e($label); ?></a><?php endforeach; ?>
    </nav>
    <details class="admin-notice-filter mb-4" <?php echo $search !== '' || $date_filter !== 'all' || $category !== 'all' ? 'open' : ''; ?>>
        <summary><span><i class="fas fa-filter me-2" aria-hidden="true"></i>Filters<?php if ($search !== '' || $date_filter !== 'all' || $category !== 'all'): ?><span class="small fw-normal ms-2">Active</span><?php endif; ?></span><i class="fas fa-chevron-down" aria-hidden="true"></i></summary>
        <form method="get" class="row g-2 align-items-end">
            <input type="hidden" name="filter" value="<?php echo e($filter); ?>">
            <div class="col-12 col-lg-6"><label class="form-label small mb-1" for="noticeSearch">Search notifications</label><input class="form-control form-control-sm" id="noticeSearch" name="search" value="<?php echo e($search); ?>" placeholder="Search notifications (title or message)"></div>
            <div class="col-12 col-sm-6 col-lg-2"><label class="form-label small mb-1" for="noticeCategory">Category</label><select class="form-select form-select-sm" id="noticeCategory" name="category" aria-label="Notification category"><?php foreach ($categories as $key => $meta): ?><option value="<?php echo e($key); ?>" <?php echo $category === $key ? 'selected' : ''; ?>><?php echo e($meta[0]); ?></option><?php endforeach; ?></select></div>
            <div class="col-6 col-lg-2"><label class="form-label small mb-1">Date</label><select class="form-select form-select-sm" name="date"><?php foreach (['all' => 'All time', 'today' => 'Today', '7' => 'Last 7 days', '30' => 'Last 30 days'] as $key => $label): ?><option value="<?php echo $key; ?>" <?php echo $date_filter === $key ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></div>
            <div class="col-6 col-lg-1"><button class="btn btn-sm btn-success w-100" type="submit">Apply</button></div>
            <div class="col-6 col-lg-1"><a class="btn btn-sm btn-outline-secondary w-100" href="<?php echo e(BASE_URL . '/' . $notification_center_portal . '/notifications.php'); ?>">Clear</a></div>
        </form>
    </details>
    <div class="admin-notice-activity page-hero fadeup d-flex flex-wrap justify-content-between align-items-center gap-2"><div><h2 class="h5 mb-0">Notification activity</h2><small class="activity-subtitle">Showing <?php echo $total_filtered ? number_format($offset + 1) . '–' . number_format($offset + count($manager_notif_items)) : '0'; ?> of <?php echo number_format($total_filtered); ?> matching notifications</small></div><span class="activity-help small"><i class="fas fa-circle-info me-1"></i>Expand a notice to view details and actions</span></div>
    <?php if ($manager_notif_items): ?>
        <form method="post" id="noticeBulkForm" class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <?php echo csrfField(); ?>
            <label class="small me-2"><input type="checkbox" id="noticeSelectAll"> Select page</label>
            <span id="noticeSelectionCount" class="small text-muted">0 selected</span>
            <button class="btn btn-sm btn-outline-success" name="inbox_action" value="bulk_read" type="submit" disabled>Mark selected read</button>
            <button class="btn btn-sm btn-outline-danger" name="inbox_action" value="bulk_delete" type="submit" onclick="return confirm('Delete the selected notifications?')" disabled>Delete selected</button>
        </form>
    <?php endif; ?>
    <?php if (!$manager_notif_items): ?><div class="text-center text-muted bg-light rounded-4 py-5"><i class="far fa-bell-slash fa-2x mb-3"></i><br>No notifications match these filters.</div><?php endif; ?>
    <?php foreach ($manager_notif_items as $item): $id = (int)$item['notification_id']; $meta = $categories[$item['category'] ?? 'system'] ?? $categories['system']; $icon_info = getNotifIconInfo($item['title'], $item['category'] ?? ''); $target = managerNotifLink($item['link']); ?>
        <details class="admin-notice-event <?php echo !empty($item['needs_action']) ? 'is-action' : ''; ?>" <?php echo $selected_id === $id && isset($_GET['id']) ? 'open' : ''; ?>><summary><div class="admin-notice-icon <?php echo e($icon_info['class']); ?>"><i class="<?php echo e($icon_info['icon']); ?>"></i></div><div><div class="admin-notice-title"><?php echo e($item['title']); ?></div><div class="admin-notice-meta"><?php echo e($meta[0]); ?> · <?php echo e(formatDateTime($item['created_at'])); ?></div><div class="admin-notice-preview"><?php echo e(mb_strimwidth($item['message'], 0, 150, '…')); ?></div></div></summary>
            <div class="admin-notice-row-actions"><span class="admin-notice-pill <?php echo !empty($item['needs_action']) ? 'action' : ($item['is_read'] ? '' : 'unread'); ?>"><?php echo !empty($item['needs_action']) ? 'Needs action' : ($item['is_read'] ? 'Read' : 'Unread'); ?></span>
                <?php if ($target): ?><form method="post"><?php echo csrfField(); ?><input type="hidden" name="notification_id" value="<?php echo $id; ?>"><button class="btn <?php echo !empty($item['needs_action']) ? 'btn-success' : 'btn-outline-success'; ?>" name="inbox_action" value="open_related" type="submit"><?php echo !empty($item['needs_action']) ? 'Review' : 'Open'; ?> <i class="fas fa-arrow-right ms-1" aria-hidden="true"></i></button></form><?php else: ?><button type="button" class="btn btn-outline-success notice-open-details">Details <i class="fas fa-arrow-right ms-1" aria-hidden="true"></i></button><?php endif; ?>
            </div>
            <div class="admin-notice-detail"><p><?php echo e($item['message']); ?></p><div class="admin-notice-actions">
                <label class="small align-self-center me-2"><input type="checkbox" class="notice-select-item" form="noticeBulkForm" name="notification_ids[]" value="<?php echo $id; ?>"> Select</label>
                <?php if ($target): ?><form method="post"><?php echo csrfField(); ?><input type="hidden" name="notification_id" value="<?php echo $id; ?>"><button class="btn btn-sm btn-success" name="inbox_action" value="open_related"><i class="fas fa-arrow-up-right-from-square me-1"></i>Open related page</button></form><?php endif; ?>
                <form method="post"><?php echo csrfField(); ?><input type="hidden" name="notification_id" value="<?php echo $id; ?>"><button class="btn btn-sm btn-outline-secondary" name="inbox_action" value="<?php echo $item['is_read'] ? 'mark_unread' : 'mark_read'; ?>"><?php echo $item['is_read'] ? 'Mark unread' : 'Mark read'; ?></button></form>
                <form method="post" onsubmit="return confirm('Delete this notification?')"><?php echo csrfField(); ?><input type="hidden" name="notification_id" value="<?php echo $id; ?>"><button class="btn btn-sm btn-outline-danger" name="inbox_action" value="delete">Delete</button></form>
            </div></div>
        </details>
    <?php endforeach; ?>
    <?php if ($total_pages > 1): ?><nav class="d-flex justify-content-center align-items-center gap-2 my-4" aria-label="Notification pages"><a class="btn btn-sm btn-outline-secondary <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="<?php echo e(managerNotifUrl(['page' => max(1, $page - 1), 'id' => null])); ?>">Previous</a><span class="small text-muted">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span><a class="btn btn-sm btn-outline-secondary <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" href="<?php echo e(managerNotifUrl(['page' => min($total_pages, $page + 1), 'id' => null])); ?>">Next</a></nav><?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.notice-open-details').forEach(button => button.addEventListener('click', function () { this.closest('details').open = true; }));
    const boxes = [...document.querySelectorAll('.notice-select-item')];
    const all = document.getElementById('noticeSelectAll');
    const count = document.getElementById('noticeSelectionCount');
    const buttons = document.querySelectorAll('#noticeBulkForm button');
    function updateSelection() {
        const selected = boxes.filter(box => box.checked).length;
        if (count) count.textContent = selected + ' selected';
        buttons.forEach(button => button.disabled = selected === 0);
        if (all) {
            all.checked = selected === boxes.length && selected > 0;
            all.indeterminate = selected > 0 && selected < boxes.length;
        }
    }
    all?.addEventListener('change', function () { boxes.forEach(box => box.checked = all.checked); updateSelection(); });
    boxes.forEach(box => box.addEventListener('change', updateSelection));
});
</script>
