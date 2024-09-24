<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
                <div class="profile-settings open-modal-btn" 
                     data-modal-target="#modal-profile-settings"
                     hx-get="/account/settings" 
                     hx-target="body" 
                     hx-swap="beforeend">
                    <div class="profile-photo">
                        <img src="<?=$user->getProfilePhotoPath()?>" alt="Profile Photo">
                    </div>
                </div>

                <?php if (isset($user) && $user !== null): ?>
                    <a href="/logout" class="btn btn-green">Logout (<?= htmlspecialchars($user->user_id()) ?>)</a>
                <?php endif; ?>
            </li>
        </ul>
        <div id="global-system-message"><!-- Global backend message goes here --></div>
    </header>



