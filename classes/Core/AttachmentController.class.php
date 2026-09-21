<?php
namespace Dashboard\Core;

use Dashboard\Core\Interfaces\DatabaseInterface;
use Dashboard\Core\User;
use Dashboard\Core\HtmxEvents;
use Dashboard\Taskboard\Task;
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
     */
    public function handleDeletePending(): string {
        $token = (string)($_GET['token'] ?? '');
        $userId = (int)$this->user->getUserId();

        $service = new AttachmentService($this->db);
        $service->discardPending($userId, $token);

        triggerResponse(HtmxEvents::successResponse('Attachment removed.', [
            HtmxEvents::ATTACHMENTS_UPDATE => ['itemType' => (string)($_GET['item_type'] ?? ''), 'itemId' => 0],
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
            $html = $this->renderPendingList($service->getPending($userId, $itemType), $itemType);
        } elseif (!$this->validateItemAccess($itemType, $userId, $itemId)) {
            $html = '<div class="attachment-list-empty">You do not have access to this item.</div>';
        } else {
            $service = new AttachmentService($this->db);
            $attachments = $service->getForItem($userId, $itemId, $itemType);
            $html = $this->renderList($attachments, $itemType, $itemId);
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
     * Render the attachment list partial. Kept here (rather than a view
     * file) so the same markup is returned by both handleList and the
     * upload/delete flows.
     */
    /**
     * Render the pending (staged, not-yet-claimed) attachment list. Same
     * markup as renderList(), but rows are keyed by token and delete goes
     * to /attachment/pending/:token. Pending files cannot be downloaded —
     * they are not yet owned by any item.
     */
    private function renderPendingList(array $pending, string $itemType): string {
        if (empty($pending)) {
            return '<div class="attachment-list-empty">No attachments yet. Drop a file here or use the button above.</div>';
        }

        $html = '<ul class="attachment-list">';
        foreach ($pending as $token => $entry) {
            $safeToken = htmlspecialchars((string)$token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $name = Sanitize::e($entry['original_filename'] ?? '');
            $size = $this->formatSize((int)($entry['file_size'] ?? 0));

            $html .= '<li class="attachment-item" data-pending-token="' . $safeToken . '">'
                . '<span class="attachment-icon" aria-hidden="true">' . Sanitize::e($this->fileIcon((string)($entry['mime_type'] ?? ''))) . '</span>'
                . '<span class="attachment-details">'
                . '<span class="attachment-name">' . $name . '</span>'
                . '<span class="attachment-meta">' . Sanitize::e($size) . ' &middot; pending</span>'
                . '</span>'
                . '<button type="button" class="btn btn-light-gray btn-hover-red attachment-delete-btn" tabindex="-1"'
                . ' hx-delete="/attachment/pending/' . $safeToken . '?item_type=' . Sanitize::e($itemType) . '"'
                . ' hx-swap="none">X</button>'
                . '</li>';
        }
        $html .= '</ul>';

        return $html;
    }

    private function renderList(array $attachments, string $itemType, int $itemId): string {
        if (empty($attachments)) {
            return '<div class="attachment-list-empty">No attachments yet. Drop a file here or use the button above.</div>';
        }

        $html = '<ul class="attachment-list">';
        foreach ($attachments as $attachment) {
            $id = (int)$attachment['id'];
            $name = Sanitize::e($attachment['original_filename']);
            $size = $this->formatSize((int)$attachment['file_size']);
            $date = date('M j, Y', strtotime((string)$attachment['created_at']));

            $html .= '<li class="attachment-item" data-attachment-id="' . $id . '">'
                . '<span class="attachment-icon" aria-hidden="true">' . Sanitize::e($this->fileIcon($attachment['mime_type'])) . '</span>'
                . '<span class="attachment-details">'
                . '<a class="attachment-name" href="/attachment/download/' . $id . '" download>' . $name . '</a>'
                . '<span class="attachment-meta">' . Sanitize::e($size) . ' &middot; ' . Sanitize::e($date) . '</span>'
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

    private function formatSize(int $bytes): string {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024) . ' KB';
        }
        return $bytes . ' B';
    }
}
