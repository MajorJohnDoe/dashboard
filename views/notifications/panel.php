<?php
use Dashboard\Core\CsrfProtection;

$csrfToken = CsrfProtection::getToken();
$unreadCount = $unreadCount ?? 0;
?>

<div id="panel-modal-notifications" class="panel-modal" data-csrf-token="<?php echo $csrfToken; ?>">
    <div class="panel-modal-header">
        <h3 class="panel-modal-title">Notifications</h3>
        <button class="panel-modal-close" title="Close">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
    </div>
    
    <div class="panel-modal-tabs">
        <button class="panel-modal-tab active" data-filter="all">View all</button>
        <button class="panel-modal-tab" data-filter="unread">View unread <?php echo $unreadCount > 0 ? "(" . $unreadCount . ")" : ''; ?></button>
    </div>
    
    <div class="panel-modal-content" id="notifications-content">
        <?php include 'list.php'; ?>
    </div>
    
    <div class="panel-modal-footer">
        <button class="btn-text" hx-post="/notifications/mark-all-read" hx-vals='{"csrf_token": "<?php echo $csrfToken; ?>"}' hx-swap="none">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
            Mark all as read
        </button>
    </div>
</div>
