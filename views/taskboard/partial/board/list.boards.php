<?php
use Dashboard\Core\UserController;
use Dashboard\Taskboard\BoardController;
use Dashboard\Taskboard\ColumnController;

$boardObj = new BoardController($db, $user);
$columnController = new ColumnController($db, $user);
$userController = new UserController($db, $user);

$ownedBoards = $boardObj->getAllBoards();
$sharedBoards = $boardObj->getSharedBoards();

// Batch-load column counts and members for all boards (3 queries total
// instead of 2 per board)
$allBoardIds = array_merge(
    array_column($ownedBoards, 'id'),
    array_column($sharedBoards, 'id')
);

$columnCounts = $columnController->getColumnCountsForBoards($allBoardIds);
$membersByBoard = $boardObj->getBoardMembersWithAvatarsForBoards($allBoardIds);
?>

<div class="boards-section">
    <h3>My Boards</h3>
    <div class="widget-grid">
        <?php foreach ($ownedBoards as $board): ?>
            <?php include __DIR__ . '/board.card.php'; ?>
        <?php endforeach; ?>
        
        <?php include __DIR__ . '/board.card.create.php'; ?>
    </div>
</div>

<?php if (!empty($sharedBoards)): ?>
<div class="boards-section">
    <h3>Shared With Me</h3>
    <div class="widget-grid">
        <?php foreach ($sharedBoards as $board): ?>
            <?php include __DIR__ . '/board.card.php'; ?>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if (empty($ownedBoards) && empty($sharedBoards)): ?>
    <div class="empty-state">
        <p>No task boards yet. Create your first board to get started!</p>
    </div>
<?php endif; ?>
