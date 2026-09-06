<?php
use Dashboard\Core\Sanitize;
use Dashboard\Core\HtmxEvents;
    use Dashboard\Taskboard\ColumnController;
    use Dashboard\Taskboard\TaskController;

    $taskColumn = new ColumnController($db, $user);
    $taskColumns = $taskColumn->getColumnsForBoard($user->getActiveTaskBoard());
    $post_url = '';

    // User wants to duplicate a task to a column
    if(isset($_GET['action']) &&  $_GET['action'] == 'dupe' && isset($_GET['taskid'])) {
        $post_url = '/task/duplicate/dupe/'.(isset($_GET['taskid']) ? $_GET['taskid'] : '');
    }
    
    // #MARK: duplicate task - form post
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['action'] == 'dupe' && isset($_POST['task_id'])) {
        // Perform input validation
        $columnId       = filter_input(INPUT_POST, 'column_pick', FILTER_SANITIZE_NUMBER_INT);
        $taskId         = filter_input(INPUT_POST, 'task_id', FILTER_SANITIZE_NUMBER_INT);

        $task = new TaskController($db, $user);
        $result = $task->handleDuplicateTask($columnId, $taskId);

        if ($result['success'] == true) {
            triggerResponse(HtmxEvents::successResponse(
                $result['message'],
                [HtmxEvents::TASK_BOARD_COLUMN_LIST => true, HtmxEvents::CLOSE_MODAL => true]
            ));
        } else {
            triggerResponse(HtmxEvents::errorResponse($result['message']));
        }
    }
?>

    <div class="nice-form-group" id="dialog-task-duplicate" style="width: 30rem; padding: 10px">
        <div class="form-inner">
            <form id="form_duplicateTask" method="POST" hx-post="<?=$post_url?>" hx-target="#dialog-task-duplicate .form-inner" hx-swap="beforeend">
                <?= \Dashboard\Core\CsrfProtection::getTokenField() ?>
                
                <div class="flex-table">
                    <div class="flex-row">
                        <div class="flex-cell flex-vertical-center ">
                            <strong>Duplicate this task to another column:</strong>
                        </div>
                        <div class="flex-cell">
                            <select name="column_pick" id="column_pick" style="width: 100%;">
                                <option value="0">pick a column</option>
                                <?php
                                    if($taskColumns != false) {
                                        foreach ($taskColumns['columns'] as $column) {
                                            echo '<option value="'.$column['id'].'">'.Sanitize::e(html_entity_decode($column['column_name'])).'</option>';
                                        }
                                    }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="flex-row">
                        <div class="flex-cell flex-vertical-center flex-right">
                            <input type="hidden" value="<?=(isset($_GET['taskid']) ? $_GET['taskid'] : '')?>" name="task_id">
                            <input type="submit" value="Duplicate task" form="form_duplicateTask" class="btn btn-green">
                        </div>
                    </div>
                </div>
                
            </form>
        </div>
    </div>
