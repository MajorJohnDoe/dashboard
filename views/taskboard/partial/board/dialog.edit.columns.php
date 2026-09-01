<?php
use Dashboard\Taskboard\ColumnController;
    
// Initialize variables
$postUrl = '/board/dialog/columns/new';

$controller = new ColumnController($db, $user);
$activeBoardId = $user->getActiveTaskBoard();

// Handle POST request to add a new column
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['action'] == 'new') {

    $result = $controller->handleCreateColumn($activeBoardId, "New column", 60);

    if ($result['success']) {
        triggerResponse(\Dashboard\Core\HtmxEvents::successResponse(
            $result['message'],
            [\Dashboard\Core\HtmxEvents::TASK_BOARD_COLUMN_LIST => true, "listBoardColumns" => true]
        ));
    } else {
        triggerResponse(\Dashboard\Core\HtmxEvents::errorResponse($result['message'], ["listBoardColumns" => true]));
    }
}

// List board columns
$result = $controller->getColumnsForBoard($activeBoardId);
$boardColumns = $result['success'] ? ($result['columns'] ?? []) : [];
?>

<form id="form_addColumn" hx-post="<?=htmlspecialchars($postUrl);?>" hx-target="#list-Columns-edit-board" hx-swap="innerHTML">
    <?= \Dashboard\Core\CsrfProtection::getTokenField() ?>
    <div class="flex-table edit-board-column-list">
        <div class="flex-row">
            <?php if ($boardColumns) : ?>
                <?php foreach ($boardColumns as $column) : ?>
                    <div class="flex-cell"><?=htmlspecialchars($column['column_name']);?></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($controller->validateBoardWriteAccess($activeBoardId)): ?>
    <div class="flex-row">
        <div class="flex-cell flex-right">
            <input type="submit" value="Add column" form="form_addColumn" class="btn btn-light-gray" style="margin:0;">
        </div>
    </div>
    <?php endif; ?>
</form>
