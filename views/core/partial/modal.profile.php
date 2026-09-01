<?php
use Dashboard\Core\HtmxEvents;
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
            // Photo upload is done via fetch API, return JSON
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
        case 'delete_photo':
            $result = $userController->deleteProfilePhoto();
            break;
    }

    if ($result['success']) {
        triggerResponse(HtmxEvents::successResponse($result['message'], [HtmxEvents::REFRESH_PROFILE_MODAL => true]), false);
    } else {
        triggerResponse(HtmxEvents::errorResponse($result['message']), false);
    }

}

$userPhotoPath = $user->getProfilePhotoPath();
?>

<link rel="stylesheet" href="/assets/css/image_cropper.css">
<?php
// ImageCropper is only needed in this modal - load it here, not globally.
// Deferred scripts execute in document order, so http.js/core.js (footer) run first.
$cropperVersion = filemtime(BASE_DIR . '/assets/js/image.cropper.js') ?: time();
?>
<script src="/assets/js/image.cropper.js?v=<?= $cropperVersion ?>" defer></script>
<div id="modal-profile-settings" 
    class="modal-container" 
    hx-get="/account/settings" 
    hx-trigger="refreshProfileModal from:body"
    hx-target="#modal-profile-settings" 
    hx-swap="outerHTML"
    >
    <div class="dialog dialog-md" style="height: 50%;">
        <div class="dialog-header">
            <span>Account settings</span>
            <button class="close-modal-btn btn">X</button>
        </div>
        <div class="formOuter">
        <!-- end of modal header -->

            <div class="flex-table nice-form-group">

                <!-- Profile Photo Section -->
                <div id="profile-photo-section">
                    <div class="flex-row">
                        <div class="flex-cell flex-cell-vcenter flex-cell-shrink">
                            <img src="<?= $userPhotoPath ?>?t=<?=time()?>" alt="Profile Photo" style="width: 10rem; height: 10rem; object-fit: cover; border-radius: 50%;">
                        </div>
                        <div class="flex-cell flex-cell-vcenter">
                            <input type="file" id="profile-photo-input" accept=".jpg,.jpeg" style="display: none;">
                            <button type="button" 
                                    id="change-picture-btn"
                                    class="btn btn-green" 
                                    style="margin-top: 1rem;">
                                Change picture
                            </button><br>
                            <button type="button" class="btn btn-dark-gray btn-hover-red" hx-post="/account/settings" hx-vals='{"action": "delete_photo"}' style="margin-top: 1rem;">Delete picture</button>
                        </div>
                    </div>
                </div>

                <script>
                    (function() {
                        // Get elements
                        var changeBtn = document.getElementById('change-picture-btn');
                        var fileInput = document.getElementById('profile-photo-input');
                        
                        if (changeBtn && fileInput) {
                            // Click button to open file picker
                            changeBtn.addEventListener('click', function() {
                                fileInput.click();
                            });
                            
                            // When file is selected, open cropper modal
                            fileInput.addEventListener('change', function(evt) {
                                if (!evt.target.files || evt.target.files.length === 0) {
                                    return; // No file selected
                                }
                                
                                var file = evt.target.files[0];
                                
                                // Create cropper modal dynamically
                                var modalHtml = `
                                    <div id="modal-image-cropper" class="modal-container" style="display: flex;">
                                        <div class="dialog dialog-md" style="max-height: 80vh;">
                                            <div class="dialog-header">
                                                <span>Crop Profile Photo</span>
                                                <button class="close-modal-btn btn">X</button>
                                            </div>
                                            <div class="formOuter" style="padding: 1rem;">
                                                <div id="crop-preview"></div>
                                            </div>
                                        </div>
                                    </div>
                                `;
                                
                                // Add modal to body
                                var tempDiv = document.createElement('div');
                                tempDiv.innerHTML = modalHtml;
                                document.body.appendChild(tempDiv.firstElementChild);
                                
                                // Initialize cropper
                                if (window._imageCropper) {
                                    delete window._imageCropper;
                                }
                                window._imageCropper = new ImageCropperFromFile(file, 'crop-preview');
                                
                                // Clear input so we can select the same file again
                                fileInput.value = '';
                            });
                        }
                    })();
                </script>
                
                <!-- Password Change Section -->
                <form id="form_change_password" hx-post="/account/settings" hx-target="#modal-profile-settings .formOuter" hx-swap="beforeend">
                    <?= \Dashboard\Core\CsrfProtection::getTokenField() ?>
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
                    <?= \Dashboard\Core\CsrfProtection::getTokenField() ?>
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
