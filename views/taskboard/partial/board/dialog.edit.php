<?php        
use Dashboard\Taskboard\BoardController;

// Initialize the controller
$controller = new BoardController($db, $user);

// Initialize variables
$board_result = null;
$boardId = null;
$BoardName = '';
$post_url = '/board/dialog/edit';

// User wants to edit board
if($_GET['action'] == 'edit') {
    $board_result = $controller->loadBoardDataById($user->getActiveTaskBoard());
    if($board_result) {
        $BoardName = $board_result[0]['tm_name'];
    }

    // Note: board columns are lazy-loaded by dialog.edit.columns.php via the
    // hx-get="/board/dialog/columns/edit" trigger below - no need to load them here.
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'edit':
            $boardId = $user->getActiveTaskBoard();
            $boardTitle = $_POST['board_title'] ?? '';
            $result = $controller->handleEditBoard($boardId, $boardTitle);
            break;
        default:
            $result = ['success' => false, 'message' => 'Invalid action'];
    }

    if ($result['success']) {
        triggerResponse(\Dashboard\Core\HtmxEvents::successResponse(
            $result['message'] ?? 'Operation successful',
            [\Dashboard\Core\HtmxEvents::TASK_BOARD_COLUMN_LIST => true]
        ));
    } else {
        triggerResponse(\Dashboard\Core\HtmxEvents::errorResponse($result['message'] ?? 'Operation failed'));
    }
}

// Handle DELETE requests
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && $_GET['action'] == 'edit') {
    $boardId = $user->getActiveTaskBoard();
    $result = $controller->handleDeleteBoard($boardId);

    if ($result['success']) {
        triggerResponse(\Dashboard\Core\HtmxEvents::successResponse(
            $result['message'] ?? 'Board deleted successfully',
            [
                \Dashboard\Core\HtmxEvents::TASK_BOARD_COLUMN_LIST => true,
                \Dashboard\Core\HtmxEvents::CLOSE_SPECIFIC_MODAL => ["dialog-board"],
            ]
        ));
    } else {
        triggerResponse(\Dashboard\Core\HtmxEvents::errorResponse($result['message'] ?? 'Failed to delete board'));
    }
}
?>

<div id="dialog-board-settings" class="modal-container">
    <div class="dialog">
        <div class="dialog-header">
            <span>Board settings</span>
            <button class="close-modal-btn btn">X</button>
        </div>
        <div class="formOuter">
        <!-- end of modal header -->

            <div class="nice-form-group">
                <div class="edit-grid">
                    <!-- Left Column -->
                    <div class="left-column">
                        <div class="flex-table">
                            <form   
                            id="form_editBoard" 
                            hx-post="<?=$post_url?>" 
                            hx-target="#dialog-board-settings" 
                            hx-swap="none">
                            <div class="flex-row">
                                <div class="flex-cell">
                                    <span class="form-label">Board title:</span>
<?php if ($board_result && isset($board_result[0]['user_id']) && $board_result[0]['user_id'] == $user->getUserId()): ?>
                                        <input type="text" name="board_title" id="board_title" value="<?=(isset($BoardName) ? htmlspecialchars($BoardName):'')?>">
                                    <?php else: ?>
                                        <div class="form-value"><?=(isset($BoardName) ? htmlspecialchars($BoardName):'')?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            </form>
                            <div class="flex-row ">
                                <div class="flex-cell">
                                    <span class="form-label">Board columns:</span>
                                    <div id="list-Columns-edit-board"
                                        hx-get="/board/dialog/columns/edit" 
                                        hx-trigger="load, listBoardColumns from:body" 
                                        hx-target="this" hx-swap="innerHTML">
                                    </div>
                                </div>
                            </div>
<?php if ($board_result && isset($board_result[0]['user_id']) && $board_result[0]['user_id'] == $user->getUserId()): ?>
                            <div class="flex-row">
                                <div class="flex-cell"><span class="form-label">Share board:</span></div>
                            </div>
                            <div class="flex-row nice-form-group">
                                <div class="flex-cell ">
                                    <input type="email" name="share_email" id="share_email" placeholder="Enter user email">
                                </div>
                                <div class="flex-cell flex-cell-shrink">
                                    <select name="access_level" id="share_access_level">
                                        <option value="read">Read access</option>
                                        <option value="write">Write access</option>
                                    </select>
                                </div>
                                <div class="flex-cell flex-cell-shrink">
                                    <button type="button" class="btn btn-light-gray" 
                                            hx-post="/board/share" 
                                            hx-include="#share_email,#share_access_level"
                                            hx-swap="none">
                                        Share
                                    </button>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="flex-row">
                                <div class="flex-cell">
                                    <span class="form-label">Board members:</span>
                                    <div id="board-members-list"
                                            hx-get="/board/members" 
                                            hx-trigger="load, boardMembersUpdate from:body"
                                            hx-target="this">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Right Column -->
                    <div class="right-column">
                        <div class="flex-table">
                            <div class="flex-row">
                                <span class="form-label">Attributes</span>
                            </div>
<?php if ($board_result && isset($board_result[0]['user_id']) && $board_result[0]['user_id'] == $user->getUserId()): ?>
                            <div class="flex-row">
                                <div class="flex-cell">
                                    <button class="open-modal-btn btn btn-blue" 
                                            hx-get="/label/edit" 
                                            hx-target="body" 
                                            hx-swap="beforeend">
                                            Edit labels
                                    </button>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- Actions -->
                    <div class="form-actions">
                        <div class="flex-table">
                            <div class="flex-row">
                            <?php if ($board_result && isset($board_result[0]['user_id']) && $board_result[0]['user_id'] == $user->getUserId()): ?>
                                <div class="flex-cell flex-vertical-center">                        
                                    <input 
                                        type="button" 
                                        value="Delete board" 
                                        hx-delete="<?=$post_url?>" 
                                        form="form_editBoard" 
                                        class="btn btn-light-gray btn-hover-red" 
                                        hx-confirm="Wish to continue deleting task board? Everything will be lost, columns, files, tasks etc..." 
                                        tabindex="-1">
                                </div>
                                <div class="flex-cell flex-vertical-center flex-right">                        
                                    <input type="submit" value="Update board" form="form_editBoard" class="btn btn-green">
                                </div>
                            <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    
        <!-- end of modal -->
        </div>
    </div>
</div>
