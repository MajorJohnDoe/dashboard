<?php
namespace Dashboard\Core;

use Dashboard\Core\Interfaces\DatabaseInterface;

/**
 * Per-user UI preferences, stored as JSON in `user_ui_preference`.
 *
 * One row per (user, preference key), so adding a preference never needs a
 * schema change. Reads are fail-safe: a missing table or malformed value falls
 * back to the caller's defaults instead of breaking a dialog render.
 */
final class UiPreferenceService
{
    /** Dialog contexts that expose a reorderable tab bar. */
    private const TAB_ORDER_TABS = [
        'task' => ['description', 'checklist', 'attachments'],
        'note' => ['content', 'attachments'],
        'job' => ['description', 'attachments'],
    ];

    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    /** Is $context a dialog whose tabs can be reordered? */
    public static function isValidTabContext(string $context): bool
    {
        return isset(self::TAB_ORDER_TABS[$context]);
    }

    /**
     * Tabs of a dialog in the user's saved order.
     *
     * Unknown entries are dropped and tabs the user has never ordered (e.g. one
     * added in a later release) are appended, so the result always contains
     * every tab of the context exactly once.
     *
     * @param int      $userId
     * @param string   $context  'task' | 'note' | 'job'
     * @param string[] $defaults Fallback order
     * @return string[]
     */
    public function getTabOrder(int $userId, string $context, array $defaults = []): array
    {
        $stored = $this->read($userId, self::tabOrderKey($context));

        return $this->normaliseOrder(is_array($stored) ? $stored : [], $defaults);
    }

    /**
     * Persist a tab order. Tabs that do not belong to the context are ignored.
     *
     * @param string[] $order
     */
    public function saveTabOrder(int $userId, string $context, array $order): bool
    {
        if (!self::isValidTabContext($context)) {
            return false;
        }

        return $this->write(
            $userId,
            self::tabOrderKey($context),
            $this->normaliseOrder($order, self::TAB_ORDER_TABS[$context])
        );
    }

    /**
     * The tab a dialog should open on: the first tab in the saved order that is
     * actually visible — a conditional tab (checklist/attachments) can be hidden
     * while it is empty — falling back to the first tab of the order.
     *
     * @param string[]           $order   Order from getTabOrder()
     * @param array<string,bool> $visible Map of tab name => visible
     */
    public static function defaultTab(array $order, array $visible): string
    {
        foreach ($order as $tab) {
            if (!empty($visible[$tab])) {
                return $tab;
            }
        }

        return (string)($order[0] ?? '');
    }

    /** Keep only known tabs, drop duplicates, then append anything missing. */
    private function normaliseOrder(array $order, array $defaults): array
    {
        $normalised = [];

        foreach ($order as $tab) {
            $tab = is_string($tab) ? trim($tab) : '';
            if ($tab !== '' && in_array($tab, $defaults, true) && !in_array($tab, $normalised, true)) {
                $normalised[] = $tab;
            }
        }

        foreach ($defaults as $tab) {
            if (!in_array($tab, $normalised, true)) {
                $normalised[] = $tab;
            }
        }

        return $normalised;
    }

    private static function tabOrderKey(string $context): string
    {
        return 'tab_order:' . $context;
    }

    /** @return array|null Decoded JSON value, or null when unset/unreadable. */
    private function read(int $userId, string $key)
    {
        try {
            $rows = $this->db->q(
                'SELECT `preference_value` FROM `user_ui_preference` WHERE `user_id` = ? AND `preference_key` = ? LIMIT 1',
                'is',
                $userId,
                $key
            );
        } catch (\Throwable $e) {
            // Not migrated yet (or unreadable): callers fall back to defaults.
            return null;
        }

        if (!is_array($rows) || $rows === []) {
            return null;
        }

        $decoded = json_decode((string)($rows[0]['preference_value'] ?? ''), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function write(int $userId, string $key, array $value): bool
    {
        try {
            $this->db->q(
                'INSERT INTO `user_ui_preference` (`user_id`, `preference_key`, `preference_value`, `modified`)
                 VALUES (?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE `preference_value` = VALUES(`preference_value`), `modified` = NOW()',
                'iss',
                $userId,
                $key,
                json_encode(array_values($value))
            );
        } catch (\Throwable $e) {
            return false;
        }

        return true;
    }
}
