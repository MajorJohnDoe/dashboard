<?php
namespace Dashboard\Core;

use Dashboard\Core\User;

class AuthMiddleware
{
    private User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function handle(): bool
    {
        // Apply security headers
        $this->applySecurityHeaders();

        // Check authentication
        if (!$this->user->isLoggedIn()) {
            $current_page = basename($_SERVER['REQUEST_URI']);
            if ($current_page !== 'login') {
                header('Location: /login');
                exit;
            }
        }
        return true;
    }

    private function applySecurityHeaders(): void
    {
        // Prevent clickjacking attacks
        header("X-Frame-Options: DENY");
        // Enable the browser's XSS protection
        header("X-XSS-Protection: 1; mode=block");
        // Prevent MIME type sniffing
        header("X-Content-Type-Options: nosniff");
        // Referrer Policy
        header("Referrer-Policy: strict-origin-when-cross-origin");
    }
}
?>