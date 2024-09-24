<?php
use Dashboard\Taskboard\ColumnController;
    
// Initialize variables
$postUrl = '/board/dialog/columns/new';
$responseTriggers = [];

$controller = new ColumnController($db, $user);
$activeBoardId = $user->getActiveTaskBoard();

// Handle POST request to add a new column
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['action'] == 'new') {

    $result = $controller->handleCreateColumn($activeBoardId, "New column", 60);

    if ($result['success']) {
        triggerResponse([
            "taskBoardColumnList" => true,
            "listBoardColumns" => true,
            "globalMessagePopupUpdate" => ['type' => 'success', 'message' => $result['message']]
        ]);
    } else {
        triggerResponse([
            "listBoardColumns" => true,
            "globalMessagePopupUpdate" => ['type' => 'error', 'message' => $result['message']]
        ]);
    }
}

// List board columns
$result = $controller->getColumnsForBoard($activeBoardId);
$boardColumns = $result['columns'];
?>

<form id="form_addColumn" hx-post="<?=htmlspecialchars($postUrl);?>" hx-target="#list-Columns-edit-board" hx-swap="innerHTML">
    <div class="flex-table edit-board-column-list">
        <div class="flex-row">
            <?php if ($boardColumns) : ?>
                <?php foreach ($boardColumns as $column) : ?>
                    <div class="flex-cell"><?=htmlspecialchars($column['column_name']);?></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="flex-row">
        <div class="flex-cell flex-right">
            <input type="submit" value="Add column" form="form_addColumn" class="btn btn-light-gray" style="margin:0;">
        </div>
    </div>
</form>