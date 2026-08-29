<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= \Dashboard\Core\CsrfProtection::getToken() ?>">
    <title><?= $options['title'] ?? 'Hyperboard' ?></title>
    <?php foreach ($options['css'] ?? [] as $css): ?>
        <link type="text/css" href="/assets/css/<?= $css ?>.css?id=<?=time()?>" rel="stylesheet">
    <?php endforeach; ?>
    <?php foreach ($options['js'] ?? [] as $js): ?>
        <script src="/assets/js/<?= $js ?>.js"></script>
    <?php endforeach; ?>
    <?php foreach ($options['external_js'] ?? [] as $js): ?>
        <script src="<?= $js ?>"></script>
    <?php endforeach; ?>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400..700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/htmx.org@1.9.11"></script>
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
                        <img src="<?=$user->getProfilePhotoPath()?>?t=<?=time()?>" alt="Profile Photo" id="header-profile-img">
                    </div>
                </div>

                <?php if (isset($user) && $user !== null): ?>
                    <a href="/logout" class="btn btn-green">Logout (<?= htmlspecialchars($user->user_id()) ?>)</a>
                <?php endif; ?>
            </li>
        </ul>
        <div id="global-system-message"><!-- Global backend message goes here --></div>
    </header>
