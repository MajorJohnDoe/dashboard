<?php
namespace Dashboard\Taskboard;

use Dashboard\Core\Sanitize;

use Dashboard\Core\Interfaces\DatabaseInterface;
use Dashboard\Core\User;
use Dashboard\Core\ItemImageService;
use Dashboard\Core\AttachmentService;
use Dashboard\Core\HtmxEvents;
use Dashboard\Taskboard\ColumnController;

class TaskController {
    private $db;
    private $user;
    private $taskModel;

    public function __construct(DatabaseInterface $db, User $user) {
        $this->db = $db;
        $this->user = $user;
        $this->taskModel = new Task($db);
    }

    public function handleCreateTask($postData) {
        $validatedData = $this->validateTaskData($postData);
        if (!$validatedData['isValid']) {
            return ['success' => false, 'message' => $validatedData['error']];
        }

        if (!$this->taskModel->validateColumnOwnership($this->user->getUserId(), $postData['column_id'])) {
            return ['success' => false, 'message' => 'Unauthorized: User does not own this column.'];
        }

        $this->db->beginTransaction();
        try {
            $taskId = $this->taskModel->createTask(
                $this->user->getUserId(),
                $validatedData['columnId'],
                $validatedData['taskTitle'],
                $validatedData['taskDesc'],
                $validatedData['taskPriority'],
                $validatedData['checklistJSON'],
                $validatedData['selectedLabels']
            );
            
            $updatedDesc = $this->processTaskImages($this->user->getUserId(), $taskId, $validatedData['taskDesc']);
            
            if ($updatedDesc !== $validatedData['taskDesc']) {
                $updateResult = $this->taskModel->updateTaskDescription($taskId, $updatedDesc);
                if (!$updateResult) {
                    throw new \RuntimeException('Failed to update task description after image processing.');
                }
            }

            // Claim staged pending attachments (uploaded from the new-task
            // dialog before the task existed). Tokens arrive as an array of
            // hidden fields; invalid/foreign tokens are skipped silently.
            $pendingTokens = array_values(array_filter(
                (array)($_POST['pending_attachments'] ?? []),
                fn($t) => is_string($t) && preg_match('/^[a-f0-9]{32}$/', $t)
            ));
            if (!empty($pendingTokens)) {
                (new AttachmentService($this->db))->claimPending(
                    (int)$this->user->getUserId(), (int)$taskId, 'task', $pendingTokens
                );
            }
            
            $this->db->commit();
            return ['success' => true, 'message' => 'New task was added successfully.', 'task_id' => $taskId];
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("Error creating task: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while creating the task. Please try again.'];
        }
    }

    public function handleUpdateTask($postData) {
        $validatedData = $this->validateTaskData($postData);
        if (!$validatedData['isValid']) {
            return ['success' => false, 'message' => $validatedData['error']];
        }

        if (!$this->taskModel->validateColumnOwnership($this->user->getUserId(), $postData['column_id'])) {
            return ['success' => false, 'message' => 'Unauthorized: User does not own this column.'];
        }

        if (!$this->taskModel->validateTaskOwnership($this->user->getUserId(), $postData['task_id'])) {
            return ['success' => false, 'message' => 'Unauthorized: User does not own this task.'];
        }

        $this->db->beginTransaction();
        try {
            $updatedDesc = $this->processTaskImages($this->user->getUserId(), $validatedData['taskId'], $validatedData['taskDesc']);
            $validatedData['taskDesc'] = $updatedDesc;

            $result = $this->taskModel->updateTask(
                $this->user->getUserId(),
                $validatedData['taskId'],
                $validatedData['taskTitle'],
                $validatedData['taskDesc'],
                $validatedData['taskPriority'],
                $validatedData['checklistJSON'],
                $validatedData['selectedLabels'],
                $validatedData['moveTaskToColumn']
            );
            
            $this->db->commit();
            return [
                'success' => true,
                'message' => 'Task updated successfully.',
                // Task was moved to a different column: close the modal instead
                // of refreshing it in place (view maps this to closeModalEvent).
                'close_modal' => !empty($validatedData['moveTaskToColumn']),
            ];
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("Error updating task: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while updating the task. Please try again.'];
        }
    }

    public function handleDeleteTask($taskId) {
        if (!$this->taskModel->validateTaskOwnership($this->user->getUserId(), $taskId)) {
            return ['success' => false, 'message' => 'Unauthorized: User does not own this task.'];
        }

        $this->db->beginTransaction();
        try {
            // Delete the images associated with this task (files + DB rows)
            $this->getImageService()->deleteAllForItem($this->user->getUserId(), $taskId, 'task');

            // Delete document attachments (files + item_attachments rows)
            (new AttachmentService($this->db))->deleteAllForItem($this->user->getUserId(), (int)$taskId, 'task');

            $result = $this->taskModel->deleteTask($taskId);
            
            if ($result) {
                $this->db->commit();
                return ['success' => true, 'message' => 'Task deleted successfully.'];
            } else {
                throw new \RuntimeException('Failed to delete task.');
            }
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("Error deleting task: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while deleting the task. Please try again.'];
        }
    }

    public function handleMoveTask($taskId, $newColumnId, $columnFlagSettings = null) {
        if (!$this->taskModel->validateTaskOwnership($this->user->getUserId(), $taskId)) {
            return ['success' => false, 'message' => 'Unauthorized: User does not own this task.'];
        }

        if (!$this->taskModel->validateColumnOwnership($this->user->getUserId(), $newColumnId)) {
            return ['success' => false, 'message' => 'Unauthorized: User does not own this column.'];
        }

        return $this->taskModel->moveTask($this->user->getUserId(), $taskId, $newColumnId, $columnFlagSettings);
    }

    /**
     * Quick priority change (context menu / inline actions).
     *
     * @param int $taskId
     * @param int $priority 0-4 (Lowest..Highest)
     * @return array ['success' => bool, 'message' => string]
     */
    public function handleSetPriority(int $taskId, int $priority): array {
        if (!$this->taskModel->validateTaskOwnership($this->user->getUserId(), $taskId)) {
            return ['success' => false, 'message' => 'Unauthorized: User does not own this task.'];
        }

        $priority = max(0, min(4, $priority));

        $this->db->q(
            "UPDATE `tm_task` SET `task_priority` = ?, `task_modified` = NOW() WHERE `task_id` = ?",
            'ii',
            $priority,
            $taskId
        );

        return ['success' => true, 'message' => 'Priority updated.'];
    }

    /**
     * HTTP entry point for POST /task/priority/:task_id (context menu).
     * Sends triggerResponse (toast + board refresh) and returns ''.
     */
    public function handleSetPriorityRequest() {
        $taskId = (int)($_GET['task_id'] ?? 0);
        $priority = (int)($_POST['task_priority'] ?? -1);

        if ($taskId <= 0 || $priority < 0 || $priority > 4) {
            triggerResponse(HtmxEvents::errorResponse('Invalid priority request.'));
        }

        $result = $this->handleSetPriority($taskId, $priority);

        if ($result['success']) {
            triggerResponse(HtmxEvents::successResponse($result['message'], [
                HtmxEvents::TASK_BOARD_COLUMN_LIST => true,
            ]));
        } else {
            triggerResponse(HtmxEvents::errorResponse($result['message']));
        }

        return '';
    }


    public function handleDragAndDropTaskColumns()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $taskId = $data['itemId'] ?? null;
        $newColumnId = $data['newListId'] ?? null;

        if (is_null($taskId) || is_null($newColumnId)) {
            return ['error' => true, 'message' => 'Missing data for itemId or newListId'];
        }

        $columnObj = new ColumnController($this->db, $this->user);
        $columnResult = $columnObj->getColumnDataById($newColumnId);

        if ($columnResult == false) {
            return ['error' => true, 'message' => 'Error getting column flag settings'];
        }

        $columnFlagSettings = $columnObj->getColumnFlag();

        $result = $this->taskModel->moveTask($this->user->user_id(), $taskId, $newColumnId, $columnFlagSettings);

        if ($result['success'] == true) {
            return ['success' => true, 'message' => 'Successfully moved task to new column.'];
        } else {
            return ['success' => false, 'message' => 'Failed to move task to new column.'];
        }
    }


    public function handleDuplicateTask($columnId, $taskId) {
        if (!$this->taskModel->validateTaskOwnership($this->user->getUserId(), $taskId)) {
            return ['success' => false, 'message' => 'Unauthorized: User does not own this task.'];
        }

        if (!$this->taskModel->validateColumnOwnership($this->user->getUserId(), $columnId)) {
            return ['success' => false, 'message' => 'Unauthorized: User does not own this column.'];
        }

        return $this->taskModel->duplicateTask($this->user->getUserId(), $columnId, $taskId);
    }

    public function handleGetTaskDetails($taskId) {

        if (!$this->taskModel->validateTaskOwnership($this->user->getUserId(), $taskId)) {
            return ['success' => false, 'message' => 'Unauthorized: User does not own this task.'];
        }
        
        if ($this->taskModel->loadTaskDetails($taskId, $this->user->getUserId())) {
            return [
                'success' => true,
                'task' => [
                    'id' => $this->taskModel->getTaskId(),
                    'title' => $this->taskModel->getTaskTitle(),
                    'description' => $this->taskModel->getTaskDescription(),
                    'created_at' => $this->taskModel->getTaskCreationDate(),
                    'checklist' => $this->taskModel->getTaskChecklist(),
                    'priority' => $this->taskModel->getTaskPriority(),
                    'labels' => $this->taskModel->getTaskLabels(),
                    'resolved_date' => $this->taskModel->getTaskResolvedDate(),
                    'checklist_completion_rate' => $this->taskModel->getChecklistCompletionRate(),
                    'schedule_id' => $this->taskModel->getTaskScheduleId(),
                ]
            ];
        }
        return ['success' => false, 'message' => 'Task not found or access denied.'];
    }

    public function handleGetTasksUsingDate($boardId, $date) {
        $tasks = $this->taskModel->getTasksUsingDate($this->user->getUserId(), $boardId, $date);
        return ['success' => true, 'tasks' => $tasks];
    }   

    /**
     * Processes task images, handles deletions, and updates task description
     */
    private function processTaskImages($userId, $taskId, $taskDesc) {
        return $this->getImageService()->processAndPersist($userId, $taskId, $taskDesc, 'task');
    }

    private function getImageService(): ItemImageService {
        return new ItemImageService($this->db);
    }

    private function validateTaskData($postData) {
        $validatedData = [
            'isValid' => true,
            'error' => '',
            'columnId' => filter_var($postData['column_id'] ?? '', FILTER_SANITIZE_NUMBER_INT),
            'taskId' => $postData['task_id'] ?? '',
            'taskTitle' => $postData['task_title'] ?? '',
            'taskPriority' => $postData['task_priority'] ?? '',
            'taskDesc' => $postData['task_desc'] ?? '',
            'selectedLabels' => $postData['selectedLabels'] ?? [],
            'moveTaskToColumn' => isset($postData['move_task_column_id']) ? (int)$postData['move_task_column_id'] : 0,
        ];

        if (strlen($validatedData['taskTitle']) < 2) {
            $validatedData['isValid'] = false;
            $validatedData['error'] = 'Title cannot be empty.';
            return $validatedData;
        }

        if (strlen($validatedData['taskTitle']) > 99) {
            $validatedData['isValid'] = false;
            $validatedData['error'] = 'Title is too long.';
            return $validatedData;
        }

        $validatedData['checklistJSON'] = $this->validateChecklist($postData['checklist'] ?? []);

        return $validatedData;
    }

    private function validateChecklist($checklistData) {
        $checklistItems = [];
        if (is_array($checklistData)) {
            foreach ($checklistData as $index => $item) {
                if ($index >= _TASKBOARD_TASK_CHECKLIST_MAXIMUM) {
                    break;
                }
                $description = Sanitize::e($item['description'] ?? '');
                $status = (isset($item['status']) && $item['status'] === 'complete') ? 'complete' : 'incomplete';
                $checklistItems[] = [
                    'description' => $description,
                    'status' => $status,
                ];
            }
        }
        return json_encode($checklistItems);
    }

    public function loadTaskDataById($taskId, $userId) {
        return $this->taskModel->loadTaskDetails($taskId, $userId);
    }

    /**
     * Batch-load task data for multiple task IDs (2 queries total instead of 2N).
     *
     * @param array $taskIds Task IDs to load
     * @param int $userId Current user ID for access validation
     * @return array Map of taskId => ['task' => row, 'labels' => [...], 'completion_rate' => ?int]
     */
    public function loadTaskDataByIds(array $taskIds, int $userId): array {
        $loaded = $this->taskModel->loadTaskDetailsForTasks($taskIds, $userId);

        $result = [];
        foreach ($loaded as $taskId => $data) {
            $result[$taskId] = [
                'task' => $data['task'],
                'labels' => $data['labels'],
                'completion_rate' => Task::computeChecklistCompletionRate($data['task']['task_checklist'] ?? null),
            ];
        }

        return $result;
    }

    public function getTaskLabels() {
        return $this->taskModel->getTaskLabels();
    }

    public function getChecklistCompletionRate() {
        return $this->taskModel->getChecklistCompletionRate();
    }

    
    public function getTaskChecklist() { return $this->taskModel->getTaskChecklist(); }
    
}
?>