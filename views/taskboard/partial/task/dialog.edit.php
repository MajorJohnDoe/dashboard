<?php
use Dashboard\Taskboard\TaskController;
use Dashboard\Taskboard\ColumnController;

// User wants to add a new task to column
if($_GET['action'] == 'new' && isset($_GET['column_id'])) {
    $post_url = '/task/dialog/'.$_GET['action'].'/'.$_GET['column_id'];
}

    // User wants to edit a task
    if($_GET['action'] == 'edit' && isset($_GET['column_id']) && isset($_GET['task_id'])) {
        $post_url = '/task/dialog/'.$_GET['action'].'/'.$_GET['column_id'].'/'.$_GET['task_id'];

        $task = new TaskController($db, $user);
        $result = $task->handleGetTaskDetails($_GET['task_id']);
        if ($result['success']) {
            $taskData = $result['task'];
            $taskID = $taskData['id'];
            $taskTitle = $taskData['title'];
            $taskDescription = $taskData['description'];
            $taskChecklist = $taskData['checklist'];
            $taskPriority = $taskData['priority'];
            $taskSelectLabels = $taskData['labels'];
            $taskIsResolved = $taskData['resolved_date'];
        }
    }


    // #MARK: NEW task - form post
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['action'] == 'new' && isset($_POST['column_id'])) {
        $task = new TaskController($db, $user);
        $result = $task->handleCreateTask($_POST);
    
        if ($result['success'] == true) {
            triggerResponse([
                "taskBoardColumnList" => true, 
                "closeModalEvent" => true, 
                "globalMessagePopupUpdate" => ['type' => 'success', 'message' => $result['message']]
            ]);
        } else {
            triggerResponse(["globalMessagePopupUpdate" => ['type' => 'error', 'message' => $result['message']]]);
        }
    }
    

    // #MARK: DELETE task - form post
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && $_GET['action'] == 'edit' && isset($_GET['task_id'])) {
        $taskId = $_GET['task_id'];

        $task = new TaskController($db, $user);
        $result = $task->handleDeleteTask($taskId);

        if ($result['success'] == true) {
            triggerResponse([
                "taskBoardColumnList" => true, 
                "closeModalEvent" => true, 
                "refreshTaskHistory" => true, 
                "globalMessagePopupUpdate" => ['type' => 'success', 'message' => $result['message']]
            ]);
        } else {
            triggerResponse(["globalMessagePopupUpdate" => ['type' => 'error', 'message' => $result['message']]]);
        }
    }


    // #MARK: EDIT task - form post
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['action'] == 'edit' && isset($_POST['column_id']) && isset($_POST['task_id'])) {
        $task = new TaskController($db, $user);
        $result = $task->handleUpdateTask($_POST);

        if ($result['success'] == true) {
            $triggers = [
                "taskBoardColumnList" => true, 
                "refreshTaskHistory" => true, 
                "globalMessagePopupUpdate" => ['type' => 'success', 'message' => $result['message']]
            ];

            if (isset($_POST['move_task_column_id']) && $_POST['move_task_column_id'] != '0') {
                $triggers["closeModalEvent"] = true;
            } else {
                $triggers["refreshModal"] = true;
            }

            triggerResponse($triggers);
        } else {
            triggerResponse(["globalMessagePopupUpdate" => ['type' => 'error', 'message' => $result['message']]]);
        }
    }
?>
<div id="dialog-column-add-task" 
     class="modal-container"
     hx-get="/task/dialog/edit/<?=$_GET['column_id']?><?=(isset($_GET['task_id']) ? '/'.$_GET['task_id'] : '')?>"
     hx-trigger="refreshModal from:body"
     hx-target="#dialog-column-add-task"
     hx-swap="outerHTML"
     >

    <div class="dialog" style="width: 70rem;">
        <div class="dialog-header">
            <span>Task</span>
            <button class="close-modal-btn btn">X</button>
        </div>
        <div class="formOuter">
            <!-- end of modal header -->
            <form id="form_addTask" method="POST" hx-post="<?=$post_url?>" hx-target="#dialog-column-add-task .formOuter" hx-swap="beforeend">
                <?php
                    // Message to user that task has a resolved date from moving task to a column that has a resolve flag.
                    // Resolve flag on a column sets task task_resolved_date, kinda like a archived task
                    if(isset($taskIsResolved) && $taskIsResolved != null) { 
                        $taskColumn = new ColumnController($db, $user);
                        $taskColumns = $taskColumn->getColumnsForBoard($user->getActiveTaskBoard());

                            echo '
                            <div class="flex-table nice-form-group" style="background: #c6e1c3; border-radius: 0.5rem; margin: 10px 10px 10px 10px;">
                                <div class="flex-row">
                                    <div class="flex-cell flex-vertical-center flex-cell-shrink">
                                        <label for="task_title">Move task back to a column:</label>
                                    </div>
                                    <div class="flex-cell flex-vertical-center flex-cell-shrink">
                                        <select name="move_task_column_id" id="move_task_column_id">
                                            <option value="0">pick a column</option>';

                                        if($taskColumns != false) {
                                            foreach ($taskColumns['columns'] as $column) {
                                                echo '<option value="'.$column['id'].'">'.htmlspecialchars(html_entity_decode($column['column_name'], ENT_QUOTES, 'UTF-8')).'</option>';
                                            }
                                        }
                    
                            echo '      </select>
                                    </div>
                                    <div class="flex-cell flex-vertical-center flex-cell-shrink">
                                        <input type="hidden" name="task_id" value="'.(isset($_GET['task_id']) ? htmlspecialchars($_GET['task_id'], ENT_QUOTES, 'UTF-8') : null).'">
                                        <input type="submit" value="Move task" form="form_addTask" class="btn btn-green">
                                    </div>
                                </div>
                            </div>
                            ';
                    }
                ?>
                <div class="nice-form-group">
                    <div class="task-edit-grid">
                        <!-- Left Column -->
                        <div class="left-column">
                            <div class="flex-table">
                                <div class="flex-row">
                                    <div class="flex-cell">
                                        <label for="task_title">Task title:</label>
                                        <input type="text" name="task_title" id="task_title" autocomplete="off" autofocus value="<?=(isset($taskTitle) ? htmlspecialchars($taskTitle, ENT_QUOTES, 'UTF-8') : '')?>">
                                    </div>
                                </div>
                                <div class="flex-row">
                                    <div class="flex-cell flex-cell-shrink flex-vertical-center" style="position: relative;">
                                        <div style="position: relative;">
                                            <input 
                                                autocomplete="off"
                                                type="search" 
                                                name="search-label" 
                                                id="search-label" 
                                                placeholder="Search for label" 
                                                hx-get="/task/label/search"
                                                hx-trigger="input changed delay:100ms, focus, search-label"
                                                hx-target="#search-label-result" 
                                                hx-swap="innerHTML"
                                                data-type="small-popup"
                                                data-popup-wrapper="search-label-result">
                                            <div class="small-popup-box-wrapper">
                                                <div id="search-label-result"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
                                    // Container for selected current task labels
                                    $selectedLabels = '';
                                    if (isset($taskSelectLabels) && is_array($taskSelectLabels) && count($taskSelectLabels) > 0) {
                                        foreach ($taskSelectLabels as $label) {
                                            $selectedLabels .= '<input type="hidden" id="hiddenLabelId_' . htmlspecialchars($label['label_id'], ENT_QUOTES, 'UTF-8') . '" name="selectedLabels[]" value="' . htmlspecialchars($label['label_id'], ENT_QUOTES, 'UTF-8') . '">';
                                            $selectedLabels .= '<span id="visualLabelId_' . htmlspecialchars($label['label_id'], ENT_QUOTES, 'UTF-8') . '" style="background-color: ' . htmlspecialchars($label['label_color'], ENT_QUOTES, 'UTF-8') . ';">' . htmlspecialchars($label['label_name'], ENT_QUOTES, 'UTF-8') . '</span>';
                                        }
                                    }
                                    ?>
                                    <div class="flex-cell flex-cell-vcenter" id="selectedLabelsContainer"><?=$selectedLabels?></div>
                                </div>
                                <div class="flex-row">
                                    <div class="flex-cell">
                                        <label for="task_desc">Task description:</label><br>
                                        <textarea name="task_desc" id="task_desc" class="tinymce_editor" style="height: 0rem; width: 100%; position: absolute; visibility: hidden;" aria-hidden="true"><?=(isset($taskDescription) ? htmlspecialchars($taskDescription, ENT_QUOTES, 'UTF-8') : '')?></textarea>
                                    </div>
                                </div>
                                <div class="flex-row task_checklist"></div>
                                <?php
                                if (isset($taskChecklist) && $taskChecklist != null) {
                                    echo '
                                        <div
                                            hx-get="/task/checklist/edit/'.(isset($_GET['task_id']) ? $_GET['task_id'] : '').'" 
                                            hx-trigger="load, taskChecklist from:body" 
                                            hx-target="this" hx-swap="innerHTML">
                                        </div>';
                                }
                                ?>
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="right-column">
                            <div class="flex-table">
                                <div class="flex-row">
                                    <div class="flex-cell">
                                        <span class="form-label">Add to task</span>
                                        <!-- Labels Button -->
                                        <button class="open-modal-btn btn btn-dark-gray" 
                                                style="margin: 0.5rem 0 0 0; width: 100%;" 
                                                hx-get="/label/edit"
                                                hx-target="body" 
                                                hx-swap="beforeend"
                                                tabindex="-1">
                                            Edit labels
                                        </button>
                                        <!-- Checklist Button -->
                                        <button type="button" 
                                                class="btn btn-dark-gray" 
                                                style="margin: 0.5rem 0 0 0; width: 100%;" 
                                                hx-get="/task/checklist/new/0"
                                                hx-target="#dialog-column-add-task .task_checklist" 
                                                hx-swap="innerHTML" 
                                                tabindex="-1"
                                                <?= isset($taskChecklist) && $taskChecklist != null ? 'disabled' : '' ?>>
                                            Checklist
                                        </button>
                                    </div>
                                </div>
                                <div class="flex-row">
                                    <div class="flex-cell">
                                        <span class="form-label">Priority</span>
                                        <div class="task-priority-container">
                                            <!-- Lowest Priority -->
                                            <input type="radio" id="priority-lowest" name="task_priority" value="0" class="task-priority-input"
                                                <?= !isset($taskPriority) || $taskPriority == "0" ? 'checked' : '' ?>>
                                            <label for="priority-lowest" class="task-priority-label priority-lowest">Lowest</label>
                                            
                                            <!-- Low Priority -->
                                            <input type="radio" id="priority-low" name="task_priority" value="1" class="task-priority-input"
                                                <?= isset($taskPriority) && $taskPriority == "1" ? 'checked' : '' ?>>
                                            <label for="priority-low" class="task-priority-label priority-low">Low</label>
                                            
                                            <!-- Alarming Priority -->
                                            <input type="radio" id="priority-alarming" name="task_priority" value="2" class="task-priority-input"
                                                <?= isset($taskPriority) && $taskPriority == "2" ? 'checked' : '' ?>>
                                            <label for="priority-alarming" class="task-priority-label priority-alarming">Alarming</label>
                                            
                                            <!-- Critical Priority -->
                                            <input type="radio" id="priority-critical" name="task_priority" value="3" class="task-priority-input"
                                                <?= isset($taskPriority) && $taskPriority == "3" ? 'checked' : '' ?>>
                                            <label for="priority-critical" class="task-priority-label priority-critical">Critical</label>
                                            
                                            <!-- Highest Priority -->
                                            <input type="radio" id="priority-highest" name="task_priority" value="4" class="task-priority-input"
                                                <?= isset($taskPriority) && $taskPriority == "4" ? 'checked' : '' ?>>
                                            <label for="priority-highest" class="task-priority-label priority-highest">Highest</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex-row">
                                    <div class="flex-cell">
                                        <span class="form-label">Actions</span><br>
                                        <!-- Duplicate task Button -->
                                        <div style="position: relative;">
                                            <button class="btn btn-dark-gray" 
                                                    style="margin: 0.5rem 0 0 0; width: 100%;" 
                                                    id="duplicate-task-btn"
                                                    hx-get="/task/duplicate/dupe/<?=(isset($_GET['task_id']) ? $_GET['task_id'] : '')?>"
                                                    hx-target="#duplicate-task-box" 
                                                    hx-swap="innerHTML"
                                                    tabindex="-1"
                                                    data-type="small-popup"
                                                    data-popup-wrapper="duplicate-task-box"
                                                    <?=(isset($_GET['action']) && $_GET['action'] == 'new'? 'disabled':'')?>>
                                                    Duplicate
                                                </button>
                                            <div class="small-popup-box-wrapper">
                                                <div id="duplicate-task-box"><!-- content goes here --></div>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>  <!-- End flex table -->
                        </div> <!-- End right-column -->

                        <input type="hidden" value="<?=$_GET['column_id']?>" name="column_id" class="btn btn-green">
                        <input type="hidden" value="<?=(isset($_GET['task_id']) ? $_GET['task_id'] : '')?>" name="task_id">
                    </div>
                </div>
            </form>
            <!-- Actions -->
            <div class="form-actions">
                <div class="flex-table">
                    <div class="flex-row">
                        <div class="flex-cell">
                        <?php if (isset($_GET['action']) && $_GET['action'] == 'edit'): ?>
                            <form id="form_deleteTask" hx-delete="<?=(isset($post_url) ? $post_url : '')?>" hx-target="body" hx-swap="beforeend">
                                <input type="hidden" name="task_id" value="<?=(isset($_GET['task_id']) ? htmlspecialchars($_GET['task_id'], ENT_QUOTES, 'UTF-8') : '')?>">
                                <button type="submit" class="btn btn-light-gray btn-hover-red" tabindex="-1">Delete task</button>
                            </form>
                        <?php endif; ?>
                        </div>
                        <div class="flex-cell flex-vertical-center flex-right">
                            <input type="submit" value="<?=($_GET['action'] == 'edit' ? 'Save task' : 'Add task')?>" form="form_addTask" class="btn btn-green">
                        </div>
                    </div>
                </div>
            </div>
            <!-- End form action -->

            <!-- end of modal -->
        </div>
    </div>
</div>