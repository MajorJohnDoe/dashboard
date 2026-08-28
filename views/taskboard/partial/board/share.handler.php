<?php
use Dashboard\Taskboard\BoardController;
use Dashboard\Core\Notifications;

// Initialize the controller
$controller = new BoardController($db, $user);
$action = basename($_SERVER['REQUEST_URI']); // Get the action from URL

// Handle accept/decline actions
if ($action === 'accept' || $action === 'decline') {
    error_log("Handling board invitation $action with data: " . print_r($_POST, true));
    
    $result = ($action === 'accept') 
        ? $controller->handleInvitationAccept() 
        : $controller->handleInvitationDecline();
    
    triggerResponse($result);
    exit;
}

// Regular sharing functionality
$boardId = $user->getActiveTaskBoard();

// Handle POST requests for sharing a board
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'share') {
    $email = $_POST['share_email'] ?? '';
    $accessLevel = $_POST['access_level'] ?? 'read';
    
    $result = $controller->shareBoard($boardId, $email, $accessLevel);
    
    if ($result['success']) {
        triggerResponse([
            "boardMembersUpdate" => true,
            "globalMessagePopupUpdate" => ['type' => 'success', 'message' => $result['message']]
        ]);
    } else {
        triggerResponse([
            "globalMessagePopupUpdate" => ['type' => 'error', 'message' => $result['message']]
        ]);
    }
}

// Handle PUT requests for updating access levels
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    parse_str(file_get_contents("php://input"), $putData);
    error_log("PUT data received: " . print_r($putData, true));
    $userId = $putData['user_id'] ?? '';
    $accessLevel = $putData['access_level'] ?? '';
    error_log("Updating access - boardId: $boardId, userId: $userId, accessLevel: $accessLevel");
    
    $result = $controller->updateBoardAccess($boardId, $userId, $accessLevel);
    
    if ($result['success']) {
        triggerResponse([
            "boardMembersUpdate" => true,
            "globalMessagePopupUpdate" => ['type' => 'success', 'message' => $result['message']]
        ]);
    } else {
        triggerResponse([
            "globalMessagePopupUpdate" => ['type' => 'error', 'message' => $result['message']]
        ]);
    }
}

// Handle DELETE requests for removing access
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    parse_str(file_get_contents("php://input"), $deleteData);
    $userId = $deleteData['user_id'] ?? '';
    
    $result = $controller->removeBoardAccess($boardId, $userId);
    
    if ($result['success']) {
        // Delete any pending board invitation notification for this user
        $notifications = new Notifications($db);
        $notifications->deleteBoardInviteNotification($boardId, (int)$userId);
        
        triggerResponse([
            "boardMembersUpdate" => true,
            "notificationsUpdate" => true,
            "globalMessagePopupUpdate" => ['type' => 'success', 'message' => $result['message']]
        ]);
    } else {
        triggerResponse([
            "globalMessagePopupUpdate" => ['type' => 'error', 'message' => $result['message']]
        ]);
    }
}
