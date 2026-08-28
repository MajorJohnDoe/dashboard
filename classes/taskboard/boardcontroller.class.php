<?php
namespace Dashboard\Taskboard;

use Dashboard\Core\Interfaces\DatabaseInterface;
use Dashboard\Core\User;
use Dashboard\Core\NotificationsController;
use Dashboard\Core\Notifications\NotificationService;
use Dashboard\Core\Notifications;

class BoardController
{
    private $db;
    private $board;
    private $user;

    public function __construct(DatabaseInterface $db, User $user)
    {
        $this->db = $db;
        $this->board = new Board($db);
        $this->user = $user;
    }

    // Handle board invitation acceptance
    public function handleInvitationAccept()
    {
        $boardId = $_POST['board_id'] ?? null;
        $notificationId = $_POST['notification_id'] ?? null;

        if (!$boardId) {
            return ['success' => false, 'message' => 'Board ID is required'];
        }

        // Get board share details from the Board class
        $shareResult = $this->board->getBoardShareDetails($boardId, $this->user->getUserId());
        
        if (empty($shareResult)) {
            return ['success' => false, 'message' => 'Board share not found'];
        }

        $inviterId = $shareResult[0]['shared_by_user_id'];

        // Mark the notification as read first
        $notificationsController = new NotificationsController($this->db, $this->user, null);
        if ($notificationId) {
            $notificationsController->handleMarkAsRead($notificationId);
        }

        error_log("Board share query result: " . print_r($shareResult, true));
        error_log("Found shared_by_user_id: $inviterId");

        // Update the share status
        error_log("Updating board share status to 'accepted'");
        try {
            $result = $this->board->updateBoardShareStatus($boardId, $this->user->getUserId(), 'accepted');
        } catch (\Exception $e) {
            error_log("Error updating board share status: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update board share status: ' . $e->getMessage()];
        }

        if ($result) {
            error_log("Board share status updated successfully");
            // Create a notification for the inviter
            $notificationService = new NotificationService($this->db);
            $notificationService->notifyBoardAccept($inviterId, $boardId, $this->user->getUserId());

            // Delete the invitation notification
            $this->deleteBoardInviteNotification($boardId, $this->user->getUserId());

            return [
                'success' => true,
                'notificationsUpdate' => true,
                'message' => 'Board invitation accepted'
            ];
        }

        return ['success' => false, 'message' => 'Failed to accept board invitation'];
    }

    // Handle board invitation decline
    public function handleInvitationDecline()
    {
        $boardId = $_POST['board_id'] ?? null;
        $notificationId = $_POST['notification_id'] ?? null;

        if (!$boardId) {
            return ['success' => false, 'message' => 'Board ID is required'];
        }

        // Get board share details from the Board class
        $shareResult = $this->board->getBoardShareDetails($boardId, $this->user->getUserId());
        
        if (empty($shareResult)) {
            return ['success' => false, 'message' => 'Board share not found'];
        }

        $inviterId = $shareResult[0]['shared_by_user_id'];

        // Mark the notification as read first
        $notificationsController = new NotificationsController($this->db, $this->user, null);
        if ($notificationId) {
            $notificationsController->handleMarkAsRead($notificationId);
        }

        // Update the share status
        $result = $this->board->updateBoardShareStatus($boardId, $this->user->getUserId(), 'declined');

        if ($result) {
            // Create a notification for the inviter
            $notificationService = new NotificationService($this->db);
            $notificationService->notifyBoardDecline($inviterId, $boardId, $this->user->getUserId());

            // Delete the invitation notification
            $this->deleteBoardInviteNotification($boardId, $this->user->getUserId());

            return [
                'success' => true,
                'notificationsUpdate' => true,
                'message' => 'Board invitation declined'
            ];
        }

        return ['success' => false, 'message' => 'Failed to decline board invitation'];
    }

    // Share a board with another user via email
    public function shareBoard($boardId, $email, $accessLevel = 'read')
    {
        // Validate board ownership
        if (!$this->board->validateBoardOwnership($this->user->getUserId(), $boardId)) {
            return ['success' => false, 'message' => 'You do not own this board.'];
        }

        // Share the board
        $result = $this->board->shareBoardWithUser($boardId, $email, $this->user->getUserId(), $accessLevel);

        if ($result) {
            return ['success' => true, 'message' => 'Board shared successfully.'];
        } else {
            return ['success' => false, 'message' => 'Failed to share board. User not found or already has access.'];
        }
    }

    // Get all users who have access to a board
    public function getBoardUsers($boardId)
    {
        // Validate board ownership or shared access
        if (!$this->board->validateBoardOwnership($this->user->getUserId(), $boardId) && 
            !$this->board->validateBoardWriteAccess($this->user->getUserId(), $boardId)) {
            return [];
        }

        return $this->board->getBoardUsers($boardId);
    }

    // Update a user's access level for a board
    public function updateBoardAccess($boardId, $userId, $accessLevel)
    {
        // Validate board ownership
        if (!$this->board->validateBoardOwnership($this->user->getUserId(), $boardId)) {
            return ['success' => false, 'message' => 'You do not own this board.'];
        }

        $result = $this->board->updateBoardShareStatus($boardId, $userId, $accessLevel);

        if ($result) {
            try {
                // Create a notification for the user whose access was changed
                $notificationService = new NotificationService($this->db);
                $notificationService->notifyBoardAccessChanged($userId, $boardId, $accessLevel);
            } catch (\Exception $e) {
                error_log("Failed to create access change notification: " . $e->getMessage());
                // Continue even if notification fails
            }
        }

        return [
            'success' => (bool)$result,
            'message' => $result ? 'Access level updated successfully.' : 'Failed to update access level.'
        ];
    }

    // Remove a user's access to a board
    public function removeBoardAccess($boardId, $userId)
    {
        // Validate board ownership
        if (!$this->board->validateBoardOwnership($this->user->getUserId(), $boardId)) {
            return ['success' => false, 'message' => 'You do not own this board.'];
        }

        $result = $this->board->removeBoardAccess($boardId, $userId);
        
        return [
            'success' => (bool)$result,
            'message' => $result ? 'User removed from board successfully.' : 'Failed to remove user from board.'
        ];
    }

    public function createBoard()
    {
        $newBoardName = trim($_POST['boardName']);
        $response = $this->handleCreateBoard($newBoardName);

        if ($response['success']) {
            triggerResponse([
                "newBoard" => true, 
                "taskBoardColumnList" => true,
                'globalMessagePopupUpdate' => ['type' => 'success', 'message' => $response['message']]
            ], false);
        } else {
            triggerResponse([
                "globalMessagePopupUpdate" => ['type' => 'error', 'message' => $response['message']]
            ], false);
        }

        // Return an empty string because triggerResponse has already sent the response
        return '';
    }

    // Handle creating a new board
    public function handleCreateBoard()
    {
        $boardName = $_POST['boardName'] ?? '';
        if (empty($boardName)) {
            return ['success' => false, 'message' => 'Board title cannot be empty'];
        }

        if($this->board->nbrBoards($this->user->getUserId()) >= _TASKBOARD_MAXIMUM_BOARDS){
            return ['success' => false, 'message' => 'Maximum number of boards reached.'];
        }

        $result = $this->board->addBoard($this->user->getUserId(), trim($boardName));

        if ($result) {
            return ['success' => true, 'message' => 'Board created successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to create board'];
        }
    }

    // Handle editing an existing board
    public function handleEditBoard($boardId, $boardTitle)
    {
        if (!$this->board->validateBoardOwnership($this->user->getUserId(), $boardId)) {
            return ['success' => false, 'message' => 'Board ownership error.'];
        }

        if (empty($boardTitle)) {
            return ['success' => false, 'message' => 'Board title cannot be empty'];
        }

        $result = $this->board->updateBoard($boardId, $boardTitle);

        if ($result) {
            return ['success' => true, 'message' => 'Board updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to update board'];
        }
    }

    // Handle deleting a board
    public function handleDeleteBoard($boardId)
    {
        if (!$this->board->validateBoardOwnership($this->user->getUserId(), $boardId)) {
            return ['success' => false, 'message' => 'Board ownership error.'];
        }

        $result = $this->board->deleteBoard($boardId);

        if ($result) {
            return ['success' => true, 'message' => 'Board deleted successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to delete board'];
        }
    }

    // Search labels on a board
    public function searchBoardLabels($boardId, $searchInput)
    {
        if ($this->board->validateBoardOwnership($this->user->getUserId(), $boardId)) {
            return $this->board->searchBoardLabels($boardId, $searchInput);
        }

        return ['success' => false, 'message' => 'Invalid board or permissions.'];
    }

    public function loadBoardLabels($boardId)
    {
        if (!$this->board->validateBoardOwnership($this->user->getUserId(), $boardId)) {
            return ['success' => false, 'message' => 'Board does not exist.'];
        }

        return $this->board->loadBoardLabels($boardId);
    }

    public function loadLabelDataByID($labelId)
    {
        if (!$this->board->validateLabelOwnership($this->user->getUserId(), $labelId)) {
            return ['success' => false, 'message' => 'Label ownership error.'];
        }

        $labelData = $this->board->getLabelData($labelId);
        return ['success' => true, 'data' => $labelData[0]];
    }

    // Add a label to a board
    public function addLabel($boardId, $labelName, $labelColor)
    {
        if ($this->board->validateBoardOwnership($this->user->getUserId(), $boardId)) {
            $result = $this->board->addLabel($boardId, trim($labelName), trim($labelColor));
            return $result ? ['success' => true, 'message' => 'Label added successfully.'] : ['success' => false, 'message' => 'Failed to add label.'];
        }

        return ['success' => false, 'message' => 'Invalid board or permissions.'];
    }

    // Edit a label on a board
    public function editLabel($labelId, $labelName, $labelColor)
    {
        if ($this->board->validateLabelOwnership($this->user->getUserId(), $labelId)) {
            $result = $this->board->editLabel($labelId, trim($labelName), trim($labelColor));
            return $result ? ['success' => true, 'message' => 'Label updated successfully.'] : ['success' => false, 'message' => 'Failed to update label.'];
        }

        return ['success' => false, 'message' => 'Invalid label or permissions.'];
    }

    // Delete a label from a board
    public function deleteLabel($labelId)
    {
        if ($this->board->validateLabelOwnership($this->user->getUserId(), $labelId)) {
            $result = $this->board->deleteLabelByID($labelId);
            return $result ? ['success' => true, 'message' => 'Label deleted successfully.'] : ['success' => false, 'message' => 'Failed to delete label.'];
        }

        return ['success' => false, 'message' => 'Invalid label or permissions.'];
    }

    // Toggle favorite for a label
    public function toggleFavoriteLabel($labelId)
    {
        if ($this->board->validateLabelOwnership($this->user->getUserId(), $labelId)) {
            $result = $this->board->toggleLabelFavorite($labelId);
            return $result ? ['success' => true, 'message' => 'Favorite status updated.'] : ['success' => false, 'message' => 'Failed to update favorite status.'];
        }

        return ['success' => false, 'message' => 'Invalid label or permissions.'];
    }

    // Fetches board data by its ID
    public function loadBoardDataById($boardId)
    {
        return $this->board->loadBoardDataById($boardId);
    }

    // We set active task board on user,
    // and redirect to the selected board page.
    public function selectBoard()
    {
        if ($this->user->setActiveTaskBoard($_GET['boardid'])) {
            header('HX-Redirect: /board');
            exit;
        }
    }

    // Validate board name
    private function validateBoardName($boardName)
    {
        return !empty($boardName) && strlen($boardName) > 3;
    }

    // Get shared boards for the user
    public function getSharedBoards()
    {
        return $this->board->getSharedBoardsForUser($this->user->getUserId());
    }

    // Get all boards for the user
    public function getAllBoards()
    {
        return $this->board->getAllBoardsForUser($this->user->getUserId());
    }

    // Get board members with their avatar URLs
    public function getBoardMembersWithAvatars($boardId)
    {
        // Get board owner first
        $boardData = $this->loadBoardDataById($boardId);
        $members = [];
        $addedUserIds = [];
        
        if ($boardData) {
            $ownerId = $boardData[0]['user_id'];
            // Add owner
            $members[] = [
                'user_id' => $ownerId,
                'is_owner' => true
            ];
            $addedUserIds[] = $ownerId;
        }
        
        // Get shared users
        $sharedUsers = $this->getBoardUsers($boardId);
        foreach ($sharedUsers as $user) {
            if ($user['status'] === 'accepted' && !in_array($user['user_id'], $addedUserIds)) {
                $members[] = [
                    'user_id' => $user['user_id'],
                    'is_owner' => false
                ];
                $addedUserIds[] = $user['user_id'];
            }
        }
        
        return $members;
    }

    // Retrieves the board's ID
    public function getBoardId()
    {
        return $this->user->getActiveTaskBoard();
    }

    // Retrieves the board's title
    public function getBoardTitle()
    {
        $boardData = $this->loadBoardDataById($this->getBoardId());
        return $boardData['tm_name'] ?? null;
    }

    // Delete a board invitation notification
    private function deleteBoardInviteNotification(int $boardId, int $userId): bool
    {
        $notifications = new Notifications($this->db);
        return $notifications->deleteBoardInviteNotification($boardId, $userId);
    }
}
