<?php
use Dashboard\Core\UserController;

/**
 * Board card partial.
 * Expects (from list.boards.php): $board, $columnCounts, $membersByBoard, $userController
 */
$boardId = $board['id'] ?? null;
$boardName = $board['tm_name'] ?? 'Untitled Board';
$columnCount = $columnCounts[$boardId] ?? 0;
$members = $membersByBoard[$boardId] ?? [];
?>
<div class="widget board-card"
     title="Visit task board"
     hx-get="/board/select?boardid=<?php echo (int) $boardId; ?>"
     hx-trigger="click">
    <h3><?php echo htmlspecialchars($boardName); ?></h3>
    <p><?php echo $columnCount; ?> column<?php echo $columnCount !== 1 ? 's' : ''; ?></p>

    <?php if (!empty($members)): ?>
    <div class="board-meta">
        <div class="board-members">
            <?php
            $displayMembers = array_slice($members, 0, 4);
            $extraCount = count($members) - 4;

            foreach ($displayMembers as $member):
                $avatarUrl = $userController->getUserAvatar($member['user_id']);
                $ownerClass = $member['is_owner'] ? ' owner' : '';
                ?>
                <img class="board-member-avatar<?php echo $ownerClass; ?>"
                     src="<?php echo htmlspecialchars($avatarUrl); ?>"
                     alt="Member avatar"
                     title="<?php echo htmlspecialchars($member['username'] ?? 'Member'); ?>">
            <?php endforeach; ?>

            <?php if ($extraCount > 0): ?>
                <span class="board-member-count">+<?php echo $extraCount; ?></span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
