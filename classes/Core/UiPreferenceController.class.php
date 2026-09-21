<?php
namespace Dashboard\Core;

use Dashboard\Core\Interfaces\DatabaseInterface;
use Dashboard\Core\User;

/**
 * JSON endpoint(s) for per-user UI preferences (currently the dialog tab order).
 *
 * Controller routes return an array and the Router JSON-encodes it — the same
 * shape as the existing column-order endpoint.
 */
final class UiPreferenceController
{
    private DatabaseInterface $db;
    private User $user;

    public function __construct(DatabaseInterface $db, User $user)
    {
        $this->db = $db;
        $this->user = $user;
    }

    /**
     * POST /ui/tab-order   body: {"context": "task", "tabs": ["checklist", …]}
     *
     * The client has already applied the order visually, so this only needs the
     * acknowledgement.
     *
     * @return array{success: bool, message: string}
     */
    public function handleSaveTabOrder(): array
    {
        $payload = json_decode((string)file_get_contents('php://input'), true);
        $context = is_array($payload) ? (string)($payload['context'] ?? '') : '';
        $tabs = is_array($payload['tabs'] ?? null) ? $payload['tabs'] : [];

        if (!UiPreferenceService::isValidTabContext($context) || $tabs === []) {
            return ['success' => false, 'message' => 'Invalid tab order request.'];
        }

        $saved = (new UiPreferenceService($this->db))
            ->saveTabOrder((int)$this->user->getUserId(), $context, $tabs);

        return $saved
            ? ['success' => true, 'message' => 'Tab order saved.']
            : ['success' => false, 'message' => 'Could not save the tab order.'];
    }
}
