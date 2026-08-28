<?php
namespace Dashboard\Core\Notifications;

use Dashboard\Core\Interfaces\DatabaseInterface;
use Dashboard\Core\Notifications\NotificationTypeHandlerInterface;

class NotificationRenderer
{
    private DatabaseInterface $db;
    private ?NotificationTypeInterface $handler = null;
    private array $data = [];

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    public function setNotification(string $type, array $data): self
    {
        $this->data = $data;
        $this->data['notification_type'] = $type;
        
        $handler = NotificationTypeRegistry::get($type);
        
        if ($handler instanceof NotificationTypeHandlerInterface) {
            $handler->setDatabase($this->db);
        }
        
        $this->handler = $handler;
        
        return $this;
    }

    public function getTitle(): string
    {
        if ($this->handler) {
            return $this->handler->getTitle($this->data);
        }
        return $this->data['title'] ?? 'Notification';
    }

    public function getMessage(): string
    {
        if ($this->handler) {
            return $this->handler->getMessage($this->data);
        }
        return $this->data['message'] ?? '';
    }

    public function getIcon(): ?string
    {
        if ($this->handler) {
            return $this->handler->getIcon($this->data);
        }
        return $this->data['icon'] ?? null;
    }

    public function isSystem(): bool
    {
        if ($this->handler) {
            return $this->handler->isSystem($this->data);
        }
        return empty($this->data['from_user_id']);
    }

    public function getFromUserId(): ?int
    {
        if ($this->handler) {
            return $this->handler->getFromUserId($this->data);
        }
        return $this->data['from_user_id'] ?? null;
    }

    public function getActionUrl(): ?string
    {
        if ($this->handler) {
            return $this->handler->getActionUrl($this->data);
        }
        return $this->data['action_url'] ?? null;
    }

    public function getActions(): array
    {
        if ($this->handler) {
            return $this->handler->getActions($this->data);
        }
        return $this->data['actions'] ?? [];
    }

    public function hasHandler(): bool
    {
        return $this->handler !== null;
    }
}
