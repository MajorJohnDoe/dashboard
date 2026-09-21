/**
 * Shared HTTP + CSRF + routing helpers.
 *
 * Loaded on every page (before core.js). Provides:
 *  - Csrf.getToken()          single source of CSRF token retrieval
 *  - Http.fetchJson()         fetch wrapper: ok-check, JSON parse, toast on error
 *  - Http.postForm()          FormData POST helper with CSRF attached
 *  - APP_ROUTES               central URL map (mirrors HTMX_EVENTS pattern)
 */

// Central URL constants - keep in sync with classes/Routes/*.php
const APP_ROUTES = Object.freeze({
    ACCOUNT_SETTINGS: '/account/settings',
    NOTIFICATIONS_CHECK: '/notifications/check',
    NOTIFICATIONS_LIST: '/notifications/list',
    NOTIFICATIONS_MARK_READ: '/notifications/mark-read',
    BOARD_SHARE: '/board/share',
    COLUMN_SAVE_ORDER: '/column/save-column-order',
    TASK_MOVE_TO_COLUMN: '/task/move-to-column',
    TAB_ORDER: '/ui/tab-order',
    JOBS_BATCH: '/jobs/batch',
});

// Single CSRF token retrieval (meta tag is set in header.php for all pages)
const Csrf = (() => {
    function getToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.content) return meta.content;

        const input = document.querySelector('input[name="csrf_token"]');
        if (input && input.value) return input.value;

        return '';
    }

    return { getToken };
})();

// Shared fetch helpers
const Http = (() => {
    /**
     * Show an error toast via the global message popup event.
     */
    function toastError(message) {
        document.body.dispatchEvent(new CustomEvent(HTMX_EVENTS.GLOBAL_MESSAGE, {
            detail: { type: 'error', message }
        }));
    }

    /**
     * fetch() wrapper: checks response.ok, parses JSON, shows a toast on failure.
     *
     * @param {string} url
     * @param {Object} options  fetch options (method, headers, body...)
     * @param {Object} [opts]   { errorMessage: string } toast message on failure
     * @return {Promise<Object>} parsed JSON response
     */
    async function fetchJson(url, options = {}, opts = {}) {
        let response;
        try {
            response = await fetch(url, options);
        } catch (networkError) {
            const msg = opts.errorMessage || 'Network error';
            toastError(msg);
            throw networkError;
        }

        if (!response.ok) {
            const msg = (opts.errorMessage || 'Request failed') + ` (HTTP ${response.status})`;
            toastError(msg);
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        try {
            return await response.json();
        } catch (parseError) {
            const msg = opts.errorMessage || 'Invalid server response';
            toastError(msg);
            throw parseError;
        }
    }

    /**
     * POST a FormData body with the CSRF token attached.
     * Returns parsed JSON; shows toast on failure.
     */
    async function postForm(url, formData, opts = {}) {
        formData.append('csrf_token', Csrf.getToken());
        return fetchJson(url, { method: 'POST', body: formData }, opts);
    }

    /**
     * POST a JSON body with CSRF header attached.
     * Returns parsed JSON; shows toast on failure.
     */
    async function postJson(url, data, opts = {}) {
        return fetchJson(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': Csrf.getToken(),
            },
            body: JSON.stringify(data),
        }, opts);
    }

    return { fetchJson, postForm, postJson, toastError };
})();
