<?php
namespace Dashboard\Core;

use Dashboard\Core\Interfaces\DatabaseInterface;

class Notifications
{
    private $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    // Get unread notifications count for a user
    public function getUnreadCount($userId)
    {
        $sql = "SELECT COUNT(*) as unread_count 
                FROM `notifications` 
                WHERE `user_id` = ? AND `is_read` = 0";
        $result = $this->db->q($sql, "i", $userId);
        return intval($result[0]['unread_count'] ?? 0);
    }

    // Get all notifications for a user
    public function getAllNotifications($userId)
    {
        $sql = "SELECT * FROM `notifications` 
                WHERE `user_id` = ? 
                ORDER BY `created_at` DESC";
        return $this->db->q($sql, "i", $userId);
    }

    // Mark a notification as read
    public function markAsRead($notificationId, $userId)
    {
        // First verify the notification belongs to the user
        if (!$this->validateOwnership($notificationId, $userId)) {
            return false;
        }

        $sql = "UPDATE `notifications` 
                SET `is_read` = 1 
                WHERE `id` = ? AND `user_id` = ?";
        return $this->db->q($sql, "ii", $notificationId, $userId);
    }

    // Delete a notification
    public function deleteNotification($notificationId, $userId)
    {
        // First verify the notification belongs to the user
        if (!$this->validateOwnership($notificationId, $userId)) {
            return false;
        }

        $sql = "DELETE FROM `notifications` 
                WHERE `id` = ? AND `user_id` = ?";
        return $this->db->q($sql, "ii", $notificationId, $userId);
    }

    // Delete a board invitation notification by board_id and user_id
    public function deleteBoardInviteNotification(int $boardId, int $userId): bool
    {
        $sql = "DELETE FROM `notifications` 
                WHERE `user_id` = ? AND `type` = 'board_invite' AND JSON_EXTRACT(`data`, '$.board_id') = ?";
        return $this->db->q($sql, "ii", $userId, $boardId);
    }

    // Add a new notification
    public function addNotification($userId, $type, $data)
    {
        $sql = "INSERT INTO `notifications` (`user_id`, `type`, `data`) 
                VALUES (?, ?, ?)";
        return $this->db->q($sql, "iss", $userId, $type, json_encode($data));
    }

    // Mark all notifications as read for a user
    public function markAllAsRead($userId)
    {
        $sql = "UPDATE `notifications` 
                SET `is_read` = 1 
                WHERE `user_id` = ? AND `is_read` = 0";
        return $this->db->q($sql, "i", $userId);
    }

    // Get only unread notifications for a user
    public function getUnreadNotifications($userId)
    {
        $sql = "SELECT * FROM `notifications` 
                WHERE `user_id` = ? AND `is_read` = 0
                ORDER BY `created_at` DESC";
        return $this->db->q($sql, "i", $userId);
    }

    // Validate notification ownership
    private function validateOwnership($notificationId, $userId)
    {
        $sql = "SELECT 1 FROM `notifications` 
                WHERE `id` = ? AND `user_id` = ? 
                LIMIT 1";
        $result = $this->db->q($sql, "ii", $notificationId, $userId);
        return !empty($result);
    }
}
?>
