<?php
namespace Dashboard\Core;

use Dashboard\Core\CsrfProtection;

/**
 * CSRF Middleware
 * 
 * Validates CSRF tokens on all state-changing requests (POST/PUT/DELETE).
 * Applied automatically by the Router for non-GET requests unless a route
 * opts out via the 'csrf' => false option.
 * 
 * On failure, responds with BOTH:
 *  - HTTP 403 + JSON body (for API-style / fetch callers)
 *  - HX-Trigger header with a toast event (for HTMX callers)
 */
class CsrfMiddleware
{
    /**
     * Handle the CSRF validation for the current request.
     * 
     * @return bool True if the request is valid (or method doesn't require validation)
     */
    public function handle(): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            return true;
        }

        $token = $_POST['csrf_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? null;

        if (!CsrfProtection::validate($token)) {
            $this->reject();
            return false;
        }

        return true;
    }

    /**
     * Send a 403 response with both a JSON body and an HTMX toast trigger.
     */
    private function reject(): void
    {
        http_response_code(403);

        // Toast for HTMX clients (fires globalMessagePopupUpdate listener in core.js)
        header('HX-Trigger: ' . json_encode([
            'globalMessagePopupUpdate' => [
                'type' => 'error',
                'message' => 'Session expired — please reload the page.'
            ]
        ]));

        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Invalid CSRF token'
        ]);
        exit;
    }
}
