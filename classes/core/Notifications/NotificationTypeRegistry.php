<?php
namespace Dashboard\Core\Notifications;

class NotificationTypeRegistry
{
    private static array $types = [];

    public static function register(NotificationTypeInterface $type): void
    {
        self::$types[$type->getType()] = $type;
    }

    public static function get(string $type): ?NotificationTypeInterface
    {
        return self::$types[$type] ?? null;
    }

    public static function all(): array
    {
        return self::$types;
    }

    public static function has(string $type): bool
    {
        return isset(self::$types[$type]);
    }

    public static function getTypes(): array
    {
        return array_keys(self::$types);
    }

    public static function clear(): void
    {
        self::$types = [];
    }

    public static function count(): int
    {
        return count(self::$types);
    }
}
