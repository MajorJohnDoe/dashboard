<?php
namespace Dashboard\Taskboard;

use Dashboard\Taskboard\Board;
use Dashboard\Core\Interfaces\DatabaseInterface;
use Dashboard\Core\User;

class BoardController
{
    private $board;
    private $user;

    public function __construct(DatabaseInterface $db, User $user)
    {
        $this->board = new Board($db);
        $this->user = $user;
    }

    // Handle creating a new board
    public function handleCreateBoard($boardName)
    {
        if (empty($boardName)) {
            return ['success' => false, 'message' => 'Board title cannot be empty'];
        }

        if($this->board->nbrBoards($this->user->getUserId()) >= _TASKBOARD_MAXIMUM_BOARDS){
            return ['success' => false, 'message' => 'Maximum number of boards reached.'];
        }

        $result = $this->board->addBoard($this->user->getUserId(), $boardName);

        if ($result) {
            return ['success' => true, 'message' => 'Board created successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to create board'];
        }
    }

    public function createBoard()
    {
        $newBoardName = trim($_POST['boardName']);
        $response = $this->handleCreateBoard($newBoardName);

        if ($response['success']) {
            triggerResponse([
                "newBoard" => true, 
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

    // We set active task board on user,
    // and redirect to the selected board page.
    public function selectBoard()
    {
        if ($this->user->setActiveTaskBoard($_GET['boardid'])) {
            header('HX-Redirect: /board');
            exit;
        }
    }

    // Handle editing an existing board
    public function handleEditBoard($boardId, $boardTitle)
    {
        if (!$this->board->validateBoardOwnership($this->user->getUserId(), $boardId)) {
            return ['success' => false, 'message' => 'Board ownership error..'];
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
        $result = $this->board->deleteBoard($boardId, $this->user->getUserId());

        if ($result) {
            return ['success' => true, 'message' => 'Board deleted successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to delete board'];
        }
    }

    
    // Fetches board data by its ID
    public function loadBoardDataById($boardId)
    {
        if ($this->board->validateBoardOwnership($this->user->getUserId(), $boardId)) {
            return $this->board->loadBoardDataById($boardId);
        }

        return ['success' => false, 'message' => 'Invalid board.'];
    }

    // Create a new board
/*     public function createBoard($boardName)
    {
        $boardName = trim($boardName);

        // Validate board name
        if ($this->validateBoardName($boardName)) {
            $result = $this->board->addBoard($this->user->getUserId(), $boardName);
            return $result ? ['success' => true, 'message' => 'Board created successfully.'] : ['success' => false, 'message' => 'Failed to create board.'];
        }

        return ['success' => false, 'message' => 'Invalid board name.'];
    } */

    // Edit board title
    public function editBoard($boardId, $boardName)
    {
        $boardName = trim($boardName);

        // Validate ownership and board name
        if ($this->board->validateBoardOwnership($this->user->getUserId(), $boardId) && $this->validateBoardName($boardName)) {
            $result = $this->board->updateBoard($boardId, $boardName);
            return $result ? ['success' => true, 'message' => 'Board updated successfully.'] : ['success' => false, 'message' => 'Failed to update board.'];
        }

        return ['success' => false, 'message' => 'Invalid board or permissions.'];
    }

    // Delete a board
    public function deleteBoard($boardId)
    {
        if ($this->board->validateBoardOwnership($this->user->getUserId(), $boardId)) {
            $result = $this->board->deleteBoard($boardId);
            return $result ? ['success' => true, 'message' => 'Board deleted successfully.'] : ['success' => false, 'message' => 'Failed to delete board.'];
        }

        return ['success' => false, 'message' => 'Board not found or permission denied.'];
    }

    // Get all boards for the user
    public function getAllBoards()
    {
        return $this->board->getAllBoardsForUser($this->user->getUserId());
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

    // Validate board name
    private function validateBoardName($boardName)
    {
        return !empty($boardName) && strlen($boardName) > 3;
    }

/*     public function getNbrBoards()
    {
        return $this->board->nbrBoards($this->user->getUserId());
    } */

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
}
?>