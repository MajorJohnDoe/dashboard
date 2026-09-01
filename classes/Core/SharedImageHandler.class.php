<?php
namespace Dashboard\Core;

use Dashboard\Core\Interfaces\DatabaseInterface;

class SharedImageHandler {
    private $db;
    private $userId;
    private $itemId;
    private $content;
    private $uploadDir;
    private $baseDir;
    private $itemType;

    public function __construct($userId, $itemId, $content, DatabaseInterface $db, $itemType) {
        $this->userId = $userId;
        $this->itemId = $itemId;
        $this->content = $content;
        $this->db = $db;
        $this->itemType = $itemType;

        $this->baseDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'user_upload' . DIRECTORY_SEPARATOR;
        $currentYear = date('Y');
        $currentMonth = date('m');
        $this->uploadDir = $this->baseDir . $userId . DIRECTORY_SEPARATOR . $currentYear . DIRECTORY_SEPARATOR . $currentMonth . DIRECTORY_SEPARATOR;

        if (!file_exists($this->uploadDir)) {
            $mkdirResult = mkdir($this->uploadDir, 0755, true);
            if (!$mkdirResult) {
                error_log("Failed to create upload directory: " . $this->uploadDir);
                error_log("mkdir error: " . error_get_last()['message']);
            } else {
                error_log("Successfully created upload directory: " . $this->uploadDir);
            }
        }
    }

    public function processImages() {
        $pattern = '/<img[^>]+src="(data:image\/([^;]+);base64,([^"]+))"/i';
        $newContent = preg_replace_callback($pattern, [$this, 'replaceBase64WithUrl'], $this->content);

        $toDelete = $this->collectImagesToDelete($newContent);

        return ['newContent' => $newContent, 'toDelete' => $toDelete];
    }

    private function replaceBase64WithUrl($matches) {
        $base64Data = $matches[1];
        $extension = $matches[2];
        $imageData = base64_decode(explode(',', $base64Data)[1]);

        $fileName = 'img_' . $this->itemType . $this->itemId . '_' . uniqid() . '.' . $extension;
        $filePath = $this->uploadDir . $fileName;
        $webPath = str_replace(DIRECTORY_SEPARATOR, '/', str_replace($this->baseDir, '/user_upload/', $filePath));

        if (file_put_contents($filePath, $imageData) !== false) {
            $fileSize = filesize($filePath);
            $insertResult = $this->db->q(
                "INSERT INTO `shared_item_images` (`item_id`, `item_type`, `image_name`, `image_path`, `upload_type`, `file_size`) VALUES (?, ?, ?, ?, 0, ?)", 
                "isssi", 
                $this->itemId, $this->itemType, $fileName, $webPath, $fileSize
            );
            if ($insertResult === false) {
                error_log("Failed to insert image record into database");
            }
            return str_replace($base64Data, $webPath, $matches[0]);
        } else {
            error_log("Failed to save image: " . $filePath);
            return $matches[0]; // Return original if save fails
        }
    }

    private function collectImagesToDelete($newContent) {
        $existingImages = $this->db->q("SELECT image_name, image_path FROM `shared_item_images` WHERE `item_id` = ? AND `item_type` = ?", "is", $this->itemId, $this->itemType);
        $toDelete = [];

        foreach ($existingImages as $image) {
            if (strpos($newContent, $image['image_path']) === false) {
                $toDelete[] = $image['image_path'];
                $this->db->q("DELETE FROM `shared_item_images` WHERE `item_id` = ? AND `item_type` = ? AND `image_name` = ?", "iss", $this->itemId, $this->itemType, $image['image_name']);
            }
        }

        return $toDelete;
    }

}
?>