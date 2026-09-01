// Check for unread notifications
function checkUnreadNotifications() {
    fetch('/notifications/check')
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

function getCsrfToken() {
    const panelModal = document.getElementById('panel-modal-notifications');
    if (panelModal && panelModal.dataset.csrfToken) return panelModal.dataset.csrfToken;
    
    // Try from meta tag
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.content;
    
    return '';
}

// Add CSRF token to all HTMX requests for notifications
document.addEventListener('htmx:configRequest', function(event) {
    const path = event.detail.path;
    if (path && path.includes('/notifications/')) {
        const csrfToken = getCsrfToken();
        if (csrfToken) {
            event.detail.headers['X-CSRF-Token'] = csrfToken;
        }
    }
});

// Start checking for notifications
document.addEventListener('DOMContentLoaded', () => {
    // Initial check
    checkUnreadNotifications();
    
    // Check every 30 seconds
    setInterval(checkUnreadNotifications, 30000);
    
    // Listen for refreshNotificationsDialog to update the tab count
    document.body.addEventListener(HTMX_EVENTS.REFRESH_NOTIFICATIONS_DIALOG, () => {
        fetch('/notifications/check')
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

    // Only proceed if we have a valid path that requires notification updates
    if (shouldUpdate || 
        event.detail.pathInfo.requestPath.startsWith('/board/share') || 
        event.detail.pathInfo.requestPath.startsWith('/notifications')) {
        
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
                
                // Get the inner list container
                const listElement = listContainer.querySelector('.notifications-list');
                
                htmx.ajax('GET', '/notifications/list', {
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
