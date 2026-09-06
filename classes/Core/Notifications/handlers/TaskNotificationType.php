<?php
namespace Dashboard\Core\Notifications\Handlers;

use Dashboard\Core\Sanitize;

use Dashboard\Core\Notifications\AbstractNotificationType;
use Dashboard\Core\Notifications\NotificationTypeHandlerInterface;
use Dashboard\Core\Interfaces\DatabaseInterface;

class TaskNotificationType extends AbstractNotificationType implements NotificationTypeHandlerInterface
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
            'task_due_soon',
            'task_overdue',
            'task_assigned',
            'task_completed',
            'task_comment'
        ];
    }

    public function getTitle(array $data): string
    {
        $type = $data['notification_type'] ?? $this->getType();
        
        return match ($type) {
            'task_due_soon' => 'Task Due Soon',
            'task_overdue' => 'Task Overdue',
            'task_assigned' => 'Task Assigned',
            'task_completed' => 'Task Completed',
            'task_comment' => 'New Comment',
            default => 'Task Notification',
        };
    }

    public function getMessage(array $data): string
    {
        $type = $data['notification_type'] ?? $this->getType();
        $taskTitle = $this->getTaskTitle($data['task_id'] ?? 0);
        
        return match ($type) {
            'task_due_soon' => sprintf(
                'Task "%s" is due soon (%s)',
                Sanitize::e($taskTitle),
                $data['due_date'] ?? 'today'
            ),
            'task_overdue' => sprintf(
                'Task "%s" is overdue!',
                Sanitize::e($taskTitle)
            ),
            'task_assigned' => sprintf(
                'You were assigned to task "%s"%s',
                Sanitize::e($taskTitle),
                !empty($data['assigned_by']) ? ' by ' . Sanitize::e($data['assigned_by']) : ''
            ),
            'task_completed' => sprintf(
                'Task "%s" has been completed',
                Sanitize::e($taskTitle)
            ),
            'task_comment' => sprintf(
                '%s commented on "%s"',
                Sanitize::e($data['commenter_name'] ?? 'Someone'),
                Sanitize::e($taskTitle)
            ),
            default => '',
        };
    }

    public function getIcon(array $data): ?string
    {
        $type = $data['notification_type'] ?? $this->getType();
        
        return match ($type) {
            'task_overdue' => '/assets/img/icon_warning.svg',
            'task_completed' => '/assets/img/icon_check.svg',
            default => '/assets/img/icon_task.svg',
        };
    }

    public function isSystem(array $data): bool
    {
        $type = $data['notification_type'] ?? $this->getType();
        
        return in_array($type, ['task_due_soon', 'task_overdue']);
    }

    public function getFromUserId(array $data): ?int
    {
        return $data['from_user_id'] ?? $data['assigned_by_id'] ?? $data['commenter_id'] ?? null;
    }

    public function getActionUrl(array $data): ?string
    {
        if (!empty($data['task_id'])) {
            return '/board/task/' . $data['task_id'];
        }
        
        return '/board';
    }

    public function getActions(array $data): array
    {
        $type = $data['notification_type'] ?? $this->getType();
        $actions = [];
        
        if (in_array($type, ['task_due_soon', 'task_overdue'])) {
            $actions[] = [
                'label' => 'View Task',
                'url' => $this->getActionUrl($data),
                'method' => 'GET',
                'class' => 'btn-blue'
            ];
        }
        
        return $actions;
    }

    private function getTaskTitle(int $taskId): string
    {
        if (!$this->db || $taskId === 0) {
            return 'Unknown Task';
        }

        $sql = "SELECT task_title FROM tm_task WHERE id = ? LIMIT 1";
        $result = $this->db->q($sql, "i", $taskId);
        
        return $result[0]['task_title'] ?? 'Unknown Task';
    }
}
