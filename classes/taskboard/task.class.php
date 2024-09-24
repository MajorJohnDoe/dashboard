<?php
namespace Dashboard\Taskboard;

use Dashboard\Core\Interfaces\DatabaseInterface;

class Task {
    private $db;
    private $taskId;
    private $boardId;
    private $columnId;
    private $taskTitle;
    private $taskDesc;
    private $taskChecklist;
    private $taskPriority;
    private $taskCreated;
    private $taskSelectedLabels;
    private $taskResolvedDate;

    public function __construct(DatabaseInterface $db) {
        $this->db = $db;
    }

    public function loadTaskDetails($taskId, $userId) {
        $sql = "SELECT task.* 
                FROM `tm_task` task
                    JOIN `tm_board` board ON task.`board_id` = board.`id`
                WHERE 
                    task.task_id = ? AND
                    board.user_id = ?
                LIMIT 1";
        $result = $this->db->q($sql, "ii", $taskId, $userId);

        if ($result) {
            $this->setTaskProperties($result[0]);
            $this->taskSelectedLabels = $this->loadTaskLabels($this->taskId);
            return true;
        }

        return false;
    }

    private function setTaskProperties($data) {
        $this->taskId = $data['task_id'];
        $this->boardId = $data['board_id'];
        $this->columnId = $data['column_id'];
        $this->taskTitle = $data['task_title'];
        $this->taskDesc = $data['task_desc'];
        $this->taskCreated = $data['task_created'];
        $this->taskChecklist = $data['task_checklist'];
        $this->taskPriority = $data['task_priority'];
        $this->taskResolvedDate = $data['task_resolved_date'];
    }

    public function createTask($userId, $columnId, $taskTitle, $taskDesc, $taskPriority, $checklistJSON = null, $taskLabels = null) {
        $columnDetails = $this->validateColumnOwnership($userId, $columnId);
        if (!$columnDetails) {
            throw new \InvalidArgumentException('Column does not belong to the user.');
        }

        $isChecklistEmpty = is_null($checklistJSON) || $checklistJSON === '[]' ? null : $checklistJSON;

        $sql = "INSERT INTO `tm_task` (`board_id`, `column_id`, `task_title`, `task_desc`, `task_checklist`, `task_priority`, `task_created`, `task_modified`) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
        $insertResult = $this->db->q($sql, "iisssi", $columnDetails[0]['board_id'], $columnDetails[0]['column_id'], $taskTitle, $taskDesc, $isChecklistEmpty, $taskPriority);

        if ($insertResult === false) {
            throw new \RuntimeException('Failed to create new task.');
        }

        $taskId = $this->db->lastInsertId();

        $this->addTaskLabels($userId, $taskId, $taskLabels);

        return $taskId;
    }

    public function updateTask($userId, $taskId, $taskTitle, $taskDesc, $taskPriority, $checklistJSON = null, $taskLabels = null, $moveTaskToColumn = 0) {
        if (!$this->validateTaskOwnership($userId, $taskId)) {
            throw new \InvalidArgumentException('Task does not belong to the user.');
        }

        $isChecklistEmpty = is_null($checklistJSON) || $checklistJSON === '[]' ? null : $checklistJSON;

        $sql = "UPDATE `tm_task` SET `task_title` = ?, `task_desc` = ?, `task_checklist` = ?, `task_priority` = ?, `task_modified` = NOW()";
        $params = [$taskTitle, $taskDesc, $isChecklistEmpty, $taskPriority];
        $types = "sssi";

        if ($moveTaskToColumn !== 0) {
            if (!$this->validateColumnOwnership($userId, $moveTaskToColumn)) {
                throw new \InvalidArgumentException('Target column does not belong to the user.');
            }
            $sql .= ", `column_id` = ?, `task_resolved_date` = NULL";
            $params[] = $moveTaskToColumn;
            $types .= "i";
        }

        $sql .= " WHERE `task_id` = ? LIMIT 1";
        $params[] = $taskId;
        $types .= "i";

        $updateResult = $this->db->q($sql, $types, ...$params);

        if ($updateResult === false) {
            throw new \RuntimeException('Failed to update task.');
        }

        $this->updateTaskLabels($userId, $taskId, $taskLabels);

        return true;
    }

    public function deleteTask($taskId) {
        $this->db->beginTransaction();
        try {
            $sql = "DELETE FROM `tm_task` WHERE `task_id` = ? LIMIT 1";
            $deleteResult = $this->db->q($sql, "i", $taskId);
            
            if ($deleteResult === false) {
                throw new \RuntimeException('Failed to delete task.');
            }

            $this->deleteTaskImages($taskId);

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function moveTask($userId, $taskId, $newColumnId, $columnFlagSettings = null) {
        if (!$this->validateTaskOwnership($userId, $taskId) || !$this->validateColumnOwnership($userId, $newColumnId)) {
            return ['success' => false, 'error' => 'Task or column does not belong to the user.'];
        }

        $columnFlagSettings = ($columnFlagSettings == 1 ? date('Y-m-d H:i:s') : null);

        $sql = "UPDATE `tm_task` SET `column_id` = ?, `task_resolved_date` = ? WHERE `task_id` = ? LIMIT 1";
        $result = $this->db->q($sql, "isi", $newColumnId, $columnFlagSettings, $taskId);

        return $result !== false 
            ? ['success' => true, 'message' => 'Task moved successfully.']
            : ['success' => false, 'error' => 'Failed to move task.'];
    }

    public function duplicateTask($userId, $columnId, $taskId) {
        if (!$this->validateTaskOwnership($userId, $taskId) || !$this->validateColumnOwnership($userId, $columnId)) {
            return ['success' => false, 'error' => 'Task or column ownership error.'];
        }

        $this->db->beginTransaction();
        try {
            $originalTask = $this->getTaskData($taskId);
            if (empty($originalTask)) {
                throw new \Exception('Original task not found.');
            }

            $newTaskId = $this->insertDuplicateTask($originalTask, $columnId);
            $this->duplicateTaskLabels($taskId, $newTaskId);

            $this->db->commit();
            return ['success' => true, 'message' => 'Task duplicated successfully', 'new_task_id' => $newTaskId];
        } catch (\Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getTasksUsingDate($userId, $boardId, $date) {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        if ($d && $d->format('Y-m-d') === $date) {
            $sql = "SELECT task.* 
                    FROM `tm_task` task
                    LEFT JOIN 
                        `tm_board` board 
                            ON 	board.id = task.board_id AND 
                                board.user_id = ? AND
                                board.id = ?
                    WHERE DATE(task.`task_resolved_date`) = ?";
    
            $results = $this->db->q($sql, "iis", $userId, $boardId, $date);
            return $results !== false ? $results : [];
        }
        return [];
    }

    // Validation methods
    public function validateTaskOwnership($userId, $taskId) {
        $sql = "SELECT task.task_id FROM `tm_task` task JOIN `tm_board` board ON task.`board_id` = board.`id` WHERE task.task_id = ? AND board.user_id = ? LIMIT 1";
        $result = $this->db->q($sql, "ii", $taskId, $userId);
        return $result !== false && count($result) > 0;
    }

    public function validateColumnOwnership($userId, $columnId) {
        $sql = "SELECT a.*, a.id AS column_id, b.id as board_id FROM `tm_column` a JOIN `tm_board` b ON a.`parent_id` = b.`id` WHERE a.`id` = ? AND b.`user_id` = ? LIMIT 1";
        $result = $this->db->q($sql, "ii", $columnId, $userId);
        return $result !== false && count($result) > 0 ? $result : false;
    }

    public function validateLabelOwnership($userId, $labelId) {
        $sql = "SELECT label.id FROM `tm_label` label JOIN `tm_board` board ON label.`board_id` = board.`id` WHERE label.id = ? AND board.user_id = ? LIMIT 1";
        $result = $this->db->q($sql, "ii", $labelId, $userId);
        return $result !== false && count($result) > 0;
    }

    // Helper methods
    private function loadTaskLabels($taskId) {
        $labelSql = "SELECT lbl.id, lbl.label_name, lbl.label_color
                     FROM `tm_label` lbl
                     INNER JOIN `tm_task_label_rel` tl ON lbl.id = tl.label_id
                     WHERE tl.task_id = ?";
        $labelResult = $this->db->q($labelSql, "i", $taskId);
    
        $labels = [];
        if ($labelResult) {
            foreach ($labelResult as $labelRow) {
                $labels[] = [
                    'label_id' => $labelRow['id'],
                    'label_name' => $labelRow['label_name'],
                    'label_color' => $labelRow['label_color']
                ];
            }
        }
    
        return $labels;
    }

    private function addTaskLabels($userId, $taskId, $taskLabels) {
        if (is_array($taskLabels)) {
            foreach ($taskLabels as $labelId) {
                if ($this->validateLabelOwnership($userId, $labelId)) {
                    $this->db->q("INSERT INTO `tm_task_label_rel` (task_id, label_id) VALUES (?, ?)", "ii", $taskId, $labelId);
                }
            }
        }
    }

    private function updateTaskLabels($userId, $taskId, $taskLabels) {
        $this->db->q("DELETE FROM `tm_task_label_rel` WHERE `task_id` = ?", "i", $taskId);
        $this->addTaskLabels($userId, $taskId, $taskLabels);
    }

    public function getTaskImages($taskId) {
        $sql = "SELECT image_path FROM `shared_item_images` WHERE `item_id` = ? AND `item_type` = 'task'";
        return $this->db->q($sql, "i", $taskId);
    }

    private function deleteTaskImages($taskId) {
        $sql = "DELETE FROM `shared_item_images` WHERE `item_id` = ? AND `item_type` = 'task'";
        $result = $this->db->q($sql, "i", $taskId);
        if ($result === false) {
            throw new \RuntimeException('Failed to delete task image records.');
        }
    }

    private function getTaskData($taskId) {
        $sql = "SELECT * FROM `tm_task` WHERE `task_id` = ? LIMIT 1";
        $result = $this->db->q($sql, "i", $taskId);
        return $result ? $result[0] : null;
    }

    private function insertDuplicateTask($originalTask, $columnId) {
        $sql = "INSERT INTO `tm_task` 
                    (
                        `board_id`, 
                        `column_id`, 
                        `task_title`, 
                        `task_desc`, 
                        `task_checklist`, 
                        `task_priority`, 
                        `task_created`,
                        `task_modified`
                    ) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
                
        $result = $this->db->q($sql, "iisssi", 
                                $originalTask['board_id'],
                                $columnId,
                                $originalTask['task_title'] . ' (dupe)',
                                $originalTask['task_desc'],
                                $originalTask['task_checklist'],
                                $originalTask['task_priority']
                              );

        if ($result === false) {
            throw new \Exception('Failed to insert new task.');
        }

        return $this->db->lastInsertId();
    }

    private function duplicateTaskLabels($originalTaskId, $newTaskId) {
        $sql = "SELECT `label_id` FROM `tm_task_label_rel` WHERE `task_id` = ?";
        $taskLabels = $this->db->q($sql, "i", $originalTaskId);

        foreach ($taskLabels as $label) {
            $sql = "INSERT INTO `tm_task_label_rel` (`task_id`, `label_id`) VALUES (?, ?)";
            $result = $this->db->q($sql, "ii", $newTaskId, $label['label_id']);
            if ($result === false) {
                throw new \Exception('Failed to duplicate task labels.');
            }
        }
    }

    public function updateTaskDescription($taskId, $newDescription) {
        $sql = "UPDATE `tm_task` SET `task_desc` = ?, `task_modified` = NOW() WHERE `task_id` = ? LIMIT 1";
        return $this->db->q($sql, "si", $newDescription, $taskId);
    }

    // Getter methods
    public function getTaskTitle() { return $this->taskTitle; }
    public function getTaskDescription() { return $this->taskDesc; }
    public function getTaskId() { return $this->taskId; }
    public function getTaskCreationDate() { return $this->taskCreated; }
    public function getTaskChecklist() { return $this->taskChecklist; }
    public function getTaskPriority() { return $this->taskPriority; }
    public function getTaskLabels() { return $this->taskSelectedLabels; }
    public function getTaskResolvedDate() { return $this->taskResolvedDate; }


    public function getChecklistCompletionRate() {
        if (empty($this->taskChecklist)) {
            return null;
        }

        $checklist = json_decode($this->taskChecklist, true);
        if (!is_array($checklist)) {
            return null;
        }

        $totalItems = count($checklist);
        $completedItems = 0;

        foreach ($checklist as $item) {
            if (isset($item['status']) && $item['status'] === 'complete') {
                $completedItems++;
            }
        }

        return ($totalItems > 0) ? round(($completedItems / $totalItems) * 100) : 0;
    }
}
?>