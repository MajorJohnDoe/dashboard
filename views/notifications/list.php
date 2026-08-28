<?php
use Dashboard\Core\NotificationsController;
use Dashboard\Core\Notifications\NotificationTypeLoader;
use Dashboard\Core\Notifications\NotificationRenderer;
use Dashboard\Core\CsrfProtection;

$filter = $_GET['filter'] ?? 'all';
$notifications = $notifications ?? [];
$renderer = $renderer ?? null;
$csrfToken = $csrfToken ?? CsrfProtection::getToken();

if (!$renderer) {
    NotificationTypeLoader::setDatabase($db);
    NotificationTypeLoader::load();
    $renderer = new NotificationRenderer($db);
}

$controller = new NotificationsController($db, $user, null);
?>

<div id="notifications-list-inner" hx-get="/notifications/list/<?php echo $filter; ?>" hx-trigger="notificationsUpdate from:body" hx-swap="outerHTML">
    <?php if ($notifications && count($notifications) > 0): ?>
        <?php foreach ($notifications as $notification): ?>
            <?php 
            $data = json_decode($notification['data'], true) ?? [];
            $data['notification_type'] = $notification['type'];
            $data['notification_id'] = $notification['id'];
            $data['user_id'] = $notification['user_id'];
            $isUnread = !$notification['is_read'];
            
            $renderer->setNotification($notification['type'], $data);
            $notificationTitle = $renderer->getTitle();
            $notificationMessage = $renderer->getMessage();
            
            $avatarUrl = null;
            $isSystem = $renderer->isSystem();
            $fromUserId = $renderer->getFromUserId();
            
            if ($fromUserId) {
                $fromUser = $controller->getUserData($fromUserId);
                if ($fromUser) {
                    $avatarUrl = $fromUser['user_avatar'] ?? null;
                    $isSystem = false;
                }
            }
            
            $iconUrl = $renderer->getIcon();
            $actions = $renderer->getActions();
            ?>
            
            <div class="notification-item <?php echo $isUnread ? 'unread' : ''; ?>" 
                 data-notification-id="<?php echo $notification['id']; ?>"
                 data-csrf-token="<?php echo $csrfToken; ?>">
                
                <div class="notification-avatar <?php echo $isSystem ? 'system' : ''; ?>">
                    <?php if ($isSystem || empty($avatarUrl)): ?>
                        <img src="<?php echo $iconUrl !== null ? $iconUrl : '/assets/img/icon_settings.svg'; ?>" alt="System">
                    <?php else: ?>
                        <img src="<?php echo (!empty($avatarUrl) ? $avatarUrl : '/assets/img/default_profile.jpg'); ?>" alt="User">
                    <?php endif; ?>
                </div>
                
                <div class="notification-body">
                    <div class="notification-title">
                        <?php echo htmlspecialchars($notificationTitle); ?>
                    </div>
                    <div class="notification-message">
                        <?php echo htmlspecialchars($notificationMessage); ?>
                    </div>
                    <div class="notification-meta">
                        <span><?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?></span>
                        <?php if ($isUnread): ?>
                            <div class="notification-unread-dot"></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Expandable actions row -->
            <div class="notification-actions">
                <?php if (!empty($actions)): ?>
                    <?php foreach ($actions as $action): ?>
                        <?php 
                        $actionData = $action['data'] ?? [];
                        $actionData['csrf_token'] = $csrfToken;
                        ?>
                        <button class="btn btn-small <?php echo $action['class'] ?? 'btn-blue'; ?>"
                                hx-<?php echo strtolower($action['method'] ?? 'get'); ?>="<?php echo $action['url']; ?>"
                                hx-vals='<?php echo json_encode($actionData); ?>'
                                hx-swap="none">
                            <?php echo htmlspecialchars($action['label']); ?>
                        </button>
                    <?php endforeach; ?>
                <?php else: ?>
                    <button class="btn btn-small btn-red"
                            hx-delete="/notifications/delete"
                            hx-vals='{"notification_id": "<?php echo $notification['id']; ?>", "csrf_token": "<?php echo $csrfToken; ?>"}'
                            hx-swap="none">
                        Delete
                    </button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="notification-empty">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
            <p>No notifications</p>
        </div>
    <?php endif; ?>
</div>
