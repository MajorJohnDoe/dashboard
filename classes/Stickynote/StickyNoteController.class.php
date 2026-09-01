<?php
namespace Dashboard\Stickynote;

use Dashboard\Core\User;
use Dashboard\Core\Interfaces\DatabaseInterface;
use Dashboard\Core\ItemImageService;
use Dashboard\Stickynote\StickyNote;
use Dashboard\Stickynote\StickyCategory;

class StickyNoteController {
    private $user;
    private $stickyNote;
    private $stickyCategory;
    private $db;

    public function __construct(User $user, StickyNote $stickyNote, StickyCategory $stickyCategory, DatabaseInterface $db) {
        $this->user = $user;
        $this->stickyNote = $stickyNote;
        $this->stickyCategory = $stickyCategory;
        $this->db = $db;
    }

    public function getCategories(): array {
        return $this->stickyNote->getCategories($this->user->getUserId());
    }

    public function getCategoryData($category_id): array {
        return $this->stickyCategory->categoryData($category_id, $this->user->getUserId());
    }

    public function getNoteData(int $note_id): ?array {
        return $this->stickyNote->getNoteData($note_id, $this->user->getUserId());
    }

    public function getNotes(int $limit = 100, ?int $category_id = null, int $content_preview_length = 120): array {
        return $this->stickyNote->getNotes($this->user->getUserId(), $limit, $category_id, $content_preview_length);
    }

    public function handleCreateNote() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $category_id = $_POST['category_id'] ?? 0;
            $category_id = ($category_id == 0 ? null : $category_id);
            $title = $_POST['title'] ?? '';
            $content = $_POST['content'] ?? '';
            $file_path = $this->handleFileUpload();
            $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;

            $this->db->beginTransaction();
            try {
                $note_id = $this->stickyNote->create($this->user->getUserId(), $category_id, $title, $content, $file_path, $is_pinned);

                if ($note_id) {
                    $updatedContent = $this->processNoteImages($this->user->getUserId(), $note_id, $content);
                    
                    if ($updatedContent !== $content) {
                        $updateResult = $this->stickyNote->updateNoteContent($note_id, $updatedContent);
                        if (!$updateResult) {
                            throw new \RuntimeException('Failed to update note content after image processing.');
                        }
                    }
                    
                    $this->db->commit();
                    return ['success' => true, 'note_id' => $note_id];
                } else {
                    throw new \RuntimeException('Failed to create note.');
                }
            } catch (\Exception $e) {
                $this->db->rollback();
                return ['success' => false, 'message' => $e->getMessage()];
            }
        }
        return ['success' => false, 'message' => 'Invalid request'];
    }

    public function handleEditNote() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $note_id = $_POST['note_id'] ?? null;
            $title = $_POST['title'] ?? '';
            $content = $_POST['content'] ?? '';
            $file_path = $this->handleFileUpload();
            $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;

            if ($note_id) {
                $this->db->beginTransaction();
                try {
                    $updatedContent = $this->processNoteImages($this->user->getUserId(), $note_id, $content);
                    
                    $result = $this->stickyNote->edit($note_id, $this->user->getUserId(), $title, $updatedContent, $file_path, $is_pinned);
                    
                    if ($result) {
                        $this->db->commit();
                        return ['success' => true];
                    } else {
                        throw new \RuntimeException('Failed to edit note.');
                    }
                } catch (\Exception $e) {
                    $this->db->rollback();
                    return ['success' => false, 'message' => $e->getMessage()];
                }
            }
        }
        return ['success' => false, 'message' => 'Invalid request'];
    }

    public function handleDeleteNote() {
        if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            $note_id = $_POST['note_id'] ?? null;
            if ($note_id) {
                $this->db->beginTransaction();
                try {
                    // Get the images associated with this note
                    $images = $this->stickyNote->getNoteImages($note_id, $this->user->getUserId());
                    
                    // Delete the note
                    $result = $this->stickyNote->delete($note_id, $this->user->getUserId());
                    
                    if ($result) {
                        // If note deletion was successful, delete the associated images
                        foreach ($images as $image) {
                            $this->deleteImageFile($image['image_path']);
                        }
                        $this->db->commit();
                        return ['success' => true];
                    } else {
                        throw new \Exception('Failed to delete note');
                    }
                } catch (\Exception $e) {
                    $this->db->rollback();
                    return ['success' => false, 'message' => $e->getMessage()];
                }
            }
        }
        return ['success' => false, 'message' => 'Invalid request'];
    }

    private function deleteImageFile($filePath) {
        $fullPath = $_SERVER['DOCUMENT_ROOT'] . $filePath;
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

    public function handleMoveNote() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $note_id = $_POST['note_id'] ?? null;
            $new_category_id = $_POST['new_category_id'] ?? null;

            if ($note_id && $new_category_id !== null) {
                $result = $this->stickyNote->move($note_id, $this->user->getUserId(), $new_category_id);
                return $result ? ['success' => true] : ['success' => false, 'message' => 'Failed to move note'];
            }
        }
        return ['success' => false, 'message' => 'Invalid request'];
    }

/*     public function handleSearchNotes() {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $search_term = $_GET['search'] ?? '';
            $results = $this->stickyNote->search($this->user->getUserId(), $search_term);
            return ['success' => true, 'results' => $results];
        }
        return ['success' => false, 'message' => 'Invalid request'];
    } */

    public function handleSearchNotes($search_term, $category_id = null) {
        // If search term is empty, return all notes
        /* if (empty($search_term)) {
            return $this->getNotes(100, $category_id);
        } */
        return $this->stickyNote->search($this->user->getUserId(), $search_term, $category_id, 120);
    }

    public function handleDuplicateNote() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $note_id = $_POST['note_id'] ?? null;

            if ($note_id) {
                $new_note_id = $this->stickyNote->duplicate($note_id, $this->user->getUserId());
                return $new_note_id ? ['success' => true, 'new_note_id' => $new_note_id] : ['success' => false, 'message' => 'Failed to duplicate note'];
            }
        }
        return ['success' => false, 'message' => 'Invalid request'];
    }

    public function handleCreateCategory() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = $_POST['title'] ?? '';
            $color = $_POST['color'] ?? '#b2e4e5';

            if(strlen($title) < 3) {
                return ['success' => false, 'message' => 'Too short category title'];
            }

            $nbrCategories = $this->stickyCategory->getNbrCategories($this->user->getUserId());
            if ($nbrCategories > 30) {
                return ['success' => false, 'message' => 'Too Many categories'];
            }

            $category_id = $this->stickyCategory->create($this->user->getUserId(), $title, $color);
            return $category_id ? ['success' => true, 'category_id' => $category_id] : ['success' => false, 'message' => 'Failed to create category'];
        }
        return ['success' => false, 'message' => 'Invalid request'];
    }

    public function handleEditCategory() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $category_id    = $_POST['category_id'] ?? null;
            $title          = $_POST['title'] ?? '';
            $color          = $_POST['color'] ?? '#b2e4e5';

            if(strlen($title) < 3) {
                return ['success' => false, 'message' => 'Too short category title'];
            }

            if ($category_id) {
                $result = $this->stickyCategory->edit($category_id, $this->user->getUserId(), $title, $color);
                return $result ? ['success' => true] : ['success' => false, 'message' => 'Failed to edit category'];
            }
        }
        return ['success' => false, 'message' => 'Invalid request'];
    }

    public function handleDeleteCategory() {
        if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            $category_id            = $_GET['category_id'] ?? null;
            $move_to_category_id    = $_GET['move_to_category_id'] ?? null;

            if ($category_id) {
                $result = $this->stickyCategory->delete($category_id, $this->user->getUserId(), $move_to_category_id);
                return ['success' => true];
            }
        }
        return ['success' => false, 'message' => 'Invalid requestssss'];
    }

    private function handleFileUpload() {
        // Implement file upload logic here
        // Return the file path if successful, null otherwise
        return null;
    }


    private function processNoteImages($userId, $noteId, $noteContent) {
        return $this->getImageService()->processAndPersist($userId, $noteId, $noteContent, 'stickynote');
    }

    private function getImageService(): ItemImageService {
        return new ItemImageService($this->db);
    }
}

?>