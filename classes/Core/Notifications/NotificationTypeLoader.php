<?php
namespace Dashboard\Core\Notifications;

require_once __DIR__ . '/handlers/BoardNotificationType.php';
require_once __DIR__ . '/handlers/ChatNotificationType.php';
require_once __DIR__ . '/handlers/TaskNotificationType.php';

use Dashboard\Core\Notifications\Handlers\BoardNotificationType;
use Dashboard\Core\Notifications\Handlers\ChatNotificationType;
use Dashboard\Core\Notifications\Handlers\TaskNotificationType;
use Dashboard\Core\Interfaces\DatabaseInterface;

class NotificationTypeLoader
{
    private static bool $loaded = false;
    private static ?DatabaseInterface $db = null;

    public static function setDatabase(DatabaseInterface $db): void
    {
        self::$db = $db;
    }

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        $boardHandler = new BoardNotificationType(self::$db);
        foreach (BoardNotificationType::getHandledTypes() as $type) {
            $boardHandlerInstance = clone $boardHandler;
            $boardHandlerInstance->setType($type);
            NotificationTypeRegistry::register($boardHandlerInstance);
        }

        $chatHandler = new ChatNotificationType(self::$db);
        foreach (ChatNotificationType::getHandledTypes() as $type) {
            $chatHandlerInstance = clone $chatHandler;
            $chatHandlerInstance->setType($type);
            NotificationTypeRegistry::register($chatHandlerInstance);
        }

        $taskHandler = new TaskNotificationType(self::$db);
        foreach (TaskNotificationType::getHandledTypes() as $type) {
            $taskHandlerInstance = clone $taskHandler;
            $taskHandlerInstance->setType($type);
            NotificationTypeRegistry::register($taskHandlerInstance);
        }

        NotificationTypeRegistry::register(new DefaultNotificationType());

        self::$loaded = true;
    }

    public static function isLoaded(): bool
    {
        return self::$loaded;
    }

    public static function reset(): void
    {
        NotificationTypeRegistry::clear();
        self::$loaded = false;
    }
}

class DefaultNotificationType extends AbstractNotificationType
{
    public function getType(): string
    {
        return 'default';
    }

    public function getTitle(array $data): string
    {
        return $data['title'] ?? 'Notification';
    }

    public function getMessage(array $data): string
    {
        return $data['message'] ?? '';
    }

    public function getIcon(array $data): ?string
    {
        return $data['icon'] ?? '/assets/img/icon_notification.svg';
    }
}
