<?php
use Dashboard\Taskboard\BoardController;

$controller = new BoardController($db, $user);
$boardId = $user->getActiveTaskBoard();

// Get board members
$boardUsers = $controller->getBoardUsers($boardId);
?>

<div class="flex-table flex-table-bg-ed">
    <?php if ($boardUsers && count($boardUsers) > 0): ?>
    <?php foreach ($boardUsers as $boardUser): ?>
    <div class="flex-row">
        <div class="flex-cell flex-vertical-center"><?= htmlspecialchars($boardUser['user_username']) ?></div>
        <div class="flex-cell flex-vertical-center"><?= htmlspecialchars($boardUser['user_email']) ?></div>
        <div class="flex-cell flex-vertical-center flex-cell-shrink">
            <span class="member-access <?= $boardUser['access_level'] ?>"><?= ucfirst($boardUser['access_level']) ?></span>
            <?php if ($boardUser['status'] === 'pending'): ?>
                <span class="member-status pending">Pending</span>
            <?php endif; ?>
        </div>
        <?php if ($boardUser['shared_by_user_id'] == $user->getUserId()): ?>
        <div class="flex-cell flex-cell-shrink">
            <select class="access-level-select"
                    hx-put="/board/share/access"
                    hx-vals='{"user_id": "<?= $boardUser['user_id'] ?>"}'
                    hx-include="this"
                    hx-swap="none"
                    name="access_level">
                <option value="read" <?= $boardUser['access_level'] === 'read' ? 'selected' : '' ?>>Read</option>
                <option value="write" <?= $boardUser['access_level'] === 'write' ? 'selected' : '' ?>>Write</option>
            </select>
        </div>
        <div class="flex-cell flex-cell-shrink">
            <button class="btn btn-red btn-small" 
                    hx-delete="/board/share" 
                    hx-vals='{"user_id": "<?= $boardUser['user_id'] ?>"}'
                    hx-confirm="Remove <?= htmlspecialchars($boardUser['user_username']) ?> from this board?"
                    hx-swap="none">
                X
            </button>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
    <div class="flex-row">
        <div class="flex-cell"><div class="no-members">No shared members yet</div></div>
    </div>
    <?php endif; ?>
</div>
