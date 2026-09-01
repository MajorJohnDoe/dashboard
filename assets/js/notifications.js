/**
 * Notifications JavaScript
 * Depends on http.js (Csrf, APP_ROUTES) and core.js (HTMX_EVENTS).
 */

// Check for unread notifications
function checkUnreadNotifications() {
    fetch(APP_ROUTES.NOTIFICATIONS_CHECK)
        .then(response => response.json())
        .then(data => {
            const notificationBtn = document.querySelector('.notifications-btn');
            if (notificationBtn) {
                if (data.unread_count > 0) {
                    notificationBtn.setAttribute('data-unread', 'true');
                } else {
                    notificationBtn.removeAttribute('data-unread');
                }
            }
        })
        .catch(error => console.error('Error checking notifications:', error));
}

// Add CSRF token to all HTMX requests for notifications
document.addEventListener('htmx:configRequest', function(event) {
    const path = event.detail.path;
    if (path && path.includes('/notifications/')) {
        const csrfToken = Csrf.getToken();
        if (csrfToken) {
            event.detail.headers['X-CSRF-Token'] = csrfToken;
        }
    }
});

// Start checking for notifications
document.addEventListener('DOMContentLoaded', () => {
    // Initial check
    checkUnreadNotifications();

    // Check every 30 seconds, but only when the tab is visible
    setInterval(() => {
        if (document.visibilityState === 'visible') {
            checkUnreadNotifications();
        }
    }, 30000);

    // Listen for refreshNotificationsDialog to update the tab count
    document.body.addEventListener(HTMX_EVENTS.REFRESH_NOTIFICATIONS_DIALOG, () => {
        fetch(APP_ROUTES.NOTIFICATIONS_CHECK)
            .then(response => response.json())
            .then(data => {
                const modal = document.getElementById('panel-modal-notifications');
                if (!modal) return;

                const unreadTab = modal.querySelector('.panel-modal-tab[data-filter="unread"]');
                if (unreadTab) {
                    if (data.unread_count > 0) {
                        unreadTab.textContent = `View unread (${data.unread_count})`;
                    } else {
                        unreadTab.textContent = 'View unread';
                    }
                }
            })
            .catch(err => console.error('Error updating notification tab:', err));
    });
});

document.addEventListener('htmx:afterRequest', (event) => {
    let jsonResponse = null;
    const response = event.detail.xhr.response;

    // Only try to parse if response looks like JSON (starts with { or [)
    if (response && typeof response === 'string' && /^[\[{]/.test(response.trim())) {
        try {
            jsonResponse = JSON.parse(response);
        } catch (err) {
            console.error("Error parsing JSON:", err);
        }
    }

    const shouldUpdate = jsonResponse && jsonResponse.notificationsUpdate;

    // Guard pathInfo - it is not present for all htmx:afterRequest events
    // (e.g. htmx.ajax() calls or certain detail shapes).
    const requestPath = event.detail.pathInfo?.requestPath || '';
    const isNotificationPath = requestPath.startsWith(APP_ROUTES.BOARD_SHARE) ||
        requestPath.startsWith('/notifications');

    // Only proceed if we have a valid path that requires notification updates
    if (shouldUpdate || isNotificationPath) {

        // Update unread count badge
        checkUnreadNotifications();

        // If we're in the notifications dialog, refresh the list
        const listContainer = document.getElementById('notifications-list-container');
        const notificationsDialog = document.getElementById('notifications-dialog');

        // Only attempt to refresh if both container and dialog exist and are attached to DOM
        if (listContainer &&
            notificationsDialog &&
            document.body.contains(notificationsDialog)) {

            // Prevent multiple simultaneous updates
            if (!listContainer.dataset.updating) {
                listContainer.dataset.updating = 'true';

                htmx.ajax('GET', APP_ROUTES.NOTIFICATIONS_LIST, {
                    target: '.notifications-list',
                    swap: 'innerHTML',
                    headers: {
                        'HX-Request': 'true'
                    }
                }).then(() => {
                    delete listContainer.dataset.updating;
                }).catch(() => {
                    delete listContainer.dataset.updating;
                });
            }
        }
    }
});
