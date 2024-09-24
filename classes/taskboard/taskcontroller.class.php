<?php
namespace Dashboard\Taskboard;

use Dashboard\Core\Interfaces\DatabaseInterface;
use Dashboard\Core\User;
use Dashboard\Core\SharedImageHandler;
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
            return ['success' => true, 'message' => 'Task updated successfully.'];
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
            // Get the images associated with this task
            $images = $this->taskModel->getTaskImages($taskId);
            
            $result = $this->taskModel->deleteTask($taskId);
            
            if ($result) {
                // If task deletion was successful, delete the associated images
                foreach ($images as $image) {
                    $this->securelyDeleteFile($image['image_path']);
                }
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
                    'checklist_completion_rate' => $this->taskModel->getChecklistCompletionRate()
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
        $imageHandler = new SharedImageHandler($userId, $taskId, $taskDesc, $this->db, 'task');
        $imageProcessingResult = $imageHandler->processImages();
        
        foreach ($imageProcessingResult['toDelete'] as $fileToDelete) {
            $this->securelyDeleteFile($fileToDelete);
        }
        
        return $imageProcessingResult['newContent'];
    }

    /**
     * Securely deletes a file after performing various safety checks
     */
    private function securelyDeleteFile($filePath) {
        // Step 1: Validate the file path
        $fullPath = $this->validateAndSanitizePath($filePath);
        if ($fullPath === false) {
            error_log("Invalid file path attempted to be deleted: " . $filePath);
            return false;
        }

        // Step 2: Check if the file exists and is within the allowed directory
        if (!file_exists($fullPath) || !$this->isInAllowedDirectory($fullPath)) {
            error_log("File does not exist or is not in an allowed directory: " . $fullPath);
            return false;
        }

        // Step 3: Ensure the file is owned by the web server process
        if (!$this->isOwnedByWebServer($fullPath)) {
            error_log("File is not owned by the web server process: " . $fullPath);
            return false;
        }

        // Step 4: Attempt to delete the file
        if (unlink($fullPath)) {
            error_log("Successfully deleted file: " . $fullPath);
            return true;
        } else {
            error_log("Failed to delete file: " . $fullPath);
            return false;
        }
    }

    /**
     * Validates and sanitizes a file path, ensuring it's within the allowed directory
     */
    private function validateAndSanitizePath($filePath) {
        // Remove any null bytes
        $filePath = str_replace(chr(0), '', $filePath);

        // Resolve the real path, removing any '..' or symbolic links
        $realPath = realpath(dirname(__DIR__, 2) . $filePath);

        // Check if the path is within the allowed directory
        $allowedDirectory = realpath(dirname(__DIR__, 2) . '/uploads');
        if (strpos($realPath, $allowedDirectory) === 0) {
            return $realPath;
        }

        return false;
    }

    /**
     * Checks if a given file path is within the allowed directory
     */
    private function isInAllowedDirectory($fullPath) {
        $allowedDirectory = realpath(dirname(__DIR__, 2) . '/uploads');
        return strpos($fullPath, $allowedDirectory) === 0;
    }

    /**
     * Verifies if the file is owned by the web server process
     */
    private function isOwnedByWebServer($fullPath) {
        $fileOwner = fileowner($fullPath);
        $serverOwner = posix_getpwuid(posix_geteuid());
        return $fileOwner === $serverOwner['uid'];
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
                $description = htmlspecialchars($item['description'] ?? '', ENT_QUOTES, 'UTF-8');
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

    public function getTaskLabels() {
        return $this->taskModel->getTaskLabels();
    }

    public function getChecklistCompletionRate() {
        return $this->taskModel->getChecklistCompletionRate();
    }

    
    public function getTaskChecklist() { return $this->taskModel->getTaskChecklist(); }
    
}
?>