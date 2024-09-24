<?php
use Dashboard\Taskboard\ColumnController;
use Dashboard\Taskboard\TaskController;

// Assume we've already instantiated $db and $user objects
$columnController = new ColumnController($db, $user);
$columnsResult = $columnController->getColumnsForBoard($user->getActiveTaskBoard());

$priorityClasses = [
    0 => 'priority-lowest',
    1 => 'priority-low',
    2 => 'priority-alarming',
    3 => 'priority-critical',
    4 => 'priority-highest',
];

if ($columnsResult['success'] && !empty($columnsResult['columns'])) {
    echo '<div class="columns-container">';
            
    foreach ($columnsResult['columns'] as $column) {
        echo '  <div id="column-' . $column['id'] . '" class="task-column">';
        echo '      <div class="column-header">';
        echo '          <div class="column-name">' . htmlspecialchars(html_entity_decode($column['column_name'])) . '</div>';
        echo '          <div class="column-icons">
                            <div 
                                class="icon open-modal-btn" 
                                id="column-settings-id-' . $column['id'] . '"
                                title="Column settings" 
                                data-modal-target="#dialog-column-settings"
                                hx-get="/column/dialog/edit/' . $column['id'] . '"
                                hx-target="body"
                                hx-swap="beforeend">&#9881;</div>
                            <div class="icon move" title="Move column">&#10021;</div>
                            <div    
                                class="icon add-task open-modal-btn" 
                                title="Add task" 
                                data-modal-target="#dialog-column-add-task"
                                hx-get="/task/dialog/new/' . $column['id'] . '" 
                                hx-target="body" 
                                hx-swap="beforeend">+</div>
                        </div>';
        echo '      </div>';
        echo '  <ul id="inner-column-' . $column['id'] . '" class="sortable-list">';

        $tasksResult = $columnController->getTasksForColumn(
            $column['id'], 
            $column['column_task_order'], 
            $column['column_max_display_tasks']
        );

        if ($tasksResult['success']) {
            foreach ($tasksResult['tasks'] as $task) {
                $objTask = new TaskController($db, $user);
                $taskData = $objTask->loadTaskDataById($task['task_id'], $user->getUserId());
                $taskSelectedLabels = $objTask->getTaskLabels();
                $completionRate = $objTask->getChecklistCompletionRate();

                $taskLabels = '';
                if (!empty($taskSelectedLabels)) {
                    $formattedLabels = array_map(function($label) {
                        $fontColor = adjustHexColorBrightness(htmlspecialchars($label['label_color']), -155);
                        return '<div style="background-color: ' . htmlspecialchars($label['label_color']) . '; color: ' . $fontColor . ';">' . htmlspecialchars($label['label_name']) . '</div>';
                    }, $taskSelectedLabels);
                
                    $taskLabels .= implode('', $formattedLabels);
                }

                $taskPriorityClass = $priorityClasses[$task['task_priority']] ?? 'priority-lowest';

                echo '<li 
                        class="tm-task ' . $taskPriorityClass . ' open-modal-btn"
                        data-id="' . $task['task_id'] . '"
                        data-modal-target="#dialog-column-add-task"
                        hx-get="/task/dialog/edit/' . $column['id'] . '/' . $task['task_id'] . '"
                        hx-target="body" 
                        hx-swap="beforeend">';
                echo '<div class="flex-table">
                        <div class="flex-row">
                            <div class="flex-cell flex-cell-vcenter" style="padding: 0px;">
                                <div class="title">' . htmlspecialchars(html_entity_decode($task['task_title'])) . '</div>
                                ' . (!empty($taskSelectedLabels) ? '<div class="labels">' . $taskLabels . '</div>' : '') . '
                            </div>
                            <div class="flex-cell flex-cell-shrink">
                                ' . (($completionRate !== null) ? '<div class="completed ' . taskCompletedPercentage($completionRate) . '">' . $completionRate . '%</div>' : '') . '
                            </div>
                        </div>
                      </div>';
                echo '</li>';
            }
        }
        echo '  </ul>';
        
        echo '</div>';
    }
    
    echo '</div>';
} 
else {
    echo '<div class="no-columns-message">No columns found, click on "edit board" to add new columns.</div>';
}
?>