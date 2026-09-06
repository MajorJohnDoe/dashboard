<?php
namespace Dashboard\Core\Notifications\Handlers;

use Dashboard\Core\Sanitize;

use Dashboard\Core\Notifications\AbstractNotificationType;
use Dashboard\Core\Notifications\NotificationTypeHandlerInterface;
use Dashboard\Core\Interfaces\DatabaseInterface;

class ChatNotificationType extends AbstractNotificationType implements NotificationTypeHandlerInterface
{
    private ?DatabaseInterface $db = null;

    public function __construct(?DatabaseInterface $db = null)
    {
        $this->db = $db;
    }

    public function setDatabase(DatabaseInterface $db): void
    {
        $this->db = $db;
    }

    public static function getHandledTypes(): array
    {
        return [
            'chat_message',
            'chat_mention',
            'chat_room_invite'
        ];
    }

    public function getTitle(array $data): string
    {
        $type = $data['notification_type'] ?? $this->getType();
        
        return match ($type) {
            'chat_message' => 'New Message',
            'chat_mention' => 'You were mentioned',
            'chat_room_invite' => 'Chat Room Invitation',
            default => 'Chat Notification',
        };
    }

    public function getMessage(array $data): string
    {
        $type = $data['notification_type'] ?? $this->getType();
        
        if ($type === 'chat_room_invite') {
            $roomName = $this->getRoomName($data['room_id'] ?? 0);
            return sprintf('You were invited to chat room "%s"', Sanitize::e($roomName));
        }

        $senderName = $data['sender_name'] ?? 'Someone';
        
        if ($type === 'chat_mention') {
            return sprintf('%s mentioned you in a message', Sanitize::e($senderName));
        }

        $preview = $data['message_preview'] ?? '';
        if (strlen($preview) > 50) {
            $preview = substr($preview, 0, 47) . '...';
        }
        
        return sprintf('%s: %s', Sanitize::e($senderName), Sanitize::e($preview));
    }

    public function getIcon(array $data): ?string
    {
        $type = $data['notification_type'] ?? $this->getType();
        
        return match ($type) {
            'chat_room_invite' => '/assets/img/icon_group.svg',
            default => '/assets/img/icon_chat.svg',
        };
    }

    public function isSystem(array $data): bool
    {
        return false;
    }

    public function getFromUserId(array $data): ?int
    {
        return $data['from_user_id'] ?? $data['sender_id'] ?? null;
    }

    public function getActionUrl(array $data): ?string
    {
        $type = $data['notification_type'] ?? $this->getType();
        
        if ($type === 'chat_room_invite') {
            return '/chat/room/' . ($data['room_id'] ?? 0);
        }
        
        if (!empty($data['room_id'])) {
            return '/chat/room/' . $data['room_id'];
        }
        
        if (!empty($data['sender_id'])) {
            return '/chat/user/' . $data['sender_id'];
        }
        
        return '/chat';
    }

    public function getActions(array $data): array
    {
        $type = $data['notification_type'] ?? $this->getType();
        $actions = [];
        
        if ($type === 'chat_room_invite') {
            $actions[] = [
                'label' => 'Accept',
                'url' => '/notifications/chat/accept-invite',
                'method' => 'POST',
                'class' => 'btn-blue',
                'data' => ['room_id' => $data['room_id'] ?? 0]
            ];
            $actions[] = [
                'label' => 'Decline',
                'url' => '/notifications/chat/decline-invite', 
                'method' => 'POST',
                'class' => 'btn-red',
                'data' => ['room_id' => $data['room_id'] ?? 0]
            ];
        } else {
            $actions[] = [
                'label' => 'Reply',
                'url' => $this->getActionUrl($data),
                'method' => 'GET',
                'class' => 'btn-blue'
            ];
        }
        
        return $actions;
    }

    private function getRoomName(int $roomId): string
    {
        if (!$this->db || $roomId === 0) {
            return 'Unknown Room';
        }

        $sql = "SELECT name FROM chat_rooms WHERE id = ? LIMIT 1";
        $result = $this->db->q($sql, "i", $roomId);
        
        return $result[0]['name'] ?? 'Unknown Room';
    }
}
