<?php
namespace Dashboard\Core;

use Dashboard\Core\Interfaces\DatabaseInterface;

/**
 * Centralized access-control service for board-scoped resources.
 *
 * Single source of truth for "can this user act on this task/column/board?"
 * checks. Replaces the ownership-validation SQL that was previously
 * copy-pasted across Task, TaskController, Column, ColumnController and Board.
 *
 * Access model:
 *  - A user always has full access to boards they own (tm_board.user_id).
 *  - A user has access to boards shared with them via board_shares with
 *    status = 'accepted'. Write access additionally requires
 *    access_level = 'write'.
 *
 * All methods return the matching row(s) on success (truthy) or false on
 * denial, so callers can keep their existing `if (!$guard->...)` style.
 */
class AccessGuard {
    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db) {
        $this->db = $db;
    }

    /**
     * SQL fragment expressing "board is owned by :userId OR shared-accepted
     * with :userId". Expects bindings for the share-user placeholder and the
     * owner placeholder, in that order.
     */
    public const BOARD_ACCESS_CONDITION = '(
        board.`user_id` = ?
        OR EXISTS (
            SELECT 1 FROM `board_shares` bs
            WHERE bs.`board_id` = board.`id`
              AND bs.`user_id` = ?
              AND bs.`status` = \'accepted\'
        )
    )';

    /**
     * Can the user view (read) this board? Owner or accepted share.
     */
    public function canViewBoard(int $userId, int $boardId): bool {
        $sql = "SELECT 1
                FROM `tm_board` board
                WHERE board.`id` = ?
                AND " . self::BOARD_ACCESS_CONDITION . "
                LIMIT 1";
        $result = $this->db->q($sql, "iii", $boardId, $userId, $userId);
        return !empty($result);
    }

    /**
     * Can the user write to this board? Owner, or accepted share with
     * access_level = 'write'.
     */
    public function canWriteBoard(int $userId, int $boardId): bool {
        // Owners always have write access.
        $sql = "SELECT 1 FROM `tm_board` WHERE `id` = ? AND `user_id` = ? LIMIT 1";
        $result = $this->db->q($sql, "ii", $boardId, $userId);
        if (!empty($result)) {
            return true;
        }

        // Shared write access.
        $sql = "SELECT 1
                FROM `board_shares`
                WHERE `board_id` = ? AND `user_id` = ?
                AND `status` = 'accepted' AND `access_level` = 'write'
                LIMIT 1";
        $result = $this->db->q($sql, "ii", $boardId, $userId);
        return !empty($result);
    }

    /**
     * Does the task belong to a board the user can view?
     * Returns the task's board_id on success, false on denial.
     *
     * @return int|false
     */
    public function assertTask(int $userId, int $taskId) {
        $sql = "SELECT task.`board_id`
                FROM `tm_task` task
                JOIN `tm_board` board ON task.`board_id` = board.`id`
                WHERE task.`task_id` = ?
                AND " . self::BOARD_ACCESS_CONDITION . "
                LIMIT 1";
        $result = $this->db->q($sql, "iii", $taskId, $userId, $userId);
        return $result ? (int)$result[0]['board_id'] : false;
    }

    /**
     * Does the column belong to a board the user can view?
     * Returns the column row (including board_id) on success, false on denial.
     *
     * @return array|false
     */
    public function assertColumn(int $userId, int $columnId) {
        $sql = "SELECT col.*, col.`id` AS column_id, board.`id` AS board_id
                FROM `tm_column` col
                JOIN `tm_board` board ON col.`parent_id` = board.`id`
                WHERE col.`id` = ?
                AND " . self::BOARD_ACCESS_CONDITION . "
                LIMIT 1";
        $result = $this->db->q($sql, "iii", $columnId, $userId, $userId);
        return $result ?: false;
    }

    /**
     * Does the label belong to a board the user can view?
     */
    public function canAccessLabel(int $userId, int $labelId): bool {
        $sql = "SELECT 1
                FROM `tm_label` label
                JOIN `tm_board` board ON label.`board_id` = board.`id`
                WHERE label.`id` = ?
                AND " . self::BOARD_ACCESS_CONDITION . "
                LIMIT 1";
        $result = $this->db->q($sql, "iii", $labelId, $userId, $userId);
        return !empty($result);
    }
}
