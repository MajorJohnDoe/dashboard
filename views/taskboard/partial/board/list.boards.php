<?php
use Dashboard\Core\Interfaces\DatabaseInterface;
use Dashboard\Core\User;
use Dashboard\Core\UserController;
use Dashboard\Taskboard\BoardController;
use Dashboard\Taskboard\ColumnController;

$boardObj = new BoardController($db, $user);
$columnController = new ColumnController($db, $user);
$userController = new UserController($db, $user);

$ownedBoards = $boardObj->getAllBoards();
$sharedBoards = $boardObj->getSharedBoards();
?>

<div class="boards-section">
    <h3>My Boards</h3>
    <div class="widget-grid">
        <?php foreach ($ownedBoards as $board): ?>
            <?php renderBoardCard($board, $user, $columnController, $boardObj, $userController); ?>
        <?php endforeach; ?>
        
        <?php renderCreateBoardCard(); ?>
    </div>
</div>

<?php if (!empty($sharedBoards)): ?>
<div class="boards-section">
    <h3>Shared With Me</h3>
    <div class="widget-grid">
        <?php foreach ($sharedBoards as $board): ?>
            <?php renderBoardCard($board, $user, $columnController, $boardObj, $userController); ?>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if (empty($ownedBoards) && empty($sharedBoards)): ?>
    <div class="empty-state">
        <p>No task boards yet. Create your first board to get started!</p>
    </div>
<?php endif; ?>

<?php
function renderBoardCard($board, $user, ColumnController $columnController, BoardController $boardObj, UserController $userController) {
    $boardId = $board['id'] ?? null;
    $boardName = $board['tm_name'] ?? 'Untitled Board';

    $columnsResult = $columnController->getColumnsForBoard($boardId);
    $columnCount = $columnsResult['success'] ? count($columnsResult['columns']) : 0;

    $members = $boardObj->getBoardMembersWithAvatars($boardId);
    ?>
    <div class="widget board-card" 
         title="Visit task board" 
         hx-get="/board/select?boardid=<?php echo $boardId; ?>" 
         hx-trigger="click">
        <h3><?php echo htmlspecialchars($boardName); ?></h3>
        <p><?php echo $columnCount; ?> column<?php echo $columnCount !== 1 ? 's' : ''; ?></p>
        
        <?php if (!empty($members)): ?>
        <div class="board-meta">
            <div class="board-members">
                <?php 
                $displayMembers = array_slice($members, 0, 4);
                $extraCount = count($members) - 4;
                
                foreach ($displayMembers as $index => $member): 
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
    <?php
}

function renderCreateBoardCard() {
    ?>
    <div class="widget create-board-card" 
         title="Create new board"
         hx-get="/board/dialog/new" 
         hx-target="body" 
         hx-swap="beforeend">
        <div class="create-board-content">
            <span class="create-board-icon">+</span>
            <h3>Create Board</h3>
            <p>Add a new task board</p>
        </div>
    </div>
    <?php
}
?>

<style>
    .boards-section {
        margin-bottom: 2rem;
    }
    
    .boards-section h3 {
        color: #374151;
        font-size: 1rem;
        font-weight: 600;
        margin: 0 0 1rem 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .board-card {
        display: flex;
        flex-direction: column;
        min-height: 170px;
    }
    
    .board-card h3 {
        margin: 0 0 0.5rem 0;
        font-size: 1rem;
        font-weight: 600;
        color: #1f2937;
    }
    
    .board-card p {
        margin: 0 0 1rem 0;
        font-size: 0.85rem;
        color: #6b7280;
    }
    
    .board-card .board-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: auto;
        padding-top: 0.75rem;
        border-top: 1px solid #f3f4f6;
    }
    
    .board-card .board-members {
        display: flex;
        align-items: center;
    }
    
    .board-card .board-member-avatar {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #fff;
        margin-left: -6px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .board-card .board-member-avatar:first-child {
        margin-left: 0;
    }
    
    .board-card .board-member-count {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #e5e7eb;
        color: #6b7280;
        font-size: 0.7rem;
        font-weight: 600;
        margin-left: -6px;
    }
    
    .create-board-card {
        background: #f9fafb;
        border: 2px dashed #d1d5db;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 120px;
    }
    
    .create-board-card:hover {
        border-color: #3896c9;
        background: #f0f9ff;
    }
    
    .create-board-card::before {
        display: none;
    }
    
    .create-board-content {
        text-align: center;
        color: #6b7280;
    }
    
    .create-board-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: #e5e7eb;
        font-size: 1.5rem;
        font-weight: 300;
        margin-bottom: 0.75rem;
        transition: background 0.2s ease;
    }
    
    .create-board-card:hover .create-board-icon {
        background: #3896c9;
        color: white;
    }
    
    .create-board-content h3 {
        margin: 0 0 0.25rem 0 !important;
        color: #374151 !important;
    }
    
    .create-board-content p {
        margin: 0;
        font-size: 0.85rem;
    }
</style>
