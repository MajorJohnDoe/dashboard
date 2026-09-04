/**
 * SlideOutPanel — a panel that slides out from the right edge of its host
 * dialog (pure CSS: the panel is an absolutely positioned child of the
 * dialog at left: 100%, z-index: -1, so it emerges from behind the dialog).
 *
 * The panel markup lives in a partial (see panel.recurrence.php) and marks
 * its host with a data attribute:
 *
 *   <aside id="my-panel" class="slide-panel" data-slide-panel-host="#some-dialog .dialog">...</aside>
 *   <script>SlideOutPanel.setup('my-panel');</script>
 *
 * setup() moves the panel into the host element (partials are appended to
 * <body>, and being a child of the host is what makes the CSS positioning
 * work) and adds .slide-panel-open to play the slide-in transition.
 *
 * If the declared host is not in the DOM (e.g. the panel was opened from
 * the task context menu with no task dialog open), the panel falls back to
 * being hosted on <body> with .slide-panel-standalone fixed positioning.
 *
 * API:
 *   SlideOutPanel.setup(panelId)     attach + open a panel
 *   SlideOutPanel.close(panelId)     close programmatically
 *
 * Closing triggers: [data-slide-panel-close] click, click outside both the
 * panel and its host dialog, the host dialog being removed from the DOM
 * (modal closed), Escape, or the server firing
 * HtmxEvents::CLOSE_RECURRENCE_PANEL (only one panel open at a time).
 */
const SlideOutPanel = (() => {
    const panels = new Map(); // id -> { panel, hostSelector, onDocClick, onKeydown }
    let activeId = null;

    function setup(panelId) {
        // Dedupe: keep only the newest instance with this id (partials are
        // appended to <body>, so it's the last), remove older strays.
        const instances = document.querySelectorAll('[id="' + panelId + '"]');
        const newest = instances[instances.length - 1] || null;
        instances.forEach(el => {
            if (el !== newest) el.remove();
        });

        // Toggle: clicking the trigger again while the panel is open closes
        // it instead of re-opening (e.g. "Make recurring" clicked twice).
        // Only toggle when the registered panel is still IN the DOM — if its
        // host modal was closed, the panel was removed with it but stayed
        // registered, and the toggle would swallow the re-open request.
        if (panels.has(panelId)) {
            const registered = panels.get(panelId).panel;
            if (registered && registered.isConnected) {
                close(panelId);
                return;
            }
            // Stale registration (panel removed with its host dialog) —
            // clean up and continue opening the freshly fetched panel.
            teardown(panelId);
        }

        const panel = newest;
        if (!panel) return;

        const hostSelector = panel.dataset.slidePanelHost || null;
        let host = hostSelector ? document.querySelector(hostSelector) : null;
        // The host must be VISIBLE, not merely present: a closed task modal
        // (#dialog-column-add-task) can linger in the DOM as display:none,
        // and attaching the panel to it would hide the panel too.
        const hostIsVisible = (el) => (typeof el.checkVisibility === 'function')
            ? el.checkVisibility()
            : el.getClientRects().length > 0;
        if (host && hostIsVisible(host)) {
            // Move the panel into the host dialog: CSS (left: 100%, z-index: -1)
            // does all the positioning from there.
            host.classList.add('slide-panel-host');
            host.appendChild(panel);
        } else {
            // Fallback: no visible host dialog (e.g. panel opened from the
            // task context menu, or the declared host is a hidden leftover).
            if (hostSelector) {
                console.warn('SlideOutPanel: no visible host for selector, falling back to body', hostSelector);
            }
            host = document.body;
            panel.classList.add('slide-panel-standalone');
            // Inline styles guarantee visibility even if the standalone CSS
            // class is missing/cached-out. maxWidth override is critical: the
            // base .slide-panel rule uses max-width: calc(100vw - 100%), which
            // computes to ZERO for a fixed element (100% = viewport width).
            Object.assign(panel.style, {
                position: 'fixed',
                left: 'auto',
                right: '0',
                top: '0',
                height: '100dvh',
                width: '26rem',
                maxWidth: '100vw',
                zIndex: '1000',
                borderRadius: '0',
            });
            host.appendChild(panel);
        }

        // Auto-open on next frame so the CSS transition plays.
        requestAnimationFrame(() => {
            panel.classList.add('slide-panel-open');
        });
        activeId = panelId;

        // Close buttons inside the panel (X / Cancel).
        panel.addEventListener('click', (e) => {
            if (e.target.closest('[data-slide-panel-close]')) {
                close(panelId);
            }
        });

        // Outside click: close when the click hits neither the panel nor its
        // host dialog. If the host dialog is gone (modal closed/refreshed),
        // remove the stray panel too.
        const onDocClick = (e) => {
            if (!panel.isConnected) {
                close(panelId);
                return;
            }
            if (e.target.closest('.slide-panel')) return;
            if (hostSelector && e.target.closest(hostSelector)) return;
            close(panelId);
        };
        // Defer so the click that opened the panel doesn't instantly close it
        // (the opening click bubbles to document after setup returns).
        setTimeout(() => document.addEventListener('click', onDocClick), 0);

        const onKeydown = (e) => {
            if (e.key === 'Escape') close(panelId);
        };
        document.addEventListener('keydown', onKeydown);

        panels.set(panelId, { panel, hostSelector, onDocClick, onKeydown });
    }

    function teardown(panelId) {
        const entry = panels.get(panelId);
        if (!entry) return;

        document.removeEventListener('click', entry.onDocClick);
        document.removeEventListener('keydown', entry.onKeydown);
        panels.delete(panelId);
        if (activeId === panelId) activeId = null;
    }

    function close(panelId) {
        const entry = panels.get(panelId || activeId);
        if (!entry) return;

        teardown(panelId || activeId);
        const { panel } = entry;
        if (!panel.parentNode) return;

        panel.classList.remove('slide-panel-open');
        panel.addEventListener('transitionend', function handler() {
            panel.remove();
            panel.removeEventListener('transitionend', handler);
        });
        // Fallback removal if transitionend doesn't fire
        setTimeout(() => { if (panel.parentNode) panel.remove(); }, 400);
    }

    function init() {
        // Server-driven close: any open slide panel closes on save success.
        if (typeof HTMX_EVENTS !== 'undefined') {
            document.body.addEventListener(HTMX_EVENTS.CLOSE_RECURRENCE_PANEL, () => {
                if (activeId) close(activeId);
            });
        }
    }

    return { setup, close, init };
})();

document.addEventListener('DOMContentLoaded', SlideOutPanel.init);
