<?php    
use Dashboard\Taskboard\ColumnController;
use Dashboard\Taskboard\Task;

// Assume we've already instantiated $db and $user objects
$columnController = new ColumnController($db, $user);

$postUrl = '';
$formColumnName = '';
$formColumnFlag = '0';
$columnId = '';
$columnTaskOrderBy = '';
$formColumnDisplayTaskLimit = '';

// GET, EDIT column data modal
if (isset($_GET['action'], $_GET['column_id']) && $_GET['action'] == 'edit') {
    $columnId = $_GET['column_id'];
    $postUrl = '/column/dialog/edit/' . $columnId;

    $result = $columnController->getColumnDataById($columnId);
    
    if ($result['success']) {
        $columnData = $result['data'];
        $formColumnName = $columnData['name'];;
        $formColumnFlag = $columnData['flag'];
        $formColumnTaskOrderBy = $columnData['orderBy'];
        $formColumnDisplayTaskLimit = $columnData['displayLimit'];
    } 
}

// Process requests based on the method
// Save column changes
if ($_SERVER['REQUEST_METHOD'] === 'POST') { 
    if (isset($_POST['column_id'], $_GET['action']) && $_GET['action']) {
        $columnId = $_POST['column_id'] ?? '';
        $columnTitle = $_POST['column_name'] ?? '';
        $columnFlag = $_POST['column_flag'] ?? '0';
        $columnTaskOrderBy = $_POST['column-order-select'] ?? '0';
        $columnTaskDisplayLimit = $_POST['column_display_limit'] ?? '2';

        if (strlen($columnTitle) < 2) {
            triggerResponse(['globalMessagePopupUpdate' => ['type' => 'error', 'message' => 'Column title cannot be empty.']]);
        }

        $result = $columnController->updateColumnData($columnId, $columnTitle, $columnTaskOrderBy, $columnFlag, $columnTaskDisplayLimit);

        if ($result['success']) {
            triggerResponse(['taskBoardColumnList' => true, 'globalMessagePopupUpdate' => ['type' => 'success', 'message' => $result['message']]]);
        } else {
            triggerResponse(['globalMessagePopupUpdate' => ['type' => 'error', 'message' => $result['error']]]);
        }
    }
} 

// Process DELETE requests
// Delete column and its task children
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && isset($_GET['action'], $_GET['column_id']) && $_GET['action'] == 'edit') {
    $columnId = $_GET['column_id'] ?? '';

    $result = $columnController->deleteColumn($columnId);

    if ($result['success']) {
        triggerResponse(['taskBoardColumnList' => true, 'closeModalEvent' => true, 'globalMessagePopupUpdate' => ['type' => 'success', 'message' => $result['message']]]);
    } else {
        triggerResponse(['globalMessagePopupUpdate' => ['type' => 'error', 'message' => $result['error']]]);
    }
}

?>
<div id="dialog-column-settings" class="modal-container">
    <div class="dialog" style="width: 55rem;">
        <div class="dialog-header">
            <span>Edit column settings</span>
            <button class="close-modal-btn btn">X</button>
        </div>
        <div class="formOuter">
        <!-- end of modal header -->

            <form id="form_column_edit" hx-post="<?=$postUrl?>" hx-target="#dialog-column-settings .formOuter" hx-swap="beforeend">
                <div class="task-edit-grid nice-form-group" style="grid-template-columns: 3fr 1fr;">
                    <div class="left-column">
                        <div class="flex-table">
                            <div class="flex-row">
                                <div class="flex-cell flex-vertical-center">
                                    <label for="column_name">Column name:</label>
                                </div>
                                <div class="flex-cell">
                                    <input type="text" name="column_name" id="column_name" value="<?= htmlspecialchars(html_entity_decode($formColumnName)) ?>">
                                </div>
                            </div>
                            <div class="flex-row">
                                <div class="flex-cell flex-vertical-center">
                                    <label for="column-order-select">Order tasks by:</label>
                                </div>
                                <div class="flex-cell flex-vertical-center">
                                    <select name="column-order-select" id="column-order-select">
                                        <option value="0" <?php echo $formColumnTaskOrderBy == '0' ? 'selected' : ''; ?>>Date created</option>
                                        <option value="1" <?php echo $formColumnTaskOrderBy == '1' ? 'selected' : ''; ?>>Last modified</option>
                                        <option value="2" <?php echo $formColumnTaskOrderBy == '2' ? 'selected' : ''; ?>>Priority</option>
                                    </select>
                                </div>
                            </div>
                            <div class="flex-row">
                                <div class="flex-cell flex-vertical-center">
                                    <label for="column_flag">Column flag:</label>
                                </div>
                                <div class="flex-cell flex-vertical-center">
                                    <select name="column_flag" id="column_flag">
                                        <option value="0" <?php echo $formColumnFlag == '0' ? 'selected' : ''; ?>>None</option>
                                        <option value="1" <?php echo $formColumnFlag == '1' ? 'selected' : ''; ?>>Resolved flag</option>
                                    </select>
                                </div>
                            </div>
                            <div class="flex-row">
                                <div class="flex-cell flex-vertical-center">
                                    <label for="column_flag">Maximum displayed tasks:</label>
                                </div>
                                <div class="flex-cell flex-vertical-center">
                                    <select name="column_display_limit" id="column_display_limit">
                                        <option value="0" <?php echo $formColumnDisplayTaskLimit == '0' ? 'selected' : ''; ?>>15</option>
                                        <option value="1" <?php echo $formColumnDisplayTaskLimit == '1' ? 'selected' : ''; ?>>25</option>
                                        <option value="2" <?php echo $formColumnDisplayTaskLimit == '2' ? 'selected' : ''; ?>>35</option>
                                        <option value="3" <?php echo $formColumnDisplayTaskLimit == '3' ? 'selected' : ''; ?>>50</option>
                                        <option value="4" <?php echo $formColumnDisplayTaskLimit == '4' ? 'selected' : ''; ?>>80</option>
                                    </select>
                                </div>
                            </div>
                            <input type="hidden" name="column_id" value="<?= htmlspecialchars($columnId) ?>">
                        </div>
                    </div>
                    <div class="right-column">
                        <div class="flex-table">
                            <div class="flex-row">
                                <div class="flex-cell"><strong>Information</strong><br><p>Placeholder text here.</p></div>
                            </div>
                            <div class="flex-row">
                                <div class="flex-cell"><strong>Column flag?</strong><br>
                                <p>Resolved flag actually sets tasks that end up here as finished. It also sets a resolved date to the task so we can track it later.</p></div>
                            </div>
                        </div>
                    </div>
                    <div class="form-actions">
                        <div class="flex-table">
                            <div class="flex-row">
                                <div class="flex-cell flex-vertical-center">
                                    <?php if (isset($_GET['action']) && $_GET['action'] == 'edit'): ?>
                                        <button hx-delete="<?=$postUrl?>" 
                                                hx-target="body" 
                                                hx-swap="beforeend" 
                                                form="form_addTask" 
                                                class="btn btn-light-gray btn-hover-red" 
                                                tabindex="-1"
                                                hx-confirm="Delete this column and all its tasks?">
                                                Delete Column
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-cell flex-vertical-center flex-right">
                                    <input type="submit" value="Save column" form="form_column_edit" class="btn btn-green">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

        <!-- end of modal -->
        </div>
    </div>
</div>