<?php
namespace Dashboard\Core\Notifications;

/**
 * Abstract base class for notification type handlers.
 * 
 * Provides default implementations for common methods.
 * Subclasses must implement getTitle() and getMessage().
 * 
 * Usage:
 *   1. Create a new class extending this in handlers/ directory
 *   2. Implement getTitle() and getMessage() 
 *   3. Optionally override other methods
 *   4. Add types to getHandledTypes() static method
 *   5. Register in NotificationTypeLoader
 * 
 * Example:
 *   class MyNotificationType extends AbstractNotificationType
 *   {
 *       public static function getHandledTypes(): array
 *       {
 *           return ['my_type'];
 *       }
 *       
 *       public function getTitle(array $data): string
 *       {
 *           return 'My Notification';
 *       }
 *       
 *       public function getMessage(array $data): string
 *       {
 *           return $data['message'] ?? '';
 *       }
 *   }
 */
abstract class AbstractNotificationType implements NotificationTypeInterface
{
    /** @var string The notification type identifier, set by NotificationTypeLoader */
    protected string $type = '';

    /**
     * Set the notification type. Called by NotificationTypeLoader when registering.
     * @param string $type The notification type (e.g., 'board_invite')
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * Get the notification type identifier
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get notification title - MUST be implemented by subclass
     */
    abstract public function getTitle(array $data): string;

    /**
     * Get notification message - MUST be implemented by subclass
     */
    abstract public function getMessage(array $data): string;

    /**
     * Get icon URL - returns null by default (uses default icon)
     */
    public function getIcon(array $data): ?string
    {
        return null;
    }

    /**
     * Check if system notification - returns true if no from_user_id
     */
    public function isSystem(array $data): bool
    {
        return empty($data['from_user_id']);
    }

    /**
     * Get user ID who triggered notification - defaults to from_user_id in data
     */
    public function getFromUserId(array $data): ?int
    {
        return $data['from_user_id'] ?? null;
    }

    /**
     * Get URL to navigate to - returns null by default (no action)
     */
    public function getActionUrl(array $data): ?string
    {
        return null;
    }

    /**
     * Get action buttons - returns empty array by default
     */
    public function getActions(array $data): array
    {
        return [];
    }

    /**
     * Helper to safely escape HTML in notification content
     */
    protected function htmlspecialchars(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
