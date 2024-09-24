<?php
namespace Dashboard\Taskboard;

use Dashboard\Core\Interfaces\DatabaseInterface;

class Column {
    private $db;
    private $columnName;
    private $columnId;
    private $parentId;
    private $columnFlag;
    private $columnOrderBy;
    private $columnTaskDisplayLimit;

    public function __construct(DatabaseInterface $db, $columnId = null, $columnName = null, $parentId = null) {
        $this->db = $db;
        $this->columnId = $columnId;
        $this->columnName = $columnName;
        $this->parentId = $parentId;
    }

    public function createColumn($boardId, $columnName, $columnOrder) {
        $sql = "INSERT INTO `tm_column` (`parent_id`, `column_order`, `column_name`) VALUES (?, ?, ?)";
        return $this->db->q($sql, "iis", $boardId, $columnOrder, $columnName) !== false;
    }

    public function countColumns($boardId) {
        $sql = "SELECT COUNT(id) AS column_count FROM `tm_column` WHERE `parent_id` = ?";
        $result = $this->db->q($sql, "i", $boardId);
        if ($result !== false && count($result) > 0) {
            return intval($result[0]['column_count']);
        }
        return 0;
    }

    public function saveColumnOrder($columnOrders) {
        foreach ($columnOrders as $order => $columnId) {
            $sql = "UPDATE `tm_column` SET `column_order` = ? WHERE `id` = ?";
            $result = $this->db->q($sql, "ii", $order, $columnId);
            if ($result === false) {
                return false;
            }
        }
        return true;
    }

    public function updateColumnData($columnId, $columnTitle, $columnTaskOrderBy, $columnFlag, $columnTaskDisplayLimit) {
        $sql = "UPDATE `tm_column` SET `column_name` = ?, `column_task_order` = ?, `column_flag` = ?, `column_max_display_tasks` = ? WHERE `id` = ? LIMIT 1";
        $result = $this->db->q($sql, "siiii", $columnTitle, $columnTaskOrderBy, $columnFlag, $columnTaskDisplayLimit, $columnId);
        return $result !== false ? ['success' => true, 'message' => 'Column updated successfully.'] : ['success' => false, 'error' => 'Column failed to update.'];
    }

    public function deleteColumn($columnId) {
        $sql = "DELETE FROM `tm_column` WHERE `id` = ?";
        $result = $this->db->q($sql, "i", $columnId);
        
        if ($result !== false) {
            $sql = "DELETE FROM `tm_task` WHERE `column_id` = ?";
            $taskResult = $this->db->q($sql, "i", $columnId);
            return ['success' => true, 'message' => 'Column deleted successfully.'];
        }
        return ['success' => false, 'error' => 'Column failed to delete.'];
    }

    public function getColumnDataById($columnId) {
        $sql = "SELECT * FROM `tm_column` WHERE `id` = ? LIMIT 1";
        $result = $this->db->q($sql, "i", $columnId);
        if ($result && count($result) > 0) {
            $this->columnName = $result[0]['column_name'];
            $this->columnId = $result[0]['id'];
            $this->columnFlag = $result[0]['column_flag'];
            $this->columnOrderBy = $result[0]['column_task_order'];
            $this->columnTaskDisplayLimit = $result[0]['column_max_display_tasks'];
            return [
                'success' => true,
                'data' => [
                    'name' => $this->columnName,
                    'flag' => $this->columnFlag,
                    'orderBy' => $this->columnOrderBy,
                    'displayLimit' => $this->columnTaskDisplayLimit
                ]
            ];
        }
        return ['success' => false, 'error' => 'Column not found'];
    }

    public function getColumnsForBoard($boardId) {
        $sql = "SELECT * FROM `tm_column` WHERE `parent_id` = ? ORDER BY `column_order` ASC";
        $results = $this->db->q($sql, "i", $boardId);
        return $results !== false ? $results : [];
    }

    public function getTasksForColumn($columnId, $taskOrder = null, $displayMaxTasks = 2) {
        $arrDisplayTaskLimit = [
            0 => '15', 1 => '25', 2 => '35', 3 => '50', 4 => '80',
        ];    

        switch ($taskOrder) {
            case 0: $orderby = 'ORDER BY `task_created` DESC'; break;
            case 1: $orderby = 'ORDER BY `task_modified` DESC'; break;
            case 2: $orderby = 'ORDER BY `task_priority` DESC'; break;
            default: $orderby = ''; break;
        }

        $displayMaxTasks = isset($arrDisplayTaskLimit[$displayMaxTasks]) ? $arrDisplayTaskLimit[$displayMaxTasks] : $arrDisplayTaskLimit[2];
        $limitClause = $displayMaxTasks ? "LIMIT $displayMaxTasks" : '';

        $sql = "SELECT * FROM `tm_task` WHERE `column_id` = ? {$orderby} {$limitClause}";
        $results = $this->db->q($sql, "i", $columnId);
        return $results !== false ? $results : [];
    }

    public function changeColumnName($newColumnName) {
        $sql = "UPDATE `tm_column` SET `column_name` = ? WHERE `id` = ?";
        $result = $this->db->q($sql, "si", $newColumnName, $this->columnId);
        return $result !== false;
    }

    public function verifyOwnership($columnId, $userId) {
        $sql = "SELECT a.* FROM `tm_column` a JOIN `tm_board` b ON a.`parent_id` = b.`id` WHERE a.`id` = ? AND b.`user_id` = ? LIMIT 1";
        $result = $this->db->q($sql, "ii", $columnId, $userId);
        return $result !== false && count($result) > 0;
    }

    // Getters
    public function getColumnName() { return $this->columnName; }
    public function getColumnFlag() { return $this->columnFlag; }
    public function getColumnOrderBy() { return $this->columnOrderBy; }
    public function getColumnDisplayLimit() { return $this->columnTaskDisplayLimit; }
}
?>