<?php
namespace Dashboard\Taskboard;

use Dashboard\Core\Interfaces\DatabaseInterface;
use Dashboard\Core\AccessGuard;

class Board
{
    private $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    public function shareBoardWithUser($boardId, $userEmail, $sharedByUserId, $accessLevel = 'read')
    {
        if (!in_array($accessLevel, ['read', 'write'])) {
            return false;
        }

        // Check if user exists and get their details using email
        $sql = "SELECT user_id FROM `user` WHERE `user_email` = ? LIMIT 1";
        $userResult = $this->db->q($sql, "s", $userEmail);
        
        if (!$userResult || count($userResult) === 0) {
            return false;
        }
        
        $userId = $userResult[0]['user_id'];

        try {
            // Add share record
            $sql = "INSERT INTO `board_shares` (`board_id`, `user_id`, `shared_by_user_id`, `access_level`, `status`) 
                    VALUES (?, ?, ?, ?, 'pending')
                    ON DUPLICATE KEY UPDATE `access_level` = ?, `status` = 'pending'";
            $shareResult = $this->db->q($sql, "iiiss", $boardId, $userId, $sharedByUserId, $accessLevel, $accessLevel);

            if ($shareResult) {
                // Create notification for the invited user
                $sql = "INSERT INTO `notifications` (`user_id`, `type`, `data`) VALUES (?, 'board_invite', ?)";
                $notificationData = json_encode([
                    'board_id' => $boardId,
                    'inviter_id' => $sharedByUserId,
                    'access_level' => $accessLevel
                ]);
                $this->db->q($sql, "is", $userId, $notificationData);
                error_log("Board shared successfully with user $userId");
                return true;
            } else {
                error_log("Failed to create board share record");
                return false;
            }
        } catch (\Exception $e) {
            error_log("Error sharing board: " . $e->getMessage());
            return false;
        }
    }

    // Get shared boards for a user
    public function getSharedBoardsForUser($userId)
    {
        $sql = "SELECT b.*, bs.access_level, bs.status 
                FROM `tm_board` b 
                JOIN `board_shares` bs ON b.id = bs.board_id 
                WHERE bs.user_id = ? AND bs.status = 'accepted'";
        return $this->db->q($sql, "i", $userId);
    }

    // Get all users who have access to a board
    public function getBoardUsers($boardId) 
    {
        $sql = "SELECT u.user_id, u.user_username, u.user_email, bs.access_level, bs.status, bs.shared_by_user_id 
                FROM `board_shares` bs
                JOIN `user` u ON bs.user_id = u.user_id
                WHERE bs.board_id = ?";
        return $this->db->q($sql, "i", $boardId);
    }

    // Update board share status (accept/decline) or access level (read/write)
    public function updateBoardShareStatus($boardId, $userId, $value)
    {
        error_log("Updating board share: boardId=$boardId, userId=$userId, value=$value");

        try {
            // Check if this is an access level update (read/write) or status update (accepted/declined)
            if (in_array($value, ['read', 'write'])) {
                // Update access_level column
                $sql = "UPDATE `board_shares` SET `access_level` = ? WHERE `board_id` = ? AND `user_id` = ?";
                $result = $this->db->q($sql, "sii", $value, $boardId, $userId);
            } elseif (in_array($value, ['accepted', 'declined'])) {
                // Update status column
                $sql = "UPDATE `board_shares` SET `status` = ? WHERE `board_id` = ? AND `user_id` = ?";
                $result = $this->db->q($sql, "sii", $value, $boardId, $userId);
            } else {
                error_log("Invalid board share update value: $value");
                return false;
            }

            if ($result) {
                error_log("Board share updated successfully");
                return true;
            }

            error_log("No rows affected when updating board share");
            return false;
        } catch (\Exception $e) {
            error_log("Error updating board share: " . $e->getMessage());
            return false;
        }
    }

    // Remove user's access to a board
    public function removeBoardAccess($boardId, $userId) 
    {
        $sql = "DELETE FROM `board_shares` WHERE `board_id` = ? AND `user_id` = ?";
        return $this->db->q($sql, "ii", $boardId, $userId);
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

    /**
     * Batch-load board owners for multiple boards in one query.
     *
     * @param array $boardIds Board IDs
     * @return array Map of boardId => user_id (owner)
     */
    public function getBoardOwnersForBoards(array $boardIds): array
    {
        $boardIds = array_values(array_filter(array_map('intval', $boardIds)));
        if (empty($boardIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($boardIds), '?'));
        $sql = "SELECT `id`, `user_id` FROM `tm_board` WHERE `id` IN ($placeholders)";
        $result = $this->db->q($sql, str_repeat('i', count($boardIds)), ...$boardIds);

        $owners = [];
        if ($result) {
            foreach ($result as $row) {
                $owners[(int) $row['id']] = (int) $row['user_id'];
            }
        }

        return $owners;
    }

    /**
     * Batch-load accepted shared users for multiple boards in one query.
     *
     * @param array $boardIds Board IDs
     * @return array Map of boardId => list of ['user_id' => int]
     */
    public function getBoardUsersForBoards(array $boardIds): array
    {
        $boardIds = array_values(array_filter(array_map('intval', $boardIds)));
        if (empty($boardIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($boardIds), '?'));
        $sql = "SELECT bs.board_id, bs.user_id
                FROM `board_shares` bs
                WHERE bs.board_id IN ($placeholders) AND bs.status = 'accepted'";
        $result = $this->db->q($sql, str_repeat('i', count($boardIds)), ...$boardIds);

        $usersByBoard = [];
        if ($result) {
            foreach ($result as $row) {
                $usersByBoard[(int) $row['board_id']][] = ['user_id' => (int) $row['user_id']];
            }
        }

        return $usersByBoard;
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

    // Validate ownership or shared access to a board (delegated to AccessGuard)
    public function validateBoardOwnership($userId, $boardId)
    {
        return (new AccessGuard($this->db))->canViewBoard((int)$userId, (int)$boardId);
    }

    // Check if user has write access to a board (delegated to AccessGuard)
    public function validateBoardWriteAccess($userId, $boardId)
    {
        return (new AccessGuard($this->db))->canWriteBoard((int)$userId, (int)$boardId);
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

    // Get board share details for a specific board and user
    public function getBoardShareDetails($boardId, $userId)
    {
        $sql = "SELECT shared_by_user_id, status, access_level FROM board_shares WHERE board_id = ? AND user_id = ? LIMIT 1";
        return $this->db->q($sql, "ii", $boardId, $userId);
    }
}
?>
