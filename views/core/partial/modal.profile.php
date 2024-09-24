<?php
use Dashboard\Core\UserController;

$userController = new UserController($db, $user);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $result = ['success' => false, 'message' => 'Invalid action'];

    switch ($action) {
        case 'change_gpt_key':
            $chatGPTKey = $_POST['chatgpt_api_key'] ?? '';
            $result = $userController->updateChatGPTAPIKey($chatGPTKey);
            break;
        case 'update_password':
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $result = $userController->updatePassword($currentPassword, $newPassword);
            break;
        case 'update_photo':
            if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == UPLOAD_ERR_OK) {
                $result = $userController->updateProfilePhoto($_FILES['profile_photo']);
            } else {
                $result = ['success' => false, 'message' => 'No file uploaded or upload error occurred'];
            }
            break;
        case 'delete_photo':
            $result = $userController->deleteProfilePhoto();
            break;
    }

    if ($result['success']) {
        triggerResponse(["refreshProfileModal" => true, "globalMessagePopupUpdate" => ['type' => 'success', 'message' => $result['message']]], false);
    } else {
        triggerResponse(["globalMessagePopupUpdate" => ['type' => 'error', 'message' => $result['message']]], false);
    }

}

$userPhotoPath = $user->getProfilePhotoPath();
?>

<div id="modal-profile-settings" 
    class="modal-container" 
    hx-get="/account/settings" 
    hx-trigger="refreshProfileModal from:body"
    hx-target="#modal-profile-settings" 
    hx-swap="outerHTML"
    >
    <div class="dialog" style="width: 50rem; height: 50%;">
        <div class="dialog-header">
            <span>Account settings</span>
            <button class="close-modal-btn btn">X</button>
        </div>
        <div class="formOuter">
        <!-- end of modal header -->

            <div class="flex-table nice-form-group">

                <!-- Profile Photo Section -->
                <form id="form_profile_photo" method="POST" enctype="multipart/form-data" hx-post="/account/settings" hx-encoding="multipart/form-data" hx-target="#modal-profile-settings .formOuter" hx-swap="beforeend">
                    <div id="profile-photo-section">
                        <div class="flex-row">
                            <div class="flex-cell flex-cell-vcenter flex-cell-shrink">
                                <img src="<?= $userPhotoPath ?>" alt="Profile Photo" style="width: 10rem; height: 10rem; object-fit: cover; border-radius: 50%;">
                            </div>
                            <div class="flex-cell flex-cell-vcenter">
                                <input type="file" name="profile_photo" id="profile_photo" accept="image/*" style="display: none;" hx-trigger="change" hx-post="/account/settings" hx-encoding="multipart/form-data" hx-target="#modal-profile-settings .formOuter" hx-swap="beforeend">
                                <input type="hidden" name="action" value="update_photo">
                                <button type="button" class="btn btn-green" onclick="document.getElementById('profile_photo').click();" style="margin-top: 1rem;">Change picture</button><br>
                                <button type="button" class="btn btn-dark-gray btn-hover-red" hx-post="/account/settings" hx-vals='{"action": "delete_photo"}' style="margin-top: 1rem;">Delete picture</button>
                            </div>
                        </div>
                    </div>
                </form>
                
                <!-- Password Change Section -->
                <form id="form_change_password" hx-post="/account/settings" hx-target="#modal-profile-settings .formOuter" hx-swap="beforeend">
                    <input type="hidden" name="action" value="update_password">
                    <div class="flex-row">
                        <div class="flex-cell "><h3>Password settings:</h3></div>
                    </div>
                    <div class="flex-row">
                        <div class="flex-cell">Current password:</div>
                        <div class="flex-cell"><input type="password" name="current_password" required></div>
                    </div>
                    <div class="flex-row">
                        <div class="flex-cell">New password:</div>
                        <div class="flex-cell"><input type="password" name="new_password" required></div>
                    </div>
                    <div class="flex-row">
                        <div class="flex-cell flex-vertical-center flex-right">
                            <button type="submit" class="btn btn-green">Update password</button>
                        </div>
                    </div>
                </form>

                <!-- Password Change Section -->
                <form id="form_change_gpt_key" hx-post="/account/settings" hx-target="#modal-profile-settings .formOuter" hx-swap="beforeend">
                    <input type="hidden" name="action" value="change_gpt_key">
                    <div class="flex-row">
                        <div class="flex-cell"><h3>AI API:</h3></div>
                    </div>
                    <div class="flex-row">
                        <div class="flex-cell">ChatGPT API key:</div>
                        <div class="flex-cell"><input type="text" name="chatgpt_api_key" id="chatgpt_api_key" value="<?=$user->getChatGPTAPIKey()?>"></div>
                    </div>
                    <div class="flex-row">
                        <div class="flex-cell"></div>
                        <div class="flex-cell flex-vertical-center flex-right">
                            <button type="submit" class="btn btn-green" style="margin-bottom: 1rem;">Update API key</button><br>
                        </div>
                    </div>
                </form>
               
            

            </div>

        <!-- end of modal -->
        </div>
    </div>
</div>
