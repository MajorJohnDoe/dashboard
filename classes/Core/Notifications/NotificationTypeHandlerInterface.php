<?php
namespace Dashboard\Core\Notifications;

use Dashboard\Core\Interfaces\DatabaseInterface;

interface NotificationTypeHandlerInterface
{
    public function setDatabase(DatabaseInterface $db): void;
}
