<?php        
use Dashboard\Taskboard\BoardController;
use Dashboard\Taskboard\ColumnController;

// Initialize the controller
$controller = new BoardController($db, $user);

// User wants to edit board
if($_GET['action'] == 'edit') {
    $post_url = '/board/dialog/edit';

    $board_result = $controller->loadBoardDataById($user->getActiveTaskBoard());
    if($board_result) {
        $boardId = $board_result[0]['id'];
        $BoardName = $board_result[0]['tm_name'];
    }

    $objColumn = new ColumnController($db, $user);
    $BoardColumns = $objColumn->getColumnsForBoard($user->user_id(), $user->getActiveTaskBoard());
} 

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'create':
            $boardName = $_POST['board_title'] ?? '';
            $result = $controller->handleCreateBoard($boardName);
            break;
        case 'edit':
            $boardId = $user->getActiveTaskBoard();
            $boardTitle = $_POST['board_title'] ?? '';
            $result = $controller->handleEditBoard($boardId, $boardTitle);
            break;
        default:
            $result = ['success' => false, 'message' => 'Invalid action'];
    }

    if ($result['success']) {
        $response = [
            "taskBoardColumnList" => true,
            "globalMessagePopupUpdate" => ['type' => 'success', 'message' => $result['message'] ?? 'Operation successful']
        ];

        if ($action === 'create' || $action === 'delete') {
            $response["closeSpecificModalEvent"] = ["dialog-board"];
        }

        triggerResponse($response);
    } else {
        triggerResponse([
            "globalMessagePopupUpdate" => ['type' => 'error', 'message' => $result['message'] ?? 'Operation failed']
        ]);
    }
}

// Handle DELETE requests
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && $_GET['action'] == 'edit') {
    $boardId = $user->getActiveTaskBoard();
    $result = $controller->handleDeleteBoard($boardId);

    if ($result['success']) {
        triggerResponse([
            "taskBoardColumnList" => true,
            "closeSpecificModalEvent" => ["dialog-board"],
            "globalMessagePopupUpdate" => ['type' => 'success', 'message' => $result['message'] ?? 'Board deleted successfully']
        ]);
    } else {
        triggerResponse([
            "globalMessagePopupUpdate" => ['type' => 'error', 'message' => $result['message'] ?? 'Failed to delete board']
        ]);
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
                <div class="task-edit-grid">
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
                                        <input type="text" name="board_title" id="board_title" value="<?=(isset($BoardName) ? htmlspecialchars($BoardName):'')?>">
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
                                <!--  
                                <div class="flex-row ">
                                    <div class="flex-cell">
                                        <span class="form-label">Board users:</span>
                                        <div class="board-members-container">
                                            <div class="board-members">You</div><div class="board-members">Lina</div>
                                        </div>
                                    </div>
                                </div> 
                                -->
                        </div>
                    </div>
                    <!-- Right Column -->
                    <div class="right-column">
                        <div class="flex-table">
                            <div class="flex-row">
                                <span class="form-label">Attributes</span>
                            </div>
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
                        </div>
                    </div>
                    <!-- Actions -->
                    <div class="form-actions">
                        <div class="flex-table">
                            <div class="flex-row">
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
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    
        <!-- end of modal -->
        </div>
    </div>
</div>