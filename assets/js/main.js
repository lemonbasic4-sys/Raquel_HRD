// ============================================
// Raquel Pawnshop HRIS - Main JavaScript
// ============================================

/**
 * Toggle sidebar visibility/collapse
 */
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const body = document.body;
    const isMobile = window.matchMedia('(max-width: 992px)').matches;

    if (isMobile) {
        // Mobile behavior: slide-in/slide-out
        sidebar.classList.toggle('show');
        if (overlay) overlay.classList.toggle('show');
        
        // Toggle body scroll lock
        if (sidebar.classList.contains('show')) {
            body.classList.add('mobile-sidebar-active');
            document.documentElement.classList.add('mobile-sidebar-active');
        } else {
            body.classList.remove('mobile-sidebar-active');
            document.documentElement.classList.remove('mobile-sidebar-active');
        }
    } else {
        // Desktop behavior: collapse to icon-only
        document.documentElement.classList.toggle('sidebar-collapsed');
        const isCollapsed = document.documentElement.classList.contains('sidebar-collapsed');
        localStorage.setItem('sidebar_collapsed', isCollapsed);
    }
}

/**
 * Mark all notifications as read (AJAX)
 */
function markAllRead() {
    const baseUrl = (typeof window !== 'undefined' && window.APP_BASE_URL) ? window.APP_BASE_URL : '';
    const context = (typeof window !== 'undefined' && window.NOTIF_CONTEXT) ? window.NOTIF_CONTEXT : 'hr';
    const fd = new FormData();
    fd.append('action', 'mark_all_read');
    fd.append('context', context);

    fetch(baseUrl + '/includes/ajax/notification-action.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove badge
                const badge = document.querySelector('.notification-badge');
                if (badge) badge.style.display = 'none';
                // Remove unread styling from dropdown items
                document.querySelectorAll('.notification-item.unread').forEach(el => {
                    el.classList.remove('unread');
                });
                // If we are on a notifications page, reload to refresh stats/list
                if (window.location.pathname.includes('notifications.php')) {
                    window.location.reload();
                }
            }
        })
        .catch(err => console.error('Error marking notifications:', err));
}

/**
 * Client-side table search filter
 */
function filterTable(inputId, tableId) {
    const filter = document.getElementById(inputId).value.toLowerCase();
    const table = document.getElementById(tableId);
    if (table) {
        const rows = table.getElementsByTagName('tr');
        for (let i = 1; i < rows.length; i++) {
            const cells = rows[i].getElementsByTagName('td');
            let match = false;
            for (let j = 0; j < cells.length; j++) {
                if (cells[j].textContent.toLowerCase().includes(filter)) {
                    match = true;
                    break;
                }
            }
            rows[i].style.display = match ? '' : 'none';
        }
    }

    // Also search mobile card list if it exists on the page
    const mobileCards = document.querySelectorAll('.mobile-list-view .student-item');
    mobileCards.forEach(card => {
        const text = card.textContent.toLowerCase();
        card.style.setProperty('display', text.includes(filter) ? 'flex' : 'none', 'important');
    });
}

/**
 * Confirm delete action
 */
function confirmDelete(message) {
    return confirm(message || 'Are you sure you want to delete this item?');
}

/**
 * Initialize components that need re-binding after PJAX load
 */
function initDynamicComponents() {
    // Move PHP-rendered flash banners into the unified toast stack
    const stack = getHrdToastStack();
    document.querySelectorAll('.flash-message-banner').forEach(function (toast) {
        if (toast.parentElement !== stack) {
            stack.appendChild(toast);
        }
        if (!toast.dataset.animated) {
            toast.dataset.animated = 'true';
            toast.classList.remove('show');
            void toast.offsetWidth; // Force reflow
            toast.classList.add('show');
            // Auto-dismiss after 5s (success/info) or 8s (danger/warning)
            const isDanger = toast.classList.contains('flash-message-danger') ||
                             toast.classList.contains('flash-message-warning');
            const delay = isDanger ? 8000 : 5000;
            setTimeout(function () {
                _hrdDismissToast(toast);
            }, delay);
        }
    });

    // Bootstrap alert-dismissible still gets auto-closed (non-flash alerts)
    document.querySelectorAll('.alert-dismissible:not(.flash-message-banner)').forEach(function (alert) {
        if (alert.dataset.init) return;
        alert.dataset.init = 'true';
        setTimeout(function () {
            const closeBtn = alert.querySelector('.btn-close');
            if (closeBtn) closeBtn.click();
        }, 5000);
    });

    initAdminMobileTables();
}

/**
 * Add readable labels to admin table cells for the mobile card layout.
 */
function initAdminMobileTables() {
    if (!document.body.classList.contains('admin-area')) return;

    document.querySelectorAll('.table-responsive table').forEach(function (table) {
        if (table.dataset.mobileLabels === 'true') return;

        const headers = Array.from(table.querySelectorAll('thead th')).map(function (th) {
            return th.textContent.replace(/\s+/g, ' ').trim();
        });

        if (!headers.length) return;

        table.querySelectorAll('tbody tr').forEach(function (row) {
            const cells = Array.from(row.children).filter(function (cell) {
                return cell.tagName.toLowerCase() === 'td';
            });

            if (cells.length === 1 && cells[0].hasAttribute('colspan')) {
                cells[0].classList.add('mobile-empty-cell');
                return;
            }

            cells.forEach(function (cell, index) {
                if (!cell.dataset.label && headers[index]) {
                    cell.dataset.label = headers[index];
                }
            });
        });

        table.dataset.mobileLabels = 'true';
    });
}

document.addEventListener('DOMContentLoaded', initDynamicComponents);

/**
 * Common Main JS
 */

document.addEventListener("DOMContentLoaded", function () {
    // Prevent FOUC from sidebar collapsed class
    if (localStorage.getItem('sidebar_collapsed') === 'true') {
        document.documentElement.classList.add('sidebar-collapsed');
        const sb = document.getElementById('sidebar');
        if (sb) {
            sb.classList.add('collapsed');
        }
    }

    // Global Fix: Move all modals to body to prevent Bootstrap z-index layer issues (black shadow bug)
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        if (modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
    });

    // Global Fix: Scroll modal body to top when modal is shown
    document.addEventListener('show.bs.modal', function (event) {
        const modalBody = event.target.querySelector('.modal-body');
        if (modalBody) {
            modalBody.scrollTop = 0;
        }
    });

    // Clear Employee Draft if success feedback exists
    const successFeedback = document.querySelector('.alert-success, .flash-message-success .flash-message-text');
    if (successFeedback) {
        const text = successFeedback.innerText.toLowerCase();
        if (text.includes('successfully') || text.includes('added') || text.includes('saved')) {
            localStorage.removeItem('hris_add_employee_draft');
        }
    }
});

// Export for PJAX use
if (typeof window !== 'undefined') {
    window.initDynamicComponents = initDynamicComponents;
    window.initAdminMobileTables = initAdminMobileTables;
}

// Back to Top Logic
window.addEventListener('scroll', function() {
    const btn = document.getElementById('backToTop');
    if (!btn) return;
    if (window.pageYOffset > 300) {
        btn.style.display = 'flex';
    } else {
        btn.style.display = 'none';
    }
});

function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

/**
 * View full-size profile picture or any image in a shared modal
 */
function viewFullImage(src, name) {
    const modalEl = document.getElementById('imageModal');
    if (!modalEl) return;
    
    const modal = new bootstrap.Modal(modalEl);
    const fullImage = document.getElementById('fullImage');
    const fullImageName = document.getElementById('fullImageName');
    
    if (fullImage) fullImage.src = src;
    if (fullImageName) fullImageName.textContent = name || '';
    
    modal.show();
}

/**
 * Polling and UI utilities for live notification center
 */
window.lastSeenNotifId = 0;

function showLiveToast(title, message, link) {
    // Build a dark-card notification toast matching #connToast style
    const toast = document.createElement('div');
    toast.className = 'flash-message-banner flash-message-info';
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');

    const clickable = !!link;
    const cursorStyle = clickable ? 'cursor:pointer;' : '';

    toast.innerHTML = `
        <div class="flash-message-icon" style="${cursorStyle}" ${clickable ? `onclick="window.location.href='${link}'"` : ''}>
            <i class="fas fa-bell" aria-hidden="true"></i>
        </div>
        <div class="flash-message-copy" style="${cursorStyle}" ${clickable ? `onclick="window.location.href='${link}'"` : ''}>
            <span class="flash-message-app">Notification</span>
            <span class="flash-message-title">${title}</span>
            <span class="flash-message-text">${message}</span>
        </div>
        <div class="hrd-toast__dot" aria-hidden="true"></div>
        <button type="button" class="btn-close" aria-label="Close"></button>
    `;

    // Wire close button
    toast.querySelector('.btn-close').addEventListener('click', function (e) {
        e.stopPropagation();
        _hrdDismissToast(toast);
    });

    // Add to unified stack
    getHrdToastStack().appendChild(toast);
    void toast.offsetWidth;
    toast.classList.add('show');

    // Auto-dismiss after 8s
    setTimeout(function () { _hrdDismissToast(toast); }, 8000);
}

function getNotifIconInfoJS(title) {
    const t = (title || '').toLowerCase();
    if (t.includes('approved') || t.includes('approval') || t.includes('confirmed') || t.includes('endorsed')) {
        return { icon: 'fas fa-check-circle', class: 'approve' };
    }
    if (t.includes('rejected') || t.includes('reject')) {
        return { icon: 'fas fa-times-circle', class: 'reject' };
    }
    if (t.includes('returned') || t.includes('revision')) {
        return { icon: 'fas fa-undo-alt', class: 'return' };
    }
    if (t.includes('evaluation') || t.includes('validation') || t.includes('rating') || t.includes('pending')) {
        return { icon: 'fas fa-clipboard-check', class: 'eval' };
    }
    return { icon: 'fas fa-bell', class: 'system' };
}

function updateNotificationDOM(unreadCount, recentNotifications) {
    const notifBtn = document.getElementById('notificationBtn');
    if (notifBtn) {
        let badge = notifBtn.querySelector('.notification-badge');
        if (unreadCount > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'notification-badge';
                notifBtn.appendChild(badge);
            }
            badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
            badge.style.display = '';
        } else if (badge) {
            badge.remove();
        }
    }

    
    const dropdown = document.querySelector('.notification-dropdown');
    if (dropdown && recentNotifications) {
        // Update header bar pill count
        const pillEl = dropdown.querySelector('.notif-unread-pill');
        if (pillEl) {
            if (unreadCount > 0) {
                pillEl.textContent = unreadCount > 9 ? '9+ unread' : unreadCount + ' unread';
                pillEl.style.display = 'inline-block';
            } else {
                pillEl.style.display = 'none';
            }
        }
        
        let listBody = dropdown.querySelector('.notif-list-body');
        if (!listBody) {
            // Fallback if full HTML shell isn't rendered yet
            listBody = dropdown;
        }
        
        listBody.innerHTML = '';
        
        if (recentNotifications.length === 0) {
            const emptyEl = document.createElement('div');
            emptyEl.className = 'p-4 text-center text-muted';
            emptyEl.style.fontSize = '0.9rem';
            emptyEl.innerHTML = `
                <i class="fas fa-bell-slash d-block mb-2" style="font-size:2rem;opacity:0.3;color:var(--primary-blue);"></i>
                <div class="fw-semibold">You're all caught up!</div>
                <div class="small opacity-75 mt-1">No notifications at the moment</div>
            `;
            listBody.appendChild(emptyEl);
        } else {
            recentNotifications.forEach(notif => {
                const itemEl = document.createElement('a');
                itemEl.href = notif.link || '#';
                itemEl.className = `notification-item ${notif.is_read ? '' : 'unread'}`;
                
                const iconInfo = getNotifIconInfoJS(notif.title);
                const timeStr = formatDateTimeString(notif.created_at);
                
                itemEl.innerHTML = `
                    <div class="notif-avatar ${iconInfo.class}">
                        <i class="${iconInfo.icon}"></i>
                    </div>
                    <div class="notif-content-area">
                        <div class="notif-title">${escapeHtml(notif.title)}</div>
                        <div class="notif-message">${escapeHtml(notif.message)}</div>
                        <div class="notif-time"><i class="far fa-clock me-1"></i>${timeStr}</div>
                    </div>
                    ${notif.is_read ? '' : '<div class="unread-dot" title="Unread"></div>'}
                `;
                listBody.appendChild(itemEl);
            });
        }
    }

    
    if (window.location.pathname.includes('notifications.php')) {
        const totalEl = document.getElementById('statTotal');
        const unreadEl = document.getElementById('statUnread');
        const readEl = document.getElementById('statRead');
        
        if (unreadEl) {
            const currentUnread = parseInt(unreadEl.textContent || '0');
            if (currentUnread !== unreadCount) {
                const totalCount = parseInt(totalEl ? totalEl.textContent || '0' : '0');
                const diff = unreadCount - currentUnread;
                
                unreadEl.textContent = unreadCount;
                if (totalEl) totalEl.textContent = totalCount + diff;
                if (readEl && totalEl) {
                    readEl.textContent = Math.max(0, parseInt(totalEl.textContent) - unreadCount);
                }
            }
        }
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;')
              .replace(/</g, '&lt;')
              .replace(/>/g, '&gt;')
              .replace(/"/g, '&quot;')
              .replace(/'/g, '&#039;');
}

function formatDateTimeString(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr.replace(/-/g, '/'));
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);
    
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
    
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function startNotificationPolling() {
    const baseUrl = (typeof window !== 'undefined' && window.APP_BASE_URL) ? window.APP_BASE_URL : '';
    const context = (typeof window !== 'undefined' && window.NOTIF_CONTEXT) ? window.NOTIF_CONTEXT : 'hr';
    
    function checkNotifications() {
        const url = `${baseUrl}/includes/ajax/get-unread-notifications.php?context=${context}&last_seen_id=${window.lastSeenNotifId || 0}`;
        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const isFirstRun = (!window.lastSeenNotifId || window.lastSeenNotifId === 0);
                
                if (data.new_notifications && data.new_notifications.length > 0) {
                    let maxId = window.lastSeenNotifId || 0;
                    data.new_notifications.forEach(notif => {
                        if (notif.id > maxId) {
                            maxId = notif.id;
                        }
                        if (!isFirstRun) {
                            showLiveToast(notif.title, notif.message, notif.link);
                        }
                    });
                    window.lastSeenNotifId = maxId;
                }
                
                updateNotificationDOM(data.unread_count, data.recent_notifications);
            }
        })
        .catch(err => console.error('Error polling notifications:', err));
    }
    
    checkNotifications();
    setInterval(checkNotifications, 10000);
}

document.addEventListener('DOMContentLoaded', () => {
    const notifBtn = document.getElementById('notificationBtn') || document.querySelector('.employee-bottom-nav');
    if (notifBtn) {
        startNotificationPolling();
    }

    // ── Auto mark-all-read when notification bell dropdown is opened ──────────
    const bellBtn = document.getElementById('notificationBtn');
    if (bellBtn) {
        bellBtn.addEventListener('show.bs.dropdown', function () {
            const badge = bellBtn.querySelector('.notification-badge');
            // Only fire if there are unread notifications (badge is visible)
            if (badge && badge.style.display !== 'none' && badge.textContent.trim() !== '') {
                markAllRead();
            }
        });
    }

    // ── Auto mark-all-read for mobile bottom-nav notification icon ─────────────
    const mobileNotifLink = document.querySelector('.employee-bottom-nav a[href*="notifications.php"]');
    if (mobileNotifLink) {
        mobileNotifLink.addEventListener('click', function () {
            markAllRead();
        });
    }
});

/* ================================================================
   HRD TOAST STACK — shared helpers
   getHrdToastStack()  creates/returns the stacking container
   _hrdDismissToast()  animates a toast out then removes it
   window.alert        override → routes ALL alert() calls through
                       showToast() (if loaded) or the inline fallback
================================================================ */

/**
 * Returns (or creates) the #hrdToastStack container in <body>.
 * All toast types (PHP flash, live notifications, showToast) render here.
 */
function getHrdToastStack() {
    var stack = document.getElementById('hrdToastStack');
    if (!stack) {
        stack = document.createElement('div');
        stack.id = 'hrdToastStack';
        stack.className = 'hrd-toast-stack';
        stack.setAttribute('aria-live', 'polite');
        stack.setAttribute('aria-atomic', 'false');
        stack.setAttribute('role', 'region');
        stack.setAttribute('aria-label', 'Notifications');
        document.body.appendChild(stack);
    }
    return stack;
}

/**
 * Animate a toast out and remove it from the DOM.
 * @param {HTMLElement} toast
 */
function _hrdDismissToast(toast) {
    if (!toast || !toast.parentNode) return;
    toast.classList.remove('show');
    setTimeout(function () {
        if (toast.parentNode) toast.parentNode.removeChild(toast);
    }, 380);
}

/**
 * Override window.alert so every native alert() call is routed
 * through the unified toast system instead of the browser dialog.
 */
(function () {
    var _nativeAlert = window.alert;
    window.alert = function (msg) {
        // Detect type from message text
        var str = String(msg || '');
        var type = 'info';
        var lcStr = str.toLowerCase();
        if (/error|fail|invalid|not found|unable|could not|unexpected/i.test(lcStr)) {
            type = 'error';
        } else if (/warning|caution|careful/i.test(lcStr)) {
            type = 'warning';
        } else if (/success|saved|updated|created|approved|endorsed|confirmed/i.test(lcStr)) {
            type = 'success';
        }

        // Use ep-toast showToast if loaded (Employee portal)
        if (typeof window.showToast === 'function') {
            window.showToast(str, type);
            return;
        }

        // Fallback: build an inline hrd-toast
        var iconMap = { success: '✓', error: '⚠', warning: '⚠', info: 'ℹ' };
        var labelMap = { success: 'Success', error: 'Error', warning: 'Warning', info: 'Notice' };
        var borderMap = { success: 'flash-message-success', error: 'flash-message-danger',
                          warning: 'flash-message-warning', info: 'flash-message-info' };

        var toast = document.createElement('div');
        toast.className = 'flash-message-banner ' + (borderMap[type] || 'flash-message-info');
        toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
        toast.innerHTML =
            '<div class="flash-message-icon">' + (iconMap[type] || 'ℹ') + '</div>' +
            '<div class="flash-message-copy">' +
            '  <span class="flash-message-app">Raquel HRIS</span>' +
            '  <span class="flash-message-title">' + (labelMap[type] || 'Notice') + '</span>' +
            '  <span class="flash-message-text">' + str + '</span>' +
            '</div>' +
            '<div class="hrd-toast__dot" aria-hidden="true"></div>' +
            '<button type="button" class="btn-close" aria-label="Close"></button>';

        var closeBtn = toast.querySelector('.btn-close');
        closeBtn.addEventListener('click', function () { _hrdDismissToast(toast); });

        getHrdToastStack().appendChild(toast);
        void toast.offsetWidth;
        toast.classList.add('show');

        var autoDismissMs = (type === 'error') ? 0 : 5000;
        if (autoDismissMs > 0) {
            setTimeout(function () { _hrdDismissToast(toast); }, autoDismissMs);
        }
    };
})();
