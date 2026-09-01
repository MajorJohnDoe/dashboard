<?php
namespace Dashboard\Core;

class SecureSession
{
    // Configuration properties for session security
    private bool $checkBrowser = true;
    private int $checkIpBlocks = 4;
    private string $secureWord = 'defrgfhd&%&gfdgy5454y45y&%&45ytrhsgfhsgfhdgsfhfghty87654356786)(/&%&/()=)';
    private bool $regenerateId = true;

    // Constructor to set security options
    public function __construct(bool $checkBrowser = true, int $checkIpBlocks = 4, bool $regenerateId = true)
    {
        $this->checkBrowser = $checkBrowser;
        $this->checkIpBlocks = $checkIpBlocks;
        $this->regenerateId = $regenerateId;
    }

    // Initialize or resume a secure session
    public function open(): void
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['ss_fprint'])) {
            $_SESSION['ss_fprint'] = $this->generateFingerprint();
        }
        /* error_log("Session opened. ID: " . session_id() . " Data: " . print_r($_SESSION, true)); */
    }


    // Verify session integrity
    public function check(): bool
    {
        $this->regenerateId();
        return (isset($_SESSION['ss_fprint']) && $_SESSION['ss_fprint'] === $this->generateFingerprint());
    }

    // Set a session variable and ensure it's written to storage
    public function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
        /* error_log("Session set: $key = " . print_r($value, true)); */
        session_write_close();
        session_start();
    }

    // Retrieve a session variable
    public function get(string $key)
    {
        $value = $_SESSION[$key] ?? null;
        error_log("SecureSession get: $key = " . ($value === null ? 'null' : (is_bool($value) ? ($value ? 'true' : 'false') : $value)));
        return $value;
    }

    // Check if a session variable is set
    public function isSet(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    // Destroy the current session
    public function destroy(): void
    {
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    // Generate a unique fingerprint for session validation
    private function generateFingerprint(): string
    {
        $fingerprint = $this->secureWord;
        if ($this->checkBrowser) {
            $fingerprint .= $_SERVER['HTTP_USER_AGENT'];
        }
        if ($this->checkIpBlocks > 0) {
            $blocks = array_slice(explode('.', $_SERVER['REMOTE_ADDR']), 0, min($this->checkIpBlocks, 4));
            $fingerprint .= implode('.', $blocks);
        }
        return md5($fingerprint);
    }

    // Regenerate session ID to prevent session fixation
    private function regenerateId(): void
    {
        if ($this->regenerateId && function_exists('session_regenerate_id')) {
            session_regenerate_id(true);
        }
    }
}

?>