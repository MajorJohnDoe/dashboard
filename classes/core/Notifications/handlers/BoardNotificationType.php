<?php
namespace Dashboard\Core\Notifications\Handlers;

use Dashboard\Core\Notifications\AbstractNotificationType;
use Dashboard\Core\Notifications\NotificationTypeHandlerInterface;
use Dashboard\Core\Interfaces\DatabaseInterface;

class BoardNotificationType extends AbstractNotificationType implements NotificationTypeHandlerInterface
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
            'board_invite',
            'board_accept', 
            'board_decline',
            'board_access_changed'
        ];
    }

    public function getTitle(array $data): string
    {
        $type = $data['notification_type'] ?? $this->getType();
        
        return match ($type) {
            'board_invite' => 'Board Invitation',
            'board_accept' => 'Invitation Accepted',
            'board_decline' => 'Invitation Declined',
            'board_access_changed' => 'Access Changed',
            default => 'Notification',
        };
    }

    public function getMessage(array $data): string
    {
        $type = $data['notification_type'] ?? $this->getType();
        $boardName = $this->getBoardName($data['board_id'] ?? 0);
        
        $accessLevel = $data['access_level'] ?? 'read';
        
        return match ($type) {
            'board_invite' => sprintf(
                'You\'ve been invited to "%s" with %s access',
                $this->htmlspecialchars($boardName),
                $this->htmlspecialchars($accessLevel)
            ),
            'board_accept' => 'Your board invitation was accepted',
            'board_decline' => 'Your board invitation was declined',
            'board_access_changed' => sprintf(
                'Your access to "%s" has been changed to %s',
                $this->htmlspecialchars($boardName),
                $this->htmlspecialchars($accessLevel)
            ),
            default => $data['message'] ?? '',
        };
    }

    public function getFromUserId(array $data): ?int
    {
        if (!empty($data['from_user_id'])) {
            return (int) $data['from_user_id'];
        }
        if (!empty($data['inviter_id'])) {
            return (int) $data['inviter_id'];
        }
        if (!empty($data['user_id'])) {
            return (int) $data['user_id'];
        }
        return null;
    }

    public function getIcon(array $data): ?string
    {
        return '/assets/img/icon_settings.svg';
    }

    public function getActions(array $data): array
    {
        $type = $data['notification_type'] ?? $this->getType();
        
        if ($type === 'board_invite') {
            $boardId = $data['board_id'] ?? 0;
            $userId = $data['user_id'] ?? null;
            
            $invitationStatus = $this->getInvitationStatus($boardId, $userId);
            
            if ($invitationStatus === 'pending') {
                return [
                    [
                        'label' => 'Accept',
                        'url' => '/notifications/accept',
                        'method' => 'POST',
                        'class' => 'btn-blue',
                        'data' => ['board_id' => $boardId]
                    ],
                    [
                        'label' => 'Decline', 
                        'url' => '/notifications/decline',
                        'method' => 'POST',
                        'class' => 'btn-red',
                        'data' => ['board_id' => $boardId]
                    ]
                ];
            }
            
            return [
                [
                    'label' => 'Delete',
                    'url' => '/notifications/delete',
                    'method' => 'DELETE',
                    'class' => 'btn-light-gray',
                    'data' => ['notification_id' => $data['notification_id'] ?? 0]
                ]
            ];
        }
        
        return [];
    }

    private function getBoardName(int $boardId): string
    {
        if (!$this->db || $boardId === 0) {
            return 'Unknown board';
        }

        $sql = "SELECT tm_name FROM tm_board WHERE id = ? LIMIT 1";
        $result = $this->db->q($sql, "i", $boardId);
        
        return $result[0]['tm_name'] ?? 'Unknown board';
    }

    private function getInvitationStatus(int $boardId, ?int $userId): string
    {
        if (!$this->db || $boardId === 0 || $userId === null) {
            return 'pending';
        }

        $sql = "SELECT status FROM board_shares WHERE board_id = ? AND user_id = ? LIMIT 1";
        $result = $this->db->q($sql, "ii", $boardId, $userId);
        
        if (empty($result)) {
            return 'pending';
        }

        return $result[0]['status'] ?? 'pending';
    }
}
