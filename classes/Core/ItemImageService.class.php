<?php
namespace Dashboard\Core;

use Dashboard\Core\Sanitize;

use Dashboard\Core\Interfaces\DatabaseInterface;

/**
 * Central service for the lifecycle of images embedded in rich-text content
 * (base64 data URIs converted to files on save) and for deleting images
 * belonging to an item.
 *
 * Item types currently in use: 'task', 'stickynote', 'job'.
 */
class ItemImageService {
    /** Maximum size in bytes for a remote image download (5 MB). */
    private const MAX_REMOTE_IMAGE_BYTES = 5242880;

    /** Root directory (absolute) that all image files must live under. */
    private string $baseDir;

    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db) {
        $this->db = $db;
        $this->baseDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'user_upload' . DIRECTORY_SEPARATOR;
    }

    /**
     * Converts base64 data URIs in the given HTML content into files on disk,
     * records them in `shared_item_images`, and deletes files that were
     * removed from the content (orphans). Also downloads remote http(s)
     * images and rewrites them to local paths.
     *
     * Must be called inside a database transaction by the caller.
     *
     * @param int    $userId     Owner of the item (images are stored under the user's upload dir).
     * @param int    $itemId     ID of the item the content belongs to.
     * @param string $htmlContent HTML content possibly containing <img src="data:..."> tags.
     * @param string $itemType   Discriminator stored in `shared_item_images.item_type`.
     *
     * @return string The rewritten HTML content with web paths instead of data URIs.
     */
    public function processAndPersist(int $userId, int $itemId, string $htmlContent, string $itemType): string {
        $imageHandler = new SharedImageHandler($userId, $itemId, $htmlContent, $this->db, $itemType);
        $result = $imageHandler->processImages();

        foreach ($result['toDelete'] as $fileToDelete) {
            $this->securelyDeleteFile($userId, $fileToDelete);
        }

        return $this->importRemoteImages($userId, $itemId, $result['newContent'], $itemType);
    }

    /**
     * Downloads remote images referenced by absolute http(s) URLs in the given
     * HTML content, stores them locally, and rewrites the <img src> to the
     * local web path. Images already hosted under /user_upload/ are left alone.
     *
     * Security hardening:
     * - Only http/https schemes are followed.
     * - DNS is resolved up-front and any host resolving to a private/reserved
     *   IP range is rejected (SSRF protection).
     * - Response body is read with a hard byte cap and a short timeout.
     * - Content is validated as a real image (finfo) against an allowlist.
     *
     * @return string The rewritten HTML content with local image paths where possible.
     */
    private function importRemoteImages(int $userId, int $itemId, string $htmlContent, string $itemType): string {
        $pattern = '/<img[^>]+src="(https?:\/\/[^"#?]+[^"#]*)"/i';

        $newContent = preg_replace_callback($pattern, function ($matches) use ($userId, $itemId, $itemType) {
            $srcAttribute = $matches[0];
            $url = html_entity_decode($matches[1], ENT_QUOTES);

            // Skip images already stored locally
            if (strpos($url, '/user_upload/') !== false) {
                return $srcAttribute;
            }

            $imported = $this->fetchRemoteImage($userId, $itemId, $url, $itemType);
            if ($imported === null) {
                // Download or validation failed; keep the original remote URL
                return $srcAttribute;
            }

            return str_replace($matches[1], Sanitize::e($imported), $srcAttribute);
        }, $htmlContent);

        return $newContent ?? $htmlContent;
    }

    /**
     * Fetches, validates, and stores a single remote image.
     *
     * @return string|null The local web path of the stored image, or null on failure.
     */
    private function fetchRemoteImage(int $userId, int $itemId, string $url, string $itemType): ?string {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host'], $parts['scheme'])) {
            error_log("Remote image import: malformed URL rejected: " . $url);
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            error_log("Remote image import: non-http(s) scheme rejected: " . $scheme);
            return null;
        }

        if (!$this->isPubliclyRoutableHost($parts['host'])) {
            error_log("Remote image import: host resolves to private/reserved address, rejected: " . $parts['host']);
            return null;
        }

        // Stream the response with a hard size cap and short timeout
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'follow_location' => 1,
                'max_redirects' => 3,
                'user_agent' => 'Hyperboard/1.0 (image importer)',
                'header' => "Accept: image/*\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $stream = @fopen($url, 'rb', false, $context);
        if ($stream === false) {
            error_log("Remote image import: failed to open URL: " . $url);
            return null;
        }

        $body = stream_get_contents($stream, self::MAX_REMOTE_IMAGE_BYTES + 1);
        fclose($stream);

        if ($body === false || strlen($body) === 0) {
            error_log("Remote image import: empty or unreadable response: " . $url);
            return null;
        }

        if (strlen($body) > self::MAX_REMOTE_IMAGE_BYTES) {
            error_log("Remote image import: response exceeds size cap, rejected: " . $url);
            return null;
        }

        // Validate the bytes are actually an image with an allowed type
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($body);
        $allowedMimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];

        if (!isset($allowedMimeToExt[$mimeType])) {
            error_log("Remote image import: unsupported content type '{$mimeType}', rejected: " . $url);
            return null;
        }

        // Store alongside the other item images
        $extension = $allowedMimeToExt[$mimeType];
        $currentYear = date('Y');
        $currentMonth = date('m');
        $uploadDir = $this->baseDir . $userId . DIRECTORY_SEPARATOR . $currentYear . DIRECTORY_SEPARATOR . $currentMonth . DIRECTORY_SEPARATOR;

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            error_log("Remote image import: failed to create upload directory: " . $uploadDir);
            return null;
        }

        $fileName = 'img_' . $itemType . $itemId . '_' . uniqid() . '.' . $extension;
        $filePath = $uploadDir . $fileName;

        if (file_put_contents($filePath, $body) === false) {
            error_log("Remote image import: failed to write file: " . $filePath);
            return null;
        }

        $webPath = str_replace(DIRECTORY_SEPARATOR, '/', str_replace($this->baseDir, '/user_upload/', $filePath));

        $insertResult = $this->db->q(
            "INSERT INTO `shared_item_images` (`item_id`, `item_type`, `image_name`, `image_path`, `upload_type`, `file_size`) VALUES (?, ?, ?, ?, 1, ?)",
            "isssi",
            $itemId, $itemType, $fileName, $webPath, filesize($filePath)
        );

        if ($insertResult === false) {
            error_log("Remote image import: failed to insert DB record for: " . $webPath);
            unlink($filePath);
            return null;
        }

        return $webPath;
    }

    /**
     * Resolves the given host and rejects any address that is private,
     * reserved, or loopback — the core SSRF defense.
     */
    private function isPubliclyRoutableHost(string $host): bool {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        $ips = [];

        if ($records !== false) {
            foreach ($records as $record) {
                if (isset($record['ip'])) {
                    $ips[] = $record['ip'];
                }
                if (isset($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        // Fallback when dns_get_record returns nothing (e.g. some resolvers)
        if (empty($ips)) {
            $resolved = @gethostbynamel($host);
            if ($resolved !== false) {
                $ips = $resolved;
            }
        }

        if (empty($ips)) {
            return false;
        }

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Deletes all images belonging to a single item (files on disk and
     * `shared_item_images` rows).
     *
     * @param int    $userId   Owner of the item; used to validate file paths.
     * @param int    $itemId   ID of the item.
     * @param string $itemType Discriminator stored in `shared_item_images.item_type`.
     */
    public function deleteAllForItem(int $userId, int $itemId, string $itemType): void {
        $this->deleteAllForItems($userId, [$itemId], $itemType);
    }

    /**
     * Batch variant of deleteAllForItem().
     *
     * @param int    $userId   Owner of the items; used to validate file paths.
     * @param int[]  $itemIds  IDs of the items.
     * @param string $itemType Discriminator stored in `shared_item_images.item_type`.
     */
    public function deleteAllForItems(int $userId, array $itemIds, string $itemType): void {
        $cleanIds = array_values(array_filter(array_map('intval', $itemIds), fn($id) => $id > 0));
        if (empty($cleanIds)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
        $types = str_repeat('i', count($cleanIds)) . 's';
        $params = array_merge($cleanIds, [$itemType]);

        $images = $this->db->q(
            "SELECT image_name, image_path FROM `shared_item_images` WHERE `item_id` IN ($placeholders) AND `item_type` = ?",
            $types,
            ...$params
        );

        if (empty($images) || !is_array($images)) {
            return;
        }

        foreach ($images as $image) {
            // The file may be shared with other items (duplicated tasks,
            // schedule templates, spawned recurring tasks). Only unlink it
            // when the last DB reference is going away; the rows for the
            // deleted items are removed below either way.
            if (!$this->isImageReferencedByOthers($image['image_path'], $itemType, $cleanIds)) {
                $this->securelyDeleteFile($userId, $image['image_path']);
            }
        }

        $this->db->q(
            "DELETE FROM `shared_item_images` WHERE `item_id` IN ($placeholders) AND `item_type` = ?",
            $types,
            ...$params
        );
    }

    /**
     * Whether any OTHER item (outside the given exclusion list for the given
     * item type) still holds a reference to the same image file.
     *
     * @param string   $imagePath      Web-relative image path (e.g. /user_upload/1/2026/01/img.png).
     * @param string   $itemType       Item type of the items being deleted.
     * @param int[]    $excludeItemIds Item IDs of the items being deleted.
     */
    private function isImageReferencedByOthers(string $imagePath, string $itemType, array $excludeItemIds): bool {
        $placeholders = implode(',', array_fill(0, count($excludeItemIds), '?'));
        $types = 's' . str_repeat('i', count($excludeItemIds));
        $refs = $this->db->q(
            "SELECT COUNT(*) AS cnt FROM `shared_item_images`
             WHERE `image_path` = ? AND NOT (`item_type` = ? AND `item_id` IN ($placeholders))",
            $types,
            $imagePath,
            $itemType,
            ...$excludeItemIds
        );
        return ((int)($refs[0]['cnt'] ?? 0)) > 0;
    }

    /**
     * Copy all image references from one item to another, pointing at the
     * same files on disk.
     *
     * Used whenever content is duplicated into a new owner — task duplicate,
     * recurring schedule template ("Make recurring"), and each task spawned
     * from a schedule — so the new owner holds its own DB reference. Removal
     * of the image from any single owner then cannot break the others:
     * file deletion is reference-counted (see deleteAllForItems() and
     * SharedImageHandler::collectImagesToDelete()).
     *
     * Must be called inside a database transaction by the caller (same
     * requirement as processAndPersist()).
     *
     * @param int    $fromItemId   Source item ID.
     * @param string $fromItemType Source item type (e.g. 'task', 'schedule').
     * @param int    $toItemId     Target item ID.
     * @param string $toItemType   Target item type.
     */
    public function copyImageReferences(int $fromItemId, string $fromItemType, int $toItemId, string $toItemType): void {
        if ($fromItemId <= 0 || $toItemId <= 0 || $fromItemType === '' || $toItemType === '') {
            return;
        }

        // Skip files the target already owns (e.g. repeated migration).
        $owned = $this->db->q(
            "SELECT image_path FROM `shared_item_images` WHERE `item_id` = ? AND `item_type` = ?",
            "is",
            $toItemId,
            $toItemType
        );
        $ownedPaths = [];
        foreach ((is_array($owned) ? $owned : []) as $row) {
            $ownedPaths[$row['image_path']] = true;
        }

        $rows = $this->db->q(
            "SELECT image_name, image_path, upload_type, file_size FROM `shared_item_images`
             WHERE `item_id` = ? AND `item_type` = ?",
            "is",
            $fromItemId,
            $fromItemType
        );

        foreach ((is_array($rows) ? $rows : []) as $row) {
            if (isset($ownedPaths[$row['image_path']])) {
                continue;
            }
            $this->db->q(
                "INSERT INTO `shared_item_images` (`item_id`, `item_type`, `image_name`, `image_path`, `upload_type`, `file_size`)
                 VALUES (?, ?, ?, ?, ?, ?)",
                "isssii",
                $toItemId,
                $toItemType,
                $row['image_name'],
                $row['image_path'],
                (int)$row['upload_type'],
                (int)($row['file_size'] ?? 0)
            );
        }
    }

    /**
     * Securely deletes a file after performing safety checks:
     * path sanitization, existence, containment within the user's upload
     * directory, and (on POSIX systems) ownership by the web server process.
     */
    private function securelyDeleteFile(int $userId, string $filePath): bool {
        // Step 1: Validate the file path
        $fullPath = $this->validateAndSanitizePath($userId, $filePath);
        if ($fullPath === false) {
            error_log("Invalid file path attempted to be deleted: " . $filePath);
            return false;
        }

        // Step 2: Check if the file exists and is within the allowed directory
        if (!file_exists($fullPath) || !$this->isInAllowedDirectory($userId, $fullPath)) {
            error_log("File does not exist or is not in an allowed directory: " . $fullPath);
            return false;
        }

        // Step 3: Ensure the file is owned by the web server process (POSIX only)
        if (!$this->isOwnedByWebServer($fullPath)) {
            error_log("File is not owned by the web server process: " . $fullPath);
            return false;
        }

        // Step 4: Attempt to delete the file
        if (unlink($fullPath)) {
            error_log("Successfully deleted file: " . $fullPath);
            return true;
        }

        error_log("Failed to delete file: " . $fullPath);
        return false;
    }

    /**
     * Validates and sanitizes a web-relative file path (e.g. /user_upload/1/2026/01/img.png),
     * ensuring it resolves within the requesting user's upload directory.
     *
     * @return string|false The absolute real path, or false if invalid.
     */
    private function validateAndSanitizePath(int $userId, string $filePath) {
        // Remove any null bytes
        $filePath = str_replace(chr(0), '', $filePath);

        // Resolve the real path, removing any '..' or symbolic links
        $realPath = realpath(dirname(__DIR__, 2) . $filePath);
        if ($realPath === false) {
            return false;
        }

        // The path must resolve inside the user's own upload directory
        $userUploadDir = realpath($this->baseDir . $userId);
        if ($userUploadDir === false) {
            return false;
        }

        if (strpos($realPath, $userUploadDir . DIRECTORY_SEPARATOR) === 0) {
            return $realPath;
        }

        return false;
    }

    /**
     * Defense-in-depth check that the resolved file is inside the user's upload directory.
     */
    private function isInAllowedDirectory(int $userId, string $fullPath): bool {
        $userUploadDir = realpath($this->baseDir . $userId);
        if ($userUploadDir === false) {
            return false;
        }

        return strpos($fullPath, $userUploadDir . DIRECTORY_SEPARATOR) === 0;
    }

    /**
     * Verifies the file is owned by the web server process.
     * Always returns true on platforms without POSIX functions (e.g. Windows).
     */
    private function isOwnedByWebServer(string $fullPath): bool {
        if (!function_exists('posix_getpwuid') || !function_exists('posix_geteuid')) {
            return true;
        }

        $fileOwner = fileowner($fullPath);
        if ($fileOwner === false) {
            return false;
        }

        $serverOwner = posix_getpwuid(posix_geteuid());
        return $serverOwner !== false && $fileOwner === $serverOwner['uid'];
    }
}
