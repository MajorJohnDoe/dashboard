<?php
namespace Dashboard\Taskboard;

use Dashboard\Core\Interfaces\DatabaseInterface;
use Dashboard\Core\User;
use Dashboard\Taskboard\Board;

class ColumnController {
    private $db;
    private $user;
    private $column;

    public function __construct(DatabaseInterface $db, User $user) {
        $this->db = $db;
        $this->user = $user;
        $this->column = new Column($db);
    }

    public function handleCreateColumn($boardId, $columnName, $columnOrder) {
        if (!$this->verifyBoardOwnership($boardId)) {
            return [
                'success' => false,
                'message' => 'Board does not belong to the user.'
            ];
        }
    
        $columnCount = $this->column->countColumns($boardId);
    
        if ($columnCount < _TASKBOARD_MAXIMUM_COLUMNS) {
            $result = $this->column->createColumn($boardId, $columnName, $columnOrder);
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Added new column successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'There was a problem adding a new column.'
                ];
            }
        } else {
            return [
                'success' => false,
                'message' => 'Too many columns. Maximum allowed is ' . _TASKBOARD_MAXIMUM_COLUMNS . '.'
            ];
        }
    }


    public function createColumn($boardId, $columnName, $columnOrder) {
        $result = $this->column->createColumn($boardId, $columnName, $columnOrder);
        return $result ? ['success' => true, 'message' => 'Column created successfully.'] : ['success' => false, 'error' => 'Failed to create column.'];
    }

    public function countColumns($boardId) {
        if (!$this->verifyBoardOwnership($boardId)) {
            return ['success' => false, 'error' => 'Board does not belong to the user.'];
        }
        return ['success' => true, 'count' => $this->column->countColumns($boardId)];
    }

    public function saveColumnOrder($columnOrders) {
        foreach ($columnOrders as $columnId => $order) {
            if (!$this->verifyColumnOwnership($columnId)) {
                return ['success' => false, 'error' => 'One or more columns do not belong to the user.'];
            }
        }
        $result = $this->column->saveColumnOrder($columnOrders);
        return $result ? ['success' => true, 'message' => 'Column order saved successfully.'] : ['success' => false, 'error' => 'Failed to save column order.'];
    }

    public function handleDragDropColumnOrder()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['columnOrders']) || !is_array($data['columnOrders'])) {
            return ['success' => false, 'message' => 'Invalid or missing columnOrders data'];
        }

        $result = $this->column->saveColumnOrder($data['columnOrders']);

        if ($result == true) {
            return ['success' => true, 'message' => 'Column order updated successfully'];
        } else {
            return ['success' => false, 'message' => $result['message'] ?? 'Failed to update column order'];
        }
    }

    public function updateColumnData($columnId, $columnTitle, $columnTaskOrderBy, $columnFlag, $columnTaskDisplayLimit) {
        if (!$this->verifyColumnOwnership($columnId)) {
            return ['success' => false, 'error' => 'Column does not belong to the user.'];
        }

        if (strlen($columnTitle) < 2) {
            return ['success' => false, 'error' => 'Column title cannot be empty.'];
        }

        return $this->column->updateColumnData($columnId, $columnTitle, $columnTaskOrderBy, $columnFlag, $columnTaskDisplayLimit);
    }

    public function deleteColumn($columnId) {
        if (!$this->verifyColumnOwnership($columnId)) {
            return ['success' => false, 'error' => 'Column does not belong to the user.'];
        }
        return $this->column->deleteColumn($columnId);
    }

    public function getColumnDataById($columnId) {
        if (!$this->verifyColumnOwnership($columnId)) {
            return ['success' => false, 'error' => 'Column does not belong to the user.'];
        }
        return $this->column->getColumnDataById($columnId);
    }

    public function getColumnsForBoard($boardId) {
        if (!$this->verifyBoardOwnership($boardId)) {
            return ['success' => false, 'error' => 'Board does not belong to the user.'];
        }
        $columns = $this->column->getColumnsForBoard($boardId);
        return ['success' => true, 'columns' => $columns];
    }

    public function getTasksForColumn($columnId, $taskOrder = null, $displayMaxTasks = 2) {
        if (!$this->verifyColumnOwnership($columnId)) {
            return ['success' => false, 'error' => 'Column does not belong to the user.'];
        }
        $tasks = $this->column->getTasksForColumn($columnId, $taskOrder, $displayMaxTasks);
        return ['success' => true, 'tasks' => $tasks];
    }

    public function changeColumnName($columnId, $newColumnName) {
        if (!$this->verifyColumnOwnership($columnId)) {
            return ['success' => false, 'error' => 'Column does not belong to the user.'];
        }
        $result = $this->column->changeColumnName($newColumnName);
        return $result ? ['success' => true, 'message' => 'Column name changed successfully.'] : ['success' => false, 'error' => 'Failed to change column name.'];
    }

    private function verifyBoardOwnership($boardId) {
        $board = new Board($this->db);
        return $board->validateBoardOwnership($this->user->getUserId(), $boardId);
    }

    private function verifyColumnOwnership($columnId) {
        return $this->column->verifyOwnership($columnId, $this->user->getUserId());
    }  

    public function getColumnFlag() {
        return $this->column->getColumnFlag();
    }
}
?>