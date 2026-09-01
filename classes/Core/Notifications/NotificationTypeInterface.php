<?php
namespace Dashboard\Core\Notifications;

/**
 * Interface for notification type handlers.
 * 
 * Each notification type (e.g., board_invite, chat_message) should implement this interface
 * to define how its title, message, icon, and actions are rendered.
 * 
 * Usage:
 *   - Create a new class implementing this interface in handlers/ directory
 *   - Register it in NotificationTypeLoader::load()
 *   - Add the type to the database schema
 */
interface NotificationTypeInterface
{
    /**
     * Get the notification type identifier (e.g., 'board_invite', 'chat_message')
     */
    public function getType(): string;
    
    /**
     * Get the notification title based on notification data
     * @param array $data The decoded JSON data from the notifications table
     */
    public function getTitle(array $data): string;
    
    /**
     * Get the notification message/body based on notification data
     * @param array $data The decoded JSON data from the notifications table
     */
    public function getMessage(array $data): string;
    
    /**
     * Get the icon URL for this notification type
     * @param array $data The decoded JSON data from the notifications table
     */
    public function getIcon(array $data): ?string;
    
    /**
     * Check if this is a system notification (no user avatar)
     * @param array $data The decoded JSON data from the notifications table
     */
    public function isSystem(array $data): bool;
    
    /**
     * Get the user ID who triggered this notification (for avatar)
     * @param array $data The decoded JSON data from the notifications table
     */
    public function getFromUserId(array $data): ?int;
    
    /**
     * Get the URL to navigate to when clicking the notification
     * @param array $data The decoded JSON data from the notifications table
     */
    public function getActionUrl(array $data): ?string;
    
    /**
     * Get action buttons to display for this notification
     * @param array $data The decoded JSON data from the notifications table
     * @return array Array of action arrays with keys: label, url, method, class, data
     */
    public function getActions(array $data): array;
}
