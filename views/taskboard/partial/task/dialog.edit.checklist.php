<?php  
    use Dashboard\Taskboard\TaskController;

    // User wants to edit a task checklist
    if($_GET['action'] == 'edit' && isset($_GET['task_id'])) {
        $task = new TaskController($db, $user);
        $result = $task->loadTaskDataById($_GET['task_id'], $user->user_id());

        $checklistItems = '';

        if($result) {
            $taskChecklist      = $task->getTaskChecklist();
            $checklistItems = json_decode($taskChecklist, true); // Ensure it's an associative array    
        }
    }
?>

<div class="flex-cell task-checklist-container">
    <label for="task_desc">Checklist:</label><br>
    <div id="checklist-container">
        <div class="flex-table" id="checklist-items">
            <?php 
            if (!empty($checklistItems)): ?>
            <?php foreach ($checklistItems as $index => $item): ?>
                    <div class="flex-row">
                        <div class="flex-cell flex-cell-shrink flex-cell-vcenter">
                            <input type="checkbox" name="checklist[<?= $index; ?>][status]" tabindex="-1" value="complete" <?=$item['status'] === 'complete' ? 'checked' : ''; ?>/>
                        </div>
                        <div class="flex-cell flex-cell-vcenter">
                            <input type="text" name="checklist[<?= $index; ?>][description]" value="<?=htmlspecialchars(html_entity_decode($item['description'])); ?>" style="padding: 0.4rem;"/>
                        </div>
                        <div class="flex-cell flex-cell-shrink flex-cell-vcenter">
                            <button type="button" tabindex="-1" class="remove-item btn btn-dark-gray btn-hover-red" style="padding: 0.2rem 0.6rem;">X</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
            <?php endif; ?>
            
        </div>
    
    </div>
    <button type="button" id="add-item" class="btn btn-light-gray" style="float: right; margin: 0.4rem 0 0 0;">Add item</button>
</div>


