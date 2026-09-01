<?php
namespace Dashboard\Core;

use Dashboard\Core\Interfaces\DatabaseInterface;
use Dashboard\Core\User;

class UserController
{
    private $db;
    private $user;

    public function __construct(DatabaseInterface $db, User $user)
    {
        $this->db = $db;
        $this->user = $user;
    }

    public function updatePassword(string $currentPassword, string $newPassword): array
    {
        // Verify current password
        $user = $this->db->q("SELECT user_password FROM `user` WHERE user_id = ? LIMIT 1", 'i', $this->user->getUserId());
        if (!$user || !isset($user[0]['user_password'])) {
            return ['success' => false, 'message' => 'User not found'];
        }

        $storedHash = $user[0]['user_password'];
        $computedHash = $this->user->passwordHashFunction($currentPassword, _USER_PASSWORD_SALT);

        if ($computedHash !== $storedHash) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }

        // Update password
        $newPasswordHash = $this->user->passwordHashFunction($newPassword, _USER_PASSWORD_SALT);
        $updateResult = $this->db->q("UPDATE `user` SET user_password = ? WHERE user_id = ?", 'si', $newPasswordHash, $this->user->getUserId());

        if ($updateResult === false) {
            return ['success' => false, 'message' => 'Failed to update password'];
        }

        return ['success' => true, 'message' => 'Password updated successfully'];
    }

    public function updateProfilePhoto($file): array
    {
        if (!isset($file['tmp_name']) || !\is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'message' => 'No file uploaded'];
        }

        $userId = $this->user->getUserId();
        $uploadDir = \dirname(__DIR__, 2) . "/user_upload/{$userId}/";
        
        if (!\file_exists($uploadDir)) {
            if (!\mkdir($uploadDir, 0755, true)) {
                return ['success' => false, 'message' => 'Failed to create upload directory'];
            }
        }

        $fileName = 'profileimg.jpg';
        $filePath = $uploadDir . $fileName;

        // The image is already cropped client-side, just save it directly
        if (!\move_uploaded_file($file['tmp_name'], $filePath)) {
            return ['success' => false, 'message' => 'Failed to save uploaded file'];
        }

        // Update user avatar in the database
        $avatarPath = "/user_upload/{$userId}/profileimg.jpg";
        $updateResult = $this->db->q("UPDATE `user` SET user_avatar = ? WHERE user_id = ?", 'si', $avatarPath, $userId);

        if ($updateResult === false) {
            return ['success' => false, 'message' => 'Failed to update profile photo in database'];
        }

        return ['success' => true, 'message' => 'Profile photo updated successfully'];
    }

    public function deleteProfilePhoto(): array
    {
        $userId = $this->user->getUserId();
        $user = $this->db->q("SELECT user_avatar FROM `user` WHERE user_id = ? LIMIT 1", 'i', $userId);
        if (!$user || !isset($user[0]['user_avatar']) || $user[0]['user_avatar'] === '') {
            return ['success' => false, 'message' => 'User not found or no profile photo set'];
        }

        $avatarPath = \dirname(__DIR__, 2) . $user[0]['user_avatar'];
        if (\file_exists($avatarPath)) {
            \unlink($avatarPath);
        }

        $updateResult = $this->db->q("UPDATE `user` SET user_avatar = '' WHERE user_id = ?", 'i', $userId);

        if ($updateResult === false) {
            return ['success' => false, 'message' => 'Failed to delete profile photo'];
        }

        return ['success' => true, 'message' => 'Profile photo deleted successfully'];
    }

    public function updateChatGPTAPIKey(string $newAPIKey): array
    {
        $updateResult = $this->db->q("UPDATE `user` SET `gpt_api_key` = ? WHERE `user_id` = ? LIMIT 1", 'si', $newAPIKey, $this->user->getUserId());
        if ($updateResult === false) {
            return ['success' => false, 'message' => 'Failed to update API key'];
        }

        return ['success' => true, 'message' => 'API key changed successfully'];
    }

    public function getUserAvatar(int $userId): string 
    {
        if (!$userId) {
            return '/assets/img/default_profile.jpg';
        }
        
        $sql = "SELECT `user_avatar` FROM `user` WHERE `user_id` = ? LIMIT 1";
        $result = $this->db->q($sql, 'i', $userId);
        
        if ($result && isset($result[0]['user_avatar']) && $result[0]['user_avatar'] !== '') {
            return $result[0]['user_avatar'];
        }
        
        return '/assets/img/default_profile.jpg';
    }
}

?>
