<?php
namespace Dashboard\Core\Notifications;

use Dashboard\Core\Interfaces\DatabaseInterface;

/**
 * Service layer for creating notifications.
 * 
 * Provides methods to create different types of notifications.
 * This is the main entry point for creating notifications in controllers.
 * 
 * Usage:
 *   $service = new NotificationService($db);
 *   $service->notifyBoardInvite($userId, $boardId, 'write', $fromUserId);
 *   $service->notifyChatMessage($userId, $senderId, 'John', 'Hello!', $roomId);
 *   $service->notifyTaskDueSoon($userId, $taskId, 'Finish report', 'tomorrow');
 */
class NotificationService
{
    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Create a generic notification.
     * @param int $userId The user to notify
     * @param string $type Notification type (must be registered in NotificationTypeLoader)
     * @param array $data Notification data (stored as JSON)
     * @return int The new notification ID
     */
    public function notify(int $userId, string $type, array $data): int
    {
        $sql = "INSERT INTO `notifications` (`user_id`, `type`, `data`) VALUES (?, ?, ?)";
        $this->db->q($sql, "iss", $userId, $type, json_encode($data));
        
        return (int) $this->db->lastInsertId();
    }

    /**
     * Create the same notification for multiple users.
     * @param array $userIds Array of user IDs to notify
     * @param string $type Notification type
     * @param array $data Notification data
     * @return array Array of inserted notification IDs
     */
    public function notifyMultiple(array $userIds, string $type, array $data): array
    {
        $insertedIds = [];
        
        foreach ($userIds as $userId) {
            $insertedIds[] = $this->notify($userId, $type, $data);
        }
        
        return $insertedIds;
    }

    /**
     * Notify user of a board invitation.
     * @param int $userId User being invited
     * @param int $boardId Board ID
     * @param string $accessLevel 'read' or 'write'
     * @param int $fromUserId User who sent the invitation
     */
    public function notifyBoardInvite(int $userId, int $boardId, string $accessLevel, int $fromUserId): int
    {
        return $this->notify($userId, 'board_invite', [
            'board_id' => $boardId,
            'access_level' => $accessLevel,
            'from_user_id' => $fromUserId
        ]);
    }

    /**
     * Notify inviter that invitation was accepted.
     * @param int $userId User who was notified
     * @param int $boardId Board ID
     * @param int $fromUserId User who accepted
     */
    public function notifyBoardAccept(int $userId, int $boardId, int $fromUserId): int
    {
        return $this->notify($userId, 'board_accept', [
            'board_id' => $boardId,
            'from_user_id' => $fromUserId
        ]);
    }

    /**
     * Notify inviter that invitation was declined.
     * @param int $userId User who was notified
     * @param int $boardId Board ID
     * @param int $fromUserId User who declined
     */
    public function notifyBoardDecline(int $userId, int $boardId, int $fromUserId): int
    {
        return $this->notify($userId, 'board_decline', [
            'board_id' => $boardId,
            'from_user_id' => $fromUserId
        ]);
    }

    /**
     * Notify user that their board access level changed.
     * @param int $userId User whose access changed
     * @param int $boardId Board ID
     * @param string $accessLevel New access level
     */
    public function notifyBoardAccessChanged(int $userId, int $boardId, string $accessLevel): int
    {
        return $this->notify($userId, 'board_access_changed', [
            'board_id' => $boardId,
            'access_level' => $accessLevel
        ]);
    }

    /**
     * Notify user of a new chat message.
     * @param int $userId User to notify
     * @param int $senderId Sender's user ID
     * @param string $senderName Sender's display name
     * @param string $preview Message preview (truncated)
     * @param int|null $roomId Optional chat room ID
     */
    public function notifyChatMessage(int $userId, int $senderId, string $senderName, string $preview, ?int $roomId = null): int
    {
        return $this->notify($userId, 'chat_message', [
            'from_user_id' => $senderId,
            'sender_id' => $senderId,
            'sender_name' => $senderName,
            'message_preview' => $preview,
            'room_id' => $roomId
        ]);
    }

    /**
     * Notify user they were mentioned in chat.
     * @param int $userId User mentioned
     * @param int $senderId User who mentioned them
     * @param string $senderName Sender's display name
     * @param string $message The message content
     * @param int $roomId Chat room ID
     */
    public function notifyChatMention(int $userId, int $senderId, string $senderName, string $message, int $roomId): int
    {
        return $this->notify($userId, 'chat_mention', [
            'from_user_id' => $senderId,
            'sender_id' => $senderId,
            'sender_name' => $senderName,
            'message_preview' => $message,
            'room_id' => $roomId
        ]);
    }

    /**
     * Notify user of chat room invitation.
     * @param int $userId User invited
     * @param int $roomId Chat room ID
     * @param string $roomName Chat room name
     * @param int $fromUserId User who sent invite
     */
    public function notifyChatRoomInvite(int $userId, int $roomId, string $roomName, int $fromUserId): int
    {
        return $this->notify($userId, 'chat_room_invite', [
            'room_id' => $roomId,
            'room_name' => $roomName,
            'from_user_id' => $fromUserId
        ]);
    }

    /**
     * Notify user task is due soon.
     * @param int $userId User to notify
     * @param int $taskId Task ID
     * @param string $taskTitle Task title
     * @param string $dueDate Due date string
     */
    public function notifyTaskDueSoon(int $userId, int $taskId, string $taskTitle, string $dueDate): int
    {
        return $this->notify($userId, 'task_due_soon', [
            'task_id' => $taskId,
            'task_title' => $taskTitle,
            'due_date' => $dueDate
        ]);
    }

    /**
     * Notify user task is overdue.
     * @param int $userId User to notify
     * @param int $taskId Task ID
     * @param string $taskTitle Task title
     */
    public function notifyTaskOverdue(int $userId, int $taskId, string $taskTitle): int
    {
        return $this->notify($userId, 'task_overdue', [
            'task_id' => $taskId,
            'task_title' => $taskTitle
        ]);
    }

    /**
     * Notify user they were assigned to a task.
     * @param int $userId Assigned user
     * @param int $taskId Task ID
     * @param string $taskTitle Task title
     * @param int $assignedById User who assigned
     * @param string|null $assignedByName Assigner's name
     */
    public function notifyTaskAssigned(int $userId, int $taskId, string $taskTitle, int $assignedById, ?string $assignedByName = null): int
    {
        return $this->notify($userId, 'task_assigned', [
            'task_id' => $taskId,
            'task_title' => $taskTitle,
            'assigned_by_id' => $assignedById,
            'assigned_by' => $assignedByName
        ]);
    }

    /**
     * Notify user their task was completed.
     * @param int $userId User to notify
     * @param int $taskId Task ID
     * @param string $taskTitle Task title
     * @param int $completedById User who completed
     */
    public function notifyTaskCompleted(int $userId, int $taskId, string $taskTitle, int $completedById): int
    {
        return $this->notify($userId, 'task_completed', [
            'task_id' => $taskId,
            'task_title' => $taskTitle,
            'from_user_id' => $completedById
        ]);
    }

    /**
     * Notify user of comment on their task.
     * @param int $userId User to notify
     * @param int $taskId Task ID
     * @param string $taskTitle Task title
     * @param int $commenterId Commenter user ID
     * @param string $commenterName Commenter name
     */
    public function notifyTaskComment(int $userId, int $taskId, string $taskTitle, int $commenterId, string $commenterName): int
    {
        return $this->notify($userId, 'task_comment', [
            'task_id' => $taskId,
            'task_title' => $taskTitle,
            'commenter_id' => $commenterId,
            'commenter_name' => $commenterName
        ]);
    }
}
