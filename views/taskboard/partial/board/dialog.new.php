<?php
use Dashboard\Core\HtmxEvents;
use Dashboard\Taskboard\BoardController;

$controller = new BoardController($db, $user);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $controller->handleCreateBoard();

    if ($result['success']) {
        triggerResponse(HtmxEvents::successResponse(
            $result['message'],
            [
                HtmxEvents::NEW_BOARD => true,
                HtmxEvents::TASK_BOARD_COLUMN_LIST => true,
                HtmxEvents::CLOSE_SPECIFIC_MODAL => ["dialog-board-new"],
            ]
        ));
    } else {
        triggerResponse(HtmxEvents::errorResponse($result['message']));
    }
}
?>

<div id="dialog-board-new" class="modal-container">
    <div class="dialog dialog-sm">
        <div class="dialog-header">
            <span>Create New Board</span>
            <button class="close-modal-btn btn">X</button>
        </div>
        <div class="formOuter">
            <form id="form_createBoard" method="POST" hx-post="/board/dialog/new" hx-target="#dialog-board-new" hx-swap="outerHTML">
                <?= \Dashboard\Core\CsrfProtection::getTokenField() ?>
                <div class="nice-form-group" style="padding: 1.5rem;">
                    <div class="flex-table">
                        <div class="flex-row">
                            <div class="flex-cell">
                                <label for="boardName">Board Name:</label>
                                <input type="text" 
                                       name="boardName" 
                                       id="boardName" 
                                       autocomplete="off" 
                                       autofocus 
                                       placeholder="Enter board name..."
                                       required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-actions" style="padding: 0 1.5rem 1.5rem;">
                    <div class="flex-table">
                        <div class="flex-row">
                            <div class="flex-cell flex-right">
                                <button type="submit" class="btn btn-green">Create Board</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
