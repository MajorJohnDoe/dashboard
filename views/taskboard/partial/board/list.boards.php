<?php
use Dashboard\Core\Interfaces\DatabaseInterface;
use Dashboard\Taskboard\BoardController;
use Dashboard\Taskboard\ColumnController;
use Dashboard\Core\User;


$boardObj = new BoardController($db, $user);
$columnController = new ColumnController($db, $user);
$boardResult = $boardObj->getAllBoards($user->getUserId());

echo '<div class="widget-grid">';

$boardCount = count($boardResult);
$totalSlots = min($boardCount, _TASKBOARD_MAXIMUM_BOARDS);

for ($i = 0; $i < $totalSlots; $i++) {
    renderBoardCell($boardResult[$i], $user, $columnController);
}

// Add empty slots if needed
$emptySlots = _TASKBOARD_MAXIMUM_BOARDS - $boardCount;
for ($i = 0; $i < $emptySlots; $i++) {
    echo '<div class="flex-cell box-shadow widget-empty">Empty</div>';
}

echo '</div>'; // Close the grid container

if ($boardCount == 0) {
    echo '<p>No Task boards have been added. Start by creating a new board.</p>';
}

function renderBoardCell($board, $user, ColumnController $columnController) {
    // Assuming $board is an associative array, not an object
    $boardId = $board['id'] ?? null;
    $boardName = $board['tm_name'] ?? 'Untitled Board'; // Use 'tm_name' or the actual field name from DB

    // Get columns for this board
    $columnsResult = $columnController->getColumnsForBoard($boardId);
    $columnCount = $columnsResult['success'] ? count($columnsResult['columns']) : 0;

    echo '<div class="widget box-shadow" style="cursor: pointer;" title="Visit task board" hx-get="/board/select?boardid=' . $boardId . '" hx-trigger="click">
            <h3>' . htmlspecialchars($boardName) . '</h3>
            <p>Columns: ' . $columnCount . '</p>
          </div>';
}
?>