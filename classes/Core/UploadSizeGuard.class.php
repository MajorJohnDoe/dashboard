<?php
namespace Dashboard\Core;

/**
 * UploadSizeGuard middleware.
 *
 * When a POST body exceeds php.ini's post_max_size, PHP silently drops
 * $_POST and $_FILES entirely. Without this guard such a request would
 * fail later with a confusing CSRF/validation error (or crash a handler
 * expecting fields). This middleware detects the condition up-front and
 * returns a clean 413 with an HTMX toast.
 *
 * Detection: a POST/PUT/DELETE/PATCH with a non-zero Content-Length whose
 * body PHP did not parse (empty $_POST for form content types) is treated
 * as "body too large". Also checks Content-Length against post_max_size
 * directly when the ini value is readable.
 *
 * Applied globally in index.php (before CSRF middleware).
 */
class UploadSizeGuard
{
    public function handle(): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            return true;
        }

        $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLength <= 0) {
            return true;
        }

        // Direct check against post_max_size when the ini value is available.
        $postMaxSize = $this->iniBytes('post_max_size');
        if ($postMaxSize > 0 && $contentLength > $postMaxSize) {
            $this->reject($postMaxSize);
            return false;
        }

        // Fallback detection: PHP dropped the body. Only applies to POST —
        // htmx sends PUT/DELETE with a form content-type header but an empty
        // body, which must NOT be treated as an oversized request.
        // For POST with a form content type, a non-empty body always
        // populates $_POST (at least the CSRF field). Empty $_POST + empty
        // $_FILES with a real body means the parser bailed — almost always
        // post_max_size overflow.
        $contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
        $isFormContent = str_contains($contentType, 'application/x-www-form-urlencoded')
            || str_contains($contentType, 'multipart/form-data');

        if ($method === 'POST' && $isFormContent && empty($_POST) && empty($_FILES)) {
            $this->reject($postMaxSize);
            return false;
        }

        return true;
    }

    /**
     * Send a 413 response with both a JSON body and an HTMX toast trigger.
     *
     * @param int $postMaxSize Effective post_max_size in bytes (0 = unknown),
     *                         included in the message so the user knows the real limit.
     */
    private function reject(int $postMaxSize = 0): void
    {
        http_response_code(413);

        $limitText = $postMaxSize > 0
            ? ' The server accepts at most ' . round($postMaxSize / 1048576, 1) . ' MB per upload.'
            : '';

        header('HX-Trigger: ' . json_encode([
            'globalMessagePopupUpdate' => [
                'type' => 'error',
                'message' => 'Upload too large — the file exceeds the server limit.' . $limitText
            ]
        ]));

        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Request body exceeds the maximum allowed size.'
        ]);
        exit;
    }

    /**
     * Convert an ini shorthand value (e.g. "12M", "1G") to bytes.
     * Returns 0 when the directive is not set or unparsable.
     */
    public static function iniBytes(string $directive): int
    {
        $value = ini_get($directive);
        if ($value === false || $value === '' || $value === '-1') {
            return 0;
        }

        $value = trim($value);
        $unit = strtolower(substr($value, -1));
        $number = (int)$value;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => (int)$value,
        };
    }
}
