
    <footer>
        <div id="message-area"></div>
    </footer>

    <?php
    // http.js must load before core.js (core.js depends on Csrf/Http/APP_ROUTES).
    // Both deferred so they execute in order after DOM parsing.
    $assetVersion = fn(string $relPath): string => filemtime(BASE_DIR . $relPath) ?: time();
    ?>
    <script src="/assets/js/http.js?v=<?= $assetVersion('/assets/js/http.js') ?>" defer></script>
    <script src="/assets/js/core.js?v=<?= $assetVersion('/assets/js/core.js') ?>" defer></script>

</body>
</html>