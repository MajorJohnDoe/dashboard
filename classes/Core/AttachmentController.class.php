<?php
namespace Dashboard\Core;

use Dashboard\Core\Interfaces\DatabaseInterface;
use Dashboard\Core\User;
use Dashboard\Core\HtmxEvents;
use Dashboard\Taskboard\Board;
use Dashboard\Taskboard\Task;
use Dashboard\Taskboard\TaskSchedule;
use Dashboard\Stickynote\StickyNote;
use Dashboard\Jobs\JobApplication;

/**
 * HTTP endpoints for document attachments.
 *
 * Routes (registered in AttachmentRoutes):
 *   POST   /attachment/upload/:item_type/:item_id   → handleUpload
 *   GET    /attachment/list/:item_type/:item_id     → handleList
 *   GET    /attachment/download/:attachment_id      → handleDownload
 *   DELETE /attachment/delete/:attachment_id        → handleDelete
 *
 * All handlers validate that the requesting user actually owns (or can
 * write to) the target item before touching files or the database.
 * CSRF is enforced centrally by CsrfMiddleware via the Router.
 */
class AttachmentController {
    private DatabaseInterface $db;
    private User $user;

    /** item_type => validator fn($userId, $itemId): bool */
    private const ITEM_VALIDATORS = [
        'task'       => 'validateTaskAccess',
        'schedule'   => 'validateScheduleAccess',
        'stickynote' => 'validateNoteAccess',
        'job'        => 'validateJobAccess',
    ];

    public function __construct(DatabaseInterface $db, User $user) {
        $this->db = $db;
        $this->user = $user;
    }

    /**
     * POST /attachment/upload/:item_type/:item_id
     *
     * item_id > 0: item exists — persist immediately (existing flow).
     * item_id = 0: item not created yet (e.g. "new task" dialog) — stage the
     * file as a session-tracked pending upload; it is claimed into the DB
     * when the item is created (see TaskController::handleCreateTask).
     */
    public function handleUpload(): string {
        $itemType = (string)($_GET['item_type'] ?? '');
        $itemId = (int)($_GET['item_id'] ?? 0);
        $userId = (int)$this->user->getUserId();

        if (!$this->isValidItemType($itemType)) {
            triggerResponse(HtmxEvents::errorResponse('Invalid attachment target.'));
            return '';
        }

        // Item exists: verify access, then persist straight away.
        if ($itemId > 0) {
            if (!$this->validateItemAccess($itemType, $userId, $itemId)) {
                triggerResponse(HtmxEvents::errorResponse('You do not have access to this item.'));
                return '';
            }

            $file = $_FILES['attachment'] ?? null;
            if (!is_array($file)) {
                triggerResponse(HtmxEvents::errorResponse('No file was uploaded.'));
                return '';
            }

            $service = new AttachmentService($this->db);

            $staged = $service->stageUpload($file);
            if (!$staged['success']) {
                triggerResponse(HtmxEvents::errorResponse($staged['message']));
                return '';
            }

            $this->db->beginTransaction();
            try {
                $attachmentId = $service->persist($userId, $itemId, $itemType, $staged['staged']);
                if ($attachmentId === false) {
                    throw new \RuntimeException('Failed to persist attachment.');
                }
                $this->db->commit();
            } catch (\Exception $e) {
                $this->db->rollback();
                // Clean up the staged temp file — no orphans on disk.
                $service->discardUpload((string)($staged['staged']['tmpPath'] ?? ''));
                error_log('Attachment upload failed: ' . $e->getMessage());
                triggerResponse(HtmxEvents::errorResponse('An error occurred while saving the attachment.'));
                return '';
            }

            triggerResponse(HtmxEvents::successResponse('File uploaded.', [
                HtmxEvents::ATTACHMENTS_UPDATE => ['itemType' => $itemType, 'itemId' => $itemId],
            ]));
            return '';
        }

        // Item does not exist yet: stage as a pending session upload.
        // Only tasks have a create flow that can claim the tokens
        // (TaskController::handleCreateTask). The sticky-note and job dialogs
        // expose attachments in edit mode only, so staging for them would just
        // park a file that nothing can ever claim.
        if ($itemType !== 'task') {
            triggerResponse(HtmxEvents::errorResponse('Save this item before attaching files.'));
            return '';
        }

        $file = $_FILES['attachment'] ?? null;
        if (!is_array($file)) {
            triggerResponse(HtmxEvents::errorResponse('No file was uploaded.'));
            return '';
        }

        $service = new AttachmentService($this->db);
        $pending = $service->stagePending($userId, $itemType, $file);
        if (!$pending['success']) {
            triggerResponse(HtmxEvents::errorResponse($pending['message']));
            return '';
        }

        // Token rides in a header so the client can append a hidden field to
        // the item-create form (claim happens on item creation).
        header('X-Attachment-Token: ' . $pending['pending']['token']);

        triggerResponse(HtmxEvents::successResponse('File attached.', [
            HtmxEvents::ATTACHMENTS_UPDATE => ['itemType' => $itemType, 'itemId' => 0],
        ]));
        return '';
    }

    /**
     * DELETE /attachment/pending/:token
     * Removes a staged-but-not-yet-claimed file (user removed it from the
     * attachments list of a not-yet-created item).
     *
     * Fails (with a toast) when the token is unknown or belongs to another
     * user, instead of reporting a success that did not happen.
     */
    public function handleDeletePending(): string {
        $token = (string)($_GET['token'] ?? '');
        $itemType = (string)($_GET['item_type'] ?? '');
        $userId = (int)$this->user->getUserId();

        $service = new AttachmentService($this->db);
        if (!$service->discardPending($userId, $token)) {
            triggerResponse(HtmxEvents::errorResponse('That staged file is no longer available.'));
            return '';
        }

        // Fall back to a valid type so the refresh event still targets a pane.
        if (!$this->isValidItemType($itemType)) {
            $itemType = 'task';
        }

        triggerResponse(HtmxEvents::successResponse('Attachment removed.', [
            HtmxEvents::ATTACHMENTS_UPDATE => ['itemType' => $itemType, 'itemId' => 0],
        ]));
        return '';
    }

    /**
     * GET /attachment/list/:item_type/:item_id
     * Returns the attachment list partial (HTML) for the attachments tab.
     *
     * Echoes the HTML directly and exits — the Router JSON-encodes controller
     * return values, which would mangle the markup into a quoted string.
     */
    public function handleList(): string {
        $itemType = (string)($_GET['item_type'] ?? '');
        $itemId = (int)($_GET['item_id'] ?? 0);
        $userId = (int)$this->user->getUserId();

        if (!$this->isValidItemType($itemType) || $itemId < 0) {
            $html = '<div class="attachment-list-empty">Invalid attachment target.</div>';
        } elseif ($itemId === 0) {
            // Not-yet-created item: list session-staged pending files.
            $service = new AttachmentService($this->db);
            $html = $this->renderList($service->getPending($userId, $itemType), $itemType, true);
        } elseif (!$this->validateItemAccess($itemType, $userId, $itemId)) {
            $html = '<div class="attachment-list-empty">You do not have access to this item.</div>';
        } else {
            $service = new AttachmentService($this->db);
            $attachments = $service->getForItem($userId, $itemId, $itemType);
            $html = $this->renderList($attachments, $itemType, false);
        }

        if (ob_get_length()) {
            ob_clean();
        }
        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
        exit;
    }

    /**
     * GET /attachment/download/:attachment_id
     * Streams the file with hardening headers. Ownership is re-verified
     * inside AttachmentService::serveDownload().
     */
    public function handleDownload(): string {
        $attachmentId = (int)($_GET['attachment_id'] ?? 0);
        $userId = (int)$this->user->getUserId();

        if ($attachmentId <= 0) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid attachment ID.']);
            exit;
        }

        (new AttachmentService($this->db))->serveDownload($userId, $attachmentId);
        return ''; // serveDownload exits
    }

    /**
     * DELETE /attachment/delete/:attachment_id
     * Deletes one attachment (file + row) and returns the refreshed list.
     */
    public function handleDelete(): string {
        $attachmentId = (int)($_GET['attachment_id'] ?? 0);
        $userId = (int)$this->user->getUserId();

        $service = new AttachmentService($this->db);
        $attachment = $service->getAttachment($userId, $attachmentId);

        if ($attachment === null) {
            triggerResponse(HtmxEvents::errorResponse('Attachment not found.'));
            return '';
        }

        $itemType = (string)$attachment['item_type'];
        $itemId = (int)$attachment['item_id'];

        $this->db->beginTransaction();
        try {
            if (!$service->deleteAttachment($userId, $attachment)) {
                throw new \RuntimeException('Failed to delete attachment.');
            }
            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log('Attachment delete failed: ' . $e->getMessage());
            triggerResponse(HtmxEvents::errorResponse('An error occurred while deleting the attachment.'));
            return '';
        }

        triggerResponse(HtmxEvents::successResponse('Attachment deleted.', [
            HtmxEvents::ATTACHMENTS_UPDATE => ['itemType' => $itemType, 'itemId' => $itemId],
        ]));
        return '';
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function isValidItemType(string $itemType): bool {
        return isset(self::ITEM_VALIDATORS[$itemType]);
    }

    private function validateItemAccess(string $itemType, int $userId, int $itemId): bool {
        $validator = self::ITEM_VALIDATORS[$itemType];
        return $this->$validator($userId, $itemId);
    }

    /** Task: owner or accepted board share (write access for uploads). */
    private function validateTaskAccess(int $userId, int $itemId): bool {
        $task = new Task($this->db);
        return $task->validateTaskOwnership($userId, $itemId);
    }

    /**
     * Recurring schedule template: the schedule must exist and its board must be
     * writable by the user (owner or accepted write share) — the same gate the
     * schedule's own controller uses before editing it.
     *
     * Attachments on a template are inherited by every task it spawns
     * (TaskSchedule::spawnTaskFromSchedule copies them), so this is a write.
     */
    private function validateScheduleAccess(int $userId, int $itemId): bool {
        $schedule = (new TaskSchedule($this->db))->getScheduleById($itemId);
        if (empty($schedule)) {
            return false;
        }

        return (new Board($this->db))->validateBoardWriteAccess($userId, (int)$schedule[0]['board_id']);
    }

    /** Sticky note: must belong to the user. */
    private function validateNoteAccess(int $userId, int $itemId): bool {
        $note = new StickyNote($this->db);
        return $note->getNoteData($itemId, $userId) !== null;
    }

    /** Job application: must belong to the user. */
    private function validateJobAccess(int $userId, int $itemId): bool {
        $job = new JobApplication($this->db);
        return $job->getById($itemId, $userId) !== null;
    }

    /**
     * Render the attachment list markup — one implementation for both real and
     * pending (staged) rows.
     *
     * Pending rows are keyed by session token, cannot be downloaded (they are
     * not owned by any item yet) and are deleted via /attachment/pending/:token;
     * real rows are keyed by attachment id and link to the download route.
     *
     * @param array  $rows     Rows from getForItem(), or pending entries keyed by token.
     * @param string $itemType Item type, used in the pending delete URL.
     * @param bool   $pending  True when $rows are staged, not-yet-claimed files.
     */
    private function renderList(array $rows, string $itemType, bool $pending): string {
        if (empty($rows)) {
            return '<div class="attachment-list-empty">No attachments yet. Drop a file here or use the button above.</div>';
        }

        $html = '<ul class="attachment-list">';
        foreach ($rows as $key => $row) {
            $icon = Sanitize::e($this->fileIcon((string)($row['mime_type'] ?? '')));
            $name = Sanitize::e($row['original_filename'] ?? '');
            $size = Sanitize::e(AttachmentService::formatBytes((int)($row['file_size'] ?? 0), 1));

            if ($pending) {
                // Token is server-generated hex; escaped anyway (Sanitize::e
                // is the project-wide rule for anything echoed into HTML).
                $token = Sanitize::e((string)$key);

                $html .= '<li class="attachment-item" data-pending-token="' . $token . '">'
                    . '<span class="attachment-icon" aria-hidden="true">' . $icon . '</span>'
                    . '<span class="attachment-details">'
                    . '<span class="attachment-name">' . $name . '</span>'
                    . '<span class="attachment-meta">' . $size . ' &middot; pending</span>'
                    . '</span>'
                    . '<button type="button" class="btn btn-light-gray btn-hover-red attachment-delete-btn" tabindex="-1"'
                    . ' hx-delete="/attachment/pending/' . $token . '?item_type=' . Sanitize::e($itemType) . '"'
                    . ' hx-swap="none">X</button>'
                    . '</li>';
                continue;
            }

            $id = (int)($row['id'] ?? 0);
            $date = Sanitize::e(date('M j, Y', strtotime((string)($row['created_at'] ?? ''))));

            $html .= '<li class="attachment-item" data-attachment-id="' . $id . '">'
                . '<span class="attachment-icon" aria-hidden="true">' . $icon . '</span>'
                . '<span class="attachment-details">'
                . '<a class="attachment-name" href="/attachment/download/' . $id . '" download>' . $name . '</a>'
                . '<span class="attachment-meta">' . $size . ' &middot; ' . $date . '</span>'
                . '</span>'
                . '<button type="button" class="btn btn-light-gray btn-hover-red attachment-delete-btn" tabindex="-1"'
                . ' hx-delete="/attachment/delete/' . $id . '"'
                . ' hx-confirm="Delete this attachment?"'
                . ' hx-swap="none">X</button>'
                . '</li>';
        }
        $html .= '</ul>';

        return $html;
    }

    /** Unicode glyph per file type (no icon fonts per AGENTS.md). */
    private function fileIcon(string $mimeType): string {
        return match ($mimeType) {
            'application/pdf' => '📄',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => '📊',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => '📝',
            'text/plain' => '📃',
            default => '📎',
        };
    }
}
