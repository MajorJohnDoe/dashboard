<?php
namespace Dashboard\Taskboard;

use Dashboard\Core\Interfaces\DatabaseInterface;

class Board
{
    private $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    // Fetch all boards for a specific user
    public function getAllBoardsForUser($userId)
    {
        $sql = "SELECT * FROM `tm_board` WHERE `user_id` = ?";
        return $this->db->q($sql, "i", $userId);
    }

    // Fetch a specific board by ID for a user
    public function loadBoardDataById($boardId)
    {
        $sql = "SELECT * FROM `tm_board` WHERE `id` = ? LIMIT 1";
        return $this->db->q($sql, "i", $boardId);
    }

    // Fetch all labels for a specific board
    public function loadBoardLabels($boardId)
    {
        $sql = "SELECT * FROM `tm_label` WHERE `board_id` = ? ORDER BY `label_name`";
        return $this->db->q($sql, "i", $boardId);
    }

    // Full-text search for board labels, with a fallback to LIKE
    public function searchBoardLabels($boardId, $searchInput)
    {
        // Full-text search
        $sqlFullText = "SELECT * FROM `tm_label` WHERE `board_id` = ? AND MATCH(`label_name`) AGAINST (?) LIMIT 20";
        $results = $this->db->q($sqlFullText, "is", $boardId, $searchInput);

        if (!$results) {
            // Fallback to LIKE search
            $sqlLike = "SELECT * FROM `tm_label` WHERE `board_id` = ? AND `label_name` LIKE ? LIMIT 20";
            $searchInputLike = '%' . $searchInput . '%';
            $results = $this->db->q($sqlLike, "is", $boardId, $searchInputLike);
        }

        return $results;
    }

    // Add a label to a specific board
    public function addLabel($boardId, $labelName, $labelColor)
    {
        $sql = "INSERT INTO `tm_label` (`board_id`, `label_color`, `label_name`) VALUES (?, ?, ?)";
        return $this->db->q($sql, "iss", $boardId, $labelColor, $labelName);
    }

    // Edit an existing label
    public function editLabel($labelId, $labelName, $labelColor)
    {
        $sql = "UPDATE `tm_label` SET `label_color` = ?, `label_name` = ? WHERE `id` = ? LIMIT 1";
        return $this->db->q($sql, "ssi", $labelColor, $labelName, $labelId);
    }

    // Delete a label by ID
    public function deleteLabelByID($labelId)
    {
        $sql = "DELETE FROM `tm_label` WHERE `id` = ? LIMIT 1";
        return $this->db->q($sql, "i", $labelId);
    }

    // Set a label as a favorite (toggle)
    public function toggleLabelFavorite($labelId)
    {
        $sql = "UPDATE `tm_label` 
                SET `is_favorite` = CASE 
                    WHEN `is_favorite` = 0 THEN 1 
                    ELSE 0 
                END
                WHERE `id` = ? 
                LIMIT 1";
        return $this->db->q($sql, "i", $labelId);
    }

    // Add a new board for a user
    public function addBoard($userId, $boardName)
    {
        $sql = "INSERT INTO `tm_board` (`user_id`, `tm_name`) VALUES (?, ?)";
        return $this->db->q($sql, "is", $userId, $boardName);
    }

    // Update board data
    public function updateBoard($boardId, $boardName)
    {
        $sql = "UPDATE `tm_board` SET `tm_name` = ? WHERE `id` = ? LIMIT 1";
        return $this->db->q($sql, "si", $boardName, $boardId);
    }

    // Delete a board
    public function deleteBoard($boardId)
    {
        $sql = "DELETE FROM `tm_board` WHERE `id` = ? LIMIT 1";
        return $this->db->q($sql, "i", $boardId);
    }

    public function nbrBoards($userId)
    {
        $sql = "SELECT COUNT(*) AS `board_count` FROM `tm_board` WHERE `user_id` = ?";
        $result = $this->db->q($sql, "i", $userId);
        if ($result) {
            return intval($result[0]['board_count']);
        } else {
            return 0;
        }
    }

    public function getLabelData($labelId)
    {
        $sql = "SELECT * FROM `tm_label` WHERE `id` = ? LIMIT 1";
        return $this->db->q($sql, "i", $labelId);
    }

    // Validate ownership of a board by the user
    public function validateBoardOwnership($userId, $boardId)
    {
        $sql = "SELECT 1 FROM `tm_board` WHERE `id` = ? AND `user_id` = ? LIMIT 1";
        $result = $this->db->q($sql, "ii", $boardId, $userId);
        return $result !== false && count($result) > 0;
    }

    // Validate ownership of a label by the user
    public function validateLabelOwnership($userId, $labelId)
    {
        $sql = "SELECT 1 FROM `tm_label` label
                JOIN `tm_board` board ON label.`board_id` = board.`id`
                WHERE label.id = ? AND board.user_id = ? LIMIT 1";
        $result = $this->db->q($sql, "ii", $labelId, $userId);
        return $result !== false && count($result) > 0;
    }
}
?>