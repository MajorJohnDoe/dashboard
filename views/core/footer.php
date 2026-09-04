
    <footer>
        <div id="message-area"></div>
    </footer>

    <?php
    // http.js must load before core.js (core.js depends on Csrf/Http/APP_ROUTES).
    // context.menu.js loads after http.js (uses Csrf/Http) and before core.js
    // (core.js calls ContextMenuManager.init()). This is the ONLY include of
    // context.menu.js — do not add it to route 'js' options (double-load
    // causes "Identifier 'ContextMenuManager' has already been declared").
    // Both deferred so they execute in order after DOM parsing.
    $assetVersion = fn(string $relPath): string => filemtime(BASE_DIR . $relPath) ?: time();
    ?>
    <script src="/assets/js/http.js?v=<?= $assetVersion('/assets/js/http.js') ?>" defer></script>
    <script src="/assets/js/context.menu.js?v=<?= $assetVersion('/assets/js/context.menu.js') ?>" defer></script>
    <script src="/assets/js/slide.panel.js?v=<?= $assetVersion('/assets/js/slide.panel.js') ?>" defer></script>
    <script src="/assets/js/core.js?v=<?= $assetVersion('/assets/js/core.js') ?>" defer></script>

</body>
</html>