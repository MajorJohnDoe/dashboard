<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= \Dashboard\Core\CsrfProtection::getToken() ?>">
    <title><?= $options['title'] ?? 'Hyperboard' ?></title>
    <?php
use Dashboard\Core\Sanitize;
    // Cache-bust with file modification time (stable until a file actually changes,
    // unlike time() which defeats caching on every request).
    $assetVersion = fn(string $relPath): string => filemtime(BASE_DIR . $relPath) ?: time();
    ?>
    <?php foreach ($options['css'] ?? [] as $css): ?>
        <link type="text/css" href="/assets/css/<?= $css ?>.css?v=<?= $assetVersion('/assets/css/' . $css . '.css') ?>" rel="stylesheet">
    <?php endforeach; ?>
    <?php foreach ($options['js'] ?? [] as $js): ?>
        <script src="/assets/js/<?= $js ?>.js?v=<?= $assetVersion('/assets/js/' . $js . '.js') ?>" defer></script>
    <?php endforeach; ?>
    <?php foreach ($options['external_js'] ?? [] as $js): ?>
        <script src="<?= $js ?>" defer></script>
    <?php endforeach; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400..700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/htmx.org@1.9.11" defer></script>
    <script>
        // Event names emitted by \Dashboard\Core\HtmxEvents (classes/Core/HtmxEvents.class.php).
        // Emitted server-side so PHP and JS can never drift apart.
        window.HTMX_EVENTS = Object.freeze(<?= json_encode(\Dashboard\Core\HtmxEvents::all(), JSON_FORCE_OBJECT) ?>);
        // Client-side mirror of _UPLOAD_MAX_BYTES (config.php) for instant
        // size validation before an upload request is even sent.
        window.ATTACHMENT_MAX_BYTES = <?= (int)(_UPLOAD_MAX_BYTES ?? 10485760) ?>;
        // Effective server limit: the smaller of the app limit and php.ini's
        // post_max_size (PHP drops the whole body above post_max_size, so
        // that is the real ceiling). 0 = unknown.
        window.ATTACHMENT_SERVER_MAX_BYTES = <?= (int)(min(
            (int)(_UPLOAD_MAX_BYTES ?? 10485760),
            \Dashboard\Core\UploadSizeGuard::iniBytes('post_max_size')
        )) ?>;
    </script>
</head>

<body>

    <aside class="nav-left">
        <ul>
            <li><a href="/"><img src="/assets/img/icon_tasks.png">Tasks</a></li>
            <li><a href="/stickynotes"><img src="/assets/img/icon_sticky_notes.png">Notes</a></li>
            <li><a href="/jobs"><img src="/assets/img/icon_jobs.svg">Jobs</a></li>
        </ul>
    </aside>

    <header>
        <ul>
            <li <?=($_SERVER['REQUEST_URI'] != '/board' ? 'style="display:none;"':'')?>>
                <button 
                    class="open-modal-btn btn btn-blue" 
                    hx-get="/board/dialog/edit" 
                    hx-target="body" 
                    hx-swap="beforeend">
                    Edit board
                </button>
            </li>
            <li <?=($_SERVER['REQUEST_URI'] != '/board' ? 'style="display:none;"':'')?>>
                <button 
                    class="open-modal-btn btn btn-blue" 
                    data-modal-target="#dialog-view-task-history"
                    hx-get="/calendar/dialog/init/true" 
                    hx-target="body" 
                    hx-swap="beforeend">
                    History
                </button>
            </li>
            <li <?=($_SERVER['REQUEST_URI'] != '/board' ? 'style="display:none;"':'')?>>
                <button 
                    class="open-modal-btn btn btn-blue" 
                    data-modal-target="#dialog-schedule-list"
                    hx-get="/schedule/dialog/init" 
                    hx-target="body" 
                    hx-swap="beforeend">
                    Recurring
                </button>
            </li>
            <li class="align-right">
                <div class="notification-wrapper" style="position: relative;">
                    <button 
                        class="btn btn-icon notifications-btn"
                        data-panel-modal="panel-modal-notifications"
                        hx-get="/notifications"
                        hx-target="#notifications-container"
                        hx-swap="innerHTML"
                        hx-on::before-request="document.getElementById('notifications-container').innerHTML = ''"
                        title="Notifications">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                    </button>
                    <div id="notifications-container"></div>
                </div>

                <div class="profile-settings open-modal-btn" 
                     data-modal-target="#modal-profile-settings"
                     hx-get="/account/settings" 
                     hx-target="body" 
                     hx-swap="beforeend">
                    <div class="profile-photo" id="header-profile-photo">
                        <img src="<?=($user !== null ? $user->getProfilePhotoPath() : '/assets/img/default_profile.jpg')?>?t=<?=time()?>" alt="Profile Photo" id="header-profile-img">
                    </div>
                </div>

                <?php if (isset($user) && $user !== null): ?>
                    <a href="/logout" class="btn btn-green">Logout (<?= Sanitize::e($user->user_id()) ?>)</a>
                <?php endif; ?>
            </li>
        </ul>
        <div id="global-system-message"><!-- Global backend message goes here --></div>
    </header>
