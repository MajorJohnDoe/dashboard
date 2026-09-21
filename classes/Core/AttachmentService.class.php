<?php
namespace Dashboard\Core;

use Dashboard\Core\Interfaces\DatabaseInterface;

/**
 * Central service for document attachments (PDF, txt, docx, xlsx) on tasks,
 * sticky notes and job applications.
 *
 * Storage layout mirrors ItemImageService: files live under
 * user_upload/<userId>/<YYYY>/<MM>/doc_<itemType><itemId>_<uniqid>.<ext>
 * and are tracked in the polymorphic `item_attachments` table
 * (item_id + item_type discriminator).
 *
 * Security model:
 *  - Files are NEVER served statically. Downloads go through serveDownload()
 *    which re-verifies ownership, MIME and path containment on every request.
 *  - Upload validation: PHP error codes, size cap, extension whitelist,
 *    finfo MIME check + magic-byte verification (PDF: %PDF, docx/xlsx: PK zip).
 *  - Crash-safe flow: the uploaded file is first parked in user_upload_temp/,
 *    the DB row is inserted, and only then is the file moved to its final
 *    destination. If the caller's transaction rolls back, discardUpload()
 *    removes the temp file — no orphan files on disk.
 *  - original_filename is sanitized (basename, control chars, traversal,
 *    leading/trailing dots) before it is ever stored; views must still
 *    escape it with Sanitize::e() on output.
 *  - docx/xlsx are never parsed server-side (zip-bomb safe): magic bytes
 *    are checked and the file is stored as opaque bytes.
 */
class AttachmentService {
    /** Root directory (absolute) that all attachment files must live under. */
    private string $baseDir;

    /** Staging directory for in-flight uploads (same one the audio flow uses). */
    private string $tempDir;

    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db) {
        $this->db = $db;
        $this->baseDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'user_upload' . DIRECTORY_SEPARATOR;
        $this->tempDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'user_upload_temp' . DIRECTORY_SEPARATOR;
    }

    /**
     * Validate an uploaded file ($_FILES entry) and stage it for persistence.
     *
     * Does NOT touch the database — call persist() inside the caller's
     * transaction to insert the row and move the file into place, and
     * discardUpload() if anything fails afterwards.
     *
     * @param array $file One entry from $_FILES (must contain tmp_name, name, size, error).
     * @return array{success:bool, message:string, staged?:array{
     *   tmpPath:string, extension:string, mimeType:string,
     *   originalFilename:string, fileSize:int}}
     */
    public function stageUpload(array $file): array {
        // --- PHP upload error codes -------------------------------------
        $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => $this->uploadErrorMessage($errorCode)];
        }

        $tmpName = (string)($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return ['success' => false, 'message' => 'No file uploaded.'];
        }

        // --- Size cap ----------------------------------------------------
        $fileSize = (int)($file['size'] ?? 0);
        if ($fileSize <= 0) {
            return ['success' => false, 'message' => 'File is empty.'];
        }
        if ($fileSize > _UPLOAD_MAX_BYTES) {
            return ['success' => false, 'message' => 'File exceeds the maximum size of ' . $this->formatBytes(_UPLOAD_MAX_BYTES) . '.'];
        }
        // Trust but verify: the real size on disk is the authority.
        $actualSize = (int)filesize($tmpName);
        if ($actualSize > _UPLOAD_MAX_BYTES) {
            return ['success' => false, 'message' => 'File exceeds the maximum size of ' . $this->formatBytes(_UPLOAD_MAX_BYTES) . '.'];
        }

        // --- Extension whitelist -----------------------------------------
        $originalFilename = $this->sanitizeFilename((string)($file['name'] ?? ''));
        if ($originalFilename === '') {
            return ['success' => false, 'message' => 'File has no usable name.'];
        }

        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        if (!in_array($extension, _UPLOAD_ALLOWED_EXTENSIONS, true)) {
            return ['success' => false, 'message' => 'File type not allowed. Supported: ' . implode(', ', _UPLOAD_ALLOWED_EXTENSIONS) . '.'];
        }

        // --- Content validation (finfo + magic bytes) ---------------------
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($tmpName) ?: '';
        $expectedMime = _UPLOAD_ALLOWED_MIMES[$extension] ?? null;

        if ($expectedMime === null || $mimeType !== $expectedMime) {
            error_log("Attachment upload: MIME mismatch for .{$extension} — got '{$mimeType}', expected '{$expectedMime}'");
            return ['success' => false, 'message' => 'File content does not match its extension.'];
        }

        if (!$this->verifyMagicBytes($tmpName, $extension)) {
            return ['success' => false, 'message' => 'File content does not match its extension.'];
        }

        // --- Stage into user_upload_temp/ --------------------------------
        if (!is_dir($this->tempDir) && !mkdir($this->tempDir, 0755, true)) {
            error_log('Attachment upload: failed to create temp directory: ' . $this->tempDir);
            return ['success' => false, 'message' => 'Server could not stage the upload.'];
        }

        $stagedPath = $this->tempDir . 'att_' . uniqid() . '.' . $extension;
        if (!move_uploaded_file($tmpName, $stagedPath)) {
            error_log('Attachment upload: failed to move uploaded file to temp: ' . $stagedPath);
            return ['success' => false, 'message' => 'Server could not stage the upload.'];
        }
        chmod($stagedPath, 0644);

        return [
            'success' => true,
            'message' => 'File staged.',
            'staged' => [
                'tmpPath'          => $stagedPath,
                'extension'        => $extension,
                'mimeType'         => $mimeType,
                'originalFilename' => $originalFilename,
                'fileSize'         => $actualSize,
            ],
        ];
    }

    /**
     * Persist a staged upload: insert the DB row and move the file from
     * user_upload_temp/ to its final destination under the user's upload
     * directory.
     *
     * Must be called inside a database transaction by the caller. If the
     * caller's transaction later rolls back, call discardUpload() with the
     * returned attachment id (or the staged path) to clean up.
     *
     * @param int    $userId Owner of the item.
     * @param int    $itemId ID of the item the attachment belongs to.
     * @param string $itemType 'task' | 'stickynote' | 'job'.
     * @param array  $staged Value of stageUpload()['staged'].
     * @return int|false New attachment ID, or false on failure.
     */
    public function persist(int $userId, int $itemId, string $itemType, array $staged): int|false {
        $tmpPath = (string)($staged['tmpPath'] ?? '');
        if ($tmpPath === '' || !is_file($tmpPath)) {
            return false;
        }

        // Final destination: user_upload/<userId>/<YYYY>/<MM>/
        $uploadDir = $this->baseDir . $userId . DIRECTORY_SEPARATOR . date('Y') . DIRECTORY_SEPARATOR . date('m') . DIRECTORY_SEPARATOR;
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            error_log('Attachment persist: failed to create upload directory: ' . $uploadDir);
            return false;
        }

        $storedName = 'doc_' . $itemType . $itemId . '_' . uniqid() . '.' . $staged['extension'];
        $finalPath = $uploadDir . $storedName;

        // Move LAST so a failure here leaves no DB row behind.
        if (!rename($tmpPath, $finalPath)) {
            error_log('Attachment persist: failed to move staged file to: ' . $finalPath);
            return false;
        }
        chmod($finalPath, 0644);

        $webPath = str_replace(DIRECTORY_SEPARATOR, '/', str_replace($this->baseDir, '/user_upload/', $finalPath));

        $insertResult = $this->db->q(
            "INSERT INTO `item_attachments` (`user_id`, `item_id`, `item_type`, `original_filename`, `stored_path`, `mime_type`, `file_size`)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            "iissssi",
            $userId,
            $itemId,
            $itemType,
            $staged['originalFilename'],
            $webPath,
            $staged['mimeType'],
            $staged['fileSize']
        );

        if ($insertResult === false) {
            error_log('Attachment persist: failed to insert DB record for: ' . $webPath);
            // Roll the file move back so no orphan file remains.
            if (is_file($finalPath)) {
                unlink($finalPath);
            }
            return false;
        }

        return (int)$this->db->lastInsertId();
    }

    /**
     * Delete a staged upload that was never persisted (transaction rolled
     * back, validation failed after staging, etc.).
     */
    public function discardUpload(string $stagedPath): void {
        if ($stagedPath === '') {
            return;
        }
        // Containment check: only ever delete inside user_upload_temp/.
        $realStaged = realpath($stagedPath);
        $realTempDir = realpath($this->tempDir);
        if ($realStaged === false || $realTempDir === false || strpos($realStaged, $realTempDir . DIRECTORY_SEPARATOR) !== 0) {
            return;
        }
        if (is_file($realStaged)) {
            unlink($realStaged);
        }
    }

    /**
     * Fetch all attachments for one item (newest first).
     *
     * @return array<int,array<string,mixed>> Rows with id, original_filename,
     *   stored_path, mime_type, file_size, created_at.
     */
    public function getForItem(int $userId, int $itemId, string $itemType): array {
        $rows = $this->db->q(
            "SELECT `id`, `original_filename`, `stored_path`, `mime_type`, `file_size`, `created_at`
             FROM `item_attachments`
             WHERE `user_id` = ? AND `item_id` = ? AND `item_type` = ?
             ORDER BY `id` DESC",
            "iis",
            $userId,
            $itemId,
            $itemType
        );
        return is_array($rows) ? $rows : [];
    }

    /**
     * Stage an upload for an item that does not exist yet (e.g. the "new
     * task" dialog). The file stays in user_upload_temp/ and is registered
     * in the session under a random token; nothing touches the database or
     * the user's upload directory.
     *
     * The token must later be claimed via claimPending() when the item is
     * actually created, or discarded via discardPending(). Files orphaned by
     * an abandoned dialog (cancel, browser close, crash) are cleaned up by
     * sweepExpired() — no DB row or final-dir file is ever created for them.
     *
     * @return array{success:bool, message:string, pending?:array{
     *   token:string, originalFilename:string, fileSize:int, extension:string}}
     */
    public function stagePending(int $userId, string $itemType, array $file): array {
        $staged = $this->stageUpload($file);
        if (!$staged['success']) {
            return $staged;
        }

        $token = bin2hex(random_bytes(16));
        $extension = $staged['staged']['extension'];

        // Rename the staged file to the token so the global sweeper can
        // clean it up by file age without needing session access.
        $tokenPath = $this->tempDir . 'att_' . $token . '.' . $extension;
        if (!rename($staged['staged']['tmpPath'], $tokenPath)) {
            $this->discardUpload((string)$staged['staged']['tmpPath']);
            return ['success' => false, 'message' => 'Server could not stage the upload.'];
        }

        $_SESSION['pending_attachments'][$token] = [
            'user_id'          => $userId,
            'item_type'        => $itemType,
            'temp_path'        => $tokenPath,
            'original_filename'=> $staged['staged']['originalFilename'],
            'mime_type'        => $staged['staged']['mimeType'],
            'file_size'        => $staged['staged']['fileSize'],
            'created_at'       => time(),
        ];

        return [
            'success' => true,
            'message' => 'File staged.',
            'pending' => [
                'token'            => $token,
                'originalFilename' => $staged['staged']['originalFilename'],
                'fileSize'         => $staged['staged']['fileSize'],
                'extension'        => $extension,
            ],
        ];
    }

    /**
     * Pending (staged, not yet claimed) attachments for one user + item type.
     * Entries whose temp file has disappeared (swept) are pruned.
     *
     * @return array<string,array<string,mixed>> token => entry
     */
    public function getPending(int $userId, string $itemType): array {
        $all = $_SESSION['pending_attachments'] ?? [];
        if (!is_array($all)) {
            return [];
        }

        $result = [];
        foreach ($all as $token => $entry) {
            if (!is_array($entry)
                || ($entry['user_id'] ?? null) !== $userId
                || ($entry['item_type'] ?? null) !== $itemType) {
                continue;
            }
            // Prune entries whose temp file is gone (e.g. swept while the
            // dialog was open for a long time).
            if (!is_file((string)($entry['temp_path'] ?? ''))) {
                unset($_SESSION['pending_attachments'][$token]);
                continue;
            }
            $result[$token] = $entry;
        }
        return $result;
    }

    /**
     * Discard one pending staged file (user removed it from the list before
     * saving the item).
     */
    public function discardPending(int $userId, string $token): bool {
        $entry = $_SESSION['pending_attachments'][$token] ?? null;
        if (!is_array($entry) || ($entry['user_id'] ?? null) !== $userId) {
            return false;
        }

        $this->discardUpload((string)($entry['temp_path'] ?? ''));
        unset($_SESSION['pending_attachments'][$token]);
        return true;
    }

    /**
     * Claim pending staged files for a just-created item: move each temp
     * file into the user's upload directory and insert the item_attachments
     * rows. Must be called inside the caller's database transaction (same
     * requirement as persist()). Tokens not found / not owned / wrong type
     * are silently skipped.
     *
     * @param int[] $tokens
     * @return int Number of attachments claimed.
     */
    public function claimPending(int $userId, int $itemId, string $itemType, array $tokens): int {
        $claimed = 0;
        foreach ($tokens as $token) {
            $entry = $_SESSION['pending_attachments'][$token] ?? null;
            if (!is_array($entry)
                || ($entry['user_id'] ?? null) !== $userId
                || ($entry['item_type'] ?? null) !== $itemType
                || !is_file((string)($entry['temp_path'] ?? ''))) {
                continue;
            }

            $staged = [
                'tmpPath'          => $entry['temp_path'],
                'extension'        => strtolower(pathinfo((string)$entry['temp_path'], PATHINFO_EXTENSION)),
                'mimeType'         => $entry['mime_type'],
                'originalFilename' => $entry['original_filename'],
                'fileSize'         => (int)$entry['file_size'],
            ];

            $attachmentId = $this->persist($userId, $itemId, $itemType, $staged);
            if ($attachmentId !== false) {
                $claimed++;
                unset($_SESSION['pending_attachments'][$token]);
            }
            // If persist() failed it cleaned up the file itself; drop the
            // stale entry either way so it can't be retried forever.
            unset($_SESSION['pending_attachments'][$token]);
        }
        return $claimed;
    }

    /**
     * Delete orphaned staged uploads older than the given age (default 24 h).
     *
     * Runs globally over user_upload_temp/ — it cannot (and must not) touch
     * session data; stale session entries are pruned lazily by getPending().
     * Designed to be called opportunistically (e.g. piggybacked on the
     * board render, time-throttled), mirroring the runDueSchedules pattern.
     */
    public function sweepExpired(int $maxAgeSeconds = 86400): int {
        if (!is_dir($this->tempDir)) {
            return 0;
        }

        // Cheap time-throttle via a marker file: sweep at most once/hour.
        $marker = $this->tempDir . '.last_sweep';
        if (is_file($marker) && (time() - (int)filemtime($marker)) < 3600) {
            return 0;
        }
        @touch($marker);

        $removed = 0;
        foreach (glob($this->tempDir . 'att_*') ?: [] as $file) {
            if (is_file($file) && (time() - (int)filemtime($file)) > $maxAgeSeconds) {
                if (unlink($file)) {
                    $removed++;
                }
            }
        }
        return $removed;
    }

    /** Count attachments for one item (used for tab badges). */
    public function countForItem(int $userId, int $itemId, string $itemType): int {
        $rows = $this->db->q(
            "SELECT COUNT(*) AS cnt FROM `item_attachments`
             WHERE `user_id` = ? AND `item_id` = ? AND `item_type` = ?",
            "iis",
            $userId,
            $itemId,
            $itemType
        );
        return (int)($rows[0]['cnt'] ?? 0);
    }

    /**
     * Which items (of one type) have at least one attachment — single grouped
     * query, used to render indicators on task cards without per-task lookups.
     *
     * @return int[] Item IDs that have attachments.
     */
    public function itemsWithAttachments(int $userId, array $itemIds, string $itemType): array {
        $cleanIds = array_values(array_filter(array_map('intval', $itemIds), fn($id) => $id > 0));
        if (empty($cleanIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
        $types = 'is' . str_repeat('i', count($cleanIds));
        $rows = $this->db->q(
            "SELECT DISTINCT `item_id` FROM `item_attachments`
             WHERE `user_id` = ? AND `item_type` = ? AND `item_id` IN ($placeholders)",
            $types,
            $userId,
            $itemType,
            ...$cleanIds
        );

        return array_map(fn($row) => (int)$row['item_id'], is_array($rows) ? $rows : []);
    }

    /**
     * Load one attachment and verify it belongs to the given user.
     *
     * @return array<string,mixed>|null The row, or null when not found / not owned.
     */
    public function getAttachment(int $userId, int $attachmentId): ?array {
        $rows = $this->db->q(
            "SELECT `id`, `user_id`, `item_id`, `item_type`, `original_filename`, `stored_path`, `mime_type`, `file_size`
             FROM `item_attachments`
             WHERE `id` = ? AND `user_id` = ?
             LIMIT 1",
            "ii",
            $attachmentId,
            $userId
        );
        return (is_array($rows) && isset($rows[0])) ? $rows[0] : null;
    }

    /**
     * Delete a single attachment (file + DB row). Ownership must be verified
     * by the caller (or pass the verified row).
     *
     * @param int   $userId       Owner id (used for path containment).
     * @param array $attachment   Row from getAttachment().
     */
    public function deleteAttachment(int $userId, array $attachment): bool {
        $fullPath = $this->resolveStoredPath($userId, (string)$attachment['stored_path']);
        if ($fullPath !== false && is_file($fullPath)) {
            unlink($fullPath);
        }

        $result = $this->db->q(
            "DELETE FROM `item_attachments` WHERE `id` = ? AND `user_id` = ?",
            "ii",
            (int)$attachment['id'],
            $userId
        );
        return $result !== false;
    }

    /**
     * Delete all attachments belonging to one item (files + rows).
     * Call from task/note/job delete flows, inside their transactions.
     */
    public function deleteAllForItem(int $userId, int $itemId, string $itemType): void {
        $rows = $this->getForItem($userId, $itemId, $itemType);
        foreach ($rows as $row) {
            $this->deleteAttachment($userId, $row);
        }
    }

    /**
     * Batch variant of deleteAllForItem().
     */
    public function deleteAllForItems(int $userId, array $itemIds, string $itemType): void {
        $cleanIds = array_values(array_filter(array_map('intval', $itemIds), fn($id) => $id > 0));
        if (empty($cleanIds)) {
            return;
        }
        foreach ($cleanIds as $itemId) {
            $this->deleteAllForItem($userId, $itemId, $itemType);
        }
    }

    /**
     * Copy all attachments from one item to another as independent copies
     * (new files + new rows). Used by task duplicate and recurring spawn so
     * each copy owns its own files — deleting one copy never breaks another.
     *
     * Must be called inside a database transaction by the caller.
     *
     * @return int Number of attachments copied.
     */
    public function copyAttachments(int $userId, int $fromItemId, string $fromItemType, int $toItemId, string $toItemType): int {
        if ($fromItemId <= 0 || $toItemId <= 0 || $fromItemType === '' || $toItemType === '') {
            return 0;
        }

        $rows = $this->getForItem($userId, $fromItemId, $fromItemType);
        $copied = 0;

        foreach ($rows as $row) {
            $sourcePath = $this->resolveStoredPath($userId, (string)$row['stored_path']);
            if ($sourcePath === false || !is_file($sourcePath)) {
                continue;
            }

            $uploadDir = $this->baseDir . $userId . DIRECTORY_SEPARATOR . date('Y') . DIRECTORY_SEPARATOR . date('m') . DIRECTORY_SEPARATOR;
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                continue;
            }

            $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
            $storedName = 'doc_' . $toItemType . $toItemId . '_' . uniqid() . '.' . $extension;
            $targetPath = $uploadDir . $storedName;

            if (!copy($sourcePath, $targetPath)) {
                error_log("Attachment copy: failed to copy {$sourcePath} to {$targetPath}");
                continue;
            }
            chmod($targetPath, 0644);

            $webPath = str_replace(DIRECTORY_SEPARATOR, '/', str_replace($this->baseDir, '/user_upload/', $targetPath));

            $insertResult = $this->db->q(
                "INSERT INTO `item_attachments` (`user_id`, `item_id`, `item_type`, `original_filename`, `stored_path`, `mime_type`, `file_size`)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                "iissssi",
                $userId,
                $toItemId,
                $toItemType,
                $row['original_filename'],
                $webPath,
                $row['mime_type'],
                (int)$row['file_size']
            );

            if ($insertResult === false) {
                unlink($targetPath);
                continue;
            }
            $copied++;
        }

        return $copied;
    }

    /**
     * Stream an attachment to the browser after re-verifying ownership,
     * MIME and path containment. Sends hardening headers:
     *   X-Content-Type-Options: nosniff
     *   Content-Security-Policy: default-src 'none'
     *   Content-Disposition: attachment; filename="..."
     *
     * Exits after streaming (or after sending the error response).
     *
     * @param int $userId       Requesting user (must own the attachment).
     * @param int $attachmentId Attachment ID.
     */
    public function serveDownload(int $userId, int $attachmentId): void {
        $attachment = $this->getAttachment($userId, $attachmentId);
        if ($attachment === null) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Attachment not found.']);
            exit;
        }

        $fullPath = $this->resolveStoredPath($userId, (string)$attachment['stored_path']);
        if ($fullPath === false || !is_file($fullPath) || !is_readable($fullPath)) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Attachment file missing.']);
            exit;
        }

        // Re-verify the content still matches the stored MIME type.
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $actualMime = $finfo->file($fullPath) ?: '';
        $storedMime = (string)$attachment['mime_type'];
        $sendMime = ($actualMime === $storedMime) ? $storedMime : 'application/octet-stream';

        $filename = $this->sanitizeFilename((string)$attachment['original_filename']);
        if ($filename === '') {
            $filename = 'attachment.' . strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        }

        if (ob_get_length()) {
            ob_clean();
        }

        header('Content-Type: ' . $sendMime);
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'");
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
        header('Content-Length: ' . (string)filesize($fullPath));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: private');

        readfile($fullPath);
        exit;
    }

    /**
     * Delete every attachment row + file for a user (used when a user
     * account is removed). Also removes the user's upload directory tree.
     */
    public function deleteAllForUser(int $userId): void {
        $rows = $this->db->q(
            "SELECT `id`, `stored_path` FROM `item_attachments` WHERE `user_id` = ?",
            "i",
            $userId
        );

        foreach ((is_array($rows) ? $rows : []) as $row) {
            $fullPath = $this->resolveStoredPath($userId, (string)$row['stored_path']);
            if ($fullPath !== false && is_file($fullPath)) {
                unlink($fullPath);
            }
        }

        $this->db->q("DELETE FROM `item_attachments` WHERE `user_id` = ?", "i", $userId);
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    /**
     * Resolve a stored web-relative path (/user_upload/<userId>/...) to an
     * absolute path, enforcing containment within the user's upload directory.
     *
     * @return string|false Absolute real path, or false when invalid.
     */
    private function resolveStoredPath(int $userId, string $storedPath): string|false {
        // Strip null bytes before any filesystem use.
        $storedPath = str_replace(chr(0), '', $storedPath);

        // Must start with the expected web prefix.
        if (strpos($storedPath, '/user_upload/') !== 0) {
            return false;
        }

        $realPath = realpath(dirname(__DIR__, 2) . $storedPath);
        if ($realPath === false) {
            return false;
        }

        $userUploadDir = realpath($this->baseDir . $userId);
        if ($userUploadDir === false) {
            return false;
        }

        if (strpos($realPath, $userUploadDir . DIRECTORY_SEPARATOR) !== 0) {
            return false;
        }

        return $realPath;
    }

    /**
     * Verify magic bytes for the allowed document types.
     * PDF starts with "%PDF"; docx/xlsx are ZIP containers ("PK\x03\x04").
     */
    private function verifyMagicBytes(string $path, string $extension): bool {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }
        $header = (string)fread($handle, 8);
        fclose($handle);

        return match ($extension) {
            'pdf'  => str_starts_with($header, '%PDF'),
            'docx', 'xlsx' => str_starts_with($header, "PK\x03\x04"),
            'txt'  => true, // plain text: no magic signature
            default => false,
        };
    }

    /**
     * Sanitize a client-supplied filename for safe storage and display:
     * basename only (no traversal), control chars / null bytes stripped,
     * leading/trailing dots and whitespace removed, length capped at 255.
     */
    private function sanitizeFilename(string $filename): string {
        // Basename kills directory traversal components (../, ..\, paths).
        $filename = basename(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $filename));

        // Strip null bytes and control characters (ASCII < 32 and DEL).
        $filename = preg_replace('/[\x00-\x1F\x7F]/', '', $filename) ?? '';

        // Collapse whitespace runs, trim dots/spaces from both ends.
        $filename = trim(preg_replace('/\s+/u', ' ', $filename) ?? '', " .");

        // Cap length while keeping the extension intact.
        if (mb_strlen($filename) > 255) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $base = mb_substr(pathinfo($filename, PATHINFO_FILENAME), 0, 250);
            $filename = $extension !== '' ? $base . '.' . $extension : $base;
        }

        return $filename;
    }

    /**
     * Human-readable message for a PHP upload error code.
     */
    private function uploadErrorMessage(int $errorCode): string {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE   => 'File exceeds the server upload limit.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds the maximum allowed size.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded. Please try again.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server misconfiguration: missing temp directory.',
            UPLOAD_ERR_CANT_WRITE => 'Server failed to write the file to disk.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.',
            default               => 'Unknown upload error.',
        };
    }

    /**
     * Format a byte count for user-facing messages (e.g. "10 MB").
     */
    private function formatBytes(int $bytes): string {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024) . ' KB';
        }
        return $bytes . ' B';
    }
}
