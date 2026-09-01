<?php
namespace Dashboard\Core;

use Dashboard\Core\Interfaces\DatabaseInterface;
use Dashboard\Core\Notifications\NotificationTypeLoader;
use Dashboard\Core\Notifications\NotificationRenderer;
use Dashboard\Taskboard\BoardController;

class NotificationsController
{
    private $db;
    private $user;
    private $view;
    private $notifications;

    public function __construct(DatabaseInterface $db, User $user, View $view = null)
    {
        $this->db = $db;
        $this->user = $user;
        $this->view = $view;
        $this->notifications = new Notifications($db);
    }

    /**
     * GET /notifications - Show notification panel
     */
    public function panel()
    {
        // Get all notifications for initial display
        $notifications = $this->notifications->getAllNotifications($this->user->getUserId());
        
        // Load notification types and create renderer
        NotificationTypeLoader::setDatabase($this->db);
        NotificationTypeLoader::load();
        $renderer = new NotificationRenderer($this->db);
        
        return [
            'view' => 'notifications/panel',
            'data' => [
                'unreadCount' => $this->notifications->getUnreadCount($this->user->getUserId()),
                'notifications' => $notifications,
                'renderer' => $renderer,
                'csrfToken' => CsrfProtection::getToken()
            ]
        ];
    }

    /**
     * GET /notifications/list or /notifications/list/:filter
     */
    public function list()
    {
        $filter = $_GET['filter'] ?? 'all';
        $notifications = ($filter === 'unread')
            ? $this->notifications->getUnreadNotifications($this->user->getUserId())
            : $this->notifications->getAllNotifications($this->user->getUserId());

        // Load notification types
        NotificationTypeLoader::setDatabase($this->db);
        NotificationTypeLoader::load();
        $renderer = new NotificationRenderer($this->db);

        return [
            'view' => 'notifications/list',
            'data' => [
                'notifications' => $notifications,
                'renderer' => $renderer,
                'csrfToken' => CsrfProtection::getToken()
            ]
        ];
    }

    /**
     * GET /notifications/check - Unread count API
     */
    public function check()
    {
        $count = $this->notifications->getUnreadCount($this->user->getUserId());
        return ['unread_count' => $count];
    }

    /**
     * POST /notifications/mark-read
     */
    public function markRead()
    {
        // CSRF is enforced centrally by CsrfMiddleware via the Router
        $notificationId = $_POST['notification_id'] ?? null;
        if (!$notificationId) {
            triggerResponse([
                'globalMessagePopupUpdate' => ['type' => 'error', 'message' => 'Invalid notification ID']
            ]);
            return;
        }

        $result = $this->notifications->markAsRead($notificationId, $this->user->getUserId());

        if ($result) {
            triggerResponse([
                'notificationsUpdate' => true,
                'refreshNotificationsDialog' => true,
                'globalMessagePopupUpdate' => ['type' => 'success', 'message' => 'Notification marked as read']
            ]);
        } else {
            triggerResponse([
                'globalMessagePopupUpdate' => ['type' => 'error', 'message' => 'Failed to mark notification as read']
            ]);
        }
    }

    /**
     * POST /notifications/mark-all-read
     */
    public function markAllRead()
    {
        // CSRF is enforced centrally by CsrfMiddleware via the Router
        $result = $this->notifications->markAllAsRead($this->user->getUserId());

        if ($result) {
            triggerResponse([
                'notificationsUpdate' => true,
                'refreshNotificationsDialog' => true,
                'globalMessagePopupUpdate' => ['type' => 'success', 'message' => 'All notifications marked as read']
            ]);
        } else {
            triggerResponse([
                'globalMessagePopupUpdate' => ['type' => 'error', 'message' => 'Failed to mark notifications as read']
            ]);
        }
    }

    /**
     * DELETE /notifications/delete
     */
    public function delete()
    {
        $deleteData = [];
        
        // For DELETE requests, parse the body to get the notification ID
        if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            parse_str(file_get_contents("php://input"), $deleteData);
        }
        
        // CSRF is enforced centrally by CsrfMiddleware via the Router
        $notificationId = $_POST['notification_id'] ?? ($deleteData['notification_id'] ?? null);
        $result = $this->notifications->deleteNotification($notificationId, $this->user->getUserId());

        if ($result) {
            triggerResponse([
                'notificationsUpdate' => true,
                'refreshNotificationsDialog' => true,
                'globalMessagePopupUpdate' => ['type' => 'success', 'message' => 'Notification deleted']
            ]);
        } else {
            triggerResponse([
                'globalMessagePopupUpdate' => ['type' => 'error', 'message' => 'Failed to delete notification']
            ]);
        }
    }

    /**
     * POST /notifications/accept - Accept board invitation
     */
    public function accept()
    {
        // CSRF is enforced centrally by CsrfMiddleware via the Router
        $boardId = $_POST['board_id'] ?? null;
        if (!$boardId) {
            triggerResponse([
                'globalMessagePopupUpdate' => ['type' => 'error', 'message' => 'Missing board ID']
            ]);
            return;
        }

        $boardController = new BoardController($this->db, $this->user);
        $result = $boardController->handleInvitationAccept();
        triggerResponse($result);
    }

    /**
     * POST /notifications/decline - Decline board invitation
     */
    public function decline()
    {
        // CSRF is enforced centrally by CsrfMiddleware via the Router
        $boardId = $_POST['board_id'] ?? null;
        if (!$boardId) {
            triggerResponse([
                'globalMessagePopupUpdate' => ['type' => 'error', 'message' => 'Missing board ID']
            ]);
            return;
        }

        $boardController = new BoardController($this->db, $this->user);
        $result = $boardController->handleInvitationDecline();
        triggerResponse($result);
    }

    // Legacy methods for backward compatibility
    public function handleCheck()
    {
        return $this->check();
    }

    public function handleList($filter = 'all')
    {
        $_GET['filter'] = $filter;
        $result = $this->list();
        return $result['data']['notifications'] ?? [];
    }

    public function getUnreadCount()
    {
        return $this->notifications->getUnreadCount($this->user->getUserId());
    }

    public function getUserData($userId)
    {
        $sql = "SELECT user_id, user_username, user_avatar FROM `user` WHERE `user_id` = ? LIMIT 1";
        $result = $this->db->q($sql, "i", $userId);
        return $result ? $result[0] : null;
    }

    public function handleMarkAsRead($notificationId = null)
    {
        $_POST['notification_id'] = $notificationId ?? $_POST['notification_id'] ?? null;
        return $this->markRead();
    }

    public function handleDelete($notificationId = null)
    {
        $_POST['notification_id'] = $notificationId ?? $_POST['notification_id'] ?? null;
        return $this->delete();
    }

    public function handleMarkAllAsRead()
    {
        return $this->markAllRead();
    }

    public function addNotification($type, $data)
    {
        return $this->notifications->addNotification($this->user->getUserId(), $type, $data);
    }

    public function addNotificationForUser($userId, $type, $data)
    {
        return $this->notifications->addNotification($userId, $type, $data);
    }

    protected function triggerResponse($response)
    {
        if ($response['success']) {
            triggerResponse([
                HtmxEvents::NOTIFICATIONS_UPDATE => true,
                HtmxEvents::GLOBAL_MESSAGE => [
                    'type' => 'success',
                    'message' => $response['message']
                ]
            ]);
        } else {
            triggerResponse([
                HtmxEvents::GLOBAL_MESSAGE => [
                    'type' => 'error',
                    'message' => $response['message']
                ]
            ]);
        }
    }

    public function getBoardData($boardId)
    {
        $sql = "SELECT * FROM `tm_board` WHERE `id` = ? LIMIT 1";
        $result = $this->db->q($sql, "i", $boardId);
        return $result ? $result[0] : null;
    }
}
