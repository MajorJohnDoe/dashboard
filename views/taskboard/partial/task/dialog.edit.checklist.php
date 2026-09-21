<?php  
use Dashboard\Core\Sanitize;
    use Dashboard\Taskboard\TaskController;

    // User wants to edit a task checklist
    if($_GET['action'] == 'edit' && isset($_GET['task_id'])) {
        $task = new TaskController($db, $user);
        $result = $task->loadTaskDataById($_GET['task_id'], $user->user_id());

        if($result) {
            $decoded = json_decode((string)$task->getTaskChecklist(), true);
            $checklistItems = is_array($decoded) ? $decoded : [];
        }
    }

    // Shared markup with the recurring-schedule dialog (unified checklist UI).
    // The task dialog has no empty-state placeholder; the schedule template does.
    $checklistEmptyText = null;
    include BASE_DIR . '/views/core/partial/checklist.php';


